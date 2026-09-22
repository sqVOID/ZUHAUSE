<?php
// Suppress all output before JSON response
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once 'session_check.php';
include 'config.php';

// Ensure receive-added item columns exist
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS is_receive_added TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS receiving_branch VARCHAR(255) DEFAULT NULL");

// Clear any output that might have been generated
ob_end_clean();

header('Content-Type: application/json');

// Get POST data
$po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
$items_json = isset($_POST['items']) ? $_POST['items'] : '';
$branch_name = isset($_POST['branch_name']) ? trim($_POST['branch_name']) : '';
$force_new_rows = isset($_POST['force_new_rows']) ? ($_POST['force_new_rows'] === 'true' || $_POST['force_new_rows'] === '1') : false; // Flag to force creating new rows even if family_code exists
$skip_allocation = isset($_POST['skip_allocation']) ? ($_POST['skip_allocation'] === 'true' || $_POST['skip_allocation'] === '1') : false; // Emergency items added during receive - skip allocation, go directly to stock on receive

// Receive-added items always get their own row
if ($skip_allocation) {
    $force_new_rows = true;
}

if ($po_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID']);
    exit;
}

if (empty($items_json)) {
    echo json_encode(['success' => false, 'message' => 'No items provided']);
    exit;
}

$items = json_decode($items_json, true);
if (!$items || !is_array($items)) {
    echo json_encode(['success' => false, 'message' => 'Invalid items format']);
    exit;
}

// Get PO details
$po_query = $conn->query("SELECT po_number, created_by_branch FROM purchase_orders WHERE id = $po_id LIMIT 1");
if (!$po_query || $po_query->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Purchase Order not found']);
    exit;
}

$po = $po_query->fetch_assoc();
$po_number = $po['po_number'];
$created_by_branch = $po['created_by_branch'];

// Begin transaction
$conn->begin_transaction();

try {
    $added_count = 0;
    
    foreach ($items as $item) {
        // Handle both camelCase and snake_case field names
        $family_code = isset($item['family_code']) ? $item['family_code'] : (isset($item['familyCode']) ? $item['familyCode'] : 'N/A');
        $family_code = $conn->real_escape_string($family_code);
        
        $item_no = isset($item['item_no']) ? (int)$item['item_no'] : 1;
        
        $item_model = isset($item['item_model']) ? $item['item_model'] : (isset($item['itemModel']) ? $item['itemModel'] : '');
        $item_model = $conn->real_escape_string($item_model);
        
        $item_description = isset($item['item_description']) ? $item['item_description'] : (isset($item['itemDescription']) ? $item['itemDescription'] : '');
        $item_description = $conn->real_escape_string($item_description);
        
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        $received_qty = isset($item['received_qty']) ? (int)$item['received_qty'] : 0;
        
        // Check if item with same family_code already exists in this PO
        $existing_item_query = $conn->query("SELECT id, cost, quantity, received_qty FROM purchase_order_items 
                                             WHERE po_id = $po_id AND family_code = '$family_code' 
                                             ORDER BY item_no ASC LIMIT 1");
        
        $cost = 0.00;
        $item_already_exists = false;
        $existing_item_id = 0;
        
        if ($existing_item_query && $existing_item_query->num_rows > 0) {
            $existing_item = $existing_item_query->fetch_assoc();
            $cost = (float)$existing_item['cost'];
            
            // Only mark as existing if we're NOT forcing new rows (i.e., not splitting)
            if (!$force_new_rows) {
                $item_already_exists = true;
                $existing_item_id = (int)$existing_item['id'];
                $existing_qty = (int)$existing_item['quantity'];
                $existing_received_qty = (int)$existing_item['received_qty'];
            }
        }
        
        if ($item_already_exists && !$force_new_rows) {
            // Update existing item - add to quantity
            $new_quantity = $existing_qty + $quantity;
            $new_total = $new_quantity * $cost;
            
            $update_sql = "UPDATE purchase_order_items 
                          SET quantity = $new_quantity, 
                              total = $new_total,
                              item_model = '$item_model',
                              item_description = '$item_description'
                          WHERE id = $existing_item_id";
            
            if (!$conn->query($update_sql)) {
                throw new Exception('Failed to update existing item: ' . $conn->error);
            }
        } else {
            // Insert new PO item
            $total = $quantity * $cost;
            
            $receive_added_cols = '';
            $receive_added_vals = '';
            if ($skip_allocation && !empty($branch_name)) {
                $branch_name_escaped = $conn->real_escape_string($branch_name);
                $receive_added_cols = ', is_receive_added, receiving_branch';
                $receive_added_vals = ", 1, '$branch_name_escaped'";
            }
            
            $insert_sql = "INSERT INTO purchase_order_items 
                          (po_id, po_number, item_no, family_code, item_model, item_description, quantity, cost, total, serial_number, received_qty{$receive_added_cols}) 
                          VALUES 
                          ($po_id, '$po_number', $item_no, '$family_code', '$item_model', '$item_description', $quantity, $cost, $total, NULL, $received_qty{$receive_added_vals})";
            
            if (!$conn->query($insert_sql)) {
                throw new Exception('Failed to insert item: ' . $conn->error);
            }
        }
        
        // If branch_name is provided, also create/update allocation entry (skip for emergency receive-added items)
        if (!empty($branch_name) && !$skip_allocation) {
            $branch_name_escaped = $conn->real_escape_string($branch_name);
            
            // Check if allocation already exists
            $alloc_check = $conn->query("SELECT id, quantity FROM purchase_order_allocations 
                                        WHERE po_id = $po_id 
                                        AND family_code = '$family_code' 
                                        AND branch_name = '$branch_name_escaped'");
            
            // Only update existing allocation if NOT forcing new rows
            if ($alloc_check && $alloc_check->num_rows > 0 && !$force_new_rows) {
                // Update existing allocation - add to quantity
                $alloc_row = $alloc_check->fetch_assoc();
                $existing_alloc_qty = (int)$alloc_row['quantity'];
                $new_alloc_qty = $existing_alloc_qty + $quantity;
                
                $update_alloc_sql = "UPDATE purchase_order_allocations 
                                    SET quantity = $new_alloc_qty,
                                        item_model = '$item_model',
                                        item_description = '$item_description',
                                        cost = $cost
                                    WHERE po_id = $po_id 
                                    AND family_code = '$family_code' 
                                    AND branch_name = '$branch_name_escaped'";
                
                if (!$conn->query($update_alloc_sql)) {
                    throw new Exception('Failed to update allocation: ' . $conn->error);
                }
            } else {
                // Create new allocation (either doesn't exist, or we're forcing new rows for splitting)
                $insert_alloc_sql = "INSERT INTO purchase_order_allocations 
                                    (po_id, family_code, branch_name, quantity, item_model, item_description, cost, serial_number, received_qty) 
                                    VALUES 
                                    ($po_id, '$family_code', '$branch_name_escaped', $quantity, '$item_model', '$item_description', $cost, NULL, $received_qty)";
                
                if (!$conn->query($insert_alloc_sql)) {
                    throw new Exception('Failed to insert allocation: ' . $conn->error);
                }
            }
        }
        
        $added_count++;
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Successfully added $added_count item(s)",
        'added_count' => $added_count
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode([
        'success' => false, 
        'message' => 'Error adding items: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
