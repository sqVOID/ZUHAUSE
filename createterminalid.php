<?php
require_once 'session_check.php';

// Authorization Check
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

// Restrict 'User' from accessing this page
if (false) {
    header("Location: report.php");
    exit();
}

include 'config.php';

// Get filter parameter early so it can be preserved in all redirects
$selected_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$create_table = "CREATE TABLE IF NOT EXISTS `terminal_ids` (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    terminal_id VARCHAR(255) NOT NULL UNIQUE,
    terminal_issuer VARCHAR(255) NOT NULL,
    branches TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$conn->query($create_table);

// Handle form submission
$message = "";
$messageType = "";
$editMode = false;
$editData = null;

// Check if editing
if (isset($_GET['edit'])) {
    $editMode = true;
    $id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT * FROM `terminal_ids` WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $editData = $result->fetch_assoc();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_terminal'])) {
    $terminal_id_raw = $_POST['terminal_id'];
    // Terminal Issuer: single selection from dropdown
    $terminal_issuer_raw = isset($_POST['terminal_issuer']) ? trim($_POST['terminal_issuer']) : '';
    $terminal_issuer = $conn->real_escape_string($terminal_issuer_raw);
    // Branches: use hidden input value sent as comma-separated string
    $branches_raw = isset($_POST['branches_hidden']) ? trim($_POST['branches_hidden']) : '';
    $branches = $conn->real_escape_string($branches_raw);

    // Check for leading or trailing spaces in terminal ID
    if ($terminal_id_raw !== trim($terminal_id_raw)) {
        $message = "Terminal ID contains special characters! (Leading or trailing spaces are not allowed)";
        $messageType = "error";
    }
    // Check for special characters in terminal ID (only allow alphanumeric and spaces)
    else if (!preg_match('/^[A-Za-z0-9 ]+$/', $terminal_id_raw)) {
        $message = "Terminal ID contains special characters! (Only letters, numbers, and spaces are allowed)";
        $messageType = "error";
    }
    else {
        $terminal_id = strtoupper($conn->real_escape_string($terminal_id_raw));

    // Check if updating or inserting
    if (isset($_POST['terminal_record_id']) && !empty($_POST['terminal_record_id'])) {
        // Update existing terminal
        $id = $conn->real_escape_string($_POST['terminal_record_id']);
        $sql = "UPDATE `terminal_ids` SET terminal_id='$terminal_id', terminal_issuer='$terminal_issuer', branches='$branches' WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            $redirect_url = "createterminalid.php?updated=1";
            if (!empty($selected_filter)) {
                $redirect_url .= "&status_filter=" . urlencode($selected_filter);
            }
            header("Location: $redirect_url");
            exit();
        } else {
            $message = "Error: " . $conn->error;
            $messageType = "error";
        }
    } else {
        // Insert new terminal
        $sql = "INSERT INTO `terminal_ids` (terminal_id, terminal_issuer, branches, status) VALUES ('$terminal_id', '$terminal_issuer', '$branches', 'Active')";

        if ($conn->query($sql) === TRUE) {
            $redirect_url = "createterminalid.php?success=1";
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
$toast_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $toast_message = "Terminal ID registered successfully!";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Check for update message from redirect
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $toast_message = "Terminal ID updated successfully!";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Handle status update
if (isset($_GET['status_update'])) {
    $id = $conn->real_escape_string($_GET['id']);
    $status = $conn->real_escape_string($_GET['status']);

    $new_status = ($status == 'true') ? 'Deactivated' : 'Active';

    $sql = "UPDATE `terminal_ids` SET status = '$new_status' WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $redirect_url = "createterminalid.php";
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
    $sql = "DELETE FROM `terminal_ids` WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "Terminal ID deleted successfully!";
        $messageType = "success";
        $redirect_url = "createterminalid.php";
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

// Fetch all terminal IDs based on selected filter
// Only execute query if a filter is selected
$terminals_result = null;

if (!empty($selected_filter)) {
    $query = "SELECT * FROM `terminal_ids`";
    
    // Apply status filter if not 'all'
    if ($selected_filter !== 'all') {
        $status_safe = $conn->real_escape_string($selected_filter);
        $query .= " WHERE status = '" . $status_safe . "'";
    }
    
    $query .= " ORDER BY id DESC";
    $terminals_result = $conn->query($query);
}

// Fetch all terminal issuers for dropdown
$issuers_result = $conn->query("SELECT * FROM `terminal_issuers` WHERE status='Active' ORDER BY bank_name");

// Fetch all branches for modal
$branches_result = $conn->query("SELECT * FROM branches ORDER BY branch_name");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
        <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Terminal ID Registration</title>
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

        .btn-add-terminal {
            padding: 10px 24px;
            border: none;
            background: var(--color-gold);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-add-terminal:hover {
            background: var(--color-gold-light);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(176, 138, 82, 0.3);
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

        .btn-internal-apply {
            padding: 10px 20px;
            border: none;
            background: var(--color-gold);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            transition: all 0.3s ease;
        }

        .btn-internal-apply:hover {
            background: var(--color-gold-light);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(176, 138, 82, 0.3);
        }

        .form-actions {
            display: flex;
            justify-content: flex-start;
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

        .btn-save {
            padding: 10px 24px;
            border: none;
            background: var(--color-gold);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            background: var(--color-gold-light);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(176, 138, 82, 0.3);
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

        .btn-delete {
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

        .btn-delete:hover {
            background: #b71c1c;
        }

        .btn-view {
            padding: 6px 16px;
            border: none;
            background: #757575;
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
            background: #616161;
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

        .btn-toggle-status.activate {
            background: #2e7d32;
            color: white;
        }

        .btn-toggle-status.activate:hover {
            background: #1b5e20;
        }

        .btn-toggle-status.deactivate {
            background: #c62828;
            color: white;
        }

        .btn-toggle-status.deactivate:hover {
            background: #b71c1c;
        }

        /* Multi-select dropdown for Terminal Issuer */
        .multi-select-wrapper {
            position: relative;
        }

        .multi-select-trigger {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: white;
            cursor: pointer;
            user-select: none;
            min-height: 42px;
        }

        .multi-select-trigger:hover {
            border-color: #2196F3;
        }

        .multi-select-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 500;
            max-height: 260px;
            overflow-y: auto;
        }

        .multi-select-dropdown.open {
            display: block;
        }

        .issuer-option-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            cursor: pointer;
            font-size: 13px;
            color: #333;
        }

        .issuer-option-label:hover {
            background: #f5f5f5;
        }

        .issuer-option-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            flex-shrink: 0;
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
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: 1px solid #888;
            width: 90%;
            max-width: 1000px;
            border-radius: 8px;
        }

        .modal-header {
            font-size: 18px;
            font-weight: 600;
            padding: 20px 30px;
            border-bottom: 1px solid #eee;
            color: #333;
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
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-modal-nav {
            padding: 10px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-back {
            background: white;
            color: #666;
            border: 1px solid #ddd;
        }

        .btn-back:hover {
            background: #f5f5f5;
        }

        .btn-next {
            background: var(--color-gold);
            color: white;
            transition: all 0.3s ease;
        }

        .btn-next:hover {
            background: var(--color-gold-light);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(176, 138, 82, 0.3);
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
            text-align: center;
        }

        .branches-table tbody tr:hover {
            background: #fdf8f3;
        }

        .branches-table .area-cell {
            font-weight: 600;
            background-color: #f9f9f9;
            text-align: left !important;
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
            text-align: left;
        }

        @media (max-width: 1024px) {
            .form-row {
                grid-template-columns: 1fr;
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
                min-width: 800px;
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
            .btn-save {
                width: 100%;
            }

            th,
            td {
                padding: 8px;
                font-size: 12px;
            }
        }

        /* Toast notification */
        .toast-notification {
            position: fixed;
            top: 80px;
            right: 30px;
            background: var(--color-gold);
            color: white;
            padding: 14px 24px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            opacity: 1;
            transition: opacity 0.6s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toast-notification.hide {
            opacity: 0;
            pointer-events: none;
        }
    </style>
</head>

<body>
    <?php if (!empty($toast_message)): ?>
        <div class="toast-notification" id="toastMsg">
            <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:white;flex-shrink:0;">
                <path
                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
            </svg>
            <?php echo htmlspecialchars($toast_message); ?>
        </div>
        <script>
            setTimeout(function () {
                var t = document.getElementById('toastMsg');
                if (t) t.classList.add('hide');
            }, 3000);
            setTimeout(function () {
                var t = document.getElementById('toastMsg');
                if (t) t.remove();
            }, 3700);
        </script>
        <?php
    endif; ?>
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
            <h2>Terminal ID Registration</h2>
            <button class="btn-add-terminal" onclick="toggleForm()">Add Terminal ID</button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php
        endif; ?>

        <div id="formContainer" class="form-container <?php echo $editMode ? '' : 'hidden'; ?>">
            <form method="POST" action="">
                <?php if ($editMode): ?>
                    <input type="hidden" name="terminal_record_id" value="<?php echo $editData['id']; ?>">
                    <?php
                endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Terminal ID</label>
                        <input type="text" name="terminal_id" required
                            value="<?php echo $editMode ? $editData['terminal_id'] : ''; ?>"
                            placeholder="Enter Terminal ID" oninput="this.value = this.value.toUpperCase()">
                    </div>

                    <div class="form-group">
                        <label>Terminal Issuer</label>
                        <select name="terminal_issuer" required>
                            <option value="">Select Terminal Issuer</option>
                            <?php
                            if ($issuers_result && $issuers_result->num_rows > 0) {
                                $issuers_result->data_seek(0);
                                $saved_issuer = ($editMode && !empty($editData['terminal_issuer'])) ? trim($editData['terminal_issuer']) : '';
                                while ($row = $issuers_result->fetch_assoc()) {
                                    $selected = ($row['bank_name'] === $saved_issuer) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($row['bank_name'], ENT_QUOTES) . '" ' . $selected . '>' . htmlspecialchars($row['bank_name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- Hidden input to carry branches value on submit -->
                <input type="hidden" name="branches_hidden" id="branchesHiddenInput"
                    value="<?php echo ($editMode && !empty($editData['branches'])) ? htmlspecialchars($editData['branches']) : ''; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Select Branches</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="selectedBranchesDisplay" readonly placeholder="Select Branches"
                                onclick="openBranchesModal()"
                                value="<?php echo ($editMode && !empty($editData['branches'])) ? htmlspecialchars($editData['branches']) : ''; ?>"
                                style="padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; color: #333; background: white; cursor: pointer; flex-grow: 1;">
                            <button type="button" class="btn-internal-apply"
                                onclick="openBranchesModal()">Select</button>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="toggleForm()">Cancel</button>
                    <button type="submit" name="register_terminal" class="btn-save"
                        onclick="syncBranchesBeforeSubmit()">
                        <?php echo $editMode ? 'Update' : 'Register'; ?>
                    </button>
                </div>
            </form>

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
                        <div id="branchListContainer" style="max-height: 400px; overflow-y: auto;">
                            <?php
                            if ($branches_result && $branches_result->num_rows > 0) {
                                $branches_result->data_seek(0);
                                $saved_branches = ($editMode && !empty($editData['branches'])) ? explode(', ', $editData['branches']) : [];

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
                                echo '<th style="text-align: left;">Area</th>';
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
        </div>

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
                <h3>Terminal ID List</h3>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <select id="statusFilter" onchange="filterByStatus()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer;">
                        <option value="">Select Status</option>
                        <option value="all" <?php echo ($selected_filter === 'all') ? 'selected' : ''; ?>>All Status</option>
                        <option value="Active" <?php echo ($selected_filter === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Deactivated" <?php echo ($selected_filter === 'Deactivated') ? 'selected' : ''; ?>>Deactivated</option>
                    </select>
                    <div class="search-box">
                        <svg viewBox="0 0 24 24">
                            <path
                                d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                        </svg>
                        <input type="text" id="searchInput" placeholder="Search..." onkeyup="searchTable()">
                    </div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Terminal ID</th>
                        <th>Terminal Issuer</th>
                        <th>Branches</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($terminals_result === null): ?>
                        <!-- No filter selected -->
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="16" x2="12" y2="12"></line>
                                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                    </svg>
                                    <div style="color: #333; font-size: 15px; font-weight: 600;">SELECT A FILTER TO DISPLAY THE DATA</div>
                                    <div style="color: #666; font-size: 13px;">Please select a status filter from the dropdown above to view terminal IDs.</div>
                                </div>
                            </td>
                        </tr>
                    <?php elseif ($terminals_result && $terminals_result->num_rows > 0): ?>
                        <?php while ($row = $terminals_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['terminal_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['terminal_issuer']); ?></td>
                                <td>
                                    <?php
                                    $branches = $row['branches'];
                                    if (strlen($branches) > 30) {
                                        echo htmlspecialchars(substr($branches, 0, 30)) . '... ';
                                        echo '<button type="button" class="btn-view" onclick="viewBranches(\'' . htmlspecialchars($branches, ENT_QUOTES) . '\')">View All</button>';
                                    } else {
                                        echo htmlspecialchars($branches);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span
                                        class="status-badge <?php echo strtolower($row['status']) == 'active' ? 'active' : 'inactive'; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="createterminalid.php?edit=<?php echo $row['id']; ?><?php if (!empty($selected_filter)) echo '&status_filter=' . urlencode($selected_filter); ?>" class="btn-edit">Edit</a>
                                    <!-- Toggle Status Button -->
                                    <button
                                        class="btn-toggle-status <?php echo strtolower($row['status']) == 'active' ? 'deactivate' : 'activate'; ?>"
                                        onclick="toggleStatus(<?php echo $row['id']; ?>, '<?php echo $row['status']; ?>')">
                                        <?php echo ($row['status'] == 'Active') ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                    <a href="createterminalid.php?delete=<?php echo $row['id']; ?><?php if (!empty($selected_filter)) echo '&status_filter=' . urlencode($selected_filter); ?>" class="btn-delete"
                                        onclick="return confirm('Are you sure you want to delete this terminal ID?')">Delete</a>
                                </td>
                            </tr>
                            <?php
                        endwhile; ?>
                        <?php
                    else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center !important; padding: 30px 20px;">
                                <div style="color: #666; font-size: 14px;">No terminal IDs found</div>
                            </td>
                        </tr>
                        <?php
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('hidden');
            document.querySelector('.main-content').classList.toggle('expanded');
            document.querySelector('.menu-btn').classList.toggle('active');
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
            const formContainer = document.getElementById('formContainer');
            const btnAdd = document.querySelector('.btn-add-terminal');

            if (formContainer.classList.contains('hidden')) {
                formContainer.classList.remove('hidden');
                btnAdd.style.display = 'none';

                <?php if (!$editMode): ?>
                    document.querySelector('form').reset();
                    document.getElementById('selectedBranchesDisplay').value = '';
                    <?php
                endif; ?>
            } else {
                formContainer.classList.add('hidden');
                btnAdd.style.display = 'block';
                <?php if ($editMode): ?>
                    // Preserve filter parameter when canceling edit
                    <?php
                    $cancel_url = "createterminalid.php";
                    if (!empty($selected_filter)) {
                        $cancel_url .= "?status_filter=" . urlencode($selected_filter);
                    }
                    ?>
                    window.location.href = '<?php echo $cancel_url; ?>';
                    <?php
                endif; ?>
            }
        }

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.querySelector('table');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const tds = tr[i].getElementsByTagName('td');
                let found = false;

                for (let j = 0; j < tds.length - 1; j++) {
                    if (tds[j]) {
                        const txtValue = tds[j].textContent || tds[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }

                tr[i].style.display = found ? "" : "none";
            }
        }

        function toggleStatus(id, currentStatus) {
            // Determine the new status based on current status
            const newStatus = (currentStatus === 'Active') ? 'Deactivated' : 'Active';
            const statusParam = (currentStatus === 'Active') ? 'true' : 'false';

            // Preserve filter parameter
            const urlParams = new URLSearchParams(window.location.search);
            const statusFilter = urlParams.get('status_filter');
            
            let url = `createterminalid.php?status_update=1&id=${id}&status=${statusParam}`;
            if (statusFilter) {
                url += `&status_filter=${encodeURIComponent(statusFilter)}`;
            }
            
            window.location.href = url;
        }

        function filterByStatus() {
            const filter = document.getElementById('statusFilter').value;
            if (filter) {
                window.location.href = 'createterminalid.php?status_filter=' + encodeURIComponent(filter);
            } else {
                window.location.href = 'createterminalid.php';
            }
        }

        // Branch Modal Functions
        function openBranchesModal() {
            document.getElementById('branchesModal').style.display = 'flex';
            updateSelectAllState();
        }

        function closeBranchesModal() {
            document.getElementById('branchesModal').style.display = 'none';
        }

        function toggleBranchModalSelectAll() {
            const selectAll = document.getElementById('selectAllBranchesModal');
            const allChecked = selectAll.checked;
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

            if (checkboxes.length === 0) {
                if (selectAll) selectAll.checked = false;
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

        function applyBranchesSelection() {
            const checkboxes = document.querySelectorAll('.branch-checkbox:checked');
            const selected = Array.from(checkboxes).map(cb => cb.value);

            const displayInput = document.getElementById('selectedBranchesDisplay');
            const hiddenInput = document.getElementById('branchesHiddenInput');
            if (selected.length === 0) {
                displayInput.value = '';
                hiddenInput.value = '';
            } else {
                const joined = selected.join(', ');
                displayInput.value = joined;
                hiddenInput.value = joined;
            }
            closeBranchesModal();
        }

        function syncBranchesBeforeSubmit() {
            // Sync the display value into the hidden input right before form submit
            const displayInput = document.getElementById('selectedBranchesDisplay');
            const hiddenInput = document.getElementById('branchesHiddenInput');
            if (displayInput && hiddenInput) {
                hiddenInput.value = displayInput.value;
            }
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
            const viewModal = document.getElementById('viewBranchesModal');
            if (event.target == branchesModal) {
                branchesModal.style.display = "none";
            }
            if (event.target == viewModal) {
                viewModal.style.display = "none";
            }
        }

        // Init on DOM ready
        document.addEventListener('DOMContentLoaded', function () {
            // Branch checkboxes state
            const checkboxes = document.querySelectorAll('.branch-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectAllState);
            });

            // Update area select all when branch checkboxes change
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    const areaGroup = this.closest('.area-group');
                    if (areaGroup) {
                        const areaCheckbox = areaGroup.querySelector('.area-select-all');
                        const areaBranchCheckboxes = areaGroup.querySelectorAll('.branch-checkbox');
                        const allChecked = Array.from(areaBranchCheckboxes).every(cb => cb.checked);
                        const someChecked = Array.from(areaBranchCheckboxes).some(cb => cb.checked);
                        areaCheckbox.checked = allChecked;
                        areaCheckbox.indeterminate = someChecked && !allChecked;
                    }
                });
            });

            // Ensure branches hidden input is synced on any form submit
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function () {
                    syncBranchesBeforeSubmit();
                });
            }
        });
        // View Branches Modal Functions
        function viewBranches(branchesStr) {
            const modal = document.getElementById('viewBranchesModal');
            const list = document.getElementById('viewBranchesList');
            list.innerHTML = '';

            if (branchesStr && branchesStr.trim() !== '') {
                const branches = branchesStr.split(',').map(b => b.trim());
                branches.forEach(branch => {
                    const li = document.createElement('li');
                    li.textContent = branch;
                    li.style.padding = '8px 0';
                    li.style.borderBottom = '1px solid #eee';
                    list.appendChild(li);
                });
            } else {
                list.innerHTML = '<li>No branches assigned</li>';
            }
            modal.style.display = 'flex';
        }

        function closeViewBranchesModal() {
            document.getElementById('viewBranchesModal').style.display = 'none';
        }
    </script>
</body>

</html>