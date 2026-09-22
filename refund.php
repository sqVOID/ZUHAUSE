<?php require_once 'session_check.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
   <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Refund</title>
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

        .main-content.expanded {
            margin-left: 0;
        }

        /* Refund Content Styles */
        .content-header {
            margin-bottom: 20px;
        }

        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .form-container {
            background: white;
            border: 1px solid #ccc;
            padding: 30px;
            border-radius: 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 40px;
            margin-bottom: 25px;
        }

        .form-row {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .form-row label {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            min-width: 120px;
        }

        .form-row input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
        }

        .form-row input:focus,
        .form-row select:focus {
            outline: none;
            border-color: #2196F3;
        }

        .form-row select {
            background-color: white;
            cursor: pointer;
        }

        .btn-search {
            padding: 10px 30px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-search:hover {
            background-color: var(--color-navy-dark);
        }

        .remark-section {
            margin-bottom: 25px;
        }

        .remark-section label {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            display: block;
            margin-bottom: 8px;
        }

        .remark-section textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 100px;
            resize: vertical;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .remark-section textarea:focus {
            outline: none;
            border-color: #2196F3;
        }

        .items-table-container {
            overflow-x: auto;
            margin-bottom: 20px;
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

        .summary-section {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-row {
            display: flex;
            align-items: center;
            gap: 15px;
            width: 400px;
        }

        .summary-row label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            min-width: 120px;
            text-align: right;
        }

        .summary-row input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
            background-color: #f5f5f5;
        }

        .action-section {
            display: flex;
            justify-content: flex-end;
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
            text-transform: uppercase;
        }

        .btn-save:hover {
            background-color: var(--color-gold-light);
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
        }

        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .summary-row {
                width: 100%;
            }
        }

        /* Mobile Phones (max-width: 600px) */
        @media (max-width: 600px) {
            /* Form container padding */
            .form-container {
                padding: 15px !important;
            }

            /* Form grid - already single column from 1024px */
            .form-grid {
                gap: 15px !important;
            }

            /* Form rows - stack label and input vertically */
            .form-row {
                flex-direction: column !important;
                gap: 8px !important;
                align-items: flex-start !important;
            }

            .form-row label {
                font-size: 13px !important;
                font-weight: 600 !important;
                width: 100% !important;
            }

            .form-row input,
            .form-row select {
                width: 100% !important;
                font-size: 14px !important;
                padding: 10px !important;
            }

            /* Search button in invoice row */
            .form-row .btn-search {
                width: 100% !important;
                padding: 10px 20px !important;
                font-size: 14px !important;
                margin-top: 5px !important;
            }

            /* Date field */
            #date {
                font-size: 14px !important;
            }

            /* Remark section */
            .remark-section {
                margin-bottom: 20px !important;
            }

            .remark-section label {
                font-size: 13px !important;
                font-weight: 600 !important;
            }

            .remark-section textarea {
                font-size: 14px !important;
                padding: 10px !important;
                min-height: 100px !important;
            }

            /* Items table */
            .items-table {
                font-size: 12px !important;
            }

            .items-table th,
            .items-table td {
                padding: 8px 5px !important;
                font-size: 12px !important;
            }

            /* Summary section */
            .summary-section {
                padding: 15px !important;
            }

            .summary-row {
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 10px !important;
                padding: 10px 0 !important;
            }

            .summary-row label {
                font-size: 14px !important;
                flex-shrink: 0 !important;
            }

            .summary-row input {
                font-size: 14px !important;
                padding: 10px !important;
                flex: 1 !important;
                min-width: 100px !important;
            }

            /* Save button */
            .btn-save {
                width: 100% !important;
                padding: 12px 20px !important;
                font-size: 15px !important;
            }

            /* Content header */
            .content-header h2 {
                font-size: 20px !important;
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
        <!-- <img src="Icon/motogam_logo.jpg" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Refund</h2>
        </div>

        <div class="form-container">
            <div class="form-grid">
                <!-- Invoice No with Search -->
                <div class="form-row">
                    <label>Invoice No:<span style="color: red;">*</span></label>
                    <input type="text" id="invoice_no" placeholder="Enter Invoice" required>
                    <button class="btn-search" id="btnSearch" onclick="searchInvoice()">Search</button>
                </div>

                <!-- Date -->
                <div class="form-row">
                    <label>Date:</label>
                    <input type="date" id="refund_date" readonly style="background-color: #f5f5f5;">
                </div>

                <!-- Customer's Name -->
                <div class="form-row">
                    <label>Customer's Name:</label>
                    <input type="text" id="customer_name" placeholder="Auto-filled from invoice" readonly
                        style="background-color: #f5f5f5;">
                </div>

                <!-- Approved By -->
                <div class="form-row">
                    <label>Approved By:<span style="color: red;">*</span></label>
                    <input type="text" id="approved_by" placeholder="Enter name" required>
                </div>

                <!-- Reason -->
                <div class="form-row">
                    <label>Reason:<span style="color: red;">*</span></label>
                    <select id="reason" required style="padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; flex: 1;">
                        <option value="">Select Reason</option>
                        <option value="Defective">Defective</option>
                        <option value="Customer Request">Customer Request</option>
                        <option value="Wrong Item">Wrong Item</option>
                        <option value="Change of Mind">Change of Mind</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <!-- Remark Section -->
            <div class="remark-section">
                <label>Remark:<span style="color: red;">*</span></label>
                <textarea id="remarks" placeholder="Enter remarks..." required></textarea>
            </div>

            <!-- Items Table -->
            <div class="items-table-container">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Item Description</th>
                            <th style="width: 30%;">IMEI</th>
                            <th style="width: 15%;">Quantity</th>
                            <th style="width: 15%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="items_tbody">
                        <tr>
                            <td colspan="4" style="text-align: center;">Search an invoice to load items</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Summary Section -->
            <div class="summary-section">
                <div class="summary-row">
                    <label>Total Quantity:</label>
                    <input type="text" id="total_qty" readonly value="0">
                </div>
                <div class="summary-row">
                    <label>Balance:</label>
                    <input type="text" id="balance" readonly value="0.00">
                </div>
            </div>

            <!-- Save Button -->
            <div class="action-section">
                <button class="btn-save" onclick="saveRefund()">Save</button>
            </div>
        </div>
    </div>

    <!-- Data Storage -->
    <script>
        let currentSalesId = null;
        let invoiceData = [];
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

        document.getElementById('refund_date').valueAsDate = new Date();

        function searchInvoice() {
            const invoiceNo = document.getElementById('invoice_no').value.trim();
            if (!invoiceNo) {
                alert('Please enter an Invoice No.');
                return;
            }

            const tbody = document.getElementById('items_tbody');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Searching...</td></tr>';

            fetch('get_sales_by_invoice.php?invoice_no=' + encodeURIComponent(invoiceNo))
                .then(res => res.json())
                .then(data => {
                    if (data.status !== 'success') {
                        alert(data.message || 'Invoice not found.');
                        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: red;">' + (data.message || 'Invoice not found') + '</td></tr>';
                        currentSalesId = null;
                        invoiceData = [];
                        calculateTotal();
                        return;
                    }

                    const d = data.data;
                    currentSalesId = d.id || null; // Will need to ensure ID is passed

                    // If the backend doesn't pass ID but passes items, it's fine. We use Invoice No.

                    // Set Customer Name
                    const first = d.customer.first_name || '';
                    const last = d.customer.last_name || '';
                    document.getElementById('customer_name').value = (first + ' ' + last).trim();

                    invoiceData = d.items || [];

                    if (invoiceData.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">No items found in this invoice.</td></tr>';
                        calculateTotal();
                        return;
                    }

                    let html = '';
                    invoiceData.forEach((item, index) => {
                        const maxQty = item.quantity;
                        html += `
                        <tr data-index="${index}">
                            <td>${item.item_description}</td>
                            <td>${item.imei || ''}</td>
                            <td>${maxQty}</td>
                            <td>
                                <button type="button" class="btn-delete-item" title="Remove Item" onclick="removeItemByAction(this)">&times;</button>
                            </td>
                        </tr>
                    `;
                    });
                    tbody.innerHTML = html;
                    calculateTotal();
                })
                .catch(err => {
                    alert('Error searching: ' + err.message);
                });
        }

        function removeItemByAction(btn) {
            const tr = btn.closest('tr');
            tr.remove();
            calculateTotal();
        }

        function calculateTotal() {
            let totalQty = 0;
            let totalBalance = 0;

            const rows = document.querySelectorAll('#items_tbody tr[data-index]');
            rows.forEach(tr => {
                const idx = tr.getAttribute('data-index');
                const originalItem = invoiceData[idx];
                const q = parseInt(originalItem.quantity) || 0;
                // Use discounted_price if available, otherwise fall back to price
                const price = parseFloat(originalItem.discounted_price || originalItem.price) || 0;
                totalQty += q;
                totalBalance += (q * price);
            });

            document.getElementById('total_qty').value = totalQty;
            document.getElementById('balance').value = totalBalance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function saveRefund() {
            const invoiceNo = document.getElementById('invoice_no').value.trim();
            if (!invoiceNo) {
                alert('Please enter an Invoice Number.');
                document.getElementById('invoice_no').focus();
                return;
            }

            const approvedBy = document.getElementById('approved_by').value.trim();
            if (!approvedBy) {
                alert('Please enter who approved this refund.');
                document.getElementById('approved_by').focus();
                return;
            }

            const reason = document.getElementById('reason').value.trim();
            if (!reason) {
                alert('Please select a reason for this refund.');
                document.getElementById('reason').focus();
                return;
            }

            const remarks = document.getElementById('remarks').value.trim();
            if (!remarks) {
                alert('Please enter remarks for this refund.');
                document.getElementById('remarks').focus();
                return;
            }

            const rows = document.querySelectorAll('#items_tbody tr[data-index]');
            let itemsToRefund = [];

            rows.forEach(tr => {
                const idx = tr.getAttribute('data-index');
                const originalItem = invoiceData[idx];
                const qty = parseInt(originalItem.quantity) || 0;

                if (qty > 0) {
                    itemsToRefund.push({
                        item_code: originalItem.item_code,
                        item_description: originalItem.item_description,
                        imei: originalItem.imei,
                        price: originalItem.discounted_price || originalItem.price, // Use discounted price
                        quantity: qty
                    });
                }
            });

            if (itemsToRefund.length === 0) {
                alert('No items found to refund. Please search for an invoice first.');
                return;
            }

            const data = {
                invoice_no: invoiceNo,
                refund_date: document.getElementById('refund_date').value,
                customer_name: document.getElementById('customer_name').value,
                approved_by: approvedBy,
                reason: reason,
                remarks: remarks,
                total_qty: parseInt(document.getElementById('total_qty').value) || 0,
                total_amount: parseFloat(document.getElementById('balance').value.replace(/,/g, '')) || 0,
                items: itemsToRefund
            };

            fetch('save_refund.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        alert('Refund processed successfully!');
                        window.location.href = 'refundreport.php';
                    } else {
                        alert('Error: ' + res.message);
                    }
                })
                .catch(err => alert('Fatal Error: ' + err.message));
        }
    </script>
</body>

</html>