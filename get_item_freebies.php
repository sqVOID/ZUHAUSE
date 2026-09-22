<?php
require_once 'session_check.php';
// Suppress all errors to prevent breaking JSON response
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['item_code'])) {
    echo json_encode(['status' => 'error', 'message' => 'Item code not provided']);
    exit;
}

$item_code = $conn->real_escape_string($_GET['item_code']);

// Check if items table exists
$table_check = @$conn->query("SHOW TABLES LIKE 'items'");
if (!$table_check || $table_check->num_rows == 0) {
    echo json_encode(['status' => 'success', 'freebies' => []]);
    exit;
}

// Query to get freebies for the item
$query = "SELECT freebies, has_freebies FROM items WHERE item_code = '$item_code'";
$result = @$conn->query($query);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();

    $has_freebies = isset($row['has_freebies']) ? $row['has_freebies'] : 0;
    $freebies = isset($row['freebies']) ? $row['freebies'] : '';

    if ($has_freebies && !empty($freebies)) {
        // Split freebies by comma and trim whitespace
        $freebies_array = array_map('trim', explode(',', $freebies));

        echo json_encode([
            'status' => 'success',
            'freebies' => $freebies_array
        ]);
    }
    else {
        echo json_encode([
            'status' => 'success',
            'freebies' => []
        ]);
    }
}
else {
    echo json_encode([
        'status' => 'success',
        'freebies' => []
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>

