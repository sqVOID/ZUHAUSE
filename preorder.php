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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="Icon/ZUHAUSE-LOGO.png?v=1">
    <link rel="shortcut icon" type="image/png" href="Icon/ZUHAUSE-LOGO.png?v=1">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Pre-Order</title>
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
            margin-top: 55px;
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

        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
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

            /* Fix the main flex container - stack Family Code section and Quantity/Price section */
            .item-selection-container>div[style*="display: flex"][style*="gap: 30px"] {
                display: flex !important;
                flex-direction: column !important;
                gap: 20px !important;
            }

            /* Make left and right columns full width */
            .item-selection-container>div>div[style*="flex: 1"] {
                flex: none !important;
                width: 100% !important;
            }

            /* Keep Quantity, Price, and buttons in a row but allow wrapping */
            .item-selection-container div[style*="display: flex"][style*="gap: 10px"] {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 10px !important;
            }

            /* Make form inputs within the row responsive */
            .item-selection-container .form-group[style*="flex: 1"] {
                flex: 1 1 200px !important;
                min-width: 200px !important;
            }

            /* Ensure all inputs are readable */
            .item-selection-container input,
            .item-selection-container select {
                font-size: 15px !important;
                padding: 12px !important;
                min-height: 44px !important;
            }

            /* Make buttons wrap to new line if needed */
            .item-selection-container .btn-search-item,
            .item-selection-container .btn-add-item {
                flex: 1 1 auto !important;
                min-width: 150px !important;
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

            .form-row,
            .form-row.three-columns {
                grid-template-columns: 1fr;
            }

            /* Fix the main flex container - ensure stacking */
            .item-selection-container>div[style*="display: flex"][style*="gap: 30px"] {
                display: flex !important;
                flex-direction: column !important;
                gap: 15px !important;
            }

            /* Make columns full width */
            .item-selection-container>div>div[style*="flex: 1"] {
                flex: none !important;
                width: 100% !important;
            }

            /* Stack Quantity, Price, and buttons vertically on smaller tablets */
            .item-selection-container div[style*="display: flex"][style*="gap: 10px"] {
                display: flex !important;
                flex-direction: column !important;
                gap: 12px !important;
            }

            /* Make form groups full width */
            .item-selection-container .form-group[style*="flex: 1"] {
                flex: none !important;
                width: 100% !important;
            }

            /* Ensure inputs are large enough */
            .item-selection-container input,
            .item-selection-container select {
                font-size: 16px !important;
                padding: 12px !important;
                min-height: 46px !important;
                width: 100% !important;
            }

            /* Make buttons full width */
            .item-selection-container .btn-search-item,
            .item-selection-container .btn-add-item {
                width: 100% !important;
            }
        }

        /* Small Tablet (max-width: 960px) - Fix Price & Quantity Display */
        @media (max-width: 960px) {

            /* Fix the main flex container with two columns */
            .item-selection-container>div[style*="display: flex"][style*="gap: 30px"] {
                display: flex !important;
                flex-direction: column !important;
                gap: 15px !important;
            }

            /* Fix left and right column containers */
            .item-selection-container>div>div[style*="flex: 1"] {
                flex: none !important;
                width: 100% !important;
            }

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

            /* Fix family code input */
            input#family_code {
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
                table-layout: fixed;
                /* Fixed layout for consistent column widths */
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

            /* Set minimum column widths to ensure scrollbar appears when needed */
            .items-table th:nth-child(1),
            .items-table td:nth-child(1) {
                min-width: 200px;
                /* Family Code */
            }

            .items-table th:nth-child(2),
            .items-table td:nth-child(2) {
                min-width: 100px;
                /* Quantity */
            }

            .items-table th:nth-child(3),
            .items-table td:nth-child(3) {
                min-width: 100px;
                /* Price */
            }

            .items-table th:nth-child(4),
            .items-table td:nth-child(4) {
                min-width: 80px;
                /* Action */
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

        /* Table Styles (Matched to requested design with full grid lines) */

        .preorder-badge {
            background: #ff9800;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
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
            justify-content: center;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            border: 1px solid #888;
            width: 90%;
            max-width: 1000px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: fadeIn 0.3s;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
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
            max-width: 1000px;
            width: 90%;
            align-self: flex-start;
            margin-top: 5%;
            margin-bottom: 40px;
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

        /* Payment Sections */
        .home-credit-section,
        .credit-card-section,
        .debit-card-section,
        .qr-ph-section,
        .starpay-qr-section,
        .ewallet-section,
        .online-banking-section,
        .cash-section {
            background: white;
            padding-top: 0px;
            display: none;
        }

        .home-credit-section {
            display: block;
        }

        .home-credit-section h3,
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

        .hc-grid-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .hc-form-group {
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
            flex-shrink: 0;
        }

        .hc-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
            width: 100%;
        }

        .hc-input:focus {
            outline: none;
            border-color: #2e7d32;
        }

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
        }

        .down-payment-box {
            padding: 15px 20px;
            border: 1px solid #bfbfbf;
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

        .btn-save-modal {
            padding: 10px 40px;
            border: none;
            background: #1b5e20;
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

        /* Search Results Table Styles */
        .search-results-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #ccc;
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
        }

        .search-results-table td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
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
            <h2>Pre-Order</h2>
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
            <form id="preOrderForm" method="POST" class="form-split-layout">
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
                                placeholder="Enter First Name"
                                oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name </label>
                            <input type="text" id="last_name" name="last_name" placeholder="Enter Last Name"
                                oninput="this.value = this.value.toUpperCase()">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="address">Address </label>
                            <input type="text" id="address" name="address" placeholder="Enter Address">
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

                                        // If there's a brand (promoter), display as "Brand - Full Name"
                                        if (!empty($brand)) {
                                            echo '<option value="' . $displayText . '">' . $brand . ' - ' . $displayText . '</option>';
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
                                placeholder="Enter Contact No."
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
                    <div class="form-group full-height">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" style="height: 100%;" placeholder="Enter remarks..."
                            oninput="this.value = this.value.toUpperCase()"></textarea>
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
                        <label>Family Code</label>
                        <input type="text" id="family_code" placeholder=""
                            oninput="this.value = this.value.toUpperCase()">
                    </div>
                </div>

                <!-- Right Column -->
                <div style="flex: 1; display: flex; flex-direction: column; gap: 15px;">
                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <div class="form-group" style="flex: 1;">
                            <label>Quantity</label>
                            <input type="number" id="qty" value="0" min="0" style="text-align: center;">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Price</label>
                            <input type="text" id="price" placeholder="0"
                                style="background-color: #ffffff; color: #333;">
                        </div>
                        <button type="button" class="btn-search-item" style="margin-bottom: 1px;"
                            onclick="performSearch()">Search</button>
                        <button type="button" class="btn-add-item" style="margin-bottom: 1px;"
                            onclick="addItem()">Add</button>
                    </div>
                </div>
            </div>

            <div class="items-table-wrapper">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Family Code</th>
                            <th style="width: 20%;">Quantity</th>
                            <th style="width: 20%;">Price</th>
                            <th style="width: 20%; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <tr id="no-sales-row">
                            <td colspan="4" style="text-align:center; padding: 20px;">No Pre-Order Entry yet</td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
                                <label>Commission:</label>
                                <input type="text" id="commissionField" readonly>
                            </div>
                        </div>
                        <div class="footer-right-group">
                            <button type="button" class="btn-payment" onclick="openPaymentModal()">PAYMENT</button>
                            <button type="button" class="btn-save" onclick="savePreOrder()">SAVE</button>
                            <button type="button" class="btn-clear-main" onclick="clearMainForm()">CLEAR</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Item Modal -->
        <div id="searchItemModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    Search Items
                </div>
                <div class="modal-body">
                    <table class="search-results-table">
                        <thead>
                            <tr>
                                <th style="width: 50%;">Family Code</th>
                                <th style="width: 50%; text-align: center;">Action</th>
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
                    <button type="button" class="btn-save-modal" onclick="savePaymentData()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let itemsData = [];
        let currentSearchResults = [];

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

            // Fetch next invoice number from booklet system
            const branchCode = '<?php echo $branch_code; ?>'; // Get branch code from PHP

            fetch('get_next_invoice_number.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_invoice_number&branch_code=' + branchCode + '&page_type=preorder'
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.invoice_number) {
                        document.getElementById('invoice_no').value = result.invoice_number;
                    } else {
                        // Display fallback or message
                        document.getElementById('invoice_no').value = result.invoice_number || 'System Generated';
                        if (result.message) {
                            console.log('Invoice number info:', result.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching invoice number:', error);
                    document.getElementById('invoice_no').value = 'System Generated';
                });

            const formattedDate = today.toLocaleDateString('en-US', {
                month: '2-digit',
                day: '2-digit',
                year: 'numeric'
            });
            document.getElementById('date').value = formattedDate;
        }

        function validateEmail(input) {
            const val = input.value.trim().toLowerCase();
            if (val === 'n/a' || val === 'na') {
                input.value = '';
                alert("Email cannot be 'N/A' or 'n/a'. Please leave it empty if not applicable.");
            }
        }

        function performSearch() {
            const searchTerm = document.getElementById('family_code').value.trim();

            if (searchTerm === '') {
                alert('Please input Family Code!');
                return;
            }

            // Fetch results from search_preorder.php
            fetch(`search_preorder.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.data.length > 0) {
                        // Always show modal with results
                        showSearchModal(data.data);
                    } else {
                        alert('No items found');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while searching.');
                });
        }

        function showSearchModal(data) {
            currentSearchResults = data;
            const modal = document.getElementById('searchItemModal');
            const resultsBody = document.getElementById('searchResultsBody');

            resultsBody.innerHTML = '';

            data.forEach((item, index) => {
                const row = `
                    <tr>
                        <td>${item.family_code}</td>
                        <td style="text-align: center;">
                            <button type="button" class="btn-select" onclick="selectItem(${index})">Select</button>
                        </td>
                    </tr>
                `;
                resultsBody.insertAdjacentHTML('beforeend', row);
            });

            modal.style.display = 'flex';
        }

        function closeSearchModal() {
            const modal = document.getElementById('searchItemModal');
            modal.style.display = 'none';
        }

        function selectItem(index, data = null) {
            const items = data || currentSearchResults;
            const item = items[index];
            if (!item) return;

            // Store prices
            selectedItemPrices = item.prices || {};
            if (typeof updateCardPaymentDropdowns === 'function') {
                updateCardPaymentDropdowns();
            }

            const familyCode = item.family_code;
            const price = item.price;

            // Store the auto price for later restoration
            const priceInput = document.getElementById('price');
            if (priceInput) {
                priceInput.setAttribute('data-auto-price', price);
            }

            // Check if item is serialized
            if (familyCode) {
                fetch(`check_serial_permission.php?family_code=${encodeURIComponent(familyCode)}`)
                    .then(response => response.json())
                    .then(data => {
                        // Proceed to populate fields regardless of serialization status
                        document.getElementById('family_code').value = familyCode;

                        // Check if price mode is custom or auto
                        const priceMode = priceInput.getAttribute('data-price-mode');
                        if (priceMode !== 'custom') {
                            // Auto mode or no mode - fill the price automatically with comma formatting
                            const formattedPrice = parseFloat(price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            priceInput.value = formattedPrice;
                        }
                        // If custom mode, keep the existing price value

                        // Close modal if it's open
                        closeSearchModal();
                    })
                    .catch(error => {
                        console.error('Error checking serial status:', error);
                        // Proceed anyway if check fails
                        document.getElementById('family_code').value = familyCode;

                        // Check if price mode is custom or auto
                        const priceMode = priceInput.getAttribute('data-price-mode');
                        if (priceMode !== 'custom') {
                            const formattedPrice = parseFloat(price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            priceInput.value = formattedPrice;
                        }

                        closeSearchModal();
                    });
            } else {
                // No family code, just populate
                document.getElementById('family_code').value = familyCode;

                // Check if price mode is custom or auto
                const priceMode = priceInput.getAttribute('data-price-mode');
                if (priceMode !== 'custom') {
                    const formattedPrice = parseFloat(price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    priceInput.value = formattedPrice;
                }

                closeSearchModal();
            }
        }
        function addItem() {
            const familyCode = document.getElementById('family_code').value.trim();
            const priceFieldValue = document.getElementById('price').value;
            const price = parseFloat(priceFieldValue.replace(/,/g, '')) || 0; // Remove commas before parsing
            const qty = parseInt(document.getElementById('qty').value);

            // Check if Assisted By is selected
            const assistedBy = document.getElementById('assisted_by').value;
            if (!assistedBy || assistedBy === '') {
                alert("Please select Assisted By before adding items.");
                document.getElementById('assisted_by').focus();
                return;
            }

            if (!familyCode || !price) {
                alert("Please select or enter a family code and price.");
                return;
            }

            if (!qty || qty <= 0) {
                alert("Please input quantity.");
                return;
            }

            const itemsTableBody = document.getElementById('itemsTableBody');

            // Remove "no items" row if it exists
            const noItemsRow = document.getElementById('no-sales-row');
            if (noItemsRow) {
                noItemsRow.remove();
            }

            // Format price with commas for display
            const formattedPrice = price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>${familyCode}</td>
                <td style="text-align: center;">${qty}<input type="hidden" class="qty-input" value="${qty}"></td>
                <td style="text-align: center;">${formattedPrice}<input type="hidden" class="price-input-table" value="${price}"></td>
                <td style="text-align: center;"><button type="button" class="btn-delete-item" onclick="removeRow(this)">X</button></td>
            `;
            newRow.setAttribute('data-family-code', familyCode);

            itemsTableBody.appendChild(newRow);

            // Clear inputs
            document.getElementById('family_code').value = '';
            document.getElementById('qty').value = '0';
            document.getElementById('price').value = '';

            // Price field reset - simple input now
            const priceInput = document.getElementById('price');
            if (priceInput) {
                priceInput.value = '';
                priceInput.placeholder = '0';
            }

            // Keep Payment Method dropdown value (don't reset it)
            // Payment Method dropdown removed


            // Update totals
            updateTotals();
        }

        function removeRow(button) {
            const row = button.closest('tr');
            row.remove();

            // Check if table is empty and add "no items" row
            const itemsTableBody = document.getElementById('itemsTableBody');
            if (itemsTableBody.children.length === 0) {
                itemsTableBody.innerHTML = '<tr id="no-sales-row"><td colspan="4" style="text-align:center; padding: 20px;">No Pre-Order Entry yet</td></tr>';
            }

            updateTotals();
        }

        function updateTotals() {
            const itemsTableBody = document.getElementById('itemsTableBody');
            const rows = itemsTableBody.querySelectorAll('tr');

            let totalQty = 0;
            let totalAmount = 0;

            rows.forEach(row => {
                const qtyInput = row.querySelector('.qty-input');
                const priceInput = row.querySelector('.price-input-table');

                if (qtyInput && priceInput) {
                    const qty = parseInt(qtyInput.value) || 0;
                    const price = parseFloat(priceInput.value) || 0;

                    totalQty += qty;
                    totalAmount += qty * price;
                }
            });

            const discount = parseFloat(document.getElementById('discountField').value) || 0;
            const finalAmount = totalAmount - discount;

            document.getElementById('totalQty').value = totalQty;
            document.getElementById('totalAmount').value = finalAmount.toFixed(2);
        }

        function clearMainForm() {
            if (!confirm('Are you sure you want to clear the entire form?')) return;

            // Reset form inputs
            document.getElementById('preOrderForm').reset();

            // Reset item search inputs
            document.getElementById('family_code').value = '';
            document.getElementById('qty').value = '0';
            document.getElementById('price').value = '';

            // Clear items table
            const itemsTableBody = document.getElementById('itemsTableBody');
            itemsTableBody.innerHTML = '<tr id="no-sales-row"><td colspan="4" style="text-align:center; padding: 20px;">No Pre-Order Entry yet</td></tr>';

            // Reset totals
            document.getElementById('totalQty').value = '';
            document.getElementById('totalAmount').value = '';
            document.getElementById('discountField').value = '0';
            document.getElementById('pointsField').value = '';
            document.getElementById('commissionField').value = '';

            // Clear payment data
            window.paymentData = null;

            // Reset payment button text and style
            const btnPayment = document.querySelector('.btn-payment');
            if (btnPayment) {
                btnPayment.innerText = 'PAYMENT';
                btnPayment.style.backgroundColor = ''; // Reset to default CSS
                btnPayment.style.color = ''; // Reset to default CSS
            }

            // Re-initialize form
            initializeForm();
        }

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

        function savePreOrder() {
            // Validate required field: Assisted By
            const assistedBy = document.getElementById('assisted_by').value;
            if (!assistedBy) {
                alert('Assisted By is required!');
                document.getElementById('assisted_by').focus();
                return;
            }

            // Check if payment has been processed
            if (!window.paymentData) {
                alert('Please process payment first before saving the pre-order.');
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
                    const familyCode = row.getAttribute('data-family-code') || '';

                    items.push({
                        family_code: familyCode,
                        quantity: parseInt(qtyInput.value) || 0,
                        price: parseFloat(priceInput.value) || 0
                    });
                }
            });

            // Validate that there are items
            if (items.length === 0) {
                alert('Please add at least one item to the pre-order!');
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
                total_qty: parseInt(document.getElementById('totalQty').value) || 0,
                discount: parseFormattedNumber(document.getElementById('discountField').value),
                total_amount: parseFormattedNumber(document.getElementById('totalAmount').value),
                payment_data: window.paymentData,
                items: items
            };

            if (!data.first_name) {
                alert('First Name is required!');
                document.getElementById('first_name').focus();
                return;
            }
            if (!data.last_name) {
                alert('Last Name is required!');
                document.getElementById('last_name').focus();
                return;
            }
            if (!data.address) {
                alert('Address is required!');
                document.getElementById('address').focus();
                return;
            }
            if (!data.contact_no) {
                alert('Contact No is required!');
                document.getElementById('contact_no').focus();
                return;
            }

            // Show loading state
            const saveBtn = document.querySelector('.btn-save');
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Saving...';
            saveBtn.disabled = true;

            // Send data to server
            fetch('save_preorder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

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
                        alert('Pre-order saved successfully!\nInvoice No: ' + result.invoice_no);

                        // Update invoice number in the form
                        document.getElementById('invoice_no').value = result.invoice_no;

                        // Optionally clear the form after successful save
                        if (confirm('Do you want to clear the form for a new entry?')) {
                            clearMainForm();
                        }
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    saveBtn.textContent = originalText;
                    saveBtn.disabled = false;
                    console.error('Error:', error);
                    console.error('Error:', error);
                    alert('An error occurred while saving the pre-order. Please try again.');
                });
        }

        // Format input function - blocks spaces and auto-formats with commas
        function formatInput(input) {
            // Save cursor position
            let cursorPosition = input.selectionStart;
            let oldValLength = input.value.length;

            // Remove non-numeric chars except dot (this blocks spaces automatically)
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

        // Event listeners
        document.addEventListener('DOMContentLoaded', function () {
            initializeForm();

            // Price field - simple input (no dropdown functionality)
            const priceField = document.getElementById('price');

            // Helper function to format number with commas
            function formatPriceWithCommas(value) {
                // Remove all non-digit and non-decimal characters
                let num = value.replace(/[^\d.]/g, '');

                // Ensure only one decimal point
                const parts = num.split('.');
                if (parts.length > 2) {
                    num = parts[0] + '.' + parts.slice(1).join('');
                }

                // Split into integer and decimal parts
                const [intPart, decPart] = num.split('.');

                // Add commas to integer part
                const formattedInt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

                // Return formatted value
                return decPart !== undefined ? formattedInt + '.' + decPart : formattedInt;
            }

            // Helper function to remove commas for calculation
            function parseFormattedPrice(value) {
                return parseFloat(value.replace(/,/g, '')) || 0;
            }

            if (priceField) {
                // Format price with commas as user types and prevent spacebar
                priceField.addEventListener('input', function (e) {
                    const cursorPos = this.selectionStart;
                    const oldValue = this.value;
                    const oldLength = oldValue.length;

                    // Format the value
                    this.value = formatPriceWithCommas(this.value);

                    // Adjust cursor position after formatting
                    const newLength = this.value.length;
                    const diff = newLength - oldLength;
                    this.setSelectionRange(cursorPos + diff, cursorPos + diff);
                });

                // Prevent spacebar input
                priceField.addEventListener('keydown', function (e) {
                    if (e.key === ' ' || e.keyCode === 32) {
                        e.preventDefault();
                        return false;
                    }
                });
            }

            // Payment Method Dropdown removed - no longer needed

            // Attach formatInput to all amount/balance input fields in payment modal
            const paymentInputs = document.querySelectorAll('.amount-input, #creditCardAmount, .hc-input');

            paymentInputs.forEach(input => {
                // Check if the input field is for amount or balance
                const label = input.closest('.hc-form-group')?.querySelector('label')?.textContent;
                const parentLabel = input.closest('.enter-amount-row')?.querySelector('label')?.textContent;

                // Apply formatInput to Amount and Balance fields
                if ((label && (label.includes('Amount') || label.includes('Balance'))) ||
                    (parentLabel && parentLabel.includes('Amount')) ||
                    input.id === 'creditCardAmount' ||
                    input.classList.contains('amount-input')) {
                    input.addEventListener('input', function () { formatInput(this); });

                    // Prevent spacebar input
                    input.addEventListener('keydown', function (e) {
                        if (e.key === ' ' || e.keyCode === 32) {
                            e.preventDefault();
                            return false;
                        }
                    });
                }
            });

            // Family code input event listener
            document.getElementById('family_code').addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    performSearch();
                }
            });

            // Payment method checkbox listeners (multi-select split payment)
            const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
            const cardPaymentDropdown = document.getElementById('cardPaymentDropdown');
            const qrDropdown = document.getElementById('qrDropdown');

            const homeCreditSection = document.querySelector('.home-credit-section');
            const creditCardSection = document.querySelector('.credit-card-section');
            const debitCardSection = document.querySelector('.debit-card-section');
            const qrPhSection = document.querySelector('.qr-ph-section');
            const starpayQrSection = document.querySelector('.starpay-qr-section');
            const ewalletSection = document.querySelector('.ewallet-section');
            const onlineBankingSection = document.querySelector('.online-banking-section');
            const cashSection = document.querySelector('.cash-section');

            // Payment Partners dropdown change
            if (paymentPartnersDropdown) {
                paymentPartnersDropdown.addEventListener('change', function () {
                    const chk = document.getElementById('chkPaymentPartners');
                    if (this.value !== '') {
                        if (chk) chk.checked = true;
                    }
                    if (chk && chk.checked) {
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

            // Card Payment dropdown change
            if (cardPaymentDropdown) {
                cardPaymentDropdown.addEventListener('change', function () {
                    if (this.disabled) return;
                    const chk = document.getElementById('chkCardPayment');
                    if (chk && chk.checked) {
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

            // QR dropdown change
            if (qrDropdown) {
                qrDropdown.addEventListener('change', function () {
                    if (this.disabled) return;
                    const chk = document.getElementById('chkQR');
                    if (chk && chk.checked) {
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

            // Each payment method checkbox independently shows/hides its section
            const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
            paymentRadios.forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    if (this.disabled) return;

                    if (this.value === 'payment_partners' || this.id === 'chkPaymentPartners') {
                        if (this.checked) {
                            if (paymentPartnersDropdown && paymentPartnersDropdown.value === '') {
                                paymentPartnersDropdown.value = 'partner2';
                            }
                            paymentPartnersDropdown && paymentPartnersDropdown.dispatchEvent(new Event('change'));
                        } else {
                            if (homeCreditSection) homeCreditSection.style.display = 'none';
                        }
                    } else if (this.value === 'card_payment' || this.id === 'chkCardPayment') {
                        if (this.checked) {
                            if (cardPaymentDropdown && cardPaymentDropdown.value === '') {
                                cardPaymentDropdown.value = 'credit_card';
                            }
                            cardPaymentDropdown && cardPaymentDropdown.dispatchEvent(new Event('change'));
                        } else {
                            if (creditCardSection) creditCardSection.style.display = 'none';
                            if (debitCardSection) debitCardSection.style.display = 'none';
                        }
                    } else if (this.value === 'qr' || this.id === 'chkQR') {
                        if (this.checked) {
                            if (qrDropdown && qrDropdown.value === '') {
                                qrDropdown.value = 'qr_ph';
                            }
                            qrDropdown && qrDropdown.dispatchEvent(new Event('change'));
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

        // Helper: find an input inside a section by its label text (skips unit-selector-row)
        function getFieldByLabel(section, labelText) {
            if (!section) return null;
            const groups = section.querySelectorAll('.hc-form-group');
            for (const group of groups) {
                if (group.classList.contains('unit-selector-row')) continue;
                const lbl = group.querySelector('label');
                if (lbl && lbl.textContent.trim().toLowerCase().includes(labelText.toLowerCase())) {
                    return group.querySelector('input, select');
                }
            }
            return null;
        }
        function getSelectByLabel(section, labelText) {
            if (!section) return null;
            const groups = section.querySelectorAll('.hc-form-group');
            for (const group of groups) {
                if (group.classList.contains('unit-selector-row')) continue;
                const lbl = group.querySelector('label');
                if (lbl && lbl.textContent.trim().toLowerCase().includes(labelText.toLowerCase())) {
                    return group.querySelector('select');
                }
            }
            return null;
        }

        // Payment Modal Functions
        function openPaymentModal() {
            const totalAmount = document.getElementById('totalAmount').value;
            if (!totalAmount || parseFloat(totalAmount) <= 0) {
                alert('Please add items and calculate total before payment');
                return;
            }

            // Reset: uncheck all payment checkboxes and hide all sections
            document.querySelectorAll('input[name="payment_method"]').forEach(cb => {
                cb.checked = false;
            });
            const allSections = [
                '.home-credit-section', '.credit-card-section', '.debit-card-section',
                '.qr-ph-section', '.starpay-qr-section', '.ewallet-section',
                '.online-banking-section', '.cash-section'
            ];
            allSections.forEach(sel => {
                const el = document.querySelector(sel);
                if (el) el.style.display = 'none';
            });

            // Reset dropdowns to default
            const ppDd = document.getElementById('paymentPartnersDropdown');
            if (ppDd) ppDd.selectedIndex = 0;
            const ccDd = document.getElementById('cardPaymentDropdown');
            if (ccDd) ccDd.selectedIndex = 0;
            const qrDd = document.getElementById('qrDropdown');
            if (qrDd) qrDd.selectedIndex = 0;

            // Clear all amount/reference inputs inside payment sections
            document.querySelectorAll('.home-credit-section input, .credit-card-section input, .debit-card-section input, .qr-ph-section input, .starpay-qr-section input, .ewallet-section input, .online-banking-section input, .cash-section input').forEach(inp => {
                if (inp.type !== 'checkbox') inp.value = '';
            });

            // Set total amount in all per-section total labels
            const totalInputs = document.querySelectorAll('.total-input');
            totalInputs.forEach(input => {
                input.value = totalAmount;
            });

            // Populate Total Amount Due in the modal summary area with comma formatting
            const globalTotalDue = document.getElementById('globalTotalDueInput');
            if (globalTotalDue) {
                const formattedTotal = parseFloat(totalAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                globalTotalDue.value = formattedTotal;
            }

            // Reset Total Payment and breakdown banner
            const globalTotal = document.getElementById('globalTotalInput');
            if (globalTotal) globalTotal.value = '';
            const bkBanner = document.getElementById('paymentBreakdownBanner');
            if (bkBanner) { bkBanner.style.display = 'none'; bkBanner.innerHTML = ''; }

            // Populate Unit dropdowns from the items table
            populateUnitSelector();

            // If window.paymentData already has saved info, restore it
            if (window.paymentData) {
                const payments = window.paymentData.payment_type === 'multiple'
                    ? window.paymentData.payments
                    : [window.paymentData];

                const homeCreditSection = document.querySelector('.home-credit-section');
                const creditCardSection = document.querySelector('.credit-card-section');
                const debitCardSection = document.querySelector('.debit-card-section');
                const qrPhSection = document.querySelector('.qr-ph-section');
                const starpayQrSection = document.querySelector('.starpay-qr-section');
                const ewalletSection = document.querySelector('.ewallet-section');
                const onlineBankingSection = document.querySelector('.online-banking-section');
                const cashSection = document.querySelector('.cash-section');

                payments.forEach(payment => {
                    if (payment.payment_type === 'cash') {
                        const cb = document.querySelector('input[name="payment_method"][value="cash"]');
                        if (cb) { cb.checked = true; }
                        if (cashSection) {
                            cashSection.style.display = 'block';
                            const cashAmountInput = getFieldByLabel(cashSection, 'amount');
                            if (cashAmountInput) cashAmountInput.value = payment.amount;
                            if (payment.units) {
                                cashSection.querySelectorAll('input[name="Unit"]').forEach(ucb => {
                                    ucb.checked = payment.units.includes(ucb.value);
                                    ucb.dispatchEvent(new Event('change'));
                                });
                            }
                        }
                    }
                    else if (payment.payment_type === 'ewallet') {
                        const cb = document.querySelector('input[name="payment_method"][value="ewallet"]');
                        if (cb) { cb.checked = true; }
                        if (ewalletSection) {
                            ewalletSection.style.display = 'block';
                            const ewalletTypeEl = getSelectByLabel(ewalletSection, 'e-wallet');
                            if (ewalletTypeEl) ewalletTypeEl.value = payment.ewallet_type;
                            const ewalletRefEl = getFieldByLabel(ewalletSection, 'reference');
                            if (ewalletRefEl) ewalletRefEl.value = payment.reference_no;
                            const ewalletAmtEl = getFieldByLabel(ewalletSection, 'amount');
                            if (ewalletAmtEl) ewalletAmtEl.value = payment.amount;
                            const ewalletNameEl = getFieldByLabel(ewalletSection, "customer");
                            if (ewalletNameEl) ewalletNameEl.value = payment.customer_name || '';
                            if (payment.units) {
                                ewalletSection.querySelectorAll('input[name="Unit"]').forEach(ucb => {
                                    ucb.checked = payment.units.includes(ucb.value);
                                    ucb.dispatchEvent(new Event('change'));
                                });
                            }
                        }
                    }
                    else if (payment.payment_type === 'online_banking') {
                        const cb = document.querySelector('input[name="payment_method"][value="online_banking"]');
                        if (cb) { cb.checked = true; }
                        if (onlineBankingSection) {
                            onlineBankingSection.style.display = 'block';
                            const bankSel = getSelectByLabel(onlineBankingSection, 'bank');
                            if (bankSel) bankSel.value = payment.bank_name;
                            const bankRefEl = getFieldByLabel(onlineBankingSection, 'reference');
                            if (bankRefEl) bankRefEl.value = payment.reference_no;
                            const bankAmtEl = getFieldByLabel(onlineBankingSection, 'amount');
                            if (bankAmtEl) bankAmtEl.value = payment.amount;
                            if (payment.units) {
                                onlineBankingSection.querySelectorAll('input[name="Unit"]').forEach(ucb => {
                                    ucb.checked = payment.units.includes(ucb.value);
                                    ucb.dispatchEvent(new Event('change'));
                                });
                            }
                        }
                    }
                    else if (payment.payment_type === 'payment_partners') {
                        const cb = document.getElementById('chkPaymentPartners');
                        if (cb) { cb.checked = true; }
                        const ppDropdown = document.getElementById('paymentPartnersDropdown');
                        if (ppDropdown) {
                            // Try to match by text name first, fallback to value for backwards compatibility
                            let matched = false;
                            for (let i = 0; i < ppDropdown.options.length; i++) {
                                if (ppDropdown.options[i].text === payment.payment_partner) {
                                    ppDropdown.selectedIndex = i;
                                    matched = true;
                                    break;
                                }
                            }
                            if (!matched) {
                                // Fallback to old format (partner1, partner2, etc.)
                                ppDropdown.value = payment.payment_partner || 'partner1';
                            }
                            // Trigger change event to update the UI
                            ppDropdown.dispatchEvent(new Event('change'));
                        }
                        if (homeCreditSection) {
                            homeCreditSection.style.display = 'block';
                            const loanTypeEl = getSelectByLabel(homeCreditSection, 'loan type');
                            const loanTermsEl = getSelectByLabel(homeCreditSection, 'loan terms');
                            const custNameEl = getFieldByLabel(homeCreditSection, "customer");
                            const loanNumEl = getFieldByLabel(homeCreditSection, 'loan number');
                            const totalLoanEl = document.getElementById('totalLoanAmount');
                            const loanBalEl = getFieldByLabel(homeCreditSection, 'loan balance');

                            if (loanTypeEl) {
                                const selectedPartner = ppDropdown ? ppDropdown.value : '';
                                if (selectedPartner === 'partner2') {
                                    loanTypeEl.innerHTML = '<option value=""></option><option value="0_installment">0% Installment</option><option value="standard_loan">Standard loan</option><option value="retailer_zero">Retailer Zero</option><option value="saver_plan">Saver Plan</option>';
                                } else {
                                    loanTypeEl.innerHTML = '<option value=""></option><option value="installment_loan">Installment Loan</option><option value="0_installment">0% Installment</option><option value="promo_installment">Promo Installment</option><option value="standard_installment">Standard Installment</option><option value="cash_loan">Cash Loan</option>';
                                }
                                loanTypeEl.value = payment.loan_type;
                            }
                            if (loanTermsEl) loanTermsEl.value = payment.loan_terms;
                            if (custNameEl) custNameEl.value = payment.customer_name || '';
                            if (loanNumEl) loanNumEl.value = payment.loan_number;
                            if (totalLoanEl) totalLoanEl.value = payment.total_loan_amount || '';
                            if (loanBalEl) loanBalEl.value = payment.loan_balance;

                            if (payment.down_payment_methods) {
                                payment.down_payment_methods.forEach(dpMethod => {
                                    const dpcb = homeCreditSection.querySelector(`input[name="down_payment_method"][value="${dpMethod}"]`);
                                    if (dpcb) dpcb.checked = true;
                                });
                                toggleDownPaymentReference();
                            }
                            if (payment.cash_dp_amount) {
                                const inp = document.getElementById('cash_down_payment_amount');
                                if (inp) inp.value = payment.cash_dp_amount;
                            }
                            if (payment.gcash_reference) {
                                const ref = document.getElementById('gcash_down_payment_reference');
                                if (ref) ref.value = payment.gcash_reference;
                            }
                            if (payment.gcash_dp_amount) {
                                const inp = document.getElementById('gcash_down_payment_amount');
                                if (inp) inp.value = payment.gcash_dp_amount;
                            }
                            if (payment.maya_reference) {
                                const ref = document.getElementById('maya_down_payment_reference');
                                if (ref) ref.value = payment.maya_reference;
                            }
                            if (payment.maya_dp_amount) {
                                const inp = document.getElementById('maya_down_payment_amount');
                                if (inp) inp.value = payment.maya_dp_amount;
                            }
                            if (payment.units) {
                                homeCreditSection.querySelectorAll('input[name="Unit"]').forEach(ucb => {
                                    ucb.checked = payment.units.includes(ucb.value);
                                    ucb.dispatchEvent(new Event('change'));
                                });
                            }
                        }
                    }
                    else if (payment.payment_type === 'credit_card') {
                        const cb = document.getElementById('chkCardPayment');
                        if (cb) { cb.checked = true; }
                        const cardDropdown = document.getElementById('cardPaymentDropdown');
                        if (cardDropdown) cardDropdown.value = 'credit_card';
                        if (creditCardSection) {
                            creditCardSection.style.display = 'block';
                            const ccAmtEl = document.getElementById('creditCardAmount');
                            if (ccAmtEl) ccAmtEl.value = payment.amount;
                            if (payment.units) {
                                creditCardSection.querySelectorAll('input[name="Unit"]').forEach(ucb => {
                                    ucb.checked = payment.units.includes(ucb.value);
                                    ucb.dispatchEvent(new Event('change'));
                                });
                            }
                        }
                    }
                    else if (payment.payment_type === 'debit_card') {
                        const cb = document.getElementById('chkCardPayment');
                        if (cb) { cb.checked = true; }
                        const cardDropdown = document.getElementById('cardPaymentDropdown');
                        if (cardDropdown) cardDropdown.value = 'debit_card';
                        if (debitCardSection) {
                            debitCardSection.style.display = 'block';
                            const dcAmtEl = getFieldByLabel(debitCardSection, 'amount');
                            if (dcAmtEl) dcAmtEl.value = payment.amount;
                            if (payment.units) {
                                debitCardSection.querySelectorAll('input[name="Unit"]').forEach(ucb => {
                                    ucb.checked = payment.units.includes(ucb.value);
                                    ucb.dispatchEvent(new Event('change'));
                                });
                            }
                        }
                    }
                    else if (payment.payment_type === 'qr_ph') {
                        const cb = document.getElementById('chkQR');
                        if (cb) { cb.checked = true; }
                        const qrDropdown = document.getElementById('qrDropdown');
                        if (qrDropdown) qrDropdown.value = 'qr_ph';
                        if (qrPhSection) {
                            qrPhSection.style.display = 'block';
                            const qrAmtEl = getFieldByLabel(qrPhSection, 'amount');
                            if (qrAmtEl) qrAmtEl.value = payment.amount;
                            if (payment.units) {
                                qrPhSection.querySelectorAll('input[name="Unit"]').forEach(ucb => {
                                    ucb.checked = payment.units.includes(ucb.value);
                                    ucb.dispatchEvent(new Event('change'));
                                });
                            }
                        }
                    }
                    else if (payment.payment_type === 'starpay_qr') {
                        const cb = document.getElementById('chkQR');
                        if (cb) { cb.checked = true; }
                        const qrDropdown = document.getElementById('qrDropdown');
                        if (qrDropdown) qrDropdown.value = 'starpay_qr';
                        if (starpayQrSection) {
                            starpayQrSection.style.display = 'block';
                            const qrAmtEl = getFieldByLabel(starpayQrSection, 'amount');
                            if (qrAmtEl) qrAmtEl.value = payment.amount;
                            if (payment.units) {
                                starpayQrSection.querySelectorAll('input[name="Unit"]').forEach(ucb => {
                                    ucb.checked = payment.units.includes(ucb.value);
                                    ucb.dispatchEvent(new Event('change'));
                                });
                            }
                        }
                    }
                });

                // Recalculate and display breakdown
                recalcTotalPayment();
            }

            // Attach live Total Payment + Breakdown listener (once) using event delegation
            const modalBody = document.querySelector('#paymentModal .modal-body');
            if (modalBody && !modalBody._totalListenerAttached) {
                modalBody._totalListenerAttached = true;
                modalBody.addEventListener('input', function (e) {
                    const inp = e.target;
                    if (!inp || inp.classList.contains('total-input') || inp.readOnly) return;
                    // Exclude "totalLoanAmount" from triggering payment recalculation
                    if (inp.id === 'totalLoanAmount') return;
                    const parentSection = inp.closest(
                        '.cash-section, .ewallet-section, .online-banking-section, ' +
                        '.home-credit-section, .credit-card-section, .debit-card-section, ' +
                        '.qr-ph-section, .starpay-qr-section'
                    );
                    if (!parentSection || parentSection.style.display !== 'block') return;
                    const formGroup = inp.closest('.hc-form-group, .enter-amount-row, .reference-no-row');
                    const lbl = formGroup ? formGroup.querySelector('label') : null;
                    const lblText = lbl ? lbl.textContent.toLowerCase() : '';
                    // Exclude "Total Loan Amount" from payment calculation - it's for reference only
                    const isAmountField = (lblText.includes('amount') || lblText.includes('balance')) && !lblText.includes('total loan amount');
                    if (!isAmountField) return;
                    recalcTotalPayment();
                });
            }

            // Re-trigger recalc when payment sections are toggled (checkbox change)
            if (!document.body._paymentSectionChangeAttached) {
                document.body._paymentSectionChangeAttached = true;
                document.body.addEventListener('change', function (e) {
                    if (e.target.name === 'payment_method' || e.target.id === 'paymentPartnersDropdown' || e.target.id === 'cardPaymentDropdown' || e.target.id === 'qrDropdown') {
                        setTimeout(recalcTotalPayment, 60);
                    }
                });
            }

            document.getElementById('paymentModal').style.display = 'flex';

            // Auto-check payment method removed - Payment Method dropdown no longer exists
        }

        function populateUnitSelector() {
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
                    if (desc) items.push({ desc });
                }
            });

            unitRows.forEach(unitRow => {
                if (items.length === 0) {
                    unitRow.style.display = 'none';
                    unitRow.innerHTML = '';
                    return;
                }

                unitRow.style.display = '';
                unitRow.innerHTML = `
                    <label style="min-width: 120px;">Unit:</label>
                    <div class="custom-multiselect" style="position: relative; flex: 1; min-width: 200px;">
                        <div class="multiselect-selected hc-input" style="cursor: pointer; background: ${items.length === 1 ? '#f5f5f5' : '#fff'}; display: flex; align-items: center; justify-content: space-between;" onclick="const d = this.nextElementSibling; d.style.display = d.style.display === 'none' ? 'block' : 'none';">
                            <span class="selected-text" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 10px;">-- Select Units --</span>
                            <span style="font-size: 10px;">&#9660;</span>
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
                    selectAllLbl.style.cssText = 'margin:0 0 5px 0; display:flex; align-items:center; gap:8px; font-weight:bold; line-height:1.2; color:#1e40af; cursor:pointer; padding:5px; border-bottom:1px solid #e5e7eb; border-radius:3px;';
                    selectAllLbl.onmouseover = () => selectAllLbl.style.backgroundColor = '#eff6ff';
                    selectAllLbl.onmouseout = () => selectAllLbl.style.backgroundColor = 'transparent';

                    const selectAllCb = document.createElement('input');
                    selectAllCb.type = 'checkbox';
                    selectAllCb.className = 'select-all-units-cb';
                    selectAllCb.style.cssText = 'margin-top:0; width:16px; height:16px;';

                    selectAllCb.addEventListener('change', function () {
                        const unitCbs = container.querySelectorAll('input[type="checkbox"][name="Unit"]');
                        unitCbs.forEach(cb => cb.checked = this.checked);
                        updateText();
                    });

                    selectAllLbl.appendChild(selectAllCb);
                    selectAllLbl.appendChild(document.createTextNode('Select All'));
                    container.appendChild(selectAllLbl);
                }

                items.forEach(item => {
                    const lbl = document.createElement('label');
                    lbl.style.cssText = 'margin:0; display:flex; align-items:flex-start; gap:8px; font-weight:normal; line-height:1.2; color:#333; cursor:pointer; padding:5px; border-radius:3px;';
                    lbl.onmouseover = () => lbl.style.backgroundColor = '#f0f0f0';
                    lbl.onmouseout = () => lbl.style.backgroundColor = 'transparent';

                    const cb = document.createElement('input');
                    cb.type = 'checkbox'; cb.name = 'Unit'; cb.value = item.desc;
                    cb.style.cssText = 'margin-top:2px; width:16px; height:16px;';
                    cb.addEventListener('change', updateText);

                    if (items.length === 1) cb.checked = true;

                    lbl.appendChild(cb);
                    lbl.appendChild(document.createTextNode(item.desc));
                    container.appendChild(lbl);
                });

                updateText();
            });

            // Close unit dropdowns when clicking outside
            if (!document._unitDropdownCloseAttached) {
                document._unitDropdownCloseAttached = true;
                document.addEventListener('click', function (e) {
                    if (!e.target.closest('.custom-multiselect')) {
                        document.querySelectorAll('.multiselect-dropdown').forEach(d => d.style.display = 'none');
                    }
                });
            }
        }

        function recalcTotalPayment() {
            const originalTotalStr = document.getElementById('totalAmount') ? document.getElementById('totalAmount').value : '0';
            const originalTotal = parseFloat((originalTotalStr || '').replace(/,/g, '')) || 0;

            // Sum all amount inputs in currently visible payment sections
            const allPaySections = [
                { class: '.cash-section', name: 'Cash' },
                { class: '.ewallet-section', name: 'E-Wallet' },
                { class: '.online-banking-section', name: 'Online Banking' },
                { class: '.home-credit-section', name: 'Home Credit' },
                { class: '.credit-card-section', name: 'Credit Card' },
                { class: '.debit-card-section', name: 'Debit Card' },
                { class: '.qr-ph-section', name: 'QR PH' },
                { class: '.starpay-qr-section', name: 'Starpay QR' }
            ];

            let totalPaid = 0;
            allPaySections.forEach(sec => {
                const secEl = document.querySelector(sec.class);
                if (!secEl || secEl.style.display !== 'block') return;
                secEl.querySelectorAll('.hc-form-group, .enter-amount-row, .reference-no-row').forEach(group => {
                    const lbl = group.querySelector('label');
                    const lblText = lbl ? lbl.textContent.toLowerCase() : '';
                    // Exclude "Total Loan Amount" from payment calculation - it's for reference only
                    if ((lblText.includes('amount') || lblText.includes('balance')) && !lblText.includes('total loan amount')) {
                        const inp = group.querySelector('input');
                        if (inp && !inp.readOnly && inp.type !== 'checkbox' && inp.id !== 'totalLoanAmount') {
                            totalPaid += parseFloat((inp.value || '').replace(/,/g, '')) || 0;
                        }
                    }
                });
            });

            // Update Total Payment display
            const globalTotal = document.getElementById('globalTotalInput');
            if (globalTotal) {
                globalTotal.value = totalPaid > 0
                    ? totalPaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : '';
            }

            // Update Total Amount Due = original - paid
            const remaining = originalTotal - totalPaid;
            const globalTotalDue = document.getElementById('globalTotalDueInput');
            if (globalTotalDue) {
                globalTotalDue.value = remaining.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // Render Payment Breakdown
            if (totalPaid > 0) renderPaymentBreakdown(totalPaid, originalTotal);
            else {
                const bkBanner = document.getElementById('paymentBreakdownBanner');
                if (bkBanner) { bkBanner.style.display = 'none'; bkBanner.innerHTML = ''; }
            }
        }

        function renderPaymentBreakdown(totalPaid, originalTotal) {
            const banner = document.getElementById('paymentBreakdownBanner');
            if (!banner) return;

            const neededDisp = originalTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const enteredDisp = totalPaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const overallDiff = originalTotal - totalPaid;
            const remainingBal = overallDiff < 0 ? 0 : overallDiff;
            const diffDisp = remainingBal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const diffColor = overallDiff <= 0.01 ? '#16a34a' : '#ca8a04';

            // Build Unit(s) To Pay rows from items table
            let unitRowsHtml = '';
            const itemTbody = document.getElementById('itemsTableBody');
            if (itemTbody) {
                itemTbody.querySelectorAll('tr:not(#no-sales-row)').forEach(row => {
                    const tds = row.querySelectorAll('td');
                    if (tds.length >= 3) {
                        const descText = tds[0].textContent.trim();
                        const priceInput = row.querySelector('.price-input-table');
                        const qtyInput = row.querySelector('.qty-input');
                        if (descText && priceInput) {
                            const pVal = parseFloat(priceInput.value.replace(/,/g, '')) || 0;
                            const qVal = parseInt(qtyInput ? qtyInput.value : 1) || 1;
                            unitRowsHtml += `
                                <div style="display:flex; justify-content:space-between; padding-left:12px; font-size:13px; color:#000; margin-top:2px;">
                                    <span style="font-style:italic; max-width:320px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">- ${descText}</span>
                                    <span>₱${(pVal * qVal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                </div>`;
                        }
                    }
                });
            }
            if (unitRowsHtml) {
                const _discountFieldEl = document.getElementById('discountField');
                const _discountAmt = _discountFieldEl ? (parseFloat(_discountFieldEl.value.replace(/,/g, '')) || 0) : 0;
                const _discountRow = _discountAmt > 0
                    ? `<div style="display:flex; justify-content:space-between; padding-left:14px; font-size:12px; color:#dc2626; margin-top:2px;"><span style="font-weight:600;">↳ Less Discount:</span><span style="font-weight:600;">-₱${_discountAmt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div>`
                    : '';
                unitRowsHtml = `<div style="margin-bottom:6px;"><div style="font-weight:600; color:#000; font-size:13px;">Unit(s) To Pay:</div>${unitRowsHtml}${_discountRow}</div>`;
            }

            // Build payment method breakdown rows
            const paymentSections = [
                { class: '.home-credit-section', name: 'Home Credit' },
                { class: '.credit-card-section', name: 'Credit Card' },
                { class: '.debit-card-section', name: 'Debit Card' },
                { class: '.qr-ph-section', name: 'QR PH' },
                { class: '.starpay-qr-section', name: 'Starpay QR' },
                { class: '.ewallet-section', name: 'E-Wallet' },
                { class: '.online-banking-section', name: 'Online Banking' },
                { class: '.cash-section', name: 'Cash' }
            ];

            let breakdownInner = '';
            paymentSections.forEach(sec => {
                const secEl = document.querySelector(sec.class);
                if (!secEl || secEl.style.display !== 'block') return;

                let secTotal = 0;
                let secRows = '';

                secEl.querySelectorAll('input[type="text"], input[type="number"]').forEach(inp => {
                    if (inp.readOnly || inp.classList.contains('total-input')) return;
                    // Exclude "totalLoanAmount" ID from payment calculation - it's for reference only
                    if (inp.id === 'totalLoanAmount') return;
                    let isAmount = false, labelText = '';

                    if (inp.id === 'cash_down_payment_amount') { isAmount = true; labelText = 'Cash (DP)'; }
                    if (inp.id === 'gcash_down_payment_amount') { isAmount = true; labelText = 'G-Cash (DP)'; }
                    if (inp.id === 'maya_down_payment_amount') { isAmount = true; labelText = 'Maya (DP)'; }

                    const fg = inp.closest('.hc-form-group');
                    if (fg) {
                        const lbl = fg.querySelector('label');
                        // Exclude "Total Loan Amount" from payment calculation - it's for reference only
                        if (lbl && (lbl.innerText.includes('Amount') || lbl.innerText.includes('Balance')) && !lbl.innerText.includes('Total Loan Amount')) {
                            isAmount = true;
                            if (!labelText) labelText = lbl.innerText.replace(':', '').trim();
                        }
                    }
                    const ear = inp.closest('.enter-amount-row, .reference-no-row');
                    if (ear) {
                        const lbl = ear.querySelector('label');
                        if (lbl && lbl.innerText.includes('Amount')) {
                            isAmount = true;
                            if (!labelText) labelText = 'Downpayment';
                        }
                    }
                    if (isAmount && !labelText) labelText = 'Amount';

                    if (isAmount) {
                        const val = parseFloat((inp.value || '').replace(/,/g, '')) || 0;
                        if (val > 0) {
                            secTotal += val;
                            secRows += `<div style="display:flex; justify-content:space-between; padding-left:12px; font-size:12px; color:#000; margin-top:2px;">
                                <span>- ${labelText}</span>
                                <span>₱${val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                            </div>`;
                        }
                    }
                });

                if (secTotal > 0) {
                    let displayName = sec.name;
                    const ppDd = document.getElementById('paymentPartnersDropdown');
                    if (sec.name === 'Home Credit' && ppDd && ppDd.value !== '') displayName = ppDd.options[ppDd.selectedIndex].text;
                    const ewSel = secEl.querySelector('select');
                    if ((sec.name === 'E-Wallet' || sec.name === 'Online Banking') && ewSel && ewSel.selectedIndex > 0) displayName = ewSel.options[ewSel.selectedIndex].text;

                    breakdownInner += `<div style="margin-bottom:6px;"><div style="font-weight:600; color:#000; font-size:13px;">${displayName}:</div>${secRows}</div>`;
                }
            });

            const breakdownHtml = breakdownInner
                ? `<div style="margin:8px 0; padding:6px 0; border-top:1px solid #cfcfcf; border-bottom:1px solid #cfcfcf;">${breakdownInner}</div>`
                : '';

            banner.innerHTML = `
                <div style="display:flex; align-items:flex-start; gap:12px;">
                    <div style="color:#000; margin-top:2px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                    </div>
                    <div style="flex:1; color:#000; font-size:13px; line-height:1.6;">
                        <div style="font-weight:600; font-size:14px; color:#000; margin-bottom:4px;">Payment Breakdown</div>
                        <div>You are currently entering your breakdown details.</div>
                        <div style="margin-top:6px; display:flex; flex-direction:column; gap:2px; max-width:450px; padding-top:6px;">
                            ${unitRowsHtml}
                            <div style="display:flex; justify-content:space-between; border-bottom:1.5px solid #fff; padding-bottom:4px; margin-bottom:2px;">
                                <span style="font-weight:600;">Total Amount Due:</span><span style="font-weight:700;">₱${neededDisp}</span>
                            </div>
                            ${breakdownHtml}
                            <div style="display:flex; justify-content:space-between;">
                                <span>Total Entered:</span><span style="font-weight:600;">₱${enteredDisp}</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; margin-top:2px; padding-top:4px; border-top:1.5px dashed #afafaf;">
                                <span style="color:#000; font-weight:600;">Remaining Balance:</span><span style="color:${diffColor}; font-weight:600;">₱${diffDisp}</span>
                            </div>
                        </div>
                    </div>
                </div>`;
            banner.style.display = 'block';
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }

        // showPaymentSection() removed - now handled by per-checkbox listeners above

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

        function savePaymentData() {
            // Check which sections are currently visible (multi-payment support)
            const homeCreditSection = document.querySelector('.home-credit-section');
            const creditCardSection = document.querySelector('.credit-card-section');
            const debitCardSection = document.querySelector('.debit-card-section');
            const qrPhSection = document.querySelector('.qr-ph-section');
            const starpayQrSection = document.querySelector('.starpay-qr-section');
            const ewalletSection = document.querySelector('.ewallet-section');
            const onlineBankingSection = document.querySelector('.online-banking-section');
            const cashSection = document.querySelector('.cash-section');

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

            // Collect payment data from ALL visible sections
            let payments = [];

            // Cash â€” find by label "Amount"
            if (cashSection && cashSection.style.display === 'block') {
                const cashAmountInput = getFieldByLabel(cashSection, 'amount');
                const cashAmount = cashAmountInput ? cashAmountInput.value.trim() : '';
                if (!cashAmount) { alert('Please enter Cash amount.'); return; }
                payments.push({
                    payment_type: 'cash',
                    amount: cashAmount,
                    units: Array.from(cashSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            // E-Wallet â€” find by labels
            if (ewalletSection && ewalletSection.style.display === 'block') {
                const ewalletTypeEl = getSelectByLabel(ewalletSection, 'e-wallet');
                const ewalletType = ewalletTypeEl ? ewalletTypeEl.value : '';
                const ewalletRefEl = getFieldByLabel(ewalletSection, 'reference');
                const ewalletAmtEl = getFieldByLabel(ewalletSection, 'amount');
                const ewalletNameEl = getFieldByLabel(ewalletSection, "customer");
                const ewalletRef = ewalletRefEl ? ewalletRefEl.value.trim() : '';
                const ewalletAmount = ewalletAmtEl ? ewalletAmtEl.value.trim() : '';
                const ewalletCustomer = ewalletNameEl ? ewalletNameEl.value.trim() : '';
                if (!ewalletType || !ewalletRef || !ewalletAmount) { alert('Please fill all E-Wallet fields.'); return; }
                payments.push({
                    payment_type: 'ewallet',
                    ewallet_type: ewalletType,
                    customer_name: ewalletCustomer,
                    reference_no: ewalletRef,
                    amount: ewalletAmount,
                    units: Array.from(ewalletSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            // Online Banking â€” find by labels
            if (onlineBankingSection && onlineBankingSection.style.display === 'block') {
                const bankSel = getSelectByLabel(onlineBankingSection, 'bank');
                const bankName = bankSel ? bankSel.value : '';
                const bankRefEl = getFieldByLabel(onlineBankingSection, 'reference');
                const bankAmtEl = getFieldByLabel(onlineBankingSection, 'amount');
                const bankRef = bankRefEl ? bankRefEl.value.trim() : '';
                const bankAmount = bankAmtEl ? bankAmtEl.value.trim() : '';
                if (!bankName || !bankRef || !bankAmount) { alert('Please fill all Online Banking fields.'); return; }
                payments.push({
                    payment_type: 'online_banking',
                    bank_name: bankName,
                    reference_no: bankRef,
                    amount: bankAmount,
                    units: Array.from(onlineBankingSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            // Home Credit / Payment Partners â€” find by labels
            if (homeCreditSection && homeCreditSection.style.display === 'block') {
                const loanTypeEl = getSelectByLabel(homeCreditSection, 'loan type');
                const loanTermsEl = getSelectByLabel(homeCreditSection, 'loan terms');
                const custNameEl = getFieldByLabel(homeCreditSection, "customer");
                const loanNumEl = getFieldByLabel(homeCreditSection, 'loan number');
                const totalLoanEl = document.getElementById('totalLoanAmount');
                const loanBalEl = getFieldByLabel(homeCreditSection, 'loan balance');
                const loanType = loanTypeEl ? loanTypeEl.value : '';
                const loanTerms = loanTermsEl ? loanTermsEl.value : '';
                const customerName = custNameEl ? custNameEl.value.trim() : '';
                const loanNumber = loanNumEl ? loanNumEl.value.trim() : '';
                const totalLoan = totalLoanEl ? totalLoanEl.value.trim() : '';
                const loanBalance = loanBalEl ? loanBalEl.value.trim() : '';

                const downPaymentMethods = [];
                homeCreditSection.querySelectorAll('input[name="down_payment_method"]:checked').forEach(cb => { downPaymentMethods.push(cb.value); });

                if (downPaymentMethods.length === 0) {
                    alert('DOWN PAYMENT METHOD REQUIRED! Please check at least one payment method (Cash, G-Cash, or Maya).');
                    return;
                }

                // Get payment partner name (text) instead of value
                const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
                const paymentPartnerName = paymentPartnersDropdown ?
                    paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text : '';

                const dpEntry = {
                    payment_type: 'payment_partners',
                    payment_partner: paymentPartnerName,
                    loan_type: loanType,
                    loan_terms: loanTerms,
                    customer_name: customerName,
                    loan_number: loanNumber,
                    total_loan_amount: totalLoan,
                    loan_balance: loanBalance,
                    down_payment_methods: downPaymentMethods,
                    units: Array.from(homeCreditSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                };
                if (downPaymentMethods.includes('cash')) {
                    const inp = document.getElementById('cash_down_payment_amount');
                    const cashVal = inp ? inp.value.trim() : '';
                    if (!cashVal) {
                        alert('ENTER AMOUNT REQUIRED! Please enter the Cash down payment amount.');
                        if (inp) inp.focus();
                        return;
                    }
                    dpEntry.cash_dp_amount = cashVal;
                }
                if (downPaymentMethods.includes('gcash')) {
                    const ref = document.getElementById('gcash_down_payment_reference');
                    const inp = document.getElementById('gcash_down_payment_amount');
                    const gcashRef = ref ? ref.value.trim() : '';
                    const gcashAmt = inp ? inp.value.trim() : '';
                    if (!gcashRef) {
                        alert('G-CASH REFERENCE NUMBER REQUIRED! Please enter the G-Cash reference number.');
                        if (ref) ref.focus();
                        return;
                    }
                    if (!gcashAmt) {
                        alert('ENTER AMOUNT REQUIRED! Please enter the G-Cash down payment amount.');
                        if (inp) inp.focus();
                        return;
                    }
                    dpEntry.gcash_reference = gcashRef;
                    dpEntry.gcash_dp_amount = gcashAmt;
                }
                if (downPaymentMethods.includes('maya')) {
                    const ref = document.getElementById('maya_down_payment_reference');
                    const inp = document.getElementById('maya_down_payment_amount');
                    const mayaRef = ref ? ref.value.trim() : '';
                    const mayaAmt = inp ? inp.value.trim() : '';
                    if (!mayaRef) {
                        alert('MAYA REFERENCE NUMBER REQUIRED! Please enter the Maya reference number.');
                        if (ref) ref.focus();
                        return;
                    }
                    if (!mayaAmt) {
                        alert('ENTER AMOUNT REQUIRED! Please enter the Maya down payment amount.');
                        if (inp) inp.focus();
                        return;
                    }
                    dpEntry.maya_reference = mayaRef;
                    dpEntry.maya_dp_amount = mayaAmt;
                }
                payments.push(dpEntry);
            }

            // Credit Card â€” find by label "Amount"
            if (creditCardSection && creditCardSection.style.display === 'block') {
                const ccAmtEl = getFieldByLabel(creditCardSection, 'amount');
                const ccAmount = ccAmtEl ? ccAmtEl.value.trim() : '';
                if (!ccAmount) { alert('Please enter Credit Card amount.'); return; }
                payments.push({
                    payment_type: 'credit_card',
                    amount: ccAmount,
                    units: Array.from(creditCardSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            // Debit Card â€” find by label "Amount"
            if (debitCardSection && debitCardSection.style.display === 'block') {
                const dcAmtEl = getFieldByLabel(debitCardSection, 'amount');
                const dcAmount = dcAmtEl ? dcAmtEl.value.trim() : '';
                if (!dcAmount) { alert('Please enter Debit Card amount.'); return; }
                payments.push({
                    payment_type: 'debit_card',
                    amount: dcAmount,
                    units: Array.from(debitCardSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            // QR PH â€” find by label "Amount"
            if (qrPhSection && qrPhSection.style.display === 'block') {
                const qrAmtEl = getFieldByLabel(qrPhSection, 'amount');
                const qrAmount = qrAmtEl ? qrAmtEl.value.trim() : '';
                if (!qrAmount) { alert('Please enter QR PH amount.'); return; }
                payments.push({
                    payment_type: 'qr_ph',
                    amount: qrAmount,
                    units: Array.from(qrPhSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            // Starpay QR â€” find by label "Amount"
            if (starpayQrSection && starpayQrSection.style.display === 'block') {
                const spAmtEl = getFieldByLabel(starpayQrSection, 'amount');
                const spAmount = spAmtEl ? spAmtEl.value.trim() : '';
                if (!spAmount) { alert('Please enter Starpay QR amount.'); return; }
                payments.push({
                    payment_type: 'starpay_qr',
                    amount: spAmount,
                    units: Array.from(starpayQrSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            // Store all payment data
            window.paymentData = payments.length === 1 ? payments[0] : { payment_type: 'multiple', payments: payments };
            const hiddenInput = document.getElementById('payment_data');
            if (hiddenInput) {
                hiddenInput.value = JSON.stringify(window.paymentData);
            }

            // Show summary in button
            const btnPayment = document.querySelector('.btn-payment');
            if (btnPayment) {
                const labels = payments.map(p => {
                    if (p.payment_type === 'cash') return 'Cash';
                    if (p.payment_type === 'ewallet') return p.ewallet_type || 'E-Wallet';
                    if (p.payment_type === 'online_banking') return 'Online Banking';
                    if (p.payment_type === 'credit_card') return 'Credit Card';
                    if (p.payment_type === 'debit_card') return 'Debit Card';
                    if (p.payment_type === 'qr_ph') return 'QR PH';
                    if (p.payment_type === 'starpay_qr') return 'Starpay QR';
                    if (p.payment_type === 'payment_partners') return document.getElementById('paymentPartnersDropdown')?.options[document.getElementById('paymentPartnersDropdown').selectedIndex]?.text || 'Payment Partner';
                    return p.payment_type;
                });
                btnPayment.innerText = 'Payment: ' + labels.join(' + ');
                btnPayment.style.backgroundColor = '#B08A52';
                btnPayment.style.color = 'white';
            }

            alert('Payment information saved successfully!');
            closePaymentModal();
        }

        // Close modals when clicking outside
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
    </script>
</body>

</html>