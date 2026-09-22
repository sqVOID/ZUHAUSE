<?php
require_once 'session_check.php';
include 'config.php';

// Create tables if they don't exist
$create_transfers = "CREATE TABLE IF NOT EXISTS stock_transfers (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    st_number VARCHAR(50) NOT NULL UNIQUE,
    st_date DATE NOT NULL,
    branch_from VARCHAR(10),
    branch_to VARCHAR(10),
    store_name VARCHAR(255),
    prepared_by VARCHAR(100),
    approver VARCHAR(100),
    approval_date DATETIME,
    status VARCHAR(20) DEFAULT 'Pending',
    remarks TEXT,
    total_quantity INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_transfers);

$create_items = "CREATE TABLE IF NOT EXISTS stock_transfer_items (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    st_number VARCHAR(50) NOT NULL,
    item_code VARCHAR(50),
    item_description TEXT,
    imei VARCHAR(100),
    quantity INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_items);


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Transfer Approval</title>
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

        .btn-add-branch {
            padding: 10px 24px;
            border: none;
            background: #2e7d32;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-add-branch:hover {
            background: #1b5e20;
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

        .status-badge.received {
            background: #e3f2fd;
            color: #1565c0;
        }

        .form-container.hidden {
            display: none;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
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

        .form-group textarea {
            resize: vertical;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #999;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2196F3;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
        }

        .btn-cancel {
            padding: 10px 24px;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-cancel:hover {
            background: #f5f5f5;
        }

        .btn-register {
            padding: 10px 24px;
            border: none;
            background: #2e7d32;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-register:hover {
            background: #1976D2;
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

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            margin: 0;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 8px 35px 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #2196F3;
        }

        .search-box svg {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            fill: #999;
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

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.inactive {
            background: #ffebee;
            color: #c62828;
        }

        .btn-edit {
            padding: 6px 16px;
            border: none;
            background: #1976D2;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
        }

        .btn-edit:hover {
            background: #1565C0;
        }

        .btn-deactivate {
            padding: 6px 16px;
            border: none;
            background: #c62828;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }

        .btn-deactivate:hover {
            background: #b71c1c;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
        }

        .alert.success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #2e7d32;
        }

        .alert.error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #c62828;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: 1;
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

            .form-container {
                padding: 20px;
            }

            .table-container {
                padding: 20px;
                overflow-x: auto;
            }

            table {
                min-width: 600px;
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

            .form-container {
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

            .form-actions {
                flex-direction: column;
            }

            .btn-cancel,
            .btn-register {
                width: 100%;
            }

            .form-group label {
                font-size: 13px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 13px;
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
        <!-- <img src="Icon/motogam_logo.jpg" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Transfer Approval</h2>
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
                            $branch_code_value = htmlspecialchars($branch_row['branch_code']);
                            $branch_name_value = htmlspecialchars($branch_row['branch_name']);
                            $branches_options .= "<option value=\"{$branch_code_value}\" data-name=\"{$branch_name_value}\">{$branch_display}</option>";
                        }
                    }
                    
                    echo '<div class="filter-group">';
                    echo '    <label>Branch From</label>';
                    echo '    <select id="branch_from_filter">';
                    echo '        <option value="">Select Branch From</option>';
                    echo '        <option value="ALL">All Branches</option>';
                    echo $branches_options;
                    echo '    </select>';
                    echo '</div>';
                    
                    echo '<div class="filter-group">';
                    echo '    <label>Branch To</label>';
                    echo '    <select id="branch_to_filter">';
                    echo '        <option value="">Select Branch To</option>';
                    echo '        <option value="ALL">All Branches</option>';
                    echo $branches_options;
                    echo '    </select>';
                    echo '</div>';
                }
                ?>
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
                    <button class="btn-search" onclick="searchTransfers()">SEARCH</button>
                    <button class="btn-print" onclick="printCurrentTable()">PREVIEW ALL</button>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ST NO</th>
                        <th>DATE</th>
                        <th>BRANCH FROM</th>
                        <th>BRANCH TO</th>
                        <th>PREPARED BY</th>
                        <th>APPROVER</th>
                        <th>STATUS</th>
                        <th>REMARKS</th>
                        <th>VIEW</th>
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
                                <div style="color: #666; font-size: 13px;">Please select a status filter from the dropdown above to view transfers.</div>
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
            const savedFilters = localStorage.getItem('transferApprovalFilters');
            
            if (savedFilters) {
                try {
                    const filters = JSON.parse(savedFilters);
                    if (filters.date_from) document.getElementById('date_from').value = filters.date_from;
                    if (filters.date_to) document.getElementById('date_to').value = filters.date_to;
                    // Don't restore status filter - always start with "Select Status"
                    // if (filters.status) document.getElementById('status_filter').value = filters.status;
                    
                    // Don't automatically reload - require explicit filter selection
                } catch (e) {
                    console.error('Error loading saved filters:', e);
                }
            }
        });

        function searchTransfers() { loadTransfers(); }

        function loadTransfers() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const status = document.getElementById('status_filter').value;
            
            // Get branch filters if they exist (only for Super-Admin and Sub-admin)
            const branchFromElement = document.getElementById('branch_from_filter');
            const branchToElement = document.getElementById('branch_to_filter');
            const branchFrom = branchFromElement ? branchFromElement.value : '';
            const branchTo = branchToElement ? branchToElement.value : '';

            // Check if status filter is selected
            if (!status || status === '') {
                // Show message to select a filter
                const tbody = document.getElementById('approvalTableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 30px 20px;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                <div style="color: #333; font-size: 15px; font-weight: 600;">SELECT A STATUS FILTER TO DISPLAY THE DATA</div>
                                <div style="color: #666; font-size: 13px;">Please select a status filter from the dropdown above to view transfers.</div>
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
                branch_from: branchFrom,
                branch_to: branchTo
            };
            localStorage.setItem('transferApprovalFilters', JSON.stringify(filters));

            // Build POST parameters
            let postParams = `date_from=${dateFrom}&date_to=${dateTo}&status=${status}`;
            if (branchFrom) postParams += `&branch_from=${branchFrom}`;
            if (branchTo) postParams += `&branch_to=${branchTo}`;

            // Temporarily use test file
            fetch('get_transfer_approvals.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: postParams
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers.get('content-type'));
                    return response.text();
                })
                .then(text => {
                    console.log('Response text:', text);
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed data:', data);
                        // get_transfer_approvals.php returns an array directly, or an object with 'error'
                        if (Array.isArray(data)) {
                            renderTable(data);
                        } else if (data.error) {
                            document.getElementById('approvalTableBody').innerHTML =
                                `<tr><td colspan="11" style="text-align:center; padding:20px; color:#c62828;">Error: ${data.error}</td></tr>`;
                        } else {
                            renderTable([]);
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response was:', text);
                        document.getElementById('approvalTableBody').innerHTML =
                            '<tr><td colspan="11" style="text-align:center; padding:20px; color:#c62828;">Error: Invalid response from server. Check console for details.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    document.getElementById('approvalTableBody').innerHTML =
                        '<tr><td colspan="11" style="text-align:center; padding:20px; color:#c62828;">Error loading transfers</td></tr>';
                });
        }

        function renderTable(transfers) {
            const tbody = document.getElementById('approvalTableBody');

            // Check if response contains an error
            if (transfers && transfers.error) {
                tbody.innerHTML = `<tr><td colspan="11" style="text-align:center; padding:20px; color:#c62828;">Error: ${transfers.error}<br>File: ${transfers.file || 'unknown'}<br>Line: ${transfers.line || 'unknown'}</td></tr>`;
                return;
            }

            if (!transfers || transfers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" style="text-align:center; padding:20px;">No transfers found</td></tr>';
                return;
            }

            tbody.innerHTML = '';
            transfers.forEach(transfer => {
                const tr = document.createElement('tr');
                let statusBadge = `<span class="status-badge ${transfer.status.toLowerCase()}">${transfer.status}</span>`;
                
                // Show disapprove button for Pending, show red badge for Disapproved status
                let disapproveBtn;
                if (transfer.status === 'Pending') {
                    disapproveBtn = `<button class="btn-disapprove" onclick="updateStatus('${transfer.st_number}', 'Disapproved')">Disapprove</button>`;
                } else if (transfer.status === 'Disapproved') {
                    disapproveBtn = `<span class="status-badge disapproved">Disapproved</span>`;
                } else {
                    disapproveBtn = '-';
                }
                
                // Show approve button for Pending, show green badge for Approved status
                let approveBtn;
                if (transfer.status === 'Pending') {
                    approveBtn = `<button class="btn-approve" onclick="updateStatus('${transfer.st_number}', 'Approved')">Approve</button>`;
                } else if (transfer.status === 'Approved') {
                    approveBtn = `<span class="status-badge approved">Approved</span>`;
                } else {
                    approveBtn = '-';
                }

                tr.innerHTML = `
                    <td>${transfer.st_number}</td>
                    <td>${transfer.date}</td>
                    <td>${transfer.branch_from}</td>
                    <td>${transfer.branch_to}</td>
                    <td>${transfer.prepared_by}</td>
                    <td>${transfer.approver || '-'}</td>
                    <td>${statusBadge}</td>
                    <td>${transfer.remarks || '-'}</td>
                    <td><button class="btn-preview" onclick="printTransfer('${transfer.st_number}')">Preview</button></td>
                    <td>${disapproveBtn}</td>
                    <td>${approveBtn}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function updateStatus(stNumber, newStatus) {
            if (!confirm(`Are you sure you want to ${newStatus.toLowerCase()} this transfer?`)) return;

            fetch('update_transfer_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `st_number=${stNumber}&status=${newStatus}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Transfer ${newStatus.toLowerCase()} successfully!`);
                        // Reload the current table to show updated status
                        loadTransfers();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to update status'));
                    }
                })
                .catch(() => alert('Error updating transfer status'));
        }

        function printTransfer(stNumber) {
            // Open PDF in new window
            window.open('print_transfer_approval_pdf.php?st_number=' + encodeURIComponent(stNumber), '_blank', 'width=900,height=700');
        }

        function printCurrentTable() {
            // Get current filter values
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const status = document.getElementById('status_filter').value;

            // Build URL with parameters
            let url = 'print_transfer_approval_report_pdf.php?';
            const params = [];

            if (dateFrom) params.push('date_from=' + encodeURIComponent(dateFrom));
            if (dateTo) params.push('date_to=' + encodeURIComponent(dateTo));
            if (status) params.push('status=' + encodeURIComponent(status));

            url += params.join('&');

            // Open PDF in new window
            window.open(url, '_blank', 'width=900,height=700');
        }
    </script>

</body>

</html>