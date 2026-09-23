<?php
require_once 'session_check.php';
include 'config.php';

// Authorization Check
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';


// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_name VARCHAR(100) NOT NULL UNIQUE,
    sidebar_access TEXT
)");

// Get filter parameter early so it's available for redirects
$selected_filter = isset($_GET['position_filter']) ? $_GET['position_filter'] : '';

// Handle Form Submission
$message = "";
$messageType = "";
$editMode = false;
$editData = null;

if (isset($_GET['edit'])) {
    $editMode = true;
    $id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT * FROM positions WHERE id = '$id'");
    if ($result && $result->num_rows > 0) {
        $editData = $result->fetch_assoc();

        // Prevent Sub-admin and User from editing Superadmin positions
        if (
            (strcasecmp($system_level, 'Sub-admin') === 0 || strcasecmp($system_level, 'User') === 0) &&
            (stripos($editData['position_name'], 'superadmin') !== false ||
                stripos($editData['position_name'], 'super admin') !== false)
        ) {
            $redirect_url = "position.php?error=unauthorized";
            if (!empty($selected_filter)) {
                $redirect_url .= "&position_filter=" . urlencode($selected_filter);
            }
            header("Location: $redirect_url");
            exit();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['save_position'])) {
        $position_raw = $_POST['position'];
        $sidebar = isset($_POST['sidebar_access']) ? $conn->real_escape_string($_POST['sidebar_access']) : '';

        // Check for leading or trailing spaces in position name
        if ($position_raw !== trim($position_raw)) {
            $message = "Position name contains special characters! (Leading or trailing spaces are not allowed)";
            $messageType = "error";
        }
        // Check for special characters in position name (only allow alphanumeric and spaces)
        else if (!preg_match('/^[A-Za-z0-9 ]+$/', $position_raw)) {
            $message = "Position name contains special characters! (Only letters, numbers, and spaces are allowed)";
            $messageType = "error";
        }
        // Prevent Sub-admin and User from creating or updating Superadmin positions
        else if (
            (strcasecmp($system_level, 'Sub-admin') === 0 || strcasecmp($system_level, 'User') === 0) &&
            (stripos($position_raw, 'superadmin') !== false || stripos($position_raw, 'super admin') !== false)
        ) {
            $message = "Error: You do not have permission to manage Superadmin positions.";
            $messageType = "error";
        } else {
            $position = $conn->real_escape_string($position_raw);

            // Prevent Sub-admin and User from creating or updating Superadmin positions
            if (
                (strcasecmp($system_level, 'Sub-admin') === 0 || strcasecmp($system_level, 'User') === 0) &&
                (stripos($position, 'superadmin') !== false || stripos($position, 'super admin') !== false)
            ) {
                $message = "Error: You do not have permission to manage Superadmin positions.";
                $messageType = "error";
            }
            // Check if Sub-admin or User is trying to create/update a position that's already used by Super-Admin or Sub-admin accounts
            elseif (strcasecmp($system_level, 'Sub-admin') === 0 || strcasecmp($system_level, 'User') === 0) {
                $check_usage = $conn->query("SELECT COUNT(*) as count FROM accounts 
                                         WHERE position = '$position' 
                                         AND system_level IN ('Super-Admin', 'Sub-admin')");
                $usage_data = $check_usage->fetch_assoc();

                if ($usage_data['count'] > 0) {
                    $message = "Error: You do not have permission to manage positions used by Sub-admin accounts.";
                    $messageType = "error";
                } else {
                    // Proceed with update or insert
                    if (isset($_POST['id']) && !empty($_POST['id'])) {
                        // Update - verify not editing a position used by Super-Admin/Sub-admin
                        $id = $conn->real_escape_string($_POST['id']);

                        $check_result = $conn->query("SELECT position_name FROM positions WHERE id='$id'");
                        if ($check_result && $check_result->num_rows > 0) {
                            $check_data = $check_result->fetch_assoc();
                            $old_position = $check_data['position_name'];

                            // Check if old position is used by Super-Admin/Sub-admin
                            $check_old_usage = $conn->query("SELECT COUNT(*) as count FROM accounts 
                                                         WHERE position = '" . $conn->real_escape_string($old_position) . "' 
                                                         AND system_level IN ('Super-Admin', 'Sub-admin')");
                            $old_usage_data = $check_old_usage->fetch_assoc();

                            if ($old_usage_data['count'] > 0) {
                                $redirect_url = "position.php?error=unauthorized";
                                if (!empty($selected_filter)) {
                                    $redirect_url .= "&position_filter=" . urlencode($selected_filter);
                                }
                                header("Location: $redirect_url");
                                exit();
                            }
                        }

                        $sql = "UPDATE positions SET position_name='$position', sidebar_access='$sidebar' WHERE id='$id'";
                        if ($conn->query($sql)) {
                            $redirect_url = "position.php?updated=1";
                            if (!empty($selected_filter)) {
                                $redirect_url .= "&position_filter=" . urlencode($selected_filter);
                            }
                            header("Location: $redirect_url");
                            exit();
                        } else {
                            $message = "Error: " . $conn->error;
                            $messageType = "error";
                        }
                    } else {
                        // Insert
                        $sql = "INSERT INTO positions (position_name, sidebar_access) VALUES ('$position', '$sidebar')";
                        if ($conn->query($sql)) {
                            $redirect_url = "position.php?success=1";
                            if (!empty($selected_filter)) {
                                $redirect_url .= "&position_filter=" . urlencode($selected_filter);
                            }
                            header("Location: $redirect_url");
                            exit();
                        } else {
                            $message = "Error: " . $conn->error;
                            $messageType = "error";
                        }
                    }
                }
            } else {
                // Super-Admin can do anything
                if (isset($_POST['id']) && !empty($_POST['id'])) {
                    // Update
                    $id = $conn->real_escape_string($_POST['id']);

                    $sql = "UPDATE positions SET position_name='$position', sidebar_access='$sidebar' WHERE id='$id'";
                    if ($conn->query($sql)) {
                        $redirect_url = "position.php?updated=1";
                        if (!empty($selected_filter)) {
                            $redirect_url .= "&position_filter=" . urlencode($selected_filter);
                        }
                        header("Location: $redirect_url");
                        exit();
                    } else {
                        $message = "Error: " . $conn->error;
                        $messageType = "error";
                    }
                } else {
                    // Insert
                    $sql = "INSERT INTO positions (position_name, sidebar_access) VALUES ('$position', '$sidebar')";
                    if ($conn->query($sql)) {
                        $redirect_url = "position.php?success=1";
                        if (!empty($selected_filter)) {
                            $redirect_url .= "&position_filter=" . urlencode($selected_filter);
                        }
                        header("Location: $redirect_url");
                        exit();
                    } else {
                        $message = "Error: " . $conn->error;
                        $messageType = "error";
                    }
                }
            }
        } // End validation else block
    }
}

// Delete
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);

    // Prevent Sub-admin and User from deleting Superadmin positions
    if (strcasecmp($system_level, 'Sub-admin') === 0 || strcasecmp($system_level, 'User') === 0) {
        $check_result = $conn->query("SELECT position_name FROM positions WHERE id='$id'");
        if ($check_result && $check_result->num_rows > 0) {
            $check_data = $check_result->fetch_assoc();
            if (
                stripos($check_data['position_name'], 'superadmin') !== false ||
                stripos($check_data['position_name'], 'super admin') !== false
            ) {
                $redirect_url = "position.php?error=unauthorized";
                if (!empty($selected_filter)) {
                    $redirect_url .= "&position_filter=" . urlencode($selected_filter);
                }
                header("Location: $redirect_url");
                exit();
            }
        }
    }

    $conn->query("DELETE FROM positions WHERE id='$id'");
    $redirect_url = "position.php?deleted=1";
    if (!empty($selected_filter)) {
        $redirect_url .= "&position_filter=" . urlencode($selected_filter);
    }
    header("Location: $redirect_url");
    exit();
}

// Messages
if (isset($_GET['success'])) {
    $message = "Position added successfully!";
    $messageType = "success";
    echo "<script>if (window.history.replaceState) { window.history.replaceState(null, null, window.location.pathname); }</script>";
}
if (isset($_GET['updated'])) {
    $message = "Position updated successfully!";
    $messageType = "success";
    echo "<script>if (window.history.replaceState) { window.history.replaceState(null, null, window.location.pathname); }</script>";
}
if (isset($_GET['deleted'])) {
    $message = "Position deleted successfully!";
    $messageType = "success";
    echo "<script>if (window.history.replaceState) { window.history.replaceState(null, null, window.location.pathname); }</script>";
}
if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $message = "Error: You do not have permission to manage Superadmin positions.";
    $messageType = "error";
    echo "<script>if (window.history.replaceState) { window.history.replaceState(null, null, window.location.pathname); }</script>";
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

// Current selection for JS init (if edit mode)
$current_sidebar = ($editMode && !empty($editData['sidebar_access'])) ? explode(',', $editData['sidebar_access']) : [];

// Fetch positions based on logged-in user's system level and selected filter
// Only execute query if a filter is selected
$positions = null;
$selected_filter = isset($_GET['position_filter']) ? $_GET['position_filter'] : '';

if (!empty($selected_filter)) {
    // Build base query based on system level
    if (strcasecmp($system_level, 'Sub-admin') === 0 || strcasecmp($system_level, 'User') === 0) {
        // Sub-admin or User: exclude Superadmin positions and positions used by Super-Admin or Sub-admin accounts
        $query = "SELECT p.* FROM positions p 
                  WHERE p.position_name NOT LIKE '%superadmin%' 
                  AND p.position_name NOT LIKE '%super admin%'
                  AND p.position_name NOT IN (
                      SELECT DISTINCT position FROM accounts 
                      WHERE system_level IN ('Super-Admin', 'Sub-admin')
                  )
                  ORDER BY p.id DESC";
    } else {
        // Super-Admin: see all positions
        $query = "SELECT * FROM positions ORDER BY id DESC";
    }

    $positions = $conn->query($query);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Position</title>
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

        /* Header & Sidebar Styles (Copied from accountregistration.php for consistency) */
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

        /* Content Styles */
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

        .btn-add-position {
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

        .btn-add-position:hover {
            background: var(--color-gold-light);
        }

        /* Form Styles */
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

        .form-group {
            margin-bottom: 20px;
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
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: white;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2196F3;
        }

        .btn-set-sidebar {
            padding: 10px 24px;
            border: none;
            background: var(--color-gold);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            width: 100%;
            text-align: center;
            transition: background-color 0.2s;
        }

        .btn-set-sidebar:hover {
            background: var(--color-gold-light);
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

        .btn-save {
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

        .btn-save:hover {
            background: var(--color-gold-light);
        }

        /* Table Styles */
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

        .btn-delete {
            padding: 6px 16px;
            border: none;
            background: #d32f2f;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-left: 5px;
        }

        .btn-delete:hover {
            background: #b71c1c;
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
            font-family: Arial, sans-serif;
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
            align-items: center;
        }

        .btn-modal-nav {
            padding: 0;
            height: 36px;
            width: 100px;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }

        .btn-back {
            border: 1px solid #bbb;
            background: white;
            color: #333;
        }

        .btn-back:hover {
            background: #f5f5f5;
        }

        .btn-next {
            border: none;
            background: var(--color-gold);
            color: white;
            transition: background-color 0.2s;
        }

        .btn-next:hover {
            background: var(--color-gold-light);
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

        .btn-view-sidebar {
            padding: 6px 16px;
            border: none;
            background: #17a2b8;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-view-sidebar:hover {
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

            .content-header h2 {
                font-size: 18px;
            }
        }

        /* Medium screens - Stack action buttons at 952px and below */
        @media (max-width: 952px) {

            .btn-view-sidebar,
            .btn-edit,
            .btn-delete {
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
            <h2>Position Registration</h2>
            <button type="button" class="btn-add-position" onclick="toggleForm()">Add Position</button>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php
        endif; ?>

        <form method="POST" action="" class="form-container hidden" id="positionForm">
            <input type="hidden" name="id" id="position_id"
                value="<?php echo $editMode && isset($editData['id']) ? $editData['id'] : ''; ?>">
            <div class="form-group">
                <label>Position</label>
                <input type="text" name="position" id="position" placeholder="Enter Position"
                    value="<?php echo $editMode && isset($editData['position_name']) ? htmlspecialchars($editData['position_name']) : ''; ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Sidebar</label>
                <textarea id="sidebar_display" name="sidebar_display" placeholder="Display" readonly
                    style="height: 100px; resize: vertical;"><?php echo $editMode && isset($editData['sidebar_access']) ? htmlspecialchars($editData['sidebar_access']) : ''; ?></textarea>
                <input type="hidden" name="sidebar_access" id="sidebar_access"
                    value="<?php echo $editMode && isset($editData['sidebar_access']) ? htmlspecialchars($editData['sidebar_access']) : ''; ?>">
            </div>

            <div class="form-group">
                <button type="button" class="btn-set-sidebar" onclick="openSidebarModal()">Set Sidebar</button>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn-cancel" onclick="toggleForm()">Cancel</button>
                <button type="submit" name="save_position"
                    class="btn-save"><?php echo $editMode ? 'Update' : 'Save'; ?></button>
            </div>
        </form>

        <div class="table-container">
            <div class="table-header">
                <h3>Position List</h3>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <select id="positionFilter" onchange="filterPositions()"
                        style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer;">
                        <option value="">Select Filter</option>
                        <option value="all" <?php echo ($selected_filter === 'all') ? 'selected' : ''; ?>>View All
                        </option>
                    </select>
                </div>
            </div>
            <table id="positionTable">
                <thead>
                    <tr>
                        <th style="width: 30%;">Position</th>
                        <th style="width: 50%;">Sidebar</th>
                        <th style="width: 20%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($positions === null) {
                        // No filter selected - show message
                        echo "<tr><td colspan='3' style='text-align: center; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>
                                <div style='display: flex; flex-direction: column; align-items: center; gap: 12px;'>
                                    <svg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 24 24' fill='none' stroke='#999' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'>
                                        <circle cx='12' cy='12' r='10'></circle>
                                        <line x1='12' y1='16' x2='12' y2='12'></line>
                                        <line x1='12' y1='8' x2='12.01' y2='8'></line>
                                    </svg>
                                    <div style='color: #333; font-size: 15px; font-weight: 600;'>SELECT A FILTER TO DISPLAY THE DATA</div>
                                    <div style='color: #666; font-size: 13px;'>Please select a filter from the dropdown above to view positions.</div>
                                </div>
                              </td></tr>";
                    } elseif ($positions && $positions->num_rows > 0) {
                        $positions->data_seek(0); // Reset pointer
                        while ($row = $positions->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['position_name']) . "</td>";
                            echo "<td><button type='button' class='btn-view-sidebar' onclick='viewSidebarDetails(" . json_encode($row['sidebar_access']) . ")'>View Details</button></td>";
                            echo "<td>";

                            // Preserve position_filter when editing or deleting
                            $edit_url = '?edit=' . $row['id'];
                            $delete_url = '?delete=' . $row['id'];
                            if (!empty($selected_filter)) {
                                $edit_url .= '&position_filter=' . urlencode($selected_filter);
                                $delete_url .= '&position_filter=' . urlencode($selected_filter);
                            }

                            echo "<a href='" . $edit_url . "' class='btn-edit'>Edit</a>";
                            echo "<a href='" . $delete_url . "' class='btn-delete' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3' style='text-align: center !important; padding: 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>No positions found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sidebar Activation Modal (Copied and Adapted) -->
    <div id="sidebarModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Sidebar Deactivation
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                    <label style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="selectAllSidebar" style="width: 18px; height: 18px;"
                            onchange="toggleSidebarSelectAll()">
                        Select All
                    </label>
                </div>
                <div id="sidebarListContainer">
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
                            <?php foreach ($processItems as $index => $item):
                                $checked = in_array($item, $current_sidebar) ? 'checked' : '';
                                ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($processItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="process"
                                                style="width:16px;height:16px;" onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($processItems); ?>">Process</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox process-checkbox"
                                                value="<?php echo $item; ?>" <?php echo $checked; ?>
                                                style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Purchase Order group -->
                            <?php foreach ($purchaseOrderItems as $index => $item):
                                $checked = in_array($item, $current_sidebar) ? 'checked' : '';
                                ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($purchaseOrderItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="purchase-order"
                                                style="width:16px;height:16px;" onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($purchaseOrderItems); ?>">Purchase
                                            Order</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox purchase-order-checkbox"
                                                value="<?php echo $item; ?>" <?php echo $checked; ?>
                                                style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Reports group -->
                            <?php foreach ($reportItems as $index => $item):
                                $checked = in_array($item, $current_sidebar) ? 'checked' : '';
                                ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($reportItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="reports"
                                                style="width:16px;height:16px;" onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($reportItems); ?>">Reports</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox reports-checkbox"
                                                value="<?php echo $item; ?>" <?php echo $checked; ?>
                                                style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Approval Process group -->
                            <?php foreach ($approvalProcessItems as $index => $item):
                                $checked = in_array($item, $current_sidebar) ? 'checked' : '';
                                ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($approvalProcessItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="approval-process"
                                                style="width:16px;height:16px;" onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($approvalProcessItems); ?>">Approval
                                            Process</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox approval-process-checkbox"
                                                value="<?php echo $item; ?>" <?php echo $checked; ?>
                                                style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Receive Process group -->
                            <?php foreach ($receiveProcessItems as $index => $item):
                                $checked = in_array($item, $current_sidebar) ? 'checked' : '';
                                ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($receiveProcessItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="receive-process"
                                                style="width:16px;height:16px;" onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($receiveProcessItems); ?>">Receive
                                            Process</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox receive-process-checkbox"
                                                value="<?php echo $item; ?>" <?php echo $checked; ?>
                                                style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Void Process group -->
                            <?php foreach ($voidProcessItems as $index => $item):
                                $checked = in_array($item, $current_sidebar) ? 'checked' : '';
                                ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($voidProcessItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="void-process"
                                                style="width:16px;height:16px;" onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($voidProcessItems); ?>">Void Process
                                        </td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox void-process-checkbox"
                                                value="<?php echo $item; ?>" <?php echo $checked; ?>
                                                style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Sub-admin group - Only visible for Sub-admin -->
                            <?php if (strcasecmp($system_level, 'Sub-admin') === 0): ?>
                                <?php foreach ($subadminItems as $index => $item):
                                    $checked = in_array($item, $current_sidebar) ? 'checked' : '';
                                    ?>
                                    <tr>
                                        <?php if ($index === 0): ?>
                                            <td class="checkbox-cell" rowspan="<?php echo count($subadminItems); ?>">
                                                <input type="checkbox" class="category-select-all" data-category="subadmin"
                                                    style="width:16px;height:16px;" onchange="toggleCategorySelectAll(this)">
                                            </td>
                                            <td class="category-cell" rowspan="<?php echo count($subadminItems); ?>">Sub-admin</td>
                                        <?php endif; ?>
                                        <td class="item-cell">
                                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                                <input type="checkbox" class="sidebar-checkbox subadmin-checkbox"
                                                    value="<?php echo $item; ?>" <?php echo $checked; ?>
                                                    style="width:16px;height:16px;">
                                                <span><?php echo $item; ?></span>
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- User Registration group -->
                            <?php foreach ($userRegistrationItems as $index => $item):
                                $checked = in_array($item, $current_sidebar) ? 'checked' : '';
                                ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($userRegistrationItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="user-registration"
                                                style="width:16px;height:16px;" onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($userRegistrationItems); ?>">User
                                            Registration</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox user-registration-checkbox"
                                                value="<?php echo $item; ?>" <?php echo $checked; ?>
                                                style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Location Registration group -->
                            <?php foreach ($locationRegistrationItems as $index => $item):
                                $checked = in_array($item, $current_sidebar) ? 'checked' : '';
                                ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($locationRegistrationItems); ?>">
                                            <input type="checkbox" class="category-select-all"
                                                data-category="location-registration" style="width:16px;height:16px;"
                                                onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($locationRegistrationItems); ?>">
                                            Store Registration</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox location-registration-checkbox"
                                                value="<?php echo $item; ?>" <?php echo $checked; ?>
                                                style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Item Registration group -->
                            <?php foreach ($itemRegistrationItems as $index => $item):
                                $checked = in_array($item, $current_sidebar) ? 'checked' : '';
                                ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($itemRegistrationItems); ?>">
                                            <input type="checkbox" class="category-select-all" data-category="item-registration"
                                                style="width:16px;height:16px;" onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($itemRegistrationItems); ?>">Item
                                            Registration</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox item-registration-checkbox"
                                                value="<?php echo $item; ?>" <?php echo $checked; ?>
                                                style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Terminal Registration group -->
                            <?php foreach ($terminalRegistrationItems as $index => $item):
                                $checked = in_array($item, $current_sidebar) ? 'checked' : '';
                                ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td class="checkbox-cell" rowspan="<?php echo count($terminalRegistrationItems); ?>">
                                            <input type="checkbox" class="category-select-all"
                                                data-category="terminal-registration" style="width:16px;height:16px;"
                                                onchange="toggleCategorySelectAll(this)">
                                        </td>
                                        <td class="category-cell" rowspan="<?php echo count($terminalRegistrationItems); ?>">
                                            Terminal Registration</td>
                                    <?php endif; ?>
                                    <td class="item-cell">
                                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                            <input type="checkbox" class="sidebar-checkbox terminal-registration-checkbox"
                                                value="<?php echo $item; ?>" <?php echo $checked; ?>
                                                style="width:16px;height:16px;">
                                            <span><?php echo $item; ?></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-nav btn-back" onclick="closeSidebarModal()">Cancel</button>
                <button type="button" class="btn-modal-nav btn-next" onclick="applySidebarSelection()">Done</button>
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
            allSections.forEach(function (s) {
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

        function toggleForm() {
            const form = document.getElementById('positionForm');
            const btn = document.querySelector('.btn-add-position');

            if (form.classList.contains('hidden')) {
                form.classList.remove('hidden');
                btn.style.display = 'none';
            } else {
                // If exiting edit mode via cancel/toggle, preserve position_filter
                if (window.location.search.includes('edit')) {
                    const urlParams = new URLSearchParams(window.location.search);
                    const positionFilter = urlParams.get('position_filter');
                    let redirectUrl = 'position.php';
                    if (positionFilter) {
                        redirectUrl += '?position_filter=' + encodeURIComponent(positionFilter);
                    }
                    window.location.href = redirectUrl;
                    return;
                }
                form.classList.add('hidden');
                btn.style.display = 'block';
                form.reset();
            }
        }

        <?php if ($editMode): ?>
            // Auto open form on edit
            document.addEventListener('DOMContentLoaded', function () {
                document.getElementById('positionForm').classList.remove('hidden');
                document.querySelector('.btn-add-position').style.display = 'none';
                updateSidebarSelectAllState(); // Ensure checkboxes are synced
            });
            <?php
        endif; ?>

        // Modal Logic
        function openSidebarModal() {
            document.getElementById('sidebarModal').style.display = 'flex';

            // Sync modal checkboxes with current textarea value (in case user typed manually or cleared it)
            // But usually textarea is readonly.
            // If starting fresh add (not edit), clear checks
            if (!<?php echo $editMode ? 'true' : 'false'; ?> && document.getElementById('sidebar_access').value === '') {
                // Optional: Clear selection for new entry if needed
                // For now, checks persist in DOM unless unchecked.
                // Let's rely on DOM state or clearing it on form reset.
            }
            updateSidebarSelectAllState();
        }

        function closeSidebarModal() {
            document.getElementById('sidebarModal').style.display = 'none';
        }

        function toggleSidebarSelectAll() {
            const selectAll = document.getElementById('selectAllSidebar');
            const tableSelectAll = document.getElementById('tableSelectAll');

            // Sync both checkboxes
            if (selectAll) tableSelectAll.checked = selectAll.checked;
            if (tableSelectAll) selectAll.checked = tableSelectAll.checked;

            const allChecked = selectAll ? selectAll.checked : tableSelectAll.checked;
            const checkboxes = document.querySelectorAll('.sidebar-checkbox');
            checkboxes.forEach(cb => cb.checked = allChecked);
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
            let hasCheckboxes = checkboxes.length > 0;

            if (hasCheckboxes) {
                for (let i = 0; i < checkboxes.length; i++) {
                    if (!checkboxes[i].checked) { allChecked = false; break; }
                }
                if (selectAll) selectAll.checked = allChecked;
                if (tableSelectAll) tableSelectAll.checked = allChecked;
            }

            // Update Category Select All checkboxes
            document.querySelectorAll('.category-select-all').forEach(catCheckbox => {
                const category = catCheckbox.getAttribute('data-category');
                const categoryCheckboxes = document.querySelectorAll('.' + category + '-checkbox');
                if (categoryCheckboxes.length === 0) return;

                let categoryAll = true;
                categoryCheckboxes.forEach(cb => { if (!cb.checked) categoryAll = false; });
                catCheckbox.checked = categoryAll;
            });
        }

        function applySidebarSelection() {
            const checkboxes = document.querySelectorAll('.sidebar-checkbox');
            const selected = [];
            checkboxes.forEach(cb => {
                if (cb.checked) selected.push(cb.value);
            });
            const val = selected.join(',');
            document.getElementById('sidebar_access').value = val;
            document.getElementById('sidebar_display').value = val;
            closeSidebarModal();
        }

        // Listeners
        document.addEventListener('DOMContentLoaded', function () {
            const checkboxes = document.querySelectorAll('.sidebar-checkbox');
            checkboxes.forEach(cb => cb.addEventListener('change', updateSidebarSelectAllState));
        });

        window.onclick = function (event) {
            const modal = document.getElementById('sidebarModal');
            const viewModal = document.getElementById('viewDetailsModal');
            if (event.target == modal) modal.style.display = 'none';
            if (event.target == viewModal) viewModal.style.display = 'none';
        }

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
            const itemRegistrationItems = ['Supplier Registration', 'Brand Registration', 'Family Code Registration', 'Department Registration', 'Group Registration', 'Item Registration', 'Bank Registration'];
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

        // Filter positions function
        function filterPositions() {
            const filterValue = document.getElementById('positionFilter').value;

            // If a filter is selected, reload page with the filter parameter
            if (filterValue) {
                window.location.href = 'position.php?position_filter=' + encodeURIComponent(filterValue);
            } else {
                // If no filter, just reload the page
                window.location.href = 'position.php';
            }
        }

        // Initialize: Check if we have data loaded
        document.addEventListener('DOMContentLoaded', function () {
            const table = document.getElementById('positionTable');
            if (table) {
                const tbody = table.getElementsByTagName('tbody')[0];
                const rows = tbody.getElementsByTagName('tr');

                // Check if we're showing the "SELECT A FILTER" message
                if (rows.length > 0) {
                    const firstRow = rows[0];
                    if (firstRow && firstRow.cells[0] && firstRow.cells[0].colSpan == 3) {
                        // This is the "SELECT A FILTER" message
                        console.log('No filter selected - showing message');
                    }
                }
            }
        });
    </script>

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
                <button type="button" class="btn-modal-nav btn-back" onclick="closeViewDetailsModal()">Close</button>
            </div>
        </div>
    </div>
</body>

</html>