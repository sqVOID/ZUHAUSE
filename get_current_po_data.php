<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;

if ($po_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
    exit;
}

try {
    // Branch allocations store serial numbers after receiving
    $allocation_serials_by_family = [];
    $allocations_result = $conn->query("
        SELECT family_code, serial_number
        FROM purchase_order_allocations
        WHERE po_id = {$po_id}
    ");
    if ($allocations_result) {
        while ($alloc = $allocations_result->fetch_assoc()) {
            $family_code = $alloc['family_code'] ?? '';
            if ($family_code === '' || empty($alloc['serial_number'])) {
                continue;
            }
            if (!isset($allocation_serials_by_family[$family_code])) {
                $allocation_serials_by_family[$family_code] = [];
            }
            $serials = trim($alloc['serial_number']);
            if (strpos($serials, "\n") !== false) {
                $serial_array = explode("\n", $serials);
            } else {
                $serial_array = explode(",", $serials);
            }
            $serial_array = array_filter(array_map('trim', $serial_array));
            $allocation_serials_by_family[$family_code] = array_merge(
                $allocation_serials_by_family[$family_code],
                $serial_array
            );
        }
    }

    // Get current PO items with their latest quantities and serial numbers
    $items_query = "SELECT poi.*, i.has_serial 
                   FROM purchase_order_items poi 
                   LEFT JOIN items i ON poi.family_code = i.family_code 
                   WHERE poi.po_id = {$po_id} 
                   ORDER BY poi.item_no ASC";
    
    $items_result = $conn->query($items_query);
    
    if (!$items_result) {
        echo json_encode(['success' => false, 'message' => 'Error fetching PO items.']);
        exit;
    }
    
    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $family_code = $item['family_code'] ?? '';
        $serial_string = $item['serial_number'] ?? '';

        if (empty($serial_string) && !empty($allocation_serials_by_family[$family_code])) {
            $serial_string = implode("\n", array_values(array_unique($allocation_serials_by_family[$family_code])));
        }

        $existing_serials = [];
        if (!empty($serial_string)) {
            if (strpos($serial_string, "\n") !== false) {
                $existing_serials = array_filter(array_map('trim', explode("\n", $serial_string)));
            } else {
                $existing_serials = array_filter(array_map('trim', explode(",", $serial_string)));
            }
        }
        
        $items[] = [
            'family_code' => $family_code,
            'item_model' => $item['item_model'],
            'item_description' => $item['item_description'],
            'quantity' => (int)$item['quantity'],
            'has_serial' => $item['has_serial'] == 1,
            'serial_number' => $serial_string,
            'existing_serials' => array_values($existing_serials)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>