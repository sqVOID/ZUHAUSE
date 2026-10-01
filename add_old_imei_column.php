<?php
// Script to add old_imei column to sales_entry_items table
require_once 'session_check.php';
include 'config.php';

try {
    // Check if old_imei column exists
    $check_column = $conn->query("SHOW COLUMNS FROM sales_entry_items LIKE 'old_imei'");
    
    if ($check_column->num_rows == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE sales_entry_items ADD COLUMN old_imei VARCHAR(50) NULL AFTER imei";
        
        if ($conn->query($sql)) {
            echo "✅ Successfully added old_imei column to sales_entry_items table<br>";
        } else {
            echo "❌ Error adding column: " . $conn->error . "<br>";
        }
    } else {
        echo "ℹ️ Column old_imei already exists in sales_entry_items table<br>";
    }
    
    $conn->close();
    
    echo "<br><a href='report.php'>Go to Report</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
