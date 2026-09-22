<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

// Validate ST Number
$st_number = isset($_GET['st_number']) ? trim($_GET['st_number']) : '';
if (empty($st_number)) {
    die("Invalid Stock Transfer Number");
}

// Fetch transfer header with prepared_by full name, approver full name, branch full names
$stmt = $conn->prepare("
    SELECT 
        st.*,
        CONCAT(acc.first_name, ' ', acc.last_name) as prepared_by_full_name,
        CONCAT(app_acc.first_name, ' ', app_acc.last_name) as approver_full_name,
        app_acc.system_level as approver_system_level,
        b_from.branch_name as branch_from_full_name,
        b_to.branch_name as branch_to_full_name
    FROM stock_transfers st
    LEFT JOIN accounts acc ON st.prepared_by = acc.username
    LEFT JOIN accounts app_acc ON st.approver = app_acc.username
    LEFT JOIN branches b_from ON st.branch_from = b_from.branch_code
    LEFT JOIN branches b_to ON st.branch_to = b_to.branch_code
    WHERE st.st_number = ?
");
$stmt->bind_param("s", $st_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Stock Transfer not found");
}

$transfer = $result->fetch_assoc();
$stmt->close();

// Fetch transfer items with prices
$stmt = $conn->prepare("
    SELECT 
        sti.*,
        i.id as item_id,
        COALESCE(
            (SELECT price FROM item_prices 
             WHERE item_id = i.id 
             AND branch = ? 
             AND price_type = 'SRP' 
             LIMIT 1),
            i.srp,
            0
        ) as amount
    FROM stock_transfer_items sti
    LEFT JOIN items i ON sti.item_code = i.item_code
    WHERE sti.st_number = ?
    ORDER BY sti.id
");
$branch_to = $transfer['branch_to'];
$stmt->bind_param("ss", $branch_to, $st_number);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
$total_quantity = 0;
$total_amount = 0;

while ($row = $result->fetch_assoc()) {
    $items[] = $row;
    $total_quantity += (int)$row['quantity'];
    $total_amount += (float)$row['amount'] * (int)$row['quantity'];
}
$stmt->close();

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
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);

// Logo and Title
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 10, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(20);
$pdf->Cell(0, 10, 'STOCK TRANSFER', 0, 1, 'C');
$pdf->Ln(5);

// Meta information - Left and Right columns
$pdf->SetFont('Courier', 'B', 9);
$pdf->SetTextColor(0, 0, 0);

// Left column
$pdf->Cell(95, 6, 'ST NUMBER: ' . $transfer['st_number'], 0, 0, 'L');
// Right column
$pdf->Cell(95, 6, 'DATE: ' . date('F d, Y', strtotime($transfer['st_date'])), 0, 1, 'R');

// Use full branch names
$branch_from_display = !empty($transfer['branch_from_full_name']) ? $transfer['branch_from_full_name'] : $transfer['branch_from'];
$branch_to_display = !empty($transfer['branch_to_full_name']) ? $transfer['branch_to_full_name'] : $transfer['branch_to'];

$pdf->Cell(95, 6, 'BRANCH FROM: ' . $branch_from_display, 0, 0, 'L');
$pdf->Cell(95, 6, 'BRANCH TO: ' . $branch_to_display, 0, 1, 'R');

// Use full name for prepared_by
$prepared_by_display = !empty($transfer['prepared_by_full_name']) ? $transfer['prepared_by_full_name'] : $transfer['prepared_by'];
$pdf->Cell(95, 6, 'PREPARED BY: ' . $prepared_by_display, 0, 0, 'L');
$pdf->Cell(95, 6, 'STATUS: ' . strtoupper($transfer['status']), 0, 1, 'R');

// Show APPROVER with full name, but blank if Super-Admin
if (!empty($transfer['approver'])) {
    // If Super-Admin, show blank; otherwise show full name (or fallback to username)
    if ($transfer['approver_system_level'] == 'Super-Admin') {
        $approver_display = '';
    } else {
        $approver_display = !empty($transfer['approver_full_name']) ? $transfer['approver_full_name'] : $transfer['approver'];
    }
    $pdf->Cell(95, 6, 'APPROVER: ' . $approver_display, 0, 0, 'L');
    $pdf->Cell(95, 6, 'APPROVAL DATE: ' . (!empty($transfer['approval_date']) ? date('F d, Y H:i', strtotime($transfer['approval_date'])) : '-'), 0, 1, 'R');
}

if (!empty($transfer['remarks'])) {
    $pdf->Cell(95, 6, 'REMARKS: ' . $transfer['remarks'], 0, 1, 'L');
}

$pdf->Ln(5);

// Table Header
$pdf->SetFont('Courier', 'B', 7);
$pdf->SetFillColor(240, 240, 240);

$pdf->Cell(8, 8, 'NO', 1, 0, 'C', true);
$pdf->Cell(42, 8, 'ITEM CODE', 1, 0, 'C', true);
$pdf->Cell(52, 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'IMEI', 1, 0, 'C', true);
$pdf->Cell(13, 8, 'QTY', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'AMOUNT', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Courier', '', 8);

if (count($items) === 0) {
    $pdf->Cell(180, 8, 'NO ITEMS', 1, 1, 'C');
} else {
    $item_no = 1;
    foreach ($items as $item) {
        // Handle IMEI - split by various separators
        $imei = '';
        if (!empty($item['imei'])) {
            $imei = $item['imei'];
        }
        
        // Normalize separators to comma
        $imei_normalized = str_replace(["\r\n", "\n", "\r", "<br>", "<br/>", "<br />", "&"], ",", $imei);
        $imei_array = array_filter(array_map('trim', explode(',', $imei_normalized)));
        
        // Calculate row height based on number of IMEIs
        $row_height = max(6, count($imei_array) * 4);
        
        $x = 15; // Left margin
        $y = $pdf->GetY();
        
        // Draw all cell borders
        $pdf->Rect($x, $y, 8, $row_height);
        $pdf->Rect($x + 8, $y, 42, $row_height);
        $pdf->Rect($x + 50, $y, 52, $row_height);
        $pdf->Rect($x + 102, $y, 40, $row_height);
        $pdf->Rect($x + 142, $y, 13, $row_height);
        $pdf->Rect($x + 155, $y, 25, $row_height);
        
        // Calculate vertical center for single-line cells
        $v_center = $y + ($row_height / 2) - 2;
        
        // Draw NO (centered)
        $pdf->SetXY($x, $v_center);
        $pdf->Cell(8, 4, $item_no, 0, 0, 'C');
        
        // Draw ITEM CODE (left aligned) - display full text without cutting
        $item_code = $item['item_code'];
        $item_code_width = $pdf->GetStringWidth($item_code);
        
        // Adjust font size if text is too wide for the cell (42mm width)
        $pdf->SetFont('Courier', '', 8);
        if ($item_code_width > 40) {
            // Calculate required font size to fit
            $scale_factor = 40 / $item_code_width;
            $new_font_size = 8 * $scale_factor;
            $pdf->SetFont('Courier', '', max(5, $new_font_size)); // Minimum 5pt font
        }
        
        $pdf->SetXY($x + 8, $v_center);
        $pdf->Cell(42, 4, $item_code, 0, 0, 'L');
        
        // Reset font for other cells
        $pdf->SetFont('Courier', '', 8);
        
        // Draw ITEM DESCRIPTION (left aligned) - display without exceeding border
        $item_description = $item['item_description'];
        $desc_width = $pdf->GetStringWidth($item_description);
        
        // Adjust font size if text is too wide for the cell (52mm width)
        $pdf->SetFont('Courier', '', 8);
        if ($desc_width > 50) {
            // Calculate required font size to fit
            $scale_factor = 50 / $desc_width;
            $new_font_size = 8 * $scale_factor;
            $pdf->SetFont('Courier', '', max(5, $new_font_size)); // Minimum 5pt font
        }
        
        $pdf->SetXY($x + 50, $v_center);
        $pdf->Cell(52, 4, $item_description, 0, 0, 'L');
        
        // Reset font back to 8pt
        $pdf->SetFont('Courier', '', 8);
        
        // Draw IMEI with line breaks (centered in cell)
        if (count($imei_array) > 0) {
            $imei_start_y = $y + (($row_height - (count($imei_array) * 4)) / 2);
            $imei_y = $imei_start_y;
            foreach ($imei_array as $serial) {
                $pdf->SetXY($x + 102, $imei_y);
                // Check if serial length is exactly 15 characters
                $serial_length = strlen($serial);
                if ($serial_length != 15) {
                    // Set text color to red if not exactly 15 characters
                    $pdf->SetTextColor(255, 0, 0);
                } else {
                    // Set text color to black if exactly 15 characters
                    $pdf->SetTextColor(0, 0, 0);
                }
                $pdf->Cell(40, 4, $serial, 0, 0, 'C');
                $imei_y += 4;
            }
            // Reset text color to black for subsequent cells
            $pdf->SetTextColor(0, 0, 0);
        } else {
            // No IMEI
            $pdf->SetXY($x + 102, $v_center);
            $pdf->Cell(40, 4, '-', 0, 0, 'C');
        }
        
        // Draw QTY (centered)
        $pdf->SetXY($x + 142, $v_center);
        $pdf->Cell(13, 4, $item['quantity'], 0, 0, 'C');
        
        // Draw AMOUNT (right aligned)
        $pdf->SetXY($x + 155, $v_center);
        $pdf->Cell(25, 4, number_format($item['amount'], 2), 0, 0, 'R');
        
        // Move to next row
        $pdf->SetXY($x, $y + $row_height);
        
        $item_no++;
    }
}

$pdf->Ln(5);

// Summary
$pdf->SetFont('Courier', 'B', 10);
$pdf->SetTextColor(211, 47, 47);

$pdf->Cell(50, 6, 'TOTAL QUANTITY:', 0, 0, 'L');
$pdf->Cell(30, 6, $total_quantity, 0, 1, 'L');

$pdf->Cell(50, 6, 'TOTAL AMOUNT:', 0, 0, 'L');
$pdf->Cell(30, 6, number_format($total_amount, 2), 0, 1, 'L');

// Output PDF
$pdf->Output('I', 'ST_' . $transfer['st_number'] . '.pdf');
?>
