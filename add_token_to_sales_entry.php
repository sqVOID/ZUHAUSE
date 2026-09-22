<?php
// add_token_to_sales_entry.php - Add token column to sales_entry table

include 'config.php';

echo "<h2>Adding Token Column to Sales_Entry Table</h2>";

// Check if column already exists
$check_sql = "SHOW COLUMNS FROM sales_entry LIKE 'token'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>Column already exists. No changes made.</p>";
} else {
    // Add token column after voucher_amount
    $sql = "ALTER TABLE sales_entry ADD COLUMN token DECIMAL(10,2) DEFAULT 0.00 AFTER voucher_amount";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✓ Column 'token' added successfully to sales_entry table.</p>";
        echo "<p style='color: blue;'><strong>Migration completed!</strong></p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding 'token': " . $conn->error . "</p>";
    }
}

$conn->close();
?>
