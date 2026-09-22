<?php
/**
 * Get Total Cost Received
 * Calculates the total cost of all received items for a PO
 */

require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    // Get parameter
    $po_id = isset($_GET['po_id']) ? (int)$_GET['po_id'] : 0;
    
    // Validate input
    if ($po_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
        exit;
    }
    
    // Calculate total cost received (sum of received_qty * cost for all allocations)
    $cost_query = $conn->query("
        SELECT SUM(poa.received_qty * poa.cost) as total_cost_received
        FROM purchase_order_allocations poa
        WHERE poa.po_id = {$po_id}
    ");
    
    $total_cost_received = 0;
    if ($cost_query && $cost_query->num_rows > 0) {
        $result = $cost_query->fetch_assoc();
        $total_cost_received = (float)($result['total_cost_received'] ?? 0);
    }
    
    echo json_encode([
        'success' => true,
        'total_cost_received' => $total_cost_received
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
