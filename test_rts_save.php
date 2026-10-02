<?php
// Quick test script to verify RTS save functionality
require_once 'session_check.php';
include 'config.php';

echo "<!DOCTYPE html><html><head><title>RTS Save Test</title>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; }
    .success { color: green; background: #e8f5e9; padding: 15px; margin: 10px 0; border-left: 4px solid green; }
    .error { color: red; background: #ffebee; padding: 15px; margin: 10px 0; border-left: 4px solid red; }
    .info { color: blue; background: #e3f2fd; padding: 15px; margin: 10px 0; border-left: 4px solid blue; }
    h2 { color: #0d3347; margin-top: 30px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f5f5f5; font-weight: bold; }
</style></head><body>";

echo "<h1>🧪 RTS System - Diagnostic Test</h1>";

$all_ok = true;

// Test 1: Check if tables exist
echo "<h2>1. Database Tables Check</h2>";
$required_tables = ['return_to_supplier', 'return_to_supplier_items', 'rts_approval_log'];
foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<div class='success'>✓ Table '$table' exists</div>";
    } else {
        echo "<div class='error'>✗ Table '$table' NOT found! Run setup_rts_tables.php</div>";
        $all_ok = false;
    }
}

// Test 2: Check booklet_numbers table structure
echo "<h2>2. Booklet Numbers Table Check</h2>";
$booklet_check = $conn->query("SHOW TABLES LIKE 'booklet_numbers'");
if ($booklet_check && $booklet_check->num_rows > 0) {
    echo "<div class='success'>✓ booklet_numbers table exists</div>";
    
    // Show columns
    $columns = $conn->query("SHOW COLUMNS FROM booklet_numbers");
    echo "<table><tr><th>Column Name</th><th>Type</th></tr>";
    while ($col = $columns->fetch_assoc()) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>✗ booklet_numbers table NOT found!</div>";
    $all_ok = false;
}

// Test 3: Check for returntosupplier page_type in booklet
echo "<h2>3. RTS Booklet Numbers Check</h2>";
$rts_booklet = $conn->query("SELECT * FROM booklet_numbers WHERE page_type = 'returntosupplier' LIMIT 5");
if ($rts_booklet && $rts_booklet->num_rows > 0) {
    echo "<div class='success'>✓ Found " . $rts_booklet->num_rows . " RTS booklet number(s)</div>";
    echo "<table><tr><th>ID</th><th>Branch</th><th>Current Number</th><th>Status</th></tr>";
    while ($book = $rts_booklet->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$book['id']}</td>";
        echo "<td>{$book['branch_code']}</td>";
        echo "<td>{$book['current_number']}</td>";
        echo "<td>{$book['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='info'>ℹ No RTS booklet numbers configured yet. You can add them in Booklet Number Registration.</div>";
}

// Test 4: Check required PHP files
echo "<h2>4. Required Files Check</h2>";
$required_files = [
    'save_return_to_supplier.php',
    'get_rts_approvals.php',
    'update_rts_approval.php',
    'preview_rts.php',
    'returntosupplier.php',
    'approval-returntosupplier.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<div class='success'>✓ $file exists</div>";
    } else {
        echo "<div class='error'>✗ $file NOT found!</div>";
        $all_ok = false;
    }
}

// Test 5: Check items and purchase_order_items tables
echo "<h2>5. Cost Lookup Tables Check</h2>";
$cost_tables = ['items', 'purchase_order_items'];
foreach ($cost_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $count = $conn->query("SELECT COUNT(*) as c FROM $table")->fetch_assoc()['c'];
        echo "<div class='success'>✓ Table '$table' exists ($count records)</div>";
    } else {
        echo "<div class='error'>✗ Table '$table' NOT found!</div>";
    }
}

// Test 6: Session check
echo "<h2>6. Session Information</h2>";
echo "<table>";
echo "<tr><th>Variable</th><th>Value</th></tr>";
echo "<tr><td>user_name</td><td>" . ($_SESSION['user_name'] ?? 'NOT SET') . "</td></tr>";
echo "<tr><td>user_branch</td><td>" . ($_SESSION['user_branch'] ?? 'NOT SET') . "</td></tr>";
echo "<tr><td>system_level</td><td>" . ($_SESSION['system_level'] ?? 'NOT SET') . "</td></tr>";
echo "</table>";

// Final summary
echo "<h2>📊 Test Summary</h2>";
if ($all_ok) {
    echo "<div class='success'>";
    echo "<h3>✓ All Critical Tests Passed!</h3>";
    echo "<p><strong>System is ready to use.</strong></p>";
    echo "<p>Next steps:</p>";
    echo "<ul>";
    echo "<li>Go to <a href='returntosupplier.php'>Return to Supplier</a> to create an RTS</li>";
    echo "<li>Test the SAVE button</li>";
    echo "<li>Check <a href='approval-returntosupplier.php'>RTS Approval</a> page</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>⚠ Some Issues Found</h3>";
    echo "<p>Please fix the errors above before using the system.</p>";
    echo "<p>Quick fix: Run <a href='setup_rts_tables.php'>setup_rts_tables.php</a></p>";
    echo "</div>";
}

echo "</body></html>";
$conn->close();
?>
