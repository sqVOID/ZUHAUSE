<?php
/**
 * Migration: Add late_created_at column to sales_entry table
 * 
 * Purpose: Track the actual timestamp when a late entry was created by the user,
 * separate from the created_at which stores the sale date for late entries.
 * 
 * Usage: Run this file once to add the column to the database.
 */

require_once 'config.php';

echo "<h2>Adding late_created_at Column to sales_entry Table</h2>";

try {
    // Check if column already exists
    $check_query = "SHOW COLUMNS FROM sales_entry LIKE 'late_created_at'";
    $result = $conn->query($check_query);
    
    if ($result->num_rows > 0) {
        echo "<p style='color: orange;'>Column 'late_created_at' already exists in sales_entry table. No action needed.</p>";
    } else {
        // Add the late_created_at column after created_at
        $alter_query = "
            ALTER TABLE sales_entry 
            ADD COLUMN late_created_at DATETIME NULL DEFAULT NULL 
            AFTER created_at
        ";
        
        if ($conn->query($alter_query) === TRUE) {
            echo "<p style='color: green;'>✓ Successfully added 'late_created_at' column to sales_entry table.</p>";
            echo "<p><strong>Column Details:</strong></p>";
            echo "<ul>";
            echo "<li>Type: DATETIME</li>";
            echo "<li>Nullable: YES (NULL for regular sales entries)</li>";
            echo "<li>Purpose: Stores the actual timestamp when a late entry was created</li>";
            echo "<li>Usage: Only populated for entries from salesentrylate.php</li>";
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>✗ Error adding column: " . $conn->error . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception occurred: " . $e->getMessage() . "</p>";
}

$conn->close();

echo "<hr>";
echo "<p><a href='salesentrylate.php'>Go to Late Entry Page</a> | <a href='salesentry.php'>Go to Sales Entry Page</a></p>";
?>
