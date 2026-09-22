<?php
require_once 'session_check.php';
// Add transfer tracking columns to booklet_numbers table
require_once 'config.php';

echo "Adding transfer tracking columns to booklet_numbers table...\n";

// Add transfer_date column
$sql1 = "ALTER TABLE booklet_numbers ADD COLUMN IF NOT EXISTS transfer_date DATETIME NULL AFTER return_branch";
if ($conn->query($sql1) === TRUE) {
    echo "✓ transfer_date column added successfully\n";
} else {
    echo "Note: transfer_date column may already exist or error: " . $conn->error . "\n";
}

// Add transfer_by column
$sql2 = "ALTER TABLE booklet_numbers ADD COLUMN IF NOT EXISTS transfer_by VARCHAR(100) NULL AFTER transfer_date";
if ($conn->query($sql2) === TRUE) {
    echo "✓ transfer_by column added successfully\n";
} else {
    echo "Note: transfer_by column may already exist or error: " . $conn->error . "\n";
}

// Add transfer_from_branch column
$sql3 = "ALTER TABLE booklet_numbers ADD COLUMN IF NOT EXISTS transfer_from_branch VARCHAR(50) NULL AFTER transfer_by";
if ($conn->query($sql3) === TRUE) {
    echo "✓ transfer_from_branch column added successfully\n";
} else {
    echo "Note: transfer_from_branch column may already exist or error: " . $conn->error . "\n";
}

echo "\nMigration completed!\n";
echo "New columns added:\n";
echo "- transfer_date (DATETIME) - Records when the transfer happened\n";
echo "- transfer_by (VARCHAR) - Records who performed the transfer\n";
echo "- transfer_from_branch (VARCHAR) - Records which branch it was transferred from\n";

$conn->close();
?>
