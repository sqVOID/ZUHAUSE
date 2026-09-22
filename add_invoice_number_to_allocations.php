<?php
/**
 * Migration: Add invoice_number column to purchase_order_allocations table
 */

require_once 'session_check.php';
require_once 'config.php';

// Check if user is Super Admin
$sys_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
if (strcasecmp($sys_level, 'Super-Admin') !== 0) {
    die("<h2>Access Denied</h2><p>Only Super Admin can run this script.</p><a href='main.php'>Go to Dashboard</a>");
}

echo "<h2>Migration: Add invoice_number to purchase_order_allocations</h2>";

try {
    // Check if the column already exists
    $check_query = "SHOW COLUMNS FROM purchase_order_allocations LIKE 'invoice_number'";
    $result = $conn->query($check_query);
    
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: orange;'>Column 'invoice_number' already exists in purchase_order_allocations table.</p>";
    } else {
        // Add the column
        $alter_query = "ALTER TABLE purchase_order_allocations 
                       ADD COLUMN invoice_number VARCHAR(100) DEFAULT NULL AFTER received_qty";
        
        if ($conn->query($alter_query)) {
            echo "<p style='color: green;'>✓ Successfully added 'invoice_number' column to purchase_order_allocations table.</p>";
        } else {
            throw new Exception("Failed to add column: " . $conn->error);
        }
    }
    
    echo "<h3 style='color: green;'>✓ Migration completed successfully!</h3>";
    echo "<p><a href='backfill_allocation_received_data.php'>Next: Run Backfill Script</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Migration failed!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>
