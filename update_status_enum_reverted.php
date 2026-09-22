<?php
require_once 'session_check.php';
include 'config.php';

// Update status ENUM to include 'Reverted'
$sql = "ALTER TABLE skip_receipt_requests 
        MODIFY COLUMN status ENUM('Pending', 'Approved', 'Rejected', 'Reverted') DEFAULT 'Pending'";

if ($conn->query($sql) === TRUE) {
    echo "✓ Status ENUM updated successfully to include 'Reverted'.<br>";
} else {
    echo "✗ Error updating status ENUM: " . $conn->error . "<br>";
}

$conn->close();
echo "<br>Migration completed!";
?>
