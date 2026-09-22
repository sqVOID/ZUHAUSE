<?php require_once 'session_check.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
   <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Claim Item</title>
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

        /* Claim Item Content Styles */
        .content-header {
            margin-bottom: 20px;
        }

        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .top-section-container {
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

        .search-invoice-box {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .search-invoice-box label {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            white-space: nowrap;
        }

        .search-invoice-box input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
        }

        .search-invoice-box input:focus {
            outline: none;
            border-color: #2196F3;
        }

        .btn-search {
            padding: 10px 50px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            height: 38px;
        }

        .btn-search:hover {
            background-color: var(--color-navy-dark);
        }

        .info-panel {
            border: 1px solid #dcdcdc;
            border-radius: 2px;
            min-height: 200px;
            display: flex;
            flex-direction: column;
        }

        .info-panel-header {
            text-align: center;
            padding: 12px;
            font-weight: bold;
            border-bottom: 1px solid #dcdcdc;
            font-size: 14px;
            color: #000;
        }

        .info-panel-body {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
        }

        .unclaimed-item-card {
            padding: 12px;
            margin-bottom: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            background: #fafafa;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .unclaimed-item-card:last-child {
            margin-bottom: 0;
        }

        .unclaimed-item-checkbox {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .unclaimed-item-content {
            flex: 1;
        }

        .unclaimed-item-title {
            font-weight: bold;
            font-size: 14px;
            color: #333;
            margin-bottom: 5px;
        }

        .unclaimed-item-detail {
            font-size: 13px;
            color: #666;
            margin: 3px 0;
        }

        .customer-detail-row {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .customer-detail-row:last-child {
            border-bottom: none;
        }

        .customer-detail-label {
            font-weight: 600;
            color: #333;
            display: inline-block;
            min-width: 80px;
        }

        .customer-detail-value {
            color: #666;
        }

        .bottom-container {
            background: white;
            border: 1px solid #e0e0e0;
            padding: 20px;
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .inputs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .input-row {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 15px;
        }

        .input-row label {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .input-row input {
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            width: 100%;
        }

        .input-row input:focus {
            outline: none;
            border-color: #2196F3;
        }

        .input-with-button {
            display: flex;
            gap: 15px;
            width: 100%;
            align-items: center;
        }

        .input-with-button input {
            flex: 1;
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
        }

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
            color: #000;
            border: 1px solid #ccc;
        }

        .items-table td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
        }

        .bottom-action-row {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .bottom-action-row label {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .bottom-action-row textarea {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            min-height: 80px;
            resize: vertical;
            width: 100%;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .bottom-action-row textarea:focus {
            outline: none;
            border-color: #2196F3;
        }

        .bottom-action-row .btn-save {
            align-self: flex-start;
        }

        .remarks-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
        }

        .remarks-container textarea {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 80px;
            resize: vertical;
            width: 100%;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .remarks-container textarea:focus {
            outline: none;
            border-color: #2196F3;
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
            width: max-content;
        }

        .btn-save:hover {
            background-color: #558b2f;
        }

        @media (max-width: 1024px) {

            .top-section-container,
            .inputs-grid {
                grid-template-columns: 1fr;
            }

            .remarks-container {
                max-width: 100%;
            }
        }

        .main-content.expanded {
            margin-left: 0;
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
            color: #000;
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

        .btn-delete-item:hover {
            background: #d32f2f;
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
            <h2>Claim Item</h2>
        </div>

        <div class="top-section-container">
            <!-- Left Box -->
            <div class="box-container">
                <div class="search-invoice-box">
                    <label>Invoice No:</label>
                    <input type="text" id="invoiceInput" placeholder="Enter Invoice">
                    <button class="btn-search" onclick="searchUnclaimedFreebies()">Search</button>
                </div>
                <div class="info-panel">
                    <div class="info-panel-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Unclaimed Item</span>
                        <button id="selectAllBtn" 
                                onclick="toggleSelectAll()" 
                                style="display: none; padding: 4px 12px; font-size: 12px; background: #2e7d32; color: white; border: none; border-radius: 3px; cursor: pointer;">
                            Select All
                        </button>
                    </div>
                    <div class="info-panel-body" id="unclaimedItemsPanel">
                        <!-- Unclaimed items will be displayed here -->
                    </div>
                </div>
            </div>

            <!-- Right Box -->
            <div class="box-container">
                <!-- Set height 100% to match tall left box with search row -->
                <div class="info-panel" style="height: 100%;">
                    <div class="info-panel-header">Customer Details</div>
                    <div class="info-panel-body" id="customerDetailsPanel">
                        <!-- Customer details will be displayed here -->
                    </div>
                </div>
            </div>
        </div>

        <div class="bottom-container">
            <!-- Info message
             
            <div id="claimInfoMessage" style="display: none; padding: 12px; background-color: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px; margin-bottom: 15px;">
                <strong>ℹ️ Important:</strong> You can only add items that are selected (checked) from the unclaimed items list above. The quantity cannot exceed the unclaimed quantity.
            </div>
    -->
            <div class="inputs-grid">
                <!-- Middle Section: Left Inputs -->
                <div class="left-inputs">
                    <div class="input-row">
                        <label>Item Code:</label>
                        <input type="text" id="itemCode" placeholder="" style="text-transform: uppercase;">
                    </div>
                    <div class="input-row">
                        <label>Item Description:</label>
                        <input type="text" id="description" placeholder="" readonly style="background-color: #ffffffff; cursor: not-allowed; color: #333;">
                    </div>
                </div>
                <!-- Middle Section: Right Inputs -->
                <div class="right-inputs">
                    <div class="input-row">
                        <label>IMEI:</label>
                        <input type="text" id="imei" placeholder="">
                    </div>
                    <div class="input-row">
                        <label>Quantity:</label>
                        <div class="input-with-button">
                            <input type="number" id="quantity" placeholder="" value="0">
                            <button class="btn-search">Search</button>
                            <button class="btn-add">Add</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="items-table-container">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Item Description</th>
                            <th style="width: 30%;">IMEI</th>
                            <th style="width: 20%;">Quantity</th>
                            <th style="width: 20%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Remarks and Save Section -->
            <div class="bottom-action-row">
                <label>Remarks:</label>
                <textarea id="remarksTextarea" placeholder="Enter remarks (optional)"></textarea>
                <button class="btn-save" onclick="saveClaimedItems()">Save</button>
            </div>
        </div>
    </div>

    <!-- Search Item Modal -->
    <div id="searchItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Search Claim Item
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
        // Stock validation added - Version 2.0
        // UNCLAIMED ITEM RESTRICTION - Version 3.0
        let currentUnclaimedItems = [];
        let currentCustomerDetails = null;
        let currentSearchResults = [];
        let claimedItemsTable = []; // Array to store items added to the table
        let selectedUnclaimedItems = []; // Track selected unclaimed items with their allowed quantities

        const searchItemBtn = document.querySelector('.btn-search');
        const itemCodeInput = document.getElementById('itemCode');
        const descriptionInput = document.getElementById('description');
        const imeiInput = document.getElementById('imei');
        const quantityInput = document.getElementById('quantity');
        const modal = document.getElementById('searchItemModal');
        const resultsBody = document.getElementById('searchResultsBody');

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

        function searchUnclaimedFreebies() {
            const invoiceNo = document.getElementById('invoiceInput').value.trim();
            
            if (!invoiceNo) {
                alert('Please enter an invoice number');
                return;
            }

            // Show loading state
            document.getElementById('unclaimedItemsPanel').innerHTML = '<p style="text-align: center; padding: 20px;">Searching...</p>';
            document.getElementById('customerDetailsPanel').innerHTML = '';
            document.getElementById('selectAllBtn').style.display = 'none';
            
            // Clear previous selections
            selectedUnclaimedItems = [];
            claimedItemsTable = [];
            renderItemsTable();

            // Call backend API
            fetch('search_unclaimed_freebies.php?invoice_no=' + encodeURIComponent(invoiceNo))
                .then(response => {
                    // Check if response is ok
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.text(); // Get as text first to debug
                })
                .then(text => {
                    // Try to parse as JSON
                    try {
                        const data = JSON.parse(text);
                        if (data.status === 'success') {
                            currentUnclaimedItems = data.unclaimed_items;
                            currentCustomerDetails = data.customer_details;
                            displayUnclaimedItems(data.unclaimed_items);
                            displayCustomerDetails(data.customer_details);
                        } else if (data.status === 'all_claimed') {
                            document.getElementById('unclaimedItemsPanel').innerHTML = 
                                '<div style="text-align: center; padding: 30px;">' +
                                '<div style="font-size: 40px; margin-bottom: 10px;">✅</div>' +
                                '<p style="color: #2e7d32; font-weight: bold; font-size: 15px;">All Freebies Already Claimed</p>' +
                                '<p style="color: #666; font-size: 13px; margin-top: 6px;">All freebies for this invoice have already been claimed.</p>' +
                                '</div>';
                            document.getElementById('customerDetailsPanel').innerHTML = '';
                            document.getElementById('selectAllBtn').style.display = 'none';
                            currentUnclaimedItems = [];
                            currentCustomerDetails = null;
                        } else if (data.status === 'not_found') {
                            document.getElementById('unclaimedItemsPanel').innerHTML = 
                                '<p style="text-align: center; padding: 20px; color: #666;">' + data.message + '</p>';
                            document.getElementById('customerDetailsPanel').innerHTML = '';
                            currentUnclaimedItems = [];
                            currentCustomerDetails = null;
                        } else {
                            alert('Error: ' + data.message);
                            document.getElementById('unclaimedItemsPanel').innerHTML = '';
                            document.getElementById('customerDetailsPanel').innerHTML = '';
                        }
                    } catch (parseError) {
                        console.error('JSON Parse Error:', parseError);
                        console.error('Response text:', text);
                        alert('Error: Invalid response from server. Check console for details.');
                        document.getElementById('unclaimedItemsPanel').innerHTML = 
                            '<p style="text-align: center; padding: 20px; color: #d32f2f;">Error parsing server response. Please check browser console.</p>';
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    alert('An error occurred while searching: ' + error.message);
                    document.getElementById('unclaimedItemsPanel').innerHTML = '';
                    document.getElementById('customerDetailsPanel').innerHTML = '';
                });
        }

        function getSelectedItemsCount() {
            const checkboxes = document.querySelectorAll('.unclaimed-item-checkbox:checked');
            return checkboxes.length;
        }

        function displayUnclaimedItems(items) {
            const panel = document.getElementById('unclaimedItemsPanel');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const infoMessage = document.getElementById('claimInfoMessage');
            
            if (!items || items.length === 0) {
                panel.innerHTML = '<p style="text-align: center; padding: 20px; color: #666;">No items found for this invoice</p>';
                selectAllBtn.style.display = 'none';
                if (infoMessage) infoMessage.style.display = 'none';
                return;
            }

            // Show select all button and info message if there are enabled (unclaimed) items
            const hasUnclaimed = items.some(item => item.status !== 'claimed');
            selectAllBtn.style.display = hasUnclaimed ? 'inline-block' : 'none';
            if (infoMessage) infoMessage.style.display = 'block';

            let html = '';
            
            items.forEach((item, index) => {
                const isClaimed = (item.status === 'claimed');
                const statusBadge = isClaimed 
                    ? '<span class="status-badge status-claimed" style="font-size: 11px; padding: 2px 8px; border-radius: 4px; background-color: #c8e6c9; color: #2e7d32; font-weight: bold; margin-left: 8px;">CLAIMED</span>'
                    : '<span class="status-badge status-unclaimed" style="font-size: 11px; padding: 2px 8px; border-radius: 4px; background-color: #ffcdd2; color: #c62828; font-weight: bold; margin-left: 8px;">UNCLAIMED</span>';

                const disabledAttr = isClaimed ? 'disabled' : '';
                const cardBorder = isClaimed ? '#a5d6a7' : '#e0e0e0';
                const cardBg = isClaimed ? '#f4fbf4' : '#ffffff';
                const cursorStyle = isClaimed ? 'cursor: not-allowed;' : 'cursor: pointer;';

                let claimedDateHtml = '';
                if (isClaimed) {
                    const rawDate = item.claimed_at || item.created_at;
                    if (rawDate && rawDate !== '0000-00-00 00:00:00') {
                        const d = new Date(rawDate);
                        const month = String(d.getMonth() + 1).padStart(2, '0');
                        const day = String(d.getDate()).padStart(2, '0');
                        const year = d.getFullYear();
                        let hours = d.getHours();
                        const minutes = String(d.getMinutes()).padStart(2, '0');
                        const ampm = hours >= 12 ? 'PM' : 'AM';
                        hours = hours % 12;
                        hours = hours ? hours : 12;
                        const formattedDate = `${month}/${day}/${year} ${hours}:${minutes} ${ampm}`;
                        claimedDateHtml = `<div class="unclaimed-item-detail" style="color: #2e7d32; font-weight: 500;">Claimed Date: ${formattedDate}</div>`;
                    }
                }

                html += `
                    <div class="unclaimed-item-card" style="border-color: ${cardBorder}; background-color: ${cardBg};">
                        <input type="checkbox" 
                               class="unclaimed-item-checkbox" 
                               id="freebie-${item.id}" 
                               value="${item.id}"
                               data-item-code="${item.item_code}"
                               data-description="${item.item_description}"
                               data-quantity="${item.quantity}"
                               ${disabledAttr}
                               onchange="toggleItemSelection(this)">
                        <div class="unclaimed-item-content">
                            <label for="freebie-${item.id}" style="${cursorStyle} display: block;">
                                <div class="unclaimed-item-title">${item.item_description || 'N/A'} ${statusBadge}</div>
                                <div class="unclaimed-item-detail">Item Code: ${item.item_code || 'N/A'}</div>
                                <div class="unclaimed-item-detail">Quantity: ${item.quantity || 0}</div>
                                ${item.note ? '<div class="unclaimed-item-detail">Note: ' + item.note + '</div>' : ''}
                                ${claimedDateHtml}
                                <div class="unclaimed-item-detail" style="font-size: 12px; color: #888; margin-top: 5px;">
                                    Branch: ${item.branch || 'N/A'}
                                </div>
                            </label>
                        </div>
                    </div>
                `;
            });
            
            panel.innerHTML = html;
        }

        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.unclaimed-item-checkbox:not(:disabled)');
            const selectAllBtn = document.getElementById('selectAllBtn');
            if (checkboxes.length === 0) return;
            
            // Check if all enabled checkboxes are currently selected
            const allSelected = Array.from(checkboxes).every(cb => cb.checked);
            
            if (allSelected) {
                // Deselect all
                checkboxes.forEach(cb => {
                    cb.checked = false;
                    toggleItemSelection(cb);
                });
                selectAllBtn.textContent = 'Select All';
            } else {
                // Select all
                checkboxes.forEach(cb => {
                    cb.checked = true;
                    toggleItemSelection(cb);
                });
                selectAllBtn.textContent = 'Deselect All';
            }
        }

        function toggleItemSelection(checkbox) {
            // Visual feedback when checkbox is selected
            const card = checkbox.closest('.unclaimed-item-card');
            if (checkbox.checked) {
                card.style.borderColor = '#2e7d32';
                card.style.backgroundColor = '#f9fff3ff';
                
                // Add to selectedUnclaimedItems array
                const itemData = {
                    id: checkbox.value,
                    item_code: checkbox.getAttribute('data-item-code'),
                    description: checkbox.getAttribute('data-description'),
                    quantity: parseInt(checkbox.getAttribute('data-quantity')) || 0
                };
                
                // Check if not already in array
                const exists = selectedUnclaimedItems.find(item => item.id === itemData.id);
                if (!exists) {
                    selectedUnclaimedItems.push(itemData);
                }
            } else {
                card.style.borderColor = '#e0e0e0';
                card.style.backgroundColor = '#fafafa';
                
                // Remove from selectedUnclaimedItems array
                selectedUnclaimedItems = selectedUnclaimedItems.filter(item => item.id !== checkbox.value);
            }
            
            // Update Select All button text
            const checkboxes = document.querySelectorAll('.unclaimed-item-checkbox:not(:disabled)');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const allSelected = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
            
            if (selectAllBtn) {
                selectAllBtn.textContent = allSelected ? 'Deselect All' : 'Select All';
            }
            
            // Update remaining/added to claim quantities display
            updateRemainingQuantities();

            console.log('Selected unclaimed items:', selectedUnclaimedItems);
        }

        function getSelectedItems() {
            const checkboxes = document.querySelectorAll('.unclaimed-item-checkbox:checked');
            const selectedItems = [];
            
            checkboxes.forEach(checkbox => {
                selectedItems.push({
                    id: checkbox.value,
                    item_code: checkbox.getAttribute('data-item-code'),
                    description: checkbox.getAttribute('data-description'),
                    quantity: checkbox.getAttribute('data-quantity')
                });
            });
            
            return selectedItems;
        }

        function displayCustomerDetails(customer) {
            const panel = document.getElementById('customerDetailsPanel');
            
            if (!customer) {
                panel.innerHTML = '<p style="text-align: center; padding: 20px; color: #666;">No customer details available</p>';
                return;
            }

            const fullName = [customer.first_name, customer.last_name].filter(n => n).join(' ') || 'N/A';
            
            let html = '';
            html += `<div class="customer-detail-row">
                        <span class="customer-detail-label">Name:</span>
                        <span class="customer-detail-value">${fullName}</span>
                    </div>`;
            html += `<div class="customer-detail-row">
                        <span class="customer-detail-label">Address:</span>
                        <span class="customer-detail-value">${customer.address || 'N/A'}</span>
                    </div>`;
            html += `<div class="customer-detail-row">
                        <span class="customer-detail-label">Contact:</span>
                        <span class="customer-detail-value">${customer.contact_no || 'N/A'}</span>
                    </div>`;
            html += `<div class="customer-detail-row">
                        <span class="customer-detail-label">Email:</span>
                        <span class="customer-detail-value">${customer.email || 'N/A'}</span>
                    </div>`;
            html += `<div class="customer-detail-row">
                        <span class="customer-detail-label">Encoder:</span>
                        <span class="customer-detail-value">${customer.encoder || 'N/A'}</span>
                    </div>`;
            
            panel.innerHTML = html;
        }

        // Allow Enter key to trigger search
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('invoiceInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchUnclaimedFreebies();
                }
            });

            // Replace spaces with dashes in Item Code field
            if (itemCodeInput) {
                itemCodeInput.addEventListener('input', function(e) {
                    // Replace all spaces with dashes
                    this.value = this.value.replace(/\s/g, '-');
                });
            }

            // Add search button click handler for item search (the one in the Quantity row)
            const searchItemBtn = document.querySelector('.input-with-button .btn-search');
            if (searchItemBtn) {
                searchItemBtn.addEventListener('click', performItemSearch);
            }

            // Add button click handler
            const addBtn = document.querySelector('.btn-add');
            if (addBtn) {
                addBtn.addEventListener('click', addItemToTable);
            }

            // Add Enter key handler for item code search
            if (itemCodeInput) {
                itemCodeInput.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        performItemSearch();
                    }
                });
            }

            // Close modal when clicking outside
            window.onclick = function(event) {
                const searchModal = document.getElementById('searchItemModal');
                if (event.target == searchModal) {
                    closeSearchModal();
                }
            };
        });

        // Add item to table
        function addItemToTable() {
            const itemCode = itemCodeInput.value.trim().toUpperCase();
            const description = descriptionInput.value.trim();
            const imei = imeiInput.value.trim().toUpperCase();
            const quantity = parseInt(quantityInput.value) || 0;

            // Validation
            if (!itemCode) {
                alert('Please enter or search for an item code');
                itemCodeInput.focus();
                return;
            }

            if (!description) {
                alert('Please select a valid item');
                return;
            }

            if (quantity <= 0) {
                alert('Please enter a valid quantity');
                quantityInput.focus();
                return;
            }

            // Check if IMEI is required (for serialized items)
            const isImeiReadonly = imeiInput.hasAttribute('readonly');
            if (!isImeiReadonly && !imei) {
                alert('Please enter IMEI for this serialized item');
                imeiInput.focus();
                return;
            }

            // VALIDATION: Check if item is in selected unclaimed items
            const selectedItem = selectedUnclaimedItems.find(item => item.item_code === itemCode);
            if (!selectedItem) {
                alert(`Cannot add "${itemCode}". This item is not selected from the unclaimed items list. Please select it first from the unclaimed items panel.`);
                return;
            }

            // Calculate total quantity already in table for this item code
            let totalQtyInTable = 0;
            claimedItemsTable.forEach(item => {
                if (item.itemCode === itemCode) {
                    totalQtyInTable += item.quantity;
                }
            });

            const totalRequestedQty = totalQtyInTable + quantity;

            // Note: We're claiming from actual stock, not from unclaimed_freebies quantity
            // The stock availability check below will verify if sufficient stock exists

            // Check stock availability before adding
            const imeiParam = imei || '';
            const checkUrl = `check_stock_availability.php?item_code=${encodeURIComponent(itemCode)}&imei=${encodeURIComponent(imeiParam)}&qty=${totalRequestedQty}&force_branch=true`;
            
            console.log('Checking stock availability:', checkUrl);
            console.log('Quantity in table:', totalQtyInTable);
            console.log('Current quantity:', quantity);
            console.log('Total requested:', totalRequestedQty);
            console.log('Allowed unclaimed quantity:', selectedItem.quantity);
            
            fetch(checkUrl)
                .then(response => {
                    console.log('Stock check response status:', response.status);
                    return response.json();
                })
                .then(stockData => {
                    console.log('Stock check data:', stockData);
                    
                    // Log detailed stock information if available
                    if (stockData.stock_details) {
                        console.log('Stock details breakdown:', stockData.stock_details);
                        console.log('User branch:', stockData.user_branch);
                        console.log('Has full access:', stockData.has_full_access);
                    }
                    
                    if (stockData.status === 'error' || !stockData.available) {
                        alert(stockData.message || 'Insufficient stock no available');
                        return;
                    }

                    // Stock is available, add item to array
                    const newItem = {
                        itemCode: itemCode,
                        description: description,
                        imei: imei || '',
                        quantity: quantity
                    };

                    claimedItemsTable.push(newItem);

                    // Refresh table display
                    renderItemsTable();

                    // Clear input fields after adding
                    clearItemInputs();
                })
                .catch(error => {
                    console.error('Error checking stock availability:', error);
                    alert('Error checking stock availability: ' + error.message);
                });
        }

        // Render items table
        function renderItemsTable() {
            const tbody = document.querySelector('.items-table tbody');
            
            if (claimedItemsTable.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = '';
            claimedItemsTable.forEach((item, index) => {
                const row = `
                    <tr>
                        <td>${item.description}</td>
                        <td>${item.imei}</td>
                        <td style="text-align: center;">${item.quantity}</td>
                        <td style="text-align: center;">
                            <button type="button" class="btn-delete-item" onclick="deleteItem(${index})">X</button>
                        </td>
                    </tr>
                `;
                tbody.insertAdjacentHTML('beforeend', row);
            });
            
            // Update unclaimed items panel to show remaining quantities
            updateRemainingQuantities();
        }
        
        // Update remaining quantities in unclaimed items panel
        function updateRemainingQuantities() {
            // Remove all existing 'Added to claim' displays from all cards
            document.querySelectorAll('.remaining-qty-display').forEach(el => el.remove());

            // Check all currently checked unclaimed item checkboxes
            const checkedBoxes = document.querySelectorAll('.unclaimed-item-checkbox:checked');
            checkedBoxes.forEach(checkbox => {
                const itemCode = checkbox.getAttribute('data-item-code');
                const card = checkbox.closest('.unclaimed-item-card');
                if (!card) return;
                
                const content = card.querySelector('.unclaimed-item-content');
                if (!content) return;

                // Calculate total claimed quantity in table for this item code
                let claimedQty = 0;
                claimedItemsTable.forEach(claimedItem => {
                    if (claimedItem.itemCode === itemCode) {
                        claimedQty += claimedItem.quantity;
                    }
                });

                // Add new claimed quantity display if there's claimed quantity in table
                if (claimedQty > 0) {
                    const claimedDiv = document.createElement('div');
                    claimedDiv.className = 'unclaimed-item-detail remaining-qty-display';
                    claimedDiv.style.fontWeight = 'bold';
                    claimedDiv.style.color = '#2e7d32';
                    claimedDiv.innerHTML = `Added to claim: ${claimedQty}`;
                    content.appendChild(claimedDiv);
                }
            });
        }

        // Delete item from table
        function deleteItem(index) {
            if (confirm('Are you sure you want to remove this item?')) {
                claimedItemsTable.splice(index, 1);
                renderItemsTable();
            }
        }

        // Clear item input fields
        function clearItemInputs() {
            itemCodeInput.value = '';
            descriptionInput.value = '';
            imeiInput.value = '';
            quantityInput.value = '0';
            
            // Reset IMEI field state
            imeiInput.removeAttribute('readonly');
            imeiInput.style.backgroundColor = '#ffffff';
            imeiInput.style.cursor = 'text';
            
            // Focus back to item code
            itemCodeInput.focus();
        }

        // Perform item search
        function performItemSearch() {
            const searchTerm = itemCodeInput.value.trim();
            
            if (!searchTerm) {
                alert('Please enter an item code to search');
                return;
            }

            // Show loading state
            resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">Searching...</td></tr>';
            modal.style.display = 'flex';

            // Call the search API
            fetch(`search_claim_item.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    resultsBody.innerHTML = '';
                    if (data.status === 'success' && data.data.length > 0) {
                        currentSearchResults = data.data;
                        data.data.forEach((item, index) => {
                            const row = `
                                <tr>
                                    <td>${item.item_code}</td>
                                    <td>${item.description}</td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-select" onclick="selectSearchItem(${index})">Select</button>
                                    </td>
                                </tr>
                            `;
                            resultsBody.insertAdjacentHTML('beforeend', row);
                        });
                    } else {
                        resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">No items found</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px; color: #d32f2f;">An error occurred while searching</td></tr>';
                });
        }

        // Select item from search results
        function selectSearchItem(index) {
            const selectedItem = currentSearchResults[index];
            
            if (selectedItem) {
                const itemCode = selectedItem.item_code;
                const description = selectedItem.description;
                const imeiField = document.getElementById('imei');
                const selectedQty = Math.max(parseInt(quantityInput.value) || 0, 1);
                
                // VALIDATION: Check if this item is in the selected unclaimed items
                const unclaimedItem = selectedUnclaimedItems.find(item => item.item_code === itemCode);
                if (!unclaimedItem) {
                    alert(`Cannot select "${itemCode}".\n\nThis item is not in your selected unclaimed items list. Please select it from the unclaimed items panel first.`);
                    closeSearchModal();
                    return;
                }
                
                // Validate stock availability first
                fetch(`check_stock_availability.php?item_code=${encodeURIComponent(itemCode)}&imei=&qty=${selectedQty}&force_branch=true`)
                    .then(response => response.json())
                    .then(stockData => {
                        if (stockData.status === 'error' || !stockData.available) {
                            alert(stockData.message || 'Insufficient stock no available');
                            return;
                        }

                        // Stock is available, now check if item is serialized
                        if (itemCode) {
                            fetch(`check_serial_permission.php?item_code=${encodeURIComponent(itemCode)}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === 'success' && data.has_serial) {
                                        // Item is serialized - show alert and clear fields
                                        alert('This item is serialized');
                                        itemCodeInput.value = '';
                                        descriptionInput.value = '';
                                        imeiField.value = '';
                                        
                                        // Make IMEI field editable for serialized items
                                        imeiField.removeAttribute('readonly');
                                        imeiField.style.backgroundColor = '#ffffff';
                                        imeiField.style.cursor = 'text';
                                        
                                        closeSearchModal();
                                        return;
                                    }

                                    // Item is not serialized - proceed normally and disable IMEI field
                                    itemCodeInput.value = itemCode;
                                    descriptionInput.value = description;
                                    
                                    closeSearchModal();
                                    
                                    // Disable IMEI field for non-serialized items
                                    imeiField.setAttribute('readonly', 'readonly');
                                    imeiField.style.backgroundColor = '#f5f5f5';
                                    imeiField.style.cursor = 'not-allowed';
                                    imeiField.value = '';
                                    
                                    // Focus on quantity field instead
                                    document.getElementById('quantity').focus();
                                })
                                .catch(error => {
                                    console.error('Error checking serial permission:', error);
                                    alert('Error checking if item is serialized');
                                });
                        } else {
                            itemCodeInput.value = itemCode;
                            descriptionInput.value = description;
                            closeSearchModal();
                            imeiField.focus();
                        }
                    })
                    .catch(error => {
                        console.error('Error checking stock availability:', error);
                        alert('Error checking stock availability');
                    });
            }
        }

        // Close search modal
        function closeSearchModal() {
            modal.style.display = 'none';
        }
        
        // Save claimed items
        function saveClaimedItems() {
            // Validation: Check if invoice has been searched
            if (!currentCustomerDetails) {
                alert('Please search for an invoice first');
                return;
            }
            
            // Validation: Check if items are selected from unclaimed list
            if (selectedUnclaimedItems.length === 0) {
                alert('Please select at least one item from the unclaimed items list');
                return;
            }
            
            // Validation: Check if items have been added to the table
            if (claimedItemsTable.length === 0) {
                alert('Please add at least one item to the claim table');
                return;
            }
            
            // Note: We're claiming from actual stock, not validating against unclaimed_freebies quantity
            // The backend will handle stock validation
            
            // Prepare data for saving
            const remarksTextarea = document.getElementById('remarksTextarea');
            const remarks = remarksTextarea ? remarksTextarea.value.trim() : '';
            
            const invoiceNo = document.getElementById('invoiceInput').value.trim();
            
            const saveData = {
                invoice_no: invoiceNo,
                customer_name: [currentCustomerDetails.first_name, currentCustomerDetails.last_name]
                    .filter(n => n).join(' ') || 'N/A',
                customer_address: currentCustomerDetails.address || '',
                customer_contact: currentCustomerDetails.contact_no || '',
                customer_email: currentCustomerDetails.email || '',
                remarks: remarks,
                claimed_items: claimedItemsTable,
                unclaimed_item_ids: selectedUnclaimedItems.map(item => ({
                    id: item.id,
                    item_code: item.item_code
                }))
            };
            
            console.log('Saving claimed items:', saveData);
            
            // Show loading state
            const saveButton = event.target;
            const originalText = saveButton.textContent;
            saveButton.textContent = 'Saving...';
            saveButton.disabled = true;
            
            // Send to backend
            fetch('save_claimed_items.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(saveData)
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || 'Server error');
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Response from server:', data);
                if (data.success) {
                    let message = data.message;
                    
                    // Add debug info if available
                    if (data.stock_debug && data.stock_debug.length > 0) {
                        console.log('Stock Debug Info:', data.stock_debug);
                    }
                    
                    if (data.stock_errors && data.stock_errors.length > 0) {
                        console.warn('Stock Errors:', data.stock_errors);
                        message += '\n\n⚠️ Stock Warnings:\n' + data.stock_errors.join('\n');
                    }
                    
                    alert('✅ ' + message);
                    
                    // Reset form after successful save
                    resetClaimForm();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error saving claimed items:', error);
                alert('❌ Error saving claimed items: ' + error.message);
            })
            .finally(() => {
                // Restore button state
                saveButton.textContent = originalText;
                saveButton.disabled = false;
            });
        }
        
        // Reset claim form after successful save
        function resetClaimForm() {
            // Clear invoice input
            document.getElementById('invoiceInput').value = '';
            
            // Clear unclaimed items panel
            document.getElementById('unclaimedItemsPanel').innerHTML = '';
            
            // Clear customer details panel
            document.getElementById('customerDetailsPanel').innerHTML = '';
            
            // Hide select all button and info message
            document.getElementById('selectAllBtn').style.display = 'none';
            const infoMessage = document.getElementById('claimInfoMessage');
            if (infoMessage) infoMessage.style.display = 'none';
            
            // Clear item input fields
            clearItemInputs();
            
            // Clear claimed items table
            claimedItemsTable = [];
            renderItemsTable();
            
            // Clear remarks
            const remarksTextarea = document.getElementById('remarksTextarea');
            if (remarksTextarea) remarksTextarea.value = '';
            
            // Reset state variables
            currentUnclaimedItems = [];
            currentCustomerDetails = null;
            selectedUnclaimedItems = [];
            currentSearchResults = [];
            
            console.log('Form reset complete');
        }
    </script>
</body>

</html>