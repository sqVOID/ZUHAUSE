<?php
require_once 'session_check.php';
include 'config.php';

// Handle POST request (Searching a specific pre-order by preorder_no for claim)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $preorder_no = isset($input['preorder_no']) ? trim($input['preorder_no']) : '';

    if (empty($preorder_no)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a pre-order number']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT DISTINCT p.* 
        FROM preorders p 
        LEFT JOIN preorder_payment_history ph ON p.id = ph.preorder_id
        WHERE p.invoice_no = ? 
           OR p.claimed_invoice_no = ? 
           OR ph.invoice_no = ?
        LIMIT 1
    ");
    $stmt->bind_param("sss", $preorder_no, $preorder_no, $preorder_no);
    $stmt->execute();
    $preorder_res = $stmt->get_result();

    if ($preorder_res && $preorder_res->num_rows > 0) {
        $preorder = $preorder_res->fetch_assoc();
        $preorder_id = $preorder['id'];

        // Only search preorders that are 'fully paid' or 'partial'
        if ($preorder['status'] !== 'fully paid' && $preorder['status'] !== 'partial') {
            echo json_encode(['success' => false, 'message' => 'Only fully paid or partial pre-orders can be claimed. This pre-order is currently ' . $preorder['status']]);
            $stmt->close();
            exit;
        }

        // Get total amount and discount from preorder
        $totalAmount = floatval($preorder['total_amount']);
        $discount = floatval($preorder['discount']);
        
        // Get payment history records for this preorder
        $payment_history_stmt = $conn->prepare("
            SELECT invoice_no, payment_date, amount, payment_method, payment_sequence, status_after_payment 
            FROM preorder_payment_history 
            WHERE preorder_id = ? 
            ORDER BY payment_sequence ASC, id ASC
        ");
        $payment_history_stmt->bind_param("i", $preorder_id);
        $payment_history_stmt->execute();
        $payment_history_result = $payment_history_stmt->get_result();
        
        $totalPaid = 0;
        $paymentMethod = 'N/A';
        $paymentStatus = 'unpaid';
        $payment_history_records = [];
        
        if ($payment_history_result && $payment_history_result->num_rows > 0) {
            $methods = [];
            while ($ph_row = $payment_history_result->fetch_assoc()) {
                $totalPaid += floatval($ph_row['amount'] ?? 0);
                if (!empty($ph_row['payment_method']) && !in_array($ph_row['payment_method'], $methods)) {
                    $methods[] = $ph_row['payment_method'];
                }
                $payment_history_records[] = $ph_row;
            }
            $paymentMethod = implode(', ', $methods);
            if ($totalPaid >= $totalAmount) {
                $paymentStatus = 'paid';
            }
        }
        $payment_history_stmt->close();
        
        // If no payment history found, fall back to payment_data JSON (for backward compatibility)
        if ($totalPaid == 0 && !empty($preorder['payment_data'])) {
            $payment_data = json_decode($preorder['payment_data'], true);
            
            if (!empty($payment_data)) {
                // Handle payment_partners structure
                if (isset($payment_data['payment_type']) && $payment_data['payment_type'] === 'payment_partners') {
                    // Calculate total: loan_balance + all down payments
                    $loanBalance = floatval(str_replace(',', '', $payment_data['loan_balance'] ?? $payment_data['total_loan_amount'] ?? '0'));
                    $cashDp = floatval(str_replace(',', '', $payment_data['cash_dp_amount'] ?? '0'));
                    $gcashDp = floatval(str_replace(',', '', $payment_data['gcash_dp_amount'] ?? '0'));
                    $mayaDp = floatval(str_replace(',', '', $payment_data['maya_dp_amount'] ?? '0'));
                    
                    $totalPaid = $loanBalance + $cashDp + $gcashDp + $mayaDp;
                    $paymentMethod = isset($payment_data['payment_partner']) ? $payment_data['payment_partner'] : 'Payment Partner';
                    
                    // Check if fully paid
                    if ($totalPaid >= $totalAmount) {
                        $paymentStatus = 'paid';
                    }
                }
                // Handle new payment data structure with payment_type and amount
                else if (isset($payment_data['payment_type']) && isset($payment_data['amount'])) {
                    // Remove commas before converting to float
                    $totalPaid = floatval(str_replace(',', '', $payment_data['amount']));
                    $paymentMethod = ucwords(str_replace('_', ' ', $payment_data['payment_type']));
                    
                    // Check if fully paid
                    if ($totalPaid >= $totalAmount) {
                        $paymentStatus = 'paid';
                    }
                }
                // Handle old payment data structure (fallback)
                else {
                    // Extract payment method and calculate total paid
                    if (isset($payment_data['cash']) && floatval(str_replace(',', '', $payment_data['cash'])) > 0) {
                        $totalPaid += floatval(str_replace(',', '', $payment_data['cash']));
                        $paymentMethod = 'Cash';
                    }
                    if (isset($payment_data['home_credit_amount']) && floatval(str_replace(',', '', $payment_data['home_credit_amount'])) > 0) {
                        $totalPaid += floatval(str_replace(',', '', $payment_data['home_credit_amount']));
                        $paymentMethod = 'Home Credit';
                    }
                    if (isset($payment_data['credit_card_amount']) && floatval(str_replace(',', '', $payment_data['credit_card_amount'])) > 0) {
                        $totalPaid += floatval(str_replace(',', '', $payment_data['credit_card_amount']));
                        $paymentMethod = isset($payment_data['credit_card_issuer']) ? $payment_data['credit_card_issuer'] . ' (Credit Card)' : 'Credit Card';
                    }
                    if (isset($payment_data['debit_card_amount']) && floatval(str_replace(',', '', $payment_data['debit_card_amount'])) > 0) {
                        $totalPaid += floatval(str_replace(',', '', $payment_data['debit_card_amount']));
                        $paymentMethod = 'Debit Card';
                    }
                    if (isset($payment_data['qr_ph_amount']) && floatval(str_replace(',', '', $payment_data['qr_ph_amount'])) > 0) {
                        $totalPaid += floatval(str_replace(',', '', $payment_data['qr_ph_amount']));
                        $paymentMethod = 'QR PH';
                    }
                    if (isset($payment_data['starpay_qr_amount']) && floatval(str_replace(',', '', $payment_data['starpay_qr_amount'])) > 0) {
                        $totalPaid += floatval(str_replace(',', '', $payment_data['starpay_qr_amount']));
                        $paymentMethod = 'Starpay QR';
                    }
                    if (isset($payment_data['ewallet_amount']) && floatval(str_replace(',', '', $payment_data['ewallet_amount'])) > 0) {
                        $totalPaid += floatval(str_replace(',', '', $payment_data['ewallet_amount']));
                        $paymentMethod = isset($payment_data['ewallet_provider']) ? $payment_data['ewallet_provider'] . ' (E-Wallet)' : 'E-Wallet';
                    }
                    if (isset($payment_data['online_banking_amount']) && floatval(str_replace(',', '', $payment_data['online_banking_amount'])) > 0) {
                        $totalPaid += floatval(str_replace(',', '', $payment_data['online_banking_amount']));
                        $paymentMethod = 'Online Banking';
                    }
                    
                    // Check if fully paid
                    if ($totalPaid >= $totalAmount) {
                        $paymentStatus = 'paid';
                    }
                }
            }
        }

        // Fetch preorder items
        $item_stmt = $conn->prepare("SELECT * FROM preorder_items WHERE preorder_id = ?");
        $item_stmt->bind_param("i", $preorder_id);
        $item_stmt->execute();
        $item_res = $item_stmt->get_result();

        $items = [];
        if ($item_res) {
            while ($item = $item_res->fetch_assoc()) {
                $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
                $price = isset($item['price']) ? floatval($item['price']) : 0;
                $itemTotal = $price * $quantity;
                
                $items[] = [
                    'family_code' => isset($item['family_code']) ? $item['family_code'] : (isset($item['item_description']) ? $item['item_description'] : 'N/A'),
                    'description' => isset($item['item_description']) ? $item['item_description'] : (isset($item['family_code']) ? $item['family_code'] : 'N/A'),
                    'item_code' => isset($item['item_code']) ? $item['item_code'] : null,
                    'imei' => isset($item['imei']) ? $item['imei'] : null,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total_payment' => $itemTotal,
                    'amount_paid' => $totalPaid,
                    'status' => $paymentStatus,
                    'payment_method' => $paymentMethod
                ];
            }
        }
        $item_stmt->close();

        include_once 'get_next_invoice_number.php';
        $next_invoice_no = '';
        $booklet = getBookletConfig($conn, $preorder['branch_code'], 'salesentry');
        if ($booklet) {
            $sales_invoice_no = generateInvoiceNumber($booklet);
            if ($booklet['page_type'] === 'preorder' || stristr($sales_invoice_no, '-PRE') === false) {
                $next_invoice_no = $sales_invoice_no;
            } else {
                $next_invoice_no = $sales_invoice_no;
            }
        }

        echo json_encode([
            'success' => true,
            'preorder' => [
                'preorder_no' => $preorder['invoice_no'],
                'next_invoice_no' => $next_invoice_no,
                'status' => $preorder['status'],
                'date' => $preorder['date'] ?? $preorder['created_at'] ?? date('Y-m-d'),
                'payment_history' => $payment_history_records,
                'customer' => [
                    'name' => trim($preorder['first_name'] . ' ' . $preorder['last_name']),
                    'phone' => $preorder['contact_no'],
                    'email' => $preorder['email'] ? $preorder['email'] : 'N/A',
                    'address' => $preorder['address'],
                    'remarks' => $preorder['remarks'] ?? 'N/A'
                ],
                'items' => $items
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Pre-order not found']);
    }
    $stmt->close();
    exit;
}

// Handle GET request (Searching item list by term for preorder entry dropdown)
header('Content-Type: application/json');
if (isset($_GET['term'])) {
    $term = $conn->real_escape_string($_GET['term']);
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

    $whereClause = "i.family_code LIKE '%$term%'";

    $sql = "SELECT i.id, i.family_code, i.srp, i.commission, i.has_commission, i.points, i.has_points, i.branch 
            FROM items i 
            WHERE $whereClause 
            AND i.status = 'Active' 
            GROUP BY i.family_code
            LIMIT 20";

    $result = $conn->query($sql);

    $items_data = [];
    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $item_branches = !empty($row['branch']) ? array_map('trim', explode(',', $row['branch'])) : [];
                $price_to_use = 0;
                $is_branch_allowed = false;

                if (empty($item_branches)) {
                    $is_branch_allowed = true;
                    $price_to_use = $row['srp'] ?? 0;
                }
                else {
                    if (!empty($user_branch) && in_array($user_branch, $item_branches)) {
                        $is_branch_allowed = true;
                        $price_to_use = $row['srp'] ?? 0;
                    }
                    else {
                        $is_branch_allowed = false;
                        $price_to_use = 0;
                    }
                }

                $itemId = $row['id'];
                $prices = [];
                $branch_price_filter = !empty($user_branch) ? "AND branch = '" . $conn->real_escape_string($user_branch) . "'" : "";
                $priceRes = $conn->query("SELECT price_type, price FROM item_prices WHERE item_id = '$itemId' AND is_active = 1 $branch_price_filter");
                if ($priceRes) {
                    $srp_price = 0;
                    $bdo_price = 0;
                    while ($p = $priceRes->fetch_assoc()) {
                        if ($p['price_type'] === '__SRP__' && is_numeric($p['price']) && $p['price'] > 0 && $is_branch_allowed) {
                            $srp_price = $p['price'];
                        }
                        if ($p['price_type'] === 'BDO Straight' && is_numeric($p['price']) && $p['price'] > 0 && $is_branch_allowed) {
                            $bdo_price = $p['price'];
                        }
                        $prices[$p['price_type']] = $is_branch_allowed ? $p['price'] : 0;
                    }
                    if ($srp_price > 0) {
                        $price_to_use = $srp_price;
                    }
                    elseif ($bdo_price > 0) {
                        $price_to_use = $bdo_price;
                    }
                }

                $items_data[] = [
                    'family_code' => $row['family_code'],
                    'price' => $price_to_use,
                    'commission' => $is_branch_allowed ? ($row['commission'] ?? 0) : 0,
                    'has_commission' => $row['has_commission'] ?? 0,
                    'points' => $is_branch_allowed ? ($row['points'] ?? 0) : 0,
                    'has_points' => $row['has_points'] ?? 0,
                    'prices' => $prices,
                    'branch_allowed' => $is_branch_allowed
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $items_data]);
        }
        else {
            echo json_encode(['status' => 'success', 'data' => []]);
        }
    }
    else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}
else {
    echo json_encode(['status' => 'error', 'message' => 'No search term provided']);
}
?>
