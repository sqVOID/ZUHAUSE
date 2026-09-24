<?php
require_once 'session_check.php';
include 'config.php';

// Validate ID
$po_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($po_id <= 0) {
    header('Location: purchaseorder.php');
    exit;
}

$from_param = isset($_GET['from']) ? $_GET['from'] : '';
$specific_branch = isset($_GET['branch']) ? trim($_GET['branch']) : '';
$is_branch_view = ($from_param === 'purchaseorderreceive' && !empty($specific_branch));

// Fetch PO header
$sql = "SELECT * FROM purchase_orders WHERE id = $po_id LIMIT 1";
$po_result = $conn->query($sql);
if (!$po_result || $po_result->num_rows === 0) {
    header('Location: purchaseorder.php');
    exit;
}
$po = $po_result->fetch_assoc();

// Branch access check
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

// Only Super-Admin can view all purchase orders
// Sub-admin should be restricted to their assigned branches
if (strcasecmp($system_level, 'Super-Admin') !== 0) {
    $po_branch_code = $po['created_by_branch'] ?? '';

    // Handle multiple branches (comma-separated)
    $branch_names = array_map('trim', explode(',', $user_branch));
    $user_branch_codes = [];

    foreach ($branch_names as $branch_name) {
        if (!empty($branch_name)) {
            $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($branch_name) . "' LIMIT 1");
            if ($branch_query && $branch_query->num_rows > 0) {
                $user_branch_codes[] = $branch_query->fetch_assoc()['branch_code'];
            }
        }
    }

    // Check if PO branch is in user's allowed branches
    $access_granted = false;
    foreach ($user_branch_codes as $allowed_code) {
        if (strcasecmp($po_branch_code, $allowed_code) === 0) {
            $access_granted = true;
            break;
        }
    }

    if (!$access_granted) {
        header('Location: purchaseorder.php');
        exit;
    }
}

// Ensure purchase_order_items table exists
$conn->query("CREATE TABLE IF NOT EXISTS purchase_order_items (
    id              INT(11) AUTO_INCREMENT PRIMARY KEY,
    po_id           INT(11) NOT NULL,
    po_number       VARCHAR(50) NOT NULL,
    item_no         INT(11),
    family_code     VARCHAR(100),
    category        VARCHAR(100),
    brand           VARCHAR(150),
    item_model      VARCHAR(255),
    item_description TEXT,
    quantity        INT(11) DEFAULT 0,
    cost            DECIMAL(12,2) DEFAULT 0.00,
    total           DECIMAL(12,2) DEFAULT 0.00,
    serial_number   TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Add family_code column if it doesn't exist
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS family_code VARCHAR(100) AFTER item_no");

// Add serial_number column if it doesn't exist
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS serial_number TEXT");

// Add received_qty column if it doesn't exist (for non-serialized items)
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS received_qty INT(11) DEFAULT NULL");

// Add receiving_remarks column to purchase_orders table if it doesn't exist
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS receiving_remarks TEXT");

// Add reason_to_modify column to purchase_orders table if it doesn't exist
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS reason_to_modify TEXT");

// Branch-specific invoice/remarks when modifying from receive list
$display_invoice_number = $po['invoice_number'] ?? '';
$display_receiving_remarks = $po['receiving_remarks'] ?? '';
if ($is_branch_view) {
    $branch_details_query = $conn->query("
        SELECT invoice_number, receiving_remarks
        FROM purchase_order_allocations
        WHERE po_id = {$po_id}
        AND branch_name = '" . $conn->real_escape_string($specific_branch) . "'
        LIMIT 1
    ");
    if ($branch_details_query && $branch_details_query->num_rows > 0) {
        $branch_details = $branch_details_query->fetch_assoc();
        $display_invoice_number = $branch_details['invoice_number'] ?? '';
        $display_receiving_remarks = $branch_details['receiving_remarks'] ?? '';
    } else {
        $display_invoice_number = '';
        $display_receiving_remarks = '';
    }
}

// Fetch PO items with serialization info
$raw_item_rows = [];
if ($is_branch_view) {
    // Allocated items for this branch + emergency receive-added items (no allocation)
    $specific_branch_esc = $conn->real_escape_string($specific_branch);

    $allocated_items_sql = "
        SELECT poi.id, poi.po_id, poi.po_number, poi.item_no, poi.family_code, poi.created_at,
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
               END), MAX(i.has_serial_number), 0) as has_serial_number,
               COALESCE(MAX(i.department), '') as department,
               poa.quantity as allocated_quantity,
               poa.quantity as quantity,
               COALESCE(NULLIF(poa.cost, 0), poi.cost, 0) as cost,
               (poa.quantity * COALESCE(NULLIF(poa.cost, 0), poi.cost, 0)) as total,
               poa.branch_name,
               COALESCE(NULLIF(poi.item_model, ''), NULLIF(poi.item_model, '-'), poa.item_model) as item_model,
               COALESCE(NULLIF(poi.item_description, ''), NULLIF(poi.item_description, '-'), poa.item_description) as item_description,
               COALESCE(poa.serial_number, poi.serial_number) as serial_number,
               COALESCE(poa.imei_2, poi.imei_2) as imei_2,
               COALESCE(poa.received_qty, 0) as received_qty,
               0 as is_receive_added
        FROM purchase_order_allocations poa
        LEFT JOIN purchase_order_items poi ON poa.po_id = poi.po_id
            AND poa.family_code COLLATE utf8mb4_general_ci = poi.family_code COLLATE utf8mb4_general_ci
            AND (
                poa.item_model IS NULL OR poa.item_model = '' OR poa.item_model = '-'
                OR poi.item_model IS NULL OR poi.item_model = '' OR poi.item_model = '-'
                OR poa.item_model COLLATE utf8mb4_general_ci = poi.item_model COLLATE utf8mb4_general_ci
            )
        LEFT JOIN items i ON (
            (poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci)
            OR (poi.family_code COLLATE utf8mb4_general_ci = i.family_code COLLATE utf8mb4_general_ci)
        ) AND i.status = 'Active'
        WHERE poa.po_id = $po_id
        AND poa.branch_name COLLATE utf8mb4_general_ci = '{$specific_branch_esc}' COLLATE utf8mb4_general_ci
        GROUP BY poa.id, poi.id
        ORDER BY poi.item_no ASC
    ";
    $allocated_result = $conn->query($allocated_items_sql);
    if ($allocated_result) {
        while ($row = $allocated_result->fetch_assoc()) {
            $raw_item_rows[] = $row;
        }
    }

    $receive_added_sql = "
        SELECT poi.id, poi.po_id, poi.po_number, poi.item_no, poi.family_code, poi.created_at,
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
               END), MAX(i.has_serial_number), 0) as has_serial_number,
               COALESCE(MAX(i.department), '') as department,
               poi.quantity as allocated_quantity,
               poi.quantity as quantity,
               COALESCE(poi.cost, 0) as cost,
               (poi.quantity * COALESCE(poi.cost, 0)) as total,
               poi.receiving_branch as branch_name,
               poi.item_model,
               poi.item_description,
               poi.serial_number,
               poi.imei_2,
               COALESCE(poi.received_qty, 0) as received_qty,
               1 as is_receive_added
        FROM purchase_order_items poi
        LEFT JOIN items i ON (
            (poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci)
            OR (poi.family_code COLLATE utf8mb4_general_ci = i.family_code COLLATE utf8mb4_general_ci)
        ) AND i.status = 'Active'
        WHERE poi.po_id = $po_id
        AND COALESCE(poi.is_receive_added, 0) = 1
        AND poi.receiving_branch COLLATE utf8mb4_general_ci = '{$specific_branch_esc}' COLLATE utf8mb4_general_ci
        GROUP BY poi.id
        ORDER BY poi.item_no ASC
    ";
    $receive_added_result = $conn->query($receive_added_sql);
    if ($receive_added_result) {
        while ($row = $receive_added_result->fetch_assoc()) {
            $raw_item_rows[] = $row;
        }
    }

    usort($raw_item_rows, function ($a, $b) {
        return (int) ($a['item_no'] ?? 0) <=> (int) ($b['item_no'] ?? 0);
    });
} else {
    $items_result = $conn->query("
        SELECT poi.*,
               COALESCE(MAX(CASE 
                   WHEN poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' 
                        AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                   THEN i.has_serial 
                   ELSE 0 
               END), MAX(i.has_serial), 0) as has_serial,
               COALESCE(MAX(CASE 
                   WHEN poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' 
                        AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                   THEN i.has_serial_2 
                   ELSE 0 
               END), MAX(i.has_serial_2), 0) as has_serial_2,
               COALESCE(MAX(CASE 
                   WHEN poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' 
                        AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci
                   THEN i.has_serial_number 
                   ELSE 0 
               END), MAX(i.has_serial_number), 0) as has_serial_number,
               COALESCE(MAX(i.department), '') as department
        FROM purchase_order_items poi
        LEFT JOIN items i ON (
            (poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' AND poi.item_model COLLATE utf8mb4_general_ci = i.item_code COLLATE utf8mb4_general_ci)
            OR (poi.family_code COLLATE utf8mb4_general_ci = i.family_code COLLATE utf8mb4_general_ci)
        )
        WHERE poi.po_id = $po_id
        GROUP BY poi.id
        ORDER BY poi.item_no ASC
    ");
    if ($items_result) {
        while ($row = $items_result->fetch_assoc()) {
            $raw_item_rows[] = $row;
        }
    }
}

// Branch allocations store serial numbers after receiving (not always in purchase_order_items)
$allocation_serials_by_family = [];
$allocation_imei2_by_family = [];
$allocation_received_by_family = [];
$allocation_details_by_family = [];

if (!$is_branch_view) {
    $allocations_result = $conn->query("
        SELECT family_code, serial_number, imei_2, received_qty, item_model, item_description
        FROM purchase_order_allocations
        WHERE po_id = $po_id
    ");
    if ($allocations_result) {
        while ($alloc = $allocations_result->fetch_assoc()) {
            $family_code = $alloc['family_code'] ?? '';
            if ($family_code === '') {
                continue;
            }

            if (!empty($alloc['item_model']) && $alloc['item_model'] !== '-') {
                if (!isset($allocation_details_by_family[$family_code])) {
                    $allocation_details_by_family[$family_code] = [
                        'item_model' => $alloc['item_model'],
                        'item_description' => $alloc['item_description'] ?? ''
                    ];
                }
            }

            if (!empty($alloc['serial_number'])) {
                if (!isset($allocation_serials_by_family[$family_code])) {
                    $allocation_serials_by_family[$family_code] = [];
                }
                $serials = trim($alloc['serial_number']);
                if (strpos($serials, "\n") !== false) {
                    $serial_array = explode("\n", $serials);
                } else {
                    $serial_array = explode(",", $serials);
                }
                $serial_array = array_filter(array_map('trim', $serial_array));
                $allocation_serials_by_family[$family_code] = array_merge(
                    $allocation_serials_by_family[$family_code],
                    $serial_array
                );
            }

            if (!empty($alloc['imei_2'])) {
                if (!isset($allocation_imei2_by_family[$family_code])) {
                    $allocation_imei2_by_family[$family_code] = [];
                }
                $serials2 = trim($alloc['imei_2']);
                if (strpos($serials2, "\n") !== false) {
                    $serial2_array = explode("\n", $serials2);
                } else {
                    $serial2_array = explode(",", $serials2);
                }
                $serial2_array = array_filter(array_map('trim', $serial2_array));
                $allocation_imei2_by_family[$family_code] = array_merge(
                    $allocation_imei2_by_family[$family_code],
                    $serial2_array
                );
            }

            if (!isset($allocation_received_by_family[$family_code])) {
                $allocation_received_by_family[$family_code] = 0;
            }
            $allocation_received_by_family[$family_code] += (int) ($alloc['received_qty'] ?? 0);
        }
    }
}

$items = [];
$grand_total = 0;
$total_received_qty = 0;
$total_ordered_qty = 0;
$has_serialized_items = false;

foreach ($raw_item_rows as $item) {
    $family_code = $item['family_code'] ?? '';

    // For non-branch views, merge allocation data when missing on PO items
    if (!$is_branch_view) {
        if (empty($item['serial_number']) && !empty($allocation_serials_by_family[$family_code])) {
            $unique_serials = array_values(array_unique($allocation_serials_by_family[$family_code]));
            $item['serial_number'] = implode("\n", $unique_serials);
        }

        if (empty($item['imei_2']) && !empty($allocation_imei2_by_family[$family_code])) {
            $unique_serials2 = array_values(array_unique($allocation_imei2_by_family[$family_code]));
            $item['imei_2'] = implode("\n", $unique_serials2);
        }

        if (
            (!empty($allocation_details_by_family[$family_code]))
            && (empty($item['item_model']) || $item['item_model'] === '-')
        ) {
            $item['item_model'] = $allocation_details_by_family[$family_code]['item_model'];
            $item['item_description'] = $allocation_details_by_family[$family_code]['item_description'];
        }

        if (
            (int) ($item['has_serial'] ?? 0) !== 1
            && empty($item['received_qty'])
            && !empty($allocation_received_by_family[$family_code])
        ) {
            $item['received_qty'] = $allocation_received_by_family[$family_code];
        }
    }

    if ($is_branch_view && isset($item['allocated_quantity'])) {
        $item_quantity = (int) $item['allocated_quantity'];
        $item['quantity'] = $item_quantity;
        $item['total'] = (float) $item['cost'] * $item_quantity;
    } else {
        $item_quantity = (int) $item['quantity'];
    }

    $grand_total += (float) $item['total'];
    $total_ordered_qty += $item_quantity;

    // Count received quantity
    $received_count = 0;
    if ($item['has_serial'] == 1 || !empty($item['has_serial_number'])) {
        if (!empty($item['serial_number'])) {
            $serials = trim($item['serial_number']);
            if (strpos($serials, "\n") !== false) {
                $serial_array = explode("\n", $serials);
            } else {
                $serial_array = explode(",", $serials);
            }
            $serial_array = array_filter(array_map('trim', $serial_array));
            $received_count = count($serial_array);
        }
    } else {
        $received_count = (int) ($item['received_qty'] ?? 0);
    }
    $item['calculated_received_qty'] = $received_count;
    $total_received_qty += $received_count;

    if ($item['has_serial'] == 1 || !empty($item['has_serial_number'])) {
        $has_serialized_items = true;
    }
    $items[] = $item;
}

// Status badge class
$status = $po['status'] ?? 'Pending';
$badge_class = 'badge-pending';

// Transform status display: RECEIVED -> COMPLETED, PENDING -> OPEN
$display_status = $status;
if (strcasecmp($status, 'Received') === 0) {
    $display_status = 'Completed';
    $badge_class = 'badge-received';
} elseif (strcasecmp($status, 'Pending') === 0) {
    $display_status = 'Open';
    $badge_class = 'badge-pending';
} elseif (strcasecmp($status, 'Completed') === 0) {
    $badge_class = 'badge-completed';
} elseif (strcasecmp($status, 'Incomplete') === 0) {
    $badge_class = 'badge-incomplete';
} elseif (strcasecmp($status, 'CANCELED') === 0 || strcasecmp($status, 'Cancelled') === 0) {
    $badge_class = 'badge-decline';
} elseif (strcasecmp($status, 'Cancelled') === 0) {
    $badge_class = 'badge-cancelled';
}

// Format dates
$po_date_fmt = !empty($po['po_date']) ? date('n/j/Y', strtotime($po['po_date'])) : '-';
$due_date_fmt = !empty($po['payment_due_date']) ? date('F j, Y', strtotime($po['payment_due_date'])) : '-';
$created_fmt = !empty($po['created_at']) ? date('n/j/Y, g:i A', strtotime($po['created_at'])) : '-';

// Terms display
$terms_raw = $po['terms'] ?? '';
if (is_numeric($terms_raw)) {
    $terms_display = $terms_raw . ' Days';
} elseif (strtolower($terms_raw) === 'cod') {
    $terms_display = 'Cash on Delivery';
} else {
    $terms_display = $terms_raw ?: '-';
}

// Created by and branch information from database
$created_by = $po['created_by'] ?? 'Unknown User';
$created_by_branch = $po['created_by_branch'] ?? '';

// If created_by is empty, fall back to session (for backward compatibility with old records)
if (empty($created_by) || $created_by === 'Unknown User') {
    $created_by = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin User');
}

// Get branch name from created_by_branch code
$created_branch_name = '';
if (!empty($created_by_branch)) {
    if ($created_by_branch === 'ALL') {
        $created_branch_name = 'ALL BRANCHES';
    } else {
        $branch_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '" . $conn->real_escape_string($created_by_branch) . "' LIMIT 1");
        if ($branch_query && $branch_query->num_rows > 0) {
            $branch_result = $branch_query->fetch_assoc();
            $created_branch_name = $branch_result['branch_name'];
        }
    }
}

// Prefer allocated branches over created_by_branch for display
$allocated_branches = [];
$allocated_branches_query = $conn->query("
    SELECT DISTINCT branch_name
    FROM purchase_order_allocations
    WHERE po_id = {$po_id}
    ORDER BY branch_name ASC
");
if ($allocated_branches_query && $allocated_branches_query->num_rows > 0) {
    while ($branch_row = $allocated_branches_query->fetch_assoc()) {
        $allocated_branches[] = $branch_row['branch_name'];
    }
}

$display_branch_name = '';
if ($is_branch_view) {
    $display_branch_name = $specific_branch;
} elseif (!empty($allocated_branches)) {
    $display_branch_name = implode(', ', $allocated_branches);
} elseif (!empty($created_branch_name)) {
    $display_branch_name = $created_branch_name;
}

// Workflow action tracking
$received_by = $po['received_by'] ?? null;
$received_at = $po['received_at'] ?? null;
$received_by_branch = $po['received_by_branch'] ?? null;
$declined_by = $po['declined_by'] ?? null;
$declined_at = $po['declined_at'] ?? null;
$declined_by_branch = $po['declined_by_branch'] ?? null;
$completed_by = $po['completed_by'] ?? null;
$completed_at = $po['completed_at'] ?? null;
$completed_by_branch = $po['completed_by_branch'] ?? null;
$incomplete_by = $po['incomplete_by'] ?? null;
$incomplete_at = $po['incomplete_at'] ?? null;
$incomplete_by_branch = $po['incomplete_by_branch'] ?? null;

$received_fmt = !empty($received_at) ? date('n/j/Y, g:i A', strtotime($received_at)) : '';
$declined_fmt = !empty($declined_at) ? date('n/j/Y, g:i A', strtotime($declined_at)) : '';
$completed_fmt = !empty($completed_at) ? date('n/j/Y, g:i A', strtotime($completed_at)) : '';
$incomplete_fmt = !empty($incomplete_at) ? date('n/j/Y, g:i A', strtotime($incomplete_at)) : '';

// Get received branch name from branch code
$received_branch_name = '';
if (!empty($received_by_branch)) {
    if ($received_by_branch === 'ALL') {
        $received_branch_name = 'ALL BRANCHES';
    } else {
        $received_branch_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '" . $conn->real_escape_string($received_by_branch) . "' LIMIT 1");
        if ($received_branch_query && $received_branch_query->num_rows > 0) {
            $received_branch_result = $received_branch_query->fetch_assoc();
            $received_branch_name = $received_branch_result['branch_name'];
        }
    }
}

// Get declined branch name from branch code
$declined_branch_name = '';
if (!empty($declined_by_branch)) {
    if ($declined_by_branch === 'ALL') {
        $declined_branch_name = 'ALL BRANCHES';
    } else {
        $declined_branch_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '" . $conn->real_escape_string($declined_by_branch) . "' LIMIT 1");
        if ($declined_branch_query && $declined_branch_query->num_rows > 0) {
            $declined_branch_result = $declined_branch_query->fetch_assoc();
            $declined_branch_name = $declined_branch_result['branch_name'];
        }
    }
}

// Get completed branch name from branch code
$completed_branch_name = '';
if (!empty($completed_by_branch)) {
    if ($completed_by_branch === 'ALL') {
        $completed_branch_name = 'ALL BRANCHES';
    } else {
        $completed_branch_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '" . $conn->real_escape_string($completed_by_branch) . "' LIMIT 1");
        if ($completed_branch_query && $completed_branch_query->num_rows > 0) {
            $completed_branch_result = $completed_branch_query->fetch_assoc();
            $completed_branch_name = $completed_branch_result['branch_name'];
        }
    }
}

// Get incomplete branch name from branch code
$incomplete_branch_name = '';
if (!empty($incomplete_by_branch)) {
    if ($incomplete_by_branch === 'ALL') {
        $incomplete_branch_name = 'ALL BRANCHES';
    } else {
        $incomplete_branch_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '" . $conn->real_escape_string($incomplete_by_branch) . "' LIMIT 1");
        if ($incomplete_branch_query && $incomplete_branch_query->num_rows > 0) {
            $incomplete_branch_result = $incomplete_branch_query->fetch_assoc();
            $incomplete_branch_name = $incomplete_branch_result['branch_name'];
        }
    }
}

// Get edit history for this PO
$edit_history = [];
$edit_query = $conn->query("SELECT edit_reason, edited_by, edited_at FROM purchase_order_edit_history WHERE po_id = {$po_id} ORDER BY edited_at ASC");
if ($edit_query && $edit_query->num_rows > 0) {
    while ($edit_row = $edit_query->fetch_assoc()) {
        $edit_history[] = [
            'reason' => $edit_row['edit_reason'],
            'edited_by' => $edit_row['edited_by'],
            'edited_at' => date('n/j/Y, g:i A', strtotime($edit_row['edited_at']))
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modify Purchase Order – <?php echo htmlspecialchars($po['po_number']); ?></title>
    <style>
        :root {
            /* Brand Colors - Navy & Gold Theme */
            --color-navy: #0d3347;
            --color-navy-dark: #081f2d;
            --color-navy-light: #164460;
            --color-gold: #b08a52;
            --color-gold-light: #c9a46e;
            --color-gold-pale: #f5ede0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0ff;
            zoom: 77%;
        }

        /* ── Header ── */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: white;
            display: flex;
            align-items: center;
            padding: 0 20px;
            z-index: 1000;
            gap: 30px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
        }

        .logo {
            margin-left: -20px;
            height: 50px;
        }

        .menu-btn {
            width: 24px;
            height: 22px;
            cursor: pointer;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .menu-btn span {
            display: block;
            width: 18px;
            height: 2px;
            background-color: #333;
            position: absolute;
            transition: all 0.3s ease;
        }

        .menu-btn span:nth-child(1) {
            top: 0;
        }

        .menu-btn span:nth-child(2) {
            top: 50%;
            transform: translateY(-50%);
        }

        .menu-btn span:nth-child(3) {
            bottom: 0;
        }

        .menu-btn.active span:nth-child(1) {
            top: 50%;
            transform: translateY(-50%) rotate(45deg);
        }

        .menu-btn.active span:nth-child(2) {
            opacity: 0;
        }

        .menu-btn.active span:nth-child(3) {
            bottom: 50%;
            transform: translateY(50%) rotate(-45deg);
        }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            left: 0;
            top: 60px;
            width: 250px;
            height: calc(149.3vh - 60px);
            background-color: white;
            box-shadow: 2px 0 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            overflow-y: auto;
            padding: 20px 0;
            z-index: 999;
        }

        .sidebar.hidden {
            transform: translateX(-100%);
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #666;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.2s;
            font-size: 14px;
        }

        .menu-item:hover {
            background-color: #f5f5f5;
        }

        .menu-item.active {
            background-color: var(--color-gold-pale);
            color: var(--color-navy);
            font-weight: bold;
        }

        .menu-item svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .menu-section-title {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            color: #666;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .menu-section-title svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .menu-section-title .arrow {
            transition: transform 0.3s ease;
        }

        .menu-section.collapsed .arrow {
            transform: rotate(-90deg);
        }

        .submenu {
            padding-left: 20px;
            max-height: 500px;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .menu-section.collapsed .submenu {
            max-height: 0;
        }

        .submenu .menu-item {
            padding: 10px 20px;
            font-size: 13px;
        }

        /* ── Main Content ── */
        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* ── Page top bar ── */
        .page-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .back-link {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 15px;
            font-weight: 600;
            color: #222;
            cursor: pointer;
            background: none;
            border: none;
            text-decoration: none;
        }

        .back-link svg {
            width: 18px;
            height: 18px;
            fill: #222;
        }

        .top-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-print {
            padding: 9px 20px;
            background: white;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }

        .btn-print:hover {
            background: #f5f5f5;
        }

        .btn-print svg {
            width: 16px;
            height: 16px;
            fill: #444;
        }

        .btn-receive {
            padding: 9px 22px;
            background: #1a7a35;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-receive:hover {
            background: #155e28;
        }

        .btn-decline {
            padding: 9px 22px;
            background: #c62828;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-decline:hover {
            background: #b71c1c;
        }

        .btn-save-modification {
            padding: 9px 22px;
            background: #1976d2;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-save-modification:hover {
            background: #1565c0;
        }

        /* ── Status bar ── */
        .status-bar {
            background: white;
            border: 1px solid #d0f0c0;
            border-radius: 6px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 16px;
        }

        .status-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .status-label {
            font-size: 11px;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .badge-received {
            background: #d4edda;
            color: #1a7a35;
            border: 1px solid #b8dfc6;
        }

        .badge-completed {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa0;
        }

        .badge-incomplete {
            background: #ffeaa0;
            color: #856404;
            border: 1px solid #ffdd57;
        }

        .badge-decline {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        .badge-cancelled {
            background: #e2e3e5;
            color: #41464b;
            border: 1px solid #d3d6d8;
        }

        .status-divider {
            width: 1px;
            height: 40px;
            background: #ccc;
        }

        .po-number-label {
            font-size: 11px;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .po-number-value {
            font-size: 14px;
            font-weight: 700;
            color: #111;
        }

        /* ── Three-column cards row ── */
        .cards-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .info-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 18px 20px;
        }

        .info-card h4 {
            font-size: 13px;
            font-weight: 700;
            color: #222;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e8e8e8;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
        }

        .detail-grid.two-col {
            grid-template-columns: 1fr 1fr;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .detail-item.full {
            grid-column: 1 / -1;
        }

        .detail-key {
            font-size: 10px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .detail-val {
            font-size: 13px;
            font-weight: 700;
            color: #111;
        }

        .detail-val.light {
            font-weight: 400;
            color: #444;
        }

        /* Workflow History card */
        .workflow-entry {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .wf-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #1a7a35;
            margin-top: 4px;
            flex-shrink: 0;
        }

        .wf-text {}

        .wf-action {
            font-size: 13px;
            font-weight: 700;
            color: #111;
        }

        .wf-by {
            font-size: 12px;
            color: #555;
            margin-top: 2px;
        }

        .wf-date {
            font-size: 11px;
            color: #999;
            margin-top: 1px;
        }

        /* ── PO Items section ── */
        .items-section {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 20px;
        }

        .items-section h4 {
            font-size: 14px;
            font-weight: 700;
            color: #222;
            margin-bottom: 16px;
        }

        .items-table-wrapper {
            overflow-x: auto;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table thead {
            background: var(--color-gold-pale);
        }

        .items-table th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000000;
            border-top: 1px solid #ccc;
            border-bottom: 1px solid #ccc;
        }

        .items-table th:first-child {
            border-left: 1px solid #ccc;
        }

        .items-table th:last-child {
            border-right: 1px solid #ccc;
        }

        .items-table td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border-bottom: 1px solid #ccc;
            text-align: center;
        }

        .items-table td:first-child {
            border-left: 1px solid #ccc;
        }

        .items-table td:last-child {
            border-right: 1px solid #ccc;
        }

        .items-table tbody tr:hover {
            background: #fafafa;
        }

        /* Staged for deletion styling */
        .staged-for-deletion {
            background-color: #ffebee !important;
            opacity: 0.6;
            position: relative;
        }

        .staged-for-deletion td {
            text-decoration: line-through;
            color: #999 !important;
        }

        .staged-for-deletion .btn-delete-item {
            text-decoration: none;
        }

        .items-table tbody tr:hover {
            background: #fafafa;
        }

        /* Add IMEI Button */
        .btn-add-serial-number {
            background: #333;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-add-serial-number:hover {
            background: #555;
        }

        /* Add Item Model Button */
        .btn-add-item-model {
            background: #1a7a35;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-add-item-model:hover {
            background: #155e28;
        }

        /* Select Item Button in Modal */
        .btn-select-item {
            background: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-select-item:hover {
            background: #2e6b2e;
        }

        /* Serial Modal */
        .serial-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            align-items: center;
            justify-content: center;
        }

        .serial-modal-content {
            background-color: #fefefe;
            margin: auto;
            border: 1px solid #888;
            width: 80%;
            max-width: 800px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: fadeIn 0.3s;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }

        .serial-modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }

        .serial-modal-body {
            padding: 20px 25px;
            overflow-y: auto;
            flex: 1;
        }

        .serial-modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #eee;
            text-align: right;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .serial-input-section {
            margin-bottom: 20px;
        }

        .serial-count-info {
            margin-bottom: 15px;
            text-align: left;
        }

        .serial-input-section label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .serial-input-section input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .serial-input-section input:focus {
            border-color: #408140;
            outline: none;
        }

        .serial-list-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .serial-list-table thead {
            background: var(--color-gold-pale);
        }

        .serial-list-table th {
            text-align: center;
            padding: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border: 1px solid #ccc;
        }

        .serial-list-table td {
            padding: 10px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
        }

        .serial-list-table tbody tr:hover {
            background: #fafafa;
        }

        .serial-input-field {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
        }

        .serial-input-field:focus {
            outline: none;
            border-color: #666;
        }

        .serial-table-input {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
            box-sizing: border-box;
            background-color: #fff;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .serial-table-input:focus {
            outline: none;
            border-color: #408140;
            box-shadow: 0 0 0 2px rgba(64, 129, 64, 0.2);
            background-color: #fff;
        }

        /* Item Type Dropdown Styling */
        .item-type-dropdown {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
            background-color: white;
            cursor: pointer;
            transition: all 0.2s;
        }

        .item-type-dropdown:hover {
            border-color: #b08a52;
        }

        .item-type-dropdown:focus {
            outline: none;
            border-color: #b08a52;
            box-shadow: 0 0 0 2px rgba(176, 138, 82, 0.1);
        }

        .btn-remove-from-list {
            background: #c62828;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            font-size: 12px;
            cursor: pointer;
        }

        .btn-remove-from-list:hover {
            background: #b71c1c;
        }

        .btn-modal-back {
            background: #f5f5f5;
            color: #333;
            border: 1px solid #ccc;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-modal-back:hover {
            background: #e0e0e0;
        }

        .btn-modal-save {
            background: var(--color-navy);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-modal-save:hover {
            background: var(--color-navy-dark);
        }

        /* Edit and Delete Serial Buttons */
        .btn-edit-serial,
        .btn-edit-item-type-status,
        .btn-delete-item {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-edit-serial {
            color: #1a7a35;
        }

        .btn-edit-serial:hover {
            background: #e8f5e9;
            color: #155e28;
        }

        .btn-edit-item-type-status {
            color: #b08a52;
        }

        .btn-edit-item-type-status:hover {
            background: #f5ede0;
            color: #8b6f3f;
        }

        .btn-delete-item {
            color: #c62828;
        }

        .btn-delete-item:hover {
            background: #ffebee;
            color: #b71c1c;
        }

        /* Searchable Select Styles */
        .select-wrapper {
            position: relative;
        }

        .select-search-input {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: white;
            font-family: Arial, sans-serif;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .select-search-input:focus {
            border-color: #408140;
        }

        .select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #d0d0d0;
            border-top: none;
            border-radius: 0 0 4px 4px;
            z-index: 1000;
            display: none;
        }

        .select-dropdown.active {
            display: block;
        }

        .select-option {
            padding: 10px 12px;
            cursor: pointer;
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
        }

        .select-option:last-child {
            border-bottom: none;
        }

        .select-option:hover {
            background: #f5f5f5;
        }

        .select-option.selected {
            background: #e8e8e8;
            font-weight: 600;
        }

        .select-option.hidden {
            display: none;
        }

        .btn-edit-serial svg,
        .btn-edit-item-type-status svg,
        .btn-delete-item svg {
            width: 16px;
            height: 16px;
        }

        /* Edit and Remove Item Model Buttons */
        .btn-edit-item-model,
        .btn-remove-item-model {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-edit-item-model {
            color: #1976d2;
        }

        .btn-edit-item-model:hover {
            background: #e3f2fd;
            color: #1565c0;
        }

        .btn-remove-item-model {
            color: #ed6c02;
        }

        .btn-remove-item-model:hover {
            background: #fff4e5;
            color: #e65100;
        }

        .btn-edit-item-model svg,
        .btn-remove-item-model svg {
            width: 16px;
            height: 16px;
        }

        .items-table tbody tr:hover {
            background: #fafafa;
        }

        /* Total Quantity row */
        .total-quantity-row td {
            border-bottom: 1px solid #ccc;
            border-top: 1px solid #ccc;
            font-size: 14px;
            padding: 12px;
            background: #f8f9fa;
            font-weight: 600;
        }

        .total-quantity-row td.tq-label {
            text-align: center;
            color: #000000;
        }

        .total-quantity-row td.tq-amount {
            color: #000000;
            text-align: center;
        }

        .total-quantity-row td:last-child {
            border-right: 1px solid #ccc;
        }

        .total-quantity-row td:first-child {
            border-left: 1px solid #ccc;
        }

        /* Grand Total row */
        .grand-total-row td {
            border-bottom: 1px solid #ccc;
            border-top: 1px solid #ccc;
            font-size: 14px;
            padding: 12px;
            background: #f8f9fa;
            font-weight: 600;
        }

        .grand-total-row td.gt-label {
            text-align: center;
            color: #000000;
        }

        .grand-total-row td.gt-amount {
            color: #000000;
            text-align: center;
        }

        .grand-total-row td:last-child {
            border-right: 1px solid #ccc;
        }

        .grand-total-row td:first-child {
            border-left: 1px solid #ccc;
        }

        .no-items {
            text-align: center;
            padding: 30px;
            color: #888;
            font-size: 13px;
        }

        /* ── Confirmation Modal ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-box {
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            padding: 36px 40px;
            max-width: 420px;
            width: 90%;
            text-align: center;
            animation: modalIn 0.2s ease;
        }

        @keyframes modalIn {
            from {
                transform: scale(0.88);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .modal-icon {
            font-size: 44px;
            margin-bottom: 14px;
        }

        .modal-title {
            font-size: 17px;
            font-weight: 700;
            color: #111;
            margin-bottom: 10px;
        }

        .modal-msg {
            font-size: 13px;
            color: #555;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .modal-btn {
            padding: 10px 28px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .modal-btn-cancel {
            background: #f0f0f0;
            color: #444;
        }

        .modal-btn-cancel:hover {
            background: #e0e0e0;
        }

        .modal-btn-confirm-green {
            background: #1a7a35;
            color: white;
        }

        .modal-btn-confirm-green:hover {
            background: #155e28;
        }

        .modal-btn-confirm-red {
            background: #c62828;
            color: white;
        }

        .modal-btn-confirm-red:hover {
            background: #b71c1c;
        }

        /* ── Print Styles ── */
        .print-area {
            display: none;
        }

        /* ── Animations ── */
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .print-area,
            .print-area * {
                visibility: visible;
            }

            .print-area {
                display: block !important;
                position: fixed;
                inset: 0;
                background: white;
                padding: 28px 36px;
                font-family: Arial, sans-serif;
                font-size: 11px;
                color: #111;
                zoom: 1;
            }

            .modal-overlay {
                display: none !important;
            }
        }

        @media (max-width: 900px) {
            .cards-row {
                grid-template-columns: 1fr;
            }
        }

        /* Comprehensive Responsive Styles */

        /* Large Desktop & Laptop (max-width: 1640px) */
        @media (max-width: 1640px) {
            .cards-row {
                gap: 12px;
            }

            .detail-grid {
                gap: 12px;
            }
        }

        /* Medium Desktop (max-width: 1366px) */
        @media (max-width: 1366px) {
            .cards-row {
                grid-template-columns: 1fr 1fr;
            }

            .detail-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* Tablet & Smaller Desktop (max-width: 1024px) */
        @media (max-width: 1024px) {
            .main-content {
                padding: 15px;
            }

            .cards-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .page-topbar {
                flex-wrap: wrap;
                gap: 10px;
            }

            .top-actions {
                flex-wrap: wrap;
                width: 100%;
            }

            .status-bar {
                flex-wrap: wrap;
                gap: 15px;
            }

            .status-divider {
                display: none;
            }

            .items-table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .items-table {
                min-width: 1000px;
            }

            .items-table th,
            .items-table td {
                font-size: 12px;
                padding: 10px 8px;
                white-space: nowrap;
            }
        }

        /* Small Tablet (max-width: 960px) */
        @media (max-width: 960px) {
            .status-bar {
                flex-direction: column;
                align-items: flex-start;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .items-table {
                zoom: 0.85;
            }

            .serial-modal-content {
                width: 95%;
            }
        }

        /* Mobile Devices (max-width: 768px) */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1500;
            }

            .sidebar.hidden {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 12px;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .page-topbar {
                flex-direction: column;
                align-items: stretch;
            }

            .back-link {
                font-size: 14px;
            }

            .top-actions {
                flex-direction: column;
                gap: 8px;
            }

            .btn-print,
            .btn-receive,
            .btn-decline,
            .btn-save-modification {
                width: 100%;
                justify-content: center;
                padding: 12px 20px;
                font-size: 14px;
            }

            .status-bar {
                padding: 12px 15px;
            }

            .cards-row {
                gap: 12px;
            }

            .info-card {
                padding: 15px;
            }

            .info-card h4 {
                font-size: 12px;
            }

            .items-section {
                padding: 15px;
            }

            .items-table {
                min-width: 900px;
                zoom: 0.75;
            }

            .items-table th,
            .items-table td {
                font-size: 11px;
                padding: 8px 6px;
            }

            .btn-add-serial-number,
            .btn-add-item-model {
                padding: 8px 12px;
                font-size: 11px;
            }

            .serial-modal-content {
                width: 98%;
                max-height: 95vh;
            }

            .serial-modal-header {
                padding: 15px 20px;
                font-size: 16px;
            }

            .serial-modal-body {
                padding: 15px 20px;
            }

            .serial-modal-footer {
                padding: 12px 20px;
                flex-direction: column;
                gap: 10px;
            }

            .btn-modal-back,
            .btn-modal-save {
                width: 100%;
            }
        }

        /* Small Mobile (max-width: 480px) */
        @media (max-width: 480px) {
            .header {
                height: 50px;
                padding: 0 10px;
                gap: 10px;
            }

            .logo {
                height: 35px;
            }

            .sidebar {
                top: 50px;
                height: calc(100vh - 50px);
                width: 220px;
            }

            .main-content {
                margin-top: 50px;
                padding: 10px;
            }

            .page-topbar {
                gap: 8px;
            }

            .back-link {
                font-size: 13px;
            }

            .btn-print,
            .btn-receive,
            .btn-decline,
            .btn-save-modification {
                padding: 10px 16px;
                font-size: 13px;
            }

            .status-bar {
                padding: 10px 12px;
            }

            .info-card {
                padding: 12px;
            }

            .items-section {
                padding: 12px;
            }

            .items-table {
                min-width: 800px;
                zoom: 0.7;
            }

            .items-table th,
            .items-table td {
                font-size: 10px;
                padding: 6px 4px;
            }

            .badge {
                font-size: 9px;
                padding: 2px 8px;
            }

            .serial-modal-header {
                font-size: 15px;
            }

            .btn-modal-back,
            .btn-modal-save {
                padding: 10px 16px;
                font-size: 13px;
            }
        }
    </style>
</head>

<body>

    <!-- Header -->
    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()">
            <span></span><span></span><span></span>
        </div>
        <!-- <img src="Icon/imslogo2.svg" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">

        <!-- Top Bar -->
        <div class="page-topbar">
            <a class="back-link" href="purchaseorderreceive.php">
                <svg viewBox="0 0 24 24">
                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z" />
                </svg>
                Modify Purchase Order
            </a>
            <div class="top-actions">
                <button class="btn-print" onclick="printWithLiveSerials()">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z" />
                    </svg>
                    Print
                </button>
                <button class="btn-save-modification" onclick="saveModification()">Save Modification</button>
                <?php if (strcasecmp($status, 'Received') !== 0 && strcasecmp($status, 'CANCELED') !== 0 && strcasecmp($status, 'Cancelled') !== 0): ?>
                    <button class="btn-receive" onclick="updateStatus('Received')">Receive</button>
                    <button class="btn-decline" onclick="updateStatus('CANCELED')">Cancel</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deletion Warning Banner (initially hidden) 
        <div id="deletionWarningBanner" style="display: none; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 12px 20px; margin-bottom: 16px; display: flex; align-items: center; gap: 12px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="#856404">
                <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
            </svg>
        
            <span style="flex: 1; font-size: 13px; color: #856404; font-weight: 600;">
                <span id="deletionCount">0</span> item(s) staged for deletion. Click "Save Modification" to permanently delete, or click the delete button again to undo.
            </span>
        </div>
        -->

        <!-- Status Bar -->
        <div class="status-bar">
            <div class="status-group">
                <span class="status-label">Status</span>
                <span class="badge <?php echo $badge_class; ?>"
                    id="status-badge"><?php echo htmlspecialchars(strtoupper($display_status)); ?></span>
            </div>
            <div class="status-divider"></div>
            <div class="status-group">
                <span class="po-number-label">PO Number</span>
                <span class="po-number-value"><?php echo htmlspecialchars($po['po_number']); ?></span>
            </div>
            <div class="status-divider"></div>
            <div class="status-group">
                <span class="po-number-label">Branch</span>
                <span class="po-number-value">
                    <?php
                    if (!empty($display_branch_name)) {
                        echo htmlspecialchars($display_branch_name);
                    } else {
                        echo 'No Allocations Yet';
                    }
                    ?>
                </span>
            </div>
        </div>

        <!-- Three Cards Row -->
        <div class="cards-row">

            <!-- PO Details Card -->
            <div class="info-card">
                <h4>Purchase Order Details</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-key">PO Number</span>
                        <input type="text" id="poNumberInput" value="<?php echo htmlspecialchars($po['po_number']); ?>"
                            readonly
                            style="width: 100%; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif; background-color: #f5f5f5; cursor: not-allowed;" />
                    </div>
                    <div class="detail-item">
                        <span class="detail-key">PO Date</span>
                        <input type="text" id="poDateInput"
                            value="<?php echo !empty($po['po_date']) ? date('Y-m-d', strtotime($po['po_date'])) : ''; ?>"
                            readonly
                            style="width: 100%; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif; background-color: #f5f5f5; cursor: not-allowed;" />
                    </div>
                    <div class="detail-item">
                        <span class="detail-key">Invoice Number</span>
                        <input type="text" id="invoiceNumberInput"
                            value="<?php echo htmlspecialchars($display_invoice_number); ?>"
                            placeholder="Enter invoice number"
                            style="width: 100%; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif;" />
                    </div>
                    <div class="detail-item" style="display: none;">
                        <span class="detail-key">Supplier Company Name</span>
                        <select id="supplierCompanyInput" class="searchable-select">
                            <option value="">Select Company Name</option>
                        </select>
                    </div>
                    <div class="detail-item" style="display: none;">
                        <span class="detail-key">Supplier Name</span>
                        <select id="supplierNameInput" class="searchable-select">
                            <option value="">Select Supplier Name</option>
                        </select>
                    </div>
                    <!-- Reason to Modify - Full width at bottom -->
                    <div class="detail-item full">
                        <span class="detail-key">Reason to Modify <span style="color: #c62828;">*</span></span>
                        <textarea id="reasonToModifyTextarea"
                            placeholder="Enter the reason for modifying this purchase order..."
                            style="width: 100%; min-height: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif; resize: vertical; margin-top: 5px;"><?php echo htmlspecialchars(($po['reason_to_modify'] ?? '')); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Payment Terms Card -->
            <div class="info-card">
                <h4>Payment Terms</h4>
                <div class="detail-grid two-col">
                    <div class="detail-item">
                        <span class="detail-key">Payment Days</span>
                        <select id="paymentDaysInput"
                            style="width: 100%; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif; background: white; color: #333;">
                            <option value="">Select Payment Terms</option>
                            <option value="30" <?php echo ($po['terms'] == '30') ? 'selected' : ''; ?>>30 Days</option>
                            <option value="15" <?php echo ($po['terms'] == '15') ? 'selected' : ''; ?>>15 Days</option>
                            <option value="60" <?php echo ($po['terms'] == '60') ? 'selected' : ''; ?>>60 Days</option>
                            <option value="90" <?php echo ($po['terms'] == '90') ? 'selected' : ''; ?>>90 Days</option>
                            <option value="cod" <?php echo (strtolower($po['terms']) === 'cod') ? 'selected' : ''; ?>>Cash
                                on Delivery</option>
                        </select>
                    </div>
                    <div class="detail-item">
                        <span class="detail-key">Payment Due Date</span>
                        <input type="date" id="paymentDueDateInput"
                            value="<?php echo !empty($po['payment_due_date']) ? date('Y-m-d', strtotime($po['payment_due_date'])) : ''; ?>"
                            style="width: 100%; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif;" />
                    </div>
                    <div class="detail-item full">
                        <span class="detail-key">PO Remarks</span>
                        <textarea id="remarksTextarea" placeholder="Enter remarks..."
                            style="width: 100%; min-height: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif; resize: vertical; margin-top: 5px;"><?php echo htmlspecialchars($po['remarks'] ?? ''); ?></textarea>
                    </div>
                    <!-- Receiving Remarks - Editable -->
                    <div class="detail-item full">
                        <span class="detail-key">Receiving Remarks</span>
                        <textarea id="receivingRemarksTextarea" placeholder="Enter receiving remarks..."
                            style="width: 100%; min-height: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif; resize: vertical; margin-top: 5px;"><?php echo htmlspecialchars($display_receiving_remarks); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Workflow History Card -->
            <div class="info-card">
                <h4>Workflow History</h4>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <!-- Created entry -->
                    <div class="workflow-entry">
                        <div class="wf-dot" style="background:#1a7a35;"></div>
                        <div class="wf-text">
                            <div class="wf-action">Created</div>
                            <div class="wf-by">by
                                <?php echo htmlspecialchars($created_by); ?><?php if (!empty($created_branch_name)): ?>
                                    (<?php echo htmlspecialchars($created_branch_name); ?>)<?php endif; ?>
                            </div>
                            <div class="wf-date"><?php echo $created_fmt; ?></div>
                        </div>
                    </div>

                    <?php if (strcasecmp($status, 'Incomplete') === 0 || !empty($completed_by)): ?>
                        <!-- Incomplete entry (only if it was actually incomplete) -->
                        <div class="workflow-entry">
                            <div class="wf-dot" style="background:#856404;"></div>
                            <div class="wf-text">
                                <div class="wf-action">Marked as Incomplete</div>
                                <?php if (!empty($incomplete_by)): ?>
                                    <div class="wf-by">by
                                        <?php echo htmlspecialchars($incomplete_by); ?>
                                        <?php if (!empty($incomplete_branch_name)): ?>
                                            (<?php echo htmlspecialchars($incomplete_branch_name); ?>)<?php endif; ?>
                                    </div>
                                    <div class="wf-date"><?php echo $incomplete_fmt; ?></div>
                                <?php else: ?>
                                    <div class="wf-by">during receiving process</div>
                                    <div class="wf-date">-</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($edit_history as $edit): ?>
                        <!-- Edit entry -->
                        <div class="workflow-entry">
                            <div class="wf-dot" style="background:#17a2b8;"></div>
                            <div class="wf-text">
                                <div class="wf-action">Edited</div>
                                <div class="wf-by">by <?php echo htmlspecialchars($edit['edited_by']); ?></div>
                                <div class="wf-date"><?php echo $edit['edited_at']; ?></div>
                                <div class="wf-reason" style="font-size:12px;color:#666;margin-top:2px;">Reason:
                                    <?php echo htmlspecialchars($edit['reason']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!empty($completed_by)): ?>
                        <!-- Completed entry -->
                        <div class="workflow-entry">
                            <div class="wf-dot" style="background:#0c5460;"></div>
                            <div class="wf-text">
                                <div class="wf-action">Completed</div>
                                <div class="wf-by">by
                                    <?php echo htmlspecialchars($completed_by); ?>
                                    <?php if (!empty($completed_branch_name)): ?>
                                        (<?php echo htmlspecialchars($completed_branch_name); ?>)<?php endif; ?>
                                </div>
                                <div class="wf-date"><?php echo $completed_fmt; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($received_by)): ?>
                        <!-- Received entry -->
                        <div class="workflow-entry">
                            <div class="wf-dot" style="background:#1a7a35;"></div>
                            <div class="wf-text">
                                <div class="wf-action">Received</div>
                                <div class="wf-by">by
                                    <?php echo htmlspecialchars($received_by); ?>
                                    <?php if (!empty($received_branch_name)): ?>
                                        (<?php echo htmlspecialchars($received_branch_name); ?>)<?php endif; ?>
                                </div>
                                <div class="wf-date"><?php echo $received_fmt; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($declined_by)): ?>
                        <!-- Canceled entry -->
                        <div class="workflow-entry">
                            <div class="wf-dot" style="background:#c62828;"></div>
                            <div class="wf-text">
                                <div class="wf-action">Canceled</div>
                                <div class="wf-by">by
                                    <?php echo htmlspecialchars($declined_by); ?>
                                    <?php if (!empty($declined_branch_name)): ?>
                                        (<?php echo htmlspecialchars($declined_branch_name); ?>)<?php endif; ?>
                                </div>
                                <div class="wf-date"><?php echo $declined_fmt; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- end .cards-row -->

        <!-- PO Items -->
        <div class="items-section">
            <h4>PO Items</h4>
            <?php if (count($items) > 0): ?>
                <div class="items-table-wrapper">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Family Code</th>
                                <th>Item Model</th>
                                <th>Edit Item</th>
                                <th>Item Description</th>
                                <th>IMEI</th>
                                <th>Edit IMEI</th>
                                <th>Edit Item Type/Status</th>
                                <th>Quantity</th>
                                <th>Cost</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr data-item-id="<?php echo (int) $item['id']; ?>">
                                    <td><?php echo (int) $item['item_no']; ?></td>
                                    <td><strong><?php echo htmlspecialchars(strtoupper($item['family_code'] ?? '-')); ?></strong>
                                    </td>
                                    <td class="item-model-cell"
                                        data-family-code="<?php echo htmlspecialchars($item['family_code']); ?>"
                                        data-item-no="<?php echo (int) $item['item_no']; ?>"
                                        data-item-model="<?php echo htmlspecialchars($item['item_model'] ?? ''); ?>"
                                        data-item-description="<?php echo htmlspecialchars($item['item_description'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($item['item_model'] ?? '-'); ?>
                                    </td>
                                    <td class="item-model-action-cell">
                                        <?php if (!empty($item['item_model']) && $item['item_model'] !== '-'): ?>
                                            <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                                <button class="btn-edit-item-model"
                                                    onclick="editItemModel('<?php echo htmlspecialchars($item['family_code']); ?>', <?php echo (int) $item['item_no']; ?>, '<?php echo htmlspecialchars($item['item_model'] ?? ''); ?>', '<?php echo htmlspecialchars($item['item_description'] ?? ''); ?>')"
                                                    title="Edit Item Model">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                        <path
                                                            d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" />
                                                    </svg>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 12px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="item-description-cell"
                                        data-family-code="<?php echo htmlspecialchars($item['family_code']); ?>"
                                        data-item-no="<?php echo (int) $item['item_no']; ?>"
                                        data-item-description="<?php echo htmlspecialchars($item['item_description'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($item['item_description'] ?? '-'); ?>
                                    </td>
                                    <td class="serial-cell"
                                        data-family-code="<?php echo htmlspecialchars($item['family_code']); ?>"
                                        data-item-no="<?php echo (int) $item['item_no']; ?>"
                                        data-item-model="<?php echo htmlspecialchars($item['item_model'] ?? ''); ?>"
                                        data-item-description="<?php echo htmlspecialchars($item['item_description'] ?? ''); ?>"
                                        data-quantity="<?php echo (int) $item['quantity']; ?>"
                                        data-has-serial="<?php echo $item['has_serial']; ?>"
                                        data-has-serial-2="<?php echo $item['has_serial_2'] ?? 0; ?>"
                                        data-has-serial-number="<?php echo $item['has_serial_number'] ?? 0; ?>"
                                        data-department="<?php echo htmlspecialchars($item['department'] ?? ''); ?>"
                                        data-imei-2="<?php echo htmlspecialchars($item['imei_2'] ?? ''); ?>"
                                        data-received-qty="<?php echo (int) $item['calculated_received_qty']; ?>">
                                        <?php
                                        // Display serial numbers if they exist, regardless of has_serial flag
                                        if (!empty($item['serial_number'])) {
                                            $raw_s1 = $item['serial_number'] ?? '';
                                            $raw_s2 = $item['imei_2'] ?? '';

                                            $serials1 = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw_s1)), 'strlen'));
                                            $serials2 = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw_s2)), 'strlen'));
                                            $total_pairs = max(count($serials1), count($serials2));
                                            $sec_label = (strcasecmp($item['department'] ?? '', 'TABLET') === 0 && !empty($item['has_serial_number'])) ? 'S/N' : 'IMEI 2';

                                            if ($total_pairs > 0) {
                                                for ($i = 0; $i < $total_pairs; $i++) {
                                                    $s1 = $serials1[$i] ?? '';
                                                    $s2 = $serials2[$i] ?? '';
                                                    $line = '';
                                                    if ($total_pairs > 1) {
                                                        $line .= '<span style="font-weight: 600; color: #555; margin-right: 4px;">' . ($i + 1) . '.</span>';
                                                    }
                                                    $line .= htmlspecialchars($s1);
                                                    if ($s2 !== '') {
                                                        $line .= ' <span style="color: #2563eb; font-size: 12px; font-weight: 600;">(' . $sec_label . ': ' . htmlspecialchars($s2) . ')</span>';
                                                    }
                                                    echo ($i > 0 ? '<br>' : '') . $line;
                                                }
                                            }
                                        } elseif ($item['has_serial'] == 1 || !empty($item['has_serial_number'])) {
                                            // Item should have serial but doesn't yet - show dash
                                            echo '-';
                                        } else {
                                            // Item doesn't require serial number
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td class="action-cell serial-action-cell"
                                        data-family-code="<?php echo htmlspecialchars($item['family_code']); ?>"
                                        data-item-no="<?php echo (int) $item['item_no']; ?>"
                                        data-item-description="<?php echo htmlspecialchars($item['item_description'] ?? ''); ?>"
                                        data-quantity="<?php echo (int) $item['quantity']; ?>"
                                        data-has-serial="<?php echo $item['has_serial']; ?>">
                                        <!-- Edit Serial Button will be added by JavaScript if serial numbers exist -->
                                        <span style="color: #999; font-size: 12px;">-</span>
                                    </td>
                                    <td class="action-cell item-type-action-cell"
                                        data-family-code="<?php echo htmlspecialchars($item['family_code']); ?>"
                                        data-item-no="<?php echo (int) $item['item_no']; ?>"
                                        data-item-description="<?php echo htmlspecialchars($item['item_description'] ?? ''); ?>"
                                        data-quantity="<?php echo (int) $item['quantity']; ?>"
                                        data-has-serial="<?php echo $item['has_serial']; ?>">
                                        <!-- Edit Item Type/Status Button will be added by JavaScript if serial numbers exist -->
                                        <span style="color: #999; font-size: 12px;">-</span>
                                    </td>
                                    <td class="quantity-cell"
                                        data-family-code="<?php echo htmlspecialchars($item['family_code']); ?>"
                                        data-item-no="<?php echo (int) $item['item_no']; ?>"
                                        data-quantity="<?php echo (int) $item['quantity']; ?>"
                                        data-received-qty="<?php echo (int) $item['calculated_received_qty']; ?>">
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 4px;">
                                            <span
                                                class="received-count-span"><?php echo (int) $item['calculated_received_qty']; ?></span>
                                            /
                                            <input type="number" class="item-qty-input" min="0"
                                                style="width: 65px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; text-align: center; font-size: 13px; font-weight: 600;"
                                                data-item-id="<?php echo (int) $item['id']; ?>"
                                                data-family-code="<?php echo htmlspecialchars($item['family_code']); ?>"
                                                data-item-no="<?php echo (int) $item['item_no']; ?>"
                                                value="<?php echo (int) $item['quantity']; ?>" oninput="updateRowTotal(this)" />
                                        </div>
                                    </td>
                                    <td class="cost-cell">
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 4px;">
                                            <span>&#8369;</span>
                                            <input type="number" step="0.01" min="0" class="item-cost-input"
                                                style="width: 90px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; text-align: right; font-size: 13px; font-weight: 600;"
                                                data-item-id="<?php echo (int) $item['id']; ?>"
                                                data-family-code="<?php echo htmlspecialchars($item['family_code']); ?>"
                                                data-item-no="<?php echo (int) $item['item_no']; ?>"
                                                value="<?php echo number_format((float) $item['cost'], 2, '.', ''); ?>"
                                                oninput="updateRowTotal(this)" />
                                        </div>
                                    </td>
                                    <td class="total-cell">
                                        <strong>&#8369; <span
                                                class="row-total-span"><?php echo number_format((float) $item['total'], 2); ?></span></strong>
                                    </td>
                                    <td class="delete-action-cell">
                                        <div style="display: flex; justify-content: center; align-items: center;">
                                            <button class="btn-delete-item"
                                                onclick="deleteItemRow(<?php echo (int) $item['id']; ?>, '<?php echo htmlspecialchars($item['family_code']); ?>', <?php echo (int) $item['item_no']; ?>)"
                                                title="Delete Item">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                    <path
                                                        d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <!-- Total Quantity and Grand Total Row -->
                            <tr class="grand-total-row">
                                <td colspan="7" style="border-left:1px solid #ccc;"></td>
                                <td class="gt-label">TOTAL QUANTITY</td>
                                <td class="gt-amount">
                                    <strong><span id="grandTotalReceived"><?php echo $total_received_qty; ?></span>/<span
                                            id="grandTotalOrdered"><?php echo $total_ordered_qty; ?></span></strong>
                                </td>
                                <td class="gt-label">GRAND TOTAL</td>
                                <td class="gt-amount"><strong>&#8369; <span
                                            id="grandTotalAmount"><?php echo number_format($grand_total, 2); ?></span></strong>
                                </td>
                                <td style="border-right:1px solid #ccc;"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-items">No items found for this Purchase Order.</div>
            <?php endif; ?>
        </div>

    </div><!-- end .main-content -->

    <!-- IMEI Modal -->
    <div class="serial-modal" id="serialModal">
        <div class="serial-modal-content" id="serialModalContent">
            <div class="serial-modal-header">
                Add IMEI
            </div>
            <div class="serial-modal-body">
                <div class="serial-inputs-container" id="serialInputsContainer"
                    style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <div class="serial-input-section" style="flex: 1;">
                        <label id="serial1Label">IMEI <span
                                style="float: right; font-weight: 700; color: #1a7a35;"><span
                                    id="serialCountCurrent">0</span>/<span id="serialCountTotal">0</span></span></label>
                        <input type="text" id="serialNumberInput" placeholder="Enter IMEI">
                    </div>
                    <div class="serial-input-section" id="serial2InputSection" style="flex: 1; display: none;">
                        <label id="serial2Label">IMEI 2 <span
                                style="float: right; font-weight: 700; color: #1a7a35;"><span
                                    id="serial2CountCurrent">0</span>/<span
                                    id="serial2CountTotal">0</span></span></label>
                        <input type="text" id="serial2NumberInput" placeholder="Enter IMEI 2">
                    </div>
                </div>

                <div class="serial-tables-container" id="serialTablesContainer"
                    style="width: 100%; max-height: 280px; overflow-y: auto;">
                    <table class="serial-list-table" id="serialListTable">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">No.</th>
                                <th id="serial1TableHeader">IMEI</th>
                                <th id="serial2TableHeader" style="display: none;">IMEI 2</th>
                                <th style="width: 100px; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="serialListBody">
                            <!-- IMEI entries will be added here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="serial-modal-footer">
                <button class="btn-modal-back" onclick="closeSerialModal()">Back</button>
                <button class="btn-modal-save" onclick="saveSerialNumbers()">Save</button>
            </div>
        </div>
    </div>

    <!-- Item Model Modal -->
    <div class="serial-modal" id="itemModelModal">
        <div class="serial-modal-content">
            <div class="serial-modal-header">
                Search & Select Item Model
            </div>
            <div class="serial-modal-body">
                <!-- Selected Items List Section -->
                <div class="serial-input-section" id="selectedItemsListSection" style="display: none;">
                    <label>Selected Items (<span id="selectedItemsCount">0</span>) - Total Qty: <span
                            id="selectedItemsTotalQty" style="font-weight: 700; color: #1a7a35;">0</span></label>
                    <div style="max-height: 200px; overflow-y: auto; margin-bottom: 15px;">
                        <table class="serial-list-table" id="selectedItemsTable">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Item Model</th>
                                    <th>Item Description</th>
                                    <th>Quantity</th>
                                    <th id="receivedColumnHeader">Received</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="selectedItemsList">
                                <!-- Selected items will be displayed here -->
                            </tbody>
                        </table>
                    </div>
                    <div
                        style="background: #f0f7ff; border: 1px solid #b3d9ff; padding: 10px; border-radius: 4px; font-size: 12px; color: #0066cc;">
                        <strong>💡 Tip:</strong> Adjust quantities as needed. Total selected quantity: <strong><span
                                id="totalQtyIndicator">0</span></strong>
                    </div>
                </div>

                <div class="serial-input-section">
                    <label>Search Item</label>
                    <input type="text" id="itemModelSearchInput" placeholder="Search by Item Code or Description">
                </div>
                <div class="serial-input-section" id="searchResultsSection" style="display: none;">
                    <label>Search Results</label>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="serial-list-table" id="itemSearchResultsTable">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Item Model</th>
                                    <th>Item Description</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="itemSearchResults">
                                <!-- Search results will be displayed here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="serial-modal-footer">
                <button class="btn-modal-back" onclick="closeItemModelModal()">Close</button>
                <button class="btn-modal-save" onclick="saveAllSelectedItems()" id="btnSaveItemModel">Save All Items
                    (<span id="saveButtonCount">0</span>)</button>
            </div>
        </div>
    </div>

    <!-- Item Type/Status Modal -->
    <div class="serial-modal" id="itemTypeStatusModal">
        <div class="serial-modal-content">
            <div class="serial-modal-header">
                Edit Item Type/Status
            </div>
            <div class="serial-modal-body">
                <table class="serial-list-table" id="itemTypeStatusTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">No.</th>
                            <th>Item Model</th>
                            <th>IMEI</th>
                            <th style="width: 180px;">Type</th>
                            <th style="width: 80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemTypeStatusTableBody">
                        <!-- Rows will be added here by JavaScript -->
                    </tbody>
                </table>
            </div>
            <div class="serial-modal-footer">
                <button class="btn-modal-back" onclick="closeItemTypeStatusModal()">Back</button>
                <button class="btn-modal-save" onclick="saveItemTypeStatus()">Save</button>
            </div>
        </div>
    </div>

    <!-- ═══════════════ PRINT AREA (hidden on screen, shown on print) ═══════════════ -->
    <div class="print-area" id="printArea">

        <!-- Header with Logo and Title -->
        <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:20px;">
            <div>
                <img src="Icon/ZUHAUSE-LOGO.png" alt="ZUHAUSE LOGO"
                    style="height:50px; width:auto;          border-radius: 5px;">
            </div>
            <div style="text-align:center; flex:1;">
                <div style="font-size:18px; font-weight:bold; margin-bottom:5px; margin-right: 150px;">Purchase Order
                </div>
            </div>
        </div>

        <!-- Company Information Section -->
        <div style="margin-bottom:20px;">
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="width:50%; vertical-align:top;">
                        <div style="margin-bottom:8px;">
                            <span style="font-weight:bold;">BRANCH:</span>
                            <span
                                style="margin-left:20px;"><?php echo htmlspecialchars($display_branch_name ?: 'No Allocations Yet'); ?></span>
                        </div>
                        <div style="margin-bottom:8px;">
                            <span style="font-weight:bold;">PO NUMBER:</span>
                            <span style="margin-left:20px;"><?php echo htmlspecialchars($po['po_number']); ?></span>
                        </div>
                        <div style="margin-bottom:8px; display: none;">
                            <span style="font-weight:bold;">SUPPLIER COMPANY NAME:</span>
                            <span
                                style="margin-left:20px;"><?php echo htmlspecialchars($po['supplier_company'] ?: ''); ?></span>
                        </div>
                    </td>
                    <td style="width:50%; vertical-align:top; text-align:right;">
                        <div style="margin-bottom:8px;">
                            <span style="font-weight:bold;">RECEIVE DATE:</span>
                            <span style="margin-left:20px;"><?php echo $received_fmt ?: ''; ?></span>
                        </div>
                        <div style="margin-bottom:8px;">
                            <span style="font-weight:bold;">PO DATE:</span>
                            <span style="margin-left:20px;"><?php echo $po_date_fmt; ?></span>
                        </div>
                        <div style="margin-bottom:8px; display: none;">
                            <span style="font-weight:bold;">SUPPLIER NAME:</span>
                            <span
                                style="margin-left:20px;"><?php echo htmlspecialchars(isset($po['supplier_name']) && $po['supplier_name'] !== '' ? $po['supplier_name'] : ''); ?></span>
                        </div>
                        <div style="margin-bottom:8px;">
                            <span style="font-weight:bold;">PAYMENT DUE DATE:</span>
                            <span style="margin-left:20px;"><?php echo $due_date_fmt; ?></span>
                        </div>
                        <div style="margin-bottom:8px;">
                            <span style="font-weight:bold;">REMARKS:</span>
                            <span style="margin-left:20px;"><?php echo htmlspecialchars($po['remarks'] ?: ''); ?></span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Items Table -->
        <table style="width:100%; border-collapse:collapse; border:1px solid #000; margin-bottom:30px;">
            <thead>
                <tr>
                    <th
                        style="padding:12px; border:1px solid #000; text-align:center; font-weight:600; background:#E1FFDE; font-size:13px;">
                        No.</th>
                    <th
                        style="padding:12px; border:1px solid #000; text-align:center; font-weight:600; background:#E1FFDE; font-size:13px;">
                        Family Code</th>
                    <th
                        style="padding:12px; border:1px solid #000; text-align:center; font-weight:600; background:#E1FFDE; font-size:13px;">
                        Item Model</th>
                    <th
                        style="padding:12px; border:1px solid #000; text-align:center; font-weight:600; background:#E1FFDE; font-size:13px;">
                        Item Description</th>
                    <th
                        style="padding:12px; border:1px solid #000; text-align:center; font-weight:600; background:#E1FFDE; font-size:13px;">
                        IMEI</th>
                    <th
                        style="padding:12px; border:1px solid #000; text-align:center; font-weight:600; background:#E1FFDE; font-size:13px;">
                        Quantity</th>
                    <th
                        style="padding:12px; border:1px solid #000; text-align:center; font-weight:600; background:#E1FFDE; font-size:13px;">
                        Cost</th>
                    <th
                        style="padding:12px; border:1px solid #000; text-align:center; font-weight:600; background:#E1FFDE; font-size:13px;">
                        Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td style="padding:12px; border:1px solid #000; text-align:center; font-size:13px;">
                                <?php echo (int) $item['item_no']; ?>
                            </td>
                            <td style="padding:12px; border:1px solid #000; text-align:center; font-size:13px;">
                                <?php echo htmlspecialchars($item['family_code'] ?? ''); ?>
                            </td>
                            <td style="padding:12px; border:1px solid #000; text-align:center; font-size:13px;">
                                <?php echo htmlspecialchars($item['item_model'] ?? '-'); ?>
                            </td>
                            <td style="padding:12px; border:1px solid #000; text-align:center; font-size:13px;">
                                <?php echo htmlspecialchars($item['item_description'] ?? '-'); ?>
                            </td>
                            <td
                                style="padding:12px; border:1px solid #000; text-align:center; font-size:13px; white-space: pre-line;">
                                <?php
                                // Display serial numbers if they exist, regardless of has_serial flag
                                if (!empty($item['serial_number'])) {
                                    $raw_s1 = $item['serial_number'] ?? '';
                                    $raw_s2 = $item['imei_2'] ?? '';

                                    $serials1 = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw_s1)), 'strlen'));
                                    $serials2 = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw_s2)), 'strlen'));
                                    $total_pairs = max(count($serials1), count($serials2));
                                    $sec_label = (strcasecmp($item['department'] ?? '', 'TABLET') === 0 && !empty($item['has_serial_number'])) ? 'S/N' : 'IMEI 2';

                                    if ($total_pairs > 0) {
                                        for ($i = 0; $i < $total_pairs; $i++) {
                                            $s1 = $serials1[$i] ?? '';
                                            $s2 = $serials2[$i] ?? '';
                                            $line = '';
                                            if ($total_pairs > 1) {
                                                $line .= '<span style="font-weight: 600; color: #555; margin-right: 4px;">' . ($i + 1) . '.</span>';
                                            }
                                            $line .= htmlspecialchars($s1);
                                            if ($s2 !== '') {
                                                $line .= ' <span style="color: #2563eb; font-size: 12px; font-weight: 600;">(' . $sec_label . ': ' . htmlspecialchars($s2) . ')</span>';
                                            }
                                            echo ($i > 0 ? '<br>' : '') . $line;
                                        }
                                    }
                                } elseif ($item['has_serial'] == 1 || !empty($item['has_serial_number'])) {
                                    // Item should have serial but doesn't yet
                                    echo '-';
                                } else {
                                    // Item doesn't require serial number
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td style="padding:12px; border:1px solid #000; text-align:center; font-size:13px;">
                                <?php
                                // Calculate received serial numbers count
                                $total_quantity = (int) $item['quantity'];
                                $received_count = 0;

                                // Count serial numbers if they exist
                                if (!empty($item['serial_number'])) {
                                    $serials = trim($item['serial_number']);
                                    // Handle both newline and comma-separated formats
                                    if (strpos($serials, "\n") !== false) {
                                        $serial_array = explode("\n", $serials);
                                    } else {
                                        $serial_array = explode(",", $serials);
                                    }
                                    // Filter out empty entries
                                    $serial_array = array_filter(array_map('trim', $serial_array));
                                    $received_count = count($serial_array);
                                }

                                // Display in format: received/total
                                echo $received_count . '/' . $total_quantity;
                                ?>
                            </td>
                            <td style="padding:12px; border:1px solid #000; text-align:center; font-size:13px;">
                                ₱<?php echo number_format((float) $item['cost'], 2); ?></td>
                            <td style="padding:12px; border:1px solid #000; text-align:center; font-size:13px;">
                                ₱<?php echo number_format((float) $item['total'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8"
                            style="padding:20px; border:1px solid #000; text-align:center; color:#666; font-size:13px;">No
                            Purchase Orders</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Signature Section -->
        <div style="margin-top:40px;">
            <div style="font-weight:bold;">SIGNATURE</div>
            <div style="border-bottom:1px solid #000; width:200px; margin-top:20px;"></div>
        </div>

    </div><!-- end .print-area -->

    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-box">
            <div class="modal-icon" id="modalIcon"></div>
            <div class="modal-title" id="modalTitle"></div>
            <div class="modal-msg" id="modalMsg"></div>
            <div class="modal-actions">
                <button class="modal-btn modal-btn-cancel" onclick="closeModal()">Cancel</button>
                <button class="modal-btn" id="modalConfirmBtn" onclick="confirmAction()">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn = document.querySelector('.menu-btn');
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
            menuBtn.classList.toggle('active');
        }
        function toggleSection(element) {
            const section = element.parentElement;
            const isCurrentlyCollapsed = section.classList.contains('collapsed');

            // Close all other sections (accordion behavior)
            const allSections = document.querySelectorAll('.menu-section');
            allSections.forEach(function (s) {
                if (s !== section) {
                    s.classList.add('collapsed');
                }
            });

            // Toggle the clicked section
            if (isCurrentlyCollapsed) {
                section.classList.remove('collapsed');
            } else {
                section.classList.add('collapsed');
            }

            // Save the sidebar state to persist across navigation
            if (typeof saveSidebarState === 'function') {
                saveSidebarState();
            }
        }

        let _pendingStatus = null;
        let _isReceivingMode = false;

        function parseRowKey(rowKey) {
            const pos = rowKey.lastIndexOf('-');
            if (pos === -1) {
                return { familyCode: rowKey, itemNo: 0 };
            }
            return {
                familyCode: rowKey.substring(0, pos),
                itemNo: parseInt(rowKey.substring(pos + 1), 10)
            };
        }

        let _currentItemModel = '';
        let _currentItemDescription = '';
        let _currentItemQuantity = 0;
        let _currentSerialNumbers = [];
        let _currentSerialNumbers2 = [];
        let _serialRowCount = 0;
        let _allSerialNumbers = {}; // Store serial numbers for display (includes existing + new)
        let _existingSerialNumbers = {}; // Store existing serial numbers from database (for validation)
        let _allSerialNumbers2 = {}; // Store IMEI 2 / S/N for display
        let _existingSerialNumbers2 = {}; // Store existing IMEI 2 from database
        let _allItemHasSerial2 = {}; // Store has_serial_2 per rowKey
        let _allItemHasSerialNumber = {}; // Store has_serial_number (TABLET) per rowKey
        let _allItemDepartment = {}; // Store department per rowKey
        let _allItemModels = {}; // Store item models for each unique row (family_code + item_no)
        let _allItemDescriptions = {}; // Store item descriptions for each unique row
        let _allReceivedQty = {}; // Store received quantities for non-serialized items (family_code + item_no as key)
        let _shouldAutoSave = false; // Flag to control auto-save behavior
        let _currentEditingRowKey = ''; // Track which specific row is being edited (family_code-item_no)
        let _isEditingExistingItem = false; // Track if we're editing an existing item model or adding new
        let _isModalEditMode = false; // Track if the IMEI modal was opened in Edit mode or Add mode
        let _serializedItems = <?php echo json_encode(array_values(array_filter($items, function ($item) {
            return isset($item['has_serial']) && $item['has_serial'] == 1;
        }))); ?>;
        if (!Array.isArray(_serializedItems)) {
            _serializedItems = Object.values(_serializedItems || {});
        }

        // Load existing serial numbers on page load
        document.addEventListener('DOMContentLoaded', function () {
            // Restore saved state from sessionStorage (after Add Item reload)
            const savedInvoice = sessionStorage.getItem('po_invoice_number');
            const savedRemarks = sessionStorage.getItem('po_receiving_remarks');
            const savedSerials = sessionStorage.getItem('po_serial_numbers');
            const savedReceivingMode = sessionStorage.getItem('po_receiving_mode');

            if (savedInvoice) {
                const invoiceInput = document.getElementById('invoiceNumberInput');
                if (invoiceInput) {
                    invoiceInput.value = savedInvoice;
                }
                sessionStorage.removeItem('po_invoice_number');
            }

            if (savedRemarks) {
                const remarksTextarea = document.querySelector('textarea[name="receiving_remarks"]');
                if (remarksTextarea) {
                    remarksTextarea.value = savedRemarks;
                }
                sessionStorage.removeItem('po_receiving_remarks');
            }

            if (savedSerials) {
                try {
                    const serialData = JSON.parse(savedSerials);
                    Object.keys(serialData).forEach(rowKey => {
                        _allSerialNumbers[rowKey] = serialData[rowKey];
                        updateSerialActionButton(rowKey);
                        updateItemTypeStatusActionButton(rowKey);
                    });
                    sessionStorage.removeItem('po_serial_numbers');
                } catch (e) {
                    console.error('Failed to restore serial numbers:', e);
                }
            }

            if (savedReceivingMode === 'true') {
                showSerialButtons();
                changeReceiveButtonToSave();
                sessionStorage.removeItem('po_receiving_mode');
            }

            <?php foreach ($items as $item): ?>
                <?php
                $row_key = $item['family_code'] . '-' . $item['item_no'];
                ?>
                _allItemHasSerial2['<?php echo addslashes($row_key); ?>'] = <?php echo (int) ($item['has_serial_2'] ?? 0); ?>;
                _allItemHasSerialNumber['<?php echo addslashes($row_key); ?>'] = <?php echo (int) ($item['has_serial_number'] ?? 0); ?>;
                _allItemDepartment['<?php echo addslashes($row_key); ?>'] = '<?php echo addslashes($item['department'] ?? ''); ?>';
                <?php if (($item['has_serial'] == 1 || !empty($item['has_serial_number'])) && !empty($item['serial_number'])): ?>
                    <?php
                    $serial_string = $item['serial_number'];
                    // Handle both newline and comma-separated formats
                    if (strpos($serial_string, "\n") === false && strpos($serial_string, ',') !== false) {
                        $serial_string = str_replace(',', "\n", $serial_string);
                    }
                    $existing_serials = explode("\n", $serial_string);
                    $existing_serials = array_values(array_filter(array_map('trim', $existing_serials)));
                    ?>
                    _allSerialNumbers['<?php echo addslashes($row_key); ?>'] = <?php echo json_encode($existing_serials); ?>;
                    _existingSerialNumbers['<?php echo addslashes($row_key); ?>'] = <?php echo json_encode($existing_serials); ?>;

                    // Initialize Edit Serial button for this item
                    updateSerialActionButton('<?php echo addslashes($row_key); ?>');

                    // Initialize Edit Item Type/Status button for this item
                    updateItemTypeStatusActionButton('<?php echo addslashes($row_key); ?>');
                <?php endif; ?>
                <?php if (!empty($item['imei_2'])): ?>
                    <?php
                    $imei2_string = $item['imei_2'];
                    if (strpos($imei2_string, "\n") === false && strpos($imei2_string, ',') !== false) {
                        $imei2_string = str_replace(',', "\n", $imei2_string);
                    }
                    $existing_imei2 = explode("\n", $imei2_string);
                    $existing_imei2 = array_values(array_filter(array_map('trim', $existing_imei2)));
                    ?>
                    _allSerialNumbers2['<?php echo addslashes($row_key); ?>'] = <?php echo json_encode($existing_imei2); ?>;
                    _existingSerialNumbers2['<?php echo addslashes($row_key); ?>'] = <?php echo json_encode($existing_imei2); ?>;
                <?php endif; ?>
            <?php endforeach; ?>

            // Initialize Add buttons for empty/dash items
            initializeAddButtons();

            // Initialize searchable selects for supplier fields
            initializeSearchableSelects();
        });

        // ===== Searchable Select Functions for Suppliers =====
        function initializeSearchableSelects() {
            document.querySelectorAll('.searchable-select').forEach(function (select) {
                const wrapper = document.createElement('div');
                wrapper.className = 'select-wrapper';

                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'select-search-input';
                input.placeholder = select.options[0] ? select.options[0].text : 'Select...';

                const dropdown = document.createElement('div');
                dropdown.className = 'select-dropdown';

                // Insert wrapper before select
                select.parentNode.insertBefore(wrapper, select);
                wrapper.appendChild(input);
                wrapper.appendChild(dropdown);
                wrapper.appendChild(select);

                // Hide original select
                select.style.display = 'none';

                // Handle input focus
                input.addEventListener('focus', function () {
                    dropdown.classList.add('active');
                    if (select.id === 'supplierCompanyInput') {
                        // Load companies if dropdown is empty
                        if (dropdown.children.length === 0) {
                            loadCompaniesIntoDropdown(dropdown, select, input);
                        }
                    } else if (select.id === 'supplierNameInput') {
                        // Load suppliers for selected company
                        const companySelect = document.getElementById('supplierCompanyInput');
                        const selectedCompany = companySelect ? companySelect.value : '';
                        if (selectedCompany && dropdown.children.length === 0) {
                            loadSuppliersIntoDropdown(selectedCompany, dropdown, select, input);
                        }
                    }
                });

                // Handle input typing for search
                input.addEventListener('input', function () {
                    const searchTerm = input.value.toLowerCase();

                    if (select.id === 'supplierCompanyInput') {
                        // Search company names
                        if (searchTerm.length >= 2) {
                            searchCompanies(searchTerm, dropdown, select, input);
                        } else if (searchTerm.length === 0) {
                            loadCompaniesIntoDropdown(dropdown, select, input);
                        }
                    } else if (select.id === 'supplierNameInput') {
                        // Search supplier names within selected company
                        const companySelect = document.getElementById('supplierCompanyInput');
                        const selectedCompany = companySelect ? companySelect.value : '';
                        if (selectedCompany) {
                            if (searchTerm.length >= 2) {
                                searchSuppliersInCompany(selectedCompany, searchTerm, dropdown, select, input);
                            } else if (searchTerm.length === 0) {
                                loadSuppliersIntoDropdown(selectedCompany, dropdown, select, input);
                            }
                        }
                    }
                });

                // Handle clicks outside
                document.addEventListener('click', function (e) {
                    if (!wrapper.contains(e.target)) {
                        dropdown.classList.remove('active');
                    }
                });
            });

            // Set current values after initialization
            setTimeout(function () {
                const currentCompany = '<?php echo htmlspecialchars($po['supplier_company'] ?? ''); ?>';
                if (currentCompany) {
                    setSelectValue('supplierCompanyInput', currentCompany);

                    // Load suppliers for the current company
                    setTimeout(function () {
                        const currentSupplier = '<?php echo htmlspecialchars($po['supplier_name'] ?? ''); ?>';
                        if (currentSupplier) {
                            setSelectValue('supplierNameInput', currentSupplier);
                        }
                    }, 300);
                }
            }, 100);
        }

        function loadCompaniesIntoDropdown(dropdown, select, input) {
            dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Loading...</div>';

            fetch('search_supplier.php?type=company&term=')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    dropdown.innerHTML = '';
                    if (data.status === 'success' && data.data.length > 0) {
                        data.data.forEach(company => {
                            const option = document.createElement('div');
                            option.className = 'select-option';
                            option.textContent = company.store_name;
                            option.addEventListener('click', function () {
                                setSelectValue('supplierCompanyInput', company.store_name);
                                dropdown.classList.remove('active');

                                // Clear supplier name when company changes
                                const supplierNameSelect = document.getElementById('supplierNameInput');
                                const supplierNameWrapper = supplierNameSelect.parentNode;
                                const supplierNameInput = supplierNameWrapper.querySelector('.select-search-input');
                                const supplierNameDropdown = supplierNameWrapper.querySelector('.select-dropdown');

                                supplierNameSelect.value = '';
                                supplierNameInput.value = '';
                                supplierNameDropdown.innerHTML = '';
                            });
                            dropdown.appendChild(option);
                        });
                    } else {
                        dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">No companies found</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading companies:', error);
                    dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Error loading companies</div>';
                });
        }

        function loadSuppliersIntoDropdown(companyName, dropdown, select, input) {
            dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Loading...</div>';

            fetch(`search_supplier.php?type=supplier&company=${encodeURIComponent(companyName)}&term=`)
                .then(response => response.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    if (data.status === 'success' && data.data.length > 0) {
                        data.data.forEach(supplier => {
                            const option = document.createElement('div');
                            option.className = 'select-option';
                            option.textContent = supplier.supplier_name;
                            option.addEventListener('click', function () {
                                setSelectValue('supplierNameInput', supplier.supplier_name);
                                dropdown.classList.remove('active');
                            });
                            dropdown.appendChild(option);
                        });
                    } else {
                        dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">No suppliers found for this company</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading suppliers:', error);
                    dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Error loading suppliers</div>';
                });
        }

        function searchCompanies(searchTerm, dropdown, select, input) {
            dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Searching...</div>';

            fetch(`search_supplier.php?type=company&term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    if (data.status === 'success' && data.data.length > 0) {
                        data.data.forEach(company => {
                            const option = document.createElement('div');
                            option.className = 'select-option';
                            option.textContent = company.store_name;
                            option.addEventListener('click', function () {
                                setSelectValue('supplierCompanyInput', company.store_name);
                                dropdown.classList.remove('active');

                                // Clear supplier name when company changes
                                const supplierNameSelect = document.getElementById('supplierNameInput');
                                const supplierNameWrapper = supplierNameSelect.parentNode;
                                const supplierNameInput = supplierNameWrapper.querySelector('.select-search-input');
                                const supplierNameDropdown = supplierNameWrapper.querySelector('.select-dropdown');

                                supplierNameSelect.value = '';
                                supplierNameInput.value = '';
                                supplierNameDropdown.innerHTML = '';
                            });
                            dropdown.appendChild(option);
                        });
                    } else {
                        dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">No companies found</div>';
                    }
                })
                .catch(error => {
                    console.error('Error searching companies:', error);
                    dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Error searching companies</div>';
                });
        }

        function searchSuppliersInCompany(companyName, searchTerm, dropdown, select, input) {
            dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Searching...</div>';

            fetch(`search_supplier.php?type=supplier&company=${encodeURIComponent(companyName)}&term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    if (data.status === 'success' && data.data.length > 0) {
                        data.data.forEach(supplier => {
                            const option = document.createElement('div');
                            option.className = 'select-option';
                            option.textContent = supplier.supplier_name;
                            option.addEventListener('click', function () {
                                setSelectValue('supplierNameInput', supplier.supplier_name);
                                dropdown.classList.remove('active');
                            });
                            dropdown.appendChild(option);
                        });
                    } else {
                        dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">No suppliers found</div>';
                    }
                })
                .catch(error => {
                    console.error('Error searching suppliers:', error);
                    dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Error searching suppliers</div>';
                });
        }

        function setSelectValue(selectId, value) {
            const select = document.getElementById(selectId);
            const wrapper = select.parentNode;
            const input = wrapper.querySelector('.select-search-input');
            const dropdown = wrapper.querySelector('.select-dropdown');

            // Set input value
            input.value = value;

            // Update select value (create option if doesn't exist)
            let option = Array.from(select.options).find(opt => opt.value === value);
            if (!option) {
                option = new Option(value, value);
                select.appendChild(option);
            }
            option.selected = true;

            // Close dropdown
            dropdown.classList.remove('active');
        }
        // ===== End Searchable Select Functions =====

        function initializeAddButtons() {
            // Add IMEI buttons for items with dash or empty
            const serialCells = document.querySelectorAll('.serial-cell');
            serialCells.forEach(cell => {
                const hasSerial = cell.getAttribute('data-has-serial') === '1';
                const content = cell.textContent.trim();

                if (hasSerial && (content === '-' || content === '')) {
                    const familyCode = cell.getAttribute('data-family-code');
                    const itemNo = cell.getAttribute('data-item-no');
                    const rowKey = familyCode + '-' + itemNo;
                    const itemDescription = cell.getAttribute('data-item-description');
                    const quantity = cell.getAttribute('data-quantity');

                    // Show Add IMEI button
                    cell.innerHTML = `<button class="btn-add-serial-number" onclick="openSerialModal('${rowKey}', '${itemDescription}', ${quantity})">Add IMEI</button>`;
                }
            });

            // Add Item Model buttons for items with dash or empty
            const itemModelCells = document.querySelectorAll('.item-model-cell');
            itemModelCells.forEach(cell => {
                const content = cell.textContent.trim();

                if (content === '-' || content === '') {
                    const familyCode = cell.getAttribute('data-family-code');
                    const itemNo = cell.getAttribute('data-item-no');
                    const currentItemModel = cell.getAttribute('data-item-model');
                    const itemDescription = cell.getAttribute('data-item-description');

                    // Show Add Item Model button
                    cell.innerHTML = `<button class="btn-add-item-model" onclick="openItemModelModal('${familyCode}', '${itemNo}', '${currentItemModel}', '${itemDescription}')">Add Item Model</button>`;
                }
            });
        }

        function updateStatus(newStatus) {
            _pendingStatus = newStatus;
            const isReceive = newStatus === 'Received';

            if (isReceive) {
                // For receiving, always show item model buttons first (both serialized and non-serialized)
                // Show IMEI buttons and change Receive to Save
                showSerialButtons();
                changeReceiveButtonToSave();
                return;
            }

            // Normal flow for decline/cancel
            showConfirmModal(isReceive);
        }

        function saveModification() {
            // Show confirmation modal for modification
            const modal = document.getElementById('confirmModal');
            const modalIcon = document.getElementById('modalIcon');
            const modalTitle = document.getElementById('modalTitle');
            const modalMsg = document.getElementById('modalMsg');
            const confirmBtn = document.getElementById('modalConfirmBtn');

            modalIcon.textContent = '💾';
            modalTitle.textContent = 'Save Modifications?';

            // Build confirmation message based on what's being changed
            let message = 'Are you sure you want to save all modifications to this purchase order?';
            if (stagedDeletions.length > 0) {
                message += `\n\n⚠️ This will permanently delete ${stagedDeletions.length} item(s).`;
            }
            message += '\n\nThis action will update all item models, IMEI, and remarks that have been changed.';

            modalMsg.textContent = message;
            confirmBtn.textContent = 'Yes, Save';
            confirmBtn.className = 'modal-btn modal-btn-confirm-green';

            modal.classList.add('show');

            // Set flag to indicate this is a modification save
            _isModificationSave = true;
        }

        // Flag to track modification save
        let _isModificationSave = false;

        // Check if we need to show serial buttons on page load for Incomplete status
        <?php if (strcasecmp($status, 'Incomplete') === 0): ?>
            document.addEventListener('DOMContentLoaded', function () {
                showSerialButtons();
                changeReceiveButtonToSave();
            });
        <?php endif; ?>

        // Check if receiving_mode parameter is set in URL (after saving item models)
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('receiving_mode') === '1') {
                // Auto-activate receiving mode
                <?php if (strcasecmp($status, 'Pending') === 0): ?>
                    showSerialButtons();
                    changeReceiveButtonToSave();

                    // Clean up URL parameter
                    const cleanUrl = new URL(window.location.href);
                    cleanUrl.searchParams.delete('receiving_mode');
                    window.history.replaceState({}, '', cleanUrl.toString());
                <?php endif; ?>
            }
        });

        function showSerialButtons() {
            const currentStatus = '<?php echo $status; ?>';
            const isIncomplete = currentStatus.toLowerCase() === 'incomplete';
            const isCompleted = currentStatus.toLowerCase() === 'completed';

            // Find all serial cells for serialized items and show Add IMEI buttons
            const serialCells = document.querySelectorAll('.serial-cell');
            serialCells.forEach(cell => {
                const hasSerial = cell.getAttribute('data-has-serial') === '1';
                const familyCode = cell.getAttribute('data-family-code');
                const itemNo = cell.getAttribute('data-item-no');
                const rowKey = familyCode + '-' + itemNo;
                const itemModel = cell.getAttribute('data-item-model');
                const itemDescription = cell.getAttribute('data-item-description');
                const quantity = cell.getAttribute('data-quantity');

                if (hasSerial) {
                    const existingSerials = _allSerialNumbers[rowKey] || [];
                    const existingCount = existingSerials.length;
                    const totalQuantity = parseInt(quantity);

                    if (isIncomplete) {
                        // For INCOMPLETE status, hide buttons and show N/A if no IMEI
                        if (existingCount === 0) {
                            cell.innerHTML = 'N/A';
                        } else {
                            // Show existing IMEI without button
                            const serials2 = _allSerialNumbers2[rowKey] || [];
                            cell.innerHTML = formatSerialDisplayHtml(rowKey, existingSerials, serials2);
                        }
                    } else {
                        // Original behavior for other statuses
                        if (existingCount === 0) {
                            // No serials entered yet - show Add IMEI button
                            const config = getSerialConfig(rowKey);
                            const btnText = 'Add ' + config.modalTitleBase;
                            cell.innerHTML = `<button class="btn-add-serial-number" onclick="openSerialModal('${rowKey}', '${itemDescription}', ${quantity})">${escapeHtml(btnText)}</button>`;
                        } else if (existingCount < totalQuantity) {
                            // Some serials entered - show existing serials + Add More button
                            const serials2 = _allSerialNumbers2[rowKey] || [];
                            const serialList = formatSerialDisplayHtml(rowKey, existingSerials, serials2);
                            cell.innerHTML = `${serialList}<br><button class="btn-add-serial-number" onclick="openSerialModal('${rowKey}', '${itemDescription}', ${quantity})" style="margin-top: 5px; font-size: 11px; padding: 3px 8px;">Add More (${existingCount}/${totalQuantity})</button>`;
                        } else {
                            // All serials entered - show only the serial numbers
                            const serials2 = _allSerialNumbers2[rowKey] || [];
                            cell.innerHTML = formatSerialDisplayHtml(rowKey, existingSerials, serials2);
                        }
                    }
                }
            });

            // Find all item model cells and show Add Item Model buttons
            const itemModelCells = document.querySelectorAll('.item-model-cell');
            itemModelCells.forEach(cell => {
                const familyCode = cell.getAttribute('data-family-code');
                const itemNo = cell.getAttribute('data-item-no');
                const rowKey = familyCode + '-' + itemNo;
                const currentItemModel = cell.getAttribute('data-item-model');
                const itemDescription = cell.getAttribute('data-item-description');

                if (isIncomplete || isCompleted) {
                    // For INCOMPLETE or COMPLETED status, hide the Add Item Model button
                    // Only show the item model text if it exists
                    const displayText = _allItemModels[rowKey] || currentItemModel;
                    if (displayText && displayText !== '-') {
                        cell.innerHTML = `<span>${displayText}</span>`;
                    } else {
                        cell.innerHTML = '-';
                    }
                } else {
                    // Original behavior for other statuses
                    // Show button only if item model is not filled
                    const displayText = _allItemModels[rowKey] || currentItemModel;
                    if (displayText && displayText !== '-') {
                        // Item model exists - just show the text without button
                        cell.innerHTML = `<span>${displayText}</span>`;
                    } else {
                        // Item model is empty - show the Add Item Model button
                        cell.innerHTML = `<button class="btn-add-item-model" onclick="openItemModelModal('${familyCode}', '${itemNo}', '${currentItemModel}', '${itemDescription}')">Add Item Model</button>`;
                    }
                }
            });
        }

        function changeReceiveButtonToSave() {
            const receiveBtn = document.querySelector('.btn-receive');
            if (receiveBtn && !_isReceivingMode) {
                _isReceivingMode = true;
                receiveBtn.textContent = 'Save';
                receiveBtn.style.background = '#2e7d32';
                receiveBtn.onclick = function () {
                    // Check if current status is Incomplete and validate edit requirement
                    const currentStatus = '<?php echo $status; ?>';
                    if (currentStatus.toLowerCase() === 'incomplete') {
                        // Check if PO has been edited since becoming incomplete
                        checkEditRequirement();
                        return;
                    }

                    _pendingStatus = 'Received'; // Make sure this is set
                    // Allow saving with partial serial numbers
                    const hasAllSerials = checkAllSerialsEntered();
                    showConfirmModal(true, true, !hasAllSerials);
                };
            }
        }

        function checkEditRequirement() {
            // Check if serial numbers match quantities for serialized items
            const currentStatus = '<?php echo $status; ?>';

            // First check if PO has been edited - if recently edited, we need to get fresh data
            fetch('check_po_edit_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'po_id=<?php echo (int) $po_id; ?>'
            })
                .then(res => res.json())
                .then(editData => {
                    console.log('Edit status check:', editData);

                    if (!editData.success || !editData.has_been_edited) {
                        // PO hasn't been edited, show error requiring edit first
                        alert('This Purchase Order is incomplete and must be edited first before it can be completed. Please click the "Edit" button to review and adjust the quantities, or add/modify serial numbers, then try again.');
                        return;
                    }

                    // PO has been edited, now get fresh data and validate quantities vs serial numbers
                    validateWithFreshData();
                })
                .catch(err => {
                    console.log('Edit status check error:', err);
                    alert('Network error: ' + err.message);
                });
        }

        function validateWithFreshData() {
            // Get fresh data from database to ensure we have current quantities
            fetch('get_current_po_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'po_id=<?php echo (int) $po_id; ?>'
            })
                .then(res => res.json())
                .then(data => {
                    console.log('Fresh PO data response:', data);

                    if (!data.success) {
                        console.log('Error getting fresh PO data, using page data');
                        validateQuantitiesVsSerials();
                        return;
                    }

                    // Use fresh data for validation
                    validateWithData(data.items);
                })
                .catch(err => {
                    console.log('Network error getting fresh data, using page data:', err);
                    validateQuantitiesVsSerials();
                });
        }

        function validateWithData(items) {
            // Validate using fresh data from database
            let hasMismatch = false;
            let mismatchMessage = '';

            console.log('=== STARTING VALIDATION WITH FRESH DATA ===');
            console.log('Fresh items:', items);
            console.log('_allSerialNumbers:', _allSerialNumbers);
            console.log('_existingSerialNumbers:', _existingSerialNumbers);

            for (let item of items) {
                if (item.has_serial) {
                    const familyCode = item.family_code;
                    const requiredQuantity = parseInt(item.quantity);

                    // Count existing serials from fresh data (these are already in database)
                    const existingSerials = item.existing_serials || [];

                    // Count only NEW serials being added (not the existing ones)
                    const allSerials = _allSerialNumbers[familyCode] || [];
                    const existingFromPage = _existingSerialNumbers[familyCode] || [];
                    const newSerials = allSerials.filter(serial => !existingFromPage.includes(serial));

                    const totalSerials = existingSerials.length + newSerials.length;

                    console.log('=== VALIDATION DEBUG (FRESH DATA) ===');
                    console.log('Family Code:', familyCode);
                    console.log('Required Quantity (FRESH):', requiredQuantity);
                    console.log('Existing Serials from DB:', existingSerials);
                    console.log('Existing Serials Count:', existingSerials.length);
                    console.log('All Serials from Page:', allSerials);
                    console.log('Existing Serials from Page:', existingFromPage);
                    console.log('NEW Serials (filtered):', newSerials);
                    console.log('New Serials Count:', newSerials.length);
                    console.log('Total Serials:', totalSerials);
                    console.log('Condition: totalSerials !== requiredQuantity');
                    console.log('Condition Result:', totalSerials !== requiredQuantity);
                    console.log('Should show error?', totalSerials !== requiredQuantity);
                    console.log('========================');

                    // CORRECT LOGIC: If serials don't match quantity, show error
                    if (totalSerials !== requiredQuantity) {
                        hasMismatch = true;
                        if (totalSerials < requiredQuantity) {
                            mismatchMessage = `Item "${itemModel}" has quantity ${requiredQuantity} but only ${totalSerials} serial number(s). ` +
                                `Please either:\n\n` +
                                `1. Edit the Purchase Order to reduce quantity to ${totalSerials}, OR\n` +
                                `2. Add ${requiredQuantity - totalSerials} more serial number(s) to match the quantity.`;
                        } else {
                            mismatchMessage = `Item "${itemModel}" has quantity ${requiredQuantity} but ${totalSerials} serial number(s). ` +
                                `Please edit the Purchase Order to increase quantity to ${totalSerials} to match the serial numbers.`;
                        }
                        console.log('MISMATCH DETECTED - SHOULD SHOW ERROR:', mismatchMessage);
                        break;
                    } else {
                        console.log('VALIDATION PASSED - SHOULD PROCEED:', itemModel);
                    }
                }
            }

            if (hasMismatch) {
                console.log('FINAL RESULT: SHOWING ERROR ALERT');
                alert(mismatchMessage);
                return;
            }

            // Validation passed - quantities match serial numbers
            console.log('FINAL RESULT: ALL VALIDATION PASSED - proceeding with completion');
            proceedWithCompletion();
        }

        function validateQuantitiesVsSerials() {
            // Validate using the current page data (_serializedItems) which reflects the latest state
            let hasMismatch = false;
            let mismatchMessage = '';

            console.log('=== STARTING VALIDATION ===');
            console.log('_serializedItems:', _serializedItems);
            console.log('_allSerialNumbers:', _allSerialNumbers);

            const itemsToCheck = Array.isArray(_serializedItems) ? _serializedItems : Object.values(_serializedItems || {});
            for (let item of itemsToCheck) {
                if (item.has_serial) {
                    const itemModel = item.item_model;
                    const requiredQuantity = parseInt(item.quantity);

                    // Count existing serials from database (stored as newline-separated text)
                    let existingSerials = [];
                    if (item.serial_number) {
                        existingSerials = item.serial_number.split('\n').filter(s => s.trim() !== '');
                    }

                    // Count new serials being added
                    const newSerials = _allSerialNumbers[itemModel] || [];
                    const totalSerials = existingSerials.length + newSerials.length;

                    console.log('=== VALIDATION DEBUG ===');
                    console.log('Item Model:', itemModel);
                    console.log('Required Quantity:', requiredQuantity);
                    console.log('Existing Serials Array:', existingSerials);
                    console.log('Existing Serials Count:', existingSerials.length);
                    console.log('New Serials Array:', newSerials);
                    console.log('New Serials Count:', newSerials.length);
                    console.log('Total Serials:', totalSerials);
                    console.log('Condition: totalSerials !== requiredQuantity');
                    console.log('Condition Result:', totalSerials !== requiredQuantity);
                    console.log('Should show error?', totalSerials !== requiredQuantity);
                    console.log('========================');

                    // CORRECT LOGIC: If serials don't match quantity, show error
                    if (totalSerials !== requiredQuantity) {
                        hasMismatch = true;
                        if (totalSerials < requiredQuantity) {
                            mismatchMessage = `Item "${itemModel}" has quantity ${requiredQuantity} but only ${totalSerials} serial number(s). ` +
                                `Please either:\n\n` +
                                `1. Edit the Purchase Order to reduce quantity to ${totalSerials}, OR\n` +
                                `2. Add ${requiredQuantity - totalSerials} more serial number(s) to match the quantity.`;
                        } else {
                            mismatchMessage = `Item "${itemModel}" has quantity ${requiredQuantity} but ${totalSerials} serial number(s). ` +
                                `Please edit the Purchase Order to increase quantity to ${totalSerials} to match the serial numbers.`;
                        }
                        console.log('MISMATCH DETECTED - SHOULD SHOW ERROR:', mismatchMessage);
                        break;
                    } else {
                        console.log('VALIDATION PASSED - SHOULD PROCEED:', itemModel);
                    }
                }
            }

            if (hasMismatch) {
                console.log('FINAL RESULT: SHOWING ERROR ALERT');
                alert(mismatchMessage);
                return;
            }

            // Validation passed - quantities match serial numbers
            console.log('FINAL RESULT: ALL VALIDATION PASSED - proceeding with completion');
            proceedWithCompletion();
        }

        function checkEditStatusOnly() {
            // This function is kept for backward compatibility but not used in current flow
            // The validation now happens in checkEditRequirement -> validateQuantitiesVsSerials
            proceedWithCompletion();
        }

        function proceedWithCompletion() {
            const currentStatus = '<?php echo $status; ?>';

            // Set receiving mode to true so confirmAction logic works properly
            _isReceivingMode = true;

            if (currentStatus.toLowerCase() === 'incomplete') {
                // For incomplete status, we want to complete it
                _pendingStatus = 'Received'; // This will be converted to 'Completed' in confirmAction
            } else {
                _pendingStatus = 'Received';
            }

            const hasAllSerials = checkAllSerialsEntered();
            showConfirmModal(true, true, !hasAllSerials);
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function getSerialConfig(rowKey) {
            rowKey = rowKey || _currentItemModel;
            let isDualImei = _allItemHasSerial2[rowKey] == 1;
            let isTabletSerial = _allItemHasSerialNumber[rowKey] == 1;
            let department = (_allItemDepartment[rowKey] || '').toUpperCase();

            // Fallback to DOM attributes if not in memory
            if (rowKey && !_allItemDepartment[rowKey]) {
                const parts = rowKey.split('-');
                const familyCode = parts.slice(0, -1).join('-');
                const itemNo = parts[parts.length - 1];
                const cell = document.querySelector(`.serial-cell[data-family-code="${familyCode}"][data-item-no="${itemNo}"]`);
                if (cell) {
                    isDualImei = isDualImei || (cell.getAttribute('data-has-serial-2') === '1');
                    isTabletSerial = isTabletSerial || (cell.getAttribute('data-has-serial-number') === '1');
                    department = department || (cell.getAttribute('data-department') || '').toUpperCase();
                }
            }

            const hasCol2 = isDualImei || isTabletSerial;

            let col1Label = 'IMEI';
            let col2Label = 'IMEI 2';
            let modalTitleBase = 'IMEI';

            if (department === 'TABLET') {
                if (isTabletSerial) {
                    col1Label = 'IMEI';
                    col2Label = 'Serial Number';
                    modalTitleBase = 'IMEI & Serial Number';
                } else if (isDualImei) {
                    col1Label = 'IMEI';
                    col2Label = 'IMEI 2';
                    modalTitleBase = 'IMEI';
                } else {
                    col1Label = 'Serial Number';
                    col2Label = 'Serial Number 2';
                    modalTitleBase = 'Serial Number';
                }
            } else if (department === 'MOBILE') {
                col1Label = 'IMEI';
                col2Label = 'IMEI 2';
                modalTitleBase = 'IMEI';
            } else {
                col1Label = isDualImei ? 'Serial 1' : 'Serial Number';
                col2Label = 'Serial 2';
                modalTitleBase = 'Serial Number';
            }

            return { hasCol2, isDualImei, isTabletSerial, department, col1Label, col2Label, modalTitleBase };
        }

        function hasSerial2Active() {
            return getSerialConfig().hasCol2;
        }

        function formatSerialDisplayHtml(rowKey, serialNumbers, serials2) {
            const s1 = Array.isArray(serialNumbers) ? serialNumbers : [];
            const s2 = Array.isArray(serials2) ? serials2 : [];
            const totalPairs = Math.max(s1.length, s2.length);
            if (totalPairs === 0) return '';

            const config = getSerialConfig(rowKey);
            const secLabel = (config.col2Label === 'Serial Number') ? 'S/N' : config.col2Label;

            const lines = [];
            for (let i = 0; i < totalPairs; i++) {
                const val1 = s1[i] !== undefined ? s1[i] : '';
                const val2 = s2[i] !== undefined ? s2[i] : '';

                let line = '';
                if (totalPairs > 1) {
                    line += `<span style="font-weight: 600; color: #555; margin-right: 4px;">${i + 1}.</span>`;
                }
                line += escapeHtml(val1);
                if (val2 !== '') {
                    line += ` <span style="color: #2563eb; font-size: 12px; font-weight: 600;">(${escapeHtml(secLabel)}: ${escapeHtml(val2)})</span>`;
                }
                lines.push(line);
            }
            return lines.join('<br>');
        }

        function openSerialModal(rowKey, itemDescription, quantity, isEditMode = false) {
            _currentItemModel = rowKey; // Using rowKey (family_code-item_no) as the key
            _currentItemDescription = itemDescription;
            _currentItemQuantity = quantity;
            _isModalEditMode = isEditMode;
            _shouldAutoSave = false; // Reset auto-save flag when opening modal

            // Load existing serial numbers for this item
            _currentSerialNumbers = [...(_allSerialNumbers[rowKey] || [])];
            _currentSerialNumbers2 = [...(_allSerialNumbers2[rowKey] || [])];

            const config = getSerialConfig(rowKey);

            // Clear inputs
            const sInput = document.getElementById('serialNumberInput');
            if (sInput) {
                sInput.value = '';
                sInput.placeholder = 'Enter ' + config.col1Label;
            }
            const s2Input = document.getElementById('serial2NumberInput');
            if (s2Input) {
                s2Input.value = '';
                s2Input.placeholder = 'Enter ' + config.col2Label;
            }

            // Update modal header based on mode & item type
            const modalHeader = document.querySelector('.serial-modal-header');
            if (modalHeader) {
                modalHeader.textContent = (isEditMode ? 'Edit ' : 'Add ') + config.modalTitleBase;
            }

            // Update input labels
            const label1 = document.getElementById('serial1Label');
            if (label1) {
                label1.innerHTML = `${config.col1Label} <span style="float: right; font-weight: 700; color: #1a7a35;"><span id="serialCountCurrent">0</span>/<span id="serialCountTotal">0</span></span>`;
            }
            const label2 = document.getElementById('serial2Label');
            if (label2) {
                label2.innerHTML = `${config.col2Label} <span style="float: right; font-weight: 700; color: #1a7a35;"><span id="serial2CountCurrent">0</span>/<span id="serial2CountTotal">0</span></span>`;
            }

            // Update column headers
            const header1 = document.getElementById('serial1TableHeader');
            if (header1) { header1.textContent = config.col1Label; }
            const header2 = document.getElementById('serial2TableHeader');
            if (header2) {
                header2.textContent = config.col2Label;
                header2.style.display = config.hasCol2 ? 'table-cell' : 'none';
            }

            const modalContent = document.getElementById('serialModalContent');
            const s2InputSec = document.getElementById('serial2InputSection');

            if (config.hasCol2) {
                if (modalContent) modalContent.style.maxWidth = '700px';
                if (s2InputSec) s2InputSec.style.display = 'block';
            } else {
                if (modalContent) modalContent.style.maxWidth = '550px';
                if (s2InputSec) s2InputSec.style.display = 'none';
            }

            // Update quantity counter
            updateSerialQuantityCounter();

            populateSerialList();

            // Show modal
            document.getElementById('serialModal').style.display = 'flex';

            setTimeout(() => {
                if (sInput) sInput.focus();
            }, 50);
        }

        function closeSerialModal() {
            document.getElementById('serialModal').style.display = 'none';
            _currentItemModel = '';
            _currentItemDescription = '';
            _currentItemQuantity = 0;
            _currentSerialNumbers = [];
            _currentSerialNumbers2 = [];
            _isModalEditMode = false;
            _shouldAutoSave = false; // Reset auto-save flag
        }

        function addSerialToList() {
            const input = document.getElementById('serialNumberInput');
            const serialNumber = input.value.trim();
            const config = getSerialConfig();

            if (!serialNumber) {
                alert('Please enter a ' + config.col1Label + '.');
                return;
            }
            if (_currentSerialNumbers.includes(serialNumber)) {
                alert('This ' + config.col1Label + ' is already added for this item.');
                return;
            }
            for (let itemKey in _allSerialNumbers) {
                if (_allSerialNumbers[itemKey].includes(serialNumber)) {
                    alert('This ' + config.col1Label + ' is already used for another item.');
                    return;
                }
            }
            if (_currentSerialNumbers.length >= _currentItemQuantity) {
                alert(`Maximum ${_currentItemQuantity} entries allowed.`);
                return;
            }
            const formData = new FormData();
            formData.append('po_id', <?php echo (int) $po_id; ?>);
            formData.append('serial_number', serialNumber);
            fetch('check_duplicate_serial.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(result => {
                    if (result.success && result.is_duplicate) {
                        alert('Error: ' + result.message);
                    } else if (!result.success) {
                        alert('Error: ' + result.message);
                    } else {
                        _currentSerialNumbers.push(serialNumber);
                        input.value = '';
                        _shouldAutoSave = true;
                        populateSerialList();
                        if (hasSerial2Active() && _currentSerialNumbers2.length < _currentItemQuantity) {
                            const s2 = document.getElementById('serial2NumberInput');
                            if (s2) s2.focus();
                        }
                    }
                })
                .catch(err => alert('Error: ' + err.message));
        }

        function addSerial2ToList() {
            const input = document.getElementById('serial2NumberInput');
            const serialNumber = input.value.trim();
            const config = getSerialConfig();

            if (!serialNumber) {
                alert('Please enter ' + config.col2Label + '.');
                return;
            }
            if (_currentSerialNumbers2.includes(serialNumber)) {
                alert('This ' + config.col2Label + ' is already added for this item.');
                return;
            }
            for (let itemKey in _allSerialNumbers2) {
                if (_allSerialNumbers2[itemKey].includes(serialNumber)) {
                    alert('This ' + config.col2Label + ' is already used for another item.');
                    return;
                }
            }
            if (_currentSerialNumbers2.length >= _currentItemQuantity) {
                alert(`Maximum ${_currentItemQuantity} entries allowed.`);
                return;
            }
            _currentSerialNumbers2.push(serialNumber);
            input.value = '';
            _shouldAutoSave = true;
            populateSerialList();
            if (_currentSerialNumbers.length < _currentItemQuantity) {
                const s1 = document.getElementById('serialNumberInput');
                if (s1) s1.focus();
            }
        }

        function removeSerialRow(index) {
            if (_currentSerialNumbers[index] !== undefined) {
                _currentSerialNumbers.splice(index, 1);
            }
            if (_currentSerialNumbers2[index] !== undefined) {
                _currentSerialNumbers2.splice(index, 1);
            }
            _shouldAutoSave = false;
            populateSerialList();
        }

        function removeSerialFromList(index) {
            removeSerialRow(index);
        }

        function populateSerialList() {
            const tbody = document.getElementById('serialListBody');
            if (!tbody) return;
            tbody.innerHTML = '';

            const config = getSerialConfig();
            const isDual = config.hasCol2;

            const s2Header = document.getElementById('serial2TableHeader');
            if (s2Header) {
                s2Header.textContent = config.col2Label;
                s2Header.style.display = isDual ? 'table-cell' : 'none';
            }
            const s1Header = document.getElementById('serial1TableHeader');
            if (s1Header) { s1Header.textContent = config.col1Label; }

            if (isDual) {
                const totalRows = Math.max(_currentSerialNumbers.length, _currentSerialNumbers2.length, _currentItemQuantity);
                for (let index = 0; index < totalRows; index++) {
                    const val1 = _currentSerialNumbers[index] !== undefined ? _currentSerialNumbers[index] : '';
                    const val2 = _currentSerialNumbers2[index] !== undefined ? _currentSerialNumbers2[index] : '';

                    const row = document.createElement('tr');
                    if (_isModalEditMode) {
                        row.innerHTML = `
                            <td style="text-align: center; vertical-align: middle; font-weight: 600;">${index + 1}</td>
                            <td><input type="text" class="serial-table-input" id="tableImei1_${index}"
                                   value="${escapeHtml(val1)}" placeholder="Enter ${escapeHtml(config.col1Label)}"
                                   oninput="updateSerialAtIndex(${index}, 'imei1', this.value)"></td>
                            <td><input type="text" class="serial-table-input" id="tableImei2_${index}"
                                   value="${escapeHtml(val2)}" placeholder="Enter ${escapeHtml(config.col2Label)}"
                                   oninput="updateSerialAtIndex(${index}, 'imei2', this.value)"></td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button class="btn-remove-from-list" onclick="removeSerialRow(${index})">Remove</button>
                            </td>`;
                    } else {
                        const d1 = val1 ? escapeHtml(val1) : '<span style="color:#999;font-style:italic;">Pending...</span>';
                        const d2 = val2 ? escapeHtml(val2) : '<span style="color:#999;font-style:italic;">Pending...</span>';
                        row.innerHTML = `
                            <td style="text-align: center; vertical-align: middle; font-weight: 600;">${index + 1}</td>
                            <td style="text-align: center; vertical-align: middle;">${d1}</td>
                            <td style="text-align: center; vertical-align: middle;">${d2}</td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button class="btn-remove-from-list" onclick="removeSerialRow(${index})">Remove</button>
                            </td>`;
                    }
                    tbody.appendChild(row);
                }
            } else {
                for (let i = 0; i < Math.max(_currentSerialNumbers.length, _currentItemQuantity); i++) {
                    const serialValue = _currentSerialNumbers[i] !== undefined ? _currentSerialNumbers[i] : '';
                    const row = document.createElement('tr');
                    if (_isModalEditMode) {
                        row.innerHTML = `
                            <td style="text-align: center; vertical-align: middle; font-weight: 600;">${i + 1}</td>
                            <td><input type="text" class="serial-table-input" id="tableImei1_${i}"
                                   value="${escapeHtml(serialValue)}" placeholder="Enter ${escapeHtml(config.col1Label)}"
                                   oninput="updateSerialAtIndex(${i}, 'imei1', this.value)"></td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button class="btn-remove-from-list" onclick="removeSerialRow(${i})">Remove</button>
                            </td>`;
                    } else {
                        row.innerHTML = `
                            <td style="text-align: center; vertical-align: middle; font-weight: 600;">${i + 1}</td>
                            <td style="text-align: center; vertical-align: middle;">${escapeHtml(serialValue)}</td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button class="btn-remove-from-list" onclick="removeSerialRow(${i})">Remove</button>
                            </td>`;
                    }
                    tbody.appendChild(row);
                }
            }

            // Update quantity counter
            updateSerialQuantityCounter();
        }

        function updateSerialAtIndex(index, field, value) {
            _shouldAutoSave = false;
            if (field === 'imei1') {
                _currentSerialNumbers[index] = value;
            } else if (field === 'imei2') {
                _currentSerialNumbers2[index] = value;
            }
            updateSerialQuantityCounter();
        }

        function updateSerialQuantityCounter() {
            const current = _currentSerialNumbers.filter(s => s && s.trim() !== '').length;
            const total = _currentItemQuantity;

            const currentElem = document.getElementById('serialCountCurrent');
            const totalElem = document.getElementById('serialCountTotal');
            if (currentElem) currentElem.textContent = current;
            if (totalElem) totalElem.textContent = total;

            if (currentElem && currentElem.parentElement) {
                const parentSpan = currentElem.parentElement;
                if (current === total) {
                    parentSpan.style.color = '#1a7a35';
                } else if (current > total) {
                    parentSpan.style.color = '#c62828';
                } else {
                    parentSpan.style.color = '#856404';
                }
            }

            const isDual = hasSerial2Active();
            if (isDual) {
                const current2 = _currentSerialNumbers2.filter(s => s && s.trim() !== '').length;
                const c2Elem = document.getElementById('serial2CountCurrent');
                const t2Elem = document.getElementById('serial2CountTotal');
                if (c2Elem) c2Elem.textContent = current2;
                if (t2Elem) t2Elem.textContent = total;

                if (c2Elem && c2Elem.parentElement) {
                    const parentSpan2 = c2Elem.parentElement;
                    if (current2 === total) {
                        parentSpan2.style.color = '#1a7a35';
                    } else if (current2 > total) {
                        parentSpan2.style.color = '#c62828';
                    } else {
                        parentSpan2.style.color = '#856404';
                    }
                }
            }
        }

        function saveSerialNumbers() {
            // Clean/trim
            _currentSerialNumbers = _currentSerialNumbers.map(s => (s || '').trim()).filter(s => s !== '');
            if (hasSerial2Active()) {
                _currentSerialNumbers2 = _currentSerialNumbers2.map(s => (s || '').trim()).filter(s => s !== '');
            }

            // Save current serial numbers to global storage
            _allSerialNumbers[_currentItemModel] = [..._currentSerialNumbers];
            if (hasSerial2Active()) {
                _allSerialNumbers2[_currentItemModel] = [..._currentSerialNumbers2];
            }

            // Update the serial cell display
            updateSerialButtonDisplay(_currentItemModel);

            // Update the Edit Serial button visibility
            updateSerialActionButton(_currentItemModel);

            // Update the Edit Item Type/Status button visibility
            updateItemTypeStatusActionButton(_currentItemModel);

            // Update the quantity display (received/total format)
            updateQuantityDisplay(_currentItemModel);

            // Update the grand total quantity
            updateGrandTotalQuantity();

            // Close modal
            closeSerialModal();

            // Show message that changes are staged
            alert('✅ Serial numbers updated! Click "Save Modification" to save changes.');
        }

        function updateSerialButtonDisplay(rowKey) {
            // Update the serial-cell HTML content with paired display
            const serialCells = document.querySelectorAll('.serial-cell');
            serialCells.forEach(cell => {
                const cellFamilyCode = cell.getAttribute('data-family-code');
                const cellItemNo = cell.getAttribute('data-item-no');
                const cellRowKey = cellFamilyCode + '-' + cellItemNo;

                if (cellRowKey === rowKey) {
                    const s1 = _allSerialNumbers[rowKey] || [];
                    const s2 = _allSerialNumbers2[rowKey] || [];

                    if (s1.length === 0 && s2.length === 0) {
                        cell.innerHTML = '-';
                        return;
                    }

                    const html = formatSerialDisplayHtml(rowKey, s1, s2);
                    cell.innerHTML = html || '-';
                }
            });
        }

        function updateSerialActionButton(rowKey) {
            // Find the serial action cell and update the Edit button visibility
            const serialActionCells = document.querySelectorAll('.serial-action-cell');
            serialActionCells.forEach(cell => {
                const cellFamilyCode = cell.getAttribute('data-family-code');
                const cellItemNo = cell.getAttribute('data-item-no');
                const cellRowKey = cellFamilyCode + '-' + cellItemNo;

                if (cellRowKey === rowKey) {
                    const serialNumbers = _allSerialNumbers[rowKey] || [];
                    const itemDescription = cell.getAttribute('data-item-description');
                    const quantity = cell.getAttribute('data-quantity');
                    const hasSerial = cell.getAttribute('data-has-serial');

                    // Show Edit button if serial numbers exist OR if item requires serial (has_serial = 1)
                    if (serialNumbers.length > 0 || hasSerial == '1') {
                        cell.innerHTML = `
                            <div style="display: flex; justify-content: center; align-items: center;">
                                <button class="btn-edit-serial" 
                                        onclick="editSerialNumbers('${cellFamilyCode}', ${cellItemNo}, '${itemDescription}', ${quantity})"
                                        title="Edit IMEI">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                    </svg>
                                </button>
                            </div>
                        `;
                    } else {
                        // No serial numbers yet and doesn't require serial, show dash
                        cell.innerHTML = '<span style="color: #999; font-size: 12px;">-</span>';
                    }
                }
            });
        }

        // ===== ITEM TYPE/STATUS FUNCTIONS =====

        function editItemTypeStatus(familyCode, itemNo, itemModel) {
            const rowKey = familyCode + '-' + itemNo;
            const serialNumbers = _allSerialNumbers[rowKey] || [];

            if (serialNumbers.length === 0) {
                alert('No serial numbers found for this item.');
                return;
            }

            // Store current item being edited
            window._currentEditingItemTypeStatus = {
                rowKey: rowKey,
                familyCode: familyCode,
                itemNo: itemNo,
                itemModel: itemModel,
                serialNumbers: serialNumbers,
                types: {} // Will store type for each serial number
            };

            // Load existing types from database via AJAX
            const poId = <?php echo $po_id; ?>;

            fetch('get_item_types.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    po_id: poId,
                    family_code: familyCode,
                    item_no: itemNo
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.types) {
                        // Load the types from database
                        window._currentEditingItemTypeStatus.types = data.types;

                        // Also update the storage
                        if (!window._itemTypeStatusStorage) {
                            window._itemTypeStatusStorage = {};
                        }
                        window._itemTypeStatusStorage[rowKey] = data.types;
                    }

                    // Populate the modal table
                    populateItemTypeStatusModal();
                })
                .catch(error => {
                    console.error('Error loading item types:', error);
                    // Even if there's an error, still show the modal with defaults
                    populateItemTypeStatusModal();
                });
        }

        function populateItemTypeStatusModal() {
            const displayItemModel = window._currentEditingItemTypeStatus.itemModel || window._currentEditingItemTypeStatus.familyCode;
            const serialNumbers = window._currentEditingItemTypeStatus.serialNumbers;

            // Populate the table
            const tableBody = document.getElementById('itemTypeStatusTableBody');
            tableBody.innerHTML = '';

            serialNumbers.forEach((serialNum, index) => {
                const currentType = window._currentEditingItemTypeStatus.types[serialNum] || 'Good Stock';

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td style="text-align: left; padding-left: 15px; font-weight: 600;">${escapeHtml(displayItemModel)}</td>
                    <td style="text-align: left; padding-left: 15px;">${escapeHtml(serialNum)}</td>
                    <td>
                        <select class="item-type-dropdown" data-serial="${escapeHtml(serialNum)}" onchange="updateItemType('${serialNum.replace(/'/g, "\\'")}', this.value)">
                            <option value="Good Stock" ${currentType === 'Good Stock' ? 'selected' : ''}>Good Stock</option>
                            <option value="Defective" ${currentType === 'Defective' ? 'selected' : ''}>Defective</option>
                            <option value="Demo" ${currentType === 'Demo' ? 'selected' : ''}>Demo</option>
                            <option value="Serviced" ${currentType === 'Serviced' ? 'selected' : ''}>Serviced</option>
                        </select>
                    </td>
                    <td>
                        <button class="btn-remove-from-list" onclick="removeItemTypeRow(${index})">Remove</button>
                    </td>
                `;
                tableBody.appendChild(row);
            });

            // Show the modal
            document.getElementById('itemTypeStatusModal').style.display = 'flex';
        }

        function updateItemType(serialNumber, type) {
            if (window._currentEditingItemTypeStatus) {
                window._currentEditingItemTypeStatus.types[serialNumber] = type;
            }
        }

        function removeItemTypeRow(index) {
            if (!window._currentEditingItemTypeStatus) {
                return;
            }

            // Get the serial number being removed
            const serialNumber = window._currentEditingItemTypeStatus.serialNumbers[index];

            // Remove from serial numbers array
            window._currentEditingItemTypeStatus.serialNumbers.splice(index, 1);

            // Remove from types object
            if (window._currentEditingItemTypeStatus.types[serialNumber]) {
                delete window._currentEditingItemTypeStatus.types[serialNumber];
            }

            // Re-render the table
            const tableBody = document.getElementById('itemTypeStatusTableBody');
            tableBody.innerHTML = '';

            const displayItemModel = window._currentEditingItemTypeStatus.itemModel || window._currentEditingItemTypeStatus.familyCode;

            window._currentEditingItemTypeStatus.serialNumbers.forEach((serialNum, idx) => {
                const currentType = window._currentEditingItemTypeStatus.types[serialNum] || 'Good Stock';

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${idx + 1}</td>
                    <td style="text-align: left; padding-left: 15px; font-weight: 600;">${escapeHtml(displayItemModel)}</td>
                    <td style="text-align: left; padding-left: 15px;">${escapeHtml(serialNum)}</td>
                    <td>
                        <select class="item-type-dropdown" data-serial="${escapeHtml(serialNum)}" onchange="updateItemType('${escapeHtml(serialNum)}', this.value)">
                            <option value="Good Stock" ${currentType === 'Good Stock' ? 'selected' : ''}>Good Stock</option>
                            <option value="Defective" ${currentType === 'Defective' ? 'selected' : ''}>Defective</option>
                            <option value="Demo" ${currentType === 'Demo' ? 'selected' : ''}>Demo</option>
                            <option value="Serviced" ${currentType === 'Serviced' ? 'selected' : ''}>Serviced</option>
                        </select>
                    </td>
                    <td>
                        <button class="btn-remove-from-list" onclick="removeItemTypeRow(${idx})">Remove</button>
                    </td>
                `;
                tableBody.appendChild(row);
            });

            // If all items removed, close the modal
            if (window._currentEditingItemTypeStatus.serialNumbers.length === 0) {
                alert('All items removed.');
                closeItemTypeStatusModal();
            }
        }

        function closeItemTypeStatusModal() {
            document.getElementById('itemTypeStatusModal').style.display = 'none';
            window._currentEditingItemTypeStatus = null;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function saveItemTypeStatus() {
            if (!window._currentEditingItemTypeStatus) {
                return;
            }

            const { rowKey, familyCode, itemNo, types } = window._currentEditingItemTypeStatus;
            const poId = <?php echo $po_id; ?>;

            // Initialize storage if needed
            if (!window._itemTypeStatusStorage) {
                window._itemTypeStatusStorage = {};
            }

            // Save the types to memory first
            window._itemTypeStatusStorage[rowKey] = { ...types };

            // Send to backend to save in database
            fetch('save_item_type_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    po_id: poId,
                    family_code: familyCode,
                    item_no: itemNo,
                    types: types
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Item types/status saved successfully!');
                        // Close modal
                        closeItemTypeStatusModal();
                    } else {
                        alert('Error saving data: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving data. Please try again.');
                });
        }

        function updateItemTypeStatusActionButton(rowKey) {
            // Find the item type action cell and update the button visibility
            const itemTypeActionCells = document.querySelectorAll('.item-type-action-cell');
            itemTypeActionCells.forEach(cell => {
                const cellFamilyCode = cell.getAttribute('data-family-code');
                const cellItemNo = cell.getAttribute('data-item-no');
                const cellRowKey = cellFamilyCode + '-' + cellItemNo;

                if (cellRowKey === rowKey) {
                    const serialNumbers = _allSerialNumbers[rowKey] || [];
                    const itemDescription = cell.getAttribute('data-item-description');
                    const hasSerial = cell.getAttribute('data-has-serial');

                    // Get item model from the table
                    const itemModelCell = document.querySelector(`.item-model-cell[data-family-code="${cellFamilyCode}"][data-item-no="${cellItemNo}"]`);
                    const itemModel = itemModelCell ? itemModelCell.getAttribute('data-item-model') : cellFamilyCode;

                    // Show Edit button if serial numbers exist
                    if (serialNumbers.length > 0 || hasSerial == '1') {
                        cell.innerHTML = `
                            <div style="display: flex; justify-content: center; align-items: center;">
                                <button class="btn-edit-item-type-status" 
                                        onclick="editItemTypeStatus('${cellFamilyCode}', ${cellItemNo}, '${itemModel}')"
                                        title="Edit Item Type/Status">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                    </svg>
                                </button>
                            </div>
                        `;
                    } else {
                        // No serial numbers yet, show dash
                        cell.innerHTML = '<span style="color: #999; font-size: 12px;">-</span>';
                    }
                }
            });
        }

        function updateQuantityDisplay(rowKey) {
            // Only update the received count span — never replace the whole cell
            // (replacing textContent/innerHTML destroys .item-qty-input and corrupts Save Modification)
            const quantityCells = document.querySelectorAll('.quantity-cell');
            quantityCells.forEach(cell => {
                const cellFamilyCode = cell.getAttribute('data-family-code');
                const cellItemNo = cell.getAttribute('data-item-no');
                const cellRowKey = cellFamilyCode + '-' + cellItemNo;

                if (cellRowKey !== rowKey) return;

                const serialCell = document.querySelector(
                    `.serial-cell[data-family-code="${cellFamilyCode}"][data-item-no="${cellItemNo}"]`
                );
                const hasSerial = serialCell
                    ? (parseInt(serialCell.getAttribute('data-has-serial')) || 0)
                    : 0;

                let receivedCount = 0;
                if (hasSerial === 1) {
                    receivedCount = (_allSerialNumbers[rowKey] || []).length;
                } else if (_allReceivedQty[rowKey] !== undefined && _allReceivedQty[rowKey] !== null) {
                    receivedCount = parseInt(_allReceivedQty[rowKey]) || 0;
                } else {
                    receivedCount = parseInt(cell.getAttribute('data-received-qty')) || 0;
                }

                const receivedSpan = cell.querySelector('.received-count-span');
                if (receivedSpan) {
                    receivedSpan.textContent = receivedCount;
                }
                cell.setAttribute('data-received-qty', receivedCount);
            });
        }

        function updateGrandTotalQuantity() {
            // Calculate total received and total ordered across all items
            let totalReceived = 0;
            let totalOrdered = 0;

            const serialCells = document.querySelectorAll('.serial-cell');
            serialCells.forEach(cell => {
                const cellFamilyCode = cell.getAttribute('data-family-code');
                const cellItemNo = cell.getAttribute('data-item-no');
                const rowKey = cellFamilyCode + '-' + cellItemNo;
                const quantity = parseInt(cell.getAttribute('data-quantity')) || 0;

                // Add to total ordered
                totalOrdered += quantity;

                // Count received quantity
                const hasSerial = parseInt(cell.getAttribute('data-has-serial')) || 0;
                if (hasSerial === 1) {
                    const serialNumbers = _allSerialNumbers[rowKey] || [];
                    totalReceived += serialNumbers.length;
                } else {
                    const receivedQty = parseInt(cell.getAttribute('data-received-qty')) || 0;
                    totalReceived += receivedQty;
                }
            });

            // Find and update the TOTAL QUANTITY cell in grand total row
            const grandTotalRow = document.querySelector('.grand-total-row');
            if (grandTotalRow) {
                const cells = grandTotalRow.querySelectorAll('td');
                // Find the cell with TOTAL QUANTITY label (index 7) and update the next cell (index 8)
                cells.forEach((cell, index) => {
                    if (cell.classList.contains('gt-label') && cell.textContent.trim() === 'TOTAL QUANTITY') {
                        const quantityCell = cells[index + 1];
                        if (quantityCell) {
                            quantityCell.innerHTML = `<strong>${totalReceived}/${totalOrdered}</strong>`;
                        }
                    }
                });
            }
        }

        function editItemModel(familyCode, itemNo, currentItemModel, itemDescription) {
            // Set editing mode flag
            _isEditingExistingItem = true;
            _currentEditingRowKey = familyCode + '-' + itemNo;
            _currentEditingFamilyCode = familyCode;

            // Get the current quantity and has_serial for this specific row
            const serialCell = document.querySelector(`.serial-cell[data-family-code="${familyCode}"][data-item-no="${itemNo}"]`);
            let hasSerial = 0;
            let receivedQty = 0;
            if (serialCell) {
                _originalQuantity = parseInt(serialCell.getAttribute('data-quantity')) || 1;
                hasSerial = parseInt(serialCell.getAttribute('data-has-serial')) || 0;
                receivedQty = parseInt(serialCell.getAttribute('data-received-qty')) || 0;
            }

            // Pre-populate the selected items with the current item model
            _tempSelectedItems = [{
                itemCode: currentItemModel,
                itemDescription: itemDescription,
                quantity: _originalQuantity,
                hasSerial: hasSerial,
                receivedQty: hasSerial ? 0 : receivedQty
            }];

            // Reset modal search input
            document.getElementById('itemModelSearchInput').value = '';

            // Show the selected items section with current item
            document.getElementById('selectedItemsListSection').style.display = 'block';
            updateSelectedItemsList();
            updateSelectedItemsCount();

            // Update button text to "Update"
            const saveBtn = document.getElementById('btnSaveItemModel');
            saveBtn.innerHTML = 'Update Item';

            // Update modal header to "Edit Item"
            const modalHeader = document.querySelector('#itemModelModal .serial-modal-header');
            if (modalHeader) {
                modalHeader.textContent = 'Edit Item';
            }

            // Show modal first
            document.getElementById('itemModelModal').style.display = 'flex';

            // Then automatically search for items matching this family code and show results
            // This will display all items from the family code in search results
            searchItems(familyCode, true);

            // Focus on search input
            requestAnimationFrame(() => {
                document.getElementById('itemModelSearchInput').focus();
            });
        }

        function removeItemModel(familyCode, itemNo) {
            if (!confirm('Are you sure you want to remove the item model for this item?')) {
                return;
            }

            const formData = new FormData();
            formData.append('po_id', <?php echo (int) $po_id; ?>);
            formData.append('family_code', familyCode);
            formData.append('item_no', itemNo);
            formData.append('item_model', '-');
            formData.append('item_description', '-');

            fetch('update_po_item_model.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Item model removed successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                });
        }

        function editSerialNumbers(familyCode, itemNo, itemDescription, quantity) {
            const rowKey = familyCode + '-' + itemNo;
            openSerialModal(rowKey, itemDescription, quantity, true); // Pass true for edit mode
        }

        // Array to track items staged for deletion
        let stagedDeletions = [];

        function deleteItemRow(itemId, familyCode, itemNo) {
            // Find the row
            const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
            if (!row) {
                alert('Item row not found.');
                return;
            }

            // Check if already staged for deletion
            if (stagedDeletions.includes(itemId)) {
                // Undo deletion - restore the row
                row.classList.remove('staged-for-deletion');
                stagedDeletions = stagedDeletions.filter(id => id !== itemId);

                // Update delete button to show "Delete Item"
                const deleteBtn = row.querySelector('.btn-delete-item');
                if (deleteBtn) {
                    deleteBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>`;
                    deleteBtn.title = 'Delete Item';
                    deleteBtn.style.color = '#c62828';
                }

                console.log('Item restored. Will not be deleted.');
            } else {
                // Stage for deletion
                row.classList.add('staged-for-deletion');
                stagedDeletions.push(itemId);

                // Update delete button to show "Undo Delete"
                const deleteBtn = row.querySelector('.btn-delete-item');
                if (deleteBtn) {
                    deleteBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/>
                    </svg>`;
                    deleteBtn.title = 'Undo Delete';
                    deleteBtn.style.color = '#ff9800';
                }

                console.log('Item staged for deletion. Click "Save Modification" to permanently delete.');
            }

            // Update UI feedback
            updateDeleteFeedback();
        }

        function updateDeleteFeedback() {
            const banner = document.getElementById('deletionWarningBanner');
            const countSpan = document.getElementById('deletionCount');

            if (stagedDeletions.length > 0) {
                countSpan.textContent = stagedDeletions.length;
                banner.style.display = 'flex';
                console.log(`${stagedDeletions.length} item(s) staged for deletion. Click "Save Modification" to confirm.`);
            } else {
                banner.style.display = 'none';
            }
        }



        // ========== Item Model Functions ==========
        let _selectedItemModel = '';
        let _selectedItemDescription = '';
        let _searchTimeout = null;
        let _currentEditingFamilyCode = ''; // Store current family code for filtering search
        let _tempSelectedItems = []; // Track multiple selected items in modal
        let _originalQuantity = 0; // Store the original/max quantity for validation

        function openItemModelModal(familyCode, itemNo, currentItemModel, itemDescription) {
            _currentEditingRowKey = familyCode + '-' + itemNo;
            _currentEditingFamilyCode = familyCode; // Store the family code for search filtering
            _isEditingExistingItem = false; // Reset editing flag

            // Get the original quantity for this family code to set as max
            const allCells = document.querySelectorAll('.serial-cell');
            _originalQuantity = 0;
            allCells.forEach(cell => {
                const cellFamilyCode = cell.getAttribute('data-family-code');
                if (cellFamilyCode === familyCode) {
                    const qty = parseInt(cell.getAttribute('data-quantity'));
                    if (qty > _originalQuantity) {
                        _originalQuantity = qty;
                    }
                }
            });

            // Reset temporary selected items array
            _tempSelectedItems = [];

            // Reset modal
            document.getElementById('itemModelSearchInput').value = '';
            document.getElementById('searchResultsSection').style.display = 'none';
            document.getElementById('itemSearchResults').innerHTML = '';
            document.getElementById('selectedItemsListSection').style.display = 'none';
            document.getElementById('selectedItemsList').innerHTML = '';
            updateSelectedItemsCount();

            // Reset button text to default
            const saveBtn = document.getElementById('btnSaveItemModel');
            saveBtn.innerHTML = 'Save All Items (<span id="saveButtonCount">0</span>)';

            // Automatically search for items matching this family code to show available options
            searchItems(familyCode, true);

            // Show modal
            document.getElementById('itemModelModal').style.display = 'flex';

            // Focus on search input
            requestAnimationFrame(() => {
                document.getElementById('itemModelSearchInput').focus();
            });
        }

        function closeItemModelModal() {
            document.getElementById('itemModelModal').style.display = 'none';
            _currentEditingRowKey = '';
            _currentEditingFamilyCode = '';
            _selectedItemModel = '';
            _selectedItemDescription = '';
            _tempSelectedItems = [];
            _isEditingExistingItem = false;
            document.getElementById('itemModelSearchInput').value = '';
            document.getElementById('searchResultsSection').style.display = 'none';
            document.getElementById('selectedItemsListSection').style.display = 'none';

            // Reset button text
            const saveBtn = document.getElementById('btnSaveItemModel');
            saveBtn.innerHTML = 'Save All Items (<span id="saveButtonCount">0</span>)';
        }

        function searchItems(searchTerm, immediate = false) {
            // Allow search for family codes even if less than 2 characters when immediate=true (auto-opening modal)
            if (!immediate && searchTerm.length < 2) {
                document.getElementById('searchResultsSection').style.display = 'none';
                return;
            }

            // For immediate searches (auto-load on modal open), require at least 1 character
            if (immediate && searchTerm.length < 1) {
                document.getElementById('searchResultsSection').style.display = 'none';
                return;
            }

            // Clear previous timeout
            if (_searchTimeout) {
                clearTimeout(_searchTimeout);
            }

            // Function to perform the actual search
            const performSearch = () => {
                // Pass the family code to filter results
                // When immediate=true and searchTerm is the family code, pass it as family_code parameter only
                let searchUrl;
                if (immediate && _currentEditingFamilyCode === searchTerm) {
                    // Auto-loading by family code only
                    searchUrl = 'search_item_po.php?term=&family_code=' + encodeURIComponent(_currentEditingFamilyCode);
                } else {
                    // Regular search with optional family code filter
                    searchUrl = 'search_item_po.php?term=' + encodeURIComponent(searchTerm) + '&family_code=' + encodeURIComponent(_currentEditingFamilyCode);
                }

                fetch(searchUrl)
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            displaySearchResults(data.data);
                        } else {
                            document.getElementById('itemSearchResults').innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #999;">No items found</td></tr>';
                            document.getElementById('searchResultsSection').style.display = 'block';
                        }
                    })
                    .catch(err => {
                        console.error('Search error:', err);
                        document.getElementById('itemSearchResults').innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #c62828;">Error searching items</td></tr>';
                        document.getElementById('searchResultsSection').style.display = 'block';
                    });
            };

            // If immediate is true, search right away without debounce delay
            if (immediate) {
                performSearch();
            } else {
                // Set new timeout to debounce search (for user typing)
                _searchTimeout = setTimeout(performSearch, 300);
            }
        }

        function displaySearchResults(items) {
            const resultsBody = document.getElementById('itemSearchResults');
            resultsBody.innerHTML = '';

            if (items.length === 0) {
                resultsBody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #999;">No items found</td></tr>';
            } else {
                let displayCount = 0;
                items.forEach((item, index) => {
                    // Check if this item is already selected
                    const isSelected = _tempSelectedItems.some(selectedItem => selectedItem.itemCode === item.item_code);

                    // Skip (hide) selected items completely
                    if (isSelected) {
                        return; // Skip this item, don't add it to the table
                    }

                    displayCount++;
                    const row = document.createElement('tr');

                    // Show Select button for non-selected items
                    row.innerHTML = `
                        <td>${displayCount}</td>
                        <td><strong>${item.item_code}</strong></td>
                        <td>${item.description}</td>
                        <td><button class="btn-select-item" onclick="selectItem('${item.item_code.replace(/'/g, "\\'")}', '${item.description.replace(/'/g, "\\'")}', ${item.has_serial || 0})">Select</button></td>
                    `;

                    resultsBody.appendChild(row);
                });

                // If all items are selected, show a message
                if (displayCount === 0) {
                    resultsBody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #999;">All items from this search have been selected</td></tr>';
                }
            }

            document.getElementById('searchResultsSection').style.display = 'block';
        }

        function selectItem(itemCode, itemDescription, hasSerial) {
            // Check if item already selected
            const alreadySelected = _tempSelectedItems.some(item => item.itemCode === itemCode);
            if (alreadySelected) {
                alert('This item has already been selected.');
                return;
            }

            // Add to temporary selected items array with default quantity and received qty
            _tempSelectedItems.push({
                itemCode: itemCode,
                itemDescription: itemDescription,
                quantity: 1,  // Default quantity (ordered)
                hasSerial: hasSerial || 0,  // Track if serialized
                receivedQty: hasSerial ? 0 : 1  // Default received qty (only for non-serialized)
            });

            // Update the selected items list display
            updateSelectedItemsList();
            updateSelectedItemsCount();

            // Refresh search results to show the selected item with "Selected" badge
            refreshSearchResults();

            // DO NOT clear search input or hide search results
            // Keep the search results visible so user can continue selecting

            // Show success message
            showTemporaryMessage('Item added! You can select more items or click "Save All Items" to finish.', 'success');
        }

        function updateSelectedItemsList() {
            const listBody = document.getElementById('selectedItemsList');
            listBody.innerHTML = '';

            if (_tempSelectedItems.length === 0) {
                document.getElementById('selectedItemsListSection').style.display = 'none';
                return;
            }

            document.getElementById('selectedItemsListSection').style.display = 'block';

            let totalQty = 0;
            let hasAnyNonSerialized = _tempSelectedItems.some(item => !item.hasSerial);

            // Show/hide Received column header based on whether any non-serialized items exist
            const receivedHeader = document.getElementById('receivedColumnHeader');
            if (receivedHeader) {
                receivedHeader.style.display = hasAnyNonSerialized ? '' : 'none';
            }

            _tempSelectedItems.forEach((item, index) => {
                totalQty += parseInt(item.quantity) || 0;

                const row = document.createElement('tr');

                // Build the received column cell (only for non-serialized items)
                let receivedCell = '';
                if (!item.hasSerial) {
                    // Non-serialized: show input for received quantity
                    receivedCell = `<td><input type="number" min="0" max="${item.quantity}" value="${item.receivedQty !== undefined && item.receivedQty !== null ? item.receivedQty : item.quantity}" 
                        style="width: 80px; padding: 5px; border: 1px solid #ddd; border-radius: 4px; text-align: center;"
                        onchange="updateReceivedQuantity(${index}, this.value)" /></td>`;
                } else {
                    // Serialized: hide cell
                    receivedCell = `<td style="display: ${hasAnyNonSerialized ? '' : 'none'};">N/A</td>`;
                }

                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td><strong>${item.itemCode}</strong></td>
                    <td>${item.itemDescription}</td>
                    <td><input type="number" min="1" max="${_originalQuantity}" value="${item.quantity}" 
                        style="width: 80px; padding: 5px; border: 1px solid #ddd; border-radius: 4px; text-align: center;"
                        onchange="updateItemQuantity(${index}, this.value)" /></td>
                    ${receivedCell}
                    <td><button class="btn-remove-from-list" onclick="removeSelectedItem(${index})">Remove</button></td>
                `;
                listBody.appendChild(row);
            });

            // Update total quantity displays with color coding
            const totalQtySpan = document.getElementById('selectedItemsTotalQty');
            const totalQtyIndicator = document.getElementById('totalQtyIndicator');

            totalQtySpan.textContent = totalQty + ' / ' + _originalQuantity;
            totalQtyIndicator.textContent = totalQty + ' / ' + _originalQuantity;

            // Color code based on whether total exceeds max
            if (totalQty > _originalQuantity) {
                totalQtySpan.style.color = '#c62828'; // Red if over
                totalQtyIndicator.style.color = '#c62828';
            } else if (totalQty === _originalQuantity) {
                totalQtySpan.style.color = '#1a7a35'; // Green if exact
                totalQtyIndicator.style.color = '#1a7a35';
            } else {
                totalQtySpan.style.color = '#f57c00'; // Orange if under
                totalQtyIndicator.style.color = '#f57c00';
            }
        }

        function updateItemQuantity(index, newQuantity) {
            const qty = parseInt(newQuantity);
            if (qty < 1) {
                alert('Quantity must be at least 1');
                _tempSelectedItems[index].quantity = 1;
                updateSelectedItemsList();
                return;
            }
            if (qty > _originalQuantity) {
                alert(`Quantity cannot exceed the original quantity of ${_originalQuantity}`);
                _tempSelectedItems[index].quantity = _originalQuantity;
                updateSelectedItemsList();
                return;
            }

            // Calculate total quantity
            let totalQty = 0;
            _tempSelectedItems.forEach((item, i) => {
                if (i === index) {
                    totalQty += qty;
                } else {
                    totalQty += parseInt(item.quantity) || 0;
                }
            });

            if (totalQty > _originalQuantity) {
                alert(`Total quantity (${totalQty}) cannot exceed the original quantity of ${_originalQuantity}. Please adjust the quantities.`);
                _tempSelectedItems[index].quantity = 1;
                updateSelectedItemsList();
                return;
            }

            _tempSelectedItems[index].quantity = qty;
            // Adjust receivedQty if it exceeds the new quantity
            if (!_tempSelectedItems[index].hasSerial && _tempSelectedItems[index].receivedQty > qty) {
                _tempSelectedItems[index].receivedQty = qty;
            }
            updateSelectedItemsList();
        }

        function updateReceivedQuantity(index, receivedQty) {
            const qty = parseInt(receivedQty);
            const orderedQty = parseInt(_tempSelectedItems[index].quantity);

            if (isNaN(qty) || qty < 0) {
                alert('Received quantity must be 0 or greater');
                _tempSelectedItems[index].receivedQty = 0;
                updateSelectedItemsList();
                return;
            }

            if (qty > orderedQty) {
                alert(`Received quantity (${qty}) cannot exceed ordered quantity (${orderedQty})`);
                _tempSelectedItems[index].receivedQty = orderedQty;
                updateSelectedItemsList();
                return;
            }

            _tempSelectedItems[index].receivedQty = qty;
            updateSelectedItemsList();
        }

        function removeSelectedItem(index) {
            _tempSelectedItems.splice(index, 1);
            updateSelectedItemsList();
            updateSelectedItemsCount();

            // Always ensure search results section is visible
            document.getElementById('searchResultsSection').style.display = 'block';

            // Refresh search results to show the removed item again
            // Force immediate refresh with family code
            if (_currentEditingFamilyCode) {
                searchItems(_currentEditingFamilyCode, true);
            } else {
                refreshSearchResults();
            }
        }

        function refreshSearchResults() {
            // Get current search results and redisplay them to update button states
            const searchInput = document.getElementById('itemModelSearchInput').value;
            if (searchInput && searchInput.length >= 2) {
                searchItems(searchInput, true);
            } else if (_currentEditingFamilyCode) {
                // If no search term, reload family code results
                searchItems(_currentEditingFamilyCode, true);
            }
        }

        function updateSelectedItemsCount() {
            const count = _tempSelectedItems.length;
            const selectedCountElement = document.getElementById('selectedItemsCount');
            const saveButtonCountElement = document.getElementById('saveButtonCount');

            if (selectedCountElement) {
                selectedCountElement.textContent = count;
            }

            if (saveButtonCountElement) {
                saveButtonCountElement.textContent = count;
            }
        }

        // updateQuantityDisplay is defined earlier — keep a single implementation that
        // only updates .received-count-span and never destroys .item-qty-input.

        function showTemporaryMessage(message, type = 'success') {
            // Create a temporary message elemen  t
            const msgDiv = document.createElement('div');
            msgDiv.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                padding: 15px 25px;
                background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
                color: ${type === 'success' ? '#155724' : '#721c24'};
                border: 1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'};
                border-radius: 5px;
                font-size: 14px;
                font-weight: 600;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideIn 0.3s ease;
            `;
            msgDiv.textContent = message;
            document.body.appendChild(msgDiv);

            // Remove after 3 seconds
            setTimeout(() => {
                msgDiv.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    document.body.removeChild(msgDiv);
                }, 300);
            }, 3000);
        }

        function saveAllSelectedItems() {
            if (_tempSelectedItems.length === 0) {
                alert('Please select at least one item.');
                return;
            }

            // Validate total quantity matches the original quantity exactly
            let totalQty = 0;
            _tempSelectedItems.forEach(item => {
                totalQty += parseInt(item.quantity) || 0;
            });

            if (totalQty !== _originalQuantity) {
                if (totalQty < _originalQuantity) {
                    alert(`Total quantity (${totalQty}) is less than the required quantity of ${_originalQuantity}. Please add more items or adjust quantities to match exactly ${_originalQuantity}.`);
                } else {
                    alert(`Total quantity (${totalQty}) exceeds the maximum quantity of ${_originalQuantity}. Please adjust the quantities to match exactly ${_originalQuantity}.`);
                }
                return;
            }

            // Get the current family code and item_no being edited
            const familyCode = _currentEditingFamilyCode;
            const { itemNo: itemNoStr } = parseRowKey(_currentEditingRowKey);
            const currentItemNo = parseInt(itemNoStr);

            // Disable button to prevent double-clicks
            const saveBtn = document.getElementById('btnSaveItemModel');
            saveBtn.disabled = true;
            const originalText = saveBtn.innerHTML;
            saveBtn.textContent = 'Saving...';

            // If we're editing an existing item (edit mode), always update even if multiple items selected
            if (_isEditingExistingItem && _tempSelectedItems.length === 1) {
                // Update mode - just update the existing row
                const item = _tempSelectedItems[0];
                const formData = new FormData();
                formData.append('po_id', <?php echo (int) $po_id; ?>);
                formData.append('family_code', familyCode);
                formData.append('item_no', currentItemNo);
                formData.append('item_model', item.itemCode);
                formData.append('item_description', item.itemDescription);
                formData.append('quantity', parseInt(item.quantity) || 1);

                // Add received_qty for non-serialized items
                if (!item.hasSerial && item.receivedQty !== undefined) {
                    formData.append('received_qty', parseInt(item.receivedQty) || 0);
                }

                fetch('update_po_item_model.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert('Item model updated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                            saveBtn.disabled = false;
                            saveBtn.innerHTML = originalText;
                        }
                    })
                    .catch(err => {
                        alert('Error: ' + err.message);
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = originalText;
                    });

                return;
            }

            // Strategy for adding new items or splitting:
            // 1. Update the first selected item to the existing row (current item_no)
            // 2. Create new rows for the remaining items

            if (_tempSelectedItems.length === 1) {
                // Single item - just update the existing row
                const item = _tempSelectedItems[0];
                const formData = new FormData();
                formData.append('po_id', <?php echo (int) $po_id; ?>);
                formData.append('family_code', familyCode);
                formData.append('item_no', currentItemNo);
                formData.append('item_model', item.itemCode);
                formData.append('item_description', item.itemDescription);
                formData.append('quantity', parseInt(item.quantity) || 1);

                // Add received_qty for non-serialized items
                if (!item.hasSerial && item.receivedQty !== undefined) {
                    formData.append('received_qty', parseInt(item.receivedQty) || 0);
                }

                fetch('update_po_item_model.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert('Item model updated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                            saveBtn.disabled = false;
                            saveBtn.innerHTML = originalText;
                        }
                    })
                    .catch(err => {
                        alert('Error: ' + err.message);
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = originalText;
                    });

                return;
            }

            // Multiple items selected - need to split
            // Step 1: Update the current row with the first item
            const firstItem = _tempSelectedItems[0];
            const updateFormData = new FormData();
            updateFormData.append('po_id', <?php echo (int) $po_id; ?>);
            updateFormData.append('family_code', familyCode);
            updateFormData.append('item_no', currentItemNo);
            updateFormData.append('item_model', firstItem.itemCode);
            updateFormData.append('item_description', firstItem.itemDescription);
            updateFormData.append('quantity', parseInt(firstItem.quantity) || 1);

            // Add received_qty for non-serialized items
            if (!firstItem.hasSerial && firstItem.receivedQty !== undefined) {
                updateFormData.append('received_qty', parseInt(firstItem.receivedQty) || 0);
            }

            fetch('update_po_item_model.php', {
                method: 'POST',
                body: updateFormData
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Failed to update existing row');
                    }

                    // Step 2: Add remaining items as new rows (if any)
                    if (_tempSelectedItems.length > 1) {
                        // Find the max item_no for this family code
                        let maxItemNo = currentItemNo;
                        const itemModelCells = document.querySelectorAll('.item-model-cell');
                        itemModelCells.forEach(cell => {
                            const cellFamilyCode = cell.getAttribute('data-family-code');
                            const cellItemNo = parseInt(cell.getAttribute('data-item-no'));
                            if (cellFamilyCode === familyCode && cellItemNo > maxItemNo) {
                                maxItemNo = cellItemNo;
                            }
                        });

                        // Prepare remaining items (skip first one)
                        const remainingItems = _tempSelectedItems.slice(1).map((item, index) => ({
                            item_no: maxItemNo + index + 1,
                            family_code: familyCode,
                            category: '<?php echo $conn->real_escape_string($items[0]['category'] ?? ''); ?>',
                            brand: '<?php echo $conn->real_escape_string($items[0]['brand'] ?? ''); ?>',
                            item_model: item.itemCode,
                            item_description: item.itemDescription,
                            quantity: parseInt(item.quantity) || 1,
                            cost: 0,
                            total: 0,
                            received_qty: (!item.hasSerial && item.receivedQty !== undefined) ? parseInt(item.receivedQty) || 0 : null
                        }));

                        // Send all remaining items in a single request
                        const addFormData = new FormData();
                        addFormData.append('po_id', <?php echo (int) $po_id; ?>);
                        addFormData.append('po_number', '<?php echo $conn->real_escape_string($po['po_number']); ?>');
                        addFormData.append('items', JSON.stringify(remainingItems));
                        <?php if ($is_branch_view && !empty($specific_branch)): ?>
                        addFormData.append('skip_allocation', 'true');
                        addFormData.append('branch_name', '<?php echo addslashes($specific_branch); ?>');
                        <?php endif; ?>

                        return fetch('add_po_items.php', {
                            method: 'POST',
                            body: addFormData
                        });
                    }
                    return null;
                })
                .then(res => {
                    if (res) return res.json();
                    return { success: true };
                })
                .then(data => {
                    if (data.success) {
                        alert('All items saved successfully! The item has been split into multiple rows.');

                        // Save current state before reload
                        const invoiceInput = document.getElementById('invoiceNumberInput');
                        const remarksTextarea = document.querySelector('textarea[name="receiving_remarks"]');

                        if (invoiceInput) {
                            sessionStorage.setItem('po_invoice_number', invoiceInput.value);
                        }
                        if (remarksTextarea) {
                            sessionStorage.setItem('po_receiving_remarks', remarksTextarea.value);
                        }

                        // Save all serial numbers
                        const serialData = {};
                        Object.keys(_allSerialNumbers).forEach(rowKey => {
                            if (_allSerialNumbers[rowKey] && _allSerialNumbers[rowKey].length > 0) {
                                serialData[rowKey] = _allSerialNumbers[rowKey];
                            }
                        });
                        if (Object.keys(serialData).length > 0) {
                            sessionStorage.setItem('po_serial_numbers', JSON.stringify(serialData));
                        }

                        // Save receiving mode state
                        if (_isReceivingMode) {
                            sessionStorage.setItem('po_receiving_mode', 'true');
                        }

                        location.reload();
                    } else {
                        alert('Error adding new items: ' + data.message);
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = originalText;
                    }
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalText;
                });
        }

        function saveItemModel() {
            if (!_selectedItemModel) {
                alert('Please select an item model.');
                return;
            }

            // Save the new item model and description using the unique row key
            _allItemModels[_currentEditingRowKey] = _selectedItemModel;
            _allItemDescriptions[_currentEditingRowKey] = _selectedItemDescription;

            // Update the display in the table for this specific row
            updateItemModelDisplay(_currentEditingRowKey);

            // Close modal
            closeItemModelModal();
        }

        function updateItemModelDisplay(rowKey) {
            const currentStatus = '<?php echo $status; ?>';
            const isIncomplete = currentStatus.toLowerCase() === 'incomplete';
            const isCompleted = currentStatus.toLowerCase() === 'completed';

            const { familyCode, itemNo } = parseRowKey(rowKey);

            // Update Item Model cell for this specific row
            const itemModelCells = document.querySelectorAll('.item-model-cell');
            itemModelCells.forEach(cell => {
                const cellFamilyCode = cell.getAttribute('data-family-code');
                const cellItemNo = cell.getAttribute('data-item-no');
                const cellRowKey = cellFamilyCode + '-' + cellItemNo;

                if (cellRowKey === rowKey) {
                    const currentItemModel = cell.getAttribute('data-item-model');
                    const itemDescription = cell.getAttribute('data-item-description');
                    const newItemModel = _allItemModels[rowKey] || currentItemModel;

                    if (isIncomplete || isCompleted) {
                        // For INCOMPLETE or COMPLETED status, hide the button and only show the item model
                        if (newItemModel) {
                            cell.innerHTML = `<span style="font-weight: 600; color: #1a7a35;">${newItemModel}</span>`;
                        } else {
                            cell.innerHTML = '-';
                        }
                    } else {
                        // Original behavior for other statuses
                        // Only show the new item model without dash if it exists
                        if (newItemModel) {
                            // After selection, change button text to "Change Item Model"
                            const buttonText = _allItemModels[rowKey] ? 'Change Item Model' : 'Add Item Model';
                            cell.innerHTML = `<span style="font-weight: 600; color: #1a7a35;">${newItemModel}</span><br><button class="btn-add-item-model" onclick="openItemModelModal('${cellFamilyCode}', '${cellItemNo}', '${currentItemModel}', '${itemDescription}')" style="margin-top: 5px;">${buttonText}</button>`;
                        } else {
                            cell.innerHTML = `<button class="btn-add-item-model" onclick="openItemModelModal('${cellFamilyCode}', '${cellItemNo}', '${currentItemModel}', '${itemDescription}')">Add Item Model</button>`;
                        }
                    }
                }
            });

            // Update Item Description cell for this specific row
            const itemDescriptionCells = document.querySelectorAll('.item-description-cell');
            itemDescriptionCells.forEach(cell => {
                const cellFamilyCode = cell.getAttribute('data-family-code');
                const cellItemNo = cell.getAttribute('data-item-no');
                const cellRowKey = cellFamilyCode + '-' + cellItemNo;

                if (cellRowKey === rowKey) {
                    const newItemDescription = _allItemDescriptions[rowKey];

                    if (newItemDescription) {
                        cell.innerHTML = `<span style="font-weight: 600; color: #1a7a35;">${newItemDescription}</span>`;
                    }
                }
            });
        }

        function checkAllSerialsEntered() {
            console.log('=== checkAllSerialsEntered DEBUG ===');
            const itemsToCheck = Array.isArray(_serializedItems) ? _serializedItems : Object.values(_serializedItems || {});
            for (let item of itemsToCheck) {
                const familyCode = item.family_code;
                const itemNo = item.item_no;
                const rowKey = familyCode + '-' + itemNo;
                const requiredQty = parseInt(item.quantity);

                // Count both existing and new serial numbers
                let totalSerials = 0;

                // Count existing serials from database (stored as newline-separated text)
                let existingCount = 0;
                if (item.serial_number) {
                    const existingSerials = item.serial_number.split('\n').filter(s => s.trim() !== '');
                    existingCount = existingSerials.length;
                    totalSerials += existingCount;
                }

                // Count new serials being added
                let newCount = 0;
                if (_allSerialNumbers[rowKey]) {
                    newCount = _allSerialNumbers[rowKey].length;
                    totalSerials += newCount;
                }

                console.log(`Item: ${rowKey}, Required: ${requiredQty}, Existing: ${existingCount}, New: ${newCount}, Total: ${totalSerials}`);

                if (totalSerials < requiredQty) {
                    console.log(`checkAllSerialsEntered returning false for ${rowKey}`);
                    return false;
                }
            }
            console.log('checkAllSerialsEntered returning true');
            return true;
        }

        function checkIsPOIncomplete() {
            if (!checkAllSerialsEntered()) {
                return true;
            }

            let totalReceived = 0;
            let totalOrdered = 0;

            const serialCells = document.querySelectorAll('.serial-cell');
            if (serialCells.length > 0) {
                serialCells.forEach(cell => {
                    const hasSerial = cell.getAttribute('data-has-serial') === '1';
                    const quantity = parseInt(cell.getAttribute('data-quantity')) || 0;
                    totalOrdered += quantity;

                    if (hasSerial) {
                        const familyCode = cell.getAttribute('data-family-code');
                        const itemNo = cell.getAttribute('data-item-no');
                        const rowKey = familyCode + '-' + itemNo;
                        let count = 0;
                        const existingSerials = cell.textContent.trim();
                        if (existingSerials && existingSerials !== '-' && existingSerials !== 'N/A') {
                            count = existingSerials.split('\n').filter(s => s.trim() !== '').length;
                        }
                        if (_allSerialNumbers[rowKey]) {
                            count = _allSerialNumbers[rowKey].length;
                        }
                        totalReceived += count;
                    } else {
                        const receivedQty = parseInt(cell.getAttribute('data-received-qty')) || 0;
                        totalReceived += receivedQty;
                    }
                });
                if (totalReceived < totalOrdered) {
                    return true;
                }
            } else {
                const totalReceivedDisplay = <?php echo (int) $total_received_qty; ?>;
                const totalOrderedDisplay = <?php echo (int) $total_ordered_qty; ?>;
                if (totalReceivedDisplay < totalOrderedDisplay) {
                    return true;
                }
            }

            return false;
        }

        function updateRowTotal(input) {
            const row = input.closest('tr');
            if (!row) return;

            const qtyInput = row.querySelector('.item-qty-input');
            const costInput = row.querySelector('.item-cost-input');
            const totalSpan = row.querySelector('.row-total-span');

            if (!qtyInput || !costInput) return;

            const qty = parseFloat(qtyInput.value) || 0;
            const cost = parseFloat(costInput.value) || 0;
            const total = qty * cost;

            if (totalSpan) {
                totalSpan.textContent = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            const qtyCell = row.querySelector('.quantity-cell');
            if (qtyCell) {
                qtyCell.setAttribute('data-quantity', qty);
            }

            recalculateGrandTotals();
        }

        function recalculateGrandTotals() {
            let totalOrdered = 0;
            let totalReceived = 0;
            let grandTotal = 0;

            document.querySelectorAll('.items-table tbody tr[data-item-id]').forEach(row => {
                if (row.classList.contains('staged-for-deletion')) return;

                const qtyInput = row.querySelector('.item-qty-input');
                const costInput = row.querySelector('.item-cost-input');
                const qtyCell = row.querySelector('.quantity-cell');

                if (qtyInput && costInput) {
                    const qty = parseFloat(qtyInput.value) || 0;
                    const cost = parseFloat(costInput.value) || 0;
                    const received = qtyCell ? (parseInt(qtyCell.getAttribute('data-received-qty')) || 0) : 0;

                    totalOrdered += qty;
                    totalReceived += received;
                    grandTotal += (qty * cost);
                }
            });

            const grandTotalOrderedSpan = document.getElementById('grandTotalOrdered');
            if (grandTotalOrderedSpan) {
                grandTotalOrderedSpan.textContent = totalOrdered;
            }

            const grandTotalReceivedSpan = document.getElementById('grandTotalReceived');
            if (grandTotalReceivedSpan) {
                grandTotalReceivedSpan.textContent = totalReceived;
            }

            const grandTotalAmountSpan = document.getElementById('grandTotalAmount');
            if (grandTotalAmountSpan) {
                grandTotalAmountSpan.textContent = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        function showConfirmModal(isReceive, isSaveMode = false, isIncomplete = false) {
            document.getElementById('modalIcon').textContent = isReceive ? '✅' : '❌';

            const currentStatus = '<?php echo $status; ?>';

            if (isSaveMode) {
                if (currentStatus.toLowerCase() === 'incomplete' && !isIncomplete) {
                    // When current status is Incomplete and user completes all items/serials
                    document.getElementById('modalTitle').textContent = 'Complete Purchase Order?';
                    document.getElementById('modalMsg').textContent = 'This will mark the Purchase Order as "Completed" with the current items and serial numbers. This action cannot be undone.';
                } else if (isIncomplete) {
                    document.getElementById('modalTitle').textContent = 'Save Purchase Order as Incomplete?';
                    document.getElementById('modalMsg').textContent = 'Not all serial numbers or item quantities have been entered. The Purchase Order will be saved with status "Incomplete". You can add the remaining items later.';
                } else {
                    document.getElementById('modalTitle').textContent = 'Save Purchase Order?';
                    document.getElementById('modalMsg').textContent = 'Are you sure you want to save this Purchase Order with the entered items and serial numbers? This action cannot be undone.';
                }
            } else {
                if (currentStatus.toLowerCase() === 'completed' && isReceive) {
                    // When current status is Completed and user clicks Receive, it becomes Received
                    document.getElementById('modalTitle').textContent = 'Receive Purchase Order?';
                    document.getElementById('modalMsg').textContent = 'This Purchase Order is already completed. Clicking Receive will mark it as "Received". This action cannot be undone.';
                } else {
                    document.getElementById('modalTitle').textContent = isReceive
                        ? 'Receive Purchase Order?'
                        : 'Cancel Purchase Order?';
                    document.getElementById('modalMsg').textContent = isReceive
                        ? 'Are you sure you want to receive this Purchase Order? This action cannot be undone.'
                        : 'Are you sure you want to cancel this Purchase Order? This action cannot be undone.';
                }
            }

            const btn = document.getElementById('modalConfirmBtn');
            if (isSaveMode) {
                if (currentStatus.toLowerCase() === 'incomplete' && !isIncomplete) {
                    btn.textContent = 'Yes, Complete';
                } else {
                    btn.textContent = 'Yes, Save';
                }
            } else {
                btn.textContent = isReceive ? 'Yes, Receive' : 'Yes, Cancel';
            }
            btn.className = 'modal-btn ' + (isReceive ? 'modal-btn-confirm-green' : 'modal-btn-confirm-red');

            document.getElementById('confirmModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('confirmModal').classList.remove('show');
            _pendingStatus = null;
            _isModificationSave = false;
        }

        function saveModificationData() {
            closeModal();

            // Validate that reason to modify is filled (required field)
            const reasonToModify = document.getElementById('reasonToModifyTextarea').value.trim();
            if (!reasonToModify) {
                alert('❌ Please enter a reason for modifying this purchase order.');
                return;
            }

            const formData = new FormData();
            formData.append('po_id', <?php echo (int) $po_id; ?>);
            formData.append('status', '<?php echo addslashes($status); ?>'); // Keep current status
            formData.append('is_modification', '1');

            // Collect all serial numbers
            let serialNumbers = [];
            for (let rowKey in _allSerialNumbers) {
                const { familyCode, itemNo } = parseRowKey(rowKey);
                _allSerialNumbers[rowKey].forEach(serial => {
                    serialNumbers.push({
                        family_code: familyCode,
                        item_no: parseInt(itemNo),
                        serial_number: serial
                    });
                });
            }

            if (serialNumbers.length > 0) {
                formData.append('serial_numbers', JSON.stringify(serialNumbers));
            }

            // Collect all IMEI 2 / S/N values
            let serialNumbers2 = [];
            for (let rowKey in _allSerialNumbers2) {
                const { familyCode, itemNo } = parseRowKey(rowKey);
                (_allSerialNumbers2[rowKey] || []).forEach(serial2 => {
                    serialNumbers2.push({
                        family_code: familyCode,
                        item_no: parseInt(itemNo),
                        imei_2: serial2
                    });
                });
            }

            if (serialNumbers2.length > 0) {
                formData.append('serial_numbers_2', JSON.stringify(serialNumbers2));
            }

            // Collect all item models
            if (Object.keys(_allItemModels).length > 0) {
                const itemModelData = {};
                for (let rowKey in _allItemModels) {
                    const { familyCode, itemNo } = parseRowKey(rowKey);
                    itemModelData[rowKey] = {
                        family_code: familyCode,
                        item_no: parseInt(itemNo),
                        item_model: _allItemModels[rowKey],
                        item_description: _allItemDescriptions[rowKey] || ''
                    };
                }
                formData.append('item_models', JSON.stringify(itemModelData));
            }

            // Collect all received quantities for non-serialized items
            if (Object.keys(_allReceivedQty).length > 0) {
                const receivedQtyData = {};
                for (let rowKey in _allReceivedQty) {
                    const { familyCode, itemNo } = parseRowKey(rowKey);
                    receivedQtyData[rowKey] = {
                        family_code: familyCode,
                        item_no: parseInt(itemNo),
                        received_qty: parseInt(_allReceivedQty[rowKey])
                    };
                }
                formData.append('received_quantities', JSON.stringify(receivedQtyData));
            }

            // Collect updated quantities and costs
            const itemQuantitiesCosts = [];
            document.querySelectorAll('.items-table tbody tr[data-item-id]').forEach(row => {
                const itemId = row.getAttribute('data-item-id');
                const qtyInput = row.querySelector('.item-qty-input');
                const costInput = row.querySelector('.item-cost-input');
                if (qtyInput && costInput) {
                    itemQuantitiesCosts.push({
                        id: parseInt(itemId),
                        family_code: qtyInput.getAttribute('data-family-code'),
                        item_no: parseInt(qtyInput.getAttribute('data-item-no')),
                        quantity: parseFloat(qtyInput.value) || 0,
                        cost: parseFloat(costInput.value) || 0
                    });
                }
            });

            if (itemQuantitiesCosts.length > 0) {
                formData.append('quantities_costs', JSON.stringify(itemQuantitiesCosts));
            }

            // Include staged deletions
            if (stagedDeletions.length > 0) {
                formData.append('deleted_items', JSON.stringify(stagedDeletions));
            }

            // Collect reason to modify (required)
            formData.append('reason_to_modify', reasonToModify);

            // Collect PO remarks (optional)
            const remarks = document.getElementById('remarksTextarea').value.trim();
            if (remarks) {
                formData.append('remarks', remarks);
            }

            // Collect receiving remarks (optional)
            const receivingRemarks = document.getElementById('receivingRemarksTextarea').value.trim();
            if (receivingRemarks) {
                formData.append('receiving_remarks', receivingRemarks);
            }

            // Collect invoice number (optional)
            const invoiceNumber = document.getElementById('invoiceNumberInput').value.trim();
            if (invoiceNumber) {
                formData.append('invoice_number', invoiceNumber);
            }

            // Note: PO Number and PO Date are read-only and should not be modified

            <?php if (!empty($specific_branch)): ?>
            formData.append('receiving_branch', '<?php echo addslashes($specific_branch); ?>');
            <?php endif; ?>

            // Collect Supplier Company
            const supplierCompany = document.getElementById('supplierCompanyInput').value.trim();
            if (supplierCompany) {
                formData.append('supplier_company', supplierCompany);
            }

            // Collect Supplier Name
            const supplierName = document.getElementById('supplierNameInput').value.trim();
            if (supplierName) {
                formData.append('supplier_name', supplierName);
            }

            // Collect Payment Days
            const paymentDays = document.getElementById('paymentDaysInput').value.trim();
            if (paymentDays) {
                formData.append('terms', paymentDays);
            }

            // Collect Payment Due Date
            const paymentDueDate = document.getElementById('paymentDueDateInput').value.trim();
            if (paymentDueDate) {
                formData.append('payment_due_date', paymentDueDate);
            }

            // Send to server
            fetch('save_po_modifications.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Modifications saved successfully!');
                        window.location.reload();
                    } else {
                        alert('❌ Error: ' + (data.message || 'Could not save modifications.'));
                    }
                })
                .catch(err => {
                    console.error('Network error:', err);
                    alert('❌ Network error: Could not save modifications.');
                });
        }

        function confirmAction() {
            // Check if this is a modification save
            if (_isModificationSave) {
                saveModificationData();
                return;
            }

            if (!_pendingStatus) return;

            const statusToSend = _pendingStatus;
            let serialNumbers = [];
            let isIncomplete = false;
            const currentStatus = '<?php echo $status; ?>';

            console.log('=== CONFIRM ACTION DEBUG ===');
            console.log('Current Status:', currentStatus);
            console.log('Status To Send:', statusToSend);
            console.log('Is Receiving Mode:', _isReceivingMode);
            console.log('All Serial Numbers Object:', _allSerialNumbers);

            // If in receiving mode with serialized items, collect serial numbers
            if (_isReceivingMode && statusToSend === 'Received') {
                for (let rowKey in _allSerialNumbers) {
                    const { familyCode, itemNo } = parseRowKey(rowKey);
                    console.log(`Processing rowKey: ${rowKey}, familyCode: ${familyCode}, itemNo: ${itemNo}`);
                    console.log(`Serials for this row:`, _allSerialNumbers[rowKey]);

                    _allSerialNumbers[rowKey].forEach(serial => {
                        const serialObj = {
                            family_code: familyCode,
                            item_no: parseInt(itemNo),
                            serial_number: serial
                        };
                        console.log('Adding serial object:', serialObj);
                        serialNumbers.push(serialObj);
                    });
                }

                // Check if incomplete
                isIncomplete = checkIsPOIncomplete();
                console.log('Is Incomplete:', isIncomplete);
                console.log('Total serial numbers collected:', serialNumbers.length);
                console.log('Serial Numbers Array:', serialNumbers);
            }

            closeModal();

            let finalStatus;
            // Determine final status based on the requested action
            if (statusToSend === 'CANCELED') {
                // If user clicked Cancel, always use CANCELED
                finalStatus = 'CANCELED';
            } else if (_isReceivingMode && statusToSend === 'Received') {
                // This is the Save button logic: if incomplete, keep as Incomplete; otherwise Received
                finalStatus = isIncomplete ? 'Incomplete' : 'Received';
            } else if (currentStatus.toLowerCase() === 'completed' && statusToSend === 'Received') {
                // If current status is Completed and user clicks Receive, it becomes Received
                finalStatus = 'Received';
            } else {
                // For any other action, use the requested status
                finalStatus = statusToSend;
            }

            console.log('Final Status:', finalStatus);

            const formData = new FormData();
            formData.append('po_id', <?php echo (int) $po_id; ?>);
            formData.append('status', finalStatus);

            if (serialNumbers.length > 0) {
                const serialNumbersJson = JSON.stringify(serialNumbers);
                console.log('Serial Numbers JSON to send:', serialNumbersJson);
                formData.append('serial_numbers', serialNumbersJson);
            } else {
                console.log('NO SERIAL NUMBERS TO SEND!');
            }

            // Add item models if any were modified
            if (Object.keys(_allItemModels).length > 0) {
                // Convert rowKey (familyCode-itemNo) format to structured data
                const itemModelData = {};
                for (let rowKey in _allItemModels) {
                    const { familyCode, itemNo } = parseRowKey(rowKey);
                    itemModelData[rowKey] = {
                        family_code: familyCode,
                        item_no: parseInt(itemNo),
                        item_model: _allItemModels[rowKey],
                        item_description: _allItemDescriptions[rowKey] || ''
                    };
                }
                console.log('Item Models to send:', itemModelData);
                formData.append('item_models', JSON.stringify(itemModelData));
            }

            console.log('Sending FormData to update_po_status.php');
            console.log('FormData contents:');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }

            fetch('update_po_status.php', {
                method: 'POST',
                body: formData
            })
                .then(res => {
                    console.log('Response received:', res);
                    return res.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Could not update status.'));
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    alert('Network error: ' + err.message);
                });
        }

        // ========== Receiving Remarks Functions ==========
        let _isSavingRemarks = false; // Flag to prevent multiple saves

        function saveReceivingRemarks() {
            // Prevent multiple simultaneous saves
            if (_isSavingRemarks) {
                return;
            }

            const textarea = document.getElementById('receivingRemarksTextarea');
            const remarks = textarea.value.trim();

            _isSavingRemarks = true;

            // Send to server
            const formData = new FormData();
            formData.append('po_id', <?php echo (int) $po_id; ?>);
            formData.append('receiving_remarks', remarks);

            fetch('update_receiving_remarks.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    _isSavingRemarks = false;
                    if (data.success) {
                        // Show subtle success indicator (optional)
                        textarea.style.borderColor = '#1a7a35';
                        setTimeout(() => {
                            textarea.style.borderColor = '#ddd';
                        }, 1000);
                    } else {
                        alert('Error: ' + (data.message || 'Could not update receiving remarks.'));
                    }
                })
                .catch(err => {
                    _isSavingRemarks = false;
                    alert('Network error: ' + err.message);
                });
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const serialModal = document.getElementById('serialModal');
            const itemModelModal = document.getElementById('itemModelModal');
            const itemTypeStatusModal = document.getElementById('itemTypeStatusModal');

            if (event.target == serialModal) {
                closeSerialModal();
            }
            if (event.target == itemModelModal) {
                closeItemModelModal();
            }
            if (event.target == itemTypeStatusModal) {
                closeItemTypeStatusModal();
            }
        }

        // REMOVED: Serial input Enter key handler - editing is now done directly in table cells
        document.addEventListener('DOMContentLoaded', function () {
            // Serial input field has been removed, editing is now done directly in table cells

            // Handle search input for item model
            const itemModelSearchInput = document.getElementById('itemModelSearchInput');
            if (itemModelSearchInput) {
                itemModelSearchInput.addEventListener('input', function (e) {
                    searchItems(e.target.value);
                });
            }
        });

        // Close modal on overlay click
        document.getElementById('confirmModal').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });

        function printWithLiveSerials() {
            // Open PDF in new window
            const serialData = encodeURIComponent(JSON.stringify(_allSerialNumbers || {}));
            const serialData2 = encodeURIComponent(JSON.stringify(_allSerialNumbers2 || {}));
            window.open('print_po_pdf.php?id=<?php echo $po_id; ?>&live_serials=' + serialData + '&live_serials2=' + serialData2, '_blank', 'width=900,height=700');
        }

        // Handle Enter key in serial inputs
        document.addEventListener('DOMContentLoaded', function () {
            const serialInput = document.getElementById('serialNumberInput');
            if (serialInput) {
                serialInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') { addSerialToList(); }
                });
            }
            const serial2Input = document.getElementById('serial2NumberInput');
            if (serial2Input) {
                serial2Input.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') { addSerial2ToList(); }
                });
            }
        });
    </script>
</body>

</html>