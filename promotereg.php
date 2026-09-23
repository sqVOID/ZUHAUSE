<?php
require_once 'session_check.php';

// Authorization Check
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$user_position = isset($_SESSION['user_position']) ? trim($_SESSION['user_position']) : '';

// Allow access for Area Manager (case-insensitive) or non-User system levels
$is_area_manager = (stripos($user_position, 'Area Manager') !== false);

// Restrict 'User' from accessing this page, but allow Area Managers
if (false) {
    header("Location: report.php");
    exit();
}
include 'config.php';

$create_table = "CREATE TABLE IF NOT EXISTS promoters (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL,
    brand VARCHAR(255) NOT NULL,
    branch VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";


$conn->query($create_table);

// Get filter parameter early so it's available for redirects
$selected_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$selected_brand_filter = isset($_GET['brand_filter']) ? $_GET['brand_filter'] : '';

// Handle form submission
$message = "";
$messageType = "";
$editMode = false;
$editData = null;


if (isset($_GET['edit'])) {
    $editMode = true;
    $id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT * FROM promoters WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $editData = $result->fetch_assoc();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_promoter'])) {
    $name_raw = $_POST['name'];
    $position = $conn->real_escape_string($_POST['position']);
    $brand = $conn->real_escape_string($_POST['brand']);
    $branch = isset($_POST['branch']) && is_array($_POST['branch']) ? implode(', ', $_POST['branch']) : '';

    // Check for leading or trailing spaces in name
    if ($name_raw !== trim($name_raw)) {
        $message = "Promoter name contains special characters! (Leading or trailing spaces are not allowed)";
        $messageType = "error";
    }
    // Check for special characters in name (only allow alphanumeric and spaces)
    else if (!preg_match('/^[A-Za-z0-9 ]+$/', $name_raw)) {
        $message = "Promoter name contains special characters! (Only letters, numbers, and spaces are allowed)";
        $messageType = "error";
    } else {
        // Convert name to uppercase before saving
        $name = $conn->real_escape_string(strtoupper($name_raw));

        // Check if updating or inserting
        if (isset($_POST['promoter_id']) && !empty($_POST['promoter_id'])) {
            // Update existing promoter
            $id = $conn->real_escape_string($_POST['promoter_id']);
            $sql = "UPDATE promoters SET 
                name='$name', 
                position='$position', 
                brand='$brand', 
                branch='$branch'
                WHERE id=$id";

            if ($conn->query($sql) === TRUE) {
                $redirect_url = "promotereg.php?updated=1";
                if (!empty($selected_filter)) {
                    $redirect_url .= "&status_filter=" . urlencode($selected_filter);
                }
                if (!empty($selected_brand_filter)) {
                    $redirect_url .= "&brand_filter=" . urlencode($selected_brand_filter);
                }
                header("Location: $redirect_url");
                exit();
            } else {
                $message = "Error: " . $conn->error;
                $messageType = "error";
            }
        } else {
            // Insert new promoter
            $sql = "INSERT INTO promoters (name, position, brand, branch, status) 
                VALUES ('$name', '$position', '$brand', '$branch', 'Active')";

            if ($conn->query($sql) === TRUE) {
                $redirect_url = "promotereg.php?success=1";
                if (!empty($selected_filter)) {
                    $redirect_url .= "&status_filter=" . urlencode($selected_filter);
                }
                if (!empty($selected_brand_filter)) {
                    $redirect_url .= "&brand_filter=" . urlencode($selected_brand_filter);
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
    $message = "Promoter registered successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Check for update message from redirect
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = "Promoter updated successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Handle deactivation
if (isset($_GET['deactivate'])) {
    $id = $conn->real_escape_string($_GET['deactivate']);
    $sql = "UPDATE promoters SET status='Deactivated' WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "Promoter deactivated successfully!";
        $messageType = "success";
        $redirect_url = "promotereg.php";
        $params = [];
        if (!empty($selected_filter)) {
            $params[] = "status_filter=" . urlencode($selected_filter);
        }
        if (!empty($selected_brand_filter)) {
            $params[] = "brand_filter=" . urlencode($selected_brand_filter);
        }
        if (!empty($params)) {
            $redirect_url .= "?" . implode("&", $params);
        }
        header("Location: $redirect_url");
        exit();
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Handle activation
if (isset($_GET['activate'])) {
    $id = $conn->real_escape_string($_GET['activate']);
    $sql = "UPDATE promoters SET status='Active' WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "Promoter activated successfully!";
        $messageType = "success";
        $redirect_url = "promotereg.php";
        $params = [];
        if (!empty($selected_filter)) {
            $params[] = "status_filter=" . urlencode($selected_filter);
        }
        if (!empty($selected_brand_filter)) {
            $params[] = "brand_filter=" . urlencode($selected_brand_filter);
        }
        if (!empty($params)) {
            $redirect_url .= "?" . implode("&", $params);
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
    $sql = "DELETE FROM promoters WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "Promoter deleted successfully!";
        $messageType = "success";
        $redirect_url = "promotereg.php";
        $params = [];
        if (!empty($selected_filter)) {
            $params[] = "status_filter=" . urlencode($selected_filter);
        }
        if (!empty($selected_brand_filter)) {
            $params[] = "brand_filter=" . urlencode($selected_brand_filter);
        }
        if (!empty($params)) {
            $redirect_url .= "?" . implode("&", $params);
        }
        header("Location: $redirect_url");
        exit();
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Fetch all promoters based on selected filter
// Only execute query if a filter is selected
$promoters_result = null;

if (!empty($selected_filter)) {
    $query = "SELECT * FROM promoters";
    $conditions = [];

    // Apply status filter if not 'all'
    if ($selected_filter !== 'all') {
        $status_safe = $conn->real_escape_string($selected_filter);
        $conditions[] = "status = '" . $status_safe . "'";
    }

    // Apply brand filter if selected and not 'all'
    if (!empty($selected_brand_filter) && $selected_brand_filter !== 'all') {
        $brand_safe = $conn->real_escape_string($selected_brand_filter);
        $conditions[] = "brand = '" . $brand_safe . "'";
    }

    // Add WHERE clause if there are conditions
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY id DESC";
    $promoters_result = $conn->query($query);
}

// Fetch all unique brands for brand filter dropdown
$brands_result = $conn->query("SELECT DISTINCT brand FROM promoters WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");

// Fetch all branches for dropdown
$branches_result = $conn->query("SELECT * FROM branches ORDER BY branch_name");

// Fetch all brands for dropdown (for registration form)
$brands_for_form_result = $conn->query("SELECT brand_name FROM brands ORDER BY brand_name");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Promoter Registration</title>
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
            /* box-shadow: 0 2px 4px rgba(0,0,0,0.1); */
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

        .btn-add-promoter {
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

        .btn-add-promoter:hover {
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

        .btn-register {
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

        .btn-register:hover {
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

        /* Custom Checkbox Dropdown Styles */
        .checkbox-dropdown {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px 12px;
            position: relative;
            margin: 0;
            user-select: none;
            background: white;
            cursor: pointer;
        }

        .checkbox-dropdown:after {
            content: '';
            height: 0;
            position: absolute;
            width: 0;
            border: 6px solid transparent;
            border-top-color: #333;
            top: 50%;
            right: 10px;
            margin-top: -3px;
        }

        .checkbox-dropdown.is-active:after {
            border-bottom-color: #333;
            border-top-color: transparent;
            margin-top: -9px;
        }

        .checkbox-dropdown-list {
            list-style: none;
            margin: 0;
            padding: 0;
            position: absolute;
            top: 100%;
            border: 1px solid #ddd;
            border-top: none;
            left: -1px;
            right: -1px;
            opacity: 0;
            transition: opacity 0.2s;
            background: white;
            pointer-events: none;
            z-index: 100;
            max-height: 200px;
            overflow-y: auto;
            border-bottom-left-radius: 4px;
            border-bottom-right-radius: 4px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .checkbox-dropdown.is-active .checkbox-dropdown-list {
            opacity: 1;
            pointer-events: auto;
        }

        .checkbox-dropdown-list li {
            padding: 0;
        }

        .checkbox-dropdown-list li label {
            display: block;
            padding: 10px 12px;
            cursor: pointer;
            width: 100%;
            margin: 0;
        }

        .checkbox-dropdown-list li label:hover {
            background-color: #f5f5f5;
        }

        .checkbox-dropdown-list li input[type="checkbox"] {
            margin-right: 10px;
            width: auto;
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

        .btn-activate {
            padding: 6px 16px;
            border: none;
            background: #2e7d32;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
        }

        .btn-activate:hover {
            background: #1b5e20;
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
            margin-right: 5px;
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

        .modal-body {
            padding: 30px;
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

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1500;
            }

            .header::after {
                left: 0;
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
            .btn-register {
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
            <h2>Promoter Registration</h2>
            <button type="button" class="btn-add-promoter" onclick="toggleForm()">Add Promoter </button>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php
        endif; ?>

        <form method="POST" action="" class="form-container hidden" id="promoterForm">
            <input type="hidden" name="promoter_id" id="promoter_id"
                value="<?php echo $editMode && $editData ? $editData['id'] : ''; ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="name" placeholder="Enter name"
                        value="<?php echo $editMode && $editData ? htmlspecialchars($editData['name']) : ''; ?>"
                        oninput="this.value = this.value.toUpperCase()"
                        required>
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <select name="position" id="position" required>
                        <option value="">Select Position</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Brand</label>
                    <select name="brand" id="brand" required>
                        <option value="">Select Brand</option>
                        <?php
                        if ($brands_for_form_result && $brands_for_form_result->num_rows > 0) {
                            $brands_for_form_result->data_seek(0); // Reset pointer
                            while ($row = $brands_for_form_result->fetch_assoc()) {
                                $selected = ($editMode && $editData && $editData['brand'] == $row['brand_name']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($row['brand_name']) . "' $selected>" . htmlspecialchars($row['brand_name']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Branch</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="selectedBranchesDisplay" name="branch_display"
                            placeholder="Select Branch" readonly style="flex-grow: 1; cursor: pointer;"
                            onclick="openBranchesModal()">
                        <input type="hidden" id="selectedBranches" name="branch[]">
                        <!-- This will be populated by JS -->
                        <button type="button" class="btn-register" style="padding: 10px 20px;"
                            onclick="openBranchesModal()">Select</button>
                    </div>
                </div>
            </div>

            <!-- Branches Selection Modal -->
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
                        <button type="button" class="btn-cancel" onclick="closeBranchesModal()">Cancel</button>
                        <button type="button" class="btn-register" onclick="applyBranchesSelection()">Done</button>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="toggleForm()">Cancel</button>
                <button type="submit" name="register_promoter"
                    class="btn-register"><?php echo $editMode ? 'Update' : 'Add'; ?></button>
            </div>
        </form>

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

        <div class="table-container">
            <div class="table-header">
                <h3>Promoters</h3>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <select id="statusFilter" onchange="applyFilters()"
                        style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer;">
                        <option value="">Select Status</option>
                        <option value="all" <?php echo ($selected_filter === 'all') ? 'selected' : ''; ?>>All Status
                        </option>
                        <option value="Active" <?php echo ($selected_filter === 'Active') ? 'selected' : ''; ?>>Active
                        </option>
                        <option value="Deactivated" <?php echo ($selected_filter === 'Deactivated') ? 'selected' : ''; ?>>
                            Deactivated</option>
                    </select>
                    <select id="brandFilter" onchange="applyFilters()"
                        style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer;">
                        <option value="">Select Brand</option>
                        <option value="all" <?php echo ($selected_brand_filter === 'all') ? 'selected' : ''; ?>>All Brands
                        </option>
                        <?php
                        if ($brands_result && $brands_result->num_rows > 0) {
                            while ($brand_row = $brands_result->fetch_assoc()) {
                                $brand = htmlspecialchars($brand_row['brand']);
                                $selected = ($selected_brand_filter === $brand) ? 'selected' : '';
                                echo '<option value="' . $brand . '" ' . $selected . '>' . $brand . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search promoters..." onkeyup="searchTable()">
                        <svg viewBox="0 0 24 24">
                            <path
                                d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                        </svg>
                    </div>
                </div>
            </div>
            <table id="promoterTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Brand</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($promoters_result === null) {
                        // No filter selected - show message
                        echo "<tr><td colspan='6' style='text-align: center; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>
                                <div style='display: flex; flex-direction: column; align-items: center; gap: 12px;'>
                                    <svg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 24 24' fill='none' stroke='#999' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'>
                                        <circle cx='12' cy='12' r='10'></circle>
                                        <line x1='12' y1='16' x2='12' y2='12'></line>
                                        <line x1='12' y1='8' x2='12.01' y2='8'></line>
                                    </svg>
                                    <div style='color: #333; font-size: 15px; font-weight: 600;'>SELECT A FILTER TO DISPLAY THE DATA</div>
                                    <div style='color: #666; font-size: 13px;'>Please select a status filter from the dropdown above to view promoters.</div>
                                </div>
                              </td></tr>";
                    } elseif ($promoters_result && $promoters_result->num_rows > 0) {
                        $promoters_result->data_seek(0); // Reset pointer
                        while ($row = $promoters_result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['position']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['brand']) . "</td>";

                            $branchContent = 'None';
                            if (!empty($row['branch'])) {
                                $branchList = htmlspecialchars($row['branch'], ENT_QUOTES);
                                $branchContent = "<button type='button' class='btn-view-branches' onclick='viewBranches(\"" . $branchList . "\")'>View</button>";
                            }
                            echo "<td>" . $branchContent . "</td>";

                            $statusClass = strtolower($row['status']) == 'active' ? 'active' : 'inactive';
                            echo "<td><span class='status-badge $statusClass'>" . htmlspecialchars($row['status']) . "</span></td>";

                            echo "<td>";
                            // Preserve status_filter and brand_filter when editing, activating, deactivating, or deleting
                            $edit_url = '?edit=' . $row['id'];
                            $deactivate_url = '?deactivate=' . $row['id'];
                            $activate_url = '?activate=' . $row['id'];
                            $delete_url = '?delete=' . $row['id'];
                            if (!empty($selected_filter)) {
                                $edit_url .= '&status_filter=' . urlencode($selected_filter);
                                $deactivate_url .= '&status_filter=' . urlencode($selected_filter);
                                $activate_url .= '&status_filter=' . urlencode($selected_filter);
                                $delete_url .= '&status_filter=' . urlencode($selected_filter);
                            }
                            if (!empty($selected_brand_filter)) {
                                $edit_url .= '&brand_filter=' . urlencode($selected_brand_filter);
                                $deactivate_url .= '&brand_filter=' . urlencode($selected_brand_filter);
                                $activate_url .= '&brand_filter=' . urlencode($selected_brand_filter);
                                $delete_url .= '&brand_filter=' . urlencode($selected_brand_filter);
                            }
                            echo "<a href='" . $edit_url . "' class='btn-edit' onclick='return editPromoter(" . $row['id'] . ")'>Edit</a>";

                            if (strtolower($row['status']) == 'active') {
                                echo "<a href='" . $deactivate_url . "' class='btn-deactivate' onclick='return confirm(\"Are you sure you want to deactivate this promoter?\")'>Deactivate</a>";
                            } else {
                                echo "<a href='" . $activate_url . "' class='btn-activate' onclick='return confirm(\"Are you sure you want to activate this promoter?\")'>Activate</a>";
                            }

                            echo "<a href='" . $delete_url . "' class='btn-delete' onclick='return confirm(\"Are you sure you want to delete this promoter?\")'>Delete</a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align: center !important; padding: 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>No promoters found for the selected filter</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <style>
        /* Add styling for Branch Modal */
        #branchesModal .modal-content {
            max-width: 800px;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
        }

        #branchesModal .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            font-size: 18px;
            font-weight: 600;
        }

        #branchesModal .modal-body {
            padding: 20px;
            overflow-y: auto;
            flex-grow: 1;
        }

        #branchesModal .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Branches Table Styles */
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
    </style>
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

            // Save the sidebar state to persist across navigation
            if (typeof saveSidebarState === 'function') {
                saveSidebarState();
            }
        }

        function applyFilters() {
            const statusFilter = document.getElementById('statusFilter').value;
            const brandFilter = document.getElementById('brandFilter').value;

            // Build URL with both filters
            let url = 'promotereg.php?';
            let params = [];

            if (statusFilter) {
                params.push('status_filter=' + encodeURIComponent(statusFilter));
            }

            if (brandFilter) {
                params.push('brand_filter=' + encodeURIComponent(brandFilter));
            }

            if (params.length > 0) {
                url += params.join('&');
                window.location.href = url;
            } else {
                window.location.href = 'promotereg.php';
            }
        }

        // Keep old function for backward compatibility
        function filterByStatus() {
            applyFilters();
        }

        function searchTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const table = document.getElementById('promoterTable');
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
        document.addEventListener('DOMContentLoaded', function () {
            const table = document.getElementById('promoterTable');
            if (table) {
                const tbody = table.getElementsByTagName('tbody')[0];
                const rows = tbody.getElementsByTagName('tr');

                // Check if we're showing the "SELECT A FILTER" message
                if (rows.length > 0) {
                    const firstRow = rows[0];
                    if (firstRow && firstRow.cells[0] && firstRow.cells[0].colSpan == 6) {
                        // This is the "SELECT A FILTER" message, disable search
                        const searchInput = document.getElementById('searchInput');
                        searchInput.disabled = true;
                        searchInput.placeholder = "Select a filter first...";
                    }
                }
            }
        });

        function toggleForm() {
            const form = document.getElementById('promoterForm');
            const button = document.querySelector('.btn-add-promoter');

            if (form.classList.contains('hidden')) {
                form.classList.remove('hidden');
                button.style.display = 'none';
            } else {
                // Check if we're in edit mode (URL has edit parameter)
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('edit')) {
                    // Preserve status_filter when canceling edit
                    let redirectUrl = 'promotereg.php';
                    const statusFilter = urlParams.get('status_filter');
                    if (statusFilter) {
                        redirectUrl += '?status_filter=' + encodeURIComponent(statusFilter);
                    }
                    window.location.href = redirectUrl;
                    return;
                }

                form.classList.add('hidden');
                button.style.display = 'block';
                // Reset form fields
                form.reset();
                document.getElementById('promoter_id').value = '';
                document.querySelector('.btn-register').textContent = 'Add';

                // Reset branches
                const displayInput = document.getElementById('selectedBranchesDisplay');
                if (displayInput) {
                    displayInput.value = '';
                    displayInput.placeholder = 'Select Branch';
                }
                const checkboxes = document.querySelectorAll('.branch-checkbox');
                checkboxes.forEach(cb => cb.checked = false);
                updateSelectAllState();
            }
        }

        // --- Branch Modal Functions ---

        function openBranchesModal() {
            document.getElementById('branchesModal').style.display = 'flex';
            updateSelectAllState(); // Ensure state is correct when opening

            // Also ensure initial display value is correct based on pre-checked boxes (from PHP)
            const displayInput = document.getElementById('selectedBranchesDisplay');
            if (displayInput && !displayInput.value) {
                applyBranchesSelection(true); // pass true to suppress closing
            }
        }

        function closeBranchesModal() {
            document.getElementById('branchesModal').style.display = 'none';
        }

        function toggleBranchModalSelectAll() {
            const selectAll = document.getElementById('selectAllBranchesModal');
            const checkboxes = document.querySelectorAll('.branch-checkbox');

            checkboxes.forEach(cb => {
                if (cb.closest('.branch-item-label').style.display !== 'none') {
                    cb.checked = selectAll.checked;
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

        function applyBranchesSelection(suppressClose = false) {
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

            if (!suppressClose) {
                closeBranchesModal();
            }
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

            // Initialize display text
            applyBranchesSelection(true);
        });

        // Close modal if clicked outside
        window.onclick = function (event) {
            const branchesModal = document.getElementById('branchesModal');
            if (event.target == branchesModal) {
                branchesModal.style.display = "none";
            }
            // Close modal if clicked outside
            const viewBranchesModal = document.getElementById('viewBranchesModal');
            if (event.target == viewBranchesModal) {
                viewBranchesModal.style.display = "none";
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

        function editPromoter(id) {
            // Show the form
            const form = document.getElementById('promoterForm');
            const button = document.querySelector('.btn-add-promoter');
            form.classList.remove('hidden');
            button.style.display = 'none';

            // Scroll to form
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });

            // Trigger display update for loaded edit data
            // We need to re-check the checkboxes based on edit data? 
            // Actually PHP handles the 'checked' state during render.
            // We just need to update the text display and modal state.
            setTimeout(() => {
                applyBranchesSelection(true);
            }, 100);

            return true;
        }

        // Fetch positions from the database and populate dropdown
        function loadPositions() {
            fetch('fetch_positions.php')
                .then(response => response.json())
                .then(data => {
                    const positionSelect = document.getElementById('position');
                    // Clear existing options except the first one
                    positionSelect.innerHTML = '<option value="">Select Position</option>';

                    // Add positions from database
                    data.forEach(position => {
                        const option = document.createElement('option');
                        option.value = position.position_name;
                        option.textContent = position.position_name;

                        // Check if this is the selected position in edit mode
                        <?php if ($editMode && $editData): ?>
                            if (position.position_name === '<?php echo addslashes($editData['position']); ?>') {
                                option.selected = true;
                            }
                        <?php endif; ?>

                        positionSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading positions:', error);
                });
        }

        // Load positions when page loads
        window.addEventListener('DOMContentLoaded', function () {
            loadPositions();
        });

        // Check if we're in edit mode on page load
        <?php if ($editMode && $editData): ?>
            window.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('promoterForm');
                const button = document.querySelector('.btn-add-promoter');
                form.classList.remove('hidden');
                button.style.display = 'none';
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                // updateBranchDisplay(); // function replaced
                applyBranchesSelection(true);
            });
            <?php
        endif; ?>
    </script>
</body>

</html>
<?php
$conn->close();
?>