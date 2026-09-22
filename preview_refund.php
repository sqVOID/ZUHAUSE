<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Refund ID.");
}

$refund_id = intval($_GET['id']);

// Fetch refund header
$stmt = $conn->prepare("SELECT * FROM refunds WHERE id = ?");
$stmt->bind_param("i", $refund_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Refund record not found.");
}

$header = $res->fetch_assoc();
$stmt->close();

// Fetch refund items
$items = [];
$stmt_items = $conn->prepare("
    SELECT * FROM refund_items 
    WHERE refund_id = ?
    ORDER BY id ASC
");
$stmt_items->bind_param("i", $refund_id);
$stmt_items->execute();
$res_items = $stmt_items->get_result();
while ($row = $res_items->fetch_assoc()) {
    $items[] = $row;
}
$stmt_items->close();

// Calculate totals
$total_quantity = 0;
$total_amount = 0;
foreach ($items as $item) {
    $qty = intval($item['quantity']);
    $price = floatval($item['price']);
    $total_quantity += $qty;
    $total_amount += ($price * $qty);
}

// Get branch name
$branch_name = $header['branch_code'];
$branch_query = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = ?");
$branch_query->bind_param("s", $header['branch_code']);
$branch_query->execute();
$branch_result = $branch_query->get_result();
if ($branch_result && $branch_result->num_rows > 0) {
    $branch_data = $branch_result->fetch_assoc();
    $branch_name = $branch_data['branch_name'];
}
$branch_query->close();

// Create PDF with custom footer
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
    $pdf->Image($logo_path, 10, 10, 20, 20);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(22);
$pdf->Cell(0, 10, 'REFUND REPORT', 0, 1, 'C');
$pdf->Ln(5);

// Meta information
$pdf->SetFont('Courier', 'B', 9);

$pdf->Cell(95, 6, 'INVOICE NO: ' . $header['invoice_no'], 0, 0, 'L');
$pdf->Cell(95, 6, 'BRANCH: ' . $branch_name, 0, 1, 'R');

$pdf->Cell(95, 6, 'DATE: ' . date('F d, Y', strtotime($header['refund_date'])), 0, 0, 'L');
$pdf->Cell(95, 6, 'CUSTOMER: ' . ($header['customer_name'] ?: 'N/A'), 0, 1, 'R');

$pdf->Cell(95, 6, 'APPROVED BY: ' . ($header['approved_by'] ?: 'Unknown'), 0, 0, 'L');
$pdf->Cell(95, 6, 'PROCESSED BY: ' . ($header['encoder'] ?: 'System'), 0, 1, 'R');

if (!empty($header['remarks'])) {
    $pdf->Cell(190, 6, 'REMARKS: ' . $header['remarks'], 0, 1, 'L');
} else {
    $pdf->Cell(190, 6, 'REMARKS: N/A', 0, 1, 'L');
}

$pdf->Ln(5);

// Items Table Header
$pdf->SetFont('Courier', 'B', 8);
$pdf->SetFillColor(240, 240, 240);

$pdf->Cell(15, 8, 'QTY', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'ITEM CODE', 1, 0, 'C', true);
$pdf->Cell(60, 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'IMEI', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'PRICE', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'AMOUNT', 1, 1, 'C', true);

// Items Table Body
$pdf->SetFont('Courier', '', 7);

if (count($items) === 0) {
    $pdf->Cell(190, 8, 'No Items', 1, 1, 'C');
} else {
    foreach ($items as $item) {
        $item_code = $item['item_code'] ?: '-';
        $description = $item['item_description'] ?: '-';
        
        $chars_per_mm = 0.55; 
        
        $code_chars = floor(30 * $chars_per_mm);
        $desc_chars = floor(60 * $chars_per_mm);
        
        $code_lines = explode("\n", wordwrap($item_code, $code_chars, "\n", true));
        $desc_lines = explode("\n", wordwrap($description, $desc_chars, "\n", true));
        
        $row_height = max(8, count($code_lines) * 4, count($desc_lines) * 4);
        
        $x = 15;
        $y = $pdf->GetY();
        
        // Check page break
        if ($y + $row_height > 270) {
            $pdf->AddPage();
            
            $pdf->SetFont('Courier', 'B', 8);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(15, 8, 'QTY', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'ITEM CODE', 1, 0, 'C', true);
            $pdf->Cell(60, 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
            $pdf->Cell(40, 8, 'IMEI', 1, 0, 'C', true);
            $pdf->Cell(20, 8, 'PRICE', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'AMOUNT', 1, 1, 'C', true);
            
            $pdf->SetFont('Courier', '', 7);
            $y = $pdf->GetY();
        }
        
        // Draw cell borders
        $pdf->Rect($x, $y, 15, $row_height);
        $pdf->Rect($x + 15, $y, 30, $row_height);
        $pdf->Rect($x + 45, $y, 60, $row_height);
        $pdf->Rect($x + 105, $y, 40, $row_height);
        $pdf->Rect($x + 145, $y, 20, $row_height);
        $pdf->Rect($x + 165, $y, 25, $row_height);
        
        $v_center = $y + ($row_height / 2) - 2;
        
        // QTY
        $pdf->SetXY($x, $v_center);
        $pdf->Cell(15, 4, $item['quantity'] ?: '0', 0, 0, 'C');
        
        // ITEM CODE (Multiline)
        $start_y = $y + (($row_height - (count($code_lines) * 4)) / 2);
        $curr_y = $start_y;
        foreach ($code_lines as $line) {
            $pdf->SetXY($x + 15, $curr_y);
            $pdf->Cell(30, 4, $line, 0, 0, 'C');
            $curr_y += 4;
        }
        
        // ITEM DESCRIPTION (Multiline)
        $start_y = $y + (($row_height - (count($desc_lines) * 4)) / 2);
        $curr_y = $start_y;
        foreach ($desc_lines as $line) {
            $pdf->SetXY($x + 45, $curr_y);
            $pdf->Cell(60, 4, $line, 0, 0, 'C');
            $curr_y += 4;
        }
        
        // IMEI
        $pdf->SetXY($x + 105, $v_center);
        $imei_value = $item['imei'] ?: '-';
        // Check if IMEI length is exactly 15 characters (skip check for '-')
        if ($imei_value !== '-') {
            $imei_length = strlen($imei_value);
            if ($imei_length != 15) {
                // Set text color to red if not exactly 15 characters
                $pdf->SetTextColor(255, 0, 0);
            }
        }
        $pdf->Cell(40, 4, $imei_value, 0, 0, 'C');
        // Reset text color to black
        $pdf->SetTextColor(0, 0, 0);
        
        // PRICE
        $pdf->SetXY($x + 145, $v_center);
        $item_price = floatval($item['price']);
        $pdf->Cell(20, 4, number_format($item_price, 2), 0, 0, 'C');
        
        // AMOUNT
        $pdf->SetXY($x + 165, $v_center);
        $item_total = $item_price * intval($item['quantity']);
        $pdf->Cell(25, 4, number_format($item_total, 2), 0, 0, 'C');
        
        $pdf->SetY($y + $row_height);
    }
}

$pdf->Ln(5);

// Summary section
$pdf->SetFont('Courier', 'B', 9);
$pdf->SetTextColor(255, 0, 0); // Red color
$pdf->Cell(190, 6, 'TOTAL QUANTITY: ' . $total_quantity, 0, 1, 'L');
$pdf->Cell(190, 6, 'GRAND TOTAL: ' . number_format($total_amount, 2), 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0); // Reset color to black

$pdf->Ln(5);

// Output PDF
$pdf->Output('I', 'Refund_' . $header['invoice_no'] . '.pdf');
?>
