<?php
require_once 'session_check.php';
include 'config.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Item Status Report</title>
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
            height: 50px;
            margin-left: -20px;
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
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 30px;
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
            gap: 10px;
        }

        .date-filters {
            display: flex;
            align-items: center;
            margin-left: auto;
            gap: 10px;
        }

        .search-bar-wrapper input {
            padding: 9px 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            width: 280px;
            outline: none;
            font-family: Arial, sans-serif;
            background: white;
        }

        .search-bar-wrapper input:focus {
            border-color: #2196F3;
        }

        .search-bar-wrapper select {
            padding: 9px 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            min-width: 180px;
            outline: none;
            background: white;
        }

        .search-bar-wrapper select:focus {
            border-color: #2196F3;
        }

        .search-bar-wrapper input[type="date"] {
            padding: 9px 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            width: 160px;
            outline: none;
            background: white;
        }

        .search-bar-wrapper input[type="date"]:focus {
            border-color: #2196F3;
        }

        .search-bar-wrapper button {
            padding: 9px 16px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .search-bar-wrapper button:hover {
            background-color: var(--color-navy-dark);
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
        }

        .btn-filter:hover {
            background-color: #45a049;
        }

        .btn-preview-all {
            padding: 9px 20px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-preview-all:hover {
            background-color: var(--color-navy-dark);
        }

        .btn-view {
            padding: 6px 16px;
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

        .action-btns {
            display: flex;
            gap: 6px;
            justify-content: center;
            align-items: center;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.good-stock {
            background: #ffffffff;
            color: #313131ff;
        }

        .status-badge.defective {
            background: #ffffffff;
            color: #313131ff;
        }

        .status-badge.serviced {
            background: #ffffffff;
            color: #313131ff;
        }

        .status-badge.demo {
            background: #ffffffff;
            color: #313131ff;
        }

        .status-badge.active {
            background: #ffffffff;
            color: #313131ff;
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
            color: #000000;
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
            background: #fdf8f3;
        }

        tbody td {
            text-align: center;
        }

        /* No data row */
        .report-table .no-data td {
            color: #999;
            font-style: italic;
            padding: 20px;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .filter-row {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
                max-width: 100%;
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

            .header {
                padding: 0 15px;
                gap: 15px;
            }

            .logo {
                height: 40px;
            }

            .table-container {
                padding: 20px;
                overflow-x: auto;
            }

            table {
                min-width: 800px;
            }

            .content-header h2 {
                font-size: 18px;
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
                padding: 15px;
            }

            .table-container {
                padding: 15px;
            }

            th,
            td {
                padding: 8px;
                font-size: 12px;
            }

            .content-header {
                margin-bottom: 20px;
            }

            .content-header h2 {
                font-size: 16px;
            }
        }

        @media print {
            .header,
            .sidebar,
            .search-bar-wrapper,
            .menu-btn,
            .btn-preview-all {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                margin-top: 0 !important;
                padding: 0 !important;
            }

            .table-container {
                box-shadow: none !important;
                padding: 0 !important;
            }

            body {
                zoom: 100%;
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
        <?php include '_header_user.php'; ?>
    </div>

    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Item Status Report</h2>
        </div>

        <div class="search-bar-wrapper">
            <div class="search-controls">
                <input type="text" id="imei_filter" placeholder="Enter IMEI">
                <button onclick="searchByIMEI()">SEARCH</button>
                
                <?php
                // Show branch filters only for Super-Admin and Sub-admin
                $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
                $is_super_admin = ($system_level === 'Super-Admin');
                $is_sub_admin = ($system_level === 'Sub-admin');
                
                if ($is_super_admin || $is_sub_admin) {
                    // Get branches based on user level
                    if ($is_super_admin) {
                        // Super-Admin sees all branches
                        $branches_query = "SELECT DISTINCT branch_code, branch_name FROM branches ORDER BY branch_name";
                    } else {
                        // Sub-admin sees only their assigned branches
                        $user_branch_var = isset($_SESSION['user_branch']) ? $_SESSION['user_branch'] : '';
                        $branch_names = array_map('trim', explode(',', $user_branch_var));
                        $branch_names_quoted = array_map(function($name) use ($conn) {
                            return "'" . $conn->real_escape_string($name) . "'";
                        }, $branch_names);
                        $branch_in_clause = implode(', ', $branch_names_quoted);
                        $branches_query = "SELECT DISTINCT branch_code, branch_name FROM branches WHERE branch_name IN ($branch_in_clause) ORDER BY branch_name";
                    }
                    
                    $branches_result = $conn->query($branches_query);
                    
                    echo '<select id="branch_filter">';
                    echo '    <option value="">Select Branch</option>';
                    echo '    <option value="ALL">All Branches</option>';
                    
                    if ($branches_result && $branches_result->num_rows > 0) {
                        while ($branch_row = $branches_result->fetch_assoc()) {
                            $branch_display = htmlspecialchars($branch_row['branch_code'] . ' - ' . $branch_row['branch_name']);
                            $branch_name_value = htmlspecialchars($branch_row['branch_name']);
                            echo "<option value=\"{$branch_name_value}\">{$branch_display}</option>";
                        }
                    }
                    echo '</select>';
                }
                ?>
            </div>
            
            <div class="date-filters">
                <label for="date_from" style="font-size: 14px; color: #333;">From:</label>
                <input type="date" id="date_from">
                <label for="date_to" style="margin-left: 10px; font-size: 14px; color: #333;">To:</label>
                <input type="date" id="date_to">
                
                <select id="stock_type_filter">
                    <option value="">Select Stock Type</option>
                    <option value="all">All Types</option>
                    <option value="Good Stock">Good Stock</option>
                    <option value="Defective">Defective</option>
                    <option value="Serviced">Serviced</option>
                    <option value="Demo">Demo</option>
                    <option value="Active">Active</option>
                </select>
                
                <button class="btn-filter" onclick="searchEntries()">FILTER</button>
                <button class="btn-preview-all" onclick="previewAllItemStatus()">PREVIEW ALL</button>
            </div>
        </div>

        <div class="table-container">
            <h3>Item Status Change History</h3>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>BRANCH</th>
                        <th>ITEM CODE</th>
                        <th>IMEI</th>
                        <th>FROM STATUS</th>
                        <th>TO STATUS</th>
                        <th>CHANGED BY</th>
                        <th>APPROVER BY</th>
                        <th>REMARKS</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    <tr class="select-filter-msg no-data">
                        <td colspan="10">SELECT A FILTER TO DISPLAY THE DATA</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.menu-btn').classList.toggle('active');
            document.querySelector('.sidebar').classList.toggle('hidden');
            document.querySelector('.main-content').classList.toggle('expanded');
        }

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

        document.addEventListener('DOMContentLoaded', () => {
            // Set default dates (current month)
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            document.getElementById('date_from').valueAsDate = firstDay;
            document.getElementById('date_to').valueAsDate = today;
        });

        function searchByIMEI() {
            const imei = document.getElementById('imei_filter').value.trim();
            
            if (!imei) {
                alert('Please enter an IMEI to search');
                return;
            }

            // Build POST parameters
            let postParams = `imei=${encodeURIComponent(imei)}`;
            
            // Fetch data from backend
            fetchApprovedItems(postParams);
        }

        function searchEntries() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const stockType = document.getElementById('stock_type_filter').value;
            const imei = document.getElementById('imei_filter').value.trim();
            
            // Get branch filter if it exists (only for Super-Admin and Sub-admin)
            const branchElement = document.getElementById('branch_filter');
            const branch = branchElement ? branchElement.value : '';

            if (!dateFrom || !dateTo) {
                alert('Please select date range');
                return;
            }

            // Build POST parameters
            let postParams = `date_from=${dateFrom}&date_to=${dateTo}`;
            if (stockType) postParams += `&stock_type=${stockType}`;
            if (imei) postParams += `&imei=${encodeURIComponent(imei)}`;
            if (branch) postParams += `&branch=${encodeURIComponent(branch)}`;
            
            // Fetch data from backend
            fetchApprovedItems(postParams);
        }

        function fetchApprovedItems(postParams) {
            const tbody = document.getElementById('reportTableBody');
            
            // Show loading state
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" style="text-align: center; padding: 30px 20px;">
                        <div style="color: #666; font-size: 14px;">Loading...</div>
                    </td>
                </tr>
            `;

            fetch('get_approved_item_status_report.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: postParams
            })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 30px 20px;">
                                    <div style="color: #c62828; font-size: 14px;">Error: ${data.error}</div>
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    renderApprovedData(data);
                })
                .catch(error => {
                    console.error('Error:', error);
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 30px 20px;">
                                <div style="color: #c62828; font-size: 14px;">Error loading data</div>
                            </td>
                        </tr>
                    `;
                });
        }

        function renderApprovedData(entries) {
            const tbody = document.getElementById('reportTableBody');
            
            if (!entries || entries.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 30px 20px;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                <div style="color: #333; font-size: 15px; font-weight: 600;">NO APPROVED RECORDS FOUND</div>
                                <div style="color: #666; font-size: 13px;">No approved status change history matches your search criteria.</div>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = '';
            entries.forEach(entry => {
                const tr = document.createElement('tr');
                
                const fromStatusClass = getStatusClass(entry.from_status);
                const toStatusClass = getStatusClass(entry.to_status);
                
                tr.innerHTML = `
                    <td>${entry.date}</td>
                    <td>${entry.branch}</td>
                    <td>${entry.item_code || 'N/A'}</td>
                    <td>${entry.imei}</td>
                    <td><span class="status-badge ${fromStatusClass}">${entry.from_status}</span></td>
                    <td><span class="status-badge ${toStatusClass}">${entry.to_status}</span></td>
                    <td>${entry.changed_by}</td>
                    <td>${entry.approver_by}</td>
                    <td style="text-align: left !important;">${entry.remarks}</td>
                    <td>
                        <div class="action-btns">
                            <button class="btn-print" onclick="previewRecord(${entry.id})">Preview</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function getStatusClass(status) {
            const statusMap = {
                'Good Stock': 'good-stock',
                'Defective': 'defective',
                'Serviced': 'serviced',
                'Demo': 'demo',
                'Active': 'active'
            };
            return statusMap[status] || '';
        }

        function previewAllItemStatus() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const stockType = document.getElementById('stock_type_filter').value;
            const imei = document.getElementById('imei_filter').value.trim();
            
            // Get branch filter if it exists
            const branchElement = document.getElementById('branch_filter');
            const branch = branchElement ? branchElement.value : '';

            if (!dateFrom || !dateTo) {
                alert('Please select date range');
                return;
            }

            // Build URL with parameters
            let url = 'preview_all_report-itemstatus.php?';
            url += `date_from=${dateFrom}&date_to=${dateTo}`;
            if (stockType) url += `&stock_type=${stockType}`;
            if (imei) url += `&imei=${encodeURIComponent(imei)}`;
            if (branch) url += `&branch=${encodeURIComponent(branch)}`;
            
            // Open in new window with specific dimensions
            window.open(url, '_blank', 'width=900,height=700');
        }

        function previewRecord(logId) {
            // Open preview window for specific record
            window.open('print_item_status.php?id=' + logId, '_blank', 'width=900,height=700');
        }
    </script>
</body>

</html>
