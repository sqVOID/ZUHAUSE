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
    <title>Modification Refund</title>
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

        .btn-cancel {
            padding: 5px 14px;
            background-color: #d32f2f;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-cancel:hover {
            background-color: #b71c1c;
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

        .status-processed {
            background-color: #c8e6c9;
            color: #2e7d32;
        }

        .status-cancelled {
            background-color: #ffcdd2;
            color: #c62828;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
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

        .btn-cancel-modal {
            padding: 10px 30px;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
        }

        .btn-cancel-modal:hover {
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

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
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

        .items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ccc;
            margin-top: 10px;
        }

        .items-table thead {
            background: var(--color-gold-pale);
        }

        .items-table th {
            text-align: center;
            padding: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border: 1px solid #ccc;
        }

        .items-table td {
            padding: 10px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
        }

        .cancel-modal-content {
            max-width: 600px;
        }

        .cancel-details {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #d32f2f;
        }

        .cancel-detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }

        .cancel-detail-row:last-child {
            border-bottom: none;
        }

        .cancel-detail-label {
            font-weight: 600;
            width: 150px;
            color: #333;
        }

        .cancel-detail-value {
            flex: 1;
            color: #666;
        }

        .btn-confirm-cancel {
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

        .btn-confirm-cancel:hover {
            background-color: #b71c1c;
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
            <h2>Modification Refund</h2>
        </div>

        <!-- Search and Filter Section -->
        <div class="table-container">
            <div class="search-bar-wrapper">
                <div class="search-controls">
                    <input type="text" id="searchInput" placeholder="Search by Refund No, Customer Name, IMEI...">
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

            <!-- Refund Records Table -->
            <table class="report-table" id="refundTable">
                <thead>
                    <tr>
                        <th>Refund No</th>
                        <th>Date</th>
                        <th>Original Invoice</th>
                        <th>Customer Name</th>
                        <th>Branch</th>
                        <th>IMEI</th>
                        <th>Item Model</th>
                        <th>Refund Amount</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>RFD-2024-0001</td>
                        <td>2024-01-15</td>
                        <td>INV-2024-1001</td>
                        <td>Juan Dela Cruz</td>
                        <td>Main Branch</td>
                        <td>123456789012345</td>
                        <td>iPhone 12 64GB Black</td>
                        <td>₱35,000.00</td>
                        <td>Defective Unit</td>
                        <td><span class="status-badge status-processed">Processed</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-modify" onclick="modifyRefund('RFD-2024-0001')">Modify</button>
                                <button class="btn-preview" onclick="previewRefund('RFD-2024-0001')">Preview</button>
                                <button class="btn-cancel" onclick="cancelRefund('RFD-2024-0001')">Cancel</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>RFD-2024-0002</td>
                        <td>2024-01-16</td>
                        <td>INV-2024-1002</td>
                        <td>Maria Santos</td>
                        <td>Branch 2</td>
                        <td>111222333444555</td>
                        <td>Samsung S23 256GB Black</td>
                        <td>₱42,000.00</td>
                        <td>Customer Changed Mind</td>
                        <td><span class="status-badge status-processed">Processed</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-modify" onclick="modifyRefund('RFD-2024-0002')">Modify</button>
                                <button class="btn-preview" onclick="previewRefund('RFD-2024-0002')">Preview</button>
                                <button class="btn-cancel" onclick="cancelRefund('RFD-2024-0002')">Cancel</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>RFD-2024-0003</td>
                        <td>2024-01-17</td>
                        <td>INV-2024-1003</td>
                        <td>Pedro Garcia</td>
                        <td>Main Branch</td>
                        <td>222333444555666</td>
                        <td>Xiaomi 13 256GB Black</td>
                        <td>₱22,000.00</td>
                        <td>Wrong Item Delivered</td>
                        <td><span class="status-badge status-cancelled">Cancelled</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-preview" onclick="previewRefund('RFD-2024-0003')">Preview</button>
                                <button class="btn-modify" disabled style="opacity:0.5;cursor:not-allowed;">Modify</button>
                                <button class="btn-cancel" disabled style="opacity:0.5;cursor:not-allowed;">Cancel</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>RFD-2024-0004</td>
                        <td>2024-01-18</td>
                        <td>INV-2024-1004</td>
                        <td>Ana Reyes</td>
                        <td>Branch 3</td>
                        <td>333444555666777</td>
                        <td>Oppo Reno 8 128GB Gold</td>
                        <td>₱23,800.00</td>
                        <td>Battery Issue</td>
                        <td><span class="status-badge status-processed">Processed</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-modify" onclick="modifyRefund('RFD-2024-0004')">Modify</button>
                                <button class="btn-preview" onclick="previewRefund('RFD-2024-0004')">Preview</button>
                                <button class="btn-cancel" onclick="cancelRefund('RFD-2024-0004')">Cancel</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>RFD-2024-0005</td>
                        <td>2024-01-19</td>
                        <td>INV-2024-1005</td>
                        <td>Carlos Mendoza</td>
                        <td>Branch 2</td>
                        <td>444555666777888</td>
                        <td>Vivo V27 128GB Purple</td>
                        <td>₱28,000.00</td>
                        <td>Screen Defect</td>
                        <td><span class="status-badge status-pending">Pending</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-modify" onclick="modifyRefund('RFD-2024-0005')">Modify</button>
                                <button class="btn-preview" onclick="previewRefund('RFD-2024-0005')">Preview</button>
                                <button class="btn-cancel" onclick="cancelRefund('RFD-2024-0005')">Cancel</button>
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
                <h3>Modify Refund Transaction</h3>
                <button class="close-modal" onclick="closeModificationModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert-warning">
                    <strong>⚠️ Warning:</strong> You are about to modify an existing refund transaction. Please ensure all changes are accurate before saving.
                </div>

                <!-- Transaction Info Section -->
                <div class="form-section">
                    <div class="form-section-title">Transaction Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Refund No</label>
                            <input type="text" id="mod_refund_no" readonly>
                        </div>
                        <div class="form-group">
                            <label>Refund Date</label>
                            <input type="date" id="mod_refund_date">
                        </div>
                        <div class="form-group">
                            <label>Original Invoice No</label>
                            <input type="text" id="mod_original_invoice">
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

                <!-- Item Information -->
                <div class="form-section">
                    <div class="form-section-title">Item Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>IMEI <span style="color:red">*</span></label>
                            <input type="text" id="mod_imei" placeholder="Enter 15-digit IMEI">
                        </div>
                        <div class="form-group">
                            <label>Item Model <span style="color:red">*</span></label>
                            <input type="text" id="mod_item_model" placeholder="Enter item model">
                        </div>
                        <div class="form-group">
                            <label>Original Price</label>
                            <input type="number" id="mod_original_price" placeholder="0.00" step="0.01">
                        </div>
                    </div>
                </div>

                <!-- Refund Information -->
                <div class="form-section">
                    <div class="form-section-title">Refund Information</div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Refund Amount <span style="color:red">*</span></label>
                            <input type="number" id="mod_refund_amount" placeholder="0.00" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Refund Method</label>
                            <select id="mod_refund_method">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Check">Check</option>
                                <option value="Credit to Account">Credit to Account</option>
                                <option value="Store Credit">Store Credit</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Refund Reason -->
                <div class="form-section">
                    <div class="form-section-title">Refund Reason & Remarks</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Reason for Refund <span style="color:red">*</span></label>
                            <select id="mod_reason">
                                <option value="">Select reason</option>
                                <option value="Defective Unit">Defective Unit</option>
                                <option value="Customer Changed Mind">Customer Changed Mind</option>
                                <option value="Wrong Item Delivered">Wrong Item Delivered</option>
                                <option value="Battery Issue">Battery Issue</option>
                                <option value="Screen Defect">Screen Defect</option>
                                <option value="Not as Described">Not as Described</option>
                                <option value="Warranty Claim">Warranty Claim</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label>Remarks / Additional Notes</label>
                            <textarea id="mod_remarks" placeholder="Enter any additional notes or remarks"></textarea>
                        </div>
                        <div class="form-group full-width">
                            <label>Reason for Modification <span style="color:red">*</span></label>
                            <textarea id="mod_modification_reason" placeholder="Explain why you are modifying this refund transaction" required></textarea>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn-cancel-modal" onclick="closeModificationModal()">Cancel</button>
                <button class="btn-save-changes" onclick="saveModification()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Cancel Refund Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content cancel-modal-content">
            <div class="modal-header">
                <h3>⚠️ Cancel Refund Transaction</h3>
                <button class="close-modal" onclick="closeCancelModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert-danger">
                    <strong>⛔ WARNING: This action will cancel the refund transaction!</strong>
                    Cancelling this refund will reverse all refund actions and restore the original sale.
                </div>

                <div class="cancel-details">
                    <h4 style="margin-top:0; color:#d32f2f;">Refund to be Cancelled:</h4>
                    <div class="cancel-detail-row">
                        <span class="cancel-detail-label">Refund No:</span>
                        <span class="cancel-detail-value" id="cancel_refund_no">-</span>
                    </div>
                    <div class="cancel-detail-row">
                        <span class="cancel-detail-label">Date:</span>
                        <span class="cancel-detail-value" id="cancel_date">-</span>
                    </div>
                    <div class="cancel-detail-row">
                        <span class="cancel-detail-label">Customer:</span>
                        <span class="cancel-detail-value" id="cancel_customer">-</span>
                    </div>
                    <div class="cancel-detail-row">
                        <span class="cancel-detail-label">Branch:</span>
                        <span class="cancel-detail-value" id="cancel_branch">-</span>
                    </div>
                    <div class="cancel-detail-row">
                        <span class="cancel-detail-label">Item:</span>
                        <span class="cancel-detail-value" id="cancel_item">-</span>
                    </div>
                    <div class="cancel-detail-row">
                        <span class="cancel-detail-label">Refund Amount:</span>
                        <span class="cancel-detail-value" id="cancel_amount">-</span>
                    </div>
                </div>

                <div class="form-group">
                    <label><strong>Reason for Cancelling this Refund <span style="color:red">*</span></strong></label>
                    <textarea id="cancel_reason" placeholder="Please provide a detailed reason for cancelling this refund transaction..." style="width:100%; min-height:100px; padding:10px; border:1px solid #ddd; border-radius:4px; font-size:14px; margin-top:8px;" required></textarea>
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn-cancel-modal" onclick="closeCancelModal()">Back</button>
                <button class="btn-confirm-cancel" onclick="confirmCancel()">Confirm Cancel</button>
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

        // Search records
        function searchRecords() {
            const searchValue = document.getElementById('searchInput').value;
            const branchFilter = document.getElementById('branchFilter').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;

            alert('Search functionality - This will fetch refund records from the database\nSearch: ' + searchValue + '\nBranch: ' + branchFilter + '\nDate Range: ' + dateFrom + ' to ' + dateTo);
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

        // Modify refund
        function modifyRefund(refundNo) {
            const sampleData = {
                'RFD-2024-0001': {
                    refund_no: 'RFD-2024-0001',
                    refund_date: '2024-01-15',
                    original_invoice: 'INV-2024-1001',
                    branch: 'Main Branch',
                    customer_name: 'Juan Dela Cruz',
                    contact_number: '09171234567',
                    email: 'juan.delacruz@email.com',
                    address: '123 Main Street, Quezon City, Metro Manila',
                    imei: '123456789012345',
                    item_model: 'iPhone 12 64GB Black',
                    original_price: '35000.00',
                    refund_amount: '35000.00',
                    refund_method: 'Cash',
                    reason: 'Defective Unit',
                    remarks: 'Phone not turning on after 2 days of purchase'
                },
                'RFD-2024-0002': {
                    refund_no: 'RFD-2024-0002',
                    refund_date: '2024-01-16',
                    original_invoice: 'INV-2024-1002',
                    branch: 'Branch 2',
                    customer_name: 'Maria Santos',
                    contact_number: '09181234567',
                    email: 'maria.santos@email.com',
                    address: '456 Market Street, Makati City, Metro Manila',
                    imei: '111222333444555',
                    item_model: 'Samsung S23 256GB Black',
                    original_price: '42000.00',
                    refund_amount: '42000.00',
                    refund_method: 'Bank Transfer',
                    reason: 'Customer Changed Mind',
                    remarks: 'Customer decided to purchase different model'
                },
                'RFD-2024-0004': {
                    refund_no: 'RFD-2024-0004',
                    refund_date: '2024-01-18',
                    original_invoice: 'INV-2024-1004',
                    branch: 'Branch 3',
                    customer_name: 'Ana Reyes',
                    contact_number: '09201234567',
                    email: 'ana.reyes@email.com',
                    address: '321 Residential St, Taguig City, Metro Manila',
                    imei: '333444555666777',
                    item_model: 'Oppo Reno 8 128GB Gold',
                    original_price: '23800.00',
                    refund_amount: '23800.00',
                    refund_method: 'Cash',
                    reason: 'Battery Issue',
                    remarks: 'Battery draining too fast, only lasts 2 hours'
                },
                'RFD-2024-0005': {
                    refund_no: 'RFD-2024-0005',
                    refund_date: '2024-01-19',
                    original_invoice: 'INV-2024-1005',
                    branch: 'Branch 2',
                    customer_name: 'Carlos Mendoza',
                    contact_number: '09211234567',
                    email: 'carlos.mendoza@email.com',
                    address: '555 Commerce Road, Mandaluyong City, Metro Manila',
                    imei: '444555666777888',
                    item_model: 'Vivo V27 128GB Purple',
                    original_price: '28000.00',
                    refund_amount: '28000.00',
                    refund_method: 'Store Credit',
                    reason: 'Screen Defect',
                    remarks: 'Screen has dead pixels and touch issues'
                }
            };

            const data = sampleData[refundNo];
            if (!data) {
                alert('Record not found or cannot be modified!');
                return;
            }

            // Populate the modal
            document.getElementById('mod_refund_no').value = data.refund_no;
            document.getElementById('mod_refund_date').value = data.refund_date;
            document.getElementById('mod_original_invoice').value = data.original_invoice;
            document.getElementById('mod_branch').value = data.branch;
            document.getElementById('mod_customer_name').value = data.customer_name;
            document.getElementById('mod_contact_number').value = data.contact_number;
            document.getElementById('mod_email').value = data.email;
            document.getElementById('mod_address').value = data.address;
            document.getElementById('mod_imei').value = data.imei;
            document.getElementById('mod_item_model').value = data.item_model;
            document.getElementById('mod_original_price').value = data.original_price;
            document.getElementById('mod_refund_amount').value = data.refund_amount;
            document.getElementById('mod_refund_method').value = data.refund_method;
            document.getElementById('mod_reason').value = data.reason;
            document.getElementById('mod_remarks').value = data.remarks;
            document.getElementById('mod_modification_reason').value = '';

            // Show modal
            document.getElementById('modificationModal').classList.add('show');
        }

        // Close modification modal
        function closeModificationModal() {
            document.getElementById('modificationModal').classList.remove('show');
        }

        // Save modification
        function saveModification() {
            const refundNo = document.getElementById('mod_refund_no').value;
            const modificationReason = document.getElementById('mod_modification_reason').value.trim();

            if (!modificationReason) {
                alert('Please provide a reason for modification!');
                return;
            }

            const confirmSave = confirm('Are you sure you want to save these modifications for ' + refundNo + '?');
            
            if (confirmSave) {
                alert('✓ Modification saved successfully!\n\nRefund No: ' + refundNo + '\nReason: ' + modificationReason + '\n\nThe changes have been logged in the system.');
                closeModificationModal();
            }
        }

        // Preview refund
        function previewRefund(refundNo) {
            alert('Preview functionality for Refund: ' + refundNo + '\n\nThis will show full details of the refund transaction.');
        }

        // Cancel refund
        function cancelRefund(refundNo) {
            const sampleData = {
                'RFD-2024-0001': {
                    refund_no: 'RFD-2024-0001',
                    date: '2024-01-15',
                    customer: 'Juan Dela Cruz',
                    branch: 'Main Branch',
                    item: 'iPhone 12 64GB Black (IMEI: 123456789012345)',
                    amount: '₱35,000.00'
                },
                'RFD-2024-0002': {
                    refund_no: 'RFD-2024-0002',
                    date: '2024-01-16',
                    customer: 'Maria Santos',
                    branch: 'Branch 2',
                    item: 'Samsung S23 256GB Black (IMEI: 111222333444555)',
                    amount: '₱42,000.00'
                },
                'RFD-2024-0004': {
                    refund_no: 'RFD-2024-0004',
                    date: '2024-01-18',
                    customer: 'Ana Reyes',
                    branch: 'Branch 3',
                    item: 'Oppo Reno 8 128GB Gold (IMEI: 333444555666777)',
                    amount: '₱23,800.00'
                },
                'RFD-2024-0005': {
                    refund_no: 'RFD-2024-0005',
                    date: '2024-01-19',
                    customer: 'Carlos Mendoza',
                    branch: 'Branch 2',
                    item: 'Vivo V27 128GB Purple (IMEI: 444555666777888)',
                    amount: '₱28,000.00'
                }
            };

            const data = sampleData[refundNo];
            if (!data) {
                alert('This refund cannot be cancelled or has already been cancelled.');
                return;
            }

            // Populate cancel modal
            document.getElementById('cancel_refund_no').textContent = data.refund_no;
            document.getElementById('cancel_date').textContent = data.date;
            document.getElementById('cancel_customer').textContent = data.customer;
            document.getElementById('cancel_branch').textContent = data.branch;
            document.getElementById('cancel_item').textContent = data.item;
            document.getElementById('cancel_amount').textContent = data.amount;
            document.getElementById('cancel_reason').value = '';

            // Show cancel modal
            document.getElementById('cancelModal').classList.add('show');
        }

        // Close cancel modal
        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('show');
        }

        // Confirm cancel
        function confirmCancel() {
            const refundNo = document.getElementById('cancel_refund_no').textContent;
            const reason = document.getElementById('cancel_reason').value.trim();

            if (!reason) {
                alert('Please provide a reason for cancelling this refund!');
                return;
            }

            if (reason.length < 10) {
                alert('Please provide a more detailed reason (at least 10 characters).');
                return;
            }

            const confirmAction = confirm(
                '⚠️ FINAL CONFIRMATION\n\n' +
                'Are you sure you want to cancel this refund transaction?\n\n' +
                'Refund No: ' + refundNo + '\n\n' +
                'This will:\n' +
                '• Cancel the refund\n' +
                '• Restore the original sale\n' +
                '• Return item to inventory\n\n' +
                'Click OK to proceed or Cancel to abort.'
            );

            if (confirmAction) {
                alert(
                    '✓ Refund Cancelled Successfully!\n\n' +
                    'Refund No: ' + refundNo + '\n' +
                    'Reason: ' + reason + '\n\n' +
                    'The refund transaction has been cancelled.\n' +
                    '• Original sale restored\n' +
                    '• Item returned to inventory\n' +
                    '• Transaction marked as CANCELLED'
                );
                closeCancelModal();
                location.reload();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modificationModal = document.getElementById('modificationModal');
            if (event.target === modificationModal) {
                closeModificationModal();
            }
            const cancelModal = document.getElementById('cancelModal');
            if (event.target === cancelModal) {
                closeCancelModal();
            }
        }

        // Pagination
        function previousPage() {
            if (currentPage > 1) {
                currentPage--;
                updatePagination();
            }
        }

        function nextPage() {
            if (currentPage < totalPages) {
                currentPage++;
                updatePagination();
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
            totalPages = 1;
            document.getElementById('totalPages').textContent = totalPages;
        };
    </script>

</body>

</html>
