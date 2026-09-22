<?php
// Endpoint specifically for receivestocktransfer.php
// Only shows transfers WHERE TO = user's branch (incoming transfers to receive)

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once 'session_check.php';
    include 'config.php';
    
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
    
    // Get user's branch information for filtering
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
    
    // Check if user is Super-Admin (has access to all branches)
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
    
    // CRITICAL: For receivestocktransfer.php, only show transfers TO user's branch
    // Only Super-Admin sees all branches
    if (!$is_super_admin && !empty($user_branch)) {
        // Handle multiple branches for Sub-admin (comma-separated)
        $branch_list = array_map('trim', explode(',', $user_branch));
        $branch_list_escaped = array_map(function($branch) use ($conn) {
            return "'" . $conn->real_escape_string($branch) . "'";
        }, $branch_list);
        $branch_list_sql = implode(',', $branch_list_escaped);
        
        // Only show transfers WHERE TO = user's branch (incoming transfers)
        $sql .= " AND st.branch_to IN ($branch_list_sql)";
    }
    
    // Apply date filters
    if (!empty($date_from)) {
        $sql .= " AND st.st_date >= '$date_from'";
    }
    
    if (!empty($date_to)) {
        $sql .= " AND st.st_date <= '$date_to'";
    }
    
    // Apply status filter
    if (!empty($status) && $status !== 'all') {
        $sql .= " AND st.status = '$status'";
    }
    
    $sql .= " ORDER BY st.st_date DESC, st.st_number DESC";
    
    // Log query for debugging
    error_log("Receive Transfers Query: " . $sql);
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
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}

ob_end_flush();
?>
