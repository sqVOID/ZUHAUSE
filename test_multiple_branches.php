<?php
/**
 * Test Script for Multiple Branch Support
 * This script helps verify that Sub-admin users with multiple branches can access their data correctly
 */

require_once 'session_check.php';
require_once 'config.php';

echo "<!DOCTYPE html><html><head><title>Multiple Branch Test</title>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} table{border-collapse:collapse;margin:20px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f0f0f0;}</style>";
echo "</head><body>";

echo "<h1>Multiple Branch Support Test</h1>";

// Display current user info
echo "<h2>Current User Information</h2>";
echo "<table>";
echo "<tr><th>Field</th><th>Value</th></tr>";
echo "<tr><td>Username</td><td>" . htmlspecialchars($_SESSION['username'] ?? 'N/A') . "</td></tr>";
echo "<tr><td>System Level</td><td>" . htmlspecialchars($_SESSION['system_level'] ?? 'N/A') . "</td></tr>";
echo "<tr><td>User Branch (Raw)</td><td>" . htmlspecialchars($_SESSION['user_branch'] ?? 'N/A') . "</td></tr>";
echo "</table>";

$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

// Test 1: Parse multiple branches
echo "<h2>Test 1: Branch Parsing</h2>";
if (!empty($user_branch)) {
    $branch_names = array_map('trim', explode(',', $user_branch));
    echo "<p class='success'>✓ Found " . count($branch_names) . " branch(es)</p>";
    echo "<table>";
    echo "<tr><th>#</th><th>Branch Name</th><th>Branch Code</th></tr>";
    
    $branch_codes = [];
    foreach ($branch_names as $idx => $branch_name) {
        $branch_query = $conn->query("SELECT branch_code, branch_name FROM branches WHERE branch_name = '" . $conn->real_escape_string($branch_name) . "' LIMIT 1");
        if ($branch_query && $branch_query->num_rows > 0) {
            $branch_data = $branch_query->fetch_assoc();
            $branch_codes[] = $branch_data['branch_code'];
            echo "<tr><td>" . ($idx + 1) . "</td><td>" . htmlspecialchars($branch_name) . "</td><td class='success'>" . htmlspecialchars($branch_data['branch_code']) . " ✓</td></tr>";
        } else {
            echo "<tr><td>" . ($idx + 1) . "</td><td>" . htmlspecialchars($branch_name) . "</td><td class='error'>NOT FOUND ✗</td></tr>";
        }
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ No branches assigned</p>";
}

// Test 2: Check Purchase Order access
echo "<h2>Test 2: Purchase Order Access</h2>";
if (!empty($branch_codes)) {
    $branch_codes_quoted = array_map(function($code) use ($conn) {
        return "'" . $conn->real_escape_string($code) . "'";
    }, $branch_codes);
    $branch_in_clause = implode(', ', $branch_codes_quoted);
    
    echo "<p class='info'>SQL Clause: <code>created_by_branch IN ($branch_in_clause)</code></p>";
    
    $po_query = "SELECT po_number, supplier_company, created_by_branch, status 
                 FROM purchase_orders 
                 WHERE created_by_branch IN ($branch_in_clause) 
                 ORDER BY id DESC 
                 LIMIT 10";
    
    $result = $conn->query($po_query);
    
    if ($result && $result->num_rows > 0) {
        echo "<p class='success'>✓ Found " . $result->num_rows . " purchase order(s)</p>";
        echo "<table>";
        echo "<tr><th>PO Number</th><th>Supplier</th><th>Branch Code</th><th>Status</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['po_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['supplier_company']) . "</td>";
            echo "<td>" . htmlspecialchars($row['created_by_branch']) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>No purchase orders found for your branches (this is OK if you haven't created any)</p>";
    }
} else {
    echo "<p class='error'>✗ Cannot test - no valid branch codes found</p>";
}

// Test 3: Check Stock on Hand access
echo "<h2>Test 3: Stock on Hand Access</h2>";
if (!empty($branch_names)) {
    $branch_names_quoted = array_map(function($name) {
        return "'" . addslashes($name) . "'";
    }, $branch_names);
    $branch_in_clause_names = implode(',', $branch_names_quoted);
    
    echo "<p class='info'>SQL Clause: <code>branch IN ($branch_in_clause_names)</code></p>";
    
    $stock_query = "SELECT item_code, description, branch, COUNT(*) as qty 
                    FROM stock_on_hand 
                    WHERE item_type = 'IMEI' AND branch IN ($branch_in_clause_names) 
                    GROUP BY item_code, description, branch 
                    LIMIT 10";
    
    $result = $conn->query($stock_query);
    
    if ($result && $result->num_rows > 0) {
        echo "<p class='success'>✓ Found " . $result->num_rows . " stock item(s)</p>";
        echo "<table>";
        echo "<tr><th>Item Code</th><th>Description</th><th>Branch</th><th>Qty</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
            echo "<td>" . htmlspecialchars($row['branch']) . "</td>";
            echo "<td>" . htmlspecialchars($row['qty']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>No stock found for your branches (this is OK if your branches have no stock)</p>";
    }
}

// Summary
echo "<h2>Summary</h2>";
if (strcasecmp($system_level, 'Super-Admin') === 0) {
    echo "<p class='info'>You are Super-Admin - You should have access to ALL branches</p>";
} elseif (strcasecmp($system_level, 'Sub-admin') === 0) {
    echo "<p class='success'>You are Sub-admin - You should only see data from: " . htmlspecialchars($user_branch) . "</p>";
} else {
    echo "<p class='info'>You are a regular user - You should only see data from: " . htmlspecialchars($user_branch) . "</p>";
}

echo "<p><a href='purchaseorder.php'>Go to Purchase Order Page</a> | <a href='sohandunit.php'>Go to Stock on Hand</a></p>";

echo "</body></html>";
?>
