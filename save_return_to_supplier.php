<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

try {
    // Get POST data
    $rts_number = trim($_POST['rts_number'] ?? '');
    $rts_date = trim($_POST['rts_date'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');
    $branch_from = trim($_POST['branch_from'] ?? '');
    $delivery_to = trim($_POST['delivery_to'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $items_json = $_POST['items'] ?? '[]';
    
    // Get user info
    $user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Unknown';
    $user_branch = isset($_SESSION['user_branch']) ? $_SESSION['user_branch'] : 'Unknown';
    
    // Decode items
    $items = json_decode($items_json, true);
    if (!$items || !is_array($items)) {
        throw new Exception('Invalid items data');
    }
    
    // Validate required fields
    if (empty($rts_number)) {
        throw new Exception('RTS Number is required');
    }
    if (empty($reference_number)) {
        throw new Exception('Reference Number is required');
    }
    if (empty($delivery_to)) {
        throw new Exception('Delivery to is required');
    }
    if (empty($remarks)) {
        throw new Exception('Remarks is required');
    }
    if (count($items) === 0) {
        throw new Exception('At least one item is required');
    }
    
    // Get branch code
    $branch_code_query = $conn->prepare("SELECT branch_code, branch_name FROM branches WHERE branch_code = ? LIMIT 1");
    $branch_code_query->bind_param("s", $branch_from);
    $branch_code_query->execute();
    $branch_result = $branch_code_query->get_result();
    
    $branch_name = $user_branch;
    if ($branch_result->num_rows > 0) {
        $branch_row = $branch_result->fetch_assoc();
        $branch_name = $branch_row['branch_name'];
    }
    $branch_code_query->close();
    
    // Create return_to_supplier table if it doesn't exist
    $create_rts_table = "CREATE TABLE IF NOT EXISTS return_to_supplier (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        rts_number VARCHAR(50) NOT NULL,
        rts_date VARCHAR(20),
        reference_number VARCHAR(100),
        branch_from VARCHAR(10),
        branch_name VARCHAR(255),
        delivery_to VARCHAR(255),
        remarks TEXT,
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_rts_number (rts_number),
        INDEX idx_branch (branch_from),
        INDEX idx_created_at (created_at)
    )";
    $conn->query($create_rts_table);
    
    // Create return_to_supplier_items table if it doesn't exist
    $create_items_table = "CREATE TABLE IF NOT EXISTS return_to_supplier_items (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        rts_id INT(11) NOT NULL,
        rts_number VARCHAR(50),
        item_code VARCHAR(100),
        item_description TEXT,
        imei VARCHAR(100),
        quantity INT(11) DEFAULT 0,
        cost DECIMAL(15,2) DEFAULT 0.00,
        reason TEXT,
        INDEX idx_rts_id (rts_id),
        INDEX idx_rts_number (rts_number),
        INDEX idx_item_code (item_code),
        INDEX idx_imei (imei)
    )";
    $conn->query($create_items_table);
    
    // Create rts_approval_log table if it doesn't exist (should already exist from approval-returntosupplier.php)
    $create_log_table = "CREATE TABLE IF NOT EXISTS rts_approval_log (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        rts_id INT(11) NOT NULL,
        rts_number VARCHAR(50) NOT NULL,
        reference_number VARCHAR(100),
        branch_from VARCHAR(255),
        branch_code VARCHAR(10),
        delivery_to VARCHAR(255),
        reason TEXT,
        remarks TEXT,
        total_items INT(11) DEFAULT 0,
        created_by VARCHAR(100),
        created_at DATETIME,
        approver VARCHAR(100),
        approval_date DATETIME,
        disapprover VARCHAR(100),
        disapproval_date DATETIME,
        status VARCHAR(20) DEFAULT 'Pending',
        INDEX idx_rts_id (rts_id),
        INDEX idx_rts_number (rts_number),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        INDEX idx_branch (branch_from)
    )";
    $conn->query($create_log_table);
    
    // Check if RTS number already exists
    $check_duplicate = $conn->prepare("SELECT id FROM return_to_supplier WHERE rts_number = ?");
    $check_duplicate->bind_param("s", $rts_number);
    $check_duplicate->execute();
    $duplicate_result = $check_duplicate->get_result();
    
    if ($duplicate_result->num_rows > 0) {
        throw new Exception('RTS Number already exists. Please refresh and try again.');
    }
    $check_duplicate->close();
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Insert into return_to_supplier table
    $insert_rts = $conn->prepare("INSERT INTO return_to_supplier 
        (rts_number, rts_date, reference_number, branch_from, branch_name, delivery_to, remarks, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $insert_rts->bind_param("ssssssss", 
        $rts_number, 
        $rts_date, 
        $reference_number, 
        $branch_from, 
        $branch_name, 
        $delivery_to, 
        $remarks, 
        $user_name
    );
    
    if (!$insert_rts->execute()) {
        throw new Exception('Failed to save return to supplier: ' . $insert_rts->error);
    }
    
    $rts_id = $conn->insert_id;
    $insert_rts->close();
    
    // Insert items
    $insert_item = $conn->prepare("INSERT INTO return_to_supplier_items 
        (rts_id, rts_number, item_code, item_description, imei, quantity, cost, reason) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $total_items = 0;
    foreach ($items as $item) {
        $item_code = trim($item['item_code'] ?? '');
        $item_description = trim($item['item_description'] ?? '');
        $imei = trim($item['imei'] ?? '');
        $quantity = intval($item['quantity'] ?? 0);
        // Prefer cost sent from the form (already resolved via get_po_cost.php)
        $cost = floatval($item['cost'] ?? 0);
        $reason = trim($item['reason'] ?? '');
        
        // Fallback: resolve exact cost (never use AVG — that diluted 50000 across zero-cost rows)
        if ($cost <= 0 && !empty($item_code)) {
            if (!empty($imei)) {
                // Serialized: get cost for this specific IMEI from allocations
                $cost_query = $conn->prepare("SELECT cost FROM purchase_order_allocations 
                    WHERE UPPER(TRIM(item_model)) = UPPER(?) 
                    AND (FIND_IN_SET(?, REPLACE(REPLACE(serial_number, '\r', ''), '\n', ',')) > 0 
                         OR FIND_IN_SET(?, REPLACE(REPLACE(imei_2, '\r', ''), '\n', ',')) > 0)
                    ORDER BY id DESC LIMIT 1");
                $cost_query->bind_param("sss", $item_code, $imei, $imei);
                $cost_query->execute();
                $cost_result = $cost_query->get_result();
                if ($cost_result->num_rows > 0) {
                    $cost = floatval($cost_result->fetch_assoc()['cost']);
                }
                $cost_query->close();
            }
            
            // Latest non-zero PO / allocation cost for the item
            if ($cost <= 0) {
                $cost_query = $conn->prepare("SELECT cost FROM purchase_order_allocations 
                    WHERE UPPER(TRIM(item_model)) = UPPER(?) AND cost > 0 
                    ORDER BY id DESC LIMIT 1");
                $cost_query->bind_param("s", $item_code);
                $cost_query->execute();
                $cost_result = $cost_query->get_result();
                if ($cost_result->num_rows > 0) {
                    $cost = floatval($cost_result->fetch_assoc()['cost']);
                }
                $cost_query->close();
            }
            
            if ($cost <= 0) {
                $cost_query = $conn->prepare("SELECT cost FROM purchase_order_items 
                    WHERE UPPER(TRIM(item_model)) = UPPER(?) AND cost > 0 
                    ORDER BY id DESC LIMIT 1");
                $cost_query->bind_param("s", $item_code);
                $cost_query->execute();
                $cost_result = $cost_query->get_result();
                if ($cost_result->num_rows > 0) {
                    $cost = floatval($cost_result->fetch_assoc()['cost']);
                }
                $cost_query->close();
            }
            
            if ($cost <= 0) {
                $srp_query = $conn->prepare("SELECT COALESCE(srp, 0) as srp FROM items WHERE UPPER(TRIM(item_code)) = UPPER(?) LIMIT 1");
                $srp_query->bind_param("s", $item_code);
                $srp_query->execute();
                $srp_result = $srp_query->get_result();
                if ($srp_result->num_rows > 0) {
                    $cost = floatval($srp_result->fetch_assoc()['srp']);
                }
                $srp_query->close();
            }
        }
        
        $insert_item->bind_param("issssids", 
            $rts_id, 
            $rts_number, 
            $item_code, 
            $item_description, 
            $imei, 
            $quantity, 
            $cost, 
            $reason
        );
        
        if (!$insert_item->execute()) {
            throw new Exception('Failed to save item: ' . $insert_item->error);
        }
        
        // Lock item as On Process while RTS is pending approval
        // Prevents selling/transferring; restored on Disapprove, removed on Approve
        if (!empty($imei)) {
            $upd_stock = $conn->prepare("UPDATE stock_on_hand 
                SET status = 'On Process' 
                WHERE UPPER(TRIM(imei)) = UPPER(?) 
                LIMIT 1");
            $upd_stock->bind_param("s", $imei);
            if (!$upd_stock->execute()) {
                throw new Exception('Failed to update stock status: ' . $upd_stock->error);
            }
            $upd_stock->close();
        } elseif (!empty($item_code) && $quantity > 0) {
            $get_stock = $conn->prepare("SELECT id, quantity, description, family_code, group_name, department, brand, 
                dr_number, dr_date, system_entry_date, item_type, branch
                FROM stock_on_hand 
                WHERE UPPER(TRIM(item_code)) = UPPER(?) 
                AND (imei IS NULL OR imei = '')
                AND status = 'Good Stock'
                AND quantity >= ?
                ORDER BY dr_date ASC LIMIT 1");
            $get_stock->bind_param("si", $item_code, $quantity);
            $get_stock->execute();
            $stock_result = $get_stock->get_result();
            if ($stock_result->num_rows > 0) {
                $stock_row = $stock_result->fetch_assoc();
                $stock_id = intval($stock_row['id']);
                $remaining = intval($stock_row['quantity']) - $quantity;
                
                if ($remaining > 0) {
                    $deduct = $conn->prepare("UPDATE stock_on_hand SET quantity = ? WHERE id = ?");
                    $deduct->bind_param("ii", $remaining, $stock_id);
                    $deduct->execute();
                    $deduct->close();
                    
                    $ins = $conn->prepare("INSERT INTO stock_on_hand 
                        (item_code, description, item_type, branch, dr_number, dr_date, system_entry_date, 
                         quantity, status, family_code, group_name, department, brand) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'On Process', ?, ?, ?, ?)");
                    $ins->bind_param("sssssssissss",
                        $item_code,
                        $stock_row['description'],
                        $stock_row['item_type'],
                        $stock_row['branch'],
                        $stock_row['dr_number'],
                        $stock_row['dr_date'],
                        $stock_row['system_entry_date'],
                        $quantity,
                        $stock_row['family_code'],
                        $stock_row['group_name'],
                        $stock_row['department'],
                        $stock_row['brand']
                    );
                    $ins->execute();
                    $ins->close();
                } else {
                    $mark = $conn->prepare("UPDATE stock_on_hand SET status = 'On Process' WHERE id = ?");
                    $mark->bind_param("i", $stock_id);
                    $mark->execute();
                    $mark->close();
                }
            }
            $get_stock->close();
        }
        
        $total_items++;
    }
    $insert_item->close();
    
    // Insert into approval log with Pending status
    $insert_log = $conn->prepare("INSERT INTO rts_approval_log 
        (rts_id, rts_number, reference_number, branch_from, branch_code, delivery_to, remarks, total_items, created_by, created_at, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending')");
    
    $insert_log->bind_param("issssssis", 
        $rts_id, 
        $rts_number, 
        $reference_number, 
        $branch_name, 
        $branch_from, 
        $delivery_to, 
        $remarks, 
        $total_items, 
        $user_name
    );
    
    if (!$insert_log->execute()) {
        throw new Exception('Failed to create approval log: ' . $insert_log->error);
    }
    $insert_log->close();
    
    // Note: Booklet number tracking is handled by get_next_invoice_number.php
    // The RTS number is already marked as used when it was generated
    // No additional update needed here
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Return to Supplier saved successfully and pending approval!',
        'rts_number' => $rts_number
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>
