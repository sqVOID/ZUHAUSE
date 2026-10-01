<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

try {
    // Get parameters
    $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : date('Y-m-d');
    $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : date('Y-m-d');
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $branch_filter = isset($_POST['branch']) ? $_POST['branch'] : '';
    
    // Get user info
    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
    $is_super_admin = ($system_level === 'Super-Admin');
    $is_sub_admin = ($system_level === 'Sub-admin');
    
    // Handle multiple branches for Sub-admin
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $user_branches = [];
    if (!empty($user_branch) && !$is_super_admin) {
        $user_branches = array_map('trim', explode(',', $user_branch));
    }
    
    // Build WHERE clause
    $where_conditions = [];
    
    // Add date filter
    $where_conditions[] = "DATE(u.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
    $where_conditions[] = "DATE(u.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
    
    // Add status filter
    if ($status && $status !== 'all') {
        $where_conditions[] = "ual.status = '" . $conn->real_escape_string($status) . "'";
    }
    
    // Add branch filter based on user level
    if (!$is_super_admin && !$is_sub_admin) {
        // Regular user - only their branch(es)
        if (!empty($user_branches)) {
            $branch_names_quoted = array_map(function($name) use ($conn) {
                return "'" . $conn->real_escape_string($name) . "'";
            }, $user_branches);
            $branch_in_clause = implode(', ', $branch_names_quoted);
            $where_conditions[] = "u.branch IN ($branch_in_clause)";
        } else {
            $where_conditions[] = "1=0"; // No valid branches
        }
    } else if ($is_sub_admin) {
        // Sub-admin - their assigned branches
        if (!empty($user_branches)) {
            $branch_names_quoted = array_map(function($name) use ($conn) {
                return "'" . $conn->real_escape_string($name) . "'";
            }, $user_branches);
            $branch_in_clause = implode(', ', $branch_names_quoted);
            $where_conditions[] = "u.branch IN ($branch_in_clause)";
        } else {
            $where_conditions[] = "1=0"; // No valid branches
        }
        
        // If specific branch selected, further filter
        if ($branch_filter && $branch_filter !== 'ALL') {
            $where_conditions[] = "u.branch = '" . $conn->real_escape_string($branch_filter) . "'";
        }
    } else if ($is_super_admin && $branch_filter && $branch_filter !== 'ALL') {
        // Super-Admin with specific branch selected
        $where_conditions[] = "u.branch = '" . $conn->real_escape_string($branch_filter) . "'";
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Fetch upgrade approval data
    // Left join with upgrade_approval_log to get approval status
    // If no approval log exists, default to Pending
    $query = "SELECT 
                u.id as upgrade_id,
                u.upgrade_no,
                u.original_invoice_no,
                u.new_invoice_no,
                u.reason,
                u.remarks,
                u.total_amount,
                u.created_by,
                u.branch,
                u.created_at,
                COALESCE(ual.id, 0) as approval_log_id,
                COALESCE(ual.status, 'Pending') as status,
                ual.approver,
                ual.approval_date,
                ual.disapprover,
                ual.disapproval_date,
                b.branch_code
              FROM upgrades u
              LEFT JOIN branches b ON u.branch = b.branch_name
              LEFT JOIN upgrade_approval_log ual ON u.id = ual.upgrade_id
              $where_clause
              ORDER BY u.created_at DESC, u.id DESC";
    
    $result = $conn->query($query);
    
    $records = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $approval_log_id = intval($row['approval_log_id']);
            
            // If no approval log exists, create one with Pending status
            if ($approval_log_id === 0) {
                $insert_log = $conn->prepare("INSERT INTO upgrade_approval_log 
                    (upgrade_id, upgrade_no, original_invoice_no, new_invoice_no, branch, branch_code, reason, remarks, total_amount, created_by, created_at, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                
                $insert_log->bind_param(
                    "isssssssdss",
                    $row['upgrade_id'],
                    $row['upgrade_no'],
                    $row['original_invoice_no'],
                    $row['new_invoice_no'],
                    $row['branch'],
                    $row['branch_code'],
                    $row['reason'],
                    $row['remarks'],
                    $row['total_amount'],
                    $row['created_by'],
                    $row['created_at']
                );
                
                if ($insert_log->execute()) {
                    $approval_log_id = $conn->insert_id;
                }
                $insert_log->close();
            }
            
            $u_date = !empty($row['created_at']) ? date('m/d/Y', strtotime($row['created_at'])) : '';
            $u_upgrade_no = htmlspecialchars($row['upgrade_no']);
            $u_original_invoice = htmlspecialchars($row['original_invoice_no']);
            $u_new_invoice = htmlspecialchars($row['new_invoice_no'] ?? '');
            $u_reason = htmlspecialchars($row['reason'] ?? 'N/A');
            
            $b_name = $row['branch'] ? $row['branch'] : 'Unknown';
            $b_code = $row['branch_code'] ? $row['branch_code'] : 'UNK';
            $u_branch = htmlspecialchars($b_code . ' - ' . $b_name);
            
            $u_total = number_format(floatval($row['total_amount'] ?? 0), 2);
            $u_created_by = htmlspecialchars($row['created_by'] ?? 'Unknown');
            
            $status_value = htmlspecialchars($row['status'] ?? 'Pending');
            $approver = htmlspecialchars($row['approver'] ?? '');
            $disapprover = htmlspecialchars($row['disapprover'] ?? '');
            
            $records[] = [
                'id' => $approval_log_id,
                'date' => $u_date,
                'upgrade_no' => $u_upgrade_no,
                'original_invoice_no' => $u_original_invoice,
                'new_invoice_no' => $u_new_invoice,
                'branch' => $u_branch,
                'reason' => $u_reason,
                'total_amount' => $u_total,
                'created_by' => $u_created_by,
                'status' => $status_value,
                'approver' => $approver,
                'disapprover' => $disapprover
            ];
        }
    }
    
    echo json_encode($records);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'An error occurred while fetching data: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
