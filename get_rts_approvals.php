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
    $where_conditions[] = "DATE(rts.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
    $where_conditions[] = "DATE(rts.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
    
    // Add status filter
    if ($status && $status !== 'all') {
        $where_conditions[] = "ral.status = '" . $conn->real_escape_string($status) . "'";
    }
    
    // Add branch filter based on user level
    if (!$is_super_admin && !$is_sub_admin) {
        // Regular user - only their branch(es)
        if (!empty($user_branches)) {
            $branch_names_quoted = array_map(function($name) use ($conn) {
                return "'" . $conn->real_escape_string($name) . "'";
            }, $user_branches);
            $branch_in_clause = implode(', ', $branch_names_quoted);
            $where_conditions[] = "rts.branch_from IN ($branch_in_clause)";
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
            $where_conditions[] = "rts.branch_from IN ($branch_in_clause)";
        } else {
            $where_conditions[] = "1=0"; // No valid branches
        }
        
        // If specific branch selected, further filter
        if ($branch_filter && $branch_filter !== 'ALL') {
            $where_conditions[] = "b.branch_name = '" . $conn->real_escape_string($branch_filter) . "'";
        }
    } else if ($is_super_admin && $branch_filter && $branch_filter !== 'ALL') {
        // Super-Admin with specific branch selected
        $where_conditions[] = "b.branch_name = '" . $conn->real_escape_string($branch_filter) . "'";
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Check if return_to_supplier table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'return_to_supplier'");
    if ($table_check->num_rows === 0) {
        // Table doesn't exist yet, return empty array
        echo json_encode([]);
        exit;
    }
    
    // Fetch RTS approval data
    $query = "SELECT 
                rts.id as rts_id,
                rts.rts_number,
                rts.reference_number,
                rts.branch_from,
                rts.delivery_to,
                rts.remarks,
                rts.created_by,
                rts.created_at,
                COALESCE(ral.id, 0) as approval_log_id,
                COALESCE(ral.status, 'Pending') as status,
                ral.approver,
                ral.approval_date,
                ral.disapprover,
                ral.disapproval_date,
                ral.total_items,
                b.branch_code
              FROM return_to_supplier rts
              LEFT JOIN branches b ON rts.branch_from = b.branch_code
              LEFT JOIN rts_approval_log ral ON rts.id = ral.rts_id
              $where_clause
              ORDER BY rts.created_at DESC, rts.id DESC";
    
    $result = $conn->query($query);
    
    $records = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $approval_log_id = intval($row['approval_log_id']);
            
            // Count items if approval log doesn't exist
            $total_items = 0;
            if ($approval_log_id === 0) {
                $count_query = $conn->prepare("SELECT COUNT(*) as count FROM return_to_supplier_items WHERE rts_id = ?");
                $count_query->bind_param("i", $row['rts_id']);
                $count_query->execute();
                $count_result = $count_query->get_result()->fetch_assoc();
                $total_items = intval($count_result['count']);
                $count_query->close();
            } else {
                $total_items = intval($row['total_items'] ?? 0);
            }
            
            // If no approval log exists, create one with Pending status
            if ($approval_log_id === 0) {
                $insert_log = $conn->prepare("INSERT INTO rts_approval_log 
                    (rts_id, rts_number, reference_number, branch_from, branch_code, delivery_to, remarks, total_items, created_by, created_at, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                
                $insert_log->bind_param(
                    "issssssisss",
                    $row['rts_id'],
                    $row['rts_number'],
                    $row['reference_number'],
                    $row['branch_from'],
                    $row['branch_code'],
                    $row['delivery_to'],
                    $row['remarks'],
                    $total_items,
                    $row['created_by'],
                    $row['created_at']
                );
                
                if ($insert_log->execute()) {
                    $approval_log_id = $conn->insert_id;
                }
                $insert_log->close();
            }
            
            $rts_date = !empty($row['created_at']) ? date('m/d/Y', strtotime($row['created_at'])) : '';
            $rts_number = htmlspecialchars($row['rts_number']);
            $reference_number = htmlspecialchars($row['reference_number'] ?? 'N/A');
            $delivery_to = htmlspecialchars($row['delivery_to'] ?? 'N/A');
            $remarks = htmlspecialchars($row['remarks'] ?? '');
            
            $b_code = $row['branch_from'] ? $row['branch_from'] : 'UNK';
            $b_name = $row['branch_code'] ? $row['branch_code'] : '';
            $rts_branch = htmlspecialchars($b_code);
            
            $rts_created_by = htmlspecialchars($row['created_by'] ?? 'Unknown');
            
            $status_value = htmlspecialchars($row['status'] ?? 'Pending');
            $approver = htmlspecialchars($row['approver'] ?? '');
            $disapprover = htmlspecialchars($row['disapprover'] ?? '');
            
            $records[] = [
                'id' => $approval_log_id,
                'date' => $rts_date,
                'rts_number' => $rts_number,
                'reference_number' => $reference_number,
                'branch_from' => $rts_branch,
                'delivery_to' => $delivery_to,
                'total_items' => $total_items,
                'remarks' => $remarks,
                'created_by' => $rts_created_by,
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
