<?php
require_once 'session_check.php';
// Suppress all errors to prevent breaking JSON response
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['item_code'])) {
    echo json_encode(['status' => 'error', 'message' => 'Item code not provided']);
    exit;
}

$item_code = $conn->real_escape_string($_GET['item_code']);

$result = @$conn->query(
    "SELECT commission, has_commission, tc_commission, points, has_points, has_voucher, voucher_amount, has_token, token_amount
     FROM items
     WHERE item_code = '$item_code'
       AND status = 'Active'
     LIMIT 1"
);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'status' => 'success',
        'commission' => $row['commission'] ?? 0,
        'has_commission' => $row['has_commission'] ?? 0,
        'tc_commission' => $row['tc_commission'] ?? 0,
        'points' => $row['points'] ?? 0,
        'has_points' => $row['has_points'] ?? 0,
        'has_voucher' => $row['has_voucher'] ?? 0,
        'voucher_amount' => $row['voucher_amount'] ?? 0,
        'has_token' => $row['has_token'] ?? 0,
        'token_amount' => $row['token_amount'] ?? 0,
    ]);
}
else {
    echo json_encode([
        'status' => 'success',
        'commission' => 0,
        'has_commission' => 0,
        'tc_commission' => 0,
        'points' => 0,
        'has_points' => 0,
        'has_voucher' => 0,
        'voucher_amount' => 0,
        'has_token' => 0,
        'token_amount' => 0,
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>
