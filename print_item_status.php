<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

// Get log ID parameter
$log_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($log_id <= 0) {
    die('Invalid log ID');
}

// Fetch single record data
$report_query = "SELECT 
                    l.id as log_id,
                    l.entry_date as date,
                    l.branch_name as branch,
                    l.stock_type as to_status,
                    l.remarks,
                    l.created_by as changed_by,
                    l.approver,
                    CONCAT(a.first_name, ' ', a.last_name) as approver_full_name,
                    i.item_code,
                    i.item_description,
                    i.imei,
                    h.previous_status as from_status
                 FROM sales_entry_status_log l
                 LEFT JOIN sales_entry_status_items i ON l.id = i.log_id
                 LEFT JOIN accounts a ON l.approver COLLATE utf8mb4_unicode_ci = a.username COLLATE utf8mb4_unicode_ci
                 LEFT JOIN stock_status_history h ON l.id = h.approval_log_id 
                    AND (
                        (i.imei IS NOT NULL AND i.imei != '' AND h.imei COLLATE utf8mb4_unicode_ci = i.imei COLLATE utf8mb4_unicode_ci) 
                        OR 
                        ((i.imei IS NULL OR i.imei = '') AND h.item_code COLLATE utf8mb4_unicode_ci = i.item_code COLLATE utf8mb4_unicode_ci AND h.branch COLLATE utf8mb4_unicode_ci = l.branch_name COLLATE utf8mb4_unicode_ci AND (h.imei IS NULL OR h.imei = ''))
                    )
                 WHERE l.id = ? AND l.status = 'Approved'
                 ORDER BY i.id ASC";

$stmt = $conn->prepare($report_query);
$stmt->bind_param('i', $log_id);
$stmt->execute();
$result = $stmt->get_result();

$status_records = [];
$log_date = '';
$log_branch = '';

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if (empty($log_date)) {
            $log_date = $row['date'];
            $log_branch = $row['branch'];
        }
        $status_records[] = [
            'log_id' => $row['log_id'],
            'date' => $row['date'],
            'branch' => $row['branch'],
            'item_code' => $row['item_code'],
            'item_description' => $row['item_description'],
            'imei' => $row['imei'],
            'from_status' => $row['from_status'] ?? 'N/A',
            'to_status' => $row['to_status'],
            'changed_by' => $row['changed_by'],
            'approver_by' => $row['approver_full_name'] ?? ($row['approver'] ?? 'N/A'),
            'remarks' => $row['remarks']
        ];
    }
}

$stmt->close();

if (empty($status_records)) {
    die('No record found');
}

// Create PDF
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

$pdf = new PDF('L', 'mm', 'A4'); // Landscape
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);

// Logo and Title
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 15, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(25);
$pdf->Cell(0, 10, 'ITEM STATUS CHANGE REPORT', 0, 1, 'C');
$pdf->Ln(5);

// Report information
$pdf->SetFont('Courier', 'B', 10);
$pdf->Cell(135, 6, 'REPORT DATE: ' . date('F d, Y', strtotime($log_date)), 0, 1, 'L');
$pdf->Cell(135, 6, 'BRANCH: ' . $log_branch, 0, 1, 'L');
$pdf->Cell(135, 6, 'LOG ID: ' . $log_id, 0, 1, 'L');

$pdf->Ln(5);

// Calculate column widths
$available_width = 267; // A4 Landscape width minus margins

$col_widths = [
    'date' => 20,
    'branch' => 26,
    'item_code' => 55,
    'imei' => 36,
    'from_status' => 24,
    'to_status' => 24,
    'changed_by' => 28,
    'approver' => 28,
    'remarks' => 36
];

// Table Header
$pdf->SetFont('Courier', 'B', 6);
$pdf->SetFillColor(240, 240, 240);

$pdf->Cell($col_widths['date'], 8, 'DATE', 1, 0, 'C', true);
$pdf->Cell($col_widths['branch'], 8, 'BRANCH', 1, 0, 'C', true);
$pdf->Cell($col_widths['item_code'], 8, 'ITEM CODE', 1, 0, 'C', true);
$pdf->Cell($col_widths['imei'], 8, 'IMEI', 1, 0, 'C', true);
$pdf->Cell($col_widths['from_status'], 8, 'FROM STATUS', 1, 0, 'C', true);
$pdf->Cell($col_widths['to_status'], 8, 'TO STATUS', 1, 0, 'C', true);
$pdf->Cell($col_widths['changed_by'], 8, 'CHANGED BY', 1, 0, 'C', true);
$pdf->Cell($col_widths['approver'], 8, 'APPROVER BY', 1, 0, 'C', true);
$pdf->Cell($col_widths['remarks'], 8, 'REMARKS', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Courier', '', 5.5);

foreach ($status_records as $row) {
    $date = $row['date']; // Already in m/d/Y format from database
    $branch = $row['branch'];
    $item_code = $row['item_code'] ?: 'N/A';
    $imei = $row['imei'] ?: '-';
    $from_status = $row['from_status'];
    $to_status = $row['to_status'];
    $changed_by = $row['changed_by'];
    $approver = $row['approver_by'];
    $remarks = $row['remarks'] ?: '-';
    
    // Handle text wrapping
    $chars_per_mm = 0.65;
    
    $item_chars = floor($col_widths['item_code'] * $chars_per_mm);
    $remarks_chars = floor($col_widths['remarks'] * $chars_per_mm);
    
    $item_lines = explode("\n", wordwrap($item_code, $item_chars, "\n", true));
    $remarks_lines = explode("\n", wordwrap($remarks, $remarks_chars, "\n", true));
    
    $row_height = max(6, count($item_lines) * 3, count($remarks_lines) * 3);
    
    $x = 15;
    $y = $pdf->GetY();
    
    // Check if we need a new page
    if ($y + $row_height > 185) {
        $pdf->AddPage();
        
        // Redraw header
        $pdf->SetFont('Courier', 'B', 6);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($col_widths['date'], 8, 'DATE', 1, 0, 'C', true);
        $pdf->Cell($col_widths['branch'], 8, 'BRANCH', 1, 0, 'C', true);
        $pdf->Cell($col_widths['item_code'], 8, 'ITEM CODE', 1, 0, 'C', true);
        $pdf->Cell($col_widths['imei'], 8, 'IMEI', 1, 0, 'C', true);
        $pdf->Cell($col_widths['from_status'], 8, 'FROM STATUS', 1, 0, 'C', true);
        $pdf->Cell($col_widths['to_status'], 8, 'TO STATUS', 1, 0, 'C', true);
        $pdf->Cell($col_widths['changed_by'], 8, 'CHANGED BY', 1, 0, 'C', true);
        $pdf->Cell($col_widths['approver'], 8, 'APPROVER BY', 1, 0, 'C', true);
        $pdf->Cell($col_widths['remarks'], 8, 'REMARKS', 1, 1, 'C', true);
        
        $pdf->SetFont('Courier', '', 5.5);
        $y = $pdf->GetY();
    }
    
    // Draw borders
    $current_x = $x;
    $pdf->Rect($current_x, $y, $col_widths['date'], $row_height);
    $current_x += $col_widths['date'];
    
    $pdf->Rect($current_x, $y, $col_widths['branch'], $row_height);
    $current_x += $col_widths['branch'];
    
    $pdf->Rect($current_x, $y, $col_widths['item_code'], $row_height);
    $current_x += $col_widths['item_code'];
    
    $pdf->Rect($current_x, $y, $col_widths['imei'], $row_height);
    $current_x += $col_widths['imei'];
    
    $pdf->Rect($current_x, $y, $col_widths['from_status'], $row_height);
    $current_x += $col_widths['from_status'];
    
    $pdf->Rect($current_x, $y, $col_widths['to_status'], $row_height);
    $current_x += $col_widths['to_status'];
    
    $pdf->Rect($current_x, $y, $col_widths['changed_by'], $row_height);
    $current_x += $col_widths['changed_by'];
    
    $pdf->Rect($current_x, $y, $col_widths['approver'], $row_height);
    $current_x += $col_widths['approver'];
    
    $pdf->Rect($current_x, $y, $col_widths['remarks'], $row_height);
    
    $v_center = $y + ($row_height / 2) - 1.5;
    $current_x = $x;
    
    // DATE
    $pdf->SetXY($current_x, $v_center);
    $pdf->Cell($col_widths['date'], 3, $date, 0, 0, 'C');
    $current_x += $col_widths['date'];
    
    // BRANCH
    $pdf->SetXY($current_x, $v_center);
    $pdf->Cell($col_widths['branch'], 3, $branch, 0, 0, 'C');
    $current_x += $col_widths['branch'];
    
    // ITEM CODE (Multiline)
    $start_y = $y + (($row_height - (count($item_lines) * 3)) / 2);
    $curr_y = $start_y;
    foreach ($item_lines as $line) {
        $pdf->SetXY($current_x, $curr_y);
        $pdf->Cell($col_widths['item_code'], 3, $line, 0, 0, 'C');
        $curr_y += 3;
    }
    $current_x += $col_widths['item_code'];
    
    // IMEI
    $pdf->SetXY($current_x, $v_center);
    $pdf->Cell($col_widths['imei'], 3, $imei, 0, 0, 'C');
    $current_x += $col_widths['imei'];
    
    // FROM STATUS
    $pdf->SetXY($current_x, $v_center);
    $pdf->Cell($col_widths['from_status'], 3, $from_status, 0, 0, 'C');
    $current_x += $col_widths['from_status'];
    
    // TO STATUS
    $pdf->SetXY($current_x, $v_center);
    $pdf->Cell($col_widths['to_status'], 3, $to_status, 0, 0, 'C');
    $current_x += $col_widths['to_status'];
    
    // CHANGED BY
    $pdf->SetXY($current_x, $v_center);
    $pdf->Cell($col_widths['changed_by'], 3, $changed_by, 0, 0, 'C');
    $current_x += $col_widths['changed_by'];
    
    // APPROVER BY
    $pdf->SetXY($current_x, $v_center);
    $pdf->Cell($col_widths['approver'], 3, $approver, 0, 0, 'C');
    $current_x += $col_widths['approver'];
    
    // REMARKS (Multiline)
    $start_y = $y + (($row_height - (count($remarks_lines) * 3)) / 2);
    $curr_y = $start_y;
    foreach ($remarks_lines as $line) {
        $pdf->SetXY($current_x, $curr_y);
        $pdf->Cell($col_widths['remarks'], 3, $line, 0, 0, 'L');
        $curr_y += 3;
    }
    
    $pdf->SetY($y + $row_height);
}

$pdf->Ln(5);
$pdf->SetFont('Courier', 'B', 9);
$pdf->SetTextColor(255, 0, 0);
$pdf->Cell(267, 6, 'TOTAL ITEMS: ' . count($status_records), 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);

// Output PDF
$filename = 'Item_Status_Report_' . $log_id . '_' . date('Y-m-d') . '.pdf';
$pdf->Output('I', $filename);
?>
