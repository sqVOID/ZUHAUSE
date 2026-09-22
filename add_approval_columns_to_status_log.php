<?php
require_once 'config.php';

echo "<h2>Adding Approval Columns to sales_entry_status_log Table</h2>";

try {
    // Check if table exists
    $check_table = "SHOW TABLES LIKE 'sales_entry_status_log'";
    $result = $conn->query($check_table);
    
    if ($result->num_rows == 0) {
        echo "<p style='color: red;'>Error: sales_entry_status_log table does not exist!</p>";
        exit;
    }
    
    echo "<p>Table sales_entry_status_log exists. Checking columns...</p>";
    
    // Check existing columns
    $check_columns = "SHOW COLUMNS FROM sales_entry_status_log";
    $columns_result = $conn->query($check_columns);
    $existing_columns = [];
    
    while ($col = $columns_result->fetch_assoc()) {
        $existing_columns[] = $col['Field'];
    }
    
    echo "<p>Existing columns: " . implode(', ', $existing_columns) . "</p>";
    
    // Add approver column if it doesn't exist
    if (!in_array('approver', $existing_columns)) {
        $add_approver = "ALTER TABLE sales_entry_status_log 
                         ADD COLUMN approver VARCHAR(100) AFTER created_at";
        if ($conn->query($add_approver)) {
            echo "<p style='color: green;'>✓ Added 'approver' column successfully</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding 'approver' column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>→ Column 'approver' already exists</p>";
    }
    
    // Add approval_date column if it doesn't exist
    if (!in_array('approval_date', $existing_columns)) {
        $add_approval_date = "ALTER TABLE sales_entry_status_log 
                              ADD COLUMN approval_date DATETIME AFTER approver";
        if ($conn->query($add_approval_date)) {
            echo "<p style='color: green;'>✓ Added 'approval_date' column successfully</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding 'approval_date' column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>→ Column 'approval_date' already exists</p>";
    }
    
    // Add disapproved_by column if it doesn't exist
    if (!in_array('disapproved_by', $existing_columns)) {
        $add_disapproved = "ALTER TABLE sales_entry_status_log 
                            ADD COLUMN disapproved_by VARCHAR(100) AFTER approval_date";
        if ($conn->query($add_disapproved)) {
            echo "<p style='color: green;'>✓ Added 'disapproved_by' column successfully</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding 'disapproved_by' column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>→ Column 'disapproved_by' already exists</p>";
    }
    
    // Add status column if it doesn't exist
    if (!in_array('status', $existing_columns)) {
        $add_status = "ALTER TABLE sales_entry_status_log 
                       ADD COLUMN status VARCHAR(20) DEFAULT 'Pending' AFTER disapproved_by";
        if ($conn->query($add_status)) {
            echo "<p style='color: green;'>✓ Added 'status' column successfully</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding 'status' column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>→ Column 'status' already exists</p>";
    }
    
    // Add index on status if it doesn't exist
    $check_indexes = "SHOW INDEX FROM sales_entry_status_log WHERE Key_name = 'idx_status'";
    $index_result = $conn->query($check_indexes);
    
    if ($index_result->num_rows == 0) {
        $add_index = "ALTER TABLE sales_entry_status_log ADD INDEX idx_status (status)";
        if ($conn->query($add_index)) {
            echo "<p style='color: green;'>✓ Added index on 'status' column successfully</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding index: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>→ Index on 'status' already exists</p>";
    }
    
    // Show final table structure
    echo "<h3>Final Table Structure:</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $show_columns = "SHOW COLUMNS FROM sales_entry_status_log";
    $final_columns = $conn->query($show_columns);
    
    while ($col = $final_columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<h3 style='color: green;'>Migration completed successfully!</h3>";
    echo "<p><a href='approval-itemstatus.php'>Go to Item Status Approval Page</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>

<style>
    body {
        font-family: Arial, sans-serif;
        padding: 20px;
        background-color: #f5f5f5;
    }
    h2, h3 {
        color: #333;
    }
    p {
        padding: 5px;
        margin: 5px 0;
    }
    table {
        background-color: white;
        margin-top: 10px;
    }
    th {
        background-color: #0d3347;
        color: white;
        padding: 10px;
    }
    td {
        padding: 8px;
    }
    a {
        display: inline-block;
        margin-top: 20px;
        padding: 10px 20px;
        background-color: #0d3347;
        color: white;
        text-decoration: none;
        border-radius: 4px;
    }
    a:hover {
        background-color: #164460;
    }
</style>
