<?php
require_once 'session_check.php';
/**
 * Migration Script: Add reason_to_modify column to sales_entry table
 * Run this script once to add the new column
 */

include 'config.php';

// Check if column already exists
$check_query = "SHOW COLUMNS FROM sales_entry LIKE 'reason_to_modify'";
$result = $conn->query($check_query);

if ($result->num_rows == 0) {
    // Column doesn't exist, add it
    $alter_query = "ALTER TABLE sales_entry ADD COLUMN reason_to_modify TEXT NULL AFTER remarks";
    
    if ($conn->query($alter_query)) {
        echo "SUCCESS: Column 'reason_to_modify' has been added to the sales_entry table.\n";
    } else {
        echo "ERROR: Failed to add column. " . $conn->error . "\n";
    }
} else {
    echo "INFO: Column 'reason_to_modify' already exists in the sales_entry table.\n";
}

$conn->close();
?>
