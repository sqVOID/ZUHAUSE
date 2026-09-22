<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if (isset($_GET['term'])) {
    $term = $conn->real_escape_string($_GET['term']);

    // Build WHERE clause for purchase order item search
    $whereClause = "(i.description LIKE '%$term%' OR i.item_code LIKE '%$term%' OR i.family_code LIKE '%$term%')";

    // Check if item_model column exists, if not use item_code
    $sql = "SELECT i.id, i.item_code, i.family_code, i.description, i.item_code as item_model, i.srp, i.branch, i.has_serial 
            FROM items i 
            WHERE $whereClause 
            AND i.status = 'Active' 
            LIMIT 20";

    $result = $conn->query($sql);

    $items_data = [];
    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $items_data[] = [
                    'item_code' => $row['item_code'],
                    'family_code' => $row['family_code'],
                    'item_model' => $row['item_model'],
                    'description' => $row['description'],
                    'price' => $row['srp'] ?? 0,
                    'has_serial' => $row['has_serial'] ?? 0
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $items_data]);
        }
        else {
            echo json_encode(['status' => 'success', 'data' => []]);
        }
    }
    else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}
else {
    echo json_encode(['status' => 'error', 'message' => 'No search term provided']);
}
?>
