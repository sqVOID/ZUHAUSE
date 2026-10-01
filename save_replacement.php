<?php
require_once 'session_check.php';
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

include 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid data received');
    }

    $replacement_no = trim($data['replacement_no'] ?? '');
    $invoice_no = trim($data['invoice_no'] ?? '');
    $reason = trim($data['reason'] ?? '');
    $remarks = trim($data['remarks'] ?? '');
    $old_items = $data['old_items'] ?? [];
    $new_items = $data['new_items'] ?? [];
    $less_amount = floatval($data['less_amount'] ?? 0);
    $total_amount = floatval($data['total_amount'] ?? 0);

    $user_name = $_SESSION['user_name'] ?? 'Unknown';
    $user_branch = $_SESSION['user_branch'] ?? 'Unknown';

    // Validate required fields
    if (empty($replacement_no) || empty($invoice_no) || empty($reason) || empty($old_items) || empty($new_items)) {
        throw new Exception('Missing required fields');
    }

    $conn->begin_transaction();

    // 1. Insert record into replacements table
    // Generate new invoice number if needed (similar to upgrade)
    $new_invoice_no = '';
    $booklet_id = 0;
    $booklet_format = '';

    // Get branch code
    $branch_code = '';
    $branch_code_query = $conn->prepare("SELECT branch_code FROM branches WHERE branch_name = ? LIMIT 1");
    $branch_code_query->bind_param("s", $user_branch);
    $branch_code_query->execute();
    $branch_code_result = $branch_code_query->get_result();
    if ($branch_code_result->num_rows > 0) {
        $branch_code_row = $branch_code_result->fetch_assoc();
        $branch_code = $branch_code_row['branch_code'];
    }
    $branch_code_query->close();

    $sql_replacement = "INSERT INTO replacements (replacement_no, invoice_no, new_invoice_no, reason, remarks, less_amount, total_amount, created_by, branch, branch_code, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending')";
    $stmt_replacement = $conn->prepare($sql_replacement);
    $stmt_replacement->bind_param("sssssddsss", $replacement_no, $invoice_no, $new_invoice_no, $reason, $remarks, $less_amount, $total_amount, $user_name, $user_branch, $branch_code);

    if (!$stmt_replacement->execute()) {
        throw new Exception('Failed to insert replacement record: ' . $stmt_replacement->error);
    }

    $replacement_id = $conn->insert_id;
    $stmt_replacement->close();

    // 2. Insert old items into replacement_old_items
    $sql_old = "INSERT INTO replacement_old_items (replacement_id, item_description, imei, price) VALUES (?, ?, ?, ?)";
    $stmt_old = $conn->prepare($sql_old);
    foreach ($old_items as $item) {
        $description = $item['description'] ?? '';
        $imei = $item['imei'] ?? '';
        $price = floatval($item['price'] ?? 0);

        $stmt_old->bind_param("issd", $replacement_id, $description, $imei, $price);
        if (!$stmt_old->execute()) {
            throw new Exception('Failed to insert old item: ' . $stmt_old->error);
        }
    }
    $stmt_old->close();
    // 2b. Update stock status for OLD items to "On Process"
    // Old items were sold and removed from stock_on_hand, so re-insert them as "On Process"
    foreach ($old_items as $old_item) {
        $old_imei      = trim($old_item['imei'] ?? '');
        $old_item_code = trim($old_item['item_code'] ?? '');
        $old_desc      = trim($old_item['description'] ?? '');

        if (!empty($old_imei)) {
            // Try update first (in case item still exists in stock)
            $upd_old = $conn->prepare("UPDATE stock_on_hand SET status = 'On Process' WHERE UPPER(TRIM(imei)) = UPPER(?) LIMIT 1");
            $upd_old->bind_param("s", $old_imei);
            $upd_old->execute();
            $affected = $upd_old->affected_rows;
            $upd_old->close();

            // If not found in stock (was already sold), re-insert it
            if ($affected === 0) {
                // Look up original sale details for this IMEI
                $sale_item_q = $conn->prepare("
                    SELECT sei.item_code, sei.item_description, sei.imei, sei.dr_number,
                           i.group_name, i.department, i.brand, i.family_code,
                           COALESCE(b.branch_name, se.branch_code, '') AS branch
                    FROM sales_entry_items sei
                    LEFT JOIN sales_entry se ON se.id = sei.sales_entry_id
                    LEFT JOIN items i ON i.item_code = sei.item_code
                    LEFT JOIN branches b ON b.branch_code = se.branch_code
                    WHERE se.invoice_no = ?
                      AND UPPER(TRIM(sei.imei)) = UPPER(?)
                    LIMIT 1
                ");
                $sale_item_q->bind_param("ss", $invoice_no, $old_imei);
                $sale_item_q->execute();
                $sale_item_res = $sale_item_q->get_result();
                $sale_item_q->close();

                $dr_number = null;
                $dr_date   = null;

                if ($sale_item_res && $sale_item_res->num_rows > 0) {
                    $si         = $sale_item_res->fetch_assoc();
                    $ins_code   = $si['item_code'] ?: $old_item_code;
                    $ins_desc   = $si['item_description'] ?: $old_desc;
                    $ins_group  = $si['group_name'] ?? '';
                    $ins_dept   = $si['department'] ?? '';
                    $ins_brand  = $si['brand'] ?? '';
                    $ins_family = $si['family_code'] ?? '';
                    $ins_type   = 'IMEI';
                    $ins_branch = $si['branch'] ?? $user_branch;
                    $dr_number  = !empty($si['dr_number']) ? $si['dr_number'] : null;
                } else {
                    // Fallback to minimal data
                    $ins_code   = $old_item_code;
                    $ins_desc   = $old_desc;
                    $ins_group  = '';
                    $ins_dept   = '';
                    $ins_brand  = '';
                    $ins_family = '';
                    $ins_type   = 'IMEI';
                    $ins_branch = $user_branch;
                }

                $ins_today = date('Y-m-d');
                $dr_date   = $ins_today;

                if (!empty($dr_number)) {
                    $po_q = $conn->prepare("SELECT po_date FROM purchase_orders WHERE po_number = ? LIMIT 1");
                    if ($po_q) {
                        $po_q->bind_param("s", $dr_number);
                        $po_q->execute();
                        $po_row = $po_q->get_result()->fetch_assoc();
                        $po_q->close();
                        if ($po_row && !empty($po_row['po_date'])) {
                            $dr_date = $po_row['po_date'];
                        }
                    }
                }
                $ins_old = $conn->prepare("
                    INSERT INTO stock_on_hand
                        (item_code, description, group_name, department, brand, family_code, imei, quantity, branch, dr_date, dr_number, system_entry_date, item_type, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 'On Process', NOW(), NOW())
                ");
                if (!$ins_old) {
                    throw new Exception('Prepare failed for stock_on_hand: ' . $conn->error);
                }
                $ins_old->bind_param("ssssssssssss",
                    $ins_code, $ins_desc, $ins_group, $ins_dept,
                    $ins_brand, $ins_family, $old_imei,
                    $ins_branch, $dr_date, $dr_number, $ins_today, $ins_type
                );
                $ins_old->execute();
                $ins_old->close();
            }
        }
    }

    // 3. Insert new items into replacement_new_items
    $sql_new = "INSERT INTO replacement_new_items (replacement_id, item_code, item_description, imei, quantity, price) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_new = $conn->prepare($sql_new);
    foreach ($new_items as $item) {
        $item_code = $item['item_code'] ?? '';
        $description = $item['description'] ?? '';
        $imei = $item['imei'] ?? '';
        $quantity = intval($item['quantity'] ?? 0);
        $price = floatval($item['price'] ?? 0);

        $stmt_new->bind_param("isssid", $replacement_id, $item_code, $description, $imei, $quantity, $price);
        if (!$stmt_new->execute()) {
            throw new Exception('Failed to insert new item: ' . $stmt_new->error);
        }
    }
    $stmt_new->close();

    // 4. Update stock status for new items to "On Process"
    foreach ($new_items as $item) {
        $new_imei = trim($item['imei'] ?? '');
        $new_item_code = trim($item['item_code'] ?? '');

        if (!empty($new_imei) && !empty($new_item_code)) {
            // Check current status before updating
            $check_status = $conn->prepare("SELECT status FROM stock_on_hand WHERE UPPER(TRIM(imei)) = UPPER(?) AND UPPER(TRIM(item_code)) = UPPER(?) LIMIT 1");
            $check_status->bind_param("ss", $new_imei, $new_item_code);
            $check_status->execute();
            $status_result = $check_status->get_result();

            if ($status_result->num_rows > 0) {
                $status_row = $status_result->fetch_assoc();
                $current_status = $status_row['status'];

                // Block if status is In Transit, On Process, or Defective
                $blocked_statuses = ['In Transit', 'On Process', 'Defective'];
                if (in_array($current_status, $blocked_statuses)) {
                    throw new Exception("Cannot process replacement. Item with IMEI $new_imei has status: $current_status. Only items with 'Good Stock', 'Demo', or 'Serviced' status can be used.");
                }
            }
            $check_status->close();

            // Update status to "On Process"
            $upd = $conn->prepare("UPDATE stock_on_hand SET status = 'On Process' WHERE UPPER(TRIM(imei)) = UPPER(?) AND UPPER(TRIM(item_code)) = UPPER(?) LIMIT 1");
            $upd->bind_param("ss", $new_imei, $new_item_code);
            $upd->execute();
            $upd->close();
        } elseif (empty($new_imei) && !empty($new_item_code)) {
            // For accessories (non-IMEI items), decrement quantity
            $new_qty = intval($item['quantity'] ?? 1);
            $dec = $conn->prepare("UPDATE stock_on_hand SET quantity = quantity - ? WHERE UPPER(TRIM(item_code)) = UPPER(?) AND branch = ? AND quantity >= ? LIMIT 1");
            $dec->bind_param("issi", $new_qty, $new_item_code, $user_branch, $new_qty);
            $dec->execute();
            $dec->close();
        }
    }

    $conn->commit();
    $conn->close();

    echo json_encode([
        'status' => 'success',
        'message' => 'Replacement saved successfully',
        'replacement_no' => $replacement_no
    ]);

} catch (Throwable $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->close();
    }

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>