<?php
require_once 'session_check.php';

// Authorization Check - Check if user has access to this page
if (isset($_SESSION['sidebar_access']) && $_SESSION['sidebar_access'] !== '') {
    $sidebar_hidden = array_map('trim', explode(',', $_SESSION['sidebar_access']));
    if (in_array('Booklet Inventory', $sidebar_hidden)) {
        header("Location: report.php");
        exit();
    }
}

$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

// Check if transfer button access is disabled
$transfer_button_disabled = (isset($_SESSION['transfer_button_access']) && $_SESSION['transfer_button_access'] === 'disabled');

include 'config.php';

$message = "";
$messageType = "";

// Handle activation
if (isset($_POST['activate_booklet'])) {
    $id = $conn->real_escape_string($_POST['booklet_id']);
    $branch_code = $conn->real_escape_string($_POST['branch_code']);
    $used_by = $conn->real_escape_string($_SESSION['username'] ?? 'system');
    $used_date = date('Y-m-d H:i:s');
    
    // First, deactivate all other booklets for this branch
    $deactivate_sql = "UPDATE booklet_numbers SET status='Inactive' WHERE branch_code='$branch_code' AND status='Active'";
    $conn->query($deactivate_sql);
    
    // Then activate the selected booklet with usage tracking
    $activate_sql = "UPDATE booklet_numbers SET status='Active', last_used_date='$used_date', last_used_by='$used_by' WHERE id = $id";
    
    if ($conn->query($activate_sql) === TRUE) {
        $message = "Booklet activated successfully! All other booklets for this branch have been deactivated.";
        $messageType = "success";
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Handle deactivation
if (isset($_POST['deactivate_booklet'])) {
    $id = $conn->real_escape_string($_POST['booklet_id']);
    $sql = "UPDATE booklet_numbers SET status='Inactive' WHERE id = $id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Booklet deactivated successfully!";
        $messageType = "success";
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Handle return
if (isset($_POST['return_booklet'])) {
    $id = $conn->real_escape_string($_POST['booklet_id']);
    $return_by = $conn->real_escape_string($_SESSION['username'] ?? 'system');
    $return_branch = $conn->real_escape_string($_POST['return_branch']);
    $return_date = date('Y-m-d H:i:s');
    
    $sql = "UPDATE booklet_numbers SET return_date='$return_date', return_by='$return_by', return_branch='$return_branch' WHERE id = $id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Booklet returned successfully!";
        $messageType = "success";
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Handle transfer
if (isset($_POST['transfer_booklet'])) {
    $id = $conn->real_escape_string($_POST['booklet_id']);
    $transfer_from_branch = $conn->real_escape_string($_POST['transfer_from_branch']);
    $transfer_to_branch = $conn->real_escape_string($_POST['transfer_to_branch']);
    $transfer_by = $conn->real_escape_string($_SESSION['username'] ?? 'system');
    $transfer_date = date('Y-m-d H:i:s');
    
    // Update the booklet with new branch code, transfer tracking, and deactivate it
    $sql = "UPDATE booklet_numbers SET 
            branch_code='$transfer_to_branch', 
            status='Inactive', 
            transfer_date='$transfer_date', 
            transfer_by='$transfer_by', 
            transfer_from_branch='$transfer_from_branch'
            WHERE id = $id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Booklet transferred successfully to the new branch!";
        $messageType = "success";
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Branch access control
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

// For non-Super-Admin users, determine their branch codes
$user_branch_codes = [];
if (strcasecmp($system_level, 'Super-Admin') !== 0 && !empty($user_branch)) {
    // Handle multiple branches (comma-separated)
    $branch_names = array_map('trim', explode(',', $user_branch));
    $branch_names_quoted = array_map(function($name) use ($conn) {
        return "'" . $conn->real_escape_string($name) . "'";
    }, $branch_names);
    $branch_names_in = implode(',', $branch_names_quoted);
    
    // Get branch codes for these branch names
    $branch_code_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name IN ($branch_names_in)");
    if ($branch_code_query && $branch_code_query->num_rows > 0) {
        while ($row = $branch_code_query->fetch_assoc()) {
            $user_branch_codes[] = $row['branch_code'];
        }
    }
}

// Fetch branches for filter (only show branches user has access to)
if (strcasecmp($system_level, 'Super-Admin') === 0) {
    // Super-Admin sees all branches
    $branches_result = $conn->query("SELECT * FROM branches WHERE status = 'Active' ORDER BY branch_name ASC");
} else {
    // Other users see only their assigned branches
    if (!empty($user_branch_codes)) {
        $codes_quoted = array_map(function($code) use ($conn) {
            return "'" . $conn->real_escape_string($code) . "'";
        }, $user_branch_codes);
        $codes_in = implode(',', $codes_quoted);
        $branches_result = $conn->query("SELECT * FROM branches WHERE status = 'Active' AND branch_code IN ($codes_in) ORDER BY branch_name ASC");
    } else {
        // No valid branches, return empty result
        $branches_result = $conn->query("SELECT * FROM branches WHERE 1=0");
    }
}

// Build query based on branch filter and status filter
$branch_filter = isset($_GET['branch']) ? $conn->real_escape_string($_GET['branch']) : '';
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$area_filter = isset($_GET['area']) ? $conn->real_escape_string($_GET['area']) : '';

// Build WHERE clause with branch access control
$where_conditions = [];

// Apply user branch restriction for non-Super-Admin
if (strcasecmp($system_level, 'Super-Admin') !== 0) {
    if (!empty($user_branch_codes)) {
        $codes_quoted = array_map(function($code) use ($conn) {
            return "'" . $conn->real_escape_string($code) . "'";
        }, $user_branch_codes);
        $codes_in = implode(',', $codes_quoted);
        $where_conditions[] = "bn.branch_code IN ($codes_in)";
    } else {
        // No valid branches, show nothing
        $where_conditions[] = "1=0";
    }
}

// Apply area filter if selected
if ($area_filter && $area_filter !== 'ALL') {
    $where_conditions[] = "b.area = '$area_filter'";
}

// Apply dropdown filter if selected
if ($branch_filter && $branch_filter !== 'ALL') {
    $where_conditions[] = "bn.branch_code = '$branch_filter'";
}

// Apply status filter if selected
if ($status_filter && $status_filter !== 'ALL') {
    if ($status_filter == 'Completed') {
        // For completed status, check complete_date is not null and return_date is null
        $where_conditions[] = "bn.complete_date IS NOT NULL AND bn.return_date IS NULL";
    } elseif ($status_filter == 'Returned') {
        // For returned status, check return_date is not null
        $where_conditions[] = "bn.return_date IS NOT NULL";
    } else {
        // For Active/Inactive, use status column and ensure complete_date is null
        $where_conditions[] = "bn.status = '$status_filter' AND bn.complete_date IS NULL";
    }
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";

// Fetch all booklet numbers grouped by branch
$booklets_query = "SELECT bn.*, b.branch_name, b.area,
                   CASE 
                       WHEN bn.return_date IS NOT NULL THEN 4
                       WHEN bn.complete_date IS NOT NULL THEN 2
                       WHEN bn.status = 'Active' THEN 1
                       WHEN bn.status = 'Inactive' THEN 3
                       ELSE 5
                   END as sort_order
                   FROM booklet_numbers bn 
                   LEFT JOIN branches b ON bn.branch_code = b.branch_code 
                   $where_clause
                   ORDER BY b.branch_name ASC, sort_order ASC, bn.id ASC";
$booklets_result = $conn->query($booklets_query);

// Fetch distinct areas for filter dropdown (only show areas from user's accessible branches)
if (strcasecmp($system_level, 'Super-Admin') === 0) {
    // Super-Admin sees all areas
    $areas_query = "SELECT DISTINCT area FROM branches WHERE status = 'Active' AND area IS NOT NULL AND area != '' ORDER BY area ASC";
} else {
    // Other users see only areas from their accessible branches
    if (!empty($user_branch_codes)) {
        $codes_quoted = array_map(function($code) use ($conn) {
            return "'" . $conn->real_escape_string($code) . "'";
        }, $user_branch_codes);
        $codes_in = implode(',', $codes_quoted);
        $areas_query = "SELECT DISTINCT area FROM branches WHERE status = 'Active' AND branch_code IN ($codes_in) AND area IS NOT NULL AND area != '' ORDER BY area ASC";
    } else {
        // No valid branches, return empty result
        $areas_query = "SELECT DISTINCT area FROM branches WHERE 1=0";
    }
}
$areas_result = $conn->query($areas_query);

// Group booklets by branch
$booklets_by_branch = [];
if ($booklets_result && $booklets_result->num_rows > 0) {
    while ($row = $booklets_result->fetch_assoc()) {
        $branch_code = $row['branch_code'];
        if (!isset($booklets_by_branch[$branch_code])) {
            $booklets_by_branch[$branch_code] = [
                'branch_name' => $row['branch_name'] ?? 'Unknown Branch',
                'branch_code' => $branch_code,
                'booklets' => []
            ];
        }
        $booklets_by_branch[$branch_code]['booklets'][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Booklet Inventory</title>
    <style>
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
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .filter-section {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .filter-section label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        .filter-section select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            min-width: 200px;
            background: white;
        }

        .filter-section select:focus {
            outline: none;
            border-color: #2196F3;
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

        .branch-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }

        .branch-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #4CAF50;
        }

        .branch-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .branch-code {
            font-size: 14px;
            color: #666;
            background: #f5f5f5;
            padding: 5px 12px;
            border-radius: 4px;
        }

        .booklets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .booklet-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .booklet-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .booklet-card.active {
            border-color: #4CAF50;
            background: #f1f8f4;
        }

        .booklet-card.inactive {
            border-color: #ddd;
            opacity: 0.8;
        }

        .booklet-card.completed {
            border-color: #dc3545;
            background: #fef5f5;
        }

        .booklet-card.returned {
            border-color: #90a4ae;
            background: #f5f7f8;
        }

        .booklet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .booklet-number {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.active {
            background: #4CAF50;
            color: white;
        }

        .status-badge.inactive {
            background: #757575;
            color: white;
        }

        .status-badge.completed {
            background: #dc3545;
            color: white;
        }

        .status-badge.returned {
            background: #90a4ae;
            color: white;
        }

        .booklet-info {
            margin-bottom: 15px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #666;
            font-weight: 500;
        }

        .info-value {
            color: #333;
            font-weight: 600;
        }

        .booklet-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-activate {
            flex: 1;
            padding: 10px 20px;
            border: none;
            background: #4CAF50;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-activate:hover {
            background: #45a049;
        }

        .btn-deactivate {
            flex: 1;
            padding: 10px 20px;
            border: none;
            background: #dc3545;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-deactivate:hover {
            background: #c82333;
        }

        .btn-return {
            flex: 1;
            padding: 10px 20px;
            border: none;
            background: #dc3545;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-return:hover {
            background: #c82333;
        }

        .btn-returned {
            flex: 1;
            padding: 10px 20px;
            border: none;
            background: #90a4ae;
            color: white;
            border-radius: 4px;
            cursor: not-allowed;
            font-size: 13px;
            font-weight: 500;
        }

        .btn-transfer {
            flex: 1;
            padding: 10px 20px;
            border: none;
            background: #2196F3;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-transfer:hover {
            background: #1976D2;
        }

        .btn-activate:disabled,
        .btn-deactivate:disabled,
        .btn-transfer:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .no-booklets {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 15px;
        }

        .empty-state {
            background: white;
            padding: 60px 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            fill: #ccc;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 18px;
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 14px;
            color: #999;
            margin-bottom: 20px;
        }

        .btn-add-booklet {
            padding: 12px 28px;
            border: none;
            background: #4CAF50;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }

        .btn-add-booklet:hover {
            background: #45a049;
        }

        /* ── Activation Modal (Professional Design) ── */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            padding: 36px 40px;
            max-width: 420px;
            width: 90%;
            text-align: center;
            animation: modalIn 0.2s ease;
        }

        @keyframes modalIn {
            from {
                transform: scale(0.88);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .modal-header {
            display: none;
        }

        .modal-icon {
            font-size: 44px;
            margin-bottom: 14px;
        }

        .modal-title {
            font-size: 17px;
            font-weight: 700;
            color: #111;
            margin-bottom: 10px;
        }

        .modal-body {
            margin-bottom: 28px;
        }

        .modal-body p {
            font-size: 13px;
            color: #555;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .modal-body .highlight {
            font-weight: 600;
            color: #111;
        }

        .modal-footer {
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .btn-modal-cancel {
            padding: 10px 28px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            background: #f0f0f0;
            color: #444;
        }

        .btn-modal-cancel:hover {
            background: #e0e0e0;
        }

        .btn-modal-confirm {
            padding: 10px 28px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            background: #1a7a35;
            color: white;
        }

        .btn-modal-confirm:hover {
            background: #155e28;
        }

        .btn-modal-confirm.danger {
            background: #c62828;
        }

        .btn-modal-confirm.danger:hover {
            background: #b71c1c;
        }

        .close {
            display: none;
        }

        .no-search-message {
            background: white;
            padding: 60px 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
            margin-bottom: 20px;
        }

        .no-search-message h3 {
            font-size: 18px;
            color: #666;
            margin-bottom: 10px;
        }

        .no-search-message p {
            font-size: 14px;
            color: #999;
        }

        .empty-state {
            background: white;
            padding: 60px 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            fill: #ccc;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 18px;
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 14px;
            color: #999;
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
            <h2>Booklet Inventory</h2>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Filters Section - Always Visible -->
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); margin-bottom: 20px;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <select id="areaFilter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer; min-width: 150px;">
                    <option value="" selected>Select Area</option>
                    <option value="ALL">All Areas</option>
                    <?php
                    if ($areas_result && $areas_result->num_rows > 0) {
                        while ($area_row = $areas_result->fetch_assoc()) {
                            $area_value = htmlspecialchars($area_row['area']);
                            $selected = ($area_filter == $area_value) ? 'selected' : '';
                            echo "<option value='" . $area_value . "' " . $selected . ">" . $area_value . "</option>";
                        }
                    }
                    ?>
                </select>
                <select id="branchFilter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer; min-width: 150px;">
                    <option value="" selected>Select Branch</option>
                    <option value="ALL">All Branches</option>
                    <?php
                    if ($branches_result && $branches_result->num_rows > 0) {
                        $branches_result->data_seek(0);
                        while ($branch = $branches_result->fetch_assoc()) {
                            $selected = ($branch_filter == $branch['branch_code']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($branch['branch_code']) . "' " . $selected . ">" . htmlspecialchars($branch['branch_name']) . "</option>";
                        }
                    }
                    ?>
                </select>
                <select id="statusFilter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer; min-width: 150px;">
                    <option value="" selected>Select Status</option>
                    <option value="ALL">All Status</option>
                    <option value="Active" <?php echo ($status_filter == 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Completed" <?php echo ($status_filter == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="Returned" <?php echo ($status_filter == 'Returned') ? 'selected' : ''; ?>>Returned</option>
                    <option value="Inactive" <?php echo ($status_filter == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button onclick="applyFilters()" style="padding: 8px 20px; background: #1a1a1a; border: none; color: white; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; white-space: nowrap; transition: background 0.2s ease; height: 38px;">
                    Filter
                </button>
                <button onclick="hideBooklets()" style="padding: 8px 20px; background: #d32f2f; border: none; color: white; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; white-space: nowrap; transition: background 0.2s ease; height: 38px;">
                    Hide
                </button>
            </div>
        </div>

        <!-- No Search Message (shown initially) -->
        <div class="no-search-message" id="noSearchMessage">
            <svg viewBox="0 0 24 24" style="width: 80px; height: 80px; fill: #ccc; margin-bottom: 20px;">
                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
            </svg>
            <h3>Please Select Filters and Click Filter Button</h3>
            <p>Choose your filters from the dropdowns above and click the Filter button to view booklet inventory.</p>
        </div>

        <!-- Booklet Content (hidden by default) -->
        <div id="bookletContent" style="display: none;">
        <?php if (empty($booklets_by_branch)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24">
                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zm-7-2h2V7h-4v2h2z"/>
                </svg>
                <h3>No Booklets Found</h3>
                <p>There are no booklet numbers registered for your branch.</p>
            </div>
        <?php else: ?>
            <?php foreach ($booklets_by_branch as $branch_code => $branch_data): ?>
                <div class="branch-section">
                    <div class="branch-header">
                        <h3><?php echo htmlspecialchars($branch_data['branch_name']); ?></h3>
                        <span class="branch-code"><?php echo htmlspecialchars($branch_code); ?></span>
                    </div>
                    
                    <div class="booklets-grid">
                        <?php 
                        // Check if the current invoice number for the active booklet has been used in any transaction
                        $has_used_current_invoice = false;
                        
                        // Find the active booklet for this branch
                        $active_booklet_current = null;
                        $active_booklet_is_completed = false;
                        foreach ($branch_data['booklets'] as $check_booklet) {
                            if ($check_booklet['status'] == 'Active') {
                                $active_booklet_current = str_pad($check_booklet['current_number'], 4, '0', STR_PAD_LEFT);
                                $active_booklet_is_completed = !empty($check_booklet['complete_date']);
                                break;
                            }
                        }
                        
                        // If the active booklet is completed, allow switching to other booklets
                        if ($active_booklet_is_completed) {
                            $has_used_current_invoice = false; // Unlock buttons for switching
                        }
                        // If there's an active booklet that's NOT completed, check if any invoice number from this booklet has been used
                        elseif ($active_booklet_current) {
                            // Get the active booklet's beginning number
                            $active_booklet_beginning = null;
                            foreach ($branch_data['booklets'] as $check_booklet) {
                                if ($check_booklet['status'] == 'Active') {
                                    $active_booklet_beginning = (int)$check_booklet['beginning_number'];
                                    $current_num = (int)$check_booklet['current_number'];
                                    break;
                                }
                            }
                            
                            // If current > beginning, it means at least one number has been used
                            if ($active_booklet_beginning && $current_num > $active_booklet_beginning) {
                                $has_used_current_invoice = true;
                            }
                            
                            // Also check if current number exists in any table (double check)
                            if (!$has_used_current_invoice) {
                                // Check in sales table
                                $check_sales = $conn->query("SELECT COUNT(*) as count FROM sales WHERE invoice_no = '$active_booklet_current'");
                                if ($check_sales && $check_sales->fetch_assoc()['count'] > 0) {
                                    $has_used_current_invoice = true;
                                }
                                
                                // Check in preorders table if not found in sales
                                if (!$has_used_current_invoice) {
                                    $check_preorders = $conn->query("SELECT COUNT(*) as count FROM preorders WHERE invoice_no = '$active_booklet_current'");
                                    if ($check_preorders && $check_preorders->fetch_assoc()['count'] > 0) {
                                        $has_used_current_invoice = true;
                                    }
                                }
                                
                                // Check in claim_preorders table if not found
                                if (!$has_used_current_invoice) {
                                    $table_check = $conn->query("SHOW TABLES LIKE 'claim_preorders'");
                                    if ($table_check && $table_check->num_rows > 0) {
                                        $check_claim = $conn->query("SELECT COUNT(*) as count FROM claim_preorders WHERE invoice_no = '$active_booklet_current'");
                                        if ($check_claim && $check_claim->fetch_assoc()['count'] > 0) {
                                            $has_used_current_invoice = true;
                                        }
                                    }
                                }
                                
                                // Check in upgrade_unit table if exists and not found
                                if (!$has_used_current_invoice) {
                                    $table_check = $conn->query("SHOW TABLES LIKE 'upgrade_unit'");
                                    if ($table_check && $table_check->num_rows > 0) {
                                        $check_upgrade = $conn->query("SELECT COUNT(*) as count FROM upgrade_unit WHERE invoice_no = '$active_booklet_current'");
                                        if ($check_upgrade && $check_upgrade->fetch_assoc()['count'] > 0) {
                                            $has_used_current_invoice = true;
                                        }
                                    }
                                }
                            }
                        }
                        
                        foreach ($branch_data['booklets'] as $booklet): 
                            $status = htmlspecialchars($booklet['status']);
                            $status_class = strtolower($status);
                            $is_active = ($status == 'Active');
                            $is_completed = !empty($booklet['complete_date']);
                            $is_returned = !empty($booklet['return_date']);
                            
                            // Override status display for returned/completed booklets
                            if ($is_returned) {
                                $display_status = 'Returned';
                                $display_status_class = 'returned';
                            } elseif ($is_completed) {
                                $display_status = 'Completed';
                                $display_status_class = 'completed';
                            } else {
                                $display_status = $status;
                                $display_status_class = $status_class;
                            }
                            
                            // Disable buttons only if the current invoice number has been actually used in a transaction
                            $disable_buttons = $has_used_current_invoice;
                            ?>
                            <div class="booklet-card <?php echo $display_status_class; ?>">
                                <div class="booklet-header">
                                    <span class="booklet-number"><?php echo htmlspecialchars($booklet['booklet_no']); ?></span>
                                    <span class="status-badge <?php echo $display_status_class; ?>"><?php echo $display_status; ?></span>
                                </div>
                                
                                <div class="booklet-info">
                                    <div class="info-row">
                                        <span class="info-label">Beginning:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($booklet['beginning_number']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Ending:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($booklet['ending_number']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Current:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($booklet['current_number']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Created By:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($booklet['created_by'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Date Used:</span>
                                        <span class="info-value"><?php echo !empty($booklet['last_used_date']) ? date('M d, Y h:i A', strtotime($booklet['last_used_date'])) : '-'; ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Used By:</span>
                                        <span class="info-value"><?php echo !empty($booklet['last_used_by']) ? htmlspecialchars($booklet['last_used_by']) : '-'; ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Complete Date:</span>
                                        <span class="info-value" style="<?php echo !empty($booklet['complete_date']) ? 'color: #dc3545; font-weight: 700;' : ''; ?>">
                                            <?php echo !empty($booklet['complete_date']) ? date('M d, Y h:i A', strtotime($booklet['complete_date'])) : '-'; ?>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Return Date:</span>
                                        <span class="info-value" style="<?php echo !empty($booklet['return_date']) ? 'color: #4CAF50; font-weight: 700;' : ''; ?>">
                                            <?php echo !empty($booklet['return_date']) ? date('M d, Y h:i A', strtotime($booklet['return_date'])) : '-'; ?>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Return By:</span>
                                        <span class="info-value"><?php echo !empty($booklet['return_by']) ? htmlspecialchars($booklet['return_by']) : '-'; ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Return Branch:</span>
                                        <span class="info-value"><?php echo !empty($booklet['return_branch']) ? htmlspecialchars($booklet['return_branch']) : '-'; ?></span>
                                    </div>
                                    <?php if (!empty($booklet['transfer_date'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">Transfer Date:</span>
                                        <span class="info-value" style="color: #2196F3; font-weight: 700;">
                                            <?php echo date('M d, Y h:i A', strtotime($booklet['transfer_date'])); ?>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Transfer By:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($booklet['transfer_by']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Transfer From:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($booklet['transfer_from_branch']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="booklet-actions">
                                    <?php if ($is_active): ?>
                                        <button type="button" class="btn-deactivate" 
                                                <?php echo $disable_buttons ? 'disabled title="Cannot deactivate booklet after invoice numbers have been used"' : ''; ?>
                                                onclick="<?php echo $disable_buttons ? '' : "showDeactivateModal({$booklet['id']}, '" . htmlspecialchars($booklet['booklet_no']) . "', '{$branch_code}')"; ?>">
                                            Deactivate
                                        </button>
                                        <?php if (!$transfer_button_disabled): ?>
                                        <button type="button" class="btn-transfer" 
                                                <?php echo $disable_buttons ? 'disabled title="Cannot transfer booklet after invoice numbers have been used"' : ''; ?>
                                                onclick="<?php echo $disable_buttons ? '' : "showTransferModal({$booklet['id']}, '" . htmlspecialchars($booklet['booklet_no']) . "', '{$branch_code}')"; ?>">
                                            Transfer
                                        </button>
                                        <?php endif; ?>
                                    <?php elseif ($is_completed): ?>
                                        <?php 
                                        $is_returned = !empty($booklet['return_date']);
                                        ?>
                                        <button type="button" class="<?php echo $is_returned ? 'btn-returned' : 'btn-return'; ?>" 
                                                <?php echo $is_returned ? 'disabled' : ''; ?>
                                                onclick="<?php echo $is_returned ? '' : "showReturnModal({$booklet['id']}, '" . htmlspecialchars($booklet['booklet_no']) . "', '{$branch_code}')"; ?>">
                                            <?php echo $is_returned ? 'Returned' : 'Return'; ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn-activate" 
                                                <?php 
                                                if ($disable_buttons) {
                                                    echo 'disabled title="Cannot switch booklets after invoice numbers have been used"';
                                                }
                                                ?>
                                                onclick="<?php 
                                                if (!$disable_buttons) {
                                                    echo "showActivateModal({$booklet['id']}, '" . htmlspecialchars($booklet['booklet_no']) . "', '{$branch_code}')";
                                                }
                                                ?>">
                                            Activate
                                        </button>
                                        <?php if (!$transfer_button_disabled): ?>
                                        <button type="button" class="btn-transfer" 
                                                onclick="showTransferModal(<?php echo $booklet['id']; ?>, '<?php echo htmlspecialchars($booklet['booklet_no']); ?>', '<?php echo $branch_code; ?>')">
                                            Transfer
                                        </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div><!-- End bookletContent -->
    </div>

    <!-- Activation Modal -->
    <div id="activateModal" class="modal">
        <div class="modal-content">
            <div class="modal-icon">⚠️</div>
            <div class="modal-title">Are you sure you want to activate this booklet again?</div>
            <div class="modal-body">
                <p>Booklet: <span class="highlight" id="activateBookletNo"></span></p>
                <p style="font-size: 12px; color: #888;">All other active booklets for this branch will be automatically deactivated.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('activateModal')">No</button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="booklet_id" id="activateBookletId">
                    <input type="hidden" name="branch_code" id="activateBranchCode">
                    <button type="submit" name="activate_booklet" class="btn-modal-confirm">Yes</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Deactivation Modal -->
    <div id="deactivateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Deactivate Booklet</h3>
                <span class="close" onclick="closeModal('deactivateModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to deactivate booklet <span class="highlight" id="deactivateBookletNo"></span>?</p>
                <p><strong>Warning:</strong> This booklet will no longer be used for new transactions.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('deactivateModal')">Cancel</button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="booklet_id" id="deactivateBookletId">
                    <button type="submit" name="deactivate_booklet" class="btn-modal-confirm danger">Deactivate</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Return Modal -->
    <div id="returnModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Return Booklet</h3>
                <span class="close" onclick="closeModal('returnModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Return booklet <span class="highlight" id="returnBookletNo"></span> to branch:</p>
                <form method="POST" action="" id="returnForm">
                    <input type="hidden" name="booklet_id" id="returnBookletId">
                    <div style="margin: 20px 0;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #666;">Select Branch:</label>
                        <select name="return_branch" id="returnBranchSelect" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                            <option value="">-- Select Branch --</option>
                            <?php
                            // Show all active branches
                            $return_branches_query = $conn->query("SELECT * FROM branches WHERE status = 'Active' ORDER BY branch_name ASC");
                            if ($return_branches_query && $return_branches_query->num_rows > 0) {
                                while ($branch = $return_branches_query->fetch_assoc()) {
                                    // Highlight HEAD OFFICE if it exists
                                    $is_head_office = (stripos($branch['branch_name'], 'HEAD OFFICE') !== false);
                                    $style_attr = $is_head_office ? "style='font-weight: 600; background: #e8f5e9;'" : "";
                                    echo "<option value='" . htmlspecialchars($branch['branch_code']) . "' data-branch-code='" . htmlspecialchars($branch['branch_code']) . "' $style_attr>" . htmlspecialchars($branch['branch_name']) . " (" . htmlspecialchars($branch['branch_code']) . ")</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </form>
                <p style="margin-top: 15px;"><strong>Note:</strong> This will record the return date, your username, and the selected branch.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('returnModal')">Cancel</button>
                <button type="submit" form="returnForm" name="return_booklet" class="btn-modal-confirm danger">Return Booklet</button>
            </div>
        </div>
    </div>

    <!-- Transfer Modal -->
    <div id="transferModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Transfer Booklet</h3>
                <span class="close" onclick="closeModal('transferModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Transfer booklet <span class="highlight" id="transferBookletNo"></span> to another branch:</p>
                <form method="POST" action="" id="transferForm">
                    <input type="hidden" name="booklet_id" id="transferBookletId">
                    <input type="hidden" name="transfer_from_branch" id="transferFromBranch">
                    <div style="margin: 20px 0;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #666;">Select Target Branch:</label>
                        <select name="transfer_to_branch" id="transferBranchSelect" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                            <option value="">-- Select Branch --</option>
                            <?php
                            // Show all active branches
                            $all_branches_query = $conn->query("SELECT * FROM branches WHERE status = 'Active' ORDER BY branch_name ASC");
                            if ($all_branches_query && $all_branches_query->num_rows > 0) {
                                while ($branch = $all_branches_query->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($branch['branch_code']) . "' data-branch-code='" . htmlspecialchars($branch['branch_code']) . "'>" . htmlspecialchars($branch['branch_name']) . " (" . htmlspecialchars($branch['branch_code']) . ")</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </form>
                <p style="margin-top: 15px;"><strong>Warning:</strong> This will move the booklet to the selected branch and deactivate it. The booklet must be re-activated at the new branch to be used.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('transferModal')">Cancel</button>
                <button type="submit" form="transferForm" name="transfer_booklet" class="btn-modal-confirm" style="background: #2196F3;">Transfer Booklet</button>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn = document.querySelector('.menu-btn');

            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
            menuBtn.classList.toggle('active');
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

        function filterByBranch() {
            const branchFilter = document.getElementById('branchFilter').value;
            if (branchFilter) {
                window.location.href = 'bookletinv.php?branch=' + branchFilter;
            } else {
                window.location.href = 'bookletinv.php';
            }
        }

        function applyFilters() {
            const areaFilter = document.getElementById('areaFilter').value;
            const branchFilter = document.getElementById('branchFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            
            let url = 'bookletinv.php?';
            let params = [];
            
            if (areaFilter) {
                params.push('area=' + encodeURIComponent(areaFilter));
            }
            
            if (branchFilter) {
                params.push('branch=' + encodeURIComponent(branchFilter));
            }
            
            if (statusFilter) {
                params.push('status=' + encodeURIComponent(statusFilter));
            }
            
            if (params.length > 0) {
                url += params.join('&');
            } else {
                url = 'bookletinv.php';
            }
            
            window.location.href = url;
        }

        function hideBooklets() {
            document.getElementById('bookletContent').style.display = 'none';
            document.getElementById('noSearchMessage').style.display = 'block';
        }

        // Check if filters are applied, if so show booklets
        window.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const hasFilters = urlParams.has('area') || urlParams.has('branch') || urlParams.has('status');
            
            if (hasFilters) {
                document.getElementById('bookletContent').style.display = 'block';
                document.getElementById('noSearchMessage').style.display = 'none';
            }
        });

        function showActivateModal(bookletId, bookletNo, branchCode) {
            document.getElementById('activateBookletId').value = bookletId;
            document.getElementById('activateBookletNo').textContent = bookletNo;
            document.getElementById('activateBranchCode').value = branchCode;
            const modal = document.getElementById('activateModal');
            modal.style.display = 'flex';
            modal.classList.add('show');
        }

        function showDeactivateModal(bookletId, bookletNo, branchCode) {
            document.getElementById('deactivateBookletId').value = bookletId;
            document.getElementById('deactivateBookletNo').textContent = bookletNo;
            const modal = document.getElementById('deactivateModal');
            modal.style.display = 'flex';
            modal.classList.add('show');
        }

        function showReturnModal(bookletId, bookletNo, branchCode) {
            document.getElementById('returnBookletId').value = bookletId;
            document.getElementById('returnBookletNo').textContent = bookletNo;
            
            // Hide the current branch from the dropdown options
            const selectElement = document.getElementById('returnBranchSelect');
            const options = selectElement.options;
            
            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                if (option.getAttribute('data-branch-code') === branchCode) {
                    option.style.display = 'none';
                    option.disabled = true;
                } else {
                    option.style.display = '';
                    option.disabled = false;
                }
            }
            
            // Reset selected value
            selectElement.value = '';
            
            const modal = document.getElementById('returnModal');
            modal.style.display = 'flex';
            modal.classList.add('show');
        }

        function showTransferModal(bookletId, bookletNo, branchCode) {
            document.getElementById('transferBookletId').value = bookletId;
            document.getElementById('transferBookletNo').textContent = bookletNo;
            document.getElementById('transferFromBranch').value = branchCode;
            
            // Hide the current branch from the dropdown options
            const selectElement = document.getElementById('transferBranchSelect');
            const options = selectElement.options;
            
            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                if (option.getAttribute('data-branch-code') === branchCode) {
                    option.style.display = 'none';
                    option.disabled = true;
                } else {
                    option.style.display = '';
                    option.disabled = false;
                }
            }
            
            // Reset selected value
            selectElement.value = '';
            
            const modal = document.getElementById('transferModal');
            modal.style.display = 'flex';
            modal.classList.add('show');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
            modal.style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const activateModal = document.getElementById('activateModal');
            const deactivateModal = document.getElementById('deactivateModal');
            const returnModal = document.getElementById('returnModal');
            const transferModal = document.getElementById('transferModal');
            if (event.target == activateModal) {
                activateModal.style.display = 'none';
            }
            if (event.target == deactivateModal) {
                deactivateModal.style.display = 'none';
            }
            if (event.target == returnModal) {
                returnModal.style.display = 'none';
            }
            if (event.target == transferModal) {
                transferModal.style.display = 'none';
            }
        }

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>
