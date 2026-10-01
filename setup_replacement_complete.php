<?php
/**
 * Complete Setup Script for Replacement Unit System
 * Run this once to ensure all database tables and columns are properly set up
 */

require_once 'session_check.php';
include 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Replacement Unit System Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .success {
            color: #4CAF50;
            padding: 10px;
            margin: 10px 0;
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
        }
        .error {
            color: #f44336;
            padding: 10px;
            margin: 10px 0;
            background: #ffebee;
            border-left: 4px solid #f44336;
        }
        .info {
            color: #2196F3;
            padding: 10px;
            margin: 10px 0;
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #fafafa;
            border-radius: 4px;
        }
        .step-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
            color: #555;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Replacement Unit System Setup</h1>
        <p>This script will set up all necessary database tables and columns for the replacement unit system.</p>

<?php

$errors = [];
$success = [];

try {
    // Step 1: Create replacements table
    echo '<div class="step">';
    echo '<div class="step-title">Step 1: Setting up replacements table...</div>';
    
    $create_replacements = "CREATE TABLE IF NOT EXISTS `replacements` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `replacement_no` VARCHAR(50) UNIQUE NOT NULL,
        `invoice_no` VARCHAR(50) NOT NULL,
        `new_invoice_no` VARCHAR(50) NULL,
        `branch` VARCHAR(100),
        `branch_code` VARCHAR(20),
        `reason` TEXT,
        `remarks` TEXT,
        `total_amount` DECIMAL(10,2) DEFAULT 0,
        `created_by` VARCHAR(100),
        `created_at` DATETIME,
        `approved_by` VARCHAR(100),
        `approved_at` DATETIME,
        `disapproved_by` VARCHAR(100),
        `disapproved_at` DATETIME,
        `disapproval_reason` TEXT,
        `status` VARCHAR(20) DEFAULT 'Pending',
        INDEX `idx_replacement_no` (`replacement_no`),
        INDEX `idx_invoice_no` (`invoice_no`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($create_replacements)) {
        echo '<div class="success">✅ Replacements table ready</div>';
    } else {
        echo '<div class="error">❌ Error: ' . $conn->error . '</div>';
    }
    echo '</div>';

    // Step 2: Create replacement_old_items table
    echo '<div class="step">';
    echo '<div class="step-title">Step 2: Setting up replacement_old_items table...</div>';
    
    $create_old_items = "CREATE TABLE IF NOT EXISTS `replacement_old_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `replacement_id` INT NOT NULL,
        `item_code` VARCHAR(50),
        `item_description` VARCHAR(255),
        `imei` VARCHAR(50),
        `price` DECIMAL(10,2) DEFAULT 0,
        FOREIGN KEY (`replacement_id`) REFERENCES `replacements`(`id`) ON DELETE CASCADE,
        INDEX `idx_replacement_id` (`replacement_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($create_old_items)) {
        echo '<div class="success">✅ Replacement old items table ready</div>';
    } else {
        echo '<div class="error">❌ Error: ' . $conn->error . '</div>';
    }
    echo '</div>';

    // Step 3: Create replacement_new_items table
    echo '<div class="step">';
    echo '<div class="step-title">Step 3: Setting up replacement_new_items table...</div>';
    
    $create_new_items = "CREATE TABLE IF NOT EXISTS `replacement_new_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `replacement_id` INT NOT NULL,
        `item_code` VARCHAR(50),
        `item_description` VARCHAR(255),
        `imei` VARCHAR(50),
        `quantity` INT DEFAULT 1,
        `price` DECIMAL(10,2) DEFAULT 0,
        FOREIGN KEY (`replacement_id`) REFERENCES `replacements`(`id`) ON DELETE CASCADE,
        INDEX `idx_replacement_id` (`replacement_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($create_new_items)) {
        echo '<div class="success">✅ Replacement new items table ready</div>';
    } else {
        echo '<div class="error">❌ Error: ' . $conn->error . '</div>';
    }
    echo '</div>';

    // Step 4: Create replacement_approval_log table
    echo '<div class="step">';
    echo '<div class="step-title">Step 4: Setting up replacement_approval_log table...</div>';
    
    $create_approval_log = "CREATE TABLE IF NOT EXISTS `replacement_approval_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `replacement_id` INT NOT NULL,
        `replacement_no` VARCHAR(50),
        `original_invoice_no` VARCHAR(50),
        `new_invoice_no` VARCHAR(50),
        `branch` VARCHAR(100),
        `branch_code` VARCHAR(20),
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
        `status` VARCHAR(20),
        INDEX `idx_replacement_id` (`replacement_id`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($create_approval_log)) {
        echo '<div class="success">✅ Replacement approval log table ready</div>';
    } else {
        echo '<div class="error">❌ Error: ' . $conn->error . '</div>';
    }
    echo '</div>';

    // Step 5: Add old_imei column to sales_entry_items
    echo '<div class="step">';
    echo '<div class="step-title">Step 5: Adding old_imei column to sales_entry_items...</div>';
    
    $check_old_imei = $conn->query("SHOW COLUMNS FROM sales_entry_items LIKE 'old_imei'");
    
    if ($check_old_imei && $check_old_imei->num_rows > 0) {
        echo '<div class="info">ℹ️ Column old_imei already exists</div>';
    } else {
        $add_old_imei = "ALTER TABLE sales_entry_items ADD COLUMN old_imei VARCHAR(50) NULL AFTER imei";
        if ($conn->query($add_old_imei)) {
            echo '<div class="success">✅ Added old_imei column to sales_entry_items</div>';
        } else {
            echo '<div class="error">❌ Error: ' . $conn->error . '</div>';
        }
    }
    echo '</div>';

    // Step 6: Verify all tables exist
    echo '<div class="step">';
    echo '<div class="step-title">Step 6: Verifying setup...</div>';
    
    $tables_to_check = [
        'replacements',
        'replacement_old_items',
        'replacement_new_items',
        'replacement_approval_log',
        'sales_entry_items'
    ];
    
    $all_good = true;
    foreach ($tables_to_check as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check && $check->num_rows > 0) {
            echo "<div class='success'>✅ Table '$table' exists</div>";
        } else {
            echo "<div class='error'>❌ Table '$table' is missing!</div>";
            $all_good = false;
        }
    }
    
    if ($all_good) {
        echo '<div class="success" style="font-size: 18px; margin-top: 20px;">
            <strong>🎉 Setup Complete!</strong><br>
            All tables and columns are properly configured.
        </div>';
    }
    echo '</div>';

} catch (Exception $e) {
    echo '<div class="error">Fatal Error: ' . $e->getMessage() . '</div>';
}

$conn->close();
?>

        <div style="margin-top: 30px; text-align: center;">
            <a href="replacementunit.php" class="btn">Go to Replacement Unit</a>
            <a href="approval-replacementunit.php" class="btn">Go to Approval</a>
            <a href="report-replacementunit.php" class="btn">Go to Report</a>
        </div>
    </div>
</body>
</html>
