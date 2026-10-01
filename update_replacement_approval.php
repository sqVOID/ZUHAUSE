<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

try {
    $id = intval($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $user_name = $_SESSION['user_name'] ?? 'Unknown';

    if ($id <= 0) {
        throw new Exception('Invalid replacement ID');
    }

    if (!in_array($status, ['Approved', 'Disapproved'])) {
        throw new Exception('Invalid status');
    }

    if ($status === 'Disapproved' && empty($reason)) {
        throw new Exception('Disapproval reason is required');
    }

    $conn->begin_transaction();

    // Get replacement details
    $stmt_get = $conn->prepare("SELECT * FROM replacements WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Replacement not found');
    }
    
    $replacement = $result->fetch_assoc();
    $stmt_get->close();

    // Check if already processed
    if ($replacement['status'] !== 'Pending') {
        throw new Exception('This replacement has already been ' . strtolower($replacement['status']));
    }

    if ($status === 'Approved') {
        // Update replacement status to Approved
        $stmt_update = $conn->prepare("UPDATE replacements SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt_update->bind_param("si", $user_name, $id);
        $stmt_update->execute();
        $stmt_update->close();

        // Get old items that were replaced
        $stmt_old = $conn->prepare("SELECT * FROM replacement_old_items WHERE replacement_id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $old_items_result = $stmt_old->get_result();
        $old_items = [];
        while ($row = $old_items_result->fetch_assoc()) {
            $old_items[] = $row;
        }
        $stmt_old->close();

        // Get new items that replaced them
        $stmt_new = $conn->prepare("SELECT * FROM replacement_new_items WHERE replacement_id = ?");
        $stmt_new->bind_param("i", $id);
        $stmt_new->execute();
        $new_items_result = $stmt_new->get_result();
        $new_items = [];
        while ($row = $new_items_result->fetch_assoc()) {
            $new_items[] = $row;
        }
        $stmt_new->close();

        $today = date('Y-m-d');

        // Update the original invoice's sales_entry_items to replace old IMEI with new IMEI
        // Create a map of old IMEI to new IMEI
        $imei_replacement_map = [];
        foreach ($old_items as $idx => $old_item) {
            $old_imei = trim($old_item['imei'] ?? '');
            if (!empty($old_imei) && isset($new_items[$idx])) {
                $new_imei = trim($new_items[$idx]['imei'] ?? '');
                if (!empty($new_imei)) {
                    $imei_replacement_map[$old_imei] = [
                        'new_imei' => $new_imei,
                        'old_imei' => $old_imei
                    ];
                }
            }
        }

        // Update sales_entry_items: replace IMEI and add old_imei field
        foreach ($imei_replacement_map as $old_imei => $replacement_info) {
            $new_imei = $replacement_info['new_imei'];
            $old_imei_value = $replacement_info['old_imei'];
            
            // First, check if old_imei column exists in sales_entry_items, if not add it
            $check_column = $conn->query("SHOW COLUMNS FROM sales_entry_items LIKE 'old_imei'");
            if ($check_column->num_rows == 0) {
                $conn->query("ALTER TABLE sales_entry_items ADD COLUMN old_imei VARCHAR(50) AFTER imei");
            }
            
            // Update the IMEI in sales_entry_items for this invoice
            $update_invoice_item = $conn->prepare("
                UPDATE sales_entry_items 
                SET imei = ?, old_imei = ?
                WHERE sales_entry_id IN (
                    SELECT id FROM sales_entry WHERE invoice_no = ?
                ) AND UPPER(TRIM(imei)) = UPPER(TRIM(?))
            ");
            $update_invoice_item->bind_param("ssss", $new_imei, $old_imei_value, $replacement['invoice_no'], $old_imei);
            $update_invoice_item->execute();
            $update_invoice_item->close();
        }

        // Return old units back to stock (mark as "Good Stock")
        foreach ($old_items as $item) {
            $imei = trim($item['imei'] ?? '');
            $description = $item['item_description'] ?? '';

            if (!empty($imei)) {
                // Get item details from items table
                $item_query = $conn->prepare("SELECT item_code, group_name, department, brand, family_code FROM items WHERE description = ? LIMIT 1");
                $item_query->bind_param("s", $description);
                $item_query->execute();
                $item_meta = $item_query->get_result()->fetch_assoc();
                $item_query->close();

                if ($item_meta) {
                    $item_code = $item_meta['item_code'] ?? '';
                    $group_name = $item_meta['group_name'] ?? null;
                    $department = $item_meta['department'] ?? null;
                    $brand = $item_meta['brand'] ?? null;
                    $family_code = $item_meta['family_code'] ?? null;

                    // Check if item exists in stock and get original DR number
                    $check_stock = $conn->prepare("SELECT id, dr_number FROM stock_on_hand WHERE imei = ? AND item_code = ? LIMIT 1");
                    $check_stock->bind_param("ss", $imei, $item_code);
                    $check_stock->execute();
                    $stock_result = $check_stock->get_result();
                    $stock_row = $stock_result->fetch_assoc();
                    $check_stock->close();

                    if (!$stock_row) {
                        // Item doesn't exist in stock, need to get original DR from sales_entry_items
                        $get_original_dr = $conn->prepare("
                            SELECT sei.dr_number 
                            FROM sales_entry_items sei
                            JOIN sales_entry se ON se.id = sei.sales_entry_id
                            WHERE se.invoice_no = ? AND UPPER(TRIM(sei.imei)) = UPPER(TRIM(?))
                            LIMIT 1
                        ");
                        $get_original_dr->bind_param("ss", $replacement['invoice_no'], $imei);
                        $get_original_dr->execute();
                        $dr_result = $get_original_dr->get_result();
                        $dr_row = $dr_result->fetch_assoc();
                        $get_original_dr->close();
                        
                        // Use original DR if available, otherwise use replacement number
                        $dr_number = !empty($dr_row['dr_number']) ? $dr_row['dr_number'] : $replacement['replacement_no'];
                        
                        // Add back to stock with "Good Stock" status
                        $insert_stock = $conn->prepare("
                            INSERT INTO stock_on_hand
                                (item_code, description, group_name, department, brand, family_code,
                                 imei, quantity, branch, dr_date, dr_number, system_entry_date, item_type, status)
                            VALUES
                                (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, 'IMEI', 'Good Stock')
                        ");
                        $insert_stock->bind_param(
                            "sssssssssss",
                            $item_code, $description, $group_name, $department, $brand, $family_code,
                            $imei, $replacement['branch'], $today, $dr_number, $today
                        );
                        $insert_stock->execute();
                        $insert_stock->close();
                    } else {
                        // Update status to "Good Stock" - keep original DR number
                        $update_stock = $conn->prepare("UPDATE stock_on_hand SET status = 'Good Stock' WHERE imei = ? AND item_code = ?");
                        $update_stock->bind_param("ss", $imei, $item_code);
                        $update_stock->execute();
                        $update_stock->close();
                    }
                }
            }
        }

        // Mark new units as "Sold" and remove from stock
        foreach ($new_items as $item) {
            $imei = trim($item['imei'] ?? '');
            $item_code = trim($item['item_code'] ?? '');

            if (!empty($imei) && !empty($item_code)) {
                // Delete from stock_on_hand (item is now sold)
                $delete_stock = $conn->prepare("DELETE FROM stock_on_hand WHERE UPPER(TRIM(imei)) = UPPER(?) AND UPPER(TRIM(item_code)) = UPPER(?)");
                $delete_stock->bind_param("ss", $imei, $item_code);
                $delete_stock->execute();
                $delete_stock->close();
            } elseif (empty($imei) && !empty($item_code)) {
                // For accessories, quantity was already decremented during save, no action needed
            }
        }

    } elseif ($status === 'Disapproved') {
        // Update replacement status to Disapproved
        $stmt_update = $conn->prepare("UPDATE replacements SET status = 'Disapproved', disapproved_by = ?, disapproved_at = NOW(), disapproval_reason = ? WHERE id = ?");
        $stmt_update->bind_param("ssi", $user_name, $reason, $id);
        $stmt_update->execute();
        $stmt_update->close();

        // 1. Get old items and remove them from stock_on_hand (since replacement was rejected, old item stays sold/with customer)
        $stmt_old = $conn->prepare("SELECT * FROM replacement_old_items WHERE replacement_id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $old_items_result = $stmt_old->get_result();
        $old_items = [];
        while ($row = $old_items_result->fetch_assoc()) {
            $old_items[] = $row;
        }
        $stmt_old->close();

        foreach ($old_items as $old_item) {
            $old_imei = trim($old_item['imei'] ?? '');
            if (!empty($old_imei)) {
                $del_old = $conn->prepare("DELETE FROM stock_on_hand WHERE UPPER(TRIM(imei)) = UPPER(?) AND status = 'On Process'");
                $del_old->bind_param("s", $old_imei);
                $del_old->execute();
                $del_old->close();
            }
        }

        // 2. Get new items
        $stmt_new = $conn->prepare("SELECT * FROM replacement_new_items WHERE replacement_id = ?");
        $stmt_new->bind_param("i", $id);
        $stmt_new->execute();
        $new_items_result = $stmt_new->get_result();
        $new_items = [];
        while ($row = $new_items_result->fetch_assoc()) {
            $new_items[] = $row;
        }
        $stmt_new->close();

        // Restore new items back to stock (change status from "On Process" to "Good Stock")
        foreach ($new_items as $item) {
            $imei = trim($item['imei'] ?? '');
            $item_code = trim($item['item_code'] ?? '');

            if (!empty($imei) && !empty($item_code)) {
                // Update status back to "Good Stock"
                $restore_stock = $conn->prepare("UPDATE stock_on_hand SET status = 'Good Stock' WHERE UPPER(TRIM(imei)) = UPPER(?) AND UPPER(TRIM(item_code)) = UPPER(?)");
                $restore_stock->bind_param("ss", $imei, $item_code);
                $restore_stock->execute();
                $restore_stock->close();
            } elseif (empty($imei) && !empty($item_code)) {
                // For accessories, restore quantity
                $quantity = intval($item['quantity'] ?? 1);
                $restore_qty = $conn->prepare("UPDATE stock_on_hand SET quantity = quantity + ? WHERE UPPER(TRIM(item_code)) = UPPER(?) AND branch = ?");
                $restore_qty->bind_param("iss", $quantity, $item_code, $replacement['branch']);
                $restore_qty->execute();
                $restore_qty->close();
            }
        }
    }

    // Log to approval log table
    $stmt_log = $conn->prepare("
        INSERT INTO replacement_approval_log 
        (replacement_id, replacement_no, original_invoice_no, new_invoice_no, branch, branch_code, 
         reason, remarks, total_amount, created_by, created_at, approver, approval_date, 
         disapprover, disapproval_date, disapproval_reason, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $approval_date = ($status === 'Approved') ? date('Y-m-d H:i:s') : null;
    $disapproval_date = ($status === 'Disapproved') ? date('Y-m-d H:i:s') : null;
    $approver = ($status === 'Approved') ? $user_name : null;
    $disapprover = ($status === 'Disapproved') ? $user_name : null;
    
    $stmt_log->bind_param(
        "isssssssdssssssss",
        $replacement['id'],
        $replacement['replacement_no'],
        $replacement['invoice_no'],
        $replacement['new_invoice_no'],
        $replacement['branch'],
        $replacement['branch_code'],
        $replacement['reason'],
        $replacement['remarks'],
        $replacement['total_amount'],
        $replacement['created_by'],
        $replacement['created_at'],
        $approver,
        $approval_date,
        $disapprover,
        $disapproval_date,
        $reason,
        $status
    );
    
    $stmt_log->execute();
    $stmt_log->close();

    $conn->commit();
    $conn->close();

    echo json_encode(['success' => true, 'message' => 'Replacement ' . strtolower($status) . ' successfully']);

} catch (Throwable $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->close();
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
