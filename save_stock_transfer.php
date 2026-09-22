<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'session_check.php';
include 'config.php';

ob_clean();
header('Content-Type: application/json');

// Ensure tables exist
$create_transfers = "CREATE TABLE IF NOT EXISTS stock_transfers (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    st_number VARCHAR(50) NOT NULL UNIQUE,
    st_date DATE NOT NULL,
    branch_from VARCHAR(255),
    branch_to VARCHAR(255),
    store_name VARCHAR(255),
    prepared_by VARCHAR(100),
    approver VARCHAR(100),
    approval_date DATETIME,
    status VARCHAR(20) DEFAULT 'Pending',
    remarks TEXT,
    total_quantity INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_transfers);

$create_items = "CREATE TABLE IF NOT EXISTS stock_transfer_items (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    st_number VARCHAR(50) NOT NULL,
    item_code VARCHAR(50),
    item_description TEXT,
    imei VARCHAR(100),
    quantity INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_items);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$st_number_provided = isset($_POST['st_number']) ? $conn->real_escape_string($_POST['st_number']) : '';
$st_date_raw = isset($_POST['st_date']) ? $_POST['st_date'] : '';
$branch_from = isset($_POST['branch_from']) ? $conn->real_escape_string($_POST['branch_from']) : '';
$branch_to = isset($_POST['branch_to']) ? $conn->real_escape_string($_POST['branch_to']) : '';
$store_name = isset($_POST['store_name']) ? $conn->real_escape_string($_POST['store_name']) : '';
$remarks = isset($_POST['remarks']) ? $conn->real_escape_string($_POST['remarks']) : '';
$prepared_by = isset($_SESSION['username']) ? $conn->real_escape_string($_SESSION['username']) : '';
$items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

// Generate ST Number using booklet configuration
include_once 'get_next_invoice_number.php';

// Get branch code from session/form
$user_branch_code = '000';
if (isset($_SESSION['user_branch'])) {
    $user_branch_name = $_SESSION['user_branch'];
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch_name'");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $user_branch_code = $branch_data['branch_code'];
    }
}

$booklet = getBookletConfig($conn, $user_branch_code, 'stocktransfer');

if ($booklet) {
    // Use booklet number configuration
    $st_number = generateInvoiceNumber($booklet);
    
    // Auto-increment for numeric formats
    // Also increment 'custom' format if current_number is purely numeric (e.g. 0000001)
    if ($booklet['booklet_format'] === 'numeric') {
        $next_number = incrementInvoiceNumber($booklet['current_number'], 'numeric');
        updateInvoiceNumber($conn, $booklet['id'], $next_number);
    } elseif ($booklet['booklet_format'] === 'custom' && is_numeric(ltrim($booklet['current_number'], '0') ?: '0')) {
        // current_number is a zero-padded numeric string (e.g. "0000001") — safe to auto-increment
        $current_num = intval($booklet['current_number']);
        $padding     = strlen($booklet['current_number']);
        $next_number = str_pad($current_num + 1, $padding, '0', STR_PAD_LEFT);
        updateInvoiceNumber($conn, $booklet['id'], $next_number);
    }
} else {
    // Fallback: use provided ST number or generate one
    if (!empty($st_number_provided)) {
        $st_number = $st_number_provided;
    } else {
        // Generate fallback format: ST-YYYYMMDD-###
        $today = date('Ymd');
        $prefix = "ST-{$today}-";
        
        $st_query = $conn->query("SELECT st_number FROM stock_transfers WHERE st_number LIKE 'ST-%' ORDER BY st_number DESC LIMIT 1");
        
        if ($st_query && $st_query->num_rows > 0) {
            $row = $st_query->fetch_assoc();
            $last_st = $row['st_number'];
            // Extract the last 3 digits from ST-YYYYMMDD-### format
            $last_id = intval(substr($last_st, -3));
            $next_id = $last_id + 1;
        } else {
            $next_id = 1;
        }
        
        $formatted_id = str_pad($next_id, 3, '0', STR_PAD_LEFT);
        $st_number = $prefix . $formatted_id;
    }
}

// Convert date from MM/DD/YYYY to YYYY-MM-DD
$st_date = '';
if (!empty($st_date_raw)) {
    $date_obj = DateTime::createFromFormat('m/d/Y', $st_date_raw);
    if ($date_obj) {
        $st_date = $date_obj->format('Y-m-d');
    } else {
        // Try other formats
        $date_obj = DateTime::createFromFormat('Y-m-d', $st_date_raw);
        if ($date_obj) {
            $st_date = $date_obj->format('Y-m-d');
        }
    }
}

// Validate required fields
if (empty($st_number) || empty($st_date) || empty($branch_from) || empty($branch_to)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'No items to transfer']);
    exit;
}

// Check if any serial numbers are already in pending transfers
$pending_serials = [];
foreach ($items as $item) {
    $imei = isset($item['imei']) ? $conn->real_escape_string($item['imei']) : '';
    if (!empty($imei)) {
        // Check if this serial number exists in any pending transfer
        $check_sql = "SELECT st.st_number, st.branch_to 
                      FROM stock_transfer_items sti 
                      JOIN stock_transfers st ON sti.st_number = st.st_number 
                      WHERE sti.imei = '$imei' 
                      AND st.status = 'Pending'";
        $check_result = $conn->query($check_sql);
        
        if ($check_result && $check_result->num_rows > 0) {
            $row = $check_result->fetch_assoc();
            $pending_serials[] = $imei . ' (already in pending transfer ' . $row['st_number'] . ' to ' . $row['branch_to'] . ')';
        }
    }
}

// If there are any pending serial numbers, return error
if (!empty($pending_serials)) {
    $error_message = 'The following serial number(s) are already in pending transfers: ' . implode(', ', $pending_serials);
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit;
}

// Calculate total quantity
$total_quantity = 0;
foreach ($items as $item) {
    $total_quantity += isset($item['quantity']) ? intval($item['quantity']) : 0;
}

// Insert into stock_transfers table with Pending status
$sql = "INSERT INTO stock_transfers (st_number, st_date, branch_from, branch_to, store_name, prepared_by, status, remarks, total_quantity, created_at) 
        VALUES ('$st_number', '$st_date', '$branch_from', '$branch_to', '$store_name', '$prepared_by', 'Pending', '$remarks', $total_quantity, NOW())";

if ($conn->query($sql) === TRUE) {
    // Insert items
    $items_inserted = true;
    foreach ($items as $item) {
        $item_code = isset($item['item_code']) ? $conn->real_escape_string($item['item_code']) : '';
        $item_description = isset($item['item_description']) ? $conn->real_escape_string($item['item_description']) : '';
        $imei = isset($item['imei']) ? $conn->real_escape_string($item['imei']) : '';
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
        
        $item_sql = "INSERT INTO stock_transfer_items (st_number, item_code, item_description, imei, quantity, created_at) 
                     VALUES ('$st_number', '$item_code', '$item_description', '$imei', $quantity, NOW())";
        
        if ($conn->query($item_sql) !== TRUE) {
            $items_inserted = false;
            break;
        }
    }
    
    if ($items_inserted) {
        echo json_encode(['success' => true, 'message' => 'Stock transfer saved successfully and pending approval']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error saving transfer items: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Error saving transfer: ' . $conn->error]);
}

$conn->close();
?>
