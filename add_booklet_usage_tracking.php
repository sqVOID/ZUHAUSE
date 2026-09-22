<?php
require_once 'session_check.php';
// Add usage tracking columns to booklet_numbers table
require_once 'config.php';

// Check if columns already exist
$check_columns = $conn->query("SHOW COLUMNS FROM booklet_numbers LIKE 'last_used_date'");
if ($check_columns->num_rows > 0) {
    echo "Columns already exist!";
    exit;
}

// Add the new columns
$sql = "ALTER TABLE booklet_numbers 
        ADD COLUMN last_used_date DATETIME NULL AFTER status,
        ADD COLUMN last_used_by VARCHAR(100) NULL AFTER last_used_date";

if ($conn->query($sql) === TRUE) {
    echo "Successfully added last_used_date and last_used_by columns to booklet_numbers table.";
} else {
    echo "Error adding columns: " . $conn->error;
}

$conn->close();
?>
