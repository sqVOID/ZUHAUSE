<?php
require_once 'session_check.php';
include 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to Super-Admin only
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
if (strcasecmp($system_level, 'Super-Admin') !== 0) {
    // Redirect non-Super-Admin users to the report page
    header("Location: report.php");
    exit();
}

// Get logged in user's branch code
$branch_code = '000';
$user_branch_name = '';
if (isset($_SESSION['user_branch'])) {
    $user_branch_name = $_SESSION['user_branch'];
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch_name'");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Claim Item Modification</title>

    <!-- Prevent flash of table when loading detail view -->
    <script>
        (function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('invoice')) {
                // Add style to hide list view immediately before page renders
                document.write('<style id="preload-hide">#listView { display: none !important; }</style>');
            }
        })();
    </script>

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

        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background-color: white;
            display: flex;
            justify-content: flex-start;
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
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: 15px;
            margin-bottom: 10px;
        }

        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        /* Search bar */
        .search-bar-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }

        .search-controls {
            display: flex;
            align-items: center;
        }

        .date-filters {
            display: flex;
            align-items: center;
            margin-left: auto;
        }

        .search-bar-wrapper input {
            padding: 9px 14px;
            border: 1px solid #ccc;
            border-radius: 4px 0 0 4px;
            font-size: 14px;
            width: 280px;
            outline: none;
            font-family: Arial, sans-serif;
            background: white;
        }

        .search-bar-wrapper input[type="date"] {
            padding: 9px 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            width: 150px;
            outline: none;
            font-family: Arial, sans-serif;
            background: white;
            margin-left: 5px;
        }

        .search-bar-wrapper input[type="date"]:focus {
            border-color: #2196F3;
        }

        .search-bar-wrapper select {
            padding: 9px 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            outline: none;
            font-family: Arial, sans-serif;
            background: white;
            margin-left: 10px;
            min-width: 200px;
        }

        .search-bar-wrapper select:focus {
            border-color: #2196F3;
        }

        .search-bar-wrapper input:focus {
            border-color: #2196F3;
        }

        .search-bar-wrapper button {
            padding: 9px 16px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.2s;
        }

        .search-bar-wrapper button:hover {
            background-color: var(--color-navy-dark);
        }

        .search-bar-wrapper button svg {
            width: 16px;
            height: 16px;
            fill: white;
        }

        /* Table container */
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 20px;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ccc;
        }

        .report-table thead {
            background: var(--color-gold-pale);
        }

        .report-table th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border: 1px solid #ccc;
        }

        .report-table td {
            padding: 10px 12px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
            vertical-align: middle;
        }

        .report-table tbody tr:hover {
            background-color: #fdf8f3;
        }

        .report-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        .report-table tbody tr:nth-child(even):hover {
            background-color: #fdf8f3;
        }

        .no-data td {
            color: #999;
            font-style: italic;
            padding: 20px;
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-claimed {
            background-color: #c8e6c9;
            color: #2e7d32;
        }

        .status-unclaimed {
            background-color: #ffcdd2;
            color: #c62828;
        }

        /* Action buttons */
        .action-btns {
            display: flex;
            gap: 6px;
            justify-content: center;
            align-items: center;
        }

        .btn-modify {
            padding: 5px 14px;
            background-color: #f57c00;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-modify:hover {
            background-color: #e65100;
        }

        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 6px;
            margin-top: 14px;
        }

        .pagination-wrapper span {
            font-size: 13px;
            color: #555;
            margin-right: 6px;
        }

        .page-btn {
            padding: 5px 11px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 13px;
            color: #333;
            transition: background 0.2s;
        }

        .page-btn:hover {
            background: #e0e0e0;
            border-color: #333333;
        }

        .page-btn.active {
            background: #333333;
            color: white;
            border-color: #333333;
        }

        /* Alert messages */
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 9999;
            min-width: 400px;
            max-width: 600px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 600px;
            max-width: 90%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 20px 25px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .close-modal {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
            transition: color 0.2s;
        }

        .close-modal:hover,
        .close-modal:focus {
            color: #000;
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            font-family: Arial, sans-serif;
            outline: none;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #4caf50;
        }

        .form-group input:disabled {
            background-color: #f5f5f5;
            color: #666;
            cursor: not-allowed;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .modal-footer {
            padding: 15px 25px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 8px 8px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-cancel {
            padding: 9px 20px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .btn-cancel:hover {
            background-color: #5a6268;
        }

        .btn-save {
            padding: 9px 20px;
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .btn-save:hover {
            background-color: var(--color-gold-light);
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        /* Item card styles */
        .item-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .item-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .item-card-title {
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }

        .item-card-body {
            font-size: 13px;
            color: #555;
        }

        .item-card-row {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
        }

        .item-card-label {
            font-weight: 500;
            color: #666;
        }

        .item-card-value {
            color: #333;
        }

        .item-status-selector {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #eee;
        }

        .item-status-selector label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 5px;
            color: #555;
        }

        .item-status-selector select {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
            outline: none;
        }

        .item-status-selector select:focus {
            border-color: #4caf50;
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
        <?php include '_header_user.php'; ?>
    </div>

    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button id="backButton" onclick="goBackToList()"
                    style="display: none; padding: 8px 16px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                    Back to List
                </button>
                <h2 id="pageTitle">Claim Item Modification</h2>
            </div>
        </div>

        <!-- Alert -->
        <div id="alertBox" class="alert"></div>

        <!-- List View (← Table) -->
        <div id="listView">
            <!-- Search Bar -->
            <div class="search-bar-wrapper">
                <div class="search-controls">
                    <input type="text" id="searchInput" placeholder="Search by Invoice, Customer, or Item..."
                        onkeyup="filterTable()">
                    <button onclick="filterTable()">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path
                                d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                        </svg>
                        Search
                    </button>
                    <?php
                    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
                    $is_admin = ($system_level === 'Super-Admin' || $system_level === 'Sub-admin');

                    if ($is_admin) {
                        echo '<select id="branchFilter" onchange="filterTable()">';
                        echo '    <option value="">All Branches</option>';

                        $branch_filter_query = "SELECT DISTINCT branch_name FROM branches ORDER BY branch_name";
                        $branch_filter_result = $conn->query($branch_filter_query);
                        if ($branch_filter_result && $branch_filter_result->num_rows > 0) {
                            while ($branch_row = $branch_filter_result->fetch_assoc()) {
                                $branch_name = htmlspecialchars($branch_row['branch_name']);
                                echo "<option value=\"{$branch_name}\">{$branch_name}</option>";
                            }
                        }
                        echo '</select>';
                    }
                    ?>

                    <!-- Status Filter -->
                    <select id="statusFilter" onchange="filterTable()"
                        style="padding: 9px 14px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; outline: none; font-family: Arial, sans-serif; background: white; min-width: 150px; margin-left: 10px;">
                        <option value="">All Status</option>
                        <option value="claimed">Claimed</option>
                        <option value="unclaimed">Unclaimed</option>
                    </select>
                </div>
                <div class="date-filters">
                    <label for="dateFrom" style="font-size: 14px; color: #333;">From:</label>
                    <input type="date" id="dateFrom" value="" onchange="filterTable()">
                    <label for="dateTo" style="margin-left: 10px; font-size: 14px; color: #333;">To:</label>
                    <input type="date" id="dateTo" value="" onchange="filterTable()">
                </div>
            </div>

            <!-- Claimed Items Table -->
            <div class="table-container">
                <table class="report-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:10%;">Date Created</th>
                            <th style="width:12%;">Invoice Number</th>
                            <th style="width:14%;">Customer Name</th>
                            <th style="width:14%;">Item Description</th>
                            <th style="width:7%;">Quantity</th>
                            <th style="width:11%;">Branch</th>
                            <th style="width:12%;">Reason to Modify</th>
                            <th style="width:10%;">Status</th>
                            <th style="width:10%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        <?php
                        $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
                        $is_admin = ($system_level === 'Super-Admin' || $system_level === 'Sub-admin');

                        // Only show records from the last 30 days (including today)
                        $date_filter = "DATE(uf.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

                        // Get user's branch name from session for filtering
                        $user_branch_name = $_SESSION['user_branch'] ?? '';

                        if ($is_admin) {
                            $where_clause = "WHERE $date_filter";
                        } else {
                            $where_clause = "WHERE uf.branch = '$user_branch_name' AND $date_filter";
                        }

                        // Query to get unclaimed freebies with their status and any modifications
                        $query = "SELECT uf.id, uf.created_at, uf.claimed_at, uf.invoice_number as sales_invoice_no,
                                CONCAT(COALESCE(se.first_name, ''), ' ', COALESCE(se.last_name, '')) as full_customer_name,
                                uf.item_description, uf.item_code, uf.quantity,
                                uf.branch as branch_name,
                                uf.note as reason_to_modify,
                                uf.status
                                FROM unclaimed_freebies uf
                                LEFT JOIN sales_entry se ON uf.sales_entry_id = se.id
                                $where_clause
                                ORDER BY uf.created_at DESC, uf.id DESC LIMIT 500";

                        $result = $conn->query($query);
                        $total_entries = 0;

                        if ($result && $result->num_rows > 0) {
                            // First, group by invoice to determine overall status
                            $invoice_data = [];
                            while ($row = $result->fetch_assoc()) {
                                $inv = $row['sales_invoice_no'];
                                if (!isset($invoice_data[$inv])) {
                                    $invoice_data[$inv] = [
                                        'has_unclaimed' => false,
                                        'has_claimed' => false,
                                        'records' => []
                                    ];
                                }

                                // Track status types for this invoice
                                if (strtolower($row['status']) === 'unclaimed') {
                                    $invoice_data[$inv]['has_unclaimed'] = true;
                                } else {
                                    $invoice_data[$inv]['has_claimed'] = true;
                                }

                                $invoice_data[$inv]['records'][] = $row;
                            }

                            // Now display records based on computed status
                            $idx = 0;
                            $displayed_invoices = [];

                            foreach ($invoice_data as $inv => $inv_info) {
                                // Determine the overall status for this invoice
                                if ($inv_info['has_unclaimed'] && $inv_info['has_claimed']) {
                                    // Both statuses exist = Claimed (takes priority)
                                    $display_status = 'CLAIMED';
                                    $status_class = 'status-claimed';
                                    $data_status = 'claimed';
                                } else if ($inv_info['has_unclaimed'] && !$inv_info['has_claimed']) {
                                    // Only unclaimed = show as Unclaimed
                                    $display_status = 'UNCLAIMED';
                                    $status_class = 'status-unclaimed';
                                    $data_status = 'unclaimed';
                                } else {
                                    // Only claimed = show as Claimed
                                    $display_status = 'CLAIMED';
                                    $status_class = 'status-claimed';
                                    $data_status = 'claimed';
                                }

                                // Display only the first record for each invoice (to avoid duplicates)
                                $row = $inv_info['records'][0];

                                $r_date = !empty($row['created_at']) && $row['created_at'] != '0000-00-00 00:00:00'
                                    ? date('m/d/Y', strtotime($row['created_at']))
                                    : 'N/A';
                                $r_inv = htmlspecialchars($row['sales_invoice_no']);
                                $r_cust = !empty($row['full_customer_name']) ? htmlspecialchars($row['full_customer_name']) : '—';
                                $r_item = htmlspecialchars($row['item_description']);
                                $r_qty = htmlspecialchars($row['quantity']);
                                $r_branch = !empty($row['branch_name']) ? htmlspecialchars($row['branch_name']) : '—';
                                $r_id = $row['id'];
                                $r_reason = !empty($row['reason_to_modify']) ? htmlspecialchars($row['reason_to_modify']) : '—';

                                // Store all records for this invoice as JSON in data attribute
                                $all_records_json = htmlspecialchars(json_encode($inv_info['records']), ENT_QUOTES);

                                echo "<tr data-id=\"{$r_id}\" data-index=\"{$idx}\" data-invoice=\"{$r_inv}\" data-status=\"{$data_status}\" data-all-records='{$all_records_json}'>";
                                echo "  <td>{$r_date}</td>";
                                echo "  <td>{$r_inv}</td>";
                                echo "  <td>{$r_cust}</td>";
                                echo "  <td style=\"text-align:left; padding-left:12px;\">{$r_item}</td>";
                                echo "  <td>{$r_qty}</td>";
                                echo "  <td>{$r_branch}</td>";
                                echo "  <td style=\"text-align:left; padding-left:12px;\">{$r_reason}</td>";
                                echo "  <td><span class=\"status-badge {$status_class}\">{$display_status}</span></td>";
                                echo "  <td>";
                                echo "      <div class=\"action-btns\">";
                                echo "          <button class=\"btn-modify\" onclick=\"modifyClaimItem({$r_id})\">Modify</button>";
                                echo "      </div>";
                                echo "  </td>";
                                echo "</tr>";
                                $idx++;
                                $total_entries++;
                            }

                            if ($total_entries === 0) {
                                echo "<tr class=\"no-data\" id=\"noDataRow\"><td colspan=\"9\">No claimed items found.</td></tr>";
                            }
                        } else {
                            echo "<tr class=\"no-data\" id=\"noDataRow\"><td colspan=\"9\">No claimed items found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination-wrapper">
                    <span id="pageInfo">Showing <?php echo $total_entries; ?> entries</span>
                    <button class="page-btn active" id="prevBtn" onclick="changePage(-1)" disabled>&laquo; Prev</button>
                    <button class="page-btn active" id="nextBtn" onclick="changePage(1)" disabled>Next &raquo;</button>
                </div>
            </div>
        </div>
        <!-- End List View -->

        <!-- Detail View (Modification Form) -->
        <div id="detailView" style="display: none;">
            <div class="table-container">
                <form id="modificationForm">
                    <input type="hidden" id="detailClaimId" name="claim_id">

                    <!-- Invoice Header Info -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #333;">Invoice Information</h3>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div>
                                <label style="display: block; font-size: 13px; color: #666; margin-bottom: 5px;">Date
                                    Created</label>
                                <input type="text" id="detailDate" disabled
                                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5; color: #666;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 13px; color: #666; margin-bottom: 5px;">Invoice
                                    Number</label>
                                <input type="text" id="detailInvoice"
                                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                            </div>
                            <div>
                                <label
                                    style="display: block; font-size: 13px; color: #666; margin-bottom: 5px;">Customer
                                    Name</label>
                                <input type="text" id="detailCustomer"
                                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                            </div>
                            <div>
                                <label
                                    style="display: block; font-size: 13px; color: #666; margin-bottom: 5px;">Branch</label>
                                <input type="text" id="detailBranch"
                                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #333;">Items in this Invoice</h3>
                        <div id="detailItemsList" style="max-height: 500px; overflow-y: auto;">
                            <!-- Items will be populated here dynamically -->
                        </div>
                    </div>

                    <!-- Reason for Modification -->
                    <div style="margin-bottom: 20px;">
                        <label
                            style="display: block; font-size: 14px; font-weight: 600; color: #333; margin-bottom: 8px;">
                            Reason for Modification <span style="color: red;">*</span>
                        </label>
                        <textarea id="detailReason" name="reason"
                            placeholder="Enter the reason for this modification..." required
                            style="width: 100%; min-height: 100px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; font-family: Arial, sans-serif; resize: vertical;"></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div
                        style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 15px; border-top: 1px solid #ddd;">
                        <button type="button" onclick="goBackToList()"
                            style="padding: 10px 24px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">
                            Cancel
                        </button>
                        <button type="button" onclick="saveModification()"
                            style="padding: 10px 24px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- End Detail View -->
    </div>

    <script>
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn = document.querySelector('.menu-btn');

            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
            menuBtn.classList.toggle('active');
        }

        // Sidebar section toggle (from _sidebar.php)
        function toggleSection(element) {
            const section = element.parentElement;
            const isCurrentlyCollapsed = section.classList.contains('collapsed');

            // Close all other sections
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

            saveSidebarState();
        }

        // Save sidebar state to localStorage
        function saveSidebarState() {
            const sections = document.querySelectorAll('.menu-section');
            const state = {};

            sections.forEach(function (section, index) {
                const title = section.querySelector('.menu-section-title span');
                if (title) {
                    const sectionName = title.textContent.trim();
                    state[sectionName] = !section.classList.contains('collapsed');
                }
            });

            localStorage.setItem('sidebarState', JSON.stringify(state));
        }

        // Restore sidebar state from localStorage
        function restoreSidebarState() {
            const savedState = localStorage.getItem('sidebarState');
            if (!savedState) return;

            try {
                const state = JSON.parse(savedState);
                const sections = document.querySelectorAll('.menu-section');

                sections.forEach(function (section) {
                    const title = section.querySelector('.menu-section-title span');
                    if (title) {
                        const sectionName = title.textContent.trim();
                        if (state[sectionName] === true) {
                            section.classList.remove('collapsed');
                        } else if (state[sectionName] === false) {
                            section.classList.add('collapsed');
                        }
                    }
                });
            } catch (e) {
                console.error('Error restoring sidebar state:', e);
            }
        }

        // Save view state to URL and localStorage
        function saveViewState(view, invoiceNumber = null, rowData = null) {
            if (view === 'detail' && invoiceNumber) {
                // Update URL with query parameter
                const url = new URL(window.location);
                url.searchParams.set('invoice', invoiceNumber);
                window.history.pushState({ view: 'detail', invoice: invoiceNumber }, '', url);

                // Also save to localStorage as backup
                const state = {
                    currentView: view,
                    invoiceNumber: invoiceNumber,
                    rowData: rowData,
                    timestamp: new Date().getTime()
                };
                localStorage.setItem('claimModificationViewState', JSON.stringify(state));
            }
        }

        // Restore view state from URL or localStorage
        function restoreViewState() {
            try {
                // First check URL parameters
                const urlParams = new URLSearchParams(window.location.search);
                const invoiceFromUrl = urlParams.get('invoice');

                if (invoiceFromUrl) {
                    console.log('Restoring detail view for invoice:', invoiceFromUrl);

                    // Remove the preload hide style if it exists
                    const preloadStyle = document.getElementById('preload-hide');
                    if (preloadStyle) {
                        preloadStyle.remove();
                    }

                    // Find and open the detail view for this invoice
                    const table = document.getElementById('reportTable');
                    if (!table) {
                        console.error('Table not found');
                        return;
                    }

                    const tbody = table.getElementsByTagName('tbody')[0];
                    if (!tbody) {
                        console.error('Table body not found');
                        return;
                    }

                    const rows = tbody.getElementsByTagName('tr');
                    console.log('Searching through', rows.length, 'rows');

                    for (let i = 0; i < rows.length; i++) {
                        const row = rows[i];
                        if (row.classList.contains('no-data')) continue;

                        const invoiceCell = row.cells[1];
                        if (invoiceCell) {
                            const cellInvoice = invoiceCell.textContent.trim();
                            console.log('Checking row', i, 'invoice:', cellInvoice);

                            if (cellInvoice === invoiceFromUrl) {
                                console.log('Found matching invoice, triggering modify');
                                // Simulate clicking the modify button
                                const modifyBtn = row.querySelector('.btn-modify');
                                if (modifyBtn) {
                                    modifyBtn.click();
                                    console.log('Detail view opened successfully');
                                } else {
                                    console.error('Modify button not found in row');
                                }
                                break;
                            }
                        }
                    }
                    return;
                }

                // Fallback to localStorage if no URL parameter
                const stateJson = localStorage.getItem('claimModificationViewState');
                if (stateJson) {
                    const state = JSON.parse(stateJson);

                    // Check if state is recent (within last 5 minutes)
                    const now = new Date().getTime();
                    const fiveMinutes = 5 * 60 * 1000;

                    if (now - state.timestamp < fiveMinutes && state.currentView === 'detail' && state.invoiceNumber) {
                        // Update URL and open detail view
                        const url = new URL(window.location);
                        url.searchParams.set('invoice', state.invoiceNumber);
                        window.history.replaceState({ view: 'detail', invoice: state.invoiceNumber }, '', url);

                        // Find the row in the table
                        const table = document.getElementById('reportTable');
                        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

                        for (let i = 0; i < rows.length; i++) {
                            const row = rows[i];
                            const invoiceCell = row.cells[1];
                            if (invoiceCell && invoiceCell.textContent.trim() === state.invoiceNumber) {
                                const modifyBtn = row.querySelector('.btn-modify');
                                if (modifyBtn) {
                                    modifyBtn.click();
                                }
                                break;
                            }
                        }
                    } else {
                        // Clear old state
                        localStorage.removeItem('claimModificationViewState');
                    }
                }
            } catch (e) {
                console.error('Error restoring view state:', e);
            }
        }

        // Clear view state from URL and localStorage
        function clearViewState() {
            // Remove query parameter from URL
            const url = new URL(window.location);
            url.searchParams.delete('invoice');
            window.history.pushState({ view: 'list' }, '', url);

            // Clear localStorage
            localStorage.removeItem('claimModificationViewState');
        }

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function (event) {
            if (event.state) {
                if (event.state.view === 'list') {
                    // Return to list view
                    document.getElementById('detailView').style.display = 'none';
                    document.getElementById('listView').style.display = 'block';
                    document.getElementById('backButton').style.display = 'none';
                    document.getElementById('pageTitle').textContent = 'Claim Item Modification';
                } else if (event.state.view === 'detail' && event.state.invoice) {
                    // Restore detail view
                    restoreViewState();
                }
            }
        });

        // Restore sidebar state on page load
        document.addEventListener('DOMContentLoaded', function () {
            restoreSidebarState();

            // Delay restoration to ensure table is fully loaded
            setTimeout(function () {
                restoreViewState();
            }, 100);
        });

        // Filter table
        function filterTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const branchFilter = document.getElementById('branchFilter') ? document.getElementById('branchFilter').value : '';
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;

            const table = document.getElementById('reportTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            let visibleCount = 0;

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                if (row.classList.contains('no-data')) continue;

                const cells = row.getElementsByTagName('td');
                const date = cells[0].textContent;
                const invoice = cells[1].textContent.toLowerCase();
                const customer = cells[2].textContent.toLowerCase();
                const item = cells[3].textContent.toLowerCase();
                const branch = cells[5].textContent.trim();
                const rowStatus = row.getAttribute('data-status') || '';

                let matchSearch = invoice.includes(searchInput) || customer.includes(searchInput) || item.includes(searchInput);
                let matchBranch = !branchFilter || branchFilter.toLowerCase() === 'all' || branch === branchFilter;
                let matchStatus = !statusFilter || rowStatus === statusFilter;
                let matchDate = true;

                if (dateFrom || dateTo) {
                    const rowDate = new Date(date);
                    if (dateFrom) {
                        const fromDate = new Date(dateFrom);
                        if (rowDate < fromDate) matchDate = false;
                    }
                    if (dateTo) {
                        const toDate = new Date(dateTo);
                        if (rowDate > toDate) matchDate = false;
                    }
                }

                if (matchSearch && matchBranch && matchStatus && matchDate) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            }

            // Update page info
            document.getElementById('pageInfo').textContent = `Showing ${visibleCount} entries`;

            // Show/hide no data row
            const noDataRow = document.getElementById('noDataRow');
            if (noDataRow) {
                noDataRow.style.display = visibleCount === 0 ? '' : 'none';
            }
        }

        // Modify claim item - show detail view
        function modifyClaimItem(claimId) {
            // Get the row data
            const row = document.querySelector(`tr[data-id="${claimId}"]`);
            if (!row) {
                showAlert('Error: Could not find claim item data.', 'error');
                return;
            }

            const cells = row.getElementsByTagName('td');

            // Get all records for this invoice from data attribute
            const allRecordsJson = row.getAttribute('data-all-records');
            let allRecords = [];

            try {
                allRecords = JSON.parse(allRecordsJson);
            } catch (e) {
                showAlert('Error: Could not parse invoice records.', 'error');
                console.error('JSON parse error:', e);
                return;
            }

            // Populate detail header info
            document.getElementById('detailClaimId').value = claimId;
            document.getElementById('detailDate').value = cells[0].textContent.trim();
            document.getElementById('detailInvoice').value = cells[1].textContent.trim();
            document.getElementById('detailCustomer').value = cells[2].textContent.trim();
            document.getElementById('detailBranch').value = cells[5].textContent.trim();

            // Reset reason field
            const existingReason = cells[6].textContent.trim();
            document.getElementById('detailReason').value = existingReason !== '—' ? existingReason : '';

            // Build items list
            const itemsList = document.getElementById('detailItemsList');
            itemsList.innerHTML = '';

            // Group records by item_code
            const itemGroups = {};
            allRecords.forEach(record => {
                const itemCode = record.item_code || 'N/A';
                if (!itemGroups[itemCode]) {
                    itemGroups[itemCode] = [];
                }
                itemGroups[itemCode].push(record);
            });

            // Display grouped items
            let itemIndex = 1;
            Object.keys(itemGroups).forEach(itemCode => {
                const records = itemGroups[itemCode];

                // Sort records: Unclaimed first, then Claimed
                records.sort((a, b) => {
                    const statusA = a.status.toLowerCase();
                    const statusB = b.status.toLowerCase();
                    if (statusA === 'unclaimed' && statusB === 'claimed') return -1;
                    if (statusA === 'claimed' && statusB === 'unclaimed') return 1;
                    return 0;
                });

                const firstRecord = records[0];

                const itemCard = document.createElement('div');
                itemCard.className = 'item-card';

                let statusSections = '';

                // Create a section for each status record
                records.forEach(record => {
                    const statusLower = record.status.toLowerCase();

                    // Format created_at (Date Created)
                    let createdAtDate = '';
                    if (record.created_at && record.created_at !== '0000-00-00 00:00:00') {
                        const dateObj = new Date(record.created_at);
                        const year = dateObj.getFullYear();
                        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                        const day = String(dateObj.getDate()).padStart(2, '0');
                        createdAtDate = `${year}-${month}-${day}`;
                    }

                    // Format claimed_at (Date Claimed)
                    let claimedAtDate = '';
                    if (record.claimed_at && record.claimed_at !== '0000-00-00 00:00:00') {
                        const dateObj = new Date(record.claimed_at);
                        const year = dateObj.getFullYear();
                        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                        const day = String(dateObj.getDate()).padStart(2, '0');
                        claimedAtDate = `${year}-${month}-${day}`;
                    }

                    if (statusLower === 'claimed') {
                        // For CLAIMED items, show both UNCLAIMED (Date Created) and CLAIMED (Date Claimed) boxes
                        statusSections += `
                            <div style="background: #fff3f3; padding: 12px; border-radius: 4px; border-left: 3px solid #c62828;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span class="status-badge status-unclaimed" style="font-size: 11px;">UNCLAIMED</span>
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <label style="display: block; font-size: 12px; color: #666; margin-bottom: 4px;">Date Created:</label>
                                    <input type="date" id="itemDate_created_${record.id}" class="item-date-input" data-item-id="${record.id}" data-date-field="created_at" value="${createdAtDate}" 
                                        style="width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <label style="display: block; font-size: 12px; color: #666; margin-bottom: 4px;">Quantity:</label>
                                    <input type="number" id="itemQty_${record.id}" class="item-qty-input" data-item-id="${record.id}" value="${record.quantity || 0}" min="0"
                                        style="width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                </div>
                                <div class="item-status-selector">
                                    <label for="itemStatusUnclaimed_${record.id}" style="font-size: 12px;">Action:</label>
                                    <select id="itemStatusUnclaimed_${record.id}" class="item-status-select" data-item-id="${record.id}" data-original-status="unclaimed" style="width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                        <option value="">-- Keep Current --</option>
                                        <option value="remove">Remove</option>
                                    </select>
                                </div>
                            </div>
                            <div style="background: #f3fff3; padding: 12px; border-radius: 4px; border-left: 3px solid #2e7d32;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span class="status-badge status-claimed" style="font-size: 11px;">CLAIMED</span>
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <label style="display: block; font-size: 12px; color: #666; margin-bottom: 4px;">Date Claimed:</label>
                                    <input type="date" id="itemDate_claimed_${record.id}" class="item-date-input" data-item-id="${record.id}" data-date-field="claimed_at" value="${claimedAtDate}" 
                                        style="width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <label style="display: block; font-size: 12px; color: #666; margin-bottom: 4px;">Quantity:</label>
                                    <input type="number" id="itemQtyClaimed_${record.id}" class="item-qty-input-claimed" data-item-id="${record.id}" value="${record.quantity || 0}" min="0"
                                        style="width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                </div>
                                <div class="item-status-selector">
                                    <label for="itemStatus_${record.id}" style="font-size: 12px;">Action:</label>
                                    <select id="itemStatus_${record.id}" class="item-status-select" data-item-id="${record.id}" data-original-status="claimed" style="width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                        <option value="">-- Keep Current --</option>
                                        <option value="remove">Remove</option>
                                    </select>
                                </div>
                            </div>
                        `;
                    } else {
                        // For UNCLAIMED status
                        statusSections += `
                            <div style="background: #fff3f3; padding: 12px; border-radius: 4px; border-left: 3px solid #c62828;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span class="status-badge status-unclaimed" style="font-size: 11px;">UNCLAIMED</span>
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <label style="display: block; font-size: 12px; color: #666; margin-bottom: 4px;">Date Created:</label>
                                    <input type="date" id="itemDate_created_${record.id}" class="item-date-input" data-item-id="${record.id}" data-date-field="created_at" value="${createdAtDate}" 
                                        style="width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <label style="display: block; font-size: 12px; color: #666; margin-bottom: 4px;">Quantity:</label>
                                    <input type="number" id="itemQty_${record.id}" class="item-qty-input" data-item-id="${record.id}" value="${record.quantity || 0}" min="0"
                                        style="width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                </div>
                                <div class="item-status-selector">
                                    <label for="itemStatus_${record.id}" style="font-size: 12px;">Action:</label>
                                    <select id="itemStatus_${record.id}" class="item-status-select" data-item-id="${record.id}" data-original-status="unclaimed" style="width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                        <option value="">-- Keep Current --</option>
                                        <option value="remove">Remove</option>
                                    </select>
                                </div>
                            </div>
                        `;
                    }
                });

                itemCard.innerHTML = `
                    <div class="item-card-header">
                        <span class="item-card-title">Item #${itemIndex}</span>
                    </div>
                    <div class="item-card-body">
                        <div class="item-card-row">
                            <span class="item-card-label">Item Code:</span>
                            <span class="item-card-value" style="font-weight: 600;">${itemCode}</span>
                        </div>
                        <div class="item-card-row">
                            <span class="item-card-label">Quantity:</span>
                            <span class="item-card-value">${firstRecord.quantity || 0}</span>
                        </div>
                        <div style="margin-top: 15px; padding-top: 12px; border-top: 1px solid #eee;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 10px; color: #555;">Status Records:</label>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                ${statusSections}
                            </div>
                        </div>
                    </div>
                `;

                itemsList.appendChild(itemCard);
                itemIndex++;
            });

            // Switch to detail view
            document.getElementById('listView').style.display = 'none';
            document.getElementById('detailView').style.display = 'block';
            document.getElementById('backButton').style.display = 'block';
            document.getElementById('pageTitle').textContent = 'Modify Claim Item - ' + cells[1].textContent.trim();

            // Save state to localStorage
            saveViewState('detail', cells[1].textContent.trim(), {
                claimId: claimId,
                invoice: cells[1].textContent.trim(),
                customer: cells[2].textContent.trim(),
                branch: cells[5].textContent.trim(),
                reason: cells[6].textContent.trim()
            });
        }

        // Go back to list view
        function goBackToList() {
            document.getElementById('detailView').style.display = 'none';
            document.getElementById('listView').style.display = 'block';
            document.getElementById('backButton').style.display = 'none';
            document.getElementById('pageTitle').textContent = 'Claim Item Modification';
            document.getElementById('modificationForm').reset();

            // Clear state from localStorage
            clearViewState();
        }

        // Close modal (deprecated - keeping for compatibility)
        function closeModal() {
            goBackToList();
        }

        // Close modal when clicking outside (deprecated)
        window.onclick = function (event) {
            // No longer needed for page-based approach
        }

        // Save modification
        function saveModification() {
            const claimId = document.getElementById('detailClaimId').value;
            const reason = document.getElementById('detailReason').value.trim();

            // Collect invoice information
            const invoiceNumber = document.getElementById('detailInvoice').value.trim();
            const customerName = document.getElementById('detailCustomer').value.trim();
            const branch = document.getElementById('detailBranch').value.trim();

            // Validation
            if (!reason) {
                showAlert('Please provide a reason for modification.', 'error');
                return;
            }

            if (!invoiceNumber) {
                showAlert('Invoice Number is required.', 'error');
                return;
            }

            if (!customerName) {
                showAlert('Customer Name is required.', 'error');
                return;
            }

            if (!branch) {
                showAlert('Branch is required.', 'error');
                return;
            }

            // Collect all item status changes, date changes, and quantity changes
            const statusSelects = document.querySelectorAll('.item-status-select');
            const modifications = [];

            statusSelects.forEach(select => {
                const itemId = select.getAttribute('data-item-id');
                const originalStatus = select.getAttribute('data-original-status');
                const newStatus = select.value;

                // Collect created_at date (from UNCLAIMED box)
                const createdDateInput = document.querySelector(`#itemDate_created_${itemId}`);
                const createdAt = createdDateInput ? createdDateInput.value : null;

                // Collect claimed_at date (from CLAIMED box, only exists for claimed records)
                const claimedDateInput = document.querySelector(`#itemDate_claimed_${itemId}`);
                const claimedAt = claimedDateInput ? claimedDateInput.value : null;

                // Fallback: if neither specific ID found, try generic class
                const genericDateInput = !createdAt && !claimedAt ? document.querySelector(`.item-date-input[data-item-id="${itemId}"]`) : null;
                const newDate = genericDateInput ? genericDateInput.value : null;
                const dateField = genericDateInput ? genericDateInput.getAttribute('data-date-field') : null;

                // Collect quantity — for claimed records, use itemQtyClaimed_; otherwise use itemQty_
                const claimedQtyInput = document.querySelector(`#itemQtyClaimed_${itemId}`);
                const regularQtyInput = document.querySelector(`#itemQty_${itemId}`);
                const newQuantity = claimedQtyInput ? claimedQtyInput.value : (regularQtyInput ? regularQtyInput.value : null);

                modifications.push({
                    itemId: itemId,
                    originalStatus: originalStatus,
                    newStatus: newStatus || originalStatus,
                    createdAt: createdAt,
                    claimedAt: claimedAt,
                    dateField: dateField,
                    newDate: newDate,
                    newQuantity: newQuantity
                });
            });

            // Disable save button to prevent double submit
            const saveBtn = document.querySelector('button[onclick="saveModification()"]');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
            }

            // Send to backend via AJAX
            fetch('save_claimitem_modification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    claimId: claimId,
                    invoiceNumber: invoiceNumber,
                    customerName: customerName,
                    branch: branch,
                    reason: reason,
                    modifications: modifications
                })
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        showAlert(result.message || 'Changes saved successfully!', 'success');
                        // Go back to list and reload the page after a short delay
                        setTimeout(() => {
                            clearViewState();
                            window.location.href = 'modification-claimitem.php';
                        }, 1800);
                    } else {
                        showAlert('Error: ' + (result.message || 'Failed to save changes.'), 'error');
                        if (saveBtn) {
                            saveBtn.disabled = false;
                            saveBtn.textContent = 'Save Changes';
                        }
                    }
                })
                .catch(err => {
                    showAlert('Network error: ' + err.message, 'error');
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save Changes';
                    }
                });
        }

        // Pagination (basic implementation)
        function changePage(direction) {
            // To be implemented with server-side pagination
            console.log('Change page:', direction);
        }

        // Show alert
        function showAlert(message, type) {
            const alertBox = document.getElementById('alertBox');
            alertBox.textContent = message;
            alertBox.className = 'alert alert-' + type;
            alertBox.style.display = 'block';

            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 5000);
        }
    </script>
</body>

</html>