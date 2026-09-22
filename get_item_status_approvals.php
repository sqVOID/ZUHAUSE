<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

try {
    // Get filter parameters
    $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : '';
    $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $stock_type = isset($_POST['stock_type']) ? $_POST['stock_type'] : '';
    $branch = isset($_POST['branch']) ? $_POST['branch'] : '';
    
    // Get user information from session
    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
    $user_branch = isset($_SESSION['user_branch']) ? $_SESSION['user_branch'] : '';
    $is_super_admin = ($system_level === 'Super-Admin');
    $is_sub_admin = ($system_level === 'Sub-admin');
    
    // Build the WHERE clause
    $where_conditions = [];
    $params = [];
    $types = '';
    
    // Date filters - convert from YYYY-MM-DD to MM/DD/YYYY format for comparison
    if (!empty($date_from)) {
        // Convert YYYY-MM-DD to MM/DD/YYYY
        $date_obj = DateTime::createFromFormat('Y-m-d', $date_from);
        if ($date_obj) {
            $formatted_date_from = $date_obj->format('m/d/Y');
            $where_conditions[] = "STR_TO_DATE(entry_date, '%m/%d/%Y') >= STR_TO_DATE(?, '%m/%d/%Y')";
            $params[] = $formatted_date_from;
            $types .= 's';
        }
    }
    
    if (!empty($date_to)) {
        // Convert YYYY-MM-DD to MM/DD/YYYY
        $date_obj = DateTime::createFromFormat('Y-m-d', $date_to);
        if ($date_obj) {
            $formatted_date_to = $date_obj->format('m/d/Y');
            $where_conditions[] = "STR_TO_DATE(entry_date, '%m/%d/%Y') <= STR_TO_DATE(?, '%m/%d/%Y')";
            $params[] = $formatted_date_to;
            $types .= 's';
        }
    }
    
    // Status filter
    if (!empty($status) && $status !== 'all') {
        $where_conditions[] = "l.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    // Stock type filter
    if (!empty($stock_type) && $stock_type !== 'all') {
        $where_conditions[] = "l.stock_type = ?";
        $params[] = $stock_type;
        $types .= 's';
    }
    
    // Branch filter
    if (!empty($branch) && $branch !== 'ALL') {
        $where_conditions[] = "l.branch_name = ?";
        $params[] = $branch;
        $types .= 's';
    } else if (!$is_super_admin && !$is_sub_admin) {
        // Regular users can only see their own branch
        if (!empty($user_branch)) {
            $where_conditions[] = "l.branch_name = ?";
            $params[] = $user_branch;
            $types .= 's';
        }
    } else if ($is_sub_admin && (empty($branch) || $branch === 'ALL')) {
        // Sub-admin can see multiple branches
        if (!empty($user_branch)) {
            $user_branch_names = array_map('trim', explode(',', $user_branch));
            if (count($user_branch_names) > 0) {
                $branch_placeholders = implode(',', array_fill(0, count($user_branch_names), '?'));
                $where_conditions[] = "l.branch_name IN ($branch_placeholders)";
                foreach ($user_branch_names as $bn) {
                    $params[] = $bn;
                    $types .= 's';
                }
            }
        }
    }
    
    // Build the final query with JOIN to get approver's full name
    $sql = "SELECT 
                l.id, l.entry_date, l.branch_code, l.branch_name, l.stock_type, l.remarks, 
                l.items_count, l.updated_count, l.created_by, l.created_at, 
                l.approver, l.approval_date, l.disapproved_by, l.status,
                CONCAT(a.first_name, ' ', a.last_name) as approver_full_name
            FROM sales_entry_status_log l
            LEFT JOIN accounts a ON l.approver COLLATE utf8mb4_unicode_ci = a.username COLLATE utf8mb4_unicode_ci";
    
    if (count($where_conditions) > 0) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY l.created_at DESC, l.id DESC";
    
    // Prepare and execute query
    if (count($params) > 0) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
    }
    
    // Fetch results
    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $entries[] = [
            'id' => $row['id'],
            'entry_date' => $row['entry_date'],
            'branch_code' => $row['branch_code'] ?? '',
            'branch_name' => $row['branch_name'] ?? '',
            'stock_type' => $row['stock_type'] ?? '',
            'remarks' => $row['remarks'] ?? '',
            'items_count' => intval($row['items_count'] ?? 0),
            'updated_count' => intval($row['updated_count'] ?? 0),
            'created_by' => $row['created_by'] ?? '',
            'created_at' => $row['created_at'] ?? '',
            'approver' => $row['approver_full_name'] ?? ($row['approver'] ?? ''),
            'approval_date' => $row['approval_date'] ?? '',
            'disapproved_by' => $row['disapproved_by'] ?? '',
            'status' => $row['status'] ?? 'Pending'
        ];
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    echo json_encode($entries);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
