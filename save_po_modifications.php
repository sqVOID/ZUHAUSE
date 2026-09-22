<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

function parseRowKey($row_key) {
    $pos = strrpos($row_key, '-');
    if ($pos === false) {
        return ['family_code' => $row_key, 'item_no' => 0];
    }

    return [
        'family_code' => substr($row_key, 0, $pos),
        'item_no' => (int)substr($row_key, $pos + 1),
    ];
}

function resolveStockBranchName($conn, $receiving_branch, $po_branch_name, $po_number, $family_code_esc) {
    if (!empty($receiving_branch)) {
        return $receiving_branch;
    }

    $existing_branch_query = $conn->query("
        SELECT branch
        FROM stock_on_hand
        WHERE dr_number = '{$po_number}'
        AND family_code = '{$family_code_esc}'
        AND branch IS NOT NULL
        AND branch != ''
        LIMIT 1
    ");

    if ($existing_branch_query && $existing_branch_query->num_rows > 0) {
        return trim($existing_branch_query->fetch_assoc()['branch']);
    }

    return $po_branch_name;
}

function syncSerializedStockForItem($conn, $po_number, $family_code_esc, $item_model, $item_description, $old_serials, $new_serials, $stock_branch_name, $po_id = 0, $item_no = 0) {
    $item_model_esc = $conn->real_escape_string($item_model);
    $item_description_esc = $conn->real_escape_string($item_description);
    $stock_branch_name_esc = $conn->real_escape_string($stock_branch_name);
    $po_number_esc = $conn->real_escape_string($po_number);

    $old_serials = array_values($old_serials);
    $new_serials = array_values($new_serials);
    
    // Load item types from purchase_order_serial_types table
    $serial_types = [];
    if ($po_id > 0 && $item_no > 0) {
        $types_query = $conn->query("SELECT serial_number, item_type 
                                     FROM purchase_order_serial_types 
                                     WHERE po_id = {$po_id} 
                                     AND family_code = '{$family_code_esc}' 
                                     AND item_no = {$item_no}");
        if ($types_query && $types_query->num_rows > 0) {
            while ($type_row = $types_query->fetch_assoc()) {
                $serial_types[$type_row['serial_number']] = $type_row['item_type'];
            }
        }
    }

    $original_dr_date = date('Y-m-d H:i:s');
    $original_system_entry_date = date('Y-m-d H:i:s');

    if (!empty($old_serials)) {
        $first_old_esc = $conn->real_escape_string($old_serials[0]);
        $original_dates_query = $conn->query("
            SELECT dr_date, system_entry_date, branch
            FROM stock_on_hand
            WHERE imei = '{$first_old_esc}'
            AND dr_number = '{$po_number_esc}'
            LIMIT 1
        ");
    } else {
        $original_dates_query = $conn->query("
            SELECT dr_date, system_entry_date, branch
            FROM stock_on_hand
            WHERE family_code = '{$family_code_esc}'
            AND dr_number = '{$po_number_esc}'
            LIMIT 1
        ");
    }

    if ($original_dates_query && $original_dates_query->num_rows > 0) {
        $dates_data = $original_dates_query->fetch_assoc();
        $original_dr_date = $dates_data['dr_date'];
        $original_system_entry_date = $dates_data['system_entry_date'];
        if (empty($stock_branch_name) && !empty($dates_data['branch'])) {
            $stock_branch_name_esc = $conn->real_escape_string(trim($dates_data['branch']));
        }
    }

    $max_len = max(count($old_serials), count($new_serials));
    for ($i = 0; $i < $max_len; $i++) {
        $old_serial = $old_serials[$i] ?? null;
        $new_serial = $new_serials[$i] ?? null;

        if ($old_serial && $new_serial && $old_serial === $new_serial) {
            $serial_esc = $conn->real_escape_string($old_serial);
            
            // Get the item status for this serial number
            $item_status = isset($serial_types[$old_serial]) ? $serial_types[$old_serial] : 'Good Stock';
            $item_status_esc = $conn->real_escape_string($item_status);
            
            $update_stock_sql = "UPDATE stock_on_hand
                                SET item_code = '{$item_model_esc}',
                                    description = '{$item_description_esc}',
                                    family_code = '{$family_code_esc}',
                                    branch = '{$stock_branch_name_esc}',
                                    status = '{$item_status_esc}'
                                WHERE imei = '{$serial_esc}'
                                AND dr_number = '{$po_number_esc}'";

            if (!$conn->query($update_stock_sql)) {
                throw new Exception("Failed to update serial in stock: " . $conn->error);
            }
            continue;
        }

        if ($old_serial && $new_serial && $old_serial !== $new_serial) {
            $old_serial_esc = $conn->real_escape_string($old_serial);
            $new_serial_esc = $conn->real_escape_string($new_serial);

            $update_imei_sql = "UPDATE stock_on_hand
                               SET imei = '{$new_serial_esc}',
                                   item_code = '{$item_model_esc}',
                                   description = '{$item_description_esc}',
                                   family_code = '{$family_code_esc}',
                                   branch = '{$stock_branch_name_esc}'
                               WHERE imei = '{$old_serial_esc}'
                               AND dr_number = '{$po_number_esc}'";

            if (!$conn->query($update_imei_sql)) {
                throw new Exception("Failed to update serial number in stock: " . $conn->error);
            }

            if ($conn->affected_rows === 0) {
                $duplicate_check = $conn->query("SELECT id FROM stock_on_hand WHERE imei = '{$new_serial_esc}' LIMIT 1");
                if ($duplicate_check && $duplicate_check->num_rows > 0) {
                    throw new Exception("Serial number '{$new_serial}' already exists in stock.");
                }
                
                // Get the item status for this serial number
                $item_status = isset($serial_types[$new_serial]) ? $serial_types[$new_serial] : 'Good Stock';
                $item_status_esc = $conn->real_escape_string($item_status);

                $insert_stock_sql = "INSERT INTO stock_on_hand
                                    (item_code, description, item_type, imei, dr_number, branch,
                                     dr_date, system_entry_date, status, quantity, family_code)
                                    VALUES
                                    ('{$item_model_esc}', '{$item_description_esc}', 'IMEI',
                                     '{$new_serial_esc}', '{$po_number_esc}', '{$stock_branch_name_esc}',
                                     '{$original_dr_date}', '{$original_system_entry_date}', '{$item_status_esc}', 1, '{$family_code_esc}')";

                if (!$conn->query($insert_stock_sql)) {
                    throw new Exception("Failed to add updated serial to stock: " . $conn->error);
                }
            }

            continue;
        }

        if ($old_serial && !$new_serial) {
            $old_serial_esc = $conn->real_escape_string($old_serial);
            $delete_stock_sql = "DELETE FROM stock_on_hand
                                WHERE imei = '{$old_serial_esc}'
                                AND dr_number = '{$po_number_esc}'";

            if (!$conn->query($delete_stock_sql)) {
                throw new Exception("Failed to delete old serial from stock: " . $conn->error);
            }
            continue;
        }

        if (!$old_serial && $new_serial) {
            $new_serial_esc = $conn->real_escape_string($new_serial);
            $duplicate_check = $conn->query("SELECT id FROM stock_on_hand WHERE imei = '{$new_serial_esc}' LIMIT 1");
            if ($duplicate_check && $duplicate_check->num_rows > 0) {
                throw new Exception("Serial number '{$new_serial}' already exists in stock.");
            }
            
            // Get the item status for this serial number
            $item_status = isset($serial_types[$new_serial]) ? $serial_types[$new_serial] : 'Good Stock';
            $item_status_esc = $conn->real_escape_string($item_status);

            $insert_stock_sql = "INSERT INTO stock_on_hand
                                (item_code, description, item_type, imei, dr_number, branch,
                                 dr_date, system_entry_date, status, quantity, family_code)
                                VALUES
                                ('{$item_model_esc}', '{$item_description_esc}', 'IMEI',
                                 '{$new_serial_esc}', '{$po_number_esc}', '{$stock_branch_name_esc}',
                                 '{$original_dr_date}', '{$original_system_entry_date}', '{$item_status_esc}', 1, '{$family_code_esc}')";

            if (!$conn->query($insert_stock_sql)) {
                throw new Exception("Failed to add new serial to stock: " . $conn->error);
            }
        }
    }
}

function parseSerialString($serial_string) {
    if (empty($serial_string)) {
        return [];
    }

    $serials = trim($serial_string);
    if (strpos($serials, "\n") !== false) {
        $serial_array = explode("\n", $serials);
    } else {
        $serial_array = explode(",", $serials);
    }

    return array_values(array_filter(array_map('trim', $serial_array)));
}

function getMergedAllocationSerials($conn, $po_id, $family_code, $branch_name = '') {
    $serials = [];
    $family_code_esc = $conn->real_escape_string($family_code);
    $branch_filter = '';
    if (!empty($branch_name)) {
        $branch_filter = " AND branch_name = '" . $conn->real_escape_string($branch_name) . "'";
    }

    $result = $conn->query("
        SELECT serial_number
        FROM purchase_order_allocations
        WHERE po_id = {$po_id}
        AND family_code = '{$family_code_esc}'
        AND serial_number IS NOT NULL
        AND serial_number != ''
        {$branch_filter}
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $serials = array_merge($serials, parseSerialString($row['serial_number']));
        }
    }

    return array_values(array_unique($serials));
}

function syncAllocationSerials($conn, $po_id, $family_code, $serial_string, $received_qty, $branch_name = '') {
    $family_code_esc = $conn->real_escape_string($family_code);
    $serial_string_esc = $conn->real_escape_string($serial_string);
    $branch_filter = '';
    if (!empty($branch_name)) {
        $branch_filter = " AND branch_name = '" . $conn->real_escape_string($branch_name) . "'";
    }

    $alloc_result = $conn->query("
        SELECT id, quantity, received_qty, serial_number
        FROM purchase_order_allocations
        WHERE po_id = {$po_id}
        AND family_code = '{$family_code_esc}'
        {$branch_filter}
    ");

    if (!$alloc_result || $alloc_result->num_rows === 0) {
        return;
    }

    if ($alloc_result->num_rows === 1) {
        $conn->query("
            UPDATE purchase_order_allocations
            SET serial_number = '{$serial_string_esc}',
                received_qty = {$received_qty}
            WHERE po_id = {$po_id}
            AND family_code = '{$family_code_esc}'
            {$branch_filter}
        ");
        return;
    }

    while ($alloc = $alloc_result->fetch_assoc()) {
        $alloc_id = (int)$alloc['id'];
        if ((int)($alloc['received_qty'] ?? 0) > 0 || !empty($alloc['serial_number'])) {
            $branch_qty = min($received_qty, (int)$alloc['quantity']);
            $conn->query("
                UPDATE purchase_order_allocations
                SET serial_number = '{$serial_string_esc}',
                    received_qty = {$branch_qty}
                WHERE id = {$alloc_id}
            ");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
$serial_numbers_raw = isset($_POST['serial_numbers']) ? $_POST['serial_numbers'] : '';
$serial_numbers = !empty($serial_numbers_raw) ? json_decode($serial_numbers_raw, true) : [];
$serial_numbers_2_raw = isset($_POST['serial_numbers_2']) ? $_POST['serial_numbers_2'] : '';
$serial_numbers_2 = !empty($serial_numbers_2_raw) ? json_decode($serial_numbers_2_raw, true) : [];
$item_models = isset($_POST['item_models']) ? json_decode($_POST['item_models'], true) : [];
$received_quantities_raw = isset($_POST['received_quantities']) ? $_POST['received_quantities'] : '';
$received_quantities = !empty($received_quantities_raw) ? json_decode($received_quantities_raw, true) : [];
$quantities_costs_raw = isset($_POST['quantities_costs']) ? $_POST['quantities_costs'] : '';
$quantities_costs = !empty($quantities_costs_raw) ? json_decode($quantities_costs_raw, true) : [];
$reason_to_modify = isset($_POST['reason_to_modify']) ? trim($_POST['reason_to_modify']) : '';
$invoice_number = isset($_POST['invoice_number']) ? trim($_POST['invoice_number']) : '';
$deleted_items_raw = isset($_POST['deleted_items']) ? $_POST['deleted_items'] : '';
$deleted_items = !empty($deleted_items_raw) ? json_decode($deleted_items_raw, true) : [];

// New fields for header modification (PO Number and PO Date are read-only and excluded)
$contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';
$supplier_company = isset($_POST['supplier_company']) ? trim($_POST['supplier_company']) : '';
$supplier_name = isset($_POST['supplier_name']) ? trim($_POST['supplier_name']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';
$terms = isset($_POST['terms']) ? trim($_POST['terms']) : '';
$payment_due_date = isset($_POST['payment_due_date']) ? trim($_POST['payment_due_date']) : '';
$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
$receiving_remarks = isset($_POST['receiving_remarks']) ? trim($_POST['receiving_remarks']) : '';
$receiving_branch = isset($_POST['receiving_branch']) ? trim($_POST['receiving_branch']) : '';

if ($po_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
    exit;
}

// Validate that reason to modify is provided (required field)
if (empty($reason_to_modify)) {
    echo json_encode(['success' => false, 'message' => 'Reason to modify is required.']);
    exit;
}

// Get PO details for stock operations
$po_query = $conn->query("SELECT po_number, created_by_branch FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
if (!$po_query || $po_query->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Purchase order not found.']);
    exit;
}
$po_data = $po_query->fetch_assoc();
$po_number = $po_data['po_number'];
$po_branch_code = $po_data['created_by_branch'];

// Get branch name from branch code
$po_branch_name = '';
if (!empty($po_branch_code)) {
    if ($po_branch_code === 'ALL') {
        $po_branch_name = 'ALL BRANCHES';
    } else {
        $branch_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '" . $conn->real_escape_string($po_branch_code) . "' LIMIT 1");
        if ($branch_query && $branch_query->num_rows > 0) {
            $branch_result = $branch_query->fetch_assoc();
            $po_branch_name = $branch_result['branch_name'];
        }
    }
}

// Start transaction
$conn->begin_transaction();

try {
    // Delete staged items if provided - ALSO DELETE FROM STOCK ON HAND
    if (!empty($deleted_items)) {
        foreach ($deleted_items as $item_id) {
            $item_id = (int)$item_id;
            
            // Get item details before deletion to remove from stock
            $item_query = $conn->query("SELECT poi.*, i.has_serial 
                                        FROM purchase_order_items poi 
                                        LEFT JOIN items i ON poi.family_code = i.family_code 
                                        WHERE poi.id = {$item_id} AND poi.po_id = {$po_id} LIMIT 1");
            
            if ($item_query && $item_query->num_rows > 0) {
                $item_data = $item_query->fetch_assoc();
                $family_code = $item_data['family_code'];
                $item_model = $item_data['item_model'];
                $has_serial = $item_data['has_serial'];
                $serial_numbers_field = $item_data['serial_number'];

                if (empty($serial_numbers_field)) {
                    $allocation_serials = getMergedAllocationSerials($conn, $po_id, $family_code, $receiving_branch);
                    if (!empty($allocation_serials)) {
                        $serial_numbers_field = implode("\n", $allocation_serials);
                    }
                }
                
                // Remove from stock_on_hand based on serialization
                if ($has_serial == 1 && !empty($serial_numbers_field)) {
                    // Handle serialized items - delete each serial number
                    $serials = trim($serial_numbers_field);
                    if (strpos($serials, "\n") !== false) {
                        $serial_array = explode("\n", $serials);
                    } else {
                        $serial_array = explode(",", $serials);
                    }
                    $serial_array = array_filter(array_map('trim', $serial_array));
                    
                    foreach ($serial_array as $serial) {
                        $serial_esc = $conn->real_escape_string($serial);
                        $item_model_esc = $conn->real_escape_string($item_model);
                        
                        $delete_stock_sql = "DELETE FROM stock_on_hand 
                                            WHERE imei = '{$serial_esc}' 
                                            AND item_code = '{$item_model_esc}' 
                                            AND dr_number = '{$po_number}'";
                        
                        if (!$conn->query($delete_stock_sql)) {
                            throw new Exception("Failed to delete serialized item from stock: " . $conn->error);
                        }
                    }
                } else {
                    // Handle non-serialized items - delete quantity-based entry
                    $family_code_esc = $conn->real_escape_string($family_code);
                    
                    $delete_stock_sql = "DELETE FROM stock_on_hand 
                                        WHERE family_code = '{$family_code_esc}' 
                                        AND dr_number = '{$po_number}'";
                    
                    if (!$conn->query($delete_stock_sql)) {
                        throw new Exception("Failed to delete non-serialized item from stock: " . $conn->error);
                    }
                }
            }
            
            // Delete from purchase order items
            $delete_sql = "DELETE FROM purchase_order_items WHERE id = {$item_id} AND po_id = {$po_id}";
            
            if (!$conn->query($delete_sql)) {
                throw new Exception("Failed to delete item: " . $conn->error);
            }
        }
    }
    
    // Update item models if provided - SYNC WITH STOCK ON HAND
    if (!empty($item_models)) {
        foreach ($item_models as $row_key => $item_data) {
            $family_code_esc = $conn->real_escape_string($item_data['family_code']);
            $item_no = (int)$item_data['item_no'];
            $new_item_model_esc = $conn->real_escape_string($item_data['item_model']);
            $new_item_description_esc = $conn->real_escape_string($item_data['item_description']);
            
            // Get current item to find old item_model for stock update
            $current_item_query = $conn->query("
                SELECT poi.*, i.has_serial 
                FROM purchase_order_items poi 
                LEFT JOIN items i ON poi.family_code = i.family_code 
                WHERE poi.po_id = {$po_id} 
                AND poi.family_code = '{$family_code_esc}' 
                AND poi.item_no = {$item_no} 
                LIMIT 1
            ");
            
            if ($current_item_query && $current_item_query->num_rows > 0) {
                $current_item = $current_item_query->fetch_assoc();
                $old_item_model = $current_item['item_model'];
                $has_serial = $current_item['has_serial'];
                
                // Update stock_on_hand with new item_code (item_model) and description
                if ($has_serial == 1) {
                    // For serialized items, update by family_code and dr_number
                    $update_stock_sql = "UPDATE stock_on_hand 
                                        SET item_code = '{$new_item_model_esc}', 
                                            description = '{$new_item_description_esc}'
                                        WHERE family_code = '{$family_code_esc}' 
                                        AND dr_number = '{$po_number}'";
                } else {
                    // For non-serialized items, update by family_code and dr_number
                    $update_stock_sql = "UPDATE stock_on_hand 
                                        SET item_code = '{$new_item_model_esc}', 
                                            description = '{$new_item_description_esc}'
                                        WHERE family_code = '{$family_code_esc}' 
                                        AND dr_number = '{$po_number}'";
                }
                
                if (!$conn->query($update_stock_sql)) {
                    throw new Exception("Failed to update item model in stock: " . $conn->error);
                }
            }
            
            // Update purchase order items
            $update_model_sql = "UPDATE purchase_order_items 
                                SET item_model = '{$new_item_model_esc}',
                                    item_description = '{$new_item_description_esc}'
                                WHERE po_id = {$po_id} 
                                AND family_code = '{$family_code_esc}' 
                                AND item_no = {$item_no}";
            
            if (!$conn->query($update_model_sql)) {
                throw new Exception("Failed to update item model: " . $conn->error);
            }
        }
    }
    
    // Update received quantities for non-serialized items if provided
    if (!empty($received_quantities)) {
        foreach ($received_quantities as $row_key => $qty_data) {
            $family_code_esc = $conn->real_escape_string($qty_data['family_code']);
            $item_no = (int)$qty_data['item_no'];
            $new_received_qty = (int)$qty_data['received_qty'];
            
            // Get current item details including old received_qty
            $current_item_query = $conn->query("
                SELECT poi.*, i.has_serial 
                FROM purchase_order_items poi 
                LEFT JOIN items i ON poi.family_code = i.family_code 
                WHERE poi.po_id = {$po_id} 
                AND poi.family_code = '{$family_code_esc}' 
                AND poi.item_no = {$item_no} 
                LIMIT 1
            ");
            
            if ($current_item_query && $current_item_query->num_rows > 0) {
                $current_item = $current_item_query->fetch_assoc();
                $old_received_qty = (int)($current_item['received_qty'] ?? 0);
                $item_model = $current_item['item_model'];
                $item_description = $current_item['item_description'];
                $has_serial = $current_item['has_serial'];
                
                // Only update stock_on_hand for non-serialized items
                if ($has_serial != 1) {
                    // Calculate the difference in received quantity
                    $qty_difference = $new_received_qty - $old_received_qty;
                    
                    if ($qty_difference != 0) {
                        $item_model_esc = $conn->real_escape_string($item_model);
                        $item_description_esc = $conn->real_escape_string($item_description);
                        $po_branch_name_esc = $conn->real_escape_string($po_branch_name);
                        
                        // Get original dr_date and system_entry_date to preserve AGING and IOU
                        $original_dates_query = $conn->query("
                            SELECT dr_date, system_entry_date 
                            FROM stock_on_hand 
                            WHERE family_code = '{$family_code_esc}' 
                            AND dr_number = '{$po_number}' 
                            LIMIT 1
                        ");
                        
                        // Default to current date if no existing stock found
                        $original_dr_date = date('Y-m-d H:i:s');
                        $original_system_entry_date = date('Y-m-d H:i:s');
                        
                        if ($original_dates_query && $original_dates_query->num_rows > 0) {
                            $dates_data = $original_dates_query->fetch_assoc();
                            $original_dr_date = $dates_data['dr_date'];
                            $original_system_entry_date = $dates_data['system_entry_date'];
                        }
                        
                        if ($qty_difference > 0) {
                            // Received quantity increased - add to stock_on_hand
                            // Check if stock entry already exists for this item
                            $existing_stock_query = $conn->query("
                                SELECT id, quantity 
                                FROM stock_on_hand 
                                WHERE family_code = '{$family_code_esc}' 
                                AND item_code = '{$item_model_esc}' 
                                AND dr_number = '{$po_number}' 
                                LIMIT 1
                            ");
                            
                            if ($existing_stock_query && $existing_stock_query->num_rows > 0) {
                                // Update existing stock entry
                                $stock_data = $existing_stock_query->fetch_assoc();
                                $current_stock_qty = (int)$stock_data['quantity'];
                                $new_stock_qty = $current_stock_qty + $qty_difference;
                                $stock_id = (int)$stock_data['id'];
                                
                                $update_stock_sql = "UPDATE stock_on_hand 
                                                    SET quantity = {$new_stock_qty},
                                                        description = '{$item_description_esc}'
                                                    WHERE id = {$stock_id}";
                                
                                if (!$conn->query($update_stock_sql)) {
                                    throw new Exception("Failed to update stock quantity: " . $conn->error);
                                }
                            } else {
                                // Insert new stock entry - preserve original dates for AGING/IOU
                                $insert_stock_sql = "INSERT INTO stock_on_hand 
                                                    (item_code, description, item_type, imei, dr_number, branch, 
                                                     dr_date, system_entry_date, status, quantity, family_code)
                                                    VALUES 
                                                    ('{$item_model_esc}', '{$item_description_esc}', 'QTY', '', 
                                                     '{$po_number}', '{$po_branch_name_esc}', 
                                                     '{$original_dr_date}', '{$original_system_entry_date}', 
                                                     'Active', {$new_received_qty}, '{$family_code_esc}')";
                                
                                if (!$conn->query($insert_stock_sql)) {
                                    throw new Exception("Failed to add stock: " . $conn->error);
                                }
                            }
                        } else if ($qty_difference < 0) {
                            // Received quantity decreased - remove from stock_on_hand
                            $qty_to_remove = abs($qty_difference);
                            
                            // Update or delete stock entry
                            $existing_stock_query = $conn->query("
                                SELECT id, quantity 
                                FROM stock_on_hand 
                                WHERE family_code = '{$family_code_esc}' 
                                AND item_code = '{$item_model_esc}' 
                                AND dr_number = '{$po_number}' 
                                LIMIT 1
                            ");
                            
                            if ($existing_stock_query && $existing_stock_query->num_rows > 0) {
                                $stock_data = $existing_stock_query->fetch_assoc();
                                $current_stock_qty = (int)$stock_data['quantity'];
                                $new_stock_qty = $current_stock_qty - $qty_to_remove;
                                $stock_id = (int)$stock_data['id'];
                                
                                if ($new_stock_qty <= 0) {
                                    // Delete stock entry if quantity becomes 0 or negative
                                    $delete_stock_sql = "DELETE FROM stock_on_hand WHERE id = {$stock_id}";
                                    
                                    if (!$conn->query($delete_stock_sql)) {
                                        throw new Exception("Failed to remove stock: " . $conn->error);
                                    }
                                } else {
                                    // Update stock quantity
                                    $update_stock_sql = "UPDATE stock_on_hand 
                                                        SET quantity = {$new_stock_qty}
                                                        WHERE id = {$stock_id}";
                                    
                                    if (!$conn->query($update_stock_sql)) {
                                        throw new Exception("Failed to update stock quantity: " . $conn->error);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            // Update purchase_order_items with new received_qty
            $update_received_sql = "UPDATE purchase_order_items 
                                   SET received_qty = {$new_received_qty}
                                   WHERE po_id = {$po_id} 
                                   AND family_code = '{$family_code_esc}' 
                                   AND item_no = {$item_no}";
            
            if (!$conn->query($update_received_sql)) {
                throw new Exception("Failed to update received quantity: " . $conn->error);
            }
        }
    }
    
    // Update quantities and costs if provided
    if (!empty($quantities_costs)) {
        foreach ($quantities_costs as $item_qc) {
            $item_id = (int)($item_qc['id'] ?? 0);
            $family_code_esc = $conn->real_escape_string($item_qc['family_code'] ?? '');
            $item_no = (int)($item_qc['item_no'] ?? 0);
            $new_qty = (float)($item_qc['quantity'] ?? 0);
            $new_cost = (float)($item_qc['cost'] ?? 0);
            $new_total = $new_qty * $new_cost;

            if ($item_id > 0) {
                $conn->query("
                    UPDATE purchase_order_items
                    SET quantity = {$new_qty},
                        cost = {$new_cost},
                        total = {$new_total}
                    WHERE id = {$item_id} AND po_id = {$po_id}
                ");
            } else {
                $conn->query("
                    UPDATE purchase_order_items
                    SET quantity = {$new_qty},
                        cost = {$new_cost},
                        total = {$new_total}
                    WHERE po_id = {$po_id}
                    AND family_code = '{$family_code_esc}'
                    AND item_no = {$item_no}
                ");
            }

            $conn->query("
                UPDATE purchase_order_allocations
                SET quantity = {$new_qty},
                    cost = {$new_cost}
                WHERE po_id = {$po_id}
                AND family_code = '{$family_code_esc}'
            ");
        }
    }
    
    // Update serial numbers if provided - SYNC WITH STOCK ON HAND
    if (!empty($serial_numbers)) {
        // Group serial numbers by family_code and item_no
        $serials_by_row = [];
        foreach ($serial_numbers as $serial_data) {
            $family_code = $serial_data['family_code'];
            $item_no = (int)$serial_data['item_no'];
            $row_key = $family_code . '-' . $item_no;
            
            if (!isset($serials_by_row[$row_key])) {
                $serials_by_row[$row_key] = [];
            }
            $serials_by_row[$row_key][] = $serial_data['serial_number'];
        }
        
        // Update each row with its serial numbers
        foreach ($serials_by_row as $row_key => $item_serials) {
            $item_serials = array_values(array_filter(array_map('trim', $item_serials)));
            $parsed_row = parseRowKey($row_key);
            $family_code = $parsed_row['family_code'];
            $item_no = (int)$parsed_row['item_no'];
            $family_code_esc = $conn->real_escape_string($family_code);
            
            // Get the current item details and old serial numbers
            $current_item_query = $conn->query("
                SELECT poi.*, i.has_serial 
                FROM purchase_order_items poi 
                LEFT JOIN items i ON poi.family_code = i.family_code 
                WHERE poi.po_id = {$po_id} 
                AND poi.family_code = '{$family_code_esc}' 
                AND poi.item_no = {$item_no} 
                LIMIT 1
            ");
            
            if ($current_item_query && $current_item_query->num_rows > 0) {
                $current_item = $current_item_query->fetch_assoc();
                $old_serial_string = $current_item['serial_number'];
                $item_model = $current_item['item_model'];
                $item_description = $current_item['item_description'];
                $has_serial = $current_item['has_serial'];
                
                // Get old serial numbers as array
                $old_serials = parseSerialString($old_serial_string);
                if (empty($old_serials)) {
                    $old_serials = getMergedAllocationSerials($conn, $po_id, $family_code, $receiving_branch);
                }
                
                // Get new serial numbers (already in array)
                $new_serials = $item_serials;
                
                // Only update stock if this is a serialized item
                if ($has_serial == 1) {
                    $stock_branch_name = resolveStockBranchName(
                        $conn,
                        $receiving_branch,
                        $po_branch_name,
                        $po_number,
                        $family_code_esc
                    );

                    syncSerializedStockForItem(
                        $conn,
                        $po_number,
                        $family_code_esc,
                        $item_model,
                        $item_description,
                        $old_serials,
                        $new_serials,
                        $stock_branch_name,
                        $po_id,
                        $item_no
                    );
                }
            }
            
            // Join serial numbers with line breaks
            $serial_string = $conn->real_escape_string(implode("\n", $item_serials));
            
            // Update purchase order items with new serial numbers
            $update_item_sql = "UPDATE purchase_order_items 
                               SET serial_number = '{$serial_string}' 
                               WHERE po_id = {$po_id} 
                               AND family_code = '{$family_code_esc}' 
                               AND item_no = {$item_no}";
            
            if (!$conn->query($update_item_sql)) {
                throw new Exception("Failed to update serial numbers: " . $conn->error);
            }

            syncAllocationSerials(
                $conn,
                $po_id,
                $family_code,
                implode("\n", $item_serials),
                count($item_serials),
                $receiving_branch
            );
        }
    }

    // Update IMEI 2 / Serial Number 2 (imei_2) if provided
    if (!empty($serial_numbers_2)) {
        $imei2_by_row = [];
        foreach ($serial_numbers_2 as $s2_data) {
            $family_code = $s2_data['family_code'];
            $item_no = (int)$s2_data['item_no'];
            $row_key = $family_code . '-' . $item_no;

            if (!isset($imei2_by_row[$row_key])) {
                $imei2_by_row[$row_key] = [];
            }
            $imei2_by_row[$row_key][] = $s2_data['imei_2'];
        }

        foreach ($imei2_by_row as $row_key => $item_imei2s) {
            $item_imei2s = array_values(array_filter(array_map('trim', $item_imei2s)));
            $parsed_row = parseRowKey($row_key);
            $family_code = $parsed_row['family_code'];
            $item_no = (int)$parsed_row['item_no'];
            $family_code_esc = $conn->real_escape_string($family_code);

            $imei2_string = $conn->real_escape_string(implode("\n", $item_imei2s));

            // Update purchase_order_items.imei_2
            $conn->query("
                UPDATE purchase_order_items
                SET imei_2 = '{$imei2_string}'
                WHERE po_id = {$po_id}
                AND family_code = '{$family_code_esc}'
                AND item_no = {$item_no}
            ");

            // Update purchase_order_allocations.imei_2
            $alloc_filter = "";
            if (!empty($receiving_branch)) {
                $receiving_branch_esc = $conn->real_escape_string($receiving_branch);
                $alloc_filter = " AND branch_name = '{$receiving_branch_esc}'";
            }
            $conn->query("
                UPDATE purchase_order_allocations
                SET imei_2 = '{$imei2_string}'
                WHERE po_id = {$po_id}
                AND family_code COLLATE utf8mb4_general_ci = '{$family_code_esc}'
                {$alloc_filter}
            ");
        }
    }
    
    // Update PO remarks if provided
    if (!empty($remarks)) {
        $remarks_esc = $conn->real_escape_string($remarks);
        
        $update_remarks_sql = "UPDATE purchase_orders 
                              SET remarks = '{$remarks_esc}' 
                              WHERE id = {$po_id}";
        
        if (!$conn->query($update_remarks_sql)) {
            throw new Exception("Failed to update PO remarks: " . $conn->error);
        }
    }
    
    // Update receiving remarks if provided
    if (!empty($receiving_remarks)) {
        $receiving_remarks_esc = $conn->real_escape_string($receiving_remarks);

        if (!empty($receiving_branch)) {
            $receiving_branch_esc = $conn->real_escape_string($receiving_branch);
            $update_receiving_remarks_sql = "UPDATE purchase_order_allocations
                                            SET receiving_remarks = '{$receiving_remarks_esc}'
                                            WHERE po_id = {$po_id}
                                            AND branch_name = '{$receiving_branch_esc}'";
        } else {
            $update_receiving_remarks_sql = "UPDATE purchase_orders
                                            SET receiving_remarks = '{$receiving_remarks_esc}'
                                            WHERE id = {$po_id}";
        }

        if (!$conn->query($update_receiving_remarks_sql)) {
            throw new Exception("Failed to update receiving remarks: " . $conn->error);
        }
    }

    // Update invoice number if provided
    if (!empty($invoice_number)) {
        $invoice_number_esc = $conn->real_escape_string($invoice_number);

        if (!empty($receiving_branch)) {
            $receiving_branch_esc = $conn->real_escape_string($receiving_branch);
            $update_invoice_sql = "UPDATE purchase_order_allocations
                                  SET invoice_number = '{$invoice_number_esc}'
                                  WHERE po_id = {$po_id}
                                  AND branch_name = '{$receiving_branch_esc}'";
        } else {
            $update_invoice_sql = "UPDATE purchase_orders
                                  SET invoice_number = '{$invoice_number_esc}'
                                  WHERE id = {$po_id}";
        }

        if (!$conn->query($update_invoice_sql)) {
            throw new Exception("Failed to update invoice number: " . $conn->error);
        }
    }
    
    // Update reason to modify (required field)
    if (!empty($reason_to_modify)) {
        $reason_to_modify_esc = $conn->real_escape_string($reason_to_modify);
        
        $update_reason_sql = "UPDATE purchase_orders 
                             SET reason_to_modify = '{$reason_to_modify_esc}' 
                             WHERE id = {$po_id}";
        
        if (!$conn->query($update_reason_sql)) {
            throw new Exception("Failed to update reason to modify: " . $conn->error);
        }
    }
    
    // Note: PO Number and PO Date are read-only fields and should not be modified
    
    // Update Contact Number if provided
    if (!empty($contact_number)) {
        $contact_number_esc = $conn->real_escape_string($contact_number);
        
        $update_contact_sql = "UPDATE purchase_orders 
                              SET contact_number = '{$contact_number_esc}' 
                              WHERE id = {$po_id}";
        
        if (!$conn->query($update_contact_sql)) {
            throw new Exception("Failed to update contact number: " . $conn->error);
        }
    }
    
    // Update Supplier Company if provided
    if (!empty($supplier_company)) {
        $supplier_company_esc = $conn->real_escape_string($supplier_company);
        
        $update_supplier_company_sql = "UPDATE purchase_orders 
                                       SET supplier_company = '{$supplier_company_esc}' 
                                       WHERE id = {$po_id}";
        
        if (!$conn->query($update_supplier_company_sql)) {
            throw new Exception("Failed to update supplier company: " . $conn->error);
        }
    }
    
    // Update Supplier Name if provided
    if (!empty($supplier_name)) {
        $supplier_name_esc = $conn->real_escape_string($supplier_name);
        
        $update_supplier_name_sql = "UPDATE purchase_orders 
                                    SET supplier_name = '{$supplier_name_esc}' 
                                    WHERE id = {$po_id}";
        
        if (!$conn->query($update_supplier_name_sql)) {
            throw new Exception("Failed to update supplier name: " . $conn->error);
        }
    }
    
    // Update Address if provided
    if (!empty($address)) {
        $address_esc = $conn->real_escape_string($address);
        
        $update_address_sql = "UPDATE purchase_orders 
                              SET address = '{$address_esc}' 
                              WHERE id = {$po_id}";
        
        if (!$conn->query($update_address_sql)) {
            throw new Exception("Failed to update address: " . $conn->error);
        }
    }
    
    // Update Payment Days/Terms if provided
    if (!empty($terms)) {
        $terms_esc = $conn->real_escape_string($terms);
        
        $update_terms_sql = "UPDATE purchase_orders 
                            SET terms = '{$terms_esc}' 
                            WHERE id = {$po_id}";
        
        if (!$conn->query($update_terms_sql)) {
            throw new Exception("Failed to update payment days: " . $conn->error);
        }
    }
    
    // Update Payment Due Date if provided
    if (!empty($payment_due_date)) {
        $payment_due_date_esc = $conn->real_escape_string($payment_due_date);
        
        $update_due_date_sql = "UPDATE purchase_orders 
                               SET payment_due_date = '{$payment_due_date_esc}' 
                               WHERE id = {$po_id}";
        
        if (!$conn->query($update_due_date_sql)) {
            throw new Exception("Failed to update payment due date: " . $conn->error);
        }
    }
    
    // Recalculate and update total_qty and total_items after modifications
    // Only count original PO items (is_receive_added = 0), not items added during receiving
    $recalc_query = $conn->query("
        SELECT COUNT(DISTINCT id) as total_items,
               SUM(quantity) as total_qty,
               SUM(total) as total_cost
        FROM purchase_order_items
        WHERE po_id = {$po_id}
        AND COALESCE(is_receive_added, 0) = 0
    ");
    
    if ($recalc_query && $recalc_query->num_rows > 0) {
        $recalc_data = $recalc_query->fetch_assoc();
        $new_total_items = (int)$recalc_data['total_items'];
        $new_total_qty = (int)$recalc_data['total_qty'];
        $new_total_cost = (float)($recalc_data['total_cost'] ?? 0);
        
        $update_totals_sql = "UPDATE purchase_orders 
                             SET total_items = {$new_total_items},
                                 total_qty = {$new_total_qty},
                                 total_cost = {$new_total_cost}
                             WHERE id = {$po_id}";
        
        if (!$conn->query($update_totals_sql)) {
            throw new Exception("Failed to update totals: " . $conn->error);
        }
    }

    // Recalculate and update PO status in database if items or quantities were modified
    $po_status_query = $conn->query("SELECT status FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
    if ($po_status_query && $po_status_row = $po_status_query->fetch_assoc()) {
        $current_po_status = $po_status_row['status'];
        if (!in_array(strtolower($current_po_status), ['canceled', 'cancelled', 'closed'])) {
            $all_items_q = $conn->query("
                SELECT poi.quantity, poi.serial_number, poi.received_qty, i.has_serial
                FROM purchase_order_items poi
                LEFT JOIN items i ON poi.family_code = i.family_code
                WHERE poi.po_id = {$po_id}
            ");
            $calc_ordered = 0;
            $calc_received = 0;
            if ($all_items_q) {
                while ($item_row = $all_items_q->fetch_assoc()) {
                    $calc_ordered += (int)$item_row['quantity'];
                    if ((int)($item_row['has_serial'] ?? 0) === 1) {
                        $sn = trim($item_row['serial_number'] ?? '');
                        if (!empty($sn)) {
                            $sn_arr = (strpos($sn, "\n") !== false) ? explode("\n", $sn) : explode(",", $sn);
                            $calc_received += count(array_filter(array_map('trim', $sn_arr)));
                        }
                    } else {
                        $calc_received += (int)($item_row['received_qty'] ?? 0);
                    }
                }
            }

            if ($calc_received < $calc_ordered && $calc_received > 0) {
                $conn->query("UPDATE purchase_orders SET status = 'Incomplete' WHERE id = {$po_id}");
            } elseif ($calc_received >= $calc_ordered && $calc_ordered > 0) {
                $conn->query("UPDATE purchase_orders SET status = 'Received' WHERE id = {$po_id}");
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Modifications saved successfully.']);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
