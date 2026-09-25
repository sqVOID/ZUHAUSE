<?php
require_once 'session_check.php';
include 'config.php';
require_once 'po_status_helpers.php';

// Ensure receive-added item column exists (emergency items added during receive)
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS is_receive_added TINYINT(1) DEFAULT 0");

// Get PO ID from URL
$po_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$from = isset($_GET['from']) ? $_GET['from'] : 'purchaseorder';

// Redirect if no ID provided
if ($po_id <= 0) {
    header('Location: purchaseorder.php');
    exit;
}

// Fetch active branches from database
$branches_result = $conn->query("SELECT branch_name, branch_code, area FROM branches WHERE status = 'Active' ORDER BY area ASC, branch_name ASC");
$branches = [];
if ($branches_result && $branches_result->num_rows > 0) {
    while ($branch = $branches_result->fetch_assoc()) {
        $branches[] = $branch;
    }
}

// Fetch PO data from database
$po_query = $conn->query("SELECT * FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
if (!$po_query || $po_query->num_rows === 0) {
    header('Location: purchaseorder.php');
    exit;
}
$po_data = $po_query->fetch_assoc();

// Calculate overall PO status based on order qty, allocations, and received totals
$po_totals = get_po_quantity_totals($conn, $po_id);
$po_data['total_order_qty'] = $po_totals['total_order_qty'];
$po_data['total_allocated'] = $po_totals['total_allocated'];
$po_data['total_received']  = $po_totals['total_received'];

if (strcasecmp($po_data['status'], 'Canceled') === 0 || strcasecmp($po_data['status'], 'Cancelled') === 0) {
    $po_data['calculated_status'] = $po_data['status'];
} else {
    $po_data['calculated_status'] = calculate_po_workflow_status($po_totals, $po_data['status']);
}

// Fetch items for this PO with live allocation totals from purchase_order_allocations
$items_query = $conn->query("
    SELECT 
        MIN(poi.id) as id,
        poi.po_id,
        poi.po_number,
        MIN(poi.item_no) as item_no,
        poi.family_code,
        MAX(poi.cost) as cost,
        MAX(poi.quantity) as quantity,
        COALESCE(alloc.total_allocated, 0) as allocated_quantity,
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM purchase_order_allocations poa
                WHERE poa.po_id = poi.po_id 
                AND poa.family_code COLLATE utf8mb4_general_ci = poi.family_code COLLATE utf8mb4_general_ci
                AND poa.received_qty > 0
            ) THEN 1
            ELSE 0
        END as has_received_items
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
    GROUP BY poi.po_id, poi.po_number, poi.family_code, alloc.total_allocated
    ORDER BY MIN(poi.item_no) ASC
");
$items_data = [];
if ($items_query && $items_query->num_rows > 0) {
    while ($item = $items_query->fetch_assoc()) {
        $order_qty = (int) ($item['quantity'] ?? 0);
        $allocated_qty = (int) ($item['allocated_quantity'] ?? 0);

        // Keep displayed totals aligned when branch allocations exceed stored item quantity
        if ($allocated_qty > $order_qty) {
            $item['quantity'] = $allocated_qty;
        }

        $items_data[] = $item;
    }

    // Sync cached allocated_quantity on purchase_order_items for this PO
    $conn->query("
        UPDATE purchase_order_items poi
        SET allocated_quantity = (
            SELECT COALESCE(SUM(poa.quantity), 0)
            FROM purchase_order_allocations poa
            WHERE poa.po_id = poi.po_id
            AND poa.family_code COLLATE utf8mb4_general_ci = poi.family_code COLLATE utf8mb4_general_ci
        )
        WHERE poi.po_id = {$po_id}
        AND COALESCE(poi.is_receive_added, 0) = 0
    ");
}

// Fetch branch allocations (if you have an allocations table)
$table_check = $conn->query("SHOW TABLES LIKE 'purchase_order_allocations'");
$branch_allocations = [];

if ($table_check && $table_check->num_rows > 0) {
    // One row per allocation — do NOT join PO items by family/model (that cartesian-duplicates
    // qty when multiple units share the same model, e.g. after editing BLACK→BLUE).
    $detail_query = false;
    try {
        $detail_query = $conn->query("
            SELECT
                poa.id AS poa_id,
                poa.branch_name AS branch,
                poa.quantity AS allocated_qty,
                COALESCE(poa.received_qty, 0) AS poa_received_qty,
                poa.serial_number AS serial_number,
                COALESCE(i.has_serial, 0) AS has_serial,
                COALESCE(NULLIF(poa.cost, 0), 0) AS cost,
                poa.received_by,
                poa.received_at
            FROM purchase_order_allocations poa
            LEFT JOIN items i ON poa.item_model IS NOT NULL
                AND poa.item_model != ''
                AND poa.item_model != '-'
                AND BINARY poa.item_model = BINARY i.item_code
                AND i.status = 'Active'
            WHERE poa.po_id = {$po_id}
            ORDER BY poa.branch_name ASC, poa.id ASC
        ");
    } catch (Throwable $e) {
        $detail_query = false;
    }

    $branch_totals = [];
    $seen_poa_ids = [];
    if ($detail_query && $detail_query->num_rows > 0) {
        while ($drow = $detail_query->fetch_assoc()) {
            $poa_id = (int)($drow['poa_id'] ?? 0);
            if ($poa_id > 0) {
                if (isset($seen_poa_ids[$poa_id])) {
                    continue;
                }
                $seen_poa_ids[$poa_id] = true;
            }

            $bn = $drow['branch'];
            if (!isset($branch_totals[$bn])) {
                $branch_totals[$bn] = ['total_allocation_qty' => 0, 'received_qty' => 0, 'total_cost_allocated' => 0.0];
            }

            $alloc_qty = (int)$drow['allocated_qty'];
            $branch_totals[$bn]['total_allocation_qty'] += $alloc_qty;
            $branch_totals[$bn]['total_cost_allocated'] += $alloc_qty * (float)$drow['cost'];

            if ((int)$drow['has_serial'] === 1) {
                // Serialized: count serial numbers
                $sn = trim($drow['serial_number'] ?? '');
                if (!empty($sn)) {
                    $sn_arr = (strpos($sn, "\n") !== false)
                        ? explode("\n", $sn)
                        : explode(",", $sn);
                    $branch_totals[$bn]['received_qty'] += count(array_filter(array_map('trim', $sn_arr)));
                }
            } else {
                // Unserialized: count received_qty if > 0 or formally received
                if ((int)$drow['poa_received_qty'] > 0 || !empty($drow['received_by']) || !empty($drow['received_at'])) {
                    $branch_totals[$bn]['received_qty'] += (int)$drow['poa_received_qty'];
                }
            }
        }
    }

    foreach ($branch_totals as $bn => $totals) {
        $alloc_qty = $totals['total_allocation_qty'];
        $recv_qty = $totals['received_qty'];
        
        $status = 'Waiting';
        if ($alloc_qty > 0 && $recv_qty >= $alloc_qty) {
            $status = 'Complete';
        } elseif ($recv_qty > 0) {
            $status = 'Incomplete';
        }

        $branch_allocations[] = [
            'branch' => $bn,
            'total_cost_allocated' => $totals['total_cost_allocated'],
            'total_allocation_qty' => $alloc_qty,
            'received_qty' => $recv_qty,
            'status' => $status
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Details</title>
    <style>
        :root {
            --color-navy: #0d3347;
            --color-navy-dark: #081f2d;
            --color-navy-light: #164460;
            --color-gold: #b08a52;
            --color-gold-light: #c9a46e;
            --color-gold-pale: #f5ede0;
            --color-green: #2e7d32;
            --color-green-dark: #1b5e20;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            zoom: 77%;
        }

        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background-color: white;
            display: flex;
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

        /* Sidebar */
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
            z-index: 999;
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

        /* Main Content */
        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Page Header */
        .page-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .back-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            color: #333;
        }

        .back-btn svg {
            width: 20px;
            height: 20px;
            fill: #333;
        }

        .page-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        /* Table Container */
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .table-container h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0 0 15px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--color-gold-pale);
        }

        th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border-top: 1px solid #ccc;
            border-bottom: 1px solid #ccc;
        }

        th:first-child {
            border-left: 1px solid #ccc;
        }

        th:last-child {
            border-right: 1px solid #ccc;
        }

        td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border-bottom: 1px solid #ccc;
            text-align: center;
        }

        td:first-child {
            border-left: 1px solid #ccc;
        }

        td:last-child {
            border-right: 1px solid #ccc;
        }

        /* Ensure border appears even with rowspan */
        td[rowspan] {
            border-left: 1px solid #ccc;
        }

        tbody tr:hover {
            background: #fafafa;
        }

        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa0;
        }

        .badge-waiting {
            background: #cfe2ff;
            color: #084298;
            border: 1px solid #b6d4fe;
        }

        .badge-allocated {
            background: #d4edda;
            color: #1a7a35;
            border: 1px solid #b8dfc6;
        }

        .badge-complete {
            background: #d4edda;
            color: #1a7a35;
            border: 1px solid #b8dfc6;
        }

        .badge-received {
            background: #d4edda;
            color: #1a7a35;
            border: 1px solid #b8dfc6;
        }

        .badge-incomplete {
            background: #ffeaa0;
            color: #856404;
            border: 1px solid #ffdd57;
        }

        .badge-open {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa0;
        }

        .badge-completed {
            background: #d4edda;
            color: #1a7a35;
            border: 1px solid #b8dfc6;
        }

        .badge-cancelled,
        .badge-canceled {
            background: #e2e3e5;
            color: #41464b;
            border: 1px solid #d3d4d5;
        }

        .badge-decline,
        .badge-declined {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        /* Action Buttons */
        .btn-allocate {
            padding: 5px 14px;
            background: var(--color-green);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 4px;
        }

        .btn-allocate:hover {
            background: var(--color-green-dark);
        }

        .btn-view {
            padding: 5px 14px;
            background: var(--color-navy);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 4px;
        }

        .btn-view:hover {
            background: var(--color-navy-dark);
        }

        .btn-cancel {
            padding: 5px 14px;
            background: #c62828;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-left: 4px;
        }

        .btn-cancel:hover {
            background: #b71c1c;
        }

        .btn-closed {
            padding: 5px 14px;
            background: #1976d2;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-left: 4px;
        }

        .btn-closed:hover {
            background: #1565c0;
        }

        .btn-edit {
            padding: 5px 14px;
            background: var(--color-gold);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-edit:hover {
            background: var(--color-gold-light);
        }

        .btn-remove {
            padding: 5px 14px;
            background: #c62828;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 4px;
        }

        .btn-remove:hover {
            background: #b71c1c;
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
            padding: 20px 30px;
            border-bottom: 1px solid #eee;
            font-size: 18px;
            font-weight: 700;
            color: #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .modal-close:hover {
            color: #000;
        }

        .modal-body {
            padding: 20px 30px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-table-section {
            margin-bottom: 30px;
        }

        .modal-table-section h4 {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }

        .modal-footer {
            padding: 15px 30px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-back-modal {
            background-color: #666;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-back-modal:hover {
            background-color: #555;
        }

        .btn-set-allocation {
            background-color: var(--color-green);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-set-allocation:hover {
            background-color: var(--color-green-dark);
        }

        .btn-save-final-allocation {
            background-color: var(--color-navy);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-save-final-allocation:hover {
            background-color: var(--color-navy-dark);
        }

        .btn-remove-session-allocation {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-remove-session-allocation:hover {
            background-color: #c82333;
        }

        /* Per-branch session allocation styles */
        .session-branch-section {
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
            background-color: #f9f9f9;
        }

        .session-branch-name {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 700;
            color: var(--color-navy);
        }

        .session-allocation-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }

        .session-allocation-table thead {
            background-color: #f5f5f5;
        }

        .session-allocation-table th,
        .session-allocation-table td {
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }

        .session-allocation-table th:first-child,
        .session-allocation-table td:first-child {
            text-align: left;
        }

        /* Modal Table Styles */
        .modal-body table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .modal-body thead {
            background: var(--color-gold-pale);
        }

        .modal-body th {
            text-align: center;
            padding: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border: 1px solid #ccc;
        }

        .modal-body td {
            padding: 10px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
        }

        .modal-body tbody tr:hover {
            background: #fafafa;
        }

        .modal-body input[type="number"],
        .modal-body select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            text-align: left;
        }

        .modal-body input[type="number"]:focus,
        .modal-body select:focus {
            outline: none;
            border-color: var(--color-gold);
        }

        .modal-body select option {
            text-align: left;
        }

        .btn-add-allocation {
            padding: 6px 12px;
            background: var(--color-green);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-add-allocation:hover {
            background: var(--color-green-dark);
        }

        .btn-remove-allocation {
            padding: 6px 12px;
            background: #c62828;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-remove-allocation:hover {
            background: #b71c1c;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.hidden {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .header::after {
                left: 0;
            }

            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                min-width: 600px;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()">
            <span></span><span></span><span></span>
        </div>
        <?php include '_header_user.php'; ?>
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <button class="back-btn" onclick="window.location.href='<?php echo $from; ?>.php'" title="Back">
                <svg viewBox="0 0 24 24">
                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z" />
                </svg>
            </button>
            <h2>Purchase Order Details - <?php echo htmlspecialchars($po_data['po_number']); ?></h2>
        </div>

        <!-- Table 1: PO Information -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Supplier</th>
                        <th>Brand</th>
                        <th>PO Date</th>
                        <th>Terms</th>
                        <th>Total Cost</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align:left; font-weight:600;">
                            <?php echo htmlspecialchars($po_data['po_number']); ?></td>
                        <td><?php echo htmlspecialchars($po_data['supplier_company']); ?></td>
                        <td>
                            <?php
                            // Format brand display
                            $brand_display = '-';
                            if (!empty($po_data['brand_type']) && !empty($po_data['selected_brands'])) {
                                $selected_brands = json_decode($po_data['selected_brands'], true);
                                if (is_array($selected_brands) && count($selected_brands) > 0) {
                                    $brand_display = htmlspecialchars(implode(', ', $selected_brands));
                                }
                            }
                            echo $brand_display;
                            ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($po_data['po_date'])); ?></td>
                        <td><?php
                        if (strtolower($po_data['terms']) === 'cod') {
                            echo 'Cash on Delivery';
                        } else {
                            echo $po_data['terms'] . ' Days';
                        }
                        ?></td>
                        <td>&#8369; <?php echo number_format($po_data['total_cost'], 2); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($po_data['calculated_status']); ?>">
                                <?php
                                if ($po_data['calculated_status'] === 'Pending') {
                                    echo 'Open';
                                } elseif ($po_data['calculated_status'] === 'Received') {
                                    echo 'Completed';
                                } elseif ($po_data['calculated_status'] === 'Incomplete') {
                                    echo 'Incomplete';
                                } else {
                                    echo htmlspecialchars($po_data['calculated_status']);
                                }
                                ?>
                            </span></td>
                        <td>
                            <?php if ($po_data['total_received'] > 0): ?>
                                <button class="btn-view" onclick="viewPO()">VIEW</button>
                            <?php else: ?>
                                <button class="btn-view" disabled
                                    style="background: #ccc; cursor: not-allowed;">VIEW</button>
                            <?php endif; ?>

                            <?php
                            $calculated_status = strtolower($po_data['calculated_status']);
                            // Hide CANCEL button if status is Received (Completed) or Incomplete
                            if ($calculated_status !== 'received' && $calculated_status !== 'incomplete'):
                                ?>
                                <button class="btn-cancel" onclick="openCancelModal()">CANCEL</button>
                            <?php endif; ?>

                            <?php
                            // Show CLOSED button only when status is Received (Completed)
                            if ($calculated_status === 'received'):
                                ?>
                                <button class="btn-closed" onclick="openClosedModal()">CLOSED</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Table 2: Items -->
        <div class="table-container">
            <?php if (empty($items_data)): ?>
                <p style="text-align: center; color: #888; padding: 30px;">No items found for this purchase order.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="text-align: left;">Family Code</th>
                            <th>Cost</th>
                            <th>Quantity</th>
                            <th>Allocated Quantity</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $item_count = count($items_data);
                        foreach ($items_data as $index => $item): 
                            $order_qty = (int) ($item['quantity'] ?? 0);
                            $allocated_qty = (int) ($item['allocated_quantity'] ?? 0);
                        ?>
                            <tr>
                                <td style="text-align:left; font-weight:600;">
                                    <?php echo htmlspecialchars($item['family_code']); ?></td>
                                <td>&#8369; <?php echo number_format($item['cost'], 2); ?></td>
                                <td><?php echo $order_qty; ?></td>
                                <td><?php echo $allocated_qty . ' / ' . $order_qty; ?></td>
                                <?php if ($index === 0): ?>
                                    <td rowspan="<?php echo $item_count; ?>" style="vertical-align: middle;">
                                        <button class="btn-allocate" onclick="openAllocateAllModal()">ALLOCATE</button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Table 3: Branch Allocations -->
        <div class="table-container">
            <h3>Branch Allocations</h3>
            <?php if (empty($branch_allocations)): ?>
                <p style="text-align: center; color: #888; padding: 30px;">No branch allocations yet. Click "ALLOCATE" on
                    items above to assign them to branches.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>Total Cost Allocated</th>
                            <th>Total Allocation QTY</th>
                            <th>Received QTY</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branch_allocations as $allocation): ?>
                            <tr>
                                <td style="text-align:left; font-weight:600;">
                                    <?php echo htmlspecialchars($allocation['branch']); ?></td>
                                <td>&#8369; <?php echo number_format($allocation['total_cost_allocated'], 2); ?></td>
                                <td><?php echo $allocation['total_allocation_qty']; ?></td>
                                <td><?php echo isset($allocation['received_qty']) ? $allocation['received_qty'] : '0'; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($allocation['status']); ?>">
                                        <?php echo htmlspecialchars($allocation['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-view"
                                        onclick="viewAllocation('<?php echo htmlspecialchars($allocation['branch']); ?>')">VIEW</button>
                                    <?php if ($allocation['status'] === 'Complete'): ?>
                                        <button class="btn-edit" disabled
                                            style="background: #ccc; cursor: not-allowed;">EDIT</button>
                                        <button class="btn-remove" disabled
                                            style="background: #ccc; cursor: not-allowed;">REMOVE</button>
                                    <?php elseif ($allocation['status'] === 'Incomplete'): ?>
                                        <button class="btn-edit"
                                            onclick="editAllocation('<?php echo htmlspecialchars($allocation['branch']); ?>')">EDIT</button>
                                        <button class="btn-remove" disabled
                                            style="background: #ccc; cursor: not-allowed;">REMOVE</button>
                                    <?php else: ?>
                                        <button class="btn-edit"
                                            onclick="editAllocation('<?php echo htmlspecialchars($allocation['branch']); ?>')">EDIT</button>
                                        <button class="btn-remove"
                                            onclick="removeAllocation('<?php echo htmlspecialchars($allocation['branch']); ?>')">REMOVE</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Allocate Modal -->
    <div id="allocateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span>Allocate Items</span>
                <span class="modal-close" onclick="closeAllocateModal()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Table 1: Item Information -->
                <div class="modal-table-section">
                    <h4>Item Information</h4>
                    <table id="itemInfoTable">
                        <thead>
                            <tr>
                                <th style="text-align: left;">Family Code</th>
                                <th>Total Quantity</th>
                                <th>Already Allocated</th>
                                <th>Quantity Left</th>
                            </tr>
                        </thead>
                        <tbody id="itemInfoBody">
                            <?php foreach ($items_data as $item): ?>
                                <?php
                                $order_qty = (int) ($item['quantity'] ?? 0);
                                $allocated_qty = (int) ($item['allocated_quantity'] ?? 0);
                                $qty_left = $order_qty - $allocated_qty;
                                ?>
                                <tr data-family-code="<?php echo htmlspecialchars($item['family_code']); ?>"
                                    data-total-qty="<?php echo $order_qty; ?>"
                                    data-allocated-qty="<?php echo $allocated_qty; ?>">
                                    <td style="font-weight:600; text-align: left;">
                                        <?php echo htmlspecialchars($item['family_code']); ?>
                                    </td>
                                    <td class="item-total-qty"><?php echo $order_qty; ?></td>
                                    <td class="item-allocated-qty"><?php echo $allocated_qty; ?></td>
                                    <td class="item-qty-left"><?php echo $qty_left; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Table 1.5: Current Session Allocations -->
                <div class="modal-table-section" id="currentSessionAllocationsSection" style="display: none;">
                    <h4>Current Session Allocations</h4>
                    <div id="currentSessionAllocationsContainer">
                        <!-- Will be populated with per-branch tables dynamically -->
                    </div>
                </div>

                <!-- Table 2: Branch Allocation -->
                <div class="modal-table-section">
                    <h4>Branch Allocation</h4>
                    <div style="margin-bottom: 15px;">
                        <label
                            style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #444;">Select
                            Branch:</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="selectedBranchDisplay" readonly placeholder="Click to select branch"
                                onclick="openBranchSelectionModal()"
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; cursor: pointer; background: white;">
                            <button type="button" class="btn-allocate" onclick="openBranchSelectionModal()"
                                style="white-space: nowrap;">Select</button>
                        </div>
                        <input type="hidden" id="branchSelectAll" value="">
                    </div>

                    <div id="branchAllocationTableContainer">
                        <table id="branchAllocationTable">
                            <thead>
                                <tr>
                                    <th style="width: 70%; text-align: left;">Family Code</th>
                                    <th style="width: 30%;">Quantity</th>
                                </tr>
                            </thead>
                            <tbody id="branchAllocationBody">
                                <?php foreach ($items_data as $item): ?>
                                    <?php
                                    $order_qty = (int) ($item['quantity'] ?? 0);
                                    $allocated_qty = (int) ($item['allocated_quantity'] ?? 0);
                                    ?>
                                    <tr data-family-code="<?php echo htmlspecialchars($item['family_code']); ?>"
                                        data-total-qty="<?php echo $order_qty; ?>"
                                        data-allocated-qty="<?php echo $allocated_qty; ?>"
                                        data-cost="<?php echo $item['cost']; ?>">
                                        <td style="font-weight:600; text-align: left;">
                                            <?php echo htmlspecialchars($item['family_code']); ?>
                                        </td>
                                        <td>
                                            <input type="number" min="0" value="0" class="allocation-qty-input"
                                                style="width: 100%; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;"
                                                onchange="updateAllQuantityLeft()">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Set button inside Branch Allocation section -->
                    <div style="margin-top: 15px; text-align: right;">
                        <button class="btn-set-allocation" onclick="setAllAllocations()">Set</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-back-modal" onclick="closeAllocateModal()">Back</button>
                <button class="btn-save-final-allocation" onclick="saveFinalAllocation()" style="background-color: var(--color-navy); color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600;">Save Allocation</button>
            </div>
        </div>
    </div>

    <!-- Branch Selection Modal (for Allocate Modal) -->
    <div id="branchSelectionModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <span>Select Branch</span>
                <span class="modal-close" onclick="closeBranchSelectionModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 15px;">
                    <input type="text" id="branchSearchInput" placeholder="Search branches..."
                        onkeyup="filterBranchSelection()"
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
                </div>
                <div style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                    <label style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="selectAllBranchesModal" style="width: 18px; height: 18px;"
                            onchange="toggleBranchSelectionSelectAll()">
                        Select All
                    </label>
                </div>
                <div id="branchSelectionList" style="max-height: 400px; overflow-y: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center; padding: 10px; background: var(--color-gold-pale); border: 1px solid #ccc;"></th>
                                <th style="text-align: left; padding: 10px; background: var(--color-gold-pale); border: 1px solid #ccc;">Area</th>
                                <th style="text-align: left; padding: 10px; background: var(--color-gold-pale); border: 1px solid #ccc;">Branch Name</th>
                            </tr>
                        </thead>
                        <tbody id="branchSelectionBody">
                            <?php
                            // Group branches by area
                            $branches_by_area = [];
                            foreach ($branches as $branch) {
                                $area = !empty($branch['area']) ? $branch['area'] : 'Uncategorized';
                                $branches_by_area[$area][] = $branch;
                            }
                            ksort($branches_by_area);

                            // Display branches grouped by area
                            foreach ($branches_by_area as $area => $area_branches) {
                                $area_display = htmlspecialchars(ucwords(str_replace('_', ' ', $area)));
                                $area_safe = htmlspecialchars($area);
                                $branch_count = count($area_branches);
                                
                                foreach ($area_branches as $index => $branch) {
                                    $branch_name = htmlspecialchars($branch['branch_name']);
                                    
                                    echo '<tr class="branch-selection-row" style="border: 1px solid #ccc;" ';
                                    echo 'data-area="' . $area_safe . '" ';
                                    echo 'data-branch="' . $branch_name . '">';
                                    
                                    // First row of each area: show area checkbox with rowspan
                                    if ($index === 0) {
                                        echo '<td style="text-align: center; padding: 10px; vertical-align: middle; border-right: 1px solid #ccc;" rowspan="' . $branch_count . '">';
                                        echo '<input type="checkbox" class="area-select-all" data-area="' . $area_safe . '" ';
                                        echo 'style="width: 16px; height: 16px;" onchange="toggleAreaSelectAll(this)">';
                                        echo '</td>';
                                        echo '<td style="padding: 10px; vertical-align: middle; border-right: 1px solid #ccc;" rowspan="' . $branch_count . '">';
                                        echo $area_display;
                                        echo '</td>';
                                    }
                                    
                                    // Branch name with checkbox
                                    echo '<td style="padding: 10px;">';
                                    echo '<label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">';
                                    echo '<input type="checkbox" class="branch-checkbox ' . $area_safe . '-checkbox" ';
                                    echo 'value="' . $branch_name . '" ';
                                    echo 'data-area="' . $area_safe . '" ';
                                    echo 'style="width: 16px; height: 16px;" onchange="handleBranchCheckboxChange(this)">';
                                    echo '<span>' . $branch_name;
                                    if (!empty($branch['branch_code'])) {
                                        echo ' - ' . htmlspecialchars($branch['branch_code']);
                                    }
                                    echo '</span>';
                                    echo '</label>';
                                    echo '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-back-modal" onclick="closeBranchSelectionModal()">Cancel</button>
                <button class="btn-set-allocation" onclick="applyBranchSelection()">Select</button>
            </div>
        </div>
    </div>

    <!-- Edit Allocation Modal -->
    <div id="editAllocationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span>Edit Allocation - <span id="editModalBranchName">Branch Name</span></span>
                <span class="modal-close" onclick="closeEditAllocationModal()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Table 1: Item Information -->
                <div class="modal-table-section">
                    <h4>Item Information</h4>
                    <table id="editItemInfoTable">
                        <thead>
                            <tr>
                                <th style="text-align: left;">Family Code</th>
                                <th>Total Quantity</th>
                                <th>Other Branch Allocated</th>
                                <th>Quantity Left</th>
                            </tr>
                        </thead>
                        <tbody id="editItemInfoBody">
                            <!-- Will be populated dynamically -->
                            <tr>
                                <td colspan="4" style="text-align:center; padding:20px;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table 2: Allocated Items -->
                <div class="modal-table-section">
                    <h4>Allocated Items</h4>
                    <table id="editAllocationTable">
                        <thead>
                            <tr>
                                <th style="width: 50%; text-align: left;">Family Code</th>
                                <th style="width: 30%;">Allocated Quantity</th>
                                <th style="width: 20%;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="editAllocationBody">
                            <!-- Will be populated dynamically -->
                            <tr>
                                <td colspan="3" style="text-align:center; padding:20px;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-back-modal" onclick="closeEditAllocationModal()">Back</button>
                <button class="btn-set-allocation" onclick="updateAllocation()">Update</button>
            </div>
        </div>
    </div>

    <!-- View Allocation Modal -->
    <div id="viewAllocationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span>View Allocation - <span id="viewModalBranchName">Branch Name</span></span>
                <span class="modal-close" onclick="closeViewAllocationModal()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Table 1: PO Information -->
                <div class="modal-table-section">
                    <h4>Purchase Order Information</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Supplier</th>
                                <th>PO Date</th>
                                <th>Terms</th>
                                <th>Total Cost</th>
                                <th>Status</th>
                                <th>Branch</th>
                            </tr>
                        </thead>
                        <tbody id="viewPOInfoBody">
                            <tr>
                                <td colspan="7" style="text-align:center; padding:20px;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table 2: Allocated Items -->
                <div class="modal-table-section" style="margin-top: 20px;">
                    <h4>Allocated Items</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Family Code</th>
                                <th>Allocated Quantity</th>
                                <th>Receive Quantity</th>
                                <th>Invoice Number</th>
                            </tr>
                        </thead>
                        <tbody id="viewAllocatedItemsBody">
                            <tr>
                                <td colspan="4" style="text-align:center; padding:20px;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Remarks Section -->
                    <div
                        style="margin-top: 15px; padding: 12px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 6px; color: #444;">Remarks:</label>
                        <div id="viewRemarks" style="color: #666; font-size: 13px; min-height: 20px;">-</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-back-modal" onclick="closeViewAllocationModal()">Back</button>
            </div>
        </div>
    </div>

    <!-- View PO Modal (for first table VIEW button) -->
    <div id="viewPOModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span>PO Invoice per branch - <span id="viewPOModalNumber">PO-XXXX-XXX</span></span>
                <span class="modal-close" onclick="closeViewPOModal()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Table 1: PO Information -->
                <div class="modal-table-section">
                    <h4>Purchase Order Information</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Supplier</th>
                                <th>Brand</th>
                                <th>PO Date</th>
                                <th>Terms</th>
                                <th>Total Cost</th>
                                <th>Total Cost Received</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="viewPOInfoTableBody">
                            <tr>
                                <td colspan="8" style="text-align:center; padding:20px;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table 2: Branch Allocations -->
                <div class="modal-table-section" style="margin-top: 20px;">
                    <h4>Branch Allocations</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Invoice Number</th>
                                <th>DR Date</th>
                                <th>DR Received</th>
                                <th>Received By</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="viewPOItemsTableBody">
                            <tr>
                                <td colspan="6" style="text-align:center; padding:20px;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-back-modal" onclick="closeViewPOModal()">Back</button>
            </div>
        </div>
    </div>

    <!-- Cancel PO Modal -->
    <div id="cancelPOModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <span>Cancel Purchase Order - <?php echo htmlspecialchars($po_data['po_number']); ?></span>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 15px; font-size: 14px; color: #666;">Please provide a reason for canceling this
                    purchase order:</p>
                <textarea id="cancelReason" placeholder="Enter cancellation reason..."
                    style="width: 100%; min-height: 120px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif; resize: vertical;"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="submitCancelPO()" style="margin-right: 8px;">Confirm Cancel</button>
                <button class="btn-back-modal" onclick="closeCancelModal()">Back</button>
            </div>
        </div>
    </div>

    <!-- Closed PO Modal -->
    <div id="closedPOModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <span>Close Purchase Order - <?php echo htmlspecialchars($po_data['po_number']); ?></span>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 15px; font-size: 14px; color: #666;">Are you sure you want to close this
                    purchase order?</p>
                <p style="margin-bottom: 15px; font-size: 14px; color: #666;">This will mark the purchase order as
                    closed and finalized. This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button class="btn-closed" onclick="submitClosedPO()" style="margin-right: 8px;">Confirm Close</button>
                <button class="btn-back-modal" onclick="closeClosedModal()">Back</button>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuBtn = document.querySelector('.menu-btn');

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

        // Toggle section for sidebar menu
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

        // Initialize sidebar state
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.querySelector('.sidebar');
            if (window.innerWidth <= 768) {
                sidebar.classList.add('hidden');
                sidebar.style.transform = 'translateX(-100%)';
            }
        });

        // Action functions (placeholders for now)
        function viewPO() {
            // Get PO ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            const poId = urlParams.get('id');

            if (!poId) {
                alert('Invalid purchase order ID!');
                return;
            }

            // Show loading state
            document.getElementById('viewPOInfoTableBody').innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px;">Loading...</td></tr>';
            document.getElementById('viewPOItemsTableBody').innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px;">Loading...</td></tr>';

            // Show modal
            document.getElementById('viewPOModal').style.display = 'flex';

            // Fetch PO data and branch data together to calculate proper status
            Promise.all([
                fetch(`get_po_info.php?po_id=${poId}`).then(response => response.json()),
                fetch(`get_po_branch_details.php?po_id=${poId}`).then(response => response.json()),
                fetch(`get_total_cost_received.php?po_id=${poId}`).then(response => response.json())
            ])
                .then(([poData, branchData, costData]) => {
                    if (poData.success) {
                        const po = poData.po;
                        const termsDisplay = po.terms.toLowerCase() === 'cod' ? 'Cash on Delivery' : po.terms + ' Days';

                        // Format brand display
                        let brandDisplay = '-';
                        if (po.selected_brands) {
                            try {
                                const brands = JSON.parse(po.selected_brands);
                                if (Array.isArray(brands) && brands.length > 0) {
                                    brandDisplay = brands.join(', ');
                                }
                            } catch (e) {
                                brandDisplay = '-';
                            }
                        }

                        // Set modal title
                        document.getElementById('viewPOModalNumber').textContent = po.po_number;

                        // Calculate overall status based on ALL branch allocations
                        let calculatedStatus = po.status; // Default to database status
                        let calculatedStatusDisplay = po.status;
                        let badgeClass = po.status.toLowerCase();

                        // If PO is Canceled, keep it as Canceled
                        if (po.status.toLowerCase() === 'canceled' || po.status.toLowerCase() === 'cancelled') {
                            calculatedStatus = 'Canceled';
                            calculatedStatusDisplay = 'Canceled';
                            badgeClass = 'canceled';
                        } else if (branchData.success && branchData.branches.length > 0) {
                            const orderQty = parseInt(po.total_qty || 0, 10);
                            const allocatedQty = parseInt(po.total_allocated || 0, 10);
                            const branches = branchData.branches;
                            const allCompleted = branches.every(branch => branch.status === 'Completed');
                            const allWaiting = branches.every(branch => branch.status === 'Waiting');
                            const someReceived = branches.some(branch => branch.status === 'Completed' || branch.status === 'Incomplete');

                            if (orderQty > allocatedQty) {
                                calculatedStatus = 'Incomplete';
                                calculatedStatusDisplay = 'Incomplete';
                                badgeClass = 'incomplete';
                            } else if (allCompleted) {
                                calculatedStatus = 'Received';
                                calculatedStatusDisplay = 'Completed';
                                badgeClass = 'completed';
                            } else if (allWaiting) {
                                calculatedStatus = 'Pending';
                                calculatedStatusDisplay = 'Open';
                                badgeClass = 'open';
                            } else if (someReceived) {
                                calculatedStatus = 'Incomplete';
                                calculatedStatusDisplay = 'Incomplete';
                                badgeClass = 'incomplete';
                            }
                        }

                        const totalCostReceived = costData.success ? costData.total_cost_received : 0;

                        // Populate PO Info Table with calculated status
                        document.getElementById('viewPOInfoTableBody').innerHTML = `
                            <tr>
                                <td style="font-weight:600;">${po.po_number}</td>
                                <td>${po.supplier_company}</td>
                                <td>${brandDisplay}</td>
                                <td>${new Date(po.po_date).toLocaleDateString('en-GB')}</td>
                                <td>${termsDisplay}</td>
                                <td>&#8369; ${parseFloat(po.total_cost).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}</td>
                                <td>&#8369; ${parseFloat(totalCostReceived).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}</td>
                                <td><span class="badge badge-${badgeClass}">${calculatedStatusDisplay}</span></td>
                            </tr>
                        `;

                        // Populate branch details table
                        if (branchData.success && branchData.branches.length > 0) {
                            console.log('Number of branches:', branchData.branches.length);
                            let branchHTML = '';
                            branchData.branches.forEach(branch => {
                                // Main branch row
                                branchHTML += `
                                    <tr>
                                        <td style="font-weight:600; text-align:left !important;">${branch.branch_name}</td>
                                        <td style="text-align:left !important;">${branch.invoice_number}</td>
                                        <td style="text-align:left !important;">${branch.dr_date}</td>
                                        <td>${branch.dr_received}</td>
                                        <td style="text-align:left !important;">${branch.received_by}</td>
                                        <td><span class="badge badge-${branch.status.toLowerCase()}">${branch.status}</span></td>
                                    </tr>
                                `;
                                // Remarks row (below the main row)
                                const remarksText = (branch.receiving_remarks === '-' || !branch.receiving_remarks) ? '' : branch.receiving_remarks;
                                branchHTML += `
                                    <tr>
                                        <td colspan="6" style="padding:8px 12px; background-color:#f9f9f9; border-top:none; text-align:left !important;">
                                            <strong style="color:#666; font-size:12px;">Remarks:</strong> 
                                            <span style="font-size:12px; color:#333;">${remarksText}</span>
                                        </td>
                                    </tr>
                                `;
                            });
                            document.getElementById('viewPOItemsTableBody').innerHTML = branchHTML;
                        } else {
                            console.log('No branches found or error');
                            document.getElementById('viewPOItemsTableBody').innerHTML = '<tr><td colspan="6" style="text-align:center; color:#999; padding:20px;">No branch allocations found</td></tr>';
                        }
                    } else {
                        document.getElementById('viewPOInfoTableBody').innerHTML = '<tr><td colspan="8" style="text-align:center; color:red; padding:20px;">Error loading PO data</td></tr>';
                        document.getElementById('viewPOItemsTableBody').innerHTML = '<tr><td colspan="6" style="text-align:center; color:red; padding:20px;">Error loading data</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('viewPOInfoTableBody').innerHTML = '<tr><td colspan="8" style="text-align:center; color:red; padding:20px;">Error loading data</td></tr>';
                    document.getElementById('viewPOItemsTableBody').innerHTML = '<tr><td colspan="6" style="text-align:center; color:red; padding:20px;">Error loading data</td></tr>';
                });
        }

        function closeViewPOModal() {
            document.getElementById('viewPOModal').style.display = 'none';
        }

        // --- Cancel PO Modal Functions ---
        function openCancelModal() {
            document.getElementById('cancelPOModal').style.display = 'flex';
            document.getElementById('cancelReason').value = '';
            document.getElementById('cancelReason').focus();
        }

        function closeCancelModal() {
            document.getElementById('cancelPOModal').style.display = 'none';
        }

        function submitCancelPO() {
            const reason = document.getElementById('cancelReason').value.trim();

            if (!reason) {
                alert('Please provide a reason for canceling this purchase order.');
                document.getElementById('cancelReason').focus();
                return;
            }

            if (!confirm('Are you sure you want to cancel this purchase order? This action cannot be undone.')) {
                return;
            }

            // Send cancellation request to server
            const formData = new FormData();
            formData.append('po_id', <?php echo $po_id; ?>);
            formData.append('cancel_reason', reason);

            fetch('cancel_purchase_order.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Purchase Order canceled successfully.');
                        window.location.href = '<?php echo $from === "purchaseorderreceive" ? "purchaseorderreceive.php" : "purchaseorder.php"; ?>';
                    } else {
                        alert('Error: ' + (data.message || 'Failed to cancel purchase order.'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while canceling the purchase order.');
                });
        }

        // --- Closed PO Modal Functions ---
        function openClosedModal() {
            document.getElementById('closedPOModal').style.display = 'flex';
        }

        function closeClosedModal() {
            document.getElementById('closedPOModal').style.display = 'none';
        }

        function submitClosedPO() {
            if (!confirm('Are you sure you want to close this purchase order? This action cannot be undone.')) {
                return;
            }

            // Send closed request to server
            const formData = new FormData();
            formData.append('po_id', <?php echo $po_id; ?>);

            fetch('close_purchase_order.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Purchase Order closed successfully.');
                        window.location.href = '<?php echo $from === "purchaseorderreceive" ? "purchaseorderreceive.php" : "purchaseorder.php"; ?>';
                    } else {
                        alert('Error: ' + (data.message || 'Failed to close purchase order.'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while closing the purchase order.');
                });
        }

        function editPO() {
            alert('Edit PO functionality - to be implemented');
        }

        // Function to add allocation to current session table
        function addToCurrentSessionAllocations(branchName, allocations) {
            const container = document.getElementById('currentSessionAllocationsContainer');
            const section = document.getElementById('currentSessionAllocationsSection');
            
            // Show the section if hidden
            if (section.style.display === 'none') {
                section.style.display = 'block';
            }
            
            // Check if a table for this branch already exists
            let branchTable = document.getElementById(`sessionTable_${branchName.replace(/\s+/g, '_')}`);
            
            if (!branchTable) {
                // Create new branch section with table
                const branchDiv = document.createElement('div');
                branchDiv.className = 'session-branch-section';
                branchDiv.id = `sessionBranch_${branchName.replace(/\s+/g, '_')}`;
                
                branchDiv.innerHTML = `
                    <h5 class="session-branch-name">${branchName}</h5>
                    <table class="session-allocation-table" id="sessionTable_${branchName.replace(/\s+/g, '_')}">
                        <thead>
                            <tr>
                                <th style="text-align: left;">Family Code</th>
                                <th>Quantity</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                `;
                
                container.appendChild(branchDiv);
                branchTable = document.getElementById(`sessionTable_${branchName.replace(/\s+/g, '_')}`);
            }
            
            // Add each allocation as a row to the branch's table
            const tbody = branchTable.querySelector('tbody');
            allocations.forEach(alloc => {
                const row = document.createElement('tr');
                row.setAttribute('data-branch', branchName);
                row.setAttribute('data-family-code', alloc.family_code);
                row.setAttribute('data-quantity', alloc.quantity);
                
                row.innerHTML = `
                    <td style="text-align: left;">${alloc.family_code}</td>
                    <td>${alloc.quantity}</td>
                    <td>
                        <button type="button" class="btn-remove-session-allocation" onclick="removeSessionAllocation(this)">
                            Remove
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Function to clear session allocations table
        function clearCurrentSessionAllocations() {
            const container = document.getElementById('currentSessionAllocationsContainer');
            const section = document.getElementById('currentSessionAllocationsSection');
            container.innerHTML = '';
            section.style.display = 'none';
        }

        // Function to remove a single allocation from session table
        function removeSessionAllocation(button) {
            const row = button.closest('tr');
            const branchTable = button.closest('table');
            const branchDiv = button.closest('.session-branch-section');
            const container = document.getElementById('currentSessionAllocationsContainer');
            const section = document.getElementById('currentSessionAllocationsSection');
            
            // Remove the row
            row.remove();
            
            // If the branch table is now empty, remove the entire branch section
            const tbody = branchTable.querySelector('tbody');
            if (tbody.querySelectorAll('tr').length === 0) {
                branchDiv.remove();
            }
            
            // Hide section if no more branch sections
            if (container.querySelectorAll('.session-branch-section').length === 0) {
                section.style.display = 'none';
            }
            
            // Recalculate quantity left after removal
            updateAllQuantityLeft();
        }

        // Function to save final allocation and close modal
        function saveFinalAllocation() {
            // Get all pending allocations from all branch tables
            const container = document.getElementById('currentSessionAllocationsContainer');
            const sessionRows = container.querySelectorAll('.session-allocation-table tbody tr');
            
            if (sessionRows.length === 0) {
                alert('No allocations to save! Please add allocations using the Set button first.');
                return;
            }

            // Parse session tables into structured data
            const allocationsByBranch = {};
            
            sessionRows.forEach(row => {
                const branchName = row.getAttribute('data-branch');
                const familyCode = row.getAttribute('data-family-code');
                const qty = parseInt(row.getAttribute('data-quantity')) || 0;
                
                if (!allocationsByBranch[branchName]) {
                    allocationsByBranch[branchName] = [];
                }
                
                // Find if this family code already exists for this branch
                const existing = allocationsByBranch[branchName].find(a => a.family_code === familyCode);
                if (existing) {
                    existing.quantity += qty;
                } else {
                    // Get cost from Branch Allocation table
                    const allocationRow = document.querySelector(`#branchAllocationBody tr[data-family-code="${familyCode}"]`);
                    const cost = allocationRow ? parseFloat(allocationRow.getAttribute('data-cost')) || 0 : 0;
                    
                    allocationsByBranch[branchName].push({
                        family_code: familyCode,
                        quantity: qty,
                        cost: cost
                    });
                }
            });

            // Get PO ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            const poId = urlParams.get('id');

            if (!poId) {
                alert('Invalid purchase order ID!');
                return;
            }

            // Disable button and show loading state
            const saveButton = document.querySelector('.btn-save-final-allocation');
            const originalText = saveButton.textContent;
            saveButton.disabled = true;
            saveButton.textContent = 'Saving to database...';

            // Process allocations for each branch sequentially
            const branches = Object.keys(allocationsByBranch);
            let completedBranches = 0;
            let allSuccessful = true;
            let errorMessages = [];

            // Function to save allocation for one branch
            function saveForBranch(branchName, index) {
                const allocations = allocationsByBranch[branchName];
                const formData = new FormData();
                formData.append('po_id', poId);
                formData.append('branch_name', branchName);
                formData.append('allocations', JSON.stringify(allocations));

                fetch('save_all_allocations.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        completedBranches++;
                        
                        if (!data.success) {
                            allSuccessful = false;
                            errorMessages.push(`${branchName}: ${data.message}`);
                        }
                        
                        // Check if all branches are processed
                        if (completedBranches === branches.length) {
                            finishFinalAllocation();
                        } else {
                            // Process next branch
                            if (allSuccessful) {
                                saveForBranch(branches[index + 1], index + 1);
                            } else {
                                finishFinalAllocation();
                            }
                        }
                    })
                    .catch(error => {
                        completedBranches++;
                        allSuccessful = false;
                        errorMessages.push(`${branchName}: ${error.message}`);
                        
                        if (completedBranches === branches.length) {
                            finishFinalAllocation();
                        }
                    });
            }

            // Function to finalize after all branches saved
            function finishFinalAllocation() {
                if (allSuccessful) {
                    alert(`Successfully saved allocations for ${branches.length} branch(es) to database!`);
                    // Close modal and reload page
                    closeAllocateModal();
                    window.location.reload();
                } else {
                    alert('Some allocations failed to save:\n' + errorMessages.join('\n'));
                    // Re-enable button
                    saveButton.disabled = false;
                    saveButton.textContent = originalText;
                }
            }

            // Start processing first branch
            saveForBranch(branches[0], 0);
        }

        // New function to open allocation modal for all items
        function openAllocateAllModal() {
            // Reset branch selection
            document.getElementById('branchSelectAll').value = '';
            document.getElementById('selectedBranchDisplay').value = '';
            
            // Reset all quantity inputs to 0
            const inputs = document.querySelectorAll('.allocation-qty-input');
            inputs.forEach(input => {
                input.value = '0';
            });
            
            // Clear current session allocations table
            clearCurrentSessionAllocations();
            
            // Update all quantity left values
            updateAllQuantityLeft();
            
            // Show modal
            document.getElementById('allocateModal').style.display = 'flex';
        }

        function closeAllocateModal() {
            document.getElementById('allocateModal').style.display = 'none';
        }

        // Branch Selection Modal Functions
        function openBranchSelectionModal() {
            document.getElementById('branchSelectionModal').style.display = 'flex';
            document.getElementById('branchSearchInput').value = '';
            
            // Uncheck all checkboxes
            const allCheckboxes = document.querySelectorAll('.branch-checkbox');
            allCheckboxes.forEach(cb => cb.checked = false);
            
            // Uncheck all area checkboxes
            const areaCheckboxes = document.querySelectorAll('.area-select-all');
            areaCheckboxes.forEach(cb => cb.checked = false);
            
            // Update select all state
            updateBranchSelectionSelectAllState();
            
            filterBranchSelection();
        }

        function closeBranchSelectionModal() {
            document.getElementById('branchSelectionModal').style.display = 'none';
        }

        function filterBranchSelection() {
            const searchValue = document.getElementById('branchSearchInput').value.toLowerCase();
            const rows = document.querySelectorAll('.branch-selection-row');
            
            rows.forEach(row => {
                const area = row.getAttribute('data-area').toLowerCase();
                const branch = row.getAttribute('data-branch').toLowerCase();
                
                // Search in both area and branch name
                if (area.includes(searchValue) || branch.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function handleBranchCheckboxChange(checkbox) {
            // Allow multiple checkboxes to be selected
            // Update the display to show count of selected branches
            updateSelectedBranchesDisplay();
            // Update select all checkbox state
            updateBranchSelectionSelectAllState();
            // Update area checkbox states
            updateAreaCheckboxStates();
        }

        function toggleBranchSelectionSelectAll() {
            const selectAll = document.getElementById('selectAllBranchesModal');
            const checkboxes = document.querySelectorAll('.branch-checkbox');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
            });
            
            updateSelectedBranchesDisplay();
            updateAreaCheckboxStates();
        }

        function toggleAreaSelectAll(areaCheckbox) {
            const area = areaCheckbox.getAttribute('data-area');
            const areaCheckboxes = document.querySelectorAll('.' + area + '-checkbox');
            
            areaCheckboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (!row || row.style.display !== 'none') {
                    cb.checked = areaCheckbox.checked;
                }
            });
            
            updateSelectedBranchesDisplay();
            updateBranchSelectionSelectAllState();
        }

        function updateAreaCheckboxStates() {
            const areas = document.querySelectorAll('.area-select-all');
            
            areas.forEach(areaCheckbox => {
                const area = areaCheckbox.getAttribute('data-area');
                const areaCheckboxes = document.querySelectorAll('.' + area + '-checkbox');
                const visibleAreaCheckboxes = Array.from(areaCheckboxes).filter(cb => {
                    const row = cb.closest('tr');
                    return !row || row.style.display !== 'none';
                });
                
                if (visibleAreaCheckboxes.length === 0) {
                    areaCheckbox.checked = false;
                    areaCheckbox.indeterminate = false;
                    return;
                }
                
                const checkedCount = visibleAreaCheckboxes.filter(cb => cb.checked).length;
                
                if (checkedCount === 0) {
                    areaCheckbox.checked = false;
                    areaCheckbox.indeterminate = false;
                } else if (checkedCount === visibleAreaCheckboxes.length) {
                    areaCheckbox.checked = true;
                    areaCheckbox.indeterminate = false;
                } else {
                    areaCheckbox.checked = false;
                    areaCheckbox.indeterminate = true;
                }
            });
        }

        function updateBranchSelectionSelectAllState() {
            const checkboxes = document.querySelectorAll('.branch-checkbox');
            const selectAll = document.getElementById('selectAllBranchesModal');
            
            if (checkboxes.length === 0) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
                return;
            }
            
            const checkedCount = document.querySelectorAll('.branch-checkbox:checked').length;
            
            if (checkedCount === 0) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            } else if (checkedCount === checkboxes.length) {
                selectAll.checked = true;
                selectAll.indeterminate = false;
            } else {
                selectAll.checked = false;
                selectAll.indeterminate = true;
            }
        }

        function updateSelectedBranchesDisplay() {
            const selectedCheckboxes = document.querySelectorAll('.branch-checkbox:checked');
            const count = selectedCheckboxes.length;
            const displayField = document.getElementById('selectedBranchDisplay');
            
            if (count === 0) {
                displayField.value = '';
                document.getElementById('branchSelectAll').value = '';
            } else if (count === 1) {
                displayField.value = selectedCheckboxes[0].value;
                document.getElementById('branchSelectAll').value = selectedCheckboxes[0].value;
            } else {
                displayField.value = `${count} branches selected`;
                // Store comma-separated list in hidden field
                const branchNames = Array.from(selectedCheckboxes).map(cb => cb.value).join(',');
                document.getElementById('branchSelectAll').value = branchNames;
            }
            
            // IMPORTANT: Update quantity left when branch selection changes
            updateAllQuantityLeft();
        }

        function applyBranchSelection() {
            const selectedCheckboxes = document.querySelectorAll('.branch-checkbox:checked');
            
            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one branch!');
                return;
            }
            
            updateSelectedBranchesDisplay();
            closeBranchSelectionModal();
        }

        function updateAllQuantityLeft() {
            // Get number of selected branches
            const branchInput = document.getElementById('branchSelectAll').value;
            const branches = branchInput ? branchInput.split(',').map(b => b.trim()).filter(b => b.length > 0) : [];
            const branchCount = branches.length || 1; // Default to 1 if no branches selected yet

            // First, get pending allocations from all branch tables
            const container = document.getElementById('currentSessionAllocationsContainer');
            const sessionRows = container.querySelectorAll('.session-allocation-table tbody tr');
            const pendingByItem = {};

            sessionRows.forEach(row => {
                const familyCode = row.getAttribute('data-family-code');
                const qty = parseInt(row.getAttribute('data-quantity')) || 0;
                
                if (!pendingByItem[familyCode]) {
                    pendingByItem[familyCode] = 0;
                }
                pendingByItem[familyCode] += qty;
            });

            // Now update each row
            const allocationRows = document.querySelectorAll('#branchAllocationBody tr');
            
            allocationRows.forEach(allocationRow => {
                const familyCode = allocationRow.getAttribute('data-family-code');
                const totalQty = parseInt(allocationRow.getAttribute('data-total-qty')) || 0;
                const allocatedQty = parseInt(allocationRow.getAttribute('data-allocated-qty')) || 0;
                const input = allocationRow.querySelector('.allocation-qty-input');
                const inputQty = parseInt(input.value) || 0;
                const pendingQty = pendingByItem[familyCode] || 0;
                
                // IMPORTANT: Multiply input quantity by number of branches
                // If user selects 2 branches and enters 1, that's 2 units total
                const totalInputQty = inputQty * branchCount;
                
                // Calculate quantity left: Total - Allocated - Pending - (Current Input × Branches)
                const qtyLeft = totalQty - allocatedQty - pendingQty - totalInputQty;
                
                // Update the corresponding row in Item Information table
                const itemInfoRow = document.querySelector(`#itemInfoBody tr[data-family-code="${familyCode}"]`);
                if (itemInfoRow) {
                    const qtyLeftCell = itemInfoRow.querySelector('.item-qty-left');
                    qtyLeftCell.textContent = qtyLeft;
                    
                    // Change color if over-allocated or fully allocated
                    if (qtyLeft < 0) {
                        qtyLeftCell.style.color = 'red';
                        qtyLeftCell.style.fontWeight = 'bold';
                    } else if (qtyLeft === 0) {
                        qtyLeftCell.style.color = 'orange';
                        qtyLeftCell.style.fontWeight = 'normal';
                    } else {
                        qtyLeftCell.style.color = '#333';
                        qtyLeftCell.style.fontWeight = 'normal';
                    }
                }
            });
        }

        function setAllAllocations() {
            const branchInput = document.getElementById('branchSelectAll').value;
            
            if (!branchInput) {
                alert('Please select at least one branch!');
                return;
            }

            // Parse branch names (could be single or comma-separated for multiple)
            const branches = branchInput.split(',').map(b => b.trim()).filter(b => b.length > 0);

            // First, calculate pending allocations from session table
            const container = document.getElementById('currentSessionAllocationsContainer');
            const sessionRows = container.querySelectorAll('.session-allocation-table tbody tr');
            const pendingByItem = {};

            sessionRows.forEach(row => {
                const familyCode = row.getAttribute('data-family-code');
                const qty = parseInt(row.getAttribute('data-quantity')) || 0;
                
                if (!pendingByItem[familyCode]) {
                    pendingByItem[familyCode] = 0;
                }
                pendingByItem[familyCode] += qty;
            });

            // Collect all items with quantity > 0
            const rows = document.querySelectorAll('#branchAllocationBody tr');
            const allocations = [];
            let hasOverAllocation = false;
            let overAllocationMessages = [];

            rows.forEach(row => {
                const familyCode = row.getAttribute('data-family-code');
                const totalQty = parseInt(row.getAttribute('data-total-qty')) || 0;
                const allocatedQty = parseInt(row.getAttribute('data-allocated-qty')) || 0;
                const cost = parseFloat(row.getAttribute('data-cost')) || 0;
                const input = row.querySelector('.allocation-qty-input');
                const qty = parseInt(input.value) || 0;
                
                if (qty > 0) {
                    // Calculate total new allocation for all selected branches
                    const totalNewAllocation = qty * branches.length;
                    // Get pending allocations for this item
                    const pendingQty = pendingByItem[familyCode] || 0;
                    // Calculate available quantity
                    const qtyLeft = totalQty - allocatedQty - pendingQty - totalNewAllocation;
                    
                    if (qtyLeft < 0) {
                        hasOverAllocation = true;
                        const shortage = Math.abs(qtyLeft);
                        overAllocationMessages.push(`${familyCode}: Over by ${shortage} unit(s)`);
                    }
                    
                    allocations.push({
                        family_code: familyCode,
                        quantity: qty,
                        cost: cost
                    });
                }
            });

            if (allocations.length === 0) {
                alert('Please enter at least one item quantity greater than 0!');
                return;
            }

            if (hasOverAllocation) {
                const errorMsg = `Cannot allocate! Insufficient quantity:\n\n${overAllocationMessages.join('\n')}\n\nYou selected ${branches.length} branch(es). Please reduce the quantities or remove some pending allocations.`;
                alert(errorMsg);
                return;
            }

            // Add to session table (NO DATABASE SAVE YET)
            branches.forEach(branchName => {
                addToCurrentSessionAllocations(branchName, allocations);
            });

            // Update quantity left to include pending allocations
            updateAllQuantityLeft();

            // Show success message
            alert(`Added allocation for ${branches.length} branch(es) to session. Click "Save Allocation" to commit.`);

            // Reset branch selection
            document.getElementById('branchSelectAll').value = '';
            document.getElementById('selectedBranchDisplay').value = '';
            
            // Reset all quantity inputs to 0
            const inputs = document.querySelectorAll('.allocation-qty-input');
            inputs.forEach(input => {
                input.value = '0';
            });
            
            // Update quantity left calculations
            updateAllQuantityLeft();
        }

        // Function to update pending allocations display
        // Close modal when clicking outside
        window.onclick = function (event) {
            const allocateModal = document.getElementById('allocateModal');
            const branchSelectionModal = document.getElementById('branchSelectionModal');
            const editModal = document.getElementById('editAllocationModal');
            const viewModal = document.getElementById('viewAllocationModal');
            const viewPOModal = document.getElementById('viewPOModal');

            if (event.target == allocateModal) {
                closeAllocateModal();
            } else if (event.target == branchSelectionModal) {
                closeBranchSelectionModal();
            } else if (event.target == editModal) {
                closeEditAllocationModal();
            } else if (event.target == viewModal) {
                closeViewAllocationModal();
            } else if (event.target == viewPOModal) {
                closeViewPOModal();
            }
        }

        function viewAllocation(branch) {
            // Set branch name in modal header
            document.getElementById('viewModalBranchName').textContent = branch;

            // Get PO ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            const poId = urlParams.get('id');

            if (!poId) {
                alert('Invalid purchase order ID!');
                return;
            }

            // Show loading state
            document.getElementById('viewPOInfoBody').innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px;">Loading...</td></tr>';
            document.getElementById('viewAllocatedItemsBody').innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">Loading...</td></tr>';
            document.getElementById('viewRemarks').textContent = '-';

            // Show modal
            document.getElementById('viewAllocationModal').style.display = 'flex';

            // Fetch PO data and allocations
            Promise.all([
                fetch(`get_po_info.php?po_id=${poId}`).then(r => r.json()),
                fetch(`get_branch_allocations.php?po_id=${poId}&branch_name=${encodeURIComponent(branch)}`).then(r => r.json())
            ])
                .then(([poData, allocData]) => {
                    // Populate PO Info Table
                    if (poData.success) {
                        const po = poData.po;
                        const termsDisplay = po.terms.toLowerCase() === 'cod' ? 'Cash on Delivery' : po.terms + ' Days';

                        // CRITICAL FIX: Calculate branch-specific status based on allocations
                        let branchStatus = 'Waiting';
                        let branchStatusClass = 'pending';

                        if (allocData.success && allocData.allocations.length > 0) {
                            let totalAllocated = 0;
                            let totalReceived = 0;

                            allocData.allocations.forEach(alloc => {
                                totalAllocated += parseInt(alloc.quantity) || 0;
                                totalReceived += parseInt(alloc.received_qty) || 0;
                            });

                            if (totalReceived === 0) {
                                branchStatus = 'Waiting';
                                branchStatusClass = 'pending';
                            } else if (totalReceived >= totalAllocated) {
                                branchStatus = 'Complete';
                                branchStatusClass = 'received';
                            } else {
                                branchStatus = 'Incomplete';
                                branchStatusClass = 'incomplete';
                            }
                        }

                        document.getElementById('viewPOInfoBody').innerHTML = `
                        <tr>
                            <td style="font-weight:600;">${po.po_number}</td>
                            <td>${po.supplier_company}</td>
                            <td>${new Date(po.po_date).toLocaleDateString('en-GB')}</td>
                            <td>${termsDisplay}</td>
                            <td>&#8369; ${parseFloat(po.total_cost).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}</td>
                            <td><span class="badge badge-${branchStatusClass}">${branchStatus}</span></td>
                            <td style="font-weight:600;">${branch}</td>
                        </tr>
                    `;

                    } else {
                        document.getElementById('viewPOInfoBody').innerHTML = '<tr><td colspan="7" style="text-align:center; color:red; padding:20px;">Error loading PO data</td></tr>';
                    }

                    // Populate Allocated Items Table
                    if (allocData.success && allocData.allocations.length > 0) {
                        let itemsHTML = '';
                        let branchRemarks = '-';

                        allocData.allocations.forEach(alloc => {
                            const receivedQty = alloc.received_qty || 0;
                            const invoiceNum = alloc.invoice_number || '-';
                            if (alloc.receiving_remarks && alloc.receiving_remarks.trim() !== '') {
                                branchRemarks = alloc.receiving_remarks;
                            }
                            itemsHTML += `
                            <tr>
                                <td style="font-weight:600;">${alloc.family_code}</td>
                                <td>${alloc.quantity}</td>
                                <td>${receivedQty}</td>
                                <td>${invoiceNum}</td>
                            </tr>
                        `;
                        });
                        document.getElementById('viewAllocatedItemsBody').innerHTML = itemsHTML;
                        document.getElementById('viewRemarks').textContent = branchRemarks;
                    } else {
                        document.getElementById('viewAllocatedItemsBody').innerHTML = '<tr><td colspan="4" style="text-align:center; color:#999; padding:20px;">No allocations found</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('viewPOInfoBody').innerHTML = '<tr><td colspan="7" style="text-align:center; color:red; padding:20px;">Error loading data</td></tr>';
                    document.getElementById('viewAllocatedItemsBody').innerHTML = '<tr><td colspan="4" style="text-align:center; color:red; padding:20px;">Error loading data</td></tr>';
                });
        }

        function closeViewAllocationModal() {
            document.getElementById('viewAllocationModal').style.display = 'none';
        }

        function editAllocation(branch) {
            // Set branch name in modal header
            document.getElementById('editModalBranchName').textContent = branch;

            // Get PO ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            const poId = urlParams.get('id');

            if (!poId) {
                alert('Invalid purchase order ID!');
                return;
            }

            // Show loading state
            const editItemInfoBody = document.getElementById('editItemInfoBody');
            const editBody = document.getElementById('editAllocationBody');
            editItemInfoBody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">Loading...</td></tr>';
            editBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px;">Loading...</td></tr>';

            // Show modal
            document.getElementById('editAllocationModal').style.display = 'flex';

            // Fetch PO items and allocation data
            Promise.all([
                fetch(`get_po_items.php?po_id=${poId}`).then(r => r.json()),
                fetch(`get_branch_allocations.php?po_id=${poId}&branch_name=${encodeURIComponent(branch)}`).then(r => r.json())
            ])
                .then(([itemsData, allocData]) => {
                    // Populate Item Information table
                    if (itemsData.success && itemsData.items.length > 0) {
                        editItemInfoBody.innerHTML = '';
                        
                        itemsData.items.forEach(item => {
                            const totalQty = parseInt(item.quantity) || 0;
                            const totalAllocated = parseInt(item.allocated_quantity) || 0;
                            
                            // Find this branch's allocation for this item
                            let thisBranchQty = 0;
                            if (allocData.success && allocData.allocations.length > 0) {
                                const branchAlloc = allocData.allocations.find(a => a.family_code === item.family_code);
                                if (branchAlloc) {
                                    thisBranchQty = parseInt(branchAlloc.quantity) || 0;
                                }
                            }
                            
                            // Other branches allocated = total allocated - this branch
                            const otherBranchAllocated = totalAllocated - thisBranchQty;
                            const qtyLeft = totalQty - otherBranchAllocated;
                            
                            const row = document.createElement('tr');
                            row.setAttribute('data-family-code', item.family_code);
                            row.setAttribute('data-total-qty', totalQty);
                            row.setAttribute('data-other-allocated', otherBranchAllocated);
                            row.innerHTML = `
                                <td style="font-weight:600; text-align: left;">${item.family_code}</td>
                                <td class="edit-item-total-qty">${totalQty}</td>
                                <td class="edit-item-other-allocated">${otherBranchAllocated}</td>
                                <td class="edit-item-qty-left">${qtyLeft}</td>
                            `;
                            editItemInfoBody.appendChild(row);
                        });
                    } else {
                        editItemInfoBody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#999; padding:20px;">No items found</td></tr>';
                    }

                    // Populate Allocated Items table
                    if (allocData.success && allocData.allocations.length > 0) {
                        editBody.innerHTML = '';
                        allocData.allocations.forEach(allocation => {
                            const row = document.createElement('tr');
                            row.setAttribute('data-allocation-id', allocation.id);
                            row.setAttribute('data-family-code', allocation.family_code);
                            row.setAttribute('data-original-qty', allocation.quantity);
                            row.innerHTML = `
                                <td style="font-weight:600; text-align: left;">${allocation.family_code}</td>
                                <td>
                                    <input type="number" min="0" 
                                           value="${allocation.quantity}" 
                                           class="edit-allocation-qty" 
                                           style="width:100%; padding:6px 8px; border:1px solid #ddd; border-radius:4px; font-size:13px;"
                                           data-original-qty="${allocation.quantity}"
                                           onchange="updateEditQuantityLeft()">
                                </td>
                                <td><button class="btn-remove-allocation" onclick="removeEditAllocationRow(this)">Remove</button></td>
                            `;
                            editBody.appendChild(row);
                        });
                    } else {
                        editBody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#999; padding:20px;">No allocations found for this branch</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    editItemInfoBody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red; padding:20px;">Error loading data</td></tr>';
                    editBody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:red; padding:20px;">Error loading allocations</td></tr>';
                });
        }

        function updateEditQuantityLeft() {
            const allocationRows = document.querySelectorAll('#editAllocationBody tr');
            
            allocationRows.forEach(allocationRow => {
                const familyCode = allocationRow.getAttribute('data-family-code');
                if (!familyCode) return; // Skip loading/error rows
                
                const input = allocationRow.querySelector('.edit-allocation-qty');
                if (!input) return;
                
                const newQty = parseInt(input.value) || 0;
                const originalQty = parseInt(allocationRow.getAttribute('data-original-qty')) || 0;
                
                // Find corresponding item info row
                const itemInfoRow = document.querySelector(`#editItemInfoBody tr[data-family-code="${familyCode}"]`);
                if (itemInfoRow) {
                    const totalQty = parseInt(itemInfoRow.getAttribute('data-total-qty')) || 0;
                    const otherAllocated = parseInt(itemInfoRow.getAttribute('data-other-allocated')) || 0;
                    
                    // Calculate quantity left: total - other branches - this new quantity
                    const qtyLeft = totalQty - otherAllocated - newQty;
                    
                    const qtyLeftCell = itemInfoRow.querySelector('.edit-item-qty-left');
                    qtyLeftCell.textContent = qtyLeft;
                    
                    // Change color if over-allocated
                    if (qtyLeft < 0) {
                        qtyLeftCell.style.color = 'red';
                        qtyLeftCell.style.fontWeight = 'bold';
                    } else {
                        qtyLeftCell.style.color = '#333';
                        qtyLeftCell.style.fontWeight = 'normal';
                    }
                }
            });
        }

        function closeEditAllocationModal() {
            document.getElementById('editAllocationModal').style.display = 'none';
        }

        function removeEditAllocationRow(button) {
            const row = button.closest('tr');
            const familyCode = row.getAttribute('data-family-code') || row.querySelector('td:first-child').textContent;

            if (confirm('Are you sure you want to remove allocation for ' + familyCode + '?')) {
                // Mark row for deletion
                row.setAttribute('data-action', 'remove');
                row.style.opacity = '0.5';
                row.style.textDecoration = 'line-through';

                // Disable input and change button to undo
                const input = row.querySelector('.edit-allocation-qty');
                if (input) {
                    input.disabled = true;
                    input.value = 0; // Set to 0 to update quantity left
                }

                button.textContent = 'Undo';
                button.classList.remove('btn-remove-allocation');
                button.classList.add('btn-add-allocation');
                button.onclick = function () { undoRemoveAllocationRow(this); };
                
                // Update item information table
                updateEditQuantityLeft();
            }
        }

        function undoRemoveAllocationRow(button) {
            const row = button.closest('tr');

            // Remove delete mark
            row.removeAttribute('data-action');
            row.style.opacity = '1';
            row.style.textDecoration = 'none';

            // Re-enable input and restore original value
            const input = row.querySelector('.edit-allocation-qty');
            if (input) {
                input.disabled = false;
                const originalQty = input.getAttribute('data-original-qty');
                input.value = originalQty;
            }

            button.textContent = 'Remove';
            button.classList.remove('btn-add-allocation');
            button.classList.add('btn-remove-allocation');
            button.onclick = function () { removeEditAllocationRow(this); };
            
            // Update item information table
            updateEditQuantityLeft();
        }

        function updateAllocation() {
            const branchName = document.getElementById('editModalBranchName').textContent;
            const rows = document.querySelectorAll('#editAllocationBody tr');

            // Check if no data rows
            if (rows.length === 0 || (rows.length === 1 && rows[0].querySelector('td').getAttribute('colspan'))) {
                alert('No allocations to update!');
                return;
            }

            let allocations = [];
            let isValid = true;
            let hasOverAllocation = false;

            rows.forEach(row => {
                const allocationId = row.getAttribute('data-allocation-id');
                const familyCode = row.getAttribute('data-family-code');
                const action = row.getAttribute('data-action') || 'update';
                const qtyInput = row.querySelector('.edit-allocation-qty');

                if (!familyCode) return; // Skip empty rows

                if (action === 'remove') {
                    // Mark for deletion
                    allocations.push({
                        id: parseInt(allocationId),
                        family_code: familyCode,
                        action: 'remove'
                    });
                } else if (qtyInput) {
                    const qty = parseInt(qtyInput.value) || 0;

                    if (qty <= 0) {
                        alert('Quantity for ' + familyCode + ' must be greater than 0!');
                        isValid = false;
                        return;
                    }

                    // Check if this allocation would exceed available quantity
                    const itemInfoRow = document.querySelector(`#editItemInfoBody tr[data-family-code="${familyCode}"]`);
                    if (itemInfoRow) {
                        const qtyLeftCell = itemInfoRow.querySelector('.edit-item-qty-left');
                        const qtyLeft = parseInt(qtyLeftCell.textContent) || 0;
                        
                        if (qtyLeft < 0) {
                            hasOverAllocation = true;
                        }
                    }

                    allocations.push({
                        id: parseInt(allocationId),
                        family_code: familyCode,
                        quantity: qty,
                        action: 'update'
                    });
                }
            });

            if (!isValid) return;
            
            if (hasOverAllocation) {
                alert('One or more items have allocated quantity exceeding available quantity (shown in red in Item Information table)!');
                return;
            }

            if (allocations.length === 0) {
                alert('No changes to save!');
                return;
            }

            // Get PO ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            const poId = urlParams.get('id');

            if (!poId) {
                alert('Invalid purchase order ID!');
                return;
            }

            // Disable button and show loading state
            const updateButton = document.querySelector('#editAllocationModal .btn-set-allocation');
            const originalText = updateButton.textContent;
            updateButton.disabled = true;
            updateButton.textContent = 'Updating...';

            // Prepare form data
            const formData = new FormData();
            formData.append('po_id', poId);
            formData.append('branch_name', branchName);
            formData.append('allocations', JSON.stringify(allocations));

            // Send to backend
            fetch('update_branch_allocations.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        // Reload page to show updated allocations
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Could not update allocations.'));
                        // Re-enable button
                        updateButton.disabled = false;
                        updateButton.textContent = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error: ' + error.message);
                    // Re-enable button
                    updateButton.disabled = false;
                    updateButton.textContent = originalText;
                });
        }

        function removeAllocation(branch) {
            if (confirm('Are you sure you want to remove ALL allocations for ' + branch + '?\n\nThis will delete all items allocated to this branch.')) {
                // Get PO ID from URL
                const urlParams = new URLSearchParams(window.location.search);
                const poId = urlParams.get('id');

                if (!poId) {
                    alert('Invalid purchase order ID!');
                    return;
                }

                // Prepare form data
                const formData = new FormData();
                formData.append('po_id', poId);
                formData.append('branch_name', branch);

                // Send delete request
                fetch('delete_branch_allocation.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            // Reload page to show updated data
                            window.location.reload();
                        } else {
                            alert('Error: ' + (data.message || 'Could not remove allocations.'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Network error: ' + error.message);
                    });
            }
        }
    </script>
</body>

</html>