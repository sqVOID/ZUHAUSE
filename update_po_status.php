<?php
require_once 'session_check.php';
include 'config.php';

// Ensure receive-added item columns and IMEI 2 columns exist
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS is_receive_added TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS receiving_branch VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS imei_2 TEXT");
$conn->query("ALTER TABLE purchase_order_allocations ADD COLUMN IF NOT EXISTS imei_2 TEXT");
$conn->query("ALTER TABLE stock_on_hand ADD COLUMN IF NOT EXISTS imei2 VARCHAR(100) AFTER imei");

/**
 * Check if a PO item row was added during receive (emergency item, no allocation).
 */
function isReceiveAddedItem($conn, $po_id, $family_code, $item_no)
{
    $po_id = (int) $po_id;
    $item_no = (int) $item_no;
    $family_code_esc = $conn->real_escape_string($family_code);
    $check = $conn->query("SELECT is_receive_added FROM purchase_order_items 
                           WHERE po_id = {$po_id} AND family_code = '{$family_code_esc}' AND item_no = {$item_no} LIMIT 1");
    if ($check && $check->num_rows > 0) {
        return (int) $check->fetch_assoc()['is_receive_added'] === 1;
    }
    return false;
}

/**
 * Build WHERE clause to scope stock/receive processing to the current receiving branch.
 */
function buildBranchReceiveItemsFilter($conn, $receiving_branch)
{
    if (empty($receiving_branch)) {
        return '';
    }
    $receiving_branch_esc = $conn->real_escape_string($receiving_branch);
    return " AND (
        (COALESCE(poi.is_receive_added, 0) = 1 AND poi.receiving_branch = '{$receiving_branch_esc}')
        OR EXISTS (
            SELECT 1 FROM purchase_order_allocations poa
            WHERE poa.po_id = poi.po_id
            AND poa.branch_name = '{$receiving_branch_esc}'
            AND poa.family_code COLLATE utf8mb4_unicode_ci = poi.family_code COLLATE utf8mb4_unicode_ci
        )
    )";
}

// Enable error logging for debugging (but don't display errors to avoid breaking JSON)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Changed to 0 to not break JSON response
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_update_po_status.log');

// Log all incoming POST data
file_put_contents(__DIR__ . '/debug_update_po_status.log', "\n\n=== NEW REQUEST " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_update_po_status.log', "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$po_id = isset($_POST['po_id']) ? (int) $_POST['po_id'] : 0;
$new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
$serial_numbers_raw = isset($_POST['serial_numbers']) ? $_POST['serial_numbers'] : '';
$serial_numbers = !empty($serial_numbers_raw) ? json_decode($serial_numbers_raw, true) : [];
$serial_numbers_2_raw = isset($_POST['serial_numbers_2']) ? $_POST['serial_numbers_2'] : '';
$serial_numbers_2 = !empty($serial_numbers_2_raw) ? json_decode($serial_numbers_2_raw, true) : [];
$item_models = isset($_POST['item_models']) ? json_decode($_POST['item_models'], true) : [];
$item_types_raw = isset($_POST['item_types']) ? $_POST['item_types'] : '';
$item_types = !empty($item_types_raw) ? json_decode($_POST['item_types'], true) : [];
$invoice_number = isset($_POST['invoice_number']) ? trim($_POST['invoice_number']) : '';
$deleted_items_raw = isset($_POST['deleted_items']) ? $_POST['deleted_items'] : '';
$deleted_items = !empty($deleted_items_raw) ? json_decode($deleted_items_raw, true) : [];
$receiving_branch = isset($_POST['receiving_branch']) ? trim($_POST['receiving_branch']) : ''; // Specific branch receiving items

file_put_contents(__DIR__ . '/debug_update_po_status.log', "PO ID: {$po_id}\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_update_po_status.log', "New Status: {$new_status}\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_update_po_status.log', "Invoice Number: {$invoice_number}\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_update_po_status.log', "Receiving Branch: {$receiving_branch}\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_update_po_status.log', "Serial Numbers Raw: {$serial_numbers_raw}\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_update_po_status.log', "Serial Numbers Decoded: " . print_r($serial_numbers, true) . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_update_po_status.log', "Serial Numbers Count: " . count($serial_numbers) . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_update_po_status.log', "Serial Numbers 2 Raw: {$serial_numbers_2_raw}\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_update_po_status.log', "Serial Numbers 2 Decoded: " . print_r($serial_numbers_2, true) . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_update_po_status.log', "Item Models: " . print_r($item_models, true) . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_update_po_status.log', "Item Types: " . print_r($item_types, true) . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug_update_po_status.log', "Deleted Items: " . print_r($deleted_items, true) . "\n", FILE_APPEND);

$allowed = ['Received', 'CANCELED', 'Pending', 'Cancelled', 'Incomplete', 'Completed'];

if ($po_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
    exit;
}

if (!in_array($new_status, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

// Escape the status after validation
$new_status_esc = $conn->real_escape_string($new_status);

// Get current user name from session
$action_by = '';
if (!empty($_SESSION['fullname'])) {
    $action_by = $_SESSION['fullname'];
} elseif (!empty($_SESSION['username'])) {
    $action_by = $_SESSION['username'];
} else {
    $action_by = 'Unknown';
}
$action_by_esc = $conn->real_escape_string($action_by);
$action_at = date('Y-m-d H:i:s');

// Get user's branch information for workflow tracking with special handling for super admin
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$branch_code = '000'; // Default branch code

// Check if user is Super-Admin (has access to all branches)
if ($system_level === 'Super-Admin' || strtoupper($user_branch) === 'SUPERADMIN') {
    $branch_code = 'ALL'; // Use 'ALL' for super admin accounts
} elseif (!empty($user_branch)) {
    // Regular branch user - look up their specific branch code
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($user_branch) . "'");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_result = $branch_query->fetch_assoc();
        $branch_code = $branch_result['branch_code'];
    }
}

// Ensure the tracking columns exist (idempotent)
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS received_by  VARCHAR(150) DEFAULT NULL");
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS received_at  DATETIME     DEFAULT NULL");
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS received_by_branch VARCHAR(10) DEFAULT NULL");
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS declined_by  VARCHAR(150) DEFAULT NULL");
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS declined_at  DATETIME     DEFAULT NULL");
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS declined_by_branch VARCHAR(10) DEFAULT NULL");
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS completed_by VARCHAR(150) DEFAULT NULL");
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS completed_at DATETIME     DEFAULT NULL");
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS completed_by_branch VARCHAR(10) DEFAULT NULL");
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS incomplete_by VARCHAR(150) DEFAULT NULL");
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS incomplete_at DATETIME     DEFAULT NULL");
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS incomplete_by_branch VARCHAR(10) DEFAULT NULL");

// Start transaction for data consistency
$conn->begin_transaction();

try {
    // Delete marked items FIRST if provided
    if (!empty($deleted_items)) {
        file_put_contents(__DIR__ . '/debug_update_po_status.log', "=== DELETING MARKED ITEMS ===\n", FILE_APPEND);
        foreach ($deleted_items as $item_id) {
            $item_id = (int) $item_id;

            // Get item details before deletion (for stock removal if needed)
            $item_query = $conn->query("SELECT poi.*, i.has_serial 
                                       FROM purchase_order_items poi 
                                       LEFT JOIN items i ON poi.family_code = i.family_code 
                                       WHERE poi.id = {$item_id} AND poi.po_id = {$po_id}");

            if ($item_query && $item_query->num_rows > 0) {
                $item = $item_query->fetch_assoc();
                $family_code = $item['family_code'];
                $serial_number = $item['serial_number'] ?? '';
                $po_number = $item['po_number'];
                $has_serial = $item['has_serial'] == 1;

                file_put_contents(__DIR__ . '/debug_update_po_status.log', "Deleting item {$item_id}: FC={$family_code}, PO={$po_number}\n", FILE_APPEND);

                // If item has serial numbers, remove them from stock_on_hand
                if ($has_serial && !empty($serial_number)) {
                    // Parse serial numbers (can be newline or comma separated)
                    $serials = [];
                    if (strpos($serial_number, "\n") !== false) {
                        $serials = explode("\n", $serial_number);
                    } else {
                        $serials = explode(",", $serial_number);
                    }
                    $serials = array_filter(array_map('trim', $serials));

                    foreach ($serials as $serial) {
                        $serial_esc = $conn->real_escape_string($serial);
                        $delete_stock_sql = "DELETE FROM stock_on_hand WHERE imei = '{$serial_esc}' AND dr_number = '{$po_number}'";
                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Delete stock SQL: {$delete_stock_sql}\n", FILE_APPEND);

                        if (!$conn->query($delete_stock_sql)) {
                            file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR deleting stock: " . $conn->error . "\n", FILE_APPEND);
                        }
                    }
                } else {
                    // Non-serialized item, remove from stock by family_code and dr_number
                    $family_code_esc = $conn->real_escape_string($family_code);
                    $po_number_esc = $conn->real_escape_string($po_number);
                    $delete_stock_sql = "DELETE FROM stock_on_hand WHERE family_code = '{$family_code_esc}' AND dr_number = '{$po_number_esc}'";
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Delete stock SQL: {$delete_stock_sql}\n", FILE_APPEND);

                    if (!$conn->query($delete_stock_sql)) {
                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR deleting stock: " . $conn->error . "\n", FILE_APPEND);
                    }
                }

                // Delete from purchase_order_items
                $delete_item_sql = "DELETE FROM purchase_order_items WHERE id = {$item_id} AND po_id = {$po_id}";
                file_put_contents(__DIR__ . '/debug_update_po_status.log', "Delete item SQL: {$delete_item_sql}\n", FILE_APPEND);

                if (!$conn->query($delete_item_sql)) {
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR deleting item: " . $conn->error . "\n", FILE_APPEND);
                    throw new Exception("Failed to delete purchase order item: " . $conn->error);
                }

                file_put_contents(__DIR__ . '/debug_update_po_status.log', "Item {$item_id} deleted successfully\n", FILE_APPEND);
            }
        }
    }

    // Check current status to determine if items are already in stock
    $current_status_query = $conn->query("SELECT status FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
    $current_po_status = '';
    if ($current_status_query && $current_status_query->num_rows > 0) {
        $current_po_status = $current_status_query->fetch_assoc()['status'];
    }

    // Update item models FIRST if any were provided (before any status-specific logic)
    // This ensures item_model and item_description are available when adding items to stock
    if (!empty($item_models)) {
        file_put_contents(__DIR__ . '/debug_update_po_status.log', "=== UPDATING ITEM MODELS FIRST ===\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Receiving Branch Value: '" . $receiving_branch . "' (empty=" . (empty($receiving_branch) ? 'YES' : 'NO') . ")\n", FILE_APPEND);

        foreach ($item_models as $row_key => $item_data) {
            $family_code_esc = $conn->real_escape_string($item_data['family_code']);
            $item_no = (int) $item_data['item_no'];
            $new_item_model_esc = $conn->real_escape_string($item_data['item_model']);
            $new_item_description_esc = $conn->real_escape_string($item_data['item_description']);

            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Updating item model for row {$row_key}: Model={$new_item_model_esc}, Desc={$new_item_description_esc}\n", FILE_APPEND);

            // CRITICAL FIX: Always update branch-specific allocation table for allocated POs
            // Check if this PO has allocations - if yes, we should ONLY update allocations, not the shared items table
            $check_allocations = $conn->query("SELECT COUNT(*) as alloc_count FROM purchase_order_allocations WHERE po_id = {$po_id}");
            $has_allocations = false;
            if ($check_allocations) {
                $alloc_row = $check_allocations->fetch_assoc();
                $has_allocations = $alloc_row['alloc_count'] > 0;
            }

            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Has Allocations: " . ($has_allocations ? 'YES' : 'NO') . " (count: " . ($has_allocations ? $alloc_row['alloc_count'] : 0) . ")\n", FILE_APPEND);
            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Receiving Branch is empty: " . (empty($receiving_branch) ? 'YES' : 'NO') . "\n", FILE_APPEND);

            if ($has_allocations && !empty($receiving_branch) && !isReceiveAddedItem($conn, $po_id, $item_data['family_code'], $item_no)) {
                // PO has allocations AND specific branch provided - Update branch-specific allocation ONLY
                $receiving_branch_esc = $conn->real_escape_string($receiving_branch);

                // First, check what will be matched by this update
                $check_match_sql = "SELECT id, po_id, family_code, branch_name, item_model, item_description 
                                   FROM purchase_order_allocations 
                                   WHERE po_id = {$po_id} 
                                   AND family_code = '{$family_code_esc}'
                                   AND branch_name = '{$receiving_branch_esc}'";
                file_put_contents(__DIR__ . '/debug_update_po_status.log', "Check Match SQL: {$check_match_sql}\n", FILE_APPEND);

                $check_result = $conn->query($check_match_sql);
                if ($check_result && $check_result->num_rows > 0) {
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Found {$check_result->num_rows} matching allocation(s) to update:\n", FILE_APPEND);
                    while ($match_row = $check_result->fetch_assoc()) {
                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "  - ID: {$match_row['id']}, Branch: {$match_row['branch_name']}, FC: {$match_row['family_code']}, Old Model: {$match_row['item_model']}, Old Desc: {$match_row['item_description']}\n", FILE_APPEND);
                    }
                } else {
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "WARNING: No matching allocations found! This update will not affect any rows.\n", FILE_APPEND);
                }

                // Update the allocation table (branch-specific)
                $update_alloc_model_sql = "UPDATE purchase_order_allocations 
                                          SET item_model = '{$new_item_model_esc}',
                                              item_description = '{$new_item_description_esc}'
                                          WHERE po_id = {$po_id} 
                                          AND family_code = '{$family_code_esc}'
                                          AND branch_name = '{$receiving_branch_esc}'";

                file_put_contents(__DIR__ . '/debug_update_po_status.log', "Update allocation model SQL (branch-specific): {$update_alloc_model_sql}\n", FILE_APPEND);

                if (!$conn->query($update_alloc_model_sql)) {
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR updating allocation item model: " . $conn->error . "\n", FILE_APPEND);
                    throw new Exception("Failed to update allocation item model: " . $conn->error);
                }

                $affected_rows = $conn->affected_rows;
                file_put_contents(__DIR__ . '/debug_update_po_status.log', "Allocation item model updated (branch-specific), affected rows: {$affected_rows}\n", FILE_APPEND);

                // Now check what OTHER branches have for this same family_code (should NOT be changed)
                $check_other_branches_sql = "SELECT id, branch_name, item_model, item_description 
                                            FROM purchase_order_allocations 
                                            WHERE po_id = {$po_id} 
                                            AND family_code = '{$family_code_esc}'
                                            AND branch_name != '{$receiving_branch_esc}'";
                $check_other_result = $conn->query($check_other_branches_sql);
                if ($check_other_result && $check_other_result->num_rows > 0) {
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Other branches with same family_code (should NOT be changed):\n", FILE_APPEND);
                    while ($other_row = $check_other_result->fetch_assoc()) {
                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "  - ID: {$other_row['id']}, Branch: {$other_row['branch_name']}, Model: {$other_row['item_model']}, Desc: {$other_row['item_description']}\n", FILE_APPEND);
                    }
                }
            } elseif ($has_allocations && !empty($receiving_branch) && isReceiveAddedItem($conn, $po_id, $item_data['family_code'], $item_no)) {
                // Emergency receive-added item - update purchase_order_items directly
                $update_model_sql = "UPDATE purchase_order_items 
                                    SET item_model = '{$new_item_model_esc}',
                                        item_description = '{$new_item_description_esc}'
                                    WHERE po_id = {$po_id} 
                                    AND family_code = '{$family_code_esc}' 
                                    AND item_no = {$item_no}";

                if (!$conn->query($update_model_sql)) {
                    throw new Exception("Failed to update receive-added item model: " . $conn->error);
                }
            } elseif ($has_allocations && empty($receiving_branch)) {
                // PO has allocations but NO specific branch provided - This should not happen from purchaseorderreceive
                // Throw error to prevent accidental update to shared table
                file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR: Attempting to update allocated PO without specifying branch\n", FILE_APPEND);
                throw new Exception("Cannot update item model for allocated PO without specifying the receiving branch.");
            } else {
                // PO has NO allocations - update purchase_order_items (shared across all branches)
                // This is for POs that haven't been allocated yet
                $update_model_sql = "UPDATE purchase_order_items 
                                    SET item_model = '{$new_item_model_esc}',
                                        item_description = '{$new_item_description_esc}'
                                    WHERE po_id = {$po_id} 
                                    AND family_code = '{$family_code_esc}' 
                                    AND item_no = {$item_no}";

                file_put_contents(__DIR__ . '/debug_update_po_status.log', "Update model SQL (shared - no allocations): {$update_model_sql}\n", FILE_APPEND);

                if (!$conn->query($update_model_sql)) {
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR updating item model: " . $conn->error . "\n", FILE_APPEND);
                    throw new Exception("Failed to update item model: " . $conn->error);
                }

                $affected_rows = $conn->affected_rows;
                file_put_contents(__DIR__ . '/debug_update_po_status.log', "Item model updated (shared - no allocations), affected rows: {$affected_rows}\n", FILE_APPEND);
            }
        }
    }

    // Save item types to database if provided
    if (!empty($item_types)) {
        file_put_contents(__DIR__ . '/debug_update_po_status.log', "=== SAVING ITEM TYPES ===\n", FILE_APPEND);
        
        foreach ($item_types as $row_key => $item_type_data) {
            $family_code_esc = $conn->real_escape_string($item_type_data['family_code']);
            $item_no = (int) $item_type_data['item_no'];
            $types = $item_type_data['types']; // Array of serial_number => type
            
            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Processing item types for row {$row_key}\n", FILE_APPEND);
            
            // Delete existing entries for this item
            $delete_types_sql = "DELETE FROM purchase_order_serial_types 
                               WHERE po_id = $po_id 
                               AND family_code = '{$family_code_esc}' 
                               AND item_no = $item_no";
            $conn->query($delete_types_sql);
            
            // Insert new entries
            if (!empty($types)) {
                $insert_stmt = $conn->prepare("INSERT INTO purchase_order_serial_types 
                                               (po_id, family_code, item_no, serial_number, item_type) 
                                               VALUES (?, ?, ?, ?, ?)");
                
                foreach ($types as $serial_number => $item_type) {
                    $serial_number_escaped = $conn->real_escape_string($serial_number);
                    $item_type_escaped = $conn->real_escape_string($item_type);
                    
                    $insert_stmt->bind_param("isiss", 
                        $po_id, 
                        $family_code_esc, 
                        $item_no, 
                        $serial_number_escaped, 
                        $item_type_escaped
                    );
                    $insert_stmt->execute();
                    
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Saved type for serial {$serial_number}: {$item_type}\n", FILE_APPEND);
                }
                
                $insert_stmt->close();
            }
        }
    }

    // Build the UPDATE based on which action was taken
    if ($new_status === 'Received') {
        file_put_contents(__DIR__ . '/debug_update_po_status.log', "=== PROCESSING RECEIVED STATUS ===\n", FILE_APPEND);

        // Use the PO's own created_by_branch as the receiving branch so it
        // shows up in the correct branch's RD Delivery Report.
        $po_branch_query = $conn->query("SELECT created_by_branch, received_by FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
        $po_branch_code = $branch_code; // fallback to current user's branch
        $existing_received_by = null;
        if ($po_branch_query && $po_branch_query->num_rows > 0) {
            $po_branch_row = $po_branch_query->fetch_assoc();
            if (!empty($po_branch_row['created_by_branch'])) {
                $po_branch_code = $po_branch_row['created_by_branch'];
            }
            $existing_received_by = $po_branch_row['received_by'];
        }
        $po_branch_code_esc = $conn->real_escape_string($po_branch_code);

        // Also resolve the branch NAME for stock_on_hand insertions
        $po_branch_name = $user_branch; // fallback
        $po_bname_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '{$po_branch_code_esc}' LIMIT 1");
        if ($po_bname_query && $po_bname_query->num_rows > 0) {
            $po_branch_name = $po_bname_query->fetch_assoc()['branch_name'];
        }
        $po_branch_name_esc = $conn->real_escape_string($po_branch_name);

        // Escape invoice number for SQL
        $invoice_number_esc = $conn->real_escape_string($invoice_number);

        // IMPORTANT: Only update received_by, received_at, and received_by_branch if they are NULL
        // This preserves the original receiver information when Super Admin modifies the PO
        $sql = "UPDATE purchase_orders
                SET status = 'Received'";

        // Only set received_by fields if they haven't been set before (preserves workflow history)
        if (empty($existing_received_by)) {
            $sql .= ",
                    received_by = '{$action_by_esc}',
                    received_at = '{$action_at}',
                    received_by_branch = '{$po_branch_code_esc}'";
            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Setting received_by for first time: {$action_by_esc}\n", FILE_APPEND);
        } else {
            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Preserving existing received_by: {$existing_received_by}\n", FILE_APPEND);
        }

        // Add invoice_number to the UPDATE if it's provided
        if (!empty($invoice_number)) {
            $sql .= ",
                    invoice_number = '{$invoice_number_esc}'";
        }

        $sql .= " WHERE id = {$po_id}";

        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Update PO SQL: {$sql}\n", FILE_APPEND);

        $result = $conn->query($sql);
        if (!$result) {
            file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR updating PO: " . $conn->error . "\n", FILE_APPEND);
            throw new Exception("Failed to update PO status: " . $conn->error);
        }

        file_put_contents(__DIR__ . '/debug_update_po_status.log', "PO status updated successfully\n", FILE_APPEND);

        // Update serial numbers - CRITICAL FIX: Save to allocations table if receiving_branch is provided
        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Processing serial numbers for Received status\n", FILE_APPEND);

        // Group serial numbers by family_code and item_no
        $serials_by_row = [];
        if (!empty($serial_numbers)) {
            foreach ($serial_numbers as $serial_data) {
                $family_code = $serial_data['family_code'];
                $item_no = (int) $serial_data['item_no'];
                $row_key = $family_code . '-' . $item_no;

                if (!isset($serials_by_row[$row_key])) {
                    $serials_by_row[$row_key] = [];
                }
                $serials_by_row[$row_key][] = $serial_data['serial_number'];
            }
        }

        // Group IMEI 2 numbers by family_code and item_no
        $serials2_by_row = [];
        if (!empty($serial_numbers_2)) {
            foreach ($serial_numbers_2 as $s2_data) {
                $family_code = $s2_data['family_code'];
                $item_no = (int) $s2_data['item_no'];
                $row_key = $family_code . '-' . $item_no;

                if (!isset($serials2_by_row[$row_key])) {
                    $serials2_by_row[$row_key] = [];
                }
                $serials2_by_row[$row_key][] = $s2_data['imei_2'];
            }
        }

        // Check if PO has allocations
        $check_allocations = $conn->query("SELECT COUNT(*) as alloc_count FROM purchase_order_allocations WHERE po_id = {$po_id}");
        $has_allocations = false;
        if ($check_allocations) {
            $alloc_row = $check_allocations->fetch_assoc();
            $has_allocations = $alloc_row['alloc_count'] > 0;
        }

        // Fetch ALL items for this PO with their has_serial info
        $po_all_items = [];
        $items_check_q = $conn->query("
            SELECT poi.id, poi.item_no, poi.family_code, poi.item_model, poi.item_description,
                   COALESCE(MAX(CASE 
                       WHEN poi.item_model IS NOT NULL 
                           AND poi.item_model != '' 
                           AND poi.item_model != '-' 
                           AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                       THEN i.has_serial 
                       ELSE 0 
                   END), MAX(i.has_serial), 0) as has_serial,
                   COALESCE(MAX(CASE 
                       WHEN poi.item_model IS NOT NULL 
                           AND poi.item_model != '' 
                           AND poi.item_model != '-' 
                           AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                       THEN i.has_serial_2 
                       ELSE 0 
                   END), MAX(i.has_serial_2), 0) as has_serial_2,
                   COALESCE(MAX(CASE 
                       WHEN poi.item_model IS NOT NULL 
                           AND poi.item_model != '' 
                           AND poi.item_model != '-' 
                           AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                       THEN i.has_serial_number 
                       ELSE 0 
                   END), MAX(i.has_serial_number), 0) as has_serial_number
            FROM purchase_order_items poi
            LEFT JOIN items i ON (
                (poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci)
                OR (poi.family_code COLLATE utf8mb4_general_ci = i.family_code COLLATE utf8mb4_general_ci)
            ) AND i.status = 'Active'
            WHERE poi.po_id = {$po_id}
            GROUP BY poi.id
            ORDER BY poi.item_no ASC
        ");
        if ($items_check_q) {
            while ($r = $items_check_q->fetch_assoc()) {
                $po_all_items[] = $r;
            }
        }

        // Update each item row with its serial numbers (or clear if unserialized/no serials)
        foreach ($po_all_items as $item_info) {
            $family_code = $item_info['family_code'];
            $family_code_esc = $conn->real_escape_string($family_code);
            $item_no = (int) $item_info['item_no'];
            $item_model = $item_info['item_model'] ?? '';
            $item_model_esc = $conn->real_escape_string($item_model);
            $has_serial = ((int) ($item_info['has_serial'] ?? 0) == 1 || (int) ($item_info['has_serial_number'] ?? 0) == 1);
            $row_key = $family_code . '-' . $item_no;

            $serial_sql_val = "NULL";
            if (isset($serials_by_row[$row_key]) && !empty($serials_by_row[$row_key])) {
                $clean_serials = array_values(array_filter(array_map('trim', $serials_by_row[$row_key])));
                if (!empty($clean_serials)) {
                    $serial_str = implode("\n", $clean_serials);
                    $serial_sql_val = "'" . $conn->real_escape_string($serial_str) . "'";
                }
            }

            $serial2_sql_val = "NULL";
            if (isset($serials2_by_row[$row_key]) && !empty($serials2_by_row[$row_key])) {
                $clean_s2 = array_values(array_filter(array_map('trim', $serials2_by_row[$row_key])));
                if (!empty($clean_s2)) {
                    $s2_str = implode("\n", $clean_s2);
                    $serial2_sql_val = "'" . $conn->real_escape_string($s2_str) . "'";
                }
            }

            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Updating row {$row_key} (has_serial=" . ($has_serial ? 1 : 0) . ") with serials: {$serial_sql_val}, imei2: {$serial2_sql_val}\n", FILE_APPEND);

            if ($has_allocations && !empty($receiving_branch) && !isReceiveAddedItem($conn, $po_id, $family_code, $item_no)) {
                $receiving_branch_esc = $conn->real_escape_string($receiving_branch);

                $model_where = "";
                if (!empty($item_model_esc)) {
                    $model_where = " AND (item_model = '{$item_model_esc}' OR item_model IS NULL OR item_model = '' OR item_model = '-')";
                }

                $update_alloc_serial_sql = "UPDATE purchase_order_allocations 
                                           SET serial_number = {$serial_sql_val},
                                               imei_2 = {$serial2_sql_val},
                                               item_model = IF(item_model IS NULL OR item_model = '' OR item_model = '-', '{$item_model_esc}', item_model)
                                           WHERE po_id = {$po_id} 
                                           AND family_code COLLATE utf8mb4_general_ci = '{$family_code_esc}' 
                                           AND branch_name COLLATE utf8mb4_general_ci = '{$receiving_branch_esc}'{$model_where}";

                if (!$conn->query($update_alloc_serial_sql)) {
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR updating allocation serial: " . $conn->error . "\n", FILE_APPEND);
                }
            } else {
                $update_item_sql = "UPDATE purchase_order_items 
                                   SET serial_number = {$serial_sql_val},
                                       imei_2 = {$serial2_sql_val} 
                                   WHERE po_id = {$po_id} AND family_code COLLATE utf8mb4_general_ci = '{$family_code_esc}' AND item_no = {$item_no}";
                if (!$conn->query($update_item_sql)) {
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR updating item serial: " . $conn->error . "\n", FILE_APPEND);
                }
            }
        }

        // Add items to stock for Received status
        // Note: Completed status does NOT add items to stock, so we need to add them when transitioning to Received
        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Adding items to stock\n", FILE_APPEND);

        // IMPORTANT: Re-fetch items AFTER item models have been updated to get the latest item_model and item_description
        // Use family_code join to get has_serial info
        $branch_items_filter = buildBranchReceiveItemsFilter($conn, $receiving_branch);
        $items_query = "SELECT poi.*,
                               COALESCE(MAX(CASE
                                   WHEN poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-'
                                        AND poi.item_model = i.item_code
                                   THEN i.has_serial ELSE 0 END), 0) as has_serial
                       FROM purchase_order_items poi
                       LEFT JOIN items i ON poi.family_code = i.family_code AND i.status = 'Active'
                       WHERE poi.po_id = {$po_id}{$branch_items_filter}
                       GROUP BY poi.id
                       ORDER BY poi.item_no ASC";

        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Items query: {$items_query}\n", FILE_APPEND);

        $items_result = $conn->query($items_query);

        if ($items_result && $items_result->num_rows > 0) {
            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Found " . $items_result->num_rows . " items to add to stock\n", FILE_APPEND);

            while ($item = $items_result->fetch_assoc()) {
                file_put_contents(__DIR__ . '/debug_update_po_status.log', "Processing item for stock: " . print_r($item, true) . "\n", FILE_APPEND);

                // Use item_model and item_description from purchase_order_items (which should be updated by now)
                $item_model = $conn->real_escape_string($item['item_model'] ?? '');
                $item_description = $conn->real_escape_string($item['item_description'] ?? '');

                // If item_model is empty, try to get it from items table using family_code
                if (empty($item_model)) {
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Item model is empty, looking up from items table\n", FILE_APPEND);
                    $family_code = $item['family_code'];
                    $lookup_query = "SELECT item_code, description FROM items WHERE family_code = '{$conn->real_escape_string($family_code)}' LIMIT 1";
                    $lookup_result = $conn->query($lookup_query);
                    if ($lookup_result && $lookup_result->num_rows > 0) {
                        $lookup_item = $lookup_result->fetch_assoc();
                        $item_model = $conn->real_escape_string($lookup_item['item_code']);
                        $item_description = $conn->real_escape_string($lookup_item['description']);
                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Found from items table: Model={$item_model}, Desc={$item_description}\n", FILE_APPEND);
                    }
                }

                $quantity = (int) $item['quantity'];
                $po_number = $conn->real_escape_string($item['po_number']);
                $family_code = $conn->real_escape_string($item['family_code']);
                $item_no = (int) $item['item_no'];
                $has_serial = $item['has_serial'] == 1;

                file_put_contents(__DIR__ . '/debug_update_po_status.log', "Item details - Model: {$item_model}, Desc: {$item_description}, FC: {$family_code}, ItemNo: {$item_no}, HasSerial: " . ($has_serial ? 'YES' : 'NO') . "\n", FILE_APPEND);

                if ($has_serial && !empty($serial_numbers)) {
                    // Handle serialized items - create individual entries for each serial number
                    // Match by both family_code and item_no for row-specific serial numbers
                    $item_serials = array_values(array_filter($serial_numbers, function ($sn) use ($family_code, $item_no) {
                        return isset($sn['family_code']) && $sn['family_code'] === $family_code
                            && isset($sn['item_no']) && (int) $sn['item_no'] === $item_no;
                    }));

                    $item_serials_2 = array_values(array_filter($serial_numbers_2, function ($sn) use ($family_code, $item_no) {
                        return isset($sn['family_code']) && $sn['family_code'] === $family_code
                            && isset($sn['item_no']) && (int) $sn['item_no'] === $item_no;
                    }));

                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Filtered serials for stock insertion: " . print_r($item_serials, true) . "\n", FILE_APPEND);
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Filtered serials 2 for stock insertion: " . print_r($item_serials_2, true) . "\n", FILE_APPEND);

                    // Load item types for this item from database
                    $serial_types = [];
                    $types_query = $conn->query("SELECT serial_number, item_type FROM purchase_order_serial_types 
                                                WHERE po_id = {$po_id} 
                                                AND family_code = '{$family_code}' 
                                                AND item_no = {$item_no}");
                    if ($types_query && $types_query->num_rows > 0) {
                        while ($type_row = $types_query->fetch_assoc()) {
                            $serial_types[$type_row['serial_number']] = $type_row['item_type'];
                        }
                    }
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Loaded serial types: " . print_r($serial_types, true) . "\n", FILE_APPEND);

                    foreach ($item_serials as $idx => $serial_data) {
                        $serial_number = $conn->real_escape_string($serial_data['serial_number']);
                        $serial_number_2 = isset($item_serials_2[$idx]['imei_2']) ? $conn->real_escape_string($item_serials_2[$idx]['imei_2']) : '';
                        $imei2_sql = !empty($serial_number_2) ? "'{$serial_number_2}'" : "NULL";
                        
                        // Get the type for this serial number, default to 'Good Stock'
                        $item_status = isset($serial_types[$serial_data['serial_number']]) ? $serial_types[$serial_data['serial_number']] : 'Good Stock';
                        $item_status_esc = $conn->real_escape_string($item_status);

                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Adding serial to stock: {$serial_number} (imei2: {$serial_number_2}) with status: {$item_status}\n", FILE_APPEND);

                        // Check if this serial number already exists in stock_on_hand (to prevent duplicates)
                        $duplicate_check = $conn->query("SELECT id FROM stock_on_hand WHERE imei = '{$serial_number}' LIMIT 1");
                        if ($duplicate_check && $duplicate_check->num_rows > 0) {
                            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Serial {$serial_number} already exists in stock, skipping\n", FILE_APPEND);
                            continue; // Skip this serial, it's already in stock
                        }

                        $stock_sql = "INSERT INTO stock_on_hand 
                                      (item_code, description, item_type, imei, imei2, dr_number, branch, 
                                       dr_date, system_entry_date, status, quantity, family_code)
                                      VALUES 
                                      ('{$item_model}', '{$item_description}', 'IMEI', 
                                       '{$serial_number}', {$imei2_sql}, '{$po_number}', '{$po_branch_name_esc}', 
                                       '{$action_at}', '{$action_at}', '{$item_status_esc}', 1, '{$family_code}')";

                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Stock SQL: {$stock_sql}\n", FILE_APPEND);

                        if (!$conn->query($stock_sql)) {
                            file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR adding to stock: " . $conn->error . "\n", FILE_APPEND);
                            throw new Exception("Failed to add serialized item to stock: " . $conn->error);
                        }

                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Serial added to stock successfully with status: {$item_status}\n", FILE_APPEND);
                    }

                } else {
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Non-serialized item (has_serial=0), adding to stock as accessories\n", FILE_APPEND);

                    // Check if this specific item_code already exists in stock for this PO (to prevent duplicates)
                    // Must use item_code (not family_code) so IPHONE-15-128GB-CASE and IPHONE-16-128GB-CASE are separate
                    $item_code_for_dup = $conn->real_escape_string($item['item_model'] ?? '');
                    $dup_query = !empty($item_code_for_dup)
                        ? "SELECT id FROM stock_on_hand WHERE item_code = '{$item_code_for_dup}' AND dr_number = '{$po_number}' LIMIT 1"
                        : "SELECT id FROM stock_on_hand WHERE family_code = '{$family_code}' AND dr_number = '{$po_number}' AND item_code = '{$item_model}' LIMIT 1";
                    $duplicate_check = $conn->query($dup_query);
                    if ($duplicate_check && $duplicate_check->num_rows > 0) {
                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Item {$item_model} and PO {$po_number} already exists in stock, skipping\n", FILE_APPEND);
                        continue; // Skip this item, it's already in stock
                    }

                    // Handle non-serialized items - create quantity-based entry
                    $stock_sql = "INSERT INTO stock_on_hand 
                                  (item_code, description, item_type, dr_number, branch, 
                                   dr_date, system_entry_date, status, quantity, family_code)
                                  VALUES 
                                  ('{$item_model}', '{$item_description}', 'Accessories', 
                                   '{$po_number}', '{$po_branch_name_esc}', 
                                   '{$action_at}', '{$action_at}', 'Good Stock', {$quantity}, '{$family_code}')";

                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Stock SQL: {$stock_sql}\n", FILE_APPEND);

                    if (!$conn->query($stock_sql)) {
                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR adding to stock: " . $conn->error . "\n", FILE_APPEND);
                        throw new Exception("Failed to add item to stock: " . $conn->error);
                    }

                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Item added to stock successfully\n", FILE_APPEND);
                }
            }
        } else {
            file_put_contents(__DIR__ . '/debug_update_po_status.log', "No items found for adding to stock\n", FILE_APPEND);
        }

        // Update purchase_order_allocations with received quantities and invoice number
        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Updating purchase_order_allocations with received quantities and invoice number\n", FILE_APPEND);

        // Check if purchase_order_allocations table exists
        $allocations_check = $conn->query("SHOW TABLES LIKE 'purchase_order_allocations'");
        if ($allocations_check && $allocations_check->num_rows > 0) {
            $received_by_username = isset($_SESSION['username']) ? $conn->real_escape_string($_SESSION['username']) : '';
            $invoice_number_esc = !empty($invoice_number) ? $conn->real_escape_string($invoice_number) : '';
            $receiving_branch_esc = !empty($receiving_branch) ? $conn->real_escape_string($receiving_branch) : '';

            foreach ($po_all_items as $item_info) {
                $family_code = $item_info['family_code'];
                $family_code_esc = $conn->real_escape_string($family_code);
                $item_no = (int) $item_info['item_no'];
                $item_model = $item_info['item_model'] ?? '';
                $item_model_esc = $conn->real_escape_string($item_model);
                $has_serial = ((int) $item_info['has_serial'] == 1);
                $row_key = $family_code . '-' . $item_no;

                if ($has_serial) {
                    $rec_qty = 0;
                    if (isset($serials_by_row[$row_key]) && !empty($serials_by_row[$row_key])) {
                        $clean_serials = array_values(array_filter(array_map('trim', $serials_by_row[$row_key])));
                        $rec_qty = count($clean_serials);
                    }
                } else {
                    // Unserialized items: use allocated quantity for this specific item_model
                    // MUST filter by item_model to avoid grabbing the serialized item's qty (e.g., 1 instead of 15)
                    $alloc_model_where_q = !empty($item_model_esc)
                        ? " AND item_model = '{$item_model_esc}'"
                        : " AND (item_model IS NULL OR item_model = '' OR item_model = '-')";
                    $alloc_qty_res = $conn->query("SELECT quantity FROM purchase_order_allocations WHERE po_id = {$po_id} AND family_code = '{$family_code_esc}' AND branch_name = '{$receiving_branch_esc}'{$alloc_model_where_q} LIMIT 1");
                    if ($alloc_qty_res && $alloc_qty_res->num_rows > 0) {
                        $alloc_q_row = $alloc_qty_res->fetch_assoc();
                        $rec_qty = (int) $alloc_q_row['quantity'];
                    } else {
                        // Fallback: get from purchase_order_items by item_no
                        $item_qty_res = $conn->query("SELECT quantity FROM purchase_order_items WHERE po_id = {$po_id} AND family_code = '{$family_code_esc}' AND item_no = {$item_no} LIMIT 1");
                        $item_q_row = $item_qty_res ? $item_qty_res->fetch_assoc() : null;
                        $rec_qty = $item_q_row ? (int) $item_q_row['quantity'] : 0;
                    }
                }

                if (!empty($receiving_branch)) {
                    $model_where = !empty($item_model_esc) ? " AND (item_model = '{$item_model_esc}' OR item_model IS NULL OR item_model = '' OR item_model = '-')" : "";

                    $update_alloc_sql = "UPDATE purchase_order_allocations 
                                        SET received_qty = {$rec_qty}";
                    if (!empty($invoice_number_esc)) {
                        $update_alloc_sql .= ", invoice_number = '{$invoice_number_esc}'";
                    }
                    if (!empty($received_by_username)) {
                        $update_alloc_sql .= ", received_by = '{$received_by_username}'";
                    }
                    $update_alloc_sql .= ", received_at = NOW()";
                    $update_alloc_sql .= " WHERE po_id = {$po_id} AND family_code = '{$family_code_esc}' AND branch_name = '{$receiving_branch_esc}'{$model_where}";

                    $conn->query($update_alloc_sql);
                }

                $update_item_rec_sql = "UPDATE purchase_order_items SET received_qty = {$rec_qty} WHERE po_id = {$po_id} AND family_code = '{$family_code_esc}' AND item_no = {$item_no}";
                $conn->query($update_item_rec_sql);
            }
        }

    } elseif ($new_status === 'Incomplete') {
        // Handle Incomplete status - save serial numbers AND add received items to stock
        $sql = "UPDATE purchase_orders
                SET status = 'Incomplete',
                    incomplete_by = '{$action_by_esc}',
                    incomplete_at = '{$action_at}',
                    incomplete_by_branch = '{$branch_code}'";
        if (!empty($invoice_number)) {
            $invoice_number_esc = $conn->real_escape_string($invoice_number);
            $sql .= ", invoice_number = '{$invoice_number_esc}'";
        }
        $sql .= " WHERE id = {$po_id}";

        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Failed to update PO status: " . $conn->error);
        }

        // Group serial numbers by family_code and item_no
        $serials_by_row = [];
        if (!empty($serial_numbers)) {
            foreach ($serial_numbers as $serial_data) {
                $family_code = $serial_data['family_code'];
                $item_no = (int) $serial_data['item_no'];
                $row_key = $family_code . '-' . $item_no;

                if (!isset($serials_by_row[$row_key])) {
                    $serials_by_row[$row_key] = [];
                }
                $serials_by_row[$row_key][] = $serial_data['serial_number'];
            }
        }

        // Group IMEI 2 numbers by family_code and item_no
        $serials2_by_row = [];
        if (!empty($serial_numbers_2)) {
            foreach ($serial_numbers_2 as $s2_data) {
                $family_code = $s2_data['family_code'];
                $item_no = (int) $s2_data['item_no'];
                $row_key = $family_code . '-' . $item_no;

                if (!isset($serials2_by_row[$row_key])) {
                    $serials2_by_row[$row_key] = [];
                }
                $serials2_by_row[$row_key][] = $s2_data['imei_2'];
            }
        }

        // Check if PO has allocations
        $check_allocations = $conn->query("SELECT COUNT(*) as alloc_count FROM purchase_order_allocations WHERE po_id = {$po_id}");
        $has_allocations = false;
        if ($check_allocations) {
            $alloc_row = $check_allocations->fetch_assoc();
            $has_allocations = $alloc_row['alloc_count'] > 0;
        }

        // Fetch ALL items for this PO with their has_serial info
        $po_all_items = [];
        $items_check_q = $conn->query("
            SELECT poi.id, poi.item_no, poi.family_code, poi.item_model, poi.item_description,
                   COALESCE(MAX(CASE 
                       WHEN poi.item_model IS NOT NULL 
                           AND poi.item_model != '' 
                           AND poi.item_model != '-' 
                           AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                       THEN i.has_serial 
                       ELSE 0 
                   END), MAX(i.has_serial), 0) as has_serial,
                   COALESCE(MAX(CASE 
                       WHEN poi.item_model IS NOT NULL 
                           AND poi.item_model != '' 
                           AND poi.item_model != '-' 
                           AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                       THEN i.has_serial_2 
                       ELSE 0 
                   END), MAX(i.has_serial_2), 0) as has_serial_2,
                   COALESCE(MAX(CASE 
                       WHEN poi.item_model IS NOT NULL 
                           AND poi.item_model != '' 
                           AND poi.item_model != '-' 
                           AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                       THEN i.has_serial_number 
                       ELSE 0 
                   END), MAX(i.has_serial_number), 0) as has_serial_number
            FROM purchase_order_items poi
            LEFT JOIN items i ON (
                (poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci)
                OR (poi.family_code COLLATE utf8mb4_general_ci = i.family_code COLLATE utf8mb4_general_ci)
            ) AND i.status = 'Active'
            WHERE poi.po_id = {$po_id}
            GROUP BY poi.id
            ORDER BY poi.item_no ASC
        ");
        if ($items_check_q) {
            while ($r = $items_check_q->fetch_assoc()) {
                $po_all_items[] = $r;
            }
        }

        // Update each item row with its serial numbers and add to stock
        foreach ($po_all_items as $item_info) {
            $family_code = $item_info['family_code'];
            $family_code_esc = $conn->real_escape_string($family_code);
            $item_no = (int) $item_info['item_no'];
            $item_model = $item_info['item_model'] ?? '';
            $item_model_esc = $conn->real_escape_string($item_model);
            $item_description = $item_info['item_description'] ?? '';
            $item_description_esc = $conn->real_escape_string($item_description);
            $has_serial = ((int) ($item_info['has_serial'] ?? 0) == 1 || (int) ($item_info['has_serial_number'] ?? 0) == 1);
            $row_key = $family_code . '-' . $item_no;

            $serial_sql_val = "NULL";
            $clean_serials = [];
            if (isset($serials_by_row[$row_key]) && !empty($serials_by_row[$row_key])) {
                $clean_serials = array_values(array_filter(array_map('trim', $serials_by_row[$row_key])));
                if (!empty($clean_serials)) {
                    $serial_str = implode("\n", $clean_serials);
                    $serial_sql_val = "'" . $conn->real_escape_string($serial_str) . "'";
                }
            }

            $serial2_sql_val = "NULL";
            $clean_s2 = [];
            if (isset($serials2_by_row[$row_key]) && !empty($serials2_by_row[$row_key])) {
                $clean_s2 = array_values(array_filter(array_map('trim', $serials2_by_row[$row_key])));
                if (!empty($clean_s2)) {
                    $s2_str = implode("\n", $clean_s2);
                    $serial2_sql_val = "'" . $conn->real_escape_string($s2_str) . "'";
                }
            }

            // Insert into stock_on_hand for serialized items
            if ($has_serial && !empty($clean_serials)) {
                foreach ($clean_serials as $idx => $serial_number) {
                    $serial_number_esc = $conn->real_escape_string($serial_number);
                    $s2_val = isset($clean_s2[$idx]) ? $conn->real_escape_string($clean_s2[$idx]) : '';
                    $imei2_sql = !empty($s2_val) ? "'{$s2_val}'" : "NULL";

                    $duplicate_check = $conn->query("SELECT id FROM stock_on_hand WHERE imei = '{$serial_number_esc}' LIMIT 1");
                    if ($duplicate_check && $duplicate_check->num_rows > 0) {
                        continue;
                    }

                    $stock_sql = "INSERT INTO stock_on_hand 
                                  (item_code, description, item_type, imei, imei2, dr_number, branch, 
                                   dr_date, system_entry_date, status, quantity, family_code)
                                  VALUES 
                                  ('{$item_model_esc}', '{$item_description_esc}', 'IMEI', 
                                   '{$serial_number_esc}', {$imei2_sql}, '{$po_number}', '{$user_branch}', 
                                   '{$action_at}', '{$action_at}', 'Good Stock', 1, '{$family_code_esc}')";

                    if (!$conn->query($stock_sql)) {
                        throw new Exception("Failed to add serialized item to stock: " . $conn->error);
                    }
                }
            }

            if ($has_allocations && !empty($receiving_branch) && !isReceiveAddedItem($conn, $po_id, $family_code, $item_no)) {
                $receiving_branch_esc = $conn->real_escape_string($receiving_branch);
                $model_where = !empty($item_model_esc) ? " AND (item_model = '{$item_model_esc}' OR item_model IS NULL OR item_model = '' OR item_model = '-')" : "";

                $update_alloc_serial_sql = "UPDATE purchase_order_allocations 
                                           SET serial_number = {$serial_sql_val},
                                               imei_2 = {$serial2_sql_val},
                                               item_model = IF(item_model IS NULL OR item_model = '' OR item_model = '-', '{$item_model_esc}', item_model)
                                           WHERE po_id = {$po_id} 
                                           AND family_code COLLATE utf8mb4_general_ci = '{$family_code_esc}' 
                                           AND branch_name COLLATE utf8mb4_general_ci = '{$receiving_branch_esc}'{$model_where}";

                if (!$conn->query($update_alloc_serial_sql)) {
                    throw new Exception("Failed to update allocation serial numbers: " . $conn->error);
                }
            } else {
                $update_item_sql = "UPDATE purchase_order_items 
                                   SET serial_number = {$serial_sql_val},
                                       imei_2 = {$serial2_sql_val} 
                                   WHERE po_id = {$po_id} AND family_code COLLATE utf8mb4_general_ci = '{$family_code_esc}' AND item_no = {$item_no}";

                if (!$conn->query($update_item_sql)) {
                    throw new Exception("Failed to update item serial numbers: " . $conn->error);
                }
            }
        }

        // Update purchase_order_allocations with received quantities and invoice number for Incomplete status
        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Updating purchase_order_allocations for Incomplete status\n", FILE_APPEND);

        // Check if purchase_order_allocations table exists
        $allocations_check = $conn->query("SHOW TABLES LIKE 'purchase_order_allocations'");
        if ($allocations_check && $allocations_check->num_rows > 0) {
            $received_by_username = isset($_SESSION['username']) ? $conn->real_escape_string($_SESSION['username']) : '';
            $invoice_number_esc = !empty($invoice_number) ? $conn->real_escape_string($invoice_number) : '';
            $receiving_branch_esc = !empty($receiving_branch) ? $conn->real_escape_string($receiving_branch) : '';

            foreach ($po_all_items as $item_info) {
                $family_code = $item_info['family_code'];
                $family_code_esc = $conn->real_escape_string($family_code);
                $item_no = (int) $item_info['item_no'];
                $item_model = $item_info['item_model'] ?? '';
                $item_model_esc = $conn->real_escape_string($item_model);
                $has_serial = ((int) $item_info['has_serial'] == 1);
                $row_key = $family_code . '-' . $item_no;

                if ($has_serial) {
                    $rec_qty = 0;
                    if (isset($serials_by_row[$row_key]) && !empty($serials_by_row[$row_key])) {
                        $clean_serials = array_values(array_filter(array_map('trim', $serials_by_row[$row_key])));
                        $rec_qty = count($clean_serials);
                    }
                } else {
                    // Unserialized items: preserve existing received_qty (do not force full quantity for Incomplete)
                    $alloc_model_where_q = !empty($item_model_esc)
                        ? " AND item_model = '{$item_model_esc}'"
                        : " AND (item_model IS NULL OR item_model = '' OR item_model = '-')";
                    $alloc_qty_res = $conn->query("SELECT received_qty, quantity FROM purchase_order_allocations WHERE po_id = {$po_id} AND family_code = '{$family_code_esc}' AND branch_name = '{$receiving_branch_esc}'{$alloc_model_where_q} LIMIT 1");
                    if ($alloc_qty_res && $alloc_qty_res->num_rows > 0) {
                        $alloc_q_row = $alloc_qty_res->fetch_assoc();
                        $rec_qty = (isset($alloc_q_row['received_qty']) && $alloc_q_row['received_qty'] !== null) ? (int)$alloc_q_row['received_qty'] : 0;
                    } else {
                        // Fallback: get from purchase_order_items by item_no
                        $item_qty_res = $conn->query("SELECT received_qty FROM purchase_order_items WHERE po_id = {$po_id} AND family_code = '{$family_code_esc}' AND item_no = {$item_no} LIMIT 1");
                        $item_q_row = $item_qty_res ? $item_qty_res->fetch_assoc() : null;
                        $rec_qty = ($item_q_row && isset($item_q_row['received_qty']) && $item_q_row['received_qty'] !== null) ? (int) $item_q_row['received_qty'] : 0;
                    }
                }

                if (!empty($receiving_branch)) {
                    $model_where = !empty($item_model_esc) ? " AND (item_model = '{$item_model_esc}' OR item_model IS NULL OR item_model = '' OR item_model = '-')" : "";

                    $update_alloc_sql = "UPDATE purchase_order_allocations 
                                            SET received_qty = {$rec_qty}";
                    if (!empty($invoice_number_esc)) {
                        $update_alloc_sql .= ", invoice_number = '{$invoice_number_esc}'";
                    }
                    if (!empty($received_by_username)) {
                        $update_alloc_sql .= ", received_by = '{$received_by_username}'";
                    }
                    $update_alloc_sql .= ", received_at = NOW()";
                    $update_alloc_sql .= " WHERE po_id = {$po_id} AND family_code = '{$family_code_esc}' AND branch_name = '{$receiving_branch_esc}'{$model_where}";

                    $conn->query($update_alloc_sql);
                }

                $update_item_rec_sql = "UPDATE purchase_order_items SET received_qty = {$rec_qty} WHERE po_id = {$po_id} AND family_code = '{$family_code_esc}' AND item_no = {$item_no}";
                $conn->query($update_item_rec_sql);
            }
        }

    } elseif ($new_status === 'Completed') {
        file_put_contents(__DIR__ . '/debug_update_po_status.log', "=== PROCESSING COMPLETED STATUS ===\n", FILE_APPEND);

        // Handle Completed status - save serial numbers and update PO status
        $sql = "UPDATE purchase_orders
                SET status      = 'Completed',
                    completed_by = '{$action_by_esc}',
                    completed_at = '{$action_at}',
                    completed_by_branch = '{$branch_code}'
                WHERE id = {$po_id}";

        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Update PO SQL: {$sql}\n", FILE_APPEND);

        $result = $conn->query($sql);
        if (!$result) {
            file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR updating PO: " . $conn->error . "\n", FILE_APPEND);
            throw new Exception("Failed to update PO status: " . $conn->error);
        }

        file_put_contents(__DIR__ . '/debug_update_po_status.log', "PO status updated successfully\n", FILE_APPEND);

        // Group serial numbers by family_code and item_no
        $serials_by_row = [];
        if (!empty($serial_numbers)) {
            foreach ($serial_numbers as $serial_data) {
                $family_code = $serial_data['family_code'];
                $item_no = (int) $serial_data['item_no'];
                $row_key = $family_code . '-' . $item_no;

                if (!isset($serials_by_row[$row_key])) {
                    $serials_by_row[$row_key] = [];
                }
                $serials_by_row[$row_key][] = $serial_data['serial_number'];
            }
        }

        // Group IMEI 2 numbers by family_code and item_no
        $serials2_by_row = [];
        if (!empty($serial_numbers_2)) {
            foreach ($serial_numbers_2 as $s2_data) {
                $family_code = $s2_data['family_code'];
                $item_no = (int) $s2_data['item_no'];
                $row_key = $family_code . '-' . $item_no;

                if (!isset($serials2_by_row[$row_key])) {
                    $serials2_by_row[$row_key] = [];
                }
                $serials2_by_row[$row_key][] = $s2_data['imei_2'];
            }
        }

        // Check if PO has allocations
        $check_allocations = $conn->query("SELECT COUNT(*) as alloc_count FROM purchase_order_allocations WHERE po_id = {$po_id}");
        $has_allocations = false;
        if ($check_allocations) {
            $alloc_row = $check_allocations->fetch_assoc();
            $has_allocations = $alloc_row['alloc_count'] > 0;
        }

        // Fetch ALL items for this PO with their has_serial info
        $po_all_items = [];
        $items_check_q = $conn->query("
            SELECT poi.id, poi.item_no, poi.family_code, poi.item_model, poi.item_description, poi.quantity,
                   COALESCE(MAX(CASE 
                       WHEN poi.item_model IS NOT NULL 
                           AND poi.item_model != '' 
                           AND poi.item_model != '-' 
                           AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                       THEN i.has_serial 
                       ELSE 0 
                   END), MAX(i.has_serial), 0) as has_serial,
                   COALESCE(MAX(CASE 
                       WHEN poi.item_model IS NOT NULL 
                           AND poi.item_model != '' 
                           AND poi.item_model != '-' 
                           AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                       THEN i.has_serial_2 
                       ELSE 0 
                   END), MAX(i.has_serial_2), 0) as has_serial_2,
                   COALESCE(MAX(CASE 
                       WHEN poi.item_model IS NOT NULL 
                           AND poi.item_model != '' 
                           AND poi.item_model != '-' 
                           AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                       THEN i.has_serial_number 
                       ELSE 0 
                   END), MAX(i.has_serial_number), 0) as has_serial_number
            FROM purchase_order_items poi
            LEFT JOIN items i ON (
                (poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci)
                OR (poi.family_code COLLATE utf8mb4_general_ci = i.family_code COLLATE utf8mb4_general_ci)
            ) AND i.status = 'Active'
            WHERE poi.po_id = {$po_id}
            GROUP BY poi.id
            ORDER BY poi.item_no ASC
        ");
        if ($items_check_q) {
            while ($r = $items_check_q->fetch_assoc()) {
                $po_all_items[] = $r;
            }
        }

        // Update each item row with its serial numbers
        foreach ($po_all_items as $item_info) {
            $family_code = $item_info['family_code'];
            $family_code_esc = $conn->real_escape_string($family_code);
            $item_no = (int) $item_info['item_no'];
            $item_model = $item_info['item_model'] ?? '';
            $item_model_esc = $conn->real_escape_string($item_model);
            $has_serial = ((int) ($item_info['has_serial'] ?? 0) == 1 || (int) ($item_info['has_serial_number'] ?? 0) == 1);
            $row_key = $family_code . '-' . $item_no;

            $serial_sql_val = "NULL";
            if (isset($serials_by_row[$row_key]) && !empty($serials_by_row[$row_key])) {
                $clean_serials = array_values(array_filter(array_map('trim', $serials_by_row[$row_key])));
                if (!empty($clean_serials)) {
                    $serial_str = implode("\n", $clean_serials);
                    $serial_sql_val = "'" . $conn->real_escape_string($serial_str) . "'";
                }
            }

            $serial2_sql_val = "NULL";
            if (isset($serials2_by_row[$row_key]) && !empty($serials2_by_row[$row_key])) {
                $clean_s2 = array_values(array_filter(array_map('trim', $serials2_by_row[$row_key])));
                if (!empty($clean_s2)) {
                    $s2_str = implode("\n", $clean_s2);
                    $serial2_sql_val = "'" . $conn->real_escape_string($s2_str) . "'";
                }
            }

            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Updating row {$row_key} (has_serial=" . ($has_serial ? 1 : 0) . ") with serials: {$serial_sql_val}, imei2: {$serial2_sql_val}\n", FILE_APPEND);

            if ($has_allocations && !empty($receiving_branch) && !isReceiveAddedItem($conn, $po_id, $family_code, $item_no)) {
                $receiving_branch_esc = $conn->real_escape_string($receiving_branch);
                $model_where = !empty($item_model_esc) ? " AND (item_model = '{$item_model_esc}' OR item_model IS NULL OR item_model = '' OR item_model = '-')" : "";

                $update_alloc_serial_sql = "UPDATE purchase_order_allocations 
                                           SET serial_number = {$serial_sql_val},
                                               imei_2 = {$serial2_sql_val},
                                               item_model = IF(item_model IS NULL OR item_model = '' OR item_model = '-', '{$item_model_esc}', item_model)
                                           WHERE po_id = {$po_id} 
                                           AND family_code COLLATE utf8mb4_general_ci = '{$family_code_esc}' 
                                           AND branch_name COLLATE utf8mb4_general_ci = '{$receiving_branch_esc}'{$model_where}";

                if (!$conn->query($update_alloc_serial_sql)) {
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR updating allocation serial: " . $conn->error . "\n", FILE_APPEND);
                }
            } else {
                $update_item_sql = "UPDATE purchase_order_items 
                                   SET serial_number = {$serial_sql_val},
                                       imei_2 = {$serial2_sql_val} 
                                   WHERE po_id = {$po_id} AND family_code COLLATE utf8mb4_general_ci = '{$family_code_esc}' AND item_no = {$item_no}";
                if (!$conn->query($update_item_sql)) {
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR updating item serial: " . $conn->error . "\n", FILE_APPEND);
                }
            }
        }

        // Add items to stock_on_hand when PO is completed (same logic as Received)
        $branch_items_filter = buildBranchReceiveItemsFilter($conn, $receiving_branch);
        $items_query = "SELECT poi.*, 
                               COALESCE(MAX(CASE 
                                   WHEN poi.item_model IS NOT NULL 
                                       AND poi.item_model != '' 
                                       AND poi.item_model != '-' 
                                       AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                                   THEN i.has_serial 
                                   ELSE 0 
                               END), MAX(i.has_serial), 0) as has_serial,
                               COALESCE(MAX(CASE 
                                   WHEN poi.item_model IS NOT NULL 
                                       AND poi.item_model != '' 
                                       AND poi.item_model != '-' 
                                       AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                                   THEN i.has_serial_number 
                                   ELSE 0 
                               END), MAX(i.has_serial_number), 0) as has_serial_number
                        FROM purchase_order_items poi 
                        LEFT JOIN items i ON (
                            (poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci)
                            OR (poi.family_code COLLATE utf8mb4_general_ci = i.family_code COLLATE utf8mb4_general_ci)
                        ) AND i.status = 'Active'
                        WHERE poi.po_id = {$po_id}{$branch_items_filter}
                        GROUP BY poi.id";

        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Items query: {$items_query}\n", FILE_APPEND);

        $items_result = $conn->query($items_query);

        if ($items_result && $items_result->num_rows > 0) {
            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Found " . $items_result->num_rows . " items\n", FILE_APPEND);

            while ($item = $items_result->fetch_assoc()) {
                file_put_contents(__DIR__ . '/debug_update_po_status.log', "Processing item: " . print_r($item, true) . "\n", FILE_APPEND);

                $item_model = $conn->real_escape_string($item['item_model']);
                $item_description = $conn->real_escape_string($item['item_description']);
                $quantity = (int) $item['quantity'];
                $po_number = $conn->real_escape_string($item['po_number']);
                $family_code = $conn->real_escape_string($item['family_code']);
                $item_no = (int) $item['item_no'];
                $has_serial = ((int) ($item['has_serial'] ?? 0) == 1 || (int) ($item['has_serial_number'] ?? 0) == 1);

                file_put_contents(__DIR__ . '/debug_update_po_status.log', "Item details - Model: {$item_model}, FC: {$family_code}, ItemNo: {$item_no}, HasSerial: " . ($has_serial ? 'YES' : 'NO') . "\n", FILE_APPEND);

                if ($has_serial && !empty($serial_numbers)) {
                    // Handle serialized items - create individual entries for each serial number
                    // Match by both family_code and item_no for row-specific serial numbers
                    $item_serials = array_values(array_filter($serial_numbers, function ($sn) use ($family_code, $item_no) {
                        return isset($sn['family_code']) && $sn['family_code'] === $family_code
                            && isset($sn['item_no']) && (int) $sn['item_no'] === $item_no;
                    }));

                    $item_serials_2 = array_values(array_filter($serial_numbers_2, function ($sn) use ($family_code, $item_no) {
                        return isset($sn['family_code']) && $sn['family_code'] === $family_code
                            && isset($sn['item_no']) && (int) $sn['item_no'] === $item_no;
                    }));

                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Filtered serials for this item: " . print_r($item_serials, true) . "\n", FILE_APPEND);
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Count of serials for this item: " . count($item_serials) . "\n", FILE_APPEND);

                    foreach ($item_serials as $idx => $serial_data) {
                        $serial_number = $conn->real_escape_string($serial_data['serial_number']);
                        $serial_number_2 = isset($item_serials_2[$idx]['imei_2']) ? $conn->real_escape_string($item_serials_2[$idx]['imei_2']) : '';
                        $imei2_sql = !empty($serial_number_2) ? "'{$serial_number_2}'" : "NULL";

                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Adding serial to stock: {$serial_number} (imei2: {$serial_number_2})\n", FILE_APPEND);

                        // Check if this serial number already exists in stock (to avoid duplicates)
                        $existing_check = $conn->query("SELECT id FROM stock_on_hand WHERE imei = '{$serial_number}' AND family_code = '{$family_code}' LIMIT 1");
                        if (!$existing_check || $existing_check->num_rows == 0) {
                            $stock_sql = "INSERT INTO stock_on_hand 
                                          (item_code, description, item_type, imei, imei2, dr_number, branch, 
                                           dr_date, system_entry_date, status, quantity, family_code)
                                          VALUES 
                                          ('{$item_model}', '{$item_description}', 'IMEI', 
                                           '{$serial_number}', {$imei2_sql}, '{$po_number}', '{$user_branch}', 
                                           '{$action_at}', '{$action_at}', 'Good Stock', 1, '{$family_code}')";

                            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Stock SQL: {$stock_sql}\n", FILE_APPEND);

                            if (!$conn->query($stock_sql)) {
                                file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR adding to stock: " . $conn->error . "\n", FILE_APPEND);
                                throw new Exception("Failed to add serialized item to stock: " . $conn->error);
                            }

                            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Serial added to stock successfully\n", FILE_APPEND);
                        } else {
                            file_put_contents(__DIR__ . '/debug_update_po_status.log', "Serial already exists in stock, skipping\n", FILE_APPEND);
                        }
                    }

                } else {
                    file_put_contents(__DIR__ . '/debug_update_po_status.log', "Non-serialized item or no serials, adding to stock as accessories\n", FILE_APPEND);

                    // Handle non-serialized items - create quantity-based entry
                    // Check if this item already exists in stock for this PO (to avoid duplicates)
                    $existing_check = $conn->query("SELECT id FROM stock_on_hand WHERE family_code = '{$family_code}' AND dr_number = '{$po_number}' LIMIT 1");
                    if (!$existing_check || $existing_check->num_rows == 0) {
                        $stock_sql = "INSERT INTO stock_on_hand 
                                      (item_code, description, item_type, dr_number, branch, 
                                       dr_date, system_entry_date, status, quantity, family_code)
                                      VALUES 
                                      ('{$item_model}', '{$item_description}', 'Accessories', 
                                       '{$po_number}', '{$user_branch}', 
                                       '{$action_at}', '{$action_at}', 'Good Stock', {$quantity}, '{$family_code}')";

                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Stock SQL: {$stock_sql}\n", FILE_APPEND);

                        if (!$conn->query($stock_sql)) {
                            file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR adding to stock: " . $conn->error . "\n", FILE_APPEND);
                            throw new Exception("Failed to add item to stock: " . $conn->error);
                        }

                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Item added to stock successfully\n", FILE_APPEND);
                    } else {
                        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Item already exists in stock, skipping\n", FILE_APPEND);
                    }
                }
            }
        } else {
            file_put_contents(__DIR__ . '/debug_update_po_status.log', "No items found for PO\n", FILE_APPEND);
        }

    } elseif ($new_status === 'Decline') {
        $sql = "UPDATE purchase_orders
                SET status      = 'Decline',
                    declined_by = '{$action_by_esc}',
                    declined_at = '{$action_at}',
                    declined_by_branch = '{$branch_code}'
                WHERE id = {$po_id}";

        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Failed to update PO status: " . $conn->error);
        }
    } elseif ($new_status === 'CANCELED' || $new_status === 'Cancelled') {
        // Handle CANCELED status - track who canceled it
        file_put_contents(__DIR__ . '/debug_update_po_status.log', "=== PROCESSING CANCELED STATUS ===\n", FILE_APPEND);

        $sql = "UPDATE purchase_orders
                SET status      = 'CANCELED',
                    declined_by = '{$action_by_esc}',
                    declined_at = '{$action_at}',
                    declined_by_branch = '{$branch_code}'
                WHERE id = {$po_id}";

        file_put_contents(__DIR__ . '/debug_update_po_status.log', "Update PO SQL: {$sql}\n", FILE_APPEND);

        $result = $conn->query($sql);
        if (!$result) {
            file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR updating PO: " . $conn->error . "\n", FILE_APPEND);
            throw new Exception("Failed to update PO status: " . $conn->error);
        }

        file_put_contents(__DIR__ . '/debug_update_po_status.log', "PO status updated to CANCELED successfully\n", FILE_APPEND);
    } else {
        $sql = "UPDATE purchase_orders SET status = '{$new_status_esc}' WHERE id = {$po_id}";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Failed to update PO status: " . $conn->error);
        }
    }

    // Recalculate PO totals if items were deleted
    if (!empty($deleted_items)) {
        file_put_contents(__DIR__ . '/debug_update_po_status.log', "=== RECALCULATING PO TOTALS ===\n", FILE_APPEND);

        // Calculate new totals from remaining items
        $totals_query = $conn->query("
            SELECT 
                COUNT(DISTINCT id) as total_items,
                SUM(quantity) as total_qty,
                SUM(quantity * cost) as total_cost
            FROM purchase_order_items 
            WHERE po_id = {$po_id}
        ");

        if ($totals_query && $totals_query->num_rows > 0) {
            $totals = $totals_query->fetch_assoc();
            $total_items = (int) $totals['total_items'];
            $total_qty = (int) $totals['total_qty'];
            $total_cost = (float) $totals['total_cost'];

            file_put_contents(__DIR__ . '/debug_update_po_status.log', "New totals - Items: {$total_items}, Qty: {$total_qty}, Cost: {$total_cost}\n", FILE_APPEND);

            // Update PO with new totals
            $update_totals_sql = "UPDATE purchase_orders 
                                 SET total_items = {$total_items},
                                     total_qty = {$total_qty},
                                     total_cost = {$total_cost}
                                 WHERE id = {$po_id}";

            if (!$conn->query($update_totals_sql)) {
                file_put_contents(__DIR__ . '/debug_update_po_status.log', "ERROR updating totals: " . $conn->error . "\n", FILE_APPEND);
                throw new Exception("Failed to update PO totals: " . $conn->error);
            }

            file_put_contents(__DIR__ . '/debug_update_po_status.log', "PO totals updated successfully\n", FILE_APPEND);
        }
    }

    // Commit transaction
    $conn->commit();
    file_put_contents(__DIR__ . '/debug_update_po_status.log', "=== TRANSACTION COMMITTED SUCCESSFULLY ===\n", FILE_APPEND);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    file_put_contents(__DIR__ . '/debug_update_po_status.log', "=== TRANSACTION ROLLED BACK - ERROR: " . $e->getMessage() . " ===\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>