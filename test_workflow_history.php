<?php
// Test script to verify workflow history data
// Run this after adding the database columns to check if everything is working

require_once 'session_check.php';
include 'config.php';

echo "<h2>Purchase Order Workflow History Test</h2>";

// Check if the new columns exist
$check_created_by = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'created_by'");
$has_created_by = ($check_created_by && $check_created_by->num_rows > 0);

$check_created_by_branch = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'created_by_branch'");
$has_created_by_branch = ($check_created_by_branch && $check_created_by_branch->num_rows > 0);

$check_received_by_branch = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'received_by_branch'");
$has_received_by_branch = ($check_received_by_branch && $check_received_by_branch->num_rows > 0);

$check_declined_by_branch = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'declined_by_branch'");
$has_declined_by_branch = ($check_declined_by_branch && $check_declined_by_branch->num_rows > 0);

echo "<h3>Database Column Check:</h3>";
echo "created_by column exists: " . ($has_created_by ? "✅ YES" : "❌ NO") . "<br>";
echo "created_by_branch column exists: " . ($has_created_by_branch ? "✅ YES" : "❌ NO") . "<br>";
echo "received_by_branch column exists: " . ($has_received_by_branch ? "✅ YES" : "❌ NO") . "<br>";
echo "declined_by_branch column exists: " . ($has_declined_by_branch ? "✅ YES" : "❌ NO") . "<br><br>";

if (!$has_created_by || !$has_created_by_branch || !$has_received_by_branch || !$has_declined_by_branch) {
    echo "<p style='color: red;'><strong>Please run the SQL script 'add_purchase_order_columns.sql' first!</strong></p>";
    exit;
}

// Get current session info
echo "<h3>Current Session Info:</h3>";
echo "User Name: " . ($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Not set') . "<br>";
echo "User Branch: " . ($_SESSION['user_branch'] ?? 'Not set') . "<br>";

// Get user's branch code with super admin handling
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$branch_code = '000';

if ($system_level === 'Super-Admin' || strtoupper($user_branch) === 'SUPERADMIN') {
    $branch_code = 'ALL';
    echo "System Level: " . $system_level . " (Super Admin - All Branches)<br>";
} elseif (!empty($user_branch)) {
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($user_branch) . "'");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_result = $branch_query->fetch_assoc();
        $branch_code = $branch_result['branch_code'];
    }
    echo "System Level: " . $system_level . " (Branch User)<br>";
}
echo "Created By Branch Code: " . $branch_code . "<br><br>";

// Show recent purchase orders with workflow data
echo "<h3>Recent Purchase Orders with Workflow Data:</h3>";
$recent_pos = $conn->query("SELECT po_number, supplier_company, created_by, created_by_branch, received_by, received_by_branch, declined_by, declined_by_branch, status, created_at FROM purchase_orders ORDER BY id DESC LIMIT 5");

if ($recent_pos && $recent_pos->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>PO Number</th><th>Supplier</th><th>Created By</th><th>Created Branch</th><th>Received By</th><th>Received Branch</th><th>Declined By</th><th>Declined Branch</th><th>Status</th><th>Created At</th></tr>";
    
    while ($row = $recent_pos->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['po_number']) . "</td>";
        echo "<td>" . htmlspecialchars($row['supplier_company']) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_by'] ?? 'Not set') . "</td>";
        echo "<td>" . htmlspecialchars($row['created_by_branch'] ?? 'Not set') . "</td>";
        echo "<td>" . htmlspecialchars($row['received_by'] ?? 'Not received') . "</td>";
        echo "<td>" . htmlspecialchars($row['received_by_branch'] ?? 'Not received') . "</td>";
        echo "<td>" . htmlspecialchars($row['declined_by'] ?? 'Not declined') . "</td>";
        echo "<td>" . htmlspecialchars($row['declined_by_branch'] ?? 'Not declined') . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No purchase orders found.</p>";
}

echo "<br><h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Create a new purchase order to test the workflow tracking</li>";
echo "<li>Change the status to 'Received' to test the received_by_branch tracking</li>";
echo "<li>Change the status to 'Decline' to test the declined_by_branch tracking</li>";
echo "<li>View the purchase order details to see the enhanced workflow history</li>";
echo "<li>Verify that it shows 'Created by [Name] ([Branch])', 'Received by [Name] ([Branch])', and 'Declined by [Name] ([Branch])'</li>";
echo "</ol>";
?>