<?php
/**
 * WARNING: This script will DELETE ALL DATA from the database EXCEPT accounts table
 * This action is IRREVERSIBLE and should only be run with proper authorization
 * 
 * Security measures:
 * - Requires login session
 * - Requires Super-Admin privileges
 * - Requires manual confirmation via POST
 * - Logs all deletion activities
 */

require_once 'session_check.php';
require_once 'config.php';

// Security Check: Must be logged in
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized: Login required');
}

// Security Check: Must be Super-Admin
$user_id = (int)$_SESSION['user_id'];
$check_admin = $conn->query("SELECT system_level FROM accounts WHERE id = $user_id LIMIT 1");
if (!$check_admin || $check_admin->num_rows === 0) {
    die('Unauthorized: User not found');
}

$user_data = $check_admin->fetch_assoc();
if (strcasecmp($user_data['system_level'], 'Super-Admin') !== 0) {
    die('Unauthorized: Super-Admin access required');
}

// Get all tables in the database
$tables_query = $conn->query("SHOW TABLES");
$all_tables = [];
while ($row = $tables_query->fetch_array()) {
    $all_tables[] = $row[0];
}

// Tables to PRESERVE (do not clear)
$preserved_tables = [
    'accounts',
    'users',
    'positions',
    'branches',
    'areas',
    'departments',
    'groups',
    'active_sessions'
];

// Tables to CLEAR
$tables_to_clear = array_diff($all_tables, $preserved_tables);

// Handle POST request (actual deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'DELETE_ALL_DATA') {
    
    $deletion_log = [];
    $errors = [];
    
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        foreach ($tables_to_clear as $table) {
            $result = $conn->query("TRUNCATE TABLE `$table`");
            if ($result) {
                $deletion_log[] = "✓ Cleared table: $table";
            } else {
                $errors[] = "✗ Failed to clear table: $table - " . $conn->error;
            }
        }
        
        // If any errors, rollback
        if (count($errors) > 0) {
            $conn->rollback();
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            
            echo "<!DOCTYPE html><html><head><title>Deletion Failed</title>";
            echo "<style>body{font-family:Arial;padding:20px;} .error{color:red;} .success{color:green;}</style></head><body>";
            echo "<h2 class='error'>❌ Data Deletion FAILED - Rolled Back</h2>";
            echo "<h3>Errors:</h3><ul>";
            foreach ($errors as $error) {
                echo "<li class='error'>$error</li>";
            }
            echo "</ul>";
            echo "<p><a href='clear_all_data_except_accounts.php'>Go Back</a></p>";
            echo "</body></html>";
            exit;
        }
        
        // Commit transaction
        $conn->commit();
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        // Log the deletion action
        $username = $_SESSION['username'] ?? 'Unknown';
        $log_message = "User '$username' (ID: $user_id) cleared all database tables except accounts at " . date('Y-m-d H:i:s');
        error_log($log_message);
        
        // Display success message
        echo "<!DOCTYPE html><html><head><title>Deletion Successful</title>";
        echo "<style>body{font-family:Arial;padding:20px;} .error{color:red;} .success{color:green;}</style></head><body>";
        echo "<h2 class='success'>✅ Data Deletion Completed Successfully</h2>";
        echo "<p>All data has been cleared except for preserved tables.</p>";
        echo "<h3>Preserved Tables (NOT cleared):</h3><ul>";
        foreach ($preserved_tables as $table) {
            if (in_array($table, $all_tables)) {
                echo "<li class='success'>✓ $table</li>";
            }
        }
        echo "</ul>";
        echo "<h3>Cleared Tables (" . count($deletion_log) . " tables):</h3><ul>";
        foreach ($deletion_log as $log) {
            echo "<li class='success'>$log</li>";
        }
        echo "</ul>";
        echo "<p><strong>Performed by:</strong> $username (ID: $user_id)</p>";
        echo "<p><strong>Date/Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
        echo "<p><a href='dashboard.php'>Go to Dashboard</a></p>";
        echo "</body></html>";
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        die("Database Error: " . $e->getMessage());
    }
}

// Display confirmation form
?>
<!DOCTYPE html>
<html>
<head>
    <title>Clear All Data Except Accounts</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }
        .warning {
            background-color: #ff4444;
            color: white;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info {
            background-color: #f0f0f0;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .preserved {
            color: green;
            font-weight: bold;
        }
        .cleared {
            color: red;
        }
        .confirm-box {
            background-color: #fff3cd;
            padding: 20px;
            border: 2px solid #ff9800;
            border-radius: 5px;
            margin: 20px 0;
        }
        input[type="text"] {
            padding: 10px;
            font-size: 16px;
            width: 300px;
        }
        button {
            padding: 15px 30px;
            font-size: 16px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
        }
        .btn-danger {
            background-color: #ff4444;
            color: white;
        }
        .btn-danger:hover {
            background-color: #cc0000;
        }
        .btn-cancel {
            background-color: #666;
            color: white;
            margin-left: 10px;
        }
        .btn-cancel:hover {
            background-color: #444;
        }
        ul {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
        }
    </style>
</head>
<body>
    <h1>⚠️ Clear All Data Except Accounts</h1>
    
    <div class="warning">
        <h2>🚨 CRITICAL WARNING 🚨</h2>
        <p><strong>This action will PERMANENTLY DELETE ALL DATA from the database except for preserved tables.</strong></p>
        <p>This action is <strong>IRREVERSIBLE</strong> and cannot be undone!</p>
        <p>Make sure you have a backup before proceeding.</p>
    </div>

    <div class="info">
        <h3>📋 Current User Information</h3>
        <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?></p>
        <p><strong>User ID:</strong> <?php echo $user_id; ?></p>
        <p><strong>System Level:</strong> <?php echo htmlspecialchars($user_data['system_level']); ?></p>
        <p><strong>Current Date/Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>

    <div class="info">
        <h3 class="preserved">✓ Tables That Will Be PRESERVED (NOT cleared):</h3>
        <ul>
            <?php foreach ($preserved_tables as $table): ?>
                <?php if (in_array($table, $all_tables)): ?>
                    <li class="preserved">✓ <?php echo htmlspecialchars($table); ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="info">
        <h3 class="cleared">✗ Tables That Will Be CLEARED (<?php echo count($tables_to_clear); ?> tables):</h3>
        <ul>
            <?php foreach ($tables_to_clear as $table): ?>
                <li class="cleared">✗ <?php echo htmlspecialchars($table); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="confirm-box">
        <h3>🔐 Confirmation Required</h3>
        <p>To proceed with this dangerous operation, type <strong>DELETE_ALL_DATA</strong> in the box below and click the button.</p>
        
        <form method="POST" id="deleteForm" onsubmit="return confirmDeletion()">
            <input type="text" name="confirm" id="confirmInput" placeholder="Type DELETE_ALL_DATA here" required>
            <br><br>
            <button type="submit" class="btn-danger">🗑️ DELETE ALL DATA (Except Accounts)</button>
            <button type="button" class="btn-cancel" onclick="window.location.href='dashboard.php'">Cancel</button>
        </form>
    </div>

    <script>
        function confirmDeletion() {
            const input = document.getElementById('confirmInput').value;
            if (input !== 'DELETE_ALL_DATA') {
                alert('Incorrect confirmation text. Please type exactly: DELETE_ALL_DATA');
                return false;
            }
            
            return confirm(
                '⚠️ FINAL WARNING ⚠️\n\n' +
                'You are about to DELETE ALL DATA from <?php echo count($tables_to_clear); ?> tables.\n\n' +
                'This action CANNOT be undone!\n\n' +
                'Are you absolutely sure you want to continue?'
            );
        }
    </script>
</body>
</html>
