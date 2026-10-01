<?php
require_once 'session_check.php';
include 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Check if replacements table exists and has required columns
    $check_table = $conn->query("SHOW TABLES LIKE 'replacements'");
    if ($check_table->num_rows === 0) {
        throw new Exception('Replacements table does not exist. Please create it first.');
    }

    // Check for required columns
    $required_columns = ['id', 'replacement_no', 'invoice_no', 'new_invoice_no', 'branch', 'branch_code', 'reason', 'remarks', 'total_amount', 'created_by', 'created_at', 'status', 'approved_by', 'disapproved_by', 'disapproval_reason'];
    $missing_columns = [];
    
    foreach ($required_columns as $col) {
        $result = $conn->query("SHOW COLUMNS FROM replacements LIKE '$col'");
        if ($result->num_rows === 0) {
            $missing_columns[] = $col;
        }
    }
    
    if (!empty($missing_columns)) {
        throw new Exception('Missing columns in replacements table: ' . implode(', ', $missing_columns));
    }

    $date_from = $_POST['date_from'] ?? date('Y-m-d');
    $date_to = $_POST['date_to'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? '';
    $branch = $_POST['branch'] ?? '';

    // Get user permissions
    $system_level = $_SESSION['system_level'] ?? '';
    $user_branch = $_SESSION['user_branch'] ?? '';

    // Build query
    $sql = "SELECT 
                r.id,
                DATE_FORMAT(r.created_at, '%m/%d/%Y') as date,
                r.replacement_no,
                r.invoice_no as original_invoice_no,
                COALESCE(r.new_invoice_no, '-') as new_invoice_no,
                r.branch,
                r.reason,
                r.remarks,
                COALESCE(FORMAT(r.total_amount, 2), '0.00') as total_amount,
                r.created_by,
                r.status,
                r.approved_by as approver,
                r.disapproved_by as disapprover,
                r.disapproval_reason
            FROM replacements r
            WHERE DATE(r.created_at) BETWEEN ? AND ?";

    $params = [$date_from, $date_to];
    $types = "ss";

    // Filter by status
    if ($status && $status !== 'all') {
        $sql .= " AND r.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    // Filter by branch based on user level
    if ($system_level === 'Super-Admin') {
        // Super-Admin can see all branches or filter by specific branch
        if ($branch && $branch !== 'ALL') {
            $sql .= " AND r.branch = ?";
            $params[] = $branch;
            $types .= "s";
        }
    } elseif ($system_level === 'Sub-admin') {
        // Sub-admin can only see their assigned branches
        if ($branch && $branch !== 'ALL') {
            $sql .= " AND r.branch = ?";
            $params[] = $branch;
            $types .= "s";
        } else {
            // Filter by user's assigned branches
            $user_branches = array_map('trim', explode(',', $user_branch));
            $placeholders = implode(',', array_fill(0, count($user_branches), '?'));
            $sql .= " AND r.branch IN ($placeholders)";
            foreach ($user_branches as $ub) {
                $params[] = $ub;
                $types .= "s";
            }
        }
    } else {
        // Regular users can only see their branch
        $sql .= " AND r.branch = ?";
        $params[] = $user_branch;
        $types .= "s";
    }

    $sql .= " ORDER BY r.created_at DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters dynamically
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }

    $stmt->close();
    $conn->close();

    echo json_encode($entries);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
