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
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Upgrade Unit</title>
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

        /* Upgrade Unit Content Styles */
        /* Item Selection Section */
        .item-selection-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 0px;
        }

        .item-input-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto auto;
            gap: 15px;
            align-items: flex-end;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-height {
            height: 100%;
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

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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

        .btn-save {
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
        }

        .btn-save:hover {
            background-color: var(--color-gold-light);
        }

        .btn-payment {
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 40px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            white-space: nowrap;
            text-transform: uppercase;
        }

        .btn-payment:hover {
            background-color: var(--color-gold-light);
        }

        .btn-skip-receipt {
            padding: 10px 40px;
            border: 1px solid #ff6b6b;
            background: #ff6b6b;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            min-width: 120px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .btn-skip-receipt:hover {
            background: white;
            color: #ff6b6b;
            border: 1px solid #ff6b6b;
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

        /* Bottom Section Grid */
        .bottom-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .totals-section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            display: flex;
            flex-direction: column;
            gap: 10px;
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
            font-weight: 500;
            color: #333;
            white-space: nowrap;
            min-width: 100px;
        }

        .invoice-search input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
        }

        .invoice-search input:focus {
            outline: none;
            border-color: #2196F3;
        }

        .btn-search {
            padding: 10px 30px;
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
            font-weight: 500;
            color: #333;
        }

        .reason-dropdown select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
            background-color: white;
            cursor: pointer;
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

        /* Items Table Styles (Matched to salesentry.php design with full grid lines) */
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

        /* Improved Number Inputs within Table */
        .items-table input[type="number"] {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
            width: 100%;
            box-sizing: border-box;
            text-align: center;
        }

        .items-table input[type="number"]:focus {
            border-color: #66bb6a;
            box-shadow: 0 0 3px rgba(102, 187, 106, 0.3);
        }

        .qty-input {
            max-width: 80px;
            margin: 0 auto;
            display: block;
        }

        .price-input-table {
            max-width: 120px;
            margin: 0 auto;
            display: block;
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

        /* Delete button styles */
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

        .bottom-section {
            background: white;
            border: 1px solid #e0e0e0;
            padding: 20px;
            border-radius: 4px;
        }

        .input-fields-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .input-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .input-field label {
            font-size: 13px;
            color: #333;
            font-weight: 500;
        }

        .input-field input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        .input-field input:focus {
            outline: none;
            border-color: #2e7d32;
        }

        .item-model-row {
            display: flex;
            align-items: flex-end;
            gap: 15px;
            margin-bottom: 20px;
        }

        .item-model-field {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .item-model-field label {
            font-size: 13px;
            color: #333;
            font-weight: 500;
        }

        .item-model-field input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        .item-model-field input:focus {
            outline: none;
            border-color: #2e7d32;
        }

        .button-row {
            display: flex;
            gap: 15px;
        }

        .btn-search-dark {
            padding: 8px 30px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-search-dark:hover {
            background-color: var(--color-navy-dark);
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
            border-color: #2e7d32;
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
            padding: 12px 50px;
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            align-self: flex-end;
        }

        .btn-save:hover {
            background-color: var(--color-gold-light);
        }

        .btn-payment {
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 40px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            white-space: nowrap;
            margin-right: 0px;
        }

        .btn-payment:hover {
            background-color: var(--color-gold-light);
        }

        /* Payment Modal Specific Styles */
        .payment-modal-content {
            max-width: 900px;
            width: 100%;
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

        /* Disabled Payment Options */
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

        /* Home Credit Section */
        .home-credit-section {
            background: white;
            padding-top: 0px;
            display: none;
        }

        /* Credit Card Section */
        .credit-card-section {
            background: white;
            padding-top: 0px;
            display: none;
        }

        /* Debit Card Section */
        .debit-card-section {
            background: white;
            padding-top: 0px;
            display: none;
        }

        /* QR PH Section */
        .qr-ph-section {
            background: white;
            padding-top: 0px;
            display: none;
        }

        /* Starpay QR Section */
        .starpay-qr-section {
            background: white;
            padding-top: 0px;
            display: none;
        }

        /* E-Wallet Section */
        .ewallet-section {
            background: white;
            padding-top: 0px;
            display: none;
        }

        /* Online Banking Section */
        .online-banking-section {
            background: white;
            padding-top: 0px;
            display: none;
        }

        /* Cash Section */
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

        /* HC Single Column Layout */
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

        .hc-form-row {
            display: contents;
        }

        .hc-form-group-wide {
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

        /* Down Payment Group specific styling */
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

        /* Total Section - Right Aligned */
        .total-section {
            display: flex;
            width: 100%;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
        }

        .total-section label {
            font-size: 16px;
            font-weight: 700;
            color: #333;
        }

        .total-input {
            padding: 10px 15px;
            border: 1px solid #bfbfbf;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            width: 200px;
            text-align: right;
            background: white;
        }

        /* Footer - Back (Left) and Save (Right) */
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

        @media (max-width: 1460px) {

            /* Make Search and Add buttons smaller for better spacing */
            .btn-search-item,
            .btn-add-item {
                width: auto !important;
                /* Let buttons size naturally */
                max-width: 90px !important;
                /* Limit button width */
                padding: 10px 20px !important;
                /* Compact padding */
                font-size: 14px !important;
                /* Slightly smaller font */
                flex-shrink: 0 !important;
                /* Don't let buttons shrink */
            }

            /* Make Quantity and Price inputs take available space */
            .form-group input[type="number"],
            .form-group input[type="text"] {
                min-width: 70px !important;
                /* Ensure minimum readable width */
            }
        }

        @media (max-width: 1200px) {
            .top-section {
                grid-template-columns: 1fr;
            }

            .item-input-row {
                grid-template-columns: 1fr 1fr;
            }

            .btn-search-item,
            .btn-add-item {
                grid-column: span 1;
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

            .item-input-row {
                grid-template-columns: 1fr 0.8fr 1.5fr !important;
                /* Item Model: 1fr, IMEI: 0.8fr, Quantity/Price/Buttons: 1.5fr */
            }

            .btn-search-item,
            .btn-add-item {
                width: auto !important;
                /* Let buttons size naturally */
                max-width: 80px !important;
                /* Limit button width */
                padding: 8px 15px !important;
                /* Compact padding */
                font-size: 13px !important;
                /* Smaller font */
                flex-shrink: 0 !important;
                /* Don't let buttons shrink */
            }

            /* Make Quantity and Price inputs take available space */
            .form-group input[type="number"],
            .form-group input[type="text"] {
                min-width: 60px !important;
                /* Ensure minimum readable width */
            }

            /* Make top search button smaller too */
            .btn-search {
                padding: 8px 20px !important;
                font-size: 13px !important;
            }
        }

        /* Mobile Phones (max-width: 600px) */
        @media (max-width: 600px) {

            /* Stack all input fields vertically within item-selection-container */
            .item-selection-container .item-input-row {
                grid-template-columns: 1fr !important;
                /* Single column layout */
                gap: 10px !important;
            }

            /* Make form groups full width */
            .item-selection-container .form-group {
                width: 100% !important;
            }

            /* Adjust labels and inputs */
            .item-selection-container .form-group label {
                font-size: 13px !important;
                font-weight: 600 !important;
            }

            .item-selection-container .form-group input {
                font-size: 14px !important;
                padding: 10px !important;
                width: 100% !important;
            }

            /* Stack Quantity, Price, and Buttons row */
            .item-selection-container div[style*="display: flex"][style*="gap: 10px"] {
                flex-direction: column !important;
                gap: 10px !important;
                align-items: stretch !important;
            }

            /* Make buttons full width on mobile */
            .item-selection-container .btn-search-item,
            .item-selection-container .btn-add-item {
                width: 100% !important;
                max-width: none !important;
                padding: 12px 20px !important;
                font-size: 14px !important;
                margin-bottom: 0 !important;
            }

            /* Make items table responsive */
            .item-selection-container .items-table {
                font-size: 12px !important;
            }

            .item-selection-container .items-table th,
            .item-selection-container .items-table td {
                padding: 8px 5px !important;
                font-size: 12px !important;
            }

            /* Reduce table column widths for better fit */
            .item-selection-container .items-table th:nth-child(1),
            .item-selection-container .items-table td:nth-child(1) {
                min-width: 120px !important;
            }

            .item-selection-container .items-table th:nth-child(2),
            .item-selection-container .items-table td:nth-child(2) {
                min-width: 100px !important;
            }

            .item-selection-container .items-table th:nth-child(3),
            .item-selection-container .items-table td:nth-child(3) {
                min-width: 70px !important;
            }

            .item-selection-container .items-table th:nth-child(4),
            .item-selection-container .items-table td:nth-child(4) {
                min-width: 80px !important;
            }

            .item-selection-container .items-table th:nth-child(5),
            .item-selection-container .items-table td:nth-child(5) {
                min-width: 60px !important;
            }

            /* Stack Remarks and Totals section vertically on mobile */
            div[style*="grid-template-columns: 2fr 1fr"] {
                display: flex !important;
                flex-direction: column !important;
                gap: 15px !important;
            }

            /* Make Remarks section full width */
            .remarks-section,
            div[style*="grid-template-columns: 2fr 1fr"]>div:first-child {
                width: 100% !important;
            }

            /* Adjust Remarks textarea */
            #remarks_textarea {
                min-height: 120px !important;
                font-size: 14px !important;
            }

            /* Make Totals section full width */
            .totals-section {
                width: 100% !important;
            }

            /* Adjust total rows */
            .total-row {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 10px !important;
            }

            .total-row label {
                font-size: 14px !important;
                flex-shrink: 0 !important;
            }

            .total-row input {
                font-size: 14px !important;
                padding: 10px !important;
                flex: 1 !important;
                min-width: 120px !important;
            }

            /* Adjust Balance row */
            .total-row:last-child label {
                font-size: 15px !important;
            }

            .total-row:last-child input {
                font-size: 15px !important;
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
            <h2>Upgrade Unit</h2>
        </div>

        <!-- Top Section -->
        <div class="top-section">
            <!-- Left Box -->
            <div class="box-container">
                <!-- New Invoice Number -->
                <div id="newInvoiceNumberSection" class="invoice-search" style="margin-bottom: 20px;">
                    <label>New Invoice No:</label>
                    <input type="text" id="newInvoiceNumberInput" readonly
                        style="background-color: #f5f5f5; color: #999; cursor: not-allowed;">
                </div>
                <div class="invoice-search">
                    <label>Invoice No:</label>
                    <input type="text" id="invoice_no_input" placeholder="Enter Invoice">
                    <button class="btn-search" onclick="searchInvoice()">Search</button>
                </div>
                <div class="customer-details-box" id="customer_details_box">
                    Show Customer Details
                </div>
                <div class="reason-dropdown">
                    <label>Reason:</label>
                    <select id="reason_select">
                        <option>Defective Unit</option>
                        <option>Customer Request</option>
                        <option>Upgrade</option>
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
                    <tbody id="items_display_tbody">
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Item Selection Section (Matching salesentry.php UI) -->
        <div class="item-selection-container"
            style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #ccc; margin-bottom: 20px;">
            <div class="item-input-row"
                style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
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
                            <div style="position: relative;">
                                <input type="text" id="price_input" placeholder="" readonly
                                    style="background-color: #ffffff; color: #333;">
                                <span
                                    style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #666;">▼</span>
                                <select id="priceDropdown"
                                    style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;">
                                    <option value="">Select</option>
                                    <option value="auto">Auto</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" class="btn-search-item" id="search_item_btn"
                            style="margin-bottom: 1px;">Search</button>
                        <button type="button" class="btn-add-item" id="add_item_btn"
                            style="margin-bottom: 1px;">Add</button>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Item Description</th>
                        <th style="width: 30%;">IMEI</th>
                        <th style="width: 15%;">Quantity</th>
                        <th style="width: 15%;">Price</th>
                        <th style="width: 10%; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="itemsTableBody">
                    <tr id="no-items-row">
                        <td colspan="5" style="text-align:center; padding: 20px;">No items added yet</td>
                    </tr>
                </tbody>
            </table>

            <!-- Totals and Remarks Section -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Remarks Section (Left) -->
                <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #ccc;">
                    <div class="form-group full-height">
                        <label>Remarks</label>
                        <textarea id="remarks_textarea"
                            style="min-height: 150px; resize: vertical; font-family: Arial, sans-serif; font-size: 14px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; width: 100%;"></textarea>
                    </div>
                </div>

                <!-- Totals Section (Right) -->
                <div class="totals-section" style="width: 100%;">
                    <div class="total-row">
                        <label>Old Unit Price:</label>
                        <input type="text" id="oldUnitTotal" readonly style="background-color: #f5f5f5;">
                    </div>
                    <div class="total-row">
                        <label>New Unit Price:</label>
                        <input type="text" id="newUnitTotal" readonly style="background-color: #f5f5f5;">
                    </div>
                    <div class="total-row" style="border-top: 2px solid #333; padding-top: 10px; margin-top: 10px;">
                        <label style="font-weight: bold; font-size: 15px;">Balance:</label>
                        <input type="text" id="totalAmount" readonly
                            style="font-weight: bold; font-size: 15px; background-color: #f5f5f5;">
                    </div>
                </div>
            </div>

            <!-- Footer Actions Section (Below the grid) -->
            <div class="footer-actions"
                style="width: 100%; justify-content: flex-end; padding: 20px; background: white; margin-top: 20px; border-radius: 8px; border: 1px solid #ccc;">
                <div class="footer-right-group">
                    <button type="button" class="btn-payment" onclick="openPaymentModal()">PAYMENT</button>
                    <button type="button" class="btn-save" onclick="validateAndSave()">SAVE</button>
                </div>
            </div>

            <input type="hidden" id="payment_data" name="payment_data">
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

    <!-- Payment Modal -->
    <style>
        .total-section {
            display: none !important;
        }

        /* Add spacing and separators between active payment sections */
        .payment-modal-body>div[class$="-section"]:not(.payment-top-section) {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed #ccc;
        }

        /* Ensure the first visible section doesn't have a top border if it immediately follows top-section */
        .payment-top-section {
            margin-bottom: 20px;
            /* Optional space below checkboxes */
        }
    </style>
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
                            <option value="partner1">STO niño de cebu</option>
                            <option value="partner2">Home Credit</option>
                            <option value="partner3">Bank of Makati</option>
                            <option value="partner4">Sumisho</option>

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
                    <h3 id="paymentPartnerTitle">STO niño de cebu </h3>

                    <div class="hc-grid-container">
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
                            <input type="text" class="hc-input" id="totalLoanAmountUpgrade">
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
                                            onchange="toggleDownPaymentReferenceUpgrade()">
                                        1. Cash
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="down_payment_method" value="gcash"
                                            onchange="toggleDownPaymentReferenceUpgrade()">
                                        2. G-Cash
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="down_payment_method" value="maya"
                                            onchange="toggleDownPaymentReferenceUpgrade()">
                                        3. Maya
                                    </label>
                                </div>

                                <div class="reference-no-row hci-amount-row" id="dpCashRowUpgrade"
                                    style="display: none; align-items: center; gap: 10px; margin-bottom: 15px;">
                                    <label
                                        style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">Cash
                                        Amount (DP):</label>
                                    <input type="text" class="amount-input" id="cash_down_payment_amount_upgrade"
                                        style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                </div>

                                <div class="reference-no-row hci-amount-row" id="dpGcashRowUpgrade"
                                    style="display: none; flex-direction: column; gap: 10px; margin-bottom: 15px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <label
                                            style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">G-Cash
                                            Reference No:</label>
                                        <input type="text" class="reference-input"
                                            id="gcash_down_payment_reference_upgrade"
                                            name="gcash_down_payment_reference"
                                            style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <label
                                            style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">G-Cash
                                            Amount (DP):</label>
                                        <input type="text" class="amount-input" id="gcash_down_payment_amount_upgrade"
                                            style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                    </div>
                                </div>

                                <div class="reference-no-row hci-amount-row" id="dpMayaRowUpgrade"
                                    style="display: none; flex-direction: column; gap: 10px; margin-bottom: 15px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <label
                                            style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">Maya
                                            Reference No:</label>
                                        <input type="text" class="reference-input"
                                            id="maya_down_payment_reference_upgrade" name="maya_down_payment_reference"
                                            style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <label
                                            style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">Maya
                                            Amount (DP):</label>
                                        <input type="text" class="amount-input" id="maya_down_payment_amount_upgrade"
                                            style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                    </div>
                                </div>

                            </div>
                        </div>
                        <script>
                            function toggleDownPaymentReferenceUpgrade() {
                                const cashCb = document.querySelector('.home-credit-section input[name="down_payment_method"][value="cash"]');
                                const gc = document.querySelector('.home-credit-section input[name="down_payment_method"][value="gcash"]');
                                const my = document.querySelector('.home-credit-section input[name="down_payment_method"][value="maya"]');

                                const cashRow = document.getElementById('dpCashRowUpgrade');
                                const gcashRow = document.getElementById('dpGcashRowUpgrade');
                                const mayaRow = document.getElementById('dpMayaRowUpgrade');

                                if (cashCb && cashRow) {
                                    if (cashCb.checked) {
                                        cashRow.style.display = 'flex';
                                    } else {
                                        cashRow.style.display = 'none';
                                        const cInput = document.getElementById('cash_down_payment_amount_upgrade');
                                        if (cInput) { cInput.value = ''; cInput.dispatchEvent(new Event('input', { bubbles: true })); }
                                    }
                                }

                                if (gc && gcashRow) {
                                    if (gc.checked) {
                                        gcashRow.style.display = 'flex';
                                    } else {
                                        gcashRow.style.display = 'none';
                                        const gRef = document.getElementById('gcash_down_payment_reference_upgrade');
                                        const gInput = document.getElementById('gcash_down_payment_amount_upgrade');
                                        if (gRef) gRef.value = '';
                                        if (gInput) { gInput.value = ''; gInput.dispatchEvent(new Event('input', { bubbles: true })); }
                                    }
                                }

                                if (my && mayaRow) {
                                    if (my.checked) {
                                        mayaRow.style.display = 'flex';
                                    } else {
                                        mayaRow.style.display = 'none';
                                        const mRef = document.getElementById('maya_down_payment_reference_upgrade');
                                        const mInput = document.getElementById('maya_down_payment_amount_upgrade');
                                        if (mRef) mRef.value = '';
                                        if (mInput) { mInput.value = ''; mInput.dispatchEvent(new Event('input', { bubbles: true })); }
                                    }
                                }
                            }
                        </script>

                        <div class="total-section">
                            <label>Total:</label>
                            <input type="text" class="total-input" readonly>
                        </div>
                    </div> <!-- Closes hc-grid-container -->
                </div> <!-- Closes home-credit-section -->

                <!-- Credit Card Section -->
                <div class="credit-card-section">
                    <h3>Credit Card</h3>

                    <div class="hc-grid-container">
                        <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                        <div class="hc-form-group">
                            <label>Terminal Issuer:</label>
                            <select class="hc-input" id="ccTerminalIssuer">
                                <option value="">Select Terminal Issuer</option>
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
                            <select class="hc-input" id="dcTerminalIssuer">
                                <option value="">Select Terminal Issuer</option>
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
                            <select class="hc-input">
                                <option value="">Select Bank</option>
                            </select>
                        </div>

                        <div class="hc-form-group">
                            <label>Terms:</label>
                            <select class="hc-input">
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
                            <input type="text" class="hc-input">
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
                                <option value="E-West">E-West</option>
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
                                <option value="E-West">E-West</option>
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
                                <option value="E-West">E-West</option>
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
                        style="display: flex; justify-content: flex-end; align-items: center; font-size: 16px; font-weight: bold; gap: 10px;">
                        <label>Total Payment:</label>
                        <input type="text" id="globalTotalInput" readonly
                            style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; width: 150px; background-color: #f5f5f5; font-weight: bold; text-align: right;">
                    </div>
                </div>
                <!-- Payment Breakdown banner -->
                <div id="paymentBreakdownBanner"
                    style="display:none; margin: 0 0 15px 0; padding: 12px 16px; background-color: #ffffffff; border-left: 4px solid #16a34a; border-right:1px solid #c9c9c9ff; border-top:1px solid #c9c9c9ff; border-bottom:1px solid #c9c9c9ff; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-family: system-ui, -apple-system, sans-serif;">
                </div>

                <!-- Inline payment error banner -->
                <div id="paymentErrorBanner"
                    style="display:none; margin: 0 0 15px 0; padding: 12px 16px; background-color: #fef2f2; border-left: 4px solid #ef4444; border-right:1px solid #c9c9c9ff; border-top:1px solid #c9c9c9ff; border-bottom:1px solid #c9c9c9ff; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-family: system-ui, -apple-system, sans-serif;">
                </div>
            </div>

            <div class="modal-footer payment-modal-footer">
                <button type="button" class="btn-back-modal" onclick="closePaymentModal()">Back</button>
                <button type="button" class="btn-save-modal">Save</button>
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

        // Search invoice and populate data
        function searchInvoice() {
            const invoiceNo = document.getElementById('invoice_no_input').value.trim();

            if (!invoiceNo) {
                alert('Please enter an invoice number');
                return;
            }

            // Show loading state
            const customerBox = document.getElementById('customer_details_box');
            customerBox.innerHTML = 'Loading...';
            customerBox.classList.remove('has-data');

            // Fetch sales data
            fetch('get_sales_by_invoice.php?invoice_no=' + encodeURIComponent(invoiceNo))
                .then(response => {
                    // Check if response is ok
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }

                    // Check content type
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            console.error('Non-JSON response:', text);
                            throw new Error('Server returned non-JSON response. Check console for details.');
                        });
                    }

                    return response.json();
                })
                .then(result => {
                    if (result.status === 'success') {
                        displayCustomerDetails(result.data);
                        displayItems(result.data.items);
                    } else {
                        alert(result.message || 'Invoice not found');
                        customerBox.innerHTML = 'Show Customer Details';
                        clearItemsDisplay();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while searching for the invoice: ' + error.message);
                    customerBox.innerHTML = 'Show Customer Details';
                    clearItemsDisplay();
                });
        }

        // Display customer details
        function displayCustomerDetails(data) {
            const customerBox = document.getElementById('customer_details_box');
            customerBox.classList.add('has-data');

            const customer = data.customer;
            const fullName = `${customer.first_name} ${customer.last_name}`.trim();

            // Store customer data globally for later use
            window.currentCustomerData = {
                first_name: customer.first_name || '',
                last_name: customer.last_name || '',
                address: customer.address || '',
                contact_no: customer.contact_no || '',
                email: customer.email || '',
                assisted_by: data.assisted_by || ''
            };

            let html = '';
            if (fullName) {
                html += `<div class="customer-detail-row"><span class="label">Name:</span><span class="value">${fullName}</span></div>`;
            }
            if (customer.address) {
                html += `<div class="customer-detail-row"><span class="label">Address:</span><span class="value">${customer.address}</span></div>`;
            }
            if (customer.contact_no) {
                html += `<div class="customer-detail-row"><span class="label">Contact:</span><span class="value">${customer.contact_no}</span></div>`;
            }
            if (customer.email) {
                html += `<div class="customer-detail-row"><span class="label">Email:</span><span class="value">${customer.email}</span></div>`;
            }
            if (data.assisted_by) {
                html += `<div class="customer-detail-row"><span class="label">Assisted By:</span><span class="value">${data.assisted_by}</span></div>`;
            }

            customerBox.innerHTML = html || 'No customer details available';
        }

        // Display items in the table
        function displayItems(items) {
            const tbody = document.getElementById('items_display_tbody');
            tbody.innerHTML = '';

            // Store items data globally for later use
            window.originalInvoiceItems = items;

            // Only display rows with actual items (no empty rows)
            items.forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><input type="checkbox" name="item_select" value="${index}" data-imei="${item.imei || ''}" data-description="${item.item_description || ''}" data-price="${item.discounted_price || item.price || 0}" onchange="calculateLessAmount()"></td>
                    <td>${item.item_description || ''}</td>
                    <td>${item.imei || ''}</td>
                `;
                tbody.appendChild(row);
            });

            // Reset Less and Total when new invoice is loaded
            calculateLessAmount();
        }

        // Clear items display
        function clearItemsDisplay() {
            const tbody = document.getElementById('items_display_tbody');
            tbody.innerHTML = '';
            // Add one blank row to show table structure
            const row = document.createElement('tr');
            row.innerHTML = '<td></td><td></td><td></td>';
            tbody.appendChild(row);

            // Reset totals
            document.getElementById('oldUnitTotal').value = '';
            document.getElementById('newUnitTotal').value = '';
            document.getElementById('totalAmount').value = '';
        }

        // Calculate Old Unit Total (sum of selected items from first table)
        function calculateLessAmount() {
            const checkboxes = document.querySelectorAll('input[name="item_select"]:checked');
            let oldUnitTotal = 0;

            checkboxes.forEach(checkbox => {
                const price = parseFloat(checkbox.dataset.price) || 0;
                oldUnitTotal += price;
            });

            // Update Old Unit Total field with formatted number
            document.getElementById('oldUnitTotal').value = formatCurrency(oldUnitTotal);

            // Recalculate total
            calculateTotal();
        }

        // Calculate Total Amount (new items total - old unit total)
        function calculateTotal() {
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

            // Update New Unit Total field
            document.getElementById('newUnitTotal').value = formatCurrency(newItemsTotal);

            const oldUnitTotalStr = document.getElementById('oldUnitTotal').value;
            const oldUnitTotal = parseFloat(oldUnitTotalStr.replace(/,/g, '')) || 0;

            // Calculate total need to pay (new items - old unit)
            // Only show the difference if BOTH old units are selected AND new items are added
            let totalAmount = 0;
            if (newItemsTotal > 0 && oldUnitTotal > 0) {
                totalAmount = newItemsTotal - oldUnitTotal;
            }

            // Update Total Need to Pay field with formatted number
            document.getElementById('totalAmount').value = formatCurrency(totalAmount);
        }

        // Format number as currency (with commas and 2 decimal places)
        function formatCurrency(amount) {
            return amount.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Get selected items
        function getSelectedItems() {
            const checkboxes = document.querySelectorAll('input[name="item_select"]:checked');
            const selectedItems = [];

            checkboxes.forEach(checkbox => {
                selectedItems.push({
                    index: checkbox.value,
                    imei: checkbox.dataset.imei,
                    description: checkbox.dataset.description
                });
            });

            return selectedItems;
        }

        // IMEI Search Function (same as salesentry.php)
        function searchByIMEI(imei) {
            fetch(`search_imei.php?imei=${encodeURIComponent(imei)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const priceField = document.getElementById('price_input');
                        const priceDropdown = document.getElementById('priceDropdown');
                        const rawPrice = data.data.price || '';

                        // Populate fields with found data
                        document.getElementById('item_model_input').value = data.data.item_code || ''; // Show item_code, not description

                        // Format price with commas and store auto-price
                        if (rawPrice) {
                            const formattedPrice = parseFloat(rawPrice).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            priceField.value = formattedPrice;
                            priceField.setAttribute('data-auto-price', rawPrice);
                        } else {
                            priceField.value = '';
                        }

                        // Set dropdown to auto mode
                        if (priceDropdown) {
                            priceDropdown.value = 'auto';
                        }

                        document.getElementById('quantity_input').value = '1'; // Set quantity to 1 when IMEI is found

                        // Mark IMEI as found
                        document.getElementById('imei_input').setAttribute('data-imei-found', '1');

                        // Set price field to auto mode (readonly)
                        priceField.setAttribute('readonly', 'readonly');
                        priceField.setAttribute('data-price-mode', 'auto');
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
                        priceField.removeAttribute('data-price-mode');
                        priceField.removeAttribute('data-auto-price');
                        priceField.style.backgroundColor = '#ffffff';
                        priceField.style.cursor = 'pointer';
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
                        const priceDropdown = document.getElementById('priceDropdown');

                        if (data.status === 'success' && data.has_serial) {
                            // Item is serialized - show alert and clear fields
                            alert('This item is serialized');
                            document.getElementById('item_model_input').value = '';
                            priceField.value = '';
                            priceField.removeAttribute('data-auto-price');
                            priceField.removeAttribute('data-price-mode');
                            if (priceDropdown) priceDropdown.value = '';
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

                        // Format price with commas and store auto-price
                        if (price) {
                            const formattedPrice = parseFloat(price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            priceField.value = formattedPrice;
                            priceField.setAttribute('data-auto-price', price);
                        } else {
                            priceField.value = '';
                        }

                        // Set dropdown to auto mode
                        if (priceDropdown) {
                            priceDropdown.value = 'auto';
                        }

                        // Set price field to auto mode (readonly)
                        priceField.setAttribute('readonly', 'readonly');
                        priceField.setAttribute('data-price-mode', 'auto');
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
                        const priceDropdown = document.getElementById('priceDropdown');

                        // Fallback: populate fields anyway with comma formatting
                        document.getElementById('item_model_input').value = code;

                        if (price) {
                            const formattedPrice = parseFloat(price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            priceField.value = formattedPrice;
                            priceField.setAttribute('data-auto-price', price);
                        } else {
                            priceField.value = '';
                        }

                        // Set dropdown to auto mode
                        if (priceDropdown) {
                            priceDropdown.value = 'auto';
                        }

                        // Set price field to auto mode
                        priceField.setAttribute('readonly', 'readonly');
                        priceField.setAttribute('data-price-mode', 'auto');
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
                    <td>${imei}</td>
                    <td style="text-align: center;">${quantity}<input type="hidden" class="qty-input" value="${quantity}"></td>
                    <td style="text-align: center;">${price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}<input type="hidden" class="price-input-table" value="${price}"></td>
                    <td style="text-align: center;"><button type="button" class="btn-delete-item" onclick="removeItemRow(this)">X</button></td>
                `;

                // Store item data
                newRow.setAttribute('data-item-code', itemCode || '');
                newRow.setAttribute('data-description', description);
                newRow.setAttribute('data-imei', imei);

                itemsTableBody.appendChild(newRow);

                // Clear input fields
                clearInputFields();

                // Update totals
                updateTotals();
            }
        }

        // Remove Item Row Function
        window.removeItemRow = function (btn) {
            const row = btn.closest('tr');
            row.remove();

            const itemsTableBody = document.getElementById('itemsTableBody');

            // If table is empty, show "no items" row
            if (itemsTableBody.children.length === 0) {
                itemsTableBody.innerHTML = '<tr id="no-items-row"><td colspan="5" style="text-align:center; padding: 20px;">No items added yet</td></tr>';
            }

            updateTotals();
        };

        // Clear Input Fields Function
        function clearInputFields() {
            document.getElementById('item_model_input').value = '';
            document.getElementById('imei_input').value = '';
            document.getElementById('quantity_input').value = '';
            document.getElementById('price_input').value = '';

            // Reset price dropdown to default state
            const priceDropdown = document.getElementById('priceDropdown');
            if (priceDropdown) {
                priceDropdown.value = '';
                priceDropdown.style.opacity = '0';
            }

            // Re-enable price field when clearing
            const priceField = document.getElementById('price_input');
            priceField.removeAttribute('readonly');
            priceField.removeAttribute('data-price-mode');
            priceField.removeAttribute('data-auto-price');
            priceField.style.backgroundColor = '#ffffff';
            priceField.style.color = '#333';
            priceField.style.cursor = 'pointer';
            priceField.placeholder = 'Click to select Auto or Custom';

            // DO NOT reset Payment Method dropdown - Payment Method dropdown removed

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
            // Recalculate the total amount
            calculateTotal();
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
                        newInvoiceInput.value = data.invoice_number;
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

        // Allow Enter key to trigger search
        document.addEventListener('DOMContentLoaded', function () {
            const invoiceInput = document.getElementById('invoice_no_input');
            if (invoiceInput) {
                invoiceInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        searchInvoice();
                    }
                });
            }

            // Generate new invoice number on page load
            generateNewInvoiceNumber();

            // Price Dropdown Handler
            const priceField = document.getElementById('price_input');
            const priceDropdown = document.getElementById('priceDropdown');

            // Helper function to format number with commas
            function formatPriceWithCommas(value) {
                // Remove all non-digit and non-decimal characters
                let num = value.replace(/[^\d.]/g, '');

                // Ensure only one decimal point
                const parts = num.split('.');
                if (parts.length > 2) {
                    num = parts[0] + '.' + parts.slice(1).join('');
                }

                // Split into integer and decimal parts
                const [intPart, decPart] = num.split('.');

                // Add commas to integer part
                const formattedInt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

                // Return formatted value
                return decPart !== undefined ? formattedInt + '.' + decPart : formattedInt;
            }

            // Helper function to remove commas for calculation
            function parseFormattedPrice(value) {
                return parseFloat(value.replace(/,/g, '')) || 0;
            }

            if (priceDropdown && priceField) {
                priceDropdown.addEventListener('change', function () {
                    if (this.value === 'auto') {
                        // Auto mode - readonly, will be filled automatically
                        priceField.setAttribute('readonly', 'readonly');
                        priceField.setAttribute('data-price-mode', 'auto');
                        priceField.style.backgroundColor = '#ffffff';
                        priceField.style.color = '#333';
                        priceField.style.cursor = 'not-allowed';
                        priceField.placeholder = 'Auto - Will be filled automatically';

                        // Restore auto price if available
                        const autoPrice = priceField.getAttribute('data-auto-price');
                        if (autoPrice) {
                            priceField.value = formatPriceWithCommas(autoPrice);
                        }
                    } else if (this.value === 'custom') {
                        // Custom mode - enable price field for manual input
                        priceField.removeAttribute('readonly');
                        priceField.setAttribute('data-price-mode', 'custom');
                        priceField.style.backgroundColor = '#ffffff';
                        priceField.style.color = '#333';
                        priceField.style.cursor = 'text';
                        priceField.placeholder = 'Enter custom price';
                        priceField.value = '';
                        priceField.focus();
                        priceDropdown.style.opacity = '0';
                    } else {
                        // No selection - readonly
                        priceField.setAttribute('readonly', 'readonly');
                        priceField.removeAttribute('data-price-mode');
                        priceField.style.backgroundColor = '#ffffff';
                        priceField.style.color = '#333';
                        priceField.style.cursor = 'pointer';
                        priceField.placeholder = 'Click to select Auto or Custom';
                    }
                });

                // Format price with commas as user types
                priceField.addEventListener('input', function (e) {
                    if (this.getAttribute('data-price-mode') === 'custom') {
                        const cursorPos = this.selectionStart;
                        const oldValue = this.value;
                        const oldLength = oldValue.length;

                        // Format the value
                        this.value = formatPriceWithCommas(this.value);

                        // Adjust cursor position after formatting
                        const newLength = this.value.length;
                        const diff = newLength - oldLength;
                        this.setSelectionRange(cursorPos + diff, cursorPos + diff);
                    }
                });

                // Prevent spacebar input
                priceField.addEventListener('keydown', function (e) {
                    if (e.key === ' ' || e.keyCode === 32) {
                        e.preventDefault();
                        return false;
                    }
                });

                // When clicking price field, show dropdown
                priceField.addEventListener('click', function () {
                    if (this.hasAttribute('readonly')) {
                        priceDropdown.style.opacity = '1';
                        priceDropdown.focus();
                    }
                });

                // Hide dropdown when it loses focus
                priceDropdown.addEventListener('blur', function () {
                    setTimeout(() => {
                        if (this.value !== 'custom') {
                            this.style.opacity = '0';
                        }
                    }, 200);
                });
            }

            // Add IMEI search on Enter key
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

            // Add Item Model search functionality
            const searchBtn = document.getElementById('search_item_btn');
            const itemModelInput = document.getElementById('item_model_input');
            const addBtn = document.getElementById('add_item_btn');

            if (searchBtn) {
                searchBtn.addEventListener('click', performItemSearch);
            }

            if (itemModelInput) {
                itemModelInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        performItemSearch();
                    }
                });
            }

            if (addBtn) {
                addBtn.addEventListener('click', addItemToTable);
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
            };
        });

        // Stores the original total amount due when the payment modal opens
        let _originalTotalAmountDue = 0;

        function populateInstallmentUnit() {
            const tbody = document.getElementById('itemsTableBody');
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.id || r.id !== 'no-items-row');
            const unitRows = document.querySelectorAll('.unit-selector-row');

            if (unitRows.length === 0) return;

            // Build item list from table rows
            const items = [];
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 2) {
                    const desc = cells[0].textContent.trim();
                    const serial = cells[1].textContent.trim();
                    if (desc) items.push({ desc, serial });
                }
            });

            unitRows.forEach(unitRow => {
                if (items.length === 0) {
                    unitRow.style.display = 'none';
                    unitRow.innerHTML = '';
                    return;
                }

                unitRow.style.display = '';
                // Container for custom multi-select dropdown
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
                    const checked = Array.from(container.querySelectorAll('input[type="checkbox"][name="Unit"]:checked'));
                    const allUnitCbs = Array.from(container.querySelectorAll('input[type="checkbox"][name="Unit"]'));
                    const selectAllCb = container.querySelector('.select-all-units-cb');

                    if (selectAllCb && allUnitCbs.length > 0) {
                        selectAllCb.checked = (checked.length === allUnitCbs.length);
                    }

                    if (checked.length === 0) textSpan.textContent = '-- Select Units --';
                    else if (checked.length === 1) textSpan.textContent = checked[0].value;
                    else if (allUnitCbs.length > 1 && checked.length === allUnitCbs.length) textSpan.textContent = 'All Units Selected (' + checked.length + ')';
                    else textSpan.textContent = checked.length + ' Units Selected';
                };

                // Add "Select All" option if there are multiple units
                if (items.length > 1) {
                    const selectAllLbl = document.createElement('label');
                    selectAllLbl.style.margin = '0 0 5px 0';
                    selectAllLbl.style.display = 'flex';
                    selectAllLbl.style.alignItems = 'center';
                    selectAllLbl.style.gap = '8px';
                    selectAllLbl.style.fontWeight = 'bold';
                    selectAllLbl.style.lineHeight = '1.2';
                    selectAllLbl.style.color = '#1e40af';
                    selectAllLbl.style.cursor = 'pointer';
                    selectAllLbl.style.padding = '5px';
                    selectAllLbl.style.borderBottom = '1px solid #e5e7eb';
                    selectAllLbl.style.borderRadius = '3px';
                    selectAllLbl.onmouseover = () => selectAllLbl.style.backgroundColor = '#eff6ff';
                    selectAllLbl.onmouseout = () => selectAllLbl.style.backgroundColor = 'transparent';

                    const selectAllCb = document.createElement('input');
                    selectAllCb.type = 'checkbox';
                    selectAllCb.className = 'select-all-units-cb';
                    selectAllCb.style.marginTop = '0';
                    selectAllCb.style.width = '16px';
                    selectAllCb.style.height = '16px';

                    selectAllCb.addEventListener('change', function () {
                        const unitCbs = container.querySelectorAll('input[type="checkbox"][name="Unit"]');
                        unitCbs.forEach(cb => cb.checked = this.checked);
                        updateText();
                    });

                    selectAllLbl.appendChild(selectAllCb);
                    selectAllLbl.appendChild(document.createTextNode('Select All'));
                    container.appendChild(selectAllLbl);
                }

                items.forEach((item) => {
                    const labelText = item.serial ? item.desc + ' (' + item.serial + ')' : item.desc;

                    const lbl = document.createElement('label');
                    lbl.style.margin = '0';
                    lbl.style.display = 'flex';
                    lbl.style.alignItems = 'flex-start';
                    lbl.style.gap = '8px';
                    lbl.style.fontWeight = 'normal';
                    lbl.style.lineHeight = '1.2';
                    lbl.style.color = '#333';
                    lbl.style.cursor = 'pointer';
                    lbl.style.padding = '5px';
                    lbl.style.borderRadius = '3px';
                    lbl.onmouseover = () => lbl.style.backgroundColor = '#f0f0f0';
                    lbl.onmouseout = () => lbl.style.backgroundColor = 'transparent';

                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.name = 'Unit';
                    cb.value = labelText;
                    cb.style.marginTop = '2px';
                    cb.style.width = '16px';
                    cb.style.height = '16px';

                    cb.addEventListener('change', updateText);

                    // Auto-check if only 1 item exists
                    if (items.length === 1) {
                        cb.checked = true;
                    }

                    lbl.appendChild(cb);
                    lbl.appendChild(document.createTextNode(labelText));
                    container.appendChild(lbl);
                });

                updateText(); // Initial text population
            });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.custom-multiselect')) {
                document.querySelectorAll('.multiselect-dropdown').forEach(d => d.style.display = 'none');
            }
        });

        // Payment Modal Functions
        let hasUnpaidBalance = false; // Track if there was an unpaid balance when modal opened

        function openPaymentModal() {
            const modal = document.getElementById('paymentModal');
            modal.style.display = 'flex';
            const totalAmountInput = document.getElementById('totalAmount');
            const globalTotalDueInput = document.getElementById('globalTotalDueInput');
            if (totalAmountInput && globalTotalDueInput) {
                const rawValue = totalAmountInput.value || '0';
                _originalTotalAmountDue = parseFloat(rawValue.replace(/,/g, '')) || 0;
                globalTotalDueInput.value = totalAmountInput.value || '0.00';
            }
            populateInstallmentUnit();
            if (typeof window.updateSectionTotal === 'function') {
                window.updateSectionTotal();
            }

            // Check if there's a balance to pay
            const remainingBalance = _originalTotalAmountDue;
            const isFullyPaid = remainingBalance <= 0;

            // Set flag if there's an unpaid balance when modal opens
            hasUnpaidBalance = remainingBalance > 0;

            // Note: newInvoiceNumberSection is now always visible at the top of the page
            // No need to show/hide it based on payment modal state

            populateInstallmentUnit();

            // Auto-check payment method removed - Payment Method dropdown no longer exists
        }

        function closePaymentModal() {
            const modal = document.getElementById('paymentModal');
            modal.style.display = 'none';
        }

        // Payment Partners Dropdown Handler
        document.addEventListener('DOMContentLoaded', function () {
            // Dropdowns
            const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
            const cardPaymentDropdown = document.getElementById('cardPaymentDropdown');
            const qrDropdown = document.getElementById('qrDropdown');

            // Radio Buttons
            const paymentRadios = document.querySelectorAll('input[name="payment_method"]');

            // Sections
            const homeCreditSection = document.querySelector('.home-credit-section');
            const creditCardSection = document.querySelector('.credit-card-section');
            const debitCardSection = document.querySelector('.debit-card-section');
            const qrPhSection = document.querySelector('.qr-ph-section');
            const starpayQrSection = document.querySelector('.starpay-qr-section');
            const ewalletSection = document.querySelector('.ewallet-section');
            const onlineBankingSection = document.querySelector('.online-banking-section');
            const cashSection = document.querySelector('.cash-section');

            if (paymentPartnersDropdown) {
                paymentPartnersDropdown.addEventListener('change', function () {
                    if (document.getElementById('chkPaymentPartners').checked) {
                        if (homeCreditSection) homeCreditSection.style.display = 'none';
                        const partnerTitle = document.getElementById('paymentPartnerTitle');
                        const loanTypeSelect = homeCreditSection ? homeCreditSection.querySelector('.hc-form-group:nth-child(2) select') : null;
                        if (this.value === 'partner1') {
                            if (homeCreditSection) homeCreditSection.style.display = 'block';
                            if (partnerTitle) partnerTitle.textContent = 'STO niño de cebu';
                            if (loanTypeSelect) {
                                loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                            }
                        } else if (this.value === 'partner2') {
                            if (homeCreditSection) homeCreditSection.style.display = 'block';
                            if (partnerTitle) partnerTitle.textContent = 'Home Credit';
                            if (loanTypeSelect) {
                                loanTypeSelect.innerHTML = '<option value=""></option><option value="0_installment">0% Installment</option><option value="standard_loan">Standard loan</option><option value="retailer_zero">Retailer Zero</option><option value="saver_plan">Saver Plan</option>';
                            }
                        } else if (this.value === 'partner3') {
                            if (homeCreditSection) homeCreditSection.style.display = 'block';
                            if (partnerTitle) partnerTitle.textContent = 'Bank of Makati';
                            if (loanTypeSelect) {
                                loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                            }
                        } else if (this.value === 'partner4') {
                            if (homeCreditSection) homeCreditSection.style.display = 'block';
                            if (partnerTitle) partnerTitle.textContent = 'Sumisho';
                            if (loanTypeSelect) {
                                loanTypeSelect.innerHTML = '<option value=""></option><option value="standard_loan">Standard Loan</option>';
                            }
                        }
                    }
                });
            }

            // Card Payment Dropdown Handler
            if (cardPaymentDropdown) {
                cardPaymentDropdown.addEventListener('change', function () {
                    if (this.disabled) return false;
                    if (document.getElementById('chkCardPayment').checked) {
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

            // QR Dropdown Handler
            if (qrDropdown) {
                qrDropdown.addEventListener('change', function () {
                    if (this.disabled) return false;
                    if (document.getElementById('chkQR').checked) {
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

            // Checkbox Handlers for Split Payment
            paymentRadios.forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    if (this.disabled) return false;

                    if (this.value === 'payment_partners' || this.id === 'chkPaymentPartners') {
                        if (this.checked) {
                            if (paymentPartnersDropdown.value === '') {
                                paymentPartnersDropdown.value = 'partner1';
                            }
                            const event = new Event('change');
                            paymentPartnersDropdown.dispatchEvent(event);
                        } else {
                            if (homeCreditSection) homeCreditSection.style.display = 'none';
                        }
                    } else if (this.value === 'card_payment' || this.id === 'chkCardPayment') {
                        if (this.checked) {
                            if (cardPaymentDropdown.value === '') {
                                cardPaymentDropdown.value = 'credit_card';
                            }
                            const event = new Event('change');
                            cardPaymentDropdown.dispatchEvent(event);
                        } else {
                            if (creditCardSection) creditCardSection.style.display = 'none';
                            if (debitCardSection) debitCardSection.style.display = 'none';
                        }
                    } else if (this.value === 'qr' || this.id === 'chkQR') {
                        if (this.checked) {
                            if (qrDropdown.value === '') {
                                qrDropdown.value = 'qr_ph';
                            }
                            const event = new Event('change');
                            qrDropdown.dispatchEvent(event);
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
                });
            });

            // Save Payment Data
            const saveBtn = document.querySelector('.btn-save-modal');
            if (saveBtn) {
                saveBtn.addEventListener('click', savePaymentData);
            }

            function savePaymentData() {
                // First, check which payment method is being used
                const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
                const cardPaymentDropdown = document.getElementById('cardPaymentDropdown');
                const qrDropdown = document.getElementById('qrDropdown');
                const paymentRadios = document.querySelectorAll('input[name="payment_method"]');

                // Check which section is visible
                const homeCreditSection = document.querySelector('.home-credit-section');
                const creditCardSection = document.querySelector('.credit-card-section');
                const debitCardSection = document.querySelector('.debit-card-section');
                const qrPhSection = document.querySelector('.qr-ph-section');
                const starpayQrSection = document.querySelector('.starpay-qr-section');
                const ewalletSection = document.querySelector('.ewallet-section');
                const onlineBankingSection = document.querySelector('.online-banking-section');
                const cashSection = document.querySelector('.cash-section');

                // Determine which payment methods are active
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

                // Validate based on active payment sections
                if (homeCreditSection && homeCreditSection.style.display === 'block') {
                    if (paymentPartnersDropdown && paymentPartnersDropdown.value === '') {
                        alert('Please choose a Payment Partner option in order to proceed!');
                        paymentPartnersDropdown.focus();
                        return;
                    }

                    // Validate Home Credit (STO ninio de cebu) fields
                    if (homeCreditSection && homeCreditSection.style.display === 'block') {
                        const loanTypeSelect = homeCreditSection.querySelector('.hc-form-group:nth-child(2) select');
                        const loanTermsSelect = homeCreditSection.querySelector('.hc-form-group:nth-child(3) select');
                        const customerNameInput = homeCreditSection.querySelector('.hc-form-group:nth-child(4) input');
                        const loanNumberInput = homeCreditSection.querySelector('.hc-form-group:nth-child(5) input');
                        const totalLoanAmountInput = homeCreditSection.querySelector('.hc-form-group:nth-child(6) input');
                        const loanBalanceInput = homeCreditSection.querySelector('.hc-form-group:nth-child(7) input');
                        const cashCheckbox = homeCreditSection.querySelector('input[name="down_payment_method"][value="cash"]');
                        const gcashCheckbox = homeCreditSection.querySelector('input[name="down_payment_method"][value="gcash"]');
                        const mayaCheckbox = homeCreditSection.querySelector('input[name="down_payment_method"][value="maya"]');
                        const gcashRefInput = document.getElementById('gcash_down_payment_reference_upgrade');
                        const mayaRefInput = document.getElementById('maya_down_payment_reference_upgrade');
                        const cashAmountInput = document.getElementById('cash_down_payment_amount_upgrade');
                        const gcashAmountInput = document.getElementById('gcash_down_payment_amount_upgrade');
                        const mayaAmountInput = document.getElementById('maya_down_payment_amount_upgrade');

                        // Validate Loan Type
                        if (!loanTypeSelect || !loanTypeSelect.value || loanTypeSelect.value.trim() === '') {
                            alert('LOAN TYPE REQUIRED! Please select a loan type.');
                            if (loanTypeSelect) loanTypeSelect.focus();
                            return;
                        }

                        // Validate Loan Terms
                        if (!loanTermsSelect || !loanTermsSelect.value || loanTermsSelect.value.trim() === '') {
                            alert('LOAN TERMS REQUIRED! Please select loan terms.');
                            if (loanTermsSelect) loanTermsSelect.focus();
                            return;
                        }

                        // Validate Customer's Name
                        if (!customerNameInput || !customerNameInput.value || customerNameInput.value.trim() === '') {
                            alert("CUSTOMER'S NAME REQUIRED! Please enter the customer's name.");
                            if (customerNameInput) customerNameInput.focus();
                            return;
                        }

                        // Validate Loan Number
                        if (!loanNumberInput || !loanNumberInput.value || loanNumberInput.value.trim() === '') {
                            alert('LOAN NUMBER REQUIRED! Please enter the loan number.');
                            if (loanNumberInput) loanNumberInput.focus();
                            return;
                        }

                        // Validate Loan Balance
                        if (!loanBalanceInput || !loanBalanceInput.value || loanBalanceInput.value.trim() === '') {
                            alert('LOAN BALANCE REQUIRED! Please enter the loan balance.');
                            if (loanBalanceInput) loanBalanceInput.focus();
                            return;
                        }

                        // Validate Down Payment Method (at least one checkbox must be checked)
                        // Validate Down Payment Method (at least one checkbox must be checked)
                        const isAnyDownPaymentChecked = ((cashCheckbox && cashCheckbox.checked) || (gcashCheckbox && gcashCheckbox.checked) || (mayaCheckbox && mayaCheckbox.checked));
                        if (!isAnyDownPaymentChecked) {
                            alert('DOWN PAYMENT METHOD REQUIRED! Please check at least one payment method (Cash, G-Cash, or Maya).');
                            return;
                        }

                        // Validate Cash Amount if Cash is checked
                        if (cashCheckbox && cashCheckbox.checked) {
                            if (!cashAmountInput || !cashAmountInput.value || cashAmountInput.value.trim() === '') {
                                alert('ENTER AMOUNT REQUIRED! Please enter the Cash down payment amount.');
                                if (cashAmountInput) cashAmountInput.focus();
                                return;
                            }
                        }

                        // Validate G-Cash Reference Number & Amount if G-Cash is checked
                        if (gcashCheckbox && gcashCheckbox.checked) {
                            if (!gcashRefInput || !gcashRefInput.value || gcashRefInput.value.trim() === '') {
                                alert('G-CASH REFERENCE NUMBER REQUIRED! Please enter the G-Cash reference number.');
                                if (gcashRefInput) gcashRefInput.focus();
                                return;
                            }
                            if (!gcashAmountInput || !gcashAmountInput.value || gcashAmountInput.value.trim() === '') {
                                alert('ENTER AMOUNT REQUIRED! Please enter the G-Cash down payment amount.');
                                if (gcashAmountInput) gcashAmountInput.focus();
                                return;
                            }
                        }

                        // Validate Maya Reference Number & Amount if Maya is checked
                        if (mayaCheckbox && mayaCheckbox.checked) {
                            if (!mayaRefInput || !mayaRefInput.value || mayaRefInput.value.trim() === '') {
                                alert('MAYA REFERENCE NUMBER REQUIRED! Please enter the Maya reference number.');
                                if (mayaRefInput) mayaRefInput.focus();
                                return;
                            }
                            if (!mayaAmountInput || !mayaAmountInput.value || mayaAmountInput.value.trim() === '') {
                                alert('ENTER AMOUNT REQUIRED! Please enter the Maya down payment amount.');
                                if (mayaAmountInput) mayaAmountInput.focus();
                                return;
                            }
                        }
                    }
                }
                if ((creditCardSection && creditCardSection.style.display === 'block') || (debitCardSection && debitCardSection.style.display === 'block')) {
                    if (cardPaymentDropdown && cardPaymentDropdown.value === '') {
                        alert('Please choose a Card Payment option in order to proceed!');
                        cardPaymentDropdown.focus();
                        return;
                    }
                }
                if ((qrPhSection && qrPhSection.style.display === 'block') || (starpayQrSection && starpayQrSection.style.display === 'block')) {
                    if (qrDropdown && qrDropdown.value === '') {
                        alert('Please choose a QR option in order to proceed!');
                        qrDropdown.focus();
                        return;
                    }
                }
                if (ewalletSection && ewalletSection.style.display === 'block') {
                    // Validate E-Wallet fields
                    if (ewalletSection && ewalletSection.style.display === 'block') {
                        const ewalletSelect = ewalletSection.querySelector('.hc-form-group:nth-child(2) select');
                        const customerNameInput = ewalletSection.querySelector('.hc-form-group:nth-child(3) input');
                        const referenceNoInput = ewalletSection.querySelector('.hc-form-group:nth-child(4) input');
                        const amountInput = ewalletSection.querySelector('.hc-form-group:nth-child(5) input');

                        // Validate E-Wallet selection
                        if (!ewalletSelect || !ewalletSelect.value || ewalletSelect.value.trim() === '') {
                            alert('E-WALLET REQUIRED! Please select an e-wallet.');
                            if (ewalletSelect) ewalletSelect.focus();
                            return;
                        }

                        // Get selected e-wallet name for dynamic error message
                        const selectedEwalletName = ewalletSelect.options[ewalletSelect.selectedIndex].text;

                        // Validate Customer's Name
                        if (!customerNameInput || !customerNameInput.value || customerNameInput.value.trim() === '') {
                            alert("CUSTOMER'S NAME REQUIRED! Please enter the customer's name.");
                            if (customerNameInput) customerNameInput.focus();
                            return;
                        }

                        // Validate Reference Number with e-wallet-specific message
                        if (!referenceNoInput || !referenceNoInput.value || referenceNoInput.value.trim() === '') {
                            alert(selectedEwalletName.toUpperCase() + ' REFERENCE NUMBER REQUIRED! Please enter the ' + selectedEwalletName + ' reference number.');
                            if (referenceNoInput) referenceNoInput.focus();
                            return;
                        }

                        // Validate Amount
                        if (!amountInput || !amountInput.value || amountInput.value.trim() === '') {
                            alert('AMOUNT REQUIRED! Please enter the payment amount.');
                            if (amountInput) amountInput.focus();
                            return;
                        }
                    }
                }
                if (onlineBankingSection && onlineBankingSection.style.display === 'block') {
                    // Validate Online Banking fields
                    if (onlineBankingSection && onlineBankingSection.style.display === 'block') {
                        const bankSelect = onlineBankingSection.querySelector('.hc-form-group:nth-child(2) select');
                        const referenceNoInput = onlineBankingSection.querySelector('.hc-form-group:nth-child(3) input');
                        const amountInput = onlineBankingSection.querySelector('.hc-form-group:nth-child(4) input');

                        // Validate Bank selection
                        if (!bankSelect || !bankSelect.value || bankSelect.value.trim() === '') {
                            alert('BANK REQUIRED! Please select a bank.');
                            if (bankSelect) bankSelect.focus();
                            return;
                        }

                        // Get selected bank name for dynamic error message
                        const selectedBankName = bankSelect.options[bankSelect.selectedIndex].text;

                        // Validate Reference Number with bank-specific message
                        if (!referenceNoInput || !referenceNoInput.value || referenceNoInput.value.trim() === '') {
                            alert(selectedBankName.toUpperCase() + ' REFERENCE NUMBER REQUIRED! Please enter the ' + selectedBankName + ' reference number.');
                            if (referenceNoInput) referenceNoInput.focus();
                            return;
                        }

                        // Validate Amount
                        if (!amountInput || !amountInput.value || amountInput.value.trim() === '') {
                            alert('AMOUNT REQUIRED! Please enter the payment amount.');
                            if (amountInput) amountInput.focus();
                            return;
                        }
                    }
                }
                if (cashSection && cashSection.style.display === 'block') {
                    // Cash validation will be handled by hasValues check below
                }

                const data = {};
                let sectionName = '';
                let isValid = false;
                let hasValues = false;

                function collectData(sectionClass, type) {
                    const section = document.querySelector(sectionClass);
                    if (section && section.style.display === 'block') {
                        if (sectionName) {
                            sectionName += ' + ' + type;
                        } else {
                            sectionName = type;
                        }
                        data.payment_type = sectionName;
                        isValid = true;

                        const inputs = section.querySelectorAll('input, select');

                        inputs.forEach(input => {
                            if (input.type === 'hidden') return;

                            let key = input.id;

                            if (!key) {
                                const formGroup = input.closest('.hc-form-group');
                                if (formGroup) {
                                    const label = formGroup.querySelector('label');
                                    if (label) {
                                        key = label.innerText.replace(':', '').trim();
                                    }
                                }

                                if (!key) {
                                    const parentRow = input.closest('.enter-amount-row');
                                    if (parentRow) {
                                        const label = parentRow.querySelector('label');
                                        if (label) key = label.innerText.replace(':', '').trim();
                                    }
                                }
                            }

                            if (!key && input.name) key = input.name;
                            if (!key && input.className) key = input.className;

                            if (!key) return;

                            if (input.type === 'checkbox' || input.type === 'radio') {
                                if (input.checked) {
                                    hasValues = true;
                                    if (input.name === 'payment_method') return;

                                    if (data[key]) {
                                        data[key] += ', ' + input.value;
                                    } else {
                                        data[key] = input.value;
                                    }
                                }
                            } else {
                                if (input.tagName === 'SELECT') {
                                    data[key] = data[key] ? data[key] + ' | ' + input.value : input.value;
                                    if (key === 'E-Wallet' && input.selectedIndex > 0) {
                                        data['E-Wallet-Text'] = data['E-Wallet-Text'] ? data['E-Wallet-Text'] + ' | ' + input.options[input.selectedIndex].text : input.options[input.selectedIndex].text;
                                    }
                                    if (key === 'Bank' && input.selectedIndex > 0) {
                                        data['Bank-Text'] = data['Bank-Text'] ? data['Bank-Text'] + ' | ' + input.options[input.selectedIndex].text : input.options[input.selectedIndex].text;
                                    }
                                } else {
                                    data[key] = data[key] ? data[key] + ' | ' + input.value : input.value;
                                }
                                if (input.value && input.value.trim() !== '') {
                                    hasValues = true;
                                }
                            }
                        });

                        // Capture Total from global total
                        const globalTotalInput = document.getElementById('globalTotalInput');
                        if (globalTotalInput) {
                            data['Total'] = globalTotalInput.value;
                            if (globalTotalInput.value && globalTotalInput.value.trim() !== '') hasValues = true;
                        }

                        return true;
                    }
                    return false;
                }

                const sections = [
                    { class: '.home-credit-section', name: 'Home Credit' },
                    { class: '.credit-card-section', name: 'Credit Card' },
                    { class: '.debit-card-section', name: 'Debit Card' },
                    { class: '.qr-ph-section', name: 'QR PH' },
                    { class: '.starpay-qr-section', name: 'Starpay QR' },
                    { class: '.ewallet-section', name: 'E-Wallet' },
                    { class: '.online-banking-section', name: 'Online Banking' },
                    { class: '.cash-section', name: 'Cash' }
                ];

                for (const sec of sections) {
                    collectData(sec.class, sec.name);
                }

                if (isValid) {
                    if (!hasValues) {
                        alert('Please fill in the payment details before saving.');
                        return;
                    }

                    console.log('Saving Payment Data:', data);
                    const paymentDataInput = document.getElementById('payment_data');
                    if (paymentDataInput) {
                        paymentDataInput.value = JSON.stringify(data);
                    }

                    // Global Payment Validation: Sum of ALL entered payments must equal Total Amount Due
                    const globalTotalInputCheck = document.getElementById('globalTotalInput');
                    const overallTotalPayment = parseFloat(globalTotalInputCheck ? (globalTotalInputCheck.value || '0').replace(/,/g, '') : '0') || 0;

                    const overallDifference = _originalTotalAmountDue - overallTotalPayment;

                    if (_originalTotalAmountDue > 0 && Math.abs(overallDifference) > 0.01) {
                        const bkBanner = document.getElementById('paymentBreakdownBanner');
                        if (bkBanner) bkBanner.style.display = 'none';

                        const neededDisp = _originalTotalAmountDue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const enteredDisp = overallTotalPayment.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const isExceeded = overallDifference < -0.01;
                        const diffLabel = isExceeded ? 'Exceeded Amount:' : 'Remaining Balance:';
                        const diffAmount = Math.abs(overallDifference);
                        const diffDisp = diffAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        // Build detailed unit summary directly from DOM
                        let oldUnitRowsHtml = '';
                        const oldUnitCheckboxes = document.querySelectorAll('input[name="item_select"]:checked');
                        oldUnitCheckboxes.forEach(checkbox => {
                            const descText = checkbox.dataset.description || '';
                            const pVal = parseFloat(checkbox.dataset.price) || 0;
                            if (descText) {
                                oldUnitRowsHtml += `
                                    <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 13px; color: #b91c1c; margin-top: 2px;">
                                        <span style="font-style: italic; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">- ${descText}</span>
                                        <span>- ₱${pVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                    </div>
                                `;
                            }
                        });

                        if (oldUnitRowsHtml) {
                            oldUnitRowsHtml = `
                                <div style="margin-bottom: 6px;">
                                    <div style="font-weight: 600; color: #991b1b; font-size: 13px;">Old unit:</div>
                                    ${oldUnitRowsHtml}
                                </div>
                            `;
                        }

                        let unitRowsHtml = '';
                        const unitRows = document.querySelectorAll('#itemsTableBody tr:not(#no-items-row)');
                        unitRows.forEach(row => {
                            const tds = row.querySelectorAll('td');
                            if (tds.length >= 4) {
                                const descText = tds[0].textContent.trim();
                                const priceInput = row.querySelector('.price-input-table');
                                const qtyInput = row.querySelector('.qty-input');

                                if (descText && priceInput) {
                                    let pVal = parseFloat(priceInput.value.replace(/,/g, '')) || 0;
                                    let qVal = parseInt(qtyInput ? qtyInput.value : 1) || 1;
                                    let rowTotal = pVal * qVal;
                                    unitRowsHtml += `
                                        <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 13px; color: #1e3a8a; margin-top: 2px;">
                                            <span style="font-style: italic; max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">- ${descText}</span>
                                            <span>₱${rowTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                        </div>
                                    `;
                                }
                            }
                        });

                        if (unitRowsHtml) {
                            unitRowsHtml = `
                                <div style="margin-bottom: 6px;">
                                    <div style="font-weight: 600; color: #1e40af; font-size: 13px;">Upgrade Unit(s) to Pay:</div>
                                    ${unitRowsHtml}
                                </div>
                            `;
                        }

                        // Build detailed breakdown of what the user entered
                        let breakdownHtml = '<div style="margin: 8px 0; padding: 6px 0; border-top: 1px solid #fecaca; border-bottom: 1px solid #fecaca;">';
                        sections.forEach(sec => {
                            const secEl = document.querySelector(sec.class);
                            if (secEl && secEl.style.display === 'block') {
                                let secTotal = 0;
                                let secBreakdownRows = '';
                                const inputs = secEl.querySelectorAll('input[type="text"], input[type="number"]');

                                inputs.forEach(input => {
                                    let isAmount = false;
                                    let labelText = '';

                                    // Identify if input is an amount field and scrape its label
                                    if (input.classList.contains('amount-input')) {
                                        isAmount = true;
                                        if (input.previousElementSibling && input.previousElementSibling.tagName.toLowerCase() === 'label') {
                                            labelText = input.previousElementSibling.innerText.replace(':', '').trim();
                                        }
                                    }
                                    // Specific DP input IDs → correct label names
                                    if (input.id === 'cash_down_payment_amount_upgrade') { isAmount = true; labelText = 'Cash (DP)'; }
                                    if (input.id === 'gcash_down_payment_amount_upgrade') { isAmount = true; labelText = 'G-Cash (DP)'; }
                                    if (input.id === 'maya_down_payment_amount_upgrade') { isAmount = true; labelText = 'Maya (DP)'; }
                                    // Exclude totalLoanAmountUpgrade from payment calculation - it's for reference only
                                    if (input.id === 'totalLoanAmountUpgrade') { isAmount = false; }
                                    if (input.id && input.id.toLowerCase().includes('amount') && input.id !== 'totalLoanAmountUpgrade' && !labelText) { isAmount = true; labelText = 'Amount'; }

                                    const formGroup = input.closest('.hc-form-group');
                                    if (formGroup && !labelText) {
                                        const label = formGroup.querySelector('label');
                                        if (label && (label.innerText.includes('Amount') || label.innerText.includes('Loan Balance'))) {
                                            // Exclude Total Loan Amount from payment calculation
                                            if (!label.innerText.includes('Total Loan')) {
                                                isAmount = true;
                                                labelText = label.innerText.replace(':', '').trim();
                                            }
                                        }
                                    }
                                    const enterAmountRow = input.closest('.enter-amount-row');
                                    if (enterAmountRow) {
                                        const label = enterAmountRow.querySelector('label');
                                        if (label && label.innerText.includes('Amount')) {
                                            isAmount = true;

                                            // Detect which downpayment methods (Cash, G-Cash, Maya) are selected
                                            let dpMethods = [];
                                            const dpCbs = secEl.querySelectorAll('input[name="down_payment_method"]:checked');
                                            dpCbs.forEach(cb => {
                                                if (cb.value === "cash") dpMethods.push("Cash");
                                                if (cb.value === "gcash") dpMethods.push("G-Cash");
                                                if (cb.value === "maya") dpMethods.push("Maya");
                                            });

                                            if (dpMethods.length > 0) {
                                                labelText = dpMethods.join(' & ') + ' (DP)';
                                            } else {
                                                labelText = 'Downpayment';
                                            }
                                        }
                                    }
                                    if (isAmount && !labelText) labelText = 'Amount';

                                    if (isAmount) {
                                        let val = parseFloat(input.value.replace(/,/g, '')) || 0;
                                        if (val > 0) {
                                            secTotal += val;
                                            secBreakdownRows += `
                                                <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 12px; color: #b91c1c; margin-top: 2px;">
                                                    <span>- ${labelText}</span>
                                                    <span>₱${val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                </div>
                                            `;
                                        }
                                    }
                                });

                                if (secTotal > 0) {
                                    let displayName = sec.name;
                                    if (sec.name === 'Home Credit' && paymentPartnersDropdown && paymentPartnersDropdown.value !== '') {
                                        displayName = paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text;
                                    }
                                    if (sec.name === 'E-Wallet' && data['E-Wallet-Text']) displayName = data['E-Wallet-Text'];
                                    if (sec.name === 'Online Banking' && data['Bank-Text']) displayName = data['Bank-Text'];

                                    breakdownHtml += `
                                        <div style="margin-bottom: 6px;">
                                            <div style="font-weight: 600; color: #991b1b; font-size: 13px;">${displayName}:</div>
                                            ${secBreakdownRows}
                                        </div>
                                    `;
                                }
                            }
                        });
                        breakdownHtml += '</div>';

                        const banner = document.getElementById('paymentErrorBanner');
                        if (banner) {
                            banner.innerHTML = `
                                <div style="display: flex; align-items: flex-start; gap: 12px;">
                                    <div style="color: #ef4444; margin-top: 2px;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="12" y1="8" x2="12" y2="12"></line>
                                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                        </svg>
                                    </div>
                                    <div style="flex: 1; color: #991b1b; font-size: 13px; line-height: 1.6;">
                                        <div style="font-weight: 600; font-size: 14px; color: #7f1d1d; margin-bottom: 4px;">Payment Mismatch</div>
                                        <div>The total payment entered does not match the Total Amount Due. Please check your breakdown below:</div>
                                        <div style="margin-top: 6px; display: flex; flex-direction: column; gap: 2px; max-width: 450px; padding-top: 6px;">
                                            
                                            ${unitRowsHtml}
                                            ${oldUnitRowsHtml}
                                            
                                            <div style="display: flex; justify-content: space-between; border-bottom: 1.5px solid #fca5a5; padding-bottom: 4px; margin-bottom: 2px;">
                                                <span style="font-weight: 600;">Total Amount Due:</span><span style="font-weight: 700;">₱${neededDisp}</span>
                                            </div>
                                            
                                            ${breakdownHtml}
                                            
                                            <div style="display: flex; justify-content: space-between;">
                                                <span>Total Entered:</span><span style="font-weight: 600;">₱${enteredDisp}</span>
                                            </div>
                                            <div style="display: flex; justify-content: space-between; margin-top: 2px; padding-top: 4px; border-top: 1.5px dashed #fca5a5;">
                                                <span style="color: #7f1d1d; font-weight: 600;">${diffLabel}</span><span style="color: #ef4444; font-weight: 600;">₱${diffDisp}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            banner.style.display = 'block';
                            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                        return; // Block save
                    } else {
                        // Clear success
                        const banner = document.getElementById('paymentErrorBanner');
                        if (banner) { banner.style.display = 'none'; banner.innerHTML = ''; }
                    }

                    // Update Payment Button
                    const btnPayment = document.querySelector('.btn-payment');
                    if (btnPayment) {
                        let buttonText = `Payment: ${sectionName}`;

                        if (sectionName === 'E-Wallet' && data['E-Wallet-Text']) {
                            buttonText = `Payment: ${data['E-Wallet-Text']}`;
                        } else if (sectionName === 'Online Banking' && data['Bank-Text']) {
                            buttonText = `Payment: ${data['Bank-Text']}`;
                        } else if (sectionName === 'Home Credit') {
                            buttonText = `Payment: Home Credit`;
                        }

                        btnPayment.innerText = buttonText;
                        btnPayment.style.backgroundColor = '#2E7D32';
                        btnPayment.style.color = 'white';
                    }

                    alert('Payment details saved successfully!');
                    closePaymentModal();
                } else {
                    alert('Please select a payment method.');
                }
            }

            // Function to update global total based on input across all sections
            function updateSectionTotal() {
                let globalTotal = 0;

                const sections = [
                    '.home-credit-section',
                    '.credit-card-section',
                    '.debit-card-section',
                    '.qr-ph-section',
                    '.starpay-qr-section',
                    '.ewallet-section',
                    '.online-banking-section',
                    '.cash-section'
                ];

                sections.forEach(secClass => {
                    const sec = document.querySelector(secClass);
                    if (sec && sec.style.display === 'block') {
                        const inputs = sec.querySelectorAll('input[type="text"], input[type="number"]');
                        inputs.forEach(input => {
                            // Skip non-amount inputs
                            let isAmount = false;
                            if (input.classList.contains('amount-input')) isAmount = true;
                            // Exclude totalLoanAmountUpgrade from payment calculation - it's for reference only
                            if (input.id && input.id.toLowerCase().includes('amount') && input.id !== 'totalLoanAmountUpgrade') isAmount = true;
                            const formGroup = input.closest('.hc-form-group');
                            if (formGroup) {
                                const label = formGroup.querySelector('label');
                                if (label && label.innerText.includes('Amount') && !label.innerText.includes('Total Loan')) isAmount = true;
                                if (label && label.innerText.includes('Loan Balance')) isAmount = true;
                            }
                            const enterAmountRow = input.closest('.enter-amount-row');
                            if (enterAmountRow) {
                                const label = enterAmountRow.querySelector('label');
                                if (label && label.innerText.includes('Amount')) isAmount = true;
                            }

                            if (isAmount) {
                                let val = parseFloat(input.value.replace(/,/g, '')) || 0;
                                globalTotal += val;
                            }
                        });
                    }
                });

                const globalTotalInput = document.getElementById('globalTotalInput');
                if (globalTotalInput) {
                    globalTotalInput.value = globalTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                // Update TOTAL AMOUNT DUE: original total minus what has been paid so far
                const globalTotalDueInput = document.getElementById('globalTotalDueInput');
                if (globalTotalDueInput) {
                    let remaining = _originalTotalAmountDue - globalTotal;
                    if (remaining < 0) remaining = 0;
                    globalTotalDueInput.value = remaining.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                // Note: newInvoiceNumberSection is now always visible at the top of the page
                // No need to show/hide it based on payment calculations

                // Render dynamic breakdown box
                renderPaymentBreakdown(globalTotal);
            }
            window.updateSectionTotal = updateSectionTotal;

            function renderPaymentBreakdown(globalTotal) {
                const banner = document.getElementById('paymentBreakdownBanner');
                if (!banner) return;

                if (_originalTotalAmountDue <= 0 && globalTotal <= 0) {
                    banner.style.display = 'none';
                    return;
                }

                const neededDisp = _originalTotalAmountDue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const enteredDisp = globalTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const overallDifference = _originalTotalAmountDue - globalTotal;
                const isExceeded = overallDifference < -0.01;
                const isPerfectMatch = (Math.abs(overallDifference) <= 0.01 && globalTotal > 0);
                const diffLabel = isExceeded ? 'Exceeded Amount:' : 'Remaining Balance:';
                const diffAmount = Math.abs(overallDifference);
                const diffDisp = diffAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                // Build detailed unit summary directly from DOM
                // Build detailed old unit summary directly from DOM
                let oldUnitRowsHtml = '';
                const oldUnitCheckboxes = document.querySelectorAll('input[name="item_select"]:checked');
                oldUnitCheckboxes.forEach(checkbox => {
                    const descText = checkbox.dataset.description || '';
                    const pVal = parseFloat(checkbox.dataset.price) || 0;
                    if (descText) {
                        oldUnitRowsHtml += `
                            <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 13px; color: #000000ff; margin-top: 2px;">
                                <span style="font-style: italic; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">- ${descText}</span>
                                <span>- ₱${pVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                            </div>
                        `;
                    }
                });

                if (oldUnitRowsHtml) {
                    oldUnitRowsHtml = `
                        <div style="margin-bottom: 6px;">
                            <div style="font-weight: 600; color: #14532d; font-size: 13px;">Old unit:</div>
                            ${oldUnitRowsHtml}
                        </div>
                    `;
                }

                let unitRowsHtml = '';
                const unitRows = document.querySelectorAll('#itemsTableBody tr:not(#no-items-row)');
                unitRows.forEach(row => {
                    const tds = row.querySelectorAll('td');
                    if (tds.length >= 4) {
                        const descText = tds[0].textContent.trim();
                        const priceInput = row.querySelector('.price-input-table');
                        const qtyInput = row.querySelector('.qty-input');

                        if (descText && priceInput) {
                            let pVal = parseFloat(priceInput.value.replace(/,/g, '')) || 0;
                            let qVal = parseInt(qtyInput ? qtyInput.value : 1) || 1;
                            let rowTotal = pVal * qVal;
                            unitRowsHtml += `
                                <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 13px; color: #000000ff; margin-top: 2px;">
                                    <span style="font-style: italic; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">- ${descText}</span>
                                    <span>₱${rowTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                </div>
                            `;
                        }
                    }
                });

                if (unitRowsHtml) {
                    unitRowsHtml = `
                        <div style="margin-bottom: 6px;">
                            <div style="font-weight: 600; color: #000000ff; font-size: 13px;">Upgrade Unit(s) to Pay:</div>
                            ${unitRowsHtml}
                        </div>
                    `;
                }

                let breakdownInner = '';
                const sections = [
                    { class: '.home-credit-section', name: 'Home Credit' },
                    { class: '.credit-card-section', name: 'Credit Card' },
                    { class: '.debit-card-section', name: 'Debit Card' },
                    { class: '.qr-ph-section', name: 'QR PH' },
                    { class: '.starpay-qr-section', name: 'Starpay QR' },
                    { class: '.ewallet-section', name: 'E-Wallet' },
                    { class: '.online-banking-section', name: 'Online Banking' },
                    { class: '.cash-section', name: 'Cash' }
                ];

                sections.forEach(sec => {
                    const secEl = document.querySelector(sec.class);
                    if (secEl && secEl.style.display === 'block') {
                        let secTotal = 0;
                        let secBreakdownRows = '';
                        const inputs = secEl.querySelectorAll('input[type="text"], input[type="number"]');

                        inputs.forEach(input => {
                            let isAmount = false;
                            let labelText = '';

                            if (input.classList.contains('amount-input')) {
                                isAmount = true;
                                if (input.previousElementSibling && input.previousElementSibling.tagName.toLowerCase() === 'label') {
                                    labelText = input.previousElementSibling.innerText.replace(':', '').trim();
                                }
                            }
                            // Specific DP input IDs → correct label names
                            if (input.id === 'cash_down_payment_amount_upgrade') { isAmount = true; labelText = 'Cash (DP)'; }
                            if (input.id === 'gcash_down_payment_amount_upgrade') { isAmount = true; labelText = 'G-Cash (DP)'; }
                            if (input.id === 'maya_down_payment_amount_upgrade') { isAmount = true; labelText = 'Maya (DP)'; }
                            if (input.id && input.id.toLowerCase().includes('amount') && !labelText) { isAmount = true; labelText = 'Amount'; }

                            const formGroup = input.closest('.hc-form-group');
                            if (formGroup && !labelText) {
                                const label = formGroup.querySelector('label');
                                if (label && (label.innerText.includes('Amount') || label.innerText.includes('Loan Balance'))) {
                                    isAmount = true;
                                    labelText = label.innerText.replace(':', '').trim();
                                }
                            }
                            const enterAmountRow = input.closest('.enter-amount-row');
                            if (enterAmountRow) {
                                const label = enterAmountRow.querySelector('label');
                                if (label && label.innerText.includes('Amount')) {
                                    isAmount = true;

                                    let dpMethods = [];
                                    const dpCbs = secEl.querySelectorAll('input[name="down_payment_method"]:checked');
                                    dpCbs.forEach(cb => {
                                        if (cb.value === "cash") dpMethods.push("Cash");
                                        if (cb.value === "gcash") dpMethods.push("G-Cash");
                                        if (cb.value === "maya") dpMethods.push("Maya");
                                    });

                                    if (dpMethods.length > 0) {
                                        labelText = dpMethods.join(' & ') + ' (DP)';
                                    } else {
                                        labelText = 'Downpayment';
                                    }
                                }
                            }
                            if (isAmount && !labelText) labelText = 'Amount';

                            if (isAmount) {
                                let val = parseFloat(input.value.replace(/,/g, '')) || 0;
                                if (val > 0) {
                                    secTotal += val;
                                    secBreakdownRows += `
                                        <div style="display: flex; justify-content: space-between; padding-left: 12px; font-size: 12px; color: #000000ff; margin-top: 2px;">
                                            <span>- ${labelText}</span>
                                            <span>₱${val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                        </div>
                                    `;
                                }
                            }
                        });

                        if (secTotal > 0) {
                            let displayName = sec.name;
                            const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
                            if (sec.name === 'Home Credit' && paymentPartnersDropdown && paymentPartnersDropdown.value !== '') {
                                displayName = paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text;
                            }
                            if (sec.name === 'E-Wallet') {
                                const sel = secEl.querySelector('select');
                                if (sel && sel.selectedIndex > 0) displayName = sel.options[sel.selectedIndex].text;
                            }
                            if (sec.name === 'Online Banking') {
                                const sel = secEl.querySelector('select');
                                if (sel && sel.selectedIndex > 0) displayName = sel.options[sel.selectedIndex].text;
                            }

                            breakdownInner += `
                                <div style="margin-bottom: 6px;">
                                    <div style="font-weight: 600; color: #000000ff; font-size: 13px;">${displayName}:</div>
                                    ${secBreakdownRows}
                                </div>
                            `;
                        }
                    }
                });
                // Only wrap with bordered container if there are actual breakdown rows
                const breakdownHtml = breakdownInner
                    ? '<div style="margin: 8px 0; padding: 6px 0; border-top: 1px solid #cfcfcfff; border-bottom: 1px solid #cfcfcfff;">' + breakdownInner + '</div>'
                    : '';

                let statusText = "You are currently entering your breakdown details.";
                if (isPerfectMatch) {
                    statusText = "Payment matches Total Amount Due.";
                } else if (isExceeded) {
                    statusText = "Payment exceeds Total Amount Due. Please adjust your payment.";
                }
                let diffColor = isPerfectMatch ? "#16a34a" : (isExceeded ? "#dc2626" : "#ca8a04");

                banner.innerHTML = `
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="color: #000000ff; margin-top: 2px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </div>
                        <div style="flex: 1; color: #000000ff; font-size: 13px; line-height: 1.6;">
                            <div style="font-weight: 600; font-size: 14px; color: #000000ff; margin-bottom: 4px;">Payment Breakdown</div>
                            <div>${statusText}</div>
                            <div style="margin-top: 6px; display: flex; flex-direction: column; gap: 2px; max-width: 450px; padding-top: 6px;">
                                
                                ${unitRowsHtml}
                                ${oldUnitRowsHtml}
                                
                                <div style="display: flex; justify-content: space-between; border-bottom: 1.5px solid #ffffffff; padding-bottom: 4px; margin-bottom: 2px;">
                                    <span style="font-weight: 600;">Total Amount Due:</span><span style="font-weight: 700;">₱${neededDisp}</span>
                                </div>
                                
                                ${breakdownHtml}
                                
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Total Entered:</span><span style="font-weight: 600;">₱${enteredDisp}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-top: 2px; padding-top: 4px; border-top: 1.5px dashed #afafafff;">
                                    <span style="color: #000000ff; font-weight: 600;">${diffLabel}</span><span style="color: ${diffColor}; font-weight: 600;">₱${diffDisp}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                const errorBanner = document.getElementById('paymentErrorBanner');
                if (errorBanner && errorBanner.style.display === 'block') {
                    errorBanner.style.display = 'none';
                    errorBanner.innerHTML = '';
                }

                banner.style.display = 'block';
            }

            // Add observer or global listener for section toggles to immediately update sum
            document.body.addEventListener('change', function (e) {
                if (e.target.name === 'payment_method' || e.target.id === 'paymentPartnersDropdown' || e.target.id === 'cardPaymentDropdown' || e.target.id === 'qrDropdown') {
                    setTimeout(updateSectionTotal, 50);
                }
            });

            function formatInput(input) {
                // Save cursor position
                let cursorPosition = input.selectionStart;
                let oldValLength = input.value.length;

                // Remove non-numeric chars except dot
                let val = input.value.replace(/[^0-9.]/g, '');

                // Handle multiple dots
                const parts = val.split('.');
                if (parts.length > 2) {
                    val = parts[0] + '.' + parts.slice(1).join('');
                }

                // Add commas to integer part
                if (parts[0].length > 3) {
                    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                }

                const newVal = parts.join('.');
                input.value = newVal;

                // Restore cursor position accounting for added/removed commas
                if (document.activeElement === input) {
                    let diff = newVal.length - oldValLength;
                    let newPos = cursorPosition + diff;
                    // Keep cursor within bounds
                    newPos = Math.max(0, Math.min(newPos, newVal.length));
                    input.setSelectionRange(newPos, newPos);
                }
            }

            // Attach listeners to all identifiable amount inputs
            const allInputs = document.querySelectorAll('input[type="text"], input[type="number"]');

            allInputs.forEach(input => {
                // Skip if it is a total field itself
                if (input.classList.contains('total-input') || input.id === 'totalAmount' || input.id === 'totalQty' || input.id === 'discountField') return;

                let isAmount = false;

                // key checks
                if (input.classList.contains('amount-input')) isAmount = true;
                if (input.id && input.id.toLowerCase().includes('amount')) isAmount = true;

                // label checks
                const formGroup = input.closest('.hc-form-group');
                if (formGroup) {
                    const label = formGroup.querySelector('label');
                    if (label && label.innerText.includes('Amount')) isAmount = true;
                    if (label && label.innerText.includes('Loan Balance')) isAmount = true;
                }

                const enterAmountRow = input.closest('.enter-amount-row');
                if (enterAmountRow) {
                    const label = enterAmountRow.querySelector('label');
                    if (label && label.innerText.includes('Amount')) isAmount = true;
                }

                if (isAmount) {
                    if (input.type === 'number') input.type = 'text';

                    input.addEventListener('input', function () {
                        formatInput(this);
                        updateSectionTotal();
                    });
                    // Also update on blur to capture programmatic changes if any event dispatched, or delayed
                    input.addEventListener('blur', function () {
                        formatInput(this);
                        updateSectionTotal();
                    });
                }
            });
        });

        // Validate and Save Function
        function validateAndSave() {
            // 1. Check if invoice is searched and items are loaded
            const invoiceNo = document.getElementById('invoice_no_input').value.trim();
            if (!invoiceNo) {
                alert('Please search for an invoice first.');
                document.getElementById('invoice_no_input').focus();
                return false;
            }

            // 2. Check if at least one item is selected from the first table
            const selectedItems = document.querySelectorAll('input[name="item_select"]:checked');
            if (selectedItems.length === 0) {
                alert('Please select at least one item from the original invoice.');
                return false;
            }

            // 3. Check if reason is selected
            const reason = document.getElementById('reason_select').value.trim();
            if (!reason) {
                alert('Please select a reason for the upgrade.');
                document.getElementById('reason_select').focus();
                return false;
            }

            // 4. Check if at least one new item is added to the second table
            const itemsTableBody = document.getElementById('itemsTableBody');
            const newItems = itemsTableBody.querySelectorAll('tr:not(#no-items-row)');
            if (newItems.length === 0) {
                alert('Please add at least one upgrade item.');
                return false;
            }

            // 5. Check if payment is selected
            const btnPayment = document.querySelector('.btn-payment');
            const paymentText = btnPayment.innerText;
            if (paymentText === 'Payment') {
                alert('Please select a payment method.');
                return false;
            }
            const paymentDataInput = document.getElementById('payment_data');
            if (!paymentDataInput || !paymentDataInput.value) {
                alert('Please save payment details first.');
                return false;
            }

            // 6. Check if remarks is filled
            const remarksInput = document.getElementById('remarks_textarea') || document.querySelector('.remarks-section textarea');
            const remarks = remarksInput ? remarksInput.value.trim() : '';
            if (!remarks) {
                alert('Please enter remarks.');
                if (remarksInput) remarksInput.focus();
                return false;
            }

            // All validations passed, proceed with save
            saveUpgrade();

            return true;
        }

        // Save Upgrade Function
        function saveUpgrade() {
            // Show loading state
            const saveBtn = document.querySelector('.btn-save');
            const originalText = saveBtn.innerText;
            saveBtn.disabled = true;
            saveBtn.innerText = 'Saving...';

            // Get upgrade number from server
            fetch('get_next_upgrade_number.php')
                .then(response => response.text()) // Get as text first to see errors
                .then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Response from get_next_upgrade_number.php:', text);
                        throw new Error('Invalid JSON from get_next_upgrade_number.php. Check console for details.');
                    }
                })
                .then(result => {
                    if (result.status === 'success') {
                        const upgradeNo = result.upgrade_no;

                        // Collect all data
                        const upgradeData = collectUpgradeData(upgradeNo);

                        console.log('Sending upgrade data:', upgradeData);

                        // Send to server
                        return fetch('save_upgrade.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(upgradeData)
                        });
                    } else {
                        throw new Error('Failed to generate upgrade number: ' + (result.message || 'Unknown error'));
                    }
                })
                .then(response => response.text()) // Get as text first to see errors
                .then(text => {
                    console.log('Response from save_upgrade.php:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Server returned an error. Please check:\n1. Run create_upgrade_tables.sql in your database\n2. Check browser console for details');
                    }
                })
                .then(result => {
                    if (result.status === 'success') {
                        alert('Upgrade saved successfully!\nUpgrade No: ' + result.upgrade_no + '\nInvoice No: ' + result.invoice_no + '\n\nThe invoice has been updated with the new items.');
                        // Reset form or redirect
                        window.location.reload();
                    } else {
                        throw new Error(result.message || 'Failed to save upgrade');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving:\n' + error.message + '\n\nPlease check the browser console (F12) for more details.');
                })
                .finally(() => {
                    // Restore button state
                    saveBtn.disabled = false;
                    saveBtn.innerText = originalText;
                });
        }

        // Collect Upgrade Data
        function collectUpgradeData(upgradeNo) {
            const invoiceNo = document.getElementById('invoice_no_input').value.trim();
            const reason = document.getElementById('reason_select').value.trim();
            const remarksInput = document.getElementById('remarks_textarea') || document.querySelector('.remarks-section textarea');
            const remarks = remarksInput ? remarksInput.value.trim() : '';

            // Get old unit, new unit, and total amounts (remove commas)
            const oldUnitTotalStr = document.getElementById('oldUnitTotal').value;
            const newUnitTotalStr = document.getElementById('newUnitTotal').value;
            const totalAmountStr = document.getElementById('totalAmount').value;
            const oldUnitTotal = parseFloat(oldUnitTotalStr.replace(/,/g, '')) || 0;
            const newUnitTotal = parseFloat(newUnitTotalStr.replace(/,/g, '')) || 0;
            const totalAmount = parseFloat(totalAmountStr.replace(/,/g, '')) || 0;

            // Get customer data from the displayed customer details
            const customerData = window.currentCustomerData || {};

            // Collect old items (selected items from first table)
            const oldItems = [];
            const selectedCheckboxes = document.querySelectorAll('input[name="item_select"]:checked');
            selectedCheckboxes.forEach(checkbox => {
                oldItems.push({
                    description: checkbox.dataset.description || '',
                    imei: checkbox.dataset.imei || '',
                    price: parseFloat(checkbox.dataset.price) || 0
                });
            });

            // Collect new items (from second table)
            const newItems = [];
            const itemsTableBody = document.getElementById('itemsTableBody');
            const rows = itemsTableBody.querySelectorAll('tr:not(#no-items-row)');
            rows.forEach(row => {
                const qtyInput = row.querySelector('.qty-input');
                const priceInput = row.querySelector('.price-input-table');

                newItems.push({
                    item_code: row.getAttribute('data-item-code') || '',
                    description: row.getAttribute('data-description') || '',
                    imei: row.getAttribute('data-imei') || '',
                    quantity: parseInt(qtyInput.value) || 0,
                    price: parseFloat(priceInput.value) || 0
                });
            });

            // Get payment data (if saved from payment modal)
            const paymentDataInput = document.getElementById('payment_data');
            let paymentData = {};
            if (paymentDataInput && paymentDataInput.value) {
                try {
                    paymentData = JSON.parse(paymentDataInput.value);
                } catch (e) {
                    console.error('Error parsing payment data:', e);
                    paymentData = {};
                }
            }

            const newInvoiceInput = document.getElementById('newInvoiceNumberInput');
            const newInvoiceNo = (newInvoiceInput && newInvoiceInput.value) ? newInvoiceInput.value.trim() : '';
            const bookletId = (newInvoiceInput && newInvoiceInput.dataset.bookletId) ? newInvoiceInput.dataset.bookletId : '';
            const bookletFormat = (newInvoiceInput && newInvoiceInput.dataset.bookletFormat) ? newInvoiceInput.dataset.bookletFormat : '';

            return {
                upgrade_no: upgradeNo,
                original_invoice_no: invoiceNo,
                new_invoice_no: newInvoiceNo,
                booklet_id: bookletId,
                booklet_format: bookletFormat,
                reason: reason,
                remarks: remarks,
                old_items: oldItems,
                new_items: newItems,
                less_amount: oldUnitTotal,
                total_amount: totalAmount,
                payment_data: paymentData,
                customer_data: customerData
            };
        }
    </script>
</body>

</html>