<?php
require_once 'session_check.php';
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre Order Report</title>
    <style>
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

        /* -- HEADER -- */
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

        /* -- MENU BUTTON -- */
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

        /* -- SIDEBAR -- */
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
            background-color: #f5ede0;
            color: #0d3347;
            font-weight: bold;
        }

        .menu-item svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
            flex-shrink: 0;
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

        /* -- MAIN CONTENT -- */
        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 24px 28px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* -- PAGE TITLE -- */
        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 20px;
        }

        /* -- FILTER BAR -- */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .filter-label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .filter-input,
        .filter-select {
            height: 38px;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 0 12px;
            font-size: 14px;
            color: #333;
            background: #fff;
            outline: none;
            transition: border-color 0.2s;
        }

        .filter-input:focus,
        .filter-select:focus {
            border-color: #0e7725;
        }

        .filter-input {
            min-width: 170px;
        }

        .filter-select {
            min-width: 180px;
            appearance: none;
            cursor: pointer;
            padding-right: 32px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%23666' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }

        .filter-select:disabled {
            background-color: #f5f5f5;
            color: #666;
            cursor: not-allowed;
            border-color: #ddd;
        }

        .btn-search {
            height: 38px;
            padding: 0 22px;
            background: #000000ff;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-search:hover {
            background: #616161ff;
        }

        .btn-print {
            height: 38px;
            padding: 0 22px;
            background: #1a1a1a;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-left: auto;
        }

        .btn-print:hover {
            background: #333;
        }

        .btn-export {
            height: 38px;
            padding: 0 22px;
            background: #1a7a2e;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-export:hover {
            background: #145c22;
        }

        /* -- PREORDER HISTORY WRAPPER -- */
        .preorder-history-wrapper {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }

        .preorder-history-header {
            background: #f8f8f8;
            border-bottom: 1px solid #ddd;
            padding: 14px 20px;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 2px;
            color: #1a1a1a;
            text-transform: uppercase;
        }

        /* -- RECEIPT / DOCUMENT AREA -- */
        .receipt-area {
            padding: 24px 28px;
        }

        /* Printable document card */
        .doc-card {
            border: 1px solid #ccc;
            border-radius: 4px;
            background: white;
            padding: 20px 24px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            color: #111;
        }

        /* Header row: logo left, title absolutely centred */
        .doc-head {
            position: relative;
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .doc-logo {
            margin-left: -33px;
            height: 130px;
            border-radius: 5px;
            position: relative;
            z-index: 10;
            background: transparent;
        }

        .doc-preorder-title {
            position: absolute;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 20px;
            font-weight: 600;
            letter-spacing: 2px;
            pointer-events: none;
            font-family: 'Courier New', Courier, monospace;
            z-index: 1;
            background: transparent;
        }

        .doc-meta {
            margin-bottom: 6px;
        }

        .doc-meta-row {
            display: flex;
            justify-content: space-between;
            font-size: 13.5px;
            margin-bottom: 3px;
            color: #000000ff;
            letter-spacing: 1px;
            font-weight: 600;
            font-family: 'Courier New', Courier, monospace;
        }

        .doc-meta-row .left {
            text-align: left;
            color: #000000ff;
        }

        .doc-meta-row .right {
            text-align: right;
        }

        .meta-label {
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Table */
        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .doc-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 16px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
        }

        .doc-table th {
            border: 1px solid #000000;
            letter-spacing: 1.5px;
            padding: 5px 6px;
            text-align: center;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            background: #f4f4f4;
            white-space: nowrap;
            color: #000000;
        }

        .doc-table td {
            border: 1px solid #000000;
            letter-spacing: 1.5px;
            padding: 5px 6px;
            text-align: center;
            font-size: 13px;
            white-space: nowrap;
            vertical-align: top;
            color: #000000;
        }

        .doc-table td.td-no-data {
            text-align: center;
            color: #000000ff;
            font-size: 14px;
            padding: 12px;
            font-style: italic;
        }

        .doc-table td.td-text-left {
            text-align: left;
        }

        .doc-table td.td-number {
            text-align: right;
        }

        /* Status indicators */
        .status-pending {
            color: #ff9800;
            font-weight: bold;
        }

        .status-partial {
            color: #ff6b00;
            font-weight: bold;
        }

        .status-fully-paid {
            color: #4caf50;
            font-weight: bold;
        }

        .status-claimed {
            color: #2196f3;
            font-weight: bold;
        }

        .status-cancelled {
            color: #f44336;
            font-weight: bold;
        }

        /* Signature + page */
        .doc-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 34px;
        }

        .doc-signature {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            font-weight: 600;
            color: #000000ff;
            letter-spacing: 1px;
        }

        .doc-signature-line {
            border-top: 1px solid #555;
            width: 180px;
            margin-bottom: 4px;
        }

        .doc-page {
            font-weight: bold;
            font-family: 'Courier New', Courier, monospace;
            font-size: 16px;
            color: #444;
            text-align: right;
        }

        /* Unclaimed Freebies print styles */
        #unclaimedFreebiesSection {
            display: block !important;
        }


        /* -- RESPONSIVE MEDIA QUERIES -- */

        /* Stack filter controls vertically below 1169px */
        @media (max-width: 1169px) {
            .filter-bar {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 10px;
            }
        }

        /* Responsive: Compact layout below 930px */
        @media (max-width: 930px) {
            .filter-bar {
                flex-wrap: wrap;
                gap: 10px;
            }

            .filter-input,
            select {
                width: 140px;
            }
        }

        /* Stack all controls vertically below 510px */
        @media (max-width: 510px) {
            .filter-bar {
                flex-direction: column;
                align-items: stretch !important;
                gap: 10px;
            }

            .filter-label {
                display: none;
            }

            .filter-input,
            select,
            button {
                width: 100% !important;
                max-width: 100%;
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

            .content-header h2 {
                font-size: 18px;
            }

            /* Enable horizontal scrolling for tables on mobile */
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                min-width: 800px;
                display: table;
            }

            /* Add scrollbar styling for better visibility */
            .table-container::-webkit-scrollbar {
                height: 8px;
            }

            .table-container::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }

            .table-container::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 4px;
            }

            .table-container::-webkit-scrollbar-thumb:hover {
                background: #555;
            }
        }

        /* -- PRINT STYLES -- */
        @media print {
            @page {
                margin: 0.5in;
                size: A4;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body {
                background: white !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
                font-size: 12pt !important;
            }

            body * {
                visibility: hidden !important;
            }

            .doc-print-zone,
            .doc-print-zone * {
                visibility: visible !important;
            }

            .doc-print-zone {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
    <!-- xlsx-js-style: SheetJS with cell styling support -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
</head>

<body>
    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()"><span></span><span></span><span></span></div>
        <?php include '_header_user.php'; ?>
    </div>

    <?php include '_sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="page-title">Pre Order Report</div>

        <div class="filter-bar">
            <span class="filter-label">From:</span>
            <input type="date" id="filterDateFrom" class="filter-input" value="<?php echo date('Y-m-d'); ?>">

            <span class="filter-label">To:</span>
            <input type="date" id="filterDateTo" class="filter-input" value="<?php echo date('Y-m-d'); ?>">

            <span class="filter-label">Branch:</span>
            <select id="filterBranch" class="filter-select">
                <?php
                $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
                $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

                if ($system_level === 'Super-Admin' || $system_level === 'Sub-admin' || strtoupper($user_branch) === 'SUPERADMIN') {
                    echo '<option value="">Select Branch</option>';
                    $branches_result = $conn->query("SELECT branch_name FROM branches WHERE status = 'Active' ORDER BY branch_name");
                    if ($branches_result && $branches_result->num_rows > 0) {
                        while ($branch = $branches_result->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($branch['branch_name']) . '">' . htmlspecialchars($branch['branch_name']) . '</option>';
                        }
                    }
                } elseif (!empty($user_branch)) {
                    $user_branches = array_map('trim', explode(',', $user_branch));
                    $user_branches = array_filter($user_branches, function ($branch) {
                        return !empty($branch);
                    });

                    if (count($user_branches) == 1) {
                        echo '<option value="' . htmlspecialchars($user_branches[0]) . '" selected>' . htmlspecialchars($user_branches[0]) . '</option>';
                        echo '<script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    const branchEl = document.getElementById("filterBranch");
                                    branchEl.disabled = true;
                                    document.getElementById("displayBranch").textContent = "' . htmlspecialchars($user_branches[0]) . '";
                                });
                              </script>';
                    } else {
                        echo '<option value="">Select Branch</option>';
                        foreach ($user_branches as $branch) {
                            echo '<option value="' . htmlspecialchars($branch) . '">' . htmlspecialchars($branch) . '</option>';
                        }
                    }
                } else {
                    echo '<option value="">No Branch Access</option>';
                }
                ?>
            </select>

            <span class="filter-label">Status:</span>
            <select id="filterStatus" class="filter-select">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="fully paid">Fully Paid</option>
                <option value="claimed">Claimed</option>
                <option value="cancelled">Cancelled</option>
            </select>

            <button class="btn-search" onclick="searchPreOrderReport()">Search</button>
            <button class="btn-print" onclick="printPreOrderReport()">Print</button>
            <button class="btn-export" onclick="exportToExcel()">&#128196; Export</button>
        </div>

        <!-- Status Legend
        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 15px; padding: 10px; background: #fff; border-radius: 6px; border: 1px solid #ddd;">
            <span style="font-size: 13px; font-weight: 600; color: #333;">Status Legend:</span>

            
            <div style="display: flex; align-items: center; gap: 5px;">
                <span style="width: 8px; height: 8px; background: #ff9800; border-radius: 50%; display: inline-block;"></span>
                <span style="font-size: 12px; color: #666;">Pending</span>
            </div>
        
            <div style="display: flex; align-items: center; gap: 5px;">
                <span style="width: 8px; height: 8px; background: #ff6b00; border-radius: 50%; display: inline-block;"></span>
                <span style="font-size: 12px; color: #666;">Partial</span>
            </div>
            <div style="display: flex; align-items: center; gap: 5px;">
                <span style="width: 8px; height: 8px; background: #4caf50; border-radius: 50%; display: inline-block;"></span>
                <span style="font-size: 12px; color: #666;">Completed</span>
            </div>
            <div style="display: flex; align-items: center; gap: 5px;">
                <span style="width: 8px; height: 8px; background: #2196f3; border-radius: 50%; display: inline-block;"></span>
                <span style="font-size: 12px; color: #666;">Claimed</span>
            </div>
        
            <div style="display: flex; align-items: center; gap: 5px;">
                <span style="width: 8px; height: 8px; background: #f44336; border-radius: 50%; display: inline-block;"></span>
                <span style="font-size: 12px; color: #666;">Cancelled</span>
            </div>
           
        </div>
         -->

        <div class="preorder-history-wrapper">
            <div class="preorder-history-header">PRE ORDER REPORT</div>
            <div class="receipt-area">
                <div class="doc-card doc-print-zone" id="docCard">

                    <!-- Doc head: logo + title -->
                    <div class="doc-head">
                        <img src="Icon/ZUHAUSE-LOGO.png" alt="ZUHAUSE LOGO" class="doc-logo">
                        <div class="doc-preorder-title">PRE ORDER REPORT</div>
                    </div>

                    <!-- Meta rows -->
                    <div class="doc-meta">
                        <div class="doc-meta-row">
                            <span class="left"><span class="meta-label">BRANCH:</span> <span
                                    id="displayBranch">&nbsp;</span></span>
                        </div>
                        <div class="doc-meta-row">
                            <span class="left"><span class="meta-label">DATE RANGE:</span> <span
                                    id="displayDateRange">&nbsp;</span></span>
                        </div>
                        <div class="doc-meta-row">
                            <span class="left"><span class="meta-label">STATUS:</span> <span
                                    id="displayStatus">&nbsp;</span></span>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="doc-table">
                            <thead>
                                <tr>
                                    <th>Pre Order No</th>
                                    <th>Customer Name</th>
                                    <th>Item Description</th>
                                    <th>IMEI</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total Amount</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Branch</th>
                                    <th>Date Sold</th>
                                    <th>Date Claimed</th>
                                </tr>
                            </thead>
                            <tbody id="preorderTableBody">
                                <tr>
                                    <td class="td-no-data" colspan="12">SELECT A FILTER TO DISPLAY THE DATA</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Unclaimed Freebies Breakdown Box -->
                    <div
                        style="display: flex; justify-content: flex-end; align-items: flex-start; width: 100%; margin-top: 20px;">
                        <div id="unclaimedFreebiesBreakdownBox" style="display: none; flex: 0 0 auto;">
                            <!-- Unclaimed freebies box will be inserted here -->
                        </div>
                    </div>

                </div><!-- /doc-card -->
            </div><!-- /receipt-area -->
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

        function formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        }

        function formatDateTime(dateTimeStr) {
            if (!dateTimeStr) return '';
            const d = new Date(dateTimeStr);
            return d.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function formatDateOnly(dateTimeStr) {
            if (!dateTimeStr) return '';
            const d = new Date(dateTimeStr);
            return d.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: '2-digit'
            });
        }

        function searchPreOrderReport() {
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            const branch = document.getElementById('filterBranch').value;
            const status = document.getElementById('filterStatus').value;
            const tbody = document.getElementById('preorderTableBody');

            if (!dateFrom || !dateTo) {
                alert('Please select from and to dates.');
                return;
            }
            if (dateFrom > dateTo) {
                alert('From date cannot be later than To date.');
                return;
            }
            if (!branch) {
                alert('Please select a branch.');
                return;
            }

            document.getElementById('displayBranch').textContent = branch;
            document.getElementById('displayDateRange').textContent = formatDate(dateFrom) + ' to ' + formatDate(dateTo);
            document.getElementById('displayStatus').textContent = status || 'All Status';
            tbody.innerHTML = '<tr><td class="td-no-data" colspan="11">Loading...</td></tr>';

            // Fetch both preorder data and unclaimed freebies in parallel
            const preorderPromise = fetch('fetch_preorder_report.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    date_from: dateFrom,
                    date_to: dateTo,
                    branch: branch,
                    status: status
                })
            }).then(res => res.json());

            const freebiesPromise = fetchUnclaimedFreebies(dateFrom, dateTo, branch);

            Promise.all([preorderPromise, freebiesPromise])
                .then(([data, freebiesData]) => {
                    if (data.status !== 'success') {
                        tbody.innerHTML = '<tr><td class="td-no-data" colspan="12">' + (data.message || 'Error loading report') + '</td></tr>';
                        return;
                    }

                    if (!data.rows || data.rows.length === 0) {
                        tbody.innerHTML = '<tr><td class="td-no-data" colspan="12">NO DATA</td></tr>';
                        return;
                    }

                    let html = '';
                    data.rows.forEach(row => {
                        const statusClass = getStatusClass(row.status);
                        const claimedDate = row.claimed_at ? formatDateOnly(row.claimed_at) : '-';

                        html += `
                            <tr>
                                <td>${row.preorder_no || ''}</td>
                                <td class="td-text-left">${row.customer_name || ''}</td>
                                <td class="td-text-left">${row.item_description || ''}</td>
                                <td>${row.imei || ''}</td>
                                <td>${row.quantity || 0}</td>
                                <td class="td-number">${Number(row.unit_price || 0).toFixed(2)}</td>
                                <td class="td-number">${Number(row.total_amount || 0).toFixed(2)}</td>
                                <td class="td-number">${Number(row.payment_amount || 0).toFixed(2)}</td>
                                <td><span class="${statusClass}">${(row.status || '').toUpperCase()}</span></td>
                                <td>${row.branch_name || ''}</td>
                                <td>${formatDateOnly(row.date_created)}</td>
                                <td>${claimedDate}</td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;

                    // Handle unclaimed freebies data
                    if (freebiesData && freebiesData.success) {
                        displayUnclaimedFreebies(freebiesData);
                    }
                })
                .catch(err => {
                    tbody.innerHTML = '<tr><td class="td-no-data" colspan="12">Error loading report: ' + err.message + '</td></tr>';
                });
        }

        function getStatusClass(status) {
            switch (status?.toLowerCase()) {
                case 'pending': return 'status-pending';
                case 'partial': return 'status-partial';
                case 'fully paid': return 'status-fully-paid';
                case 'claimed': return 'status-claimed';
                case 'cancelled': return 'status-cancelled';
                default: return '';
            }
        }

        /* --- Fetch Unclaimed Freebies --- */
        let currentUnclaimedFreebiesData = null;

        function fetchUnclaimedFreebies(dateFrom, dateTo, branch) {
            const url = `get_unclaimed_freebies_report.php?date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}&branch=${encodeURIComponent(branch)}`;

            console.log('Fetching unclaimed freebies:', url);

            return fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentUnclaimedFreebiesData = data;
                        return data;
                    } else {
                        console.error('Error fetching unclaimed freebies:', data.message);
                        currentUnclaimedFreebiesData = null;
                        const container = document.getElementById('unclaimedFreebiesBreakdownBox');
                        if (container) container.style.display = 'none';
                        return null;
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    currentUnclaimedFreebiesData = null;
                    const container = document.getElementById('unclaimedFreebiesBreakdownBox');
                    if (container) container.style.display = 'none';
                    return null;
                });
        }

        /* --- Display Unclaimed Freebies Breakdown --- */
        function displayUnclaimedFreebies(data) {
            if (!data) data = currentUnclaimedFreebiesData;
            if (!data) return;

            const container = document.getElementById('unclaimedFreebiesBreakdownBox');

            if (!container) {
                console.warn('Unclaimed freebies container not found');
                return;
            }

            if (!data.data || data.data.length === 0) {
                container.style.display = 'none';
                return;
            }

            const records = data.data;
            let html = '';

            // Create bordered box similar to commission breakdown
            html += `<div style="border: 2px solid #000; font-family: 'Courier New', Courier, monospace; font-size: 13px; font-weight: bold; width: 400px; max-height: 600px; overflow-y: auto; -webkit-print-color-adjust: exact; print-color-adjust: exact;">`;
            html += `<div style="border-bottom: 2px solid #000; padding: 6px; text-align: center; color: #000; font-size: 13px;">UNCLAIMED FREEBIES BREAKDOWNS</div>`;
            html += `<div style="padding: 6px; color: #000;">`;

            // Show simple list of records
            for (const record of records) {
                const statusColor = record.status === 'unclaimed' ? '#d32f2f' : '#2e7d32';
                const statusText = record.status === 'unclaimed' ? 'UNCLAIMED' : 'CLAIMED';

                html += `<div style="border-bottom: 1px solid #ccc; padding: 4px 0; margin-bottom: 4px;">`;
                html += `<div style="font-size: 12px; margin-bottom: 2px;"><strong>INV:</strong> ${record.invoice_number}</div>`;
                html += `<div style="font-size: 12px; margin-bottom: 2px;"><strong>ITEM:</strong> ${record.item_code}</div>`;
                html += `<div style="font-size: 12px; margin-bottom: 2px;"><strong>QTY:</strong> ${record.quantity}</div>`;
                html += `<div style="font-size: 12px; margin-bottom: 2px;"><strong>BRANCH:</strong> ${record.branch}</div>`;
                html += `<div style="font-size: 12px; color: ${statusColor}; margin-bottom: 2px;"><strong>STATUS:</strong> ${statusText}</div>`;

                if (record.status === 'unclaimed') {
                    const unclaimedDate = record.created_at ? formatDateTime(record.created_at) : 'N/A';
                    html += `<div style="font-size: 12px; margin-bottom: 2px;"><strong>UNCLAIMED DATE:</strong> ${unclaimedDate}</div>`;
                } else {
                    const claimedDate = record.claimed_at ? formatDateTime(record.claimed_at) : (record.created_at ? formatDateTime(record.created_at) : 'N/A');
                    html += `<div style="font-size: 12px; margin-bottom: 2px;"><strong>CLAIMED DATE:</strong> ${claimedDate}</div>`;
                }

                html += `</div>`;
            }

            html += `</div></div>`; // Close padding div and main container

            container.innerHTML = html;
            container.style.display = 'block';
        }

        function exportToExcel() {
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            const branch = document.getElementById('filterBranch').value;
            const status = document.getElementById('filterStatus').value;

            if (!dateFrom || !dateTo) { alert('Please select from and to dates before exporting.'); return; }
            if (dateFrom > dateTo) { alert('From date cannot be later than To date.'); return; }
            if (!branch) { alert('Please select a branch before exporting.'); return; }

            const tbody = document.getElementById('preorderTableBody');
            const rows = tbody.querySelectorAll('tr');

            if (rows.length === 0 || (rows.length === 1 && rows[0].querySelector('.td-no-data'))) {
                alert('No data to export. Please search first.');
                return;
            }

            const branchLabel = document.getElementById('displayBranch').textContent.trim() || branch;
            const dateLabel = document.getElementById('displayDateRange').textContent.trim()
                || (formatDate(dateFrom) + ' to ' + formatDate(dateTo));
            const statusLabel = document.getElementById('displayStatus').textContent.trim() || 'All Status';

            // ── Colour palette ───────────────────────────────────────────────
            const C = {
                titleBg: '000000',      // title background (black)
                titleFg: 'FFFFFF',      // title text (white)
                headerBg: '0E4C2F',     // header row background (dark green)
                headerFg: 'FFFFFF',     // header text (white)
                metaBg: 'EAF0FB',       // branch/date rows background
                metaKey: '000000',      // metadata label colour
                metaVal: '333333',      // metadata value colour
                oddRow: 'FFFFFF',       // data odd rows
                evenRow: 'F0F5FF',      // data even rows (light blue tint)
                border: 'B0BEC5',       // data cell borders
                hdrBorder: '000000',    // header borders
                currency: '1A5C38',     // currency font colour
                accentLine: '000000'    // thick accent border colour
            };

            // ── Column definitions ──────────────────────────────────────────
            const headers = [
                'Pre Order No', 'Customer Name', 'Item Description', 'IMEI', 'Quantity',
                'Unit Price', 'Total Amount', 'Payment', 'Status', 'Branch', 'Date Created', 'Date Claimed'
            ];
            const COL = headers.length;

            // ── Collect data rows ───────────────────────────────────────────
            const dataRows = [];
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length === 0) return;
                const r = Array.from(cells).map(cell => cell.textContent.trim());
                r[4] = isNaN(r[4]) || r[4] === '' ? r[4] : Number(r[4]);   // Quantity
                r[5] = isNaN(r[5].replace(/,/g, '')) || r[5] === '' ? r[5] : Number(r[5].replace(/,/g, ''));   // Unit Price
                r[6] = isNaN(r[6].replace(/,/g, '')) || r[6] === '' ? r[6] : Number(r[6].replace(/,/g, ''));   // Total Amount
                r[7] = isNaN(r[7].replace(/,/g, '')) || r[7] === '' ? r[7] : Number(r[7].replace(/,/g, ''));   // Payment
                dataRows.push(r);
            });

            // ── aoa_to_sheet: rows 0-5 are meta, row 5 is header, 6+ data ──
            const allRows = [
                ['PRE ORDER REPORT'],                // R0 – title
                ['Branch:', branchLabel],            // R1
                ['Date Range:', dateLabel],          // R2
                ['Status:', statusLabel],            // R3
                [],                                  // R4 – spacer
                headers,                             // R5 – header
                ...dataRows                          // R6+
            ];
            const ws = XLSX.utils.aoa_to_sheet(allRows);
            const EXPORT_SCALE = 0.9;

            // ── Merge title across all columns, and merge meta values ──────────
            ws['!merges'] = [
                { s: { r: 0, c: 0 }, e: { r: 0, c: COL - 1 } },    // Title
                { s: { r: 1, c: 1 }, e: { r: 1, c: 4 } },        // Branch value merged across B-E
                { s: { r: 2, c: 1 }, e: { r: 2, c: 4 } },        // Date Range value merged across B-E
                { s: { r: 3, c: 1 }, e: { r: 3, c: 4 } }         // Status value merged across B-E
            ];

            // ── Row heights (points) ────────────────────────────────────────
            ws['!rows'] = [
                { hpt: Math.round(36 * EXPORT_SCALE) },  // R0 title
                { hpt: Math.round(18 * EXPORT_SCALE) },  // R1 branch
                { hpt: Math.round(18 * EXPORT_SCALE) },  // R2 date
                { hpt: Math.round(18 * EXPORT_SCALE) },  // R3 status
                { hpt: Math.round(8 * EXPORT_SCALE) },   // R4 spacer
                { hpt: Math.round(22 * EXPORT_SCALE) }   // R5 header
            ];

            // ── Column widths ───────────────────────────────────────────────
            ws['!cols'] = [
                { wch: Math.round(18 * EXPORT_SCALE) }, // Pre Order No
                { wch: Math.round(24 * EXPORT_SCALE) }, // Customer Name
                { wch: Math.round(30 * EXPORT_SCALE) }, // Item Description
                { wch: Math.round(20 * EXPORT_SCALE) }, // IMEI
                { wch: Math.round(10 * EXPORT_SCALE) }, // Quantity
                { wch: Math.round(14 * EXPORT_SCALE) }, // Unit Price
                { wch: Math.round(14 * EXPORT_SCALE) }, // Total Amount
                { wch: Math.round(14 * EXPORT_SCALE) }, // Payment
                { wch: Math.round(12 * EXPORT_SCALE) }, // Status
                { wch: Math.round(20 * EXPORT_SCALE) }, // Branch
                { wch: Math.round(14 * EXPORT_SCALE) }, // Date Created
                { wch: Math.round(14 * EXPORT_SCALE) }  // Date Claimed
            ];

            // ── Helper: apply style to a cell ───────────────────────────────
            function cs(addr, style) {
                if (!ws[addr]) ws[addr] = { v: '', t: 's' };
                ws[addr].s = style;
            }

            // ── Thick border shorthand ──────────────────────────────────────
            const thick = col => ({ style: 'medium', color: { rgb: col } });
            const thin = col => ({ style: 'thin', color: { rgb: col } });

            // ── ROW 0: Title ─────────────────────────────────────────────────
            cs('A1', {
                font: { bold: true, sz: Math.round(18 * EXPORT_SCALE), color: { rgb: C.titleFg }, name: 'Calibri' },
                fill: { patternType: 'solid', fgColor: { rgb: C.titleBg } },
                alignment: { horizontal: 'center', vertical: 'center' },
                border: {
                    bottom: thick(C.titleBg),
                    left: thick(C.titleBg),
                    right: thick(C.titleBg),
                    top: thick(C.titleBg)
                }
            });

            // ── ROW 1, 2 & 3: Metadata ───────────────────────────────────────
            [1, 2, 3].forEach(ri => {
                const keyAddr = XLSX.utils.encode_cell({ r: ri, c: 0 });
                const valAddr = XLSX.utils.encode_cell({ r: ri, c: 1 });
                cs(keyAddr, {
                    font: { bold: true, sz: Math.round(11 * EXPORT_SCALE), color: { rgb: C.metaKey }, name: 'Calibri' },
                    fill: { patternType: 'solid', fgColor: { rgb: C.metaBg } },
                    alignment: { horizontal: 'left', vertical: 'center' }
                });
                cs(valAddr, {
                    font: { sz: Math.round(11 * EXPORT_SCALE), color: { rgb: C.metaVal }, name: 'Calibri' },
                    fill: { patternType: 'solid', fgColor: { rgb: C.metaBg } },
                    alignment: { horizontal: 'left', vertical: 'center' }
                });
                // Fill remaining cells in metadata rows with same bg
                for (let ci = 2; ci < COL; ci++) {
                    cs(XLSX.utils.encode_cell({ r: ri, c: ci }), {
                        fill: { patternType: 'solid', fgColor: { rgb: C.metaBg } }
                    });
                }
            });

            // ── ROW 5: Header ────────────────────────────────────────────────
            headers.forEach((h, ci) => {
                const addr = XLSX.utils.encode_cell({ r: 5, c: ci });
                cs(addr, {
                    font: { bold: true, sz: Math.round(11 * EXPORT_SCALE), color: { rgb: C.headerFg }, name: 'Calibri' },
                    fill: { patternType: 'solid', fgColor: { rgb: C.headerBg } },
                    alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
                    border: {
                        top: thick(C.accentLine),
                        bottom: thick(C.accentLine),
                        left: thin(C.hdrBorder),
                        right: thin(C.hdrBorder)
                    }
                });
            });

            // ── ROWS 6+: Data ────────────────────────────────────────────────
            dataRows.forEach((drow, ri) => {
                const isEven = ri % 2 === 1;
                const rowBg = isEven ? C.evenRow : C.oddRow;

                headers.forEach((__, ci) => {
                    const addr = XLSX.utils.encode_cell({ r: 6 + ri, c: ci });
                    if (!ws[addr]) ws[addr] = { v: '', t: 's' };

                    const isCurrency = ci === 5 || ci === 6 || ci === 7; // Unit Price, Total Amount, Payment
                    const isLeftAlign = ci === 1 || ci === 2 || ci === 9; // Customer Name, Item Description, Branch

                    ws[addr].s = {
                        font: {
                            sz: Math.round(10 * EXPORT_SCALE),
                            name: 'Calibri',
                            color: { rgb: isCurrency ? C.currency : '1A1A1A' },
                            bold: isCurrency
                        },
                        fill: { patternType: 'solid', fgColor: { rgb: rowBg } },
                        alignment: {
                            horizontal: isCurrency ? 'right' : (isLeftAlign ? 'left' : 'center'),
                            vertical: 'center'
                        },
                        border: {
                            top: thin(C.border),
                            bottom: thin(C.border),
                            left: thin(C.border),
                            right: thin(C.border)
                        }
                    };

                    // Number format: 2dp for currency
                    if (isCurrency) ws[addr].z = '#,##0.00';
                });
            });

            // ── Build workbook & download ─────────────────────────────────────
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Pre Order Report');
            const fileName = 'PreOrder_Report_' + branch.replace(/\s+/g, '_')
                + '_' + dateFrom + '_to_' + dateTo + '.xlsx';
            XLSX.writeFile(wb, fileName);
        }

        function printPreOrderReport() {
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            const branch = document.getElementById('filterBranch').value;
            const status = document.getElementById('filterStatus').value;

            if (!dateFrom || !dateTo) {
                alert('Please select from and to dates before printing.');
                return;
            }
            if (dateFrom > dateTo) {
                alert('From date cannot be later than To date.');
                return;
            }
            if (!branch) {
                alert('Please select a branch before printing.');
                return;
            }

            window.open(
                'print_preorder_report_pdf.php?date_from=' + encodeURIComponent(dateFrom) +
                '&date_to=' + encodeURIComponent(dateTo) +
                '&branch=' + encodeURIComponent(branch) +
                '&status=' + encodeURIComponent(status),
                '_blank',
                'width=900,height=700'
            );
        }
    </script>
</body>

</html>