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
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
    $is_admin = ($system_level === 'Super-Admin' || $system_level === 'Sub-admin');
    $is_super_admin = ($system_level === 'Super-Admin');
    
    // Handle multiple branches for Sub-admin
    $branch_codes = [];
    if (isset($_SESSION['user_branch']) && !$is_super_admin) {
        $user_branch = $_SESSION['user_branch'];
        
        // Handle multiple branches (comma-separated)
        $branch_names = array_map('trim', explode(',', $user_branch));
        
        foreach ($branch_names as $branch_name) {
            $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($branch_name) . "' LIMIT 1");
            if ($branch_query && $branch_query->num_rows > 0) {
                $branch_data = $branch_query->fetch_assoc();
                $branch_codes[] = $branch_data['branch_code'];
            }
        }
    }
    
    // Build WHERE clause
    $where_conditions = [];
    
    // Add date filter
    $where_conditions[] = "DATE(r.refund_date) >= '$date_from'";
    $where_conditions[] = "DATE(r.refund_date) <= '$date_to'";
    
    // Add branch filter based on user level
    if (!$is_admin) {
        // Regular user - only their branch(es)
        if (!empty($branch_codes)) {
            $branch_codes_quoted = array_map(function($code) use ($conn) {
                return "'" . $conn->real_escape_string($code) . "'";
            }, $branch_codes);
            $branch_in_clause = implode(', ', $branch_codes_quoted);
            $where_conditions[] = "r.branch_code IN ($branch_in_clause)";
        } else {
            $where_conditions[] = "1=0"; // No valid branches, return empty
        }
    } else if ($branch_filter && $branch_filter !== 'ALL') {
        // Admin with specific branch selected
        $where_conditions[] = "r.branch_code = '" . $conn->real_escape_string($branch_filter) . "'";
    }
    // If Admin with 'ALL' branches, no branch filter needed
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Fetch refund data
    $query = "SELECT r.id, r.refund_date, r.invoice_no, r.customer_name, r.total_qty, r.total_amount, 
                     r.approved_by, r.encoder, r.branch_code, b.branch_name 
              FROM refunds r
              LEFT JOIN branches b ON r.branch_code = b.branch_code
              $where_clause
              ORDER BY r.refund_date DESC, r.id DESC";
    
    $result = $conn->query($query);
    
    $records = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $r_date = !empty($row['refund_date']) ? date('m/d/Y', strtotime($row['refund_date'])) : '';
            
            $b_name = $row['branch_name'] ? $row['branch_name'] : 'Unknown Branch';
            $b_code = $row['branch_code'] ? $row['branch_code'] : 'UNK';
            
            $records[] = [
                'date_refunded' => $r_date,
                'invoice_no' => htmlspecialchars($row['invoice_no']),
                'customer_name' => htmlspecialchars($row['customer_name'] ?: 'N/A'),
                'branch' => htmlspecialchars($b_name . ' - ' . $b_code),
                'total_qty' => number_format($row['total_qty']),
                'total_amount' => number_format($row['total_amount'], 2),
                'approved_by' => htmlspecialchars($row['approved_by'] ?: 'Unknown'),
                'processed_by' => htmlspecialchars($row['encoder'] ?: 'System')
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'refunds' => $records,
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
