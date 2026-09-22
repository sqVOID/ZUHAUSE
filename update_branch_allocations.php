<?php
/**
 * Update Branch Allocations
 * Updates or removes allocations for a specific branch
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
    $allocations_json = isset($_POST['allocations']) ? $_POST['allocations'] : '[]';
    $allocations = json_decode($allocations_json, true);
    
    // Validate inputs
    if ($po_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
        exit;
    }
    
    if (empty($branch_name)) {
        echo json_encode(['success' => false, 'message' => 'Branch name is required.']);
        exit;
    }
    
    if (!is_array($allocations)) {
        echo json_encode(['success' => false, 'message' => 'Invalid allocations data.']);
        exit;
    }
    
    // Get PO number
    $po_query = $conn->query("SELECT po_number FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
    if (!$po_query || $po_query->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Purchase order not found.']);
        exit;
    }
    $po_data = $po_query->fetch_assoc();
    $po_number = $po_data['po_number'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Track which family codes are being updated
        $updated_family_codes = [];
        
        foreach ($allocations as $allocation) {
            $allocation_id = isset($allocation['id']) ? (int)$allocation['id'] : 0;
            $family_code = isset($allocation['family_code']) ? trim($allocation['family_code']) : '';
            $quantity = isset($allocation['quantity']) ? (int)$allocation['quantity'] : 0;
            $action = isset($allocation['action']) ? $allocation['action'] : 'update';
            
            if (empty($family_code)) {
                throw new Exception('Family code is required for all allocations.');
            }
            
            $updated_family_codes[] = $family_code;
            
            if ($action === 'remove') {
                // Delete the allocation
                if ($allocation_id > 0) {
                    $delete_sql = "DELETE FROM purchase_order_allocations WHERE id = {$allocation_id}";
                    if (!$conn->query($delete_sql)) {
                        throw new Exception('Failed to delete allocation: ' . $conn->error);
                    }
                }
            } else {
                // Update or validate
                if ($quantity <= 0) {
                    throw new Exception("Quantity for {$family_code} must be greater than 0.");
                }
                
                // Get item details
                $item_query = $conn->query("
                    SELECT quantity, cost 
                    FROM purchase_order_items 
                    WHERE po_id = {$po_id} 
                    AND family_code = '" . $conn->real_escape_string($family_code) . "' 
                    LIMIT 1
                ");
                
                if (!$item_query || $item_query->num_rows === 0) {
                    throw new Exception("Item {$family_code} not found in purchase order.");
                }
                
                $item_data = $item_query->fetch_assoc();
                $total_quantity = $item_data['quantity'];
                $cost = $item_data['cost'];
                
                // Check total allocated for this item (excluding current allocation)
                $allocated_query = $conn->query("
                    SELECT COALESCE(SUM(quantity), 0) as total_allocated 
                    FROM purchase_order_allocations 
                    WHERE po_id = {$po_id} 
                    AND family_code = '" . $conn->real_escape_string($family_code) . "'
                    AND id != {$allocation_id}
                ");
                
                $total_allocated = 0;
                if ($allocated_query && $allocated_query->num_rows > 0) {
                    $allocated_data = $allocated_query->fetch_assoc();
                    $total_allocated = (int)$allocated_data['total_allocated'];
                }
                
                // Validate new allocation won't exceed total
                if (($total_allocated + $quantity) > $total_quantity) {
                    $available = $total_quantity - $total_allocated;
                    throw new Exception("Allocation for {$family_code} exceeds available quantity. Only {$available} units available.");
                }
                
                // Update the allocation
                if ($allocation_id > 0) {
                    $update_sql = "UPDATE purchase_order_allocations 
                                  SET quantity = {$quantity}, 
                                      cost = {$cost},
                                      updated_at = NOW() 
                                  WHERE id = {$allocation_id}";
                    
                    if (!$conn->query($update_sql)) {
                        throw new Exception('Failed to update allocation: ' . $conn->error);
                    }
                }
            }
        }
        
        // Update allocated_quantity for all affected items
        $unique_family_codes = array_unique($updated_family_codes);
        foreach ($unique_family_codes as $family_code) {
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
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Allocations for {$branch_name} updated successfully!",
            'redirect' => "purchaseorder-details.php?id={$po_id}"
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
