<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'session_check.php';
include 'config.php';

ob_clean();
header('Content-Type: application/json');

$today = date('Ymd'); // e.g. 20260330
$prefix = "ST-$today-";

// Find the highest existing sequence for today
$sql = "SELECT st_number FROM stock_transfers 
        WHERE st_number LIKE '$prefix%' 
        ORDER BY st_number DESC 
        LIMIT 1";

$result = $conn->query($sql);

$next_seq = 1;
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $last = $row['st_number'];
    // Extract the sequence number after the last dash
    $parts = explode('-', $last);
    $last_seq = intval(end($parts));
    $next_seq = $last_seq + 1;
}

$st_number = $prefix . str_pad($next_seq, 3, '0', STR_PAD_LEFT);

echo json_encode(['st_number' => $st_number]);

ob_end_flush();
?>
