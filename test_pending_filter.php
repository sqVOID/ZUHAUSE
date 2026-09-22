<?php
require_once 'session_check.php';
include 'config.php';

// Simulate the POST request with "Pending" status
$_POST['status'] = 'Pending';
$_POST['date_from'] = '';
$_POST['date_to'] = '';

echo "<h2>Test Pending Filter</h2>";
echo "<p><strong>Testing with status = 'Pending'</strong></p>";
echo "<hr>";

// Now include and run the same logic
$date_from = isset($_POST['date_from']) ? $conn->real_escape_string($_POST['date_from']) : '';
$date_to = isset($_POST['date_to']) ? $conn->real_escape_string($_POST['date_to']) : '';
$status = isset($_POST['status']) ? $conn->real_escape_string($_POST['status']) : '';

$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

$is_super_admin = (strcasecmp($system_level, 'Super-Admin') === 0);

echo "<p>User Branch: " . htmlspecialchars($user_branch) . "</p>";
echo "<p>System Level: " . htmlspecialchars($system_level) . "</p>";
echo "<p>Status Filter: '" . htmlspecialchars($status) . "'</p>";
echo "<p>Is Super Admin: " . ($is_super_admin ? 'Yes' : 'No') . "</p>";

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

if (!$is_super_admin && !empty($user_branch)) {
    $user_branch_escaped = $conn->real_escape_string($user_branch);
    
    $branch_code_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch_escaped' LIMIT 1");
    $user_branch_code = '';
    if ($branch_code_query && $branch_code_query->num_rows > 0) {
        $branch_code_data = $branch_code_query->fetch_assoc();
        $user_branch_code = $branch_code_data['branch_code'];
    }
    
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

echo "<h3>BEFORE status filter:</h3>";
echo "<pre>" . htmlspecialchars($sql) . "</pre>";

if (!empty($status) && $status !== 'all') {
    $sql .= " AND st.status = '$status'";
    echo "<h3>AFTER adding status filter (status = '$status'):</h3>";
    echo "<pre>" . htmlspecialchars($sql) . "</pre>";
}

$sql .= " ORDER BY st.st_date DESC, st.st_number DESC";

echo "<h3>FINAL Query:</h3>";
echo "<pre>" . htmlspecialchars($sql) . "</pre>";
echo "<hr>";

$result = $conn->query($sql);

if ($result) {
    echo "<h3>Results Found: " . $result->num_rows . "</h3>";
    if ($result->num_rows > 0) {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>ST Number</th><th>Date</th><th>Status</th><th>Branch From</th><th>Branch To</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['st_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['date']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($row['status']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($row['branch_from']) . "</td>";
            echo "<td>" . htmlspecialchars($row['branch_to']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'><strong>NO RESULTS - But query executed successfully</strong></p>";
        echo "<p>This means the filter is working but no records match the criteria.</p>";
    }
} else {
    echo "<p style='color: red;'><strong>SQL Error:</strong> " . htmlspecialchars($conn->error) . "</p>";
}
?>
