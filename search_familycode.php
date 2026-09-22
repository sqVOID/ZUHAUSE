<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$brands = isset($_GET['brands']) ? $_GET['brands'] : ''; // Can be comma-separated list

if (empty($term)) {
    echo json_encode(['status' => 'error', 'message' => 'No search term provided', 'data' => []]);
    exit;
}

// Search for family codes in the family_codes table
$term_escaped = $conn->real_escape_string($term);

// Base query - check if family_codes table has brand_id column
$check_column = $conn->query("SHOW COLUMNS FROM family_codes LIKE 'brand_id'");

if ($check_column && $check_column->num_rows > 0 && !empty($brands)) {
    // family_codes table has brand_id column - use it for filtering
    $brands_array = explode(',', $brands);
    $brands_escaped = array_map(function($brand) use ($conn) {
        return "'" . $conn->real_escape_string(trim($brand)) . "'";
    }, $brands_array);
    $brands_list = implode(',', $brands_escaped);
    
    // Join with brands table to filter by brand name
    $sql = "SELECT DISTINCT fc.family_code
            FROM family_codes fc
            INNER JOIN brands b ON fc.brand_id = b.id
            WHERE fc.family_code LIKE '%{$term_escaped}%'
                AND fc.status = 'Active'
                AND b.brand_name IN ({$brands_list})
                AND b.status = 'Active'
            ORDER BY fc.family_code
            LIMIT 50";
} else {
    // Either no brand_id column or no brand filter - search all active family codes
    $sql = "SELECT DISTINCT family_code
            FROM family_codes
            WHERE family_code LIKE '%{$term_escaped}%'
                AND status = 'Active'
            ORDER BY family_code
            LIMIT 50";
}

$result = $conn->query($sql);

$data = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'family_code' => $row['family_code']
        ];
    }
}

if (count($data) > 0) {
    echo json_encode(['status' => 'success', 'data' => $data]);
} else {
    // Log for debugging
    error_log("No family codes found for the selected brand(s). Query: " . $sql);
    error_log("Brands parameter: " . $brands);
    
    echo json_encode(['status' => 'error', 'message' => 'No family codes found for the selected brand(s)', 'data' => []]);
}

$conn->close();
?>
