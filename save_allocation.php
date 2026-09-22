<?php
/**
 * Save Purchase Order Allocation
 * Saves branch allocation for a specific item in a purchase order
 */

require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    // Get POST data
    $po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
    $family_code = isset($_POST['family_code']) ? trim($_POST['family_code']) : '';
    $branch_name = isset($_POST['branch_name']) ? trim($_POST['branch_name']) : '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    
    // Validate inputs
    if ($po_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
        exit;
    }
    
    if (empty($family_code)) {
        echo json_encode(['success' => false, 'message' => 'Family code is required.']);
        exit;
    }
    
    if (empty($branch_name)) {
        echo json_encode(['success' => false, 'message' => 'Branch name is required.']);
        exit;
    }
    
    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0.']);
        exit;
    }
    
    // Get PO number and verify PO exists
    $po_query = $conn->query("SELECT po_number FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
    if (!$po_query || $po_query->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Purchase order not found.']);
        exit;
    }
    $po_data = $po_query->fetch_assoc();
    $po_number = $po_data['po_number'];
    
    // Get item details (cost) from purchase_order_items
    $item_query = $conn->query("
        SELECT cost, quantity 
        FROM purchase_order_items 
        WHERE po_id = {$po_id} 
        AND family_code = '" . $conn->real_escape_string($family_code) . "' 
        LIMIT 1
    ");
    
    if (!$item_query || $item_query->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Item not found in purchase order.']);
        exit;
    }
    
    $item_data = $item_query->fetch_assoc();
    $cost = $item_data['cost'];
    $total_quantity = $item_data['quantity'];
    
    // Check current allocated quantity for this item
    $allocated_query = $conn->query("
        SELECT COALESCE(SUM(quantity), 0) as total_allocated 
        FROM purchase_order_allocations 
        WHERE po_id = {$po_id} 
        AND family_code = '" . $conn->real_escape_string($family_code) . "'
    ");
    
    $total_allocated = 0;
    if ($allocated_query && $allocated_query->num_rows > 0) {
        $allocated_data = $allocated_query->fetch_assoc();
        $total_allocated = (int)$allocated_data['total_allocated'];
    }
    
    // Validate that new allocation doesn't exceed total quantity
    if (($total_allocated + $quantity) > $total_quantity) {
        $available = $total_quantity - $total_allocated;
        echo json_encode([
            'success' => false, 
            'message' => "Allocation exceeds available quantity. Only {$available} units available."
        ]);
        exit;
    }
    
    // Get branch code
    $branch_query = $conn->query("
        SELECT branch_code 
        FROM branches 
        WHERE branch_name = '" . $conn->real_escape_string($branch_name) . "' 
        LIMIT 1
    ");
    
    $branch_code = '';
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
    
    // Check if allocation already exists for this branch and item
    $existing_query = $conn->query("
        SELECT id, quantity 
        FROM purchase_order_allocations 
        WHERE po_id = {$po_id} 
        AND family_code = '" . $conn->real_escape_string($family_code) . "' 
        AND branch_name = '" . $conn->real_escape_string($branch_name) . "'
        LIMIT 1
    ");
    
    if ($existing_query && $existing_query->num_rows > 0) {
        // Update existing allocation
        $existing_data = $existing_query->fetch_assoc();
        $allocation_id = $existing_data['id'];
        $old_quantity = $existing_data['quantity'];
        $new_quantity = $old_quantity + $quantity;
        
        // Re-validate with updated quantity
        if (($total_allocated - $old_quantity + $new_quantity) > $total_quantity) {
            $available = $total_quantity - $total_allocated + $old_quantity;
            echo json_encode([
                'success' => false, 
                'message' => "Allocation exceeds available quantity. Only {$available} units available for this branch."
            ]);
            exit;
        }
        
        $update_sql = "UPDATE purchase_order_allocations 
                      SET quantity = {$new_quantity}, 
                          cost = {$cost}, 
                          updated_at = NOW() 
                      WHERE id = {$allocation_id}";
        
        if (!$conn->query($update_sql)) {
            throw new Exception('Failed to update allocation: ' . $conn->error);
        }
        
        $message = "Allocation updated successfully! Added {$quantity} units (new total: {$new_quantity})";
        
    } else {
        // Insert new allocation
        $insert_sql = "INSERT INTO purchase_order_allocations 
                      (po_id, po_number, branch_name, branch_code, family_code, quantity, received_qty, cost, status) 
                      VALUES (
                          {$po_id}, 
                          '" . $conn->real_escape_string($po_number) . "', 
                          '" . $conn->real_escape_string($branch_name) . "', 
                          '" . $conn->real_escape_string($branch_code) . "', 
                          '" . $conn->real_escape_string($family_code) . "', 
                          {$quantity}, 
                          0, 
                          {$cost}, 
                          'Waiting'
                      )";
        
        if (!$conn->query($insert_sql)) {
            throw new Exception('Failed to insert allocation: ' . $conn->error);
        }
        
        $message = "Allocation created successfully! {$quantity} units allocated to {$branch_name}";
    }
    
    // Update allocated_quantity in purchase_order_items
    $update_item_sql = "UPDATE purchase_order_items 
                       SET allocated_quantity = (
                           SELECT COALESCE(SUM(quantity), 0) 
                           FROM purchase_order_allocations 
                           WHERE po_id = {$po_id} 
                           AND family_code = '" . $conn->real_escape_string($family_code) . "'
                       )
                       WHERE po_id = {$po_id} 
                       AND family_code = '" . $conn->real_escape_string($family_code) . "'";
    
    if (!$conn->query($update_item_sql)) {
        throw new Exception('Failed to update item allocated quantity: ' . $conn->error);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'redirect' => "purchaseorder-details.php?id={$po_id}"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
