<?php
require_once 'session_check.php';
include 'config.php';

// Simulate the POST request with "All Status"
$_POST['status'] = 'all';
$_POST['date_from'] = '';
$_POST['date_to'] = '';
$_POST['branch_from'] = '';
$_POST['branch_to'] = '';

// Get filter parameters
$date_from = isset($_POST['date_from']) ? $conn->real_escape_string($_POST['date_from']) : '';
$date_to = isset($_POST['date_to']) ? $conn->real_escape_string($_POST['date_to']) : '';
$status = isset($_POST['status']) ? $conn->real_escape_string($_POST['status']) : '';
$branch_from = isset($_POST['branch_from']) ? $conn->real_escape_string($_POST['branch_from']) : '';
$branch_to = isset($_POST['branch_to']) ? $conn->real_escape_string($_POST['branch_to']) : '';

// Get user's branch information for filtering
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

// Check if user is Super-Admin (only Super-Admin has access to ALL branches without filtering)
$is_super_admin = (strcasecmp($system_level, 'Super-Admin') === 0);
$is_sub_admin = (strcasecmp($system_level, 'Sub-admin') === 0);

// Build query
$sql = "SELECT 
            st.st_number, 
            st.st_date as date, 
            CONCAT(st.branch_from, ' - ', COALESCE(bf.branch_name, '')) as branch_from,
            CONCAT(COALESCE(bt.branch_code, ''), ' - ', st.branch_to) as branch_to,
            st.prepared_by, 
            st.approver, 
            st.received_by,
            st.status, 
            st.remarks 
        FROM stock_transfers st
        LEFT JOIN branches bf ON bf.branch_code = st.branch_from
        LEFT JOIN branches bt ON bt.branch_name = st.branch_to
        WHERE st.status != 'Received'";

// Filter by user's branch unless they are Super-Admin
if (!$is_super_admin && !empty($user_branch)) {
    $user_branch_escaped = $conn->real_escape_string($user_branch);
    
    // Get the branch code for the user's branch name
    $branch_code_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch_escaped' LIMIT 1");
    $user_branch_code = '';
    if ($branch_code_query && $branch_code_query->num_rows > 0) {
        $branch_code_data = $branch_code_query->fetch_assoc();
        $user_branch_code = $branch_code_data['branch_code'];
    }
    
    // Show transfers where branch_from or branch_to matches
    if (!empty($user_branch_code)) {
        $sql .= " AND (st.branch_from = '$user_branch_code' OR st.branch_from = '$user_branch_escaped' OR st.branch_to = '$user_branch_escaped')";
    } else {
        $sql .= " AND (st.branch_from = '$user_branch_escaped' OR st.branch_to = '$user_branch_escaped')";
    }
}

if (!empty($date_from)) {
    $sql .= " AND st.st_date >= '$date_from'";
}

if (!empty($date_to)) {
    $sql .= " AND st.st_date <= '$date_to'";
}

if (!empty($status) && $status !== 'all') {
    $sql .= " AND st.status = '$status'";
}

$sql .= " ORDER BY st.st_date DESC, st.st_number DESC";

echo "<h2>Debug Get Transfers</h2>";
echo "<p><strong>User Branch:</strong> " . htmlspecialchars($user_branch) . "</p>";
echo "<p><strong>System Level:</strong> " . htmlspecialchars($system_level) . "</p>";
echo "<p><strong>Is Super Admin:</strong> " . ($is_super_admin ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Is Sub Admin:</strong> " . ($is_sub_admin ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Status Filter:</strong> " . htmlspecialchars($status) . "</p>";
echo "<hr>";
echo "<h3>Generated SQL Query:</h3>";
echo "<pre>" . htmlspecialchars($sql) . "</pre>";
echo "<hr>";

$result = $conn->query($sql);

if ($result) {
    echo "<h3>Results Found: " . $result->num_rows . "</h3>";
    if ($result->num_rows > 0) {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>ST Number</th><th>Date</th><th>Branch From</th><th>Branch To</th><th>Status</th><th>Prepared By</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['st_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['date']) . "</td>";
            echo "<td>" . htmlspecialchars($row['branch_from']) . "</td>";
            echo "<td>" . htmlspecialchars($row['branch_to']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($row['status']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($row['prepared_by']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'><strong>SQL Error:</strong> " . htmlspecialchars($conn->error) . "</p>";
}
?>
