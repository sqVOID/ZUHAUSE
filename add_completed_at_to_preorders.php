<?php
/**
 * Migration Script: Add completed_at column to preorders table
 * 
 * This column will store the date/time when a preorder is fully paid (completed).
 * This allows the preorder report to show entries based on completion date,
 * not just creation date.
 */

require_once 'config.php';

echo "<!DOCTYPE html><html><head><title>Add completed_at Column</title>";
echo "<style>body{font-family:Arial;padding:20px;max-width:800px;margin:0 auto;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border:1px solid #ccc;overflow-x:auto;}</style>";
echo "</head><body>";
echo "<h1>Add completed_at Column to preorders Table</h1>";

try {
    // Check if column already exists
    echo "<h2>Step 1: Check if column exists</h2>";
    $check_query = "SHOW COLUMNS FROM preorders LIKE 'completed_at'";
    $result = $conn->query($check_query);
    
    if ($result && $result->num_rows > 0) {
        echo "<p class='info'>✓ Column 'completed_at' already exists in preorders table.</p>";
        $column_exists = true;
    } else {
        echo "<p class='info'>Column 'completed_at' does not exist. Will add it now.</p>";
        $column_exists = false;
    }
    
    if (!$column_exists) {
        // Add the column
        echo "<h2>Step 2: Add completed_at column</h2>";
        $alter_query = "ALTER TABLE preorders 
                        ADD COLUMN completed_at DATETIME NULL DEFAULT NULL 
                        COMMENT 'Date/time when preorder was fully paid' 
                        AFTER updated_at";
        
        if ($conn->query($alter_query)) {
            echo "<p class='success'>✓ Successfully added 'completed_at' column to preorders table!</p>";
        } else {
            throw new Exception("Failed to add column: " . $conn->error);
        }
        
        // Update existing completed preorders
        echo "<h2>Step 3: Update existing completed preorders</h2>";
        $update_query = "UPDATE preorders 
                        SET completed_at = updated_at 
                        WHERE status = 'completed' 
                        AND completed_at IS NULL";
        
        if ($conn->query($update_query)) {
            $affected = $conn->affected_rows;
            echo "<p class='success'>✓ Updated $affected existing completed preorder(s) with completion date.</p>";
        } else {
            echo "<p class='error'>⚠ Warning: Could not update existing records: " . $conn->error . "</p>";
        }
    }
    
    // Show current table structure
    echo "<h2>Step 4: Verify table structure</h2>";
    $structure_query = "SHOW COLUMNS FROM preorders";
    $structure_result = $conn->query($structure_query);
    
    if ($structure_result) {
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse;width:100%;'>";
        echo "<tr style='background:#f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Default</th><th>Extra</th></tr>";
        while ($col = $structure_result->fetch_assoc()) {
            $highlight = ($col['Field'] === 'completed_at') ? "background:#ffffcc;" : "";
            echo "<tr style='$highlight'>";
            echo "<td><strong>{$col['Field']}</strong></td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>{$col['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h2>✅ Migration Complete!</h2>";
    echo "<p class='success'>The 'completed_at' column has been successfully added to the preorders table.</p>";
    echo "<p class='info'>This column will now be automatically populated when a preorder is marked as completed (fully paid).</p>";
    
    echo "<hr>";
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Update the preorder report to use 'completed_at' for date filtering when status is 'completed'</li>";
    echo "<li>Test the payment flow in preorder2.php to ensure completed_at is set correctly</li>";
    echo "<li>Verify the report shows preorders on their completion date, not creation date</li>";
    echo "</ol>";
    
    echo "<p><a href='preorder2.php'>Go to Preorder2</a> | <a href='preorderreport.php'>Go to Preorder Report</a></p>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>❌ Error</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
}

echo "</body></html>";

$conn->close();
?>
