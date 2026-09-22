<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['term']) || empty(trim($_GET['term']))) {
    echo json_encode(['status' => 'error', 'message' => 'Search term is required']);
    exit;
}

$searchTerm = trim($_GET['term']);
$searchTerm = $conn->real_escape_string($searchTerm);

// Search for items by item code or description
$sql = "SELECT DISTINCT item_code, description 
        FROM items 
        WHERE (item_code LIKE '%$searchTerm%' OR description LIKE '%$searchTerm%')
        AND status = 'Active'
        ORDER BY item_code ASC 
        LIMIT 50";

$result = $conn->query($sql);

if ($result === false) {
    echo json_encode(['status' => 'error', 'message' => 'Database query failed: ' . $conn->error]);
    exit;
}

$items = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'item_code' => $row['item_code'],
            'description' => $row['description']
        ];
    }
    echo json_encode(['status' => 'success', 'data' => $items]);
} else {
    echo json_encode(['status' => 'not_found', 'message' => 'No items found', 'data' => []]);
}

$conn->close();
?>
