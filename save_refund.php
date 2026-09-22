<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'session_check.php';

ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($data['invoice_no']) || empty($data['items'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
    exit;
}

$invoice_no = trim($data['invoice_no']);
$refund_date = !empty($data['refund_date']) ? $data['refund_date'] : date('Y-m-d');
$customer_name = trim($data['customer_name']);
$approved_by = trim($data['approved_by']);
$remarks = trim($data['remarks']);
$total_qty = (int)$data['total_qty'];
$total_amount = (float)$data['total_amount'];
$itemsToRefund = $data['items'];

$encoder = 'Unknown';
foreach (['user_name', 'full_name', 'username'] as $k) {
    if (!empty($_SESSION[$k])) { $encoder = $_SESSION[$k]; break; }
}

$stmt = $conn->prepare("SELECT id, branch_code, status FROM sales_entry WHERE invoice_no = ? LIMIT 1");
$stmt->bind_param("s", $invoice_no);
$stmt->execute();
$sale_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale_row) {
    echo json_encode(['status' => 'error', 'message' => 'Invoice not found.']);
    exit;
}
if ($sale_row['status'] === 'voided') {
    echo json_encode(['status' => 'error', 'message' => 'Cannot refund a voided sale.']);
    exit;
}

// Check if invoice has already been refunded
$refund_check = $conn->prepare("SELECT id FROM refunds WHERE invoice_no = ? LIMIT 1");
$refund_check->bind_param("s", $invoice_no);
$refund_check->execute();
$existing_refund = $refund_check->get_result()->fetch_assoc();
$refund_check->close();

if ($existing_refund) {
    echo json_encode(['status' => 'error', 'message' => 'This invoice has already been refunded. Invoice: ' . $invoice_no]);
    exit;
}

$original_sales_id = $sale_row['id'];
$branch_code = $sale_row['branch_code'];

$branch_name = $branch_code;
$bq = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = ? LIMIT 1");
$bq->bind_param("s", $branch_code);
$bq->execute();
$bq_row = $bq->get_result()->fetch_assoc();
$bq->close();
if ($bq_row && !empty($bq_row['branch_name'])) {
    $branch_name = $bq_row['branch_name'];
}

$conn->begin_transaction();

try {
    // 1. Insert into refunds table
    $insRefund = $conn->prepare("
        INSERT INTO refunds 
        (invoice_no, original_sales_id, refund_date, customer_name, approved_by, remarks, total_qty, total_amount, branch_code, encoder) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insRefund->bind_param("sissssidss", 
        $invoice_no, $original_sales_id, $refund_date, $customer_name, $approved_by, $remarks, $total_qty, $total_amount, $branch_code, $encoder
    );
    $insRefund->execute();
    $refund_id = $insRefund->insert_id;
    $insRefund->close();

    $today = date('Y-m-d');
    
    // Check if sales_entry has status update
    $statusUpdate = 'refunded';
    
    foreach ($itemsToRefund as $item) {
        $item_code = trim($item['item_code']);
        $imei = trim($item['imei'] ?? '');
        $qty = (int)$item['quantity'];
        $price = (float)$item['price'];
        $item_desc = trim($item['item_description']);

        // Get original DR number, DR date, and system_entry_date from stock_on_hand history
        $original_dr_number = '';
        $original_dr_date = $today;
        $original_system_entry_date = $today;
        
        // First, get the DR number from sales_entry_items
        if (!empty($imei)) {
            // For IMEI items, get the specific item's DR info
            $dr_query = $conn->prepare("SELECT dr_number FROM sales_entry_items WHERE sales_entry_id = ? AND item_code = ? AND imei = ? LIMIT 1");
            $dr_query->bind_param("iss", $original_sales_id, $item_code, $imei);
        } else {
            // For non-IMEI items, get by item_code only
            $dr_query = $conn->prepare("SELECT dr_number FROM sales_entry_items WHERE sales_entry_id = ? AND item_code = ? LIMIT 1");
            $dr_query->bind_param("is", $original_sales_id, $item_code);
        }
        
        $dr_query->execute();
        $dr_result = $dr_query->get_result()->fetch_assoc();
        $dr_query->close();
        
        if ($dr_result && !empty($dr_result['dr_number'])) {
            $original_dr_number = $dr_result['dr_number'];
            
            // Get the original dr_date and system_entry_date from stock_on_hand history
            if (!empty($imei)) {
                // For IMEI items, search by IMEI and DR number
                $stock_history_query = $conn->prepare("
                    SELECT dr_date, system_entry_date 
                    FROM stock_on_hand 
                    WHERE imei = ? AND dr_number = ? 
                    ORDER BY system_entry_date ASC 
                    LIMIT 1
                ");
                $stock_history_query->bind_param("ss", $imei, $original_dr_number);
            } else {
                // For non-IMEI items, search by item_code and DR number
                $stock_history_query = $conn->prepare("
                    SELECT dr_date, system_entry_date 
                    FROM stock_on_hand 
                    WHERE item_code = ? AND dr_number = ? 
                    ORDER BY system_entry_date ASC 
                    LIMIT 1
                ");
                $stock_history_query->bind_param("ss", $item_code, $original_dr_number);
            }
            
            $stock_history_query->execute();
            $stock_history_result = $stock_history_query->get_result()->fetch_assoc();
            $stock_history_query->close();
            
            if ($stock_history_result) {
                if (!empty($stock_history_result['dr_date'])) {
                    $original_dr_date = $stock_history_result['dr_date'];
                }
                if (!empty($stock_history_result['system_entry_date'])) {
                    $original_system_entry_date = $stock_history_result['system_entry_date'];
                }
            }
        }

        // Insert into refund_items
        $insItem = $conn->prepare("
            INSERT INTO refund_items (refund_id, item_code, imei, quantity, price, item_description) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insItem->bind_param("issids", $refund_id, $item_code, $imei, $qty, $price, $item_desc);
        $insItem->execute();
        $insItem->close();

        // 2. Restore item to stock_on_hand
        $mq = $conn->prepare("SELECT group_name, department, brand, family_code, has_serial FROM items WHERE item_code = ? LIMIT 1");
        $mq->bind_param("s", $item_code);
        $mq->execute();
        $meta = $mq->get_result()->fetch_assoc();
        $mq->close();

        $group_name  = $meta['group_name'] ?? '';
        $department  = $meta['department'] ?? '';
        $brand       = $meta['brand'] ?? '';
        $family_code = $meta['family_code'] ?? '';
        $has_serial  = !empty($meta['has_serial']) ? (int)$meta['has_serial'] : 0;
        $item_type   = ($has_serial || !empty($imei)) ? 'IMEI' : 'Unit';

        // Use original DR number if available, otherwise use REFUND prefix
        $dr_number = !empty($original_dr_number) ? $original_dr_number : 'REFUND-' . $invoice_no;

        if (!empty($imei)) {
            // Restore IMEI item
            $ck = $conn->prepare("SELECT id FROM stock_on_hand WHERE imei = ? AND item_code = ? LIMIT 1");
            $ck->bind_param("ss", $imei, $item_code);
            $ck->execute();
            $already = ($ck->get_result()->num_rows > 0);
            $ck->close();

            if (!$already) {
                // Must insert 1 row per quantity but IMEI items usually have qty=1
                $insStock = $conn->prepare("
                    INSERT INTO stock_on_hand
                        (item_code, description, group_name, department, brand, family_code,
                         imei, quantity, branch, dr_date, dr_number, system_entry_date, item_type, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 'Active')
                ");
                $insStock->bind_param("ssssssssssss",
                    $item_code, $item_desc, $group_name, $department, $brand, $family_code,
                    $imei, $branch_name, $original_dr_date, $dr_number, $original_system_entry_date, $item_type
                );
                $insStock->execute();
                $insStock->close();
            }
        } else {
            // Restore Non-Serial item
            $sq = $conn->prepare("SELECT id FROM stock_on_hand WHERE item_code = ? AND branch = ? LIMIT 1");
            $sq->bind_param("ss", $item_code, $branch_name);
            $sq->execute();
            $stock_row = $sq->get_result()->fetch_assoc();
            $sq->close();

            if ($stock_row) {
                // Increment
                $inc = $conn->prepare("UPDATE stock_on_hand SET quantity = quantity + ? WHERE id = ?");
                $inc->bind_param("ii", $qty, $stock_row['id']);
                $inc->execute();
                $inc->close();
            } else {
                // Re-insert completely newly
                $insStock = $conn->prepare("
                    INSERT INTO stock_on_hand
                        (item_code, description, group_name, department, brand, family_code,
                         imei, quantity, branch, dr_date, dr_number, system_entry_date, item_type, status)
                    VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, 'Active')
                ");
                $insStock->bind_param("ssssssisssss",
                    $item_code, $item_desc, $group_name, $department, $brand, $family_code,
                    $qty, $branch_name, $original_dr_date, $dr_number, $original_system_entry_date, $item_type
                );
                $insStock->execute();
                $insStock->close();
            }
        }

        // Technically we should update sales_entry_items to subtract the refunded amount to prevent returning again.
        // Wait, how does one prevent refunding the same item twice?
        // We can deduct from sales_entry_items:
        if (!empty($imei)) {
            // Do not zero-out the whole line on partial refunds.
            // Keep remaining quantity visible in sales reports (e.g. 3 sold, 1 refunded => 2 remains).
            $deduct = $conn->prepare("
                UPDATE sales_entry_items
                SET quantity = GREATEST(0, quantity - ?)
                WHERE sales_entry_id = ? AND item_code = ? AND imei = ?
                LIMIT 1
            ");
            $deduct->bind_param("iiss", $qty, $original_sales_id, $item_code, $imei);
            $deduct->execute();
            $deduct->close();
        } else {
            $deduct = $conn->prepare("UPDATE sales_entry_items SET quantity = GREATEST(0, quantity - ?) WHERE sales_entry_id = ? AND item_code = ? LIMIT 1");
            $deduct->bind_param("iis", $qty, $original_sales_id, $item_code);
            $deduct->execute();
            $deduct->close();
        }
    }

    // Optionally update sales_entry total amounts but logging in `refunds` is standard enough.
    // If you deduct item quantities directly in sales_entry_items, the report query will pull shorter amounts accurately.
    // However, the `total_amount` column in `sales_entry` remains originally encoded. 
    // We should subtract the amount refunded from the primary `total_amount` in `sales_entry`.
    $updSalesEntry = $conn->prepare("UPDATE sales_entry SET total_amount = GREATEST(0, total_amount - ?), total_qty = GREATEST(0, total_qty - ?) WHERE id = ?");
    $updSalesEntry->bind_param("dii", $total_amount, $total_qty, $original_sales_id);
    $updSalesEntry->execute();
    $updSalesEntry->close();

    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => 'Refund processed effectively.', 'refund_id' => $refund_id]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

$conn->close();
?>
