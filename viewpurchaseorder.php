<?php
require_once 'session_check.php';
include 'config.php';

// Validate ID
$po_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($po_id <= 0) {
    header('Location: purchaseorder.php');
    exit;
}

// Determine active page for sidebar based on 'from' parameter
$from_param = isset($_GET['from']) ? $_GET['from'] : '';
$specific_branch = isset($_GET['branch']) ? trim($_GET['branch']) : ''; // Get specific branch if provided

// Get the branch code for the specific branch early on for filtering
$specific_branch_code = '';
if (!empty($specific_branch)) {
    $branch_code_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($specific_branch) . "' LIMIT 1");
    if ($branch_code_query && $branch_code_query->num_rows > 0) {
        $branch_code_result = $branch_code_query->fetch_assoc();
        $specific_branch_code = $branch_code_result['branch_code'];
    }
}

if ($from_param === 'purchaseorderreceive') {
    // Simulate we're on purchaseorderreceive.php for sidebar highlighting
    $_GET['_sidebar_page'] = 'purchaseorderreceive.php';
} else {
    // Default to purchaseorder.php
    $_GET['_sidebar_page'] = 'purchaseorder.php';
}

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
// Sub-admin should be restricted to their assigned branches based on ALLOCATIONS
if (strcasecmp($system_level, 'Super-Admin') !== 0) {
    // Handle multiple branches (comma-separated)
    $branch_names = array_map('trim', explode(',', $user_branch));
    $branch_names_escaped = [];

    foreach ($branch_names as $branch_name) {
        if (!empty($branch_name)) {
            $branch_names_escaped[] = "'" . $conn->real_escape_string($branch_name) . "'";
        }
    }

    // Check if user has allocations for this PO
    $access_granted = false;
    if (!empty($branch_names_escaped)) {
        $access_check_query = $conn->query("
            SELECT COUNT(*) as has_access 
            FROM purchase_order_allocations 
            WHERE po_id = {$po_id} 
            AND branch_name IN (" . implode(', ', $branch_names_escaped) . ")
        ");
        
        if ($access_check_query) {
            $access_result = $access_check_query->fetch_assoc();
            $access_granted = ($access_result['has_access'] > 0);
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
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS imei_2 TEXT");
$conn->query("ALTER TABLE purchase_order_allocations ADD COLUMN IF NOT EXISTS imei_2 TEXT");

// Add received_qty column if it doesn't exist (for non-serialized items)
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS received_qty INT(11) DEFAULT NULL");

// Columns for emergency items added during receive (skip allocation, go to stock on receive)
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS is_receive_added TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS receiving_branch VARCHAR(255) DEFAULT NULL");

// Add receiving_remarks column to purchase_orders table if it doesn't exist
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS receiving_remarks TEXT");

// Add reason_to_modify column to purchase_orders table if it doesn't exist
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS reason_to_modify TEXT");

// Add invoice_number column to purchase_orders table if it doesn't exist
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(100)");

// Fetch branch-specific invoice number if viewing from a specific branch
$branch_invoice_number = null;
$branch_receiving_remarks = null;
if ($from_param === 'purchaseorderreceive' && !empty($specific_branch)) {
    $branch_query = $conn->query("
        SELECT invoice_number, receiving_remarks
        FROM purchase_order_allocations 
        WHERE po_id = {$po_id} 
        AND branch_name = '" . $conn->real_escape_string($specific_branch) . "'
        LIMIT 1
    ");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_result = $branch_query->fetch_assoc();
        $branch_invoice_number = $branch_result['invoice_number'];
        $branch_receiving_remarks = $branch_result['receiving_remarks'];
    }
}

// Use branch-specific invoice number and remarks when viewing from specific branch
// Otherwise use global invoice number and remarks
if (!empty($specific_branch) && $from_param === 'purchaseorderreceive') {
    $display_invoice_number = !empty($branch_invoice_number) ? $branch_invoice_number : ($po['invoice_number'] ?? '');
    $display_receiving_remarks = !empty($branch_receiving_remarks) ? $branch_receiving_remarks : ($po['receiving_remarks'] ?? '');
} else {
    $display_invoice_number = $po['invoice_number'] ?? '';
    $display_receiving_remarks = $po['receiving_remarks'] ?? '';
}

// Collation used for cross-table string comparisons (tables may use mixed collations)
$po_string_collate = 'utf8mb4_unicode_ci';

// Fetch PO items with serialization info
// If from purchaseorderreceive with a specific branch, show only that branch's allocation
$raw_item_rows = [];
if ($from_param === 'purchaseorderreceive' && !empty($specific_branch)) {
    // Allocated items for this branch + emergency receive-added items (no allocation)
    $branch_esc = $conn->real_escape_string($specific_branch);
    $collate = $po_string_collate;

    // 1:1 pair each allocation to one PO item (same family+model). Prevents cartesian
    // duplicates when multiple rows share a model (e.g. editing PINK→BLUE when BLUE already exists).
    // Use BINARY for string compares to avoid utf8mb4_general_ci vs unicode_ci mix.
    $allocated_items_sql = "
        SELECT poi.id, poa.id as allocation_id, poa.po_id, COALESCE(poi.po_number, poa.po_number) as po_number,
               COALESCE(poi.item_no, 0) as item_no,
               COALESCE(NULLIF(poa.family_code, ''), poi.family_code) as family_code, poi.created_at,
               COALESCE(i.has_serial, 0) as has_serial,
               COALESCE(i.has_serial_2, 0) as has_serial_2,
               COALESCE(i.has_serial_number, 0) as has_serial_number,
               COALESCE(i.department, '') as department,
               poa.quantity as allocated_quantity,
               poa.quantity as quantity,
               COALESCE(NULLIF(poa.cost, 0), poi.cost, 0) as cost,
               (poa.quantity * COALESCE(NULLIF(poa.cost, 0), poi.cost, 0)) as total,
               poa.branch_name,
               CASE
                   WHEN poa.item_model IS NOT NULL AND poa.item_model != '' AND poa.item_model != '-'
                   THEN poa.item_model
                   ELSE poi.item_model
               END as item_model,
               CASE
                   WHEN poa.item_description IS NOT NULL AND poa.item_description != '' AND poa.item_description != '-'
                   THEN poa.item_description
                   ELSE poi.item_description
               END as item_description,
               COALESCE(poa.serial_number, poi.serial_number) as serial_number,
               COALESCE(poa.imei_2, poi.imei_2) as imei_2,
               COALESCE(poa.received_qty, 0) as received_qty,
               0 as is_receive_added
        FROM (
            SELECT a.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY a.po_id, BINARY a.branch_name, BINARY a.family_code,
                                    BINARY COALESCE(NULLIF(NULLIF(TRIM(a.item_model), ''), '-'), '')
                       ORDER BY a.id
                   ) AS model_rn
            FROM purchase_order_allocations a
            WHERE a.po_id = {$po_id}
            AND BINARY a.branch_name = BINARY '{$branch_esc}'
        ) poa
        LEFT JOIN (
            SELECT p.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY p.po_id, BINARY p.family_code,
                                    BINARY COALESCE(NULLIF(NULLIF(TRIM(p.item_model), ''), '-'), '')
                       ORDER BY p.id
                   ) AS model_rn
            FROM purchase_order_items p
            WHERE p.po_id = {$po_id}
            AND COALESCE(p.is_receive_added, 0) = 0
        ) poi ON poi.po_id = poa.po_id
            AND BINARY poi.family_code = BINARY poa.family_code
            AND BINARY COALESCE(NULLIF(NULLIF(TRIM(poi.item_model), ''), '-'), '')
                = BINARY COALESCE(NULLIF(NULLIF(TRIM(poa.item_model), ''), '-'), '')
            AND poi.model_rn = poa.model_rn
        LEFT JOIN items i ON (
            BINARY CASE
                WHEN poa.item_model IS NOT NULL AND poa.item_model != '' AND poa.item_model != '-'
                THEN poa.item_model
                ELSE poi.item_model
            END = BINARY i.item_code
        ) AND i.status = 'Active'
        ORDER BY COALESCE(poi.item_no, 0) ASC, poa.id ASC
    ";
    $allocated_result = false;
    try {
        $allocated_result = $conn->query($allocated_items_sql);
    } catch (Throwable $e) {
        $allocated_result = false;
    }
    if (!$allocated_result) {
        // Fallback: allocations only + items master (no poi cartesian join)
        $allocated_items_sql = "
            SELECT NULL as id, poa.id as allocation_id, poa.po_id, poa.po_number,
                   0 as item_no, poa.family_code, poa.created_at,
                   COALESCE(i.has_serial, 0) as has_serial,
                   COALESCE(i.has_serial_2, 0) as has_serial_2,
                   COALESCE(i.has_serial_number, 0) as has_serial_number,
                   COALESCE(i.department, '') as department,
                   poa.quantity as allocated_quantity,
                   poa.quantity as quantity,
                   COALESCE(poa.cost, 0) as cost,
                   (poa.quantity * COALESCE(poa.cost, 0)) as total,
                   poa.branch_name,
                   poa.item_model,
                   poa.item_description,
                   poa.serial_number,
                   poa.imei_2,
                   COALESCE(poa.received_qty, 0) as received_qty,
                   0 as is_receive_added
            FROM purchase_order_allocations poa
            LEFT JOIN items i ON poa.item_model IS NOT NULL AND poa.item_model != '' AND poa.item_model != '-'
                AND BINARY poa.item_model = BINARY i.item_code AND i.status = 'Active'
            WHERE poa.po_id = {$po_id}
            AND BINARY poa.branch_name = BINARY '{$branch_esc}'
            ORDER BY poa.id ASC
        ";
        try {
            $allocated_result = $conn->query($allocated_items_sql);
        } catch (Throwable $e) {
            $allocated_result = false;
        }
    }
    if ($allocated_result) {
        while ($row = $allocated_result->fetch_assoc()) {
            $raw_item_rows[] = $row;
        }
    }

    $receive_added_sql = "
        SELECT poi.id, 0 as allocation_id, poi.po_id, poi.po_number, poi.item_no, poi.family_code, poi.created_at,
               COALESCE(MAX(CASE 
                   WHEN poi.item_model IS NOT NULL 
                       AND poi.item_model != '' 
                       AND poi.item_model != '-' 
                       AND BINARY poi.item_model = BINARY i.item_code
                   THEN i.has_serial 
                   ELSE 0 
               END), 0) as has_serial,
               COALESCE(MAX(CASE 
                   WHEN poi.item_model IS NOT NULL 
                       AND poi.item_model != '' 
                       AND poi.item_model != '-' 
                       AND BINARY poi.item_model = BINARY i.item_code
                   THEN i.has_serial_2 
                   ELSE 0 
               END), 0) as has_serial_2,
               COALESCE(MAX(CASE 
                   WHEN poi.item_model IS NOT NULL 
                       AND poi.item_model != '' 
                       AND poi.item_model != '-' 
                       AND BINARY poi.item_model = BINARY i.item_code
                   THEN i.has_serial_number 
                   ELSE 0 
               END), 0) as has_serial_number,
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
            (poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' AND BINARY poi.item_model = BINARY i.item_code)
            OR (BINARY poi.family_code = BINARY i.family_code)
        ) AND i.status = 'Active'
        WHERE poi.po_id = $po_id
        AND COALESCE(poi.is_receive_added, 0) = 1
        AND BINARY poi.receiving_branch = BINARY '{$branch_esc}'
        GROUP BY poi.id
        ORDER BY poi.item_no ASC
    ";
    $receive_added_result = false;
    try {
        $receive_added_result = $conn->query($receive_added_sql);
    } catch (Throwable $e) {
        $receive_added_result = false;
    }
    if ($receive_added_result) {
        while ($row = $receive_added_result->fetch_assoc()) {
            $raw_item_rows[] = $row;
        }
    }

    usort($raw_item_rows, function ($a, $b) {
        return (int)($a['item_no'] ?? 0) <=> (int)($b['item_no'] ?? 0);
    });
} elseif ($from_param === 'purchaseorderreceive' && strcasecmp($system_level, 'Super-Admin') !== 0) {
    // Show items allocated to user's branch(es) (no specific branch clicked)
    $branch_names = array_map('trim', explode(',', $user_branch));
    $branch_names_escaped = [];
    foreach ($branch_names as $branch) {
        $branch_names_escaped[] = "'" . $conn->real_escape_string($branch) . "'";
    }
    $collate = $po_string_collate;
    
    $items_result = false;
    try {
        $items_result = $conn->query("
        SELECT poi.id, poa.id as allocation_id, poa.po_id, COALESCE(poi.po_number, poa.po_number) as po_number,
               COALESCE(poi.item_no, 0) as item_no,
               COALESCE(NULLIF(poa.family_code, ''), poi.family_code) as family_code, poi.created_at,
               COALESCE(i.has_serial, 0) as has_serial,
               COALESCE(i.has_serial_2, 0) as has_serial_2,
               COALESCE(i.has_serial_number, 0) as has_serial_number,
               COALESCE(i.department, '') as department,
               poa.quantity as allocated_quantity,
               poa.quantity as quantity,
               COALESCE(NULLIF(poa.cost, 0), poi.cost, 0) as cost,
               (poa.quantity * COALESCE(NULLIF(poa.cost, 0), poi.cost, 0)) as total,
               poa.branch_name,
               CASE
                   WHEN poa.item_model IS NOT NULL AND poa.item_model != '' AND poa.item_model != '-'
                   THEN poa.item_model
                   ELSE poi.item_model
               END as item_model,
               CASE
                   WHEN poa.item_description IS NOT NULL AND poa.item_description != '' AND poa.item_description != '-'
                   THEN poa.item_description
                   ELSE poi.item_description
               END as item_description,
               COALESCE(poa.serial_number, poi.serial_number) as serial_number,
               COALESCE(poa.imei_2, poi.imei_2) as imei_2,
               COALESCE(poa.received_qty, 0) as received_qty
        FROM (
            SELECT a.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY a.po_id, BINARY a.branch_name, BINARY a.family_code,
                                    BINARY COALESCE(NULLIF(NULLIF(TRIM(a.item_model), ''), '-'), '')
                       ORDER BY a.id
                   ) AS model_rn
            FROM purchase_order_allocations a
            WHERE a.po_id = {$po_id}
            AND a.branch_name IN (" . implode(', ', $branch_names_escaped) . ")
        ) poa
        LEFT JOIN (
            SELECT p.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY p.po_id, BINARY p.family_code,
                                    BINARY COALESCE(NULLIF(NULLIF(TRIM(p.item_model), ''), '-'), '')
                       ORDER BY p.id
                   ) AS model_rn
            FROM purchase_order_items p
            WHERE p.po_id = {$po_id}
            AND COALESCE(p.is_receive_added, 0) = 0
        ) poi ON poi.po_id = poa.po_id
            AND BINARY poi.family_code = BINARY poa.family_code
            AND BINARY COALESCE(NULLIF(NULLIF(TRIM(poi.item_model), ''), '-'), '')
                = BINARY COALESCE(NULLIF(NULLIF(TRIM(poa.item_model), ''), '-'), '')
            AND poi.model_rn = poa.model_rn
        LEFT JOIN items i ON (
            BINARY CASE
                WHEN poa.item_model IS NOT NULL AND poa.item_model != '' AND poa.item_model != '-'
                THEN poa.item_model
                ELSE poi.item_model
            END = BINARY i.item_code
        ) AND i.status = 'Active'
        ORDER BY COALESCE(poi.item_no, 0) ASC, poa.id ASC
    ");
    } catch (Throwable $e) {
        $items_result = false;
    }
    if (!$items_result) {
        try {
            $items_result = $conn->query("
                SELECT NULL as id, poa.id as allocation_id, poa.po_id, poa.po_number,
                       0 as item_no, poa.family_code, poa.created_at,
                       COALESCE(i.has_serial, 0) as has_serial,
                       COALESCE(i.has_serial_2, 0) as has_serial_2,
                       COALESCE(i.has_serial_number, 0) as has_serial_number,
                       COALESCE(i.department, '') as department,
                       poa.quantity as allocated_quantity,
                       poa.quantity as quantity,
                       COALESCE(poa.cost, 0) as cost,
                       (poa.quantity * COALESCE(poa.cost, 0)) as total,
                       poa.branch_name,
                       poa.item_model,
                       poa.item_description,
                       poa.serial_number,
                       poa.imei_2,
                       COALESCE(poa.received_qty, 0) as received_qty
                FROM purchase_order_allocations poa
                LEFT JOIN items i ON poa.item_model IS NOT NULL AND poa.item_model != '' AND poa.item_model != '-'
                    AND BINARY poa.item_model = BINARY i.item_code AND i.status = 'Active'
                WHERE poa.po_id = {$po_id}
                AND poa.branch_name IN (" . implode(', ', $branch_names_escaped) . ")
                ORDER BY poa.id ASC
            ");
        } catch (Throwable $e) {
            $items_result = false;
        }
    }
    if ($items_result) {
        while ($row = $items_result->fetch_assoc()) {
            $raw_item_rows[] = $row;
        }
    }
} else {
    // Show all items (for Super Admin or when viewing from purchaseorder.php)
    $items_result = $conn->query("
        SELECT poi.*, 
               COALESCE(MAX(CASE 
                   WHEN poi.item_model IS NOT NULL 
                       AND poi.item_model != '' 
                       AND poi.item_model != '-' 
                       AND poi.item_model COLLATE {$po_string_collate} = i.item_code COLLATE {$po_string_collate}
                   THEN i.has_serial 
                   ELSE 0 
               END), 0) as has_serial,
               COALESCE(MAX(CASE 
                   WHEN poi.item_model IS NOT NULL 
                       AND poi.item_model != '' 
                       AND poi.item_model != '-' 
                       AND poi.item_model COLLATE {$po_string_collate} = i.item_code COLLATE {$po_string_collate}
                   THEN i.has_serial_2 
                   ELSE 0 
               END), 0) as has_serial_2,
               COALESCE(MAX(CASE 
                   WHEN poi.item_model IS NOT NULL 
                       AND poi.item_model != '' 
                       AND poi.item_model != '-' 
                       AND poi.item_model COLLATE {$po_string_collate} = i.item_code COLLATE {$po_string_collate}
                   THEN i.has_serial_number 
                   ELSE 0 
               END), 0) as has_serial_number,
               COALESCE(MAX(i.department), '') as department
        FROM purchase_order_items poi 
        LEFT JOIN items i ON (
            (poi.item_model IS NOT NULL AND poi.item_model != '' AND poi.item_model != '-' AND poi.item_model COLLATE {$po_string_collate} = i.item_code COLLATE {$po_string_collate})
            OR (poi.family_code COLLATE {$po_string_collate} = i.family_code COLLATE {$po_string_collate})
        ) AND i.status = 'Active'
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
$items = [];
$grand_total = 0;
$total_received_qty = 0;  // For status calculation only
$total_received_qty_display = 0;  // For GT-AMOUNT display
$total_ordered_qty = 0;
$has_serialized_items = false;
$seen_allocation_ids = [];
foreach ($raw_item_rows as $item) {
        // One display row per allocation — drop cartesian join duplicates
        $alloc_id = (int)($item['allocation_id'] ?? 0);
        if ($alloc_id > 0) {
            if (isset($seen_allocation_ids[$alloc_id])) {
                continue;
            }
            $seen_allocation_ids[$alloc_id] = true;
        }

        // When receiving by branch, always use branch allocation quantity (not total PO item quantity)
        if ($from_param === 'purchaseorderreceive' && isset($item['allocated_quantity'])) {
            $item_quantity = (int)$item['allocated_quantity'];
        } else {
            $row_qty = (int)($item['quantity'] ?? 0);
            $alloc_qty = isset($item['allocated_quantity']) ? (int)$item['allocated_quantity'] : 0;
            $item_quantity = ($row_qty > 0) ? $row_qty : ($alloc_qty > 0 ? $alloc_qty : 0);
        }
        
        $item['quantity'] = $item_quantity;
        $item['total'] = (float)$item['cost'] * $item_quantity;
        
        // ALWAYS add to grand_total (shows allocated cost, not received cost)
        $grand_total += (float)$item['total'];

        $total_ordered_qty += $item_quantity;

        // Count received quantity
        // CRITICAL: For status calculation, only count serial numbers (serialized items)
        // For unserialized items, received_qty is for DISPLAY only (e.g., 10/10), NOT for status
        $received_count = 0;
        $received_count_for_display = 0;
        
        if ($item['has_serial'] == 1) {
            // Serialized items: count actual serial numbers
            if (!empty($item['serial_number'])) {
                $serials = trim($item['serial_number']);
                if (strpos($serials, "\n") !== false) {
                    $serial_array = explode("\n", $serials);
                } else {
                    $serial_array = explode(",", $serials);
                }
                $serial_array = array_filter(array_map('trim', $serial_array));
                $received_count = count($serial_array);
                $received_count_for_display = $received_count;
            }
            // For status calculation, add to total ONLY if serialized
            $total_received_qty += $received_count;
        } else {
            // Unserialized items: received_qty is for display only
            $current_po_status = $po['status'] ?? 'Pending';
            if (isset($item['received_qty']) && $item['received_qty'] !== null && $item['received_qty'] !== '') {
                $received_count_for_display = (int)$item['received_qty'];
            } elseif (strcasecmp($current_po_status, 'Received') === 0 || strcasecmp($current_po_status, 'Completed') === 0) {
                $received_count_for_display = (int)$item['quantity'];
            } else {
                $received_count_for_display = 0;
            }
        }
        $item['calculated_received_qty'] = $received_count_for_display;
        
        // ALWAYS add to display counter (both serialized and unserialized)
        $total_received_qty_display += $received_count_for_display;
        if ($item['has_serial'] == 1) {
            $has_serialized_items = true;
        }
        $items[] = $item;
}

// Status badge class
$status = $po['status'] ?? 'Pending';

// CRITICAL FIX: Calculate branch-specific status when viewing from a specific branch
// BUT respect Canceled, Closed, Received, Completed, and Incomplete statuses from database
if (strcasecmp($status, 'Canceled') !== 0 
    && strcasecmp($status, 'Cancelled') !== 0 
    && strcasecmp($status, 'Closed') !== 0
    && strcasecmp($status, 'Received') !== 0
    && strcasecmp($status, 'Completed') !== 0
    && strcasecmp($status, 'Incomplete') !== 0) {
    // Only recalculate status for Pending or unknown statuses
    if ($total_received_qty == 0) {
        $status = 'Pending';
    } elseif ($total_received_qty < $total_ordered_qty) {
        $status = 'Incomplete';
    } elseif ($total_received_qty >= $total_ordered_qty) {
        $status = 'Received';
    }
}

$badge_class = 'badge-pending';
if (strcasecmp($status, 'Received') === 0)
    $badge_class = 'badge-received';
elseif (strcasecmp($status, 'Completed') === 0)
    $badge_class = 'badge-completed';
elseif (strcasecmp($status, 'Incomplete') === 0)
    $badge_class = 'badge-incomplete';
elseif (strcasecmp($status, 'Canceled') === 0 || strcasecmp($status, 'Cancelled') === 0)
    $badge_class = 'badge-cancelled';
elseif (strcasecmp($status, 'Closed') === 0)
    $badge_class = 'badge-closed';

// Map status for display
$status_display = $status;
if (strcasecmp($status, 'Pending') === 0) {
    $status_display = 'OPEN';
} elseif (strcasecmp($status, 'Received') === 0) {
    $status_display = 'COMPLETED';
} elseif (strcasecmp($status, 'Canceled') === 0 || strcasecmp($status, 'Cancelled') === 0) {
    $status_display = 'CANCELED';
} elseif (strcasecmp($status, 'Closed') === 0) {
    $status_display = 'CLOSED';
} elseif (strcasecmp($status, 'Incomplete') === 0) {
    $status_display = 'INCOMPLETE';
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

// Get full name from accounts table for created_by
$created_by_fullname = $created_by;
if (!empty($created_by) && $created_by !== 'Unknown User' && $created_by !== 'Admin User') {
    // Check if created_by looks like a username (not already a full name)
    // If it doesn't contain a space, it's likely a username, so fetch the full name
    if (strpos($created_by, ' ') === false) {
        $created_user_query = $conn->query("SELECT first_name, last_name FROM accounts WHERE username = '" . $conn->real_escape_string($created_by) . "' LIMIT 1");
        if ($created_user_query && $created_user_query->num_rows > 0) {
            $created_user = $created_user_query->fetch_assoc();
            $created_by_fullname = trim($created_user['first_name'] . ' ' . $created_user['last_name']);
        }
    }
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

// Get full names from accounts table for workflow users
$received_by_fullname = $received_by;
if (!empty($received_by)) {
    $received_user_query = $conn->query("SELECT first_name, last_name FROM accounts WHERE username = '" . $conn->real_escape_string($received_by) . "' LIMIT 1");
    if ($received_user_query && $received_user_query->num_rows > 0) {
        $received_user = $received_user_query->fetch_assoc();
        $received_by_fullname = trim($received_user['first_name'] . ' ' . $received_user['last_name']);
    }
}

$completed_by_fullname = $completed_by;
if (!empty($completed_by)) {
    $completed_user_query = $conn->query("SELECT first_name, last_name FROM accounts WHERE username = '" . $conn->real_escape_string($completed_by) . "' LIMIT 1");
    if ($completed_user_query && $completed_user_query->num_rows > 0) {
        $completed_user = $completed_user_query->fetch_assoc();
        $completed_by_fullname = trim($completed_user['first_name'] . ' ' . $completed_user['last_name']);
    }
}

$incomplete_by_fullname = $incomplete_by;
if (!empty($incomplete_by)) {
    $incomplete_user_query = $conn->query("SELECT first_name, last_name FROM accounts WHERE username = '" . $conn->real_escape_string($incomplete_by) . "' LIMIT 1");
    if ($incomplete_user_query && $incomplete_user_query->num_rows > 0) {
        $incomplete_user = $incomplete_user_query->fetch_assoc();
        $incomplete_by_fullname = trim($incomplete_user['first_name'] . ' ' . $incomplete_user['last_name']);
    }
}

$declined_by_fullname = $declined_by;
if (!empty($declined_by)) {
    $declined_user_query = $conn->query("SELECT first_name, last_name FROM accounts WHERE username = '" . $conn->real_escape_string($declined_by) . "' LIMIT 1");
    if ($declined_user_query && $declined_user_query->num_rows > 0) {
        $declined_user = $declined_user_query->fetch_assoc();
        $declined_by_fullname = trim($declined_user['first_name'] . ' ' . $declined_user['last_name']);
    }
}

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
        $edited_by_username = $edit_row['edited_by'];
        $edited_by_fullname = $edited_by_username;

        // Fetch full name from accounts table
        if (!empty($edited_by_username)) {
            $edited_user_query = $conn->query("SELECT first_name, last_name FROM accounts WHERE username = '" . $conn->real_escape_string($edited_by_username) . "' LIMIT 1");
            if ($edited_user_query && $edited_user_query->num_rows > 0) {
                $edited_user = $edited_user_query->fetch_assoc();
                $edited_by_fullname = trim($edited_user['first_name'] . ' ' . $edited_user['last_name']);
            }
        }

        $edit_history[] = [
            'reason' => $edit_row['edit_reason'],
            'edited_by' => $edited_by_fullname,
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
    <title>View Purchase Order – <?php echo htmlspecialchars($po['po_number']); ?></title>
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

        .badge-open {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa0;
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

        .badge-closed {
            background: #cfe2ff;
            color: #052c65;
            border: 1px solid #9ec5fe;
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
            border: 1px solid #ccc;
        }

        .items-table thead {
            background: var(--color-gold-pale);
        }

        .items-table th {
            text-align: left;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000000;
            border: 1px solid #ccc;
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
            border: 1px solid #ccc;
            text-align: left;
        }

        /* Center align for action columns */
        .items-table .item-model-action-cell,
        .items-table .serial-action-cell,
        .items-table .action-cell,
        .items-table .delete-action-cell {
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

        /* Marked for deletion styling */
        .items-table tbody tr.marked-for-deletion {
            text-decoration: line-through;
            color: #c62828;
            opacity: 0.7;
            background: #ffebee;
        }

        .items-table tbody tr.marked-for-deletion:hover {
            background: #ffcdd2;
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

        /* Add PO Item Button */
        .btn-add-po-item {
            background: #1a7a35;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            transition: background 0.2s;
        }

        .btn-add-po-item:hover {
            background: #155e28;
        }

        /* Search Modal Styles */
        .modal {
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
            font-family: Arial, sans-serif;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            border: 1px solid #888;
            width: 80%;
            max-width: 1000px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: fadeIn 0.3s;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid #eee;
            font-size: 22px;
            font-weight: 700;
            color: #333;
            font-family: Arial, sans-serif;
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .search-results-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #ccc;
        }

        .search-results-table thead {
            background: var(--color-gold-pale);
        }

        .search-results-table th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000000;
            border: 1px solid #ccc;
            font-family: Arial, sans-serif;
        }

        .search-results-table td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
            word-wrap: break-word;
            font-family: Arial, sans-serif;
        }

        .search-results-table tr:hover {
            background-color: #f5f5f5;
        }

        .btn-select {
            background-color: var(--color-navy);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            font-family: Arial, sans-serif;
        }

        .btn-select:hover {
            background-color: var(--color-navy-dark);
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-start;
        }

        .btn-back-modal {
            padding: 10px 30px;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 15px;
            font-family: Arial, sans-serif;
        }

        .btn-back-modal:hover {
            background-color: #f5f5f5;
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
            padding: 8px 10px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
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

        .serial-list-table tbody tr:hover {
            background: #fafafa;
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
            border-color: var(--color-gold);
        }

        .item-type-dropdown:focus {
            outline: none;
            border-color: var(--color-gold);
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

        .btn-edit-item-type-status {
            color: #b08a52;
        }

        .btn-edit-serial:hover {
            background: #e8f5e9;
            color: #155e28;
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
            .btn-complete,
            .btn-approve-completion {
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
            .btn-complete,
            .btn-approve-completion {
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
            <?php
            // Determine back link based on 'from' parameter
            $from = isset($_GET['from']) ? $_GET['from'] : '';
            $backUrl = 'purchaseorder.php'; // default
            
            if ($from === 'purchaseorder') {
                $backUrl = 'purchaseorder.php';
            } elseif ($from === 'purchaseorderreceive') {
                $backUrl = 'purchaseorderreceive.php';
            }
            ?>
            <a class="back-link" href="<?php echo htmlspecialchars($backUrl); ?>">
                <svg viewBox="0 0 24 24">
                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z" />
                </svg>
                Purchase Order Details
            </a>
            <div class="top-actions">
                <button class="btn-print" onclick="printWithLiveSerials()">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z" />
                    </svg>
                    Print
                </button>
                <?php
                // Only show Receive and Cancel buttons if NOT from purchaseorder.php
                $from = isset($_GET['from']) ? $_GET['from'] : '';
                if ($from !== 'purchaseorder' && strcasecmp($status, 'Received') !== 0 && strcasecmp($status, 'CANCELED') !== 0 && strcasecmp($status, 'Cancelled') !== 0 && strcasecmp($status, 'Closed') !== 0):
                    ?>
                    <button class="btn-receive" onclick="updateStatus('Received')">Receive</button>
                    <button class="btn-decline" onclick="updateStatus('CANCELED')">Cancel</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status Bar -->
        <div class="status-bar">
            <div class="status-group">
                <span class="status-label">Status</span>
                <span class="badge <?php echo $badge_class; ?>"
                    id="status-badge"><?php echo htmlspecialchars(strtoupper($status_display)); ?></span>
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
                    // If viewing specific branch allocation from purchaseorderreceive, show only that branch
                    if (!empty($specific_branch)) {
                        echo htmlspecialchars($specific_branch);
                    } else {
                        // Get allocated branches instead of created_by_branch
                        $allocated_branches_query = $conn->query("
                            SELECT DISTINCT branch_name 
                            FROM purchase_order_allocations 
                            WHERE po_id = {$po_id}
                            ORDER BY branch_name ASC
                        ");
                        
                        $allocated_branches = [];
                        if ($allocated_branches_query && $allocated_branches_query->num_rows > 0) {
                            while ($branch_row = $allocated_branches_query->fetch_assoc()) {
                                $allocated_branches[] = $branch_row['branch_name'];
                            }
                        }
                        
                        if (!empty($allocated_branches)) {
                            echo htmlspecialchars(implode(', ', $allocated_branches));
                        } else {
                            echo 'No Allocations Yet';
                        }
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
                        <span class="detail-val"><?php echo htmlspecialchars($po['po_number']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-key">PO Date</span>
                        <span class="detail-val"><?php echo $po_date_fmt; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-key">Invoice Number <span
                                style="color: #c62828; font-weight: bold;">*</span></span>
                        <input type="text" id="invoiceNumberInput"
                            value="<?php echo htmlspecialchars($display_invoice_number); ?>"
                            placeholder="Enter invoice number"
                            style="width: 100%; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif; <?php if ($from === 'purchaseorder' || strcasecmp($status, 'Received') === 0 || ($from !== 'purchaseorderreceive' && strcasecmp($status, 'Completed') === 0) || strcasecmp($status, 'CANCELED') === 0 || strcasecmp($status, 'Cancelled') === 0): ?>background-color: #f5f5f5; cursor: not-allowed;<?php endif; ?>"
                            <?php if ($from === 'purchaseorder' || strcasecmp($status, 'Received') === 0 || ($from !== 'purchaseorderreceive' && strcasecmp($status, 'Completed') === 0) || strcasecmp($status, 'CANCELED') === 0 || strcasecmp($status, 'Cancelled') === 0): ?>readonly<?php endif; ?> />
                    </div>
                    <div class="detail-item">
                        <span class="detail-key">Supplier Company Name</span>
                        <span class="detail-val"><?php echo htmlspecialchars($po['supplier_company'] ?: '-'); ?></span>
                    </div>
                    <!-- Reason to Modify - Only show if not empty -->
                    <?php if (!empty($po['reason_to_modify'])): ?>
                        <div class="detail-item full">
                            <span class="detail-key">Reason to Modify</span>
                            <textarea readonly
                                style="width: 100%; min-height: 80px; padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif; background-color: #f9f9f9; margin-top: 5px; color: #333; resize: vertical; line-height: 1.5;"
                            ><?php echo htmlspecialchars($po['reason_to_modify']); ?></textarea>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Terms Card -->
            <div class="info-card">
                <h4>Payment Terms</h4>
                <div class="detail-grid two-col">
                    <div class="detail-item">
                        <span class="detail-key">Payment Days</span>
                        <span class="detail-val"><?php echo htmlspecialchars($terms_display); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-key">Payment Due Date</span>
                        <span class="detail-val"><?php echo $due_date_fmt; ?></span>
                    </div>
                    <div class="detail-item full">
                        <span class="detail-key">PO Remarks</span>
                        <span class="detail-val light"><?php echo htmlspecialchars($po['remarks'] ?: '-'); ?></span>
                    </div>
                    <!-- Receiving Remarks - Full width (Editable) -->
                    <div class="detail-item full">
                        <span class="detail-key">Receiving Remarks (Optional)</span>
                        <textarea id="receivingRemarksTextarea"
                            placeholder="Enter any remarks about receiving this purchase order..."
                            style="width: 100%; min-height: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif; resize: vertical; margin-top: 5px; <?php if ($from === 'purchaseorder' || strcasecmp($status, 'Received') === 0 || strcasecmp($status, 'CANCELED') === 0): ?>background-color: #f5f5f5; cursor: not-allowed;<?php endif; ?>"
                            onblur="saveReceivingRemarks()" <?php if ($from === 'purchaseorder' || strcasecmp($status, 'Received') === 0 || strcasecmp($status, 'CANCELED') === 0): ?>readonly<?php endif; ?>><?php echo htmlspecialchars(($display_receiving_remarks ?? '')); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Workflow History Card -->
            <div class="info-card">
                <h4>Workflow History</h4>
                <script>
                    // Debug logging for workflow history
                    console.log('=== WORKFLOW HISTORY DEBUG ===');
                    console.log('completed_by:', <?php echo json_encode($completed_by); ?>);
                    console.log('completed_at:', <?php echo json_encode($completed_at); ?>);
                    console.log('completed_by_branch:', <?php echo json_encode($completed_by_branch); ?>);
                    console.log('received_by:', <?php echo json_encode($received_by); ?>);
                    console.log('received_at:', <?php echo json_encode($received_at); ?>);
                    console.log('received_by_branch:', <?php echo json_encode($received_by_branch); ?>);
                    console.log('incomplete_by:', <?php echo json_encode($incomplete_by); ?>);
                    console.log('declined_by:', <?php echo json_encode($declined_by); ?>);
                    console.log('specific_branch:', <?php echo json_encode($specific_branch); ?>);
                    console.log('specific_branch_code:', <?php echo json_encode($specific_branch_code); ?>);
                    console.log('from_param:', <?php echo json_encode($from_param); ?>);
                    console.log('================================');
                </script>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <!-- Created entry -->
                    <div class="workflow-entry">
                        <div class="wf-dot" style="background:#1a7a35;"></div>
                        <div class="wf-text">
                            <div class="wf-action">Created</div>
                            <div class="wf-by">by
                                <?php echo htmlspecialchars($created_by_fullname); ?><?php if (!empty($created_branch_name)): ?>
                                    (<?php echo htmlspecialchars($created_branch_name); ?>)<?php endif; ?>
                            </div>
                            <div class="wf-date"><?php echo $created_fmt; ?></div>
                        </div>
                    </div>

                    <?php 
                    // Only show Incomplete workflow entry if this branch has actually received items AND branch matches
                    $show_incomplete_entry = false;
                    if (!empty($incomplete_by)) {
                        if (!empty($specific_branch) && $from_param === 'purchaseorderreceive' && !empty($specific_branch_code)) {
                            // Check if the incomplete_by_branch matches the current specific branch code
                            if (!empty($incomplete_by_branch)) {
                                // Show only if branch code matches
                                if ($incomplete_by_branch === $specific_branch_code || $incomplete_by_branch === 'ALL') {
                                    // Also check if this specific branch has received any items
                                    $branch_check = $conn->query("
                                        SELECT SUM(received_qty) as total_received 
                                        FROM purchase_order_allocations 
                                        WHERE po_id = {$po_id} 
                                        AND branch_name = '" . $conn->real_escape_string($specific_branch) . "'
                                    ");
                                    if ($branch_check) {
                                        $branch_data = $branch_check->fetch_assoc();
                                        $show_incomplete_entry = ((int)($branch_data['total_received'] ?? 0) > 0);
                                    }
                                }
                            }
                        } else {
                            // Not viewing from specific branch, show if incomplete_by exists
                            $show_incomplete_entry = true;
                        }
                    }
                    
                    if ($show_incomplete_entry): 
                    ?>
                        <!-- Incomplete entry (only if it was actually incomplete) -->
                        <div class="workflow-entry">
                            <div class="wf-dot" style="background:#856404;"></div>
                            <div class="wf-text">
                                <div class="wf-action">Marked as Incomplete</div>
                                <?php if (!empty($incomplete_by)): ?>
                                    <div class="wf-by">by
                                        <?php echo htmlspecialchars($incomplete_by_fullname); ?>
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

                    <?php 
                    // Only show Completed workflow entry if branch matches (when viewing from specific branch)
                    echo "<!-- DEBUG: Starting Completed check -->";
                    $show_completed_entry = false;
                    if (!empty($completed_by)) {
                        echo "<!-- DEBUG: completed_by exists: " . htmlspecialchars($completed_by) . " -->";
                        if (!empty($specific_branch) && $from_param === 'purchaseorderreceive' && !empty($specific_branch_code)) {
                            echo "<!-- DEBUG: Viewing from specific branch: " . htmlspecialchars($specific_branch) . " (code: " . htmlspecialchars($specific_branch_code) . ") -->";
                            // Check if the completed_by_branch matches the current specific branch code
                            if (!empty($completed_by_branch)) {
                                echo "<!-- DEBUG: completed_by_branch: " . htmlspecialchars($completed_by_branch) . " -->";
                                // Show if branch code matches OR if Super Admin (000) completed it
                                $show_completed_entry = ($completed_by_branch === $specific_branch_code || $completed_by_branch === 'ALL' || $completed_by_branch === '000');
                                echo "<!-- DEBUG: Branch match result: " . ($show_completed_entry ? 'SHOW' : 'HIDE') . " -->";
                            } else {
                                echo "<!-- DEBUG: completed_by_branch is empty, showing (backward compat) -->";
                                // If no branch code is set, show it (backward compatibility)
                                $show_completed_entry = true;
                            }
                        } else {
                            echo "<!-- DEBUG: Not viewing from specific branch, showing all -->";
                            // Not viewing from specific branch, show all completed entries
                            $show_completed_entry = true;
                        }
                    } else {
                        echo "<!-- DEBUG: completed_by is empty -->";
                    }
                    echo "<!-- DEBUG: show_completed_entry = " . ($show_completed_entry ? 'TRUE' : 'FALSE') . " -->";
                    
                    if ($show_completed_entry): 
                    ?>
                        <!-- Completed entry -->
                        <div class="workflow-entry">
                            <div class="wf-dot" style="background:#0c5460;"></div>
                            <div class="wf-text">
                                <div class="wf-action">Completed</div>
                                <div class="wf-by">by
                                    <?php echo htmlspecialchars($completed_by_fullname); ?>
                                    <?php if (!empty($completed_branch_name)): ?>
                                        (<?php echo htmlspecialchars($completed_branch_name); ?>)<?php endif; ?>
                                </div>
                                <div class="wf-date"><?php echo $completed_fmt; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php 
                    // Only show Received workflow entry if branch matches (when viewing from specific branch)
                    echo "<!-- DEBUG: Starting Received check -->";
                    $show_received_entry = false;
                    $received_display_user = $received_by_fullname;
                    $received_display_date = $received_fmt;
                    $received_display_branch = $received_branch_name;
                    
                    if (!empty($specific_branch) && $from_param === 'purchaseorderreceive') {
                        echo "<!-- DEBUG: Checking branch-specific allocation data for: " . htmlspecialchars($specific_branch) . " -->";
                        // When viewing from a specific branch, check allocation-level received data
                        $allocation_received_query = $conn->query("
                            SELECT 
                                poa.received_by,
                                poa.received_at,
                                CONCAT(acc.first_name, ' ', acc.last_name) as received_by_fullname
                            FROM purchase_order_allocations poa
                            LEFT JOIN accounts acc ON poa.received_by COLLATE utf8mb4_general_ci = acc.username COLLATE utf8mb4_general_ci
                            WHERE poa.po_id = {$po_id}
                            AND poa.branch_name = '" . $conn->real_escape_string($specific_branch) . "'
                            AND poa.received_by IS NOT NULL
                            LIMIT 1
                        ");
                        
                        if ($allocation_received_query && $allocation_received_query->num_rows > 0) {
                            $allocation_received = $allocation_received_query->fetch_assoc();
                            $received_display_user = $allocation_received['received_by_fullname'] ?: $allocation_received['received_by'];
                            $received_display_date = !empty($allocation_received['received_at']) ? date('n/j/Y, g:i A', strtotime($allocation_received['received_at'])) : '';
                            $received_display_branch = $specific_branch;
                            $show_received_entry = true;
                            echo "<!-- DEBUG: Found allocation-level received data for this branch -->";
                        } else {
                            echo "<!-- DEBUG: No allocation-level received data found for this branch -->";
                        }
                    } elseif (!empty($received_by)) {
                        echo "<!-- DEBUG: Using PO-level received_by: " . htmlspecialchars($received_by) . " -->";
                        // Not viewing from specific branch, use PO-level received data
                        $show_received_entry = true;
                        echo "<!-- DEBUG: Not specific branch view, showing PO-level data -->";
                    } else {
                        echo "<!-- DEBUG: No received data found -->";
                    }
                    
                    echo "<!-- DEBUG: show_received_entry = " . ($show_received_entry ? 'TRUE' : 'FALSE') . " -->";
                    
                    if ($show_received_entry): 
                    ?>
                        <!-- Received entry -->
                        <div class="workflow-entry">
                            <div class="wf-dot" style="background:#1a7a35;"></div>
                            <div class="wf-text">
                                <div class="wf-action">Received</div>
                                <div class="wf-by">by
                                    <?php echo htmlspecialchars($received_display_user); ?>
                                    <?php if (!empty($received_display_branch)): ?>
                                        (<?php echo htmlspecialchars($received_display_branch); ?>)<?php endif; ?>
                                </div>
                                <div class="wf-date"><?php echo $received_display_date; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php 
                    // Only show Declined workflow entry if branch matches (when viewing from specific branch)
                    $show_declined_entry = false;
                    if (!empty($declined_by)) {
                        if (!empty($specific_branch) && $from_param === 'purchaseorderreceive' && !empty($specific_branch_code)) {
                            // Check if the declined_by_branch matches the current specific branch code
                            if (!empty($declined_by_branch)) {
                                // Show if branch code matches OR if Super Admin (000) declined it
                                $show_declined_entry = ($declined_by_branch === $specific_branch_code || $declined_by_branch === 'ALL' || $declined_by_branch === '000');
                            } else {
                                // If no branch code is set, show it (backward compatibility)
                                $show_declined_entry = true;
                            }
                        } else {
                            // Not viewing from specific branch, show all declined entries
                            $show_declined_entry = true;
                        }
                    }
                    
                    if ($show_declined_entry): 
                    ?>
                        <!-- Canceled entry -->
                        <div class="workflow-entry">
                            <div class="wf-dot" style="background:#c62828;"></div>
                            <div class="wf-text">
                                <div class="wf-action">Canceled</div>
                                <div class="wf-by">by
                                    <?php echo htmlspecialchars($declined_by_fullname); ?>
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
                                <?php if ($from !== 'purchaseorder' && !(strcasecmp($status, 'CANCELED') === 0 && $from === 'purchaseorderreceive')): ?>
                                    <th style="text-align: center;">Edit Item</th>
                                <?php endif; ?>
                                <th>Item Description</th>
                                <th>IMEI</th>
                                <?php if ($from !== 'purchaseorder' && !(strcasecmp($status, 'CANCELED') === 0 && $from === 'purchaseorderreceive')): ?>
                                    <th style="text-align: center;">Edit IMEI</th>
                                    <th style="text-align: center; display: none;">Edit Item Status</th>
                                <?php endif; ?>
                                <th>Quantity</th>
                                <th>Cost</th>
                                <th>Total</th>
                                <?php if ($from !== 'purchaseorder' && !(strcasecmp($status, 'CANCELED') === 0 && $from === 'purchaseorderreceive')): ?>
                                    <th style="text-align: center;">Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr data-item-id="<?php echo (int) ($item['id'] ?? 0); ?>"
                                    data-allocation-id="<?php echo (int) ($item['allocation_id'] ?? 0); ?>"
                                    data-family-code="<?php echo htmlspecialchars($item['family_code'] ?? ''); ?>"
                                    data-item-no="<?php echo (int) ($item['item_no'] ?? 0); ?>"
                                    data-item-model="<?php echo htmlspecialchars($item['item_model'] ?? ''); ?>">
                                    <td><?php echo (int) ($item['item_no'] ?? 0); ?></td>
                                    <td><strong><?php echo htmlspecialchars(strtoupper(($item['family_code'] !== null && $item['family_code'] !== '') ? $item['family_code'] : '-')); ?></strong>
                                    </td>
                                    <td class="item-model-cell"
                                        data-family-code="<?php echo htmlspecialchars($item['family_code'] ?? ''); ?>"
                                        data-item-no="<?php echo (int) ($item['item_no'] ?? 0); ?>"
                                        data-item-model="<?php echo htmlspecialchars($item['item_model'] ?? ''); ?>"
                                        data-item-description="<?php echo htmlspecialchars($item['item_description'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($item['item_model'] ?? '-'); ?>
                                    </td>
                                    <?php if ($from !== 'purchaseorder' && !(strcasecmp($status, 'CANCELED') === 0 && $from === 'purchaseorderreceive')): ?>
                                        <td class="item-model-action-cell">
                                            <?php if (strcasecmp($status, 'Received') !== 0 && !empty($item['item_model']) && $item['item_model'] !== '-'): ?>
                                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                                    <button class="btn-edit-item-model"
                                                        onclick="editItemModel('<?php echo htmlspecialchars($item['family_code'] ?? '', ENT_QUOTES); ?>', <?php echo (int) ($item['item_no'] ?? 0); ?>, '<?php echo htmlspecialchars($item['item_model'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['item_description'] ?? '', ENT_QUOTES); ?>', <?php echo (int) ($item['allocation_id'] ?? 0); ?>, <?php echo (int) ($item['id'] ?? 0); ?>)"
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
                                    <?php endif; ?>
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
                                        data-department="<?php echo htmlspecialchars($item['department'] ?? ''); ?>"
                                        data-quantity="<?php echo (int) $item['quantity']; ?>"
                                        data-has-serial="<?php echo $item['has_serial']; ?>"
                                        data-has-serial-2="<?php echo (int)($item['has_serial_2'] ?? 0); ?>"
                                        data-has-serial-number="<?php echo (int)($item['has_serial_number'] ?? 0); ?>"
                                        data-received-qty="<?php echo (int) $item['calculated_received_qty']; ?>">
                                        <?php
                                        // Display serial numbers if they exist, regardless of has_serial flag
                                        if (!empty($item['serial_number'])) {
                                            $raw_s1 = $item['serial_number'] ?? '';
                                            $raw_s2 = $item['imei_2'] ?? '';

                                            // Split by comma or newline
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
                                            echo '<span style="color: #999; font-size: 14px;">-</span>';
                                        } else {
                                            // Item doesn't require serial number
                                            echo '<span style="color: #999; font-size: 14px;">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                    <?php if ($from !== 'purchaseorder' && !(strcasecmp($status, 'CANCELED') === 0 && $from === 'purchaseorderreceive')): ?>
                                        <td class="action-cell serial-action-cell"
                                            data-family-code="<?php echo htmlspecialchars($item['family_code']); ?>"
                                            data-item-no="<?php echo (int) $item['item_no']; ?>"
                                            data-item-description="<?php echo htmlspecialchars($item['item_description'] ?? ''); ?>"
                                            data-department="<?php echo htmlspecialchars($item['department'] ?? ''); ?>"
                                            data-quantity="<?php echo (int) $item['quantity']; ?>"
                                            data-has-serial="<?php echo $item['has_serial']; ?>"
                                            data-has-serial-2="<?php echo (int)($item['has_serial_2'] ?? 0); ?>"
                                            data-has-serial-number="<?php echo (int)($item['has_serial_number'] ?? 0); ?>">
                                            <!-- Edit Serial Button will be added by JavaScript if serial numbers exist -->
                                            <span style="color: #999; font-size: 12px;">-</span>
                                        </td>
                                        <td class="action-cell item-type-status-action-cell"
                                            data-family-code="<?php echo htmlspecialchars($item['family_code']); ?>"
                                            data-item-no="<?php echo (int) $item['item_no']; ?>"
                                            data-item-model="<?php echo htmlspecialchars($item['item_model'] ?? ''); ?>"
                                            data-department="<?php echo htmlspecialchars($item['department'] ?? ''); ?>"
                                            data-has-serial="<?php echo $item['has_serial']; ?>"
                                            data-has-serial-2="<?php echo (int)($item['has_serial_2'] ?? 0); ?>"
                                            data-has-serial-number="<?php echo (int)($item['has_serial_number'] ?? 0); ?>"
                                            style="display: none;">
                                            <!-- Edit Item Type/Status Button will be added by JavaScript if serial numbers exist -->
                                            <span style="color: #999; font-size: 12px;">-</span>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php
                                        $total_quantity = (int) $item['quantity'];
                                        $received_count = (int) $item['calculated_received_qty'];

                                        // Display in format: received/total
                                        echo $received_count . '/' . $total_quantity;
                                        ?>
                                    </td>
                                    <td><strong>&#8369; <?php echo number_format((float) $item['cost'], 2); ?></strong></td>
                                    <td><strong>&#8369; <?php echo number_format((float) $item['total'], 2); ?></strong></td>
                                    <?php if ($from !== 'purchaseorder' && !(strcasecmp($status, 'CANCELED') === 0 && $from === 'purchaseorderreceive')): ?>
                                        <td class="delete-action-cell">
                                            <div style="display: flex; justify-content: center; align-items: center;">
                                                <button class="btn-delete-item" <?php if (strcasecmp($status, 'Received') === 0): ?>
                                                        disabled style="opacity: 0.4; cursor: not-allowed;" <?php endif; ?>
                                                    onclick="deleteItemRow(<?php echo (int) $item['id']; ?>, '<?php echo htmlspecialchars($item['family_code']); ?>', <?php echo (int) $item['item_no']; ?>)"
                                                    title="<?php echo strcasecmp($status, 'Received') === 0 ? 'Cannot delete items from received PO' : 'Delete Item'; ?>">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                        <path
                                                            d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            <!-- Total Quantity and Grand Total Row -->
                            <tr class="grand-total-row">
                                <?php if ($from !== 'purchaseorder' && !(strcasecmp($status, 'CANCELED') === 0 && $from === 'purchaseorderreceive')): ?>
                                    <td colspan="7" style="border-left:1px solid #ccc;"></td>
                                <?php elseif ($from !== 'purchaseorder' || (strcasecmp($status, 'CANCELED') === 0 && $from === 'purchaseorderreceive')): ?>
                                    <td colspan="4" style="border-left:1px solid #ccc;"></td>
                                <?php else: ?>
                                    <td colspan="4" style="border-left:1px solid #ccc;"></td>
                                <?php endif; ?>
                                <td class="gt-label">TOTAL QUANTITY</td>
                                <td class="gt-amount">
                                    <strong><?php echo $total_received_qty_display; ?>/<?php echo $total_ordered_qty; ?></strong>
                                </td>
                                <td class="gt-label">GRAND TOTAL</td>
                                <td class="gt-amount"><strong>&#8369; <?php echo number_format($grand_total, 2); ?></strong>
                                </td>
                                <?php if ($from !== 'purchaseorder' && !(strcasecmp($status, 'CANCELED') === 0 && $from === 'purchaseorderreceive')): ?>
                                    <td style="border-right:1px solid #ccc;"></td>
                                <?php endif; ?>

                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Add Item Button (only show when from purchaseorderreceive and not canceled or completed) -->
                <?php if ($from_param === 'purchaseorderreceive' && !(strcasecmp($status, 'CANCELED') === 0 || strcasecmp($status, 'CANCELLED') === 0 || strcasecmp($status, 'Received') === 0 || strcasecmp($status, 'Completed') === 0 || strcasecmp($status, 'Closed') === 0)): ?>
                    <div id="addItemButtonContainer" style="margin-top: 15px; text-align: left; display: none;">
                        <button class="btn-add-po-item" onclick="addNewItemToPO()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 5px;">
                                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                            </svg>
                            Add New Item
                        </button>
                    </div>
                <?php endif; ?>
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
                <div class="serial-inputs-container" id="serialInputsContainer" style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <div class="serial-input-section" style="flex: 1;">
                        <label id="serial1Label">IMEI <span style="float: right; font-weight: 700; color: #1a7a35;"><span
                                    id="serialCountCurrent">0</span>/<span id="serialCountTotal">0</span></span></label>
                        <input type="text" id="serialNumberInput" placeholder="Enter IMEI">
                    </div>
                    <div class="serial-input-section" id="serial2InputSection" style="flex: 1; display: none;">
                        <label id="serial2Label">IMEI 2 <span style="float: right; font-weight: 700; color: #1a7a35;"><span
                                    id="serial2CountCurrent">0</span>/<span id="serial2CountTotal">0</span></span></label>
                        <input type="text" id="serial2NumberInput" placeholder="Enter IMEI 2">
                    </div>
                </div>

                <div class="serial-tables-container" id="serialTablesContainer" style="width: 100%; max-height: 280px; overflow-y: auto;">
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

    <!-- Search Item Modal -->
    <div id="searchItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Search Item
            </div>
            <div class="modal-body">
                <table class="search-results-table">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Item Model</th>
                            <th style="width: 50%;">Item Description</th>
                            <th style="width: 15%; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="searchResultsBody">
                        <!-- Results will be injected here -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-back-modal" onclick="closeSearchItemModal()">Back</button>
            </div>
        </div>
    </div>

    <!-- ═══════════════ PRINT AREA (hidden on screen, shown on print) ═══════════════ -->
    <div class="print-area" id="printArea">

        <!-- Header with Logo and Title -->
        <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:20px;">
            <div>
                <img src="Icon/ZUHAUSE-LOGO.png" alt="ZUHAUSE LOGO"
                    style="height:50px; width:auto; border-radius: 5px;">
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
                                style="margin-left:20px;"><?php echo htmlspecialchars($po['supplier_company'] ?: ''); ?></span>
                        </div>
                        <div style="margin-bottom:8px;">
                            <span style="font-weight:bold;">PO NUMBER:</span>
                            <span style="margin-left:20px;"><?php echo htmlspecialchars($po['po_number']); ?></span>
                        </div>
                        <div style="margin-bottom:8px;">
                            <span style="font-weight:bold;">SUPPLIER COMPANY NAME:</span>
                            <span
                                style="margin-left:20px;"><?php echo htmlspecialchars($po['supplier_company'] ?: ''); ?></span>
                        </div>
                    </td>
                    <td style="width:50%; vertical-align:top; text-align:right;">
                        <div style="margin-bottom:8px;">
                            <span style="font-weight:bold;">INVOICE NUMBER:</span>
                            <span
                                style="margin-left:20px;"><?php echo htmlspecialchars($display_invoice_number); ?></span>
                        </div>
                        <div style="margin-bottom:8px;">
                            <span style="font-weight:bold;">RECEIVE DATE:</span>
                            <span style="margin-left:20px;"><?php echo $received_fmt ?: ''; ?></span>
                        </div>
                        <div style="margin-bottom:8px;">
                            <span style="font-weight:bold;">PO DATE:</span>
                            <span style="margin-left:20px;"><?php echo $po_date_fmt; ?></span>
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
                                } elseif ($item['has_serial'] == 1) {
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

                                // Count serial numbers if serialized, else use received_qty / total_quantity
                                if ($item['has_serial'] == 1) {
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
                                    $received_count = (isset($item['received_qty']) && (int)$item['received_qty'] > 0) ? (int)$item['received_qty'] : $total_quantity;
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

    <!-- Cancel Reason Modal -->
    <div class="modal-overlay" id="cancelReasonModal">
        <div class="modal-box" style="max-width: 600px;">
            <div class="modal-icon" style="font-size: 48px;">❌</div>
            <div class="modal-title">Cancel Purchase Order</div>
            <div class="modal-msg" style="text-align: left; margin-bottom: 15px;">
                Please provide a reason for canceling this purchase order:
            </div>
            <textarea id="cancelReasonTextarea" 
                placeholder="Enter cancellation reason..." 
                style="width: 100%; min-height: 120px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; font-family: Arial, sans-serif; resize: vertical; margin-bottom: 20px;"
                maxlength="500"></textarea>
            <div style="text-align: right; font-size: 12px; color: #999; margin-top: -15px; margin-bottom: 15px;">
                <span id="cancelReasonCharCount">0</span>/500 characters
            </div>
            <div class="modal-actions">
                <button class="modal-btn modal-btn-cancel" onclick="closeCancelReasonModal()">Back</button>
                <button class="modal-btn modal-btn-confirm-red" onclick="confirmCancelWithReason()">Yes, Cancel PO</button>
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
        let _currentItemModel = '';
        let _currentItemDescription = '';
        let _currentItemQuantity = 0;
        let _currentSerialNumbers = [];
        let _currentSerialNumbers2 = [];
        let _serialRowCount = 0;
        let _allSerialNumbers = {}; // Store serial numbers for display (includes existing + new)
        let _existingSerialNumbers = {}; // Store existing serial numbers from database (for validation)
        let _allSerialNumbers2 = {}; // Store IMEI 2 numbers for display
        let _existingSerialNumbers2 = {}; // Store existing IMEI 2 numbers from database
        let _allItemHasSerial2 = {}; // Store has_serial_2 per rowKey
        let _allItemHasSerialNumber = {}; // Store has_serial_number (TABLET) per rowKey
        let _allItemDepartment = {}; // Store department per rowKey
        let _allItemModels = {}; // Store item models for each unique row (family_code + item_no)
        let _allItemDescriptions = {}; // Store item descriptions for each unique row
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
            const savedSerials2 = sessionStorage.getItem('po_serial_numbers_2');
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
                    });
                    sessionStorage.removeItem('po_serial_numbers');
                } catch (e) {
                    console.error('Failed to restore serial numbers:', e);
                }
            }

            if (savedSerials2) {
                try {
                    const serialData2 = JSON.parse(savedSerials2);
                    Object.keys(serialData2).forEach(rowKey => {
                        _allSerialNumbers2[rowKey] = serialData2[rowKey];
                    });
                    sessionStorage.removeItem('po_serial_numbers_2');
                } catch (e) {
                    console.error('Failed to restore serial numbers 2:', e);
                }
            }
            
            if (savedReceivingMode === 'true') {
                showSerialButtons();
                changeReceiveButtonToSave();
                
                // Also show the Add New Item button
                const addItemButtonContainer = document.getElementById('addItemButtonContainer');
                if (addItemButtonContainer) {
                    addItemButtonContainer.style.display = 'block';
                }
                
                sessionStorage.removeItem('po_receiving_mode');
            }
            
            <?php foreach ($items as $item): ?>
                <?php 
                $row_key = $item['family_code'] . '-' . $item['item_no']; 
                ?>
                _allItemHasSerial2['<?php echo addslashes($row_key); ?>'] = <?php echo (int)($item['has_serial_2'] ?? 0); ?>;
                _allItemHasSerialNumber['<?php echo addslashes($row_key); ?>'] = <?php echo (int)($item['has_serial_number'] ?? 0); ?>;
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
        });

        function updateStatus(newStatus) {
            _pendingStatus = newStatus;
            const isReceive = newStatus === 'Received';
            const isCancel = (newStatus === 'CANCELED' || newStatus === 'Cancelled');

            if (isReceive) {
                // Check if invoice number is filled
                const invoiceInput = document.getElementById('invoiceNumberInput');
                const invoiceNumber = invoiceInput ? invoiceInput.value.trim() : '';

                if (!invoiceNumber) {
                    alert('Invoice Number is required before receiving the purchase order.');
                    invoiceInput.focus();
                    invoiceInput.style.borderColor = '#c62828';
                    setTimeout(() => {
                        invoiceInput.style.borderColor = '#ddd';
                    }, 2000);
                    return;
                }

                // For receiving, always show item model buttons first (both serialized and non-serialized)
                // Change Receive to Save button first, then show serial number buttons
                changeReceiveButtonToSave();
                showSerialButtons();
                
                // Show the "Add New Item" button when Receive is clicked
                const addItemButtonContainer = document.getElementById('addItemButtonContainer');
                if (addItemButtonContainer) {
                    addItemButtonContainer.style.display = 'block';
                }
                
                return;
            }

            // For cancel, show cancel reason modal
            if (isCancel) {
                showCancelReasonModal();
                return;
            }

            // Normal flow for other status changes
            showConfirmModal(isReceive);
        }

        // Check if we need to show serial buttons on page load for Incomplete status
        <?php if (strcasecmp($status, 'Incomplete') === 0): ?>
            document.addEventListener('DOMContentLoaded', function () {
                showSerialButtons();
                // Don't change button to "Save" if coming from purchaseorderreceive
                // Let user click "Receive" first to add/edit items
                <?php if ($from_param !== 'purchaseorderreceive'): ?>
                    changeReceiveButtonToSave();
                <?php endif; ?>
                
                // Show the "Add New Item" button for Incomplete status
                const addItemButtonContainer = document.getElementById('addItemButtonContainer');
                if (addItemButtonContainer) {
                    addItemButtonContainer.style.display = 'block';
                }
            });
        <?php endif; ?>

        // Check if receiving_mode parameter is set in URL (after saving item models)
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);

            // Restore invoice number from URL parameter if present
            const invoiceNumberParam = urlParams.get('invoice_number');
            if (invoiceNumberParam) {
                const invoiceInput = document.getElementById('invoiceNumberInput');
                if (invoiceInput) {
                    invoiceInput.value = invoiceNumberParam;
                }

                // Clean up URL parameter
                const cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('invoice_number');
                window.history.replaceState({}, '', cleanUrl.toString());
            }

            if (urlParams.get('receiving_mode') === '1') {
                // Auto-activate receiving mode
                <?php if (strcasecmp($status, 'Pending') === 0): ?>
                    showSerialButtons();
                    changeReceiveButtonToSave();
                    
                    // Show the "Add New Item" button when receiving_mode is activated
                    const addItemButtonContainer = document.getElementById('addItemButtonContainer');
                    if (addItemButtonContainer) {
                        addItemButtonContainer.style.display = 'block';
                    }

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

            // Check if we're in receiving mode (user clicked Receive button)
            // If in receiving mode, show buttons even for INCOMPLETE status
            const inReceivingMode = _isReceivingMode;

            // Find all serial cells for serialized items and show Add Serial Number buttons
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

                    // Check if item model exists (not empty and not '-')
                    const hasItemModel = itemModel && itemModel !== '-' && itemModel.trim() !== '';

                    if (isIncomplete && !inReceivingMode) {
                        // For INCOMPLETE status NOT in receiving mode, hide buttons and show N/A if no serial numbers
                        if (existingCount === 0) {
                            cell.innerHTML = 'N/A';
                        } else {
                            // Show existing serial numbers without button
                            const serials2 = _allSerialNumbers2[rowKey] || [];
                            cell.innerHTML = formatSerialDisplayHtml(rowKey, existingSerials, serials2);
                        }
                    } else {
                        // Only show Add Serial Number button if item model exists
                        if (!hasItemModel) {
                            // No item model yet - hide the button, show placeholder
                            cell.innerHTML = '<span style="color: #999; font-size: 12px;">-</span>';
                        } else if (existingCount === 0) {
                            // Item model exists and no serials entered yet - show Add button
                            const config = getSerialConfig(rowKey);
                            const btnText = 'Add ' + config.modalTitleBase;
                            cell.innerHTML = `<button class="btn-add-serial-number" onclick="openSerialModal('${rowKey}', '${itemDescription}', ${quantity})">${escapeHtml(btnText)}</button>`;
                        } else if (existingCount < totalQuantity) {
                            // Item model exists and some serials entered - show existing serials + Add More button
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

                if ((isIncomplete && !inReceivingMode) || isCompleted) {
                    // For INCOMPLETE (not in receiving mode) or COMPLETED status, hide the Add Item Model button
                    // Only show the item model text if it exists
                    const displayText = _allItemModels[rowKey] || currentItemModel;
                    if (displayText && displayText !== '-') {
                        cell.innerHTML = `<span>${displayText}</span>`;
                    } else {
                        cell.innerHTML = '-';
                    }
                } else {
                    // Original behavior for other statuses or INCOMPLETE in receiving mode
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
                    // Check if invoice number is filled
                    const invoiceInput = document.getElementById('invoiceNumberInput');
                    const invoiceNumber = invoiceInput ? invoiceInput.value.trim() : '';

                    if (!invoiceNumber) {
                        alert('Invoice Number is required before saving the purchase order.');
                        invoiceInput.focus();
                        invoiceInput.style.borderColor = '#c62828';
                        setTimeout(() => {
                            invoiceInput.style.borderColor = '#ddd';
                        }, 2000);
                        return;
                    }

                    // Check if current status is Incomplete and validate edit requirement
                    const currentStatus = '<?php echo $status; ?>';
                    if (currentStatus.toLowerCase() === 'incomplete') {
                        // Check if PO has been edited since becoming incomplete
                        checkEditRequirement();
                        return;
                    }

                    _pendingStatus = 'Received'; // Make sure this is set
                    const isIncomplete = checkIsPOIncomplete();
                    showConfirmModal(true, true, isIncomplete);
                };
            }
        }

        function checkEditRequirement() {
            // Check if we're coming from purchaseorderreceive.php
            const urlParams = new URLSearchParams(window.location.search);
            const fromPage = urlParams.get('from');

            // If from purchaseorderreceive, skip all validation and proceed directly
            if (fromPage === 'purchaseorderreceive') {
                console.log('Coming from purchaseorderreceive - SKIPPING all validation, proceeding directly to completion');
                proceedWithCompletion();
                return;
            }

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
            _isReceivingMode = true;
            _pendingStatus = 'Received';

            const isIncomplete = checkIsPOIncomplete();
            showConfirmModal(true, true, isIncomplete);
        }

        function getSerialConfig(rowKey) {
            rowKey = rowKey || _currentItemModel;
            let isDualImei = _allItemHasSerial2[rowKey] == 1;
            let isTabletSerial = _allItemHasSerialNumber[rowKey] == 1;
            let department = (_allItemDepartment[rowKey] || '').toUpperCase();

            // Fallback to DOM attributes if not in memory
            if (rowKey && !_allItemDepartment[rowKey]) {
                const [familyCode, itemNo] = rowKey.split('-');
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

            return {
                hasCol2,
                isDualImei,
                isTabletSerial,
                department,
                col1Label,
                col2Label,
                modalTitleBase
            };
        }

        function hasSerial2Active() {
            return getSerialConfig().hasCol2;
        }

        function formatSerialDisplayHtml(rowKey, serialNumbers, serials2) {
            const s1 = Array.isArray(serialNumbers) ? serialNumbers : [];
            const s2 = Array.isArray(serials2) ? serials2 : [];
            const totalPairs = Math.max(s1.length, s2.length);
            if (totalPairs === 0) return '';

            const config = (typeof getSerialConfig === 'function') ? getSerialConfig(rowKey) : { col2Label: 'IMEI 2' };
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
            _isModalEditMode = isEditMode; // Set whether modal is in Edit mode
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
                // Set maxlength to 15 for MOBILE department
                if (config.department === 'MOBILE') {
                    sInput.setAttribute('maxlength', '15');
                } else {
                    sInput.removeAttribute('maxlength');
                }
            }
            const s2Input = document.getElementById('serial2NumberInput');
            if (s2Input) {
                s2Input.value = '';
                s2Input.placeholder = 'Enter ' + config.col2Label;
                // Set maxlength to 15 for MOBILE department
                if (config.department === 'MOBILE') {
                    s2Input.setAttribute('maxlength', '15');
                } else {
                    s2Input.removeAttribute('maxlength');
                }
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
            if (header1) {
                header1.textContent = config.col1Label;
            }
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
            _isModalEditMode = false; // Reset edit mode flag
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

            // Check for exactly 15 characters (letters and digits) if department is MOBILE
            if (config.department === 'MOBILE') {
                if (serialNumber.length !== 15) {
                    alert(config.col1Label + ' must be exactly 15 characters for MOBILE department.');
                    return;
                }
            }

            // Check for duplicates within current item locally
            if (_currentSerialNumbers.includes(serialNumber)) {
                alert('This ' + config.col1Label + ' is already added for this item.');
                return;
            }

            // Check for duplicates across all items in current modal context locally
            for (let itemModel in _allSerialNumbers) {
                if (_allSerialNumbers[itemModel].includes(serialNumber)) {
                    alert('This ' + config.col1Label + ' is already used for another item.');
                    return;
                }
            }

            // Check quantity limit
            if (_currentSerialNumbers.length >= _currentItemQuantity) {
                alert(`Maximum ${_currentItemQuantity} ${config.col1Label} entries allowed for this item.`);
                return;
            }

            // Check against database if serial is globally unique
            const formData = new FormData();
            formData.append('po_id', <?php echo (int) $po_id; ?>);
            formData.append('serial_number', serialNumber);

            fetch('check_duplicate_serial.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(result => {
                    if (result.success && result.is_duplicate) {
                        alert('Error: ' + result.message);
                    } else if (!result.success) {
                        alert('Error: ' + result.message);
                    } else {
                        // Add to current list
                        _currentSerialNumbers.push(serialNumber);
                        input.value = '';
                        _shouldAutoSave = true; // Enable auto-save since user added a new serial
                        populateSerialList();

                        if (hasSerial2Active() && _currentSerialNumbers2.length < _currentItemQuantity) {
                            const s2 = document.getElementById('serial2NumberInput');
                            if (s2) s2.focus();
                        }
                    }
                })
                .catch(err => {
                    alert('Error checking ' + config.col1Label + ': ' + err.message);
                });
        }

        function removeSerialRow(index) {
            if (_currentSerialNumbers[index] !== undefined) {
                _currentSerialNumbers.splice(index, 1);
            }
            if (_currentSerialNumbers2[index] !== undefined) {
                _currentSerialNumbers2.splice(index, 1);
            }
            _shouldAutoSave = false; // Don't auto-save when removing
            populateSerialList();
        }

        function removeSerialFromList(index) {
            removeSerialRow(index);
        }

        function removeSerial2FromList(index) {
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
            if (s1Header) {
                s1Header.textContent = config.col1Label;
            }

            if (isDual) {
                const totalRows = Math.max(_currentSerialNumbers.length, _currentSerialNumbers2.length);
                for (let index = 0; index < totalRows; index++) {
                    const val1 = _currentSerialNumbers[index] !== undefined ? _currentSerialNumbers[index] : '';
                    const val2 = _currentSerialNumbers2[index] !== undefined ? _currentSerialNumbers2[index] : '';

                    const row = document.createElement('tr');
                    if (_isModalEditMode) {
                        // EDIT MODE: Editable input fields inside table cells
                        const maxlengthAttr = config.department === 'MOBILE' ? 'maxlength="15"' : '';
                        row.innerHTML = `
                            <td style="text-align: center; vertical-align: middle; font-weight: 600;">${index + 1}</td>
                            <td>
                                <input type="text" class="serial-table-input" id="tableImei1_${index}"
                                       value="${escapeHtml(val1)}" placeholder="Enter ${escapeHtml(config.col1Label)}"
                                       ${maxlengthAttr}
                                       oninput="updateSerialAtIndex(${index}, 'imei1', this.value)">
                            </td>
                            <td>
                                <input type="text" class="serial-table-input" id="tableImei2_${index}"
                                       value="${escapeHtml(val2)}" placeholder="Enter ${escapeHtml(config.col2Label)}"
                                       ${maxlengthAttr}
                                       oninput="updateSerialAtIndex(${index}, 'imei2', this.value)">
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button class="btn-remove-from-list" onclick="removeSerialRow(${index})">Remove</button>
                            </td>
                        `;
                    } else {
                        // ADD MODE: Plain text display
                        const imei1Display = val1 ? escapeHtml(val1) : '<span style="color: #999; font-style: italic;">Pending...</span>';
                        const imei2Display = val2 ? escapeHtml(val2) : '<span style="color: #999; font-style: italic;">Pending...</span>';
                        row.innerHTML = `
                            <td style="text-align: center; vertical-align: middle; font-weight: 600;">${index + 1}</td>
                            <td style="text-align: center; vertical-align: middle;">${imei1Display}</td>
                            <td style="text-align: center; vertical-align: middle;">${imei2Display}</td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button class="btn-remove-from-list" onclick="removeSerialRow(${index})">Remove</button>
                            </td>
                        `;
                    }
                    tbody.appendChild(row);
                }
            } else {
                _currentSerialNumbers.forEach((serial, index) => {
                    const row = document.createElement('tr');
                    if (_isModalEditMode) {
                        // EDIT MODE: Editable input field inside table cell
                        const maxlengthAttr = config.department === 'MOBILE' ? 'maxlength="15"' : '';
                        row.innerHTML = `
                            <td style="text-align: center; vertical-align: middle; font-weight: 600;">${index + 1}</td>
                            <td>
                                <input type="text" class="serial-table-input" id="tableImei1_${index}"
                                       value="${escapeHtml(serial || '')}" placeholder="Enter ${escapeHtml(config.col1Label)}"
                                       ${maxlengthAttr}
                                       oninput="updateSerialAtIndex(${index}, 'imei1', this.value)">
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button class="btn-remove-from-list" onclick="removeSerialRow(${index})">Remove</button>
                            </td>
                        `;
                    } else {
                        // ADD MODE: Plain text display
                        row.innerHTML = `
                            <td style="text-align: center; vertical-align: middle; font-weight: 600;">${index + 1}</td>
                            <td style="text-align: center; vertical-align: middle;">${escapeHtml(serial || '')}</td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button class="btn-remove-from-list" onclick="removeSerialRow(${index})">Remove</button>
                            </td>
                        `;
                    }
                    tbody.appendChild(row);
                });
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

        function addSerial2ToList() {
            const input = document.getElementById('serial2NumberInput');
            const serialNumber = input.value.trim();
            const config = getSerialConfig();

            if (!serialNumber) {
                alert('Please enter ' + config.col2Label + '.');
                return;
            }

            // Check for exactly 15 characters (letters and digits) if department is MOBILE
            if (config.department === 'MOBILE') {
                if (serialNumber.length !== 15) {
                    alert(config.col2Label + ' must be exactly 15 characters for MOBILE department.');
                    return;
                }
            }

            // Check for duplicates within current item list
            if (_currentSerialNumbers2.includes(serialNumber)) {
                alert('This ' + config.col2Label + ' is already added for this item.');
                return;
            }

            // Check for duplicates across all items in current modal context locally
            for (let itemModel in _allSerialNumbers2) {
                if (_allSerialNumbers2[itemModel].includes(serialNumber)) {
                    alert('This ' + config.col2Label + ' is already used for another item.');
                    return;
                }
            }

            // Check quantity limit
            if (_currentSerialNumbers2.length >= _currentItemQuantity) {
                alert(`Maximum ${_currentItemQuantity} ${config.col2Label} entries allowed for this item.`);
                return;
            }

            // Check against database if serial is globally unique
            const formData = new FormData();
            formData.append('po_id', <?php echo (int) $po_id; ?>);
            formData.append('serial_number', serialNumber);

            fetch('check_duplicate_serial.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(result => {
                    if (result.success && result.is_duplicate) {
                        alert('Error: ' + result.message);
                    } else if (!result.success) {
                        alert('Error: ' + result.message);
                    } else {
                        // Add to current list
                        _currentSerialNumbers2.push(serialNumber);
                        input.value = '';
                        _shouldAutoSave = true;
                        populateSerialList();

                        if (_currentSerialNumbers.length < _currentItemQuantity) {
                            const s1 = document.getElementById('serialNumberInput');
                            if (s1) s1.focus();
                        }
                    }
                })
                .catch(err => {
                    alert('Error checking ' + config.col2Label + ': ' + err.message);
                });
        }

        function updateSerialQuantityCounter() {
            const current = _currentSerialNumbers.filter(s => s && s.trim() !== '').length;
            const total = _currentItemQuantity;

            const currentElem = document.getElementById('serialCountCurrent');
            const totalElem = document.getElementById('serialCountTotal');
            if (currentElem) currentElem.textContent = current;
            if (totalElem) totalElem.textContent = total;

            // Change color based on status
            if (currentElem && currentElem.parentElement) {
                const parentSpan = currentElem.parentElement;
                if (current === total) {
                    parentSpan.style.color = '#1a7a35'; // Green when complete
                } else if (current > total) {
                    parentSpan.style.color = '#c62828'; // Red when exceeded
                } else {
                    parentSpan.style.color = '#856404'; // Orange when incomplete
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

            const isComplete = isDual 
                ? (current === total && _currentSerialNumbers2.filter(s => s && s.trim() !== '').length === total)
                : (current === total);

            if (isComplete && _shouldAutoSave) {
                setTimeout(() => {
                    saveSerialNumbers();
                }, 100);
            }
        }

        function saveSerialNumbers() {
            // Clean/trim and filter entries
            _currentSerialNumbers = _currentSerialNumbers.map(s => (s || '').trim()).filter(s => s !== '');
            if (hasSerial2Active()) {
                _currentSerialNumbers2 = _currentSerialNumbers2.map(s => (s || '').trim()).filter(s => s !== '');
            }

            const config = getSerialConfig();

            // Validate MOBILE department: all IMEIs must be exactly 15 characters
            if (config.department === 'MOBILE') {
                for (let i = 0; i < _currentSerialNumbers.length; i++) {
                    if (_currentSerialNumbers[i].length !== 15) {
                        alert(`${config.col1Label} at row ${i + 1} must be exactly 15 characters for MOBILE department.`);
                        return;
                    }
                }
                if (hasSerial2Active()) {
                    for (let i = 0; i < _currentSerialNumbers2.length; i++) {
                        if (_currentSerialNumbers2[i] && _currentSerialNumbers2[i].length !== 15) {
                            alert(`${config.col2Label} at row ${i + 1} must be exactly 15 characters for MOBILE department.`);
                            return;
                        }
                    }
                }
            }

            // Check duplicates in list 1
            const duplicates1 = _currentSerialNumbers.filter((item, index) => _currentSerialNumbers.indexOf(item) !== index);
            if (duplicates1.length > 0) {
                alert('Duplicate IMEI found in table: ' + duplicates1[0]);
                return;
            }

            // Check duplicates in list 2
            if (hasSerial2Active()) {
                const duplicates2 = _currentSerialNumbers2.filter((item, index) => _currentSerialNumbers2.indexOf(item) !== index);
                if (duplicates2.length > 0) {
                    alert('Duplicate IMEI 2 found in table: ' + duplicates2[0]);
                    return;
                }
            }

            // Save current serial numbers to global storage
            _allSerialNumbers[_currentItemModel] = [..._currentSerialNumbers];
            if (hasSerial2Active()) {
                _allSerialNumbers2[_currentItemModel] = [..._currentSerialNumbers2];
            }

            // Update the button in the table
            updateSerialButtonDisplay(_currentItemModel);

            // Update the Edit Serial button visibility
            updateSerialActionButton(_currentItemModel);

            // Update the quantity display (received/total format)
            updateQuantityDisplay(_currentItemModel);

            // Update the grand total quantity
            updateGrandTotalQuantity();

            // Close modal
            closeSerialModal();
        }

        function updateSerialActionButton(rowKey) {
            const currentStatus = '<?php echo $status; ?>';
            const isReceived = currentStatus.toLowerCase() === 'received';

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

                    // Show Edit button if:
                    // 1. Item has serial requirement AND serial numbers exist AND (status is not Received OR we're in receiving mode)
                    if (hasSerial == '1' && serialNumbers.length > 0 && (!isReceived || _isReceivingMode)) {
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
                        // No serial numbers yet or status is Received (and not in receiving mode), show dash
                        cell.innerHTML = '<span style="color: #999; font-size: 12px;">-</span>';
                    }
                }
            });
            
            // Also update the Edit Item Type/Status button
            updateItemTypeStatusActionButton(rowKey);
        }

        function updateItemTypeStatusActionButton(rowKey) {
            const currentStatus = '<?php echo $status; ?>';
            const isReceived = currentStatus.toLowerCase() === 'received';

            // Find the item type/status action cell and update the Edit button visibility
            const itemTypeStatusActionCells = document.querySelectorAll('.item-type-status-action-cell');
            itemTypeStatusActionCells.forEach(cell => {
                const cellFamilyCode = cell.getAttribute('data-family-code');
                const cellItemNo = cell.getAttribute('data-item-no');
                const cellRowKey = cellFamilyCode + '-' + cellItemNo;

                if (cellRowKey === rowKey) {
                    const serialNumbers = _allSerialNumbers[rowKey] || [];
                    const itemModel = cell.getAttribute('data-item-model');
                    const hasSerial = cell.getAttribute('data-has-serial');

                    // Show Edit button if:
                    // 1. Item has serial requirement AND serial numbers exist AND (status is not Received OR we're in receiving mode)
                    if (hasSerial == '1' && serialNumbers.length > 0 && (!isReceived || _isReceivingMode)) {
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
                        // No serial numbers yet or status is Received (and not in receiving mode), show dash
                        cell.innerHTML = '<span style="color: #999; font-size: 12px;">-</span>';
                    }
                }
            });
        }

        function updateQuantityDisplay(rowKey) {
            // Find all rows and update the quantity column for this item
            const rows = document.querySelectorAll('tr');
            rows.forEach(row => {
                const serialCell = row.querySelector('.serial-cell');
                if (serialCell) {
                    const cellFamilyCode = serialCell.getAttribute('data-family-code');
                    const cellItemNo = serialCell.getAttribute('data-item-no');
                    const cellRowKey = cellFamilyCode + '-' + cellItemNo;

                    if (cellRowKey === rowKey) {
                        // Find the quantity cell - it comes after the serial action cell(s)
                        // Get all td elements in the row
                        const quantityCells = row.querySelectorAll('td');
                        
                        // Find the index of the serial-action-cell
                        let serialActionIndex = -1;
                        quantityCells.forEach((cell, index) => {
                            if (cell.classList.contains('serial-action-cell')) {
                                serialActionIndex = index;
                            }
                        });
                        
                        // Quantity cell should be 2 positions after serial-action-cell
                        // (serial-action-cell, item-type-status-action-cell, then quantity)
                        let quantityCell = null;
                        if (serialActionIndex >= 0 && serialActionIndex + 2 < quantityCells.length) {
                            quantityCell = quantityCells[serialActionIndex + 2];
                        }

                        if (quantityCell) {
                            const hasSerial = parseInt(serialCell.getAttribute('data-has-serial')) || 0;
                            let receivedCount = 0;
                            if (hasSerial === 1) {
                                const serialNumbers = _allSerialNumbers[rowKey] || [];
                                receivedCount = serialNumbers.length;
                            } else {
                                receivedCount = parseInt(serialCell.getAttribute('data-received-qty')) || 0;
                            }
                            const totalQuantity = parseInt(serialCell.getAttribute('data-quantity')) || 0;

                            // Update display in format: received/total
                            quantityCell.textContent = receivedCount + '/' + totalQuantity;
                        }
                    }
                }
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

        function editItemModel(familyCode, itemNo, currentItemModel, itemDescription, allocationId, itemId) {
            // Set editing mode flag
            _isEditingExistingItem = true;
            _currentEditingRowKey = familyCode + '-' + itemNo;
            _currentEditingFamilyCode = familyCode;
            _currentEditingOldItemModel = currentItemModel || '';
            _currentEditingAllocationId = parseInt(allocationId) || 0;
            _currentEditingItemId = parseInt(itemId) || 0;

            // Prefer IDs from the actual table row when available
            const row = Array.from(document.querySelectorAll('.items-table tbody tr')).find(r => {
                if (_currentEditingAllocationId > 0) {
                    return parseInt(r.getAttribute('data-allocation-id') || '0') === _currentEditingAllocationId;
                }
                if (_currentEditingItemId > 0) {
                    return parseInt(r.getAttribute('data-item-id') || '0') === _currentEditingItemId;
                }
                return String(r.getAttribute('data-family-code') || '') === String(familyCode || '')
                    && parseInt(r.getAttribute('data-item-no') || '0') === parseInt(itemNo)
                    && String(r.getAttribute('data-item-model') || '') === String(currentItemModel || '');
            });
            if (row) {
                _currentEditingAllocationId = parseInt(row.getAttribute('data-allocation-id') || '0') || _currentEditingAllocationId;
                _currentEditingItemId = parseInt(row.getAttribute('data-item-id') || '0') || _currentEditingItemId;
                _currentEditingOldItemModel = row.getAttribute('data-item-model') || _currentEditingOldItemModel;
            }

            // Get the current quantity and has_serial for this specific row
            const serialCell = row
                ? row.querySelector('.serial-cell')
                : document.querySelector(`.serial-cell[data-family-code="${familyCode}"][data-item-no="${itemNo}"]`);
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
            
            // CRITICAL FIX: Add receiving_branch if viewing from purchaseorderreceive
            <?php if (!empty($specific_branch)): ?>
            formData.append('receiving_branch', '<?php echo addslashes($specific_branch); ?>');
            console.log('Sending receiving_branch for item model removal:', '<?php echo addslashes($specific_branch); ?>');
            <?php endif; ?>

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

        function editItemTypeStatus(familyCode, itemNo, itemModel) {
            const rowKey = familyCode + '-' + itemNo;
            
            // Get serial numbers for this item
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
            const savedTypes = window._currentEditingItemTypeStatus.types || {};

            const typesByUpper = {};
            Object.keys(savedTypes).forEach(k => {
                typesByUpper[String(k).toUpperCase()] = savedTypes[k];
            });

            const tableBody = document.getElementById('itemTypeStatusTableBody');
            tableBody.innerHTML = '';

            serialNumbers.forEach((serialNum, index) => {
                const currentType = typesByUpper[String(serialNum).toUpperCase()] || 'Good Stock';
                window._currentEditingItemTypeStatus.types[serialNum] = currentType;

                const row = document.createElement('tr');
                const tdNo = document.createElement('td');
                tdNo.textContent = String(index + 1);
                const tdModel = document.createElement('td');
                tdModel.style.cssText = 'text-align: left; padding-left: 15px; font-weight: 600;';
                tdModel.textContent = displayItemModel;
                const tdImei = document.createElement('td');
                tdImei.style.cssText = 'text-align: left; padding-left: 15px;';
                tdImei.textContent = serialNum;
                const tdType = document.createElement('td');
                const select = document.createElement('select');
                select.className = 'item-type-dropdown';
                select.dataset.serial = serialNum;
                ['Good Stock', 'Defective', 'Demo', 'Serviced'].forEach(optVal => {
                    const opt = document.createElement('option');
                    opt.value = optVal;
                    opt.textContent = optVal;
                    if (optVal === currentType) opt.selected = true;
                    select.appendChild(opt);
                });
                select.addEventListener('change', function () {
                    updateItemType(serialNum, this.value);
                });
                tdType.appendChild(select);
                const tdAction = document.createElement('td');
                const removeBtn = document.createElement('button');
                removeBtn.className = 'btn-remove-from-list';
                removeBtn.textContent = 'Remove';
                removeBtn.addEventListener('click', function () {
                    removeItemTypeRow(index);
                });
                tdAction.appendChild(removeBtn);

                row.appendChild(tdNo);
                row.appendChild(tdModel);
                row.appendChild(tdImei);
                row.appendChild(tdType);
                row.appendChild(tdAction);
                tableBody.appendChild(row);
            });

            document.getElementById('itemTypeStatusModal').style.display = 'flex';
        }
        
        function updateItemType(serialNumber, type) {
            if (window._currentEditingItemTypeStatus) {
                if (!window._currentEditingItemTypeStatus.types) {
                    window._currentEditingItemTypeStatus.types = {};
                }
                window._currentEditingItemTypeStatus.types[serialNumber] = type;
            }
        }
        
        function removeItemTypeRow(index) {
            if (!window._currentEditingItemTypeStatus) {
                return;
            }
            
            const serialNumber = window._currentEditingItemTypeStatus.serialNumbers[index];
            window._currentEditingItemTypeStatus.serialNumbers.splice(index, 1);
            if (window._currentEditingItemTypeStatus.types && window._currentEditingItemTypeStatus.types[serialNumber]) {
                delete window._currentEditingItemTypeStatus.types[serialNumber];
            }
            
            if (window._currentEditingItemTypeStatus.serialNumbers.length === 0) {
                alert('All items removed.');
                closeItemTypeStatusModal();
                return;
            }
            populateItemTypeStatusModal();
        }
        
        function closeItemTypeStatusModal() {
            document.getElementById('itemTypeStatusModal').style.display = 'none';
            window._currentEditingItemTypeStatus = null;
        }
        
        function saveItemTypeStatus() {
            if (!window._currentEditingItemTypeStatus) {
                return;
            }
            
            const { rowKey, familyCode, itemNo } = window._currentEditingItemTypeStatus;
            const poId = <?php echo $po_id; ?>;

            const types = {};
            document.querySelectorAll('#itemTypeStatusTableBody .item-type-dropdown').forEach(sel => {
                const serial = sel.dataset.serial || sel.getAttribute('data-serial') || '';
                if (serial) {
                    types[serial] = sel.value || 'Good Stock';
                }
            });
            if (Object.keys(types).length === 0 && window._currentEditingItemTypeStatus.types) {
                Object.assign(types, window._currentEditingItemTypeStatus.types);
            }
            if (Object.keys(types).length === 0) {
                alert('No IMEI/type rows to save.');
                return;
            }

            window._currentEditingItemTypeStatus.types = { ...types };
            
            if (!window._itemTypeStatusStorage) {
                window._itemTypeStatusStorage = {};
            }
            window._itemTypeStatusStorage[rowKey] = { ...types };
            
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
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function deleteItemRow(itemId, familyCode, itemNo) {
            // Find the row
            const rows = document.querySelectorAll('.items-table tbody tr');
            let targetRow = null;

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    // Check if this row matches the item
                    const deleteBtn = row.querySelector('.btn-delete-item');
                    if (deleteBtn && deleteBtn.onclick && deleteBtn.onclick.toString().includes(`deleteItemRow(${itemId}`)) {
                        targetRow = row;
                    }
                }
            });

            if (!targetRow) return;

            // Check if row is already marked for deletion
            const isMarked = targetRow.classList.contains('marked-for-deletion');

            if (isMarked) {
                // Unmark - remove strikethrough and enable buttons
                targetRow.classList.remove('marked-for-deletion');
                targetRow.style.textDecoration = '';
                targetRow.style.color = '';
                targetRow.style.opacity = '';

                // Re-enable Edit Item, Edit IMEI, and Add Item Model buttons
                const editItemBtn = targetRow.querySelector('.btn-edit-item-model');
                const editSerialBtn = targetRow.querySelector('.btn-edit-serial');
                const addSerialBtn = targetRow.querySelector('.btn-add-serial-number');
                const addItemModelBtn = targetRow.querySelector('.btn-add-item-model');

                if (editItemBtn) {
                    editItemBtn.disabled = false;
                    editItemBtn.style.opacity = '';
                    editItemBtn.style.cursor = '';
                    editItemBtn.style.pointerEvents = '';
                }
                if (editSerialBtn) {
                    editSerialBtn.disabled = false;
                    editSerialBtn.style.opacity = '';
                    editSerialBtn.style.cursor = '';
                    editSerialBtn.style.pointerEvents = '';
                }
                if (addSerialBtn) {
                    addSerialBtn.disabled = false;
                    addSerialBtn.style.opacity = '';
                    addSerialBtn.style.cursor = '';
                    addSerialBtn.style.pointerEvents = '';
                }
                if (addItemModelBtn) {
                    addItemModelBtn.disabled = false;
                    addItemModelBtn.style.opacity = '';
                    addItemModelBtn.style.cursor = '';
                    addItemModelBtn.style.pointerEvents = '';
                }

                // Change button icon back to X
                const deleteBtn = targetRow.querySelector('.btn-delete-item');
                if (deleteBtn) {
                    deleteBtn.innerHTML = `
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    `;
                    deleteBtn.title = 'Mark for deletion';
                }
            } else {
                // Mark for deletion - add strikethrough and disable buttons
                targetRow.classList.add('marked-for-deletion');
                targetRow.style.textDecoration = 'line-through';
                targetRow.style.color = '#c62828';
                targetRow.style.opacity = '0.7';

                // Disable Edit Item, Edit IMEI, and Add Item Model buttons
                const editItemBtn = targetRow.querySelector('.btn-edit-item-model');
                const editSerialBtn = targetRow.querySelector('.btn-edit-serial');
                const addSerialBtn = targetRow.querySelector('.btn-add-serial-number');
                const addItemModelBtn = targetRow.querySelector('.btn-add-item-model');

                if (editItemBtn) {
                    editItemBtn.disabled = true;
                    editItemBtn.style.opacity = '0.5';
                    editItemBtn.style.cursor = 'not-allowed';
                    editItemBtn.style.pointerEvents = 'none';
                }
                if (editSerialBtn) {
                    editSerialBtn.disabled = true;
                    editSerialBtn.style.opacity = '0.5';
                    editSerialBtn.style.cursor = 'not-allowed';
                    editSerialBtn.style.pointerEvents = 'none';
                }
                if (addSerialBtn) {
                    addSerialBtn.disabled = true;
                    addSerialBtn.style.opacity = '0.5';
                    addSerialBtn.style.cursor = 'not-allowed';
                    addSerialBtn.style.pointerEvents = 'none';
                }
                if (addItemModelBtn) {
                    addItemModelBtn.disabled = true;
                    addItemModelBtn.style.opacity = '0.5';
                    addItemModelBtn.style.cursor = 'not-allowed';
                    addItemModelBtn.style.pointerEvents = 'none';
                }

                // Change button icon to undo
                const deleteBtn = targetRow.querySelector('.btn-delete-item');
                if (deleteBtn) {
                    deleteBtn.innerHTML = `
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/>
                        </svg>
                    `;
                    deleteBtn.title = 'Undo deletion';
                }
            }
        }

        function updateSerialButtonDisplay(rowKey) {
            const currentStatus = '<?php echo $status; ?>';
            const isIncomplete = currentStatus.toLowerCase() === 'incomplete';

            // Find the serial cell for this item and update its display
            const serialCells = document.querySelectorAll('.serial-cell');
            serialCells.forEach(cell => {
                const cellFamilyCode = cell.getAttribute('data-family-code');
                const cellItemNo = cell.getAttribute('data-item-no');
                const cellRowKey = cellFamilyCode + '-' + cellItemNo;

                if (cellRowKey === rowKey) {
                    const serialNumbers = _allSerialNumbers[rowKey] || [];
                    const quantity = parseInt(cell.getAttribute('data-quantity'));
                    const itemDescription = cell.getAttribute('data-item-description');
                    const itemModel = cell.getAttribute('data-item-model');

                    // Check if item model exists (not empty and not '-')
                    const hasItemModel = itemModel && itemModel !== '-' && itemModel.trim() !== '';

                    if (isIncomplete) {
                        // For INCOMPLETE status, hide buttons
                        if (serialNumbers.length > 0) {
                            const serials2 = _allSerialNumbers2[rowKey] || [];
                            cell.innerHTML = formatSerialDisplayHtml(rowKey, serialNumbers, serials2);
                        } else {
                            cell.innerHTML = 'N/A';
                        }
                    } else {
                        // Only show Add Serial Number button if item model exists
                        if (!hasItemModel) {
                            // No item model yet - hide the button, show placeholder
                            cell.innerHTML = '<span style="color: #999; font-size: 12px;">-</span>';
                        } else if (serialNumbers.length > 0) {
                            // Item model exists - Display serial numbers paired
                            const serials2 = _allSerialNumbers2[rowKey] || [];
                            const serialList = formatSerialDisplayHtml(rowKey, serialNumbers, serials2);

                            if (serialNumbers.length < quantity) {
                                // Not all serials entered - show serials + button to add more
                                cell.innerHTML = `${serialList}<br><button class="btn-add-serial-number" onclick="openSerialModal('${rowKey}', '${itemDescription}', ${quantity})" style="margin-top: 5px; font-size: 11px; padding: 3px 8px;">Add More (${serialNumbers.length}/${quantity})</button>`;
                            } else {
                                // All serials entered - show only the serial numbers
                                cell.innerHTML = serialList;
                            }
                        } else {
                            // Item model exists but no serials - show the Add Serial/IMEI button
                            const config = getSerialConfig(rowKey);
                            const btnText = 'Add ' + config.modalTitleBase;
                            cell.innerHTML = `<button class="btn-add-serial-number" onclick="openSerialModal('${rowKey}', '${itemDescription}', ${quantity})">${escapeHtml(btnText)}</button>`;
                        }
                    }
                }
            });
        }

        // ========== Item Model Functions ==========
        let _selectedItemModel = '';
        let _selectedItemDescription = '';
        let _searchTimeout = null;
        let _currentEditingFamilyCode = ''; // Store current family code for filtering search
        let _currentEditingOldItemModel = '';
        let _currentEditingAllocationId = 0;
        let _currentEditingItemId = 0;
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
            _currentEditingOldItemModel = '';
            _currentEditingAllocationId = 0;
            _currentEditingItemId = 0;
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

            // Edit mode: replace the current selection instead of appending a second model
            if (_isEditingExistingItem) {
                const prevQty = (_tempSelectedItems[0] && _tempSelectedItems[0].quantity)
                    ? parseInt(_tempSelectedItems[0].quantity) || _originalQuantity || 1
                    : (_originalQuantity || 1);
                _tempSelectedItems = [{
                    itemCode: itemCode,
                    itemDescription: itemDescription,
                    quantity: prevQty,
                    hasSerial: hasSerial || 0,
                    receivedQty: hasSerial ? 0 : prevQty
                }];
            } else {
                // Add to temporary selected items array with default quantity and received qty
                _tempSelectedItems.push({
                    itemCode: itemCode,
                    itemDescription: itemDescription,
                    quantity: 1,  // Default quantity (ordered)
                    hasSerial: hasSerial || 0,  // Track if serialized
                    receivedQty: hasSerial ? 0 : 1  // Default received qty (only for non-serialized)
                });
            }

            // Update the selected items list display
            updateSelectedItemsList();
            updateSelectedItemsCount();

            // Refresh search results to show the selected item with "Selected" badge
            refreshSearchResults();

            // DO NOT clear search input or hide search results
            // Keep the search results visible so user can continue selecting

            // Show success message
            showTemporaryMessage(
                _isEditingExistingItem
                    ? 'Item replaced! Click "Update Item" to save.'
                    : 'Item added! You can select more items or click "Save All Items" to finish.',
                'success'
            );
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

        function showTemporaryMessage(message, type = 'success') {
            // Create a temporary message element
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
            const rowKey = _currentEditingRowKey || '';
            const dashPos = rowKey.lastIndexOf('-');
            const currentItemNo = dashPos >= 0 ? parseInt(rowKey.substring(dashPos + 1)) || 0 : 0;

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
                formData.append('old_item_model', _currentEditingOldItemModel || '');
                formData.append('allocation_id', _currentEditingAllocationId || 0);
                formData.append('item_id', _currentEditingItemId || 0);
                
                // CRITICAL FIX: Add receiving_branch if viewing from purchaseorderreceive
                <?php if (!empty($specific_branch)): ?>
                formData.append('receiving_branch', '<?php echo addslashes($specific_branch); ?>');
                console.log('Sending receiving_branch for item model update:', '<?php echo addslashes($specific_branch); ?>');
                <?php endif; ?>

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
                            // Preserve receiving mode state and invoice number on reload
                            if (_isReceivingMode) {
                                const currentUrl = new URL(window.location.href);
                                currentUrl.searchParams.set('receiving_mode', '1');

                                // Preserve invoice number if entered
                                const invoiceInput = document.getElementById('invoiceNumberInput');
                                if (invoiceInput && invoiceInput.value.trim()) {
                                    currentUrl.searchParams.set('invoice_number', invoiceInput.value.trim());
                                }

                                window.location.href = currentUrl.toString();
                            } else {
                                // Not in receiving mode, but still preserve invoice number if entered
                                const invoiceInput = document.getElementById('invoiceNumberInput');
                                if (invoiceInput && invoiceInput.value.trim()) {
                                    const currentUrl = new URL(window.location.href);
                                    currentUrl.searchParams.set('invoice_number', invoiceInput.value.trim());
                                    window.location.href = currentUrl.toString();
                                } else {
                                    location.reload();
                                }
                            }
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
                formData.append('old_item_model', _currentEditingOldItemModel || '');
                formData.append('allocation_id', _currentEditingAllocationId || 0);
                formData.append('item_id', _currentEditingItemId || 0);
                
                // CRITICAL FIX: Add receiving_branch if viewing from purchaseorderreceive
                <?php if (!empty($specific_branch)): ?>
                formData.append('receiving_branch', '<?php echo addslashes($specific_branch); ?>');
                console.log('Sending receiving_branch for item model update:', '<?php echo addslashes($specific_branch); ?>');
                <?php endif; ?>

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
                            // Preserve receiving mode state and invoice number on reload
                            if (_isReceivingMode) {
                                const currentUrl = new URL(window.location.href);
                                currentUrl.searchParams.set('receiving_mode', '1');

                                // Preserve invoice number if entered
                                const invoiceInput = document.getElementById('invoiceNumberInput');
                                if (invoiceInput && invoiceInput.value.trim()) {
                                    currentUrl.searchParams.set('invoice_number', invoiceInput.value.trim());
                                }

                                window.location.href = currentUrl.toString();
                            } else {
                                // Not in receiving mode, but still preserve invoice number if entered
                                const invoiceInput = document.getElementById('invoiceNumberInput');
                                if (invoiceInput && invoiceInput.value.trim()) {
                                    const currentUrl = new URL(window.location.href);
                                    currentUrl.searchParams.set('invoice_number', invoiceInput.value.trim());
                                    window.location.href = currentUrl.toString();
                                } else {
                                    location.reload();
                                }
                            }
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

            // Multiple items selected
            // Step 1: Update the current row with the first item
            const firstItem = _tempSelectedItems[0];
            const updateFormData = new FormData();
            updateFormData.append('po_id', <?php echo (int) $po_id; ?>);
            updateFormData.append('family_code', familyCode);
            updateFormData.append('item_no', currentItemNo);
            updateFormData.append('item_model', firstItem.itemCode);
            updateFormData.append('item_description', firstItem.itemDescription);
            updateFormData.append('quantity', parseInt(firstItem.quantity) || 1);
            updateFormData.append('old_item_model', _currentEditingOldItemModel || '');
            updateFormData.append('allocation_id', _currentEditingAllocationId || 0);
            updateFormData.append('item_id', _currentEditingItemId || 0);

            // CRITICAL FIX: Add receiving_branch if viewing from purchaseorderreceive
            <?php if (!empty($specific_branch)): ?>
            updateFormData.append('receiving_branch', '<?php echo addslashes($specific_branch); ?>');
            console.log('Sending receiving_branch for item model update (multiple):', '<?php echo addslashes($specific_branch); ?>');
            <?php endif; ?>

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
                        const itemsToAdd = [];
                        for (let i = 1; i < _tempSelectedItems.length; i++) {
                            const item = _tempSelectedItems[i];
                            const newItemNo = maxItemNo + i;
                            itemsToAdd.push({
                                family_code: familyCode,
                                item_no: newItemNo,
                                item_model: item.itemCode,
                                item_description: item.itemDescription,
                                quantity: parseInt(item.quantity) || 1,
                                received_qty: !item.hasSerial && item.receivedQty !== undefined ? parseInt(item.receivedQty) || 0 : 0
                            });
                        }

                        // Add new rows
                        const addFormData = new FormData();
                        addFormData.append('po_id', <?php echo (int) $po_id; ?>);
                        addFormData.append('items', JSON.stringify(itemsToAdd));
                        addFormData.append('force_new_rows', 'true'); // Force creating new rows for splitting
                        
                        // CRITICAL FIX: Add branch_name if viewing from purchaseorderreceive
                        <?php if (!empty($specific_branch)): ?>
                        addFormData.append('branch_name', '<?php echo addslashes($specific_branch); ?>');
                        console.log('Sending branch_name for splitting items:', '<?php echo addslashes($specific_branch); ?>');
                        <?php endif; ?>

                        return fetch('add_po_items.php', {
                            method: 'POST',
                            body: addFormData
                        });
                    } else {
                        // Only one item, already updated
                        return Promise.resolve({ ok: true, json: () => ({ success: true }) });
                    }
                })
                .then(res => {
                    if (res.ok || res.success) {
                        return res.json ? res.json() : res;
                    }
                    throw new Error('Failed to add new rows');
                })
                .then(data => {
                    if (data.success) {
                        alert('Items saved successfully!');
                        // Preserve receiving mode state and invoice number on reload
                        if (_isReceivingMode) {
                            const currentUrl = new URL(window.location.href);
                            currentUrl.searchParams.set('receiving_mode', '1');

                            // Preserve invoice number if entered
                            const invoiceInput = document.getElementById('invoiceNumberInput');
                            if (invoiceInput && invoiceInput.value.trim()) {
                                currentUrl.searchParams.set('invoice_number', invoiceInput.value.trim());
                            }

                            window.location.href = currentUrl.toString();
                        } else {
                            // Not in receiving mode, but still preserve invoice number if entered
                            const invoiceInput = document.getElementById('invoiceNumberInput');
                            if (invoiceInput && invoiceInput.value.trim()) {
                                const currentUrl = new URL(window.location.href);
                                currentUrl.searchParams.set('invoice_number', invoiceInput.value.trim());
                                window.location.href = currentUrl.toString();
                            } else {
                                location.reload();
                            }
                        }
                    } else {
                        alert('Error: ' + (data.message || 'Failed to add items'));
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save All Items (' + _tempSelectedItems.length + ')';
                    }
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save All Items (' + _tempSelectedItems.length + ')';
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

            // Split rowKey back to familyCode and itemNo
            const [familyCode, itemNo] = rowKey.split('-');

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
                const totalReceivedDisplay = <?php echo (int)$total_received_qty_display; ?>;
                const totalOrderedDisplay = <?php echo (int)$total_ordered_qty; ?>;
                if (totalReceivedDisplay < totalOrderedDisplay) {
                    return true;
                }
            }

            return false;
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
        }

        // Cancel Reason Modal Functions
        function showCancelReasonModal() {
            const modal = document.getElementById('cancelReasonModal');
            const textarea = document.getElementById('cancelReasonTextarea');
            textarea.value = ''; // Clear previous input
            updateCancelReasonCharCount();
            modal.classList.add('show');
            setTimeout(() => textarea.focus(), 100);
        }

        function closeCancelReasonModal() {
            document.getElementById('cancelReasonModal').classList.remove('show');
            _pendingStatus = null;
        }

        function updateCancelReasonCharCount() {
            const textarea = document.getElementById('cancelReasonTextarea');
            const charCount = document.getElementById('cancelReasonCharCount');
            charCount.textContent = textarea.value.length;
        }

        function confirmCancelWithReason() {
            const reason = document.getElementById('cancelReasonTextarea').value.trim();
            
            if (!reason) {
                alert('Please provide a reason for cancellation.');
                document.getElementById('cancelReasonTextarea').focus();
                return;
            }

            // Close cancel reason modal
            document.getElementById('cancelReasonModal').classList.remove('show');

            // Proceed with cancellation
            const poId = <?php echo $po_id; ?>;
            const formData = new FormData();
            formData.append('po_id', poId);
            formData.append('cancel_reason', reason);

            fetch('cancel_purchase_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Purchase Order canceled successfully.');
                    // Redirect back to purchaseorderreceive or reload
                    window.location.href = 'purchaseorderreceive.php';
                } else {
                    alert('Error: ' + (data.message || 'Failed to cancel purchase order.'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while canceling the purchase order.');
            });
        }

        // Character counter for cancel reason
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('cancelReasonTextarea');
            if (textarea) {
                textarea.addEventListener('input', updateCancelReasonCharCount);
            }
        });

        function confirmAction() {
            if (!_pendingStatus) return;

            const statusToSend = _pendingStatus;
            let serialNumbers = [];
            let serialNumbers2 = [];
            let isIncomplete = false;
            const currentStatus = '<?php echo $status; ?>';

            console.log('=== CONFIRM ACTION DEBUG ===');
            console.log('Current Status:', currentStatus);
            console.log('Status To Send:', statusToSend);
            console.log('Is Receiving Mode:', _isReceivingMode);
            console.log('All Serial Numbers Object:', _allSerialNumbers);
            console.log('All Serial Numbers 2 Object:', _allSerialNumbers2);

            // Collect items marked for deletion
            const markedRows = document.querySelectorAll('.items-table tbody tr.marked-for-deletion');
            const itemsToDelete = [];
            markedRows.forEach(row => {
                const deleteBtn = row.querySelector('.btn-delete-item');
                if (deleteBtn && deleteBtn.onclick) {
                    // Extract item ID from the onclick attribute
                    const onclickStr = deleteBtn.onclick.toString();
                    const match = onclickStr.match(/deleteItemRow\((\d+)/);
                    if (match && match[1]) {
                        itemsToDelete.push(parseInt(match[1]));
                    }
                }
            });
            console.log('Items marked for deletion:', itemsToDelete);

            // If in receiving mode with serialized items, collect serial numbers
            if (_isReceivingMode && statusToSend === 'Received') {
                for (let rowKey in _allSerialNumbers) {
                    const [familyCode, itemNo] = rowKey.split('-');
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

                for (let rowKey in _allSerialNumbers2) {
                    const [familyCode, itemNo] = rowKey.split('-');
                    (_allSerialNumbers2[rowKey] || []).forEach(serial2 => {
                        serialNumbers2.push({
                            family_code: familyCode,
                            item_no: parseInt(itemNo),
                            imei_2: serial2
                        });
                    });
                }

                // Check if incomplete
                isIncomplete = checkIsPOIncomplete();
                console.log('Is Incomplete:', isIncomplete);
                console.log('Total serial numbers collected:', serialNumbers.length);
                console.log('Serial Numbers Array:', serialNumbers);
                console.log('Serial Numbers 2 Array:', serialNumbers2);
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
            
            // Add specific branch if viewing from purchaseorderreceive
            <?php if (!empty($specific_branch)): ?>
            formData.append('receiving_branch', '<?php echo addslashes($specific_branch); ?>');
            console.log('Receiving Branch:', '<?php echo addslashes($specific_branch); ?>');
            <?php else: ?>
            console.log('WARNING: No specific branch set! This may cause updates to all branches!');
            <?php endif; ?>

            // Add invoice number
            const invoiceInput = document.getElementById('invoiceNumberInput');
            const invoiceNumber = invoiceInput ? invoiceInput.value.trim() : '';
            if (invoiceNumber) {
                formData.append('invoice_number', invoiceNumber);
                console.log('Invoice Number to send:', invoiceNumber);
            }

            if (serialNumbers.length > 0) {
                const serialNumbersJson = JSON.stringify(serialNumbers);
                console.log('Serial Numbers JSON to send:', serialNumbersJson);
                formData.append('serial_numbers', serialNumbersJson);
            } else {
                console.log('NO SERIAL NUMBERS TO SEND!');
            }

            if (serialNumbers2.length > 0) {
                const serialNumbers2Json = JSON.stringify(serialNumbers2);
                console.log('Serial Numbers 2 JSON to send:', serialNumbers2Json);
                formData.append('serial_numbers_2', serialNumbers2Json);
            }

            // Add item models if any were modified
            if (Object.keys(_allItemModels).length > 0) {
                // Convert rowKey (familyCode-itemNo) format to structured data
                const itemModelData = {};
                for (let rowKey in _allItemModels) {
                    const [familyCode, itemNo] = rowKey.split('-');
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

            // Add item types/status if any were set
            if (window._itemTypeStatusStorage && Object.keys(window._itemTypeStatusStorage).length > 0) {
                const itemTypesData = {};
                for (let rowKey in window._itemTypeStatusStorage) {
                    const [familyCode, itemNo] = rowKey.split('-');
                    itemTypesData[rowKey] = {
                        family_code: familyCode,
                        item_no: parseInt(itemNo),
                        types: window._itemTypeStatusStorage[rowKey]
                    };
                }
                console.log('Item Types to send:', itemTypesData);
                formData.append('item_types', JSON.stringify(itemTypesData));
            }

            // Add deleted items if any were marked
            if (itemsToDelete.length > 0) {
                console.log('Deleted Items to send:', itemsToDelete);
                formData.append('deleted_items', JSON.stringify(itemsToDelete));
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
            // Check if status is Received - prevent editing
            const currentStatus = '<?php echo $status; ?>';
            if (currentStatus.toLowerCase() === 'received') {
                return; // Don't save if status is Received
            }

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
            
            // CRITICAL FIX: Add receiving_branch if viewing from purchaseorderreceive
            <?php if (!empty($specific_branch)): ?>
            formData.append('receiving_branch', '<?php echo addslashes($specific_branch); ?>');
            console.log('Saving receiving_remarks for branch:', '<?php echo addslashes($specific_branch); ?>');
            <?php endif; ?>

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

            if (event.target == serialModal) {
                closeSerialModal();
            }
            if (event.target == itemModelModal) {
                closeItemModelModal();
            }
        }

        // Handle Enter key in serial input
        document.addEventListener('DOMContentLoaded', function () {
            const serialInput = document.getElementById('serialNumberInput');
            if (serialInput) {
                serialInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        addSerialToList();
                    }
                });
            }

            const serial2Input = document.getElementById('serial2NumberInput');
            if (serial2Input) {
                serial2Input.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        addSerial2ToList();
                    }
                });
            }

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
            // Open PDF in new window (consistent with report.php and upgradeunitreport.php)
            const serialData = encodeURIComponent(JSON.stringify(_allSerialNumbers || {}));
            const serialData2 = encodeURIComponent(JSON.stringify(_allSerialNumbers2 || {}));
            window.open('print_po_pdf.php?id=<?php echo $po_id; ?>&live_serials=' + serialData + '&live_serials2=' + serialData2, '_blank', 'width=900,height=700');
        }

        // ═══════════════ ADD NEW ITEM TO PO FUNCTIONS ═══════════════
        let _addedItemsCount = 0;
        let _currentItemId = null;
        let _currentSearchResults = [];

        function addNewItemToPO() {
            _addedItemsCount++;
            
            // Create or get the container for new items
            let newItemsContainer = document.getElementById('new-items-container');
            if (!newItemsContainer) {
                newItemsContainer = document.createElement('div');
                newItemsContainer.id = 'new-items-container';
                newItemsContainer.className = 'items-section';
                newItemsContainer.style.cssText = 'margin-top: 20px;';
                
                const title = document.createElement('h4');
                title.textContent = 'New Item';
                title.style.cssText = 'margin: 0 0 15px 0;';
                newItemsContainer.appendChild(title);
                
                const itemsWrapper = document.createElement('div');
                itemsWrapper.id = 'new-items-wrapper';
                newItemsContainer.appendChild(itemsWrapper);
                
                // Insert after the Add Item Button container
                const addItemButtonContainer = document.getElementById('addItemButtonContainer');
                if (addItemButtonContainer) {
                    addItemButtonContainer.parentNode.insertBefore(newItemsContainer, addItemButtonContainer.nextSibling);
                } else {
                    // Fallback: insert after the items section if button container not found
                    const itemsSection = document.querySelector('.items-section');
                    itemsSection.parentNode.insertBefore(newItemsContainer, itemsSection.nextSibling);
                }
            }
            
            const itemsWrapper = document.getElementById('new-items-wrapper');
            
            // Create new item card
            const itemCard = document.createElement('div');
            itemCard.id = 'new-item-card-' + _addedItemsCount;
            itemCard.style.cssText = 'background: #f8f9fa;; padding: 20px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #e0e0e0;';
            
            itemCard.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr 150px auto; gap: 15px; align-items: end;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #333;">Item Model</label>
                        <input type="text" 
                            id="item-model-${_addedItemsCount}" 
                            placeholder="Enter Item Model or press Enter to search" 
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; color: #333; font-family: Arial, sans-serif;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #333;">Item Description</label>
                        <input type="text" 
                            id="item-description-${_addedItemsCount}" 
                            placeholder="Item Description" 
                            disabled
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; color: #333; font-family: Arial, sans-serif; background: white; cursor: not-allowed;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #333;">Quantity</label>
                        <input type="number" 
                            id="quantity-${_addedItemsCount}" 
                            placeholder="Qty" 
                            min="0" 
                            value="0"
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; color: #333; font-family: Arial, sans-serif;">
                    </div>
                    <div>
                        <button class="btn-add-po-item" onclick="saveNewItem(${_addedItemsCount})" style="padding: 10px 20px; white-space: nowrap;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 5px;">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                            </svg>
                            Add Item
                        </button>
                    </div>
                </div>
            `;
            
            itemsWrapper.appendChild(itemCard);
            
            // Add Enter key event listener to item model input
            const itemModelInput = document.getElementById('item-model-' + _addedItemsCount);
            itemModelInput.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    openSearchItemModal(_addedItemsCount);
                }
            });
            
            // Focus on item model input
            setTimeout(() => {
                itemModelInput.focus();
            }, 100);
        }

        function openSearchItemModal(itemId) {
            _currentItemId = itemId;
            const searchTerm = document.getElementById('item-model-' + itemId).value.trim();
            
            if (searchTerm === '') {
                alert('Please enter Item Model to search');
                return;
            }
            
            // Fetch search results
            fetch(`search_item_purchaseorder.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    const resultsBody = document.getElementById('searchResultsBody');
                    resultsBody.innerHTML = '';
                    
                    if (data.status === 'success' && data.data.length > 0) {
                        _currentSearchResults = data.data;
                        data.data.forEach((item, index) => {
                            const row = `
                                <tr>
                                    <td>${item.item_model || item.item_code || '-'}</td>
                                    <td>${item.description || '-'}</td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-select" onclick="selectSearchItem(${index})">Select</button>
                                    </td>
                                </tr>
                            `;
                            resultsBody.insertAdjacentHTML('beforeend', row);
                        });
                    } else {
                        resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">No items found</td></tr>';
                    }
                    
                    document.getElementById('searchItemModal').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while searching.');
                });
        }

        function selectSearchItem(index) {
            if (_currentItemId === null) return;
            
            const selectedItem = _currentSearchResults[index];
            
            console.log('Selected item:', selectedItem);
            console.log('Family code:', selectedItem.family_code);
            console.log('Item code:', selectedItem.item_code);
            
            // Fill the fields with selected item data
            document.getElementById('item-model-' + _currentItemId).value = selectedItem.item_model || selectedItem.item_code || '';
            
            // Enable and fill description field
            const descField = document.getElementById('item-description-' + _currentItemId);
            descField.disabled = false;
            descField.value = selectedItem.description || '';
            descField.style.cursor = 'text';
            
            // Store family_code as data attribute for later use
            const familyCodeToStore = selectedItem.family_code || selectedItem.item_code || '';
            console.log('Storing family code:', familyCodeToStore);
            document.getElementById('item-model-' + _currentItemId).setAttribute('data-family-code', familyCodeToStore);
            
            closeSearchItemModal();
        }

        function closeSearchItemModal() {
            document.getElementById('searchItemModal').style.display = 'none';
            _currentItemId = null;
            _currentSearchResults = [];
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('searchItemModal');
            if (event.target === modal) {
                closeSearchItemModal();
            }
            
            const itemTypeStatusModal = document.getElementById('itemTypeStatusModal');
            if (event.target === itemTypeStatusModal) {
                closeItemTypeStatusModal();
            }
        });

        function saveNewItem(itemId) {
            const itemModelInput = document.getElementById('item-model-' + itemId);
            const itemModel = itemModelInput.value.trim();
            const itemDescription = document.getElementById('item-description-' + itemId).value.trim();
            const quantity = parseInt(document.getElementById('quantity-' + itemId).value) || 0;
            const familyCode = itemModelInput.getAttribute('data-family-code') || '';
            
            if (!itemModel && !itemDescription) {
                alert('Please enter Item Model or Item Description');
                return;
            }
            
            if (quantity < 0) {
                alert('Quantity cannot be negative');
                return;
            }
            
            // Get the next item number
            const existingRows = document.querySelectorAll('.items-table tbody tr:not(.grand-total-row):not(.new-item-row)');
            const nextItemNo = existingRows.length + 1;
            
            // Get family code - use stored data attribute if available, otherwise use item model as fallback
            const storedFamilyCode = itemModelInput.getAttribute('data-family-code');
            const finalFamilyCode = (storedFamilyCode && storedFamilyCode !== '') ? storedFamilyCode : itemModel;
            
            console.log('Saving item:', {
                familyCode: finalFamilyCode,
                itemModel: itemModel,
                itemDescription: itemDescription,
                quantity: quantity
            });
            
            // Send to server
            const formData = new FormData();
            formData.append('action', 'add_items_to_po');
            formData.append('po_id', <?php echo $po_id; ?>);
            formData.append('po_number', '<?php echo addslashes($po['po_number']); ?>');
            formData.append('items', JSON.stringify([{
                family_code: finalFamilyCode,
                familyCode: finalFamilyCode,
                item_no: nextItemNo,
                itemModel: itemModel,
                item_model: itemModel,
                itemDescription: itemDescription,
                item_description: itemDescription,
                quantity: quantity,
                cost: 0,
                received_qty: 0
            }]));
            formData.append('skip_allocation', 'true');
            formData.append('force_new_rows', 'true');
            <?php if (!empty($specific_branch)): ?>
            formData.append('branch_name', '<?php echo addslashes($specific_branch); ?>');
            <?php endif; ?>
            
            // Disable button during save
            const saveBtn = event.target.closest('button');
            const originalHTML = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = 'Saving...';
            
            fetch('add_po_items.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Log the raw response for debugging
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse JSON:', text);
                        throw new Error('Server returned invalid JSON: ' + text.substring(0, 100));
                    }
                });
            })
            .then(data => {
                console.log('Parsed data:', data);
                if (data.success) {
                    // Remove the new item card
                    const itemCard = document.getElementById('new-item-card-' + itemId);
                    if (itemCard) {
                        itemCard.remove();
                    }
                    
                    // Check if there are any items left
                    const itemsWrapper = document.getElementById('new-items-wrapper');
                    if (itemsWrapper && itemsWrapper.children.length === 0) {
                        // Remove entire container if no items left
                        const container = document.getElementById('new-items-container');
                        if (container) {
                            container.remove();
                        }
                    }
                    
                    // Show success message
                    console.log('Item saved successfully, reloading page...');
                    
                    // Save current state before reload
                    const invoiceInput = document.getElementById('invoiceNumberInput');
                    const remarksTextarea = document.querySelector('textarea[name="receiving_remarks"]');
                    
                    if (invoiceInput) {
                        sessionStorage.setItem('po_invoice_number', invoiceInput.value);
                    }
                    if (remarksTextarea) {
                        sessionStorage.setItem('po_receiving_remarks', remarksTextarea.value);
                    }
                    
                    // Save all serial numbers from the page
                    const serialData = {};
                    Object.keys(_allSerialNumbers).forEach(rowKey => {
                        if (_allSerialNumbers[rowKey] && _allSerialNumbers[rowKey].length > 0) {
                            serialData[rowKey] = _allSerialNumbers[rowKey];
                        }
                    });
                    if (Object.keys(serialData).length > 0) {
                        sessionStorage.setItem('po_serial_numbers', JSON.stringify(serialData));
                    }

                    const serialData2 = {};
                    Object.keys(_allSerialNumbers2).forEach(rowKey => {
                        if (_allSerialNumbers2[rowKey] && _allSerialNumbers2[rowKey].length > 0) {
                            serialData2[rowKey] = _allSerialNumbers2[rowKey];
                        }
                    });
                    if (Object.keys(serialData2).length > 0) {
                        sessionStorage.setItem('po_serial_numbers_2', JSON.stringify(serialData2));
                    }
                    
                    // Save receiving mode state
                    if (_isReceivingMode) {
                        sessionStorage.setItem('po_receiving_mode', 'true');
                    }
                    
                    // Force reload with current URL to preserve query parameters
                    setTimeout(() => {
                        window.location.href = window.location.href;
                    }, 100);
                } else {
                    console.error('Save failed:', data.message);
                    alert('Error adding item: ' + (data.message || 'Unknown error'));
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalHTML;
                }
            })
            .catch(error => {
                console.error('Save error:', error);
                alert('Failed to add item: ' + error.message);
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalHTML;
            });
        }
    </script>
</body>

</html>