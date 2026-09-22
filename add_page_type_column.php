<?php
require_once 'session_check.php';
/**
 * Migration script to add page_type column to existing booklet_numbers table
 * Run this if you already have the booklet_numbers table without page_type column
 */

include 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Add Page Type Column - Migration</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #2e7d32; }
        .success { color: #2e7d32; background: #e8f5e9; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #c62828; background: #ffebee; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #1976d2; background: #e3f2fd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>
    <h1>Add Page Type Column Migration</h1>";

// Check if column already exists
$check_column = $conn->query("SHOW COLUMNS FROM booklet_numbers LIKE 'page_type'");

if ($check_column && $check_column->num_rows > 0) {
    echo "<div class='info'>ℹ Column 'page_type' already exists in booklet_numbers table. No migration needed.</div>";
} else {
    echo "<div class='info'>Adding 'page_type' column to booklet_numbers table...</div>";
    
    // Add the column
    $sql = "ALTER TABLE booklet_numbers 
            ADD COLUMN page_type varchar(50) NOT NULL DEFAULT 'salesentry' COMMENT 'salesentry, preorder, stocktransfer, etc.' 
            AFTER branch_code,
            ADD KEY page_type (page_type)";
    
    if ($conn->query($sql) === TRUE) {
        echo "<div class='success'>✓ Column 'page_type' added successfully!</div>";
        
        // Update existing records to have 'salesentry' as page_type
        $update_sql = "UPDATE booklet_numbers SET page_type = 'salesentry' WHERE page_type = '' OR page_type IS NULL";
        $conn->query($update_sql);
        
        echo "<div class='success'>✓ Existing records updated with default page_type='salesentry'</div>";
        
        // Check for duplicate entries that might violate unique constraint
        $check_duplicates = $conn->query("
            SELECT branch_code, COUNT(*) as count 
            FROM booklet_numbers 
            WHERE status = 'Active' 
            GROUP BY branch_code 
            HAVING count > 1
        ");
        
        if ($check_duplicates && $check_duplicates->num_rows > 0) {
            echo "<div class='info'>⚠ Warning: Found branches with multiple active booklets.</div>";
            echo "<div class='info'>You may want to review and set different page_types for each or deactivate duplicates.</div>";
            
            echo "<h3>Branches with Multiple Active Booklets:</h3>";
            echo "<ul>";
            while ($dup = $check_duplicates->fetch_assoc()) {
                echo "<li>Branch Code: " . htmlspecialchars($dup['branch_code']) . " (" . $dup['count'] . " active booklets)</li>";
            }
            echo "</ul>";
        }
        
    } else {
        echo "<div class='error'>✗ Error adding column: " . $conn->error . "</div>";
    }
}

echo "<h2>Current Table Structure:</h2>";
echo "<pre>";
$columns = $conn->query("SHOW COLUMNS FROM booklet_numbers");
if ($columns) {
    echo str_pad("Field", 20) . str_pad("Type", 20) . str_pad("Null", 10) . "Key\n";
    echo str_repeat("-", 70) . "\n";
    while ($col = $columns->fetch_assoc()) {
        echo str_pad($col['Field'], 20) . 
             str_pad($col['Type'], 20) . 
             str_pad($col['Null'], 10) . 
             $col['Key'] . "\n";
    }
}
echo "</pre>";

echo "<h2>Next Steps:</h2>";
echo "<ul>";
echo "<li>Go to <a href='bookletnoreg.php'>Booklet Number Registration</a> to manage booklet numbers</li>";
echo "<li>Each booklet entry now requires a Page Type selection</li>";
echo "<li>You can have different invoice formats for Sales Entry, Pre-Order, Stock Transfer, etc.</li>";
echo "<li>Edit existing booklet numbers to assign the correct page type</li>";
echo "</ul>";

echo "</div>
</body>
</html>";

$conn->close();
?>
