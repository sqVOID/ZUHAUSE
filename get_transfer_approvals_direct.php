<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

// Get filter parameters
$date_from = isset($_POST['date_from']) ? $conn->real_escape_string($_POST['date_from']) : '';
$date_to = isset($_POST['date_to']) ? $conn->real_escape_string($_POST['date_to']) : '';
$status = isset($_POST['status']) ? $conn->real_escape_string($_POST['status']) : '';
$branch_from = isset($_POST['branch_from']) ? $conn->real_escape_string($_POST['branch_from']) : '';
$branch_to = isset($_POST['branch_to']) ? $conn->real_escape_string($_POST['branch_to']) : '';

// Get user's branch information
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$is_super_admin = (strcasecmp($system_level, 'Super-Admin') === 0);

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

// Filter by user's branch unless Super-Admin
if (!$is_super_admin && !empty($user_branch)) {
    $user_branch_escaped = $conn->real_escape_string($user_branch);
    
    // Get branch code
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

// Add branch filters for admins
if (!empty($branch_from) && $branch_from !== 'ALL') {
    $sql .= " AND st.branch_from = '$branch_from'";
}

if (!empty($branch_to) && $branch_to !== 'ALL') {
    $branch_to_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '$branch_to' LIMIT 1");
    if ($branch_to_query && $branch_to_query->num_rows > 0) {
        $branch_to_data = $branch_to_query->fetch_assoc();
        $branch_to_name = $conn->real_escape_string($branch_to_data['branch_name']);
        $sql .= " AND st.branch_to = '$branch_to_name'";
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

$result = $conn->query($sql);

$transfers = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transfers[] = $row;
    }
}

$conn->close();

echo json_encode($transfers);
?>
