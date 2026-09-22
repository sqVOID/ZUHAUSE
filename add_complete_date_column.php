<?php
require_once 'session_check.php';
/**
 * Migration script to add complete_date column to booklet_numbers table
 * This column tracks when a booklet reaches or passes its ending number
 */

include 'config.php';

echo "<h2>Adding complete_date column to booklet_numbers table...</h2>";

// Check if column already exists
$check_sql = "SHOW COLUMNS FROM booklet_numbers LIKE 'complete_date'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>Column 'complete_date' already exists. No changes needed.</p>";
} else {
    // Add complete_date column
    $alter_sql = "ALTER TABLE booklet_numbers 
                  ADD COLUMN complete_date DATETIME NULL DEFAULT NULL 
                  AFTER last_used_by";
    
    if ($conn->query($alter_sql) === TRUE) {
        echo "<p style='color: green;'>✓ Successfully added 'complete_date' column to booklet_numbers table</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding complete_date column: " . $conn->error . "</p>";
    }
}

echo "<h3>Column Structure:</h3>";
echo "<ul>";
echo "<li><strong>complete_date</strong>: DATETIME NULL - Records when the booklet reaches or passes its ending number</li>";
echo "</ul>";

echo "<p><a href='bookletnoreg.php'>Go to Booklet Number Registration</a></p>";

$conn->close();
?>
