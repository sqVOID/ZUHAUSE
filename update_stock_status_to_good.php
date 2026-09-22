<?php
// Update stock_on_hand status to "Good Stock"
require_once 'config.php';

echo "Starting migration to update stock_on_hand status to 'Good Stock'...\n";

// Check if status column exists
$check_column = $conn->query("SHOW COLUMNS FROM stock_on_hand LIKE 'status'");

if ($check_column && $check_column->num_rows > 0) {
    echo "Status column found in stock_on_hand table.\n";
    
    // Update all records to have status = "Good Stock"
    $update_sql = "UPDATE stock_on_hand SET status = 'Good Stock'";
    
    if ($conn->query($update_sql) === TRUE) {
        $affected_rows = $conn->affected_rows;
        echo "✓ Successfully updated $affected_rows records to 'Good Stock' status.\n";
        
        // Display some sample records (first get actual columns)
        $columns_result = $conn->query("SHOW COLUMNS FROM stock_on_hand");
        $available_columns = [];
        while ($col = $columns_result->fetch_assoc()) {
            $available_columns[] = $col['Field'];
        }
        
        // Build SELECT query with available columns
        $select_cols = [];
        $display_cols = [];
        
        if (in_array('id', $available_columns)) { $select_cols[] = 'id'; $display_cols[] = 'ID'; }
        if (in_array('item_code', $available_columns)) { $select_cols[] = 'item_code'; $display_cols[] = 'Item Code'; }
        if (in_array('branch', $available_columns)) { $select_cols[] = 'branch'; $display_cols[] = 'Branch'; }
        if (in_array('quantity', $available_columns)) { $select_cols[] = 'quantity'; $display_cols[] = 'Quantity'; }
        if (in_array('status', $available_columns)) { $select_cols[] = 'status'; $display_cols[] = 'Status'; }
        
        if (count($select_cols) > 0) {
            $sample_sql = "SELECT " . implode(", ", $select_cols) . " FROM stock_on_hand LIMIT 5";
            $sample_result = $conn->query($sample_sql);
            
            if ($sample_result && $sample_result->num_rows > 0) {
                echo "\nSample records after update:\n";
                echo str_repeat("-", 80) . "\n";
                echo implode(" | ", $display_cols) . "\n";
                echo str_repeat("-", 80) . "\n";
                
                while ($row = $sample_result->fetch_assoc()) {
                    $values = [];
                    foreach ($select_cols as $col) {
                        $values[] = $row[$col];
                    }
                    echo implode(" | ", $values) . "\n";
                }
            }
        }
    } else {
        echo "✗ Error updating records: " . $conn->error . "\n";
    }
    
} else {
    echo "✗ Status column does not exist in stock_on_hand table.\n";
    echo "Would you like to add the status column first? (Y/N)\n";
    
    // Add status column if it doesn't exist
    $add_column_sql = "ALTER TABLE stock_on_hand ADD COLUMN status VARCHAR(50) DEFAULT 'Good Stock'";
    
    if ($conn->query($add_column_sql) === TRUE) {
        echo "✓ Successfully added status column with default value 'Good Stock'.\n";
        
        // Count records
        $count_result = $conn->query("SELECT COUNT(*) as total FROM stock_on_hand");
        if ($count_result) {
            $count_row = $count_result->fetch_assoc();
            echo "✓ All {$count_row['total']} records now have status = 'Good Stock'.\n";
        }
    } else {
        echo "✗ Error adding status column: " . $conn->error . "\n";
    }
}

echo "\nMigration completed!\n";

$conn->close();
?>
