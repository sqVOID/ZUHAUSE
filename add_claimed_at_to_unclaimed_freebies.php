<?php
require_once 'session_check.php';
require_once 'config.php';

echo "<h2>Adding claimed_at column to unclaimed_freebies table...</h2>";

// Add claimed_at column
$sql = "ALTER TABLE unclaimed_freebies 
        ADD COLUMN claimed_at TIMESTAMP NULL DEFAULT NULL AFTER created_at";

try {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✓ Column 'claimed_at' added successfully!</p>";
    } else {
        // Check if column already exists
        if (strpos($conn->error, 'Duplicate column name') !== false) {
            echo "<p style='color: orange;'>⚠ Column 'claimed_at' already exists.</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding column: " . $conn->error . "</p>";
        }
    }
    
    // Show current table structure
    $result = $conn->query("DESCRIBE unclaimed_freebies");
    if ($result) {
        echo "<h3>Current Table Structure:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>";
}

$conn->close();
?>
