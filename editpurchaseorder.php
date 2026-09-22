<?php
require_once 'session_check.php';
include 'config.php';

// Validate ID
$po_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($po_id <= 0) {
    header('Location: purchaseorder.php');
    exit;
}

// Determine back URL based on 'from' parameter
$from = isset($_GET['from']) ? $_GET['from'] : '';
$backUrl = 'purchaseorder.php'; // default
if ($from === 'purchaseorderreceive') {
    $backUrl = 'purchaseorderreceive.php';
} else {
    $backUrl = 'purchaseorder.php';
}

// Fetch PO header
$po_result = $conn->query("SELECT * FROM purchase_orders WHERE id = $po_id LIMIT 1");
if (!$po_result || $po_result->num_rows === 0) {
    header('Location: purchaseorder.php');
    exit;
}
$po = $po_result->fetch_assoc();

// Block editing of Completed / Declined / Canceled POs with a user-friendly message
$status = $po['status'] ?? 'Pending';
if (in_array(strtolower($status), ['completed', 'decline', 'declined', 'canceled'])) {
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Cannot Edit PO</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh;  background-color: #fffcf4ff; margin: 0; }
        .box { background: white; border-radius: 10px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); padding: 40px 48px; text-align: center; max-width: 420px; }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h2 { font-size: 18px; color: #222; margin-bottom: 10px; }
        p  { font-size: 14px; color: #666; margin-bottom: 24px; line-height: 1.6; }
        .btn { display: inline-block; padding: 10px 28px; background: #408140; color: white; border-radius: 5px; text-decoration: none; font-weight: 600; font-size: 14px; }
        .btn:hover { background: #2e6b2e; }
    </style></head><body>
    <div class="box">
        <div class="icon">??</div>
        <h2>Cannot Edit This Purchase Order</h2>
        <p>This Purchase Order (<strong><?php echo htmlspecialchars($po['po_number']); ?></strong>) has already been marked as <strong><?php echo htmlspecialchars($status); ?></strong> and can no longer be edited.</p>
        <a class="btn" href="viewpurchaseorder.php?id=<?php echo $po_id; ?>">? View Purchase Order</a>
    </div>
    </body></html>
    <?php
    exit;
}

// Fetch existing items grouped by family code with live allocation totals
$items_result = $conn->query("
    SELECT 
        MIN(poi.id) as id,
        poi.po_id,
        MIN(poi.item_no) as item_no,
        poi.family_code,
        MAX(poi.quantity) as quantity,
        MAX(poi.cost) as cost,
        MAX(poi.total) as total,
        COALESCE(alloc.total_allocated, 0) as allocated_quantity
    FROM purchase_order_items poi
    LEFT JOIN (
        SELECT 
            po_id,
            family_code,
            SUM(quantity) as total_allocated
        FROM purchase_order_allocations
        WHERE po_id = {$po_id}
        GROUP BY po_id, family_code
    ) alloc ON alloc.po_id = poi.po_id
        AND alloc.family_code COLLATE utf8mb4_general_ci = poi.family_code COLLATE utf8mb4_general_ci
    WHERE poi.po_id = {$po_id}
    AND COALESCE(poi.is_receive_added, 0) = 0
    GROUP BY poi.po_id, poi.family_code, alloc.total_allocated
    ORDER BY MIN(poi.item_no) ASC
");
$existing_items = [];
if ($items_result) {
    while ($row = $items_result->fetch_assoc()) {
        $order_qty = (int)($row['quantity'] ?? 0);
        $allocated_qty = (int)($row['allocated_quantity'] ?? 0);

        if ($allocated_qty > $order_qty) {
            $row['quantity'] = $allocated_qty;
        }

        $existing_items[] = $row;
    }
}

// Terms display helper
$terms_val = $po['terms'] ?? '30';
$due_date_val = !empty($po['payment_due_date']) ? date('Y-m-d', strtotime($po['payment_due_date'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
      <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Purchase Order � <?php echo htmlspecialchars($po['po_number']); ?></title>
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

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0ff;
            zoom: 77%;
        }

        /* --- Header --- */
        .header {
            position: fixed; top: 0; left: 0; right: 0; height: 60px;
            background-color: white; display: flex; align-items: center;
            padding: 0 20px; z-index: 1000; gap: 30px;
        }
        .header::after {
            content: ''; position: absolute; bottom: 0; left: 250px; right: 0;
            height: 1px; box-shadow: 0 2px 4px rgba(0,0,0,0.5); pointer-events: none;
        }
        .logo { margin-left: -20px; height: 50px; }
        .menu-btn {
            width: 24px; height: 22px; cursor: pointer; position: relative;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
        }
        .menu-btn span {
            display: block; width: 18px; height: 2px; background-color: #333;
            position: absolute; transition: all 0.3s ease;
        }
        .menu-btn span:nth-child(1) { top: 0; }
        .menu-btn span:nth-child(2) { top: 50%; transform: translateY(-50%); }
        .menu-btn span:nth-child(3) { bottom: 0; }
        .menu-btn.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); }
        .menu-btn.active span:nth-child(2) { opacity: 0; }
        .menu-btn.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }

        /* --- Sidebar --- */
        .sidebar {
            position: fixed; left: 0; top: 60px; width: 250px;
            height: calc(149.3vh - 60px); background-color: white;
            box-shadow: 2px 0 4px rgba(0,0,0,0.1); transition: transform 0.3s ease;
            overflow-y: auto; padding: 20px 0; z-index: 999;
        }
        .sidebar.hidden { transform: translateX(-100%); }
        .menu-item {
            padding: 12px 20px; display: flex; align-items: center; gap: 12px;
            color: #666; text-decoration: none; cursor: pointer;
            transition: background-color 0.2s; font-size: 14px;
        }
        .menu-item:hover { background-color: #f5f5f5; }
        .menu-item.active { background-color: #e3fdeeff; color: #0e7725ff; font-weight: bold; }
        .menu-item svg { width: 20px; height: 20px; fill: currentColor; }
        .menu-section-title {
            padding: 12px 20px; display: flex; align-items: center;
            justify-content: space-between; gap: 12px; color: #666;
            cursor: pointer; font-size: 14px; font-weight: 500;
        }
        .menu-section-title svg { width: 20px; height: 20px; fill: currentColor; }
        .menu-section-title .arrow { transition: transform 0.3s ease; }
        .menu-section.collapsed .arrow { transform: rotate(-90deg); }
        .submenu { padding-left: 20px; max-height: 500px; overflow: hidden; transition: max-height 0.3s ease; }
        .menu-section.collapsed .submenu { max-height: 0; }
        .submenu .menu-item { padding: 10px 20px; font-size: 13px; }

        /* --- Main Content --- */
        .main-content {
            margin-left: 250px; margin-top: 60px; padding: 20px;
            transition: margin-left 0.3s ease;
        }
        .main-content.expanded { margin-left: 0; }

        /* --- Page Header --- */
        .page-header {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 20px;
        }
        .back-btn {
            display: flex; align-items: center; justify-content: center;
            background: none; border: none; cursor: pointer; padding: 4px;
            color: #333;
        }
        .back-btn svg { width: 20px; height: 20px; fill: #333; }
        .page-header h2 { font-size: 18px; font-weight: 600; color: #222; }

        /* --- Two-column layout --- */
        .create-po-layout {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 20px;
            align-items: start;
        }

        /* --- Information Card --- */
        .info-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            padding: 24px;
        }
        .info-card h3 {
            font-size: 15px; font-weight: 600; color: #222;
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full-width { grid-column: 1 / -1; }

        .form-group label {
            font-size: 13px; font-weight: 500; color: #444;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 9px 12px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: white;
            font-family: Arial, sans-serif;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { border-color: var(--color-gold); }
        .form-group input[readonly] { background: #f9f9f9; color: #888; cursor: default; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group input::placeholder,
        .form-group textarea::placeholder { color: #bbb; }

        /* --- Custom Brand Select Dropdown --- */
        .custom-brand-select {
            position: relative;
            width: 100%;
        }
        .brand-select-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: border-color 0.2s;
            font-size: 13px;
            color: #333;
            min-height: 36px;
            box-sizing: border-box;
        }
        .brand-select-input:hover {
            border-color: #999;
        }
        .brand-select-input.active {
            border-color: var(--color-gold);
        }
        #brand-display {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #999;
        }
        #brand-display.has-selection {
            color: #333;
        }
        .brand-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
        }
        .brand-dropdown.show {
            display: block;
        }
        .brand-option {
            padding: 10px 12px;
            cursor: pointer;
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
        }
        .brand-option:last-child {
            border-bottom: none;
        }
        .brand-option:hover {
            background: #f5f5f5;
        }
        .brand-option .brand-text {
            flex: 1;
        }
        .brand-option.selected {
            background: var(--color-gold-pale);
            font-weight: 600;
        }
        .brand-checkbox {
            margin-right: 8px;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        /* --- Summary Card --- */
        .summary-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            padding: 20px;
            position: sticky;
            top: 80px;
        }
        .summary-card h3 {
            font-size: 14px; font-weight: 600; color: #222;
            margin-bottom: 16px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 13px;
            color: #444;
        }
        .summary-row .value { font-weight: 500; color: #222; }
        .summary-divider {
            border: none; border-top: 1px solid #e5e5e5;
            margin: 14px 0;
        }
        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            font-size: 14px;
            font-weight: 600;
            color: #111;
        }
        .summary-total .total-amount { font-size: 15px; }

        .btn-save-po {
            width: 100%;
            padding: 11px;
            background: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 10px;
            transition: background 0.2s;
        }
        .btn-save-po:hover { background: var(--color-gold-light); }

        .btn-cancel-po {
            width: 100%;
            padding: 10px;
            background: white;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-cancel-po:hover { background: #f5f5f5; }

        /* --- Item Section --- */
        .item-section {
            margin-top: 20px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            padding: 20px 24px;
            grid-column: 1 / -1;
        }
        .item-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .item-section-header h3 { font-size: 15px; font-weight: 600; color: #222; }

        .btn-add-item {
            padding: 8px 16px;
            background: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background 0.2s;
        }
        .btn-add-item:hover { background: var(--color-gold-light); }

        .item-empty {
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            padding: 50px 20px;
            text-align: center;
            color: #888;
            font-size: 13px;
            background: #fefefe;
        }

        .items-table-wrapper { overflow-x: auto; }
        .items-table {
            width: 100%; border-collapse: collapse; display: none;
        }
        .items-table thead { background: var(--color-gold-pale); }
        .items-table th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000000;
            border-top: 1px solid #ccc;
            border-bottom: 1px solid #ccc;
        }
        .items-table th:first-child { border-left: 1px solid #ccc; }
        .items-table th:last-child  { border-right: 1px solid #ccc; }
        .items-table td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border-bottom: 1px solid #ccc;
            text-align: center;
        }
        .items-table td:first-child { border-left: 1px solid #ccc; }
        .items-table td:last-child  { border-right: 1px solid #ccc; }
        .items-table tbody tr:hover { background: #fdf8f3; }
        .items-table td input,
        .items-table td select {
            border: 1px solid #d0d0d0; border-radius: 4px;
            padding: 6px 8px; font-size: 13px; width: 100%; text-align: center;
            outline: none; font-family: Arial, sans-serif; background: white;
        }
        .items-table td input::placeholder { color: #bbb; }
        .items-table td input:focus,
        .items-table td select:focus { border-color: var(--color-gold); }
        .btn-remove-row {
            background: #c62828; color: white; border: none;
            border-radius: 4px; padding: 5px 12px; font-size: 12px;
            font-weight: 500; cursor: pointer;
        }
        .btn-remove-row:hover { background: #b71c1c; }

        @media (max-width: 900px) {
            .create-po-layout { grid-template-columns: 1fr; }
            .summary-card { position: static; }
            .item-section { grid-column: 1; }
        }
        
        /* Comprehensive Responsive Styles */
        
        /* Large Desktop & Laptop (max-width: 1640px) */
        @media (max-width: 1640px) {
            .form-grid {
                gap: 12px;
            }
        }

        /* Medium Desktop (max-width: 1366px) */
        @media (max-width: 1366px) {
            .create-po-layout {
                grid-template-columns: 1fr;
            }

            .summary-card {
                position: static;
            }

            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* Tablet & Smaller Desktop (max-width: 1024px) */
        @media (max-width: 1024px) {
            .main-content {
                padding: 15px;
            }

            .page-header {
                flex-wrap: wrap;
                gap: 10px;
            }

            .form-grid,
            .form-row {
                grid-template-columns: 1fr;
            }

            .item-section-header {
                flex-direction: column;
                align-items: stretch !important;
                gap: 10px;
            }

            .btn-add-item {
                width: 100%;
                padding: 12px;
                font-size: 15px;
            }

            .item-search-row,
            .item-input-row {
                flex-direction: column;
                gap: 10px;
            }

            .item-search-row input,
            .item-input-row input {
                width: 100%;
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
                min-width: 640px;
            }

            .items-table th,
            .items-table td {
                font-size: 12px;
                padding: 10px 8px;
                white-space: nowrap;
            }
        }

        /* Small Tablet (max-width: 960px) */
        @media (max-width: 960px) {
            .items-table {
                zoom: 0.85;
            }

            input[type="text"],
            input[type="number"],
            input[type="date"],
            select,
            textarea {
                font-size: 16px !important;
                padding: 12px !important;
            }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .form-row { grid-template-columns: 1fr; }
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
                padding: 12px;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .page-header {
                flex-direction: row;
                align-items: center;
                gap: 12px;
            }

            .back-btn {
                flex-shrink: 0;
                padding: 8px;
                background: #f5f5f5;
                border-radius: 4px;
                border: 1px solid #ddd;
            }

            .back-btn svg {
                width: 20px;
                height: 20px;
            }

            .page-header h2 {
                font-size: 18px;
                flex: 1;
            }

            .header-actions {
                flex-direction: column;
                gap: 8px;
                width: 100%;
            }

            .btn-back,
            .btn-save-po {
                width: 100%;
                justify-content: center;
                padding: 12px 20px;
                font-size: 14px;
            }

            .item-section-header {
                flex-direction: column;
                align-items: stretch !important;
            }

            .btn-add-item {
                width: 100%;
                padding: 12px;
                font-size: 15px;
            }

            .form-section,
            .item-section,
            .summary-card {
                padding: 15px;
            }

            .form-section h3,
            .item-section h3 {
                font-size: 14px;
            }

            .item-search-row,
            .item-input-row {
                gap: 10px;
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

            .btn-delete-item {
                width: 20px;
                height: 20px;
                font-size: 10px;
            }

            .summary-card {
                position: static;
            }

            .summary-row input {
                width: 100%;
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

            .page-header h2 {
                font-size: 16px;
            }

            .btn-back,
            .btn-save-po {
                padding: 10px 16px;
                font-size: 13px;
            }

            .form-section,
            .item-section,
            .summary-card {
                padding: 12px;
            }

            .items-table {
                min-width: 800px;
                zoom: 0.7;
            }

            .items-table th,
            .items-table td {
                font-size: 10px;
                padding: 6px 4px;
            }

            .summary-row label {
                font-size: 12px;
            }

            .summary-row input {
                font-size: 13px;
            }
        }

        /* Searchable Select Styles */
        .select-wrapper {
            position: relative;
        }
        .select-search-input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: white;
            font-family: Arial, sans-serif;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .select-search-input:focus {
            border-color: var(--color-gold);
        }
        .select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #d0d0d0;
            border-top: none;
            border-radius: 0 0 4px 4px;
            z-index: 1000;
            display: none;
        }
        .select-dropdown.active {
            display: block;
        }
        .select-option {
            padding: 10px 12px;
            cursor: pointer;
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
        }
        .select-option:last-child {
            border-bottom: none;
        }
        .select-option:hover {
            background: #f5f5f5;
        }
        .select-option.selected {
            background: #e8e8e8;
            font-weight: 600;
        }
        .select-option.hidden {
            display: none;
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
            background-color: rgba(0,0,0,0.4); 
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: fadeIn 0.3s;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
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
            text-align: right;
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
            background-color: #2e7d32;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-select:hover {
            background-color: #1b5e20;
        }

        .btn-back-modal {
            background-color: #f5f5f5;
            color: #333;
            border: 1px solid #ccc;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-back-modal:hover {
            background-color: #e0e0e0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()">
            <span></span><span></span><span></span>
        </div>
       <!-- <img src="Icon/ZUHAUSE-LOGO.png" alt="ZUHAUSE LOGO" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">

        <!-- Page Header -->
        <div class="page-header">
            <button class="back-btn" onclick="window.location.href='<?php echo htmlspecialchars($backUrl); ?>'" title="Back">
                <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            </button>
            <h2>Edit Purchase Order</h2>
        </div>

        <!-- Two-column layout -->
        <div class="create-po-layout">

            <!-- LEFT: Information Card -->
            <div class="info-card">
                <h3>Information</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>P.O Number</label>
                        <input type="text" id="po_number" value="<?php echo htmlspecialchars($po['po_number']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>PO Date</label>
                        <input type="text" id="po_date" value="<?php echo !empty($po['po_date']) ? date('Y-m-d', strtotime($po['po_date'])) : ''; ?>" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Supplier Company Name: <span style="color: red;">*</span></label>
                        <select id="supplier_company" class="searchable-select" required>
                            <option value="">Select Company Name</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select Brand: <span style="color: red;">*</span></label>
                        <div class="custom-brand-select">
                            <div class="brand-select-input" onclick="toggleBrandDropdown()">
                                <span id="brand-display">Select Brand</span>
                                <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: #666;">
                                    <path d="M7 10l5 5 5-5z"/>
                                </svg>
                            </div>
                            <div class="brand-dropdown" id="brand-dropdown">
                                <!-- Multiple Brands Checkbox Option -->
                                <div style="padding: 8px; border-bottom: 1px solid #eee; background: #f9f9f9;">
                                    <label style="display: flex; align-items: center; cursor: pointer; font-weight: 600; margin: 0;">
                                        <input type="checkbox" id="multiple-brands-checkbox" onclick="toggleMultipleBrandsMode()" style="margin-right: 8px; width: 16px; height: 16px; cursor: pointer;">
                                        <span>Multiple Brands</span>
                                    </label>
                                </div>
                                <!-- Brand Options Container -->
                                <div id="brand-options-container" style="max-height: 200px; overflow-y: auto; padding: 8px;">
                                    <?php
                                    $brands_query = $conn->query("SELECT brand_name FROM brands WHERE status = 'Active' ORDER BY brand_name ASC");
                                    if ($brands_query) {
                                        while ($brand = $brands_query->fetch_assoc()) {
                                            $brand_name = htmlspecialchars($brand['brand_name']);
                                            echo '<div class="brand-option" data-brand="' . $brand_name . '" onclick="selectSingleBrand(\'' . $brand_name . '\')">
                                                    <input type="checkbox" class="brand-checkbox" value="' . $brand_name . '" style="display: none; margin-right: 8px; width: 16px; height: 16px; cursor: pointer;" onclick="event.stopPropagation(); updateBrandDisplay()">
                                                    <span class="brand-text">' . $brand_name . '</span>
                                                  </div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Terms: <span style="color: red;">*</span></label>
                        <select id="terms" required>
                            <option value="30"  <?php echo ($terms_val == '30')  ? 'selected' : ''; ?>>30 Days</option>
                            <option value="15"  <?php echo ($terms_val == '15')  ? 'selected' : ''; ?>>15 Days</option>
                            <option value="60"  <?php echo ($terms_val == '60')  ? 'selected' : ''; ?>>60 Days</option>
                            <option value="90"  <?php echo ($terms_val == '90')  ? 'selected' : ''; ?>>90 Days</option>
                            <option value="cod" <?php echo (strtolower($terms_val) === 'cod') ? 'selected' : ''; ?>>Cash on Delivery</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Due Date: <span style="color: red;">*</span></label>
                        <input type="date" id="payment_due_date" value="<?php echo $due_date_val; ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label>Remarks</label>
                        <textarea id="remarks" placeholder="Remarks"><?php echo htmlspecialchars($po['remarks'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Summary Card -->
            <div class="summary-card">
                <h3>Reason to edit PO</h3>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 500; color: #444; margin-bottom: 6px; display: block;">Reason <span style="color: red;">*</span></label>
                    <textarea id="edit_reason" placeholder="Enter reason for editing this Purchase Order" style="min-height: 80px; resize: vertical; width: 100%; padding: 9px 12px; border: 1px solid #d0d0d0; border-radius: 4px; font-size: 13px; color: #333; background: white; font-family: Arial, sans-serif; outline: none; transition: border-color 0.2s; box-sizing: border-box;" required onfocus="this.style.borderColor='#408140'" onblur="this.style.borderColor='#d0d0d0'"></textarea>
                </div>

                <h3>Receiving Summary</h3>

                <div class="summary-row">
                    <span>Items</span>
                    <span class="value" id="summary-items">0</span>
                </div>
                <div class="summary-row">
                    <span>Total Quantity</span>
                    <span class="value" id="summary-qty">&#8369; 0.00</span>
                </div>

                <hr class="summary-divider">

                <div class="summary-total">
                    <span>Total</span>
                    <span class="total-amount" id="summary-total">&#8369; 0.00</span>
                </div>

                <button class="btn-save-po" id="btn-save" onclick="savePO()">Save Changes</button>
                <button class="btn-cancel-po" onclick="window.location.href='purchaseorder.php'">Cancel</button>
            </div>

            <!-- BOTTOM: Item Section -->
            <div class="item-section">
                <div class="item-section-header">
                    <h3>Item</h3>
                    <button class="btn-add-item" onclick="addItemRow()">+ Add Item</button>
                </div>

                <div id="item-empty" class="item-empty" style="display:none;">
                    No item added yet. Click "Add Item" to start.
                </div>

                <div class="items-table-wrapper">
                    <table class="items-table" id="items-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Family Code</th>
                                <th>Quantity</th>
                                <th>Cost</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="items-tbody"></tbody>
                    </table>
                </div>
            </div>

        </div><!-- end .create-po-layout -->
    </div><!-- end .main-content -->

    <!-- Search Modal -->
    <div id="searchItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Search Family Code
            </div>
            <div class="modal-body">
                <table class="search-results-table">
                    <thead>
                        <tr>
                            <th style="width: 70%;">Family Code</th>
                            <th style="width: 30%; text-align: center;">Action</th>
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

    <!-- Pre-load existing items from PHP into JS -->
    <script>
        const existingItems = <?php echo json_encode($existing_items); ?>;
    </script>

    <script>
        // --- Brand Selection Functions --------------------------------------
        let isMultipleBrandsMode = false;
        let selectedBrand = null;

        // --- Toggle Brand Dropdown ---
        function toggleBrandDropdown() {
            const dropdown = document.getElementById('brand-dropdown');
            const input = document.querySelector('.brand-select-input');
            
            if (dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                input.classList.remove('active');
            } else {
                dropdown.classList.add('show');
                input.classList.add('active');
            }
        }

        // --- Toggle Multiple Brands Mode ---
        function toggleMultipleBrandsMode() {
            const checkbox = document.getElementById('multiple-brands-checkbox');
            isMultipleBrandsMode = checkbox.checked;
            
            const brandOptions = document.querySelectorAll('.brand-option');
            const brandCheckboxes = document.querySelectorAll('.brand-checkbox');
            
            if (isMultipleBrandsMode) {
                // Switch to multiple selection mode - show all checkboxes
                brandOptions.forEach(option => {
                    option.onclick = null; // Remove single-select click handler
                    option.classList.remove('selected');
                });
                brandCheckboxes.forEach(cb => {
                    cb.style.display = 'inline-block';
                    cb.checked = false; // Reset selections
                });
                selectedBrand = null;
            } else {
                // Switch to single selection mode - hide all checkboxes
                brandOptions.forEach((option, index) => {
                    const brandName = option.getAttribute('data-brand');
                    option.onclick = function() { selectSingleBrand(brandName); };
                    option.classList.remove('selected');
                });
                brandCheckboxes.forEach(cb => {
                    cb.style.display = 'none';
                    cb.checked = false;
                });
                selectedBrand = null;
            }
            
            updateBrandDisplay();
        }

        // --- Select Single Brand ---
        function selectSingleBrand(brandName) {
            if (isMultipleBrandsMode) return; // Ignore in multiple mode
            
            selectedBrand = brandName;
            
            // Update visual selection
            document.querySelectorAll('.brand-option').forEach(option => {
                if (option.getAttribute('data-brand') === brandName) {
                    option.classList.add('selected');
                } else {
                    option.classList.remove('selected');
                }
            });
            
            updateBrandDisplay();
            toggleBrandDropdown(); // Close dropdown after selection
        }

        // --- Update Brand Display ---
        function updateBrandDisplay() {
            const display = document.getElementById('brand-display');
            
            if (isMultipleBrandsMode) {
                const checkedBoxes = document.querySelectorAll('.brand-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    display.textContent = 'Select Brands';
                    display.classList.remove('has-selection');
                } else {
                    const selectedBrands = Array.from(checkedBoxes).map(cb => cb.value);
                    display.textContent = selectedBrands.join(', ');
                    display.classList.add('has-selection');
                }
            } else {
                if (selectedBrand) {
                    display.textContent = selectedBrand;
                    display.classList.add('has-selection');
                } else {
                    display.textContent = 'Select Brand';
                    display.classList.remove('has-selection');
                }
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('brand-dropdown');
            const customSelect = document.querySelector('.custom-brand-select');
            
            if (dropdown && customSelect && !customSelect.contains(event.target)) {
                dropdown.classList.remove('show');
                document.querySelector('.brand-select-input')?.classList.remove('active');
            }
        });

        // --- Load Saved Brands Function ---
        function loadSavedBrands() {
            // Get saved brand data from PHP
            const savedBrandType = '<?php echo isset($po["brand_type"]) ? $po["brand_type"] : "single"; ?>';
            const savedBrandsJson = '<?php echo isset($po["selected_brands"]) ? addslashes($po["selected_brands"]) : "[]"; ?>';
            
            try {
                const savedBrands = JSON.parse(savedBrandsJson);
                
                if (savedBrands && savedBrands.length > 0) {
                    if (savedBrandType === 'multiple' && savedBrands.length > 1) {
                        // Enable multiple brands mode
                        document.getElementById('multiple-brands-checkbox').checked = true;
                        toggleMultipleBrandsMode();
                        
                        // Check the saved brands
                        savedBrands.forEach(brandName => {
                            const checkbox = document.querySelector(`.brand-checkbox[value="${brandName}"]`);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                        updateBrandDisplay();
                    } else {
                        // Single brand mode - set the brand without opening dropdown
                        if (savedBrands.length > 0) {
                            const brandName = savedBrands[0];
                            selectedBrand = brandName;
                            
                            // Update visual selection
                            document.querySelectorAll('.brand-option').forEach(option => {
                                if (option.getAttribute('data-brand') === brandName) {
                                    option.classList.add('selected');
                                } else {
                                    option.classList.remove('selected');
                                }
                            });
                            
                            // Update display
                            const display = document.getElementById('brand-display');
                            display.textContent = brandName;
                            display.classList.add('has-selection');
                            
                            // Make sure dropdown is closed
                            const dropdown = document.getElementById('brand-dropdown');
                            dropdown.classList.remove('show');
                            document.querySelector('.brand-select-input')?.classList.remove('active');
                        }
                    }
                }
            } catch (e) {
                console.error('Error parsing saved brands:', e);
            }
        }

        // --- Sidebar toggle -------------------------------------------------
        function toggleSidebar() {
            const sidebar     = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn     = document.querySelector('.menu-btn');
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('expanded');
            menuBtn.classList.toggle('active');
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

        // --- Item rows -------------------------------------------------------
        let rowCount = 0;

        // Helper function to format numbers with commas
        function formatNumberWithCommas(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // Function to format cost input with commas as user types
        function formatCostInput(input, rowId) {
            let value = input.value.replace(/[^\d.]/g, ''); // Remove non-numeric characters except decimal
            let parts = value.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ','); // Add commas to integer part
            if (parts.length > 2) {
                parts = [parts[0], parts.slice(1).join('')]; // Handle multiple decimal points
            }
            if (parts[1] && parts[1].length > 2) {
                parts[1] = parts[1].substring(0, 2); // Limit to 2 decimal places
            }
            input.value = parts.join('.');
            recalcRow(rowId);
        }

        function addItemRow(familyCode = '', qty = '', cost = '') {
            rowCount++;
            const tbody = document.getElementById('items-tbody');
            const empty = document.getElementById('item-empty');
            const table = document.getElementById('items-table');

            empty.style.display = 'none';
            table.style.display = 'table';

            const lineTotal = (parseFloat(qty) || 0) * (parseFloat(cost) || 0);

            const tr = document.createElement('tr');
            tr.id = 'row-' + rowCount;

            tr.innerHTML = `
                <td>${rowCount}</td>
                <td><input type="text" placeholder="Family Code" class="familycode-${rowCount}" value="${escHtml(familyCode)}" onkeydown="handleFamilyCodeKeydown(event, ${rowCount})" onblur="handleFamilyCodeBlur(event, ${rowCount})"></td>
                <td><input type="number" min="0" placeholder="Enter Quantity" style="width:130px;" oninput="recalcRow(${rowCount})" class="qty-${rowCount}" value="${escHtml(qty)}"></td>
                <td><input type="text" placeholder="Enter cost" style="width:130px;" oninput="formatCostInput(this, ${rowCount})" class="price-${rowCount}" value="${escHtml(cost)}"></td>
                <td id="row-total-${rowCount}">&#8369; ${formatNumberWithCommas(lineTotal.toFixed(2))}</td>
                <td><button class="btn-remove-row" onclick="removeRow(${rowCount})">Remove</button></td>
            `;
            tbody.appendChild(tr);
            updateSummary();
        }

        function escHtml(val) {
            if (val === null || val === undefined) return '';
            return String(val).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function removeRow(id) {
            const row = document.getElementById('row-' + id);
            if (row) row.remove();
            updateSummary();
            checkEmpty();
        }

        function recalcRow(id) {
            const qty   = parseFloat(document.querySelector('.qty-'   + id)?.value) || 0;
            const priceInput = document.querySelector('.price-' + id);
            const priceValue = priceInput ? priceInput.value.replace(/,/g, '') : '0'; // Remove commas for calculation
            const price = parseFloat(priceValue) || 0;
            const total = qty * price;
            const cell  = document.getElementById('row-total-' + id);
            if (cell) cell.innerHTML = '&#8369; ' + formatNumberWithCommas(total.toFixed(2));
            updateSummary();
        }

        function updateSummary() {
            const rows  = document.querySelectorAll('#items-tbody tr');
            let grandTotal = 0, totalQty = 0;
            rows.forEach(row => {
                const id    = row.id.replace('row-', '');
                const qty   = parseFloat(document.querySelector('.qty-'   + id)?.value) || 0;
                const priceInput = document.querySelector('.price-' + id);
                const priceValue = priceInput ? priceInput.value.replace(/,/g, '') : '0'; // Remove commas for calculation
                const price = parseFloat(priceValue) || 0;
                totalQty   += qty;
                grandTotal += qty * price;
            });
            document.getElementById('summary-items').textContent = rows.length;
            document.getElementById('summary-qty').innerHTML    = '&#8369; ' + formatNumberWithCommas(totalQty.toFixed(2));
            document.getElementById('summary-total').innerHTML  = '&#8369; ' + formatNumberWithCommas(grandTotal.toFixed(2));
        }

        function checkEmpty() {
            const rows  = document.querySelectorAll('#items-tbody tr');
            const empty = document.getElementById('item-empty');
            const table = document.getElementById('items-table');
            if (rows.length === 0) {
                empty.style.display = 'block';
                table.style.display = 'none';
            }
        }

        // --- Load existing items on page load -------------------------------
        window.addEventListener('DOMContentLoaded', () => {
            // Initialize searchable selects
            initializeSearchableSelects();
            
            // Load suppliers
            loadSuppliers();
            
            // Load saved brand data
            loadSavedBrands();
            
            if (existingItems && existingItems.length > 0) {
                existingItems.forEach(item => {
                    addItemRow(
                        item.family_code || '',
                        item.quantity    || '',
                        item.cost        || ''
                    );
                });
            } else {
                document.getElementById('item-empty').style.display = 'block';
                document.getElementById('items-table').style.display = 'none';
            }
        });

        // --- Searchable Select Functionality -----------------------------
        function initializeSearchableSelects() {
            document.querySelectorAll('.searchable-select').forEach(function(select) {
                const wrapper = document.createElement('div');
                wrapper.className = 'select-wrapper';
                
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'select-search-input';
                input.placeholder = select.options[0] ? select.options[0].text : 'Select...';
                
                const dropdown = document.createElement('div');
                dropdown.className = 'select-dropdown';
                
                // Insert wrapper before select
                select.parentNode.insertBefore(wrapper, select);
                wrapper.appendChild(input);
                wrapper.appendChild(dropdown);
                wrapper.appendChild(select);
                
                // Hide original select
                select.style.display = 'none';
                
                // Handle input focus
                input.addEventListener('focus', function() {
                    dropdown.classList.add('active');
                    if (select.id === 'supplier_company') {
                        // Load companies if dropdown is empty
                        if (dropdown.children.length === 0) {
                            loadCompaniesIntoDropdown(dropdown, select, input);
                        }
                    }
                });
                
                // Handle input typing for search
                input.addEventListener('input', function() {
                    const searchTerm = input.value.toLowerCase();
                    
                    if (select.id === 'supplier_company') {
                        // Search company names
                        if (searchTerm.length >= 2) {
                            searchCompanies(searchTerm, dropdown, select, input);
                        } else if (searchTerm.length === 0) {
                            loadCompaniesIntoDropdown(dropdown, select, input);
                        }
                    } else {
                        // Regular filtering for other selects
                        const options = dropdown.querySelectorAll('.select-option');
                        options.forEach(option => {
                            const text = option.textContent.toLowerCase();
                            if (text.includes(searchTerm)) {
                                option.classList.remove('hidden');
                            } else {
                                option.classList.add('hidden');
                            }
                        });
                    }
                });
                
                // Handle clicks outside
                document.addEventListener('click', function(e) {
                    if (!wrapper.contains(e.target)) {
                        dropdown.classList.remove('active');
                    }
                });
            });
        }

        function loadCompaniesIntoDropdown(dropdown, select, input) {
            dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Loading...</div>';
            
            fetch('search_supplier.php?type=company&term=')
                .then(response => response.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    if (data.status === 'success' && data.data.length > 0) {
                        data.data.forEach(company => {
                            const option = document.createElement('div');
                            option.className = 'select-option';
                            option.textContent = company.store_name;
                            option.addEventListener('click', function() {
                                setSelectValue('supplier_company', company.store_name);
                                dropdown.classList.remove('active');
                            });
                            dropdown.appendChild(option);
                        });
                    } else {
                        dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">No companies found</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading companies:', error);
                    dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Error loading companies</div>';
                });
        }

        function searchCompanies(searchTerm, dropdown, select, input) {
            dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Searching...</div>';
            
            fetch(`search_supplier.php?type=company&term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    if (data.status === 'success' && data.data.length > 0) {
                        data.data.forEach(company => {
                            const option = document.createElement('div');
                            option.className = 'select-option';
                            option.textContent = company.store_name;
                            option.addEventListener('click', function() {
                                setSelectValue('supplier_company', company.store_name);
                                dropdown.classList.remove('active');
                            });
                            dropdown.appendChild(option);
                        });
                    } else {
                        dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">No companies found</div>';
                    }
                })
                .catch(error => {
                    console.error('Error searching companies:', error);
                    dropdown.innerHTML = '<div class="select-option" style="text-align:center; color:#999;">Error searching companies</div>';
                });
        }

        // --- Load Suppliers -------------------------------------------------
        function loadSuppliers() {
            // Load companies first
            fetch('search_supplier.php?type=company&term=')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        populateSupplierCompanies(data.data);
                        
                        // Set current company value if editing
                        const currentCompany = '<?php echo htmlspecialchars($po['supplier_company'] ?? ''); ?>';
                        if (currentCompany) {
                            setSelectValue('supplier_company', currentCompany);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading companies:', error);
                });
        }

        function populateSupplierCompanies(companies) {
            const select = document.getElementById('supplier_company');
            const wrapper = select.parentNode;
            const dropdown = wrapper.querySelector('.select-dropdown');
            
            // Clear existing options
            dropdown.innerHTML = '';
            
            companies.forEach(company => {
                const option = document.createElement('div');
                option.className = 'select-option';
                option.textContent = company.store_name;
                option.addEventListener('click', function() {
                    setSelectValue('supplier_company', company.store_name);
                    dropdown.classList.remove('active');
                    wrapper.querySelector('.select-search-input').readOnly = true;
                });
                dropdown.appendChild(option);
            });
        }

        function setSelectValue(selectId, value) {
            const select = document.getElementById(selectId);
            const wrapper = select.parentNode;
            const input = wrapper.querySelector('.select-search-input');
            const dropdown = wrapper.querySelector('.select-dropdown');
            
            // Set input value
            input.value = value;
            
            // Update select value (create option if doesn't exist)
            let option = Array.from(select.options).find(opt => opt.value === value);
            if (!option) {
                option = new Option(value, value);
                select.appendChild(option);
            }
            select.value = value;
            
            // Update dropdown selection
            dropdown.querySelectorAll('.select-option').forEach(opt => {
                opt.classList.remove('selected');
                if (opt.textContent === value) {
                    opt.classList.add('selected');
                }
            });
        }

        // --- Family Code Search Functions -----------------------------------------
        let currentSearchResults = [];
        let currentSearchRowId = null;

        function handleFamilyCodeKeydown(event, rowId) {
            if (event.key === 'Enter') {
                event.preventDefault();
                const familyCodeInput = document.querySelector(`.familycode-${rowId}`);
                const searchTerm = familyCodeInput.value.trim();
                
                if (searchTerm === '') {
                    alert('Please enter a family code to search!');
                    return;
                }
                
                currentSearchRowId = rowId;
                performSearch(searchTerm);
            }
        }

        function handleFamilyCodeBlur(event, rowId) {
            const familyCodeInput = event.target;
            const familyCode = familyCodeInput.value.trim();
            
            if (familyCode) {
                // Family code entered, validation will happen on submit
            }
        }

        function performSearch(searchTerm) {
            const modal = document.getElementById('searchItemModal');
            const resultsBody = document.getElementById('searchResultsBody');
            
            // Get selected brands
            let selectedBrandsParam = '';
            if (isMultipleBrandsMode) {
                const checkedBoxes = document.querySelectorAll('.brand-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one brand before searching for items.');
                    return;
                }
                const brands = Array.from(checkedBoxes).map(cb => cb.value);
                selectedBrandsParam = brands.join(',');
            } else {
                if (!selectedBrand) {
                    alert('Please select a brand before searching for items.');
                    return;
                }
                selectedBrandsParam = selectedBrand;
            }
            
            // Show loading state
            resultsBody.innerHTML = '<tr><td colspan="2" style="text-align:center; padding: 20px;">Searching...</td></tr>';
            modal.style.display = 'flex';
            
            console.log('Searching with term:', searchTerm, 'and brands:', selectedBrandsParam);
            
            // Fetch results from search_familycode.php with brand filter
            fetch(`search_familycode.php?term=${encodeURIComponent(searchTerm)}&brands=${encodeURIComponent(selectedBrandsParam)}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Search response:', data);
                    resultsBody.innerHTML = '';
                    if (data.status === 'success' && data.data.length > 0) {
                        currentSearchResults = data.data;
                        data.data.forEach((item, index) => {
                            const row = `
                                <tr>
                                    <td>${item.family_code || ''}</td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-select" onclick="selectItem(${index})">Select</button>
                                    </td>
                                </tr>
                            `;
                            resultsBody.insertAdjacentHTML('beforeend', row);
                        });
                    } else {
                        const message = data.message || 'No family codes found for the selected brand(s)';
                        resultsBody.innerHTML = `<tr><td colspan="2" style="text-align:center; padding: 20px;">${message}</td></tr>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsBody.innerHTML = '<tr><td colspan="2" style="text-align:center; padding: 20px;">Error occurred while searching</td></tr>';
                });
        }

        function selectItem(index) {
            if (currentSearchRowId && currentSearchResults[index]) {
                const selectedItem = currentSearchResults[index];
                const familyCodeInput = document.querySelector(`.familycode-${currentSearchRowId}`);
                
                // Fill the family code
                familyCodeInput.value = selectedItem.family_code;
                
                closeSearchModal();
                
                // Focus on quantity field
                const qtyInput = document.querySelector(`.qty-${currentSearchRowId}`);
                if (qtyInput) {
                    qtyInput.focus();
                }
            }
        }

        function closeSearchModal() {
            document.getElementById('searchItemModal').style.display = 'none';
            currentSearchResults = [];
            currentSearchRowId = null;
        }

        // Close modal on background click
        window.onclick = function (event) {
            const searchModal = document.getElementById('searchItemModal');
            if (event.target == searchModal) {
                closeSearchModal();
            }
        };

        // --- Save PO (AJAX) -------------------------------------------------
        function savePO() {
            const editReason = document.getElementById('edit_reason').value.trim();
            if (!editReason) {
                alert('Please provide a reason for editing this Purchase Order.');
                document.getElementById('edit_reason').focus();
                return;
            }

            const supplier_company = document.getElementById('supplier_company').value.trim();
            const terms            = document.getElementById('terms').value.trim();
            const dueDate          = document.getElementById('payment_due_date').value.trim();

            if (!supplier_company) {
                alert('Please select a supplier company.');
                return;
            }
            if (!dueDate) {
                alert('Please select a payment due date.');
                return;
            }

            // Validate brand selection
            let selectedBrands = [];
            let brandType = 'single';
            
            if (isMultipleBrandsMode) {
                brandType = 'multiple';
                const checkedBoxes = document.querySelectorAll('.brand-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one brand.');
                    return;
                }
                checkedBoxes.forEach(cb => selectedBrands.push(cb.value));
            } else {
                if (!selectedBrand) {
                    alert('Please select a brand.');
                    return;
                }
                selectedBrands.push(selectedBrand);
            }

            const rows = document.querySelectorAll('#items-tbody tr');
            if (rows.length === 0) {
                alert('Please add at least one item.');
                return;
            }

            const items = [];
            let valid = true;
            rows.forEach((row, idx) => {
                const id    = row.id.replace('row-', '');
                const familyCode = document.querySelector('.familycode-' + id)?.value.trim() || '';
                const qty   = document.querySelector('.qty-' + id)?.value.trim() || '';
                const priceInput = document.querySelector('.price-' + id);
                const cost = priceInput ? priceInput.value.replace(/,/g, '').trim() : '';

                if (!familyCode || !qty || !cost) {
                    alert('Row ' + (idx + 1) + ': Please fill in family code, quantity, and cost.');
                    valid = false;
                    return;
                }

                items.push({
                    item_no: idx + 1,
                    family_code: familyCode,
                    item_model: '',
                    item_description: '',
                    serial_number: '',
                    quantity: qty,
                    cost: cost,
                    total: (parseFloat(qty) * parseFloat(cost)).toFixed(2)
                });
            });

            if (!valid) return;

            const payload = {
                po_id: <?php echo $po_id; ?>,
                edit_reason: editReason,
                supplier_company: supplier_company,
                terms: terms,
                payment_due_date: dueDate,
                remarks: document.getElementById('remarks').value.trim(),
                brand_type: brandType,
                selected_brands: selectedBrands,
                items: items
            };

            document.getElementById('btn-save').disabled = true;
            document.getElementById('btn-save').textContent = 'Saving...';

            fetch('update_purchase_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Purchase Order updated successfully!');
                    window.location.href = '<?php echo $backUrl; ?>';
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                    document.getElementById('btn-save').disabled = false;
                    document.getElementById('btn-save').textContent = 'Save Changes';
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('An error occurred while saving. Please try again.');
                document.getElementById('btn-save').disabled = false;
                document.getElementById('btn-save').textContent = 'Save Changes';
            });
        }
    </script>
</body>
</html>

