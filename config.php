<?php

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

$servername = "localhost";
$username = "root"; 
$password = "";
$dbname = "zuhause_management";

$conn = new mysqli($servername, $username, $password, $dbname);



if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Dynamic Sidebar Access Update based on Position
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure button access columns exist (add if not present)
$conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS revert_button_access VARCHAR(20) DEFAULT 'enabled'");
$conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS transfer_button_access VARCHAR(20) DEFAULT 'enabled'");

if (isset($_SESSION['user_id'])) {
    $acc_id = (int) $_SESSION['user_id'];
    
    // Check if sidebar_source column exists (for backward compatibility)
    $check_columns = $conn->query("SHOW COLUMNS FROM accounts LIKE 'sidebar_source'");
    $has_sidebar_columns = ($check_columns && $check_columns->num_rows > 0);
    
    if ($has_sidebar_columns) {
        // New method: Use sidebar_source column
        $acc_sql = "SELECT sidebar_source, sidebar_access, position, revert_button_access, transfer_button_access FROM accounts WHERE id = '$acc_id'";
        $acc_result = $conn->query($acc_sql);
        if ($acc_result && $acc_result->num_rows > 0) {
            $acc_row = $acc_result->fetch_assoc();
            $sidebar_source = isset($acc_row['sidebar_source']) ? $acc_row['sidebar_source'] : 'position';
            $_SESSION['sidebar_source'] = $sidebar_source;
            $_SESSION['user_position'] = $acc_row['position']; // Sync position to session
            
            // Load button access settings
            $_SESSION['revert_button_access'] = isset($acc_row['revert_button_access']) ? $acc_row['revert_button_access'] : 'enabled';
            $_SESSION['transfer_button_access'] = isset($acc_row['transfer_button_access']) ? $acc_row['transfer_button_access'] : 'enabled';
            
            if ($sidebar_source === 'account') {
                // Per Account source: use the account's specific settings (even if empty)
                $_SESSION['sidebar_access'] = isset($acc_row['sidebar_access']) ? $acc_row['sidebar_access'] : '';
            } else {
                // From Position source: load sidebar access from the positions table
                $current_position = $conn->real_escape_string($acc_row['position']);
                $perm_sql = "SELECT sidebar_access FROM positions WHERE position_name = '$current_position'";
                $perm_result = $conn->query($perm_sql);
                if ($perm_result && $perm_result->num_rows > 0) {
                    $perm_row = $perm_result->fetch_assoc();
                    $_SESSION['sidebar_access'] = $perm_row['sidebar_access'];
                } else {
                    $_SESSION['sidebar_access'] = '';
                }
            }
        }
    } else {
        // Old method: Fallback to position-based sidebar only
        $acc_sql = "SELECT position FROM accounts WHERE id = '$acc_id'";
        $acc_result = $conn->query($acc_sql);
        if ($acc_result && $acc_result->num_rows > 0) {
            $acc_row = $acc_result->fetch_assoc();
            $_SESSION['sidebar_source'] = 'position';
            $_SESSION['user_position'] = $acc_row['position'];
            
            // Load sidebar access from positions table
            $current_position = $conn->real_escape_string($acc_row['position']);
            $perm_sql = "SELECT sidebar_access FROM positions WHERE position_name = '$current_position'";
            $perm_result = $conn->query($perm_sql);
            if ($perm_result && $perm_result->num_rows > 0) {
                $perm_row = $perm_result->fetch_assoc();
                $_SESSION['sidebar_access'] = $perm_row['sidebar_access'];
            } else {
                $_SESSION['sidebar_access'] = '';
            }
        }
    }
}
?>
