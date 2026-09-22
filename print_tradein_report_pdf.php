<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

// Get parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$area = isset($_GET['area']) ? trim($_GET['area']) : '';
$branch = isset($_GET['branch']) ? trim($_GET['branch']) : '';

if (empty($date_from) || empty($date_to)) {
    die("Missing required parameters: date_from and date_to");
}

// Get user access information
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

// Build the query
$query = "
    SELECT 
        se.id,
        se.invoice_no,
        DATE_FORMAT(se.created_at, '%Y-%m-%d') as created_at,
        b.branch_name,
        CONCAT(se.first_name, ' ', se.last_name) as customer_name,
        se.tradein_brand,
        se.tradein_item_code,
        se.tradein_imei,
        se.tradein_value,
        se.total_amount,
        se.assisted_by,
        (SELECT GROUP_CONCAT(DISTINCT sei.imei SEPARATOR ', ')
         FROM sales_entry_items sei
         WHERE sei.sales_entry_id = se.id
         AND sei.imei IS NOT NULL
         AND sei.imei != ''
        ) as sold_imeis
    FROM sales_entry se
    LEFT JOIN branches b ON se.branch_code = b.branch_code
    WHERE se.page_type = 'salestrade-in'
    AND DATE(se.created_at) BETWEEN ? AND ?
    AND (
        se.tradein_value > 0 
        OR se.tradein_imei IS NOT NULL AND se.tradein_imei != ''
        OR se.tradein_item_code IS NOT NULL AND se.tradein_item_code != ''
        OR se.tradein_brand IS NOT NULL AND se.tradein_brand != ''
    )
";

$params = [$date_from, $date_to];
$param_types = 'ss';

// Apply area filter
if (!empty($area)) {
    $query .= " AND b.area = ?";
    $params[] = $area;
    $param_types .= 's';
}

// Apply branch filter
if (!empty($branch)) {
    $query .= " AND b.branch_name = ?";
    $params[] = $branch;
    $param_types .= 's';
}

// Apply user access restrictions
if ($system_level !== 'Super-Admin' && strtoupper($user_branch) !== 'SUPERADMIN') {
    if (!empty($user_branch)) {
        $user_branches = array_map('trim', explode(',', $user_branch));
        $user_branches = array_filter($user_branches, function ($b) {
            return !empty($b);
        });

        if (!empty($user_branches)) {
            $placeholders = implode(',', array_fill(0, count($user_branches), '?'));
            $query .= " AND b.branch_name IN ($placeholders)";
            foreach ($user_branches as $ub) {
                $params[] = $ub;
                $param_types .= 's';
            }
        }
    }
}

$query .= " ORDER BY se.created_at DESC, se.invoice_no ASC";

// Prepare and execute query
$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// Bind parameters dynamically
if (!empty($params)) {
    $bind_params = array_merge([$param_types], $params);
    $tmp = [];
    foreach ($bind_params as $key => $value) {
        $tmp[$key] = &$bind_params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $tmp);
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];
$totalTradeInValue = 0;
$totalSalesAmount = 0;

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
    $totalTradeInValue += floatval($row['tradein_value']);
    $totalSalesAmount += floatval($row['total_amount']);
}

$stmt->close();
$conn->close();

// Helper function to fit text in cell
function fitTextInCell($pdf, $text, $cellWidth, $baseFontSize = 7, $minFontSize = 5)
{
    $text = utf8_decode(mb_strtoupper(trim((string) $text), 'UTF-8'));
    $fontSize = $baseFontSize;
    $availableWidth = $cellWidth - 2;

    $pdf->SetFont('Courier', '', $fontSize);
    while ($fontSize > $minFontSize && $pdf->GetStringWidth($text) > $availableWidth) {
        $fontSize -= 0.2;
        $pdf->SetFont('Courier', '', $fontSize);
    }

    return [$text, $fontSize];
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

$pdf = new PDF('L', 'mm', 'A4'); // Landscape orientation
$pdf->AddPage();
$pdf->SetMargins(10, 15, 10);
$pdf->SetAutoPageBreak(true, 20);

// Logo and Title
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.PNG';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 15, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(25);
$pdf->Cell(0, 10, 'TRADE-IN SALES REPORT', 0, 1, 'C');
$pdf->Ln(5);

// Meta information
$pdf->SetFont('Courier', 'B', 9);
$pdf->SetTextColor(0, 0, 0);

$dateDisplayFrom = date('F d, Y', strtotime($date_from));
$dateDisplayTo = date('F d, Y', strtotime($date_to));
$dateDisplay = ($dateDisplayFrom === $dateDisplayTo) ? $dateDisplayFrom : ($dateDisplayFrom . ' - ' . $dateDisplayTo);

$pdf->Cell(0, 6, 'DATE RANGE: ' . $dateDisplay, 0, 1, 'L');
$pdf->Cell(0, 6, 'AREA: ' . ($area ?: 'All Areas'), 0, 1, 'L');
$pdf->Cell(0, 6, 'BRANCH: ' . ($branch ?: 'All Branches'), 0, 1, 'L');

$pdf->Ln(5);

// Table Header
$pdf->SetFont('Courier', 'B', 7);
$pdf->SetFillColor(240, 240, 240);

// Column widths for landscape
$wNo = 10;
$wInvoice = 24;
$wDate = 20;
$wBranch = 30;
$wCustomer = 30;
$wSerial = 28;
$wBrand = 22;
$wItem = 26;
$wIMEI = 26;
$wTradeValue = 20;
$wTotalAmt = 20;
$wAssisted = 24;

$pdf->Cell($wNo, 8, 'NO', 1, 0, 'C', true);
$pdf->Cell($wInvoice, 8, 'INVOICE NO', 1, 0, 'C', true);
$pdf->Cell($wDate, 8, 'DATE', 1, 0, 'C', true);
$pdf->Cell($wBranch, 8, 'BRANCH', 1, 0, 'C', true);
$pdf->Cell($wCustomer, 8, 'CUSTOMER', 1, 0, 'C', true);
$pdf->Cell($wSerial, 8, 'SERIAL NO', 1, 0, 'C', true);
$pdf->Cell($wBrand, 8, 'TI BRAND', 1, 0, 'C', true);
$pdf->Cell($wItem, 8, 'TI ITEM', 1, 0, 'C', true);
$pdf->Cell($wIMEI, 8, 'TI IMEI', 1, 0, 'C', true);
$pdf->Cell($wTradeValue, 8, 'TI VALUE', 1, 0, 'C', true);
$pdf->Cell($wTotalAmt, 8, 'TOTAL AMT', 1, 0, 'C', true);
$pdf->Cell($wAssisted, 8, 'ASSISTED BY', 1, 1, 'C', true);

$pdf->SetFont('Courier', '', 6);

if (count($data) === 0) {
    $pdf->Cell(280, 8, 'NO TRADE-IN SALES FOUND', 1, 1, 'C');
} else {
    $counter = 1;
    foreach ($data as $row) {
        $pdf->SetTextColor(0, 0, 0);

        $pdf->Cell($wNo, 6, $counter++, 1, 0, 'C');

        list($invoiceText, $invoiceFontSize) = fitTextInCell($pdf, $row['invoice_no'], $wInvoice, 6, 4);
        $pdf->SetFont('Courier', '', $invoiceFontSize);
        $pdf->Cell($wInvoice, 6, $invoiceText, 1, 0, 'C');
        $pdf->SetFont('Courier', '', 6);

        $pdf->Cell($wDate, 6, $row['created_at'], 1, 0, 'C');

        list($branchText, $branchFontSize) = fitTextInCell($pdf, $row['branch_name'], $wBranch, 6, 4);
        $pdf->SetFont('Courier', '', $branchFontSize);
        $pdf->Cell($wBranch, 6, $branchText, 1, 0, 'L');
        $pdf->SetFont('Courier', '', 6);

        list($customerText, $customerFontSize) = fitTextInCell($pdf, $row['customer_name'], $wCustomer, 6, 4);
        $pdf->SetFont('Courier', '', $customerFontSize);
        $pdf->Cell($wCustomer, 6, $customerText, 1, 0, 'L');
        $pdf->SetFont('Courier', '', 6);

        list($serialText, $serialFontSize) = fitTextInCell($pdf, $row['sold_imeis'] ?: '-', $wSerial, 6, 4);
        $pdf->SetFont('Courier', '', $serialFontSize);
        $pdf->Cell($wSerial, 6, $serialText, 1, 0, 'L');
        $pdf->SetFont('Courier', '', 6);

        list($brandText, $brandFontSize) = fitTextInCell($pdf, $row['tradein_brand'], $wBrand, 6, 4);
        $pdf->SetFont('Courier', '', $brandFontSize);
        $pdf->Cell($wBrand, 6, $brandText, 1, 0, 'L');
        $pdf->SetFont('Courier', '', 6);

        list($itemText, $itemFontSize) = fitTextInCell($pdf, $row['tradein_item_code'], $wItem, 6, 4);
        $pdf->SetFont('Courier', '', $itemFontSize);
        $pdf->Cell($wItem, 6, $itemText, 1, 0, 'L');
        $pdf->SetFont('Courier', '', 6);

        list($imeiText, $imeiFontSize) = fitTextInCell($pdf, $row['tradein_imei'], $wIMEI, 6, 4);
        $pdf->SetFont('Courier', '', $imeiFontSize);
        $pdf->Cell($wIMEI, 6, $imeiText, 1, 0, 'C');
        $pdf->SetFont('Courier', '', 6);

        $tradeValue = number_format(floatval($row['tradein_value']), 2, '.', ',');
        list($tradeValueText, $tradeValueFontSize) = fitTextInCell($pdf, $tradeValue, $wTradeValue, 6, 4);
        $pdf->SetFont('Courier', '', $tradeValueFontSize);
        $pdf->Cell($wTradeValue, 6, $tradeValueText, 1, 0, 'R');
        $pdf->SetFont('Courier', '', 6);

        $totalAmount = number_format(floatval($row['total_amount']), 2, '.', ',');
        list($totalAmountText, $totalAmountFontSize) = fitTextInCell($pdf, $totalAmount, $wTotalAmt, 6, 4);
        $pdf->SetFont('Courier', '', $totalAmountFontSize);
        $pdf->Cell($wTotalAmt, 6, $totalAmountText, 1, 0, 'R');
        $pdf->SetFont('Courier', '', 6);

        list($assistedText, $assistedFontSize) = fitTextInCell($pdf, $row['assisted_by'], $wAssisted, 6, 4);
        $pdf->SetFont('Courier', '', $assistedFontSize);
        $pdf->Cell($wAssisted, 6, $assistedText, 1, 1, 'L');
        $pdf->SetFont('Courier', '', 6);
    }
}

// Summary section
$pdf->Ln(5);
$pdf->SetFont('Courier', 'B', 9);
$pdf->SetTextColor(211, 47, 47); // Red color

$pdf->Cell(0, 6, 'TOTAL TRANSACTIONS: ' . count($data), 0, 1, 'L');
$pdf->Cell(0, 6, 'TOTAL TRADE-IN VALUE: PHP ' . number_format($totalTradeInValue, 2, '.', ','), 0, 1, 'L');
$pdf->Cell(0, 6, 'TOTAL SALES AMOUNT: PHP ' . number_format($totalSalesAmount, 2, '.', ','), 0, 1, 'L');

$pdf->Output('I', 'Trade-In_Sales_Report_' . $date_from . '_to_' . $date_to . '.pdf');
?>
