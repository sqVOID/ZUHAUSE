<?php
/**
 * Get All PO Allocations
 * Fetches all allocations for a PO across all branches with item details
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
    
    // Fetch all allocations with item details
    $allocations_query = $conn->query("
        SELECT 
            poa.id,
            poa.family_code,
            poa.quantity,
            poa.cost,
            COALESCE(poa.received_qty, 0) as received_qty,
            poa.invoice_number,
            poa.branch_name,
            poi.item_model as item_code,
            poi.item_description
        FROM purchase_order_allocations poa
        LEFT JOIN purchase_order_items poi 
            ON poa.po_id = poi.po_id 
            AND poa.family_code COLLATE utf8mb4_general_ci = poi.family_code COLLATE utf8mb4_general_ci
        WHERE poa.po_id = {$po_id}
        ORDER BY poa.branch_name ASC, poa.family_code ASC
    ");
    
    $allocations = [];
    if ($allocations_query && $allocations_query->num_rows > 0) {
        while ($allocation = $allocations_query->fetch_assoc()) {
            $allocations[] = [
                'id' => $allocation['id'],
                'family_code' => $allocation['family_code'],
                'item_code' => $allocation['item_code'] ?: $allocation['family_code'],
                'item_description' => $allocation['item_description'] ?: '-',
                'quantity' => (int)$allocation['quantity'],
                'received_qty' => (int)$allocation['received_qty'],
                'invoice_number' => $allocation['invoice_number'] ?: '',
                'branch_name' => $allocation['branch_name']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'allocations' => $allocations
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
