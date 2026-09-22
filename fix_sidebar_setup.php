<?php
require_once 'session_check.php';
require_once 'config.php';

echo "<h2>Fixing Sidebar Setup</h2>";

// Step 1: Ensure sidebar_source column exists
echo "<h3>Step 1: Adding sidebar_source column...</h3>";
$result1 = $conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS sidebar_source VARCHAR(20) DEFAULT 'position'");
if ($result1) {
    echo "✓ sidebar_source column added/verified<br>";
} else {
    echo "✗ Error: " . $conn->error . "<br>";
}

// Step 2: Ensure sidebar_access column exists
echo "<h3>Step 2: Adding sidebar_access column...</h3>";
$result2 = $conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS sidebar_access TEXT DEFAULT NULL");
if ($result2) {
    echo "✓ sidebar_access column added/verified<br>";
} else {
    echo "✗ Error: " . $conn->error . "<br>";
}

// Step 3: Show current Red account data
echo "<h3>Step 3: Current Red Account Data:</h3>";
$red_account = $conn->query("SELECT id, username, first_name, last_name, system_level, sidebar_source, sidebar_access FROM accounts WHERE username = 'Red'");
if ($red_account && $red_account->num_rows > 0) {
    $red = $red_account->fetch_assoc();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>" . $red['id'] . "</td></tr>";
    echo "<tr><td>Username</td><td>" . $red['username'] . "</td></tr>";
    echo "<tr><td>Name</td><td>" . $red['first_name'] . " " . $red['last_name'] . "</td></tr>";
    echo "<tr><td>System Level</td><td>" . $red['system_level'] . "</td></tr>";
    echo "<tr><td>Sidebar Source</td><td>" . ($red['sidebar_source'] ?? 'NULL') . "</td></tr>";
    echo "<tr><td>Sidebar Access</td><td>" . ($red['sidebar_access'] ?: 'EMPTY/NULL') . "</td></tr>";
    echo "</table><br>";
    
    // Step 4: Fix Red account if needed
    echo "<h3>Step 4: Fixing Red Account...</h3>";
    $red_id = $red['id'];
    
    // Update to account source with empty sidebar_access
    $fix_query = "UPDATE accounts SET sidebar_source = 'account', sidebar_access = '' WHERE id = $red_id";
    if ($conn->query($fix_query)) {
        echo "✓ Red account updated to:<br>";
        echo "  - sidebar_source = 'account'<br>";
        echo "  - sidebar_access = '' (empty = full access)<br>";
    } else {
        echo "✗ Error updating: " . $conn->error . "<br>";
    }
    
    // Verify the update
    echo "<h3>Step 5: Verification - Red Account After Fix:</h3>";
    $verify = $conn->query("SELECT sidebar_source, sidebar_access FROM accounts WHERE id = $red_id");
    if ($verify && $verify->num_rows > 0) {
        $v = $verify->fetch_assoc();
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>Sidebar Source</td><td><strong>" . $v['sidebar_source'] . "</strong></td></tr>";
        echo "<tr><td>Sidebar Access</td><td><strong>" . ($v['sidebar_access'] ?: 'EMPTY (full access)') . "</strong></td></tr>";
        echo "</table><br>";
    }
} else {
    echo "✗ Red account not found!<br>";
}

echo "<h3>Done!</h3>";
echo "<p><a href='login.php'>Go to Login</a> | <a href='accountregistration.php'>Go to Account Registration</a></p>";

$conn->close();
?>