<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Record ID.");
}

$id = intval($_GET['id']);

// Fetch header - now from purchase_order_allocations
$stmt = $conn->prepare("
    SELECT poa.id as allocation_id,
           po.po_number, 
           poa.invoice_number,
           po.supplier_company as supplier, 
           poa.branch_name,
           b.branch_code,
           po.po_date as invoice_date,
           poa.receiving_remarks as notes,
           poa.received_by,
           poa.received_at,
           po.created_at
    FROM purchase_order_allocations poa
    INNER JOIN purchase_orders po ON poa.po_id = po.id
    LEFT JOIN branches b ON poa.branch_name COLLATE utf8mb4_general_ci = b.branch_name COLLATE utf8mb4_general_ci
    WHERE poa.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Record not found.");
}

$header = $res->fetch_assoc();
$po_id = null;

// Get the PO ID for fetching items
$stmt_po = $conn->prepare("SELECT po_id FROM purchase_order_allocations WHERE id = ?");
$stmt_po->bind_param("i", $id);
$stmt_po->execute();
$res_po = $stmt_po->get_result();
if ($res_po->num_rows > 0) {
    $po_data = $res_po->fetch_assoc();
    $po_id = $po_data['po_id'];
}
$stmt_po->close();
$stmt->close();

if (!$po_id) {
    die("Invalid PO reference.");
}

// Fetch items - from purchase_order_allocations for the specific branch
$items = [];
$branch_name = $header['branch_name'];
$stmt_items = $conn->prepare("
    SELECT 
        poa.quantity as alloc_quantity,
        poa.received_qty as received_quantity, 
        poa.cost as actual_cost, 
        poa.serial_number as serials,
        poa.imei_2 as serials2,
        poa.item_model as item_code, 
        poa.item_description,
        i.department as department,
        COALESCE(MAX(CASE 
            WHEN poa.item_model IS NOT NULL 
                AND poa.item_model != '' 
                AND poa.item_model != '-' 
                AND poa.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
            THEN i.has_serial 
            ELSE 0 
        END), 0) as has_serial
    FROM purchase_order_allocations poa 
    LEFT JOIN items i ON poa.family_code COLLATE utf8mb4_general_ci = i.family_code COLLATE utf8mb4_general_ci
    WHERE poa.po_id = ?
    AND poa.branch_name COLLATE utf8mb4_general_ci = ?
    AND poa.invoice_number = ?
    GROUP BY poa.id
    ORDER BY poa.id ASC
");
$stmt_items->bind_param("iss", $po_id, $branch_name, $header['invoice_number']);
$stmt_items->execute();
$res_items = $stmt_items->get_result();
while ($row = $res_items->fetch_assoc()) {
    $alloc_qty = (int)($row['alloc_quantity'] ?? 1);
    if ((int)$row['has_serial'] === 1) {
        $sn = trim($row['serials'] ?? '');
        if (!empty($sn)) {
            $sn_normalized = str_replace(["\r\n", "\n", "\r", "<br>", "<br/>", "<br />", "&"], ",", $sn);
            $sn_arr = array_filter(array_map('trim', explode(',', $sn_normalized)));
            $recv_qty = count($sn_arr);
        } else {
            $recv_qty = 0;
        }
    } else {
        $recv_qty = (int)($row['received_quantity'] ?? 0);
    }
    
    if ($alloc_qty > 0 && $recv_qty > $alloc_qty) {
        $recv_qty = $alloc_qty;
    }
    
    $row['received_quantity'] = $recv_qty;
    $items[] = $row;
}
$stmt_items->close();

// Also fetch "Add New Item" (is_receive_added) rows for this branch.
// These items were added during receiving and are stored in purchase_order_items
// with is_receive_added=1 and receiving_branch set to the branch name.
// They are NOT in purchase_order_allocations, so they were missing from the PDF.
$branch_esc_rdd = $conn->real_escape_string($branch_name);
$receive_added_items_result = $conn->query("
    SELECT
        poi.received_qty,
        poi.cost as actual_cost,
        poi.serial_number as serials,
        poi.imei_2 as serials2,
        poi.item_model as item_code,
        poi.item_description,
        poi.quantity,
        i.department as department,
        COALESCE(MAX(i.has_serial), 0) as has_serial
    FROM purchase_order_items poi
    LEFT JOIN items i ON poi.family_code = i.family_code
    WHERE poi.po_id = $po_id
    AND COALESCE(poi.is_receive_added, 0) = 1
    AND poi.receiving_branch = '$branch_esc_rdd'
    GROUP BY poi.id
    ORDER BY poi.id ASC
");
if ($receive_added_items_result && $receive_added_items_result->num_rows > 0) {
    while ($ra = $receive_added_items_result->fetch_assoc()) {
        // Mirror viewpurchaseorder.php: for serialized items, received_quantity = count of serial numbers;
        // for non-serialized items, received_quantity = received_qty field.
        if ((int)$ra['has_serial'] === 1) {
            $sn = trim($ra['serials'] ?? '');
            if (!empty($sn)) {
                $sn_arr = (strpos($sn, "\n") !== false)
                    ? explode("\n", $sn)
                    : explode(",", $sn);
                $recv_qty = count(array_filter(array_map('trim', $sn_arr)));
            } else {
                $recv_qty = 0;
            }
        } else {
            $recv_qty = (int)($ra['received_qty'] ?? 0);
        }
        $items[] = [
            'received_quantity' => $recv_qty,
            'actual_cost'       => $ra['actual_cost'],
            'serials'           => $ra['serials'],
            'serials2'          => $ra['serials2'],
            'item_code'         => $ra['item_code'],
            'item_description'  => $ra['item_description'],
            'department'        => $ra['department'],
        ];
    }
}

$branch_text = ($header['branch_name'] ? $header['branch_name'] : 'Unknown') . ' - ' . ($header['branch_code'] ? $header['branch_code'] : 'UNK');
$invoice_date_txt = (!empty($header['invoice_date']) && $header['invoice_date'] != '1970-01-01') ? date('F d, Y', strtotime($header['invoice_date'])) : '';
$encoded_date_txt = (!empty($header['received_at'])) ? date('F d, Y H:i:s', strtotime($header['received_at'])) : '';

$total_qty = 0;
$overall_total_amt = 0;

// Calculate totals
foreach ($items as $row) {
    $rcv_qty = floatval($row['received_quantity']);
    $total_qty += $rcv_qty;
    $amt = floatval($row['actual_cost']);
    $tot_amt = $amt * $rcv_qty;
    $overall_total_amt += $tot_amt;
}

// Create PDF
class PDF extends FPDF
{
    function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        $this->SetTextColor(0, 0, 0); // Black color
        $this->SetFont('Courier', 'B', 10);
        $this->Cell(0, 10, 'PAGE 1 OF 1', 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);

// Logo and Title on same line
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 10, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(20);
$pdf->Cell(0, 10, 'RECEIVE DIRECT DELIVERY', 0, 1, 'C');
$pdf->Ln(5);

// Meta information
$pdf->SetFont('Courier', 'B', 9);

$pdf->Cell(95, 6, 'P.O NO: ' . $header['po_number'], 0, 0, 'L');
$pdf->Cell(95, 6, 'BRANCH RECEIVED: ' . $branch_text, 0, 1, 'R');

$pdf->Cell(95, 6, 'INVOICE NO: ' . ($header['invoice_number'] ?? '-'), 0, 0, 'L');
$pdf->Cell(95, 6, 'SUPPLIER: ' . $header['supplier'], 0, 1, 'R');

$pdf->Cell(95, 6, 'INVOICE DATE: ' . $invoice_date_txt, 0, 0, 'L');
$pdf->Cell(95, 6, 'INVOICE ENCODED: ' . $encoded_date_txt, 0, 1, 'R');

$pdf->Cell(95, 6, 'NOTE: ' . (isset($header['notes']) ? $header['notes'] : ''), 0, 1, 'L');

$pdf->Ln(5);

// Table Header
$pdf->SetFont('Courier', 'B', 7);
$pdf->SetFillColor(240, 240, 240);

$pdf->Cell(38, 8, 'ITEM CODE', 1, 0, 'C', true);
$pdf->Cell(50, 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
$pdf->Cell(50, 8, 'IMEI', 1, 0, 'C', true);
$pdf->Cell(12, 8, 'QTY', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'AMOUNT', 1, 0, 'C', true);

// Multi-line header for TOTAL AMOUNT
$x = $pdf->GetX();
$y = $pdf->GetY();
$pdf->MultiCell(20, 4, "TOTAL\nAMOUNT", 1, 'C', true);
$pdf->SetXY($x + 20, $y + 8);

// Table Body
$pdf->SetFont('Courier', '', 8);

if (count($items) === 0) {
    $pdf->Cell(190, 8, 'No Items', 1, 1, 'C');
} else {
    foreach ($items as $row) {
        $rcv_qty = floatval($row['received_quantity']);
        $amt = floatval($row['actual_cost']);
        $tot_amt = $amt * $rcv_qty;
        
        // ── Parse primary serials (IMEI / Serial Number) and IMEI 2 ──
        $raw_s1 = !empty($row['serials']) ? $row['serials'] : '';
        $raw_s2 = !empty($row['serials2']) ? $row['serials2'] : '';

        $serials1 = !empty($raw_s1)
            ? array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw_s1)), 'strlen'))
            : [];
        $serials2 = !empty($raw_s2)
            ? array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw_s2)), 'strlen'))
            : [];

        $serial_count = max(count($serials1), count($serials2));
        $has_secondary = count($serials2) > 0;

        // Determine secondary label
        $is_tablet_sn = (strcasecmp($row['department'] ?? '', 'TABLET') === 0);
        $sec_label = $is_tablet_sn ? 'S/N' : 'IMEI 2';
        
        // Handle wrapping for item code and item description
        $item_code = $row['item_code'];
        $item_description = $row['item_description'];
        
        // Calculate character limits based on cell width and font
        $pdf->SetFont('Courier', '', 8);
        $char_width = $pdf->GetStringWidth('A'); // Average character width
        
        // Calculate max characters that fit in each cell (with small padding)
        $code_max_chars = floor(36 / $char_width); // 38mm cell - 2mm padding
        $desc_max_chars = floor(46 / $char_width); // 50mm cell - 4mm padding
        
        // Wrap text to fit within cell width
        $code_lines = explode("\n", wordwrap($item_code, $code_max_chars, "\n", true));
        $desc_lines = explode("\n", wordwrap($item_description, $desc_max_chars, "\n", true));
        
        // Recalculate row height based on max lines needed (code, desc, or serial)
        $row_height = max(6, $serial_count * 4, count($code_lines) * 4, count($desc_lines) * 4);
        
        $x = 15; // Left margin
        $y = $pdf->GetY();
        
        // Draw all cell borders (new order: ITEM CODE, DESCRIPTION, IMEI, QTY, AMOUNT, TOTAL)
        $pdf->Rect($x, $y, 38, $row_height);
        $pdf->Rect($x + 38, $y, 50, $row_height);
        $pdf->Rect($x + 88, $y, 50, $row_height);
        $pdf->Rect($x + 138, $y, 12, $row_height);
        $pdf->Rect($x + 150, $y, 20, $row_height);
        $pdf->Rect($x + 170, $y, 20, $row_height);
        
        // Calculate vertical center for single-line cells
        $v_center = $y + ($row_height / 2) - 2;
        
        // Draw ITEM CODE (left aligned) with line breaks - clipped to cell
        $code_start_y = $y + (($row_height - (count($code_lines) * 4)) / 2);
        $code_y = $code_start_y;
        foreach ($code_lines as $line) {
            $pdf->SetXY($x, $code_y);
            $pdf->Cell(38, 4, $line, 0, 0, 'L', false);
            $code_y += 4;
        }
        
        // Draw ITEM DESCRIPTION (left aligned) with line breaks - clipped to cell
        $desc_start_y = $y + (($row_height - (count($desc_lines) * 4)) / 2);
        $desc_y = $desc_start_y;
        foreach ($desc_lines as $line) {
            $pdf->SetXY($x + 38, $desc_y);
            $pdf->Cell(50, 4, $line, 0, 0, 'L', false);
            $desc_y += 4;
        }
        
        // Draw IMEI with line breaks (centered in cell) - showing both IMEI and IMEI 2
        if ($serial_count > 0) {
            $imei_start_y = $y + (($row_height - ($serial_count * 4)) / 2);
            $imei_y = $imei_start_y;
            
            for ($idx = 0; $idx < $serial_count; $idx++) {
                $s1 = $serials1[$idx] ?? '';
                $s2 = $serials2[$idx] ?? '';

                // Build the line text
                $line_text = $s1;
                if ($s2 !== '') {
                    $line_text .= ' (' . $sec_label . ': ' . $s2 . ')';
                }

                $pdf->SetXY($x + 88, $imei_y);
                
                // Check if primary serial length is exactly 15 characters
                $serial_length = strlen($s1);
                if ($serial_length != 15 && $serial_length > 0) {
                    // Set text color to red if not exactly 15 characters
                    $pdf->SetTextColor(255, 0, 0);
                } else {
                    // Set text color to black if exactly 15 characters or empty
                    $pdf->SetTextColor(0, 0, 0);
                }
                
                // Use smaller font if line is too long
                $line_width = $pdf->GetStringWidth($line_text);
                if ($line_width > 48) {
                    $pdf->SetFont('Courier', '', 6);
                } else {
                    $pdf->SetFont('Courier', '', 8);
                }
                
                $pdf->Cell(50, 4, $line_text, 0, 0, 'C');
                $imei_y += 4;
                
                // Reset font
                $pdf->SetFont('Courier', '', 8);
            }
            // Reset text color to black for subsequent cells
            $pdf->SetTextColor(0, 0, 0);
        } else {
            // No IMEI
            $pdf->SetXY($x + 88, $v_center);
            $pdf->Cell(50, 4, '-', 0, 0, 'C');
        }
        
        // Draw QTY (centered) - now after IMEI
        $pdf->SetXY($x + 138, $v_center);
        $pdf->Cell(12, 4, $rcv_qty, 0, 0, 'C');
        
        // Draw AMOUNT (right aligned)
        $pdf->SetXY($x + 150, $v_center);
        $pdf->Cell(20, 4, number_format($amt, 2), 0, 0, 'R');
        
        // Draw TOTAL AMOUNT (right aligned)
        $pdf->SetXY($x + 170, $v_center);
        $pdf->Cell(20, 4, number_format($tot_amt, 2), 0, 0, 'R');
        
        // Move to next row
        $pdf->SetXY($x, $y + $row_height);
    }
}

$pdf->Ln(5);

// Summary
$pdf->SetFont('Courier', 'B', 10);
$pdf->SetTextColor(211, 47, 47);

$pdf->Cell(50, 6, 'TOTAL QUANTITY:', 0, 0, 'L');
$pdf->Cell(20, 6, $total_qty, 0, 1, 'L');

$pdf->Cell(50, 6, 'TOTAL AMOUNT:', 0, 0, 'L');
$pdf->Cell(20, 6, number_format($overall_total_amt, 2), 0, 1, 'L');

// Output PDF
$pdf->Output('I', 'RDD_' . $header['invoice_number'] . '.pdf');
?>