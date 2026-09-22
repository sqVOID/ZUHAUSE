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
        dr_number
    FROM sales_entry_items 
    WHERE sales_entry_id = ?
    ORDER BY id ASC
");
$items_query->bind_param("i", $sales_entry_id);
$items_query->execute();
$items_result = $items_query->get_result();

$items = [];
while ($item = $items_result->fetch_assoc()) {
    $prices = [];
    $others_bank_enabled = false;

    if (!empty($item['item_code'])) {
        $item_code_escaped = $conn->real_escape_string($item['item_code']);
        $item_res = $conn->query("SELECT id, srp FROM items WHERE item_code = '$item_code_escaped' LIMIT 1");
        if ($item_res && $item_res->num_rows > 0) {
            $item_row = $item_res->fetch_assoc();
            $itemId = $item_row['id'];
            $item_srp = floatval($item_row['srp'] ?? 0);
            $item['srp'] = $item_srp;
            $item['base_price'] = floatval($item['price']) > 0 ? floatval($item['price']) : ($item_srp > 0 ? $item_srp : floatval($item['price']));
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
        'discount' => $sales_entry['discount'],
        'voucher_amount' => $sales_entry['voucher_amount'] ?? 0,
        'total_amount' => $sales_entry['total_amount'],
        'points' => $sales_entry['points'],
        'commission' => $sales_entry['commission'],
        'payment_data' => $sales_entry['payment_data'],
        'cash_payments' => $sales_entry['cash_payments'],
        'card_bank_type' => $sales_entry['card_bank_type'],
        'status' => $sales_entry['status'],
        'branch_code' => $sales_entry['branch_code'],
        'branch_name' => $branch_name,
        'encoder' => $sales_entry['encoder'],
        'created_at' => $sales_entry['created_at'],
        'items' => $items,
        'freebies' => $freebies
    ]
];

echo json_encode($response);

$conn->close();
if (ob_get_length()) ob_end_flush();
?>
