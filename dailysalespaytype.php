<?php
require_once 'session_check.php';
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
     <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Payment Details Report</title>
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
            background-color: var(--color-gold-pale);
            color: var(--color-navy);
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
            border-color: var(--color-gold);
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

        /* -- SALES HISTORY WRAPPER -- */
        .sales-history-wrapper {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }

        .sales-history-header {
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

        .doc-sales-title {
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

            .doc-card {
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                background: white !important;
                font-family: 'Courier New', Courier, monospace !important;
                font-size: 13px !important;
                color: #000000 !important;
            }

            .doc-logo {
                height: 80px !important;
                width: auto !important;
                max-width: none !important;
                min-height: 80px !important;
                border-radius: 5px !important;
                display: block !important;
                position: relative !important;
                z-index: 999 !important;
                background: transparent !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .doc-head {
                position: relative !important;
                display: flex !important;
                align-items: flex-start !important;
                margin-bottom: 18px !important;
                background: transparent !important;
            }

            .doc-sales-title {
                position: absolute !important;
                left: 0 !important;
                right: 0 !important;
                text-align: center !important;
                font-size: 22px !important;
                font-weight: bold !important;
                letter-spacing: 3px !important;
                font-family: 'Courier New', Courier, monospace !important;
                color: #000000 !important;
                background: transparent !important;
                z-index: 1 !important;
            }

            .doc-meta {
                margin-bottom: 8px !important;
            }

            .doc-meta-row {
                display: flex !important;
                justify-content: space-between !important;
                font-size: 14px !important;
                margin-bottom: 4px !important;
                color: #000000 !important;
                letter-spacing: 1px !important;
                font-weight: bold !important;
                font-family: 'Courier New', Courier, monospace !important;
            }

            .doc-meta-row .left,
            .doc-meta-row .right,
            .meta-label {
                color: #000000 !important;
                background: white !important;
                font-weight: bold !important;
            }

            .doc-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 12px !important;
                margin-bottom: 18px !important;
                font-family: 'Courier New', Courier, monospace !important;
                font-size: 11px !important;
                background: white !important;
                border: 2px solid #000000 !important;
            }

            .doc-table th {
                border: 2px solid #000000 !important;
                letter-spacing: 1px !important;
                padding: 6px 8px !important;
                text-align: center !important;
                font-weight: bold !important;
                font-size: 11px !important;
                text-transform: uppercase !important;
                background: #f0f0f0 !important;
                white-space: nowrap !important;
                color: #000000 !important;
                font-family: 'Courier New', Courier, monospace !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .doc-table td,
            .doc-table tbody td,
            .doc-table tr td,
            table.doc-table td,
            #salesTableBody td {
                border: 2px solid #000000 !important;
                letter-spacing: 1px !important;
                padding: 6px 8px !important;
                text-align: center !important;
                font-size: 12px !important;
                white-space: nowrap !important;
                vertical-align: top !important;
                color: #000000 !important;
                background: white !important;
                font-family: 'Courier New', Courier, monospace !important;
                font-weight: bold !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .doc-table td *,
            .doc-table tbody td *,
            .doc-table tr td *,
            #salesTableBody td *,
            .doc-table span,
            .doc-table div,
            .doc-table p {
                color: #000000 !important;
                background: white !important;
                font-family: 'Courier New', Courier, monospace !important;
                font-weight: bold !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .doc-table td.td-text-left {
                text-align: left !important;
                color: #000000 !important;
                font-weight: bold !important;
            }

            .doc-table td.td-number {
                text-align: right !important;
                color: #000000 !important;
                font-weight: bold !important;
            }

            .doc-table td.td-no-data {
                text-align: center !important;
                color: #000000 !important;
                font-size: 14px !important;
                padding: 12px !important;
                font-style: italic !important;
                font-weight: bold !important;
            }

            .doc-footer {
                display: flex !important;
                justify-content: space-between !important;
                align-items: flex-end !important;
                margin-top: 34px !important;
                position: relative !important;
            }

            .doc-signature {
                font-family: 'Courier New', Courier, monospace !important;
                font-size: 13px !important;
                font-weight: bold !important;
                color: #000000 !important;
                letter-spacing: 1px !important;
            }

            .doc-signature-line {
                border-top: 2px solid #000000 !important;
                width: 200px !important;
                margin-bottom: 5px !important;
            }

            .doc-page {
                font-weight: bold !important;
                font-family: 'Courier New', Courier, monospace !important;
                font-size: 18px !important;
                color: #000000 !important;
                text-align: right !important;
                position: fixed !important;
                bottom: 0.5in !important;
                right: 0.5in !important;
            }

            .no-print {
                display: none !important;
            }
        }

        /* Responsive sidebar toggle for mobile/tablet */
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

            .header {
                padding: 0 15px;
                gap: 15px;
            }

            .content-header h2 {
                font-size: 18px;
            }

            .filter-section {
                padding: 20px;
            }

            /* Enable horizontal scrolling for tables on mobile */
            .doc-card {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .doc-table {
                min-width: 800px;
                display: table;
            }

            /* Add scrollbar styling for better visibility */
            .doc-card::-webkit-scrollbar {
                height: 8px;
            }

            .doc-card::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }

            .doc-card::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 4px;
            }

            .doc-card::-webkit-scrollbar-thumb:hover {
                background: #555;
            }
        }

        @media (max-width: 480px) {
            .header {
                height: 50px;
                padding: 0 10px;
                gap: 10px;
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

            .filter-section {
                padding: 15px;
            }

            .content-header h2 {
                font-size: 16px;
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
        <div class="page-title">Payment Details Report</div>

        <div class="filter-bar">
            <?php
            // Check user role
            $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
            $is_user = ($system_level === 'User');
            
            if ($is_user) {
                // User: Only show From date (single date)
                echo '<span class="filter-label">Date:</span>';
                echo '<input type="date" id="filterDateFrom" class="filter-input" value="' . date('Y-m-d') . '">';
            } else {
                // Sub-admin & Super-Admin: Show From and To (date range)
                echo '<span class="filter-label">From:</span>';
                echo '<input type="date" id="filterDateFrom" class="filter-input" value="' . date('Y-m-d') . '">';
                echo '<span class="filter-label">To:</span>';
                echo '<input type="date" id="filterDateTo" class="filter-input" value="' . date('Y-m-d') . '">';
            }
            ?>

            <span class="filter-label">Branch:</span>
            <select id="filterBranch" class="filter-select">
                <?php
                $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
                $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

                if ($system_level === 'Super-Admin' || strtoupper($user_branch) === 'SUPERADMIN') {
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

            <button class="btn-search" onclick="searchSalesReport()">Search</button>
            <button class="btn-print" onclick="printSalesReport()">Print</button>
        </div>

        <div class="sales-history-wrapper">
            <div class="sales-history-header">PAYMENT DETAILS REPORT</div>
            <div class="receipt-area">
                <div class="doc-card doc-print-zone" id="docCard">

                    <!-- Doc head: logo + title -->
                    <div class="doc-head">
                        <img src="Icon/ZUHAUSE-LOGO.png" alt="ZUHAUSE LOGO" class="doc-logo">
                        <div class="doc-sales-title">PAYMENT DETAILS REPORT</div>
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
                    </div>

                    <div class="table-wrap">
                        <table class="doc-table">
                            <thead>
                                <tr>
                                    <th>Invoice Number</th>
                                    <th>Customer Name</th>
                                    <th>SRP</th>
                                    <th>Payment Method</th>
                                    <th>Reference No.</th>
                                    <th>Loan Type</th>
                                    <th>Loan Terms</th>
                                    <th>Loan Number</th>
                                    <th>Loan Balance</th>
                                    <th>DP Method</th>
                                    <th>DP Ref</th>
                                    <th>DP Amount</th>
                                    <th>Old Unit Amount</th>
                                    <th>Amount</th>
                                    <th>Refund Amount</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody id="salesTableBody">
                                <tr>
                                    <td class="td-no-data" colspan="16">SELECT A FILTER TO DISPLAY THE DATA</td>
                                </tr>
                            </tbody>
                        </table>
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

        function searchSalesReport() {
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateToField = document.getElementById('filterDateTo');
            const dateTo = dateToField ? dateToField.value : dateFrom; // If no "To" field, use "From" value
            const branch = document.getElementById('filterBranch').value;
            const tbody = document.getElementById('salesTableBody');

            if (!dateFrom) {
                alert('Please select a date.');
                return;
            }
            if (dateToField && !dateTo) {
                alert('Please select both from and to dates.');
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
            
            // Display date or date range based on whether it's a range
            if (dateFrom === dateTo) {
                document.getElementById('displayDateRange').textContent = formatDate(dateFrom);
            } else {
                document.getElementById('displayDateRange').textContent = formatDate(dateFrom) + ' to ' + formatDate(dateTo);
            }
            
            tbody.innerHTML = '<tr><td class="td-no-data" colspan="16">Loading...</td></tr>';

            fetch('fetch_dailysalespaytype.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    date_from: dateFrom,
                    date_to: dateTo,
                    branch: branch
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status !== 'success') {
                        tbody.innerHTML = '<tr><td class="td-no-data" colspan="16">' + (data.message || 'Error loading report') + '</td></tr>';
                        return;
                    }

                    if (!data.rows || data.rows.length === 0) {
                        tbody.innerHTML = '<tr><td class="td-no-data" colspan="16">NO DATA</td></tr>';
                        return;
                    }

                    let html = '';
                    let totalAmount = 0;
                    data.rows.forEach(row => {
                        // Check if this is a refunded or UPGD sale
                        const isRefunded = row.display_status === 'refunded';
                        const isUpgrade = row.upgrade === 'UPGD';
                        const rowStyle = isRefunded ? ' style="color:#d32f2f;"' : '';
                        const refundIndicator = isRefunded ? ' RF' : '';
                        const oldUnitAmountRaw = Math.round(Number(row.old_unit_amount || 0));
                        const oldUnitAmount = (oldUnitAmountRaw > 0)
                            ? oldUnitAmountRaw.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                            : '0.00';
                        // Use total_amount (from sales_entry or preorder) instead of invoice_items_total
                        const rowTotalAmountRaw = Math.round(Number(row.total_amount || 0));
                        const refundAmountRaw = Math.round(Number(row.refund_amount || 0));
                        totalAmount += rowTotalAmountRaw;

                        // Amount shows collected payment value(s). For upgraded invoices, backend already combines original + upgrade payments.
                        const displayAmount = row.payment_amount
                            ? Math.round(Number(row.payment_amount.toString().replace(/,/g, ''))).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                            : '';

                        const srpAmount = row.srp_amount
                            ? Math.round(Number(row.srp_amount.toString().replace(/,/g, ''))).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                            : '';

                        html += `
                        <tr${rowStyle}>
                            <td class="td-text-left">${row.invoice_no || ''}${refundIndicator}</td>
                            <td class="td-text-left">${row.customer_name || ''}</td>
                            <td class="td-number">${srpAmount}</td>
                            <td class="td-text-left">${row.payment_method || ''}</td>
                            <td>${row.reference_no || ''}</td>
                            <td>${row.loan_type || ''}</td>
                            <td>${row.loan_term || ''}</td>
                            <td>${row.loan_number || ''}</td>
                            <td class="td-number">${row.loan_balance ? Number(row.loan_balance.toString().replace(/,/g, '')).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : ''}</td>
                            <td>${row.dp_method || ''}</td>
                            <td>${row.dp_ref || ''}</td>
                            <td class="td-number">${row.dp_amount ? Number(row.dp_amount.toString().replace(/,/g, '')).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : ''}</td>
                            <td class="td-number">${oldUnitAmount}</td>
                            <td class="td-number">${displayAmount}</td>
                            <td class="td-number">${refundAmountRaw.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                            <td class="td-number">${rowTotalAmountRaw.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        </tr>
                    `;
                    });

                    html += `
                    <tr>
                        <td colspan="16" style="text-align: left; padding-top: 20px; border: none; padding-left: 5px;">
                            <span style="color: #d32f2f; font-weight: bold; font-family: Courier, monospace; font-size: 14px; display: inline-block; width: 140px;">TOTAL AMOUNT:</span>
                            <span style="color: #d32f2f; font-weight: bold; font-family: Courier, monospace; font-size: 14px; display: inline-block; width: 120px; text-align: right;">${totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                        </td>
                    </tr>
                `;

                    tbody.innerHTML = html;
                })
                .catch(err => {
                    tbody.innerHTML = '<tr><td class="td-no-data" colspan="16">Error loading report: ' + err.message + '</td></tr>';
                });
        }

        function exportToExcel() {
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateToField = document.getElementById('filterDateTo');
            const dateTo = dateToField ? dateToField.value : dateFrom; // If no "To" field, use "From" value
            const branch = document.getElementById('filterBranch').value;

            if (!dateFrom || !dateTo) { alert('Please select from and to dates before exporting.'); return; }
            if (dateFrom > dateTo) { alert('From date cannot be later than To date.'); return; }
            if (!branch) { alert('Please select a branch before exporting.'); return; }

            const tbody = document.getElementById('salesTableBody');
            const rows = tbody.querySelectorAll('tr');

            if (rows.length === 0 || (rows.length === 1 && rows[0].querySelector('.td-no-data'))) {
                alert('No data to export. Please search first.');
                return;
            }

            const branchLabel = document.getElementById('displayBranch').textContent.trim() || branch;
            const dateLabel = document.getElementById('displayDateRange').textContent.trim()
                || (formatDate(dateFrom) + ' to ' + formatDate(dateTo));

            // ── Colour palette ───────────────────────────────────────────────
            const C = {
                titleBg: '000000',   // title background (black)
                titleFg: 'FFFFFF',   // title text (white)
                headerBg: '0E4C2F',   // header row background (dark green)
                headerFg: 'FFFFFF',   // header text (white)
                metaBg: 'EAF0FB',   // branch/date rows background
                metaKey: '000000',   // metadata label colour
                metaVal: '333333',   // metadata value colour
                oddRow: 'FFFFFF',   // data odd rows
                evenRow: 'F0F5FF',   // data even rows (light blue tint)
                border: 'B0BEC5',   // data cell borders
                hdrBorder: '000000',   // header borders
                currency: '1A5C38',   // total sales font colour
                accentLine: '000000'    // thick accent border colour
            };

            // ── Column definitions ──────────────────────────────────────────
            const headers = [
                'Invoice Number', 'Customer Name', 'SRP', 'Payment Method', 'Reference No.',
                'Loan Type', 'Loan Terms', 'Loan Number', 'Loan Balance', 'DP Method', 'DP Ref', 'DP Amount', 'Old Unit Amount', 'Amount', 'Refund Amount', 'Total Amount'
            ];
            const COL = headers.length;

            // ── Collect data rows ───────────────────────────────────────────
            const dataRows = [];
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length === 0 || row.style.fontWeight === 'bold') return; // skip header
                const r = Array.from(cells).map(cell => cell.textContent.trim());
                if (r[0] === 'NO DATA') {
                    // skip
                } else {
                    // loan balance is 7
                    r[7] = isNaN(r[7].replace(/,/g, '')) || r[7] === '' ? r[7] : Number(r[7].replace(/,/g, ''));
                    // dp amount is 10
                    r[10] = isNaN(r[10].replace(/,/g, '')) || r[10] === '' ? r[10] : Number(r[10].replace(/,/g, ''));
                    // old unit amount is 11
                    r[11] = isNaN(r[11].replace(/,/g, '')) || r[11] === '' ? r[11] : Number(r[11].replace(/,/g, ''));
                    // amount is 12
                    r[12] = isNaN(r[12].replace(/,/g, '')) || r[12] === '' ? r[12] : Number(r[12].replace(/,/g, ''));
                    // refund amount is 13
                    r[13] = isNaN(r[13].replace(/,/g, '')) || r[13] === '' ? r[13] : Number(r[13].replace(/,/g, ''));
                    // total amount is 14
                    r[14] = isNaN(r[14].replace(/,/g, '')) || r[14] === '' ? r[14] : Number(r[14].replace(/,/g, ''));
                    dataRows.push(r);
                }
            });

            // ── aoa_to_sheet: rows 0-4 are meta, row 4 is header, 5+ data ──
            const allRows = [
                ['DAILY SALES PAYMENT TYPE'],        // R0 – title
                ['Branch:', branchLabel],            // R1
                ['Date Range:', dateLabel],          // R2
                [],                                  // R3 – spacer
                headers,                             // R4 – header
                ...dataRows                          // R5+
            ];
            const ws = XLSX.utils.aoa_to_sheet(allRows);

            // ── Merge title across all columns, and merge meta values ──────────
            ws['!merges'] = [
                { s: { r: 0, c: 0 }, e: { r: 0, c: COL - 1 } }, // Title
                { s: { r: 1, c: 1 }, e: { r: 1, c: 4 } },     // Branch value merged across B-E
                { s: { r: 2, c: 1 }, e: { r: 2, c: 4 } }      // Date Range value merged across B-E
            ];

            // ── Row heights (points) ────────────────────────────────────────
            ws['!rows'] = [
                { hpt: 36 },  // R0 title
                { hpt: 18 },  // R1 branch
                { hpt: 18 },  // R2 date
                { hpt: 8 },  // R3 spacer
                { hpt: 22 },  // R4 header
            ];

            // ── Column widths ───────────────────────────────────────────────
            ws['!cols'] = [
                { wch: 18 }, // Invoice Number
                { wch: 24 }, // Customer Name
                { wch: 16 }, // SRP
                { wch: 18 }, // Payment Method
                { wch: 20 }, // Reference No.
                { wch: 16 }, // Loan Type
                { wch: 14 }, // Loan Terms
                { wch: 18 }, // Loan Number
                { wch: 16 }, // Loan Balance
                { wch: 16 }, // DP Method
                { wch: 20 }, // DP Ref
                { wch: 16 }, // DP Amount
                { wch: 16 }, // Old Unit Amount
                { wch: 14 }, // Amount
                { wch: 16 }, // Refund Amount
                { wch: 16 }  // Total Amount
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
                font: { bold: true, sz: 18, color: { rgb: C.titleFg }, name: 'Calibri' },
                fill: { patternType: 'solid', fgColor: { rgb: C.titleBg } },
                alignment: { horizontal: 'center', vertical: 'center' },
                border: {
                    bottom: thick(C.titleBg),
                    left: thick(C.titleBg),
                    right: thick(C.titleBg),
                    top: thick(C.titleBg)
                }
            });

            // ── ROW 1 & 2: Metadata ──────────────────────────────────────────
            [1, 2].forEach(ri => {
                const keyAddr = XLSX.utils.encode_cell({ r: ri, c: 0 });
                const valAddr = XLSX.utils.encode_cell({ r: ri, c: 1 });
                cs(keyAddr, {
                    font: { bold: true, sz: 11, color: { rgb: C.metaKey }, name: 'Calibri' },
                    fill: { patternType: 'solid', fgColor: { rgb: C.metaBg } },
                    alignment: { horizontal: 'left', vertical: 'center' }
                });
                cs(valAddr, {
                    font: { sz: 11, color: { rgb: C.metaVal }, name: 'Calibri' },
                    fill: { patternType: 'solid', fgColor: { rgb: C.metaBg } },
                    alignment: { horizontal: 'left', vertical: 'center' }
                });
                for (let ci = 2; ci < COL; ci++) {
                    cs(XLSX.utils.encode_cell({ r: ri, c: ci }), {
                        fill: { patternType: 'solid', fgColor: { rgb: C.metaBg } }
                    });
                }
            });

            // ── ROW 4: Header ────────────────────────────────────────────────
            headers.forEach((h, ci) => {
                const addr = XLSX.utils.encode_cell({ r: 4, c: ci });
                cs(addr, {
                    font: { bold: true, sz: 11, color: { rgb: C.headerFg }, name: 'Calibri' },
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

            // ── ROWS 5+: Data ────────────────────────────────────────────────
            dataRows.forEach((drow, ri) => {
                const isEven = ri % 2 === 1;
                const rowBg = isEven ? C.evenRow : C.oddRow;

                headers.forEach((__, ci) => {
                    const addr = XLSX.utils.encode_cell({ r: 5 + ri, c: ci });
                    if (!ws[addr]) ws[addr] = { v: '', t: 's' };

                    const isCurrency = ci === 2 || ci === 8 || ci === 11 || ci === 12 || ci === 13 || ci === 14 || ci === 15;
                    const isLeftAlign = ci === 0 || ci === 1 || ci === 3 || ci === 4 || ci === 5 || ci === 7 || ci === 9 || ci === 10;

                    ws[addr].s = {
                        font: {
                            sz: 10,
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

                    if (isCurrency) ws[addr].z = '#,##0.00';
                });
            });

            // ── Build workbook & download ─────────────────────────────────────
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Daily Sales Payment Type');
            const fileName = 'Sales_Payment_Type_' + branch.replace(/\s+/g, '_')
                + '_' + dateFrom + '_to_' + dateTo + '.xlsx';
            XLSX.writeFile(wb, fileName);
        }

        function printSalesReport() {
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateToField = document.getElementById('filterDateTo');
            const dateTo = dateToField ? dateToField.value : dateFrom; // If no "To" field, use "From" value
            const branch = document.getElementById('filterBranch').value;

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
                'print_dailysalespaytype_pdf.php?date_from=' + encodeURIComponent(dateFrom) +
                '&date_to=' + encodeURIComponent(dateTo) +
                '&branch=' + encodeURIComponent(branch),
                '_blank',
                'width=1200,height=700'
            );
        }
    </script>
</body>

</html>