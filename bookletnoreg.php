<?php
require_once 'session_check.php';

// Authorization Check - Check if user has access to this page
if (isset($_SESSION['sidebar_access']) && $_SESSION['sidebar_access'] !== '') {
    $sidebar_hidden = array_map('trim', explode(',', $_SESSION['sidebar_access']));
    if (in_array('Booklet Number Registration', $sidebar_hidden)) {
        header("Location: report.php");
        exit();
    }
}

$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

include 'config.php';

$message = "";
$messageType = "";
$editMode = false;
$editData = null;

// Check if edit mode
if (isset($_GET['edit'])) {
    $editMode = true;
    $id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT bn.*, b.area FROM booklet_numbers bn LEFT JOIN branches b ON bn.branch_code = b.branch_code WHERE bn.id = $id");
    if ($result && $result->num_rows > 0) {
        $editData = $result->fetch_assoc();
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_booklet'])) {
    $branch_code = $conn->real_escape_string($_POST['branch_code']);
    
    // Preserve the view_branch parameter if it exists
    $redirect_param = isset($_GET['view_branch']) ? '&view_branch=' . urlencode($_GET['view_branch']) : '';

    // Check if updating or inserting
    if (isset($_POST['booklet_id']) && !empty($_POST['booklet_id'])) {
        // Update existing booklet
        $id = $conn->real_escape_string($_POST['booklet_id']);
        $booklet_no = $conn->real_escape_string($_POST['booklet_no']);
        $beginning_number = $conn->real_escape_string($_POST['beginning_number']);
        $ending_number = $conn->real_escape_string($_POST['ending_number']);
        $current_number = $conn->real_escape_string($_POST['current_number']);
        
        // Auto-detect format: numeric if purely numeric or dash-separated with a numeric end part
        $is_pure_numeric = is_numeric(ltrim($current_number, '0') ?: '0');
        $has_dashes_numeric = false;
        if (strpos($current_number, '-') !== false) {
            $parts = explode('-', $current_number);
            $last_part = end($parts);
            if (is_numeric(ltrim($last_part, '0') ?: '0')) {
                $has_dashes_numeric = true;
            }
        }
        $booklet_format = ($is_pure_numeric || $has_dashes_numeric) ? 'numeric' : 'custom';
        
        $sql = "UPDATE booklet_numbers SET 
                branch_code='$branch_code',
                booklet_no='$booklet_no',
                beginning_number='$beginning_number',
                ending_number='$ending_number',
                current_number='$current_number',
                booklet_format='$booklet_format'
                WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            header("Location: bookletnoreg.php?updated=1" . $redirect_param);
            exit();
        } else {
            $message = "Error: " . $conn->error;
            $messageType = "error";
        }
    } else {
        // Insert new booklet
        $booklet_no = $conn->real_escape_string($_POST['booklet_no']);
        $beginning_number = $conn->real_escape_string($_POST['beginning_number']);
        $ending_number = $conn->real_escape_string($_POST['ending_number']);
        $current_number = $conn->real_escape_string($_POST['current_number']);
        
        // Validate required fields
        if (empty($booklet_no) || empty($beginning_number) || empty($ending_number) || empty($current_number)) {
            $message = "Error: Please fill in all required fields";
            $messageType = "error";
        } else {
            $created_by = $_SESSION['username'] ?? 'system';
                
                // Auto-detect format: numeric if purely numeric or dash-separated with a numeric end part
                $is_pure_numeric = is_numeric(ltrim($current_number, '0') ?: '0');
                $has_dashes_numeric = false;
                if (strpos($current_number, '-') !== false) {
                    $parts = explode('-', $current_number);
                    $last_part = end($parts);
                    if (is_numeric(ltrim($last_part, '0') ?: '0')) {
                        $has_dashes_numeric = true;
                    }
                }
                $booklet_format = ($is_pure_numeric || $has_dashes_numeric) ? 'numeric' : 'custom';

                $sql = "INSERT INTO booklet_numbers (branch_code, booklet_no, beginning_number, ending_number, current_number, booklet_format, status, created_by) 
                        VALUES ('$branch_code', '$booklet_no', '$beginning_number', '$ending_number', '$current_number', '$booklet_format', 'Inactive', '$created_by')";

                if ($conn->query($sql) === TRUE) {
                    header("Location: bookletnoreg.php?success=1" . $redirect_param);
                    exit();
                } else {
                    $message = "Error: Failed to create booklet. " . $conn->error;
                    $messageType = "error";
                }
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] >= 1) {
    $count = intval($_GET['success']);
    $message = $count > 1 ? "$count booklet numbers registered successfully!" : "Booklet number registered successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            var currentUrl = window.location.pathname;
            var viewBranch = new URLSearchParams(window.location.search).get('view_branch');
            if (viewBranch) {
                currentUrl += '?view_branch=' + viewBranch;
            }
            window.history.replaceState(null, null, currentUrl);
        }
    </script>";
}

// Check for update message from redirect
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = "Booklet number updated successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            var currentUrl = window.location.pathname;
            var viewBranch = new URLSearchParams(window.location.search).get('view_branch');
            if (viewBranch) {
                currentUrl += '?view_branch=' + viewBranch;
            }
            window.history.replaceState(null, null, currentUrl);
        }
    </script>";
}

// Handle activation
if (isset($_GET['activate'])) {
    // Check if user is Super-Admin
    if (strcasecmp($system_level, 'Super-Admin') !== 0) {
        $message = "Error: Only Super-Admin can activate booklet numbers";
        $messageType = "error";
    } else {
        $id = $conn->real_escape_string($_GET['activate']);
        $used_by = $conn->real_escape_string($_SESSION['username'] ?? 'system');
        $used_date = date('Y-m-d H:i:s');
        $sql = "UPDATE booklet_numbers SET status='Active', last_used_date='$used_date', last_used_by='$used_by' WHERE id = $id";

        if ($conn->query($sql) === TRUE) {
            $message = "Booklet number activated successfully!";
            $messageType = "success";
            $redirect_param = isset($_GET['view_branch']) ? '&view_branch=' . urlencode($_GET['view_branch']) : '';
            header("Location: bookletnoreg.php?activated=1" . $redirect_param);
            exit();
        } else {
            $message = "Error: " . $conn->error;
            $messageType = "error";
        }
    }
}

// Check for activation message from redirect
if (isset($_GET['activated']) && $_GET['activated'] == 1) {
    $message = "Booklet number activated successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            var currentUrl = window.location.pathname;
            var viewBranch = new URLSearchParams(window.location.search).get('view_branch');
            if (viewBranch) {
                currentUrl += '?view_branch=' + viewBranch;
            }
            window.history.replaceState(null, null, currentUrl);
        }
    </script>";
}

// Handle deactivation
if (isset($_GET['deactivate'])) {
    // Check if user is Super-Admin
    if (strcasecmp($system_level, 'Super-Admin') !== 0) {
        $message = "Error: Only Super-Admin can deactivate booklet numbers";
        $messageType = "error";
    } else {
        $id = $conn->real_escape_string($_GET['deactivate']);
        $sql = "UPDATE booklet_numbers SET status='Inactive' WHERE id = $id";

        if ($conn->query($sql) === TRUE) {
            $message = "Booklet number deactivated successfully!";
            $messageType = "success";
            $redirect_param = isset($_GET['view_branch']) ? '?view_branch=' . urlencode($_GET['view_branch']) : '';
            header("Location: bookletnoreg.php" . $redirect_param);
            exit();
        } else {
            $message = "Error: " . $conn->error;
            $messageType = "error";
        }
    }
}

// Handle return
if (isset($_POST['return_booklet'])) {
    $id = $conn->real_escape_string($_POST['booklet_id']);
    $return_by = $conn->real_escape_string($_SESSION['username'] ?? 'system');
    $return_branch = $conn->real_escape_string($_POST['return_branch']);
    $return_date = date('Y-m-d H:i:s');
    
    $sql = "UPDATE booklet_numbers SET return_date='$return_date', return_by='$return_by', return_branch='$return_branch' WHERE id = $id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Booklet returned successfully!";
        $messageType = "success";
        $redirect_param = isset($_GET['view_branch']) ? '&view_branch=' . urlencode($_GET['view_branch']) : '';
        header("Location: bookletnoreg.php?returned=1" . $redirect_param);
        exit();
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Check for return message from redirect
if (isset($_GET['returned']) && $_GET['returned'] == 1) {
    $message = "Booklet returned successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            var currentUrl = window.location.pathname;
            var viewBranch = new URLSearchParams(window.location.search).get('view_branch');
            if (viewBranch) {
                currentUrl += '?view_branch=' + viewBranch;
            }
            window.history.replaceState(null, null, currentUrl);
        }
    </script>";
}

// Handle deletion
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);
    $sql = "DELETE FROM booklet_numbers WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $redirect_param = isset($_GET['view_branch']) ? '&view_branch=' . urlencode($_GET['view_branch']) : '';
        header("Location: bookletnoreg.php?deleted=1" . $redirect_param);
        exit();
    } else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Check for deletion message from redirect
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = "Booklet number deleted successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            var currentUrl = window.location.pathname;
            var viewBranch = new URLSearchParams(window.location.search).get('view_branch');
            if (viewBranch) {
                currentUrl += '?view_branch=' + viewBranch;
            }
            window.history.replaceState(null, null, currentUrl);
        }
    </script>";
}

// Check if viewing specific branch booklets
$view_branch = isset($_GET['view_branch']) ? $conn->real_escape_string($_GET['view_branch']) : '';

// Get user's branch access
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
$user_branch_codes = [];

// For non-Super-Admin users, determine their branch codes
if (strcasecmp($system_level, 'Super-Admin') !== 0 && !empty($user_branch)) {
    // Handle multiple branches (comma-separated)
    $branch_names = array_map('trim', explode(',', $user_branch));
    $branch_names_quoted = array_map(function($name) use ($conn) {
        return "'" . $conn->real_escape_string($name) . "'";
    }, $branch_names);
    $branch_names_in = implode(',', $branch_names_quoted);
    
    // Get branch codes for these branch names
    $branch_code_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name IN ($branch_names_in)");
    if ($branch_code_query && $branch_code_query->num_rows > 0) {
        while ($row = $branch_code_query->fetch_assoc()) {
            $user_branch_codes[] = $row['branch_code'];
        }
    }
}

// Fetch all booklet numbers (for detailed view or all)
if ($view_branch) {
    $booklets_result = $conn->query("SELECT bn.*, b.branch_name, b.area FROM booklet_numbers bn LEFT JOIN branches b ON bn.branch_code = b.branch_code WHERE bn.branch_code = '$view_branch' ORDER BY bn.id DESC");
} else {
    $booklets_result = $conn->query("SELECT bn.*, b.branch_name, b.area FROM booklet_numbers bn LEFT JOIN branches b ON bn.branch_code = b.branch_code ORDER BY bn.id DESC");
}

// Build branch access filter for Branch Summary
$branch_access_filter = '';
if (strcasecmp($system_level, 'Super-Admin') !== 0 && !empty($user_branch_codes)) {
    $codes_quoted = array_map(function($code) use ($conn) {
        return "'" . $conn->real_escape_string($code) . "'";
    }, $user_branch_codes);
    $codes_in = implode(',', $codes_quoted);
    $branch_access_filter = "AND b.branch_code IN ($codes_in)";
}

// Fetch branch summary (branch with total booklet count) - Exclude HEAD OFFICE
$branch_summary_query = "SELECT b.branch_code, b.branch_name, b.area, COUNT(bn.id) as total_booklets 
                         FROM branches b 
                         LEFT JOIN booklet_numbers bn ON b.branch_code = bn.branch_code 
                         WHERE b.status = 'Active' 
                         AND b.branch_name NOT LIKE '%HEAD OFFICE%'
                         $branch_access_filter
                         GROUP BY b.branch_code, b.branch_name, b.area 
                         ORDER BY b.branch_name ASC";
$branch_summary_result = $conn->query($branch_summary_query);

// Fetch branches for dropdown
$branches_result = $conn->query("SELECT * FROM branches WHERE status = 'Active' ORDER BY branch_name ASC");

// Fetch distinct areas for filter dropdown
$areas_result = $conn->query("SELECT DISTINCT area FROM branches WHERE status = 'Active' AND area IS NOT NULL AND area != '' ORDER BY area ASC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Booklet Number Registration</title>
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
            background-color: #f5ede0;
            color: #0d3347;
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

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 15px;
            font-weight: 600;
            color: #222;
            cursor: pointer;
            background: none;
            border: none;
            text-decoration: none;
            padding: 8px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .back-link:hover {
            background: #f5f5f5;
        }

        .back-link svg {
            width: 20px;
            height: 20px;
            fill: #222;
        }

        .content-header {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 30px;
        }

        .header-buttons-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            gap: 15px;
        }

        .date-filters {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .date-filters label {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }

        .date-filters input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: white;
        }

        .date-filters input[type="date"]:focus {
            outline: none;
            border-color: #2196F3;
        }

        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .btn-add-booklet {
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

        .btn-add-booklet:hover {
            background: var(--color-gold-light);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(176, 138, 82, 0.3);
        }

        .btn-report {
            padding: 10px 24px;
            border: none;
            background: #1a1a1aff;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }

        .btn-report:hover {
            background: #1565C0;
        }

        .btn-search {
            padding: 8px 20px;
            background: #1a1a1a;
            border: none;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            transition: background 0.2s ease;
            height: 38px;
        }

        .btn-search:hover {
            background: #333333;
        }

        .btn-hide {
            padding: 8px 20px;
            background: #d32f2f;
            border: none;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            transition: background 0.2s ease;
            height: 38px;
        }

        .btn-hide:hover {
            background: #b71c1c;
        }

        .header-buttons {
            display: flex;
            gap: 10px;
        }

        .form-container.hidden {
            display: none;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            border-left: 4px solid var(--color-gold);
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
            color: #333;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            color: #333;
            background: white;
            font-family: Arial, sans-serif;
            transition: all 0.2s ease;
        }

        .form-group textarea {
            resize: vertical;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #999;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--color-gold);
            box-shadow: 0 0 0 3px rgba(176, 138, 82, 0.1);
        }

        .form-group small {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .btn-cancel {
            padding: 12px 28px;
            border: 2px solid #ddd;
            background: white;
            color: #666;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-cancel:hover {
            background: #f5f5f5;
            border-color: #999;
            color: #333;
        }

        .btn-register {
            padding: 12px 28px;
            border: none;
            background: var(--color-gold);
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(176, 138, 82, 0.2);
        }

        .btn-register:hover {
            background: var(--color-gold-light);
            box-shadow: 0 4px 8px rgba(176, 138, 82, 0.3);
            transform: translateY(-1px);
        }

        .table-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table-container.hidden {
            display: none;
        }

        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Responsive table scrolling at 1350px */
        @media (max-width: 1350px) {
            .table-container {
                padding: 20px 15px;
            }

            .table-wrapper {
                overflow-x: auto;
                margin: 0 -15px;
                padding: 0 15px;
            }

            table {
                min-width: 1400px;
            }

            /* Smaller font size for table cells on mobile */
            th, td {
                padding: 10px 8px;
                font-size: 12px;
            }

            /* Adjust form container padding */
            .form-container {
                padding: 20px;
            }

            /* Stack filter buttons vertically */
            .header-buttons-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .date-filters {
                width: 100%;
                justify-content: flex-start;
                margin-left: 0;
            }

            /* Custom scrollbar styling */
            .table-wrapper::-webkit-scrollbar {
                height: 8px;
            }

            .table-wrapper::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }

            .table-wrapper::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 4px;
            }

            .table-wrapper::-webkit-scrollbar-thumb:hover {
                background: #555;
            }
        }

        .no-search-message {
            background: white;
            padding: 60px 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .no-search-message h3 {
            font-size: 18px;
            color: #666;
            margin-bottom: 10px;
        }

        .no-search-message p {
            font-size: 14px;
            color: #999;
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
            width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            height: 38px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #1a1a1a;
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
            white-space: nowrap;
        }

        tbody tr:hover {
            background: #fafafa;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.active {
            background: #4CAF50;
            color: white;
        }

        .status-badge.inactive {
            background: #757575;
            color: white;
        }

        .status-badge.completed {
            background: #dc3545;
            color: white;
            font-weight: 600;
        }

        .status-badge.returned {
            background: #90a4ae;
            color: white;
            font-weight: 600;
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
            background: var(--color-gold);
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
            background: var(--color-gold-light);
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

        .btn-return {
            padding: 6px 16px;
            border: none;
            background: #ff9800;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-left: 5px;
            display: none;
        }

        .btn-return:hover {
            background: #f57c00;
        }

        .btn-returned {
            padding: 6px 16px;
            border: none;
            background: #4caf50;
            color: white;
            border-radius: 4px;
            cursor: not-allowed;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-left: 5px;
            display: none;
        }

        /* Default: Hide icons, show text */
        .btn-icon {
            display: none;
        }

        .btn-text {
            display: inline;
        }

        /* Responsive: Show icons instead of text for buttons at 1661px and below */
        @media (max-width: 1661px) {
            .btn-edit .btn-text,
            .btn-activate .btn-text,
            .btn-deactivate .btn-text,
            .btn-delete .btn-text,
            .btn-return .btn-text {
                display: none;
            }

            .btn-edit .btn-icon,
            .btn-activate .btn-icon,
            .btn-deactivate .btn-icon,
            .btn-delete .btn-icon,
            .btn-return .btn-icon {
                display: inline-block;
            }

            .btn-edit,
            .btn-activate,
            .btn-deactivate,
            .btn-delete,
            .btn-return {
                padding: 8px;
                min-width: 36px;
                margin: 2px;
            }

            /* Adjust action column to fit icon buttons */
            td:last-child {
                white-space: nowrap;
                padding: 8px;
            }
        }

        .btn-icon {
            width: 16px;
            height: 16px;
            fill: white;
            vertical-align: middle;
            display: none;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ff9800;
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #ff9800;
            margin: 0;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            color: #666;
            cursor: pointer;
            line-height: 20px;
        }

        .close:hover {
            color: #333;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-body p {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .modal-body .highlight {
            font-weight: 600;
            color: #ff9800;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-modal-cancel {
            padding: 10px 24px;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-modal-cancel:hover {
            background: #f5f5f5;
        }

        .btn-modal-confirm {
            padding: 10px 24px;
            border: none;
            background: #ff9800;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-modal-confirm:hover {
            background: #f57c00;
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

        .format-preview {
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 14px;
            color: #333;
        }

        /* Multi-select dropdown with checkboxes */
        .multi-select-dropdown {
            position: relative;
            width: 100%;
        }

        .multi-select-header {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
        }

        .multi-select-header:hover {
            border-color: #2196F3;
        }

        .multi-select-header.active {
            border-color: #2196F3;
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }

        .dropdown-arrow {
            font-size: 10px;
            transition: transform 0.3s;
        }

        .multi-select-header.active .dropdown-arrow {
            transform: rotate(180deg);
        }

        .multi-select-options {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #2196F3;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .multi-select-options.active {
            display: block;
        }

        .checkbox-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            cursor: pointer;
            transition: background-color 0.2s;
            user-select: none;
        }

        .checkbox-option:hover {
            background-color: #f5f5f5;
        }

        .checkbox-option input[type="checkbox"] {
            cursor: pointer;
            width: 16px;
            height: 16px;
            margin: 0;
        }

        .checkbox-option span {
            font-size: 14px;
            color: #333;
        }

        #selected-page-types-display {
            color: #333;
        }

        #selected-page-types-display.placeholder {
            color: #999;
        }

        .page-type-config-section {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fafafa;
        }

        .page-type-config-section h4 {
            font-size: 15px;
            font-weight: 600;
            color: #2e7d32;
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #2e7d32;
        }

        /* Responsive styles for medium screens (1229px and below) */
        @media (max-width: 1229px) {
            .header-buttons-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .date-filters {
                margin-left: 0;
                flex-wrap: wrap;
                width: 100%;
            }

            .btn-add-booklet,
            .btn-report {
                width: 100%;
                text-align: center;
            }

            .btn-cancel {
                width: 100%;
                text-align: center;
                display: block !important;
            }

            .header-buttons {
                width: 100%;
                flex-direction: column;
            }

            .btn-search,
            .btn-hide {
                width: 100%;
            }

            /* Fix search controls section */
            #statusFilter {
                width: 100% !important;
                min-width: 100% !important;
            }

            .search-box {
                width: 100% !important;
            }

            .search-box input {
                width: 100% !important;
            }

            /* Make the search controls wrapper stack properly */
            div[style*="display: flex"][style*="gap: 5px"] {
                width: 100% !important;
                flex-direction: column !important;
                gap: 10px !important;
            }

            /* Parent container for filters */
            div[style*="display: flex"][style*="gap: 10px"][style*="flex-wrap: wrap"] {
                flex-direction: column !important;
            }

            /* Fix Branch Summary filters */
            #summaryAreaFilter,
            #summaryBranchFilter {
                width: 100% !important;
                min-width: 100% !important;
            }

            #summarySearchInput {
                width: 100% !important;
            }
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

            .date-filters input[type="date"] {
                width: 100%;
            }

            .date-filters label {
                width: 100%;
            }

            /* Additional fixes for very small screens */
            #statusFilter {
                font-size: 14px !important;
                padding: 10px 12px !important;
            }

            .btn-search,
            .btn-hide {
                font-size: 14px !important;
                padding: 10px 20px !important;
                height: auto !important;
            }

            /* Branch Summary filters on mobile */
            #summaryAreaFilter,
            #summaryBranchFilter {
                font-size: 14px !important;
                padding: 10px 12px !important;
            }

            #summarySearchInput {
                font-size: 14px !important;
                padding: 10px 12px !important;
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
        <?php include '_header_user.php'; ?>
    </div>

    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div style="display: flex; align-items: center; gap: 0px;">
                <?php if ($view_branch): ?>
                <a class="back-link" href="bookletnoreg.php">
                    <svg viewBox="0 0 24 24">
                        <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z" />
                    </svg>
                </a>
                <?php endif; ?>
                <h2>Booklet Number Registration</h2>
            </div>
            <div class="header-buttons-row">
                <button type="button" class="btn-add-booklet" onclick="toggleForm()">Add Booklet Number</button>
                <div class="date-filters">
                    <label>Date From:</label>
                    <input type="date" id="dateFrom" name="date_from">
                    <label>Date To:</label>
                    <input type="date" id="dateTo" name="date_to">
                </div>
                <a href="#" class="btn-report" onclick="openBookletReport(); return false;">Booklet     Report</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="form-container <?php echo $editMode ? '' : 'hidden'; ?>" id="bookletForm">
            <input type="hidden" name="booklet_id" id="booklet_id"
                value="<?php echo $editMode && $editData ? $editData['id'] : ''; ?>">
            
            <h3 style="margin-bottom: 25px; color: var(--color-gold); font-size: 18px; font-weight: 600; border-bottom: 2px solid var(--color-gold); padding-bottom: 10px;">
                <?php echo $editMode ? 'Edit Booklet Number' : 'Add Booklet Number'; ?>
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Branch <span style="color: red;">*</span></label>
                    <select name="branch_code" id="branch_code" required onchange="updateBranchArea()">
                        <option value="">Select branch</option>
                        <?php
                        if ($branches_result && $branches_result->num_rows > 0) {
                            $branches_result->data_seek(0);
                            while ($branch = $branches_result->fetch_assoc()) {
                                $selected = ($editMode && $editData && $editData['branch_code'] == $branch['branch_code']) ? 'selected' : '';
                                $area = isset($branch['area']) ? htmlspecialchars($branch['area']) : '';
                                echo "<option value='" . htmlspecialchars($branch['branch_code']) . "' data-area='$area' " . $selected . ">" . htmlspecialchars($branch['branch_name']) . " (" . htmlspecialchars($branch['branch_code']) . ")</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Area</label>
                    <input type="text" name="area" id="area" placeholder="Auto-filled from branch" readonly 
                        value="<?php echo $editMode && $editData ? htmlspecialchars($editData['area'] ?? '') : ''; ?>"
                        style="background-color: #f5f5f5; cursor: not-allowed;">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group full-width">
                    <label>Booklet Number <span style="color: red;">*</span></label>
                    <input type="text" name="booklet_no" id="booklet_no" placeholder="Enter booklet number (e.g., 1, 2, 3)" 
                        value="<?php echo $editMode && $editData ? htmlspecialchars($editData['booklet_no'] ?? '') : ''; ?>"
                        required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Beginning Number <span style="color: red;">*</span></label>
                    <input type="text" name="beginning_number" id="beginning_number" placeholder="e.g., 0001 or 0003128-092-001" 
                        value="<?php echo $editMode && $editData ? htmlspecialchars($editData['beginning_number'] ?? '') : ''; ?>"
                        required onkeyup="syncCurrentNumber()">
                </div>
                <div class="form-group">
                    <label>Ending Number <span style="color: red;">*</span></label>
                    <input type="text" name="ending_number" id="ending_number" placeholder="e.g., 0050 or 0003128-092-050" 
                        value="<?php echo $editMode && $editData ? htmlspecialchars($editData['ending_number'] ?? '') : ''; ?>"
                        required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group full-width">
                    <label>Current Number <span style="color: red;">*</span></label>
                    <input type="text" name="current_number" id="current_number" placeholder="Auto-synced with Beginning Number" 
                        value="<?php echo $editMode && $editData ? htmlspecialchars($editData['current_number'] ?? '') : ''; ?>"
                        required>
                    <small style="color: #666; font-size: 12px; margin-top: 5px; display: block;">
                        This will be auto-filled with the Beginning Number. Format supports numeric (0001) or custom (0003128-092-001).
                    </small>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="toggleForm()">Cancel</button>
                <button type="submit" name="register_booklet" class="btn-register">
                    <?php echo $editMode ? 'Update Booklet' : 'Add Booklet'; ?>
                </button>
            </div>
        </form>

        <?php if (!$view_branch): ?>
        <!-- Branch Summary Filters -->
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); margin-bottom: 20px;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <select id="summaryAreaFilter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer; min-width: 150px;" onchange="filterBranchSummary()">
                    <option value="">All Areas</option>
                    <?php
                    if ($areas_result && $areas_result->num_rows > 0) {
                        $areas_result->data_seek(0);
                        while ($area_row = $areas_result->fetch_assoc()) {
                            $area_value = htmlspecialchars($area_row['area']);
                            echo "<option value='" . $area_value . "'>" . $area_value . "</option>";
                        }
                    }
                    ?>
                </select>
                <select id="summaryBranchFilter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer; min-width: 200px;" onchange="filterBranchSummary()">
                    <option value="">All Branches</option>
                    <?php
                    if ($branches_result && $branches_result->num_rows > 0) {
                        $branches_result->data_seek(0);
                        while ($branch = $branches_result->fetch_assoc()) {
                            // Exclude HEAD OFFICE branches
                            if (stripos($branch['branch_name'], 'HEAD OFFICE') === false) {
                                echo "<option value='" . htmlspecialchars($branch['branch_code']) . "'>" . htmlspecialchars($branch['branch_name']) . "</option>";
                            }
                        }
                    }
                    ?>
                </select>
                <div class="search-box">
                    <input type="text" id="summarySearchInput" placeholder="Search Branch..." onkeyup="filterBranchSummary()" style="width: 250px;">
                </div>
            </div>
        </div>

        <!-- Branch Summary Table -->
        <div class="table-container" style="margin-bottom: 30px;">
            <div class="table-header">
                <h3>Branch Summary</h3>
            </div>
            <div class="table-wrapper">
                <table id="branchSummaryTable">
                <thead>
                    <tr>
                        <th style="text-align: left;">Branch Name</th>
                        <th>Branch Code</th>
                        <th>Area</th>
                        <th>Total Booklets</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($branch_summary_result && $branch_summary_result->num_rows > 0) {
                        while ($branch = $branch_summary_result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td style='text-align: left;'>" . htmlspecialchars($branch['branch_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($branch['branch_code']) . "</td>";
                            echo "<td>" . htmlspecialchars($branch['area'] ?? '-') . "</td>";
                            echo "<td>" . htmlspecialchars($branch['total_booklets']) . "</td>";
                            echo "<td>";
                            echo "<a href='bookletnoreg.php?view_branch=" . urlencode($branch['branch_code']) . "' class='btn-edit' style='background: #1976D2; text-decoration: none;' title='View Booklets'>";
                            echo "<svg class='btn-icon' viewBox='0 0 24 24'><path d='M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z'/></svg>";
                            echo "<span class='btn-text'>View</span>";
                            echo "</a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align: center; padding: 40px; color: #666;'>No branches found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($view_branch): ?>

        <!-- Filters Section - Always Visible -->
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); margin-bottom: 20px;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <select id="statusFilter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer; min-width: 150px;">
                    <option value="" selected>Select Status</option>
                    <option value="ALL">All Status</option>
                    <option value="Active">Use</option>
                    <option value="Completed">Complete</option>
                    <option value="Returned">Return</option>
                    <option value="Inactive">Inactive</option>
                </select>
                <div style="display: flex; gap: 5px; align-items: center;">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search...">
                    </div>
                    <button onclick="applyFilters()" class="btn-search">
                        Search
                    </button>
                    <button onclick="hideList()" class="btn-hide" style="padding: 8px 20px; background: #d32f2f; border: none; color: white; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; white-space: nowrap; transition: background 0.2s ease; height: 38px;">
                        Hide
                    </button>
                </div>
            </div>
        </div>

        <div class="no-search-message" id="noSearchMessage" style="<?php echo $view_branch ? 'display: none;' : ''; ?>">
            <svg viewBox="0 0 24 24" style="width: 80px; height: 80px; fill: #ccc; margin-bottom: 20px;">
                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
            </svg>
            <h3>Please Select Filters and Click Search</h3>
            <p>Choose your filters from the dropdowns above and click the Search button to view booklet numbers.</p>
        </div>

        <div class="table-container <?php echo $view_branch ? '' : 'hidden'; ?>" id="tableContainer">
            <div class="table-header">
                <h3>
                    <?php 
                    if ($view_branch && $booklets_result && $booklets_result->num_rows > 0) {
                        $first_row = $booklets_result->fetch_assoc();
                        echo "Booklet Numbers - " . htmlspecialchars($first_row['branch_name']) . " (" . htmlspecialchars($view_branch) . ")";
                        $booklets_result->data_seek(0); // Reset pointer
                    } else {
                        echo "Booklet Numbers";
                    }
                    ?>
                </h3>
            </div>
            <div class="table-wrapper">
                <table id="bookletTable">
                <thead>
                    <tr>
                        <th>Branch Name</th>
                        <th>Branch Code</th>
                        <th>Area</th>
                        <th>Booklet Number</th>
                        <th>Beginning Number</th>
                        <th>Ending Number</th>
                        <th>Current Number</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Date Created</th>
                        <th>Date Used</th>
                        <th>Used By</th>
                        <th>Complete Date</th>
                        <th>Return Date</th>
                        <th>Return By</th>
                        <th>Return Branch</th>
                        <th>Transfer Date</th>
                        <th>Transfer By</th>
                        <th>Transfer From</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($booklets_result && $booklets_result->num_rows > 0) {
                        while ($row = $booklets_result->fetch_assoc()) {
                            $status = htmlspecialchars($row['status']);
                            $status_class = strtolower($status);
                            $is_completed = !empty($row['complete_date']);
                            $is_returned = !empty($row['return_date']);
                            
                            // Override status display for returned/completed booklets
                            if ($is_returned) {
                                $display_status = 'Returned';
                                $display_status_class = 'returned';
                            } elseif ($is_completed) {
                                $display_status = 'Completed';
                                $display_status_class = 'completed';
                            } else {
                                $display_status = $status;
                                $display_status_class = $status_class;
                            }
                            
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['branch_name'] ?? 'N/A') . "</td>";
                            echo "<td>" . htmlspecialchars($row['branch_code']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['area'] ?? '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['booklet_no'] ?? '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['beginning_number'] ?? '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['ending_number'] ?? '-') . "</td>";
                            echo "<td style='font-weight: 600;'>" . htmlspecialchars($row['current_number']) . "</td>";
                            
                            echo "<td><span class='status-badge " . $display_status_class . "'>" . $display_status . "</span></td>";
                            echo "<td>" . htmlspecialchars($row['created_by'] ?? '-') . "</td>";
                            echo "<td>" . (isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) . '<br>' . date('h:i A', strtotime($row['created_at'])) : '-') . "</td>";
                            echo "<td>" . (isset($row['last_used_date']) && !empty($row['last_used_date']) ? date('M d, Y', strtotime($row['last_used_date'])) . '<br>' . date('h:i A', strtotime($row['last_used_date'])) : '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['last_used_by'] ?? '-') . "</td>";
                            echo "<td style='" . (isset($row['complete_date']) && !empty($row['complete_date']) ? "color: #c62828; font-weight: 600;" : "") . "'>" . (isset($row['complete_date']) && !empty($row['complete_date']) ? date('M d, Y', strtotime($row['complete_date'])) . '<br>' . date('h:i A', strtotime($row['complete_date'])) : '-') . "</td>";
                            echo "<td style='" . ($is_returned ? "color: #2e7d32; font-weight: 600;" : "") . "'>" . ($is_returned ? date('M d, Y', strtotime($row['return_date'])) . '<br>' . date('h:i A', strtotime($row['return_date'])) : '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['return_by'] ?? '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['return_branch'] ?? '-') . "</td>";
                            
                            // Transfer columns
                            $is_transferred = !empty($row['transfer_date']);
                            echo "<td style='" . ($is_transferred ? "color: #2196F3; font-weight: 600;" : "") . "'>" . ($is_transferred ? date('M d, Y', strtotime($row['transfer_date'])) . '<br>' . date('h:i A', strtotime($row['transfer_date'])) : '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['transfer_by'] ?? '-') . "</td>";
                            echo "<td>" . htmlspecialchars($row['transfer_from_branch'] ?? '-') . "</td>";
                            
                            echo "<td>";
                            
                            // Build URL parameter for view_branch if it exists
                            $url_params = $view_branch ? '&view_branch=' . urlencode($view_branch) : '';
                            
                            // Only show Edit button if booklet is NOT returned
                            if (!$is_returned) {
                                echo "<a href='bookletnoreg.php?edit=" . $row['id'] . $url_params . "' class='btn-edit' title='Edit Booklet'>";
                                echo "<svg class='btn-icon' viewBox='0 0 24 24'><path d='M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z'/></svg>";
                                echo "<span class='btn-text'>Edit</span>";
                                echo "</a>";
                            }
                            
                            if ($is_completed) {
                                // Show Return button for completed booklets
                                if ($is_returned) {
                                    echo "<button class='btn-returned' disabled title='Already Returned'>Returned</button>";
                                } else {
                                    echo "<button class='btn-return' onclick='showReturnModal(" . $row['id'] . ", \"" . htmlspecialchars($row['booklet_no']) . "\")' title='Return Booklet' style='display=none'>";
                                    echo "<svg class='btn-icon' viewBox='0 0 24 24'><path d='M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z'/></svg>";
                                    echo "<span class='btn-text'>Return</span>";
                                    echo "</button>";
                                }
                            } else {
                                // Show Activate/Deactivate for non-completed booklets (only if not returned)
                                if (!$is_returned) {
                                    // Only Super-Admin can see Activate/Deactivate buttons
                                    if (strcasecmp($system_level, 'Super-Admin') === 0) {
                                        if ($status == 'Active') {
                                            echo "<a href='bookletnoreg.php?deactivate=" . $row['id'] . $url_params . "' class='btn-deactivate' onclick='return confirm(\"Are you sure you want to deactivate this booklet number?\")' title='Deactivate Booklet'>";
                                            echo "<svg class='btn-icon' viewBox='0 0 24 24'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm-5-9h10v2H7z'/></svg>";
                                            echo "<span class='btn-text'>Deactivate</span>";
                                            echo "</a>";
                                        } else {
                                            echo "<a href='bookletnoreg.php?activate=" . $row['id'] . $url_params . "' class='btn-activate' title='Activate Booklet'>";
                                            echo "<svg class='btn-icon' viewBox='0 0 24 24'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z'/></svg>";
                                            echo "<span class='btn-text'>Activate</span>";
                                            echo "</a>";
                                        }
                                    }
                                    echo "<a href='bookletnoreg.php?delete=" . $row['id'] . $url_params . "' class='btn-delete' onclick='return confirm(\"Are you sure you want to delete this booklet number? This action cannot be undone.\")' title='Delete Booklet'>";
                                    echo "<svg class='btn-icon' viewBox='0 0 24 24'><path d='M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z'/></svg>";
                                    echo "<span class='btn-text'>Delete</span>";
                                    echo "</a>";
                                }
                            }
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='19' style='text-align: center;'>No booklet numbers found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Save filter state to localStorage when search is clicked
        function saveFilterState() {
            const statusFilter = document.getElementById('statusFilter');
            const searchInput = document.getElementById('searchInput');
            
            const filterState = {
                status: statusFilter ? statusFilter.value : '',
                search: searchInput ? searchInput.value : '',
                tableVisible: true
            };
            localStorage.setItem('bookletFilterState', JSON.stringify(filterState));
        }

        // Restore filter state on page load
        function restoreFilterState() {
            const savedState = localStorage.getItem('bookletFilterState');
            if (savedState) {
                const filterState = JSON.parse(savedState);
                const statusFilter = document.getElementById('statusFilter');
                const searchInput = document.getElementById('searchInput');
                
                if (statusFilter) statusFilter.value = filterState.status || '';
                if (searchInput) searchInput.value = filterState.search || '';
                
                // If table was visible, show it and apply filters
                if (filterState.tableVisible) {
                    const tableContainer = document.getElementById('tableContainer');
                    const noSearchMessage = document.getElementById('noSearchMessage');
                    if (tableContainer) tableContainer.classList.remove('hidden');
                    if (noSearchMessage) noSearchMessage.style.display = 'none';
                    applyFiltersWithoutSaving();
                }
            }
        }

        // Hide the list/table
        function hideList() {
            // Hide table and show message
            document.getElementById('tableContainer').classList.add('hidden');
            document.getElementById('noSearchMessage').style.display = 'block';
            
            // Save the hidden state (keep filter values but mark table as not visible)
            const statusFilter = document.getElementById('statusFilter');
            const searchInput = document.getElementById('searchInput');
            
            const filterState = {
                status: '',
                search: '',
                tableVisible: false
            };
            localStorage.setItem('bookletFilterState', JSON.stringify(filterState));
            
            // Reset filters
            if (statusFilter) statusFilter.value = '';
            if (searchInput) searchInput.value = '';
        }

        // Apply filters without saving (used on restore)
        function applyFiltersWithoutSaving() {
            const searchInput = document.getElementById('searchInput').value.toUpperCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const table = document.getElementById('bookletTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                
                if (td.length === 0) continue;
                
                const branchName = (td[0]?.textContent || '').toUpperCase();
                const branchCode = (td[1]?.textContent || '').toUpperCase();
                const areaText = (td[2]?.textContent || '').toUpperCase();
                const bookletNo = (td[3]?.textContent || '').toUpperCase();
                const statusText = (td[7]?.textContent || '').trim();
                const returnDate = (td[13]?.textContent || '').trim();
                
                let statusMatch = true;
                if (statusFilter !== '' && statusFilter !== 'ALL') {
                    if (statusFilter === 'Returned') {
                        const hasReturnDate = returnDate && returnDate.trim() !== '' && returnDate.trim() !== '-';
                        statusMatch = hasReturnDate;
                    } else if (statusFilter === 'Completed') {
                        const hasReturnDate = returnDate && returnDate.trim() !== '' && returnDate.trim() !== '-';
                        statusMatch = statusText === 'Completed' && !hasReturnDate;
                    } else {
                        statusMatch = statusText === statusFilter;
                    }
                }
                
                let searchMatch = true;
                if (searchInput !== '') {
                    searchMatch = false;
                    for (let j = 0; j < td.length; j++) {
                        const txtValue = (td[j].textContent || td[j].innerText).toUpperCase();
                        if (txtValue.indexOf(searchInput) > -1) {
                            searchMatch = true;
                            break;
                        }
                    }
                }
                
                tr[i].style.display = (statusMatch && searchMatch) ? '' : 'none';
            }
        }

        // Load saved state when page loads
        window.addEventListener('DOMContentLoaded', function() {
            <?php if ($view_branch): ?>
            // Restore filter state if it exists (user clicked Search before)
            const savedState = localStorage.getItem('bookletFilterState');
            if (savedState) {
                const filterState = JSON.parse(savedState);
                const statusFilter = document.getElementById('statusFilter');
                const searchInput = document.getElementById('searchInput');
                
                if (statusFilter) statusFilter.value = filterState.status || '';
                if (searchInput) searchInput.value = filterState.search || '';
                
                // If table was visible (user had clicked Search), show it and apply filters
                if (filterState.tableVisible) {
                    const tableContainer = document.getElementById('tableContainer');
                    const noSearchMessage = document.getElementById('noSearchMessage');
                    if (tableContainer) tableContainer.classList.remove('hidden');
                    if (noSearchMessage) noSearchMessage.style.display = 'none';
                    applyFiltersWithoutSaving();
                } else {
                    // State was explicitly hidden by user clicking Hide button
                    document.getElementById('tableContainer').classList.add('hidden');
                    document.getElementById('noSearchMessage').style.display = 'block';
                }
            } else {
                // No saved state - first time visiting, start with hidden table
                document.getElementById('tableContainer').classList.add('hidden');
                document.getElementById('noSearchMessage').style.display = 'block';
            }
            <?php else: ?>
            restoreFilterState();
            <?php endif; ?>
        });

        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn = document.querySelector('.menu-btn');

            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
            menuBtn.classList.toggle('active');
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

        // Filter branch summary table
        function filterBranchSummary() {
            const areaFilter = document.getElementById('summaryAreaFilter').value.toUpperCase();
            const branchFilter = document.getElementById('summaryBranchFilter').value.toUpperCase();
            const searchInput = document.getElementById('summarySearchInput').value.toUpperCase();
            const table = document.getElementById('branchSummaryTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                
                if (td.length === 0) continue;
                
                const branchName = (td[0]?.textContent || '').toUpperCase();
                const branchCode = (td[1]?.textContent || '').toUpperCase();
                const areaText = (td[2]?.textContent || '').toUpperCase();
                
                let areaMatch = true;
                if (areaFilter !== '') {
                    areaMatch = areaText.includes(areaFilter);
                }
                
                let branchMatch = true;
                if (branchFilter !== '') {
                    branchMatch = branchCode === branchFilter;
                }
                
                let searchMatch = true;
                if (searchInput !== '') {
                    searchMatch = branchName.includes(searchInput) || branchCode.includes(searchInput);
                }
                
                tr[i].style.display = (areaMatch && branchMatch && searchMatch) ? '' : 'none';
            }
        }

        function updateBranchArea() {
            const branchSelect = document.getElementById('branch_code');
            const areaInput = document.getElementById('area');
            const selectedOption = branchSelect.options[branchSelect.selectedIndex];
            const area = selectedOption.getAttribute('data-area') || '';
            areaInput.value = area;
        }

        function toggleForm() {
            // Check if we're in edit mode (URL has edit parameter)
            const urlParams = new URLSearchParams(window.location.search);
            const isEditMode = urlParams.has('edit');
            
            if (isEditMode) {
                // If in edit mode, redirect to clean URL (exit edit mode)
                window.location.href = 'bookletnoreg.php';
            } else {
                // If not in edit mode, just toggle the form
                const form = document.getElementById('bookletForm');
                form.classList.toggle('hidden');
                
                if (!form.classList.contains('hidden')) {
                    updatePreview();
                }
            }
        }

        function syncCurrentNumber() {
            const editMode = <?php echo $editMode ? 'true' : 'false'; ?>;
            if (!editMode) {
                const beginningNumber = document.getElementById('beginning_number').value;
                document.getElementById('current_number').value = beginningNumber;
            }
        }

        function applyFilters() {
            // Get filter values
            const statusFilter = document.getElementById('statusFilter') ? document.getElementById('statusFilter').value : '';
            const searchInput = document.getElementById('searchInput') ? document.getElementById('searchInput').value : '';
            
            // Check if at least one filter is selected (not empty and not just search)
            const hasValidFilter = statusFilter !== '';
            
            if (!hasValidFilter && searchInput.trim() === '') {
                // Show alert if no filters are selected
                alert('Please select Status or enter search text before searching.');
                return;
            }
            
            // Save filter state
            saveFilterState();
            
            // Show table and hide no-search message
            const tableContainer = document.getElementById('tableContainer');
            const noSearchMessage = document.getElementById('noSearchMessage');
            if (tableContainer) tableContainer.classList.remove('hidden');
            if (noSearchMessage) noSearchMessage.style.display = 'none';
            
            // Apply filters
            applyFiltersWithoutSaving();
        }

        function openBookletReport() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            
            // Build URL with date parameters if provided
            let url = 'print_booklet_report.php';
            const params = new URLSearchParams();
            
            // Branch is required - set to ALL
            params.append('branch', 'ALL');
            
            if (dateFrom) {
                params.append('date_from', dateFrom);
            }
            if (dateTo) {
                params.append('date_to', dateTo);
            }
            
            if (params.toString()) {
                url += '?' + params.toString();
            }
            
            // Open PDF in new window
            window.open(url, 'BookletReport', 'width=1200,height=800,scrollbars=yes,resizable=yes');
        }

        // Return modal functions
        function showReturnModal(bookletId, bookletNo) {
            document.getElementById('returnBookletId').value = bookletId;
            document.getElementById('returnBookletNo').textContent = bookletNo;
            document.getElementById('returnModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const returnModal = document.getElementById('returnModal');
            if (event.target == returnModal) {
                returnModal.style.display = 'none';
            }
        }

        // Initialize form if in edit mode
        <?php if ($editMode): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Scroll to form
            document.getElementById('bookletForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        <?php endif; ?>
    </script>

    <!-- Return Modal -->
    <div id="returnModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Return Booklet</h3>
                <span class="close" onclick="closeModal('returnModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Return booklet <span class="highlight" id="returnBookletNo"></span> to branch:</p>
                <form method="POST" action="" id="returnForm">
                    <input type="hidden" name="booklet_id" id="returnBookletId">
                    <div style="margin: 20px 0;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #666;">Select Branch:</label>
                        <select name="return_branch" id="returnBranchSelect" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                            <option value="">-- Select Branch --</option>
                            <?php
                            // Always show HEAD OFFICE first
                            $head_office_query = $conn->query("SELECT * FROM branches WHERE branch_name = 'HEAD OFFICE' AND status = 'Active' LIMIT 1");
                            if ($head_office_query && $head_office_query->num_rows > 0) {
                                $head_office = $head_office_query->fetch_assoc();
                                echo "<option value='" . htmlspecialchars($head_office['branch_code']) . "' style='font-weight: 600; background: #e8f5e9;'>" . htmlspecialchars($head_office['branch_name']) . " (" . htmlspecialchars($head_office['branch_code']) . ")</option>";
                            }
                            
                            // Then show other branches
                            if ($branches_result && $branches_result->num_rows > 0) {
                                $branches_result->data_seek(0);
                                while ($branch = $branches_result->fetch_assoc()) {
                                    // Skip HEAD OFFICE if already shown
                                    if ($branch['branch_name'] != 'HEAD OFFICE') {
                                        echo "<option value='" . htmlspecialchars($branch['branch_code']) . "'>" . htmlspecialchars($branch['branch_name']) . " (" . htmlspecialchars($branch['branch_code']) . ")</option>";
                                    }
                                }
                            }
                            ?>
                        </select>
                    </div>
                </form>
                <p style="margin-top: 15px;"><strong>Note:</strong> This will record the return date, your username, and the selected branch.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('returnModal')">Cancel</button>
                <button type="submit" form="returnForm" name="return_booklet" class="btn-modal-confirm">Return Booklet</button>
            </div>
        </div>
    </div>
</body>
</html>
