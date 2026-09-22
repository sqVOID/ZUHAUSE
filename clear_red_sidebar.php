<?php
require_once 'session_check.php';
require_once 'config.php';

echo "<h2>Clearing Red Account Sidebar Restrictions</h2>";

// Clear the sidebar_access for Red account
$result = $conn->query("UPDATE accounts SET sidebar_access = '' WHERE username = 'Red'");

if ($result) {
    echo "<p style='color: green; font-weight: bold;'>✓ Successfully cleared sidebar_access for Red account!</p>";
    
    // Verify
    $verify = $conn->query("SELECT username, sidebar_source, sidebar_access FROM accounts WHERE username = 'Red'");
    if ($verify && $verify->num_rows > 0) {
        $data = $verify->fetch_assoc();
        echo "<h3>Verification:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Username</th><td>" . $data['username'] . "</td></tr>";
        echo "<tr><th>Sidebar Source</th><td>" . $data['sidebar_source'] . "</td></tr>";
        echo "<tr><th>Sidebar Access</th><td>" . ($data['sidebar_access'] ?: '<strong style="color: green;">EMPTY (Full Access!)</strong>') . "</td></tr>";
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
}

echo "<br><h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>Logout</strong> from Red account</li>";
echo "<li><strong>Login again</strong> as Red</li>";
echo "<li><strong>Full sidebar should now appear!</strong></li>";
echo "</ol>";

echo "<p><a href='logout.php' style='padding: 10px 20px; background: #d32f2f; color: white; text-decoration: none; border-radius: 4px; display: inline-block; margin-right: 10px;'>Logout Now</a>";
echo "<a href='login.php' style='padding: 10px 20px; background: #2e7d32; color: white; text-decoration: none; border-radius: 4px; display: inline-block;'>Go to Login</a></p>";

$conn->close();
?>