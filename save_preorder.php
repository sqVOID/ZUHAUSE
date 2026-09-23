<?php
require_once 'session_check.php';
// Suppress all output before JSON response
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';
session_start();

// Clean any output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Check if JSON decode was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data received']);
    exit;
}

// Validate required fields
if (empty($data['first_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'First Name is required!']);
    exit;
}
if (empty($data['last_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Last Name is required!']);
    exit;
}
if (empty($data['address'])) {
    echo json_encode(['status' => 'error', 'message' => 'Address is required!']);
    exit;
}
if (empty($data['contact_no'])) {
    echo json_encode(['status' => 'error', 'message' => 'Contact No is required!']);
    exit;
}
if (empty($data['assisted_by'])) {
    echo json_encode(['status' => 'error', 'message' => 'Assisted By is required!']);
    exit;
}

// Validate that there are items
if (empty($data['items']) || count($data['items']) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please add at least one item to the pre-order!']);
    exit;
}

// Get branch code from session
$branch_code = '000'; // Default
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
if (isset($_SESSION['user_branch'])) {
    $branch_name_exact = $user_branch;
    $branch_name_base = trim(preg_replace('/\s*-\s*[A-Za-z0-9]+$/', '', $user_branch));
    if ($branch_name_base === '') {
        $branch_name_base = $branch_name_exact;
    }

    $branch_lookup = $conn->prepare("
        SELECT branch_code
        FROM branches
        WHERE branch_name = ? OR branch_name = ?
        ORDER BY (branch_name = ?) DESC
        LIMIT 1
    ");
    $branch_lookup->bind_param("sss", $branch_name_exact, $branch_name_base, $branch_name_exact);
    $branch_lookup->execute();
    $branch_result = $branch_lookup->get_result();
    if ($branch_result && $branch_result->num_rows > 0) {
        $branch_data = $branch_result->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
    $branch_lookup->close();
}

// Generate Pre-order Invoice Number using booklet configuration
include_once 'get_next_invoice_number.php';

$booklet = getBookletConfig($conn, $branch_code, 'preorder');

if ($booklet) {
    // ── VALIDATE BOOKLET RANGE ──────────────────────────────────────────────
    // Extract the numeric part from current_number for comparison
    $current_number_numeric = null;

    if ($booklet['booklet_format'] === 'numeric') {
        // For numeric format like "0003128-092-149", extract last segment
        $parts = explode('-', $booklet['current_number']);
        $current_number_numeric = intval(end($parts));
    } elseif (is_numeric(ltrim($booklet['current_number'], '0') ?: '0')) {
        // For pure numeric strings like "0000001"
        $current_number_numeric = intval($booklet['current_number']);
    }

    // If we can extract numeric value, validate against booklet range
    if ($current_number_numeric !== null) {
        // Extract ending number for comparison
        $ending_number_numeric = null;
        if ($booklet['booklet_format'] === 'numeric') {
            $ending_parts = explode('-', $booklet['ending_number']);
            $ending_number_numeric = intval(end($ending_parts));
        } elseif (is_numeric(ltrim($booklet['ending_number'], '0') ?: '0')) {
            $ending_number_numeric = intval($booklet['ending_number']);
        }

        // Check if current number exceeds ending number
        if ($ending_number_numeric !== null && $current_number_numeric > $ending_number_numeric) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Booklet number range exhausted! Current number (' . $booklet['current_number'] . ') has exceeded the ending number (' . $booklet['ending_number'] . '). Please register a new booklet range or contact administrator.'
            ]);
            exit;
        }
    }

    // Use booklet number configuration
    $invoice_no = generateInvoiceNumber($booklet);

    // Auto-increment for numeric formats
    // Also increment 'custom' format if current_number is purely numeric (e.g. 0000001)
    if ($booklet['booklet_format'] === 'numeric') {
        $next_number = incrementInvoiceNumber($booklet['current_number'], 'numeric');
        // Update booklet for next invoice
        updateInvoiceNumber($conn, $booklet['id'], $next_number);
    } elseif ($booklet['booklet_format'] === 'custom' && is_numeric(ltrim($booklet['current_number'], '0') ?: '0')) {
        // current_number is a zero-padded numeric string (e.g. "0000001") — safe to auto-increment
        $current_num = intval($booklet['current_number']);
        $padding = strlen($booklet['current_number']);
        $next_number = str_pad($current_num + 1, $padding, '0', STR_PAD_LEFT);
        updateInvoiceNumber($conn, $booklet['id'], $next_number);
    }
} else {
    // Fallback to old system if no booklet configured for preorder
    $today = date('Ymd');
    $prefix = "PRE-{$today}-";

    // Get the highest invoice number from ALL preorders (not just today)
    $invoice_query = $conn->prepare("SELECT invoice_no FROM preorders WHERE invoice_no LIKE 'PRE-%' ORDER BY invoice_no DESC LIMIT 1");
    $invoice_query->execute();
    $invoice_result = $invoice_query->get_result();

    if ($invoice_result && $invoice_result->num_rows > 0) {
        $row = $invoice_result->fetch_assoc();
        $last_invoice = $row['invoice_no'];
        // Extract the last 4 digits from any PRE-YYYYMMDD-#### format
        $last_id = intval(substr($last_invoice, -4));
        $next_id = $last_id + 1;
    } else {
        $next_id = 1;
    }
    $invoice_query->close();

    $formatted_id = str_pad($next_id, 4, '0', STR_PAD_LEFT);
    $invoice_no = $prefix . $formatted_id;
}

// Resolve payment json
$payment_data_raw = isset($data['payment_data']) ? $data['payment_data'] : null;
$payment_data_json = $payment_data_raw ? json_encode($payment_data_raw) : null;

// Calculate total payment amount to determine status
$total_payment_amount = 0;
if ($payment_data_raw && is_array($payment_data_raw)) {
    $payments_to_check = [];

    // Handle multiple payments
    if (isset($payment_data_raw['payment_type']) && $payment_data_raw['payment_type'] === 'multiple') {
        $payments_to_check = $payment_data_raw['payments'];
    } else {
        $payments_to_check = [$payment_data_raw];
    }

    foreach ($payments_to_check as $payment) {
        if (isset($payment['amount'])) {
            $total_payment_amount += floatval(str_replace(',', '', $payment['amount']));
        }
        // Handle payment partners (down payments)
        if (isset($payment['payment_type']) && $payment['payment_type'] === 'payment_partners') {
            if (isset($payment['cash_dp_amount'])) {
                $total_payment_amount += floatval(str_replace(',', '', $payment['cash_dp_amount']));
            }
            if (isset($payment['gcash_dp_amount'])) {
                $total_payment_amount += floatval(str_replace(',', '', $payment['gcash_dp_amount']));
            }
            if (isset($payment['maya_dp_amount'])) {
                $total_payment_amount += floatval(str_replace(',', '', $payment['maya_dp_amount']));
            }
        }
    }
}

// Determine initial status based on payment
// If fully paid (remaining balance <= 0.01), status is 'fully paid'
// If partially paid, status is 'partial'
// Otherwise, status is 'pending'
$total_amount = floatval($data['total_amount']);
$remaining_balance = $total_amount - $total_payment_amount;

if ($remaining_balance <= 0.01 && $total_payment_amount > 0) {
    $initial_status = 'fully paid';
} elseif ($total_payment_amount > 0) {
    $initial_status = 'partial';
} else {
    $initial_status = 'pending';
}

// Determine encoder
$encoder = '';
if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
    $encoder = trim($_SESSION['user_name']);
} elseif (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
    $encoder = trim($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
} elseif (isset($_SESSION['username'])) {
    $encoder = trim($_SESSION['username']);
} else {
    $encoder = $data['assisted_by'] ?: 'System';
}

// Start transaction
$conn->begin_transaction();

try {
    // Set completed_at timestamp if status is fully paid
    $completed_at = ($initial_status === 'fully paid') ? date('Y-m-d H:i:s') : null;

    // Insert into preorders
    $stmt = $conn->prepare("
        INSERT INTO preorders (
            invoice_no,
            first_name,
            last_name,
            address,
            contact_no,
            email,
            assisted_by,
            remarks,
            total_qty,
            discount,
            total_amount,
            payment_data,
            branch_code,
            encoder,
            status,
            completed_at,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $first_name = strtoupper(trim($data['first_name']));
    $last_name = strtoupper(trim($data['last_name']));
    $address = trim($data['address']);
    $contact_no = trim($data['contact_no']);
    $email = trim($data['email']);
    $assisted_by = trim($data['assisted_by']);
    $remarks = strtoupper(trim($data['remarks']));
    $total_qty = intval($data['total_qty']);
    $discount = floatval($data['discount']);
    $total_amount = floatval($data['total_amount']);

    $stmt->bind_param(
        "ssssssssdddsssss",
        $invoice_no,
        $first_name,
        $last_name,
        $address,
        $contact_no,
        $email,
        $assisted_by,
        $remarks,
        $total_qty,
        $discount,
        $total_amount,
        $payment_data_json,
        $branch_code,
        $encoder,
        $initial_status,
        $completed_at
    );

    $stmt->execute();
    $preorder_id = $conn->insert_id;
    $stmt->close();

    // Insert items into preorder_items
    $stmt_items = $conn->prepare("
        INSERT INTO preorder_items (
            preorder_id,
            family_code,
            item_description,
            quantity,
            price
        ) VALUES (?, ?, NULL, ?, ?)
    ");

    foreach ($data['items'] as $item) {
        $family_code = trim($item['family_code']);
        $qty = intval($item['quantity']);
        $price = floatval($item['price']);

        $stmt_items->bind_param(
            "isid",
            $preorder_id,
            $family_code,
            $qty,
            $price
        );
        $stmt_items->execute();
    }
    $stmt_items->close();

    // Insert payment history records if payment data exists
    if ($payment_data_raw && is_array($payment_data_raw)) {
        $payments_array = [];

        // Handle multiple payments
        if (isset($payment_data_raw['payment_type']) && $payment_data_raw['payment_type'] === 'multiple') {
            $payments_array = $payment_data_raw['payments'];
        } else {
            $payments_array = [$payment_data_raw];
        }

        $sequence = 1;
        $running_balance = floatval($total_amount);
        $total_payments = count($payments_array);

        foreach ($payments_array as $payment) {
            $payment_type = $payment['payment_type'] ?? 'unknown';
            $amount = 0;

            // Calculate amount based on payment type
            if (isset($payment['amount'])) {
                $amount = floatval(str_replace(',', '', $payment['amount']));
            } elseif ($payment_type === 'payment_partners') {
                // Handle payment partners (Home Credit, etc.)
                $loan_balance = isset($payment['loan_balance']) ? floatval(str_replace(',', '', $payment['loan_balance'])) : 0;
                $cash_dp = isset($payment['cash_dp_amount']) ? floatval(str_replace(',', '', $payment['cash_dp_amount'])) : 0;
                $gcash_dp = isset($payment['gcash_dp_amount']) ? floatval(str_replace(',', '', $payment['gcash_dp_amount'])) : 0;
                $maya_dp = isset($payment['maya_dp_amount']) ? floatval(str_replace(',', '', $payment['maya_dp_amount'])) : 0;
                $amount = $loan_balance + $cash_dp + $gcash_dp + $maya_dp;
            }

            if ($amount > 0) {
                // Get payment method details
                $payment_method = 'Unknown';
                if ($payment_type === 'ewallet') {
                    $payment_method = $payment['ewallet_type'] ?? 'E-Wallet';
                } elseif ($payment_type === 'online_banking') {
                    $payment_method = $payment['bank_name'] ?? 'Online Banking';
                } elseif ($payment_type === 'payment_partners') {
                    $payment_method = $payment['payment_partner'] ?? 'Payment Partner';
                } else {
                    $payment_method = ucfirst(str_replace('_', ' ', $payment_type));
                }

                $balance_before = $running_balance;
                $balance_after = $running_balance - $amount;
                $running_balance = $balance_after;

                // Status logic: if balance_after > 0, it's partial (not fully paid yet)
                $status_after = ($balance_after <= 0.01) ? 'fully paid' : 'partial';

                // Payment date is NOW for all payments when creating new preorder
                $payment_date = date('Y-m-d H:i:s');

                // Insert into payment history
                $history_stmt = $conn->prepare("
                    INSERT INTO preorder_payment_history 
                    (preorder_id, invoice_no, payment_date, payment_type, payment_method, 
                     amount, payment_data, payment_sequence, status_after_payment, 
                     balance_before, balance_after, branch_code, encoder, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $payment_json = json_encode($payment);

                $history_stmt->bind_param(
                    "issssdsisddss",
                    $preorder_id,
                    $invoice_no,
                    $payment_date,
                    $payment_type,
                    $payment_method,
                    $amount,
                    $payment_json,
                    $sequence,
                    $status_after,
                    $balance_before,
                    $balance_after,
                    $branch_code,
                    $encoder
                );

                $history_stmt->execute();
                $history_stmt->close();
            }

            $sequence++;
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'invoice_no' => $invoice_no
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>