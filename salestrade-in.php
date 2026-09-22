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
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Sales Trade-In</title>
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

        .btn-save {
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
        }

        .btn-save:hover {
            background-color: var(--color-gold-light);
        }

        /* Table Styles */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border: 1px solid #ccc;
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
        }

        .items-table td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
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
            text-align: center;
        }

        .items-table input[type="number"]:focus {
            border-color: #66bb6a;
            box-shadow: 0 0 3px rgba(102, 187, 106, 0.3);
        }

        .qty-input {
            max-width: 80px;
            margin: 0 auto;
            display: block;
        }

        .price-input-table {
            max-width: 120px;
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

        .btn-clear-main:hover {
            background: #ecffebff;
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
            width: 80%;
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

        /* Media Queries for Responsiveness */
        @media (max-width: 1640px) {
            .form-split-layout {
                grid-template-columns: 1.5fr 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 1366px) {
            .form-split-layout {
                grid-template-columns: 1fr;
            }

            .bottom-section {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1024px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            /* Stack Unclaimed Freebies and Totals vertically on tablets */
            div[style*="grid-template-columns: 2fr 1fr"] {
                display: flex !important;
                flex-direction: column !important;
                gap: 20px !important;
            }

            /* Unclaimed Freebies container full width */
            .unclaimed-freebies-container {
                width: 100% !important;
            }

            /* Totals section full width */
            .totals-section {
                width: 100% !important;
            }

            /* Footer actions - horizontal layout on tablets */
            .footer-actions {
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 15px !important;
                flex-wrap: wrap !important;
            }

            .footer-left-group {
                display: flex !important;
                gap: 15px !important;
                align-items: center !important;
                flex: 1 !important;
            }

            .footer-right-group {
                display: flex !important;
                gap: 10px !important;
                align-items: center !important;
                flex-shrink: 0 !important;
            }

            /* Points and Commission inline */
            .footer-input-group {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
            }

            .footer-input-group label {
                white-space: nowrap !important;
                font-size: 14px !important;
                font-weight: 600 !important;
            }

            .footer-input-group input {
                width: 100px !important;
                padding: 8px !important;
                font-size: 14px !important;
            }

            /* Buttons compact size */
            .footer-right-group button,
            .btn-payment,
            .btn-save,
            .btn-clear,
            .btn-clear-main {
                padding: 10px 20px !important;
                font-size: 14px !important;
                min-width: 100px !important;
                white-space: nowrap !important;
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

            .form-container {
                padding: 15px;
            }

            /* Items table wrapper for horizontal scrolling */
            .items-table {
                width: 100%;
                display: table;
                table-layout: auto;
                /* Auto layout to respect min-widths */
            }

            .items-table-wrapper {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .items-table thead,
            .items-table tbody {
                width: 100%;
            }

            .items-table th,
            .items-table td {
                white-space: nowrap;
                /* Prevent text wrapping */
            }

            /* Set minimum column widths to force horizontal scroll on mobile */
            .items-table th:nth-child(1),
            .items-table td:nth-child(1) {
                min-width: 220px !important;
                /* Item Description */
            }

            .items-table th:nth-child(2),
            .items-table td:nth-child(2) {
                min-width: 180px !important;
                /* IMEI */
            }

            .items-table th:nth-child(3),
            .items-table td:nth-child(3) {
                min-width: 100px !important;
                /* Quantity */
            }

            .items-table th:nth-child(4),
            .items-table td:nth-child(4) {
                min-width: 120px !important;
                /* Price */
            }

            .items-table th:nth-child(5),
            .items-table td:nth-child(5) {
                min-width: 80px !important;
                /* Action */
            }
        }

        @media (max-width: 480px) {
            .header {
                height: 50px;
                padding: 0 10px;
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
        }

        /* Mobile Phones (max-width: 600px) */
        @media (max-width: 600px) {

            /* Form container */
            .form-container {
                padding: 10px !important;
            }

            /* Remarks section */
            .form-group textarea {
                font-size: 14px !important;
                padding: 10px !important;
                min-height: 100px !important;
            }

            /* Trade-In Details section */
            .item-selection-container>div[style*="background-color: #f9f9f9"] {
                padding: 15px !important;
            }

            .item-selection-container h4 {
                font-size: 15px !important;
                margin-bottom: 12px !important;
            }

            /* Stack Trade-In form rows vertically */
            .form-row[style*="grid-template-columns: 1fr 1fr"] {
                display: flex !important;
                flex-direction: column !important;
                gap: 12px !important;
            }

            /* Form groups full width */
            .form-group {
                width: 100% !important;
            }

            .form-group label {
                font-size: 13px !important;
                font-weight: 600 !important;
            }

            .form-group input,
            .form-group select {
                font-size: 14px !important;
                padding: 10px !important;
                width: 100% !important;
            }

            /* Sales item input rows - stack vertically */
            .item-input-row {
                display: flex !important;
                flex-direction: column !important;
                gap: 12px !important;
            }

            .item-input-row>div {
                width: 100% !important;
                flex: none !important;
            }

            /* Search and Add buttons */
            .btn-search-item,
            .btn-add-item {
                width: 100% !important;
                padding: 12px 20px !important;
                font-size: 14px !important;
            }

            /* Items table */
            .items-table {
                font-size: 12px !important;
                width: 100% !important;
                display: table !important;
            }

            .items-table th,
            .items-table td {
                padding: 8px 5px !important;
                font-size: 12px !important;
                white-space: nowrap !important;
            }

            /* Stack Unclaimed Freebies and Totals vertically */
            div[style*="grid-template-columns: 2fr 1fr"] {
                display: flex !important;
                flex-direction: column !important;
                gap: 15px !important;
            }

            /* Unclaimed Freebies container */
            .unclaimed-freebies-container {
                width: 100% !important;
                padding: 15px !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }

            .unclaimed-freebies-container .items-table {
                min-width: 100% !important;
            }

            /* Add Unclaimed Freebies button */
            .btn-add-unclaimed-freebies {
                width: 100% !important;
                padding: 10px 20px !important;
                font-size: 14px !important;
                margin-bottom: 12px !important;
            }

            /* Totals section */
            .totals-section {
                width: 100% !important;
            }

            .total-row {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 10px !important;
                padding: 8px 0 !important;
            }

            .total-row label {
                font-size: 14px !important;
                flex-shrink: 0 !important;
            }

            .total-row input {
                font-size: 14px !important;
                padding: 10px !important;
                flex: 1 !important;
                min-width: 120px !important;
            }

            /* Footer actions - stack vertically */
            .footer-actions {
                display: flex !important;
                flex-direction: column !important;
                gap: 15px !important;
                padding: 15px !important;
            }

            .footer-left-group {
                width: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 10px !important;
            }

            .footer-right-group {
                width: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 10px !important;
            }

            /* Points and Commission - horizontal layout */
            .footer-input-group {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 10px !important;
                padding: 10px !important;
                background-color: #f9f9f9 !important;
                border-radius: 4px !important;
            }

            .footer-input-group label {
                font-size: 14px !important;
                font-weight: 600 !important;
                color: #333 !important;
                flex-shrink: 0 !important;
                min-width: 100px !important;
            }

            .footer-input-group input {
                font-size: 14px !important;
                padding: 10px !important;
                flex: 1 !important;
                min-width: 150px !important;
                text-align: right !important;
            }

            /* Footer buttons */
            .footer-right-group button,
            .btn-payment,
            .btn-save,
            .btn-clear,
            .btn-clear-main {
                width: 100% !important;
                padding: 14px 20px !important;
                font-size: 15px !important;
                font-weight: 600 !important;
                margin: 0 !important;
            }
        }

        /* Payment Modal Specific Styles */
        .payment-modal-content {
            max-width: 900px;
            width: 100%;
            align-self: flex-start;
            margin: 5% auto 40px auto;
        }

        .payment-modal-body {
            padding: 20px 25px;
        }

        .payment-top-section {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
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

        .payment-radio-option input[type="radio"],
        .payment-radio-option input[type="checkbox"] {
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

        .payment-radio-option.disabled-payment input[type="radio"],
        .payment-radio-option.disabled-payment input[type="checkbox"] {
            cursor: not-allowed;
        }

        .payment-radio-option.disabled-payment span {
            color: #999;
        }

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

        .hc-form-row {
            display: contents;
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

        .payment-modal-body>div[class$="-section"]:not(.payment-top-section) {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed #ccc;
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

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Sales Trade-In</h2>
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
            <?php endif; ?>
        </div>

        <div class="form-container">
            <form id="salesTradeInForm" method="POST" class="form-split-layout">
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
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name"
                                placeholder="Enter First Name (Optional)"
                                oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" placeholder="Enter Last Name (Optional)"
                                oninput="this.value = this.value.toUpperCase()">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="address">Address</label>
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
                            <input type="email" id="email" name="email" placeholder="Enter Email (Optional)">
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

        <!-- Sales Item Section -->
        <div class="item-selection-container">

            <!-- Trade-In Details Section (Inside Sales Item) -->
            <div
                style="background-color: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e0e0e0;">
                <h4 style="margin-bottom: 15px; color: #333; font-size: 16px; font-weight: 600; text-align: center;">
                    Trade-In Details</h4>
                <div class="form-row" style="grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label>Trade-In Value</label>
                        <input type="text" id="tradein_value" value="0.00" placeholder="0.00" style="text-align: left;">
                    </div>
                    <div class="form-group">
                        <label>IMEI</label>
                        <input type="text" id="tradein_imei" placeholder="Enter IMEI"
                            oninput="this.value = this.value.toUpperCase()">
                    </div>
                </div>
                <div class="form-row" style="grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 0;">
                    <div class="form-group">
                        <label>Item Code</label>
                        <input type="text" id="tradein_item_code" placeholder="Enter Item Code"
                            oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div class="form-group">
                        <label>Brand</label>
                        <select id="tradein_brand">
                            <option value="">Select Brand</option>
                            <?php
                            $brands_result = $conn->query("SELECT brand_name FROM brands WHERE status = 'Active' ORDER BY brand_name");
                            if ($brands_result && $brands_result->num_rows > 0) {
                                while ($brand = $brands_result->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($brand['brand_name']) . '">' . htmlspecialchars($brand['brand_name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 30px; margin-bottom: 20px;">
                <!-- Left Column -->
                <div style="flex: 1; display: flex; flex-direction: column; gap: 15px;">
                    <div class="form-group">
                        <label>Item Code</label>
                        <input type="text" id="sales_item_code" placeholder=""
                            oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div class="form-group">
                        <label>Item Description</label>
                        <input type="text" id="sales_item_desc" placeholder="" readonly
                            style="background-color: #ffffffff; cursor: not-allowed; color: #333;">
                    </div>
                </div>

                <!-- Right Column -->
                <div style="flex: 1; display: flex; flex-direction: column; gap: 15px;">
                    <div class="form-group">
                        <label>IMEI</label>
                        <input type="text" id="sales_imei" placeholder=""
                            oninput="this.value = this.value.toUpperCase()">
                    </div>

                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <div class="form-group" style="flex: 1;">
                            <label>Quantity</label>
                            <input type="number" id="sales_qty" value="0" min="0" style="text-align: left;">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Price</label>
                            <input type="text" id="sales_price" placeholder="" style="text-align: left;">
                        </div>
                        <button type="button" class="btn-search-item" style="margin-bottom: 1px;"
                            onclick="openSalesSearchModal()">Search</button>
                        <button type="button" class="btn-add-item" style="margin-bottom: 1px;"
                            onclick="addSalesItem()">Add</button>
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
                    <tbody id="salesTableBody">
                        <tr id="no-sales-row">
                            <td colspan="5" style="text-align:center; padding: 20px;">No Sales Items Yet</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Unclaimed Freebies and Totals Section -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Unclaimed Freebies Table Container (Left) -->
                <div class="unclaimed-freebies-container"
                    style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #ccc;">
                    <button type="button" class="btn-add-unclaimed-freebies" onclick="addUnclaimedFreebieRow()"
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
                <div class="totals-section">
                    <div class="total-row">
                        <label>Trade-In Qty:</label>
                        <input type="text" id="tradeInQty" value="0" readonly>
                    </div>
                    <div class="total-row">
                        <label>Discount:</label>
                        <input type="text" id="discountField" value="0.00" readonly style="background-color: #e0e0e0;">
                    </div>
                    <div class="total-row">
                        <label>Token:</label>
                        <input type="text" id="tokenField" value="0.00" readonly
                            style="background-color: #e0e0e0; cursor: not-allowed;">
                    </div>
                    <div class="total-row">
                        <label>Trade-In Value:</label>
                        <input type="text" id="tradeInValue" value="0.00" readonly>
                    </div>
                    <div class="total-row">
                        <button type="button" onclick="openTITUVoucherModal()"
                            style="padding: 8px 12px; background-color: var(--color-gold); color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; text-align: center; width: auto; min-width: 120px;">TITU
                            Voucher</button>
                        <input type="text" id="tituVoucherAmount" value="0.00" readonly placeholder="0.00"
                            style="background-color: #e0e0e0;">
                    </div>
                    <div class="total-row">
                        <label>Total:</label>
                        <input type="text" id="totalAmount" value="0.00" readonly style="font-weight: bold;">
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="footer-actions"
                style="width: 100%; justify-content: space-between; padding: 20px; background: white; margin-top: 20px; border-radius: 8px; border: 1px solid #ccc;">
                <div class="footer-left-group">
                    <div class="footer-input-group">
                        <label>Points:</label>
                        <input type="text" id="pointsField" value="0.00" style="background-color: #e0e0e0;" readonly>
                    </div>
                    <div class="footer-input-group">
                        <label>Commission:</label>
                        <input type="text" id="commissionField" value="0.00" style="background-color: #e0e0e0;"
                            readonly>
                    </div>
                </div>
                <div class="footer-right-group">
                    <input type="hidden" name="payment_data" id="payment_data">
                    <button type="button" class="btn-payment" onclick="openPaymentModal()">PAYMENT</button>
                    <button type="button" class="btn-save" onclick="saveSalesTradeIn()">SAVE</button>
                    <button type="button" class="btn-clear-main" onclick="clearMainForm()">CLEAR</button>
                </div>
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
                    style="display:none; margin: 0 0 15px 0; padding: 12px 16px; background-color: #ffffffff; border-left: 4px solid #16a34a; border-right:1px solid #c9c9c9ff; border-top:1px solid #c9c9c9ff; border-bottom:1px solid #c9c9c9ff; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-family: system-ui, -apple-system, sans-serif;">
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

    <!-- Trade-In Search Modal -->
    <div id="searchTradeInModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Search Trade-In Item
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
                    <tbody id="tradeInSearchResultsBody">
                        <!-- Results will be injected here -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-back-modal" onclick="closeTradeInSearchModal()">Back</button>
            </div>
        </div>
    </div>

    <!-- Sales Item Search Modal -->
    <div id="searchSalesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Search Sales Item
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
                    <tbody id="salesSearchResultsBody">
                        <!-- Results will be injected here -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-back-modal" onclick="closeSalesSearchModal()">Back</button>
            </div>
        </div>
    </div>

    <!-- Trade-In Item Code Search Modal -->
    <div id="searchSalesModalTrade" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Search Trade-In Sales Item
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
                    <tbody id="tradeInItemSearchResultsBody">
                        <!-- Results will be injected here -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-back-modal" onclick="closeTradeInSalesSearchModal()">Back</button>
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

    <!-- TITU Voucher Modal -->
    <div id="tituVoucherModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                TITU Voucher
            </div>
            <div class="modal-body" style="padding: 30px;">
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <!-- 2x2 Grid for first 4 fields -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>TITU Control:</label>
                            <input type="text" id="tituControl" placeholder="Enter TITU Control"
                                style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                        </div>
                        <div class="form-group">
                            <label>TITU Token:</label>
                            <input type="text" id="tituToken" placeholder="Enter TITU Token"
                                style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                        </div>
                        <div class="form-group">
                            <label>Cross Sell:</label>
                            <input type="number" id="crossSell" value="0.00" min="0" step="0.01" placeholder="0.00"
                                style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%; text-align: right;">
                        </div>
                        <div class="form-group">
                            <label>Trade-In Voucher:</label>
                            <input type="number" id="tradeInVoucher" value="0.00" min="0" step="0.01" placeholder="0.00"
                                style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%; text-align: right;">
                        </div>
                    </div>

                    <!-- Total on separate row -->
                    <div class="form-group">
                        <label style="font-weight: 700;">Total:</label>
                        <input type="text" id="tituVoucherTotal" value="0.00" readonly
                            style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%; text-align: right; background-color: #f5f5f5; font-weight: 700;">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: space-between;">
                <button type="button" class="btn-back-modal" onclick="closeTITUVoucherModal()">Cancel</button>
                <button type="button" class="btn-save-modal" onclick="applyTITUVoucher()"
                    style="background-color: var(--color-gold); border: none; padding: 10px 40px; border-radius: 4px; color: white; cursor: pointer; font-weight: 600;">Apply</button>
            </div>
        </div>
    </div>

    <script>
        // Number Formatting Utilities
        function formatNumber(num) {
            if (!num && num !== 0) return '';
            const number = parseFloat(num);
            if (isNaN(number)) return '';
            return number.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function parseNumber(str) {
            if (!str) return 0;
            return parseFloat(str.toString().replace(/,/g, '')) || 0;
        }

        // Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn = document.querySelector('.menu-btn');

            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
            menuBtn.classList.toggle('active');
        }

        // IMEI Search Function for Sales Items
        function searchByIMEI(imei) {
            // Check for duplicate IMEI in the table before searching
            if (imei && imei.trim() !== '') {
                const salesTableBody = document.getElementById('salesTableBody');
                const existingRows = salesTableBody.querySelectorAll('tr');
                for (let row of existingRows) {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 2) {
                        const existingSerial = cells[1].textContent.trim();
                        if (existingSerial === imei.trim()) {
                            alert('This IMEI (' + imei + ') has already been added to this sales entry!');
                            // Clear the IMEI field
                            document.getElementById('sales_imei').value = '';
                            return;
                        }
                    }
                }
            }

            fetch(`search_imei.php?imei=${encodeURIComponent(imei)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const salesItemCodeInput = document.getElementById('sales_item_code');
                        salesItemCodeInput.value = data.data.item_code || '';
                        document.getElementById('sales_item_desc').value = data.data.description;
                        document.getElementById('sales_price').value = formatNumber(data.data.price);

                        // Set quantity to 1 automatically for serialized items
                        document.getElementById('sales_qty').value = '1';

                        // -- Fetch item prices so Bank/Terms dropdowns populate correctly --
                        if (data.data.item_code) {
                            const ic = data.data.item_code;
                            fetch(`search_item.php?term=${encodeURIComponent(ic)}`)
                                .then(r => r.json())
                                .then(sd => {
                                    if (sd.status === 'success' && sd.data.length > 0) {
                                        const match = sd.data.find(it => it.item_code === ic) || sd.data[0];
                                        salesItemCodeInput.setAttribute('data-prices', JSON.stringify(match.prices || {}));
                                        salesItemCodeInput.setAttribute('data-others-bank-enabled', match.others_bank_enabled ? '1' : '0');
                                        window.itemPricesCache = window.itemPricesCache || {};
                                        window.itemPricesCache[ic] = {
                                            prices: match.prices || {},
                                            othersBankEnabled: match.others_bank_enabled || false
                                        };
                                        document.querySelectorAll('#salesTableBody tr').forEach(r => {
                                            if (r.getAttribute('data-item-code') === ic) {
                                                r.setAttribute('data-prices', JSON.stringify(match.prices || {}));
                                                r.setAttribute('data-others-bank-enabled', match.others_bank_enabled ? '1' : '0');
                                            }
                                        });
                                        if (typeof updateCardPaymentDropdowns === 'function') {
                                            updateCardPaymentDropdowns();
                                        }
                                    }
                                })
                                .catch(() => { });
                        }

                        // Fetch and cache commission, points, voucher, and token for this item
                        if (data.data.item_code) {
                            fetch(`check_item_details.php?item_code=${encodeURIComponent(data.data.item_code)}`)
                                .then(r => r.json())
                                .then(det => {
                                    if (det.status === 'success') {
                                        salesItemCodeInput.setAttribute('data-commission', det.commission || 0);
                                        salesItemCodeInput.setAttribute('data-has-commission', det.has_commission || 0);
                                        salesItemCodeInput.setAttribute('data-points', det.points || 0);
                                        salesItemCodeInput.setAttribute('data-has-points', det.has_points || 0);
                                        salesItemCodeInput.setAttribute('data-has-voucher', det.has_voucher || 0);
                                        salesItemCodeInput.setAttribute('data-voucher-amount', det.voucher_amount || 0);
                                        salesItemCodeInput.setAttribute('data-has-token', det.has_token || 0);
                                        salesItemCodeInput.setAttribute('data-token-amount', det.token_amount || 0);
                                    }
                                })
                                .catch(() => { });

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
                                            discountField.value = '0.00';
                                        }
                                    }
                                })
                                .catch(error => {
                                    console.error('Error checking discount permission:', error);
                                });
                        }

                        // Focus on the Add button or next field
                        console.log('IMEI found and fields populated');
                    } else {
                        alert(data.message || 'IMEI not found');
                        document.getElementById('sales_item_code').value = '';
                        document.getElementById('sales_item_desc').value = '';
                        document.getElementById('sales_price').value = '';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while searching for the IMEI.');
                });
        }

        // Add event listener for Sales IMEI field (Enter key)
        document.addEventListener('DOMContentLoaded', function () {
            const salesImeiInput = document.getElementById('sales_imei');
            if (salesImeiInput) {
                salesImeiInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        const imei = salesImeiInput.value.trim();
                        if (imei !== '') {
                            searchByIMEI(imei);
                        }
                    }
                });
            }

            // Add event listener for Sales Item Code field (Enter key)
            const salesItemCodeInput = document.getElementById('sales_item_code');
            if (salesItemCodeInput) {
                salesItemCodeInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        performSalesItemSearch();
                    }
                });
            }

            // Add event listener for Trade-In Item Code field (Enter key)
            const tradeInItemCodeInput = document.getElementById('tradein_item_code');
            if (tradeInItemCodeInput) {
                tradeInItemCodeInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        performTradeInItemSearch();
                    }
                });
            }
        });

        // Store current search results
        let currentSalesSearchResults = [];

        // Perform Sales Item Search
        function performSalesItemSearch() {
            const searchTerm = document.getElementById('sales_item_code').value.trim();
            const imei = document.getElementById('sales_imei').value.trim();

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
                    const resultsBody = document.getElementById('salesSearchResultsBody');
                    resultsBody.innerHTML = '';

                    if (data.status === 'success' && data.data.length > 0) {
                        currentSalesSearchResults = data.data; // Store results
                        data.data.forEach((item, index) => {
                            const row = `
                                <tr>
                                    <td>${item.item_code}</td>
                                    <td>${item.description}</td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-select" onclick="selectSalesItem(${index})">Select</button>
                                    </td>
                                </tr>
                            `;
                            resultsBody.insertAdjacentHTML('beforeend', row);
                        });
                    } else {
                        resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">No items found</td></tr>';
                    }
                    document.getElementById('searchSalesModal').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while searching.');
                });
        }

        // Select Sales Item from Search Results
        function selectSalesItem(index) {
            const item = currentSalesSearchResults[index];
            if (!item) return;

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

            // Check if item is serialized
            if (code) {
                fetch(`check_serial_permission.php?item_code=${encodeURIComponent(code)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success' && data.has_serial) {
                            // Item is serialized - show alert and clear fields
                            alert('This item is serialized. Please enter the IMEI first.');
                            document.getElementById('sales_item_code').value = '';
                            document.getElementById('sales_item_desc').value = '';
                            document.getElementById('sales_price').value = '';
                            const imeiField = document.getElementById('sales_imei');
                            imeiField.value = '';
                            // Make IMEI field editable after alert (for manual IMEI entry)
                            imeiField.removeAttribute('readonly');
                            imeiField.style.backgroundColor = '#ffffff';
                            imeiField.style.cursor = 'text';
                            imeiField.focus();

                            closeSalesSearchModal();
                            return;
                        }

                        // Item is not serialized - proceed normally
                        const salesItemCodeInput = document.getElementById('sales_item_code');
                        salesItemCodeInput.value = code;
                        document.getElementById('sales_item_desc').value = description;
                        document.getElementById('sales_price').value = formatNumber(price);

                        // Store commission, points, voucher and token data
                        salesItemCodeInput.setAttribute('data-commission', commission || 0);
                        salesItemCodeInput.setAttribute('data-has-commission', has_commission || 0);
                        salesItemCodeInput.setAttribute('data-points', points || 0);
                        salesItemCodeInput.setAttribute('data-has-points', has_points || 0);
                        salesItemCodeInput.setAttribute('data-has-voucher', has_voucher || 0);
                        salesItemCodeInput.setAttribute('data-voucher-amount', voucher_amount || 0);
                        salesItemCodeInput.setAttribute('data-has-token', has_token || 0);
                        salesItemCodeInput.setAttribute('data-token-amount', token_amount || 0);
                        salesItemCodeInput.setAttribute('data-prices', JSON.stringify(item.prices || {}));
                        salesItemCodeInput.setAttribute('data-others-bank-enabled', item.others_bank_enabled ? '1' : '0');

                        window.itemPricesCache = window.itemPricesCache || {};
                        window.itemPricesCache[code] = {
                            prices: item.prices || {},
                            othersBankEnabled: item.others_bank_enabled || false
                        };

                        if (typeof updateCardPaymentDropdowns === 'function') {
                            updateCardPaymentDropdowns();
                        }

                        // Lock IMEI field for non-serialized items
                        const imeiField = document.getElementById('sales_imei');
                        imeiField.setAttribute('readonly', 'readonly');
                        imeiField.style.backgroundColor = '#f5f5f5';
                        imeiField.style.cursor = 'not-allowed';
                        imeiField.value = '';

                        // Check discount permission
                        fetch(`check_discount_permission.php?item_code=${encodeURIComponent(code)}`)
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
                                        discountField.value = '0.00';
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error checking discount permission:', error);
                            });

                        closeSalesSearchModal();
                    })
                    .catch(error => {
                        console.error('Error checking serial status:', error);
                    });
            }
        }

        // Modal Functions
        function openTradeInSearchModal() {
            // Trade-In search modal removed - not needed without table
            alert('Trade-In search functionality removed');
        }

        function closeTradeInSearchModal() {
            document.getElementById('searchTradeInModal').style.display = 'none';
        }

        function openSalesSearchModal() {
            performSalesItemSearch();
        }

        function closeSalesSearchModal() {
            document.getElementById('searchSalesModal').style.display = 'none';
        }

        // Store Trade-In Item search results
        let currentTradeInItemSearchResults = [];

        // Perform Trade-In Item Search
        function performTradeInItemSearch() {
            const searchTerm = document.getElementById('tradein_item_code').value.trim();

            if (searchTerm === '') {
                alert('Please input Trade-In Item Code!');
                return;
            }

            // Fetch results
            fetch(`search_item.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    const resultsBody = document.getElementById('tradeInItemSearchResultsBody');
                    resultsBody.innerHTML = '';

                    if (data.status === 'success' && data.data.length > 0) {
                        currentTradeInItemSearchResults = data.data; // Store results
                        data.data.forEach((item, index) => {
                            const row = `
                                <tr>
                                    <td>${item.item_code}</td>
                                    <td>${item.description}</td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-select" onclick="selectTradeInSalesItem(${index})">Select</button>
                                    </td>
                                </tr>
                            `;
                            resultsBody.insertAdjacentHTML('beforeend', row);
                        });
                    } else {
                        resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">No items found</td></tr>';
                    }
                    document.getElementById('searchSalesModalTrade').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while searching.');
                });
        }

        // Select Trade-In Item from Search Results
        function selectTradeInSalesItem(index) {
            const item = currentTradeInItemSearchResults[index];
            if (!item) return;

            document.getElementById('tradein_item_code').value = item.item_code || '';
            closeTradeInSalesSearchModal();
        }

        function openTradeInSalesSearchModal() {
            performTradeInItemSearch();
        }

        function closeTradeInSalesSearchModal() {
            const modal = document.getElementById('searchSalesModalTrade');
            if (modal) modal.style.display = 'none';
        }

        // Terminal IDs dataset from PHP (filtered by current branch)
        const allTerminalIds = <?php echo json_encode($terminal_ids_for_js); ?>;

        // Stores the original total amount due when the payment modal opens
        let _originalTotalAmountDue = 0;

        /**
         * Filter Terminal ID dropdown based on selected Terminal Issuer.
         */
        function filterTerminalIds(section) {
            const issuerSelect = document.getElementById(section === 'cc' ? 'ccTerminalIssuer' : 'dcTerminalIssuer');
            const terminalSelect = document.getElementById(section === 'cc' ? 'ccTerminalId' : 'dcTerminalId');
            if (!issuerSelect || !terminalSelect) return;

            const selectedIssuer = issuerSelect.value;
            terminalSelect.innerHTML = '<option value="">Select Terminal ID</option>';
            filterBanksByTerminalId(section, null);
            if (!selectedIssuer) return;

            const filtered = allTerminalIds.filter(tid => {
                const issuers = tid.terminal_issuer.split(',').map(s => s.trim());
                return issuers.some(iss => iss === selectedIssuer || iss.startsWith(selectedIssuer));
            });

            filtered.forEach(tid => {
                const opt = document.createElement('option');
                opt.value = tid.terminal_id;
                opt.textContent = tid.terminal_id;
                opt.setAttribute('data-issuers', tid.terminal_issuer);
                terminalSelect.appendChild(opt);
            });

            const newSelect = terminalSelect.cloneNode(true);
            terminalSelect.parentNode.replaceChild(newSelect, terminalSelect);
            newSelect.addEventListener('change', function () {
                const selectedOpt = this.options[this.selectedIndex];
                const issuers = selectedOpt ? selectedOpt.getAttribute('data-issuers') : null;
                filterBanksByTerminalId(section, issuers);
            });
        }

        /**
         * Populate Bank dropdown.
         */
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
                const rows = document.querySelectorAll('#salesTableBody tr:not(#no-sales-row), #itemsTableBody tr:not(#no-items-row)');
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

            // Collect all banks from currentPrices
            const banks = new Set();

            if (Object.keys(currentPrices).length > 0) {
                for (const key in currentPrices) {
                    if (key === '__SRP__' || key === 'SRP') continue;
                    const spaceIndex = key.indexOf(' ');
                    if (spaceIndex !== -1) {
                        const bankName = key.substring(0, spaceIndex);
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
                    echo "];\n";
                    echo "                otherBanks.forEach(bank => banks.add(bank));";
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
                filterBanksByTerminalId('cc');
                filterBanksByTerminalId('dc');
                return;
            }

            // Multiple active sections
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

            filterBanksByTerminalId('cc');
            filterBanksByTerminalId('dc');
        }
        window.syncUnitSelectorsAcrossSections = syncUnitSelectorsAcrossSections;

        /**
         * Populate the Unit selector inside Home Credit / Card / QR payment sections.
         */
        function populateInstallmentUnit() {
            const tbody = document.getElementById('salesTableBody') || document.getElementById('itemsTableBody');
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.id || (r.id !== 'no-sales-row' && r.id !== 'no-items-row'));
            const unitRows = document.querySelectorAll('.unit-selector-row');

            if (unitRows.length === 0) return;

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

                            const isOtherBank = otherBanksList.includes(selectedBank);

                            if (isOtherBank) {
                                const standardTerms = ['3 Months', '6 Months', '12 Months', '24 Months'];
                                standardTerms.forEach(term => {
                                    const option = document.createElement('option');
                                    option.value = term;
                                    option.textContent = term;
                                    option.setAttribute('data-is-other-bank', 'true');
                                    termsDropdown.appendChild(option);
                                });

                                if (previousTerm && standardTerms.includes(previousTerm)) {
                                    termsDropdown.value = previousTerm;
                                }

                                if (amountInput) {
                                    amountInput.removeAttribute('readonly');
                                    amountInput.placeholder = 'Enter amount';
                                }
                            } else {
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

                        if (this.value) {
                            this.setAttribute('data-selected-value', this.value);
                        }

                        const unitInfo = getSelectedUnitPrices(pair.sectionCode);
                        const currentPrices = unitInfo.prices;

                        if (isOtherBank) {
                            if (amountInput) {
                                amountInput.removeAttribute('readonly');
                                amountInput.placeholder = 'Enter amount';
                                amountInput.focus();
                            }
                        } else if (fullKey && currentPrices[fullKey] !== undefined) {
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
                        }
                    });
                }
            });
        });

        function openPaymentModal() {
            const modal = document.getElementById('paymentModal');
            if (!modal) return;
            modal.style.display = 'block';
            const banner = document.getElementById('paymentErrorBanner');
            if (banner) { banner.style.display = 'none'; banner.innerHTML = ''; }
            const totalAmountInput = document.getElementById('totalAmount');
            const globalTotalDueInput = document.getElementById('globalTotalDueInput');
            if (totalAmountInput && globalTotalDueInput) {
                const rawValue = totalAmountInput.value || '0';
                _originalTotalAmountDue = parseFloat(rawValue.replace(/,/g, '')) || 0;
                globalTotalDueInput.value = totalAmountInput.value || '0.00';
            }
            populateInstallmentUnit();
            if (typeof window.updateSectionTotal === 'function') {
                window.updateSectionTotal();
            }
        }

        function closePaymentModal() {
            const modal = document.getElementById('paymentModal');
            if (modal) modal.style.display = 'none';
        }

        // Add Sales Item
        function addSalesItem() {
            // Check if Assisted By is selected
            const assistedBy = document.getElementById('assisted_by').value;
            if (!assistedBy || assistedBy === '') {
                alert('Please select Assisted By before adding items.');
                document.getElementById('assisted_by').focus();
                return;
            }

            const salesItemCodeInput = document.getElementById('sales_item_code');
            const itemCode = salesItemCodeInput.value;
            const itemDesc = document.getElementById('sales_item_desc').value;
            const imei = document.getElementById('sales_imei').value;
            const qty = document.getElementById('sales_qty').value;
            const price = document.getElementById('sales_price').value;

            if (!itemCode || !itemDesc) {
                alert('Please fill in item details');
                return;
            }

            // Get commission, points, voucher, token data from input
            const commission = parseFloat(salesItemCodeInput.getAttribute('data-commission')) || 0;
            const has_commission = parseInt(salesItemCodeInput.getAttribute('data-has-commission')) || 0;
            const points = parseFloat(salesItemCodeInput.getAttribute('data-points')) || 0;
            const has_points = parseInt(salesItemCodeInput.getAttribute('data-has-points')) || 0;
            const has_voucher = parseInt(salesItemCodeInput.getAttribute('data-has-voucher')) || 0;
            const voucher_amount = parseFloat(salesItemCodeInput.getAttribute('data-voucher-amount')) || 0;
            const has_token = parseInt(salesItemCodeInput.getAttribute('data-has-token')) || 0;
            const token_amount = parseFloat(salesItemCodeInput.getAttribute('data-token-amount')) || 0;
            const pricesStr = salesItemCodeInput.getAttribute('data-prices') || '{}';
            const othersBankStr = salesItemCodeInput.getAttribute('data-others-bank-enabled') || '0';
            let pricesObj = {};
            try { pricesObj = JSON.parse(pricesStr); } catch (e) { }

            const tbody = document.getElementById('salesTableBody');
            const noSalesRow = document.getElementById('no-sales-row');

            if (noSalesRow) {
                noSalesRow.remove();
            }

            const row = tbody.insertRow();
            row.setAttribute('data-item-code', itemCode);
            row.setAttribute('data-item-desc', itemDesc);
            row.setAttribute('data-commission', commission);
            row.setAttribute('data-has-commission', has_commission);
            row.setAttribute('data-points', points);
            row.setAttribute('data-has-points', has_points);
            row.setAttribute('data-has-voucher', has_voucher);
            row.setAttribute('data-voucher-amount', voucher_amount);
            row.setAttribute('data-has-token', has_token);
            row.setAttribute('data-token-amount', token_amount);
            row.setAttribute('data-prices', JSON.stringify(pricesObj));
            row.setAttribute('data-others-bank-enabled', othersBankStr);

            row.innerHTML = `
                <td>${itemDesc}</td>
                <td>${imei}</td>
                <td>${qty}</td>
                <td>${formatNumber(parseNumber(price))}</td>
                <td><button class="btn-delete-item" onclick="deleteSalesRow(this)">×</button></td>
            `;

            // Clear inputs
            salesItemCodeInput.value = '';
            salesItemCodeInput.removeAttribute('data-commission');
            salesItemCodeInput.removeAttribute('data-has-commission');
            salesItemCodeInput.removeAttribute('data-points');
            salesItemCodeInput.removeAttribute('data-has-points');
            salesItemCodeInput.removeAttribute('data-has-voucher');
            salesItemCodeInput.removeAttribute('data-voucher-amount');
            salesItemCodeInput.removeAttribute('data-has-token');
            salesItemCodeInput.removeAttribute('data-token-amount');
            salesItemCodeInput.removeAttribute('data-prices');
            salesItemCodeInput.removeAttribute('data-others-bank-enabled');

            document.getElementById('sales_item_desc').value = '';
            const salesImei = document.getElementById('sales_imei');
            salesImei.value = '';
            salesImei.removeAttribute('readonly');
            salesImei.style.backgroundColor = '#ffffff';
            salesImei.style.cursor = 'text';

            document.getElementById('sales_qty').value = '0';
            document.getElementById('sales_price').value = '';

            calculateTotals();
        }

        // Delete Row Functions
        function deleteSalesRow(btn) {
            const row = btn.closest('tr');
            row.remove();

            const tbody = document.getElementById('salesTableBody');
            if (tbody.rows.length === 0) {
                tbody.innerHTML = '<tr id="no-sales-row"><td colspan="5" style="text-align:center; padding: 20px;">No Sales Items Yet</td></tr>';
            }

            calculateTotals();
        }

        // Calculate Totals
        function calculateTotals() {
            // Get trade-in value from input field - use parseNumber to handle commas
            const tradeInValue = parseNumber(document.getElementById('tradein_value').value);
            const tradeInQty = tradeInValue > 0 ? 1 : 0; // If there's a value, count as 1 item

            let salesTotal = 0;
            let totalCommission = 0;
            let totalPoints = 0;
            let totalToken = 0;
            let hasAnyCommission = false;
            let hasAnyPoints = false;

            // Calculate Sales Total and item attributes
            const salesRows = document.querySelectorAll('#salesTableBody tr:not(#no-sales-row)');
            salesRows.forEach(row => {
                const qty = parseFloat(row.cells[2].textContent) || 0;
                const price = parseNumber(row.cells[3].textContent);
                salesTotal += qty * price;

                const commission = parseFloat(row.getAttribute('data-commission')) || 0;
                const has_commission = parseInt(row.getAttribute('data-has-commission')) || 0;
                const points = parseFloat(row.getAttribute('data-points')) || 0;
                const has_points = parseInt(row.getAttribute('data-has-points')) || 0;
                const has_token = parseInt(row.getAttribute('data-has-token')) || 0;
                const token_amount = parseFloat(row.getAttribute('data-token-amount')) || 0;

                if (has_commission === 1) {
                    totalCommission += commission;
                    hasAnyCommission = true;
                }

                if (has_points === 1) {
                    totalPoints += points;
                    hasAnyPoints = true;
                }

                if (has_token === 1) {
                    totalToken += token_amount;
                }
            });

            // Update token field
            const tokenField = document.getElementById('tokenField');
            if (tokenField) {
                tokenField.value = formatNumber(totalToken);
                if (totalToken > 0) {
                    tokenField.style.backgroundColor = '#ffffff';
                } else {
                    tokenField.style.backgroundColor = '#e0e0e0';
                }
            }

            // Update commission and points fields
            const commissionField = document.getElementById('commissionField');
            const pointsField = document.getElementById('pointsField');
            if (commissionField) {
                commissionField.value = hasAnyCommission ? formatNumber(totalCommission) : '0.00';
                commissionField.style.backgroundColor = hasAnyCommission ? '#ffffff' : '#e0e0e0';
            }
            if (pointsField) {
                pointsField.value = hasAnyPoints ? formatNumber(totalPoints) : '0.00';
                pointsField.style.backgroundColor = hasAnyPoints ? '#ffffff' : '#e0e0e0';
            }

            const discount = parseNumber(document.getElementById('discountField').value);
            const tituVoucher = parseNumber(document.getElementById('tituVoucherAmount').value);
            let total = salesTotal - tradeInValue - discount - totalToken - tituVoucher;

            // Don't allow negative total - set to 0 if result is negative
            if (total < 0) {
                total = 0;
            }

            document.getElementById('tradeInQty').value = tradeInQty;
            document.getElementById('tradeInValue').value = formatNumber(tradeInValue);
            document.getElementById('totalAmount').value = formatNumber(total);
        }

        // Update trade-in value field to trigger calculation
        document.addEventListener('DOMContentLoaded', function () {
            const tradeInValueInput = document.getElementById('tradein_value');
            if (tradeInValueInput) {
                // Add input event for calculation
                tradeInValueInput.addEventListener('input', calculateTotals);

                // Add formatting on blur
                tradeInValueInput.addEventListener('blur', function () {
                    const value = parseNumber(this.value);
                    this.value = formatNumber(value);
                });

                // Add focus event to remove formatting for easier editing
                tradeInValueInput.addEventListener('focus', function () {
                    const value = parseNumber(this.value);
                    this.value = value === 0 ? '' : value.toString();
                });
            }

            // Add formatting for price field
            const salesPriceInput = document.getElementById('sales_price');
            if (salesPriceInput) {
                salesPriceInput.addEventListener('blur', function () {
                    const value = parseNumber(this.value);
                    if (value > 0) {
                        this.value = formatNumber(value);
                    }
                });

                salesPriceInput.addEventListener('focus', function () {
                    // Remove formatting when focused for easier editing
                    const value = parseNumber(this.value);
                    if (value > 0) {
                        this.value = value;
                    }
                });
            }

            // Add formatting and calculation for discount field
            const discountFieldInput = document.getElementById('discountField');
            if (discountFieldInput) {
                discountFieldInput.addEventListener('input', calculateTotals);
                discountFieldInput.addEventListener('blur', function () {
                    const value = parseNumber(this.value);
                    this.value = formatNumber(value);
                    calculateTotals();
                });
                discountFieldInput.addEventListener('focus', function () {
                    const value = parseNumber(this.value);
                    this.value = value === 0 ? '' : value.toString();
                });
            }
        });

        // TITU Voucher Modal Functions
        function openTITUVoucherModal() {
            document.getElementById('tituVoucherModal').style.display = 'flex';
            calculateTITUVoucherTotal();
        }

        function closeTITUVoucherModal() {
            document.getElementById('tituVoucherModal').style.display = 'none';
        }

        function calculateTITUVoucherTotal() {
            const crossSell = parseFloat(document.getElementById('crossSell').value) || 0;
            const tradeInVoucher = parseFloat(document.getElementById('tradeInVoucher').value) || 0;
            const total = crossSell + tradeInVoucher;
            document.getElementById('tituVoucherTotal').value = formatNumber(total);
        }

        function applyTITUVoucher() {
            const total = parseNumber(document.getElementById('tituVoucherTotal').value);
            document.getElementById('tituVoucherAmount').value = formatNumber(total);
            closeTITUVoucherModal();
            calculateTotals();
        }

        // Add event listeners for TITU Voucher calculation
        document.addEventListener('DOMContentLoaded', function () {
            const crossSellInput = document.getElementById('crossSell');
            const tradeInVoucherInput = document.getElementById('tradeInVoucher');

            if (crossSellInput) {
                crossSellInput.addEventListener('input', calculateTITUVoucherTotal);
            }
            if (tradeInVoucherInput) {
                tradeInVoucherInput.addEventListener('input', calculateTITUVoucherTotal);
            }
        });

        // Clear Form
        function clearMainForm() {
            if (!confirm('Are you sure you want to clear all data?')) {
                return;
            }

            // Clear customer info
            document.getElementById('first_name').value = '';
            document.getElementById('last_name').value = '';
            document.getElementById('address').value = '';
            document.getElementById('contact_no').value = '';
            document.getElementById('email').value = '';
            document.getElementById('remarks').value = '';
            document.getElementById('assisted_by').value = '';

            // Clear trade-in inputs
            document.getElementById('tradein_value').value = '0';
            document.getElementById('tradein_imei').value = '';
            document.getElementById('tradein_item_code').value = '';
            document.getElementById('tradein_brand').value = '';

            // Clear sales inputs
            const salesItemCode = document.getElementById('sales_item_code');
            salesItemCode.value = '';
            salesItemCode.removeAttribute('data-commission');
            salesItemCode.removeAttribute('data-has-commission');
            salesItemCode.removeAttribute('data-points');
            salesItemCode.removeAttribute('data-has-points');
            salesItemCode.removeAttribute('data-has-voucher');
            salesItemCode.removeAttribute('data-voucher-amount');
            salesItemCode.removeAttribute('data-has-token');
            salesItemCode.removeAttribute('data-token-amount');

            document.getElementById('sales_item_desc').value = '';
            const salesImei = document.getElementById('sales_imei');
            salesImei.value = '';
            salesImei.removeAttribute('readonly');
            salesImei.style.backgroundColor = '#ffffff';
            salesImei.style.cursor = 'text';

            document.getElementById('sales_qty').value = '0';
            document.getElementById('sales_price').value = '';

            // Clear sales table
            document.getElementById('salesTableBody').innerHTML = '<tr id="no-sales-row"><td colspan="5" style="text-align:center; padding: 20px;">No Sales Items Yet</td></tr>';

            // Reset totals
            document.getElementById('tradeInQty').value = '0';
            document.getElementById('tradeInValue').value = '0.00';
            const discountField = document.getElementById('discountField');
            if (discountField) {
                discountField.value = '0.00';
                discountField.setAttribute('readonly', 'readonly');
                discountField.style.backgroundColor = '#e0e0e0';
                discountField.style.cursor = 'not-allowed';
            }
            const tokenField = document.getElementById('tokenField');
            if (tokenField) {
                tokenField.value = '0.00';
                tokenField.style.backgroundColor = '#e0e0e0';
            }
            document.getElementById('tituVoucherAmount').value = '0.00';
            document.getElementById('totalAmount').value = '0.00';
            const pointsField = document.getElementById('pointsField');
            if (pointsField) {
                pointsField.value = '0.00';
                pointsField.style.backgroundColor = '#e0e0e0';
            }
            const commissionField = document.getElementById('commissionField');
            if (commissionField) {
                commissionField.value = '0.00';
                commissionField.style.backgroundColor = '#e0e0e0';
            }

            // Clear TITU Voucher modal fields
            document.getElementById('tituControl').value = '';
            document.getElementById('tituToken').value = '';
            document.getElementById('crossSell').value = '0.00';
            document.getElementById('tradeInVoucher').value = '0.00';
            document.getElementById('tituVoucherTotal').value = '0.00';

            // Clear payment data & reset payment button
            const paymentDataInput = document.getElementById('payment_data');
            if (paymentDataInput) paymentDataInput.value = '';
            const btnPayment = document.querySelector('.btn-payment');
            if (btnPayment) {
                btnPayment.innerText = 'PAYMENT';
                btnPayment.style.backgroundColor = '';
                btnPayment.style.color = '';
            }

            // Clear unclaimed freebies table
            document.getElementById('unclaimedFreebiesTableBody').innerHTML = '<tr id="no-unclaimed-freebies-row"><td colspan="4" style="text-align:center; padding: 20px;">Please click Add Unclaimed Freebies</td></tr>';
        }

        // Unclaimed Freebies Functions
        let unclaimedFreebieRowCounter = 0;
        let currentUnclaimedFreebieRow = null;

        function addUnclaimedFreebieRow() {
            const unclaimedFreebiesTableBody = document.getElementById('unclaimedFreebiesTableBody');

            // Remove "no unclaimed freebies" row if it exists
            const noUnclaimedFreebiesRow = document.getElementById('no-unclaimed-freebies-row');
            if (noUnclaimedFreebiesRow) {
                noUnclaimedFreebiesRow.remove();
            }

            unclaimedFreebieRowCounter++;
            const newRow = document.createElement('tr');
            newRow.dataset.rowId = unclaimedFreebieRowCounter;
            newRow.innerHTML = `
                <td>
                    <div style="display: flex; gap: 5px; align-items: center;">
                        <input type="text" class="unclaimed-freebie-name-input" placeholder="Enter item code" 
                               style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" readonly>
                        <button type="button" class="btn-search-unclaimed-freebie" 
                                style="background-color: var(--color-navy); color: white; border: none; border-radius: 4px; padding: 8px 15px; cursor: pointer; white-space: nowrap; font-size: 12px;"
                                onclick="openUnclaimedFreebieSearchModal(${unclaimedFreebieRowCounter})">Search</button>
                    </div>
                </td>
                <td>
                    <input type="number" class="unclaimed-freebie-qty-input" value="1" min="1" 
                           style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; text-align: center;">
                </td>
                <td>
                    <input type="text" class="unclaimed-freebie-note-input" placeholder="Optional note" 
                           style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                </td>
                <td style="text-align: center;">
                    <button class="btn-delete-item" onclick="deleteUnclaimedFreebieRow(this)">×</button>
                </td>
            `;
            unclaimedFreebiesTableBody.appendChild(newRow);
        }

        function deleteUnclaimedFreebieRow(btn) {
            const row = btn.closest('tr');
            row.remove();

            const tbody = document.getElementById('unclaimedFreebiesTableBody');
            if (tbody.querySelectorAll('tr').length === 0) {
                const placeholderRow = document.createElement('tr');
                placeholderRow.id = 'no-unclaimed-freebies-row';
                placeholderRow.innerHTML = '<td colspan="4" style="text-align:center; padding: 20px;">Please click Add Unclaimed Freebies</td>';
                tbody.appendChild(placeholderRow);
            }
        }

        function openUnclaimedFreebieSearchModal(rowId) {
            currentUnclaimedFreebieRow = rowId;
            document.getElementById('searchUnclaimedFreebieModal').style.display = 'flex';
            document.getElementById('unclaimedFreebieSearchInput').value = '';
            document.getElementById('unclaimedFreebieSearchResultsBody').innerHTML = '';
        }

        function closeUnclaimedFreebieSearchModal() {
            document.getElementById('searchUnclaimedFreebieModal').style.display = 'none';
            currentUnclaimedFreebieRow = null;
        }

        function selectUnclaimedFreebie(itemCode, itemDesc) {
            if (currentUnclaimedFreebieRow) {
                const row = document.querySelector(`tr[data-row-id="${currentUnclaimedFreebieRow}"]`);
                if (row) {
                    const nameInput = row.querySelector('.unclaimed-freebie-name-input');
                    if (nameInput) {
                        nameInput.value = itemDesc;
                        nameInput.dataset.itemCode = itemCode;
                    }
                }
            }
            closeUnclaimedFreebieSearchModal();
        }

        // Save Function
        function saveSalesTradeIn() {
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

            // Validate required field: Assisted By
            const assistedBy = document.getElementById('assisted_by').value;
            if (!assistedBy) {
                alert('Assisted By is required!');
                document.getElementById('assisted_by').focus();
                return;
            }

            // Helper to parse numeric strings cleanly
            function parseFormattedNum(str) {
                if (!str) return 0;
                return parseFloat(str.toString().replace(/,/g, '')) || 0;
            }

            // Collect items from sales table
            const salesTableBody = document.getElementById('salesTableBody');
            const itemRows = salesTableBody.querySelectorAll('tr:not(#no-sales-row)');
            if (itemRows.length === 0) {
                alert('Please add at least one sales item!');
                return;
            }

            const items = [];
            itemRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 4) {
                    const itemCode = row.getAttribute('data-item-code') || '';
                    items.push({
                        description: cells[0].textContent.trim(),
                        imei: cells[1].textContent.trim(),
                        quantity: parseInt(cells[2].textContent.trim()) || 1,
                        price: parseFormattedNum(cells[3].textContent.trim()),
                        item_code: itemCode
                    });
                }
            });

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

                // Trade-In Details
                tradein_value: parseFormattedNum(document.getElementById('tradein_value').value),
                tradein_imei: document.getElementById('tradein_imei').value.trim(),
                tradein_item_code: document.getElementById('tradein_item_code').value.trim(),
                tradein_brand: document.getElementById('tradein_brand').value.trim(),

                // TITU Voucher Modal Details
                titu_control: document.getElementById('tituControl').value.trim(),
                titu_token: document.getElementById('tituToken').value.trim(),
                cross_sell: parseFormattedNum(document.getElementById('crossSell').value),
                trade_in_voucher: parseFormattedNum(document.getElementById('tradeInVoucher').value),
                titu_voucher_total: parseFormattedNum(document.getElementById('tituVoucherTotal').value),

                // Form Totals
                total_qty: parseInt(document.getElementById('tradeInQty').value) || items.length,
                discount: parseFormattedNum(document.getElementById('discountField').value),
                voucher_amount: 0,
                token: parseFormattedNum(document.getElementById('tokenField').value),
                total_amount: parseFormattedNum(document.getElementById('totalAmount').value),
                points: parseFormattedNum(document.getElementById('pointsField').value),
                commission: parseFormattedNum(document.getElementById('commissionField').value),

                payment_data: paymentData,
                items: items,
                unclaimed_freebies: unclaimedFreebies
            };

            // Show loading state
            const saveBtn = document.querySelector('.btn-save');
            const originalText = saveBtn ? saveBtn.textContent : 'SAVE';
            if (saveBtn) {
                saveBtn.textContent = 'Saving...';
                saveBtn.disabled = true;
            }

            // Send data to backend
            fetch('save_sales_trade_in.php', {
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
                    if (saveBtn) {
                        saveBtn.textContent = originalText;
                        saveBtn.disabled = false;
                    }

                    if (result.status === 'success') {
                        alert('Sales Trade-In saved successfully!\nInvoice No: ' + result.invoice_no);
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    if (saveBtn) {
                        saveBtn.textContent = originalText;
                        saveBtn.disabled = false;
                    }
                    console.error('Error:', error);
                    alert('An error occurred while saving the Sales Trade-In entry. Please try again.');
                });
        }

        // Formatted input handler
        function formatInput(input) {
            let cursorPosition = input.selectionStart;
            let oldValLength = input.value.length;
            let val = input.value.replace(/[^0-9.]/g, '');
            const parts = val.split('.');
            if (parts.length > 2) {
                val = parts[0] + '.' + parts.slice(1).join('');
            }
            if (parts[0].length > 3) {
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
            const newVal = parts.join('.');
            input.value = newVal;
            if (document.activeElement === input) {
                let diff = newVal.length - oldValLength;
                let newPos = cursorPosition + diff;
                newPos = Math.max(0, Math.min(newPos, newVal.length));
                input.setSelectionRange(newPos, newPos);
            }
        }

        // Initialize Payment Modal Event Listeners
        document.addEventListener('DOMContentLoaded', function () {
            const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
            const cardPaymentDropdown = document.getElementById('cardPaymentDropdown');
            const qrDropdown = document.getElementById('qrDropdown');
            const paymentRadios = document.querySelectorAll('input[name="payment_method"]');

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
                    if (document.getElementById('chkPaymentPartners').checked) {
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

            if (cardPaymentDropdown) {
                cardPaymentDropdown.addEventListener('change', function () {
                    if (this.disabled) return false;
                    if (document.getElementById('chkCardPayment').checked) {
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

            if (qrDropdown) {
                qrDropdown.addEventListener('change', function () {
                    if (this.disabled) return false;
                    if (document.getElementById('chkQR').checked) {
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

            // Save Payment Button Handler
            const saveBtn = document.querySelector('#paymentModal .btn-save-modal');
            if (saveBtn) {
                saveBtn.addEventListener('click', savePaymentData);
            }

            function savePaymentData() {
                // Validate Trade-In Details (All fields are required)
                const tradeInValue = parseFloat(document.getElementById('tradein_value').value.replace(/,/g, '')) || 0;
                const tradeInIMEI = document.getElementById('tradein_imei').value.trim();
                const tradeInItemCode = document.getElementById('tradein_item_code').value.trim();
                const tradeInBrand = document.getElementById('tradein_brand').value.trim();

                if (!tradeInValue || tradeInValue <= 0) {
                    alert('Trade-In Value is required! Please enter a valid trade-in value.');
                    document.getElementById('tradein_value').focus();
                    return;
                }

                if (!tradeInIMEI || tradeInIMEI === '') {
                    alert('Trade-In IMEI is required! Please enter the IMEI.');
                    document.getElementById('tradein_imei').focus();
                    return;
                }

                if (!tradeInItemCode || tradeInItemCode === '') {
                    alert('Trade-In Item Code is required! Please search and select an item.');
                    document.getElementById('tradein_item_code').focus();
                    return;
                }

                if (!tradeInBrand || tradeInBrand === '') {
                    alert('Trade-In Brand is required! Please select a brand.');
                    document.getElementById('tradein_brand').focus();
                    return;
                }

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

                // Validate Home Credit
                if (homeCreditSection && homeCreditSection.style.display === 'block') {
                    if (paymentPartnersDropdown && paymentPartnersDropdown.value === '') {
                        alert('Please choose a Payment Partner option in order to proceed!');
                        paymentPartnersDropdown.focus();
                        return;
                    }
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

                    if (!loanTypeSelect || !loanTypeSelect.value || loanTypeSelect.value.trim() === '') {
                        alert('LOAN TYPE REQUIRED! Please select a loan type.');
                        if (loanTypeSelect) loanTypeSelect.focus();
                        return;
                    }
                    if (!loanTermsSelect || !loanTermsSelect.value || loanTermsSelect.value.trim() === '') {
                        alert('LOAN TERMS REQUIRED! Please select loan terms.');
                        if (loanTermsSelect) loanTermsSelect.focus();
                        return;
                    }
                    if (!customerNameInput || !customerNameInput.value || customerNameInput.value.trim() === '') {
                        alert("CUSTOMER'S NAME REQUIRED! Please enter the customer's name.");
                        if (customerNameInput) customerNameInput.focus();
                        return;
                    }
                    if (!loanNumberInput || !loanNumberInput.value || loanNumberInput.value.trim() === '') {
                        alert('LOAN NUMBER REQUIRED! Please enter the loan number.');
                        if (loanNumberInput) loanNumberInput.focus();
                        return;
                    }
                    if (!loanBalanceInput || !loanBalanceInput.value || loanBalanceInput.value.trim() === '') {
                        alert('LOAN BALANCE REQUIRED! Please enter the loan balance.');
                        if (loanBalanceInput) loanBalanceInput.focus();
                        return;
                    }
                    const isAnyDownPaymentChecked = ((cashCheckbox && cashCheckbox.checked) || (gcashCheckbox && gcashCheckbox.checked) || (mayaCheckbox && mayaCheckbox.checked));
                    if (!isAnyDownPaymentChecked) {
                        alert('DOWN PAYMENT METHOD REQUIRED! Please check at least one payment method (Cash, G-Cash, or Maya).');
                        return;
                    }
                    if (cashCheckbox && cashCheckbox.checked) {
                        if (!cashAmountInput || !cashAmountInput.value || cashAmountInput.value.trim() === '') {
                            alert('ENTER AMOUNT REQUIRED! Please enter the Cash down payment amount.');
                            if (cashAmountInput) cashAmountInput.focus();
                            return;
                        }
                    }
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
                }

                // Validate Credit Card / Debit Card
                if ((creditCardSection && creditCardSection.style.display === 'block') || (debitCardSection && debitCardSection.style.display === 'block')) {
                    if (cardPaymentDropdown && cardPaymentDropdown.value === '') {
                        alert('Please choose a Card Payment option in order to proceed!');
                        cardPaymentDropdown.focus();
                        return;
                    }
                    if (creditCardSection && creditCardSection.style.display === 'block') {
                        const terminalIssuer = document.getElementById('ccTerminalIssuer');
                        const terminalId = document.getElementById('ccTerminalId');
                        const bank = document.getElementById('creditCardBankDropdown');
                        const terms = document.getElementById('creditCardTermsDropdown');
                        const mid = creditCardSection.querySelector('input[type="text"]');
                        const cardNo = creditCardSection.querySelectorAll('input[type="text"]')[1];
                        const approvalCode = creditCardSection.querySelectorAll('input[type="text"]')[2];
                        const batch = creditCardSection.querySelectorAll('input[type="text"]')[3];
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

                    if (debitCardSection && debitCardSection.style.display === 'block') {
                        const terminalIssuer = document.getElementById('dcTerminalIssuer');
                        const terminalId = document.getElementById('dcTerminalId');
                        const bank = document.getElementById('debitCardBankDropdown');
                        const terms = document.getElementById('debitCardTermsDropdown');
                        const mid = debitCardSection.querySelector('input[type="text"]');
                        const cardNo = debitCardSection.querySelectorAll('input[type="text"]')[1];
                        const approvalCode = debitCardSection.querySelectorAll('input[type="text"]')[2];
                        const batch = debitCardSection.querySelectorAll('input[type="text"]')[3];
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

                // Validate QR
                if ((qrPhSection && qrPhSection.style.display === 'block') || (starpayQrSection && starpayQrSection.style.display === 'block')) {
                    if (qrDropdown && qrDropdown.value === '') {
                        alert('Please choose a QR option in order to proceed!');
                        qrDropdown.focus();
                        return;
                    }
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

                // Validate E-Wallet
                if (ewalletSection && ewalletSection.style.display === 'block') {
                    const ewalletSelect = ewalletSection.querySelector('.hc-form-group:nth-child(2) select');
                    const customerNameInput = ewalletSection.querySelector('.hc-form-group:nth-child(3) input');
                    const referenceNoInput = ewalletSection.querySelector('.hc-form-group:nth-child(4) input');
                    const amountInput = ewalletSection.querySelector('.hc-form-group:nth-child(5) input');

                    if (!ewalletSelect || !ewalletSelect.value || ewalletSelect.value.trim() === '') {
                        alert('E-WALLET REQUIRED! Please select an e-wallet.');
                        if (ewalletSelect) ewalletSelect.focus();
                        return;
                    }
                    const selectedEwalletName = ewalletSelect.options[ewalletSelect.selectedIndex].text;
                    if (!customerNameInput || !customerNameInput.value || customerNameInput.value.trim() === '') {
                        alert("CUSTOMER'S NAME REQUIRED! Please enter the customer's name.");
                        if (customerNameInput) customerNameInput.focus();
                        return;
                    }
                    if (!referenceNoInput || !referenceNoInput.value || referenceNoInput.value.trim() === '') {
                        alert(selectedEwalletName.toUpperCase() + ' REFERENCE NUMBER REQUIRED! Please enter the ' + selectedEwalletName + ' reference number.');
                        if (referenceNoInput) referenceNoInput.focus();
                        return;
                    }
                    if (!amountInput || !amountInput.value || amountInput.value.trim() === '') {
                        alert('AMOUNT REQUIRED! Please enter the payment amount.');
                        if (amountInput) amountInput.focus();
                        return;
                    }
                }

                // Validate Online Banking
                if (onlineBankingSection && onlineBankingSection.style.display === 'block') {
                    const bankSelect = onlineBankingSection.querySelector('.hc-form-group:nth-child(2) select');
                    const referenceNoInput = onlineBankingSection.querySelector('.hc-form-group:nth-child(3) input');
                    const amountInput = onlineBankingSection.querySelector('.hc-form-group:nth-child(4) input');

                    if (!bankSelect || !bankSelect.value || bankSelect.value.trim() === '') {
                        alert('BANK REQUIRED! Please select a bank.');
                        if (bankSelect) bankSelect.focus();
                        return;
                    }
                    const selectedBankName = bankSelect.options[bankSelect.selectedIndex].text;
                    if (!referenceNoInput || !referenceNoInput.value || referenceNoInput.value.trim() === '') {
                        alert(selectedBankName.toUpperCase() + ' REFERENCE NUMBER REQUIRED! Please enter the ' + selectedBankName + ' reference number.');
                        if (referenceNoInput) referenceNoInput.focus();
                        return;
                    }
                    if (!amountInput || !amountInput.value || amountInput.value.trim() === '') {
                        alert('AMOUNT REQUIRED! Please enter the payment amount.');
                        if (amountInput) amountInput.focus();
                        return;
                    }
                }

                // Validate Unit selection
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
                let hasValues = false;

                function collectData(sectionClass, type) {
                    const section = document.querySelector(sectionClass);
                    if (section && section.style.display === 'block') {
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

                        const inputs = section.querySelectorAll('input, select');
                        inputs.forEach(input => {
                            if (input.type === 'hidden') return;
                            let key = input.id;
                            if (key === 'ccTerminalIssuer' || key === 'dcTerminalIssuer') key = 'Terminal Issuer';
                            else if (key === 'ccTerminalId' || key === 'dcTerminalId') key = 'Terminal ID';
                            else if (key === 'creditCardBankDropdown' || key === 'debitCardBankDropdown') key = 'Bank';
                            else if (key === 'creditCardTermsDropdown' || key === 'debitCardTermsDropdown') key = 'Terms';

                            if (!key) {
                                const formGroup = input.closest('.hc-form-group');
                                if (formGroup) {
                                    const label = formGroup.querySelector('label');
                                    if (label) key = label.innerText.replace(':', '').trim();
                                }
                                if (!key) {
                                    const parentRow = input.closest('.enter-amount-row');
                                    if (parentRow) {
                                        const label = parentRow.querySelector('label');
                                        if (label) key = label.innerText.replace(':', '').trim();
                                    }
                                }
                            }

                            if (!key && input.name) key = input.name;
                            if (!key && input.className) key = input.className;
                            if (!key) return;

                            if (input.type === 'checkbox' || input.type === 'radio') {
                                if (input.checked) {
                                    hasValues = true;
                                    if (input.name === 'payment_method') return;
                                    if (data[key]) {
                                        data[key] += ', ' + input.value;
                                    } else {
                                        data[key] = input.value;
                                    }
                                }
                            } else {
                                if (input.tagName === 'SELECT') {
                                    data[key] = data[key] ? data[key] + ' | ' + input.value : input.value;
                                    if (key === 'E-Wallet' && input.selectedIndex > 0) {
                                        data['E-Wallet-Text'] = data['E-Wallet-Text'] ? data['E-Wallet-Text'] + ' | ' + input.options[input.selectedIndex].text : input.options[input.selectedIndex].text;
                                    }
                                    if (key === 'Bank' && input.selectedIndex > 0) {
                                        data['Bank-Text'] = data['Bank-Text'] ? data['Bank-Text'] + ' | ' + input.options[input.selectedIndex].text : input.options[input.selectedIndex].text;
                                    }
                                } else {
                                    data[key] = data[key] ? data[key] + ' | ' + input.value : input.value;
                                }
                                if (input.value && input.value.trim() !== '') {
                                    hasValues = true;
                                }
                            }
                        });

                        const globalTotalInput = document.getElementById('globalTotalInput');
                        if (globalTotalInput) {
                            data['Total'] = globalTotalInput.value;
                            if (globalTotalInput.value && globalTotalInput.value.trim() !== '') hasValues = true;
                        }
                        return true;
                    }
                    return false;
                }

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

                const unitPaymentMap = {};
                for (const sec of sections) {
                    const secEl = document.querySelector(sec.class);
                    if (!secEl || secEl.style.display !== 'block') continue;

                    let displayName = sec.name;
                    if (sec.name === 'E-Wallet' && data['E-Wallet-Text']) {
                        displayName = data['E-Wallet-Text'];
                    } else if (sec.name === 'Online Banking' && data['Bank-Text']) {
                        displayName = data['Bank-Text'];
                    } else if (sec.name === 'Home Credit' && paymentPartnersDropdown && paymentPartnersDropdown.value !== '') {
                        displayName = paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text;
                    }

                    const unitCheckboxes = secEl.querySelectorAll('.unit-selector-row input[type="checkbox"][name="Unit"]:checked');
                    unitCheckboxes.forEach(cb => {
                        unitPaymentMap[cb.value] = displayName;
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

                    const context = getCartAndPaymentContext();
                    const globalTotalInputCheck = document.getElementById('globalTotalInput');
                    const overallTotalPayment = parseFloat(globalTotalInputCheck ? (globalTotalInputCheck.value || '0').replace(/[^0-9.-]/g, '') : '0') || 0;
                    const targetDue = context.activeSelectedDue;
                    const overallDifference = targetDue - overallTotalPayment;

                    if (targetDue > 0 && Math.abs(overallDifference) > 0.01) {
                        const bkBanner = document.getElementById('paymentBreakdownBanner');
                        if (bkBanner) bkBanner.style.display = 'none';

                        const neededDisp = targetDue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const enteredDisp = overallTotalPayment.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const isExceeded = overallDifference < -0.01;
                        const diffLabel = isExceeded ? 'Exceeded Amount:' : 'Remaining Balance:';
                        const diffAmount = Math.abs(overallDifference);
                        const diffDisp = diffAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

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
                                    <div style="margin-top: 4px; padding: 4px 6px; background-color: #fee2e2; border-radius: 4px; border: 1px solid #fca5a5;">
                                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: #7f1d1d; font-weight: 600;">
                                            <span style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">• ${item.labelText}</span>
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
                                        <div style="display: flex; justify-content: space-between; padding-left: 14px; font-size: 12px; color: #991b1b; margin-top: 3px; border-top: 1px dashed #fca5a5; padding-top: 2px;">
                                            <span style="font-weight: 600;">Item Net Total:</span>
                                            <span style="font-weight: 700;">₱${(item.itemNetDue - (hasDiscount ? context.discountAmount : 0)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                        </div>` : ''}
                                    </div>
                                `;
                            });
                        }

                        if (unitRowsHtml) {
                            unitRowsHtml = `
                                <div style="margin-bottom: 6px;">
                                    <div style="font-weight: 600; color: #7f1d1d; font-size: 13px;">Unit(s) To Pay:</div>
                                    ${unitRowsHtml}
                                </div>
                            `;
                        }

                        let tradeInRowHtml = '';
                        if (context.tradeInValue > 0) {
                            tradeInRowHtml = `
                                <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 13px; color: #1d4ed8; margin-top: 2px;">
                                    <span style="font-weight: 600;">- Less Trade-In Value:</span>
                                    <span style="font-weight: 600;">-₱${context.tradeInValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                </div>
                            `;
                        }

                        let tituVoucherRowHtml = '';
                        if (context.tituVoucher > 0) {
                            tituVoucherRowHtml = `
                                <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 13px; color: #6d28d9; margin-top: 2px;">
                                    <span style="font-weight: 600;">- Less TITU Voucher:</span>
                                    <span style="font-weight: 600;">-₱${context.tituVoucher.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                </div>
                            `;
                        }



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
                                    if (input.classList.contains('amount-input')) isAmount = true;
                                    if (input.id && input.id.toLowerCase().includes('amount') && input.id !== 'totalLoanAmount') { isAmount = true; labelText = 'Amount'; }
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
                                            ${tradeInRowHtml}
                                            ${tituVoucherRowHtml}
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
                        return;
                    } else {
                        const banner = document.getElementById('paymentErrorBanner');
                        if (banner) { banner.style.display = 'none'; banner.innerHTML = ''; }
                    }

                    const hiddenInput = document.getElementById('payment_data');
                    if (hiddenInput) {
                        hiddenInput.value = JSON.stringify(data);

                        const btnPayment = document.querySelector('.btn-payment');
                        if (btnPayment) {
                            let buttonText = `Payment: ${sectionName}`;
                            if (sectionName === 'E-Wallet' && data['E-Wallet-Text']) {
                                buttonText = `Payment: ${data['E-Wallet-Text']}`;
                            } else if (sectionName === 'Online Banking' && data['Bank-Text']) {
                                buttonText = `Payment: ${data['Bank-Text']}`;
                            } else if (sectionName === 'Home Credit') {
                                const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
                                if (paymentPartnersDropdown && paymentPartnersDropdown.value) {
                                    const selectedPartnerText = paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text;
                                    buttonText = `Payment: ${selectedPartnerText}`;
                                } else {
                                    buttonText = `Payment: Home Credit`;
                                }
                            }

                            btnPayment.innerText = buttonText;
                            btnPayment.style.backgroundColor = '#2E7D32';
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

                const unitRows = document.querySelectorAll('#salesTableBody tr:not(#no-sales-row), #itemsTableBody tr:not(#no-items-row)');
                const items = [];
                let overallSalesTotal = 0;
                let activeSelectedSales = 0;
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
                        const pVal = priceInput ? (parseFloat(priceInput.value.replace(/,/g, '')) || 0) : (parseFloat(tds[3].textContent.replace(/,/g, '')) || 0);
                        const qVal = qtyInput ? (parseInt(qtyInput.value) || 1) : (parseInt(tds[2].textContent) || 1);
                        const rowTotal = pVal * qVal;

                        const hasVoucher = parseInt(row.getAttribute('data-has-voucher')) || 0;
                        const voucherAmount = parseFloat(row.getAttribute('data-voucher-amount')) || 0;
                        const itemVoucher = (hasVoucher === 1) ? voucherAmount : 0;

                        const hasToken = parseInt(row.getAttribute('data-has-token')) || 0;
                        const tokenAmount = parseFloat(row.getAttribute('data-token-amount')) || 0;
                        const itemToken = (hasToken === 1) ? tokenAmount : 0;

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
                            isSelected,
                            itemNetDue
                        });

                        overallSalesTotal += rowTotal;
                        if (isSelected) {
                            activeSelectedSales += rowTotal;
                            activeSelectedVoucher += itemVoucher;
                            activeSelectedToken += itemToken;
                        }
                    }
                });

                const tradeInValue = parseNumber(document.getElementById('tradein_value') ? document.getElementById('tradein_value').value : (document.getElementById('tradeInValue') ? document.getElementById('tradeInValue').value : 0));
                const tituVoucher = parseNumber(document.getElementById('tituVoucherAmount') ? document.getElementById('tituVoucherAmount').value : 0);
                const discountAmount = parseNumber(document.getElementById('discountField') ? document.getElementById('discountField').value : 0);

                let totalDeductions = tradeInValue + tituVoucher + discountAmount;
                let overallDue = Math.max(0, overallSalesTotal - totalDeductions - items.reduce((sum, it) => sum + it.voucherAmount + it.tokenAmount, 0));

                let baseSelected = hasActiveUnitSelector ? activeSelectedSales : overallSalesTotal;
                let selectedDue = Math.max(0, baseSelected - activeSelectedVoucher - activeSelectedToken - totalDeductions);

                return {
                    items,
                    sections,
                    hasActiveUnitSelector,
                    selectedUnitLabels,
                    activeSelectedDue: hasActiveUnitSelector ? selectedDue : overallDue,
                    overallDue,
                    tradeInValue,
                    tituVoucher,
                    discountAmount,
                    activeSelectedVoucher,
                    activeSelectedToken
                };
            }
            window.getCartAndPaymentContext = getCartAndPaymentContext;

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
                            let isAmount = false;
                            if (input.classList.contains('amount-input')) isAmount = true;
                            if (input.id && input.id.toLowerCase().includes('amount') && input.id !== 'totalLoanAmount') isAmount = true;
                            const formGroup = input.closest('.hc-form-group');
                            if (formGroup) {
                                const label = formGroup.querySelector('label');
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

                const globalTotalDueInput = document.getElementById('globalTotalDueInput');
                if (globalTotalDueInput) {
                    let remaining = context.activeSelectedDue - globalTotal;
                    if (remaining < 0) remaining = 0;
                    globalTotalDueInput.value = '₱' + remaining.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

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
                                        <span style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">• ${item.labelText}</span>
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

                let tradeInRowHtml = '';
                if (context.tradeInValue > 0) {
                    tradeInRowHtml = `
                        <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 13px; color: #1d4ed8; margin-top: 2px;">
                            <span style="font-weight: 600;">- Less Trade-In Value:</span>
                            <span style="font-weight: 600;">-₱${context.tradeInValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                        </div>
                    `;
                }

                let tituVoucherRowHtml = '';
                if (context.tituVoucher > 0) {
                    tituVoucherRowHtml = `
                        <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 13px; color: #6d28d9; margin-top: 2px;">
                            <span style="font-weight: 600;">- Less TITU Voucher:</span>
                            <span style="font-weight: 600;">-₱${context.tituVoucher.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
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
                                ${unitRowsHtml}
                                ${tradeInRowHtml}
                                ${tituVoucherRowHtml}
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

            document.body.addEventListener('change', function (e) {
                if (e.target.matches('.unit-checkboxes input[type="checkbox"], input[name="payment_method"], input[name="down_payment_method"], .custom-multiselect input') || e.target.id === 'paymentPartnersDropdown' || e.target.id === 'cardPaymentDropdown' || e.target.id === 'qrDropdown') {
                    setTimeout(updateSectionTotal, 50);
                }
            });

            const allInputs = document.querySelectorAll('input[type="text"], input[type="number"]');
            allInputs.forEach(input => {
                if (input.classList.contains('total-input') || input.id === 'totalAmount' || input.id === 'tradeInQty' || input.id === 'discountField') return;
                let isAmount = false;
                if (input.classList.contains('amount-input')) isAmount = true;
                if (input.id && input.id.toLowerCase().includes('amount') && input.id !== 'totalLoanAmount') isAmount = true;

                const formGroup = input.closest('.hc-form-group');
                if (formGroup) {
                    const label = formGroup.querySelector('label');
                    if (label && label.innerText.includes('Amount') && !label.innerText.includes('Total Loan Amount')) isAmount = true;
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
                    input.addEventListener('blur', function () {
                        updateSectionTotal();
                    });
                }
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function (event) {
            const paymentModal = document.getElementById('paymentModal');
            if (event.target === paymentModal) {
                closePaymentModal();
            }
            const searchSalesModalTrade = document.getElementById('searchSalesModalTrade');
            if (event.target === searchSalesModalTrade) {
                closeTradeInSalesSearchModal();
            }
        });

        // Set current date and fetch invoice number
        window.onload = function () {
            const today = new Date();
            const dateString = today.toLocaleDateString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            });
            document.getElementById('date').value = dateString;

            // Fetch invoice number on page load
            fetch('get_next_invoice_number.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_invoice_number&branch_code=<?php echo $branch_code; ?>&page_type=salestrade-in'
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
                    const yy = String(today.getFullYear()).substr(-2);
                    const mm = String(today.getMonth() + 1).padStart(2, '0');
                    const dd = String(today.getDate()).padStart(2, '0');
                    const fallback = `${yy}${mm}${dd}-<?php echo $branch_code; ?>-00001`;
                    document.getElementById('invoice_no').value = fallback;
                });
        };
    </script>
</body>

</html>