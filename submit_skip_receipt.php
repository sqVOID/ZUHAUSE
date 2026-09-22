<?php
require_once 'session_check.php';
include 'config.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get POST data
$invoice_no = isset($_POST['invoice_no']) ? trim($_POST['invoice_no']) : '';
$branch_code = isset($_POST['branch_code']) ? trim($_POST['branch_code']) : '';
$branch_name = isset($_POST['branch_name']) ? trim($_POST['branch_name']) : '';
$requested_by = isset($_POST['requested_by']) ? trim($_POST['requested_by']) : '';
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

// Validate input
if (empty($invoice_no)) {
    echo json_encode(['success' => false, 'message' => 'Invoice number is required']);
    exit;
}

if (empty($branch_code)) {
    echo json_encode(['success' => false, 'message' => 'Branch code is required']);
    exit;
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Reason is required']);
    exit;
}

// Check if this invoice already has a pending or approved skip request
$check_stmt = $conn->prepare("SELECT id, status FROM skip_receipt_requests WHERE invoice_no = ? AND status IN ('Pending', 'Approved')");
$check_stmt->bind_param("s", $invoice_no);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $existing = $check_result->fetch_assoc();
    echo json_encode([
        'success' => false, 
        'message' => 'A ' . strtolower($existing['status']) . ' skip receipt request already exists for this invoice number.'
    ]);
    $check_stmt->close();
    exit;
}
$check_stmt->close();

// Generate unique request ID
$year = date('Y');
$request_id_prefix = 'SR-' . $year . '-';

// Get the last request ID for this year
$last_id_query = $conn->query("SELECT request_id FROM skip_receipt_requests WHERE request_id LIKE '$request_id_prefix%' ORDER BY id DESC LIMIT 1");

if ($last_id_query && $last_id_query->num_rows > 0) {
    $last_request = $last_id_query->fetch_assoc();
    $last_number = intval(substr($last_request['request_id'], strlen($request_id_prefix)));
    $new_number = $last_number + 1;
} else {
    $new_number = 1;
}

$request_id = $request_id_prefix . str_pad($new_number, 5, '0', STR_PAD_LEFT);

// Get current user's username for tracking
$requested_by_user = $_SESSION['username'];

// Insert skip receipt request
$stmt = $conn->prepare("INSERT INTO skip_receipt_requests (request_id, invoice_no, branch_code, branch_name, requested_by, requested_by_user, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
$stmt->bind_param("sssssss", $request_id, $invoice_no, $branch_code, $branch_name, $requested_by, $requested_by_user, $reason);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Skip receipt request submitted successfully',
        'request_id' => $request_id
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to submit request: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>
