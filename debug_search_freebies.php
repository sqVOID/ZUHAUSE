<?php
// Debug version - shows all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "<h2>Debug Search Freebies</h2>";
echo "<pre>";

// Check session
echo "=== SESSION CHECK ===\n";
echo "user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "\n";
echo "user_branch: " . (isset($_SESSION['user_branch']) ? $_SESSION['user_branch'] : 'NOT SET') . "\n";
echo "\n";

// Check database connection
echo "=== DATABASE CONNECTION ===\n";
include 'config.php';
if (isset($conn)) {
    echo "✓ Database connected\n";
    echo "Connection type: " . get_class($conn) . "\n";
} else {
    echo "✗ Database NOT connected\n";
    die("Cannot proceed without database connection");
}
echo "\n";

// Check tables exist
echo "=== CHECK TABLES ===\n";
$tables_check = ['items', 'stock_on_hand', 'branches'];
foreach ($tables_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Table '$table' exists\n";
    } else {
        echo "✗ Table '$table' DOES NOT exist\n";
    }
}
echo "\n";

// Check items table structure
echo "=== ITEMS TABLE STRUCTURE ===\n";
$result = $conn->query("DESCRIBE items");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "✗ Cannot describe items table: " . $conn->error . "\n";
}
echo "\n";

// Check stock_on_hand table structure
echo "=== STOCK_ON_HAND TABLE STRUCTURE ===\n";
$result = $conn->query("DESCRIBE stock_on_hand");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "✗ Cannot describe stock_on_hand table: " . $conn->error . "\n";
}
echo "\n";

// Get user branch and branch code
if (isset($_SESSION['user_branch'])) {
    $user_branch = trim($_SESSION['user_branch']);
    echo "=== BRANCH LOOKUP ===\n";
    echo "User branch: $user_branch\n";
    
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($user_branch) . "'");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
        echo "Branch code: $branch_code\n";
    } else {
        $branch_code = '000';
        echo "Branch code not found, using default: $branch_code\n";
    }
    echo "\n";
    
    // Test search query
    echo "=== TEST SEARCH QUERY ===\n";
    $search_term = isset($_GET['term']) ? $_GET['term'] : 'test';
    echo "Search term: $search_term\n\n";
    
    $term = $conn->real_escape_string($search_term);
    $whereClause = "i.item_code LIKE '%$term%'";
    
    $sql = "SELECT i.id, i.item_code, i.description, i.branch, i.has_serial,
            COALESCE(SUM(soh.quantity), 0) as stock_quantity
            FROM items i 
            LEFT JOIN stock_on_hand soh ON i.item_code = soh.item_code 
                AND soh.branch = '" . $conn->real_escape_string($user_branch) . "'
                AND (LOWER(TRIM(soh.status)) = 'available' OR LOWER(TRIM(soh.status)) = 'active')
            WHERE $whereClause 
            AND i.status = 'Active'
            AND (i.has_serial = 0 OR i.has_serial IS NULL)
            GROUP BY i.id, i.item_code, i.description, i.branch, i.has_serial
            LIMIT 20";
    
    echo "SQL Query:\n$sql\n\n";
    
    $result = $conn->query($sql);
    
    if ($result === false) {
        echo "✗ SQL ERROR: " . $conn->error . "\n";
    } else {
        echo "✓ Query executed successfully\n";
        echo "Rows found: " . $result->num_rows . "\n\n";
        
        if ($result->num_rows > 0) {
            echo "Results:\n";
            while ($row = $result->fetch_assoc()) {
                echo "  - " . $row['item_code'] . " | " . $row['description'] . " | Stock: " . $row['stock_quantity'] . " | Serial: " . ($row['has_serial'] ?? 'NULL') . "\n";
            }
        }
    }
}

echo "</pre>";
?>
