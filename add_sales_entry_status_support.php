<?php
// Migration script to add Sales Entry Status support
require_once 'config.php';

echo "<!DOCTYPE html><html><head><title>Sales Entry Status Migration</title></head><body>";
echo "<h2>Sales Entry Status Migration</h2>";

try {
    // 1. Add modified_by and modified_at columns to stock_on_hand if they don't exist
    echo "<h3>Step 1: Checking stock_on_hand table columns...</h3>";
    
    $check_modified_by = $conn->query("SHOW COLUMNS FROM stock_on_hand LIKE 'modified_by'");
    if ($check_modified_by->num_rows == 0) {
        $sql = "ALTER TABLE stock_on_hand ADD COLUMN modified_by VARCHAR(100) NULL AFTER status";
        if ($conn->query($sql)) {
            echo "✓ Added 'modified_by' column to stock_on_hand<br>";
        } else {
            echo "✗ Error adding 'modified_by': " . $conn->error . "<br>";
        }
    } else {
        echo "✓ Column 'modified_by' already exists<br>";
    }
    
    $check_modified_at = $conn->query("SHOW COLUMNS FROM stock_on_hand LIKE 'modified_at'");
    if ($check_modified_at->num_rows == 0) {
        $sql = "ALTER TABLE stock_on_hand ADD COLUMN modified_at DATETIME NULL AFTER modified_by";
        if ($conn->query($sql)) {
            echo "✓ Added 'modified_at' column to stock_on_hand<br>";
        } else {
            echo "✗ Error adding 'modified_at': " . $conn->error . "<br>";
        }
    } else {
        echo "✓ Column 'modified_at' already exists<br>";
    }

    // 2. Create sales_entry_status_log table if it doesn't exist
    echo "<h3>Step 2: Creating sales_entry_status_log table...</h3>";
    
    $check_table = $conn->query("SHOW TABLES LIKE 'sales_entry_status_log'");
    if ($check_table->num_rows == 0) {
        $create_table_sql = "CREATE TABLE sales_entry_status_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entry_date VARCHAR(50) NULL,
            branch_code VARCHAR(20) NULL,
            branch_name VARCHAR(100) NULL,
            stock_type VARCHAR(50) NULL COMMENT 'Good Stock or Defective',
            remarks TEXT NULL,
            items_count INT DEFAULT 0,
            updated_count INT DEFAULT 0,
            created_by VARCHAR(100) NULL,
            created_at DATETIME NULL,
            INDEX idx_branch_name (branch_name),
            INDEX idx_stock_type (stock_type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($create_table_sql)) {
            echo "✓ Created 'sales_entry_status_log' table<br>";
        } else {
            echo "✗ Error creating table: " . $conn->error . "<br>";
        }
    } else {
        echo "✓ Table 'sales_entry_status_log' already exists<br>";
    }

    // 3. Ensure status column exists in stock_on_hand
    echo "<h3>Step 3: Verifying 'status' column in stock_on_hand...</h3>";
    
    $check_status = $conn->query("SHOW COLUMNS FROM stock_on_hand LIKE 'status'");
    if ($check_status->num_rows == 0) {
        $sql = "ALTER TABLE stock_on_hand ADD COLUMN status VARCHAR(50) DEFAULT 'Good Stock' AFTER quantity";
        if ($conn->query($sql)) {
            echo "✓ Added 'status' column to stock_on_hand<br>";
        } else {
            echo "✗ Error adding 'status': " . $conn->error . "<br>";
        }
    } else {
        echo "✓ Column 'status' already exists<br>";
    }

    // 4. Set default status for existing records if NULL
    echo "<h3>Step 4: Setting default status for existing records...</h3>";
    $update_sql = "UPDATE stock_on_hand SET status = 'Good Stock' WHERE status IS NULL OR status = ''";
    if ($conn->query($update_sql)) {
        $affected = $conn->affected_rows;
        echo "✓ Updated $affected record(s) with default status 'Good Stock'<br>";
    } else {
        echo "✗ Error updating default status: " . $conn->error . "<br>";
    }

    echo "<h3 style='color: green;'>✓ Migration completed successfully!</h3>";
    echo "<p><a href='salesentry-status.php'>Go to Sales Entry Status Page</a></p>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Migration failed: " . $e->getMessage() . "</h3>";
}

$conn->close();

echo "</body></html>";
?>
