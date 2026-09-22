<?php
require_once 'session_check.php';
include 'config.php';

echo "<h2>Testing Item Status Approval Data</h2>";
echo "<hr>";

// Check if table exists
$check_table = "SHOW TABLES LIKE 'sales_entry_status_log'";
$result = $conn->query($check_table);

if ($result->num_rows == 0) {
    echo "<p style='color: red;'>Table 'sales_entry_status_log' does not exist!</p>";
    exit;
}

echo "<p style='color: green;'>✓ Table 'sales_entry_status_log' exists</p>";

// Show table structure
echo "<h3>Table Structure:</h3>";
echo "<table border='1' cellpadding='5' cellspacing='0' style='background: white;'>";
echo "<tr style='background: #0d3347; color: white;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";

$columns = $conn->query("SHOW COLUMNS FROM sales_entry_status_log");
while ($col = $columns->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Count total records
$count_result = $conn->query("SELECT COUNT(*) as total FROM sales_entry_status_log");
$count_row = $count_result->fetch_assoc();
echo "<h3>Total Records: " . $count_row['total'] . "</h3>";

// Show all records
echo "<h3>All Records:</h3>";
echo "<div style='overflow-x: auto;'>";
echo "<table border='1' cellpadding='5' cellspacing='0' style='background: white; font-size: 12px;'>";
echo "<tr style='background: #0d3347; color: white;'>";
echo "<th>ID</th><th>Date</th><th>Branch Code</th><th>Branch Name</th><th>Stock Type</th>";
echo "<th>Remarks</th><th>Items Count</th><th>Updated Count</th><th>Created By</th>";
echo "<th>Created At</th><th>Approver</th><th>Approval Date</th><th>Disapproved By</th><th>Status</th>";
echo "</tr>";

$all_records = $conn->query("SELECT * FROM sales_entry_status_log ORDER BY id DESC");
if ($all_records->num_rows > 0) {
    while ($row = $all_records->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['entry_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['branch_code'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['branch_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['stock_type'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['remarks'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['items_count'] ?? 0) . "</td>";
        echo "<td>" . htmlspecialchars($row['updated_count'] ?? 0) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_by'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['approver'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['approval_date'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['disapproved_by'] ?? '') . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['status'] ?? 'Pending') . "</strong></td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='14' style='text-align: center; padding: 20px;'>No records found</td></tr>";
}
echo "</table>";
echo "</div>";

// Test query with session info
echo "<h3>Session Information:</h3>";
echo "<table border='1' cellpadding='5' cellspacing='0' style='background: white;'>";
echo "<tr><td><strong>System Level:</strong></td><td>" . htmlspecialchars($_SESSION['system_level'] ?? 'Not set') . "</td></tr>";
echo "<tr><td><strong>User Branch:</strong></td><td>" . htmlspecialchars($_SESSION['user_branch'] ?? 'Not set') . "</td></tr>";
echo "<tr><td><strong>Username:</strong></td><td>" . htmlspecialchars($_SESSION['username'] ?? 'Not set') . "</td></tr>";
echo "<tr><td><strong>User Name:</strong></td><td>" . htmlspecialchars($_SESSION['user_name'] ?? 'Not set') . "</td></tr>";
echo "</table>";

// Check items table
echo "<h3>Items Table Status:</h3>";
$check_items = "SHOW TABLES LIKE 'sales_entry_status_items'";
$items_result = $conn->query($check_items);

if ($items_result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Table 'sales_entry_status_items' exists</p>";
    
    $items_count = $conn->query("SELECT COUNT(*) as total FROM sales_entry_status_items");
    $items_count_row = $items_count->fetch_assoc();
    echo "<p>Total items: " . $items_count_row['total'] . "</p>";
    
    // Show items
    echo "<table border='1' cellpadding='5' cellspacing='0' style='background: white; font-size: 12px;'>";
    echo "<tr style='background: #0d3347; color: white;'>";
    echo "<th>ID</th><th>Log ID</th><th>Item Code</th><th>Item Description</th><th>IMEI</th><th>Quantity</th><th>Created At</th>";
    echo "</tr>";
    
    $items = $conn->query("SELECT * FROM sales_entry_status_items ORDER BY id DESC LIMIT 20");
    while ($item = $items->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($item['id']) . "</td>";
        echo "<td>" . htmlspecialchars($item['log_id']) . "</td>";
        echo "<td>" . htmlspecialchars($item['item_code'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($item['item_description'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($item['imei'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($item['quantity'] ?? 0) . "</td>";
        echo "<td>" . htmlspecialchars($item['created_at'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>✗ Table 'sales_entry_status_items' does not exist</p>";
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
    table {
        margin: 10px 0 20px 0;
        border-collapse: collapse;
    }
    th {
        text-align: left;
        font-weight: 600;
    }
    td {
        padding: 8px;
    }
</style>
