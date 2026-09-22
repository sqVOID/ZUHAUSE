<?php
require_once 'session_check.php';
error_reporting(0);
ini_set('display_errors', 0);

session_start();
include 'config.php';

header('Content-Type: application/json');

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input
if (!isset($data['sales_entry_id']) || !isset($data['old_item']) || !isset($data['new_item'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required fields'
    ]);
    exit;
}

$sales_entry_id = intval($data['sales_entry_id']);
$old_item = $data['old_item'];
$new_item = $data['new_item'];

$item_code = isset($old_item['item_code']) ? trim($old_item['item_code']) : '';
$old_imei = isset($old_item['imei']) ? trim($old_item['imei']) : '';
$old_qty = isset($old_item['quantity']) ? floatval($old_item['quantity']) : 0;
$old_price = isset($old_item['price']) ? floatval($old_item['price']) : 0;
$old_desc = isset($old_item['item_description']) ? trim($old_item['item_description']) : '';

$new_item_code = isset($new_item['item_code']) && trim($new_item['item_code']) !== '' ? trim($new_item['item_code']) : $item_code;
$new_description = isset($new_item['item_description']) ? trim($new_item['item_description']) : '';
$new_imei = isset($new_item['imei']) ? trim($new_item['imei']) : '';
$new_qty = isset($new_item['quantity']) ? floatval($new_item['quantity']) : 0;
$new_price = isset($new_item['price']) ? floatval($new_item['price']) : 0;

try {
    // Resolve branch for stock operations
    $se_stmt = $conn->prepare("SELECT branch_code FROM sales_entry WHERE id = ? LIMIT 1");
    $se_stmt->bind_param("i", $sales_entry_id);
    $se_stmt->execute();
    $se_res = $se_stmt->get_result();
    $branch_code = '';
    if ($se_res && $se_res->num_rows > 0) {
        $branch_code = $se_res->fetch_assoc()['branch_code'];
    }
    $se_stmt->close();

    $stock_branch_name = '';
    $branch_name_query = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = ? OR branch_name = ? LIMIT 1");
    $branch_name_query->bind_param("ss", $branch_code, $branch_code);
    $branch_name_query->execute();
    $branch_name_result = $branch_name_query->get_result();
    if ($branch_name_result && $branch_name_result->num_rows > 0) {
        $stock_branch_name = trim($branch_name_result->fetch_assoc()['branch_name']);
    }
    if (empty($stock_branch_name) && isset($_SESSION['user_branch'])) {
        $stock_branch_name = trim($_SESSION['user_branch']);
    }

    $today = date('Y-m-d');
    $conn->begin_transaction();

    // ── 1. Stock Adjustment ──────────────────────────────────────────────────
    if ($old_imei !== $new_imei) {
        // Serialized item change

        // A. If new IMEI is provided, check availability and deduct from stock_on_hand
        if (!empty($new_imei)) {
            $deducted = false;
            if (!empty($stock_branch_name) && strtolower($stock_branch_name) !== 'all branches') {
                $del_stmt = $conn->prepare("
                    DELETE FROM stock_on_hand
                    WHERE TRIM(imei) = TRIM(?)
                      AND branch = ?
                      AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active')
                    LIMIT 1
                ");
                $del_stmt->bind_param("ss", $new_imei, $stock_branch_name);
                $del_stmt->execute();
                if ($del_stmt->affected_rows > 0) {
                    $deducted = true;
                }
                $del_stmt->close();
            }

            if (!$deducted) {
                $del_fallback = $conn->prepare("
                    DELETE FROM stock_on_hand
                    WHERE TRIM(imei) = TRIM(?)
                      AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active')
                    LIMIT 1
                ");
                $del_fallback->bind_param("s", $new_imei);
                $del_fallback->execute();
                if ($del_fallback->affected_rows > 0) {
                    $deducted = true;
                }
                $del_fallback->close();
            }

            if (!$deducted) {
                throw new Exception("The Serial/IMEI '$new_imei' is not available in stock for branch '$stock_branch_name'.");
            }
        }

        // B. If old IMEI existed, restore it back to stock_on_hand
        if (!empty($old_imei)) {
            // Check if old_imei is already in stock_on_hand
            $check_old = $conn->prepare("SELECT id FROM stock_on_hand WHERE imei = ? AND item_code = ? LIMIT 1");
            $check_old->bind_param("ss", $old_imei, $item_code);
            $check_old->execute();
            $already_in_stock = ($check_old->get_result()->num_rows > 0);
            $check_old->close();

            if (!$already_in_stock) {
                // Fetch metadata from items table
                $group_name = null;
                $department = null;
                $brand = null;
                $family_code = null;

                $mq = $conn->prepare("SELECT group_name, department, brand, family_code, description FROM items WHERE item_code = ? LIMIT 1");
                $mq->bind_param("s", $item_code);
                $mq->execute();
                $mres = $mq->get_result();
                if ($mres && $mres->num_rows > 0) {
                    $m = $mres->fetch_assoc();
                    $group_name = $m['group_name'] ?? null;
                    $department = $m['department'] ?? null;
                    $brand = $m['brand'] ?? null;
                    $family_code = $m['family_code'] ?? null;
                    if (empty($old_desc) && !empty($m['description'])) {
                        $old_desc = $m['description'];
                    }
                }
                $mq->close();

                $dr_number = !empty($old_item['dr_number']) ? trim($old_item['dr_number']) : 'MOD-RESTORE';

                $restore_stmt = $conn->prepare("
                    INSERT INTO stock_on_hand (
                        item_code, description, group_name, department, brand, family_code,
                        imei, quantity, branch, dr_date, dr_number, system_entry_date, item_type, status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, 1, ?, ?, ?, ?, 'IMEI', 'Active'
                    )
                ");
                $restore_stmt->bind_param(
                    "sssssssssss",
                    $item_code,
                    $old_desc,
                    $group_name,
                    $department,
                    $brand,
                    $family_code,
                    $old_imei,
                    $stock_branch_name,
                    $today,
                    $dr_number,
                    $today
                );
                $restore_stmt->execute();
                $restore_stmt->close();
            }
        }
    } else if (empty($old_imei) && empty($new_imei) && $old_qty != $new_qty) {
        // Non-serialized item quantity change
        $qty_diff = $new_qty - $old_qty;
        if ($qty_diff > 0) {
            // Deduct more from stock
            if (!empty($stock_branch_name) && strtolower($stock_branch_name) !== 'all branches') {
                $upd_stmt = $conn->prepare("
                    UPDATE stock_on_hand
                    SET quantity = quantity - ?
                    WHERE TRIM(item_code) = TRIM(?)
                      AND branch = ?
                      AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active')
                      AND quantity >= ?
                    LIMIT 1
                ");
                $upd_stmt->bind_param("issi", $qty_diff, $new_item_code, $stock_branch_name, $qty_diff);
            } else {
                $upd_stmt = $conn->prepare("
                    UPDATE stock_on_hand
                    SET quantity = quantity - ?
                    WHERE TRIM(item_code) = TRIM(?)
                      AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active')
                      AND quantity >= ?
                    LIMIT 1
                ");
                $upd_stmt->bind_param("isi", $qty_diff, $new_item_code, $qty_diff);
            }
            $upd_stmt->execute();
            if ($upd_stmt->affected_rows <= 0) {
                $upd_stmt->close();
                throw new Exception("Insufficient stock for item: $new_item_code");
            }
            $upd_stmt->close();
        } else if ($qty_diff < 0) {
            // Restore excess back to stock
            $restore_qty = abs($qty_diff);
            $check_stk = $conn->prepare("
                SELECT id FROM stock_on_hand
                WHERE TRIM(item_code) = TRIM(?)
                  AND branch = ?
                  AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active')
                LIMIT 1
            ");
            $check_stk->bind_param("ss", $item_code, $stock_branch_name);
            $check_stk->execute();
            $stk_res = $check_stk->get_result();

            if ($stk_res && $stk_res->num_rows > 0) {
                $stk_id = $stk_res->fetch_assoc()['id'];
                $upd_restore = $conn->prepare("UPDATE stock_on_hand SET quantity = quantity + ? WHERE id = ?");
                $upd_restore->bind_param("ii", $restore_qty, $stk_id);
                $upd_restore->execute();
                $upd_restore->close();
            } else {
                $ins_stk = $conn->prepare("
                    INSERT INTO stock_on_hand (
                        item_code, description, imei, quantity, branch, dr_date, dr_number, system_entry_date, item_type, status
                    ) VALUES (
                        ?, ?, NULL, ?, ?, ?, 'MOD-RESTORE', ?, 'Unit', 'Active'
                    )
                ");
                $ins_stk->bind_param("ssisss", $item_code, $new_description, $restore_qty, $stock_branch_name, $today, $today);
                $ins_stk->execute();
                $ins_stk->close();
            }
            $check_stk->close();
        }
    }

    // ── 2. Update sales_entry_items table ────────────────────────────────────
    $escaped_desc = $conn->real_escape_string($new_description);
    $escaped_imei = $conn->real_escape_string($new_imei);
    $escaped_item_code = $conn->real_escape_string($item_code);
    $escaped_old_imei = $conn->real_escape_string($old_imei);

    if (!empty($old_imei)) {
        $update_query = "UPDATE sales_entry_items 
                        SET item_description = '$escaped_desc',
                            imei = '$escaped_imei',
                            quantity = $new_qty,
                            price = $new_price
                        WHERE sales_entry_id = '$sales_entry_id'
                          AND item_code = '$escaped_item_code'
                          AND imei = '$escaped_old_imei'
                        LIMIT 1";
    } else {
        $update_query = "UPDATE sales_entry_items 
                        SET item_description = '$escaped_desc',
                            imei = '$escaped_imei',
                            quantity = $new_qty,
                            price = $new_price
                        WHERE sales_entry_id = '$sales_entry_id'
                          AND item_code = '$escaped_item_code'
                          AND (imei = '' OR imei IS NULL)
                          AND quantity = $old_qty
                          AND price = $old_price
                        LIMIT 1";
    }

    if (!$conn->query($update_query)) {
        throw new Exception('Failed to update item in database: ' . $conn->error);
    }

    // ── 3. Recalculate totals for the sales entry ───────────────────────────
    $totals_query = "SELECT 
                        SUM(quantity * price) as total_amount,
                        SUM(quantity) as total_quantity
                     FROM sales_entry_items
                     WHERE sales_entry_id = '$sales_entry_id'";
    
    $totals_result = $conn->query($totals_query);
    if ($totals_result && $totals_result->num_rows > 0) {
        $totals = $totals_result->fetch_assoc();
        $total_amount = $totals['total_amount'] ?? 0;
        $total_quantity = $totals['total_quantity'] ?? 0;

        $discount_query = "SELECT discount, voucher_amount FROM sales_entry WHERE id = '$sales_entry_id'";
        $discount_result = $conn->query($discount_query);
        $discount = 0;
        $voucher_amount = 0;
        if ($discount_result && $discount_result->num_rows > 0) {
            $discount_row = $discount_result->fetch_assoc();
            $discount = floatval($discount_row['discount'] ?? 0);
            $voucher_amount = floatval($discount_row['voucher_amount'] ?? 0);
        }

        $grand_total = $total_amount - $discount - $voucher_amount;

        $update_totals = "UPDATE sales_entry 
                         SET total_amount = $grand_total,
                             total_qty = $total_quantity,
                             updated_at = NOW()
                         WHERE id = '$sales_entry_id'";
        
        if (!$conn->query($update_totals)) {
            throw new Exception('Failed to update sales entry totals: ' . $conn->error);
        }
    }

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Item updated successfully',
        'affected_rows' => $conn->affected_rows
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log('Update Sales Item Error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
