<?php
/**
 * Save Multiple Purchase Order Allocations
 * Saves branch allocations for multiple items in a purchase order
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
    $branch_name = isset($_POST['branch_name']) ? trim($_POST['branch_name']) : '';
    $allocations_json = isset($_POST['allocations']) ? $_POST['allocations'] : '';
    
    // Validate inputs
    if ($po_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
        exit;
    }
    
    if (empty($branch_name)) {
        echo json_encode(['success' => false, 'message' => 'Branch name is required.']);
        exit;
    }
    
    if (empty($allocations_json)) {
        echo json_encode(['success' => false, 'message' => 'No allocations provided.']);
        exit;
    }
    
    // Parse allocations JSON
    $allocations = json_decode($allocations_json, true);
    if (!is_array($allocations) || count($allocations) === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid allocations data.']);
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
    
    // Start transaction
    $conn->begin_transaction();
    
    $success_count = 0;
    $error_messages = [];
    
    // Process each allocation
    foreach ($allocations as $allocation) {
        $family_code = isset($allocation['family_code']) ? trim($allocation['family_code']) : '';
        $quantity = isset($allocation['quantity']) ? (int)$allocation['quantity'] : 0;
        $cost = isset($allocation['cost']) ? (float)$allocation['cost'] : 0;
        
        if (empty($family_code) || $quantity <= 0) {
            continue; // Skip invalid entries
        }
        
        // Get item details from purchase_order_items
        $item_query = $conn->query("
            SELECT cost, quantity 
            FROM purchase_order_items 
            WHERE po_id = {$po_id} 
            AND family_code = '" . $conn->real_escape_string($family_code) . "' 
            LIMIT 1
        ");
        
        if (!$item_query || $item_query->num_rows === 0) {
            $error_messages[] = "Item {$family_code} not found in purchase order.";
            continue;
        }
        
        $item_data = $item_query->fetch_assoc();
        $item_cost = $cost > 0 ? $cost : $item_data['cost']; // Use provided cost or default from item
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
            $error_messages[] = "Item {$family_code}: Allocation exceeds available quantity. Only {$available} units available.";
            continue;
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
                $error_messages[] = "Item {$family_code}: Allocation exceeds available quantity. Only {$available} units available for this branch.";
                continue;
            }
            
            $update_sql = "UPDATE purchase_order_allocations 
                          SET quantity = {$new_quantity}, 
                              cost = {$item_cost}, 
                              updated_at = NOW() 
                          WHERE id = {$allocation_id}";
            
            if (!$conn->query($update_sql)) {
                throw new Exception("Failed to update allocation for {$family_code}: " . $conn->error);
            }
            
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
                              {$item_cost}, 
                              'Waiting'
                          )";
            
            if (!$conn->query($insert_sql)) {
                throw new Exception("Failed to insert allocation for {$family_code}: " . $conn->error);
            }
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
            throw new Exception("Failed to update item allocated quantity for {$family_code}: " . $conn->error);
        }
        
        $success_count++;
    }
    
    // Commit transaction
    $conn->commit();
    
    if ($success_count > 0) {
        $message = "Successfully allocated {$success_count} item(s) to {$branch_name}!";
        if (count($error_messages) > 0) {
            $message .= " Note: " . implode(" ", $error_messages);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'allocated_count' => $success_count,
            'redirect' => "purchaseorder-details.php?id={$po_id}"
        ]);
    } else {
        $conn->rollback();
        $message = "No items were allocated.";
        if (count($error_messages) > 0) {
            $message .= " Errors: " . implode(" ", $error_messages);
        }
        echo json_encode([
            'success' => false, 
            'message' => $message
        ]);
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
