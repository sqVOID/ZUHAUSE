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
    // Get the PO details
    $po_query = "SELECT status, created_at FROM purchase_orders WHERE id = {$po_id} LIMIT 1";
    $po_result = $conn->query($po_query);
    
    if (!$po_result || $po_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Purchase Order not found.']);
        exit;
    }
    
    $po = $po_result->fetch_assoc();
    
    // Check if there are any edit history records for this PO
    // We consider the PO as "edited" if there's any edit history record
    $edit_check_query = "SELECT COUNT(*) as edit_count 
                        FROM purchase_order_edit_history 
                        WHERE po_id = {$po_id}";
    
    $edit_result = $conn->query($edit_check_query);
    
    if (!$edit_result) {
        echo json_encode(['success' => false, 'message' => 'Error checking edit history.']);
        exit;
    }
    
    $edit_data = $edit_result->fetch_assoc();
    $has_been_edited = $edit_data['edit_count'] > 0;
    
    echo json_encode([
        'success' => true,
        'has_been_edited' => $has_been_edited,
        'edit_count' => $edit_data['edit_count']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>