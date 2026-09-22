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

        .bottom-section {
            background: white;
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 8px;
        }
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

        .items-table th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            background: var(--color-gold-pale);
            border: 1px solid #ccc;
            white-space: nowrap;
        }

        .items-table td {
            padding: 12px;
            border: 1px solid #ccc;
            height: 35px;
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
            min-width: 300px;
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
            min-width: 80px;
            text-align: right;
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
            padding: 10px 50px;
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            align-self: flex-end;
            text-transform: uppercase;
        }

        .btn-save:hover {
            background-color: var(--color-gold-light);
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
                <div class="input-field">
                    <label>Item Model</label>
                    <input type="text" id="item_model_input" placeholder="">
                </div>
                <div class="input-field">
                    <label>IMEI</label>
                    <input type="text" id="imei_input" placeholder="" oninput="this.value = this.value.toUpperCase()">
                </div>
                <div style="flex: 1; display: flex; flex-direction: column; gap: 15px;">
                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <div class="input-field" style="flex: 1;">
                            <label>Quantity</label>
                            <input type="number" id="quantity_input" value="0" min="0" style="text-align: center;">
                        </div>
                        <div class="input-field" style="flex: 1;">
                            <label>Price</label>
                            <input type="text" id="price_input" placeholder="" readonly style="background-color: #ffffff; color: #333;">
                        </div>
                        <button type="button" class="btn-search-dark" id="search_item_btn" style="margin-bottom: 1px;">Search</button>
                        <button type="button" class="btn-add" id="add_item_btn" style="margin-bottom: 1px;">Add</button>
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
                            <th style="width: 15%;">Action</th>
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

            <!-- Bottom Row: Remarks and Totals -->
            <div class="bottom-row">
                <div class="remarks-section">
                    <label>Remarks:</label>
                    <textarea></textarea>
                </div>
                <div class="totals-section">
                    <div class="total-row">
                        <label>Less:</label>
                        <input type="text" readonly>
                    </div>
                    <div class="total-row">
                        <label>Total:</label>
                        <input type="text" readonly>
                    </div>
                    <button class="btn-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script>
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
                            <td><input type="checkbox" name="item_select" value="${index}" data-imei="${item.imei || ''}" data-description="${item.item_description || item.item_code || ''}" data-price="${item.price || 0}"></td>
                            <td>${item.item_description || item.item_code || 'N/A'}</td>
                            <td>${item.imei || 'N/A'}</td>
                        </tr>
                    `;
                });
                document.getElementById('invoiceItemsBody').innerHTML = itemsHTML;
            } else {
                document.getElementById('invoiceItemsBody').innerHTML = '<tr><td colspan="3" style="text-align: center; color: #666;">No items found</td></tr>';
            }
        }

        function resetDisplay() {
            const customerBox = document.getElementById('customerDetailsBox');
            customerBox.innerHTML = 'Enter an invoice number to view customer details';
            customerBox.classList.remove('has-data');
            
            document.getElementById('invoiceItemsBody').innerHTML = '<tr><td colspan="3" style="text-align: center; color: #666;">No items to display</td></tr>';
        }

        // Allow Enter key to trigger search
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('invoiceNoInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchInvoice();
                }
            });
        });
    </script>
</body>

</html>