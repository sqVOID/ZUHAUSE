<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

$family_code = isset($_GET['family_code']) ? trim($_GET['family_code']) : '';

if (empty($family_code)) {
    echo json_encode(['status' => 'error', 'message' => 'Family code is required', 'exists' => false]);
    exit;
}

// Check if family code exists in family_codes table
$family_code_escaped = $conn->real_escape_string($family_code);

$sql = "SELECT COUNT(*) as count 
        FROM family_codes 
        WHERE family_code = '{$family_code_escaped}'
            AND status = 'Active'
        LIMIT 1";

$result = $conn->query($sql);

if ($result) {
    $row = $result->fetch_assoc();
    $exists = $row['count'] > 0;
    
    if ($exists) {
        echo json_encode(['status' => 'success', 'exists' => true, 'message' => 'Family code is valid']);
    } else {
        echo json_encode(['status' => 'error', 'exists' => false, 'message' => 'Family code "' . htmlspecialchars($family_code) . '" does not exist in the system. Please select a valid family code from the search.']);
    }
} else {
    echo json_encode(['status' => 'error', 'exists' => false, 'message' => 'Database error']);
}

$conn->close();
?>
