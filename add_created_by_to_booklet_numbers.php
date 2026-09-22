<?php
require_once 'session_check.php';
/**
 * Migration script to add created_by column to booklet_numbers table
 * Run this once to add the column
 */

require_once 'config.php';

echo "Adding created_by column to booklet_numbers table...<br>";

// Check if column already exists
$check_sql = "SHOW COLUMNS FROM booklet_numbers LIKE 'created_by'";
$result = $conn->query($check_sql);

if ($result && $result->num_rows > 0) {
    echo "✓ Column 'created_by' already exists!<br>";
} else {
    // Add the column
    $sql = "ALTER TABLE booklet_numbers 
            ADD COLUMN created_by VARCHAR(100) NULL AFTER status";
    
    if ($conn->query($sql) === TRUE) {
        echo "✓ Column 'created_by' added successfully!<br>";
    } else {
        echo "✗ Error adding column: " . $conn->error . "<br>";
    }
}

echo "<br><strong>Migration complete!</strong><br>";
echo "<a href='bookletnoreg.php'>Go to Booklet Registration</a>";

$conn->close();
?>
