<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

// Check if user is authorized
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

// Get POST data
$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;

if ($item_id <= 0 || $po_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item or PO ID.']);
    exit;
}

// Verify the PO exists and user has access
$po_query = $conn->query("SELECT * FROM purchase_orders WHERE id = $po_id LIMIT 1");
if (!$po_query || $po_query->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Purchase order not found.']);
    exit;
}

$po = $po_query->fetch_assoc();

// Branch access check
if (strcasecmp($system_level, 'Super-Admin') !== 0 && strcasecmp($system_level, 'Sub-admin') !== 0) {
    $po_branch_code = $po['created_by_branch'] ?? '';
    
    $user_branch_code = '';
    if (!empty($user_branch)) {
        $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($user_branch) . "' LIMIT 1");
        if ($branch_query && $branch_query->num_rows > 0) {
            $user_branch_code = $branch_query->fetch_assoc()['branch_code'];
        }
    }
    
    if (empty($po_branch_code) || strcasecmp($po_branch_code, $user_branch_code) !== 0) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this purchase order.']);
        exit;
    }
}

// Check if PO status allows deletion (typically only Pending status should allow deletion)
$po_status = $po['status'] ?? 'Pending';
if (strcasecmp($po_status, 'Completed') === 0 || strcasecmp($po_status, 'CANCELED') === 0 || strcasecmp($po_status, 'Cancelled') === 0) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete items from a ' . $po_status . ' purchase order.']);
    exit;
}

// Verify the item belongs to this PO
$item_query = $conn->query("SELECT * FROM purchase_order_items WHERE id = $item_id AND po_id = $po_id LIMIT 1");
if (!$item_query || $item_query->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Item not found in this purchase order.']);
    exit;
}

// Delete the item
$delete_query = $conn->query("DELETE FROM purchase_order_items WHERE id = $item_id AND po_id = $po_id");

if ($delete_query) {
    // Recalculate the grand total for the PO
    $total_query = $conn->query("SELECT SUM(total) as grand_total FROM purchase_order_items WHERE po_id = $po_id");
    $new_total = 0;
    if ($total_query && $total_query->num_rows > 0) {
        $total_result = $total_query->fetch_assoc();
        $new_total = $total_result['grand_total'] ?? 0;
    }
    
    // Update the purchase order total if you have such a field (optional)
    // $conn->query("UPDATE purchase_orders SET total = $new_total WHERE id = $po_id");
    
    // Check if there are any items left
    $remaining_items = $conn->query("SELECT COUNT(*) as count FROM purchase_order_items WHERE po_id = $po_id");
    $count = 0;
    if ($remaining_items && $remaining_items->num_rows > 0) {
        $count_result = $remaining_items->fetch_assoc();
        $count = $count_result['count'];
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Item deleted successfully.',
        'remaining_items' => $count,
        'new_total' => $new_total
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete item: ' . $conn->error]);
}

$conn->close();
?>
