<?php
/**
 * Fix Collation Issues
 * This script standardizes the collation across all relevant tables to utf8mb4_general_ci
 * to prevent "Illegal mix of collations" errors
 */

require_once 'config.php';

echo "Starting collation fix...\n\n";

try {
    // Tables and columns to check/fix
    $tables_to_fix = [
        'purchase_orders' => ['po_number'],
        'purchase_order_items' => ['po_number', 'family_code'],
        'purchase_order_allocations' => ['po_number', 'family_code', 'branch_name', 'branch_code'],
        'family_codes' => ['family_code'],
        'branches' => ['branch_name', 'branch_code']
    ];

    foreach ($tables_to_fix as $table => $columns) {
        // Check if table exists
        $table_check = $conn->query("SHOW TABLES LIKE '{$table}'");
        
        if ($table_check && $table_check->num_rows > 0) {
            echo "Processing table: {$table}\n";
            
            // Get current table collation
            $table_info = $conn->query("SHOW TABLE STATUS WHERE Name = '{$table}'");
            if ($table_info) {
                $info = $table_info->fetch_assoc();
                echo "  Current table collation: " . ($info['Collation'] ?? 'unknown') . "\n";
            }
            
            foreach ($columns as $column) {
                // Check if column exists
                $column_check = $conn->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
                
                if ($column_check && $column_check->num_rows > 0) {
                    $col_info = $column_check->fetch_assoc();
                    
                    // Get column type
                    $type = $col_info['Type'];
                    $null = $col_info['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
                    $default = '';
                    
                    if ($col_info['Default'] !== null) {
                        $default = "DEFAULT '" . $conn->real_escape_string($col_info['Default']) . "'";
                    } elseif ($col_info['Null'] === 'YES') {
                        $default = "DEFAULT NULL";
                    }
                    
                    // Modify column to use utf8mb4_general_ci
                    $alter_query = "ALTER TABLE {$table} 
                                   MODIFY COLUMN {$column} {$type} 
                                   CHARACTER SET utf8mb4 
                                   COLLATE utf8mb4_general_ci 
                                   {$null} {$default}";
                    
                    if ($conn->query($alter_query)) {
                        echo "  ✓ Fixed column: {$column}\n";
                    } else {
                        echo "  ✗ Failed to fix column: {$column} - " . $conn->error . "\n";
                    }
                } else {
                    echo "  - Column {$column} does not exist (skipped)\n";
                }
            }
            
            // Also fix the table's default collation
            $alter_table_query = "ALTER TABLE {$table} 
                                 CONVERT TO CHARACTER SET utf8mb4 
                                 COLLATE utf8mb4_general_ci";
            
            if ($conn->query($alter_table_query)) {
                echo "  ✓ Fixed table default collation\n";
            } else {
                echo "  ✗ Failed to fix table collation - " . $conn->error . "\n";
            }
            
            echo "\n";
        } else {
            echo "Table {$table} does not exist (skipped)\n\n";
        }
    }
    
    echo "===========================================\n";
    echo "Collation fix completed!\n";
    echo "===========================================\n\n";
    
    // Verify the changes
    echo "Verification:\n";
    foreach ($tables_to_fix as $table => $columns) {
        $table_check = $conn->query("SHOW TABLES LIKE '{$table}'");
        if ($table_check && $table_check->num_rows > 0) {
            echo "\nTable: {$table}\n";
            foreach ($columns as $column) {
                $result = $conn->query("
                    SELECT COLUMN_NAME, COLLATION_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = '{$table}' 
                    AND COLUMN_NAME = '{$column}'
                ");
                
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    echo "  - {$row['COLUMN_NAME']}: {$row['COLLATION_NAME']}\n";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();

echo "\n✓ All done! You can now use the purchase order system without collation errors.\n";
?>
