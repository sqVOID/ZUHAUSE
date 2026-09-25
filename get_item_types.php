<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

if (!isset($input['po_id']) || !isset($input['family_code']) || !isset($input['item_no'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$po_id = (int)$input['po_id'];
$family_code = trim($input['family_code']);
$item_no = (int)$input['item_no'];
$family_code_esc = $conn->real_escape_string($family_code);

try {
    $types_query = $conn->query("SELECT serial_number, item_type FROM purchase_order_serial_types 
                                 WHERE po_id = {$po_id} 
                                 AND BINARY family_code = BINARY '{$family_code_esc}' 
                                 AND item_no = {$item_no}");

    $types = [];
    if ($types_query && $types_query->num_rows > 0) {
        while ($type_row = $types_query->fetch_assoc()) {
            $sn = trim($type_row['serial_number'] ?? '');
            if ($sn !== '') {
                $types[$sn] = $type_row['item_type'];
            }
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
