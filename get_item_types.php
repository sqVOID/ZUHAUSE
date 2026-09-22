<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['po_id']) || !isset($input['family_code']) || !isset($input['item_no'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$po_id = (int)$input['po_id'];
$family_code = $conn->real_escape_string($input['family_code']);
$item_no = (int)$input['item_no'];

try {
    // Fetch item types from database
    $types_query = $conn->query("SELECT serial_number, item_type FROM purchase_order_serial_types 
                                 WHERE po_id = {$po_id} 
                                 AND family_code = '{$family_code}' 
                                 AND item_no = {$item_no}");
    
    $types = [];
    if ($types_query && $types_query->num_rows > 0) {
        while ($type_row = $types_query->fetch_assoc()) {
            $types[$type_row['serial_number']] = $type_row['item_type'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'types' => $types,
        'count' => count($types)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching data: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
