<?php
require_once 'session_check.php';
// Check branches table to understand the mapping
include 'config.php';

echo "<h2>Branch Mapping Check</h2>";

// Get all branches
$query = "SELECT branch_code, branch_name FROM branches ORDER BY branch_code";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<h3>Branches:</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background: #e0e0e0;'><th>Branch Code</th><th>Branch Name</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['branch_code']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No branches found</p>";
}

// Check what's stored in preorders
echo "<h3>Branch Codes in Preorders Table:</h3>";
$query2 = "SELECT DISTINCT branch_code FROM preorders ORDER BY branch_code";
$result2 = $conn->query($query2);

if ($result2 && $result2->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background: #e0e0e0;'><th>Branch Code (as stored in preorders)</th></tr>";
    while ($row = $result2->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['branch_code']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>
