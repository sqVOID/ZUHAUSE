<?php
require_once 'session_check.php';

$toast_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $toast_message = 'Promo registered successfully!';
} elseif (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $toast_message = 'Promo updated successfully!';
} elseif (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $toast_message = 'Promo deleted successfully!';
}

include 'config.php';

// Auto-create promos table
$conn->query("CREATE TABLE IF NOT EXISTS promos (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    promo_name VARCHAR(255) NOT NULL,
    discount_type VARCHAR(50) NOT NULL DEFAULT 'Free',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Auto-migrate: add columns if they don't exist yet
$migrate_cols = [
    'discount_value' => "ALTER TABLE promos ADD COLUMN discount_value DECIMAL(10,2) DEFAULT 0.00 AFTER discount_type",
    'motor_model' => "ALTER TABLE promos ADD COLUMN motor_model VARCHAR(255) AFTER end_date",
    'brand' => "ALTER TABLE promos ADD COLUMN brand VARCHAR(100) AFTER motor_model",
    'free_item' => "ALTER TABLE promos ADD COLUMN free_item TEXT AFTER brand",
    'branch' => "ALTER TABLE promos ADD COLUMN branch TEXT AFTER free_item",
    'usage_limit' => "ALTER TABLE promos ADD COLUMN usage_limit INT(11) DEFAULT NULL AFTER branch",
    'usage_count' => "ALTER TABLE promos ADD COLUMN usage_count INT(11) DEFAULT 0 AFTER usage_limit",
];
foreach ($migrate_cols as $col => $alter_sql) {
    $chk = $conn->query("SHOW COLUMNS FROM promos LIKE '$col'");
    if ($chk && $chk->num_rows == 0) {
        $conn->query($alter_sql);
    }
}

// Auto-create promo_items table (line items for each promo)
$conn->query("CREATE TABLE IF NOT EXISTS promo_items (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    promo_id INT(11) NOT NULL,
    motor_model VARCHAR(255) DEFAULT '',
    discount_type VARCHAR(50) DEFAULT 'Free',
    discount_value DECIMAL(10,2) DEFAULT 0.00,
    promo_item VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (promo_id) REFERENCES promos(id) ON DELETE CASCADE
)");

// Migrate existing promo_items table
$chk_type = $conn->query("SHOW COLUMNS FROM promo_items LIKE 'discount_type'");
if ($chk_type && $chk_type->num_rows == 0) {
    $conn->query("ALTER TABLE promo_items ADD COLUMN discount_type VARCHAR(50) DEFAULT 'Free' AFTER motor_model");
    $conn->query("ALTER TABLE promo_items ADD COLUMN discount_value DECIMAL(10,2) DEFAULT 0.00 AFTER discount_type");
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $conn->query("DELETE FROM promo_items WHERE promo_id = $id");
    if ($conn->query("DELETE FROM promos WHERE id = $id") === TRUE) {
        header("Location: promoreg.php?deleted=1");
        exit();
    }
}

// Handle Status Toggle
if (isset($_GET['status_update'])) {
    $id = (int) $_GET['id'];
    $new = ($_GET['status'] === 'true') ? 'Active' : 'Deactivated';
    $conn->query("UPDATE promos SET status='$new' WHERE id=$id");
    header("Location: promoreg.php");
    exit();
}

$editMode = false;
$editData = null;
$editItems = [];

if (isset($_GET['edit'])) {
    $editMode = true;
    $id = (int) $_GET['edit'];
    $r = $conn->query("SELECT * FROM promos WHERE id=$id");
    if ($r && $r->num_rows > 0) {
        $editData = $r->fetch_assoc();
    }
    // Load existing promo items
    $ri = $conn->query("SELECT * FROM promo_items WHERE promo_id=$id ORDER BY id ASC");
    if ($ri && $ri->num_rows > 0) {
        while ($pi = $ri->fetch_assoc()) {
            $editItems[] = $pi;
        }
    }
}

// Handle Save / Update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_promo'])) {
    $promo_name   = $conn->real_escape_string(strtoupper(trim($_POST['promo_name'])));
    $start_date   = $conn->real_escape_string(trim($_POST['start_date'] ?? ''));
    $end_date     = $conn->real_escape_string(trim($_POST['end_date'] ?? ''));
    $branch_raw   = isset($_POST['branch']) && is_array($_POST['branch']) ? implode(', ', $_POST['branch']) : '';
    $branch       = $conn->real_escape_string($branch_raw);
    $usage_limit  = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : NULL;

    // Decode promo items JSON
    $promo_items_json = $_POST['promo_items_json'] ?? '[]';
    $promo_items_arr  = json_decode($promo_items_json, true) ?? [];

    // Derive legacy single fields from first item (backward compat)
    $motor_model    = $conn->real_escape_string($promo_items_arr[0]['motor_model'] ?? '');
    $free_item      = $conn->real_escape_string($promo_items_arr[0]['promo_item']  ?? '');
    $discount_type  = $conn->real_escape_string($promo_items_arr[0]['discount_type']  ?? 'Free');
    $discount_value = floatval(str_replace(',', '', $promo_items_arr[0]['discount_value'] ?? 0));
    if ($discount_type === 'Free') $discount_value = 0;
    $brand = '';

    if (isset($_POST['promo_id']) && !empty($_POST['promo_id'])) {
        $pid = (int) $_POST['promo_id'];
        $usage_limit_sql = $usage_limit !== NULL ? "'$usage_limit'" : "NULL";
        $sql = "UPDATE promos SET
                    promo_name='$promo_name',
                    discount_type='$discount_type',
                    discount_value='$discount_value',
                    start_date='$start_date',
                    end_date='$end_date',
                    motor_model='$motor_model',
                    brand='$brand',
                    free_item='$free_item',
                    branch='$branch',
                    usage_limit=$usage_limit_sql
                WHERE id=$pid";
        if ($conn->query($sql)) {
            // Replace promo_items
            $conn->query("DELETE FROM promo_items WHERE promo_id=$pid");
            foreach ($promo_items_arr as $pi) {
                $mm = $conn->real_escape_string(trim($pi['motor_model'] ?? ''));
                $dt = $conn->real_escape_string(trim($pi['discount_type'] ?? 'Free'));
                $dv = floatval(str_replace(',', '', $pi['discount_value'] ?? 0));
                if ($dt === 'Free') $dv = 0;
                $fi = $conn->real_escape_string(trim($pi['promo_item'] ?? ''));
                $conn->query("INSERT INTO promo_items (promo_id, motor_model, discount_type, discount_value, promo_item) VALUES ($pid,'$mm','$dt','$dv','$fi')");
            }
            header("Location: promoreg.php?updated=1");
            exit();
        } else {
            $message = "Error: " . $conn->error;
            $messageType = "error";
        }
    } else {
        $usage_limit_sql = $usage_limit !== NULL ? "'$usage_limit'" : "NULL";
        $sql = "INSERT INTO promos (promo_name, discount_type, discount_value, start_date, end_date, motor_model, brand, free_item, branch, usage_limit, usage_count, status)
                VALUES ('$promo_name','$discount_type','$discount_value','$start_date','$end_date','$motor_model','$brand','$free_item','$branch',$usage_limit_sql,0,'Active')";
        if ($conn->query($sql)) {
            $new_pid = $conn->insert_id;
            foreach ($promo_items_arr as $pi) {
                $mm = $conn->real_escape_string(trim($pi['motor_model'] ?? ''));
                $dt = $conn->real_escape_string(trim($pi['discount_type'] ?? 'Free'));
                $dv = floatval(str_replace(',', '', $pi['discount_value'] ?? 0));
                if ($dt === 'Free') $dv = 0;
                $fi = $conn->real_escape_string(trim($pi['promo_item'] ?? ''));
                $conn->query("INSERT INTO promo_items (promo_id, motor_model, discount_type, discount_value, promo_item) VALUES ($new_pid,'$mm','$dt','$dv','$fi')");
            }
            header("Location: promoreg.php?success=1");
            exit();
        } else {
            $message = "Error: " . $conn->error;
            $messageType = "error";
        }
    }
}

// Fetch motors (items) for Motor Model dropdown — all active items
$motors_result = $conn->query("SELECT id, item_code, description, brand FROM items WHERE status='Active' ORDER BY description ASC");

// Fetch ALL active items for the Free Item dropdown (motors, accessories, etc.)
$free_items_result = $conn->query("SELECT id, description, brand, group_name, department FROM items WHERE status='Active' ORDER BY group_name, description ASC");

// Fetch branches
$branches_result = $conn->query("SELECT * FROM branches WHERE status='Active' ORDER BY branch_name ASC");
$branches_list = [];
if ($branches_result && $branches_result->num_rows > 0) {
    while ($b = $branches_result->fetch_assoc()) {
        $branches_list[] = $b;
    }
}

// Fetch all promos for list
$promos_result = $conn->query("SELECT * FROM promos ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
        <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <title>Promo Registration</title>
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

        /* ===== HEADER ===== */
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

        /* ===== MENU BTN ===== */
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

        /* ===== SIDEBAR ===== */
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

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* ===== BUTTONS ===== */
        .btn-add-promo {
            padding: 10px 24px;
            border: none;
            background: var(--color-gold);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-add-promo:hover {
            background: var(--color-gold-light);
        }

        .btn-cancel {
            padding: 8px 20px;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-save {
            padding: 8px 20px;
            border: none;
            background: var(--color-gold);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-save:hover {
            background: var(--color-gold-light);
        }

        .btn-delete-form {
            padding: 8px 20px;
            border: none;
            background: #d32f2f;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-delete-form:hover {
            background: #b71c1c;
        }

        .btn-edit {
            padding: 5px 14px;
            border: none;
            background: var(--color-navy);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }

        .btn-deactivate {
            padding: 5px 14px;
            border: none;
            background: var(--color-gold);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-deactivate.inactive {
            background: #9e9e9e;
        }

        .btn-del {
            padding: 5px 14px;
            border: none;
            background: #e53935;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        /* ===== FORM CONTAINER ===== */
        .form-container {
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .form-container.hidden {
            display: none;
        }


        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: white;
            width: 100%;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2196F3;
        }

        .form-group input[readonly] {
            background: #f5f5f5;
            color: #666;
            cursor: not-allowed;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 70px;
        }

        .discount-value-row {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .discount-value-row .form-group {
            flex: 1;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 16px;
        }

        /* ===== TABLE ===== */
        .table-container {
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 16px;
            gap: 10px;
        }

        .table-header-row h3 {
            font-size: 15px;
            font-weight: 700;
            color: #333;
        }

        .search-box {
            position: relative;
            width: 220px;
        }

        .search-box input {
            width: 100%;
            padding: 7px 32px 7px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }

        .search-box svg {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            fill: #999;
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
            padding: 10px 8px;
            font-size: 12px;
            font-weight: 700;
            color: #000;
            border: 1px solid #ccc;
        }

        td {
            padding: 10px 8px;
            font-size: 12px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
        }

        tbody tr:hover {
            background: #fafafa;
        }

        .status-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }

        .status-badge.active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.inactive {
            background: #ffebee;
            color: #c62828;
        }

        .btn-view {
            background-color: #2196F3;
            color: #fff;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .btn-view:hover {
            background-color: #1976D2;
        }

        .action-cell {
            display: flex;
            justify-content: center;
            gap: 5px;
        }

        /* ===== TOAST ===== */
        .toast-notification {
            position: fixed;
            top: 80px;
            right: 30px;
            background: var(--color-gold);
            color: white;
            padding: 14px 24px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            opacity: 1;
            transition: opacity 0.6s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toast-notification.hide {
            opacity: 0;
            pointer-events: none;
        }

        /* ===== DISCOUNT VALUE TOGGLE ===== */
        #discountValueGroup {
            display: none;
        }

        /* ===== CUSTOM SCROLLABLE DROPDOWN (Free Item) ===== */
        .custom-single-dropdown {
            position: relative;
            width: 100%;
        }
        .custom-single-display {
            padding: 8.5px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .custom-single-display .display-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 10px;
        }
        .custom-single-display::after {
            content: '\25BC';
            font-size: 10px;
            color: #999;
        }
        .custom-single-list {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            border: 1px solid #ddd;
            max-height: 250px;
            overflow-y: auto;
            overflow-x: auto; /* ENABLES HORIZONTAL SCROLLBAR */
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            border-radius: 0 0 4px 4px;
        }
        .custom-single-list.open {
            display: block;
        }
        .custom-single-item {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 13px;
            white-space: nowrap; /* Forces text to stay on one line so horizontal scroll works */
            color: #333;
        }
        .custom-single-item:hover, .custom-single-item.selected {
            background-color: #f5f5f5;
            color: #2196F3;
        }
        .custom-single-group {
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 700;
            color: #777;
            background-color: #f9f9f9;
            text-transform: uppercase;
        }

        /* ===== BRANCH CHECKBOX DROPDOWN ===== */
        .branch-chk-dropdown {
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
        }
        .branch-chk-dropdown:hover { border-color: #2196F3; }
        .branch-chk-arrow { font-size: 10px; color: #999; }
        .branch-chk-list {
            display: none;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            max-height: 220px;
            overflow-y: auto;
            margin-top: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            z-index: 100;
        }
        .branch-chk-list.open { display: block; }
        .branch-chk-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 13px;
            color: #333;
        }
        .branch-chk-item:hover { background: #f5f5f5; }
        .branch-tag {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 500;
            margin: 1px;
        }

        /* Search Modal Styles */
        .btn-search-item {
            padding: 9px 24px;
            background-color: var(--color-navy);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
        }

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
            max-width: 800px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            font-size: 18px;
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
            border: 1px solid #ccc;
        }
        .search-results-table thead {
            background: var(--color-gold-pale);
        }
        .search-results-table th, .search-results-table td {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            border: 1px solid #ccc;
        }
        .search-results-table tr:hover {
            background-color: #f5f5f5;
        }

        .btn-select {
            background-color: var(--color-gold);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-select:hover { background-color: var(--color-gold-light); }

        .modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-start;
        }
        .btn-back-modal {
            padding: 8px 24px;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
        }
        .btn-back-modal:hover { background-color: #f5f5f5; }

        /* ===== PROMO ITEM SECTION ===== */
        .promo-item-section {
            margin-top: 20px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.07);
            padding: 18px 20px;
        }
        .promo-item-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .promo-item-section-header h3 {
            font-size: 14px;
            font-weight: 700;
            color: #222;
        }
        .btn-add-promo-item {
            padding: 7px 16px;
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
        .btn-add-promo-item:hover { background: var(--color-gold-light); }

        .promo-item-empty {
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            padding: 36px 20px;
            text-align: center;
            color: #888;
            font-size: 13px;
            background: #fefefe;
        }
        .promo-items-table-wrapper { overflow-x: auto; }
        .promo-items-table {
            width: 100%;
            border-collapse: collapse;
            display: none;
        }
        .promo-items-table thead { background: var(--color-gold-pale); }
        .promo-items-table th {
            text-align: center;
            padding: 11px 10px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border: 1px solid #ccc;
        }
        .promo-items-table td {
            padding: 10px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            text-align: center;
        }
        .promo-items-table tbody tr:hover { background: #fafafa; }
        .promo-items-table td .row-input {
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 13px;
            width: calc(100% - 70px);
            outline: none;
            font-family: Arial, sans-serif;
            background: white;
        }
        .promo-items-table td .row-input:focus { border-color: var(--color-gold); }
        .promo-items-table td .btn-row-search {
            padding: 6px 10px;
            background: var(--color-navy);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-left: 4px;
        }
        .promo-items-table td .btn-row-search:hover { background: var(--color-navy-dark); }
        .promo-row-cell { display: flex; align-items: center; gap: 4px; }
        .btn-remove-promo-row {
            background: #c62828;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
        }
        .btn-remove-promo-row:hover { background: #b71c1c; }

        /* Branches Table Styles */
        .branches-table {
            width: 100%;
            border-collapse: collapse;
        }

        .branches-table thead {
            background: var(--color-gold-pale);
        }

        .branches-table th {
            text-align: center;
            padding: 10px 12px;
            font-weight: 600;
            font-size: 13px;
            color: var(--color-navy);
            border-bottom: 2px solid var(--color-gold);
        }

        .branches-table th:nth-child(2) {
            text-align: left;
        }

        .branches-table td {
            padding: 8px 12px;
            font-size: 13px;
            color: #333;
            border-bottom: 1px solid #eee;
        }

        .branches-table tbody tr:hover {
            background: #fdf8f3;
        }

        .branches-table .area-cell {
            font-weight: 600;
            background-color: #f9f9f9;
            vertical-align: top;
        }

        .branches-table .checkbox-cell {
            text-align: center;
            width: 60px;
            vertical-align: top;
        }

        .branches-table .checkbox-cell input[type="checkbox"] {
            margin: 0 auto;
            display: block;
        }

        .branches-table .branch-cell {
            padding-left: 20px;
        }

        .btn-internal-apply {
            background: var(--color-gold);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-internal-apply:hover {
            background: var(--color-gold-light);
        }

        /* Modal Navigation Buttons */
        .btn-modal-nav {
            padding: 0;
            height: 36px;
            width: 100px;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }

        .btn-back {
            border: 1px solid #bbb;
            background: white;
            color: #333;
        }

        .btn-back:hover {
            background: #f5f5f5;
        }

        .btn-next {
            border: none;
            background: var(--color-gold);
            color: white;
            transition: background-color 0.2s;
        }

        .btn-next:hover {
            background: var(--color-gold-light);
        }

        /* Modal Base Styles */
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
            font-family: Arial, sans-serif;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            border: 1px solid #888;
            width: 90%;
            max-width: 1000px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            padding: 20px 0;
            border-bottom: 1px solid #eee;
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            color: #000;
        }

        .modal-body {
            padding: 20px 30px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>

<body>

    <?php if (!empty($toast_message)): ?>
        <div class="toast-notification" id="toastMsg">
            <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:white;flex-shrink:0;">
                <path
                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
            </svg>
            <?php echo htmlspecialchars($toast_message); ?>
        </div>
        <script>
            setTimeout(function () { var t = document.getElementById('toastMsg'); if (t) t.classList.add('hide'); }, 3000);
            setTimeout(function () { var t = document.getElementById('toastMsg'); if (t) t.remove(); }, 3700);
        </script>
    <?php endif; ?>

    <!-- HEADER -->
    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()">
            <span></span><span></span><span></span>
        </div>
        <?php include '_header_user.php'; ?>
    </div>

    <!-- SIDEBAR -->
    <?php include '_sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="main-content" id="mainContent">

        <!-- Page Header -->
        <div class="content-header"
            style="display:flex;flex-direction:column;align-items:flex-start;gap:15px;margin-bottom:20px;">
            <h2 style="font-size:20px;font-weight:600;color:#333;margin:0;">Promo Registration</h2>
            <button class="btn-add-promo" id="btnAddPromo" onclick="openForm()">Add Promo</button>
            <button class="btn-add-promo" id="btnCloseForm" style="background:#555;display:none;"
                onclick="closeForm()">Close Form</button>
        </div>

        <?php if (!empty($message)): ?>
            <div style="padding:12px;margin-bottom:16px;border-radius:4px;
        background:<?php echo $messageType == 'error' ? '#ffebee' : '#e8f5e9'; ?>;
        color:<?php echo $messageType == 'error' ? '#c62828' : '#2e7d32'; ?>;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- FORM CONTAINER -->
        <div id="formContainer" class="form-container <?php echo $editMode ? '' : 'hidden'; ?>">
            <form method="POST" action="">
                <?php if ($editMode && $editData): ?>
                    <input type="hidden" name="promo_id" value="<?php echo $editData['id']; ?>">
                <?php endif; ?>

                <!-- Row 1: Promo Name | Branch -->
                <div class="form-grid">
                    <div class="form-group">
                        <label>Promo Name</label>
                        <input type="text" name="promo_name" placeholder="Enter promo name"
                            oninput="this.value = this.value.toUpperCase()"
                            value="<?php echo $editMode ? htmlspecialchars($editData['promo_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Branch</label>
                        <?php
                        $saved_branches = [];
                        if ($editMode && !empty($editData['branch'])) {
                            $saved_branches = array_map('trim', explode(',', $editData['branch']));
                        }
                        ?>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="selectedBranchesDisplay" readonly placeholder="Select Branch"
                                onclick="openBranchesModal()"
                                value="<?php echo !empty($saved_branches) ? htmlspecialchars(implode(', ', $saved_branches)) : ''; ?>"
                                style="padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; color: #333; background: white; cursor: pointer; flex-grow: 1;">
                            <button type="button" class="btn-internal-apply" onclick="openBranchesModal()">Select</button>
                        </div>
                    </div>
                </div>                <!-- Row 2: Start Date | End Date -->
                <div class="form-grid">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" required
                            value="<?php echo $editMode ? htmlspecialchars($editData['start_date']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" required
                            value="<?php echo $editMode ? htmlspecialchars($editData['end_date']) : ''; ?>">
                    </div>
                </div>

                <!-- Row 3: Usage Limit -->
                <div class="form-grid">
                    <div class="form-group">
                        <label>Usage Limit <span style="color: #999; font-size: 12px; font-weight: 400;">(Optional - Leave blank for unlimited)</span></label>
                        <input type="number" name="usage_limit" min="1" placeholder="e.g., 20"
                            value="<?php echo $editMode && !empty($editData['usage_limit']) ? htmlspecialchars($editData['usage_limit']) : ''; ?>">
                        <!--
                            <small style="color: #666; font-size: 11px; margin-top: 5px; display: block;">
                            ℹ️ When limit is reached, the promo will automatically be deactivated
                        </small>
                            -->
                    </div>
                    <?php if ($editMode && isset($editData['usage_count'])): ?>
                    <div class="form-group">
                        <label>Current Usage Count</label>
                        <input type="text" readonly 
                            value="<?php echo htmlspecialchars($editData['usage_count']); ?> / <?php echo !empty($editData['usage_limit']) ? htmlspecialchars($editData['usage_limit']) : 'Unlimited'; ?>">
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Promo Item Section (like Item Section in createpurchaseorder) -->
                <div class="promo-item-section">
                    <div class="promo-item-section-header">
                        <h3>Promo Item</h3>
                        <button type="button" class="btn-add-promo-item" id="addPromoItemBtn" onclick="addPromoItemRow()">+ Add Promo Item</button>
                    </div>

                    <div id="promo-item-empty" class="promo-item-empty">
                        No promo item added yet. Click "Add Promo Item" to start.
                    </div>

                    <div class="promo-items-table-wrapper">
                        <table class="promo-items-table" id="promo-items-table">
                            <thead>
                                <tr>
                                    <th style="width:40px;">No.</th>
                                    <th>Model</th>
                                    <th style="width:230px;">Discount Type</th>
                                    <th>Promo Item</th>
                                    <th style="width:80px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="promo-items-tbody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Hidden field to carry promo items as JSON -->
                <input type="hidden" name="promo_items_json" id="promoItemsJson" value="[]">

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeForm()">Cancel</button>
                    <?php if ($editMode && $editData): ?>
                        <button type="button" class="btn-delete-form"
                            onclick="confirmDelete(<?php echo $editData['id']; ?>)">Delete</button>
                    <?php endif; ?>
                    <button type="submit" name="save_promo" class="btn-save">
                        <?php echo $editMode ? 'Update' : 'Save'; ?>
                    </button>
                </div>
            </form>

            <!-- Branches Modal -->
            <div id="branchesModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        Select Branches
                    </div>
                    <div class="modal-body">
                        <div style="margin-bottom: 15px;">
                            <input type="text" id="branchSearchInput" placeholder="Search branches..."
                                onkeyup="filterBranches()"
                                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
                        </div>
                        <div style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <label
                                style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" id="selectAllBranchesModal" style="width: 18px; height: 18px;"
                                    onchange="toggleBranchModalSelectAll()">
                                Select All
                            </label>
                        </div>
                        <div id="branchListContainer">
                            <?php
                            if (count($branches_list) > 0) {
                                $branches_by_area = [];

                                foreach ($branches_list as $row) {
                                    $area = !empty($row['area']) ? $row['area'] : 'Uncategorized';
                                    $branches_by_area[$area][] = $row;
                                }
                                ksort($branches_by_area);

                                echo '<table class="branches-table">';
                                echo '<thead>';
                                echo '<tr>';
                                echo '<th style="width: 60px; text-align: center;"></th>';
                                echo '<th>Area</th>';
                                echo '<th>Branch</th>';
                                echo '</tr>';
                                echo '</thead>';
                                echo '<tbody>';

                                foreach ($branches_by_area as $area => $area_branches) {
                                    $area_display = htmlspecialchars(ucwords(str_replace('_', ' ', $area)));
                                    $branch_count = count($area_branches);
                                    
                                    foreach ($area_branches as $index => $row) {
                                        echo '<tr class="branch-row" data-area="' . htmlspecialchars($area) . '">';
                                        
                                        // First row of each area: show area checkbox and name with rowspan
                                        if ($index === 0) {
                                            echo '<td class="checkbox-cell" style="vertical-align: middle;" rowspan="' . $branch_count . '">';
                                            echo '<input type="checkbox" class="area-select-all" data-area="' . htmlspecialchars($area) . '" style="width:16px;height:16px;" onchange="toggleAreaSelectAll(this)">';
                                            echo '</td>';
                                            echo '<td class="area-cell" style="vertical-align: middle;" rowspan="' . $branch_count . '">' . $area_display . '</td>';
                                            
                                            
                                        }
                                        
                                        // Branch cell
                                        $checked = in_array($row['branch_name'], $saved_branches) ? 'checked' : '';
                                        $display_name = htmlspecialchars($row['branch_name']);
                                        if (!empty($row['branch_code'])) {
                                            $display_name .= " - " . htmlspecialchars($row['branch_code']);
                                        }
                                        
                                        echo '<td class="branch-cell">';
                                        echo '<label style="display:flex; align-items:center; gap:10px; cursor:pointer;">';
                                        echo '<input type="checkbox" name="branch[]" class="branch-checkbox ' . htmlspecialchars($area) . '-checkbox" value="' . htmlspecialchars($row['branch_name']) . '" ' . $checked . ' style="width: 16px; height: 16px;">';
                                        echo '<span>' . $display_name . '</span>';
                                        echo '</label>';
                                        echo '</td>';
                                        
                                        echo '</tr>';
                                    }
                                }

                                echo '</tbody>';
                                echo '</table>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modal-nav btn-back"
                            onclick="closeBranchesModal()">Cancel</button>
                        <button type="button" class="btn-modal-nav btn-next"
                            onclick="applyBranchesSelection()">Done</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="table-container">
            <div class="table-header-row">
                <h3>Promo List</h3>
                <div class="search-box">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                    </svg>
                    <input type="text" id="searchInput" placeholder="Search.." onkeyup="searchTable()">
                </div>
            </div>

            <table id="promoTable">
                <thead>
                    <tr>
                        <th>Promo Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Model</th>
                        <th>Discount Type</th>
                        <th>Promo Item</th>
                        <th>Branch</th>
                        <th>Usage</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($promos_result && $promos_result->num_rows > 0): ?>
                        <?php while ($row = $promos_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['promo_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($row['end_date']); ?></td>
                                <?php
                                $sub_pid = (int) $row['id'];
                                $pi_res = $conn->query("SELECT * FROM promo_items WHERE promo_id=$sub_pid ORDER BY id ASC");
                                $pi_list = [];
                                if ($pi_res && $pi_res->num_rows > 0) {
                                    while ($pi = $pi_res->fetch_assoc()) {
                                        $pi_list[] = $pi;
                                    }
                                }
                                ?>
                                <td style="padding:0;">
                                    <?php if(count($pi_list) > 0): ?>
                                        <?php foreach($pi_list as $index => $pi): ?>
                                            <div style="padding:10px; <?php echo ($index < count($pi_list) - 1) ? 'border-bottom:1px solid #eee;' : ''; ?>">
                                                <?php echo htmlspecialchars($pi['motor_model'] ?: '—'); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="padding:10px;">
                                            <?php echo htmlspecialchars($row['motor_model'] ?: '—'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:0;">
                                    <?php if(count($pi_list) > 0): ?>
                                        <?php foreach($pi_list as $index => $pi): ?>
                                            <div style="padding:10px; <?php echo ($index < count($pi_list) - 1) ? 'border-bottom:1px solid #eee;' : ''; ?>">
                                                <?php
                                                if ($pi['discount_type'] === 'Free') {
                                                    echo '<span style="color:#2e7d32;font-weight:600;">Free</span>';
                                                } elseif ($pi['discount_type'] === '%') {
                                                    echo number_format($pi['discount_value'], 0) . '%';
                                                } else {
                                                    echo htmlspecialchars($pi['discount_type']) . ' ' . number_format($pi['discount_value'], 0);
                                                }
                                                ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="padding:10px;">
                                            <?php
                                            $dt = $row['discount_type'];
                                            if ($dt === 'Free') echo '<span style="color:#2e7d32;font-weight:600;">Free</span>';
                                            elseif ($dt === '%') echo number_format($row['discount_value'], 0) . '%';
                                            else echo htmlspecialchars($dt) . ' ' . number_format($row['discount_value'], 0);
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:0;">
                                    <?php if(count($pi_list) > 0): ?>
                                        <?php foreach($pi_list as $index => $pi): ?>
                                            <div style="padding:10px; <?php echo ($index < count($pi_list) - 1) ? 'border-bottom:1px solid #eee;' : ''; ?>">
                                                <?php echo htmlspecialchars($pi['promo_item'] ?: '—'); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="padding:10px;">
                                            <?php echo !empty($row['free_item']) ? htmlspecialchars($row['free_item']) : '—'; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['branch'])): ?>
                                        <button class="btn-view" onclick="viewBranches('<?php echo htmlspecialchars(addslashes($row['branch'])); ?>')">View</button>
                                    <?php else: ?>
                                        <span style="color:#999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $usage_count = isset($row['usage_count']) ? (int)$row['usage_count'] : 0;
                                    $usage_limit = isset($row['usage_limit']) && !empty($row['usage_limit']) ? (int)$row['usage_limit'] : null;
                                    
                                    if ($usage_limit !== null) {
                                        $percentage = ($usage_limit > 0) ? round(($usage_count / $usage_limit) * 100) : 0;
                                        $color = $percentage >= 100 ? '#d32f2f' : ($percentage >= 80 ? '#ff9800' : '#2e7d32');
                                        echo '<span style="color:' . $color . '; font-weight:600;">' . $usage_count . ' / ' . $usage_limit . '</span>';
                                        if ($percentage >= 100) {
                                            echo '<br><span style="color:#d32f2f; font-size:10px; font-weight:600;">LIMIT REACHED</span>';
                                        }
                                    } else {
                                        echo '<span style="color:#999;">' . $usage_count . ' / Unlimited</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span
                                        class="status-badge <?php echo strtolower($row['status']) === 'active' ? 'active' : 'inactive'; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <a href="promoreg.php?edit=<?php echo $row['id']; ?>" class="btn-edit">Edit</a>
                                        <?php $isActive = ($row['status'] === 'Active'); ?>
                                        <button class="btn-deactivate <?php echo $isActive ? '' : 'inactive'; ?>"
                                            onclick="toggleStatus(<?php echo $row['id']; ?>, <?php echo $isActive ? 'false' : 'true'; ?>)">
                                            <?php echo $isActive ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                        <button class="btn-del"
                                            onclick="confirmDelete(<?php echo $row['id']; ?>)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">No promos found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Confirm Delete Modal (simple) -->
    <div id="deleteModal"
        style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:3000;align-items:center;justify-content:center;">
        <div
            style="background:white;padding:30px 40px;border-radius:8px;text-align:center;box-shadow:0 4px 16px rgba(0,0,0,0.2);min-width:320px;">
            <p style="font-size:16px;font-weight:600;margin-bottom:20px;color:#333;">Are you sure you want to delete
                this promo?</p>
            <div style="display:flex;gap:12px;justify-content:center;">
                <button onclick="closeDeleteModal()"
                    style="padding:9px 24px;border:1px solid #ddd;background:white;color:#666;border-radius:4px;cursor:pointer;font-size:13px;">Cancel</button>
                <a id="deleteConfirmLink" href="#"
                    style="padding:9px 24px;border:none;background:#e53935;color:white;border-radius:4px;cursor:pointer;font-size:13px;text-decoration:none;font-weight:600;">Delete</a>
            </div>
        </div>
    </div>

    <!-- Search Modal -->
    <div id="searchItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Search
            </div>
            <div class="modal-body">
                <table class="search-results-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Item Code</th>
                            <th style="width: 45%;">Item Description</th>
                            <th style="width: 15%; text-align: center;">Action</th>
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

    <!-- Search Promo Item Modal -->
    <div id="searchPromoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Search Promo Item
            </div>
            <div class="modal-body">
                <table class="search-results-table">
                    <thead>
                        <tr>
                            <th style="width: 80%;">Description</th>
                            <th style="width: 20%; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="promoSearchResultsBody">
                        <!-- Results will be injected here -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-back-modal" onclick="closePromoSearchModal()">Back</button>
            </div>
        </div>
    </div>

    <!-- View Branches Modal -->
    <div id="viewBranchesModal"
        style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:3000;align-items:center;justify-content:center;">
        <div style="background:white;padding:0;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.2);min-width:320px;max-width:400px;overflow:hidden;">
            <div style="background:#f5f5f5;padding:12px 20px;border-bottom:1px solid #ddd;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:16px;color:#333;">Promo Branches</h3>
                <span onclick="closeViewBranchesModal()" style="cursor:pointer;font-size:18px;color:#999;font-weight:bold;">&times;</span>
            </div>
            <div id="viewBranchesList" style="padding:20px;max-height:300px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;">
                <!-- Branches will be appended here -->
            </div>
            <div style="padding:15px 20px;border-top:1px solid #ddd;text-align:right;">
                <button onclick="closeViewBranchesModal()" style="padding:8px 16px;background:#e0e0e0;border:none;border-radius:4px;cursor:pointer;font-weight:600;color:#333;">Close</button>
            </div>
        </div>
    </div>

    <script>
        /* ===== SIDEBAR ===== */
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('hidden');
            document.querySelector('.main-content').classList.toggle('expanded');
            document.querySelector('.menu-btn').classList.toggle('active');
        }
        function toggleSection(el) {
            const section = el.parentElement;
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


        /* ===== AUTO-FILL BRAND AND SEARCH DATA ===== */
        let motorsData = <?php 
            $motors_arr = [];
            if ($motors_result && $motors_result->num_rows > 0) {
                $motors_result->data_seek(0);
                while ($m = $motors_result->fetch_assoc()) {
                    $motors_arr[] = [
                        'item_code'   => $m['item_code'],
                        'description' => $m['description'],
                        'brand'       => $m['brand']
                    ];
                }
            }
            echo json_encode($motors_arr);
        ?>;

        /* ===== PROMO ITEM TABLE ROW MANAGEMENT ===== */
        let promoRowCount = 0;
        let currentSearchRowId = null;
        let currentSearchType  = null; // 'model' or 'promo'

        function addPromoItemRow(motorModel = '', discountType = 'Free', discountValue = '', promoItem = '') {
            promoRowCount++;
            const tbody  = document.getElementById('promo-items-tbody');
            const empty  = document.getElementById('promo-item-empty');
            const table  = document.getElementById('promo-items-table');

            empty.style.display = 'none';
            table.style.display = 'table';

            const tr = document.createElement('tr');
            tr.id = 'promo-row-' + promoRowCount;
            tr.innerHTML = `
                <td>${promoRowCount}</td>
                <td>
                    <div class="promo-row-cell">
                        <input type="text" placeholder="Enter Item Model" class="row-input promo-model-${promoRowCount}"
                               value="${escapeHtmlAttr(motorModel || '')}"
                               onkeypress="if(event.key==='Enter'){event.preventDefault();openRowSearch(${promoRowCount},'model');}">
                        <button type="button" class="btn-row-search" onclick="openRowSearch(${promoRowCount},'model')">Search</button>
                    </div>
                </td>
                <td>
                    <div style="display:flex; gap:5px; align-items:center; justify-content:center;">
                        <select class="row-input promo-discount-type-${promoRowCount}" onchange="toggleRowDiscountValue(${promoRowCount})" style="width:135px;">
                            <option value="Free" ${discountType === 'Free' ? 'selected' : ''}>Free</option>
                            <option value="%" ${discountType === '%' ? 'selected' : ''}>% (Percentage)</option>
                        </select>
                        <input type="number" step="0.01" min="0" class="row-input promo-discount-val-${promoRowCount}"
                               placeholder="%" value="${discountValue !== '0.00' && discountValue !== 0 ? discountValue : ''}" 
                               style="width:70px; ${discountType === '%' ? '' : 'display:none;'}">
                    </div>
                </td>
                <td>
                    <div class="promo-row-cell">
                        <input type="text" placeholder="Enter Promo Item" class="row-input promo-item-${promoRowCount}"
                               value="${escapeHtmlAttr(promoItem || '')}"
                               onkeypress="if(event.key==='Enter'){event.preventDefault();openRowSearch(${promoRowCount},'promo');}">
                        <button type="button" class="btn-row-search" onclick="openRowSearch(${promoRowCount},'promo')">Search</button>
                    </div>
                </td>
                <td><button type="button" class="btn-remove-promo-row" onclick="removePromoRow(${promoRowCount})">Remove</button></td>
            `;
            tbody.appendChild(tr);
        }

        function toggleRowDiscountValue(rowId) {
            var sel = document.querySelector('.promo-discount-type-' + rowId).value;
            var inp = document.querySelector('.promo-discount-val-' + rowId);
            if (sel === 'Free') {
                inp.style.display = 'none';
                inp.value = '';
            } else {
                inp.style.display = 'inline-block';
            }
        }

        function removePromoRow(id) {
            const row = document.getElementById('promo-row-' + id);
            if (row) row.remove();
            checkPromoEmpty();
        }

        function checkPromoEmpty() {
            const rows  = document.querySelectorAll('#promo-items-tbody tr');
            const empty = document.getElementById('promo-item-empty');
            const table = document.getElementById('promo-items-table');
            if (rows.length === 0) {
                empty.style.display = 'block';
                table.style.display = 'none';
            } else {
                empty.style.display = 'none';
                table.style.display = 'table';
            }
        }

        /* ===== PER-ROW SEARCH MODAL ===== */
        function openRowSearch(rowId, type) {
            var inputClass = type === 'model' ? '.promo-model-' + rowId : '.promo-item-' + rowId;
            var searchTerm = (document.querySelector(inputClass)?.value || '').trim();
            if (searchTerm === '') {
                alert('Please enter a ' + (type === 'model' ? 'motor model' : 'promo item') + ' to search!');
                return;
            }
            currentSearchRowId = rowId;
            currentSearchType  = type;

            var modal = document.getElementById('searchItemModal');
            var resultsBody = document.getElementById('searchResultsBody');
            resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:16px;color:#999;">Searching…</td></tr>';
            modal.style.display = 'flex';

            searchTerm = searchTerm.toLowerCase();
            resultsBody.innerHTML = '';
            var found = false;
            motorsData.forEach(function(item, index) {
                if ((item.item_code && item.item_code.toLowerCase().includes(searchTerm)) ||
                    (item.description && item.description.toLowerCase().includes(searchTerm)) ||
                    (item.brand && item.brand.toLowerCase().includes(searchTerm))) {
                    found = true;
                    var row = document.createElement('tr');
                    row.innerHTML = '<td>' + escapeHtml(item.item_code || '') + '</td>' +
                                    '<td>' + escapeHtml(item.description || '') + '</td>' +
                                    '<td><button type="button" class="btn-select" onclick="selectRowItem(' + index + ')">Select</button></td>';
                    resultsBody.appendChild(row);
                }
            });
            if (!found) {
                resultsBody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:16px;color:#999;">No results found.</td></tr>';
            }
        }

        function selectRowItem(index) {
            var item = motorsData[index];
            var inputClass = currentSearchType === 'model'
                ? '.promo-model-' + currentSearchRowId
                : '.promo-item-'  + currentSearchRowId;
            var input = document.querySelector(inputClass);
            if (input) input.value = item.item_code || item.description;
            closeSearchModal();
        }

        function closeSearchModal() {
            document.getElementById('searchItemModal').style.display = 'none';
            currentSearchRowId = null;
            currentSearchType  = null;
        }

        /* Close modal when clicking outside */
        window.onclick = function(event) {
            var modal = document.getElementById('searchItemModal');
            if (event.target === modal) closeSearchModal();
        };

        /* ===== HELPER FUNCTIONS ===== */
        function escapeHtml(unsafe) {
            return (unsafe || '').replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");
        }
        function escapeHtmlAttr(unsafe) {
            return (unsafe || '').replace(/&/g,"&amp;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");
        }

        /* ===== INJECT PROMO ITEMS JSON BEFORE SUBMIT ===== */
        document.addEventListener('DOMContentLoaded', function() {
            var form = document.querySelector('#formContainer form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    var rows = document.querySelectorAll('#promo-items-tbody tr');
                    var items = [];
                    rows.forEach(function(row) {
                        var id = row.id.replace('promo-row-', '');
                        var modelInput = row.querySelector('.promo-model-' + id);
                        var promoInput = row.querySelector('.promo-item-' + id);
                        var dtInput    = row.querySelector('.promo-discount-type-' + id);
                        var dvInput    = row.querySelector('.promo-discount-val-' + id);
                        items.push({
                            motor_model:    modelInput ? modelInput.value.trim() : '',
                            discount_type:  dtInput ? dtInput.value : 'Free',
                            discount_value: dvInput ? dvInput.value : '',
                            promo_item:     promoInput ? promoInput.value.trim() : ''
                        });
                    });
                    document.getElementById('promoItemsJson').value = JSON.stringify(items);
                });
            }

            /* Pre-populate rows in edit mode */
            <?php if ($editMode && !empty($editItems)): ?>
            var editItems = <?php echo json_encode($editItems); ?>;
            editItems.forEach(function(pi) {
                addPromoItemRow(pi.motor_model, pi.discount_type, pi.discount_value, pi.promo_item);
            });
            <?php endif; ?>
        });

        /* ===== FORM OPEN/CLOSE ===== */
        function openForm() {
            document.getElementById('formContainer').classList.remove('hidden');
            document.getElementById('btnAddPromo').style.display = 'none';
            document.getElementById('btnCloseForm').style.display = '';
            document.getElementById('formContainer').scrollIntoView({ behavior: 'smooth' });
        }
        function closeForm() {
            document.getElementById('formContainer').classList.add('hidden');
            document.getElementById('btnAddPromo').style.display = '';
            document.getElementById('btnCloseForm').style.display = 'none';
            if (window.location.search.includes('edit=')) {
                window.location.href = 'promoreg.php';
            }
        }

        // If edit mode, show form automatically
        <?php if ($editMode): ?>
            window.onload = function () {
                document.getElementById('btnAddPromo').style.display = 'none';
                document.getElementById('btnCloseForm').style.display = '';
            };
        <?php endif; ?>

        /* ===== STATUS TOGGLE ===== */
        function toggleStatus(id, activate) {
            window.location.href = 'promoreg.php?status_update=1&id=' + id + '&status=' + activate;
        }

        /* ===== DELETE CONFIRM ===== */
        function confirmDelete(id) {
            document.getElementById('deleteConfirmLink').href = 'promoreg.php?delete=' + id;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        /* ===== SEARCH TABLE ===== */
        function searchTable() {
            var input = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('#promoTable tbody tr').forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
            });
        }

        /* ===== BRANCH MODAL FUNCTIONS ===== */
        function openBranchesModal() {
            document.getElementById('branchesModal').style.display = 'flex';
            updateSelectAllState();
        }

        function closeBranchesModal() {
            document.getElementById('branchesModal').style.display = 'none';
        }

        function toggleBranchModalSelectAll() {
            const selectAll = document.getElementById('selectAllBranchesModal');
            const checkboxes = document.querySelectorAll('.branch-checkbox');
            
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            
            // Update area checkboxes
            document.querySelectorAll('.area-select-all').forEach(area => {
                area.checked = selectAll.checked;
            });
        }

        function toggleAreaSelectAll(areaCheckbox) {
            const area = areaCheckbox.dataset.area;
            const areaCheckboxes = document.querySelectorAll(`.${area}-checkbox`);
            
            areaCheckboxes.forEach(cb => cb.checked = areaCheckbox.checked);
            
            updateSelectAllState();
        }

        function updateSelectAllState() {
            const checkboxes = document.querySelectorAll('.branch-checkbox');
            const selectAll = document.getElementById('selectAllBranchesModal');
            
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            selectAll.checked = allChecked && checkboxes.length > 0;
            
            // Update area select-all checkboxes
            document.querySelectorAll('.area-select-all').forEach(areaCheckbox => {
                const area = areaCheckbox.dataset.area;
                const areaCheckboxes = document.querySelectorAll(`.${area}-checkbox`);
                const allAreaChecked = Array.from(areaCheckboxes).every(cb => cb.checked);
                areaCheckbox.checked = allAreaChecked && areaCheckboxes.length > 0;
            });
        }

        function filterBranches() {
            const searchTerm = document.getElementById('branchSearchInput').value.toLowerCase();
            const rows = document.querySelectorAll('.branch-row');
            
            rows.forEach(row => {
                const branchText = row.textContent.toLowerCase();
                if (branchText.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function applyBranchesSelection() {
            const selected = [];
            document.querySelectorAll('.branch-checkbox:checked').forEach(cb => {
                selected.push(cb.value);
            });
            
            const displayInput = document.getElementById('selectedBranchesDisplay');
            if (selected.length === 0) {
                displayInput.value = '';
            } else {
                displayInput.value = selected.join(', ');
            }
            
            // Remove existing hidden branch inputs
            document.querySelectorAll('input[name="branch[]"][type="hidden"]').forEach(input => {
                input.remove();
            });
            
            // Add hidden inputs for each selected branch inside the form
            const form = document.querySelector('#formContainer form');
            selected.forEach(branch => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'branch[]';
                hiddenInput.value = branch;
                form.appendChild(hiddenInput);
            });
            
            closeBranchesModal();
        }

        // Listen to checkbox changes to update area select-all state
        document.addEventListener('change', function(event) {
            if (event.target.classList.contains('branch-checkbox')) {
                updateSelectAllState();
            }
        });

        /* ===== BRANCH VIEW MODAL ===== */
        function viewBranches(branchesStr) {
            var listContainer = document.getElementById('viewBranchesList');
            listContainer.innerHTML = '';
            if (branchesStr.trim() !== '') {
                branchesStr.split(',').forEach(function(br) {
                    var div = document.createElement('div');
                    div.style.cssText = 'padding:10px;border-bottom:1px solid #eee;color:#333;font-weight:500;font-size:14px;';
                    div.innerText = br.trim();
                    listContainer.appendChild(div);
                });
            }
            document.getElementById('viewBranchesModal').style.display = 'flex';
        }
        function closeViewBranchesModal() {
            document.getElementById('viewBranchesModal').style.display = 'none';
        }

        // Initialize display on load
        window.addEventListener('DOMContentLoaded', updateBranchDisplay);
    </script>
</body>

</html>