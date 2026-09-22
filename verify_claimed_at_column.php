<?php
require_once 'session_check.php';
require_once 'config.php';

echo "<h2>Verifying claimed_at Column in claimed_items Table</h2>";

try {
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'claimed_items'");
    
    if ($result->num_rows === 0) {
        echo "<p style='color: orange;'>⚠️ Table 'claimed_items' does not exist yet.</p>";
        echo "<p>The table will be created automatically when you save claimed items for the first time.</p>";
    } else {
        echo "<p style='color: green;'>✓ Table 'claimed_items' exists!</p>";
        
        // Check table structure
        $result = $conn->query("DESCRIBE claimed_items");
        
        if ($result) {
            echo "<h3>Table Structure:</h3>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            
            $hasClaimedAt = false;
            
            while ($row = $result->fetch_assoc()) {
                if ($row['Field'] === 'claimed_at') {
                    $hasClaimedAt = true;
                    echo "<tr style='background-color: #c8e6c9;'>";
                } else {
                    echo "<tr>";
                }
                
                echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            if ($hasClaimedAt) {
                echo "<p style='color: green; font-weight: bold;'>✓ claimed_at column EXISTS and is properly configured!</p>";
                echo "<p>The column will automatically record the date and time when items are claimed.</p>";
            } else {
                echo "<p style='color: red; font-weight: bold;'>✗ claimed_at column NOT FOUND!</p>";
                echo "<p>The column needs to be added to the table.</p>";
            }
            
            // Show sample data if any exists
            $sampleResult = $conn->query("SELECT id, invoice_no, item_code, claimed_by, claimed_at FROM claimed_items ORDER BY claimed_at DESC LIMIT 5");
            
            if ($sampleResult && $sampleResult->num_rows > 0) {
                echo "<h3>Recent Claimed Items (Sample):</h3>";
                echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
                echo "<tr><th>ID</th><th>Invoice No</th><th>Item Code</th><th>Claimed By</th><th>Claimed At</th></tr>";
                
                while ($row = $sampleResult->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['invoice_no']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['claimed_by']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['claimed_at']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p><em>No claimed items found in the database yet.</em></p>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

$conn->close();

echo "<br><p><a href='claimitem.php'>← Back to Claim Item</a></p>";
?>
