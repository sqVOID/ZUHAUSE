<?php
require_once 'session_check.php';
/**
 * Add received_by and received_at columns to purchase_order_allocations table
 * Run this script once to add the new columns for tracking who received items per branch
 */

require_once 'config.php';

try {
    // Add received_by column
    $sql1 = "ALTER TABLE purchase_order_allocations 
             ADD COLUMN IF NOT EXISTS received_by VARCHAR(150) DEFAULT NULL";
    
    if ($conn->query($sql1)) {
        echo "✅ Added received_by column to purchase_order_allocations successfully<br>";
    } else {
        echo "⚠️ received_by column: " . $conn->error . "<br>";
    }

    // Add received_at column
    $sql2 = "ALTER TABLE purchase_order_allocations 
             ADD COLUMN IF NOT EXISTS received_at DATETIME DEFAULT NULL";
    
    if ($conn->query($sql2)) {
        echo "✅ Added received_at column to purchase_order_allocations successfully<br>";
    } else {
        echo "⚠️ received_at column: " . $conn->error . "<br>";
    }

    echo "<br>✅ Migration completed! Now each branch allocation can track who received the items.";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

$conn->close();
?>
