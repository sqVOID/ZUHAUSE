<?php
require_once 'session_check.php';
// Debug script to capture POST data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $log = "Timestamp: " . date("Y-m-d H:i:s") . "\n";
    $log .= "POST Data:\n" . print_r($_POST, true) . "\n";
    file_put_contents('post_debug.txt', $log, FILE_APPEND);
}
?>

