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

    $original_invoice_no = trim($data['original_invoice_no'] ?? '');
    $new_invoice_no = trim($data['new_invoice_no'] ?? '');
    $booklet_id = intval($data['booklet_id'] ?? 0);
    $booklet_format = trim($data['booklet_format'] ?? '');
    $upgrade_no = trim($data['upgrade_no'] ?? '');
    $reason = trim($data['reason'] ?? '');
    $remarks = trim($data['remarks'] ?? '');
    $old_items = $data['old_items'] ?? [];
    $new_items = $data['new_items'] ?? [];
    $less_amount = floatval($data['less_amount'] ?? 0);
    $total_amount = floatval($data['total_amount'] ?? 0);
    $payment_data = $data['payment_data'] ?? '';
    if (is_array($payment_data) || is_object($payment_data)) {
        $payment_data = json_encode($payment_data, JSON_UNESCAPED_UNICODE);
    }

    $user_name = $_SESSION['user_name'] ?? 'Unknown';
    $user_branch = $_SESSION['user_branch'] ?? 'Unknown';

    if (empty($original_invoice_no) || empty($upgrade_no) || empty($reason) || empty($old_items) || empty($new_items)) {
        throw new Exception('Missing required fields');
    }

    $conn->begin_transaction();

    // 1. Get original sales entry
    $stmt_get_sale = $conn->prepare("SELECT * FROM sales_entry WHERE invoice_no = ?");
    $stmt_get_sale->bind_param("s", $original_invoice_no);
    $stmt_get_sale->execute();
    $result_sale = $stmt_get_sale->get_result();
    
    if (!$result_sale || $result_sale->num_rows === 0) {
        throw new Exception('Original invoice not found');
    }
    
    $orig_sale = $result_sale->fetch_assoc();
    $orig_sales_entry_id = $orig_sale['id'];
    $stmt_get_sale->close();

    // 2. Resolve new_invoice_no if empty
    include_once 'get_next_invoice_number.php';
    if (empty($new_invoice_no)) {
        $booklet = getBookletConfig($conn, $user_branch, 'salesentry');
        if ($booklet) {
            $new_invoice_no = generateInvoiceNumber($booklet);
            $booklet_id = $booklet['id'];
            $booklet_format = $booklet['booklet_format'];
        } else {
            $today = date('Ymd');
            $new_invoice_no = "UPGD-{$today}-0001";
        }
    }

    // Increment and update booklet sequence if booklet_id was supplied
    if ($booklet_id > 0 && !empty($booklet_format)) {
        $next_number = incrementInvoiceNumber($new_invoice_no, $booklet_format);
        updateInvoiceNumber($conn, $booklet_id, $next_number);
    }

    // 3. Mark original sales_entry as UPGD (leave items intact)
    $stmt_mark_orig = $conn->prepare("UPDATE sales_entry SET upgrade = 'UPGD' WHERE id = ?");
    $stmt_mark_orig->bind_param("i", $orig_sales_entry_id);
    $stmt_mark_orig->execute();
    $stmt_mark_orig->close();

    // 4. Save old dr_numbers for restoring old units to stock_on_hand
    $old_dr_map = [];
    $stmt_old_dr = $conn->prepare("SELECT imei, item_code, dr_number FROM sales_entry_items WHERE sales_entry_id = ? AND imei IS NOT NULL AND imei != ''");
    $stmt_old_dr->bind_param("i", $orig_sales_entry_id);
    $stmt_old_dr->execute();
    $old_dr_res = $stmt_old_dr->get_result();
    while ($old_dr_row = $old_dr_res->fetch_assoc()) {
        $key = strtoupper(trim($old_dr_row['imei']));
        if (!empty($old_dr_row['dr_number'])) {
            $old_dr_map[$key] = $old_dr_row['dr_number'];
        }
    }
    $stmt_old_dr->close();

    // 5. Calculate total amount for new items (SRP)
    $new_unit_total_amount = 0;
    foreach ($new_items as $ni) {
        $new_unit_total_amount += (floatval($ni['price'] ?? 0) * intval($ni['quantity'] ?? 1));
    }
    // The actual cash paid by the customer is new unit SRP minus old unit trade-in value.
    // This is already computed on the front-end as total_amount (= newUnitTotal - oldUnitTotal).
    // If $total_amount was not sent or is 0, fall back to extracting from payment_data.
    $cash_paid = $total_amount; // cash the customer actually paid
    if ($cash_paid <= 0 && !empty($payment_data)) {
        $pd_arr = json_decode($payment_data, true);
        $amt_raw = $pd_arr['Amount'] ?? $pd_arr['Total'] ?? 0;
        $cash_paid = floatval(str_replace(',', '', (string)$amt_raw));
    }
    // Fallback: if still 0, use new SRP (old behaviour)
    if ($cash_paid <= 0) {
        $cash_paid = $new_unit_total_amount;
    }

    // 6. Create NEW sales_entry record under new_invoice_no
    $upgrade_flag = 'UPGD';
    $page_type = 'upgradeunit';
    $total_qty = count($new_items);
    $assisted_by = $orig_sale['assisted_by'] ?? '';
    $sale_branch_code = !empty($orig_sale['branch_code']) ? $orig_sale['branch_code'] : $user_branch;

    $stmt_new_sale = $conn->prepare("
        INSERT INTO sales_entry (
            invoice_no,
            original_invoice_no,
            first_name,
            last_name,
            address,
            contact_no,
            email,
            assisted_by,
            remarks,
            total_qty,
            discount,
            total_amount,
            payment_data,
            upgrade,
            page_type,
            branch_code,
            encoder,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt_new_sale->bind_param(
        "ssssssssidsssssss",
        $new_invoice_no,
        $original_invoice_no,
        $orig_sale['first_name'],
        $orig_sale['last_name'],
        $orig_sale['address'],
        $orig_sale['contact_no'],
        $orig_sale['email'],
        $assisted_by,
        $remarks,
        $total_qty,
        $less_amount,
        $cash_paid,
        $payment_data,
        $upgrade_flag,
        $page_type,
        $sale_branch_code,
        $user_name
    );
    if (!$stmt_new_sale->execute()) {
        throw new Exception('Failed to insert new sales entry: ' . $stmt_new_sale->error);
    }
    $new_sales_entry_id = $conn->insert_id;
    $stmt_new_sale->close();

    // Auto-fix any previous sales_entry rows whose branch_code was set to full branch name instead of code
    $conn->query("
        UPDATE sales_entry se_new
        JOIN sales_entry se_orig ON se_orig.invoice_no = se_new.original_invoice_no
        SET se_new.branch_code = se_orig.branch_code
        WHERE se_new.original_invoice_no IS NOT NULL AND se_new.original_invoice_no != ''
    ");

    // 7. Insert items into sales_entry_items for new_sales_entry_id
    $stmt_items = $conn->prepare("
        INSERT INTO sales_entry_items (
            sales_entry_id, item_code, item_description, imei, quantity, price, dr_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($new_items as $item) {
        $item_code   = $item['item_code'] ?? '';
        $description = $item['description'] ?? '';
        $imei        = $item['imei'] ?? '';
        $quantity    = intval($item['quantity'] ?? 0);
        $price       = floatval($item['price'] ?? 0);

        $new_dr = '';
        if (!empty($imei) && !empty($item_code)) {
            $ndr = $conn->prepare("SELECT dr_number FROM stock_on_hand WHERE UPPER(TRIM(imei)) = UPPER(?) AND UPPER(TRIM(item_code)) = UPPER(?) LIMIT 1");
            $ndr->bind_param("ss", $imei, $item_code);
            $ndr->execute();
            $ndr_row = $ndr->get_result()->fetch_assoc();
            $ndr->close();
            if ($ndr_row && !empty($ndr_row['dr_number'])) {
                $new_dr = $ndr_row['dr_number'];
            }
        }

        $stmt_items->bind_param("isssids", $new_sales_entry_id, $item_code, $description, $imei, $quantity, $price, $new_dr);

        if (!$stmt_items->execute()) {
            throw new Exception('Failed to insert new item: ' . $stmt_items->error);
        }
    }
    $stmt_items->close();

    // 8. Insert record into upgrades table
    $sql_upgrade = "INSERT INTO upgrades (upgrade_no, original_invoice_no, new_invoice_no, reason, remarks, less_amount, total_amount, payment_data, created_by, branch, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt_upgrade = $conn->prepare($sql_upgrade);
    $stmt_upgrade->bind_param("sssssddsss", $upgrade_no, $original_invoice_no, $new_invoice_no, $reason, $remarks, $less_amount, $total_amount, $payment_data, $user_name, $user_branch);
    
    if (!$stmt_upgrade->execute()) {
        throw new Exception('Failed to insert upgrade record: ' . $stmt_upgrade->error);
    }
    
    $upgrade_id = $conn->insert_id;
    $stmt_upgrade->close();

    // 9. Insert old items into upgrade_old_items
    $sql_old = "INSERT INTO upgrade_old_items (upgrade_id, item_description, imei, price) VALUES (?, ?, ?, ?)";
    $stmt_old = $conn->prepare($sql_old);
    foreach ($old_items as $item) {
        $description = $item['description'] ?? '';
        $imei = $item['imei'] ?? '';
        $price = floatval($item['price'] ?? 0);
        
        $stmt_old->bind_param("issd", $upgrade_id, $description, $imei, $price);
        $stmt_old->execute();
    }
    $stmt_old->close();

    // 10. Insert new items into upgrade_new_items
    $sql_new = "INSERT INTO upgrade_new_items (upgrade_id, item_code, item_description, imei, quantity, price) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_new = $conn->prepare($sql_new);
    foreach ($new_items as $item) {
        $item_code = $item['item_code'] ?? '';
        $description = $item['description'] ?? '';
        $imei = $item['imei'] ?? '';
        $quantity = intval($item['quantity'] ?? 0);
        $price = floatval($item['price'] ?? 0);
        
        $stmt_new->bind_param("isssid", $upgrade_id, $item_code, $description, $imei, $quantity, $price);
        $stmt_new->execute();
    }
    $stmt_new->close();

    // 11. Stock updates
    $today = date('Y-m-d');

    // 11a. Return OLD unit back to stock_on_hand
    foreach ($old_items as $item) {
        $description = $item['description'] ?? '';
        $imei        = $item['imei'] ?? '';
        $price       = floatval($item['price'] ?? 0);

        if (!empty($imei)) {
            $item_code_query = $conn->prepare("SELECT item_code, group_name, department, brand, family_code, has_serial FROM items WHERE description = ? LIMIT 1");
            $item_code_query->bind_param("s", $description);
            $item_code_query->execute();
            $item_meta = $item_code_query->get_result()->fetch_assoc();
            $item_code_query->close();

            if ($item_meta) {
                $item_code   = $item_meta['item_code']   ?? '';
                $group_name  = $item_meta['group_name']  ?? null;
                $department  = $item_meta['department']  ?? null;
                $brand       = $item_meta['brand']       ?? null;
                $family_code = $item_meta['family_code'] ?? null;
                $item_type   = 'IMEI';

                $dr_number = null;
                $dr_date   = $today;

                $imei_key = strtoupper(trim($imei));
                if (!empty($old_dr_map[$imei_key])) {
                    $dr_number = $old_dr_map[$imei_key];
                }

                if (!$dr_number) {
                    $siq = $conn->prepare("SELECT dr_number FROM sales_entry_items WHERE UPPER(TRIM(imei)) = UPPER(?) AND UPPER(TRIM(item_code)) = UPPER(?) AND dr_number IS NOT NULL AND dr_number != '' ORDER BY id DESC LIMIT 1");
                    $siq->bind_param("ss", $imei, $item_code);
                    $siq->execute();
                    $si_row = $siq->get_result()->fetch_assoc();
                    $siq->close();
                    if ($si_row && !empty($si_row['dr_number'])) {
                        $dr_number = $si_row['dr_number'];
                    }
                }

                if (!$dr_number) {
                    $pq = $conn->prepare("SELECT po_number FROM purchase_order_items WHERE TRIM(serial_number) = ? LIMIT 1");
                    $pq->bind_param("s", $imei);
                    $pq->execute();
                    $pr = $pq->get_result()->fetch_assoc(); $pq->close();
                    if ($pr && !empty($pr['po_number'])) { $dr_number = $pr['po_number']; }
                }

                if (!$dr_number) {
                    $like = '%' . $imei . '%';
                    $pq = $conn->prepare("SELECT po_number FROM purchase_order_items WHERE serial_number LIKE ? LIMIT 1");
                    $pq->bind_param("s", $like);
                    $pq->execute();
                    $pr = $pq->get_result()->fetch_assoc(); $pq->close();
                    if ($pr && !empty($pr['po_number'])) { $dr_number = $pr['po_number']; }
                }

                if (!$dr_number) {
                    $pq = $conn->prepare("
                        SELECT po.po_number FROM purchase_orders po
                        JOIN purchase_order_items poi ON poi.po_id = po.id
                        WHERE UPPER(TRIM(poi.item_model)) = UPPER(?)
                          AND po.status IN ('Received','Completed','Incomplete')
                        ORDER BY po.id DESC LIMIT 1
                    ");
                    $pq->bind_param("s", $item_code);
                    $pq->execute();
                    $pr = $pq->get_result()->fetch_assoc(); $pq->close();
                    if ($pr && !empty($pr['po_number'])) { $dr_number = $pr['po_number']; }
                }

                if (!$dr_number) { $dr_number = $upgrade_no; }

                $check_stock = $conn->prepare("SELECT id FROM stock_on_hand WHERE imei = ? AND item_code = ? LIMIT 1");
                $check_stock->bind_param("ss", $imei, $item_code);
                $check_stock->execute();
                $already_exists = ($check_stock->get_result()->num_rows > 0);
                $check_stock->close();

                if (!$already_exists) {
                    $insert_stock = $conn->prepare("
                        INSERT INTO stock_on_hand
                            (item_code, description, group_name, department, brand, family_code,
                             imei, quantity, branch, dr_date, dr_number, system_entry_date, item_type, status)
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 'Good Stock')
                    ");
                    $insert_stock->bind_param(
                        "ssssssssssss",
                        $item_code, $description, $group_name, $department, $brand, $family_code,
                        $imei, $user_branch, $dr_date, $dr_number, $today, $item_type
                    );
                    $insert_stock->execute();
                    $insert_stock->close();
                } else {
                    // Ensure returned upgrade unit is marked Good Stock (not Active)
                    $upd_status = $conn->prepare("UPDATE stock_on_hand SET status = 'Good Stock' WHERE imei = ? AND item_code = ? LIMIT 1");
                    $upd_status->bind_param("ss", $imei, $item_code);
                    $upd_status->execute();
                    $upd_status->close();
                }
            }
        }
    }

    // 11b. Remove NEW unit from stock_on_hand
    foreach ($new_items as $item) {
        $new_imei      = trim($item['imei'] ?? '');
        $new_item_code = trim($item['item_code'] ?? '');

        if (!empty($new_imei) && !empty($new_item_code)) {
            $del = $conn->prepare("DELETE FROM stock_on_hand WHERE UPPER(TRIM(imei)) = UPPER(?) AND UPPER(TRIM(item_code)) = UPPER(?) LIMIT 1");
            $del->bind_param("ss", $new_imei, $new_item_code);
            $del->execute();
            $del->close();
        } elseif (empty($new_imei) && !empty($new_item_code)) {
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
        'message' => 'Upgrade saved successfully',
        'upgrade_no' => $upgrade_no,
        'invoice_no' => $new_invoice_no
    ]);

} catch (Exception $e) {
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
