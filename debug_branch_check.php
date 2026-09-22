<?php
require_once 'session_check.php';
session_start();
include 'config.php';

// Get the session branch
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

echo "<h2>Debug Branch Comparison</h2>";
echo "<p><strong>Session Branch:</strong> '" . htmlspecialchars($user_branch) . "'</p>";
echo "<p><strong>Session Branch (uppercase):</strong> '" . htmlspecialchars(strtoupper($user_branch)) . "'</p>";

// Check the transfer
$imei = isset($_GET['imei']) ? $conn->real_escape_string(trim($_GET['imei'])) : '';

if (!empty($imei)) {
    echo "<p><strong>Checking IMEI:</strong> $imei</p>";
    
    $check_transfer = "SELECT st.st_number, st.status, st.branch_to, st.branch_from
                       FROM stock_transfer_items sti
                       JOIN stock_transfers st ON sti.st_number = st.st_number
                       WHERE sti.imei = '$imei'
                         AND st.status IN ('Approved', 'Received')
                       LIMIT 1";
    $result_transfer = $conn->query($check_transfer);
    
    if ($result_transfer && $result_transfer->num_rows > 0) {
        $transfer_row = $result_transfer->fetch_assoc();
        
        echo "<h3>Transfer Found:</h3>";
        echo "<p><strong>Transfer Number:</strong> " . htmlspecialchars($transfer_row['st_number']) . "</p>";
        echo "<p><strong>Status:</strong> " . htmlspecialchars($transfer_row['status']) . "</p>";
        echo "<p><strong>From:</strong> " . htmlspecialchars($transfer_row['branch_from']) . "</p>";
        echo "<p><strong>To:</strong> '" . htmlspecialchars($transfer_row['branch_to']) . "'</p>";
        echo "<p><strong>To (uppercase):</strong> '" . htmlspecialchars(strtoupper(trim($transfer_row['branch_to']))) . "'</p>";
        
        $branch_to_normalized = strtoupper(trim($transfer_row['branch_to']));
        $user_branch_normalized = strtoupper(trim($user_branch));
        
        echo "<h3>Comparison Results:</h3>";
        echo "<p><strong>Exact match:</strong> " . ($branch_to_normalized === $user_branch_normalized ? 'YES' : 'NO') . "</p>";
        echo "<p><strong>Branch_to contains user_branch:</strong> " . (strpos($branch_to_normalized, $user_branch_normalized) !== false ? 'YES' : 'NO') . "</p>";
        echo "<p><strong>User_branch contains branch_to:</strong> " . (strpos($user_branch_normalized, $branch_to_normalized) !== false ? 'YES' : 'NO') . "</p>";
        
        $is_received_by_user_branch = (
            $transfer_row['status'] === 'Received' && 
            ($branch_to_normalized === $user_branch_normalized || 
             strpos($branch_to_normalized, $user_branch_normalized) !== false ||
             strpos($user_branch_normalized, $branch_to_normalized) !== false)
        );
        
        echo "<p><strong>FINAL RESULT - Should Allow:</strong> " . ($is_received_by_user_branch ? 'YES ✓' : 'NO ✗') . "</p>";
    } else {
        echo "<p>No transfer found for this IMEI</p>";
    }
} else {
    echo "<p>Add ?imei=YOUR_SERIAL_NUMBER to the URL to test</p>";
}
?>
