<?php
require_once 'config.php';

echo "<h2>Testing Suppliers Data</h2>";

// Check if suppliers table exists
$table_check = $conn->query("SHOW TABLES LIKE 'suppliers'");
if ($table_check && $table_check->num_rows > 0) {
    echo "<p style='color: green;'>✓ Suppliers table exists</p>";
    
    // Check table structure
    echo "<h3>Table Structure:</h3>";
    $structure = $conn->query("DESCRIBE suppliers");
    echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = $structure->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
    }
    echo "</table>";
    
    // Count suppliers
    $count_query = $conn->query("SELECT COUNT(*) as total FROM suppliers");
    $count = $count_query->fetch_assoc()['total'];
    echo "<p>Total suppliers: <strong>{$count}</strong></p>";
    
    // Count active suppliers
    $active_query = $conn->query("SELECT COUNT(*) as total FROM suppliers WHERE status = 'Active'");
    $active = $active_query->fetch_assoc()['total'];
    echo "<p>Active suppliers: <strong>{$active}</strong></p>";
    
    // Show all suppliers
    echo "<h3>All Suppliers:</h3>";
    $all_suppliers = $conn->query("SELECT * FROM suppliers ORDER BY store_name ASC");
    
    if ($all_suppliers && $all_suppliers->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Store Name</th><th>Contact</th><th>Address</th><th>Status</th></tr>";
        while ($row = $all_suppliers->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>" . htmlspecialchars($row['store_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['contact_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['address']) . "</td>";
            echo "<td>{$row['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>No suppliers found in database</p>";
    }
    
    // Test the search_supplier.php query
    echo "<h3>Testing Search Query:</h3>";
    echo "<p>Testing: <code>search_supplier.php?type=company&term=</code></p>";
    
    $test_query = $conn->query("SELECT DISTINCT store_name FROM suppliers WHERE status = 'Active' ORDER BY store_name ASC LIMIT 20");
    if ($test_query && $test_query->num_rows > 0) {
        echo "<p style='color: green;'>✓ Query successful! Found {$test_query->num_rows} active companies:</p>";
        echo "<ul>";
        while ($row = $test_query->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['store_name']) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>✗ Query returned no results</p>";
    }
    
} else {
    echo "<p style='color: red;'>✗ Suppliers table does not exist</p>";
}

$conn->close();
?>
