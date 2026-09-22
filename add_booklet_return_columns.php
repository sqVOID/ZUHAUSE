<?php
require_once 'session_check.php';
require_once 'config.php';

// Add return tracking columns to booklet_numbers table
$columns_to_add = [
    "ALTER TABLE booklet_numbers ADD COLUMN return_date DATETIME NULL AFTER complete_date",
    "ALTER TABLE booklet_numbers ADD COLUMN return_by VARCHAR(100) NULL AFTER return_date",
    "ALTER TABLE booklet_numbers ADD COLUMN return_branch VARCHAR(50) NULL AFTER return_by"
];

foreach ($columns_to_add as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Column added successfully<br>";
    } else {
        // Check if column already exists
        if (strpos($conn->error, 'Duplicate column name') !== false) {
            echo "Column already exists, skipping...<br>";
        } else {
            echo "Error: " . $conn->error . "<br>";
        }
    }
}

echo "<br>Booklet return tracking columns setup complete!";
$conn->close();
?>
