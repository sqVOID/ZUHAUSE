<?php
require_once 'session_check.php';
include 'config.php';

// Create upgrade approval log table if it doesn't exist
$create_log_table = "CREATE TABLE IF NOT EXISTS upgrade_approval_log (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    upgrade_id INT(11) NOT NULL,
    upgrade_no VARCHAR(50) NOT NULL,
    original_invoice_no VARCHAR(50),
    new_invoice_no VARCHAR(50),
    branch VARCHAR(255),
    branch_code VARCHAR(10),
    reason TEXT,
    remarks TEXT,
    total_amount DECIMAL(10,2) DEFAULT 0,
    created_by VARCHAR(100),
    created_at DATETIME,
    approver VARCHAR(100),
    approval_date DATETIME,
    disapprover VARCHAR(100),
    disapproval_date DATETIME,
    disapproval_reason TEXT,
    status VARCHAR(20) DEFAULT 'Pending',
    INDEX idx_upgrade_id (upgrade_id),
    INDEX idx_upgrade_no (upgrade_no),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_branch (branch)
)";
$conn->query($create_log_table);

// Check if columns exist and add if needed
$columns_to_check = [
    'approver' => "VARCHAR(100) AFTER created_at",
    'approval_date' => "DATETIME AFTER approver",
    'disapproval_reason' => "TEXT AFTER disapproval_date",
    'disapprover' => "VARCHAR(100) AFTER approval_date",
    'disapproval_date' => "DATETIME AFTER disapprover",
    'status' => "VARCHAR(20) DEFAULT 'Pending' AFTER disapproval_date"
];

foreach ($columns_to_check as $column => $definition) {
    $check = "SHOW COLUMNS FROM upgrade_approval_log LIKE '$column'";
    $result = $conn->query($check);
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE upgrade_approval_log ADD COLUMN $column $definition");
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Approval Upgrade Unit</title>
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
            <h2>Upgrade Unit Approval</h2>
        </div>

        <div class="filter-container">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" id="date_from" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" id="date_to" value="<?php echo date('Y-m-d'); ?>">
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
                        <th>UPGRADE NO.</th>
                        <th>ORIGINAL INVOICE</th>
                        <th>NEW INVOICE</th>
                        <th>BRANCH</th>
                        <th>REASON</th>
                        <th>TOTAL AMOUNT</th>
                        <th>CREATED BY</th>
                        <th>APPROVER</th>
                        <th>STATUS</th>
                        <th>PREVIEW</th>
                        <th>DISAPPROVE</th>
                        <th>APPROVE</th>
                    </tr>
                </thead>
                <tbody id="approvalTableBody">
                    <tr>
                        <td colspan="13" style="text-align: center; padding: 30px 20px;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                <div style="color: #333; font-size: 15px; font-weight: 600;">SELECT A STATUS FILTER TO DISPLAY THE DATA</div>
                                <div style="color: #666; font-size: 13px;">Please select a status filter from the dropdown above to view upgrade unit entries.</div>
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
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Try to restore filters from localStorage
            const savedFilters = localStorage.getItem('upgradeUnitApprovalFilters');
            
            if (savedFilters) {
                try {
                    const filters = JSON.parse(savedFilters);
                    if (filters.date_from) document.getElementById('date_from').value = filters.date_from;
                    if (filters.date_to) document.getElementById('date_to').value = filters.date_to;
                    if (filters.status) document.getElementById('status_filter').value = filters.status;
                    const branchElement = document.getElementById('branch_filter');
                    if (branchElement && filters.branch) branchElement.value = filters.branch;
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
            
            // Get branch filter if it exists (only for Super-Admin and Sub-admin)
            const branchElement = document.getElementById('branch_filter');
            const branch = branchElement ? branchElement.value : '';

            // Check if status filter is selected
            if (!status || status === '') {
                const tbody = document.getElementById('approvalTableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="13" style="text-align: center; padding: 30px 20px;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                <div style="color: #333; font-size: 15px; font-weight: 600;">SELECT A STATUS FILTER TO DISPLAY THE DATA</div>
                                <div style="color: #666; font-size: 13px;">Please select a status filter from the dropdown above to view upgrade unit entries.</div>
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
                branch: branch
            };
            localStorage.setItem('upgradeUnitApprovalFilters', JSON.stringify(filters));

            // Build POST parameters
            let postParams = `date_from=${dateFrom}&date_to=${dateTo}&status=${status}`;
            if (branch) postParams += `&branch=${branch}`;

            fetch('get_upgrade_approvals.php', {
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
                                `<tr><td colspan="13" style="text-align:center; padding:20px; color:#c62828;">Error: ${data.error}</td></tr>`;
                        } else {
                            renderTable([]);
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response was:', text);
                        document.getElementById('approvalTableBody').innerHTML =
                            '<tr><td colspan="13" style="text-align:center; padding:20px; color:#c62828;">Error: Invalid response from server. Check console for details.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    document.getElementById('approvalTableBody').innerHTML =
                        '<tr><td colspan="13" style="text-align:center; padding:20px; color:#c62828;">Error loading entries</td></tr>';
                });
        }

        function renderTable(entries) {
            const tbody = document.getElementById('approvalTableBody');

            if (entries && entries.error) {
                tbody.innerHTML = `<tr><td colspan="13" style="text-align:center; padding:20px; color:#c62828;">Error: ${entries.error}</td></tr>`;
                return;
            }

            if (!entries || entries.length === 0) {
                tbody.innerHTML = '<tr><td colspan="13" style="text-align:center; padding:20px;">No entries found</td></tr>';
                return;
            }

            tbody.innerHTML = '';
            entries.forEach(entry => {
                const tr = document.createElement('tr');
                let statusBadge = `<span class="status-badge ${entry.status.toLowerCase()}">${entry.status}</span>`;
                
                // Approval/Disapproval by
                let approverText = '-';
                if (entry.status === 'Approved' && entry.approver) {
                    approverText = entry.approver;
                } else if (entry.status === 'Disapproved' && entry.disapprover) {
                    approverText = entry.disapprover;
                }
                
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
                    <td>${entry.date}</td>
                    <td>${entry.upgrade_no}</td>
                    <td>${entry.original_invoice_no}</td>
                    <td>${entry.new_invoice_no}</td>
                    <td>${entry.branch}</td>
                    <td>${entry.reason}</td>
                    <td style="text-align: right;">₱${entry.total_amount}</td>
                    <td>${entry.created_by}</td>
                    <td>${approverText}</td>
                    <td>${statusBadge}</td>
                    <td><button class="btn-preview" onclick="previewUpgrade('${entry.upgrade_no}')">Preview</button></td>
                    <td>${disapproveBtn}</td>
                    <td>${approveBtn}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function previewUpgrade(upgradeNo) {
            const width = 900;
            const height = 700;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            const features = `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes,status=yes`;
            window.open(`preview_upgrade.php?upgrade_no=${encodeURIComponent(upgradeNo)}`, 'PreviewUpgrade', features);
        }

        function updateStatus(id, status) {
            let reason = '';
            
            // If disapproving, ask for reason
            if (status === 'Disapproved') {
                reason = prompt('Please enter the reason for disapproval:');
                if (reason === null) {
                    // User clicked cancel
                    return;
                }
                if (reason.trim() === '') {
                    alert('Disapproval reason is required.');
                    return;
                }
            }
            
            if (!confirm(`Are you sure you want to ${status.toLowerCase()} this upgrade unit entry?`)) {
                return;
            }

            const formData = `id=${id}&status=${status}${status === 'Disapproved' ? '&reason=' + encodeURIComponent(reason) : ''}`;

            fetch('update_upgrade_approval.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Entry ${status.toLowerCase()} successfully!`);
                        loadEntries(); // Reload the table
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating status');
                });
        }
    </script>
</body>

</html>
