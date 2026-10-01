<?php
require_once 'session_check.php';
include 'config.php';

echo "<h2>Setting up Replacement Tables...</h2>";

try {
    // 1. Create replacements table
    $create_replacements = "CREATE TABLE IF NOT EXISTS `replacements` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `replacement_no` VARCHAR(50) UNIQUE NOT NULL,
        `invoice_no` VARCHAR(50) NOT NULL,
        `new_invoice_no` VARCHAR(50) DEFAULT NULL,
        `reason` VARCHAR(100) NOT NULL,
        `remarks` TEXT,
        `less_amount` DECIMAL(10,2) DEFAULT 0,
        `total_amount` DECIMAL(10,2) DEFAULT 0,
        `created_by` VARCHAR(100),
        `branch` VARCHAR(100),
        `branch_code` VARCHAR(10),
        `status` VARCHAR(20) DEFAULT 'Pending',
        `created_at` DATETIME,
        `approved_by` VARCHAR(100),
        `approved_at` DATETIME,
        `disapproved_by` VARCHAR(100),
        `disapproved_at` DATETIME,
        `disapproval_reason` TEXT,
        INDEX `idx_replacement_no` (`replacement_no`),
        INDEX `idx_invoice_no` (`invoice_no`),
        INDEX `idx_status` (`status`),
        INDEX `idx_created_at` (`created_at`),
        INDEX `idx_branch` (`branch`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($create_replacements)) {
        echo "<p style='color: green;'>✓ Replacements table created/verified</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating replacements table: " . $conn->error . "</p>";
    }

    // 2. Add missing columns to replacements table
    $columns_to_add = [
        'new_invoice_no' => "VARCHAR(50) DEFAULT NULL AFTER invoice_no",
        'branch_code' => "VARCHAR(10) AFTER branch",
        'approved_by' => "VARCHAR(100) AFTER created_at",
        'approved_at' => "DATETIME AFTER approved_by",
        'disapproved_by' => "VARCHAR(100) AFTER approved_at",
        'disapproved_at' => "DATETIME AFTER disapproved_by",
        'disapproval_reason' => "TEXT AFTER disapproved_at"
    ];

    foreach ($columns_to_add as $column => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM replacements LIKE '$column'");
        if ($check->num_rows == 0) {
            $alter = "ALTER TABLE replacements ADD COLUMN $column $definition";
            if ($conn->query($alter)) {
                echo "<p style='color: green;'>✓ Added column: $column</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Could not add column $column: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>• Column $column already exists</p>";
        }
    }

    // 3. Create replacement_old_items table
    $create_old_items = "CREATE TABLE IF NOT EXISTS `replacement_old_items` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `replacement_id` INT(11) NOT NULL,
        `item_description` VARCHAR(255),
        `imei` VARCHAR(50),
        `price` DECIMAL(10,2),
        INDEX `idx_replacement_id` (`replacement_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($create_old_items)) {
        echo "<p style='color: green;'>✓ Replacement old items table created/verified</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating replacement_old_items table: " . $conn->error . "</p>";
    }

    // 4. Create replacement_new_items table
    $create_new_items = "CREATE TABLE IF NOT EXISTS `replacement_new_items` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `replacement_id` INT(11) NOT NULL,
        `item_code` VARCHAR(50),
        `item_description` VARCHAR(255),
        `imei` VARCHAR(50),
        `quantity` INT(11) DEFAULT 1,
        `price` DECIMAL(10,2),
        INDEX `idx_replacement_id` (`replacement_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($create_new_items)) {
        echo "<p style='color: green;'>✓ Replacement new items table created/verified</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating replacement_new_items table: " . $conn->error . "</p>";
    }

    // 5. Create replacement_approval_log table
    $create_log = "CREATE TABLE IF NOT EXISTS `replacement_approval_log` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `replacement_id` INT(11) NOT NULL,
        `replacement_no` VARCHAR(50) NOT NULL,
        `original_invoice_no` VARCHAR(50),
        `new_invoice_no` VARCHAR(50),
        `branch` VARCHAR(255),
        `branch_code` VARCHAR(10),
        `reason` TEXT,
        `remarks` TEXT,
        `total_amount` DECIMAL(10,2) DEFAULT 0,
        `created_by` VARCHAR(100),
        `created_at` DATETIME,
        `approver` VARCHAR(100),
        `approval_date` DATETIME,
        `disapprover` VARCHAR(100),
        `disapproval_date` DATETIME,
        `disapproval_reason` TEXT,
        `status` VARCHAR(20) DEFAULT 'Pending',
        INDEX `idx_replacement_id` (`replacement_id`),
        INDEX `idx_replacement_no` (`replacement_no`),
        INDEX `idx_status` (`status`),
        INDEX `idx_created_at` (`created_at`),
        INDEX `idx_branch` (`branch`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($create_log)) {
        echo "<p style='color: green;'>✓ Replacement approval log table created/verified</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating replacement_approval_log table: " . $conn->error . "</p>";
    }

    echo "<h3 style='color: green;'>✓ Setup Complete!</h3>";
    echo "<p><a href='approval-replacementunit.php'>Go to Approval Replacement Unit</a></p>";
    echo "<p><a href='replacementunit.php'>Go to Replacement Unit</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>
