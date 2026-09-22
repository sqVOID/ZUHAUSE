<?php
/**
 * ZUHAUSE MANAGEMENT SYSTEM - COMPLETE DATA RESET SCRIPT
 * 
 * ⚠️ DANGER: This script will DELETE ALL DATA from EVERY TABLE
 * in the database except SUPERADMIN accounts!
 * 
 * This action is IRREVERSIBLE. Make sure you have a backup before running!
 * 
 * To use:
 * 1. Make a database backup first!
 * 2. Uncomment the line below: define('CONFIRM_RESET', true);
 * 3. Run this script in your browser
 * 4. Comment out the CONFIRM_RESET line again after use
 */

// SAFETY LOCK - Uncomment the line below to enable the reset
// define('CONFIRM_RESET', true);

if (!defined('CONFIRM_RESET') || CONFIRM_RESET !== true) {
    die('<!DOCTYPE html>
<html>
<head>
    <title>ZUHAUSE Data Reset - LOCKED</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #ffebee; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 700px; margin: 0 auto; border: 3px solid #d32f2f; }
        h1 { color: #d32f2f; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
        .warning { background: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>⚠️ SAFETY LOCK ENABLED</h1>
    <p>To use this script, you must:</p>
    <ol>
        <li><strong>Make a complete database backup</strong></li>
        <li>Edit this file (<code>clear_all_data.php</code>)</li>
        <li>Uncomment line 17: <code>define(\'CONFIRM_RESET\', true);</code></li>
        <li>Save the file and refresh this page</li>
    </ol>
    <div class="warning">
        <strong>⚠️ WARNING:</strong> This will DELETE ALL DATA except SUPERADMIN accounts!<br><br>
        This includes:<br>
        • All stock on hand<br>
        • All purchase orders and allocations<br>
        • All sales entries<br>
        • All items and inventory<br>
        • All branches, suppliers, dealers<br>
        • All user accounts (except SUPERADMIN)<br>
        • All brands, family codes, groups<br>
        • ALL OTHER DATA
    </div>
</div>
</body>
</html>');
}

require_once 'config.php';

// Start output
echo "<!DOCTYPE html>
<html>
<head>
    <title>ZUHAUSE Complete Data Reset</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 900px; margin: 0 auto; }
        h1 { color: #d32f2f; }
        h2 { color: #333; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
        .success { color: #4caf50; }
        .error { color: #f44336; }
        .warning { color: #ff9800; font-weight: bold; }
        .info { color: #2196f3; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; font-size: 13px; }
        th { background-color: #f44336; color: white; position: sticky; top: 0; }
        tr:hover { background-color: #f5f5f5; }
        .summary-box { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #2196f3; }
    </style>
</head>
<body>
<div class='container'>
<h1>🗑️ ZUHAUSE COMPLETE Data Reset</h1>
<p class='warning'>⚠️ Starting COMPLETE data deletion process...</p>";

// Disable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
echo "<p class='info'>✓ Foreign key checks disabled</p>";

$results = [];

// ========================================================================
// STEP 1: Identify and preserve SUPERADMIN accounts
// ========================================================================
echo "<h2>📋 Step 1: Identifying SUPERADMIN Accounts</h2>";

$superadmins = [];
$checkSuperAdmin = $conn->query("SELECT id, username, first_name, last_name, position FROM accounts WHERE LOWER(position) LIKE '%superadmin%' OR position = 'Superadmin' OR position = 'SUPERADMIN'");

if ($checkSuperAdmin && $checkSuperAdmin->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Position</th></tr>";
    while ($admin = $checkSuperAdmin->fetch_assoc()) {
        $superadmins[] = $admin['id'];
        echo "<tr>";
        echo "<td>{$admin['id']}</td>";
        echo "<td>{$admin['username']}</td>";
        echo "<td>{$admin['first_name']} {$admin['last_name']}</td>";
        echo "<td>{$admin['position']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p class='success'>✓ Found " . count($superadmins) . " SUPERADMIN account(s) - these will be preserved</p>";
} else {
    echo "<p class='error'>⚠️ WARNING: No SUPERADMIN accounts found! ALL accounts will be deleted!</p>";
}

// ========================================================================
// STEP 2: Get ALL tables from the database dynamically
// ========================================================================
echo "<h2>� Step 2: Discovering All Tables in Database</h2>";

$allTables = [];
$tablesResult = $conn->query("SHOW TABLES");

if ($tablesResult) {
    while ($row = $tablesResult->fetch_array()) {
        $allTables[] = $row[0];
    }
    echo "<p class='info'>✓ Found " . count($allTables) . " tables in database</p>";
    echo "<p style='font-size: 12px; color: #666;'>Tables: " . implode(', ', $allTables) . "</p>";
} else {
    echo "<p class='error'>✗ Error discovering tables: " . $conn->error . "</p>";
    die("</div></body></html>");
}

// ========================================================================
// STEP 3: Clear ALL tables
// ========================================================================
echo "<h2>🗑️ Step 3: Clearing ALL Tables</h2>";
echo "<table>";
echo "<tr><th>#</th><th>Table Name</th><th>Rows Before</th><th>Status</th></tr>";

$tableNumber = 1;
$totalRowsDeleted = 0;

foreach ($allTables as $table) {
    // Count rows before deletion
    $countResult = $conn->query("SELECT COUNT(*) as count FROM `$table`");
    $rowCount = 0;
    if ($countResult) {
        $countRow = $countResult->fetch_assoc();
        $rowCount = $countRow['count'];
    }
    
    // Special handling for accounts table - preserve SUPERADMIN
    if ($table === 'accounts') {
        if (count($superadmins) > 0) {
            $superadminIds = implode(',', array_map('intval', $superadmins));
            $result = $conn->query("DELETE FROM `$table` WHERE id NOT IN ($superadminIds)");
            $actualDeleted = $conn->affected_rows;
        } else {
            $result = $conn->query("TRUNCATE TABLE `$table`");
            $actualDeleted = $rowCount;
        }
    } 
    // Try TRUNCATE first (faster), fallback to DELETE if it fails
    else {
        $result = $conn->query("TRUNCATE TABLE `$table`");
        if (!$result) {
            // TRUNCATE failed, try DELETE
            $result = $conn->query("DELETE FROM `$table`");
        }
        $actualDeleted = $rowCount;
    }
    
    if ($result) {
        echo "<tr>";
        echo "<td>$tableNumber</td>";
        echo "<td><strong>$table</strong></td>";
        echo "<td>$rowCount</td>";
        
        if ($table === 'accounts' && count($superadmins) > 0) {
            echo "<td><span class='success'>✓ Cleared (kept " . count($superadmins) . " SUPERADMIN)</span></td>";
        } else {
            echo "<td><span class='success'>✓ Cleared</span></td>";
        }
        echo "</tr>";
        
        $results[$table] = ['success' => true, 'rows' => $actualDeleted];
        $totalRowsDeleted += $actualDeleted;
    } else {
        echo "<tr>";
        echo "<td>$tableNumber</td>";
        echo "<td><strong>$table</strong></td>";
        echo "<td>$rowCount</td>";
        echo "<td><span class='error'>✗ Error: " . $conn->error . "</span></td>";
        echo "</tr>";
        $results[$table] = ['success' => false, 'error' => $conn->error];
    }
    
    $tableNumber++;
}

echo "</table>";

// ========================================================================
// STEP 4: Reset auto-increment values
// ========================================================================
echo "<h2>🔄 Step 4: Resetting Auto-Increment Values</h2>";

$resetCount = 0;
foreach ($allTables as $table) {
    // For accounts, preserve the ID sequence after SUPERADMIN
    if ($table === 'accounts' && count($superadmins) > 0) {
        $maxId = $conn->query("SELECT MAX(id) as max_id FROM `$table`");
        if ($maxId) {
            $row = $maxId->fetch_assoc();
            $nextId = ($row['max_id'] ?? 0) + 1;
            $conn->query("ALTER TABLE `$table` AUTO_INCREMENT = $nextId");
            $resetCount++;
        }
    } else {
        // Reset to 1 for all other tables
        $result = $conn->query("ALTER TABLE `$table` AUTO_INCREMENT = 1");
        if ($result) {
            $resetCount++;
        }
    }
}
echo "<p class='success'>✓ Reset auto-increment for $resetCount tables</p>";

// ========================================================================
// STEP 5: Re-enable foreign key checks
// ========================================================================
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
echo "<p class='info'>✓ Foreign key checks re-enabled</p>";

// ========================================================================
// SUMMARY
// ========================================================================
echo "<h2>📊 Final Summary</h2>";

$totalSuccess = count(array_filter($results, function($r) { return $r['success']; }));
$totalFailed = count($results) - $totalSuccess;

echo "<div class='summary-box'>";
echo "<h3 style='margin-top: 0;'>Execution Results</h3>";
echo "<table style='background: white;'>";
echo "<tr><td><strong>Total tables found:</strong></td><td>" . count($allTables) . "</td></tr>";
echo "<tr><td><strong>Successfully cleared:</strong></td><td><span class='success'>$totalSuccess</span></td></tr>";
echo "<tr><td><strong>Failed:</strong></td><td><span class='error'>$totalFailed</span></td></tr>";
echo "<tr><td><strong>Total rows deleted:</strong></td><td><strong>" . number_format($totalRowsDeleted) . "</strong></td></tr>";
echo "<tr><td><strong>SUPERADMIN accounts preserved:</strong></td><td><span class='success'>" . count($superadmins) . "</span></td></tr>";
echo "</table>";
echo "</div>";

if ($totalFailed > 0) {
    echo "<div style='background: #ffebee; padding: 15px; border-radius: 5px; border-left: 4px solid #f44336; margin: 15px 0;'>";
    echo "<h4 style='margin-top: 0; color: #d32f2f;'>⚠️ Some Tables Failed to Clear:</h4>";
    echo "<ul>";
    foreach ($results as $table => $result) {
        if (!$result['success']) {
            echo "<li><strong>$table</strong>: " . $result['error'] . "</li>";
        }
    }
    echo "</ul>";
    echo "</div>";
}

echo "<h2>✅ Data Reset Complete!</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
echo "<p class='warning' style='margin: 0;'>⚠️ IMPORTANT: Remember to comment out line 17 in this script (CONFIRM_RESET) to prevent accidental re-runs!</p>";
echo "</div>";

echo "<p style='text-align: center; margin-top: 30px;'>";
echo "<a href='accountregistration.php' style='display: inline-block; background: #2196f3; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go to Login Page</a>";
echo "</p>";

echo "</div>
</body>
</html>";

$conn->close();
?>
