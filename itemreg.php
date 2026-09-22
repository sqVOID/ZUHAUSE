<?php
require_once 'session_check.php';

// Authorization Check
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

// Get filter parameters early so they're available for redirects
$filter_family = isset($_GET['filter_family']) ? trim($_GET['filter_family']) : '';
$filter_brand = isset($_GET['filter_brand']) ? trim($_GET['filter_brand']) : '';
$filter_group = isset($_GET['filter_group']) ? trim($_GET['filter_group']) : '';
$filter_dept = isset($_GET['filter_dept']) ? trim($_GET['filter_dept']) : '';

// Restrict 'User' from accessing this page
if (false) {
    header("Location: report.php");
    exit();
}

// Detect success/updated toast
$toast_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $toast_message = 'Item registered successfully!';
} elseif (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $toast_message = 'Item updated successfully!';
} elseif (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $toast_message = 'Item deleted successfully!';
}

include 'config.php';


$create_items_table = "CREATE TABLE IF NOT EXISTS items (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    family_code VARCHAR(100),
    brand VARCHAR(100),
    group_name VARCHAR(100),
    department VARCHAR(100),
    srp DECIMAL(10, 2) DEFAULT 0.00,
    commission DECIMAL(10, 2) DEFAULT 0.00,
    has_commission TINYINT(1) DEFAULT 0,
    tc_commission DECIMAL(10, 2) DEFAULT 0.00,
    points DECIMAL(10, 2) DEFAULT 0.00,
    has_points TINYINT(1) DEFAULT 0,
    freebies TEXT,
    has_freebies TINYINT(1) DEFAULT 0,
    has_discount TINYINT(1) DEFAULT 0,
    has_serial TINYINT(1) DEFAULT 0,
    stock_qty INT(11) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_items_table);


$check_cols = $conn->query("SHOW COLUMNS FROM items LIKE 'has_commission'");
if ($check_cols->num_rows == 0) {
    $conn->query("ALTER TABLE items ADD COLUMN has_commission TINYINT(1) DEFAULT 0 AFTER commission");
}
$check_cols = $conn->query("SHOW COLUMNS FROM items LIKE 'has_points'");
if ($check_cols->num_rows == 0) {
    $conn->query("ALTER TABLE items ADD COLUMN has_points TINYINT(1) DEFAULT 0 AFTER points");
}
$check_cols = $conn->query("SHOW COLUMNS FROM items LIKE 'freebies'");
if ($check_cols->num_rows == 0) {
    $conn->query("ALTER TABLE items ADD COLUMN freebies TEXT AFTER has_points");
}


$create_freebies_table = "CREATE TABLE IF NOT EXISTS item_freebies (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    item_id INT(11) NOT NULL,
    freebie_name VARCHAR(255) NOT NULL,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
)";
// $conn->query($create_freebies_table); // Commented out - not using this table anymore


// Create item_prices table (for installment prices)
// Recommended structure: one row per (item_id, branch, price_type)
// branch = '' means it's the global/default price (applies to all branches)
$create_prices_table = "CREATE TABLE IF NOT EXISTS item_prices (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    item_id INT(11) NOT NULL,
    branch VARCHAR(100) NOT NULL DEFAULT '',
    price_type VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 0,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_branch_price (item_id, branch, price_type)
)";
$conn->query($create_prices_table);

// Auto-migrate: add branch column if it doesn't exist yet
$check_branch_ip = $conn->query("SHOW COLUMNS FROM item_prices LIKE 'branch'");
if ($check_branch_ip && $check_branch_ip->num_rows == 0) {
    $conn->query("ALTER TABLE item_prices ADD COLUMN branch VARCHAR(100) NOT NULL DEFAULT '' AFTER item_id");
    // Add unique key (ignore error if already exists)
    $conn->query("ALTER TABLE item_prices ADD UNIQUE KEY unique_item_branch_price (item_id, branch, price_type)");
}

// Add branch column to items table if it doesn't exist
$check_branch_col = $conn->query("SHOW COLUMNS FROM items LIKE 'branch'");
if ($check_branch_col->num_rows == 0) {
    $conn->query("ALTER TABLE items ADD COLUMN branch VARCHAR(100) AFTER department");
}

// Add has_serial_2 column to items table if it doesn't exist (for IMEI 2 checkbox)
$check_serial2_col = $conn->query("SHOW COLUMNS FROM items LIKE 'has_serial_2'");
if ($check_serial2_col->num_rows == 0) {
    $conn->query("ALTER TABLE items ADD COLUMN has_serial_2 TINYINT(1) DEFAULT 0 AFTER has_serial");
}

// Add has_serial_number column to items table if it doesn't exist (for SERIAL NUMBER checkbox for TABLET)
$check_serial_number_col = $conn->query("SHOW COLUMNS FROM items LIKE 'has_serial_number'");
if ($check_serial_number_col->num_rows == 0) {
    $conn->query("ALTER TABLE items ADD COLUMN has_serial_number TINYINT(1) DEFAULT 0 AFTER has_serial_2");
}

// Add serial_primary column to items table if it doesn't exist (tracks which IMEI checkbox was checked first)
// 1 = IMEI is primary (default), 2 = IMEI 2 is primary
$check_serial_primary_col = $conn->query("SHOW COLUMNS FROM items LIKE 'serial_primary'");
if ($check_serial_primary_col->num_rows == 0) {
    $conn->query("ALTER TABLE items ADD COLUMN serial_primary TINYINT(1) DEFAULT 1 AFTER has_serial_number");
}

// Create item_discount_branches table to track which branches can edit discount
$create_discount_branches_table = "CREATE TABLE IF NOT EXISTS item_discount_branches (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    item_id INT(11) NOT NULL,
    branch_name VARCHAR(100) NOT NULL,
    discount_editable TINYINT(1) DEFAULT 1,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_branch (item_id, branch_name)
)";
$conn->query($create_discount_branches_table);


// Handle Delete Item
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);

    // Delete the item (CASCADE will handle related records)
    $sql = "DELETE FROM items WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        // Build redirect URL with filters
        $redirect_url = "itemreg.php?deleted=1";
        if (!empty($filter_family))
            $redirect_url .= "&filter_family=" . urlencode($filter_family);
        if (!empty($filter_brand))
            $redirect_url .= "&filter_brand=" . urlencode($filter_brand);
        if (!empty($filter_group))
            $redirect_url .= "&filter_group=" . urlencode($filter_group);
        if (!empty($filter_dept))
            $redirect_url .= "&filter_dept=" . urlencode($filter_dept);
        header("Location: $redirect_url");
        exit();
    } else {
        $message = "Error deleting item: " . $conn->error;
        $messageType = "error";
    }
}

// Handle Form Submission
$message = "";
$messageType = "";
$editMode = false;
$editData = null;
$editFreebies = [];
$editPrices = []; // Branch-specific SRP prices

// Helper function to process prices
// Returns: ['BranchName' => ['__SRP__' => ['price'=>..., 'active'=>...], ...], ...]
// Uses '' (empty string) as the key for global/default prices
function getPriceData($conn, $itemId)
{
    $data = [];
    $res = $conn->query("SELECT * FROM item_prices WHERE item_id = $itemId ORDER BY branch, price_type");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $branch = $row['branch']; // '' = global
            $data[$branch][$row['price_type']] = [
                'price' => $row['price'],
                'active' => $row['is_active']
            ];
        }
    }
    return $data;
}

if (isset($_GET['edit'])) {
    $editMode = true;
    $id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT * FROM items WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $editData = $result->fetch_assoc();

        // Parse freebies from the freebies column (comma-separated)
        if (!empty($editData['freebies'])) {
            $editFreebies = explode(',', $editData['freebies']);
            $editFreebies = array_map('trim', $editFreebies);
        }

        $editPrices = getPriceData($conn, $id);

        // Check if item code is being used in other tables
        $item_code_in_use = false;
        $item_code_check = $conn->real_escape_string($editData['item_code']);

        // Check in multiple tables (only if they exist)
        $tables_to_check = [
            'stock_on_hand' => "SELECT COUNT(*) as count FROM stock_on_hand WHERE UPPER(TRIM(item_code)) = UPPER(TRIM('$item_code_check'))",
            'sales_entry_items' => "SELECT COUNT(*) as count FROM sales_entry_items WHERE UPPER(TRIM(item_code)) = UPPER(TRIM('$item_code_check'))",
            'po_items' => "SELECT COUNT(*) as count FROM po_items WHERE UPPER(TRIM(item_code)) = UPPER(TRIM('$item_code_check'))",
            'preorder_items' => "SELECT COUNT(*) as count FROM preorder_items WHERE UPPER(TRIM(item_code)) = UPPER(TRIM('$item_code_check'))",
            'allocations' => "SELECT COUNT(*) as count FROM allocations WHERE UPPER(TRIM(item_code)) = UPPER(TRIM('$item_code_check'))",
        ];

        foreach ($tables_to_check as $table => $query) {
            // First check if table exists
            $table_check = $conn->query("SHOW TABLES LIKE '$table'");
            if ($table_check && $table_check->num_rows > 0) {
                // Table exists, now check if item code is in use
                try {
                    $check_result = $conn->query($query);
                    if ($check_result) {
                        $row = $check_result->fetch_assoc();
                        if ($row['count'] > 0) {
                            $item_code_in_use = true;
                            break;
                        }
                    }
                } catch (Exception $e) {
                    // Silently skip if there's an error with this table
                    continue;
                }
            }
        }

        // Store this in a variable for use in the form
        // Super-Admin can always edit, even if item code is in use
        $editData['item_code_locked'] = ($item_code_in_use && strcasecmp($system_level, 'Super-Admin') !== 0);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_item'])) {
    // DEBUG: Log POST data
    $log = "Timestamp: " . date("Y-m-d H:i:s") . "\n";
    $log .= "POST Data:\n" . print_r($_POST, true) . "\n";
    file_put_contents('post_debug.txt', $log);

    $item_code = strtoupper($conn->real_escape_string($_POST['item_code']));
    $description = strtoupper($conn->real_escape_string($_POST['description']));
    $family_code = $conn->real_escape_string($_POST['family_code']);
    $brand = $conn->real_escape_string($_POST['brand']);
    $group_name = $conn->real_escape_string($_POST['group']);
    $department = $conn->real_escape_string($_POST['department']);
    $branch = isset($_POST['branch']) && is_array($_POST['branch']) ? implode(', ', $_POST['branch']) : '';
    $branch = $conn->real_escape_string($branch);

    // Remove commas for numeric fields
    $srp = !empty($_POST['srp']) ? str_replace(',', '', $_POST['srp']) : 0;

    $commission = !empty($_POST['commission']) ? str_replace(',', '', $_POST['commission']) : 0;
    $has_commission = isset($_POST['has_commission']) ? 1 : 0;

    $tc_commission = !empty($_POST['tc_commission']) ? str_replace(',', '', $_POST['tc_commission']) : 0;

    $points = !empty($_POST['points']) ? str_replace(',', '', $_POST['points']) : 0;
    $has_points = isset($_POST['has_points']) ? 1 : 0;

    $has_freebies = isset($_POST['has_freebies']) ? 1 : 0;
    $has_discount = isset($_POST['has_discount']) ? 1 : 0;
    $has_serial = isset($_POST['use_serial']) ? 1 : 0;
    $has_serial_2 = isset($_POST['use_serial_2']) ? 1 : 0;
    $has_serial_number = isset($_POST['use_serial_number']) ? 1 : 0;
    // serial_primary: 1 = IMEI is primary (default), 2 = IMEI 2 / SERIAL NUMBER is primary
    $serial_primary = !empty($_POST['imei_primary_order']) ? intval($_POST['imei_primary_order']) : 1;
    if (($has_serial_2 || $has_serial_number) && !$has_serial) {
        $serial_primary = 2;
    } elseif ($has_serial && !$has_serial_2 && !$has_serial_number) {
        $serial_primary = 1;
    }
    if ($serial_primary < 1 || $serial_primary > 2)
        $serial_primary = 1;

    // Voucher fields
    $has_voucher = isset($_POST['has_voucher']) ? 1 : 0;
    $voucher_amount = !empty($_POST['voucher_amount']) ? str_replace(',', '', $_POST['voucher_amount']) : 0;

    // Token fields
    $has_token = isset($_POST['has_token']) ? 1 : 0;
    $token_amount = !empty($_POST['token_amount']) ? str_replace(',', '', $_POST['token_amount']) : 0;

    // Process freebies with item_code and quantity into formatted string
    $freebies_str = '';
    if ($has_freebies && isset($_POST['freebie_item_code']) && is_array($_POST['freebie_item_code'])) {
        $freebie_items = $_POST['freebie_item_code'];
        $freebie_quantities = isset($_POST['freebie_quantity']) ? $_POST['freebie_quantity'] : [];

        $freebies_array = [];
        foreach ($freebie_items as $index => $item_code) {
            $item_code = trim($item_code);
            if (!empty($item_code)) {
                $qty = isset($freebie_quantities[$index]) ? intval($freebie_quantities[$index]) : 1;
                if ($qty < 1)
                    $qty = 1;
                // Format: "ITEM_CODE (qty: X)"
                $freebies_array[] = "$item_code (qty: $qty)";
            }
        }

        if (!empty($freebies_array)) {
            $freebies_str = implode(', ', $freebies_array);
            $freebies_str = $conn->real_escape_string($freebies_str);
        }
    }

    // Prepare prices from JSON price sets
    // price_sets_json = [{branches:[], srp:'', prices:{type:amt}, actives:{type:1}}, ...]
    $price_sets_raw = isset($_POST['price_sets_json']) ? $_POST['price_sets_json'] : '';
    $price_sets = [];
    $all_branches_selected = [];
    if (!empty($price_sets_raw)) {
        $decoded = json_decode(stripslashes($price_sets_raw), true);
        if (is_array($decoded)) {
            $price_sets = $decoded;
            foreach ($price_sets as $pset) {
                if (isset($pset['branches']) && is_array($pset['branches'])) {
                    foreach ($pset['branches'] as $b) {
                        $b = trim($b);
                        if (!empty($b) && !in_array($b, $all_branches_selected)) {
                            $all_branches_selected[] = $b;
                        }
                    }
                }
            }
        }
    }

    // Override the generic branch list if we are using multi-set pricing
    if (!empty($all_branches_selected)) {
        $branch = implode(', ', $all_branches_selected);
        $branch = $conn->real_escape_string($branch);
    }

    // Fallback: legacy single-set POST fields
    $price_amounts = isset($_POST['price_amount']) ? $_POST['price_amount'] : [];
    $price_actives = isset($_POST['price_active']) ? $_POST['price_active'] : [];

    if (isset($_POST['item_id']) && !empty($_POST['item_id'])) {
        // Update
        $id = $conn->real_escape_string($_POST['item_id']);

        // Check for duplicate item_code (excluding current item)
        $check_duplicate = $conn->query("SELECT id FROM items WHERE item_code = '$item_code' AND id != $id");
        if ($check_duplicate && $check_duplicate->num_rows > 0) {
            $message = "Error: Item Code '$item_code' already exists. Please use a different Item Code.";
            $messageType = "error";
        } else {
            $sql = "UPDATE items SET 
                    item_code='$item_code', 
                    description='$description', 
                    family_code='$family_code', 
                    brand='$brand', 
                    group_name='$group_name', 
                    department='$department', 
                    branch='$branch',
                    srp='$srp', 
                    commission='$commission', 
                    has_commission='$has_commission',
                    tc_commission='$tc_commission', 
                    points='$points', 
                    has_points='$has_points',
                    freebies='$freebies_str',
                    has_freebies='$has_freebies', 
                    has_discount='$has_discount', 
                    has_serial='$has_serial',
                    has_serial_2='$has_serial_2',
                    has_serial_number='$has_serial_number',
                    serial_primary='$serial_primary',
                    has_voucher='$has_voucher',
                    voucher_amount='$voucher_amount',
                    has_token='$has_token',
                    token_amount='$token_amount'
                    WHERE id=$id";

            if ($conn->query($sql) === TRUE) {
                // Update Prices: DELETE old, then INSERT per price set
                $conn->query("DELETE FROM item_prices WHERE item_id=$id");

                if (!empty($price_sets)) {
                    // New multi-set approach
                    foreach ($price_sets as $pset) {
                        $set_branches = (isset($pset['branches']) && is_array($pset['branches']) && count($pset['branches']) > 0)
                            ? $pset['branches'] : [''];
                        $set_srp = isset($pset['srp']) ? str_replace(',', '', $pset['srp']) : '0';
                        $set_prices = isset($pset['prices']) && is_array($pset['prices']) ? $pset['prices'] : [];
                        $set_actives = isset($pset['actives']) && is_array($pset['actives']) ? $pset['actives'] : [];
                        foreach ($set_branches as $branch) {
                            $pBranch = $conn->real_escape_string(trim($branch));
                            // Save SRP for this branch
                            $cleanSrp = $conn->real_escape_string($set_srp);
                            $conn->query("INSERT INTO item_prices (item_id, branch, price_type, price, is_active)
                                      VALUES ($id, '$pBranch', '__SRP__', '$cleanSrp', 1)
                                      ON DUPLICATE KEY UPDATE price='$cleanSrp', is_active=1");
                            // Save each price type
                            foreach ($set_prices as $type => $amount) {
                                $isActive = isset($set_actives[$type]) ? 1 : 0;
                                $cleanAmt = str_replace(',', '', (!empty($amount) ? $amount : '0'));
                                if ($cleanAmt == 0 && !$isActive)
                                    continue;
                                $pType = $conn->real_escape_string($type);
                                $pAmount = $conn->real_escape_string($cleanAmt);
                                $conn->query("INSERT INTO item_prices (item_id, branch, price_type, price, is_active)
                                          VALUES ($id, '$pBranch', '$pType', '$pAmount', $isActive)
                                          ON DUPLICATE KEY UPDATE price='$pAmount', is_active=$isActive");
                            }
                        }
                    }
                } else {
                    // Legacy fallback
                    $price_branches_raw = isset($_POST['price_branches']) ? $_POST['price_branches'] : '[""]';
                    $price_branches = json_decode(stripslashes($price_branches_raw), true);
                    if (!is_array($price_branches) || count($price_branches) === 0)
                        $price_branches = [''];
                    foreach ($price_branches as $branch) {
                        $pBranch = $conn->real_escape_string(trim($branch));
                        foreach ($price_amounts as $type => $amount) {
                            $isActive = isset($price_actives[$type]) ? 1 : 0;
                            $cleanAmt = str_replace(',', '', (!empty($amount) ? $amount : '0'));
                            if ($cleanAmt == 0 && !$isActive)
                                continue;
                            $pType = $conn->real_escape_string($type);
                            $pAmount = $conn->real_escape_string($cleanAmt);
                            $conn->query("INSERT INTO item_prices (item_id, branch, price_type, price, is_active)
                                      VALUES ($id, '$pBranch', '$pType', '$pAmount', $isActive)
                                      ON DUPLICATE KEY UPDATE price='$pAmount', is_active=$isActive");
                        }
                    }
                }

                // Discount is now item-based, no need to update branch permissions

                // Build redirect URL with filters
                $redirect_url = "itemreg.php?updated=1";
                if (!empty($filter_family))
                    $redirect_url .= "&filter_family=" . urlencode($filter_family);
                if (!empty($filter_brand))
                    $redirect_url .= "&filter_brand=" . urlencode($filter_brand);
                if (!empty($filter_group))
                    $redirect_url .= "&filter_group=" . urlencode($filter_group);
                if (!empty($filter_dept))
                    $redirect_url .= "&filter_dept=" . urlencode($filter_dept);
                header("Location: $redirect_url");
                exit();
            } else {
                $message = "Error: " . $conn->error;
                $messageType = "error";
            }
        }
    } else {
        // Insert - Check for duplicate item_code first
        $check_duplicate = $conn->query("SELECT id FROM items WHERE item_code = '$item_code'");
        if ($check_duplicate && $check_duplicate->num_rows > 0) {
            $message = "Error: Item Code '$item_code' already exists. Please use a different Item Code.";
            $messageType = "error";
        } else {
            $sql = "INSERT INTO items (item_code, description, family_code, brand, group_name, department, branch, srp, commission, has_commission, tc_commission, points, has_points, freebies, has_freebies, has_discount, has_serial, has_serial_2, has_serial_number, serial_primary, has_voucher, voucher_amount, has_token, token_amount, status) 
                    VALUES ('$item_code', '$description', '$family_code', '$brand', '$group_name', '$department', '$branch', '$srp', '$commission', '$has_commission', '$tc_commission', '$points', '$has_points', '$freebies_str', '$has_freebies', '$has_discount', '$has_serial', '$has_serial_2', '$has_serial_number', '$serial_primary', '$has_voucher', '$voucher_amount', '$has_token', '$token_amount', 'Active')";

            if ($conn->query($sql) === TRUE) {
                $new_id = $conn->insert_id;

                if (!empty($price_sets)) {
                    foreach ($price_sets as $pset) {
                        $set_branches = (isset($pset['branches']) && is_array($pset['branches']) && count($pset['branches']) > 0)
                            ? $pset['branches'] : [''];
                        $set_srp = isset($pset['srp']) ? str_replace(',', '', $pset['srp']) : '0';
                        $set_prices = isset($pset['prices']) && is_array($pset['prices']) ? $pset['prices'] : [];
                        $set_actives = isset($pset['actives']) && is_array($pset['actives']) ? $pset['actives'] : [];
                        foreach ($set_branches as $branch) {
                            $pBranch = $conn->real_escape_string(trim($branch));
                            $cleanSrp = $conn->real_escape_string($set_srp);
                            $conn->query("INSERT INTO item_prices (item_id, branch, price_type, price, is_active)
                                      VALUES ($new_id, '$pBranch', '__SRP__', '$cleanSrp', 1)
                                      ON DUPLICATE KEY UPDATE price='$cleanSrp', is_active=1");
                            foreach ($set_prices as $type => $amount) {
                                $isActive = isset($set_actives[$type]) ? 1 : 0;
                                $cleanAmt = str_replace(',', '', (!empty($amount) ? $amount : '0'));
                                if ($cleanAmt == 0 && !$isActive)
                                    continue;
                                $pType = $conn->real_escape_string($type);
                                $pAmount = $conn->real_escape_string($cleanAmt);
                                $conn->query("INSERT INTO item_prices (item_id, branch, price_type, price, is_active)
                                          VALUES ($new_id, '$pBranch', '$pType', '$pAmount', $isActive)
                                          ON DUPLICATE KEY UPDATE price='$pAmount', is_active=$isActive");
                            }
                        }
                    }
                } else {
                    $price_branches_raw = isset($_POST['price_branches']) ? $_POST['price_branches'] : '[""]';
                    $price_branches = json_decode(stripslashes($price_branches_raw), true);
                    if (!is_array($price_branches) || count($price_branches) === 0)
                        $price_branches = [''];
                    foreach ($price_branches as $branch) {
                        $pBranch = $conn->real_escape_string(trim($branch));
                        foreach ($price_amounts as $type => $amount) {
                            $isActive = isset($price_actives[$type]) ? 1 : 0;
                            $cleanAmt = str_replace(',', '', (!empty($amount) ? $amount : '0'));
                            if ($cleanAmt == 0 && !$isActive)
                                continue;
                            $pType = $conn->real_escape_string($type);
                            $pAmount = $conn->real_escape_string($cleanAmt);
                            $conn->query("INSERT INTO item_prices (item_id, branch, price_type, price, is_active)
                                      VALUES ($new_id, '$pBranch', '$pType', '$pAmount', $isActive)
                                      ON DUPLICATE KEY UPDATE price='$pAmount', is_active=$isActive");
                        }
                    }
                }

                // Discount is now item-based, no need to insert branch permissions

                // Build redirect URL with filters
                $redirect_url = "itemreg.php?success=1";
                if (!empty($filter_family))
                    $redirect_url .= "&filter_family=" . urlencode($filter_family);
                if (!empty($filter_brand))
                    $redirect_url .= "&filter_brand=" . urlencode($filter_brand);
                if (!empty($filter_group))
                    $redirect_url .= "&filter_group=" . urlencode($filter_group);
                if (!empty($filter_dept))
                    $redirect_url .= "&filter_dept=" . urlencode($filter_dept);
                header("Location: $redirect_url");
                exit();
            } else {
                $message = "Error: " . $conn->error;
                $messageType = "error";
            }
        }
    }
}

// Handle Status Update
if (isset($_GET['status_update'])) {
    $id = $conn->real_escape_string($_GET['id']);
    $status = $conn->real_escape_string($_GET['status']);
    $new_status = ($status == 'true') ? 'Active' : 'Deactivated';
    $conn->query("UPDATE items SET status = '$new_status' WHERE id = $id");

    // Build redirect URL with filters
    $redirect_url = "itemreg.php";
    $params = [];
    if (!empty($filter_family))
        $params[] = "filter_family=" . urlencode($filter_family);
    if (!empty($filter_brand))
        $params[] = "filter_brand=" . urlencode($filter_brand);
    if (!empty($filter_group))
        $params[] = "filter_group=" . urlencode($filter_group);
    if (!empty($filter_dept))
        $params[] = "filter_dept=" . urlencode($filter_dept);
    if (!empty($params))
        $redirect_url .= "?" . implode("&", $params);

    header("Location: $redirect_url");
    exit();
}

// Fetch Dropdown Options
$family_codes = $conn->query("SELECT * FROM family_codes WHERE status='Active' ORDER BY family_code");
$brands = $conn->query("SELECT * FROM brands WHERE status='Active' ORDER BY brand_name");
$groups = $conn->query("SELECT * FROM `groups` WHERE status='Active' ORDER BY group_name");
$departments = $conn->query("SELECT * FROM departments WHERE status='Active' ORDER BY department_name");
$branches = $conn->query("SELECT * FROM branches WHERE status='Active' ORDER BY branch_name");

// Fetch Items for List � apply GET filters
// Only execute query if at least one filter is selected
$items_result = null;
$has_filter = ($filter_family !== '' || $filter_brand !== '' || $filter_group !== '' || $filter_dept !== '');

if ($has_filter) {
    $where_clauses = [];
    if ($filter_family !== '' && $filter_family !== 'all')
        $where_clauses[] = "family_code = '" . $conn->real_escape_string($filter_family) . "'";
    if ($filter_brand !== '' && $filter_brand !== 'all')
        $where_clauses[] = "brand = '" . $conn->real_escape_string($filter_brand) . "'";
    if ($filter_group !== '' && $filter_group !== 'all')
        $where_clauses[] = "group_name = '" . $conn->real_escape_string($filter_group) . "'";
    if ($filter_dept !== '' && $filter_dept !== 'all')
        $where_clauses[] = "department = '" . $conn->real_escape_string($filter_dept) . "'";

    $items_where = count($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    $items_result = $conn->query("SELECT * FROM items $items_where ORDER BY id DESC");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Item Registration</title>
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
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 30px;
        }

        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .btn-add-item {
            padding: 10px 24px;
            border: none;
            background: var(--color-gold);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-add-item:hover {
            background: var(--color-gold-light);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(176, 138, 82, 0.3);
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .form-container.hidden {
            display: none;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
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

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: white;
        }

        .form-group input::placeholder {
            color: #999;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2196F3;
        }

        .form-row-custom {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-set-prices {
            background: #1b5e20;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            white-space: nowrap;
            height: 38px;
            align-self: flex-end;
        }

        .checkbox-large {
            width: 18px;
            height: 18px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .features-grid {
            margin-top: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .feature-block {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* Revised Freebies Style */
        .freebies-box {
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 20px;
            background: white;
            display: flex;
            flex-direction: column;
        }

        .freebies-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .freebies-title-group {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .freebies-label {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .toggle-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background-color: #ccc;
            transition: background-color 0.3s;
        }

        .toggle-dot.active {
            background-color: #4CAF50;
        }

        .toggle-dot.disabled {
            background-color: #e0e0e0;
            cursor: not-allowed;
        }

        .toggle-dot.disabled.active {
            background-color: #a5d6a7;
        }

        .freebies-title-group.disabled-feature {
            cursor: not-allowed;
            opacity: 0.6;
        }

        .freebies-title-group.disabled-feature .freebies-label {
            color: #999;
        }

        .btn-add-more:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-add-more:disabled:hover {
            background: #ccc;
        }

        .hidden-checkbox {
            display: none;
        }

        .btn-add-more {
            background: #2e7d32;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: none;
        }

        .btn-add-more:hover {
            background: #1b5e20;
        }

        .freebies-table-container {
            display: none;
            margin-top: 10px;
        }

        .freebies-table {
            width: 100%;

            border-collapse: collapse;
            border: 1px solid #ddd;
        }

        .freebies-table th {
            background-color: var(--color-gold-pale);

            padding: 10px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #333;
            border: 1px solid #ddd;
        }

        .freebies-table td {
            padding: 8px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }

        .freebies-table .freebie-input {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 13px;
            box-sizing: border-box;
        }

        .freebies-table .freebie-input:focus {
            outline: none;
            border-color: #2e7d32;
        }

        .freebies-table .action-cell {
            text-align: center;
            padding: 10px;
        }

        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .input-with-icon .freebie-input {
            flex: 1;
            min-width: 0;
        }

        .freebie-row {
            background-color: #fff;
        }

        .freebie-row:hover {
            background-color: #f9f9f9;
        }

        .btn-icon-action {
            min-width: 32px;
            height: 32px;
            border: 1px solid #dee2e6;
            color: #666;
            background: #fff;
            border-radius: 4px;
            font-size: 20px;
            font-weight: 300;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            padding: 0 6px;
            flex-shrink: 0;
        }

        .btn-icon-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-plus {
            background: #f8f9fa;
            border-color: #dee2e6;
            color: #28a745;
        }

        .btn-plus:hover {
            background: #e8f5e9;
            border-color: #28a745;
            color: #28a745;
        }

        .btn-cross {
            background: #800020;
            border-color: #800020;
            color: #ffffff;
            font-size: 20px;
            font-weight: 400;
        }

        .btn-cross:hover {
            background: #5c0017;
            border-color: #5c0017;
            color: #ffffff;
        }

        .btn-search {
            background: #fff;
            border-color: #dee2e6;
            color: #007bff;
            padding: 0 6px;
        }

        .btn-search:hover {
            background: #e7f3ff;
            border-color: #007bff;
            color: #007bff;
        }

        .btn-search svg {
            width: 16px;
            height: 16px;
            display: block;
            height: 14px;
        }

        .btn-toggle-group {
            display: inline-flex;
            flex-direction: row;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            flex-wrap: nowrap;
            width: auto;
            min-width: 180px;
        }

        .btn-toggle-group .checkbox-large {
            flex-shrink: 0;
        }

        .btn-toggle {
            background: #f0a500;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            min-width: 120px;
            text-align: center;
            line-height: 1.5;
            flex-shrink: 0;
            display: inline-block;
        }

        .btn-toggle.active {
            background: #f0a500;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
        }

        .btn-cancel {
            padding: 10px 24px;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-save {
            padding: 10px 24px;
            border: none;
            background: #2e7d32;
            color: white;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-delete {
            padding: 10px 24px;
            border: none;
            background: #d32f2f;
            color: white;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-delete:hover {
            background: #b71c1c;
        }

        .table-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table-container h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0 0 20px 0;
        }

        .table-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .table-header-row h3 {
            margin: 0;
        }

        .table-header-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .search-box {
            position: relative;
            width: 300px;
            display: flex;
            align-items: center;
            gap: 0;
        }

        .search-box input {
            width: 100%;
            padding: 8px 35px 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .search-box svg {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            fill: #999;
        }

        /* Filter bar */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-bar select {
            padding: 7px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            color: #333;
            background: white;
            cursor: pointer;
            height: 34px;
        }

        .filter-bar select:focus {
            outline: none;
            border-color: #2196F3;
        }

        .btn-filter-reset {
            padding: 6px 14px;
            background: #f0f0f0;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 12px;
            color: #555;
            cursor: pointer;
            height: 34px;
            white-space: nowrap;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-filter-reset:hover {
            background: #e0e0e0;
        }

        .btn-filter-submit {
            padding: 6px 16px;
            background: #000000;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            color: white;
            cursor: pointer;
            height: 34px;
            white-space: nowrap;
            font-weight: 500;
        }

        .btn-filter-submit:hover {
            background: #333333;
        }

        .btn-filter-clear {
            padding: 6px 14px;
            background: #555555;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            color: white;
            cursor: pointer;
            height: 34px;
            white-space: nowrap;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-filter-clear:hover {
            background: #777777;
        }

        .btn-search {
            padding: 6px 16px;
            background: #000000;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            color: white;
            cursor: pointer;
            height: 34px;
            white-space: nowrap;
            font-weight: 500;
            margin-left: 8px;
        }

        .btn-search:hover {
            background: #333333;
        }

        .btn-search:disabled {
            background: #cccccc;
            cursor: not-allowed;
            opacity: 0.6;
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
            color: #000000;
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
            text-align: left !important;
        }

        td:last-child {
            border-right: 1px solid #ccc;
        }

        tbody tr:hover {
            background: #fdf8f3;
        }

        tbody td {
            text-align: center;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .status-badge.active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.inactive {
            background: #ffebee;
            color: #c62828;
        }

        .action-cell {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
        }

        .btn-edit {
            padding: 6px 16px;
            border: none;
            background: #1976D2;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
        }

        .deactivate-container {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #42c628ff;
            color: white;
            padding: 6px 16px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .deactivate-container input {
            margin: 0;
            cursor: pointer;
            width: 15px;
            height: 15px;
        }

        .deactivate-container label {
            cursor: pointer;
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
            font-family: Arial, sans-serif;
        }

        .btn-view-branches {
            padding: 5px 10px;
            border: none;
            background: #17a2b8;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-view-branches:hover {
            background: #138496;
        }

        .btn-close-modal {
            padding: 10px 24px;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-close-modal:hover {
            background: #f5f5f5;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 1.5% auto;
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
            padding: 30px 40px;
            max-height: auto;
            overflow-y: auto;
        }

        .price-cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 80px;
        }

        .price-row {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 15px;
            margin-bottom: 5px;
        }

        .price-label {
            font-size: 12px;
            font-weight: 700;
            color: #222;

            width: 130px;
            flex-shrink: 0;
        }

        .price-input {
            flex-grow: 1;
            padding: 0 10px;
            height: 34px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            width: 100%;
            /* Ensure it fills space */
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .price-check {
            width: 18px;
            height: 18px;
            cursor: pointer;
            margin-left: 5px;
            flex-shrink: 0;
            border: 1px solid #ccc;
            border-radius: 3px;
        }

        /* Others Bank Dropdown Styles */
        .price-row-others-bank {
            position: relative;
        }

        .others-bank-dropdown-container {
            position: relative;
            flex-grow: 1;
        }

        .others-bank-display {
            padding: 8px 12px;
            min-height: 34px;
            height: auto;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 11px;
            background-color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: border-color 0.2s;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.3;
        }

        .others-bank-display:hover {
            border-color: #999;
        }

        .others-bank-dropdown {
            position: absolute;
            top: 38px;
            left: 0;
            right: 0;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            padding: 8px 0;
        }

        .bank-checkbox-option {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.15s;
        }

        .bank-checkbox-option:hover {
            background-color: #f5f5f5;
        }

        .bank-checkbox-option input[type="checkbox"] {
            margin-right: 8px;
            cursor: pointer;
        }

        .branch-checkbox-option {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.15s;
        }

        .branch-checkbox-option:hover {
            background-color: #f5f5f5;
        }

        .branch-checkbox-option input[type="checkbox"] {
            margin-right: 8px;
            cursor: pointer;
        }

        .modal-footer {
            margin-top: -20px;
            padding: 20px 40px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pagination-text {
            font-size: 12px;
            font-weight: 600;
            color: #333;
        }

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
            background: #2e7d32;
            color: white;
        }

        .btn-next:hover {
            background: #1b5e20;
        }

        .srp-container {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            margin-top: 0px;
            margin-right: 0px;
            /* Align with check boxes roughly */
        }

        .srp-row {
            display: flex;
            align-items: center;
            gap: 15px;

            margin-bottom: 10px;
        }

        .srp-row .price-label {
            width: auto;
            font-size: 18px;
            font-weight: 600;
        }

        .srp-row .price-input {
            width: 200px;
            flex-grow: 0;
        }

        .btn-internal-apply {
            background: #2e7d32;
            color: white;
            border: none;
            width: 200px;
            height: 36px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-internal-apply:hover {
            background: #1b5e20;
        }

        .price-input[readonly] {
            background-color: #f5f5f5;
            cursor: not-allowed;
            color: #666;
        }

        /* Price Set Tabs */
        .ps-tabs-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            border-bottom: 2px solid #e0e0e0;
            padding: 0 20px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .ps-tab {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-bottom: none;
            border-radius: 6px 6px 0 0;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            background: #f5f5f5;
            color: #666;
            transition: all 0.2s;
            position: relative;
            top: 2px;
        }

        .ps-tab.active {
            background: #fff;
            color: #2e7d32;
            border-color: #2e7d32 #2e7d32 #fff #2e7d32;
            border-bottom: 2px solid #fff;
        }

        .ps-tab .ps-tab-remove {
            background: #e53935;
            color: white;
            border: none;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 11px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .btn-add-set {
            background: #1976d2;
            color: white;
            border: none;
            border-radius: 6px 6px 0 0;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            position: relative;
            top: 2px;
        }

        .btn-add-set:hover {
            background: #1565c0;
        }

        .ps-panel {
            display: none;
        }

        .ps-panel.active {
            display: block;
        }

        .ps-branches-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
            padding: 10px 0;
            border-top: 1px solid #f0f0f0;
        }

        .ps-srp-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }

        .ps-srp-label {
            font-size: 13px;
            font-weight: 700;
            color: #333;
            min-width: 50px;
        }

        .ps-srp-input {
            flex: 1;
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            max-width: 220px;
        }

        .ps-branches-label {
            font-size: 13px;
            font-weight: 700;
            color: #333;
            min-width: 140px;
        }

        .ps-branches-display {
            flex: 1;
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 12px;
            background: #fafafa;
            cursor: pointer;
            min-height: 32px;
            color: #555;
        }

        .ps-select-btn {
            background: #2e7d32;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .ps-select-btn:hover {
            background: #1b5e20;
        }

        /* Mobile Price Pagination Styles */
        .price-mobile-view {
            display: none;
        }

        .price-desktop-view {
            display: grid;
        }

        .price-mobile-pages {
            position: relative;
            min-height: 400px;
        }

        .price-mobile-page {
            display: none;
        }

        .price-mobile-page.active {
            display: block;
        }

        .price-mobile-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-top: 20px;
            border-top: 1px solid #eee;
        }

        .pagination-btn {
            background: #2e7d32;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .pagination-btn:hover {
            background: #1b5e20;
        }

        .pagination-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .pagination-info {
            font-size: 13px;
            font-weight: 600;
            color: #333;
        }

        .pagination-info .current-page {
            color: #2e7d32;
        }


        @media (max-width: 1024px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1500;
            }

            .header::after {
                left: 0;
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

            .logo {
                height: 40px;
            }

            .form-container {
                padding: 20px;
            }

            .table-container {
                padding: 20px;
                overflow-x: auto;
            }

            .search-box {
                width: 200px;
            }

            table {
                min-width: 600px;
            }

            .content-header h2 {
                font-size: 18px;
            }
        }

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
                padding: 15px;
            }

            .form-container {
                padding: 15px;
            }

            .table-container {
                padding: 15px;
            }

            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .search-box {
                width: 100%;
            }

            .content-header {
                margin-bottom: 20px;
            }

            .content-header h2 {
                font-size: 16px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn-cancel,
            .btn-activate {
                width: 100%;
            }

            th,
            td {
                padding: 8px;
                font-size: 12px;
            }
        }

        /* Tablet - Show pagination for prices modal */
        @media (max-width: 1024px) {
            .price-desktop-view {
                display: none;
            }

            .price-mobile-view {
                display: block;
            }

            .modal-content {
                max-width: 90% !important;
                margin: 20px auto !important;
            }

            .price-row {
                gap: 10px;
            }

            .price-label {
                width: 120px;
            }

            .ps-srp-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .ps-srp-input {
                max-width: 100%;
            }

            .srp-hint {
                display: block;
                margin-left: 0 !important;
            }

            .ps-branches-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .ps-branches-label {
                min-width: auto;
            }

            .ps-branches-display {
                width: 100%;
            }
        }

        /* Phone - More compact pagination */
        @media (max-width: 768px) {
            .modal-content {
                max-width: 95% !important;
                margin: 10px auto !important;
            }

            .modal-body {
                padding: 10px 15px !important;
            }

            .modal-footer {
                padding: 15px 20px;
                flex-direction: column;
                gap: 10px;
            }

            .btn-modal-nav {
                width: 100%;
            }

            .price-row {
                display: flex;
                flex-direction: row;
                align-items: center;
                gap: 10px;
                margin-bottom: 12px;
                padding: 0;
                background: transparent;
                border-radius: 0;
            }

            .price-label {
                width: 140px;
                flex-shrink: 0;
                font-size: 13px;
            }

            .price-input {
                flex: 1;
                min-width: 0;
                height: 38px;
            }

            .price-check {
                width: 20px;
                height: 20px;
                flex-shrink: 0;
                margin-left: 0;
            }

            .price-mobile-pages {
                min-height: 450px;
            }

            .pagination-btn {
                padding: 10px 14px;
                font-size: 12px;
            }

            .pagination-info {
                font-size: 12px;
            }

            .ps-tabs-bar {
                padding: 0 10px;
                overflow-x: auto;
            }

            .ps-tab {
                font-size: 12px;
                padding: 6px 12px;
            }

            .btn-add-set {
                font-size: 11px;
                padding: 6px 10px;
            }

            .ps-srp-row {
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }

            .ps-srp-label {
                flex-shrink: 0;
            }

            .ps-srp-input {
                flex: 1;
            }

            .srp-hint {
                display: none;
            }

            .ps-branches-row {
                flex-direction: column;
                align-items: stretch;
            }

            .ps-select-btn {
                width: 100%;
            }
        }

        /* Custom Checkbox Dropdown Styles */
        .checkbox-dropdown {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px 12px;
            position: relative;
            margin: 0;
            user-select: none;
            background: white;
            cursor: pointer;
            font-size: 14px;
            color: #333;
        }

        .checkbox-dropdown:after {
            content: '';
            height: 0;
            position: absolute;
            width: 0;
            border: 6px solid transparent;
            border-top-color: #333;
            top: 50%;
            right: 10px;
            margin-top: -3px;
        }

        .checkbox-dropdown.is-active:after {
            border-bottom-color: #333;
            border-top-color: transparent;
            margin-top: -9px;
        }

        .checkbox-dropdown-list {
            list-style: none;
            margin: 0;
            padding: 0;
            position: absolute;
            top: 100%;
            border: 1px solid #ddd;
            border-top: none;
            left: -1px;
            right: -1px;
            opacity: 0;
            transition: opacity 0.2s;
            background: white;
            pointer-events: none;
            z-index: 100;
            max-height: 200px;
            overflow-y: auto;
            border-bottom-left-radius: 4px;
            border-bottom-right-radius: 4px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .checkbox-dropdown.is-active .checkbox-dropdown-list {
            opacity: 1;
            pointer-events: auto;
        }

        .checkbox-dropdown-list li {
            padding: 0;
        }

        .checkbox-dropdown-list li label {
            display: block;
            padding: 10px 12px;
            cursor: pointer;
            width: 100%;
            margin: 0;
            font-size: 14px;
            color: #333;
        }

        .checkbox-dropdown-list li label:hover {
            background-color: #f5f5f5;
        }

        .checkbox-dropdown-list li input[type="checkbox"] {
            margin-right: 10px;
            width: auto;
        }

        .checkbox-dropdown-list li.select-all {
            border-bottom: 1px solid #ddd;
            background-color: #f9f9f9;
        }

        .checkbox-dropdown-list li.select-all label {
            font-weight: 600;
        }

        .branch-item-label:hover {
            background-color: #f5f5f5;
        }

        #selectedBranchesDisplay {
            cursor: pointer;
            background-color: #fff;
        }


        .branches-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .branches-table thead {
            background: var(--color-gold-pale);
        }

        .branches-table th {
            text-align: center;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            border: 1px solid #ccc;
        }

        .branches-table th:nth-child(2) {
            text-align: left;
        }

        .branches-table td {
            padding: 8px 12px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ccc;
            vertical-align: middle;
        }

        .branches-table tbody tr:hover {
            background: #fdf8f3;
        }

        .branches-table .area-cell {
            font-weight: 600;
            background-color: #f9f9f9;
            text-align: left;
        }

        .branches-table .checkbox-cell {
            text-align: center;
            width: 60px;
            vertical-align: middle;
        }

        .branches-table .checkbox-cell input[type="checkbox"] {
            margin: 0 auto;
            display: block;
        }

        .branches-table .branch-cell {
            padding-left: 20px;
        }

        /* Toast notification */
        .toast-notification {
            position: fixed;
            top: 80px;
            right: 30px;
            background: #2e7d32;
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
            setTimeout(function () {
                var t = document.getElementById('toastMsg');
                if (t) t.classList.add('hide');
            }, 3000);
            setTimeout(function () {
                var t = document.getElementById('toastMsg');
                if (t) t.remove();
            }, 3700);
        </script>
        <?php
    endif; ?>
    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <!-- <img src="Icon/imslogo2.svg" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Item Registration</h2>
            <button class="btn-add-item" onclick="toggleForm()">Add Item</button>
        </div>

        <?php if (!empty($message)): ?>
            <div
                style="padding: 15px; margin-bottom: 20px; border-radius: 4px; background: <?php echo $messageType == 'success' ? '#e8f5e9' : '#ffebee'; ?>; color: <?php echo $messageType == 'success' ? '#2e7d32' : '#c62828'; ?>;">
                <?php echo $message; ?>
            </div>
            <?php
        endif; ?>

        <div id="formContainer" class="form-container <?php echo $editMode ? '' : 'hidden'; ?>">
            <form method="POST" action="" onsubmit="return validateItemCode()">
                <input type="hidden" name="imei_primary_order" id="imeiPrimaryOrderInput"
                    value="<?php echo ($editMode && isset($editData['serial_primary'])) ? intval($editData['serial_primary']) : '1'; ?>">
                <?php if ($editMode): ?>
                    <input type="hidden" name="item_id" value="<?php echo $editData['id']; ?>">
                    <?php
                endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Item Code</label>
                        <input type="text" placeholder="Enter Item Code" name="item_code" id="itemCodeInput" required
                            value="<?php echo $editMode ? $editData['item_code'] : ''; ?>"
                            oninput="replaceSpacesWithDash(this)" <?php if ($editMode && isset($editData['item_code_locked']) && $editData['item_code_locked']): ?> readonly
                                style="background-color: #f5f5f5; cursor: not-allowed; color: #666; border: 1px solid #e0e0e0;"
                                title="This item code cannot be changed because it is being used in other records (Purchase Orders, Sales Entries, Stock, Preorders, Allocations, etc.). Only Super-Admin can edit it."
                            <?php endif; ?>>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" placeholder="Enter Description" name="description"
                            value="<?php echo $editMode ? $editData['description'] : ''; ?>"
                            oninput="this.value = this.value.toUpperCase()">
                    </div>

                    <div class="form-group">
                        <label>Brand</label>
                        <select name="brand" id="brandSelect" required onchange="loadFamilyCodesByBrand()">
                            <option value="">Select Brand</option>
                            <?php
                            if ($brands)
                                $brands->data_seek(0);
                            while ($row = $brands->fetch_assoc()): ?>
                                <option value="<?php echo $row['brand_name']; ?>" <?php echo ($editMode && $editData['brand'] == $row['brand_name']) ? 'selected' : ''; ?>>
                                    <?php echo $row['brand_name']; ?>
                                </option>
                                <?php
                            endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Family Code</label>
                        <select name="family_code" id="familyCodeSelect" required <?php echo (!$editMode) ? 'disabled' : ''; ?>>
                            <option value="">Please select a brand first</option>
                            <?php
                            if ($editMode && $family_codes) {
                                $family_codes->data_seek(0);
                                while ($row = $family_codes->fetch_assoc()): ?>
                                    <option value="<?php echo $row['family_code']; ?>" <?php echo ($editMode && $editData['family_code'] == $row['family_code']) ? 'selected' : ''; ?>>
                                        <?php echo $row['family_code']; ?>
                                    </option>
                                    <?php
                                endwhile;
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Group</label>
                        <select name="group" required>
                            <option value="">Select Group</option>
                            <?php
                            if ($groups)
                                $groups->data_seek(0);
                            while ($row = $groups->fetch_assoc()): ?>
                                <option value="<?php echo $row['group_name']; ?>" <?php echo ($editMode && $editData['group_name'] == $row['group_name']) ? 'selected' : ''; ?>>
                                    <?php echo $row['group_name']; ?>
                                </option>
                                <?php
                            endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" id="department-select" required>
                            <option value="">Select Department</option>
                            <?php
                            if ($departments)
                                $departments->data_seek(0);
                            while ($row = $departments->fetch_assoc()): ?>
                                <option value="<?php echo $row['department_name']; ?>" <?php echo ($editMode && $editData['department'] == $row['department_name']) ? 'selected' : ''; ?>>
                                    <?php echo $row['department_name']; ?>
                                </option>
                                <?php
                            endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>SRP</label>
                        <div class="form-row-custom">
                            <input type="number" step="0.01" style="flex:1;" name="srp" id="mainSRP"
                                value="<?php echo $editMode ? $editData['srp'] : ''; ?>" readonly>
                            <button type="button" class="btn-set-prices" style="align-self: center;"
                                onclick="openPricesModal()">Set Prices</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Commission</label>
                        <div class="form-row-custom">
                            <input type="number" placeholder="Enter Commission" step="0.01" style="flex:1;"
                                name="commission" value="<?php echo $editMode ? $editData['commission'] : ''; ?>">
                            <input type="checkbox" name="has_commission" class="checkbox-large" <?php echo ($editMode && $editData['has_commission']) ? 'checked' : ''; ?>>
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>TC Commission</label>
                        <input type="number" placeholder="Enter TC Commission" step="0.01" name="tc_commission"
                            value="<?php echo $editMode ? $editData['tc_commission'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Points</label>
                        <div class="form-row-custom">
                            <input type="number" placeholder="Enter Points" step="0.01" name="points" style="flex:1;"
                                value="<?php echo $editMode ? $editData['points'] : ''; ?>">
                            <input type="checkbox" name="has_points" class="checkbox-large" <?php echo ($editMode && $editData['has_points']) ? 'checked' : ''; ?>>
                        </div>
                    </div>
                </div>

                <div class="features-grid">
                    <div class="feature-block">
                        <div class="freebies-box">
                            <div class="freebies-header-row">
                                <div class="freebies-title-group"
                                    onclick="document.getElementById('chkFreebies').click();" style="cursor: pointer;">
                                    <span class="freebies-label">Freebies</span>
                                    <input type="checkbox" name="has_freebies" id="chkFreebies" class="hidden-checkbox"
                                        onchange="toggleFreebies()" <?php echo ($editMode && $editData['has_freebies']) ? 'checked' : ''; ?>>
                                    <div
                                        class="toggle-dot <?php echo ($editMode && $editData['has_freebies']) ? 'active' : ''; ?>">
                                    </div>
                                </div>
                                <button type="button" class="btn-add-more" id="btnAddFreebie"
                                    onclick="addFreebieRow()">Add Freebies</button>
                            </div>

                            <div id="freebiesTableContainer" class="freebies-table-container">
                                <table class="freebies-table" id="freebiesTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 55%;">Item Code</th>
                                            <th style="width: 25%;">Quantity</th>
                                            <th style="width: 20%; text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="freebiesTableBody">
                                        <?php if ($editMode && !empty($editFreebies)): ?>
                                            <?php foreach ($editFreebies as $freebie): ?>
                                                <?php
                                                // Parse freebie format: "ITEM_CODE (qty: X)" or just "ITEM_CODE"
                                                $itemCode = $freebie;
                                                $qty = 1;
                                                if (preg_match('/^(.+?)\s*\(qty:\s*(\d+)\)$/i', $freebie, $matches)) {
                                                    $itemCode = trim($matches[1]);
                                                    $qty = intval($matches[2]);
                                                }
                                                ?>
                                                <tr class="freebie-row">
                                                    <td>
                                                        <div class="input-with-icon">
                                                            <input type="text" name="freebie_item_code[]"
                                                                value="<?php echo htmlspecialchars($itemCode); ?>"
                                                                placeholder="Enter item code" class="freebie-input">
                                                            <button type="button" class="btn-icon-action btn-search"
                                                                onclick="searchFreebieItem(this)" title="Search Item">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                                                    stroke="currentColor" stroke-width="2">
                                                                    <circle cx="11" cy="11" r="8"></circle>
                                                                    <path d="m21 21-4.35-4.35"></path>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="number" name="freebie_quantity[]"
                                                            value="<?php echo $qty; ?>" min="1" placeholder="1"
                                                            class="freebie-input">
                                                    </td>
                                                    <td class="action-cell">
                                                        <button type="button" class="btn-icon-action btn-cross"
                                                            onclick="removeFreebieRow(this)" title="Remove">×</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr class="freebie-row">
                                                <td>
                                                    <div class="input-with-icon">
                                                        <input type="text" name="freebie_item_code[]"
                                                            placeholder="Enter item code" class="freebie-input">
                                                        <button type="button" class="btn-icon-action btn-search"
                                                            onclick="searchFreebieItem(this)" title="Search Item">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                                                stroke="currentColor" stroke-width="2">
                                                                <circle cx="11" cy="11" r="8"></circle>
                                                                <path d="m21 21-4.35-4.35"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="number" name="freebie_quantity[]" value="1" min="1"
                                                        placeholder="1" class="freebie-input">
                                                </td>
                                                <td class="action-cell">
                                                    <button type="button" class="btn-icon-action btn-cross"
                                                        onclick="removeFreebieRow(this)">X</button>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="feature-block">
                        <div class="btn-toggle-group">
                            <div class="btn-toggle">DISCOUNT</div>
                            <input type="checkbox" name="has_discount" class="checkbox-large" <?php echo ($editMode && $editData['has_discount']) ? 'checked' : ''; ?>>
                        </div>
                        <div class="btn-toggle-group">
                            <div class="btn-toggle" id="imei1-label">IMEI</div>
                            <input type="checkbox" name="use_serial" class="checkbox-large" <?php echo ($editMode && $editData['has_serial']) ? 'checked' : ''; ?>>
                        </div>
                        <div class="btn-toggle-group" id="imei2-toggle" style="display: none;">
                            <div class="btn-toggle">IMEI 2</div>
                            <input type="checkbox" name="use_serial_2" class="checkbox-large" <?php echo ($editMode && isset($editData['has_serial_2']) && $editData['has_serial_2']) ? 'checked' : ''; ?>>
                        </div>
                        <div class="btn-toggle-group" id="serial-number-toggle" style="display: none;">
                            <div class="btn-toggle">SERIAL NUMBER</div>
                            <input type="checkbox" name="use_serial_number" class="checkbox-large" <?php echo ($editMode && isset($editData['has_serial_number']) && $editData['has_serial_number']) ? 'checked' : ''; ?>>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <div class="btn-toggle-group" style="margin-bottom: 0;">
                                <div class="btn-toggle">VOUCHER</div>
                                <input type="checkbox" name="has_voucher" class="checkbox-large" <?php echo ($editMode && $editData['has_voucher']) ? 'checked' : ''; ?>>
                            </div>
                            <input type="text" name="voucher_amount" placeholder="Voucher Amount"
                                value="<?php echo ($editMode && isset($editData['voucher_amount']) && $editData['voucher_amount'] > 0) ? number_format($editData['voucher_amount'], 2) : ''; ?>"
                                style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; width: 100%; max-width: 160px;">
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <div class="btn-toggle-group" style="margin-bottom: 0;">
                                <div class="btn-toggle">TOKEN</div>
                                <input type="checkbox" name="has_token" class="checkbox-large" <?php echo ($editMode && isset($editData['has_token']) && $editData['has_token']) ? 'checked' : ''; ?>>
                            </div>
                            <input type="text" name="token_amount" placeholder="Token Amount"
                                value="<?php echo ($editMode && isset($editData['token_amount']) && $editData['token_amount'] > 0) ? number_format($editData['token_amount'], 2) : ''; ?>"
                                style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; width: 100%; max-width: 160px;">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="toggleForm()">Cancel</button>
                    <?php if ($editMode): ?>
                        <button type="button" class="btn-delete"
                            onclick="confirmDelete(<?php echo $editData['id']; ?>)">Delete</button>
                        <?php
                    endif; ?>
                    <button type="submit" name="save_item"
                        class="btn-save"><?php echo $editMode ? 'Update' : 'Save'; ?></button>
                </div>

                <!-- Prices Modal (Multi-Set) -->
                <div id="pricesModal" class="modal">
                    <div class="modal-content" style="max-width:1060px; margin-top:2%;">
                        <div class="modal-header" style="padding:16px 0;">Prices</div>
                        <div class="modal-body"
                            style="padding:15px 30px; max-height:calc(100vh - 120px); overflow-y:auto;">

                            <!-- Tab bar -->
                            <div class="ps-tabs-bar" id="psTabs"></div>

                            <!-- Sets container -->
                            <div id="psSetsContainer"></div>

                            <!-- Hidden JSON output -->
                            <input type="hidden" name="price_sets_json" id="priceSetsJson">

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-modal-nav btn-back"
                                onclick="closePricesModal()">Cancel</button>
                            <button type="button" class="btn-modal-nav btn-next" onclick="applyPrices()">Apply</button>
                        </div>
                    </div>
                </div>

                <!-- Branches Modal -->
                <div id="branchesModal" class="modal">
                    <div class="modal-content" style="max-width: 1000px;">
                        <div class="modal-header">
                            Select Branches
                        </div>
                        <div class="modal-body" style="padding: 20px;">
                            <div style="margin-bottom: 15px;">
                                <input type="text" id="branchSearchInput" placeholder="Search branches..."
                                    onkeyup="filterBranches()"
                                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
                            </div>
                            <div style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                                <label
                                    style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                                    <input type="checkbox" id="selectAllBranchesModal"
                                        style="width: 18px; height: 18px;" onchange="toggleBranchModalSelectAll()">
                                    Select All
                                </label>
                            </div>
                            <div id="branchListContainer" style="max-height: 400px; overflow-y: auto;">
                                <?php
                                if ($branches && $branches->num_rows > 0) {
                                    $branches->data_seek(0);
                                    $saved_branches = ($editMode && !empty($editData['branch'])) ? explode(', ', $editData['branch']) : [];
                                    $branches_by_area = [];

                                    while ($row = $branches->fetch_assoc()) {
                                        $area = !empty($row['area']) ? $row['area'] : 'Uncategorized';
                                        $branches_by_area[$area][] = $row;
                                    }
                                    ksort($branches_by_area);

                                    echo '<table class="branches-table">';
                                    echo '<thead>';
                                    echo '<tr>';
                                    echo '<th style="width: 60px; text-align: center;"></th>';
                                    echo '<th style="text-align: left;">Area</th>';
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
                                                echo '<td class="checkbox-cell" rowspan="' . $branch_count . '">';
                                                echo '<input type="checkbox" class="area-select-all" data-area="' . htmlspecialchars($area) . '" style="width:16px;height:16px;" onchange="toggleAreaSelectAll(this)">';
                                                echo '</td>';
                                                echo '<td class="area-cell" rowspan="' . $branch_count . '">' . $area_display . '</td>';
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



            </form>
        </div>

        <!-- View Branches Modal ? outside formContainer so it works even when form is hidden -->
        <div id="viewBranchesModal" class="modal">
            <div class="modal-content" style="max-width: 900px; margin: 5% auto;">
                <div class="modal-header"
                    style="display: block; text-align: center; font-weight: 700; font-size: 18px; padding: 20px 0; color: #000;">
                    Set Prices Branches
                </div>
                <div class="modal-body" style="padding: 20px;" id="viewBranchesContainer">
                    <!-- Tables will be populated here -->
                </div>
                <div class="modal-footer" style="justify-content: center;">
                    <button type="button" class="btn-close-modal" onclick="closeViewBranchesModal()">Close</button>
                </div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header-row">
                <!-- Left: title + search -->
                <div style="display:flex; flex-direction:column; align-items:flex-start; gap:6px; margin">
                    <h3>Item List</h3>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div class="search-box">
                            <svg viewBox="0 0 24 24">
                                <path
                                    d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                            </svg>
                            <input type="text" id="searchInput" placeholder="Search.."
                                onkeypress="if(event.key === 'Enter') searchTable()">
                        </div>
                        <button type="button" id="searchButton" class="btn-search"
                            onclick="searchTable()">SEARCH</button>
                    </div>
                </div>

                <!-- Right: filters -->
                <div class="table-header-controls">
                    <form method="GET" action="" class="filter-bar" id="filterForm">
                        <select name="filter_family">
                            <option value="">Select Family Code</option>
                            <option value="all" <?php echo ($filter_family === 'all') ? 'selected' : ''; ?>>All Family
                                Codes</option>
                            <?php
                            if ($family_codes)
                                $family_codes->data_seek(0);
                            while ($frow = $family_codes->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($frow['family_code']); ?>" <?php echo ($filter_family === $frow['family_code']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($frow['family_code']); ?>
                                </option>
                                <?php
                            endwhile; ?>
                        </select>

                        <select name="filter_brand">
                            <option value="">Select Brand</option>
                            <option value="all" <?php echo ($filter_brand === 'all') ? 'selected' : ''; ?>>All Brands
                            </option>
                            <?php
                            if ($brands)
                                $brands->data_seek(0);
                            while ($brow = $brands->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($brow['brand_name']); ?>" <?php echo ($filter_brand === $brow['brand_name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($brow['brand_name']); ?>
                                </option>
                                <?php
                            endwhile; ?>
                        </select>

                        <select name="filter_group">
                            <option value="">Select Group</option>
                            <option value="all" <?php echo ($filter_group === 'all') ? 'selected' : ''; ?>>All Groups
                            </option>
                            <?php
                            if ($groups)
                                $groups->data_seek(0);
                            while ($grow = $groups->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($grow['group_name']); ?>" <?php echo ($filter_group === $grow['group_name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grow['group_name']); ?>
                                </option>
                                <?php
                            endwhile; ?>
                        </select>

                        <select name="filter_dept">
                            <option value="">Select Department</option>
                            <option value="all" <?php echo ($filter_dept === 'all') ? 'selected' : ''; ?>>All Departments
                            </option>
                            <?php
                            if ($departments)
                                $departments->data_seek(0);
                            while ($drow = $departments->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($drow['department_name']); ?>" <?php echo ($filter_dept === $drow['department_name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($drow['department_name']); ?>
                                </option>
                                <?php
                            endwhile; ?>
                        </select>

                        <button type="submit" class="btn-filter-submit">FILTER</button>
                        <a href="itemreg.php" class="btn-filter-clear">CLEAR</a>
                    </form>
                </div>
            </div>

            <?php
            // Pre-fetch all __SRP__ prices for branch overrides to avoid N+1 queries
            $all_branch_srps = [];
            $srpQuery = $conn->query("SELECT item_id, branch, price_type, price FROM item_prices WHERE is_active = 1 AND price_type = '__SRP__'");
            if ($srpQuery) {
                while ($p_row = $srpQuery->fetch_assoc()) {
                    $iid = $p_row['item_id'];
                    $b = $p_row['branch'];
                    $pr = $p_row['price'];
                    $ptype = $p_row['price_type'];

                    if (!isset($all_branch_srps[$iid])) {
                        $all_branch_srps[$iid] = ['srp' => []];
                    }

                    if ($ptype === '__SRP__' && is_numeric($pr) && $pr > 0) {
                        $all_branch_srps[$iid]['srp'][$b] = $pr;
                    }
                }
            }
            ?>

            <table>
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>Family Code</th>
                        <th>Brand</th>
                        <th>Group</th>
                        <th>Department</th>
                        <th>Branch Prices</th>
                        <th>SRP</th>
                        <th>Commission</th>
                        <th>Freebies</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($items_result === null) {
                        // No filter selected - show message
                        echo "<tr><td colspan='12' style='text-align: center; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;'>
                                <div style='display: flex; flex-direction: column; align-items: center; gap: 12px;'>
                                    <svg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 24 24' fill='none' stroke='#999' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'>
                                        <circle cx='12' cy='12' r='10'></circle>
                                        <line x1='12' y1='16' x2='12' y2='12'></line>
                                        <line x1='12' y1='8' x2='12.01' y2='8'></line>
                                    </svg>
                                    <div style='color: #333; font-size: 15px; font-weight: 600;'>SELECT A FILTER TO DISPLAY THE DATA</div>
                                    <div style='color: #666; font-size: 13px;'>Please select at least one filter from the dropdowns above to view items.</div>
                                </div>
                              </td></tr>";
                    } elseif ($items_result && $items_result->num_rows > 0) {
                        while ($row = $items_result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['family_code'] ?? '�') . "</td>";
                            echo "<td>" . htmlspecialchars($row['brand']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['group_name'] ?? '�') . "</td>";
                            echo "<td>" . htmlspecialchars($row['department']) . "</td>";
                            echo "<td>";

                            $itemId = $row['id'];
                            $branches_arr = !empty($row['branch']) ? explode(',', $row['branch']) : [];
                            $branches_arr = array_map('trim', $branches_arr);
                            $branches_arr = array_filter($branches_arr);

                            // Also include any branches that exist in item_prices but might be missing from items.branch
                            if (isset($all_branch_srps[$itemId]['srp'])) {
                                $branches_arr = array_merge($branches_arr, array_keys($all_branch_srps[$itemId]['srp']));
                            }

                            $branches_arr = array_unique($branches_arr);

                            if (!empty($branches_arr)) {
                                $branchList = htmlspecialchars(implode(', ', $branches_arr), ENT_QUOTES);
                                $srpValue = htmlspecialchars($row['srp'], ENT_QUOTES);

                                $specificSrps = [];
                                foreach ($branches_arr as $bName) {
                                    if (isset($all_branch_srps[$itemId]['srp'][$bName])) {
                                        $specificSrps[$bName] = $all_branch_srps[$itemId]['srp'][$bName];
                                    }
                                }
                                $specificSrpsJson = htmlspecialchars(json_encode($specificSrps), ENT_QUOTES);

                                echo "<button type='button' class='btn-view-branches' onclick='viewBranches(\"" . $branchList . "\", \"" . $srpValue . "\", " . $specificSrpsJson . ")'>View</button>";
                            } else {
                                echo 'None';
                            }

                            echo "</td>";
                            echo "<td>" . number_format($row['srp'], 2) . "</td>";
                            echo "<td>" . number_format($row['commission'], 2) . "</td>";
                            echo "<td>" . (!empty($row['freebies']) ? htmlspecialchars($row['freebies']) : 'None') . "</td>";
                            echo "<td>";
                            echo "<span class='status-badge " . (strtolower($row['status']) == 'active' ? 'active' : 'inactive') . "'>";
                            echo htmlspecialchars($row['status']);
                            echo "</span>";
                            echo "</td>";
                            echo "<td>";
                            echo "<div class='action-cell'>";
                            // Build edit URL with filter parameters
                            $edit_url = "itemreg.php?edit=" . $row['id'];
                            if (!empty($filter_family))
                                $edit_url .= "&filter_family=" . urlencode($filter_family);
                            if (!empty($filter_brand))
                                $edit_url .= "&filter_brand=" . urlencode($filter_brand);
                            if (!empty($filter_group))
                                $edit_url .= "&filter_group=" . urlencode($filter_group);
                            if (!empty($filter_dept))
                                $edit_url .= "&filter_dept=" . urlencode($filter_dept);
                            echo "<a href='" . $edit_url . "' class='btn-edit'>Edit</a>";
                            echo "<div class='deactivate-container'>";
                            echo "<label for='status_" . $row['id'] . "'>Active</label>";
                            echo "<input type='checkbox' id='status_" . $row['id'] . "' " . (($row['status'] == 'Active') ? 'checked' : '') . " ";
                            echo "onclick='updateStatus(" . $row['id'] . ", this.checked)'>";
                            echo "</div>";
                            echo "</div>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        // No results for the selected filter
                        echo "<tr><td colspan='12' style='text-align: center !important; padding: 40px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc; color: #666;'>No items found for the selected filter(s)</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Show/hide IMEI 2 checkbox based on department selection
        document.addEventListener('DOMContentLoaded', function () {
            const departmentSelect = document.getElementById('department-select');
            const imei2Toggle = document.getElementById('imei2-toggle');
            const serialNumberToggle = document.getElementById('serial-number-toggle');
            const imei1Label = document.getElementById('imei1-label');

            function updateDepartmentSpecificCheckboxes() {
                const selectedDepartment = departmentSelect.value;

                // Handle IMEI 2 for MOBILE department
                if (selectedDepartment === 'MOBILE') {
                    imei2Toggle.style.display = 'flex';
                    // Change IMEI label to IMEI 1
                    if (imei1Label) {
                        imei1Label.textContent = 'IMEI 1';
                    }
                } else {
                    imei2Toggle.style.display = 'none';
                    // Uncheck IMEI 2 when hiding
                    const imei2Checkbox = imei2Toggle.querySelector('input[type="checkbox"]');
                    if (imei2Checkbox) {
                        imei2Checkbox.checked = false;
                    }
                }

                // Handle SERIAL NUMBER for TABLET department
                if (selectedDepartment === 'TABLET') {
                    serialNumberToggle.style.display = 'flex';
                    // Change IMEI label to IMEI 1
                    if (imei1Label) {
                        imei1Label.textContent = 'IMEI 1';
                    }
                } else {
                    serialNumberToggle.style.display = 'none';
                    // Uncheck SERIAL NUMBER when hiding
                    const serialNumberCheckbox = serialNumberToggle.querySelector('input[type="checkbox"]');
                    if (serialNumberCheckbox) {
                        serialNumberCheckbox.checked = false;
                    }
                }

                // If neither MOBILE nor TABLET, show just "IMEI"
                if (selectedDepartment !== 'MOBILE' && selectedDepartment !== 'TABLET') {
                    if (imei1Label) {
                        imei1Label.textContent = 'IMEI';
                    }
                }
            }

            // Check on page load
            updateDepartmentSpecificCheckboxes();

            // Check when department changes
            departmentSelect.addEventListener('change', updateDepartmentSpecificCheckboxes);

            // Track which serial/IMEI checkbox is checked first
            const chkImei1 = document.querySelector('input[name="use_serial"]');
            const chkImei2 = document.querySelector('input[name="use_serial_2"]');
            const chkSerialNum = document.querySelector('input[name="use_serial_number"]');
            const imeiPrimaryOrder = document.getElementById('imeiPrimaryOrderInput');

            function isSecondaryChecked() {
                return (chkImei2 && chkImei2.checked) || (chkSerialNum && chkSerialNum.checked);
            }

            if (chkImei1 && imeiPrimaryOrder) {
                chkImei1.addEventListener('change', function () {
                    if (this.checked) {
                        // If secondary is not checked yet, IMEI 1 was checked first
                        if (!isSecondaryChecked()) {
                            imeiPrimaryOrder.value = '1';
                        }
                    } else {
                        // If IMEI 1 is unchecked, but secondary is checked, make secondary primary
                        if (isSecondaryChecked()) {
                            imeiPrimaryOrder.value = '2';
                        }
                    }
                });
            }

            function handleSecondaryChange(elem) {
                if (!imeiPrimaryOrder) return;
                if (elem.checked) {
                    // If IMEI 1 is not checked yet, secondary was checked first
                    if (!chkImei1 || !chkImei1.checked) {
                        imeiPrimaryOrder.value = '2';
                    }
                } else {
                    // If secondary is unchecked, but IMEI 1 is checked, make IMEI 1 primary
                    if (chkImei1 && chkImei1.checked) {
                        imeiPrimaryOrder.value = '1';
                    }
                }
            }

            if (chkImei2) {
                chkImei2.addEventListener('change', function () {
                    handleSecondaryChange(this);
                });
            }

            if (chkSerialNum) {
                chkSerialNum.addEventListener('change', function () {
                    handleSecondaryChange(this);
                });
            }
        });

        // Validate Item Code for duplicates before form submission
        function validateItemCode() {
            const itemCode = document.querySelector('input[name="item_code"]').value.trim().toUpperCase();
            const itemId = document.querySelector('input[name="item_id"]') ? document.querySelector('input[name="item_id"]').value : '';

            if (!itemCode) {
                alert('Please enter an Item Code.');
                return false;
            }

            // Check for duplicate via AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'check_item_code.php', false); // Synchronous request
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            let isDuplicate = false;
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.exists) {
                        alert('Error: Item Code "' + itemCode + '" already exists. Please use a different Item Code.');
                        isDuplicate = true;
                    }
                }
            };

            xhr.send('item_code=' + encodeURIComponent(itemCode) + '&item_id=' + encodeURIComponent(itemId));

            return !isDuplicate;
        }

        function replaceSpacesWithDash(input) {
            input.value = input.value.toUpperCase().replace(/\s+/g, '-');
        }

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('hidden');
            document.querySelector('.main-content').classList.toggle('expanded');
            document.querySelector('.menu-btn').classList.toggle('active');
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

        function toggleForm() {
            const formContainer = document.getElementById('formContainer');
            const btnAdd = document.querySelector('.btn-add-item');

            if (formContainer.classList.contains('hidden')) {
                formContainer.classList.remove('hidden');
                btnAdd.style.display = 'none';
                <?php if (!$editMode): ?>
                    document.querySelector('form').reset();
                    // Reset freebies table to one empty row
                    document.getElementById('freebiesTableBody').innerHTML = `
                        <tr class="freebie-row">
                            <td>
                                <input type="text" name="freebie_item_code[]" placeholder="Enter item code" class="freebie-input">
                            </td>
                            <td>
                                <input type="number" name="freebie_quantity[]" value="1" min="1" placeholder="1" class="freebie-input">
                            </td>
                            <td class="action-cell">
                                <button type="button" class="btn-icon-action btn-cross" onclick="removeFreebieRow(this)">X</button>
                            </td>
                        </tr>
                    `;
                    toggleFreebies();
                    <?php
                endif; ?>
                toggleFreebies();
            } else {
                formContainer.classList.add('hidden');
                btnAdd.style.display = 'block';
                <?php if ($editMode): ?>
                    // Preserve filter parameters when canceling edit
                    const urlParams = new URLSearchParams(window.location.search);
                    const filterFamily = urlParams.get('filter_family');
                    const filterBrand = urlParams.get('filter_brand');
                    const filterGroup = urlParams.get('filter_group');
                    const filterDept = urlParams.get('filter_dept');

                    let url = 'itemreg.php';
                    const params = [];
                    if (filterFamily) params.push('filter_family=' + encodeURIComponent(filterFamily));
                    if (filterBrand) params.push('filter_brand=' + encodeURIComponent(filterBrand));
                    if (filterGroup) params.push('filter_group=' + encodeURIComponent(filterGroup));
                    if (filterDept) params.push('filter_dept=' + encodeURIComponent(filterDept));
                    if (params.length > 0) url += '?' + params.join('&');

                    window.location.href = url;
                    <?php
                endif; ?>
            }
        }

        function loadFamilyCodesByBrand() {
            const brandSelect = document.getElementById('brandSelect');
            const familyCodeSelect = document.getElementById('familyCodeSelect');
            const selectedBrand = brandSelect.value;
            const currentValue = familyCodeSelect.value; // Save current selection

            // If no brand selected, disable family code dropdown
            if (!selectedBrand) {
                familyCodeSelect.disabled = true;
                familyCodeSelect.innerHTML = '<option value="">Please select a brand first</option>';
                return;
            }

            // Enable the dropdown
            familyCodeSelect.disabled = false;

            // Clear current options except the first one
            familyCodeSelect.innerHTML = '<option value="">Select Family Code</option>';

            // Make AJAX request to get family codes
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_family_codes_by_brand.php?brand=' + encodeURIComponent(selectedBrand), true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success && response.family_codes) {
                            response.family_codes.forEach(function (familyCode) {
                                const option = document.createElement('option');
                                option.value = familyCode;
                                option.textContent = familyCode;
                                // Restore selection if it exists in new list
                                if (familyCode === currentValue) {
                                    option.selected = true;
                                }
                                familyCodeSelect.appendChild(option);
                            });
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                    }
                }
            };
            xhr.send();
        }

        function confirmDelete(itemId) {
            if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                // Preserve filter parameters when deleting
                const urlParams = new URLSearchParams(window.location.search);
                const filterFamily = urlParams.get('filter_family');
                const filterBrand = urlParams.get('filter_brand');
                const filterGroup = urlParams.get('filter_group');
                const filterDept = urlParams.get('filter_dept');

                let url = 'itemreg.php?delete=' + itemId;
                if (filterFamily) url += '&filter_family=' + encodeURIComponent(filterFamily);
                if (filterBrand) url += '&filter_brand=' + encodeURIComponent(filterBrand);
                if (filterGroup) url += '&filter_group=' + encodeURIComponent(filterGroup);
                if (filterDept) url += '&filter_dept=' + encodeURIComponent(filterDept);

                window.location.href = url;
            }
        }

        function toggleFreebies() {
            const chk = document.getElementById('chkFreebies');
            const dot = document.querySelector('.toggle-dot');
            const tableContainer = document.getElementById('freebiesTableContainer');
            const btnAdd = document.getElementById('btnAddFreebie');

            if (chk.checked) {
                dot.classList.add('active');
                tableContainer.style.display = 'block';
                btnAdd.style.display = 'block';
            } else {
                dot.classList.remove('active');
                tableContainer.style.display = 'none';
                btnAdd.style.display = 'none';
            }
        }

        function addFreebieRow() {
            const tbody = document.getElementById('freebiesTableBody');
            const tr = document.createElement('tr');
            tr.className = 'freebie-row';
            tr.innerHTML = `
                <td>
                    <div class="input-with-icon">
                        <input type="text" name="freebie_item_code[]" placeholder="Enter item code" class="freebie-input">
                        <button type="button" class="btn-icon-action btn-search" onclick="searchFreebieItem(this)" title="Search Item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </button>
                    </div>
                </td>
                <td>
                    <input type="number" name="freebie_quantity[]" value="1" min="1" placeholder="1" class="freebie-input">
                </td>
                <td class="action-cell">
                    <button type="button" class="btn-icon-action btn-cross" onclick="removeFreebieRow(this)" title="Remove">×</button>
                </td>
            `;
            tbody.appendChild(tr);
        }

        function removeFreebieRow(btn) {
            const tbody = document.getElementById('freebiesTableBody');
            const row = btn.closest('tr');

            // Keep at least one row
            if (tbody.querySelectorAll('tr').length > 1) {
                row.remove();
            } else {
                // Clear the inputs instead of removing the row
                row.querySelector('input[name="freebie_item_code[]"]').value = '';
                row.querySelector('input[name="freebie_quantity[]"]').value = '1';
            }
        }

        function lockFreebie(btn) {
            // This function is no longer needed with the new table structure
            // Kept for backward compatibility
        }

        function searchFreebieItem(btn) {
            // Get the input field in the same row
            const row = btn.closest('tr');
            const input = row.querySelector('input[name="freebie_item_code[]"]');

            // Show alert for now - can be enhanced with a modal later
            alert('Item search functionality\n\nThis will open a searchable list of items.\nFor now, please enter the item code manually.');

            // Focus the input field
            input.focus();

            // TODO: Implement modal with item list and search functionality
            // You can create a modal similar to the branches/prices modal
        }

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.querySelector('table');
            const tr = table.getElementsByTagName('tr');
            let visibleCount = 0;

            // Remove any existing "no data" row
            const existingNoData = table.querySelector('.no-search-results');
            if (existingNoData) {
                existingNoData.remove();
            }

            for (let i = 1; i < tr.length; i++) {
                // Skip the "SELECT A FILTER" message row
                if (tr[i].querySelector('td[colspan]')) {
                    continue;
                }

                let visible = false;
                const columns = tr[i].getElementsByTagName('td');
                for (let j = 0; j < columns.length; j++) {
                    const td = columns[j];
                    if (td) {
                        const txtValue = td.textContent || td.innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            visible = true;
                            break;
                        }
                    }
                }
                if (visible) {
                    tr[i].style.display = "";
                    visibleCount++;
                } else {
                    tr[i].style.display = "none";
                }
            }

            // Show "No data found" if no results and search is active
            if (visibleCount === 0 && filter.trim() !== '') {
                const tbody = table.querySelector('tbody');
                const noDataRow = document.createElement('tr');
                noDataRow.className = 'no-search-results';
                noDataRow.innerHTML = '<td colspan="12" style="text-align: center !important; padding: 30px 20px; border-left: 1px solid #ccc; border-right: 1px solid #ccc; border-bottom: 1px solid #ccc; color: #666; font-size: 14px;">No data found matching your search.</td>';
                tbody.appendChild(noDataRow);
            }
        }

        // Initialize search box state based on filter selection
        (function () {
            const hasFilter = <?php echo $has_filter ? 'true' : 'false'; ?>;
            const searchInput = document.getElementById('searchInput');
            const searchButton = document.getElementById('searchButton');

            if (!hasFilter) {
                searchInput.disabled = true;
                searchInput.placeholder = 'Select a filter first...';
                if (searchButton) {
                    searchButton.disabled = true;
                }
            }
        })();

        function updateStatus(id, isChecked) {
            // Preserve filter parameters
            const urlParams = new URLSearchParams(window.location.search);
            const filterFamily = urlParams.get('filter_family');
            const filterBrand = urlParams.get('filter_brand');
            const filterGroup = urlParams.get('filter_group');
            const filterDept = urlParams.get('filter_dept');

            let url = `itemreg.php?status_update=1&id=${id}&status=${isChecked}`;
            if (filterFamily) url += `&filter_family=${encodeURIComponent(filterFamily)}`;
            if (filterBrand) url += `&filter_brand=${encodeURIComponent(filterBrand)}`;
            if (filterGroup) url += `&filter_group=${encodeURIComponent(filterGroup)}`;
            if (filterDept) url += `&filter_dept=${encodeURIComponent(filterDept)}`;

            window.location.href = url;
        }

        // ============================================================
        //  MULTI-SET PRICE SYSTEM
        // ============================================================

        // Fetch branches from database for "Others Bank" dropdown
        const PHP_BRANCHES = <?php
        $branches_for_dropdown = [];
        $branches_query = $conn->query("SELECT branch_name, branch_code FROM branches WHERE status='Active' ORDER BY branch_name");
        if ($branches_query && $branches_query->num_rows > 0) {
            while ($branch_row = $branches_query->fetch_assoc()) {
                $branches_for_dropdown[] = [
                    'name' => $branch_row['branch_name'],
                    'code' => $branch_row['branch_code']
                ];
            }
        }
        echo json_encode($branches_for_dropdown);
        ?>;

        // Define default price types including bank installment options
        const DEFAULT_PRICE_TYPES = [
            'BDO Straight',
            'BDO 3 Months',
            'BDO 6 Months',
            'BDO 12 Months',
            'BDO 24 Months',
            'BPI Straight',
            'BPI 3 Months',
            'BPI 6 Months',
            'BPI 12 Months',
            'BPI 24 Months',
            'E-West Straight',
            'E-West 3 Months',
            'E-West 6 Months',
            'E-West 12 Months',
            'E-West 24 Months',
            'MBTC Straight',
            'MBTC 3 Months',
            'MBTC 6 Months',
            'MBTC 12 Months',
            'MBTC 24 Months',
            'PNB Straight',
            'PNB 3 Months',
            'PNB 6 Months',
            'PNB 12 Months',
            'PNB 24 Months',
            'RCBC Straight',
            'RCBC 3 Months',
            'RCBC 6 Months',
            'RCBC 12 Months',
            'RCBC 24 Months',
            'Dealer Price',
            'Others Bank'
        ];

        // Collect all price type keys from saved data (excluding internal __SRP__ key)
        // and merge with default types
        const PRICE_TYPES = (function () {
            const types = new Set(DEFAULT_PRICE_TYPES);
            const editData = <?php echo json_encode($editPrices); ?>;
            if (editData && typeof editData === 'object') {
                Object.values(editData).forEach(function (branchPrices) {
                    Object.keys(branchPrices).forEach(function (key) {
                        if (key !== '__SRP__') types.add(key);
                    });
                });
            }
            return Array.from(types);
        })();

        let priceSets = [];
        let activeSetIdx = 0;
        let psActiveBranchSet = null;

        // PHP edit-mode data
        const phpEditPrices = <?php echo json_encode($editPrices); ?>;

        function buildInitialSets() {
            if (!phpEditPrices || Object.keys(phpEditPrices).length === 0) {
                priceSets = [newEmptySet('Price Set 1')];
                return;
            }
            const groups = [];
            Object.keys(phpEditPrices).forEach(branch => {
                const bPrices = phpEditPrices[branch];
                let matched = null;

                // Check if this branch's price profile (including SRP) matches any existing group
                for (const g of groups) {
                    if (priceProfilesMatch(g.prices, g.actives, g.srp, bPrices)) {
                        matched = g;
                        break;
                    }
                }

                if (matched) {
                    if (branch !== '') matched.branches.push(branch);
                } else {
                    const prices = {}, actives = {};
                    let srp = '';
                    Object.entries(bPrices).forEach(([type, info]) => {
                        if (type === '__SRP__') { srp = info.price; return; }
                        // Only process if there's actually a price OR if it's marked active
                        if (info.price > 0 || info.active) {
                            if (info.price > 0) prices[type] = info.price;
                            actives[type] = info.active ? true : false;
                        }
                    });
                    groups.push({ label: 'Price Set ' + (groups.length + 1), srp, branches: branch === '' ? [] : [branch], prices, actives });
                }
            });
            priceSets = groups.length ? groups : [newEmptySet('Price Set 1')];
        }

        function priceProfilesMatch(p1, a1, srp1, bPrices) {
            // First check if SRP values match
            const bSrp = bPrices['__SRP__'] ? String(bPrices['__SRP__'].price) : '';
            if (String(srp1) !== bSrp) return false;

            // Then check other price types
            for (const type of PRICE_TYPES) {
                const v1 = p1[type] !== undefined ? String(p1[type]) : '';
                const v2 = (bPrices[type] && bPrices[type].price > 0) ? String(bPrices[type].price) : '';
                if (v1 !== v2) return false;
                const ac1 = a1[type] ? true : false;
                const ac2 = bPrices[type] ? (bPrices[type].active ? true : false) : false;
                if (ac1 !== ac2) return false;
            }
            return true;
        }

        function newEmptySet(label) {
            return { label, srp: '', branches: [], prices: {}, actives: {} };
        }

        function renderTabs() {
            const bar = document.getElementById('psTabs');
            bar.innerHTML = '';
            priceSets.forEach((set, idx) => {
                const tab = document.createElement('div');
                tab.className = 'ps-tab' + (idx === activeSetIdx ? ' active' : '');
                const lbl = document.createElement('span');
                lbl.textContent = set.label;
                lbl.onclick = () => switchSet(idx);
                tab.appendChild(lbl);
                if (priceSets.length > 1) {
                    const rm = document.createElement('button');
                    rm.className = 'ps-tab-remove';
                    rm.title = 'Remove this price set';
                    rm.innerHTML = '&#x2715;';
                    rm.onclick = (e) => { e.stopPropagation(); removeSet(idx); };
                    tab.appendChild(rm);
                }
                bar.appendChild(tab);
            });
            const addBtn = document.createElement('button');
            addBtn.className = 'btn-add-set';
            addBtn.innerHTML = '+ Add Price Set';
            addBtn.onclick = addSet;
            bar.appendChild(addBtn);
        }

        function renderPanels() {
            const container = document.getElementById('psSetsContainer');
            container.innerHTML = '';
            priceSets.forEach((set, idx) => {
                const panel = document.createElement('div');
                panel.className = 'ps-panel' + (idx === activeSetIdx ? ' active' : '');
                panel.id = 'psPanel_' + idx;
                panel.innerHTML = buildPanelHTML(set, idx);
                container.appendChild(panel);
            });
        }

        function buildPanelHTML(set, idx) {
            const splitPoint = Math.ceil(PRICE_TYPES.length / 2);
            const col1 = PRICE_TYPES.slice(0, splitPoint);
            const col2 = PRICE_TYPES.slice(splitPoint);

            // For mobile: split into 4 pages (8 items per page)
            const itemsPerPage = 8;
            const page1 = PRICE_TYPES.slice(0, itemsPerPage);
            const page2 = PRICE_TYPES.slice(itemsPerPage, itemsPerPage * 2);
            const page3 = PRICE_TYPES.slice(itemsPerPage * 2, itemsPerPage * 3);
            const page4 = PRICE_TYPES.slice(itemsPerPage * 3);

            const makeRows = (types) => types.map(t => {
                const val = (set.prices[t] !== undefined && set.prices[t] !== '') ? set.prices[t] : '';
                const formattedVal = formatNumberWithCommas(val);
                const chk = set.actives[t] ? 'checked' : '';
                const esc = t.replace(/'/g, '&#39;');

                // Special handling for "Others Bank" - render as multi-select dropdown with branches
                if (t === 'Others Bank') {
                    // Get selected branches from stored value (comma-separated)
                    const selectedBranches = val ? val.split(',').map(b => b.trim()) : [];
                    const dropdownId = `otherBranchesDropdown_${idx}`;
                    const displayId = `otherBranchesDisplay_${idx}`;

                    const branchOptions = PHP_BRANCHES.map(branch => {
                        const isChecked = selectedBranches.includes(branch.name) ? 'checked' : '';
                        const branchEsc = branch.name.replace(/'/g, '&#39;');
                        return `<label class="branch-checkbox-option">
                            <input type="checkbox" value="${branchEsc}" ${isChecked} onchange="updateOtherBranchesSelection(${idx})"> ${branchEsc}
                        </label>`;
                    }).join('');

                    const displayText = selectedBranches.length > 0 ? selectedBranches.join(', ') : 'Select branches...';

                    return `<div class="price-row price-row-others-bank">
                        <span class="price-label">${esc}:</span>
                        <div class="others-bank-dropdown-container">
                            <div class="others-bank-display" id="${displayId}" onclick="toggleOtherBranchesDropdown(${idx})">
                                ${displayText}
                            </div>
                            <div class="others-bank-dropdown" id="${dropdownId}" style="display:none;">
                                ${branchOptions}
                            </div>
                            <input type="hidden" class="price-input ps-price-input" data-set="${idx}" data-type="${esc}" value="${val}">
                        </div>
                        <input type="checkbox" class="price-check ps-active-check" data-set="${idx}" data-type="${esc}" ${chk}>
                    </div>`;
                }

                return `<div class="price-row">
                    <span class="price-label">${esc}:</span>
                    <input type="text" class="price-input ps-price-input" data-set="${idx}" data-type="${esc}" value="${formattedVal}" oninput="formatPsInput(this)">
                    <input type="checkbox" class="price-check ps-active-check" data-set="${idx}" data-type="${esc}" ${chk}>
                </div>`;
            }).join('');

            const branchDisplay = set.branches.length
                ? set.branches.join(', ')
                : '<em style="color:#aaa;">No branches (global price)</em>';

            return `
            <!-- Desktop view: 2 columns -->
            <div class="price-cols price-desktop-view">
                <div>${makeRows(col1)}</div>
                <div>${makeRows(col2)}</div>
            </div>
            
            <!-- Mobile/Tablet view: Paginated -->
            <div class="price-mobile-view">
                <div class="price-mobile-pages">
                    <div class="price-mobile-page active" data-page="1">
                        ${makeRows(page1)}
                    </div>
                    <div class="price-mobile-page" data-page="2">
                        ${makeRows(page2)}
                    </div>
                    <div class="price-mobile-page" data-page="3">
                        ${makeRows(page3)}
                    </div>
                    <div class="price-mobile-page" data-page="4">
                        ${makeRows(page4)}
                    </div>
                </div>
                <div class="price-mobile-pagination">
                    <button type="button" class="pagination-btn" onclick="changePricePage(${idx}, -1)">← Previous</button>
                    <span class="pagination-info">Page <span class="current-page">1</span> of 4</span>
                    <button type="button" class="pagination-btn" onclick="changePricePage(${idx}, 1)">Next →</button>
                </div>
            </div>
            
            <div class="ps-srp-row">
                <span class="ps-srp-label">SRP:</span>
                <input type="text" class="ps-srp-input" data-set="${idx}" value="${formatNumberWithCommas(set.srp || '')}" placeholder="Enter SRP" oninput="formatPsInput(this)">
                <span style="font-size:12px;color:#888;margin-left:10px;" class="srp-hint">SRP for all branches in this set.</span>
            </div>
            <div class="ps-branches-row">
                <span class="ps-branches-label">Set Price for Branches:</span>
                <div class="ps-branches-display" id="psBranchDisplay_${idx}" onclick="openBranchPickerForSet(${idx})">${branchDisplay}</div>
                <button type="button" class="ps-select-btn" onclick="openBranchPickerForSet(${idx})">Select</button>
            </div>`;
        }

        function syncSetDataFromDOM(idx) {
            const panel = document.getElementById('psPanel_' + idx);
            if (!panel) return;

            // Clear out old prices/actives first, then rebuild from DOM
            priceSets[idx].prices = {};
            priceSets[idx].actives = {};

            // Determine which view is currently visible (desktop or mobile)
            // so we don't double-read duplicate inputs from the hidden view.
            const desktopView = panel.querySelector('.price-desktop-view');
            const mobileView = panel.querySelector('.price-mobile-view');
            const useDesktop = desktopView && window.getComputedStyle(desktopView).display !== 'none';

            // Pick the container whose inputs we should read
            const activeView = useDesktop ? desktopView : mobileView;
            if (!activeView) return;

            activeView.querySelectorAll('.ps-price-input').forEach(inp => {
                const val = inp.value.replace(/,/g, '').trim();
                // Only store non-empty values
                if (val !== '') {
                    priceSets[idx].prices[inp.dataset.type] = val;
                }
            });

            // For checkboxes, read from the active view as well
            activeView.querySelectorAll('.ps-active-check').forEach(chk => {
                priceSets[idx].actives[chk.dataset.type] = chk.checked;
            });

            // For any price type not present in the active view's checkboxes,
            // fall back to reading from the full panel so unchecked types are still stored.
            panel.querySelectorAll('.ps-active-check').forEach(chk => {
                if (!(chk.dataset.type in priceSets[idx].actives)) {
                    priceSets[idx].actives[chk.dataset.type] = chk.checked;
                }
            });

            const srpInp = panel.querySelector('.ps-srp-input');
            if (srpInp) priceSets[idx].srp = srpInp.value.replace(/,/g, '');
        }

        function switchSet(idx) {
            syncSetDataFromDOM(activeSetIdx);
            activeSetIdx = idx;
            renderTabs();
            renderPanels();
        }

        function changePricePage(setIdx, direction) {
            const panel = document.getElementById('psPanel_' + setIdx);
            if (!panel) return;

            const pages = panel.querySelectorAll('.price-mobile-page');
            const pageInfo = panel.querySelector('.current-page');
            let currentPage = parseInt(pageInfo.textContent);

            currentPage += direction;
            if (currentPage < 1) currentPage = 1;
            if (currentPage > pages.length) currentPage = pages.length;

            pages.forEach((page, idx) => {
                page.classList.toggle('active', idx === currentPage - 1);
            });

            pageInfo.textContent = currentPage;
        }

        function addSet() {
            syncSetDataFromDOM(activeSetIdx);
            priceSets.push(newEmptySet('Price Set ' + (priceSets.length + 1)));
            activeSetIdx = priceSets.length - 1;
            renderTabs();
            renderPanels();
        }

        function removeSet(idx) {
            if (priceSets.length <= 1) return;
            priceSets.splice(idx, 1);
            if (activeSetIdx >= priceSets.length) activeSetIdx = priceSets.length - 1;
            renderTabs();
            renderPanels();
        }

        function formatPsInput(inp) {
            let val = inp.value.replace(/[^0-9.]/g, '');
            const parts = val.split('.');
            if (parts.length > 2) val = parts[0] + '.' + parts[1];
            if (parts[0] && parts[0].length > 3)
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            inp.value = parts.join('.');
        }

        function formatNumberWithCommas(num) {
            if (!num || num === '') return '';
            const str = num.toString();
            const parts = str.split('.');
            if (parts[0] && parts[0].length > 3)
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return parts.join('.');
        }

        // Toggle "Others Bank" dropdown visibility
        function toggleOtherBranchesDropdown(setIdx) {
            const dropdown = document.getElementById('otherBranchesDropdown_' + setIdx);
            if (!dropdown) return;

            // Close all other dropdowns first
            document.querySelectorAll('.others-bank-dropdown').forEach(dd => {
                if (dd !== dropdown) dd.style.display = 'none';
            });

            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }

        // Update "Others Bank" selection when checkboxes change
        function updateOtherBranchesSelection(setIdx) {
            const dropdown = document.getElementById('otherBranchesDropdown_' + setIdx);
            const display = document.getElementById('otherBranchesDisplay_' + setIdx);
            const panel = document.getElementById('psPanel_' + setIdx);

            if (!dropdown || !display || !panel) return;

            // Get all checked branches
            const checkedBoxes = dropdown.querySelectorAll('input[type="checkbox"]:checked');
            const selectedBranches = Array.from(checkedBoxes).map(cb => cb.value);

            // Update display text
            display.textContent = selectedBranches.length > 0 ? selectedBranches.join(', ') : 'Select branches...';

            // Update hidden input value (comma-separated)
            const hiddenInput = panel.querySelector('.ps-price-input[data-type="Others Bank"]');
            if (hiddenInput) {
                hiddenInput.value = selectedBranches.join(', ');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.others-bank-dropdown-container')) {
                document.querySelectorAll('.others-bank-dropdown').forEach(dd => {
                    dd.style.display = 'none';
                });
            }
        });

        function openPricesModal() {
            // Check if we have data in the JSON field from a previous Apply
            const jsonField = document.getElementById('priceSetsJson');
            const jsonData = jsonField ? jsonField.value : '';

            if (jsonData && jsonData.trim() !== '') {
                // Load from the saved JSON data
                try {
                    const parsed = JSON.parse(jsonData);
                    if (parsed && Array.isArray(parsed) && parsed.length > 0) {
                        priceSets = parsed.map((set, idx) => ({
                            label: set.label || 'Price Set ' + (idx + 1),
                            srp: set.srp || '',
                            branches: set.branches || [],
                            prices: set.prices || {},
                            actives: set.actives || {}
                        }));
                    } else {
                        buildInitialSets();
                    }
                } catch (e) {
                    console.error('Error parsing price sets JSON:', e);
                    buildInitialSets();
                }
            } else {
                // No saved data, build from PHP edit data
                buildInitialSets();
                // IMPORTANT: Sync the loaded PHP data to JSON field immediately
                // This ensures that if user opens modal and clicks Cancel (without Apply),
                // the database data is preserved when they click Update
                syncPriceSetsToJSON();
            }

            activeSetIdx = 0;
            renderTabs();
            renderPanels();
            document.getElementById('pricesModal').style.display = 'block';
        }

        function syncPriceSetsToJSON() {
            const payload = priceSets.map(set => ({
                label: set.label,
                branches: set.branches,
                srp: set.srp || '0',
                prices: set.prices,
                actives: set.actives
            }));
            document.getElementById('priceSetsJson').value = JSON.stringify(payload);
        }

        function closePricesModal() {
            document.getElementById('pricesModal').style.display = 'none';
        }

        function applyPrices() {
            syncSetDataFromDOM(activeSetIdx);
            const payload = priceSets.map(set => ({
                label: set.label,
                branches: set.branches,
                srp: set.srp || '0',
                prices: set.prices,  // Already filtered by syncSetDataFromDOM
                actives: set.actives
            }));
            document.getElementById('priceSetsJson').value = JSON.stringify(payload);
            // Sync first set SRP to main field
            if (priceSets.length > 0 && priceSets[0].srp) {
                document.getElementById('mainSRP').value = priceSets[0].srp;
            }
            // Show summary on the branch display in main form
            const allBranches = [...new Set(priceSets.flatMap(s => s.branches).filter(Boolean))];
            const disp = document.getElementById('selectedBranchesDisplay');
            if (disp) disp.value = allBranches.length ? allBranches.join(', ') : '';
            closePricesModal();
        }

        // --- Branch picker per set ---
        function openBranchPickerForSet(idx) {
            syncSetDataFromDOM(activeSetIdx);
            psActiveBranchSet = idx;
            document.querySelectorAll('.branch-checkbox').forEach(cb => {
                cb.checked = priceSets[idx].branches.includes(cb.value);
            });
            updateSelectAllState();
            document.getElementById('branchesModal').style.display = 'flex';
        }

        // --- Branch Modal Functions ---

        function openBranchesModal() {
            document.getElementById('branchesModal').style.display = 'flex';
            updateSelectAllState();
        }

        function closeBranchesModal() {
            document.getElementById('branchesModal').style.display = 'none';
            psActiveBranchSet = null;
        }

        function toggleBranchModalSelectAll() {
            const selectAll = document.getElementById('selectAllBranchesModal');
            const allChecked = selectAll.checked;
            const checkboxes = document.querySelectorAll('.branch-checkbox');

            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (!row || row.style.display !== 'none') {
                    cb.checked = allChecked;
                }
            });
            updateSelectAllState();
        }

        function updateSelectAllState() {
            const checkboxes = document.querySelectorAll('.branch-checkbox');
            const selectAll = document.getElementById('selectAllBranchesModal');

            if (checkboxes.length === 0) {
                if (selectAll) selectAll.checked = false;
            } else {
                let allChecked = true;
                for (let i = 0; i < checkboxes.length; i++) {
                    const row = checkboxes[i].closest('tr');
                    if (!row || row.style.display !== 'none') {
                        if (!checkboxes[i].checked) {
                            allChecked = false;
                            break;
                        }
                    }
                }
                if (selectAll) selectAll.checked = allChecked;
            }

            // Update Area Select All Checkboxes
            document.querySelectorAll('.area-select-all').forEach(areaCheckbox => {
                const area = areaCheckbox.getAttribute('data-area');
                const areaCheckboxes = document.querySelectorAll('.' + area + '-checkbox');

                if (areaCheckboxes.length > 0) {
                    let areaAllChecked = true;
                    areaCheckboxes.forEach(cb => {
                        const row = cb.closest('tr');
                        if (!row || row.style.display !== 'none') {
                            if (!cb.checked) {
                                areaAllChecked = false;
                            }
                        }
                    });
                    areaCheckbox.checked = areaAllChecked;
                }
            });
        }

        function applyBranchesSelection() {
            if (psActiveBranchSet !== null) {
                const selected = [];
                document.querySelectorAll('.branch-checkbox:checked').forEach(cb => selected.push(cb.value));
                priceSets[psActiveBranchSet].branches = selected;
                const disp = document.getElementById('psBranchDisplay_' + psActiveBranchSet);
                if (disp) {
                    disp.innerHTML = selected.length
                        ? selected.join(', ')
                        : '<em style="color:#aaa;">No branches (global price)</em>';
                }
                psActiveBranchSet = null;
                closeBranchesModal();
                return;
            }
            closeBranchesModal();
        }

        function toggleAreaSelectAll(checkbox) {
            const area = checkbox.getAttribute('data-area');
            const areaCheckboxes = document.querySelectorAll('.' + area + '-checkbox');

            areaCheckboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (!row || row.style.display !== 'none') {
                    cb.checked = checkbox.checked;
                }
            });
            updateSelectAllState();
        }

        function filterBranches() {
            const input = document.getElementById('branchSearchInput');
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll('.branch-row');

            rows.forEach(row => {
                const branchCell = row.querySelector('.branch-cell span');
                if (branchCell) {
                    const txtValue = branchCell.textContent || branchCell.innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                    }
                }
            });
            updateSelectAllState();
        }

        // Add event listeners to update "Select All" status when individual boxes change
        document.addEventListener('DOMContentLoaded', function () {
            const checkboxes = document.querySelectorAll('.branch-checkbox');
            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateSelectAllState);
            });

            // Initial update
            updateSelectAllState();

            // Initial text update just in case
            applyBranchesSelection();

            // Initialize family codes based on selected brand in edit mode
            <?php if ($editMode && !empty($editData['brand'])): ?>
                const brandSelect = document.getElementById('brandSelect');
                const familyCodeSelect = document.getElementById('familyCodeSelect');
                const savedFamilyCode = '<?php echo $editData['family_code']; ?>';

                if (brandSelect && brandSelect.value) {
                    // Load family codes for the selected brand
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', 'get_family_codes_by_brand.php?brand=' + encodeURIComponent(brandSelect.value), true);
                    xhr.onload = function () {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.success && response.family_codes) {
                                    familyCodeSelect.innerHTML = '<option value="">Select Family Code</option>';
                                    response.family_codes.forEach(function (familyCode) {
                                        const option = document.createElement('option');
                                        option.value = familyCode;
                                        option.textContent = familyCode;
                                        // Select the saved family code
                                        if (familyCode === savedFamilyCode) {
                                            option.selected = true;
                                        }
                                        familyCodeSelect.appendChild(option);
                                    });
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                            }
                        }
                    };
                    xhr.send();
                }
            <?php endif; ?>
        });


        // Close modal if clicked outside
        window.onclick = function (event) {
            const pricesModal = document.getElementById('pricesModal');
            const branchesModal = document.getElementById('branchesModal');
            if (event.target == pricesModal) {
                pricesModal.style.display = "none";
            }
            if (event.target == branchesModal) {
                branchesModal.style.display = "none";
            }
            // Close view modal if clicked outside
            const viewModal = document.getElementById('viewBranchesModal');
            if (event.target == viewModal) {
                viewModal.style.display = "none";
            }
        }

        // --- View Branches Modal Functions ---
        const branchAreaMap = <?php
        $branch_area_map = [];
        if (isset($branches) && $branches && $branches->num_rows > 0) {
            $branches->data_seek(0);
            while ($b_row = $branches->fetch_assoc()) {
                $b_area = !empty($b_row['area']) ? $b_row['area'] : 'Uncategorized';
                $formatted_area = ucwords(str_replace('_', ' ', $b_area));
                $branch_area_map[$b_row['branch_name']] = $formatted_area;
            }
        }
        echo json_encode($branch_area_map);
        ?>;

        function viewBranches(branchesStr, globalSrpValue, specificSrps) {
            const modal = document.getElementById('viewBranchesModal');
            const container = document.getElementById('viewBranchesContainer');
            container.innerHTML = ''; // Clear previous

            if (branchesStr) {
                const branches = branchesStr.split(', ');
                const grouped = {};

                // Group branches by Area
                branches.forEach(branch => {
                    const bName = branch.trim();
                    if (bName) {
                        // Use the map injected by PHP
                        const area = (typeof branchAreaMap !== 'undefined' && branchAreaMap[bName]) ? branchAreaMap[bName] : 'Uncategorized';

                        if (!grouped[area]) {
                            grouped[area] = [];
                        }
                        grouped[area].push(bName);
                    }
                });

                // Display groups
                const areas = Object.keys(grouped).sort();

                areas.forEach((area, index) => {
                    // Add spacing between tables
                    if (index > 0) {
                        const spacer = document.createElement('div');
                        spacer.style.height = '20px';
                        container.appendChild(spacer);
                    }

                    // Create table for this area
                    const table = document.createElement('table');
                    table.style.width = '100%';
                    table.style.borderCollapse = 'collapse';
                    table.style.marginBottom = '0';

                    // Area Header Row (full width, gray background)
                    const areaHeaderRow = document.createElement('tr');
                    const areaHeaderCell = document.createElement('td');
                    areaHeaderCell.colSpan = 2;
                    areaHeaderCell.textContent = area.toUpperCase();
                    areaHeaderCell.style.fontWeight = 'bold';
                    areaHeaderCell.style.backgroundColor = '#d3d3d3';
                    areaHeaderCell.style.padding = '12px 10px';
                    areaHeaderCell.style.border = '1px solid #999';
                    areaHeaderCell.style.textAlign = 'center';
                    areaHeaderCell.style.fontSize = '14px';
                    areaHeaderCell.style.color = '#000';
                    areaHeaderRow.appendChild(areaHeaderCell);
                    table.appendChild(areaHeaderRow);

                    // Column Headers Row
                    const headerRow = document.createElement('tr');

                    const branchHeader = document.createElement('td');
                    branchHeader.textContent = 'Branch';
                    branchHeader.style.fontWeight = 'bold';
                    branchHeader.style.backgroundColor = '#f5f5f5';
                    branchHeader.style.padding = '10px';
                    branchHeader.style.border = '1px solid #ccc';
                    branchHeader.style.textAlign = 'center';
                    branchHeader.style.width = '60%';
                    headerRow.appendChild(branchHeader);

                    const srpHeader = document.createElement('td');
                    srpHeader.textContent = 'SRP';
                    srpHeader.style.fontWeight = 'bold';
                    srpHeader.style.backgroundColor = '#f5f5f5';
                    srpHeader.style.padding = '10px';
                    srpHeader.style.border = '1px solid #ccc';
                    srpHeader.style.textAlign = 'center';
                    srpHeader.style.width = '40%';
                    headerRow.appendChild(srpHeader);

                    table.appendChild(headerRow);

                    // Branch rows
                    grouped[area].forEach(b => {
                        const row = document.createElement('tr');
                        // Branch name cell
                        const branchCell = document.createElement('td');
                        branchCell.textContent = b;
                        branchCell.style.padding = '8px 10px';
                        branchCell.style.border = '1px solid #ccc';
                        branchCell.style.color = '#333';
                        branchCell.style.textAlign = 'left';
                        branchCell.style.backgroundColor = '#fff';
                        row.appendChild(branchCell);

                        // SRP cell
                        const srpCell = document.createElement('td');
                        let finalSrp = globalSrpValue;
                        if (specificSrps && specificSrps[b]) {
                            finalSrp = parseFloat(specificSrps[b]).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        } else {
                            finalSrp = parseFloat(globalSrpValue).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                        srpCell.textContent = finalSrp;
                        srpCell.style.padding = '8px 10px';
                        srpCell.style.border = '1px solid #ccc';
                        srpCell.style.color = '#333';
                        srpCell.style.textAlign = 'center';
                        srpCell.style.backgroundColor = '#fff';
                        row.appendChild(srpCell);

                        table.appendChild(row);
                    });

                    container.appendChild(table);
                });

            } else {
                const message = document.createElement('p');
                message.textContent = 'No branches registered.';
                message.style.textAlign = 'center';
                message.style.padding = '20px';
                container.appendChild(message);
            }

            modal.style.display = 'flex';
        }

        function closeViewBranchesModal() {
            document.getElementById('viewBranchesModal').style.display = 'none';
        }

        window.onload = function () {
            <?php if ($editMode): ?>
                toggleFreebies();

                <?php
            endif; ?>
            if (document.getElementById('chkFreebies')) {
                toggleFreebies();
            }
        };


        // Format inputs with commas
        function formatNumberInput(input) {
            // Remove existing non-numeric characters (except dot)
            let val = input.value.replace(/[^0-9.]/g, '');

            // Check for multiple dots
            const parts = val.split('.');
            if (parts.length > 2) {
                val = parts[0] + '.' + parts.slice(1).join('');
            }

            // Add commas to the integer part
            if (parts[0].length > 3) {
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
            input.value = parts.join('.');
        }

        // Attach listener to numeric inputs
        document.addEventListener('DOMContentLoaded', function () {
            // Target specific inputs by name or class
            const numericInputs = document.querySelectorAll('input[name="srp"], input[name="commission"], input[name="tc_commission"], input[name="points"], input[name="voucher_amount"], input[name="token_amount"], .price-input');

            numericInputs.forEach(input => {
                // Change type to text to allow commas
                input.type = 'text';

                // Initial format
                formatNumberInput(input);

                // Add listener
                input.addEventListener('input', function () {
                    formatNumberInput(this);
                });
            });

            // Also handle the modal SRP input which has id="modalSRP" but no name
            const modalSRP = document.getElementById('modalSRP');
            if (modalSRP) {
                modalSRP.type = 'text';
                formatNumberInput(modalSRP);
                modalSRP.addEventListener('input', function () {
                    formatNumberInput(this);
                });
            }
        });
    </script>
</body>

</html>