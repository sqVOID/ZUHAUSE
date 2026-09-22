<?php
// Quick test page to verify sidebar source functionality
require_once 'session_check.php';
include 'config.php';

$test_username = isset($_GET['username']) ? $_GET['username'] : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Sidebar Source</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .test-box { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #2e7d32; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        .position { background: #fff3e0; padding: 5px 10px; border-radius: 4px; display: inline-block; }
        .account { background: #e3f2fd; padding: 5px 10px; border-radius: 4px; display: inline-block; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { padding: 8px; width: 300px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 10px 20px; background: #2e7d32; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #1b5e20; }
        .success { color: #2e7d32; font-weight: bold; }
        .error { color: #c62828; font-weight: bold; }
        .info { color: #1976D2; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Sidebar Source Test Tool</h1>
        
        <div class="test-box">
            <h2>Test a Specific User</h2>
            <form method="GET">
                <div class="form-group">
                    <label>Enter Username:</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($test_username); ?>" placeholder="e.g., Red, Win, Dia">
                    <button type="submit" class="btn">Test User</button>
                </div>
            </form>
        </div>

        <?php if (!empty($test_username)): ?>
        <div class="test-box">
            <h2>Test Results for: <?php echo htmlspecialchars($test_username); ?></h2>
            
            <?php
            $sql = "SELECT * FROM accounts WHERE username = '" . $conn->real_escape_string($test_username) . "'";
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                echo "<table>";
                echo "<tr><th>Field</th><th>Value</th></tr>";
                echo "<tr><td>Username</td><td>" . htmlspecialchars($user['username']) . "</td></tr>";
                echo "<tr><td>Name</td><td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td></tr>";
                echo "<tr><td>Position</td><td>" . htmlspecialchars($user['position']) . "</td></tr>";
                echo "<tr><td>System Level</td><td>" . htmlspecialchars($user['system_level']) . "</td></tr>";
                
                $sidebar_source = $user['sidebar_source'] ?? 'position';
                $source_label = ($sidebar_source === 'account') ? 'Per Account' : 'From Position';
                $source_class = ($sidebar_source === 'account') ? 'account' : 'position';
                
                echo "<tr><td><strong>Sidebar Source</strong></td><td><span class='" . $source_class . "'>" . $source_label . "</span></td></tr>";
                echo "<tr><td>Account sidebar_access (DB)</td><td>" . ($user['sidebar_access'] ?: '<em>EMPTY</em>') . "</td></tr>";
                echo "</table>";
                
                // Get position data
                $pos_sql = "SELECT sidebar_access FROM positions WHERE position_name = '" . $conn->real_escape_string($user['position']) . "'";
                $pos_result = $conn->query($pos_sql);
                
                echo "<h3>Position Settings</h3>";
                $pos_sidebar_access = null;
                $has_position = false;
                if ($pos_result && $pos_result->num_rows > 0) {
                    $pos = $pos_result->fetch_assoc();
                    $pos_sidebar_access = $pos['sidebar_access'];
                    $has_position = true;
                    echo "<p><strong>Position:</strong> " . htmlspecialchars($user['position']) . "</p>";
                    echo "<p><strong>Position sidebar_access:</strong> " . ($pos_sidebar_access ?: '<em>EMPTY</em>') . "</p>";
                } else {
                    echo "<p class='error'>⚠️ Position '" . htmlspecialchars($user['position']) . "' not found in positions table!</p>";
                }
                
                // Simulate login logic
                echo "<h3>🎯 What Would Happen On Login?</h3>";
                echo "<div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #2e7d32; margin: 10px 0;'>";
                
                if ($sidebar_source === 'account') {
                    echo "<p class='success'>✓ Using PER ACCOUNT source</p>";
                    $final_access = $user['sidebar_access'] ?? '';
                    echo "<p><strong>Session sidebar_access would be:</strong></p>";
                    echo "<pre>" . ($final_access ?: '(EMPTY - Full Access)') . "</pre>";
                    
                    if (empty($final_access)) {
                        echo "<p class='error'>⚠️ This account has NO restrictions configured in Sidebar Per Account!</p>";
                        echo "<p>To configure: Go to <strong>Sidebar Per Account</strong> → Find this user → Click <strong>Set Sidebar</strong></p>";
                    }
                } else {
                    echo "<p class='success'>✓ Using FROM POSITION source</p>";
                    if ($has_position) {
                        $final_access = $pos_sidebar_access ?? '';
                        echo "<p><strong>Session sidebar_access would be:</strong></p>";
                        echo "<pre>" . ($final_access ?: '(EMPTY - Full Access)') . "</pre>";
                        
                        if (empty($final_access)) {
                            echo "<p class='error'>⚠️ The position '" . htmlspecialchars($user['position']) . "' has NO restrictions configured!</p>";
                            echo "<p>To configure: Go to <strong>Position Registration</strong> → Edit this position → Set Sidebar</p>";
                        }
                    } else {
                        echo "<p class='error'>⚠️ Position not found - would default to EMPTY (Full Access)</p>";
                    }
                }
                
                echo "</div>";
                
                // Show what items would be hidden
                if (!empty($final_access)) {
                    $hidden = explode(',', $final_access);
                    $hidden = array_map('trim', $hidden);
                    echo "<h3>🚫 Menu Items That Would Be Hidden:</h3>";
                    echo "<ul>";
                    foreach ($hidden as $item) {
                        if (!empty($item)) {
                            echo "<li>" . htmlspecialchars($item) . "</li>";
                        }
                    }
                    echo "</ul>";
                } else {
                    echo "<h3>✅ All Menu Items Would Be Visible</h3>";
                    echo "<p>No restrictions - Full sidebar access</p>";
                }
                
            } else {
                echo "<p class='error'>User '" . htmlspecialchars($test_username) . "' not found!</p>";
            }
            ?>
        </div>
        <?php endif; ?>

        <div class="test-box">
            <h2>All Accounts Overview</h2>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Sidebar Source</th>
                        <th>Has Config?</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $all_sql = "SELECT username, first_name, last_name, position, sidebar_source, sidebar_access FROM accounts ORDER BY username";
                    $all_result = $conn->query($all_sql);
                    
                    if ($all_result && $all_result->num_rows > 0) {
                        while ($row = $all_result->fetch_assoc()) {
                            $source = $row['sidebar_source'] ?? 'position';
                            $source_label = ($source === 'account') ? 'Per Account' : 'From Position';
                            $source_class = ($source === 'account') ? 'account' : 'position';
                            
                            $has_config = 'N/A';
                            if ($source === 'account') {
                                $has_config = !empty($row['sidebar_access']) ? '✓ Yes' : '✗ No';
                            } else {
                                // Check position
                                $p_sql = "SELECT sidebar_access FROM positions WHERE position_name = '" . $conn->real_escape_string($row['position']) . "'";
                                $p_res = $conn->query($p_sql);
                                if ($p_res && $p_res->num_rows > 0) {
                                    $p = $p_res->fetch_assoc();
                                    $has_config = !empty($p['sidebar_access']) ? '✓ Yes' : '✗ No';
                                } else {
                                    $has_config = '⚠️ Position not found';
                                }
                            }
                            
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['position']) . "</td>";
                            echo "<td><span class='" . $source_class . "'>" . $source_label . "</span></td>";
                            echo "<td>" . $has_config . "</td>";
                            echo "<td><a href='?username=" . urlencode($row['username']) . "' class='btn' style='padding: 5px 10px; font-size: 12px;'>Test</a></td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="test-box">
            <h2>Quick Links</h2>
            <a href="accountregistration.php" class="btn">Account Registration</a>
            <a href="sidebarperacc.php" class="btn" style="background: #1976D2;">Sidebar Per Account</a>
            <a href="position.php" class="btn" style="background: #f57c00;">Position Settings</a>
            <a href="debug_sidebar_source.php" class="btn" style="background: #7b1fa2;">Debug Current User</a>
        </div>
    </div>
</body>
</html>
