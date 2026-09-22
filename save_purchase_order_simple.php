<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    // ── Generate next PO number (PO-YYYY-NNN) ────────────────────────────────────
    $year = date('Y');
    $prefix = "PO-{$year}-";

    $result = $conn->query("SELECT po_number FROM purchase_orders WHERE po_number LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1");
    $next_seq = 1;
    if ($result && $result->num_rows > 0) {
        $last = $result->fetch_assoc()['po_number'];
        $last_seq = (int) substr($last, strlen($prefix));
        $next_seq = $last_seq + 1;
    }
    $po_number = $prefix . str_pad($next_seq, 3, '0', STR_PAD_LEFT);

    // ── Collect form data ─────────────────────────────────────────────────────────
    $supplier_company = trim($_POST['supplier_company'] ?? '');
    $terms = trim($_POST['terms'] ?? '');
    $payment_due_date = trim($_POST['payment_due_date'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $brand_type = trim($_POST['brand_type'] ?? 'single');
    $selected_brands_json = trim($_POST['selected_brands'] ?? '[]');
    $selected_brands = json_decode($selected_brands_json, true);
    $po_date = date('Y-m-d');
    $items = json_decode($_POST['items'] ?? '[]', true);

    // ── Get user information for workflow tracking ────────────────────────────────
    $created_by = '';
    if (!empty($_SESSION['fullname'])) {
        $created_by = $_SESSION['fullname'];
    } elseif (!empty($_SESSION['username'])) {
        $created_by = $_SESSION['username'];
    } else {
        $created_by = 'Unknown User';
    }

    // Get branch from session user branch
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
    $branch_code = '000'; // Default branch code

    // Use the branch name from session
    $branch_name_to_lookup = $user_branch;

    if (!empty($branch_name_to_lookup)) {
        // Look up the branch code from the branches table
        $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($branch_name_to_lookup) . "' LIMIT 1");
        if ($branch_query && $branch_query->num_rows > 0) {
            $branch_result = $branch_query->fetch_assoc();
            $branch_code = $branch_result['branch_code'];
        }
    } elseif ($system_level === 'Super-Admin') {
        $branch_code = 'ALL';
    }

    if (empty($supplier_company)) {
        echo json_encode(['success' => false, 'message' => 'Supplier Company Name is required.']);
        exit;
    }
    if (empty($payment_due_date)) {
        echo json_encode(['success' => false, 'message' => 'Payment Due Date is required.']);
        exit;
    }
    if (empty($selected_brands) || !is_array($selected_brands)) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one brand.']);
        exit;
    }
    if (empty($items) || !is_array($items)) {
        echo json_encode(['success' => false, 'message' => 'Please add at least one item.']);
        exit;
    }

    // Validate each item
    foreach ($items as $index => $item) {
        $item_no = $index + 1;
        $family_code = trim($item['family_code'] ?? '');

        if (empty($family_code)) {
            echo json_encode(['success' => false, 'message' => "Item {$item_no}: Family Code is required."]);
            exit;
        }

        // Validate that family code exists in the family_codes table
        $verify_family_code_sql = "SELECT COUNT(*) as count FROM family_codes WHERE family_code = ? AND status = 'Active' LIMIT 1";
        $verify_fc_stmt = $conn->prepare($verify_family_code_sql);
        $verify_fc_stmt->bind_param('s', $family_code);
        $verify_fc_stmt->execute();
        $verify_fc_result = $verify_fc_stmt->get_result();
        $fc_row = $verify_fc_result->fetch_assoc();

        if ($fc_row['count'] == 0) {
            echo json_encode(['success' => false, 'message' => "Item {$item_no}: Family Code '{$family_code}' does not exist in the system. Please select a valid family code."]);
            exit;
        }

        if (!isset($item['quantity']) || (int) $item['quantity'] <= 0) {
            echo json_encode(['success' => false, 'message' => "Item {$item_no}: Quantity must be greater than 0."]);
            exit;
        }
        if (!isset($item['cost']) || (float) $item['cost'] < 0) {
            echo json_encode(['success' => false, 'message' => "Item {$item_no}: Cost cannot be negative."]);
            exit;
        }
    }

    // ── Calculate totals ──────────────────────────────────────────────────────────
    $total_qty = 0;
    $total_cost = 0.00;
    $total_items = count($items);

    foreach ($items as $item) {
        $qty = (int) ($item['quantity'] ?? 0);
        $cost = (float) ($item['cost'] ?? 0);
        $total_qty += $qty;
        $total_cost += $qty * $cost;
    }

    // ── Payment due date handling ─────────────────────────────────────────────────
    $due_date_sql = (!empty($payment_due_date)) ? "'" . $conn->real_escape_string($payment_due_date) . "'" : 'NULL';

    // ── Insert PO header ──────────────────────────────────────────────────────────
    $sc = $conn->real_escape_string($supplier_company);
    $trm = $conn->real_escape_string($terms);
    $rmk = $conn->real_escape_string($remarks);
    $cb = $conn->real_escape_string($created_by);

    // Check if created_by column exists in purchase_orders table
    $check_created_by = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'created_by'");
    $has_created_by = ($check_created_by && $check_created_by->num_rows > 0);

    // Check if created_by_branch column exists in purchase_orders table
    $check_created_by_branch = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'created_by_branch'");
    $has_created_by_branch = ($check_created_by_branch && $check_created_by_branch->num_rows > 0);

    // Check if brand_type column exists, if not add it
    $check_brand_type = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'brand_type'");
    $has_brand_type = ($check_brand_type && $check_brand_type->num_rows > 0);
    if (!$has_brand_type) {
        $conn->query("ALTER TABLE purchase_orders ADD COLUMN brand_type VARCHAR(20) DEFAULT 'single' AFTER supplier_company");
    }

    // Check if selected_brands column exists, if not add it
    $check_selected_brands = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'selected_brands'");
    $has_selected_brands = ($check_selected_brands && $check_selected_brands->num_rows > 0);
    if (!$has_selected_brands) {
        $conn->query("ALTER TABLE purchase_orders ADD COLUMN selected_brands TEXT AFTER brand_type");
    }

    // Prepare brand data for storage
    $brand_type_esc = $conn->real_escape_string($brand_type);
    $selected_brands_str = $conn->real_escape_string(json_encode($selected_brands));

    // Build the INSERT query dynamically based on available columns
    $columns = "po_number, supplier_company, brand_type, selected_brands, terms, payment_due_date, remarks, po_date, total_items, total_qty, total_cost, status";
    $values = "'{$po_number}', '{$sc}', '{$brand_type_esc}', '{$selected_brands_str}', '{$trm}', {$due_date_sql}, '{$rmk}', '{$po_date}', {$total_items}, {$total_qty}, {$total_cost}, 'Pending'";

    if ($has_created_by) {
        $columns .= ", created_by";
        $values .= ", '{$cb}'";
    }

    if ($has_created_by_branch) {
        $columns .= ", created_by_branch";
        $values .= ", '{$branch_code}'";
    }

    $sql = "INSERT INTO purchase_orders ({$columns}) VALUES ({$values})";

    if (!$conn->query($sql)) {
        throw new Exception('Failed to save PO: ' . $conn->error);
    }

    $po_id = $conn->insert_id;

    // ── Insert PO items ───────────────────────────────────────────────────────────

    // Check if family_code column exists in purchase_order_items table
    $check_family_code = $conn->query("SHOW COLUMNS FROM purchase_order_items LIKE 'family_code'");
    $has_family_code = ($check_family_code && $check_family_code->num_rows > 0);

    // Add family_code column if it doesn't exist
    if (!$has_family_code) {
        $conn->query("ALTER TABLE purchase_order_items ADD COLUMN family_code VARCHAR(100) AFTER item_no");
    }

    $item_no = 1;
    foreach ($items as $item) {
        $family_code = $conn->real_escape_string(trim($item['family_code'] ?? ''));
        $qty = (int) ($item['quantity'] ?? 0);
        $cost = (float) ($item['cost'] ?? 0);
        $line_total = $qty * $cost;

        $item_sql = "INSERT INTO purchase_order_items 
                          (po_id, po_number, item_no, family_code, quantity, cost, total)
                     VALUES ({$po_id}, '{$po_number}', {$item_no}, '{$family_code}', {$qty}, {$cost}, {$line_total})";

        if (!$conn->query($item_sql)) {
            throw new Exception("Failed to insert item {$item_no}: " . $conn->error);
        }
        $item_no++;
    }

    echo json_encode(['success' => true, 'po_number' => $po_number, 'redirect' => 'purchaseorder.php']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>