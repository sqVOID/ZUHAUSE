<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

// Validate POST data
if (!isset($_POST['po_id']) || !isset($_POST['invoice_number'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

$po_id = (int) $_POST['po_id'];
$invoice_number = trim($_POST['invoice_number']);

if ($po_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
    exit;
}

// Fetch the PO to check status and permissions
$po_query = $conn->query("SELECT status, created_by_branch FROM purchase_orders WHERE id = $po_id LIMIT 1");
if (!$po_query || $po_query->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Purchase order not found.']);
    exit;
}

$po = $po_query->fetch_assoc();
$status = $po['status'] ?? 'Pending';

// Prevent editing if status is Received, Completed or Cancelled
if (strcasecmp($status, 'Received') === 0 || strcasecmp($status, 'Completed') === 0 || strcasecmp($status, 'CANCELED') === 0 || strcasecmp($status, 'Cancelled') === 0) {
    echo json_encode(['success' => false, 'message' => 'Cannot update invoice number for Received, Completed or Cancelled purchase orders.']);
    exit;
}

// Branch access check
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

// Only Super-Admin can update all purchase orders
if (strcasecmp($system_level, 'Super-Admin') !== 0) {
    $po_branch_code = $po['created_by_branch'] ?? '';

    // Handle multiple branches (comma-separated)
    $branch_names = array_map('trim', explode(',', $user_branch));
    $user_branch_codes = [];
    
    foreach ($branch_names as $branch_name) {
        if (!empty($branch_name)) {
            $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($branch_name) . "' LIMIT 1");
            if ($branch_query && $branch_query->num_rows > 0) {
                $user_branch_codes[] = $branch_query->fetch_assoc()['branch_code'];
            }
        }
    }

    // Check if PO branch is in user's allowed branches
    $access_granted = false;
    foreach ($user_branch_codes as $allowed_code) {
        if (strcasecmp($po_branch_code, $allowed_code) === 0) {
            $access_granted = true;
            break;
        }
    }
    
    if (!$access_granted) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this purchase order.']);
        exit;
    }
}

// Escape the invoice number for SQL
$invoice_number_escaped = $conn->real_escape_string($invoice_number);

// Update the purchase order
$update_sql = "UPDATE purchase_orders SET invoice_number = '$invoice_number_escaped' WHERE id = $po_id";

if ($conn->query($update_sql)) {
    echo json_encode(['success' => true, 'message' => 'Invoice number updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>
