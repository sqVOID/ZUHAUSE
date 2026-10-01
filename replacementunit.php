<?php require_once 'session_check.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <meta name=" viewport" content="width=device-width, initial-scale=1.0">
    <title>Replacement Unit</title>
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

        /* Replacement Unit Content Styles */
        .content-header {
            margin-bottom: 20px;
        }

        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .top-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .box-container {
            background: white;
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 8px;
        }

        .invoice-search {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .invoice-search label {
            font-size: 14px;
            font-weight: bold;
            color: #000;
            white-space: nowrap;
        }

        .invoice-search input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
        }

        .invoice-search input:focus {
            outline: none;
            border-color: #2196F3;
        }

        .btn-search {
            padding: 8px 25px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-search:hover {
            background-color: var(--color-navy-dark);
        }

        .customer-details-box {
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 14px;
            padding: 15px;
            background: #fafafa;
        }

        .customer-details-box.has-data {
            align-items: flex-start;
            justify-content: flex-start;
            background: white;
        }

        .customer-detail-row {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .customer-detail-row .label {
            font-weight: 600;
            min-width: 100px;
        }

        .customer-detail-row .value {
            font-weight: normal;
            color: #333;
        }

        .reason-dropdown {
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .reason-dropdown label {
            font-size: 14px;
            font-weight: bold;
            color: #000;
        }

        .reason-dropdown select {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
            background-color: white;
        }

        .reason-dropdown select:focus {
            outline: none;
            border-color: #2196F3;
        }

        .items-display-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ccc;
        }

        .items-display-table thead {
            background: var(--color-gold-pale);
        }

        .items-display-table th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border: 1px solid #ccc;
        }

        .items-display-table td {
            padding: 12px;
            border: 1px solid #ccc;
            font-size: 13px;
            color: #333;
            text-align: center;
        }

        .items-display-table input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .bottom-section {
            background: white;
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 8px;
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

        .btn-search-item:hover {
            background-color: var(--color-navy-dark);
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

        .btn-add-item:hover {
            background-color: var(--color-gold-light);
        }

        .bottom-section {
            background: white;
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 8px;
        }

        .input-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .input-field label {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .input-field input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: white;
            font-family: Arial, sans-serif;
            width: 100%;
        }

        .input-field input:focus {
            outline: none;
            border-color: #2196F3;
        }

        .input-field input[readonly] {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }

        .button-row {
            display: flex;
            gap: 15px;
        }

        .btn-search-dark {
            padding: 10px 50px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            height: 38px;
        }

        .btn-search-dark:hover {
            background-color: var(--color-navy-dark);
        }

        .btn-add {
            padding: 10px 50px;
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            height: 38px;
        }

        .btn-add:hover {
            background-color: var(--color-gold-light);
        }

        .items-table-container {
            overflow-x: auto;
            margin-bottom: 20px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
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
            font-size: 12px;
            font-weight: bold;
        }

        .btn-delete-item:hover {
            background: #d32f2f;
        }

        .bottom-row {
            display: flex;
            gap: 20px;
        }

        .remarks-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .remarks-section label {
            font-size: 13px;
            color: #333;
            font-weight: 500;
        }

        .remarks-section textarea {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            min-height: 100px;
            resize: vertical;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .remarks-section textarea:focus {
            outline: none;
            border-color: #2196F3;
        }

        .totals-section {
            display: flex;
            flex-direction: column;
            gap: 15px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }

        .total-row {
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .total-row label {
            font-size: 13px;
            color: #333;
            font-weight: 500;
            min-width: 50px;
            text-align: left;
        }

        .total-row input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
            background-color: #f5f5f5;
        }

        .btn-save {
            padding: 10px 40px;
            background-color: var(--color-gold);
            color: white;
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

        .footer-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 0px;
            background: white;
            padding: 20px;
        }

        .footer-right-group {
            display: flex;
            align-items: center;
            gap: 15px;
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

        @media (max-width: 1200px) {
            .top-section {
                grid-template-columns: 1fr;
            }

            .input-fields-grid {
                grid-template-columns: 1fr;
            }

            .bottom-row {
                flex-direction: column;
            }

            .totals-section {
                min-width: 100%;
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
        <!-- <img src="Icon/ZUHAUSE-LOGO.png" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Replacement Unit</h2>
        </div>

        <!-- Top Section -->
        <div class="top-section">
            <!-- Left Box -->
            <div class="box-container">
                <div class="invoice-search">
                    <label>Invoice No:</label>
                    <input type="text" id="invoiceNoInput" placeholder="Enter Invoice">
                    <button class="btn-search" onclick="searchInvoice()">Search</button>
                </div>
                <div class="customer-details-box" id="customerDetailsBox">
                    Enter an invoice number to view customer details
                </div>
                <div class="reason-dropdown">
                    <label>Reason:</label>
                    <select>
                        <option>Defective</option>
                        <option>Customer Request</option>
                        <option>Replacement</option>
                    </select>
                </div>
            </div>

            <!-- Right Box -->
            <div class="box-container">
                <table class="items-display-table">
                    <thead>
                        <tr>
                            <th style="width: 10%;">Select</th>
                            <th style="width: 55%;">Item Description</th>
                            <th style="width: 35%;">IMEI</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceItemsBody">
                        <tr>
                            <td colspan="3" style="text-align: center; color: #666;">No items to display</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bottom Section -->
        <div class="bottom-section">
            <!-- Input Fields -->
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label>Item Model</label>
                    <input type="text" id="item_model_input" placeholder="">
                </div>
                <div class="form-group">
                    <label>IMEI</label>
                    <input type="text" id="imei_input" placeholder="" oninput="this.value = this.value.toUpperCase()">
                </div>
                <div style="flex: 1; display: flex; flex-direction: column; gap: 15px;">
                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <div class="form-group" style="flex: 1;">
                            <label>Quantity</label>
                            <input type="number" id="quantity_input" value="0" min="0" style="text-align: center;">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Price</label>
                            <input type="text" id="price_input" placeholder="" readonly
                                style="background-color: #ffffff; color: #333;">
                        </div>
                        <button type="button" class="btn-search-item" id="search_item_btn"
                            style="margin-bottom: 1px;">Search</button>
                        <button type="button" class="btn-add-item" id="add_item_btn"
                            style="margin-bottom: 1px;">Add</button>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="items-table-container">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Item Description</th>
                            <th style="width: 25%;">IMEI</th>
                            <th style="width: 15%;">Quantity</th>
                            <th style="width: 15%;">Price</th>
                            <th style="width: 15%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <tr id="no-items-row">
                            <td colspan="5" style="text-align:center; padding: 20px; color: #666;">No items added yet
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bottom Row: Remarks and Totals -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Remarks Section (Left) -->
                <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #ccc;">
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea id="remarks_textarea"
                            style="min-height: 150px; resize: vertical; font-family: Arial, sans-serif; font-size: 14px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; width: 100%;"></textarea>
                    </div>
                </div>

                <!-- Totals Section (Right) -->
                <div class="totals-section" style="width: 100%;">
                    <div class="total-row">
                        <label>LESS:</label>
                        <input type="text" id="lessAmount" readonly style="background-color: #f5f5f5;">
                    </div>
                    <div class="total-row">
                        <label>TOTAL:</label>
                        <input type="text" id="totalAmount" readonly style="background-color: #f5f5f5;">
                    </div>
                </div>
            </div>

            <!-- Footer Actions Section (Below the grid) -->
            <div class="footer-actions"
                style="width: 100%; justify-content: flex-end; padding: 20px; background: white; margin-top: 20px; border-radius: 8px; border: 1px solid #ccc;">
                <div class="footer-right-group">
                    <button class="btn-save" onclick="saveReplacement()">SAVE</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Item Modal -->
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

    <script>
        // Version: 2024-10-01-v4 - Added save functionality
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

        // Search for invoice
        function searchInvoice() {
            const invoiceNo = document.getElementById('invoiceNoInput').value.trim();

            if (!invoiceNo) {
                alert('Please enter an invoice number');
                return;
            }

            // Show loading state
            const customerBox = document.getElementById('customerDetailsBox');
            customerBox.innerHTML = 'Loading...';
            customerBox.classList.remove('has-data');

            document.getElementById('invoiceItemsBody').innerHTML = '<tr><td colspan="3" style="text-align: center; color: #666;">Loading...</td></tr>';

            // Fetch invoice details using the existing endpoint
            fetch('get_invoice_details.php?invoice_no=' + encodeURIComponent(invoiceNo))
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        displayInvoiceDetails(data);
                    } else {
                        alert(data.message || 'Invoice not found');
                        resetDisplay();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching invoice details');
                    resetDisplay();
                });
        }

        function displayInvoiceDetails(data) {
            const sale = data.sale;
            const items = data.items || [];

            // Display customer details in upgradeunit.php style
            const customerBox = document.getElementById('customerDetailsBox');
            customerBox.classList.add('has-data');

            const fullName = `${sale.first_name || ''} ${sale.last_name || ''}`.trim();

            let html = '';
            if (fullName) {
                html += `<div class="customer-detail-row"><span class="label">Name:</span><span class="value">${fullName}</span></div>`;
            }
            if (sale.address) {
                html += `<div class="customer-detail-row"><span class="label">Address:</span><span class="value">${sale.address}</span></div>`;
            }
            if (sale.contact_no) {
                html += `<div class="customer-detail-row"><span class="label">Contact:</span><span class="value">${sale.contact_no}</span></div>`;
            }
            if (sale.email) {
                html += `<div class="customer-detail-row"><span class="label">Email:</span><span class="value">${sale.email}</span></div>`;
            }
            if (sale.assisted_by) {
                html += `<div class="customer-detail-row"><span class="label">Assisted By:</span><span class="value">${sale.assisted_by}</span></div>`;
            }
            if (sale.branch_code) {
                html += `<div class="customer-detail-row"><span class="label">Branch:</span><span class="value">${sale.branch_code}</span></div>`;
            }

            customerBox.innerHTML = html || 'No customer details available';

            // Display items with checkboxes
            if (items.length > 0) {
                let itemsHTML = '';
                items.forEach((item, index) => {
                    itemsHTML += `
                        <tr>
                            <td><input type="checkbox" name="item_select" value="${index}" data-imei="${item.imei || ''}" data-item-code="${item.item_code || ''}" data-description="${item.item_description || item.item_code || ''}" data-price="${item.price || 0}" onchange="updateTotals()"></td>
                            <td>${item.item_description || item.item_code || 'N/A'}</td>
                            <td>${item.imei || 'N/A'}</td>
                        </tr>
                    `;
                });
                document.getElementById('invoiceItemsBody').innerHTML = itemsHTML;
            } else {
                document.getElementById('invoiceItemsBody').innerHTML = '<tr><td colspan="3" style="text-align: center; color: #666;">No items found</td></tr>';
            }

            // Initialize totals
            updateTotals();
        }

        function resetDisplay() {
            const customerBox = document.getElementById('customerDetailsBox');
            customerBox.innerHTML = 'Enter an invoice number to view customer details';
            customerBox.classList.remove('has-data');

            document.getElementById('invoiceItemsBody').innerHTML = '<tr><td colspan="3" style="text-align: center; color: #666;">No items to display</td></tr>';

            // Reset totals
            document.getElementById('lessAmount').value = '';
            document.getElementById('totalAmount').value = '';
        }

        // Search Modal Variables
        let currentSearchResults = [];
        const modal = document.getElementById('searchItemModal');
        const resultsBody = document.getElementById('searchResultsBody');

        // Item Search Function
        function performItemSearch() {
            const searchTerm = document.getElementById('item_model_input').value.trim();
            const imei = document.getElementById('imei_input').value.trim();

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

        // Close Search Modal
        function closeSearchModal() {
            modal.style.display = 'none';
        }

        // Select Item Function
        window.selectItem = function (index) {
            const item = currentSearchResults[index];
            if (!item) return;

            const code = item.item_code;
            const description = item.description;
            const price = item.price;

            // Check if item is serialized first
            if (code) {
                fetch(`check_serial_permission.php?item_code=${encodeURIComponent(code)}`)
                    .then(response => response.json())
                    .then(data => {
                        const priceField = document.getElementById('price_input');

                        if (data.status === 'success' && data.has_serial) {
                            // Item is serialized - show alert and clear fields
                            alert('This item is serialized');
                            document.getElementById('item_model_input').value = '';
                            priceField.value = '';
                            document.getElementById('quantity_input').value = '';
                            const imeiField = document.getElementById('imei_input');
                            imeiField.value = '';
                            // Make IMEI field editable after alert (for manual IMEI entry)
                            imeiField.removeAttribute('readonly');
                            imeiField.style.backgroundColor = '#ffffff';
                            imeiField.style.cursor = 'text';
                            closeSearchModal();
                            return;
                        }

                        // Item is not serialized - proceed normally but lock IMEI field
                        document.getElementById('item_model_input').value = code;

                        // Format price with commas
                        if (price) {
                            const formattedPrice = parseFloat(price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            priceField.value = formattedPrice;
                        } else {
                            priceField.value = '';
                        }

                        // Set price field to readonly
                        priceField.setAttribute('readonly', 'readonly');
                        priceField.style.backgroundColor = '#ffffff';
                        priceField.style.color = '#333';
                        priceField.style.cursor = 'not-allowed';

                        document.getElementById('quantity_input').value = '1';

                        // Store item data as data attributes
                        document.getElementById('item_model_input').setAttribute('data-item-code', code);
                        document.getElementById('item_model_input').setAttribute('data-description', description);

                        closeSearchModal();

                        // Lock IMEI field for non-serialized items
                        const imeiField = document.getElementById('imei_input');
                        imeiField.setAttribute('readonly', 'readonly');
                        imeiField.style.backgroundColor = '#f5f5f5';
                        imeiField.style.cursor = 'not-allowed';
                        imeiField.value = '';
                    })
                    .catch(error => {
                        console.error('Error checking serial status:', error);
                        const priceField = document.getElementById('price_input');

                        // Fallback: populate fields anyway with comma formatting
                        document.getElementById('item_model_input').value = code;

                        if (price) {
                            const formattedPrice = parseFloat(price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            priceField.value = formattedPrice;
                        } else {
                            priceField.value = '';
                        }

                        // Set price field to readonly
                        priceField.setAttribute('readonly', 'readonly');
                        priceField.style.backgroundColor = '#ffffff';
                        priceField.style.color = '#333';
                        priceField.style.cursor = 'not-allowed';

                        document.getElementById('quantity_input').value = '1';
                        closeSearchModal();
                    });
            }
        };

        // Add Item Function
        function addItemToTable() {
            const itemModel = document.getElementById('item_model_input').value.trim();
            const imei = document.getElementById('imei_input').value.trim();
            const quantity = parseInt(document.getElementById('quantity_input').value) || 0;
            const priceField = document.getElementById('price_input');
            const price = parseFloat(priceField.value.replace(/,/g, '')) || 0; // Remove commas before parsing

            // Get description from data attribute or use item model as fallback
            const description = document.getElementById('item_model_input').getAttribute('data-description') || itemModel;
            const itemCode = document.getElementById('item_model_input').getAttribute('data-item-code') || itemModel;

            // Validation
            if (!itemModel || !description) {
                alert('Please select or enter an item.');
                return;
            }

            if (quantity <= 0) {
                alert('Please enter a valid quantity.');
                return;
            }

            if (price <= 0) {
                alert('Please enter a valid price.');
                return;
            }

            // VALIDATION: Check if there are selected items to replace
            const selectedCheckboxes = document.querySelectorAll('input[name="item_select"]:checked');
            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one item from the invoice to replace.');
                return;
            }

            // Normalize new item description for comparison
            const normalizedNewDesc = description.trim().toLowerCase();

            // Find all selected items that match this replacement description
            let matchingSelectedItems = [];
            for (let checkbox of selectedCheckboxes) {
                const oldItemDescription = checkbox.dataset.description;
                const normalizedOldDesc = oldItemDescription.trim().toLowerCase();

                if (normalizedOldDesc === normalizedNewDesc) {
                    matchingSelectedItems.push(checkbox);
                }
            }

            // VALIDATION: Check if replacement item matches at least one selected old item
            if (matchingSelectedItems.length === 0) {
                alert(`Replacement item must match one of the selected items!\n\nReplacement item: ${description}\n\nPlease select the exact same item model from the invoice items above.`);
                return;
            }

            // VALIDATION: Check quantity - must be 1 for replacement
            if (quantity !== 1) {
                alert('Replacement quantity must be 1. Please adjust the quantity.');
                document.getElementById('quantity_input').value = '1';
                return;
            }

            // VALIDATION: Check which selected items already have replacements (by tracking old IMEI)
            const itemsTableBody = document.getElementById('itemsTableBody');
            const existingRows = itemsTableBody.querySelectorAll('tr:not(#no-items-row)');
            let replacedOldImeis = new Set();

            for (let row of existingRows) {
                const oldImeiReplaced = row.getAttribute('data-old-imei');
                if (oldImeiReplaced) {
                    replacedOldImeis.add(oldImeiReplaced.toUpperCase());
                }
            }

            // Find the first matching selected item that hasn't been replaced yet
            let matchingSelectedItem = null;
            let oldItemImei = '';

            for (let checkbox of matchingSelectedItems) {
                const checkboxImei = checkbox.dataset.imei || '';
                const checkboxImeiUpper = checkboxImei.toUpperCase();

                // Check if this specific unit hasn't been replaced yet
                if (!replacedOldImeis.has(checkboxImeiUpper)) {
                    matchingSelectedItem = checkbox;
                    oldItemImei = checkboxImei;
                    break;
                }
            }

            // If all matching items already have replacements
            if (!matchingSelectedItem) {
                const totalMatching = matchingSelectedItems.length;
                const totalReplaced = matchingSelectedItems.filter(cb => replacedOldImeis.has((cb.dataset.imei || '').toUpperCase())).length;

                alert(`Replacement limit reached!\n\nYou have already added replacements for all selected units of:\n"${description}"\n\n${totalReplaced} of ${totalMatching} selected units have been replaced.\n\nPlease delete an existing replacement first if you want to change it.`);
                return;
            }

            const oldItemDescription = matchingSelectedItem.dataset.description;

            // VALIDATION: Check for duplicate IMEI in replacement table
            if (imei && imei !== '' && imei.toUpperCase() !== 'N/A') {
                for (let row of existingRows) {
                    const existingImei = row.getAttribute('data-imei');
                    if (existingImei && existingImei.toUpperCase() === imei.toUpperCase()) {
                        alert(`Duplicate IMEI detected!\n\nIMEI "${imei}" has already been added to the replacement table.\n\nPlease use a different unit with a unique IMEI.`);
                        return;
                    }
                }
            }

            // VALIDATION: Check if the IMEI is the same as the old item being replaced (oldItemImei already declared above)
            if (imei && oldItemImei && imei.toUpperCase() === oldItemImei.toUpperCase()) {
                alert(`Cannot use the same IMEI!\n\nThe IMEI "${imei}" is the same as the item being replaced.\n\nPlease use a different unit with a new IMEI for replacement.`);
                return;
            }

            // Check if item is serialized and requires IMEI
            if (itemCode) {
                const imeiAlreadyFound = document.getElementById('imei_input').getAttribute('data-imei-found') === '1';

                if (!imeiAlreadyFound) {
                    fetch(`check_serial_permission.php?item_code=${encodeURIComponent(itemCode)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success' && data.has_serial && !imei) {
                                alert('This item is serialized. Please enter the IMEI first.');
                                document.getElementById('imei_input').focus();
                                return;
                            }
                            proceedWithAddingItem();
                        })
                        .catch(error => {
                            console.error('Error checking serial status:', error);
                            proceedWithAddingItem();
                        });
                } else {
                    proceedWithAddingItem();
                }
            } else {
                proceedWithAddingItem();
            }

            function proceedWithAddingItem() {
                const itemsTableBody = document.getElementById('itemsTableBody');

                // Remove "no items" row if it exists
                const noItemsRow = document.getElementById('no-items-row');
                if (noItemsRow) {
                    noItemsRow.remove();
                }

                // Create new row
                const newRow = document.createElement('tr');
                newRow.innerHTML = `
                    <td>${description}</td>
                    <td>${imei || 'N/A'}</td>
                    <td style="text-align: center;">${quantity}<input type="hidden" class="qty-input" value="${quantity}"></td>
                    <td style="text-align: center;">${price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}<input type="hidden" class="price-input-table" value="${price}"></td>
                    <td style="text-align: center;"><button type="button" class="btn-delete-item" onclick="removeItemRow(this)">X</button></td>
                `;

                // Store item data including the old IMEI being replaced
                newRow.setAttribute('data-item-code', itemCode || '');
                newRow.setAttribute('data-description', description);
                newRow.setAttribute('data-imei', imei);
                newRow.setAttribute('data-old-imei', oldItemImei); // Track which old unit is being replaced

                itemsTableBody.appendChild(newRow);

                // Clear input fields
                clearInputFields();

                // Update totals
                updateTotals();

                // Show success message
                alert('Replacement item added successfully!');
            }
        }

        // Remove Item Row Function
        window.removeItemRow = function (btn) {
            const row = btn.closest('tr');

            // Get the old IMEI that was being replaced
            const oldImeiReplaced = row.getAttribute('data-old-imei');

            // Find and uncheck the corresponding checkbox in the invoice items
            if (oldImeiReplaced) {
                const checkboxes = document.querySelectorAll('input[name="item_select"]');
                checkboxes.forEach(checkbox => {
                    const checkboxImei = checkbox.dataset.imei || '';
                    if (checkboxImei.toUpperCase() === oldImeiReplaced.toUpperCase()) {
                        checkbox.checked = false;
                    }
                });
            }

            row.remove();

            const itemsTableBody = document.getElementById('itemsTableBody');

            // If table is empty, show "no items" row
            if (itemsTableBody.children.length === 0) {
                itemsTableBody.innerHTML = '<tr id="no-items-row"><td colspan="5" style="text-align:center; padding: 20px; color: #666;">No items added yet</td></tr>';
            }

            updateTotals();
        };

        // Clear Input Fields Function
        function clearInputFields() {
            document.getElementById('item_model_input').value = '';
            document.getElementById('imei_input').value = '';
            document.getElementById('quantity_input').value = '';
            document.getElementById('price_input').value = '';

            // Re-enable price field when clearing
            const priceField = document.getElementById('price_input');
            priceField.removeAttribute('readonly');
            priceField.style.backgroundColor = '#ffffff';
            priceField.style.color = '#333';
            priceField.style.cursor = 'text';

            // Remove data attributes
            document.getElementById('item_model_input').removeAttribute('data-item-code');
            document.getElementById('item_model_input').removeAttribute('data-description');
            document.getElementById('imei_input').removeAttribute('data-imei-found');

            // Reset IMEI field
            const imeiField = document.getElementById('imei_input');
            imeiField.removeAttribute('readonly');
            imeiField.style.backgroundColor = '#ffffff';
            imeiField.style.cursor = 'text';
        }

        // Update Totals Function
        function updateTotals() {
            // Calculate total from new items in the replacement table
            const itemsTableBody = document.getElementById('itemsTableBody');
            let newItemsTotal = 0;

            const rows = itemsTableBody.querySelectorAll('tr:not(#no-items-row)');
            rows.forEach(row => {
                const qtyInput = row.querySelector('.qty-input');
                const priceInput = row.querySelector('.price-input-table');

                if (qtyInput && priceInput) {
                    const qty = parseInt(qtyInput.value) || 0;
                    const price = parseFloat(priceInput.value) || 0;
                    newItemsTotal += (qty * price);
                }
            });

            // Calculate less amount from selected old items (checkboxes)
            const checkboxes = document.querySelectorAll('input[name="item_select"]:checked');
            let lessAmount = 0;

            checkboxes.forEach(checkbox => {
                const price = parseFloat(checkbox.dataset.price) || 0;
                lessAmount += price;
            });

            // Calculate total: new items - less amount
            const totalAmount = newItemsTotal - lessAmount;

            // Update the fields with formatted currency
            document.getElementById('lessAmount').value = formatCurrency(lessAmount);
            document.getElementById('totalAmount').value = formatCurrency(totalAmount);
        }

        // Format number as currency (with commas and 2 decimal places)
        function formatCurrency(amount) {
            return amount.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // IMEI Search Function (same as upgradeunit.php)
        function searchByIMEI(imei) {
            fetch(`search_imei.php?imei=${encodeURIComponent(imei)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const priceField = document.getElementById('price_input');
                        const rawPrice = data.data.price || '';

                        // Populate fields with found data
                        document.getElementById('item_model_input').value = data.data.item_code || ''; // Show item_code, not description

                        // Format price with commas
                        if (rawPrice) {
                            const formattedPrice = parseFloat(rawPrice).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            priceField.value = formattedPrice;
                        } else {
                            priceField.value = '';
                        }

                        document.getElementById('quantity_input').value = '1'; // Set quantity to 1 when IMEI is found

                        // Mark IMEI as found
                        document.getElementById('imei_input').setAttribute('data-imei-found', '1');

                        // Set price field to readonly
                        priceField.setAttribute('readonly', 'readonly');
                        priceField.style.backgroundColor = '#ffffff';
                        priceField.style.color = '#333';
                        priceField.style.cursor = 'not-allowed';

                        // Store item code and description in item_model_input for later use
                        document.getElementById('item_model_input').setAttribute('data-item-code', data.data.item_code || '');
                        document.getElementById('item_model_input').setAttribute('data-description', data.data.description || '');

                    } else {
                        alert(data.message || 'IMEI not found in stock');
                        // Clear fields on error
                        document.getElementById('item_model_input').value = '';
                        document.getElementById('price_input').value = '';
                        document.getElementById('quantity_input').value = ''; // Keep quantity blank on error

                        // Re-enable price field when clearing
                        const priceField = document.getElementById('price_input');
                        priceField.removeAttribute('readonly');
                        priceField.style.backgroundColor = '#ffffff';
                        priceField.style.cursor = 'text';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while searching IMEI.');
                    // Clear fields on error
                    document.getElementById('item_model_input').value = '';
                    document.getElementById('price_input').value = '';
                    document.getElementById('quantity_input').value = ''; // Keep quantity blank on error
                });
        }

        // Allow Enter key to trigger search
        document.addEventListener('DOMContentLoaded', function () {
            // Invoice search on Enter
            document.getElementById('invoiceNoInput').addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    searchInvoice();
                }
            });

            // IMEI search on Enter
            const imeiInput = document.getElementById('imei_input');
            if (imeiInput) {
                imeiInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        const imei = imeiInput.value.trim();
                        if (imei !== '') {
                            searchByIMEI(imei);
                        } else {
                            alert('Please enter an IMEI number');
                        }
                    }
                });
            }

            // Search button click event
            const searchBtn = document.getElementById('search_item_btn');
            if (searchBtn) {
                searchBtn.addEventListener('click', performItemSearch);
            }

            // Item Model input Enter key event
            const itemModelInput = document.getElementById('item_model_input');
            if (itemModelInput) {
                itemModelInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        performItemSearch();
                    }
                });
            }

            // Add button click event
            const addBtn = document.getElementById('add_item_btn');
            if (addBtn) {
                addBtn.addEventListener('click', addItemToTable);
            }

            // Close modal when clicking outside
            window.onclick = function (event) {
                const searchModal = document.getElementById('searchItemModal');
                if (event.target == searchModal) {
                    closeSearchModal();
                }
            };
        });

        // Save Replacement Function
        function saveReplacement() {
            // Validate invoice number
            const invoiceNo = document.getElementById('invoiceNoInput').value.trim();
            if (!invoiceNo) {
                alert('Please search and select an invoice first.');
                return;
            }

            // Validate selected items (old items to replace)
            const selectedCheckboxes = document.querySelectorAll('input[name="item_select"]:checked');
            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one item to replace from the invoice.');
                return;
            }

            // Validate replacement items table
            const itemsTableBody = document.getElementById('itemsTableBody');
            const replacementRows = itemsTableBody.querySelectorAll('tr:not(#no-items-row)');
            if (replacementRows.length === 0) {
                alert('Please add at least one replacement item.');
                return;
            }

            // Get reason dropdown
            const reasonSelect = document.querySelector('.reason-dropdown select');
            const reason = reasonSelect ? reasonSelect.value : '';
            if (!reason) {
                alert('Please select a reason for replacement.');
                return;
            }

            // Get remarks
            const remarks = document.getElementById('remarks_textarea').value.trim();

            // Collect old items (selected for replacement)
            const oldItems = [];
            selectedCheckboxes.forEach(checkbox => {
                oldItems.push({
                    description: checkbox.dataset.description || '',
                    item_code: checkbox.dataset.itemCode || '',
                    imei: checkbox.dataset.imei || '',
                    price: parseFloat(checkbox.dataset.price) || 0
                });
            });

            // Collect new replacement items
            const newItems = [];
            replacementRows.forEach(row => {
                const itemCode = row.getAttribute('data-item-code') || '';
                const description = row.getAttribute('data-description') || '';
                const imei = row.getAttribute('data-imei') || '';
                const qtyInput = row.querySelector('.qty-input');
                const priceInput = row.querySelector('.price-input-table');

                const quantity = qtyInput ? parseInt(qtyInput.value) : 1;
                const price = priceInput ? parseFloat(priceInput.value) : 0;

                newItems.push({
                    item_code: itemCode,
                    description: description,
                    imei: imei,
                    quantity: quantity,
                    price: price
                });
            });

            // Get totals
            const lessAmount = parseFloat(document.getElementById('lessAmount').value.replace(/,/g, '')) || 0;
            const totalAmount = parseFloat(document.getElementById('totalAmount').value.replace(/,/g, '')) || 0;

            // Generate replacement number (format: REP-YYYYMMDD-####)
            const today = new Date();
            const dateStr = today.getFullYear() +
                String(today.getMonth() + 1).padStart(2, '0') +
                String(today.getDate()).padStart(2, '0');
            const randomNum = String(Math.floor(Math.random() * 10000)).padStart(4, '0');
            const replacementNo = 'REP-' + dateStr + '-' + randomNum;

            // Prepare data to send
            const data = {
                replacement_no: replacementNo,
                invoice_no: invoiceNo,
                reason: reason,
                remarks: remarks,
                old_items: oldItems,
                new_items: newItems,
                less_amount: lessAmount,
                total_amount: totalAmount
            };

            // Show confirmation
            if (!confirm(`Are you sure you want to save this replacement?\n\nReplacement No: ${replacementNo}\nInvoice No: ${invoiceNo}\nTotal: ${formatCurrency(totalAmount)}`)) {
                return;
            }

            // Disable save button to prevent double submission
            const saveBtn = event.target;
            saveBtn.disabled = true;
            saveBtn.textContent = 'SAVING...';

            // Send data to server
            fetch('save_replacement.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(result => {
                    if (result.status === 'success') {
                        alert('Replacement saved successfully!\n\nReplacement No: ' + result.replacement_no);
                        // Redirect or reset form
                        window.location.href = 'replacementunit.php';
                    } else {
                        alert('Error saving replacement: ' + (result.message || 'Unknown error'));
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'SAVE';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving. Please try again.');
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'SAVE';
                });
        }
    </script>
</body>

</html>