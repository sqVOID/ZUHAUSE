<?php
require_once 'session_check.php';
session_start();
include 'config.php';

$imei = 'KF49E7316907';

echo "<h2>Testing IMEI Search: $imei</h2>";
echo "<p><strong>Session Branch:</strong> '" . ($_SESSION['user_branch'] ?? 'NOT SET') . "'</p>";
echo "<p><strong>Session User:</strong> " . ($_SESSION['username'] ?? 'NOT SET') . "</p>";
echo "<p><strong>System Level:</strong> " . ($_SESSION['system_level'] ?? 'NOT SET') . "</p>";

// Test the actual API with current session
echo "<h3>API Response with Current Session:</h3>";
$api_url = "http://" . $_SERVER['HTTP_HOST'] . "/MOTOGAM/search_imei_claimpreorder.php?imei=$imei";
$context = stream_context_create([
    'http' => [
        'header' => 'Cookie: ' . $_SERVER['HTTP_COOKIE']
    ]
]);
$response = file_get_contents($api_url, false, $context);
echo "<pre>";
echo htmlspecialchars($response);
echo "</pre>";

$json = json_decode($response, true);
echo "<h3>Parsed JSON:</h3>";
echo "<pre>";
print_r($json);
echo "</pre>";

// Check what branch Super Admin has
echo "<h3>Debug Info:</h3>";
$system_level = $_SESSION['system_level'] ?? 'NULL';
$is_super_admin = (strcasecmp($system_level, 'Super-Admin') === 0);
echo "<p>system_level = '$system_level'</p>";
echo "<p>Is Super Admin? " . ($is_super_admin ? 'YES' : 'NO') . "</p>";
echo "<p>user_branch = '" . ($_SESSION['user_branch'] ?? 'NULL') . "'</p>";
echo "<p>Is empty? " . (empty($_SESSION['user_branch']) ? 'YES' : 'NO') . "</p>";

// Check the item's branch field
$sql_item = "SELECT branch, srp FROM items WHERE item_code = 'HONDA-CLICK-160-WHITE'";
$res = $conn->query($sql_item);
if ($res && $res->num_rows > 0) {
    $item = $res->fetch_assoc();
    echo "<p><strong>Item branch field:</strong> '" . ($item['branch'] ?? 'NULL') . "'</p>";
    echo "<p><strong>Item SRP:</strong> " . $item['srp'] . "</p>";
    echo "<p><strong>Item has branch restrictions?</strong> " . (empty($item['branch']) ? 'NO' : 'YES') . "</p>";
}

?>
