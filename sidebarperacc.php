<?php
require_once 'session_check.php';
include 'config.php';

// Authorization Check
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

// Ensure sidebar_access column exists on accounts table
$conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS sidebar_access TEXT DEFAULT NULL");

// Get filter parameter early so it's available for redirects
$selected_filter = isset($_GET['account_filter']) ? $_GET['account_filter'] : '';

// Handle Save (POST)
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sidebar'])) {
    $account_id = (int) $_POST['account_id'];
    
    // Get filter from POST if available (for Clear button), otherwise from GET
    if (isset($_POST['account_filter']) && !empty($_POST['account_filter'])) {
        $selected_filter = $_POST['account_filter'];
    }
    
    // Prevent Sub-admin and User from modifying Super-Admin accounts, Sub-admin accounts, or Superadmin position accounts
    if (strcasecmp($system_level, 'Sub-admin') === 0 || strcasecmp($system_level, 'User') === 0) {
        $check_result = $conn->query("SELECT system_level, position FROM accounts WHERE id = $account_id");
        if ($check_result && $check_result->num_rows > 0) {
            $check_data = $check_result->fetch_assoc();
            if ($check_data['system_level'] === 'Super-Admin' || 
                $check_data['system_level'] === 'Sub-admin' ||
                stripos($check_data['position'], 'superadmin') !== false || 
                stripos($check_data['position'], 'super admin') !== false) {
                $redirect_url = "sidebarperacc.php?error=unauthorized";
                if (!empty($selected_filter)) {
                    $redirect_url .= "&account_filter=" . urlencode($selected_filter);
                }
                header("Location: $redirect_url");
                exit();
            }
        }
    }
    
    $sidebar = isset($_POST['sidebar_access']) ? $conn->real_escape_string($_POST['sidebar_access']) : '';
    $sql = "UPDATE accounts SET sidebar_access='$sidebar' WHERE id='$account_id'";
    if ($conn->query($sql)) {
        $redirect_url = "sidebarperacc.php?updated=1";
        if (!empty($selected_filter)) {
            $redirect_url .= "&account_filter=" . urlencode($selected_filter);
        }
        header("Location: $redirect_url");
        exit();
    } else {
        $message = "Error: " . $conn->error;
        $messageType = 'error';
    }
}

// Messages
if (isset($_GET['updated'])) {
    $message = "Sidebar updated successfully!";
    $messageType = 'success';
    echo "<script>if(window.history.replaceState){window.history.replaceState(null,null,window.location.pathname);}</script>";
}
if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $message = "Error: You do not have permission to manage Super-Admin or Sub-admin accounts, or Superadmin positions.";
    $messageType = 'error';
    echo "<script>if(window.history.replaceState){window.history.replaceState(null,null,window.location.pathname);}</script>";
}

// Define sidebar items arrays
$processItems = [
    'Sales Entry',
    'Stock Transfer',
    'Upgrade Unit',
    'Refund',
    'Claim Item'
];
$purchaseOrderItems = [
    'Purchase Order',
    'PO Invoice per Branch'
];
$superadminItems = [
    'Modification Sales'
];
$subadminItems = [
    'Late Entry'
];
$voidProcessItems = [
    'Void Sales'
];
$approvalProcessItems = [
    'Transfer Approval'
];
$receiveProcessItems = [
    'Receive Purchase Order',
    'Receive Stock Transfer'
];
$otherItems = [
    // Items that shouldn't be in access control
];
$reportItems = [
    'Daily Sales Report',
    'Monthly Sales Report',
    'Payment Details Report',
    'Void Sales Report',
    'Upgrade Unit Report',
    'Receive Direct Delivery',
    'Stock Transfer Report',
    'Refund Report',
    'Stock on Hand'
    // 'Pre-order Report' // GLOBALLY HIDDEN
];
$preorderItems = [
    // 'Pre-order', // GLOBALLY HIDDEN
    // 'Claim Pre-order' // GLOBALLY HIDDEN
];
$userRegistrationItems = [
    'Account Registration',
    'User Activation',
    'Position Registration',
    'Sidebar Per Account'
];
$locationRegistrationItems = [
    'Promoter Registration',
    'Area Registration',
    'Branch Registration',
    'Dealer Registration'
    // 'Booklet Number Registration', // GLOBALLY HIDDEN
    // 'Skip Booklet Number' // GLOBALLY HIDDEN
];
$itemRegistrationItems = [
    'Supplier Registration',
    'Brand Registration',
    'Family Code Registration',
    'Department Registration',
    'Group Registration',
    'Item Registration',
    'Bank Registration'
];
$terminalRegistrationItems = [
    'Terminal Issuer Registration',
    'Terminal ID Registration'
];
$historyItems = [
    // 'IMEI History', // GLOBALLY HIDDEN
    // 'Item History' // GLOBALLY HIDDEN
];

// Fetch accounts based on logged-in user's system level and selected filter
// Only execute query if a filter is selected
$accounts = null;

if (!empty($selected_filter)) {
    // Build base query based on system level
    if (strcasecmp($system_level, 'Sub-admin') === 0 || strcasecmp($system_level, 'User') === 0) {
        // Sub-admin or User: exclude Super-Admin accounts, Sub-admin accounts, and Superadmin positions
        $query = "SELECT id, username, first_name, last_name, position, branch, system_level, sidebar_access 
                  FROM accounts 
                  WHERE system_level = 'User'
                  AND position NOT LIKE '%superadmin%' 
                  AND position NOT LIKE '%super admin%' 
                  ORDER BY first_name, last_name";
    } else {
        // Super-Admin: see all accounts
        $query = "SELECT id, username, first_name, last_name, position, branch, system_level, sidebar_access 
                  FROM accounts 
                  ORDER BY first_name, last_name";
    }
    
    $accounts = $conn->query($query);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
        <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Sidebar Per Account</title>
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
            background-color:#f0f0f0ff;
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

        /* Main content */
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

        /* Table */
        .table-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #333;
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
            color: #000;
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
            text-align: center;
        }

        td:first-child {
            border-left: 1px solid #ccc;
               text-align: left !important;
        }

        td:last-child {
            border-right: 1px solid #ccc;
        }

        tbody tr:hover {
            background: #fdf8f3;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-super {
            background: #fde68a;
            color: #92400e;
        }

        .badge-sub {
            background: #bfdbfe;
            color: #1e40af;
        }

        .badge-user {
            background: #d1fae5;
            color: #065f46;
        }

        .btn-view {
            padding: 6px 16px;
            border: none;
            background: #17a2b8;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view:hover {
            background: #138496;
        }

        /* View Details Modal */
        #viewDetailsModal .modal-body {
            padding: 20px 30px;
            max-height: 60vh;
            overflow-y: auto;
        }

        #viewDetailsModal .detail-group {
            margin-bottom: 20px;
        }

        #viewDetailsModal .detail-group h4 {
            font-size: 14px;
            font-weight: 600;
            color: #2e7d32;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #2e7d32;
        }

        #viewDetailsModal .detail-group ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        #viewDetailsModal .detail-group li {
            padding: 5px 0 5px 20px;
            font-size: 13px;
            color: #666;
            position: relative;
        }

        #viewDetailsModal .detail-group li:before {
            content: "✓";
            position: absolute;
            left: 5px;
            color: #2e7d32;
        }

        #viewDetailsModal .no-items {
            padding: 10px;
            text-align: center;
            color: #999;
            font-style: italic;
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
        }

        .btn-edit:hover {
            background: #1565C0;
        }

        .btn-clear {
            padding: 6px 16px;
            border: none;
            background: #d32f2f;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            margin-left: 5px;
        }

        .btn-clear:hover {
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

        /* Search bar */
        .search-bar {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-bar input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            width: 300px;
            outline: none;
        }

        .search-bar input:focus {
            border-color: #2e7d32;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            border: 1px solid #888;
            width: 90%;
            max-width: 1000px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            padding: 20px 0;
            border-bottom: 1px solid #eee;
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            color: #000;
        }

        .modal-body {
            padding: 20px 30px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
        }

        .btn-back {
            padding: 0;
            height: 36px;
            width: 100px;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            font-weight: 600;
            border: 1px solid #bbb;
            background: white;
            color: #333;
        }

        .btn-next {
            padding: 0;
            height: 36px;
            width: 100px;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            font-weight: 600;
            border: none;
            background: #2e7d32;
            color: white;
        }

        .btn-back:hover {
            background: #f5f5f5;
        }

        .btn-next:hover {
            background: #1b5e20;
        }

        .modal-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .modal-table thead {
            background: var(--color-gold-pale);
        }

        .modal-table th {
            text-align: center;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border: 1px solid #ccc;
        }

        .modal-table th:nth-child(2) {
            text-align: left;
        }

        .modal-table td {
            padding: 8px 12px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            vertical-align: middle;
        }

        .modal-table tbody tr:hover {
            background: #fdf8f3;
        }

        .modal-table .category-cell {
            font-weight: 600;
            background-color: #f9f9f9;
            text-align: left;
        }

        .modal-table .checkbox-cell {
            text-align: center;
            width: 60px;
            vertical-align: middle;
        }

        .modal-table .checkbox-cell input[type="checkbox"] {
            margin: 0 auto;
            display: block;
        }

        .modal-table .item-cell {
            padding-left: 20px;
        }

        #modal-account-name {
            display: none;
        }

        /* Responsive sidebar toggle for mobile/tablet */
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

            .form-container {
                padding: 20px;
            }

            .table-container {
                padding: 20px;
                overflow-x: auto;
            }
        }

        /* Medium screens - Stack action buttons at 952px and below */
        @media (max-width: 952px) {
            .btn-view,
            .btn-edit {
                display: block;
                width: 100%;
                margin: 3px 0;
                text-align: center;
                padding: 8px 12px;
                font-size: 11px;
            }

            /* Make action column wider to accommodate stacked buttons */
            td:last-child {
                min-width: 100px;
                padding: 8px;
                white-space: normal;
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

            .form-container {
                padding: 15px;
            }

            .table-container {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()">
            <span></span><span></span><span></span>
        </div>
        <!-- <img src="Icon/imslogo2.svg" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Sidebar Per Account</h2>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php
        endif; ?>

        <div class="table-container">
            <div class="table-header">
                <h3>Account Sidebar List</h3>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <select id="accountFilter" onchange="filterAccounts()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer;">
                        <option value="">Select Filter</option>
                        <option value="all" <?php echo ($selected_filter === 'all') ? 'selected' : ''; ?>>View All</option>
                    </select>
                    <div class="search-bar">
                        <input type="text" id="accountSearch" placeholder="Search by name or username…" oninput="filterTable()">
                    </div>
                </div>
            </div>

            <table id="accountTable">
                <thead>
                    <tr>
                        <th style="width:15%">Username</th>
                        <th style="width:20%">Full Name</th>
                        <th style="width:15%">Position</th>
                        <th style="width:15%">System Level</th>
                        <th style="width:20%">Current Sidebar Override</th>
                        <th style="width:15%">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($accounts === null): ?>
                        <tr><td colspan='6' style='text-align: center; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>
                                <div style='display: flex; flex-direction: column; align-items: center; gap: 12px;'>
                                    <svg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 24 24' fill='none' stroke='#999' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'>
                                        <circle cx='12' cy='12' r='10'></circle>
                                        <line x1='12' y1='16' x2='12' y2='12'></line>
                                        <line x1='12' y1='8' x2='12.01' y2='8'></line>
                                    </svg>
                                    <div style='color: #333; font-size: 15px; font-weight: 600;'>SELECT A FILTER TO DISPLAY THE DATA</div>
                                    <div style='color: #666; font-size: 13px;'>Please select a filter from the dropdown above to view accounts.</div>
                                </div>
                        </td></tr>
                    <?php elseif ($accounts && $accounts->num_rows > 0):
                        while ($row = $accounts->fetch_assoc()):
                            $overrideVal = htmlspecialchars($row['sidebar_access'] ?? '');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['position']); ?></td>
                                <td><?php echo htmlspecialchars($row['system_level']); ?></td>
                                <td style="font-size:11px; text-align:center;">
                                    <?php if (!empty($overrideVal)): ?>
                                        <button type="button" class="btn-view" onclick="viewSidebarDetails('<?php echo htmlspecialchars(addslashes($row['sidebar_access'] ?? '')); ?>')">View</button>
                                        <?php
                                    else: ?>
                                        <em style="color:#aaa">None (uses position default)</em>
                                        <?php
                                    endif; ?>
                                </td>
                                <td>
                                    <button class="btn-edit" onclick="openModal(
                                <?php echo $row['id']; ?>,
                                '<?php echo htmlspecialchars(addslashes($row['first_name'] . ' ' . $row['last_name'])); ?>',
                                '<?php echo htmlspecialchars(addslashes($row['sidebar_access'] ?? '')); ?>'
                            )">Set Sidebar</button>
                                    <?php if (!empty($row['sidebar_access'])): ?>
                                        <form method="POST" style="display:inline;"
                                            onsubmit="return confirm('Clear this account\'s sidebar override?')">
                                            <input type="hidden" name="account_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="sidebar_access" value="">
                                            <?php if (!empty($selected_filter)): ?>
                                                <input type="hidden" name="account_filter" value="<?php echo htmlspecialchars($selected_filter); ?>">
                                            <?php endif; ?>
                                            <button type="submit" name="save_sidebar" class="btn-clear">Clear</button>
                                        </form>
                                        <?php
                                    endif; ?>
                                </td>
                            </tr>
                            <?php
                        endwhile;
                    else: ?>
                        <tr>
                            <td colspan="6" style='text-align: center !important; padding: 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>No accounts found.</td>
                        </tr>
                        <?php
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sidebar Modal -->
    <div id="sidebarModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Sidebar Deactivation
                <span id="modal-account-name"></span>
            </div>
            <div class="modal-body">
                <form id="sidebarForm" method="POST">
                    <input type="hidden" name="account_id" id="modal_account_id">
                    <input type="hidden" name="sidebar_access" id="modal_sidebar_access">
                    <input type="hidden" name="save_sidebar" value="1">
                    <?php if (!empty($selected_filter)): ?>
                        <input type="hidden" name="account_filter" value="<?php echo htmlspecialchars($selected_filter); ?>">
                    <?php endif; ?>

                    <div style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <label style="font-weight:600; cursor:pointer; display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" id="selectAllSidebar" style="width:18px;height:18px;"
                                onchange="toggleSidebarSelectAll()">
                            Select All
                        </label>
                    </div>

                    <table class="modal-table">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">
                            
                                </th>
                                <th>Category</th>
                                <th>Item</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Process group -->
                            <?php foreach ($processItems as $index => $item): ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($processItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="process"
                                                style="width:16px;height:16px;"
                                                onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($processItems); ?>">Process</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox process-checkbox" 
                                                value="<?php echo $item; ?>" style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Purchase Order group -->
                            <?php foreach ($purchaseOrderItems as $index => $item): ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($purchaseOrderItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="purchase-order"
                                                style="width:16px;height:16px;"
                                                onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($purchaseOrderItems); ?>">Purchase Order</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox purchase-order-checkbox" 
                                                value="<?php echo $item; ?>" style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Reports group -->
                            <?php foreach ($reportItems as $index => $item): ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($reportItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="reports"
                                                style="width:16px;height:16px;"
                                                onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($reportItems); ?>">Reports</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox reports-checkbox" 
                                                value="<?php echo $item; ?>" style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Approval Process group -->
                            <?php foreach ($approvalProcessItems as $index => $item): ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($approvalProcessItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="approval-process"
                                                style="width:16px;height:16px;"
                                                onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($approvalProcessItems); ?>">Approval Process</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox approval-process-checkbox" 
                                                value="<?php echo $item; ?>" style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Receive Process group -->
                            <?php foreach ($receiveProcessItems as $index => $item): ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($receiveProcessItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="receive-process"
                                                style="width:16px;height:16px;"
                                                onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($receiveProcessItems); ?>">Receive Process</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox receive-process-checkbox" 
                                                value="<?php echo $item; ?>" style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Void Process group -->
                            <?php foreach ($voidProcessItems as $index => $item): ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($voidProcessItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="void-process"
                                                style="width:16px;height:16px;"
                                                onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($voidProcessItems); ?>">Void Process</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox void-process-checkbox" 
                                                value="<?php echo $item; ?>" style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Sub-admin group - Only visible for Sub-admin -->
                            <?php if (strcasecmp($system_level, 'Sub-admin') === 0): ?>
                                <?php foreach ($subadminItems as $index => $item): ?>
                                    <tr>
                                        <?php if ($index === 0): ?>
                                            <td class="checkbox-cell" rowspan="<?php echo count($subadminItems); ?>">
                                                <input type="checkbox" class="category-select-all" data-category="subadmin"
                                                    style="width:16px;height:16px;"
                                                    onchange="toggleCategorySelectAll(this)">
                                            </td>
                                            <td class="category-cell" rowspan="<?php echo count($subadminItems); ?>">Sub-admin</td>
                                        <?php endif; ?>
                                        <td class="item-cell">
                                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                                <input type="checkbox" class="sidebar-checkbox subadmin-checkbox" 
                                                    value="<?php echo $item; ?>" style="width:16px;height:16px;">
                                                <span><?php echo $item; ?></span>
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- User Registration group -->
                            <?php foreach ($userRegistrationItems as $index => $item): ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($userRegistrationItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="user-registration"
                                                style="width:16px;height:16px;"
                                                onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($userRegistrationItems); ?>">User Registration</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox user-registration-checkbox" 
                                                value="<?php echo $item; ?>" style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Location Registration group -->
                            <?php foreach ($locationRegistrationItems as $index => $item): ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($locationRegistrationItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="location-registration"
                                                style="width:16px;height:16px;"
                                                onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($locationRegistrationItems); ?>">Store Registration</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox location-registration-checkbox" 
                                                value="<?php echo $item; ?>" style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Item Registration group -->
                            <?php foreach ($itemRegistrationItems as $index => $item): ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($itemRegistrationItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="item-registration"
                                                style="width:16px;height:16px;"
                                                onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($itemRegistrationItems); ?>">Item Registration</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox item-registration-checkbox" 
                                                value="<?php echo $item; ?>" style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Terminal Registration group -->
                            <?php foreach ($terminalRegistrationItems as $index => $item): ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($terminalRegistrationItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="terminal-registration"
                                                style="width:16px;height:16px;"
                                                onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($terminalRegistrationItems); ?>">Terminal Registration</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox terminal-registration-checkbox" 
                                                value="<?php echo $item; ?>" style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-back" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn-next" onclick="applyAndSubmit()">Done</button>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="viewDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Deactivated Sidebar Details
            </div>
            <div class="modal-body" id="viewDetailsBody">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer" style="justify-content: center;">
                <button type="button" class="btn-back" onclick="closeViewDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.menu-btn').classList.toggle('active');
            document.querySelector('.sidebar').classList.toggle('hidden');
            document.querySelector('.main-content').classList.toggle('expanded');
        }
        function toggleSection(el) { 
            const section = el.parentElement;
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
            
            if (typeof saveSidebarState === 'function') { saveSidebarState(); }
        }

        // ── Table search and filter ─────────────────────────────────────────────
        function filterAccounts() {
            const filterValue = document.getElementById('accountFilter').value;
            
            // If a filter is selected, reload page with the filter parameter
            if (filterValue) {
                window.location.href = 'sidebarperacc.php?account_filter=' + encodeURIComponent(filterValue);
            } else {
                // If no filter, just reload the page
                window.location.href = 'sidebarperacc.php';
            }
        }

        function filterTable() {
            const searchInput = document.getElementById('accountSearch').value.toLowerCase();
            const table = document.getElementById('accountTable');
            const tbody = table.getElementsByTagName('tbody')[0];
            const rows = tbody.getElementsByTagName('tr');

            // Check if we're in the "no filter selected" state
            const firstRow = rows[0];
            if (firstRow && firstRow.cells[0] && firstRow.cells[0].colSpan == 6) {
                // This is the "SELECT A FILTER" message row, don't filter
                return;
            }

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const rowText = row.innerText.toLowerCase();
                const searchMatch = searchInput === '' || rowText.includes(searchInput);
                row.style.display = searchMatch ? '' : 'none';
            }
        }

        // Initialize: Check if we have data loaded
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('accountTable');
            if (table) {
                const tbody = table.getElementsByTagName('tbody')[0];
                const rows = tbody.getElementsByTagName('tr');
                
                // Check if we're showing the "SELECT A FILTER" message
                if (rows.length > 0) {
                    const firstRow = rows[0];
                    if (firstRow && firstRow.cells[0] && firstRow.cells[0].colSpan == 6) {
                        // This is the "SELECT A FILTER" message, disable search
                        const searchInput = document.getElementById('accountSearch');
                        searchInput.disabled = true;
                        searchInput.placeholder = "Select a filter first...";
                    }
                }
            }
        });

        // ── Modal ───────────────────────────────────────────────────────────
        function openModal(accountId, fullName, currentSidebar) {
            document.getElementById('modal_account_id').value = accountId;
            document.getElementById('modal-account-name').textContent = fullName;

            // Reset all checkboxes
            document.querySelectorAll('.sidebar-checkbox').forEach(cb => cb.checked = false);

            // Tick the ones in currentSidebar
            if (currentSidebar) {
                const items = currentSidebar.split(',').map(s => s.trim());
                document.querySelectorAll('.sidebar-checkbox').forEach(cb => {
                    if (items.includes(cb.value)) cb.checked = true;
                });
            }

            updateSidebarSelectAllState();
            document.getElementById('sidebarModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('sidebarModal').style.display = 'none';
        }

        function applyAndSubmit() {
            const selected = [];
            document.querySelectorAll('.sidebar-checkbox:checked').forEach(cb => selected.push(cb.value));
            document.getElementById('modal_sidebar_access').value = selected.join(',');
            document.getElementById('sidebarForm').submit();
        }

        function toggleSidebarSelectAll() {
            const selectAll = document.getElementById('selectAllSidebar');
            const tableSelectAll = document.getElementById('tableSelectAll');
            
            // Sync both checkboxes
            if (selectAll) tableSelectAll.checked = selectAll.checked;
            if (tableSelectAll) selectAll.checked = tableSelectAll.checked;
            
            const allChecked = selectAll ? selectAll.checked : tableSelectAll.checked;
            document.querySelectorAll('.sidebar-checkbox').forEach(cb => cb.checked = allChecked);
            updateSidebarSelectAllState();
        }

        function toggleCategorySelectAll(checkbox) {
            const category = checkbox.getAttribute('data-category');
            const categoryCheckboxes = document.querySelectorAll('.' + category + '-checkbox');
            categoryCheckboxes.forEach(cb => cb.checked = checkbox.checked);
            updateSidebarSelectAllState();
        }

        function updateSidebarSelectAllState() {
            const checkboxes = document.querySelectorAll('.sidebar-checkbox');
            const selectAll = document.getElementById('selectAllSidebar');
            const tableSelectAll = document.getElementById('tableSelectAll');
            
            let allChecked = true;
            checkboxes.forEach(cb => { if (!cb.checked) allChecked = false; });
            
            if (selectAll) selectAll.checked = allChecked && checkboxes.length > 0;
            if (tableSelectAll) tableSelectAll.checked = allChecked && checkboxes.length > 0;

            // Update category checkboxes
            document.querySelectorAll('.category-select-all').forEach(catCheckbox => {
                const category = catCheckbox.getAttribute('data-category');
                const categoryCheckboxes = document.querySelectorAll('.' + category + '-checkbox');
                if (categoryCheckboxes.length === 0) return;
                
                let categoryAll = true;
                categoryCheckboxes.forEach(cb => { if (!cb.checked) categoryAll = false; });
                catCheckbox.checked = categoryAll;
            });
        }

        // Sync on change
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.sidebar-checkbox').forEach(cb =>
                cb.addEventListener('change', updateSidebarSelectAllState)
            );
        });

        // Close on backdrop click
        window.onclick = e => {
            const modal = document.getElementById('sidebarModal');
            const viewModal = document.getElementById('viewDetailsModal');
            if (e.target === modal) closeModal();
            if (e.target === viewModal) closeViewDetailsModal();
        };

        // View sidebar details function
        function viewSidebarDetails(sidebarAccess) {
            const modal = document.getElementById('viewDetailsModal');
            const modalBody = document.getElementById('viewDetailsBody');
            
            if (!sidebarAccess || sidebarAccess.trim() === '') {
                modalBody.innerHTML = '<div class="no-items">No deactivated sidebar items</div>';
                modal.style.display = 'flex';
                return;
            }

            const items = sidebarAccess.split(',').map(item => item.trim());
            
            // Categorize items based on the arrays defined in PHP
            const processItems = ['Sales Entry', 'Stock Transfer', 'Upgrade Unit', 'Refund', 'Claim Item'];
            const purchaseOrderItems = ['Purchase Order', 'PO Invoice per Branch'];
            const subadminItems = ['Late Entry'];
            const voidProcessItems = ['Void Sales'];
            const approvalProcessItems = ['Transfer Approval'];
            const receiveProcessItems = ['Receive Purchase Order', 'Receive Stock Transfer'];
            const reportItems = ['Daily Sales Report', 'Monthly Sales Report', 'Payment Details Report', 'Void Sales Report', 'Upgrade Unit Report', 'Receive Direct Delivery', 'Stock Transfer Report', 'Refund Report', 'Stock on Hand'];
            const userRegistrationItems = ['Account Registration', 'User Activation', 'Position Registration', 'Sidebar Per Account'];
            const locationRegistrationItems = ['Promoter Registration', 'Area Registration', 'Branch Registration', 'Dealer Registration'];
            const itemRegistrationItems = ['Supplier Registration', 'Brand Registration', 'Family Code Registration', 'Department Registration', 'Group Registration', 'Item Registration'];
            const terminalRegistrationItems = ['Terminal Issuer Registration', 'Terminal ID Registration'];
            
            const process = items.filter(item => processItems.includes(item));
            const purchaseOrder = items.filter(item => purchaseOrderItems.includes(item));
            const subadmin = items.filter(item => subadminItems.includes(item));
            const voidProcess = items.filter(item => voidProcessItems.includes(item));
            const approvalProcess = items.filter(item => approvalProcessItems.includes(item));
            const receiveProcess = items.filter(item => receiveProcessItems.includes(item));
            const reports = items.filter(item => reportItems.includes(item));
            const userRegistration = items.filter(item => userRegistrationItems.includes(item));
            const locationRegistration = items.filter(item => locationRegistrationItems.includes(item));
            const itemRegistration = items.filter(item => itemRegistrationItems.includes(item));
            const terminalRegistration = items.filter(item => terminalRegistrationItems.includes(item));
            
            let html = '';
            
            if (process.length > 0) {
                html += '<div class="detail-group"><h4>Process</h4><ul>';
                process.forEach(item => html += `<li>${item}</li>`);
                html += '</ul></div>';
            }
            
            if (purchaseOrder.length > 0) {
                html += '<div class="detail-group"><h4>Purchase Order</h4><ul>';
                purchaseOrder.forEach(item => html += `<li>${item}</li>`);
                html += '</ul></div>';
            }
            
            if (reports.length > 0) {
                html += '<div class="detail-group"><h4>Reports</h4><ul>';
                reports.forEach(item => html += `<li>${item}</li>`);
                html += '</ul></div>';
            }
            
            if (approvalProcess.length > 0) {
                html += '<div class="detail-group"><h4>Approval Process</h4><ul>';
                approvalProcess.forEach(item => html += `<li>${item}</li>`);
                html += '</ul></div>';
            }
            
            if (receiveProcess.length > 0) {
                html += '<div class="detail-group"><h4>Receive Process</h4><ul>';
                receiveProcess.forEach(item => html += `<li>${item}</li>`);
                html += '</ul></div>';
            }
            
            if (voidProcess.length > 0) {
                html += '<div class="detail-group"><h4>Void Process</h4><ul>';
                voidProcess.forEach(item => html += `<li>${item}</li>`);
                html += '</ul></div>';
            }
            
            if (subadmin.length > 0) {
                html += '<div class="detail-group"><h4>Sub-admin</h4><ul>';
                subadmin.forEach(item => html += `<li>${item}</li>`);
                html += '</ul></div>';
            }
            
            if (userRegistration.length > 0) {
                html += '<div class="detail-group"><h4>User Registration</h4><ul>';
                userRegistration.forEach(item => html += `<li>${item}</li>`);
                html += '</ul></div>';
            }
            
            if (locationRegistration.length > 0) {
                html += '<div class="detail-group"><h4>Store Registration</h4><ul>';
                locationRegistration.forEach(item => html += `<li>${item}</li>`);
                html += '</ul></div>';
            }
            
            if (itemRegistration.length > 0) {
                html += '<div class="detail-group"><h4>Item Registration</h4><ul>';
                itemRegistration.forEach(item => html += `<li>${item}</li>`);
                html += '</ul></div>';
            }
            
            if (terminalRegistration.length > 0) {
                html += '<div class="detail-group"><h4>Terminal Registration</h4><ul>';
                terminalRegistration.forEach(item => html += `<li>${item}</li>`);
                html += '</ul></div>';
            }
            
            if (html === '') {
                html = '<div class="no-items">No deactivated sidebar items</div>';
            }
            
            modalBody.innerHTML = html;
            modal.style.display = 'flex';
        }

        function closeViewDetailsModal() {
            document.getElementById('viewDetailsModal').style.display = 'none';
        }
    </script>
</body>

</html>