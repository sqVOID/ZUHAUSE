<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$log_id = isset($_GET['log_id']) ? intval($_GET['log_id']) : 0;

if (empty($log_id)) {
    echo json_encode(['success' => false, 'message' => 'Log ID is required']);
    exit;
}

try {
    // Get the log entry details including entry_date and status
    $log_sql = "SELECT branch_name, stock_type, remarks, entry_date, created_at, status FROM sales_entry_status_log WHERE id = ?";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param('i', $log_id);
    $log_stmt->execute();
    $log_result = $log_stmt->get_result();
    
    if ($log_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Log entry not found']);
        exit;
    }
    
    $log_data = $log_result->fetch_assoc();
    $log_status = $log_data['status'] ?? 'Pending'; // Get the approval status
    $log_stmt->close();
    
    // Get all items for this log entry with their current status from stock_on_hand
    $items_sql = "SELECT item_code, item_description, imei, quantity 
                  FROM sales_entry_status_items 
                  WHERE log_id = ? 
                  ORDER BY id ASC";
    
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param('i', $log_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $items = [];
    while ($row = $items_result->fetch_assoc()) {
        $item_code = $row['item_code'] ?? '';
        $imei = $row['imei'] ?? '';
        
        // Get current status from stock_on_hand
        $previous_status = 'N/A';
        $current_branch = $log_data['branch_name'] ?? 'N/A';
        $item_date = 'N/A';
        
        if (!empty($item_code)) {
            if (!empty($imei)) {
                // For serialized items - get by item_code and IMEI
                $status_sql = "SELECT status, branch, dr_date FROM stock_on_hand 
                              WHERE item_code = ? AND imei = ? 
                              LIMIT 1";
                $status_stmt = $conn->prepare($status_sql);
                $status_stmt->bind_param('ss', $item_code, $imei);
                $status_stmt->execute();
                $status_result = $status_stmt->get_result();
                if ($status_row = $status_result->fetch_assoc()) {
                    $previous_status = $status_row['status'] ?? 'N/A';
                    $current_branch = $status_row['branch'] ?? $log_data['branch_name'];
                    $item_date = $status_row['dr_date'] ?? 'N/A';
                }
                $status_stmt->close();
            } else {
                // For non-serialized items - get by item_code and branch
                $status_sql = "SELECT status, dr_date FROM stock_on_hand 
                              WHERE item_code = ? AND branch = ? 
                              AND (imei IS NULL OR imei = '')
                              LIMIT 1";
                $status_stmt = $conn->prepare($status_sql);
                $status_stmt->bind_param('ss', $item_code, $log_data['branch_name']);
                $status_stmt->execute();
                $status_result = $status_stmt->get_result();
                if ($status_row = $status_result->fetch_assoc()) {
                    $previous_status = $status_row['status'] ?? 'N/A';
                    $item_date = $status_row['dr_date'] ?? 'N/A';
                }
                $status_stmt->close();
            }
        }
        
        // Get history for this item - show ALL history BEFORE this specific log entry
        $history = [];
        $history_previous_status = null;
        
        if (!empty($imei)) {
            // First, check if THIS request has been approved and has a history record
            // If so, use the previous_status from that history record for FROM STATUS
            if ($log_status === 'Approved') {
                $this_approval_sql = "SELECT previous_status FROM stock_status_history 
                                     WHERE imei = ? AND approval_log_id = ? LIMIT 1";
                $this_approval_stmt = $conn->prepare($this_approval_sql);
                $this_approval_stmt->bind_param('si', $imei, $log_id);
                $this_approval_stmt->execute();
                $this_approval_result = $this_approval_stmt->get_result();
                if ($this_row = $this_approval_result->fetch_assoc()) {
                    // This approval has a history record - use its previous_status as FROM STATUS
                    $history_previous_status = $this_row['previous_status'];
                }
                $this_approval_stmt->close();
            }
            
            // For serialized items - get ALL history BEFORE THIS approval request (excluding current)
            $history_sql = "SELECT previous_status, new_status, branch, changed_by, changed_at, remarks, approval_log_id 
                           FROM stock_status_history 
                           WHERE imei = ? 
                           AND approval_log_id < ? 
                           ORDER BY changed_at DESC"; // DESC to get newest first
            $history_stmt = $conn->prepare($history_sql);
            $history_stmt->bind_param('si', $imei, $log_id);
            $history_stmt->execute();
            $history_result = $history_stmt->get_result();
            
            // Collect all history records
            $history_records = [];
            $oldest_previous_status = null;
            
            while ($history_row = $history_result->fetch_assoc()) {
                $history_records[] = $history_row;
                // The last record has the original previous_status
                $oldest_previous_status = $history_row['previous_status'];
            }
            $history_stmt->close();
            
            // Add status changes in order (newest to oldest, already sorted by query)
            foreach ($history_records as $history_row) {
                $history[] = [
                    'date' => $history_row['changed_at'],
                    'item_code' => $item_code,
                    'imei' => $imei,
                    'quantity' => intval($row['quantity'] ?? 0),
                    'current_branch' => $history_row['branch'],
                    'previous_status' => $history_row['new_status'], // Show what status it changed TO
                    'is_current' => false
                ];
            }
            
            // Add the original Good Stock status at the end (use dr_date)
            if (!empty($history_records) && $oldest_previous_status !== null) {
                $original_date = $item_date !== 'N/A' ? $item_date : $history_records[count($history_records) - 1]['changed_at'];
                if ($history_previous_status === null) {
                    $history_previous_status = $oldest_previous_status;
                }
                
                $history[] = [
                    'date' => $original_date,
                    'item_code' => $item_code,
                    'imei' => $imei,
                    'quantity' => intval($row['quantity'] ?? 0),
                    'current_branch' => $history_records[count($history_records) - 1]['branch'],
                    'previous_status' => $oldest_previous_status, // Show the ORIGINAL status
                    'is_current' => false
                ];
            }
            
            // If no history found from previous approvals AND this is approved, get the previous_status from THIS approval's history
            if (empty($history) && $log_status === 'Approved' && $history_previous_status !== null) {
                // Use dr_date for the original date
                $original_date = $item_date !== 'N/A' ? $item_date : '';
                
                // Show the status BEFORE this change
                $history[] = [
                    'date' => $original_date,
                    'item_code' => $item_code,
                    'imei' => $imei,
                    'quantity' => intval($row['quantity'] ?? 0),
                    'current_branch' => $current_branch,
                    'previous_status' => $history_previous_status, // Show what it was BEFORE
                    'is_current' => false
                ];
            }
        } else {
            // First, check if THIS request has been approved and has a history record
            // If so, use the previous_status from that history record for FROM STATUS
            if ($log_status === 'Approved') {
                $this_approval_sql = "SELECT previous_status FROM stock_status_history 
                                     WHERE item_code = ? AND branch = ? AND (imei IS NULL OR imei = '') 
                                     AND approval_log_id = ? LIMIT 1";
                $this_approval_stmt = $conn->prepare($this_approval_sql);
                $this_approval_stmt->bind_param('ssi', $item_code, $log_data['branch_name'], $log_id);
                $this_approval_stmt->execute();
                $this_approval_result = $this_approval_stmt->get_result();
                if ($this_row = $this_approval_result->fetch_assoc()) {
                    // This approval has a history record - use its previous_status as FROM STATUS
                    $history_previous_status = $this_row['previous_status'];
                }
                $this_approval_stmt->close();
            }
            
            // For non-serialized items - get ALL history BEFORE THIS approval request (excluding current)
            $history_sql = "SELECT previous_status, new_status, branch, changed_by, changed_at, remarks, approval_log_id 
                           FROM stock_status_history 
                           WHERE item_code = ? AND branch = ? AND (imei IS NULL OR imei = '') 
                           AND approval_log_id < ? 
                           ORDER BY changed_at DESC"; // DESC to get newest first
            $history_stmt = $conn->prepare($history_sql);
            $history_stmt->bind_param('ssi', $item_code, $log_data['branch_name'], $log_id);
            $history_stmt->execute();
            $history_result = $history_stmt->get_result();
            
            // Collect all history records
            $history_records = [];
            $oldest_previous_status = null;
            
            while ($history_row = $history_result->fetch_assoc()) {
                $history_records[] = $history_row;
                // The last record has the original previous_status
                $oldest_previous_status = $history_row['previous_status'];
            }
            $history_stmt->close();
            
            // Add status changes in order (newest to oldest, already sorted by query)
            foreach ($history_records as $history_row) {
                $history[] = [
                    'date' => $history_row['changed_at'],
                    'item_code' => $item_code,
                    'imei' => $imei,
                    'quantity' => intval($row['quantity'] ?? 0),
                    'current_branch' => $history_row['branch'],
                    'previous_status' => $history_row['new_status'], // Show what status it changed TO
                    'is_current' => false
                ];
            }
            
            // Add the original Good Stock status at the end (use dr_date)
            if (!empty($history_records) && $oldest_previous_status !== null) {
                $original_date = $item_date !== 'N/A' ? $item_date : $history_records[count($history_records) - 1]['changed_at'];
                if ($history_previous_status === null) {
                    $history_previous_status = $oldest_previous_status;
                }
                
                $history[] = [
                    'date' => $original_date,
                    'item_code' => $item_code,
                    'imei' => $imei,
                    'quantity' => intval($row['quantity'] ?? 0),
                    'current_branch' => $history_records[count($history_records) - 1]['branch'],
                    'previous_status' => $oldest_previous_status, // Show the ORIGINAL status
                    'is_current' => false
                ];
            }
            
            // If no history found from previous approvals AND this is approved, get the previous_status from THIS approval's history
            if (empty($history) && $log_status === 'Approved' && $history_previous_status !== null) {
                // Use dr_date for the original date
                $original_date = $item_date !== 'N/A' ? $item_date : '';
                
                // Show the status BEFORE this change
                $history[] = [
                    'date' => $original_date,
                    'item_code' => $item_code,
                    'imei' => $imei,
                    'quantity' => intval($row['quantity'] ?? 0),
                    'current_branch' => $log_data['branch_name'],
                    'previous_status' => $history_previous_status, // Show what it was BEFORE
                    'is_current' => false
                ];
            }
        }
        
        // If we have history, use the previous_status from the latest history record
        // Otherwise, fall back to the stock_on_hand status
        if ($history_previous_status !== null) {
            $previous_status = $history_previous_status;
        }
        
        $items[] = [
            'item_code' => $item_code,
            'item_description' => $row['item_description'] ?? '',
            'imei' => $imei,
            'quantity' => intval($row['quantity'] ?? 0),
            'previous_status' => $previous_status,
            'current_branch' => $current_branch,
            'item_date' => $item_date,
            'history' => $history  // Complete history of all status changes
        ];
    }
    
    $items_stmt->close();
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'branch_name' => $log_data['branch_name'] ?? '',
        'stock_type' => $log_data['stock_type'] ?? '',
        'remarks' => $log_data['remarks'] ?? '',
        'entry_date' => $log_data['entry_date'] ?? '',
        'created_at' => $log_data['created_at'] ?? '',
        'status' => $log_status
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
