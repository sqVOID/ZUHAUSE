<?php
require_once 'session_check.php';
/**
 * Add received_by and received_date columns to stock_transfers table
 * Run this script once to add the new columns
 */

require_once 'config.php';

try {
    // Add received_by column
    $sql1 = "ALTER TABLE stock_transfers 
             ADD COLUMN IF NOT EXISTS received_by VARCHAR(150) DEFAULT NULL AFTER approver";
    
    if ($conn->query($sql1)) {
        echo "✅ Added received_by column successfully<br>";
    } else {
        echo "⚠️ received_by column: " . $conn->error . "<br>";
    }

    // Add received_date column
    $sql2 = "ALTER TABLE stock_transfers 
             ADD COLUMN IF NOT EXISTS received_date DATETIME DEFAULT NULL AFTER received_by";
    
    if ($conn->query($sql2)) {
        echo "✅ Added received_date column successfully<br>";
    } else {
        echo "⚠️ received_date column: " . $conn->error . "<br>";
    }

    echo "<br>✅ Migration completed!";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

$conn->close();
?>
