<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

$po_id = isset($_GET['po_id']) ? (int)$_GET['po_id'] : 0;

if ($po_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID']);
    exit;
}

// Fetch items with updated allocation totals
$items_query = $conn->query("
    SELECT 
        poi.family_code,
        MAX(poi.cost) as cost,
        MAX(poi.quantity) as quantity,
        COALESCE(alloc.total_allocated, 0) as allocated_quantity
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
    GROUP BY poi.po_id, poi.family_code, alloc.total_allocated
    ORDER BY MIN(poi.item_no) ASC
");

$items = [];
if ($items_query && $items_query->num_rows > 0) {
    while ($item = $items_query->fetch_assoc()) {
        $items[] = [
            'family_code' => $item['family_code'],
            'cost' => (float)$item['cost'],
            'quantity' => (int)$item['quantity'],
            'allocated_quantity' => (int)$item['allocated_quantity']
        ];
    }
}

echo json_encode([
    'success' => true,
    'items' => $items
]);
