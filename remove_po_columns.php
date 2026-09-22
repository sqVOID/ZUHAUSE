<?php
/**
 * Database Migration Script
 * Removes unused columns from purchase_orders table
 * 
 * Columns to be removed:
 * - supplier_name
 * - contact_number
 * - address
 * - branch
 */

require_once 'config.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html><head><title>Database Migration - Remove PO Columns</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}";
echo ".container{max-width:800px;margin:0 auto;background:white;padding:30px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo "h1{color:#333;border-bottom:2px solid #b08a52;padding-bottom:10px;}";
echo ".success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:12px;border-radius:4px;margin:10px 0;}";
echo ".error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:12px;border-radius:4px;margin:10px 0;}";
echo ".info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:12px;border-radius:4px;margin:10px 0;}";
echo ".code{background:#f8f9fa;border:1px solid #dee2e6;padding:10px;border-radius:4px;font-family:monospace;margin:10px 0;overflow-x:auto;}";
echo "</style></head><body>";
echo "<div class='container'>";
echo "<h1>🔧 Database Migration: Remove Purchase Order Columns</h1>";

try {
    // Check connection
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    echo "<div class='info'><strong>Starting migration...</strong></div>";
    
    // List of columns to remove
    $columns_to_remove = [
        'supplier_name',
        'contact_number',
        'address',
        'branch'
    ];
    
    $removed_columns = [];
    $not_found_columns = [];
    $errors = [];
    
    // Check which columns exist first
    echo "<h3>Step 1: Checking existing columns</h3>";
    $result = $conn->query("SHOW COLUMNS FROM purchase_orders");
    $existing_columns = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
        }
        echo "<div class='code'>";
        echo "Existing columns in purchase_orders table:<br>";
        echo implode(', ', $existing_columns);
        echo "</div>";
    }
    
    // Remove each column
    echo "<h3>Step 2: Removing columns</h3>";
    
    foreach ($columns_to_remove as $column) {
        if (in_array($column, $existing_columns)) {
            echo "<div class='info'>Processing column: <strong>{$column}</strong></div>";
            
            $sql = "ALTER TABLE purchase_orders DROP COLUMN `{$column}`";
            
            if ($conn->query($sql) === TRUE) {
                $removed_columns[] = $column;
                echo "<div class='success'>✓ Successfully removed column: <strong>{$column}</strong></div>";
            } else {
                $errors[] = "Error removing {$column}: " . $conn->error;
                echo "<div class='error'>✗ Error removing column {$column}: " . $conn->error . "</div>";
            }
        } else {
            $not_found_columns[] = $column;
            echo "<div class='info'>ℹ Column <strong>{$column}</strong> does not exist (already removed or never existed)</div>";
        }
    }
    
    // Show final status
    echo "<h3>Step 3: Migration Summary</h3>";
    
    if (count($removed_columns) > 0) {
        echo "<div class='success'>";
        echo "<strong>✓ Successfully removed " . count($removed_columns) . " column(s):</strong><br>";
        echo implode(', ', $removed_columns);
        echo "</div>";
    }
    
    if (count($not_found_columns) > 0) {
        echo "<div class='info'>";
        echo "<strong>ℹ " . count($not_found_columns) . " column(s) not found (skipped):</strong><br>";
        echo implode(', ', $not_found_columns);
        echo "</div>";
    }
    
    if (count($errors) > 0) {
        echo "<div class='error'>";
        echo "<strong>✗ Errors encountered:</strong><br>";
        foreach ($errors as $error) {
            echo "• " . htmlspecialchars($error) . "<br>";
        }
        echo "</div>";
    }
    
    // Show updated table structure
    echo "<h3>Step 4: Updated Table Structure</h3>";
    $result = $conn->query("SHOW COLUMNS FROM purchase_orders");
    
    if ($result) {
        echo "<div class='code'>";
        echo "<table style='width:100%; border-collapse:collapse;'>";
        echo "<tr style='background:#f8f9fa; font-weight:bold;'>";
        echo "<td style='padding:8px; border:1px solid #dee2e6;'>Field</td>";
        echo "<td style='padding:8px; border:1px solid #dee2e6;'>Type</td>";
        echo "<td style='padding:8px; border:1px solid #dee2e6;'>Null</td>";
        echo "<td style='padding:8px; border:1px solid #dee2e6;'>Key</td>";
        echo "<td style='padding:8px; border:1px solid #dee2e6;'>Default</td>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='padding:8px; border:1px solid #dee2e6;'>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td style='padding:8px; border:1px solid #dee2e6;'>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td style='padding:8px; border:1px solid #dee2e6;'>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td style='padding:8px; border:1px solid #dee2e6;'>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td style='padding:8px; border:1px solid #dee2e6;'>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "</div>";
    }
    
    // Final message
    if (count($errors) === 0) {
        echo "<div class='success'>";
        echo "<h3>✓ Migration Completed Successfully!</h3>";
        echo "<p>All specified columns have been processed. The purchase_orders table has been updated.</p>";
        echo "<p><strong>Next steps:</strong></p>";
        echo "<ul>";
        echo "<li>Test the Create Purchase Order form to ensure it works correctly</li>";
        echo "<li>Verify existing purchase orders are still accessible</li>";
        echo "<li>You can safely delete this migration script after verification</li>";
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<h3>⚠ Migration Completed with Errors</h3>";
        echo "<p>Some operations failed. Please review the errors above and run the script again if needed.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>✗ Migration Failed:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<hr style='margin:30px 0;'>";
echo "<p style='color:#666; font-size:14px;'>";
echo "<strong>Note:</strong> This migration script removes the following columns from the purchase_orders table:<br>";
echo "• supplier_name<br>";
echo "• contact_number<br>";
echo "• address<br>";
echo "• branch<br><br>";
echo "After successful migration, you can delete this file (remove_po_columns.php) for security.";
echo "</p>";

echo "</div></body></html>";

// Close connection
if (isset($conn)) {
    $conn->close();
}
?>
