<?php
/**
 * Migration: Remove supplier_name (Supplier Agent) column from suppliers table
 */

require_once 'session_check.php';
require_once 'config.php';

// Check if user is Super Admin
$sys_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
if (strcasecmp($sys_level, 'Super-Admin') !== 0) {
    die("<h2>Access Denied</h2><p>Only Super Admin can run this script.</p><a href='main.php'>Go to Dashboard</a>");
}

echo "<h2>Migration: Remove Supplier Agent Column</h2>";

try {
    // Check if the column exists
    $check_query = "SHOW COLUMNS FROM suppliers LIKE 'supplier_name'";
    $result = $conn->query($check_query);
    
    if ($result && $result->num_rows > 0) {
        // Column exists, remove it
        $alter_query = "ALTER TABLE suppliers DROP COLUMN supplier_name";
        
        if ($conn->query($alter_query)) {
            echo "<p style='color: green;'>✓ Successfully removed 'supplier_name' (Supplier Agent) column from suppliers table.</p>";
        } else {
            throw new Exception("Failed to remove column: " . $conn->error);
        }
    } else {
        echo "<p style='color: orange;'>Column 'supplier_name' does not exist in suppliers table (already removed or never existed).</p>";
    }
    
    echo "<h3 style='color: green;'>✓ Migration completed successfully!</h3>";
    echo "<p><a href='supplierreg.php'>Go to Supplier Registration</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Migration failed!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>
