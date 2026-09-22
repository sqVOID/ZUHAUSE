<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

$brand = isset($_GET['brand']) ? $_GET['brand'] : '';

if (empty($brand)) {
    // If no brand is selected, return all active family codes
    $query = "SELECT DISTINCT fc.family_code 
              FROM family_codes fc 
              WHERE fc.status = 'Active' 
              ORDER BY fc.family_code ASC";
} else {
    // Get family codes for the selected brand
    $brand_safe = $conn->real_escape_string($brand);
    
    // First, get the brand_id from the brand name
    $brand_query = "SELECT id FROM brands WHERE brand_name = '$brand_safe' AND status = 'Active'";
    $brand_result = $conn->query($brand_query);
    
    if ($brand_result && $brand_result->num_rows > 0) {
        $brand_row = $brand_result->fetch_assoc();
        $brand_id = $brand_row['id'];
        
        // Get family codes associated with this brand
        $query = "SELECT DISTINCT fc.family_code 
                  FROM family_codes fc 
                  WHERE fc.brand_id = $brand_id 
                  AND fc.status = 'Active' 
                  ORDER BY fc.family_code ASC";
    } else {
        // Brand not found, return empty array
        echo json_encode(['success' => true, 'family_codes' => []]);
        exit();
    }
}

$result = $conn->query($query);
$family_codes = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $family_codes[] = $row['family_code'];
    }
}

echo json_encode(['success' => true, 'family_codes' => $family_codes]);
?>
