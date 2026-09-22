<?php
require_once 'session_check.php';
// Simple test to check if the file can run at all
echo json_encode([
    'status' => 'success',
    'message' => 'Test file works'
]);
?>
