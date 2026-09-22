<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['st_number'])) {
    echo json_encode(['error' => 'ST number is required']);
    exit;
}

$st_number = $_GET['st_number'];

try {
    // Get transfer header
    $stmt = $conn->prepare("SELECT * FROM stock_transfers WHERE st_number = ?");
    $stmt->bind_param("s", $st_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Transfer not found']);
        exit;
    }
    
    $transfer = $result->fetch_assoc();
    $stmt->close();
    
    // Get transfer items with prices
    $stmt = $conn->prepare("
        SELECT 
            sti.*,
            i.id as item_id,
            COALESCE(
                (SELECT price FROM item_prices 
                 WHERE item_id = i.id 
                 AND branch = ? 
                 AND price_type = 'SRP' 
                 LIMIT 1),
                i.srp,
                0
            ) as amount
        FROM stock_transfer_items sti
        LEFT JOIN items i ON sti.item_code = i.item_code
        WHERE sti.st_number = ?
        ORDER BY sti.id
    ");
    $branch_to = $transfer['branch_to'];
    $stmt->bind_param("ss", $branch_to, $st_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'transfer' => $transfer,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
