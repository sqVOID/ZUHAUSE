<?php
require_once 'session_check.php';
include 'config.php';

// Authorization Check - Check if user has access to this page
// Temporarily disabled for testing
/*
if (isset($_SESSION['sidebar_access']) && $_SESSION['sidebar_access'] !== '') {
    $sidebar_hidden = array_map('trim', explode(',', $_SESSION['sidebar_access']));
    if (in_array('Receive Purchase Order', $sidebar_hidden)) {
        header("Location: report.php");
        exit;
    }
}
*/

// Create purchase_orders table if not exists (with full schema)
$conn->query("CREATE TABLE IF NOT EXISTS purchase_orders (
    id                  INT(11) AUTO_INCREMENT PRIMARY KEY,
    po_number           VARCHAR(50) NOT NULL UNIQUE,
    supplier_company    VARCHAR(255),
    supplier_name       VARCHAR(255),
    contact_number      VARCHAR(100),
    address             TEXT,
    terms               VARCHAR(100),
    payment_due_date    DATE,
    remarks             TEXT,
    po_date             DATE,
    total_items         INT(11) DEFAULT 0,
    total_qty           INT(11) DEFAULT 0,
    total_cost          DECIMAL(12,2) DEFAULT 0.00,
    status              VARCHAR(50) DEFAULT 'Pending',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Add created_by and created_by_branch columns if they don't exist
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS created_by VARCHAR(100)");
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS created_by_branch VARCHAR(50)");

// Add serial_number column to purchase_order_allocations if it doesn't exist (for branch-specific serial numbers)
$conn->query("ALTER TABLE purchase_order_allocations ADD COLUMN IF NOT EXISTS serial_number TEXT DEFAULT NULL");

// Handle search and filter functionality
$search_query = '';
$status_filter = '';
$date_filter = '';
$where_conditions = [];

// Branch filtering
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

// Initialize branch variables
$branch_names_escaped = [];

// Only Super-Admin can see all branches
// Sub-admin should be restricted to their assigned branches based on ALLOCATIONS
if (strcasecmp($system_level, 'Super-Admin') !== 0) {
    if (!empty($user_branch)) {
        // Handle multiple branches (comma-separated)
        $branch_names = array_map('trim', explode(',', $user_branch));
        
        foreach ($branch_names as $branch_name) {
            $branch_names_escaped[] = "'" . $conn->real_escape_string($branch_name) . "'";
        }
        
        if (!empty($branch_names_escaped)) {
            // Filter by ALLOCATED branches, not created_by_branch
            // User can only see POs that have allocations for their branch
            $where_conditions[] = "EXISTS (
                SELECT 1 FROM purchase_order_allocations poa 
                WHERE poa.po_id = po.id 
                AND poa.branch_name IN (" . implode(', ', $branch_names_escaped) . ")
            )";
        } else {
            // User doesn't have any valid branches mapped
            $where_conditions[] = "1 = 0";
        }
    } else {
        // User has no branch set
        $where_conditions[] = "1 = 0";
    }
} else {
    // Super-Admin: Get all branches for filtering allocations
    $all_branches_query = $conn->query("SELECT branch_name FROM branches WHERE status = 'Active'");
    if ($all_branches_query && $all_branches_query->num_rows > 0) {
        while ($branch_row = $all_branches_query->fetch_assoc()) {
            $branch_names_escaped[] = "'" . $conn->real_escape_string($branch_row['branch_name']) . "'";
        }
    }
}

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
    $search_escaped = $conn->real_escape_string($search_query);
    $where_conditions[] = "(po_number LIKE '%$search_escaped%' OR supplier_company LIKE '%$search_escaped%')";
}

// Supplier filter
$supplier_filter = '';
if (isset($_GET['supplier']) && !empty(trim($_GET['supplier']))) {
    $supplier_filter = trim($_GET['supplier']);
    // Only add to WHERE conditions if not "all"
    if ($supplier_filter !== 'all') {
        $supplier_escaped = $conn->real_escape_string($supplier_filter);
        $where_conditions[] = "supplier_company = '$supplier_escaped'";
    }
}

// Branch filter
$branch_filter = '';
if (isset($_GET['branch']) && !empty(trim($_GET['branch']))) {
    $branch_filter = trim($_GET['branch']);
    // Only add to WHERE conditions if not "all"
    if ($branch_filter !== 'all') {
        $branch_escaped = $conn->real_escape_string($branch_filter);
        // Filter by specific branch allocation
        $where_conditions[] = "EXISTS (
            SELECT 1 FROM purchase_order_allocations poa 
            WHERE poa.po_id = po.id 
            AND poa.branch_name = '$branch_escaped'
        )";
    }
}

// Check if there's a status filter from the URL, otherwise default to showing Pending and Incomplete
$has_status_filter = isset($_GET['status']) && !empty(trim($_GET['status']));

// Fetch unique suppliers for the dropdown filter
$suppliers_list = [];
$supplier_query_where = '';
if (strcasecmp($system_level, 'Super-Admin') !== 0 && !empty($branch_names_escaped)) {
    // For non-Super-Admin, only show suppliers from POs that have allocations for their branches
    $supplier_query_where = " WHERE EXISTS (
        SELECT 1 FROM purchase_order_allocations poa 
        WHERE poa.po_id = po.id 
        AND poa.branch_name IN (" . implode(', ', $branch_names_escaped) . ")
    )";
}
$suppliers_query = $conn->query("SELECT DISTINCT supplier_company FROM purchase_orders po" . $supplier_query_where . " ORDER BY supplier_company ASC");
if ($suppliers_query) {
    while ($supplier_row = $suppliers_query->fetch_assoc()) {
        if (!empty($supplier_row['supplier_company'])) {
            $suppliers_list[] = $supplier_row['supplier_company'];
        }
    }
}

// Fetch unique branches for the dropdown filter
$branches_list = [];
if (strcasecmp($system_level, 'Super-Admin') === 0) {
    // Super-Admin: Show all active branches
    $branches_query = $conn->query("SELECT branch_name FROM branches WHERE status = 'Active' ORDER BY branch_name ASC");
    if ($branches_query) {
        while ($branch_row = $branches_query->fetch_assoc()) {
            if (!empty($branch_row['branch_name'])) {
                $branches_list[] = $branch_row['branch_name'];
            }
        }
    }
} else {
    // Non-Super-Admin: Show only their assigned branches
    if (!empty($user_branch)) {
        $branch_names = array_map('trim', explode(',', $user_branch));
        $branches_list = $branch_names;
    }
}

// If a status filter is explicitly set in URL, use it
if ($has_status_filter) {
    $status_filter = trim($_GET['status']);
}

// Only execute query if a status filter is selected (or auto-selected)
$po_result = null;
$processed_results = [];

if ($has_status_filter) {
    // DON'T filter by status at the SQL level - we need to calculate branch-specific status first
    // Only keep non-status filters (branch, search, date)
    $sql_where_conditions = [];
    
    // Branch filtering
    if (strcasecmp($system_level, 'Super-Admin') !== 0) {
        if (!empty($user_branch)) {
            $branch_names = array_map('trim', explode(',', $user_branch));
            $branch_names_escaped_sql = [];
            foreach ($branch_names as $branch_name) {
                $branch_names_escaped_sql[] = "'" . $conn->real_escape_string($branch_name) . "'";
            }
            
            if (!empty($branch_names_escaped_sql)) {
                $sql_where_conditions[] = "EXISTS (
                    SELECT 1 FROM purchase_order_allocations poa 
                    WHERE poa.po_id = po.id 
                    AND poa.branch_name IN (" . implode(', ', $branch_names_escaped_sql) . ")
                )";
            } else {
                $sql_where_conditions[] = "1 = 0";
            }
        } else {
            $sql_where_conditions[] = "1 = 0";
        }
    }
    
    // Search filter
    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $search_query = trim($_GET['search']);
        $search_escaped = $conn->real_escape_string($search_query);
        $sql_where_conditions[] = "(po_number LIKE '%$search_escaped%' OR supplier_company LIKE '%$search_escaped%')";
    }
    
    // Supplier filter
    if (isset($_GET['supplier']) && !empty(trim($_GET['supplier']))) {
        $supplier_filter_query = trim($_GET['supplier']);
        // Only add to WHERE conditions if not "all"
        if ($supplier_filter_query !== 'all') {
            $supplier_escaped = $conn->real_escape_string($supplier_filter_query);
            $sql_where_conditions[] = "supplier_company = '$supplier_escaped'";
        }
    }
    
    // Branch filter
    if (isset($_GET['branch']) && !empty(trim($_GET['branch']))) {
        $branch_filter_query = trim($_GET['branch']);
        // Only add to WHERE conditions if not "all"
        if ($branch_filter_query !== 'all') {
            $branch_escaped = $conn->real_escape_string($branch_filter_query);
            // Filter by specific branch allocation
            $sql_where_conditions[] = "EXISTS (
                SELECT 1 FROM purchase_order_allocations poa 
                WHERE poa.po_id = po.id 
                AND poa.branch_name = '$branch_escaped'
            )";
        }
    }
    
    // IMPORTANT: Only show POs that have branch allocations (allocation requirement)
    $sql_where_conditions[] = "EXISTS (
        SELECT 1 FROM purchase_order_allocations poa 
        WHERE poa.po_id = po.id
    )";
    
    // CRITICAL: Exclude Canceled and Closed POs from purchaseorderreceive UNLESS specifically filtered OR "all" is selected
    if ($status_filter !== 'Closed' && $status_filter !== 'CANCELED' && $status_filter !== 'all') {
        $sql_where_conditions[] = "po.status != 'Canceled' AND po.status != 'Cancelled' AND po.status != 'Closed'";
    } elseif ($status_filter === 'CANCELED') {
        // Only show canceled
        $sql_where_conditions[] = "(po.status = 'Canceled' OR po.status = 'Cancelled')";
    } elseif ($status_filter === 'Closed') {
        // Only show closed
        $sql_where_conditions[] = "po.status = 'Closed'";
    }
    // If status_filter is 'all', don't add any status filtering - show everything

    if (isset($_GET['date']) && !empty(trim($_GET['date']))) {
        $date_filter = trim($_GET['date']);
        $date_escaped = $conn->real_escape_string($date_filter);
        $sql_where_conditions[] = "DATE(po_date) = '$date_escaped'";
    }

    $where_clause = '';
    if (!empty($sql_where_conditions)) {
        $where_clause = " WHERE " . implode(' AND ', $sql_where_conditions);
    }

    // Fetch ALL Purchase Orders (don't filter by status yet)
    $sql = "SELECT 
                po.id, 
                po.po_number, 
                po.supplier_company, 
                po.po_date, 
                po.total_items,
                po.total_qty,
                po.terms, 
                po.total_cost, 
                po.status
            FROM purchase_orders po
            " . $where_clause . "
            ORDER BY po.id DESC";
    $po_result = $conn->query($sql);

    // Process results to create separate rows for each branch allocation
    if ($po_result && $po_result->num_rows > 0) {
        while ($po_row = $po_result->fetch_assoc()) {
            // Get all branch allocations for this PO (one row per branch)
            $branch_allocations_query = $conn->query("
                SELECT 
                    poa.branch_name,
                    SUM(poa.quantity) as branch_allocated_qty,
                    SUM(COALESCE(poa.received_qty, 0)) as branch_received_qty,
                    SUM(poa.quantity * poa.cost) as allocated_cost
                FROM purchase_order_allocations poa
                WHERE poa.po_id = {$po_row['id']}
                AND poa.branch_name IN (" . implode(', ', $branch_names_escaped) . ")
                GROUP BY poa.branch_name
                ORDER BY poa.branch_name ASC
            ");
            
            if ($branch_allocations_query && $branch_allocations_query->num_rows > 0) {
                while ($branch_alloc = $branch_allocations_query->fetch_assoc()) {
                    // If branch filter is set, only process that branch
                    if (!empty($branch_filter) && $branch_filter !== 'all') {
                        if (strcasecmp($branch_alloc['branch_name'], $branch_filter) !== 0) {
                            continue; // Skip branches that don't match the filter
                        }
                    }
                    
                    // Create a separate row for each branch
                    $branch_po_row = $po_row; // Copy the PO data
                    
                    // Start with allocation-based totals
                    $branch_alloc_qty  = (int)$branch_alloc['branch_allocated_qty'];
                    $branch_recv_qty   = (int)$branch_alloc['branch_received_qty'];
                    $branch_alloc_cost = (float)$branch_alloc['allocated_cost'];

                    // --- Also count "Add New Item" (is_receive_added) rows for this branch ---
                    // These items are stored in purchase_order_items with is_receive_added=1
                    // and receiving_branch set to this branch. viewpurchaseorder.php counts
                    // them too (using serial number count for serialized items, received_qty
                    // for non-serialized items). We must mirror that logic exactly.
                    $branch_esc_inner = $conn->real_escape_string($branch_alloc['branch_name']);
                    $receive_added_query = $conn->query("
                        SELECT
                            poi.quantity,
                            poi.received_qty,
                            poi.serial_number,
                            poi.cost,
                            COALESCE(MAX(i.has_serial), 0) as has_serial
                        FROM purchase_order_items poi
                        LEFT JOIN items i ON poi.family_code = i.family_code
                        WHERE poi.po_id = {$po_row['id']}
                        AND COALESCE(poi.is_receive_added, 0) = 1
                        AND poi.receiving_branch = '{$branch_esc_inner}'
                        GROUP BY poi.id
                    ");
                    if ($receive_added_query && $receive_added_query->num_rows > 0) {
                        while ($ra_row = $receive_added_query->fetch_assoc()) {
                            $ra_qty = (int)($ra_row['quantity'] ?? 1);
                            $branch_alloc_qty  += $ra_qty;
                            $branch_alloc_cost += $ra_qty * (float)($ra_row['cost'] ?? 0);

                            // Mirror viewpurchaseorder.php: serialized = count serial numbers,
                            // non-serialized = use received_qty field
                            if ((int)$ra_row['has_serial'] === 1) {
                                $sn = trim($ra_row['serial_number'] ?? '');
                                if (!empty($sn)) {
                                    $sn_arr = (strpos($sn, "\n") !== false)
                                        ? explode("\n", $sn)
                                        : explode(",", $sn);
                                    $branch_recv_qty += count(array_filter(array_map('trim', $sn_arr)));
                                }
                            } else {
                                $branch_recv_qty += (int)($ra_row['received_qty'] ?? 0);
                            }
                        }
                    }

                    // Override with combined branch-specific values
                    $branch_po_row['branch_name']  = $branch_alloc['branch_name'];
                    $branch_po_row['total_qty']    = $branch_alloc_qty;
                    $branch_po_row['received_qty'] = $branch_recv_qty;
                    $branch_po_row['total_cost']   = $branch_alloc_cost;
                    
                    // CRITICAL FIX: Calculate branch-specific status
                    // BUT preserve CLOSED and CANCELED statuses from database
                    $branch_allocated = $branch_alloc_qty;
                    $branch_received  = $branch_recv_qty;
                    $db_status = $po_row['status']; // Original status from database
                    
                    // If the PO is CLOSED or CANCELED in the database, preserve that status
                    if (strcasecmp($db_status, 'Closed') === 0 || strcasecmp($db_status, 'Canceled') === 0 || strcasecmp($db_status, 'Cancelled') === 0) {
                        // Keep the database status (CLOSED or CANCELED)
                        $branch_po_row['status'] = $db_status;
                    } else {
                        // Calculate branch-specific status for other cases
                        if ($branch_received == 0) {
                            // This branch hasn't received anything yet
                            $branch_po_row['status'] = 'Pending';
                        } elseif ($branch_received < $branch_allocated) {
                            // This branch has partially received
                            $branch_po_row['status'] = 'Incomplete';
                        } elseif ($branch_received >= $branch_allocated) {
                            // This branch has fully received
                            $branch_po_row['status'] = 'Received';
                        }
                    }
                    
                    // NOW apply status filtering AFTER calculating branch-specific status
                    $status_escaped = $conn->real_escape_string($status_filter);
                    $include_row = false;
                    
                    if ($status_filter === 'pending_incomplete') {
                        // Show both Pending and Incomplete
                        $include_row = ($branch_po_row['status'] === 'Pending' || $branch_po_row['status'] === 'Incomplete');
                    } elseif ($status_filter === 'all') {
                        // Show all statuses
                        $include_row = true;
                    } else {
                        // Match specific status
                        $include_row = (strcasecmp($branch_po_row['status'], $status_filter) === 0);
                    }
                    
                    if ($include_row) {
                        $processed_results[] = $branch_po_row;
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - Invoice Per Branch</title>
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

        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background-color: white;
            display: flex;
            align-items: center;
            padding: 0 20px;
            z-index: 1000;
            gap: 30px;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 250px;
            right: 0;
            height: 1px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
            pointer-events: none;
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

        /* Sidebar */
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

        /* Main Content */
        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .content-header {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
        }

        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #000;
            margin: 0;
        }

        /* Filter Bar */
        .filter-bar {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-group {
            display: flex;
            align-items: center;
        }

        .search-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input-wrapper input {
            padding: 8px 10px 8px 35px;
            border: 1px solid #ddd;
            border-right: none;
            border-radius: 4px 0 0 4px;
            font-size: 14px;
            width: 250px;
            outline: none;
        }

        .search-input-wrapper svg {
            position: absolute;
            left: 10px;
            width: 16px;
            height: 16px;
            fill: #999;
        }

        .btn-search {
            background-color: var(--color-navy);
            color: white;
            border: none;
            padding: 9px 20px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-search:hover {
            background-color: var(--color-navy-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(13, 51, 71, 0.3);
        }

        .filters-right {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .dropdown {
            position: relative;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            min-width: 150px;
        }

        .dropdown-select {
            padding: 8px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #666;
        }

        .dropdown-select svg {
            width: 10px;
            height: 10px;
            fill: #666;
        }

        .dropdown select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            font-size: 14px;
            color: #666;
            cursor: pointer;
            min-width: 120px;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 12px;
            padding-right: 30px;
        }

        .dropdown select:hover {
            border-color: #999;
        }

        .dropdown select:focus {
            border-color: #408140;
            box-shadow: 0 0 0 2px rgba(64, 129, 64, 0.1);
        }

        .dropdown input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            font-size: 14px;
            color: #666;
            cursor: pointer;
            min-width: 150px;
            outline: none;
        }

        .dropdown input[type="date"]:hover {
            border-color: #999;
        }

        .dropdown input[type="date"]:focus {
            border-color: #408140;
            box-shadow: 0 0 0 2px rgba(64, 129, 64, 0.1);
        }


        .table-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table-container h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0 0 20px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--color-gold-pale);
        }

        th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000000;
            border-top: 1px solid #ccc;
            border-bottom: 1px solid #ccc;
        }

        th:first-child {
            border-left: 1px solid #ccc;
        }

        th:last-child {
            border-right: 1px solid #ccc;
        }

        td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border-bottom: 1px solid #ccc;
            text-align: center !important;
        }

        td:first-child {
            border-left: 1px solid #ccc;
        }

        td:last-child {
            border-right: 1px solid #ccc;
        }

        tbody tr:hover {
            background: #fdf8f3;
        }

        tbody td {
            text-align: center;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-weight: 500;
            font-size: 14px;
        }

        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
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
            background: #e2e3e5;
            color: #41464b;
            border: 1px solid #d3d6d8;
        }

        .badge-waiting {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa0;
        }

        .badge-completed {
            background: #d4edda;
            color: #1a7a35;
            border: 1px solid #b8dfc6;
        }

        /* Action buttons in table - DIFFERENT STYLE FOR INVPERBRANCH */
        .btn-view {
            padding: 6px 16px;
            background: linear-gradient(135deg, var(--color-navy) 0%, var(--color-navy-light) 100%);
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            margin-right: 4px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(13, 51, 71, 0.2);
            letter-spacing: 0.5px;
        }

        .btn-view:hover {
            background: linear-gradient(135deg, var(--color-gold) 0%, var(--color-gold-light) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(176, 138, 82, 0.3);
        }

        .btn-view:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(13, 51, 71, 0.2);
        }

        .btn-edit {
            padding: 5px 14px;
            background: #555;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-edit:hover {
            background: #333;
        }

        .btn-modify {
            padding: 5px 14px;
            background: #408140;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 4px;
        }

        .btn-modify:hover {
            background: #367036;
        }

        /* Modal Styles */
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
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            border: 1px solid #888;
            width: 90%;
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
            padding: 20px 30px;
            border-bottom: 1px solid #eee;
            font-size: 18px;
            font-weight: 700;
            color: #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .modal-close:hover {
            color: #000;
        }

        .modal-body {
            padding: 20px 30px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-table-section {
            margin-bottom: 30px;
        }

        .modal-table-section h4 {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }

        .modal-footer {
            padding: 15px 30px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-back-modal {
            background-color: #666;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-back-modal:hover {
            background-color: #555;
        }

        /* Modal Table Styles */
        .modal-body table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .modal-body thead {
            background: var(--color-gold-pale);
        }

        .modal-body th {
            text-align: center;
            padding: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border: 1px solid #ccc;
        }

        .modal-body td {
            padding: 10px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
        }

        .modal-body tbody tr:hover {
            background: #fafafa;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.hidden {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .header::after {
                left: 0;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .search-input-wrapper input {
                width: 100%;
            }

            .filters-right {
                flex-direction: column;
                gap: 10px;
            }

            .dropdown {
                width: 100%;
            }

            .dropdown select {
                width: 100%;
                min-width: unset;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <!-- <img src="Icon/motogam_logo.jpg" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h2>Purchase Order - Invoice Per Branch</h2>
        </div>

        <div class="filter-bar">
            <form method="GET" action="" style="display: contents;">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                <input type="hidden" name="supplier" value="<?php echo htmlspecialchars($supplier_filter); ?>">
                <input type="hidden" name="branch" value="<?php echo htmlspecialchars($branch_filter); ?>">
                <div class="search-group">
                    <div class="search-input-wrapper">
                        <svg viewBox="0 0 24 24">
                            <path
                                d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                        </svg>
                        <input type="text" name="search" placeholder="Search PO Number or Supplier"
                            value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    <button type="submit" class="btn-search">Search</button>
                    <?php if (!empty($search_query) || !empty($status_filter) || !empty($date_filter) || !empty($supplier_filter) || !empty($branch_filter)): ?>
                        <a href="purchaseorder-invperbranch.php" class="btn-clear"
                            style="padding: 9px 15px; background: #666; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; margin-left: 5px;">Clear
                            All</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="filters-right">

                <!-- Status Filter -->
                <form method="GET" action="" style="display: inline;">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    <input type="hidden" name="supplier" value="<?php echo htmlspecialchars($supplier_filter); ?>">
                    <input type="hidden" name="branch" value="<?php echo htmlspecialchars($branch_filter); ?>">
                    <div class="dropdown">
                        <select name="status" onchange="this.form.submit()"
                            style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: white; font-size: 14px; color: #666; cursor: pointer; min-width: 150px;">
                            <option value="" <?php echo (!isset($_GET['status']) && $status_filter !== 'Pending') ? 'selected' : ''; ?>>Select Status</option>
                            <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>
                                Open</option>
                            <option value="Incomplete" <?php echo ($status_filter === 'Incomplete') ? 'selected' : ''; ?>>
                                Incomplete</option>
                            <option value="Received" <?php echo ($status_filter === 'Received') ? 'selected' : ''; ?>>
                                Completed</option>
                            <option value="Closed" <?php echo ($status_filter === 'Closed') ? 'selected' : ''; ?>>
                                Closed</option>
                            <option value="CANCELED" <?php echo ($status_filter === 'CANCELED') ? 'selected' : ''; ?>>
                                Canceled</option>
                        </select>
                    </div>
                </form>

                <!-- Supplier Filter -->
                <form method="GET" action="" style="display: inline;">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    <input type="hidden" name="branch" value="<?php echo htmlspecialchars($branch_filter); ?>">
                    <div class="dropdown">
                        <select name="supplier" onchange="this.form.submit()"
                            style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: white; font-size: 14px; color: #666; cursor: pointer; min-width: 180px;">
                            <option value="">Select Supplier</option>
                            <option value="all" <?php echo ($supplier_filter === 'all') ? 'selected' : ''; ?>>All Suppliers</option>
                            <?php foreach ($suppliers_list as $supplier): ?>
                                <option value="<?php echo htmlspecialchars($supplier); ?>" 
                                    <?php echo ($supplier_filter === $supplier) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <!-- Branch Filter -->
                <form method="GET" action="" style="display: inline;">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    <input type="hidden" name="supplier" value="<?php echo htmlspecialchars($supplier_filter); ?>">
                    <div class="dropdown">
                        <select name="branch" onchange="this.form.submit()"
                            style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: white; font-size: 14px; color: #666; cursor: pointer; min-width: 180px;">
                            <option value="">Select Branch</option>
                            <option value="all" <?php echo ($branch_filter === 'all') ? 'selected' : ''; ?>>All Branches</option>
                            <?php foreach ($branches_list as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch); ?>" 
                                    <?php echo ($branch_filter === $branch) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branch); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <!-- Date Filter -->
                <form method="GET" action="" style="display: inline;">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <input type="hidden" name="supplier" value="<?php echo htmlspecialchars($supplier_filter); ?>">
                    <input type="hidden" name="branch" value="<?php echo htmlspecialchars($branch_filter); ?>">
                    <div class="dropdown">
                        <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>"
                            onchange="this.form.submit()"
                            style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: white; font-size: 14px; color: #666; cursor: pointer; min-width: 150px;">
                    </div>
                </form>
            </div>
        </div>

        <div class="table-container">
            <h3>Purchase Order - Invoice Per Branch List<?php
            $filter_info = [];
            if (!empty($search_query))
                $filter_info[] = 'Search: "' . htmlspecialchars($search_query) . '"';
            if (!empty($status_filter)) {
                if ($status_filter === 'all') {
                    $filter_info[] = 'Status: All Status';
                } elseif ($status_filter === 'pending_incomplete') {
                    $filter_info[] = 'Status: Open & Incomplete';
                } else {
                    // Map status for display in filter info
                    $status_display_info = $status_filter;
                    if ($status_filter === 'Pending') {
                        $status_display_info = 'Open';
                    } elseif ($status_filter === 'Received') {
                        $status_display_info = 'Completed';
                    }
                    $filter_info[] = 'Status: ' . htmlspecialchars($status_display_info);
                }
            }
            if (!empty($supplier_filter)) {
                if ($supplier_filter === 'all') {
                    $filter_info[] = 'Supplier: All Suppliers';
                } else {
                    $filter_info[] = 'Supplier: ' . htmlspecialchars($supplier_filter);
                }
            }
            if (!empty($branch_filter)) {
                if ($branch_filter === 'all') {
                    $filter_info[] = 'Branch: All Branches';
                } else {
                    $filter_info[] = 'Branch: ' . htmlspecialchars($branch_filter);
                }
            }
            if (!empty($date_filter))
                $filter_info[] = 'Date: ' . date('m/d/Y', strtotime($date_filter));
            if (!empty($filter_info))
                echo ' - ' . implode(', ', $filter_info);
            ?></h3>
            <table>
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Supplier Company</th>
                        <th>Branch</th>
                        <th>PO Date</th>
                        <th>Total Item</th>
                        <th>Terms</th>
                        <th>Total Cost</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($po_result === null): ?>
                        <!-- No filter selected -->
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 8px;">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <path d="m21 21-4.35-4.35"></path>
                                        <line x1="11" y1="8" x2="11" y2="14"></line>
                                        <line x1="8" y1="11" x2="14" y2="11"></line>
                                    </svg>
                                    <div style="color: #333; font-size: 15px; font-weight: 600;">SELECT A FILTER TO DISPLAY THE DATA</div>
                                    <div style="color: #666; font-size: 13px;">Please select a status filter from the dropdown above to view purchase orders.</div>
                                </div>
                            </td>
                        </tr>
                    <?php elseif (!empty($processed_results)): ?>
                        <?php foreach ($processed_results as $row):
                            $status = $row['status'];
                            $badge_class = 'badge-default';
                            if (strcasecmp($status, 'Received') === 0)
                                $badge_class = 'badge-received';
                            elseif (strcasecmp($status, 'Completed') === 0)
                                $badge_class = 'badge-completed';
                            elseif (strcasecmp($status, 'Pending') === 0)
                                $badge_class = 'badge-pending';
                            elseif (strcasecmp($status, 'Incomplete') === 0)
                                $badge_class = 'badge-incomplete';
                            elseif (strcasecmp($status, 'CANCELED') === 0 || strcasecmp($status, 'Canceled') === 0 || strcasecmp($status, 'Cancelled') === 0)
                                $badge_class = 'badge-cancelled';
                            elseif (strcasecmp($status, 'Closed') === 0)
                                $badge_class = 'badge-closed';
                            
                            // Map status for display
                            $status_display = $status;
                            if (strcasecmp($status, 'Pending') === 0) {
                                $status_display = 'OPEN';
                            } elseif (strcasecmp($status, 'Received') === 0) {
                                $status_display = 'COMPLETED';
                            } elseif (strcasecmp($status, 'Closed') === 0) {
                                $status_display = 'CLOSED';
                            } elseif (strcasecmp($status, 'Canceled') === 0 || strcasecmp($status, 'Cancelled') === 0 || strcasecmp($status, 'CANCELED') === 0) {
                                $status_display = 'CANCELED';
                            } elseif (strcasecmp($status, 'Incomplete') === 0) {
                                $status_display = 'INCOMPLETE';
                            }
                            
                            $po_date_fmt = $row['po_date'] ? date('d/m/Y', strtotime($row['po_date'])) : '-';
                            $terms_display = $row['terms'] ? strtoupper($row['terms']) . (is_numeric($row['terms']) ? ' DAYS' : '') : '-';
                            $total_cost_fmt = '&#8369; ' . number_format((float) $row['total_cost'], 2);
                            ?>
                            <tr>
                                <td style="text-align:left; font-weight:600;"><?php echo htmlspecialchars($row['po_number']); ?>
                                </td>
                                <td style="text-align:left;"><?php echo htmlspecialchars($row['supplier_company'] ?? '-'); ?>
                                </td>
                                <td style="text-align:left; font-weight:600;"><?php echo htmlspecialchars($row['branch_name'] ?? '-'); ?>
                                </td>
                                <td><?php echo $po_date_fmt; ?></td>
                                <td><?php echo (int)($row['received_qty'] ?? 0) . '/' . (int)($row['total_qty'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($terms_display); ?></td>
                                <td><?php echo $total_cost_fmt; ?></td>
                                <td><span
                                        class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status_display); ?></span>
                                </td>
                                <td>
                                    <!-- Different View Button - No functionality yet -->
                                    <button class="btn-view" onclick="viewPOInventory(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['branch_name'] ?? '', ENT_QUOTES); ?>')">VIEW</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="no-data">
                                    <?php
                                    // Filter was applied but no results found
                                    echo "No purchase orders found";
                                    if (!empty($search_query))
                                        echo " matching \"" . htmlspecialchars($search_query) . "\"";
                                    if (!empty($status_filter))
                                        echo " with status \"" . htmlspecialchars($status_filter) . "\"";
                                    if (!empty($date_filter))
                                        echo " for date \"" . date('m/d/Y', strtotime($date_filter)) . "\"";
                                    
                                    // Add note about allocation requirement
                                    echo ".<br><small style='color: #999; font-size: 12px; margin-top: 8px; display: block;'>Note: Only purchase orders with branch allocations are shown here. Please allocate items to branches first in Purchase Order Details.</small>";
                                    ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- View PO Modal -->
    <div id="viewPOModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span>PO Invoice per branch - <span id="viewPOModalNumber">PO-XXXX-XXX</span></span>
                <span class="modal-close" onclick="closeViewPOModal()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Table 1: PO Information -->
                <div class="modal-table-section">
                    <h4>Purchase Order Information</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Supplier</th>
                                <th>Brand</th>
                                <th>PO Date</th>
                                <th>Terms</th>
                                <th>Total Cost</th>
                                <th>Total Cost Received</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="viewPOInfoTableBody">
                            <tr>
                                <td colspan="8" style="text-align:center; padding:20px;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table 2: Branch Allocations -->
                <div class="modal-table-section" style="margin-top: 20px;">
                    <h4>Branch Allocations</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Invoice Number</th>
                                <th>DR Date</th>
                                <th>DR Received</th>
                                <th>Received By</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="viewPOItemsTableBody">
                            <tr>
                                <td colspan="6" style="text-align:center; padding:20px;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Cancellation Details (shown only for canceled POs) -->
                <div id="cancelDetailsSection" class="modal-table-section" style="margin-top: 20px; display: none;">
                    <h4>Cancellation Details</h4>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 20%;">Canceled By</th>
                                <th style="width: 15%;">Canceled At</th>
                                <th style="width: 10%;">Branch</th>
                                <th style="width: 55%;">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td id="canceledBy" style="text-align: left;">-</td>
                                <td id="canceledAt">-</td>
                                <td id="canceledByBranch">-</td>
                                <td id="cancelReason" style="text-align: left; white-space: pre-wrap; word-wrap: break-word;">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-back-modal" onclick="closeViewPOModal()">Back</button>
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
            allSections.forEach(function(s) {
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

        // View button for inventory per branch - Filters by logged-in user's branch
        function viewPOInventory(id, branchName) {
            const poId = id;
            const userBranch = branchName; // The branch passed from the table row (user's branch)
            
            // Show loading state for modal tables
            document.getElementById('viewPOInfoTableBody').innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px;">Loading...</td></tr>';
            document.getElementById('viewPOItemsTableBody').innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px;">Loading...</td></tr>';
            
            // Show modal
            document.getElementById('viewPOModal').style.display = 'flex';
            
            // Fetch PO data and branch data together to calculate proper status
            Promise.all([
                fetch(`get_po_info.php?po_id=${poId}`).then(response => response.json()),
                fetch(`get_po_branch_details.php?po_id=${poId}`).then(response => response.json()),
                fetch(`get_total_cost_received.php?po_id=${poId}`).then(response => response.json())
            ])
                .then(([poData, branchData, costData]) => {
                    if (poData.success) {
                        const po = poData.po;
                        const termsDisplay = po.terms.toLowerCase() === 'cod' ? 'Cash on Delivery' : po.terms + ' Days';
                        
                        // Set modal title
                        document.getElementById('viewPOModalNumber').textContent = po.po_number;
                        
                        // Check if PO is canceled and show cancellation details
                        const isCanceled = po.status.toLowerCase() === 'canceled' || po.status.toLowerCase() === 'cancelled';
                        const cancelDetailsSection = document.getElementById('cancelDetailsSection');
                        
                        if (isCanceled && po.canceled_by) {
                            // Show cancellation details
                            cancelDetailsSection.style.display = 'block';
                            
                            // Format canceled at date
                            let canceledAtFormatted = '-';
                            if (po.canceled_at) {
                                const cancelDate = new Date(po.canceled_at);
                                canceledAtFormatted = cancelDate.toLocaleDateString('en-GB') + ' ' + 
                                                     cancelDate.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                            }
                            
                            document.getElementById('canceledBy').textContent = po.canceled_by || '-';
                            document.getElementById('canceledAt').textContent = canceledAtFormatted;
                            document.getElementById('canceledByBranch').textContent = po.canceled_by_branch || '-';
                            document.getElementById('cancelReason').textContent = po.cancel_reason || '-';
                        } else {
                            // Hide cancellation details
                            cancelDetailsSection.style.display = 'none';
                        }
                        
                        // Calculate overall status based on ALL branch allocations
                        let calculatedStatus = po.status; // Default to database status
                        let calculatedStatusDisplay = po.status;
                        let badgeClass = 'badge-default';
                        
                        // If PO is Canceled, keep it as Canceled
                        if (po.status.toLowerCase() === 'canceled' || po.status.toLowerCase() === 'cancelled') {
                            calculatedStatus = 'Canceled';
                            calculatedStatusDisplay = 'Canceled';
                            badgeClass = 'badge-cancelled';
                        } else if (po.status.toLowerCase() === 'closed') {
                            // If PO is Closed, keep it as Closed
                            calculatedStatus = 'Closed';
                            calculatedStatusDisplay = 'Closed';
                            badgeClass = 'badge-closed';
                        } else if (branchData.success && branchData.branches.length > 0) {
                            const orderQty = parseInt(po.total_qty || 0, 10);
                            const allocatedQty = parseInt(po.total_allocated || 0, 10);
                            const branches = branchData.branches;
                            const allCompleted = branches.every(branch => branch.status === 'Completed');
                            const allWaiting = branches.every(branch => branch.status === 'Waiting');
                            const someReceived = branches.some(branch => branch.status === 'Completed' || branch.status === 'Incomplete');

                            if (orderQty > allocatedQty) {
                                calculatedStatus = 'Incomplete';
                                calculatedStatusDisplay = 'Incomplete';
                                badgeClass = 'badge-incomplete';
                            } else if (allCompleted) {
                                calculatedStatus = 'Received';
                                calculatedStatusDisplay = 'Completed';
                                badgeClass = 'badge-completed';
                            } else if (allWaiting) {
                                calculatedStatus = 'Pending';
                                calculatedStatusDisplay = 'Waiting';
                                badgeClass = 'badge-waiting';
                            } else if (someReceived) {
                                calculatedStatus = 'Incomplete';
                                calculatedStatusDisplay = 'Incomplete';
                                badgeClass = 'badge-incomplete';
                            }
                        } else {
                            // No branches allocated yet - map database status to display
                            if (po.status === 'Pending') {
                                calculatedStatusDisplay = 'Waiting';
                                badgeClass = 'badge-waiting';
                            } else if (po.status === 'Received') {
                                calculatedStatusDisplay = 'Completed';
                                badgeClass = 'badge-completed';
                            } else if (po.status === 'Incomplete') {
                                calculatedStatusDisplay = 'Incomplete';
                                badgeClass = 'badge-incomplete';
                            }
                        }
                        
                        const totalCostReceived = costData.success ? costData.total_cost_received : 0;
                        
                        // Format brand display
                        let brandDisplay = '-';
                        if (po.selected_brands) {
                            try {
                                const brands = JSON.parse(po.selected_brands);
                                if (Array.isArray(brands) && brands.length > 0) {
                                    brandDisplay = brands.join(', ');
                                }
                            } catch (e) {
                                brandDisplay = '-';
                            }
                        }
                        
                        // Populate PO Info Table with calculated status
                        document.getElementById('viewPOInfoTableBody').innerHTML = `
                            <tr>
                                <td style="font-weight:600;">${po.po_number}</td>
                                <td>${po.supplier_company}</td>
                                <td>${brandDisplay}</td>
                                <td>${new Date(po.po_date).toLocaleDateString('en-GB')}</td>
                                <td>${termsDisplay}</td>
                                <td>&#8369; ${parseFloat(po.total_cost).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}</td>
                                <td>&#8369; ${parseFloat(totalCostReceived).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}</td>
                                <td><span class="badge ${badgeClass}">${calculatedStatusDisplay}</span></td>
                            </tr>
                        `;
                        
                        // FILTER Branch Allocations: Only show the logged-in user's branch
                        if (branchData.success && branchData.branches.length > 0) {
                            console.log('Number of branches:', branchData.branches.length);
                            console.log('Filtering for branch:', userBranch);
                            
                            // Filter branches to only show the user's branch
                            const filteredBranches = branchData.branches.filter(branch => 
                                branch.branch_name.trim().toLowerCase() === userBranch.trim().toLowerCase()
                            );
                            
                            console.log('Filtered branches:', filteredBranches.length);
                            
                            if (filteredBranches.length > 0) {
                                let branchHTML = '';
                                filteredBranches.forEach(branch => {
                                    // Main branch row
                                    branchHTML += `
                                        <tr>
                                            <td style="font-weight:600; text-align:left !important;">${branch.branch_name}</td>
                                            <td style="text-align:left !important;">${branch.invoice_number}</td>
                                            <td style="text-align:left !important;">${branch.dr_date}</td>
                                            <td>${branch.dr_received}</td>
                                            <td style="text-align:left !important;">${branch.received_by}</td>
                                            <td><span class="badge badge-${branch.status.toLowerCase()}">${branch.status}</span></td>
                                        </tr>
                                    `;
                                    // Remarks row (below the main row)
                                    const remarksText = (branch.receiving_remarks === '-' || !branch.receiving_remarks) ? '' : branch.receiving_remarks;
                                    branchHTML += `
                                        <tr>
                                            <td colspan="6" style="padding:8px 12px; background-color:#f9f9f9; border-top:none; text-align:left !important;">
                                                <strong style="color:#666; font-size:12px;">Remarks:</strong> 
                                                <span style="font-size:12px; color:#333;">${remarksText}</span>
                                            </td>
                                        </tr>
                                    `;
                                });
                                document.getElementById('viewPOItemsTableBody').innerHTML = branchHTML;
                            } else {
                                // No allocation found for this branch
                                document.getElementById('viewPOItemsTableBody').innerHTML = '<tr><td colspan="6" style="text-align:center; color:#999; padding:20px;">No allocations found for your branch</td></tr>';
                            }
                        } else {
                            console.log('No branches found or error');
                            document.getElementById('viewPOItemsTableBody').innerHTML = '<tr><td colspan="6" style="text-align:center; color:#999; padding:20px;">No branch allocations found</td></tr>';
                        }
                    } else {
                        document.getElementById('viewPOInfoTableBody').innerHTML = '<tr><td colspan="8" style="text-align:center; color:red; padding:20px;">Error loading PO data</td></tr>';
                        document.getElementById('viewPOItemsTableBody').innerHTML = '<tr><td colspan="6" style="text-align:center; color:red; padding:20px;">Error loading data</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('viewPOInfoTableBody').innerHTML = '<tr><td colspan="8" style="text-align:center; color:red; padding:20px;">Error loading data</td></tr>';
                    document.getElementById('viewPOItemsTableBody').innerHTML = '<tr><td colspan="6" style="text-align:center; color:red; padding:20px;">Error loading data</td></tr>';
                });
        }

        function closeViewPOModal() {
            document.getElementById('viewPOModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const viewPOModal = document.getElementById('viewPOModal');
            
            if (event.target == viewPOModal) {
                closeViewPOModal();
            }
        }

        // Add loading state for filters
        document.addEventListener('DOMContentLoaded', function () {
            const statusSelect = document.querySelector('select[name="status"]');
            const dateInput = document.querySelector('input[name="date"]');

            if (statusSelect) {
                statusSelect.addEventListener('change', function () {
                    // Add loading state
                    this.style.opacity = '0.6';
                    this.style.cursor = 'wait';

                    // Submit the form
                    this.form.submit();
                });
            }

            if (dateInput) {
                dateInput.addEventListener('change', function () {
                    // Add loading state
                    this.style.opacity = '0.6';
                    this.style.cursor = 'wait';

                    // Submit the form
                    this.form.submit();
                });
            }
        });
    </script>
</body>

</html>
