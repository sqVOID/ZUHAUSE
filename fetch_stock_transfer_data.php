<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

try {
    // Get parameters
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
    $branch_from_filter = isset($_GET['branch_from']) ? $_GET['branch_from'] : '';
    $branch_to_filter = isset($_GET['branch_to']) ? $_GET['branch_to'] : '';
    
    // Get user info
    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
    $is_admin = ($system_level === 'Super-Admin');
    
    // Get user's branches
    $user_branches = [];
    $branch_codes = [];
    if (isset($_SESSION['user_branch']) && !$is_admin) {
        $user_branch = $_SESSION['user_branch'];
        
        // Handle multiple branches (comma-separated)
        $user_branches = array_map('trim', explode(',', $user_branch));
        
        foreach ($user_branches as $branch_name) {
            $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($branch_name) . "' LIMIT 1");
            if ($branch_query && $branch_query->num_rows > 0) {
                $branch_data = $branch_query->fetch_assoc();
                $branch_codes[] = $branch_data['branch_code'];
            }
        }
    }
    
    // Build WHERE clause
    $where_clause = "WHERE st.status = 'Received'";
    
    // Add date filter
    $where_clause .= " AND st.st_date >= '$date_from'";
    $where_clause .= " AND st.st_date <= '$date_to'";
    
    // Add branch filter
    if (!$is_admin) {
        // Regular user - show transfers FROM or TO any of the user's branches
        if (!empty($user_branches) && !empty($branch_codes)) {
            $branch_names_quoted = array_map(function($name) use ($conn) {
                return "'" . $conn->real_escape_string($name) . "'";
            }, $user_branches);
            $branch_codes_quoted = array_map(function($code) use ($conn) {
                return "'" . $conn->real_escape_string($code) . "'";
            }, $branch_codes);
            
            $branch_names_in = implode(', ', $branch_names_quoted);
            $branch_codes_in = implode(', ', $branch_codes_quoted);
            
            $where_clause .= " AND (st.branch_from IN ($branch_codes_in) OR st.branch_to IN ($branch_names_in) OR bf.branch_name IN ($branch_names_in))";
        } else {
            $where_clause .= " AND 1=0"; // No valid branches
        }
    }
    
    // Add specific branch filters if provided
    if ($branch_from_filter && $branch_from_filter !== 'ALL') {
        $where_clause .= " AND st.branch_from = '" . $conn->real_escape_string($branch_from_filter) . "'";
    }
    
    if ($branch_to_filter && $branch_to_filter !== 'ALL') {
        // branch_to is stored as branch name, need to convert filter code to name
        $to_branch_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '" . $conn->real_escape_string($branch_to_filter) . "' LIMIT 1");
        if ($to_branch_query && $to_branch_query->num_rows > 0) {
            $to_branch_data = $to_branch_query->fetch_assoc();
            $to_branch_name = $to_branch_data['branch_name'];
            $where_clause .= " AND st.branch_to = '" . $conn->real_escape_string($to_branch_name) . "'";
        }
    }
    
    // Fetch stock transfer data
    // JOIN with accounts to get full names for prepared_by and received_by
    // Hide names if user is Super-Admin (show blank/empty)
    $query = "SELECT st.st_number, st.st_date, 
                     CASE 
                         WHEN acc_prep.system_level = 'Super-Admin' THEN ''
                         ELSE COALESCE(CONCAT(acc_prep.first_name, ' ', acc_prep.last_name), st.prepared_by)
                     END as prepared_by,
                     CASE 
                         WHEN acc_recv.system_level = 'Super-Admin' THEN ''
                         ELSE COALESCE(CONCAT(acc_recv.first_name, ' ', acc_recv.last_name), st.received_by)
                     END as received_by,
                     st.disapproved_by, 
                     st.remarks, st.status, st.branch_from, st.branch_to,
                     bf.branch_name as from_branch_name,
                     bt.branch_code as to_branch_code
              FROM stock_transfers st
              LEFT JOIN branches bf ON bf.branch_code = st.branch_from
              LEFT JOIN branches bt ON bt.branch_name = st.branch_to
              LEFT JOIN accounts acc_prep ON st.prepared_by = acc_prep.username
              LEFT JOIN accounts acc_recv ON st.received_by = acc_recv.username
              $where_clause
              ORDER BY st.st_date DESC, st.st_number DESC";
    
    $result = $conn->query($query);
    
    $records = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $st_date = !empty($row['st_date']) ? date('m/d/Y', strtotime($row['st_date'])) : '';
            $st_no = htmlspecialchars($row['st_number']);
            
            $branch_from_display = htmlspecialchars($row['branch_from'] . ' - ' . ($row['from_branch_name'] ?? ''));
            $branch_to_display = htmlspecialchars(($row['to_branch_code'] ?? '') . ' - ' . $row['branch_to']);
            
            $prepared_by = htmlspecialchars($row['prepared_by']);
            $received_by = !empty($row['received_by']) ? htmlspecialchars($row['received_by']) : '-';
            $disapproved_by = !empty($row['disapproved_by']) ? htmlspecialchars($row['disapproved_by']) : '-';
            $status = htmlspecialchars($row['status']);
            
            $records[] = [
                'date' => $st_date,
                'st_number' => $st_no,
                'branch_from' => $branch_from_display,
                'branch_to' => $branch_to_display,
                'status' => $status,
                'prepared_by' => $prepared_by,
                'received_by' => $received_by,
                'disapproved_by' => $disapproved_by
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $records,
        'count' => count($records)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching data: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
