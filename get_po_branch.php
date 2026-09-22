<?php
/**
 * Get PO Branch Information
 * Fetches the branch name for a purchase order
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
    
    // Fetch branch information for the PO
    $branch_query = $conn->query("
        SELECT b.branch_name 
        FROM purchase_orders po
        LEFT JOIN branches b ON po.created_by_branch = b.branch_code
        WHERE po.id = {$po_id} 
        LIMIT 1
    ");
    
    if (!$branch_query || $branch_query->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Branch not found.']);
        exit;
    }
    
    $branch = $branch_query->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'branch_name' => $branch['branch_name'] ?: 'N/A'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
