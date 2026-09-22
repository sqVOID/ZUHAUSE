<?php
require_once 'session_check.php';
include 'config.php';

$branch_code = '000';
$user_branch = '';
$user_branch_names = []; // Array to store user's accessible branch names

if (isset($_SESSION['user_branch'])) {
    $user_branch = $_SESSION['user_branch'];

    // Handle multiple branches (comma-separated)
    $user_branch_names = array_map('trim', explode(',', $user_branch));

    // Get branch code for the first branch (or current branch)
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch'");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
}

// Check if user is Super-Admin
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$is_superadmin = (strcasecmp($system_level, 'Super-Admin') === 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">


    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item Entry Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            z-index: 999;
        }

        .sidebar.hidden {
            display: block;
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

        /* Page header */
        .content-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: 15px;
            margin-bottom: 20px;
        }

        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        /* General Forms */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 14px;
            color: #333;
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
            width: 100%;
            outline: none;
        }

        .form-group input::placeholder {
            color: #999;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #2196F3;
        }

        .form-group input[readonly] {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }

        /* Bottom Section / Items Container */
        .items-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }

        .item-entry-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            margin-bottom: 15px;
        }

        .item-entry-row .form-group {
            margin-bottom: 0;
        }

        .btn-search {
            background-color: var(--color-navy);
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 50px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            height: 38px;
        }

        .btn-search:hover {
            background-color: var(--color-navy-dark);
        }

        .btn-add-item {
            background-color: var(--color-gold);
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 50px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            height: 38px;
        }

        .btn-add-item:hover {
            background-color: var(--color-gold-light);
        }

        .btn-add-unit {
            width: 280px;
            background-color: var(--color-gold);
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 20px;
            margin-left: auto;
            margin-right: auto;
            display: block;
            cursor: pointer;
        }

        .btn-add-unit:hover {
            background-color: var(--color-gold-light);
        }

        /* Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ccc;
            margin-bottom: 20px;
        }

        .items-table th {
            background-color: var(--color-gold-pale);
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border: 1px solid #ccc;
            text-align: center;
        }

        .items-table td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
        }

        .btn-remove {
            padding: 6px 12px;
            background-color: #ef5350;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-remove:hover {
            background-color: #d32f2f;
        }

        .bottom-actions {
            display: flex;
            justify-content: flex-end;
            align-items: flex-end;
            width: 100%;
        }

        .btn-new {
            background-color: var(--color-navy);
            color: #fff;
            padding: 10px 40px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }

        .btn-new:hover {
            background-color: var(--color-navy-dark);
        }

        .btn-save {
            background-color: var(--color-gold);
            color: #fff;
            padding: 10px 45px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
        }

        .btn-save:hover {
            background-color: var(--color-gold-light);
        }

        .totals-section {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 15px;
        }

        .total-qty-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .total-qty-wrap span {
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }

        .total-qty-wrap input {
            width: 150px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            text-align: right;
            background-color: #f5f5f5;
            color: #333;
        }

        .total-qty-wrap input {
            width: 120px;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-align: right;
            outline: none;
            background: #fff;
            font-weight: 600;
        }

        /* Modal */
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
            max-width: 900px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: fadeIn 0.3s;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
            overflow: visible;
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
            position: relative;
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

        .btn-select {
            background-color: var(--color-navy);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
        }

        .btn-select:hover {
            background-color: var(--color-navy-dark);
        }

        /* Searchable Select Styles */
        .select-wrapper {
            position: relative;
            width: 100%;
            z-index: 1;
        }

        .select-search-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: white;
            outline: none;
            cursor: pointer;
        }

        .select-search-input:focus {
            border-color: #2196F3;
        }

        .select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 250px;
            overflow-y: auto;
            display: none;
            z-index: 9999;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            margin-top: -1px;
        }

        .select-dropdown.active {
            display: block;
        }

        .select-option {
            padding: 10px 12px;
            cursor: pointer;
            font-size: 14px;
        }

        .select-option:hover {
            background-color: #f5f5f5;
        }

        .select-option.selected {
            background-color: var(--color-gold-pale);
            color: var(--color-navy);
            font-weight: 600;
        }

        .select-option.hidden {
            display: none;
        }

        .hidden {
            display: none;
        }

        /* ========== RESPONSIVE STYLES ========== */

        /* Large Desktop & Laptop (max-width: 1640px) */
        @media (max-width: 1640px) {
            .form-grid {
                gap: 15px;
            }
        }

        /* Medium Desktop (max-width: 1366px) */
        @media (max-width: 1366px) {

            .form-container,
            .items-container {
                padding: 25px;
            }
        }

        /* Tablet & Medium Desktop (max-width: 1024px) */
        @media (max-width: 1024px) {
            .main-content {
                padding: 15px;
            }

            .content-header h2 {
                font-size: 20px;
            }

            .form-container,
            .items-container {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .item-entry-row {
                flex-wrap: wrap;
            }

            .item-entry-row .form-group {
                flex: 1 1 100% !important;
            }

            .item-entry-row>div {
                width: 100%;
            }

            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .items-table {
                min-width: 800px;
            }

            .items-table th,
            .items-table td {
                font-size: 12px;
                padding: 10px 8px;
                white-space: nowrap;
            }
        }

        /* Small Tablet (max-width: 960px) */
        @media (max-width: 960px) {

            .form-container,
            .items-container {
                padding: 15px;
            }

            .items-table {
                zoom: 0.85;
            }
        }

        /* Mobile & Tablet (max-width: 768px) */
        @media (max-width: 768px) {

            /* Sidebar behavior on mobile */
            .sidebar {
                transform: translateX(-100%);
                z-index: 1500;
            }

            .sidebar.hidden {
                display: block;
                transform: translateX(0);
            }

            /* Main content always takes full width */
            .main-content {
                margin-left: 0;
                padding: 12px;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            /* Header shadow adjustment */
            .header::after {
                left: 0;
            }

            .content-header {
                flex-direction: column;
                align-items: stretch;
                margin-bottom: 15px;
            }

            .content-header h2 {
                font-size: 18px;
            }

            .form-container,
            .items-container {
                padding: 12px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .form-group label {
                font-size: 13px;
            }

            .form-group input,
            .form-group select {
                font-size: 16px;
                padding: 12px;
            }

            .item-entry-row {
                flex-direction: column;
                gap: 12px;
            }

            .item-entry-row .form-group {
                flex: 1 1 100% !important;
            }

            .item-entry-row>div {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .btn-search,
            .btn-add-item,
            .btn-add-unit,
            .btn-new,
            .btn-save {
                width: 100%;
                padding: 12px 20px;
                font-size: 15px;
            }

            .items-table {
                min-width: 900px;
                zoom: 0.75;
            }

            .items-table th,
            .items-table td {
                padding: 10px 8px;
                font-size: 12px;
            }

            .btn-remove,
            .btn-select {
                padding: 6px 12px;
                font-size: 11px;
            }

            .bottom-actions {
                flex-direction: column;
                gap: 15px;
            }

            .totals-section {
                width: 100%;
                align-items: stretch;
            }

            .total-qty-wrap {
                justify-content: space-between;
            }

            .total-qty-wrap input {
                width: 150px;
            }

            /* Modal adjustments */
            .modal-content {
                width: 95%;
                max-width: 95%;
                margin: 10px;
            }

            .modal-header {
                padding: 15px;
                font-size: 18px;
            }

            .modal-body {
                padding: 15px;
            }

            .modal-footer {
                padding: 12px 15px;
                flex-wrap: wrap;
            }

            .btn-back-modal {
                width: 100%;
                padding: 12px;
                font-size: 15px;
            }

            .select-search-input {
                font-size: 16px;
                padding: 12px;
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
                font-size: 16px;
            }

            .form-container,
            .items-container {
                padding: 10px;
            }

            .form-grid {
                gap: 10px;
            }

            .form-group input,
            .form-group select {
                font-size: 15px;
                padding: 10px;
            }

            .item-entry-row {
                gap: 10px;
            }

            .btn-search,
            .btn-add-item,
            .btn-add-unit,
            .btn-new,
            .btn-save {
                font-size: 14px;
                padding: 10px 16px;
            }

            .items-table {
                min-width: 800px;
                zoom: 0.7;
            }

            .items-table th,
            .items-table td {
                padding: 8px 6px;
                font-size: 11px;
            }

            .btn-remove,
            .btn-select {
                padding: 4px 8px;
                font-size: 10px;
            }

            .total-qty-wrap input {
                width: 120px;
            }

            .modal-header {
                padding: 12px;
                font-size: 16px;
            }

            .modal-body {
                padding: 12px;
            }

            #modalSerialInput {
                padding: 8px !important;
                font-size: 15px !important;
            }
        }
    </style>
</head>

<body>

    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()">
            <span></span><span></span><span></span>
        </div>
        <?php include '_header_user.php'; ?>
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Request Change Item Status</h2>
        </div>

        <!-- Top Form -->
        <div class="form-container">
            <div class="form-grid">
                <div class="form-group">
                    <label for="stock_type">Stock Type</label>
                    <select id="stock_type"
                        style="padding: 10px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; color: #333; background: white; width: 100%; outline: none;">
                        <option value="">Select Stock Type</option>
                        <option value="Good Stock">Good Stock</option>
                        <option value="Defective">Defective</option>
                        <option value="Serviced">Serviced</option>
                        <option value="Demo">Demo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="entry_date">Date</label>
                    <input type="text" id="entry_date" placeholder="System Generated" readonly>
                </div>
                <div class="form-group">
                    <label for="branch_name">Branch</label>
                    <select id="branch_name" class="searchable-select"
                        style="padding: 10px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; color: #333; background: white; width: 100%; outline: none;">
                        <option value="">Select Branch</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="remarks">Reason</label>
                    <input type="text" id="remarks" placeholder="Reason">
                </div>
            </div>
        </div>

        <!-- Bottom Container -->
        <div class="items-container">

            <!-- Add Item Inputs -->
            <div class="item-entry-row">
                <div class="form-group" style="flex:2;">
                    <label>Item Code</label>
                    <input type="text" id="input_item_code">
                </div>
                <div class="form-group" style="flex:4;">
                    <label>Item Description</label>
                    <input type="text" id="input_item_desc">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Quantity</label>
                    <input type="number" id="input_item_qty" min="0" value="0">
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-search" onclick="openSearchModal()">Search</button>
                    <button type="button" class="btn-add-item" onclick="addItem()">Add</button>
                </div>
            </div>

            <!-- Serialized Item Button Row -->
            <div style="display: flex; gap: 18px; align-items: center; justify-content: flex-end; margin-bottom: 15px;">
                <label
                    style="font-size: 16px; color: #333; font-weight: 600; font-style: italic; white-space: nowrap; margin-bottom: 0;">BUTTON
                    FOR SERIALIZED ITEM ></label>
                <button type="button" class="btn-add-unit" style="margin: 0;" onclick="openSerialModal()">ADD
                    UNIT</button>
            </div>

            <table class="items-table" id="entryTable">
                <thead>
                    <tr>
                        <th style="width: 40%">Item Description</th>
                        <th style="width: 25%">Serial Number</th>
                        <th style="width: 15%">Quantity</th>
                        <th style="width: 20%">Action</th>
                    </tr>
                </thead>
                <tbody id="entryTableBody">
                    <tr id="empty-row">
                        <td colspan="4" style="height: 38px;">&nbsp;</td>
                    </tr>
                </tbody>
            </table>

            <!-- Bottom Actions -->
            <div class="bottom-actions">
                <div class="totals-section">
                    <div class="total-qty-wrap">
                        <span>Total Quantity:</span>
                        <input type="text" id="total_quantity" readonly>
                    </div>
                    <button type="button" class="btn-save" onclick="saveEntry()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Modal -->
    <div id="searchModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Search</div>
            <div class="modal-body">
                <table class="items-table" style="margin-bottom:0;">
                    <thead>
                        <tr>
                            <th style="width:25%;">Item Code</th>
                            <th style="width:50%;">Item Description</th>
                            <th style="width:25%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="modalSearchBody">
                        <tr>
                            <td colspan="3" style="text-align:center; padding:20px;">Loading items...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-back-modal" onclick="closeSearchModal()">Back</button>
            </div>
        </div>
    </div>

    <!-- Serial Number Modal -->
    <div id="serialModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Serial Number</div>
            <div class="modal-body">
                <input type="text" id="modalSerialInput" class="form-group"
                    style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:4px;"
                    placeholder="Enter serial number..." onkeydown="handleSerialKeyPress(event)">
                <table class="items-table" style="margin-bottom:0;">
                    <thead>
                        <tr>
                            <th style="width:25%;">Item Code</th>
                            <th style="width:50%;">Item Description</th>
                            <th style="width:25%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="modalSerialBody">
                        <tr>
                            <td colspan="3" style="text-align:center; padding:20px;">Enter serial number to search</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer" style="justify-content: space-between;">
                <button type="button" class="btn-back-modal" onclick="closeSerialModal()">Back</button>
                <button type="button" class="btn-save" onclick="submitSerialItems()">Submit</button>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn = document.querySelector('.menu-btn');

            menuBtn.classList.toggle('active');
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
        }

        function initializeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn = document.querySelector('.menu-btn');

            if (window.innerWidth <= 768) {
                if (sidebar.classList.contains('hidden')) {
                    sidebar.classList.remove('hidden');
                }
                if (!mainContent.classList.contains('expanded')) {
                    mainContent.classList.add('expanded');
                }
                if (menuBtn.classList.contains('active')) {
                    menuBtn.classList.remove('active');
                }
            } else {
                if (sidebar.classList.contains('hidden')) {
                    sidebar.classList.remove('hidden');
                }
                if (mainContent.classList.contains('expanded')) {
                    mainContent.classList.remove('expanded');
                }
                if (!menuBtn.classList.contains('active')) {
                    menuBtn.classList.add('active');
                }
            }
        }

        window.addEventListener('load', initializeSidebar);

        window.addEventListener('resize', function () {
            clearTimeout(window.resizeTimer);
            window.resizeTimer = setTimeout(initializeSidebar, 250);
        });

        function toggleSection(element) {
            const section = element.parentElement;
            const isCurrentlyCollapsed = section.classList.contains('collapsed');

            const allSections = document.querySelectorAll('.menu-section');
            allSections.forEach(function (s) {
                if (s !== section) {
                    s.classList.add('collapsed');
                }
            });

            if (isCurrentlyCollapsed) {
                section.classList.remove('collapsed');
            } else {
                section.classList.add('collapsed');
            }

            if (typeof saveSidebarState === 'function') {
                saveSidebarState();
            }
        }

        // Set today's date
        document.addEventListener('DOMContentLoaded', () => {
            const today = new Date();
            document.getElementById('entry_date').value = today.toLocaleDateString('en-US', {
                year: 'numeric', month: '2-digit', day: '2-digit'
            });

            // Load branches for Branch dropdown
            loadBranches();

            // Add Enter key listener to Item Code input
            const itemCodeInput = document.getElementById('input_item_code');
            if (itemCodeInput) {
                itemCodeInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        performSearch();
                    }
                });
            }
        });

        // Load branches from database
        function loadBranches() {
            // Get user's accessible branches from PHP
            const userBranches = <?php echo json_encode($user_branch_names); ?>;
            const isSuperAdmin = <?php echo $is_superadmin ? 'true' : 'false'; ?>;

            fetch('get_branches.php')
                .then(response => response.json())
                .then(data => {
                    const branchSelect = document.getElementById('branch_name');
                    branchSelect.innerHTML = '<option value="">Select Branch</option>';

                    if (data.status === 'success' && data.data) {
                        // Filter branches based on user access
                        let filteredBranches = data.data;

                        if (!isSuperAdmin && userBranches.length > 0) {
                            // Filter to show only branches the user has access to
                            filteredBranches = data.data.filter(branch =>
                                userBranches.includes(branch.branch_name)
                            );
                        }

                        // Add filtered branches to dropdown
                        filteredBranches.forEach(branch => {
                            const option = document.createElement('option');
                            option.value = branch.branch_name;
                            option.textContent = branch.branch_name;
                            branchSelect.appendChild(option);
                        });

                        // Initialize searchable-select after options are loaded
                        initializeSearchableSelect();
                    }
                })
                .catch(error => {
                    console.error('Error loading branches:', error);
                });
        }

        // Initialize searchable-select functionality
        function initializeSearchableSelect() {
            document.querySelectorAll('.searchable-select').forEach(function (select) {
                // Skip if already initialized
                if (select.parentNode.classList.contains('select-wrapper')) {
                    return;
                }

                const wrapper = document.createElement('div');
                wrapper.className = 'select-wrapper';
                select.parentNode.insertBefore(wrapper, select);

                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'select-search-input';
                input.placeholder = select.options[0].text;

                const dropdown = document.createElement('div');
                dropdown.className = 'select-dropdown';

                // Get selected value
                const selectedValue = select.value;
                if (selectedValue) {
                    const selectedOption = Array.from(select.options).find(opt => opt.value === selectedValue);
                    if (selectedOption) input.value = selectedOption.text;
                }

                // Build dropdown options
                Array.from(select.options).forEach(function (option, index) {
                    if (index === 0) return; // Skip placeholder
                    const div = document.createElement('div');
                    div.className = 'select-option';
                    div.textContent = option.text;
                    div.dataset.value = option.value;
                    if (option.value === selectedValue) div.classList.add('selected');
                    dropdown.appendChild(div);
                });

                wrapper.appendChild(input);
                wrapper.appendChild(dropdown);
                select.style.display = 'none';
                wrapper.appendChild(select);

                // Show dropdown on focus
                input.addEventListener('focus', function () {
                    dropdown.classList.add('active');
                });

                // Filter options on input
                input.addEventListener('input', function () {
                    const searchTerm = this.value.toLowerCase();
                    // Clear the select value when user manually types/clears
                    select.value = '';
                    dropdown.querySelectorAll('.select-option').forEach(function (option) {
                        option.classList.remove('selected');
                        const text = option.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            option.classList.remove('hidden');
                        } else {
                            option.classList.add('hidden');
                        }
                    });
                    // Keep dropdown open while typing
                    dropdown.classList.add('active');
                });

                // Select option on click
                dropdown.addEventListener('click', function (e) {
                    if (e.target.classList.contains('select-option')) {
                        input.value = e.target.textContent;
                        select.value = e.target.dataset.value;
                        dropdown.querySelectorAll('.select-option').forEach(opt => opt.classList.remove('selected'));
                        e.target.classList.add('selected');
                        dropdown.classList.remove('active');
                    }
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function (e) {
                    if (!wrapper.contains(e.target)) {
                        dropdown.classList.remove('active');
                    }
                });
            });
        }

        let itemsList = [];
        let modalItemsCache = [];
        let currentSearchResults = [];

        function addItem() {
            const code = document.getElementById('input_item_code').value.trim();
            const desc = document.getElementById('input_item_desc').value.trim();
            const qty = parseFloat(document.getElementById('input_item_qty').value) || 0;

            if (!code && !desc) {
                alert('Please select an item or enter code/description.');
                return;
            }

            if (qty <= 0) {
                alert('Please enter a valid quantity.');
                return;
            }

            // Check if item is serialized first
            if (code) {
                fetch(`check_serial_permission.php?item_code=${encodeURIComponent(code)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success' && data.has_serial) {
                            alert('This item is serialized. Please use the "ADD UNIT" button to add serial numbers.');
                            document.getElementById('input_item_code').value = '';
                            document.getElementById('input_item_desc').value = '';
                            document.getElementById('input_item_qty').value = '';
                            return;
                        }

                        proceedWithStockCheck(code, desc, qty);
                    })
                    .catch(error => {
                        console.error('Error checking serial status:', error);
                        proceedWithStockCheck(code, desc, qty);
                    });
            } else {
                proceedWithStockCheck(code, desc, qty);
            }
        }

        function proceedWithStockCheck(code, desc, qty) {
            const stockCheckUrl = `check_stock_availability.php?item_code=${encodeURIComponent(code)}&qty=${qty}&force_branch=true`;

            fetch(stockCheckUrl)
                .then(response => response.json())
                .then(stockData => {
                    console.log('Stock check response:', stockData);

                    if (stockData.status === 'error' || !stockData.available) {
                        alert(stockData.message || 'Insufficient stock available');
                        return;
                    }

                    itemsList.push({ code, desc, IMEI: '', qty });
                    renderTable();

                    document.getElementById('input_item_code').value = '';
                    document.getElementById('input_item_desc').value = '';
                    document.getElementById('input_item_qty').value = '';
                })
                .catch(error => {
                    console.error('Error checking stock:', error);
                    alert('Error checking stock availability. Please try again.');
                });
        }

        function removeItem(index) {
            itemsList.splice(index, 1);
            renderTable();
        }

        function renderTable() {
            const tbody = document.getElementById('entryTableBody');
            tbody.innerHTML = '';

            if (itemsList.length === 0) {
                tbody.innerHTML = '<tr id="empty-row"><td colspan="4" style="height: 38px;">&nbsp;</td></tr>';
                document.getElementById('total_quantity').value = '';
                return;
            }

            let totalQty = 0;
            itemsList.forEach((item, idx) => {
                totalQty += item.qty;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td style="text-align: left;">${escHtml(item.desc)}</td>
                <td style="text-align: center;">${escHtml(item.IMEI)}</td>
                <td>${item.qty !== 0 ? item.qty : ''}</td>
                <td>
                    <button class="btn-remove" onclick="removeItem(${idx})">Remove</button>
                </td>
            `;
                tbody.appendChild(tr);
            });

            document.getElementById('total_quantity').value = totalQty > 0 ? totalQty : '';
        }

        function updateIMEI(index, value) {
            if (itemsList[index]) {
                itemsList[index].IMEI = value.trim();
            }
        }

        function escHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function escJs(str) {
            return String(str || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        }

        function performSearch() {
            const searchTerm = document.getElementById('input_item_code').value.trim();

            if (searchTerm === '') {
                alert('Please input Item Code!');
                return;
            }

            fetch(`search_item.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('modalSearchBody');
                    tbody.innerHTML = '';
                    if (data.status === 'success' && data.data && data.data.length > 0) {
                        currentSearchResults = data.data;
                        modalItemsCache = data.data;
                        renderModalItems(data.data);
                    } else {
                        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px;">No items found</td></tr>';
                    }
                    document.getElementById('searchModal').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while searching.');
                });
        }

        function openSearchModal() {
            performSearch();
        }

        function closeSearchModal() {
            document.getElementById('searchModal').style.display = 'none';
        }

        function loadItems(query) {
            const tbody = document.getElementById('modalSearchBody');
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:20px;">Loading...</td></tr>';
            fetch('search_item.php?term=' + encodeURIComponent(query))
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success' && data.data) {
                        modalItemsCache = data.data || [];
                        renderModalItems(modalItemsCache);
                    } else {
                        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:20px;">No items found.</td></tr>';
                    }
                })
                .catch(() => {
                    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:20px;">Error loading items.</td></tr>';
                });
        }

        function renderModalItems(items) {
            const tbody = document.getElementById('modalSearchBody');
            if (!items || items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:20px;">No items found.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            items.forEach((item, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>${escHtml(item.item_code)}</td>
                <td>${escHtml(item.description)}</td>
                <td><button class="btn-select" onclick="selectItem(${index})">Select</button></td>
            `;
                tbody.appendChild(tr);
            });
        }

        function selectItem(index) {
            const item = currentSearchResults[index];
            if (!item) return;

            const code = item.item_code;
            const desc = item.description;

            if (code) {
                fetch(`check_serial_permission.php?item_code=${encodeURIComponent(code)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success' && data.has_serial) {
                            alert('This item is serialized. Please use the "ADD UNIT" button to add serial numbers.');
                            document.getElementById('input_item_code').value = '';
                            document.getElementById('input_item_desc').value = '';
                            closeSearchModal();
                            return;
                        }

                        document.getElementById('input_item_code').value = code;
                        document.getElementById('input_item_desc').value = desc;
                        closeSearchModal();
                    })
                    .catch(error => {
                        console.error('Error checking serial status:', error);
                    });
            }
        }

        function saveEntry() {
            const stockType = document.getElementById('stock_type').value.trim();
            if (!stockType) {
                alert("Stock Type is required");
                return;
            }

            const branchName = document.getElementById('branch_name').value.trim();
            if (!branchName) {
                alert("Branch is required");
                return;
            }

            const remarks = document.getElementById('remarks').value.trim();
            if (!remarks) {
                alert("Reason is required");
                return;
            }

            if (itemsList.length === 0) {
                alert("No items to save.");
                return;
            }

            const entryDate = document.getElementById('entry_date').value;
            const branchCode = '<?php echo $branch_code; ?>';

            if (!entryDate) {
                alert("Missing required field: Date.");
                return;
            }

            // Validate each item before saving - check if any item already has the requested status
            const validatePromises = itemsList.map(item => {
                if (item.IMEI) {
                    // For serialized items, check the current status
                    return fetch(`search_imei.php?imei=${encodeURIComponent(item.IMEI)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success' && data.data) {
                                const currentStockType = data.data.current_stock_type || 'Good Stock';
                                if (currentStockType === stockType) {
                                    return {
                                        valid: false,
                                        message: `Cannot save. Item "${item.code}" (${item.IMEI}) is already in "${currentStockType}" status. You cannot request to change it to the same status.`
                                    };
                                }
                            }
                            return { valid: true };
                        });
                } else {
                    // For non-serialized items, we can't easily check, so allow it
                    return Promise.resolve({ valid: true });
                }
            });

            Promise.all(validatePromises)
                .then(validationResults => {
                    // Check if any validation failed
                    const failedValidation = validationResults.find(result => !result.valid);
                    if (failedValidation) {
                        alert(failedValidation.message);
                        return;
                    }

                    // All validations passed, proceed with save
                    const items = itemsList.map(item => ({
                        item_code: item.code || '',
                        item_description: item.desc || '',
                        imei: item.IMEI || '',
                        quantity: item.qty || 0
                    }));

                    const formData = new FormData();
                    formData.append('entry_date', entryDate);
                    formData.append('branch_code', branchCode);
                    formData.append('branch_name', branchName);
                    formData.append('stock_type', stockType);
                    formData.append('remarks', remarks);
                    formData.append('items', JSON.stringify(items));

                    fetch('save_sales_entry_status.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message || 'Item Entry Status saved successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + (data.message || 'Failed to save item entry status'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error saving item entry status. Please try again.');
                        });
                })
                .catch(error => {
                    console.error('Validation error:', error);
                    alert('Error validating items. Please try again.');
                });
        }

        // Serial Modal logic
        let modalSerialCache = [];
        let tempSerialItems = [];

        function handleSerialKeyPress(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchBySerialNumber();
            }
        }

        function openSerialModal() {
            document.getElementById('serialModal').style.display = 'flex';
            const serialInput = document.getElementById('modalSerialInput');
            serialInput.value = '';

            tempSerialItems = [];

            const tbody = document.getElementById('modalSerialBody');
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px;">Enter serial number to search</td></tr>';

            serialInput.focus();
        }

        function closeSerialModal() {
            document.getElementById('serialModal').style.display = 'none';
            tempSerialItems = [];
        }

        function searchBySerialNumber() {
            const serialNumber = document.getElementById('modalSerialInput').value.trim();

            if (!serialNumber) {
                alert('Please enter a serial number');
                return;
            }

            const exists = tempSerialItems.some(item => item.IMEI === serialNumber);
            if (exists) {
                alert('This serial number has already been added');
                document.getElementById('modalSerialInput').value = '';
                return;
            }

            // Get the selected stock type from the form
            const selectedStockType = document.getElementById('stock_type').value.trim();
            if (!selectedStockType) {
                alert('Please select a Stock Type first before adding items');
                return;
            }

            fetch(`search_imei.php?imei=${encodeURIComponent(serialNumber)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.data) {
                        // Check if current stock type matches the selected stock type
                        const currentStockType = data.data.current_stock_type || 'Good Stock';
                        
                        if (currentStockType === selectedStockType) {
                            alert(`Cannot add this item. The item is already in "${currentStockType}" status. You cannot request to change it to the same status.`);
                            document.getElementById('modalSerialInput').value = '';
                            return;
                        }

                        tempSerialItems.push({
                            code: data.data.item_code,
                            desc: data.data.description,
                            IMEI: serialNumber,
                            qty: 1
                        });

                        renderSerialItems();

                        document.getElementById('modalSerialInput').value = '';
                        document.getElementById('modalSerialInput').focus();
                    } else {
                        alert(data.message || 'Serial number not found in stock');
                    }
                })
                .catch(error => {
                    console.error('Error searching serial number:', error);
                    alert('Error searching for serial number');
                });
        }

        function renderSerialItems() {
            const tbody = document.getElementById('modalSerialBody');

            if (tempSerialItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:20px;">Enter serial number to search</td></tr>';
                return;
            }

            tbody.innerHTML = '';
            tempSerialItems.forEach((item, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td style="text-align:left;">${escHtml(item.code)}</td>
                <td style="text-align:left;">${escHtml(item.desc)}</td>
                <td style="text-align:center;">
                    <button class="btn-remove" onclick="removeSerialItem(${index})">Remove</button>
                </td>
            `;
                tbody.appendChild(tr);
            });
        }

        function removeSerialItem(index) {
            tempSerialItems.splice(index, 1);
            renderSerialItems();
        }

        function submitSerialItems() {
            if (tempSerialItems.length === 0) {
                alert('No serial numbers added');
                return;
            }

            tempSerialItems.forEach(item => {
                itemsList.push(item);
            });

            renderTable();
            closeSerialModal();

            document.getElementById('input_item_code').value = '';
            document.getElementById('input_item_desc').value = '';
            document.getElementById('input_item_qty').value = '0';
        }

        window.onclick = function (event) {
            const modal = document.getElementById('searchModal');
            const serialModal = document.getElementById('serialModal');
            if (event.target === modal) {
                closeSearchModal();
            }
            if (event.target === serialModal) {
                closeSerialModal();
            }
        }
    </script>
</body>

</html>