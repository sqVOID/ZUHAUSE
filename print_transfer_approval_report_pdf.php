<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$sql = "SELECT st_number, st_date as date, branch_from, branch_to, prepared_by, approver, status, remarks 
        FROM stock_transfers WHERE 1=1";

$params = [];
$types = '';

if (!empty($date_from)) {
    $sql .= " AND st_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $sql .= " AND st_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

// Handle status filter - 'all' means show all statuses
if (!empty($status) && $status !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= 's';
}

$sql .= " ORDER BY st_date DESC, st_number DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$transfers = [];
while ($row = $result->fetch_assoc()) {
    $transfers[] = $row;
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
        $this->Cell(0, 10, 'PAGE ' . $this->PageNo() . ' OF {nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape orientation
$pdf->AliasNbPages();
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
$pdf->Cell(0, 10, 'TRANSFER APPROVAL REPORT', 0, 1, 'C');
$pdf->Ln(3);

// Filter information
$pdf->SetFont('Courier', 'B', 9);
$pdf->SetTextColor(0, 0, 0);

$filter_text = 'Filters: ';
if (!empty($date_from)) {
    $filter_text .= 'From: ' . date('M d, Y', strtotime($date_from)) . ' ';
}
if (!empty($date_to)) {
    $filter_text .= 'To: ' . date('M d, Y', strtotime($date_to)) . ' ';
}
if (!empty($status)) {
    $filter_text .= 'Status: ' . ($status === 'all' ? 'All Status' : $status) . ' ';
}
if ($filter_text === 'Filters: ') {
    $filter_text .= 'All Records';
}

$pdf->Cell(0, 6, $filter_text, 0, 1, 'C');
$pdf->Ln(5);

// Table Header
$pdf->SetFont('Courier', 'B', 7);
$pdf->SetFillColor(240, 240, 240);

$pdf->Cell(25, 8, 'ST NO', 1, 0, 'C', true);
$pdf->Cell(22, 8, 'DATE', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'BRANCH FROM', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'BRANCH TO', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'PREPARED BY', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'APPROVER', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'STATUS', 1, 0, 'C', true);
$pdf->Cell(58, 8, 'REMARKS', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Courier', '', 6);

if (count($transfers) === 0) {
    $pdf->Cell(270, 8, 'NO RECORDS FOUND', 1, 1, 'C');
} else {
    foreach ($transfers as $transfer) {
        $pdf->Cell(25, 6, $transfer['st_number'], 1, 0, 'C');
        $pdf->Cell(22, 6, date('m/d/Y', strtotime($transfer['date'])), 1, 0, 'C');
        $pdf->Cell(35, 6, substr($transfer['branch_from'], 0, 20), 1, 0, 'C');
        $pdf->Cell(35, 6, substr($transfer['branch_to'], 0, 20), 1, 0, 'C');
        $pdf->Cell(35, 6, substr($transfer['prepared_by'], 0, 20), 1, 0, 'C');
        $pdf->Cell(35, 6, substr($transfer['approver'] ?: '-', 0, 20), 1, 0, 'C');
        $pdf->Cell(25, 6, $transfer['status'], 1, 0, 'C');
        $pdf->Cell(58, 6, substr($transfer['remarks'] ?: '-', 0, 35), 1, 1, 'L');
    }
}

$pdf->Ln(5);

// Summary
$pdf->SetFont('Courier', 'B', 10);
$pdf->SetTextColor(211, 47, 47);
$pdf->Cell(50, 6, 'TOTAL RECORDS: ' . count($transfers), 0, 1, 'L');

// Output PDF
$filename = 'Transfer_Approval_Report_' . date('Ymd_His') . '.pdf';
$pdf->Output('I', $filename);
?>
