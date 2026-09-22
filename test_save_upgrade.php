<?php
require_once 'session_check.php';
// Test file to see the actual error
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';
session_start();

echo "Testing save_upgrade.php...<br>";
echo "Session user: " . ($_SESSION['user_name'] ?? 'Not set') . "<br>";
echo "Session branch: " . ($_SESSION['user_branch'] ?? 'Not set') . "<br>";

// Test if upgrades table exists
$result = $conn->query("SHOW TABLES LIKE 'upgrades'");
if ($result->num_rows > 0) {
    echo "✓ upgrades table exists<br>";
} else {
    echo "✗ upgrades table does NOT exist - Please run create_upgrade_tables.sql<br>";
}

// Test if upgrade_old_items table exists
$result = $conn->query("SHOW TABLES LIKE 'upgrade_old_items'");
if ($result->num_rows > 0) {
    echo "✓ upgrade_old_items table exists<br>";
} else {
    echo "✗ upgrade_old_items table does NOT exist - Please run create_upgrade_tables.sql<br>";
}

// Test if upgrade_new_items table exists
$result = $conn->query("SHOW TABLES LIKE 'upgrade_new_items'");
if ($result->num_rows > 0) {
    echo "✓ upgrade_new_items table exists<br>";
} else {
    echo "✗ upgrade_new_items table does NOT exist - Please run create_upgrade_tables.sql<br>";
}

echo "<br>If all tables exist, the issue might be with the data being sent.";
?>
