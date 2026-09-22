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
    SELECT first_name, last_name, '' as brand
    FROM users 
    WHERE status = 'Activated' $branch_filter
    AND position NOT LIKE '%superadmin%' 
    AND position NOT LIKE '%super admin%'
    UNION ALL 
    SELECT first_name, last_name, '' as brand
    FROM accounts 
    WHERE status = 'Activated' $branch_filter
    AND system_level != 'Super-Admin'
    AND position NOT LIKE '%superadmin%' 
    AND position NOT LIKE '%super admin%'
    UNION ALL
    SELECT name as first_name, '' as last_name, brand
    FROM promoters
    WHERE status = 'Active' $branch_filter
    UNION ALL
    SELECT dealer_name as first_name, '(Dealer)' as last_name, '' as brand
    FROM dealers
    WHERE status = 'Active'
    ORDER BY first_name, last_name
");

// Fetch terminal issuers (Active only)
$terminal_issuers_result = $conn->query("SELECT bank_name FROM terminal_issuers WHERE status='Active' ORDER BY bank_name");

// Fetch other banks (Active only) for Card Payment
$others_bank_result = $conn->query("SELECT bank_name FROM others_bank WHERE status='Active' ORDER BY bank_name");

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
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Sales Entry</title>
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
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
            margin-top: 60px;
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

        .promo-details-box {
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            min-height: 100px;
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

        /* Large Desktop & Laptop (max-width: 1640px) */
        @media (max-width: 1640px) {
            .form-split-layout {
                grid-template-columns: 1.5fr 1fr;
                gap: 20px;
            }

            .item-input-row {
                grid-template-columns: 1fr 1fr 0.8fr auto auto;
                gap: 10px;
            }

            .btn-search-item,
            .btn-add-item {
                padding: 10px 30px;
            }

            .footer-actions {
                flex-wrap: wrap;
                gap: 10px;
            }

            .footer-left-group {
                gap: 10px;
            }

            .footer-right-group {
                gap: 10px;
            }

            .btn-skip-receipt,
            .btn-payment,
            .btn-save {
                padding: 10px 30px;
                font-size: 15px;
            }
        }

        /* Medium Desktop (max-width: 1366px) */
        @media (max-width: 1366px) {
            .form-split-layout {
                grid-template-columns: 1fr;
            }

            .item-input-row {
                grid-template-columns: 1fr 1fr 1fr;
                gap: 10px;
            }

            .btn-search-item,
            .btn-add-item {
                grid-column: span 1;
            }

            .bottom-section {
                grid-template-columns: 1fr;
            }
        }

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

        /* Small Tablet (max-width: 960px) - Fix Price & Quantity Display */
        @media (max-width: 960px) {

            /* CRITICAL FIX: Target the inline flex container with quantity, price, and buttons */
            .item-selection-container div[style*="display: flex"][style*="gap: 10px"] {
                display: flex !important;
                flex-direction: column !important;
                gap: 15px !important;
                align-items: stretch !important;
            }

            /* Make form groups full width */
            .item-selection-container .form-group[style*="flex: 1"] {
                flex: none !important;
                width: 100% !important;
            }

            /* Fix ALL form inputs to be large and visible */
            .item-selection-container input,
            .item-selection-container select {
                font-size: 16px !important;
                padding: 14px 12px !important;
                min-height: 48px !important;
                width: 100% !important;
            }

            /* Fix price dropdown container */
            .item-selection-container div[style*="position: relative"] {
                width: 100% !important;
            }

            /* Fix price text input specifically */
            input#price {
                width: 100% !important;
                font-size: 16px !important;
                padding: 14px 12px !important;
                padding-right: 40px !important;
                min-height: 48px !important;
            }

            /* Fix price dropdown select */
            select#priceDropdown {
                width: 100% !important;
                min-height: 48px !important;
            }

            /* Fix quantity input specifically */
            input#qty {
                width: 100% !important;
                font-size: 16px !important;
                padding: 14px 12px !important;
                min-height: 48px !important;
            }

            /* Make search and add buttons full width */
            .item-selection-container .btn-search-item,
            .item-selection-container .btn-add-item {
                width: 100% !important;
                padding: 14px 20px !important;
                font-size: 16px !important;
                margin-bottom: 0 !important;
            }

            /* Make table container scrollable horizontally */
            .item-selection-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            /* Zoom out the table to fit all columns without scrolling */
            .items-table {
                min-width: 100%;
                width: 100%;
                display: table;
                zoom: 0.75;
                -moz-transform: scale(0.75);
                -moz-transform-origin: 0 0;
                transform-origin: 0 0;
            }

            /* Make price and quantity inputs in TABLE always fully visible */
            .items-table input[type="number"] {
                font-size: 16px !important;
                padding: 12px 14px !important;
                min-width: 100px;
                width: 100%;
            }

            .qty-input {
                min-width: 100px;
            }

            .price-input-table {
                min-width: 120px;
            }

            /* Adjust table font sizes for better readability */
            .items-table th,
            .items-table td {
                font-size: 15px;
                padding: 12px 10px;
                white-space: nowrap;
            }

            /* Ensure table columns don't collapse */
            .items-table th:nth-child(1),
            .items-table td:nth-child(1) {
                min-width: 220px;
            }

            .items-table th:nth-child(2),
            .items-table td:nth-child(2) {
                min-width: 160px;
            }

            .items-table th:nth-child(3),
            .items-table td:nth-child(3) {
                min-width: 140px;
            }

            .items-table th:nth-child(4),
            .items-table td:nth-child(4) {
                min-width: 160px;
            }

            .items-table th:nth-child(5),
            .items-table td:nth-child(5) {
                min-width: 80px;
            }

            /* Unclaimed Freebies responsive for tablets */
            .btn-add-unclaimed-freebies {
                width: 100% !important;
                font-size: 16px !important;
                padding: 14px 20px !important;
            }

            .unclaimed-freebies-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            /* Make unclaimed freebies inputs larger on tablet */
            .unclaimed-freebie-name-input,
            .unclaimed-freebie-qty-input,
            .unclaimed-freebie-note-input {
                font-size: 16px !important;
                padding: 12px !important;
                min-height: 48px !important;
            }

            .btn-search-unclaimed-freebie {
                font-size: 14px !important;
                padding: 12px 18px !important;
            }
        }

        /* Mobile Devices (max-width: 768px) */
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

            /* Responsive Tables - Wrap tables in scrollable containers */
            .items-table {
                width: 100%;
                display: table;
                /* Keep table display */
            }

            .search-results-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            /* Create scrollable wrapper for items table */
            .items-table-wrapper {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                /* Smooth scrolling on iOS */
            }

            .items-table thead,
            .items-table tbody {
                width: 100%;
            }

            .items-table th,
            .items-table td {
                white-space: nowrap;
                /* Prevent text wrapping in cells */
            }

            /* Unclaimed Freebies Section - Stack vertically on mobile */
            .unclaimed-freebies-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            /* Make Add Unclaimed Freebies button full width on mobile */
            .btn-add-unclaimed-freebies {
                width: 100% !important;
            }

            /* Stack unclaimed freebies and totals section vertically */
            div[style*="display: grid"][style*="grid-template-columns: 2fr 1fr"] {
                display: block !important;
            }

            .unclaimed-freebies-container,
            .totals-section {
                width: 100% !important;
                margin-bottom: 15px;
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

            .btn-skip-receipt {
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
            background-color: var(--color-navy);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-select:hover {
            background-color: var(--color-navy-dark);
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

        .btn-skip-receipt {
            padding: 10px 40px;
            border: 1px solid #ff6b6b;
            background: #ff6b6b;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            min-width: 120px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .btn-skip-receipt:hover {
            background: white;
            color: #ff6b6b;
            border: 1px solid #ff6b6b;
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
            <h2>Sales Entry</h2>
            <?php if (!empty($user_branch_name) || (isset($_SESSION['system_level']) && $_SESSION['system_level'] === 'Super-Admin')): ?>
                <div
                    style="margin-left: auto; font-weight: bold; color: #1b5e20; font-size: 18px; text-transform: uppercase;">
                    <?php
                    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
                    if ($system_level === 'Super-Admin') {
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
                            <input type="text" id="invoice_no" name="invoice_no" placeholder="Loading invoice number..."
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
                                        $brand = htmlspecialchars($user['brand'] ?? '');
                                        $displayText = $name;
                                        
                                        // If there's a brand (promoter), include it in the option
                                        if (!empty($brand)) {
                                            echo '<option value="' . $displayText . '">' . $displayText . ' - ' . $brand . '</option>';
                                        } else {
                                            echo '<option value="' . $displayText . '">' . $displayText . '</option>';
                                        }
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
                </div>

                <div class="form-right-section">
                    <div class="form-group">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" style="height: 60px; min-height: 60px;"
                            placeholder="Enter remarks..." oninput="this.value = this.value.toUpperCase()"></textarea>

                        <!-- Promo Checkbox -->
                        <div
                            style="display: flex; align-items: center; gap: 8px; margin-top: 20px; margin-bottom: 15px;">
                            <label for="promo" style="margin: 0; cursor: pointer; font-weight: 600;">Promo</label>
                            <input type="checkbox" id="promo" name="promo"
                                style="width: 18px; height: 18px; cursor: pointer;" onchange="togglePromoFields()">

                        </div>
                    </div>

                    <!-- Promo Fields (Hidden by default) -->
                    <div id="promo-fields" style="display: none;">
                        <div class="form-group">
                            <label for="applied_promo">Apply Promo</label>
                            <select id="applied_promo" name="applied_promo" onchange="applyPromoLogic()">
                                <option value="">Select Promo</option>
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

                        <div class="form-group" style="margin-top: 15px;">
                            <label for="promo_details">Promo Details</label>
                            <div id="promo_details" class="promo-details-box">No promo selected.</div>
                        </div>
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
                        <label>IMEI | Serial Number</label>
                        <input type="text" id="imei" placeholder="" oninput="this.value = this.value.toUpperCase()">
                    </div>

                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <div class="form-group" style="flex: 1;">
                            <label>Quantity</label>
                            <input type="number" id="qty" value="0" min="0" style="text-align: center;">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Price</label>
                            <div style="position: relative;">
                                <input type="text" id="price" placeholder="" readonly
                                    style="background-color: #ffffff; color: #333;">
                                <span
                                    style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #666;">?</span>
                                <select id="priceDropdown"
                                    style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;">
                                    <option value="">Select</option>
                                    <option value="auto">Auto</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                            <!-- <input type="number" id="price" placeholder="" readonly style="background-color: #ffffffff; cursor: not-allowed; color: #333;"> -->
                        </div>
                        <button type="button" class="btn-search-item" style="margin-bottom: 1px;">Search</button>
                        <button type="button" class="btn-add-item" style="margin-bottom: 1px;">Add</button>
                    </div>
                </div>
            </div>

            <div class="items-table-wrapper">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Item Description</th>
                            <th style="width: 30%;">IMEI</th>
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
            </div>

            <!-- Unclaimed Freebies Table Section -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Unclaimed Freebies Table Container (Left) -->
                <div class="unclaimed-freebies-container"
                    style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #ccc;">
                    <button type="button" class="btn-add-unclaimed-freebies"
                        style="margin-bottom: 15px; width: auto; padding: 10px 30px; background-color: var(--color-gold); color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">Add
                        Unclaimed Freebies</button>
                    <table class="items-table">
                        <thead>
                            <tr style="background: var(--color-gold-pale);">
                                <th style="width: 40%; border: 1px solid #ccc;">Unclaimed Freebies</th>
                                <th style="width: 15%; border: 1px solid #ccc;">Quantity</th>
                                <th style="width: 35%; border: 1px solid #ccc;">Note</th>
                                <th style="width: 10%; border: 1px solid #ccc;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="unclaimedFreebiesTableBody">
                            <!-- Empty state - will show message until user adds freebies -->
                            <tr id="no-unclaimed-freebies-row">
                                <td colspan="4" style="text-align:center; padding: 20px;">Please click Add Unclaimed
                                    Freebies</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Totals Section Container (Right) -->
                <div class="totals-section" style="width: 100%;">
                    <div class="total-row">
                        <label>Total QTY:</label>
                        <input type="text" id="totalQty" readonly>
                    </div>
                    <div class="total-row">
                        <label>Discount:</label>
                        <input type="text" id="discountField" value="0.00" style="background-color: #e0e0e0;" readonly>
                    </div>
                    <div class="total-row">
                        <label>Voucher:</label>
                        <input type="text" id="voucherField" value="0.00"
                            style="background-color: #e0e0e0; cursor: not-allowed;" readonly>
                    </div>
                    <div class="total-row">
                        <label>Token:</label>
                        <input type="text" id="tokenField" value="0.00"
                            style="background-color: #e0e0e0; cursor: not-allowed;" readonly>
                    </div>
                    <div class="total-row">
                        <label>Total:</label>
                        <input type="text" id="totalAmount" readonly>
                    </div>
                </div>
            </div>

            <!-- Footer Actions Section (Below the grid) -->
            <div class="footer-actions"
                style="width: 100%; justify-content: space-between; padding: 20px; background: white; margin-top: 20px; border-radius: 8px; border: 1px solid #ccc;">
                <div class="footer-left-group">
                    <div class="footer-input-group">
                        <label>Points:</label>
                        <input type="text" id="pointsField" style="background-color: #e0e0e0;" readonly>
                    </div>
                    <div class="footer-input-group">
                        <label>Commision:</label>
                        <input type="text" id="commissionField" style="background-color: #e0e0e0;" readonly>
                    </div>
                </div>
                <div class="footer-right-group">
                    <button type="button" class="btn-payment" onclick="openPaymentModal()">PAYMENT</button>
                    <button type="button" class="btn-save">SAVE</button>
                    <button type="button" class="btn-clear-main" onclick="clearMainForm()">CLEAR</button>
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

    <!-- Unclaimed Freebies Search Modal -->
    <div id="searchUnclaimedFreebieModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Search Unclaimed Freebies (Non-Serialized Items Only)
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 15px;">
                    <input type="text" id="unclaimedFreebieSearchInput" placeholder="Enter item code..."
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                <table class="search-results-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Item Code</th>
                            <th style="width: 50%;">Item Description</th>
                            <th style="width: 20%; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="unclaimedFreebieSearchResultsBody">
                        <!-- Results will be injected here -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-back-modal" onclick="closeUnclaimedFreebieSearchModal()">Back</button>
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
                                <option value="EastWest Bank">EastWest Bank</option>
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
                                <option value="EastWest Bank">EastWest Bank</option>
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
                                <option value="EastWest Bank">EastWest Bank</option>
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

        // Promos dataset from PHP
        const promosData = <?php echo json_encode($promos_for_js); ?>;
        let activePromoItems = [];
        let activePromoDiscountLabel = '0';

        // Function to toggle promo fields visibility
        function togglePromoFields() {
            const promoCheckbox = document.getElementById('promo');
            const promoFields = document.getElementById('promo-fields');

            if (promoCheckbox.checked) {
                promoFields.style.display = 'block';
            } else {
                promoFields.style.display = 'none';
                // Reset promo selection when unchecked
                document.getElementById('applied_promo').value = '';
                renderPromoDetails(null);
            }
        }

        // Function to render promo details
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

        // Function to apply promo logic
        function applyPromoLogic() {
            const promoSelect = document.getElementById('applied_promo');
            const promoId = promoSelect.value;

            // Reset promo state
            activePromoItems = [];
            activePromoDiscountLabel = '0';

            if (!promoId) {
                renderPromoDetails(null);
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

            // Re-evaluate cart to apply promo logic
            reevaluateCartPromo();
        }

        // Helper function to normalize promo text
        function normalizePromoText(value) {
            return (value || '').toString().toUpperCase().trim();
        }

        // Function to get active promo usage label
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
                    const priceVal = parseFloat((priceInputEl ? priceInputEl.value : '0').replace(/,/g, '')) || 0;
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

        // Helper function to normalize promo text
        function normalizePromoText(value) {
            return (value || '').toString().toUpperCase().trim();
        }

        // Helper function to check if item matches promo model
        function isItemMatchingModel(itemCode, itemDesc, targetModel) {
            const codeNorm = normalizePromoText(itemCode);
            const descNorm = normalizePromoText(itemDesc);
            const targetNorm = normalizePromoText(targetModel);

            if (!targetNorm || targetNorm === 'ALL MODELS' || targetNorm === 'ALL') {
                return true;
            }

            return (codeNorm === targetNorm || descNorm.includes(targetNorm) || targetNorm.includes(codeNorm));
        }

        // Function to re-evaluate cart and apply promo logic
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
                    // Different item promo: buy X, get Y free
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
                    // Same item promo: buy 1 take 1
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

        // Unclaimed Freebies functionality
        let unclaimedFreebieRowCounter = 0;
        let currentUnclaimedFreebieRow = null; // Track which row is being searched

        // Function to add a new unclaimed freebie row
        function addUnclaimedFreebieRow() {
            unclaimedFreebieRowCounter++;
            const unclaimedFreebiesTableBody = document.getElementById('unclaimedFreebiesTableBody');

            // Remove "no unclaimed freebies" row if it exists
            const noUnclaimedFreebiesRow = document.getElementById('no-unclaimed-freebies-row');
            if (noUnclaimedFreebiesRow) {
                noUnclaimedFreebiesRow.remove();
            }

            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                    <td style="border: 1px solid #ccc;">
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <input type="text" class="unclaimed-freebie-name-input" style="flex: 1; border: 1px solid #ddd; padding: 8px; border-radius: 4px;" readonly>
                            <button type="button" class="btn-search-unclaimed-freebie" style="background-color: #424242; color: white; border: none; border-radius: 4px; padding: 8px 15px; cursor: pointer; white-space: nowrap; font-size: 12px;" onclick="openUnclaimedFreebieSearchModal(this)">Search</button>
                        </div>
                    </td>
                    <td style="border: 1px solid #ccc;"><input type="number" class="unclaimed-freebie-qty-input" style="width: 100%; border: 1px solid #ddd; padding: 8px; text-align: center; border-radius: 4px;" value="0" min="0"></td>
                    <td style="border: 1px solid #ccc;">
                        <input type="text" class="unclaimed-freebie-note-input" placeholder="Enter Notes (Optional)" style="width: 100%; border: 1px solid #ddd; padding: 8px; text-align: left; border-radius: 4px;">
                    </td>
                    <td style="border: 1px solid #ccc; text-align: center;">
                        <button type="button" class="btn-delete-unclaimed-freebie" style="background: #ef5350; color: white; border: none; border-radius: 4px; width: 24px; height: 24px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;" onclick="removeUnclaimedFreebieRow(this)">X</button>
                    </td>
                `;

            unclaimedFreebiesTableBody.appendChild(newRow);
        }

        // Function to remove an unclaimed freebie row
        function removeUnclaimedFreebieRow(button) {
            const row = button.closest('tr');
            const tbody = row.parentElement;
            row.remove();

            // If no rows left, show the placeholder message
            if (tbody.querySelectorAll('tr').length === 0) {
                const placeholderRow = document.createElement('tr');
                placeholderRow.id = 'no-unclaimed-freebies-row';
                placeholderRow.innerHTML = '<td colspan="4" style="text-align:center; padding: 20px;">Please click Add Unclaimed Freebies</td>';
                tbody.appendChild(placeholderRow);
            }
        }

        // Function to open unclaimed freebie search modal
        function openUnclaimedFreebieSearchModal(button) {
            currentUnclaimedFreebieRow = button.closest('tr');
            const modal = document.getElementById('searchUnclaimedFreebieModal');
            const searchInput = document.getElementById('unclaimedFreebieSearchInput');
            const resultsBody = document.getElementById('unclaimedFreebieSearchResultsBody');

            // Clear previous search
            searchInput.value = '';
            resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">Enter search term and press Enter</td></tr>';

            modal.style.display = 'flex';
            searchInput.focus();
        }

        // Function to close unclaimed freebie search modal
        function closeUnclaimedFreebieSearchModal() {
            const modal = document.getElementById('searchUnclaimedFreebieModal');
            modal.style.display = 'none';
            currentUnclaimedFreebieRow = null;
        }

        // Function to search unclaimed freebies
        function searchUnclaimedFreebies() {
            const searchInput = document.getElementById('unclaimedFreebieSearchInput');
            const searchTerm = searchInput.value.trim();
            const resultsBody = document.getElementById('unclaimedFreebieSearchResultsBody');

            if (searchTerm === '') {
                alert('Please enter a search term!');
                return;
            }

            // Show loading state
            resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">Searching...</td></tr>';

            // Fetch non-serialized items
            fetch(`search_freebies.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers.get('content-type'));

                    // Check if response is OK
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    // Clone response to read it twice (for debugging)
                    return response.clone().text().then(text => {
                        console.log('Raw response:', text);

                        // Check if response is empty
                        if (!text || text.trim() === '') {
                            throw new Error('Empty response from server');
                        }

                        // Try to parse as JSON
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            console.error('Response text:', text);
                            throw new Error('Invalid JSON response from server');
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed data:', data);
                    resultsBody.innerHTML = '';

                    // Check for error status
                    if (data.status === 'error') {
                        resultsBody.innerHTML = `<tr><td colspan="3" style="text-align:center; padding: 20px; color: red;">Error: ${data.message}</td></tr>`;
                        return;
                    }

                    // Display results
                    if (data.status === 'success' && data.data && data.data.length > 0) {
                        data.data.forEach((item) => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                    <td>${item.item_code}</td>
                                    <td>${item.description}</td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-select" onclick="selectUnclaimedFreebie('${item.item_code.replace(/'/g, "\\'")}', '${item.description.replace(/'/g, "\\'")}')">Select</button>
                                    </td>
                                `;
                            resultsBody.appendChild(row);
                        });
                    } else {
                        resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">No items found with zero stock at your branch</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsBody.innerHTML = `<tr><td colspan="3" style="text-align:center; padding: 20px; color: red;">Error: ${error.message}<br/>Check browser console for details</td></tr>`;
                });
        }

        // Function to select an unclaimed freebie
        function selectUnclaimedFreebie(itemCode, description) {
            if (currentUnclaimedFreebieRow) {
                const nameInput = currentUnclaimedFreebieRow.querySelector('.unclaimed-freebie-name-input');
                nameInput.value = description;
                nameInput.setAttribute('data-item-code', itemCode);
                closeUnclaimedFreebieSearchModal();
            }
        }

        // Attach event listener to Add Unclaimed Freebies button when DOM loads
        document.addEventListener('DOMContentLoaded', function () {
            const addUnclaimedFreebiesButton = document.querySelector('.btn-add-unclaimed-freebies');
            if (addUnclaimedFreebiesButton) {
                addUnclaimedFreebiesButton.addEventListener('click', addUnclaimedFreebieRow);
            }

            // Add event listener for unclaimed freebie search input (Enter key)
            const unclaimedFreebieSearchInput = document.getElementById('unclaimedFreebieSearchInput');
            if (unclaimedFreebieSearchInput) {
                unclaimedFreebieSearchInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        searchUnclaimedFreebies();
                    }
                });
            }

            // Close modal when clicking outside
            window.addEventListener('click', function (event) {
                const unclaimedFreebieModal = document.getElementById('searchUnclaimedFreebieModal');
                if (event.target === unclaimedFreebieModal) {
                    closeUnclaimedFreebieSearchModal();
                }
            });
        });

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
         * Helper to get prices and Others Bank status for the currently selected unit(s) in a payment section.
         * Falls back to single cart item or global selectedItemPrices.
         * @param {string} section - 'cc' or 'dc' (or section class)
         */
        function getSelectedUnitPrices(section) {
            let secEl = null;
            if (section === 'cc' || section === '.credit-card-section') {
                secEl = document.querySelector('.credit-card-section');
            } else if (section === 'dc' || section === '.debit-card-section') {
                secEl = document.querySelector('.debit-card-section');
            } else if (typeof section === 'string' && section.startsWith('.')) {
                secEl = document.querySelector(section);
            }

            let mergedPrices = {};
            let othersBankEnabled = false;
            let hasCheckedUnit = false;

            if (secEl) {
                const checkedCbs = Array.from(secEl.querySelectorAll('input[type="checkbox"][name="Unit"]:checked'));
                if (checkedCbs.length > 0) {
                    hasCheckedUnit = true;
                    if (checkedCbs.length === 1) {
                        try {
                            mergedPrices = JSON.parse(checkedCbs[0].getAttribute('data-prices') || '{}');
                        } catch (e) { }
                        const ob = checkedCbs[0].getAttribute('data-others-bank-enabled');
                        othersBankEnabled = (ob === '1' || ob === 'true');
                    } else {
                        // Multiple units selected:
                        // A bank/term price is only valid if EVERY selected unit has a price for that bank/term.
                        // If any unit has NO prices at all (empty object), or lacks that specific bank/term key,
                        // it cannot be selected across all units.
                        // Also othersBankEnabled is only true if ALL selected units have it enabled.
                        const unitPricesList = [];
                        let allHaveOthersBank = true;

                        checkedCbs.forEach(cb => {
                            let p = {};
                            try {
                                p = JSON.parse(cb.getAttribute('data-prices') || '{}');
                            } catch (e) { }
                            unitPricesList.push(p);

                            const ob = cb.getAttribute('data-others-bank-enabled');
                            if (ob !== '1' && ob !== 'true') {
                                allHaveOthersBank = false;
                            }
                        });

                        othersBankEnabled = allHaveOthersBank;

                        const anyUnitEmpty = unitPricesList.some(p => Object.keys(p).length === 0);
                        if (!anyUnitEmpty && unitPricesList.length > 0) {
                            const firstUnitKeys = Object.keys(unitPricesList[0]);
                            firstUnitKeys.forEach(key => {
                                const existsInAll = unitPricesList.every(p => p[key] !== undefined && p[key] !== null && p[key] !== '');
                                if (existsInAll) {
                                    const sumPrice = unitPricesList.reduce((sum, p) => sum + (parseFloat(p[key]) || 0), 0);
                                    mergedPrices[key] = sumPrice;
                                }
                            });
                        }
                    }
                }
            }

            // Fallback if no specific unit is checked in this section
            if (!hasCheckedUnit) {
                const rows = document.querySelectorAll('#itemsTableBody tr:not(#no-sales-row)');
                if (rows.length === 1) {
                    try {
                        mergedPrices = JSON.parse(rows[0].getAttribute('data-prices') || '{}');
                        const ob = rows[0].getAttribute('data-others-bank-enabled');
                        othersBankEnabled = (ob === '1' || ob === 'true');
                    } catch (e) { }
                } else if (rows.length > 1) {
                    const unitPricesList = [];
                    let allHaveOthersBank = true;
                    rows.forEach(r => {
                        let p = {};
                        try {
                            p = JSON.parse(r.getAttribute('data-prices') || '{}');
                        } catch (e) { }
                        unitPricesList.push(p);
                        const ob = r.getAttribute('data-others-bank-enabled');
                        if (ob !== '1' && ob !== 'true') allHaveOthersBank = false;
                    });
                    othersBankEnabled = allHaveOthersBank;
                    const anyEmpty = unitPricesList.some(p => Object.keys(p).length === 0);
                    if (!anyEmpty && unitPricesList.length > 0) {
                        const firstKeys = Object.keys(unitPricesList[0]);
                        firstKeys.forEach(key => {
                            if (unitPricesList.every(p => p[key] !== undefined && p[key] !== null && p[key] !== '')) {
                                mergedPrices[key] = unitPricesList.reduce((sum, p) => sum + (parseFloat(p[key]) || 0), 0);
                            }
                        });
                    }
                }
                if (Object.keys(mergedPrices).length === 0 && rows.length === 1 && typeof selectedItemPrices === 'object' && Object.keys(selectedItemPrices).length > 0) {
                    mergedPrices = Object.assign({}, selectedItemPrices);
                    othersBankEnabled = (typeof selectedItemOthersBankEnabled !== 'undefined') ? selectedItemOthersBankEnabled : false;
                }
            }

            return { prices: mergedPrices, othersBankEnabled: othersBankEnabled };
        }
        window.getSelectedUnitPrices = getSelectedUnitPrices;

        /**
         * Populate the Bank dropdown with ALL banks from the selected unit prices,
         * regardless of which terminal issuer/ID is selected.
         * Also includes banks from others_bank table.
         * @param {string} section  - 'cc' or 'dc'
         * @param {string|null} issuersStr - (unused, kept for signature compatibility)
         */
        function filterBanksByTerminalId(section, issuersStr) {
            const isCC = (section === 'cc');
            const bankDropdown = document.getElementById(isCC ? 'creditCardBankDropdown' : 'debitCardBankDropdown');
            const termsDropdown = document.getElementById(isCC ? 'creditCardTermsDropdown' : 'debitCardTermsDropdown');
            const amountInput = document.getElementById(isCC ? 'creditCardAmount' : 'debitCardAmount');
            if (!bankDropdown) return;

            const previousBank = bankDropdown.value;

            // Clear existing options
            bankDropdown.innerHTML = '<option value="">Select Bank</option>';
            if (termsDropdown) termsDropdown.innerHTML = '<option value="">Select Terms</option>';
            if (amountInput) { amountInput.value = ''; amountInput.removeAttribute('readonly'); }

            const unitInfo = getSelectedUnitPrices(section);
            const currentPrices = unitInfo.prices;
            const currentOthersBank = unitInfo.othersBankEnabled;

            // Collect all banks from currentPrices (no issuer filtering)
            const banks = new Set();

            // Add banks from currentPrices (excluding "Others Bank", SRP, etc.)
            if (Object.keys(currentPrices).length > 0) {
                for (const key in currentPrices) {
                    if (key === '__SRP__' || key === 'SRP') continue;
                    const spaceIndex = key.indexOf(' ');
                    if (spaceIndex !== -1) {
                        const bankName = key.substring(0, spaceIndex);
                        // Exclude "Others" from the dropdown
                        if (bankName !== 'Others') {
                            banks.add(bankName);
                        }
                    }
                }
            }

            // Add banks from others_bank table ONLY if Others Bank is enabled for this item and branch
            if (currentOthersBank) {
                <?php
                if ($others_bank_result && $others_bank_result->num_rows > 0) {
                    echo "const otherBanks = [";
                    $others_bank_result->data_seek(0);
                    $bank_list = [];
                    while ($ob_row = $others_bank_result->fetch_assoc()) {
                        $bank_list[] = "'" . addslashes($ob_row['bank_name']) . "'";
                    }
                    echo implode(", ", $bank_list);
                    echo "];";
                    echo "\n                    otherBanks.forEach(bank => banks.add(bank));";
                }
                ?>
            }

            // Sort banks alphabetically
            const sortedBanks = Array.from(banks).sort();

            sortedBanks.forEach(bank => {
                const option = document.createElement('option');
                option.value = bank;
                option.textContent = bank;
                bankDropdown.appendChild(option);
            });

            // Restore previous selection if still available
            if (previousBank && Array.from(bankDropdown.options).some(opt => opt.value === previousBank)) {
                bankDropdown.value = previousBank;
                bankDropdown.dispatchEvent(new Event('change'));
            }
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

            // Debug: Log discount field value
            const discountFieldDebug = document.getElementById('discountField');
            console.log('Discount Field Value:', discountFieldDebug.value);
            console.log('Total Amount Field Value:', totalAmountField.value);

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

            // Re-evaluate promo state right before collecting items to ensure fresh flags
            if (typeof reevaluateCartPromo === 'function') {
                reevaluateCartPromo();
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
                    // Mark as promo item if:
                    // 1. Tagged by promo rule (data-is-promo-item='1')
                    // 2. Price was reduced from base price under an active promo
                    // 3. Price is 0 (FREE) and a promo is applied (Buy 1 Take 1 scenario)
                    const isPromoItem = (
                        row.getAttribute('data-is-promo-item') === '1' ||
                        (appliedPromoId && currentPrice < basePrice) ||
                        (appliedPromoId && currentPrice === 0 && basePrice > 0)
                    ) ? 1 : 0;

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

            // Collect Unclaimed Freebies data
            const unclaimedFreebiesTableBody = document.getElementById('unclaimedFreebiesTableBody');
            const unclaimedFreebieRows = unclaimedFreebiesTableBody.querySelectorAll('tr:not(#no-unclaimed-freebies-row)');
            const unclaimedFreebies = [];

            unclaimedFreebieRows.forEach(row => {
                const nameInput = row.querySelector('.unclaimed-freebie-name-input');
                const qtyInput = row.querySelector('.unclaimed-freebie-qty-input');
                const noteInput = row.querySelector('.unclaimed-freebie-note-input');

                if (nameInput && qtyInput) {
                    const itemCode = nameInput.getAttribute('data-item-code') || '';
                    const description = nameInput.value.trim();
                    const quantity = parseInt(qtyInput.value) || 0;
                    const note = noteInput ? noteInput.value.trim() : '';

                    // Only add if item is selected and has description
                    if (description && itemCode) {
                        unclaimedFreebies.push({
                            item_code: itemCode,
                            description: description,
                            quantity: quantity,
                            note: note
                        });
                    }
                }
            });

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
                discount: parseFormattedNumber(document.getElementById('discountField').value),
                voucher: parseFormattedNumber(document.getElementById('voucherField').value),
                voucher_amount: parseFormattedNumber(document.getElementById('voucherField').value),
                token: parseFormattedNumber(document.getElementById('tokenField').value),
                total_amount: parseFormattedNumber(document.getElementById('totalAmount').value),
                points: parseFormattedNumber(document.getElementById('pointsField').value),
                commission: parseFormattedNumber(document.getElementById('commissionField').value),
                payment_data: paymentData,
                items: items,
                unclaimed_freebies: unclaimedFreebies
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

            // Fetch next invoice number from server based on branch booklet configuration
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

                        // Optional: Show a subtle indicator if using fallback format
                        if (result.format === 'fallback') {
                            console.info('Using default invoice format (no booklet configured)');
                        }
                    } else {
                        // Last resort fallback - should rarely happen
                        console.error('Failed to generate invoice number:', result.message);
                        const fallback = '<?php echo date("ymd") . "-" . $branch_code . "-00001"; ?>';
                        document.getElementById('invoice_no').value = fallback;
                    }
                })
                .catch(error => {
                    console.error('Error fetching invoice number:', error);
                    // Generate client-side fallback
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

            // Initialize discount field with 0
            const discountField = document.getElementById('discountField');
            if (discountField && !discountField.value) {
                discountField.value = '0';
            }
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
            // Check for duplicate IMEI in the table before searching
            if (imei && imei.trim() !== '') {
                const existingRows = itemsTableBody.querySelectorAll('tr');
                for (let row of existingRows) {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 2) {
                        const existingSerial = cells[1].textContent.trim();
                        if (existingSerial === imei.trim()) {
                            alert('This IMEI (' + imei + ') has already been added to this sales entry!');
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

                        // Store the auto price for later use
                        const autoPrice = formatNumber(data.data.price);
                        priceInput.setAttribute('data-auto-price', autoPrice);

                        // Check if price mode is custom or auto
                        const priceMode = priceInput.getAttribute('data-price-mode');
                        if (priceMode === 'custom') {
                            // Keep price field editable in custom mode, don't overwrite value
                            priceInput.removeAttribute('readonly');
                            priceInput.style.backgroundColor = '#ffffff';
                            priceInput.style.cursor = 'text';
                        } else {
                            // Auto mode - fill the price automatically
                            priceInput.value = autoPrice;
                        }

                        // Set quantity to 1 automatically for IMEI items
                        qtyInput.value = '1';

                        // Mark IMEI as already looked up so Add button skips the serialized-redirect
                        imeiInput.setAttribute('data-imei-found', '1');

                        // Keep price field editable for serialized items
                        priceInput.removeAttribute('readonly');
                        priceInput.style.backgroundColor = '#ffffff';
                        priceInput.style.color = '#333';
                        priceInput.style.cursor = 'text';

                        // -- Fetch item prices so Bank/Terms dropdowns populate correctly --
                        if (data.data.item_code) {
                            const ic = data.data.item_code;
                            fetch(`search_item.php?term=${encodeURIComponent(ic)}`)
                                .then(r => r.json())
                                .then(sd => {
                                    if (sd.status === 'success' && sd.data.length > 0) {
                                        // Find the exact matching item_code
                                        const match = sd.data.find(it => it.item_code === ic) || sd.data[0];
                                        selectedItemPrices = match.prices || {};
                                        selectedItemOthersBankEnabled = match.others_bank_enabled || false;
                                        itemCodeInput.setAttribute('data-prices', JSON.stringify(match.prices || {}));
                                        itemCodeInput.setAttribute('data-others-bank-enabled', match.others_bank_enabled ? '1' : '0');
                                        window.itemPricesCache = window.itemPricesCache || {};
                                        window.itemPricesCache[ic] = {
                                            prices: match.prices || {},
                                            othersBankEnabled: match.others_bank_enabled || false
                                        };
                                        // Also update any table row matching this item code
                                        document.querySelectorAll('#itemsTableBody tr').forEach(r => {
                                            if (r.getAttribute('data-item-code') === ic) {
                                                r.setAttribute('data-prices', JSON.stringify(match.prices || {}));
                                                r.setAttribute('data-others-bank-enabled', match.others_bank_enabled ? '1' : '0');
                                            }
                                        });
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
                                        itemCodeInput.setAttribute('data-has-voucher', det.has_voucher || 0);
                                        itemCodeInput.setAttribute('data-voucher-amount', det.voucher_amount || 0);
                                        itemCodeInput.setAttribute('data-has-token', det.has_token || 0);
                                        itemCodeInput.setAttribute('data-token-amount', det.token_amount || 0);
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
        let selectedItemOthersBankEnabled = false;

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
            filterBanksByTerminalId('cc');
            filterBanksByTerminalId('dc');
        }

        // Initialize listeners for Card Payment Dropdowns
        document.addEventListener('DOMContentLoaded', function () {
            const pairs = [
                { bankId: 'creditCardBankDropdown', termsId: 'creditCardTermsDropdown', amountId: 'creditCardAmount', sectionClass: '.credit-card-section', sectionCode: 'cc' },
                { bankId: 'debitCardBankDropdown', termsId: 'debitCardTermsDropdown', amountId: 'debitCardAmount', sectionClass: '.debit-card-section', sectionCode: 'dc' }
            ];

            pairs.forEach(pair => {
                const bankDropdown = document.getElementById(pair.bankId);
                const termsDropdown = document.getElementById(pair.termsId);
                const amountInput = document.getElementById(pair.amountId);

                if (bankDropdown) {
                    bankDropdown.addEventListener('change', function () {
                        const selectedBank = this.value;
                        // Store the currently selected term before clearing
                        const previousTerm = termsDropdown ? termsDropdown.value : '';

                        if (termsDropdown) termsDropdown.innerHTML = '<option value="">Select Terms</option>';
                        if (amountInput) {
                            amountInput.value = '';
                            amountInput.removeAttribute('readonly');
                            amountInput.dispatchEvent(new Event('input'));
                        }

                        if (selectedBank) {
                            const unitInfo = getSelectedUnitPrices(pair.sectionCode);
                            const currentPrices = unitInfo.prices;

                            // List of banks from others_bank table
                            const otherBanksList = [<?php
                            if ($others_bank_result && $others_bank_result->num_rows > 0) {
                                $others_bank_result->data_seek(0);
                                $bank_list = [];
                                while ($ob_row = $others_bank_result->fetch_assoc()) {
                                    $bank_list[] = "'" . addslashes($ob_row['bank_name']) . "'";
                                }
                                echo implode(", ", $bank_list);
                            }
                            ?>];

                            // Check if selected bank is from others_bank table
                            const isOtherBank = otherBanksList.includes(selectedBank);

                            if (isOtherBank) {
                                // For Other Banks: Add standard installment terms
                                const standardTerms = ['3 Months', '6 Months', '12 Months', '24 Months'];
                                standardTerms.forEach(term => {
                                    const option = document.createElement('option');
                                    option.value = term;
                                    option.textContent = term;
                                    option.setAttribute('data-is-other-bank', 'true');
                                    termsDropdown.appendChild(option);
                                });

                                // Restore previous selection if it exists in new options
                                if (previousTerm && standardTerms.includes(previousTerm)) {
                                    termsDropdown.value = previousTerm;
                                }

                                // Keep amount field editable for Other Banks
                                if (amountInput) {
                                    amountInput.removeAttribute('readonly');
                                    amountInput.placeholder = 'Enter amount';
                                }
                            } else {
                                // For regular banks: Use item prices from selected unit(s)
                                const searchPrefix = selectedBank + ' ';

                                for (const key in currentPrices) {
                                    if (key.startsWith(searchPrefix)) {
                                        const term = key.substring(searchPrefix.length);
                                        const option = document.createElement('option');
                                        option.value = term;
                                        const cleanTerm = term.replace(/<[^>]*>/g, '').trim();
                                        option.textContent = cleanTerm;
                                        option.setAttribute('data-full-key', key);
                                        option.setAttribute('data-actual-bank', selectedBank);
                                        termsDropdown.appendChild(option);
                                    }
                                }

                                // Restore previous selection if it exists in new options
                                if (previousTerm) {
                                    const options = Array.from(termsDropdown.options);
                                    const matchingOption = options.find(opt => opt.value === previousTerm);
                                    if (matchingOption) {
                                        termsDropdown.value = previousTerm;
                                    }
                                }
                            }
                        }
                    });
                }

                if (termsDropdown) {
                    termsDropdown.addEventListener('change', function () {
                        const selectedOption = this.options[this.selectedIndex];
                        const isOtherBank = selectedOption ? selectedOption.getAttribute('data-is-other-bank') : null;
                        const fullKey = selectedOption ? selectedOption.getAttribute('data-full-key') : null;

                        // Store the selected value explicitly to ensure it persists
                        if (this.value) {
                            this.setAttribute('data-selected-value', this.value);
                        }

                        const unitInfo = getSelectedUnitPrices(pair.sectionCode);
                        const currentPrices = unitInfo.prices;

                        if (isOtherBank) {
                            // For Other Banks: Keep amount editable
                            if (amountInput) {
                                amountInput.removeAttribute('readonly');
                                amountInput.placeholder = 'Enter amount';
                                amountInput.focus();
                            }
                        } else if (fullKey && currentPrices[fullKey] !== undefined) {
                            // For regular banks: Set price from item and make readonly
                            const price = currentPrices[fullKey];
                            if (amountInput) {
                                const formattedPrice = formatNumber(price);
                                amountInput.value = formattedPrice;
                                amountInput.setAttribute('readonly', 'readonly');
                                amountInput.dispatchEvent(new Event('input'));

                                const section = amountInput.closest(pair.sectionClass);
                                if (section) {
                                    const totalInput = section.querySelector('.total-input');
                                    if (totalInput) totalInput.value = formattedPrice;
                                }
                            }
                        } else {
                            if (amountInput) {
                                amountInput.value = '';
                                amountInput.removeAttribute('readonly');
                                amountInput.dispatchEvent(new Event('input'));
                            }
                            const section = amountInput.closest(pair.sectionClass);
                            if (section) {
                                const totalInput = section.querySelector('.total-input');
                                if (totalInput) totalInput.value = '';
                            }
                        }
                    });
                }
            });
        });

        window.selectItem = function (index) {
            const item = currentSearchResults[index];
            if (!item) return;

            // Store prices and Others Bank flag
            selectedItemPrices = item.prices || {};
            selectedItemOthersBankEnabled = item.others_bank_enabled || false;
            itemCodeInput.setAttribute('data-prices', JSON.stringify(item.prices || {}));
            itemCodeInput.setAttribute('data-others-bank-enabled', item.others_bank_enabled ? '1' : '0');
            if (item.item_code) {
                window.itemPricesCache = window.itemPricesCache || {};
                window.itemPricesCache[item.item_code] = {
                    prices: item.prices || {},
                    othersBankEnabled: item.others_bank_enabled || false
                };
            }
            updateCardPaymentDropdowns();

            const code = item.item_code;
            const description = item.description;
            const price = item.price;
            const commission = item.commission;
            const has_commission = item.has_commission;
            const points = item.points;
            const has_points = item.has_points;
            const has_voucher = item.has_voucher;
            const voucher_amount = item.voucher_amount;
            const has_token = item.has_token;
            const token_amount = item.token_amount;
            const branchAllowed = (item.branch_allowed !== false);
            const selectedQty = Math.max(parseInt(qtyInput.value) || 0, 1);

            // Block selection immediately when item is not available for current branch context
            // (e.g. Superadmin account on ALL BRANCHES with no assigned stock branch pricing)
            if (!branchAllowed) {
                alert('Insufficient stock no available');
                return;
            }

            // Calculate total quantity already in table for this item code
            let totalQtyInTable = 0;
            const itemsTableBody = document.getElementById('itemsTableBody');
            const existingRows = itemsTableBody.querySelectorAll('tr:not(#no-sales-row)');

            // Only sum quantities for non-serialized items
            // Serialized items will have their stock checked based on IMEI count in stock_on_hand
            existingRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 3) {
                    const rowDesc = cells[0].textContent.trim();
                    const rowImei = cells[1].textContent.trim();
                    const qtyInput = row.querySelector('.qty-input');
                    const rowQty = qtyInput ? parseInt(qtyInput.value) || 0 : 0;

                    // Sum quantities for the same item (matching description and no IMEI)
                    if (rowDesc === description && (!rowImei || rowImei === '')) {
                        totalQtyInTable += rowQty;
                    }
                }
            });

            const totalRequestedQty = totalQtyInTable + selectedQty;
            console.log('Modal selection - Quantity in table:', totalQtyInTable);
            console.log('Modal selection - Selected quantity:', selectedQty);
            console.log('Modal selection - Total requested:', totalRequestedQty);

            // Validate stock immediately when user clicks Select in modal
            fetch(`check_stock_availability.php?item_code=${encodeURIComponent(code)}&imei=&qty=${totalRequestedQty}&force_branch=true`)
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

                                    // Re-enable price field when clearing
                                    priceInput.removeAttribute('readonly');
                                    priceInput.style.backgroundColor = '#ffffff';
                                    priceInput.style.cursor = 'text';

                                    closeSearchModal();
                                    return;
                                }

                                // Item is not serialized - proceed normally but lock IMEI field
                                itemCodeInput.value = code;
                                itemDescInput.value = description;
                                priceInput.value = formatNumber(price);

                                // Store commission and points data as data attributes
                                itemCodeInput.setAttribute('data-commission', commission || 0);
                                itemCodeInput.setAttribute('data-has-commission', has_commission || 0);
                                itemCodeInput.setAttribute('data-points', points || 0);
                                itemCodeInput.setAttribute('data-has-points', has_points || 0);
                                itemCodeInput.setAttribute('data-has-voucher', has_voucher || 0);
                                itemCodeInput.setAttribute('data-voucher-amount', voucher_amount || 0);
                                itemCodeInput.setAttribute('data-has-token', has_token || 0);
                                itemCodeInput.setAttribute('data-token-amount', token_amount || 0);

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
            const price = parseNumber(priceInput.value);
            const qty = parseInt(qtyInput.value);

            // Check if Assisted By is selected
            const assistedBy = document.getElementById('assisted_by').value;
            if (!assistedBy || assistedBy === '') {
                alert("Please select Assisted By before adding items.");
                document.getElementById('assisted_by').focus();
                return;
            }

            // Get commission and points data
            const commission = parseFloat(itemCodeInput.getAttribute('data-commission')) || 0;
            const has_commission = parseInt(itemCodeInput.getAttribute('data-has-commission')) || 0;
            const points = parseFloat(itemCodeInput.getAttribute('data-points')) || 0;
            const has_points = parseInt(itemCodeInput.getAttribute('data-has-points')) || 0;
            const has_voucher = parseInt(itemCodeInput.getAttribute('data-has-voucher')) || 0;
            const voucher_amount = parseFloat(itemCodeInput.getAttribute('data-voucher-amount')) || 0;
            const has_token = parseInt(itemCodeInput.getAttribute('data-has-token')) || 0;
            const token_amount = parseFloat(itemCodeInput.getAttribute('data-token-amount')) || 0;

            if (!desc) {
                alert("Please select or enter an item description.");
                return;
            }

            // For serialized items (with IMEI), price is required
            // For non-serialized items, price can be empty/zero
            if (imei && imei.trim() !== '' && !price) {
                alert("Please enter a price for this serialized item.");
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

            // Calculate total quantity already in table for this item code
            let totalQtyInTable = 0;
            const existingRows = itemsTableBody.querySelectorAll('tr:not(#no-sales-row)');

            // Only sum quantities for non-serialized items (items without IMEI or with empty IMEI)
            if (!imei || imei.trim() === '') {
                existingRows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 3) {
                        // Get description from first cell
                        const rowDesc = cells[0].textContent.trim();
                        const rowImei = cells[1].textContent.trim();
                        const qtyInput = row.querySelector('.qty-input');
                        const rowQty = qtyInput ? parseInt(qtyInput.value) || 0 : 0;

                        // Sum quantities for the same item (matching description and no IMEI)
                        if (rowDesc === desc && (!rowImei || rowImei === '')) {
                            totalQtyInTable += rowQty;
                        }
                    }
                });
            }

            const totalRequestedQty = totalQtyInTable + qty;
            console.log('Quantity in table:', totalQtyInTable);
            console.log('Current quantity:', qty);
            console.log('Total requested:', totalRequestedQty);

            const stockCheckUrl = `check_stock_availability.php?item_code=${encodeURIComponent(itemCode)}&imei=${encodeURIComponent(imei)}&qty=${totalRequestedQty}&force_branch=true`;
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
                        // Check for duplicate IMEI in the table
                        if (imei && imei.trim() !== '') {
                            const existingRows = itemsTableBody.querySelectorAll('tr');
                            for (let row of existingRows) {
                                const cells = row.querySelectorAll('td');
                                if (cells.length >= 2) {
                                    const existingSerial = cells[1].textContent.trim();
                                    if (existingSerial === imei.trim()) {
                                        alert('This IMEI (' + imei + ') has already been added to this sales entry!');
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
                        <td style="text-align: center;">${formatNumber(price)}<input type="hidden" class="price-input-table" value="${price}"></td>
                        <td style="text-align: center;"><button type="button" class="btn-delete-item" onclick="removeRow(this)">X</button></td>
                    `;
                        newRow.setAttribute('data-commission', commission);
                        newRow.setAttribute('data-has-commission', has_commission);
                        newRow.setAttribute('data-points', points);
                        newRow.setAttribute('data-has-points', has_points);
                        newRow.setAttribute('data-has-voucher', has_voucher);
                        newRow.setAttribute('data-voucher-amount', voucher_amount);
                        newRow.setAttribute('data-has-token', has_token);
                        newRow.setAttribute('data-token-amount', token_amount);
                        newRow.setAttribute('data-item-code', itemCode || ''); // Store item code
                        newRow.setAttribute('data-base-price', price); // Store base price for promo

                        let itemPricesStr = itemCodeInput.getAttribute('data-prices');
                        let itemOthersBankStr = itemCodeInput.getAttribute('data-others-bank-enabled');
                        if ((!itemPricesStr || itemPricesStr === '{}') && itemCode && window.itemPricesCache && window.itemPricesCache[itemCode]) {
                            itemPricesStr = JSON.stringify(window.itemPricesCache[itemCode].prices || {});
                            itemOthersBankStr = window.itemPricesCache[itemCode].othersBankEnabled ? '1' : '0';
                        }
                        if (!itemPricesStr && typeof selectedItemPrices !== 'undefined') {
                            itemPricesStr = JSON.stringify(selectedItemPrices);
                        }
                        if (!itemOthersBankStr) {
                            itemOthersBankStr = (typeof selectedItemOthersBankEnabled !== 'undefined' && selectedItemOthersBankEnabled) ? '1' : '0';
                        }
                        newRow.setAttribute('data-prices', itemPricesStr || '{}');
                        newRow.setAttribute('data-others-bank-enabled', itemOthersBankStr || '0');

                        const noSalesRow = document.getElementById('no-sales-row');
                        if (noSalesRow) noSalesRow.remove();

                        itemsTableBody.appendChild(newRow);

                        // Re-evaluate promo after adding item
                        reevaluateCartPromo();

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

                        // Reset Price dropdown and make price field readonly again
                        const priceDropdown = document.getElementById('priceDropdown');
                        if (priceDropdown) {
                            priceDropdown.value = '';
                            priceDropdown.style.opacity = '0';
                        }
                        priceInput.setAttribute('readonly', 'readonly');
                        priceInput.style.backgroundColor = '#ffffff';
                        priceInput.style.color = '#333';
                        priceInput.placeholder = '';
                        priceInput.removeAttribute('data-auto-price');
                        priceInput.removeAttribute('data-price-mode');

                        // Keep Payment Method dropdown value (don't reset it)
                        // Payment Method dropdown removed


                        itemCodeInput.removeAttribute('data-commission');
                        itemCodeInput.removeAttribute('data-has-commission');
                        itemCodeInput.removeAttribute('data-points');
                        itemCodeInput.removeAttribute('data-has-points');
                        itemCodeInput.removeAttribute('data-prices');
                        itemCodeInput.removeAttribute('data-others-bank-enabled');

                        updateTotals();

                        // Check discount permission
                        fetch(`check_discount_permission.php?item_code=${encodeURIComponent(itemCode)}`)
                            .then(r => r.json())
                            .then(d => {
                                if (d.status === 'success') {
                                    if (d.discount_editable) {
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
                            .catch(err => console.error('Error checking discount:', err));
                    };

                    if (imeiAlreadyFound) {
                        // IMEI already verified  add directly, no serialized check needed
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

                            // Item is not serialized  use the shared proceedToAddRow helper
                            proceedToAddRow(commission, has_commission, points, has_points);
                        })
                        .catch(error => {
                            console.error('Error checking serial status:', error);
                        });
                } else {
                    // No item code - just add to table
                    // Check for duplicate IMEI in the table
                    if (imei && imei.trim() !== '') {
                        const existingRows = itemsTableBody.querySelectorAll('tr');
                        for (let row of existingRows) {
                            const cells = row.querySelectorAll('td');
                            if (cells.length >= 2) {
                                const existingSerial = cells[1].textContent.trim();
                                if (existingSerial === imei.trim()) {
                                    alert('This IMEI (' + imei + ') has already been added to this sales entry!');
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
                    <td style="text-align: center;">${formatNumber(price)}<input type="hidden" class="price-input-table" value="${price}"></td>
                    <td style="text-align: center;"><button type="button" class="btn-delete-item" onclick="removeRow(this)">X</button></td>
                `;

                    // Store commission and points data in the row
                    newRow.setAttribute('data-commission', commission);
                    newRow.setAttribute('data-has-commission', has_commission);
                    newRow.setAttribute('data-points', points);
                    newRow.setAttribute('data-has-points', has_points);
                    newRow.setAttribute('data-has-voucher', has_voucher);
                    newRow.setAttribute('data-voucher-amount', voucher_amount);
                    newRow.setAttribute('data-has-token', has_token);
                    newRow.setAttribute('data-token-amount', token_amount);
                    newRow.setAttribute('data-item-code', itemCode || ''); // Store item code
                    newRow.setAttribute('data-base-price', price); // Store base price for promo

                    let fallbackPricesStr = itemCodeInput.getAttribute('data-prices');
                    let fallbackOthersBankStr = itemCodeInput.getAttribute('data-others-bank-enabled');
                    if ((!fallbackPricesStr || fallbackPricesStr === '{}') && itemCode && window.itemPricesCache && window.itemPricesCache[itemCode]) {
                        fallbackPricesStr = JSON.stringify(window.itemPricesCache[itemCode].prices || {});
                        fallbackOthersBankStr = window.itemPricesCache[itemCode].othersBankEnabled ? '1' : '0';
                    }
                    if (!fallbackPricesStr && typeof selectedItemPrices !== 'undefined') {
                        fallbackPricesStr = JSON.stringify(selectedItemPrices);
                    }
                    if (!fallbackOthersBankStr) {
                        fallbackOthersBankStr = (typeof selectedItemOthersBankEnabled !== 'undefined' && selectedItemOthersBankEnabled) ? '1' : '0';
                    }
                    newRow.setAttribute('data-prices', fallbackPricesStr || '{}');
                    newRow.setAttribute('data-others-bank-enabled', fallbackOthersBankStr || '0');

                    const noSalesRow = document.getElementById('no-sales-row');
                    if (noSalesRow) {
                        noSalesRow.remove();
                    }

                    itemsTableBody.appendChild(newRow);

                    // Re-evaluate promo after adding item
                    reevaluateCartPromo();

                    itemCodeInput.value = '';
                    itemDescInput.value = '';
                    document.getElementById('imei').value = '';
                    qtyInput.value = '0';
                    priceInput.value = '';

                    // Re-enable price field when clearing
                    priceInput.removeAttribute('readonly');
                    priceInput.style.backgroundColor = '#ffffff';
                    priceInput.style.cursor = 'text';

                    // Clear data attributes
                    itemCodeInput.removeAttribute('data-commission');
                    itemCodeInput.removeAttribute('data-has-commission');
                    itemCodeInput.removeAttribute('data-points');
                    itemCodeInput.removeAttribute('data-has-points');
                    itemCodeInput.removeAttribute('data-prices');
                    itemCodeInput.removeAttribute('data-others-bank-enabled');

                    updateTotals();
                }
            } // End of proceedWithAddingItem function
        });

        window.removeRow = function (btn) {
            const row = btn.closest('tr');
            if (row) {
                row.remove();
                updateTotals();
                reevaluateCartPromo();

                const tbody = document.getElementById('itemsTableBody');
                if (tbody && tbody.querySelectorAll('tr').length === 0) {
                    const noSalesRow = document.createElement('tr');
                    noSalesRow.id = 'no-sales-row';
                    noSalesRow.innerHTML = '<td colspan="5" style="text-align: center; color: #888;">No items added yet.</td>';
                    tbody.appendChild(noSalesRow);
                }
            }
        };

        window.updateTotals = function () {
            let totalQty = 0;
            let grandTotal = 0;
            let totalCommission = 0;
            let totalPoints = 0;
            let totalVoucher = 0;
            let totalToken = 0;
            let hasAnyCommission = false;
            let hasAnyPoints = false;

            const rows = itemsTableBody.querySelectorAll('tr');
            rows.forEach(row => {
                const qtyInput = row.querySelector('.qty-input');
                const priceInput = row.querySelector('.price-input-table');

                if (qtyInput && priceInput) {
                    const qty = parseInt(qtyInput.value) || 0;
                    const price = parseNumber(priceInput.value) || 0;

                    totalQty += qty;
                    grandTotal += (qty * price);

                    // Get commission and points data from row
                    const commission = parseFloat(row.getAttribute('data-commission')) || 0;
                    const has_commission = parseInt(row.getAttribute('data-has-commission')) || 0;
                    const points = parseFloat(row.getAttribute('data-points')) || 0;
                    const has_points = parseInt(row.getAttribute('data-has-points')) || 0;
                    const has_voucher = parseInt(row.getAttribute('data-has-voucher')) || 0;
                    const voucher_amount = parseFloat(row.getAttribute('data-voucher-amount')) || 0;
                    const has_token = parseInt(row.getAttribute('data-has-token')) || 0;
                    const token_amount = parseFloat(row.getAttribute('data-token-amount')) || 0;

                    // Only add to totals if the flags are enabled
                    if (has_commission === 1) {
                        totalCommission += commission;
                        hasAnyCommission = true;
                    }

                    if (has_points === 1) {
                        totalPoints += points;
                        hasAnyPoints = true;
                    }

                    if (has_voucher === 1) {
                        totalVoucher += voucher_amount;
                    }

                    if (has_token === 1) {
                        totalToken += token_amount;
                    }
                }
            });

            // Get discount value and subtract from grand total along with voucher and token
            const discount = parseNumber(discountField.value) || 0;
            const finalTotal = grandTotal - discount - totalVoucher - totalToken;

            totalQtyField.value = totalQty;
            totalAmountField.value = formatNumber(finalTotal > 0 ? finalTotal : 0);

            // Update voucher field
            const voucherField = document.getElementById('voucherField');
            if (voucherField) {
                voucherField.value = formatNumber(totalVoucher);
                // Change background color based on whether there's a voucher
                if (totalVoucher > 0) {
                    voucherField.style.backgroundColor = '#ffffff';
                    voucherField.style.cursor = 'not-allowed';
                } else {
                    voucherField.style.backgroundColor = '#e0e0e0';
                    voucherField.style.cursor = 'not-allowed';
                }
            }

            // Update token field
            const tokenField = document.getElementById('tokenField');
            if (tokenField) {
                tokenField.value = formatNumber(totalToken);
                // Change background color based on whether there's a token
                if (totalToken > 0) {
                    tokenField.style.backgroundColor = '#ffffff';
                    tokenField.style.cursor = 'not-allowed';
                } else {
                    tokenField.style.backgroundColor = '#e0e0e0';
                    tokenField.style.cursor = 'not-allowed';
                }
            }

            // Update commission and points fields
            const commissionField = document.getElementById('commissionField');
            const pointsField = document.getElementById('pointsField');

            if (commissionField) {
                commissionField.value = hasAnyCommission ? formatNumber(totalCommission) : '';
                // Change background color based on whether commission is enabled
                if (hasAnyCommission) {
                    commissionField.style.backgroundColor = '#ffffff';
                } else {
                    commissionField.style.backgroundColor = '#e0e0e0';
                }
            }

            if (pointsField) {
                pointsField.value = hasAnyPoints ? formatNumber(totalPoints) : '';
                // Points field already has background-color set in HTML, update dynamically
                if (hasAnyPoints) {
                    pointsField.style.backgroundColor = '#ffffff';
                } else {
                    pointsField.style.backgroundColor = '#e0e0e0';
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
         * - 2+ items ? show dropdown with all items
         * - 1 item   ? auto-select, show read-only text
         * - 0 items  ? hide the row
         */
        // Sync unit selectors so a unit chosen in one payment method is excluded from other payment methods
        function syncUnitSelectorsAcrossSections(triggeredUnitRow) {
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

            const activeSections = sections
                .map(secClass => document.querySelector(secClass))
                .filter(secEl => secEl && secEl.style.display === 'block');

            if (activeSections.length === 0) return;

            if (activeSections.length === 1) {
                const unitRow = activeSections[0].querySelector('.unit-selector-row');
                if (unitRow) {
                    const container = unitRow.querySelector('.unit-checkboxes');
                    if (container) {
                        const itemLabels = container.querySelectorAll('label:not(:has(.select-all-units-cb))');
                        itemLabels.forEach(lbl => {
                            lbl.style.display = 'flex';
                        });
                        const unitCbs = Array.from(container.querySelectorAll('input[type="checkbox"][name="Unit"]'));
                        let checked = unitCbs.filter(cb => cb.checked);
                        if (checked.length === 0 && unitCbs.length > 0) {
                            unitCbs.forEach(cb => cb.checked = true);
                            checked = unitCbs;
                        }
                        const selectAllCb = container.querySelector('.select-all-units-cb');
                        const selectAllLbl = selectAllCb ? selectAllCb.closest('label') : null;
                        if (selectAllLbl) {
                            selectAllLbl.style.display = unitCbs.length > 1 ? 'flex' : 'none';
                        }
                        if (selectAllCb && unitCbs.length > 0) {
                            selectAllCb.checked = (checked.length === unitCbs.length);
                        }
                        const textSpan = unitRow.querySelector('.selected-text');
                        if (textSpan) {
                            if (checked.length === 0) textSpan.textContent = '-- Select Units --';
                            else if (checked.length === 1) textSpan.textContent = checked[0].value;
                            else if (unitCbs.length > 1 && checked.length === unitCbs.length) textSpan.textContent = 'All Units Selected (' + checked.length + ')';
                            else textSpan.textContent = checked.length + ' Units Selected';
                        }
                    }
                }
                // Refresh Bank dropdowns even when only one payment section is active
                filterBanksByTerminalId('cc');
                filterBanksByTerminalId('dc');
                return;
            }

            // Multiple active sections!
            // 1. If triggered by a specific section's checkbox change, uncheck any duplicates in other sections
            if (triggeredUnitRow) {
                const checkedInTriggered = Array.from(triggeredUnitRow.querySelectorAll('input[type="checkbox"][name="Unit"]:checked')).map(cb => cb.value);
                activeSections.forEach(secEl => {
                    const unitRow = secEl.querySelector('.unit-selector-row');
                    if (unitRow && unitRow !== triggeredUnitRow) {
                        const cbs = unitRow.querySelectorAll('input[type="checkbox"][name="Unit"]');
                        cbs.forEach(cb => {
                            if (checkedInTriggered.includes(cb.value) && cb.checked) {
                                cb.checked = false;
                            }
                        });
                    }
                });
            } else {
                // Initial setup or payment method toggle:
                // Ensure no unit is checked in multiple sections simultaneously.
                const claimed = new Set();
                activeSections.forEach(secEl => {
                    const unitRow = secEl.querySelector('.unit-selector-row');
                    if (!unitRow) return;
                    const cbs = unitRow.querySelectorAll('input[type="checkbox"][name="Unit"]');
                    cbs.forEach(cb => {
                        if (cb.checked) {
                            if (claimed.has(cb.value)) {
                                cb.checked = false;
                            } else {
                                claimed.add(cb.value);
                            }
                        }
                    });
                });
            }

            // 2. Hide units from each section if they are checked in ANY OTHER active section
            activeSections.forEach(secEl => {
                const unitRow = secEl.querySelector('.unit-selector-row');
                if (!unitRow) return;

                const claimedByOthers = new Set();
                activeSections.forEach(otherSec => {
                    if (otherSec !== secEl) {
                        const otherUnitRow = otherSec.querySelector('.unit-selector-row');
                        if (otherUnitRow) {
                            otherUnitRow.querySelectorAll('input[type="checkbox"][name="Unit"]:checked').forEach(cb => {
                                claimedByOthers.add(cb.value);
                            });
                        }
                    }
                });

                const container = unitRow.querySelector('.unit-checkboxes');
                if (!container) return;

                const itemLabels = container.querySelectorAll('label:not(:has(.select-all-units-cb))');
                itemLabels.forEach(lbl => {
                    const cb = lbl.querySelector('input[type="checkbox"][name="Unit"]');
                    if (!cb) return;
                    if (claimedByOthers.has(cb.value)) {
                        lbl.style.display = 'none';
                        cb.checked = false;
                    } else {
                        lbl.style.display = 'flex';
                    }
                });

                const visibleItemCbs = Array.from(container.querySelectorAll('input[type="checkbox"][name="Unit"]'))
                    .filter(cb => cb.closest('label').style.display !== 'none');

                let checkedItemCbs = visibleItemCbs.filter(cb => cb.checked);
                if (checkedItemCbs.length === 0 && visibleItemCbs.length === 1 && !triggeredUnitRow) {
                    visibleItemCbs[0].checked = true;
                    checkedItemCbs = [visibleItemCbs[0]];
                }

                const selectAllCb = container.querySelector('.select-all-units-cb');
                const selectAllLbl = selectAllCb ? selectAllCb.closest('label') : null;
                if (selectAllLbl) {
                    selectAllLbl.style.display = (visibleItemCbs.length > 1) ? 'flex' : 'none';
                }
                if (selectAllCb && visibleItemCbs.length > 0) {
                    selectAllCb.checked = (checkedItemCbs.length === visibleItemCbs.length);
                }

                const textSpan = unitRow.querySelector('.selected-text');
                if (textSpan) {
                    if (checkedItemCbs.length === 0) textSpan.textContent = '-- Select Units --';
                    else if (checkedItemCbs.length === 1) textSpan.textContent = checkedItemCbs[0].value;
                    else if (visibleItemCbs.length > 1 && checkedItemCbs.length === visibleItemCbs.length) textSpan.textContent = 'All Available Units (' + checkedItemCbs.length + ')';
                    else textSpan.textContent = checkedItemCbs.length + ' Units Selected';
                }
            });

            // Refresh Bank dropdowns when unit selections change across sections
            filterBanksByTerminalId('cc');
            filterBanksByTerminalId('dc');
        }
        window.syncUnitSelectorsAcrossSections = syncUnitSelectorsAcrossSections;

        function populateInstallmentUnit() {
            const tbody = document.getElementById('itemsTableBody');
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.id || r.id !== 'no-sales-row');
            const unitRows = document.querySelectorAll('.unit-selector-row');

            if (unitRows.length === 0) return;

            // Build item list from table rows including prices and others_bank_enabled
            const items = [];
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 2) {
                    const desc = cells[0].textContent.trim();
                    const serial = cells[1].textContent.trim();
                    const pricesStr = row.getAttribute('data-prices') || '{}';
                    const othersBankStr = row.getAttribute('data-others-bank-enabled') || '0';
                    let prices = {};
                    try { prices = JSON.parse(pricesStr); } catch (e) { }
                    if (desc) items.push({
                        desc,
                        serial,
                        itemCode: row.getAttribute('data-item-code') || '',
                        prices: prices,
                        othersBankEnabled: (othersBankStr === '1' || othersBankStr === 'true')
                    });
                }
            });

            unitRows.forEach((unitRow, unitRowIdx) => {
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
                    selectAllCb.style.marginTop = '0';
                    selectAllCb.style.width = '16px';
                    selectAllCb.style.height = '16px';

                    selectAllCb.addEventListener('change', function () {
                        const visibleUnitCbs = Array.from(container.querySelectorAll('input[type="checkbox"][name="Unit"]'))
                            .filter(cb => cb.closest('label').style.display !== 'none');
                        visibleUnitCbs.forEach(cb => cb.checked = this.checked);
                        syncUnitSelectorsAcrossSections(unitRow);
                        if (typeof window.updateSectionTotal === 'function') {
                            window.updateSectionTotal();
                        }
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
                    cb.setAttribute('data-prices', JSON.stringify(item.prices || {}));
                    cb.setAttribute('data-others-bank-enabled', item.othersBankEnabled ? '1' : '0');
                    cb.setAttribute('data-item-code', item.itemCode || '');
                    cb.setAttribute('data-serial', item.serial || '');
                    cb.style.marginTop = '2px';
                    cb.style.width = '16px';
                    cb.style.height = '16px';

                    cb.addEventListener('change', function () {
                        syncUnitSelectorsAcrossSections(unitRow);
                        if (typeof window.updateSectionTotal === 'function') {
                            window.updateSectionTotal();
                        }
                    });

                    // Only first unit row checks by default if multiple sections exist
                    cb.checked = (unitRowIdx === 0);

                    lbl.appendChild(cb);
                    lbl.appendChild(document.createTextNode(labelText));
                    container.appendChild(lbl);
                });
            });

            syncUnitSelectorsAcrossSections();
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.custom-multiselect')) {
                document.querySelectorAll('.multiselect-dropdown').forEach(d => d.style.display = 'none');
            }
        });

        function skipCancelReceipt() {
            // Get the current invoice number
            const invoiceNo = document.getElementById('invoice_no').value;

            if (!invoiceNo || invoiceNo === 'Loading invoice number...') {
                alert('Please wait for the invoice number to load.');
                return;
            }

            // Prompt for reason
            const reason = prompt('Please enter the reason for skipping/canceling the receipt:');

            if (!reason || reason.trim() === '') {
                alert('Reason is required to skip/cancel receipt.');
                return;
            }

            // Get user information
            const branchCode = '<?php echo $branch_code; ?>';
            const branchName = '<?php echo $user_branch_name; ?>';
            const requestedBy = '<?php echo isset($_SESSION['first_name']) && isset($_SESSION['last_name']) ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] : $_SESSION['username']; ?>';

            // Send request to server
            fetch('submit_skip_receipt.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'invoice_no=' + encodeURIComponent(invoiceNo) +
                    '&branch_code=' + encodeURIComponent(branchCode) +
                    '&branch_name=' + encodeURIComponent(branchName) +
                    '&requested_by=' + encodeURIComponent(requestedBy) +
                    '&reason=' + encodeURIComponent(reason)
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Skip receipt request submitted successfully!\nRequest ID: ' + result.request_id + '\n\nPlease wait for approval before proceeding.');
                        // Optionally clear the form or refresh
                        clearMainForm();
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while submitting the request.');
                });
        }

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
                            '2. Remove/unapply the promo by selecting "Select Promo" from the Apply Promo dropdown'
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
                discountField.addEventListener('input', function () {
                    formatInput(this);
                    updateTotals(); // Recalculate totals when discount changes
                });
            }

            // 2b. Attach to Main Price Input Field
            const priceField = document.getElementById('price');
            if (priceField) {
                priceField.addEventListener('input', function () { formatInput(this); });
            }

            // 2c. Price Dropdown Handler - Enable/Disable price field based on Auto/Custom selection
            const priceDropdown = document.getElementById('priceDropdown');
            if (priceDropdown && priceField) {
                priceDropdown.addEventListener('change', function () {
                    if (this.value === 'auto') {
                        // Auto mode - readonly, will be filled automatically
                        priceField.setAttribute('readonly', 'readonly');
                        priceField.setAttribute('data-price-mode', 'auto');
                        priceField.style.backgroundColor = '#ffffff';
                        priceField.style.color = '#333';
                        priceField.placeholder = '';

                        // Restore auto price if available
                        const autoPrice = priceField.getAttribute('data-auto-price');
                        if (autoPrice) {
                            priceField.value = autoPrice;
                        }
                    } else if (this.value === 'custom') {
                        // Custom mode - enable price field for manual input (stays editable)
                        priceField.removeAttribute('readonly');
                        priceField.setAttribute('data-price-mode', 'custom');
                        priceField.style.backgroundColor = '#ffffff';
                        priceField.style.color = '#333';
                        priceField.style.cursor = 'text';
                        priceField.placeholder = '';
                        priceField.focus();
                        // Make dropdown invisible again so user can type
                        priceDropdown.style.opacity = '0';
                    } else {
                        // No selection - readonly
                        priceField.setAttribute('readonly', 'readonly');
                        priceField.removeAttribute('data-price-mode');
                        priceField.style.backgroundColor = '#ffffff';
                        priceField.style.color = '#333';
                        priceField.placeholder = '';
                    }
                });

                // Hide dropdown when it loses focus
                priceDropdown.addEventListener('blur', function () {
                    setTimeout(() => {
                        if (this.value !== 'custom') {
                            this.style.opacity = '0';
                        }
                    }, 200);
                });
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

            // Payment Method Dropdown removed - no longer needed
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

                    // Validate Credit Card fields if Credit Card is selected
                    if (creditCardSection && creditCardSection.style.display === 'block') {
                        const terminalIssuer = document.getElementById('ccTerminalIssuer');
                        const terminalId = document.getElementById('ccTerminalId');
                        const bank = document.getElementById('creditCardBankDropdown');
                        const terms = document.getElementById('creditCardTermsDropdown');
                        const mid = creditCardSection.querySelector('input[type="text"]'); // MID input
                        const cardNo = creditCardSection.querySelectorAll('input[type="text"]')[1]; // Card No input
                        const approvalCode = creditCardSection.querySelectorAll('input[type="text"]')[2]; // Approval Code
                        const batch = creditCardSection.querySelectorAll('input[type="text"]')[3]; // Batch
                        const amount = document.getElementById('creditCardAmount');

                        if (!terminalIssuer || !terminalIssuer.value || terminalIssuer.value.trim() === '') {
                            alert('TERMINAL ISSUER REQUIRED! Please select a terminal issuer for Credit Card payment.');
                            if (terminalIssuer) terminalIssuer.focus();
                            return;
                        }
                        if (!terminalId || !terminalId.value || terminalId.value.trim() === '') {
                            alert('TERMINAL ID REQUIRED! Please select a terminal ID for Credit Card payment.');
                            if (terminalId) terminalId.focus();
                            return;
                        }
                        if (!bank || !bank.value || bank.value.trim() === '') {
                            alert('BANK REQUIRED! Please select a bank for Credit Card payment.');
                            if (bank) bank.focus();
                            return;
                        }
                        if (!terms || !terms.value || terms.value.trim() === '') {
                            alert('TERMS REQUIRED! Please select payment terms for Credit Card payment.');
                            if (terms) terms.focus();
                            return;
                        }
                        if (!mid || !mid.value || mid.value.trim() === '') {
                            alert('MID REQUIRED! Please enter MID for Credit Card payment.');
                            if (mid) mid.focus();
                            return;
                        }
                        if (!cardNo || !cardNo.value || cardNo.value.trim() === '') {
                            alert('CARD NO REQUIRED! Please enter card number for Credit Card payment.');
                            if (cardNo) cardNo.focus();
                            return;
                        }
                        if (!approvalCode || !approvalCode.value || approvalCode.value.trim() === '') {
                            alert('APPROVAL CODE REQUIRED! Please enter approval code for Credit Card payment.');
                            if (approvalCode) approvalCode.focus();
                            return;
                        }
                        if (!batch || !batch.value || batch.value.trim() === '') {
                            alert('BATCH REQUIRED! Please enter batch number for Credit Card payment.');
                            if (batch) batch.focus();
                            return;
                        }
                        if (!amount || !amount.value || amount.value.trim() === '') {
                            alert('AMOUNT REQUIRED! Please enter amount for Credit Card payment.');
                            if (amount) amount.focus();
                            return;
                        }
                    }

                    // Validate Debit Card fields if Debit Card is selected
                    if (debitCardSection && debitCardSection.style.display === 'block') {
                        const terminalIssuer = document.getElementById('dcTerminalIssuer');
                        const terminalId = document.getElementById('dcTerminalId');
                        const bank = document.getElementById('debitCardBankDropdown');
                        const terms = document.getElementById('debitCardTermsDropdown');
                        const mid = debitCardSection.querySelector('input[type="text"]'); // MID input
                        const cardNo = debitCardSection.querySelectorAll('input[type="text"]')[1]; // Card No input
                        const approvalCode = debitCardSection.querySelectorAll('input[type="text"]')[2]; // Approval Code
                        const batch = debitCardSection.querySelectorAll('input[type="text"]')[3]; // Batch
                        const amount = document.getElementById('debitCardAmount');

                        if (!terminalIssuer || !terminalIssuer.value || terminalIssuer.value.trim() === '') {
                            alert('TERMINAL ISSUER REQUIRED! Please select a terminal issuer for Debit Card payment.');
                            if (terminalIssuer) terminalIssuer.focus();
                            return;
                        }
                        if (!terminalId || !terminalId.value || terminalId.value.trim() === '') {
                            alert('TERMINAL ID REQUIRED! Please select a terminal ID for Debit Card payment.');
                            if (terminalId) terminalId.focus();
                            return;
                        }
                        if (!bank || !bank.value || bank.value.trim() === '') {
                            alert('BANK REQUIRED! Please select a bank for Debit Card payment.');
                            if (bank) bank.focus();
                            return;
                        }
                        if (!terms || !terms.value || terms.value.trim() === '') {
                            alert('TERMS REQUIRED! Please select payment terms for Debit Card payment.');
                            if (terms) terms.focus();
                            return;
                        }
                        if (!mid || !mid.value || mid.value.trim() === '') {
                            alert('MID REQUIRED! Please enter MID for Debit Card payment.');
                            if (mid) mid.focus();
                            return;
                        }
                        if (!cardNo || !cardNo.value || cardNo.value.trim() === '') {
                            alert('CARD NO REQUIRED! Please enter card number for Debit Card payment.');
                            if (cardNo) cardNo.focus();
                            return;
                        }
                        if (!approvalCode || !approvalCode.value || approvalCode.value.trim() === '') {
                            alert('APPROVAL CODE REQUIRED! Please enter approval code for Debit Card payment.');
                            if (approvalCode) approvalCode.focus();
                            return;
                        }
                        if (!batch || !batch.value || batch.value.trim() === '') {
                            alert('BATCH REQUIRED! Please enter batch number for Debit Card payment.');
                            if (batch) batch.focus();
                            return;
                        }
                        if (!amount || !amount.value || amount.value.trim() === '') {
                            alert('AMOUNT REQUIRED! Please enter amount for Debit Card payment.');
                            if (amount) amount.focus();
                            return;
                        }
                    }
                }
                if ((qrPhSection && qrPhSection.style.display === 'block') || (starpayQrSection && starpayQrSection.style.display === 'block')) {
                    if (qrDropdown && qrDropdown.value === '') {
                        alert('Please choose a QR option in order to proceed!');
                        qrDropdown.focus();
                        return;
                    }

                    // Validate QR PH fields if QR PH is selected
                    if (qrPhSection && qrPhSection.style.display === 'block') {
                        const bank = qrPhSection.querySelector('.hc-form-group:nth-child(2) select');
                        const customerName = qrPhSection.querySelector('.hc-form-group:nth-child(3) input');
                        const referenceNo = qrPhSection.querySelector('.hc-form-group:nth-child(4) input');
                        const amount = qrPhSection.querySelector('.hc-form-group:nth-child(5) input');

                        if (!bank || !bank.value || bank.value.trim() === '') {
                            alert('BANK REQUIRED! Please select a bank for QR PH payment.');
                            if (bank) bank.focus();
                            return;
                        }
                        if (!customerName || !customerName.value || customerName.value.trim() === '') {
                            alert('CUSTOMER\'S NAME REQUIRED! Please enter customer\'s name for QR PH payment.');
                            if (customerName) customerName.focus();
                            return;
                        }
                        if (!referenceNo || !referenceNo.value || referenceNo.value.trim() === '') {
                            alert('REFERENCE NO REQUIRED! Please enter reference number for QR PH payment.');
                            if (referenceNo) referenceNo.focus();
                            return;
                        }
                        if (!amount || !amount.value || amount.value.trim() === '') {
                            alert('AMOUNT REQUIRED! Please enter amount for QR PH payment.');
                            if (amount) amount.focus();
                            return;
                        }
                    }

                    // Validate Starpay QR fields if Starpay QR is selected
                    if (starpayQrSection && starpayQrSection.style.display === 'block') {
                        const bank = starpayQrSection.querySelector('.hc-form-group:nth-child(2) select');
                        const customerName = starpayQrSection.querySelector('.hc-form-group:nth-child(3) input');
                        const referenceNo = starpayQrSection.querySelector('.hc-form-group:nth-child(4) input');
                        const amount = starpayQrSection.querySelector('.hc-form-group:nth-child(5) input');

                        if (!bank || !bank.value || bank.value.trim() === '') {
                            alert('BANK REQUIRED! Please select a bank for Starpay QR payment.');
                            if (bank) bank.focus();
                            return;
                        }
                        if (!customerName || !customerName.value || customerName.value.trim() === '') {
                            alert('CUSTOMER\'S NAME REQUIRED! Please enter customer\'s name for Starpay QR payment.');
                            if (customerName) customerName.focus();
                            return;
                        }
                        if (!referenceNo || !referenceNo.value || referenceNo.value.trim() === '') {
                            alert('REFERENCE NO REQUIRED! Please enter reference number for Starpay QR payment.');
                            if (referenceNo) referenceNo.focus();
                            return;
                        }
                        if (!amount || !amount.value || amount.value.trim() === '') {
                            alert('AMOUNT REQUIRED! Please enter amount for Starpay QR payment.');
                            if (amount) amount.focus();
                            return;
                        }
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

                // Validate Unit selection for all active payment sections
                const allActiveSections = [
                    { el: homeCreditSection, name: 'Home Credit' },
                    { el: creditCardSection, name: 'Credit Card' },
                    { el: debitCardSection, name: 'Debit Card' },
                    { el: qrPhSection, name: 'QR PH' },
                    { el: starpayQrSection, name: 'Starpay QR' },
                    { el: ewalletSection, name: 'E-Wallet' },
                    { el: onlineBankingSection, name: 'Online Banking' },
                    { el: cashSection, name: 'Cash' }
                ];

                for (const sec of allActiveSections) {
                    if (sec.el && sec.el.style.display === 'block') {
                        const unitRow = sec.el.querySelector('.unit-selector-row');
                        if (unitRow && unitRow.style.display !== 'none') {
                            const checkedUnits = unitRow.querySelectorAll('input[type="checkbox"][name="Unit"]:checked');
                            if (checkedUnits.length === 0) {
                                alert('UNIT REQUIRED! Please select unit.');
                                return;
                            }
                        }
                    }
                }

                const data = {};
                let sectionName = '';
                let isValid = false;
                let hasValues = false; // Flag to track if any input has a value

                function collectData(sectionClass, type) {
                    const section = document.querySelector(sectionClass);
                    // Check if section is visible (style.display is set to 'block' by the toggle logic)
                    if (section && section.style.display === 'block') {
                        // Determine the actual display name for this section
                        let displayType = type;
                        if (type === 'Home Credit' && paymentPartnersDropdown && paymentPartnersDropdown.value !== '') {
                            displayType = paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text;
                        }

                        if (sectionName) {
                            sectionName += ' + ' + displayType;
                        } else {
                            sectionName = displayType;
                        }
                        data.payment_type = sectionName;
                        isValid = true;

                        // Select all inputs and selects
                        const inputs = section.querySelectorAll('input, select');

                        inputs.forEach(input => {
                            // Skip hidden inputs
                            if (input.type === 'hidden') return;

                            let key = input.id;

                            // Map technical IDs to standard report keys
                            if (key === 'ccTerminalIssuer' || key === 'dcTerminalIssuer') key = 'Terminal Issuer';
                            else if (key === 'ccTerminalId' || key === 'dcTerminalId') key = 'Terminal ID';
                            else if (key === 'creditCardBankDropdown' || key === 'debitCardBankDropdown') key = 'Bank';
                            else if (key === 'creditCardTermsDropdown' || key === 'debitCardTermsDropdown') key = 'Terms';

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
                    { class: '.home-credit-section', name: 'Home Credit' },
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
                    } else if (sec.name === 'Home Credit' && paymentPartnersDropdown && paymentPartnersDropdown.value !== '') {
                        displayName = paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text;
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

                        // Global Payment Validation: Sum of ALL entered payments must equal Target Amount Due
                        const globalTotalInputCheck = document.getElementById('globalTotalInput');
                        const overallTotalPayment = parseFloat(globalTotalInputCheck ? (globalTotalInputCheck.value || '0').replace(/[^0-9.-]/g, '') : '0') || 0;

                        const context = (typeof getCartAndPaymentContext === 'function') ? getCartAndPaymentContext() : null;
                        const targetAmountDue = context ? context.activeSelectedDue : _originalTotalAmountDue;
                        const overallDifference = targetAmountDue - overallTotalPayment;

                        if (targetAmountDue > 0 && Math.abs(overallDifference) > 0.01) {
                            const bkBanner = document.getElementById('paymentBreakdownBanner');
                            if (bkBanner) bkBanner.style.display = 'none';

                            const neededDisp = targetAmountDue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const enteredDisp = overallTotalPayment.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const isExceeded = overallDifference < -0.01;
                            const diffLabel = isExceeded ? 'Exceeded Amount:' : 'Remaining Balance:';
                            const diffAmount = Math.abs(overallDifference);
                            const diffDisp = diffAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                            // Build detailed unit summary with itemized vouchers & tokens
                            let unitRowsHtml = '';
                            const displayedItems = context ? context.items.filter(item => item.isSelected) : [];
                            if (displayedItems.length === 0) {
                                unitRowsHtml = '<div style="color: #ef4444; font-style: italic; font-size: 12px; padding: 4px;">No units selected for payment.</div>';
                            } else {
                                displayedItems.forEach(item => {
                                    const hasVoucher = (item.hasVoucher === 1 && item.voucherAmount > 0);
                                    const hasToken = (item.hasToken === 1 && item.tokenAmount > 0);

                                    unitRowsHtml += `
                                        <div style="margin-top: 4px; padding: 4px 6px; background-color: #fef2f2; border-radius: 4px; border: 1px solid #fecaca;">
                                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: #7f1d1d; font-weight: 600;">
                                                <span style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">• ${item.labelText}${item.promoLabel}</span>
                                                <span>₱${item.rowTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                            </div>
                                            ${hasVoucher ? `
                                            <div style="display: flex; justify-content: space-between; padding-left: 14px; font-size: 12px; color: #16a34a; margin-top: 2px;">
                                                <span style="font-weight: 600;">↳ Less Voucher:</span>
                                                <span style="font-weight: 600;">-₱${item.voucherAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                            </div>` : ''}
                                            ${hasToken ? `
                                            <div style="display: flex; justify-content: space-between; padding-left: 14px; font-size: 12px; color: #d97706; margin-top: 2px;">
                                                <span style="font-weight: 600;">↳ Less Token:</span>
                                                <span style="font-weight: 600;">-₱${item.tokenAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                            </div>` : ''}
                                            ${(hasVoucher || hasToken) ? `
                                            <div style="display: flex; justify-content: space-between; padding-left: 14px; font-size: 12px; color: #991b1b; margin-top: 3px; border-top: 1px dashed #fca5a5; padding-top: 2px;">
                                                <span style="font-weight: 600;">Item Net Total:</span>
                                                <span style="font-weight: 700; color: #7f1d1d;">₱${item.itemNetDue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                            </div>` : ''}
                                        </div>
                                    `;
                                });
                            }

                            if (unitRowsHtml) {
                                unitRowsHtml = `
                                        <div style="margin-bottom: 6px;">
                                            <div style="font-weight: 600; color: #991b1b; font-size: 13px;">Unit(s) To Pay:</div>
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
                                        if (input.id && input.id.toLowerCase().includes('amount') && input.id !== 'totalLoanAmount') { isAmount = true; labelText = 'Amount'; }

                                        // Specific DP input IDs
                                        if (input.id === 'cash_down_payment_amount') { isAmount = true; labelText = 'Cash (DP)'; }
                                        if (input.id === 'gcash_down_payment_amount') { isAmount = true; labelText = 'G-Cash (DP)'; }
                                        if (input.id === 'maya_down_payment_amount') { isAmount = true; labelText = 'Maya (DP)'; }

                                        const formGroup = input.closest('.hc-form-group');
                                        if (formGroup) {
                                            const label = formGroup.querySelector('label');
                                            if (label && (label.innerText.includes('Amount') || label.innerText.includes('Loan Balance')) && !label.innerText.includes('Total Loan Amount')) {
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
                            } else if (sectionName === 'Home Credit') {
                                // Get the actual selected payment partner name
                                const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
                                if (paymentPartnersDropdown && paymentPartnersDropdown.value) {
                                    const selectedPartnerText = paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text;
                                    buttonText = `Payment: ${selectedPartnerText}`;
                                } else {
                                    buttonText = `Payment: Home Credit`;
                                }
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
        // Global Cart and Payment Context Helper
        function getCartAndPaymentContext() {
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

            let selectedUnitLabels = [];
            let hasActiveUnitSelector = false;

            sections.forEach(s => {
                const secEl = document.querySelector(s.class);
                if (secEl && secEl.style.display === 'block') {
                    const unitRow = secEl.querySelector('.unit-selector-row');
                    if (unitRow && unitRow.style.display !== 'none') {
                        const allUnitCbs = unitRow.querySelectorAll('input[type="checkbox"][name="Unit"]');
                        if (allUnitCbs.length > 0) {
                            hasActiveUnitSelector = true;
                            const checkedCbs = unitRow.querySelectorAll('input[type="checkbox"][name="Unit"]:checked');
                            checkedCbs.forEach(cb => {
                                if (!selectedUnitLabels.includes(cb.value)) {
                                    selectedUnitLabels.push(cb.value);
                                }
                            });
                        }
                    }
                }
            });

            const unitRows = document.querySelectorAll('#itemsTableBody tr:not(#no-sales-row):not(#no-items-row)');
            const items = [];
            let overallDue = 0;
            let activeSelectedDue = 0;
            let activeSelectedVoucher = 0;
            let activeSelectedToken = 0;

            unitRows.forEach(row => {
                const tds = row.querySelectorAll('td');
                if (tds.length >= 4) {
                    const descText = tds[0].textContent.trim();
                    const serialText = tds[1] ? tds[1].textContent.trim() : '';
                    const labelText = serialText ? (descText + ' (' + serialText + ')') : descText;
                    const priceInput = row.querySelector('.price-input-table');
                    const qtyInput = row.querySelector('.qty-input');
                    const pVal = parseFloat(priceInput ? priceInput.value.replace(/,/g, '') : 0) || 0;
                    const qVal = parseInt(qtyInput ? qtyInput.value : 1) || 1;
                    const rowTotal = pVal * qVal;

                    const hasVoucher = parseInt(row.getAttribute('data-has-voucher')) || 0;
                    const voucherAmount = parseFloat(row.getAttribute('data-voucher-amount')) || 0;
                    const itemVoucher = (hasVoucher === 1) ? voucherAmount : 0;

                    const hasToken = parseInt(row.getAttribute('data-has-token')) || 0;
                    const tokenAmount = parseFloat(row.getAttribute('data-token-amount')) || 0;
                    const itemToken = (hasToken === 1) ? tokenAmount : 0;

                    const basePrice = parseFloat(row.getAttribute('data-base-price')) || 0;

                    // Check promo discount or FREE
                    let promoLabel = '';
                    const priceTdText = tds[3].textContent.trim().toUpperCase();
                    if (priceTdText === 'FREE' || pVal === 0) {
                        promoLabel = ' <span style="color: #16a34a; font-weight: 600;">(FREE)</span>';
                    } else if (basePrice > 0 && pVal < basePrice) {
                        const discountPercent = Math.round(((basePrice - pVal) / basePrice) * 100);
                        promoLabel = ` <span style="color: #d97706; font-weight: 600;">(${discountPercent}% DISCOUNT)</span>`;
                    }

                    const isSelected = (!hasActiveUnitSelector) ? true : selectedUnitLabels.includes(labelText);
                    const itemNetDue = Math.max(0, rowTotal - itemVoucher - itemToken);

                    items.push({
                        descText,
                        serialText,
                        labelText,
                        pVal,
                        qVal,
                        rowTotal,
                        hasVoucher,
                        voucherAmount: itemVoucher,
                        hasToken,
                        tokenAmount: itemToken,
                        basePrice,
                        promoLabel,
                        isSelected,
                        itemNetDue
                    });

                    overallDue += itemNetDue;
                    if (isSelected) {
                        activeSelectedDue += itemNetDue;
                        activeSelectedVoucher += itemVoucher;
                        activeSelectedToken += itemToken;
                    }
                }
            });

            // Discount from main form discount field
            const discountField = document.getElementById('discountField');
            const discountAmount = discountField ? (parseFloat(discountField.value.replace(/,/g, '')) || 0) : 0;

            let finalTargetDue = hasActiveUnitSelector ? activeSelectedDue : overallDue;
            if (discountAmount > 0) {
                finalTargetDue = Math.max(0, finalTargetDue - discountAmount);
            }

            return {
                items,
                sections,
                hasActiveUnitSelector,
                selectedUnitLabels,
                activeSelectedDue: finalTargetDue,
                overallDue,
                activeSelectedVoucher,
                activeSelectedToken,
                discountAmount
            };
        }
        window.getCartAndPaymentContext = getCartAndPaymentContext;

        document.addEventListener('DOMContentLoaded', function () {
            // Function to update global total based on input across all sections
            function updateSectionTotal() {
                let globalTotal = 0;
                const context = getCartAndPaymentContext();

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
                            // Exclude "totalLoanAmount" ID from payment calculation - it's for reference only
                            if (input.id && input.id.toLowerCase().includes('amount') && input.id !== 'totalLoanAmount') isAmount = true;
                            const formGroup = input.closest('.hc-form-group');
                            if (formGroup) {
                                const label = formGroup.querySelector('label');
                                // Exclude "Total Loan Amount" from payment calculation - it's for reference only
                                if (label && label.innerText.includes('Amount') && !label.innerText.includes('Total Loan Amount')) isAmount = true;
                                if (label && label.innerText.includes('Loan Balance')) isAmount = true;
                            }
                            const enterAmountRow = input.closest('.enter-amount-row');
                            if (enterAmountRow) {
                                const label = enterAmountRow.querySelector('label');
                                if (label && label.innerText.includes('Amount')) isAmount = true;
                            }

                            if (isAmount) {
                                let val = parseFloat(input.value.replace(/[^0-9.-]+/g, '')) || 0;
                                globalTotal += val;
                            }
                        });
                    }
                });

                const globalTotalInput = document.getElementById('globalTotalInput');
                if (globalTotalInput) {
                    globalTotalInput.value = globalTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                // Update TOTAL AMOUNT DUE: target amount due for selected units minus what has been paid so far
                const globalTotalDueInput = document.getElementById('globalTotalDueInput');
                if (globalTotalDueInput) {
                    let remaining = context.activeSelectedDue - globalTotal;
                    if (remaining < 0) remaining = 0;
                    globalTotalDueInput.value = '₱' + remaining.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                // Render dynamic breakdown box
                renderPaymentBreakdown(globalTotal, context);
            }
            window.updateSectionTotal = updateSectionTotal;

            function renderPaymentBreakdown(globalTotal, context) {
                const banner = document.getElementById('paymentBreakdownBanner');
                if (!banner) return;

                if (!context) {
                    context = getCartAndPaymentContext();
                }

                const targetDue = context.activeSelectedDue;

                if (targetDue <= 0 && globalTotal <= 0 && context.items.length === 0) {
                    banner.style.display = 'none';
                    return;
                }

                const neededDisp = targetDue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const enteredDisp = globalTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const overallDifference = targetDue - globalTotal;
                const isExceeded = overallDifference < -0.01;
                const isPerfectMatch = (Math.abs(overallDifference) <= 0.01 && globalTotal > 0);
                const diffLabel = isExceeded ? 'Exceeded Amount:' : 'Remaining Balance:';
                const diffAmount = Math.abs(overallDifference);
                const diffDisp = diffAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                // Build itemized unit summary with per-unit voucher and token
                let unitRowsHtml = '';
                const displayedItems = context.items.filter(item => item.isSelected);

                if (displayedItems.length === 0) {
                    unitRowsHtml = '<div style="color: #ef4444; font-style: italic; font-size: 12px; padding: 4px;">No units selected for payment.</div>';
                } else {
                    displayedItems.forEach((item, idx) => {
                        const hasVoucher = (item.hasVoucher === 1 && item.voucherAmount > 0);
                        const hasToken = (item.hasToken === 1 && item.tokenAmount > 0);
                        const isLastItem = (idx === displayedItems.length - 1);
                        const hasDiscount = (isLastItem && context.discountAmount > 0);

                        unitRowsHtml += `
                            <div style="margin-top: 4px; padding: 4px 6px; background-color: #f8fafc; border-radius: 4px; border: 1px solid #e2e8f0;">
                                <div style="display: flex; justify-content: space-between; font-size: 13px; color: #0f172a; font-weight: 600;">
                                    <span style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">• ${item.labelText}${item.promoLabel}</span>
                                    <span>₱${item.rowTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                </div>
                                ${hasVoucher ? `
                                <div style="display: flex; justify-content: space-between; padding-left: 14px; font-size: 12px; color: #16a34a; margin-top: 2px;">
                                    <span style="font-weight: 600;">↳ Less Voucher:</span>
                                    <span style="font-weight: 600;">-₱${item.voucherAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                </div>` : ''}
                                ${hasToken ? `
                                <div style="display: flex; justify-content: space-between; padding-left: 14px; font-size: 12px; color: #d97706; margin-top: 2px;">
                                    <span style="font-weight: 600;">↳ Less Token:</span>
                                    <span style="font-weight: 600;">-₱${item.tokenAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                </div>` : ''}
                                ${hasDiscount ? `
                                <div style="display: flex; justify-content: space-between; padding-left: 14px; font-size: 12px; color: #dc2626; margin-top: 2px;">
                                    <span style="font-weight: 600;">↳ Less Discount:</span>
                                    <span style="font-weight: 600;">-₱${context.discountAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                </div>` : ''}
                                ${(hasVoucher || hasToken || hasDiscount) ? `
                                <div style="display: flex; justify-content: space-between; padding-left: 14px; font-size: 12px; color: #475569; margin-top: 3px; border-top: 1px dashed #cbd5e1; padding-top: 2px;">
                                    <span style="font-weight: 600;">Item Net Total:</span>
                                    <span style="font-weight: 700; color: #0f172a;">₱${(item.itemNetDue - (hasDiscount ? context.discountAmount : 0)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                </div>` : ''}
                            </div>
                        `;
                    });
                }

                if (unitRowsHtml) {
                    unitRowsHtml = `
                            <div style="margin-bottom: 6px;">
                                <div style="font-weight: 600; color: #000000ff; font-size: 13px;">Unit(s) To Pay:</div>
                                ${unitRowsHtml}
                            </div>
                        `;
                }



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

                let breakdownInner = '';
                context.sections.forEach(sec => {
                    const secEl = document.querySelector(sec.class);
                    if (secEl && secEl.style.display === 'block') {
                        let secTotal = 0;
                        let secBreakdownRows = '';
                        const inputs = secEl.querySelectorAll('input[type="text"], input[type="number"]');

                        inputs.forEach(input => {
                            let isAmount = false;
                            let labelText = '';

                            if (input.classList.contains('amount-input')) isAmount = true;
                            if (input.id && input.id.toLowerCase().includes('amount') && input.id !== 'totalLoanAmount') { isAmount = true; labelText = 'Amount'; }

                            // Specific DP input IDs
                            if (input.id === 'cash_down_payment_amount') { isAmount = true; labelText = 'Cash (DP)'; }
                            if (input.id === 'gcash_down_payment_amount') { isAmount = true; labelText = 'G-Cash (DP)'; }
                            if (input.id === 'maya_down_payment_amount') { isAmount = true; labelText = 'Maya (DP)'; }

                            const formGroup = input.closest('.hc-form-group');
                            if (formGroup) {
                                const label = formGroup.querySelector('label');
                                if (label && (label.innerText.includes('Amount') || label.innerText.includes('Loan Balance')) && !label.innerText.includes('Total Loan Amount')) {
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


            // Add observer or global listener for section toggles to immediately update sum and sync unit selectors
            document.body.addEventListener('change', function (e) {
                if (e.target.name === 'payment_method' || e.target.id === 'paymentPartnersDropdown' || e.target.id === 'cardPaymentDropdown' || e.target.id === 'qrDropdown') {
                    setTimeout(function () {
                        if (typeof window.syncUnitSelectorsAcrossSections === 'function') {
                            window.syncUnitSelectorsAcrossSections();
                        }
                        updateSectionTotal();
                    }, 50);
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