<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get POST data
$po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
$cancel_reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';

// Validate inputs
if ($po_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
    exit;
}

if (empty($cancel_reason)) {
    echo json_encode(['success' => false, 'message' => 'Cancellation reason is required.']);
    exit;
}

// Get current user information
$canceled_by = '';
if (!empty($_SESSION['fullname'])) {
    $canceled_by = $_SESSION['fullname'];
} elseif (!empty($_SESSION['username'])) {
    $canceled_by = $_SESSION['username'];
} else {
    $canceled_by = 'Unknown User';
}

// Get user branch
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$canceled_by_branch = '';

if ($system_level === 'Super-Admin') {
    $canceled_by_branch = 'ALL';
} elseif (!empty($user_branch)) {
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($user_branch) . "' LIMIT 1");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_result = $branch_query->fetch_assoc();
        $canceled_by_branch = $branch_result['branch_code'];
    }
}

// Check if PO exists
$po_check = $conn->query("SELECT po_number, status FROM purchase_orders WHERE id = $po_id LIMIT 1");
if (!$po_check || $po_check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Purchase Order not found.']);
    exit;
}

$po_data = $po_check->fetch_assoc();
$po_number = $po_data['po_number'];
$current_status = strtolower($po_data['status']);

// Check if PO can be canceled (not already completed or incomplete)
if ($current_status === 'received' || $current_status === 'incomplete') {
    echo json_encode(['success' => false, 'message' => 'Cannot cancel a purchase order that has been received or is incomplete.']);
    exit;
}

if ($current_status === 'canceled' || $current_status === 'cancelled') {
    echo json_encode(['success' => false, 'message' => 'This purchase order is already canceled.']);
    exit;
}

// Check if canceled_by column exists, if not add it
$check_canceled_by = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'canceled_by'");
if (!$check_canceled_by || $check_canceled_by->num_rows === 0) {
    $conn->query("ALTER TABLE purchase_orders ADD COLUMN canceled_by VARCHAR(150) AFTER status");
}

// Check if canceled_at column exists, if not add it
$check_canceled_at = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'canceled_at'");
if (!$check_canceled_at || $check_canceled_at->num_rows === 0) {
    $conn->query("ALTER TABLE purchase_orders ADD COLUMN canceled_at TIMESTAMP NULL AFTER canceled_by");
}

// Check if canceled_by_branch column exists, if not add it
$check_canceled_by_branch = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'canceled_by_branch'");
if (!$check_canceled_by_branch || $check_canceled_by_branch->num_rows === 0) {
    $conn->query("ALTER TABLE purchase_orders ADD COLUMN canceled_by_branch VARCHAR(50) AFTER canceled_at");
}

// Check if cancel_reason column exists, if not add it
$check_cancel_reason = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'cancel_reason'");
if (!$check_cancel_reason || $check_cancel_reason->num_rows === 0) {
    $conn->query("ALTER TABLE purchase_orders ADD COLUMN cancel_reason TEXT AFTER canceled_by_branch");
}

// Escape data
$canceled_by_esc = $conn->real_escape_string($canceled_by);
$canceled_by_branch_esc = $conn->real_escape_string($canceled_by_branch);
$cancel_reason_esc = $conn->real_escape_string($cancel_reason);

// Update PO status to Canceled
$update_sql = "UPDATE purchase_orders 
               SET status = 'Canceled',
                   canceled_by = '$canceled_by_esc',
                   canceled_at = NOW(),
                   canceled_by_branch = '$canceled_by_branch_esc',
                   cancel_reason = '$cancel_reason_esc'
               WHERE id = $po_id";

if ($conn->query($update_sql)) {
    // Log the cancellation in workflow or audit table if exists
    error_log("PO $po_number (ID: $po_id) was canceled by $canceled_by. Reason: $cancel_reason");
    
    echo json_encode([
        'success' => true, 
        'message' => "Purchase Order $po_number has been canceled successfully."
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to cancel purchase order: ' . $conn->error
    ]);
}

$conn->close();
?>
