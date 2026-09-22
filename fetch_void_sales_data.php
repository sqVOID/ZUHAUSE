<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

try {
    // Get parameters
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
    $branch_filter = isset($_GET['branch']) ? $_GET['branch'] : '';
    
    // Get user info
    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
    $is_admin = ($system_level === 'Super-Admin');
    
    // Get user's branch code
    $branch_code = '000';
    if (isset($_SESSION['user_branch'])) {
        $user_branch = $_SESSION['user_branch'];
        $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch'");
        if ($branch_query && $branch_query->num_rows > 0) {
            $branch_data = $branch_query->fetch_assoc();
            $branch_code = $branch_data['branch_code'];
        }
    }
    
    // Build WHERE clause
    $where_conditions = ["se.status = 'voided'"];
    
    // Add date filter
    $where_conditions[] = "DATE(se.voided_at) >= '$date_from'";
    $where_conditions[] = "DATE(se.voided_at) <= '$date_to'";
    
    // Add branch filter
    if (!$is_admin) {
        // Regular user - only their branch
        $where_conditions[] = "se.branch_code = '$branch_code'";
    } else if ($branch_filter && $branch_filter !== 'ALL') {
        // Admin with specific branch selected
        $where_conditions[] = "se.branch_code = '" . $conn->real_escape_string($branch_filter) . "'";
    }
    // If Admin with 'ALL' branches, no branch filter needed
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Fetch voided sales data
    $query = "SELECT se.id, se.voided_at, se.created_at, se.invoice_no, se.first_name, se.last_name, 
                     se.void_reason, se.voided_by, se.branch_code, b.branch_name 
              FROM sales_entry se
              LEFT JOIN branches b ON se.branch_code = b.branch_code
              $where_clause
              ORDER BY se.voided_at DESC, se.id DESC";
    
    $result = $conn->query($query);
    
    $records = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $v_date = !empty($row['voided_at']) ? date('m/d/Y', strtotime($row['voided_at'])) : '';
            $v_date_sold = !empty($row['created_at']) && $row['created_at'] != '1970-01-01 00:00:00' ? date('m/d/Y', strtotime($row['created_at'])) : '';
            
            $customer_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $v_customer = $customer_name ?: 'N/A';
            
            $b_name = $row['branch_name'] ? $row['branch_name'] : 'Unknown Branch';
            $b_code = $row['branch_code'] ? $row['branch_code'] : 'UNK';
            
            $records[] = [
                'voided_date' => $v_date,
                'date_sold' => $v_date_sold,
                'invoice_no' => htmlspecialchars($row['invoice_no']),
                'customer_name' => htmlspecialchars($v_customer),
                'branch' => htmlspecialchars($b_name . ' - ' . $b_code),
                'void_reason' => htmlspecialchars($row['void_reason'] ?? 'No reason provided'),
                'voided_by' => htmlspecialchars($row['voided_by'] ?? 'Unknown')
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'records' => $records,
        'count' => count($records)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching data.'
    ]);
}

$conn->close();
?>
