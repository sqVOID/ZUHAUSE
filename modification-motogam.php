<?php
require_once 'session_check.php';
include 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to Super-Admin and Sub-admin only
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
if (strcasecmp($system_level, 'Super-Admin') !== 0 && strcasecmp($system_level, 'Sub-admin') !== 0) {
    // Redirect non-authorized users to the report page
    header("Location: report.php");
    exit();
}

// Get logged in user's branch code
$branch_code = '000';
$user_branch_name = '';
if (isset($_SESSION['user_branch'])) {
    $user_branch_name = $_SESSION['user_branch'];
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch_name'");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
}

// Fetch active users for Assisted By dropdown
$branch_filter = '';
if (!empty($user_branch_name)) {
    $escaped_branch = $conn->real_escape_string($user_branch_name);
    $branch_filter = " AND (branch LIKE '%$escaped_branch%' OR branch = '$escaped_branch')";
}

$users_result = $conn->query("
    SELECT first_name, last_name
    FROM users 
    WHERE status = 'Activated' $branch_filter
    UNION ALL 
    SELECT first_name, last_name
    FROM accounts 
    WHERE status = 'Activated' $branch_filter
    UNION ALL
    SELECT name as first_name, '' as last_name
    FROM promoters
    WHERE status = 'Active' $branch_filter
    UNION ALL
    SELECT dealer_name as first_name, '(Dealer)' as last_name
    FROM dealers
    WHERE status = 'Active'
    ORDER BY first_name, last_name
");

// Fetch terminal issuers
$terminal_issuers_result = $conn->query("SELECT bank_name FROM terminal_issuers WHERE status='Active' ORDER BY bank_name");

// Fetch other banks (Active only) for Card Payment
$others_bank_result = $conn->query("SELECT bank_name FROM others_bank WHERE status='Active' ORDER BY bank_name");

// Fetch all unique banks from item_prices table for Card Payment Bank dropdowns
// Extract bank names from price_type field (format: "BankName TermName")
$item_banks_query = "SELECT DISTINCT 
    SUBSTRING_INDEX(price_type, ' ', 1) as bank_name 
    FROM item_prices 
    WHERE is_active = 1 
    AND price_type NOT IN ('__SRP__', 'Others Bank')
    AND price_type != ''
    ORDER BY bank_name";
$item_banks_result = $conn->query($item_banks_query);


// Fetch all bank-term combinations from item_prices for dynamic term population
$bank_terms_query = "SELECT DISTINCT price_type 
    FROM item_prices 
    WHERE is_active = 1 
    AND price_type NOT IN ('__SRP__', 'Others Bank')
    AND price_type != ''
    AND price_type LIKE '% %'
    ORDER BY price_type";
$bank_terms_result = $conn->query($bank_terms_query);

// Build a JavaScript object mapping banks to their terms
$bank_terms_map = [];
if ($bank_terms_result && $bank_terms_result->num_rows > 0) {
    while ($row = $bank_terms_result->fetch_assoc()) {
        $price_type = $row['price_type'];
        // Split "BankName TermName" into bank and term
        $parts = explode(' ', $price_type, 2);
        if (count($parts) === 2) {
            $bank = $parts[0];
            $term = $parts[1];
            if (!isset($bank_terms_map[$bank])) {
                $bank_terms_map[$bank] = [];
            }
            if (!in_array($term, $bank_terms_map[$bank])) {
                $bank_terms_map[$bank][] = $term;
            }
        }
    }
}

// Fetch terminal IDs for current branch
$escaped_branch = $conn->real_escape_string($user_branch_name);
$terminal_ids_result = $conn->query("SELECT * FROM terminal_ids WHERE status='Active' AND (branches LIKE '%$escaped_branch%' OR branches = '' OR branches IS NULL) ORDER BY terminal_id");

$terminal_ids_for_js = [];
if ($terminal_ids_result && $terminal_ids_result->num_rows > 0) {
    while ($tid_row = $terminal_ids_result->fetch_assoc()) {
        $terminal_ids_for_js[] = [
            'id' => $tid_row['id'],
            'terminal_id' => $tid_row['terminal_id'],
            'terminal_issuer' => $tid_row['terminal_issuer'],
            'branches' => $tid_row['branches']
        ];
    }
}

// Fetch active promos for dropdown (excludes limit-reached for new selections)
// If branch filter applies, only show promos for this branch
$promo_branch_filter = "";
if (!empty($user_branch_name) && $user_branch_name !== 'All Branches') {
    $escaped_branch = $conn->real_escape_string($user_branch_name);
    $promo_branch_filter = " AND (branch LIKE '%$escaped_branch%' OR branch = '' OR branch IS NULL)";
}
$promos_result = $conn->query("
    SELECT * FROM promos 
    WHERE status = 'Active' 
    AND (usage_limit IS NULL OR usage_count < usage_limit)
    $promo_branch_filter
    ORDER BY promo_name
");

// Fetch ALL promos (including limit-reached/deactivated) for JS data
// This ensures existing sales that used a now-exhausted promo still display correctly
$promos_all_result = $conn->query("
    SELECT * FROM promos 
    WHERE 1=1
    $promo_branch_filter
    ORDER BY promo_name
");

// Fetch all promo_items and group by promo_id for JS
$promo_items_all_result = $conn->query("SELECT * FROM promo_items ORDER BY promo_id ASC, id ASC");
$promo_items_grouped = [];
if ($promo_items_all_result && $promo_items_all_result->num_rows > 0) {
    while ($pi = $promo_items_all_result->fetch_assoc()) {
        $pid = $pi['promo_id'];
        if (!isset($promo_items_grouped[$pid])) {
            $promo_items_grouped[$pid] = [];
        }
        $promo_items_grouped[$pid][] = [
            'motor_model' => $pi['motor_model'],
            'discount_type' => $pi['discount_type'],
            'discount_value' => $pi['discount_value'],
            'promo_item' => $pi['promo_item']
        ];
    }
}

// Build a PHP array of ALL Promos for JS (includes limit-reached/deactivated)
// so modification page can display promo details for previously applied promos
$promos_for_js = [];
if ($promos_all_result && $promos_all_result->num_rows > 0) {
    while ($promo_row = $promos_all_result->fetch_assoc()) {
        $pid = $promo_row['id'];
        // Use promo_items rows if available, fall back to legacy single fields
        $items = isset($promo_items_grouped[$pid]) && count($promo_items_grouped[$pid]) > 0
            ? $promo_items_grouped[$pid]
            : [
                [
                    'motor_model' => $promo_row['motor_model'],
                    'discount_type' => $promo_row['discount_type'],
                    'discount_value' => $promo_row['discount_value'],
                    'promo_item' => $promo_row['free_item']
                ]
            ];
        $promos_for_js[] = [
            'id' => $pid,
            'promo_name' => $promo_row['promo_name'],
            'status' => $promo_row['status'],
            'usage_count' => isset($promo_row['usage_count']) ? (int) $promo_row['usage_count'] : 0,
            'usage_limit' => !empty($promo_row['usage_limit']) ? (int) $promo_row['usage_limit'] : null,
            'items' => $items
        ];
    }
}
// Fetch existing promo usage slots by promo_id and invoice_no
$used_slots_result = $conn->query("
    SELECT id, invoice_no, promo_id, promo_usage_number 
    FROM sales_entry 
    WHERE promo_id IS NOT NULL 
      AND promo_usage_number IS NOT NULL 
      AND promo_usage_number != ''
      AND status != 'Cancelled'
");
$promo_used_slots = [];
if ($used_slots_result && $used_slots_result->num_rows > 0) {
    while ($usr = $used_slots_result->fetch_assoc()) {
        $pid = $usr['promo_id'];
        if (!isset($promo_used_slots[$pid])) {
            $promo_used_slots[$pid] = [];
        }
        $promo_used_slots[$pid][] = [
            'invoice_no' => $usr['invoice_no'],
            'sales_entry_id' => $usr['id'],
            'slot_str' => $usr['promo_usage_number']
        ];
    }
}

// Reset active-only result pointer for HTML dropdown rendering
if ($promos_result && $promos_result->num_rows > 0) {
    $promos_result->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Sales Modification</title>
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

        .content-header {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: 15px;
            margin-bottom: 10px;
        }

        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .btn-back-to-table {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background-color: #757575;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .btn-back-to-table:hover {
            background-color: #616161;
        }

        .btn-back-to-table svg {
            width: 16px;
            height: 16px;
            fill: white;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 10px;
        }

        .search-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 20px;
        }

        .search-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 15px;
            align-items: flex-end;
        }

        /* Search bar from voidsales.php */
        .search-bar-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .search-controls {
            display: flex;
            align-items: center;
        }

        .date-filters {
            display: flex;
            align-items: center;
            margin-left: auto;
        }

        .search-bar-wrapper input {
            padding: 9px 14px;
            border: 1px solid #ccc;
            border-radius: 4px 0 0 4px;
            font-size: 14px;
            width: 280px;
            outline: none;
            font-family: Arial, sans-serif;
            background: white;
        }

        .search-bar-wrapper input[type="date"] {
            padding: 9px 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            width: 150px;
            outline: none;
            font-family: Arial, sans-serif;
            background: white;
            margin-left: 5px;
        }

        .search-bar-wrapper input[type="date"]:focus {
            border-color: #2196F3;
        }

        .search-bar-wrapper select {
            padding: 9px 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            outline: none;
            font-family: Arial, sans-serif;
            background: white;
            margin-left: 10px;
            min-width: 200px;
        }

        .search-bar-wrapper select:focus {
            border-color: #2196F3;
        }

        .search-bar-wrapper input:focus {
            border-color: #2196F3;
        }

        .search-bar-wrapper button {
            padding: 9px 16px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }

        .search-bar-wrapper button:hover {
            background-color: var(--color-navy-dark);
        }

        /* Table container */
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 20px;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ccc;
        }

        .report-table thead {
            background: var(--color-gold-pale);
        }

        .report-table th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border: 1px solid #ccc;
        }

        .report-table td {
            padding: 10px 12px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
            vertical-align: middle;
        }

        .report-table tbody tr:hover {
            background-color: #fdf8f3;
        }

        .report-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        .report-table tbody tr:nth-child(even):hover {
            background-color: #fdf8f3;
        }

        .no-data td {
            color: #999;
            font-style: italic;
            padding: 20px;
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background-color: #c8e6c9;
            color: #2e7d32;
        }

        .status-void {
            background-color: #ffcdd2;
            color: #c62828;
        }

        /* Action buttons */
        .action-btns {
            display: flex;
            gap: 6px;
            justify-content: center;
            align-items: center;
        }

        .btn-view {
            padding: 5px 14px;
            background-color: #1e88e5;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-view:hover {
            background-color: #1565c0;
        }

        .btn-modify {
            padding: 5px 14px;
            background-color: #f57c00;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-modify:hover {
            background-color: #e65100;
        }

        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 6px;
            margin-top: 14px;
        }

        .pagination-wrapper span {
            font-size: 13px;
            color: #555;
            margin-right: 6px;
        }

        .page-btn {
            padding: 5px 11px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 13px;
            color: #333;
            transition: background 0.2s;
        }

        .page-btn:hover {
            background: #e0e0e0;
            border-color: #333333;
        }

        .page-btn.active {
            background: #333333;
            color: white;
            border-color: #333333;
        }

        .form-split-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .form-left-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-right-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 0;
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

        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
        }

        .btn-search {
            padding: 10px 40px;
            background-color: #424242;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            height: 38px;
        }

        .btn-search:hover {
            background-color: #333;
        }

        .item-input-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto auto;
            gap: 15px;
            align-items: flex-end;
            margin-bottom: 15px;
        }

        .btn-search-item,
        .btn-add-item {
            padding: 10px 50px;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            height: 38px;
            transition: background-color 0.2s;
        }

        .btn-search-item {
            background-color: var(--color-navy);
        }

        .btn-search-item:hover {
            background-color: var(--color-navy-dark);
        }

        .btn-add-item {
            background-color: var(--color-gold);
        }

        .btn-add-item:hover {
            background-color: var(--color-gold-light);
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

        .items-table th,
        .items-table td {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            border: 1px solid #ccc;
        }

        .items-table th {
            font-weight: 600;
            color: #000000;
        }

        .items-table td {
            color: #333;
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

        .btn-update-item {
            background: var(--color-gold);
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
            padding: 0;
            transition: background-color 0.2s;
        }

        .btn-update-item:hover {
            background: var(--color-gold-light);
        }

        .btn-update-item svg {
            width: 14px;
            height: 14px;
            fill: white;
        }

        .items-table input[type="number"],
        .items-table input[type="text"] {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 13px;
            width: 100%;
            text-align: center;
        }

        .promo-details-box {
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            min-height: 100px;
            padding: 10px 12px;
            color: #555;
            font-size: 13px;
            line-height: 1.45;
            white-space: pre-line;
        }

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

        .footer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0px;
            background: white;
            padding: 20px;
        }

        .footer-left-group,
        .footer-right-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .footer-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-input-group label {
            font-weight: 600;
        }

        .footer-input-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 150px;
        }

        .btn-update {
            padding: 12px 50px;
            background-color: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-update:hover {
            background-color: var(--color-gold-light);
        }

        .payment-footer-actions {
            display: flex;
            width: 100%;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            margin-top: 20px;
        }

        .payment-footer-actions .footer-left-group {
            display: flex;
            gap: 20px;
        }

        .payment-footer-actions .footer-input-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .payment-footer-actions .footer-input-group label {
            font-weight: 600;
            font-size: 14px;
            color: #333;
            min-width: 90px;
        }

        .payment-footer-actions .footer-input-group input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            width: 150px;
            text-align: right;
        }

        .btn-clear {
            padding: 10px 40px;
            border: 1px solid #ccc;
            background: white;
            color: black;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }

        .btn-clear:hover {
            background: #ecffebff;
        }

        .btn-payment {
            background-color: #689f38;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 40px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
        }

        .btn-payment:hover {
            background-color: #558b2f;
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

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-start;
        }

        /* Payment Modal Specific */
        .payment-modal-content {
            max-width: 850px;
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
            margin-bottom: 0;
            padding-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
            background: transparent;
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

        .payment-radio-option input[type="checkbox"] {
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

        .payment-radio-option.disabled-payment input[type="checkbox"] {
            cursor: not-allowed;
        }

        .payment-radio-option.disabled-payment span {
            color: #999;
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

        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 9999;
            min-width: 400px;
            max-width: 600px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .sales-info-header {
            background: #E1FFDE;
            padding: 15px 20px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #19d222ff;
            position: relative;
        }

        .sales-info-header h3 {
            margin: 0;
            color: #575757ff;
            font-size: 16px;
        }

        #voided_badge {
            position: absolute;
            top: 15px;
            right: 20px;
            background-color: #dc3545;
            color: white;
            padding: 6px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-red {

            0%,
            100% {
                box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
            }

            50% {
                box-shadow: 0 2px 8px rgba(220, 53, 69, 0.6);
            }
        }

        /* Payment Section (Inline) */
        .payment-section-wrapper {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .payment-section-header {
            background: #E1FFDE;
            padding: 15px 20px;
            border-bottom: 2px solid #ccccccff;
            border-radius: 0 0 0 0;
        }

        .payment-section-header h3 {
            margin: 0;
            color: #575757ff;
            font-size: 18px;
            font-weight: 600;
        }

        .payment-section-body {
            padding: 20px;
        }

        /* Payment Modal Additional Styles */
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
            display: flex;
            width: 100%;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
            display: none !important;
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
            background: var(--color-gold);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            min-width: 120px;
            transition: background-color 0.2s;
        }

        .btn-save-modal:hover {
            background-color: var(--color-gold-light);
        }

        .cash-section,
        .online-banking-section,
        .ewallet-section,
        .starpay-qr-section,
        .qr-ph-section,
        .debit-card-section,
        .credit-card-section,
        .home-credit-section {
            background: transparent;
            padding: 20px 0;
            margin-top: 0;
            border-top: 2px solid #dee2e6;
            display: none;
        }

        .cash-section h3,
        .online-banking-section h3,
        .ewallet-section h3,
        .starpay-qr-section h3,
        .qr-ph-section h3,
        .debit-card-section h3,
        .credit-card-section h3,
        .home-credit-section h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 16px;
            font-weight: 600;
        }

        .payment-methods-container {
            border: 2px solid #dee2e6;
            border-radius: 0px;
            background: #ffffff;
            padding: 20px;
            margin-top: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .payment-modal-body>div[class$="-section"]:not(.payment-top-section) {
            margin-top: 0;
        }

        .payment-top-section {
            margin-bottom: 20px;
        }

        /* Comprehensive Responsive Styles */

        /* Large Desktop & Laptop (max-width: 1640px) */
        @media (max-width: 1640px) {
            .form-split-layout {
                gap: 20px;
            }

            .item-input-row {
                grid-template-columns: 1fr 1fr 0.8fr auto auto;
                gap: 10px;
            }
        }

        /* Medium Desktop (max-width: 1366px) */
        @media (max-width: 1366px) {
            .form-split-layout {
                grid-template-columns: 1fr;
            }

            .item-input-row {
                grid-template-columns: 1fr 1fr 1fr;
                gap: 10px;
            }

            .btn-search-item,
            .btn-add-item {
                grid-column: span 1;
            }

            .bottom-section {
                grid-template-columns: 1fr;
            }

            .search-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .search-bar-wrapper {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .search-controls,
            .date-filters {
                width: 100%;
                margin-left: 0;
            }

            .search-bar-wrapper input,
            .search-bar-wrapper select,
            .search-bar-wrapper input[type="date"] {
                width: 100%;
            }

            /* Make search button icon-only at 1366px and below */
            .search-bar-wrapper button {
                padding: 9px 12px;
                border-radius: 4px;
                min-width: 44px;
                font-size: 0;
            }

            .search-bar-wrapper button svg {
                margin: 0 !important;
                font-size: 16px;
            }

            .search-bar-wrapper input {
                border-radius: 4px;
            }
        }

        /* Tablet & Smaller Desktop (max-width: 1024px) */
        @media (max-width: 1024px) {
            .main-content {
                padding: 15px;
            }

            .content-header {
                flex-wrap: wrap;
            }

            .form-container {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .item-input-row {
                grid-template-columns: 1fr 1fr;
            }

            .btn-search-item,
            .btn-add-item {
                width: 100%;
            }

            .items-table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .items-table {
                min-width: 1000px;
            }

            .items-table th,
            .items-table td {
                font-size: 12px;
                padding: 10px 8px;
                white-space: nowrap;
            }

            .report-table {
                min-width: 1000px;
            }

            .footer-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .footer-left-group,
            .footer-right-group {
                width: 100%;
                flex-direction: column;
            }
        }

        /* Small Tablet (max-width: 960px) */
        @media (max-width: 960px) {
            .item-input-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .items-table {
                zoom: 0.85;
            }

            .report-table {
                zoom: 0.85;
            }

            .payment-modal-content {
                width: 95%;
            }

            .payment-top-section {
                flex-wrap: wrap;
            }

            /* Make quantity and price inputs more visible */
            .qty-input-group,
            .price-input-group {
                min-width: 120px !important;
            }
        }

        /* Mobile Devices (max-width: 768px) */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1500;
            }

            .sidebar.hidden {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                padding: 12px;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .header::after {
                left: 0;
            }

            .content-header {
                flex-direction: column;
                align-items: stretch;
            }

            .content-header h2 {
                font-size: 18px;
            }

            .btn-back-to-table {
                width: 100%;
                justify-content: center;
                padding: 12px;
            }

            .form-container,
            .search-section,
            .table-container {
                padding: 15px;
            }

            .search-bar-wrapper input {
                font-size: 16px;
                padding: 12px;
            }

            .search-bar-wrapper button {
                padding: 12px;
            }

            .btn-search,
            .btn-update {
                width: 100%;
                padding: 12px 20px;
            }

            /* Make quantity and price inputs full width and more visible on mobile */
            .qty-input-group,
            .price-input-group {
                flex: 1 1 100% !important;
                min-width: 100% !important;
                max-width: 100% !important;
            }

            #qty,
            #price {
                width: 100% !important;
                font-size: 16px !important;
                padding: 12px !important;
                text-align: right !important;
            }

            .items-table {
                min-width: 900px;
                zoom: 0.75;
            }

            .items-table th,
            .items-table td {
                font-size: 11px;
                padding: 8px 6px;
            }

            .report-table {
                min-width: 900px;
                zoom: 0.75;
            }

            .report-table th,
            .report-table td {
                font-size: 11px;
                padding: 8px 6px;
            }

            .action-btns {
                flex-direction: column;
                gap: 4px;
            }

            .btn-view,
            .btn-modify {
                width: 100%;
            }

            .total-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .total-row input {
                width: 100%;
            }

            .footer-input-group {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }

            .footer-input-group input {
                width: 100%;
            }

            .btn-payment,
            .btn-clear,
            .btn-update {
                width: 100%;
            }

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

            .payment-modal-content {
                width: 98%;
            }

            .alert {
                min-width: unset;
                max-width: calc(100% - 40px);
                right: 10px;
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

            .form-container,
            .search-section,
            .table-container {
                padding: 12px;
            }

            /* Ensure search button stays icon-only */
            .search-bar-wrapper button {
                padding: 10px;
                font-size: 0;
                min-width: 40px;
            }

            .search-bar-wrapper button svg {
                margin: 0 !important;
            }

            .search-bar-wrapper input,
            .search-bar-wrapper select {
                font-size: 15px;
            }

            .items-table,
            .report-table {
                min-width: 800px;
                zoom: 0.7;
            }

            .items-table th,
            .items-table td,
            .report-table th,
            .report-table td {
                font-size: 10px;
                padding: 6px 4px;
            }

            .btn-search,
            .btn-update,
            .btn-payment,
            .btn-clear {
                padding: 10px 16px;
                font-size: 14px;
            }

            .modal-header {
                font-size: 16px;
            }
        }

        /* Items Table Optimization (max-width: 630px) */
        @media (max-width: 630px) {

            /* Add horizontal scroll wrapper for items table */
            .item-selection-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .items-table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            /* Custom scrollbar for items table */
            .items-table-wrapper::-webkit-scrollbar,
            .item-selection-container::-webkit-scrollbar {
                height: 8px;
            }

            .items-table-wrapper::-webkit-scrollbar-track,
            .item-selection-container::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }

            .items-table-wrapper::-webkit-scrollbar-thumb,
            .item-selection-container::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 4px;
            }

            .items-table-wrapper::-webkit-scrollbar-thumb:hover,
            .item-selection-container::-webkit-scrollbar-thumb:hover {
                background: #555;
            }

            .items-table {
                min-width: 900px;
                zoom: 0.8;
            }

            .items-table th,
            .items-table td {
                font-size: 12px;
                padding: 10px 8px;
                white-space: nowrap;
            }

            /* Compact form inputs in table */
            .items-table input[type="number"],
            .items-table input[type="text"] {
                font-size: 13px;
                padding: 6px 8px;
            }

            /* Make update/delete buttons more compact */
            .btn-update-item,
            .btn-delete-item {
                width: 22px;
                height: 22px;
            }

            .btn-update-item svg {
                width: 12px;
                height: 12px;
            }
        }

        /* Extra Small Mobile - Table Optimization (max-width: 560px) */
        @media (max-width: 560px) {
            .table-container {
                padding: 10px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                position: relative;
            }

            /* Add visible scrollbar for tables */
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

            .items-table,
            .report-table {
                min-width: 700px;
                zoom: 0.65;
                display: table;
            }

            .items-table th,
            .items-table td,
            .report-table th,
            .report-table td {
                font-size: 11px;
                padding: 8px 6px;
                white-space: nowrap;
            }

            /* Ensure table wrapper shows scroll hint */
            .table-container::after {
                content: '← Scroll to see more →';
                display: block;
                text-align: center;
                padding: 8px;
                background: #f8f9fa;
                color: #666;
                font-size: 12px;
                border-top: 1px solid #dee2e6;
                position: sticky;
                bottom: 0;
                left: 0;
                right: 0;
            }

            /* Hide scroll hint when not needed */
            .table-container.no-scroll::after {
                display: none;
            }

            /* Make action buttons more compact */
            .action-btns {
                gap: 4px;
            }

            .btn-view,
            .btn-modify {
                padding: 4px 8px;
                font-size: 10px;
            }

            /* Compact status badges */
            .status-badge {
                padding: 3px 8px;
                font-size: 10px;
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
        <?php include '_header_user.php'; ?>
    </div>

    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <button class="btn-back-to-table" id="btnBackToTable" onclick="backToTable()">
                <svg viewBox="0 0 24 24">
                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z" />
                </svg>
                Back to List
            </button>
            <h2>Sales Modification</h2>
            <?php if (!empty($user_branch_name) || (isset($_SESSION['system_level']) && ($_SESSION['system_level'] === 'Super-Admin' || $_SESSION['system_level'] === 'Sub-admin'))): ?>
                <div
                    style="margin-left: auto; font-weight: bold; color: #1b5e20; font-size: 18px; text-transform: uppercase;">
                    <?php
                    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
                    if ($system_level === 'Super-Admin') {
                        // Only Super-Admin can see "All Branches"
                        echo "All Branches";
                    } else {
                        // Sub-admin and regular users see their own branch
                        echo htmlspecialchars($user_branch_name);
                        if (!empty($branch_code) && $branch_code !== '000') {
                            echo ' - ' . htmlspecialchars($branch_code);
                        }
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="alertBox" class="alert"></div>

        <!-- Search Bar and Filters -->
        <div class="search-bar-wrapper">
            <div class="search-controls">
                <input type="text" id="searchInput" placeholder="Enter Invoice Number." oninput="filterTable()">
                <button onclick="filterTable()">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="white" style="vertical-align:middle;">
                        <path
                            d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                    </svg>
                    Search
                </button>
            </div>
            <div class="date-filters">
                <label for="dateFrom" style="font-size: 14px; color: #333;">Date:</label>
                <input type="date" id="dateFrom"
                    value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">

                <?php
                $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
                $is_super_admin = ($system_level === 'Super-Admin');
                $is_sub_admin = ($system_level === 'Sub-admin');
                $selected_branch = isset($_GET['branch']) ? $_GET['branch'] : '';

                // Parse user branches - can be comma-separated for Sub-admins
                $user_branches_array = array();
                if (!empty($user_branch_name)) {
                    $user_branches_array = array_map('trim', explode(',', $user_branch_name));
                }

                // Show branch dropdown if:
                // 1. Super-Admin (can see all branches)
                // 2. Sub-admin with multiple branches
                $show_branch_filter = $is_super_admin || ($is_sub_admin && count($user_branches_array) > 1);

                if ($show_branch_filter) {
                    echo '<select id="branchFilter" style="margin-left: 10px;">';

                    // Default placeholder option
                    $is_default = ($selected_branch === '');
                    echo '    <option value="" disabled' . ($is_default ? ' selected' : '') . '>Select Branches</option>';

                    if ($is_super_admin) {
                        // Super-Admin can see "All Branches" option and all branches
                        $is_all_selected = ($selected_branch === 'all');
                        echo '    <option value="all"' . ($is_all_selected ? ' selected' : '') . '>All Branches</option>';

                        $branch_filter_query = "SELECT DISTINCT branch_code, branch_name FROM branches ORDER BY branch_name";
                        $branch_filter_result = $conn->query($branch_filter_query);
                        if ($branch_filter_result && $branch_filter_result->num_rows > 0) {
                            while ($branch_row = $branch_filter_result->fetch_assoc()) {
                                $branch_display = htmlspecialchars($branch_row['branch_name'] . ' - ' . $branch_row['branch_code']);
                                $branch_value = htmlspecialchars($branch_row['branch_code']);
                                $selected_attr = ($branch_value === $selected_branch) ? ' selected' : '';
                                echo "<option value=\"{$branch_value}\"{$selected_attr}>{$branch_display}</option>";
                            }
                        }
                    } else {
                        // Sub-admin with multiple branches - only show their accessible branches
                        foreach ($user_branches_array as $branch_name) {
                            $escaped_branch_name = $conn->real_escape_string($branch_name);
                            $branch_query = $conn->query("SELECT branch_code, branch_name FROM branches WHERE branch_name = '$escaped_branch_name' LIMIT 1");
                            if ($branch_query && $branch_query->num_rows > 0) {
                                $branch_row = $branch_query->fetch_assoc();
                                $branch_display = htmlspecialchars($branch_row['branch_name'] . ' - ' . $branch_row['branch_code']);
                                $branch_value = htmlspecialchars($branch_row['branch_code']);
                                $selected_attr = ($branch_value === $selected_branch) ? ' selected' : '';
                                echo "<option value=\"{$branch_value}\"{$selected_attr}>{$branch_display}</option>";
                            }
                        }
                    }
                    echo '</select>';
                }

                $selected_modified = isset($_GET['modified']) ? $_GET['modified'] : '';
                ?>

                <!-- Modified Filter -->
                <select id="modifiedFilter"
                    style="padding: 9px 14px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; outline: none; font-family: Arial, sans-serif; background: white; min-width: 120px; margin-left: 10px;">
                    <option value="" <?php echo ($selected_modified === '') ? ' selected' : ''; ?>>All</option>
                    <option value="Yes" <?php echo ($selected_modified === 'Yes') ? ' selected' : ''; ?>>Modified (Yes)
                    </option>
                    <option value="No" <?php echo ($selected_modified === 'No') ? ' selected' : ''; ?>>Not Modified (No)
                    </option>
                </select>

                <!-- Filter Button -->
                <button onclick="applyFilter()"
                    style="padding: 9px 16px; background-color: var(--color-gold); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin-left: 10px; font-weight: 500; transition: background-color 0.2s;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="white"
                        style="vertical-align:middle; margin-right: 6px;">
                        <path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z" />
                    </svg>
                    Filter
                </button>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="table-container">
            <table class="report-table" id="reportTable">
                <thead>
                    <tr>
                        <th style="width:12%;">Date Sold</th>
                        <th style="width:15%;">Invoice Number</th>
                        <th style="width:18%;">Customer Name</th>
                        <th style="width:12%;">Branch</th>
                        <th style="width:8%;">Status</th>
                        <th style="width:15%;">Reason to Modify</th>
                        <th style="width:10%;">Modified</th>
                        <th style="width:10%;">Action</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    <?php
                    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
                    $is_super_admin = (strcasecmp($system_level, 'Super-Admin') === 0);
                    $is_sub_admin = (strcasecmp($system_level, 'Sub-admin') === 0);

                    // Check if filter has been applied
                    $filter_applied = isset($_GET['filter_applied']) && $_GET['filter_applied'] === '1';
                    $sales_result = null;

                    if ($filter_applied) {
                        // Get filter parameters from URL
                        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
                        $branch_filter = isset($_GET['branch']) ? $_GET['branch'] : '';
                        $modified_filter = isset($_GET['modified']) ? $_GET['modified'] : '';

                        // Build WHERE clause
                        $where_conditions = array();

                        // Base condition
                        $where_conditions[] = "1=1";

                        // Apply date filter if provided - filter by single date
                        if (!empty($date_from)) {
                            $date_from_safe = $conn->real_escape_string($date_from);
                            $where_conditions[] = "DATE(se.created_at) = '$date_from_safe'";
                        }

                        // For Sub-admin: restrict to sales within 1 day (yesterday and today only)
                        if ($is_sub_admin) {
                            $where_conditions[] = "DATE(se.created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                        }

                        // Apply branch filter based on user role
                        if ($is_super_admin) {
                            // Super-Admin: Can select specific branch or see all branches
                            if (!empty($branch_filter) && $branch_filter !== 'all') {
                                $branch_safe = $conn->real_escape_string($branch_filter);
                                $where_conditions[] = "se.branch_code = '$branch_safe'";
                            }
                            // If branch_filter is 'all' or empty, no branch condition (show all branches)
                        } elseif ($is_sub_admin) {
                            // Sub-admin: Parse accessible branches from session
                            $user_branches_array = array();
                            if (!empty($user_branch_name)) {
                                $user_branches_array = array_map('trim', explode(',', $user_branch_name));
                            }

                            if (count($user_branches_array) > 1) {
                                // Sub-admin with multiple branches
                                if (!empty($branch_filter)) {
                                    // Specific branch selected - verify they have access to it
                                    $branch_safe = $conn->real_escape_string($branch_filter);
                                    // Get branch codes for all accessible branches
                                    $accessible_codes = array();
                                    foreach ($user_branches_array as $branch_name) {
                                        $escaped_branch_name = $conn->real_escape_string($branch_name);
                                        $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$escaped_branch_name' LIMIT 1");
                                        if ($branch_query && $branch_query->num_rows > 0) {
                                            $branch_data = $branch_query->fetch_assoc();
                                            $accessible_codes[] = $branch_data['branch_code'];
                                        }
                                    }
                                    // Only apply filter if selected branch is in accessible list
                                    if (in_array($branch_filter, $accessible_codes)) {
                                        $where_conditions[] = "se.branch_code = '$branch_safe'";
                                    } else {
                                        // Selected branch not accessible - show no results
                                        $where_conditions[] = "1=0";
                                    }
                                } else {
                                    // No specific branch selected - show all their accessible branches
                                    $accessible_codes = array();
                                    foreach ($user_branches_array as $branch_name) {
                                        $escaped_branch_name = $conn->real_escape_string($branch_name);
                                        $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$escaped_branch_name' LIMIT 1");
                                        if ($branch_query && $branch_query->num_rows > 0) {
                                            $branch_data = $branch_query->fetch_assoc();
                                            $accessible_codes[] = "'" . $conn->real_escape_string($branch_data['branch_code']) . "'";
                                        }
                                    }
                                    if (!empty($accessible_codes)) {
                                        $where_conditions[] = "se.branch_code IN (" . implode(',', $accessible_codes) . ")";
                                    }
                                }
                            } else {
                                // Sub-admin with single branch - restrict to that branch only
                                $where_conditions[] = "se.branch_code = '$branch_code'";
                            }
                        } else {
                            // Regular users: Can only see their own branch
                            $where_conditions[] = "se.branch_code = '$branch_code'";
                        }

                        // Apply modified filter if provided
                        if (!empty($modified_filter)) {
                            if ($modified_filter === 'Yes') {
                                $where_conditions[] = "(se.reason_to_modify IS NOT NULL AND se.reason_to_modify != '')";
                            } elseif ($modified_filter === 'No') {
                                $where_conditions[] = "(se.reason_to_modify IS NULL OR se.reason_to_modify = '')";
                            }
                        }

                        $where_clause = "WHERE " . implode(" AND ", $where_conditions);

                        $query = "SELECT se.id, se.created_at, se.invoice_no, se.first_name, se.last_name, se.branch_code, b.branch_name, se.status, se.reason_to_modify 
                                    FROM sales_entry se
                                    LEFT JOIN branches b ON se.branch_code = b.branch_code
                                    $where_clause
                                    ORDER BY se.created_at DESC, se.id DESC LIMIT 500";

                        $sales_result = $conn->query($query);
                    }

                    $total_entries = 0;

                    if ($sales_result === null) {
                        // No filter applied - show message
                        echo "<tr><td colspan='8' style='text-align: center; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>
                                <div style='display: flex; flex-direction: column; align-items: center; gap: 12px;'>
                                    <svg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 24 24' fill='none' stroke='#999' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'>
                                        <circle cx='12' cy='12' r='10'></circle>
                                        <line x1='12' y1='16' x2='12' y2='12'></line>
                                        <line x1='12' y1='8' x2='12.01' y2='8'></line>
                                    </svg>
                                    <div style='color: #333; font-size: 15px; font-weight: 600;'>SELECT A FILTER TO DISPLAY THE DATA</div>
                                    <div style='color: #666; font-size: 13px;'>Please click the FILTER button to view sales entries.</div>
                                </div>
                              </td></tr>";
                    } elseif ($sales_result && $sales_result->num_rows > 0) {
                        $total_entries = $sales_result->num_rows;
                        $idx = 0;
                        while ($row = $sales_result->fetch_assoc()) {
                            $r_date = !empty($row['created_at']) && $row['created_at'] != '1970-01-01 00:00:00' ? date('m/d/Y', strtotime($row['created_at'])) : '';
                            $r_date_ymd = !empty($row['created_at']) && $row['created_at'] != '1970-01-01 00:00:00' ? date('Y-m-d', strtotime($row['created_at'])) : '';
                            $r_inv = htmlspecialchars($row['invoice_no']);
                            $r_cust = htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name']));

                            $b_name = $row['branch_name'] ? $row['branch_name'] : 'Unknown Branch';
                            $b_code = $row['branch_code'] ? $row['branch_code'] : 'UNK';
                            $r_branch = htmlspecialchars($b_name . ' - ' . $b_code);

                            $r_id = $row['id'];
                            $r_status = strtolower($row['status']) === 'voided' ? 'VOID' : 'Active';
                            $status_class = strtolower($row['status']) === 'voided' ? 'status-void' : 'status-active';

                            $r_reason = !empty($row['reason_to_modify']) ? htmlspecialchars($row['reason_to_modify']) : '—';

                            // Check if modified - if reason_to_modify is not empty, it means the record has been modified
                            $is_modified = !empty($row['reason_to_modify']) && trim($row['reason_to_modify']) !== '' ? 'Yes' : 'No';
                            $modified_class = $is_modified === 'Yes' ? 'status-badge' : '';
                            $modified_style = $is_modified === 'Yes' ? 'background-color: #fff3cd; color: #856404;' : '';

                            // Skip voided sales - don't display them
                            if (strtolower($row['status']) === 'voided') {
                                continue;
                            }

                            echo "<tr data-id=\"{$r_id}\" data-index=\"{$idx}\" data-invoice=\"{$r_inv}\" data-branch=\"{$b_code}\" data-date=\"{$r_date_ymd}\">";
                            echo "  <td>{$r_date}</td>";
                            echo "  <td>{$r_inv}</td>";
                            echo "  <td>{$r_cust}</td>";
                            echo "  <td>{$r_branch}</td>";
                            echo "  <td><span class=\"status-badge {$status_class}\">{$r_status}</span></td>";
                            echo "  <td style=\"text-align:left; padding-left:12px;\">{$r_reason}</td>";
                            echo "  <td><span class=\"{$modified_class}\" style=\"{$modified_style}\">{$is_modified}</span></td>";
                            echo "  <td>";
                            echo "      <div class=\"action-btns\">";
                            echo "          <button class=\"btn-modify\" onclick=\"modifySale('{$r_inv}')\">Modify</button>";
                            echo "      </div>";
                            echo "  </td>";
                            echo "</tr>";
                            $idx++;
                        }
                    } else {
                        // Filter applied but no results found
                        echo "<tr class=\"no-data\" id=\"noDataRow\"><td colspan=\"8\" style='text-align: center !important; padding: 40px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc; color: #666;'>No sales records found for the selected filters.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination-wrapper">
                <span id="pageInfo">Showing <?php echo $total_entries; ?> entries</span>
                <button class="page-btn active" id="prevBtn" onclick="changePage(-1)" disabled>&laquo; Prev</button>
                <button class="page-btn active" id="nextBtn" onclick="changePage(1)" disabled>Next &raquo;</button>
            </div>
        </div>

        <!-- Modification Form (Hidden until modify action) -->
        <div id="modificationSection" style="display: none;">
            <div class="sales-info-header">
                <h3 id="salesInfoTitle">
                    Modifying Invoice: <span id="current_invoice"></span>
                    | Branch: <span id="current_branch"></span>
                </h3>
                <span id="voided_badge" style="display: none;">VOIDED</span>
            </div>

            <div class="form-container">
                <form id="modificationForm" class="form-split-layout">
                    <input type="hidden" id="sales_entry_id" name="sales_entry_id">
                    <input type="hidden" id="payment_data" name="payment_data">

                    <div class="form-left-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="invoice_no">Invoice No</label>
                                <input type="text" id="invoice_no" name="invoice_no"
                                    oninput="syncInvoiceNumberDisplay()">
                            </div>
                            <div class="form-group">
                                <label for="date">Date</label>
                                <input type="text" id="date" name="date" readonly>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name"
                                    oninput="this.value = this.value.toUpperCase()">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name"
                                    oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="address">Address</label>
                                <input type="text" id="address" name="address">
                            </div>
                            <div class="form-group">
                                <label for="assisted_by">Assisted By</label>
                                <select id="assisted_by" name="assisted_by" required>
                                    <option value="" disabled>Select</option>
                                    <?php
                                    if ($users_result && $users_result->num_rows > 0) {
                                        $users_result->data_seek(0);
                                        while ($user = $users_result->fetch_assoc()) {
                                            $name = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                                            echo '<option value="' . $name . '">' . $name . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact_no">Contact No</label>
                                <input type="text" id="contact_no" name="contact_no"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email">
                            </div>
                        </div>
                    </div>

                    <div class="form-right-section">
                        <div class="form-group" style="flex: 1;">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" style="height: 10%; min-height: 38px;"
                                oninput="this.value = this.value.toUpperCase()"></textarea>

                            <!-- Promo Checkbox -->
                            <div
                                style="display: flex; align-items: center; gap: 8px; margin-top: 20px; margin-bottom: 0px;">
                                <label for="promo" style="margin: 0; cursor: pointer; font-weight: 600;">Promo</label>
                                <input type="checkbox" id="promo" name="promo"
                                    style="width: 18px; height: 18px; cursor: pointer;" onchange="togglePromoFields()">
                            </div>
                        </div>


                        <!-- Promo Fields (Hidden by default) -->
                        <div id="promo-fields" style="display: none;">
                            <div class="form-group">
                                <label for="applied_promo">Apply Promo</label>
                                <select id="applied_promo" name="applied_promo"
                                    onchange="window._currentPromoUsageSlot=null; applyPromoLogic(true)">
                                    <option value="">Select Promo</option>
                                    <?php
                                    if ($promos_result && $promos_result->num_rows > 0) {
                                        while ($promo = $promos_result->fetch_assoc()) {
                                            $promo_label = htmlspecialchars($promo['promo_name']);
                                            // Add usage info if limit exists
                                            if (!empty($promo['usage_limit'])) {
                                                $usage_count = isset($promo['usage_count']) ? $promo['usage_count'] : 0;
                                                $promo_label .= " (" . $usage_count . "/" . $promo['usage_limit'] . " used)";
                                            }
                                            echo '<option value="' . htmlspecialchars($promo['id']) . '">' . $promo_label . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group" id="promo_usage_container" style="margin-top: 10px; display: none;">
                                <label for="promo_usage_number">Promo Used / Slot <span
                                        style="font-size: 11px; color: #666; font-weight: normal;">(Auto-adjusted or
                                        editable, e.g. 1 or 2 & 3)</span></label>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="text" id="promo_usage_number" name="promo_usage_number"
                                        style="width: 140px; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; font-weight: bold; color: #1976d2;"
                                        placeholder="1 or 2 & 3" oninput="onPromoUsageNumberChange()">
                                    <span id="promo_usage_limit_display"
                                        style="font-size: 14px; color: #555; font-weight: 600;">/ 10 promo used</span>
                                </div>
                            </div>



                            <div class="form-group" style="margin-top: 15px;">
                                <label for="promo_details">Promo Details</label>
                                <div id="promo_details" class="promo-details-box">No promo selected.</div>
                            </div>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="reason_to_modify">Reason to Modify <span style="color: red;">*</span></label>
                            <textarea id="reason_to_modify" name="reason_to_modify"
                                style="height: 100%; min-height: 100px;" oninput="this.value = this.value.toUpperCase()"
                                required></textarea>
                        </div>
                    </div>
                </form>
            </div>


            <div class="bottom-section">
                <div class="right-section-wrapper"
                    style="display: flex; flex-direction: column; gap: 20px; align-items: flex-end;">
                    <div class="totals-section" style="width: 100%; display: none;">
                        <div class="total-row">
                            <label>Total QTY:</label>
                            <input type="number" id="totalQty" readonly>
                        </div>
                        <div class="total-row">
                            <label>Discount:</label>
                            <input type="text" id="discountField" style="background-color: #e0e0e0;" readonly>
                        </div>
                        <div class="total-row">
                            <label>Voucher:</label>
                            <input type="text" id="voucherField" style="background-color: #e0e0e0;" readonly>
                        </div>
                        <div class="total-row">
                            <label>Token:</label>
                            <input type="text" id="tokenField" style="background-color: #e0e0e0;" readonly>
                        </div>
                        <div class="total-row">
                            <label>Total:</label>
                            <input type="text" id="totalAmount" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Section (Inline) -->
            <div class="payment-section-wrapper">
                <div class="payment-section-header">
                    <h3>Payment Details</h3>
                </div>
                <div class="payment-section-body">

                    <!-- Order Breakdown Section -->
                    <div class="order-breakdown-section"
                        style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; margin-bottom: 0px;">
                        <h4
                            style="margin: 0 0 15px 0; color: #333; font-size: 16px; font-weight: 600; border-bottom: 2px solid #a8a8a8ff; padding-bottom: 10px;">
                            Order Breakdown</h4>
                        <table class="breakdown-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #e9ecef;">
                                    <th
                                        style="padding: 10px; text-align: left; border: 1px solid #dee2e6; font-size: 13px; font-weight: 600;">
                                        Item Description</th>
                                    <th
                                        style="padding: 10px; text-align: left; border: 1px solid #dee2e6; font-size: 13px; font-weight: 600; width: 180px;">
                                        IMEI</th>
                                    <th
                                        style="padding: 10px; text-align: center; border: 1px solid #dee2e6; font-size: 13px; font-weight: 600; width: 80px;">
                                        Quantity</th>
                                    <th
                                        style="padding: 10px; text-align: right; border: 1px solid #dee2e6; font-size: 13px; font-weight: 600; width: 130px;">
                                        Price</th>
                                    <th
                                        style="padding: 10px; text-align: right; border: 1px solid #dee2e6; font-size: 13px; font-weight: 600; width: 130px;">
                                        Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="breakdownTableBody">
                                <tr>
                                    <td colspan="4"
                                        style="padding: 20px; text-align: center; color: #999; border: 1px solid #dee2e6;">
                                        No items</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr style="background: #e9ecef; font-weight: 600;">
                                    <td colspan="4"
                                        style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-size: 14px;">
                                        Total Amount:</td>
                                    <td id="breakdownTotal"
                                        style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-size: 14px; color: #292929ff">
                                        ₱0.00</td>
                                </tr>
                                <tr style="background: #e9ecef; ">
                                    <td colspan="4"
                                        style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-size: 14px; font-weight: 600;">
                                        Discount:</td>
                                    <td id="breakdownDiscount"
                                        style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-size: 14px; color: #dc3545;">
                                        -₱0.00</td>
                                </tr>
                                <tr style="background: #e9ecef; ">
                                    <td colspan="4"
                                        style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-size: 14px; font-weight: 600;">
                                        Voucher:</td>
                                    <td id="breakdownVoucher"
                                        style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-size: 14px; color: #dc3545;">
                                        -₱0.00</td>
                                </tr>
                                <tr style="background: #e9ecef; ">
                                    <td colspan="4"
                                        style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-size: 14px; font-weight: 600;">
                                        Token:</td>
                                    <td id="breakdownToken"
                                        style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-size: 14px; color: #dc3545;">
                                        -₱0.00</td>
                                </tr>
                                <tr style="background: #d4edda; font-weight: 700;">
                                    <td colspan="4"
                                        style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-size: 15px;">
                                        Grand Total:</td>
                                    <td id="breakdownGrandTotal"
                                        style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-size: 15px; color: #28a745;">
                                        ₱0.00</td>
                                </tr>
                            </tfoot>
                        </table>

                        <!-- Item Selection Section (Moved to bottom of Order Breakdown) -->
                        <div class="item-selection-container"
                            style="margin-top: 20px; padding: 20px; background: white; border: 1px solid #dee2e6; border-radius: 6px;">
                            <h4
                                style="margin: 0 0 15px 0; color: #333; font-size: 16px; font-weight: 600; border-bottom: 2px solid #a8a8a8ff; padding-bottom: 10px;">
                                Add Items</h4>
                            <div style="display: flex; gap: 30px; margin-bottom: 20px;">
                                <div style="flex: 1; display: flex; flex-direction: column; gap: 15px;">
                                    <div class="form-group">
                                        <label>Item Code</label>
                                        <input type="text" id="item_code" placeholder=""
                                            oninput="this.value = this.value.toUpperCase()"
                                            onkeypress="handleItemEnter(event)">
                                    </div>
                                    <div class="form-group">
                                        <label>Item Description</label>
                                        <input type="text" id="item_desc" placeholder="" readonly
                                            style="background-color: #ffffffff; cursor: not-allowed;">
                                    </div>
                                </div>

                                <div style="flex: 1; display: flex; flex-direction: column; gap: 15px;">
                                    <div class="form-group">
                                        <label>IMEI</label>
                                        <input type="text" id="imei" placeholder=""
                                            oninput="this.value = this.value.toUpperCase()"
                                            onkeypress="handleItemEnter(event)">
                                    </div>

                                    <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                                        <div class="form-group qty-input-group" style="flex: 1; min-width: 100px;">
                                            <label>Quantity</label>
                                            <input type="number" id="qty" value="0" min="0" style="text-align: center;"
                                                onkeypress="handleItemEnter(event)">
                                        </div>
                                        <div class="form-group price-input-group" style="flex: 1; min-width: 150px;">
                                            <label>Price</label>
                                            <input type="text" id="price" placeholder=""
                                                onkeypress="handleItemEnter(event)">
                                        </div>
                                        <button type="button" class="btn-search-item" style="margin-bottom: 1px;"
                                            onclick="openSearchModal()">Search</button>
                                        <button type="button" class="btn-add-item" style="margin-bottom: 1px;"
                                            onclick="addItem()">Add</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Items Table (Connected to Add Items) -->
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th style="width: 22%;">Item Description</th>
                                        <th style="width: 16%;">IMEI</th>
                                        <th style="width: 8%;">Qty</th>
                                        <th style="width: 10%;">Price</th>
                                        <th style="width: 10%;">Discount</th>
                                        <th style="width: 10%;">Voucher</th>
                                        <th style="width: 10%;">Token</th>
                                        <th style="width: 14%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTableBody">
                                    <tr id="no-items-row">
                                        <td colspan="8" style="text-align:center; padding: 20px;">No items loaded</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Trade-In Details Section (Only shown if sales is from salestrade-in.php) -->
                        <div id="tradeInDetailsSection" class="item-selection-container"
                            style="margin-top: 20px; padding: 20px; background: #f9f9f9; border: 1px solid #dee2e6; border-radius: 6px; display: none;">
                            <h4 style="margin: 0 0 15px 0; color: #1E455D; font-size: 16px; font-weight: 700; border-bottom: 2px solid #1E455D; padding-bottom: 10px;">
                                Trade-In Details</h4>
                            
                            <div style="background: white; padding: 15px; border-radius: 4px; border: 1px solid #ddd;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #333; margin-bottom: 5px; display: block;">Trade-In Value:</label>
                                        <input type="number" id="tradeInValueEdit" step="0.01" min="0" 
                                            style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: white; width: 100%; font-weight: 700; color: #1E455D;"
                                            placeholder="0.00">
                                    </div>
                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #333; margin-bottom: 5px; display: block;">Trade-In Qty:</label>
                                        <input type="number" id="tradeInQtyEdit" min="0" readonly
                                            style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: #f5f5f5; width: 100%; cursor: not-allowed;">
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #333; margin-bottom: 5px; display: block;">Trade-In IMEI:</label>
                                        <input type="text" id="tradeInIMEIEdit" 
                                            style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: white; width: 100%;"
                                            placeholder="Enter IMEI" oninput="this.value = this.value.toUpperCase()">
                                    </div>
                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #333; margin-bottom: 5px; display: block;">Trade-In Brand:</label>
                                        <input type="text" id="tradeInBrandEdit" 
                                            style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: white; width: 100%;"
                                            placeholder="Enter Brand" oninput="this.value = this.value.toUpperCase()">
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #333; margin-bottom: 5px; display: block;">Trade-In Item Code:</label>
                                        <input type="text" id="tradeInItemCodeEdit" 
                                            style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: white; width: 100%;"
                                            placeholder="Enter Item Code" oninput="this.value = this.value.toUpperCase()">
                                    </div>
                                </div>

                                <!-- TITU Voucher Section -->
                                <div style="border-top: 2px solid #dee2e6; padding-top: 15px; margin-top: 15px;">
                                    <h5 style="margin: 0 0 12px 0; color: #1E455D; font-size: 15px; font-weight: 600;">TITU Voucher Details</h5>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                                        <div class="form-group">
                                            <label style="font-weight: 600; color: #333; margin-bottom: 5px; display: block;">TITU Control:</label>
                                            <input type="text" id="tituControlEdit" 
                                                style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: white; width: 100%;"
                                                placeholder="Enter TITU Control">
                                        </div>
                                        <div class="form-group">
                                            <label style="font-weight: 600; color: #333; margin-bottom: 5px; display: block;">TITU Token:</label>
                                            <input type="text" id="tituTokenEdit" 
                                                style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: white; width: 100%;"
                                                placeholder="Enter TITU Token">
                                        </div>
                                        <div class="form-group">
                                            <label style="font-weight: 600; color: #333; margin-bottom: 5px; display: block;">TITU Voucher Total:</label>
                                            <input type="number" id="tituVoucherTotalEdit" step="0.01" min="0"
                                                style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: white; width: 100%; font-weight: 700; color: #1E455D;"
                                                placeholder="0.00">
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                                        <div class="form-group">
                                            <label style="font-weight: 600; color: #333; margin-bottom: 5px; display: block;">Cross Sell:</label>
                                            <input type="number" id="crossSellEdit" step="0.01" min="0"
                                                style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: white; width: 100%;"
                                                placeholder="0.00">
                                        </div>
                                        <div class="form-group">
                                            <label style="font-weight: 600; color: #333; margin-bottom: 5px; display: block;">Trade-In Voucher:</label>
                                            <input type="number" id="tradeInVoucherEdit" step="0.01" min="0"
                                                style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: white; width: 100%;"
                                                placeholder="0.00">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Editable Fields Section -->
                        <div class="breakdown-edit-fields"
                            style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #dee2e6;">
                            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px;">
                                <div class="breakdown-input-group">
                                    <label
                                        style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #555;">Discount <span style="font-weight:400;color:#888;">(from items)</span>:</label>
                                    <input type="number" id="modifyDiscount" placeholder="0.00"
                                        style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 14px; background-color: #f8f9fa;"
                                        step="0.01" min="0" readonly>
                                </div>
                                <div class="breakdown-input-group">
                                    <label
                                        style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #555;">Voucher <span style="font-weight:400;color:#888;">(from items)</span>:</label>
                                    <input type="number" id="modifyVoucher" placeholder="0.00"
                                        style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 14px; background-color: #f8f9fa;"
                                        step="0.01" min="0" readonly>
                                </div>
                                <div class="breakdown-input-group">
                                    <label
                                        style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #555;">Token <span style="font-weight:400;color:#888;">(from items)</span>:</label>
                                    <input type="number" id="modifyToken" placeholder="0.00"
                                        style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 14px; background-color: #f8f9fa;"
                                        step="0.01" min="0" readonly>
                                </div>
                                <div class="breakdown-input-group">
                                    <label
                                        style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #555;">Points:</label>
                                    <input type="number" id="modifyPoints" placeholder="Enter points"
                                        style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 14px;"
                                        step="0.01" min="0">
                                </div>
                                <div class="breakdown-input-group">
                                    <label
                                        style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #555;">Commission:</label>
                                    <input type="number" id="modifyCommission" placeholder="Enter commission"
                                        style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 14px;"
                                        step="0.01" min="0">
                                </div>
                            </div>

                            <!-- Encoder and Customer Name (Readonly) -->
                            <div
                                style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 15px;">
                                <div class="breakdown-input-group">
                                    <label
                                        style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #555;">Encoder:</label>
                                    <input type="text" id="displayEncoder" readonly
                                        style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 14px; background-color: #f8f9fa; color: #333;">
                                </div>
                                <div class="breakdown-input-group">
                                    <label
                                        style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #555;">Customer
                                        Name:</label>
                                    <input type="text" id="displayCustomerName" readonly
                                        style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 14px; background-color: #f8f9fa; color: #333;">
                                </div>
                            </div>
                        </div>

                        <!-- Payment Methods Container (Inside Order Breakdown) -->
                        <div class="payment-methods-container"
                            style="margin-top: 25px; padding-top: 20px; border-top: 2px solid #a8a8a8ff;">

                            <!-- Payment Method Header -->
                            <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #a8a8a8ff; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                                <h4 style="margin: 0; color: #333; font-size: 16px; font-weight: 600;">Payment Method
                                </h4>
                                <button type="button" id="btnAddPayment" onclick="addPaymentBlock()"
                                    style="padding: 8px 16px; background: #1E455D; color: #fff; border: none; border-radius: 5px; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap;"
                                    onmouseover="this.style.backgroundColor='#163447'"
                                    onmouseout="this.style.backgroundColor='#1E455D'">
                                    Add Payment
                                </button>
                            </div>

                            <!-- Dynamic multi-payment sections rendered here for PRE-ORDER / CLAIM scenarios -->
                            <div id="multiplePaymentSectionsContainer" style="display:none;"></div>

                            <!-- Single Payment: Top Section: Dropdowns and Radio Buttons -->
                            <div id="singlePaymentSection">
                                <div class="payment-top-section">
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <label class="payment-radio-option" style="margin: 0;">
                                            <input type="checkbox" name="payment_method" value="payment_partners"
                                                id="chkPaymentPartners">
                                        </label>
                                        <select class="payment-dropdown" id="paymentPartnersDropdown">
                                            <option value="">Payment Partners</option>
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
                                            <input type="checkbox" name="payment_method" value="card_payment"
                                                id="chkCardPayment">
                                        </label>
                                        <select class="payment-dropdown" id="cardPaymentDropdown">
                                            <option value="">Card Payment</option>
                                            <option value="credit_card">Credit Card</option>
                                            <option value="debit_card">Debit Card</option>
                                        </select>
                                    </div>

                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <label class="payment-radio-option" style="margin: 0;">
                                            <input type="checkbox" name="payment_method" value="qr" id="chkQR">
                                        </label>
                                        <select class="payment-dropdown" id="qrDropdown">
                                            <option value="">QR</option>
                                            <option value="qr_ph">QR PH</option>
                                            <option value="starpay_qr">Starpay QR</option>
                                        </select>
                                    </div>

                                    <div class="payment-radio-group"
                                        style="flex-direction: row; gap: 15px; margin-left: 10px;">
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
                                        <div class="hc-form-group unit-selector-row" style="display:none;"></div>
                                        <div class="hc-form-group">
                                            <label>Loan Type:</label>
                                            <select class="hc-input" id="loanTypeDropdown">
                                                <option value=""></option>
                                                <option value="standard_loan">Standard Loan</option>
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
                                                    <input type="text" class="amount-input"
                                                        id="cash_down_payment_amount" placeholder="0.00"
                                                        oninput="formatInput(this)"
                                                        style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                                </div>

                                                <div class="reference-no-row hci-amount-row" id="dpGcashRow"
                                                    style="display: none; flex-direction: column; gap: 10px; margin-bottom: 15px;">
                                                    <div style="display: flex; align-items: center; gap: 10px;">
                                                        <label
                                                            style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">G-Cash
                                                            Reference No:</label>
                                                        <input type="text" class="reference-input"
                                                            id="gcash_down_payment_reference"
                                                            name="gcash_down_payment_reference"
                                                            style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                                    </div>
                                                    <div style="display: flex; align-items: center; gap: 10px;">
                                                        <label
                                                            style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">G-Cash
                                                            Amount (DP):</label>
                                                        <input type="text" class="amount-input"
                                                            id="gcash_down_payment_amount" placeholder="0.00"
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
                                                        <input type="text" class="reference-input"
                                                            id="maya_down_payment_reference"
                                                            name="maya_down_payment_reference"
                                                            style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                                    </div>
                                                    <div style="display: flex; align-items: center; gap: 10px;">
                                                        <label
                                                            style="font-weight: 700; font-size: 14px; min-width: 160px; color: #333; margin: 0;">Maya
                                                            Amount (DP):</label>
                                                        <input type="text" class="amount-input"
                                                            id="maya_down_payment_amount" placeholder="0.00"
                                                            oninput="formatInput(this)"
                                                            style="padding: 8px 12px; border: 1px solid #bfbfbf; border-radius: 4px; font-size: 14px; width: 200px;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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
                                            <select class="hc-input" id="ccTerminalIssuer"
                                                onchange="filterTerminalIds('cc')">
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
                                            <select class="hc-input" id="dcTerminalIssuer"
                                                onchange="filterTerminalIds('dc')">
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
                            </div><!-- end singlePaymentSection -->

                            <!-- Total Payment Section (Inside Container) -->
                            <div
                                style="display: flex; flex-direction: column; gap: 10px; padding-top: 20px; margin-top: 20px; border-top: 2px solid #a8a8a8ff;">
                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                                    <label style="font-size: 16px; font-weight: 600; color: #333;">Total Amount
                                        Due:</label>
                                    <input type="text" id="globalTotalDueInput" readonly
                                        style="padding: 10px 14px; border: 2px solid #ccc; border-radius: 6px; font-size: 16px; width: 180px; background-color: #f5f5f5; font-weight: bold; text-align: right; color: #333;">
                                </div>
                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                                    <label style="font-size: 18px; font-weight: 700; color: #333;">Total
                                        Payment:</label>
                                    <input type="text" id="globalTotalInput" readonly
                                        style="padding: 12px 16px; border: 2px solid #ccc; border-radius: 6px; font-size: 18px; width: 180px; background-color: #f8fdf8; font-weight: bold; text-align: right; color: #333;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End Payment Methods Container -->
                </div>
                <!-- End Order Breakdown Section -->

                <!-- UPDATE and RE-ENTRY Button Section (Outside Order Breakdown) -->
                <div
                    style="display: flex; justify-content: flex-end; align-items: center; gap: 15px; padding: 15px 20px 15px 0; margin-top: 0px;">
                    <button type="button" id="reentryButton" onclick="reEntrySale()"
                        style="display: none; padding: 14px 60px; background-color: #28a745; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.12); transition: all 0.2s ease; text-transform: uppercase; letter-spacing: 1px;"
                        onmouseover="this.style.backgroundColor='#218838'; this.style.boxShadow='0 3px 10px rgba(0,0,0,0.18)';"
                        onmouseout="this.style.backgroundColor='#28a745'; this.style.boxShadow='0 2px 6px rgba(0,0,0,0.12)';">RE-ENTRY</button>
                    <button type="button" class="btn-update" onclick="updateSalesEntry()"
                        style="padding: 14px 70px; background-color: #1976d2; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.12); transition: all 0.2s ease; text-transform: uppercase; letter-spacing: 1px;"
                        onmouseover="this.style.backgroundColor='#1565c0'; this.style.boxShadow='0 3px 10px rgba(0,0,0,0.18)';"
                        onmouseout="this.style.backgroundColor='#1976d2'; this.style.boxShadow='0 2px 6px rgba(0,0,0,0.12)';">UPDATE</button>
                </div>

                <!-- Hidden Points and Commission fields (for compatibility) -->
                <input type="text" id="pointsField" readonly style="display: none;">
                <input type="text" id="commissionField" readonly style="display: none;">

                <!-- Payment Breakdown banner -->
                <div id="paymentBreakdownBanner"
                    style="display:none; margin: 0 0 15px 0; padding: 12px 16px; background-color: #ffffffff; border-left: 4px solid #16a34a; border-right:1px solid #c9c9c9ff; border-top:1px solid #c9c9c9ff; border-bottom:1px solid #c9c9c9ff; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-family: system-ui, -apple-system, sans-serif;">
                </div>

                <!-- Inline payment error banner -->
                <div id="paymentErrorBanner"
                    style="display:none; margin: 0 0 15px 0; padding: 12px 16px; background-color: #fef2f2; border-left: 4px solid #ef4444; border-right:1px solid #c9c9c9ff; border-top:1px solid #c9c9c9ff; border-bottom:1px solid #c9c9c9ff; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-family: system-ui, -apple-system, sans-serif;">
                </div>

            </div>
        </div>
    </div>
    </div>
    </div>

    <!-- Search Item Modal -->
    <div id="searchItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Search Item
            </div>
            <div class="modal-body">
                <table class="search-results-table"
                    style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
                    <thead style="background: #E1FFDE;">
                        <tr>
                            <th
                                style="width: 30%; padding: 12px; text-align: left; border: 1px solid #ccc; font-size: 13px; font-weight: 600;">
                                Item Code</th>
                            <th
                                style="width: 50%; padding: 12px; text-align: left; border: 1px solid #ccc; font-size: 13px; font-weight: 600;">
                                Item Description</th>
                            <th
                                style="width: 20%; padding: 12px; text-align: center; border: 1px solid #ccc; font-size: 13px; font-weight: 600;">
                                Action</th>
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
        let currentSalesData = null;
        let itemsArray = [];

        // Promo data from PHP
        const promosData = <?php echo json_encode($promos_for_js); ?>;
        const promoUsedSlotsData = <?php echo json_encode($promo_used_slots); ?>;
        let activePromoItems = [];
        let activePromoDiscountLabel = '0';
        window._manualPromoSlotEdited = false;
        window._originalLoadedPromoUsageSlot = null;
        window._currentInvoiceNo = null;

        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn = document.querySelector('.menu-btn');

            // On mobile, toggle the sidebar visibility
            if (window.innerWidth <= 768) {
                if (sidebar.classList.contains('hidden')) {
                    sidebar.classList.remove('hidden');
                    sidebar.style.transform = 'translateX(0)';
                } else {
                    sidebar.classList.add('hidden');
                    sidebar.style.transform = 'translateX(-100%)';
                }
            } else {
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('expanded');
            }

            menuBtn.classList.toggle('active');
        }

        // Initialize sidebar state on page load
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.querySelector('.sidebar');
            if (window.innerWidth <= 768) {
                // On mobile, start with sidebar hidden
                sidebar.classList.add('hidden');
                sidebar.style.transform = 'translateX(-100%) ';
            }
        });

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

        // Table filtering and pagination
        let currentPage = 1;
        const pageSize = 20;

        // Apply filter - reload page with filter_applied parameter
        function applyFilter() {
            const dateFrom = document.getElementById('dateFrom').value;
            const branchFilter = document.getElementById('branchFilter') ? document.getElementById('branchFilter').value : '';
            const modifiedFilter = document.getElementById('modifiedFilter').value;

            // Build URL with filter parameters
            let url = 'modification-motogam.php?filter_applied=1';

            if (dateFrom) url += '&date_from=' + encodeURIComponent(dateFrom);
            if (branchFilter) url += '&branch=' + encodeURIComponent(branchFilter);
            if (modifiedFilter) url += '&modified=' + encodeURIComponent(modifiedFilter);

            window.location.href = url;
        }

        function filterTable(resetPage = true) {
            if (resetPage === true || typeof resetPage !== 'boolean') {
                currentPage = 1;
            }

            const query = document.getElementById('searchInput').value.toLowerCase();
            const branchFilterElement = document.getElementById('branchFilter');
            const branchFilter = branchFilterElement ? branchFilterElement.value : '';
            const modifiedFilterElement = document.getElementById('modifiedFilter');
            const modifiedFilter = modifiedFilterElement ? modifiedFilterElement.value : '';
            const dateFromElement = document.getElementById('dateFrom');
            const dateToElement = document.getElementById('dateTo');
            const dateFrom = dateFromElement ? dateFromElement.value : '';
            const dateTo = dateToElement ? dateToElement.value : '';
            const rows = document.querySelectorAll('#reportTableBody tr:not(.no-data)');

            let matchedRows = [];

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const matchSearch = text.includes(query);

                const branchCell = row.cells[3];
                const branchText = branchCell ? branchCell.textContent : '';
                let matchBranch = true;
                if (branchFilter && branchFilter.toLowerCase() !== 'all' && branchText) {
                    const rowBranch = (row.getAttribute('data-branch') || '').trim();
                    if (rowBranch) {
                        matchBranch = (rowBranch.toLowerCase() === branchFilter.toLowerCase());
                    } else {
                        const branchCodeMatch = branchText.trim().match(/-\s*([A-Za-z0-9_-]+)$/i);
                        const rowBranchCode = branchCodeMatch ? branchCodeMatch[1] : '';
                        matchBranch = (rowBranchCode.toLowerCase() === branchFilter.toLowerCase());
                    }
                }

                // Modified filter logic
                const modifiedCell = row.cells[6]; // Modified column is at index 6
                const modifiedText = modifiedCell ? modifiedCell.textContent.trim() : '';
                let matchModified = true;
                if (modifiedFilter) {
                    matchModified = modifiedText === modifiedFilter;
                }

                const dateSoldCell = row.cells[0];
                const dateSoldText = dateSoldCell ? dateSoldCell.textContent.trim() : '';
                let matchDateRange = true;
                if ((dateFrom || dateTo) && (row.getAttribute('data-date') || dateSoldText)) {
                    let rowDate = row.getAttribute('data-date') || '';
                    if (!rowDate && dateSoldText) {
                        const dateParts = dateSoldText.split('/');
                        if (dateParts.length === 3) {
                            rowDate = `${dateParts[2]}-${dateParts[0].padStart(2, '0')}-${dateParts[1].padStart(2, '0')}`;
                        }
                    }
                    if (rowDate) {
                        if (dateFrom && rowDate < dateFrom) matchDateRange = false;
                        if (dateTo && rowDate > dateTo) matchDateRange = false;
                    }
                }

                const match = matchSearch && matchBranch && matchModified && matchDateRange;
                if (match) {
                    matchedRows.push(row);
                    row.classList.add('matched-row');
                } else {
                    row.classList.remove('matched-row');
                    row.style.display = 'none';
                }
            });

            const totalEntries = matchedRows.length;
            const totalPages = Math.ceil(totalEntries / pageSize) || 1;

            if (currentPage > totalPages) currentPage = totalPages;

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;

            matchedRows.forEach((row, i) => {
                if (i >= startIndex && i < endIndex) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            updatePaginationControls(totalEntries, totalPages);
        }

        function updatePaginationControls(totalEntries, totalPages) {
            const pageInfo = document.getElementById('pageInfo');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');

            pageInfo.textContent = `Showing ${totalEntries} entries | Page ${currentPage} of ${totalPages}`;

            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;
        }

        function changePage(direction) {
            currentPage += direction;
            filterTable(false);
        }

        function modifySale(invoiceNo) {
            if (!invoiceNo) {
                showAlert('Invalid invoice number', 'error');
                return;
            }

            // Hide the table and show the modification form
            document.querySelector('.search-bar-wrapper').style.display = 'none';
            document.querySelector('.table-container').style.display = 'none';

            // Show back button
            const backBtn = document.getElementById('btnBackToTable');
            if (backBtn) {
                backBtn.style.display = 'flex';
            }

            fetch('get_sales_entry.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    invoice_no: invoiceNo
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        loadSalesEntry(data.data);
                        showAlert('Sales entry loaded for modification!', 'success');

                        // Update URL to persist invoice number on refresh
                        const url = new URL(window.location);
                        url.searchParams.set('invoice', invoiceNo);
                        window.history.pushState({}, '', url);
                    } else {
                        showAlert(data.message || 'Sales entry not found', 'error');
                        // Show table again on error
                        backToTable();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error loading sales entry', 'error');
                    // Show table again on error
                    backToTable();
                });
        }

        function backToTable() {
            // Hide modification form
            const modSection = document.getElementById('modificationSection');
            if (modSection) {
                modSection.style.display = 'none';
            }

            // Show table and search
            const searchWrapper = document.querySelector('.search-bar-wrapper');
            const tableContainer = document.querySelector('.table-container');
            if (searchWrapper) searchWrapper.style.display = 'flex';
            if (tableContainer) tableContainer.style.display = 'block';

            // Hide back button
            const backBtn = document.getElementById('btnBackToTable');
            if (backBtn) {
                backBtn.style.display = 'none';
            }

            // Clear URL parameters
            const url = new URL(window.location);
            url.searchParams.delete('invoice');
            window.history.pushState({}, '', url);

            // Reset form
            currentSalesData = null;
            itemsArray = [];
            window._currentInvoiceNo = '';
            window._currentOriginalInvoiceNo = '';
            window._itemsEdited = false;
            const paymentDataInput = document.getElementById('payment_data');
            if (paymentDataInput) {
                paymentDataInput.value = '';
            }
        }

        // Initialize table filter on load
        document.addEventListener('DOMContentLoaded', function () {
            const searchInputElement = document.getElementById('searchInput');

            <?php if ($sales_result === null): ?>
                // Disable search box when no filter is applied
                searchInputElement.disabled = true;
                searchInputElement.placeholder = 'Click FILTER button first...';
                searchInputElement.style.backgroundColor = '#f5f5f5';
                searchInputElement.style.cursor = 'not-allowed';
            <?php else: ?>
                // Enable search box when filter is applied
                searchInputElement.disabled = false;
                searchInputElement.placeholder = 'Enter Invoice Number.';
                searchInputElement.style.backgroundColor = 'white';
                searchInputElement.style.cursor = 'text';
                filterTable();
            <?php endif; ?>

            // Header Discount/Voucher/Token are readonly totals (summed from per-item fields)
            // Points/Commission remain editable at header level.

            // Check if there's an invoice parameter in the URL (for page refresh persistence)
            const urlParams = new URLSearchParams(window.location.search);
            const invoiceParam = urlParams.get('invoice');

            if (invoiceParam) {
                // If there's an invoice in the URL, load it automatically
                console.log('Loading invoice from URL:', invoiceParam);
                modifySale(invoiceParam);
            }
        });

        function showAlert(message, type) {
            const alertBox = document.getElementById('alertBox');
            alertBox.textContent = message;
            alertBox.className = 'alert alert-' + type;
            alertBox.style.display = 'block';

            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 5000);
        }

        function formatNumber(num) {
            if (!num || isNaN(num)) return '0.00';
            return parseFloat(num).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        // IMEI Search Function
        function searchByIMEI(imei) {
            const itemCodeInput = document.getElementById('item_code');
            const itemDescInput = document.getElementById('item_desc');
            const priceInput = document.getElementById('price');
            const imeiInput = document.getElementById('imei');
            const qtyInput = document.getElementById('qty');

            // Check for duplicate IMEI in the table before searching
            if (imei && imei.trim() !== '') {
                const existingRows = document.getElementById('itemsTableBody').querySelectorAll('tr:not(#no-items-row)');
                for (let row of existingRows) {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 2) {
                        const existingSerial = cells[1].textContent.trim();
                        if (existingSerial === imei.trim()) {
                            showAlert('This IMEI (' + imei + ') has already been added!', 'error');
                            imeiInput.value = '';
                            return;
                        }
                    }
                }
            }

            fetch(`search_imei_modification.php?imei=${encodeURIComponent(imei)}`)
                .then(response => response.json())
                .then(data => {
                    console.log('IMEI Search Response:', data); // Debug log

                    if (data.status === 'success') {
                        // Populate fields with found data
                        itemCodeInput.value = data.data.item_code || '';
                        itemDescInput.value = data.data.description || '';

                        // Handle price - check if it exists and is valid
                        const price = data.data.price || 0;
                        console.log('Price from API:', price); // Debug log
                        priceInput.value = formatNumber(price);

                        window._lastSelectedItemPrices = data.data.prices || {};
                        window._lastSelectedItemOthersBankEnabled = data.data.others_bank_enabled || false;

                        // Set quantity to 1 automatically for IMEI items
                        qtyInput.value = '1';

                        // Mark IMEI as already looked up so Add button skips the serialized-redirect
                        imeiInput.setAttribute('data-imei-found', '1');

                        // Disable price field for serialized items
                        priceInput.setAttribute('readonly', 'readonly');
                        priceInput.style.backgroundColor = '#ffffff';
                        priceInput.style.color = '#333';
                        priceInput.style.cursor = 'default';

                        // Show warning if price is 0
                        if (price == 0) {
                            showAlert('Warning: Price is 0. This item may not have a price set in Item Registration.', 'info');
                        }
                    } else {
                        showAlert(data.message || 'IMEI not found', 'error');
                        itemCodeInput.value = '';
                        itemDescInput.value = '';
                        priceInput.value = '';

                        // Re-enable price field when clearing
                        priceInput.removeAttribute('readonly');
                        priceInput.style.backgroundColor = '#ffffff';
                        priceInput.style.cursor = 'text';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('An error occurred while searching IMEI.', 'error');
                });
        }

        function handleItemEnter(event) {
            if (event.key === 'Enter' || event.keyCode === 13) {
                event.preventDefault();
                addItem();
            }
        }

        function loadSalesEntry(data) {
            currentSalesData = data;

            // Store invoice numbers for payment breakdown display
            window._currentInvoiceNo = data.invoice_no || '';
            window._currentOriginalInvoiceNo = data.original_invoice_no || '';
            window._paymentHistory = Array.isArray(data.payment_history) ? data.payment_history : [];
            window._isClaimPreorder = (String(data.page_type || '').toLowerCase() === 'claimpreorder')
                || (window._paymentHistory.length > 0)
                || !!(data.original_invoice_no && String(data.original_invoice_no).trim() !== '');

            // Clear items edited flag
            window._itemsEdited = false;

            // Store payment data first, so that updateBreakdownTable() can access it immediately
            if (data.payment_data) {
                const paymentDataValue = typeof data.payment_data === 'string'
                    ? data.payment_data
                    : JSON.stringify(data.payment_data);
                document.getElementById('payment_data').value = paymentDataValue;
                console.log('✓ Payment data stored:', paymentDataValue);
            } else {
                document.getElementById('payment_data').value = '';
                console.log('⚠ No payment_data found in loaded sales entry - cleared input');
            }

            // Show modification section
            document.getElementById('modificationSection').style.display = 'block';

            // Load basic info
            document.getElementById('sales_entry_id').value = data.id;
            document.getElementById('current_invoice').textContent = data.invoice_no;
            document.getElementById('current_branch').textContent = data.branch_name || data.branch_code || 'N/A';
            document.getElementById('invoice_no').value = data.invoice_no;
            document.getElementById('date').value = data.created_at;
            document.getElementById('first_name').value = data.first_name || '';
            document.getElementById('last_name').value = data.last_name || '';
            document.getElementById('address').value = data.address || '';

            // Set assisted_by - add option if it doesn't exist
            const assistedBySelect = document.getElementById('assisted_by');
            const assistedByValue = data.assisted_by || '';
            if (assistedByValue) {
                // Check if option exists
                let optionExists = false;
                for (let i = 0; i < assistedBySelect.options.length; i++) {
                    if (assistedBySelect.options[i].value === assistedByValue) {
                        optionExists = true;
                        break;
                    }
                }
                // If option doesn't exist, add it
                if (!optionExists) {
                    const newOption = document.createElement('option');
                    newOption.value = assistedByValue;
                    newOption.textContent = assistedByValue;
                    assistedBySelect.insertBefore(newOption, assistedBySelect.options[1]); // Insert after "Select" option
                }
                assistedBySelect.value = assistedByValue;
            }

            document.getElementById('contact_no').value = data.contact_no || '';
            document.getElementById('email').value = data.email || '';
            document.getElementById('remarks').value = data.remarks || '';
            document.getElementById('reason_to_modify').value = data.reason_to_modify || '';

            // Load promo data if exists
            if (data.applied_promo) {
                document.getElementById('promo').checked = true;
                togglePromoFields();

                // The applied promo may be limit-reached/deactivated and not in the dropdown.
                // Inject it so it can be selected and its details displayed.
                const promoSelect = document.getElementById('applied_promo');
                const appliedPromoId = String(data.applied_promo);
                let optionExists = false;
                for (let i = 0; i < promoSelect.options.length; i++) {
                    if (String(promoSelect.options[i].value) === appliedPromoId) {
                        optionExists = true;
                        break;
                    }
                }
                if (!optionExists) {
                    // Find promo info from full JS data (includes all promos)
                    const promoInfo = promosData.find(p => String(p.id) === appliedPromoId);
                    const usageSlot = data.promo_usage_number || null;
                    const usageLimit = promoInfo ? promoInfo.usage_limit : null;
                    let slotLabel = '';
                    if (usageSlot && usageLimit) {
                        slotLabel = ` [${usageSlot}/${usageLimit} used - LIMIT REACHED]`;
                    } else if (usageSlot) {
                        slotLabel = ` [slot #${usageSlot} - LIMIT REACHED]`;
                    } else {
                        slotLabel = ' [LIMIT REACHED]';
                    }
                    const label = promoInfo ? promoInfo.promo_name + slotLabel : 'Promo #' + appliedPromoId + slotLabel;
                    const newOpt = document.createElement('option');
                    newOpt.value = appliedPromoId;
                    newOpt.textContent = label;
                    newOpt.disabled = true; // prevent re-selecting a used-up promo
                    promoSelect.appendChild(newOpt);
                } else {
                    // Promo is still active - update its label to show THIS sale's slot
                    const usageSlot = data.promo_usage_number || null;
                    const promoInfo = promosData.find(p => String(p.id) === appliedPromoId);
                    if (usageSlot && promoInfo) {
                        const usageLimit = promoInfo.usage_limit;
                        for (let i = 0; i < promoSelect.options.length; i++) {
                            if (String(promoSelect.options[i].value) === appliedPromoId) {
                                const baseName = promoInfo.promo_name;
                                promoSelect.options[i].textContent = usageLimit
                                    ? `${baseName} (this sale: ${usageSlot}/${usageLimit})`
                                    : `${baseName} (slot #${usageSlot})`;
                                break;
                            }
                        }
                    }
                }

                promoSelect.value = appliedPromoId;
                window._currentInvoiceNo = data.invoice_no || '';
                window._originalLoadedPromoUsageSlot = data.promo_usage_number ? String(data.promo_usage_number) : null;
                window._currentPromoUsageSlot = window._originalLoadedPromoUsageSlot;
                window._manualPromoSlotEdited = false;

                const promoUsageInput = document.getElementById('promo_usage_number');
                if (promoUsageInput) {
                    promoUsageInput.value = data.promo_usage_number || '';
                }
                applyPromoLogic();
            } else {
                document.getElementById('promo').checked = false;
                window._currentPromoUsageSlot = null;
                window._originalLoadedPromoUsageSlot = null;
                window._manualPromoSlotEdited = false;
                const promoUsageInput = document.getElementById('promo_usage_number');
                if (promoUsageInput) promoUsageInput.value = '';
                togglePromoFields();
            }

            // Don't show VOIDED badge in modification page
            const voidedBadge = document.getElementById('voided_badge');
            if (voidedBadge) {
                voidedBadge.style.display = 'none';
            }

            // Don't show RE-ENTRY button in modification page
            const reentryButton = document.getElementById('reentryButton');
            if (reentryButton) {
                reentryButton.style.display = 'none';
            }

            // Load items — each item keeps its own discount/voucher/token
            itemsArray = data.items || [];
            const headerDiscount = parseFloat(data.discount) || 0;
            const headerVoucher = parseFloat(data.voucher_amount) || 0;
            itemsArray.forEach(item => {
                if (item.base_price === undefined || item.base_price === null) {
                    item.base_price = (parseFloat(item.price) > 0) ? parseFloat(item.price) : (parseFloat(item.srp) || parseFloat(item.price) || 0);
                }
                item.has_discount = parseInt(item.has_discount) || 0;
                item.has_voucher = parseInt(item.has_voucher) || 0;
                item.has_token = parseInt(item.has_token) || 0;
                item.discount_amount = parseFloat(item.discount_amount) || 0;
                item.voucher_amount = parseFloat(item.voucher_amount) || 0;
                item.token_amount = parseFloat(item.token_amount) || 0;

                // Prefill from itemreg only when this item has no saved amount yet
                if (item.has_voucher === 1 && item.voucher_amount <= 0 && parseFloat(item.reg_voucher_amount) > 0) {
                    item.voucher_amount = parseFloat(item.reg_voucher_amount) || 0;
                }
                if (item.has_token === 1 && item.token_amount <= 0 && parseFloat(item.reg_token_amount) > 0) {
                    item.token_amount = parseFloat(item.reg_token_amount) || 0;
                }
            });

            // Legacy: only map header discount if NO per-item discounts exist yet
            const itemsDiscountSum = itemsArray.reduce((s, it) => s + (parseFloat(it.discount_amount) || 0), 0);
            if (headerDiscount > 0 && itemsDiscountSum <= 0 && itemsArray.length > 0) {
                // Prefer items flagged for discount; if several, put on first flagged only as starting point
                // (user can then split/edit per row — each row is independently editable)
                const discIdx = itemsArray.findIndex(i => parseInt(i.has_discount) === 1);
                itemsArray[discIdx >= 0 ? discIdx : 0].discount_amount = headerDiscount;
            }

            // Legacy: header voucher with no per-item voucher yet
            const itemsVoucherSum = itemsArray.reduce((s, it) => s + (parseFloat(it.voucher_amount) || 0), 0);
            if (headerVoucher > 0 && itemsVoucherSum <= 0 && itemsArray.length > 0) {
                const vouchIdx = itemsArray.findIndex(i => parseInt(i.has_voucher) === 1);
                itemsArray[vouchIdx >= 0 ? vouchIdx : 0].voucher_amount = headerVoucher;
            }

            reevaluateCartPromo();

            // Load totals (Discount/Voucher/Token come from per-item sync below)
            document.getElementById('totalQty').value = data.total_qty || 0;
            document.getElementById('totalAmount').value = formatCurrency(data.total_amount || 0);
            document.getElementById('pointsField').value = data.points || 0;
            document.getElementById('commissionField').value = formatCurrency(data.commission || 0);

            // Also populate the modifyPoints field in the Payment Details section
            const modifyPointsField = document.getElementById('modifyPoints');
            if (modifyPointsField) {
                modifyPointsField.value = data.points || 0;
            }

            // Also populate the modifyCommission field in the Payment Details section
            const modifyCommissionField = document.getElementById('modifyCommission');
            if (modifyCommissionField) {
                modifyCommissionField.value = data.commission || 0;
            }

            syncItemDeductionTotals();

            // Handle Trade-In Details Section - Show only if from salestrade-in.php
            const tradeInSection = document.getElementById('tradeInDetailsSection');
            const hasTradeInData = data.page_type === 'salestrade-in' || 
                                   (data.tradein_value && parseFloat(data.tradein_value) > 0) ||
                                   (data.tradein_imei && data.tradein_imei.trim() !== '');
            
            if (tradeInSection && hasTradeInData) {
                tradeInSection.style.display = 'block';
                
                // Populate Trade-In fields (editable)
                const tradeInValue = parseFloat(data.tradein_value || 0);
                const tradeInQty = tradeInValue > 0 ? 1 : 0;
                
                document.getElementById('tradeInValueEdit').value = tradeInValue;
                document.getElementById('tradeInQtyEdit').value = tradeInQty;
                document.getElementById('tradeInIMEIEdit').value = data.tradein_imei || '';
                document.getElementById('tradeInBrandEdit').value = data.tradein_brand || '';
                document.getElementById('tradeInItemCodeEdit').value = data.tradein_item_code || '';
                
                // Populate TITU Voucher fields (editable)
                document.getElementById('tituControlEdit').value = data.titu_control || '';
                document.getElementById('tituTokenEdit').value = data.titu_token || '';
                document.getElementById('tituVoucherTotalEdit').value = parseFloat(data.titu_voucher_total || 0);
                document.getElementById('crossSellEdit').value = parseFloat(data.cross_sell || 0);
                document.getElementById('tradeInVoucherEdit').value = parseFloat(data.trade_in_voucher || 0);
                
                // Add event listener to auto-calculate Trade-In Qty when value changes
                const tradeInValueInput = document.getElementById('tradeInValueEdit');
                const tradeInQtyInput = document.getElementById('tradeInQtyEdit');
                if (tradeInValueInput && tradeInQtyInput) {
                    tradeInValueInput.addEventListener('input', function() {
                        const value = parseFloat(this.value) || 0;
                        tradeInQtyInput.value = value > 0 ? 1 : 0;
                    });
                }
            } else if (tradeInSection) {
                tradeInSection.style.display = 'none';
            }

            // Update breakdown table again after discount is set
            updateBreakdownTable();

            // Populate invoice number, encoder and customer name in Order Breakdown
            const invoiceNumberField = document.getElementById('displayInvoiceNumber');
            const encoderField = document.getElementById('displayEncoder');
            const customerNameField = document.getElementById('displayCustomerName');
            if (invoiceNumberField) {
                invoiceNumberField.value = data.invoice_no || 'N/A';
            }
            if (encoderField) {
                encoderField.value = data.assisted_by || 'N/A';
            }
            if (customerNameField) {
                const fullName = [data.first_name, data.last_name].filter(Boolean).join(' ') || 'N/A';
                customerNameField.value = fullName;
            }

            // Initialize payment section with loaded data
            initializePaymentSection();
        }

        function syncItemDeductionTotals() {
            let totalDiscount = 0;
            let totalVoucher = 0;
            let totalToken = 0;

            itemsArray.forEach(item => {
                totalDiscount += parseFloat(item.discount_amount) || 0;
                totalVoucher += parseFloat(item.voucher_amount) || 0;
                totalToken += parseFloat(item.token_amount) || 0;
            });

            const modifyDiscountField = document.getElementById('modifyDiscount');
            const modifyVoucherField = document.getElementById('modifyVoucher');
            const modifyTokenField = document.getElementById('modifyToken');
            const discountField = document.getElementById('discountField');
            const voucherField = document.getElementById('voucherField');
            const tokenField = document.getElementById('tokenField');

            if (modifyDiscountField) modifyDiscountField.value = totalDiscount;
            if (modifyVoucherField) modifyVoucherField.value = totalVoucher;
            if (modifyTokenField) modifyTokenField.value = totalToken;
            if (discountField) discountField.value = formatCurrency(totalDiscount);
            if (voucherField) voucherField.value = formatCurrency(totalVoucher);
            if (tokenField) tokenField.value = formatCurrency(totalToken);
        }

        function updateItemDeduction(index, field, value) {
            if (!itemsArray[index]) return;
            itemsArray[index][field] = parseFloat(value) || 0;
            window._itemsEdited = true;
            syncItemDeductionTotals();
            updateBreakdownTable();
        }

        function renderItemsTable() {
            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = '';

            if (itemsArray.length === 0) {
                tbody.innerHTML = '<tr id="no-items-row"><td colspan="8" style="text-align:center; padding: 20px;">No items loaded</td></tr>';
                syncItemDeductionTotals();
                updateBreakdownTable();
                return;
            }

            itemsArray.forEach((item, index) => {
                const discountVal = parseFloat(item.discount_amount) || 0;
                const voucherVal = parseFloat(item.voucher_amount) || 0;
                const tokenVal = parseFloat(item.token_amount) || 0;
                const openStyle = 'background-color: #ffffff; cursor: text;';

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><input type="text" id="desc_${index}" value="${(item.item_description || '').replace(/"/g, '&quot;')}" style="width: 100%;"></td>
                    <td><input type="text" id="imei_${index}" value="${(item.imei || '').replace(/"/g, '&quot;')}" style="width: 100%;"></td>
                    <td><input type="number" id="qty_${index}" value="${item.quantity}" min="1" style="width: 100%;"></td>
                    <td><input type="number" id="price_${index}" value="${item.price}" step="0.01" min="0" style="width: 100%;"></td>
                    <td><input type="number" id="discount_${index}" value="${discountVal}" step="0.01" min="0"
                        style="width: 100%; ${openStyle}"
                        title="Discount for this item only"
                        oninput="updateItemDeduction(${index}, 'discount_amount', this.value)"></td>
                    <td><input type="number" id="voucher_${index}" value="${voucherVal}" step="0.01" min="0"
                        style="width: 100%; ${openStyle}"
                        title="Voucher for this item only"
                        oninput="updateItemDeduction(${index}, 'voucher_amount', this.value)"></td>
                    <td><input type="number" id="token_${index}" value="${tokenVal}" step="0.01" min="0"
                        style="width: 100%; ${openStyle}"
                        title="Token for this item only"
                        oninput="updateItemDeduction(${index}, 'token_amount', this.value)"></td>
                    <td style="display: flex; gap: 0px; justify-content: center; border: none;">
                        <button class="btn-update-item" onclick="updateRowItem(${index})" title="Update item">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                            </svg>
                        </button>
                        <button class="btn-delete-item" onclick="removeItem(${index})" title="Delete item">X</button>
                    </td>
                `;
                tbody.appendChild(row);
            });

            syncItemDeductionTotals();
            calculateTotals();
            updateBreakdownTable();
            if (typeof filterBanksByTerminalId === 'function') {
                filterBanksByTerminalId('cc');
                filterBanksByTerminalId('dc');
            }
        }

        function updateBreakdownTable() {
            const breakdownTbody = document.getElementById('breakdownTableBody');
            const breakdownTotal = document.getElementById('breakdownTotal');
            const breakdownDiscount = document.getElementById('breakdownDiscount');
            const breakdownGrandTotal = document.getElementById('breakdownGrandTotal');

            if (!breakdownTbody) return;

            breakdownTbody.innerHTML = '';

            // Check if there is multiple payment data to render detailed breakdown stages
            let paymentData = null;
            const paymentDataInput = document.getElementById('payment_data');
            if (paymentDataInput && paymentDataInput.value) {
                try {
                    paymentData = JSON.parse(paymentDataInput.value);
                } catch (e) { }
            }

            const parseAmount = (val) => {
                if (!val) return 0;
                return parseFloat(val.toString().replace(/,/g, '')) || 0;
            };

            if (!window._itemsEdited && paymentData && paymentData.payment_type === 'multiple' && Array.isArray(paymentData.payments)) {
                let totalAmount = 0;
                const totalPayments = paymentData.payments.length;

                paymentData.payments.forEach((p, idx) => {
                    const origInvoice = window._currentOriginalInvoiceNo;
                    const curInvoice = window._currentInvoiceNo;
                    const effectiveInv = (p && typeof p === 'object' && p.block_invoice_no) ? (p.block_invoice_no || '') : '';
                    const meta = getPaymentStageMeta(p, idx, paymentData.payments);
                    const isClaim = meta.isClaim;
                    const inv = effectiveInv || (isClaim ? curInvoice : ((idx === 0 ? origInvoice : '') || origInvoice));
                    const numLabel = paymentOrdinal(idx + 1);
                    const stageName = meta.label || (isClaim ? 'CLAIM PRE-ORDER' : 'PRE-ORDER');
                    const stageLabel = inv
                        ? `${numLabel} Order Breakdown (${stageName} — Invoice #${inv})`
                        : `${numLabel} Order Breakdown (${stageName})`;

                    // Add group header row in the table
                    const headerRow = document.createElement('tr');
                    headerRow.style.background = isClaim ? '#e8f5e9' : '#e3f0ff';
                    headerRow.style.fontWeight = 'bold';
                    headerRow.innerHTML = `
                        <td colspan="5" style="padding: 8px 10px; border: 1px solid #dee2e6; color: ${isClaim ? '#2e7d32' : '#1565c0'}; font-size: 13px;">
                            ${stageLabel}
                        </td>
                    `;
                    breakdownTbody.appendChild(headerRow);

                    // Render items under this payment stage
                    const amt = parseAmount(p.amount || p.Amount || p.Total || p.total || 0);
                    const units = Array.isArray(p.units) ? p.units : [p.payment_type || 'Payment'];
                    const unitPrice = units.length > 0 ? amt / units.length : amt;

                    units.forEach(unit => {
                        totalAmount += unitPrice;

                        // Find matching serial from itemsArray if available
                        let matchedSerial = 'N/A';
                        if (itemsArray && itemsArray.length > 0) {
                            const match = itemsArray.find(item => {
                                const desc = (item.item_description || '').toLowerCase();
                                const uStr = unit.toLowerCase();
                                return desc.includes(uStr) || uStr.includes(desc);
                            });
                            if (match) {
                                matchedSerial = match.imei || 'N/A';
                            } else if (itemsArray[idx]) {
                                matchedSerial = itemsArray[idx].imei || 'N/A';
                            } else if (itemsArray[0]) {
                                matchedSerial = itemsArray[0].imei || 'N/A';
                            }
                        }

                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td style="padding: 10px; border: 1px solid #dee2e6; font-size: 13px; padding-left: 20px;">${unit}</td>
                            <td style="padding: 10px; border: 1px solid #dee2e6; font-size: 13px; color: #666;">${matchedSerial}</td>
                            <td style="padding: 10px; text-align: center; border: 1px solid #dee2e6; font-size: 13px;">1</td>
                            <td style="padding: 10px; text-align: right; border: 1px solid #dee2e6; font-size: 13px;">₱${unitPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                            <td style="padding: 10px; text-align: right; border: 1px solid #dee2e6; font-size: 13px; font-weight: 600;">₱${unitPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        `;
                        breakdownTbody.appendChild(row);
                    });
                });

                // Get discount/voucher/token from per-item totals (header fields are sums)
                const modifyDiscountField = document.getElementById('modifyDiscount');
                let discountValue = modifyDiscountField ? parseFloat(modifyDiscountField.value) || 0 : 0;
                if (discountValue === 0) {
                    discountValue = itemsArray.reduce((s, it) => s + (parseFloat(it.discount_amount) || 0), 0);
                }

                const modifyVoucherField = document.getElementById('modifyVoucher');
                let voucherValue = modifyVoucherField ? parseFloat(modifyVoucherField.value) || 0 : 0;
                if (voucherValue === 0) {
                    voucherValue = itemsArray.reduce((s, it) => s + (parseFloat(it.voucher_amount) || 0), 0);
                }

                const modifyTokenField = document.getElementById('modifyToken');
                let tokenValue = modifyTokenField ? parseFloat(modifyTokenField.value) || 0 : 0;
                if (tokenValue === 0) {
                    tokenValue = itemsArray.reduce((s, it) => s + (parseFloat(it.token_amount) || 0), 0);
                }

                const grandTotal = totalAmount - discountValue - voucherValue - tokenValue;

                if (breakdownTotal) breakdownTotal.textContent = '₱' + totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (breakdownDiscount) breakdownDiscount.textContent = '-₱' + discountValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                const breakdownVoucher = document.getElementById('breakdownVoucher');
                if (breakdownVoucher) breakdownVoucher.textContent = '-₱' + voucherValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                const breakdownToken = document.getElementById('breakdownToken');
                if (breakdownToken) breakdownToken.textContent = '-₱' + tokenValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                if (breakdownGrandTotal) breakdownGrandTotal.textContent = '₱' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                const globalTotalDueInput = document.getElementById('globalTotalDueInput');
                if (globalTotalDueInput) {
                    globalTotalDueInput.value = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                const globalTotalInput = document.getElementById('globalTotalInput');
                if (globalTotalInput) {
                    globalTotalInput.value = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                window._originalTotalAmountDue = grandTotal;
                return;
            }

            if (itemsArray.length === 0) {
                breakdownTbody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #999; border: 1px solid #dee2e6;">No items</td></tr>';
                if (breakdownTotal) breakdownTotal.textContent = '₱0.00';
                if (breakdownDiscount) breakdownDiscount.textContent = '-₱0.00';
                if (breakdownGrandTotal) breakdownGrandTotal.textContent = '₱0.00';
                return;
            }

            let totalAmount = 0;

            itemsArray.forEach((item) => {
                const qty = parseFloat(item.quantity) || 0;
                const price = parseFloat(item.price) || 0;
                const subtotal = qty * price;
                totalAmount += subtotal;

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td style="padding: 10px; border: 1px solid #dee2e6; font-size: 13px;">${item.item_description || 'N/A'}</td>
                    <td style="padding: 10px; border: 1px solid #dee2e6; font-size: 13px; color: #666;">${item.imei || 'N/A'}</td>
                    <td style="padding: 10px; text-align: center; border: 1px solid #dee2e6; font-size: 13px;">${qty}</td>
                    <td style="padding: 10px; text-align: right; border: 1px solid #dee2e6; font-size: 13px;">₱${price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    <td style="padding: 10px; text-align: right; border: 1px solid #dee2e6; font-size: 13px; font-weight: 600;">₱${subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                `;
                breakdownTbody.appendChild(row);
            });

            // Get discount/voucher/token from per-item totals
            let discountValue = itemsArray.reduce((s, it) => s + (parseFloat(it.discount_amount) || 0), 0);
            let voucherValue = itemsArray.reduce((s, it) => s + (parseFloat(it.voucher_amount) || 0), 0);
            let tokenValue = itemsArray.reduce((s, it) => s + (parseFloat(it.token_amount) || 0), 0);

            const modifyDiscountField = document.getElementById('modifyDiscount');
            if (modifyDiscountField && (parseFloat(modifyDiscountField.value) || 0) > 0) {
                discountValue = parseFloat(modifyDiscountField.value) || discountValue;
            }
            const modifyVoucherField = document.getElementById('modifyVoucher');
            if (modifyVoucherField && (parseFloat(modifyVoucherField.value) || 0) > 0) {
                voucherValue = parseFloat(modifyVoucherField.value) || voucherValue;
            }
            const modifyTokenField = document.getElementById('modifyToken');
            if (modifyTokenField && (parseFloat(modifyTokenField.value) || 0) > 0) {
                tokenValue = parseFloat(modifyTokenField.value) || tokenValue;
            }

            const grandTotal = totalAmount - discountValue - voucherValue - tokenValue;

            if (breakdownTotal) breakdownTotal.textContent = '₱' + totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (breakdownDiscount) breakdownDiscount.textContent = '-₱' + discountValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const breakdownVoucher = document.getElementById('breakdownVoucher');
            if (breakdownVoucher) breakdownVoucher.textContent = '-₱' + voucherValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const breakdownToken = document.getElementById('breakdownToken');
            if (breakdownToken) breakdownToken.textContent = '-₱' + tokenValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            if (breakdownGrandTotal) breakdownGrandTotal.textContent = '₱' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Update Total Amount Due to match Grand Total
            const globalTotalDueInput = document.getElementById('globalTotalDueInput');
            if (globalTotalDueInput) {
                globalTotalDueInput.value = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // Update Total Payment to match Grand Total (should equal Amount Due)
            const globalTotalInput = document.getElementById('globalTotalInput');
            if (globalTotalInput) {
                globalTotalInput.value = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // Update _originalTotalAmountDue global variable
            window._originalTotalAmountDue = grandTotal;
        }

        function addItem() {
            const itemCode = document.getElementById('item_code').value.trim();
            const itemDesc = document.getElementById('item_desc').value.trim();
            const imei = document.getElementById('imei').value.trim();
            const qty = parseInt(document.getElementById('qty').value) || 0;
            const price = parseFloat(document.getElementById('price').value.replace(/,/g, '')) || 0;

            if (!itemCode || !itemDesc) {
                showAlert('Please select an item', 'error');
                return;
            }

            if (qty <= 0) {
                showAlert('Quantity must be greater than 0', 'error');
                return;
            }

            if (price <= 0) {
                showAlert('Price must be greater than 0', 'error');
                return;
            }

            // Check stock availability before adding
            const stockCheckUrl = `check_stock_availability.php?item_code=${encodeURIComponent(itemCode)}&imei=${encodeURIComponent(imei)}&qty=${qty}&force_branch=true`;

            fetch(stockCheckUrl)
                .then(response => response.json())
                .then(stockData => {
                    if (stockData.status === 'error' || !stockData.available) {
                        showAlert(stockData.message || 'This item is not available in stock!', 'error');
                        return;
                    }

                    // Stock is available, now check if item is serialized
                    proceedWithAddingItem();
                })
                .catch(error => {
                    console.error('Error checking stock:', error);
                    showAlert('Error checking stock availability', 'error');
                });

            function proceedWithAddingItem() {
                const itemCodeInput = document.getElementById('item_code');
                const imeiInput = document.getElementById('imei');
                const imeiAlreadyFound = imeiInput.getAttribute('data-imei-found') === '1';

                // If IMEI was already looked up, skip the serialized check
                if (imeiAlreadyFound) {
                    addItemToTable();
                    return;
                }

                // Check if item is serialized
                fetch(`check_serial_permission.php?item_code=${encodeURIComponent(itemCode)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success' && data.has_serial) {
                            // Item is serialized - guide user to enter IMEI first
                            showAlert('This item is serialized. Please enter the IMEI first.', 'error');
                            itemCodeInput.value = '';
                            document.getElementById('item_desc').value = '';
                            document.getElementById('price').value = '';
                            imeiInput.value = '';
                            imeiInput.removeAttribute('readonly');
                            imeiInput.style.backgroundColor = '#ffffff';
                            imeiInput.style.cursor = 'text';
                            imeiInput.focus();
                            return;
                        }

                        // Item is not serialized - proceed with adding
                        addItemToTable();
                    })
                    .catch(error => {
                        console.error('Error checking serial status:', error);
                        showAlert('Error checking if item is serialized', 'error');
                    });
            }

            function addItemToTable() {
                // Check for duplicate IMEI
                if (imei && imei.trim() !== '') {
                    const existingRows = document.getElementById('itemsTableBody').querySelectorAll('tr:not(#no-items-row)');
                    for (let row of existingRows) {
                        const cells = row.querySelectorAll('td');
                        if (cells.length >= 2) {
                            const existingSerial = cells[1].textContent.trim();
                            if (existingSerial === imei.trim()) {
                                showAlert('This IMEI (' + imei + ') has already been added!', 'error');
                                return;
                            }
                        }
                    }
                }

                const newItem = {
                    item_code: itemCode,
                    item_description: itemDesc,
                    imei: imei,
                    quantity: qty,
                    price: price,
                    base_price: price,
                    prices: window._lastSelectedItemPrices || {},
                    others_bank_enabled: window._lastSelectedItemOthersBankEnabled || false,
                    has_discount: 0,
                    has_voucher: 0,
                    has_token: 0,
                    discount_amount: 0,
                    voucher_amount: 0,
                    token_amount: 0
                };
                window._lastSelectedItemPrices = null;
                window._lastSelectedItemOthersBankEnabled = null;

                // Load itemreg discount/voucher/token flags for this specific item
                Promise.all([
                    fetch(`check_item_details.php?item_code=${encodeURIComponent(itemCode)}`).then(r => r.json()).catch(() => null),
                    fetch(`check_discount_permission.php?item_code=${encodeURIComponent(itemCode)}`).then(r => r.json()).catch(() => null)
                ]).then(([det, disc]) => {
                    if (det && det.status === 'success') {
                        newItem.has_voucher = parseInt(det.has_voucher) || 0;
                        newItem.has_token = parseInt(det.has_token) || 0;
                        newItem.voucher_amount = (newItem.has_voucher === 1) ? (parseFloat(det.voucher_amount) || 0) : 0;
                        newItem.token_amount = (newItem.has_token === 1) ? (parseFloat(det.token_amount) || 0) : 0;
                    }
                    if (disc && disc.status === 'success') {
                        newItem.has_discount = disc.discount_editable ? 1 : 0;
                    }

                    window._itemsEdited = true;
                    itemsArray.push(newItem);
                    reevaluateCartPromo();
                    syncItemDeductionTotals();
                    showAlert('Item added successfully', 'success');
                }).catch(() => {
                    window._itemsEdited = true;
                    itemsArray.push(newItem);
                    reevaluateCartPromo();
                    syncItemDeductionTotals();
                    showAlert('Item added successfully', 'success');
                });

                // Clear inputs
                const itemCodeInput = document.getElementById('item_code');
                const itemDescInput = document.getElementById('item_desc');
                const imeiInput = document.getElementById('imei');
                const priceInput = document.getElementById('price');

                itemCodeInput.value = '';
                itemDescInput.value = '';
                imeiInput.value = '';
                imeiInput.removeAttribute('data-imei-found');
                imeiInput.removeAttribute('readonly');
                imeiInput.style.backgroundColor = '#ffffff';
                imeiInput.style.cursor = 'text';
                document.getElementById('qty').value = '0';
                priceInput.value = '';

                // Re-enable price field when clearing
                priceInput.removeAttribute('readonly');
                priceInput.style.backgroundColor = '#ffffff';
                priceInput.style.cursor = 'text';
            }
        }

        function removeItem(index) {
            if (confirm('Are you sure you want to remove this item?')) {
                window._itemsEdited = true;
                itemsArray.splice(index, 1);
                reevaluateCartPromo();
                syncItemDeductionTotals();
            }
        }

        function updateRowItem(index) {
            if (!itemsArray[index]) {
                showAlert('Item not found', 'error');
                return;
            }

            // Get values from the input fields
            const descValue = document.getElementById(`desc_${index}`).value.trim();
            const imeiValue = document.getElementById(`imei_${index}`).value.trim();
            const qtyValue = parseFloat(document.getElementById(`qty_${index}`).value) || 0;
            const priceValue = parseFloat(document.getElementById(`price_${index}`).value) || 0;

            // Validate
            if (!descValue) {
                showAlert('Item Description is required', 'error');
                return;
            }

            if (qtyValue <= 0) {
                showAlert('Quantity must be greater than 0', 'error');
                return;
            }

            if (priceValue < 0) {
                showAlert('Price cannot be negative', 'error');
                return;
            }

            const salesEntryId = document.getElementById('sales_entry_id').value;
            const oldItem = { ...itemsArray[index] };

            // Update the item in the array
            window._itemsEdited = true;
            itemsArray[index].item_description = descValue;
            itemsArray[index].imei = imeiValue;
            itemsArray[index].quantity = qtyValue;
            if (priceValue > 0) {
                itemsArray[index].base_price = priceValue;
            }
            itemsArray[index].price = priceValue;

            // Re-evaluate promo in cart
            reevaluateCartPromo();

            // Send to backend to update database
            fetch('update_sales_item.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    sales_entry_id: salesEntryId,
                    old_item: oldItem,
                    new_item: {
                        item_code: itemsArray[index].item_code,
                        item_description: descValue,
                        imei: imeiValue,
                        quantity: qtyValue,
                        price: itemsArray[index].price
                    },
                    index: index
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Re-render and recalculate
                        window._itemsEdited = true;
                        reevaluateCartPromo();
                        showAlert('Item updated successfully in database', 'success');
                    } else {
                        showAlert(data.message || 'Error updating item in database', 'error');
                        // Revert changes on error
                        itemsArray[index] = oldItem;
                        reevaluateCartPromo();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error updating item in database', 'error');
                    // Revert changes on error
                    itemsArray[index] = oldItem;
                    reevaluateCartPromo();
                });
        }

        function updateItemField(index, field, value) {
            if (itemsArray[index]) {
                if (field === 'quantity' || field === 'price') {
                    itemsArray[index][field] = parseFloat(value) || 0;
                } else {
                    itemsArray[index][field] = value;
                }
                calculateTotals();
            }
        }

        function calculateTotals() {
            let totalQty = 0;
            let totalAmount = 0;

            itemsArray.forEach(item => {
                totalQty += parseInt(item.quantity) || 0;
                totalAmount += (parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0);
            });

            document.getElementById('totalQty').value = totalQty;
            document.getElementById('totalAmount').value = formatCurrency(totalAmount);

            // Recalculate points (you can customize this logic)
            const points = Math.floor(totalAmount / 1000);
            document.getElementById('pointsField').value = points;
        }

        function formatCurrency(amount) {
            return parseFloat(amount).toFixed(2);
        }

        function syncInvoiceNumberDisplay() {
            const invoiceNo = document.getElementById('invoice_no').value;
            const displayInvoiceNumber = document.getElementById('displayInvoiceNumber');
            if (displayInvoiceNumber) {
                displayInvoiceNumber.value = invoiceNo || 'N/A';
            }
        }

        // ========== PROMO FUNCTIONS ==========

        // Function to toggle promo fields visibility
        function togglePromoFields() {
            const promoCheckbox = document.getElementById('promo');
            const promoFields = document.getElementById('promo-fields');
            const promoUsageContainer = document.getElementById('promo_usage_container');

            if (promoCheckbox.checked) {
                promoFields.style.display = 'block';
                applyPromoLogic(false);
            } else {
                promoFields.style.display = 'none';
                if (promoUsageContainer) promoUsageContainer.style.display = 'none';
                // Reset promo selection when unchecked
                document.getElementById('applied_promo').value = '';
                const promoUsageInput = document.getElementById('promo_usage_number');
                if (promoUsageInput) promoUsageInput.value = '';
                window._currentPromoUsageSlot = null;
                activePromoItems = [];
                renderPromoDetails(null);
                reevaluateCartPromo();
            }
        }

        // Helper function to find and allocate available promo slots without conflicts
        function getAvailablePromoSlots(promoId, requiredCount, currentInvoiceNo, currentSalesEntryId, existingSlotStr) {
            const promo = promosData.find(p => String(p.id) === String(promoId));
            if (!promo || !promo.usage_limit) {
                return existingSlotStr || '1';
            }

            const limit = promo.usage_limit;
            const usedByOthers = new Set();

            if (promoUsedSlotsData && promoUsedSlotsData[promoId]) {
                promoUsedSlotsData[promoId].forEach(entry => {
                    const isCurrent = (currentInvoiceNo && String(entry.invoice_no) === String(currentInvoiceNo))
                        || (currentSalesEntryId && String(entry.sales_entry_id) === String(currentSalesEntryId));
                    if (!isCurrent && entry.slot_str) {
                        const matches = String(entry.slot_str).match(/\d+/g);
                        if (matches) {
                            matches.forEach(num => usedByOthers.add(parseInt(num)));
                        }
                    }
                });
            }

            // Parse existing slots already assigned to this invoice
            const currentSlots = [];
            if (existingSlotStr) {
                const matches = String(existingSlotStr).match(/\d+/g);
                if (matches) {
                    matches.forEach(num => {
                        const n = parseInt(num);
                        if (!usedByOthers.has(n) && !currentSlots.includes(n)) {
                            currentSlots.push(n);
                        }
                    });
                }
            }

            // If requiredCount is less than currentSlots, trim to requiredCount
            if (currentSlots.length > requiredCount && requiredCount > 0) {
                currentSlots.splice(requiredCount);
            }

            // Find additional available slots up to limit
            for (let slot = 1; slot <= limit && currentSlots.length < requiredCount; slot++) {
                if (!usedByOthers.has(slot) && !currentSlots.includes(slot)) {
                    currentSlots.push(slot);
                }
            }

            if (currentSlots.length === 0) {
                return existingSlotStr || '1';
            }
            if (currentSlots.length === 1) {
                return String(currentSlots[0]);
            }
            if (currentSlots.length === 2) {
                return `${currentSlots[0]} & ${currentSlots[1]}`;
            }
            const last = currentSlots[currentSlots.length - 1];
            const rest = currentSlots.slice(0, -1).join(', ');
            return `${rest} & ${last}`;
        }

        // Handler when promo usage slot number is edited directly
        function onPromoUsageNumberChange() {
            window._manualPromoSlotEdited = true;
            const usageInput = document.getElementById('promo_usage_number');
            const promoSelect = document.getElementById('applied_promo');
            const promoId = promoSelect ? promoSelect.value : '';
            const promo = promosData.find(p => String(p.id) === String(promoId));

            const slotVal = usageInput ? usageInput.value.trim() : null;
            window._currentPromoUsageSlot = slotVal;

            renderPromoDetails(promo, slotVal);

            // Live update the dropdown option text
            if (promo && promoSelect) {
                const baseName = promo.promo_name;
                const usageLimit = promo.usage_limit;
                for (let i = 0; i < promoSelect.options.length; i++) {
                    if (String(promoSelect.options[i].value) === String(promoId)) {
                        if (slotVal && usageLimit) {
                            promoSelect.options[i].textContent = `${baseName} (this sale: ${slotVal}/${usageLimit})`;
                        } else if (slotVal) {
                            promoSelect.options[i].textContent = `${baseName} (slot #${slotVal})`;
                        } else if (usageLimit) {
                            const count = promo.usage_count || 0;
                            promoSelect.options[i].textContent = `${baseName} (${count}/${usageLimit} used)`;
                        }
                        break;
                    }
                }
            }
        }

        // Function to render promo details
        function renderPromoDetails(promo, usageSlot) {
            const promoDetails = document.getElementById('promo_details');
            if (!promoDetails) return;

            if (!promo || !promo.items || promo.items.length === 0) {
                promoDetails.textContent = 'No promo selected.';
                return;
            }

            const details = promo.items.map((item, index) => {
                const model = item.motor_model ? item.motor_model : 'ALL MODELS';
                const discountType = (item.discount_type || '').toString().trim();
                const discountValue = (item.discount_value || '').toString().trim();
                const freeItem = (item.promo_item || '').toString().trim();

                let benefitLabel = '';
                if (discountType === '%') {
                    const pct = parseFloat(discountValue) || 0;
                    benefitLabel = `${pct}% DISCOUNT`;
                } else if (discountType.toLowerCase() === 'free') {
                    benefitLabel = 'BUY 1 TAKE 1 FREE';
                } else {
                    benefitLabel = `${discountType} ${discountValue}`.trim() || 'SPECIAL PROMO';
                }

                let itemLine = `${index + 1}) MODEL: ${model}\n   BENEFIT: ${benefitLabel}`;
                if ((discountType === '%' || discountType.toLowerCase() === 'free') && freeItem) {
                    itemLine += `\n   PROMO ITEM: ${freeItem}`;
                }

                return itemLine;
            }).join('\n\n');

            // Show which slot(s) THIS sale used (e.g. USAGE SLOT: 2 & 3 / 10)
            let slotLine = '';
            if (usageSlot != null && promo.usage_limit != null) {
                slotLine = `\nUSAGE SLOT: ${usageSlot} / ${promo.usage_limit}`;
            } else if (usageSlot != null) {
                slotLine = `\nUSAGE SLOT: #${usageSlot}`;
            }

            promoDetails.textContent = `PROMO NAME: ${promo.promo_name}${slotLine}\n\n${details}`;
        }

        // Function to apply promo logic
        function applyPromoLogic(isManual) {
            const promoSelect = document.getElementById('applied_promo');
            const promoId = promoSelect.value;
            const usageContainer = document.getElementById('promo_usage_container');
            const usageInput = document.getElementById('promo_usage_number');
            const usageLimitDisplay = document.getElementById('promo_usage_limit_display');

            // Reset promo state
            activePromoItems = [];
            activePromoDiscountLabel = '0';

            if (!promoId) {
                if (usageContainer) usageContainer.style.display = 'none';
                if (usageInput) usageInput.value = '';
                window._currentPromoUsageSlot = null;
                renderPromoDetails(null);
                reevaluateCartPromo();
                return;
            }

            // promosData now includes ALL promos (even limit-reached)
            const promo = promosData.find(p => String(p.id) === String(promoId));
            if (!promo || !promo.items || promo.items.length === 0) {
                if (usageContainer) usageContainer.style.display = 'none';
                renderPromoDetails(null);
                reevaluateCartPromo();
                return;
            }

            activePromoItems = promo.items;

            // Handle promo usage slot container display
            if (usageContainer) {
                if (promo.usage_limit) {
                    usageContainer.style.display = 'block';
                    if (usageLimitDisplay) {
                        usageLimitDisplay.textContent = `/ ${promo.usage_limit} promo used`;
                    }
                } else {
                    usageContainer.style.display = 'none';
                }
            }

            // If manual change, reset manual edit flag
            if (isManual) {
                window._manualPromoSlotEdited = false;
                window._originalLoadedPromoUsageSlot = null;
            }

            // Re-evaluate cart items against the newly active promo
            reevaluateCartPromo();

            // Only alert on manual selection (not on page load)
            if (isManual) {
                alert('Promo Applied: ' + promo.promo_name);
            }
        }

        // Helper function to normalize promo text
        function normalizePromoText(value) {
            return (value || '').toString().toUpperCase().trim();
        }

        // Helper function to check if item matches promo model
        function isItemMatchingModel(itemCode, itemDesc, targetModel) {
            const codeNorm = normalizePromoText(itemCode);
            const descNorm = normalizePromoText(itemDesc);
            const targetNorm = normalizePromoText(targetModel);

            if (!targetNorm || targetNorm === 'ALL MODELS' || targetNorm === 'ALL') {
                return true;
            }

            return (codeNorm === targetNorm || descNorm.includes(targetNorm) || targetNorm.includes(codeNorm));
        }

        // Function to re-evaluate cart and apply promo logic across items (e.g. Buy 1 Take 1, % discount)
        function reevaluateCartPromo() {
            // 1. Reset all items to base prices
            itemsArray.forEach(item => {
                if (item.base_price === undefined || item.base_price === null) {
                    item.base_price = (parseFloat(item.price) > 0) ? parseFloat(item.price) : (parseFloat(item.srp) || parseFloat(item.price) || 0);
                }
                item.price = item.base_price;
                delete item.is_promo_item;
            });

            const promoCheckbox = document.getElementById('promo');
            const isPromoEnabled = promoCheckbox && promoCheckbox.checked;
            const promoSelect = document.getElementById('applied_promo');
            const promoId = promoSelect ? promoSelect.value : '';
            const promo = promosData.find(p => String(p.id) === String(promoId));

            if (!isPromoEnabled || !promo || !activePromoItems || activePromoItems.length === 0) {
                renderItemsTable();
                return;
            }

            let promoAppliedCount = 0;

            // 2. Apply FREE rules (Buy 1 Take 1)
            const freeRules = activePromoItems.filter(item => normalizePromoText(item.discount_type) === 'FREE');

            freeRules.forEach(rule => {
                const buyModel = rule.motor_model ? normalizePromoText(rule.motor_model) : '';
                const freeModel = rule.promo_item ? normalizePromoText(rule.promo_item) : buyModel;

                const isSameItem = (!rule.promo_item || freeModel === buyModel);

                if (!isSameItem && freeModel) {
                    // Different item promo: buy X, get Y free
                    let totalBuyQty = 0;
                    const matchingBuyItems = [];
                    itemsArray.forEach(item => {
                        if (isItemMatchingModel(item.item_code, item.item_description, buyModel)) {
                            totalBuyQty += parseInt(item.quantity) || 0;
                            matchingBuyItems.push(item);
                        }
                    });

                    let freeUnitsRemaining = totalBuyQty;
                    let freeUnitsGiven = 0;

                    itemsArray.forEach(item => {
                        if (isItemMatchingModel(item.item_code, item.item_description, freeModel) && freeUnitsRemaining > 0) {
                            const itemQty = parseInt(item.quantity) || 0;
                            if (itemQty <= freeUnitsRemaining) {
                                item.price = 0;
                                item.is_promo_item = true;
                                freeUnitsRemaining -= itemQty;
                                freeUnitsGiven += itemQty;
                            }
                        }
                    });

                    if (freeUnitsGiven > 0) {
                        matchingBuyItems.forEach(item => { item.is_promo_item = true; });
                        promoAppliedCount += freeUnitsGiven;
                    }
                } else if (isSameItem && buyModel) {
                    // Same item promo: buy 1 take 1
                    let totalUnits = 0;
                    const matchingItems = [];
                    itemsArray.forEach(item => {
                        if (isItemMatchingModel(item.item_code, item.item_description, buyModel)) {
                            totalUnits += parseInt(item.quantity) || 0;
                            matchingItems.push(item);
                        }
                    });

                    const maxFreeAllowed = Math.floor(totalUnits / 2);
                    let freeAssigned = 0;

                    matchingItems.forEach((item, idx) => {
                        if (idx < maxFreeAllowed * 2) {
                            item.is_promo_item = true;
                        }
                        if (idx % 2 === 1 && freeAssigned < maxFreeAllowed) {
                            item.price = 0;
                            freeAssigned++;
                        }
                    });
                    promoAppliedCount += freeAssigned;
                }
            });

            // 3. Apply PERCENTAGE DISCOUNT rules
            const percentRules = activePromoItems.filter(item => item.discount_type === '%');

            percentRules.forEach(rule => {
                const buyModel = rule.motor_model ? normalizePromoText(rule.motor_model) : '';
                const discountModel = rule.promo_item ? normalizePromoText(rule.promo_item) : '';
                const discountPercent = parseFloat(rule.discount_value) || 0;

                if (!discountModel) return;

                const matchingBuyItems = itemsArray.filter(item => isItemMatchingModel(item.item_code, item.item_description, buyModel));

                if (matchingBuyItems.length > 0) {
                    let hasDiscountItem = false;
                    itemsArray.forEach(item => {
                        if (isItemMatchingModel(item.item_code, item.item_description, discountModel)) {
                            const basePrice = parseFloat(item.base_price) || 0;
                            item.price = basePrice * (1 - discountPercent / 100);
                            item.is_promo_item = true;
                            hasDiscountItem = true;
                            promoAppliedCount += (parseInt(item.quantity) || 1);
                        }
                    });
                    if (hasDiscountItem) {
                        matchingBuyItems.forEach(item => { item.is_promo_item = true; });
                    }
                }
            });

            // If promo is selected and items qualify or exist, ensure at least 1 promo count
            const requiredSlotCount = Math.max(promoAppliedCount, 1);

            // Auto-adjust promo slot numbers (e.g. 2 & 3 or 2 & 4) unless user manually edited
            if (!window._manualPromoSlotEdited && promo && promo.usage_limit) {
                const curInv = window._currentInvoiceNo || document.getElementById('invoice_no')?.value || '';
                const curSeId = document.getElementById('sales_entry_id')?.value || '';
                const baseSlotStr = window._originalLoadedPromoUsageSlot || window._currentPromoUsageSlot || '';
                const autoSlots = getAvailablePromoSlots(promo.id, requiredSlotCount, curInv, curSeId, baseSlotStr);
                window._currentPromoUsageSlot = autoSlots;
                const promoUsageInput = document.getElementById('promo_usage_number');
                if (promoUsageInput) {
                    promoUsageInput.value = autoSlots;
                }
            }

            renderPromoDetails(promo, window._currentPromoUsageSlot);

            // Live update dropdown label
            if (promoSelect) {
                const baseName = promo.promo_name;
                const usageLimit = promo.usage_limit;
                for (let i = 0; i < promoSelect.options.length; i++) {
                    if (String(promoSelect.options[i].value) === String(promo.id)) {
                        if (window._currentPromoUsageSlot && usageLimit) {
                            promoSelect.options[i].textContent = `${baseName} (this sale: ${window._currentPromoUsageSlot}/${usageLimit})`;
                        } else if (window._currentPromoUsageSlot) {
                            promoSelect.options[i].textContent = `${baseName} (slot #${window._currentPromoUsageSlot})`;
                        }
                        break;
                    }
                }
            }

            renderItemsTable();
        }

        // ========== END PROMO FUNCTIONS ==========

        function reEntrySale() {
            const salesEntryId = document.getElementById('sales_entry_id').value;
            const invoiceNo = document.getElementById('invoice_no').value;
            const reasonToModify = document.getElementById('reason_to_modify').value.trim();

            console.log('🔄 RE-ENTRY initiated for:', { salesEntryId, invoiceNo });

            if (!salesEntryId) {
                showAlert('No sales entry loaded', 'error');
                return;
            }

            // Validate reason to modify is filled
            if (!reasonToModify) {
                showAlert('Please provide a Reason to Modify before RE-ENTRY', 'error');
                document.getElementById('reason_to_modify').focus();
                return;
            }

            if (!confirm(`Are you sure you want to RE-ENTRY this voided sale?\n\nInvoice: ${invoiceNo}\n\nThis will change the status back to completed.`)) {
                console.log('❌ RE-ENTRY cancelled by user');
                return;
            }

            // Send request to backend to change status from voided to active
            console.log('📤 Sending RE-ENTRY request to backend...');
            fetch('reentry_sale.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    sales_entry_id: salesEntryId,
                    invoice_no: invoiceNo,
                    reason_to_modify: reasonToModify
                })
            })
                .then(response => {
                    console.log('📥 Response status:', response.status);
                    return response.text().then(text => {
                        console.log('📄 Raw response:', text);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}. Response: ${text.substring(0, 500)}`);
                        }
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('❌ Failed to parse JSON. Raw response:', text);
                            throw new Error('Server returned invalid JSON. Check console for details.');
                        }
                    });
                })
                .then(data => {
                    console.log('✅ Response data:', data);
                    if (data.status === 'success') {
                        showAlert(data.message, 'success');

                        // Refresh the page after successful re-entry
                        console.log('🔄 Refreshing page...');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        console.error('❌ RE-ENTRY failed:', data.message);
                        showAlert(data.message || 'Failed to re-entry sale', 'error');
                    }
                })
                .catch(error => {
                    console.error('❌ RE-ENTRY error:', error);
                    showAlert('An error occurred while processing re-entry: ' + error.message, 'error');
                });
        }

        function updateSalesEntry() {
            const salesEntryId = document.getElementById('sales_entry_id').value;

            if (!salesEntryId) {
                showAlert('No sales entry loaded', 'error');
                return;
            }

            // Sync itemsArray with current table input field values if user edited directly
            const tbody = document.getElementById('itemsTableBody');
            if (tbody) {
                const rows = tbody.querySelectorAll('tr:not(#no-items-row)');
                rows.forEach((row, index) => {
                    if (itemsArray[index]) {
                        const descInput = document.getElementById(`desc_${index}`);
                        const imeiInput = document.getElementById(`imei_${index}`);
                        const qtyInput = document.getElementById(`qty_${index}`);
                        const priceInput = document.getElementById(`price_${index}`);

                        if (descInput && descInput.value.trim()) itemsArray[index].item_description = descInput.value.trim();
                        if (imeiInput) itemsArray[index].imei = imeiInput.value.trim();
                        if (qtyInput) itemsArray[index].quantity = parseFloat(qtyInput.value) || 0;
                        if (priceInput) itemsArray[index].price = parseFloat(priceInput.value) || 0;

                        const discInput = document.getElementById(`discount_${index}`);
                        const vouchInput = document.getElementById(`voucher_${index}`);
                        const tokenInput = document.getElementById(`token_${index}`);
                        if (discInput) itemsArray[index].discount_amount = parseFloat(discInput.value) || 0;
                        if (vouchInput) itemsArray[index].voucher_amount = parseFloat(vouchInput.value) || 0;
                        if (tokenInput) itemsArray[index].token_amount = parseFloat(tokenInput.value) || 0;
                    }
                });
            }

            // Save payment data first (silently, validation happens in savePaymentData)
            if (savePaymentData(true) === false) {
                return;
            }

            if (itemsArray.length === 0) {
                showAlert('Please add at least one item', 'error');
                // Scroll to the item input section
                const itemSection = document.querySelector('.item-selection-container');
                if (itemSection) {
                    itemSection.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
                // Focus on the first item input field after scroll
                setTimeout(() => {
                    const itemCodeInput = document.getElementById('item_code');
                    if (itemCodeInput) {
                        itemCodeInput.focus();
                    }
                }, 100);
                return;
            }

            const assistedBy = document.getElementById('assisted_by').value;
            if (!assistedBy) {
                showAlert('Assisted By is required', 'error');
                const assistedByField = document.getElementById('assisted_by');
                assistedByField.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                setTimeout(() => {
                    assistedByField.focus();
                }, 100);
                return;
            }

            const reasonToModify = document.getElementById('reason_to_modify').value.trim();
            if (!reasonToModify) {
                showAlert('Reason to Modify is required', 'error');
                // Immediately scroll to the reason_to_modify field
                const reasonField = document.getElementById('reason_to_modify');
                reasonField.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                // Focus after a brief delay to ensure scroll completes
                setTimeout(() => {
                    reasonField.focus();
                }, 100);
                return;
            }

            // Keep invoice numbers aligned with the per-payment block Invoice No inputs.
            let preorderInvoiceOverride = '';
            let claimInvoiceOverride = '';
            const multiContainer = document.getElementById('multiplePaymentSectionsContainer');
            if (multiContainer && multiContainer.style.display === 'block') {
                const blocks = multiContainer.querySelectorAll('.payment-block');
                if (blocks.length > 0) {
                    const firstInvInput = blocks[0].querySelector('.payment-block-invoice-input');
                    if (firstInvInput) preorderInvoiceOverride = firstInvInput.value.trim();

                    const lastBlock = blocks[blocks.length - 1];
                    const lastInvInput = lastBlock ? lastBlock.querySelector('.payment-block-invoice-input') : null;
                    if (lastInvInput) claimInvoiceOverride = lastInvInput.value.trim();
                }
            }

            let totalAmountToSave = parseFloat(document.getElementById('totalAmount').value.replace(/,/g, '')) || 0;
            try {
                const pdSaved = JSON.parse(document.getElementById('payment_data').value || '{}');
                const paymentsToSum = (pdSaved.payment_type === 'multiple' && Array.isArray(pdSaved.payments))
                    ? pdSaved.payments
                    : [pdSaved];
                let paymentTotal = 0;
                paymentsToSum.forEach((p) => {
                    if (!p || typeof p !== 'object') return;
                    let blockAmt = parseAmount(p.Total || p.amount || p.Amount || p.creditCardAmount || p.debitCardAmount || 0);
                    if (blockAmt <= 0 && (p['Loan Type'] || p.loan_type || p['Loan Balance'] || p.loan_balance || p.payment_partner)) {
                        blockAmt += parseAmount(p['Loan Balance'] || p.loan_balance || 0);
                        blockAmt += parseAmount(p.cash_down_payment_amount || p.cash_dp_amount || 0);
                        blockAmt += parseAmount(p.gcash_down_payment_amount || p.gcash_dp_amount || 0);
                        blockAmt += parseAmount(p.maya_down_payment_amount || p.maya_dp_amount || 0);
                    }
                    paymentTotal += blockAmt;
                });
                // Keep sales_entry.total_amount aligned with the payment total for every method
                if (paymentTotal > 0) {
                    totalAmountToSave = paymentTotal;
                }
            } catch (e) { }

            // Sync per-item deduction inputs from the table before save
            itemsArray.forEach((item, index) => {
                const discInput = document.getElementById(`discount_${index}`);
                const vouchInput = document.getElementById(`voucher_${index}`);
                const tokenInput = document.getElementById(`token_${index}`);
                if (discInput) item.discount_amount = parseFloat(discInput.value) || 0;
                if (vouchInput) item.voucher_amount = parseFloat(vouchInput.value) || 0;
                if (tokenInput) item.token_amount = parseFloat(tokenInput.value) || 0;
            });
            syncItemDeductionTotals();

            const totalDiscount = itemsArray.reduce((s, it) => s + (parseFloat(it.discount_amount) || 0), 0);
            const totalVoucher = itemsArray.reduce((s, it) => s + (parseFloat(it.voucher_amount) || 0), 0);
            const totalToken = itemsArray.reduce((s, it) => s + (parseFloat(it.token_amount) || 0), 0);

            const updateData = {
                sales_entry_id: salesEntryId,
                invoice_no: claimInvoiceOverride || document.getElementById('invoice_no').value,
                original_invoice_no: preorderInvoiceOverride,
                first_name: document.getElementById('first_name').value,
                last_name: document.getElementById('last_name').value,
                address: document.getElementById('address').value,
                contact_no: document.getElementById('contact_no').value,
                email: document.getElementById('email').value,
                assisted_by: assistedBy,
                remarks: document.getElementById('remarks').value,
                reason_to_modify: document.getElementById('reason_to_modify').value,
                promo: document.getElementById('promo').checked ? 1 : 0,
                applied_promo: document.getElementById('applied_promo').value || null,
                promo_usage_number: document.getElementById('promo_usage_number')?.value.trim() || null,
                items: itemsArray,
                total_qty: document.getElementById('totalQty').value,
                discount: totalDiscount,
                voucher_amount: totalVoucher,
                token: totalToken,
                total_amount: totalAmountToSave,
                points: parseFloat(document.getElementById('modifyPoints')?.value || document.getElementById('pointsField').value) || 0,
                commission: parseFloat(document.getElementById('modifyCommission')?.value || document.getElementById('commissionField').value.replace(/,/g, '')) || 0,
                payment_data: document.getElementById('payment_data').value,
                
                // Trade-In Details (only include if section is visible/exists)
                tradein_value: document.getElementById('tradeInValueEdit') ? (parseFloat(document.getElementById('tradeInValueEdit').value) || 0) : null,
                tradein_imei: document.getElementById('tradeInIMEIEdit') ? document.getElementById('tradeInIMEIEdit').value.trim() : null,
                tradein_item_code: document.getElementById('tradeInItemCodeEdit') ? document.getElementById('tradeInItemCodeEdit').value.trim() : null,
                tradein_brand: document.getElementById('tradeInBrandEdit') ? document.getElementById('tradeInBrandEdit').value.trim() : null,
                titu_control: document.getElementById('tituControlEdit') ? document.getElementById('tituControlEdit').value.trim() : null,
                titu_token: document.getElementById('tituTokenEdit') ? document.getElementById('tituTokenEdit').value.trim() : null,
                titu_voucher_total: document.getElementById('tituVoucherTotalEdit') ? (parseFloat(document.getElementById('tituVoucherTotalEdit').value) || 0) : null,
                cross_sell: document.getElementById('crossSellEdit') ? (parseFloat(document.getElementById('crossSellEdit').value) || 0) : null,
                trade_in_voucher: document.getElementById('tradeInVoucherEdit') ? (parseFloat(document.getElementById('tradeInVoucherEdit').value) || 0) : null
            };

            if (claimInvoiceOverride) {
                document.getElementById('invoice_no').value = claimInvoiceOverride;
                syncInvoiceNumberDisplay();
            }

            if (confirm('Are you sure you want to update this sales entry?')) {
                fetch('update_sales_entry.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(updateData)
                })
                    .then(async response => {
                        const text = await response.text();
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Invalid JSON response:', text);
                            throw new Error('Server error: ' + (text ? text.substring(0, 150) : response.statusText));
                        }
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            showAlert('Sales entry updated successfully!', 'success');
                            // Reload the entry to show updated data
                            const invoiceNo = document.getElementById('invoice_no').value;
                            modifySale(invoiceNo);
                        } else {
                            showAlert(data.message || 'Error updating sales entry', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert(error.message || 'Error updating sales entry', 'error');
                    });
            }
        }

        function clearModificationForm() {
            if (confirm('Are you sure you want to clear all modifications?')) {
                document.getElementById('modificationSection').style.display = 'none';
                document.getElementById('search_invoice').value = '';
                currentSalesData = null;
                itemsArray = [];
            }
        }

        function openSearchModal() {
            const itemCodeInput = document.getElementById('item_code');
            const searchTerm = itemCodeInput.value.trim();

            if (searchTerm === '') {
                showAlert('Please enter an Item Code to search!', 'info');
                return;
            }

            const modal = document.getElementById('searchItemModal');
            const resultsBody = document.getElementById('searchResultsBody');

            // Fetch results
            fetch(`search_item.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    resultsBody.innerHTML = '';
                    if (data.status === 'success' && data.data.length > 0) {
                        window.currentSearchResults = data.data; // Store results globally
                        data.data.forEach((item, index) => {
                            const row = `
                            <tr style="border: 1px solid #ccc;">
                                <td style="padding: 10px 12px; border: 1px solid #ccc; font-size: 13px;">${item.item_code}</td>
                                <td style="padding: 10px 12px; border: 1px solid #ccc; font-size: 13px;">${item.description}</td>
                                <td style="padding: 10px 12px; text-align: center; border: 1px solid #ccc;">
                                    <button type="button" class="btn-select" onclick="selectItemFromModal(${index})" style="padding: 5px 14px; background-color: #4caf50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500;">Select</button>
                                </td>
                            </tr>
                        `;
                            resultsBody.insertAdjacentHTML('beforeend', row);
                        });
                    } else {
                        resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px; border: 1px solid #ccc; color: #999;">No items found</td></tr>';
                    }
                    modal.style.display = 'flex'; // Use flex to activate the centering styles
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('An error occurred while searching.', 'error');
                });
        }

        function closeSearchModal() {
            const modal = document.getElementById('searchItemModal');
            modal.style.display = 'none';
        }

        function selectItemFromModal(index) {
            if (!window.currentSearchResults || !window.currentSearchResults[index]) {
                showAlert('Error selecting item', 'error');
                return;
            }

            const item = window.currentSearchResults[index];
            window._lastSelectedItemPrices = item.prices || {};
            window._lastSelectedItemOthersBankEnabled = item.others_bank_enabled || false;

            const itemCodeInput = document.getElementById('item_code');
            const itemDescInput = document.getElementById('item_desc');
            const priceInput = document.getElementById('price');
            const imeiInput = document.getElementById('imei');

            const code = item.item_code || '';
            const description = item.description || '';
            const price = item.price || '';

            // Check if item is serialized first
            if (code) {
                fetch(`check_serial_permission.php?item_code=${encodeURIComponent(code)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success' && data.has_serial) {
                            // Item is serialized - show alert and clear fields
                            showAlert('This item is serialized. Please search by IMEI.', 'info');
                            itemCodeInput.value = '';
                            itemDescInput.value = '';
                            priceInput.value = '';
                            imeiInput.value = '';

                            // Make IMEI field editable for manual IMEI entry
                            imeiInput.removeAttribute('readonly');
                            imeiInput.style.backgroundColor = '#ffffff';
                            imeiInput.style.cursor = 'text';

                            // Re-enable price field
                            priceInput.removeAttribute('readonly');
                            priceInput.style.backgroundColor = '#ffffff';
                            priceInput.style.cursor = 'text';

                            closeSearchModal();

                            // Focus on IMEI field for user to enter IMEI
                            imeiInput.focus();
                            return;
                        }


                        // Item is not serialized - proceed normally and lock IMEI field
                        itemCodeInput.value = code;
                        itemDescInput.value = description;
                        priceInput.value = price ? formatNumber(price) : '';

                        closeSearchModal();

                        // Lock IMEI field for non-serialized items
                        imeiInput.setAttribute('readonly', 'readonly');
                        imeiInput.style.backgroundColor = '#f5f5f5';
                        imeiInput.style.cursor = 'not-allowed';
                        imeiInput.value = '';

                        // Focus on quantity field for next input
                        const qtyInput = document.getElementById('qty');
                        if (qtyInput) {
                            qtyInput.focus();
                        }
                    })
                    .catch(error => {
                        console.error('Error checking serial permission:', error);
                        showAlert('Error checking if item is serialized', 'error');
                    });
            } else {
                // No item code, just populate fields
                itemCodeInput.value = code;
                itemDescInput.value = description;
                priceInput.value = price ? formatNumber(price) : '';
                closeSearchModal();
            }
        }

        // Add Enter key support for Item Code field
        document.addEventListener('DOMContentLoaded', function () {
            const itemCodeInput = document.getElementById('item_code');
            if (itemCodeInput) {
                itemCodeInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        openSearchModal();
                    }
                });
            }

            // Add Enter key support for IMEI field
            const imeiInput = document.getElementById('imei');
            if (imeiInput) {
                imeiInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        const imei = imeiInput.value.trim();
                        if (imei !== '') {
                            searchByIMEI(imei);
                        }
                    }
                });
            }

            // Close modal when clicking outside
            window.onclick = function (event) {
                const searchModal = document.getElementById('searchItemModal');
                if (event.target == searchModal) {
                    closeSearchModal();
                }
            };
        });

        // Stores the original total amount due when the payment modal opens
        let _originalTotalAmountDue = 0;

        function initializePaymentSection() {
            // Save clean template of the payment form on first load
            const singleSection = document.getElementById('singlePaymentSection');
            if (singleSection && !window._paymentFormTemplate) {
                const clone = singleSection.cloneNode(true);
                clone.removeAttribute('id');
                // Clear any pre-filled values in the template clone
                clone.querySelectorAll('input').forEach(inp => {
                    if (inp.type !== 'checkbox' && inp.type !== 'radio') inp.value = '';
                    else inp.checked = false;
                });
                clone.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
                window._paymentFormTemplate = clone.innerHTML;
            }

            // Set global total due
            const totalAmount = document.getElementById('totalAmount').value;
            const globalTotalDueInput = document.getElementById('globalTotalDueInput');
            if (globalTotalDueInput && totalAmount) {
                globalTotalDueInput.value = totalAmount;
                // Store original total for validation
                _originalTotalAmountDue = parseFloat(totalAmount.replace(/,/g, '')) || 0;
            }

            // Clear any previous error messages
            const errorBanner = document.getElementById('paymentErrorBanner');
            if (errorBanner) {
                errorBanner.style.display = 'none';
            }

            // Load existing payment data if available
            const paymentDataInput = document.getElementById('payment_data');
            if (paymentDataInput && paymentDataInput.value) {
                try {
                    const paymentData = JSON.parse(paymentDataInput.value);
                    populatePaymentModal(paymentData);
                } catch (e) {
                    console.error('Error parsing payment data:', e);
                    updateAddPaymentButtonLabel();
                }
            } else {
                updateAddPaymentButtonLabel();
            }
        }

        // Helper to determine payment type
        function getPaymentType(p) {
            if (!p) return null;
            const type = (p.payment_type || '').toLowerCase().trim();
            if (type === 'cash') return 'cash';
            if (type === 'ewallet' || type === 'e-wallet') return 'ewallet';
            if (type === 'online_banking' || type === 'online banking') return 'online_banking';
            if (type === 'payment_partners' || type === 'payment partners' || type.includes('home credit') || type.includes('sto ninio') || type.includes('bank of makati') || type.includes('sumisho') || type.includes('partner')) return 'payment_partners';
            if (type === 'credit_card' || type === 'credit card') return 'credit_card';
            if (type === 'debit_card' || type === 'debit card') return 'debit_card';
            if (type === 'qr_ph' || type === 'qr ph') return 'qr_ph';
            if (type === 'starpay_qr' || type === 'starpay qr') return 'starpay_qr';

            // Fallbacks based on fields
            if (p['Terminal Issuer'] || p['Terminal ID'] || p.creditCardAmount || p.debitCardAmount) {
                if (p.debitCardAmount) return 'debit_card';
                return 'credit_card';
            }
            if (p['E-Wallet'] || p['E-Wallet-Text'] || p['ewallet_type']) return 'ewallet';
            if (p['Loan Type'] || p['loan_type'] || p.loanTypeDropdown || p['Loan Number'] || p['payment_partner']) return 'payment_partners';
            if (p['Bank'] || p['Bank-Text'] || p['bank_name']) return 'online_banking';
            if (p.Amount || p.amount || p.Total) return 'cash';

            return null;
        }

        function parseAmount(val) {
            if (!val) return 0;
            return parseFloat(val.toString().replace(/,/g, '')) || 0;
        }

        function mapPaymentFieldKey(key) {
            if (!key) return key;
            if (key === 'loanTypeDropdown') return 'Loan Type';
            if (key === 'ccTerminalIssuer' || key === 'dcTerminalIssuer') return 'Terminal Issuer';
            if (key === 'ccTerminalId' || key === 'dcTerminalId') return 'Terminal ID';
            if (key === 'creditCardBankDropdown' || key === 'debitCardBankDropdown') return 'Bank';
            if (key === 'creditCardTermsDropdown' || key === 'debitCardTermsDropdown') return 'Terms';
            if (key === 'creditCardAmount' || key === 'debitCardAmount') return 'Amount';
            return key;
        }

        function ensureSelectOption(selectEl, value, label) {
            if (!selectEl || value == null || String(value).trim() === '') return;
            const raw = String(value).trim();
            const exists = Array.from(selectEl.options).some(o => o.value === raw || o.text === raw);
            if (!exists) {
                const opt = document.createElement('option');
                opt.value = raw;
                opt.textContent = label || raw;
                selectEl.appendChild(opt);
            }
        }

        function setSelectByValueOrText(selectEl, storedValue) {
            if (!selectEl) return;
            const raw = String(storedValue == null ? '' : storedValue).trim();
            if (!raw) {
                selectEl.value = '';
                return;
            }

            for (let i = 0; i < selectEl.options.length; i++) {
                if (selectEl.options[i].value === raw) {
                    selectEl.selectedIndex = i;
                    return;
                }
            }

            const normalize = (s) => String(s || '')
                .toLowerCase()
                .replace(/%/g, '')
                .replace(/_/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            const rawN = normalize(raw);

            for (let i = 0; i < selectEl.options.length; i++) {
                const opt = selectEl.options[i];
                if (!opt.value) continue;
                if (normalize(opt.text) === rawN || normalize(opt.value) === rawN) {
                    selectEl.selectedIndex = i;
                    return;
                }
            }

            for (let i = 0; i < selectEl.options.length; i++) {
                const opt = selectEl.options[i];
                if (!opt.value) continue;
                const textN = normalize(opt.text);
                if (textN.includes(rawN) || rawN.includes(textN)) {
                    selectEl.selectedIndex = i;
                    return;
                }
            }
        }

        function updateSectionTotal(section) {
            if (!section) return;
            const totalInput = section.querySelector('.total-input');
            if (!totalInput) return;

            let total = 0;
            if (section.classList.contains('home-credit-section')) {
                // Home Credit section total is Loan Balance + Down Payments
                const inputs = section.querySelectorAll('input[type="text"], input[type="number"]');
                inputs.forEach(input => {
                    let isDpOrLoanBal = false;

                    // Check if it's Loan Balance
                    const formGroup = input.closest('.hc-form-group');
                    if (formGroup) {
                        const label = formGroup.querySelector('label');
                        if (label && label.innerText.includes('Loan Balance')) isDpOrLoanBal = true;
                    }

                    // Check if it's cash/gcash/maya down payment amount
                    if (input.id === 'cash_down_payment_amount' || input.id === 'gcash_down_payment_amount' || input.id === 'maya_down_payment_amount') {
                        isDpOrLoanBal = true;
                    }

                    if (isDpOrLoanBal) {
                        const val = parseFloat(input.value.replace(/,/g, '')) || 0;
                        total += val;
                    }
                });
            } else {
                // For other sections, find the amount input field
                const inputs = section.querySelectorAll('input[type="text"], input[type="number"]');
                inputs.forEach(input => {
                    if (input.classList.contains('total-input')) return;

                    let isAmount = false;
                    if (input.classList.contains('amount-input')) isAmount = true;
                    if (input.id && input.id.toLowerCase().includes('amount')) isAmount = true;

                    const formGroup = input.closest('.hc-form-group');
                    if (formGroup) {
                        const label = formGroup.querySelector('label');
                        if (label && label.innerText.includes('Amount')) isAmount = true;
                    }
                    const enterAmountRow = input.closest('.enter-amount-row');
                    if (enterAmountRow) {
                        const label = enterAmountRow.querySelector('label');
                        if (label && label.innerText.includes('Amount')) isAmount = true;
                    }

                    if (isAmount) {
                        const val = parseFloat(input.value.replace(/,/g, '')) || 0;
                        total += val;
                    }
                });
            }

            totalInput.value = total > 0 ? total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
        }

        // Initialize event listeners scoped inside a block
        function initBlockListeners(block) {
            // Payment Partners checkbox
            const chkPartners = block.querySelector('#chkPaymentPartners');
            const homeCreditSection = block.querySelector('.home-credit-section');
            if (chkPartners && homeCreditSection) {
                chkPartners.addEventListener('change', function () {
                    homeCreditSection.style.display = this.checked ? 'block' : 'none';
                    calculateGlobalTotal();
                });
            }

            // Payment Partners dropdown
            const partnersDropdown = block.querySelector('#paymentPartnersDropdown');
            const partnerTitle = block.querySelector('.home-credit-section h3');
            const loanTypeDropdown = block.querySelector('#loanTypeDropdown');
            if (partnersDropdown) {
                partnersDropdown.addEventListener('change', function () {
                    const selectedText = this.options[this.selectedIndex].text;
                    if (partnerTitle && this.value) {
                        partnerTitle.textContent = selectedText;
                    }
                    if (loanTypeDropdown) {
                        updateLoanTypeOptions(selectedText, loanTypeDropdown);
                    }
                });
            }

            // Cash checkbox
            const chkCash = block.querySelector('input[name="payment_method"][value="cash"]');
            const cashSection = block.querySelector('.cash-section');
            if (chkCash && cashSection) {
                chkCash.addEventListener('change', function () {
                    cashSection.style.display = this.checked ? 'block' : 'none';
                    calculateGlobalTotal();
                });
            }

            // Online Banking checkbox
            const chkOnlineBanking = block.querySelector('input[name="payment_method"][value="online_banking"]');
            const onlineBankingSection = block.querySelector('.online-banking-section');
            if (chkOnlineBanking && onlineBankingSection) {
                chkOnlineBanking.addEventListener('change', function () {
                    onlineBankingSection.style.display = this.checked ? 'block' : 'none';
                    calculateGlobalTotal();
                });
            }

            // E-Wallet checkbox
            const chkEwallet = block.querySelector('input[name="payment_method"][value="ewallet"]');
            const ewalletSection = block.querySelector('.ewallet-section');
            if (chkEwallet && ewalletSection) {
                chkEwallet.addEventListener('change', function () {
                    ewalletSection.style.display = this.checked ? 'block' : 'none';
                    calculateGlobalTotal();
                });
            }

            // Amount input change triggers for calculateGlobalTotal and updateSectionTotal
            const amountInputs = block.querySelectorAll('input[type="text"], input[type="number"]');
            amountInputs.forEach(input => {
                const handleInput = function () {
                    const section = input.closest('.home-credit-section, .credit-card-section, .debit-card-section, .qr-ph-section, .starpay-qr-section, .ewallet-section, .online-banking-section, .cash-section');
                    if (section) {
                        updateSectionTotal(section);
                    }
                    calculateGlobalTotal();
                };
                input.addEventListener('input', handleInput);
                input.addEventListener('blur', handleInput);
            });
        }

        // Scopes population of a payment object to a specific payment method box/form
        function populatePaymentBlock(block, p) {
            // Clear all checkboxes first
            block.querySelectorAll('input[name="payment_method"]').forEach(cb => {
                cb.checked = false;
            });

            // Hide all sections first
            block.querySelectorAll('.home-credit-section, .credit-card-section, .debit-card-section, .qr-ph-section, .starpay-qr-section, .ewallet-section, .online-banking-section, .cash-section').forEach(section => {
                section.style.display = 'none';
            });

            const pType = getPaymentType(p);
            if (!pType) return;

            // 1. CASH
            if (pType === 'cash') {
                const chkCash = block.querySelector('input[name="payment_method"][value="cash"]');
                const cashSection = block.querySelector('.cash-section');
                if (chkCash) chkCash.checked = true;
                if (cashSection) {
                    cashSection.style.display = 'block';
                    const amt = p.amount || p.Amount || p.Total || p.total || 0;
                    const amountInput = cashSection.querySelector('.hc-input');
                    if (amountInput) {
                        amountInput.value = parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                    }
                    const totalInput = cashSection.querySelector('.total-input');
                    if (totalInput) {
                        totalInput.value = amountInput ? amountInput.value : '';
                    }
                }
            }

            // 2. ONLINE BANKING
            if (pType === 'online_banking') {
                const chkOnlineBanking = block.querySelector('input[name="payment_method"][value="online_banking"]');
                const onlineBankingSection = block.querySelector('.online-banking-section');
                if (chkOnlineBanking) chkOnlineBanking.checked = true;
                if (onlineBankingSection) {
                    onlineBankingSection.style.display = 'block';
                    const bankVal = p.Bank || p['Bank-Text'] || p.bank_name || '';
                    const refVal = p['Reference No'] || p.reference_no || '';
                    const amt = p.Amount || p.amount || p.Total || p.total || 0;

                    const select = onlineBankingSection.querySelector('select.hc-input');
                    const textInputs = Array.from(onlineBankingSection.querySelectorAll('input.hc-input'));

                    if (select) {
                        ensureSelectOption(select, bankVal, p['Bank-Text'] || bankVal);
                        setSelectByValueOrText(select, bankVal);
                    }
                    if (textInputs.length >= 2) {
                        textInputs[0].value = refVal;
                        textInputs[1].value = parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                    }
                    const totalInput = onlineBankingSection.querySelector('.total-input');
                    if (totalInput) {
                        totalInput.value = parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                    }
                }
            }

            // 3. E-WALLET
            if (pType === 'ewallet') {
                const chkEwallet = block.querySelector('input[name="payment_method"][value="ewallet"]');
                const ewalletSection = block.querySelector('.ewallet-section');
                if (chkEwallet) chkEwallet.checked = true;
                if (ewalletSection) {
                    ewalletSection.style.display = 'block';
                    const walletType = p['E-Wallet'] || p['E-Wallet-Text'] || p.ewallet_type || '';
                    const custName = p["Customer's Name"] || p.customer_name || '';
                    const refVal = p['Reference No'] || p.reference_no || '';
                    const amt = p.Amount || p.amount || p.Total || p.total || 0;

                    const inputs = ewalletSection.querySelectorAll('.hc-input');
                    if (inputs.length >= 4) {
                        if (inputs[0] && inputs[0].tagName === 'SELECT') {
                            ensureSelectOption(inputs[0], walletType, p['E-Wallet-Text'] || walletType);
                            setSelectByValueOrText(inputs[0], walletType);
                        } else if (inputs[0]) {
                            inputs[0].value = walletType;
                        }
                        if (inputs[1]) inputs[1].value = custName;
                        if (inputs[2]) inputs[2].value = refVal;
                        if (inputs[3]) inputs[3].value = parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                    }
                    const totalInput = ewalletSection.querySelector('.total-input');
                    if (totalInput) {
                        totalInput.value = parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                    }
                }
            }

            // 4. PAYMENT PARTNERS
            if (pType === 'payment_partners') {
                const chkPaymentPartners = block.querySelector('input[name="payment_method"][value="payment_partners"]');
                const paymentPartnersDropdown = block.querySelector('.payment-dropdown');
                const paymentPartnerTitle = block.querySelector('.home-credit-section h3');
                const loanTypeDropdown = block.querySelector('.home-credit-section select');
                const homeCreditSection = block.querySelector('.home-credit-section');
                if (chkPaymentPartners) chkPaymentPartners.checked = true;

                const partnerVal = p.payment_partner || p.payment_type || '';
                const loanType = p['Loan Type'] || p.loan_type || p.loanTypeDropdown || '';
                const loanTerms = p['Loan Terms'] || p.loan_terms || '';
                const custName = p["Customer's Name"] || p.customer_name || '';
                const loanNum = p['Loan Number'] || p.loan_number || '';
                const loanBal = p['Loan Balance'] || p.loan_balance || '';
                const downPaymentMethod = p['Down payment'] || (p.down_payment_methods ? p.down_payment_methods.join(', ') : '');
                const cashDpAmount = parseAmount(p.cash_dp_amount || p.cash_down_payment_amount || 0);
                const gcashReference = p.gcash_reference || p.gcash_down_payment_reference || '';
                const gcashDpAmount = parseAmount(p.gcash_dp_amount || p.gcash_down_payment_amount || 0);
                const mayaReference = p.maya_reference || p.maya_down_payment_reference || '';
                const mayaDpAmount = parseAmount(p.maya_dp_amount || p.maya_down_payment_amount || 0);

                if (paymentPartnersDropdown) {
                    for (let option of paymentPartnersDropdown.options) {
                        if (partnerVal.toLowerCase().includes(option.text.toLowerCase()) || option.text.toLowerCase().includes(partnerVal.toLowerCase()) || option.value === partnerVal) {
                            paymentPartnersDropdown.value = option.value;
                            if (paymentPartnerTitle) paymentPartnerTitle.textContent = option.text;
                            if (loanTypeDropdown) updateLoanTypeOptions(option.text, loanTypeDropdown);
                            break;
                        }
                    }
                }

                if (homeCreditSection) {
                    homeCreditSection.style.display = 'block';
                    if (loanTypeDropdown) {
                        const selectedPartnerText = (paymentPartnersDropdown && paymentPartnersDropdown.selectedIndex > 0)
                            ? paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text
                            : (partnerVal || 'Home Credit');
                        updateLoanTypeOptions(selectedPartnerText, loanTypeDropdown);
                    }
                    const inputs = homeCreditSection.querySelectorAll('.hc-input');
                    if (inputs.length >= 5) {
                        setSelectByValueOrText(inputs[0], loanType);
                        setSelectByValueOrText(inputs[1], loanTerms);
                        inputs[2].value = custName;
                        inputs[3].value = loanNum;
                        inputs[4].value = loanBal;
                    }

                    // Reset Down Payment checkboxes
                    block.querySelectorAll('input[name="down_payment_method"]').forEach(cb => cb.checked = false);
                    const dpCashRow = block.querySelector('#dpCashRow');
                    const dpGcashRow = block.querySelector('#dpGcashRow');
                    const dpMayaRow = block.querySelector('#dpMayaRow');
                    if (dpCashRow) dpCashRow.style.display = 'none';
                    if (dpGcashRow) dpGcashRow.style.display = 'none';
                    if (dpMayaRow) dpMayaRow.style.display = 'none';

                    if (downPaymentMethod || cashDpAmount > 0 || gcashDpAmount > 0 || mayaDpAmount > 0) {
                        const dpMethodLower = (downPaymentMethod || '').toLowerCase();
                        if (dpMethodLower.includes('cash') || cashDpAmount > 0) {
                            const cashCb = block.querySelector('input[name="down_payment_method"][value="cash"]');
                            if (cashCb) cashCb.checked = true;
                            if (dpCashRow) dpCashRow.style.display = 'flex';
                            const cashAmountInput = block.querySelector('#cash_down_payment_amount');
                            if (cashAmountInput && cashDpAmount > 0) {
                                cashAmountInput.value = cashDpAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }
                        if (dpMethodLower.includes('gcash') || dpMethodLower.includes('g-cash') || gcashDpAmount > 0) {
                            const gcashCb = block.querySelector('input[name="down_payment_method"][value="gcash"]');
                            if (gcashCb) gcashCb.checked = true;
                            if (dpGcashRow) dpGcashRow.style.display = 'flex';
                            const gcashRefInput = block.querySelector('#gcash_down_payment_reference');
                            const gcashAmountInput = block.querySelector('#gcash_down_payment_amount');
                            if (gcashRefInput) gcashRefInput.value = gcashReference;
                            if (gcashAmountInput && gcashDpAmount > 0) {
                                gcashAmountInput.value = gcashDpAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }
                        if (dpMethodLower.includes('maya') || mayaDpAmount > 0) {
                            const mayaCb = block.querySelector('input[name="down_payment_method"][value="maya"]');
                            if (mayaCb) mayaCb.checked = true;
                            if (dpMayaRow) dpMayaRow.style.display = 'flex';
                            const mayaRefInput = block.querySelector('#maya_down_payment_reference');
                            const mayaAmountInput = block.querySelector('#maya_down_payment_amount');
                            if (mayaRefInput) mayaRefInput.value = mayaReference;
                            if (mayaAmountInput && mayaDpAmount > 0) {
                                mayaAmountInput.value = mayaDpAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }
                    }

                    const totalInput = homeCreditSection.querySelector('.total-input');
                    if (totalInput) {
                        let dpTotal = cashDpAmount + gcashDpAmount + mayaDpAmount;
                        let totalVal = (parseFloat(loanBal.replace(/,/g, '')) || 0) + dpTotal;
                        totalInput.value = totalVal > 0 ? totalVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                    }
                }
            }

            // 5. CREDIT CARD
            if (pType === 'credit_card') {
                const chkCardPayment = block.querySelector('#chkCardPayment');
                const cardPaymentDropdown = block.querySelector('#cardPaymentDropdown');
                if (chkCardPayment) chkCardPayment.checked = true;
                if (cardPaymentDropdown) cardPaymentDropdown.value = 'credit_card';

                const creditCardSection = block.querySelector('.credit-card-section');
                if (creditCardSection) {
                    creditCardSection.style.display = 'block';

                    const terminalIssuer = p['Terminal Issuer'] || '';
                    const terminalId = p['Terminal ID'] || '';
                    const bank = p['Bank'] || '';
                    const terms = p['Terms'] || '';
                    const mid = p['MID'] || '';
                    const cardNo = p['Card No'] || '';
                    const approvalCode = p['Approval Code'] || '';
                    const batch = p['Batch'] || '';
                    const amt = p['Amount'] || p.creditCardAmount || p.amount || p.Total || p.total || 0;

                    const ccIssuerEl = block.querySelector('#ccTerminalIssuer');
                    const ccIdEl = block.querySelector('#ccTerminalId');
                    const ccBankEl = block.querySelector('#creditCardBankDropdown');
                    const ccTermsEl = block.querySelector('#creditCardTermsDropdown');
                    const ccAmtEl = block.querySelector('#creditCardAmount');

                    if (ccIssuerEl) {
                        ensureSelectOption(ccIssuerEl, terminalIssuer);
                        setSelectByValueOrText(ccIssuerEl, terminalIssuer);
                        // Rebuild terminal ID options for this issuer
                        const prevEvent = window.event;
                        try {
                            window.event = { target: ccIssuerEl };
                            filterTerminalIds('cc');
                        } finally {
                            window.event = prevEvent;
                        }
                    }
                    if (ccIdEl) {
                        ensureSelectOption(ccIdEl, terminalId);
                        setSelectByValueOrText(ccIdEl, terminalId);
                        // Populate banks when Terminal ID is set, preserving bank and terms values
                        if (terminalId) {
                            filterBanksByTerminalId('cc', bank, terms);
                        }
                    }
                    if (ccBankEl) {
                        ensureSelectOption(ccBankEl, bank);
                        setSelectByValueOrText(ccBankEl, bank);
                    }
                    if (ccTermsEl) {
                        ensureSelectOption(ccTermsEl, terms);
                        setSelectByValueOrText(ccTermsEl, terms);
                    }

                    const getByLabel = (lbl) => {
                        const fg = Array.from(creditCardSection.querySelectorAll('.hc-form-group')).find(g => {
                            const l = g.querySelector('label');
                            return l && l.innerText.replace(':', '').trim() === lbl;
                        });
                        return fg ? fg.querySelector('input.hc-input, select.hc-input') : null;
                    };

                    const midEl = getByLabel('MID'); if (midEl) midEl.value = mid;
                    const cardNoEl = getByLabel('Card No'); if (cardNoEl) cardNoEl.value = cardNo;
                    const approvalEl = getByLabel('Approval Code'); if (approvalEl) approvalEl.value = approvalCode;
                    const batchEl = getByLabel('Batch'); if (batchEl) batchEl.value = batch;
                    if (ccAmtEl) ccAmtEl.value = parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';

                    const totalInput = creditCardSection.querySelector('.total-input');
                    if (totalInput) totalInput.value = ccAmtEl ? ccAmtEl.value : (parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '');
                }
            }

            // 6. DEBIT CARD
            if (pType === 'debit_card') {
                const chkCardPayment = block.querySelector('#chkCardPayment');
                const cardPaymentDropdown = block.querySelector('#cardPaymentDropdown');
                if (chkCardPayment) chkCardPayment.checked = true;
                if (cardPaymentDropdown) cardPaymentDropdown.value = 'debit_card';

                const debitCardSection = block.querySelector('.debit-card-section');
                if (debitCardSection) {
                    debitCardSection.style.display = 'block';

                    const terminalIssuer = p['Terminal Issuer'] || '';
                    const terminalId = p['Terminal ID'] || '';
                    const bank = p['Bank'] || '';
                    const terms = p['Terms'] || '';
                    const mid = p['MID'] || '';
                    const cardNo = p['Card No'] || '';
                    const approvalCode = p['Approval Code'] || '';
                    const batch = p['Batch'] || '';
                    const amt = p['Amount'] || p.debitCardAmount || p.amount || p.Total || p.total || 0;

                    const dcIssuerEl = block.querySelector('#dcTerminalIssuer');
                    const dcIdEl = block.querySelector('#dcTerminalId');
                    const dcBankEl = block.querySelector('#debitCardBankDropdown');
                    const dcTermsEl = block.querySelector('#debitCardTermsDropdown');
                    const dcAmtEl = block.querySelector('#debitCardAmount');

                    if (dcIssuerEl) {
                        ensureSelectOption(dcIssuerEl, terminalIssuer);
                        setSelectByValueOrText(dcIssuerEl, terminalIssuer);
                        const prevEvent = window.event;
                        try {
                            window.event = { target: dcIssuerEl };
                            filterTerminalIds('dc');
                        } finally {
                            window.event = prevEvent;
                        }
                    }
                    if (dcIdEl) {
                        ensureSelectOption(dcIdEl, terminalId);
                        setSelectByValueOrText(dcIdEl, terminalId);
                        // Populate banks when Terminal ID is set, preserving bank and terms values
                        if (terminalId) {
                            filterBanksByTerminalId('dc', bank, terms);
                        }
                    }
                    if (dcBankEl) {
                        ensureSelectOption(dcBankEl, bank);
                        setSelectByValueOrText(dcBankEl, bank);
                    }
                    if (dcTermsEl) {
                        ensureSelectOption(dcTermsEl, terms);
                        setSelectByValueOrText(dcTermsEl, terms);
                    }

                    const getByLabel = (lbl) => {
                        const fg = Array.from(debitCardSection.querySelectorAll('.hc-form-group')).find(g => {
                            const l = g.querySelector('label');
                            return l && l.innerText.replace(':', '').trim() === lbl;
                        });
                        return fg ? fg.querySelector('input.hc-input, select.hc-input') : null;
                    };

                    const midEl = getByLabel('MID'); if (midEl) midEl.value = mid;
                    const cardNoEl = getByLabel('Card No'); if (cardNoEl) cardNoEl.value = cardNo;
                    const approvalEl = getByLabel('Approval Code'); if (approvalEl) approvalEl.value = approvalCode;
                    const batchEl = getByLabel('Batch'); if (batchEl) batchEl.value = batch;
                    if (dcAmtEl) {
                        dcAmtEl.value = parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                    } else {
                        const amtEl = getByLabel('Amount');
                        if (amtEl) amtEl.value = parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                    }

                    const totalInput = debitCardSection.querySelector('.total-input');
                    if (totalInput) totalInput.value = parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                }
            }

            // 7. QR PH
            if (pType === 'qr_ph') {
                const chkQR = block.querySelector('#chkQR');
                const qrDropdown = block.querySelector('#qrDropdown');
                if (chkQR) chkQR.checked = true;
                if (qrDropdown) qrDropdown.value = 'qr_ph';

                const qrPhSection = block.querySelector('.qr-ph-section');
                if (qrPhSection) {
                    qrPhSection.style.display = 'block';

                    const bank = p['Bank'] || p['Bank-Text'] || '';
                    const custName = p["Customer's Name"] || '';
                    const refNo = p['Reference No'] || '';
                    const amt = p['Amount'] || p.amount || p.Total || p.total || 0;

                    const getByLabel = (lbl) => {
                        const fg = Array.from(qrPhSection.querySelectorAll('.hc-form-group')).find(g => {
                            const l = g.querySelector('label');
                            return l && l.innerText.replace(':', '').trim() === lbl;
                        });
                        return fg ? fg.querySelector('input.hc-input, select.hc-input') : null;
                    };

                    const bankEl = getByLabel('Bank');
                    if (bankEl) {
                        ensureSelectOption(bankEl, bank, p['Bank-Text'] || bank);
                        setSelectByValueOrText(bankEl, bank);
                    }
                    const nameEl = getByLabel("Customer's Name"); if (nameEl) nameEl.value = custName;
                    const refEl = getByLabel('Reference No'); if (refEl) refEl.value = refNo;
                    const amtEl = getByLabel('Amount'); if (amtEl) amtEl.value = parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';

                    const totalInput = qrPhSection.querySelector('.total-input');
                    if (totalInput) totalInput.value = parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                }
            }

            // 8. STARPAY QR
            if (pType === 'starpay_qr') {
                const chkQR = block.querySelector('#chkQR');
                const qrDropdown = block.querySelector('#qrDropdown');
                if (chkQR) chkQR.checked = true;
                if (qrDropdown) qrDropdown.value = 'starpay_qr';

                const starpayQrSection = block.querySelector('.starpay-qr-section');
                if (starpayQrSection) {
                    starpayQrSection.style.display = 'block';

                    const bank = p['Bank'] || p['Bank-Text'] || '';
                    const custName = p["Customer's Name"] || '';
                    const refNo = p['Reference No'] || '';
                    const amt = p['Amount'] || p.amount || p.Total || p.total || 0;

                    const getByLabel = (lbl) => {
                        const fg = Array.from(starpayQrSection.querySelectorAll('.hc-form-group')).find(g => {
                            const l = g.querySelector('label');
                            return l && l.innerText.replace(':', '').trim() === lbl;
                        });
                        return fg ? fg.querySelector('input.hc-input, select.hc-input') : null;
                    };

                    const bankEl = getByLabel('Bank');
                    if (bankEl) {
                        ensureSelectOption(bankEl, bank, p['Bank-Text'] || bank);
                        setSelectByValueOrText(bankEl, bank);
                    }
                    const nameEl = getByLabel("Customer's Name"); if (nameEl) nameEl.value = custName;
                    const refEl = getByLabel('Reference No'); if (refEl) refEl.value = refNo;
                    const amtEl = getByLabel('Amount'); if (amtEl) amtEl.value = parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';

                    const totalInput = starpayQrSection.querySelector('.total-input');
                    if (totalInput) totalInput.value = parseAmount(amt) > 0 ? parseAmount(amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                }
            }
        }

        function paymentOrdinal(n) {
            const s = ['th', 'st', 'nd', 'rd'];
            const v = n % 100;
            return n + (s[(v - 20) % 10] || s[v] || s[0]);
        }

        function getPaymentBlockCount() {
            const multiContainer = document.getElementById('multiplePaymentSectionsContainer');
            if (multiContainer && multiContainer.style.display === 'block') {
                return multiContainer.querySelectorAll('.payment-block').length;
            }
            const singleSection = document.getElementById('singlePaymentSection');
            if (singleSection && singleSection.style.display !== 'none') {
                const checked = singleSection.querySelector('input[name="payment_method"]:checked');
                const visibleSec = singleSection.querySelector(
                    '.home-credit-section[style*="display: block"], .credit-card-section[style*="display: block"], .debit-card-section[style*="display: block"], .qr-ph-section[style*="display: block"], .starpay-qr-section[style*="display: block"], .ewallet-section[style*="display: block"], .online-banking-section[style*="display: block"], .cash-section[style*="display: block"]'
                );
                return (checked || visibleSec) ? 1 : 0;
            }
            return 0;
        }

        function updateAddPaymentButtonLabel() {
            const btn = document.getElementById('btnAddPayment');
            if (!btn) return;
            btn.textContent = getPaymentBlockCount() > 0 ? 'Add More Payment' : 'Add Payment';
        }

        function formatPaymentBlockDate(raw) {
            if (!raw) return '';
            const str = String(raw).trim();
            if (!str || str.startsWith('0000-00-00')) return '';
            // Prefer YYYY-MM-DD for input[type=date]
            const m = str.match(/^(\d{4}-\d{2}-\d{2})/);
            if (m) return m[1];
            const d = new Date(str);
            if (!isNaN(d.getTime())) {
                const yyyy = d.getFullYear();
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                return `${yyyy}-${mm}-${dd}`;
            }
            return '';
        }

        function getPaymentStageMeta(p, idx, payments) {
            const list = Array.isArray(payments) ? payments : [];
            // Prefer backend stamp from payment history (accurate for preorder2 vs claim)
            if (p && typeof p === 'object') {
                if (p.stage_label) {
                    const label = String(p.stage_label).trim().toUpperCase();
                    const isClaim = !!p.is_claim_stage || label === 'CLAIM PRE-ORDER' || label.startsWith('CLAIM');
                    return { label, isClaim };
                }
                if (p.is_claim_stage) {
                    return { label: 'CLAIM PRE-ORDER', isClaim: true };
                }
            }
            // Fallback: label by sequence among non-claim stages — NEVER assume last = claim
            let preorderNum = 0;
            for (let i = 0; i <= idx; i++) {
                const cur = list[i];
                if (cur && cur.is_claim_stage) continue;
                preorderNum++;
            }
            const thisIsClaim = !!(p && p.is_claim_stage);
            if (thisIsClaim) return { label: 'CLAIM PRE-ORDER', isClaim: true };
            const label = preorderNum <= 1 ? 'PRE-ORDER' : `PRE-ORDER ${preorderNum}`;
            return { label, isClaim: false };
        }

        function buildPaymentBlockHeader(idx, totalPayments, invoiceNo, paymentDate, paymentObj, paymentsList) {
            const ordNum = paymentOrdinal(idx + 1);
            const meta = getPaymentStageMeta(paymentObj || {}, idx, paymentsList || []);
            let headerPrefix = `${ordNum} Payment Method`;
            if (meta.label) {
                headerPrefix = `${ordNum} Payment Method &nbsp;&nbsp; ${meta.label}`;
            }
            const dateVal = formatPaymentBlockDate(paymentDate);
            return `
                <div class="payment-block-header" style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #a8a8a8ff; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;"
                    data-stage-label="${meta.label || ''}" data-is-claim="${meta.isClaim ? '1' : '0'}">
                    <h4 style="margin: 0; color: #333; font-size: 16px; font-weight: 600; white-space: nowrap;">${headerPrefix} -</h4>
                    <input type="text"
                        class="payment-block-invoice-input"
                        value="${invoiceNo || ''}"
                        placeholder="Invoice No."
                        style="font-size: 15px; font-weight: 700; color: #1565c0; border: 1.5px solid #90caf9; border-radius: 5px; padding: 4px 10px; background: #e3f2fd; outline: none; min-width: 100px; max-width: 200px; letter-spacing: 0.5px;"
                        oninput="this.style.width = Math.max(100, this.value.length * 10) + 'px';"
                    >
                    <label style="display:flex; align-items:center; gap:6px; margin:0; font-size:13px; font-weight:600; color:#555; white-space:nowrap;">
                        Date:
                        <input type="date"
                            class="payment-block-date-input"
                            value="${dateVal}"
                            style="font-size: 14px; font-weight: 600; color: #1565c0; border: 1.5px solid #90caf9; border-radius: 5px; padding: 4px 8px; background: #e3f2fd; outline: none;"
                        >
                    </label>
                    <button type="button" class="btn-remove-payment-block" onclick="removePaymentBlock(this)"
                        title="Remove this payment"
                        style="margin-left: auto; padding: 6px 12px; background: #c62828; color: #fff; border: none; border-radius: 4px; font-size: 12px; font-weight: 700; cursor: pointer;">
                        Remove
                    </button>
                </div>
            `;
        }

        function ensurePaymentFormTemplate() {
            const singleSection = document.getElementById('singlePaymentSection');
            if (singleSection && !window._paymentFormTemplate) {
                const clone = singleSection.cloneNode(true);
                clone.removeAttribute('id');
                clone.querySelectorAll('input').forEach(inp => {
                    if (inp.type !== 'checkbox' && inp.type !== 'radio') inp.value = '';
                    else inp.checked = false;
                });
                clone.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
                window._paymentFormTemplate = clone.innerHTML;
            }
            return window._paymentFormTemplate || '';
        }

        function createEmptyPaymentBlock(idx, totalPayments, invoiceNo, paymentDate, paymentObj, paymentsList) {
            ensurePaymentFormTemplate();
            const block = document.createElement('div');
            block.className = 'payment-block';
            block.style.cssText = 'margin-bottom:25px; padding:20px; border:1px solid #ccc; border-radius:8px; background:#fff;';
            if (paymentObj) {
                block._paymentStageMeta = paymentObj;
            }
            block.innerHTML = buildPaymentBlockHeader(idx, totalPayments, invoiceNo, paymentDate, paymentObj, paymentsList) + (window._paymentFormTemplate || '');
            return block;
        }

        function relabelPaymentBlocks() {
            const multiContainer = document.getElementById('multiplePaymentSectionsContainer');
            if (!multiContainer) return;
            const blocks = multiContainer.querySelectorAll('.payment-block');
            const total = blocks.length;
            const origInvoice = window._currentOriginalInvoiceNo || '';
            const curInvoice = window._currentInvoiceNo || '';
            const paymentsList = Array.from(blocks).map(b => b._paymentStageMeta || {});

            blocks.forEach((block, idx) => {
                const invInput = block.querySelector('.payment-block-invoice-input');
                const dateInput = block.querySelector('.payment-block-date-input');
                const currentInv = invInput ? invInput.value.trim() : '';
                const currentDate = dateInput ? dateInput.value.trim() : '';
                let fallbackInv = currentInv;
                if (!fallbackInv && window._isClaimPreorder && total > 1) {
                    fallbackInv = (idx < total - 1)
                        ? ((idx === 0 ? origInvoice : '') || origInvoice)
                        : curInvoice;
                }
                const pMeta = block._paymentStageMeta || paymentsList[idx] || {};
                // Keep stage labels stable when relabeling (don't flip last to CLAIM)
                if (!pMeta.stage_label) {
                    const meta = getPaymentStageMeta(pMeta, idx, paymentsList);
                    pMeta.stage_label = meta.label;
                    pMeta.is_claim_stage = meta.isClaim ? 1 : 0;
                    block._paymentStageMeta = pMeta;
                    paymentsList[idx] = pMeta;
                }
                const headerHtml = buildPaymentBlockHeader(idx, total, fallbackInv, currentDate, pMeta, paymentsList);
                const oldHeader = block.querySelector('.payment-block-header');
                if (oldHeader) {
                    const wrap = document.createElement('div');
                    wrap.innerHTML = headerHtml;
                    oldHeader.replaceWith(wrap.firstElementChild);
                }
            });
            updateAddPaymentButtonLabel();
        }

        function switchToMultiPaymentMode(existingPayments) {
            ensurePaymentFormTemplate();
            const multiContainer = document.getElementById('multiplePaymentSectionsContainer');
            const singleSection = document.getElementById('singlePaymentSection');
            if (!multiContainer) return;

            multiContainer.style.display = 'block';
            multiContainer.innerHTML = '';
            if (singleSection) singleSection.style.display = 'none';

            const payments = Array.isArray(existingPayments) ? existingPayments : [];
            const origInvoice = window._currentOriginalInvoiceNo || '';
            const curInvoice = window._currentInvoiceNo || '';

            if (payments.length === 0) {
                const today = formatPaymentBlockDate(new Date().toISOString());
                const emptyMeta = { stage_label: 'PRE-ORDER', is_claim_stage: 0 };
                const block = createEmptyPaymentBlock(0, 1, curInvoice || origInvoice || '', today, emptyMeta, [emptyMeta]);
                multiContainer.appendChild(block);
                initBlockListeners(block);
            } else {
                payments.forEach((p, idx) => {
                    const blockInv = (p && p.block_invoice_no) ? String(p.block_invoice_no).trim() : '';
                    let inv = blockInv;
                    if (!inv) {
                        if (window._isClaimPreorder && payments.length > 1) {
                            inv = (idx < payments.length - 1)
                                ? ((idx === 0 ? origInvoice : '') || origInvoice)
                                : curInvoice;
                        } else {
                            inv = curInvoice || origInvoice || '';
                        }
                    }
                    const payDate = formatPaymentBlockDate(
                        (p && (p.block_payment_date || p.payment_date || p.date)) || ''
                    );
                    // Ensure stage_label from history (PRE-ORDER / PRE-ORDER 2 / CLAIM)
                    if (p && !p.stage_label) {
                        const meta = getPaymentStageMeta(p, idx, payments);
                        p.stage_label = meta.label;
                        p.is_claim_stage = meta.isClaim ? 1 : 0;
                    }
                    const block = createEmptyPaymentBlock(idx, payments.length, inv, payDate, p, payments);
                    multiContainer.appendChild(block);
                    initBlockListeners(block);
                    if (p) populatePaymentBlock(block, p);
                });
            }
            relabelPaymentBlocks();
            calculateGlobalTotal();
        }

        function addPaymentBlock() {
            ensurePaymentFormTemplate();
            const multiContainer = document.getElementById('multiplePaymentSectionsContainer');
            const singleSection = document.getElementById('singlePaymentSection');
            if (!multiContainer) return;

            const wasSingle = !multiContainer.style.display || multiContainer.style.display === 'none';
            if (wasSingle) {
                let existing = [];
                if (singleSection && singleSection.style.display !== 'none') {
                    try {
                        const collected = collectDataFromBlock(singleSection);
                        if (collected && collected.payment_type) {
                            collected.block_invoice_no = window._currentInvoiceNo || window._currentOriginalInvoiceNo || '';
                            existing.push(collected);
                        }
                    } catch (e) { }
                }
                if (existing.length === 0) {
                    const paymentDataInput = document.getElementById('payment_data');
                    if (paymentDataInput && paymentDataInput.value) {
                        try {
                            const pd = JSON.parse(paymentDataInput.value);
                            if (pd && pd.payment_type === 'multiple' && Array.isArray(pd.payments)) {
                                existing = pd.payments;
                            } else if (pd && pd.payment_type) {
                                existing = [pd];
                            }
                        } catch (e) { }
                    }
                }
                switchToMultiPaymentMode(existing);
                // No prior payment: switch already created one empty block ("Add Payment")
                if (existing.length === 0) {
                    const first = multiContainer.querySelector('.payment-block');
                    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    updateAddPaymentButtonLabel();
                    return;
                }
                // Had an existing payment converted — fall through to add one more below
            }

            const blocks = multiContainer.querySelectorAll('.payment-block');
            const newIdx = blocks.length;
            const today = formatPaymentBlockDate(new Date().toISOString());
            const paymentsList = Array.from(blocks).map(b => b._paymentStageMeta || {});
            const newMeta = {
                stage_label: (newIdx === 0) ? 'PRE-ORDER' : `PRE-ORDER ${newIdx + 1}`,
                is_claim_stage: 0
            };
            paymentsList.push(newMeta);
            const block = createEmptyPaymentBlock(newIdx, newIdx + 1, window._currentInvoiceNo || '', today, newMeta, paymentsList);
            multiContainer.appendChild(block);
            initBlockListeners(block);
            relabelPaymentBlocks();
            calculateGlobalTotal();
            block.scrollIntoView({ behavior: 'smooth', block: 'center' });
            updateAddPaymentButtonLabel();
        }

        function removePaymentBlock(btn) {
            const block = btn ? btn.closest('.payment-block') : null;
            const multiContainer = document.getElementById('multiplePaymentSectionsContainer');
            if (!block || !multiContainer) return;
            if (!confirm('Remove this payment block?')) return;

            block.remove();
            const remaining = multiContainer.querySelectorAll('.payment-block');
            if (remaining.length === 0) {
                multiContainer.style.display = 'none';
                multiContainer.innerHTML = '';
                const singleSection = document.getElementById('singlePaymentSection');
                if (singleSection) singleSection.style.display = 'block';
            } else {
                relabelPaymentBlocks();
            }
            calculateGlobalTotal();
            updateAddPaymentButtonLabel();
        }

        function populatePaymentModal(paymentData) {
            console.log('=== POPULATE PAYMENT MODAL ===');
            console.log('Received paymentData:', paymentData);

            if (!paymentData || typeof paymentData !== 'object') {
                console.log('Invalid payment data - not an object or null');
                updateAddPaymentButtonLabel();
                return;
            }

            // ---------- MULTIPLE PAYMENT MODE (PRE-ORDER / CLAIM) ----------
            if (paymentData.payment_type === 'multiple' && Array.isArray(paymentData.payments) && paymentData.payments.length > 1) {
                switchToMultiPaymentMode(paymentData.payments);
                updateAddPaymentButtonLabel();
                return; // Done for multiple payment mode
            }

            // ---------- SINGLE PAYMENT MODE ----------
            // Ensure the single section is visible and multi-container hidden
            const multiContainerEl = document.getElementById('multiplePaymentSectionsContainer');
            const singleSectionEl = document.getElementById('singlePaymentSection');
            if (multiContainerEl) { multiContainerEl.style.display = 'none'; multiContainerEl.innerHTML = ''; }
            if (singleSectionEl) singleSectionEl.style.display = 'block';

            // Normalize payments list
            let payments = [];
            if (paymentData.payment_type === 'multiple' && Array.isArray(paymentData.payments)) {
                payments = paymentData.payments;
            } else if (Array.isArray(paymentData)) {
                payments = paymentData;
            } else {
                payments = [paymentData];
            }

            // Expand any payment type with " + " (legacy multiple format)
            let expandedPayments = [];
            payments.forEach(p => {
                if (p && typeof p.payment_type === 'string' && p.payment_type.includes(' + ') && p.payment_type !== 'multiple') {
                    const parts = p.payment_type.split(' + ').map(t => t.trim());
                    parts.forEach((part, idx) => {
                        const newP = { payment_type: part };
                        Object.keys(p).forEach(key => {
                            if (key === 'payment_type') return;
                            const val = p[key];
                            if (typeof val === 'string' && val.includes(' | ')) {
                                const valParts = val.split(' | ').map(v => v.trim());
                                newP[key] = valParts[idx] || valParts[0] || '';
                            } else {
                                newP[key] = val;
                            }
                        });
                        expandedPayments.push(newP);
                    });
                } else {
                    expandedPayments.push(p);
                }
            });
            payments = expandedPayments;

            console.log('Normalized Payments Array to process:', payments);

            // Populate the single payment block
            populatePaymentBlock(singleSectionEl, payments[0]);

            // Render Payment History Breakdown in paymentBreakdownBanner if it is a multiple payment
            let breakdownRowsHtml = '';
            if (paymentData.payment_type === 'multiple' && Array.isArray(paymentData.payments)) {
                const totalPayments = paymentData.payments.length;
                paymentData.payments.forEach((p, idx) => {
                    let typeName = p.payment_type || 'Payment';
                    typeName = typeName.charAt(0).toUpperCase() + typeName.slice(1);
                    if (typeName === 'Online_banking') typeName = 'Online Banking';
                    if (typeName === 'Credit_card') typeName = 'Credit Card';
                    if (typeName === 'Debit_card') typeName = 'Debit Card';
                    if (typeName === 'Qr_ph') typeName = 'QR PH';
                    if (typeName === 'Starpay_qr') typeName = 'Starpay QR';
                    if (typeName === 'Payment_partners') typeName = 'Payment Partner';

                    const ordinal = (n) => {
                        const s = ['th', 'st', 'nd', 'rd'];
                        const v = n % 100;
                        return n + (s[(v - 20) % 10] || s[v] || s[0]);
                    };
                    const numLabel = ordinal(idx + 1) + ' Payment';

                    let invLabel = '';
                    const origInvoice = window._currentOriginalInvoiceNo;
                    const curInvoice = window._currentInvoiceNo;

                    const effectiveInv = (p && typeof p === 'object' && p.block_invoice_no) ? (p.block_invoice_no || '') : '';
                    const meta = getPaymentStageMeta(p, idx, paymentData.payments);
                    const isClaim = meta.isClaim;
                    const headerColor = isClaim ? '#2e7d32' : '#1565c0';
                    const headerBg = isClaim ? '#e8f5e9' : '#e3f0ff';
                    const borderCol = isClaim ? '#a5d6a7' : '#90caf9';
                    const tag = meta.label || (isClaim ? 'CLAIM' : 'PRE-ORDER');
                    const tagBg = isClaim ? '#2e7d32' : '#1565c0';

                    if (isClaim) {
                        const inv = effectiveInv || curInvoice;
                        invLabel = inv ? ` — Invoice #${inv}` : '';
                    } else {
                        const inv = effectiveInv || (idx === 0 ? origInvoice : '') || origInvoice;
                        invLabel = inv ? ` — Invoice #${inv}` : '';
                    }

                    const amt = parseAmount(p.amount || p.Amount || p.Total || p.total || 0);
                    const formattedAmt = amt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    // Build order breakdown rows from units array
                    let unitRowsHtml = '';
                    const units = Array.isArray(p.units) ? p.units : [];
                    if (units.length > 0) {
                        units.forEach(unit => {
                            unitRowsHtml += `
                                <div style="display:flex; align-items:center; gap:6px; padding: 3px 12px 3px 20px; font-size:13px; color:#555;">
                                    <span style="color:#aaa; font-size:10px;">▶</span>
                                    <span>${unit}</span>
                                </div>
                            `;
                        });
                    } else {
                        unitRowsHtml = `<div style="padding: 3px 12px 3px 20px; font-size:12px; color:#aaa; font-style:italic;">No unit info recorded</div>`;
                    }

                    breakdownRowsHtml += `
                        <div style="border: 1px solid ${borderCol}; border-radius:6px; margin-bottom:8px; overflow:hidden;">
                            <!-- Payment Header -->
                            <div style="display:flex; justify-content:space-between; align-items:center; padding: 8px 12px; background:${headerBg}; border-bottom:1px solid ${borderCol};">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="background:${tagBg}; color:#fff; font-size:10px; font-weight:700; padding:2px 6px; border-radius:3px; letter-spacing:0.5px;">${tag}</span>
                                    <span style="font-weight:700; font-size:14px; color:${headerColor};">${numLabel}${invLabel}</span>
                                </div>
                                <span style="font-weight:700; font-size:15px; color:${headerColor};">₱${formattedAmt}</span>
                            </div>
                            <!-- Payment Method Row -->
                            <div style="display:flex; justify-content:space-between; padding: 5px 12px; background:#fff; font-size:12px; color:#666; border-bottom:1px solid #f0f0f0;">
                                <span>Payment Method:</span>
                                <span style="font-weight:600; color:#333;">${typeName}</span>
                            </div>
                            <!-- Order Breakdown label -->
                            <div style="padding: 5px 12px 2px 12px; font-size:12px; font-weight:600; color:#888; background:#fafafa; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #f0f0f0;">
                                Order Breakdown
                            </div>
                            <!-- Unit rows -->
                            <div style="background:#fafafa; padding: 4px 0 6px 0;">
                                ${unitRowsHtml}
                            </div>
                        </div>
                    `;
                });
            }

            const banner = document.getElementById('paymentBreakdownBanner');
            if (banner) {
                if (breakdownRowsHtml) {
                    banner.innerHTML = `
                        <div style="font-weight:700; font-size:13px; color:#555; margin-bottom:10px; text-transform:uppercase; letter-spacing:0.6px; display:flex; align-items:center; gap:8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect>
                                <line x1="8" y1="12" x2="16" y2="12"></line>
                                <line x1="8" y1="8" x2="16" y2="8"></line>
                                <line x1="8" y1="16" x2="12" y2="16"></line>
                            </svg>
                            Payment History Breakdown
                        </div>
                        ${breakdownRowsHtml}
                    `;
                    banner.style.display = 'block';
                } else {
                    banner.style.display = 'none';
                    banner.innerHTML = '';
                }
            }

            // Calculate and display total payment
            calculateGlobalTotal();
            updateAddPaymentButtonLabel();
            console.log('=== END POPULATE PAYMENT MODAL ===');
        }

        function calculateGlobalTotal() {
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
                const secs = document.querySelectorAll(secClass);
                secs.forEach(sec => {
                    if (sec && sec.style.display === 'block') {
                        const inputs = sec.querySelectorAll('input[type="text"], input[type="number"]');
                        inputs.forEach(input => {
                            let isAmount = false;
                            if (input.classList.contains('amount-input')) isAmount = true;
                            if (input.id && input.id.toLowerCase().includes('amount')) isAmount = true;
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
                                let val = parseFloat(input.value.replace(/,/g, '')) || 0;
                                globalTotal += val;
                            }
                        });
                    }
                });
            });

            const globalTotalInput = document.getElementById('globalTotalInput');
            if (globalTotalInput) {
                globalTotalInput.value = globalTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            const globalTotalDueInput = document.getElementById('globalTotalDueInput');
            if (globalTotalDueInput && _originalTotalAmountDue) {
                globalTotalDueInput.value = _originalTotalAmountDue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        // Toggle Down Payment Reference fields inside each active payment method block
        function toggleDownPaymentReference() {
            const blocks = document.querySelectorAll('.payment-block, #singlePaymentSection');
            blocks.forEach(block => {
                const cashCb = block.querySelector('input[name="down_payment_method"][value="cash"]');
                const gcashCb = block.querySelector('input[name="down_payment_method"][value="gcash"]');
                const mayaCb = block.querySelector('input[name="down_payment_method"][value="maya"]');

                const cashRow = block.querySelector('#dpCashRow');
                const gcashRow = block.querySelector('#dpGcashRow');
                const mayaRow = block.querySelector('#dpMayaRow');

                if (cashCb && cashCb.checked) {
                    if (cashRow) cashRow.style.display = 'flex';
                } else if (cashRow) {
                    cashRow.style.display = 'none';
                    const cInput = block.querySelector('#cash_down_payment_amount');
                    if (cInput) cInput.value = '';
                }

                if (gcashCb && gcashCb.checked) {
                    if (gcashRow) gcashRow.style.display = 'flex';
                } else if (gcashRow) {
                    gcashRow.style.display = 'none';
                    const gRef = block.querySelector('#gcash_down_payment_reference');
                    const gInput = block.querySelector('#gcash_down_payment_amount');
                    if (gRef) gRef.value = '';
                    if (gInput) gInput.value = '';
                }

                if (mayaCb && mayaCb.checked) {
                    if (mayaRow) mayaRow.style.display = 'flex';
                } else if (mayaRow) {
                    mayaRow.style.display = 'none';
                    const mRef = block.querySelector('#maya_down_payment_reference');
                    const mInput = block.querySelector('#maya_down_payment_amount');
                    if (mRef) mRef.value = '';
                    if (mInput) mInput.value = '';
                }
            });
        }

        // Format input for currency
        function formatInput(input) {
            // Store cursor position
            let cursorPosition = input.selectionStart;
            let value = input.value.replace(/,/g, '');

            // If empty or just being cleared, don't format
            if (value === '' || value === '.') {
                return;
            }

            // Only format on blur or when complete
            // For now, just allow typing without auto-formatting
            if (!isNaN(value) && value !== '') {
                // Remove any existing formatting
                let numValue = parseFloat(value);
                if (!isNaN(numValue)) {
                    // Only format if value is complete (not actively typing)
                    // We'll add onblur event for final formatting
                }
            }
        }

        // Add blur event listener for all amount inputs to format on leaving the field
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.amount-input').forEach(function (input) {
                input.addEventListener('blur', function () {
                    let value = this.value.replace(/,/g, '');
                    if (!isNaN(value) && value !== '') {
                        this.value = parseFloat(value).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    } else if (value === '' || value === '0') {
                        this.value = '';
                    }
                });

                input.addEventListener('focus', function () {
                    // Remove formatting when focused for easier editing
                    let value = this.value.replace(/,/g, '');
                    if (!isNaN(value) && value !== '') {
                        this.value = value;
                    }
                });
            });
        });

        // Terminal IDs dataset from PHP
        const allTerminalIds = <?php echo json_encode($terminal_ids_for_js); ?>;

        // Bank-Terms mapping from item_prices table
        const bankTermsMap = <?php echo json_encode($bank_terms_map); ?>;
        console.log('Bank-Terms mapping:', bankTermsMap);

        // Filter Terminal IDs based on Terminal Issuer
        function filterTerminalIds(section) {
            console.log('filterTerminalIds called with section:', section);
            console.log('allTerminalIds:', allTerminalIds);

            const el = window.event ? window.event.target : null;
            const block = el ? el.closest('.payment-block, #singlePaymentSection') : document;

            const issuerSelect = block.querySelector(section === 'cc' ? '#ccTerminalIssuer' : '#dcTerminalIssuer');
            const terminalSelect = block.querySelector(section === 'cc' ? '#ccTerminalId' : '#dcTerminalId');

            console.log('issuerSelect:', issuerSelect);
            console.log('terminalSelect:', terminalSelect);

            if (!issuerSelect || !terminalSelect) {
                console.error('Could not find issuer or terminal select elements');
                return;
            }

            const selectedIssuer = issuerSelect.value;
            console.log('selectedIssuer:', selectedIssuer);

            terminalSelect.innerHTML = '<option value="">Select Terminal ID</option>';

            if (section === 'cc') {
                const bankDropdown = block.querySelector('#creditCardBankDropdown');
                const termsDropdown = block.querySelector('#creditCardTermsDropdown');
                if (bankDropdown) bankDropdown.innerHTML = '<option value="">Select Bank</option>';
                if (termsDropdown) termsDropdown.innerHTML = '<option value="">Select Terms</option>';
            }
            if (section === 'dc') {
                const bankDropdown = block.querySelector('#debitCardBankDropdown');
                const termsDropdown = block.querySelector('#debitCardTermsDropdown');
                if (bankDropdown) bankDropdown.innerHTML = '<option value="">Select Bank</option>';
                if (termsDropdown) termsDropdown.innerHTML = '<option value="">Select Terms</option>';
            }

            if (!selectedIssuer) {
                console.log('No issuer selected, returning');
                return;
            }

            const filtered = allTerminalIds.filter(tid => {
                const issuers = tid.terminal_issuer.split(',').map(s => s.trim());
                return issuers.some(iss => iss === selectedIssuer || iss.startsWith(selectedIssuer));
            });

            console.log('filtered terminal IDs:', filtered);

            filtered.forEach(tid => {
                const opt = document.createElement('option');
                opt.value = tid.terminal_id;
                opt.textContent = tid.terminal_id;
                opt.setAttribute('data-issuers', tid.terminal_issuer);
                terminalSelect.appendChild(opt);
            });

            console.log('Terminal IDs populated. Total options:', terminalSelect.options.length);
        }

        /**
         * Populate the Bank dropdown with banks from item_prices and others_bank tables
         * @param {string} section - 'cc' or 'dc'
         * @param {string} preserveBank - Optional: preserve this bank value after populating
         * @param {string} preserveTerms - Optional: preserve this terms value after populating
         */
        function filterBanksByTerminalId(section, preserveBank, preserveTerms) {
            console.log('filterBanksByTerminalId called with section:', section, 'preserveBank:', preserveBank, 'preserveTerms:', preserveTerms);

            const isCC = (section === 'cc');
            const bankDropdown = document.getElementById(isCC ? 'creditCardBankDropdown' : 'debitCardBankDropdown');
            const termsDropdown = document.getElementById(isCC ? 'creditCardTermsDropdown' : 'debitCardTermsDropdown');
            const amountInput = document.getElementById(isCC ? 'creditCardAmount' : 'debitCardAmount');

            console.log('bankDropdown:', bankDropdown);

            if (!bankDropdown) {
                console.error('Bank dropdown not found');
                return;
            }

            // Save current values if not provided
            if (!preserveBank && bankDropdown.value) {
                preserveBank = bankDropdown.value;
            }
            if (!preserveTerms && termsDropdown && termsDropdown.value) {
                preserveTerms = termsDropdown.value;
            }

            // Clear existing options
            bankDropdown.innerHTML = '<option value="">Select Bank</option>';
            if (termsDropdown) termsDropdown.innerHTML = '<option value="">Select Terms</option>';
            // Don't clear amount here - let it be preserved

            const banks = new Set();
            let othersBankEnabledForOrder = false;

            if (Array.isArray(itemsArray) && itemsArray.length > 0) {
                itemsArray.forEach(item => {
                    if (item.others_bank_enabled) {
                        othersBankEnabledForOrder = true;
                    }
                    if (item.prices) {
                        for (const key in item.prices) {
                            const spaceIndex = key.indexOf(' ');
                            if (spaceIndex !== -1) {
                                const bankName = key.substring(0, spaceIndex);
                                if (bankName !== 'Others') {
                                    banks.add(bankName);
                                }
                            }
                        }
                    }
                });
            }

            // Add banks from others_bank table ONLY if Others Bank is enabled for item(s) in order
            if (othersBankEnabledForOrder) {
                <?php
                if ($others_bank_result && $others_bank_result->num_rows > 0) {
                    echo "const otherBanks = [";
                    $others_bank_result->data_seek(0);
                    $bank_list = [];
                    while ($ob_row = $others_bank_result->fetch_assoc()) {
                        $bank_list[] = "'" . addslashes($ob_row['bank_name']) . "'";
                    }
                    echo implode(", ", $bank_list);
                    echo "];\n";
                    echo "            console.log('Banks from others_bank:', otherBanks);\n";
                    echo "            otherBanks.forEach(bank => banks.add(bank));";
                }
                ?>
            }

            // Sort banks alphabetically
            const sortedBanks = Array.from(banks).sort();
            console.log('All banks sorted:', sortedBanks);

            sortedBanks.forEach(bank => {
                const option = document.createElement('option');
                option.value = bank;
                option.textContent = bank;
                bankDropdown.appendChild(option);
            });

            console.log('Bank dropdown populated. Total options:', bankDropdown.options.length);

            // Restore preserved bank value
            // Restore preserved bank value
            if (preserveBank) {
                console.log('Restoring bank value:', preserveBank);
                bankDropdown.value = preserveBank;

                // If bank was restored, populate terms from database
                if (bankDropdown.value === preserveBank && termsDropdown) {
                    const otherBanksList = [<?php
                    if ($others_bank_result && $others_bank_result->num_rows > 0) {
                        $others_bank_result->data_seek(0);
                        $bank_list = [];
                        while ($ob_row = $others_bank_result->fetch_assoc()) {
                            $bank_list[] = "'" . addslashes($ob_row['bank_name']) . "'";
                        }
                        echo implode(", ", $bank_list);
                    }
                    ?>];
                    const isOtherBank = otherBanksList.includes(preserveBank);

                    let termsForBank = [];
                    if (isOtherBank) {
                        termsForBank = ['3 Months', '6 Months', '12 Months', '24 Months'];
                    } else if (bankTermsMap[preserveBank] && Array.isArray(bankTermsMap[preserveBank])) {
                        termsForBank = bankTermsMap[preserveBank];
                    } else {
                        termsForBank = ['Straight', '3 Months', '6 Months', '12 Months', '24 Months'];
                    }

                    termsForBank.forEach(term => {
                        const option = document.createElement('option');
                        option.value = term;
                        option.textContent = term;
                        if (isOtherBank) {
                            option.setAttribute('data-is-other-bank', 'true');
                        }
                        termsDropdown.appendChild(option);
                    });

                    // Restore preserved terms value
                    if (preserveTerms) {
                        console.log('Restoring terms value:', preserveTerms);
                        termsDropdown.value = preserveTerms;
                    }
                }
            }

            // Add event listener to Bank dropdown to populate Terms (only if not already added)
            if (!bankDropdown.hasAttribute('data-listener-added')) {
                setupBankChangeListener(section, bankDropdown, termsDropdown, amountInput);
                bankDropdown.setAttribute('data-listener-added', 'true');
            }
        }

        /**
         * Set up Bank dropdown change listener to populate Terms
         * @param {string} section - 'cc' or 'dc'
         * @param {HTMLElement} bankDropdown - Bank dropdown element
         * @param {HTMLElement} termsDropdown - Terms dropdown element
         * @param {HTMLElement} amountInput - Amount input element
         */
        function setupBankChangeListener(section, bankDropdown, termsDropdown, amountInput) {
            if (!bankDropdown) return;

            const otherBanksList = [<?php
            if ($others_bank_result && $others_bank_result->num_rows > 0) {
                $others_bank_result->data_seek(0);
                $bank_list = [];
                while ($ob_row = $others_bank_result->fetch_assoc()) {
                    $bank_list[] = "'" . addslashes($ob_row['bank_name']) . "'";
                }
                echo implode(", ", $bank_list);
            }
            ?>];

            bankDropdown.addEventListener('change', function () {
                const selectedBank = this.value;

                if (termsDropdown) termsDropdown.innerHTML = '<option value="">Select Terms</option>';
                if (amountInput) {
                    amountInput.value = '';
                    amountInput.removeAttribute('readonly');
                    amountInput.placeholder = 'Enter amount';
                    amountInput.dispatchEvent(new Event('input'));
                }

                if (selectedBank) {
                    const isOtherBank = otherBanksList.includes(selectedBank);

                    if (isOtherBank) {
                        const standardTerms = ['3 Months', '6 Months', '12 Months', '24 Months'];
                        standardTerms.forEach(term => {
                            const option = document.createElement('option');
                            option.value = term;
                            option.textContent = term;
                            option.setAttribute('data-is-other-bank', 'true');
                            termsDropdown.appendChild(option);
                        });
                        if (amountInput) {
                            amountInput.removeAttribute('readonly');
                            amountInput.placeholder = 'Enter amount';
                        }
                    } else {
                        // Regular bank
                        let termsSet = new Set();
                        if (Array.isArray(itemsArray) && itemsArray.length > 0) {
                            itemsArray.forEach(item => {
                                if (item.prices) {
                                    for (const key in item.prices) {
                                        if (key.startsWith(selectedBank + ' ')) {
                                            const term = key.substring(selectedBank.length + 1);
                                            termsSet.add(term);
                                        }
                                    }
                                }
                            });
                        }
                        let termsForBank = Array.from(termsSet);
                        if (termsForBank.length === 0 && bankTermsMap[selectedBank] && Array.isArray(bankTermsMap[selectedBank])) {
                            termsForBank = bankTermsMap[selectedBank];
                        }
                        if (termsForBank.length === 0) {
                            termsForBank = ['Straight', '3 Months', '6 Months', '12 Months', '24 Months'];
                        }

                        termsForBank.forEach(term => {
                            const option = document.createElement('option');
                            option.value = term;
                            option.textContent = term;
                            const fullKey = selectedBank + ' ' + term;
                            option.setAttribute('data-full-key', fullKey);
                            termsDropdown.appendChild(option);
                        });
                    }
                }
            });

            // Add Terms dropdown change listener
            if (termsDropdown && amountInput) {
                termsDropdown.addEventListener('change', function () {
                    const selectedOption = this.options[this.selectedIndex];
                    const isOtherBank = selectedOption ? (selectedOption.getAttribute('data-is-other-bank') === 'true' || otherBanksList.includes(bankDropdown.value)) : false;
                    const fullKey = selectedOption ? selectedOption.getAttribute('data-full-key') : null;

                    if (isOtherBank) {
                        // For Other Banks: Keep amount empty and editable for manual input
                        amountInput.value = '';
                        amountInput.removeAttribute('readonly');
                        amountInput.placeholder = 'Enter amount';
                        amountInput.focus();
                        amountInput.dispatchEvent(new Event('input'));
                    } else if (this.value) {
                        // For regular banks: check if fixed price exists in itemsArray
                        let fixedPrice = null;
                        if (fullKey && Array.isArray(itemsArray) && itemsArray.length > 0) {
                            itemsArray.forEach(item => {
                                if (item.prices && item.prices[fullKey] !== undefined) {
                                    fixedPrice = item.prices[fullKey];
                                }
                            });
                        }

                        if (fixedPrice !== null && isFinite(fixedPrice) && parseFloat(fixedPrice) > 0) {
                            amountInput.value = formatNumber(fixedPrice);
                            amountInput.setAttribute('readonly', 'readonly');
                        } else {
                            // If no fixed price for regular bank, auto-fill total amount or keep editable
                            const totalAmountField = document.getElementById('totalAmount');
                            if (totalAmountField && totalAmountField.value) {
                                amountInput.value = totalAmountField.value;
                            } else {
                                amountInput.value = '';
                            }
                            amountInput.removeAttribute('readonly');
                            amountInput.placeholder = 'Enter amount';
                        }
                        amountInput.dispatchEvent(new Event('input'));
                    }
                });
            }
        }

        // Collect payment details from a specific payment method block/form
        function collectDataFromBlock(block) {
            const data = {};
            let sectionName = '';
            let hasValues = false;

            const paymentPartnersDropdown = block.querySelector('#paymentPartnersDropdown');

            function collectSectionData(sectionClass, type) {
                const section = block.querySelector(sectionClass);
                if (section && section.style.display === 'block') {
                    let displayType = type;
                    if (type === 'Home Credit' && paymentPartnersDropdown && paymentPartnersDropdown.value !== '') {
                        displayType = paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text;
                    }

                    if (sectionName) {
                        sectionName += ' + ' + displayType;
                    } else {
                        sectionName = displayType;
                    }
                    data.payment_type = sectionName;

                    const inputs = section.querySelectorAll('input, select');
                    inputs.forEach(input => {
                        if (input.type === 'hidden') return;

                        let key = input.id;
                        if (!key) {
                            const formGroup = input.closest('.hc-form-group');
                            if (formGroup) {
                                const label = formGroup.querySelector('label');
                                if (label) key = label.innerText.replace(':', '').trim();
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
                        key = mapPaymentFieldKey(key);
                        if (!key || key === 'total-input') return;

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

                    // Capture Total from block's section total-input or global calculation
                    const totalInput = section.querySelector('.total-input');
                    if (totalInput) {
                        data['Total'] = totalInput.value;
                    } else {
                        const amtInput = Array.from(inputs).find(inp => inp.id && inp.id.toLowerCase().includes('amount'));
                        if (amtInput) data['Total'] = amtInput.value;
                    }

                    // Keep Home Credit / payment-partner fields in the same keys salesentry.php uses
                    if (section.classList.contains('home-credit-section')) {
                        const hcSelects = section.querySelectorAll('select.hc-input');
                        const loanTypeSelect = section.querySelector('#loanTypeDropdown') || hcSelects[0];
                        const loanTermsSelect = hcSelects[1];
                        if (loanTypeSelect) {
                            data['Loan Type'] = loanTypeSelect.value;
                            delete data.loanTypeDropdown;
                        }
                        if (loanTermsSelect) {
                            data['Loan Terms'] = loanTermsSelect.value;
                        }
                        if (paymentPartnersDropdown && paymentPartnersDropdown.selectedIndex > 0) {
                            data.payment_partner = paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text;
                        }
                        if (data.cash_down_payment_amount && !data.cash_dp_amount) {
                            data.cash_dp_amount = data.cash_down_payment_amount;
                        }
                        if (data.gcash_down_payment_amount && !data.gcash_dp_amount) {
                            data.gcash_dp_amount = data.gcash_down_payment_amount;
                        }
                        if (data.maya_down_payment_amount && !data.maya_dp_amount) {
                            data.maya_dp_amount = data.maya_down_payment_amount;
                        }
                        const computedTotal = parseAmount(data['Loan Balance']) +
                            parseAmount(data.cash_down_payment_amount) +
                            parseAmount(data.gcash_down_payment_amount) +
                            parseAmount(data.maya_down_payment_amount);
                        if (computedTotal > 0) {
                            data['Total'] = computedTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    } else {
                        // Card / QR / E-Wallet / Online Banking / Cash — normalize Amount + Total
                        if (data.creditCardAmount && !data.Amount) data.Amount = data.creditCardAmount;
                        if (data.debitCardAmount && !data.Amount) data.Amount = data.debitCardAmount;
                        delete data.creditCardAmount;
                        delete data.debitCardAmount;

                        const amountVal = parseAmount(data.Amount || data.amount || data.Total || 0);
                        if (amountVal > 0) {
                            const formatted = amountVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            data.Amount = data.Amount || formatted;
                            data.Total = data.Total || formatted;
                            data.amount = data.amount || formatted;
                        }

                        // Prefer display text for selects used by report/invoice modal
                        const ewalletSelect = section.querySelector('select.hc-input');
                        if (section.classList.contains('ewallet-section') && ewalletSelect && ewalletSelect.selectedIndex > 0) {
                            data['E-Wallet'] = ewalletSelect.value;
                            data['E-Wallet-Text'] = ewalletSelect.options[ewalletSelect.selectedIndex].text;
                        }
                        if ((section.classList.contains('online-banking-section') ||
                            section.classList.contains('qr-ph-section') ||
                            section.classList.contains('starpay-qr-section')) && ewalletSelect && ewalletSelect.selectedIndex > 0) {
                            data['Bank'] = ewalletSelect.value;
                            data['Bank-Text'] = ewalletSelect.options[ewalletSelect.selectedIndex].text;
                        }
                    }
                }
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

            sections.forEach(sec => collectSectionData(sec.class, sec.name));

            if (data.Total) {
                data.amount = data.Total;
            }

            // Capture the editable invoice number and date from the block header
            const invoiceInput = block.querySelector('.payment-block-invoice-input');
            if (invoiceInput) {
                data.block_invoice_no = invoiceInput.value.trim();
            }
            const dateInput = block.querySelector('.payment-block-date-input');
            if (dateInput) {
                data.block_payment_date = dateInput.value.trim();
            }
            // Preserve stage label (PRE-ORDER / PRE-ORDER 2 / CLAIM PRE-ORDER)
            if (block._paymentStageMeta) {
                if (block._paymentStageMeta.stage_label) data.stage_label = block._paymentStageMeta.stage_label;
                if (block._paymentStageMeta.is_claim_stage != null) data.is_claim_stage = block._paymentStageMeta.is_claim_stage;
            } else {
                const hdr = block.querySelector('.payment-block-header');
                if (hdr) {
                    if (hdr.dataset.stageLabel) data.stage_label = hdr.dataset.stageLabel;
                    data.is_claim_stage = hdr.dataset.isClaim === '1' ? 1 : 0;
                }
            }

            return hasValues ? data : null;
        }

        // Validate a specific payment block/form
        function validateBlock(block) {
            const paymentPartnersDropdown = block.querySelector('#paymentPartnersDropdown');
            const cardPaymentDropdown = block.querySelector('#cardPaymentDropdown');
            const qrDropdown = block.querySelector('#qrDropdown');

            const homeCreditSection = block.querySelector('.home-credit-section');
            const creditCardSection = block.querySelector('.credit-card-section');
            const debitCardSection = block.querySelector('.debit-card-section');
            const qrPhSection = block.querySelector('.qr-ph-section');
            const starpayQrSection = block.querySelector('.starpay-qr-section');
            const ewalletSection = block.querySelector('.ewallet-section');
            const onlineBankingSection = block.querySelector('.online-banking-section');
            const cashSection = block.querySelector('.cash-section');

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
                return false;
            }

            if (homeCreditSection && homeCreditSection.style.display === 'block') {
                if (paymentPartnersDropdown && paymentPartnersDropdown.value === '') {
                    alert('Please choose a Payment Partner option in order to proceed!');
                    paymentPartnersDropdown.focus();
                    return false;
                }

                const selects = homeCreditSection.querySelectorAll('select.hc-input');
                const loanTypeSelectEl = selects[0];
                const loanTermsSelectEl = selects[1];

                const inputs = homeCreditSection.querySelectorAll('input.hc-input');
                const custNameInputEl = inputs[0];
                const loanNumInputEl = inputs[1];
                const loanBalInputEl = inputs[2];

                if (!loanTypeSelectEl || !loanTypeSelectEl.value || loanTypeSelectEl.value.trim() === '') {
                    alert('LOAN TYPE REQUIRED! Please select a loan type.');
                    if (loanTypeSelectEl) loanTypeSelectEl.focus();
                    return false;
                }

                if (!loanTermsSelectEl || !loanTermsSelectEl.value || loanTermsSelectEl.value.trim() === '') {
                    alert('LOAN TERMS REQUIRED! Please select loan terms.');
                    if (loanTermsSelectEl) loanTermsSelectEl.focus();
                    return false;
                }

                if (!custNameInputEl || !custNameInputEl.value || custNameInputEl.value.trim() === '') {
                    alert("CUSTOMER'S NAME REQUIRED! Please enter the customer's name.");
                    if (custNameInputEl) custNameInputEl.focus();
                    return false;
                }

                if (!loanNumInputEl || !loanNumInputEl.value || loanNumInputEl.value.trim() === '') {
                    alert('LOAN NUMBER REQUIRED! Please enter the loan number.');
                    if (loanNumInputEl) loanNumInputEl.focus();
                    return false;
                }

                if (!loanBalInputEl || !loanBalInputEl.value || loanBalInputEl.value.trim() === '') {
                    alert('LOAN BALANCE REQUIRED! Please enter the loan balance.');
                    if (loanBalInputEl) loanBalInputEl.focus();
                    return false;
                }

                const cashCheckbox = homeCreditSection.querySelector('input[name="down_payment_method"][value="cash"]');
                const gcashCheckbox = homeCreditSection.querySelector('input[name="down_payment_method"][value="gcash"]');
                const mayaCheckbox = homeCreditSection.querySelector('input[name="down_payment_method"][value="maya"]');

                const cashAmountInput = block.querySelector('#cash_down_payment_amount');
                const gcashRefInput = block.querySelector('#gcash_down_payment_reference');
                const gcashAmountInput = block.querySelector('#gcash_down_payment_amount');
                const mayaRefInput = block.querySelector('#maya_down_payment_reference');
                const mayaAmountInput = block.querySelector('#maya_down_payment_amount');

                const isAnyDownPaymentChecked = ((cashCheckbox && cashCheckbox.checked) || (gcashCheckbox && gcashCheckbox.checked) || (mayaCheckbox && mayaCheckbox.checked));
                if (!isAnyDownPaymentChecked) {
                    alert('DOWN PAYMENT METHOD REQUIRED! Please check at least one payment method (Cash, G-Cash, or Maya).');
                    return false;
                }

                if (cashCheckbox && cashCheckbox.checked) {
                    if (!cashAmountInput || !cashAmountInput.value || cashAmountInput.value.trim() === '') {
                        alert('ENTER AMOUNT REQUIRED! Please enter the Cash down payment amount.');
                        if (cashAmountInput) cashAmountInput.focus();
                        return false;
                    }
                }

                if (gcashCheckbox && gcashCheckbox.checked) {
                    if (!gcashRefInput || !gcashRefInput.value || gcashRefInput.value.trim() === '') {
                        alert('G-CASH REFERENCE NUMBER REQUIRED! Please enter the G-Cash reference number.');
                        if (gcashRefInput) gcashRefInput.focus();
                        return false;
                    }
                    if (!gcashAmountInput || !gcashAmountInput.value || gcashAmountInput.value.trim() === '') {
                        alert('ENTER AMOUNT REQUIRED! Please enter the G-Cash down payment amount.');
                        if (gcashAmountInput) gcashAmountInput.focus();
                        return false;
                    }
                }

                if (mayaCheckbox && mayaCheckbox.checked) {
                    if (!mayaRefInput || !mayaRefInput.value || mayaRefInput.value.trim() === '') {
                        alert('MAYA REFERENCE NUMBER REQUIRED! Please enter the Maya reference number.');
                        if (mayaRefInput) mayaRefInput.focus();
                        return false;
                    }
                    if (!mayaAmountInput || !mayaAmountInput.value || mayaAmountInput.value.trim() === '') {
                        alert('ENTER AMOUNT REQUIRED! Please enter the Maya down payment amount.');
                        if (mayaAmountInput) mayaAmountInput.focus();
                        return false;
                    }
                }
            }

            if ((creditCardSection && creditCardSection.style.display === 'block') || (debitCardSection && debitCardSection.style.display === 'block')) {
                if (cardPaymentDropdown && cardPaymentDropdown.value === '') {
                    alert('Please choose a Card Payment option in order to proceed!');
                    cardPaymentDropdown.focus();
                    return false;
                }
            }

            if ((qrPhSection && qrPhSection.style.display === 'block') || (starpayQrSection && starpayQrSection.style.display === 'block')) {
                if (qrDropdown && qrDropdown.value === '') {
                    alert('Please choose a QR option in order to proceed!');
                    qrDropdown.focus();
                    return false;
                }
            }

            if (ewalletSection && ewalletSection.style.display === 'block') {
                const selects = ewalletSection.querySelectorAll('select.hc-input');
                const ewalletSelect = selects[0];
                const inputs = ewalletSection.querySelectorAll('input.hc-input');
                const customerNameInput = inputs[0];
                const referenceNoInput = inputs[1];
                const amountInput = inputs[2];

                if (!ewalletSelect || !ewalletSelect.value || ewalletSelect.value.trim() === '') {
                    alert('E-WALLET REQUIRED! Please select an e-wallet.');
                    if (ewalletSelect) ewalletSelect.focus();
                    return false;
                }

                const selectedEwalletName = ewalletSelect.options[ewalletSelect.selectedIndex].text;

                if (!customerNameInput || !customerNameInput.value || customerNameInput.value.trim() === '') {
                    alert("CUSTOMER'S NAME REQUIRED! Please enter the customer's name.");
                    if (customerNameInput) customerNameInput.focus();
                    return false;
                }

                if (!referenceNoInput || !referenceNoInput.value || referenceNoInput.value.trim() === '') {
                    alert(selectedEwalletName.toUpperCase() + ' REFERENCE NUMBER REQUIRED! Please enter the ' + selectedEwalletName + ' reference number.');
                    if (referenceNoInput) referenceNoInput.focus();
                    return false;
                }

                if (!amountInput || !amountInput.value || amountInput.value.trim() === '') {
                    alert('AMOUNT REQUIRED! Please enter the payment amount.');
                    if (amountInput) amountInput.focus();
                    return false;
                }
            }

            if (onlineBankingSection && onlineBankingSection.style.display === 'block') {
                const selects = onlineBankingSection.querySelectorAll('select.hc-input');
                const bankSelect = selects[0];
                const inputs = onlineBankingSection.querySelectorAll('input.hc-input');
                const referenceNoInput = inputs[0];
                const amountInput = inputs[1];

                if (!bankSelect || !bankSelect.value || bankSelect.value.trim() === '') {
                    alert('BANK REQUIRED! Please select a bank.');
                    if (bankSelect) bankSelect.focus();
                    return false;
                }

                const selectedBankName = bankSelect.options[bankSelect.selectedIndex].text;

                if (!referenceNoInput || !referenceNoInput.value || referenceNoInput.value.trim() === '') {
                    alert(selectedBankName.toUpperCase() + ' REFERENCE NUMBER REQUIRED! Please enter the ' + selectedBankName + ' reference number.');
                    if (referenceNoInput) referenceNoInput.focus();
                    return false;
                }

                if (!amountInput || !amountInput.value || amountInput.value.trim() === '') {
                    alert('AMOUNT REQUIRED! Please enter the payment amount.');
                    if (amountInput) amountInput.focus();
                    return false;
                }
            }

            return true;
        }

        function savePaymentData(silent) {
            const multiContainer = document.getElementById('multiplePaymentSectionsContainer');
            const hiddenInput = document.getElementById('payment_data');
            const globalTotalInputCheck = document.getElementById('globalTotalInput');
            const overallTotalPayment = parseFloat(globalTotalInputCheck ? (globalTotalInputCheck.value || '0').replace(/,/g, '') : '0') || 0;
            const overallDifference = _originalTotalAmountDue - overallTotalPayment;

            if (multiContainer && multiContainer.style.display === 'block') {
                // Multiple Mode
                const blocks = multiContainer.querySelectorAll('.payment-block');
                let allValid = true;

                blocks.forEach(block => {
                    // Check validation inside this block
                    if (!validateBlock(block)) {
                        allValid = false;
                    }
                });

                if (!allValid) return false;

                const payments = [];
                blocks.forEach(block => {
                    const blockData = collectDataFromBlock(block);
                    if (blockData) {
                        payments.push(blockData);
                    }
                });

                if (payments.length === 0) {
                    alert('Please select a payment method.');
                    return false;
                }

                const result = {
                    payment_type: 'multiple',
                    payments: payments
                };

                if (hiddenInput) {
                    hiddenInput.value = JSON.stringify(result);
                }

                // Global payment mismatch check (skip on UPDATE — Home Credit loan+DP can differ from SRP)
                if (!silent && _originalTotalAmountDue > 0 && Math.abs(overallDifference) > 0.01) {
                    const bkBanner = document.getElementById('paymentBreakdownBanner');
                    if (bkBanner) bkBanner.style.display = 'none';

                    const neededDisp = _originalTotalAmountDue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    const enteredDisp = overallTotalPayment.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    const remainingBalance = overallDifference < 0 ? 0 : overallDifference;
                    const diffDisp = remainingBalance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

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
                                        <span style="font-style: italic; max-width: 350px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">- ${descText}</span>
                                        <span>₱${rowTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                    </div>
                                `;
                            }
                        }
                    });

                    if (unitRowsHtml) {
                        unitRowsHtml = `
                            <div style="margin-bottom: 6px;">
                                <div style="font-weight: 600; color: #1e40af; font-size: 13px;">Unit(s) To Pay:</div>
                                ${unitRowsHtml}
                            </div>
                        `;
                    }

                    let breakdownHtml = '<div style="margin: 8px 0; padding: 6px 0; border-top: 1px solid #fecaca; border-bottom: 1px solid #fecaca;">';
                    blocks.forEach(block => {
                        const blockHeader = block.querySelector('.payment-block-header h4');
                        const blockLabel = blockHeader ? blockHeader.textContent.trim() : 'Payment';
                        breakdownHtml += `
                            <div style="margin-bottom: 6px;">
                                <div style="font-weight: 600; color: #991b1b; font-size: 13px;">${blockLabel}:</div>
                            </div>
                        `;
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
                                        
                                        <div style="display: flex; justify-content: space-between; border-bottom: 1.5px solid #fca5a5; padding-bottom: 4px; margin-bottom: 2px;">
                                            <span style="font-weight: 600;">Total Amount Due:</span><span style="font-weight: 700;">₱${neededDisp}</span>
                                        </div>
                                        
                                        ${breakdownHtml}
                                        
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>Total Entered:</span><span style="font-weight: 600;">₱${enteredDisp}</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; margin-top: 2px; padding-top: 4px; border-top: 1.5px dashed #fca5a5;">
                                            <span style="color: #7f1d1d; font-weight: 600;">Remaining Balance:</span><span style="color: #ef4444; font-weight: 600;">₱${diffDisp}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        banner.style.display = 'block';
                        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    return;
                } else {
                    const banner = document.getElementById('paymentErrorBanner');
                    if (banner) { banner.style.display = 'none'; banner.innerHTML = ''; }
                }

                if (!silent) alert('Payment details saved successfully!');
                return true;
            } else {
                // Single Mode
                const singleSection = document.getElementById('singlePaymentSection');
                if (!validateBlock(singleSection)) return false;

                const blockData = collectDataFromBlock(singleSection);
                if (blockData) {
                    if (hiddenInput) {
                        hiddenInput.value = JSON.stringify(blockData);

                        // Global payment mismatch check (skip on UPDATE — Home Credit loan+DP can differ from SRP)
                        if (!silent && _originalTotalAmountDue > 0 && Math.abs(overallDifference) > 0.01) {
                            const bkBanner = document.getElementById('paymentBreakdownBanner');
                            if (bkBanner) bkBanner.style.display = 'none';

                            const neededDisp = _originalTotalAmountDue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const enteredDisp = overallTotalPayment.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const remainingBalance = overallDifference < 0 ? 0 : overallDifference;
                            const diffDisp = remainingBalance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

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
                                                <span style="font-style: italic; max-width: 350px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">- ${descText}</span>
                                                <span>₱${rowTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                            </div>
                                        `;
                                    }
                                }
                            });

                            if (unitRowsHtml) {
                                unitRowsHtml = `
                                    <div style="margin-bottom: 6px;">
                                        <div style="font-weight: 600; color: #1e40af; font-size: 13px;">Unit(s) To Pay:</div>
                                        ${unitRowsHtml}
                                    </div>
                                `;
                            }

                            let breakdownHtml = '<div style="margin: 8px 0; padding: 6px 0; border-top: 1px solid #fecaca; border-bottom: 1px solid #fecaca;">';
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
                                const secEl = singleSection.querySelector(sec.class);
                                if (secEl && secEl.style.display === 'block') {
                                    let displayName = sec.name;
                                    if (sec.name === 'Home Credit' && paymentPartnersDropdown && paymentPartnersDropdown.value !== '') {
                                        displayName = paymentPartnersDropdown.options[paymentPartnersDropdown.selectedIndex].text;
                                    }
                                    if (sec.name === 'E-Wallet' && blockData['E-Wallet-Text']) displayName = blockData['E-Wallet-Text'];
                                    if (sec.name === 'Online Banking' && blockData['Bank-Text']) displayName = blockData['Bank-Text'];

                                    breakdownHtml += `
                                        <div style="margin-bottom: 6px;">
                                            <div style="font-weight: 600; color: #991b1b; font-size: 13px;">${displayName}:</div>
                                        </div>
                                    `;
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
                                                
                                                <div style="display: flex; justify-content: space-between; border-bottom: 1.5px solid #fca5a5; padding-bottom: 4px; margin-bottom: 2px;">
                                                    <span style="font-weight: 600;">Total Amount Due:</span><span style="font-weight: 700;">₱${neededDisp}</span>
                                                </div>
                                                
                                                ${breakdownHtml}
                                                
                                                <div style="display: flex; justify-content: space-between;">
                                                    <span>Total Entered:</span><span style="font-weight: 600;">₱${enteredDisp}</span>
                                                </div>
                                                <div style="display: flex; justify-content: space-between; margin-top: 2px; padding-top: 4px; border-top: 1.5px dashed #fca5a5;">
                                                    <span style="color: #7f1d1d; font-weight: 600;">Remaining Balance:</span><span style="color: #ef4444; font-weight: 600;">₱${diffDisp}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                banner.style.display = 'block';
                                banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            }
                            return;
                        } else {
                            const banner = document.getElementById('paymentErrorBanner');
                            if (banner) { banner.style.display = 'none'; banner.innerHTML = ''; }
                        }

                        if (!silent) alert('Payment details saved successfully!');
                        return true;
                    } else {
                        console.error('Hidden input #payment_data not found!');
                        return false;
                    }
                } else {
                    alert('Please select a payment method.');
                    return false;
                }
            }
        }

        // Global helper: update Loan Type dropdown options based on selected payment partner
        function updateLoanTypeOptions(partnerName, loanTypeDropdown) {
            const currentValue = loanTypeDropdown.value; // Save current selection
            loanTypeDropdown.innerHTML = '<option value=""></option>'; // Clear and add empty option

            if (partnerName.includes('Home Credit')) {
                // Home Credit: 0% Installment, Standard Loan, Retailer Zero, Saver Plan
                loanTypeDropdown.innerHTML += '<option value="0_installment">0% Installment</option>';
                loanTypeDropdown.innerHTML += '<option value="standard_loan">Standard Loan</option>';
                loanTypeDropdown.innerHTML += '<option value="retailer_zero">Retailer Zero</option>';
                loanTypeDropdown.innerHTML += '<option value="saver_plan">Saver Plan</option>';
            } else if (partnerName.includes('Salmon') || partnerName.includes('Samsung Finances') ||
                partnerName.includes('Payjoy') || partnerName.includes('Billease') ||
                partnerName.includes('Paymongo') || partnerName.includes('Skyro')) {
                // All other partners: Only Standard Loan
                loanTypeDropdown.innerHTML += '<option value="standard_loan">Standard Loan</option>';
            }

            // Restore previous value if it exists in new options
            if (currentValue) {
                setSelectByValueOrText(loanTypeDropdown, currentValue);
            }
        }

        // Handle Payment Method Checkboxes
        document.addEventListener('DOMContentLoaded', function () {
            // Set up Terminal ID change event listeners
            const ccTerminalId = document.getElementById('ccTerminalId');
            const dcTerminalId = document.getElementById('dcTerminalId');

            if (ccTerminalId) {
                ccTerminalId.addEventListener('change', function () {
                    filterBanksByTerminalId('cc');
                });

                // Auto-populate banks if Terminal ID already has a value (from loaded payment data)
                setTimeout(function () {
                    if (ccTerminalId.value && ccTerminalId.value !== '') {
                        console.log('Credit Card Terminal ID already has value:', ccTerminalId.value);
                        filterBanksByTerminalId('cc');
                    }
                }, 500);
            }

            if (dcTerminalId) {
                dcTerminalId.addEventListener('change', function () {
                    filterBanksByTerminalId('dc');
                });

                // Auto-populate banks if Terminal ID already has a value (from loaded payment data)
                setTimeout(function () {
                    if (dcTerminalId.value && dcTerminalId.value !== '') {
                        console.log('Debit Card Terminal ID already has value:', dcTerminalId.value);
                        filterBanksByTerminalId('dc');
                    }
                }, 500);
            }

            // Save Payment Data Button
            const saveBtn = document.querySelector('.btn-save-modal');
            if (saveBtn) {
                saveBtn.addEventListener('click', savePaymentData);
            }

            // Payment Partners checkbox and dropdown
            const chkPaymentPartners = document.getElementById('chkPaymentPartners');
            const paymentPartnersDropdown = document.getElementById('paymentPartnersDropdown');
            const homeCreditSection = document.querySelector('.home-credit-section');

            if (chkPaymentPartners && paymentPartnersDropdown && homeCreditSection) {
                chkPaymentPartners.addEventListener('change', function () {
                    if (this.checked) {
                        homeCreditSection.style.display = 'block';
                    } else {
                        homeCreditSection.style.display = 'none';
                    }
                });

                paymentPartnersDropdown.addEventListener('change', function () {
                    const titleElem = document.getElementById('paymentPartnerTitle');
                    const loanTypeDropdown = document.getElementById('loanTypeDropdown');
                    const selectedText = this.options[this.selectedIndex].text;

                    if (titleElem && this.value) {
                        titleElem.textContent = selectedText;
                    }

                    // Update Loan Type dropdown based on selected partner
                    if (loanTypeDropdown) {
                        updateLoanTypeOptions(selectedText, loanTypeDropdown);
                    }
                });
            }

            // Card Payment checkbox and dropdown
            const chkCardPayment = document.getElementById('chkCardPayment');
            const cardPaymentDropdown = document.getElementById('cardPaymentDropdown');
            const creditCardSection = document.querySelector('.credit-card-section');
            const debitCardSection = document.querySelector('.debit-card-section');

            if (chkCardPayment) {
                chkCardPayment.addEventListener('change', function () {
                    if (this.checked) {
                        let val = cardPaymentDropdown ? cardPaymentDropdown.value : '';
                        // Auto-select first real option if none chosen yet
                        if (!val && cardPaymentDropdown) {
                            cardPaymentDropdown.value = 'credit_card';
                            val = 'credit_card';
                        }
                        if (creditCardSection) creditCardSection.style.display = (val === 'credit_card') ? 'block' : 'none';
                        if (debitCardSection) debitCardSection.style.display = (val === 'debit_card') ? 'block' : 'none';
                    } else {
                        if (creditCardSection) creditCardSection.style.display = 'none';
                        if (debitCardSection) debitCardSection.style.display = 'none';
                        if (cardPaymentDropdown) cardPaymentDropdown.value = '';
                    }
                });
            }

            if (cardPaymentDropdown) {
                cardPaymentDropdown.addEventListener('change', function () {
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

            // QR checkbox and dropdown
            const chkQR = document.getElementById('chkQR');
            const qrDropdown = document.getElementById('qrDropdown');
            const qrPhSection = document.querySelector('.qr-ph-section');
            const starpayQrSection = document.querySelector('.starpay-qr-section');

            if (chkQR) {
                chkQR.addEventListener('change', function () {
                    if (this.checked) {
                        let val = qrDropdown ? qrDropdown.value : '';
                        // Auto-select first real option if none chosen yet
                        if (!val && qrDropdown) {
                            qrDropdown.value = 'qr_ph';
                            val = 'qr_ph';
                        }
                        if (qrPhSection) qrPhSection.style.display = (val === 'qr_ph') ? 'block' : 'none';
                        if (starpayQrSection) starpayQrSection.style.display = (val === 'starpay_qr') ? 'block' : 'none';
                    } else {
                        if (qrPhSection) qrPhSection.style.display = 'none';
                        if (starpayQrSection) starpayQrSection.style.display = 'none';
                        if (qrDropdown) qrDropdown.value = '';
                    }
                });
            }

            if (qrDropdown) {
                qrDropdown.addEventListener('change', function () {
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

            // Cash checkbox
            const chkCash = document.querySelector('input[name="payment_method"][value="cash"]');
            const cashSection = document.querySelector('.cash-section');
            if (chkCash && cashSection) {
                chkCash.addEventListener('change', function () {
                    cashSection.style.display = this.checked ? 'block' : 'none';
                });
            }

            // Online Banking checkbox
            const chkOnlineBanking = document.querySelector('input[name="payment_method"][value="online_banking"]');
            const onlineBankingSection = document.querySelector('.online-banking-section');
            if (chkOnlineBanking && onlineBankingSection) {
                chkOnlineBanking.addEventListener('change', function () {
                    onlineBankingSection.style.display = this.checked ? 'block' : 'none';
                });
            }

            // E-Wallet checkbox
            const chkEwallet = document.querySelector('input[name="payment_method"][value="ewallet"]');
            const ewalletSection = document.querySelector('.ewallet-section');
            if (chkEwallet && ewalletSection) {
                chkEwallet.addEventListener('change', function () {
                    ewalletSection.style.display = this.checked ? 'block' : 'none';
                });
            }

            // Add observer or global listener for section toggles to immediately update sum
            document.body.addEventListener('change', function (e) {
                if (e.target.name === 'payment_method' || e.target.id === 'paymentPartnersDropdown' || e.target.id === 'cardPaymentDropdown' || e.target.id === 'qrDropdown') {
                    setTimeout(calculateGlobalTotal, 50);
                }
            });

            // Attach listeners to all identifiable amount inputs
            const allInputs = document.querySelectorAll('input[type="text"], input[type="number"]');

            allInputs.forEach(input => {
                // Skip if it is a total field itself
                if (input.classList.contains('total-input') || input.id === 'totalAmount' || input.id === 'totalQty' || input.id === 'discountField' || input.id === 'globalTotalInput' || input.id === 'globalTotalDueInput') return;

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
                    const handleInput = function () {
                        const section = input.closest('.home-credit-section, .credit-card-section, .debit-card-section, .qr-ph-section, .starpay-qr-section, .ewallet-section, .online-banking-section, .cash-section');
                        if (section) {
                            updateSectionTotal(section);
                        }
                        calculateGlobalTotal();
                    };
                    input.addEventListener('input', handleInput);
                    input.addEventListener('blur', handleInput);
                }
            });

            // Modal close on outside click
            window.onclick = function (event) {
                const paymentModal = document.getElementById('paymentModal');
                if (event.target == paymentModal) {
                    paymentModal.style.display = 'none';
                }
            };
        });

        // Auto-search if invoice number is provided in URL
        window.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const invoiceNo = urlParams.get('invoice');

            if (invoiceNo) {
                modifySale(invoiceNo);
            }

            // Add global keyboard shortcuts
            document.addEventListener('keydown', function (event) {
                // Ctrl/Cmd + S to save/update (prevent default browser save)
                if ((event.ctrlKey || event.metaKey) && event.key === 's') {
                    event.preventDefault();
                    const modSection = document.getElementById('modificationSection');
                    if (modSection && modSection.style.display !== 'none') {
                        updateSalesEntry();
                    }
                }

                // Escape key to close alerts
                if (event.key === 'Escape' || event.keyCode === 27) {
                    const alertBox = document.getElementById('alertBox');
                    if (alertBox && alertBox.style.display === 'block') {
                        alertBox.style.display = 'none';
                    }
                }
            });
        });
    </script>
</body>

</html>