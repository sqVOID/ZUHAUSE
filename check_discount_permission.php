<?php
require_once 'session_check.php';
// Suppress all errors to prevent breaking JSON response
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';
session_start();

header('Content-Type: application/json');

$item_code = isset($_GET['item_code']) ? $_GET['item_code'] : '';

if (empty($item_code)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters', 'discount_editable' => false]);
    exit();
}

// Get item has_discount flag from item_code
$item_code_escaped = $conn->real_escape_string($item_code);

// Check if items table exists
$table_check = $conn->query("SHOW TABLES LIKE 'items'");
if (!$table_check || $table_check->num_rows == 0) {
    echo json_encode(['status' => 'success', 'discount_editable' => false, 'message' => 'Items table not found']);
    exit();
}

$item_query = @$conn->query("SELECT id, has_discount FROM items WHERE item_code = '$item_code_escaped'");

if ($item_query && $item_query->num_rows > 0) {
    $item = $item_query->fetch_assoc();
    $has_discount = isset($item['has_discount']) ? $item['has_discount'] : 0;

    // Discount is editable based solely on the item's has_discount flag
    if ($has_discount == 1) {
        echo json_encode(['status' => 'success', 'discount_editable' => true]);
    }
    else {
        echo json_encode(['status' => 'success', 'discount_editable' => false, 'reason' => 'Item has no discount enabled']);
    }
}
else {
    // Item not found - default to no discount
    echo json_encode(['status' => 'success', 'discount_editable' => false, 'message' => 'Item not found']);
}

if (isset($conn)) {
    $conn->close();
}
?>

