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
if (empty($data['preorder_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Preorder ID is required!']);
    exit;
}

if (empty($data['payment_data'])) {
    echo json_encode(['status' => 'error', 'message' => 'Payment data is required!']);
    exit;
}

$preorder_id = intval($data['preorder_id']);
$new_payment_data = $data['payment_data'];

// Start transaction
$conn->begin_transaction();

try {
    // Get existing preorder data
    $stmt = $conn->prepare("SELECT payment_data, total_amount FROM preorders WHERE id = ?");
    $stmt->bind_param("i", $preorder_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Preorder not found');
    }

    $preorder = $result->fetch_assoc();
    $stmt->close();

    // Parse existing payment data
    $existing_payment_data = null;
    if (!empty($preorder['payment_data'])) {
        $existing_payment_data = json_decode($preorder['payment_data'], true);
    }

    // Merge payment data - create array of payments
    $merged_payments = [];

    // Add existing payment(s) to array
    if (!empty($existing_payment_data)) {
        if (isset($existing_payment_data['payment_type']) && $existing_payment_data['payment_type'] === 'multiple') {
            // Existing data already has multiple payments
            $merged_payments = $existing_payment_data['payments'];
        } else {
            // Single existing payment
            $merged_payments[] = $existing_payment_data;
        }
    }

    // Add new payment(s) to array
    if (isset($new_payment_data['payment_type']) && $new_payment_data['payment_type'] === 'multiple') {
        // New data has multiple payments
        foreach ($new_payment_data['payments'] as $payment) {
            $merged_payments[] = $payment;
        }
    } else {
        // Single new payment
        $merged_payments[] = $new_payment_data;
    }

    // Create final payment data structure
    $final_payment_data = [
        'payment_type' => 'multiple',
        'payments' => $merged_payments
    ];

    $payment_data_json = json_encode($final_payment_data);

    // Calculate total paid amount from merged payments
    $total_paid = 0;
    foreach ($merged_payments as $payment) {
        if (isset($payment['payment_type']) && $payment['payment_type'] === 'payment_partners') {
            if (isset($payment['loan_balance'])) {
                $total_paid += floatval(str_replace(',', '', $payment['loan_balance']));
            }
            if (isset($payment['cash_dp_amount'])) {
                $total_paid += floatval(str_replace(',', '', $payment['cash_dp_amount']));
            }
            if (isset($payment['gcash_dp_amount'])) {
                $total_paid += floatval(str_replace(',', '', $payment['gcash_dp_amount']));
            }
            if (isset($payment['maya_dp_amount'])) {
                $total_paid += floatval(str_replace(',', '', $payment['maya_dp_amount']));
            }
        } elseif (isset($payment['amount'])) {
            $amount_str = str_replace(',', '', $payment['amount']);
            $total_paid += floatval($amount_str);
        }
    }

    // Calculate remaining balance
    $total_amount = floatval($preorder['total_amount']);
    $remaining_balance = $total_amount - $total_paid;

    // Determine status based on remaining balance
    $status = 'pending';
    $date_completed = null;

    if ($remaining_balance <= 0.01) {
        $status = 'fully paid'; // Fully paid
        $date_completed = date('Y-m-d H:i:s'); // Set completion date to NOW
    } else if ($total_paid > 0) {
        $status = 'partial'; // Partially paid
    }

    // Update preorder with merged payment data, status, and completion date
    $update_stmt = $conn->prepare("
        UPDATE preorders 
        SET payment_data = ?, 
            status = ?,
            completed_at = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $update_stmt->bind_param("sssi", $payment_data_json, $status, $date_completed, $preorder_id);
    $update_stmt->execute();
    $update_stmt->close();

    // Get count of existing payment history records for this preorder
    $existing_history_count = 0;
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM preorder_payment_history WHERE preorder_id = ?");
    $count_stmt->bind_param("i", $preorder_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    if ($count_result && $count_result->num_rows > 0) {
        $count_row = $count_result->fetch_assoc();
        $existing_history_count = intval($count_row['count']);
    }
    $count_stmt->close();

    // Only insert NEW payments (not the ones that already have history records)
    // If there are already 2 history records, only insert from index 2 onwards
    $start_index = $existing_history_count;

    // Calculate running balance up to the start index
    $running_balance = floatval($preorder['total_amount']);
    for ($i = 0; $i < $start_index; $i++) {
        if (isset($merged_payments[$i]['payment_type']) && $merged_payments[$i]['payment_type'] === 'payment_partners') {
            $p_amt = 0;
            if (isset($merged_payments[$i]['loan_balance']))
                $p_amt += floatval(str_replace(',', '', $merged_payments[$i]['loan_balance']));
            if (isset($merged_payments[$i]['cash_dp_amount']))
                $p_amt += floatval(str_replace(',', '', $merged_payments[$i]['cash_dp_amount']));
            if (isset($merged_payments[$i]['gcash_dp_amount']))
                $p_amt += floatval(str_replace(',', '', $merged_payments[$i]['gcash_dp_amount']));
            if (isset($merged_payments[$i]['maya_dp_amount']))
                $p_amt += floatval(str_replace(',', '', $merged_payments[$i]['maya_dp_amount']));
            $running_balance -= $p_amt;
        } elseif (isset($merged_payments[$i]['amount'])) {
            $amount = floatval(str_replace(',', '', $merged_payments[$i]['amount']));
            $running_balance -= $amount;
        }
    }

    // Insert only the NEW payment(s) into payment history table
    for ($index = $start_index; $index < count($merged_payments); $index++) {
        $payment = $merged_payments[$index];
        $payment_type = $payment['payment_type'] ?? 'unknown';
        $amount = 0;
        if ($payment_type === 'payment_partners') {
            if (isset($payment['loan_balance']))
                $amount += floatval(str_replace(',', '', $payment['loan_balance']));
            if (isset($payment['cash_dp_amount']))
                $amount += floatval(str_replace(',', '', $payment['cash_dp_amount']));
            if (isset($payment['gcash_dp_amount']))
                $amount += floatval(str_replace(',', '', $payment['gcash_dp_amount']));
            if (isset($payment['maya_dp_amount']))
                $amount += floatval(str_replace(',', '', $payment['maya_dp_amount']));
        } else {
            $amount = floatval(str_replace(',', '', $payment['amount'] ?? '0'));
        }

        if ($amount <= 0) {
            continue; // Skip zero amount payments
        }

        // Get payment method details
        $payment_method = null;
        if ($payment_type === 'ewallet') {
            $payment_method = $payment['ewallet_type'] ?? 'E-Wallet';
        } elseif ($payment_type === 'online_banking') {
            $payment_method = $payment['bank_name'] ?? 'Online Banking';
        } elseif ($payment_type === 'payment_partners') {
            $payment_method = $payment['payment_partner'] ?? 'Payment Partner';
        } else {
            $payment_method = ucfirst(str_replace('_', ' ', $payment_type));
        }

        // Calculate sequence number (existing count + 1, 2, 3...)
        $sequence = $index + 1;

        // Get branch code
        $branch_code_query = $conn->prepare("SELECT branch_code FROM preorders WHERE id = ?");
        $branch_code_query->bind_param("i", $preorder_id);
        $branch_code_query->execute();
        $branch_result = $branch_code_query->get_result();
        $branch_code = '000';
        if ($branch_result && $branch_result->num_rows > 0) {
            $branch_row = $branch_result->fetch_assoc();
            $branch_code = $branch_row['branch_code'];
        }
        $branch_code_query->close();

        // Generate new/next invoice number for this payment using booklet configuration
        include_once 'get_next_invoice_number.php';
        $booklet = getBookletConfig($conn, $branch_code, 'preorder');
        $invoice_no = '';

        if ($booklet) {
            $current_number_numeric = null;
            if ($booklet['booklet_format'] === 'numeric') {
                $parts = explode('-', $booklet['current_number']);
                $current_number_numeric = intval(end($parts));
            } elseif (is_numeric(ltrim($booklet['current_number'], '0') ?: '0')) {
                $current_number_numeric = intval($booklet['current_number']);
            }

            if ($current_number_numeric !== null) {
                $ending_number_numeric = null;
                if ($booklet['booklet_format'] === 'numeric') {
                    $ending_parts = explode('-', $booklet['ending_number']);
                    $ending_number_numeric = intval(end($ending_parts));
                } elseif (is_numeric(ltrim($booklet['ending_number'], '0') ?: '0')) {
                    $ending_number_numeric = intval($booklet['ending_number']);
                }

                if ($ending_number_numeric !== null && $current_number_numeric > $ending_number_numeric) {
                    throw new Exception('Booklet number range exhausted! Current number (' . $booklet['current_number'] . ') has exceeded the ending number (' . $booklet['ending_number'] . '). Please register a new booklet range or contact administrator.');
                }
            }

            $invoice_no = generateInvoiceNumber($booklet) . '-PRE';
            if ($booklet['booklet_format'] === 'numeric') {
                $next_number = incrementInvoiceNumber($booklet['current_number'], 'numeric');
                updateInvoiceNumber($conn, $booklet['id'], $next_number);
            } elseif ($booklet['booklet_format'] === 'custom' && is_numeric(ltrim($booklet['current_number'], '0') ?: '0')) {
                $current_num = intval($booklet['current_number']);
                $padding = strlen($booklet['current_number']);
                $next_number = str_pad($current_num + 1, $padding, '0', STR_PAD_LEFT);
                updateInvoiceNumber($conn, $booklet['id'], $next_number);
            }
        }

        if (empty($invoice_no)) {
            // Fallback to existing preorder invoice_no if booklet is not configured
            $inv_query = $conn->prepare("SELECT invoice_no FROM preorders WHERE id = ?");
            $inv_query->bind_param("i", $preorder_id);
            $inv_query->execute();
            $inv_result = $inv_query->get_result();
            if ($inv_result && $inv_result->num_rows > 0) {
                $inv_row = $inv_result->fetch_assoc();
                $invoice_no = $inv_row['invoice_no'];
            }
            $inv_query->close();
        }

        // Calculate balance before and after for THIS specific payment
        $balance_before = $running_balance;
        $balance_after = $running_balance - $amount;
        $running_balance = $balance_after;

        // Status after this specific payment
        $status_after_payment = ($balance_after <= 0.01) ? 'fully paid' : 'partial';

        // Get encoder info
        $encoder = '';
        if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
            $encoder = trim($_SESSION['user_name']);
        } elseif (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
            $encoder = trim($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
        } elseif (isset($_SESSION['username'])) {
            $encoder = trim($_SESSION['username']);
        }

        // Insert into payment history - use current timestamp for new payments
        $history_stmt = $conn->prepare("
            INSERT INTO preorder_payment_history 
            (preorder_id, invoice_no, payment_date, payment_type, payment_method, 
             amount, payment_data, payment_sequence, status_after_payment, 
             balance_before, balance_after, branch_code, encoder, created_at)
            VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $payment_json = json_encode($payment);

        $history_stmt->bind_param(
            "isssdsisddss",
            $preorder_id,
            $invoice_no,
            $payment_type,
            $payment_method,
            $amount,
            $payment_json,
            $sequence,
            $status_after_payment,
            $balance_before,
            $balance_after,
            $branch_code,
            $encoder
        );

        $history_stmt->execute();
        $history_stmt->close();
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Payment saved successfully!',
        'remaining_balance' => $remaining_balance,
        'preorder_status' => $status,
        'total_paid' => $total_paid,
        'new_invoice_no' => $invoice_no
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