<?php
require_once 'session_check.php';
include 'config.php';
require_once 'po_status_helpers.php';

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

// Handle search and filter functionality
$search_query = '';
$status_filter = '';
$date_filter = '';
$where_conditions = [];

// Branch filtering
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

// Only Super-Admin can see all branches
// Sub-admin should be restricted to their assigned branches
if (strcasecmp($system_level, 'Super-Admin') !== 0) {
    if (!empty($user_branch)) {
        // Handle multiple branches (comma-separated)
        $branch_names = array_map('trim', explode(',', $user_branch));
        $branch_codes = [];
        
        foreach ($branch_names as $branch_name) {
            $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($branch_name) . "' LIMIT 1");
            if ($branch_query && $branch_query->num_rows > 0) {
                $branch_codes[] = "'" . $conn->real_escape_string($branch_query->fetch_assoc()['branch_code']) . "'";
            }
        }
        
        if (!empty($branch_codes)) {
            $where_conditions[] = "created_by_branch IN (" . implode(', ', $branch_codes) . ")";
        } else {
            // User doesn't have any valid branches mapped
            $where_conditions[] = "1 = 0";
        }
    } else {
        // User has no branch set
        $where_conditions[] = "1 = 0";
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

// Brand filter
$brand_filter = '';
if (isset($_GET['brand']) && !empty(trim($_GET['brand']))) {
    $brand_filter = trim($_GET['brand']);
    // Only add to WHERE conditions if not "all"
    if ($brand_filter !== 'all') {
        $brand_escaped = $conn->real_escape_string($brand_filter);
        // Filter by brand - check if the brand is in the selected_brands JSON array
        $where_conditions[] = "JSON_CONTAINS(selected_brands, '\"$brand_escaped\"')";
    }
}

// Only execute query if a status filter is selected
$purchase_orders_result = null;
$has_status_filter = isset($_GET['status']) && !empty(trim($_GET['status']));

// Fetch unique suppliers for the dropdown filter
$suppliers_list = [];
$branch_filter_for_suppliers = '';
if (strcasecmp($system_level, 'Super-Admin') !== 0 && !empty($branch_codes)) {
    $branch_filter_for_suppliers = " WHERE created_by_branch IN (" . implode(', ', $branch_codes) . ")";
}
$suppliers_query = $conn->query("SELECT DISTINCT supplier_company FROM purchase_orders" . $branch_filter_for_suppliers . " ORDER BY supplier_company ASC");
if ($suppliers_query) {
    while ($supplier_row = $suppliers_query->fetch_assoc()) {
        if (!empty($supplier_row['supplier_company'])) {
            $suppliers_list[] = $supplier_row['supplier_company'];
        }
    }
}

// Fetch unique brands for the dropdown filter
$brands_list = [];
$brands_query = $conn->query("SELECT DISTINCT selected_brands FROM purchase_orders WHERE selected_brands IS NOT NULL AND selected_brands != ''");
if ($brands_query) {
    $brands_set = [];
    while ($brand_row = $brands_query->fetch_assoc()) {
        $brands_array = json_decode($brand_row['selected_brands'], true);
        if (is_array($brands_array)) {
            foreach ($brands_array as $brand) {
                $brands_set[$brand] = true;
            }
        }
    }
    $brands_list = array_keys($brands_set);
    sort($brands_list);
}

if ($has_status_filter) {
    $status_filter = trim($_GET['status']);
    $status_escaped = $conn->real_escape_string($status_filter);
    
    // Build WHERE clause
    if ($status_filter !== 'all') {
        $where_conditions[] = "status = '$status_escaped'";
    }
    
    if (isset($_GET['date']) && !empty(trim($_GET['date']))) {
        $date_filter = trim($_GET['date']);
        $date_escaped = $conn->real_escape_string($date_filter);
        $where_conditions[] = "DATE(po_date) = '$date_escaped'";
    }

    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = " WHERE " . implode(' AND ', $where_conditions);
    }

    // Fetch Purchase Orders - received_qty will be calculated in PHP to match viewpurchaseorder.php exactly
    $sql = "SELECT 
                po.id, 
                po.po_number, 
                po.supplier_company, 
                po.brand_type,
                po.selected_brands,
                po.po_date, 
                po.total_items,
                po.total_qty,
                po.terms, 
                po.total_cost, 
                po.status
            FROM purchase_orders po
            " . $where_clause . "
            ORDER BY po.po_date DESC, po.id DESC";

    $purchase_orders_result = $conn->query($sql);
} else {
    // No filter selected - don't execute query
    $purchase_orders_result = null;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Brand Colors - Matching Login Theme */
            --color-navy: #0d3347;
            --color-navy-dark: #081f2d;
            --color-navy-light: #164460;
            --color-gold: #b08a52;
            --color-gold-light: #c9a46e;
            --color-gold-pale: #f5ede0;
            
            /* Action Button Colors */
            --color-green: #2e7d32;
            --color-green-dark: #1b5e20;
            --color-green-light: #43a047;
            
            /* Background Colors */
            --bg-form-panel: #faf8f5;
            --bg-input: #f0ebe3;
            --bg-input-focus: #ffffff;
            
            /* Text Colors */
            --text-heading: #0d3347;
            --text-gold: #b08a52;
            --text-body: #9a9086;
            --text-muted: #7a7068;
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

        .menu-section {
            margin-bottom: 5px;
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
            color: #333;
            margin: 0;
        }

        .btn-create-po {
            padding: 10px 20px;
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-create-po:hover {
            background-color: var(--color-gold-light);
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

        .btn-clear {
            padding: 9px 15px;
            background: #666;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            margin-left: 5px;
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
            color: #000;
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
            background: #fafafa;
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

        .badge-complete {
            background: #d4edda;
            color: #1a7a35;
            border: 1px solid #b8dfc6;
        }

        .badge-completed {
            background: #d4edda;
            color: #1a7a35;
            border: 1px solid #b8dfc6;
        }

        .badge-waiting {
            background: #cfe2ff;
            color: #084298;
            border: 1px solid #b6d4fe;
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

        .badge-default {
            background: #e2e3e5;
            color: #41464b;
            border: 1px solid #d3d6d8;
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

        /* Action buttons in table */
        .btn-view {
            padding: 5px 14px;
            background: var(--color-navy);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 4px;
        }

        .btn-view:hover {
            background: var(--color-navy-dark);
        }

        .btn-edit {
            padding: 5px 14px;
            background: var(--color-gold);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 4px;
        }

        .btn-edit:hover {
            background: var(--color-gold-light);
        }

        .btn-modify {
            padding: 5px 14px;
            background: var(--color-gold);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-modify:hover {
            background: var(--color-gold-light);
        }

        .btn-details {
            padding: 5px 14px;
            background: var(--color-green);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 4px;
        }

        .btn-details:hover {
            background: var(--color-green-dark);
        }

        /* Media Queries for Responsiveness */

        /* Large Desktop & Laptop (max-width: 1640px) */
        @media (max-width: 1640px) {
            .filter-bar {
                gap: 12px;
            }
        }

        /* Medium Desktop (max-width: 1366px) */
        @media (max-width: 1366px) {
            .search-group {
                flex: 1;
            }

            .filters-right {
                flex: 1;
            }

            .dropdown {
                flex: 1;
                min-width: 0;
            }

            .dropdown select,
            .dropdown input[type="date"] {
                width: 100%;
            }
        }

        /* Tablet & Smaller Desktop (max-width: 1024px) */
        @media (max-width: 1024px) {
            .main-content {
                padding: 15px;
            }

            .content-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
            }

            .filter-bar {
                padding: 12px;
                gap: 12px;
            }

            .search-group,
            .filters-right {
                flex-wrap: wrap;
            }

            .table-container {
                padding: 20px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                min-width: 1000px;
            }

            th, td {
                font-size: 12px;
                padding: 10px 8px;
                white-space: nowrap;
            }

            .btn-view,
            .btn-edit,
            .btn-details,
            .btn-modify {
                padding: 4px 10px;
                font-size: 11px;
            }
        }

        /* Small Tablet (max-width: 960px) */
        @media (max-width: 960px) {
            .filter-bar {
                gap: 10px;
            }

            .filters-right {
                flex-wrap: wrap;
            }

            .dropdown select,
            .dropdown input[type="date"] {
                font-size: 15px;
                padding: 10px 12px;
            }

            .table-container {
                padding: 15px;
            }

            table {
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
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                padding: 12px;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .header::after {
                left: 0;
            }

            .content-header {
                flex-direction: column;
                align-items: stretch;
            }

            .content-header h2 {
                font-size: 18px;
            }

            .btn-create-po {
                width: 100%;
                padding: 12px 20px;
                font-size: 15px;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                padding: 12px;
            }

            .search-group {
                flex-direction: row;
                width: 100%;
                gap: 10px;
            }

            .search-input-wrapper {
                width: 100%;
                flex: 1;
            }

            .search-input-wrapper input {
                width: 100%;
                font-size: 16px;
                padding: 12px 12px 12px 40px;
            }

            .btn-search {
                padding: 12px 20px;
                font-size: 15px;
                white-space: nowrap;
                flex-shrink: 0;
            }

            .btn-clear {
                width: 100%;
                display: block !important;
                text-align: center;
                margin-left: 0 !important;
                padding: 12px 20px !important;
            }

            .filters-right {
                flex-direction: row;
                gap: 10px;
                width: 100%;
            }

            .dropdown {
                flex: 1;
            }

            .dropdown select,
            .dropdown input[type="date"] {
                width: 100%;
                min-width: unset;
                font-size: 16px;
                padding: 12px;
            }

            .table-container {
                padding: 12px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .table-container h3 {
                font-size: 15px;
                margin-bottom: 15px;
            }

            table {
                min-width: 900px;
                zoom: 0.75;
            }

            th, td {
                font-size: 12px;
                padding: 10px 8px;
            }

            .badge {
                font-size: 10px;
                padding: 3px 8px;
            }

            .btn-view,
            .btn-edit,
            .btn-details,
            .btn-modify {
                padding: 4px 8px;
                font-size: 10px;
                margin-right: 2px;
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

            .content-header h2 {
                font-size: 16px;
            }

            .btn-create-po {
                font-size: 14px;
                padding: 10px 16px;
            }

            .filter-bar {
                padding: 10px;
                gap: 10px;
            }

            .search-group {
                flex-direction: row;
                gap: 8px;
            }

            .search-input-wrapper input {
                font-size: 15px;
                padding: 10px 10px 10px 38px;
            }

            .btn-search {
                font-size: 14px;
                padding: 10px 16px;
                flex-shrink: 0;
            }

            .btn-clear {
                font-size: 14px;
                padding: 10px 16px !important;
            }

            .filters-right {
                flex-direction: row;
                gap: 8px;
            }

            .dropdown select,
            .dropdown input[type="date"] {
                font-size: 15px;
                padding: 10px;
            }

            .table-container {
                padding: 10px;
            }

            .table-container h3 {
                font-size: 14px;
                margin-bottom: 12px;
            }

            table {
                min-width: 800px;
                zoom: 0.7;
            }

            th, td {
                font-size: 11px;
                padding: 8px 6px;
            }

            .badge {
                font-size: 9px;
                padding: 2px 6px;
            }

            .btn-view,
            .btn-edit,
            .btn-details,
            .btn-modify {
                padding: 3px 6px;
                font-size: 9px;
            }
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
            <h2>Purchase Order</h2>
            <button class="btn-create-po" onclick="window.location.href='createpurchaseorder.php'">Create PO</button>
        </div>

        <div class="filter-bar">
            <form method="GET" action="" style="display: contents;">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
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
                    <?php if (!empty($search_query) || !empty($status_filter) || !empty($date_filter) || !empty($supplier_filter) || !empty($brand_filter)): ?>
                        <a href="purchaseorder.php" class="btn-clear">Clear All</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="filters-right">
                
                

                <!-- Status Filter -->
                <form method="GET" action="" style="display: inline;">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    <input type="hidden" name="supplier" value="<?php echo htmlspecialchars($supplier_filter); ?>">
                    <input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand_filter); ?>">
                    <div class="dropdown">
                        <select name="status" onchange="this.form.submit()"
                            style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: white; font-size: 14px; color: #666; cursor: pointer; min-width: 150px;">
                            <option value="">Select Status</option>
                            <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>
                                Open</option>
                            <option value="Incomplete" <?php echo ($status_filter === 'Incomplete') ? 'selected' : ''; ?>>
                                Incomplete</option>
                            <option value="Received" <?php echo ($status_filter === 'Received') ? 'selected' : ''; ?>>
                                Completed</option>
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
                    <input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand_filter); ?>">
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

                <!-- Brand Filter -->
                <form method="GET" action="" style="display: inline;">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    <input type="hidden" name="supplier" value="<?php echo htmlspecialchars($supplier_filter); ?>">
                    <div class="dropdown">
                        <select name="brand" onchange="this.form.submit()"
                            style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: white; font-size: 14px; color: #666; cursor: pointer; min-width: 180px;">
                            <option value="">Select Brand</option>
                            <option value="all" <?php echo ($brand_filter === 'all') ? 'selected' : ''; ?>>All Brands</option>
                            <?php foreach ($brands_list as $brand): ?>
                                <option value="<?php echo htmlspecialchars($brand); ?>" 
                                    <?php echo ($brand_filter === $brand) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($brand); ?>
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
                    <input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand_filter); ?>">
                    <div class="dropdown">
                        <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>"
                            onchange="this.form.submit()"
                            style="padding: 6.3px 12px; border: 1px solid #ddd; border-radius: 4px; background: white; font-size: 14px; color: #666; cursor: pointer; min-width: 150px;">
                    </div>
                </form>
            </div>
        </div>

        <div class="table-container">
            <h3>Purchase Order List<?php
            $filter_info = [];
            if (!empty($search_query))
                $filter_info[] = 'Search: "' . htmlspecialchars($search_query) . '"';
            if (!empty($status_filter))
                $filter_info[] = 'Status: ' . htmlspecialchars($status_filter);
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
                        <th>Brand</th>
                        <th>PO Date</th>
                        <th>Total Item</th>
                        <th>Terms</th>
                        <th>Total Cost</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($purchase_orders_result === null): ?>
                        <!-- No filter selected -->
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="16" x2="12" y2="12"></line>
                                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                    </svg>
                                    <div style="color: #333; font-size: 15px; font-weight: 600;">SELECT A FILTER TO DISPLAY THE DATA</div>
                                    <div style="color: #666; font-size: 13px;">Please select a status filter from the dropdown above to view purchase orders.</div>
                                </div>
                            </td>
                        </tr>
                    <?php elseif ($purchase_orders_result && $purchase_orders_result->num_rows > 0): ?>
                        <?php while ($row = $purchase_orders_result->fetch_assoc()):
                            // Calculate received_qty exactly like viewpurchaseorder.php
                            $po_id = (int)$row['id'];
                            $items_query = $conn->query("
                                SELECT poi.*, 
                                       MAX(i.has_serial) as has_serial
                                FROM purchase_order_items poi 
                                LEFT JOIN items i ON poi.family_code = i.family_code 
                                WHERE poi.po_id = $po_id 
                                GROUP BY poi.id
                            ");
                            
                            $total_received_qty = 0;
                            $calculated_total_cost = 0;
                            if ($items_query) {
                                while ($item = $items_query->fetch_assoc()) {
                                    $received_count = 0;
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
                                        $received_count = (int)($item['received_qty'] ?? 0);
                                    }
                                    $total_received_qty += $received_count;
                                    
                                    // Calculate the total cost from items, exactly like viewpurchaseorder.php
                                    $calculated_total_cost += (float)($item['total'] ?? 0);
                                }
                            }
                            
                            // Calculate overall PO status using order qty, allocations, and received totals
                            $status = $row['status'];
                            $po_totals = get_po_quantity_totals($conn, $po_id);
                            $total_allocated = $po_totals['total_allocated'];
                            $total_received = $po_totals['total_received'];
                            $total_order_qty = $po_totals['total_order_qty'];

                            if (strcasecmp($status, 'Canceled') !== 0 
                                && strcasecmp($status, 'Cancelled') !== 0 
                                && strcasecmp($status, 'Closed') !== 0
                                && strcasecmp($status, 'Incomplete') !== 0
                                && strcasecmp($status, 'Received') !== 0
                                && strcasecmp($status, 'Completed') !== 0) {
                                $status = calculate_po_workflow_status($po_totals, $status);
                            }

                            $display_received = $total_allocated > 0 ? $total_received : $total_received_qty;
                            $display_total = $total_order_qty > 0 ? $total_order_qty : ($total_allocated > 0 ? $total_allocated : (int)($row['total_qty'] ?? 0));
                            
                            $badge_class = 'badge-default';
                            if (strcasecmp($status, 'Received') === 0)
                                $badge_class = 'badge-received';
                            elseif (strcasecmp($status, 'Completed') === 0)
                                $badge_class = 'badge-completed';
                            elseif (strcasecmp($status, 'Pending') === 0)
                                $badge_class = 'badge-pending';
                            elseif (strcasecmp($status, 'Incomplete') === 0)
                                $badge_class = 'badge-incomplete';
                            elseif (strcasecmp($status, 'Canceled') === 0 || strcasecmp($status, 'Cancelled') === 0)
                                $badge_class = 'badge-cancelled';
                            elseif (strcasecmp($status, 'Closed') === 0)
                                $badge_class = 'badge-closed';
                            elseif (strcasecmp($status, 'Decline') === 0 || strcasecmp($status, 'Declined') === 0)
                                $badge_class = 'badge-decline';
                            
                            // Map status for display
                            $status_display = $status;
                            if (strcasecmp($status, 'Pending') === 0) {
                                $status_display = 'Open';
                            } elseif (strcasecmp($status, 'Received') === 0) {
                                $status_display = 'Completed';
                            } elseif (strcasecmp($status, 'Canceled') === 0 || strcasecmp($status, 'Cancelled') === 0) {
                                $status_display = 'Canceled';
                            } elseif (strcasecmp($status, 'Closed') === 0) {
                                $status_display = 'Closed';
                            } elseif (strcasecmp($status, 'Incomplete') === 0) {
                                $status_display = 'Incomplete';
                            }
                            
                            $po_date_fmt = $row['po_date'] ? date('d/m/Y', strtotime($row['po_date'])) : '-';
                            $terms_display = $row['terms'] ? strtoupper($row['terms']) . (is_numeric($row['terms']) ? ' DAYS' : '') : '-';
                            // Use the calculated total cost from items instead of the stored value
                            $total_cost_fmt = '&#8369; ' . number_format($calculated_total_cost, 2);
                            
                            // Format brand display
                            $brand_display = '-';
                            if (!empty($row['brand_type']) && !empty($row['selected_brands'])) {
                                $selected_brands = json_decode($row['selected_brands'], true);
                                if (is_array($selected_brands) && count($selected_brands) > 0) {
                                    // Show all brand names separated by commas
                                    $brand_display = htmlspecialchars(implode(', ', $selected_brands));
                                }
                            }
                            ?>
                            <tr>
                                <td style="text-align:left; font-weight:600;"><?php echo htmlspecialchars($row['po_number']); ?>
                                </td>
                                <td style="text-align:left;"><?php echo htmlspecialchars($row['supplier_company'] ?? '-'); ?>
                                </td>
                                <td><?php echo $brand_display; ?>
                                </td>
                                <td><?php echo $po_date_fmt; ?></td>
                                <td><?php echo $display_received . '/' . $display_total; ?></td>
                                <td><?php echo htmlspecialchars($terms_display); ?></td>
                                <td><?php echo $total_cost_fmt; ?></td>
                                <td><span
                                        class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status_display); ?></span>
                                </td>
                                <td>
                                    <button class="btn-view" onclick="viewPO(<?php echo $row['id']; ?>)">VIEW</button>
                                    <?php if (strcasecmp($status, 'Canceled') === 0 || strcasecmp($status, 'Cancelled') === 0 || strcasecmp($status, 'Closed') === 0): ?>
                                        <button class="btn-details" disabled style="background: #ccc; cursor: not-allowed;">DETAILS</button>
                                    <?php else: ?>
                                        <button class="btn-details" onclick="detailsPO(<?php echo $row['id']; ?>)">DETAILS</button>
                                    <?php endif; ?>
                                    <?php if (strcasecmp($status, 'Completed') === 0 || strcasecmp($status, 'Canceled') === 0 || strcasecmp($status, 'Cancelled') === 0 || strcasecmp($status, 'Closed') === 0): ?>
                                        <button class="btn-edit" disabled
                                            style="background: #ccc; cursor: not-allowed;">EDIT</button>
                                    <?php else: ?>
                                        <button class="btn-edit" onclick="editPO(<?php echo $row['id']; ?>)">EDIT</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center !important; padding: 40px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc; color: #666;">
                                <?php
                                if (!empty($search_query) || !empty($status_filter) || !empty($date_filter)) {
                                    echo "No purchase orders found";
                                    if (!empty($search_query))
                                        echo " matching \"" . htmlspecialchars($search_query) . "\"";
                                    if (!empty($status_filter) && $status_filter !== 'all')
                                        echo " with status \"" . htmlspecialchars($status_filter) . "\"";
                                    if (!empty($date_filter))
                                        echo " for date \"" . date('m/d/Y', strtotime($date_filter)) . "\"";
                                } else {
                                    echo "No purchase orders found";
                                }
                                ?>
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

            // On mobile, toggle the hidden class
            // On desktop, the sidebar is always visible
            if (window.innerWidth <= 768) {
                if (sidebar.classList.contains('hidden')) {
                    sidebar.classList.remove('hidden');
                    sidebar.style.transform = 'translateX(0)';
                } else {
                    sidebar.classList.add('hidden');
                    sidebar.style.transform = 'translateX(-100%)';
                }
            } else {
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('expanded');
            }
            
            menuBtn.classList.toggle('active');
        }

        // Initialize sidebar state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            if (window.innerWidth <= 768) {
                // On mobile, start with sidebar hidden
                sidebar.classList.add('hidden');
                sidebar.style.transform = 'translateX(-100%)';
            }
        });

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

        function viewPO(id) {
            const poId = id;
            
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
                        
                        // Populate branch details table
                        if (branchData.success && branchData.branches.length > 0) {
                            console.log('Number of branches:', branchData.branches.length);
                            let branchHTML = '';
                            branchData.branches.forEach(branch => {
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

        function detailsPO(id) {
            window.location.href = 'purchaseorder-details.php?id=' + id + '&from=purchaseorder';
        }

        function editPO(id) {
            window.location.href = 'editpurchaseorder.php?id=' + id + '&from=purchaseorder';
        }


        function modifyPO(id) {
            window.location.href = 'modificationrpo.php?id=' + id;
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