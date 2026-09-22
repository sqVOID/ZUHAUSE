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
    
    // Handle multiple branches for Sub-admin
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $user_branches = [];
    if (!empty($user_branch) && !$is_admin) {
        $user_branches = array_map('trim', explode(',', $user_branch));
    }
    
    // Build WHERE clause
    $where_conditions = [];
    
    // Add date filter
    $where_conditions[] = "DATE(u.created_at) >= '$date_from'";
    $where_conditions[] = "DATE(u.created_at) <= '$date_to'";
    
    // Add branch filter
    if (!$is_admin && !$is_sub_admin) {
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
            $where_conditions[] = "b.branch_code = '" . $conn->real_escape_string($branch_filter) . "'";
        }
    } else if ($is_admin && $branch_filter && $branch_filter !== 'ALL') {
        // Super-Admin with specific branch selected
        $where_conditions[] = "b.branch_code = '" . $conn->real_escape_string($branch_filter) . "'";
    }
    // If Super-Admin with 'ALL' branches, no branch filter needed
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Fetch upgrade data
    $query = "SELECT u.id, u.created_at, u.upgrade_no, u.original_invoice_no, u.new_invoice_no, u.reason, 
                     u.remarks, u.created_by, u.branch, b.branch_code, u.total_amount
              FROM upgrades u
              LEFT JOIN branches b ON u.branch = b.branch_name
              $where_clause
              ORDER BY u.created_at DESC, u.id DESC";
    
    $result = $conn->query($query);
    
    $records = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $u_date = !empty($row['created_at']) ? date('m/d/Y', strtotime($row['created_at'])) : '';
            $u_upgrade_no = htmlspecialchars($row['upgrade_no']);
            $u_invoice = htmlspecialchars($row['original_invoice_no']);
            $u_reason = htmlspecialchars($row['reason'] ?? 'N/A');
            $u_remarks = htmlspecialchars($row['remarks'] ?? '');
            
            $b_name = $row['branch'] ? $row['branch'] : 'Unknown Branch';
            $b_code = $row['branch_code'] ? $row['branch_code'] : 'UNK';
            $u_branch = htmlspecialchars($b_name . ' - ' . $b_code);
            
            $u_id = $row['id'];
            
            // Get old items (items being traded in) and calculate total
            $old_items_query = $conn->prepare("SELECT item_description, imei, price FROM upgrade_old_items WHERE upgrade_id = ?");
            $old_items_query->bind_param("i", $u_id);
            $old_items_query->execute();
            $old_items_result = $old_items_query->get_result();
            
            $old_items_desc = [];
            $old_items_imei = [];
            $old_unit_total_calc = 0;
            $old_items_count = 0;
            while ($old_item = $old_items_result->fetch_assoc()) {
                $old_items_desc[] = htmlspecialchars($old_item['item_description']);
                $old_items_imei[] = htmlspecialchars($old_item['imei'] ?? 'N/A');
                $old_unit_total_calc += floatval($old_item['price'] ?? 0);
                $old_items_count++;
            }
            $old_items_query->close();
            $u_old_unit = !empty($old_items_desc) ? implode('<br>', $old_items_desc) : 'N/A';
            $u_old_imei = !empty($old_items_imei) ? implode('<br>', $old_items_imei) : 'N/A';
            $old_unit_total = number_format($old_unit_total_calc, 2);
            
            // Get new items (items being purchased) and calculate total
            $new_items_query = $conn->prepare("SELECT item_description, imei, price, quantity FROM upgrade_new_items WHERE upgrade_id = ?");
            $new_items_query->bind_param("i", $u_id);
            $new_items_query->execute();
            $new_items_result = $new_items_query->get_result();
            
            $new_items_desc = [];
            $new_items_imei = [];
            $new_unit_total_calc = 0;
            $new_items_count = 0;
            while ($new_item = $new_items_result->fetch_assoc()) {
                $new_items_desc[] = htmlspecialchars($new_item['item_description']);
                $new_items_imei[] = htmlspecialchars($new_item['imei'] ?? 'N/A');
                $qty = intval($new_item['quantity'] ?? 1);
                $new_unit_total_calc += floatval($new_item['price'] ?? 0) * $qty;
                $new_items_count++;
            }
            $new_items_query->close();
            $u_new_unit = !empty($new_items_desc) ? implode('<br>', $new_items_desc) : 'N/A';
            $u_new_imei = !empty($new_items_imei) ? implode('<br>', $new_items_imei) : 'N/A';
            $new_unit_total = number_format($new_unit_total_calc, 2);
            
            // For upgrade units, qty is typically 1 (representing the upgrade transaction)
            // Use the max count to handle cases where multiple items might be involved
            $total_qty = max($old_items_count, $new_items_count);
            
            // Format total amount
            $total_amount = number_format($row['total_amount'] ?? 0, 2);
            
            $records[] = [
                'date' => $u_date,
                'upgrade_no' => $u_upgrade_no,
                'invoice_no' => $u_invoice,
                'new_invoice_no' => htmlspecialchars($row['new_invoice_no'] ?? ''),
                'old_unit' => $u_old_unit,
                'old_imei' => $u_old_imei,
                'old_unit_total' => $old_unit_total,
                'new_unit' => $u_new_unit,
                'new_imei' => $u_new_imei,
                'new_unit_total' => $new_unit_total,
                'qty' => $total_qty,
                'total_amount' => $total_amount,
                'reason' => $u_reason,
                'remarks' => $u_remarks,
                'branch' => $u_branch
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
