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
    $stock_type = isset($_POST['stock_type']) ? $_POST['stock_type'] : '';
    $branch = isset($_POST['branch']) ? $_POST['branch'] : '';
    $imei = isset($_POST['imei']) ? $_POST['imei'] : '';
    
    // Get user information from session
    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
    $user_branch = isset($_SESSION['user_branch']) ? $_SESSION['user_branch'] : '';
    $is_super_admin = ($system_level === 'Super-Admin');
    $is_sub_admin = ($system_level === 'Sub-admin');
    
    // Build the WHERE clause - Always filter for Approved status only
    $where_conditions = ["l.status = 'Approved'"];
    $params = [];
    $types = '';
    
    // Date filters
    if (!empty($date_from)) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $date_from);
        if ($date_obj) {
            $formatted_date_from = $date_obj->format('m/d/Y');
            $where_conditions[] = "STR_TO_DATE(l.entry_date, '%m/%d/%Y') >= STR_TO_DATE(?, '%m/%d/%Y')";
            $params[] = $formatted_date_from;
            $types .= 's';
        }
    }
    
    if (!empty($date_to)) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $date_to);
        if ($date_obj) {
            $formatted_date_to = $date_obj->format('m/d/Y');
            $where_conditions[] = "STR_TO_DATE(l.entry_date, '%m/%d/%Y') <= STR_TO_DATE(?, '%m/%d/%Y')";
            $params[] = $formatted_date_to;
            $types .= 's';
        }
    }
    
    // Stock type filter
    if (!empty($stock_type) && $stock_type !== 'all') {
        $where_conditions[] = "l.stock_type = ?";
        $params[] = $stock_type;
        $types .= 's';
    }
    
    // IMEI filter
    if (!empty($imei)) {
        $where_conditions[] = "i.imei LIKE ?";
        $params[] = '%' . $imei . '%';
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
    
    // Build the query with JOIN to get individual items, approver details, and history
    $sql = "SELECT 
                l.id as log_id,
                l.entry_date,
                l.branch_code,
                l.branch_name,
                l.stock_type,
                l.remarks,
                l.created_by,
                l.approver,
                CONCAT(a.first_name, ' ', a.last_name) as approver_full_name,
                i.id as item_id,
                i.item_code,
                i.item_description,
                i.imei,
                i.quantity,
                h.previous_status
            FROM sales_entry_status_log l
            LEFT JOIN sales_entry_status_items i ON l.id = i.log_id
            LEFT JOIN accounts a ON l.approver COLLATE utf8mb4_unicode_ci = a.username COLLATE utf8mb4_unicode_ci
            LEFT JOIN stock_status_history h ON l.id = h.approval_log_id 
                AND (
                    (i.imei IS NOT NULL AND i.imei != '' AND h.imei COLLATE utf8mb4_unicode_ci = i.imei COLLATE utf8mb4_unicode_ci) 
                    OR 
                    ((i.imei IS NULL OR i.imei = '') AND h.item_code COLLATE utf8mb4_unicode_ci = i.item_code COLLATE utf8mb4_unicode_ci AND h.branch COLLATE utf8mb4_unicode_ci = l.branch_name COLLATE utf8mb4_unicode_ci AND (h.imei IS NULL OR h.imei = ''))
                )";
    
    if (count($where_conditions) > 0) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY l.approval_date DESC, l.id DESC, i.id ASC";
    
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
    
    // Fetch results and build report entries
    $entries = [];
    
    while ($row = $result->fetch_assoc()) {
        // For the report, we show each item as a separate row
        // FROM STATUS comes from stock_status_history.previous_status
        // TO STATUS is the stock_type from the approval log
        
        $entries[] = [
            'id' => $row['log_id'], // Use the actual log ID from sales_entry_status_log
            'date' => $row['entry_date'],
            'branch' => $row['branch_name'] ?? '',
            'item_code' => $row['item_code'] ?? '',
            'item_description' => $row['item_description'] ?? '',
            'imei' => $row['imei'] ?? '',
            'from_status' => $row['previous_status'] ?? 'N/A',
            'to_status' => $row['stock_type'] ?? '',
            'changed_by' => $row['created_by'] ?? '',
            'approver_by' => $row['approver_full_name'] ?? ($row['approver'] ?? ''),
            'remarks' => $row['remarks'] ?? ''
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
