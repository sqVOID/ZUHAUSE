<?php
require_once 'session_check.php';
// Simple test file to check what's happening
header('Content-Type: application/json');

try {
    // Test 1: Can we output JSON?
    $test = ['test' => 'success', 'message' => 'Basic JSON output works'];
    echo json_encode($test);
    exit;
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
