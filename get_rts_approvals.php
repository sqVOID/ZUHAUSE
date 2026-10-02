<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

try {
    // Get parameters
    $date_from = isset($_POST['date_from']) ? trim($_POST['date_from']) : date('Y-m-d');
    $date_to = isset($_POST['date_to']) ? trim($_POST['date_to']) : date('Y-m-d');
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $branch_filter = isset($_POST['branch']) ? trim($_POST['branch']) : '';
    
    // Get user info
    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
    $is_super_admin = (strcasecmp($system_level, 'Super-Admin') === 0);
    $is_sub_admin = (strcasecmp($system_level, 'Sub-admin') === 0);
    
    // Session branch is usually branch NAME(s), comma-separated
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $user_branch_names = [];
    if (!empty($user_branch) && !$is_super_admin) {
        $user_branch_names = array_filter(array_map('trim', explode(',', $user_branch)));
    }
    
    // Resolve branch names -> branch codes (rts.branch_from stores CODE)
    $resolveBranchCodes = function(array $names) use ($conn) {
        $codes = [];
        foreach ($names as $name) {
            if ($name === '') continue;
            $esc = $conn->real_escape_string($name);
            // Match by name or code so either form works
            $q = $conn->query("SELECT branch_code FROM branches 
                WHERE branch_name = '$esc' OR branch_code = '$esc' LIMIT 1");
            if ($q && $q->num_rows > 0) {
                $codes[] = $q->fetch_assoc()['branch_code'];
            } else {
                // Keep raw value as fallback
                $codes[] = $name;
            }
        }
        return array_values(array_unique($codes));
    };
    
    // Build WHERE clause
    $where_conditions = [];
    
    // Date filter (created_at)
    if (!empty($date_from)) {
        $where_conditions[] = "DATE(rts.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
    }
    if (!empty($date_to)) {
        $where_conditions[] = "DATE(rts.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
    }
    
    // Status filter (All Status = 'all' or empty after select)
    if ($status !== '' && strcasecmp($status, 'all') !== 0) {
        $where_conditions[] = "COALESCE(ral.status, 'Pending') = '" . $conn->real_escape_string($status) . "'";
    }
    
    // Branch filter based on user level
    // rts.branch_from is branch_code (e.g. ZUHINFA)
    if (!$is_super_admin) {
        // Regular user / Sub-admin: restrict to their assigned branch(es)
        if (!empty($user_branch_names)) {
            $codes = $resolveBranchCodes($user_branch_names);
            if (!empty($codes)) {
                $quoted = array_map(function ($c) use ($conn) {
                    return "'" . $conn->real_escape_string($c) . "'";
                }, $codes);
                $where_conditions[] = "rts.branch_from IN (" . implode(', ', $quoted) . ")";
            } else {
                $where_conditions[] = "1=0";
            }
        } else {
            $where_conditions[] = "1=0";
        }
    }
    
    // Optional branch dropdown (Super-Admin / Sub-admin UI)
    if (($is_super_admin || $is_sub_admin) && $branch_filter !== '' && strcasecmp($branch_filter, 'ALL') !== 0) {
        $codes = $resolveBranchCodes([$branch_filter]);
        if (!empty($codes)) {
            $code = $conn->real_escape_string($codes[0]);
            $where_conditions[] = "rts.branch_from = '$code'";
        }
    }
    
    $where_clause = count($where_conditions) > 0
        ? ('WHERE ' . implode(' AND ', $where_conditions))
        : '';
    
    // Check if return_to_supplier table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'return_to_supplier'");
    if ($table_check->num_rows === 0) {
        echo json_encode([]);
        exit;
    }
    
    // Fetch RTS approval data
    $query = "SELECT 
                rts.id as rts_id,
                rts.rts_number,
                rts.reference_number,
                rts.branch_from,
                rts.branch_name,
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
                b.branch_code,
                b.branch_name as branch_name_lookup
              FROM return_to_supplier rts
              LEFT JOIN branches b ON rts.branch_from = b.branch_code
              LEFT JOIN rts_approval_log ral ON rts.id = ral.rts_id
              $where_clause
              ORDER BY rts.created_at DESC, rts.id DESC";
    
    $result = $conn->query($query);
    
    if ($result === false) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $approval_log_id = intval($row['approval_log_id']);
        
        // Count items if approval log doesn't have total_items
        $total_items = intval($row['total_items'] ?? 0);
        if ($approval_log_id === 0 || $total_items === 0) {
            $count_query = $conn->prepare("SELECT COUNT(*) as count FROM return_to_supplier_items WHERE rts_id = ?");
            $count_query->bind_param("i", $row['rts_id']);
            $count_query->execute();
            $count_result = $count_query->get_result()->fetch_assoc();
            $total_items = intval($count_result['count']);
            $count_query->close();
        }
        
        // If no approval log exists, create one with Pending status
        if ($approval_log_id === 0) {
            $branch_display = $row['branch_name'] ?: ($row['branch_name_lookup'] ?: $row['branch_from']);
            $branch_code_val = $row['branch_code'] ?: $row['branch_from'];
            
            $insert_log = $conn->prepare("INSERT INTO rts_approval_log 
                (rts_id, rts_number, reference_number, branch_from, branch_code, delivery_to, remarks, total_items, created_by, created_at, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            
            $insert_log->bind_param(
                "issssssiss",
                $row['rts_id'],
                $row['rts_number'],
                $row['reference_number'],
                $branch_display,
                $branch_code_val,
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
        
        // Show "CODE - NAME" like other approval pages
        $b_code = $row['branch_from'] ?: '';
        $b_name = $row['branch_name'] ?: ($row['branch_name_lookup'] ?: '');
        $rts_branch = $b_name !== '' ? ($b_code . ' - ' . $b_name) : $b_code;
        
        $records[] = [
            'id' => $approval_log_id,
            'date' => $rts_date,
            'rts_number' => htmlspecialchars($row['rts_number']),
            'reference_number' => htmlspecialchars($row['reference_number'] ?? 'N/A'),
            'branch_from' => htmlspecialchars($rts_branch),
            'delivery_to' => htmlspecialchars($row['delivery_to'] ?? 'N/A'),
            'total_items' => $total_items,
            'remarks' => htmlspecialchars($row['remarks'] ?? ''),
            'created_by' => htmlspecialchars($row['created_by'] ?? 'Unknown'),
            'status' => htmlspecialchars($row['status'] ?? 'Pending'),
            'approver' => htmlspecialchars($row['approver'] ?? ''),
            'disapprover' => htmlspecialchars($row['disapprover'] ?? '')
        ];
    }
    
    echo json_encode($records);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'An error occurred while fetching data: ' . $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>
