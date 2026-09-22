<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception("Invalid JSON data received.");
    }

    $po_number = $conn->real_escape_string($data['po_number']);
    $date = $conn->real_escape_string($data['date']);
    $supplier = $conn->real_escape_string($data['supplier']);
    $invoice_number = $conn->real_escape_string($data['invoice_number']);
    $invoice_date = $conn->real_escape_string($data['invoice_date']);
    $inventory_sites = $conn->real_escape_string($data['inventory_sites']);
    $items = isset($data['items']) ? $data['items'] : [];

    // branch from session
    $branch_code = '000';
    if (isset($_SESSION['user_branch'])) {
        $user_branch = $conn->real_escape_string($_SESSION['user_branch']);
        $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch'");
        if ($branch_query && $branch_query->num_rows > 0) {
            $branch_data = $branch_query->fetch_assoc();
            $branch_code = $branch_data['branch_code'];
        }
    }

    $received_by = '';
    if (isset($_SESSION['user_name'])) {
        $received_by = $conn->real_escape_string($_SESSION['user_name']);
    } else if (isset($_SESSION['fullname'])) {
        $received_by = $conn->real_escape_string($_SESSION['fullname']);
    } else if (isset($_SESSION['username'])) {
        $received_by = $conn->real_escape_string($_SESSION['username']);
    }

    $notes = isset($data['notes']) ? $conn->real_escape_string($data['notes']) : '';

    $conn->begin_transaction();

    // insert header
    $stmt = $conn->prepare("INSERT INTO rddeliveries (po_number, date, supplier, invoice_number, invoice_date, inventory_sites, branch_code, received_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // date might be "MM/DD/YYYY" format, default strtotime parses slashes as US date (M/D/Y).
    $db_date = date('Y-m-d', strtotime($date));
    $db_inv_date = date('Y-m-d', strtotime($invoice_date));

    $stmt->bind_param("sssssssss", $po_number, $db_date, $supplier, $invoice_number, $db_inv_date, $inventory_sites, $branch_code, $received_by, $notes);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $rdd_id = $stmt->insert_id;
    $stmt->close();

    // insert items
    $stmt_items = $conn->prepare("INSERT INTO rddelivery_items (rddelivery_id, item_code, item_description, quantity, received_quantity, serials) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt_items) {
        throw new Exception("Prepare items failed: " . $conn->error);
    }

    foreach ($items as $item) {
        $icode = $item['item_code'];
        $idesc = $item['item_description'];
        $iqty = $item['quantity'];
        $irecv = $item['received_quantity'];
        $iserials = isset($item['serials']) ? $item['serials'] : '';
        
        $stmt_items->bind_param("issiis", $rdd_id, $icode, $idesc, $iqty, $irecv, $iserials);
        if (!$stmt_items->execute()) {
            throw new Exception("Execute items failed: " . $stmt_items->error);
        }
    }
    $stmt_items->close();

    // Removed UPDATE query as user specifically wants purchase order status to remain Received

    $conn->commit();
    echo json_encode(["success" => true, "message" => "Saved successfully!"]);
} catch (Exception $e) {
    if (isset($conn) && $conn->connect_errno == 0) {
        $conn->rollback();
    }
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
