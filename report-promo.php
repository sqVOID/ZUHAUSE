<?php
require_once 'session_check.php';
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Promo Sales Report</title>
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

        /* -- PROMO REPORT WRAPPER -- */
        .promo-report-wrapper {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }

        .promo-report-header {
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

        .doc-promo-title {
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
            color: #000000;
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

        /* Allow specific headers to wrap without clipping */
        .doc-table th.wrap-header {
            white-space: normal;
            line-height: 1.15;
            padding-top: 7px;
            padding-bottom: 7px;
            min-width: 100px;
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

        /* Summary Section */
        .summary-section {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 15px;
            color: #d32f2f;
            line-height: 1.6;
            max-width: 500px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            letter-spacing: 1px;
            font-weight: 700;
            color: #d32f2f;
        }

        /* -- PRINT STYLES -- */
        @media print {
            @page {
                margin: 0.5in;
                size: A4 landscape;
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

            .doc-promo-title {
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
                font-size: 10px !important;
                background: white !important;
                border: 2px solid #000000 !important;
            }

            .doc-table th {
                border: 2px solid #000000 !important;
                letter-spacing: 1px !important;
                padding: 6px 8px !important;
                text-align: center !important;
                font-weight: bold !important;
                font-size: 10px !important;
                text-transform: uppercase !important;
                background: #f0f0f0 !important;
                white-space: nowrap !important;
                color: #000000 !important;
                font-family: 'Courier New', Courier, monospace !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .doc-table td {
                border: 2px solid #000000 !important;
                letter-spacing: 1px !important;
                padding: 6px 8px !important;
                text-align: center !important;
                font-size: 11px !important;
                white-space: nowrap !important;
                vertical-align: top !important;
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

            .summary-section {
                display: block !important;
                visibility: visible !important;
                page-break-before: avoid !important;
                margin-top: 15px !important;
                font-family: 'Courier New', Courier, monospace !important;
                font-size: 13px !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                color: #d32f2f !important;
                line-height: 1.6 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .summary-row {
                display: flex !important;
                justify-content: space-between !important;
                margin-bottom: 5px !important;
                letter-spacing: 1px !important;
                color: #d32f2f !important;
                font-weight: 700 !important;
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

            .page-title {
                font-size: 18px;
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

            .page-title {
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
        <div class="page-title">Promo Sales Report</div>

        <div class="filter-bar">
            <span class="filter-label">From:</span>
            <input type="date" id="filterDateFrom" class="filter-input" value="<?php echo date('Y-m-d'); ?>">

            <span class="filter-label">To:</span>
            <input type="date" id="filterDateTo" class="filter-input" value="<?php echo date('Y-m-d'); ?>">

            <span class="filter-label">Area:</span>
            <select id="filterArea" class="filter-select" onchange="filterBranchOptions()">
                <option value="">Select Area</option>
                <?php
                // Fetch areas based on user access
                $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
                $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
                
                if ($system_level === 'Super-Admin' || strtoupper($user_branch) === 'SUPERADMIN') {
                    // Super-Admin sees all areas
                    $areas_query = $conn->query("SELECT * FROM areas WHERE status = 'Active' ORDER BY area_name ASC");
                    if ($areas_query && $areas_query->num_rows > 0) {
                        while ($area_row = $areas_query->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($area_row['area_name']) . '">' . htmlspecialchars($area_row['area_name']) . '</option>';
                        }
                    }
                } elseif (!empty($user_branch)) {
                    // Sub-admin or user with specific branches - show only areas of their branches
                    $user_branches = array_map('trim', explode(',', $user_branch));
                    $user_branches = array_filter($user_branches, function ($branch) {
                        return !empty($branch);
                    });
                    
                    if (!empty($user_branches)) {
                        $branch_names_quoted = array_map(function($name) use ($conn) {
                            return "'" . $conn->real_escape_string($name) . "'";
                        }, $user_branches);
                        $branch_in_clause = implode(', ', $branch_names_quoted);
                        
                        // Get unique areas from user's branches
                        $areas_query = $conn->query("
                            SELECT DISTINCT b.area 
                            FROM branches b 
                            WHERE b.branch_name IN ($branch_in_clause) 
                            AND b.area IS NOT NULL 
                            AND b.area != '' 
                            AND b.status = 'Active' 
                            ORDER BY b.area ASC
                        ");
                        
                        if ($areas_query && $areas_query->num_rows > 0) {
                            while ($area_row = $areas_query->fetch_assoc()) {
                                if (!empty($area_row['area'])) {
                                    echo '<option value="' . htmlspecialchars($area_row['area']) . '">' . htmlspecialchars($area_row['area']) . '</option>';
                                }
                            }
                        }
                    }
                }
                ?>
            </select>

            <span class="filter-label">Branch:</span>
            <select id="filterBranch" class="filter-select">
                <option value="">All Branches</option>
                <?php
                if ($system_level === 'Super-Admin' || strtoupper($user_branch) === 'SUPERADMIN') {
                    // Super-Admin sees all branches
                    $branches_query = $conn->query("SELECT branch_name FROM branches WHERE status = 'Active' ORDER BY branch_name ASC");
                    if ($branches_query && $branches_query->num_rows > 0) {
                        while ($branch_row = $branches_query->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($branch_row['branch_name']) . '">' . htmlspecialchars($branch_row['branch_name']) . '</option>';
                        }
                    }
                } elseif (!empty($user_branch)) {
                    // User with specific branches
                    $user_branches = array_map('trim', explode(',', $user_branch));
                    $user_branches = array_filter($user_branches, function ($branch) {
                        return !empty($branch);
                    });
                    
                    foreach ($user_branches as $branch) {
                        echo '<option value="' . htmlspecialchars($branch) . '">' . htmlspecialchars($branch) . '</option>';
                    }
                }
                ?>
            </select>

            <span class="filter-label">Promo:</span>
            <select id="filterPromo" class="filter-select">
                <option value="">All Promos</option>
                <?php
                // Fetch all active promos
                $promos_query = $conn->query("SELECT id, promo_name FROM promos WHERE status = 'Active' ORDER BY promo_name ASC");
                if ($promos_query && $promos_query->num_rows > 0) {
                    while ($promo_row = $promos_query->fetch_assoc()) {
                        echo '<option value="' . htmlspecialchars($promo_row['id']) . '">' . htmlspecialchars($promo_row['promo_name']) . '</option>';
                    }
                }
                ?>
            </select>

            <button class="btn-search" onclick="loadPromoReport()">Search</button>
            <button class="btn-print" onclick="printReport()">Print</button>
            <button class="btn-export" onclick="exportToExcel()">Export to Excel</button>
        </div>

        <div class="promo-report-wrapper">
            <div class="promo-report-header">Promo Sales Report</div>
            <div class="receipt-area">
                <div class="doc-print-zone">
                    <div class="doc-card">
                        <div class="doc-head">
                            <img src="Icon/ZUHAUSE-LOGO.png" alt="ZUHAUSE Logo" class="doc-logo">
                            <div class="doc-promo-title">PROMO SALES REPORT</div>
                        </div>
                        <div class="doc-meta">
                            <div class="doc-meta-row">
                                <div class="left"><span class="meta-label">Date Range:</span> <span id="displayDateRange">-</span></div>
                                <div class="right"><span class="meta-label">Branch:</span> <span id="displayBranch">All Branches</span></div>
                            </div>
                            <div class="doc-meta-row">
                                <div class="left"><span class="meta-label">Area:</span> <span id="displayArea">All Areas</span></div>
                                <div class="right"><span class="meta-label">Promo:</span> <span id="displayPromo">All Promos</span></div>
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table class="doc-table">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Invoice No</th>
                                        <th>Date</th>
                                        <th>Branch</th>
                                        <th>Customer Name</th>
                                        <th class="wrap-header">Promo Name</th>
                                        <th class="wrap-header">1st Item</th>
                                        <th class="wrap-header">Item Model</th>
                                        <th class="wrap-header">Used Promo Item</th>
                                        <th class="wrap-header">Discount Type</th>
                                        <th class="wrap-header">Discount Value</th>
                                        <th class="wrap-header">Total Amount</th>
                                        <th>Assisted By</th>
                                    </tr>
                                </thead>
                                <tbody id="promoTableBody">
                                    <tr>
                                        <td colspan="13" class="td-no-data">No promo sales data. Click "Search" to load report.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="summary-section" id="summarySection" style="display: none;">
                            <div class="summary-row">
                                <span>TOTAL TRANSACTIONS:</span>
                                <span id="totalTransactions">0</span>
                            </div>
                            <div class="summary-row">
                                <span>TOTAL DISCOUNT VALUE:</span>
                                <span id="totalDiscountValue">₱0.00</span>
                            </div>
                            <div class="summary-row">
                                <span>TOTAL SALES AMOUNT:</span>
                                <span id="totalSalesAmount">₱0.00</span>
                            </div>
                        </div>
                        <div class="doc-footer">
                            <div class="doc-signature">
                                <div class="doc-signature-line"></div>
                                <div>PREPARED BY</div>
                            </div>
                            <div class="doc-page">Page 1</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle function
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.getElementById('mainContent');
            const menuBtn = document.querySelector('.menu-btn');

            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
            menuBtn.classList.toggle('active');
        }

        // Branch data for filtering
        let allBranchData = [];

        // Filter branch options based on selected area
        function filterBranchOptions() {
            const areaSelect = document.getElementById('filterArea');
            const branchSelect = document.getElementById('filterBranch');
            const selectedArea = areaSelect.value;

            if (!selectedArea) {
                // If no area selected, reload all branches
                loadAllBranches();
                return;
            }

            // Filter branches by area
            fetch(`get_branches_by_area.php?area=${encodeURIComponent(selectedArea)}`)
                .then(response => response.json())
                .then(data => {
                    branchSelect.innerHTML = '<option value="">All Branches</option>';
                    if (data.branches && data.branches.length > 0) {
                        data.branches.forEach(branch => {
                            const option = document.createElement('option');
                            option.value = branch;
                            option.textContent = branch;
                            branchSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error filtering branches:', error);
                });
        }

        // Load all branches
        function loadAllBranches() {
            const branchSelect = document.getElementById('filterBranch');
            const originalOptions = branchSelect.innerHTML;
            branchSelect.innerHTML = originalOptions;
        }

        // Load promo report
        function loadPromoReport() {
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            const area = document.getElementById('filterArea').value;
            const branch = document.getElementById('filterBranch').value;
            const promoId = document.getElementById('filterPromo').value;
            const promoText = document.getElementById('filterPromo').selectedOptions[0]?.text || 'All Promos';

            if (!dateFrom || !dateTo) {
                alert('Please select both From and To dates.');
                return;
            }

            // Update display values
            document.getElementById('displayDateRange').textContent = `${dateFrom} to ${dateTo}`;
            document.getElementById('displayArea').textContent = area || 'All Areas';
            document.getElementById('displayBranch').textContent = branch || 'All Branches';
            document.getElementById('displayPromo').textContent = promoText;

            // Fetch data
            const params = new URLSearchParams({
                date_from: dateFrom,
                date_to: dateTo,
                area: area,
                branch: branch,
                promo_id: promoId
            });

            fetch(`fetch_promo_report.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderPromoReport(data.data);
                        updateSummary(data.data);
                    } else {
                        alert(data.message || 'Error loading report');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading promo report');
                });
        }

        // Render promo report table
        function renderPromoReport(data) {
            const tbody = document.getElementById('promoTableBody');

            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="13" class="td-no-data">No promo sales found for the selected criteria.</td></tr>';
                document.getElementById('summarySection').style.display = 'none';
                return;
            }

            tbody.innerHTML = '';
            data.forEach((row, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${row.invoice_no || '-'}</td>
                    <td>${row.created_at || '-'}</td>
                    <td class="td-text-left">${row.branch_name || '-'}</td>
                    <td class="td-text-left">${row.customer_name || '-'}</td>
                    <td class="td-text-left">${row.promo_name || '-'}</td>
                    <td class="td-text-left">${row.first_item || '-'}</td>
                    <td class="td-text-left">${row.motor_model || '-'}</td>
                    <td class="td-text-left">${row.free_item || '-'}</td>
                    <td class="td-text-left">${row.discount_type || '-'}</td>
                    <td class="td-number">${formatCurrency(row.discount_value || 0)}</td>
                    <td class="td-number">${formatCurrency(row.total_amount || 0)}</td>
                    <td class="td-text-left">${row.assisted_by || '-'}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Update summary section
        function updateSummary(data) {
            const summarySection = document.getElementById('summarySection');

            if (!data || data.length === 0) {
                summarySection.style.display = 'none';
                return;
            }

            const totalTransactions = data.length;
            const totalDiscountValue = data.reduce((sum, row) => sum + parseFloat(row.discount_value || 0), 0);
            const totalSalesAmount = data.reduce((sum, row) => sum + parseFloat(row.total_amount || 0), 0);

            document.getElementById('totalTransactions').textContent = totalTransactions;
            document.getElementById('totalDiscountValue').textContent = formatCurrency(totalDiscountValue);
            document.getElementById('totalSalesAmount').textContent = formatCurrency(totalSalesAmount);

            summarySection.style.display = 'block';
        }

        // Format currency
        function formatCurrency(value) {
            return '₱' + parseFloat(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        // Print report
        function printReport() {
            window.print();
        }

        // Export to Excel
        function exportToExcel() {
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            const area = document.getElementById('filterArea').value || 'All';
            const branch = document.getElementById('filterBranch').value || 'All';
            const promoText = document.getElementById('filterPromo').selectedOptions[0]?.text || 'All';

            const table = document.querySelector('.doc-table');
            const tbody = document.getElementById('promoTableBody');

            if (!tbody.querySelector('tr:not(.td-no-data)')) {
                alert('No data to export. Please search for promo sales first.');
                return;
            }

            // Create workbook
            const wb = XLSX.utils.book_new();

            // Convert table to worksheet
            const ws = XLSX.utils.table_to_sheet(table);

            // Add worksheet to workbook
            XLSX.utils.book_append_sheet(wb, ws, 'Promo Report');

            // Generate filename
            const filename = `Promo_Report_${dateFrom}_to_${dateTo}_${area}_${branch}_${promoText.replace(/\s+/g, '_')}.xlsx`;

            // Save file
            XLSX.writeFile(wb, filename);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Promo Sales Report page loaded');
        });
    </script>
</body>

</html>
