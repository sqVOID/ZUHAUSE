<?php
require_once 'session_check.php';
include 'config.php';

// Get logged in user's branch code
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$branch_code = '000'; // Default
if (isset($_SESSION['user_branch'])) {
    $user_branch_name = $_SESSION['user_branch'];
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch_name'");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- <link rel="icon" type="image/svg+xml" href="Icon/imslogo.svg"> -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Pre-Order</title>
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

        /* Claim Pre-Order Content Styles */
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
            border: 1px solid #e0e0e0;
            padding: 20px;
            border-radius: 4px;
        }

        .search-invoice-box {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .search-invoice-box label {
            font-size: 14px;
            font-weight: bold;
            color: #000;
            white-space: nowrap;
            min-width: 120px;
        }

        .search-invoice-box input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
        }

        .search-invoice-box input:focus {
            outline: none;
            border-color: #2e7d32;
        }

        .btn-search {
            padding: 8px 25px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
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
        }

        .bottom-container {
            background: white;
            border: 1px solid #e0e0e0;
            padding: 20px;
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            gap: 10px;
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
            font-size: 13px;
            color: #333;
            width: auto;
            flex-shrink: 0;
        }

        .input-row input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
            width: 100%;
        }

        .input-row input:focus {
            outline: none;
            border-color: #2e7d32;
        }

        .btn-add {
            padding: 8px 40px;
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
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
        }

        .btn-delete-item:hover {
            background: #d32f2f;
        }

        .btn-save {
            padding: 10px 40px;
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            width: max-content;
        }

        .btn-save:hover {
            background-color: var(--color-gold-light);
        }

        .btn-payment {
            padding: 10px 40px;
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            width: max-content;
        }

        .btn-payment:hover {
            background-color: var(--color-gold-light);
        }

        .left-section-wrapper {
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
            max-width: 400px;
        }

        .footer-actions {
            display: flex;
            gap: 10px;
            width: 100%;
        }

        /* Totals Section Styles */
        .totals-section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-row label {
            font-weight: 600;
            font-size: 14px;
        }

        .total-row input {
            width: 150px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: right;
        }

        @media (max-width: 1024px) {

            .top-section-container,
            .inputs-grid {
                grid-template-columns: 1fr;
            }
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
            width: 90%;
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

        .search-results-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #ccc;
        }

        .search-results-table thead {
            background: #E1FFDE;
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

        /* Media Queries for Responsiveness */

        /* Unclaimed Freebies responsive styles */
        .unclaimed-freebies-container {
            overflow-x: auto;
        }

        .btn-add-unclaimed-freebies {
            width: auto !important;
        }

        .unclaimed-freebie-name-input,
        .unclaimed-freebie-qty-input,
        .unclaimed-freebie-note-input {
            font-size: 14px;
            padding: 8px;
        }

        /* Large Desktop & Laptop (max-width: 1640px) */
        @media (max-width: 1640px) {
            .top-section-container {
                gap: 15px;
            }

            .inputs-grid {
                gap: 30px;
            }

            .btn-search,
            .btn-add {
                padding: 8px 20px;
            }
        }

        /* Medium Desktop (max-width: 1366px) */
        @media (max-width: 1366px) {
            .top-section-container {
                grid-template-columns: 1fr;
            }

            .inputs-grid {
                grid-template-columns: 1fr;
            }

            .search-invoice-box {
                flex-wrap: wrap;
            }

            .search-invoice-box input {
                flex: 1;
                min-width: 200px;
            }
        }

        /* Tablet & Smaller Desktop (max-width: 1024px) */
        @media (max-width: 1024px) {
            .top-section-container {
                grid-template-columns: 1fr;
            }

            .inputs-grid {
                grid-template-columns: 1fr;
            }

            .box-container {
                padding: 15px;
            }

            .bottom-container {
                padding: 15px;
            }

            .left-section-wrapper {
                max-width: 100%;
            }

            .totals-section {
                width: 100% !important;
            }

            .footer-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }

        /* Small Tablet (max-width: 960px) - Fix Price & Quantity Display */
        @media (max-width: 960px) {

            /* Fix the input rows to stack properly */
            .right-inputs>div[style*="display: flex"] {
                flex-direction: column !important;
                gap: 15px !important;
            }

            .right-inputs>div>div[style*="flex: 1"] {
                flex: none !important;
                width: 100% !important;
            }

            /* Fix quantity and price inputs */
            #quantityInput,
            #priceInput,
            #priceDropdown {
                font-size: 16px !important;
                padding: 12px !important;
                min-height: 46px !important;
                width: 100% !important;
            }

            /* Fix all input fields in the form */
            .input-row input,
            .input-row select {
                font-size: 16px !important;
                padding: 12px !important;
                min-height: 46px !important;
            }

            /* Make buttons full width */
            .btn-search,
            .btn-add {
                width: 100% !important;
                padding: 12px 20px !important;
                font-size: 16px !important;
            }

            /* Make the button row stack vertically */
            .right-inputs>div[style*="display: flex"][style*="gap: 15px"] {
                flex-direction: column !important;
            }

            /* Table scrolling */
            .items-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .items-table {
                min-width: 800px;
                zoom: 0.8;
            }

            .items-table th,
            .items-table td {
                font-size: 14px;
                padding: 10px 8px;
            }

            /* Unclaimed Freebies responsive for tablets */
            .btn-add-unclaimed-freebies {
                width: 100% !important;
            }

            /* Make unclaimed freebies inputs larger on tablet */
            .unclaimed-freebie-name-input,
            .unclaimed-freebie-qty-input,
            .unclaimed-freebie-note-input {
                font-size: 16px !important;
                padding: 12px !important;
            }
        }

        /* Mobile Devices (max-width: 768px) */
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
                padding: 15px;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .content-header h2 {
                font-size: 18px;
            }

            .box-container {
                padding: 15px;
            }

            .bottom-container {
                padding: 15px;
            }

            .search-invoice-box {
                flex-direction: column;
                gap: 10px;
            }

            .search-invoice-box label {
                width: 100%;
            }

            .search-invoice-box input {
                width: 100%;
            }

            .btn-search {
                width: 100%;
            }

            .inputs-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .input-row {
                margin-bottom: 12px;
            }

            .input-row input {
                font-size: 16px !important;
                padding: 12px !important;
            }

            /* Stack all buttons */
            .btn-add,
            .btn-payment,
            .btn-save {
                width: 100%;
            }

            .footer-actions {
                flex-direction: column;
                gap: 10px;
            }

            /* Responsive table */
            .items-table-container {
                overflow-x: auto;
            }

            .items-table {
                min-width: 700px;
            }

            /* Totals section */
            .total-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .total-row input {
                width: 100%;
            }

            /* Modal adjustments */
            .modal-content {
                width: 95%;
                margin: 20px auto;
            }

            .modal-header {
                padding: 15px 20px;
                font-size: 18px;
            }

            .modal-body {
                padding: 15px;
            }

            .search-results-table {
                font-size: 12px;
            }

            .search-results-table th,
            .search-results-table td {
                padding: 8px 6px;
            }

            /* Unclaimed Freebies Section - Stack vertically on mobile */
            .unclaimed-freebies-container {
                overflow-x: auto;
            }

            /* Make Add Unclaimed Freebies button full width on mobile */
            .btn-add-unclaimed-freebies {
                width: 100% !important;
            }

            /* Stack unclaimed freebies and totals section vertically */
            div[style*="display: grid"][style*="grid-template-columns: 2fr 1fr"] {
                display: block !important;
            }

            /* Make unclaimed freebies inputs larger on mobile */
            .unclaimed-freebie-name-input,
            .unclaimed-freebie-qty-input,
            .unclaimed-freebie-note-input {
                font-size: 16px !important;
                padding: 12px !important;
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

            .box-container,
            .bottom-container {
                padding: 12px;
            }

            .search-invoice-box label {
                font-size: 13px;
            }

            .input-row label {
                font-size: 12px;
            }

            .btn-search,
            .btn-add,
            .btn-payment,
            .btn-save {
                padding: 10px 16px;
                font-size: 14px;
            }

            .items-table {
                min-width: 600px;
                font-size: 12px;
            }

            .items-table th,
            .items-table td {
                padding: 8px 6px;
                font-size: 12px;
            }

            .totals-section {
                padding: 12px;
            }

            .total-row label {
                font-size: 13px;
            }

            .modal-content {
                width: 98%;
                max-height: 95vh;
            }

            .payment-modal-content {
                width: 95%;
            }
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Payment Modal Specific Styles */
        .payment-modal-content {
            max-width: 1000px;
            width: 90%;
            align-self: flex-start;
            margin-top: 5%;
            margin-bottom: 40px;
        }

        .payment-modal-body {
            padding: 20px 25px;
        }

        .payment-top-section {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
            flex-wrap: nowrap;
        }

        .payment-dropdown {
            padding: 8px 12px;
            border: 1px solid #333;
            border-radius: 4px;
            font-size: 13px;
            background: white;
            cursor: pointer;
            min-width: 120px;
            flex-shrink: 0;
        }

        .payment-dropdown:focus {
            outline: none;
            border-color: #2e7d32;
        }

        .payment-radio-group {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-shrink: 0;
        }

        .payment-radio-option {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
        }

        .payment-radio-option input[type="radio"] {
            cursor: pointer;
            width: 14px;
            height: 14px;
        }

        .payment-radio-option span {
            user-select: none;
        }

        .payment-dropdown.disabled-payment {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .payment-dropdown.disabled-payment:focus {
            border-color: #ddd;
        }

        .payment-radio-option.disabled-payment {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .payment-radio-option.disabled-payment input[type="radio"] {
            cursor: not-allowed;
        }

        .payment-radio-option.disabled-payment span {
            color: #999;
        }

        .home-credit-section,
        .credit-card-section,
        .debit-card-section,
        .qr-ph-section,
        .starpay-qr-section,
        .ewallet-section,
        .online-banking-section,
        .cash-section {
            background: white;
            padding-top: 0px;
            display: none;
        }

        .home-credit-section h3,
        .credit-card-section h3,
        .debit-card-section h3,
        .qr-ph-section h3,
        .starpay-qr-section h3,
        .ewallet-section h3,
        .online-banking-section h3,
        .cash-section h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #333;
        }

        .hc-grid-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .hc-form-group {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 15px;
            width: 100%;
        }

        .hc-form-group label {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 0;
            color: #333;
            white-space: nowrap;
            width: 160px;
            flex-shrink: 0;
        }

        .hc-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
            width: 100%;
        }

        .hc-input:focus {
            outline: none;
            border-color: #2e7d32;
        }

        .down-payment-group {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 15px;
            width: 100%;
        }

        .down-payment-group>label {
            font-size: 14px;
            font-weight: 700;
            color: #333;
            white-space: nowrap;
            width: 160px;
            flex-shrink: 0;
            margin-top: 10px;
        }

        .down-payment-box {
            padding: 15px 20px;
            border: 1px solid #bfbfbf;
            border-radius: 4px;
            background: #ffffff;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .payment-method-label {
            margin-bottom: 8px;
        }

        .payment-method-row {
            display: flex;
            gap: 0px;
            align-items: center;
            flex-wrap: nowrap;
            justify-content: flex-start;
        }

        .pm-label {
            font-weight: 700;
            font-size: 14px;
            margin-right: 5px;
            white-space: nowrap;
            color: #333;
        }

        .checkbox-inline {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 400;
            white-space: nowrap;
        }

        .checkbox-inline input[type="checkbox"] {
            cursor: pointer;
            width: 18px;
            height: 18px;
            margin: 0;
        }

        .enter-amount-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .enter-amount-row label {
            font-weight: 700;
            font-size: 14px;
            width: auto;
            margin-right: 5px;
            white-space: nowrap;
            color: #333;
        }

        .amount-input {
            padding: 8px 12px;
            border: 1px solid #bfbfbf;
            border-radius: 4px;
            font-size: 14px;
            width: 200px;
        }

        .amount-input:focus {
            outline: none;
            border-color: #2e7d32;
        }

        .total-section {
            display: none !important;
        }

        .payment-modal-body>div[class$="-section"]:not(.payment-top-section) {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed #ccc;
        }

        .payment-top-section {
            margin-bottom: 20px;
        }

        .payment-modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-top: 1px solid #eee;
            width: 100%;
            box-sizing: border-box;
            background: white;
            border-radius: 0 0 8px 8px;
        }

        .btn-save-modal {
            padding: 10px 40px;
            border: none;
            background: #1b5e20;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            min-width: 120px;
        }

        .btn-save-modal:hover {
            background-color: #144a18;
        }

        /* Payment Modal Responsive Styles */
        @media (max-width: 960px) {
            .payment-modal-content {
                width: 95%;
                margin-top: 2%;
            }

            .payment-top-section {
                flex-wrap: wrap;
            }

            .payment-dropdown {
                width: 100%;
                min-width: unset;
            }

            .payment-radio-group {
                width: 100%;
                justify-content: flex-start;
            }

            .hc-form-group {
                flex-direction: column;
                align-items: flex-start;
            }

            .hc-form-group label {
                width: 100%;
            }

            .down-payment-group {
                flex-direction: column;
            }

            .down-payment-group>label {
                width: 100%;
            }

            .payment-method-row {
                flex-wrap: wrap;
            }

            .checkbox-inline {
                flex: 1 1 45%;
            }

            .enter-amount-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .amount-input {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .payment-modal-content {
                width: 98%;
                margin-top: 5px;
            }

            .payment-modal-header {
                font-size: 18px;
                padding: 15px 20px;
            }

            .payment-modal-body {
                padding: 15px;
            }

            .payment-modal-footer {
                padding: 15px 20px;
                flex-direction: column;
                gap: 10px;
            }

            .btn-back-modal,
            .btn-save-modal {
                width: 100%;
            }

            .checkbox-inline {
                flex: 1 1 100%;
            }
        }

        min-width: 120px;
        }

        .btn-save-modal:hover {
            background-color: #144a18;
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
        <?php include '_header_user.php'; ?>
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Claim Pre-Order</h2>
        </div>

        <div class="top-section-container">
            <!-- Left Box -->
            <div class="box-container">
                <!-- New Invoice Number -->
                <div id="newInvoiceNumberSection" class="search-invoice-box"
                    style="margin-bottom: 20px; display: none;">
                    <label>New Invoice No:</label>
                    <input type="text" id="newInvoiceNumberInput" readonly
                        style="background-color: #f5f5f5; color: #999; cursor: not-allowed;">
                </div>
                <div class="search-invoice-box">
                    <label>Pre-Order No:</label>
                    <input type="text" id="preorderInput" placeholder="Enter Pre-Order Number">
                    <button class="btn-search" onclick="searchPreOrder()">Search</button>
                </div>
                <div class="info-panel">
                    <div class="info-panel-header">Pre-Order Items</div>
                    <div class="info-panel-body" id="preorderItemsPanel"></div>
                </div>
            </div>

            <!-- Right Box -->
            <div class="box-container">
                <div class="info-panel" style="height: 100%;">
                    <div class="info-panel-header">Customer Details</div>
                    <div class="info-panel-body" id="customerDetailsPanel"></div>
                </div>
            </div>
        </div>

        <div class="bottom-container">
            <div class="inputs-grid">
                <!-- Middle Section: Left Inputs -->
                <div class="left-inputs">
                    <div class="input-row">
                        <label>Item Code:</label>
                        <input type="text" id="itemCodeInput">
                    </div>
                    <div class="input-row">
                        <label>Description:</label>
                        <input type="text" id="descriptionInput" readonly
                            style="background-color: #ffffffff; cursor: not-allowed; color: #333;">
                    </div>
                    <input type="hidden" id="familyCodeInput">
                </div>
                <!-- Middle Section: Right Inputs -->
                <div class="right-inputs">
                    <div class="input-row">
                        <label>IMEI:</label>
                        <input type="text" id="imeiInput">
                    </div>
                    <div style="display: flex; gap: 15px; align-items: flex-end; margin-bottom: 15px;">
                        <div style="display: flex; flex-direction: column; gap: 8px; flex: 1;">
                            <label style="font-size: 13px; color: #333;">Quantity:</label>
                            <input type="number" id="quantityInput"
                                style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; width: 100%;"
                                min="1" placeholder="0">
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px; flex: 1;">
                            <label style="font-size: 13px; color: #333;">Price:</label>
                            <input type="text" id="priceInput" placeholder="" readonly
                                style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; width: 100%; background-color: #ffffff; color: #333;">
                        </div>
                        <button class="btn-search" onclick="searchItemCode()"
                            style="flex-shrink: 0; white-space: nowrap;">Search</button>
                        <button class="btn-add" onclick="addClaimItem()"
                            style="flex-shrink: 0; white-space: nowrap;">Add</button>
                    </div>
                </div>
            </div>

            <!-- Table Section -->
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
                    <tbody id="claimItemsTable">
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Unclaimed Freebies Table Section -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Unclaimed Freebies Table Container (Left) -->
                <div class="unclaimed-freebies-container"
                    style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #ccc;">
                    <button type="button" class="btn-add-unclaimed-freebies"
                        style="margin-bottom: 15px; width: auto; padding: 10px 30px; background-color: var(--color-gold); color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">Add
                        Unclaimed Freebies</button>
                    <table class="items-table">
                        <thead>
                            <tr style="background: var(--color-gold-pale);">
                                <th style="width: 40%; border: 1px solid #ccc;">Unclaimed Freebies</th>
                                <th style="width: 15%; border: 1px solid #ccc;">Quantity</th>
                                <th style="width: 35%; border: 1px solid #ccc;">Note</th>
                                <th style="width: 10%; border: 1px solid #ccc;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="unclaimedFreebiesTableBody">
                            <!-- Empty state - will show message until user adds freebies -->
                            <tr id="no-unclaimed-freebies-row">
                                <td colspan="4" style="text-align:center; padding: 20px;">Please click Add Unclaimed
                                    Freebies</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Totals Section Container (Right) -->
                <div class="totals-section" style="width: 100%;">
                    <div class="total-row">
                        <label>Total QTY:</label>
                        <input type="number" id="totalQty" value="0" readonly>
                    </div>
                    <div class="total-row">
                        <label>Discount:</label>
                        <input type="text" id="discountField" value="0" style="background-color: #e0e0e0;" readonly>
                    </div>
                    <div class="total-row">
                        <label>Total:</label>
                        <input type="text" id="totalAmount" value="₱0.00" readonly>
                    </div>
                </div>
            </div>

            <!-- Footer Actions Section (Below the grid) -->
            <div class="footer-actions"
                style="width: 100%; justify-content: space-between; padding: 20px; background: white; margin-top: 20px; border-radius: 8px; border: 1px solid #ccc;">
                <div class="footer-left-group" style="display: flex; align-items: center; gap: 20px;">
                    <!-- Empty for now, can add points/commission later -->
                </div>
                <div class="footer-right-group" style="display: flex; align-items: center; gap: 15px;">
                    <button class="btn-payment" onclick="openPaymentModal()">PAYMENT</button>
                    <button class="btn-save" onclick="saveClaimPreOrder()">SAVE</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Item Modal -->
    <div id="searchItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Search Items
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

    <!-- Unclaimed Freebies Search Modal -->
    <div id="searchUnclaimedFreebieModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Search Unclaimed Freebies (Non-Serialized Items Only)
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 15px;">
                    <input type="text" id="unclaimedFreebieSearchInput" placeholder="Enter item code..."
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                <table class="search-results-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Item Code</th>
                            <th style="width: 50%;">Item Description</th>
                            <th style="width: 20%; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="unclaimedFreebieSearchResultsBody">
                        <!-- Results will be injected here -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-back-modal" onclick="closeUnclaimedFreebieSearchModal()">Back</button>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content payment-modal-content">
            <div class="modal-header">
                Choose your Payment
            </div>
            <div class="modal-body payment-modal-body">
                <!-- Top Section: Dropdowns and Radio Buttons -->
                <div class="payment-top-section">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label class="payment-radio-option" style="margin: 0;">
                            <input type="checkbox" name="payment_method" value="payment_partners"
                                id="chkPaymentPartners">
                        </label>
                        <select class="payment-dropdown" id="paymentPartnersDropdown">
                            <option value="">Select Payment Partners</option>
                            <option value="partner2">Home Credit</option>
                            <option value="partner5">Salmon</option>
                            <option value="partner6">Samsung Finances</option>
                            <option value="partner7">Payjoy</option>
                            <option value="partner8">Billease</option>
                            <option value="partner9">Paymongo</option>
                            <option value="partner10">Skyro</option>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label class="payment-radio-option" style="margin: 0;">
                            <input type="checkbox" name="payment_method" value="card_payment" id="chkCardPayment">
                        </label>
                        <select class="payment-dropdown" id="cardPaymentDropdown">
                            <option value="">Select Card Payment</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                        </select>
                    </div>


                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label class="payment-radio-option" style="margin: 0;">
                            <input type="checkbox" name="payment_method" value="qr" id="chkQR">
                        </label>
                        <select class="payment-dropdown" id="qrDropdown">
                            <option value="">Select QR</option>
                            <option value="qr_ph">QR PH</option>
                            <option value="starpay_qr">Starpay QR</option>
                        </select>
                    </div>

                    <div class="payment-radio-group" style="flex-direction: row; gap: 15px; margin-left: 10px;">
                        <label class="payment-radio-option">
                            <input type="checkbox" name="payment_method" value="online_banking">
                            <span>Online Banking</span>
                        </label>
                        <label class="payment-radio-option">
                            <input type="checkbox" name="payment_method" value="ewallet">
                            <span>E-Wallet</span>
                        </label>
                        <label class="payment-radio-option">
                            <input type="checkbox" name="payment_method" value="cash">
                            <span>Cash</span>
                        </label>
                    </div>
                </div>

                <!-- Home Credit Section -->
                <div class="home-credit-section">
                    <h3 id="paymentPartnerTitle">Home Credit</h3>

                    <div class="hc-grid-container">
                        <!-- Unit selector: shown when 2+ items, auto-filled when 1 item -->
                        <div class="hc-form-group unit-selector-row" style="display:none;"></div>

                        <div class="hc-form-group">
                            <label>Loan Type:</label>
                            <select class="hc-input">
                                <option value=""></option>
                                <option value="installment_loan">Installment Loan</option>
                                <option value="0_installment">0% Installment</option>
                                <option value="promo_installment">Promo Installment</option>
                                <option value="standard_installment">Standard Installment</option>
                                <option value="cash_loan">Cash Loan</option>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>Loan Terms:</label>
                            <select class="hc-input">
                                <option value=""></option>
                                <option value="3months">3 Months</option>
                                <option value="6months">6 Months</option>
                                <option value="9months">9 Months</option>
                                <option value="12months">12 Months</option>
                                <option value="15months">15 Months</option>
                                <option value="18months">18 Months</option>
                                <option value="24months">24 Months</option>
                                <option value="36months">36 Months</option>
                                <option value="48months">48 Months</option>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>Customer's Name:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Loan Number:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Total Loan Amount:</label>
                            <input type="text" class="hc-input" id="totalLoanAmount">
                        </div>

                        <div class="hc-form-group">
                            <label>Loan Balance:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group down-payment-group">
                            <label>Down payment:</label>
                            <div class="down-payment-box">
                                <div class="payment-method-label">
                                    <span class="pm-label">Payment Method:</span>
                                </div>
                                <div class="payment-method-row">
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="down_payment_method" value="cash"
                                            onchange="toggleDownPaymentReference()">
                                        1. Cash
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="down_payment_method" value="gcash"
                                            onchange="toggleDownPaymentReference()">
                                        2. G-Cash
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="down_payment_method" value="maya"
                                            onchange="toggleDownPaymentReference()">
                                        3. Maya
                                    </label>
                                </div>

                                <div class="reference-no-row hci-amount-row" id="dpCashRow"
                                    style="display: none; align-items: center; gap: 10px; margin-bottom: 15px;">
                                    <label
                                        style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">Cash
                                        Amount (DP):</label>
                                    <input type="text" class="amount-input" id="cash_down_payment_amount"
                                        oninput="formatInput(this)"
                                        style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                </div>

                                <div class="reference-no-row hci-amount-row" id="dpGcashRow"
                                    style="display: none; flex-direction: column; gap: 10px; margin-bottom: 15px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <label
                                            style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">G-Cash
                                            Reference No:</label>
                                        <input type="text" class="reference-input" id="gcash_down_payment_reference"
                                            name="gcash_down_payment_reference"
                                            style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <label
                                            style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">G-Cash
                                            Amount (DP):</label>
                                        <input type="text" class="amount-input" id="gcash_down_payment_amount"
                                            oninput="formatInput(this)"
                                            style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                    </div>
                                </div>

                                <div class="reference-no-row hci-amount-row" id="dpMayaRow"
                                    style="display: none; flex-direction: column; gap: 10px; margin-bottom: 15px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <label
                                            style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">Maya
                                            Reference No:</label>
                                        <input type="text" class="reference-input" id="maya_down_payment_reference"
                                            name="maya_down_payment_reference"
                                            style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <label
                                            style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">Maya
                                            Amount (DP):</label>
                                        <input type="text" class="amount-input" id="maya_down_payment_amount"
                                            oninput="formatInput(this)"
                                            style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                    </div>
                                </div>

                            </div>
                        </div>
                        <script>
                            function toggleDownPaymentReference() {
                                const cashCb = document.querySelector('input[name="down_payment_method"][value="cash"]');
                                const gcashCb = document.querySelector('input[name="down_payment_method"][value="gcash"]');
                                const mayaCb = document.querySelector('input[name="down_payment_method"][value="maya"]');

                                const cashRow = document.getElementById('dpCashRow');
                                const gcashRow = document.getElementById('dpGcashRow');
                                const mayaRow = document.getElementById('dpMayaRow');

                                if (cashCb && cashCb.checked) {
                                    cashRow.style.display = 'flex';
                                } else if (cashRow) {
                                    cashRow.style.display = 'none';
                                    const cInput = document.getElementById('cash_down_payment_amount');
                                    if (cInput) { cInput.value = ''; cInput.dispatchEvent(new Event('input', { bubbles: true })); }
                                }

                                if (gcashCb && gcashCb.checked) {
                                    gcashRow.style.display = 'flex';
                                } else if (gcashRow) {
                                    gcashRow.style.display = 'none';
                                    const gRef = document.getElementById('gcash_down_payment_reference');
                                    const gInput = document.getElementById('gcash_down_payment_amount');
                                    if (gRef) gRef.value = '';
                                    if (gInput) { gInput.value = ''; gInput.dispatchEvent(new Event('input', { bubbles: true })); }
                                }

                                if (mayaCb && mayaCb.checked) {
                                    mayaRow.style.display = 'flex';
                                } else if (mayaRow) {
                                    mayaRow.style.display = 'none';
                                    const mRef = document.getElementById('maya_down_payment_reference');
                                    const mInput = document.getElementById('maya_down_payment_amount');
                                    if (mRef) mRef.value = '';
                                    if (mInput) { mInput.value = ''; mInput.dispatchEvent(new Event('input', { bubbles: true })); }
                                }
                            }
                        </script>
                    </div>

                    <div class="total-section">
                        <label>Total:</label>
                        <input type="text" class="total-input" readonly>
                    </div>
                </div>

                <!-- Credit Card Section -->
                <div class="credit-card-section">
                    <h3>Credit Card</h3>

                    <div class="hc-grid-container">
                        <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                        <div class="hc-form-group">
                            <label>Terminal Issuer:</label>
                            <select class="hc-input" id="ccTerminalIssuer" onchange="filterTerminalIds('cc')">
                                <option value="">Select Terminal Issuer</option>
                                <?php
                                if ($terminal_issuers_result && $terminal_issuers_result->num_rows > 0) {
                                    $terminal_issuers_result->data_seek(0);
                                    while ($ti_row = $terminal_issuers_result->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($ti_row['bank_name'], ENT_QUOTES) . "'>" . htmlspecialchars($ti_row['bank_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>Terminal ID:</label>
                            <select class="hc-input" id="ccTerminalId">
                                <option value="">Select Terminal ID</option>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>Bank:</label>
                            <select class="hc-input" id="creditCardBankDropdown">
                                <option value="">Select Bank</option>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>Terms:</label>
                            <select class="hc-input" id="creditCardTermsDropdown">
                                <option value="">Select Terms</option>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>MID:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Card No:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Approval Code:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Batch:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Amount:</label>
                            <input type="text" class="hc-input" id="creditCardAmount">
                        </div>
                    </div>

                    <div class="total-section">
                        <label>Total:</label>
                        <input type="text" class="total-input" readonly>
                    </div>
                </div>

                <!-- Debit Card Section -->
                <div class="debit-card-section">
                    <h3>Debit Card</h3>

                    <div class="hc-grid-container">
                        <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                        <div class="hc-form-group">
                            <label>Terminal Issuer:</label>
                            <select class="hc-input" id="dcTerminalIssuer" onchange="filterTerminalIds('dc')">
                                <option value="">Select Terminal Issuer</option>
                                <?php
                                if ($terminal_issuers_result && $terminal_issuers_result->num_rows > 0) {
                                    $terminal_issuers_result->data_seek(0);
                                    while ($ti_row = $terminal_issuers_result->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($ti_row['bank_name'], ENT_QUOTES) . "'>" . htmlspecialchars($ti_row['bank_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>Terminal ID:</label>
                            <select class="hc-input" id="dcTerminalId">
                                <option value="">Select Terminal ID</option>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>Bank:</label>
                            <select class="hc-input" id="debitCardBankDropdown">
                                <option value="">Select Bank</option>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>Terms:</label>
                            <select class="hc-input" id="debitCardTermsDropdown">
                                <option value="">Select Terms</option>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>MID:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Card No:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Approval Code:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Batch:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Amount:</label>
                            <input type="text" class="hc-input" id="debitCardAmount">
                        </div>
                    </div>

                    <div class="total-section">
                        <label>Total:</label>
                        <input type="text" class="total-input" readonly>
                    </div>
                </div>

                <!-- QR PH Section -->
                <div class="qr-ph-section">
                    <h3>QR PH</h3>

                    <div class="hc-grid-container">
                        <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                        <div class="hc-form-group">
                            <label>Bank:</label>
                            <select class="hc-input">
                                <option value="">Select Bank</option>
                                <option value="BDO">BDO</option>
                                <option value="Metrobank">Metrobank</option>
                                <option value="PNB">Philippine National Bank</option>
                                <option value="EastWest Bank">EastWest Bank</option>
                                <option value="RCBC">RCBC</option>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>Customer's Name:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Reference No:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Amount:</label>
                            <input type="text" class="hc-input">
                        </div>
                    </div>

                    <div class="total-section">
                        <label>Total:</label>
                        <input type="text" class="total-input" readonly>
                    </div>
                </div>

                <!-- Starpay QR Section -->
                <div class="starpay-qr-section">
                    <h3>Starpay QR</h3>

                    <div class="hc-grid-container">
                        <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                        <div class="hc-form-group">
                            <label>Bank:</label>
                            <select class="hc-input">
                                <option value="">Select Bank</option>
                                <option value="BDO">BDO</option>
                                <option value="Metrobank">Metrobank</option>
                                <option value="PNB">Philippine National Bank</option>
                                <option value="EastWest Bank">EastWest Bank</option>
                                <option value="RCBC">RCBC</option>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>Customer's Name:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Reference No:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Amount:</label>
                            <input type="text" class="hc-input">
                        </div>
                    </div>

                    <div class="total-section">
                        <label>Total:</label>
                        <input type="text" class="total-input" readonly>
                    </div>
                </div>

                <!-- E-Wallet Section -->
                <div class="ewallet-section">
                    <h3>E-Wallet</h3>

                    <div class="hc-grid-container">
                        <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                        <div class="hc-form-group">
                            <label>E-Wallet:</label>
                            <select class="hc-input">
                                <option value="">Select E-Wallet</option>
                                <option value="gcash">GCash</option>
                                <option value="maya">Maya</option>
                                <option value="paymaya">PayMaya</option>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>Customer's Name:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Reference No:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Amount:</label>
                            <input type="text" class="hc-input">
                        </div>
                    </div>

                    <div class="total-section">
                        <label>Total:</label>
                        <input type="text" class="total-input" readonly>
                    </div>
                </div>

                <!-- Online Banking Section -->
                <div class="online-banking-section">
                    <h3>Online Banking</h3>

                    <div class="hc-grid-container">
                        <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                        <div class="hc-form-group">
                            <label>Bank:</label>
                            <select class="hc-input">
                                <option value="">Select Bank</option>
                                <option value="BDO">BDO</option>
                                <option value="Metrobank">Metrobank</option>
                                <option value="PNB">Philippine National Bank</option>
                                <option value="EastWest Bank">EastWest Bank</option>
                                <option value="RCBC">RCBC</option>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>Reference No:</label>
                            <input type="text" class="hc-input">
                        </div>

                        <div class="hc-form-group">
                            <label>Amount:</label>
                            <input type="text" class="hc-input">
                        </div>
                    </div>

                    <div class="total-section">
                        <label>Total:</label>
                        <input type="text" class="total-input" readonly>
                    </div>
                </div>

                <!-- Cash Section -->
                <div class="cash-section">
                    <h3>Cash</h3>

                    <div class="hc-grid-container">
                        <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                        <div class="hc-form-group">
                            <label>Amount:</label>
                            <input type="text" class="hc-input">
                        </div>
                    </div>

                    <div class="total-section">
                        <label>Total:</label>
                        <input type="text" class="total-input" readonly>
                    </div>
                </div>

                <!-- Global Total -->
                <div class="global-totals-wrapper"
                    style="display: flex; flex-direction: column; align-items: flex-end; padding: 15px 0; border-top: 1px solid #ddd; margin-top: 10px; gap: 10px;">
                    <div class="global-total-due"
                        style="display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: bold;">
                        <label>Total Amount Due:</label>
                        <input type="text" id="globalTotalDueInput" readonly
                            style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; width: 150px; background-color: #f5f5f5; font-weight: bold; text-align: right;">
                    </div>
                    <div class="global-total-section"
                        style="display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: bold;">
                        <label>Total Payment:</label>
                        <input type="text" id="globalTotalInput" readonly
                            style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; width: 150px; background-color: #f5f5f5; font-weight: bold; text-align: right;">
                    </div>
                </div>
                <!-- Payment Breakdown banner -->
                <div id="paymentBreakdownBanner"
                    style="display:none; margin: 0 0 15px 0; padding: 12px 16px; background-color: #ffffffff; border-left: 4px solid #16a34a; border-right:1px solid #c9c9c9ff; border-top:1px solid #c9c9c9ff; border-bottom:1px solid #c9c9c9ff; border-radius: 4px;  box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-family: system-ui, -apple-system, sans-serif;">
                </div>

                <!-- Inline payment error banner -->
                <div id="paymentErrorBanner"
                    style="display:none; margin: 0 0 15px 0; padding: 12px 16px; background-color: #fef2f2; border-left: 4px solid #ef4444; border-right:1px solid #c9c9c9ff; border-top:1px solid #c9c9c9ff; border-bottom:1px solid #c9c9c9ff; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-family: system-ui, -apple-system, sans-serif;">
                </div>
            </div>

            <div class="modal-footer payment-modal-footer">
                <button type="button" class="btn-back-modal" onclick="closePaymentModal()">Back</button>
                <button type="button" class="btn-save-modal" onclick="savePaymentData()">Save</button>
            </div>
        </div>
    </div>

    <script>
        function logToDebugConsole(message, type = 'info') {
            const consoleEl = document.getElementById('debugConsole');
            if (!consoleEl) return;
            const timestamp = new Date().toLocaleTimeString();
            let color = '#00ff00';
            if (type === 'error') color = '#ff3333';
            if (type === 'success') color = '#33ff33';
            if (type === 'warning') color = '#ffcc00';
            consoleEl.innerHTML += `<div style="color: ${color}; font-family: monospace;">[${timestamp}] ${message}</div>`;
            consoleEl.scrollTop = consoleEl.scrollHeight;
        }

        let currentPreOrderData = null;
        let claimItems = [];
        let currentSearchResults = [];
        let totalBalancePaid = 0;
        let preorderGrandTotal = 0;
        let hasUnpaidBalance = false; // Track if there was an unpaid balance when modal opened

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

        // Generate new invoice number
        function generateNewInvoiceNumber() {
            const branchCode = '<?php echo $branch_code; ?>';

            const newInvoiceInput = document.getElementById('newInvoiceNumberInput');
            if (!newInvoiceInput) return;

            console.log('Fetching invoice number for branch:', branchCode, 'page_type: salesentry');

            // Show loading state
            newInvoiceInput.value = 'Loading...';

            fetch('get_next_invoice_number.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_invoice_number&branch_code=${branchCode}&page_type=salesentry`
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Invoice number response:', data);
                    if (data.success && data.invoice_number) {
                        newInvoiceInput.value = data.invoice_number + '-PRE';
                        // Store booklet info for later use
                        newInvoiceInput.dataset.bookletId = data.booklet_id || '';
                        newInvoiceInput.dataset.bookletFormat = data.format || '';

                        // Check if it's using fallback
                        if (data.format === 'fallback') {
                            console.warn('⚠️ Using fallback invoice format. No active booklet found for branch:', branchCode);
                            console.warn('Message:', data.message);
                        } else {
                            console.log('✓ Using booklet invoice number:', data.invoice_number);
                        }
                    } else {
                        console.error('Failed to get invoice number:', data.message);
                        newInvoiceInput.value = 'Error: ' + (data.message || 'Unknown error');
                    }
                })
                .catch(error => {
                    console.error('Error fetching invoice number:', error);
                    newInvoiceInput.value = 'Error fetching invoice';
                });
        }

        // Add Enter key listener to Item Code input
        document.addEventListener('DOMContentLoaded', function () {
            // Generate new invoice number on page load
            generateNewInvoiceNumber();

            const itemCodeInput = document.getElementById('itemCodeInput');
            const imeiInput = document.getElementById('imeiInput');

            if (itemCodeInput) {
                itemCodeInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        searchItemCode();
                    }
                });
            }

            if (imeiInput) {
                imeiInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        searchByIMEI();
                    }
                });
            }

            // Price Dropdown Handler - Enable/Disable price field based on Auto/Custom selection
            const priceField = document.getElementById('priceInput');
            const priceDropdown = document.getElementById('priceDropdown');

            // Helper function to format number with commas
            function formatPriceWithCommas(value) {
                let num = value.replace(/[^\d.]/g, '');
                const parts = num.split('.');
                if (parts.length > 2) {
                    num = parts[0] + '.' + parts.slice(1).join('');
                }
                const [intPart, decPart] = num.split('.');
                const formattedInt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                return decPart !== undefined ? formattedInt + '.' + decPart : formattedInt;
            }

            // Price field is now simple auto field (no dropdown functionality)
            if (priceField) {
                // Format price with commas as user types
                priceField.addEventListener('input', function (e) {
                    const cursorPos = this.selectionStart;
                    const oldValue = this.value;
                    const oldLength = oldValue.length;
                    this.value = formatPriceWithCommas(this.value);
                    const newLength = this.value.length;
                    const diff = newLength - oldLength;
                    this.setSelectionRange(cursorPos + diff, cursorPos + diff);
                });

                // Prevent spacebar input
                priceField.addEventListener('keydown', function (e) {
                    if (e.key === ' ' || e.keyCode === 32) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });

        function searchPreOrder() {
            const preorderNo = document.getElementById('preorderInput').value.trim();

            if (!preorderNo) {
                alert('Please enter a pre-order number');
                return;
            }

            // Make AJAX request to search for pre-order
            fetch('search_preorder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ preorder_no: preorderNo })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentPreOrderData = data.preorder;
                        displayPreOrderItems(data.preorder.items);
                        displayCustomerDetails(data.preorder.customer);
                        logToDebugConsole(`Successfully loaded pre-order ${data.preorder.preorder_no} with ${data.preorder.items.length} items.`, 'success');
                        logToDebugConsole(`Pre-order items details: ${JSON.stringify(data.preorder.items)}`, 'info');
                    } else {
                        logToDebugConsole(`Pre-order search failed: ${data.message}`, 'error');
                        alert(data.message || 'Pre-order not found');
                        clearPanels();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error searching for pre-order');
                });
        }

        function displayPreOrderItems(items) {
            const panel = document.getElementById('preorderItemsPanel');
            if (!items || items.length === 0) {
                panel.innerHTML = '<p>No items found</p>';
                return;
            }

            let html = '<div style="font-size: 13px;">';
            let grandTotal = 0;
            let totalPaid = 0;

            // Display items
            items.forEach(item => {
                const itemTotal = (parseFloat(item.total_payment) || 0);
                const amountPaid = (parseFloat(item.amount_paid) || 0);
                grandTotal += itemTotal;
                totalPaid += amountPaid;

                const isPaid = item.status && item.status.toLowerCase() === 'paid';
                const remainingBalance = itemTotal - amountPaid;
                const paymentMethod = item.payment_method || 'N/A';

                html += `
                    <div style="margin-bottom: 10px; padding: 8px; border: 1px solid #eee; border-radius: 3px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <strong>${item.family_code || item.description || 'N/A'}</strong>
                            <span style="font-weight: bold; color: ${isPaid ? '#2e7d32' : '#d32f2f'};">
                                ${isPaid ? 'PAID' : 'UNPAID'}
                            </span>
                        </div>
                        <div style="color: #666; margin-bottom: 2px;">Qty: ${item.quantity || 0}</div>
                        <div style="color: #666; margin-bottom: 2px;">Payment: ₱${amountPaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                        <div style="color: #666; margin-bottom: 2px;">Payment Method: ${paymentMethod}</div>
                        ${!isPaid && remainingBalance > 0 ? `
                            <div style="color: #d32f2f; font-weight: bold; margin-top: 4px;">
                                Remaining Balance: ₱${remainingBalance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </div>
                        ` : ''}
                    </div>
                `;
            });

            // Calculate overall remaining balance
            const overallRemaining = grandTotal - totalPaid;

            // Display grand total
            html += `
                <div style="margin-top: 15px; padding: 10px; border-top: 2px solid #333; background-color: #f5f5f5;">
                    <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 14px; margin-bottom: 4px;">
                        <span>TOTAL:</span>
                        <span>₱${grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 14px; color: #2e7d32; margin-top: 6px;">
                        <span>PAYMENT:</span>
                        <span>₱${totalPaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                    </div>
                    ${overallRemaining > 0 ? `
                        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 14px; color: #d32f2f; margin-top: 6px;">
                            <span>REMAINING BALANCE:</span>
                            <span>₱${overallRemaining.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                        </div>
                    ` : ''}
                </div>
            `;

            // Display charges breakdown if available
            if (currentPreOrderData && currentPreOrderData.charges) {
                html += `
                    <div style="margin-top: 15px; padding: 10px; border-top: 1px solid #ddd;">
                        <div style="font-weight: bold; margin-bottom: 8px;">Charges Breakdown:</div>
                `;

                const charges = currentPreOrderData.charges;
                if (charges.subtotal !== undefined) {
                    html += `<div style="display: flex; justify-content: space-between; margin-bottom: 4px; color: #666;">
                        <span>Subtotal:</span>
                        <span>₱${parseFloat(charges.subtotal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                    </div>`;
                }
                if (charges.discount !== undefined && parseFloat(charges.discount) > 0) {
                    html += `<div style="display: flex; justify-content: space-between; margin-bottom: 4px; color: #666;">
                        <span>Discount:</span>
                        <span>-₱${parseFloat(charges.discount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                    </div>`;
                }
                if (charges.tax !== undefined && parseFloat(charges.tax) > 0) {
                    html += `<div style="display: flex; justify-content: space-between; margin-bottom: 4px; color: #666;">
                        <span>Tax:</span>
                        <span>₱${parseFloat(charges.tax).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                    </div>`;
                }
                if (charges.delivery_fee !== undefined && parseFloat(charges.delivery_fee) > 0) {
                    html += `<div style="display: flex; justify-content: space-between; margin-bottom: 4px; color: #666;">
                        <span>Delivery Fee:</span>
                        <span>₱${parseFloat(charges.delivery_fee).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                    </div>`;
                }
                if (charges.additional_charges !== undefined && parseFloat(charges.additional_charges) > 0) {
                    html += `<div style="display: flex; justify-content: space-between; margin-bottom: 4px; color: #666;">
                        <span>Additional Charges:</span>
                        <span>₱${parseFloat(charges.additional_charges).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                    </div>`;
                }

                html += `</div>`;
            }

            html += '</div>';
            panel.innerHTML = html;

            // Store totalPaid and grandTotal globally for use in payment modal
            totalBalancePaid = totalPaid;
            preorderGrandTotal = grandTotal;
        }

        function displayCustomerDetails(customer) {
            const panel = document.getElementById('customerDetailsPanel');
            if (!customer) {
                panel.innerHTML = '<p>No customer details found</p>';
                return;
            }

            // Get invoice number and date from the pre-order data
            const invoiceNumber = (currentPreOrderData && currentPreOrderData.preorder_no) || customer.invoice_number || 'N/A';
            let orderDate = (currentPreOrderData && currentPreOrderData.date) || (currentPreOrderData && currentPreOrderData.created_date) || customer.date || 'N/A';

            // Format date to show only date without time (YYYY-MM-DD or MM/DD/YYYY)
            if (orderDate !== 'N/A') {
                const dateObj = new Date(orderDate);
                if (!isNaN(dateObj.getTime())) {
                    // Format as YYYY-MM-DD
                    orderDate = dateObj.toLocaleDateString('en-CA'); // en-CA gives YYYY-MM-DD format
                }
            }

            // Build Payment Invoice Numbers HTML list from payment history
            let invoiceNumbersHtml = '';
            const paymentHistory = (currentPreOrderData && currentPreOrderData.payment_history) ? currentPreOrderData.payment_history : [];

            if (paymentHistory.length > 0) {
                paymentHistory.forEach((ph, idx) => {
                    const seq = ph.payment_sequence || (idx + 1);
                    const invNo = ph.invoice_no || invoiceNumber;
                    const amt = parseFloat(ph.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    invoiceNumbersHtml += `<div style="margin-bottom: 8px;"><strong>Invoice No ${seq}:</strong> ${invNo} (₱${amt})</div>`;
                });
            } else {
                invoiceNumbersHtml = `<div style="margin-bottom: 8px;"><strong>Invoice No:</strong> ${invoiceNumber}</div>`;
            }

            // Collect all unique invoice numbers from payment history
            let allInvoiceNumbers = [];
            if (paymentHistory.length > 0) {
                paymentHistory.forEach(ph => {
                    const invNo = ph.invoice_no || invoiceNumber;
                    if (invNo && !allInvoiceNumbers.includes(invNo)) {
                        allInvoiceNumbers.push(invNo);
                    }
                });
            }
            if (allInvoiceNumbers.length === 0) {
                allInvoiceNumbers.push(invoiceNumber);
            }

            // Check if preorder is fully paid
            const overallRemaining = (preorderGrandTotal > 0) ? (preorderGrandTotal - totalBalancePaid) : 0;
            const isFullyPaidPreorder = (currentPreOrderData && (currentPreOrderData.status === 'fully paid' || currentPreOrderData.status === 'claimed' || overallRemaining <= 0.01));

            // Hide or show the "New Invoice No" section based on payment status
            const newInvoiceSection = document.getElementById('newInvoiceNumberSection');
            if (newInvoiceSection) {
                if (isFullyPaidPreorder) {
                    // Hide the new invoice number section for fully paid pre-orders
                    newInvoiceSection.style.display = 'none';
                } else {
                    // Show the new invoice number section for unpaid pre-orders
                    newInvoiceSection.style.display = 'flex';
                }
            }

            // Update payment button text based on payment status
            const btnPayment = document.querySelector('.btn-payment');
            if (btnPayment) {
                if (isFullyPaidPreorder) {
                    // Get the payment methods from payment history
                    const paymentHistory = (currentPreOrderData && currentPreOrderData.payment_history) ? currentPreOrderData.payment_history : [];
                    if (paymentHistory.length > 0) {
                        // Collect unique payment methods
                        const paymentMethods = [...new Set(paymentHistory.map(ph => ph.payment_method).filter(pm => pm))];
                        if (paymentMethods.length > 0) {
                            btnPayment.innerText = 'Payment: ' + paymentMethods.join(', ');
                        } else {
                            btnPayment.innerText = 'Payment: Fully Paid';
                        }
                    } else {
                        btnPayment.innerText = 'Payment: Fully Paid';
                    }
                    btnPayment.style.backgroundColor = 'var(--color-green)';
                } else {
                    btnPayment.innerText = 'PAYMENT';
                    btnPayment.style.backgroundColor = 'var(--color-gold)';
                }
            }

            let invoiceLabel = 'New Invoice No (Claim):';
            let initialNewInvoiceDisplay = '';
            if (isFullyPaidPreorder) {
                invoiceLabel = 'Invoice No (Claim):';
                initialNewInvoiceDisplay = allInvoiceNumbers.join(', ');
            } else if (currentPreOrderData && currentPreOrderData.next_invoice_no) {
                invoiceLabel = 'New Invoice No (Claim):';
                initialNewInvoiceDisplay = currentPreOrderData.next_invoice_no;
            } else {
                invoiceLabel = 'New Invoice No (Claim):';
                initialNewInvoiceDisplay = 'Auto-generated after claim';
            }

            const html = `
                <div style="font-size: 13px;">
                    ${invoiceNumbersHtml}
                    <div style="margin-bottom: 8px;"><strong>Date:</strong> ${orderDate}</div>
                    <div style="margin-bottom: 8px;"><strong>Name:</strong> ${customer.name || 'N/A'}</div>
                    <div style="margin-bottom: 8px;"><strong>Phone:</strong> ${customer.phone || 'N/A'}</div>
                    <div style="margin-bottom: 8px;"><strong>Email:</strong> ${customer.email || 'N/A'}</div>
                    <div style="margin-bottom: 8px;"><strong>Address:</strong> ${customer.address || 'N/A'}</div>
                    <div style="margin-bottom: 8px;"><strong>Remarks:</strong> ${customer.remarks || 'N/A'}</div>
                </div>
                <div style="display: none;">
                    <span id="invoiceLabelText">${invoiceLabel}</span>
                    <span id="displayNewInvoiceNo">${initialNewInvoiceDisplay}</span>
                </div>
            `;
            panel.innerHTML = html;
        }

        function clearPanels() {
            document.getElementById('preorderItemsPanel').innerHTML = '';
            document.getElementById('customerDetailsPanel').innerHTML = '';
            currentPreOrderData = null;

            // Hide the new invoice number section when no pre-order is selected
            const newInvoiceSection = document.getElementById('newInvoiceNumberSection');
            if (newInvoiceSection) {
                newInvoiceSection.style.display = 'none';
            }

            // Reset payment button to default state
            const btnPayment = document.querySelector('.btn-payment');
            if (btnPayment) {
                btnPayment.innerText = 'PAYMENT';
                btnPayment.style.backgroundColor = 'var(--color-gold)';
            }
        }

        function addClaimItem() {
            const imei = document.getElementById('imeiInput').value.trim();
            const description = document.getElementById('descriptionInput').value.trim();
            const itemCode = document.getElementById('itemCodeInput').value.trim();
            const quantity = parseInt(document.getElementById('quantityInput').value) || 0;
            const priceFieldValue = document.getElementById('priceInput').value;
            const price = parseFloat(priceFieldValue.replace(/,/g, '')) || 0; // Remove commas before parsing

            if (!description) {
                alert('Please enter item description');
                return;
            }

            if (quantity <= 0) {
                alert('Please enter valid quantity');
                return;
            }

            // Check if current pre-order is fully paid
            if (currentPreOrderData) {
                const preorderStatus = (currentPreOrderData.status || '').toLowerCase();
                const remainingBal = preorderGrandTotal - totalBalancePaid;
                const isFullyPaidOrder = (remainingBal <= 0.01) || preorderStatus === 'fully paid' || preorderStatus === 'paid';

                if (isFullyPaidOrder) {
                    let preorderTotalQty = 0;
                    let preorderTotalAmount = 0;
                    if (currentPreOrderData.items && Array.isArray(currentPreOrderData.items)) {
                        currentPreOrderData.items.forEach(pi => {
                            preorderTotalQty += parseInt(pi.quantity) || 1;
                            preorderTotalAmount += parseFloat(pi.total_payment || pi.price) || 0;
                        });
                    }
                    if (preorderTotalQty <= 0) preorderTotalQty = parseInt(currentPreOrderData.total_qty) || 1;
                    if (preorderTotalAmount <= 0) preorderTotalAmount = parseFloat(currentPreOrderData.total_amount) || 0;

                    let currentClaimQty = 0;
                    let currentClaimTotal = 0;
                    claimItems.forEach(ci => {
                        currentClaimQty += parseInt(ci.quantity) || 1;
                        currentClaimTotal += (parseFloat(ci.price) * (parseInt(ci.quantity) || 1));
                    });

                    // Block if adding this item exceeds pre-ordered quantity or amount
                    const newItemTotal = price * quantity;
                    if ((currentClaimQty + quantity > preorderTotalQty) || (currentClaimTotal + newItemTotal > preorderTotalAmount + 0.01)) {
                        alert('This pre-order is already fully paid. You cannot add additional items to a fully paid pre-order. Please add additional items in Sales Entry.');
                        return;
                    }
                }
            }

            // CRITICAL FIX: For unserialized items (no IMEI), allow price = 0.00
            // For serialized items (with IMEI), price must be > 0
            if (imei && imei.trim() !== '') {
                // Serialized item - price must be greater than 0
                if (price <= 0) {
                    alert('Please enter valid price');
                    return;
                }
            } else {
                // Unserialized item - price can be 0 or greater, but must be a valid number
                if (isNaN(price) || price < 0) {
                    alert('Please enter valid price (0 or greater)');
                    return;
                }
            }

            // Check stock availability before adding (just like salesentry.php)
            const stockCheckUrl = `check_stock_availability.php?item_code=${encodeURIComponent(itemCode)}&imei=${encodeURIComponent(imei)}&qty=${quantity}&force_branch=true`;

            fetch(stockCheckUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const stockData = JSON.parse(text);

                        if (stockData.status === 'error' || !stockData.available) {
                            alert(stockData.message || 'This item is not available in stock!');

                            // Log debug info if available
                            if (stockData.debug) {
                                console.log('Debug info:', stockData.debug);
                            }
                            if (stockData.sql) {
                                console.log('SQL query:', stockData.sql);
                            }
                            return;
                        }

                        // Stock is available, proceed with adding the item
                        proceedWithAddingItem();
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response text:', text);
                        alert('Error parsing stock check response. Check console for details.');
                    }
                })
                .catch(error => {
                    console.error('Error checking stock:', error);
                    alert('Error checking stock availability: ' + error.message);
                });

            function proceedWithAddingItem() {
                // Check for duplicate IMEI in the table
                if (imei && imei.trim() !== '') {
                    const existingItems = claimItems.filter(item => item.imei === imei.trim());
                    if (existingItems.length > 0) {
                        alert('This IMEI/Serial Number (' + imei + ') has already been added to this claim!');
                        return;
                    }
                }

                const familyCode = document.getElementById('familyCodeInput').value.trim();

                logToDebugConsole(`proceedWithAddingItem: imei=${imei}, desc=${description}, familyCode=${familyCode}, itemCode=${itemCode}, quantity=${quantity}, price=${price}`, 'info');
                if (!familyCode) {
                    logToDebugConsole(`WARNING: familyCode is empty! This might cause preorder_items UPDATE to match 0 rows!`, 'warning');
                }

                const claimItem = {
                    id: Date.now(), // Simple ID for removal
                    imei: imei,
                    description: description,
                    family_code: familyCode,
                    itemCode: itemCode,
                    quantity: quantity,
                    price: price,
                    total: quantity * price
                };

                claimItems.push(claimItem);
                logToDebugConsole(`Current claimItems list: ${JSON.stringify(claimItems)}`, 'info');
                updateClaimItemsTable();
                clearInputs();
            }
        }

        function updateClaimItemsTable() {
            const tbody = document.getElementById('claimItemsTable');

            if (claimItems.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                `;
                updateTotals();
                return;
            }

            let html = '';
            claimItems.forEach(item => {
                html += `
                    <tr>
                        <td>${item.description}</td>
                        <td>${item.imei || ''}</td>
                        <td style="text-align: center;">${item.quantity}</td>
                        <td style="text-align: center;">₱${item.price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        <td style="text-align: center;">
                            <button onclick="removeClaimItem(${item.id})" class="btn-delete-item" title="Remove">
                                ✕
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
            updateTotals();
        }

        function updateTotals() {
            // Calculate total quantity
            const totalQty = claimItems.reduce((sum, item) => sum + item.quantity, 0);

            // Calculate total amount
            const totalAmount = claimItems.reduce((sum, item) => sum + item.total, 0);

            // Update the display fields
            document.getElementById('totalQty').value = totalQty;
            document.getElementById('totalAmount').value = '₱' + totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function removeClaimItem(itemId) {
            claimItems = claimItems.filter(item => item.id !== itemId);
            updateClaimItemsTable();
        }

        function clearInputs() {
            const imeiInput = document.getElementById('imeiInput');
            const priceInput = document.getElementById('priceInput');

            imeiInput.value = '';
            document.getElementById('descriptionInput').value = '';
            document.getElementById('itemCodeInput').value = '';
            document.getElementById('familyCodeInput').value = '';
            document.getElementById('quantityInput').value = '';
            priceInput.value = '';

            // Simple price field reset
            priceInput.setAttribute('readonly', 'readonly');
            priceInput.style.backgroundColor = '#ffffff';
            priceInput.style.color = '#333';
            priceInput.placeholder = '';

            // Re-enable IMEI field when clearing
            imeiInput.removeAttribute('readonly');
            imeiInput.style.backgroundColor = '#ffffff';
            imeiInput.style.cursor = 'text';
        }

        function searchItemCode() {
            // Check if pre-order has been searched first
            if (!currentPreOrderData) {
                alert('Please search for a Pre-Order number first before adding items.');
                return;
            }

            const itemCode = document.getElementById('itemCodeInput').value.trim();

            if (!itemCode) {
                alert('Please enter an item code to search');
                return;
            }

            // Extract family codes from pre-order items
            const familyCodes = currentPreOrderData.items ? currentPreOrderData.items.map(item => item.family_code).join(',') : '';

            // Fetch search results and display in modal
            fetch(`search_claim_preorder.php?term=${encodeURIComponent(itemCode)}&family_codes=${encodeURIComponent(familyCodes)}`)
                .then(response => response.json())
                .then(data => {
                    const resultsBody = document.getElementById('searchResultsBody');
                    const modal = document.getElementById('searchItemModal');
                    resultsBody.innerHTML = '';

                    if (data.status === 'success' && data.data && data.data.length > 0) {
                        currentSearchResults = data.data;

                        data.data.forEach((item, index) => {
                            const row = `
                                <tr>
                                    <td>${item.item_code || item.family_code || ''}</td>
                                    <td>${item.description || item.item_description || ''}</td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-select" onclick="selectItem(${index})">Select</button>
                                    </td>
                                </tr>
                            `;
                            resultsBody.insertAdjacentHTML('beforeend', row);
                        });

                        modal.style.display = 'flex';
                    } else {
                        resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">No items found matching the pre-order family codes</td></tr>';
                        modal.style.display = 'flex';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error searching for item');
                });
        }

        function searchByIMEI() {
            // Check if pre-order has been searched first
            if (!currentPreOrderData) {
                alert('Please search for a Pre-Order number first before adding items.');
                return;
            }

            const imei = document.getElementById('imeiInput').value.trim();

            if (!imei) {
                alert('Please enter an IMEI/Serial Number');
                return;
            }

            // Search for item by IMEI
            fetch(`search_imei_claimpreorder.php?imei=${encodeURIComponent(imei)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Check if the item's family code matches pre-order
                        const itemFamilyCode = data.data.family_code;
                        const preorderFamilyCodes = currentPreOrderData.items ? currentPreOrderData.items.map(item => item.family_code) : [];

                        logToDebugConsole(`IMEI Lookup: Found item_code=${data.data.item_code}, family_code=${itemFamilyCode}, desc=${data.data.description}`, 'info');

                        if (!preorderFamilyCodes.includes(itemFamilyCode)) {
                            logToDebugConsole(`IMEI Lookup validation FAILED: family_code=${itemFamilyCode} not in preorder family codes [${preorderFamilyCodes.join(', ')}]`, 'error');
                            alert('This item does not match any family code in the pre-order.');
                            document.getElementById('imeiInput').value = '';
                            return;
                        }

                        logToDebugConsole(`IMEI Lookup validation PASSED.`, 'success');

                        const priceInput = document.getElementById('priceInput');

                        // Populate fields with found data
                        document.getElementById('itemCodeInput').value = data.data.item_code || '';
                        document.getElementById('descriptionInput').value = data.data.description || '';
                        document.getElementById('familyCodeInput').value = itemFamilyCode || '';

                        // Store auto price and format with commas
                        const autoPrice = data.data.price || '';
                        priceInput.setAttribute('data-auto-price', autoPrice);
                        const formattedPrice = parseFloat(autoPrice).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        priceInput.value = formattedPrice;

                        document.getElementById('quantityInput').value = '1';

                        // Make price field readonly for serialized items (just like salesentry.php)
                        priceInput.setAttribute('readonly', 'readonly');
                        priceInput.style.backgroundColor = '#ffffff';
                        priceInput.style.color = '#333';
                        priceInput.style.cursor = 'default';

                        // Focus on quantity or add button
                        document.getElementById('quantityInput').focus();
                    } else {
                        alert(data.message || 'IMEI not found');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error searching for IMEI');
                });
        }

        function selectItem(index) {
            const item = currentSearchResults[index];
            const code = item.item_code || item.family_code || '';
            const description = item.description || item.item_description || '';
            const price = item.price || item.srp || '';

            // Check if item is serialized first
            if (code) {
                fetch(`check_serial_permission.php?item_code=${encodeURIComponent(code)}`)
                    .then(response => response.json())
                    .then(data => {
                        const imeiField = document.getElementById('imeiInput');
                        const priceInput = document.getElementById('priceInput');

                        if (data.status === 'success' && data.has_serial) {
                            logToDebugConsole(`Item ${code} is serialized. Manual IMEI lookup required.`, 'warning');
                            // Item is serialized - show alert and clear fields
                            alert('This item is serialized. Please enter the IMEI first to search for this item.');
                            document.getElementById('itemCodeInput').value = '';
                            document.getElementById('descriptionInput').value = '';
                            priceInput.value = '';
                            imeiField.value = '';

                            // Make IMEI field editable for manual entry
                            imeiField.removeAttribute('readonly');
                            imeiField.style.backgroundColor = '#ffffff';
                            imeiField.style.cursor = 'text';

                            // Re-enable price field when clearing
                            priceInput.removeAttribute('readonly');
                            priceInput.style.backgroundColor = '#ffffff';
                            priceInput.style.cursor = 'text';

                            closeSearchModal();
                            imeiField.focus();
                            return;
                        }

                        logToDebugConsole(`Selected non-serialized item: code=${code}, desc=${description}, family_code=${item.family_code}`, 'info');
                        // Item is not serialized - proceed normally
                        document.getElementById('itemCodeInput').value = code;
                        document.getElementById('descriptionInput').value = description;
                        // Store family_code: for non-serialized items it comes from the search result
                        document.getElementById('familyCodeInput').value = item.family_code || '';

                        // Store auto price and format with commas
                        priceInput.setAttribute('data-auto-price', price);
                        const formattedPrice = parseFloat(price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        priceInput.value = formattedPrice;

                        // Lock IMEI field for non-serialized items
                        imeiField.setAttribute('readonly', 'readonly');
                        imeiField.style.backgroundColor = '#f5f5f5';
                        imeiField.style.cursor = 'not-allowed';
                        imeiField.value = '';

                        // Make price field editable for non-serialized items
                        priceInput.removeAttribute('readonly');
                        priceInput.style.backgroundColor = '#ffffff';
                        priceInput.style.cursor = 'text';

                        closeSearchModal();

                        // Focus on quantity input
                        document.getElementById('quantityInput').focus();
                    })
                    .catch(error => {
                        console.error('Error checking serial status:', error);
                        alert('Error checking item serial status');
                    });
            }
        }

        function closeSearchModal() {
            const modal = document.getElementById('searchItemModal');
            modal.style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const searchModal = document.getElementById('searchItemModal');
            const paymentModal = document.getElementById('paymentModal');
            if (event.target == searchModal) {
                closeSearchModal();
            }
            if (event.target == paymentModal) {
                paymentModal.style.display = 'none';
            }
        }

        function saveClaimPreOrder() {
            if (!currentPreOrderData) {
                alert('Please search and select a pre-order first');
                return;
            }

            if (claimItems.length === 0) {
                alert('Please add at least one claim item');
                return;
            }

            // Collect unclaimed freebies data
            const unclaimedFreebies = [];
            const unclaimedFreebiesRows = document.querySelectorAll('#unclaimedFreebiesTableBody tr:not(#no-unclaimed-freebies-row)');

            unclaimedFreebiesRows.forEach(row => {
                const nameInput = row.querySelector('.unclaimed-freebie-name-input');
                const qtyInput = row.querySelector('.unclaimed-freebie-qty-input');
                const noteInput = row.querySelector('.unclaimed-freebie-note-input');

                if (nameInput && nameInput.value.trim() !== '') {
                    const itemCode = nameInput.getAttribute('data-item-code') || '';
                    const description = nameInput.value.trim();
                    const quantity = parseInt(qtyInput.value) || 0;
                    const note = noteInput.value.trim();

                    if (quantity > 0) {
                        unclaimedFreebies.push({
                            item_code: itemCode,
                            description: description,
                            quantity: quantity,
                            note: note
                        });
                    }
                }
            });

            // For fully paid pre-orders, payment is already done
            // Use existing payment data from the pre-order
            const paymentDataToSend = window.paymentData || {
                payment_type: 'fully paid',
                status: 'paid',
                amount: currentPreOrderData.items[0]?.total_payment || 0
            };

            // Get invoice number from modal input if available
            const newInvoiceInput = document.getElementById('newInvoiceNumberInput');
            const newInvoiceNo = (newInvoiceInput && newInvoiceInput.value) ? newInvoiceInput.value.trim() : '';

            const claimData = {
                preorder_no: currentPreOrderData.preorder_no,
                invoice_no: newInvoiceNo,
                claim_items: claimItems,
                unclaimed_freebies: unclaimedFreebies,
                payment_data: paymentDataToSend,
                payment_status: 'paid'
            };

            logToDebugConsole(`Initiating saveClaimPreOrder. Sending payload: ${JSON.stringify(claimData)}`, 'info');

            // Make AJAX request to save claim
            fetch('save_claim_preorder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(claimData)
            })
                .then(response => response.json())
                .then(data => {
                    logToDebugConsole(`Received response from server. Status success: ${data.success}`, data.success ? 'success' : 'error');
                    if (data.debug) {
                        logToDebugConsole('--- PHP Backend Logs: ---', 'info');
                        data.debug.forEach(logLine => {
                            if (logLine.includes('FAILED') || logLine.includes('error') || logLine.includes('rolled back')) {
                                logToDebugConsole(logLine, 'error');
                            } else if (logLine.includes('affected rows: 0')) {
                                logToDebugConsole(logLine, 'warning');
                            } else {
                                logToDebugConsole(logLine, 'info');
                            }
                        });
                        logToDebugConsole('------------------------', 'info');
                    }
                    if (data.success) {
                        const newInvEl = document.getElementById('displayNewInvoiceNo');
                        if (newInvEl && data.sales_invoice_no) {
                            newInvEl.textContent = data.sales_invoice_no;
                        }

                        // Also update payment modal invoice display if it exists
                        const paymentModalInvoiceInput = document.getElementById('paymentModalInvoiceNumberInput');
                        if (paymentModalInvoiceInput && data.sales_invoice_no) {
                            paymentModalInvoiceInput.value = data.sales_invoice_no;
                        }

                        alert(`Pre-order claim saved successfully!\n\nNew Invoice No: ${data.sales_invoice_no || ''}`);

                        // Reload the page to reset the form and clear payment modal
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error saving claim');
                    }
                })
                .catch(error => {
                    logToDebugConsole(`AJAX Request error: ${error.message}`, 'error');
                    console.error('Error:', error);
                    alert('Error saving claim');
                });
        }

        function resetForm() {
            document.getElementById('preorderInput').value = '';
            claimItems = [];
            currentPreOrderData = null;
            clearInputs();
            clearPanels();
            updateClaimItemsTable();

            // Clear unclaimed freebies table
            const unclaimedFreebiesTableBody = document.getElementById('unclaimedFreebiesTableBody');
            unclaimedFreebiesTableBody.innerHTML = '<tr id="no-unclaimed-freebies-row"><td colspan="4" style="text-align:center; padding: 20px;">Please click Add Unclaimed Freebies</td></tr>';

            // Reset payment button to default state
            const btnPayment = document.querySelector('.btn-payment');
            if (btnPayment) {
                btnPayment.innerText = 'PAYMENT';
                btnPayment.style.backgroundColor = '';
                btnPayment.style.color = '';
            }

            // Reset payment data
            window.paymentData = null;
        }

        // Helper: find an input/select inside a section by its label text
        function getFieldByLabel(section, labelText) {
            if (!section) return null;
            const groups = section.querySelectorAll('.hc-form-group');
            for (const group of groups) {
                if (group.classList.contains('unit-selector-row')) continue;
                const lbl = group.querySelector('label');
                if (lbl && lbl.textContent.trim().toLowerCase().includes(labelText.toLowerCase())) {
                    return group.querySelector('input, select');
                }
            }
            return null;
        }
        function getSelectByLabel(section, labelText) {
            if (!section) return null;
            const groups = section.querySelectorAll('.hc-form-group');
            for (const group of groups) {
                if (group.classList.contains('unit-selector-row')) continue;
                const lbl = group.querySelector('label');
                if (lbl && lbl.textContent.trim().toLowerCase().includes(labelText.toLowerCase())) {
                    return group.querySelector('select');
                }
            }
            return null;
        }

        function openPaymentModal() {
            if (!currentPreOrderData) {
                alert('Please search and select a pre-order first');
                return;
            }

            const totalAmount = document.getElementById('totalAmount').value;
            if (!totalAmount || parseFloat(totalAmount.replace(/[^0-9.]/g, '')) <= 0) {
                alert('Please add items and calculate total before payment');
                return;
            }

            // Reset: uncheck all payment checkboxes and hide all sections
            document.querySelectorAll('input[name="payment_method"]').forEach(cb => {
                cb.checked = false;
            });
            const allSections = [
                '.home-credit-section', '.credit-card-section', '.debit-card-section',
                '.qr-ph-section', '.starpay-qr-section', '.ewallet-section',
                '.online-banking-section', '.cash-section'
            ];
            allSections.forEach(sel => {
                const el = document.querySelector(sel);
                if (el) el.style.display = 'none';
            });

            // Reset dropdowns to default
            const ppDd = document.getElementById('paymentPartnersDropdown');
            if (ppDd) ppDd.selectedIndex = 0;
            const ccDd = document.getElementById('cardPaymentDropdown');
            if (ccDd) ccDd.selectedIndex = 0;
            const qrDd = document.getElementById('qrDropdown');
            if (qrDd) qrDd.selectedIndex = 0;

            // Clear all amount/reference inputs inside payment sections
            document.querySelectorAll('.home-credit-section input, .credit-card-section input, .debit-card-section input, .qr-ph-section input, .starpay-qr-section input, .ewallet-section input, .online-banking-section input, .cash-section input').forEach(inp => {
                if (inp.type !== 'checkbox') inp.value = '';
            });

            // Calculate remaining balance (Total Amount Due)
            // rawTotal = ALL claim items total (preorder items + any extra items added)
            const rawTotal = parseFloat(totalAmount.replace(/[^0-9.]/g, '')) || 0;
            // Total due = all claim items total minus what has already been paid
            const remainingBalance = rawTotal - totalBalancePaid;

            // Set remaining balance in all per-section total labels
            const totalInputs = document.querySelectorAll('.total-input');
            totalInputs.forEach(input => {
                input.value = remainingBalance.toFixed(2);
            });

            // Populate Total Amount Due in the modal summary area (remaining balance)
            const globalTotalDue = document.getElementById('globalTotalDueInput');
            if (globalTotalDue) globalTotalDue.value = remainingBalance.toFixed(2);

            // Populate Balance paid from preorder
            const balancePaidInput = document.getElementById('balancePaidInput');
            if (balancePaidInput) {
                balancePaidInput.value = '₱' + totalBalancePaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // Check if balance is fully paid
            const isFullyPaid = remainingBalance <= 0;

            // Set flag if there's an unpaid balance when modal opens
            hasUnpaidBalance = remainingBalance > 0;

            // Note: newInvoiceNumberSection is now always visible at the top of the page
            // No need to show/hide it based on payment modal state

            // If fully paid, show the balance paid as Total Payment
            if (isFullyPaid) {
                const globalTotal = document.getElementById('globalTotalInput');
                if (globalTotal) {
                    globalTotal.value = totalBalancePaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
            }

            // Disable all payment method checkboxes and inputs if fully paid
            if (isFullyPaid) {
                // Disable payment method checkboxes
                document.querySelectorAll('input[name="payment_method"]').forEach(cb => {
                    cb.disabled = true;
                    cb.checked = false;
                });

                // Disable dropdowns
                const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
                const cardPaymentDropdown = document.getElementById('cardPaymentDropdown');
                const qrDropdown = document.getElementById('qrDropdown');
                if (paymentPartnersDropdown) paymentPartnersDropdown.disabled = true;
                if (cardPaymentDropdown) cardPaymentDropdown.disabled = true;
                if (qrDropdown) qrDropdown.disabled = true;

                // Hide all payment sections
                allSections.forEach(sel => {
                    const el = document.querySelector(sel);
                    if (el) el.style.display = 'none';
                });

                // Show message that balance is fully paid
                const paymentErrorBanner = document.getElementById('paymentErrorBanner');
                if (paymentErrorBanner) {
                    paymentErrorBanner.innerHTML = `
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="color:#16a34a;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                            </div>
                            <div style="flex:1; color:#166534; font-size:14px;">
                                <div style="font-weight:600; margin-bottom:4px;">Balance Fully Paid</div>
                                <div>This pre-order has been fully paid. No additional payment is required.</div>
                            </div>
                        </div>
                    `;
                    paymentErrorBanner.style.display = 'block';
                    paymentErrorBanner.style.backgroundColor = '#f0fdf4';
                    paymentErrorBanner.style.borderLeft = '4px solid #16a34a';
                }
            } else {
                // Enable payment methods if not fully paid
                document.querySelectorAll('input[name="payment_method"]').forEach(cb => {
                    if (!cb.closest('.disabled-payment')) {
                        cb.disabled = false;
                    }
                });

                // Enable dropdowns for unpaid pre-orders
                const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
                const cardPaymentDropdown = document.getElementById('cardPaymentDropdown');
                const qrDropdown = document.getElementById('qrDropdown');
                if (paymentPartnersDropdown) paymentPartnersDropdown.disabled = false;
                if (cardPaymentDropdown) cardPaymentDropdown.disabled = false;
                if (qrDropdown) qrDropdown.disabled = false;

                // Hide the fully paid error banner
                const paymentErrorBanner = document.getElementById('paymentErrorBanner');
                if (paymentErrorBanner) {
                    paymentErrorBanner.style.display = 'none';
                    paymentErrorBanner.innerHTML = '';
                }

                // Reset Total Payment for new payments
                const globalTotal = document.getElementById('globalTotalInput');
                if (globalTotal) globalTotal.value = '';
            }

            // Reset breakdown banner
            const bkBanner = document.getElementById('paymentBreakdownBanner');
            if (bkBanner) { bkBanner.style.display = 'none'; bkBanner.innerHTML = ''; }

            // Display payment breakdown for fully paid pre-orders
            if (isFullyPaid && totalBalancePaid > 0) {
                renderPaymentBreakdown(totalBalancePaid, rawTotal);
            }

            // Populate Unit dropdowns from the items table
            populateUnitSelector();

            // Attach payment method checkbox listeners
            const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
            const cardPaymentDropdown = document.getElementById('cardPaymentDropdown');
            const qrDropdown = document.getElementById('qrDropdown');

            const homeCreditSection = document.querySelector('.home-credit-section');
            const creditCardSection = document.querySelector('.credit-card-section');
            const debitCardSection = document.querySelector('.debit-card-section');
            const qrPhSection = document.querySelector('.qr-ph-section');
            const starpayQrSection = document.querySelector('.starpay-qr-section');
            const ewalletSection = document.querySelector('.ewallet-section');
            const onlineBankingSection = document.querySelector('.online-banking-section');
            const cashSection = document.querySelector('.cash-section');

            if (!document.body._paymentListenersAttached) {
                document.body._paymentListenersAttached = true;

                if (paymentPartnersDropdown) {
                    paymentPartnersDropdown.addEventListener('change', function () {
                        const chk = document.getElementById('chkPaymentPartners');
                        if (this.value !== '') {
                            if (chk) chk.checked = true;
                        }
                        if (chk && chk.checked) {
                            if (homeCreditSection) homeCreditSection.style.display = 'none';
                            const partnerTitle = document.getElementById('paymentPartnerTitle');
                            const loanTypeSelect = homeCreditSection ? homeCreditSection.querySelector('.hc-form-group:nth-child(2) select') : null;
                            if (this.value === 'partner2') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Home Credit';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="0_installment">0% Installment</option><option value="standard_loan">Standard loan</option><option value="retailer_zero">Retailer Zero</option><option value="saver_plan">Saver Plan</option>';
                                }
                            } else if (this.value === 'partner5') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Salmon';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                                }
                            } else if (this.value === 'partner6') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Samsung Finances';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                                }
                            } else if (this.value === 'partner7') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Payjoy';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                                }
                            } else if (this.value === 'partner8') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Billease';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                                }
                            } else if (this.value === 'partner9') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Paymongo';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                                }
                            } else if (this.value === 'partner10') {
                                if (homeCreditSection) homeCreditSection.style.display = 'block';
                                if (partnerTitle) partnerTitle.textContent = 'Skyro';
                                if (loanTypeSelect) {
                                    loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                                }
                            }
                        }
                    });
                }

                if (cardPaymentDropdown) {
                    cardPaymentDropdown.addEventListener('change', function () {
                        if (this.disabled) return;
                        const chk = document.getElementById('chkCardPayment');
                        if (chk && chk.checked) {
                            if (creditCardSection) creditCardSection.style.display = 'none';
                            if (debitCardSection) debitCardSection.style.display = 'none';
                            if (this.value === 'credit_card') {
                                if (creditCardSection) creditCardSection.style.display = 'block';
                            } else if (this.value === 'debit_card') {
                                if (debitCardSection) debitCardSection.style.display = 'block';
                            }
                        }
                    });
                }

                if (qrDropdown) {
                    qrDropdown.addEventListener('change', function () {
                        if (this.disabled) return;
                        const chk = document.getElementById('chkQR');
                        if (chk && chk.checked) {
                            if (qrPhSection) qrPhSection.style.display = 'none';
                            if (starpayQrSection) starpayQrSection.style.display = 'none';
                            if (this.value === 'qr_ph') {
                                if (qrPhSection) qrPhSection.style.display = 'block';
                            } else if (this.value === 'starpay_qr') {
                                if (starpayQrSection) starpayQrSection.style.display = 'block';
                            }
                        }
                    });
                }

                const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
                paymentRadios.forEach(checkbox => {
                    checkbox.addEventListener('change', function () {
                        if (this.disabled) return;
                        if (this.value === 'payment_partners' || this.id === 'chkPaymentPartners') {
                            if (this.checked) {
                                if (paymentPartnersDropdown && paymentPartnersDropdown.value === '') {
                                    paymentPartnersDropdown.value = 'partner2';
                                }
                                paymentPartnersDropdown && paymentPartnersDropdown.dispatchEvent(new Event('change'));
                            } else {
                                if (homeCreditSection) homeCreditSection.style.display = 'none';
                            }
                        } else if (this.value === 'card_payment' || this.id === 'chkCardPayment') {
                            if (this.checked) {
                                if (cardPaymentDropdown && cardPaymentDropdown.value === '') {
                                    cardPaymentDropdown.value = 'credit_card';
                                }
                                cardPaymentDropdown && cardPaymentDropdown.dispatchEvent(new Event('change'));
                            } else {
                                if (creditCardSection) creditCardSection.style.display = 'none';
                                if (debitCardSection) debitCardSection.style.display = 'none';
                            }
                        } else if (this.value === 'qr' || this.id === 'chkQR') {
                            if (this.checked) {
                                if (qrDropdown && qrDropdown.value === '') {
                                    qrDropdown.value = 'qr_ph';
                                }
                                qrDropdown && qrDropdown.dispatchEvent(new Event('change'));
                            } else {
                                if (qrPhSection) qrPhSection.style.display = 'none';
                                if (starpayQrSection) starpayQrSection.style.display = 'none';
                            }
                        } else {
                            const targetSection =
                                this.value === 'ewallet' ? ewalletSection :
                                    this.value === 'online_banking' ? onlineBankingSection :
                                        this.value === 'cash' ? cashSection : null;
                            if (targetSection) {
                                targetSection.style.display = this.checked ? 'block' : 'none';
                            }
                        }
                        setTimeout(recalcTotalPayment, 60);
                    });
                });

                // Live recalc on amount input
                const modalBody = document.querySelector('#paymentModal .modal-body');
                if (modalBody) {
                    modalBody.addEventListener('input', function (e) {
                        const inp = e.target;
                        if (!inp || inp.classList.contains('total-input') || inp.readOnly) return;
                        const parentSection = inp.closest(
                            '.cash-section, .ewallet-section, .online-banking-section, ' +
                            '.home-credit-section, .credit-card-section, .debit-card-section, ' +
                            '.qr-ph-section, .starpay-qr-section'
                        );
                        if (!parentSection || parentSection.style.display !== 'block') return;
                        const formGroup = inp.closest('.hc-form-group, .enter-amount-row, .reference-no-row');
                        const lbl = formGroup ? formGroup.querySelector('label') : null;
                        const lblText = lbl ? lbl.textContent.toLowerCase() : '';
                        const isAmountField = lblText.includes('amount') || lblText.includes('balance');
                        if (!isAmountField) return;
                        recalcTotalPayment();
                    });
                }
            }

            document.getElementById('paymentModal').style.display = 'flex';

            // Auto-check payment method removed - Payment Method dropdown no longer exists
        }

        function populateUnitSelector() {
            const tbody = document.getElementById('claimItemsTable');
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.querySelector('td') && r.querySelector('td').textContent.trim() !== '');
            const unitRows = document.querySelectorAll('.unit-selector-row');
            if (unitRows.length === 0) return;

            const items = [];
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 2) {
                    const desc = cells[0].textContent.trim();
                    if (desc) items.push({ desc });
                }
            });

            unitRows.forEach(unitRow => {
                if (items.length === 0) {
                    unitRow.style.display = 'none';
                    unitRow.innerHTML = '';
                    return;
                }

                unitRow.style.display = '';
                unitRow.innerHTML = `
                    <label style="min-width: 120px;">Unit:</label>
                    <div class="custom-multiselect" style="position: relative; flex: 1; min-width: 200px;">
                        <div class="multiselect-selected hc-input" style="cursor: pointer; background: ${items.length === 1 ? '#f5f5f5' : '#fff'}; display: flex; align-items: center; justify-content: space-between;" onclick="const d = this.nextElementSibling; d.style.display = d.style.display === 'none' ? 'block' : 'none';">
                            <span class="selected-text" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 10px;">-- Select Units --</span>
                            <span style="font-size: 10px;">▼</span>
                        </div>
                        <div class="unit-checkboxes multiselect-dropdown" style="display: none; position: absolute; top: calc(100% + 2px); left: 0; right: 0; background: white; border: 1px solid #bfbfbf; border-radius: 4px; max-height: 150px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 5px;"></div>
                    </div>
                `;
                const container = unitRow.querySelector('.unit-checkboxes');
                const textSpan = unitRow.querySelector('.selected-text');

                const updateText = () => {
                    const checked = Array.from(container.querySelectorAll('input[type="checkbox"]:checked'));
                    if (checked.length === 0) textSpan.textContent = '-- Select Units --';
                    else if (checked.length === 1) textSpan.textContent = checked[0].value;
                    else textSpan.textContent = checked.length + ' Units Selected';
                };

                items.forEach(item => {
                    const lbl = document.createElement('label');
                    lbl.style.cssText = 'margin:0; display:flex; align-items:flex-start; gap:8px; font-weight:normal; line-height:1.2; color:#333; cursor:pointer; padding:5px; border-radius:3px;';
                    lbl.onmouseover = () => lbl.style.backgroundColor = '#f0f0f0';
                    lbl.onmouseout = () => lbl.style.backgroundColor = 'transparent';

                    const cb = document.createElement('input');
                    cb.type = 'checkbox'; cb.name = 'Unit'; cb.value = item.desc;
                    cb.style.cssText = 'margin-top:2px; width:16px; height:16px;';
                    cb.addEventListener('change', updateText);

                    if (items.length === 1) cb.checked = true;

                    lbl.appendChild(cb);
                    lbl.appendChild(document.createTextNode(item.desc));
                    container.appendChild(lbl);
                });

                updateText();
            });

            if (!document._unitDropdownCloseAttached) {
                document._unitDropdownCloseAttached = true;
                document.addEventListener('click', function (e) {
                    if (!e.target.closest('.custom-multiselect')) {
                        document.querySelectorAll('.multiselect-dropdown').forEach(d => d.style.display = 'none');
                    }
                });
            }
        }

        function recalcTotalPayment() {
            const originalTotalStr = document.getElementById('totalAmount') ? document.getElementById('totalAmount').value : '0';
            const originalTotal = parseFloat((originalTotalStr || '').replace(/[^0-9.]/g, '')) || 0;

            // Total due = all claim items total minus what has already been paid
            const remainingBalance = originalTotal - totalBalancePaid;

            const allPaySections = [
                { class: '.cash-section', name: 'Cash' },
                { class: '.ewallet-section', name: 'E-Wallet' },
                { class: '.online-banking-section', name: 'Online Banking' },
                { class: '.home-credit-section', name: 'Home Credit' },
                { class: '.credit-card-section', name: 'Credit Card' },
                { class: '.debit-card-section', name: 'Debit Card' },
                { class: '.qr-ph-section', name: 'QR PH' },
                { class: '.starpay-qr-section', name: 'Starpay QR' }
            ];

            let totalPaid = 0;
            allPaySections.forEach(sec => {
                const secEl = document.querySelector(sec.class);
                if (!secEl || secEl.style.display !== 'block') return;
                secEl.querySelectorAll('.hc-form-group, .enter-amount-row, .reference-no-row').forEach(group => {
                    const lbl = group.querySelector('label');
                    const lblText = lbl ? lbl.textContent.toLowerCase() : '';
                    if (lblText.includes('amount') || lblText.includes('balance')) {
                        const inp = group.querySelector('input');
                        if (inp && !inp.readOnly && inp.type !== 'checkbox') {
                            totalPaid += parseFloat((inp.value || '').replace(/,/g, '')) || 0;
                        }
                    }
                });
            });

            const globalTotal = document.getElementById('globalTotalInput');
            if (globalTotal) {
                globalTotal.value = totalPaid > 0
                    ? totalPaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : '';
            }

            const remaining = remainingBalance - totalPaid;
            const globalTotalDue = document.getElementById('globalTotalDueInput');
            if (globalTotalDue) {
                globalTotalDue.value = remaining.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // Note: newInvoiceNumberSection is now always visible at the top of the page
            // No need to show/hide it based on payment calculations

            if (totalPaid > 0) renderPaymentBreakdown(totalPaid, remainingBalance);
            else {
                const bkBanner = document.getElementById('paymentBreakdownBanner');
                if (bkBanner) { bkBanner.style.display = 'none'; bkBanner.innerHTML = ''; }
            }
        }

        function renderPaymentBreakdown(totalPaid, originalTotal) {
            const banner = document.getElementById('paymentBreakdownBanner');
            if (!banner) return;

            const neededDisp = originalTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const enteredDisp = totalPaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const overallDiff = originalTotal - totalPaid;
            const remainingBal = overallDiff < 0 ? 0 : overallDiff;
            const diffDisp = remainingBal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const diffColor = overallDiff <= 0.01 ? '#16a34a' : '#ca8a04';

            let unitRowsHtml = '';
            const itemTbody = document.getElementById('claimItemsTable');
            if (itemTbody) {
                itemTbody.querySelectorAll('tr').forEach(row => {
                    const tds = row.querySelectorAll('td');
                    if (tds.length >= 4 && tds[0].textContent.trim()) {
                        const descText = tds[0].textContent.trim();
                        const qty = parseInt(tds[2].textContent) || 1;
                        const priceText = tds[3].textContent.replace(/[^0-9.]/g, '');
                        const pVal = parseFloat(priceText) || 0;
                        unitRowsHtml += `
                            <div style="display:flex; justify-content:space-between; padding-left:12px; font-size:13px; color:#000; margin-top:2px;">
                                <span style="font-style:italic; max-width:320px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">- ${descText}</span>
                                <span>₱${(pVal * qty).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                            </div>`;
                    }
                });
            }
            if (unitRowsHtml) {
                const _discountFieldEl = document.getElementById('discountField');
                const _discountAmt = _discountFieldEl ? (parseFloat(_discountFieldEl.value.replace(/,/g, '')) || 0) : 0;
                const _discountRow = _discountAmt > 0
                    ? `<div style="display:flex; justify-content:space-between; padding-left:14px; font-size:12px; color:#dc2626; margin-top:2px;"><span style="font-weight:600;">↳ Less Discount:</span><span style="font-weight:600;">-₱${_discountAmt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div>`
                    : '';
                unitRowsHtml = `<div style="margin-bottom:6px;"><div style="font-weight:600; color:#000; font-size:13px;">Unit(s) To Pay:</div>${unitRowsHtml}${_discountRow}</div>`;
            }

            const paymentSections = [
                { class: '.home-credit-section', name: 'Home Credit' },
                { class: '.credit-card-section', name: 'Credit Card' },
                { class: '.debit-card-section', name: 'Debit Card' },
                { class: '.qr-ph-section', name: 'QR PH' },
                { class: '.starpay-qr-section', name: 'Starpay QR' },
                { class: '.ewallet-section', name: 'E-Wallet' },
                { class: '.online-banking-section', name: 'Online Banking' },
                { class: '.cash-section', name: 'Cash' }
            ];

            let breakdownInner = '';
            paymentSections.forEach(sec => {
                const secEl = document.querySelector(sec.class);
                if (!secEl || secEl.style.display !== 'block') return;

                let secTotal = 0;
                let secRows = '';

                secEl.querySelectorAll('input[type="text"], input[type="number"]').forEach(inp => {
                    if (inp.readOnly || inp.classList.contains('total-input')) return;
                    let isAmount = false, labelText = '';

                    if (inp.id === 'cash_down_payment_amount') { isAmount = true; labelText = 'Cash (DP)'; }
                    if (inp.id === 'gcash_down_payment_amount') { isAmount = true; labelText = 'G-Cash (DP)'; }
                    if (inp.id === 'maya_down_payment_amount') { isAmount = true; labelText = 'Maya (DP)'; }

                    const fg = inp.closest('.hc-form-group');
                    if (fg) {
                        const lbl = fg.querySelector('label');
                        if (lbl && (lbl.innerText.includes('Amount') || lbl.innerText.includes('Balance'))) {
                            isAmount = true;
                            if (!labelText) labelText = lbl.innerText.replace(':', '').trim();
                        }
                    }
                    const ear = inp.closest('.enter-amount-row, .reference-no-row');
                    if (ear) {
                        const lbl = ear.querySelector('label');
                        if (lbl && lbl.innerText.includes('Amount')) {
                            isAmount = true;
                            if (!labelText) labelText = 'Downpayment';
                        }
                    }
                    if (isAmount && !labelText) labelText = 'Amount';

                    if (isAmount) {
                        const val = parseFloat((inp.value || '').replace(/,/g, '')) || 0;
                        if (val > 0) {
                            secTotal += val;
                            secRows += `<div style="display:flex; justify-content:space-between; padding-left:12px; font-size:12px; color:#000; margin-top:2px;">
                                <span>- ${labelText}</span>
                                <span>₱${val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                            </div>`;
                        }
                    }
                });

                if (secTotal > 0) {
                    let displayName = sec.name;
                    const ppDd = document.getElementById('paymentPartnersDropdown');
                    if (sec.name === 'Home Credit' && ppDd && ppDd.value !== '') displayName = ppDd.options[ppDd.selectedIndex].text;
                    const ewSel = secEl.querySelector('select');
                    if ((sec.name === 'E-Wallet' || sec.name === 'Online Banking') && ewSel && ewSel.selectedIndex > 0) displayName = ewSel.options[ewSel.selectedIndex].text;

                    breakdownInner += `<div style="margin-bottom:6px;"><div style="font-weight:600; color:#000; font-size:13px;">${displayName}:</div>${secRows}</div>`;
                }
            });

            const breakdownHtml = breakdownInner
                ? `<div style="margin:8px 0; padding:6px 0; border-top:1px solid #cfcfcf; border-bottom:1px solid #cfcfcf;">${breakdownInner}</div>`
                : '';

            // Build Amount Paid section from preorder
            let amountPaidHtml = '';
            if (totalBalancePaid > 0 && currentPreOrderData) {
                const balancePaidDisp = totalBalancePaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                // Get payment methods from payment history (preferred) or items (fallback)
                let paymentMethodsUsed = [];
                let paymentDetails = [];

                if (currentPreOrderData.payment_history && currentPreOrderData.payment_history.length > 0) {
                    // Use payment history for more accurate payment method information
                    currentPreOrderData.payment_history.forEach(ph => {
                        if (ph.payment_method && !paymentMethodsUsed.includes(ph.payment_method)) {
                            paymentMethodsUsed.push(ph.payment_method);
                        }
                        const amt = parseFloat(ph.amount || 0);
                        if (amt > 0) {
                            paymentDetails.push({
                                method: ph.payment_method || 'N/A',
                                amount: amt,
                                invoice_no: ph.invoice_no || 'N/A',
                                sequence: ph.payment_sequence || 1
                            });
                        }
                    });
                } else if (currentPreOrderData.items) {
                    // Fallback to items
                    currentPreOrderData.items.forEach(item => {
                        if (item.payment_method && !paymentMethodsUsed.includes(item.payment_method)) {
                            paymentMethodsUsed.push(item.payment_method);
                        }
                    });
                }

                const paymentMethodText = paymentMethodsUsed.length > 0 ? paymentMethodsUsed.join(', ') : 'N/A';

                // Build payment details rows
                let paymentDetailsHtml = '';
                if (paymentDetails.length > 0) {
                    paymentDetails.forEach(pd => {
                        const amtDisp = pd.amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        paymentDetailsHtml += `
                            <div style="display:flex; justify-content:space-between; padding-left:12px; font-size:12px; color:#166534; margin-top:2px;">
                                <span>- ${pd.method} (Payment ${pd.sequence})</span>
                                <span>₱${amtDisp}</span>
                            </div>`;
                    });
                } else {
                    paymentDetailsHtml = `
                        <div style="display:flex; justify-content:space-between; padding-left:12px; font-size:12px; color:#166534; margin-top:2px;">
                            <span>- Payment Method: ${paymentMethodText}</span>
                        </div>`;
                }

                amountPaidHtml = `
                    <div style="margin-bottom:6px; padding:8px; background-color:#f0fdf4; border:1px solid #86efac; border-radius:4px;">
                        <div style="font-weight:600; color:#166534; font-size:13px; margin-bottom:4px;">Amount Already Paid (Pre-order):</div>
                        ${paymentDetailsHtml}
                        <div style="display:flex; justify-content:space-between; padding-left:12px; font-size:12px; color:#166534; margin-top:6px; padding-top:6px; border-top:1px solid #86efac; font-weight:600;">
                            <span>Total Amount Paid:</span>
                            <span>₱${balancePaidDisp}</span>
                        </div>
                    </div>
                `;
            }

            banner.innerHTML = `
                <div style="display:flex; align-items:flex-start; gap:12px;">
                    <div style="color:#000; margin-top:2px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                    </div>
                    <div style="flex:1; color:#000; font-size:13px; line-height:1.6;">
                        <div style="font-weight:600; font-size:14px; color:#000; margin-bottom:4px;">Payment Breakdown</div>
                        <div>You are currently entering your breakdown details.</div>
                        <div style="margin-top:6px; display:flex; flex-direction:column; gap:2px; max-width:450px; padding-top:6px;">
                            ${unitRowsHtml}
                            ${amountPaidHtml}
                            <div style="display:flex; justify-content:space-between; border-bottom:1.5px solid #fff; padding-bottom:4px; margin-bottom:2px;">
                                <span style="font-weight:600;">Total Amount Due:</span><span style="font-weight:700;">₱${neededDisp}</span>
                            </div>
                            ${breakdownHtml}
                            <div style="display:flex; justify-content:space-between;">
                                <span>Total Entered:</span><span style="font-weight:600;">₱${enteredDisp}</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; margin-top:2px; padding-top:4px; border-top:1.5px dashed #afafaf;">
                                <span style="color:#000; font-weight:600;">Remaining Balance:</span><span style="color:${diffColor}; font-weight:600;">₱${diffDisp}</span>
                            </div>
                        </div>
                    </div>
                </div>`;
            banner.style.display = 'block';
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }

        function toggleDownPaymentReference() {
            const cashCb = document.querySelector('input[name="down_payment_method"][value="cash"]');
            const gcashCb = document.querySelector('input[name="down_payment_method"][value="gcash"]');
            const mayaCb = document.querySelector('input[name="down_payment_method"][value="maya"]');

            const cashRow = document.getElementById('dpCashRow');
            const gcashRow = document.getElementById('dpGcashRow');
            const mayaRow = document.getElementById('dpMayaRow');

            if (cashCb && cashCb.checked) {
                cashRow.style.display = 'flex';
            } else if (cashRow) {
                cashRow.style.display = 'none';
                const cInput = document.getElementById('cash_down_payment_amount');
                if (cInput) { cInput.value = ''; }
            }

            if (gcashCb && gcashCb.checked) {
                gcashRow.style.display = 'flex';
            } else if (gcashRow) {
                gcashRow.style.display = 'none';
                const gRef = document.getElementById('gcash_down_payment_reference');
                const gInput = document.getElementById('gcash_down_payment_amount');
                if (gRef) gRef.value = '';
                if (gInput) gInput.value = '';
            }

            if (mayaCb && mayaCb.checked) {
                mayaRow.style.display = 'flex';
            } else if (mayaRow) {
                mayaRow.style.display = 'none';
                const mRef = document.getElementById('maya_down_payment_reference');
                const mInput = document.getElementById('maya_down_payment_amount');
                if (mRef) mRef.value = '';
                if (mInput) mInput.value = '';
            }
        }

        function savePaymentData() {
            // Check if balance is fully paid
            // claimItemsTotal = ALL claim items (preorder items + extras); subtract already paid
            const claimItemsTotalStr = document.getElementById('totalAmount') ? document.getElementById('totalAmount').value : '0';
            const claimItemsTotal = parseFloat((claimItemsTotalStr || '').replace(/[^0-9.]/g, '')) || 0;
            const remainingBalance = claimItemsTotal - totalBalancePaid;
            const isFullyPaid = remainingBalance <= 0;

            // If fully paid, just close modal and mark as paid
            if (isFullyPaid) {
                window.paymentData = {
                    payment_type: 'fully_paid',
                    status: 'paid',
                    amount_paid: totalBalancePaid,
                    message: 'Pre-order balance is fully paid'
                };

                const btnPayment = document.querySelector('.btn-payment');
                if (btnPayment) {
                    btnPayment.innerText = 'Payment: Fully Paid';
                    btnPayment.style.backgroundColor = '#2E7D32';
                    btnPayment.style.color = 'white';
                }

                alert('This pre-order is fully paid. No additional payment required.');
                closePaymentModal();
                return;
            }

            const homeCreditSection = document.querySelector('.home-credit-section');
            const creditCardSection = document.querySelector('.credit-card-section');
            const debitCardSection = document.querySelector('.debit-card-section');
            const qrPhSection = document.querySelector('.qr-ph-section');
            const starpayQrSection = document.querySelector('.starpay-qr-section');
            const ewalletSection = document.querySelector('.ewallet-section');
            const onlineBankingSection = document.querySelector('.online-banking-section');
            const cashSection = document.querySelector('.cash-section');

            let hasActiveSection = false;
            if (homeCreditSection && homeCreditSection.style.display === 'block') hasActiveSection = true;
            if (creditCardSection && creditCardSection.style.display === 'block') hasActiveSection = true;
            if (debitCardSection && debitCardSection.style.display === 'block') hasActiveSection = true;
            if (qrPhSection && qrPhSection.style.display === 'block') hasActiveSection = true;
            if (starpayQrSection && starpayQrSection.style.display === 'block') hasActiveSection = true;
            if (ewalletSection && ewalletSection.style.display === 'block') hasActiveSection = true;
            if (onlineBankingSection && onlineBankingSection.style.display === 'block') hasActiveSection = true;
            if (cashSection && cashSection.style.display === 'block') hasActiveSection = true;

            if (!hasActiveSection) {
                alert('Please select a payment method.');
                return;
            }

            let payments = [];

            if (cashSection && cashSection.style.display === 'block') {
                const cashAmountInput = getFieldByLabel(cashSection, 'amount');
                const cashAmount = cashAmountInput ? cashAmountInput.value.trim() : '';
                if (!cashAmount) { alert('Please enter Cash amount.'); return; }
                payments.push({
                    payment_type: 'cash',
                    amount: cashAmount,
                    units: Array.from(cashSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            if (ewalletSection && ewalletSection.style.display === 'block') {
                const ewalletTypeEl = getSelectByLabel(ewalletSection, 'e-wallet');
                const ewalletType = ewalletTypeEl ? ewalletTypeEl.value : '';
                const ewalletRefEl = getFieldByLabel(ewalletSection, 'reference');
                const ewalletAmtEl = getFieldByLabel(ewalletSection, 'amount');
                const ewalletNameEl = getFieldByLabel(ewalletSection, 'customer');
                const ewalletRef = ewalletRefEl ? ewalletRefEl.value.trim() : '';
                const ewalletAmount = ewalletAmtEl ? ewalletAmtEl.value.trim() : '';
                const ewalletCustomer = ewalletNameEl ? ewalletNameEl.value.trim() : '';
                if (!ewalletType || !ewalletRef || !ewalletAmount) { alert('Please fill all E-Wallet fields.'); return; }
                payments.push({
                    payment_type: 'ewallet',
                    ewallet_type: ewalletType,
                    customer_name: ewalletCustomer,
                    reference_no: ewalletRef,
                    amount: ewalletAmount,
                    units: Array.from(ewalletSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            if (onlineBankingSection && onlineBankingSection.style.display === 'block') {
                const bankSel = getSelectByLabel(onlineBankingSection, 'bank');
                const bankName = bankSel ? bankSel.value : '';
                const bankRefEl = getFieldByLabel(onlineBankingSection, 'reference');
                const bankAmtEl = getFieldByLabel(onlineBankingSection, 'amount');
                const bankRef = bankRefEl ? bankRefEl.value.trim() : '';
                const bankAmount = bankAmtEl ? bankAmtEl.value.trim() : '';
                if (!bankName || !bankRef || !bankAmount) { alert('Please fill all Online Banking fields.'); return; }
                payments.push({
                    payment_type: 'online_banking',
                    bank_name: bankName,
                    reference_no: bankRef,
                    amount: bankAmount,
                    units: Array.from(onlineBankingSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            if (homeCreditSection && homeCreditSection.style.display === 'block') {
                const loanTypeEl = getSelectByLabel(homeCreditSection, 'loan type');
                const loanTermsEl = getSelectByLabel(homeCreditSection, 'loan terms');
                const custNameEl = getFieldByLabel(homeCreditSection, 'customer');
                const loanNumEl = getFieldByLabel(homeCreditSection, 'loan number');
                const loanBalEl = getFieldByLabel(homeCreditSection, 'loan balance');
                const loanType = loanTypeEl ? loanTypeEl.value : '';
                const loanTerms = loanTermsEl ? loanTermsEl.value : '';
                const customerName = custNameEl ? custNameEl.value.trim() : '';
                const loanNumber = loanNumEl ? loanNumEl.value.trim() : '';
                const loanBalance = loanBalEl ? loanBalEl.value.trim() : '';

                const downPaymentMethods = [];
                homeCreditSection.querySelectorAll('input[name="down_payment_method"]:checked').forEach(cb => { downPaymentMethods.push(cb.value); });

                let hcTotalAmount = parseFloat((loanBalance || '0').replace(/,/g, '')) || 0;

                const dpEntry = {
                    payment_type: 'payment_partners',
                    payment_partner: document.getElementById('paymentPartnersDropdown') ? document.getElementById('paymentPartnersDropdown').value : '',
                    loan_type: loanType,
                    loan_terms: loanTerms,
                    customer_name: customerName,
                    loan_number: loanNumber,
                    loan_balance: loanBalance,
                    down_payment_methods: downPaymentMethods,
                    units: Array.from(homeCreditSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                };
                if (downPaymentMethods.includes('cash')) {
                    const inp = document.getElementById('cash_down_payment_amount');
                    dpEntry.cash_dp_amount = inp ? inp.value : '';
                    hcTotalAmount += parseFloat((inp ? inp.value : '0').replace(/,/g, '')) || 0;
                }
                if (downPaymentMethods.includes('gcash')) {
                    const ref = document.getElementById('gcash_down_payment_reference');
                    const inp = document.getElementById('gcash_down_payment_amount');
                    dpEntry.gcash_reference = ref ? ref.value : '';
                    dpEntry.gcash_dp_amount = inp ? inp.value : '';
                    hcTotalAmount += parseFloat((inp ? inp.value : '0').replace(/,/g, '')) || 0;
                }
                if (downPaymentMethods.includes('maya')) {
                    const ref = document.getElementById('maya_down_payment_reference');
                    const inp = document.getElementById('maya_down_payment_amount');
                    dpEntry.maya_reference = ref ? ref.value : '';
                    dpEntry.maya_dp_amount = inp ? inp.value : '';
                    hcTotalAmount += parseFloat((inp ? inp.value : '0').replace(/,/g, '')) || 0;
                }
                dpEntry.amount = hcTotalAmount.toFixed(2);
                payments.push(dpEntry);
            }

            if (creditCardSection && creditCardSection.style.display === 'block') {
                const ccAmtEl = getFieldByLabel(creditCardSection, 'amount');
                const ccAmount = ccAmtEl ? ccAmtEl.value.trim() : '';
                if (!ccAmount) { alert('Please enter Credit Card amount.'); return; }
                payments.push({
                    payment_type: 'credit_card',
                    amount: ccAmount,
                    units: Array.from(creditCardSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            if (debitCardSection && debitCardSection.style.display === 'block') {
                const dcAmtEl = getFieldByLabel(debitCardSection, 'amount');
                const dcAmount = dcAmtEl ? dcAmtEl.value.trim() : '';
                if (!dcAmount) { alert('Please enter Debit Card amount.'); return; }
                payments.push({
                    payment_type: 'debit_card',
                    amount: dcAmount,
                    units: Array.from(debitCardSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            if (qrPhSection && qrPhSection.style.display === 'block') {
                const qrAmtEl = getFieldByLabel(qrPhSection, 'amount');
                const qrAmount = qrAmtEl ? qrAmtEl.value.trim() : '';
                if (!qrAmount) { alert('Please enter QR PH amount.'); return; }
                payments.push({
                    payment_type: 'qr_ph',
                    amount: qrAmount,
                    units: Array.from(qrPhSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            if (starpayQrSection && starpayQrSection.style.display === 'block') {
                const spAmtEl = getFieldByLabel(starpayQrSection, 'amount');
                const spAmount = spAmtEl ? spAmtEl.value.trim() : '';
                if (!spAmount) { alert('Please enter Starpay QR amount.'); return; }
                payments.push({
                    payment_type: 'starpay_qr',
                    amount: spAmount,
                    units: Array.from(starpayQrSection.querySelectorAll('input[name="Unit"]:checked')).map(cb => cb.value)
                });
            }

            window.paymentData = payments.length === 1 ? payments[0] : { payment_type: 'multiple', payments: payments };

            // Check if this payment completes the balance
            const globalTotalInput = document.getElementById('globalTotalInput');
            const totalPaymentEntered = globalTotalInput ? parseFloat(globalTotalInput.value.replace(/[^0-9.]/g, '')) || 0 : 0;
            const newTotalPaid = totalBalancePaid + totalPaymentEntered;

            // Mark as paid if total payment covers the grand total
            if (newTotalPaid >= preorderGrandTotal) {
                if (payments.length === 1) {
                    window.paymentData.status = 'paid';
                } else {
                    window.paymentData.status = 'paid';
                }
            } else {
                if (payments.length === 1) {
                    window.paymentData.status = 'partial';
                } else {
                    window.paymentData.status = 'partial';
                }
            }

            const btnPayment = document.querySelector('.btn-payment');
            if (btnPayment) {
                const labels = payments.map(p => {
                    if (p.payment_type === 'cash') return 'Cash';
                    if (p.payment_type === 'ewallet') return p.ewallet_type || 'E-Wallet';
                    if (p.payment_type === 'online_banking') return 'Online Banking';
                    if (p.payment_type === 'credit_card') return 'Credit Card';
                    if (p.payment_type === 'debit_card') return 'Debit Card';
                    if (p.payment_type === 'qr_ph') return 'QR PH';
                    if (p.payment_type === 'starpay_qr') return 'Starpay QR';
                    if (p.payment_type === 'payment_partners') return document.getElementById('paymentPartnersDropdown')?.options[document.getElementById('paymentPartnersDropdown').selectedIndex]?.text || 'Payment Partner';
                    return p.payment_type;
                });
                btnPayment.innerText = 'Payment: ' + labels.join(' + ');
                btnPayment.style.backgroundColor = '#2E7D32';
                btnPayment.style.color = 'white';
            }

            const statusMsg = window.paymentData.status === 'paid'
                ? 'Payment information saved successfully! This pre-order is now fully paid.'
                : 'Payment information saved successfully!';
            alert(statusMsg);
            closePaymentModal();
        }

        // ══════════════════════════════════════════════════════════════════════════════
        // UNCLAIMED FREEBIES FUNCTIONALITY
        // ══════════════════════════════════════════════════════════════════════════════
        let unclaimedFreebieRowCounter = 0;
        let currentUnclaimedFreebieRow = null; // Track which row is being searched

        // Function to add a new unclaimed freebie row
        function addUnclaimedFreebieRow() {
            unclaimedFreebieRowCounter++;
            const unclaimedFreebiesTableBody = document.getElementById('unclaimedFreebiesTableBody');

            // Remove "no unclaimed freebies" row if it exists
            const noUnclaimedFreebiesRow = document.getElementById('no-unclaimed-freebies-row');
            if (noUnclaimedFreebiesRow) {
                noUnclaimedFreebiesRow.remove();
            }

            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td style="border: 1px solid #ccc;">
                    <div style="display: flex; gap: 5px; align-items: center;">
                        <input type="text" class="unclaimed-freebie-name-input" style="flex: 1; border: 1px solid #ddd; padding: 8px; border-radius: 4px;" readonly>
                        <button type="button" class="btn-search-unclaimed-freebie" style="background-color: #424242; color: white; border: none; border-radius: 4px; padding: 8px 15px; cursor: pointer; white-space: nowrap; font-size: 12px;" onclick="openUnclaimedFreebieSearchModal(this)">Search</button>
                    </div>
                </td>
                <td style="border: 1px solid #ccc;"><input type="number" class="unclaimed-freebie-qty-input" style="width: 100%; border: 1px solid #ddd; padding: 8px; text-align: center; border-radius: 4px;" value="0" min="0"></td>
                <td style="border: 1px solid #ccc;">
                    <input type="text" class="unclaimed-freebie-note-input" placeholder="Enter Notes (Optional)" style="width: 100%; border: 1px solid #ddd; padding: 8px; text-align: left; border-radius: 4px;">
                </td>
                <td style="border: 1px solid #ccc; text-align: center;">
                    <button type="button" class="btn-delete-unclaimed-freebie" style="background: #ef5350; color: white; border: none; border-radius: 4px; width: 24px; height: 24px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;" onclick="removeUnclaimedFreebieRow(this)">X</button>
                </td>
            `;

            unclaimedFreebiesTableBody.appendChild(newRow);
        }

        // Function to remove an unclaimed freebie row
        function removeUnclaimedFreebieRow(button) {
            const row = button.closest('tr');
            const tbody = row.parentElement;
            row.remove();

            // If no rows left, show the placeholder message
            if (tbody.querySelectorAll('tr').length === 0) {
                const placeholderRow = document.createElement('tr');
                placeholderRow.id = 'no-unclaimed-freebies-row';
                placeholderRow.innerHTML = '<td colspan="4" style="text-align:center; padding: 20px;">Please click Add Unclaimed Freebies</td>';
                tbody.appendChild(placeholderRow);
            }
        }

        // Function to open unclaimed freebie search modal
        function openUnclaimedFreebieSearchModal(button) {
            currentUnclaimedFreebieRow = button.closest('tr');
            const modal = document.getElementById('searchUnclaimedFreebieModal');
            const searchInput = document.getElementById('unclaimedFreebieSearchInput');
            const resultsBody = document.getElementById('unclaimedFreebieSearchResultsBody');

            // Clear previous search
            searchInput.value = '';
            resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">Enter search term and press Enter</td></tr>';

            modal.style.display = 'flex';
            searchInput.focus();
        }

        // Function to close unclaimed freebie search modal
        function closeUnclaimedFreebieSearchModal() {
            const modal = document.getElementById('searchUnclaimedFreebieModal');
            modal.style.display = 'none';
            currentUnclaimedFreebieRow = null;
        }

        // Function to search unclaimed freebies
        function searchUnclaimedFreebies() {
            const searchInput = document.getElementById('unclaimedFreebieSearchInput');
            const searchTerm = searchInput.value.trim();
            const resultsBody = document.getElementById('unclaimedFreebieSearchResultsBody');

            if (searchTerm === '') {
                alert('Please enter a search term!');
                return;
            }

            // Show loading state
            resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">Searching...</td></tr>';

            // Fetch non-serialized items
            fetch(`search_freebies.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers.get('content-type'));

                    // Check if response is OK
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    // Clone response to read it twice (for debugging)
                    return response.clone().text().then(text => {
                        console.log('Raw response:', text);

                        // Check if response is empty
                        if (!text || text.trim() === '') {
                            throw new Error('Empty response from server');
                        }

                        // Try to parse as JSON
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            console.error('Response text:', text);
                            throw new Error('Invalid JSON response from server');
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed data:', data);
                    resultsBody.innerHTML = '';

                    // Check for error status
                    if (data.status === 'error') {
                        resultsBody.innerHTML = `<tr><td colspan="3" style="text-align:center; padding: 20px; color: red;">Error: ${data.message}</td></tr>`;
                        return;
                    }

                    // Display results
                    if (data.status === 'success' && data.data && data.data.length > 0) {
                        data.data.forEach((item) => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.item_code}</td>
                                <td>${item.description}</td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn-select" onclick="selectUnclaimedFreebie('${item.item_code.replace(/'/g, "\\'")}', '${item.description.replace(/'/g, "\\'")}')">Select</button>
                                </td>
                            `;
                            resultsBody.appendChild(row);
                        });
                    } else {
                        resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">No items found with zero stock at your branch</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsBody.innerHTML = `<tr><td colspan="3" style="text-align:center; padding: 20px; color: red;">Error: ${error.message}<br/>Check browser console for details</td></tr>`;
                });
        }

        // Function to select an unclaimed freebie
        function selectUnclaimedFreebie(itemCode, description) {
            if (currentUnclaimedFreebieRow) {
                const nameInput = currentUnclaimedFreebieRow.querySelector('.unclaimed-freebie-name-input');
                nameInput.value = description;
                nameInput.setAttribute('data-item-code', itemCode);
                closeUnclaimedFreebieSearchModal();
            }
        }

        // Attach event listener to Add Unclaimed Freebies button when DOM loads
        document.addEventListener('DOMContentLoaded', function () {
            const addUnclaimedFreebiesButton = document.querySelector('.btn-add-unclaimed-freebies');
            if (addUnclaimedFreebiesButton) {
                addUnclaimedFreebiesButton.addEventListener('click', addUnclaimedFreebieRow);
            }

            // Add event listener for unclaimed freebie search input (Enter key)
            const unclaimedFreebieSearchInput = document.getElementById('unclaimedFreebieSearchInput');
            if (unclaimedFreebieSearchInput) {
                unclaimedFreebieSearchInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        searchUnclaimedFreebies();
                    }
                });
            }

            // Close modal when clicking outside
            window.addEventListener('click', function (event) {
                const unclaimedFreebieModal = document.getElementById('searchUnclaimedFreebieModal');
                if (event.target === unclaimedFreebieModal) {
                    closeUnclaimedFreebieSearchModal();
                }
            });
        });
    </script>
</body>

</html>