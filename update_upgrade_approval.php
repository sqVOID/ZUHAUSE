<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['id']) || !isset($_POST['status'])) {
        throw new Exception('Missing required parameters');
    }
    
    $approval_log_id = intval($_POST['id']);
    $new_status = trim($_POST['status']);
    
    // Validate status
    if (!in_array($new_status, ['Approved', 'Disapproved'])) {
        throw new Exception('Invalid status');
    }
    
    // Get current user info
    $user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Unknown';
    $current_time = date('Y-m-d H:i:s');
    
    // Start transaction
    $conn->begin_transaction();
    
    // Get approval log details and upgrade_id
    $get_log = $conn->prepare("SELECT upgrade_id, upgrade_no FROM upgrade_approval_log WHERE id = ?");
    $get_log->bind_param("i", $approval_log_id);
    $get_log->execute();
    $log_result = $get_log->get_result();
    
    if ($log_result->num_rows === 0) {
        throw new Exception('Approval log not found');
    }
    
    $log_data = $log_result->fetch_assoc();
    $upgrade_id = $log_data['upgrade_id'];
    $upgrade_no = $log_data['upgrade_no'];
    $get_log->close();
    
    // Update the approval log
    if ($new_status === 'Approved') {
        $update_query = "UPDATE upgrade_approval_log 
                        SET status = 'Approved', 
                            approver = ?, 
                            approval_date = ?,
                            disapprover = NULL,
                            disapproval_date = NULL
                        WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssi", $user_name, $current_time, $approval_log_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update approval status: ' . $stmt->error);
        }
        $stmt->close();
        
        // =====================================================
        // STOCK OPERATIONS WHEN APPROVED
        // =====================================================
        
        // 1. Get OLD items (traded-in units) - set their status to "Good Stock"
        $get_old_items = $conn->prepare("SELECT imei FROM upgrade_old_items WHERE upgrade_id = ?");
        $get_old_items->bind_param("i", $upgrade_id);
        $get_old_items->execute();
        $old_items_result = $get_old_items->get_result();
        
        while ($old_item = $old_items_result->fetch_assoc()) {
            $old_imei = trim($old_item['imei']);
            
            if (!empty($old_imei)) {
                // Update old unit status from "On Process" to "Good Stock"
                $update_old_stock = $conn->prepare("UPDATE stock_on_hand 
                    SET status = 'Good Stock' 
                    WHERE UPPER(TRIM(imei)) = UPPER(?) AND status = 'On Process'");
                $update_old_stock->bind_param("s", $old_imei);
                $update_old_stock->execute();
                $update_old_stock->close();
            }
        }
        $get_old_items->close();
        
        // 2. Get NEW items (purchased units) - delete/deduct from stock_on_hand
        $get_new_items = $conn->prepare("SELECT imei, item_code, quantity FROM upgrade_new_items WHERE upgrade_id = ?");
        $get_new_items->bind_param("i", $upgrade_id);
        $get_new_items->execute();
        $new_items_result = $get_new_items->get_result();
        
        while ($new_item = $new_items_result->fetch_assoc()) {
            $new_imei = trim($new_item['imei']);
            $new_item_code = trim($new_item['item_code']);
            $new_qty = intval($new_item['quantity']);
            
            if (!empty($new_imei) && !empty($new_item_code)) {
                // Delete new unit from stock_on_hand (IMEI items)
                $delete_new_stock = $conn->prepare("DELETE FROM stock_on_hand 
                    WHERE UPPER(TRIM(imei)) = UPPER(?) 
                    AND UPPER(TRIM(item_code)) = UPPER(?) 
                    LIMIT 1");
                $delete_new_stock->bind_param("ss", $new_imei, $new_item_code);
                $delete_new_stock->execute();
                $delete_new_stock->close();
            } else if (empty($new_imei) && !empty($new_item_code) && $new_qty > 0) {
                // Deduct quantity for non-IMEI items (accessories)
                $deduct_stock = $conn->prepare("UPDATE stock_on_hand 
                    SET quantity = quantity - ? 
                    WHERE UPPER(TRIM(item_code)) = UPPER(?) 
                    AND quantity >= ? 
                    LIMIT 1");
                $deduct_stock->bind_param("isi", $new_qty, $new_item_code, $new_qty);
                $deduct_stock->execute();
                $deduct_stock->close();
            }
        }
        $get_new_items->close();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Upgrade unit has been approved successfully. Stock updated: Old units returned to Good Stock, New units deducted from inventory."
        ]);
        
    } else {
        // =====================================================
        // DISAPPROVED - REVERT TO ORIGINAL STATE
        // =====================================================
        
        // Get disapproval reason
        $disapproval_reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
        
        $update_query = "UPDATE upgrade_approval_log 
                        SET status = 'Disapproved', 
                            disapprover = ?, 
                            disapproval_date = ?,
                            disapproval_reason = ?,
                            approver = NULL,
                            approval_date = NULL
                        WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sssi", $user_name, $current_time, $disapproval_reason, $approval_log_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update approval status: ' . $stmt->error);
        }
        $stmt->close();
        
        // 1. Remove OLD units (traded-in) from stock_on_hand
        // These were added back to stock during upgrade process, now remove them
        $get_old_items = $conn->prepare("SELECT imei FROM upgrade_old_items WHERE upgrade_id = ?");
        $get_old_items->bind_param("i", $upgrade_id);
        $get_old_items->execute();
        $old_items_result = $get_old_items->get_result();
        
        while ($old_item = $old_items_result->fetch_assoc()) {
            $old_imei = trim($old_item['imei']);
            
            if (!empty($old_imei)) {
                // Delete old unit from stock_on_hand (reverse the addition)
                $delete_old_stock = $conn->prepare("DELETE FROM stock_on_hand 
                    WHERE UPPER(TRIM(imei)) = UPPER(?) 
                    AND status = 'On Process' 
                    LIMIT 1");
                $delete_old_stock->bind_param("s", $old_imei);
                $delete_old_stock->execute();
                $delete_old_stock->close();
            }
        }
        $get_old_items->close();
        
        // 2. Return NEW units (purchased) status back to "Good Stock"
        // These were marked as "On Process", now return them to available inventory
        $get_new_items = $conn->prepare("SELECT imei, item_code, quantity FROM upgrade_new_items WHERE upgrade_id = ?");
        $get_new_items->bind_param("i", $upgrade_id);
        $get_new_items->execute();
        $new_items_result = $get_new_items->get_result();
        
        while ($new_item = $new_items_result->fetch_assoc()) {
            $new_imei = trim($new_item['imei']);
            $new_item_code = trim($new_item['item_code']);
            $new_qty = intval($new_item['quantity']);
            
            if (!empty($new_imei) && !empty($new_item_code)) {
                // Update new unit status from "On Process" back to "Good Stock"
                $restore_new_stock = $conn->prepare("UPDATE stock_on_hand 
                    SET status = 'Good Stock' 
                    WHERE UPPER(TRIM(imei)) = UPPER(?) 
                    AND UPPER(TRIM(item_code)) = UPPER(?) 
                    AND status = 'On Process'");
                $restore_new_stock->bind_param("ss", $new_imei, $new_item_code);
                $restore_new_stock->execute();
                $restore_new_stock->close();
            } else if (empty($new_imei) && !empty($new_item_code) && $new_qty > 0) {
                // For accessories, add back the quantity that was decremented
                $restore_qty = $conn->prepare("UPDATE stock_on_hand 
                    SET quantity = quantity + ? 
                    WHERE UPPER(TRIM(item_code)) = UPPER(?) 
                    LIMIT 1");
                $restore_qty->bind_param("is", $new_qty, $new_item_code);
                $restore_qty->execute();
                $restore_qty->close();
            }
        }
        $get_new_items->close();
        
        // 3. Optional: Mark the sales_entry record to indicate disapproval
        // You can update the upgrade field or add a note
        $get_upgrade_details = $conn->prepare("SELECT new_invoice_no FROM upgrades WHERE id = ?");
        $get_upgrade_details->bind_param("i", $upgrade_id);
        $get_upgrade_details->execute();
        $upgrade_details = $get_upgrade_details->get_result()->fetch_assoc();
        $get_upgrade_details->close();
        
        if ($upgrade_details && !empty($upgrade_details['new_invoice_no'])) {
            $new_invoice = $upgrade_details['new_invoice_no'];
            // Mark the sales entry as cancelled/disapproved
            $update_sales = $conn->prepare("UPDATE sales_entry 
                SET upgrade = 'UPGD-DISAPPROVED' 
                WHERE invoice_no = ?");
            $update_sales->bind_param("s", $new_invoice);
            $update_sales->execute();
            $update_sales->close();
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Upgrade unit has been disapproved. All changes reverted: Old units removed, New units returned to Good Stock."
        ]);
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
