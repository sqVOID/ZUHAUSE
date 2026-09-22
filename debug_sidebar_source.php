<?php
require_once 'session_check.php';
include 'config.php';

echo "<!DOCTYPE html>";
echo "<html><head>";
echo "<title>Sidebar Source Debug</title>";
echo "<style>";
echo "body { font-family: Arial; padding: 20px; background: #f5f5f5; }";
echo ".box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }";
echo "h2 { color: #2e7d32; }";
echo "table { width: 100%; border-collapse: collapse; margin-top: 10px; }";
echo "td { padding: 10px; border-bottom: 1px solid #ddd; }";
echo "td:first-child { font-weight: bold; width: 30%; }";
echo ".success { color: #2e7d32; }";
echo ".error { color: #c62828; }";
echo ".info { color: #1976D2; }";
echo "</style>";
echo "</head><body>";

echo "<h1>🔍 Sidebar Source Debug Information</h1>";

// Session Info
echo "<div class='box'>";
echo "<h2>Current Session Data</h2>";
echo "<table>";
echo "<tr><td>User ID</td><td>" . ($_SESSION['user_id'] ?? 'NOT SET') . "</td></tr>";
echo "<tr><td>Username</td><td>" . ($_SESSION['username'] ?? 'NOT SET') . "</td></tr>";
echo "<tr><td>User Name</td><td>" . ($_SESSION['user_name'] ?? 'NOT SET') . "</td></tr>";
echo "<tr><td>Position</td><td>" . ($_SESSION['user_position'] ?? 'NOT SET') . "</td></tr>";
echo "<tr><td>System Level</td><td>" . ($_SESSION['system_level'] ?? 'NOT SET') . "</td></tr>";
echo "<tr><td><strong>Sidebar Source</strong></td><td class='info'><strong>" . ($_SESSION['sidebar_source'] ?? 'NOT SET IN SESSION') . "</strong></td></tr>";
echo "<tr><td><strong>Sidebar Access (Session)</strong></td><td>" . ($_SESSION['sidebar_access'] ?? 'EMPTY - Full Access') . "</td></tr>";
echo "</table>";
echo "</div>";

// Database Info
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT id, username, first_name, last_name, position, system_level, sidebar_source, sidebar_access FROM accounts WHERE id = '$user_id'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        echo "<div class='box'>";
        echo "<h2>Database Account Record</h2>";
        echo "<table>";
        echo "<tr><td>ID</td><td>" . $user['id'] . "</td></tr>";
        echo "<tr><td>Username</td><td>" . $user['username'] . "</td></tr>";
        echo "<tr><td>Name</td><td>" . $user['first_name'] . " " . $user['last_name'] . "</td></tr>";
        echo "<tr><td>Position</td><td>" . $user['position'] . "</td></tr>";
        echo "<tr><td>System Level</td><td>" . $user['system_level'] . "</td></tr>";
        echo "<tr><td><strong>Sidebar Source (DB)</strong></td><td class='info'><strong>" . ($user['sidebar_source'] ?? 'NULL') . "</strong></td></tr>";
        echo "<tr><td><strong>Sidebar Access (DB)</strong></td><td>" . ($user['sidebar_access'] ?? 'NULL') . "</td></tr>";
        echo "</table>";
        echo "</div>";
        
        // Position Info
        echo "<div class='box'>";
        echo "<h2>Position Settings (From position.php)</h2>";
        $pos = $conn->real_escape_string($user['position']);
        $p_sql = "SELECT * FROM positions WHERE position_name = '$pos'";
        $p_result = $conn->query($p_sql);
        
        if ($p_result && $p_result->num_rows > 0) {
            $p_row = $p_result->fetch_assoc();
            echo "<table>";
            echo "<tr><td>Position ID</td><td>" . $p_row['id'] . "</td></tr>";
            echo "<tr><td>Position Name</td><td>" . $p_row['position_name'] . "</td></tr>";
            echo "<tr><td><strong>Position Sidebar Access</strong></td><td>" . ($p_row['sidebar_access'] ?? 'EMPTY') . "</td></tr>";
            echo "</table>";
        } else {
            echo "<p class='error'>Position '" . htmlspecialchars($user['position']) . "' not found in positions table!</p>";
        }
        echo "</div>";
        
        // Analysis
        echo "<div class='box'>";
        echo "<h2>📊 Analysis</h2>";
        
        $sidebar_source = $user['sidebar_source'] ?? 'position';
        
        if ($sidebar_source === 'account') {
            echo "<p class='success'>✓ Sidebar Source is set to: <strong>Per Account</strong></p>";
            echo "<p>This account should use its own custom sidebar settings.</p>";
            
            if (empty($user['sidebar_access'])) {
                echo "<p class='error'>⚠️ WARNING: Account sidebar_access is EMPTY. This account has FULL access to all menu items.</p>";
                echo "<p>To restrict access, go to <strong>Sidebar Per Account</strong> page and configure this user's sidebar.</p>";
            } else {
                echo "<p class='success'>✓ Account has custom sidebar configuration.</p>";
                echo "<p><strong>Hidden menu items:</strong> " . $user['sidebar_access'] . "</p>";
            }
        } else {
            echo "<p class='success'>✓ Sidebar Source is set to: <strong>From Position</strong></p>";
            echo "<p>This account uses sidebar settings from the <strong>" . $user['position'] . "</strong> position.</p>";
            
            if (isset($p_row) && $p_row) {
                if (empty($p_row['sidebar_access'])) {
                    echo "<p class='error'>⚠️ The position '" . htmlspecialchars($user['position']) . "' has NO sidebar restrictions. This account has FULL access.</p>";
                } else {
                    echo "<p class='success'>✓ Position has sidebar configuration.</p>";
                    echo "<p><strong>Hidden menu items:</strong> " . htmlspecialchars($p_row['sidebar_access']) . "</p>";
                }
            }
        }
        
        echo "</div>";
        
        // What items are currently hidden
        if (isset($_SESSION['sidebar_access']) && !empty($_SESSION['sidebar_access'])) {
            $hidden_items = explode(',', $_SESSION['sidebar_access']);
            $hidden_items = array_map('trim', $hidden_items);
            
            echo "<div class='box'>";
            echo "<h2>🚫 Currently Hidden Menu Items</h2>";
            echo "<ul>";
            foreach ($hidden_items as $item) {
                if (!empty($item)) {
                    echo "<li>" . htmlspecialchars($item) . "</li>";
                }
            }
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div class='box'>";
            echo "<h2>✅ All Menu Items Visible</h2>";
            echo "<p>No menu items are hidden. User has full access to the sidebar.</p>";
            echo "</div>";
        }
    }
}

echo "<div class='box'>";
echo "<a href='accountregistration.php' style='display: inline-block; padding: 10px 20px; background: #2e7d32; color: white; text-decoration: none; border-radius: 4px;'>← Back to Account Registration</a> ";
echo "<a href='sidebarperacc.php' style='display: inline-block; padding: 10px 20px; background: #1976D2; color: white; text-decoration: none; border-radius: 4px;'>Sidebar Per Account</a> ";
echo "<a href='position.php' style='display: inline-block; padding: 10px 20px; background: #f57c00; color: white; text-decoration: none; border-radius: 4px;'>Position Settings</a>";
echo "</div>";

echo "</body></html>";
?>
