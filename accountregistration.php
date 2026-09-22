<?php
require_once 'session_check.php';

// Authorization Check
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

// Get filter parameter early so it's available for redirects
$selected_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Restrict only 'User' from accessing this page (Sub-admin can access but with limited permissions)
if (false) {
    header("Location: report.php");
    exit();
}

include 'config.php';

$create_table = "CREATE TABLE IF NOT EXISTS accounts (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL,
    branch VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    system_level VARCHAR(20) NOT NULL,
    status VARCHAR(20) DEFAULT 'Pending',
    sidebar_source VARCHAR(20) DEFAULT 'position',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$conn->query($create_table);

// Add sidebar_source column if it doesn't exist
$conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS sidebar_source VARCHAR(20) DEFAULT 'position'");

// Ensure sidebar_access column exists (used by sidebarperacc.php)
$conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS sidebar_access TEXT DEFAULT NULL");

// Add button access control columns
$conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS revert_button_access VARCHAR(20) DEFAULT 'enabled'");
$conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS transfer_button_access VARCHAR(20) DEFAULT 'enabled'");



// Handle form submission
$message = "";
$messageType = "";
$editMode = false;
$editData = null;

// Check if editing
if (isset($_GET['edit'])) {
    $editMode = true;
    $id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT * FROM accounts WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $editData = $result->fetch_assoc();
        
        // Prevent Sub-admin from editing Super-Admin accounts, Superadmin position accounts, or other Sub-admin accounts
        if (strcasecmp($system_level, 'Sub-admin') === 0) {
            if ($editData['system_level'] === 'Super-Admin' || 
                $editData['system_level'] === 'Sub-admin' ||
                stripos($editData['position'], 'superadmin') !== false || 
                stripos($editData['position'], 'super admin') !== false) {
                header("Location: accountregistration.php?error=unauthorized");
                exit();
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_account'])) {
    $username_raw = $_POST['username'];
    $first_name_raw = $_POST['first_name'];
    $last_name_raw = $_POST['last_name'];
    //$email = $conn->real_escape_string($_POST['email']);
    //$phone_number = $conn->real_escape_string($_POST['phone_number']);
    $position = $conn->real_escape_string($_POST['position']);
    $branch = isset($_POST['branch']) && is_array($_POST['branch']) ? implode(', ', $_POST['branch']) : '';
    $branch = $conn->real_escape_string($branch);
    $sidebar_source = isset($_POST['sidebar_source']) ? $conn->real_escape_string($_POST['sidebar_source']) : 'position';
    
    // Get button access values
    $revert_button_access = isset($_POST['revert_button_access']) ? $conn->real_escape_string($_POST['revert_button_access']) : 'enabled';
    $transfer_button_access = isset($_POST['transfer_button_access']) ? $conn->real_escape_string($_POST['transfer_button_access']) : 'enabled';
    
    // Get the requested system level from the form
    $requested_system_level = isset($_POST['system_level']) ? $conn->real_escape_string($_POST['system_level']) : 'User';
    
    // Check for leading or trailing spaces in username
    if ($username_raw !== trim($username_raw)) {
        $message = "Username contains special characters! (Leading or trailing spaces are not allowed)";
        $messageType = "error";
    }
    // Check for special characters in username (only allow alphanumeric and spaces)
    else if (!preg_match('/^[A-Za-z0-9 ]+$/', $username_raw)) {
        $message = "Username contains special characters! (Only letters, numbers, and spaces are allowed)";
        $messageType = "error";
    }
    // Check for leading or trailing spaces in first name
    else if ($first_name_raw !== trim($first_name_raw)) {
        $message = "First name contains special characters! (Leading or trailing spaces are not allowed)";
        $messageType = "error";
    }
    // Check for special characters in first name (only allow alphanumeric and spaces)
    else if (!preg_match('/^[A-Za-z0-9 ]+$/', $first_name_raw)) {
        $message = "First name contains special characters! (Only letters, numbers, and spaces are allowed)";
        $messageType = "error";
    }
    // Check for leading or trailing spaces in last name
    else if ($last_name_raw !== trim($last_name_raw)) {
        $message = "Last name contains special characters! (Leading or trailing spaces are not allowed)";
        $messageType = "error";
    }
    // Check for special characters in last name (only allow alphanumeric and spaces)
    else if (!preg_match('/^[A-Za-z0-9 ]+$/', $last_name_raw)) {
        $message = "Last name contains special characters! (Only letters, numbers, and spaces are allowed)";
        $messageType = "error";
    }
    else {
        $username = $conn->real_escape_string($username_raw);
        $first_name = $conn->real_escape_string($first_name_raw);
        $last_name = $conn->real_escape_string($last_name_raw);
    
    // Access control: Sub-admin can only create User accounts
    if (strcasecmp($system_level, 'Sub-admin') === 0) {
        // Sub-admin can only create User level accounts
        $system_level = 'User';
    } else {
        // Super-Admin can create any level
        $system_level = $requested_system_level;
    }

    // Check if updating or inserting
    if (isset($_POST['account_id']) && !empty($_POST['account_id'])) {
        // Update existing account
        $id = $conn->real_escape_string($_POST['account_id']);
        
        // If switching to 'account' source but no sidebar_access is set, initialize it
        if ($sidebar_source === 'account') {
            $check_sql = "SELECT sidebar_access FROM accounts WHERE id = $id";
            $check_result = $conn->query($check_sql);
            if ($check_result && $check_result->num_rows > 0) {
                $existing_data = $check_result->fetch_assoc();
                if (empty($existing_data['sidebar_access'])) {
                    // Initialize with empty sidebar_access (no restrictions)
                    $init_sidebar_sql = "UPDATE accounts SET sidebar_access = '' WHERE id = $id";
                    $conn->query($init_sidebar_sql);
                }
            }
        }

        // Check if password is being updated
        if (!empty($_POST['password'])) {
            $password = $conn->real_escape_string($_POST['password']);
            $sql = "UPDATE accounts SET 
                    username='$username',
                    first_name='$first_name', 
                    last_name='$last_name', 
             
                    position='$position', 
                    branch='$branch',
                    branch='$branch',
                    system_level='$system_level',
                    sidebar_source='$sidebar_source',
                    revert_button_access='$revert_button_access',
                    transfer_button_access='$transfer_button_access',
                    password='$password'  
                    WHERE id=$id";
        } else {
            $sql = "UPDATE accounts SET 
                    username='$username',
                    first_name='$first_name', 
                    last_name='$last_name', 
                  
                    position='$position', 
                    branch='$branch',
                    branch='$branch',
                    system_level='$system_level',
                    sidebar_source='$sidebar_source',
                    revert_button_access='$revert_button_access',
                    transfer_button_access='$transfer_button_access' 
                    WHERE id=$id";
        }

        if ($conn->query($sql) === TRUE) {
            // If this is the currently logged in user, update their session
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
                // Update the session with new values
                $_SESSION['user_branch'] = $branch;
                $_SESSION['user_position'] = $position;
                $_SESSION['system_level'] = $system_level;
                $_SESSION['revert_button_access'] = $revert_button_access;
                $_SESSION['transfer_button_access'] = $transfer_button_access;
                
                // Update the session with new sidebar access based on the new source
                if ($sidebar_source === 'account') {
                    // Get the account's sidebar_access
                    $session_sql = "SELECT sidebar_access FROM accounts WHERE id = $id";
                    $session_result = $conn->query($session_sql);
                    if ($session_result && $session_result->num_rows > 0) {
                        $session_data = $session_result->fetch_assoc();
                        $_SESSION['sidebar_access'] = $session_data['sidebar_access'];
                    }
                } else {
                    // Get sidebar access from position
                    $user_pos = $conn->real_escape_string($position);
                    $p_sql = "SELECT sidebar_access FROM positions WHERE position_name = '$user_pos'";
                    $p_result = $conn->query($p_sql);
                    if ($p_result && $p_result->num_rows > 0) {
                        $p_row = $p_result->fetch_assoc();
                        $_SESSION['sidebar_access'] = $p_row['sidebar_access'];
                    } else {
                        $_SESSION['sidebar_access'] = '';
                    }
                }
                
                $redirect_url = "accountregistration.php?updated=1";
                if (!empty($selected_filter)) {
                    $redirect_url .= "&status_filter=" . urlencode($selected_filter);
                }
                header("Location: $redirect_url");
                exit();
            } else {
                // If editing another user's account, they need to re-login for changes to take effect
                $redirect_url = "accountregistration.php?updated=1&relogin_required=1";
                if (!empty($selected_filter)) {
                    $redirect_url .= "&status_filter=" . urlencode($selected_filter);
                }
                header("Location: $redirect_url");
                exit();
            }
        } else {
            $message = "Error: " . $conn->error;
            $messageType = "error";
        }
    } else {
        // Insert new account
        $password = $conn->real_escape_string($_POST['password']);
        $sql = "INSERT INTO accounts (username, first_name, last_name, position, branch, password, system_level, status, sidebar_source, revert_button_access, transfer_button_access) 
                VALUES ('$username', '$first_name', '$last_name', '$position', '$branch', '$password', '$system_level', 'Pending', '$sidebar_source', '$revert_button_access', '$transfer_button_access')";

        if ($conn->query($sql) === TRUE) {
            // If creating account with 'account' source, initialize sidebar_access
            if ($sidebar_source === 'account') {
                $new_id = $conn->insert_id;
                $init_sidebar_sql = "UPDATE accounts SET sidebar_access = '' WHERE id = $new_id";
                $conn->query($init_sidebar_sql);
            }
            
            $redirect_url = "accountregistration.php?success=1";
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
    } // End validation else block
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Account registered successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Check for update message from redirect
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    if (isset($_GET['relogin_required']) && $_GET['relogin_required'] == 1) {
        $message = "Account updated successfully! Note: The user must logout and login again for the changes to take effect.";
    } else {
        $message = "Account updated successfully!";
    }
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
    
    // Prevent Sub-admin from deactivating Super-Admin accounts, Superadmin position accounts, or other Sub-admin accounts
    if (strcasecmp($system_level, 'Sub-admin') === 0) {
        $check_result = $conn->query("SELECT system_level, position FROM accounts WHERE id = $id");
        if ($check_result && $check_result->num_rows > 0) {
            $check_data = $check_result->fetch_assoc();
            if ($check_data['system_level'] === 'Super-Admin' || 
                $check_data['system_level'] === 'Sub-admin' ||
                stripos($check_data['position'], 'superadmin') !== false || 
                stripos($check_data['position'], 'super admin') !== false) {
                $redirect_url = "accountregistration.php?error=unauthorized";
                if (!empty($selected_filter)) {
                    $redirect_url .= "&status_filter=" . urlencode($selected_filter);
                }
                header("Location: $redirect_url");
                exit();
            }
        }
    }
    
    $sql = "UPDATE accounts SET status='Deactivated' WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "Account deactivated successfully!";
        $messageType = "success";
        $redirect_url = "accountregistration.php";
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

// Handle deletion
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);
    
    // Prevent Sub-admin from deleting Super-Admin accounts, Superadmin position accounts, or other Sub-admin accounts
    if (strcasecmp($system_level, 'Sub-admin') === 0) {
        $check_result = $conn->query("SELECT system_level, position FROM accounts WHERE id = $id");
        if ($check_result && $check_result->num_rows > 0) {
            $check_data = $check_result->fetch_assoc();
            if ($check_data['system_level'] === 'Super-Admin' || 
                $check_data['system_level'] === 'Sub-admin' ||
                stripos($check_data['position'], 'superadmin') !== false || 
                stripos($check_data['position'], 'super admin') !== false) {
                $redirect_url = "accountregistration.php?error=unauthorized";
                if (!empty($selected_filter)) {
                    $redirect_url .= "&status_filter=" . urlencode($selected_filter);
                }
                header("Location: $redirect_url");
                exit();
            }
        }
    }
    
    $sql = "DELETE FROM accounts WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "Account deleted successfully!";
        $messageType = "success";
        $redirect_url = "accountregistration.php";
        if (!empty($selected_filter)) {
            $redirect_url .= "?status_filter=" . urlencode($selected_filter);
        }
        header("Location: $redirect_url");
        exit();
    } else {
        $message = "Error deleting account: " . $conn->error;
        $messageType = "error";
    }
}

// Fetch all accounts based on logged-in user's system level and selected filter
// Only execute query if a filter is selected
$accounts_result = null;
$selected_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

if (!empty($selected_filter)) {
    // Build base query based on system level
    if (strcasecmp($system_level, 'Sub-admin') === 0) {
        // Sub-admin can only see User level accounts
        $query = "SELECT * FROM accounts WHERE system_level = 'User'";
    } else {
        // Super-Admin can see all accounts
        $query = "SELECT * FROM accounts WHERE 1=1";
    }
    
    // Apply status filter if not 'all'
    if ($selected_filter !== 'all') {
        $status_safe = $conn->real_escape_string($selected_filter);
        $query .= " AND status = '" . $status_safe . "'";
    }
    
    $query .= " ORDER BY id DESC";
    $accounts_result = $conn->query($query);
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
    <title>Account Registration</title>
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
            transition: background 0.2s ease;
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

        /* Hide browser's default password reveal button */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }

        input[type="password"]::-webkit-contacts-auto-fill-button,
        input[type="password"]::-webkit-credentials-auto-fill-button {
            visibility: hidden;
            pointer-events: none;
            position: absolute;
            right: 0;
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
            background: var(--color-gold);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .btn-activate:hover {
            background: var(--color-gold-light);
        }

        .btn-change-password {
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

        .btn-change-password:hover {
            background: var(--color-gold-light);
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

        @media (max-width: 1024px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Medium screens - Stack action buttons at 952px and below */
        @media (max-width: 952px) {
            .btn-view-branches,
            .btn-edit,
            .btn-deactivate,
            .btn-delete {
                display: block;
                width: 100%;
                margin: 3px 0;
                text-align: center;
                padding: 6px 12px;
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
    <style>
        /* Modal and Checkbox Styles from itemreg.php */
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
            /* Adjusted margin */
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

        .branches-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .branches-table thead {
            background: var(--color-gold-pale);
        }

        .branches-table th {
            text-align: center;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border: 1px solid #ccc;
        }

        .branches-table th:nth-child(2) {
            text-align: left;
        }

        .branches-table td {
            padding: 8px 12px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            vertical-align: middle;
        }

        .branches-table tbody tr:hover {
            background: #fdf8f3;
        }

        .branches-table .area-cell {
            font-weight: 600;
            background-color: #f9f9f9;
            text-align: left;
        }

        .branches-table .checkbox-cell {
            text-align: center;
            width: 60px;
            vertical-align: middle;
        }

        .branches-table .checkbox-cell input[type="checkbox"] {
            margin: 0 auto;
            display: block;
        }

        .branches-table .branch-cell {
            padding-left: 20px;
        }
        }

        .branch-item-label:hover {
            background-color: #f5f5f5;
        }

        .btn-internal-apply {
            background: var(--color-gold);
            color: white;
            border: none;
            width: 100px;
            /* Adjusted width */
            height: 38px;
            /* Match input height roughly */
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-internal-apply:hover {
            background: var(--color-gold-light);
        }

        /* Radio button styling */
        .radio-group {
            display: flex;
            gap: 20px;
            align-items: center;
            padding-top: 10px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: normal;
        }

        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .help-text {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
            display: block;
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
            <h2>Account Registration</h2>
            <button type="button" class="btn-add-user" onclick="toggleForm()">Add Account Registration</button>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php
        endif; ?>

        <form method="POST" action="" class="form-container hidden" id="accountForm">
            <input type="hidden" name="account_id" id="account_id"
                value="<?php echo $editMode && $editData ? $editData['id'] : ''; ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="username" placeholder="Enter username"
                        value="<?php echo $editMode && $editData ? htmlspecialchars($editData['username']) : ''; ?>"
                        required>
                </div>
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" id="first_name" placeholder="Enter first name"
                        value="<?php echo $editMode && $editData ? htmlspecialchars($editData['first_name']) : ''; ?>"
                        required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" id="last_name" placeholder="Enter last name"
                        value="<?php echo $editMode && $editData ? htmlspecialchars($editData['last_name']) : ''; ?>"
                        required>
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <select name="position" id="position" required>
                        <option value="">Select position</option>
                        <?php
                        // Fetch positions from the positions table
                        if (strcasecmp($system_level, 'Sub-admin') === 0) {
                            // Sub-admin: exclude Superadmin positions and positions used by Super-Admin or Sub-admin accounts
                            $positions_query = $conn->query("SELECT p.* FROM positions p 
                                                             WHERE p.position_name NOT LIKE '%superadmin%' 
                                                             AND p.position_name NOT LIKE '%super admin%'
                                                             AND p.position_name NOT IN (
                                                                 SELECT DISTINCT position FROM accounts 
                                                                 WHERE system_level IN ('Super-Admin', 'Sub-admin')
                                                             )
                                                             ORDER BY p.position_name ASC");
                        } else {
                            // Super-Admin: see all positions
                            $positions_query = $conn->query("SELECT * FROM positions ORDER BY position_name ASC");
                        }
                        
                        if ($positions_query && $positions_query->num_rows > 0) {
                            while ($position_row = $positions_query->fetch_assoc()) {
                                $selected = ($editMode && $editData && $editData['position'] == $position_row['position_name']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($position_row['position_name']) . "' " . $selected . ">" . htmlspecialchars($position_row['position_name']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

            </div>


            <div class="form-row">
                <?php
                // System Level field - Different options based on user level
// Get the current logged-in user's system level
                $current_user_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
                ?>
                <div class="form-group">
                    <label>System Level</label>
                    <select name="system_level" id="system_level" required>
                        <option value="">Select System Level</option>
                        <?php if (strcasecmp($current_user_level, 'Super-Admin') === 0): ?>
                            <option value="Super-Admin" <?php echo ($editMode && $editData && $editData['system_level'] == 'Super-Admin') ? 'selected' : ''; ?>>Super-Admin</option>
                            <option value="Sub-admin" <?php echo ($editMode && $editData && $editData['system_level'] == 'Sub-admin') ? 'selected' : ''; ?>>Sub-admin</option>
                        <?php endif; ?>
                        <option value="User" <?php echo ($editMode && $editData && $editData['system_level'] == 'User') ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>
                <?php ?>
                <div class="form-group">
                    <label>Branch</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="selectedBranchesDisplay" readonly placeholder="Select Branch"
                            onclick="openBranchesModal()"
                            value="<?php echo ($editMode && !empty($editData['branch'])) ? htmlspecialchars($editData['branch']) : ''; ?>"
                            style="padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; color: #333; background: white; cursor: pointer; flex-grow: 1;">
                        <button type="button" class="btn-internal-apply" onclick="openBranchesModal()">Select</button>
                    </div>
                </div>
            </div>

            <div class="form-row" id="passwordFields" style="display: <?php echo $editMode ? 'none' : 'grid'; ?>;">
                <div class="form-group">
                    <label>Password <?php echo $editMode ? '' : ''; ?></label>
                    <div style="position: relative; width: 100%;">
                        <input type="password" name="password" id="password" placeholder="Enter password" <?php echo $editMode ? '' : 'required'; ?> style="width: 100%; padding-right: 40px;">
                        <span onclick="togglePasswordVisibility('password')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666;">
                            <svg id="password-eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <div style="position: relative; width: 100%;">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password"
                            <?php echo $editMode ? '' : 'required'; ?> style="width: 100%; padding-right: 40px;">
                        <span onclick="togglePasswordVisibility('confirm_password')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666;">
                            <svg id="confirm_password-eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </span>
                    </div>
                </div>
            </div>

            <!-- GLOBALLY HIDDEN
            <div class="form-row">
                <div class="form-group">
                    <label>Revert Button (Cancel Invoice) Access</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="revert_button_access" value="enabled" required
                                <?php echo ($editMode && isset($editData['revert_button_access']) && $editData['revert_button_access'] == 'enabled') ? 'checked' : ''; ?>>
                            <span>Enabled</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="revert_button_access" value="disabled" required
                                <?php echo ($editMode && isset($editData['revert_button_access']) && $editData['revert_button_access'] == 'disabled') ? 'checked' : ''; ?>>
                            <span>Disabled</span>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Transfer Button (Booklet) Access</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="transfer_button_access" value="enabled" required
                                <?php echo ($editMode && isset($editData['transfer_button_access']) && $editData['transfer_button_access'] == 'enabled') ? 'checked' : ''; ?>>
                            <span>Enabled</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="transfer_button_access" value="disabled" required
                                <?php echo ($editMode && isset($editData['transfer_button_access']) && $editData['transfer_button_access'] == 'disabled') ? 'checked' : ''; ?>>
                            <span>Disabled</span>
                        </label>
                    </div>
                </div>
            </div>
            END GLOBALLY HIDDEN -->

            <div class="form-row">
                <div class="form-group">
                    <label>Sidebar Source</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="sidebar_source" value="position" required
                                <?php echo ($editMode && isset($editData['sidebar_source']) && $editData['sidebar_source'] == 'position') ? 'checked' : ''; ?>>
                            <span>From Position</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="sidebar_source" value="account" required
                                <?php echo ($editMode && isset($editData['sidebar_source']) && $editData['sidebar_source'] == 'account') ? 'checked' : ''; ?>>
                            <span>Per Account</span>
                        </label>
                    </div>
                  <!--  <small class="help-text">
                        <strong>From Position:</strong> Uses sidebar settings from their position (configured in Position Registration)<br>
                        <strong>Per Account:</strong> Uses custom settings for this specific account (configured in Sidebar Per Account)<br>
                        <em>Note: When switching to "Per Account", you can configure the sidebar in Sidebar Per Account page</em>
                    </small>-->
                </div>
                <div class="form-group">
                    <!-- Empty space for grid alignment -->
                </div>
            </div>



            <div class="form-actions" style="justify-content: flex-end;">
                <div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-cancel" onclick="toggleForm()">Cancel</button>
                    <?php if ($editMode): ?>
                        <button type="button" class="btn-change-password" onclick="togglePasswordFields()">Change
                            Password</button>
                        <?php
                    endif; ?>
                    <button type="submit" name="register_account"
                        class="btn-activate"><?php echo $editMode ? 'Update' : 'Register'; ?></button>
                </div>
            </div>

            <!-- Branches Modal -->
            <div id="branchesModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        Select Branches
                    </div>
                    <div class="modal-body">
                        <div style="margin-bottom: 15px;">
                            <input type="text" id="branchSearchInput" placeholder="Search branches..."
                                onkeyup="filterBranches()"
                                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
                        </div>
                        <div style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <label
                                style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" id="selectAllBranchesModal" style="width: 18px; height: 18px;"
                                    onchange="toggleBranchModalSelectAll()">
                                Select All
                            </label>
                        </div>
                        <div id="branchListContainer">
                            <?php
                            if ($branches_result && $branches_result->num_rows > 0) {
                                $branches_result->data_seek(0);
                                $saved_branches = ($editMode && !empty($editData['branch'])) ? explode(', ', $editData['branch']) : [];

                                $branches_by_area = [];

                                while ($row = $branches_result->fetch_assoc()) {
                                    $area = !empty($row['area']) ? $row['area'] : 'Uncategorized';
                                    $branches_by_area[$area][] = $row;
                                }
                                ksort($branches_by_area);

                                echo '<table class="branches-table">';
                                echo '<thead>';
                                echo '<tr>';
                                echo '<th style="width: 60px; text-align: center;"></th>';
                                echo '<th>Area</th>';
                                echo '<th>Branch</th>';
                                echo '</tr>';
                                echo '</thead>';
                                echo '<tbody>';

                                foreach ($branches_by_area as $area => $area_branches) {
                                    $area_display = htmlspecialchars(ucwords(str_replace('_', ' ', $area)));
                                    $branch_count = count($area_branches);
                                    
                                    foreach ($area_branches as $index => $row) {
                                        echo '<tr class="branch-row" data-area="' . htmlspecialchars($area) . '">';
                                        
                                        // First row of each area: show area checkbox and name with rowspan
                                        if ($index === 0) {
                                            echo '<td class="checkbox-cell" rowspan="' . $branch_count . '">';
                                            echo '<input type="checkbox" class="area-select-all" data-area="' . htmlspecialchars($area) . '" style="width:16px;height:16px;" onchange="toggleAreaSelectAll(this)">';
                                            echo '</td>';
                                            echo '<td class="area-cell" rowspan="' . $branch_count . '">' . $area_display . '</td>';
                                        }
                                        
                                        // Branch cell
                                        $checked = in_array($row['branch_name'], $saved_branches) ? 'checked' : '';
                                        $display_name = htmlspecialchars($row['branch_name']);
                                        if (!empty($row['branch_code'])) {
                                            $display_name .= " - " . htmlspecialchars($row['branch_code']);
                                        }
                                        
                                        echo '<td class="branch-cell">';
                                        echo '<label style="display:flex; align-items:center; gap:10px; cursor:pointer;">';
                                        echo '<input type="checkbox" name="branch[]" class="branch-checkbox ' . htmlspecialchars($area) . '-checkbox" value="' . htmlspecialchars($row['branch_name']) . '" ' . $checked . ' style="width: 16px; height: 16px;">';
                                        echo '<span>' . $display_name . '</span>';
                                        echo '</label>';
                                        echo '</td>';
                                        
                                        echo '</tr>';
                                    }
                                }

                                echo '</tbody>';
                                echo '</table>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modal-nav btn-back"
                            onclick="closeBranchesModal()">Cancel</button>
                        <button type="button" class="btn-modal-nav btn-next"
                            onclick="applyBranchesSelection()">Done</button>
                    </div>
                </div>
            </div>
        </form>



        <!-- View Branches Modal -->
        <div id="viewBranchesModal" class="modal">
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    Registered Branches
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <ul id="viewBranchesList" style="list-style-type: none; padding: 0;">
                        <!-- Branches will be populated here -->
                    </ul>
                </div>
                <div class="modal-footer" style="justify-content: center;">
                    <button type="button" class="btn-modal-nav btn-back"
                        onclick="closeViewBranchesModal()">Close</button>
                </div>
            </div>
        </div>


        <div class="table-container">
            <div class="table-header">
                <h3>Registered Accounts</h3>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <select id="statusFilter" onchange="filterByStatus()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer;">
                        <option value="">Select Status</option>
                        <option value="all" <?php echo ($selected_filter === 'all') ? 'selected' : ''; ?>>All Status</option>
                        <option value="Activated" <?php echo ($selected_filter === 'Activated') ? 'selected' : ''; ?>>Activated</option>
                        <option value="Deactivated" <?php echo ($selected_filter === 'Deactivated') ? 'selected' : ''; ?>>Deactivated</option>
                    </select>
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search accounts..." onkeyup="searchTable()">
                        <svg viewBox="0 0 24 24">
                            <path
                                d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                        </svg>
                    </div>
                </div>
            </div>
            <table id="accountTable">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <!-- <th>Email</th>
                        <th>Phone Number</th> -->
                        <th>Position</th>
                        <th>Branch</th>
                        <th>System Level</th>
                        <th>Sidebar Source</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($accounts_result === null) {
                        // No filter selected - show message
                        echo "<tr><td colspan='8' style='text-align: center; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>
                                <div style='display: flex; flex-direction: column; align-items: center; gap: 12px;'>
                                    <svg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 24 24' fill='none' stroke='#999' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'>
                                        <circle cx='12' cy='12' r='10'></circle>
                                        <line x1='12' y1='16' x2='12' y2='12'></line>
                                        <line x1='12' y1='8' x2='12.01' y2='8'></line>
                                    </svg>
                                    <div style='color: #333; font-size: 15px; font-weight: 600;'>SELECT A FILTER TO DISPLAY THE DATA</div>
                                    <div style='color: #666; font-size: 13px;'>Please select a status filter from the dropdown above to view accounts.</div>
                                </div>
                              </td></tr>";
                    } elseif ($accounts_result && $accounts_result->num_rows > 0) {
                        while ($row = $accounts_result->fetch_assoc()) {
                            $username = isset($row['username']) ? htmlspecialchars($row['username']) : 'N/A';
                            $fullName = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                            $status = htmlspecialchars($row['status']);
                            $statusClass = strtolower($status);
                            $systemLevel = isset($row['system_level']) ? htmlspecialchars($row['system_level']) : 'User';
                            $sidebarSource = isset($row['sidebar_source']) && $row['sidebar_source'] == 'account' ? 'Per Account' : 'From Position';
                            
                            // For Per Account, show configuration status
                            if ($row['sidebar_source'] == 'account') {
                                $sidebarSource = 'Per Account';
                            }
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
                            echo "<td>" . $sidebarSource . "</td>";
                            echo "<td><span class='status-badge " . $statusClass . "'>" . $status . "</span></td>";
                            echo "<td>";
                            // Preserve status_filter when editing or deleting
                            $edit_url = '?edit=' . $row['id'];
                            $delete_url = '?delete=' . $row['id'];
                            if (!empty($selected_filter)) {
                                $edit_url .= '&status_filter=' . urlencode($selected_filter);
                                $delete_url .= '&status_filter=' . urlencode($selected_filter);
                            }
                            echo "<a href='" . $edit_url . "' class='btn-edit' onclick='return editAccount(" . $row['id'] . ")'>Edit</a>";
                            // echo "<a href='?deactivate=" . $row['id'] . "' class='btn-deactivate' onclick='return confirm(\"Are you sure you want to deactivate this account?\")'>Deactivate</a>";
                            echo "<a href='" . $delete_url . "' class='btn-delete' onclick='return confirm(\"Are you sure you want to delete this account? This action cannot be undone.\")'>Delete</a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' style='text-align: center !important; padding: 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>No accounts found for the selected filter</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
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

        function toggleForm() {
            const form = document.getElementById('accountForm');
            const button = document.querySelector('.btn-add-user');

            if (form.classList.contains('hidden')) {
                form.classList.remove('hidden');
                button.style.display = 'none';
            } else {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('edit')) {
                    // Preserve status_filter when canceling edit
                    let redirectUrl = 'accountregistration.php';
                    const statusFilter = urlParams.get('status_filter');
                    if (statusFilter) {
                        redirectUrl += '?status_filter=' + encodeURIComponent(statusFilter);
                    }
                    window.location.href = redirectUrl;
                    return;
                }

                form.classList.add('hidden');
                button.style.display = 'block';
                form.reset();
                document.getElementById('account_id').value = '';
                document.querySelector('.btn-activate').textContent = 'Activate';
            }
        }

        function editAccount(id) {
            const form = document.getElementById('accountForm');
            const button = document.querySelector('.btn-add-user');
            form.classList.remove('hidden');
            button.style.display = 'none';
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return true;
        }

        <?php if ($editMode && $editData): ?>
            window.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('accountForm');
                const button = document.querySelector('.btn-add-user');
                form.classList.remove('hidden');
                button.style.display = 'none';
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            <?php
        endif; ?>

        function filterByStatus() {
            const statusFilter = document.getElementById('statusFilter').value;
            
            // If a filter is selected, reload page with the filter parameter
            if (statusFilter) {
                window.location.href = 'accountregistration.php?status_filter=' + encodeURIComponent(statusFilter);
            } else {
                // If no filter, just reload the page
                window.location.href = 'accountregistration.php';
            }
        }

        function searchTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const table = document.getElementById('accountTable');
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
            const table = document.getElementById('accountTable');
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

        // Password validation
        document.getElementById('accountForm').addEventListener('submit', function (e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password || confirmPassword) {
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
            }
        });

        function togglePasswordFields() {
            const passwordFields = document.getElementById('passwordFields');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            if (passwordFields.style.display === 'none' || passwordFields.style.display === '') {
                passwordFields.style.display = 'grid';
                passwordInput.required = true;
                confirmPasswordInput.required = true;
            } else {
                passwordFields.style.display = 'none';
                passwordInput.required = false;
                confirmPasswordInput.required = false;
                passwordInput.value = '';
                confirmPasswordInput.value = '';
            }
        }

        function togglePasswordVisibility(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const eyeIcon = document.getElementById(fieldId + '-eye-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                // Change to eye-off icon
                eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                passwordField.type = 'password';
                // Change to eye icon
                eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        }

        // --- Branch Modal Functions ---

        function openBranchesModal() {
            document.getElementById('branchesModal').style.display = 'flex';
            updateSelectAllState();
        }

        function closeBranchesModal() {
            document.getElementById('branchesModal').style.display = 'none';
        }

        function toggleBranchModalSelectAll() {
            const selectAll = document.getElementById('selectAllBranchesModal');
            const tableSelectAll = document.getElementById('tableSelectAllBranches');
            
            // Sync both checkboxes
            if (selectAll) tableSelectAll.checked = selectAll.checked;
            if (tableSelectAll) selectAll.checked = tableSelectAll.checked;
            
            const allChecked = selectAll ? selectAll.checked : tableSelectAll.checked;
            const checkboxes = document.querySelectorAll('.branch-checkbox');

            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (!row || row.style.display !== 'none') {
                    cb.checked = allChecked;
                }
            });
            updateSelectAllState();
        }

        function updateSelectAllState() {
            const checkboxes = document.querySelectorAll('.branch-checkbox');
            const selectAll = document.getElementById('selectAllBranchesModal');
            const tableSelectAll = document.getElementById('tableSelectAllBranches');

            if (checkboxes.length === 0) {
                if (selectAll) selectAll.checked = false;
                if (tableSelectAll) tableSelectAll.checked = false;
            } else {
                let allChecked = true;
                for (let i = 0; i < checkboxes.length; i++) {
                    const row = checkboxes[i].closest('tr');
                    if (!row || row.style.display !== 'none') {
                        if (!checkboxes[i].checked) {
                            allChecked = false;
                            break;
                        }
                    }
                }
                if (selectAll) selectAll.checked = allChecked;
                if (tableSelectAll) tableSelectAll.checked = allChecked;
            }

            // Update Area Select All Checkboxes
            document.querySelectorAll('.area-select-all').forEach(areaCheckbox => {
                const area = areaCheckbox.getAttribute('data-area');
                const areaCheckboxes = document.querySelectorAll('.' + area + '-checkbox');
                
                if (areaCheckboxes.length > 0) {
                    let areaAllChecked = true;
                    areaCheckboxes.forEach(cb => {
                        const row = cb.closest('tr');
                        if (!row || row.style.display !== 'none') {
                            if (!cb.checked) {
                                areaAllChecked = false;
                            }
                        }
                    });
                    areaCheckbox.checked = areaAllChecked;
                }
            });
        }

        function applyBranchesSelection() {
            const checkboxes = document.querySelectorAll('.branch-checkbox');
            const selected = [];

            checkboxes.forEach(cb => {
                if (cb.checked) {
                    selected.push(cb.value);
                }
            });

            const displayInput = document.getElementById('selectedBranchesDisplay');
            if (selected.length === 0) {
                displayInput.value = '';
                displayInput.placeholder = 'No branches selected';
            } else if (selected.length === checkboxes.length) {
                displayInput.value = 'All Branches';
            } else {
                displayInput.value = selected.join(', ');
            }
            closeBranchesModal();
        }

        function toggleAreaSelectAll(checkbox) {
            const area = checkbox.getAttribute('data-area');
            const areaCheckboxes = document.querySelectorAll('.' + area + '-checkbox');

            areaCheckboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (!row || row.style.display !== 'none') {
                    cb.checked = checkbox.checked;
                }
            });
            updateSelectAllState();
        }

        function filterBranches() {
            const input = document.getElementById('branchSearchInput');
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll('.branch-row');

            rows.forEach(row => {
                const branchCell = row.querySelector('.branch-cell span');
                if (branchCell) {
                    const txtValue = branchCell.textContent || branchCell.innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                    }
                }
            });
            updateSelectAllState();
        }

        // Add event listeners for checkboxes
        document.addEventListener('DOMContentLoaded', function () {
            const checkboxes = document.querySelectorAll('.branch-checkbox');
            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateSelectAllState);
            });
            updateSelectAllState();
        });

        // Close modal if clicked outside
        window.onclick = function (event) {
            const branchesModal = document.getElementById('branchesModal');
            if (event.target == branchesModal) {
                branchesModal.style.display = "none";
            }
        }

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

                // Build HTML
                for (const [area, areaBranches] of Object.entries(grouped)) {
                    const li = document.createElement('li');
                    li.innerHTML = `<strong>${area}</strong>`;
                    const subUl = document.createElement('ul');
                    subUl.style.paddingLeft = '15px';
                    subUl.style.marginTop = '5px';
                    subUl.style.marginBottom = '10px';

                    areaBranches.forEach(b => {
                        const bLi = document.createElement('li');
                        bLi.textContent = b;
                        bLi.style.fontSize = '13px';
                        bLi.style.marginBottom = '3px';
                        subUl.appendChild(bLi);
                    });
                    li.appendChild(subUl);
                    list.appendChild(li);
                }
            } else {
                list.innerHTML = '<li>No branches assigned</li>';
            }
            modal.style.display = 'flex';
        }

        function closeViewBranchesModal() {
            document.getElementById('viewBranchesModal').style.display = 'none';
        }



        // Universal window click handler for all modals
        window.onclick = function (event) {
            const branchesModal = document.getElementById('branchesModal');
            const viewModal = document.getElementById('viewBranchesModal');

            if (event.target == branchesModal) branchesModal.style.display = "none";
            if (event.target == viewModal) viewModal.style.display = "none";
        }
    </script>
</body>

</html>
<?php
$conn->close();
?>