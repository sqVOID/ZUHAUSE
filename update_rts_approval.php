<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['id']) || !isset($_POST['status'])) {
        throw new Exception('Missing required parameters');
    }
    
    $approval_log_id = intval($_POST['id']);
    $new_status = trim($_POST['status']);
    
    // Validate status
    if (!in_array($new_status, ['Approved', 'Disapproved'])) {
        throw new Exception('Invalid status');
    }
    
    // Get current user info
    $user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Unknown';
    $current_time = date('Y-m-d H:i:s');
    
    // Update the approval log
    if ($new_status === 'Approved') {
        $update_query = "UPDATE rts_approval_log 
                        SET status = 'Approved', 
                            approver = ?, 
                            approval_date = ?,
                            disapprover = NULL,
                            disapproval_date = NULL
                        WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssi", $user_name, $current_time, $approval_log_id);
    } else {
        $update_query = "UPDATE rts_approval_log 
                        SET status = 'Disapproved', 
                            disapprover = ?, 
                            disapproval_date = ?,
                            approver = NULL,
                            approval_date = NULL
                        WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssi", $user_name, $current_time, $approval_log_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => "Return to supplier entry has been $new_status successfully"
        ]);
    } else {
        throw new Exception('Failed to update status: ' . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
