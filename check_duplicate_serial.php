<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
$serial_number = isset($_POST['serial_number']) ? trim($_POST['serial_number']) : '';

if (empty($serial_number)) {
    echo json_encode(['success' => false, 'message' => 'Serial number is empty.']);
    exit;
}

$serial_esc = $conn->real_escape_string($serial_number);

// Check if serial number exists in stock_on_hand (checking both imei and imei2 if column exists)
$check_imei2_col = $conn->query("SHOW COLUMNS FROM stock_on_hand LIKE 'imei2'");
$has_imei2_col = ($check_imei2_col && $check_imei2_col->num_rows > 0);
$stock_where = "imei = '{$serial_esc}'" . ($has_imei2_col ? " OR imei2 = '{$serial_esc}'" : "");
$check_stock = $conn->query("SELECT id, dr_number FROM stock_on_hand WHERE {$stock_where} LIMIT 1");
if ($check_stock && $check_stock->num_rows > 0) {
    $row = $check_stock->fetch_assoc();
    
    // Check what the current PO number is
    $check_po = $conn->query("SELECT po_number FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
    $po_number = '';
    if ($check_po && $check_po->num_rows > 0) {
        $po_number = $check_po->fetch_assoc()['po_number'];
    }
    
    if (strcasecmp($row['dr_number'], $po_number) !== 0) {
        echo json_encode(['success' => true, 'is_duplicate' => true, 'message' => "Serial number already exists in stock (PO: {$row['dr_number']})."]);
        exit;
    }
}

// Check if serial number exists in purchase_order_items (checking both serial_number and imei_2)
$check_poi = $conn->query("SELECT po.po_number, poi.po_id, poi.serial_number, poi.imei_2 
                           FROM purchase_order_items poi 
                           JOIN purchase_orders po ON poi.po_id = po.id
                           WHERE poi.serial_number LIKE '%{$serial_esc}%' OR poi.imei_2 LIKE '%{$serial_esc}%'");

if ($check_poi && $check_poi->num_rows > 0) {
    while ($row = $check_poi->fetch_assoc()) {
        $serials1 = !empty($row['serial_number']) ? preg_split('/[\n,]+/', $row['serial_number']) : [];
        $serials2 = !empty($row['imei_2']) ? preg_split('/[\n,]+/', $row['imei_2']) : [];
        $serials = array_merge(array_map('trim', $serials1), array_map('trim', $serials2));
        if (in_array($serial_number, $serials) && (int)$row['po_id'] !== $po_id) {
            echo json_encode(['success' => true, 'is_duplicate' => true, 'message' => "Serial number is already assigned to PO: {$row['po_number']}."]);
            exit;
        }
    }
}

echo json_encode(['success' => true, 'is_duplicate' => false]);
?>
