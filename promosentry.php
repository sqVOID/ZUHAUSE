salesentry.php
<?php
require_once 'session_check.php';
include 'config.php';

// Get logged in user's branch code FIRST (before queries)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$branch_code = '000'; // Default
$user_branch_name = '';
if (isset($_SESSION['user_branch'])) {
    $user_branch_name = $_SESSION['user_branch'];
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch_name'");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
}

// Fetch active users for Assisted By dropdown (Name - Branch)
// Filter by branch: if user's branch is MOTOGAM, only show MOTOGAM users/promoters
$branch_filter = '';
if (!empty($user_branch_name)) {
    $escaped_branch = $conn->real_escape_string($user_branch_name);
    $branch_filter = " AND (branch LIKE '%$escaped_branch%' OR branch = '$escaped_branch')";
}

$users_result = $conn->query("
    SELECT first_name, last_name
    FROM users 
    WHERE status = 'Activated' $branch_filter
    UNION ALL 
    SELECT first_name, last_name
    FROM accounts 
    WHERE status = 'Activated' $branch_filter
    UNION ALL
    SELECT name as first_name, '' as last_name
    FROM promoters
    WHERE status = 'Active' $branch_filter
    UNION ALL
    SELECT dealer_name as first_name, '(Dealer)' as last_name
    FROM dealers
    WHERE status = 'Active'
    ORDER BY first_name, last_name
");

// Fetch terminal issuers (Active only)
$terminal_issuers_result = $conn->query("SELECT bank_name FROM terminal_issuers WHERE status='Active' ORDER BY bank_name");

// Fetch terminal IDs for current branch (Active only)
// terminal_ids.branches stores comma-separated branch names
$escaped_branch = $conn->real_escape_string($user_branch_name);
$terminal_ids_result = $conn->query("SELECT * FROM terminal_ids WHERE status='Active' AND (branches LIKE '%$escaped_branch%' OR branches = '' OR branches IS NULL) ORDER BY terminal_id");

// Build a PHP array of terminal IDs for JS
$terminal_ids_for_js = [];
if ($terminal_ids_result && $terminal_ids_result->num_rows > 0) {
    while ($tid_row = $terminal_ids_result->fetch_assoc()) {
        $terminal_ids_for_js[] = [
            'id' => $tid_row['id'],
            'terminal_id' => $tid_row['terminal_id'],
            'terminal_issuer' => $tid_row['terminal_issuer'],
            'branches' => $tid_row['branches']
        ];
    }
}

// Fetch active promos for dropdown
// If branch filter applies, only show promos for this branch
// Also check usage limit - exclude promos that have reached their limit
$promo_branch_filter = "";
if (!empty($user_branch_name) && $user_branch_name !== 'All Branches') {
    $escaped_branch = $conn->real_escape_string($user_branch_name);
    $promo_branch_filter = " AND (branch LIKE '%$escaped_branch%' OR branch = '' OR branch IS NULL)";
}
$promos_result = $conn->query("
    SELECT * FROM promos 
    WHERE status = 'Active' 
    AND (usage_limit IS NULL OR usage_count < usage_limit)
    $promo_branch_filter
    ORDER BY promo_name
");

// Fetch all promo_items and group by promo_id for JS
$promo_items_all_result = $conn->query("SELECT * FROM promo_items ORDER BY promo_id ASC, id ASC");
$promo_items_grouped = [];
if ($promo_items_all_result && $promo_items_all_result->num_rows > 0) {
    while ($pi = $promo_items_all_result->fetch_assoc()) {
        $pid = $pi['promo_id'];
        if (!isset($promo_items_grouped[$pid])) {
            $promo_items_grouped[$pid] = [];
        }
        $promo_items_grouped[$pid][] = [
            'motor_model' => $pi['motor_model'],
            'discount_type' => $pi['discount_type'],
            'discount_value' => $pi['discount_value'],
            'promo_item' => $pi['promo_item']
        ];
    }
}

// Build a PHP array of Promos for JS
$promos_for_js = [];
if ($promos_result && $promos_result->num_rows > 0) {
    $promos_result->data_seek(0);
    while ($promo_row = $promos_result->fetch_assoc()) {
        $pid = $promo_row['id'];
        // Use promo_items rows if available, fall back to legacy single fields
        $items = isset($promo_items_grouped[$pid]) && count($promo_items_grouped[$pid]) > 0
            ? $promo_items_grouped[$pid]
            : [
                [
                    'motor_model' => $promo_row['motor_model'],
                    'discount_type' => $promo_row['discount_type'],
                    'discount_value' => $promo_row['discount_value'],
                    'promo_item' => $promo_row['free_item']
                ]
            ];
        $promos_for_js[] = [
            'id' => $pid,
            'promo_name' => $promo_row['promo_name'],
            'usage_count' => isset($promo_row['usage_count']) ? (int) $promo_row['usage_count'] : 0,
            'usage_limit' => !empty($promo_row['usage_limit']) ? (int) $promo_row['usage_limit'] : null,
            'items' => $items
        ];
    }
    $promos_result->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/motogam_logo.jpg">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Promo Entry</title>
    <style>
        :root {
            /* Brand Colors - Matching Login Theme */
            --color-navy: #0d3347;
            --color-navy-dark: #081f2d;
            --color-navy-light: #164460;
            --color-gold: #b08a52;
            --color-gold-light: #c9a46e;
            --color-gold-pale: #f5ede0;

            /* Action Button Colors */
            --color-green: #2e7d32;
            --color-green-dark: #1b5e20;
            --color-green-light: #43a047;

            /* Background Colors */
            --bg-form-panel: #faf8f5;
            --bg-input: #f0ebe3;
            --bg-input-focus: #ffffff;

            /* Text Colors */
            --text-heading: #0d3347;
            --text-gold: #b08a52;
            --text-body: #9a9086;
            --text-muted: #7a7068;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0ff;
            /* Resize everything to simulate 1080p view */
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
            /* Adjusted height for 67% zoom */
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
            margin-top: 25px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .content-header {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: 15px;
            margin-bottom: 10px;
        }

        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 10px;
        }

        /* Split Layout for Top Form */
        .form-split-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            /* 2 parts form, 1 part remarks */
            gap: 30px;
        }

        .form-left-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-right-section {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 0;
            /* Remove margin bottom as gap in flex container handles it */
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-height {
            height: 100%;
        }

        .form-group label {
            font-size: 14px;
            color: #333;
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
            width: 100%;
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

        .form-group input[readonly] {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        #remarks {
            min-height: 40px;
            height: 40px;
        }

        .promo-details-box {
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            min-height: 130px;
            padding: 10px 12px;
            color: #555;
            font-size: 13px;
            line-height: 1.45;
            white-space: pre-line;
        }

        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
        }


        @media (max-width: 1024px) {

            .form-row,
            .form-row.three-columns {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.hidden {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .form-container {
                padding: 20px;
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

            .content-header h2 {
                font-size: 16px;
            }

            .form-actions {
                flex-direction: column;
            }

        }

        /* Item Selection Section */
        .item-selection-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 0px;
        }

        .item-input-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto auto;
            gap: 15px;
            align-items: flex-end;
            margin-bottom: 15px;
        }

        .btn-search-item {
            padding: 10px 50px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            height: 38px;
        }

        .btn-add-item {
            padding: 10px 50px;
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            height: 38px;
        }

        .btn-save {
            background-color: var(--color-gold);
            /* Green */
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 40px;
            /* Wide padding */
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
        }

        .btn-save:hover {
            background-color: var(--color-gold-light);
        }

        /* Table Styles (Matched to requested design with full grid lines) */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            /* Outer border */
        }

        .items-table thead {
            background: var(--color-gold-pale);
        }

        .items-table th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000000;
            border: 1px solid #ccc;
            /* Full grid borders */
        }

        .items-table td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            /* Full grid borders */
            text-align: center;
        }

        .btn-delete-item {
            background: #ef5350;
            color: white;
            border: none;
            border-radius: 4px;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            /* Center button */
        }

        /* Improved Number Inputs within Table */
        .items-table input[type="number"] {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
            width: 100%;
            box-sizing: border-box;
            /* Maintain padding within width */
            text-align: center;
            /* Center text by default */
        }

        .items-table input[type="number"]:focus {
            border-color: #66bb6a;
            /* Green highlight on focus */
            box-shadow: 0 0 3px rgba(102, 187, 106, 0.3);
        }

        .qty-input {
            max-width: 80px;
            /* Limit width for quantity */
            margin: 0 auto;
            /* Center input block if smaller than cell */
            display: block;
        }

        .price-input-table {
            max-width: 120px;
            /* Limit width for price */
            margin: 0 auto;
            display: block;
        }

        /* Bottom Section Grid */
        .bottom-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }


        .totals-section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-row label {
            font-weight: 600;
            font-size: 14px;
        }

        .total-row input {
            width: 150px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: right;
        }

        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-left: 20px;
            justify-content: center;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
        }


        .footer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0px;
            background: white;
            padding: 20px;
            /* border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);*/
        }

        .footer-left-group {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .footer-right-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .footer-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-input-group label {
            font-weight: 600;
        }

        .footer-input-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 150px;
        }

        .btn-payment {
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 40px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-payment:hover {
            background-color: var(--color-gold-light);
        }

        /* Media Queries for Responsiveness */

        /* Tablet & Smaller Desktop (max-width: 1024px) */
        @media (max-width: 1024px) {
            .form-split-layout {
                grid-template-columns: 1fr;
                /* Stack form and remarks */
            }

            .item-input-row {
                grid-template-columns: 1fr 1fr;
                /* 2 columns for item inputs */
            }

            .bottom-section {
                grid-template-columns: 1fr;
                /* Stack totals */
            }

            .right-section-wrapper {
                align-items: flex-start !important;
                /* Align totals left on stack */
            }

            .totals-section,
            .footer-actions {
                width: 100% !important;
            }

            .footer-actions {
                justify-content: flex-start !important;
                /* Align left */
                flex-wrap: wrap;
                /* Allow wrapping */
            }

            .footer-left-group,
            .footer-right-group {
                flex-wrap: wrap;
            }
        }

        /* Mobile Devices (max-width: 768px) */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.hidden {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .form-container {
                padding: 15px;
                /* Reduced padding */
            }

            /* Stack item inputs vertically */
            .item-input-row {
                grid-template-columns: 1fr;
            }

            .btn-search-item,
            .btn-add-item {
                width: 100%;
                /* Full width buttons */
            }

            /* Responsive Tables */
            .items-table,
            .search-results-table {
                display: block;
                overflow-x: auto;
                /* Scroll horizontally */
                white-space: nowrap;
            }

            /* Adjust Totals Layout on Mobile */
            .total-row {
                flex-direction: column;
                /* Stack label and input */
                align-items: flex-start;
            }

            .total-row input {
                width: 100%;
            }

            /* Adjust Footer Actions Mobile */
            .footer-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .footer-left-group,
            .footer-right-group {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .footer-input-group {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }

            .footer-input-group input {
                width: 100%;
            }

            .btn-payment {
                width: 100%;
                margin-top: 10px;
            }
        }

        /* Small Mobile (max-width: 480px) */
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
                padding: 10px;
            }

            .content-header h2 {
                font-size: 18px;
            }

            .form-row {
                grid-template-columns: 1fr;
                /* Stack form rows */
            }
        }

        /* Search Modal Styles */
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
            /* Vertical center */
            justify-content: center;
            /* Horizontal center */
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            /* Centering with flex container */
            border: 1px solid #888;
            width: 80%;
            /* Increased width */
            max-width: 1000px;
            /* Increased max-width */
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: fadeIn 0.3s;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
            /* Prevent overflowing viewport height */
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid #eee;
            font-size: 22px;
            font-weight: 700;
            color: #333;
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
            /* Take remaining space */
        }

        .search-results-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            /* Fix layout for better wrapping control */
            border: 1px solid #ccc;
            /* Outer border */
        }

        .search-results-table thead {
            background: var(--color-gold-pale);
        }

        .search-results-table th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000000;
            border: 1px solid #ccc;
            /* Full grid borders */
        }

        .search-results-table td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            /* Full grid borders */
            text-align: center;
            /* Center alignment for all columns including description */
            word-wrap: break-word;
        }

        .search-results-table tr:hover {
            background-color: #f5f5f5;
        }

        .btn-select {
            background-color: #2e7d32;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-select:hover {
            background-color: #1b5e20;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-start;
        }

        .btn-back-modal {
            padding: 10px 30px;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 15px;
        }

        .btn-back-modal:hover {
            background-color: #f5f5f5;
        }

        /* Payment Modal Specific Styles */
        .payment-modal-content {
            max-width: 900px;
            width: 100%;
            align-self: flex-start;
            /* Align to top */
            margin-top: 5%;
            margin-bottom: 40px;
            /* Add bottom margin for scrolling */
        }

        .payment-modal-body {
            padding: 20px 25px;
        }

        .payment-top-section {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
            flex-wrap: nowrap;
        }

        .payment-dropdown {
            padding: 8px 12px;
            border: 1px solid #333;
            border-radius: 4px;
            font-size: 13px;
            background: white;
            cursor: pointer;
            min-width: 120px;
            flex-shrink: 0;
        }

        .payment-dropdown:focus {
            outline: none;
            border-color: #2e7d32;
        }

        .payment-radio-group {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-shrink: 0;
        }

        .payment-radio-option {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
        }

        .payment-radio-option input[type="radio"] {
            cursor: pointer;
            width: 14px;
            height: 14px;
        }

        .payment-radio-option span {
            user-select: none;
        }

        /* Disabled Payment Options */
        .payment-dropdown.disabled-payment {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .payment-dropdown.disabled-payment:focus {
            border-color: #ddd;
        }

        .payment-radio-option.disabled-payment {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .payment-radio-option.disabled-payment input[type="radio"] {
            cursor: not-allowed;
        }

        .payment-radio-option.disabled-payment span {
            color: #999;
        }

        /* Home Credit Section */
        .home-credit-section {
            background: white;
            padding-top: 0px;
            display: none;
            /* Hidden by default */
        }

        /* Credit Card Section */
        .credit-card-section {
            background: white;
            padding-top: 0px;
            display: none;
            /* Hidden by default */
        }

        /* Debit Card Section */
        .debit-card-section {
            background: white;
            padding-top: 0px;
            display: none;
            /* Hidden by default */
        }

        /* QR PH Section */
        .qr-ph-section {
            background: white;
            padding-top: 0px;
            display: none;
            /* Hidden by default */
        }

        /* Starpay QR Section */
        .starpay-qr-section {
            background: white;
            padding-top: 0px;
            display: none;
            /* Hidden by default */
        }

        /* E-Wallet Section */
        .ewallet-section {
            background: white;
            padding-top: 0px;
            display: none;
            /* Hidden by default */
        }

        /* Online Banking Section */
        .online-banking-section {
            background: white;
            padding-top: 0px;
            display: none;
            /* Hidden by default */
        }

        /* Cash Section */
        .cash-section {
            background: white;
            padding-top: 0px;
            display: none;
            /* Hidden by default */
        }


        .home-credit-section h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #333;
        }

        .credit-card-section h3,
        .debit-card-section h3,
        .qr-ph-section h3,
        .starpay-qr-section h3,
        .ewallet-section h3,
        .online-banking-section h3,
        .cash-section h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #333;
        }

        /* HC Single Column Layout */
        .hc-grid-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .hc-form-group {
            display: flex;
            flex-direction: row;
            /* Label left, input right */
            align-items: center;
            gap: 15px;
            width: 100%;
        }

        /* Remove .hc-form-row if it exists in HTML, or style it as a wrapper that doesn't grid */
        .hc-form-row {
            display: contents;
            /* Remove grid behavior if this class wraps items */
        }

        .hc-form-group-wide {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 15px;
            width: 100%;
        }

        .hc-form-group label {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 0;
            color: #333;
            white-space: nowrap;
            width: 160px;
            /* Fixed width for alignment */
            flex-shrink: 0;
        }

        .hc-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
            /* Fill remaining space */
            width: 100%;
        }

        .hc-input:focus {
            outline: none;
            border-color: #2e7d32;
        }

        /* Down Payment Group specific styling */
        .down-payment-group {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 15px;
            width: 100%;
        }

        .down-payment-group>label {
            font-size: 14px;
            font-weight: 700;
            color: #333;
            white-space: nowrap;
            width: 160px;
            flex-shrink: 0;
            margin-top: 10px;
            /* Align with top of the box content */
        }

        .down-payment-box {
            padding: 15px 20px;
            border: 1px solid #bfbfbf;
            /* Visible gray border */
            border-radius: 4px;
            background: #ffffff;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .payment-method-label {
            margin-bottom: 8px;
        }

        .payment-method-row {
            display: flex;
            gap: 0px;
            align-items: center;
            flex-wrap: nowrap;
            justify-content: flex-start;
        }

        .pm-label {
            font-weight: 700;
            font-size: 14px;
            margin-right: 5px;
            white-space: nowrap;
            color: #333;
        }

        .checkbox-inline {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 400;
            white-space: nowrap;
        }

        .checkbox-inline input[type="checkbox"] {
            cursor: pointer;
            width: 18px;
            height: 18px;
            margin: 0;
        }

        .enter-amount-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .enter-amount-row label {
            font-weight: 700;
            font-size: 14px;
            width: auto;
            margin-right: 5px;
            white-space: nowrap;
            color: #333;
        }

        .amount-input {
            padding: 8px 12px;
            border: 1px solid #bfbfbf;
            border-radius: 4px;
            font-size: 14px;
            width: 200px;
        }

        .amount-input:focus {
            outline: none;
            border-color: #2e7d32;
        }

        /* Total Section - Right Aligned */
        .total-section {
            display: flex;
            width: 100%;
            justify-content: flex-end;
            /* Pushes content to the right */
            align-items: center;
            gap: 15px;
            margin-top: 20px;
        }

        .total-section label {
            font-size: 16px;
            font-weight: 700;
            color: #333;
        }

        .total-input {
            padding: 10px 15px;
            border: 1px solid #bfbfbf;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            width: 200px;
            text-align: right;
            background: white;
        }

        /* Footer - Back (Left) and Save (Right) */
        .payment-modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-top: 1px solid #eee;
            width: 100%;
            box-sizing: border-box;
            background: white;
            border-radius: 0 0 8px 8px;
        }

        .btn-back-modal {
            padding: 10px 40px;
            border: 1px solid #ccc;
            background: white;
            color: #333;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            min-width: 120px;
        }

        .btn-back-modal:hover {
            background: #f5f5f5;
        }

        .btn-save-modal {
            padding: 10px 40px;
            border: none;
            background: #1b5e20;
            /* Strong Green */
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            min-width: 120px;
        }

        .btn-save-modal:hover {
            background-color: #144a18;
        }

        .btn-clear-main {
            padding: 10px 40px;
            border: 1px solid rgba(201, 201, 201, 1);
            background: white;
            color: black;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            min-width: 120px;
            /* Match Save button width */
        }

        .btn-clear-main:hover {
            background: #ecffebff;
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
        <!-- <img src="Icon/motogam_logo.jpg" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Promo Entry</h2>
            <?php if (!empty($user_branch_name) || (isset($_SESSION['system_level']) && ($_SESSION['system_level'] === 'Super-Admin' || $_SESSION['system_level'] === 'Sub-admin'))): ?>
                <div
                    style="margin-left: auto; font-weight: bold; color: #1b5e20; font-size: 18px; text-transform: uppercase;">
                    <?php
                    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
                    if ($system_level === 'Super-Admin' || $system_level === 'Sub-admin') {
                        echo "All Branches";
                    } else {
                        echo htmlspecialchars($user_branch_name);
                        if (!empty($branch_code) && $branch_code !== '000') {
                            echo ' - ' . htmlspecialchars($branch_code);
                        }
                    }
                    ?>
                </div>
                <?php
            endif; ?>
        </div>

        <div class="form-container">
            <form id="salesEntryForm" method="POST" class="form-split-layout">
                <input type="hidden" name="payment_data" id="payment_data">
                <div class="form-left-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="invoice_no">Invoice No</label>
                            <input type="text" id="invoice_no" name="invoice_no" placeholder="System Generated"
                                readonly>
                        </div>
                        <div class="form-group">
                            <label for="date">Date</label>
                            <input type="text" id="date" name="date" placeholder="System Generated" readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name </label>
                            <input type="text" id="first_name" name="first_name"
                                placeholder="Enter First Name (Optional)"
                                oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name </label>
                            <input type="text" id="last_name" name="last_name" placeholder="Enter Last Name (Optional)"
                                oninput="this.value = this.value.toUpperCase()">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="address">Address </label>
                            <input type="text" id="address" name="address" placeholder="Enter Address (Optional)">
                        </div>
                        <div class="form-group">
                            <label for="assisted_by">Assisted By</label>
                            <select id="assisted_by" name="assisted_by" required>
                                <option value="" disabled selected>Select</option>
                                <?php
                                if ($users_result && $users_result->num_rows > 0) {
                                    while ($user = $users_result->fetch_assoc()) {
                                        $name = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                                        $branch = htmlspecialchars($user['branch']);
                                        $displayText = $name;
                                        echo '<option value="' . $displayText . '">' . $displayText . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_no">Contact No</label>
                            <input type="text" id="contact_no" name="contact_no"
                                placeholder="Enter Contact No. (Optional)"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="Enter Email (Optional)"
                                onblur="validateEmail(this)">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" placeholder="Enter remarks..."
                                oninput="this.value = this.value.toUpperCase()"></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-right-section">
                    <div class="form-group">
                        <label for="applied_promo">Promo</label>
                        <select id="applied_promo" name="applied_promo" onchange="applyPromoLogic()">
                            <option value="">-- No Promo / Select Promo --</option>
                            <?php
                            if ($promos_result && $promos_result->num_rows > 0) {
                                while ($promo = $promos_result->fetch_assoc()) {
                                    $promo_label = htmlspecialchars($promo['promo_name']);
                                    // Add usage info if limit exists
                                    if (!empty($promo['usage_limit'])) {
                                        $usage_count = isset($promo['usage_count']) ? $promo['usage_count'] : 0;
                                        $promo_label .= " (" . $usage_count . "/" . $promo['usage_limit'] . " used)";
                                    }
                                    echo '<option value="' . htmlspecialchars($promo['id']) . '">' . $promo_label . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="promo_details">Promo Details</label>
                        <div id="promo_details" class="promo-details-box">No promo selected.</div>
                    </div>
                </div>

            </form>
        </div>

        <!-- Item Selection Section -->
        <div class="item-selection-container">
            <div style="display: flex; gap: 30px; margin-bottom: 20px;">
                <!-- Left Column -->
                <div style="flex: 1; display: flex; flex-direction: column; gap: 15px;">
                    <div class="form-group">
                        <label>Item Code</label>
                        <input type="text" id="item_code" placeholder=""
                            oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div class="form-group">
                        <label>Item Description</label>
                        <input type="text" id="item_desc" placeholder="" readonly
                            style="background-color: #ffffffff; cursor: not-allowed; color: #333;">
                    </div>
                </div>

                <!-- Right Column -->
                <div style="flex: 1; display: flex; flex-direction: column; gap: 15px;">
                    <div class="form-group">
                        <label>Serial number</label>
                        <input type="text" id="imei" placeholder="" oninput="this.value = this.value.toUpperCase()">
                    </div>

                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <div class="form-group" style="width: 40%;">
                            <label>Quantity</label>
                            <input type="number" id="qty" value="0" min="0" style="text-align: center;">
                        </div>
                        <div class="form-group" style="width: 40%;">
                            <label>Price</label>
                            <input type="text" id="price" placeholder="">
                            <!-- <input type="number" id="price" placeholder="" readonly style="background-color: #ffffffff; cursor: not-allowed; color: #333;"> -->
                        </div>
                        <button type="button" class="btn-search-item" style="margin-bottom: 1px;">Search</button>
                        <button type="button" class="btn-add-item" style="margin-bottom: 1px;">Add</button>
                    </div>
                </div>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Item Description</th>
                        <th style="width: 30%;">Serial Number</th>
                        <th style="width: 15%;">Quantity</th>
                        <th style="width: 15%;">Price</th>
                        <th style="width: 10%; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <tbody id="itemsTableBody">
                    <tr id="no-sales-row">
                        <td colspan="5" style="text-align:center; padding: 20px;">No Sales Entry yet</td>
                    </tr>
                </tbody>
            </table>

            <div class="bottom-section">
                <div class="right-section-wrapper"
                    style="display: flex; flex-direction: column; gap: 20px; align-items: flex-end;">
                    <div class="totals-section" style="width: 100%;">
                        <div class="total-row">
                            <label>Total QTY:</label>
                            <input type="number" id="totalQty" readonly>
                        </div>
                        <div class="total-row">
                            <label>Discount:</label>
                            <input type="text" id="discountField" style="background-color: #e0e0e0;" readonly>
                        </div>
                        <div class="total-row">
                            <label>Total:</label>
                            <input type="text" id="totalAmount" readonly>
                        </div>
                    </div>

                    <div class="footer-actions"
                        style="width: 100%; justify-content: space-between; padding: 0; background: transparent;">
                        <div class="footer-left-group">
                            <div class="footer-input-group">
                                <label>Points:</label>
                                <input type="text" id="pointsField" style="background-color: #e0e0e0;" readonly>
                            </div>
                            <div class="footer-input-group">
                                <label>Commision:</label>
                                <input type="text" id="commissionField" readonly>
                            </div>
                        </div>
                        <div class="footer-right-group">
                            <button type="button" class="btn-payment" onclick="openPaymentModal()">Payment</button>
                            <button type="button" class="btn-save">SAVE</button>
                            <button type="button" class="btn-clear-main" onclick="clearMainForm()">CLEAR</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="searchItemModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    Search
                </div>
                <div class="modal-body">
                    <table class="search-results-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Item Code</th>
                                <th style="width: 50%;">Item Description</th>
                                <th style="width: 20%; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="searchResultsBody">
                            <!-- Results will be injected here -->
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-back-modal" onclick="closeSearchModal()">Back</button>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <style>
            .total-section {
                display: none !important;
            }

            /* Add spacing and separators between active payment sections */
            .payment-modal-body>div[class$="-section"]:not(.payment-top-section) {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px dashed #ccc;
            }

            /* Ensure the first visible section doesn't have a top border if it immediately follows top-section */
            .payment-top-section {
                margin-bottom: 20px;
                /* Optional space below checkboxes */
            }
        </style>
        <div id="paymentModal" class="modal">
            <div class="modal-content payment-modal-content">
                <div class="modal-header">
                    Choose your Payment
                </div>
                <div class="modal-body payment-modal-body">
                    <!-- Top Section: Dropdowns and Radio Buttons -->
                    <div class="payment-top-section">
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <label class="payment-radio-option" style="margin: 0;">
                                <input type="checkbox" name="payment_method" value="payment_partners"
                                    id="chkPaymentPartners">
                            </label>
                            <select class="payment-dropdown" id="paymentPartnersDropdown">
                                <option value="">Select Payment Partners</option>
                                <option value="partner2">Home Credit</option>
                                <option value="partner5">Salmon</option>
                                <option value="partner6">Samsung Finances</option>
                                <option value="partner7">Payjoy</option>
                                <option value="partner8">Billease</option>
                                <option value="partner9">Paymongo</option>
                                <option value="partner10">Skyro</option>
                            </select>
                        </div>

                        <div style="display: flex; align-items: center; gap: 5px;">
                            <label class="payment-radio-option" style="margin: 0;">
                                <input type="checkbox" name="payment_method" value="card_payment" id="chkCardPayment">
                            </label>
                            <select class="payment-dropdown" id="cardPaymentDropdown">
                                <option value="">Select Card Payment</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                            </select>
                        </div>


                        <div style="display: flex; align-items: center; gap: 5px;">
                            <label class="payment-radio-option" style="margin: 0;">
                                <input type="checkbox" name="payment_method" value="qr" id="chkQR">
                            </label>
                            <select class="payment-dropdown" id="qrDropdown">
                                <option value="">Select QR</option>
                                <option value="qr_ph">QR PH</option>
                                <option value="starpay_qr">Starpay QR</option>
                            </select>
                        </div>

                        <div class="payment-radio-group" style="flex-direction: row; gap: 15px; margin-left: 10px;">
                            <label class="payment-radio-option">
                                <input type="checkbox" name="payment_method" value="online_banking">
                                <span>Online Banking</span>
                            </label>
                            <label class="payment-radio-option">
                                <input type="checkbox" name="payment_method" value="ewallet">
                                <span>E-Wallet</span>
                            </label>
                            <label class="payment-radio-option">
                                <input type="checkbox" name="payment_method" value="cash">
                                <span>Cash</span>
                            </label>
                        </div>
                    </div>

                    <!-- Home Credit Section -->
                    <div class="home-credit-section">
                        <h3 id="paymentPartnerTitle">Home Credit</h3>

                        <div class="hc-grid-container">
                            <!-- Unit selector: shown when 2+ items, auto-filled when 1 item -->
                            <div class="hc-form-group unit-selector-row" style="display:none;"></div>

                            <div class="hc-form-group">
                                <label>Loan Type:</label>
                                <select class="hc-input">
                                    <option value=""></option>
                                    <option value="installment_loan">Installment Loan</option>
                                    <option value="0_installment">0% Installment</option>
                                    <option value="promo_installment">Promo Installment</option>
                                    <option value="standard_installment">Standard Installment</option>
                                    <option value="cash_loan">Cash Loan</option>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>Loan Terms:</label>
                                <select class="hc-input">
                                    <option value=""></option>
                                    <option value="3months">3 Months</option>
                                    <option value="6months">6 Months</option>
                                    <option value="9months">9 Months</option>
                                    <option value="12months">12 Months</option>
                                    <option value="15months">15 Months</option>
                                    <option value="18months">18 Months</option>
                                    <option value="24months">24 Months</option>
                                    <option value="36months">36 Months</option>
                                    <option value="48months">48 Months</option>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>Customer's Name:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Loan Number:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Total Loan Amount:</label>
                                <input type="text" class="hc-input" id="totalLoanAmount">
                            </div>

                            <div class="hc-form-group">
                                <label>Loan Balance:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group down-payment-group">
                                <label>Down payment:</label>
                                <div class="down-payment-box">
                                    <div class="payment-method-label">
                                        <span class="pm-label">Payment Method:</span>
                                    </div>
                                    <div class="payment-method-row">
                                        <label class="checkbox-inline">
                                            <input type="checkbox" name="down_payment_method" value="cash"
                                                onchange="toggleDownPaymentReference()">
                                            1. Cash
                                        </label>
                                        <label class="checkbox-inline">
                                            <input type="checkbox" name="down_payment_method" value="gcash"
                                                onchange="toggleDownPaymentReference()">
                                            2. G-Cash
                                        </label>
                                        <label class="checkbox-inline">
                                            <input type="checkbox" name="down_payment_method" value="maya"
                                                onchange="toggleDownPaymentReference()">
                                            3. Maya
                                        </label>
                                    </div>

                                    <div class="reference-no-row hci-amount-row" id="dpCashRow"
                                        style="display: none; align-items: center; gap: 10px; margin-bottom: 15px;">
                                        <label
                                            style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">Cash
                                            Amount (DP):</label>
                                        <input type="text" class="amount-input" id="cash_down_payment_amount"
                                            oninput="formatInput(this)"
                                            style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                    </div>

                                    <div class="reference-no-row hci-amount-row" id="dpGcashRow"
                                        style="display: none; flex-direction: column; gap: 10px; margin-bottom: 15px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <label
                                                style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">G-Cash
                                                Reference No:</label>
                                            <input type="text" class="reference-input" id="gcash_down_payment_reference"
                                                name="gcash_down_payment_reference"
                                                style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <label
                                                style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">G-Cash
                                                Amount (DP):</label>
                                            <input type="text" class="amount-input" id="gcash_down_payment_amount"
                                                oninput="formatInput(this)"
                                                style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                        </div>
                                    </div>

                                    <div class="reference-no-row hci-amount-row" id="dpMayaRow"
                                        style="display: none; flex-direction: column; gap: 10px; margin-bottom: 15px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <label
                                                style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">Maya
                                                Reference No:</label>
                                            <input type="text" class="reference-input" id="maya_down_payment_reference"
                                                name="maya_down_payment_reference"
                                                style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <label
                                                style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">Maya
                                                Amount (DP):</label>
                                            <input type="text" class="amount-input" id="maya_down_payment_amount"
                                                oninput="formatInput(this)"
                                                style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <script>
                                function toggleDownPaymentReference() {
                                    const cashCb = document.querySelector('input[name="down_payment_method"][value="cash"]');
                                    const gcashCb = document.querySelector('input[name="down_payment_method"][value="gcash"]');
                                    const mayaCb = document.querySelector('input[name="down_payment_method"][value="maya"]');

                                    const cashRow = document.getElementById('dpCashRow');
                                    const gcashRow = document.getElementById('dpGcashRow');
                                    const mayaRow = document.getElementById('dpMayaRow');

                                    if (cashCb && cashCb.checked) {
                                        cashRow.style.display = 'flex';
                                    } else if (cashRow) {
                                        cashRow.style.display = 'none';
                                        const cInput = document.getElementById('cash_down_payment_amount');
                                        if (cInput) { cInput.value = ''; cInput.dispatchEvent(new Event('input', { bubbles: true })); }
                                    }

                                    if (gcashCb && gcashCb.checked) {
                                        gcashRow.style.display = 'flex';
                                    } else if (gcashRow) {
                                        gcashRow.style.display = 'none';
                                        const gRef = document.getElementById('gcash_down_payment_reference');
                                        const gInput = document.getElementById('gcash_down_payment_amount');
                                        if (gRef) gRef.value = '';
                                        if (gInput) { gInput.value = ''; gInput.dispatchEvent(new Event('input', { bubbles: true })); }
                                    }

                                    if (mayaCb && mayaCb.checked) {
                                        mayaRow.style.display = 'flex';
                                    } else if (mayaRow) {
                                        mayaRow.style.display = 'none';
                                        const mRef = document.getElementById('maya_down_payment_reference');
                                        const mInput = document.getElementById('maya_down_payment_amount');
                                        if (mRef) mRef.value = '';
                                        if (mInput) { mInput.value = ''; mInput.dispatchEvent(new Event('input', { bubbles: true })); }
                                    }
                                }
                            </script>
                        </div>

                        <div class="total-section">
                            <label>Total:</label>
                            <input type="text" class="total-input" readonly>
                        </div>
                    </div>

                    <!-- Credit Card Section -->
                    <div class="credit-card-section">
                        <h3>Credit Card</h3>

                        <div class="hc-grid-container">
                            <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                            <div class="hc-form-group">
                                <label>Terminal Issuer:</label>
                                <select class="hc-input" id="ccTerminalIssuer" onchange="filterTerminalIds('cc')">
                                    <option value="">Select Terminal Issuer</option>
                                    <?php
                                    if ($terminal_issuers_result && $terminal_issuers_result->num_rows > 0) {
                                        $terminal_issuers_result->data_seek(0);
                                        while ($ti_row = $terminal_issuers_result->fetch_assoc()) {
                                            echo "<option value='" . htmlspecialchars($ti_row['bank_name'], ENT_QUOTES) . "'>" . htmlspecialchars($ti_row['bank_name']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>Terminal ID:</label>
                                <select class="hc-input" id="ccTerminalId">
                                    <option value="">Select Terminal ID</option>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>Bank:</label>
                                <select class="hc-input" id="creditCardBankDropdown">
                                    <option value="">Select Bank</option>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>Terms:</label>
                                <select class="hc-input" id="creditCardTermsDropdown">
                                    <option value="">Select Terms</option>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>MID:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Card No:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Approval Code:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Batch:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Amount:</label>
                                <input type="text" class="hc-input" id="creditCardAmount">
                            </div>
                        </div>

                        <div class="total-section">
                            <label>Total:</label>
                            <input type="text" class="total-input" readonly>
                        </div>
                    </div>

                    <!-- Debit Card Section -->
                    <div class="debit-card-section">
                        <h3>Debit Card</h3>

                        <div class="hc-grid-container">
                            <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                            <div class="hc-form-group">
                                <label>Terminal Issuer:</label>
                                <select class="hc-input" id="dcTerminalIssuer" onchange="filterTerminalIds('dc')">
                                    <option value="">Select Terminal Issuer</option>
                                    <?php
                                    if ($terminal_issuers_result && $terminal_issuers_result->num_rows > 0) {
                                        $terminal_issuers_result->data_seek(0);
                                        while ($ti_row = $terminal_issuers_result->fetch_assoc()) {
                                            echo "<option value='" . htmlspecialchars($ti_row['bank_name'], ENT_QUOTES) . "'>" . htmlspecialchars($ti_row['bank_name']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>Terminal ID:</label>
                                <select class="hc-input" id="dcTerminalId">
                                    <option value="">Select Terminal ID</option>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>Bank:</label>
                                <select class="hc-input" id="debitCardBankDropdown">
                                    <option value="">Select Bank</option>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>Terms:</label>
                                <select class="hc-input" id="debitCardTermsDropdown">
                                    <option value="">Select Terms</option>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>MID:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Card No:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Approval Code:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Batch:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Amount:</label>
                                <input type="text" class="hc-input" id="debitCardAmount">
                            </div>
                        </div>

                        <div class="total-section">
                            <label>Total:</label>
                            <input type="text" class="total-input" readonly>
                        </div>
                    </div>

                    <!-- QR PH Section -->
                    <div class="qr-ph-section">
                        <h3>QR PH</h3>

                        <div class="hc-grid-container">
                            <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                            <div class="hc-form-group">
                                <label>Bank:</label>
                                <select class="hc-input">
                                    <option value="">Select Bank</option>
                                    <option value="BDO">BDO</option>
                                    <option value="Metrobank">Metrobank</option>
                                    <option value="PNB">Philippine National Bank</option>
                                    <option value="E-West">E-West</option>
                                    <option value="RCBC">RCBC</option>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>Customer's Name:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Reference No:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Amount:</label>
                                <input type="text" class="hc-input">
                            </div>
                        </div>

                        <div class="total-section">
                            <label>Total:</label>
                            <input type="text" class="total-input" readonly>
                        </div>
                    </div>

                    <!-- Starpay QR Section -->
                    <div class="starpay-qr-section">
                        <h3>Starpay QR</h3>

                        <div class="hc-grid-container">
                            <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                            <div class="hc-form-group">
                                <label>Bank:</label>
                                <select class="hc-input">
                                    <option value="">Select Bank</option>
                                    <option value="BDO">BDO</option>
                                    <option value="Metrobank">Metrobank</option>
                                    <option value="PNB">Philippine National Bank</option>
                                    <option value="E-West">E-West</option>
                                    <option value="RCBC">RCBC</option>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>Customer's Name:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Reference No:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Amount:</label>
                                <input type="text" class="hc-input">
                            </div>
                        </div>

                        <div class="total-section">
                            <label>Total:</label>
                            <input type="text" class="total-input" readonly>
                        </div>
                    </div>

                    <!-- E-Wallet Section -->
                    <div class="ewallet-section">
                        <h3>E-Wallet</h3>

                        <div class="hc-grid-container">
                            <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                            <div class="hc-form-group">
                                <label>E-Wallet:</label>
                                <select class="hc-input">
                                    <option value="">Select E-Wallet</option>
                                    <option value="gcash">GCash</option>
                                    <option value="maya">Maya</option>
                                    <option value="paymaya">PayMaya</option>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>Customer's Name:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Reference No:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Amount:</label>
                                <input type="text" class="hc-input">
                            </div>
                        </div>

                        <div class="total-section">
                            <label>Total:</label>
                            <input type="text" class="total-input" readonly>
                        </div>
                    </div>

                    <!-- Online Banking Section -->
                    <div class="online-banking-section">
                        <h3>Online Banking</h3>

                        <div class="hc-grid-container">
                            <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                            <div class="hc-form-group">
                                <label>Bank:</label>
                                <select class="hc-input">
                                    <option value="">Select Bank</option>
                                    <option value="BDO">BDO</option>
                                    <option value="Metrobank">Metrobank</option>
                                    <option value="PNB">Philippine National Bank</option>
                                    <option value="E-West">E-West</option>
                                    <option value="RCBC">RCBC</option>
                                </select>
                            </div>

                            <div class="hc-form-group">
                                <label>Reference No:</label>
                                <input type="text" class="hc-input">
                            </div>

                            <div class="hc-form-group">
                                <label>Amount:</label>
                                <input type="text" class="hc-input">
                            </div>
                        </div>

                        <div class="total-section">
                            <label>Total:</label>
                            <input type="text" class="total-input" readonly>
                        </div>
                    </div>

                    <!-- Cash Section -->
                    <div class="cash-section">
                        <h3>Cash</h3>

                        <div class="hc-grid-container">
                            <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                            <div class="hc-form-group">
                                <label>Amount:</label>
                                <input type="text" class="hc-input">
                            </div>
                        </div>

                        <div class="total-section">
                            <label>Total:</label>
                            <input type="text" class="total-input" readonly>
                        </div>
                    </div>

                    <!-- Global Total -->
                    <div class="global-totals-wrapper"
                        style="display: flex; flex-direction: column; align-items: flex-end; padding: 15px 0; border-top: 1px solid #ddd; margin-top: 10px; gap: 10px;">
                        <div class="global-total-due"
                            style="display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: bold;">
                            <label>Total Amount Due:</label>
                            <input type="text" id="globalTotalDueInput" readonly
                                style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; width: 150px; background-color: #f5f5f5; font-weight: bold; text-align: right;">
                        </div>
                        <div class="global-total-section"
                            style="display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: bold;">
                            <label>Total Payment:</label>
                            <input type="text" id="globalTotalInput" readonly
                                style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; width: 150px; background-color: #f5f5f5; font-weight: bold; text-align: right;">
                        </div>
                    </div>
                    <!-- Payment Breakdown banner -->
                    <div id="paymentBreakdownBanner"
                        style="display:none; margin: 0 0 15px 0; padding: 12px 16px; background-color: #ffffffff; border-left: 4px solid #16a34a; border-right:1px solid #c9c9c9ff; border-top:1px solid #c9c9c9ff; border-bottom:1px solid #c9c9c9ff; border-radius: 4px;  box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-family: system-ui, -apple-system, sans-serif;">
                    </div>

                    <!-- Inline payment error banner -->
                    <div id="paymentErrorBanner"
                        style="display:none; margin: 0 0 15px 0; padding: 12px 16px; background-color: #fef2f2; border-left: 4px solid #ef4444; border-right:1px solid #c9c9c9ff; border-top:1px solid #c9c9c9ff; border-bottom:1px solid #c9c9c9ff; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-family: system-ui, -apple-system, sans-serif;">
                    </div>
                </div>

                <div class="modal-footer payment-modal-footer">
                    <button type="button" class="btn-back-modal" onclick="closePaymentModal()">Back</button>
                    <button type="button" class="btn-save-modal">Save</button>
                </div>
            </div>
        </div>

        <script>
            // Terminal IDs dataset from PHP (filtered by current branch)
            const allTerminalIds = <?php echo json_encode($terminal_ids_for_js); ?>;

            /**
             * Filter Terminal ID dropdown based on selected Terminal Issuer.
             * Also wires the Terminal ID change event to filter the Bank dropdown.
             * @param {string} section - 'cc' for Credit Card, 'dc' for Debit Card
             */
            function filterTerminalIds(section) {
                const issuerSelect = document.getElementById(section === 'cc' ? 'ccTerminalIssuer' : 'dcTerminalIssuer');
                const terminalSelect = document.getElementById(section === 'cc' ? 'ccTerminalId' : 'dcTerminalId');
                if (!issuerSelect || !terminalSelect) return;

                const selectedIssuer = issuerSelect.value;

                // Reset Terminal ID dropdown
                terminalSelect.innerHTML = '<option value="">Select Terminal ID</option>';

                // Also reset bank dropdown when issuer changes
                filterBanksByTerminalId(section, null);

                if (!selectedIssuer) return;

                // Filter terminal IDs where the terminal_issuer field contains the selected issuer
                const filtered = allTerminalIds.filter(tid => {
                    const issuers = tid.terminal_issuer.split(',').map(s => s.trim());
                    return issuers.some(iss => iss === selectedIssuer || iss.startsWith(selectedIssuer));
                });

                filtered.forEach(tid => {
                    const opt = document.createElement('option');
                    opt.value = tid.terminal_id;
                    opt.textContent = tid.terminal_id;
                    // Store full issuer list as data attribute for bank filtering
                    opt.setAttribute('data-issuers', tid.terminal_issuer);
                    terminalSelect.appendChild(opt);
                });

                // Wire change event (remove old listener first to avoid duplicates)
                const newSelect = terminalSelect.cloneNode(true);
                terminalSelect.parentNode.replaceChild(newSelect, terminalSelect);
                newSelect.addEventListener('change', function () {
                    const selectedOpt = this.options[this.selectedIndex];
                    const issuers = selectedOpt ? selectedOpt.getAttribute('data-issuers') : null;
                    filterBanksByTerminalId(section, issuers);
                });
            }

            /**
             * Populate the Bank dropdown with ALL banks from selectedItemPrices,
             * regardless of which terminal issuer/ID is selected.
             * @param {string} section  - 'cc' or 'dc'
             * @param {string|null} issuersStr - (unused, kept for signature compatibility)
             */
            function filterBanksByTerminalId(section, issuersStr) {
                const isCC = (section === 'cc');
                const bankDropdown = document.getElementById(isCC ? 'creditCardBankDropdown' : 'debitCardBankDropdown');
                const termsDropdown = document.getElementById(isCC ? 'creditCardTermsDropdown' : 'debitCardTermsDropdown');
                const amountInput = document.getElementById(isCC ? 'creditCardAmount' : 'debitCardAmount');
                if (!bankDropdown) return;

                // Clear existing options
                bankDropdown.innerHTML = '<option value="">Select Bank</option>';
                if (termsDropdown) termsDropdown.innerHTML = '<option value="">Select Terms</option>';
                if (amountInput) { amountInput.value = ''; amountInput.removeAttribute('readonly'); }

                // Show all banks from selectedItemPrices (no issuer filtering)
                if (Object.keys(selectedItemPrices).length === 0) return;

                const banks = new Set();
                for (const key in selectedItemPrices) {
                    const spaceIndex = key.indexOf(' ');
                    if (spaceIndex !== -1) {
                        banks.add(key.substring(0, spaceIndex));
                    }
                }

                banks.forEach(bank => {
                    const option = document.createElement('option');
                    option.value = bank;
                    option.textContent = bank;
                    bankDropdown.appendChild(option);
                });
            }

            function clearMainForm() {
                if (!confirm('Are you sure you want to clear the entire form?')) return;

                // 1. Reset Main Form Inputs
                const form = document.querySelector('form'); // Assuming there's one main form
                if (form) form.reset();

                // 2. Reset Item Search Inputs
                document.getElementById('item_code').value = '';
                document.getElementById('item_desc').value = '';
                document.getElementById('imei').value = '';
                document.getElementById('qty').value = '1';
                document.getElementById('price').value = '';

                // 3. Clear Items Table
                const tbody = document.getElementById('itemsTableBody');
                tbody.innerHTML = '<tr id="no-sales-row"><td colspan="5" style="text-align:center; padding: 20px;">No Sales Entry yet</td></tr>';

                // 4. Reset Totals
                document.getElementById('totalQty').value = '';
                document.getElementById('totalAmount').value = '';

                // 6. Reset Discount Field to locked state
                const discountField = document.getElementById('discountField');
                discountField.value = '0';
                discountField.setAttribute('readonly', 'readonly');
                discountField.style.backgroundColor = '#e0e0e0';
                discountField.style.cursor = 'not-allowed';

                // 6b. Reset IMEI Field to locked state
                const imeiField = document.getElementById('imei');
                imeiField.value = '';
                imeiField.setAttribute('readonly', 'readonly');
                imeiField.style.backgroundColor = '#f5f5f5';
                imeiField.style.cursor = 'not-allowed';

                // 7. Reset Footer Inputs
                const footerInputs = document.querySelectorAll('.footer-input-group input');
                footerInputs.forEach(i => i.value = '');

                // Reset Payment Button and Payment Data
                const btnPayment = document.querySelector('.btn-payment');
                if (btnPayment) {
                    btnPayment.innerText = 'Payment';
                    btnPayment.style.backgroundColor = '#689f38';
                    btnPayment.style.color = 'white';
                }
                const paymentDataInput = document.getElementById('payment_data');
                if (paymentDataInput) {
                    paymentDataInput.value = '';
                }

                // Re-initialize Date/Invoice if needed (Optional, usually desired to keep Invoice No)
                initializeForm();
            }

            // Save Sales Entry Function
            function saveSalesEntry() {
                // Validate payment first
                const paymentDataInput = document.getElementById('payment_data');
                let paymentData = null;
                if (paymentDataInput && paymentDataInput.value) {
                    try {
                        paymentData = JSON.parse(paymentDataInput.value);
                    } catch (e) {
                        console.error('Error parsing payment data:', e);
                    }
                }

                // Check if payment has been received/entered
                if (!paymentData || Object.keys(paymentData).length === 0) {
                    alert('Please payment first before save button.');
                    return;
                }

                // Get total amount from the form
                const totalAmountField = document.getElementById('totalAmount');
                const totalAmount = parseFloat(totalAmountField.value.replace(/,/g, '')) || 0;

                // Validate full payment for specific payment types
                const paymentType = paymentData.payment_type;
                if (paymentType === 'Online Banking' || paymentType === 'E-Wallet' || paymentType === 'Cash') {
                    // Get payment amount from payment data
                    let paymentAmount = 0;

                    // Check common amount fields
                    const amountFields = ['Amount', 'amount', 'Total', 'total'];
                    for (const field of amountFields) {
                        if (paymentData[field]) {
                            paymentAmount = parseFloat(paymentData[field].toString().replace(/,/g, '')) || 0;
                            break;
                        }
                    }

                    // Validate full payment (allow small rounding differences)
                    if (Math.abs(paymentAmount - totalAmount) > 0.01) {
                        alert('Please pay the full amount for the items.');
                        return;
                    }
                }

                // Validate required field: Assisted By
                const assistedBy = document.getElementById('assisted_by').value;
                if (!assistedBy) {
                    alert('Assisted By is required!');
                    document.getElementById('assisted_by').focus();
                    return;
                }

                // Collect items from table
                const itemsTableBody = document.getElementById('itemsTableBody');
                const itemRows = itemsTableBody.querySelectorAll('tr');
                const items = [];

                itemRows.forEach(row => {
                    const qtyInput = row.querySelector('.qty-input');
                    const priceInput = row.querySelector('.price-input-table');

                    if (qtyInput && priceInput) {
                        const cells = row.querySelectorAll('td');
                        const itemCode = row.getAttribute('data-item-code') || '';
                        const basePrice = parseFloat(row.getAttribute('data-base-price')) || 0;
                        const currentPrice = parseNumber(priceInput.value) || 0;
                        const appliedPromoId = document.getElementById('applied_promo') ? document.getElementById('applied_promo').value : '';
                        const isPromoItem = (row.getAttribute('data-is-promo-item') === '1' || (appliedPromoId && currentPrice < basePrice)) ? 1 : 0;

                        items.push({
                            description: cells[0].textContent.trim(),
                            imei: cells[1].textContent.trim(),
                            quantity: parseInt(qtyInput.value) || 0,
                            price: currentPrice,
                            item_code: itemCode,
                            is_promo_item: isPromoItem
                        });
                    }
                });

                // Validate that there are items
                if (items.length === 0) {
                    alert('Please add at least one item to the sales entry!');
                    return;
                }

                // Parse number helper function
                function parseFormattedNumber(str) {
                    if (!str) return 0;
                    return parseFloat(str.replace(/,/g, '')) || 0;
                }

                // Prepare data object
                const data = {
                    first_name: document.getElementById('first_name').value.trim(),
                    last_name: document.getElementById('last_name').value.trim(),
                    address: document.getElementById('address').value.trim(),
                    contact_no: document.getElementById('contact_no').value.trim(),
                    email: document.getElementById('email').value.trim(),
                    assisted_by: assistedBy,
                    remarks: document.getElementById('remarks').value.trim(),
                    promo_id: document.getElementById('applied_promo').value || null,
                    total_qty: parseInt(document.getElementById('totalQty').value) || 0,
                    discount: (() => {
                        // discountField may hold a text label like '2 Free Items'
                        // parseFloat("2 Free Items") = 2 (wrong!) so we use regex to
                        // only treat purely numeric strings as discount values
                        const rawDiscount = document.getElementById('discountField').value.replace(/,/g, '').trim();
                        return /^[\d.]+$/.test(rawDiscount) ? (parseFloat(rawDiscount) || 0) : 0;
                    })(),
                    total_amount: parseFormattedNumber(document.getElementById('totalAmount').value),
                    points: parseFormattedNumber(document.getElementById('pointsField').value),
                    commission: parseFormattedNumber(document.getElementById('commissionField').value),
                    payment_data: paymentData,
                    page_type: 'promosentry',
                    items: items
                };

                // Show loading state
                const saveBtn = document.querySelector('.btn-save');
                const originalText = saveBtn.textContent;
                saveBtn.textContent = 'Saving...';
                saveBtn.disabled = true;

                // Send data to server
                fetch('save_sales_entry.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                    .then(response => {
                        // Check if response is ok
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        // Check if response is JSON
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            return response.text().then(text => {
                                console.error('Non-JSON response:', text);
                                throw new Error('Server returned non-JSON response');
                            });
                        }

                        return response.json();
                    })
                    .then(result => {
                        saveBtn.textContent = originalText;
                        saveBtn.disabled = false;

                        if (result.status === 'success') {
                            alert('Sales entry saved successfully!\nInvoice No: ' + result.invoice_no);

                            // Update invoice number in the form
                            document.getElementById('invoice_no').value = result.invoice_no;

                            // Automatically clear the form after successful save
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    })
                    .catch(error => {
                        saveBtn.textContent = originalText;
                        saveBtn.disabled = false;
                        console.error('Error:', error);
                        alert('An error occurred while saving the sales entry. Please try again.');
                    });
            }

            // Attach save function to SAVE button
            document.addEventListener('DOMContentLoaded', function () {
                const saveBtn = document.querySelector('.btn-save');
                if (saveBtn) {
                    saveBtn.addEventListener('click', saveSalesEntry);
                }
            });

            // Promos Data Array
            const promosData = <?php echo json_encode($promos_for_js); ?>;

            function getActivePromoUsageLabel() {
                const appliedPromoSelect = document.getElementById('applied_promo');
                if (!appliedPromoSelect || appliedPromoSelect.selectedIndex <= 0) return '';

                const promoId = appliedPromoSelect.value;
                const promo = (typeof promosData !== 'undefined' && Array.isArray(promosData))
                    ? promosData.find(p => String(p.id) === String(promoId))
                    : null;

                if (!promo) {
                    return appliedPromoSelect.options[appliedPromoSelect.selectedIndex].text;
                }

                const baseUsage = parseInt(promo.usage_count) || 0;
                const usageLimit = (promo.usage_limit !== null && promo.usage_limit !== undefined && promo.usage_limit !== '')
                    ? parseInt(promo.usage_limit)
                    : null;

                // Count free items currently in cart
                let cartUsage = 0;
                const tbody = document.getElementById('itemsTableBody');
                if (tbody) {
                    const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.id !== 'no-sales-row');
                    let freeCount = 0;
                    let hasItemMatch = false;
                    rows.forEach(row => {
                        const priceInputEl = row.querySelector('.price-input-table');
                        const priceVal = parseNumber(priceInputEl ? priceInputEl.value : '0') || 0;
                        const priceTd = row.querySelectorAll('td')[3];
                        const isFreeText = priceTd && priceTd.textContent.trim().toUpperCase() === 'FREE';
                        if (priceVal === 0 || isFreeText) {
                            const qtyEl = row.querySelector('.qty-input');
                            freeCount += parseInt(qtyEl ? qtyEl.value : 1) || 1;
                        } else {
                            hasItemMatch = true;
                        }
                    });

                    if (freeCount > 0) {
                        cartUsage = freeCount;
                    } else if (hasItemMatch && rows.length > 0) {
                        cartUsage = 1;
                    }
                }

                const currentTotalUsage = baseUsage + cartUsage;

                let label = promo.promo_name;
                if (usageLimit !== null && !isNaN(usageLimit)) {
                    label += ` (${currentTotalUsage}/${usageLimit} used)`;
                }

                return label;
            }

            // Active promo state
            let activePromoItems = [];
            let activePromoDiscountLabel = '0';

            function normalizePromoText(value) {
                return (value || '').toString().toUpperCase().trim();
            }

            function isItemMatchingModel(itemCode, itemDesc, targetModel) {
                const codeNorm = normalizePromoText(itemCode);
                const descNorm = normalizePromoText(itemDesc);
                const targetNorm = normalizePromoText(targetModel);

                if (!targetNorm || targetNorm === 'ALL MODELS' || targetNorm === 'ALL') {
                    return true;
                }

                return (codeNorm === targetNorm || descNorm.includes(targetNorm) || targetNorm.includes(codeNorm));
            }

            function applyPromoFreePriceIfEligible(itemCode, itemDesc, qty) {
                const priceInput = document.getElementById('price');
                if (!priceInput) return;

                // Reset free/discount markers by default.
                priceInput.removeAttribute('data-is-promo-free');
                priceInput.removeAttribute('data-is-promo-discounted');
                priceInput.style.color = '#333';

                if (!activePromoItems || activePromoItems.length === 0) return;

                const requestedQty = parseInt(qty, 10) || 0;
                if (requestedQty <= 0) return;

                const existingRows = Array.from(document.querySelectorAll('#itemsTableBody tr')).filter(r => r.id !== 'no-sales-row');

                // ── CHECK FOR FREE PROMO RULES ──────────────────────────────────────────
                const freeRules = activePromoItems.filter(item => normalizePromoText(item.discount_type) === 'FREE');
                let eligibleAsFree = false;

                if (freeRules.length > 0) {
                    for (const rule of freeRules) {
                        const buyModel = rule.motor_model ? normalizePromoText(rule.motor_model) : '';
                        const freeModel = rule.promo_item ? normalizePromoText(rule.promo_item) : buyModel;

                        // The pending item MUST match freeModel to be eligible for FREE price
                        if (!isItemMatchingModel(itemCode, itemDesc, freeModel)) {
                            continue;
                        }

                        const isSameItem = (!rule.promo_item || freeModel === buyModel);

                        if (!isSameItem) {
                            // Different item promo (e.g., BUY BLACK, GET BLUE FREE)
                            let buyQty = 0;
                            let alreadyFreeQty = 0;

                            existingRows.forEach(row => {
                                const rowCode = row.getAttribute('data-item-code') || '';
                                const rowDesc = row.querySelector('td') ? row.querySelector('td').textContent : '';
                                const qtyEl = row.querySelector('.qty-input');
                                const rowQty = parseInt(qtyEl ? qtyEl.value : 0) || 0;
                                const priceInputEl = row.querySelector('.price-input-table');
                                const priceVal = parseNumber(priceInputEl ? priceInputEl.value : '0') || 0;

                                if (isItemMatchingModel(rowCode, rowDesc, buyModel)) {
                                    buyQty += rowQty;
                                }
                                if (isItemMatchingModel(rowCode, rowDesc, freeModel) && priceVal === 0) {
                                    alreadyFreeQty += rowQty;
                                }
                            });

                            if (alreadyFreeQty + requestedQty <= buyQty) {
                                eligibleAsFree = true;
                                break;
                            }
                        } else {
                            // Same item promo (e.g., BUY 1 BLACK, GET 1 BLACK FREE)
                            let existingQty = 0;
                            existingRows.forEach(row => {
                                const rowCode = row.getAttribute('data-item-code') || '';
                                const rowDesc = row.querySelector('td') ? row.querySelector('td').textContent : '';
                                if (isItemMatchingModel(rowCode, rowDesc, buyModel)) {
                                    const qtyEl = row.querySelector('.qty-input');
                                    existingQty += parseInt(qtyEl ? qtyEl.value : 0) || 0;
                                }
                            });

                            const freeBefore = Math.floor(existingQty / 2);
                            const freeAfter = Math.floor((existingQty + requestedQty) / 2);
                            if (freeAfter - freeBefore >= requestedQty) {
                                eligibleAsFree = true;
                                break;
                            }
                        }
                    }

                    if (eligibleAsFree) {
                        priceInput.value = 'FREE';
                        priceInput.setAttribute('readonly', 'readonly');
                        priceInput.style.backgroundColor = '#f5f5f5';
                        priceInput.style.color = '#2e7d32';
                        priceInput.style.cursor = 'not-allowed';
                        priceInput.setAttribute('data-is-promo-free', '1');
                        return; // Exit early if item is free
                    }
                }

                // ── CHECK FOR PERCENTAGE DISCOUNT PROMO RULES ──────────────────────────
                const percentRules = activePromoItems.filter(item => item.discount_type === '%');

                if (percentRules.length > 0) {
                    for (const rule of percentRules) {
                        const buyModel = rule.motor_model ? normalizePromoText(rule.motor_model) : '';
                        const discountModel = rule.promo_item ? normalizePromoText(rule.promo_item) : '';
                        const discountPercent = parseFloat(rule.discount_value) || 0;

                        // The pending item MUST match the discount model (promo_item)
                        if (!isItemMatchingModel(itemCode, itemDesc, discountModel)) {
                            continue;
                        }

                        // Check if the main item (buyModel) exists in cart
                        let hasBuyItem = false;
                        existingRows.forEach(row => {
                            const rowCode = row.getAttribute('data-item-code') || '';
                            const rowDesc = row.querySelector('td') ? row.querySelector('td').textContent : '';
                            if (isItemMatchingModel(rowCode, rowDesc, buyModel)) {
                                hasBuyItem = true;
                            }
                        });

                        if (hasBuyItem) {
                            // Apply percentage discount to current price
                            const basePrice = parseFloat(priceInput.getAttribute('data-base-price')) || parseNumber(priceInput.value) || 0;
                            const discountedPrice = basePrice * (1 - discountPercent / 100);

                            priceInput.value = formatNumber(discountedPrice);
                            priceInput.setAttribute('data-is-promo-discounted', '1');
                            priceInput.setAttribute('data-discount-percent', discountPercent);
                            priceInput.setAttribute('readonly', 'readonly');
                            priceInput.style.backgroundColor = '#f5f5f5';
                            priceInput.style.cursor = 'not-allowed';
                            return; // Exit after applying discount
                        }
                    }
                }
            }

            function reevaluateCartPromo() {
                const itemsTableBody = document.getElementById('itemsTableBody');
                if (!itemsTableBody) return;
                const rows = Array.from(itemsTableBody.querySelectorAll('tr')).filter(r => r.id !== 'no-sales-row');

                // 1. Reset all rows to their original base prices and clear promo flag
                rows.forEach(row => {
                    row.removeAttribute('data-is-promo-item');
                    const basePrice = parseFloat(row.getAttribute('data-base-price')) || 0;
                    const priceTd = row.querySelectorAll('td')[3];
                    if (priceTd) priceTd.innerHTML = `${formatNumber(basePrice)}<input type="hidden" class="price-input-table" value="${basePrice}">`;
                });

                if (!activePromoItems || activePromoItems.length === 0) {
                    updateTotals();
                    return;
                }

                // 2. Apply FREE rules first
                const freeRules = activePromoItems.filter(item => normalizePromoText(item.discount_type) === 'FREE');

                freeRules.forEach(rule => {
                    const buyModel = rule.motor_model ? normalizePromoText(rule.motor_model) : '';
                    const freeModel = rule.promo_item ? normalizePromoText(rule.promo_item) : buyModel;

                    const isSameItem = (!rule.promo_item || freeModel === buyModel);

                    if (!isSameItem && freeModel) {
                        let totalBuyQty = 0;
                        const matchingBuyRows = [];
                        rows.forEach(row => {
                            const rowCode = row.getAttribute('data-item-code') || '';
                            const rowDesc = row.querySelector('td') ? row.querySelector('td').textContent : '';
                            if (isItemMatchingModel(rowCode, rowDesc, buyModel)) {
                                const qtyEl = row.querySelector('.qty-input');
                                totalBuyQty += parseInt(qtyEl ? qtyEl.value : 0) || 0;
                                matchingBuyRows.push(row);
                            }
                        });

                        let freeUnitsRemaining = totalBuyQty;
                        let freeUnitsGiven = 0;

                        rows.forEach(row => {
                            const rowCode = row.getAttribute('data-item-code') || '';
                            const rowDesc = row.querySelector('td') ? row.querySelector('td').textContent : '';
                            if (isItemMatchingModel(rowCode, rowDesc, freeModel) && freeUnitsRemaining > 0) {
                                const qtyEl = row.querySelector('.qty-input');
                                const rowQty = parseInt(qtyEl ? qtyEl.value : 0) || 0;
                                if (rowQty <= freeUnitsRemaining) {
                                    const priceTd = row.querySelectorAll('td')[3];
                                    if (priceTd) priceTd.innerHTML = `FREE<input type="hidden" class="price-input-table" value="0">`;
                                    row.setAttribute('data-is-promo-item', '1');
                                    freeUnitsRemaining -= rowQty;
                                    freeUnitsGiven += rowQty;
                                }
                            }
                        });

                        if (freeUnitsGiven > 0) {
                            matchingBuyRows.forEach(r => r.setAttribute('data-is-promo-item', '1'));
                        }
                    } else if (isSameItem && buyModel) {
                        let totalUnits = 0;
                        const matchingRows = [];
                        rows.forEach(row => {
                            const rowCode = row.getAttribute('data-item-code') || '';
                            const rowDesc = row.querySelector('td') ? row.querySelector('td').textContent : '';
                            if (isItemMatchingModel(rowCode, rowDesc, buyModel)) {
                                const qtyEl = row.querySelector('.qty-input');
                                totalUnits += parseInt(qtyEl ? qtyEl.value : 0) || 0;
                                matchingRows.push(row);
                            }
                        });

                        const maxFreeAllowed = Math.floor(totalUnits / 2);
                        let freeAssigned = 0;

                        matchingRows.forEach((row, idx) => {
                            if (idx < maxFreeAllowed * 2) {
                                row.setAttribute('data-is-promo-item', '1');
                            }
                            if (idx % 2 === 1 && freeAssigned < maxFreeAllowed) {
                                const priceTd = row.querySelectorAll('td')[3];
                                if (priceTd) priceTd.innerHTML = `FREE<input type="hidden" class="price-input-table" value="0">`;
                                freeAssigned++;
                            }
                        });
                    }
                });

                // 3. Apply PERCENTAGE DISCOUNT rules
                const percentRules = activePromoItems.filter(item => item.discount_type === '%');

                percentRules.forEach(rule => {
                    const buyModel = rule.motor_model ? normalizePromoText(rule.motor_model) : '';
                    const discountModel = rule.promo_item ? normalizePromoText(rule.promo_item) : '';
                    const discountPercent = parseFloat(rule.discount_value) || 0;

                    if (!discountModel) return;

                    // Check if main item (buyModel) exists in cart
                    const matchingBuyRows = [];
                    rows.forEach(row => {
                        const rowCode = row.getAttribute('data-item-code') || '';
                        const rowDesc = row.querySelector('td') ? row.querySelector('td').textContent : '';
                        if (isItemMatchingModel(rowCode, rowDesc, buyModel)) {
                            matchingBuyRows.push(row);
                        }
                    });

                    if (matchingBuyRows.length > 0) {
                        let hasDiscountItem = false;
                        // Apply discount to all matching discount items
                        rows.forEach(row => {
                            const rowCode = row.getAttribute('data-item-code') || '';
                            const rowDesc = row.querySelector('td') ? row.querySelector('td').textContent : '';

                            if (isItemMatchingModel(rowCode, rowDesc, discountModel)) {
                                const basePrice = parseFloat(row.getAttribute('data-base-price')) || 0;
                                const discountedPrice = basePrice * (1 - discountPercent / 100);
                                const priceTd = row.querySelectorAll('td')[3];

                                if (priceTd) {
                                    priceTd.innerHTML = `${formatNumber(discountedPrice)}<input type="hidden" class="price-input-table" value="${discountedPrice}">`;
                                }
                                row.setAttribute('data-is-promo-item', '1');
                                hasDiscountItem = true;
                            }
                        });
                        if (hasDiscountItem) {
                            matchingBuyRows.forEach(r => r.setAttribute('data-is-promo-item', '1'));
                        }
                    }
                });

                updateTotals();
            }

            function renderPromoDetails(promo) {
                const promoDetails = document.getElementById('promo_details');
                if (!promoDetails) return;

                if (!promo || !promo.items || promo.items.length === 0) {
                    promoDetails.textContent = 'No promo selected.';
                    return;
                }

                const details = promo.items.map((item, index) => {
                    const model = item.motor_model ? item.motor_model : 'ALL MODELS';
                    const discountType = (item.discount_type || '').toString().trim();
                    const discountValue = (item.discount_value || '').toString().trim();
                    const freeItem = (item.promo_item || '').toString().trim();

                    let benefitLabel = '';
                    if (discountType === '%') {
                        const pct = parseFloat(discountValue) || 0;
                        benefitLabel = `${pct}% DISCOUNT`;
                    } else if (discountType.toLowerCase() === 'free') {
                        benefitLabel = 'BUY 1 TAKE 1 FREE';
                    } else {
                        benefitLabel = `${discountType} ${discountValue}`.trim() || 'SPECIAL PROMO';
                    }

                    let itemLine = `${index + 1}) MODEL: ${model}\n   BENEFIT: ${benefitLabel}`;
                    if ((discountType === '%' || discountType.toLowerCase() === 'free') && freeItem) {
                        itemLine += `\n   PROMO ITEM: ${freeItem}`;
                    }

                    return itemLine;
                }).join('\n\n');

                promoDetails.textContent = `PROMO NAME: ${promo.promo_name}\n\n${details}`;
            }

            function applyPromoLogic() {
                const promoSelect = document.getElementById('applied_promo');
                const promoId = promoSelect.value;
                const discountField = document.getElementById('discountField');

                // Reset promo state
                activePromoItems = [];
                activePromoDiscountLabel = '0';

                if (!promoId) {
                    renderPromoDetails(null);
                    discountField.value = '0';
                    discountField.setAttribute('readonly', 'readonly');
                    discountField.style.backgroundColor = '#e0e0e0';
                    discountField.style.cursor = 'not-allowed';

                    // Reset payment data and button
                    const paymentDataInput = document.getElementById('payment_data');
                    if (paymentDataInput) paymentDataInput.value = '';
                    const btnPayment = document.querySelector('.btn-payment');
                    if (btnPayment) {
                        btnPayment.innerText = 'Payment';
                        btnPayment.style.backgroundColor = '#689f38';
                        btnPayment.style.color = 'white';
                    }

                    updateTotals();
                    if (typeof window.updateSectionTotal === 'function') {
                        window.updateSectionTotal();
                    }
                    return;
                }

                // Find the selected promo (compare as string)
                const promo = promosData.find(p => String(p.id) === String(promoId));
                if (!promo || !promo.items || promo.items.length === 0) {
                    renderPromoDetails(null);
                    return;
                }

                activePromoItems = promo.items;
                renderPromoDetails(promo);

                alert('Promo Applied: ' + promo.promo_name);

                // Calculate the combined numeric discount from % items
                // (Free items are given physically, they don't reduce the invoice total here)
                let totalDiscountPercent = 0;
                let hasFree = false;
                let hasPercent = false;
                promo.items.forEach(item => {
                    if (item.discount_type === '%') {
                        hasPercent = true;
                        const pct = parseFloat(item.discount_value) || 0;
                        totalDiscountPercent += pct;
                    } else if (item.discount_type === 'Free') {
                        hasFree = true;
                    }
                });

                // Build a label for the discount field
                let discountLabel = [];
                if (hasFree) discountLabel.push('Free Item');
                if (hasPercent) discountLabel.push(totalDiscountPercent + '% Off');

                activePromoDiscountLabel = discountLabel.join(' + ') || '0';

                // Initial discount display will be updated by updateTotals()
                discountField.value = '0';
                discountField.setAttribute('readonly', 'readonly');
                discountField.style.backgroundColor = '#e0e0e0';
                // Reset any previously saved payment data since promo changed
                const paymentDataInput = document.getElementById('payment_data');
                if (paymentDataInput) paymentDataInput.value = '';
                const btnPayment = document.querySelector('.btn-payment');
                if (btnPayment) {
                    btnPayment.innerText = 'Payment';
                    btnPayment.style.backgroundColor = '#689f38';
                    btnPayment.style.color = 'white';
                }

                // Re-run totals so % discount is reflected in the Total field
                reevaluateCartPromo();

                if (typeof window.updateSectionTotal === 'function') {
                    window.updateSectionTotal();
                }
            }

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


            function initializeForm() {

                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');

                // Fetch next invoice number from server
                fetch('get_next_invoice_number.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_invoice_number&branch_code=<?php echo $branch_code; ?>&page_type=salesentry'
                })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.invoice_number) {
                            document.getElementById('invoice_no').value = result.invoice_number;
                            if (result.format === 'fallback') {
                                console.info('Using default invoice format (no booklet configured)');
                            }
                        } else {
                            console.error('Failed to generate invoice number:', result.message);
                            const fallback = '<?php echo date("ymd") . "-" . $branch_code . "-00001"; ?>';
                            document.getElementById('invoice_no').value = fallback;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching invoice number:', error);
                        const today = new Date();
                        const yy = String(today.getFullYear()).substr(-2);
                        const mm = String(today.getMonth() + 1).padStart(2, '0');
                        const dd = String(today.getDate()).padStart(2, '0');
                        const fallback = `${yy}${mm}${dd}-<?php echo $branch_code; ?>-00001`;
                        document.getElementById('invoice_no').value = fallback;
                    });


                const formattedDate = today.toLocaleDateString('en-US', {
                    month: '2-digit',
                    day: '2-digit',
                    year: 'numeric'
                });
                document.getElementById('date').value = formattedDate;
            }


            document.addEventListener('DOMContentLoaded', function () {
                initializeForm();
            });

            function validateEmail(input) {
                const val = input.value.trim().toLowerCase();
                if (val === 'n/a' || val === 'na') {
                    input.value = '';
                    alert("Email cannot be 'N/A' or 'n/a'. Please leave it empty if not applicable.");
                }
            }

            const searchBtn = document.querySelector('.btn-search-item');
            const itemCodeInput = document.getElementById('item_code');
            const itemDescInput = document.getElementById('item_desc');
            const imeiInput = document.getElementById('imei');
            const qtyInput = document.getElementById('qty');
            const priceInput = document.getElementById('price');
            const modal = document.getElementById('searchItemModal');
            const resultsBody = document.getElementById('searchResultsBody');

            // IMEI Search Function
            function searchByIMEI(imei) {
                // Check for duplicate serial number in the table before searching
                if (imei && imei.trim() !== '') {
                    const existingRows = itemsTableBody.querySelectorAll('tr');
                    for (let row of existingRows) {
                        const cells = row.querySelectorAll('td');
                        if (cells.length >= 2) {
                            const existingSerial = cells[1].textContent.trim();
                            if (existingSerial === imei.trim()) {
                                alert('This IMEI/Serial Number (' + imei + ') has already been added to this sales entry!');
                                // Clear the IMEI field
                                imeiInput.value = '';
                                return;
                            }
                        }
                    }
                }

                fetch(`search_imei.php?imei=${encodeURIComponent(imei)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Populate fields with found data
                            itemCodeInput.value = data.data.item_code || '';
                            itemDescInput.value = data.data.description;
                            priceInput.value = formatNumber(data.data.price);
                            priceInput.setAttribute('data-base-price', data.data.price || 0);

                            // Set quantity to 1 automatically for IMEI items
                            qtyInput.value = '1';

                            // Buy 1 Take 1: auto-mark this scanned unit as FREE when eligible.
                            applyPromoFreePriceIfEligible(itemCodeInput.value, itemDescInput.value, qtyInput.value);

                            // Disable price field for serialized items
                            priceInput.setAttribute('readonly', 'readonly');
                            priceInput.style.backgroundColor = '#ffffff';
                            priceInput.style.color = '#333';
                            priceInput.style.cursor = 'default';

                            // Mark IMEI as already looked up so Add button skips the serialized-redirect
                            imeiInput.setAttribute('data-imei-found', '1');

                            // -- Fetch item prices so Bank/Terms dropdowns populate correctly --
                            if (data.data.item_code) {
                                fetch(`search_item.php?term=${encodeURIComponent(data.data.item_code)}`)
                                    .then(r => r.json())
                                    .then(sd => {
                                        if (sd.status === 'success' && sd.data.length > 0) {
                                            // Find the exact matching item_code
                                            const match = sd.data.find(it => it.item_code === data.data.item_code) || sd.data[0];
                                            selectedItemPrices = match.prices || {};
                                            updateCardPaymentDropdowns();
                                        }
                                    })
                                    .catch(() => { });
                            }

                            // Fetch and cache commission/points for this item
                            if (data.data.item_code) {
                                fetch(`check_item_details.php?item_code=${encodeURIComponent(data.data.item_code)}`)
                                    .then(r => r.json())
                                    .then(det => {
                                        if (det.status === 'success') {
                                            itemCodeInput.setAttribute('data-commission', det.commission || 0);
                                            itemCodeInput.setAttribute('data-has-commission', det.has_commission || 0);
                                            itemCodeInput.setAttribute('data-points', det.points || 0);
                                            itemCodeInput.setAttribute('data-has-points', det.has_points || 0);
                                        }
                                    })
                                    .catch(() => { });
                            }

                            // Check if item is serialized
                            if (data.data.item_code) {
                                fetch(`check_serial_permission.php?item_code=${encodeURIComponent(data.data.item_code)}`)
                                    .then(response => response.json())
                                    .then(permData => {
                                        const imeiField = document.getElementById('imei');

                                        if (permData.status === 'success' && permData.has_serial) {
                                            // Item is serialized - keep IMEI field editable
                                            imeiField.removeAttribute('readonly');
                                            imeiField.style.backgroundColor = '#ffffff';
                                            imeiField.style.cursor = 'text';
                                        } else {
                                            // Item is not serialized - lock IMEI field
                                            imeiField.setAttribute('readonly', 'readonly');
                                            imeiField.style.backgroundColor = '#f5f5f5';
                                            imeiField.style.cursor = 'not-allowed';
                                        }

                                        // Check discount permission
                                        fetch(`check_discount_permission.php?item_code=${encodeURIComponent(data.data.item_code)}`)
                                            .then(response => response.json())
                                            .then(permData => {
                                                if (permData.status === 'success') {
                                                    const discountField = document.getElementById('discountField');
                                                    if (permData.discount_editable) {
                                                        discountField.removeAttribute('readonly');
                                                        discountField.style.backgroundColor = '#ffffff';
                                                        discountField.style.cursor = 'text';
                                                    } else {
                                                        discountField.setAttribute('readonly', 'readonly');
                                                        discountField.style.backgroundColor = '#e0e0e0';
                                                        discountField.style.cursor = 'not-allowed';
                                                        discountField.value = '0';
                                                    }
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Error checking discount permission:', error);
                                            });
                                    })
                                    .catch(error => {
                                        console.error('Error checking serial status:', error);
                                    });
                            }
                        } else {
                            alert(data.message || 'IMEI not found');
                            itemCodeInput.value = '';
                            itemDescInput.value = '';
                            priceInput.value = '';

                            // Re-enable price field when clearing
                            priceInput.removeAttribute('readonly');
                            priceInput.style.backgroundColor = '#ffffff';
                            priceInput.style.cursor = 'text';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while searching IMEI.');
                    });
            }

            // IMEI Input Event Listeners
            imeiInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    const imei = imeiInput.value.trim();
                    if (imei !== '') {
                        searchByIMEI(imei);
                    }
                }
            });

            let currentSearchResults = [];
            let selectedItemPrices = {};

            function performSearch() {
                const searchTerm = itemCodeInput.value.trim();
                const imei = imeiInput.value.trim();

                // If IMEI is entered, search by IMEI first
                if (imei !== '') {
                    searchByIMEI(imei);
                    return;
                }

                if (searchTerm === '') {
                    alert('Please input Item Code or IMEI!');
                    return;
                }

                // Fetch results
                fetch(`search_item.php?term=${encodeURIComponent(searchTerm)}`)
                    .then(response => response.json())
                    .then(data => {
                        resultsBody.innerHTML = '';
                        if (data.status === 'success' && data.data.length > 0) {
                            currentSearchResults = data.data; // Store results
                            data.data.forEach((item, index) => {
                                const row = `
                                <tr>
                                    <td>${item.item_code}</td>
                                    <td>${item.description}</td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-select" onclick="selectItem(${index})">Select</button>
                                    </td>
                                </tr>
                            `;
                                resultsBody.insertAdjacentHTML('beforeend', row);
                            });
                        } else {
                            resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">No items found</td></tr>';
                        }
                        modal.style.display = 'flex'; // Use flex to activate the centering styles
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while searching.');
                    });
            }

            searchBtn.addEventListener('click', performSearch);

            itemCodeInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault(); // Prevent form submission if inside a form
                    performSearch();
                }
            });

            function closeSearchModal() {
                modal.style.display = 'none';
            }

            function updateCardPaymentDropdowns() {
                const bankDropdown = document.getElementById('creditCardBankDropdown');
                const termsDropdown = document.getElementById('creditCardTermsDropdown');
                const amountInput = document.getElementById('creditCardAmount');

                if (!bankDropdown || !termsDropdown) return;

                // Clear existing options
                bankDropdown.innerHTML = '<option value="">Select Bank</option>';
                termsDropdown.innerHTML = '<option value="">Select Terms</option>';

                if (amountInput) {
                    amountInput.value = '';
                    amountInput.removeAttribute('readonly');
                }

                // Extract unique banks
                const banks = new Set();
                for (const key in selectedItemPrices) {
                    // Expect key format "Bank Term" (e.g. "BDO 3mos")
                    // Check if key has space
                    const spaceIndex = key.indexOf(' ');
                    if (spaceIndex !== -1) {
                        const bank = key.substring(0, spaceIndex);
                        // Filter out "Dealer's" if needed, but for now include all
                        banks.add(bank);
                    }
                }

                banks.forEach(bank => {
                    const option = document.createElement('option');
                    option.value = bank;
                    option.textContent = bank;
                    bankDropdown.appendChild(option);
                });
            }

            // Initialize listeners for Card Payment Dropdowns
            document.addEventListener('DOMContentLoaded', function () {
                const bankDropdown = document.getElementById('creditCardBankDropdown');
                const termsDropdown = document.getElementById('creditCardTermsDropdown');
                const amountInput = document.getElementById('creditCardAmount');

                if (bankDropdown) {
                    bankDropdown.addEventListener('change', function () {
                        const selectedBank = this.value;
                        if (termsDropdown) termsDropdown.innerHTML = '<option value="">Select Terms</option>';
                        if (amountInput) {
                            amountInput.value = '';
                            amountInput.removeAttribute('readonly');
                        }

                        if (selectedBank) {
                            const searchPrefix = selectedBank + ' ';
                            for (const key in selectedItemPrices) {
                                if (key.startsWith(searchPrefix)) {
                                    const term = key.substring(searchPrefix.length);
                                    const option = document.createElement('option');
                                    option.value = term;
                                    option.textContent = term;
                                    option.setAttribute('data-full-key', key);
                                    termsDropdown.appendChild(option);
                                }
                            }
                        }
                    });
                }

                if (termsDropdown) {
                    termsDropdown.addEventListener('change', function () {
                        const selectedOption = this.options[this.selectedIndex];
                        const fullKey = selectedOption.getAttribute('data-full-key');

                        if (fullKey && selectedItemPrices[fullKey]) {
                            const price = selectedItemPrices[fullKey];
                            if (amountInput) {
                                const formattedPrice = formatNumber(price);
                                amountInput.value = formattedPrice;
                                // Make it readonly as requested
                                amountInput.setAttribute('readonly', 'readonly');

                                // Update Total Field in the same section
                                const section = amountInput.closest('.credit-card-section');
                                if (section) {
                                    const totalInput = section.querySelector('.total-input');
                                    if (totalInput) {
                                        totalInput.value = formattedPrice;
                                    }
                                }
                            }
                        } else {
                            if (amountInput) {
                                amountInput.value = '';
                                amountInput.removeAttribute('readonly');
                            }
                            // Clear total when no term selected
                            const section = amountInput.closest('.credit-card-section');
                            if (section) {
                                const totalInput = section.querySelector('.total-input');
                                if (totalInput) {
                                    totalInput.value = '';
                                }
                            }
                        }
                    });
                }
            });

            window.selectItem = function (index) {
                const item = currentSearchResults[index];
                if (!item) return;

                // Store prices
                selectedItemPrices = item.prices || {};
                updateCardPaymentDropdowns();

                const code = item.item_code;
                const description = item.description;
                const price = item.price;
                const commission = item.commission;
                const has_commission = item.has_commission;
                const points = item.points;
                const has_points = item.has_points;
                const branchAllowed = (item.branch_allowed !== false);
                const selectedQty = Math.max(parseInt(qtyInput.value) || 0, 1);

                // Block selection immediately when item is not available for current branch context
                // (e.g. Superadmin account on ALL BRANCHES with no assigned stock branch pricing)
                if (!branchAllowed) {
                    alert('Insufficient stock no available');
                    return;
                }

                // Validate stock immediately when user clicks Select in modal
                fetch(`check_stock_availability.php?item_code=${encodeURIComponent(code)}&imei=&qty=${selectedQty}&force_branch=true`)
                    .then(response => response.json())
                    .then(stockData => {
                        if (stockData.status === 'error' || !stockData.available) {
                            alert(stockData.message || 'No stock available for this item.');
                            return;
                        }

                        // Check if item is serialized first
                        if (code) {
                            fetch(`check_serial_permission.php?item_code=${encodeURIComponent(code)}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === 'success' && data.has_serial) {
                                        // Item is serialized - show alert and clear fields
                                        alert('This item is serialized');
                                        itemCodeInput.value = '';
                                        itemDescInput.value = '';
                                        priceInput.value = '';
                                        const imeiField = document.getElementById('imei');
                                        imeiField.value = '';
                                        // Make IMEI field editable after alert (for manual IMEI entry)
                                        imeiField.removeAttribute('readonly');
                                        imeiField.style.backgroundColor = '#ffffff';
                                        imeiField.style.cursor = 'text';
                                        closeSearchModal();
                                        return;
                                    }

                                    // Item is not serialized - proceed normally but lock IMEI field
                                    itemCodeInput.value = code;
                                    itemDescInput.value = description;
                                    priceInput.value = formatNumber(price);
                                    priceInput.setAttribute('data-base-price', price || 0);
                                    applyPromoFreePriceIfEligible(code, description, qtyInput.value);

                                    // Store commission and points data as data attributes
                                    itemCodeInput.setAttribute('data-commission', commission || 0);
                                    itemCodeInput.setAttribute('data-has-commission', has_commission || 0);
                                    itemCodeInput.setAttribute('data-points', points || 0);
                                    itemCodeInput.setAttribute('data-has-points', has_points || 0);

                                    closeSearchModal();

                                    // Lock IMEI field for non-serialized items
                                    const imeiField = document.getElementById('imei');
                                    imeiField.setAttribute('readonly', 'readonly');
                                    imeiField.style.backgroundColor = '#f5f5f5';
                                    imeiField.style.cursor = 'not-allowed';
                                    imeiField.value = '';

                                    // Check discount permission
                                    fetch(`check_discount_permission.php?item_code=${encodeURIComponent(code)}`)
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.status === 'success') {
                                                const discountField = document.getElementById('discountField');
                                                if (data.discount_editable) {
                                                    discountField.removeAttribute('readonly');
                                                    discountField.style.backgroundColor = '#ffffff';
                                                    discountField.style.cursor = 'text';
                                                } else {
                                                    discountField.setAttribute('readonly', 'readonly');
                                                    discountField.style.backgroundColor = '#e0e0e0';
                                                    discountField.style.cursor = 'not-allowed';
                                                    discountField.value = '0';
                                                }
                                            }
                                        })
                                        .catch(error => {
                                            console.error('Error checking discount permission:', error);
                                        });
                                })
                                .catch(error => {
                                    console.error('Error checking serial status:', error);
                                });
                        }
                    })
                    .catch(error => {
                        console.error('Error checking stock availability on select:', error);
                        alert('Unable to verify stock availability. Please try again.');
                    });
            }

            const addButton = document.querySelector('.btn-add-item');
            const itemsTableBody = document.getElementById('itemsTableBody');
            const totalQtyField = document.getElementById('totalQty');
            const discountField = document.getElementById('discountField');
            const totalAmountField = document.getElementById('totalAmount');

            addButton.addEventListener('click', function () {
                const itemCode = itemCodeInput.value;
                const desc = itemDescInput.value;
                const imei = document.getElementById('imei').value;
                const isPromoFreeUnit = priceInput.getAttribute('data-is-promo-free') === '1';
                const basePrice = parseNumber(priceInput.getAttribute('data-base-price')) || parseNumber(priceInput.value) || 0;
                const price = isPromoFreeUnit ? 0 : basePrice;
                const qty = parseInt(qtyInput.value);

                // Get commission and points data
                const commission = parseFloat(itemCodeInput.getAttribute('data-commission')) || 0;
                const has_commission = parseInt(itemCodeInput.getAttribute('data-has-commission')) || 0;
                const points = parseFloat(itemCodeInput.getAttribute('data-points')) || 0;
                const has_points = parseInt(itemCodeInput.getAttribute('data-has-points')) || 0;

                if (!desc || (!isPromoFreeUnit && !price)) {
                    alert("Please select or enter an item description and price.");
                    return;
                }

                if (!itemCode || itemCode.trim() === '') {
                    alert("Please select a valid item code from search before adding.");
                    return;
                }

                if (!qty || qty <= 0) {
                    alert("Please input quantity.");
                    return;
                }

                // Check stock availability before adding
                console.log('=== ADD BUTTON CLICKED ===');
                console.log('Item Code:', itemCode);
                console.log('Description:', desc);
                console.log('IMEI:', imei);
                console.log('Price:', price);
                console.log('Quantity:', qty);

                const stockCheckUrl = `check_stock_availability.php?item_code=${encodeURIComponent(itemCode)}&imei=${encodeURIComponent(imei)}&qty=${qty}&force_branch=true`;
                console.log('Stock check URL:', stockCheckUrl);

                fetch(stockCheckUrl)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.text();
                    })
                    .then(text => {
                        console.log('Raw response:', text); // Debug log
                        try {
                            const stockData = JSON.parse(text);
                            console.log('Stock check response:', stockData); // Debug log

                            if (stockData.status === 'error' || !stockData.available) {
                                alert(stockData.message || 'This item is not available in stock!');

                                // Log debug info if available
                                if (stockData.debug) {
                                    console.log('Debug info:', stockData.debug);
                                }
                                if (stockData.sql) {
                                    console.log('SQL query:', stockData.sql);
                                }
                                return;
                            }

                            // Stock is available, proceed with adding the item
                            proceedWithAddingItem();
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            console.error('Response text:', text);
                            alert('Error parsing stock check response. Check console for details.');
                        }
                    })
                    .catch(error => {
                        console.error('Error checking stock:', error);
                        alert('Error checking stock availability: ' + error.message);
                    });

                function proceedWithAddingItem() {

                    // Check if item is serialized first
                    if (itemCode) {
                        // If IMEI was already looked up, skip the serialized-redirect guard
                        const imeiAlreadyFound = document.getElementById('imei').getAttribute('data-imei-found') === '1';

                        const proceedToAddRow = (commission, has_commission, points, has_points) => {
                            // Check for duplicate serial number in the table
                            if (imei && imei.trim() !== '') {
                                const existingRows = itemsTableBody.querySelectorAll('tr');
                                for (let row of existingRows) {
                                    const cells = row.querySelectorAll('td');
                                    if (cells.length >= 2) {
                                        const existingSerial = cells[1].textContent.trim();
                                        if (existingSerial === imei.trim()) {
                                            alert('This IMEI/Serial Number (' + imei + ') has already been added to this sales entry!');
                                            return;
                                        }
                                    }
                                }
                            }

                            const newRow = document.createElement('tr');
                            newRow.innerHTML = `
                        <td>${desc}</td>
                        <td>${imei}</td>
                        <td style="text-align: center;">${qty}<input type="hidden" class="qty-input" value="${qty}"></td>
                        <td style="text-align: center;">${isPromoFreeUnit ? 'FREE' : formatNumber(price)}<input type="hidden" class="price-input-table" value="${price}"></td>
                        <td style="text-align: center;"><button type="button" class="btn-delete-item" onclick="removeRow(this)">X</button></td>
                    `;
                            newRow.setAttribute('data-commission', commission);
                            newRow.setAttribute('data-has-commission', has_commission);
                            newRow.setAttribute('data-points', points);
                            newRow.setAttribute('data-has-points', has_points);
                            newRow.setAttribute('data-item-code', itemCode || ''); // Store item code
                            newRow.setAttribute('data-base-price', basePrice);

                            const noSalesRow = document.getElementById('no-sales-row');
                            if (noSalesRow) noSalesRow.remove();

                            itemsTableBody.appendChild(newRow);

                            // Clear inputs
                            itemCodeInput.value = '';
                            itemDescInput.value = '';
                            const imeiField = document.getElementById('imei');
                            imeiField.value = '';
                            imeiField.removeAttribute('data-imei-found');
                            imeiField.removeAttribute('readonly');
                            imeiField.style.backgroundColor = '#ffffff';
                            imeiField.style.cursor = 'text';
                            qtyInput.value = '0';
                            priceInput.value = '';
                            priceInput.removeAttribute('data-is-promo-free');
                            priceInput.removeAttribute('data-base-price');
                            priceInput.style.color = '#333';

                            // Re-enable price field when clearing
                            priceInput.removeAttribute('readonly');
                            priceInput.style.backgroundColor = '#ffffff';
                            priceInput.style.cursor = 'text';

                            itemCodeInput.removeAttribute('data-commission');
                            itemCodeInput.removeAttribute('data-has-commission');
                            itemCodeInput.removeAttribute('data-points');
                            itemCodeInput.removeAttribute('data-has-points');

                            reevaluateCartPromo();

                            // Check discount permission
                            fetch(`check_discount_permission.php?item_code=${encodeURIComponent(itemCode)}`)
                                .then(r => r.json())
                                .then(d => {
                                    if (d.status === 'success') {
                                        if (d.discount_editable) {
                                            // Only allow manual discount editing if no promo is active
                                            if (!activePromoItems || activePromoItems.length === 0) {
                                                discountField.removeAttribute('readonly');
                                                discountField.style.backgroundColor = '#ffffff';
                                                discountField.style.cursor = 'text';
                                            }
                                        } else {
                                            discountField.setAttribute('readonly', 'readonly');
                                            discountField.style.backgroundColor = '#e0e0e0';
                                            discountField.style.cursor = 'not-allowed';
                                            // Only reset to '0' if no promo is active
                                            if (!activePromoItems || activePromoItems.length === 0) {
                                                discountField.value = '0';
                                            }
                                        }
                                    }
                                })
                                .catch(err => console.error('Error checking discount:', err));
                        };

                        if (imeiAlreadyFound) {
                            // IMEI already verified — add directly, no serialized check needed
                            const commission = parseFloat(itemCodeInput.getAttribute('data-commission')) || 0;
                            const has_commission = parseInt(itemCodeInput.getAttribute('data-has-commission')) || 0;
                            const points = parseFloat(itemCodeInput.getAttribute('data-points')) || 0;
                            const has_points = parseInt(itemCodeInput.getAttribute('data-has-points')) || 0;
                            proceedToAddRow(commission, has_commission, points, has_points);
                            return;
                        }

                        fetch(`check_serial_permission.php?item_code=${encodeURIComponent(itemCode)}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success' && data.has_serial) {
                                    // Item is serialized - guide user to enter IMEI first
                                    alert('This item is serialized. Please enter the IMEI first.');
                                    itemCodeInput.value = '';
                                    itemDescInput.value = '';
                                    priceInput.value = '';
                                    const imeiField = document.getElementById('imei');
                                    imeiField.value = '';
                                    imeiField.removeAttribute('readonly');
                                    imeiField.style.backgroundColor = '#ffffff';
                                    imeiField.style.cursor = 'text';
                                    imeiField.focus();
                                    return;
                                }

                                // Item is not serialized — use the shared proceedToAddRow helper
                                proceedToAddRow(commission, has_commission, points, has_points);
                            })
                            .catch(error => {
                                console.error('Error checking serial status:', error);
                            });
                    } else {
                        // No item code - just add to table
                        // Check for duplicate serial number in the table
                        if (imei && imei.trim() !== '') {
                            const existingRows = itemsTableBody.querySelectorAll('tr');
                            for (let row of existingRows) {
                                const cells = row.querySelectorAll('td');
                                if (cells.length >= 2) {
                                    const existingSerial = cells[1].textContent.trim();
                                    if (existingSerial === imei.trim()) {
                                        alert('This IMEI/Serial Number (' + imei + ') has already been added to this sales entry!');
                                        return;
                                    }
                                }
                            }
                        }

                        const newRow = document.createElement('tr');
                        newRow.innerHTML = `
                    <td>${desc}</td>
                    <td>${imei}</td>
                    <td style="text-align: center;">${qty}<input type="hidden" class="qty-input" value="${qty}"></td>
                    <td style="text-align: center;">${isPromoFreeUnit ? 'FREE' : formatNumber(price)}<input type="hidden" class="price-input-table" value="${price}"></td>
                    <td style="text-align: center;"><button type="button" class="btn-delete-item" onclick="removeRow(this)">X</button></td>
                `;

                        // Store commission and points data in the row
                        newRow.setAttribute('data-commission', commission);
                        newRow.setAttribute('data-has-commission', has_commission);
                        newRow.setAttribute('data-points', points);
                        newRow.setAttribute('data-has-points', has_points);
                        newRow.setAttribute('data-item-code', itemCode || ''); // Store item code
                        newRow.setAttribute('data-base-price', basePrice);

                        const noSalesRow = document.getElementById('no-sales-row');
                        if (noSalesRow) {
                            noSalesRow.remove();
                        }

                        itemsTableBody.appendChild(newRow);

                        itemCodeInput.value = '';
                        itemDescInput.value = '';
                        document.getElementById('imei').value = '';
                        qtyInput.value = '0';
                        priceInput.value = '';
                        priceInput.removeAttribute('data-is-promo-free');
                        priceInput.removeAttribute('data-base-price');
                        priceInput.style.color = '#333';

                        // Re-enable price field when clearing
                        priceInput.removeAttribute('readonly');
                        priceInput.style.backgroundColor = '#ffffff';
                        priceInput.style.cursor = 'text';

                        // Clear data attributes
                        itemCodeInput.removeAttribute('data-commission');
                        itemCodeInput.removeAttribute('data-has-commission');
                        itemCodeInput.removeAttribute('data-points');
                        itemCodeInput.removeAttribute('data-has-points');

                        reevaluateCartPromo();
                    }
                } // End of proceedWithAddingItem function
            });

            window.removeRow = function (btn) {
                const row = btn.closest('tr');
                row.remove();

                // If table is empty, show "No sales entry yet!"
                if (itemsTableBody.children.length === 0) {
                    itemsTableBody.innerHTML = '<tr id="no-sales-row"><td colspan="5" style="text-align:center; padding: 20px;">No Sales Entry yet</td></tr>';
                }

                reevaluateCartPromo();
            }

            window.updateTotals = function () {
                let totalQty = 0;
                let grandTotal = 0;
                let totalCommission = 0;
                let totalPoints = 0;
                let hasAnyCommission = false;
                let hasAnyPoints = false;

                const rows = itemsTableBody.querySelectorAll('tr');
                rows.forEach(row => {
                    if (row.id === 'no-sales-row') return;
                    const qtyInputEl = row.querySelector('.qty-input');
                    const priceInputEl = row.querySelector('.price-input-table');
                    const cells = row.querySelectorAll('td');

                    if (cells.length >= 4) {
                        const qty = qtyInputEl ? (parseInt(qtyInputEl.value) || 0) : (parseInt(cells[2].textContent.trim()) || 0);
                        let price = 0;
                        if (priceInputEl) {
                            price = parseNumber(priceInputEl.value) || 0;
                        } else if (cells[3]) {
                            const pText = cells[3].textContent.trim().toUpperCase();
                            if (pText !== 'FREE') {
                                price = parseNumber(pText) || 0;
                            }
                        }

                        totalQty += qty;
                        grandTotal += (qty * price);

                        // Get commission and points data from row
                        const commission = parseFloat(row.getAttribute('data-commission')) || 0;
                        const has_commission = parseInt(row.getAttribute('data-has-commission')) || 0;
                        const points = parseFloat(row.getAttribute('data-points')) || 0;
                        const has_points = parseInt(row.getAttribute('data-has-points')) || 0;

                        if (has_commission === 1) {
                            totalCommission += commission;
                            hasAnyCommission = true;
                        }
                        if (has_points === 1) {
                            totalPoints += points;
                            hasAnyPoints = true;
                        }
                    }
                });

                // Note: Promo discounts are already applied to individual item prices in reevaluateCartPromo()
                // So we just use the grandTotal as-is without applying additional discounts
                const finalTotal = grandTotal;

                totalQtyField.value = totalQty;
                totalAmountField.value = formatNumber(Math.max(0, finalTotal));
                _originalTotalAmountDue = Math.max(0, finalTotal);

                if (typeof window.updateSectionTotal === 'function') {
                    window.updateSectionTotal();
                }

                // Update commission and points fields
                const commissionField = document.getElementById('commissionField');
                const pointsField = document.getElementById('pointsField');

                if (commissionField) {
                    commissionField.value = hasAnyCommission ? formatNumber(totalCommission) : '';
                }
                if (pointsField) {
                    pointsField.value = hasAnyPoints ? formatNumber(totalPoints) : '';
                }

                // Keep promo discount display at 0 until at least one unit exists.
                if (typeof activePromoItems !== 'undefined' && activePromoItems.length > 0) {
                    const discountField = document.getElementById('discountField');
                    if (discountField && totalQty > 0) {
                        // Calculate which promo benefits actually apply to items in the cart
                        let appliedDiscountPercent = 0;
                        let freeItemCount = 0;

                        activePromoItems.forEach(item => {
                            if (item.discount_type === '%') {
                                const pct = parseFloat(item.discount_value) || 0;
                                appliedDiscountPercent += pct;
                            }
                        });

                        rows.forEach(row => {
                            const priceInputEl = row.querySelector('.price-input-table');
                            const priceVal = parseNumber(priceInputEl ? priceInputEl.value : '0') || 0;
                            const priceTd = row.querySelectorAll('td')[3];
                            const isFreeText = priceTd && priceTd.textContent.trim().toUpperCase() === 'FREE';
                            if (priceVal === 0 || isFreeText) {
                                const qtyEl = row.querySelector('.qty-input');
                                freeItemCount += parseInt(qtyEl ? qtyEl.value : 0) || 0;
                            }
                        });

                        // Build discount label based on what's actually applied
                        let discountLabel = [];
                        if (freeItemCount > 0) {
                            discountLabel.push(freeItemCount + ' Free Item' + (freeItemCount > 1 ? 's' : ''));
                        }
                        if (appliedDiscountPercent > 0) discountLabel.push(appliedDiscountPercent + '% Off');

                        discountField.value = discountLabel.length > 0 ? discountLabel.join(' + ') : '0';
                    } else if (discountField) {
                        discountField.value = '0';
                    }
                }
            }

            // Initialize modal close logic
            window.onclick = function (event) {
                if (event.target == modal) {
                    closeSearchModal();
                }
            }

            // Stores the original total amount due when the payment modal opens
            let _originalTotalAmountDue = 0;

            /**
             * Populate the Unit selector inside the Home Credit section.
             * - 2+ items → show dropdown with all items
             * - 1 item   → auto-select, show read-only text
             * - 0 items  → hide the row
             */
            function populateInstallmentUnit() {
                const tbody = document.getElementById('itemsTableBody');
                if (!tbody) return;

                const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.id || r.id !== 'no-sales-row');
                const unitRows = document.querySelectorAll('.unit-selector-row');

                if (unitRows.length === 0) return;

                // Build item list from table rows
                const items = [];
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 2) {
                        const desc = cells[0].textContent.trim();
                        const serial = cells[1].textContent.trim();
                        if (desc) items.push({ desc, serial });
                    }
                });

                unitRows.forEach(unitRow => {
                    if (items.length === 0) {
                        unitRow.style.display = 'none';
                        unitRow.innerHTML = '';
                        return;
                    }

                    unitRow.style.display = '';
                    // Container for custom multi-select dropdown
                    unitRow.innerHTML = `
                        <label style="min-width: 120px;">Unit:</label>
                        <div class="custom-multiselect" style="position: relative; flex: 1; min-width: 200px;">
                            <div class="multiselect-selected hc-input" style="cursor: pointer; background: ${items.length === 1 ? '#f5f5f5' : '#fff'}; display: flex; align-items: center; justify-content: space-between;" onclick="const d = this.nextElementSibling; d.style.display = d.style.display === 'none' ? 'block' : 'none';">
                                <span class="selected-text" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 10px;">-- Select Units --</span>
                                <span style="font-size: 10px;">▼</span>
                            </div>
                            <div class="unit-checkboxes multiselect-dropdown" style="display: none; position: absolute; top: calc(100% + 2px); left: 0; right: 0; background: white; border: 1px solid #bfbfbf; border-radius: 4px; max-height: 150px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 5px;"></div>
                        </div>
                    `;
                    const container = unitRow.querySelector('.unit-checkboxes');
                    const textSpan = unitRow.querySelector('.selected-text');

                    const updateText = () => {
                        const checked = Array.from(container.querySelectorAll('input[type="checkbox"][name="Unit"]:checked'));
                        const allUnitCbs = Array.from(container.querySelectorAll('input[type="checkbox"][name="Unit"]'));
                        const selectAllCb = container.querySelector('.select-all-units-cb');

                        if (selectAllCb && allUnitCbs.length > 0) {
                            selectAllCb.checked = (checked.length === allUnitCbs.length);
                        }

                        if (checked.length === 0) textSpan.textContent = '-- Select Units --';
                        else if (checked.length === 1) textSpan.textContent = checked[0].value;
                        else if (allUnitCbs.length > 1 && checked.length === allUnitCbs.length) textSpan.textContent = 'All Units Selected (' + checked.length + ')';
                        else textSpan.textContent = checked.length + ' Units Selected';
                    };

                    // Add "Select All" option if there are multiple units
                    if (items.length > 1) {
                        const selectAllLbl = document.createElement('label');
                        selectAllLbl.style.margin = '0 0 5px 0';
                        selectAllLbl.style.display = 'flex';
                        selectAllLbl.style.alignItems = 'center';
                        selectAllLbl.style.gap = '8px';
                        selectAllLbl.style.fontWeight = 'bold';
                        selectAllLbl.style.lineHeight = '1.2';
                        selectAllLbl.style.color = '#1e40af';
                        selectAllLbl.style.cursor = 'pointer';
                        selectAllLbl.style.padding = '5px';
                        selectAllLbl.style.borderBottom = '1px solid #e5e7eb';
                        selectAllLbl.style.borderRadius = '3px';
                        selectAllLbl.onmouseover = () => selectAllLbl.style.backgroundColor = '#eff6ff';
                        selectAllLbl.onmouseout = () => selectAllLbl.style.backgroundColor = 'transparent';

                        const selectAllCb = document.createElement('input');
                        selectAllCb.type = 'checkbox';
                        selectAllCb.className = 'select-all-units-cb';
                        selectAllCb.checked = true;
                        selectAllCb.style.marginTop = '0';
                        selectAllCb.style.width = '16px';
                        selectAllCb.style.height = '16px';

                        selectAllCb.addEventListener('change', function () {
                            const unitCbs = container.querySelectorAll('input[type="checkbox"][name="Unit"]');
                            unitCbs.forEach(cb => cb.checked = this.checked);
                            updateText();
                        });

                        selectAllLbl.appendChild(selectAllCb);
                        selectAllLbl.appendChild(document.createTextNode('Select All'));
                        container.appendChild(selectAllLbl);
                    }

                    items.forEach((item) => {
                        const labelText = item.serial ? item.desc + ' (' + item.serial + ')' : item.desc;

                        const lbl = document.createElement('label');
                        lbl.style.margin = '0';
                        lbl.style.display = 'flex';
                        lbl.style.alignItems = 'flex-start';
                        lbl.style.gap = '8px';
                        lbl.style.fontWeight = 'normal';
                        lbl.style.lineHeight = '1.2';
                        lbl.style.color = '#333';
                        lbl.style.cursor = 'pointer';
                        lbl.style.padding = '5px';
                        lbl.style.borderRadius = '3px';
                        lbl.onmouseover = () => lbl.style.backgroundColor = '#f0f0f0';
                        lbl.onmouseout = () => lbl.style.backgroundColor = 'transparent';

                        const cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.name = 'Unit';
                        cb.value = labelText;
                        cb.style.marginTop = '2px';
                        cb.style.width = '16px';
                        cb.style.height = '16px';

                        cb.addEventListener('change', updateText);

                        // Auto-check all items by default
                        cb.checked = true;

                        lbl.appendChild(cb);
                        lbl.appendChild(document.createTextNode(labelText));
                        container.appendChild(lbl);
                    });

                    updateText(); // Initial text population
                });
            }

            // Close dropdowns when clicking outside
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.custom-multiselect')) {
                    document.querySelectorAll('.multiselect-dropdown').forEach(d => d.style.display = 'none');
                }
            });

            function openPaymentModal() {
                // ── VALIDATE PROMO ITEMS BEFORE OPENING PAYMENT MODAL ──────────────────
                const promoSelect = document.getElementById('applied_promo');
                const promoId = promoSelect ? promoSelect.value : null;

                if (promoId && activePromoItems && activePromoItems.length > 0) {
                    // Check if any promo item with type "Free" exists
                    const freePromoItems = activePromoItems.filter(item => item.discount_type === 'Free');

                    if (freePromoItems.length > 0) {
                        // Get all items in the cart
                        const itemsTableBody = document.getElementById('itemsTableBody');
                        const cartRows = itemsTableBody ? itemsTableBody.querySelectorAll('tr') : [];

                        // Build a list of item codes in the cart
                        const cartItemCodes = [];
                        cartRows.forEach(row => {
                            const itemCode = row.getAttribute('data-item-code');
                            if (itemCode && itemCode.trim() !== '' && row.id !== 'no-sales-row') {
                                cartItemCodes.push(itemCode.toUpperCase().trim());
                            }
                        });

                        // Check if at least one free promo item is in the cart
                        let hasPromoItem = false;
                        for (const freeItem of freePromoItems) {
                            const promoItemCode = (freeItem.promo_item || '').toUpperCase().trim();
                            if (promoItemCode && cartItemCodes.includes(promoItemCode)) {
                                hasPromoItem = true;
                                break;
                            }
                        }

                        // If no promo items found in cart, show error
                        if (!hasPromoItem) {
                            const promoName = promoSelect.options[promoSelect.selectedIndex].text;

                            // Build detailed promo requirements message
                            let promoDetails = '';
                            freePromoItems.forEach((item, index) => {
                                if (index > 0) promoDetails += '\n';
                                const mainItem = item.motor_model || 'Main Item';
                                const freeItem = item.promo_item || 'Free Item';
                                promoDetails += '   • Buy: ' + mainItem + ' → Get FREE: ' + freeItem;
                            });

                            alert(
                                'PROMO VALIDATION ERROR!\n\n' +
                                'You have applied the promo: "' + promoName + '"\n' +
                                'However, you did not add any of the required promo items to the cart.\n\n' +
                                'Promo Requirements:\n' +
                                promoDetails + '\n\n' +
                                'Please either:\n' +
                                '1. Add the required promo item(s) to the cart, OR\n' +
                                '2. Remove/unapply the promo by selecting "-- No Promo / Select Promo --" from the Promo dropdown'
                            );
                            return; // Prevent payment modal from opening
                        }
                    }
                }
                // ─────────────────────────────────────────────────────────────────────────

                const modal = document.getElementById('paymentModal');
                modal.style.display = 'block';
                // Clear any stale error banner
                const banner = document.getElementById('paymentErrorBanner');
                if (banner) { banner.style.display = 'none'; banner.innerHTML = ''; }
                const totalAmountInput = document.getElementById('totalAmount');
                const globalTotalDueInput = document.getElementById('globalTotalDueInput');
                if (totalAmountInput && globalTotalDueInput) {
                    const rawValue = totalAmountInput.value || '0';
                    _originalTotalAmountDue = parseFloat(rawValue.replace(/,/g, '')) || 0;
                    globalTotalDueInput.value = totalAmountInput.value || '0.00';
                }
                // Populate the installment unit picker
                populateInstallmentUnit();

                if (typeof window.updateSectionTotal === 'function') {
                    window.updateSectionTotal();
                }
            }

            function closePaymentModal() {
                const modal = document.getElementById('paymentModal');
                modal.style.display = 'none';
            }

            // Close modal when clicking outside
            window.onclick = function (event) {
                const searchModal = document.getElementById('searchItemModal');
                const paymentModal = document.getElementById('paymentModal');
                if (event.target == searchModal) {
                    searchModal.style.display = 'none';
                }
                if (event.target == paymentModal) {
                    paymentModal.style.display = 'none';
                }
            }

            // Payment Partners Dropdown Handler
            document.addEventListener('DOMContentLoaded', function () {
                // Dropdowns
                const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
                const cardPaymentDropdown = document.getElementById('cardPaymentDropdown');
                const qrDropdown = document.getElementById('qrDropdown');

                // Radio Buttons
                const paymentRadios = document.querySelectorAll('input[name="payment_method"]');

                // Sections
                const homeCreditSection = document.querySelector('.home-credit-section');
                const creditCardSection = document.querySelector('.credit-card-section');
                const debitCardSection = document.querySelector('.debit-card-section');
                const qrPhSection = document.querySelector('.qr-ph-section');
                const starpayQrSection = document.querySelector('.starpay-qr-section');
                const ewalletSection = document.querySelector('.ewallet-section');
                const onlineBankingSection = document.querySelector('.online-banking-section');
                const cashSection = document.querySelector('.cash-section');

                if (paymentPartnersDropdown) {
                    paymentPartnersDropdown.addEventListener('change', function () {
                        const chkPP = document.getElementById('chkPaymentPartners');
                        if (this.value !== '') {
                            chkPP.checked = true;
                        }
                        if (chkPP.checked) {
                            if (homeCreditSection) homeCreditSection.style.display = 'none';
                            const partnerTitle = document.getElementById('paymentPartnerTitle');
                            const loanTypeSelect = homeCreditSection ? homeCreditSection.querySelector('.hc-form-group:nth-child(2) select') : null;
                            if (this.value === 'partner2') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Home Credit';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="0_installment">0% Installment</option><option value="standard_loan">Standard loan</option><option value="retailer_zero">Retailer Zero</option><option value="saver_plan">Saver Plan</option>';
                                }
                            } else if (this.value === 'partner5') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Salmon';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                                }
                            } else if (this.value === 'partner6') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Samsung Finances';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                                }
                            } else if (this.value === 'partner7') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Payjoy';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                                }
                            } else if (this.value === 'partner8') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Billease';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                                }
                            } else if (this.value === 'partner9') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Paymongo';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                                }
                            } else if (this.value === 'partner10') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Skyro';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                                }
                            }
                        }
                    });
                }

                // Card Payment Dropdown Handler
                if (cardPaymentDropdown) {
                    cardPaymentDropdown.addEventListener('change', function () {
                        if (this.disabled) return false;
                        const chkCard = document.getElementById('chkCardPayment');
                        if (this.value !== '') {
                            chkCard.checked = true;
                        }
                        if (chkCard.checked) {
                            if (creditCardSection) creditCardSection.style.display = 'none';
                            if (debitCardSection) debitCardSection.style.display = 'none';
                            if (this.value === 'credit_card') {
                                if (creditCardSection) creditCardSection.style.display = 'block';
                            } else if (this.value === 'debit_card') {
                                if (debitCardSection) debitCardSection.style.display = 'block';
                            }
                        }
                    });
                }

                // QR Dropdown Handler
                if (qrDropdown) {
                    qrDropdown.addEventListener('change', function () {
                        if (this.disabled) return false;
                        const chkQR = document.getElementById('chkQR');
                        if (this.value !== '') {
                            chkQR.checked = true;
                        }
                        if (chkQR.checked) {
                            if (qrPhSection) qrPhSection.style.display = 'none';
                            if (starpayQrSection) starpayQrSection.style.display = 'none';
                            if (this.value === 'qr_ph') {
                                if (qrPhSection) qrPhSection.style.display = 'block';
                            } else if (this.value === 'starpay_qr') {
                                if (starpayQrSection) starpayQrSection.style.display = 'block';
                            }
                        }
                    });
                }

                // Checkbox Handlers for Split Payment
                paymentRadios.forEach(checkbox => {
                    checkbox.addEventListener('change', function () {
                        if (this.disabled) return false;

                        if (this.value === 'payment_partners' || this.id === 'chkPaymentPartners') {
                            if (this.checked) {
                                if (paymentPartnersDropdown.value === '') {
                                    paymentPartnersDropdown.value = 'partner2';
                                }
                                const event = new Event('change');
                                paymentPartnersDropdown.dispatchEvent(event);
                            } else {
                                if (homeCreditSection) homeCreditSection.style.display = 'none';
                            }
                        } else if (this.value === 'card_payment' || this.id === 'chkCardPayment') {
                            if (this.checked) {
                                if (cardPaymentDropdown.value === '') {
                                    cardPaymentDropdown.value = 'credit_card';
                                }
                                const event = new Event('change');
                                cardPaymentDropdown.dispatchEvent(event);
                            } else {
                                if (creditCardSection) creditCardSection.style.display = 'none';
                                if (debitCardSection) debitCardSection.style.display = 'none';
                            }
                        } else if (this.value === 'qr' || this.id === 'chkQR') {
                            if (this.checked) {
                                if (qrDropdown.value === '') {
                                    qrDropdown.value = 'qr_ph';
                                }
                                const event = new Event('change');
                                qrDropdown.dispatchEvent(event);
                            } else {
                                if (qrPhSection) qrPhSection.style.display = 'none';
                                if (starpayQrSection) starpayQrSection.style.display = 'none';
                            }
                        } else {
                            const targetSection =
                                this.value === 'ewallet' ? ewalletSection :
                                    this.value === 'online_banking' ? onlineBankingSection :
                                        this.value === 'cash' ? cashSection : null;

                            if (targetSection) {
                                targetSection.style.display = this.checked ? 'block' : 'none';
                            }
                        }
                    });
                });
            });

        </script>
        <script>
            // Number Formatting Utilities
            function formatNumber(num) {
                if (num === '' || num === null || num === undefined) return '';
                // Convert to float if string
                const val = typeof num === 'string' ? parseFloat(num.replace(/,/g, '')) : num;
                if (isNaN(val)) return '';

                // Format with commas and 2 decimal places
                return val.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function formatInput(input) {
                // Save cursor position
                let cursorPosition = input.selectionStart;
                let oldValLength = input.value.length;

                // Remove non-numeric chars except dot
                let val = input.value.replace(/[^0-9.]/g, '');

                // Handle multiple dots
                const parts = val.split('.');
                if (parts.length > 2) {
                    val = parts[0] + '.' + parts.slice(1).join('');
                }

                // Add commas to integer part
                if (parts[0].length > 3) {
                    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                }

                const newVal = parts.join('.');
                input.value = newVal;

                // Restore cursor position accounting for added/removed commas
                if (document.activeElement === input) {
                    let diff = newVal.length - oldValLength;
                    let newPos = cursorPosition + diff;
                    // Keep cursor within bounds
                    newPos = Math.max(0, Math.min(newPos, newVal.length));
                    input.setSelectionRange(newPos, newPos);
                }
            }

            function parseNumber(str) {
                if (!str) return 0;
                return parseFloat(str.replace(/,/g, '')) || 0;
            }

            document.addEventListener('DOMContentLoaded', function () {
                // 1. Attach to Total Amount Field (for manual edits if any)
                const totalAmountField = document.getElementById('totalAmount');
                if (totalAmountField) {
                    totalAmountField.addEventListener('input', function () { formatInput(this); });
                }

                // 2. Attach to Discount Field
                const discountField = document.getElementById('discountField');
                if (discountField) {
                    discountField.addEventListener('input', function () { formatInput(this); });
                }

                // 2b. Attach to Main Price Input Field
                const priceField = document.getElementById('price');
                if (priceField) {
                    priceField.addEventListener('input', function () { formatInput(this); });
                }

                // 3. Attach to All Payment Amount Inputs
                // Includes inputs with class 'amount-input' and specific IDs like 'creditCardAmount'
                const paymentInputs = document.querySelectorAll('.amount-input, #creditCardAmount, .hc-input');

                paymentInputs.forEach(input => {
                    // Heuristic: Only target inputs that look like amount fields
                    // Check if label contains "Amount"
                    const label = input.closest('.hc-form-group')?.querySelector('label')?.textContent;
                    const parentLabel = input.closest('.enter-amount-row')?.querySelector('label')?.textContent;

                    if ((label && (label.includes('Amount') || label.includes('Balance'))) || (parentLabel && parentLabel.includes('Amount')) || input.id === 'creditCardAmount') {
                        input.addEventListener('input', function () { formatInput(this); });

                        // Also toggle type to text if it was number
                        if (input.type === 'number') input.type = 'text';
                    }
                });
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const saveBtn = document.querySelector('.btn-save-modal');

                if (saveBtn) {
                    saveBtn.addEventListener('click', savePaymentData);
                }

                function savePaymentData() {
                    // First, check which payment method is being used
                    const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
                    const cardPaymentDropdown = document.getElementById('cardPaymentDropdown');
                    const qrDropdown = document.getElementById('qrDropdown');
                    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');

                    // Check which section is visible
                    const homeCreditSection = document.querySelector('.home-credit-section');
                    const creditCardSection = document.querySelector('.credit-card-section');
                    const debitCardSection = document.querySelector('.debit-card-section');
                    const qrPhSection = document.querySelector('.qr-ph-section');
                    const starpayQrSection = document.querySelector('.starpay-qr-section');
                    const ewalletSection = document.querySelector('.ewallet-section');
                    const onlineBankingSection = document.querySelector('.online-banking-section');
                    const cashSection = document.querySelector('.cash-section');

                    // Determine which payment methods are active
                    let hasActiveSection = false;

                    if (homeCreditSection && homeCreditSection.style.display === 'block') hasActiveSection = true;
                    if (creditCardSection && creditCardSection.style.display === 'block') hasActiveSection = true;
                    if (debitCardSection && debitCardSection.style.display === 'block') hasActiveSection = true;
                    if (qrPhSection && qrPhSection.style.display === 'block') hasActiveSection = true;
                    if (starpayQrSection && starpayQrSection.style.display === 'block') hasActiveSection = true;
                    if (ewalletSection && ewalletSection.style.display === 'block') hasActiveSection = true;
                    if (onlineBankingSection && onlineBankingSection.style.display === 'block') hasActiveSection = true;
                    if (cashSection && cashSection.style.display === 'block') hasActiveSection = true;

                    if (!hasActiveSection) {
                        alert('Please select a payment method.');
                        return;
                    }

                    // Validate based on active payment sections
                    if (homeCreditSection && homeCreditSection.style.display === 'block') {
                        if (paymentPartnersDropdown && paymentPartnersDropdown.value === '') {
                            alert('Please choose a Payment Partner option in order to proceed!');
                            paymentPartnersDropdown.focus();
                            return;
                        }

                        // Validate Home Credit (STO ninio de cebu) fields
                        if (homeCreditSection && homeCreditSection.style.display === 'block') {
                            const loanTypeSelect = homeCreditSection.querySelector('.hc-form-group:nth-child(2) select');
                            const loanTermsSelect = homeCreditSection.querySelector('.hc-form-group:nth-child(3) select');
                            const customerNameInput = homeCreditSection.querySelector('.hc-form-group:nth-child(4) input');
                            const loanNumberInput = homeCreditSection.querySelector('.hc-form-group:nth-child(5) input');
                            const loanBalanceInput = homeCreditSection.querySelector('.hc-form-group:nth-child(6) input');
                            const cashCheckbox = homeCreditSection.querySelector('input[name="down_payment_method"][value="cash"]');
                            const gcashCheckbox = homeCreditSection.querySelector('input[name="down_payment_method"][value="gcash"]');
                            const mayaCheckbox = homeCreditSection.querySelector('input[name="down_payment_method"][value="maya"]');
                            const gcashRefInput = document.getElementById('gcash_down_payment_reference');
                            const mayaRefInput = document.getElementById('maya_down_payment_reference');
                            const cashAmountInput = document.getElementById('cash_down_payment_amount');
                            const gcashAmountInput = document.getElementById('gcash_down_payment_amount');
                            const mayaAmountInput = document.getElementById('maya_down_payment_amount');

                            // Validate Loan Type
                            if (!loanTypeSelect || !loanTypeSelect.value || loanTypeSelect.value.trim() === '') {
                                alert('LOAN TYPE REQUIRED! Please select a loan type.');
                                if (loanTypeSelect) loanTypeSelect.focus();
                                return;
                            }

                            // Validate Loan Terms
                            if (!loanTermsSelect || !loanTermsSelect.value || loanTermsSelect.value.trim() === '') {
                                alert('LOAN TERMS REQUIRED! Please select loan terms.');
                                if (loanTermsSelect) loanTermsSelect.focus();
                                return;
                            }

                            // Validate Customer's Name
                            if (!customerNameInput || !customerNameInput.value || customerNameInput.value.trim() === '') {
                                alert("CUSTOMER'S NAME REQUIRED! Please enter the customer's name.");
                                if (customerNameInput) customerNameInput.focus();
                                return;
                            }

                            // Validate Loan Number
                            if (!loanNumberInput || !loanNumberInput.value || loanNumberInput.value.trim() === '') {
                                alert('LOAN NUMBER REQUIRED! Please enter the loan number.');
                                if (loanNumberInput) loanNumberInput.focus();
                                return;
                            }

                            // Validate Loan Balance
                            if (!loanBalanceInput || !loanBalanceInput.value || loanBalanceInput.value.trim() === '') {
                                alert('LOAN BALANCE REQUIRED! Please enter the loan balance.');
                                if (loanBalanceInput) loanBalanceInput.focus();
                                return;
                            }

                            // Validate Down Payment Method (at least one checkbox must be checked)
                            const isAnyDownPaymentChecked = ((cashCheckbox && cashCheckbox.checked) || (gcashCheckbox && gcashCheckbox.checked) || (mayaCheckbox && mayaCheckbox.checked));
                            if (!isAnyDownPaymentChecked) {
                                alert('DOWN PAYMENT METHOD REQUIRED! Please check at least one payment method (Cash, G-Cash, or Maya).');
                                return;
                            }

                            // Validate Cash Amount if Cash is checked
                            if (cashCheckbox && cashCheckbox.checked) {
                                if (!cashAmountInput || !cashAmountInput.value || cashAmountInput.value.trim() === '') {
                                    alert('ENTER AMOUNT REQUIRED! Please enter the Cash down payment amount.');
                                    if (cashAmountInput) cashAmountInput.focus();
                                    return;
                                }
                            }

                            // Validate G-Cash Reference Number & Amount if G-Cash is checked
                            if (gcashCheckbox && gcashCheckbox.checked) {
                                if (!gcashRefInput || !gcashRefInput.value || gcashRefInput.value.trim() === '') {
                                    alert('G-CASH REFERENCE NUMBER REQUIRED! Please enter the G-Cash reference number.');
                                    if (gcashRefInput) gcashRefInput.focus();
                                    return;
                                }
                                if (!gcashAmountInput || !gcashAmountInput.value || gcashAmountInput.value.trim() === '') {
                                    alert('ENTER AMOUNT REQUIRED! Please enter the G-Cash down payment amount.');
                                    if (gcashAmountInput) gcashAmountInput.focus();
                                    return;
                                }
                            }

                            // Validate Maya Reference Number & Amount if Maya is checked
                            if (mayaCheckbox && mayaCheckbox.checked) {
                                if (!mayaRefInput || !mayaRefInput.value || mayaRefInput.value.trim() === '') {
                                    alert('MAYA REFERENCE NUMBER REQUIRED! Please enter the Maya reference number.');
                                    if (mayaRefInput) mayaRefInput.focus();
                                    return;
                                }
                                if (!mayaAmountInput || !mayaAmountInput.value || mayaAmountInput.value.trim() === '') {
                                    alert('ENTER AMOUNT REQUIRED! Please enter the Maya down payment amount.');
                                    if (mayaAmountInput) mayaAmountInput.focus();
                                    return;
                                }
                            }

                            // Global mismatch validation is now handled at the end of savePaymentData
                        } // end inner if (homeCreditSection)
                    } // end outer if (payment_partners)
                    if ((creditCardSection && creditCardSection.style.display === 'block') || (debitCardSection && debitCardSection.style.display === 'block')) {
                        if (cardPaymentDropdown && cardPaymentDropdown.value === '') {
                            alert('Please choose a Card Payment option in order to proceed!');
                            cardPaymentDropdown.focus();
                            return;
                        }
                    }
                    if ((qrPhSection && qrPhSection.style.display === 'block') || (starpayQrSection && starpayQrSection.style.display === 'block')) {
                        if (qrDropdown && qrDropdown.value === '') {
                            alert('Please choose a QR option in order to proceed!');
                            qrDropdown.focus();
                            return;
                        }
                    }
                    if (ewalletSection && ewalletSection.style.display === 'block') {
                        // Validate E-Wallet fields
                        if (ewalletSection && ewalletSection.style.display === 'block') {
                            const ewalletSelect = ewalletSection.querySelector('.hc-form-group:nth-child(2) select');
                            const customerNameInput = ewalletSection.querySelector('.hc-form-group:nth-child(3) input');
                            const referenceNoInput = ewalletSection.querySelector('.hc-form-group:nth-child(4) input');
                            const amountInput = ewalletSection.querySelector('.hc-form-group:nth-child(5) input');

                            // Validate E-Wallet selection
                            if (!ewalletSelect || !ewalletSelect.value || ewalletSelect.value.trim() === '') {
                                alert('E-WALLET REQUIRED! Please select an e-wallet.');
                                if (ewalletSelect) ewalletSelect.focus();
                                return;
                            }

                            // Get selected e-wallet name for dynamic error message
                            const selectedEwalletName = ewalletSelect.options[ewalletSelect.selectedIndex].text;

                            // Validate Customer's Name
                            if (!customerNameInput || !customerNameInput.value || customerNameInput.value.trim() === '') {
                                alert("CUSTOMER'S NAME REQUIRED! Please enter the customer's name.");
                                if (customerNameInput) customerNameInput.focus();
                                return;
                            }

                            // Validate Reference Number with e-wallet-specific message
                            if (!referenceNoInput || !referenceNoInput.value || referenceNoInput.value.trim() === '') {
                                alert(selectedEwalletName.toUpperCase() + ' REFERENCE NUMBER REQUIRED! Please enter the ' + selectedEwalletName + ' reference number.');
                                if (referenceNoInput) referenceNoInput.focus();
                                return;
                            }

                            // Validate Amount
                            if (!amountInput || !amountInput.value || amountInput.value.trim() === '') {
                                alert('AMOUNT REQUIRED! Please enter the payment amount.');
                                if (amountInput) amountInput.focus();
                                return;
                            }
                        }
                    }
                    if (onlineBankingSection && onlineBankingSection.style.display === 'block') {
                        // Validate Online Banking fields
                        if (onlineBankingSection && onlineBankingSection.style.display === 'block') {
                            const bankSelect = onlineBankingSection.querySelector('.hc-form-group:nth-child(2) select');
                            const referenceNoInput = onlineBankingSection.querySelector('.hc-form-group:nth-child(3) input');
                            const amountInput = onlineBankingSection.querySelector('.hc-form-group:nth-child(4) input');

                            // Validate Bank selection
                            if (!bankSelect || !bankSelect.value || bankSelect.value.trim() === '') {
                                alert('BANK REQUIRED! Please select a bank.');
                                if (bankSelect) bankSelect.focus();
                                return;
                            }

                            // Get selected bank name for dynamic error message
                            const selectedBankName = bankSelect.options[bankSelect.selectedIndex].text;

                            // Validate Reference Number with bank-specific message
                            if (!referenceNoInput || !referenceNoInput.value || referenceNoInput.value.trim() === '') {
                                alert(selectedBankName.toUpperCase() + ' REFERENCE NUMBER REQUIRED! Please enter the ' + selectedBankName + ' reference number.');
                                if (referenceNoInput) referenceNoInput.focus();
                                return;
                            }

                            // Validate Amount
                            if (!amountInput || !amountInput.value || amountInput.value.trim() === '') {
                                alert('AMOUNT REQUIRED! Please enter the payment amount.');
                                if (amountInput) amountInput.focus();
                                return;
                            }
                        }
                    }
                    if (cashSection && cashSection.style.display === 'block') {
                        // Cash validation will be handled by hasValues check below
                    }

                    const data = {};
                    let sectionName = '';
                    let isValid = false;
                    let hasValues = false; // Flag to track if any input has a value

                    function collectData(sectionClass, type) {
                        const section = document.querySelector(sectionClass);
                        // Check if section is visible (style.display is set to 'block' by the toggle logic)
                        if (section && section.style.display === 'block') {
                            if (sectionName) {
                                sectionName += ' + ' + type;
                            } else {
                                sectionName = type;
                            }
                            data.payment_type = sectionName;
                            isValid = true;

                            // Select all inputs and selects
                            const inputs = section.querySelectorAll('input, select');

                            inputs.forEach(input => {
                                // Skip hidden inputs
                                if (input.type === 'hidden') return;

                                let key = input.id;

                                // Try to derive key from label
                                if (!key) {
                                    const formGroup = input.closest('.hc-form-group');
                                    if (formGroup) {
                                        const label = formGroup.querySelector('label');
                                        if (label) {
                                            key = label.innerText.replace(':', '').trim();
                                        }
                                    }

                                    // Fallback for specialized rows like Enter Amount
                                    if (!key) {
                                        const parentRow = input.closest('.enter-amount-row');
                                        if (parentRow) {
                                            const label = parentRow.querySelector('label');
                                            if (label) key = label.innerText.replace(':', '').trim();
                                        }
                                    }
                                }

                                // Fallback to name or class
                                if (!key && input.name) key = input.name;
                                if (!key && input.className) key = input.className;

                                if (!key) return; // Skip if no key found

                                // Handle Checkboxes and Radios
                                if (input.type === 'checkbox' || input.type === 'radio') {
                                    if (input.checked) {
                                        hasValues = true; // Checked item means user managed input
                                        // Special handling for payment method radios which are structural
                                        if (input.name === 'payment_method') return;

                                        if (data[key]) {
                                            data[key] += ', ' + input.value;
                                        } else {
                                            data[key] = input.value;
                                        }
                                    }
                                } else {
                                    // Text, Number, Select
                                    if (input.tagName === 'SELECT') {
                                        data[key] = data[key] ? data[key] + ' | ' + input.value : input.value;
                                        // For E-Wallet dropdown, also capture the display text
                                        if (key === 'E-Wallet' && input.selectedIndex > 0) {
                                            data['E-Wallet-Text'] = data['E-Wallet-Text'] ? data['E-Wallet-Text'] + ' | ' + input.options[input.selectedIndex].text : input.options[input.selectedIndex].text;
                                        }
                                        // For Online Banking Bank dropdown, also capture the display text
                                        if (key === 'Bank' && input.selectedIndex > 0) {
                                            data['Bank-Text'] = data['Bank-Text'] ? data['Bank-Text'] + ' | ' + input.options[input.selectedIndex].text : input.options[input.selectedIndex].text;
                                        }
                                    } else {
                                        data[key] = data[key] ? data[key] + ' | ' + input.value : input.value;
                                    }
                                    if (input.value && input.value.trim() !== '') {
                                        // Ignore default select options like "Select Bank" if they have empty value
                                        hasValues = true;
                                    }
                                }
                            });

                            // Capture Total from global total
                            const globalTotalInput = document.getElementById('globalTotalInput');
                            if (globalTotalInput) {
                                data['Total'] = globalTotalInput.value;
                                if (globalTotalInput.value && globalTotalInput.value.trim() !== '') hasValues = true;
                            }

                            return true;
                        }
                        return false;
                    }

                    // Check each section visibility
                    const sections = [
                        { class: '.home-credit-section', name: 'STO ninio de cebu' },
                        { class: '.credit-card-section', name: 'Credit Card' },
                        { class: '.debit-card-section', name: 'Debit Card' },
                        { class: '.qr-ph-section', name: 'QR PH' },
                        { class: '.starpay-qr-section', name: 'Starpay QR' },
                        { class: '.ewallet-section', name: 'E-Wallet' },
                        { class: '.online-banking-section', name: 'Online Banking' },
                        { class: '.cash-section', name: 'Cash' }
                    ];

                    for (const sec of sections) {
                        collectData(sec.class, sec.name);
                    }

                    // Build unit_payment_map: { unitLabel -> paymentMethodName }
                    // This allows the report to show per-unit payment options correctly.
                    const unitPaymentMap = {};
                    for (const sec of sections) {
                        const secEl = document.querySelector(sec.class);
                        if (!secEl || secEl.style.display !== 'block') continue;

                        // Determine display name (substitute E-Wallet/Online Banking with specific name)
                        let displayName = sec.name;
                        if (sec.name === 'E-Wallet' && data['E-Wallet-Text']) {
                            displayName = data['E-Wallet-Text'];
                        } else if (sec.name === 'Online Banking' && data['Bank-Text']) {
                            displayName = data['Bank-Text'];
                        }

                        // Get checked unit checkboxes for this section
                        const unitCheckboxes = secEl.querySelectorAll('.unit-selector-row input[type="checkbox"][name="Unit"]:checked');
                        unitCheckboxes.forEach(cb => {
                            const unitLabel = cb.value; // e.g. 'AEROX V3 (TEST222222222227)'
                            unitPaymentMap[unitLabel] = displayName;
                        });
                    }

                    if (Object.keys(unitPaymentMap).length > 0) {
                        data.unit_payment_map = unitPaymentMap;
                    }

                    if (isValid) {
                        if (!hasValues) {
                            alert('Please fill in the payment details before saving.');
                            return;
                        }

                        console.log('Saving Payment Data:', data);
                        const hiddenInput = document.getElementById('payment_data');
                        if (hiddenInput) {
                            hiddenInput.value = JSON.stringify(data);

                            // Global Payment Validation: Sum of ALL entered payments must equal Total Amount Due
                            const globalTotalInputCheck = document.getElementById('globalTotalInput');
                            const overallTotalPayment = parseFloat(globalTotalInputCheck ? (globalTotalInputCheck.value || '0').replace(/,/g, '') : '0') || 0;

                            const overallDifference = _originalTotalAmountDue - overallTotalPayment;

                            if (_originalTotalAmountDue > 0 && Math.abs(overallDifference) > 0.01) {
                                const bkBanner = document.getElementById('paymentBreakdownBanner');
                                if (bkBanner) bkBanner.style.display = 'none';

                                const neededDisp = _originalTotalAmountDue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                const enteredDisp = overallTotalPayment.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                const isExceeded = overallDifference < -0.01;
                                const diffLabel = isExceeded ? 'Exceeded Amount:' : 'Remaining Balance:';
                                const diffAmount = Math.abs(overallDifference);
                                const diffDisp = diffAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                                // Build detailed unit summary directly from DOM
                                let unitRowsHtml = '';
                                const unitRows = document.querySelectorAll('#itemsTableBody tr:not(#no-sales-row)');
                                unitRows.forEach(row => {
                                    if (row.id === 'no-sales-row') return;
                                    const tds = row.querySelectorAll('td');
                                    if (tds.length >= 4) {
                                        const descText = tds[0].textContent.trim();
                                        const priceInput = row.querySelector('.price-input-table');
                                        const qtyInput = row.querySelector('.qty-input');
                                        const basePrice = parseFloat(row.getAttribute('data-base-price')) || 0;

                                        if (descText && priceInput) {
                                            let pVal = parseFloat(priceInput.value.replace(/,/g, '')) || 0;
                                            let qVal = parseInt(qtyInput ? qtyInput.value : 1) || 1;
                                            let rowTotal = pVal * qVal;

                                            let promoLabel = '';
                                            const priceTdText = tds[3].textContent.trim().toUpperCase();
                                            if (priceTdText === 'FREE' || pVal === 0) {
                                                promoLabel = ' <span style="color: #16a34a; font-weight: 600;">(FREE)</span>';
                                            } else if (basePrice > 0 && pVal < basePrice) {
                                                const discountPercent = Math.round(((basePrice - pVal) / basePrice) * 100);
                                                promoLabel = ` <span style="color: #d97706; font-weight: 600;">(${discountPercent}% Discount)</span>`;
                                            }

                                            unitRowsHtml += `
                                                <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 13px; color: #1e3a8a; margin-top: 2px;">
                                                    <span style="font-style: italic; max-width: 350px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">- ${descText}${promoLabel}</span>
                                                    <span>₱${rowTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                </div>
                                            `;
                                        }
                                    }
                                });

                                // Build Applied Promo banner row if a promo is selected
                                let promoBannerHtml = '';
                                const appliedPromoSelect = document.getElementById('applied_promo');
                                if (appliedPromoSelect && appliedPromoSelect.selectedIndex > 0) {
                                    const selectedPromoText = getActivePromoUsageLabel();
                                    promoBannerHtml = `
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px dashed #fca5a5; font-size: 13px;">
                                            <span style="font-weight: 600; color: #991b1b;">Applied Promo:</span>
                                            <span style="font-weight: 700; color: #7f1d1d;">${selectedPromoText}</span>
                                        </div>
                                    `;
                                }

                                if (unitRowsHtml) {
                                    unitRowsHtml = `
                                        <div style="margin-bottom: 6px;">
                                            <div style="font-weight: 600; color: #1e40af; font-size: 13px;">Unit(s) To Pay:</div>
                                            ${unitRowsHtml}
                                        </div>
                                    `;
                                }

                                // Build detailed breakdown of what the user entered
                                let breakdownHtml = '<div style="margin: 8px 0; padding: 6px 0; border-top: 1px solid #fecaca; border-bottom: 1px solid #fecaca;">';
                                sections.forEach(sec => {
                                    const secEl = document.querySelector(sec.class);
                                    if (secEl && secEl.style.display === 'block') {
                                        let secTotal = 0;
                                        let secBreakdownRows = '';
                                        const inputs = secEl.querySelectorAll('input[type="text"], input[type="number"]');

                                        inputs.forEach(input => {
                                            let isAmount = false;
                                            let labelText = '';

                                            // Identify if input is an amount field and scrape its label
                                            if (input.classList.contains('amount-input')) isAmount = true;
                                            if (input.id && input.id.toLowerCase().includes('amount')) { isAmount = true; labelText = 'Amount'; }

                                            // Specific DP input IDs → correct label names
                                            if (input.id === 'cash_down_payment_amount') { isAmount = true; labelText = 'Cash (DP)'; }
                                            if (input.id === 'gcash_down_payment_amount') { isAmount = true; labelText = 'G-Cash (DP)'; }
                                            if (input.id === 'maya_down_payment_amount') { isAmount = true; labelText = 'Maya (DP)'; }

                                            const formGroup = input.closest('.hc-form-group');
                                            if (formGroup) {
                                                const label = formGroup.querySelector('label');
                                                if (label && (label.innerText.includes('Amount') || label.innerText.includes('Loan Balance'))) {
                                                    isAmount = true;
                                                    labelText = label.innerText.replace(':', '').trim();
                                                }
                                            }
                                            const enterAmountRow = input.closest('.enter-amount-row');
                                            if (enterAmountRow) {
                                                const label = enterAmountRow.querySelector('label');
                                                if (label && label.innerText.includes('Amount')) {
                                                    isAmount = true;

                                                    // Detect which downpayment methods (Cash, G-Cash, Maya) are selected
                                                    let dpMethods = [];
                                                    const dpCbs = secEl.querySelectorAll('input[name="down_payment_method"]:checked');
                                                    dpCbs.forEach(cb => {
                                                        if (cb.value === "cash") dpMethods.push("Cash");
                                                        if (cb.value === "gcash") dpMethods.push("G-Cash");
                                                        if (cb.value === "maya") dpMethods.push("Maya");
                                                    });

                                                    if (dpMethods.length > 0) {
                                                        labelText = dpMethods.join(' & ') + ' (DP)';
                                                    } else {
                                                        labelText = 'Downpayment';
                                                    }
                                                }
                                            }
                                            if (isAmount && !labelText) labelText = 'Amount';

                                            if (isAmount) {
                                                let val = parseFloat(input.value.replace(/,/g, '')) || 0;
                                                if (val > 0) {
                                                    secTotal += val;
                                                    secBreakdownRows += `
                                                        <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 12px; color: #b91c1c; margin-top: 2px;">
                                                            <span>- ${labelText}</span>
                                                            <span>₱${val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                        </div>
                                                    `;
                                                }
                                            }
                                        });

                                        if (secTotal > 0) {
                                            let displayName = sec.name;
                                            if (sec.name === 'Home Credit' && paymentPartnersDropdown && paymentPartnersDropdown.value !== '') {
                                                displayName = paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text;
                                            }
                                            if (sec.name === 'E-Wallet' && data['E-Wallet-Text']) displayName = data['E-Wallet-Text'];
                                            if (sec.name === 'Online Banking' && data['Bank-Text']) displayName = data['Bank-Text'];

                                            breakdownHtml += `
                                                <div style="margin-bottom: 6px;">
                                                    <div style="font-weight: 600; color: #991b1b; font-size: 13px;">${displayName}:</div>
                                                    ${secBreakdownRows}
                                                </div>
                                            `;
                                        }
                                    }
                                });
                                breakdownHtml += '</div>';

                                const banner = document.getElementById('paymentErrorBanner');
                                if (banner) {
                                    banner.innerHTML = `
                                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                                            <div style="color: #ef4444; margin-top: 2px;">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                                </svg>
                                            </div>
                                            <div style="flex: 1; color: #991b1b; font-size: 13px; line-height: 1.6;">
                                                <div style="font-weight: 600; font-size: 14px; color: #7f1d1d; margin-bottom: 4px;">Payment Mismatch</div>
                                                <div>The total payment entered does not match the Total Amount Due. Please check your breakdown below:</div>
                                                <div style="margin-top: 6px; display: flex; flex-direction: column; gap: 2px; max-width: 450px; padding-top: 6px;">
                                                    
                                                    ${promoBannerHtml}
                                                    ${unitRowsHtml}
                                                    
                                                    <div style="display: flex; justify-content: space-between; border-bottom: 1.5px solid #fca5a5; padding-bottom: 4px; margin-bottom: 2px;">
                                                        <span style="font-weight: 600;">Total Amount Due:</span><span style="font-weight: 700;">₱${neededDisp}</span>
                                                    </div>
                                                    
                                                    ${breakdownHtml}
                                                    
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <span>Total Entered:</span><span style="font-weight: 600;">₱${enteredDisp}</span>
                                                    </div>
                                                    <div style="display: flex; justify-content: space-between; margin-top: 2px; padding-top: 4px; border-top: 1.5px dashed #fca5a5;">
                                                        <span style="color: #7f1d1d; font-weight: 600;">${diffLabel}</span><span style="color: #ef4444; font-weight: 600;">₱${diffDisp}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                    banner.style.display = 'block';
                                    banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                }
                                return; // Block save
                            } else {
                                // Clear success
                                const banner = document.getElementById('paymentErrorBanner');
                                if (banner) { banner.style.display = 'none'; banner.innerHTML = ''; }
                            }

                            // Update Payment Button in Main Form
                            const btnPayment = document.querySelector('.btn-payment');
                            if (btnPayment) {
                                let buttonText = `Payment: ${sectionName}`;

                                // Customize button text for specific payment types
                                if (sectionName === 'E-Wallet' && data['E-Wallet-Text']) {
                                    buttonText = `Payment: ${data['E-Wallet-Text']}`;
                                } else if (sectionName === 'Online Banking' && data['Bank-Text']) {
                                    buttonText = `Payment: ${data['Bank-Text']}`;
                                } else if (sectionName === 'STO ninio de cebu') {
                                    buttonText = `Payment: STO ninio de cebu`;
                                }

                                btnPayment.innerText = buttonText;
                                btnPayment.style.backgroundColor = '#2E7D32'; // Success Green
                                btnPayment.style.color = 'white';
                            }

                            alert('Payment details saved successfully!');
                            closePaymentModal();
                        } else {
                            console.error('Hidden input #payment_data not found!');
                        }
                    } else {
                        alert('Please select a payment method.');
                    }
                }
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Function to update global total based on input across all sections
                function updateSectionTotal() {
                    let globalTotal = 0;

                    const sections = [
                        '.home-credit-section',
                        '.credit-card-section',
                        '.debit-card-section',
                        '.qr-ph-section',
                        '.starpay-qr-section',
                        '.ewallet-section',
                        '.online-banking-section',
                        '.cash-section'
                    ];

                    sections.forEach(secClass => {
                        const sec = document.querySelector(secClass);
                        if (sec && sec.style.display === 'block') {
                            const inputs = sec.querySelectorAll('input[type="text"], input[type="number"]');
                            inputs.forEach(input => {
                                // Skip non-amount inputs
                                let isAmount = false;
                                if (input.classList.contains('amount-input')) isAmount = true;
                                if (input.id && input.id.toLowerCase().includes('amount')) isAmount = true;
                                const formGroup = input.closest('.hc-form-group');
                                if (formGroup) {
                                    const label = formGroup.querySelector('label');
                                    if (label && label.innerText.includes('Amount')) isAmount = true;
                                    if (label && label.innerText.includes('Loan Balance')) isAmount = true;
                                }
                                const enterAmountRow = input.closest('.enter-amount-row');
                                if (enterAmountRow) {
                                    const label = enterAmountRow.querySelector('label');
                                    if (label && label.innerText.includes('Amount')) isAmount = true;
                                }

                                if (isAmount) {
                                    let val = parseFloat(input.value.replace(/,/g, '')) || 0;
                                    globalTotal += val;
                                }
                            });
                        }
                    });

                    const globalTotalInput = document.getElementById('globalTotalInput');
                    if (globalTotalInput) {
                        globalTotalInput.value = globalTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }

                    // Update TOTAL AMOUNT DUE: original total minus what has been paid so far
                    const globalTotalDueInput = document.getElementById('globalTotalDueInput');
                    if (globalTotalDueInput) {
                        let remaining = _originalTotalAmountDue - globalTotal;
                        if (remaining < 0) remaining = 0;
                        globalTotalDueInput.value = remaining.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }

                    // Render dynamic breakdown box
                    renderPaymentBreakdown(globalTotal);
                }
                window.updateSectionTotal = updateSectionTotal;
                window.renderPaymentBreakdown = renderPaymentBreakdown;

                function renderPaymentBreakdown(globalTotal) {
                    const banner = document.getElementById('paymentBreakdownBanner');
                    if (!banner) return;

                    if (_originalTotalAmountDue <= 0 && globalTotal <= 0) {
                        banner.style.display = 'none';
                        return;
                    }

                    const neededDisp = _originalTotalAmountDue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    const enteredDisp = globalTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    const overallDifference = _originalTotalAmountDue - globalTotal;
                    const isExceeded = overallDifference < -0.01;
                    const isPerfectMatch = (Math.abs(overallDifference) <= 0.01 && globalTotal > 0);
                    const diffLabel = isExceeded ? 'Exceeded Amount:' : 'Remaining Balance:';
                    const diffAmount = Math.abs(overallDifference);
                    const diffDisp = diffAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    // Build detailed unit summary directly from DOM
                    let unitRowsHtml = '';
                    const unitRows = document.querySelectorAll('#itemsTableBody tr:not(#no-sales-row)');
                    unitRows.forEach(row => {
                        if (row.id === 'no-sales-row') return;
                        const tds = row.querySelectorAll('td');
                        if (tds.length >= 4) {
                            const descText = tds[0].textContent.trim();
                            const priceInput = row.querySelector('.price-input-table');
                            const qtyInput = row.querySelector('.qty-input');
                            const itemCode = row.getAttribute('data-item-code') || '';
                            const basePrice = parseFloat(row.getAttribute('data-base-price')) || 0;

                            if (descText && priceInput) {
                                let pVal = parseFloat(priceInput.value.replace(/,/g, '')) || 0;
                                let qVal = parseInt(qtyInput ? qtyInput.value : 1) || 1;
                                let rowTotal = pVal * qVal;

                                // Check if item has promo discount or is free
                                let promoLabel = '';
                                const priceTdText = tds[3].textContent.trim().toUpperCase();

                                if (priceTdText === 'FREE' || pVal === 0) {
                                    // Item is FREE
                                    promoLabel = ' <span style="color: #16a34a; font-weight: 600;">(FREE)</span>';
                                } else if (basePrice > 0 && pVal < basePrice) {
                                    // Item has percentage discount
                                    const discountPercent = Math.round(((basePrice - pVal) / basePrice) * 100);
                                    promoLabel = ` <span style="color: #d97706; font-weight: 600;">(${discountPercent}% Discount)</span>`;
                                }

                                unitRowsHtml += `
                                    <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 13px; color: #000000ff; margin-top: 2px;">
                                        <span style="font-style: italic; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">- ${descText}${promoLabel}</span>
                                        <span>₱${rowTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                    </div>
                                `;
                            }
                        }
                    });

                    // Build Applied Promo banner row if a promo is selected
                    let promoBannerHtml = '';
                    const appliedPromoSelect = document.getElementById('applied_promo');
                    if (appliedPromoSelect && appliedPromoSelect.selectedIndex > 0) {
                        const selectedPromoText = getActivePromoUsageLabel();
                        promoBannerHtml = `
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px dashed #cbd5e1; font-size: 13px;">
                                <span style="font-weight: 600; color: #b08a52;">Applied Promo:</span>
                                <span style="font-weight: 700; color: #0d3347;">${selectedPromoText}</span>
                            </div>
                        `;
                    }

                    if (unitRowsHtml) {
                        unitRowsHtml = `
                            <div style="margin-bottom: 6px;">
                                <div style="font-weight: 600; color: #000000ff; font-size: 13px;">Unit(s) To Pay:</div>
                                ${unitRowsHtml}
                            </div>
                        `;
                    }

                    let breakdownInner = '';
                    const sections = [
                        { class: '.home-credit-section', name: 'Home Credit' },
                        { class: '.credit-card-section', name: 'Credit Card' },
                        { class: '.debit-card-section', name: 'Debit Card' },
                        { class: '.qr-ph-section', name: 'QR PH' },
                        { class: '.starpay-qr-section', name: 'Starpay QR' },
                        { class: '.ewallet-section', name: 'E-Wallet' },
                        { class: '.online-banking-section', name: 'Online Banking' },
                        { class: '.cash-section', name: 'Cash' }
                    ];

                    sections.forEach(sec => {
                        const secEl = document.querySelector(sec.class);
                        if (secEl && secEl.style.display === 'block') {
                            let secTotal = 0;
                            let secBreakdownRows = '';
                            const inputs = secEl.querySelectorAll('input[type="text"], input[type="number"]');

                            inputs.forEach(input => {
                                let isAmount = false;
                                let labelText = '';

                                if (input.classList.contains('amount-input')) isAmount = true;
                                if (input.id && input.id.toLowerCase().includes('amount')) { isAmount = true; labelText = 'Amount'; }

                                // Specific DP input IDs → correct label names
                                if (input.id === 'cash_down_payment_amount') { isAmount = true; labelText = 'Cash (DP)'; }
                                if (input.id === 'gcash_down_payment_amount') { isAmount = true; labelText = 'G-Cash (DP)'; }
                                if (input.id === 'maya_down_payment_amount') { isAmount = true; labelText = 'Maya (DP)'; }

                                const formGroup = input.closest('.hc-form-group');
                                if (formGroup) {
                                    const label = formGroup.querySelector('label');
                                    if (label && (label.innerText.includes('Amount') || label.innerText.includes('Loan Balance'))) {
                                        isAmount = true;
                                        labelText = label.innerText.replace(':', '').trim();
                                    }
                                }
                                const enterAmountRow = input.closest('.enter-amount-row');
                                if (enterAmountRow) {
                                    const label = enterAmountRow.querySelector('label');
                                    if (label && label.innerText.includes('Amount')) {
                                        isAmount = true;

                                        let dpMethods = [];
                                        const dpCbs = secEl.querySelectorAll('input[name="down_payment_method"]:checked');
                                        dpCbs.forEach(cb => {
                                            if (cb.value === "cash") dpMethods.push("Cash");
                                            if (cb.value === "gcash") dpMethods.push("G-Cash");
                                            if (cb.value === "maya") dpMethods.push("Maya");
                                        });

                                        if (dpMethods.length > 0) {
                                            labelText = dpMethods.join(' & ') + ' (DP)';
                                        } else {
                                            labelText = 'Downpayment';
                                        }
                                    }
                                }
                                if (isAmount && !labelText) labelText = 'Amount';

                                if (isAmount) {
                                    let val = parseFloat(input.value.replace(/,/g, '')) || 0;
                                    if (val > 0) {
                                        secTotal += val;
                                        secBreakdownRows += `
                                            <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 12px; color: #000000ff; margin-top: 2px;">
                                                <span>- ${labelText}</span>
                                                <span>₱${val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                            </div>
                                        `;
                                    }
                                }
                            });

                            if (secTotal > 0) {
                                let displayName = sec.name;
                                const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
                                if (sec.name === 'Home Credit' && paymentPartnersDropdown && paymentPartnersDropdown.value !== '') {
                                    displayName = paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text;
                                }
                                if (sec.name === 'E-Wallet') {
                                    const sel = secEl.querySelector('select');
                                    if (sel && sel.selectedIndex > 0) displayName = sel.options[sel.selectedIndex].text;
                                }
                                if (sec.name === 'Online Banking') {
                                    const sel = secEl.querySelector('select');
                                    if (sel && sel.selectedIndex > 0) displayName = sel.options[sel.selectedIndex].text;
                                }

                                breakdownInner += `
                                    <div style="margin-bottom: 6px;">
                                        <div style="font-weight: 600; color: #000000ff; font-size: 13px;">${displayName}:</div>
                                        ${secBreakdownRows}
                                    </div>
                                `;
                            }
                        }
                    });
                    // Only wrap with bordered container if there are actual breakdown rows
                    const breakdownHtml = breakdownInner
                        ? '<div style="margin: 8px 0; padding: 6px 0; border-top: 1px solid #cfcfcfff; border-bottom: 1px solid #cfcfcfff;">' + breakdownInner + '</div>'
                        : '';

                    let statusText = "You are currently entering your breakdown details.";
                    if (isPerfectMatch) {
                        statusText = "Payment matches Total Amount Due.";
                    } else if (isExceeded) {
                        statusText = "Payment exceeds Total Amount Due. Please adjust your payment.";
                    }
                    let diffColor = isPerfectMatch ? "#16a34a" : (isExceeded ? "#dc2626" : "#ca8a04");

                    banner.innerHTML = `
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <div style="color: #000000ff; margin-top: 2px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                            </div>
                            <div style="flex: 1; color: #000000ff; font-size: 13px; line-height: 1.6;">
                                <div style="font-weight: 600; font-size: 14px; color: #000000ff; margin-bottom: 4px;">Payment Breakdown</div>
                                <div>${statusText}</div>
                                <div style="margin-top: 6px; display: flex; flex-direction: column; gap: 2px; max-width: 450px; padding-top: 6px;">
                                    
                                    ${promoBannerHtml}
                                    ${unitRowsHtml}
                                    
                                    <div style="display: flex; justify-content: space-between; border-bottom: 1.5px solid #ffffffff; padding-bottom: 4px; margin-bottom: 2px;">
                                        <span style="font-weight: 600;">Total Amount Due:</span><span style="font-weight: 700;">₱${neededDisp}</span>
                                    </div>
                                    
                                    ${breakdownHtml}
                                    
                                    <div style="display: flex; justify-content: space-between;">
                                        <span>Total Entered:</span><span style="font-weight: 600;">₱${enteredDisp}</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-top: 2px; padding-top: 4px; border-top: 1.5px dashed #afafafff;">
                                        <span style="color: #000000ff; font-weight: 600;">${diffLabel}</span><span style="color: ${diffColor}; font-weight: 600;">₱${diffDisp}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    const errorBanner = document.getElementById('paymentErrorBanner');
                    if (errorBanner && errorBanner.style.display === 'block') {
                        errorBanner.style.display = 'none';
                        errorBanner.innerHTML = '';
                    }

                    banner.style.display = 'block';
                }


                // Add observer or global listener for section toggles to immediately update sum
                document.body.addEventListener('change', function (e) {
                    if (e.target.name === 'payment_method' || e.target.id === 'paymentPartnersDropdown' || e.target.id === 'cardPaymentDropdown' || e.target.id === 'qrDropdown') {
                        setTimeout(updateSectionTotal, 50);
                    }
                });

                // Attach listeners to all identifiable amount inputs
                const allInputs = document.querySelectorAll('input[type="text"], input[type="number"]');

                allInputs.forEach(input => {
                    // Skip if it is a total field itself
                    if (input.classList.contains('total-input') || input.id === 'totalAmount' || input.id === 'totalQty' || input.id === 'discountField') return;

                    let isAmount = false;

                    // key checks
                    if (input.classList.contains('amount-input')) isAmount = true;
                    if (input.id && input.id.toLowerCase().includes('amount')) isAmount = true;

                    // label checks
                    const formGroup = input.closest('.hc-form-group');
                    if (formGroup) {
                        const label = formGroup.querySelector('label');
                        if (label && label.innerText.includes('Amount')) isAmount = true;
                        if (label && label.innerText.includes('Loan Balance')) isAmount = true;
                    }

                    const enterAmountRow = input.closest('.enter-amount-row');
                    if (enterAmountRow) {
                        const label = enterAmountRow.querySelector('label');
                        if (label && label.innerText.includes('Amount')) isAmount = true;
                    }

                    if (isAmount) {
                        input.addEventListener('input', function () {
                            updateSectionTotal();
                        });
                        // Also update on blur to capture programmatic changes if any event dispatched, or delayed
                        input.addEventListener('blur', function () {
                            updateSectionTotal();
                        });
                    }
                });
            });
        </script>
</body>

</html>