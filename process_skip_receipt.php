<?php
require_once 'session_check.php';
include 'config.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_name = $_SESSION['username'];
$action = isset($_POST['action']) ? $_POST['action'] : '';
$request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;

if (empty($action) || $request_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

if ($action === 'approve') {
    // Get the request details first
    $request_query = $conn->prepare("SELECT invoice_no, branch_code FROM skip_receipt_requests WHERE id = ?");
    $request_query->bind_param("i", $request_id);
    $request_query->execute();
    $request_result = $request_query->get_result();
    
    if ($request_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }
    
    $request_data = $request_result->fetch_assoc();
    $invoice_no = $request_data['invoice_no'];
    $branch_code = $request_data['branch_code'];
    $request_query->close();
    
    // Get the current booklet information to preserve padding
    $booklet_query = $conn->prepare("
        SELECT id, current_number 
        FROM booklet_numbers 
        WHERE branch_code = ? 
        AND status = 'Active' 
        LIMIT 1
    ");
    $booklet_query->bind_param("s", $branch_code);
    $booklet_query->execute();
    $booklet_result = $booklet_query->get_result();
    
    if ($booklet_result->num_rows > 0) {
        $booklet_data = $booklet_result->fetch_assoc();
        $current_number = $booklet_data['current_number'];
        $booklet_id = $booklet_data['id'];
        
        // Preserve padding when incrementing
        $current_num = intval($current_number);
        $padding = strlen($current_number);
        $next_number = str_pad($current_num + 1, $padding, '0', STR_PAD_LEFT);
        
        // Update the booklet to skip this number by setting it to the next number
        $update_booklet = $conn->prepare("
            UPDATE booklet_numbers 
            SET current_number = ?,
                last_used_date = NOW(),
                last_used_by = ?
            WHERE id = ?
        ");
        $update_booklet->bind_param("ssi", $next_number, $user_name, $booklet_id);
        $update_booklet->execute();
        $update_booklet->close();
    }
    $booklet_query->close();
    
    // Sync to cancelled_invoices table
    $conn->query("CREATE TABLE IF NOT EXISTS cancelled_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booklet_id INT NOT NULL,
        booklet_no VARCHAR(50),
        invoice_number VARCHAR(100) NOT NULL,
        cancel_reason TEXT NOT NULL,
        cancelled_by VARCHAR(255),
        cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_booklet_id (booklet_id),
        INDEX idx_invoice_number (invoice_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $req_info_stmt = $conn->prepare("SELECT reason, requested_by FROM skip_receipt_requests WHERE id = ?");
    $req_info_stmt->bind_param("i", $request_id);
    $req_info_stmt->execute();
    $req_info_data = $req_info_stmt->get_result()->fetch_assoc();
    $req_info_stmt->close();

    $cancel_reason = $req_info_data['reason'] ?? 'Skipped receipt approval';
    $requested_by_user = $req_info_data['requested_by'] ?? $user_name;
    $b_id = isset($booklet_id) ? $booklet_id : 0;
    $b_no = isset($booklet_data['booklet_no']) ? $booklet_data['booklet_no'] : '';

    $ins_cancel = $conn->prepare("INSERT INTO cancelled_invoices (booklet_id, booklet_no, invoice_number, cancel_reason, cancelled_by) VALUES (?, ?, ?, ?, ?)");
    $ins_cancel->bind_param("issss", $b_id, $b_no, $invoice_no, $cancel_reason, $requested_by_user);
    $ins_cancel->execute();
    $ins_cancel->close();

    // Approve the request
    $approved_date = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE skip_receipt_requests SET status = 'Approved', approved_by = ?, approved_date = ? WHERE id = ?");
    $stmt->bind_param("ssi", $user_name, $approved_date, $request_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Request approved successfully. Invoice number has been skipped.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to approve request: ' . $conn->error]);
    }
    $stmt->close();
    
} elseif ($action === 'reject') {
    // Reject the request
    $rejection_reason = isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : '';
    
    if (empty($rejection_reason)) {
        echo json_encode(['success' => false, 'message' => 'Rejection reason is required']);
        exit;
    }
    
    $rejected_date = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE skip_receipt_requests SET status = 'Rejected', rejected_by = ?, rejected_date = ?, rejection_reason = ? WHERE id = ?");
    $stmt->bind_param("sssi", $user_name, $rejected_date, $rejection_reason, $request_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Request rejected successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject request: ' . $conn->error]);
    }
    $stmt->close();
    
} elseif ($action === 'revert') {
    // Revert an approved request
    $revert_reason = isset($_POST['revert_reason']) ? $_POST['revert_reason'] : '';
    
    if (empty($revert_reason)) {
        echo json_encode(['success' => false, 'message' => 'Revert reason is required']);
        exit;
    }
    
    // Get the request details first
    $request_query = $conn->prepare("SELECT invoice_no, branch_code, status FROM skip_receipt_requests WHERE id = ?");
    $request_query->bind_param("i", $request_id);
    $request_query->execute();
    $request_result = $request_query->get_result();
    
    if ($request_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }
    
    $request_data = $request_result->fetch_assoc();
    
    // Check if the request is approved (and not already reverted)
    if ($request_data['status'] !== 'Approved') {
        if ($request_data['status'] === 'Reverted') {
            echo json_encode(['success' => false, 'message' => 'This request has already been reverted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Only approved requests can be reverted']);
        }
        exit;
    }
    
    $invoice_no = $request_data['invoice_no'];
    $branch_code = $request_data['branch_code'];
    $request_query->close();
    
    // Get the current booklet information
    $booklet_query = $conn->prepare("
        SELECT id, current_number 
        FROM booklet_numbers 
        WHERE branch_code = ? 
        AND status = 'Active' 
        LIMIT 1
    ");
    $booklet_query->bind_param("s", $branch_code);
    $booklet_query->execute();
    $booklet_result = $booklet_query->get_result();
    
    if ($booklet_result->num_rows > 0) {
        $booklet_data = $booklet_result->fetch_assoc();
        $current_number = $booklet_data['current_number'];
        $booklet_id = $booklet_data['id'];
        
        // Decrement the number (preserve padding)
        $current_num = intval($current_number);
        
        if ($current_num <= 1) {
            echo json_encode(['success' => false, 'message' => 'Cannot revert: invoice number is already at minimum']);
            exit;
        }
        
        $padding = strlen($current_number);
        $previous_number = str_pad($current_num - 1, $padding, '0', STR_PAD_LEFT);
        
        // Update the booklet to revert to previous number
        $update_booklet = $conn->prepare("
            UPDATE booklet_numbers 
            SET current_number = ?,
                last_used_date = NOW(),
                last_used_by = ?
            WHERE id = ?
        ");
        $update_booklet->bind_param("ssi", $previous_number, $user_name, $booklet_id);
        $update_booklet->execute();
        $update_booklet->close();
    }
    $booklet_query->close();
    
    // Remove from cancelled_invoices if present
    $del_cancel = $conn->prepare("DELETE FROM cancelled_invoices WHERE invoice_number = ?");
    $del_cancel->bind_param("s", $invoice_no);
    $del_cancel->execute();
    $del_cancel->close();

    // Update the request record - change status to 'Reverted'
    $reverted_date = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE skip_receipt_requests SET status = 'Reverted', is_reverted = TRUE, reverted_by = ?, reverted_date = ?, revert_reason = ? WHERE id = ?");
    $stmt->bind_param("sssi", $user_name, $reverted_date, $revert_reason, $request_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Request reverted successfully. Invoice number has been restored.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to revert request: ' . $conn->error]);
    }
    $stmt->close();
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>
