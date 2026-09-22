<?php
/**
 * Delete Branch Allocation
 * Removes all allocations for a specific branch from a purchase order
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
    
    // Validate inputs
    if ($po_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
        exit;
    }
    
    if (empty($branch_name)) {
        echo json_encode(['success' => false, 'message' => 'Branch name is required.']);
        exit;
    }
    
    // Verify PO exists
    $po_query = $conn->query("SELECT po_number FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
    if (!$po_query || $po_query->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Purchase order not found.']);
        exit;
    }
    
    // Get all family codes for this branch allocation (to update later)
    $family_codes_query = $conn->query("
        SELECT DISTINCT family_code 
        FROM purchase_order_allocations 
        WHERE po_id = {$po_id} 
        AND branch_name = '" . $conn->real_escape_string($branch_name) . "'
    ");
    
    $family_codes = [];
    if ($family_codes_query && $family_codes_query->num_rows > 0) {
        while ($row = $family_codes_query->fetch_assoc()) {
            $family_codes[] = $row['family_code'];
        }
    }
    
    if (empty($family_codes)) {
        echo json_encode(['success' => false, 'message' => 'No allocations found for this branch.']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete all allocations for this branch
        $delete_sql = "DELETE FROM purchase_order_allocations 
                      WHERE po_id = {$po_id} 
                      AND branch_name = '" . $conn->real_escape_string($branch_name) . "'";
        
        if (!$conn->query($delete_sql)) {
            throw new Exception('Failed to delete allocations: ' . $conn->error);
        }
        
        $deleted_count = $conn->affected_rows;
        
        // Update allocated_quantity for all affected items
        foreach ($family_codes as $family_code) {
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
            'message' => "All allocations for {$branch_name} have been removed successfully! ({$deleted_count} items)",
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
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
