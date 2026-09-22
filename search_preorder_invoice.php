<?php
require_once 'session_check.php';
// Suppress all errors and warnings
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

include 'config.php';

// Clean buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

$invoice_no = isset($_GET['invoice_no']) ? trim($_GET['invoice_no']) : '';

if (empty($invoice_no)) {
    echo json_encode(['status' => 'error', 'message' => 'Invoice number is required']);
    exit;
}

try {
    // Search for the preorder matching initial invoice, claimed invoice, or payment history invoice
    $stmt = $conn->prepare("
        SELECT DISTINCT p.* 
        FROM preorders p 
        LEFT JOIN preorder_payment_history ph ON p.id = ph.preorder_id
        WHERE p.invoice_no = ? 
           OR p.claimed_invoice_no = ? 
           OR ph.invoice_no = ?
        LIMIT 1
    ");
    $stmt->bind_param("sss", $invoice_no, $invoice_no, $invoice_no);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Invoice not found'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }

    $preorder = $result->fetch_assoc();
    $stmt->close();

    // Only allow pending or partial preorders to be processed for additional payment
    if ($preorder['status'] === 'fully paid' || $preorder['status'] === 'claimed') {
        echo json_encode([
            'status' => 'error',
            'message' => 'This pre-order has already been ' . $preorder['status'] . '. No additional payment needed.'
        ]);
        $conn->close();
        exit;
    }

    // Get total amount
    $totalAmount = floatval($preorder['total_amount']);
    
    // Parse payment data JSON to calculate total paid
    $payment_data = [];
    $totalPaid = 0;
    $paymentStatus = 'unpaid';
    
    if (!empty($preorder['payment_data'])) {
        $payment_data = json_decode($preorder['payment_data'], true);
        
        if (!empty($payment_data)) {
            // Handle payment_partners structure
            if (isset($payment_data['payment_type']) && $payment_data['payment_type'] === 'payment_partners') {
                $loanBalance = floatval(str_replace(',', '', $payment_data['loan_balance'] ?? $payment_data['total_loan_amount'] ?? '0'));
                $cashDp = floatval(str_replace(',', '', $payment_data['cash_dp_amount'] ?? '0'));
                $gcashDp = floatval(str_replace(',', '', $payment_data['gcash_dp_amount'] ?? '0'));
                $mayaDp = floatval(str_replace(',', '', $payment_data['maya_dp_amount'] ?? '0'));
                
                $totalPaid = $loanBalance + $cashDp + $gcashDp + $mayaDp;
            }
            // Handle new payment data structure with payment_type and amount
            else if (isset($payment_data['payment_type']) && isset($payment_data['amount'])) {
                $totalPaid = floatval(str_replace(',', '', $payment_data['amount']));
            }
            // Handle old payment data structure (fallback)
            else {
                if (isset($payment_data['cash'])) {
                    $totalPaid += floatval(str_replace(',', '', $payment_data['cash']));
                }
                if (isset($payment_data['home_credit_amount'])) {
                    $totalPaid += floatval(str_replace(',', '', $payment_data['home_credit_amount']));
                }
                if (isset($payment_data['credit_card_amount'])) {
                    $totalPaid += floatval(str_replace(',', '', $payment_data['credit_card_amount']));
                }
                if (isset($payment_data['debit_card_amount'])) {
                    $totalPaid += floatval(str_replace(',', '', $payment_data['debit_card_amount']));
                }
                if (isset($payment_data['qr_ph_amount'])) {
                    $totalPaid += floatval(str_replace(',', '', $payment_data['qr_ph_amount']));
                }
                if (isset($payment_data['starpay_qr_amount'])) {
                    $totalPaid += floatval(str_replace(',', '', $payment_data['starpay_qr_amount']));
                }
                if (isset($payment_data['ewallet_amount'])) {
                    $totalPaid += floatval(str_replace(',', '', $payment_data['ewallet_amount']));
                }
                if (isset($payment_data['online_banking_amount'])) {
                    $totalPaid += floatval(str_replace(',', '', $payment_data['online_banking_amount']));
                }
            }
            
            // Check if fully paid
            if ($totalPaid >= $totalAmount) {
                $paymentStatus = 'paid';
            }
        }
    }

    // Calculate remaining balance
    $remaining_balance = $totalAmount - $totalPaid;

    // Parse payment data to get payment method (similar to search_preorder.php)
    $paymentMethod = 'N/A';
    if (!empty($preorder['payment_data'])) {
        $parsed_payment_data = json_decode($preorder['payment_data'], true);
        
        if (!empty($parsed_payment_data)) {
            // Handle payment_partners structure
            if (isset($parsed_payment_data['payment_type']) && $parsed_payment_data['payment_type'] === 'payment_partners') {
                $paymentMethod = isset($parsed_payment_data['payment_partner']) ? $parsed_payment_data['payment_partner'] : 'Payment Partner';
            }
            // Handle new payment data structure with payment_type and amount
            else if (isset($parsed_payment_data['payment_type']) && isset($parsed_payment_data['amount'])) {
                $paymentMethod = ucwords(str_replace('_', ' ', $parsed_payment_data['payment_type']));
            }
            // Handle old payment data structure (fallback)
            else {
                if (isset($parsed_payment_data['cash']) && floatval(str_replace(',', '', $parsed_payment_data['cash'])) > 0) {
                    $paymentMethod = 'Cash';
                }
                if (isset($parsed_payment_data['home_credit_amount']) && floatval(str_replace(',', '', $parsed_payment_data['home_credit_amount'])) > 0) {
                    $paymentMethod = 'Home Credit';
                }
                if (isset($parsed_payment_data['credit_card_amount']) && floatval(str_replace(',', '', $parsed_payment_data['credit_card_amount'])) > 0) {
                    $paymentMethod = isset($parsed_payment_data['credit_card_issuer']) ? $parsed_payment_data['credit_card_issuer'] . ' (Credit Card)' : 'Credit Card';
                }
                if (isset($parsed_payment_data['debit_card_amount']) && floatval(str_replace(',', '', $parsed_payment_data['debit_card_amount'])) > 0) {
                    $paymentMethod = 'Debit Card';
                }
                if (isset($parsed_payment_data['qr_ph_amount']) && floatval(str_replace(',', '', $parsed_payment_data['qr_ph_amount'])) > 0) {
                    $paymentMethod = 'QR PH';
                }
                if (isset($parsed_payment_data['starpay_qr_amount']) && floatval(str_replace(',', '', $parsed_payment_data['starpay_qr_amount'])) > 0) {
                    $paymentMethod = 'Starpay QR';
                }
                if (isset($parsed_payment_data['ewallet_amount']) && floatval(str_replace(',', '', $parsed_payment_data['ewallet_amount'])) > 0) {
                    $paymentMethod = isset($parsed_payment_data['ewallet_provider']) ? $parsed_payment_data['ewallet_provider'] . ' (E-Wallet)' : 'E-Wallet';
                }
                if (isset($parsed_payment_data['online_banking_amount']) && floatval(str_replace(',', '', $parsed_payment_data['online_banking_amount'])) > 0) {
                    $paymentMethod = 'Online Banking';
                }
            }
        }
    }

    // Fetch preorder items
    $items_query = "
        SELECT 
            family_code,
            quantity,
            price
        FROM preorder_items
        WHERE preorder_id = ?
    ";

    $stmt_items = $conn->prepare($items_query);
    $stmt_items->bind_param("i", $preorder['id']);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();

    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        // Add payment_method to each item (same as search_preorder.php does)
        $item['payment_method'] = $paymentMethod;
        $items[] = $item;
    }
    $stmt_items->close();

    // Compute next booklet invoice number for preview
    include_once 'get_next_invoice_number.php';
    $next_invoice_no = '';
    $booklet = getBookletConfig($conn, $preorder['branch_code'], 'preorder');
    if ($booklet) {
        $next_invoice_no = generateInvoiceNumber($booklet) . '-PRE';
    }

    // Return data
    echo json_encode([
        'status' => 'success',
        'data' => [
            'id' => $preorder['id'],
            'invoice_no' => $preorder['invoice_no'],
            'next_invoice_no' => $next_invoice_no,
            'first_name' => $preorder['first_name'],
            'last_name' => $preorder['last_name'],
            'address' => $preorder['address'],
            'contact_no' => $preorder['contact_no'],
            'email' => $preorder['email'],
            'assisted_by' => $preorder['assisted_by'],
            'remarks' => $preorder['remarks'],
            'total_qty' => $preorder['total_qty'],
            'discount' => $preorder['discount'],
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'remaining_balance' => $remaining_balance,
            'payment_data' => $preorder['payment_data'], // Include payment_data for display
            'branch_code' => $preorder['branch_code'],
            'items' => $items
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred'
    ]);
}

$conn->close();
?>
