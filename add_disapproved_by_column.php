<?php
// Script to add disapproved_by column to stock_transfers table
require_once 'session_check.php';
include 'config.php';

// Add disapproved_by column if it doesn't exist
$check_column = "SHOW COLUMNS FROM stock_transfers LIKE 'disapproved_by'";
$result = $conn->query($check_column);

if ($result->num_rows == 0) {
    // Column doesn't exist, add it
    $sql = "ALTER TABLE stock_transfers ADD COLUMN disapproved_by VARCHAR(100) DEFAULT NULL AFTER received_by";
    
    if ($conn->query($sql) === TRUE) {
        echo "Column 'disapproved_by' added successfully to stock_transfers table.<br>";
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'disapproved_by' already exists in stock_transfers table.<br>";
}

$conn->close();
?>
