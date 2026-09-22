<?php
require_once 'session_check.php';

echo "<h2>Session Debug Information</h2>";
echo "<hr>";

echo "<h3>system_level value:</h3>";
echo "<pre>";
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
echo "Raw value: " . htmlspecialchars($system_level) . "\n";
echo "Length: " . strlen($system_level) . "\n";
echo "Trimmed value: " . htmlspecialchars(trim($system_level)) . "\n";
echo "</pre>";

echo "<h3>Comparison Tests:</h3>";
echo "<pre>";
echo "system_level === 'Super-Admin': " . ($system_level === 'Super-Admin' ? 'TRUE' : 'FALSE') . "\n";
echo "system_level === 'SUPER ADMIN': " . ($system_level === 'SUPER ADMIN' ? 'TRUE' : 'FALSE') . "\n";
echo "strcasecmp(system_level, 'Super-Admin'): " . strcasecmp($system_level, 'Super-Admin') . " (0 = match)\n";
echo "strcasecmp(system_level, 'Super-Admin') === 0: " . (strcasecmp($system_level, 'Super-Admin') === 0 ? 'TRUE' : 'FALSE') . "\n";
echo "</pre>";

echo "<h3>All Session Variables:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<hr>";
echo "<p><a href='itemreg.php'>Go to Item Registration</a></p>";
?>
