<?php
// Suppress all output before JSON response
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once 'session_check.php';
include 'config.php';

// Clear any output that might have been generated
ob_end_clean();

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Get data from JSON
$po_id = isset($input['po_id']) ? intval($input['po_id']) : 0;
$family_code = isset($input['family_code']) ? trim($input['family_code']) : '';
$item_no = isset($input['item_no']) ? intval($input['item_no']) : 0;
$item_types = isset($input['types']) ? $input['types'] : [];

// Validate required fields
if ($po_id <= 0 || empty($family_code) || $item_no <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Delete existing entries for this PO item
    $delete_sql = "DELETE FROM purchase_order_serial_types 
                   WHERE po_id = ? AND family_code = ? AND item_no = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("isi", $po_id, $family_code, $item_no);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Insert new entries
    if (!empty($item_types)) {
        $insert_sql = "INSERT INTO purchase_order_serial_types 
                       (po_id, family_code, item_no, serial_number, item_type, created_at, updated_at) 
                       VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        
        foreach ($item_types as $serial => $type) {
            $serial = trim($serial);
            $type = trim($type);
            
            if (!empty($serial) && !empty($type)) {
                $insert_stmt->bind_param("isiss", $po_id, $family_code, $item_no, $serial, $type);
                $insert_stmt->execute();
            }
        }
        
        $insert_stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Item types saved successfully']);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
