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
            max-width: 900px;
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

        .void-details {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid var(--color-green);
        }

        .void-detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }

        .void-detail-row:last-child {
            border-bottom: none;
        }

        .void-detail-label {
            font-weight: 600;
            width: 200px;
            color: #333;
        }

        .void-detail-value {
            flex: 1;
            color: #666;
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

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }

        .form-group label {
            font-size: 13px;
            color: #333;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: white;
            font-family: Arial, sans-serif;
            resize: vertical;
            min-height: 100px;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: var(--color-gold);
            box-shadow: 0 0 0 2px rgba(176, 138, 82, 0.1);
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

        .btn-confirm-revert {
            padding: 10px 40px;
            background-color: var(--color-green);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
        }

        .btn-confirm-revert:hover {
            background-color: var(--color-green-dark);
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-warning strong {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .alert-warning ul {
            margin: 8px 0 0 20px;
            padding: 0;
        }

        .alert-warning li {
            margin: 4px 0;
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

        /* View Details Modal */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .details-table th,
        .details-table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
            font-size: 13px;
        }

        .details-table th {
            background: var(--color-gold-pale);
            font-weight: 600;
            width: 180px;
        }

        .details-table td {
            background: white;
        }

        .items-section {
            margin-top: 20px;
        }

        .items-section h4 {
            font-size: 15px;
            color: var(--color-navy);
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--color-gold-pale);
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
                <tbody>
                    <tr>
                        <td>2024-01-20</td>
                        <td>2024-01-15</td>
                        <td>INV-2024-0001</td>
                        <td>Juan Dela Cruz</td>
                        <td>Main Branch</td>
                        <td>Customer Cancelled Order</td>
                        <td>Admin User</td>
                        <td><span class="status-badge status-voided">VOIDED</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-view" onclick="viewDetails('INV-2024-0001')">View</button>
                                <button class="btn-revert" onclick="revertVoidSale('INV-2024-0001')">Revert</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>2024-01-21</td>
                        <td>2024-01-16</td>
                        <td>INV-2024-0002</td>
                        <td>Maria Santos</td>
                        <td>Branch 2</td>
                        <td>Duplicate Entry</td>
                        <td>Staff User</td>
                        <td><span class="status-badge status-voided">VOIDED</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-view" onclick="viewDetails('INV-2024-0002')">View</button>
                                <button class="btn-revert" onclick="revertVoidSale('INV-2024-0002')">Revert</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>2024-01-22</td>
                        <td>2024-01-17</td>
                        <td>INV-2024-0003</td>
                        <td>Pedro Garcia</td>
                        <td>Main Branch</td>
                        <td>Wrong Pricing Applied</td>
                        <td>Admin User</td>
                        <td><span class="status-badge status-reverted">REVERTED</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-view" onclick="viewDetails('INV-2024-0003')">View</button>
                                <button class="btn-revert" disabled style="opacity:0.5;cursor:not-allowed;">Revert</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>2024-01-23</td>
                        <td>2024-01-18</td>
                        <td>INV-2024-0004</td>
                        <td>Ana Reyes</td>
                        <td>Branch 3</td>
                        <td>Customer Request - Payment Issue</td>
                        <td>Manager User</td>
                        <td><span class="status-badge status-voided">VOIDED</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-view" onclick="viewDetails('INV-2024-0004')">View</button>
                                <button class="btn-revert" onclick="revertVoidSale('INV-2024-0004')">Revert</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>2024-01-24</td>
                        <td>2024-01-19</td>
                        <td>INV-2024-0005</td>
                        <td>Carlos Mendoza</td>
                        <td>Branch 2</td>
                        <td>Item Out of Stock</td>
                        <td>Staff User</td>
                        <td><span class="status-badge status-voided">VOIDED</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-view" onclick="viewDetails('INV-2024-0005')">View</button>
                                <button class="btn-revert" onclick="revertVoidSale('INV-2024-0005')">Revert</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination-wrapper">
                <span>Showing 1-5 of 5 entries</span>
                <button disabled>&laquo; Previous</button>
                <button disabled>Next &raquo;</button>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Void Sale Details</h3>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <table class="details-table">
                    <tr>
                        <th>Invoice Number</th>
                        <td id="view_invoice_no">INV-2024-0001</td>
                    </tr>
                    <tr>
                        <th>Date Sold</th>
                        <td id="view_date_sold">January 15, 2024</td>
                    </tr>
                    <tr>
                        <th>Date Voided</th>
                        <td id="view_date_voided">January 20, 2024</td>
                    </tr>
                    <tr>
                        <th>Customer Name</th>
                        <td id="view_customer_name">Juan Dela Cruz</td>
                    </tr>
                    <tr>
                        <th>Branch</th>
                        <td id="view_branch">Main Branch</td>
                    </tr>
                    <tr>
                        <th>Void Reason</th>
                        <td id="view_void_reason">Customer Cancelled Order</td>
                    </tr>
                    <tr>
                        <th>Voided By</th>
                        <td id="view_voided_by">Admin User</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="status-badge status-voided" id="view_status">VOIDED</span></td>
                    </tr>
                </table>

                <div class="items-section">
                    <h4>Voided Items</h4>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Item Model</th>
                                <th>IMEI/Serial</th>
                                <th>Brand</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="view_items_list">
                            <tr>
                                <td>iPhone 14 128GB Blue</td>
                                <td>123456789012345</td>
                                <td>Apple</td>
                                <td>1</td>
                                <td>₱48,000.00</td>
                                <td>₱48,000.00</td>
                            </tr>
                            <tr>
                                <td>AirPods Pro Gen 2</td>
                                <td>987654321098765</td>
                                <td>Apple</td>
                                <td>1</td>
                                <td>₱12,000.00</td>
                                <td>₱12,000.00</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" style="text-align: right;">Grand Total:</th>
                                <th id="view_grand_total">₱60,000.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Revert Modal -->
    <div id="revertModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Revert Void Sale</h3>
                <button class="close-modal" onclick="closeRevertModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert-warning">
                    <strong>⚠️ Warning: Revert Void Sale Transaction</strong>
                    <ul>
                        <li>This action will restore the voided sale back to active status</li>
                        <li>All items will be marked as sold again</li>
                        <li>Inventory will be adjusted accordingly</li>
                        <li>This action requires proper authorization and reason</li>
                    </ul>
                </div>

                <div class="void-details">
                    <div class="void-detail-row">
                        <span class="void-detail-label">Invoice Number:</span>
                        <span class="void-detail-value" id="revert_invoice_no">INV-2024-0001</span>
                    </div>
                    <div class="void-detail-row">
                        <span class="void-detail-label">Date Sold:</span>
                        <span class="void-detail-value" id="revert_date_sold">January 15, 2024</span>
                    </div>
                    <div class="void-detail-row">
                        <span class="void-detail-label">Date Voided:</span>
                        <span class="void-detail-value" id="revert_date_voided">January 20, 2024</span>
                    </div>
                    <div class="void-detail-row">
                        <span class="void-detail-label">Customer Name:</span>
                        <span class="void-detail-value" id="revert_customer_name">Juan Dela Cruz</span>
                    </div>
                    <div class="void-detail-row">
                        <span class="void-detail-label">Branch:</span>
                        <span class="void-detail-value" id="revert_branch">Main Branch</span>
                    </div>
                    <div class="void-detail-row">
                        <span class="void-detail-label">Original Void Reason:</span>
                        <span class="void-detail-value" id="revert_void_reason">Customer Cancelled Order</span>
                    </div>
                    <div class="void-detail-row">
                        <span class="void-detail-label">Voided By:</span>
                        <span class="void-detail-value" id="revert_voided_by">Admin User</span>
                    </div>
                    <div class="void-detail-row">
                        <span class="void-detail-label">Total Amount:</span>
                        <span class="void-detail-value" id="revert_total_amount">₱60,000.00</span>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Reason for Reverting Void Sale</div>
                    <div class="form-group">
                        <label>Revert Reason<span style="color:red;">*</span></label>
                        <textarea id="revert_reason" placeholder="Enter detailed reason for reverting this void sale (e.g., Customer wants to proceed with purchase, Payment cleared, Error in voiding)..." required></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeRevertModal()">Cancel</button>
                <button class="btn-confirm-revert" onclick="confirmRevert()">Confirm Revert</button>
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

            if (!dateFrom || !dateTo) {
                alert('Please select both From and To dates');
                return;
            }

            if (new Date(dateFrom) > new Date(dateTo)) {
                alert('From date cannot be later than To date');
                return;
            }

            alert(`Filtering void sales from ${dateFrom} to ${dateTo}\n(Frontend demo only)`);
            // In production, this would filter the table based on date range
        }

        // View Details Modal
        function viewDetails(invoiceNo) {
            // Sample data - in production, this would fetch from database
            const sampleData = {
                'INV-2024-0001': {
                    invoice_no: 'INV-2024-0001',
                    date_sold: 'January 15, 2024',
                    date_voided: 'January 20, 2024',
                    customer_name: 'Juan Dela Cruz',
                    branch: 'Main Branch',
                    void_reason: 'Customer Cancelled Order',
                    voided_by: 'Admin User',
                    status: 'VOIDED',
                    grand_total: '₱60,000.00'
                },
                'INV-2024-0002': {
                    invoice_no: 'INV-2024-0002',
                    date_sold: 'January 16, 2024',
                    date_voided: 'January 21, 2024',
                    customer_name: 'Maria Santos',
                    branch: 'Branch 2',
                    void_reason: 'Duplicate Entry',
                    voided_by: 'Staff User',
                    status: 'VOIDED',
                    grand_total: '₱35,500.00'
                }
            };

            const data = sampleData[invoiceNo] || sampleData['INV-2024-0001'];

            document.getElementById('view_invoice_no').textContent = data.invoice_no;
            document.getElementById('view_date_sold').textContent = data.date_sold;
            document.getElementById('view_date_voided').textContent = data.date_voided;
            document.getElementById('view_customer_name').textContent = data.customer_name;
            document.getElementById('view_branch').textContent = data.branch;
            document.getElementById('view_void_reason').textContent = data.void_reason;
            document.getElementById('view_voided_by').textContent = data.voided_by;
            document.getElementById('view_status').textContent = data.status;
            document.getElementById('view_grand_total').textContent = data.grand_total;

            document.getElementById('viewModal').classList.add('show');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('show');
        }

        // Revert Void Sale
        function revertVoidSale(invoiceNo) {
            // Sample data - in production, this would fetch from database
            const sampleData = {
                'INV-2024-0001': {
                    invoice_no: 'INV-2024-0001',
                    date_sold: 'January 15, 2024',
                    date_voided: 'January 20, 2024',
                    customer_name: 'Juan Dela Cruz',
                    branch: 'Main Branch',
                    void_reason: 'Customer Cancelled Order',
                    voided_by: 'Admin User',
                    total_amount: '₱60,000.00'
                },
                'INV-2024-0002': {
                    invoice_no: 'INV-2024-0002',
                    date_sold: 'January 16, 2024',
                    date_voided: 'January 21, 2024',
                    customer_name: 'Maria Santos',
                    branch: 'Branch 2',
                    void_reason: 'Duplicate Entry',
                    voided_by: 'Staff User',
                    total_amount: '₱35,500.00'
                }
            };

            const data = sampleData[invoiceNo] || sampleData['INV-2024-0001'];

            document.getElementById('revert_invoice_no').textContent = data.invoice_no;
            document.getElementById('revert_date_sold').textContent = data.date_sold;
            document.getElementById('revert_date_voided').textContent = data.date_voided;
            document.getElementById('revert_customer_name').textContent = data.customer_name;
            document.getElementById('revert_branch').textContent = data.branch;
            document.getElementById('revert_void_reason').textContent = data.void_reason;
            document.getElementById('revert_voided_by').textContent = data.voided_by;
            document.getElementById('revert_total_amount').textContent = data.total_amount;
            document.getElementById('revert_reason').value = '';

            document.getElementById('revertModal').classList.add('show');
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
