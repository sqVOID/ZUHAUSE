<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$branch = isset($_GET['branch']) ? trim($_GET['branch']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

if (empty($date_from) || empty($date_to) || empty($branch)) {
    die('Missing required parameters');
}

// Build query to get pre-order report data - same as web report
$query = "SELECT 
            p.id as preorder_id,
            p.invoice_no as preorder_no,
            p.claimed_invoice_no,
            p.claimed_at,
            p.created_at as preorder_created_at,
            CONCAT(p.first_name, ' ', p.last_name) as customer_name,
            p.contact_no as customer_phone,
            p.branch_code,
            ph.payment_date as date_created,
            p.status,
            ph.payment_type,
            ph.payment_method,
            ph.amount as payment_amount,
            ph.payment_sequence,
            ph.balance_before,
            ph.balance_after,
            ph.status_after_payment,
            pi.id as preorder_item_id,
            COALESCE(NULLIF(pi.item_description, ''), pi.family_code) as item_description,
            pi.imei,
            pi.quantity,
            pi.price as unit_price,
            (pi.quantity * pi.price) as total_amount,
            pi.amount_paid as item_amount_paid,
            pi.created_at as item_created_at
          FROM preorder_payment_history ph
          INNER JOIN preorders p ON ph.preorder_id = p.id
          LEFT JOIN preorder_items pi ON p.id = pi.preorder_id
          WHERE DATE(ph.payment_date) BETWEEN ? AND ?";

// Check if we need to filter by branch
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

if ($system_level === 'Super-Admin' || $system_level === 'Sub-admin' || strtoupper($user_branch) === 'SUPERADMIN') {
    // For Super-Admin, get branch_code from branch name
    $branch_query = $conn->prepare("SELECT branch_code FROM branches WHERE branch_name = ?");
    $branch_query->bind_param('s', $branch);
    $branch_query->execute();
    $branch_result = $branch_query->get_result();
    
    if ($branch_result && $branch_result->num_rows > 0) {
        $branch_data = $branch_result->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
        $query .= " AND p.branch_code = ?";
        $params = [$date_from, $date_to, $branch_code];
        $types = 'sss';
    } else {
        $params = [$date_from, $date_to];
        $types = 'ss';
    }
    $branch_query->close();
} else {
    // For regular users, get branch_code from branch name
    $branch_query = $conn->prepare("SELECT branch_code FROM branches WHERE branch_name = ?");
    $branch_query->bind_param('s', $branch);
    $branch_query->execute();
    $branch_result = $branch_query->get_result();
    
    if ($branch_result && $branch_result->num_rows > 0) {
        $branch_data = $branch_result->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
        $query .= " AND p.branch_code = ?";
        $params = [$date_from, $date_to, $branch_code];
        $types = 'sss';
    } else {
        $params = [$date_from, $date_to];
        $types = 'ss';
    }
    $branch_query->close();
}

// Add status filter if provided
if (!empty($status)) {
    $query .= " AND p.status = ?";
    $params[] = $status;
    $types .= 's';
}

$query .= " ORDER BY ph.payment_date DESC, p.id DESC, pi.id ASC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die('Failed to prepare statement: ' . $conn->error);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    $seq = intval($row['payment_sequence']);
    $unit_price = floatval($row['unit_price']);
    $total_amount = floatval($row['total_amount']);
    $txn_payment = floatval($row['payment_amount']);
    $item_created = $row['item_created_at'] ?? $row['preorder_created_at'];
    $preorder_created = $row['preorder_created_at'];
    $payment_date = $row['date_created'];
    
    // Skip items that were added after this payment transaction
    if ($seq === 1 && !empty($item_created) && !empty($payment_date)) {
        if (strtotime($item_created) > strtotime($payment_date) + 60) {
            continue; // Item did not exist during Payment 1 (Deposit)
        }
    }
    
    // Calculate payment amount for this row
    if ($unit_price == 0 || $total_amount == 0) {
        $payment_amount = 0.00;
    } elseif ($seq === 1) {
        // Initial deposit
        $payment_amount = min($txn_payment, $total_amount);
    } else {
        // Claim / Subsequent payment
        $is_added_at_claim = (!empty($item_created) && !empty($preorder_created) && (strtotime($item_created) > strtotime($preorder_created) + 60));
        if ($is_added_at_claim) {
            $payment_amount = $total_amount;
        } else {
            // Main preorder item balance payment
            $deposit_before = floatval($row['balance_before']);
            $payment_amount = ($deposit_before > 0) ? min($total_amount, $deposit_before) : $total_amount;
        }
    }
    
    $records[] = [
        'preorder_no'        => $row['preorder_no'],
        'claimed_invoice_no' => $row['claimed_invoice_no'],
        'customer_name'      => $row['customer_name'],
        'item_description'   => $row['item_description'],
        'imei'               => $row['imei'],
        'quantity'           => $row['quantity'],
        'unit_price'         => $unit_price,
        'total_amount'       => $total_amount,
        'payment_amount'     => $payment_amount,
        'status'             => $row['status'], // Use current status from preorders table
        'date_created'       => $row['date_created'], // Use payment_date from history
        'claimed_at'         => $row['claimed_at']
    ];
}

$stmt->close();

// Create PDF
class PreOrderReportPDF extends FPDF
{
    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Courier', 'B', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, 'PAGE ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PreOrderReportPDF('L', 'mm', 'A4');
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();

// Logo
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 10, 20, 20);
}

// Title
$pdf->SetFont('Courier', 'B', 14);
$pdf->SetY(22);
$pdf->Cell(0, 8, 'PRE ORDER REPORT', 0, 1, 'C');
$pdf->Ln(2);

// Meta information
$pdf->SetFont('Courier', 'B', 8);
$pdf->Cell(25, 5, 'BRANCH:', 0, 0, 'L');
$pdf->SetFont('Courier', '', 8);
$pdf->Cell(0, 5, strtoupper($branch), 0, 1, 'L');

$pdf->SetFont('Courier', 'B', 8);
$pdf->Cell(25, 5, 'DATE RANGE:', 0, 0, 'L');
$pdf->SetFont('Courier', '', 8);
$pdf->Cell(0, 5, date('F d, Y', strtotime($date_from)) . ' to ' . date('F d, Y', strtotime($date_to)), 0, 1, 'L');

$pdf->SetFont('Courier', 'B', 8);
$pdf->Cell(25, 5, 'STATUS:', 0, 0, 'L');
$pdf->SetFont('Courier', '', 8);
$pdf->Cell(0, 5, !empty($status) ? strtoupper($status) : 'ALL STATUS', 0, 1, 'L');

$pdf->Ln(2);

// Table Header
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Courier', 'B', 6.5);

$widths  = [28, 32, 45, 20, 10, 18, 20, 18, 18, 23, 24];
$headers = ['PRE ORDER NO', 'CUSTOMER NAME', 'ITEM DESCRIPTION', 'IMEI', 'QTY', 'UNIT PRICE', 'TOTAL AMT', 'PAYMENT', 'STATUS', 'DATE CREATED', 'DATE CLAIMED'];

// Calculate starting X position to center the table
$table_width = array_sum($widths);
$page_width = 297; // A4 landscape width in mm
$start_x = ($page_width - $table_width) / 2;

$pdf->SetX($start_x);
for ($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
}
$pdf->Ln();

// Table Data
$pdf->SetFont('Courier', '', 6);
if (count($records) === 0) {
    $pdf->SetX($start_x);
    $pdf->Cell(array_sum($widths), 8, 'NO DATA', 1, 1, 'C');
} else {
    foreach ($records as $row) {
        $preorder_no    = $row['preorder_no']      ? strtoupper($row['preorder_no'])                     : 'N/A';
        $customer_name  = $row['customer_name']    ? strtoupper(substr($row['customer_name'], 0, 30))    : 'N/A';
        $item_desc      = $row['item_description'] ? strtoupper(substr($row['item_description'], 0, 40))    : 'N/A';
        $imei           = $row['imei']             ? strtoupper(substr($row['imei'], 0, 18))               : '';
        $qty            = $row['quantity']          ? $row['quantity']                                    : '0';
        $unit_price     = number_format($row['unit_price'], 2);
        $total_amount   = number_format($row['total_amount'], 2);
        $payment_amount = number_format($row['payment_amount'], 2);
        $status_display = $row['status']           ? strtoupper($row['status'])                          : 'N/A';
        $date_created   = $row['date_created']     ? date('m/d/Y', strtotime($row['date_created']))      : 'N/A';
        $date_claimed   = $row['claimed_at']       ? date('m/d/Y', strtotime($row['claimed_at']))        : '-';
        
        $pdf->SetX($start_x);
        $pdf->Cell($widths[0], 6, $preorder_no,    1, 0, 'C');
        $pdf->Cell($widths[1], 6, $customer_name,  1, 0, 'L');
        $pdf->Cell($widths[2], 6, $item_desc,      1, 0, 'L');
        $pdf->Cell($widths[3], 6, $imei,           1, 0, 'C');
        $pdf->Cell($widths[4], 6, $qty,            1, 0, 'C');
        $pdf->Cell($widths[5], 6, $unit_price,     1, 0, 'R');
        $pdf->Cell($widths[6], 6, $total_amount,   1, 0, 'R');
        $pdf->Cell($widths[7], 6, $payment_amount, 1, 0, 'R');
        $pdf->Cell($widths[8], 6, $status_display, 1, 0, 'C');
        $pdf->Cell($widths[9], 6, $date_created,   1, 0, 'C');
        $pdf->Cell($widths[10], 6, $date_claimed,  1, 1, 'C');
    }
}

$filename = 'PreOrder_Report_' . str_replace(' ', '_', $branch) . '_' . $date_from . '_to_' . $date_to . '.pdf';
$pdf->Output('I', $filename);

$conn->close();
?>
