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


$create_table = "CREATE TABLE IF NOT EXISTS brands (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    brand_name VARCHAR(255) NOT NULL UNIQUE,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$conn->query($create_table);

// Get filter parameter early so it's available for redirects
$selected_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Handle form submission
$message = "";
$messageType = "";
$editMode = false;
$editData = null;

// Check if editing
if (isset($_GET['edit'])) {
    $editMode = true;
    $id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT * FROM brands WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $editData = $result->fetch_assoc();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_brand'])) {
    $brand_name_raw = $_POST['brand_name'];
    
    // Check for leading or trailing spaces
    if ($brand_name_raw !== trim($brand_name_raw)) {
        $message = "Brand name contains special characters! (Leading or trailing spaces are not allowed)";
        $messageType = "error";
    }
    // Check for special characters (only allow alphanumeric and spaces)
    else if (!preg_match('/^[A-Za-z0-9 ]+$/', $brand_name_raw)) {
        $message = "Brand name contains special characters! (Only letters, numbers, and spaces are allowed)";
        $messageType = "error";
    }
    else {
        $brand_name = strtoupper($conn->real_escape_string($brand_name_raw));

        // Check if updating or inserting
        if (isset($_POST['brand_id']) && !empty($_POST['brand_id'])) {
        // Update existing brand
        $id = $conn->real_escape_string($_POST['brand_id']);
        
        // Check if brand name already exists (excluding current record)
        $check_sql = "SELECT id FROM brands WHERE brand_name='$brand_name' AND id != $id";
        $check_result = $conn->query($check_sql);
        
        if ($check_result && $check_result->num_rows > 0) {
            $message = "Brand name '$brand_name' already exists!";
            $messageType = "error";
        } else {
            $sql = "UPDATE brands SET brand_name='$brand_name' WHERE id=$id";

            if ($conn->query($sql) === TRUE) {
                $redirect_url = "brandreg.php?updated=1";
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
    } else {
        // Check if brand already exists before inserting
        $check_sql = "SELECT id FROM brands WHERE brand_name='$brand_name'";
        $check_result = $conn->query($check_sql);
        
        if ($check_result && $check_result->num_rows > 0) {
            $message = "Brand name '$brand_name' already exists!";
            $messageType = "error";
        } else {
            // Insert new brand
            $sql = "INSERT INTO brands (brand_name, status) VALUES ('$brand_name', 'Active')";

            if ($conn->query($sql) === TRUE) {
                $redirect_url = "brandreg.php?success=1";
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
    }
    } // End validation else block
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Brand registered successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Check for update message from redirect
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = "Brand updated successfully!";
    $messageType = "success";
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

    // Checkbox checked = Deactivate
    // Checkbox unchecked = Activate
    $new_status = ($status == 'true') ? 'Deactivated' : 'Active';

    $sql = "UPDATE brands SET status = '$new_status' WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $redirect_url = "brandreg.php";
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
    $sql = "DELETE FROM brands WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "Brand deleted successfully!";
        $messageType = "success";
        $redirect_url = "brandreg.php";
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

// Fetch all brands based on selected filter
// Only execute query if a filter is selected
$brands_result = null;

if (!empty($selected_filter)) {
    $query = "SELECT * FROM brands";
    
    // Apply status filter if not 'all'
    if ($selected_filter !== 'all') {
        $status_safe = $conn->real_escape_string($selected_filter);
        $query .= " WHERE status = '" . $status_safe . "'";
    }
    
    $query .= " ORDER BY brand_name ASC";
    $brands_result = $conn->query($query);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
        <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Brand Registration</title>
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

        .btn-add-brand {
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

        .btn-add-brand:hover {
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
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 20px;
            max-width: 500px;
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

        .form-group input {
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

        .form-group input:focus {
            outline: none;
            border-color: #2196F3;
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
               margin-left: 5px;
        }

        .btn-delete:hover {
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

        @media (max-width: 1024px) {
            .form-row {
                max-width: 100%;
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
            .btn-save {
                width: 100%;
            }

            th,
            td {
                padding: 8px;
                font-size: 12px;
            }
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
            <h2>Brand Registration</h2>
            <button class="btn-add-brand" onclick="toggleForm()">Add Brand</button>
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
                    <input type="hidden" name="brand_id" value="<?php echo $editData['id']; ?>">
                    <?php
                endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Brand Name</label>
                        <input type="text" name="brand_name" required
                            value="<?php echo $editMode ? $editData['brand_name'] : ''; ?>"
                            placeholder="Enter brand name" oninput="this.value = this.value.toUpperCase()">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="toggleForm()">Cancel</button>
                    <button type="submit" name="register_brand" class="btn-save">
                        <?php echo $editMode ? 'Update' : 'Register'; ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3>Brand List</h3>
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
                        </svg> <input type="text" id="searchInput" placeholder="Search..." onkeyup="searchTable()">
                    </div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Brand Name</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($brands_result === null) {
                        // No filter selected - show message
                        echo "<tr><td colspan='3' style='text-align: center; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>
                                <div style='display: flex; flex-direction: column; align-items: center; gap: 12px;'>
                                    <svg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 24 24' fill='none' stroke='#999' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'>
                                        <circle cx='12' cy='12' r='10'></circle>
                                        <line x1='12' y1='16' x2='12' y2='12'></line>
                                        <line x1='12' y1='8' x2='12.01' y2='8'></line>
                                    </svg>
                                    <div style='color: #333; font-size: 15px; font-weight: 600;'>SELECT A FILTER TO DISPLAY THE DATA</div>
                                    <div style='color: #666; font-size: 13px;'>Please select a status filter from the dropdown above to view brands.</div>
                                </div>
                              </td></tr>";
                    } elseif ($brands_result && $brands_result->num_rows > 0) {
                        while ($row = $brands_result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['brand_name']) . "</td>";
                            echo "<td>";
                            echo "<span class='status-badge " . (strtolower($row['status']) == 'active' ? 'active' : 'inactive') . "'>";
                            echo htmlspecialchars($row['status']);
                            echo "</span>";
                            echo "</td>";
                            echo "<td>";
                            echo "<a href='brandreg.php?edit=" . $row['id'];
                            if (!empty($selected_filter)) {
                                echo "&status_filter=" . urlencode($selected_filter);
                            }
                            echo "' class='btn-edit'>Edit</a>";
                            echo "<button class='btn-toggle-status " . (strtolower($row['status']) == 'active' ? 'deactivate' : 'activate') . "' ";
                            echo "onclick='toggleStatus(" . $row['id'] . ", \"" . $row['status'] . "\")'>";
                            echo ($row['status'] == 'Active') ? 'Deactivate' : 'Activate';
                            echo "</button>";
                            echo "<a href='brandreg.php?delete=" . $row['id'] . "' class='btn-delete' ";
                            echo "onclick='return confirm(\"Are you sure you want to delete this brand?\")'>Delete</a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        // No results for the selected filter
                        echo "<tr><td colspan='3' style='text-align: center !important; padding: 40px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc; color: #666;'>No brands found for the selected filter</td></tr>";
                    }
                    ?>
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
            const btnAdd = document.querySelector('.btn-add-brand');

            if (formContainer.classList.contains('hidden')) {
                formContainer.classList.remove('hidden');
                btnAdd.style.display = 'none';

                // If not in edit mode, reset form
                <?php if (!$editMode): ?>
                    document.querySelector('form').reset();
                    <?php
                endif; ?>
            } else {
                formContainer.classList.add('hidden');
                btnAdd.style.display = 'block';
                // If in edit mode and cancelling, preserve status_filter when redirecting
                <?php if ($editMode): ?>
                    const urlParams = new URLSearchParams(window.location.search);
                    const statusFilter = urlParams.get('status_filter');
                    let redirectUrl = 'brandreg.php';
                    if (statusFilter) {
                        redirectUrl += '?status_filter=' + encodeURIComponent(statusFilter);
                    }
                    window.location.href = redirectUrl;
                    <?php
                endif; ?>
            }
        }

        function filterByStatus() {
            const statusFilter = document.getElementById('statusFilter').value;
            // Reload page with filter parameter
            if (statusFilter) {
                window.location.href = 'brandreg.php?status_filter=' + encodeURIComponent(statusFilter);
            } else {
                window.location.href = 'brandreg.php';
            }
        }

        function searchTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const table = document.querySelector('table');
            const tr = table.getElementsByTagName('tr');

            // Only search if data is loaded (filter is selected)
            <?php if ($brands_result !== null): ?>
            for (let i = 1; i < tr.length; i++) {
                const tds = tr[i].getElementsByTagName('td');
                if (!tds || tds.length === 0) continue;

                // Search brand name
                const brandName = tds[0] ? (tds[0].textContent || tds[0].innerText) : '';
                const searchMatch = brandName.toLowerCase().indexOf(searchInput) > -1;
                
                tr[i].style.display = searchMatch ? '' : 'none';
            }
            <?php endif; ?>
        }

        // Initialize: disable search if no filter selected
        document.addEventListener('DOMContentLoaded', function() {
            const searchInputElement = document.getElementById('searchInput');
            
            <?php if ($brands_result === null): ?>
            // Disable search box when no filter is selected
            searchInputElement.disabled = true;
            searchInputElement.placeholder = 'Select a filter first...';
            searchInputElement.style.backgroundColor = '#f5f5f5';
            searchInputElement.style.cursor = 'not-allowed';
            <?php else: ?>
            // Enable search box when filter is selected
            searchInputElement.disabled = false;
            searchInputElement.placeholder = 'Search...';
            searchInputElement.style.backgroundColor = 'white';
            searchInputElement.style.cursor = 'text';
            <?php endif; ?>
        });

        function toggleStatus(id, currentStatus) {
            // Determine the new status based on current status
            const newStatus = (currentStatus === 'Active') ? 'Deactivated' : 'Active';
            const statusParam = (currentStatus === 'Active') ? 'true' : 'false';

            // Preserve status_filter when toggling status
            const urlParams = new URLSearchParams(window.location.search);
            const statusFilter = urlParams.get('status_filter');
            let redirectUrl = `brandreg.php?status_update=1&id=${id}&status=${statusParam}`;
            if (statusFilter) {
                redirectUrl += '&status_filter=' + encodeURIComponent(statusFilter);
            }
            window.location.href = redirectUrl;
        }
    </script>
</body>

</html>