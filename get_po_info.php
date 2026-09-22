<?php
/**
 * Get Purchase Order Information
 * Fetches PO header details
 */

require_once 'session_check.php';
include 'config.php';
require_once 'po_status_helpers.php';

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
    
    // Fetch PO information
    $po_query = $conn->query("
        SELECT 
            po_number,
            supplier_company,
            brand_type,
            selected_brands,
            po_date,
            terms,
            total_cost,
            status,
            remarks,
            receiving_remarks,
            canceled_by,
            canceled_at,
            canceled_by_branch,
            cancel_reason
        FROM purchase_orders 
        WHERE id = {$po_id} 
        LIMIT 1
    ");
    
    if (!$po_query || $po_query->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Purchase order not found.']);
        exit;
    }
    
    $po = $po_query->fetch_assoc();

    $totals = get_po_quantity_totals($conn, $po_id);
    
    echo json_encode([
        'success' => true,
        'po' => [
            'po_number' => $po['po_number'],
            'supplier_company' => $po['supplier_company'],
            'brand_type' => $po['brand_type'] ?? null,
            'selected_brands' => $po['selected_brands'] ?? null,
            'po_date' => $po['po_date'],
            'terms' => $po['terms'],
            'total_cost' => (float)$po['total_cost'],
            'total_qty' => $totals['total_order_qty'],
            'total_allocated' => $totals['total_allocated'],
            'total_received' => $totals['total_received'],
            'status' => $po['status'],
            'remarks' => $po['remarks'],
            'receiving_remarks' => $po['receiving_remarks'],
            'canceled_by' => $po['canceled_by'] ?? null,
            'canceled_at' => $po['canceled_at'] ?? null,
            'canceled_by_branch' => $po['canceled_by_branch'] ?? null,
            'cancel_reason' => $po['cancel_reason'] ?? null
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
