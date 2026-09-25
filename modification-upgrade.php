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
    <title>Modification Upgrade</title>
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

        .btn-modify {
            padding: 5px 14px;
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-modify:hover {
            background-color: var(--color-gold-light);
        }

        .btn-preview {
            padding: 5px 14px;
            background-color: #1e88e5;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-preview:hover {
            background-color: #1565c0;
        }

        .btn-revert {
            padding: 5px 14px;
            background-color: #d32f2f;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-revert:hover {
            background-color: #b71c1c;
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
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            border: 1px solid #888;
            width: 90%;
            max-width: 1200px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--color-navy);
            color: white;
            border-radius: 8px 8px 0 0;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .close-modal {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 25px;
            overflow-y: auto;
            flex: 1;
        }

        .form-section {
            background: white;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--color-navy);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--color-gold-pale);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 13px;
            color: #333;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: white;
            font-family: Arial, sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--color-gold);
            box-shadow: 0 0 0 2px rgba(176, 138, 82, 0.1);
        }

        .form-group input[readonly] {
            background-color: #f5f5f5;
            color: #666;
            cursor: not-allowed;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafafa;
            border-radius: 0 0 8px 8px;
        }

        .btn-cancel {
            padding: 10px 30px;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
        }

        .btn-cancel:hover {
            background-color: #f5f5f5;
        }

        .btn-save-changes {
            padding: 10px 40px;
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
        }

        .btn-save-changes:hover {
            background-color: var(--color-gold-light);
        }

        .items-modification-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ccc;
            margin-top: 10px;
        }

        .items-modification-table thead {
            background: var(--color-gold-pale);
        }

        .items-modification-table th {
            text-align: center;
            padding: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border: 1px solid #ccc;
        }

        .items-modification-table td {
            padding: 10px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
        }

        .items-modification-table input {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            text-align: center;
        }

        .items-modification-table input:focus {
            outline: none;
            border-color: var(--color-gold);
        }

        .reason-badge {
            display: inline-block;
            padding: 4px 12px;
            background: var(--color-gold-pale);
            color: var(--color-navy);
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
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

        .status-active {
            background-color: #c8e6c9;
            color: #2e7d32;
        }

        .status-reverted {
            background-color: #ffcdd2;
            color: #c62828;
        }

        .status-cancelled {
            background-color: #ffcdd2;
            color: #c62828;
        }

        .status-completed {
            background-color: #c8e6c9;
            color: #2e7d32;
        }

        /* Revert Modal Styles */
        .revert-modal-content {
            max-width: 600px;
        }

        .revert-details {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #d32f2f;
        }

        .revert-detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }

        .revert-detail-row:last-child {
            border-bottom: none;
        }

        .revert-detail-label {
            font-weight: 600;
            width: 150px;
            color: #333;
        }

        .revert-detail-value {
            flex: 1;
            color: #666;
        }

        .btn-confirm-revert {
            padding: 10px 40px;
            background-color: #d32f2f;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
        }

        .btn-confirm-revert:hover {
            background-color: #b71c1c;
        }

        .alert-danger {
            background: #ffebee;
            border: 1px solid #f44336;
            color: #c62828;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-danger strong {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .alert-danger ul {
            margin: 8px 0 0 20px;
            padding: 0;
        }

        .alert-danger li {
            margin: 4px 0;
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
            <h2>Modification Upgrade Unit</h2>
        </div>

        <!-- Search and Filter Section -->
        <div class="table-container">
            <div class="search-bar-wrapper">
                <div class="search-controls">
                    <input type="text" id="searchInput" placeholder="Search by Invoice No, Customer Name, IMEI...">
                    <button onclick="searchRecords()">Search</button>
                    <select id="branchFilter">
                        <option value="">All Branches</option>
                        <?php
                        $branches_query = $conn->query("SELECT DISTINCT branch_name FROM branches ORDER BY branch_name");
                        while ($branch = $branches_query->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($branch['branch_name']) . '">' . htmlspecialchars($branch['branch_name']) . '</option>';
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

            <!-- Upgrade Unit Records Table -->
            <table class="report-table" id="upgradeTable">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Customer Name</th>
                        <th>Branch</th>
                        <th>Old IMEI</th>
                        <th>Old Model</th>
                        <th>New IMEI</th>
                        <th>New Model</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>UPG-2024-0001</td>
                        <td>2024-01-15</td>
                        <td>Juan Dela Cruz</td>
                        <td>Main Branch</td>
                        <td>123456789012345</td>
                        <td>iPhone 12 64GB Black</td>
                        <td>987654321098765</td>
                        <td>iPhone 14 128GB Blue</td>
                        <td>₱25,000.00</td>
                        <td>Customer Upgrade</td>
                        <td><span class="status-badge status-active">Active</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-modify" onclick="modifyRecord('UPG-2024-0001')">Modify</button>
                                <button class="btn-preview" onclick="previewRecord('UPG-2024-0001')">Preview</button>
                                <button class="btn-revert" onclick="revertUpgrade('UPG-2024-0001')">Revert</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>UPG-2024-0002</td>
                        <td>2024-01-16</td>
                        <td>Maria Santos</td>
                        <td>Branch 2</td>
                        <td>111222333444555</td>
                        <td>Samsung S21 128GB White</td>
                        <td>555444333222111</td>
                        <td>Samsung S23 256GB Black</td>
                        <td>₱18,500.00</td>
                        <td>Trade-in Upgrade</td>
                        <td><span class="status-badge status-active">Active</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-modify" onclick="modifyRecord('UPG-2024-0002')">Modify</button>
                                <button class="btn-preview" onclick="previewRecord('UPG-2024-0002')">Preview</button>
                                <button class="btn-revert" onclick="revertUpgrade('UPG-2024-0002')">Revert</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>UPG-2024-0003</td>
                        <td>2024-01-17</td>
                        <td>Pedro Garcia</td>
                        <td>Main Branch</td>
                        <td>222333444555666</td>
                        <td>Xiaomi 12T 128GB Silver</td>
                        <td>666555444333222</td>
                        <td>Xiaomi 13 256GB Black</td>
                        <td>₱12,000.00</td>
                        <td>Warranty Upgrade</td>
                        <td><span class="status-badge status-reverted">Reverted</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-preview" onclick="previewRecord('UPG-2024-0003')">Preview</button>
                                <button class="btn-modify" disabled style="opacity:0.5;cursor:not-allowed;">Modify</button>
                                <button class="btn-revert" disabled style="opacity:0.5;cursor:not-allowed;">Revert</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>UPG-2024-0004</td>
                        <td>2024-01-18</td>
                        <td>Ana Reyes</td>
                        <td>Branch 3</td>
                        <td>333444555666777</td>
                        <td>Oppo A96 64GB Blue</td>
                        <td>777666555444333</td>
                        <td>Oppo Reno 8 128GB Gold</td>
                        <td>₱15,800.00</td>
                        <td>Customer Request</td>
                        <td><span class="status-badge status-active">Active</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-modify" onclick="modifyRecord('UPG-2024-0004')">Modify</button>
                                <button class="btn-preview" onclick="previewRecord('UPG-2024-0004')">Preview</button>
                                <button class="btn-revert" onclick="revertUpgrade('UPG-2024-0004')">Revert</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>UPG-2024-0005</td>
                        <td>2024-01-19</td>
                        <td>Carlos Mendoza</td>
                        <td>Branch 2</td>
                        <td>444555666777888</td>
                        <td>Vivo Y35 64GB Black</td>
                        <td>888777666555444</td>
                        <td>Vivo V27 128GB Purple</td>
                        <td>₱20,500.00</td>
                        <td>Defective Unit Replacement</td>
                        <td><span class="status-badge status-active">Active</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-modify" onclick="modifyRecord('UPG-2024-0005')">Modify</button>
                                <button class="btn-preview" onclick="previewRecord('UPG-2024-0005')">Preview</button>
                                <button class="btn-revert" onclick="revertUpgrade('UPG-2024-0005')">Revert</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination-wrapper">
                <span>Page <strong id="currentPage">1</strong> of <strong id="totalPages">1</strong></span>
                <button onclick="previousPage()" id="btnPrev">Previous</button>
                <button onclick="nextPage()" id="btnNext">Next</button>
            </div>
        </div>
    </div>

    <!-- Modification Modal -->
    <div id="modificationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Modify Upgrade Transaction</h3>
                <button class="close-modal" onclick="closeModificationModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert-warning">
                    <strong>⚠️ Warning:</strong> You are about to modify an existing upgrade transaction. Please ensure all changes are accurate before saving.
                </div>

                <!-- Transaction Info Section -->
                <div class="form-section">
                    <div class="form-section-title">Transaction Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Invoice No</label>
                            <input type="text" id="mod_invoice_no" readonly>
                        </div>
                        <div class="form-group">
                            <label>Transaction Date</label>
                            <input type="date" id="mod_transaction_date">
                        </div>
                        <div class="form-group">
                            <label>Branch</label>
                            <select id="mod_branch">
                                <option value="Main Branch">Main Branch</option>
                                <option value="Branch 2">Branch 2</option>
                                <option value="Branch 3">Branch 3</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Customer Information Section -->
                <div class="form-section">
                    <div class="form-section-title">Customer Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Customer Name <span style="color:red">*</span></label>
                            <input type="text" id="mod_customer_name" placeholder="Enter customer name">
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" id="mod_contact_number" placeholder="09XX XXX XXXX">
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" id="mod_email" placeholder="customer@email.com">
                        </div>
                        <div class="form-group full-width">
                            <label>Address</label>
                            <textarea id="mod_address" placeholder="Enter complete address"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Old Device Information -->
                <div class="form-section">
                    <div class="form-section-title">Old Device Information (Trade-In)</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Old IMEI <span style="color:red">*</span></label>
                            <input type="text" id="mod_old_imei" placeholder="Enter 15-digit IMEI">
                        </div>
                        <div class="form-group">
                            <label>Old Item Model <span style="color:red">*</span></label>
                            <input type="text" id="mod_old_model" placeholder="Enter old model">
                        </div>
                        <div class="form-group">
                            <label>Old Device Value</label>
                            <input type="number" id="mod_old_value" placeholder="0.00" step="0.01">
                        </div>
                    </div>
                </div>

                <!-- New Device Information -->
                <div class="form-section">
                    <div class="form-section-title">New Device Information (Upgrade)</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>New IMEI <span style="color:red">*</span></label>
                            <input type="text" id="mod_new_imei" placeholder="Enter 15-digit IMEI">
                        </div>
                        <div class="form-group">
                            <label>New Item Model <span style="color:red">*</span></label>
                            <input type="text" id="mod_new_model" placeholder="Enter new model">
                        </div>
                        <div class="form-group">
                            <label>New Device Price</label>
                            <input type="number" id="mod_new_price" placeholder="0.00" step="0.01">
                        </div>
                    </div>
                </div>

                <!-- Financial Information -->
                <div class="form-section">
                    <div class="form-section-title">Financial Information</div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Upgrade Amount (Additional Payment)</label>
                            <input type="number" id="mod_upgrade_amount" placeholder="0.00" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Payment Method</label>
                            <select id="mod_payment_method">
                                <option value="Cash">Cash</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Debit Card">Debit Card</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Home Credit">Home Credit</option>
                                <option value="GCash">GCash</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Upgrade Reason -->
                <div class="form-section">
                    <div class="form-section-title">Upgrade Reason & Remarks</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Reason for Upgrade <span style="color:red">*</span></label>
                            <select id="mod_reason">
                                <option value="">Select reason</option>
                                <option value="Customer Upgrade">Customer Upgrade</option>
                                <option value="Trade-in Upgrade">Trade-in Upgrade</option>
                                <option value="Warranty Upgrade">Warranty Upgrade</option>
                                <option value="Customer Request">Customer Request</option>
                                <option value="Defective Unit Replacement">Defective Unit Replacement</option>
                                <option value="Promo Upgrade">Promo Upgrade</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label>Remarks / Additional Notes</label>
                            <textarea id="mod_remarks" placeholder="Enter any additional notes or remarks about this modification"></textarea>
                        </div>
                        <div class="form-group full-width">
                            <label>Reason for Modification <span style="color:red">*</span></label>
                            <textarea id="mod_modification_reason" placeholder="Explain why you are modifying this transaction" required></textarea>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModificationModal()">Cancel</button>
                <button class="btn-save-changes" onclick="saveModification()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Revert/Cancel Upgrade Modal -->
    <div id="revertModal" class="modal">
        <div class="modal-content revert-modal-content">
            <div class="modal-header">
                <h3>⚠️ Revert Upgrade Transaction</h3>
                <button class="close-modal" onclick="closeRevertModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert-danger">
                    <strong>⛔ WARNING: This action will cancel the upgrade transaction!</strong>
                    <ul>
                        <li>The new device will be returned to inventory</li>
                        <li>The old device will be returned to customer</li>
                        <li>Payment will be refunded to customer</li>
                        <li>This action cannot be easily undone</li>
                    </ul>
                </div>

                <div class="revert-details">
                    <h4 style="margin-top:0; color:#d32f2f;">Transaction to be Reverted:</h4>
                    <div class="revert-detail-row">
                        <span class="revert-detail-label">Invoice No:</span>
                        <span class="revert-detail-value" id="revert_invoice_no">-</span>
                    </div>
                    <div class="revert-detail-row">
                        <span class="revert-detail-label">Date:</span>
                        <span class="revert-detail-value" id="revert_date">-</span>
                    </div>
                    <div class="revert-detail-row">
                        <span class="revert-detail-label">Customer:</span>
                        <span class="revert-detail-value" id="revert_customer">-</span>
                    </div>
                    <div class="revert-detail-row">
                        <span class="revert-detail-label">Branch:</span>
                        <span class="revert-detail-value" id="revert_branch">-</span>
                    </div>
                    <div class="revert-detail-row">
                        <span class="revert-detail-label">Old Device:</span>
                        <span class="revert-detail-value" id="revert_old_device">-</span>
                    </div>
                    <div class="revert-detail-row">
                        <span class="revert-detail-label">New Device:</span>
                        <span class="revert-detail-value" id="revert_new_device">-</span>
                    </div>
                    <div class="revert-detail-row">
                        <span class="revert-detail-label">Amount:</span>
                        <span class="revert-detail-value" id="revert_amount">-</span>
                    </div>
                </div>

                <div class="form-group">
                    <label><strong>Reason for Reverting this Transaction <span style="color:red">*</span></strong></label>
                    <textarea id="revert_reason" placeholder="Please provide a detailed reason for reverting this upgrade transaction..." style="width:100%; min-height:100px; padding:10px; border:1px solid #ddd; border-radius:4px; font-size:14px; margin-top:8px;" required></textarea>
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeRevertModal()">Cancel</button>
                <button class="btn-confirm-revert" onclick="confirmRevert()">Confirm Revert</button>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.getElementById('mainContent');
            const menuBtn = document.querySelector('.menu-btn');

            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
            menuBtn.classList.toggle('active');
        }

        // Pagination variables
        let currentPage = 1;
        let totalPages = 1;
        let recordsPerPage = 50;
        let allRecords = [];

        // Search records
        function searchRecords() {
            const searchValue = document.getElementById('searchInput').value;
            const branchFilter = document.getElementById('branchFilter').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;

            // In a real implementation, this would make an AJAX call to fetch records
            // For now, showing structure only
            alert('Search functionality - This will fetch upgrade records from the database\nSearch: ' + searchValue + '\nBranch: ' + branchFilter + '\nDate Range: ' + dateFrom + ' to ' + dateTo);
            
            // Example of what would be fetched:
            loadSampleData();
        }

        // Filter by date
        function filterByDate() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            
            if (!dateFrom || !dateTo) {
                alert('Please select both start and end dates');
                return;
            }
            
            searchRecords();
        }

        // Load sample data for demonstration
        function loadSampleData() {
            // Data is already loaded in the HTML, this function can be used for dynamic loading
            alert('Filtering/searching complete. In production, this would fetch filtered results from the database.');
        }

        // Modify record
        function modifyRecord(invoiceNo) {
            // Simulate fetching data for the selected invoice
            const sampleData = {
                'UPG-2024-0001': {
                    invoice_no: 'UPG-2024-0001',
                    transaction_date: '2024-01-15',
                    branch: 'Main Branch',
                    customer_name: 'Juan Dela Cruz',
                    contact_number: '09171234567',
                    email: 'juan.delacruz@email.com',
                    address: '123 Main Street, Quezon City, Metro Manila',
                    old_imei: '123456789012345',
                    old_model: 'iPhone 12 64GB Black',
                    old_value: '15000.00',
                    new_imei: '987654321098765',
                    new_model: 'iPhone 14 128GB Blue',
                    new_price: '40000.00',
                    upgrade_amount: '25000.00',
                    payment_method: 'Cash',
                    reason: 'Customer Upgrade',
                    remarks: 'Customer requested upgrade due to better camera quality'
                },
                'UPG-2024-0002': {
                    invoice_no: 'UPG-2024-0002',
                    transaction_date: '2024-01-16',
                    branch: 'Branch 2',
                    customer_name: 'Maria Santos',
                    contact_number: '09181234567',
                    email: 'maria.santos@email.com',
                    address: '456 Market Street, Makati City, Metro Manila',
                    old_imei: '111222333444555',
                    old_model: 'Samsung S21 128GB White',
                    old_value: '20000.00',
                    new_imei: '555444333222111',
                    new_model: 'Samsung S23 256GB Black',
                    new_price: '38500.00',
                    upgrade_amount: '18500.00',
                    payment_method: 'Credit Card',
                    reason: 'Trade-in Upgrade',
                    remarks: 'Trade-in program participation'
                },
                'UPG-2024-0003': {
                    invoice_no: 'UPG-2024-0003',
                    transaction_date: '2024-01-17',
                    branch: 'Main Branch',
                    customer_name: 'Pedro Garcia',
                    contact_number: '09191234567',
                    email: 'pedro.garcia@email.com',
                    address: '789 Business Ave, Pasig City, Metro Manila',
                    old_imei: '222333444555666',
                    old_model: 'Xiaomi 12T 128GB Silver',
                    old_value: '10000.00',
                    new_imei: '666555444333222',
                    new_model: 'Xiaomi 13 256GB Black',
                    new_price: '22000.00',
                    upgrade_amount: '12000.00',
                    payment_method: 'Bank Transfer',
                    reason: 'Warranty Upgrade',
                    remarks: 'Warranty replacement upgrade'
                },
                'UPG-2024-0004': {
                    invoice_no: 'UPG-2024-0004',
                    transaction_date: '2024-01-18',
                    branch: 'Branch 3',
                    customer_name: 'Ana Reyes',
                    contact_number: '09201234567',
                    email: 'ana.reyes@email.com',
                    address: '321 Residential St, Taguig City, Metro Manila',
                    old_imei: '333444555666777',
                    old_model: 'Oppo A96 64GB Blue',
                    old_value: '8000.00',
                    new_imei: '777666555444333',
                    new_model: 'Oppo Reno 8 128GB Gold',
                    new_price: '23800.00',
                    upgrade_amount: '15800.00',
                    payment_method: 'GCash',
                    reason: 'Customer Request',
                    remarks: 'Customer requested better storage capacity'
                },
                'UPG-2024-0005': {
                    invoice_no: 'UPG-2024-0005',
                    transaction_date: '2024-01-19',
                    branch: 'Branch 2',
                    customer_name: 'Carlos Mendoza',
                    contact_number: '09211234567',
                    email: 'carlos.mendoza@email.com',
                    address: '555 Commerce Road, Mandaluyong City, Metro Manila',
                    old_imei: '444555666777888',
                    old_model: 'Vivo Y35 64GB Black',
                    old_value: '7500.00',
                    new_imei: '888777666555444',
                    new_model: 'Vivo V27 128GB Purple',
                    new_price: '28000.00',
                    upgrade_amount: '20500.00',
                    payment_method: 'Home Credit',
                    reason: 'Defective Unit Replacement',
                    remarks: 'Original unit had hardware defect, upgraded to better model'
                }
            };

            const data = sampleData[invoiceNo];
            if (!data) {
                alert('Record not found!');
                return;
            }

            // Populate the modal with data
            document.getElementById('mod_invoice_no').value = data.invoice_no;
            document.getElementById('mod_transaction_date').value = data.transaction_date;
            document.getElementById('mod_branch').value = data.branch;
            document.getElementById('mod_customer_name').value = data.customer_name;
            document.getElementById('mod_contact_number').value = data.contact_number;
            document.getElementById('mod_email').value = data.email;
            document.getElementById('mod_address').value = data.address;
            document.getElementById('mod_old_imei').value = data.old_imei;
            document.getElementById('mod_old_model').value = data.old_model;
            document.getElementById('mod_old_value').value = data.old_value;
            document.getElementById('mod_new_imei').value = data.new_imei;
            document.getElementById('mod_new_model').value = data.new_model;
            document.getElementById('mod_new_price').value = data.new_price;
            document.getElementById('mod_upgrade_amount').value = data.upgrade_amount;
            document.getElementById('mod_payment_method').value = data.payment_method;
            document.getElementById('mod_reason').value = data.reason;
            document.getElementById('mod_remarks').value = data.remarks;
            document.getElementById('mod_modification_reason').value = '';

            // Show the modal
            document.getElementById('modificationModal').classList.add('show');
        }

        // Close modification modal
        function closeModificationModal() {
            document.getElementById('modificationModal').classList.remove('show');
        }

        // Save modification
        function saveModification() {
            const invoiceNo = document.getElementById('mod_invoice_no').value;
            const modificationReason = document.getElementById('mod_modification_reason').value.trim();

            if (!modificationReason) {
                alert('Please provide a reason for modification!');
                return;
            }

            // Validate required fields
            const customerName = document.getElementById('mod_customer_name').value.trim();
            const oldImei = document.getElementById('mod_old_imei').value.trim();
            const oldModel = document.getElementById('mod_old_model').value.trim();
            const newImei = document.getElementById('mod_new_imei').value.trim();
            const newModel = document.getElementById('mod_new_model').value.trim();
            const reason = document.getElementById('mod_reason').value;

            if (!customerName || !oldImei || !oldModel || !newImei || !newModel || !reason) {
                alert('Please fill in all required fields marked with *');
                return;
            }

            // In production, this would send data to backend
            const confirmSave = confirm('Are you sure you want to save these modifications for ' + invoiceNo + '?');
            
            if (confirmSave) {
                alert('✓ Modification saved successfully!\n\nInvoice: ' + invoiceNo + '\nReason: ' + modificationReason + '\n\nThe changes have been logged in the system.');
                closeModificationModal();
                // Refresh the table or update the row
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('modificationModal');
            if (event.target === modal) {
                closeModificationModal();
            }
            const revertModal = document.getElementById('revertModal');
            if (event.target === revertModal) {
                closeRevertModal();
            }
        }

        // Revert/Cancel Upgrade Transaction
        function revertUpgrade(invoiceNo) {
            // Sample data for the selected invoice
            const sampleData = {
                'UPG-2024-0001': {
                    invoice_no: 'UPG-2024-0001',
                    date: '2024-01-15',
                    customer: 'Juan Dela Cruz',
                    branch: 'Main Branch',
                    old_device: 'iPhone 12 64GB Black (IMEI: 123456789012345)',
                    new_device: 'iPhone 14 128GB Blue (IMEI: 987654321098765)',
                    amount: '₱25,000.00'
                },
                'UPG-2024-0002': {
                    invoice_no: 'UPG-2024-0002',
                    date: '2024-01-16',
                    customer: 'Maria Santos',
                    branch: 'Branch 2',
                    old_device: 'Samsung S21 128GB White (IMEI: 111222333444555)',
                    new_device: 'Samsung S23 256GB Black (IMEI: 555444333222111)',
                    amount: '₱18,500.00'
                },
                'UPG-2024-0004': {
                    invoice_no: 'UPG-2024-0004',
                    date: '2024-01-18',
                    customer: 'Ana Reyes',
                    branch: 'Branch 3',
                    old_device: 'Oppo A96 64GB Blue (IMEI: 333444555666777)',
                    new_device: 'Oppo Reno 8 128GB Gold (IMEI: 777666555444333)',
                    amount: '₱15,800.00'
                },
                'UPG-2024-0005': {
                    invoice_no: 'UPG-2024-0005',
                    date: '2024-01-19',
                    customer: 'Carlos Mendoza',
                    branch: 'Branch 2',
                    old_device: 'Vivo Y35 64GB Black (IMEI: 444555666777888)',
                    new_device: 'Vivo V27 128GB Purple (IMEI: 888777666555444)',
                    amount: '₱20,500.00'
                }
            };

            const data = sampleData[invoiceNo];
            if (!data) {
                alert('This transaction cannot be reverted or has already been reverted.');
                return;
            }

            // Populate revert modal
            document.getElementById('revert_invoice_no').textContent = data.invoice_no;
            document.getElementById('revert_date').textContent = data.date;
            document.getElementById('revert_customer').textContent = data.customer;
            document.getElementById('revert_branch').textContent = data.branch;
            document.getElementById('revert_old_device').textContent = data.old_device;
            document.getElementById('revert_new_device').textContent = data.new_device;
            document.getElementById('revert_amount').textContent = data.amount;
            document.getElementById('revert_reason').value = '';

            // Show revert modal
            document.getElementById('revertModal').classList.add('show');
        }

        // Close revert modal
        function closeRevertModal() {
            document.getElementById('revertModal').classList.remove('show');
        }

        // Confirm revert
        function confirmRevert() {
            const invoiceNo = document.getElementById('revert_invoice_no').textContent;
            const reason = document.getElementById('revert_reason').value.trim();

            if (!reason) {
                alert('Please provide a reason for reverting this transaction!');
                return;
            }

            if (reason.length < 10) {
                alert('Please provide a more detailed reason (at least 10 characters).');
                return;
            }

            const confirmAction = confirm(
                '⚠️ FINAL CONFIRMATION\n\n' +
                'Are you absolutely sure you want to revert/cancel this upgrade transaction?\n\n' +
                'Invoice: ' + invoiceNo + '\n\n' +
                'This will:\n' +
                '• Cancel the upgrade transaction\n' +
                '• Return new device to inventory\n' +
                '• Return old device to customer\n' +
                '• Process refund to customer\n\n' +
                'Click OK to proceed or Cancel to abort.'
            );

            if (confirmAction) {
                // In production, this would send data to backend
                alert(
                    '✓ Transaction Reverted Successfully!\n\n' +
                    'Invoice: ' + invoiceNo + '\n' +
                    'Reason: ' + reason + '\n\n' +
                    'The upgrade transaction has been cancelled and all actions have been reversed.\n' +
                    '• New device returned to inventory\n' +
                    '• Old device returned to customer\n' +
                    '• Refund process initiated\n' +
                    '• Transaction marked as REVERTED'
                );
                closeRevertModal();
                // Refresh the table or update the row to show reverted status
                location.reload(); // Reload to show updated status
            }
        }

        // Preview record
        function previewRecord(invoiceNo) {
            alert('Preview functionality for Invoice: ' + invoiceNo + '\n\nThis will show full details of the upgrade transaction including:\n- Customer information\n- Old device details\n- New device details\n- Payment information\n- Transaction history');
        }

        // Pagination
        function previousPage() {
            if (currentPage > 1) {
                currentPage--;
                updatePagination();
                // Load records for previous page
            }
        }

        function nextPage() {
            if (currentPage < totalPages) {
                currentPage++;
                updatePagination();
                // Load records for next page
            }
        }

        function updatePagination() {
            document.getElementById('currentPage').textContent = currentPage;
            document.getElementById('totalPages').textContent = totalPages;
            document.getElementById('btnPrev').disabled = (currentPage === 1);
            document.getElementById('btnNext').disabled = (currentPage === totalPages);
        }

        // Initialize
        window.onload = function() {
            updatePagination();
            // Set total pages based on sample data
            totalPages = 1;
            document.getElementById('totalPages').textContent = totalPages;
        };
    </script>

</body>

</html>
