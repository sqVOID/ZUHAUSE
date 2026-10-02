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
    // Process
    'salesentry.php' => 'Sales Entry',
    'stocktransfer.php' => 'Stock Transfer',
    'upgradeunit.php' => 'Upgrade Unit',
    'replacementunit.php' => 'Replacement Unit',
    'returntosupplier.php' => 'Return to Supplier',
    'refund.php' => 'Refund',
    'salestrade-in.php' => 'Trade-In',
    'claimitem.php' => 'Claim Item',
    'salesentry-status.php' => 'Item Status',
    
    // Purchase Order
    'purchaseorder.php' => 'Purchase Order',
    'purchaseorder-invperbranch.php' => 'PO Invoice per Branch',
    
    // Reports
    'report.php' => 'Daily Sales Report',
    'salesreport.php' => 'Monthly Sales Report',
    'dailysalespaytype.php' => 'Payment Details Report',
    'voidsalesreport.php' => 'Void Sales Report',
    'upgradeunitreport.php' => 'Upgrade Unit Report',
    'report-replacementunit.php' => 'Replacement Unit Report',
    'report-returntosupplier.php' => 'RTS Report (Complete)',
    'report-returntosupplier-qty.php' => 'RTS Report (Quantity)',
    'rddeliveryreport.php' => 'Receive Direct Delivery',
    'stocktransferreport.php' => 'Stock Transfer Report',
    'refundreport.php' => 'Refund Report',
    'sohandunit.php' => 'Stock on Hand',
    'sohandserial.php' => 'Stock on Hand',
    'sohandaccessories.php' => 'Stock on Hand',
    'preorderreport.php' => 'Pre Order Report',
    'reporttrade-in.php' => 'Trade-In Report',
    'report-itemstatus.php' => 'Item Status Report',
    
    // Approval Process
    'transferapproval.php' => 'Transfer Approval',
    'approval-itemstatus.php' => 'Item Status Approval',
    'approval-upgradeunit.php' => 'Upgrade Unit Approval',
    'approval-replacementunit.php' => 'Replacement Unit Approval',
    'approval-returntosupplier.php' => 'Return To Supplier Approval',
    
    // Receive Process
    'purchaseorderreceive.php' => 'Receive Purchase Order',
    'receivestocktransfer.php' => 'Receive Stock Transfer',
    
    // Void Process
    'voidsales.php' => 'Void Sales',
    
    // Sub-admin
    'salesentrylate.php' => 'Late Entry',
    
    // Pre-orders
    'preorder.php' => 'Pre Order',
    'preorder2.php' => 'Pre Order 2',
    'claimpreorder.php' => 'Claim Pre Order',
    
    // Booklet
    'bookletinv.php' => 'Booklet Inventory',
    'bookletinvlive.php' => 'Cancel Inventory',
    
    // User Registration
    'accountregistration.php' => 'Account Registration',
    'useractivation.php' => 'User Activation',
    'position.php' => 'Position Registration',
    'sidebarperacc.php' => 'Sidebar Per Account',
    
    // Store Registration
    'promotereg.php' => 'Promoter Registration',
    'areareg.php' => 'Area Registration',
    'branchregistration.php' => 'Branch Registration',
    'dealerregistration.php' => 'Dealer Registration',
    
    // Item Registration
    'supplierreg.php' => 'Supplier Registration',
    'brandreg.php' => 'Brand Registration',
    'brandReg.php' => 'Brand Registration',
    'familycodereg.php' => 'Family Code Registration',
    'departmentreg.php' => 'Department Registration',
    'groupreg.php' => 'Group Registration',
    'itemreg.php' => 'Item Registration',
    'bankreg.php' => 'Bank Registration',
    
    // Terminal Registration
    'createterminal.php' => 'Terminal Issuer Registration',
    'createterminalid.php' => 'Terminal ID Registration',
    
    // Modification (Super-Admin only)
    'modification-motogam.php' => 'Modification Sales'
];

if (isset($page_map[$current_page])) {
    $restricted_feature = $page_map[$current_page];
    $hidden_items = [];
    if (isset($_SESSION['sidebar_access']) && !empty($_SESSION['sidebar_access'])) {
        $hidden_items = explode(',', $_SESSION['sidebar_access']);
    }

    // Check if the current page feature is in the hidden (restricted) list
    if (in_array($restricted_feature, $hidden_items)) {
        // If it's the Daily Sales Report page (Dashboard) and it's blocked, show simple error to avoid loop
        if ($current_page == 'report.php') {
            die("
            <!DOCTYPE html>
            <html>
            <head>
                <title>Access Denied</title>
                <style>
                    body { font-family: Arial, sans-serif; background: #f0f0f0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                    .error-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
                    h1 { color: #d32f2f; margin: 0 0 20px 0; }
                    p { color: #666; margin: 0 0 30px 0; }
                    a { display: inline-block; padding: 10px 24px; background: #1976D2; color: white; text-decoration: none; border-radius: 4px; }
                    a:hover { background: #1565C0; }
                </style>
            </head>
            <body>
                <div class='error-container'>
                    <h1>Access Denied</h1>
                    <p>You do not have permission to access this page.</p>
                    <a href='logout.php'>Logout</a>
                </div>
            </body>
            </html>");
        }

        // Otherwise redirect to Daily Sales Report page (if not blocked) or show error
        if (in_array('Daily Sales Report', $hidden_items)) {
            die("
            <!DOCTYPE html>
            <html>
            <head>
                <title>Access Denied</title>
                <style>
                    body { font-family: Arial, sans-serif; background: #f0f0f0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                    .error-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
                    h1 { color: #d32f2f; margin: 0 0 20px 0; }
                    p { color: #666; margin: 0 0 30px 0; }
                    a { display: inline-block; padding: 10px 24px; background: #1976D2; color: white; text-decoration: none; border-radius: 4px; }
                    a:hover { background: #1565C0; }
                </style>
            </head>
            <body>
                <div class='error-container'>
                    <h1>Access Denied</h1>
                    <p>You do not have permission to access this page.</p>
                    <a href='logout.php'>Logout</a>
                </div>
            </body>
            </html>");
        }
        else {
            header("Location: report.php");
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
