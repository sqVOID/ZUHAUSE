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

// Get user branch info
$branch_code = '000';
$user_branch = 'Unknown';
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

// Build query with filters
$where_conditions = [];

// Add date range filter
if ($date_from === $date_to) {
    $where_conditions[] = "DATE(u.created_at) = '$date_from'";
} else {
    $where_conditions[] = "DATE(u.created_at) >= '$date_from' AND DATE(u.created_at) <= '$date_to'";
}

// Add branch filter if specified
if ($selected_branch) {
    // Get branch name from branch code
    $branch_name_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '$selected_branch'");
    if ($branch_name_query && $branch_name_query->num_rows > 0) {
        $branch_name_data = $branch_name_query->fetch_assoc();
        $selected_branch_name = $branch_name_data['branch_name'];
        $where_conditions[] = "u.branch = '$selected_branch_name'";
    }
} else if (!$is_admin) {
    $where_conditions[] = "u.branch = '$user_branch'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Fetch all upgrade data
$report_query = "SELECT u.id, u.created_at, u.upgrade_no, u.original_invoice_no, u.reason, 
                        u.remarks, u.created_by, u.branch, b.branch_code, u.total_amount
                 FROM upgrades u
                 LEFT JOIN branches b ON u.branch = b.branch_name
                 $where_clause
                 ORDER BY u.created_at DESC, u.id DESC";

$result = $conn->query($report_query);

if (!$result || $result->num_rows === 0) {
    // Create PDF even for no results to show the message
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

    $pdf = new PDF('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetMargins(10, 10, 10);
    
    // Logo and Title
    $logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
    if (file_exists($logo_path)) {
        $pdf->Image($logo_path, 10, 10, 25, 25);
    }

    $pdf->SetFont('Courier', 'B', 16);
    $pdf->SetY(20);
    $pdf->Cell(0, 10, 'UPGRADE UNIT REPORT', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Build message based on filters
    $pdf->SetFont('Courier', '', 12);
    $message = 'No upgrade records found';
    
    // Add date range to message
    if ($date_from === $date_to) {
        $message .= ' for ' . date('F d, Y', strtotime($date_from));
    } else {
        $message .= ' from ' . date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to));
    }
    
    // Add branch to message if filtered
    if ($selected_branch) {
        $branch_name_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '$selected_branch'");
        if ($branch_name_query && $branch_name_query->num_rows > 0) {
            $branch_name_data = $branch_name_query->fetch_assoc();
            $message .= ' for branch ' . $branch_name_data['branch_name'] . ' - ' . $selected_branch;
        }
    }
    
    $pdf->Cell(0, 10, $message, 0, 1, 'C');
    $pdf->Output('I', 'UPGRADE_REPORT_' . date('Y-m-d') . '.pdf');
    exit;
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

$pdf = new PDF('L', 'mm', 'A4'); // Landscape orientation for wide table
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);

// Logo and Title
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 10, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(20);
$pdf->Cell(0, 10, 'UPGRADE UNIT REPORT', 0, 1, 'C');
$pdf->Ln(3);

// Report information - Branch only
$pdf->SetFont('Courier', 'B', 10);

// Branch info
if (!$is_admin && isset($user_branch)) {
    $branch_text = $user_branch . ' - ' . $branch_code;
} else if ($selected_branch) {
    // Get branch name for selected branch
    $branch_name_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '$selected_branch'");
    $selected_branch_name = 'Unknown Branch';
    if ($branch_name_query && $branch_name_query->num_rows > 0) {
        $branch_name_data = $branch_name_query->fetch_assoc();
        $selected_branch_name = $branch_name_data['branch_name'];
    }
    $branch_text = $selected_branch_name . ' - ' . $selected_branch;
} else {
    $branch_text = 'ALL BRANCHES';
}
$pdf->Cell(0, 6, 'BRANCH: ' . $branch_text, 0, 1, 'L');
$pdf->Ln(5);

// Table Header
$pdf->SetFont('Courier', 'B', 7);
$pdf->SetFillColor(240, 240, 240);

$pdf->Cell(20, 8, 'DATE', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'UPGRADE NO', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'INVOICE NO', 1, 0, 'C', true);
$pdf->Cell(50, 8, 'OLD UNIT', 1, 0, 'C', true);
$pdf->Cell(50, 8, 'NEW UNIT', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'QTY', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'TOTAL PAID', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'REASON', 1, 0, 'C', true);
$pdf->Cell(32, 8, 'CREATED BY', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Courier', '', 6);
$total_records = 0;
$grand_total = 0;

while ($row = $result->fetch_assoc()) {
    $u_date = !empty($row['created_at']) ? date('m/d/Y', strtotime($row['created_at'])) : '';
    $u_upgrade_no = $row['upgrade_no'];
    $u_invoice = $row['original_invoice_no'];
    $u_reason = substr($row['reason'] ?? 'N/A', 0, 15);
    $u_by = substr($row['created_by'] ?? 'Unknown', 0, 15);
    $u_id = $row['id'];
    $total_amount = floatval($row['total_amount']);

    // Get old items
    $old_items_query = $conn->prepare("SELECT item_description FROM upgrade_old_items WHERE upgrade_id = ? LIMIT 2");
    $old_items_query->bind_param("i", $u_id);
    $old_items_query->execute();
    $old_items_result = $old_items_query->get_result();
    
    $old_items_desc = [];
    $old_items_count = 0;
    while ($old_item = $old_items_result->fetch_assoc()) {
        $old_items_desc[] = substr($old_item['item_description'], 0, 20);
        $old_items_count++;
    }
    $old_items_query->close();
    $u_old_unit = !empty($old_items_desc) ? implode(', ', $old_items_desc) : 'N/A';
    if ($old_items_count > 1) $u_old_unit .= '...';

    // Get new items
    $new_items_query = $conn->prepare("SELECT item_description, quantity FROM upgrade_new_items WHERE upgrade_id = ? LIMIT 2");
    $new_items_query->bind_param("i", $u_id);
    $new_items_query->execute();
    $new_items_result = $new_items_query->get_result();
    
    $new_items_desc = [];
    $new_items_count = 0;
    $total_qty = 0;
    while ($new_item = $new_items_result->fetch_assoc()) {
        $new_items_desc[] = substr($new_item['item_description'], 0, 20);
        $new_items_count++;
        $total_qty += intval($new_item['quantity']);
    }
    $new_items_query->close();
    $u_new_unit = !empty($new_items_desc) ? implode(', ', $new_items_desc) : 'N/A';
    if ($new_items_count > 1) $u_new_unit .= '...';

    // Use max count for qty (typically 1 for upgrades)
    $qty = max($old_items_count, $new_items_count);

    // Check if we need a new page
    if ($pdf->GetY() > 180) {
        $pdf->AddPage();
        
        // Repeat header on new page
        $pdf->SetFont('Courier', 'B', 7);
        $pdf->SetFillColor(240, 240, 240);
        
        $pdf->Cell(20, 8, 'DATE', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'UPGRADE NO', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'INVOICE NO', 1, 0, 'C', true);
        $pdf->Cell(50, 8, 'OLD UNIT', 1, 0, 'C', true);
        $pdf->Cell(50, 8, 'NEW UNIT', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'QTY', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'TOTAL PAID', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'REASON', 1, 0, 'C', true);
        $pdf->Cell(32, 8, 'CREATED BY', 1, 1, 'C', true);
        
        $pdf->SetFont('Courier', '', 6);
    }

    $pdf->Cell(20, 6, $u_date, 1, 0, 'C');
    $pdf->Cell(25, 6, $u_upgrade_no, 1, 0, 'C');
    $pdf->Cell(25, 6, $u_invoice, 1, 0, 'C');
    $pdf->Cell(50, 6, $u_old_unit, 1, 0, 'L');
    $pdf->Cell(50, 6, $u_new_unit, 1, 0, 'L');
    $pdf->Cell(20, 6, $qty, 1, 0, 'C');
    $pdf->Cell(25, 6, number_format($total_amount, 2), 1, 0, 'R');
    $pdf->Cell(30, 6, $u_reason, 1, 0, 'L');
    $pdf->Cell(32, 6, $u_by, 1, 1, 'L');

    $total_records++;
    $grand_total += $total_amount;
}

$pdf->Ln(3);

// Summary
$pdf->SetFont('Courier', 'B', 10);
$pdf->SetTextColor(211, 47, 47);

$pdf->Cell(50, 6, 'TOTAL RECORDS:', 0, 0, 'L');
$pdf->Cell(20, 6, $total_records, 0, 1, 'L');

$pdf->Cell(50, 6, 'GRAND TOTAL:', 0, 0, 'L');
$pdf->Cell(20, 6, number_format($grand_total, 2), 0, 1, 'L');

// Output PDF
$pdf->Output('I', 'UPGRADE_REPORT_' . date('Y-m-d') . '.pdf');
?>