<?php
/**
 * Close Purchase Order
 * Marks a purchase order as closed when all items are fully received
 */

require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    // Get parameters
    $po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
    $closed_remarks = ''; // No remarks for closing
    
    // Validate input
    if ($po_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
        exit;
    }
    
    // Get user info from session
    $closed_by = $_SESSION['username'] ?? 'Unknown';
    $closed_by_fullname = ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '');
    $closed_by_fullname = trim($closed_by_fullname) ?: $closed_by;
    $closed_by_branch = $_SESSION['branch_code'] ?? '000';
    
    // Check if columns exist, create if not
    $check_columns = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'closed_by'");
    if ($check_columns->num_rows === 0) {
        $conn->query("ALTER TABLE purchase_orders 
            ADD COLUMN closed_by VARCHAR(100),
            ADD COLUMN closed_at DATETIME,
            ADD COLUMN closed_by_branch VARCHAR(10),
            ADD COLUMN closed_remarks TEXT
        ");
    }
    
    // Verify the PO exists and can be closed
    $check_sql = "SELECT status FROM purchase_orders WHERE id = {$po_id}";
    $check_result = $conn->query($check_sql);
    
    if (!$check_result || $check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Purchase Order not found.']);
        exit;
    }
    
    $po_data = $check_result->fetch_assoc();
    
    // Check if PO is already canceled or closed
    if (strtolower($po_data['status']) === 'canceled' || strtolower($po_data['status']) === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'Cannot close a canceled purchase order.']);
        exit;
    }
    
    if (strtolower($po_data['status']) === 'closed') {
        echo json_encode(['success' => false, 'message' => 'Purchase Order is already closed.']);
        exit;
    }
    
    // Verify all items are fully received
    $verify_sql = "
        SELECT 
            SUM(poi.quantity) as total_qty,
            SUM(COALESCE(poa.received_qty, 0)) as total_received
        FROM purchase_order_items poi
        LEFT JOIN purchase_order_allocations poa 
            ON poi.po_id = poa.po_id 
            AND poi.family_code COLLATE utf8mb4_general_ci = poa.family_code COLLATE utf8mb4_general_ci
        WHERE poi.po_id = {$po_id}
    ";
    $verify_result = $conn->query($verify_sql);
    $verify_data = $verify_result->fetch_assoc();
    
    if ($verify_data['total_qty'] != $verify_data['total_received']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot close purchase order. Not all items have been fully received.'
        ]);
        exit;
    }
    
    // Prepare SQL update
    $closed_remarks_escaped = $conn->real_escape_string($closed_remarks);
    $closed_by_escaped = $conn->real_escape_string($closed_by_fullname);
    $closed_by_branch_escaped = $conn->real_escape_string($closed_by_branch);
    
    // Update purchase order status to Closed
    $update_sql = "
        UPDATE purchase_orders 
        SET 
            status = 'Closed',
            closed_by = '{$closed_by_escaped}',
            closed_at = NOW(),
            closed_by_branch = '{$closed_by_branch_escaped}',
            closed_remarks = '{$closed_remarks_escaped}'
        WHERE id = {$po_id}
    ";
    
    if ($conn->query($update_sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Purchase Order closed successfully.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $conn->error
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
