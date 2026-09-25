<?php
// Suppress all output before JSON response
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once 'session_check.php';
include 'config.php';

ob_end_clean();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$po_id = isset($input['po_id']) ? intval($input['po_id']) : 0;
$family_code = isset($input['family_code']) ? trim($input['family_code']) : '';
$item_no = isset($input['item_no']) ? intval($input['item_no']) : 0;
$item_types = isset($input['types']) && is_array($input['types']) ? $input['types'] : [];

if ($po_id <= 0 || empty($family_code) || $item_no <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

if (empty($item_types)) {
    echo json_encode(['success' => false, 'message' => 'No item types provided to save']);
    exit;
}

try {
    $conn->begin_transaction();

    $po_number = '';
    $po_q = $conn->query("SELECT po_number FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
    if ($po_q && ($po_row = $po_q->fetch_assoc())) {
        $po_number = $po_row['po_number'] ?? '';
    }

    // Delete existing entries for this PO item
    $delete_sql = "DELETE FROM purchase_order_serial_types 
                   WHERE po_id = ? AND family_code = ? AND item_no = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    if (!$delete_stmt) {
        throw new Exception('Prepare delete failed: ' . $conn->error);
    }
    $delete_stmt->bind_param("isi", $po_id, $family_code, $item_no);
    $delete_stmt->execute();
    $delete_stmt->close();

    $insert_sql = "INSERT INTO purchase_order_serial_types 
                   (po_id, family_code, item_no, serial_number, item_type, created_at, updated_at) 
                   VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                   ON DUPLICATE KEY UPDATE item_type = VALUES(item_type), updated_at = NOW()";
    $insert_stmt = $conn->prepare($insert_sql);
    if (!$insert_stmt) {
        throw new Exception('Prepare insert failed: ' . $conn->error);
    }

    $saved = 0;
    foreach ($item_types as $serial => $type) {
        $serial = trim((string)$serial);
        $type = trim((string)$type);
        if ($serial === '' || $type === '') {
            continue;
        }

        $insert_stmt->bind_param("isiss", $po_id, $family_code, $item_no, $serial, $type);
        if (!$insert_stmt->execute()) {
            throw new Exception('Insert failed: ' . $insert_stmt->error);
        }
        $saved++;

        // Keep stock_on_hand.status in sync for this IMEI on this PO
        if ($po_number !== '') {
            $serial_esc = $conn->real_escape_string($serial);
            $type_esc = $conn->real_escape_string($type);
            $po_number_esc = $conn->real_escape_string($po_number);
            $conn->query("UPDATE stock_on_hand
                          SET status = '{$type_esc}'
                          WHERE BINARY imei = BINARY '{$serial_esc}'
                          AND BINARY dr_number = BINARY '{$po_number_esc}'");
        }
    }
    $insert_stmt->close();

    if ($saved < 1) {
        throw new Exception('No valid serial/type pairs to save');
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Item types saved successfully',
        'saved' => $saved
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
