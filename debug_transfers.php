<?php
require_once 'session_check.php';
include 'config.php';

// Get user's branch info
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

echo "<h2>Debug Transfer Data</h2>";
echo "<p><strong>Your Branch:</strong> " . htmlspecialchars($user_branch) . "</p>";
echo "<p><strong>System Level:</strong> " . htmlspecialchars($system_level) . "</p>";

// Get branch code
$branch_code = '';
$branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($user_branch) . "' LIMIT 1");
if ($branch_query && $branch_query->num_rows > 0) {
    $branch_data = $branch_query->fetch_assoc();
    $branch_code = $branch_data['branch_code'];
}
echo "<p><strong>Your Branch Code:</strong> " . htmlspecialchars($branch_code) . "</p>";

// Get recent transfers (pending)
echo "<h3>Recent Pending Transfers:</h3>";
$query = "SELECT st_number, st_date, branch_from, branch_to, status, prepared_by 
          FROM stock_transfers 
          WHERE status = 'Pending' 
          ORDER BY st_date DESC, st_number DESC 
          LIMIT 10";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>ST Number</th><th>Date</th><th>Branch From</th><th>Branch To</th><th>Status</th><th>Prepared By</th><th>Match?</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $matches = '';
        if ($row['branch_from'] === $user_branch || $row['branch_from'] === $branch_code) {
            $matches .= 'FROM ';
        }
        if ($row['branch_to'] === $user_branch || $row['branch_to'] === $branch_code) {
            $matches .= 'TO ';
        }
        if (empty($matches)) {
            $matches = 'NO MATCH';
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['st_number']) . "</td>";
        echo "<td>" . htmlspecialchars($row['st_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['branch_from']) . "</td>";
        echo "<td>" . htmlspecialchars($row['branch_to']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prepared_by']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($matches) . "</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No pending transfers found</p>";
}

echo "<h3>All Your Branches:</h3>";
$branches_query = "SELECT branch_code, branch_name FROM branches WHERE branch_name = '" . $conn->real_escape_string($user_branch) . "'";
$branches_result = $conn->query($branches_query);
if ($branches_result && $branches_result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Branch Code</th><th>Branch Name</th></tr>";
    while ($b = $branches_result->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($b['branch_code']) . "</td><td>" . htmlspecialchars($b['branch_name']) . "</td></tr>";
    }
    echo "</table>";
}
?>
