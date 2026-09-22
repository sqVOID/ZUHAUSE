<?php
require_once 'session_check.php';
include 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
  <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Purchase Order</title>
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

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0ff;
            zoom: 77%;
        }

        /* --- Header --- */
        .header {
            position: fixed; top: 0; left: 0; right: 0; height: 60px;
            background-color: white; display: flex; align-items: center;
            padding: 0 20px; z-index: 1000; gap: 30px;
        }
        .header::after {
            content: ''; position: absolute; bottom: 0; left: 250px; right: 0;
            height: 1px; box-shadow: 0 2px 4px rgba(0,0,0,0.5); pointer-events: none;
        }
        .logo { margin-left: -20px; height: 50px; }
        .menu-btn {
            width: 24px; height: 22px; cursor: pointer; position: relative;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
        }
        .menu-btn span {
            display: block; width: 18px; height: 2px; background-color: #333;
            position: absolute; transition: all 0.3s ease;
        }
        .menu-btn span:nth-child(1) { top: 0; }
        .menu-btn span:nth-child(2) { top: 50%; transform: translateY(-50%); }
        .menu-btn span:nth-child(3) { bottom: 0; }
        .menu-btn.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); }
        .menu-btn.active span:nth-child(2) { opacity: 0; }
        .menu-btn.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }

        /* --- Sidebar --- */
        .sidebar {
            position: fixed; left: 0; top: 60px; width: 250px;
            height: calc(149.3vh - 60px); background-color: white;
            box-shadow: 2px 0 4px rgba(0,0,0,0.1); transition: transform 0.3s ease;
            overflow-y: auto; padding: 20px 0; z-index: 999;
        }
        .sidebar.hidden { transform: translateX(-100%); }
        .menu-item {
            padding: 12px 20px; display: flex; align-items: center; gap: 12px;
            color: #666; text-decoration: none; cursor: pointer;
            transition: background-color 0.2s; font-size: 14px;
        }
        .menu-item:hover { background-color: #f5f5f5; }
        .menu-item.active { background-color: var(--color-gold-pale); color: var(--color-navy); font-weight: bold; }
        .menu-item svg { width: 20px; height: 20px; fill: currentColor; }
        .menu-section-title {
            padding: 12px 20px; display: flex; align-items: center;
            justify-content: space-between; gap: 12px; color: #666;
            cursor: pointer; font-size: 14px; font-weight: 500;
        }
        .menu-section-title svg { width: 20px; height: 20px; fill: currentColor; }
        .menu-section-title .arrow { transition: transform 0.3s ease; }
        .menu-section.collapsed .arrow { transform: rotate(-90deg); }
        .submenu { padding-left: 20px; max-height: 500px; overflow: hidden; transition: max-height 0.3s ease; }
        .menu-section.collapsed .submenu { max-height: 0; }
        .submenu .menu-item { padding: 10px 20px; font-size: 13px; }

        /* --- Main Content --- */
        .main-content {
            margin-left: 250px; margin-top: 60px; padding: 20px;
            transition: margin-left 0.3s ease;
        }
        .main-content.expanded { margin-left: 0; }

        /* --- Page Header --- */
        .page-header {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 20px;
        }
        .back-btn {
            display: flex; align-items: center; justify-content: center;
            background: none; border: none; cursor: pointer; padding: 4px;
            color: #333;
        }
        .back-btn svg { width: 20px; height: 20px; fill: #333; }
        .page-header h2 { font-size: 18px; font-weight: 600; color: #222; }

        /* --- Two-column layout --- */
        .create-po-layout {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 20px;
            align-items: start;
        }

        /* --- Information Card --- */
        .info-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            padding: 24px;
        }
        .info-card h3 {
            font-size: 15px; font-weight: 600; color: #222;
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full-width { grid-column: 1 / -1; }

        .form-group label {
            font-size: 13px; font-weight: 500; color: #444;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 9px 12px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: white;
            font-family: Arial, sans-serif;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { border-color: var(--color-gold); }
        .form-group input[readonly] { background: #f9f9f9; color: #888; cursor: default; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group input::placeholder,
        .form-group textarea::placeholder { color: #bbb; }

        /* --- Custom Brand Select Dropdown --- */
        .custom-brand-select {
            position: relative;
            width: 100%;
        }
        .brand-select-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: border-color 0.2s;
            font-size: 13px;
            color: #333;
            min-height: 36px;
            box-sizing: border-box;
        }
        .brand-select-input:hover {
            border-color: #999;
        }
        .brand-select-input.active {
            border-color: var(--color-gold);
        }
        #brand-display {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #999;
        }
        #brand-display.has-selection {
            color: #333;
        }
        .brand-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
        }
        .brand-dropdown.show {
            display: block;
        }
        .brand-option {
            padding: 10px 12px;
            cursor: pointer;
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
        }
        .brand-option:last-child {
            border-bottom: none;
        }
        .brand-option:hover {
            background: #f5f5f5;
        }
        .brand-option .brand-text {
            flex: 1;
        }
        .brand-option.selected {
            background: var(--color-gold-pale);
            font-weight: 600;
        }
        .brand-checkbox {
            margin-right: 8px;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        /* --- Receiving Summary Card --- */
        .summary-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            padding: 20px;
            position: sticky;
            top: 80px;
        }
        .summary-card h3 {
            font-size: 14px; font-weight: 600; color: #222;
            margin-bottom: 16px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 13px;
            color: #444;
        }
        .summary-row .value { font-weight: 500; color: #222; }
        .summary-divider {
            border: none; border-top: 1px solid #e5e5e5;
            margin: 14px 0;
        }
        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            font-size: 14px;
            font-weight: 600;
            color: #111;
        }
        .summary-total .total-amount { font-size: 15px; }

        .btn-create-po-submit {
            width: 100%;
            padding: 11px;
            background: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 10px;
            transition: background 0.2s;
        }
        .btn-create-po-submit:hover { background: var(--color-gold-light); }

        .btn-cancel-po {
            width: 100%;
            padding: 10px;
            background: white;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-cancel-po:hover { background: #f5f5f5; }

        /* --- Item Section --- */
        .item-section {
            margin-top: 20px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            padding: 20px 24px;
            grid-column: 1 / -1;
        }
        .item-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .item-section-header h3 { font-size: 15px; font-weight: 600; color: #222; }

        .btn-add-item {
            padding: 8px 16px;
            background: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background 0.2s;
        }
        .btn-add-item:hover { background: var(--color-gold-light); }

        .item-empty {
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            padding: 50px 20px;
            text-align: center;
            color: #888;
            font-size: 13px;
            background: #fefefe;
        }

        /* --- Items Table (when items are added) --- */
        .items-table-wrapper { overflow-x: auto; }
        .items-table {
            width: 100%; border-collapse: collapse; display: none;
        }
        .items-table thead { background: var(--color-gold-pale); }
        .items-table th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000000;
            border-top: 1px solid #ccc;
            border-bottom: 1px solid #ccc;
        }
        .items-table th:first-child { border-left: 1px solid #ccc; }
        .items-table th:last-child  { border-right: 1px solid #ccc; }
        .items-table td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border-bottom: 1px solid #ccc;
            text-align: center;
        }
        .items-table td:first-child { border-left: 1px solid #ccc; }
        .items-table td:last-child  { border-right: 1px solid #ccc; }
        .items-table tbody tr:hover { background: #fdf8f3; }
        .items-table td input,
        .items-table td select {
            border: 1px solid #d0d0d0; border-radius: 4px;
            padding: 6px 8px; font-size: 13px; width: 100%; text-align: center;
            outline: none; font-family: Arial, sans-serif; background: white;
        }
        .items-table td input::placeholder { color: #bbb; }
        .items-table td input:focus,
        .items-table td select:focus { border-color: var(--color-gold); }
        .btn-remove-row {
            background: #c62828; color: white; border: none;
            border-radius: 4px; padding: 5px 12px; font-size: 12px;
            font-weight: 500; cursor: pointer;
        }
        .btn-remove-row:hover { background: #b71c1c; }

        @media (max-width: 900px) {
            .create-po-layout { grid-template-columns: 1fr; }
            .summary-card { position: static; }
            .item-section { grid-column: 1; }
        }
        @media (max-width: 768px) {
            .sidebar { 
                transform: translateX(-100%);
                z-index: 1500;
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
            .form-row { 
                grid-template-columns: 1fr; 
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
            background-color: rgba(0,0,0,0.4); 
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: fadeIn 0.3s;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
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
            text-align: right;
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
            background-color: #2e7d32;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-select:hover {
            background-color: #1b5e20;
        }

        .btn-back-modal {
            background-color: #f5f5f5;
            color: #333;
            border: 1px solid #ccc;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-back-modal:hover {
            background-color: #e0e0e0;
        }

        /* Searchable Select Styles */
        .select-wrapper {
            position: relative;
        }
        .select-search-input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: white;
            font-family: Arial, sans-serif;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .select-search-input:focus {
            border-color: var(--color-gold);
        }
        .select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #d0d0d0;
            border-top: none;
            border-radius: 0 0 4px 4px;
            z-index: 1000;
            display: none;
        }
        .select-dropdown.active {
            display: block;
        }
        .select-option {
            padding: 10px 12px;
            cursor: pointer;
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
        }
        .select-option:last-child {
            border-bottom: none;
        }
        .select-option:hover {
            background: #f5f5f5;
        }
        .select-option.selected {
            background: #e8e8e8;
            font-weight: 600;
        }
        .select-option.hidden {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()">
            <span></span><span></span><span></span>
        </div>
     <!--   <img src="Icon/imslogo2.svg" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">

        <!-- Page Header -->
        <div class="page-header">
            <button class="back-btn" onclick="window.location.href='purchaseorder.php'" title="Back">
                <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            </button>
            <h2>Create Purchase Order</h2>
        </div>

        <!-- Two-column layout -->
        <div class="create-po-layout">

            <!-- LEFT: Information Card -->
            <div class="info-card">
                <h3>Information</h3>  

                <div class="form-row">
                    <div class="form-group">
                        <label>P.O Number</label>
                        <input type="text" id="po_number" value="Automated" readonly placeholder="Automated">
                    </div>
                    <div class="form-group">
                        <label>PO date</label>
                        <input type="text" id="po_date" value="Automated" readonly placeholder="Automated">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Supplier Company Name: <span style="color: red;">*</span></label>
                        <select id="supplier_company" class="searchable-select" required>
                            <option value="">Select Company Name</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select Brand: <span style="color: red;">*</span></label>
                        <div class="custom-brand-select">
                            <div class="brand-select-input" onclick="toggleBrandDropdown()">
                                <span id="brand-display">Select Brand</span>
                                <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: #666;">
                                    <path d="M7 10l5 5 5-5z"/>
                                </svg>
                            </div>
                            <div class="brand-dropdown" id="brand-dropdown">
                                <!-- Multiple Brands Checkbox Option -->
                                <div style="padding: 8px; border-bottom: 1px solid #eee; background: #f9f9f9;">
                                    <label style="display: flex; align-items: center; cursor: pointer; font-weight: 600; margin: 0;">
                                        <input type="checkbox" id="multiple-brands-checkbox" onclick="toggleMultipleBrandsMode()" style="margin-right: 8px; width: 16px; height: 16px; cursor: pointer;">
                                        <span>Multiple Brands</span>
                                    </label>
                                </div>
                                <!-- Brand Options Container -->
                                <div id="brand-options-container" style="max-height: 200px; overflow-y: auto; padding: 8px;">
                                    <?php
                                    $brands_query = $conn->query("SELECT brand_name FROM brands WHERE status = 'Active' ORDER BY brand_name ASC");
                                    if ($brands_query) {
                                        while ($brand = $brands_query->fetch_assoc()) {
                                            $brand_name = htmlspecialchars($brand['brand_name']);
                                            echo '<div class="brand-option" data-brand="' . $brand_name . '" onclick="selectSingleBrand(\'' . $brand_name . '\')">
                                                    <input type="checkbox" class="brand-checkbox" value="' . $brand_name . '" style="display: none; margin-right: 8px; width: 16px; height: 16px; cursor: pointer;" onclick="event.stopPropagation(); updateBrandDisplay()">
                                                    <span class="brand-text">' . $brand_name . '</span>
                                                  </div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Terms: <span style="color: red;">*</span></label>
                        <select id="terms" required>
                            <option value="30">30 Days</option>
                            <option value="15">15 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="cod">Cash on Delivery</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment due date: <span style="color: red;">*</span></label>
                        <input type="date" id="payment_due_date" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label>Remarks</label>
                        <textarea id="remarks" placeholder="Remarks"></textarea>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Receiving Summary -->
            <div class="summary-card">
                <h3>Receiving Summary</h3>

                <div class="summary-row">
                    <span>Items</span>
                    <span class="value" id="summary-items">0</span>
                </div>
                <div class="summary-row">
                    <span>Total Quantity</span>
                    <span class="value" id="summary-qty">0</span>
                </div>

                <hr class="summary-divider">

                <div class="summary-total">
                    <span>Total</span>
                    <span class="total-amount" id="summary-total">&#8369; 0.00</span>
                </div>

                <button class="btn-create-po-submit" onclick="submitPO()">Create Purchase Order</button>
                <button class="btn-cancel-po" onclick="window.location.href='purchaseorder.php'">Cancel</button>
            </div>

            <!-- BOTTOM: Item Section (spans full width) -->
            <div class="item-section">
                <div class="item-section-header">
                    <h3>Item</h3>
                    <button class="btn-add-item" type="button" id="add-item-btn">+ Add Item</button>
                </div>

                <div id="item-empty" class="item-empty">
                    No item added yet. Click "Add item" to start.
                </div>

                <div class="items-table-wrapper">
                    <table class="items-table" id="items-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Family Code</th>
                                <th>Quantity</th>
                                <th>Cost</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="items-tbody"></tbody>
                    </table>
                </div>
            </div>

        </div><!-- end .create-po-layout -->
    </div>

    <!-- Search Modal -->
    <div id="searchItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Search
            </div>
            <div class="modal-body">
                <table class="search-results-table">
                    <thead>
                        <tr>
                            <th style="width: 70%;">Family Code</th>
                            <th style="width: 30%; text-align: center;">Action</th>
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

    <script>
        // --- Global Variables ---------------------------------------------
        let rowCount = 0;
        let currentSearchResults = [];
        let currentSearchRowId = null;
        let isMultipleBrandsMode = false;
        let selectedBrand = null;

        // --- Toggle Brand Dropdown ---
        function toggleBrandDropdown() {
            const dropdown = document.getElementById('brand-dropdown');
            const input = document.querySelector('.brand-select-input');
            
            if (dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                input.classList.remove('active');
            } else {
                dropdown.classList.add('show');
                input.classList.add('active');
            }
        }

        // --- Toggle Multiple Brands Mode ---
        function toggleMultipleBrandsMode() {
            const checkbox = document.getElementById('multiple-brands-checkbox');
            isMultipleBrandsMode = checkbox.checked;
            
            const brandOptions = document.querySelectorAll('.brand-option');
            const brandCheckboxes = document.querySelectorAll('.brand-checkbox');
            
            if (isMultipleBrandsMode) {
                // Switch to multiple selection mode - show all checkboxes
                brandOptions.forEach(option => {
                    option.onclick = null; // Remove single-select click handler
                    option.classList.remove('selected');
                });
                brandCheckboxes.forEach(cb => {
                    cb.style.display = 'inline-block';
                    cb.checked = false; // Reset selections
                });
                selectedBrand = null;
            } else {
                // Switch to single selection mode - hide all checkboxes
                brandOptions.forEach((option, index) => {
                    const brandName = option.getAttribute('data-brand');
                    option.onclick = function() { selectSingleBrand(brandName); };
                    option.classList.remove('selected');
                });
                brandCheckboxes.forEach(cb => {
                    cb.style.display = 'none';
                    cb.checked = false;
                });
                selectedBrand = null;
            }
            
            updateBrandDisplay();
        }

        // --- Select Single Brand ---
        function selectSingleBrand(brandName) {
            if (isMultipleBrandsMode) return; // Ignore in multiple mode
            
            selectedBrand = brandName;
            
            // Update visual selection
            document.querySelectorAll('.brand-option').forEach(option => {
                if (option.getAttribute('data-brand') === brandName) {
                    option.classList.add('selected');
                } else {
                    option.classList.remove('selected');
                }
            });
            
            updateBrandDisplay();
            toggleBrandDropdown(); // Close dropdown after selection
        }

        // --- Update Brand Display ---
        function updateBrandDisplay() {
            const display = document.getElementById('brand-display');
            
            if (isMultipleBrandsMode) {
                const checkedBoxes = document.querySelectorAll('.brand-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    display.textContent = 'Select Brands';
                    display.classList.remove('has-selection');
                } else {
                    const selectedBrands = Array.from(checkedBoxes).map(cb => cb.value);
                    display.textContent = selectedBrands.join(', ');
                    display.classList.add('has-selection');
                }
            } else {
                if (selectedBrand) {
                    display.textContent = selectedBrand;
                    display.classList.add('has-selection');
                } else {
                    display.textContent = 'Select Brand';
                    display.classList.remove('has-selection');
                }
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('brand-dropdown');
            const customSelect = document.querySelector('.custom-brand-select');
            
            if (dropdown && customSelect && !customSelect.contains(event.target)) {
                dropdown.classList.remove('show');
                document.querySelector('.brand-select-input')?.classList.remove('active');
            }
        });

        // --- Toggle All Brands ---
        function toggleAllBrands() {
            const selectAll = document.getElementById('select-all-brands');
            const checkboxes = document.querySelectorAll('input[name="multiple_brands[]"]');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
            });
            
            updateMultiselectDisplay();
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const multiselect = document.querySelector('.custom-multiselect');
            if (multiselect && !multiselect.contains(event.target)) {
                const dropdown = document.getElementById('multiselect-dropdown');
                const input = document.querySelector('.multiselect-input');
                if (dropdown) {
                    dropdown.classList.remove('show');
                    input.classList.remove('active');
                }
            }
        });

        // --- Add Item Row Function ----------------------------------------
        function addItemRow() {
            rowCount++;
            const tbody = document.getElementById('items-tbody');
            const empty = document.getElementById('item-empty');
            const table = document.getElementById('items-table');

            empty.style.display = 'none';
            table.style.display = 'table';

            const tr = document.createElement('tr');
            tr.id = 'row-' + rowCount;
            tr.innerHTML = `
                <td>${rowCount}</td>
                <td><input type="text" placeholder="Family Code" class="familycode-${rowCount}" onkeydown="handleFamilyCodeKeydown(event, ${rowCount})" onblur="handleFamilyCodeBlur(event, ${rowCount})"></td>
                <td><input type="number" min="0" placeholder="Enter Quantity" style="width:130px;" oninput="recalcRow(${rowCount})" class="qty-${rowCount}"></td>
                <td><input type="text" placeholder="Enter cost" style="width:130px;" oninput="formatCostInput(this, ${rowCount})" class="price-${rowCount}"></td>
                <td id="row-total-${rowCount}">&#8369; 0.00</td>
                <td><button class="btn-remove-row" onclick="removeRow(${rowCount})">Remove</button></td>
            `;
            tbody.appendChild(tr);
            updateSummary();
        }

        // --- Sidebar toggle -----------------------------------------------
        function toggleSidebar() {
            const sidebar     = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn     = document.querySelector('.menu-btn');
            
            // On mobile, toggle the sidebar visibility
            if (window.innerWidth <= 768) {
                if (sidebar.classList.contains('hidden')) {
                    sidebar.classList.remove('hidden');
                    sidebar.style.transform = 'translateX(0)';
                } else {
                    sidebar.classList.add('hidden');
                    sidebar.style.transform = 'translateX(-100%)';
                }
            } else {
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('expanded');
            }
            
            menuBtn.classList.toggle('active');
        }

        // Initialize sidebar state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            if (window.innerWidth <= 768) {
                // On mobile, start with sidebar hidden
                sidebar.classList.add('hidden');
                sidebar.style.transform = 'translateX(-100%)';
            }
        });
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

        // --- Helper Functions ---------------------------------------------

        // Helper function to format numbers with commas
        function formatNumberWithCommas(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // Function to format cost input with commas as user types
        function formatCostInput(input, rowId) {
            let value = input.value.replace(/[^\d.]/g, ''); // Remove non-numeric characters except decimal
            let parts = value.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ','); // Add commas to integer part
            if (parts.length > 2) {
                parts = [parts[0], parts.slice(1).join('')]; // Handle multiple decimal points
            }
            if (parts[1] && parts[1].length > 2) {
                parts[1] = parts[1].substring(0, 2); // Limit to 2 decimal places
            }
            input.value = parts.join('.');
            recalcRow(rowId);
        }

        function handleFamilyCodeKeydown(event, rowId) {
            if (event.key === 'Enter') {
                event.preventDefault();
                const familyCodeInput = document.querySelector(`.familycode-${rowId}`);
                const searchTerm = familyCodeInput.value.trim();
                
                if (searchTerm === '') {
                    alert('Please enter a family code to search!');
                    return;
                }
                
                currentSearchRowId = rowId;
                performSearch(searchTerm);
            }
        }

        function handleFamilyCodeBlur(event, rowId) {
            const familyCodeInput = event.target;
            const familyCode = familyCodeInput.value.trim();
            
            if (familyCode) {
                // Check if family code already exists in other rows
                const existingFamilyCodes = [];
                const familyCodeInputs = document.querySelectorAll('[class*="familycode-"]');
                familyCodeInputs.forEach(input => {
                    const value = input.value.trim();
                    if (value && input !== familyCodeInput) {
                        existingFamilyCodes.push(value.toUpperCase());
                    }
                });
                
                if (existingFamilyCodes.includes(familyCode.toUpperCase())) {
                    alert('This family code "' + familyCode + '" is already added to the purchase order!');
                    familyCodeInput.value = '';
                    familyCodeInput.focus();
                    return;
                }
            }
        }

        function performSearch(searchTerm) {
            const modal = document.getElementById('searchItemModal');
            const resultsBody = document.getElementById('searchResultsBody');
            
            // Get selected brands
            let selectedBrandsParam = '';
            if (isMultipleBrandsMode) {
                const checkedBoxes = document.querySelectorAll('.brand-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one brand before searching for items.');
                    return;
                }
                const brands = Array.from(checkedBoxes).map(cb => cb.value);
                selectedBrandsParam = brands.join(',');
            } else {
                if (!selectedBrand) {
                    alert('Please select a brand before searching for items.');
                    return;
                }
                selectedBrandsParam = selectedBrand;
            }
            
            // Show loading state
            resultsBody.innerHTML = '<tr><td colspan="2" style="text-align:center; padding: 20px;">Searching...</td></tr>';
            modal.style.display = 'flex';
            
            console.log('Searching with term:', searchTerm, 'and brands:', selectedBrandsParam);
            
            // Fetch results from search_familycode.php with brand filter
            fetch(`search_familycode.php?term=${encodeURIComponent(searchTerm)}&brands=${encodeURIComponent(selectedBrandsParam)}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Search response:', data);
                    resultsBody.innerHTML = '';
                    if (data.status === 'success' && data.data.length > 0) {
                        currentSearchResults = data.data;
                        data.data.forEach((item, index) => {
                            const row = `
                                <tr>
                                    <td>${item.family_code || ''}</td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-select" onclick="selectItem(${index})">Select</button>
                                    </td>
                                </tr>
                            `;
                            resultsBody.insertAdjacentHTML('beforeend', row);
                        });
                    } else {
                        const message = data.message || 'No family codes found for the selected brand(s)';
                        resultsBody.innerHTML = `<tr><td colspan="2" style="text-align:center; padding: 20px;">${message}</td></tr>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsBody.innerHTML = '<tr><td colspan="2" style="text-align:center; padding: 20px;">Error occurred while searching</td></tr>';
                });
        }

        function selectItem(index) {
            if (currentSearchRowId && currentSearchResults[index]) {
                const selectedItem = currentSearchResults[index];
                const selectedFamilyCode = selectedItem.family_code || '';
                
                // Check if family code already exists in the table
                const existingFamilyCodes = [];
                const familyCodeInputs = document.querySelectorAll('[class*="familycode-"]');
                familyCodeInputs.forEach(input => {
                    const value = input.value.trim();
                    if (value && input !== document.querySelector(`.familycode-${currentSearchRowId}`)) {
                        existingFamilyCodes.push(value.toUpperCase());
                    }
                });
                
                if (existingFamilyCodes.includes(selectedFamilyCode.toUpperCase())) {
                    alert('This family code "' + selectedFamilyCode + '" is already added to the purchase order!');
                    closeSearchModal();
                    return;
                }
                
                const familyCodeInput = document.querySelector(`.familycode-${currentSearchRowId}`);
                
                // Fill the family code
                familyCodeInput.value = selectedFamilyCode;
                
                closeSearchModal();
                
                // Focus on quantity field
                const qtyInput = document.querySelector(`.qty-${currentSearchRowId}`);
                if (qtyInput) {
                    qtyInput.focus();
                }
            }
        }

        function closeSearchModal() {
            const modal = document.getElementById('searchItemModal');
            modal.style.display = 'none';
            currentSearchRowId = null;
            currentSearchResults = [];
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('searchItemModal');
            if (event.target == modal) {
                closeSearchModal();
            }
        }

        function removeRow(id) {
            const row = document.getElementById('row-' + id);
            if (row) row.remove();
            updateSummary();
            checkEmpty();
        }

        function recalcRow(id) {
            const qty   = parseFloat(document.querySelector('.qty-'   + id)?.value) || 0;
            const costValue = document.querySelector('.price-' + id)?.value || '0';
            const cost  = parseFloat(costValue.replace(/,/g, '')) || 0;
            const total = qty * cost;
            
            const totalCell = document.getElementById('row-total-' + id);
            if (totalCell) {
                totalCell.textContent = '₱ ' + formatNumberWithCommas(total.toFixed(2));
            }
            updateSummary();
        }

        function updateSummary() {
            let totalQty = 0;
            let grandTotal = 0;
            
            const rows = document.querySelectorAll('#items-tbody tr');
            rows.forEach(row => {
                const id = row.id.replace('row-', '');
                const qty = parseFloat(document.querySelector('.qty-' + id)?.value) || 0;
                const costValue = document.querySelector('.price-' + id)?.value || '0';
                const cost = parseFloat(costValue.replace(/,/g, '')) || 0;
                
                totalQty += qty;
                grandTotal += (qty * cost);
            });
            
            // Update summary display
            const itemsElement = document.getElementById('summary-items');
            const qtyElement = document.getElementById('summary-qty');
            const totalElement = document.querySelector('.summary-total .total-amount');
            
            if (itemsElement) itemsElement.textContent = rows.length;
            if (qtyElement) qtyElement.textContent = totalQty;
            if (totalElement) totalElement.textContent = '₱ ' + formatNumberWithCommas(grandTotal.toFixed(2));
        }

        function checkEmpty() {
            const rows = document.querySelectorAll('#items-tbody tr');
            const empty = document.getElementById('item-empty');
            const table = document.getElementById('items-table');
            
            if (rows.length === 0) {
                empty.style.display = 'block';
                table.style.display = 'none';
            } else {
                empty.style.display = 'none';
                table.style.display = 'table';
            }
        }

        // --- Submit PO ------------------------------------------------
        function submitPO() {
            // Get values from searchable select inputs instead of select elements
            const supplierCompanyInput = document.querySelector('#supplier_company').parentNode.querySelector('.select-search-input');
            
            const supplier_company = supplierCompanyInput ? supplierCompanyInput.value.trim() : document.getElementById('supplier_company').value.trim();
            const terms            = document.getElementById('terms').value.trim();
            const payment_due_date = document.getElementById('payment_due_date').value.trim();
            const remarks          = document.getElementById('remarks').value.trim();

            if (!supplier_company) {
                alert('Please enter the Supplier Company Name.');
                if (supplierCompanyInput) {
                    supplierCompanyInput.focus();
                } else {
                    document.getElementById('supplier_company').focus();
                }
                return;
            }

            // Validate brand selection
            let selectedBrands = [];
            let brandType = 'single';
            
            if (isMultipleBrandsMode) {
                brandType = 'multiple';
                const checkedBoxes = document.querySelectorAll('.brand-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one brand.');
                    return;
                }
                checkedBoxes.forEach(cb => selectedBrands.push(cb.value));
            } else {
                if (!selectedBrand) {
                    alert('Please select a brand.');
                    return;
                }
                selectedBrands.push(selectedBrand);
            }

            // Collect items from table
            const rows  = document.querySelectorAll('#items-tbody tr');
            if (rows.length === 0) {
                alert('Please add at least one item before saving.');
                return;
            }

            const items = [];
            let valid = true;
            let validationPromises = [];
            
            rows.forEach(row => {
                const id = row.id.replace('row-', '');
                const familyCodeInput = row.querySelector(`input.familycode-${id}`);
                const family_code = familyCodeInput?.value.trim() || '';
                
                const qty_el = row.querySelector(`.qty-${id}`);
                const price_el = row.querySelector(`.price-${id}`);
                
                const quantity = parseFloat(qty_el?.value) || 0;
                const costValue = price_el?.value ? price_el.value.replace(/,/g, '') : '0'; // Remove commas for calculation
                const cost = parseFloat(costValue) || 0;

                if (!family_code) {
                    alert(`Row ${id}: Please enter or select a Family Code.`);
                    valid = false;
                    return;
                }

                if (quantity <= 0) {
                    alert(`Row ${id}: Quantity must be greater than 0.`);
                    valid = false;
                    return;
                }
                
                if (cost < 0) {
                    alert(`Row ${id}: Cost cannot be negative.`);
                    valid = false;
                    return;
                }
                
                // Add validation promise for each family code
                const validationPromise = fetch(`validate_family_code.php?family_code=${encodeURIComponent(family_code)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'error' || !data.exists) {
                            alert(`Row ${id}: ${data.message || 'Family code does not exist in the system'}`);
                            valid = false;
                            return false;
                        }
                        return true;
                    })
                    .catch(error => {
                        console.error('Error validating family code:', error);
                        alert(`Row ${id}: Error validating family code`);
                        valid = false;
                        return false;
                    });
                
                validationPromises.push(validationPromise);
                items.push({ family_code, quantity, cost, rowId: id });
            });

            if (!valid) return;

            // Wait for all validations to complete
            Promise.all(validationPromises).then(results => {
                // Check if all validations passed
                if (!results.every(result => result === true)) {
                    return;
                }

                const btn = document.querySelector('.btn-create-po-submit');
                btn.disabled    = true;
                btn.textContent = 'Saving...';

                const formData = new FormData();
                formData.append('supplier_company',  supplier_company);
                formData.append('terms',             terms);
                formData.append('payment_due_date',  payment_due_date);
                formData.append('remarks',           remarks);
                formData.append('brand_type',        brandType);
                formData.append('selected_brands',   JSON.stringify(selectedBrands));
                formData.append('items',             JSON.stringify(items));

                fetch('save_purchase_order_simple.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Purchase Order ' + data.po_number + ' saved successfully!');
                        window.location.href = data.redirect;
                    } else {
                        alert('Error: ' + (data.message || 'Could not save Purchase Order.'));
                        btn.disabled    = false;
                        btn.textContent = 'Create Purchase Order';
                    }
                })
                .catch(err => {
                    alert('Network error: ' + err.message);
                    btn.disabled    = false;
                    btn.textContent = 'Create Purchase Order';
                });
            });
        }

        // --- Initialize Event Listeners ----------------------------------
        document.addEventListener('DOMContentLoaded', function() {
            const addItemBtn = document.getElementById('add-item-btn');
            if (addItemBtn) {
                addItemBtn.addEventListener('click', addItemRow);
            }
            
            // Initialize searchable selects
            initializeSearchableSelects();
        });

        // --- Searchable Select Functionality -----------------------------
        function initializeSearchableSelects() {
            document.querySelectorAll('.searchable-select').forEach(function(select) {
                const wrapper = document.createElement('div');
                wrapper.className = 'select-wrapper';
                select.parentNode.insertBefore(wrapper, select);
                
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'select-search-input';
                input.placeholder = select.options[0].text;
                
                const dropdown = document.createElement('div');
                dropdown.className = 'select-dropdown';
                
                wrapper.appendChild(input);
                wrapper.appendChild(dropdown);
                select.style.display = 'none';
                wrapper.appendChild(select);
                
                // Show dropdown on focus
                input.addEventListener('focus', function() {
                    dropdown.classList.add('active');
                    if (select.id === 'supplier_company') {
                        // Load companies if dropdown is empty
                        if (dropdown.children.length === 0) {
                            loadSuppliers(dropdown, select, input);
                        }
                    }
                });
                
                // Filter options on input
                input.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    select.value = '';
                    
                    if (select.id === 'supplier_company') {
                        // Search company names
                        if (searchTerm.length >= 2) {
                            searchSuppliers(searchTerm, dropdown, select, input);
                        } else if (searchTerm.length === 0) {
                            loadSuppliers(dropdown, select, input);
                        }
                    }
                    
                    dropdown.classList.add('active');
                });
                
                // Select option on click
                dropdown.addEventListener('click', function(e) {
                    if (e.target.classList.contains('select-option')) {
                        const selectedValue = e.target.dataset.value;
                        const selectedText = e.target.textContent;
                        
                        input.value = selectedText;
                        select.value = selectedValue;
                        
                        console.log('Option selected:', selectedText, 'Value:', selectedValue);
                        console.log('Select element value set to:', select.value);
                        console.log('Input element value set to:', input.value);
                        
                        dropdown.querySelectorAll('.select-option').forEach(opt => opt.classList.remove('selected'));
                        e.target.classList.add('selected');
                        dropdown.classList.remove('active');
                    }
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!wrapper.contains(e.target)) {
                        dropdown.classList.remove('active');
                    }
                });
            });
        }

        function loadSuppliers(dropdown, select, input) {
            dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Loading...</div>';
            
            if (select.id === 'supplier_company') {
                // Load unique company names
                fetch('search_supplier.php?type=company&term=')
                    .then(response => response.json())
                    .then(data => {
                        dropdown.innerHTML = '';
                        if (data.status === 'success' && data.data.length > 0) {
                            data.data.forEach(company => {
                                const option = document.createElement('div');
                                option.className = 'select-option';
                                option.textContent = company.store_name;
                                option.dataset.value = company.store_name;
                                dropdown.appendChild(option);
                            });
                        } else {
                            dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">No companies found</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading companies:', error);
                        dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Error loading companies</div>';
                    });
            }
        }

        function searchSuppliers(searchTerm, dropdown, select, input) {
            dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Searching...</div>';
            
            if (select.id === 'supplier_company') {
                // Search company names
                fetch(`search_supplier.php?type=company&term=${encodeURIComponent(searchTerm)}`)
                    .then(response => response.json())
                    .then(data => {
                        dropdown.innerHTML = '';
                        if (data.status === 'success' && data.data.length > 0) {
                            data.data.forEach(company => {
                                const option = document.createElement('div');
                                option.className = 'select-option';
                                option.textContent = company.store_name;
                                option.dataset.value = company.store_name;
                                dropdown.appendChild(option);
                            });
                        } else {
                            dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">No companies found</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error searching companies:', error);
                        dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Error searching companies</div>';
                    });
            }
        }
    </script>
</body>
</html>

