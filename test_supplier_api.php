<?php
require_once 'session_check.php';
include 'config.php';

echo "<h2>Test Supplier API</h2>";

// Test company search
echo "<h3>Test 1: Get Companies</h3>";
$url1 = "search_supplier.php?type=company&term=";
echo "<p>URL: <a href='$url1' target='_blank'>$url1</a></p>";

// Test supplier search for MOTOSIKLOS
echo "<h3>Test 2: Get Suppliers for MOTOSIKLOS</h3>";
$url2 = "search_supplier.php?type=supplier&company=MOTOSIKLOS&term=";
echo "<p>URL: <a href='$url2' target='_blank'>$url2</a></p>";

// Direct test
echo "<h3>Direct Test Results:</h3>";

// Test the supplier query directly
$companyName = 'MOTOSIKLOS';
$sql = "SELECT id, supplier_name, store_name, contact_number, address 
        FROM suppliers 
        WHERE status = 'Active' 
        AND TRIM(store_name) = TRIM(?) 
        ORDER BY supplier_name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $companyName);
$stmt->execute();
$result = $stmt->get_result();

echo "<p>Query: $sql</p>";
echo "<p>Company parameter: '$companyName'</p>";
echo "<p>Results found: " . $result->num_rows . "</p>";

if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Supplier Name</th><th>Store Name</th><th>Contact</th><th>Address</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['supplier_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['store_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['contact_number']) . "</td>";
        echo "<td>" . htmlspecialchars($row['address']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>