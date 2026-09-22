<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

// Get POST data
$po_id = isset($_POST['po_id']) ? (int) $_POST['po_id'] : 0;
$receiving_remarks = isset($_POST['receiving_remarks']) ? trim($_POST['receiving_remarks']) : '';
$receiving_branch = isset($_POST['receiving_branch']) ? trim($_POST['receiving_branch']) : ''; // Added branch parameter

// Validate PO ID
if ($po_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Purchase Order ID.']);
    exit;
}

// Check if PO exists
$check_sql = "SELECT id FROM purchase_orders WHERE id = $po_id LIMIT 1";
$check_result = $conn->query($check_sql);

if (!$check_result || $check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Purchase Order not found.']);
    exit;
}

// CRITICAL FIX: If receiving_branch is provided, save to purchase_order_allocations (per-branch remarks)
// Otherwise, save to purchase_orders (global remarks)
if (!empty($receiving_branch)) {
    // Save per-branch remarks to purchase_order_allocations
    $receiving_branch_escaped = $conn->real_escape_string($receiving_branch);
    $remarks_escaped = $conn->real_escape_string($receiving_remarks);
    
    $update_sql = "UPDATE purchase_order_allocations 
                   SET receiving_remarks = '$remarks_escaped'
                   WHERE po_id = $po_id 
                   AND branch_name = '$receiving_branch_escaped'";
    
    if ($conn->query($update_sql)) {
        if ($conn->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Receiving remarks updated successfully for ' . $receiving_branch,
                'receiving_remarks' => $receiving_remarks,
                'branch' => $receiving_branch
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No allocation found for this branch or remarks unchanged.'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $conn->error
        ]);
    }
} else {
    // Save global remarks to purchase_orders table (backward compatibility)
    $update_sql = "UPDATE purchase_orders 
                   SET receiving_remarks = '" . $conn->real_escape_string($receiving_remarks) . "' 
                   WHERE id = $po_id";

    if ($conn->query($update_sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Receiving remarks updated successfully.',
            'receiving_remarks' => $receiving_remarks
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $conn->error
        ]);
    }
}

$conn->close();
?>
