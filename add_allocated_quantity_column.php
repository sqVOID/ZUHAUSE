<?php
/**
 * Migration: Add allocated_quantity column to purchase_order_items table
 * This column tracks how many units have been allocated to branches
 */

require_once 'config.php';

echo "Starting migration: Add allocated_quantity column to purchase_order_items table...\n";

try {
    // Check if the column already exists
    $check_query = "SHOW COLUMNS FROM purchase_order_items LIKE 'allocated_quantity'";
    $result = $conn->query($check_query);

    if ($result && $result->num_rows > 0) {
        echo "Column 'allocated_quantity' already exists in purchase_order_items table.\n";
    } else {
        // Add the column
        $alter_query = "ALTER TABLE purchase_order_items 
                       ADD COLUMN allocated_quantity INT DEFAULT 0 AFTER quantity";

        if ($conn->query($alter_query)) {
            echo "Successfully added 'allocated_quantity' column to purchase_order_items table.\n";
        } else {
            throw new Exception("Failed to add column: " . $conn->error);
        }
    }

    // Also check if received_qty column exists (for future receiving functionality)
    $check_received_query = "SHOW COLUMNS FROM purchase_order_items LIKE 'received_qty'";
    $result_received = $conn->query($check_received_query);

    if ($result_received && $result_received->num_rows > 0) {
        echo "Column 'received_qty' already exists in purchase_order_items table.\n";
    } else {
        // Add the column
        $alter_received_query = "ALTER TABLE purchase_order_items 
                                ADD COLUMN received_qty INT DEFAULT 0 AFTER allocated_quantity";

        if ($conn->query($alter_received_query)) {
            echo "Successfully added 'received_qty' column to purchase_order_items table.\n";
        } else {
            throw new Exception("Failed to add received_qty column: " . $conn->error);
        }
    }

    echo "\nMigration completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
?>