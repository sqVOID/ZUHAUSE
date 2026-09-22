<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

// Get date parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : $date_from;
$selected_branch = isset($_GET['branch']) ? $_GET['branch'] : '';

// For backward compatibility with single date parameter
if (isset($_GET['date']) && !isset($_GET['date_from'])) {
    $date_from = $_GET['date'];
    $date_to = $_GET['date'];
}

// Get branch information
$branch_code = '000';
if (isset($_SESSION['user_branch'])) {
    $user_branch = $_SESSION['user_branch'];
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch'");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
}

// Determine if the user is an admin who can see all branches
$system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
$is_admin = ($system_level === 'Super-Admin' || $system_level === 'Sub-admin');

// Build query to fetch void sales data
$where_conditions = ["se.status = 'voided'"];

// Add date range filter
if ($date_from === $date_to) {
    $where_conditions[] = "DATE(se.voided_at) = '$date_from'";
} else {
    $where_conditions[] = "DATE(se.voided_at) >= '$date_from' AND DATE(se.voided_at) <= '$date_to'";
}

// Add branch filter if specified
if ($selected_branch) {
    $where_conditions[] = "se.branch_code = '$selected_branch'";
} else if (!$is_admin) {
    $where_conditions[] = "se.branch_code = '$branch_code'";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Fetch void sales data
$report_query = "SELECT se.id, se.voided_at, se.created_at, se.invoice_no, se.first_name, se.last_name, 
                        se.void_reason, se.voided_by, se.branch_code, b.branch_name 
                 FROM sales_entry se
                 LEFT JOIN branches b ON se.branch_code = b.branch_code
                 $where_clause
                 ORDER BY se.voided_at DESC, se.id DESC";

$result = $conn->query($report_query);
$void_sales = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $void_sales[] = $row;
    }
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
        $this->Cell(0, 10, 'PAGE ' . $this->PageNo() . ' OF {nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);

// Logo and Title on same line
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 15, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(25);
$pdf->Cell(0, 10, 'VOID SALES REPORT', 0, 1, 'C');
$pdf->Ln(5);

// Report information
$pdf->SetFont('Courier', 'B', 10);
if ($date_from === $date_to) {
    $pdf->Cell(95, 6, 'REPORT DATE: ' . date('F d, Y', strtotime($date_from)), 0, 1, 'L');
} else {
    $pdf->Cell(95, 6, 'DATE RANGE: ' . date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to)), 0, 1, 'L');
}

if (!$is_admin && isset($user_branch)) {
    $pdf->Cell(95, 6, 'BRANCH: ' . $user_branch . ' - ' . $branch_code, 0, 1, 'L');
} else if ($selected_branch) {
    // Get branch name for selected branch
    $branch_name_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '$selected_branch'");
    $selected_branch_name = 'Unknown Branch';
    if ($branch_name_query && $branch_name_query->num_rows > 0) {
        $branch_name_data = $branch_name_query->fetch_assoc();
        $selected_branch_name = $branch_name_data['branch_name'];
    }
    $pdf->Cell(95, 6, 'BRANCH: ' . $selected_branch_name . ' - ' . $selected_branch, 0, 1, 'L');
} else {
    $pdf->Cell(95, 6, 'BRANCH: ALL BRANCHES', 0, 1, 'L');
}

$pdf->Ln(5);

// Calculate dynamic column widths based on available space
$available_width = 180; // A4 width minus margins (210 - 30)
$total_columns = 7;

// Define minimum widths for each column
$min_widths = [
    'date_voided' => 18,
    'date_sold' => 18,
    'invoice' => 22,
    'customer' => 25,
    'branch' => 30,
    'reason' => 20,
    'voided_by' => 20  // Increased from 15 to 20
];

// Calculate total minimum width
$min_total = array_sum($min_widths);

// If we have extra space, distribute it proportionally
$extra_space = $available_width - $min_total;
$col_widths = $min_widths;

if ($extra_space > 0) {
    // Distribute extra space proportionally - giving more to VOIDED BY
    $col_widths['date_voided'] += ($extra_space * 0.08);
    $col_widths['date_sold'] += ($extra_space * 0.08);
    $col_widths['invoice'] += ($extra_space * 0.12);
    $col_widths['customer'] += ($extra_space * 0.18);
    $col_widths['branch'] += ($extra_space * 0.22);
    $col_widths['reason'] += ($extra_space * 0.12);
    $col_widths['voided_by'] += ($extra_space * 0.20);  // Increased from 0.05 to 0.20
}

// Table Header
$pdf->SetFont('Courier', 'B', 8);
$pdf->SetFillColor(240, 240, 240);

$pdf->Cell($col_widths['date_voided'], 8, 'DATE VOIDED', 1, 0, 'C', true);
$pdf->Cell($col_widths['date_sold'], 8, 'DATE SOLD', 1, 0, 'C', true);
$pdf->Cell($col_widths['invoice'], 8, 'INVOICE NO.', 1, 0, 'C', true);
$pdf->Cell($col_widths['customer'], 8, 'CUSTOMER NAME', 1, 0, 'C', true);
$pdf->Cell($col_widths['branch'], 8, 'BRANCH', 1, 0, 'C', true);
$pdf->Cell($col_widths['reason'], 8, 'VOID REASON', 1, 0, 'C', true);
$pdf->Cell($col_widths['voided_by'], 8, 'VOIDED BY', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Courier', '', 7);

if (count($void_sales) === 0) {
    if ($date_from === $date_to) {
        $pdf->Cell($available_width, 8, 'No void sales records found for ' . date('F d, Y', strtotime($date_from)), 1, 1, 'C');
    } else {
        $pdf->Cell($available_width, 8, 'No void sales records found for ' . date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to)), 1, 1, 'C');
    }
} else {
    foreach ($void_sales as $row) {
        $v_date = !empty($row['voided_at']) ? date('m/d/Y', strtotime($row['voided_at'])) : '';
        $v_date_sold = !empty($row['created_at']) && $row['created_at'] != '1970-01-01 00:00:00' ? date('m/d/Y', strtotime($row['created_at'])) : '';
        $v_inv = $row['invoice_no'];
        
        $customer_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $v_customer = $customer_name ?: 'N/A';

        $b_name = $row['branch_name'] ? $row['branch_name'] : 'Unknown';
        $b_code = $row['branch_code'] ? $row['branch_code'] : 'UNK';
        $v_branch = $b_name . ' - ' . $b_code;

        $v_reason = $row['void_reason'] ?? 'No reason';
        $v_by = $row['voided_by'] ?? 'Unknown';

        // Handle text wrapping for long content based on column widths
        $chars_per_mm = 0.55; // Approximate characters per mm for Courier font size 7
        
        $reason_chars = floor($col_widths['reason'] * $chars_per_mm);
        $customer_chars = floor($col_widths['customer'] * $chars_per_mm);
        $branch_chars = floor($col_widths['branch'] * $chars_per_mm);
        $voided_by_chars = floor($col_widths['voided_by'] * $chars_per_mm);
        
        $reason_lines = explode("\n", wordwrap($v_reason, $reason_chars, "\n", true));
        $customer_lines = explode("\n", wordwrap($v_customer, $customer_chars, "\n", true));
        $branch_lines = explode("\n", wordwrap($v_branch, $branch_chars, "\n", true));
        $voided_by_lines = explode("\n", wordwrap($v_by, $voided_by_chars, "\n", true));
        
        // Calculate row height based on max lines needed
        $row_height = max(8, count($reason_lines) * 4, count($customer_lines) * 4, count($branch_lines) * 4, count($voided_by_lines) * 4);
        
        $x = 15; // Left margin
        $y = $pdf->GetY();
        
        // Check if we need a new page
        if ($y + $row_height > 270) {
            $pdf->AddPage();
            
            // Repeat header on new page
            $pdf->SetFont('Courier', 'B', 8);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell($col_widths['date_voided'], 8, 'DATE VOIDED', 1, 0, 'C', true);
            $pdf->Cell($col_widths['date_sold'], 8, 'DATE SOLD', 1, 0, 'C', true);
            $pdf->Cell($col_widths['invoice'], 8, 'INVOICE NO.', 1, 0, 'C', true);
            $pdf->Cell($col_widths['customer'], 8, 'CUSTOMER NAME', 1, 0, 'C', true);
            $pdf->Cell($col_widths['branch'], 8, 'BRANCH', 1, 0, 'C', true);
            $pdf->Cell($col_widths['reason'], 8, 'VOID REASON', 1, 0, 'C', true);
            $pdf->Cell($col_widths['voided_by'], 8, 'VOIDED BY', 1, 1, 'C', true);
            
            $pdf->SetFont('Courier', '', 7);
            $y = $pdf->GetY();
        }
        
        // Draw all cell borders using dynamic widths
        $current_x = $x;
        $pdf->Rect($current_x, $y, $col_widths['date_voided'], $row_height);
        $current_x += $col_widths['date_voided'];
        
        $pdf->Rect($current_x, $y, $col_widths['date_sold'], $row_height);
        $current_x += $col_widths['date_sold'];
        
        $pdf->Rect($current_x, $y, $col_widths['invoice'], $row_height);
        $current_x += $col_widths['invoice'];
        
        $pdf->Rect($current_x, $y, $col_widths['customer'], $row_height);
        $current_x += $col_widths['customer'];
        
        $pdf->Rect($current_x, $y, $col_widths['branch'], $row_height);
        $current_x += $col_widths['branch'];
        
        $pdf->Rect($current_x, $y, $col_widths['reason'], $row_height);
        $current_x += $col_widths['reason'];
        
        $pdf->Rect($current_x, $y, $col_widths['voided_by'], $row_height);
        
        // Calculate vertical center for single-line cells
        $v_center = $y + ($row_height / 2) - 2;
        
        // Draw cells using dynamic positioning
        $current_x = $x;
        
        // Draw DATE VOIDED (centered)
        $pdf->SetXY($current_x, $v_center);
        $pdf->Cell($col_widths['date_voided'], 4, $v_date, 0, 0, 'C');
        $current_x += $col_widths['date_voided'];
        
        // Draw DATE SOLD (centered)
        $pdf->SetXY($current_x, $v_center);
        $pdf->Cell($col_widths['date_sold'], 4, $v_date_sold, 0, 0, 'C');
        $current_x += $col_widths['date_sold'];
        
        // Draw INVOICE NO (centered)
        $pdf->SetXY($current_x, $v_center);
        $pdf->Cell($col_widths['invoice'], 4, $v_inv, 0, 0, 'C');
        $current_x += $col_widths['invoice'];
        
        // Draw CUSTOMER NAME with line breaks (centered in cell)
        $customer_start_y = $y + (($row_height - (count($customer_lines) * 4)) / 2);
        $customer_y = $customer_start_y;
        foreach ($customer_lines as $line) {
            $pdf->SetXY($current_x, $customer_y);
            $pdf->Cell($col_widths['customer'], 4, $line, 0, 0, 'C');
            $customer_y += 4;
        }
        $current_x += $col_widths['customer'];
        
        // Draw BRANCH with line breaks (centered in cell)
        $branch_start_y = $y + (($row_height - (count($branch_lines) * 4)) / 2);
        $branch_y = $branch_start_y;
        foreach ($branch_lines as $line) {
            $pdf->SetXY($current_x, $branch_y);
            $pdf->Cell($col_widths['branch'], 4, $line, 0, 0, 'C');
            $branch_y += 4;
        }
        $current_x += $col_widths['branch'];
        
        // Draw VOID REASON with line breaks (centered in cell)
        $reason_start_y = $y + (($row_height - (count($reason_lines) * 4)) / 2);
        $reason_y = $reason_start_y;
        foreach ($reason_lines as $line) {
            $pdf->SetXY($current_x, $reason_y);
            $pdf->Cell($col_widths['reason'], 4, $line, 0, 0, 'C');
            $reason_y += 4;
        }
        $current_x += $col_widths['reason'];
        
        // Draw VOIDED BY with line breaks (centered in cell)
        $voided_by_start_y = $y + (($row_height - (count($voided_by_lines) * 4)) / 2);
        $voided_by_y = $voided_by_start_y;
        foreach ($voided_by_lines as $line) {
            $pdf->SetXY($current_x, $voided_by_y);
            $pdf->Cell($col_widths['voided_by'], 4, $line, 0, 0, 'C');
            $voided_by_y += 4;
        }
        
        // Move to next row
        $pdf->SetY($y + $row_height);
    }
}

// Output PDF
if ($date_from === $date_to) {
    $filename = 'Void_Sales_Report_' . date('Y-m-d', strtotime($date_from)) . '.pdf';
} else {
    $filename = 'Void_Sales_Report_' . date('Y-m-d', strtotime($date_from)) . '_to_' . date('Y-m-d', strtotime($date_to)) . '.pdf';
}
$pdf->Output('I', $filename);
?>