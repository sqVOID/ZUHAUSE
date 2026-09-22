<?php
require_once 'session_check.php';
require_once 'config.php';

// Authorization Check
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

// Restrict 'User' from accessing this page
if (false) {
    header("Location: report.php");
    exit();
}


$create_table = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL,
    branch VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$conn->query($create_table);

// Handle form submission
$message = "";
$messageType = "";

// Get filter parameter early so it's available for redirects
$selected_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Handle activation
if (isset($_GET['activate'])) {
    $id = $conn->real_escape_string($_GET['activate']);
    $table = $conn->real_escape_string($_GET['table']);

    // Prevent Sub-admin from activating Super-Admin, Sub-admin accounts, or Superadmin position accounts
    if (strcasecmp($system_level, 'Sub-admin') === 0 && $table === 'accounts') {
        $check_result = $conn->query("SELECT system_level, position FROM accounts WHERE id = $id");
        if ($check_result && $check_result->num_rows > 0) {
            $check_data = $check_result->fetch_assoc();
            if ($check_data['system_level'] === 'Super-Admin' || 
                $check_data['system_level'] === 'Sub-admin' ||
                stripos($check_data['position'], 'superadmin') !== false || 
                stripos($check_data['position'], 'super admin') !== false) {
                $redirect_url = "useractivation.php?error=unauthorized";
                if (!empty($selected_filter)) {
                    $redirect_url .= "&status_filter=" . urlencode($selected_filter);
                }
                header("Location: $redirect_url");
                exit();
            }
        }
    }

    $sql = "UPDATE $table SET status='Activated' WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "User activated successfully!";
        $messageType = "success";
        $redirect_url = "useractivation.php?activated=1";
        if (!empty($selected_filter)) {
            $redirect_url .= "&status_filter=" . urlencode($selected_filter);
        }
        header("Location: $redirect_url");
        exit();
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Check for activation message from redirect
if (isset($_GET['activated']) && $_GET['activated'] == 1) {
    $message = "User activated successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Check for unauthorized error
if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $message = "Error: You do not have permission to manage Super-Admin or Sub-admin accounts, or Superadmin positions.";
    $messageType = "error";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Handle deactivation (change status to Deactivated)
if (isset($_GET['deactivate'])) {
    $id = $conn->real_escape_string($_GET['deactivate']);
    $table = $conn->real_escape_string($_GET['table']);

    // Prevent Sub-admin from deactivating Super-Admin, Sub-admin accounts, or Superadmin position accounts
    if (strcasecmp($system_level, 'Sub-admin') === 0 && $table === 'accounts') {
        $check_result = $conn->query("SELECT system_level, position FROM accounts WHERE id = $id");
        if ($check_result && $check_result->num_rows > 0) {
            $check_data = $check_result->fetch_assoc();
            if ($check_data['system_level'] === 'Super-Admin' || 
                $check_data['system_level'] === 'Sub-admin' ||
                stripos($check_data['position'], 'superadmin') !== false || 
                stripos($check_data['position'], 'super admin') !== false) {
                $redirect_url = "useractivation.php?error=unauthorized";
                if (!empty($selected_filter)) {
                    $redirect_url .= "&status_filter=" . urlencode($selected_filter);
                }
                header("Location: $redirect_url");
                exit();
            }
        }
    }

    $sql = "UPDATE $table SET status='Deactivated' WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        // Force logout the user immediately by deleting their active sessions
        if ($table === 'accounts') {
            $conn->query("DELETE FROM active_sessions WHERE user_id = $id");
        }
        
        $message = "User deactivated successfully!";
        $messageType = "success";
        $redirect_url = "useractivation.php";
        if (!empty($selected_filter)) {
            $redirect_url .= "?status_filter=" . urlencode($selected_filter);
        }
        header("Location: $redirect_url");
        exit();
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Fetch users based on logged-in user's system level and selected filter
// Only execute query if a filter is selected
$users_result = null;

if (!empty($selected_filter)) {
    // Build base query based on system level
    if (strcasecmp($system_level, 'Sub-admin') === 0 || strcasecmp($system_level, 'User') === 0) {
        // Sub-admin or User: only show User level accounts and exclude Superadmin positions
        $query = "
            SELECT id, 'N/A' as username, first_name, last_name, position, branch, status, created_at, 'N/A' as system_level, 'users' as source_table 
            FROM users 
            WHERE position NOT LIKE '%superadmin%' AND position NOT LIKE '%super admin%'
            UNION ALL 
            SELECT id, username, first_name, last_name, position, branch, status, created_at, system_level, 'accounts' as source_table 
            FROM accounts 
            WHERE system_level = 'User' AND position NOT LIKE '%superadmin%' AND position NOT LIKE '%super admin%'";
    } else {
        // Super-Admin: see all users
        $query = "
            SELECT id, 'N/A' as username, first_name, last_name, position, branch, status, created_at, 'N/A' as system_level, 'users' as source_table 
            FROM users 
            UNION ALL 
            SELECT id, username, first_name, last_name, position, branch, status, created_at, system_level, 'accounts' as source_table 
            FROM accounts";
    }
    
    // Wrap in subquery and apply status filter if not 'all'
    if ($selected_filter !== 'all') {
        $status_safe = $conn->real_escape_string($selected_filter);
        $query = "SELECT * FROM (" . $query . ") AS combined WHERE status = '" . $status_safe . "' ORDER BY created_at DESC";
    } else {
        $query .= " ORDER BY created_at DESC";
    }
    
    $users_result = $conn->query($query);
}

// Fetch all branches for dropdown
$branches_result = $conn->query("SELECT * FROM branches ORDER BY branch_name");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
        <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>User Activation</title>
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

        .btn-add-user {
            padding: 10px 24px;
            border: none;
            background: var(--color-gold);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .btn-add-user:hover {
            background: var(--color-gold-light);
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

        .form-group label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: white;
        }

        .form-group input::placeholder {
            color: #999;
        }

        .form-group input:focus,
        .form-group select:focus {
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

        .btn-activate {
            padding: 10px 24px;
            border: none;
            background: #2e7d32;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-activate:hover {
            background: #1b5e20;
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
            text-align: left !important;
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

        .status-badge.activated {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.deactivated {
            background: #ffebee;
            color: #c62828;
        }

        .status-badge.pending {
            background: #fff3e0;
            color: #e65100;
        }

        .btn-view {
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

        .btn-view:hover {
            background: #1565C0;
        }

        .btn-toggle-status {
            padding: 6px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-left: 5px;
            transition: background 0.3s;
        }

        .btn-toggle-status.deactivate {
            background: #c62828;
            color: white;
        }

        .btn-toggle-status.deactivate:hover {
            background: #b71c1c;
        }

        .btn-toggle-status.activate {
            background: #2e7d32;
            color: white;
        }

        .btn-toggle-status.activate:hover {
            background: #1b5e20;
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

        .btn-view-branches {
            padding: 5px 10px;
            border: none;
            background: #17a2b8;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-view-branches:hover {
            background: #138496;
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
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .close {
            color: #999;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 20px;
        }

        .close:hover,
        .close:focus {
            color: #333;
        }

        .modal-body {
            padding: 30px;
        }

        .view-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .view-field {
            display: flex;
            flex-direction: column;
        }

        .view-field label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .view-field .value {
            font-size: 14px;
            color: #333;
            padding: 10px 12px;
            background: #f5f5f5;
            border-radius: 4px;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: flex-end;
        }

        .btn-close-modal {
            padding: 10px 24px;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-close-modal:hover {
            background: #f5f5f5;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Medium screens - Stack action buttons at 952px and below */
        @media (max-width: 952px) {
            .btn-view,
            .btn-view-branches,
            .btn-toggle-status {
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

            .search-box {
                width: 200px;
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

            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .search-box {
                width: 100%;
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
            .btn-activate {
                width: 100%;
            }

            th,
            td {
                padding: 8px;
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
        <!-- <img src="Icon/imslogo2.svg" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>User Activation</h2>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php
        endif; ?>

        <div class="table-container">
            <div class="table-header">
                <h3>Registered Users</h3>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <select id="statusFilter" onchange="filterByStatus()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer;">
                        <option value="">Select Status</option>
                        <option value="all" <?php echo ($selected_filter === 'all') ? 'selected' : ''; ?>>All Status</option>
                        <option value="Activated" <?php echo ($selected_filter === 'Activated') ? 'selected' : ''; ?>>Activated</option>
                        <option value="Deactivated" <?php echo ($selected_filter === 'Deactivated') ? 'selected' : ''; ?>>Deactivated</option>
                    </select>
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search users..." onkeyup="searchTable()">
                        <svg viewBox="0 0 24 24">
                            <path
                                d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                        </svg>
                    </div>
                </div>
            </div>
            <table id="userTable">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <!-- <th>Email</th>
                        <th>Phone Number</th> -->
                        <th>Position</th>
                        <th>Branch</th>
                        <th>System Level</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($users_result === null) {
                        // No filter selected - show message
                        echo "<tr><td colspan='8' style='text-align: center; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>
                                <div style='display: flex; flex-direction: column; align-items: center; gap: 12px;'>
                                    <svg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 24 24' fill='none' stroke='#999' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'>
                                        <circle cx='12' cy='12' r='10'></circle>
                                        <line x1='12' y1='16' x2='12' y2='12'></line>
                                        <line x1='12' y1='8' x2='12.01' y2='8'></line>
                                    </svg>
                                    <div style='color: #333; font-size: 15px; font-weight: 600;'>SELECT A FILTER TO DISPLAY THE DATA</div>
                                    <div style='color: #666; font-size: 13px;'>Please select a status filter from the dropdown above to view users.</div>
                                </div>
                              </td></tr>";
                    } elseif ($users_result && $users_result->num_rows > 0) {
                        while ($row = $users_result->fetch_assoc()) {
                            $username = isset($row['username']) ? htmlspecialchars($row['username']) : 'N/A';
                            $fullName = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                            $status = htmlspecialchars($row['status']);
                            $statusClass = strtolower($status);
                            $sourceTable = $row['source_table'];
                            $systemLevel = isset($row['system_level']) ? htmlspecialchars($row['system_level']) : 'N/A';
                            echo "<tr>";
                            echo "<td>" . $username . "</td>";
                            echo "<td>" . $fullName . "</td>";
                            // echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                            // echo "<td>" . htmlspecialchars($row['phone_number']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['position']) . "</td>";
                            $branchContent = 'None';
                            if (!empty($row['branch'])) {
                                $branchList = htmlspecialchars($row['branch'], ENT_QUOTES);
                                $branchContent = "<button type='button' class='btn-view-branches' onclick='viewBranches(\"" . $branchList . "\")'>View</button>";
                            }
                            echo "<td>" . $branchContent . "</td>";
                            echo "<td>" . $systemLevel . "</td>";
                            echo "<td><span class='status-badge " . $statusClass . "'>" . $status . "</span></td>";
                            echo "<td>";
                            echo "<a href='#' class='btn-view' onclick='viewUser(" . json_encode($row) . "); return false;'>View</a>";
                            // Toggle Status Button
                            $toggleClass = ($status === 'Activated') ? 'deactivate' : 'activate';
                            $toggleText = ($status === 'Activated') ? 'Deactivate' : 'Activate';
                            $toggleAction = ($status === 'Activated') ? 'deactivate' : 'activate';
                            echo "<button class='btn-toggle-status " . $toggleClass . "' onclick='toggleUserStatus(" . $row['id'] . ", \"" . $sourceTable . "\", \"" . $status . "\")'>$toggleText</button>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' style='text-align: center !important; padding: 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>No users found for the selected filter</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- View Branches Modal -->
    <div id="viewBranchesModal" class="modal">
        <div class="modal-content" style="max-width: 400px; margin: 5% auto;">
            <div class="modal-header"
                style="display: block; text-align: center; font-weight: 700; font-size: 18px; padding: 20px 0; color: #000;">
                Registered Branches
            </div>
            <div class="modal-body" style="padding: 20px;">
                <ul id="viewBranchesList" style="list-style-type: none; padding: 0;">
                    <!-- Branches will be populated here -->
                </ul>
            </div>
            <div class="modal-footer" style="justify-content: center;">
                <button type="button" class="btn-close-modal" onclick="closeViewBranchesModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>User Details</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- <div class="view-row">
                    <div class="view-field">
                        <label>Username</label>
                        <div class="value" id="view_username"></div>
                    </div>
                   <div class="view-field">
                        Empty space 
                    </div>
                </div> -->
                <div class="view-row">
                    <div class="view-field">
                        <label>First Name</label>
                        <div class="value" id="view_first_name"></div>
                    </div>
                    <div class="view-field">
                        <label>Last Name</label>
                        <div class="value" id="view_last_name"></div>
                    </div>
                </div>
                <!-- <div class="view-row">
                    <div class="view-field">
                        <label>Email</label>
                        <div class="value" id="view_email"></div>
                    </div>
                    <div class="view-field">
                        <label>Phone Number</label>
                        <div class="value" id="view_phone_number"></div>
                    </div>
                </div> -->
                <div class="view-row">
                    <div class="view-field">
                        <label>Position</label>
                        <div class="value" id="view_position"></div>
                    </div>
                    <div class="view-field">
                        <label>Branch</label>
                        <div class="value" id="view_branch"></div>
                    </div>
                </div>
                <div class="view-row">
                    <div class="view-field">
                        <label>System Level</label>
                        <div class="value" id="view_system_level"></div>
                    </div>
                    <div class="view-field">
                        <label>Status</label>
                        <div class="value" id="view_status"></div>
                    </div>
                </div>
                <div class="view-row">
                    <div class="view-field">
                        <label>Revert Button (Cancel Invoice) Access</label>
                        <div class="value" id="view_revert_button_access"></div>
                    </div>
                    <div class="view-field">
                        <label>Transfer Button (Booklet) Access</label>
                        <div class="value" id="view_transfer_button_access"></div>
                    </div>
                </div>
                <div class="view-row">
                    <div class="view-field">
                        <label>Created At</label>
                        <div class="value" id="view_created_at"></div>
                    </div>
                    <div class="view-field">
                        <!-- Empty space for alignment -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-close-modal" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        const branchAreaMap = <?php
        $branch_area_map = [];
        if (isset($branches_result) && $branches_result && $branches_result->num_rows > 0) {
            $branches_result->data_seek(0);
            while ($b_row = $branches_result->fetch_assoc()) {
                $b_area = !empty($b_row['area']) ? $b_row['area'] : 'Uncategorized';
                $formatted_area = ucwords(str_replace('_', ' ', $b_area));
                $branch_area_map[$b_row['branch_name']] = $formatted_area;
            }
        }
        echo json_encode($branch_area_map);
        ?>;
        function toggleSidebar() {
            const menuBtn = document.querySelector('.menu-btn');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');

            menuBtn.classList.toggle('active');
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
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

        function viewUser(userData) {
            // document.getElementById('view_username').textContent = userData.username || 'N/A';
            document.getElementById('view_first_name').textContent = userData.first_name;
            document.getElementById('view_last_name').textContent = userData.last_name;
            // document.getElementById('view_email').textContent = userData.email;
            // document.getElementById('view_phone_number').textContent = userData.phone_number;
            document.getElementById('view_position').textContent = userData.position;
            document.getElementById('view_branch').textContent = userData.branch;
            document.getElementById('view_system_level').textContent = userData.system_level || 'N/A';
            document.getElementById('view_status').textContent = userData.status;
            
            // Format and display button access with capitalization and color
            const revertAccess = userData.revert_button_access || 'enabled';
            const transferAccess = userData.transfer_button_access || 'enabled';
            
            const revertElement = document.getElementById('view_revert_button_access');
            revertElement.textContent = revertAccess.charAt(0).toUpperCase() + revertAccess.slice(1);
            revertElement.style.color = revertAccess === 'enabled' ? '#2e7d32' : '#d32f2f';
            revertElement.style.fontWeight = '600';
            
            const transferElement = document.getElementById('view_transfer_button_access');
            transferElement.textContent = transferAccess.charAt(0).toUpperCase() + transferAccess.slice(1);
            transferElement.style.color = transferAccess === 'enabled' ? '#2e7d32' : '#d32f2f';
            transferElement.style.fontWeight = '600';
            
            document.getElementById('view_created_at').textContent = userData.created_at;

            document.getElementById('viewModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function (event) {
            const modal = document.getElementById('viewModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        function filterByStatus() {
            const statusFilter = document.getElementById('statusFilter').value;
            
            // If a filter is selected, reload page with the filter parameter
            if (statusFilter) {
                window.location.href = 'useractivation.php?status_filter=' + encodeURIComponent(statusFilter);
            } else {
                // If no filter, just reload the page
                window.location.href = 'useractivation.php';
            }
        }

        function searchTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const table = document.getElementById('userTable');
            const tbody = table.getElementsByTagName('tbody')[0];
            const rows = tbody.getElementsByTagName('tr');

            // Check if we're in the "no filter selected" state
            const firstRow = rows[0];
            if (firstRow && firstRow.cells[0] && firstRow.cells[0].colSpan == 8) {
                // This is the "SELECT A FILTER" message row, don't filter
                return;
            }

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                
                let searchMatch = false;
                if (!searchInput) {
                    searchMatch = true; // Show all if search is empty
                } else {
                    for (let j = 0; j < cells.length - 1; j++) {
                        const cellText = cells[j].textContent || cells[j].innerText;
                        if (cellText.toLowerCase().indexOf(searchInput) > -1) {
                            searchMatch = true;
                            break;
                        }
                    }
                }

                row.style.display = searchMatch ? '' : 'none';
            }
        }

        // Initialize: Check if we have data loaded
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('userTable');
            if (table) {
                const tbody = table.getElementsByTagName('tbody')[0];
                const rows = tbody.getElementsByTagName('tr');
                
                // Check if we're showing the "SELECT A FILTER" message
                if (rows.length > 0) {
                    const firstRow = rows[0];
                    if (firstRow && firstRow.cells[0] && firstRow.cells[0].colSpan == 8) {
                        // This is the "SELECT A FILTER" message, disable search
                        const searchInput = document.getElementById('searchInput');
                        searchInput.disabled = true;
                        searchInput.placeholder = "Select a filter first...";
                    }
                }
            }
        });

        // --- View Branches Modal Functions ---
        function viewBranches(branchesStr) {
            const modal = document.getElementById('viewBranchesModal');
            const list = document.getElementById('viewBranchesList');
            list.innerHTML = ''; // Clear previous

            if (branchesStr) {
                const branches = branchesStr.split(', ');
                const grouped = {};

                // Group branches by Area
                branches.forEach(branch => {
                    const bName = branch.trim();
                    if (bName) {
                        // Use the map injected by PHP
                        const area = (typeof branchAreaMap !== 'undefined' && branchAreaMap[bName]) ? branchAreaMap[bName] : 'Uncategorized';

                        if (!grouped[area]) {
                            grouped[area] = [];
                        }
                        grouped[area].push(bName);
                    }
                });

                // Display groups
                const areas = Object.keys(grouped).sort();

                areas.forEach(area => {
                    // Area Header
                    const headerLi = document.createElement('li');
                    headerLi.textContent = area;
                    headerLi.style.fontWeight = 'bold';
                    headerLi.style.backgroundColor = '#f2f2f2';
                    headerLi.style.padding = '8px 10px';
                    headerLi.style.borderBottom = '1px solid #ddd';
                    list.appendChild(headerLi);

                    // Branches in this area
                    grouped[area].forEach(b => {
                        const li = document.createElement('li');
                        li.textContent = b;
                        li.style.padding = '8px 10px 8px 25px'; // Indent
                        li.style.borderBottom = '1px solid #eee';
                        li.style.color = '#333';
                        list.appendChild(li);
                    });
                });

            } else {
                const li = document.createElement('li');
                li.textContent = 'No branches registered.';
                li.style.padding = '10px';
                li.style.textAlign = 'center';
                list.appendChild(li);
            }

            modal.style.display = 'flex';
        }

        function closeViewBranchesModal() {
            document.getElementById('viewBranchesModal').style.display = 'none';
        }

        // Update window.onclick to close both modals
        window.onclick = function (event) {
            const viewModal = document.getElementById('viewModal');
            const branchesModal = document.getElementById('viewBranchesModal');
            if (event.target == viewModal) {
                viewModal.style.display = "none";
            }
            if (event.target == branchesModal) {
                branchesModal.style.display = "none";
            }
        }

        function toggleUserStatus(id, table, currentStatus) {
            const action = (currentStatus === 'Activated') ? 'deactivate' : 'activate';
            const confirmMessage = (currentStatus === 'Activated') ? 'deactivate' : 'activate';

            if (confirm(`Are you sure you want to ${confirmMessage} this user?`)) {
                // Preserve status_filter when toggling status
                const urlParams = new URLSearchParams(window.location.search);
                const statusFilter = urlParams.get('status_filter');
                let redirectUrl = `?${action}=${id}&table=${table}`;
                if (statusFilter) {
                    redirectUrl += '&status_filter=' + encodeURIComponent(statusFilter);
                }
                window.location.href = redirectUrl;
            }
        }
    </script>
</body>

</html>
<?php
$conn->close();
?>