<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

// Get parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$branchFilter = isset($_GET['branch']) ? $_GET['branch'] : '';

// Resolve Branch Filter internally if not global
if (empty($branchFilter)) {
    if (isset($_SESSION['user_branch']) && $_SESSION['system_level'] !== 'Super-Admin' && $_SESSION['system_level'] !== 'Sub-admin') {
        $user_branch = $_SESSION['user_branch'];
        $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch'");
        if ($branch_query && $branch_query->num_rows > 0) {
            $branchFilter = $branch_query->fetch_assoc()['branch_code'];
        }
    }
}

$pdf_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pdf_id > 0) {
    $where_clause = "r.id = ?";
    $params = [$pdf_id];
    $types = "i";
} else {
    $where_clause = "DATE(r.refund_date) >= ? AND DATE(r.refund_date) <= ?";
    $params = [$date_from, $date_to];
    $types = "ss";

    if (!empty($branchFilter)) {
        $where_clause .= " AND r.branch_code = ?";
        $params[] = $branchFilter;
        $types .= "s";
    }
}

// Fetch refunds
$stmt = $conn->prepare("
    SELECT 
        r.id,
        r.invoice_no,
        r.branch_code,
        r.total_qty,
        r.total_amount,
        r.approved_by,
        r.encoder,
        r.customer_name,
        r.refund_date,
        b.branch_name
    FROM refunds r
    LEFT JOIN branches b ON r.branch_code = b.branch_code
    WHERE $where_clause
    ORDER BY r.refund_date ASC, r.invoice_no ASC
");

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$refunds = [];
$lastInvoice = '';

while ($row = $result->fetch_assoc()) {
    $refunds[] = $row;
    $lastInvoice = $row['invoice_no'];

    // Fetch items for this refund
    $stmt_items = $conn->prepare("SELECT item_code, item_description, quantity, price, imei FROM refund_items WHERE refund_id = ?");
    $stmt_items->bind_param("i", $row['id']);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();

    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    $stmt_items->close();

    $refunds[count($refunds) - 1]['items'] = $items;
}
$stmt->close();

function abbreviateName($fullName) {
    if (!$fullName || trim($fullName) === '') return '';
    $words = preg_split('/\s+/', trim($fullName));
    $abbreviated = '';
    foreach ($words as $word) {
        if (strlen($word) > 0) {
            $abbreviated .= strtoupper(substr($word, 0, 1)) . '.';
        }
    }
    return strlen($abbreviated) > 0 ? $abbreviated : '';
}

// Create PDF
class PDF extends FPDF {
    function Footer() {
        $this->SetY(-15);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Courier', 'B', 10);
        $this->Cell(0, 10, 'PAGE ' . $this->PageNo(), 0, 0, 'C');
    }
}

// Using landscape since we have more description text for refunds or similar
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);

// Logo
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 15, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(25);
$pdf->Cell(0, 10, 'REFUND REPORT', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Courier', 'B', 9);
$pdf->SetTextColor(0, 0, 0);

$displayBranch = !empty($branchFilter) ? $branchFilter : 'ALL BRANCHES';
$displayDate = ($date_from === $date_to) ? date('F d, Y', strtotime($date_from)) : date('F d, Y', strtotime($date_from)) . ' to ' . date('F d, Y', strtotime($date_to));

$pdf->Cell(0, 6, 'BRANCH: ' . $displayBranch, 0, 1, 'L');
$pdf->Cell(0, 6, 'DATE OF REFUNDS: ' . $displayDate, 0, 1, 'L');

$y_pos = $pdf->GetY();
$pdf->SetXY(15, $y_pos);
$pdf->Cell(90, 6, 'LAST INVOICE NO: ' . $lastInvoice, 0, 0, 'L');
$pdf->Cell(90, 6, 'TIME & DATE: ' . date('h:i:s A - F d, Y'), 0, 1, 'R');

$pdf->Ln(5);

// Table Header
$pdf->SetFont('Courier', 'B', 7);
$pdf->SetFillColor(240, 240, 240);

// Total Width: 180
$pdf->Cell(25, 8, 'INVOICE NO', 1, 0, 'C', true);
$pdf->Cell(15, 8, 'BRANCH', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'DATE', 1, 0, 'C', true);
$pdf->Cell(10, 8, 'QTY', 1, 0, 'C', true);
$pdf->Cell(45, 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'SRP', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'TOTAL AMT', 1, 0, 'C', true);
$pdf->Cell(15, 8, 'ENCODER', 1, 1, 'C', true);

$pdf->SetFont('Courier', '', 6);

if (count($refunds) === 0) {
    $pdf->Cell(180, 8, 'NO REFUNDS FOUND', 1, 1, 'C');
} else {
    foreach ($refunds as $sale) {
        $encoderAbbr = abbreviateName($sale['encoder']);
        $rDate = date('m/d/Y', strtotime($sale['refund_date']));

        if (isset($sale['items']) && count($sale['items']) > 0) {
            foreach ($sale['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['price'];
                $desc = substr($item['item_description'], 0, 30); // truncate fit

                $pdf->Cell(25, 6, $sale['invoice_no'], 1, 0, 'C');
                $pdf->Cell(15, 6, $sale['branch_code'], 1, 0, 'C');
                $pdf->Cell(20, 6, $rDate, 1, 0, 'C');
                $pdf->Cell(10, 6, $item['quantity'], 1, 0, 'C');
                $pdf->Cell(45, 6, $desc, 1, 0, 'L');
                $pdf->Cell(25, 6, number_format($item['price'], 2), 1, 0, 'R');
                $pdf->Cell(25, 6, number_format($itemTotal, 2), 1, 0, 'R');
                $pdf->Cell(15, 6, $encoderAbbr, 1, 1, 'C');
            }
        } else {
            $pdf->Cell(25, 6, $sale['invoice_no'], 1, 0, 'C');
            $pdf->Cell(15, 6, $sale['branch_code'], 1, 0, 'C');
            $pdf->Cell(20, 6, $rDate, 1, 0, 'C');
            $pdf->Cell(10, 6, $sale['total_qty'], 1, 0, 'C');
            $pdf->Cell(45, 6, 'No Items', 1, 0, 'L');
            $pdf->Cell(25, 6, '0.00', 1, 0, 'R');
            $pdf->Cell(25, 6, number_format($sale['total_amount'], 2), 1, 0, 'R');
            $pdf->Cell(15, 6, $encoderAbbr, 1, 1, 'C');
        }
    }
}

$pdf->SetTextColor(0, 0, 0);

// Breakdown
$groupedByEncoder = [];
$totalUnits = 0;
$grandTotalAmount = 0;

foreach ($refunds as $sale) {
    $encoderName = $sale['encoder'] ? $sale['encoder'] : 'Unknown';
    $saleAmount = floatval($sale['total_amount']);
    $saleQty = intval($sale['total_qty']);

    if (!isset($groupedByEncoder[$encoderName])) {
        $groupedByEncoder[$encoderName] = [
            'quantity' => 0,
            'totalAmount' => 0
        ];
    }
    $groupedByEncoder[$encoderName]['quantity'] += $saleQty;
    $groupedByEncoder[$encoderName]['totalAmount'] += $saleAmount;

    $totalUnits += $saleQty;
    $grandTotalAmount += $saleAmount;
}

$pdf->Ln(5);
$startY = $pdf->GetY();

$pdf->SetFont('Courier', 'B', 8);

foreach ($groupedByEncoder as $encoderName => $data) {
    $normalizedName = preg_replace('/\s+/', ' ', trim(strtoupper($encoderName)));
    $leftText = $normalizedName . ' ' . $data['quantity'] . ' MOTORCYCLE REFUNDED';
    $pdf->Cell(60, 5, $leftText, 0, 0, 'L');
    $pdf->Cell(30, 5, number_format($data['totalAmount'], 2), 0, 1, 'R');
}

$pdf->Ln(5);

$pdf->SetFont('Courier', 'B', 8);
$pdf->SetTextColor(211, 47, 47);
$pdf->Cell(60, 5, 'TOTAL UNIT QUANTITY REFUNDED:', 0, 0, 'L');
$pdf->Cell(30, 5, $totalUnits, 0, 1, 'R');

$pdf->Cell(60, 5, 'GRAND TOTAL REFUND AMOUNT:', 0, 0, 'L');
$pdf->Cell(30, 5, number_format($grandTotalAmount, 2), 0, 1, 'R');

$pdf->Ln(10);

$pdf->Output('I', 'Refund_Report_' . $date_from . '.pdf');
?>
