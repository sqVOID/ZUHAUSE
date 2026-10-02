<?php
require_once 'session_check.php';
include 'config.php';

// Get logged in user's branch code
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$branch_code = '000'; // Default
if (isset($_SESSION['user_branch'])) {
    $user_branch_name = $_SESSION['user_branch'];
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch_name'");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
}

$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Revert Void Sales</title>
    <style>
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

        .submenu {padding-left: 20px;max-height: 800px;overflow: hidden;transition: max-height 0.3s ease;}

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

        /* Page header */
        .content-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: 15px;
            margin-bottom: 15px;
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
            transition: background-color 0.2s;
        }

        .search-bar-wrapper button:hover {
            background-color: var(--color-navy-dark);
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

        .btn-filter {
            padding: 9px 20px;
            background-color: #4caf50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            margin-left: 10px;
        }

        .btn-filter:hover {
            background-color: #45a049;
        }

        /* Table container */
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }

        /* Table */
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

        /* No data row */
        .report-table .no-data td {
            color: #999;
            font-style: italic;
            padding: 20px;
        }

        /* Action buttons */
        .action-btns {
            display: flex;
            gap: 6px;
            justify-content: center;
            align-items: center;
        }

        .btn-view {
            padding: 5px 14px;
            background-color: #1e88e5;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-view:hover {
            background-color: #1565c0;
        }

        .btn-revert {
            padding: 5px 14px;
            background-color: var(--color-green);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-revert:hover {
            background-color: var(--color-green-dark);
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

        .pagination-wrapper button {
            padding: 6px 12px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .pagination-wrapper button:hover {
            background-color: var(--color-navy-dark);
        }

        .pagination-wrapper button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
        }

        .modal.show {
            display: block;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 3% auto;
            padding: 0;
            border: 1px solid #888;
            border-radius: 8px;
            width: 90%;
            max-width: 1000px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease-out;
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
            background-color: #000000ff;
            color: white;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3,
        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .close-modal,
        .modal-close {
            color: white;
            font-size: 32px;
            font-weight: bold;
            line-height: 1;
            cursor: pointer;
            transition: color 0.2s;
            background: none;
            border: none;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-modal:hover,
        .close-modal:focus,
        .modal-close:hover,
        .modal-close:focus {
            color: #ffcccc;
        }

        .modal-body {
            padding: 0;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-voided {
            background-color: #ffcdd2;
            color: #c62828;
        }

        .status-active {
            background-color: #c8e6c9;
            color: #2e7d32;
        }

        .status-reverted {
            background-color: #bbdefb;
            color: #1565c0;
        }

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
            }

            .search-bar-wrapper {
                flex-direction: column;
                gap: 10px;
            }

            .search-controls,
            .date-filters {
                width: 100%;
                flex-direction: column;
                gap: 10px;
            }

            .search-bar-wrapper input,
            .search-bar-wrapper select,
            .search-bar-wrapper input[type="date"] {
                width: 100%;
                margin-left: 0;
            }

            .search-bar-wrapper button {
                border-radius: 4px;
            }

            .table-container {
                overflow-x: auto;
            }

            .report-table {
                min-width: 1000px;
            }

            .modal-content {
                width: 95%;
                max-width: 95%;
            }
        }
    </style>
</head>

<body>

    <!-- Header -->
    <div class="header">
        <div class="menu-btn" onclick="toggleSidebar()">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <img src="Icon/ZUHAUSE-LOGO.png" alt="Zuhause Logo" class="logo">
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="content-header">
            <h2>Revert Void Sales</h2>
        </div>

        <!-- Search and Filter Section -->
        <div class="table-container">
            <div class="search-bar-wrapper">
                <div class="search-controls">
                    <input type="text" id="searchInput" placeholder="Search by Invoice No, Customer Name...">
                    <button onclick="searchRecords()">Search</button>
                    <select id="branchFilter">
                        <option value="">All Branches</option>
                        <?php
                        $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
                        $is_admin = ($system_level === 'Super-Admin');

                        if ($is_admin) {
                            $branches_query = $conn->query("SELECT DISTINCT branch_code, branch_name FROM branches ORDER BY branch_name");
                        } else {
                            $user_branch = isset($_SESSION['user_branch']) ? $_SESSION['user_branch'] : '';
                            $branch_names = array_map('trim', explode(',', $user_branch));
                            $branch_names_quoted = array_map(function ($name) use ($conn) {
                                return "'" . $conn->real_escape_string($name) . "'";
                            }, $branch_names);
                            $branch_in_clause = implode(', ', $branch_names_quoted);
                            $branches_query = $conn->query("SELECT DISTINCT branch_code, branch_name FROM branches WHERE branch_name IN ($branch_in_clause) ORDER BY branch_name");
                        }

                        while ($branch = $branches_query->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($branch['branch_code']) . '">' . htmlspecialchars($branch['branch_name']) . ' - ' . htmlspecialchars($branch['branch_code']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="date-filters">
                    <input type="date" id="dateFrom" value="<?php echo date('Y-m-01'); ?>">
                    <input type="date" id="dateTo" value="<?php echo date('Y-m-d'); ?>">
                    <button class="btn-filter" onclick="filterByDate()">Filter</button>
                </div>
            </div>

            <!-- Void Sales Records Table -->
            <table class="report-table" id="voidSalesTable">
                <thead>
                    <tr>
                        <th>Date Voided</th>
                        <th>Date Sold</th>
                        <th>Invoice No</th>
                        <th>Customer Name</th>
                        <th>Branch</th>
                        <th>Void Reason</th>
                        <th>Voided By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="voidSalesTableBody">
                    <tr class="no-data">
                        <td colspan="9">Click Filter to load voided sales</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination-wrapper">
                <span id="paginationInfo">Click Filter to load data</span>
                <button id="prevBtn" disabled>&laquo; Previous</button>
                <button id="nextBtn" disabled>Next &raquo;</button>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Void Sale Details</h2>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewModalContent">
                <div style="max-height:100vh; overflow-y:auto; padding:20px;">
                    <h2 style="margin:0 0 20px 0; color:#1a1a1a; font-size:20px; border-bottom:2px solid #acacacff; padding-bottom:10px;" id="view_title">
                        Void Sale Details: <span id="view_invoice_title">-</span>
                    </h2>

                    <!-- Invoice Info Section -->
                    <div style="border:2px solid #acacacff; border-radius:8px; padding:15px; background:#f9f9f9; margin-bottom:20px;">
                        <h3 style="margin:0 0 12px 0; color:#1E455D; font-size:16px;">Void Sale Information</h3>
                        <table style="width:100%; font-size:14px;">
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600; width:140px;">Invoice Number:</td>
                                <td style="padding:5px 0;" id="view_invoice_no">-</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Date Sold:</td>
                                <td style="padding:5px 0;" id="view_date_sold">-</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Date Voided:</td>
                                <td style="padding:5px 0;" id="view_date_voided">-</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Customer Name:</td>
                                <td style="padding:5px 0;" id="view_customer_name">-</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Branch:</td>
                                <td style="padding:5px 0;" id="view_branch">-</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Voided By:</td>
                                <td style="padding:5px 0;" id="view_voided_by">-</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Status:</td>
                                <td style="padding:5px 0;"><span class="status-badge status-voided" id="view_status">VOIDED</span></td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Void Reason:</td>
                                <td style="padding:5px 0;" id="view_void_reason">-</td>
                            </tr>
                        </table>
                    </div>

                    <!-- Items Section -->
                    <div style="border:2px solid #acacacff; border-radius:8px; padding:15px; background:#f9f9f9; margin-bottom:20px;">
                        <h3 style="margin:0 0 12px 0; color:#1E455D; font-size:16px;">Voided Items</h3>
                        <table style="width:100%; border-collapse:collapse; font-size:14px;">
                            <thead>
                                <tr style="background:#f5f5f5;">
                                    <th style="padding:10px; border:1px solid #acacacff; text-align:left;">Description</th>
                                    <th style="padding:10px; border:1px solid #acacacff; text-align:center;">IMEI</th>
                                    <th style="padding:10px; border:1px solid #acacacff; text-align:center;">Brand</th>
                                    <th style="padding:10px; border:1px solid #acacacff; text-align:center;">Qty</th>
                                    <th style="padding:10px; border:1px solid #acacacff; text-align:right;">Unit Price</th>
                                    <th style="padding:10px; border:1px solid #acacacff; text-align:right;">Total</th>
                                </tr>
                            </thead>
                            <tbody id="view_items_list">
                                <tr>
                                    <td colspan="6" style="padding:20px; text-align:center; color:#999;">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Revert Modal -->
    <div id="revertModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Revert Void Sale</h2>
                <button class="modal-close" onclick="closeRevertModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="max-height:100vh; overflow-y:auto; padding:20px;">
                    <h2 style="margin:0 0 20px 0; color:#1a1a1a; font-size:20px; border-bottom:2px solid #acacacff; padding-bottom:10px;">
                        Revert Void Sale: <span id="revert_invoice_title">-</span>
                    </h2>
                    
                    <!--
                     Warning Section
                    <div style="background:#fff3cd; border:2px solid #ffc107; border-radius:8px; padding:15px; margin-bottom:20px;">
                        <h3 style="margin:0 0 12px 0; color:#856404; font-size:16px; font-weight:700;">⚠️ Warning: Revert Void Sale Transaction</h3>
                        <ul style="margin:0; padding-left:20px; color:#856404; font-size:14px; line-height:1.8;">
                            <li>This action will restore the voided sale back to active status</li>
                            <li>All items will be marked as sold again</li>
                            <li>Inventory will be adjusted accordingly</li>
                            <li>This action requires proper authorization and reason</li>
                        </ul>
                    </div>
                    -->

                    <!-- Void Sale Details -->
                    <div style="border:2px solid #acacacff; border-radius:8px; padding:15px; background:#f9f9f9; margin-bottom:20px;">
                        <h3 style="margin:0 0 12px 0; color:#1E455D; font-size:16px;">Void Sale Details</h3>
                        <table style="width:100%; font-size:14px;">
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600; width:180px;">Invoice Number:</td>
                                <td style="padding:5px 0;" id="revert_invoice_no">-</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Date Sold:</td>
                                <td style="padding:5px 0;" id="revert_date_sold">-</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Date Voided:</td>
                                <td style="padding:5px 0;" id="revert_date_voided">-</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Customer Name:</td>
                                <td style="padding:5px 0;" id="revert_customer_name">-</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Branch:</td>
                                <td style="padding:5px 0;" id="revert_branch">-</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Original Void Reason:</td>
                                <td style="padding:5px 0;" id="revert_void_reason">-</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Voided By:</td>
                                <td style="padding:5px 0;" id="revert_voided_by">-</td>
                            </tr>
                            <tr>
                                <td style="padding:5px 10px 5px 0; font-weight:600;">Total Amount:</td>
                                <td style="padding:5px 0; font-weight:700; color:#1E455D; font-size:15px;" id="revert_total_amount">₱0.00</td>
                            </tr>
                        </table>
                    </div>

                    <!-- Revert Reason Form -->
                    <div style="border:2px solid #acacacff; border-radius:8px; padding:15px; background:#f9f9f9; margin-bottom:20px;">
                        <h3 style="margin:0 0 12px 0; color:#1E455D; font-size:16px;">Reason for Reverting Void Sale</h3>
                        <label style="display:block; margin-bottom:8px; font-size:14px; font-weight:600; color:#333;">
                            Revert Reason<span style="color:red; margin-left:4px;">*</span>
                        </label>
                        <textarea id="revert_reason" 
                                  placeholder="Enter detailed reason for reverting this void sale (e.g., Customer wants to proceed with purchase, Payment cleared, Error in voiding)..." 
                                  required
                                  style="width:100%; min-height:120px; padding:12px; border:1px solid #ddd; border-radius:4px; font-size:14px; font-family:Arial, sans-serif; resize:vertical; box-sizing:border-box;"></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display:flex; justify-content:space-between; align-items:center; padding-top:15px; border-top:2px solid #eee;">
                        <button onclick="closeRevertModal()" 
                                style="padding:12px 30px; border:1px solid #ddd; background:white; color:#333; border-radius:4px; cursor:pointer; font-weight:500; font-size:14px;">
                            Cancel
                        </button>
                        <button onclick="confirmRevert()" 
                                style="padding:12px 40px; background-color:#2e7d32; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:700; font-size:14px; text-transform:uppercase;">
                            Confirm Revert
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.getElementById('mainContent');
            const menuBtn = document.querySelector('.menu-btn');
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
            menuBtn.classList.toggle('active');
        }

        // Search Records
        function searchRecords() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const branch = document.getElementById('branchFilter').value.toLowerCase();
            const rows = document.querySelectorAll('#voidSalesTable tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const branchCell = row.cells[4].textContent.toLowerCase();
                const matchesSearch = text.includes(query);
                const matchesBranch = !branch || branchCell.includes(branch);

                row.style.display = (matchesSearch && matchesBranch) ? '' : 'none';
            });
        }

        // Filter by Date Range
        function filterByDate() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const branchFilter = document.getElementById('branchFilter').value;

            if (!dateFrom || !dateTo) {
                alert('Please select both From and To dates');
                return;
            }

            if (new Date(dateFrom) > new Date(dateTo)) {
                alert('From date cannot be later than To date');
                return;
            }

            loadVoidSales(dateFrom, dateTo, branchFilter);
        }

        // Load void sales from database
        function loadVoidSales(dateFrom, dateTo, branchFilter = '') {
            const tbody = document.getElementById('voidSalesTableBody');
            tbody.innerHTML = '<tr><td colspan="9" style="padding: 40px; text-align: center;">Loading...</td></tr>';

            // Build query parameters
            let params = new URLSearchParams();
            params.append('date_from', dateFrom);
            params.append('date_to', dateTo);
            if (branchFilter) {
                params.append('branch', branchFilter);
            }

            fetch('fetch_void_sales_data.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    tbody.innerHTML = '';

                    if (data.success && data.records && data.records.length > 0) {
                        data.records.forEach(record => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${record.voided_date}</td>
                                <td>${record.date_sold}</td>
                                <td>${record.invoice_no}</td>
                                <td>${record.customer_name}</td>
                                <td>${record.branch}</td>
                                <td style="text-align:left;">${record.void_reason}</td>
                                <td>${record.voided_by}</td>
                                <td><span class="status-badge status-voided">VOIDED</span></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn-view" onclick="viewDetails('${record.invoice_no}')">View</button>
                                        <button class="btn-revert" onclick="revertVoidSale('${record.invoice_no}')">Revert</button>
                                    </div>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });

                        document.getElementById('paginationInfo').textContent = `Showing 1-${data.count} of ${data.count} entries`;
                    } else {
                        tbody.innerHTML = '<tr class="no-data"><td colspan="9">No voided sales found for the selected date range.</td></tr>';
                        document.getElementById('paginationInfo').textContent = 'No records found';
                    }
                })
                .catch(error => {
                    console.error('Error loading void sales:', error);
                    tbody.innerHTML = '<tr class="no-data"><td colspan="9">Error loading data. Please try again.</td></tr>';
                });
        }

        // View Details Modal
        function viewDetails(invoiceNo) {
            // Fetch details from server
            fetch(`get_void_sale_details.php?invoice_no=${encodeURIComponent(invoiceNo)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('view_invoice_title').textContent = data.header.invoice_no;
                        document.getElementById('view_invoice_no').textContent = data.header.invoice_no;
                        document.getElementById('view_date_sold').textContent = data.header.date_sold;
                        document.getElementById('view_date_voided').textContent = data.header.date_voided;
                        document.getElementById('view_customer_name').textContent = data.header.customer_name;
                        document.getElementById('view_branch').textContent = data.header.branch;
                        document.getElementById('view_void_reason').textContent = data.header.void_reason;
                        document.getElementById('view_voided_by').textContent = data.header.voided_by;
                        document.getElementById('view_status').textContent = 'VOIDED';

                        // Populate items
                        const itemsList = document.getElementById('view_items_list');
                        itemsList.innerHTML = '';
                        
                        data.items.forEach(item => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td style="padding:8px; border:1px solid #acacacff;">${item.item_description}</td>
                                <td style="padding:8px; border:1px solid #acacacff; text-align:center;">${item.imei || '-'}</td>
                                <td style="padding:8px; border:1px solid #acacacff; text-align:center;">${item.brand || '-'}</td>
                                <td style="padding:8px; border:1px solid #acacacff; text-align:center;">${item.quantity}</td>
                                <td style="padding:8px; border:1px solid #acacacff; text-align:right;">₱${parseFloat(item.price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td style="padding:8px; border:1px solid #acacacff; text-align:right;">₱${parseFloat(item.total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                            `;
                            itemsList.appendChild(tr);
                        });

                        // Add grand total row
                        const totalRow = document.createElement('tr');
                        totalRow.style.background = '#F5EDE8';
                        totalRow.style.fontWeight = 'bold';
                        totalRow.style.borderTop = '2px solid #1E455D';
                        totalRow.innerHTML = `
                            <td colspan="5" style="padding:12px; border:1px solid #acacacff; text-align:right; font-size:15px;">OVERALL AMOUNT:</td>
                            <td style="padding:12px; border:1px solid #acacacff; text-align:right; color:#1E455D; font-size:15px;">₱${parseFloat(data.header.grand_total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        `;
                        itemsList.appendChild(totalRow);

                        document.getElementById('viewModal').classList.add('show');
                    } else {
                        alert('Error loading void sale details: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error fetching void sale details:', error);
                    alert('Error loading void sale details. Please try again.');
                });
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('show');
        }

        // Revert Void Sale
        function revertVoidSale(invoiceNo) {
            // Fetch details from server
            fetch(`get_void_sale_details.php?invoice_no=${encodeURIComponent(invoiceNo)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('revert_invoice_title').textContent = data.header.invoice_no;
                        document.getElementById('revert_invoice_no').textContent = data.header.invoice_no;
                        document.getElementById('revert_date_sold').textContent = data.header.date_sold;
                        document.getElementById('revert_date_voided').textContent = data.header.date_voided;
                        document.getElementById('revert_customer_name').textContent = data.header.customer_name;
                        document.getElementById('revert_branch').textContent = data.header.branch;
                        document.getElementById('revert_void_reason').textContent = data.header.void_reason;
                        document.getElementById('revert_voided_by').textContent = data.header.voided_by;
                        document.getElementById('revert_total_amount').textContent = '₱' + parseFloat(data.header.grand_total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        document.getElementById('revert_reason').value = '';

                        document.getElementById('revertModal').classList.add('show');
                    } else {
                        alert('Error loading void sale details: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error fetching void sale details:', error);
                    alert('Error loading void sale details. Please try again.');
                });
        }

        function closeRevertModal() {
            document.getElementById('revertModal').classList.remove('show');
        }

        function confirmRevert() {
            const reason = document.getElementById('revert_reason').value.trim();
            const invoiceNo = document.getElementById('revert_invoice_no').textContent;

            if (!reason) {
                alert('Please enter a reason for reverting this void sale');
                document.getElementById('revert_reason').focus();
                return;
            }

            if (reason.length < 10) {
                alert('Please provide a more detailed reason (at least 10 characters)');
                document.getElementById('revert_reason').focus();
                return;
            }

            // Confirmation dialog
            if (confirm(`Are you sure you want to REVERT the void sale ${invoiceNo}?\n\nThis will restore the sale to active status.`)) {
                alert(`Void sale ${invoiceNo} has been successfully reverted!\n\nReason: ${reason}\n\n(Frontend demo only - no actual database changes)`);
                closeRevertModal();

                // Update the status badge in the table (demo only)
                const rows = document.querySelectorAll('#voidSalesTable tbody tr');
                rows.forEach(row => {
                    if (row.cells[2].textContent === invoiceNo) {
                        row.cells[7].innerHTML = '<span class="status-badge status-reverted">REVERTED</span>';
                        row.cells[8].querySelector('.btn-revert').disabled = true;
                        row.cells[8].querySelector('.btn-revert').style.opacity = '0.5';
                        row.cells[8].querySelector('.btn-revert').style.cursor = 'not-allowed';
                    }
                });
            }
        }

        // Close modals when clicking outside
        window.onclick = function (event) {
            const viewModal = document.getElementById('viewModal');
            const revertModal = document.getElementById('revertModal');

            if (event.target == viewModal) {
                closeViewModal();
            }
            if (event.target == revertModal) {
                closeRevertModal();
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Revert Void Sales page loaded - Frontend Demo');
        });
    </script>

</body>

</html>
