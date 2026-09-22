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
$create_table = "CREATE TABLE IF NOT EXISTS dealers (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    dealer_name VARCHAR(255) NOT NULL,
    store_name VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    notes TEXT,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$conn->query($create_table);

// Get filter parameter early so it's available for redirects
$selected_filter = isset($_GET['dealer_filter']) ? $_GET['dealer_filter'] : '';

// Handle form submission
$message = "";
$messageType = "";
$editMode = false;
$editData = null;

// Check if editing
if (isset($_GET['edit'])) {
    $editMode = true;
    $id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT * FROM dealers WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $editData = $result->fetch_assoc();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_dealer'])) {
    $dealer_name = $conn->real_escape_string($_POST['dealer_name']);
    $store_name_raw = $_POST['store_name'];
    $contact_number = $conn->real_escape_string($_POST['contact_number']);
    $address = $conn->real_escape_string($_POST['address']);
    $notes = $conn->real_escape_string($_POST['notes']);

    // Check for leading or trailing spaces
    if ($store_name_raw !== trim($store_name_raw)) {
        $message = "Store name contains special characters! (Leading or trailing spaces are not allowed)";
        $messageType = "error";
    }
    // Check for special characters (only allow alphanumeric and spaces)
    else if (!preg_match('/^[A-Za-z0-9 ]+$/', $store_name_raw)) {
        $message = "Store name contains special characters! (Only letters, numbers, and spaces are allowed)";
        $messageType = "error";
    }
    else {
        $store_name = strtoupper($conn->real_escape_string($store_name_raw));

    // Check if updating or inserting
    if (isset($_POST['dealer_id']) && !empty($_POST['dealer_id'])) {
        // Update existing dealer
        $id = $conn->real_escape_string($_POST['dealer_id']);
        $sql = "UPDATE dealers SET 
                dealer_name='$dealer_name', 
                store_name='$store_name', 
                contact_number='$contact_number', 
                address='$address', 
                notes='$notes' 
                WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            $redirect_url = "dealerregistration.php?updated=1";
            if (!empty($selected_filter)) {
                $redirect_url .= "&dealer_filter=" . urlencode($selected_filter);
            }
            header("Location: $redirect_url");
            exit();
        } else {
            $message = "Error: " . $conn->error;
            $messageType = "error";
        }
    } else {
        // Insert new dealer
        $sql = "INSERT INTO dealers (dealer_name, store_name, contact_number, address, notes, status) 
                VALUES ('$dealer_name', '$store_name', '$contact_number', '$address', '$notes', 'Active')";

        if ($conn->query($sql) === TRUE) {
            $redirect_url = "dealerregistration.php?success=1";
            if (!empty($selected_filter)) {
                $redirect_url .= "&dealer_filter=" . urlencode($selected_filter);
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
    $message = "Dealer registered successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Check for update message from redirect
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = "Dealer updated successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Handle activation
if (isset($_GET['activate'])) {
    $id = $conn->real_escape_string($_GET['activate']);
    $sql = "UPDATE dealers SET status='Active' WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "Dealer activated successfully!";
        $messageType = "success";
        $redirect_url = "dealerregistration.php?activated=1";
        if (!empty($selected_filter)) {
            $redirect_url .= "&dealer_filter=" . urlencode($selected_filter);
        }
        header("Location: $redirect_url");
        exit();
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Check for activation message from redirect
if (isset($_GET['activated']) && $_GET['activated'] == 1) {
    $message = "Dealer activated successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Handle deactivation (change status to Inactive)
if (isset($_GET['deactivate'])) {
    $id = $conn->real_escape_string($_GET['deactivate']);
    $sql = "UPDATE dealers SET status='Inactive' WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "Dealer deactivated successfully!";
        $messageType = "success";
        $redirect_url = "dealerregistration.php";
        if (!empty($selected_filter)) {
            $redirect_url .= "?dealer_filter=" . urlencode($selected_filter);
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
    $sql = "DELETE FROM dealers WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "Dealer deleted successfully!";
        $messageType = "success";
        $redirect_url = "dealerregistration.php";
        if (!empty($selected_filter)) {
            $redirect_url .= "?dealer_filter=" . urlencode($selected_filter);
        }
        header("Location: $redirect_url");
        exit();
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Fetch all dealers based on selected filter
// Only execute query if a filter is selected
$dealers_result = null;

if (!empty($selected_filter)) {
    $query = "SELECT * FROM dealers";
    
    // Apply status filter if not 'all'
    if ($selected_filter !== 'all') {
        $status_safe = $conn->real_escape_string($selected_filter);
        $query .= " WHERE status = '" . $status_safe . "'";
    }
    
    $query .= " ORDER BY id DESC";
    $dealers_result = $conn->query($query);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
        <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Dealer Registration</title>
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

        .btn-add-dealer {
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

        .btn-add-dealer:hover {
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

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: white;
            font-family: Arial, sans-serif;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            max-height: 200px;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #999;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
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
        }

        .btn-deactivate:hover {
            background: #b71c1c;
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

        /* Responsive Design */
        @media (max-width: 1024px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: 1;
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
            .btn-register {
                width: 100%;
            }

            .form-group label {
                font-size: 13px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 13px;
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
            <h2>Dealer Registration</h2>
            <button type="button" class="btn-add-dealer" onclick="toggleForm()">Add Dealer Registration</button>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php
        endif; ?>

        <form method="POST" action="" class="form-container hidden" id="dealerForm">
            <input type="hidden" name="dealer_id" id="dealer_id"
                value="<?php echo $editMode && $editData ? $editData['id'] : ''; ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Dealer Name</label>
                    <input type="text" name="dealer_name" id="dealer_name" placeholder="Enter dealer name"
                        value="<?php echo $editMode && $editData ? htmlspecialchars($editData['dealer_name']) : ''; ?>"
                        required>
                </div>
                <div class="form-group">
                    <label>Store Name</label>
                    <input type="text" name="store_name" id="store_name" placeholder="Enter store name"
                        value="<?php echo $editMode && $editData ? htmlspecialchars($editData['store_name']) : ''; ?>"
                        required oninput="this.value = this.value.toUpperCase()">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group full-width">
                    <label>Contact Number</label>
                    <input type="tel" name="contact_number" id="contact_number" placeholder="Enter contact number"
                        value="<?php echo $editMode && $editData ? htmlspecialchars($editData['contact_number']) : ''; ?>"
                        required oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group full-width">
                    <label>Address</label>
                    <input type="text" name="address" id="address" placeholder="Enter dealer address"
                        value="<?php echo $editMode && $editData ? htmlspecialchars($editData['address']) : ''; ?>"
                        required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group full-width">
                    <label>Notes</label>
                    <textarea name="notes" id="notes"
                        placeholder="Enter any additional notes or comments..."><?php echo $editMode && $editData ? htmlspecialchars($editData['notes']) : ''; ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="toggleForm()">Cancel</button>
                <button type="submit" name="register_dealer"
                    class="btn-register"><?php echo $editMode ? 'Update' : 'Add'; ?></button>
            </div>
        </form>

        <div class="table-container">
            <div class="table-header">
                <h3>Registered Dealers</h3>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <select id="statusFilter" onchange="filterByStatus()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer;">
                        <option value="">Select Status</option>
                        <option value="all" <?php echo ($selected_filter === 'all') ? 'selected' : ''; ?>>All Status</option>
                        <option value="Active" <?php echo ($selected_filter === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Deactivated" <?php echo ($selected_filter === 'Deactivated') ? 'selected' : ''; ?>>Deactivated</option>
                    </select>
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search dealers..." onkeyup="searchTable()">
                        <svg viewBox="0 0 24 24">
                            <path
                                d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                        </svg>
                    </div>
                </div>
            </div>
            <table id="dealerTable">
                <thead>
                    <tr>
                        <th>Dealer Name</th>
                        <th>Store Name</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($dealers_result === null) {
                        // No filter selected - show message
                        echo "<tr><td colspan='6' style='text-align: center !important; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>
                                <div style='display: flex; flex-direction: column; align-items: center; gap: 12px;'>
                                    <svg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 24 24' fill='none' stroke='#999' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'>
                                        <circle cx='12' cy='12' r='10'></circle>
                                        <line x1='12' y1='16' x2='12' y2='12'></line>
                                        <line x1='12' y1='8' x2='12.01' y2='8'></line>
                                    </svg>
                                    <div style='color: #333; font-size: 15px; font-weight: 600;'>SELECT A FILTER TO DISPLAY THE DATA!</div>
                                    <div style='color: #666; font-size: 13px;'>Please select a status filter from the dropdown above to view dealers.</div>
                                </div>
                              </td></tr>";
                    } elseif ($dealers_result && $dealers_result->num_rows > 0) {
                        while ($row = $dealers_result->fetch_assoc()) {
                            $status = htmlspecialchars($row['status']);
                            $statusClass = strtolower($status);
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['dealer_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['store_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['contact_number']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['address']) . "</td>";
                            echo "<td><span class='status-badge " . $statusClass . "'>" . $status . "</span></td>";
                            echo "<td>";
                            // Preserve dealer_filter when editing, activating, deactivating, or deleting
                            $edit_url = '?edit=' . $row['id'];
                            $activate_url = '?activate=' . $row['id'];
                            $deactivate_url = '?deactivate=' . $row['id'];
                            $delete_url = '?delete=' . $row['id'];
                            if (!empty($selected_filter)) {
                                $edit_url .= '&dealer_filter=' . urlencode($selected_filter);
                                $activate_url .= '&dealer_filter=' . urlencode($selected_filter);
                                $deactivate_url .= '&dealer_filter=' . urlencode($selected_filter);
                                $delete_url .= '&dealer_filter=' . urlencode($selected_filter);
                            }
                            echo "<a href='" . $edit_url . "' class='btn-edit' onclick='return editDealer(" . $row['id'] . ")'>Edit</a>";
                            if ($status !== 'Active') {
                                echo "<a href='" . $activate_url . "' class='btn-activate' onclick='return confirm(\"Are you sure you want to activate this dealer?\")'>Activate</a>";
                            }
                            echo "<a href='" . $deactivate_url . "' class='btn-deactivate' onclick='return confirm(\"Are you sure you want to deactivate this dealer?\")'>Deactivate</a>";
                            echo "<a href='" . $delete_url . "' class='btn-delete' onclick='return confirm(\"Are you sure you want to delete this dealer?\")'>Delete</a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align: center !important; padding: 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>No dealers found for the selected filter</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
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
            const form = document.getElementById('dealerForm');
            const button = document.querySelector('.btn-add-dealer');

            if (form.classList.contains('hidden')) {
                form.classList.remove('hidden');
                button.style.display = 'none';
            } else {
                // Check if we're in edit mode (URL has edit parameter)
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('edit')) {
                    // Preserve dealer_filter when canceling edit
                    let redirectUrl = 'dealerregistration.php';
                    const dealerFilter = urlParams.get('dealer_filter');
                    if (dealerFilter) {
                        redirectUrl += '?dealer_filter=' + encodeURIComponent(dealerFilter);
                    }
                    window.location.href = redirectUrl;
                    return;
                }

                form.classList.add('hidden');
                button.style.display = 'block';
                form.reset();
                document.getElementById('dealer_id').value = '';
                document.querySelector('.btn-register').textContent = 'Activate';
            }
        }

        function editDealer(id) {
            // Show the form
            const form = document.getElementById('dealerForm');
            const button = document.querySelector('.btn-add-dealer');
            form.classList.remove('hidden');
            button.style.display = 'none';

            // Scroll to form
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });

            return true;
        }

        // Check if we're in edit mode on page load
        <?php if ($editMode && $editData): ?>
            window.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('dealerForm');
                const button = document.querySelector('.btn-add-dealer');
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
                window.location.href = 'dealerregistration.php?dealer_filter=' + encodeURIComponent(statusFilter);
            } else {
                // If no filter, just reload the page
                window.location.href = 'dealerregistration.php';
            }
        }

        function searchTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const table = document.querySelector('table');
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
                    // Search across dealer name, store name, contact, and address
                    const dealerName = cells[0] ? (cells[0].textContent || cells[0].innerText) : '';
                    const storeName = cells[1] ? (cells[1].textContent || cells[1].innerText) : '';
                    const contact = cells[2] ? (cells[2].textContent || cells[2].innerText) : '';
                    const address = cells[3] ? (cells[3].textContent || cells[3].innerText) : '';
                    const combinedText = (dealerName + ' ' + storeName + ' ' + contact + ' ' + address).toLowerCase();
                    searchMatch = combinedText.indexOf(searchInput) > -1;
                }

                row.style.display = searchMatch ? '' : 'none';
            }
        }

        // Initialize: Check if we have data loaded
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.querySelector('table');
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
    </script>
</body>

</html>
<?php
$conn->close();
?>