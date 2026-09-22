<?php
/**
 * Migration Script: Add family_code column to purchase_order_items table
 * 
 * This script adds the family_code column to the purchase_order_items table
 * to support the new family code-based purchase order system.
 * 
 * Run this once to update your existing database.
 */

require_once 'session_check.php';
include 'config.php';

// Check if family_code column already exists
$check_column = $conn->query("SHOW COLUMNS FROM purchase_order_items LIKE 'family_code'");

if ($check_column && $check_column->num_rows > 0) {
    echo "<h2>Migration Status</h2>";
    echo "<p style='color: orange;'>✓ The 'family_code' column already exists in the purchase_order_items table.</p>";
    echo "<p>No migration needed.</p>";
} else {
    // Add family_code column
    $add_column_sql = "ALTER TABLE purchase_order_items 
                       ADD COLUMN family_code VARCHAR(100) AFTER item_no";
    
    if ($conn->query($add_column_sql)) {
        echo "<h2>Migration Successful!</h2>";
        echo "<p style='color: green;'>✓ Successfully added 'family_code' column to purchase_order_items table.</p>";
        echo "<p>The system is now ready to use family codes in purchase orders.</p>";
    } else {
        echo "<h2>Migration Failed</h2>";
        echo "<p style='color: red;'>✗ Error adding family_code column: " . $conn->error . "</p>";
        echo "<p>Please check your database permissions and try again.</p>";
    }
}

echo "<br><a href='purchaseorder.php'>← Back to Purchase Orders</a>";

$conn->close();
?>
