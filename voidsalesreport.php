<?php
require_once 'session_check.php';
include 'config.php';

$branch_code = '000';
if (isset($_SESSION['user_branch'])) {
    $user_branch = $_SESSION['user_branch'];
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch'");
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
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Void Sales Report</title>
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
            gap: 10px;
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

        .btn-preview-all {
            padding: 8px 20px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-preview-all:hover {
            background-color: var(--color-navy-dark);
        }
            font-weight: 500;
            display: flex;
            align-items: center;
            margin-left: 15px;
        }

        .btn-preview-all:hover {
            background-color: #111111;
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
        }

        .search-bar-wrapper button:hover {
            background-color: #111111;
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
            background-color: #f0fff0;
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

        .btn-print {
            padding: 5px 14px;
            background-color: #7cb342;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-print:hover {
            background-color: #689f38;
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
            display: none;
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

        .page-btn:hover:not(:disabled) {
            background: #f0f0f0;
            border-color: #ccc;
            color: #333;
        }

        .page-btn.active {
            background: #333333;
            color: white;
            border-color: #333333;
        }

        .page-btn.active:hover:not(:disabled) {
            background: #f0f0f0 !important;
            border-color: #ccc !important;
            color: #333 !important;
        }


        /* Stack search controls vertically below 1169px */
        @media (max-width: 1169px) {
            .search-bar-wrapper {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 10px;
            }

            .search-controls {
                width: 100%;
                flex-wrap: wrap;
            }

            .date-filters {
                width: 100%;
                margin-left: 0 !important;
            }
        }

        /* Responsive: Show only icons below 930px */
        @media (max-width: 930px) {

            /* Hide text inside buttons, keep only icons */
            .search-bar-wrapper button svg {
                margin-right: 0 !important;
            }

            /* Hide the "Search" text */
            .search-bar-wrapper button {
                font-size: 0;
                padding: 10px;
                width: 40px;
                height: 40px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            /* Keep SVG icon visible */
            .search-bar-wrapper button svg {
                font-size: 16px;
                width: 18px;
                height: 18px;
            }

            /* Make input smaller */
            .search-bar-wrapper input {
                width: 150px !important;
            }

            /* Compact date inputs */
            .search-bar-wrapper input[type="date"] {
                width: 140px;
            }
        }

        /* Stack search controls vertically below 510px */
        @media (max-width: 510px) {
            .search-bar-wrapper {
                flex-direction: column;
                align-items: stretch !important;
                gap: 10px;
            }

            .search-controls {
                flex-direction: column;
                align-items: stretch !important;
                gap: 10px;
                width: 100%;
            }

            /* Stack date filters vertically */
            .date-filters {
                flex-direction: column;
                align-items: stretch !important;
                gap: 10px;
                width: 100%;
                margin-left: 0 !important;
            }

            /* Hide date labels on small screens */
            .date-filters label {
                display: none;
            }

            .search-bar-wrapper input,
            .search-bar-wrapper input[type="date"],
            .search-bar-wrapper select {
                width: 100% !important;
                max-width: 100%;
                margin-left: 0 !important;
            }

            /* Make search button icon-only */
            .search-bar-wrapper button {
                font-size: 0 !important;
                padding: 10px !important;
                width: 44px !important;
                height: 44px !important;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            /* Keep search button icon visible */
            .search-bar-wrapper button svg {
                margin-right: 0 !important;
                font-size: 16px;
                width: 18px;
                height: 18px;
            }

            /* Make FILTER button icon-only */
            .btn-filter {
                font-size: 0 !important;
                padding: 10px !important;
                width: 44px !important;
                height: 44px !important;
                margin-left: 0 !important;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            /* Keep FILTER SVG icon visible */
            .btn-filter svg {
                margin-right: 0 !important;
                width: 18px;
                height: 18px;
            }

            /* Make PREVIEW ALL button icon-only */
            .btn-preview-all {
                font-size: 0 !important;
                padding: 10px !important;
                width: 44px !important;
                height: 44px !important;
                margin-left: 0 !important;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            /* Keep PREVIEW ALL SVG icon visible */
            .btn-preview-all svg {
                margin-right: 0 !important;
                width: 18px;
                height: 18px;
            }
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

            .main-content.expanded {
                margin-left: 0;
            }

            .search-bar-wrapper input {
                width: 180px;
            }

            .content-header h2 {
                font-size: 18px;
            }

            /* Enable horizontal scrolling for tables on mobile */
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                min-width: 800px;
                display: table;
            }

            /* Add scrollbar styling for better visibility */
            .table-container::-webkit-scrollbar {
                height: 8px;
            }

            .table-container::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }

            .table-container::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 4px;
            }

            .table-container::-webkit-scrollbar-thumb:hover {
                background: #555;
            }
        }

        @media (max-width: 480px) {
            .header {
                height: 50px;
                padding: 0 10px;
                gap: 10px;
            }

            .sidebar {
                top: 50px;
                height: calc(100vh - 50px);
                width: 220px;
            }

            .main-content {
                margin-top: 50px;
                padding: 15px;
            }

            .content-header h2 {
                font-size: 16px;
            }

            .search-bar-wrapper input {
                width: 100%;
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
            <h2>Void Sales Report</h2>
            <?php if (isset($user_branch)): ?>
                <div
                    style="margin-left:auto; font-weight:bold; color:#d32f2f; font-size:18px; text-transform:uppercase; display: none;">
                    <?php
                    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
                    if ($system_level === 'Super-Admin') {
                        echo "All Branches";
                    } else {
                        echo htmlspecialchars($user_branch) . ' - ' . htmlspecialchars($branch_code);
                    }
                    ?>
                </div>
                <?php
            endif; ?>
        </div>

        <!-- Search Bar and Filters -->
        <div class="search-bar-wrapper">
            <div class="search-controls">
                <input type="text" id="searchInput" placeholder="Enter Invoice Number" oninput="filterTable(false)">
                <button onclick="filterTable(false)">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="white" style="vertical-align:middle;">
                        <path
                            d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                    </svg>
                    Search
                </button>
                <?php
                // Show branch filter for Super-Admin and Sub-admin users
                $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
                $is_admin = ($system_level === 'Super-Admin');
                $is_super_admin = ($system_level === 'Super-Admin');
                $is_sub_admin = ($system_level === 'Sub-admin');

                if ($is_super_admin || $is_sub_admin) {
                    echo '<select id="branchFilter" onchange="filterTable(false)">';
                    echo '    <option value="">Select Branch</option>';
                    echo '    <option value="ALL">All Branches</option>';

                    // Get branches based on user level
                    if ($is_super_admin) {
                        // Super-Admin sees all branches
                        $branch_filter_query = "SELECT DISTINCT branch_code, branch_name FROM branches ORDER BY branch_name";
                    } else {
                        // Sub-admin sees only their assigned branches
                        $user_branch = isset($_SESSION['user_branch']) ? $_SESSION['user_branch'] : '';
                        $branch_names = array_map('trim', explode(',', $user_branch));
                        $branch_names_quoted = array_map(function ($name) use ($conn) {
                            return "'" . $conn->real_escape_string($name) . "'";
                        }, $branch_names);
                        $branch_in_clause = implode(', ', $branch_names_quoted);
                        $branch_filter_query = "SELECT DISTINCT branch_code, branch_name FROM branches WHERE branch_name IN ($branch_in_clause) ORDER BY branch_name";
                    }

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
                <input type="date" id="dateFrom" value="<?php echo date('Y-m-d'); ?>">
                <label for="dateTo" style="margin-left: 10px; font-size: 14px; color: #333;">To:</label>
                <input type="date" id="dateTo" value="<?php echo date('Y-m-d'); ?>">
                <button class="btn-filter" onclick="filterTable(true)">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="white"
                        style="vertical-align:middle; margin-right: 5px;">
                        <path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z" />
                    </svg>
                    FILTER
                </button>
                <button class="btn-preview-all" onclick="previewAllVoidSales()">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="white"
                        style="vertical-align:middle; margin-right: 5px;">
                        <path
                            d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z" />
                    </svg>
                    PREVIEW ALL
                </button>
            </div>
        </div>

        <!-- Report Table -->
        <div class="table-container">
            <table class="report-table" id="reportTable">
                <thead>
                    <tr>
                        <th style="width:10%;">Date Voided</th>
                        <th style="width:10%;">Date Sold</th>
                        <th style="width:15%;">Invoice No.</th>
                        <th style="width:15%;">Customer Name</th>
                        <th style="width:15%;">Branch</th>
                        <th style="width:20%;">Void Reason</th>
                        <th style="width:15%;">Voided By</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    <tr class="select-filter-msg no-data">
                        <td colspan="7">SELECT A FILTER TO DISPLAY THE DATA</td>
                    </tr>
                    <tr class="no-data" style="display: none;">
                        <td colspan="7">No voided sales found.</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination-wrapper">
                <span id="pageInfo">Showing 1–<?php echo $total_entries; ?> of <?php echo $total_entries; ?>
                    entries</span>
                <button class="page-btn active" id="prevBtn" onclick="changePage(-1)" disabled>&laquo; Prev</button>
                <button class="page-btn active" id="nextBtn" onclick="changePage(1)">Next &raquo;</button>
            </div>
        </div>

    </div>

    <script>
        /* --- Sidebar / Section toggle --- */
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.getElementById('mainContent');
            const menuBtn = document.querySelector('.menu-btn');
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
            menuBtn.classList.toggle('active');
        }

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

        /* --- Live search filter --- */
        function filterTable(isButtonClick = false) {
            // If this is a button click and branch filter exists, validate it
            const branchFilterElement = document.getElementById('branchFilter');
            if (isButtonClick && branchFilterElement) {
                const branchFilter = branchFilterElement.value;
                if (!branchFilter) {
                    alert('Please select a branch before filtering.');
                    branchFilterElement.focus();
                    return;
                }
            }

            // If this is a button click, load data from server
            if (isButtonClick) {
                loadVoidSalesData();
                return;
            }

            // If no real data rows are loaded yet (only placeholder rows exist),
            // do not alter the display — keep "SELECT A FILTER" message visible.
            const realDataRows = Array.from(document.querySelectorAll('#reportTableBody tr')).filter(r =>
                !r.classList.contains('no-data') && !r.classList.contains('select-filter-msg')
            );
            const selectFilterMsg = document.querySelector('.select-filter-msg');
            if (realDataRows.length === 0) {
                // No data loaded yet — ensure the select-filter message stays visible
                if (selectFilterMsg) selectFilterMsg.style.display = '';
                return;
            }

            // Otherwise, filter existing table data
            const query = document.getElementById('searchInput').value.toLowerCase();
            const branchFilter = branchFilterElement ? branchFilterElement.value : '';
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const rows = document.querySelectorAll('#reportTableBody tr');
            let visibleCount = 0;

            // Hide the select-filter message
            if (selectFilterMsg) selectFilterMsg.style.display = 'none';


            rows.forEach(row => {
                // Skip the no-data row and select-filter-msg
                if (row.classList.contains('no-data') || row.classList.contains('select-filter-msg')) return;

                const text = row.textContent.toLowerCase();
                const branchCell = row.cells[4]; // Branch column is the 5th column (index 4)
                const branchText = branchCell ? branchCell.textContent : '';
                const dateVoidedCell = row.cells[0]; // Date Voided column is the 1st column (index 0)
                const dateVoidedText = dateVoidedCell ? dateVoidedCell.textContent.trim() : '';

                // Check if row matches search query
                const matchesSearch = text.includes(query);

                // Check if row matches branch filter (only if filter exists and is not "ALL")
                let matchesBranch = true;
                if (branchFilter && branchFilter !== 'ALL' && branchText) {
                    // Extract branch code from "Branch Name - CODE" format
                    const branchCodeMatch = branchText.match(/- ([A-Z0-9]+)$/);
                    const rowBranchCode = branchCodeMatch ? branchCodeMatch[1] : '';
                    matchesBranch = rowBranchCode === branchFilter;
                }

                // Check if row matches date range filter
                let matchesDateRange = true;
                if ((dateFrom || dateTo) && dateVoidedText) {
                    // Convert MM/DD/YYYY to YYYY-MM-DD for comparison
                    const dateParts = dateVoidedText.split('/');
                    if (dateParts.length === 3) {
                        const rowDate = `${dateParts[2]}-${dateParts[0].padStart(2, '0')}-${dateParts[1].padStart(2, '0')}`;

                        if (dateFrom && rowDate < dateFrom) {
                            matchesDateRange = false;
                        }
                        if (dateTo && rowDate > dateTo) {
                            matchesDateRange = false;
                        }
                    }
                }

                const shouldShow = matchesSearch && matchesBranch && matchesDateRange;
                row.style.display = shouldShow ? '' : 'none';
                if (shouldShow) visibleCount++;
            });

            // Update page info text
            document.getElementById('pageInfo').textContent =
                'Showing ' + visibleCount + ' of ' + rows.length + ' entries';

            // Show/hide the existing no-data row based on visible count
            const existingNoDataRows = document.querySelectorAll('#reportTableBody tr.no-data');
            if (visibleCount === 0) {
                // Show existing no-data row if it exists
                existingNoDataRows.forEach(row => row.style.display = '');
            } else {
                // Hide all no-data rows when there are visible results
                existingNoDataRows.forEach(row => row.style.display = 'none');
            }

            // Reset pagination after filtering
            currentPage = 1;
            updatePagination();
        }

        /* --- Pagination (placeholder, client-side) --- */
        let currentPage = 1;
        const rowsPerPage = 20;

        function changePage(dir) {
            currentPage += dir;
            updatePagination();
        }

        function updatePagination() {
            const rows = Array.from(document.querySelectorAll('#reportTableBody tr')).filter(r =>
                r.style.display !== 'none' && !r.classList.contains('no-data')
            );
            const total = rows.length;
            const totalPages = Math.max(1, Math.ceil(total / rowsPerPage));
            currentPage = Math.min(Math.max(1, currentPage), totalPages);

            rows.forEach((row, i) => {
                const shouldShowInPage = (i >= (currentPage - 1) * rowsPerPage && i < currentPage * rowsPerPage);
                if (shouldShowInPage) {
                    row.style.display = '';
                } else if (!row.classList.contains('no-data')) {
                    row.style.display = 'none';
                }
            });

            const start = Math.min((currentPage - 1) * rowsPerPage + 1, total);
            const end = Math.min(currentPage * rowsPerPage, total);
            document.getElementById('pageInfo').textContent =
                total > 0 ? `Showing ${start}–${end} of ${total} entries` : 'No entries found';

            document.getElementById('prevBtn').disabled = currentPage <= 1;
            document.getElementById('nextBtn').disabled = currentPage >= totalPages;
        }

        // Init
        updatePagination();

        // Load void sales data via AJAX
        function loadVoidSalesData() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const branchFilterElement = document.getElementById('branchFilter');
            const branchFilter = branchFilterElement ? branchFilterElement.value : '';

            // Build query parameters
            let params = new URLSearchParams();
            params.append('date_from', dateFrom);
            params.append('date_to', dateTo);
            if (branchFilter) {
                params.append('branch', branchFilter);
            }

            // Show loading state
            const tbody = document.getElementById('reportTableBody');
            tbody.innerHTML = '<tr><td colspan="7" style="padding: 40px; text-align: center;"><div class="loading">Loading...</div></td></tr>';

            // Fetch data
            fetch('fetch_void_sales_data.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    tbody.innerHTML = '';

                    if (data.success && data.records && data.records.length > 0) {
                        // Add data rows
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
                            `;
                            tbody.appendChild(row);
                        });

                        // Add hidden no-data row
                        const noDataRow = document.createElement('tr');
                        noDataRow.className = 'no-data';
                        noDataRow.style.display = 'none';
                        noDataRow.innerHTML = '<td colspan="7">No voided sales found.</td>';
                        tbody.appendChild(noDataRow);
                    } else {
                        // Show no data message
                        tbody.innerHTML = '<tr class="no-data"><td colspan="7">No voided sales found.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error loading data:', error);
                    tbody.innerHTML = '<tr class="no-data"><td colspan="7">Error loading data. Please try again.</td></tr>';
                });
        }

        // Preview all void sales
        function previewAllVoidSales() {
            // Get date range from the form inputs
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const branchFilterElement = document.getElementById('branchFilter');
            const branchFilter = branchFilterElement ? branchFilterElement.value : '';

            // Build parameters
            let params = [];

            // Add date parameters
            if (dateFrom && dateTo) {
                params.push(`date_from=${dateFrom}`);
                params.push(`date_to=${dateTo}`);
            } else if (dateFrom) {
                params.push(`date_from=${dateFrom}`);
                params.push(`date_to=${dateFrom}`);
            } else if (dateTo) {
                params.push(`date_from=${dateTo}`);
                params.push(`date_to=${dateTo}`);
            } else {
                // Default to today's date
                const today = new Date();
                const dateStr = today.getFullYear() + '-' +
                    String(today.getMonth() + 1).padStart(2, '0') + '-' +
                    String(today.getDate()).padStart(2, '0');
                params.push(`date_from=${dateStr}`);
                params.push(`date_to=${dateStr}`);
            }

            // Add branch parameter if selected
            if (branchFilter) {
                params.push(`branch=${branchFilter}`);
            }

            // Open preview all in new window
            window.open('preview_all_void_sales.php?' + params.join('&'), '_blank', 'width=900,height=700');
        }

        // Initialize page - do NOT auto-filter, wait for user to click FILTER button
        window.addEventListener('load', function () {
            // Page loads with "SELECT A FILTER TO DISPLAY THE DATA" message
            // User must click FILTER button to load data
        });
    </script>

</body>

</html>