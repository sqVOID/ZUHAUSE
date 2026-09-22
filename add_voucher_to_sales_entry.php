<?php
/**
 * Migration: Add voucher column to sales_entry table
 * Run this file once to add voucher_amount column to sales_entry
 */

require_once 'config.php';

echo "<h2>Adding Voucher Column to sales_entry Table</h2>";

// Add voucher_amount column (DECIMAL to store voucher monetary value)
$sql = "ALTER TABLE sales_entry ADD COLUMN IF NOT EXISTS voucher_amount DECIMAL(10,2) DEFAULT 0.00 AFTER card_bank_type";
if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green;'>✓ Column 'voucher_amount' added successfully to sales_entry table</p>";
} else {
    echo "<p style='color: red;'>✗ Error adding 'voucher_amount': " . $conn->error . "</p>";
}

echo "<h3>Migration Complete!</h3>";
echo "<p>Voucher column has been added to the sales_entry table.</p>";
echo "<p><a href='salesentry.php'>Go to Sales Entry</a> | <a href='salesentrylate.php'>Go to Late Sales Entry</a></p>";

$conn->close();
?>
