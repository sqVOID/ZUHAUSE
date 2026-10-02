<?php
require_once 'session_check.php';
include 'config.php';

// Don't call session_start() if session is already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Get branch code from session
$branch_code = '000'; // Default
if (isset($_SESSION['user_branch'])) {
    $user_branch = $_SESSION['user_branch'];
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch'");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
}

// Generate next replacement number
$current_date = date('Y-m-d');
$year = date('y');
$month = date('m');
$day = date('d');

// Get the last replacement number for today
$replacement_query = $conn->query("
    SELECT replacement_no 
    FROM replacements 
    WHERE DATE(created_at) = '$current_date' 
    ORDER BY id DESC 
    LIMIT 1
");

if ($replacement_query && $replacement_query->num_rows > 0) {
    $last_replacement = $replacement_query->fetch_assoc()['replacement_no'];
    $sequence = intval(substr($last_replacement, -4)) + 1;
} else {
    $sequence = 1;
}

// Format: REP-YYMMDD-BBBB-NNNN
$replacement_no = sprintf("REP-%s%s%s-%s-%04d", $year, $month, $day, $branch_code, $sequence);

echo json_encode([
    'status' => 'success',
    'replacement_no' => $replacement_no
]);

$conn->close();
?>
