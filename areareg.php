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

$create_table = "CREATE TABLE IF NOT EXISTS areas (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(255) NOT NULL UNIQUE,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$conn->query($create_table);

// Get filter parameter early so it's available for redirects
$selected_filter = isset($_GET['area_filter']) ? $_GET['area_filter'] : '';

// Handle form submission
$message = "";
$messageType = "";
$editMode = false;
$editData = null;

// Check if editing
if (isset($_GET['edit'])) {
    $editMode = true;
    $id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT * FROM areas WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $editData = $result->fetch_assoc();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_area'])) {
    $area_name_raw = $_POST['area_name'];
    
    // Check for leading or trailing spaces
    if ($area_name_raw !== trim($area_name_raw)) {
        $message = "Area name contains special characters! (Leading or trailing spaces are not allowed)";
        $messageType = "error";
    }
    // Check for special characters (only allow alphanumeric and spaces)
    else if (!preg_match('/^[A-Za-z0-9 ]+$/', $area_name_raw)) {
        $message = "Area name contains special characters! (Only letters, numbers, and spaces are allowed)";
        $messageType = "error";
    }
    else {
        $area_name = strtoupper($conn->real_escape_string($area_name_raw));

        // Check if updating or inserting
        if (isset($_POST['area_id']) && !empty($_POST['area_id'])) {
        // Update existing area
        $id = $conn->real_escape_string($_POST['area_id']);
        $sql = "UPDATE areas SET area_name='$area_name' WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            $redirect_url = "areareg.php?updated=1";
            if (!empty($selected_filter)) {
                $redirect_url .= "&area_filter=" . urlencode($selected_filter);
            }
            header("Location: $redirect_url");
            exit();
        } else {
            $message = "Error: " . $conn->error;
            $messageType = "error";
        }
    } else {
        // Insert new area
        $sql = "INSERT INTO areas (area_name, status) VALUES ('$area_name', 'Active')";

        if ($conn->query($sql) === TRUE) {
            $redirect_url = "areareg.php?success=1";
            if (!empty($selected_filter)) {
                $redirect_url .= "&area_filter=" . urlencode($selected_filter);
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
    $message = "Area registered successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Check for update message from redirect
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = "Area updated successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Check for delete message from redirect
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = "Area deleted successfully!";
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

    $sql = "UPDATE areas SET status = '$new_status' WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $redirect_url = "areareg.php";
        if (!empty($selected_filter)) {
            $redirect_url .= "?area_filter=" . urlencode($selected_filter);
        }
        header("Location: $redirect_url");
        exit();
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Handle deletion (optional)
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);
    $sql = "DELETE FROM areas WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $redirect_url = "areareg.php?deleted=1";
        if (!empty($selected_filter)) {
            $redirect_url .= "&area_filter=" . urlencode($selected_filter);
        }
        header("Location: $redirect_url");
        exit();
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Fetch all areas based on selected filter
// Only execute query if a filter is selected
$areas_result = null;

if (!empty($selected_filter)) {
    $query = "SELECT * FROM areas";
    
    // Apply status filter if not 'all'
    if ($selected_filter !== 'all') {
        $status_safe = $conn->real_escape_string($selected_filter);
        $query .= " WHERE status = '" . $status_safe . "'";
    }
    
    $query .= " ORDER BY id DESC";
    $areas_result = $conn->query($query);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
        <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Area Registration</title>
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

        .btn-add-area {
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

        .btn-add-area:hover {
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
            transition: background-color 0.2s;
        }

        .btn-save:hover {
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
        <?php include '_header_user.php'; ?>
    </div>

    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Area Registration</h2>
            <button class="btn-add-area" onclick="toggleForm()">Add Area</button>
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
                    <input type="hidden" name="area_id" value="<?php echo $editData['id']; ?>">
                    <?php
                endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Area Name</label>
                        <input type="text" name="area_name" required
                            value="<?php echo $editMode ? $editData['area_name'] : ''; ?>"
                            placeholder="Enter area name" oninput="this.value = this.value.toUpperCase()">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="toggleForm()">Cancel</button>
                    <button type="submit" name="register_area" class="btn-save">
                        <?php echo $editMode ? 'Update' : 'Register'; ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3>Area List</h3>
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
                        <th>Area Name</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($areas_result === null): ?>
                        <tr><td colspan='3' style='text-align: center !important; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>
                                <div style='display: flex; flex-direction: column; align-items: center; gap: 12px;'>
                                    <svg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 24 24' fill='none' stroke='#999' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'>
                                        <circle cx='12' cy='12' r='10'></circle>
                                        <line x1='12' y1='16' x2='12' y2='12'></line>
                                        <line x1='12' y1='8' x2='12.01' y2='8'></line>
                                    </svg>
                                    <div style='color: #333; font-size: 15px; font-weight: 600;'>SELECT A FILTER TO DISPLAY THE DATA</div>
                                    <div style='color: #666; font-size: 13px;'>Please select a status filter from the dropdown above to view areas.</div>
                                </div>
                        </td></tr>
                    <?php elseif ($areas_result && $areas_result->num_rows > 0): ?>
                        <?php while ($row = $areas_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['area_name']); ?></td>
                                <td>
                                    <span
                                        class="status-badge <?php echo strtolower($row['status']) == 'active' ? 'active' : 'inactive'; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    // Preserve area_filter when editing, deleting, or toggling status
                                    $edit_url = 'areareg.php?edit=' . $row['id'];
                                    $delete_url = 'areareg.php?delete=' . $row['id'];
                                    if (!empty($selected_filter)) {
                                        $edit_url .= '&area_filter=' . urlencode($selected_filter);
                                        $delete_url .= '&area_filter=' . urlencode($selected_filter);
                                    }
                                    ?>
                                    <a href="<?php echo $edit_url; ?>" class="btn-edit">Edit</a>
                                    <button
                                        class="btn-toggle-status <?php echo strtolower($row['status']) == 'active' ? 'deactivate' : 'activate'; ?>"
                                        onclick="toggleStatus(<?php echo $row['id']; ?>, '<?php echo $row['status']; ?>')">
                                        <?php echo ($row['status'] == 'Active') ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                    <a href="<?php echo $delete_url; ?>" class="btn-delete"
                                        onclick="return confirm('Are you sure you want to delete this area?')">Delete</a>
                                </td>
                            </tr>
                            <?php
                        endwhile; ?>
                        <?php
                    else: ?>
                        <tr>
                            <td colspan="3" style='text-align: center !important; padding: 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>No areas found for the selected filter</td>
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
            const btnAdd = document.querySelector('.btn-add-area');

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
                // If in edit mode and cancelling, preserve area_filter when redirecting
                <?php if ($editMode): ?>
                    const urlParams = new URLSearchParams(window.location.search);
                    const areaFilter = urlParams.get('area_filter');
                    let redirectUrl = 'areareg.php';
                    if (areaFilter) {
                        redirectUrl += '?area_filter=' + encodeURIComponent(areaFilter);
                    }
                    window.location.href = redirectUrl;
                    <?php
                endif; ?>
            }
        }

        function filterByStatus() {
            const statusFilter = document.getElementById('statusFilter').value;
            
            // If a filter is selected, reload page with the filter parameter
            if (statusFilter) {
                window.location.href = 'areareg.php?area_filter=' + encodeURIComponent(statusFilter);
            } else {
                // If no filter, just reload the page
                window.location.href = 'areareg.php';
            }
        }

        function searchTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const table = document.querySelector('table');
            const tbody = table.getElementsByTagName('tbody')[0];
            const rows = tbody.getElementsByTagName('tr');

            // Check if we're in the "no filter selected" state
            const firstRow = rows[0];
            if (firstRow && firstRow.cells[0] && firstRow.cells[0].colSpan == 3) {
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
                    const areaName = cells[0] ? (cells[0].textContent || cells[0].innerText) : '';
                    searchMatch = areaName.toLowerCase().indexOf(searchInput) > -1;
                }

                row.style.display = searchMatch ? '' : 'none';
            }
        }

        // Initialize: Check if we have data loaded
        (function() {
            const table = document.querySelector('table');
            if (table) {
                const tbody = table.getElementsByTagName('tbody')[0];
                const rows = tbody.getElementsByTagName('tr');
                
                // Check if we're showing the "SELECT A FILTER" message
                if (rows.length > 0) {
                    const firstRow = rows[0];
                    if (firstRow && firstRow.cells[0] && firstRow.cells[0].colSpan == 3) {
                        // This is the "SELECT A FILTER" message, disable search
                        const searchInput = document.getElementById('searchInput');
                        searchInput.disabled = true;
                        searchInput.placeholder = "Select a filter first...";
                    }
                }
            }
        })();

        function toggleStatus(id, currentStatus) {
            // Determine the new status based on current status
            const newStatus = (currentStatus === 'Active') ? 'Deactivated' : 'Active';
            const statusParam = (currentStatus === 'Active') ? 'true' : 'false';

            // Preserve area_filter when toggling status
            const urlParams = new URLSearchParams(window.location.search);
            const areaFilter = urlParams.get('area_filter');
            let redirectUrl = `areareg.php?status_update=1&id=${id}&status=${statusParam}`;
            if (areaFilter) {
                redirectUrl += '&area_filter=' + encodeURIComponent(areaFilter);
            }
            window.location.href = redirectUrl;
        }
    </script>
</body>

</html>
