<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ensure permissions are fresh from DB
require_once 'config.php';

// Check if user account is still activated AND session is still valid
$user_id = (int) $_SESSION['user_id'];
$current_session_id = session_id();

// First, check if session exists in active_sessions table
$session_check_sql = "SELECT id FROM active_sessions WHERE user_id = $user_id AND session_id = '$current_session_id'";
$session_check_result = $conn->query($session_check_sql);

if (!$session_check_result || $session_check_result->num_rows === 0) {
    // Session was forcefully terminated (account was deactivated)
    session_unset();
    session_destroy();
    header("Location: login.php?error=session_terminated");
    exit();
}

// Check account status
$status_check_sql = "SELECT status FROM accounts WHERE id = '$user_id'";
$status_result = $conn->query($status_check_sql);

if ($status_result && $status_result->num_rows > 0) {
    $status_row = $status_result->fetch_assoc();
    if ($status_row['status'] !== 'Activated') {
        // User account is no longer activated - force logout
        $conn->query("DELETE FROM active_sessions WHERE user_id = $user_id");
        session_unset();
        session_destroy();
        header("Location: login.php?error=account_deactivated");
        exit();
    }
} else {
    // User not found in database - force logout
    $conn->query("DELETE FROM active_sessions WHERE user_id = $user_id");
    session_unset();
    session_destroy();
    header("Location: login.php?error=account_not_found");
    exit();
}

// Update last activity timestamp
$conn->query("UPDATE active_sessions SET last_activity = CURRENT_TIMESTAMP WHERE user_id = $user_id AND session_id = '$current_session_id'");
// Access Control Logic
$current_page = basename($_SERVER['PHP_SELF']);

// Map pages to Sidebar Item Names
$page_map = [
    'report.php' => 'Report',
    'preorder.php' => 'Pre Order',
    'purchaseorder.php' => 'Purchase Order',
    'purchaseorder-invperbranch.php' => 'PO Invoice per Branch',
    'replacementunit.php' => 'Replacement Unit',
    'salesentry.php' => 'Sales Entry',
    'salesentrylate.php' => 'Late Entry',
    'modification-motogam.php' => 'Modification Sales',
    'voidsales.php' => 'Void Sales',
    'stocktransfer.php' => 'Stock Transfer',
    'upgradeunit.php' => 'Upgrade Unit',
    'refund.php' => 'Refund',
    'rddelivery.php' => 'Receive Direct Delivery',
    'dsentry.php' => 'Dealers Sales Entry',
    'claimitem.php' => 'Claim Item',
    'accountregistration.php' => 'Account Registration',
    'useractivation.php' => 'User Activation',
    'promotereg.php' => 'Promoter Registration',
    'areareg.php' => 'Area Registration',
    'branchregistration.php' => 'Branch Registration',
    'dealerregistration.php' => 'Dealer Registration',
    'brandreg.php' => 'Brand Registration',
    'brandReg.php' => 'Brand Registration',
    'familycodereg.php' => 'Family Code Registration',
    'departmentreg.php' => 'Department Registration',
    'groupreg.php' => 'Group Registration',
    'itemreg.php' => 'Item Registration',
    'bankreg.php' => 'Bank Registration',
    'position.php' => 'Position Registration',
    'sidebarperacc.php' => 'Sidebar Per Account'
];

if (isset($page_map[$current_page])) {
    $restricted_feature = $page_map[$current_page];
    $hidden_items = [];
    if (isset($_SESSION['sidebar_access']) && !empty($_SESSION['sidebar_access'])) {
        $hidden_items = explode(',', $_SESSION['sidebar_access']);
    }

    // Check if the current page feature is in the hidden (restricted) list
    if (in_array($restricted_feature, $hidden_items)) {
        // If it's the Report page (Dashboard) and it's blocked, show simple error to avoid loop
        if ($current_page == 'main.php') {
            die("<h1>Access Denied</h1><p>You do not have permission to access the Dashboard (Report).</p><a href='logout.php'>Logout</a>");
        }

        // Otherwise redirect to Report page (if not blocked) or show error
        if (in_array('Report', $hidden_items)) {
            die("<h1>Access Denied</h1><p>You do not have permission to access this page.</p><a href='logout.php'>Logout</a>");
        }
        else {
            header("Location: main.php");
            exit();
        }
    }

    // Explicit system_level check for Account Registration (Super-Admin and Sub-admin)
    if ($current_page === 'accountregistration.php') {
        $sys_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
        if (strcasecmp($sys_level, 'Super-Admin') !== 0 && strcasecmp($sys_level, 'Sub-admin') !== 0) {
            header("Location: main.php");
            exit();
        }
    }

    // Explicit system_level check for Late Entry (Super-Admin and Sub-admin only)
    if ($current_page === 'salesentrylate.php') {
        $sys_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
        if (strcasecmp($sys_level, 'Super-Admin') !== 0 && strcasecmp($sys_level, 'Sub-admin') !== 0) {
            header("Location: main.php");
            exit();
        }
    }
}
?>
