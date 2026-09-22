<?php
/**
 * Migration: Create purchase_order_allocations table
 * This table tracks which items from a PO are allocated to which branches
 */

require_once 'config.php';

echo "Starting migration: Create purchase_order_allocations table...\n";

try {
    // Check if the table already exists
    $check_query = "SHOW TABLES LIKE 'purchase_order_allocations'";
    $result = $conn->query($check_query);
    
    if ($result && $result->num_rows > 0) {
        echo "Table 'purchase_order_allocations' already exists.\n";
    } else {
        // Create the table
        $create_query = "CREATE TABLE purchase_order_allocations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            po_id INT NOT NULL,
            po_number VARCHAR(50) NOT NULL,
            branch_name VARCHAR(100) NOT NULL,
            branch_code VARCHAR(50) NOT NULL,
            family_code VARCHAR(100) NOT NULL,
            quantity INT NOT NULL DEFAULT 0,
            received_qty INT NOT NULL DEFAULT 0,
            cost DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(50) DEFAULT 'Waiting',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_po_id (po_id),
            INDEX idx_po_number (po_number),
            INDEX idx_branch_name (branch_name),
            INDEX idx_family_code (family_code),
            FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($create_query)) {
            echo "Successfully created 'purchase_order_allocations' table.\n";
        } else {
            throw new Exception("Failed to create table: " . $conn->error);
        }
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
?>
