<?php
/**
 * Migration: Add claimed_at column to preorders table
 * This tracks when a pre-order was claimed by the customer
 */

require_once 'config.php';

echo "Starting migration: Add claimed_at to preorders table...\n";

try {
    // Check if column already exists
    $check_sql = "SHOW COLUMNS FROM preorders LIKE 'claimed_at'";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows > 0) {
        echo "Column 'claimed_at' already exists in preorders table. No changes needed.\n";
    } else {
        // Add the claimed_at column
        $sql = "ALTER TABLE preorders 
                ADD COLUMN claimed_at DATETIME NULL DEFAULT NULL COMMENT 'Timestamp when pre-order was claimed' 
                AFTER claimed_invoice_no";
        
        if ($conn->query($sql) === TRUE) {
            echo "SUCCESS: Column 'claimed_at' added to preorders table.\n";
        } else {
            throw new Exception("Error adding column: " . $conn->error);
        }
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
?>
