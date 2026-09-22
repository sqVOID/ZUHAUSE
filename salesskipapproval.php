<?php
require_once 'session_check.php';
include 'config.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if revert button access is disabled
$revert_button_disabled = (isset($_SESSION['revert_button_access']) && $_SESSION['revert_button_access'] === 'disabled');

// Get logged in user's branch and user info
$user_branch_name = '';
$user_name = '';
$user_system_level = '';

if (isset($_SESSION['user_branch'])) {
    $user_branch_name = $_SESSION['user_branch'];
}
if (isset($_SESSION['username'])) {
    $user_name = $_SESSION['username'];
}
if (isset($_SESSION['system_level'])) {
    $user_system_level = $_SESSION['system_level'];
}

// Get user's branch code
$branch_code = '000';
if (!empty($user_branch_name)) {
    $escaped_branch = $conn->real_escape_string($user_branch_name);
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$escaped_branch' COLLATE utf8mb4_general_ci");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
}

// Fetch branches for filter
$branches_result = null;
if (strcasecmp($user_system_level, 'Super-Admin') === 0) {
    $branches_result = $conn->query("SELECT * FROM branches WHERE status = 'Active' ORDER BY branch_name ASC");
} else {
    $escaped_branch = $conn->real_escape_string($user_branch_name);
    $branches_result = $conn->query("SELECT * FROM branches WHERE branch_name = '$escaped_branch' COLLATE utf8mb4_general_ci AND status = 'Active' ORDER BY branch_name ASC");
}

// Fetch areas for filter
$areas_result = null;
if (strcasecmp($user_system_level, 'Super-Admin') === 0) {
    $areas_result = $conn->query("SELECT DISTINCT area FROM branches WHERE status = 'Active' AND area IS NOT NULL AND area != '' ORDER BY area ASC");
} else {
    $escaped_branch = $conn->real_escape_string($user_branch_name);
    $areas_result = $conn->query("SELECT DISTINCT b.area FROM branches b WHERE b.branch_name = '$escaped_branch' COLLATE utf8mb4_general_ci AND b.status = 'Active' AND b.area IS NOT NULL AND b.area != '' ORDER BY b.area ASC");
}

// Build WHERE clause for filtering
$where_conditions = [];

// Branch filter - Super-Admin sees all, others see only their branch
if (strcasecmp($user_system_level, 'Super-Admin') !== 0) {
    $escaped_branch_code = $conn->real_escape_string($branch_code);
    $where_conditions[] = "sr.branch_code = '$escaped_branch_code'";
}

// Add filter conditions if provided
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $date_from = $conn->real_escape_string($_GET['date_from']);
    $where_conditions[] = "DATE(sr.created_at) >= '$date_from'";
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $date_to = $conn->real_escape_string($_GET['date_to']);
    $where_conditions[] = "DATE(sr.created_at) <= '$date_to'";
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = $conn->real_escape_string($_GET['status']);
    $where_conditions[] = "sr.status = '$status'";
}

if (isset($_GET['branch']) && !empty($_GET['branch'])) {
    $branch_filter = $conn->real_escape_string($_GET['branch']);
    $where_conditions[] = "sr.branch_code = '$branch_filter'";
}

if (isset($_GET['area']) && !empty($_GET['area'])) {
    $area_filter = $conn->real_escape_string($_GET['area']);
    $where_conditions[] = "b.area = '$area_filter'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";

// Fetch skip receipt requests
$requests_query = "
    SELECT 
        sr.*,
        b.branch_name
    FROM skip_receipt_requests sr
    LEFT JOIN branches b ON sr.branch_code COLLATE utf8mb4_general_ci = b.branch_code COLLATE utf8mb4_general_ci
    $where_clause
    ORDER BY sr.created_at DESC
";

$requests_result = $conn->query($requests_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Sales Skip/Cancel Receipt Approval</title>
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
            background-color: #f0f0f0;
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
            background-color: #f5ede0;
            color: #0d3347;
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
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .content-header h2 {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 20px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr) auto;
            gap: 15px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 14px;
            color: #333;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: white;
        }

        .btn-filter {
            padding: 10px 30px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            height: 38px;
            transition: all 0.2s ease;
        }

        .btn-filter:hover {
            background-color: var(--color-navy-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(13, 51, 71, 0.3);
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccc;
            overflow-x: auto;
        }

        .approval-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ccc;
        }

        .approval-table thead {
            background: var(--color-gold-pale);
        }

        .approval-table th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000000;
            border: 1px solid #ccc;
        }

        .approval-table td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
        }

        .approval-table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-reverted {
            background-color: #fff3cd;
            color: #856404;
        }

        .btn-approve {
            background-color: #7cb342;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            margin-right: 5px;
        }

        .btn-approve:hover {
            background-color: #689f38;
        }

        .btn-reject {
            background-color: #ef5350;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-reject:hover {
            background-color: #e53935;
        }

        .btn-revert {
            background-color: #ff9800;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-revert:hover {
            background-color: #f57c00;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
            flex-wrap: wrap;
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
            max-width: 800px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            font-size: 20px;
            font-weight: 700;
            color: #333;
        }

        .modal-body {
            padding: 20px 25px;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
        }

        .detail-value {
            color: #333;
        }

        .modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-close {
            padding: 10px 30px;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-close:hover {
            background-color: #f5f5f5;
        }

        /* Responsive */
        
        /* Large Desktop & Laptop (max-width: 1640px) */
        @media (max-width: 1640px) {
            .filter-row {
                gap: 12px;
            }
        }

        /* Medium Desktop (max-width: 1366px) */
        @media (max-width: 1366px) {
            .filter-row {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Tablet & Medium Desktop (max-width: 1024px) */
        @media (max-width: 1024px) {
            .main-content {
                padding: 15px;
            }

            .content-header h2 {
                font-size: 20px;
            }

            .filter-row {
                grid-template-columns: 1fr 1fr;
            }

            .filter-group:last-child {
                grid-column: span 2;
            }

            .table-container {
                padding: 20px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .approval-table {
                min-width: 1200px;
            }

            .approval-table th,
            .approval-table td {
                font-size: 12px;
                padding: 10px 8px;
                white-space: nowrap;
            }

            .btn-approve,
            .btn-reject,
            .btn-revert {
                padding: 4px 10px;
                font-size: 11px;
            }
        }

        /* Small Tablet (max-width: 960px) */
        @media (max-width: 960px) {
            .filter-section {
                padding: 15px;
            }

            .approval-table {
                zoom: 0.85;
            }
        }

        /* Mobile & Tablet (max-width: 768px) */
        @media (max-width: 768px) {
            /* Sidebar behavior on mobile */
            .sidebar {
                transform: translateX(-100%);
                z-index: 1500;
            }

            .sidebar.hidden {
                transform: translateX(0);
            }

            /* Main content always takes full width */
            .main-content {
                margin-left: 0;
                padding: 12px;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            /* Header shadow adjustment */
            .header::after {
                left: 0;
            }

            .content-header {
                flex-direction: column;
                align-items: stretch;
                margin-bottom: 15px;
            }

            .content-header h2 {
                font-size: 18px;
            }

            .filter-section {
                padding: 12px;
            }

            .filter-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .filter-group label {
                font-size: 13px;
            }

            .filter-group input,
            .filter-group select {
                font-size: 16px;
                padding: 12px;
            }

            .btn-filter {
                width: 100%;
                padding: 12px 20px;
                font-size: 15px;
            }

            .table-container {
                padding: 12px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .approval-table {
                min-width: 1100px;
                zoom: 0.75;
            }

            .approval-table th,
            .approval-table td {
                padding: 10px 8px;
                font-size: 12px;
            }

            .action-buttons {
                flex-direction: row;
                gap: 4px;
            }

            .btn-approve,
            .btn-reject,
            .btn-revert {
                padding: 6px 12px;
                font-size: 11px;
            }

            .status-badge {
                font-size: 11px;
                padding: 3px 8px;
            }

            /* Modal adjustments */
            .modal-content {
                width: 95%;
                max-width: 95%;
                margin: 10px;
            }

            .modal-header {
                padding: 15px;
                font-size: 18px;
            }

            .modal-body {
                padding: 15px;
            }

            .detail-row {
                grid-template-columns: 1fr;
                gap: 5px;
                margin-bottom: 12px;
                padding-bottom: 12px;
            }

            .detail-label {
                font-size: 13px;
            }

            .detail-value {
                font-size: 14px;
            }

            .modal-footer {
                padding: 12px 15px;
            }

            .btn-close {
                width: 100%;
                padding: 12px;
                font-size: 15px;
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

            .filter-section {
                padding: 10px;
            }

            .filter-row {
                gap: 10px;
            }

            .filter-group input,
            .filter-group select {
                font-size: 15px;
                padding: 10px;
            }

            .btn-filter {
                font-size: 14px;
                padding: 10px 16px;
            }

            .table-container {
                padding: 10px;
            }

            .approval-table {
                min-width: 1000px;
                zoom: 0.7;
            }

            .approval-table th,
            .approval-table td {
                padding: 8px 6px;
                font-size: 11px;
            }

            .btn-approve,
            .btn-reject,
            .btn-revert {
                padding: 4px 8px;
                font-size: 10px;
            }

            .status-badge {
                font-size: 10px;
                padding: 2px 6px;
            }

            .modal-header {
                padding: 12px;
                font-size: 16px;
            }

            .modal-body {
                padding: 12px;
            }

            .detail-label,
            .detail-value {
                font-size: 12px;
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
        <!-- <img src="Icon/motogam_logo.jpg" alt="Logo" class="logo"> -->
    </div>

    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Cancel Invoice No Approval</h2>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" id="dateFrom" value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                </div>
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" id="dateTo" value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select id="statusFilter">
                        <option value="">All</option>
                        <option value="Pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                        <option value="Reverted" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Reverted') ? 'selected' : ''; ?>>Reverted</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Area</label>
                    <select id="areaFilter">
                        <option value="">All Areas</option>
                        <?php
                        if ($areas_result && $areas_result->num_rows > 0) {
                            while ($area = $areas_result->fetch_assoc()) {
                                $selected = (isset($_GET['area']) && $_GET['area'] == $area['area']) ? 'selected' : '';
                                echo "<option value='{$area['area']}' $selected>{$area['area']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Branch</label>
                    <select id="branchFilter">
                        <option value="">All Branches</option>
                        <?php
                        if ($branches_result && $branches_result->num_rows > 0) {
                            while ($branch = $branches_result->fetch_assoc()) {
                                $selected = (isset($_GET['branch']) && $_GET['branch'] == $branch['branch_code']) ? 'selected' : '';
                                echo "<option value='{$branch['branch_code']}' $selected>{$branch['branch_name']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <button class="btn-filter" onclick="filterData()">Filter</button>
            </div>
        </div>

        <!-- Table Section -->
        <div class="table-container">
            <table class="approval-table">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Date</th>
                        <th>Sales Invoice</th>
                        <th>Branch</th>
                        <th>Requested By</th>
                        <th>Reason Cancel</th>
                        <th>Status</th>
                        <th>Approved By</th>
                        <th>Reverted By</th>
                        <th>Reason Revert</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="approvalTableBody">
                    <?php
                    if ($requests_result && $requests_result->num_rows > 0) {
                        while ($row = $requests_result->fetch_assoc()) {
                            $status_class = strtolower($row['status']);
                            $status_badge_class = "status-" . $status_class;
                            $date_display = date('Y-m-d', strtotime($row['created_at']));
                            
                            // Determine who processed the request
                            $processed_by = '-';
                            if ($row['status'] == 'Approved' && !empty($row['approved_by'])) {
                                $processed_by = htmlspecialchars($row['approved_by']);
                            } elseif ($row['status'] == 'Rejected' && !empty($row['rejected_by'])) {
                                $processed_by = htmlspecialchars($row['rejected_by']);
                            } elseif ($row['status'] == 'Reverted' && !empty($row['approved_by'])) {
                                $processed_by = htmlspecialchars($row['approved_by']);
                            }
                            
                            // Check reverted by and reason
                            $reverted_by = '-';
                            $revert_reason = '-';
                            if ($row['status'] == 'Reverted') {
                                if (!empty($row['reverted_by'])) {
                                    $reverted_by = htmlspecialchars($row['reverted_by']);
                                }
                                if (!empty($row['revert_reason'])) {
                                    $revert_reason = htmlspecialchars($row['revert_reason']);
                                }
                            }
                            
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['request_id']) . "</td>";
                            echo "<td>" . $date_display . "</td>";
                            echo "<td>" . htmlspecialchars($row['invoice_no']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['requested_by']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['reason']) . "</td>";
                            echo "<td><span class='status-badge $status_badge_class'>" . htmlspecialchars($row['status']) . "</span></td>";
                            echo "<td>" . $processed_by . "</td>";
                            echo "<td>" . $reverted_by . "</td>";
                            echo "<td>" . $revert_reason . "</td>";
                            echo "<td>";
                            echo "<div class='action-buttons'>";
                            
                            if ($row['status'] == 'Pending') {
                                echo "<button class='btn-approve' onclick=\"approveRequest('" . htmlspecialchars($row['id']) . "')\">Approve</button>";
                                echo "<button class='btn-reject' onclick=\"rejectRequest('" . htmlspecialchars($row['id']) . "')\">Reject</button>";
                            } elseif ($row['status'] == 'Approved') {
                                // Check if revert button access is disabled
                                if (!$revert_button_disabled) {
                                    echo "<button class='btn-revert' onclick=\"revertRequest('" . htmlspecialchars($row['id']) . "')\">Revert</button>";
                                } else {
                                    echo "<span style='color: #999;'>Access Restricted</span>";
                                }
                            } else {
                                echo "<span style='color: #999;'>No actions available</span>";
                            }
                            
                            echo "</div>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='11' style='text-align: center; padding: 40px; color: #999;'>";
                        echo "<strong>No skip receipt requests found.</strong><br>";
                        echo "There are no skip/cancel receipt requests matching your criteria.";
                        echo "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn = document.querySelector('.menu-btn');

            // Simply toggle classes - CSS handles the smooth transition
            menuBtn.classList.toggle('active');
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
        }

        // Initialize sidebar state on page load
        function initializeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn = document.querySelector('.menu-btn');

            // On mobile, start with sidebar closed (CSS hides it without .hidden)
            if (window.innerWidth <= 768) {
                if (sidebar.classList.contains('hidden')) {
                    sidebar.classList.remove('hidden');
                }
                if (!mainContent.classList.contains('expanded')) {
                    mainContent.classList.add('expanded');
                }
                // menuBtn should NOT have active class when sidebar is hidden
                if (menuBtn.classList.contains('active')) {
                    menuBtn.classList.remove('active');
                }
            } else {
                // On desktop, sidebar is visible by default (no hidden class)
                if (sidebar.classList.contains('hidden')) {
                    sidebar.classList.remove('hidden');
                }
                if (mainContent.classList.contains('expanded')) {
                    mainContent.classList.remove('expanded');
                }
                // menuBtn should have active class when sidebar is visible
                if (!menuBtn.classList.contains('active')) {
                    menuBtn.classList.add('active');
                }
            }
        }

        // Run on page load
        document.addEventListener('DOMContentLoaded', initializeSidebar);
        
        // Run on window resize to handle orientation changes
        window.addEventListener('resize', function() {
            // Debounce resize event
            clearTimeout(window.resizeTimer);
            window.resizeTimer = setTimeout(initializeSidebar, 250);
        });

        function toggleSection(element) {
            const section = element.closest('.menu-section');
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

        function filterData() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const status = document.getElementById('statusFilter').value;
            const area = document.getElementById('areaFilter').value;
            const branch = document.getElementById('branchFilter').value;

            // Build query string
            const params = new URLSearchParams();
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            if (status) params.append('status', status);
            if (area) params.append('area', area);
            if (branch) params.append('branch', branch);

            // Reload page with filters
            window.location.href = 'salesskipapproval.php?' + params.toString();
        }

        function approveRequest(requestId) {
            if (confirm('Are you sure you want to approve this skip receipt request?')) {
                fetch('process_skip_receipt.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=approve&request_id=' + requestId
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Request approved successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing the request.');
                });
            }
        }

        function rejectRequest(requestId) {
            const reason = prompt('Please enter the reason for rejection:');
            if (reason && reason.trim()) {
                fetch('process_skip_receipt.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=reject&request_id=' + requestId + '&rejection_reason=' + encodeURIComponent(reason)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Request rejected successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing the request.');
                });
            }
        }

        function revertRequest(requestId) {
            const reason = prompt('Please enter the reason for reverting this approval:');
            if (reason && reason.trim()) {
                if (confirm('Are you sure you want to revert this approved skip request? This will decrement the invoice number.')) {
                    fetch('process_skip_receipt.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=revert&request_id=' + requestId + '&revert_reason=' + encodeURIComponent(reason)
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            alert('Request reverted successfully! Invoice number has been restored.');
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while processing the request.');
                    });
                }
            }
        }

        // Set today's date as default for date filters only if no filters are applied
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('date_from') && !urlParams.has('date_to')) {
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('dateFrom').value = today;
                document.getElementById('dateTo').value = today;
            }
        }
    </script>
</body>

</html>
