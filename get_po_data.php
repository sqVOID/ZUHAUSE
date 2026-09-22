<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

$po_number = isset($_GET['po_number']) ? trim($_GET['po_number']) : '';

if (empty($po_number)) {
    echo json_encode(['success' => false, 'message' => 'PO Number is required']);
    exit;
}

$po_esc = $conn->real_escape_string($po_number);
$po_query = $conn->query("SELECT id, supplier_company FROM purchase_orders WHERE po_number = '$po_esc' LIMIT 1");

if (!$po_query || $po_query->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'PO not found']);
    exit;
}

$po = $po_query->fetch_assoc();
$po_id = $po['id'];

$items = [];
$items_query = $conn->query("SELECT item_model, item_description, quantity, serial_number FROM purchase_order_items WHERE po_id = $po_id ORDER BY item_no ASC");

if ($items_query) {
    while ($row = $items_query->fetch_assoc()) {
        $items[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'supplier_company' => $po['supplier_company'],
    'items' => $items
]);
?>
