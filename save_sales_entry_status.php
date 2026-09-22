<?php
require_once 'session_check.php';
require_once 'config.php';

// Set response header
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
  
try {
    // Get POST data
    $entry_date = isset($_POST['entry_date']) ? trim($_POST['entry_date']) : '';
    $branch_code = isset($_POST['branch_code']) ? trim($_POST['branch_code']) : '';
    $branch_name = isset($_POST['branch_name']) ? trim($_POST['branch_name']) : '';
    $stock_type = isset($_POST['stock_type']) ? trim($_POST['stock_type']) : '';
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    $items_json = isset($_POST['items']) ? $_POST['items'] : '';

    // Validate required fields
    if (empty($entry_date)) {
        echo json_encode(['success' => false, 'message' => 'Date is required']);
        exit;
    }

    if (empty($branch_name)) {
        echo json_encode(['success' => false, 'message' => 'Branch is required']);
        exit;
    }

    if (empty($stock_type)) {
        echo json_encode(['success' => false, 'message' => 'Stock Type is required']);
        exit;
    }

    if (empty($remarks)) {
        echo json_encode(['success' => false, 'message' => 'Remarks are required']);
        exit;
    }

    // Decode items
    $items = json_decode($items_json, true);
    if (empty($items) || !is_array($items)) {
        echo json_encode(['success' => false, 'message' => 'No items provided']);
        exit;
    }

    // Get user information from session
    $user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Unknown';
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

    // Start transaction
    $conn->begin_transaction();

    // Create items details table if it doesn't exist
    $create_items_table = "CREATE TABLE IF NOT EXISTS sales_entry_status_items (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        log_id INT(11) NOT NULL,
        item_code VARCHAR(50),
        item_description TEXT,
        imei VARCHAR(100),
        quantity INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_log_id (log_id),
        FOREIGN KEY (log_id) REFERENCES sales_entry_status_log(id) ON DELETE CASCADE
    )";
    $conn->query($create_items_table);

    // Save the request to the log table with Pending status
    $log_sql = "INSERT INTO sales_entry_status_log 
                (entry_date, branch_code, branch_name, stock_type, remarks, items_count, 
                 updated_count, created_by, created_at, status) 
                VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW(), 'Pending')";
    
    $log_stmt = $conn->prepare($log_sql);
    if (!$log_stmt) {
        throw new Exception("Failed to prepare log statement: " . $conn->error);
    }
    
    $items_count = count($items);
    $log_stmt->bind_param('sssssis', 
        $entry_date, $branch_code, $branch_name, $stock_type, $remarks, 
        $items_count, $user_name
    );
    $log_stmt->execute();
    $log_id = $conn->insert_id;
    $log_stmt->close();

    // Save the items details
    $items_sql = "INSERT INTO sales_entry_status_items 
                  (log_id, item_code, item_description, imei, quantity) 
                  VALUES (?, ?, ?, ?, ?)";
    
    $items_stmt = $conn->prepare($items_sql);
    if (!$items_stmt) {
        throw new Exception("Failed to prepare items statement: " . $conn->error);
    }
    
    foreach ($items as $item) {
        $item_code = isset($item['item_code']) ? trim($item['item_code']) : '';
        $item_description = isset($item['item_description']) ? trim($item['item_description']) : '';
        $imei = isset($item['imei']) ? trim($item['imei']) : '';
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
        
        if (empty($item_code)) {
            continue;
        }
        
        $items_stmt->bind_param('isssi', $log_id, $item_code, $item_description, $imei, $quantity);
        $items_stmt->execute();
    }
    $items_stmt->close();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Item status change request submitted successfully! Request ID: $log_id. Waiting for approval.",
        'log_id' => $log_id,
        'items_count' => $items_count
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
