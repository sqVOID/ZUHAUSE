<?php
require_once 'session_check.php';
include 'config.php';

// First, check what columns exist
$check_sql = "SHOW COLUMNS FROM skip_receipt_requests";
$result = $conn->query($check_sql);

echo "<h3>Current columns in skip_receipt_requests table:</h3>";
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . "<br>";
    }
}
echo "<br><hr><br>";

// Add revert columns to skip_receipt_requests table
$columns_to_add = [
    "ADD COLUMN reverted_by VARCHAR(255) NULL",
    "ADD COLUMN reverted_date DATETIME NULL",
    "ADD COLUMN revert_reason TEXT NULL",
    "ADD COLUMN is_reverted BOOLEAN DEFAULT FALSE"
];

foreach ($columns_to_add as $column_sql) {
    $sql = "ALTER TABLE skip_receipt_requests $column_sql";
    
    if ($conn->query($sql) === TRUE) {
        echo "✓ Successfully added: $column_sql<br>";
    } else {
        // Check if column already exists
        if (strpos($conn->error, 'Duplicate column name') !== false) {
            echo "ℹ Column already exists: $column_sql<br>";
        } else {
            echo "✗ Error adding column: " . $conn->error . "<br>";
        }
    }
}

$conn->close();
echo "<br>Migration completed!";
?>
