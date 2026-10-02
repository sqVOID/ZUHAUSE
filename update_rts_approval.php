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
    
    $conn->begin_transaction();
    
    // Get approval log + RTS items
    $log_stmt = $conn->prepare("SELECT * FROM rts_approval_log WHERE id = ? LIMIT 1");
    $log_stmt->bind_param("i", $approval_log_id);
    $log_stmt->execute();
    $log_result = $log_stmt->get_result();
    
    if ($log_result->num_rows === 0) {
        throw new Exception('Approval record not found');
    }
    
    $log = $log_result->fetch_assoc();
    $log_stmt->close();
    
    if ($log['status'] !== 'Pending') {
        throw new Exception('This return to supplier has already been ' . strtolower($log['status']));
    }
    
    $rts_id = intval($log['rts_id']);
    
    $items_stmt = $conn->prepare("SELECT * FROM return_to_supplier_items WHERE rts_id = ?");
    $items_stmt->bind_param("i", $rts_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $rts_items = [];
    while ($row = $items_result->fetch_assoc()) {
        $rts_items[] = $row;
    }
    $items_stmt->close();
    
    // Update the approval log
    if ($new_status === 'Approved') {
        $update_query = "UPDATE rts_approval_log 
                        SET status = 'Approved', 
                            approver = ?, 
                            approval_date = ?,
                            disapprover = NULL,
                            disapproval_date = NULL
                        WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssi", $user_name, $current_time, $approval_log_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update status: ' . $stmt->error);
        }
        $stmt->close();
        
        // Approved: item is returned to supplier — remove from stock
        foreach ($rts_items as $item) {
            $imei = trim($item['imei'] ?? '');
            $item_code = trim($item['item_code'] ?? '');
            $quantity = intval($item['quantity'] ?? 0);
            
            if (!empty($imei)) {
                $del = $conn->prepare("DELETE FROM stock_on_hand 
                    WHERE UPPER(TRIM(imei)) = UPPER(?) 
                    LIMIT 1");
                $del->bind_param("s", $imei);
                $del->execute();
                $del->close();
            } elseif (!empty($item_code) && $quantity > 0) {
                // Prefer removing the On Process accessory row created at save time
                $del = $conn->prepare("DELETE FROM stock_on_hand 
                    WHERE UPPER(TRIM(item_code)) = UPPER(?) 
                    AND (imei IS NULL OR imei = '')
                    AND status = 'On Process'
                    AND quantity = ?
                    LIMIT 1");
                $del->bind_param("si", $item_code, $quantity);
                $del->execute();
                $deleted = $del->affected_rows;
                $del->close();
                
                if ($deleted === 0) {
                    $find = $conn->prepare("SELECT id, quantity FROM stock_on_hand 
                        WHERE UPPER(TRIM(item_code)) = UPPER(?) 
                        AND (imei IS NULL OR imei = '')
                        AND quantity >= ?
                        ORDER BY CASE WHEN status = 'On Process' THEN 0 ELSE 1 END, id ASC
                        LIMIT 1");
                    $find->bind_param("si", $item_code, $quantity);
                    $find->execute();
                    $found = $find->get_result()->fetch_assoc();
                    $find->close();
                    
                    if ($found) {
                        $new_qty = intval($found['quantity']) - $quantity;
                        if ($new_qty > 0) {
                            $upd2 = $conn->prepare("UPDATE stock_on_hand SET quantity = ? WHERE id = ?");
                            $upd2->bind_param("ii", $new_qty, $found['id']);
                            $upd2->execute();
                            $upd2->close();
                        } else {
                            $del2 = $conn->prepare("DELETE FROM stock_on_hand WHERE id = ?");
                            $del2->bind_param("i", $found['id']);
                            $del2->execute();
                            $del2->close();
                        }
                    }
                }
            }
        }
    } else {
        $update_query = "UPDATE rts_approval_log 
                        SET status = 'Disapproved', 
                            disapprover = ?, 
                            disapproval_date = ?,
                            approver = NULL,
                            approval_date = NULL
                        WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssi", $user_name, $current_time, $approval_log_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update status: ' . $stmt->error);
        }
        $stmt->close();
        
        // Disapproved: restore stock to Good Stock
        foreach ($rts_items as $item) {
            $imei = trim($item['imei'] ?? '');
            $item_code = trim($item['item_code'] ?? '');
            $quantity = intval($item['quantity'] ?? 0);
            
            if (!empty($imei)) {
                $restore = $conn->prepare("UPDATE stock_on_hand 
                    SET status = 'Good Stock' 
                    WHERE UPPER(TRIM(imei)) = UPPER(?) 
                    AND status = 'On Process'
                    LIMIT 1");
                $restore->bind_param("s", $imei);
                $restore->execute();
                $restore->close();
            } elseif (!empty($item_code) && $quantity > 0) {
                // Merge On Process accessory qty back into Good Stock
                $find_op = $conn->prepare("SELECT id, quantity, description, family_code, group_name, department, brand,
                    dr_number, dr_date, system_entry_date, item_type, branch
                    FROM stock_on_hand 
                    WHERE UPPER(TRIM(item_code)) = UPPER(?) 
                    AND (imei IS NULL OR imei = '')
                    AND status = 'On Process'
                    AND quantity = ?
                    LIMIT 1");
                $find_op->bind_param("si", $item_code, $quantity);
                $find_op->execute();
                $op_row = $find_op->get_result()->fetch_assoc();
                $find_op->close();
                
                if ($op_row) {
                    $find_gs = $conn->prepare("SELECT id, quantity FROM stock_on_hand 
                        WHERE UPPER(TRIM(item_code)) = UPPER(?) 
                        AND (imei IS NULL OR imei = '')
                        AND status = 'Good Stock'
                        AND branch = ?
                        ORDER BY id ASC LIMIT 1");
                    $find_gs->bind_param("ss", $item_code, $op_row['branch']);
                    $find_gs->execute();
                    $gs_row = $find_gs->get_result()->fetch_assoc();
                    $find_gs->close();
                    
                    if ($gs_row) {
                        $merged = intval($gs_row['quantity']) + intval($op_row['quantity']);
                        $upd = $conn->prepare("UPDATE stock_on_hand SET quantity = ? WHERE id = ?");
                        $upd->bind_param("ii", $merged, $gs_row['id']);
                        $upd->execute();
                        $upd->close();
                        
                        $del = $conn->prepare("DELETE FROM stock_on_hand WHERE id = ?");
                        $del->bind_param("i", $op_row['id']);
                        $del->execute();
                        $del->close();
                    } else {
                        $upd = $conn->prepare("UPDATE stock_on_hand SET status = 'Good Stock' WHERE id = ?");
                        $upd->bind_param("i", $op_row['id']);
                        $upd->execute();
                        $upd->close();
                    }
                }
            }
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Return to supplier entry has been $new_status successfully"
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>
