<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

if (!isset($_GET['upgrade_no']) || empty($_GET['upgrade_no'])) {
    die("Invalid Upgrade Number.");
}

$upgrade_no = $_GET['upgrade_no'];

// Fetch upgrade header
$stmt = $conn->prepare("SELECT u.*, b.branch_code FROM upgrades u LEFT JOIN branches b ON u.branch = b.branch_name WHERE u.upgrade_no = ?");
$stmt->bind_param("s", $upgrade_no);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Upgrade record not found.");
}

$header = $res->fetch_assoc();
$upgrade_id = $header['id'];
$stmt->close();

// Fetch old items
$old_items = [];
$stmt_old = $conn->prepare("SELECT item_description, imei, price FROM upgrade_old_items WHERE upgrade_id = ?");
$stmt_old->bind_param("i", $upgrade_id);
$stmt_old->execute();
$res_old = $stmt_old->get_result();
while ($row = $res_old->fetch_assoc()) {
    $old_items[] = $row;
}
$stmt_old->close();

// Fetch new items
$new_items = [];
$stmt_new = $conn->prepare("SELECT item_code, item_description, imei, quantity, price FROM upgrade_new_items WHERE upgrade_id = ?");
$stmt_new->bind_param("i", $upgrade_id);
$stmt_new->execute();
$res_new = $stmt_new->get_result();
while ($row = $res_new->fetch_assoc()) {
    $new_items[] = $row;
}
$stmt_new->close();

$branch_text = ($header['branch'] ? $header['branch'] : 'Unknown') . ' - ' . ($header['branch_code'] ? $header['branch_code'] : 'UNK');
$created_date_txt = (!empty($header['created_at'])) ? date('F d, Y H:i:s', strtotime($header['created_at'])) : '';

// Calculate totals
$old_unit_total = 0;
$old_qty = 0;
foreach ($old_items as $item) {
    $old_unit_total += floatval($item['price']);
    $old_qty++;
}

$new_unit_total = 0;
$new_qty = 0;
foreach ($new_items as $item) {
    $qty = intval($item['quantity']);
    $new_unit_total += floatval($item['price']) * $qty;
    $new_qty += $qty;
}

$total_paid = floatval($header['total_amount']);

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
    $pdf->Image($logo_path, 10, 10, 20, 20);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(22);
$pdf->Cell(0, 10, 'UPGRADE UNIT', 0, 1, 'C');
$pdf->Ln(5);

// Meta information
$pdf->SetFont('Courier', 'B', 9);

$pdf->Cell(95, 6, 'UPGRADE NO: ' . $header['upgrade_no'], 0, 0, 'L');
$pdf->Cell(95, 6, 'BRANCH: ' . $branch_text, 0, 1, 'R');

$pdf->Cell(95, 6, 'INVOICE NO: ' . $header['original_invoice_no'], 0, 0, 'L');
$pdf->Cell(95, 6, 'CREATED BY: ' . $header['created_by'], 0, 1, 'R');

$pdf->Cell(95, 6, 'REASON: ' . $header['reason'], 0, 0, 'L');
$pdf->Cell(95, 6, 'DATE: ' . $created_date_txt, 0, 1, 'R');

$pdf->Cell(190, 6, 'REMARKS: ' . ($header['remarks'] ? $header['remarks'] : 'N/A'), 0, 1, 'L');

$pdf->Ln(5);

// OLD UNIT Section Header
$pdf->SetFont('Courier', 'B', 10);
$pdf->Cell(190, 6, 'OLD UNIT', 0, 1, 'L');
$pdf->Ln(2);

// Old Unit Table Header
$pdf->SetFont('Courier', 'B', 8);
$pdf->SetFillColor(240, 240, 240);

$pdf->Cell(15, 8, 'QTY', 1, 0, 'C', true);
$pdf->Cell(80, 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
$pdf->Cell(60, 8, 'IMEI', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'AMOUNT', 1, 1, 'C', true);

// Old Unit Table Body
$pdf->SetFont('Courier', '', 7);

if (count($old_items) === 0) {
    $pdf->Cell(190, 8, 'No Old Items', 1, 1, 'C');
} else {
    foreach ($old_items as $item) {
        $x = 15;
        $y = $pdf->GetY();
        $row_height = 8;
        
        // Draw cell borders
        $pdf->Rect($x, $y, 15, $row_height);
        $pdf->Rect($x + 15, $y, 80, $row_height);
        $pdf->Rect($x + 95, $y, 60, $row_height);
        $pdf->Rect($x + 155, $y, 35, $row_height);
        
        $v_center = $y + ($row_height / 2) - 2;
        
        // QTY
        $pdf->SetXY($x, $v_center);
        $pdf->Cell(15, 4, '1', 0, 0, 'C');
        
        // ITEM DESCRIPTION
        $pdf->SetXY($x + 15, $v_center);
        $pdf->Cell(80, 4, substr($item['item_description'], 0, 45), 0, 0, 'C');
        
        // IMEI
        $pdf->SetXY($x + 95, $v_center);
        // Check if IMEI length is exactly 15 characters
        $imei_length = strlen($item['imei']);
        if ($imei_length != 15) {
            // Set text color to red if not exactly 15 characters
            $pdf->SetTextColor(255, 0, 0);
        } else {
            // Set text color to black if exactly 15 characters
            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->Cell(60, 4, $item['imei'], 0, 0, 'C');
        // Reset text color to black
        $pdf->SetTextColor(0, 0, 0);
        
        // AMOUNT
        $pdf->SetXY($x + 155, $v_center);
        $pdf->Cell(35, 4, number_format($item['price'], 2), 0, 0, 'C');
        
        $pdf->SetXY($x, $y + $row_height);
    }
}

$pdf->Ln(5);

// NEW UNIT Section Header
$pdf->SetFont('Courier', 'B', 10);
$pdf->Cell(190, 6, 'NEW UNIT', 0, 1, 'L');
$pdf->Ln(2);

// New Unit Table Header
$pdf->SetFont('Courier', 'B', 8);
$pdf->SetFillColor(240, 240, 240);

$pdf->Cell(15, 8, 'QTY', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'MODEL CODE', 1, 0, 'C', true);
$pdf->Cell(50, 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
$pdf->Cell(60, 8, 'IMEI', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'AMOUNT', 1, 1, 'C', true);

// New Unit Table Body
$pdf->SetFont('Courier', '', 7);

if (count($new_items) === 0) {
    $pdf->Cell(190, 8, 'No New Items', 1, 1, 'C');
} else {
    foreach ($new_items as $item) {
        $x = 15;
        $y = $pdf->GetY();
        $row_height = 8;
        
        // Draw cell borders
        $pdf->Rect($x, $y, 15, $row_height);
        $pdf->Rect($x + 15, $y, 30, $row_height);
        $pdf->Rect($x + 45, $y, 50, $row_height);
        $pdf->Rect($x + 95, $y, 60, $row_height);
        $pdf->Rect($x + 155, $y, 35, $row_height);
        
        $v_center = $y + ($row_height / 2) - 2;
        
        // QTY
        $pdf->SetXY($x, $v_center);
        $pdf->Cell(15, 4, $item['quantity'], 0, 0, 'C');
        
        // MODEL CODE
        $pdf->SetXY($x + 15, $v_center);
        $pdf->Cell(30, 4, substr($item['item_code'], 0, 20), 0, 0, 'C');
        
        // ITEM DESCRIPTION
        $pdf->SetXY($x + 45, $v_center);
        $pdf->Cell(50, 4, substr($item['item_description'], 0, 30), 0, 0, 'C');
        
        // IMEI
        $pdf->SetXY($x + 95, $v_center);
        $imei_value = $item['imei'] ? $item['imei'] : 'N/A';
        // Check if IMEI length is exactly 15 characters (skip check for 'N/A')
        if ($imei_value !== 'N/A') {
            $imei_length = strlen($imei_value);
            if ($imei_length != 15) {
                // Set text color to red if not exactly 15 characters
                $pdf->SetTextColor(255, 0, 0);
            } else {
                // Set text color to black if exactly 15 characters
                $pdf->SetTextColor(0, 0, 0);
            }
        }
        $pdf->Cell(60, 4, $imei_value, 0, 0, 'C');
        // Reset text color to black
        $pdf->SetTextColor(0, 0, 0);
        
        // AMOUNT
        $pdf->SetXY($x + 155, $v_center);
        $pdf->Cell(35, 4, number_format($item['price'] * $item['quantity'], 2), 0, 0, 'C');
        
        $pdf->SetXY($x, $y + $row_height);
    }
}

$pdf->Ln(5);

// Summary
$pdf->SetFont('Courier', 'B', 10);
$pdf->SetTextColor(211, 47, 47);

$pdf->Cell(50, 6, 'OLD UNIT TOTAL:', 0, 0, 'L');
$pdf->Cell(20, 6, number_format($old_unit_total, 2), 0, 1, 'L');

$pdf->Cell(50, 6, 'NEW UNIT TOTAL:', 0, 0, 'L');
$pdf->Cell(20, 6, number_format($new_unit_total, 2), 0, 1, 'L');

$pdf->Cell(50, 6, 'TOTAL NEED TO PAY:', 0, 0, 'L');
$pdf->Cell(20, 6, number_format($total_paid, 2), 0, 1, 'L');

// Output PDF
$pdf->Output('I', 'UPGRADE_' . $header['upgrade_no'] . '.pdf');
?>
