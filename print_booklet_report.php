<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

$branch = isset($_GET['branch']) ? trim($_GET['branch']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : 'ALL';
$completion = isset($_GET['completion']) ? trim($_GET['completion']) : 'ALL';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

if ($branch === '') {
    die("Missing required parameter: branch");
}

// Build query based on filters
$where_conditions = [];
$params = [];
$param_types = '';

// Branch filter
if ($branch !== 'ALL') {
    $where_conditions[] = "bn.branch_code = ?";
    $params[] = $branch;
    $param_types .= 's';
}

// Status filter
if ($status !== 'ALL') {
    $where_conditions[] = "bn.status = ?";
    $params[] = $status;
    $param_types .= 's';
}

// Completion filter
if ($completion === 'Completed') {
    $where_conditions[] = "bn.complete_date IS NOT NULL";
} elseif ($completion === 'NotCompleted') {
    $where_conditions[] = "bn.complete_date IS NULL";
}

// Date filter (on created_at)
if (!empty($date_from) && !empty($date_to)) {
    $where_conditions[] = "DATE(bn.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $param_types .= 'ss';
} elseif (!empty($date_from)) {
    $where_conditions[] = "DATE(bn.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
} elseif (!empty($date_to)) {
    $where_conditions[] = "DATE(bn.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

$where_clause = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$sql = "
    SELECT 
        bn.*,
        b.branch_name
    FROM booklet_numbers bn
    LEFT JOIN branches b ON bn.branch_code = b.branch_code
    $where_clause
    ORDER BY b.branch_name ASC, bn.id ASC
";

$stmt = $conn->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

// Get branch name for display
$branch_display = 'ALL BRANCHES';
if ($branch !== 'ALL') {
    $stmt_branch = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = ? LIMIT 1");
    $stmt_branch->bind_param("s", $branch);
    $stmt_branch->execute();
    $res_branch = $stmt_branch->get_result();
    if ($res_branch && $res_branch->num_rows > 0) {
        $branch_row = $res_branch->fetch_assoc();
        $branch_display = strtoupper($branch_row['branch_name']);
    }
    $stmt_branch->close();
}

class BookletReportPDF extends FPDF
{
    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Courier', 'B', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, 'PAGE ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new BookletReportPDF('L', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Logo
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 10, 20, 20);
}

// Title
$pdf->SetFont
('Courier', 'B', 16);
$pdf->SetY(18);
$pdf->Cell(0, 8, 'BOOKLET REPORT', 0, 1, 'C');
$pdf->Ln(2);

// Report Info
$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(0, 6, 'BRANCH: ' . $branch_display, 0, 1, 'L');
if (!empty($date_from) && !empty($date_to)) {
    $pdf->Cell(0, 6, 'DATE RANGE: ' . date('F d, Y', strtotime($date_from)) . ' to ' . date('F d, Y', strtotime($date_to)), 0, 1, 'L');
} elseif (!empty($date_from)) {
    $pdf->Cell(0, 6, 'DATE FROM: ' . date('F d, Y', strtotime($date_from)), 0, 1, 'L');
} elseif (!empty($date_to)) {
    $pdf->Cell(0, 6, 'DATE TO: ' . date('F d, Y', strtotime($date_to)), 0, 1, 'L');
}
$pdf->Cell(0, 6, 'STATUS FILTER: ' . strtoupper($status), 0, 1, 'L');
$pdf->Cell(0, 6, 'COMPLETION FILTER: ' . strtoupper($completion), 0, 1, 'L');
$pdf->Cell(0, 6, 'GENERATED: ' . date('F d, Y h:i A'), 0, 1, 'R');
$pdf->Ln(2);

// Table Headers
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Courier', 'B', 7);

// Landscape A4: ~277mm usable width (with 10mm margins)
$widths = [30, 18, 20, 20, 20, 25, 15, 25, 25, 25, 27];
$headers = ['BRANCH', 'BOOKLET', 'BEGINNING', 'ENDING', 'CURRENT', 'CREATED BY', 'STATUS', 'DATE USED', 'USED BY', 'COMPLETE', 'COMPLETED'];

for ($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
}
$pdf->Ln();

// Table Data
$pdf->SetFont('Courier', '', 6.5);
if (count($rows) === 0) {
    $pdf->Cell(array_sum($widths), 8, 'NO DATA', 1, 1, 'C');
} else {
    foreach ($rows as $row) {
        $branch_name = strtoupper(substr($row['branch_name'] ?? 'N/A', 0, 18));
        $booklet_no = strtoupper(substr($row['booklet_no'] ?? '-', 0, 12));
        $beginning = strtoupper(substr($row['beginning_number'] ?? '-', 0, 12));
        $ending = strtoupper(substr($row['ending_number'] ?? '-', 0, 12));
        $current = strtoupper(substr($row['current_number'] ?? '-', 0, 12));
        $created_by = strtoupper(substr($row['created_by'] ?? '-', 0, 15));
        $status_val = strtoupper($row['status'] ?? '-');
        
        // Format date used with date and time on separate lines
        $date_used = '-';
        $date_used_lines = 1;
        if (!empty($row['last_used_date'])) {
            $date_used = date('m/d/Y', strtotime($row['last_used_date'])) . "\n" . date('h:i A', strtotime($row['last_used_date']));
            $date_used_lines = 2;
        }
        
        $used_by = strtoupper(substr($row['last_used_by'] ?? '-', 0, 15));
        
        // Format complete date with date and time on separate lines
        $complete_date = '-';
        $complete_date_lines = 1;
        $is_completed = 'NO';
        if (!empty($row['complete_date'])) {
            $complete_date = date('m/d/Y', strtotime($row['complete_date'])) . "\n" . date('h:i A', strtotime($row['complete_date']));
            $complete_date_lines = 2;
            $is_completed = 'YES';
            $pdf->SetTextColor(255, 0, 0); // Red for completed booklets
        }
        
        // Save current position
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        
        $pdf->Cell($widths[0], 14, $branch_name, 1, 0, 'L');
        $pdf->Cell($widths[1], 14, $booklet_no, 1, 0, 'C');
        $pdf->Cell($widths[2], 14, $beginning, 1, 0, 'C');
        $pdf->Cell($widths[3], 14, $ending, 1, 0, 'C');
        $pdf->Cell($widths[4], 14, $current, 1, 0, 'C');
        $pdf->Cell($widths[5], 14, $created_by, 1, 0, 'L');
        
        // Status cell
        $pdf->SetTextColor(0, 0, 0);
        if ($status_val === 'ACTIVE') {
            $pdf->SetTextColor(0, 128, 0); // Green for active
        }
        $pdf->Cell($widths[6], 14, $status_val, 1, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
        
        // Date used cell - use Cell for single dash, MultiCell for dates
        $date_used_x = $pdf->GetX();
        if ($date_used_lines == 1) {
            $pdf->Cell($widths[7], 14, $date_used, 1, 0, 'C');
        } else {
            // Draw border first
            $pdf->Rect($date_used_x, $y, $widths[7], 14);
            // Position text with padding to center it vertically
            $pdf->SetXY($date_used_x, $y + 2.5);
            $pdf->MultiCell($widths[7], 4.5, $date_used, 0, 'C');
        }
        
        // Used by cell
        if ($date_used_lines == 1) {
            $pdf->Cell($widths[8], 14, $used_by, 1, 0, 'L');
        } else {
            $pdf->SetXY($date_used_x + $widths[7], $y);
            $pdf->Cell($widths[8], 14, $used_by, 1, 0, 'L');
        }
        
        // Complete date cell - use Cell for single dash, MultiCell for dates
        if (!empty($row['complete_date'])) {
            $pdf->SetTextColor(255, 0, 0);
        }
        $complete_x = $pdf->GetX();
        if ($complete_date_lines == 1) {
            $pdf->Cell($widths[9], 14, $complete_date, 1, 0, 'C');
        } else {
            // Draw border first
            $pdf->Rect($complete_x, $y, $widths[9], 14);
            // Position text with padding to center it vertically
            $pdf->SetXY($complete_x, $y + 2.5);
            $pdf->MultiCell($widths[9], 4.5, $complete_date, 0, 'C');
        }
        
        // Completed YES/NO cell
        if ($complete_date_lines == 1) {
            $pdf->Cell($widths[10], 14, $is_completed, 1, 1, 'C');
        } else {
            $pdf->SetXY($complete_x + $widths[9], $y);
            $pdf->Cell($widths[10], 14, $is_completed, 1, 1, 'C');
        }
        $pdf->SetTextColor(0, 0, 0);
    }
}

// Summary
$pdf->Ln(3);
$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(0, 6, 'TOTAL BOOKLETS: ' . count($rows), 0, 1, 'L');

// Count by status
$active_count = 0;
$inactive_count = 0;
$completed_count = 0;
foreach ($rows as $row) {
    if ($row['status'] === 'Active') $active_count++;
    if ($row['status'] === 'Inactive') $inactive_count++;
    if (!empty($row['complete_date'])) $completed_count++;
}

$pdf->Cell(0, 6, 'ACTIVE: ' . $active_count . ' | INACTIVE: ' . $inactive_count . ' | COMPLETED: ' . $completed_count, 0, 1, 'L');

$filename = 'Booklet_Report_' . ($branch !== 'ALL' ? $branch : 'All') . '_' . date('YmdHis') . '.pdf';
$pdf->Output('I', $filename);
?>
