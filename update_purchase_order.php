<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// ── Read JSON input ──────────────────────────────────────────────────────────
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

// If JSON parsing fails, try reading from $_POST (backward compatibility)
if (!$data) {
    $data = $_POST;
}

// ── Get & validate inputs ────────────────────────────────────────────────────
$po_id = isset($data['po_id']) ? (int) $data['po_id'] : 0;
$supplier_company = $conn->real_escape_string(trim($data['supplier_company'] ?? ''));
$supplier_name = $conn->real_escape_string(trim($data['supplier_name'] ?? ''));
$contact_number = $conn->real_escape_string(trim($data['contact_number'] ?? ''));
$address = $conn->real_escape_string(trim($data['address'] ?? ''));
$terms = $conn->real_escape_string(trim($data['terms'] ?? ''));
$payment_due_date = trim($data['payment_due_date'] ?? '');
$remarks = $conn->real_escape_string(trim($data['remarks'] ?? ''));
$edit_reason = $conn->real_escape_string(trim($data['edit_reason'] ?? ''));
$brand_type = isset($data['brand_type']) ? $conn->real_escape_string(trim($data['brand_type'])) : 'single';
$selected_brands = isset($data['selected_brands']) ? $data['selected_brands'] : [];
$items = is_array($data['items'] ?? null) ? $data['items'] : json_decode($data['items'] ?? '[]', true);

if ($po_id <= 0) {
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Invalid PO ID.']);
    exit;
}
if (empty($supplier_company)) {
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Supplier Company Name is required.']);
    exit;
}
if (empty($edit_reason)) {
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Edit reason is required.']);
    exit;
}
if (empty($items) || !is_array($items)) {
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Please add at least one item.']);
    exit;
}

// ── Verify PO exists and is editable ────────────────────────────────────────
$check = $conn->query("SELECT status FROM purchase_orders WHERE id = $po_id LIMIT 1");
if (!$check || $check->num_rows === 0) {
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Purchase Order not found.']);
    exit;
}
$current_status = strtolower($check->fetch_assoc()['status']);

// Get user system level from session
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

// Only Super-Admin can bypass the received/canceled restriction
if (in_array($current_status, ['received', 'canceled', 'cancelled'])) {
    if (strcasecmp($system_level, 'Super-Admin') !== 0) {
        echo json_encode(['status' => 'error', 'success' => false, 'message' => 'This PO cannot be edited after it has been received or canceled.']);
        exit;
    }
}

// ── Calculate totals ─────────────────────────────────────────────────────────
$total_qty = 0;
$total_cost = 0.00;
$total_items = count($items);

foreach ($items as $item) {
    $qty = (int) ($item['quantity'] ?? 0);
    $cost = (float) ($item['cost'] ?? 0);
    $total_qty += $qty;
    $total_cost += $qty * $cost;
}

// ── Payment due date ─────────────────────────────────────────────────────────
$due_date_sql = !empty($payment_due_date) ? "'" . $conn->real_escape_string($payment_due_date) . "'" : 'NULL';

// ── Check and add brand columns if they don't exist ─────────────────────────
$check_brand_type = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'brand_type'");
if (!$check_brand_type || $check_brand_type->num_rows == 0) {
    $conn->query("ALTER TABLE purchase_orders ADD COLUMN brand_type VARCHAR(20) DEFAULT 'single' AFTER supplier_company");
}

$check_selected_brands = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'selected_brands'");
if (!$check_selected_brands || $check_selected_brands->num_rows == 0) {
    $conn->query("ALTER TABLE purchase_orders ADD COLUMN selected_brands TEXT AFTER brand_type");
}

// Prepare brand data
$selected_brands_json = is_array($selected_brands) ? json_encode($selected_brands) : $selected_brands;
$selected_brands_esc = $conn->real_escape_string($selected_brands_json);

// ── Create edit history table if it doesn't exist ───────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS purchase_order_edit_history (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    po_id INT(11) NOT NULL,
    po_number VARCHAR(50) NOT NULL,
    edit_reason TEXT NOT NULL,
    edited_by VARCHAR(150) NOT NULL,
    edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_po_id (po_id)
)");

// ── Get current user information ─────────────────────────────────────────────
$edited_by = '';
if (!empty($_SESSION['fullname'])) {
    $edited_by = $_SESSION['fullname'];
} elseif (!empty($_SESSION['username'])) {
    $edited_by = $_SESSION['username'];
} else {
    $edited_by = 'Unknown User';
}
$edited_by_esc = $conn->real_escape_string($edited_by);

// ── Check if quantities have changed to determine if we need to reset status ─
$quantities_changed = false;
$old_items_check = $conn->query("SELECT family_code, quantity FROM purchase_order_items WHERE po_id = $po_id AND COALESCE(is_receive_added, 0) = 0");
$old_quantities = [];
if ($old_items_check) {
    while ($old_item = $old_items_check->fetch_assoc()) {
        $family_key = trim($old_item['family_code']);
        $old_quantities[$family_key] = ($old_quantities[$family_key] ?? 0) + (int) $old_item['quantity'];
    }
}

// Compare new items with old quantities (grouped by family code)
foreach ($items as $item) {
    $family_key = trim($item['family_code'] ?? '');
    $new_qty = (int) ($item['quantity'] ?? 0);
    $old_qty = isset($old_quantities[$family_key]) ? $old_quantities[$family_key] : 0;

    if ($new_qty != $old_qty) {
        $quantities_changed = true;
        break;
    }
}

// If quantities changed on a received/completed PO, reset workflow when needed
$status_update = "";

// ── Update PO header ─────────────────────────────────────────────────────────
$sql = "UPDATE purchase_orders SET
            supplier_company = '{$supplier_company}',
            brand_type       = '{$brand_type}',
            selected_brands  = '{$selected_brands_esc}',
            terms            = '{$terms}',
            payment_due_date = {$due_date_sql},
            remarks          = '{$remarks}',
            total_items      = {$total_items},
            total_qty        = {$total_qty},
            total_cost       = {$total_cost}
            {$status_update}
        WHERE id = {$po_id}";

if (!$conn->query($sql)) {
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Failed to update PO: ' . $conn->error]);
    exit;
}

// ── Preserve received quantities and item details before deleting old items ─
$received_qty_map = [];
$allocated_qty_map = [];
$item_details_map = [];
$old_items_result = $conn->query("SELECT family_code, item_model, item_description, serial_number, received_qty, allocated_quantity FROM purchase_order_items WHERE po_id = $po_id AND COALESCE(is_receive_added, 0) = 0");
if ($old_items_result) {
    while ($old_item = $old_items_result->fetch_assoc()) {
        $family_code_key = trim($old_item['family_code']);
        $received_qty_map[$family_code_key] = isset($old_item['received_qty']) ? (int) $old_item['received_qty'] : 0;
        $allocated_qty_map[$family_code_key] = isset($old_item['allocated_quantity']) ? (int) $old_item['allocated_quantity'] : 0;

        if (!isset($item_details_map[$family_code_key])) {
            $item_details_map[$family_code_key] = [
                'item_model' => trim($old_item['item_model'] ?? ''),
                'item_description' => trim($old_item['item_description'] ?? ''),
                'serial_number' => trim($old_item['serial_number'] ?? ''),
            ];
        } else {
            foreach (['item_model', 'item_description', 'serial_number'] as $field) {
                if ($item_details_map[$family_code_key][$field] === '' && trim($old_item[$field] ?? '') !== '') {
                    $item_details_map[$family_code_key][$field] = trim($old_item[$field]);
                }
            }
        }
    }
}

// ── Delete old items and re-insert fresh (preserving receive-added items) ────
$conn->query("DELETE FROM purchase_order_items WHERE po_id = $po_id AND COALESCE(is_receive_added, 0) = 0");

// Fetch the po_number for item records
$pn_result = $conn->query("SELECT po_number FROM purchase_orders WHERE id = $po_id LIMIT 1");
$po_number = $pn_result ? $pn_result->fetch_assoc()['po_number'] : '';
$po_number_esc = $conn->real_escape_string($po_number);

// ── Log edit history ─────────────────────────────────────────────────────────
$edit_history_sql = "INSERT INTO purchase_order_edit_history 
                     (po_id, po_number, edit_reason, edited_by, edited_at) 
                     VALUES 
                     ({$po_id}, '{$po_number_esc}', '{$edit_reason}', '{$edited_by_esc}', NOW())";

if (!$conn->query($edit_history_sql)) {
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Failed to log edit history: ' . $conn->error]);
    exit;
}

$item_no = 1;
$all_items_complete = true; // Track if all items have matching serial numbers

// Log for debugging
$debug_log = [];

foreach ($items as $item) {
    $family_code_key = trim($item['family_code'] ?? '');
    $family_code = $conn->real_escape_string($family_code_key);
    $model = trim($item['item_model'] ?? '');
    $description = trim($item['item_description'] ?? '');
    $serial_number_raw = trim($item['serial_number'] ?? '');

    if (isset($item_details_map[$family_code_key])) {
        if ($model === '') {
            $model = $item_details_map[$family_code_key]['item_model'];
        }
        if ($description === '') {
            $description = $item_details_map[$family_code_key]['item_description'];
        }
        if ($serial_number_raw === '') {
            $serial_number_raw = $item_details_map[$family_code_key]['serial_number'];
        }
    }

    $model = $conn->real_escape_string($model);
    $description = $conn->real_escape_string($description);
    $qty = (int) ($item['quantity'] ?? 0);
    $cost = (float) ($item['cost'] ?? 0);
    $line_total = $qty * $cost;

    // Now escape for database insertion
    $serial_number_esc = $conn->real_escape_string($serial_number_raw);

    // Restore the received_qty and allocated_quantity from the preserved maps based on family_code
    $received_qty = isset($received_qty_map[$family_code_key]) ? $received_qty_map[$family_code_key] : 0;
    $allocated_qty = isset($allocated_qty_map[$family_code_key]) ? $allocated_qty_map[$family_code_key] : 0;

    $conn->query("INSERT INTO purchase_order_items
                    (po_id, po_number, item_no, family_code, item_model, item_description, serial_number, quantity, cost, total, received_qty, allocated_quantity)
                  VALUES
                    ({$po_id}, '{$po_number_esc}', {$item_no}, '{$family_code}', '{$model}', '{$description}', '{$serial_number_esc}', {$qty}, {$cost}, {$line_total}, {$received_qty}, {$allocated_qty})");

    // Check if this item needs serial numbers by looking up family_code in items table
    $has_serial_check = $conn->query("SELECT has_serial FROM items WHERE family_code = '{$family_code}' LIMIT 1");

    $debug_item = [
        'item_no' => $item_no,
        'family_code' => $family_code,
        'quantity' => $qty,
        'has_serial_in_db' => false,
        'serial_count' => 0,
        'is_complete' => true,
        'raw_serial_number' => $serial_number_raw
    ];

    if ($has_serial_check && $has_serial_check->num_rows > 0) {
        $has_serial_row = $has_serial_check->fetch_assoc();
        $debug_item['has_serial_in_db'] = ($has_serial_row['has_serial'] == 1);

        if ($has_serial_row['has_serial'] == 1) {
            // This item requires serial numbers - check if all are provided
            // Count serial numbers for this item (comma-separated or newline-separated)
            $serial_count = 0;
            if (!empty($serial_number_raw)) {
                // Handle both comma and newline separation
                $serial_number_normalized = str_replace(',', "\n", $serial_number_raw);
                $serials = array_filter(explode("\n", $serial_number_normalized), function ($s) {
                    return trim($s) !== '';
                });
                $serial_count = count($serials);
            }

            $debug_item['serial_count'] = $serial_count;
            $debug_item['serials_array'] = $serials ?? [];

            // If quantity doesn't match serial count, mark as incomplete
            if ($serial_count < $qty) {
                $all_items_complete = false;
                $debug_item['is_complete'] = false;
            }
        }
    }

    $debug_log[] = $debug_item;
    $item_no++;
}

// Log the debug information
error_log("=== UPDATE_PURCHASE_ORDER DEBUG ===");
error_log("PO ID: {$po_id}");
error_log("Current Status: {$current_status}");
error_log("All Items Complete: " . ($all_items_complete ? 'YES' : 'NO'));
error_log("Debug Log: " . json_encode($debug_log, JSON_PRETTY_PRINT));

// ── Auto-update status if all items are now complete ─────────────────────────
error_log("Checking auto-completion: current_status={$current_status}, all_items_complete=" . ($all_items_complete ? 'YES' : 'NO'));

if ($current_status === 'incomplete' && $all_items_complete) {
    error_log("AUTO-COMPLETING PO {$po_id}");

    // Get current user information for completion tracking
    $completed_by = $edited_by;

    // Get user's branch code (same logic as elsewhere in the codebase)
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
    $completed_by_branch = '';

    if ($system_level === 'Super-Admin') {
        $completed_by_branch = 'ALL';
    } elseif (!empty($user_branch)) {
        $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($user_branch) . "' LIMIT 1");
        if ($branch_query && $branch_query->num_rows > 0) {
            $branch_result = $branch_query->fetch_assoc();
            $completed_by_branch = $branch_result['branch_code'];
        }
    }

    $completed_by_branch_esc = $conn->real_escape_string($completed_by_branch);

    $update_result = $conn->query("UPDATE purchase_orders SET 
                    status = 'Completed',
                    completed_by = '{$edited_by_esc}',
                    completed_at = NOW(),
                    completed_by_branch = '{$completed_by_branch_esc}'
                  WHERE id = {$po_id}");

    if ($update_result) {
        error_log("Successfully updated PO {$po_id} to Completed status");
    } else {
        error_log("FAILED to update PO {$po_id} to Completed status: " . $conn->error);
    }
} else {
    error_log("NOT auto-completing: current_status={$current_status}, all_items_complete=" . ($all_items_complete ? 'YES' : 'NO'));
}

// ── Sync PO status when order qty exceeds allocated qty ─────────────────────
require_once 'po_status_helpers.php';
$totals = get_po_quantity_totals($conn, $po_id);

if ($totals['total_order_qty'] > $totals['total_allocated']) {
    $conn->query("UPDATE purchase_orders SET status = 'Incomplete' WHERE id = {$po_id}");
    error_log("PO {$po_id} marked Incomplete: order qty {$totals['total_order_qty']} > allocated {$totals['total_allocated']}");
} elseif ($quantities_changed && in_array(strtolower($current_status), ['received', 'completed'], true)) {
    $conn->query("UPDATE purchase_orders SET status = 'Pending' WHERE id = {$po_id}");
    error_log("PO {$po_id} reset to Pending after quantity change from {$current_status}");
}

echo json_encode(['status' => 'success', 'success' => true]);
?>