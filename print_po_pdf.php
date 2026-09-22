<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

// Validate ID
$po_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($po_id <= 0) {
    die("Invalid Purchase Order ID");
}

// Fetch PO header
$sql = "SELECT * FROM purchase_orders WHERE id = $po_id LIMIT 1";
$po_result = $conn->query($sql);
if (!$po_result || $po_result->num_rows === 0) {
    die("Purchase Order not found");
}
$po = $po_result->fetch_assoc();

// Fetch PO items with serial numbers, family code, department and serial config
$collate = 'utf8mb4_general_ci';
$items_result = $conn->query("
    SELECT poi.*,
           COALESCE(
               NULLIF((SELECT poa.serial_number FROM purchase_order_allocations poa
                WHERE poa.po_id = poi.po_id
                  AND poa.family_code COLLATE $collate = poi.family_code COLLATE $collate
                  AND (poa.item_model COLLATE $collate = poi.item_model COLLATE $collate OR poi.item_model IS NULL OR poi.item_model = '' OR poi.item_model = '-')
                  AND poa.serial_number IS NOT NULL AND poa.serial_number != ''
                LIMIT 1), ''),
               poi.serial_number
           ) AS serial_number,
           COALESCE(
               NULLIF((SELECT poa.imei_2 FROM purchase_order_allocations poa
                WHERE poa.po_id = poi.po_id
                  AND poa.family_code COLLATE $collate = poi.family_code COLLATE $collate
                  AND (poa.item_model COLLATE $collate = poi.item_model COLLATE $collate OR poi.item_model IS NULL OR poi.item_model = '' OR poi.item_model = '-')
                  AND poa.imei_2 IS NOT NULL AND poa.imei_2 != ''
                LIMIT 1), ''),
               poi.imei_2
           ) AS imei_2,
           COALESCE(MAX(CASE 
               WHEN poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' 
                    AND poi.item_model COLLATE $collate = i.item_code COLLATE $collate
               THEN i.has_serial 
               ELSE 0 
           END), MAX(i.has_serial), 0) AS has_serial,
           COALESCE(MAX(CASE 
               WHEN poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' 
                    AND poi.item_model COLLATE $collate = i.item_code COLLATE $collate
               THEN i.has_serial_number 
               ELSE 0 
           END), MAX(i.has_serial_number), 0) AS has_serial_number,
           COALESCE(MAX(i.department), '') AS department
    FROM purchase_order_items poi
    LEFT JOIN items i ON (
        (poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' AND poi.item_model COLLATE $collate = i.item_code COLLATE $collate)
        OR (poi.family_code COLLATE $collate = i.family_code COLLATE $collate)
    )
    WHERE poi.po_id = $po_id
    GROUP BY poi.id
    ORDER BY poi.item_no ASC
");
$items = [];
$grand_total = 0;

// Check for live layout serial data (support both POST and GET)
$live_serials = [];
$live_serials2 = [];
$serial_data = null;
$serial_data2 = null;

// Try POST first (for backward compatibility)
if (isset($_POST['live_serials']) && !empty($_POST['live_serials'])) {
    $serial_data = $_POST['live_serials'];
} elseif (isset($_GET['live_serials']) && !empty($_GET['live_serials'])) {
    $serial_data = $_GET['live_serials'];
}

if (isset($_POST['live_serials2']) && !empty($_POST['live_serials2'])) {
    $serial_data2 = $_POST['live_serials2'];
} elseif (isset($_GET['live_serials2']) && !empty($_GET['live_serials2'])) {
    $serial_data2 = $_GET['live_serials2'];
}

if ($serial_data) {
    $decoded = json_decode($serial_data, true);
    if (is_array($decoded)) {
        $live_serials = $decoded;
    }
}

if ($serial_data2) {
    $decoded2 = json_decode($serial_data2, true);
    if (is_array($decoded2)) {
        $live_serials2 = $decoded2;
    }
}

function resolveLiveSerialsList($live_map, $item) {
    if (!is_array($live_map) || empty($live_map)) return null;
    $keys = [
        ($item['family_code'] ?? '') . '-' . ($item['item_no'] ?? ''),
        $item['item_model'] ?? '',
        $item['family_code'] ?? '',
        (string)($item['item_no'] ?? ''),
        (string)($item['id'] ?? '')
    ];
    foreach ($keys as $k) {
        if ($k !== '' && isset($live_map[$k])) {
            $val = $live_map[$k];
            if (is_array($val) && count($val) > 0) {
                return $val;
            } elseif (is_string($val) && trim($val) !== '') {
                return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $val)), 'strlen'));
            }
        }
    }
    return null;
}

if ($items_result) {
    while ($item = $items_result->fetch_assoc()) {
        // Override primary serials if live serial numbers exist
        $ls1 = resolveLiveSerialsList($live_serials, $item);
        if ($ls1 !== null && count($ls1) > 0) {
            $item['serial_number'] = implode(',', $ls1);
        }

        // Override secondary serials if live IMEI 2 exists
        $ls2 = resolveLiveSerialsList($live_serials2, $item);
        if ($ls2 !== null && count($ls2) > 0) {
            $item['imei_2'] = implode(',', $ls2);
        }

        $items[] = $item;
        $grand_total += (float)$item['total'];
    }
}

// Create PDF with custom footer
class PDF extends FPDF
{
    function Footer()
    {
        $this->SetY(-15);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Courier', 'B', 10);
        $this->Cell(0, 10, 'PAGE 1 OF 1', 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(10, 15, 10);
$pdf->SetAutoPageBreak(true, 20);

// Logo and Title
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 10, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(20);
$pdf->Cell(0, 10, 'PURCHASE ORDER', 0, 1, 'C');
$pdf->Ln(5);

// Meta information - Left and Right columns
$pdf->SetFont('Courier', 'B', 9);
$pdf->SetTextColor(0, 0, 0);

// Left column
$pdf->Cell(95, 6, 'PO NUMBER: ' . $po['po_number'], 0, 0, 'L');
// Right column
$pdf->Cell(95, 6, 'SUPPLIER: ' . ($po['supplier_name'] ?? '-'), 0, 1, 'R');

$pdf->Cell(95, 6, 'STATUS: ' . strtoupper($po['status']), 0, 0, 'L');
$pdf->Cell(95, 6, 'PO DATE: ' . date('F d, Y', strtotime($po['po_date'])), 0, 1, 'R');

$pdf->Cell(95, 6, 'TERMS: ' . ($po['terms'] ?? '-'), 0, 0, 'L');
$pdf->Cell(95, 6, 'PAYMENT DUE DATE: ' . (!empty($po['payment_due_date']) ? date('F d, Y', strtotime($po['payment_due_date'])) : '-'), 0, 1, 'R');

$pdf->Cell(95, 6, 'SUPPLIER COMPANY: ' . ($po['supplier_company'] ?? '-'), 0, 0, 'L');
$pdf->Cell(95, 6, 'CONTACT: ' . ($po['contact_number'] ?? '-'), 0, 1, 'R');

$pdf->Cell(95, 6, 'ADDRESS: ' . ($po['address'] ?? '-'), 0, 0, 'L');
$pdf->Cell(95, 6, 'REMARKS: ' . ($po['remarks'] ?? '-'), 0, 1, 'R');

$pdf->Ln(5);

// ── Column layout (total usable = 190mm with 10mm margins on each side) ──
// NO: 8 | FAMILY: 18 | MODEL: 28 | DESCRIPTION: 44 | QTY: 8 | COST: 16 | TOTAL: 16 | SERIAL: 52
// Total = 8+18+28+44+8+16+16+52 = 190
$col = [
    'no'     => 8,
    'fam'    => 18,
    'model'  => 28,
    'desc'   => 44,
    'qty'    => 8,
    'cost'   => 16,
    'total'  => 16,
    'serial' => 52,
];
$x0 = 10; // left margin

// Table Header
$pdf->SetFont('Courier', 'B', 7);
$pdf->SetFillColor(240, 240, 240);

$pdf->Cell($col['no'],    8, 'NO',               1, 0, 'C', true);
$pdf->Cell($col['fam'],   8, 'FAMILY CODE',      1, 0, 'C', true);
$pdf->Cell($col['model'], 8, 'MODEL',            1, 0, 'C', true);
$pdf->Cell($col['desc'],  8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
$pdf->Cell($col['qty'],   8, 'QTY',              1, 0, 'C', true);
$pdf->Cell($col['cost'],  8, 'COST',             1, 0, 'C', true);

// Multi-line header for TOTAL AMOUNT
$hx = $pdf->GetX();
$hy = $pdf->GetY();
$pdf->Rect($hx, $hy, $col['total'], 8, 'FD');
$pdf->SetXY($hx, $hy + 1);
$pdf->Cell($col['total'], 3, 'TOTAL',  0, 2, 'C');
$pdf->SetX($hx);
$pdf->Cell($col['total'], 3, 'AMOUNT', 0, 0, 'C');

$pdf->SetXY($hx + $col['total'], $hy);
$pdf->Cell($col['serial'], 8, ' IMEI / SERIAL', 1, 1, 'C', true);

// Table Body
$base_font_size = 8; // Base font size
$min_font_size = 4;  // Minimum font size for readability

// Helper function to auto-scale text to fit in column
function drawAutoScaledCell(&$pdf, $x, $y, $width, $height, $text, $align, $base_font, $min_font, $font_style = '', $r = 0, $g = 0, $b = 0) {
    $pdf->SetFont('Courier', $font_style, $base_font);
    $text_width = $pdf->GetStringWidth($text);
    
    // Calculate if we need to scale down
    $available_width = $width - 2; // Leave 1mm padding on each side
    
    if ($text_width > $available_width) {
        // Calculate scale factor
        $scale_factor = $available_width / $text_width;
        $new_font_size = max($base_font * $scale_factor, $min_font);
        $pdf->SetFont('Courier', $font_style, $new_font_size);
    }
    
    // Set text color
    $pdf->SetTextColor($r, $g, $b);
    
    // Draw the text
    $pdf->SetXY($x, $y);
    $pdf->Cell($width, $height, $text, 0, 0, $align);
    
    // Reset to black
    $pdf->SetTextColor(0, 0, 0);
}

if (count($items) === 0) {
    $pdf->SetFont('Courier', '', $base_font_size);
    $pdf->Cell(190, 8, 'NO ITEMS', 1, 1, 'C');
} else {
    foreach ($items as $item) {
        // ── Parse primary serials (IMEI / Serial Number) ──
        $raw_s1 = !empty($item['serial_number']) ? $item['serial_number'] : '';
        $raw_s2 = !empty($item['imei_2'])        ? $item['imei_2']        : '';

        $serials1 = !empty($raw_s1)
            ? array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw_s1)), 'strlen'))
            : [];
        $serials2 = !empty($raw_s2)
            ? array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw_s2)), 'strlen'))
            : [];

        $serial_count  = max(count($serials1), count($serials2));
        $has_secondary = count($serials2) > 0;

        // Determine secondary label
        $is_tablet_sn = (strcasecmp($item['department'] ?? '', 'TABLET') === 0 && !empty($item['has_serial_number']));
        $sec_label = $is_tablet_sn ? 'S/N' : 'IMEI 2';

        // Prepare text with fallback for empty values
        $family_code_text = !empty($item['family_code']) ? $item['family_code'] : '-';
        $model_text = !empty($item['item_model']) ? $item['item_model'] : '-';
        $desc_text = !empty($item['item_description']) ? $item['item_description'] : '-';

        // ── Row height calculation ──
        $line_h            = 3.5; // mm per serial pair line
        $min_row_h         = 10;
        $serial_block_h    = max(1, $serial_count) * $line_h + 3; // +3 top/bottom padding
        $calculated_height = max($min_row_h, $serial_block_h);

        $x = $x0;
        $y = $pdf->GetY();

        // Check if we need a new page
        if ($y + $calculated_height > 270) {
            $pdf->AddPage();
            $y = $pdf->GetY();
        }

        // ── Draw cell borders ──
        $cx = $x;
        $pdf->Rect($cx, $y, $col['no'],    $calculated_height); $cx += $col['no'];
        $pdf->Rect($cx, $y, $col['fam'],   $calculated_height); $cx += $col['fam'];
        $pdf->Rect($cx, $y, $col['model'], $calculated_height); $cx += $col['model'];
        $pdf->Rect($cx, $y, $col['desc'],  $calculated_height); $cx += $col['desc'];
        $pdf->Rect($cx, $y, $col['qty'],   $calculated_height); $cx += $col['qty'];
        $pdf->Rect($cx, $y, $col['cost'],  $calculated_height); $cx += $col['cost'];
        $pdf->Rect($cx, $y, $col['total'], $calculated_height); $cx += $col['total'];
        $pdf->Rect($cx, $y, $col['serial'],$calculated_height);

        // Vertical center for text (non-serial columns)
        $vc = $y + ($calculated_height / 2) - 2;

        // Draw NO
        $pdf->SetFont('Courier', '', $base_font_size);
        $pdf->SetXY($x, $vc);
        $pdf->Cell($col['no'], 5, $item['item_no'], 0, 0, 'C');

        // Draw FAMILY CODE with auto-scaling
        drawAutoScaledCell($pdf, $x + $col['no'], $vc, $col['fam'], 5, $family_code_text, 'C', $base_font_size, 3);

        // Draw MODEL with auto-scaling
        drawAutoScaledCell($pdf, $x + $col['no'] + $col['fam'], $vc, $col['model'], 5, $model_text, 'C', $base_font_size, $min_font_size);

        // Draw ITEM DESCRIPTION with auto-scaling
        drawAutoScaledCell($pdf, $x + $col['no'] + $col['fam'] + $col['model'], $vc, $col['desc'], 5, $desc_text, 'L', $base_font_size, $min_font_size);

        // Draw QTY
        $qx = $x + $col['no'] + $col['fam'] + $col['model'] + $col['desc'];
        $pdf->SetFont('Courier', '', $base_font_size);
        $pdf->SetXY($qx, $vc);
        $pdf->Cell($col['qty'], 5, $item['quantity'], 0, 0, 'C');

        // Draw COST
        $pdf->SetXY($qx + $col['qty'], $vc);
        $pdf->Cell($col['cost'], 5, number_format($item['cost'], 2), 0, 0, 'R');

        // Draw TOTAL AMOUNT
        $pdf->SetXY($qx + $col['qty'] + $col['cost'], $vc);
        $pdf->Cell($col['total'], 5, number_format($item['total'], 2), 0, 0, 'C');

        // ── SERIAL block ──
        $sx = $x + $col['no'] + $col['fam'] + $col['model'] + $col['desc'] + $col['qty'] + $col['cost'] + $col['total'];

        if ($serial_count > 0) {
            $sy = $y + 1.8; // top padding inside cell

            for ($idx = 0; $idx < $serial_count; $idx++) {
                $s1 = $serials1[$idx] ?? '';
                $s2 = $serials2[$idx] ?? '';

                $prefix = ($serial_count > 1) ? ($idx + 1) . '. ' : '';
                $line_text = $prefix . $s1;
                if ($s2 !== '') {
                    $line_text .= ' (' . $sec_label . ': ' . $s2 . ')';
                }

                drawAutoScaledCell($pdf, $sx + 1, $sy, $col['serial'] - 2, $line_h, $line_text, 'L', 5.5, 3.8, '', 0, 0, 0);
                $sy += $line_h;
            }
        } else {
            // No serials, just show dash
            $pdf->SetFont('Courier', '', $base_font_size);
            $pdf->SetXY($sx, $vc);
            $pdf->Cell($col['serial'], 5, '-', 0, 0, 'C');
        }

        // Move to next row
        $pdf->SetXY($x, $y + $calculated_height);
    }
}

$pdf->Ln(5);

// Grand Total
$pdf->SetFont('Courier', 'B', 10);
$pdf->SetTextColor(211, 47, 47);

$pdf->Cell(50, 6, 'GRAND TOTAL:', 0, 0, 'L');
$pdf->Cell(30, 6, number_format($grand_total, 2), 0, 1, 'L');

$pdf->Ln(15);

// Signature section
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Courier', 'B', 9);
$pdf->Line(10, $pdf->GetY(), 65, $pdf->GetY()); // Signature line
$pdf->Ln(2);
$pdf->Cell(50, 6, 'SIGNATURE', 0, 1, 'L');

// Output PDF
$pdf->Output('I', 'PO_' . $po['po_number'] . '.pdf');
?>
