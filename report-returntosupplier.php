<?php
require_once 'session_check.php';
include 'config.php';

// Handle multiple branches for Sub-admin
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

// Parse multiple branch names
$user_branches = [];
if (!empty($user_branch) && strcasecmp($system_level, 'Super-Admin') !== 0) {
    $user_branches = array_map('trim', explode(',', $user_branch));
}

// For backward compatibility, keep $branch_code as the first branch code
$branch_code = '000';
if (!empty($user_branches)) {
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($user_branches[0]) . "' LIMIT 1");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
}

// Check if return_to_supplier table exists
$table_check = $conn->query("SHOW TABLES LIKE 'return_to_supplier'");
$table_exists = ($table_check && $table_check->num_rows > 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Return to Supplier Report</title>
    <style>
        :root {
            /* Brand Colors - Navy & Gold Theme */
            --color-navy: #0d3347;
            --color-navy-dark: #081f2d;
            --color-navy-light: #164460;
            --color-gold: #b08a52;
            --color-gold-light: #c9a46e;
            --color-gold-pale: #f5ede0;
            
            /* Background Colors */
            --bg-form-panel: #faf8f5;
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
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            margin-left: 15px;
            transition: background-color 0.2s;
        }

        .btn-preview-all:hover {
            background-color: var(--color-navy-dark);
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

        /* Responsive */
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

        @media (max-width: 930px) {
            .search-bar-wrapper button svg {
                margin-right: 0 !important;
            }

            .search-bar-wrapper button {
                font-size: 0;
                padding: 10px;
                width: 40px;
                height: 40px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .search-bar-wrapper button svg {
                font-size: 16px;
                width: 18px;
                height: 18px;
            }

            .search-bar-wrapper input {
                width: 150px !important;
            }

            .search-bar-wrapper input[type="date"] {
                width: 140px;
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

            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                min-width: 800px;
                display: table;
            }

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

            .table-container {
                padding: 10px;
            }

            .search-bar-wrapper input[type="date"] {
                width: 120px;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()">
            <span></span><span></span><span></span>
        </div>
        <?php include '_header_user.php'; ?>
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Return to Supplier Report (Complete)</h2>
        </div>

        <!-- Search Bar -->
        <div class="search-bar-wrapper">
            <div class="search-controls">
                <input type="text" id="searchInput" placeholder="Search RTS Number, Reference Number...">
                <button onclick="filterTable()">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                        <path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 001.48-5.34c-.47-2.78-2.79-5-5.59-5.34a6.505 6.505 0 00-7.27 7.27c.34 2.8 2.56 5.12 5.34 5.59a6.5 6.5 0 005.34-1.48l.27.28v.79l4.25 4.25c.41.41 1.08.41 1.49 0 .41-.41.41-1.08 0-1.49L15.5 14zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                    </svg>
                    Search
                </button>
                
                <?php if (strcasecmp($system_level, 'Super-Admin') === 0 || strcasecmp($system_level, 'Sub-admin') === 0): ?>
                    <select id="branchFilter" onchange="loadReports()">
                        <option value="ALL">All Branches</option>
                        <?php
                        $branch_query = $conn->query("SELECT branch_name FROM branches ORDER BY branch_name");
                        if ($branch_query && $branch_query->num_rows > 0) {
                            while ($br = $branch_query->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($br['branch_name']) . '">' . htmlspecialchars($br['branch_name']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                <?php endif; ?>
            </div>
            
            <div class="date-filters">
                <input type="date" id="dateFrom" value="<?php echo date('Y-m-d'); ?>" onchange="loadReports()">
                <input type="date" id="dateTo" value="<?php echo date('Y-m-d'); ?>" onchange="loadReports()">
                <button class="btn-filter" onclick="loadReports()">Filter</button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">Date</th>
                        <th style="width: 12%;">RTS Number</th>
                        <th style="width: 12%;">Reference Number</th>
                        <th style="width: 10%;">Branch</th>
                        <th style="width: 13%;">Delivery To</th>
                        <th style="width: 15%;">Item Description</th>
                        <th style="width: 12%;">IMEI</th>
                        <th style="width: 6%;">Quantity</th>
                        <th style="width: 8%;">Cost</th>
                        <th style="width: 4%;">Action</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    <tr class="no-data">
                        <td colspan="10">Loading data...</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination-wrapper" id="paginationWrapper" style="display: none;">
                <button class="page-btn" id="prevPageBtn" onclick="changePage(-1)">Previous</button>
                <span id="pageInfo"></span>
                <button class="page-btn" id="nextPageBtn" onclick="changePage(1)">Next</button>
            </div>
        </div>
    </div>

    <script>
        let allReports = [];
        let filteredReports = [];
        let currentPage = 1;
        const itemsPerPage = 50;

        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn = document.querySelector('.menu-btn');

            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
            menuBtn.classList.toggle('active');
        }

        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadReports();
        });

        function loadReports() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const branch = document.getElementById('branchFilter') ? document.getElementById('branchFilter').value : 'ALL';
            
            const formData = new FormData();
            formData.append('date_from', dateFrom);
            formData.append('date_to', dateTo);
            formData.append('branch', branch);
            
            fetch('get_rts_reports.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.error) {
                    document.getElementById('reportTableBody').innerHTML =
                        `<tr class="no-data"><td colspan="10">Error: ${data.error}</td></tr>`;
                    return;
                }
                allReports = Array.isArray(data) ? data : [];
                filteredReports = allReports;
                currentPage = 1;
                renderTable();
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('reportTableBody').innerHTML = '<tr class="no-data"><td colspan="10">Error loading data</td></tr>';
            });
        }

        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            filteredReports = allReports.filter(report => {
                const matchesSearch = 
                    report.rts_number.toLowerCase().includes(searchTerm) ||
                    report.reference_number.toLowerCase().includes(searchTerm) ||
                    report.branch_from.toLowerCase().includes(searchTerm) ||
                    report.delivery_to.toLowerCase().includes(searchTerm) ||
                    report.item_description.toLowerCase().includes(searchTerm) ||
                    report.imei.toLowerCase().includes(searchTerm);
                
                return matchesSearch;
            });
            
            currentPage = 1;
            renderTable();
        }

        function renderTable() {
            const tbody = document.getElementById('reportTableBody');
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const pageData = filteredReports.slice(startIndex, endIndex);

            if (pageData.length === 0) {
                tbody.innerHTML = '<tr class="no-data"><td colspan="10">No records found</td></tr>';
                document.getElementById('paginationWrapper').style.display = 'none';
                return;
            }

            tbody.innerHTML = '';
            pageData.forEach(report => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${report.date}</td>
                    <td>${report.rts_number}</td>
                    <td>${report.reference_number}</td>
                    <td>${report.branch_from}</td>
                    <td>${report.delivery_to}</td>
                    <td style="text-align: left;">${report.item_description}</td>
                    <td>${report.imei || '-'}</td>
                    <td>${report.quantity}</td>
                    <td style="text-align: left;">${report.cost}</td>
                    <td>
                        <button class="btn-preview" onclick="previewRTS('${report.rts_number}')">Preview</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            // Update pagination
            const totalPages = Math.ceil(filteredReports.length / itemsPerPage);
            if (totalPages > 1) {
                document.getElementById('paginationWrapper').style.display = 'flex';
                document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
                document.getElementById('prevPageBtn').disabled = currentPage === 1;
                document.getElementById('nextPageBtn').disabled = currentPage === totalPages;
            } else {
                document.getElementById('paginationWrapper').style.display = 'none';
            }
        }

        function changePage(direction) {
            currentPage += direction;
            renderTable();
        }

        function previewRTS(rtsNumber) {
            const width = 1200;
            const height = 800;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            const features = `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes,status=yes`;
            window.open(`preview_rts.php?rts_number=${encodeURIComponent(rtsNumber)}`, 'PreviewRTS', features);
        }
    </script>
</body>

</html>
