<?php
require_once 'session_check.php';
require_once 'config.php';

echo "<h2>Debug Sidebar Settings</h2>";

// Check accounts table structure
echo "<h3>Accounts Table Structure:</h3>";
$structure = $conn->query("DESCRIBE accounts");
if ($structure) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Default</th></tr>";
    while ($row = $structure->fetch_assoc()) {
        echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td><td>" . $row['Default'] . "</td></tr>";
    }
    echo "</table><br>";
}

// Check all accounts data
echo "<h3>All Accounts Data:</h3>";
$accounts = $conn->query("SELECT id, username, first_name, last_name, sidebar_source, sidebar_access FROM accounts ORDER BY id");
if ($accounts) {
    echo "<table border='1'><tr><th>ID</th><th>Username</th><th>Name</th><th>Sidebar Source</th><th>Sidebar Access</th></tr>";
    while ($row = $accounts->fetch_assoc()) {
        $sidebar_access_preview = strlen($row['sidebar_access']) > 50 ? 
            substr($row['sidebar_access'], 0, 50) . "..." : 
            $row['sidebar_access'];
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['username'] . "</td>";
        echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
        echo "<td>" . ($row['sidebar_source'] ?? 'NULL') . "</td>";
        echo "<td>" . ($sidebar_access_preview ?: 'EMPTY') . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
}

// Check positions table
echo "<h3>Positions Table:</h3>";
$positions = $conn->query("SELECT position_name, sidebar_access FROM positions");
if ($positions) {
    echo "<table border='1'><tr><th>Position</th><th>Sidebar Access</th></tr>";
    while ($row = $positions->fetch_assoc()) {
        $sidebar_access_preview = strlen($row['sidebar_access']) > 50 ? 
            substr($row['sidebar_access'], 0, 50) . "..." : 
            $row['sidebar_access'];
        echo "<tr>";
        echo "<td>" . $row['position_name'] . "</td>";
        echo "<td>" . ($sidebar_access_preview ?: 'EMPTY') . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
}

$conn->close();
?>