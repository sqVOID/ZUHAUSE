<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

try {
    // Get parameters
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
    $branch_filter = isset($_GET['branch']) ? $_GET['branch'] : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Get user info
    $system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
    $is_admin = ($system_level === 'Super-Admin');
    $is_sub_admin = ($system_level === 'Sub-admin');
    
    // Handle multiple branches for Sub-admin
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $user_branches = [];
    if (!empty($user_branch) && !$is_admin) {
        $user_branches = array_map('trim', explode(',', $user_branch));
    }
    
    // Build WHERE clause
    $where_conditions = [];
    
    // Add date filter
    $where_conditions[] = "DATE(r.created_at) >= '$date_from'";
    $where_conditions[] = "DATE(r.created_at) <= '$date_to'";
    
    // Add status filter
    if (!empty($status_filter)) {
        $where_conditions[] = "COALESCE(r.status, 'Pending') = '" . $conn->real_escape_string($status_filter) . "'";
    }
    
    // Add branch filter
    if (!$is_admin && !$is_sub_admin) {
        // Regular user - only their branch(es)
        if (!empty($user_branches)) {
            $branch_names_quoted = array_map(function($name) use ($conn) {
                return "'" . $conn->real_escape_string($name) . "'";
            }, $user_branches);
            $branch_in_clause = implode(', ', $branch_names_quoted);
            $where_conditions[] = "r.branch IN ($branch_in_clause)";
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
            $where_conditions[] = "r.branch IN ($branch_in_clause)";
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
    
    // Fetch replacement data
    $query = "SELECT r.id, r.created_at, r.replacement_no, r.invoice_no, r.new_invoice_no, r.reason, 
                     r.remarks, r.created_by, r.branch, b.branch_code, r.less_amount, r.total_amount,
                     COALESCE(r.status, 'Pending') as status,
                     se.created_at as invoice_date
              FROM replacements r
              LEFT JOIN branches b ON r.branch = b.branch_name
              LEFT JOIN sales_entry se ON r.invoice_no = se.invoice_no
              $where_clause
              ORDER BY r.created_at DESC, r.id DESC";
    
    $result = $conn->query($query);
    
    $records = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $r_date = !empty($row['created_at']) ? date('m/d/Y', strtotime($row['created_at'])) : '';
            $date_sold = !empty($row['invoice_date']) ? date('m/d/Y', strtotime($row['invoice_date'])) : '-';
            $r_replacement_no = htmlspecialchars($row['replacement_no']);
            $r_invoice = htmlspecialchars($row['invoice_no']);
            $r_reason = htmlspecialchars($row['reason'] ?? 'N/A');
            $r_remarks = htmlspecialchars($row['remarks'] ?? '');
            
            $b_name = $row['branch'] ? $row['branch'] : 'Unknown Branch';
            $b_code = $row['branch_code'] ? $row['branch_code'] : 'UNK';
            $r_branch = htmlspecialchars($b_name . ' - ' . $b_code);
            
            $r_id = $row['id'];
            
            // Get old items (items being replaced)
            $old_items_query = $conn->prepare("SELECT item_description, imei, price FROM replacement_old_items WHERE replacement_id = ?");
            $old_items_query->bind_param("i", $r_id);
            $old_items_query->execute();
            $old_items_result = $old_items_query->get_result();
            
            $old_items_desc = [];
            $old_items_imei = [];
            while ($old_item = $old_items_result->fetch_assoc()) {
                $old_items_desc[] = htmlspecialchars($old_item['item_description']);
                $old_items_imei[] = htmlspecialchars($old_item['imei'] ?? 'N/A');
            }
            $old_items_query->close();
            $r_old_unit = !empty($old_items_desc) ? implode('<br>', $old_items_desc) : 'N/A';
            $r_old_imei = !empty($old_items_imei) ? implode('<br>', $old_items_imei) : 'N/A';
            
            // Get new items (replacement items)
            $new_items_query = $conn->prepare("SELECT item_description, imei, quantity FROM replacement_new_items WHERE replacement_id = ?");
            $new_items_query->bind_param("i", $r_id);
            $new_items_query->execute();
            $new_items_result = $new_items_query->get_result();
            
            $new_items_desc = [];
            $new_items_imei = [];
            $total_qty = 0;
            while ($new_item = $new_items_result->fetch_assoc()) {
                $new_items_desc[] = htmlspecialchars($new_item['item_description']);
                $new_items_imei[] = htmlspecialchars($new_item['imei'] ?? 'N/A');
                $total_qty += intval($new_item['quantity'] ?? 1);
            }
            $new_items_query->close();
            $r_new_unit = !empty($new_items_desc) ? implode('<br>', $new_items_desc) : 'N/A';
            $r_new_imei = !empty($new_items_imei) ? implode('<br>', $new_items_imei) : 'N/A';
            
            // Format amounts
            $less_amount = number_format($row['less_amount'] ?? 0, 2);
            $total_amount = number_format($row['total_amount'] ?? 0, 2);
            
            $records[] = [
                'date' => $r_date,
                'date_sold' => $date_sold,
                'replacement_no' => $r_replacement_no,
                'invoice_no' => $r_invoice,
                'old_unit' => $r_old_unit,
                'old_imei' => $r_old_imei,
                'new_unit' => $r_new_unit,
                'new_imei' => $r_new_imei,
                'qty' => $total_qty > 0 ? $total_qty : 1,
                'less_amount' => $less_amount,
                'total_amount' => $total_amount,
                'reason' => $r_reason,
                'remarks' => $r_remarks,
                'branch' => $r_branch,
                'status' => htmlspecialchars($row['status'] ?? 'Pending')
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
