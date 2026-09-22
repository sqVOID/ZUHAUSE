<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output, but log them

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {

// ── Simple table creation without complex ALTER statements ──────────────────
$conn->query("CREATE TABLE IF NOT EXISTS purchase_orders (
    id                  INT(11) AUTO_INCREMENT PRIMARY KEY,
    po_number           VARCHAR(50) NOT NULL UNIQUE,
    supplier_company    VARCHAR(255),
    supplier_name       VARCHAR(255),
    contact_number      VARCHAR(100),
    address             TEXT,
    terms               VARCHAR(100),
    payment_due_date    DATE,
    remarks             TEXT,
    po_date             DATE,
    total_items         INT(11) DEFAULT 0,
    total_qty           INT(11) DEFAULT 0,
    total_cost          DECIMAL(12,2) DEFAULT 0.00,
    status              VARCHAR(50) DEFAULT 'Pending',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS purchase_order_items (
    id              INT(11) AUTO_INCREMENT PRIMARY KEY,
    po_id           INT(11) NOT NULL,
    po_number       VARCHAR(50) NOT NULL,
    item_no         INT(11),
    item_model      VARCHAR(255),
    item_description TEXT,
    serial_number   VARCHAR(255),
    quantity        INT(11) DEFAULT 0,
    cost            DECIMAL(12,2) DEFAULT 0.00,
    total           DECIMAL(12,2) DEFAULT 0.00,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Try to add serial_number column if it doesn't exist (ignore errors if it already exists)
@$conn->query("ALTER TABLE purchase_order_items ADD COLUMN serial_number VARCHAR(255) AFTER item_description");

// ── Generate next PO number (PO-YYYY-NNN) ────────────────────────────────────
$year = date('Y');
$prefix = "PO-{$year}-";

$result = $conn->query("SELECT po_number FROM purchase_orders WHERE po_number LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1");
$next_seq = 1;
if ($result && $result->num_rows > 0) {
    $last = $result->fetch_assoc()['po_number'];
    $last_seq = (int) substr($last, strlen($prefix));
    $next_seq = $last_seq + 1;
}
$po_number = $prefix . str_pad($next_seq, 3, '0', STR_PAD_LEFT);

// ── Collect form data ─────────────────────────────────────────────────────────
$supplier_company  = trim($_POST['supplier_company'] ?? '');
$supplier_name     = trim($_POST['supplier_name'] ?? '');
$contact_number    = trim($_POST['contact_number'] ?? '');
$address           = trim($_POST['address'] ?? '');
$terms             = trim($_POST['terms'] ?? '');
$payment_due_date  = trim($_POST['payment_due_date'] ?? '');
$remarks           = trim($_POST['remarks'] ?? '');
$po_date           = date('Y-m-d');
$items             = json_decode($_POST['items'] ?? '[]', true);

if (empty($supplier_company)) {
    echo json_encode(['success' => false, 'message' => 'Supplier Company Name is required.']);
    exit;
}
if (empty($items) || !is_array($items)) {
    echo json_encode(['success' => false, 'message' => 'Please add at least one item.']);
    exit;
}

// ── Calculate totals ──────────────────────────────────────────────────────────
$total_qty   = 0;
$total_cost  = 0.00;
$total_items = count($items);

foreach ($items as $item) {
    $qty   = (int)   ($item['quantity'] ?? 0);
    $cost  = (float) ($item['cost']     ?? 0);
    $total_qty  += $qty;
    $total_cost += $qty * $cost;
}

// ── Payment due date handling ─────────────────────────────────────────────────
$due_date_sql = (!empty($payment_due_date)) ? "'" . $conn->real_escape_string($payment_due_date) . "'" : 'NULL';

// ── Insert PO header ──────────────────────────────────────────────────────────
$sc  = $conn->real_escape_string($supplier_company);
$sn  = $conn->real_escape_string($supplier_name);
$cn  = $conn->real_escape_string($contact_number);
$adr = $conn->real_escape_string($address);
$trm = $conn->real_escape_string($terms);
$rmk = $conn->real_escape_string($remarks);

$sql = "INSERT INTO purchase_orders 
            (po_number, supplier_company, supplier_name, contact_number, address, terms, payment_due_date, remarks, po_date, total_items, total_qty, total_cost, status)
        VALUES 
            ('{$po_number}', '{$sc}', '{$sn}', '{$cn}', '{$adr}', '{$trm}', {$due_date_sql}, '{$rmk}', '{$po_date}', {$total_items}, {$total_qty}, {$total_cost}, 'Pending')";

if (!$conn->query($sql)) {
    throw new Exception('Failed to save PO: ' . $conn->error);
}

$po_id = $conn->insert_id;

// ── Insert PO items ───────────────────────────────────────────────────────────
$item_no = 1;
foreach ($items as $item) {
    $model        = $conn->real_escape_string(trim($item['item_model']        ?? ''));
    $description  = $conn->real_escape_string(trim($item['item_description']  ?? ''));
    $serial_number = $conn->real_escape_string(trim($item['serial_number']    ?? ''));
    $qty          = (int)   ($item['quantity'] ?? 0);
    $cost         = (float) ($item['cost']     ?? 0);
    $line_total   = $qty * $cost;

    if (!$conn->query("INSERT INTO purchase_order_items 
                      (po_id, po_number, item_no, item_model, item_description, serial_number, quantity, cost, total)
                  VALUES 
                      ({$po_id}, '{$po_number}', {$item_no}, '{$model}', '{$description}', '{$serial_number}', {$qty}, {$cost}, {$line_total})")) {
        throw new Exception("Failed to insert item {$item_no}: " . $conn->error);
    }
    $item_no++;
}

echo json_encode(['success' => true, 'po_number' => $po_number, 'redirect' => 'purchaseorder.php']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
