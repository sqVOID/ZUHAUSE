<?php
require_once 'session_check.php';

// Authorization Check
if (isset($_SESSION['sidebar_access']) && $_SESSION['sidebar_access'] !== '') {
    $sidebar_hidden = array_map('trim', explode(',', $_SESSION['sidebar_access']));
    if (in_array('Booklet Invoice Live', $sidebar_hidden)) {
        header("Location: report.php");
        exit();
    }
}

$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

include 'config.php';

$message = "";
$messageType = "";

// Check for cancellation messages
if (isset($_SESSION['cancel_success'])) {
    $message = $_SESSION['cancel_success'];
    $messageType = "success";
    unset($_SESSION['cancel_success']);
} elseif (isset($_SESSION['cancel_error'])) {
    $message = $_SESSION['cancel_error'];
    $messageType = "error";
    unset($_SESSION['cancel_error']);
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
    $branch_names_quoted = array_map(function ($name) use ($conn) {
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

// Fetch all booklet invoice numbers (for detailed view or all)
if ($view_branch) {
    $booklets_result = $conn->query("SELECT bn.*, b.branch_name, b.area FROM booklet_numbers bn LEFT JOIN branches b ON bn.branch_code = b.branch_code WHERE bn.branch_code = '$view_branch' ORDER BY bn.id DESC");
} else {
    $booklets_result = $conn->query("SELECT bn.*, b.branch_name, b.area FROM booklet_numbers bn LEFT JOIN branches b ON bn.branch_code = b.branch_code ORDER BY bn.id DESC");
}

// Build branch access filter for Branch Summary
$branch_access_filter = '';
if (strcasecmp($system_level, 'Super-Admin') !== 0 && !empty($user_branch_codes)) {
    $codes_quoted = array_map(function ($code) use ($conn) {
        return "'" . $conn->real_escape_string($code) . "'";
    }, $user_branch_codes);
    $codes_in = implode(',', $codes_quoted);
    $branch_access_filter = "AND b.branch_code IN ($codes_in)";
}

// Fetch branch summary - Exclude HEAD OFFICE
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
    <title>Booklet Invoice Live</title>
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

        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
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

        .status-badge.cancelled {
            background: #dc3545;
            color: white;
            font-weight: 600;
        }

        .status-badge.completed {
            background: #dc3545;
            color: white;
            font-weight: 600;
        }

        .status-badge.returned {
            background: #ff9800;
            color: white;
            font-weight: 600;
        }

        .status-badge.inactive {
            background: #757575;
            color: white;
        }

        .btn-view {
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
        }

        .btn-view:hover {
            background: #1565C0;
        }

        .btn-cancel {
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

        .btn-cancel:hover {
            background: #b71c1c;
        }

        .btn-cancelled {
            padding: 6px 16px;
            border: none;
            background: #757575;
            color: white;
            border-radius: 4px;
            cursor: not-allowed;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-left: 5px;
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-content {
            background-color: white;
            margin: 8% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            border-bottom: 2px solid #d32f2f;
            background: #fff5f5;
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #d32f2f;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 svg {
            width: 24px;
            height: 24px;
            fill: #d32f2f;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            color: #666;
            cursor: pointer;
            line-height: 20px;
            transition: color 0.2s;
        }

        .close:hover {
            color: #d32f2f;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-group label .required {
            color: #d32f2f;
            margin-left: 3px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            color: #333;
            font-family: Arial, sans-serif;
            transition: all 0.2s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #d32f2f;
            box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group small {
            display: block;
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }

        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .info-box p {
            margin: 0;
            font-size: 13px;
            color: #856404;
            line-height: 1.5;
        }

        .info-box strong {
            color: #d32f2f;
            font-weight: 600;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px 30px;
            border-top: 1px solid #e0e0e0;
            background: #fafafa;
        }

        .btn-modal-cancel {
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

        .btn-modal-cancel:hover {
            background: #f5f5f5;
            border-color: #999;
            color: #333;
        }

        .btn-modal-confirm {
            padding: 12px 28px;
            border: none;
            background: #d32f2f;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(211, 47, 47, 0.2);
        }

        .btn-modal-confirm:hover {
            background: #b71c1c;
            box-shadow: 0 4px 8px rgba(211, 47, 47, 0.3);
            transform: translateY(-1px);
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

        /* Responsive table scrolling */
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
                min-width: 1200px;
            }

            th,
            td {
                padding: 10px 8px;
                font-size: 12px;
            }

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

            .header {
                padding: 0 15px;
                gap: 15px;
            }

            .content-header h2 {
                font-size: 18px;
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
                    <a class="back-link" href="bookletinvlive.php">
                        <svg viewBox="0 0 24 24">
                            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z" />
                        </svg>
                    </a>
                <?php endif; ?>
                <h2>Booklet Invoice Live</h2>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (!$view_branch): ?>
            <!-- Branch Summary Filters -->
            <div
                style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); margin-bottom: 20px;">
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <select id="summaryAreaFilter"
                        style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer; min-width: 150px;"
                        onchange="filterBranchSummary()">
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
                    <select id="summaryBranchFilter"
                        style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer; min-width: 200px;"
                        onchange="filterBranchSummary()">
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
                        <input type="text" id="summarySearchInput" placeholder="Search Branch..."
                            onkeyup="filterBranchSummary()" style="width: 250px;">
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
                                    echo "<a href='bookletinvlive.php?view_branch=" . urlencode($branch['branch_code']) . "' class='btn-view' title='View Booklets'>";
                                    echo "View";
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
            <!-- Booklet Invoice Numbers Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>
                        <?php
                        if ($view_branch && $booklets_result && $booklets_result->num_rows > 0) {
                            $first_row = $booklets_result->fetch_assoc();
                            echo "Booklet Invoice Numbers - " . htmlspecialchars($first_row['branch_name']) . " (" . htmlspecialchars($view_branch) . ")";
                            $booklets_result->data_seek(0); // Reset pointer
                        } else {
                            echo "Booklet Invoice Numbers";
                        }
                        ?>
                    </h3>
                </div>
                <div class="table-wrapper">
                    <table id="bookletTable">
                        <thead>
                            <tr>
                                <th>Booklet Number</th>
                                <th>Beginning Number</th>
                                <th>Ending Number</th>
                                <th>Current Number</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($booklets_result && $booklets_result->num_rows > 0) {
                                while ($row = $booklets_result->fetch_assoc()) {
                                    $status = htmlspecialchars($row['status']);
                                    $is_cancelled = false; // TODO: Will be updated when cancel functionality is implemented
                                    $is_completed = !empty($row['complete_date']);
                                    $is_returned = !empty($row['return_date']);
                        
                                    // Determine display status
                                    if ($is_returned) {
                                        $display_status = 'Returned';
                                        $display_status_class = 'returned';
                                    } elseif ($is_completed) {
                                        $display_status = 'Completed';
                                        $display_status_class = 'completed';
                                    } else {
                                        $display_status = $status;
                                        $display_status_class = strtolower($status);
                                    }
                        
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['booklet_no'] ?? '-') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['beginning_number'] ?? '-') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['ending_number'] ?? '-') . "</td>";
                                    echo "<td style='font-weight: 600;'>" . htmlspecialchars($row['current_number']) . "</td>";

                                    if ($is_cancelled) {
                                        echo "<td><span class='status-badge cancelled'>Cancelled</span></td>";
                                    } else {
                                        echo "<td><span class='status-badge " . $display_status_class . "'>" . $display_status . "</span></td>";
                                    }

                                    echo "<td>";
                                    if ($is_cancelled) {
                                        echo "<button class='btn-cancelled' disabled>Cancelled</button>";
                                    } elseif ($is_completed || $is_returned) {
                                        // Disable cancel button for completed or returned booklets
                                        echo "<button class='btn-cancelled' disabled>Cancel</button>";
                                    } else {
                                        echo "<button class='btn-cancel' onclick='cancelBooklet(" . $row['id'] . ", \"" . htmlspecialchars($row['booklet_no']) . "\", \"" . htmlspecialchars($row['beginning_number']) . "\", \"" . htmlspecialchars($row['ending_number']) . "\", \"" . htmlspecialchars($row['current_number']) . "\")'>Cancel</button>";
                                    }
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align: center; padding: 40px; color: #666;'>No booklet invoice numbers found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cancel Booklet Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
                    </svg>
                    Cancel Booklet Invoice Number
                </h3>
                <span class="close" onclick="closeCancelModal()">&times;</span>
            </div>
            <form id="cancelForm" method="POST" action="cancel_booklet_invoice.php">
                <div class="modal-body">
                    <div id="modalErrorMessage"
                        style="display: none; background: #ffebee; color: #c62828; border: 1px solid #ef5350; border-radius: 4px; padding: 12px; margin-bottom: 15px; font-size: 13px; line-height: 1.5;">
                    </div>

                    <div class="info-box">
                        <p><strong>Warning:</strong> Submitting a cancellation request for a booklet invoice number
                            requires approval at Sales Skip Approval before it takes effect. This action will be logged
                            and requires a reason.</p>
                    </div>

                    <input type="hidden" name="booklet_id" id="cancel_booklet_id">
                    <input type="hidden" name="view_branch" value="<?php echo htmlspecialchars($view_branch); ?>">
                    <input type="hidden" id="cancel_beginning_number">
                    <input type="hidden" id="cancel_ending_number">
                    <input type="hidden" id="cancel_current_number">

                    <div class="form-group">
                        <label>Booklet Number</label>
                        <input type="text" id="cancel_booklet_display" readonly
                            style="background-color: #f5f5f5; cursor: not-allowed; font-weight: 600;">
                    </div>

                    <div class="form-group">
                        <label>Valid Range</label>
                        <input type="text" id="cancel_range_display" readonly
                            style="background-color: #f5f5f5; cursor: not-allowed;">
                    </div>

                    <div class="form-group">
                        <label>Invoice Number to Cancel<span class="required">*</span></label>
                        <input type="text" name="invoice_number" id="cancel_invoice_number"
                            placeholder="Enter specific invoice number to cancel (e.g., 0001 or 0003128-092-025)"
                            required autocomplete="off">
                        <small>Enter the exact invoice number from this booklet that you want to cancel.</small>
                    </div>

                    <div class="form-group">
                        <label>Reason for Cancellation<span class="required">*</span></label>
                        <textarea name="cancel_reason" id="cancel_reason"
                            placeholder="Provide a detailed reason for cancelling this invoice number..."
                            required></textarea>
                        <small>This reason will be logged for audit purposes.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeCancelModal()">Cancel</button>
                    <button type="submit" class="btn-modal-confirm">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>

    <script>
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
                const area = (td[2]?.textContent || '').toUpperCase();

                let matchArea = !areaFilter || area.includes(areaFilter);
                let matchBranch = !branchFilter || branchCode === branchFilter;
                let matchSearch = !searchInput || branchName.includes(searchInput) || branchCode.includes(searchInput);

                if (matchArea && matchBranch && matchSearch) {
                    tr[i].style.display = '';
                } else {
                    tr[i].style.display = 'none';
                }
            }
        }

        function cancelBooklet(bookletId, bookletNo, beginningNumber, endingNumber, currentNumber) {
            // Open modal
            document.getElementById('cancelModal').style.display = 'block';
            document.getElementById('cancel_booklet_id').value = bookletId;
            document.getElementById('cancel_booklet_display').value = 'Booklet #' + bookletNo;

            // Store range values
            document.getElementById('cancel_beginning_number').value = beginningNumber;
            document.getElementById('cancel_ending_number').value = endingNumber;
            document.getElementById('cancel_current_number').value = currentNumber;
            document.getElementById('cancel_range_display').value = 'Starting: ' + beginningNumber + ' | Current: ' + currentNumber + ' | Ending: ' + endingNumber;

            // Clear previous inputs
            document.getElementById('cancel_invoice_number').value = '';
            document.getElementById('cancel_reason').value = '';

            // Focus on invoice number input
            setTimeout(() => {
                document.getElementById('cancel_invoice_number').focus();
            }, 100);
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function (event) {
            const modal = document.getElementById('cancelModal');
            if (event.target == modal) {
                closeCancelModal();
            }
        }

        // Prevent modal close on form submit (let PHP handle the redirect)
        document.getElementById('cancelForm').addEventListener('submit', function (e) {
            const invoiceNumber = document.getElementById('cancel_invoice_number').value.trim();
            const reason = document.getElementById('cancel_reason').value.trim();
            const beginningNumber = document.getElementById('cancel_beginning_number').value;
            const endingNumber = document.getElementById('cancel_ending_number').value;
            const currentNumber = document.getElementById('cancel_current_number').value;
            const errorMessageDiv = document.getElementById('modalErrorMessage');

            // Hide previous error messages
            errorMessageDiv.style.display = 'none';
            errorMessageDiv.innerHTML = '';

            if (!invoiceNumber) {
                e.preventDefault();
                showModalError('Please enter the invoice number to cancel.');
                document.getElementById('cancel_invoice_number').focus();
                return false;
            }

            if (!reason) {
                e.preventDefault();
                showModalError('Please provide a reason for cancellation.');
                document.getElementById('cancel_reason').focus();
                return false;
            }

            // Extract numeric part from invoice number (handle formats like "0000001" or "0003128-092-025")
            let numericPart = invoiceNumber;
            if (invoiceNumber.includes('-')) {
                numericPart = invoiceNumber.split('-')[0];
            }

            // Remove leading zeros for comparison
            const invoiceNum = parseInt(numericPart, 10);
            const beginNum = parseInt(beginningNumber, 10);
            const endNum = parseInt(endingNumber, 10);

            // Validate if invoice number is within the booklet range
            if (isNaN(invoiceNum)) {
                e.preventDefault();
                showModalError('Invalid invoice number format. Please enter a valid number.');
                document.getElementById('cancel_invoice_number').focus();
                return false;
            }

            if (invoiceNum < beginNum || invoiceNum > endNum) {
                e.preventDefault();
                const errorMsg = '<strong>Invoice number ' + invoiceNumber + ' is outside the valid range!</strong><br><br>' +
                    'Valid range for this booklet:<br>' +
                    '• Starting: <strong>' + beginningNumber + '</strong><br>' +
                    '• Current: <strong>' + currentNumber + '</strong><br>' +
                    '• Ending: <strong>' + endingNumber + '</strong><br><br>' +
                    'Please enter an invoice number within this range.';
                showModalError(errorMsg);
                document.getElementById('cancel_invoice_number').focus();
                return false;
            }

            // Confirm before submitting
            if (!confirm('Are you sure you want to submit a cancellation request for Invoice Number: ' + invoiceNumber + '?\n\nThis request will require approval at Sales Skip Approval before taking effect.')) {
                e.preventDefault();
                return false;
            }
        });

        function showModalError(message) {
            const errorMessageDiv = document.getElementById('modalErrorMessage');
            errorMessageDiv.innerHTML = message;
            errorMessageDiv.style.display = 'block';

            // Scroll to top of modal to show error
            document.querySelector('.modal-body').scrollTop = 0;
        }
    </script>
</body>

</html>