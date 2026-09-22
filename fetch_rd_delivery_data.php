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
    $is_sub_admin = ($system_level === 'Sub-admin');
    
    // Get user's branch codes
    $branch_codes = [];
    if (isset($_SESSION['user_branch']) && !$is_admin) {
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
    
    // Filter for received allocations only
    $where_conditions[] = "poa.received_qty > 0";
    
    // Add date filter (use received_at from allocations table)
    $where_conditions[] = "DATE(poa.received_at) >= '$date_from'";
    $where_conditions[] = "DATE(poa.received_at) <= '$date_to'";
    
    // Add branch filter
    if (!$is_admin && !$is_sub_admin) {
        // Regular user - only their branch(es)
        if (!empty($branch_codes)) {
            $branch_codes_quoted = array_map(function($code) use ($conn) {
                return "'" . $conn->real_escape_string($code) . "'";
            }, $branch_codes);
            $branch_in_clause = implode(', ', $branch_codes_quoted);
            $where_conditions[] = "b.branch_code IN ($branch_in_clause)";
        } else {
            $where_conditions[] = "1=0"; // No valid branches
        }
    } else if ($is_sub_admin) {
        // Sub-admin - their assigned branches
        if (!empty($branch_codes)) {
            $branch_codes_quoted = array_map(function($code) use ($conn) {
                return "'" . $conn->real_escape_string($code) . "'";
            }, $branch_codes);
            $branch_in_clause = implode(', ', $branch_codes_quoted);
            $where_conditions[] = "b.branch_code IN ($branch_in_clause)";
        } else {
            $where_conditions[] = "1=0"; // No valid branches
        }
        
        // If specific branch selected, further filter
        if ($branch_filter && $branch_filter !== 'ALL') {
            $where_conditions[] = "b.branch_code = '" . $conn->real_escape_string($branch_filter) . "'";
        }
    } else if ($is_admin && $branch_filter && $branch_filter !== 'ALL') {
        // Super-Admin with specific branch selected
        $where_conditions[] = "b.branch_code = '" . $conn->real_escape_string($branch_filter) . "'";
    }
    // If Super-Admin with 'ALL' branches, no branch filter needed
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Fetch RD delivery data from purchase_order_allocations table
    // Show one summary row per PO delivery (grouped by PO ID, branch, and invoice number)
    $query = "SELECT MIN(poa.id) as id, MAX(poa.received_at) as `date`, po.po_number, poa.invoice_number, 
                     po.supplier_company as supplier, poa.branch_name, b.branch_code
              FROM purchase_order_allocations poa
              INNER JOIN purchase_orders po ON poa.po_id = po.id
              LEFT JOIN branches b ON poa.branch_name COLLATE utf8mb4_general_ci = b.branch_name COLLATE utf8mb4_general_ci
              $where_clause
              AND poa.invoice_number IS NOT NULL 
              AND poa.invoice_number != ''
              AND poa.received_qty > 0
              GROUP BY poa.po_id, poa.branch_name, poa.invoice_number
              ORDER BY MAX(poa.received_at) DESC, MIN(poa.id) DESC";
    
    $result = $conn->query($query);
    
    $records = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $r_date = !empty($row['date']) && $row['date'] != '1970-01-01' ? date('m/d/Y', strtotime($row['date'])) : '';
            $r_po = htmlspecialchars($row['po_number']);
            $r_inv = htmlspecialchars($row['invoice_number'] ?? '-');
            $r_sup = htmlspecialchars($row['supplier']);
            
            $b_name = $row['branch_name'] ? $row['branch_name'] : 'Unknown Branch';
            $b_code = $row['branch_code'] ? $row['branch_code'] : 'UNK';
            $r_rev = htmlspecialchars($b_name . ' - ' . $b_code);
            
            $records[] = [
                'id' => $row['id'],
                'date' => $r_date,
                'po_number' => $r_po,
                'invoice_number' => $r_inv,
                'supplier' => $r_sup,
                'received_from' => $r_rev
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
