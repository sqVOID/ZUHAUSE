<?php
require_once 'session_check.php';
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_clean();
header('Content-Type: application/json');

$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS voucher_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER is_promo_item");
$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS token_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER voucher_amount");
$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER token_amount");

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data received']);
    exit;
}

$invoice_no = isset($data['invoice_no']) ? trim($data['invoice_no']) : '';
$date = isset($data['date']) ? trim($data['date']) : '';

if (empty($invoice_no) && empty($date)) {
    echo json_encode(['status' => 'error', 'message' => 'Invoice number or date is required']);
    exit;
}

// Build query based on search criteria
$where_conditions = [];
$params = [];
$types = '';

if (!empty($invoice_no)) {
    $where_conditions[] = "invoice_no = ?";
    $params[] = $invoice_no;
    $types .= 's';
}

if (!empty($date)) {
    $where_conditions[] = "DATE(created_at) = ?";
    $params[] = $date;
    $types .= 's';
}

$where_clause = implode(' AND ', $where_conditions);

// Fetch sales entry
$query = "SELECT * FROM sales_entry WHERE $where_clause LIMIT 1";
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Sales entry not found']);
    exit;
}

$sales_entry = $result->fetch_assoc();
$sales_entry_id = $sales_entry['id'];

// Get branch name from branch_code
$branch_name = '';
if (!empty($sales_entry['branch_code'])) {
    $branch_query = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = ?");
    $branch_query->bind_param("s", $sales_entry['branch_code']);
    $branch_query->execute();
    $branch_result = $branch_query->get_result();
    if ($branch_result->num_rows > 0) {
        $branch_data = $branch_result->fetch_assoc();
        $branch_name = $branch_data['branch_name'];
    }
    $branch_query->close();
}

// Fetch items for this sales entry
$items_query = $conn->prepare("
    SELECT 
        id,
        item_description,
        imei,
        quantity,
        price,
        item_code,
        dr_number,
        voucher_amount,
        token_amount,
        discount_amount
    FROM sales_entry_items 
    WHERE sales_entry_id = ?
    ORDER BY id ASC
");
$items_query->bind_param("i", $sales_entry_id);
$items_query->execute();
$items_result = $items_query->get_result();

$items = [];
$total_token = 0;
$total_voucher = 0;
$total_discount = 0;
while ($item = $items_result->fetch_assoc()) {
    $prices = [];
    $others_bank_enabled = false;
    
    // Add token/voucher/discount amounts to totals
    $token_amt = floatval($item['token_amount'] ?? 0);
    $voucher_amt = floatval($item['voucher_amount'] ?? 0);
    $discount_amt = floatval($item['discount_amount'] ?? 0);
    $total_token += $token_amt;
    $total_voucher += $voucher_amt;
    $total_discount += $discount_amt;

    // Defaults from item registration
    $item['has_discount'] = 0;
    $item['has_voucher'] = 0;
    $item['has_token'] = 0;
    $item['reg_voucher_amount'] = 0;
    $item['reg_token_amount'] = 0;

    if (!empty($item['item_code'])) {
        $item_code_escaped = $conn->real_escape_string($item['item_code']);
        $item_res = $conn->query("SELECT id, srp, has_discount, has_voucher, voucher_amount AS reg_voucher_amount, has_token, token_amount AS reg_token_amount FROM items WHERE item_code = '$item_code_escaped' LIMIT 1");
        if ($item_res && $item_res->num_rows > 0) {
            $item_row = $item_res->fetch_assoc();
            $itemId = $item_row['id'];
            $item_srp = floatval($item_row['srp'] ?? 0);
            $item['srp'] = $item_srp;
            $item['base_price'] = floatval($item['price']) > 0 ? floatval($item['price']) : ($item_srp > 0 ? $item_srp : floatval($item['price']));
            $item['has_discount'] = intval($item_row['has_discount'] ?? 0);
            $item['has_voucher'] = intval($item_row['has_voucher'] ?? 0);
            $item['has_token'] = intval($item_row['has_token'] ?? 0);
            $item['reg_voucher_amount'] = floatval($item_row['reg_voucher_amount'] ?? 0);
            $item['reg_token_amount'] = floatval($item_row['reg_token_amount'] ?? 0);
            $user_branch = !empty($branch_name) ? $branch_name : (isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '');
            $branch_price_filter = !empty($user_branch) ? "AND branch = '" . $conn->real_escape_string($user_branch) . "'" : "";
            $priceRes = $conn->query("SELECT price_type, price FROM item_prices WHERE item_id = '$itemId' AND is_active = 1 $branch_price_filter");
            if ($priceRes && $priceRes->num_rows > 0) {
                while ($p = $priceRes->fetch_assoc()) {
                    if ($p['price_type'] === 'Others Bank') {
                        $others_bank_enabled = true;
                    }
                    $prices[$p['price_type']] = $p['price'];
                }
            }
        }
    }
    if (!isset($item['base_price'])) {
        $item['base_price'] = floatval($item['price']);
    }
    $item['prices'] = $prices;
    $item['others_bank_enabled'] = $others_bank_enabled;
    $items[] = $item;
}

// Fetch freebies if any
$freebies_query = $conn->prepare("
    SELECT 
        id,
        freebie_description,
        quantity
    FROM sales_entry_freebies 
    WHERE sales_entry_id = ?
    ORDER BY id ASC
");
$freebies_query->bind_param("i", $sales_entry_id);
$freebies_query->execute();
$freebies_result = $freebies_query->get_result();

$freebies = [];
while ($freebie = $freebies_result->fetch_assoc()) {
    $freebies[] = $freebie;
}

// Enrich payment_data with preorder / preorder2 / claim stages from payment history
// (report.php invoiceModal is accurate because it uses per-invoice history; modification must match)
$payment_history = [];
$page_type = strtolower(trim($sales_entry['page_type'] ?? ''));
$orig_inv = trim($sales_entry['original_invoice_no'] ?? '');
$claim_inv = trim($sales_entry['invoice_no'] ?? '');
$enriched_payment_data = $sales_entry['payment_data'];

$preorder_id = null;
$po_find = $conn->prepare("
    SELECT id FROM preorders
    WHERE invoice_no = ? OR invoice_no = ? OR claimed_invoice_no = ?
    ORDER BY id DESC
    LIMIT 1
");
if ($po_find) {
    $po_find->bind_param("sss", $orig_inv, $claim_inv, $claim_inv);
    $po_find->execute();
    $po_res = $po_find->get_result();
    if ($po_res && $po_row = $po_res->fetch_assoc()) {
        $preorder_id = intval($po_row['id']);
    }
    $po_find->close();
}

if (!$preorder_id && ($orig_inv !== '' || $claim_inv !== '')) {
    $ph_find = $conn->prepare("
        SELECT preorder_id FROM preorder_payment_history
        WHERE invoice_no = ? OR invoice_no = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    if ($ph_find) {
        $ph_find->bind_param("ss", $orig_inv, $claim_inv);
        $ph_find->execute();
        $ph_res = $ph_find->get_result();
        if ($ph_res && $ph_row = $ph_res->fetch_assoc()) {
            $preorder_id = intval($ph_row['preorder_id']);
        }
        $ph_find->close();
    }
}

if ($preorder_id > 0) {
    $ph_stmt = $conn->prepare("
        SELECT id, invoice_no, payment_date, payment_type, payment_method, amount,
               payment_data, payment_sequence, status_after_payment
        FROM preorder_payment_history
        WHERE preorder_id = ?
        ORDER BY payment_sequence ASC, id ASC
    ");
    if ($ph_stmt) {
        $ph_stmt->bind_param("i", $preorder_id);
        $ph_stmt->execute();
        $ph_result = $ph_stmt->get_result();
        $history_payments = [];
        while ($ph = $ph_result->fetch_assoc()) {
            $payment_history[] = $ph;
            $stage_pd = json_decode($ph['payment_data'] ?? '', true);
            if (!is_array($stage_pd)) {
                $stage_pd = [
                    'payment_type' => $ph['payment_type'] ?? 'cash',
                    'amount' => $ph['amount'] ?? 0
                ];
            }
            // Expand nested multiple if a history row somehow wraps multiple
            $payment_date_raw = $ph['payment_date'] ?? '';
            $payment_date_fmt = '';
            if (!empty($payment_date_raw) && $payment_date_raw !== '0000-00-00 00:00:00') {
                $ts = strtotime($payment_date_raw);
                $payment_date_fmt = $ts ? date('Y-m-d', $ts) : substr($payment_date_raw, 0, 10);
            }
            if (isset($stage_pd['payment_type']) && $stage_pd['payment_type'] === 'multiple' && !empty($stage_pd['payments'])) {
                foreach ((array) $stage_pd['payments'] as $sub) {
                    if (!is_array($sub)) continue;
                    $sub['block_invoice_no'] = $ph['invoice_no'];
                    $sub['block_payment_date'] = $payment_date_fmt;
                    $sub['payment_sequence'] = intval($ph['payment_sequence'] ?? 0);
                    $sub['is_preorder_stage'] = true;
                    $history_payments[] = $sub;
                }
            } else {
                $stage_pd['block_invoice_no'] = $ph['invoice_no'];
                $stage_pd['block_payment_date'] = $payment_date_fmt;
                $stage_pd['payment_sequence'] = intval($ph['payment_sequence'] ?? 0);
                $stage_pd['is_preorder_stage'] = true;
                $history_payments[] = $stage_pd;
            }
        }
        $ph_stmt->close();

        if (count($history_payments) > 0) {
            // Prefer history when it has more stages than sales_entry.payment_data
            // (covers preorder + preorder2 + claim)
            $existing_pd = json_decode($sales_entry['payment_data'] ?? '', true);
            $existing_count = 0;
            if (is_array($existing_pd)) {
                if (($existing_pd['payment_type'] ?? '') === 'multiple' && !empty($existing_pd['payments'])) {
                    $existing_count = count($existing_pd['payments']);
                } elseif (!empty($existing_pd)) {
                    $existing_count = 1;
                }
            }

            if (count($history_payments) >= 2 && count($history_payments) >= $existing_count) {
                $enriched_payment_data = json_encode([
                    'payment_type' => 'multiple',
                    'payments' => $history_payments
                ]);
            } elseif ($existing_count > 0 && is_array($existing_pd)) {
                // Stamp missing block_invoice_no from history by sequence/index
                $payments = (($existing_pd['payment_type'] ?? '') === 'multiple')
                    ? (array) ($existing_pd['payments'] ?? [])
                    : [$existing_pd];
                foreach ($payments as $i => &$p) {
                    if (!is_array($p)) continue;
                    if (empty($p['block_invoice_no']) && isset($payment_history[$i]['invoice_no'])) {
                        $p['block_invoice_no'] = $payment_history[$i]['invoice_no'];
                        $p['payment_sequence'] = intval($payment_history[$i]['payment_sequence'] ?? ($i + 1));
                    }
                    if (empty($p['block_payment_date']) && !empty($payment_history[$i]['payment_date'])) {
                        $ts = strtotime($payment_history[$i]['payment_date']);
                        $p['block_payment_date'] = $ts ? date('Y-m-d', $ts) : substr($payment_history[$i]['payment_date'], 0, 10);
                    }
                }
                unset($p);
                if (count($payments) > 1) {
                    $enriched_payment_data = json_encode([
                        'payment_type' => 'multiple',
                        'payments' => $payments
                    ]);
                } else {
                    $enriched_payment_data = json_encode($payments[0] ?? $existing_pd);
                }
            }
        }
    }
}

// Prepare response
$response = [
    'status' => 'success',
    'message' => 'Sales entry found',
    'data' => [
        'id' => $sales_entry['id'],
        'invoice_no' => $sales_entry['invoice_no'],
        'first_name' => $sales_entry['first_name'],
        'last_name' => $sales_entry['last_name'],
        'address' => $sales_entry['address'],
        'contact_no' => $sales_entry['contact_no'],
        'email' => $sales_entry['email'],
        'assisted_by' => $sales_entry['assisted_by'],
        'original_invoice_no' => $sales_entry['original_invoice_no'] ?? '',
        'remarks' => $sales_entry['remarks'],
        'reason_to_modify' => $sales_entry['reason_to_modify'],
        'applied_promo'      => $sales_entry['promo_id'] ?? null,
        'promo_usage_number' => isset($sales_entry['promo_usage_number']) ? (string)$sales_entry['promo_usage_number'] : null,

        'total_qty' => $sales_entry['total_qty'],
        'discount' => ($total_discount > 0) ? $total_discount : ($sales_entry['discount'] ?? 0),
        'voucher_amount' => ($total_voucher > 0) ? $total_voucher : ($sales_entry['voucher_amount'] ?? 0),
        'token' => $total_token,
        'total_amount' => $sales_entry['total_amount'],
        'points' => $sales_entry['points'],
        'commission' => $sales_entry['commission'],
        'payment_data' => $enriched_payment_data,
        'payment_history' => $payment_history,
        'preorder_id' => $preorder_id,
        'cash_payments' => $sales_entry['cash_payments'],
        'card_bank_type' => $sales_entry['card_bank_type'],
        'status' => $sales_entry['status'],
        'branch_code' => $sales_entry['branch_code'],
        'branch_name' => $branch_name,
        'encoder' => $sales_entry['encoder'],
        'created_at' => $sales_entry['created_at'],
        
        // Trade-In Details
        'tradein_value' => $sales_entry['tradein_value'] ?? 0,
        'tradein_imei' => $sales_entry['tradein_imei'] ?? '',
        'tradein_item_code' => $sales_entry['tradein_item_code'] ?? '',
        'tradein_brand' => $sales_entry['tradein_brand'] ?? '',
        'titu_control' => $sales_entry['titu_control'] ?? '',
        'titu_token' => $sales_entry['titu_token'] ?? '',
        'titu_voucher_total' => $sales_entry['titu_voucher_total'] ?? 0,
        'cross_sell' => $sales_entry['cross_sell'] ?? 0,
        'trade_in_voucher' => $sales_entry['trade_in_voucher'] ?? 0,
        'page_type' => $sales_entry['page_type'] ?? '',
        
        'items' => $items,
        'freebies' => $freebies
    ]
];

echo json_encode($response);

$conn->close();
if (ob_get_length()) ob_end_flush();
?>
