<?php
require_once 'session_check.php';
// Suppress all errors to prevent breaking JSON response
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';
session_start();

header('Content-Type: application/json');

$item_code = isset($_GET['item_code']) ? $_GET['item_code'] : '';
$family_code = isset($_GET['family_code']) ? $_GET['family_code'] : '';

if (empty($item_code) && empty($family_code)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing item code or family code', 'has_serial' => false]);
    exit();
}

// Check if items table and has_serial column exist

$table_check = $conn->query("SHOW TABLES LIKE 'items'");
if (!$table_check || $table_check->num_rows == 0) {
    echo json_encode(['status' => 'success', 'has_serial' => false, 'message' => 'Items table not found']);
    exit();
}

// Build query based on whether we have item_code or family_code
if (!empty($item_code)) {
    $item_code_escaped = $conn->real_escape_string($item_code);
    $item_query = @$conn->query("SELECT id, department, has_serial, has_serial_2, COALESCE(has_serial_number, 0) as has_serial_number FROM items WHERE item_code = '$item_code_escaped' LIMIT 1");
} else {
    $family_code_escaped = $conn->real_escape_string($family_code);
    $item_query = @$conn->query("SELECT id, department, has_serial, has_serial_2, COALESCE(has_serial_number, 0) as has_serial_number FROM items WHERE family_code = '$family_code_escaped' LIMIT 1");
}

if ($item_query && $item_query->num_rows > 0) {
    $item = $item_query->fetch_assoc();
    $has_serial = isset($item['has_serial']) ? $item['has_serial'] : 0;
    $has_serial_2 = isset($item['has_serial_2']) ? $item['has_serial_2'] : 0;
    $has_serial_number = isset($item['has_serial_number']) ? $item['has_serial_number'] : 0;
    $department = isset($item['department']) ? $item['department'] : '';

    // Return whether item is serialized and its serial configurations
    echo json_encode([
        'status' => 'success',
        'has_serial' => (bool) $has_serial,
        'has_serial_2' => (bool) $has_serial_2,
        'has_serial_number' => (bool) $has_serial_number,
        'department' => $department
    ]);
} else {
    // Item not found - default to no serial requirement
    echo json_encode(['status' => 'success', 'has_serial' => false, 'has_serial_2' => false, 'has_serial_number' => false, 'department' => '', 'message' => 'Item not found']);
}

if (isset($conn)) {
    $conn->close();
}
?>