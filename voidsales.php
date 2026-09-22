<?php
require_once 'session_check.php';
include 'config.php';

// Handle multiple branches for Sub-admin
$branch_codes = [];
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

if (isset($_SESSION['user_branch']) && strcasecmp($system_level, 'Super-Admin') !== 0) {
    $user_branch = $_SESSION['user_branch'];

    // Handle multiple branches (comma-separated)
    $branch_names = array_map('trim', explode(',', $user_branch));

    foreach ($branch_names as $branch_name) {
        $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($branch_name) . "' LIMIT 1");
        if ($branch_query && $branch_query->num_rows > 0) {
            $branch_data = $branch_query->fetch_assoc();
            $branch_codes[] = $branch_data['branch_code'];
        }
    }
}

// For backward compatibility, keep $branch_code as the first branch
$branch_code = !empty($branch_codes) ? $branch_codes[0] : '000';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
       <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Void Sales</title>
    <style>
        :root {
            /* Brand Colors */
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
            border-color: #4caf50;
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
            border-color: #4caf50;
        }

        .search-bar-wrapper input:focus {
            border-color: #4caf50;
        }

        .search-bar-wrapper button {
            padding: 9px 16px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            font-size: 14px;
        }

        .search-bar-wrapper button:hover {
            background-color: var(--color-navy-dark);
        }

        /* Table container */
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccc;
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

        .btn-void {
            padding: 5px 14px;
            background-color: #e53935;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-void:hover {
            background-color: #c62828;
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

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            width: 800px;
            max-width: 90%;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 12px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }

        .close-btn {
            cursor: pointer;
            font-size: 24px;
            color: #999;
            border: none;
            background: transparent;
            transition: color 0.2s;
        }

        .close-btn:hover {
            color: #f44336;
        }

        .modal-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            border: 1px solid #ccc;
        }

        .modal-table thead {
            background: var(--color-gold-pale);
        }

        .modal-table th,
        .modal-table td {
            padding: 10px;
            text-align: center;
            border: 1px solid #ccc;
            font-size: 13px;
            color: #333;
        }

        .modal-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        /* Void Modal Extra Styles */
        .reason-field {
            margin-bottom: 16px;
        }

        .reason-field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }

        .reason-field textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
            font-family: Arial, sans-serif;
            resize: vertical;
            outline: none;
            min-height: 80px;
        }

        .reason-field textarea:focus {
            border-color: #e53935;
        }

        .void-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-back-modal {
            padding: 8px 20px;
            background: #757575;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-back-modal:hover {
            background: #424242;
        }

        .btn-confirm-void {
            padding: 8px 20px;
            background: #e53935;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-confirm-void:hover {
            background: #b71c1c;
        }

        .modal-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .nav-btn {
            width: 32px;
            height: 32px;
            background: #333;
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s, transform 0.1s;
            padding: 0;
        }

        .nav-btn:hover {
            background: #111;
        }

        .nav-btn:disabled {
            background: #aaa;
            cursor: not-allowed;
        }

        /* ========== RESPONSIVE STYLES ========== */

        /* Large Desktop & Laptop (max-width: 1640px) */
        @media (max-width: 1640px) {
            .search-bar-wrapper {
                gap: 12px;
            }
        }

        /* Medium Desktop (max-width: 1366px) */
        @media (max-width: 1366px) {
            .search-bar-wrapper {
                flex-wrap: wrap;
            }

            .search-controls {
                flex: 1;
            }

            .date-filters {
                flex: 1;
            }
        }

        /* Tablet & Medium Desktop (max-width: 1024px) */
        @media (max-width: 1024px) {
            .main-content {
                padding: 15px;
            }

            .content-header {
                flex-wrap: wrap;
            }

            .search-bar-wrapper {
                flex-direction: column;
                gap: 10px;
            }

            .search-controls {
                width: 100%;
                flex-wrap: wrap;
            }

            .search-bar-wrapper input {
                width: 100%;
                border-radius: 4px;
            }

            .search-bar-wrapper button {
                border-radius: 4px;
                margin-left: 10px;
                flex-shrink: 0;
            }

            .date-filters {
                width: 100%;
                margin-left: 0;
                justify-content: flex-start;
                flex-wrap: wrap;
            }

            .table-container {
                padding: 20px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .report-table {
                min-width: 1000px;
                font-size: 12px;
            }

            .report-table th,
            .report-table td {
                font-size: 12px;
                padding: 10px 8px;
                white-space: nowrap;
            }

            .btn-view,
            .btn-void {
                padding: 4px 10px;
                font-size: 11px;
            }
        }

        /* Small Tablet (max-width: 960px) */
        @media (max-width: 960px) {
            .search-bar-wrapper {
                gap: 10px;
            }

            .search-bar-wrapper select {
                font-size: 15px;
                padding: 10px 12px;
            }

            .search-bar-wrapper input[type="date"] {
                font-size: 15px;
                padding: 10px 12px;
            }

            .table-container {
                padding: 15px;
            }

            .report-table {
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
                margin-bottom: 12px;
            }

            .content-header h2 {
                font-size: 18px;
            }

            .search-bar-wrapper {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                padding: 0;
            }

            /* Search controls stack vertically */
            .search-controls {
                flex-direction: row;
                width: 100%;
                gap: 10px;
                flex-wrap: wrap;
            }

            .search-bar-wrapper input {
                width: 100%;
                font-size: 16px;
                padding: 12px;
                border-radius: 4px;
            }

            .search-bar-wrapper button {
                padding: 12px 20px;
                margin-left: 0;
                border-radius: 4px;
                font-size: 15px;
                white-space: nowrap;
                flex-shrink: 0;
            }

            .search-bar-wrapper select {
                width: 100%;
                margin-left: 0;
                font-size: 16px;
                padding: 12px;
            }

            .date-filters {
                flex-direction: row;
                gap: 10px;
                width: 100%;
                margin-left: 0;
                align-items: center;
            }

            .date-filters label {
                margin-left: 0 !important;
                font-size: 14px;
                white-space: nowrap;
            }

            .search-bar-wrapper input[type="date"] {
                flex: 1;
                width: auto;
                margin-left: 0;
                font-size: 16px;
                padding: 12px;
            }

            /* Table container with horizontal scroll */
            .table-container {
                padding: 12px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .report-table {
                min-width: 900px;
                font-size: 12px;
                zoom: 0.75;
            }

            .report-table th,
            .report-table td {
                padding: 10px 8px;
                font-size: 12px;
            }

            /* Action buttons */
            .action-btns {
                flex-direction: row;
                gap: 4px;
                flex-wrap: wrap;
            }

            .btn-view,
            .btn-void {
                padding: 6px 12px;
                font-size: 11px;
            }

            /* Pagination adjustments */
            .pagination-wrapper {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }

            .pagination-wrapper span {
                width: 100%;
                text-align: center;
                margin-bottom: 5px;
            }

            .page-btn {
                flex: 1;
                min-width: 100px;
                padding: 10px;
            }

            /* Modal adjustments */
            .modal-content {
                width: 95%;
                max-width: 95%;
                padding: 15px;
                margin: 10px;
            }

            .modal-header {
                padding-bottom: 10px;
            }

            .modal-header h3 {
                font-size: 16px;
            }

            .modal-table {
                font-size: 11px;
                min-width: 100%;
            }

            .modal-table th,
            .modal-table td {
                padding: 8px 4px;
                font-size: 11px;
            }

            .reason-field textarea {
                font-size: 14px;
                padding: 12px;
            }

            .void-modal-actions {
                flex-direction: column;
                gap: 8px;
            }

            .btn-back-modal,
            .btn-confirm-void {
                width: 100%;
                padding: 12px;
            }

            .modal-nav {
                margin-bottom: 10px;
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

            .search-bar-wrapper {
                gap: 10px;
            }

            .search-controls {
                flex-direction: row;
                gap: 8px;
            }

            .search-bar-wrapper input {
                font-size: 15px;
                padding: 10px;
            }

            .search-bar-wrapper button {
                font-size: 14px;
                padding: 10px 16px;
            }

            .search-bar-wrapper select {
                font-size: 15px;
                padding: 10px;
            }

            .date-filters {
                flex-direction: row;
                gap: 8px;
            }

            .search-bar-wrapper input[type="date"] {
                font-size: 15px;
                padding: 10px;
            }

            .table-container {
                padding: 10px;
            }

            .report-table {
                min-width: 800px;
                zoom: 0.7;
            }

            .report-table th,
            .report-table td {
                padding: 8px 6px;
                font-size: 11px;
            }

            .btn-view,
            .btn-void {
                font-size: 10px;
                padding: 4px 8px;
            }

            .modal-content {
                padding: 12px;
            }

            .modal-header h3 {
                font-size: 14px;
            }

            .modal-table th,
            .modal-table td {
                padding: 6px 3px;
                font-size: 10px;
            }

            .nav-btn {
                width: 28px;
                height: 28px;
            }
        }
    </style>
</head>

<body>

    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()">
            <span></span><span></span><span></span>
        </div>
        <!-- <img src="Icon/motogam_logo.jpg" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="content-header">
            <h2>Void Sales</h2>
        </div>

        <!-- Search Bar and Filters -->
        <div class="search-bar-wrapper">
            <div class="search-controls">
                <input type="text" id="searchInput" placeholder="Enter Invoice Number." oninput="filterTable()">
                <button onclick="filterTable()">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="white" style="vertical-align:middle;">
                        <path
                            d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                    </svg>
                    Search
                </button>
                <?php
                $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
                $is_admin = ($system_level === 'Super-Admin');

                if ($is_admin) {
                    echo '<select id="branchFilter" onchange="filterTable()">';
                    echo '    <option value="">All Branches</option>';

                    $branch_filter_query = "SELECT DISTINCT branch_code, branch_name FROM branches ORDER BY branch_name";
                    $branch_filter_result = $conn->query($branch_filter_query);
                    if ($branch_filter_result && $branch_filter_result->num_rows > 0) {
                        while ($branch_row = $branch_filter_result->fetch_assoc()) {
                            $branch_display = htmlspecialchars($branch_row['branch_name'] . ' - ' . $branch_row['branch_code']);
                            $branch_value = htmlspecialchars($branch_row['branch_code']);
                            echo "<option value=\"{$branch_value}\">{$branch_display}</option>";
                        }
                    }
                    echo '</select>';
                }
                ?>
            </div>
            <div class="date-filters">
                <label for="dateFrom" style="font-size: 14px; color: #333;">From:</label>
                <input type="date" id="dateFrom" value="" onchange="filterTable()">
                <label for="dateTo" style="margin-left: 10px; font-size: 14px; color: #333;">To:</label>
                <input type="date" id="dateTo" value="" onchange="filterTable()">
            </div>
        </div>

        <!-- Report Table -->
        <div class="table-container">
            <table class="report-table" id="reportTable">
                <thead>
                    <tr>
                        <th style="width:15%;">Date Sold</th>
                        <th style="width:18%;">Invoice Number</th>
                        <th style="width:25%;">Customer Name</th>
                        <th style="width:18%;">Branch</th>
                        <th style="width:12%;">Preview</th>
                        <th style="width:12%;">Action</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    <?php
                    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
                    $is_admin = ($system_level === 'Super-Admin');

                    // Build WHERE clause for multiple branches
                    $where_clause = "";
                    if (!$is_admin && !empty($branch_codes)) {
                        $branch_codes_quoted = array_map(function ($code) use ($conn) {
                            return "'" . $conn->real_escape_string($code) . "'";
                        }, $branch_codes);
                        $branch_in_clause = implode(', ', $branch_codes_quoted);
                        $where_clause = "WHERE se.branch_code IN ($branch_in_clause)";
                    }

                    $void_filter = $where_clause ? "AND se.status != 'voided'" : "WHERE se.status != 'voided'";

                    $query = "SELECT se.id, se.created_at, se.invoice_no, se.first_name, se.last_name, se.branch_code, b.branch_name, se.status 
                                FROM sales_entry se
                                LEFT JOIN branches b ON se.branch_code = b.branch_code
                                $where_clause $void_filter
                                ORDER BY se.created_at DESC, se.id DESC LIMIT 500";

                    $result = $conn->query($query);
                    $total_entries = 0;

                    if ($result && $result->num_rows > 0) {
                        $total_entries = $result->num_rows;
                        $idx = 0;
                        while ($row = $result->fetch_assoc()) {
                            $r_date = !empty($row['created_at']) && $row['created_at'] != '1970-01-01 00:00:00' ? date('m/d/Y', strtotime($row['created_at'])) : '';
                            $r_inv = htmlspecialchars($row['invoice_no']);
                            $r_cust = htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name']));

                            $b_name = $row['branch_name'] ? $row['branch_name'] : 'Unknown Branch';
                            $b_code = $row['branch_code'] ? $row['branch_code'] : 'UNK';
                            $r_branch = htmlspecialchars($b_name . ' - ' . $b_code);

                            $r_id = $row['id'];

                            echo "<tr data-id=\"{$r_id}\" data-index=\"{$idx}\">";
                            echo "  <td>{$r_date}</td>";
                            echo "  <td>{$r_inv}</td>";
                            echo "  <td>{$r_cust}</td>";
                            echo "  <td>{$r_branch}</td>";
                            echo "  <td>";
                            echo "      <div class=\"action-btns\">";
                            echo "          <button class=\"btn-view\" onclick=\"previewVoid({$r_id}, {$idx})\">Preview</button>";
                            echo "      </div>";
                            echo "  </td>";
                            echo "  <td>";
                            echo "      <div class=\"action-btns\">";
                            echo "          <button class=\"btn-void\" onclick=\"actionVoid({$r_id})\">Void</button>";
                            echo "      </div>";
                            echo "  </td>";
                            echo "</tr>";
                            $idx++;
                        }
                    } else {
                        echo "<tr class=\"no-data\" id=\"noDataRow\"><td colspan=\"6\">No sales records found.</td></tr>";
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

        <!-- Preview Modal Overlay -->
        <div class="modal-overlay" id="previewModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Invoice Details</h3>
                    <button class="close-btn" onclick="closeModal()"> x</button>
                </div>

                <div class="modal-nav">
                    <button class="nav-btn" id="modalPrevBtn" onclick="modalNav(-1)" title="Previous">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="white">
                            <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" />
                        </svg>
                    </button>
                    <span>Invoice: <strong id="modalInvoiceNo">�</strong></span>
                    <button class="nav-btn" id="modalNextBtn" onclick="modalNav(1)" title="Next">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="white">
                            <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
                        </svg>
                    </button>
                </div>

                <!-- 1st Table (Summary) -->
                <table class="modal-table">
                    <thead>
                        <tr>
                            <th>Date Sold</th>
                            <th>Invoice Number</th>
                            <th>Customer Name</th>
                            <th>Branch</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td id="m_date">--/--/----</td>
                            <td id="m_inv">INV-XXXXX</td>
                            <td id="m_cust">Sample Customer</td>
                            <td id="m_branch">BCH-001</td>
                            <td><button class="btn-void" id="modalVoidBtn" onclick="voidFromModal()">Void</button></td>
                        </tr>
                    </tbody>
                </table>

                <!-- 2nd Table (Items) -->
                <h4 style="margin-bottom:10px; font-size:15px; color:#333;">Purchased Items</h4>
                <table class="modal-table">
                    <thead>
                        <tr>
                            <th>Quantity</th>
                            <th>Model Code</th>
                            <th>SRP</th>
                            <th>Amount</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody id="modalItemsBody">
                        <tr>
                            <td>1</td>
                            <td>Sample Model Item</td>
                            <td>10,000.00</td>
                            <td>10,000.00</td>
                            <td>10,000.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Void Confirmation Modal -->
        <div class="modal-overlay" id="voidModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3> Void Sales</h3>
                    <button class="close-btn" onclick="closeVoidModal()">x</button>
                </div>
            
                <!-- Invoice summary table -->
                <table class="modal-table" style="margin-bottom:18px;">
                    <thead>
                        <tr>
                            <th>Date Sold</th>
                            <th>Invoice Number</th>
                            <th>Customer Name</th>
                            <th>Branch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td id="vm_date">�</td>
                            <td id="vm_inv">�</td>
                            <td id="vm_cust">�</td>
                            <td id="vm_branch">�</td>
                        </tr>
                    </tbody>
                </table>


                <!-- Reason Field -->
                <div class="reason-field">
                    <label for="voidReason">Reason for Void <span style="color:#e53935;">*</span></label>
                    <textarea id="voidReason" placeholder="Enter reason for voiding this invoice..."></textarea>
                </div>

                <!-- Buttons -->
                <div class="void-modal-actions">
                    <input type="hidden" id="voidSaleId" value="">
                    <button class="btn-back-modal" onclick="closeVoidModal()">Back</button>
                    <button class="btn-confirm-void" onclick="confirmVoid()">Void</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.getElementById('mainContent');
            const menuBtn = document.querySelector('.menu-btn');

            // Simply toggle classes - CSS handles the smooth transition
            menuBtn.classList.toggle('active');
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
        }

        // Initialize sidebar state on page load
        function initializeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.getElementById('mainContent');
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
        window.addEventListener('resize', function () {
            // Debounce resize event
            clearTimeout(window.resizeTimer);
            window.resizeTimer = setTimeout(initializeSidebar, 250);
        });

        function toggleSection(titleEl) {
            const section = titleEl.parentElement;
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

        let currentPage = 1;
        const pageSize = 20;

        function filterTable(resetPage = true) {
            if (resetPage === true || typeof resetPage !== 'boolean') {
                currentPage = 1;
            }

            const query = document.getElementById('searchInput').value.toLowerCase();
            const branchFilterElement = document.getElementById('branchFilter');
            const branchFilter = branchFilterElement ? branchFilterElement.value : '';
            const dateFromElement = document.getElementById('dateFrom');
            const dateToElement = document.getElementById('dateTo');
            const dateFrom = dateFromElement ? dateFromElement.value : '';
            const dateTo = dateToElement ? dateToElement.value : '';
            const rows = document.querySelectorAll('#reportTableBody tr:not(.no-data)');

            let matchedRows = [];

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const matchSearch = text.includes(query);

                const branchCell = row.cells[3];
                const branchText = branchCell ? branchCell.textContent : '';
                let matchBranch = true;
                if (branchFilter && branchText) {
                    const branchCodeMatch = branchText.match(/- ([A-Z0-9]+)$/);
                    const rowBranchCode = branchCodeMatch ? branchCodeMatch[1] : '';
                    matchBranch = rowBranchCode === branchFilter;
                }

                const dateSoldCell = row.cells[0];
                const dateSoldText = dateSoldCell ? dateSoldCell.textContent.trim() : '';
                let matchDateRange = true;
                if ((dateFrom || dateTo) && dateSoldText) {
                    const dateParts = dateSoldText.split('/');
                    if (dateParts.length === 3) {
                        const rowDate = `${dateParts[2]}-${dateParts[0].padStart(2, '0')}-${dateParts[1].padStart(2, '0')}`;
                        if (dateFrom && rowDate < dateFrom) matchDateRange = false;
                        if (dateTo && rowDate > dateTo) matchDateRange = false;
                    }
                }

                const match = matchSearch && matchBranch && matchDateRange;
                if (match) {
                    matchedRows.push(row);
                    row.classList.add('matched-row');
                } else {
                    row.classList.remove('matched-row');
                    row.style.display = 'none';
                }
            });

            const totalEntries = matchedRows.length;
            const totalPages = Math.ceil(totalEntries / pageSize) || 1;

            if (currentPage > totalPages) currentPage = totalPages;

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;

            matchedRows.forEach((row, index) => {
                if (index >= startIndex && index < endIndex) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            let infoText = `Showing ${totalEntries} entries`;
            if (totalEntries > 0) {
                const startLabel = startIndex + 1;
                const endLabel = Math.min(endIndex, totalEntries);
                infoText = `Showing ${startLabel} to ${endLabel} of ${totalEntries} entries`;
            }
            document.getElementById('pageInfo').textContent = infoText;

            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            if (prevBtn) prevBtn.disabled = (currentPage === 1);
            if (nextBtn) nextBtn.disabled = (currentPage === totalPages || totalEntries === 0);

            let noDataRow = document.getElementById('noDataRow');
            if (totalEntries === 0) {
                if (!noDataRow) {
                    noDataRow = document.createElement('tr');
                    noDataRow.id = 'noDataRow';
                    noDataRow.className = 'no-data';
                    noDataRow.innerHTML = '<td colspan="6">No records found.</td>';
                    document.getElementById('reportTableBody').appendChild(noDataRow);
                }
                noDataRow.style.display = '';
            } else {
                if (noDataRow) noDataRow.style.display = 'none';
            }
        }

        /* --- Modal Logic --- */
        let currentRowIndex = 0;
        let currentSaleId = null;
        let currentSaleData = null; // Store current sale data

        function getVisibleRows() {
            return Array.from(document.querySelectorAll('#reportTableBody tr.matched-row'));
        }

        function previewVoid(id, idx) {
            const rows = getVisibleRows();
            currentRowIndex = rows.findIndex(r => parseInt(r.dataset.id) === id);
            if (currentRowIndex === -1) currentRowIndex = 0;
            loadModal(id, rows);
        }

        function loadModal(id, rows) {
            if (!rows) rows = getVisibleRows();
            currentSaleId = id; // Store the current sale ID

            fetch('get_sale_details.php?id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.status !== 'success') {
                        alert('Failed to load details: ' + (data.message || 'Unknown error'));
                        return;
                    }

                    const sale = data.sale;
                    const items = data.items;

                    // Store the sale data for later use
                    currentSaleData = sale;

                    console.log('Sale data loaded:', currentSaleId, currentSaleData);

                    // Fill summary table
                    document.getElementById('modalInvoiceNo').textContent = sale.invoice_no;
                    document.getElementById('m_date').textContent = sale.date_sold;
                    document.getElementById('m_inv').textContent = sale.invoice_no;
                    document.getElementById('m_cust').textContent = sale.customer;
                    document.getElementById('m_branch').textContent = sale.branch;

                    // Fill items table
                    const tbody = document.getElementById('modalItemsBody');
                    tbody.innerHTML = '';
                    if (items.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="color:#999;font-style:italic;padding:14px;">No items found.</td></tr>';
                    } else {
                        items.forEach(item => {
                            const quantity = parseFloat(item.quantity) || 1;
                            const totalAmount = parseFloat(item.total) || 0;
                            const srpAmount = parseFloat(item.srp) || 0;
                            const itemAmount = quantity > 0 ? (totalAmount / quantity) : 0;

                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${item.quantity}</td>
                                <td style="text-align:left;">${item.item_code || item.item_description}</td>
                                <td>${srpAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                                <td>${itemAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                                <td>${totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                    }

                    // Nav buttons
                    document.getElementById('modalPrevBtn').disabled = (currentRowIndex <= 0);
                    document.getElementById('modalNextBtn').disabled = (currentRowIndex >= rows.length - 1);

                    // Make sure void button is clickable
                    const voidBtn = document.getElementById('modalVoidBtn');
                    if (voidBtn) {
                        voidBtn.onclick = function () {
                            window.voidFromModal();
                        };
                    }

                    document.getElementById('previewModal').style.display = 'flex';
                })
                .catch(err => alert('Error loading sale details: ' + err));
        }

        function modalNav(dir) {
            const rows = getVisibleRows();
            currentRowIndex += dir;
            if (currentRowIndex < 0) currentRowIndex = 0;
            if (currentRowIndex >= rows.length) currentRowIndex = rows.length - 1;
            const id = parseInt(rows[currentRowIndex].dataset.id);
            loadModal(id, rows);
        }

        function closeModal() {
            document.getElementById('previewModal').style.display = 'none';
            currentSaleId = null;
            currentSaleData = null;
        }

        // Close modal when clicking outside
        document.getElementById('previewModal').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });

        // Void from preview modal - use already loaded data
        window.voidFromModal = function () {
            console.log('voidFromModal called', currentSaleId, currentSaleData);

            if (!currentSaleId || !currentSaleData) {
                alert('Sale data not available. Please try again.');
                return;
            }

            // Close preview modal first
            document.getElementById('previewModal').style.display = 'none';

            // Small delay to ensure modal closes properly
            setTimeout(function () {
                // Populate void modal with already loaded data
                document.getElementById('voidReason').value = '';
                document.getElementById('voidSaleId').value = currentSaleId;

                document.getElementById('vm_date').textContent = currentSaleData.date_sold;
                document.getElementById('vm_inv').textContent = currentSaleData.invoice_no;
                document.getElementById('vm_cust').textContent = currentSaleData.customer;
                document.getElementById('vm_branch').textContent = currentSaleData.branch;

                // Open void modal
                document.getElementById('voidModal').style.display = 'flex';
            }, 100);
        }

        function actionVoid(id) {
            document.getElementById('voidReason').value = '';
            document.getElementById('voidSaleId').value = id;

            fetch('get_sale_details.php?id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.status !== 'success') {
                        alert('Failed to load sale details.');
                        return;
                    }

                    const sale = data.sale;
                    const items = data.items;

                    document.getElementById('vm_date').textContent = sale.date_sold;
                    document.getElementById('vm_inv').textContent = sale.invoice_no;
                    document.getElementById('vm_cust').textContent = sale.customer;
                    document.getElementById('vm_branch').textContent = sale.branch;

                    document.getElementById('voidModal').style.display = 'flex';
                })
                .catch(err => alert('Error: ' + err));
        }

        function closeVoidModal() {
            document.getElementById('voidModal').style.display = 'none';
        }

        function confirmVoid() {
            const reason = document.getElementById('voidReason').value.trim();
            const id = document.getElementById('voidSaleId').value;

            if (!reason) {
                alert('Please enter a reason for voiding this invoice.');
                document.getElementById('voidReason').focus();
                return;
            }

            if (!confirm('Are you sure you want to void this invoice? This action cannot be undone.')) return;

            const btn = document.querySelector('.btn-confirm-void');
            btn.disabled = true;
            btn.textContent = 'Processing...';

            fetch('void_sale.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, reason: reason })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        closeVoidModal();
                        alert('Sale has been voided successfully.');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to void sale.'));
                        btn.disabled = false;
                        btn.textContent = 'Void';
                    }
                })
                .catch(err => {
                    alert('Request failed: ' + err);
                    btn.disabled = false;
                    btn.textContent = 'Void';
                });
        }

        // Close void modal on backdrop click
        document.getElementById('voidModal').addEventListener('click', function (e) {
            if (e.target === this) closeVoidModal();
        });

        function changePage(dir) {
            currentPage += dir;
            filterTable(false);
        }

        document.addEventListener('DOMContentLoaded', () => {
            filterTable();
        });
    </script>

</body>

</html>