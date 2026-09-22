<?php
require_once 'session_check.php';
include 'config.php';

// Create sales_entry_status_log table if it doesn't exist
$create_log_table = "CREATE TABLE IF NOT EXISTS sales_entry_status_log (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    branch_code VARCHAR(10),
    branch_name VARCHAR(255),
    stock_type VARCHAR(50),
    remarks TEXT,
    items_count INT(11) DEFAULT 0,
    updated_count INT(11) DEFAULT 0,
    created_by VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approver VARCHAR(100),
    approval_date DATETIME,
    disapproved_by VARCHAR(100),
    status VARCHAR(20) DEFAULT 'Pending',
    INDEX idx_status (status),
    INDEX idx_entry_date (entry_date),
    INDEX idx_branch (branch_name)
)";
$conn->query($create_log_table);

// Check if the columns exist, if not add them (for existing tables)
$check_columns = "SHOW COLUMNS FROM sales_entry_status_log LIKE 'approver'";
$result = $conn->query($check_columns);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE sales_entry_status_log ADD COLUMN approver VARCHAR(100) AFTER created_at");
}

$check_columns = "SHOW COLUMNS FROM sales_entry_status_log LIKE 'approval_date'";
$result = $conn->query($check_columns);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE sales_entry_status_log ADD COLUMN approval_date DATETIME AFTER approver");
}

$check_columns = "SHOW COLUMNS FROM sales_entry_status_log LIKE 'disapproved_by'";
$result = $conn->query($check_columns);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE sales_entry_status_log ADD COLUMN disapproved_by VARCHAR(100) AFTER approval_date");
}

$check_columns = "SHOW COLUMNS FROM sales_entry_status_log LIKE 'status'";
$result = $conn->query($check_columns);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE sales_entry_status_log ADD COLUMN status VARCHAR(20) DEFAULT 'Pending' AFTER disapproved_by");
}

// Create the items table if it doesn't exist
$create_items_table = "CREATE TABLE IF NOT EXISTS sales_entry_status_items (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    log_id INT(11) NOT NULL,
    item_code VARCHAR(50),
    item_description TEXT,
    imei VARCHAR(100),
    quantity INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_id (log_id)
)";
$conn->query($create_items_table);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Approval Item Status</title>
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

        .filter-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 180px;
            max-width: 200px;
        }

        .filter-group label {
            font-size: 14px;
            color: #666;
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

        .btn-search {
            background-color: var(--color-navy);
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            height: 40px;
            transition: all 0.3s ease;
        }

        .btn-search:hover {
            background-color: var(--color-navy-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(13, 51, 71, 0.3);
        }

        .btn-print {
            background-color: #1976D2;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            height: 40px;
        }

        .btn-print:hover {
            background-color: #1565C0;
        }

        .btn-preview {
            padding: 6px 14px;
            background-color: #1976D2;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-preview:hover {
            background-color: #1565C0;
        }

        .btn-approve {
            padding: 6px 14px;
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-approve:hover {
            background-color: #1b5e20;
        }

        .btn-disapprove {
            padding: 6px 14px;
            background-color: #c62828;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-disapprove:hover {
            background-color: #b71c1c;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.pending {
            background: #fff3e0;
            color: #e65100;
        }

        .status-badge.approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.disapproved {
            background: #ffebee;
            color: #c62828;
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
            <h2>Item Status Approval</h2>
        </div>

        <div class="filter-container">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" id="date_from">
                </div>
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" id="date_to">
                </div>
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
                    $branches_options = '';
                    if ($branches_result && $branches_result->num_rows > 0) {
                        while ($branch_row = $branches_result->fetch_assoc()) {
                            $branch_display = htmlspecialchars($branch_row['branch_code'] . ' - ' . $branch_row['branch_name']);
                            $branch_name_value = htmlspecialchars($branch_row['branch_name']);
                            $branches_options .= "<option value=\"{$branch_name_value}\">{$branch_display}</option>";
                        }
                    }
                    
                    echo '<div class="filter-group">';
                    echo '    <label>Branch</label>';
                    echo '    <select id="branch_filter">';
                    echo '        <option value="">Select Branch</option>';
                    echo '        <option value="ALL">All Branches</option>';
                    echo $branches_options;
                    echo '    </select>';
                    echo '</div>';
                }
                ?>
                <div class="filter-group">
                    <label>Stock Type</label>
                    <select id="stock_type_filter">
                        <option value="">Select Stock Type</option>
                        <option value="all">All Types</option>
                        <option value="Good Stock">Good Stock</option>
                        <option value="Defective">Defective</option>
                        <option value="Serviced">Serviced</option>
                        <option value="Demo">Demo</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select id="status_filter">
                        <option value="">Select Status</option>
                        <option value="all">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Disapproved">Disapproved</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-search" onclick="searchEntries()">SEARCH</button>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>BRANCH</th>
                        <th>STOCK TYPE</th>
                        <th>REASON</th>
                        <th>ITEMS COUNT</th>
                        <th>CREATED BY</th>
                        <th>APPROVER</th>
                        <th>STATUS</th>
                        <th>PREVIEW</th>
                        <th>DISAPPROVED</th>
                        <th>APPROVED</th>
                    </tr>
                </thead>
                <tbody id="approvalTableBody">
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 30px 20px;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                <div style="color: #333; font-size: 15px; font-weight: 600;">SELECT A STATUS FILTER TO DISPLAY THE DATA</div>
                                <div style="color: #666; font-size: 13px;">Please select a status filter from the dropdown above to view item status entries.</div>
                            </div>
                        </td>
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
            // Try to restore filters from localStorage
            const savedFilters = localStorage.getItem('itemStatusApprovalFilters');
            
            if (savedFilters) {
                try {
                    const filters = JSON.parse(savedFilters);
                    if (filters.date_from) document.getElementById('date_from').value = filters.date_from;
                    if (filters.date_to) document.getElementById('date_to').value = filters.date_to;
                } catch (e) {
                    console.error('Error loading saved filters:', e);
                }
            }
        });

        function searchEntries() { loadEntries(); }

        function loadEntries() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const status = document.getElementById('status_filter').value;
            const stockType = document.getElementById('stock_type_filter').value;
            
            // Get branch filter if it exists (only for Super-Admin and Sub-admin)
            const branchElement = document.getElementById('branch_filter');
            const branch = branchElement ? branchElement.value : '';

            // Check if status filter is selected
            if (!status || status === '') {
                // Show message to select a filter
                const tbody = document.getElementById('approvalTableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 30px 20px;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                <div style="color: #333; font-size: 15px; font-weight: 600;">SELECT A STATUS FILTER TO DISPLAY THE DATA</div>
                                <div style="color: #666; font-size: 13px;">Please select a status filter from the dropdown above to view item status entries.</div>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            // Save current filters to localStorage
            const filters = {
                date_from: dateFrom,
                date_to: dateTo,
                status: status,
                stock_type: stockType,
                branch: branch
            };
            localStorage.setItem('itemStatusApprovalFilters', JSON.stringify(filters));

            // Build POST parameters
            let postParams = `date_from=${dateFrom}&date_to=${dateTo}&status=${status}&stock_type=${stockType}`;
            if (branch) postParams += `&branch=${branch}`;

            fetch('get_item_status_approvals.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: postParams
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.text();
                })
                .then(text => {
                    console.log('Response text:', text);
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed data:', data);
                        if (Array.isArray(data)) {
                            renderTable(data);
                        } else if (data.error) {
                            document.getElementById('approvalTableBody').innerHTML =
                                `<tr><td colspan="12" style="text-align:center; padding:20px; color:#c62828;">Error: ${data.error}</td></tr>`;
                        } else {
                            renderTable([]);
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response was:', text);
                        document.getElementById('approvalTableBody').innerHTML =
                            '<tr><td colspan="12" style="text-align:center; padding:20px; color:#c62828;">Error: Invalid response from server. Check console for details.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    document.getElementById('approvalTableBody').innerHTML =
                        '<tr><td colspan="12" style="text-align:center; padding:20px; color:#c62828;">Error loading entries</td></tr>';
                });
        }

        function renderTable(entries) {
            const tbody = document.getElementById('approvalTableBody');

            // Check if response contains an error
            if (entries && entries.error) {
                tbody.innerHTML = `<tr><td colspan="12" style="text-align:center; padding:20px; color:#c62828;">Error: ${entries.error}</td></tr>`;
                return;
            }

            if (!entries || entries.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" style="text-align:center; padding:20px;">No entries found</td></tr>';
                return;
            }

            tbody.innerHTML = '';
            entries.forEach(entry => {
                const tr = document.createElement('tr');
                let statusBadge = `<span class="status-badge ${entry.status.toLowerCase()}">${entry.status}</span>`;
                
                // Show disapprove button for Pending, show red badge for Disapproved status
                let disapproveBtn;
                if (entry.status === 'Pending') {
                    disapproveBtn = `<button class="btn-disapprove" onclick="updateStatus(${entry.id}, 'Disapproved')">Disapprove</button>`;
                } else if (entry.status === 'Disapproved') {
                    disapproveBtn = `<span class="status-badge disapproved">Disapproved</span>`;
                } else {
                    disapproveBtn = '-';
                }
                
                // Show approve button for Pending, show green badge for Approved status
                let approveBtn;
                if (entry.status === 'Pending') {
                    approveBtn = `<button class="btn-approve" onclick="updateStatus(${entry.id}, 'Approved')">Approve</button>`;
                } else if (entry.status === 'Approved') {
                    approveBtn = `<span class="status-badge approved">Approved</span>`;
                } else {
                    approveBtn = '-';
                }

                tr.innerHTML = `
                    <td>${entry.entry_date}</td>
                    <td>${entry.branch_name}</td>
                    <td>${entry.stock_type}</td>
                    <td>${entry.remarks || '-'}</td>
                    <td>${entry.items_count}</td>
                    <td>${entry.created_by}</td>
                    <td>${entry.approver || '-'}</td>
                    <td>${statusBadge}</td>
                    <td><button class="btn-preview" onclick="previewItems(${entry.id}, '${escapeHtml(entry.stock_type)}')">Preview</button></td>
                    <td>${disapproveBtn}</td>
                    <td>${approveBtn}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        function previewItems(logId, stockType) {
            // Fetch items for this log entry
            fetch('get_item_status_preview.php?log_id=' + logId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.items) {
                        showPreviewModal(data.items, stockType, data.branch_name, data.remarks, data.entry_date);
                    } else {
                        alert('Error: ' + (data.message || 'Failed to load items'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading items preview');
                });
        }

        function showPreviewModal(items, stockType, branchName, remarks, entryDate) {
            const modalHtml = `
                <div id="previewModal" style="display: flex; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); align-items: center; justify-content: center;">
                    <div style="background-color: white; border-radius: 8px; max-width: 1100px; width: 90%; max-height: 90vh; overflow: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                        <div style="padding: 25px 30px; border-bottom: 1px solid #eee;">
                            <h3 style="margin: 0; font-size: 20px; color: #333;">Item Status Preview</h3>
                        </div>
                        <div style="padding: 20px 30px;">
                            <!-- Item Details Table -->
                            <div style="margin-bottom: 25px;">
                                <h4 style="margin: 0 0 12px 0; font-size: 15px; font-weight: 600; color: #333;">ITEM DETAILS REQUEST</h4>
                                <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
                                    <thead>
                                        <tr style="background: var(--color-gold-pale);">
                                            <th style="padding: 12px; border: 1px solid #ccc; text-align: center; font-size: 13px;">DATE</th>
                                            <th style="padding: 12px; border: 1px solid #ccc; text-align: left; font-size: 13px;">ITEM CODE</th>
                                            <th style="padding: 12px; border: 1px solid #ccc; text-align: center; font-size: 13px;">IMEI</th>
                                            <th style="padding: 12px; border: 1px solid #ccc; text-align: center; font-size: 13px;">QUANTITY</th>
                                            <th style="padding: 12px; border: 1px solid #ccc; text-align: center; font-size: 13px;">BRANCH</th>
                                            <th style="padding: 12px; border: 1px solid #ccc; text-align: center; font-size: 13px;">FROM STATUS</th>
                                            <th style="padding: 12px; border: 1px solid #ccc; text-align: center; font-size: 13px;">TO STATUS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${items.map(item => {
                                            const currentBranch = item.current_branch || branchName || 'N/A';
                                            const fromStatus = item.previous_status || 'N/A';
                                            return `
                                            <tr>
                                                <td style="padding: 10px 12px; border: 1px solid #ccc; text-align: center; font-size: 13px;">${entryDate || 'N/A'}</td>
                                                <td style="padding: 10px 12px; border: 1px solid #ccc; font-size: 13px;">${escapeHtml(item.item_code)}</td>
                                                <td style="padding: 10px 12px; border: 1px solid #ccc; text-align: center; font-size: 13px;">${item.imei ? escapeHtml(item.imei) : '-'}</td>
                                                <td style="padding: 10px 12px; border: 1px solid #ccc; text-align: center; font-size: 13px;">${item.quantity}</td>
                                                <td style="padding: 10px 12px; border: 1px solid #ccc; text-align: center; font-size: 13px; font-weight: 600; color: var(--color-navy);">${currentBranch}</td>
                                                <td style="padding: 10px 12px; border: 1px solid #ccc; text-align: center; font-size: 13px;">${fromStatus}</td>
                                                <td style="padding: 10px 12px; border: 1px solid #ccc; text-align: center; font-size: 13px; font-weight: 600; color: var(--color-gold);">${stockType}</td>
                                            </tr>
                                            `;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Reason Section -->
                            <div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                                <div style="font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px;">REASON:</div>
                                <div style="font-size: 13px; color: #666; line-height: 1.5;">${remarks ? escapeHtml(remarks) : '-'}</div>
                            </div>
                        </div>
                        <div style="padding: 20px 30px; border-top: 1px solid #eee; text-align: right;">
                            <button onclick="closePreviewModal()" style="padding: 10px 30px; background: white; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">Close</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Close on background click
            document.getElementById('previewModal').addEventListener('click', function(e) {
                if (e.target.id === 'previewModal') {
                    closePreviewModal();
                }
            });
        }

        function closePreviewModal() {
            const modal = document.getElementById('previewModal');
            if (modal) {
                modal.remove();
            }
        }

        function updateStatus(entryId, newStatus) {
            if (!confirm(`Are you sure you want to ${newStatus.toLowerCase()} this entry?`)) return;

            fetch('update_item_status_approval.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `entry_id=${entryId}&status=${newStatus}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Entry ${newStatus.toLowerCase()} successfully!`);
                        // Reload the current table to show updated status
                        loadEntries();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to update status'));
                    }
                })
                .catch(() => alert('Error updating entry status'));
        }
    </script>

</body>

</html>
