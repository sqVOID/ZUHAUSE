<?php
// Disable any output buffering and error display that might interfere
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output

try {
    require_once 'session_check.php';
    include 'config.php';
    
    // Clear any output that might have been generated
    ob_clean();
    
    header('Content-Type: application/json');
    
    // Ensure tables exist
    $create_transfers = "CREATE TABLE IF NOT EXISTS stock_transfers (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        st_number VARCHAR(50) NOT NULL UNIQUE,
        st_date DATE NOT NULL,
        branch_from VARCHAR(255),
        branch_to VARCHAR(255),
        store_name VARCHAR(255),
        prepared_by VARCHAR(100),
        approver VARCHAR(100),
        approval_date DATETIME,
        status VARCHAR(20) DEFAULT 'Pending',
        remarks TEXT,
        total_quantity INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($create_transfers)) {
        throw new Exception("Table creation failed: " . $conn->error);
    }
    
    // Get filter parameters
    $date_from = isset($_POST['date_from']) ? $conn->real_escape_string($_POST['date_from']) : '';
    $date_to = isset($_POST['date_to']) ? $conn->real_escape_string($_POST['date_to']) : '';
    $status = isset($_POST['status']) ? $conn->real_escape_string($_POST['status']) : '';
    $branch_from = isset($_POST['branch_from']) ? $conn->real_escape_string($_POST['branch_from']) : '';
    $branch_to = isset($_POST['branch_to']) ? $conn->real_escape_string($_POST['branch_to']) : '';
    
    // Get user's branch information for filtering
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
    
    // Check if user is Super-Admin (has access to all branches)
    // Sub-admins can have multiple branches, but still need branch filtering
    $is_super_admin = (strcasecmp($system_level, 'Super-Admin') === 0);
    
    // Build query - JOIN branches to get full "code - name" for both from/to
    // JOIN accounts to get full names for prepared_by and approver
    $sql = "SELECT 
                st.st_number, 
                st.st_date as date, 
                CONCAT(st.branch_from, ' - ', COALESCE(bf.branch_name, '')) as branch_from,
                CONCAT(COALESCE(bt.branch_code, ''), ' - ', st.branch_to) as branch_to,
                COALESCE(CONCAT(acc_prep.first_name, ' ', acc_prep.last_name), st.prepared_by) as prepared_by,
                COALESCE(CONCAT(acc_appr.first_name, ' ', acc_appr.last_name), st.approver) as approver,
                st.received_by,
                st.status, 
                st.remarks 
            FROM stock_transfers st
            LEFT JOIN branches bf ON bf.branch_code = st.branch_from
            LEFT JOIN branches bt ON bt.branch_name = st.branch_to
            LEFT JOIN accounts acc_prep ON st.prepared_by = acc_prep.username
            LEFT JOIN accounts acc_appr ON st.approver = acc_appr.username
            WHERE st.status != 'Received'";
    
    // Filter by user's branch - only Super-Admin sees all branches
    // Regular users and Sub-admins see transfers FROM or TO their assigned branch(es)
    if (!$is_super_admin && !empty($user_branch)) {
        // Handle multiple branches for Sub-admin (comma-separated)
        $branch_list = array_map('trim', explode(',', $user_branch));
        
        // Build conditions for both branch_from and branch_to
        $branch_conditions = [];
        foreach ($branch_list as $branch) {
            $branch_escaped = $conn->real_escape_string($branch);
            
            // Get branch code for FROM comparison
            $branch_code_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$branch_escaped' LIMIT 1");
            if ($branch_code_query && $branch_code_query->num_rows > 0) {
                $branch_code_data = $branch_code_query->fetch_assoc();
                $user_branch_code = $conn->real_escape_string($branch_code_data['branch_code']);
                $branch_conditions[] = "st.branch_from = '$user_branch_code'";
            }
            
            // TO comparison uses branch name
            $branch_conditions[] = "st.branch_to = '$branch_escaped'";
        }
        
        if (!empty($branch_conditions)) {
            $sql .= " AND (" . implode(' OR ', $branch_conditions) . ")";
        }
    }
    
    // Add branch filters for Super-Admin and Sub-admin
    if (!empty($branch_from) && $branch_from !== 'ALL') {
        $sql .= " AND st.branch_from = '$branch_from'";
    }
    
    if (!empty($branch_to) && $branch_to !== 'ALL') {
        // branch_to is stored as branch_name, need to get the name from code
        $branch_to_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '$branch_to' LIMIT 1");
        if ($branch_to_query && $branch_to_query->num_rows > 0) {
            $branch_to_data = $branch_to_query->fetch_assoc();
            $branch_to_name = $conn->real_escape_string($branch_to_data['branch_name']);
            $sql .= " AND st.branch_to = '$branch_to_name'";
        }
    }
    
    if (!empty($date_from)) {
        $sql .= " AND st.st_date >= '$date_from'";
    }
    
    if (!empty($date_to)) {
        $sql .= " AND st.st_date <= '$date_to'";
    }
    
    if (!empty($status) && $status !== 'all') {
        $sql .= " AND st.status = '$status'";
    }
    
    $sql .= " ORDER BY st.st_date DESC, st.st_number DESC";
    
    // Log query for debugging
    error_log("Transfer Approvals Query: " . $sql);
    error_log("Status filter: " . $status);
    error_log("User branch: " . $user_branch);
    error_log("System level: " . $system_level);
    error_log("Is Super Admin: " . ($is_super_admin ? 'Yes' : 'No'));
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $transfers = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $transfers[] = $row;
        }
    }
    
    error_log("Number of transfers found: " . count($transfers));
    
    $conn->close();
    
    echo json_encode($transfers);
    
} catch (Exception $e) {
    // Clear any output
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}

ob_end_flush();
?>
