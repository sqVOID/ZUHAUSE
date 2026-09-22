<?php
// Buffer ALL output — must be absolute first line
ob_start();

// Enable error logging to file instead of output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/void_sale_errors.log');

// session_check.php internally calls session_start() + config.php (sets $conn)
require_once 'session_check.php';

// Discard anything session_check / config may have printed
ob_clean();
header('Content-Type: application/json');

// Verify database connection exists
if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// ── Validate request ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($data['id']) || empty($data['reason'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields: id and reason.']);
    exit;
}

$sale_id = (int) $data['id'];
$reason = trim($data['reason']);
$voided_at = date('Y-m-d H:i:s');

// Determine who is voiding
$voided_by = 'Unknown';
foreach (['user_name', 'full_name', 'username'] as $k) {
    if (!empty($_SESSION[$k])) {
        $voided_by = $_SESSION[$k];
        break;
    }
}

// ── 1. Verify sale exists and is not already voided ───────────────────────────
$stmt = $conn->prepare("SELECT id, status, branch_code, invoice_no FROM sales_entry WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$sale_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale_row) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sale not found.']);
    exit;
}
if ($sale_row['status'] === 'voided') {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'This sale is already voided.']);
    exit;
}

$branch_code = $sale_row['branch_code'];
$invoice_number = $sale_row['invoice_no'] ?? null;

// ── 2. Resolve branch_code → branch NAME ─────────────────────────────────────
$branch_name = $branch_code; // safe fallback
$bq = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = ? LIMIT 1");
$bq->bind_param("s", $branch_code);
$bq->execute();
$bq_row = $bq->get_result()->fetch_assoc();
$bq->close();
if ($bq_row) {
    $branch_name = $bq_row['branch_name'];
}

// ── 3. Load all sold items for this sale ──────────────────────────────────────
$iq = $conn->prepare("SELECT item_code, item_description, imei, quantity, dr_number FROM sales_entry_items WHERE sales_entry_id = ?");
$iq->bind_param("i", $sale_id);
$iq->execute();
$items = $iq->get_result()->fetch_all(MYSQLI_ASSOC);
$iq->close();

// ── 4. Run in transaction ─────────────────────────────────────────────────────
$conn->begin_transaction();

try {

    // 4a. Mark the sale as voided
    $upd = $conn->prepare("UPDATE sales_entry SET status='voided', void_reason=?, voided_at=?, voided_by=? WHERE id=?");
    $upd->bind_param("sssi", $reason, $voided_at, $voided_by, $sale_id);
    if (!$upd->execute()) {
        throw new Exception("Failed to update sales_entry: " . $upd->error);
    }
    $upd->close();

    $today = date('Y-m-d');

    // 4b. Find and restore claimed freebies back to stock, then void unclaimed_freebies
    if (!empty($invoice_number)) {
        // Restore claimed freebie items back to stock using unclaimed_freebies as source of truth
        // (claimed_items can have duplicate rows per invoice; unclaimed_freebies has the correct quantity)
        $freebies_query = $conn->prepare("
            SELECT item_code, item_description, quantity, branch
            FROM unclaimed_freebies
            WHERE invoice_number = ? AND status = 'claimed'
        ");
        $freebies_query->bind_param("s", $invoice_number);
        $freebies_query->execute();
        $freebie_items = $freebies_query->get_result()->fetch_all(MYSQLI_ASSOC);
        $freebies_query->close();

        foreach ($freebie_items as $freebie_item) {
            $item_code = trim($freebie_item['item_code']);
            $item_desc = $freebie_item['item_description'];
            $quantity = (int) $freebie_item['quantity'];
            $freebie_branch = $freebie_item['branch'] ?: $branch_name;

            // Get item metadata
            $meta_query = $conn->prepare("SELECT group_name, department, brand, family_code, has_serial FROM items WHERE item_code = ? LIMIT 1");
            $meta_query->bind_param("s", $item_code);
            $meta_query->execute();
            $meta = $meta_query->get_result()->fetch_assoc();
            $meta_query->close();

            if ($meta) {
                $group_name = $meta['group_name'] ?? null;
                $department = $meta['department'] ?? null;
                $brand = $meta['brand'] ?? null;
                $family_code = $meta['family_code'] ?? null;
                $has_serial = !empty($meta['has_serial']) ? (int) $meta['has_serial'] : 0;
                $item_type = $has_serial ? 'IMEI' : 'Unit';

                // Check if stock record exists for this item in this branch
                $stock_check = $conn->prepare("SELECT id, quantity FROM stock_on_hand WHERE TRIM(item_code) = TRIM(?) AND TRIM(branch) = TRIM(?) LIMIT 1");
                $stock_check->bind_param("ss", $item_code, $freebie_branch);
                $stock_check->execute();
                $stock_result = $stock_check->get_result()->fetch_assoc();
                $stock_check->close();

                if ($stock_result) {
                    // Stock exists — increment quantity
                    $update_stock = $conn->prepare("UPDATE stock_on_hand SET quantity = quantity + ? WHERE id = ?");
                    $update_stock->bind_param("ii", $quantity, $stock_result['id']);
                    if (!$update_stock->execute()) {
                        throw new Exception("Failed to restore stock for freebie $item_code: " . $update_stock->error);
                    }
                    $update_stock->close();
                } else {
                    // No stock row — create one
                    $insert_stock = $conn->prepare("
                        INSERT INTO stock_on_hand
                            (item_code, description, group_name, department, brand, family_code,
                             imei, quantity, branch, dr_date, dr_number, system_entry_date, item_type, status)
                        VALUES
                            (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, 'VOID-FREEBIE-RESTORE', ?, ?, 'Active')
                    ");
                    $insert_stock->bind_param(
                        "ssssssissss",
                        $item_code,
                        $item_desc,
                        $group_name,
                        $department,
                        $brand,
                        $family_code,
                        $quantity,
                        $freebie_branch,
                        $today,
                        $today,
                        $item_type
                    );
                    if (!$insert_stock->execute()) {
                        throw new Exception("Failed to insert restored freebie $item_code to stock: " . $insert_stock->error);
                    }
                    $insert_stock->close();
                }
            }
        }

        // Delete claimed_items records for this invoice
        $del_claimed = $conn->prepare("DELETE FROM claimed_items WHERE invoice_no = ?");
        $del_claimed->bind_param("s", $invoice_number);
        if (!$del_claimed->execute()) {
            throw new Exception("Failed to delete claimed items: " . $del_claimed->error);
        }
        $del_claimed->close();

        // Void unclaimed_freebies records for this invoice (mark as voided, not deleted)
        $void_freebies = $conn->prepare("
            UPDATE unclaimed_freebies 
            SET status = 'void', voided_at = ?, voided_by = ?, void_reason = ?
            WHERE invoice_number = ?
        ");
        $void_freebies->bind_param("ssss", $voided_at, $voided_by, $reason, $invoice_number);
        if (!$void_freebies->execute()) {
            throw new Exception("Failed to void unclaimed freebies: " . $void_freebies->error);
        }
        $void_freebies->close();
    }

    foreach ($items as $item) {

        $item_code = trim($item['item_code']);
        $imei = trim($item['imei']);
        $quantity = (int) $item['quantity'];
        $item_desc = $item['item_description'];

        // Pull full metadata from items table
        $mq = $conn->prepare("SELECT group_name, department, brand, family_code, has_serial FROM items WHERE item_code = ? LIMIT 1");
        $mq->bind_param("s", $item_code);
        $mq->execute();
        $meta = $mq->get_result()->fetch_assoc();
        $mq->close();

        $group_name = $meta['group_name'] ?? null;
        $department = $meta['department'] ?? null;
        $brand = $meta['brand'] ?? null;
        $family_code = $meta['family_code'] ?? null;
        // item_type: 'IMEI' for serial items (how sohandunit.php and sohandserial.php filter), 'Unit' otherwise
        $has_serial = !empty($meta['has_serial']) ? (int) $meta['has_serial'] : 0;
        $item_type = ($has_serial || !empty($imei)) ? 'IMEI' : 'Unit';

        if (!empty($imei)) {
            // ── Item has serial/IMEI → was DELETED on sale → RE-INSERT ───────

            // Use the original dr_number saved at time of sale (from stock_on_hand before deletion)
            $dr_number = !empty($item['dr_number']) ? $item['dr_number'] : 'VOID-RESTORE';

            // If still not found, try purchase_order_items as a fallback
            if ($dr_number === 'VOID-RESTORE') {
                $pq = $conn->prepare("SELECT po_number FROM purchase_order_items WHERE serial_number LIKE ? LIMIT 1");
                $imei_like = '%' . $imei . '%';
                $pq->bind_param("s", $imei_like);
                $pq->execute();
                $pq_row = $pq->get_result()->fetch_assoc();
                $pq->close();
                if ($pq_row && !empty($pq_row['po_number'])) {
                    $dr_number = $pq_row['po_number'];
                }
            }

            // Safety guard: skip if already back in stock
            $ck = $conn->prepare("SELECT id FROM stock_on_hand WHERE imei = ? AND item_code = ? LIMIT 1");
            $ck->bind_param("ss", $imei, $item_code);
            $ck->execute();
            $already = ($ck->get_result()->num_rows > 0);
            $ck->close();

            if (!$already) {
                // 11 string params (quantity hardcoded = 1 in SQL)
                $ins = $conn->prepare("
                    INSERT INTO stock_on_hand
                        (item_code, description, group_name, department, brand, family_code,
                         imei, quantity, branch, dr_date, dr_number, system_entry_date, item_type, status)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 'Active')
                ");
                // 12 params: item_code, description, group_name, department, brand, family_code,
                //            imei, branch, dr_date, dr_number, system_entry_date, item_type
                $ins->bind_param(
                    "ssssssssssss",
                    $item_code,
                    $item_desc,
                    $group_name,
                    $department,
                    $brand,
                    $family_code,
                    $imei,
                    $branch_name,
                    $today,
                    $dr_number,
                    $today,
                    $item_type
                );
                $ins->execute();
                $ins->close();
            }

        } else {
            // ── Non-serial item → quantity was decremented → INCREMENT BACK ──
            $dr_number = 'VOID-RESTORE'; // fallback for non-serial items

            $sq = $conn->prepare("SELECT id FROM stock_on_hand WHERE item_code = ? AND branch = ? LIMIT 1");
            $sq->bind_param("ss", $item_code, $branch_name);
            $sq->execute();
            $stock_row = $sq->get_result()->fetch_assoc();
            $sq->close();

            if ($stock_row) {
                // Increment existing row
                $inc = $conn->prepare("UPDATE stock_on_hand SET quantity = quantity + ? WHERE id = ?");
                $inc->bind_param("ii", $quantity, $stock_row['id']);
                $inc->execute();
                $inc->close();
            } else {
                // Row no longer exists (fully depleted) — re-insert
                // 12 string+int params (imei = NULL in SQL)
                $ins = $conn->prepare("
                    INSERT INTO stock_on_hand
                        (item_code, description, group_name, department, brand, family_code,
                         imei, quantity, branch, dr_date, dr_number, system_entry_date, item_type, status)
                    VALUES
                        (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, 'Active')
                ");
                // 12 params: 6 strings, 1 int (qty), 5 strings
                $ins->bind_param(
                    "ssssssisssss",
                    $item_code,
                    $item_desc,
                    $group_name,
                    $department,
                    $brand,
                    $family_code,
                    $quantity,
                    $branch_name,
                    $today,
                    $dr_number,
                    $today,
                    $item_type
                );
                $ins->execute();
                $ins->close();
            }
        }
    }

    $conn->commit();

    ob_clean();
    echo json_encode(['status' => 'success', 'message' => 'Sale voided successfully. Stock and claimed freebies have been restored.']);

} catch (Exception $e) {
    $conn->rollback();
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

$conn->close();
ob_end_flush();
?>