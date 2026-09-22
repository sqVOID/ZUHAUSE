<?php
/**
 * Migration: Add voucher columns to items table
 * Run this file once to add has_voucher and voucher_amount columns
 */

require_once 'config.php';

echo "<h2>Adding Voucher Columns to Items Table</h2>";

// Add has_voucher column (TINYINT to store checkbox state)
$sql1 = "ALTER TABLE items ADD COLUMN IF NOT EXISTS has_voucher TINYINT(1) DEFAULT 0 AFTER has_serial";
if ($conn->query($sql1) === TRUE) {
    echo "<p style='color: green;'>✓ Column 'has_voucher' added successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error adding 'has_voucher': " . $conn->error . "</p>";
}

// Add voucher_amount column (DECIMAL to store monetary value)
$sql2 = "ALTER TABLE items ADD COLUMN IF NOT EXISTS voucher_amount DECIMAL(10,2) DEFAULT 0.00 AFTER has_voucher";
if ($conn->query($sql2) === TRUE) {
    echo "<p style='color: green;'>✓ Column 'voucher_amount' added successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error adding 'voucher_amount': " . $conn->error . "</p>";
}

echo "<h3>Migration Complete!</h3>";
echo "<p><a href='itemreg.php'>Go to Item Registration</a></p>";

$conn->close();
?>
