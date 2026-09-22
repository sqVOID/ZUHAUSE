<?php
// add_token_columns.php - Add has_token and token_amount columns to items table

include 'config.php';

echo "<h2>Adding Token Columns to Items Table</h2>";

// Check if columns already exist
$check_sql = "SHOW COLUMNS FROM items LIKE 'has_token'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>Columns already exist. No changes made.</p>";
} else {
    // Add has_token column
    $sql1 = "ALTER TABLE items ADD COLUMN has_token TINYINT(1) DEFAULT 0 AFTER has_voucher";
    
    if ($conn->query($sql1) === TRUE) {
        echo "<p style='color: green;'>✓ Column 'has_token' added successfully.</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding 'has_token': " . $conn->error . "</p>";
    }
    
    // Add token_amount column
    $sql2 = "ALTER TABLE items ADD COLUMN token_amount DECIMAL(10,2) DEFAULT 0.00 AFTER has_token";
    
    if ($conn->query($sql2) === TRUE) {
        echo "<p style='color: green;'>✓ Column 'token_amount' added successfully.</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding 'token_amount': " . $conn->error . "</p>";
    }
    
    echo "<p style='color: blue;'><strong>Migration completed!</strong></p>";
}

$conn->close();
?>
