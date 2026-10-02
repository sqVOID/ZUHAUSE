<?php
// ====================================================
// Return to Supplier (RTS) - Table Setup Script
// ====================================================
// This script creates all necessary RTS tables
// Run this once: http://localhost/ZUHAUSE/setup_rts_tables.php
// ====================================================

require_once 'config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>RTS Table Setup</title>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
    .success { color: green; background: #e8f5e9; padding: 10px; margin: 10px 0; border-left: 4px solid green; }
    .error { color: red; background: #ffebee; padding: 10px; margin: 10px 0; border-left: 4px solid red; }
    .info { color: blue; background: #e3f2fd; padding: 10px; margin: 10px 0; border-left: 4px solid blue; }
    h1 { color: #0d3347; }
    .code { background: #f5f5f5; padding: 10px; border-radius: 4px; font-family: monospace; overflow-x: auto; }
</style>";
echo "</head><body>";

echo "<h1>Return to Supplier - Database Setup</h1>";

$tables_created = 0;
$tables_existed = 0;
$errors = [];

try {
    // 1. Create return_to_supplier table
    echo "<h2>1. Creating return_to_supplier table...</h2>";
    $sql1 = "CREATE TABLE IF NOT EXISTS `return_to_supplier` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `rts_number` VARCHAR(50) NOT NULL,
        `rts_date` VARCHAR(20),
        `reference_number` VARCHAR(100),
        `branch_from` VARCHAR(10),
        `branch_name` VARCHAR(255),
        `delivery_to` VARCHAR(255),
        `remarks` TEXT,
        `created_by` VARCHAR(100),
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_rts_number` (`rts_number`),
        INDEX `idx_branch` (`branch_from`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql1)) {
        echo "<div class='success'>✓ return_to_supplier table created/verified successfully</div>";
        $tables_created++;
    } else {
        throw new Exception("Error creating return_to_supplier: " . $conn->error);
    }
    
    // 2. Create return_to_supplier_items table
    echo "<h2>2. Creating return_to_supplier_items table...</h2>";
    $sql2 = "CREATE TABLE IF NOT EXISTS `return_to_supplier_items` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `rts_id` INT(11) NOT NULL,
        `rts_number` VARCHAR(50),
        `item_code` VARCHAR(100),
        `item_description` TEXT,
        `imei` VARCHAR(100),
        `quantity` INT(11) DEFAULT 0,
        `cost` DECIMAL(15,2) DEFAULT 0.00,
        `reason` TEXT,
        INDEX `idx_rts_id` (`rts_id`),
        INDEX `idx_rts_number` (`rts_number`),
        INDEX `idx_item_code` (`item_code`),
        INDEX `idx_imei` (`imei`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql2)) {
        echo "<div class='success'>✓ return_to_supplier_items table created/verified successfully</div>";
        $tables_created++;
    } else {
        throw new Exception("Error creating return_to_supplier_items: " . $conn->error);
    }
    
    // 3. Create rts_approval_log table
    echo "<h2>3. Creating rts_approval_log table...</h2>";
    $sql3 = "CREATE TABLE IF NOT EXISTS `rts_approval_log` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `rts_id` INT(11) NOT NULL,
        `rts_number` VARCHAR(50) NOT NULL,
        `reference_number` VARCHAR(100),
        `branch_from` VARCHAR(255),
        `branch_code` VARCHAR(10),
        `delivery_to` VARCHAR(255),
        `reason` TEXT,
        `remarks` TEXT,
        `total_items` INT(11) DEFAULT 0,
        `created_by` VARCHAR(100),
        `created_at` DATETIME,
        `approver` VARCHAR(100),
        `approval_date` DATETIME,
        `disapprover` VARCHAR(100),
        `disapproval_date` DATETIME,
        `status` VARCHAR(20) DEFAULT 'Pending',
        INDEX `idx_rts_id` (`rts_id`),
        INDEX `idx_rts_number` (`rts_number`),
        INDEX `idx_status` (`status`),
        INDEX `idx_created_at` (`created_at`),
        INDEX `idx_branch` (`branch_from`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql3)) {
        echo "<div class='success'>✓ rts_approval_log table created/verified successfully</div>";
        $tables_created++;
    } else {
        throw new Exception("Error creating rts_approval_log: " . $conn->error);
    }
    
    // Verify tables exist
    echo "<h2>4. Verifying tables...</h2>";
    $check_tables = ['return_to_supplier', 'return_to_supplier_items', 'rts_approval_log'];
    
    foreach ($check_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<div class='success'>✓ Table '$table' exists</div>";
            
            // Show column count
            $columns = $conn->query("SHOW COLUMNS FROM $table");
            $col_count = $columns->num_rows;
            echo "<div class='info'>  → $col_count columns defined</div>";
        } else {
            echo "<div class='error'>✗ Table '$table' NOT found!</div>";
        }
    }
    
    echo "<h2>✓ Setup Complete!</h2>";
    echo "<div class='success'>";
    echo "<strong>Summary:</strong><br>";
    echo "• $tables_created tables created/verified<br>";
    echo "• RTS system is ready to use<br>";
    echo "• You can now use returntosupplier.php and approval-returntosupplier.php";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. Go to <a href='returntosupplier.php'>Return to Supplier</a> to create RTS entries<br>";
    echo "2. Go to <a href='approval-returntosupplier.php'>RTS Approval</a> to approve/disapprove entries<br>";
    echo "3. You can delete this setup file (setup_rts_tables.php) after successful setup";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>Error occurred:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<strong>Troubleshooting:</strong><br>";
    echo "1. Check your database connection in config.php<br>";
    echo "2. Ensure your MySQL user has CREATE TABLE privileges<br>";
    echo "3. Verify the database name is correct<br>";
    echo "4. Check MySQL error log for details";
    echo "</div>";
}

$conn->close();

echo "</body></html>";
?>
