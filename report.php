report.php
100%
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
    <title>Daily Sales</title>
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

        /* Header row: logo left, DAILY SALES absolutely centred */
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

        /* Breakdown Section */
        .breakdown-item {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            margin-bottom: 3px;
            color: #000000;
            line-height: 1.5;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            gap: 40px;
            max-width: 450px;
        }

        .breakdown-name {
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #000000;
        }

        .breakdown-details {
            margin-left: 0;
            line-height: 1.5;
        }

        .breakdown-label {
            font-weight: 700;
            text-transform: uppercase;
            color: #000000;
        }

        .breakdown-value {
            color: #000000;
        }

        .breakdown-red {
            color: #d32f2f;
            font-weight: 700;
        }

        .breakdown-summary {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 5px;
            color: #d32f2f;
            line-height: 1.5;
            max-width: 450px;
        }

        .breakdown-summary-row {
            display: flex;
            justify-content: space-between;
            gap: 40px;
            margin-bottom: 3px;
            letter-spacing: 1px;
            font-weight: 700;
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

            /* Hide everything except print zone */
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

            /* Logo - FORCE LARGER SIZE AND FRONT POSITION */
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

            /* AGGRESSIVE TABLE STYLING */
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

            /* FORCE BLACK TEXT IN TABLE CELLS */
            .doc-table td,
            .doc-table tbody td,
            .doc-table tr td,
            table.doc-table td,
            #salesTable td,
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

            /* SUPER AGGRESSIVE TEXT COLOR OVERRIDE */
            .doc-table td *,
            .doc-table tbody td *,
            .doc-table tr td *,
            #salesTable td *,
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

            /* Breakdown print styles */
            #breakdownSection {
                display: block !important;
                visibility: visible !important;
                page-break-before: avoid !important;
                margin-top: 20px !important;
            }

            /* Unclaimed Freebies print styles */
            #unclaimedFreebiesSection {
                display: block !important;
                visibility: visible !important;
                page-break-before: auto !important;
                margin-top: 30px !important;
            }

            #unclaimedFreebiesSection * {
                visibility: visible !important;
                color: #000000 !important;
            }

            #unclaimedFreebiesSection .doc-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 12px !important;
                font-family: 'Courier New', Courier, monospace !important;
                font-size: 10px !important;
            }

            #unclaimedFreebiesSection .doc-table th,
            #unclaimedFreebiesSection .doc-table td {
                border: 1px solid #000000 !important;
                padding: 4px 6px !important;
                color: #000000 !important;
                font-family: 'Courier New', Courier, monospace !important;
            }

            .breakdown-item {
                font-family: 'Courier New', Courier, monospace !important;
                font-size: 13px !important;
                margin-bottom: 3px !important;
                color: #000000 !important;
                visibility: visible !important;
                line-height: 1.5 !important;
                font-weight: 700 !important;
                display: flex !important;
                justify-content: space-between !important;
                gap: 40px !important;
                max-width: 450px !important;
            }

            .breakdown-name {
                font-weight: 700 !important;
                text-transform: uppercase !important;
                letter-spacing: 1px !important;
                color: #000000 !important;
            }

            .breakdown-details {
                margin-left: 0 !important;
                line-height: 1.5 !important;
                color: #000000 !important;
            }

            .breakdown-label {
                font-weight: 400 !important;
                text-transform: uppercase !important;
                color: #000000 !important;
            }

            .breakdown-value {
                color: #000000 !important;
            }

            .breakdown-red {
                color: #d32f2f !important;
                font-weight: 700 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .breakdown-summary {
                font-family: 'Courier New', Courier, monospace !important;
                font-size: 13px !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                margin-top: 5px !important;
                color: #d32f2f !important;
                line-height: 1.5 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                max-width: 450px !important;
            }

            .breakdown-summary-row {
                display: flex !important;
                justify-content: space-between !important;
                gap: 40px !important;
                margin-bottom: 3px !important;
                letter-spacing: 1px !important;
                color: #d32f2f !important;
                font-weight: 700 !important;
            }

            .no-print {
                display: none !important;
            }

            /* Remove link styling in print */
            .doc-table td a,
            .doc-table td a:link,
            .doc-table td a:visited {
                color: #000000 !important;
                text-decoration: none !important;
                font-weight: bold !important;
            }

            .modal {
                display: none !important;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 3% auto;
            padding: 0;
            border: 1px solid #888;
            border-radius: 8px;
            width: 90%;
            max-width: 1000px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 20px 25px;
            background-color: #000000ff;
            color: white;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .modal-close {
            color: white;
            font-size: 32px;
            font-weight: bold;
            line-height: 1;
            cursor: pointer;
            transition: color 0.2s;
            background: none;
            border: none;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover,
        .modal-close:focus {
            color: #ffcccc;
        }

        .modal-body {
            padding: 0;
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
</head>

<body>

    <!-- -- HEADER -- -->
    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()">
            <span></span><span></span><span></span>
        </div>
        <!-- <img src="Icon/motogam_logo.jpg" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <!-- -- SIDEBAR -- -->
    <?php include '_sidebar.php'; ?>

    <!-- -- MAIN CONTENT -- -->
    <div class="main-content" id="mainContent">

        <div class="page-title">Daily Sales Report</div>

        <!-- Filter Bar -->
        <div class="filter-bar no-print">
            <?php
            // All users see single date field (Daily Report)
            echo '<span class="filter-label">Date:</span>';
            echo '<input type="date" id="filterDateFrom" class="filter-input" value="' . date('Y-m-d') . '">';
            ?>

            <span class="filter-label">Branch:</span>
            <div style="position:relative; display:inline-block;">
                <select id="filterBranch" class="filter-select">
                    <?php
                    // Get user's branch access
                    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
                    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

                    // Check if user is Super-Admin (has access to all branches)
                    if ($system_level === 'Super-Admin' || strtoupper($user_branch) === 'SUPERADMIN') {
                        // Show all branches for super admin
                        echo '<option value="">Select Branch</option>';
                        $branches_result = $conn->query("SELECT branch_name FROM branches WHERE status = 'Active' ORDER BY branch_name");
                        if ($branches_result && $branches_result->num_rows > 0) {
                            while ($branch = $branches_result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($branch['branch_name']) . '">' . htmlspecialchars($branch['branch_name']) . '</option>';
                            }
                        }
                    } elseif (!empty($user_branch)) {
                        // Check if user has access to multiple branches (comma-separated)
                        $user_branches = array_map('trim', explode(',', $user_branch));
                        // Remove empty values
                        $user_branches = array_filter($user_branches, function ($branch) {
                            return !empty($branch);
                        });

                        if (count($user_branches) == 1) {
                            // Single branch access - auto-select and disable dropdown
                            echo '<option value="' . htmlspecialchars($user_branches[0]) . '" selected>' . htmlspecialchars($user_branches[0]) . '</option>';
                            echo '<script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    document.getElementById("filterBranch").disabled = true;
                                    document.getElementById("displayBranch").textContent = "' . htmlspecialchars($user_branches[0]) . '";
                                });
                            </script>';
                        } else {
                            // Multiple branch access - show selection
                            echo '<option value="">Select Branch</option>';
                            foreach ($user_branches as $branch) {
                                if (!empty($branch)) {
                                    echo '<option value="' . htmlspecialchars($branch) . '">' . htmlspecialchars($branch) . '</option>';
                                }
                            }
                        }
                    } else {
                        // No branch access
                        echo '<option value="">No Branch Access</option>';
                    }
                    ?>
                </select>
            </div>

            <button class="btn-search" onclick="searchSales()">Search</button>
            <button class="btn-print" onclick="printReport()">Print</button>
        </div>

        <!-- Sales History Card -->
        <div class="sales-history-wrapper">
            <div class="sales-history-header">DAILY SALES</div>

            <div class="receipt-area">
                <!-- Printable zone -->
                <div class="doc-card doc-print-zone" id="docCard">

                    <!-- Doc head: logo + SALES title -->
                    <div class="doc-head">
                        <img src="Icon/ZUHAUSE-LOGO.png" alt="MOTOGAM Logo" class="doc-logo">
                        <div class="doc-sales-title">DAILY SALES</div>
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
                        <div class="doc-meta-row" style="justify-content:space-between;">
                            <span class="left"><span class="meta-label">LAST INVOICE NO:</span> <span
                                    id="displayLastInvoice">&nbsp;</span></span>
                            <span class="right"><span class="meta-label">TIME &amp; DATE:</span> <span
                                    id="displayTimeDate">&nbsp;</span></span>
                        </div>
                    </div>

                    <!-- Sales Table -->
                    <table class="doc-table" id="salesTable">
                        <thead>
                            <tr>
                                <th>INVOICE NO.</th>
                                <th>QTY</th>
                                <th style="min-width:160px;">ITEM CODE</th>
                                <th>SRP</th>
                                <th>OLD UNIT AMOUNT</th>
                                <th>ITEM AMOUNT</th>
                                <th>TOTAL AMOUNT</th>
                                <th>COMM</th>
                                <th>UPGRADE</th>
                                <th>STAT</th>
                                <th>SP</th>
                                <th>EN</th>
                                <th style="font-size:12px;">PAYMENT<br>METHOD</th>
                            </tr>
                        </thead>
                        <tbody id="salesTableBody">
                            <tr>
                                <td class="td-no-data" colspan="13">SELECT A FILTER TO DISPLAY THE DATA</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Breakdown Section -->
                    <div id="breakdownSection" style="margin-top: 20px; display: none;">
                        <!-- Breakdown content will be inserted here by JavaScript -->
                    </div>

                    <!-- Footer: signature + page -->
                    <div class="doc-footer">
                        <div class="doc-signature">
                            <div class="doc-signature-line"></div>
                            CASHIER'S SIGNATURE
                        </div>
                        <div class="doc-page" id="displayPage">PAGE 1 OF 1</div>
                    </div>

                </div><!-- /doc-card -->
            </div><!-- /receipt-area -->
        </div><!-- /sales-history-wrapper -->

    </div><!-- /main-content -->

    <script>
        /* --- Sidebar toggle --- */
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

        /* --- Helpers --- */
        function formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        }

        function nowTimeDate() {
            const n = new Date();
            const time = n.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const date = n.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            return time + ' ? ' + date;
        }

        /* --- Search Sales Data --- */
        function searchSales() {
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = dateFrom; // Single date for daily report
            const branchVal = document.getElementById('filterBranch').value;

            // Validate
            if (!dateFrom) {
                alert('Please select a date.');
                return;
            }

            if (!branchVal) {
                alert('Please select a branch before searching.');
                return;
            }

            // Update meta fields
            document.getElementById('displayBranch').textContent = branchVal;

            // Display date (single date for daily report)
            document.getElementById('displayDateRange').textContent = formatDate(dateFrom);

            document.getElementById('displayTimeDate').textContent = nowTimeDate();

            // Show loading
            const tbody = document.getElementById('salesTableBody');
            tbody.innerHTML = '<tr><td class="td-no-data" colspan="13">Loading...</td></tr>';

            console.log('Searching for:', { dateFrom: dateFrom, dateTo: dateTo, branch: branchVal });

            // Fetch both sales data and unclaimed freebies in parallel
            const salesPromise = fetch('fetch_sales_report.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ date_from: dateFrom, date_to: dateTo, branch: branchVal })
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.text();
                })
                .then(text => {
                    console.log('Raw response:', text);
                    return JSON.parse(text);
                })
                .catch(error => {
                    console.error('Sales fetch error:', error);
                    return { status: 'error', message: error.message };
                });

            const freebiesPromise = fetch(`get_unclaimed_freebies_report.php?date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}&branch=${encodeURIComponent(branchVal)}`)
                .then(response => response.json())
                .catch(error => {
                    console.error('Freebies fetch error:', error);
                    return { success: false };
                });

            Promise.all([salesPromise, freebiesPromise]).then(([salesData, freebiesData]) => {
                // Handle sales data
                if (salesData.status === 'success') {
                    displaySalesData(salesData.sales, salesData.lastInvoice);
                    console.log('Found', salesData.count, 'sales entries');
                } else {
                    const tbody = document.getElementById('salesTableBody');
                    tbody.innerHTML = '<tr><td class="td-no-data" colspan="13">Error: ' + (salesData.message || 'Unknown error') + '</td></tr>';
                    generateBreakdown([]);
                }

                // Handle unclaimed freebies data (always after breakdown DOM is ready)
                if (freebiesData && freebiesData.success) {
                    currentUnclaimedFreebiesData = freebiesData;
                    displayUnclaimedFreebies(freebiesData);
                } else {
                    currentUnclaimedFreebiesData = null;
                    const container = document.getElementById('unclaimedFreebiesBreakdownBox');
                    if (container) container.style.display = 'none';
                }
            });
        }

        /* --- Payment Method Formatter Helper --- */
        function formatPaymentMethodName(paymentData) {
            if (!paymentData) return 'N/A';
            if (typeof paymentData === 'string') {
                try {
                    paymentData = JSON.parse(paymentData);
                } catch (e) {
                    return paymentData;
                }
            }
            if (!paymentData || typeof paymentData !== 'object') return 'N/A';

            let pt = (paymentData.payment_type || '').trim();

            // Multiple payments in array (e.g. from preorder / claim preorder)
            if (pt === 'multiple' && Array.isArray(paymentData.payments) && paymentData.payments.length > 0) {
                const methods = paymentData.payments.map(p => formatPaymentMethodName(p)).filter(Boolean);
                const unique = [...new Set(methods)];
                if (unique.length === 1) return unique[0];
                if (unique.length === 2) return unique[0] + ' & ' + unique[1];
                if (unique.length > 2) return unique.slice(0, -1).join(', ') + ', & ' + unique[unique.length - 1];
            }

            // Payment Partner
            if (paymentData.payment_partner || pt === 'payment_partners' || pt.toLowerCase() === 'payment_partners') {
                const partnerMap = {
                    'partner1': 'Skyro',
                    'partner2': 'Home Credit',
                    'partner5': 'Salmon',
                    'partner6': 'Samsung Finances',
                    'partner7': 'Payjoy',
                    'partner8': 'Billease',
                    'partner9': 'Paymongo',
                    'partner10': 'Skyro'
                };
                const pp = paymentData.payment_partner || '';
                return partnerMap[pp.toLowerCase()] || pp || 'Home Credit';
            }

            // Single specific payment types (handles lowercase preorder values & uppercase POS values)
            const lowerPt = pt.toLowerCase();
            if (lowerPt === 'cash') {
                return 'Cash';
            }
            if (lowerPt === 'ewallet' || lowerPt === 'e-wallet') {
                return paymentData.ewallet_type || paymentData['E-Wallet-Text'] || paymentData['E-Wallet'] || 'E-Wallet';
            }
            if (lowerPt === 'online_banking' || lowerPt === 'online banking') {
                return paymentData.bank_name || paymentData['Bank-Text'] || paymentData['Bank'] || 'Online Banking';
            }
            if (lowerPt === 'credit_card' || lowerPt === 'credit card') {
                return 'Credit Card';
            }
            if (lowerPt === 'debit_card' || lowerPt === 'debit card') {
                return 'Debit Card';
            }
            if (lowerPt === 'qr_ph' || lowerPt === 'qr ph') {
                return 'QR PH';
            }
            if (lowerPt === 'starpay_qr' || lowerPt === 'starpay qr') {
                return 'Starpay QR';
            }

            // Substitute specific names for split strings like "E-Wallet + Cash" or "Online Banking + Cash"
            if (paymentData['E-Wallet-Text'] && pt.includes('E-Wallet')) {
                pt = pt.replace(/E-Wallet/g, paymentData['E-Wallet-Text']);
            }
            if (paymentData['Bank-Text'] && pt.includes('Online Banking')) {
                pt = pt.replace(/Online Banking/g, paymentData['Bank-Text']);
            }
            if (paymentData['payment_partner'] && pt.includes('payment_partners')) {
                pt = pt.replace(/payment_partners/g, paymentData['payment_partner']);
            }

            // Format separator: replace ' + ' with ' & '
            const pmParts = pt.split(/\s*[+&]\s*/).map(s => s.trim()).filter(s => s);
            if (pmParts.length === 1) return pmParts[0];
            if (pmParts.length === 2) return pmParts[0] + ' & ' + pmParts[1];
            if (pmParts.length > 2) return pmParts.slice(0, -1).join(', ') + ', & ' + pmParts[pmParts.length - 1];

            return pt || 'N/A';
        }

        /* --- Per-item net total (SRP − item voucher − item token − proportional discount) ---
         * Matches the payment breakdown: voucher/token stay on the item that owns them.
         */
        function getItemNetTotal(item, sale, allItems) {
            const items = allItems || (sale && sale.items) || [];
            const itemSrp = (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 1);
            const itemVoucher = parseFloat(item.voucher_amount) || 0;
            const itemToken = parseFloat(item.token_amount) || 0;
            const invoiceDiscount = parseFloat(sale.discount) || 0;
            const invoiceVoucher = parseFloat(sale.voucher_amount || sale.voucher) || 0;
            const invoiceToken = parseFloat(sale.token) || 0;
            const totalSrp = items.reduce((sum, it) => sum + ((parseFloat(it.price) || 0) * (parseInt(it.quantity) || 1)), 0);
            const itemDiscount = (totalSrp > 0 && invoiceDiscount > 0)
                ? Math.round(invoiceDiscount * (itemSrp / totalSrp))
                : 0;
            const itemNet = itemSrp - itemVoucher - itemToken - itemDiscount;
            const expectedTotal = totalSrp - invoiceVoucher - invoiceToken - invoiceDiscount;
            const saleTotal = parseFloat(sale.actual_total_amount || sale.total_amount) || 0;

            // Card/QR surcharge: scale nets so they still sum to sale total
            if (expectedTotal > 0 && saleTotal > 0 && Math.abs(saleTotal - expectedTotal) > 0.02) {
                return Math.round(saleTotal * (itemNet / expectedTotal));
            }
            return Math.round(itemNet);
        }

        /* --- Per-Item Amount Helper ---
         * For mixed payment types like "Home Credit + Cash" with unit_payment_map,
         * loan items get Loan Balance + DP, cash items get the Amount field.
         * Returns null if amount cannot be determined (caller should fall back to proportional).
         */
        function getPerItemAmountFromMap(item, pd, uMap) {
            if (!pd || !uMap || Object.keys(uMap).length === 0) return null;

            const mapMethods = new Set(Object.values(uMap).map(m => String(m).toLowerCase().trim()).filter(Boolean));
            // If ALL items in uMap are mapped to the same single method (e.g. all "Cash", all "Credit Card"),
            // there is no per-item split across multiple methods; return null so standard proportional/SRP logic applies!
            if (mapMethods.size <= 1) return null;

            const imei = (item.imei || '').trim().toUpperCase();
            const desc = (item.item_description || item.item_code || '').trim().toUpperCase();
            let matchedMethod = null;

            if (imei) {
                for (const [key, method] of Object.entries(uMap)) {
                    if (key.toUpperCase().includes(imei)) { matchedMethod = String(method); break; }
                }
            }
            if (!matchedMethod && desc) {
                for (const [key, method] of Object.entries(uMap)) {
                    if (key.toUpperCase().startsWith(desc)) { matchedMethod = String(method); break; }
                }
            }

            const isLoanMethod = (m) => {
                const ml = String(m || '').toLowerCase();
                return ml.includes('home credit') || ml.includes('salmon') || ml.includes('sumisho') ||
                    ml.includes('payjoy') || ml.includes('billease') || ml.includes('paymongo') ||
                    ml.includes('skyro') || ml.includes('samsung') || ml.includes('cebu') ||
                    ml.includes('partner') || ml.includes('makati');
            };
            const allMapMethodsAreLoan = Array.from(mapMethods).every(m => isLoanMethod(m));

            if (!matchedMethod) {
                // Item not in map — assume it's the Cash portion for mixed loan+cash
                const pt = (pd.payment_type || '').toLowerCase();
                const hasCashInType = pt.includes('cash');
                const hasLoanInType = isLoanMethod(pt) || (pd['Loan Balance'] !== undefined) || (pd.totalLoanAmount !== undefined);
                if (hasCashInType && hasLoanInType && allMapMethodsAreLoan) {
                    const cashAmtRaw = pd['Amount'] || pd['cash_amount'] || '';
                    const cashAmt = parseFloat(String(cashAmtRaw).replace(/,/g, '').trim());
                    if (!isNaN(cashAmt) && cashAmt > 0) return cashAmt * (parseInt(item.quantity) || 1);
                }
                return null;
            }

            const matchedKey = String(matchedMethod).toLowerCase().trim();
            const countWithSameMethod = Object.values(uMap).filter(m => String(m).toLowerCase().trim() === matchedKey).length;
            if (countWithSameMethod > 1) {
                // Multiple items share this method in a split sale; fallback to proportional distribution
                return null;
            }

            if (isLoanMethod(matchedMethod)) {
                // Loan item: Loan Balance + all DP amounts
                const lbRaw = pd['Loan Balance'] || pd['loan_balance'] || pd['totalLoanAmount'] || pd['total_loan_amount'] || 0;
                let loanTotal = parseFloat(String(lbRaw).replace(/,/g, ''));
                const dpKeys = ['cash_down_payment_amount', 'cash_dp_amount', 'gcash_down_payment_amount',
                    'gcash_dp_amount', 'maya_down_payment_amount', 'maya_dp_amount'];
                for (const dk of dpKeys) {
                    if (pd[dk]) {
                        const v = parseFloat(String(pd[dk]).replace(/,/g, '').split('|')[0].trim());
                        if (!isNaN(v) && v > 0) loanTotal += v;
                    }
                }
                if (!isNaN(loanTotal) && loanTotal > 0) return loanTotal * (parseInt(item.quantity) || 1);
                return null;
            } else if (matchedKey === 'cash') {
                const cashAmtRaw = pd['Amount'] || pd['cash_amount'] || '';
                const cashAmt = parseFloat(String(cashAmtRaw).replace(/,/g, '').trim());
                if (!isNaN(cashAmt) && cashAmt > 0) return cashAmt * (parseInt(item.quantity) || 1);
                return null;
            }

            return null;
        }

        /* --- Display Sales Data --- */
        function displaySalesData(salesData, lastInvoice) {
            currentSalesData = salesData || [];
            const tbody = document.getElementById('salesTableBody');

            console.log('Displaying sales data:', salesData);

            // Update last invoice
            document.getElementById('displayLastInvoice').textContent = lastInvoice || '? (no data)';

            if (!salesData || salesData.length === 0) {
                tbody.innerHTML = '<tr><td class="td-no-data" colspan="13">NO DATA</td></tr>';
                document.getElementById('displayPage').textContent = 'PAGE 1 OF 1';
                generateBreakdown([]);
                return;
            }

            // Helper function to abbreviate names
            function abbreviateName(fullName) {
                if (!fullName || fullName.trim() === '') return '';

                const words = fullName.trim().split(/\s+/);
                let abbreviated = '';

                for (let i = 0; i < words.length; i++) {
                    if (words[i].length > 0) {
                        abbreviated += words[i].charAt(0).toUpperCase() + '.';
                    }
                }

                // Add extra dots to match the format "L.A.."
                if (abbreviated.length > 0) {
                    abbreviated += '.';
                }

                return abbreviated;
            }

            let tableHTML = '';
            salesData.forEach((sale, saleIndex) => {
                console.log('Processing sale:', sale);

                const isVoided = (sale.status === 'voided');
                const isRefunded = (sale.display_status === 'refunded');

                // Parse payment data to get payment method
                let paymentMethod = '';
                let unitPaymentMap = {}; // per-unit payment lookup { "DESC (IMEI)" -> "PaymentName" }
                let parsedSalePaymentData = null; // expose parsed payment_data for amount calculations
                if (sale.payment_data) {
                    try {
                        const paymentData = JSON.parse(sale.payment_data);
                        parsedSalePaymentData = paymentData;
                        paymentMethod = formatPaymentMethodName(paymentData);

                        // Debug: log unit_payment_map for invoice 0178
                        if (sale.invoice_no === '0178') {
                            console.log('Invoice 0178 payment_data:', paymentData);
                            console.log('Invoice 0178 unit_payment_map:', paymentData.unit_payment_map);
                        }

                        // Load per-unit payment map if available (split payment with unit selector)
                        if (paymentData.unit_payment_map && typeof paymentData.unit_payment_map === 'object') {
                            unitPaymentMap = paymentData.unit_payment_map;
                        }
                    } catch (e) {
                        console.error('Error parsing payment data:', e);
                        paymentMethod = 'Unknown';
                    }
                }

                // (getPerItemAmountFromMap is defined globally above displaySalesData)

                // Parse upgrade payment method (used for is_upgrade_item rows)
                let upgradePaymentMethod = '';
                if (sale.upgrade_payment_data) {
                    try {
                        const upgPd = JSON.parse(sale.upgrade_payment_data);
                        let upm = upgPd.payment_type || '';
                        if (upgPd['E-Wallet-Text'] && upm.includes('E-Wallet')) upm = upm.replace('E-Wallet', upgPd['E-Wallet-Text']);
                        if (upgPd['Bank-Text'] && upm.includes('Online Banking')) upm = upm.replace('Online Banking', upgPd['Bank-Text']);
                        if (upgPd['payment_partner'] && upm.includes('payment_partners')) upm = upm.replace('payment_partners', upgPd['payment_partner']);
                        const uParts = upm.split(' + ').map(s => s.trim()).filter(s => s);
                        if (uParts.length === 1) upgradePaymentMethod = uParts[0];
                        else if (uParts.length === 2) upgradePaymentMethod = uParts[0] + ' & ' + uParts[1];
                        else upgradePaymentMethod = uParts.slice(0, -1).join(', ') + ', & ' + uParts[uParts.length - 1];
                    } catch (e) { upgradePaymentMethod = ''; }
                }

                // Helper: resolve payment method for a specific item using unit_payment_map.
                // The map keys are "DESCRIPTION (IMEI)". For split payments on a single unit,
                // the map only stores the LAST section that checked that unit, losing earlier
                // methods (e.g. STO ninio de cebu gets overwritten by Cash). Detect this by
                // comparing how many distinct methods are in the full paymentMethod string vs
                // how many unique values are in the map — if paymentMethod has more parts, the
                // unit is shared across multiple methods and we return the full combined string.
                function getItemPaymentMethod(item) {
                    // Upgraded items always use the upgrade's own payment method
                    if (item.is_upgrade_item == 1 && upgradePaymentMethod) return upgradePaymentMethod;

                    if (Object.keys(unitPaymentMap).length === 0) return paymentMethod;

                    // Count distinct payment parts in the full method string
                    const fullParts = paymentMethod.split(/\s*[&,]\s*|\s*&\s*/).map(s => s.trim()).filter(s => s);
                    const uniqueMapMethods = new Set(Object.values(unitPaymentMap));

                    // If the full payment has more distinct methods than what's in the map,
                    // this unit is shared across methods — return the full combined label.
                    if (fullParts.length > uniqueMapMethods.size) return paymentMethod;

                    const imei = (item.imei || '').trim().toUpperCase();
                    const desc = (item.item_description || item.item_code || '').trim().toUpperCase();

                    // Try to construct the full key format: "DESCRIPTION (IMEI)"
                    const fullKey = imei ? `${desc} (${imei})` : desc;

                    // First, try exact match with the full key
                    for (const [key, method] of Object.entries(unitPaymentMap)) {
                        const keyUpper = key.toUpperCase();
                        if (fullKey === keyUpper) return method;
                    }

                    // Second, match by IMEI if present (more specific)
                    if (imei) {
                        for (const [key, method] of Object.entries(unitPaymentMap)) {
                            const keyUpper = key.toUpperCase();
                            // Extract IMEI from the key format "DESCRIPTION (IMEI)"
                            const imeiMatch = keyUpper.match(/\(([^)]+)\)$/);
                            if (imeiMatch && imeiMatch[1] === imei) return method;
                        }
                    }

                    // Last resort: match by description (least specific, may not work for similar items)
                    for (const [key, method] of Object.entries(unitPaymentMap)) {
                        const keyUpper = key.toUpperCase();
                        if (desc && keyUpper.startsWith(desc)) return method;
                    }

                    // No match found — return the full method
                    return paymentMethod;
                }

                // Abbreviate names for SP and EN columns
                const assistedByAbbr = abbreviateName(sale.assisted_by);
                const encoderAbbr = abbreviateName(sale.encoder);

                const rowStyle = (isVoided || isRefunded) ? ' style="color:#d32f2f;"' : '';
                const isTradeIn = (sale.page_type === 'salestrade-in' && sale.upgrade !== 'UPGD');
                const hasPromoItemInSale = (sale.items && sale.items.some(i => i.is_promo_item == 1));
                const isPromoEntry = (sale.page_type === 'promosentry' || hasPromoItemInSale || (sale.promo_id && parseInt(sale.promo_id) > 0));
                let statCell = '<td></td>';
                if (isVoided) {
                    statCell = '<td style="color:#d32f2f;font-weight:700;">VD</td>';
                } else if (isRefunded) {
                    statCell = '<td style="color:#d32f2f;font-weight:700;">RF</td>';
                } else if (isTradeIn) {
                    statCell = '<td style="color:#e65100;font-weight:700;">TRD</td>';
                } else if (isPromoEntry) {
                    statCell = '<td style="color:#1a7f1a;font-weight:700;">PROMO</td>';
                }

                // Process items for this sale
                if (sale.items && sale.items.length > 0) {
                    sale.items.forEach((item, index) => {
                        const itemRefunded = (item.is_refunded == 1);
                        const itemVoided = isVoided;
                        const itemIsPromo = (item.is_promo_item == 1) || (sale.page_type === 'promosentry' && !hasPromoItemInSale) || (isPromoEntry && !hasPromoItemInSale && (sale.promo_id && parseInt(sale.promo_id) > 0));
                        const oldUnitAmount = parseFloat(sale.old_unit_amount) || 0;
                        const isNewUpgradeSale = sale.upgrade === 'UPGD' && sale.original_invoice_no && sale.original_invoice_no.trim() !== '';
                        const itemIsUpgraded = (isNewUpgradeSale || item.is_upgrade_item == 1);
                        const itemIsOldUpgraded = (item.is_old_upgrade_item == 1);
                        const amtStyle = (itemVoided || itemRefunded) ? ' style="color:#d32f2f;"' : '';
                        let itemStatCell = '<td></td>';
                        if (itemVoided) {
                            itemStatCell = '<td style="color:#d32f2f;font-weight:700;">VD</td>';
                        } else if (itemRefunded) {
                            itemStatCell = '<td style="color:#d32f2f;font-weight:700;">RF</td>';
                        } else if (isTradeIn) {
                            itemStatCell = '<td style="color:#e65100;font-weight:700;">TRD</td>';
                        } else if (itemIsPromo) {
                            itemStatCell = '<td style="color:#1a7f1a;font-weight:700;">PROMO</td>';
                        }

                        const itemRowStyle = (itemVoided || itemRefunded) ? ' style="color:#d32f2f;"' : '';
                        let itemAmtDisplay;
                        let totalAmtDisplay;
                        let oldUnitDisplay = '0.00';

                        if (isNewUpgradeSale) {
                            // New upgrade invoice (0122): OLD UNIT = trade-in value, ITEM = cash paid, TOTAL = cash paid
                            // Cash paid = payment_data.Amount (most reliable) OR total_amount - discount
                            let cashPaid = 0;
                            if (parsedSalePaymentData) {
                                const amtRaw = parsedSalePaymentData['Amount'] || parsedSalePaymentData['amount'] || parsedSalePaymentData['Total'] || '';
                                const parsed = parseFloat(String(amtRaw).replace(/,/g, '').trim());
                                if (!isNaN(parsed) && parsed > 0) cashPaid = parsed;
                            }
                            if (cashPaid <= 0) {
                                const saleTotal = parseFloat(sale.total_amount) || 0;
                                const discount = parseFloat(sale.discount) || 0;
                                cashPaid = Math.max(0, saleTotal - discount);
                            }
                            oldUnitDisplay = oldUnitAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            itemAmtDisplay = cashPaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            totalAmtDisplay = cashPaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        } else {
                            oldUnitDisplay = '0.00';
                            const isPreorder = sale.id && (String(sale.id).startsWith('PO-') || (sale.remarks && sale.remarks.includes('PRE-ORDER')));
                            if (isPreorder) {
                                if (sale.items.length === 1) {
                                    itemAmtDisplay = parseFloat(sale.actual_total_amount || sale.total_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    totalAmtDisplay = parseFloat(sale.actual_total_amount || sale.total_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                } else {
                                    // Calculate total SRP of all items in the preorder
                                    const totalSrp = sale.items.reduce((sum, it) => sum + (parseFloat(it.price) * parseInt(it.quantity || 1)), 0);
                                    const itemSrpSubtotal = parseFloat(item.price) * parseInt(item.quantity || 1);
                                    const proportion = totalSrp > 0 ? (itemSrpSubtotal / totalSrp) : 0;
                                    const itemPaid = parseFloat(sale.actual_total_amount || sale.total_amount) * proportion;
                                    itemAmtDisplay = (itemPaid / parseInt(item.quantity || 1)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    totalAmtDisplay = itemPaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                            } else {
                                itemAmtDisplay = parseFloat(item.price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                // Use the sale's actual_total_amount (includes loan amount) or total_amount for accurate calculation
                                // For single item: use actual_total_amount; for multiple items: proportional distribution
                                const saleTotal = parseFloat(sale.actual_total_amount || sale.total_amount);
                                if (sale.items.length === 1) {
                                    // For single item, ITEM AMOUNT = actual_total_amount / quantity
                                    const itemQty = parseInt(item.quantity) || 1;
                                    const itemAmount = Math.round(saleTotal / itemQty);
                                    itemAmtDisplay = itemAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    totalAmtDisplay = Math.round(saleTotal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                } else {
                                    // For multiple items: first try per-method amount from payment_data,
                                    // then fall back to per-item voucher/token net (not SRP pro-rate)
                                    const perItemAmt = getPerItemAmountFromMap(item, parsedSalePaymentData, unitPaymentMap);
                                    const itemQty = parseInt(item.quantity) || 1;
                                    if (perItemAmt !== null) {
                                        const itemTotal = Math.round(perItemAmt);
                                        const itemAmount = Math.round(perItemAmt / itemQty);
                                        itemAmtDisplay = itemAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                        totalAmtDisplay = itemTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    } else {
                                        const itemTotal = getItemNetTotal(item, sale, sale.items);
                                        const itemAmount = Math.round(itemTotal / itemQty);
                                        itemAmtDisplay = itemAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                        totalAmtDisplay = itemTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }
                                }
                            }
                        }

                        const upgradeCell = (itemIsUpgraded || itemIsOldUpgraded || (sale.items.length === 1 && sale.upgrade === 'UPGD'))
                            ? '<td style="font-weight:700;">UPGD</td>'
                            : '<td></td>';

                        // Invoice number cell with rowspan - only show on first item
                        let invoiceCell = '';
                        if (index === 0) {
                            const itemPaymentMethod = getItemPaymentMethod(item);
                            const itemImei = (item.imei || '').trim();
                            const escapedPaymentMethod = itemPaymentMethod.replace(/'/g, "\\'").replace(/"/g, '\\"');
                            const escapedImei = itemImei.replace(/'/g, "\\'").replace(/"/g, '\\"');
                            const rowspanAttr = sale.items.length > 1 ? ` rowspan="${sale.items.length}"` : '';
                            invoiceCell = isVoided
                                ? `<td${rowspanAttr} style="color:#d32f2f; vertical-align: middle;">${sale.invoice_no}</td>`
                                : `<td${rowspanAttr} style="vertical-align: middle;"><a href="#" onclick="viewInvoiceDetails('${sale.invoice_no}', null, null); return false;" style="color: #0066cc; text-decoration: underline; cursor: pointer;">${sale.invoice_no}</a></td>`;
                        }

                        tableHTML += `
                            <tr${itemRowStyle}>
                                ${invoiceCell}
                                <td>${item.quantity}</td>
                                <td class="td-text-left">${item.item_code || item.item_description}</td>
                                <td class="td-number"${amtStyle}>${parseFloat(item.price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td class="td-number"${amtStyle}>${oldUnitDisplay}</td>
                                <td class="td-number"${amtStyle}>${itemAmtDisplay}</td>
                                <td class="td-number"${amtStyle}>${totalAmtDisplay}</td>
                                <td class="td-number">${(parseFloat(sale.commission) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                ${upgradeCell}
                                ${itemStatCell}
                                <td class="td-text-left">${assistedByAbbr}</td>
                                <td class="td-text-left">${encoderAbbr}</td>
                                <td class="td-text-left">${getItemPaymentMethod(item)}</td>
                            </tr>
                        `;
                    });
                } else {
                    // No items, show basic sale info
                    const amtStyle = isVoided ? ' style="color:#d32f2f;"' : '';
                    const oldUnitAmount = parseFloat(sale.old_unit_amount) || 0;
                    const saleTotal = parseFloat(sale.actual_total_amount || sale.total_amount) || 0;
                    const upgdTotalAmount = sale.upgrade === 'UPGD'
                        ? (saleTotal + oldUnitAmount)
                        : saleTotal;
                    const oldUnitDisplay = sale.upgrade === 'UPGD'
                        ? oldUnitAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                        : '0.00';
                    const upgradeCell = sale.upgrade ? `<td style="font-weight:700;">${sale.upgrade}</td>` : '<td></td>';

                    // Invoice number cell - only add hyperlink if not voided, show red text if voided
                    const escapedPaymentMethod = paymentMethod.replace(/'/g, "\\'").replace(/"/g, '\\"');
                    const invoiceCell = isVoided
                        ? `<td style="color:#d32f2f;">${sale.invoice_no}</td>`
                        : `<td><a href="#" onclick="viewInvoiceDetails('${sale.invoice_no}'); return false;" style="color: #0066cc; text-decoration: underline; cursor: pointer;">${sale.invoice_no}</a></td>`;

                    tableHTML += `
                        <tr${rowStyle}>
                            ${invoiceCell}
                            <td>${sale.total_qty || 0}</td>
                            <td class="td-text-left">No Items</td>
                            <td class="td-number"${amtStyle}>0.00</td>
                            <td class="td-number"${amtStyle}>${oldUnitDisplay}</td>
                            <td class="td-number"${amtStyle}>0.00</td>
                            <td class="td-number"${amtStyle}>${upgdTotalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                            <td class="td-number">${(parseFloat(sale.commission) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                            ${upgradeCell}
                            ${statCell}
                            <td class="td-text-left">${assistedByAbbr}</td>
                            <td class="td-text-left">${encoderAbbr}</td>
                            <td class="td-text-left">${paymentMethod}</td>
                        </tr>
                    `;
                }
            });

            console.log('Generated table HTML length:', tableHTML.length);
            tbody.innerHTML = tableHTML;
            document.getElementById('displayPage').textContent = 'PAGE 1 OF 1';

            // Generate breakdown
            generateBreakdown(salesData);
        }

        /* --- Generate Breakdown --- */
        function generateBreakdown(salesData) {
            const breakdownSection = document.getElementById('breakdownSection');

            if (!salesData || salesData.length === 0) {
                // Still create the layout so unclaimedFreebiesBreakdownBox exists in DOM
                breakdownSection.innerHTML = `
                    <div style="display: flex; justify-content: flex-end; align-items: flex-start; width: 100%;">
                        <div style="display: flex; gap: 8px; flex: 0 0 auto;">
                            <div id="unclaimedFreebiesBreakdownBox" style="display: none; flex: 0 0 auto;">
                                <!-- Unclaimed freebies box will be inserted here -->
                            </div>
                        </div>
                    </div>
                `;
                breakdownSection.style.display = 'block';
                return;
            }

            // Group sales by encoder (full name)
            const groupedByEncoder = {};
            let totalUnits = 0;
            let grandTotalAmount = 0;
            let totalNonCashPayment = 0;
            let totalCommissions = 0;
            let totalUpgrade = 0;
            let totalOldUnit = 0;
            let totalCash = 0;
            let totalVoid = 0;
            let totalRefund = 0;

            const commissionBreakdown = {};
            const nonCashBreakdown = {};

            salesData.forEach(sale => {
                const encoderName = sale.encoder || 'Unknown';
                const isVoided = (sale.status === 'voided');
                const isRefunded = (sale.display_status === 'refunded');
                const rawSaleAmount = parseFloat(sale.actual_total_amount || sale.total_amount) || 0;
                const oldUnitAmount = parseFloat(sale.old_unit_amount) || 0;
                const discountAmount = parseFloat(sale.discount) || 0;

                // Only NEW upgrade invoices (those that have original_invoice_no set) use cash paid, not SRP
                const isNewUpgradeSale = sale.upgrade === 'UPGD' && sale.original_invoice_no && sale.original_invoice_no.trim() !== '';

                let saleAmount = rawSaleAmount;
                if (isNewUpgradeSale) {
                    // Extract cash paid from payment_data.Amount (most reliable)
                    let cashPaid = 0;
                    if (sale.payment_data) {
                        try {
                            const pd = JSON.parse(sale.payment_data);
                            const amtRaw = pd['Amount'] || pd['amount'] || '';
                            const parsed = parseFloat(String(amtRaw).replace(/,/g, '').trim());
                            if (!isNaN(parsed) && parsed > 0) cashPaid = parsed;
                        } catch (e) { }
                    }
                    if (cashPaid > 0) {
                        saleAmount = cashPaid;
                    } else {
                        saleAmount = Math.max(0, rawSaleAmount - discountAmount);
                    }
                }

                const saleQty = parseInt(sale.total_qty) || 0;
                let saleDisplayedTotal = 0;

                // Match summary totals with what is visibly shown in table rows.
                if (sale.items && sale.items.length > 0) {
                    let shownUpgradeTotals = false;
                    sale.items.forEach(item => {
                        if (!shownUpgradeTotals) {
                            saleDisplayedTotal += saleAmount;
                            shownUpgradeTotals = true;
                        }
                    });
                } else {
                    saleDisplayedTotal = saleAmount;
                }

                if (!groupedByEncoder[encoderName]) {
                    groupedByEncoder[encoderName] = {
                        fullName: encoderName,
                        quantity: 0,
                        group: 'ITEM', // Default group
                        totalAmount: 0
                    };
                }

                // Include in encoder breakdown (exclude voided sales)
                if (!isVoided) {
                    groupedByEncoder[encoderName].quantity += saleQty;
                    groupedByEncoder[encoderName].totalAmount += saleDisplayedTotal;
                }

                // Include in totals (exclude voided sales)
                if (!isVoided) {
                    totalUnits += saleQty;
                    grandTotalAmount += saleDisplayedTotal;
                }

                // Parse payment data for cash calculation (include voided and refunded sales)
                if (sale.payment_data) {
                    try {
                        const paymentData = JSON.parse(sale.payment_data);
                        const rawPaymentType = paymentData.payment_type || '';
                        const paymentTypeLower = rawPaymentType.toLowerCase();

                        const partnerMap = {
                            'partner1': 'Skyro',
                            'partner2': 'Home Credit',
                            'partner3': 'Sumisho',
                            'partner4': 'AEON Credit',
                            'partner5': 'Salmon',
                            'partner6': 'Samsung Finances',
                            'partner7': 'Payjoy',
                            'partner8': 'Billease',
                            'partner9': 'Paymongo',
                            'partner10': 'Skyro',
                            'partner11': 'Fundline',
                            'partner12': 'Flexi Finance'
                        };
                        function normalizePartnerName(p) {
                            if (!p) return '';
                            const pl = String(p).toLowerCase().trim();
                            return partnerMap[pl] || p;
                        }

                        if (!rawPaymentType || paymentTypeLower === 'cash') {
                            // Pure cash
                            totalCash += saleAmount;
                        } else if (paymentTypeLower === 'multiple' && Array.isArray(paymentData.payments) && paymentData.payments.length > 0) {
                            // Multiple payment methods array (e.g. preorder/claimed preorders)
                            paymentData.payments.forEach(p => {
                                const pType = (p.payment_type || '').toLowerCase();
                                const pAmt = parseFloat(String(p.amount || 0).replace(/,/g, '')) || 0;
                                if (pAmt <= 0) return;
                                if (pType === 'cash') {
                                    totalCash += pAmt;
                                } else {
                                    let pLabel = p.payment_method || pType;
                                    if (pType === 'payment_partners' || p.payment_partner) {
                                        pLabel = normalizePartnerName(p.payment_partner || pLabel);
                                    } else if (pType === 'ewallet') {
                                        pLabel = p.ewallet_type || 'E-Wallet';
                                    } else if (pType === 'online_banking') {
                                        pLabel = p.bank_name || 'Online Banking';
                                    }
                                    const labelUpper = pLabel.toUpperCase();
                                    if (!nonCashBreakdown[labelUpper]) nonCashBreakdown[labelUpper] = 0;
                                    nonCashBreakdown[labelUpper] += pAmt;
                                    totalNonCashPayment += pAmt;
                                }
                            });
                        } else {
                            // Build display type with real names
                            let specificType = rawPaymentType;
                            if (paymentData['E-Wallet-Text'] && specificType.includes('E-Wallet')) {
                                specificType = specificType.replace('E-Wallet', paymentData['E-Wallet-Text']);
                            }
                            if (paymentData['Bank-Text'] && specificType.includes('Online Banking')) {
                                specificType = specificType.replace('Online Banking', paymentData['Bank-Text']);
                            }
                            if (paymentData['payment_partner'] || specificType.toLowerCase().includes('payment_partners') || specificType.toLowerCase().startsWith('partner')) {
                                const mapped = normalizePartnerName(paymentData['payment_partner'] || specificType);
                                specificType = specificType.replace(/payment_partners/gi, mapped).replace(/partner\d+/gi, mapped);
                                if (!specificType || specificType === rawPaymentType) specificType = mapped;
                            }

                            // Check if this is a split payment containing cash
                            const methodParts = rawPaymentType.split(' + ').map(s => s.trim());
                            const hasCash = methodParts.some(m => m.toLowerCase() === 'cash');

                            // --- Helper: resolve display label for a raw method string ---
                            function resolveMethodLabel(m) {
                                if (!m) return '';
                                if (m === 'E-Wallet' && paymentData['E-Wallet-Text']) return paymentData['E-Wallet-Text'];
                                if (m === 'Online Banking' && paymentData['Bank-Text']) return paymentData['Bank-Text'];
                                if (m.toLowerCase().includes('payment_partners') || m.toLowerCase().startsWith('partner')) {
                                    return normalizePartnerName(paymentData['payment_partner'] || m);
                                }
                                return normalizePartnerName(m);
                            }

                            // --- Try unit_payment_map first (most accurate per-unit amounts) ---
                            const unitMap = (paymentData.unit_payment_map && typeof paymentData.unit_payment_map === 'object')
                                ? paymentData.unit_payment_map : null;

                            if (unitMap && sale.items && sale.items.length > 0) {
                                // Sum amounts per payment method using actual transaction amounts (not SRP)
                                const methodAmounts = {}; // { "STO ninio de cebu": 125900, "Sumisho": 150000 }

                                // Use actual_total_amount (loan total) instead of item prices
                                const saleActualTotal = parseFloat(sale.actual_total_amount || sale.total_amount) || 0;
                                const totalSrp = sale.items.reduce((sum, it) => sum + ((parseFloat(it.price) || 0) * (parseInt(it.quantity) || 1)), 0);

                                sale.items.forEach(item => {
                                    const imei = (item.imei || '').trim().toUpperCase();
                                    const desc = (item.item_description || '').trim().toUpperCase();
                                    let matchedMethod = null;

                                    // Pass 1: Try IMEI match against all unitMap keys first
                                    if (imei) {
                                        for (const [key, method] of Object.entries(unitMap)) {
                                            const keyUpper = key.toUpperCase();
                                            if (keyUpper.includes(imei)) { matchedMethod = method; break; }
                                        }
                                    }

                                    // Pass 2: If no IMEI match found, try description match
                                    if (!matchedMethod && desc) {
                                        for (const [key, method] of Object.entries(unitMap)) {
                                            const keyUpper = key.toUpperCase();
                                            if (keyUpper.startsWith(desc)) { matchedMethod = method; break; }
                                        }
                                    }

                                    if (!matchedMethod) matchedMethod = resolveMethodLabel(methodParts[0]);

                                    // Try per-method amount first; fall back to per-item voucher/token net
                                    let itemAmt;
                                    const perItemAmtBreakdown = getPerItemAmountFromMap(item, paymentData, unitMap);
                                    if (perItemAmtBreakdown !== null) {
                                        itemAmt = Math.round(perItemAmtBreakdown);
                                    } else {
                                        itemAmt = getItemNetTotal(item, sale, sale.items);
                                    }

                                    const resolvedMethod = resolveMethodLabel(matchedMethod);
                                    if (!methodAmounts[resolvedMethod]) methodAmounts[resolvedMethod] = 0;
                                    methodAmounts[resolvedMethod] += itemAmt;
                                });

                                // Apply to totals
                                for (const [method, amt] of Object.entries(methodAmounts)) {
                                    if (method.toLowerCase() === 'cash') {
                                        totalCash += amt;
                                    } else {
                                        totalNonCashPayment += amt;
                                        const labelUpper = resolveMethodLabel(method).toUpperCase();
                                        if (!nonCashBreakdown[labelUpper]) nonCashBreakdown[labelUpper] = 0;
                                        nonCashBreakdown[labelUpper] += amt;
                                    }
                                }

                            } else if (hasCash && methodParts.length > 1) {
                                // Split payment with cash: parse pipe-separated Amount field per section order
                                const amountRaw = paymentData['Amount'] || paymentData['Total'] || '';
                                const amountParts = String(amountRaw).split('|').map(s => parseFloat(s.replace(/,/g, '').trim()) || 0);

                                // Allocate per section: sections order matches methodParts order
                                let cashPortion = 0;
                                methodParts.forEach((m, idx) => {
                                    const amt = amountParts[idx] || 0;
                                    if (m.toLowerCase() === 'cash') {
                                        cashPortion += amt;
                                    } else {
                                        const label = resolveMethodLabel(m).toUpperCase();
                                        if (!nonCashBreakdown[label]) nonCashBreakdown[label] = 0;
                                        nonCashBreakdown[label] += amt;
                                        totalNonCashPayment += amt;
                                    }
                                });

                                // Fallback: if amounts couldn't be parsed, use total
                                if (cashPortion === 0 && Object.keys(nonCashBreakdown).length === 0) {
                                    totalNonCashPayment += saleAmount;
                                    const fallbackLabel = resolveMethodLabel(methodParts.find(m => m.toLowerCase() !== 'cash') || methodParts[0]).toUpperCase();
                                    if (!nonCashBreakdown[fallbackLabel]) nonCashBreakdown[fallbackLabel] = 0;
                                    nonCashBreakdown[fallbackLabel] += saleAmount;
                                }

                                totalCash += cashPortion;

                            } else if (methodParts.length > 1) {
                                // Pure non-cash split (no cash) without unit map: use pipe-separated amounts
                                const amountRaw = paymentData['Amount'] || paymentData['Total'] || '';
                                const amountParts = String(amountRaw).split('|').map(s => parseFloat(s.replace(/,/g, '').trim()) || 0);
                                const totalParsed = amountParts.reduce((a, b) => a + b, 0);

                                if (totalParsed > 0) {
                                    methodParts.forEach((m, idx) => {
                                        const amt = amountParts[idx] || 0;
                                        if (amt > 0) {
                                            const label = resolveMethodLabel(m).toUpperCase();
                                            if (!nonCashBreakdown[label]) nonCashBreakdown[label] = 0;
                                            nonCashBreakdown[label] += amt;
                                            totalNonCashPayment += amt;
                                        }
                                    });
                                } else {
                                    // Fallback: split evenly (shouldn't normally happen)
                                    const share = saleAmount / methodParts.length;
                                    methodParts.forEach(m => {
                                        const label = resolveMethodLabel(m).toUpperCase();
                                        if (!nonCashBreakdown[label]) nonCashBreakdown[label] = 0;
                                        nonCashBreakdown[label] += share;
                                        totalNonCashPayment += share;
                                    });
                                }

                            } else {
                                // Single non-cash method
                                totalNonCashPayment += saleAmount;
                                const specificTypeUpper = resolveMethodLabel(specificType).toUpperCase();
                                if (!nonCashBreakdown[specificTypeUpper]) {
                                    nonCashBreakdown[specificTypeUpper] = 0;
                                }
                                nonCashBreakdown[specificTypeUpper] += saleAmount;
                            }
                        }
                    } catch (e) {
                        totalCash += saleAmount;
                    }
                } else {
                    // If no payment data, assume cash
                    totalCash += saleAmount;
                }

                // Track void and refund amounts separately
                if (isVoided) {
                    totalVoid += saleDisplayedTotal;
                } else {
                    // Accumulate refund amount for any sale that has a refund record
                    const refundAmount = parseFloat(sale.refund_amount) || 0;
                    totalRefund += refundAmount;
                }

                // Accumulate upgrade amount (balance paid) for UPGD sales
                if (isNewUpgradeSale) {
                    totalUpgrade += saleAmount;
                    totalOldUnit += oldUnitAmount;
                }

                // Calculate commission breakdown
                const encoderUpper = encoderName.toUpperCase();
                const comm = parseFloat(sale.commission) || 0;
                if (!commissionBreakdown[encoderUpper]) {
                    commissionBreakdown[encoderUpper] = 0;
                }
                commissionBreakdown[encoderUpper] += comm;
                totalCommissions += comm;
            });

            // Build breakdown HTML - format like 2nd picture
            let leftSideHTML = '';

            // NET SALES is now the same as GRAND TOTAL AMOUNT since voids are already excluded
            const netSales = grandTotalAmount;

            Object.values(groupedByEncoder).forEach(encoder => {
                leftSideHTML += `<div class="breakdown-item"><span>${encoder.fullName} ${encoder.quantity} ${encoder.group}</span><span>${encoder.totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div>`;
            });

            // Add summary - with label and value separated
            leftSideHTML += `
                <br>
                <div class="breakdown-summary">
                    <div class="breakdown-summary-row"><span>UNIT QUANTITY:</span><span>${totalUnits}</span></div>
                    <div class="breakdown-summary-row breakdown-red"><span>GRAND TOTAL AMOUNT:</span><span>${grandTotalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div>
                    <br>
                    <div class="breakdown-summary-row"><span>NON-CASH PAYMENTS:</span><span></span></div>
            `;

            for (const [method, amount] of Object.entries(nonCashBreakdown)) {
                leftSideHTML += `<div class="breakdown-summary-row" style="padding-left: 15px;"><span>${method}</span><span>${amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div>`;
            }

            leftSideHTML += `
                    <div class="breakdown-summary-row"><span>CASH PAYMENT:</span><span>${totalCash.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div>
                    <div class="breakdown-summary-row"><span>COMMISSIONS:</span><span>${totalCommissions.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div>
                    <div class="breakdown-summary-row"><span>UPGRADE:</span><span>${totalUpgrade.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div>
                    <div class="breakdown-summary-row" style="color:#d32f2f;"><span>VOID:</span><span>${totalVoid > 0 ? totalVoid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00'}</span></div>
                    <div class="breakdown-summary-row" style="color:#d32f2f;"><span>REFUND:</span><span>${totalRefund > 0 ? totalRefund.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00'}</span></div>
                    <div class="breakdown-summary-row" style="margin-top: 8px;"><span>NET SALES:</span><span>${netSales.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div>
                </div>
            `;

            let commHTML = `<div style="border: 2px solid #000; font-family: 'Courier New', Courier, monospace; font-size: 13px; font-weight: bold; width: 300px; -webkit-print-color-adjust: exact; print-color-adjust: exact;">`;
            commHTML += `<div style="border-bottom: 2px solid #000; padding: 6px; text-align: center; color: #000;">COMMISSION BREAKDOWNS</div>`;
            commHTML += `<div style="padding: 6px; color: #000;">`;
            for (const [enc, comm] of Object.entries(commissionBreakdown)) {
                commHTML += `<div style="display: flex; justify-content: space-between; margin-bottom: 3px;"><span>${enc}</span><span>${comm.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div>`;
            }
            commHTML += `</div></div>`;

            let breakdownHTML = `
                <div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
                    <div style="max-width: 400px; flex: 0 0 auto;">
                        ${leftSideHTML}
                    </div>
                    <div style="display: flex; gap: 8px; flex: 0 0 auto;">
                        <div style="flex: 0 0 auto;">
                            ${commHTML}
                        </div>
                        <div id="unclaimedFreebiesBreakdownBox" style="display: none; flex: 0 0 auto;">
                            <!-- Unclaimed freebies box will be inserted here -->
                        </div>
                    </div>
                </div>
            `;

            breakdownSection.innerHTML = breakdownHTML;
            breakdownSection.style.display = 'block';

            // Re-render unclaimed freebies breakdown if data is available
            if (currentUnclaimedFreebiesData) {
                displayUnclaimedFreebies(currentUnclaimedFreebiesData);
            }
        }

        let currentSalesData = [];
        let currentUnclaimedFreebiesData = null;

        /* --- Fetch Unclaimed Freebies --- */
        function fetchUnclaimedFreebies(dateFrom, dateTo, branch) {
            const url = `get_unclaimed_freebies_report.php?date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}&branch=${encodeURIComponent(branch)}`;

            console.log('Fetching unclaimed freebies:', url);

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentUnclaimedFreebiesData = data;
                        displayUnclaimedFreebies(data);
                    } else {
                        console.error('Error fetching unclaimed freebies:', data.message);
                        currentUnclaimedFreebiesData = null;
                        const container = document.getElementById('unclaimedFreebiesBreakdownBox');
                        if (container) container.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    currentUnclaimedFreebiesData = null;
                    const container = document.getElementById('unclaimedFreebiesBreakdownBox');
                    if (container) container.style.display = 'none';
                });
        }

        /* --- Format Date Time Helper --- */
        function formatDateTime(dateTimeString) {
            if (!dateTimeString) return '';
            const date = new Date(dateTimeString);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day} ${hours}:${minutes}`;
        }

        /* --- Display Unclaimed Breakdown --- */
        function displayUnclaimedFreebies(data, salesList) {
            if (data !== undefined) currentUnclaimedFreebiesData = data;
            if (salesList !== undefined) currentSalesData = salesList;

            let container = document.getElementById('unclaimedFreebiesBreakdownBox');

            if (!container) {
                // Generate breakdown layout if not created yet by sales data
                generateBreakdown(currentSalesData || []);
                container = document.getElementById('unclaimedFreebiesBreakdownBox');
            }

            if (!container) {
                console.warn('Unclaimed breakdown container not found');
                return;
            }

            const records = [];
            const sales = currentSalesData || [];
            const branchName = document.getElementById('displayBranch')?.textContent?.trim() || '';

            // 1. Process Pre-orders from sales entries
            const preorderGroups = new Map();
            sales.forEach(sale => {
                if (sale.is_preorder || (sale.id && String(sale.id).startsWith('PO-'))) {
                    const items = (sale.items && sale.items.length > 0) ? sale.items : [{
                        item_description: sale.remarks || 'Pre-order Unit',
                        item_code: '',
                        quantity: sale.total_qty || 1,
                        imei: ''
                    }];

                    items.forEach(item => {
                        const pKey = (sale.preorder_id || sale.original_preorder_no || sale.invoice_no || '0') + '_' + (item.item_description || item.item_code || '') + '_' + (item.imei || '');

                        if (!preorderGroups.has(pKey)) {
                            preorderGroups.set(pKey, {
                                item_description: item.item_description || item.item_code || 'N/A',
                                quantity: item.quantity || 1,
                                branch: branchName,
                                status: (sale.preorder_status || '').toLowerCase(),
                                claimed_at: sale.claimed_at,
                                claimed_invoice_no: sale.claimed_invoice_no,
                                payments: []
                            });
                        }

                        const group = preorderGroups.get(pKey);
                        const invNo = sale.invoice_no;
                        if (invNo && !group.payments.some(p => p.invoice_no === invNo)) {
                            group.payments.push({
                                invoice_no: invNo,
                                date: sale.created_at
                            });
                        }
                    });
                }
            });

            preorderGroups.forEach(group => {
                const isClaimed = group.status === 'claimed';

                // UNCLAIMED PRE-ORDER entry for each payment invoice
                group.payments.forEach(p => {
                    records.push({
                        type: 'preorder',
                        invoice_number: p.invoice_no,
                        item_code: group.item_description,
                        quantity: group.quantity,
                        branch: group.branch,
                        status: 'unclaimed',
                        created_at: p.date,
                        claimed_at: null
                    });
                });

                // CLAIMED PRE-ORDER combined entry if claimed
                if (isClaimed) {
                    const allInvoices = group.payments.map(p => p.invoice_no);
                    if (group.claimed_invoice_no && !allInvoices.includes(group.claimed_invoice_no)) {
                        allInvoices.push(group.claimed_invoice_no);
                    }
                    const combinedInvoiceNo = allInvoices.join(' & ');

                    records.push({
                        type: 'preorder',
                        invoice_number: combinedInvoiceNo,
                        item_code: group.item_description,
                        quantity: group.quantity,
                        branch: group.branch,
                        status: 'claimed',
                        created_at: group.payments[0]?.date,
                        claimed_at: group.claimed_at || group.payments[0]?.date
                    });
                }
            });

            // 2. Process Freebies records if any
            if (currentUnclaimedFreebiesData && currentUnclaimedFreebiesData.data) {
                currentUnclaimedFreebiesData.data.forEach(fb => {
                    records.push({
                        type: 'freebie',
                        invoice_number: fb.invoice_number || 'N/A',
                        item_code: fb.item_code || fb.item_description || 'N/A',
                        quantity: fb.quantity || 1,
                        branch: fb.branch || branchName,
                        status: fb.status,
                        created_at: fb.created_at,
                        claimed_at: fb.claimed_at
                    });
                });
            }

            if (records.length === 0) {
                container.style.display = 'none';
                return;
            }

            let html = '';

            // Create bordered box
            html += `<div style="border: 2px solid #000; font-family: 'Courier New', Courier, monospace; font-size: 13px; font-weight: bold; width: 400px; max-height: 600px; overflow-y: auto; -webkit-print-color-adjust: exact; print-color-adjust: exact;">`;
            html += `<div style="border-bottom: 2px solid #000; padding: 6px; text-align: center; color: #000; font-size: 13px;">UNCLAIMED BREAKDOWNS</div>`;
            html += `<div style="padding: 6px; color: #000;">`;

            // Show simple list of records
            for (const record of records) {
                const statusColor = record.status === 'unclaimed' ? '#d32f2f' : '#2e7d32';
                const isPreorder = record.type === 'preorder';
                let statusText = '';
                if (record.status === 'unclaimed') {
                    statusText = isPreorder ? 'UNCLAIMED PRE-ORDER' : 'UNCLAIMED FREEBIES';
                } else {
                    statusText = isPreorder ? 'CLAIMED PRE-ORDER' : 'CLAIMED FREEBIES';
                }

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

        /* --- Print --- */
        function printReport() {
            const dateFromField = document.getElementById('filterDateFrom');
            const branchField = document.getElementById('filterBranch');

            // Debug: Log field elements
            console.log('dateFromField:', dateFromField);
            console.log('branchField:', branchField);

            // Check if fields exist
            if (!dateFromField) {
                alert('Date field not found.');
                return;
            }

            if (!branchField) {
                alert('Branch field not found.');
                return;
            }

            const dateVal = dateFromField.value;
            const branchVal = branchField.value;

            // Single date for daily report
            const dateToVal = dateVal;

            // Debug: Log values
            console.log('dateVal:', dateVal);
            console.log('branchVal:', branchVal);

            // Validate
            if (!dateVal) {
                alert('Please select a date before printing.');
                return;
            }

            if (!branchVal) {
                alert('Please select a branch before printing.');
                return;
            }

            // Build URL
            const url =
                'print_report_pdf.php?date_from=' + encodeURIComponent(dateVal) +
                '&date_to=' + encodeURIComponent(dateToVal) +
                '&branch=' + encodeURIComponent(branchVal);
            console.log('Opening URL:', url);

            // Open PDF in new window
            window.open(url, '_blank', 'width=900,height=700');
        }

        /* --- Invoice Modal Functions --- */
        function viewInvoiceDetails(invoiceNo, paymentMethodFilter = null, imeiFilter = null) {
            if (!invoiceNo) {
                alert('No invoice number provided');
                return;
            }

            // Show loading modal
            showInvoiceModal(invoiceNo, null, paymentMethodFilter, imeiFilter);

            // Fetch invoice details
            fetch('get_invoice_details.php?invoice_no=' + encodeURIComponent(invoiceNo))
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showInvoiceModal(invoiceNo, data, paymentMethodFilter, imeiFilter);
                    } else {
                        alert('Error: ' + (data.message || 'Unable to load invoice details'));
                        closeInvoiceModal();
                    }
                })
                .catch(error => {
                    console.error('Error fetching invoice details:', error);
                    alert('Error loading invoice details');
                    closeInvoiceModal();
                });
        }

        function showInvoiceModal(invoiceNo, data, paymentMethodFilter = null, imeiFilter = null) {
            const modal = document.getElementById('invoiceModal');
            const content = document.getElementById('invoiceModalContent');

            if (!data) {
                // Show loading state
                content.innerHTML = '<div style="text-align:center; padding:40px;"><p>Loading invoice details...</p></div>';
            } else {
                // Parse payment data
                let paymentData = {};
                try {
                    paymentData = data.sale.payment_data ? JSON.parse(data.sale.payment_data) : {};
                } catch (e) {
                    console.error('Error parsing payment data:', e);
                }

                // Merge multiple payments array if present into paymentData top-level for convenience
                if (Array.isArray(paymentData.payments)) {
                    paymentData.payments.forEach(p => {
                        if (p && typeof p === 'object') {
                            for (let key in p) {
                                if (paymentData[key] === undefined) {
                                    paymentData[key] = p[key];
                                }
                            }
                            if (p.payment_partner && !paymentData.payment_partner) {
                                paymentData.payment_partner = p.payment_partner;
                            }
                        }
                    });
                }

                // Helper to check if payment method or data represents a Payment Partner (Bank of Makati, STO, Home Credit, Sumisho, Salmon, Samsung Finances, Paymongo, Payjoy, Billease, Skyro, etc.)
                function isPaymentPartner(payType, data) {
                    const pt = String(payType || '').toLowerCase().trim();
                    if (pt.includes('bank of makati') || pt.includes('makati') || pt.includes('sto') ||
                        pt.includes('home credit') || pt.includes('sumisho') ||
                        pt.includes('salmon') || pt.includes('samsung finance') ||
                        pt.includes('paymongo') || pt.includes('payjoy') ||
                        pt.includes('billease') || pt.includes('skyro') ||
                        pt.includes('payment_partner') || pt.includes('partner')) {
                        return true;
                    }
                    if (!payType && data && typeof data === 'object') {
                        const dpt = String(data.payment_partner || data.payment_type || '').toLowerCase().trim();
                        if (dpt.includes('bank of makati') || dpt.includes('makati') || dpt.includes('sto') ||
                            dpt.includes('home credit') || dpt.includes('sumisho') ||
                            dpt.includes('salmon') || dpt.includes('samsung finance') ||
                            dpt.includes('paymongo') || dpt.includes('payjoy') ||
                            dpt.includes('billease') || dpt.includes('skyro') ||
                            dpt.includes('payment_partner') || dpt.includes('partner')) {
                            return true;
                        }
                    }
                    return false;
                }

                // Helper function to extract numeric value from mixed format (handles "value | reference" format)
                function extractNumericFromMixed(raw) {
                    if (raw === null || raw === undefined || raw === '') return 0;
                    if (typeof raw === 'number') return raw;
                    const str = String(raw);
                    const parts = str.split('|');
                    for (let part of parts) {
                        part = part.trim().replace(/,/g, '');
                        if (!isNaN(part) && parseFloat(part) > 0) {
                            return parseFloat(part);
                        }
                    }
                    return 0;
                }

                // Helper to extract amount from a payment object with fallbacks
                function extractPaymentAmount(p, defaultVal = 0) {
                    if (!p || typeof p !== 'object') return defaultVal;
                    const candidates = [
                        p.amount, p['Amount'], p['Amount '], p.cash_amount, p['Cash Amount'],
                        p.credit_card_amount, p.card_amount, p.debit_card_amount,
                        p.gcash_amount, p.maya_amount, p.bank_amount,
                        p['Total'], p.total
                    ];
                    for (let c of candidates) {
                        const val = extractNumericFromMixed(c);
                        if (val > 0) return val;
                    }
                    return defaultVal;
                }

                // Helper function to format text (convert underscore_text to Title Case)
                function formatDisplayText(text) {
                    if (!text || text === 'N/A') return text;
                    let formatted = String(text)
                        .replace(/_/g, ' ')
                        .split(' ')
                        .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                        .join(' ');

                    formatted = formatted.replace(/(\d+)(months?)/gi, '$1 $2');
                    formatted = formatted.replace(/\s(months?)/gi, ' Months');
                    return formatted;
                }

                // Get formatted overall payment method
                const overallPaymentMethod = formatPaymentMethodName(paymentData);

                // Extract unit_payment_map from payment_data (needed for per-item filtering)
                const unitPaymentMap = (paymentData.unit_payment_map && typeof paymentData.unit_payment_map === 'object')
                    ? paymentData.unit_payment_map : {};

                // Helper function to get payment method for a specific item
                function getItemPaymentMethod(item) {
                    if (Object.keys(unitPaymentMap).length === 0) return overallPaymentMethod;

                    const imei = (item.imei || '').trim().toUpperCase();
                    const desc = (item.item_description || item.item_code || '').trim().toUpperCase();
                    const fullKey = imei ? `${desc} (${imei})` : desc;

                    for (const [key, method] of Object.entries(unitPaymentMap)) {
                        if (fullKey === key.toUpperCase()) return method;
                    }
                    if (imei) {
                        for (const [key, method] of Object.entries(unitPaymentMap)) {
                            const imeiMatch = key.toUpperCase().match(/\(([^)]+)\)$/);
                            if (imeiMatch && imeiMatch[1] === imei) return method;
                        }
                    }
                    for (const [key, method] of Object.entries(unitPaymentMap)) {
                        if (desc && key.toUpperCase().startsWith(desc)) return method;
                    }
                    return overallPaymentMethod;
                }

                // Build items table & compute proportional amounts
                let itemsHTML = '';
                let overallAmount = 0;
                let filteredItemsTotal = 0;
                let filteredItems = [];

                if (data.items && data.items.length > 0) {
                    filteredItems = data.items;

                    if (paymentMethodFilter && paymentMethodFilter.trim() !== '') {
                        const normFilter = paymentMethodFilter.trim().toLowerCase();

                        filteredItems = data.items.filter(item => {
                            const itemPayMethod = getItemPaymentMethod(item);
                            const normItemMethod = itemPayMethod.trim().toLowerCase();

                            const methodMatches = normItemMethod === normFilter ||
                                normItemMethod.includes(normFilter) ||
                                normFilter.includes(normItemMethod) ||
                                normItemMethod.split(/\s*[&+]\s*/).some(m => m.trim() === normFilter) ||
                                normFilter.split(/\s*[&+]\s*/).some(m => m.trim() === normItemMethod);

                            if (imeiFilter && imeiFilter.trim() !== '') {
                                const itemImei = (item.imei || '').trim().toUpperCase();
                                const filterImei = imeiFilter.trim().toUpperCase();
                                return methodMatches && itemImei === filterImei;
                            }
                            return methodMatches;
                        });
                    }

                    if (filteredItems.length > 0) {
                        // For upgrade invoices, use cash paid from payment_data.Amount as the total
                        const isUpgradeInvoiceItems = data.sale.upgrade === 'UPGD' && data.sale.original_invoice_no && data.sale.original_invoice_no.trim() !== '';
                        let upgradeCashPaid = 0;
                        if (isUpgradeInvoiceItems) {
                            const amtRaw = paymentData['Amount'] || paymentData['amount'] || '';
                            const parsedAmt = parseFloat(String(amtRaw).replace(/,/g, '').trim());
                            upgradeCashPaid = (!isNaN(parsedAmt) && parsedAmt > 0) ? parsedAmt : 0;
                        }
                        const saleTotal = isUpgradeInvoiceItems && upgradeCashPaid > 0
                            ? upgradeCashPaid
                            : parseFloat(data.sale.actual_total_amount || data.sale.total_amount || 0);
                        const totalSrp = data.items.reduce((sum, it) => sum + (parseFloat(it.price || 0) * parseInt(it.quantity || 1)), 0);

                        filteredItems.forEach(item => {
                            const itemPrice = parseFloat(item.price || 0);
                            const qty = parseInt(item.quantity || 1);
                            // Try per-method amount first (fixes HC+Cash proportional rounding)
                            const perItemAmtModal = (typeof getPerItemAmountFromMap === 'function')
                                ? getPerItemAmountFromMap(item, paymentData, unitPaymentMap)
                                : null;
                            let itemTotal;
                            if (perItemAmtModal !== null) {
                                itemTotal = Math.round(perItemAmtModal);
                            } else {
                                itemTotal = getItemNetTotal(item, data.sale, data.items);
                            }
                            overallAmount += itemTotal;

                            itemsHTML += `
                                <tr>
                                    <td style="padding:8px; border:1px solid #acacacff;">${item.item_code || ''}</td>
                                    <td style="padding:8px; border:1px solid #acacacff;">${item.item_description || ''}</td>
                                    <td style="padding:8px; border:1px solid #acacacff; text-align:center;">${item.imei || ''}</td>
                                    <td style="padding:8px; border:1px solid #acacacff; text-align:center;">${qty}</td>
                                    <td style="padding:8px; border:1px solid #acacacff; text-align:right;">₱${itemPrice.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                                    <td style="padding:8px; border:1px solid #acacacff; text-align:right;">₱${itemTotal.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                                </tr>
                            `;
                        });

                        itemsHTML += `
                            <tr style="background:#F5EDE8; font-weight:bold; border-top:2px solid #1E455D;">
                                <td colspan="5" style="padding:12px; border:1px solid #acacacff; text-align:right; font-size:15px;">OVERALL AMOUNT:</td>
                                <td style="padding:12px; border:1px solid #acacacff; text-align:right; color:#1E455D; font-size:15px;">₱${overallAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            </tr>
                        `;
                        filteredItemsTotal = overallAmount;
                    } else {
                        itemsHTML = '<tr><td colspan="6" style="padding:20px; text-align:center; color:#999;">No items found for this payment method</td></tr>';
                    }
                } else {
                    itemsHTML = '<tr><td colspan="6" style="padding:20px; text-align:center; color:#999;">No items found</td></tr>';
                }

                // Get token and voucher amounts early (needed in renderPaymentDetails function)
                const tokenAmount = parseFloat(data.sale.token || 0);
                const voucherAmount = parseFloat(data.sale.voucher_amount || data.sale.voucher || 0);

                // Build Unit rows with per-unit voucher/token (matched to sale items)
                function buildUnitRowsWithVoucherToken(unitLabels) {
                    const lookupItems = (filteredItems && filteredItems.length > 0)
                        ? filteredItems
                        : (data.items || []);
                    if (!unitLabels || unitLabels.length === 0) return '';

                    return unitLabels.map((u, idx) => {
                        const label = unitLabels.length === 1 ? 'Unit:' : `Unit ${idx + 1}:`;
                        let rows = `<tr><td style="padding:5px 10px 5px 0; font-weight:600; width:180px;">${label}</td><td style="padding:5px 0;">${u}</td></tr>`;

                        const uUpper = String(u).toUpperCase();
                        const matched = lookupItems.find(it => {
                            const desc = (it.item_description || it.item_code || '').trim().toUpperCase();
                            const imei = (it.imei || '').trim().toUpperCase();
                            const full = imei ? `${desc} (${imei})` : desc;
                            return full === uUpper || (imei && uUpper.includes(imei)) || (desc && uUpper.startsWith(desc));
                        });

                        if (matched) {
                            const v = parseFloat(matched.voucher_amount) || 0;
                            const t = parseFloat(matched.token_amount) || 0;
                            if (v > 0) {
                                rows += `<tr><td style="padding:2px 10px 2px 20px; font-weight:600; color:#000000; font-size:13px;">↳ Voucher:</td><td style="padding:2px 0; color:#000000; font-size:13px;">-₱${v.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>`;
                            }
                            if (t > 0) {
                                rows += `<tr><td style="padding:2px 10px 2px 20px; font-weight:600; color:#000000; font-size:13px;">↳ Token:</td><td style="padding:2px 0; color:#000000; font-size:13px;">-₱${t.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>`;
                            }
                        }
                        return rows;
                    }).join('');
                }

                // Helper to render individual payment details
                function renderPaymentDetails(payType, pData, overrideAmount = null, includeToken = false, includeVoucher = false, unitInfo = null) {
                    const normType = String(payType || '').toLowerCase().trim();
                    let html = '';
                    let amt = (overrideAmount !== null && overrideAmount > 0)
                        ? overrideAmount
                        : extractPaymentAmount(pData, 0);

                    let unitArray = [];
                    if (Array.isArray(unitInfo)) {
                        unitArray = unitInfo.filter(Boolean);
                    } else if (typeof unitInfo === 'string' && unitInfo.trim()) {
                        unitArray = unitInfo.split(',').map(s => s.trim()).filter(Boolean);
                    }

                    // Per-unit voucher/token under each Unit (not global at bottom)
                    const unitRow = buildUnitRowsWithVoucherToken(unitArray);

                    // Check if this is explicitly a simple payment type (Cash, no additional details needed)
                    const isSimplePayment = normType === 'cash' || normType === 'cash payment';

                    // 1. PAYMENT PARTNERS (Loan / Financing) - Skip if it's a simple payment type
                    if (!isSimplePayment && (isPaymentPartner(payType, pData) || (pData && (pData.payment_type === 'payment_partners' || pData.loan_type || pData['Loan Type'])))) {
                        const loanType = formatDisplayText(pData['Loan Type'] || pData['loan_type'] || pData['loanTypeDropdown']) || 'N/A';
                        const loanTerms = formatDisplayText(pData['Loan Terms'] || pData['loan_terms']) || 'N/A';
                        const customerName = pData["Customer's Name"] || pData["Customers Name"] || pData.customer_name || 'N/A';
                        const loanNumber = pData['Loan Number'] || pData.loan_number || 'N/A';
                        const loanBalance = extractNumericFromMixed(pData['Loan Balance'] || pData.loan_balance || pData.total_loan_amount || pData['total_loan_amount'] || pData['totalLoanAmount'] || pData['Loan Amount'] || pData['Amount Financed'] || 0);

                        html += `
                            ${unitRow}
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600; width:180px;">Loan Type:</td><td style="padding:5px 0;">${loanType}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Loan Terms:</td><td style="padding:5px 0;">${loanTerms}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Customer's Name:</td><td style="padding:5px 0;">${customerName}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Loan Number:</td><td style="padding:5px 0;">${loanNumber}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Loan Balance:</td><td style="padding:5px 0;">₱${loanBalance.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>
                        `;

                        let dpTotal = 0;
                        const cashDPAmt = pData['cash_down_payment_amount'] || pData['cash_dp_amount'] || pData['cash_amount'];
                        if (cashDPAmt) {
                            const cashDP = extractNumericFromMixed(cashDPAmt);
                            if (cashDP > 0) {
                                html += `<tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Cash Amount (DP):</td><td style="padding:5px 0;">₱${cashDP.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>`;
                                dpTotal += cashDP;
                            }
                        }
                        const gcashDPAmt = pData['gcash_down_payment_amount'] || pData['gcash_dp_amount'];
                        if (gcashDPAmt) {
                            const gcashDP = extractNumericFromMixed(gcashDPAmt);
                            if (gcashDP > 0) {
                                const ref = pData['gcash_down_payment_reference'] || pData['gcash_dp_ref'] || pData.gcash_reference || '';
                                if (ref) html += `<tr><td style="padding:5px 10px 5px 20px; font-weight:500;">G-Cash Reference No:</td><td style="padding:5px 0;">${ref}</td></tr>`;
                                html += `<tr><td style="padding:5px 10px 5px 20px; font-weight:500;">G-Cash Amount (DP):</td><td style="padding:5px 0;">₱${gcashDP.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>`;
                                dpTotal += gcashDP;
                            }
                        }
                        const mayaDPAmt = pData['maya_down_payment_amount'] || pData['maya_dp_amount'];
                        if (mayaDPAmt) {
                            const mayaDP = extractNumericFromMixed(mayaDPAmt);
                            if (mayaDP > 0) {
                                const ref = pData['maya_down_payment_reference'] || pData['maya_dp_ref'] || pData.maya_reference || '';
                                if (ref) html += `<tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Maya Reference No:</td><td style="padding:5px 0;">${ref}</td></tr>`;
                                html += `<tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Maya Amount (DP):</td><td style="padding:5px 0;">₱${mayaDP.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>`;
                                dpTotal += mayaDP;
                            }
                        }

                        return { html, amount: (loanBalance + dpTotal) };
                    }

                    // 2. CREDIT CARD - Skip if it's a simple payment type
                    if (!isSimplePayment && (normType.includes('credit') || (pData && pData.payment_type === 'credit_card'))) {
                        const terminalIssuer = pData['Terminal Issuer'] || pData['ccTerminalIssuer'] || pData.terminal_issuer || 'N/A';
                        const terminalId = pData['Terminal ID'] || pData['ccTerminalId'] || pData.terminal_id || 'N/A';
                        const bank = pData['Bank'] || pData['creditCardBankDropdown'] || pData['Bank-Text'] || pData.bank || 'N/A';
                        const terms = pData['Terms'] || pData['creditCardTermsDropdown'] || pData.terms || 'N/A';
                        const mid = pData['MID'] || pData.mid || 'N/A';
                        const cardNo = pData['Card No'] || pData.card_no || 'N/A';
                        const approvalCode = pData['Approval Code'] || pData.approval_code || 'N/A';
                        const batch = pData['Batch'] || pData.batch || 'N/A';

                        html += `
                            ${unitRow}
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Terminal Issuer:</td><td style="padding:5px 0;">${terminalIssuer}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Terminal ID:</td><td style="padding:5px 0;">${terminalId}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Bank:</td><td style="padding:5px 0;">${bank}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Terms:</td><td style="padding:5px 0;">${terms}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">MID:</td><td style="padding:5px 0;">${mid}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Card No:</td><td style="padding:5px 0;">${cardNo}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Approval Code:</td><td style="padding:5px 0;">${approvalCode}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Batch:</td><td style="padding:5px 0;">${batch}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Amount:</td><td style="padding:5px 0;">₱${amt.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>
                        `;

                        return { html, amount: amt };
                    }

                    // 3. DEBIT CARD - Skip if it's a simple payment type
                    if (!isSimplePayment && (normType.includes('debit') || (pData && pData.payment_type === 'debit_card'))) {
                        const terminalIssuer = pData['Terminal Issuer'] || pData['dcTerminalIssuer'] || pData.terminal_issuer || 'N/A';
                        const terminalId = pData['Terminal ID'] || pData['dcTerminalId'] || pData.terminal_id || 'N/A';
                        const bank = pData['Bank'] || pData['debitCardBankDropdown'] || pData['Bank-Text'] || pData.bank || 'N/A';
                        const terms = pData['Terms'] || pData['debitCardTermsDropdown'] || pData.terms || 'N/A';
                        const mid = pData['MID'] || pData.mid || 'N/A';
                        const cardNo = pData['Card No'] || pData.card_no || 'N/A';
                        const approvalCode = pData['Approval Code'] || pData.approval_code || 'N/A';
                        const batch = pData['Batch'] || pData.batch || 'N/A';

                        html += `
                            ${unitRow}
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Terminal Issuer:</td><td style="padding:5px 0;">${terminalIssuer}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Terminal ID:</td><td style="padding:5px 0;">${terminalId}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Bank:</td><td style="padding:5px 0;">${bank}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Terms:</td><td style="padding:5px 0;">${terms}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">MID:</td><td style="padding:5px 0;">${mid}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Card No:</td><td style="padding:5px 0;">${cardNo}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Approval Code:</td><td style="padding:5px 0;">${approvalCode}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Batch:</td><td style="padding:5px 0;">${batch}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Amount:</td><td style="padding:5px 0;">₱${amt.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>
                        `;

                        return { html, amount: amt };
                    }

                    // 4. QR PH & STARPAY QR - Skip if it's a simple payment type
                    if (!isSimplePayment && (normType.includes('qr ph') || (pData && pData.payment_type === 'qr_ph'))) {
                        const bank = pData['Bank'] || pData.bank || 'N/A';
                        const customerName = pData["Customer's Name"] || pData["Customers Name"] || pData.customer_name || 'N/A';
                        const referenceNo = pData['Reference No'] || pData.reference_no || 'N/A';

                        html += `
                            ${unitRow}
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Bank:</td><td style="padding:5px 0;">${bank}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Customer's Name:</td><td style="padding:5px 0;">${customerName}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Reference No:</td><td style="padding:5px 0;">${referenceNo}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Amount:</td><td style="padding:5px 0;">₱${amt.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>
                        `;

                        return { html, amount: amt };
                    }
                    if (!isSimplePayment && (normType.includes('starpay') || (pData && pData.payment_type === 'starpay_qr'))) {
                        const bank = pData['Bank'] || pData.bank || 'N/A';
                        const customerName = pData["Customer's Name"] || pData["Customers Name"] || pData.customer_name || 'N/A';
                        const referenceNo = pData['Reference No'] || pData.reference_no || 'N/A';

                        html += `
                            ${unitRow}
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Bank:</td><td style="padding:5px 0;">${bank}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Customer's Name:</td><td style="padding:5px 0;">${customerName}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Reference No:</td><td style="padding:5px 0;">${referenceNo}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Amount:</td><td style="padding:5px 0;">₱${amt.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>
                        `;

                        return { html, amount: amt };
                    }

                    // 5. E-WALLET (GCash, Maya, PayMaya, ShopeePay, etc.) - Skip if it's a simple payment type
                    if (!isSimplePayment && (normType.includes('gcash') || normType.includes('maya') || normType.includes('ewallet') || normType.includes('e-wallet') || (pData && (pData.payment_type === 'ewallet' || pData.ewallet_type || pData['E-Wallet'])))) {
                        const customerName = pData["Customer's Name"] || pData["Customers Name"] || pData.customer_name || 'N/A';
                        const referenceNo = pData['Reference No'] || pData.reference_no || pData.reference || 'N/A';

                        html += `
                            ${unitRow}
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Customer's Name:</td><td style="padding:5px 0;">${customerName}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Reference No:</td><td style="padding:5px 0;">${referenceNo}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Amount:</td><td style="padding:5px 0;">₱${amt.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>
                        `;

                        return { html, amount: amt };
                    }

                    // 6. ONLINE BANKING - Skip if it's a simple payment type
                    if (!isSimplePayment && (normType.includes('online banking') || normType.includes('online_banking') || normType.includes('bdo') || normType.includes('metrobank') || normType.includes('pnb') || normType.includes('e-west') || normType.includes('eastwest') || normType.includes('rcbc') || (pData && (pData.payment_type === 'online_banking' || pData.bank_name)))) {
                        const referenceNo = pData['Reference No'] || pData.reference_no || pData.reference || 'N/A';

                        html += `
                            ${unitRow}
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Reference No:</td><td style="padding:5px 0;">${referenceNo}</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Amount:</td><td style="padding:5px 0;">₱${amt.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>
                        `;

                        return { html, amount: amt };
                    }

                    // 7. CASH or FALLBACK
                    html += `
                        ${unitRow}
                        <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Amount:</td><td style="padding:5px 0;">₱${amt.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>
                    `;

                    return { html, amount: amt };
                }

                // Identify all individual payment objects
                let paymentEntries = [];
                if (Array.isArray(paymentData.payments) && paymentData.payments.length > 0) {
                    paymentEntries = paymentData.payments;
                } else if (paymentData.payment_type && (paymentData.payment_type.includes('+') || paymentData.payment_type.includes('&'))) {
                    // Legacy plus/ampersand separated string
                    const pParts = paymentData.payment_type.split(/\s*[+&]\s*/).map(s => s.trim()).filter(Boolean);
                    pParts.forEach(part => {
                        paymentEntries.push({ payment_type: part, ...paymentData });
                    });
                } else if (Object.keys(paymentData).length > 0) {
                    paymentEntries = [paymentData];
                }

                // Build paymentInfoHTML
                let paymentInfoHTML = '';
                let totalPayment = 0;

                const displayMethod = paymentMethodFilter ? paymentMethodFilter : overallPaymentMethod;
                paymentInfoHTML += `<tr><td style="padding:5px 10px 5px 0; font-weight:600; width:180px;">Payment Method:</td><td style="padding:5px 0;">${displayMethod}</td></tr>`;

                // Check if we have unit_payment_map (per-item payment assignments)
                const hasUnitPaymentMap = Object.keys(unitPaymentMap).length > 0;

                // If we have unit_payment_map and multiple items, show separate payment sections per item
                if (hasUnitPaymentMap && filteredItems.length > 0 && !paymentMethodFilter) {
                    // Group items by payment method
                    const paymentGroups = {};

                    filteredItems.forEach(item => {
                        const itemPayMethod = getItemPaymentMethod(item);
                        if (!paymentGroups[itemPayMethod]) {
                            paymentGroups[itemPayMethod] = [];
                        }
                        paymentGroups[itemPayMethod].push(item);
                    });

                    // Render a payment section for each unique payment method
                    let isFirstPaymentSection = true; // Track if this is the first payment section
                    Object.keys(paymentGroups).forEach(payMethod => {
                        const items = paymentGroups[payMethod];

                        // Find the payment entry that matches this method
                        let matchedEntry = null;
                        const normMethod = payMethod.trim().toLowerCase();
                        for (let entry of paymentEntries) {
                            const entryName = formatPaymentMethodName(entry).toLowerCase();
                            if (entryName === normMethod || entryName.includes(normMethod) || normMethod.includes(entryName)) {
                                matchedEntry = entry;
                                break;
                            }
                        }

                        // Calculate the amount for items in this payment group using getPerItemAmountFromMap
                        const saleTotal = parseFloat(data.sale.actual_total_amount || data.sale.total_amount || 0);
                        const totalSrp = data.items.reduce((sum, it) => sum + (parseFloat(it.price || 0) * parseInt(it.quantity || 1)), 0);
                        let groupAmount = 0;
                        items.forEach(item => {
                            const itemPrice = parseFloat(item.price || 0);
                            const qty = parseInt(item.quantity || 1);
                            const itemSrpSubtotal = itemPrice * qty;
                            const perItemAmt = (typeof getPerItemAmountFromMap === 'function')
                                ? getPerItemAmountFromMap(item, paymentData, unitPaymentMap)
                                : null;
                            let itemTotal;
                            if (perItemAmt !== null) {
                                itemTotal = Math.round(perItemAmt);
                            } else if (saleTotal > 0 && totalSrp > 0) {
                                itemTotal = Math.round(saleTotal * (itemSrpSubtotal / totalSrp));
                            } else {
                                itemTotal = itemSrpSubtotal;
                            }
                            groupAmount += itemTotal;
                        });

                        // Format unit labels for this payment group
                        const unitList = items.map(item => {
                            const desc = item.item_description || item.item_code || '';
                            return item.imei ? `${desc} (${item.imei})` : desc;
                        }).filter(Boolean);

                        // Check if this payment method is a payment partner (for token display)
                        const isPayPartner = isPaymentPartner(payMethod, matchedEntry || paymentData);

                        paymentInfoHTML += `<tr><td colspan="2" style="padding:10px 10px 5px 0; font-weight:600; font-size:15px; color:#1E455D; border-top:1px solid #ddd;">${payMethod}:</td></tr>`;
                        // Show token & voucher only in the first payment section (typically the main/primary item)
                        const res = renderPaymentDetails(payMethod, matchedEntry || paymentData, groupAmount, isFirstPaymentSection && tokenAmount > 0, isFirstPaymentSection && voucherAmount > 0, unitList);
                        paymentInfoHTML += res.html;
                        totalPayment += res.amount;

                        isFirstPaymentSection = false; // After first section, set to false
                    });
                } else if (paymentEntries.length > 1 && !paymentMethodFilter) {
                    // Multiple payment entries without unit_payment_map
                    paymentEntries.forEach((entry) => {
                        const entryType = formatPaymentMethodName(entry);
                        let unitList = [];
                        if (entry.Unit) {
                            unitList = String(entry.Unit).split(',').map(s => s.trim()).filter(s => s && s.toLowerCase() !== 'on' && s.toLowerCase() !== '-- select units --');
                        }
                        if (unitList.length === 0 && filteredItems.length > 0) {
                            unitList = filteredItems.map(item => {
                                const desc = item.item_description || item.item_code || '';
                                return item.imei ? `${desc} (${item.imei})` : desc;
                            }).filter(Boolean);
                        }
                        paymentInfoHTML += `<tr><td colspan="2" style="padding:10px 10px 5px 0; font-weight:600; font-size:15px; color:#1E455D; border-top:1px solid #ddd;">${entryType}:</td></tr>`;
                        const res = renderPaymentDetails(entryType, entry, null, false, false, unitList);
                        paymentInfoHTML += res.html;
                        totalPayment += res.amount;
                    });
                    if (data.sale.upgrade === 'UPGD' && data.sale.original_invoice_no && data.sale.original_invoice_no.trim() !== '') {
                        const oldUnitAmt = parseFloat(data.sale.old_unit_amount || 0);
                        if (oldUnitAmt > 0) {
                            const origInv = ` (${data.sale.original_invoice_no.trim()})`;
                            paymentInfoHTML += `<tr><td style="padding:5px 10px 5px 0; font-weight:600; width:180px;">Old Unit Price:</td><td style="padding:5px 0;">₱${oldUnitAmt.toLocaleString('en-US', { minimumFractionDigits: 2 })}${origInv}</td></tr>`;
                        }
                    }
                } else if (paymentMethodFilter) {
                    // Filtered view: find matching payment entry or use filteredItemsTotal
                    let matchedEntry = null;
                    const normFilter = paymentMethodFilter.trim().toLowerCase();
                    for (let entry of paymentEntries) {
                        const entryName = formatPaymentMethodName(entry).toLowerCase();
                        if (entryName === normFilter || entryName.includes(normFilter) || normFilter.includes(entryName)) {
                            matchedEntry = entry;
                            break;
                        }
                    }
                    const fallbackAmt = filteredItemsTotal > 0 ? filteredItemsTotal : parseFloat(data.sale.actual_total_amount || data.sale.total_amount || 0);
                    let unitList = filteredItems.map(item => {
                        const desc = item.item_description || item.item_code || '';
                        return item.imei ? `${desc} (${item.imei})` : desc;
                    }).filter(Boolean);
                    if (unitList.length === 0 && matchedEntry && matchedEntry.Unit) {
                        unitList = String(matchedEntry.Unit).split(',').map(s => s.trim()).filter(s => s && s.toLowerCase() !== 'on' && s.toLowerCase() !== '-- select units --');
                    }
                    const res = renderPaymentDetails(paymentMethodFilter, matchedEntry || paymentData, fallbackAmt, tokenAmount > 0, voucherAmount > 0, unitList);
                    paymentInfoHTML += res.html;
                    if (data.sale.upgrade === 'UPGD' && data.sale.original_invoice_no && data.sale.original_invoice_no.trim() !== '') {
                        const oldUnitAmt = parseFloat(data.sale.old_unit_amount || 0);
                        if (oldUnitAmt > 0) {
                            const origInv = ` (${data.sale.original_invoice_no.trim()})`;
                            paymentInfoHTML += `<tr><td style="padding:5px 10px 5px 0; font-weight:600; width:180px;">Old Unit Price:</td><td style="padding:5px 0;">₱${oldUnitAmt.toLocaleString('en-US', { minimumFractionDigits: 2 })}${origInv}</td></tr>`;
                        }
                    }
                    totalPayment = res.amount > 0 ? res.amount : fallbackAmt;
                } else {
                    // Single payment method
                    const singleEntry = paymentEntries.length > 0 ? paymentEntries[0] : paymentData;
                    // For upgrade invoices, actual cash paid = payment_data.Amount (not total_amount which may be SRP)
                    const isUpgradeInvoice = data.sale.upgrade === 'UPGD' && data.sale.original_invoice_no && data.sale.original_invoice_no.trim() !== '';
                    let fallbackAmt;
                    if (isUpgradeInvoice) {
                        const amtRaw = paymentData['Amount'] || paymentData['amount'] || '';
                        const parsedAmt = parseFloat(String(amtRaw).replace(/,/g, '').trim());
                        fallbackAmt = (!isNaN(parsedAmt) && parsedAmt > 0) ? parsedAmt : parseFloat(data.sale.actual_total_amount || data.sale.total_amount || 0);
                    } else {
                        fallbackAmt = parseFloat(data.sale.actual_total_amount || data.sale.total_amount || 0);
                    }
                    let unitList = (filteredItems.length > 0 ? filteredItems : (data.items || [])).map(item => {
                        const desc = item.item_description || item.item_code || '';
                        return item.imei ? `${desc} (${item.imei})` : desc;
                    }).filter(Boolean);
                    if (unitList.length === 0 && singleEntry && singleEntry.Unit) {
                        unitList = String(singleEntry.Unit).split(',').map(s => s.trim()).filter(s => s && s.toLowerCase() !== 'on' && s.toLowerCase() !== '-- select units --');
                    }
                    const res = renderPaymentDetails(overallPaymentMethod, singleEntry, fallbackAmt, tokenAmount > 0, voucherAmount > 0, unitList);
                    paymentInfoHTML += res.html;
                    // For upgrade invoices, add Old Unit Price row after payment details
                    if (isUpgradeInvoice) {
                        const oldUnitAmt = parseFloat(data.sale.old_unit_amount || 0);
                        if (oldUnitAmt > 0) {
                            const origInv = (data.sale.original_invoice_no && data.sale.original_invoice_no.trim() !== '') ? ` (${data.sale.original_invoice_no.trim()})` : '';
                            paymentInfoHTML += `<tr><td style="padding:5px 10px 5px 0; font-weight:600; width:180px;">Old Unit Price:</td><td style="padding:5px 0;">₱${oldUnitAmt.toLocaleString('en-US', { minimumFractionDigits: 2 })}${origInv}</td></tr>`;
                        }
                    }
                    totalPayment = res.amount > 0 ? res.amount : fallbackAmt;
                }

                // Add discount information (voucher/token are shown per Unit above)
                const discount = parseFloat(data.sale.discount || 0);
                const isPromoSale = (data.sale.page_type === 'promosentry');

                // Only show discount globally if NOT using unit_payment_map
                if (!hasUnitPaymentMap) {
                    // Don't show discount for upgrade invoices (discount col = old unit trade-in value, not a real discount)
                    const isUpgradeInvoiceDiscount = data.sale.page_type === 'upgradeunit' || (data.sale.upgrade === 'UPGD' && data.sale.original_invoice_no && data.sale.original_invoice_no.trim() !== '');
                    if (discount > 0 && !isPromoSale && !isUpgradeInvoiceDiscount) {
                        paymentInfoHTML += `<tr><td style="padding:5px 10px 5px 0; font-weight:600; color:#d32f2f;">Discount:</td><td style="padding:5px 0; color:#d32f2f;">- ₱${discount.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>`;
                    }
                }

                paymentInfoHTML += `<tr style="border-top:2px solid #acacacff;"><td style="padding:10px 10px 5px 0; font-weight:700; font-size:16px;">Total Payment:</td><td style="padding:10px 0 5px 0; font-weight:700; font-size:16px; color:#1E455D;">₱${totalPayment.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>`;

                const hasTradeIn = Boolean((data.sale.tradein_value && parseFloat(data.sale.tradein_value) > 0) || (data.sale.titu_voucher_total && parseFloat(data.sale.titu_voucher_total) > 0));
                const hasPromoItemInSale = (data.items || []).some(item => item.is_promo_item == 1);
                const isLegacyPromoSale = !hasPromoItemInSale && (
                    (data.sale.page_type === 'promosentry') ||
                    (data.sale.promo_id && parseInt(data.sale.promo_id) > 0)
                );
                const hasPromoItemInModal = filteredItems.some(item => (item.is_promo_item == 1) || isLegacyPromoSale);
                const showPromoDetails = Boolean(data.promo_details) && hasPromoItemInModal;

                const tradeInCardHTML = hasTradeIn ? `
                    <div style="border:2px solid #acacacff; border-radius:8px; padding:15px; background:#f9f9f9;">
                        <h3 style="margin:0 0 12px 0; color:#1E455D; font-size:16px;">Trade-In Details</h3>
                        <table style="width:100%; font-size:14px;">
                            ${(data.sale.tradein_value && parseFloat(data.sale.tradein_value) > 0) ? `
                            <tr><td colspan="2" style="padding:5px 10px 5px 0; font-weight:600; font-size:15px; color:#1E455D; border-bottom:1px solid #ddd; padding-bottom:10px;">Trade-In Item:</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600; width:180px;">Trade-In Value:</td><td style="padding:5px 0;">₱${parseFloat(data.sale.tradein_value).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>
                            ${data.sale.tradein_item_code ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Item Code:</td><td style="padding:5px 0;">${data.sale.tradein_item_code}</td></tr>` : ''}
                            ${data.sale.tradein_imei ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Serial Number:</td><td style="padding:5px 0;">${data.sale.tradein_imei}</td></tr>` : ''}
                            ${data.sale.tradein_brand ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Brand:</td><td style="padding:5px 0;">${data.sale.tradein_brand}</td></tr>` : ''}
                            ` : ''}
                            
                            ${(data.sale.titu_voucher_total && parseFloat(data.sale.titu_voucher_total) > 0) ? `
                            <tr><td colspan="2" style="padding:15px 10px 5px 0; font-weight:600; font-size:15px; color:#1E455D; border-top:1px solid #ddd; margin-top:10px;">TITU Voucher:</td></tr>
                            ${data.sale.titu_control ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">TITU Control:</td><td style="padding:5px 0;">${data.sale.titu_control}</td></tr>` : ''}
                            ${(data.sale.titu_token && data.sale.titu_token.toString().trim() !== '') ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">TITU Token:</td><td style="padding:5px 0;">${parseInt(data.sale.titu_token)}</td></tr>` : ''}
                            ${(data.sale.cross_sell && parseFloat(data.sale.cross_sell) > 0) ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Cross Sell:</td><td style="padding:5px 0;">₱${parseFloat(data.sale.cross_sell).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>` : ''}
                            ${(data.sale.trade_in_voucher && parseFloat(data.sale.trade_in_voucher) > 0) ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Trade-In Voucher:</td><td style="padding:5px 0;">₱${parseFloat(data.sale.trade_in_voucher).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>` : ''}
                            <tr><td style="padding:5px 10px 5px 0; font-weight:700; color:#1E455D;">Total:</td><td style="padding:5px 0; font-weight:700; color:#1E455D;">₱${parseFloat(data.sale.titu_voucher_total).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>
                            ` : ''}
                        </table>
                    </div>
                ` : '';

                const promoCardHTML = showPromoDetails ? `
                    <div style="border:2px solid #acacacff; border-radius:8px; padding:15px; background:#f9f9f9;">
                        <h3 style="margin:0 0 12px 0; color:#1E455D; font-size:16px;">Promo Details</h3>
                        <table style="width:100%; font-size:14px;">
                            <tr><td colspan="2" style="padding:5px 10px 5px 0; font-weight:600; font-size:15px; color:#1E455D; border-bottom:1px solid #ddd; padding-bottom:10px;">Promo Used:</td></tr>
                            <tr><td style="padding:5px 10px 5px 0; font-weight:600; width:180px;">Promo Name:</td><td style="padding:5px 0;">${data.promo_details.promo_name}</td></tr>
                            ${(data.sale.promo_usage_number != null && data.promo_details.usage_limit != null) ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Promo Use:</td><td style="padding:5px 0; font-weight:700; color:#1E455D;">${data.sale.promo_usage_number} / ${data.promo_details.usage_limit}</td></tr>` : (data.sale.promo_usage_number != null ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Promo Use:</td><td style="padding:5px 0; font-weight:700; color:#1E455D;">#${data.sale.promo_usage_number}</td></tr>` : '')}
                            ${data.promo_details.motor_model ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Item Model:</td><td style="padding:5px 0;">${data.promo_details.motor_model}</td></tr>` : ''}
                            ${data.promo_details.brand ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Brand:</td><td style="padding:5px 0;">${data.promo_details.brand}</td></tr>` : ''}
                            ${data.promo_details.discount_type ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Discount Type:</td><td style="padding:5px 0;">${data.promo_details.discount_type}</td></tr>` : ''}
                            ${(data.promo_details.discount_value && parseFloat(data.promo_details.discount_value) > 0) ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Discount Value:</td><td style="padding:5px 0;">₱${parseFloat(data.promo_details.discount_value).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>` : ''}
                            ${data.promo_details.free_item ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Used Promo Item:</td><td style="padding:5px 0;">${data.promo_details.free_item}</td></tr>` : ''}
                            ${data.promo_details.start_date && data.promo_details.end_date ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Promo Period:</td><td style="padding:5px 0;">${data.promo_details.start_date} to ${data.promo_details.end_date}</td></tr>` : ''}
                            
                            ${(data.promo_items && data.promo_items.length > 0) ? `
                            <tr><td colspan="2" style="padding:15px 10px 5px 0; font-weight:600; font-size:15px; color:#1E455D; border-top:1px solid #ddd; margin-top:10px;">Promo Items:</td></tr>
                            ${data.promo_items.map((item, index) => `
                                <tr><td colspan="2" style="padding:5px 10px 5px 0; font-weight:600; color:#555;">Item ${index + 1}:</td></tr>
                                ${item.promo_item ? `<tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Used Promo Item:</td><td style="padding:5px 0;">${item.promo_item}</td></tr>` : ''}
                                ${item.motor_model ? `<tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Item Model:</td><td style="padding:5px 0;">${item.motor_model}</td></tr>` : ''}
                                ${item.discount_type ? `<tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Discount Type:</td><td style="padding:5px 0;">${item.discount_type}</td></tr>` : ''}
                                ${(item.discount_value && parseFloat(item.discount_value) > 0) ? `<tr><td style="padding:5px 10px 5px 20px; font-weight:500;">Discount Value:</td><td style="padding:5px 0;">₱${parseFloat(item.discount_value).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>` : ''}
                            `).join('')}
                            ` : ''}
                            
                            <tr><td style="padding:10px 0 5px 0; font-weight:700; color:#1E455D;">Total Items:</td><td style="padding:10px 0 5px 0; font-weight:700; color:#1E455D;">${data.promo_items ? data.promo_items.length : 0}</td></tr>
                        </table>
                    </div>
                ` : '';

                const paymentCardHTML = `
                    <div style="border:2px solid #acacacff; border-radius:8px; padding:15px; background:#f9f9f9;">
                        <h3 style="margin:0 0 12px 0; color:#1E455D; font-size:16px;">Payment Information</h3>
                        <table style="width:100%; font-size:14px;">
                            ${paymentInfoHTML}
                        </table>
                    </div>
                `;

                // Build freebies card - mirrors the report breakdown panel:
                // each freebie shows as UNCLAIMED row (created_at) + CLAIMED row (claimed_at) if it was claimed
                const freebiesCardHTML = (data.freebies && data.freebies.length > 0) ? `
                    <div style="border:2px solid #acacacff; border-radius:8px; padding:15px; background:#f9f9f9; margin-bottom:20px;">
                        <h3 style="margin:0 0 12px 0; color:#1E455D; font-size:16px;">Unclaimed Freebies</h3>
                        <table style="width:100%; border-collapse:collapse; font-size:14px;">
                            <thead>
                                <tr style="background:#f5f5f5;">
                                    <th style="padding:10px; border:1px solid #acacacff; text-align:left;">Item Code</th>
                                    <th style="padding:10px; border:1px solid #acacacff; text-align:left;">Description</th>
                                    <th style="padding:10px; border:1px solid #acacacff; text-align:center;">Qty</th>
                                    <th style="padding:10px; border:1px solid #acacacff; text-align:center;">Status</th>
                                    <th style="padding:10px; border:1px solid #acacacff; text-align:left;">Note</th>
                                    <th style="padding:10px; border:1px solid #acacacff; text-align:left;">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.freebies.map(fb => {
                    const dateAdded = fb.created_at ? new Date(fb.created_at).toLocaleString() : '';
                    const hasClaimed = fb.claimed_at && fb.claimed_at !== '0000-00-00 00:00:00' && fb.claimed_at !== null && fb.claimed_at !== '';
                    const claimedDate = hasClaimed ? new Date(fb.claimed_at).toLocaleString() : '';
                    // Row 1: UNCLAIMED (always shown — this is when the freebie was added)
                    let rows = `<tr>
                                        <td style="padding:8px; border:1px solid #acacacff;">${fb.item_code || ''}</td>
                                        <td style="padding:8px; border:1px solid #acacacff;">${fb.item_description || ''}</td>
                                        <td style="padding:8px; border:1px solid #acacacff; text-align:center;">${fb.quantity || 0}</td>
                                        <td style="padding:8px; border:1px solid #acacacff; text-align:center;">
                                            <span style="color:#dc3545; font-weight:700;">UNCLAIMED</span>
                                        </td>
                                        <td style="padding:8px; border:1px solid #acacacff;">${fb.note || ''}</td>
                                        <td style="padding:8px; border:1px solid #acacacff;">${dateAdded}</td>
                                    </tr>`;
                    // Row 2: CLAIMED (only if the freebie was actually claimed — shows claimed_at date)
                    if (hasClaimed) {
                        rows += `<tr>
                                            <td style="padding:8px; border:1px solid #acacacff;">${fb.item_code || ''}</td>
                                            <td style="padding:8px; border:1px solid #acacacff;">${fb.item_description || ''}</td>
                                            <td style="padding:8px; border:1px solid #acacacff; text-align:center;">${fb.quantity || 0}</td>
                                            <td style="padding:8px; border:1px solid #acacacff; text-align:center;">
                                                <span style="color:#28a745; font-weight:700;">CLAIMED</span>
                                            </td>
                                            <td style="padding:8px; border:1px solid #acacacff;">${fb.note || ''}</td>
                                            <td style="padding:8px; border:1px solid #acacacff;">${claimedDate}</td>
                                        </tr>`;
                    }
                    return rows;
                }).join('')}
                            </tbody>
                        </table>
                    </div>
                ` : '';

                let bottomSectionHTML = '';
                if (hasTradeIn && showPromoDetails) {
                    bottomSectionHTML = `
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                            ${tradeInCardHTML}
                            ${promoCardHTML}
                        </div>
                        <div style="margin-top:20px;">
                            ${paymentCardHTML}
                        </div>
                    `;
                } else if (hasTradeIn) {
                    bottomSectionHTML = `
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                            ${tradeInCardHTML}
                            ${paymentCardHTML}
                        </div>
                    `;
                } else if (showPromoDetails) {
                    bottomSectionHTML = `
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                            ${promoCardHTML}
                            ${paymentCardHTML}
                        </div>
                    `;
                } else {
                    bottomSectionHTML = paymentCardHTML;
                }

                // Build modal content
                content.innerHTML = `
                    <div style="max-height:100vh; overflow-y:auto; padding:20px;">
                        <h2 style="margin:0 0 20px 0; color:#1a1a1a; font-size:20px; border-bottom:2px solid #acacacff; padding-bottom:10px;">
                            Invoice Details: ${invoiceNo}
                        </h2>
                        
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:25px;">
                            <div style="border:2px solid #acacacff; border-radius:8px; padding:15px; background:#f9f9f9;">
                                <h3 style="margin:0 0 12px 0; color:#1E455D; font-size:16px;">Customer Information</h3>
                                <table style="width:100%; font-size:14px;">
                                    <tr><td style="padding:5px 10px 5px 0; font-weight:600; width:140px;">Name:</td><td style="padding:5px 0;">${data.sale.first_name || ''} ${data.sale.last_name || ''}</td></tr>
                                    <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Address:</td><td style="padding:5px 0;">${data.sale.address || 'N/A'}</td></tr>
                                    <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Contact No:</td><td style="padding:5px 0;">${data.sale.contact_no || 'N/A'}</td></tr>
                                    <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Email:</td><td style="padding:5px 0;">${data.sale.email || 'N/A'}</td></tr>
                                </table>
                            </div>
                            
                            <div style="border:2px solid #acacacff; border-radius:8px; padding:15px; background:#f9f9f9;">
                                <h3 style="margin:0 0 12px 0; color:#1E455D; font-size:16px;">Sale Information</h3>
                                <table style="width:100%; font-size:14px;">
                                    <tr><td style="padding:5px 10px 5px 0; font-weight:600; width:140px;">Encoder:</td><td style="padding:5px 0;">${data.sale.encoder || 'N/A'}</td></tr>
                                    <tr><td style="padding:5px 10px 5px 0; font-weight:600; width:140px;">Assisted By:</td><td style="padding:5px 0;">${data.sale.assisted_by_brand ? data.sale.assisted_by_brand + ' - ' + (data.sale.assisted_by || 'N/A') : (data.sale.assisted_by || 'N/A')}</td></tr>
                                    <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Date Sold:</td><td style="padding:5px 0;">${new Date(data.sale.created_at).toLocaleString()}</td></tr>
                                    <tr><td style="padding:5px 10px 5px 0; font-weight:600;">Branch:</td><td style="padding:5px 0;">${data.sale.branch_code || 'N/A'}</td></tr>
                                    ${parseFloat(data.sale.points || 0) > 0 ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Points:</td><td style="padding:5px 0;">${parseFloat(data.sale.points).toLocaleString('en-US', { minimumFractionDigits: 0 })}</td></tr>` : ''}
                                    ${parseFloat(data.sale.commission || 0) > 0 ? `<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Commission:</td><td style="padding:5px 0;">₱${parseFloat(data.sale.commission).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td></tr>` : ''}
                                </table>
                            </div>
                        </div>

                        <div style="border:2px solid #acacacff; border-radius:8px; padding:15px; background:#f9f9f9; margin-bottom:20px;">
                            <h3 style="margin:0 0 12px 0; color:#1E455D; font-size:16px;">Items</h3>
                            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="padding:10px; border:1px solid #acacacff; text-align:left;">Item Code</th>
                                        <th style="padding:10px; border:1px solid #acacacff; text-align:left;">Description</th>
                                        <th style="padding:10px; border:1px solid #acacacff; text-align:center;">IMEI</th>
                                        <th style="padding:10px; border:1px solid #acacacff; text-align:center;">Qty</th>
                                        <th style="padding:10px; border:1px solid #acacacff; text-align:right;">SRP</th>
                                        <th style="padding:10px; border:1px solid #acacacff; text-align:right;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${itemsHTML}
                                </tbody>
                            </table>
                        </div>

                        ${freebiesCardHTML}

                        ${bottomSectionHTML}
                    </div>
                `;

                // Update cash amount if it's a filtered cash payment
                if (paymentMethodFilter && paymentMethodFilter.toLowerCase().includes('cash')) {
                    const cashAmountCell = document.getElementById('cashAmountCell');
                    if (cashAmountCell && overallAmount > 0) {
                        cashAmountCell.textContent = '₱' + overallAmount.toLocaleString('en-US', { minimumFractionDigits: 2 });
                    }
                }
            }

            modal.style.display = 'block';
        }

        function closeInvoiceModal() {
            document.getElementById('invoiceModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('invoiceModal');
            if (event.target === modal) {
                closeInvoiceModal();
            }
        }
    </script>

    <!-- Invoice Details Modal -->
    <div id="invoiceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Invoice Details</h2>
                <button class="modal-close" onclick="closeInvoiceModal()">&times;</button>
            </div>
            <div class="modal-body" id="invoiceModalContent">
                <div style="text-align:center; padding:40px;">
                    <p>Loading...</p>
                </div>
            </div>
        </div>
    </div>

</body>

</html>