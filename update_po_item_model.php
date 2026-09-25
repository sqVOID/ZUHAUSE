<?php
// Suppress all output before JSON response
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once 'session_check.php';
include 'config.php';

// Clear any output that might have been generated
ob_end_clean();

header('Content-Type: application/json');

// Get POST data
$po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
$family_code = isset($_POST['family_code']) ? trim($_POST['family_code']) : '';
$item_no = isset($_POST['item_no']) ? (int)$_POST['item_no'] : 0;
$item_model = isset($_POST['item_model']) ? trim($_POST['item_model']) : '';
$item_description = isset($_POST['item_description']) ? trim($_POST['item_description']) : '';
$old_item_model = isset($_POST['old_item_model']) ? trim($_POST['old_item_model']) : '';
$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$allocation_id = isset($_POST['allocation_id']) ? (int)$_POST['allocation_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : null;
$received_qty = isset($_POST['received_qty']) ? (int)$_POST['received_qty'] : null;
$receiving_branch = isset($_POST['receiving_branch']) ? trim($_POST['receiving_branch']) : '';

if ($po_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID']);
    exit;
}

// Allow orphan allocation edits (item_no/family may be blank) when allocation_id or old_item_model is provided
if (empty($family_code) && $allocation_id <= 0 && $item_id <= 0 && $old_item_model === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Verify the PO exists
$po_query = $conn->query("SELECT * FROM purchase_orders WHERE id = $po_id LIMIT 1");
if (!$po_query || $po_query->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Purchase Order not found']);
    exit;
}

// Escape values
$family_code_escaped = $conn->real_escape_string($family_code);
$item_model_escaped = $conn->real_escape_string($item_model);
$item_description_escaped = $conn->real_escape_string($item_description);
$old_item_model_escaped = $conn->real_escape_string($old_item_model);

$check_allocations = $conn->query("SELECT COUNT(*) as alloc_count FROM purchase_order_allocations WHERE po_id = $po_id");
$has_allocations = false;
if ($check_allocations) {
    $alloc_row = $check_allocations->fetch_assoc();
    $has_allocations = ((int)($alloc_row['alloc_count'] ?? 0)) > 0;
}

if ($has_allocations && empty($receiving_branch) && $allocation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ERROR: Cannot update item model for allocated PO without specifying the receiving branch. Please refresh the page and try again.']);
    exit;
}

$alloc_updated = false;
$item_updated = false;

// --- Update allocation (targeted) ---
if ($has_allocations || $allocation_id > 0) {
    $set_parts = [
        "item_model = '{$item_model_escaped}'",
        "item_description = '{$item_description_escaped}'"
    ];
    if ($quantity !== null && $quantity > 0) {
        $set_parts[] = "quantity = {$quantity}";
    }
    // Never wipe received_qty on model-only edits (0 would clear completed receive)
    if ($received_qty !== null && $received_qty > 0) {
        $set_parts[] = "received_qty = {$received_qty}";
    }
    $set_sql = implode(', ', $set_parts);

    if ($allocation_id > 0) {
        $update_alloc_sql = "UPDATE purchase_order_allocations
                             SET {$set_sql}
                             WHERE id = {$allocation_id}
                             AND po_id = {$po_id}
                             LIMIT 1";
        $alloc_updated = (bool)$conn->query($update_alloc_sql) && $conn->affected_rows >= 0;
    } else {
        $receiving_branch_escaped = $conn->real_escape_string($receiving_branch);
        $where = "po_id = {$po_id}";
        if (!empty($receiving_branch)) {
            $where .= " AND BINARY branch_name = BINARY '{$receiving_branch_escaped}'";
        }
        if (!empty($family_code) && $family_code !== '-') {
            $where .= " AND BINARY family_code = BINARY '{$family_code_escaped}'";
        }
        // Prefer matching the model being edited so we don't overwrite a sibling color (LIMIT 1 by family only)
        if ($old_item_model !== '' && $old_item_model !== '-') {
            $where .= " AND BINARY item_model = BINARY '{$old_item_model_escaped}'";
        } elseif ($item_model !== '' && $item_model !== '-') {
            // Clearing / first assign: prefer blank model allocation for this family
            $where .= " AND (item_model IS NULL OR item_model = '' OR item_model = '-')";
        }

        $update_alloc_sql = "UPDATE purchase_order_allocations
                             SET {$set_sql}
                             WHERE {$where}
                             LIMIT 1";
        $alloc_updated = (bool)$conn->query($update_alloc_sql);
    }
}

// Prefer precise targeting: allocation_id / item_id first, then old_item_model.
$cost = 0.00;
$poi_where = '';
if ($item_id > 0) {
    $poi_where = "id = {$item_id} AND po_id = {$po_id}";
} elseif ($old_item_model !== '' && $old_item_model !== '-') {
    $poi_where = "po_id = {$po_id} AND BINARY item_model = BINARY '{$old_item_model_escaped}'";
    if (!empty($family_code) && $family_code !== '-') {
        $poi_where .= " AND BINARY family_code = BINARY '{$family_code_escaped}'";
    }
} elseif (!empty($family_code) && $family_code !== '-' && $item_no > 0) {
    $poi_where = "po_id = {$po_id} AND BINARY family_code = BINARY '{$family_code_escaped}' AND item_no = {$item_no}";
}

if ($poi_where !== '') {
    $cost_query = $conn->query("SELECT cost FROM purchase_order_items WHERE {$poi_where} LIMIT 1");
    if ($cost_query && $cost_query->num_rows > 0) {
        $cost = (float)$cost_query->fetch_assoc()['cost'];
    }

    $set_parts = [
        "item_model = '{$item_model_escaped}'",
        "item_description = '{$item_description_escaped}'"
    ];
    if ($quantity !== null && $quantity > 0) {
        $total = $quantity * $cost;
        $set_parts[] = "quantity = {$quantity}";
        $set_parts[] = "total = {$total}";
    }
    // Never clear received_qty / serials on model-only edits
    if ($received_qty !== null && $received_qty > 0) {
        $set_parts[] = "received_qty = {$received_qty}";
    }

    $update_sql = "UPDATE purchase_order_items
                   SET " . implode(', ', $set_parts) . "
                   WHERE {$poi_where}
                   LIMIT 1";
    if ($conn->query($update_sql)) {
        $item_updated = true;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $conn->error]);
        $conn->close();
        exit;
    }
}

// Keep IMEI with this unit and move stock_on_hand to the new model
$serials_to_move = [];
$po_number_for_stock = '';
if ($allocation_id > 0) {
    $ser_q = $conn->query("SELECT serial_number, imei_2, po_number, branch_name
                           FROM purchase_order_allocations
                           WHERE id = {$allocation_id} AND po_id = {$po_id}
                           LIMIT 1");
    if ($ser_q && ($ser_row = $ser_q->fetch_assoc())) {
        $raw = trim($ser_row['serial_number'] ?? '');
        if ($raw !== '') {
            $parts = preg_split('/[\r\n,]+/', $raw);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $serials_to_move[] = $p;
                }
            }
        }
        $po_number_for_stock = $ser_row['po_number'] ?? '';
    }
}
if (empty($serials_to_move) && $poi_where !== '') {
    $ser_q = $conn->query("SELECT serial_number, po_number FROM purchase_order_items WHERE {$poi_where} LIMIT 1");
    if ($ser_q && ($ser_row = $ser_q->fetch_assoc())) {
        $raw = trim($ser_row['serial_number'] ?? '');
        if ($raw !== '') {
            $parts = preg_split('/[\r\n,]+/', $raw);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $serials_to_move[] = $p;
                }
            }
        }
        if ($po_number_for_stock === '') {
            $po_number_for_stock = $ser_row['po_number'] ?? '';
        }
    }
}
if ($po_number_for_stock === '') {
    $pn_q = $conn->query("SELECT po_number FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
    if ($pn_q && ($pn = $pn_q->fetch_assoc())) {
        $po_number_for_stock = $pn['po_number'] ?? '';
    }
}

if (!empty($serials_to_move) && $item_model !== '' && $item_model !== '-') {
    $po_number_esc = $conn->real_escape_string($po_number_for_stock);
    $family_for_stock = $conn->real_escape_string($family_code);
    foreach ($serials_to_move as $serial) {
        $serial_esc = $conn->real_escape_string($serial);
        $conn->query("UPDATE stock_on_hand
                      SET item_code = '{$item_model_escaped}',
                          description = '{$item_description_escaped}'" .
                      ((!empty($family_code) && $family_code !== '-')
                          ? ", family_code = '{$family_for_stock}'"
                          : "") . "
                      WHERE BINARY imei = BINARY '{$serial_esc}'
                      AND BINARY dr_number = BINARY '{$po_number_esc}'");
    }
}

// Success if either allocation or PO item was targeted successfully.
// Allocation-only orphan rows (no matching poi) are valid when allocation_id/old model matched.
if ($alloc_updated || $item_updated || (!$has_allocations && $item_updated)) {
    echo json_encode([
        'success' => true,
        'message' => 'Item model updated successfully'
    ]);
} elseif (!$has_allocations && $poi_where === '') {
    echo json_encode(['success' => false, 'message' => 'Item not found to update']);
} elseif ($has_allocations && !$alloc_updated && $poi_where === '') {
    echo json_encode(['success' => false, 'message' => 'Allocation not found to update. Please refresh and try again.']);
} else {
    // Query ran (0 rows changed still OK if values identical)
    echo json_encode([
        'success' => true,
        'message' => 'Item model updated successfully'
    ]);
}

$conn->close();
?>
