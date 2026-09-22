<?php
// Suppress all output before JSON response
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once 'session_check.php';
include 'config.php';

// Clear any output that might have been generated
ob_end_clean();

header('Content-Type: application/json');

// Get POST data
$po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
$family_code = isset($_POST['family_code']) ? trim($_POST['family_code']) : '';
$item_no = isset($_POST['item_no']) ? (int)$_POST['item_no'] : 0;
$item_model = isset($_POST['item_model']) ? trim($_POST['item_model']) : '';
$item_description = isset($_POST['item_description']) ? trim($_POST['item_description']) : '';
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : null;
$received_qty = isset($_POST['received_qty']) ? (int)$_POST['received_qty'] : null;
$receiving_branch = isset($_POST['receiving_branch']) ? trim($_POST['receiving_branch']) : ''; // Get the specific branch

if ($po_id <= 0 || empty($family_code) || $item_no <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Verify the PO exists
$po_query = $conn->query("SELECT * FROM purchase_orders WHERE id = $po_id LIMIT 1");
if (!$po_query || $po_query->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Purchase Order not found']);
    exit;
}

// Escape values
$family_code_escaped = $conn->real_escape_string($family_code);
$item_model_escaped = $conn->real_escape_string($item_model);
$item_description_escaped = $conn->real_escape_string($item_description);

// CRITICAL FIX: Check if this PO has allocations
$check_allocations = $conn->query("SELECT COUNT(*) as alloc_count FROM purchase_order_allocations WHERE po_id = $po_id");
$has_allocations = false;
if ($check_allocations) {
    $alloc_row = $check_allocations->fetch_assoc();
    $has_allocations = $alloc_row['alloc_count'] > 0;
}

// If PO has allocations, we MUST have a receiving_branch to know which branch to update
if ($has_allocations && empty($receiving_branch)) {
    echo json_encode(['success' => false, 'message' => 'ERROR: Cannot update item model for allocated PO without specifying the receiving branch. Please refresh the page and try again.']);
    exit;
}

if ($has_allocations && !empty($receiving_branch)) {
    $receiving_branch_escaped = $conn->real_escape_string($receiving_branch);
    
    // Build the allocation update SQL with quantity and received_qty if provided
    if ($quantity !== null && $quantity > 0) {
        // When quantity is provided, update it in the allocation as well
        // CRITICAL FIX: Also update received_qty in allocations table
        $received_qty_alloc_clause = ($received_qty !== null) ? ", received_qty = $received_qty" : "";
        
        $update_alloc_sql = "UPDATE purchase_order_allocations 
                            SET item_model = '$item_model_escaped', 
                                item_description = '$item_description_escaped',
                                quantity = $quantity
                                $received_qty_alloc_clause
                            WHERE po_id = $po_id 
                            AND family_code = '$family_code_escaped'
                            AND branch_name = '$receiving_branch_escaped'
                            LIMIT 1";
    } else {
        // Only update item_model, item_description, and received_qty (if provided)
        // CRITICAL FIX: Also update received_qty in allocations table
        $received_qty_alloc_clause = ($received_qty !== null) ? ", received_qty = $received_qty" : "";
        
        $update_alloc_sql = "UPDATE purchase_order_allocations 
                            SET item_model = '$item_model_escaped', 
                                item_description = '$item_description_escaped'
                                $received_qty_alloc_clause
                            WHERE po_id = $po_id 
                            AND family_code = '$family_code_escaped'
                            AND branch_name = '$receiving_branch_escaped'
                            LIMIT 1";
    }
    $conn->query($update_alloc_sql);
}

// ALWAYS update the specific row in purchase_order_items table
if ($quantity !== null && $quantity > 0) {
    // Get the current cost to recalculate total
    $cost_query = $conn->query("SELECT cost FROM purchase_order_items 
                                WHERE po_id = $po_id 
                                AND family_code = '$family_code_escaped' 
                                AND item_no = $item_no 
                                LIMIT 1");
    
    $cost = 0.00;
    if ($cost_query && $cost_query->num_rows > 0) {
        $cost_result = $cost_query->fetch_assoc();
        $cost = (float)$cost_result['cost'];
    }
    
    $total = $quantity * $cost;
    $received_qty_clause = ($received_qty !== null) ? ", received_qty = $received_qty" : "";
    
    $update_sql = "UPDATE purchase_order_items 
                   SET item_model = '$item_model_escaped', 
                       item_description = '$item_description_escaped',
                       quantity = $quantity,
                       total = $total
                       $received_qty_clause
                   WHERE po_id = $po_id 
                   AND family_code = '$family_code_escaped' 
                   AND item_no = $item_no 
                   LIMIT 1";
} else {
    // Only update item_model, item_description, and received_qty (if provided)
    $received_qty_clause = ($received_qty !== null) ? ", received_qty = $received_qty" : "";
    
    $update_sql = "UPDATE purchase_order_items 
                   SET item_model = '$item_model_escaped', 
                       item_description = '$item_description_escaped'
                       $received_qty_clause
                   WHERE po_id = $po_id 
                   AND family_code = '$family_code_escaped' 
                   AND item_no = $item_no 
                   LIMIT 1";
}

if ($conn->query($update_sql)) {
    echo json_encode([
        'success' => true,
        'message' => 'Item model updated successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update: ' . $conn->error
    ]);
}

$conn->close();
?>
