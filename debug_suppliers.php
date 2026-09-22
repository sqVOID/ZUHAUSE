<?php
require_once 'session_check.php';
include 'config.php';

echo "<h2>Debug Suppliers Data</h2>";

// Show all suppliers
$sql = "SELECT id, supplier_name, store_name, contact_number, address, status FROM suppliers ORDER BY store_name, supplier_name";
$result = $conn->query($sql);

echo "<h3>All Suppliers:</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Supplier Name</th><th>Store Name</th><th>Contact</th><th>Address</th><th>Status</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['supplier_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['store_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['contact_number']) . "</td>";
    echo "<td>" . htmlspecialchars($row['address']) . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Show unique company names
echo "<h3>Unique Company Names:</h3>";
$sql2 = "SELECT DISTINCT store_name FROM suppliers WHERE status = 'Active' ORDER BY store_name";
$result2 = $conn->query($sql2);

echo "<ul>";
while ($row = $result2->fetch_assoc()) {
    echo "<li>" . htmlspecialchars($row['store_name']) . "</li>";
}
echo "</ul>";

// Test specific company
echo "<h3>Suppliers for MOTOSIKLOS:</h3>";
$sql3 = "SELECT * FROM suppliers WHERE status = 'Active' AND store_name = 'MOTOSIKLOS'";
$result3 = $conn->query($sql3);

if ($result3->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Supplier Name</th><th>Store Name</th><th>Contact</th><th>Address</th></tr>";
    while ($row = $result3->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['supplier_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['store_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['contact_number']) . "</td>";
        echo "<td>" . htmlspecialchars($row['address']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No suppliers found for MOTOSIKLOS</p>";
}

$conn->close();
?>