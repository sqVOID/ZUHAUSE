<?php
require_once 'session_check.php';
require_once 'config.php';

echo "<h2>Fixing item_description column to allow NULL...</h2>";

$sql = "ALTER TABLE preorder_items MODIFY COLUMN item_description VARCHAR(255) NULL";

try {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✓ Successfully modified item_description to allow NULL values</p>";
        
        // Show updated column definition
        $result = $conn->query("SHOW COLUMNS FROM preorder_items LIKE 'item_description'");
        if ($result && $result->num_rows > 0) {
            $column = $result->fetch_assoc();
            echo "<h3>Updated Column Definition:</h3>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($column['Field']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
            echo "</tr>";
            echo "</table>";
        }
        
        echo "<hr>";
        echo "<p style='color: green; font-size: 16px;'><strong>✓ Fix completed successfully!</strong></p>";
        echo "<p>The item_description column now allows NULL values, just like item_code.</p>";
        echo "<p><a href='preorder.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Go to Pre-order</a></p>";
        
    } else {
        echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($conn->error) . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
}

$conn->close();
?>
