<?php
require_once 'session_check.php';
include 'config.php';

echo "<h2>Stock Transfers Table Check</h2>";

// Check if table exists
$check_table = "SHOW TABLES LIKE 'stock_transfers'";
$result = $conn->query($check_table);

if ($result->num_rows == 0) {
    echo "<p style='color: red;'>Table 'stock_transfers' does not exist!</p>";
    exit;
}

echo "<p style='color: green;'>Table 'stock_transfers' exists.</p>";

// Count total records
$count_query = "SELECT COUNT(*) as total FROM stock_transfers";
$result = $conn->query($count_query);
$row = $result->fetch_assoc();
echo "<h3>Total records: " . $row['total'] . "</h3>";

// Show all records
$sql = "SELECT * FROM stock_transfers ORDER BY st_date DESC LIMIT 20";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<h3>Sample Data (First 20 records):</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr>
            <th>ST Number</th>
            <th>Date</th>
            <th>Branch From</th>
            <th>Branch To</th>
            <th>Prepared By</th>
            <th>Status</th>
            <th>Remarks</th>
          </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['st_number']) . "</td>";
        echo "<td>" . htmlspecialchars($row['st_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['branch_from']) . "</td>";
        echo "<td>" . htmlspecialchars($row['branch_to']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prepared_by']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['remarks']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>No records found in stock_transfers table.</p>";
}

// Show distinct statuses
$status_query = "SELECT DISTINCT status, COUNT(*) as count FROM stock_transfers GROUP BY status";
$result = $conn->query($status_query);

if ($result->num_rows > 0) {
    echo "<h3>Status Distribution:</h3>";
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li><strong>" . htmlspecialchars($row['status']) . ":</strong> " . $row['count'] . " records</li>";
    }
    echo "</ul>";
}

// Show session info
echo "<h3>Session Information:</h3>";
echo "<ul>";
echo "<li><strong>User Branch:</strong> " . (isset($_SESSION['user_branch']) ? $_SESSION['user_branch'] : 'Not set') . "</li>";
echo "<li><strong>System Level:</strong> " . (isset($_SESSION['system_level']) ? $_SESSION['system_level'] : 'Not set') . "</li>";
echo "</ul>";

$conn->close();
?>
