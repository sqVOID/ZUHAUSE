<?php
require_once 'session_check.php';
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booklet_id = isset($_POST['booklet_id']) ? intval($_POST['booklet_id']) : 0;
    $invoice_number = isset($_POST['invoice_number']) ? trim($_POST['invoice_number']) : '';
    $cancel_reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';
    $view_branch = isset($_POST['view_branch']) ? $conn->real_escape_string($_POST['view_branch']) : '';

    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Unknown';
    $requested_by = isset($_SESSION['full_name']) && !empty($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;

    // Validation
    if ($booklet_id <= 0) {
        $_SESSION['cancel_error'] = "Invalid booklet ID.";
        header("Location: bookletinvlive.php" . ($view_branch ? "?view_branch=" . urlencode($view_branch) : ""));
        exit();
    }

    if (empty($invoice_number)) {
        $_SESSION['cancel_error'] = "Invoice number is required.";
        header("Location: bookletinvlive.php" . ($view_branch ? "?view_branch=" . urlencode($view_branch) : ""));
        exit();
    }

    if (empty($cancel_reason)) {
        $_SESSION['cancel_error'] = "Cancellation reason is required.";
        header("Location: bookletinvlive.php" . ($view_branch ? "?view_branch=" . urlencode($view_branch) : ""));
        exit();
    }

    // Fetch booklet details
    $booklet_query = $conn->prepare("SELECT * FROM booklet_numbers WHERE id = ?");
    $booklet_query->bind_param("i", $booklet_id);
    $booklet_query->execute();
    $booklet_result = $booklet_query->get_result();

    if ($booklet_result->num_rows === 0) {
        $_SESSION['cancel_error'] = "Booklet not found.";
        header("Location: bookletinvlive.php" . ($view_branch ? "?view_branch=" . urlencode($view_branch) : ""));
        exit();
    }

    $booklet = $booklet_result->fetch_assoc();
    $beginning_number = $booklet['beginning_number'];
    $ending_number = $booklet['ending_number'];
    $booklet_no = $booklet['booklet_no'];
    $branch_code = $booklet['branch_code'];
    $booklet_query->close();

    // Get branch name
    $branch_name = $branch_code;
    $branch_query = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = ? LIMIT 1");
    if ($branch_query) {
        $branch_query->bind_param("s", $branch_code);
        $branch_query->execute();
        $branch_res = $branch_query->get_result();
        if ($branch_res && $branch_res->num_rows > 0) {
            $branch_data = $branch_res->fetch_assoc();
            $branch_name = $branch_data['branch_name'];
        }
        $branch_query->close();
    }

    // Extract numeric part from invoice number
    $numeric_part = $invoice_number;
    if (strpos($invoice_number, '-') !== false) {
        $parts = explode('-', $invoice_number);
        $numeric_part = $parts[0];
    }

    // Server-side validation: Check if invoice number is within range
    $invoice_num = intval($numeric_part);
    $begin_num = intval($beginning_number);
    $end_num = intval($ending_number);

    if ($invoice_num < $begin_num || $invoice_num > $end_num) {
        $_SESSION['cancel_error'] = "Invoice number '$invoice_number' is outside the valid range! Valid range: $beginning_number to $ending_number.";
        header("Location: bookletinvlive.php" . ($view_branch ? "?view_branch=" . urlencode($view_branch) : ""));
        exit();
    }

    // Ensure skip_receipt_requests table exists
    $create_table = "CREATE TABLE IF NOT EXISTS skip_receipt_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id VARCHAR(50) UNIQUE NOT NULL,
        invoice_no VARCHAR(100) NOT NULL,
        branch_code VARCHAR(10) NOT NULL,
        branch_name VARCHAR(255),
        requested_by VARCHAR(255) NOT NULL,
        requested_by_user VARCHAR(255),
        reason TEXT NOT NULL,
        status ENUM('Pending', 'Approved', 'Rejected', 'Reverted') DEFAULT 'Pending',
        approved_by VARCHAR(255) NULL,
        approved_date DATETIME NULL,
        rejected_by VARCHAR(255) NULL,
        rejected_date DATETIME NULL,
        rejection_reason TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_invoice (invoice_no),
        INDEX idx_branch (branch_code),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if (!$conn->query($create_table)) {
        $_SESSION['cancel_error'] = "Failed to initialize database table. Please contact administrator.";
        header("Location: bookletinvlive.php" . ($view_branch ? "?view_branch=" . urlencode($view_branch) : ""));
        exit();
    }

    // Check if a pending or approved skip request already exists for this invoice number
    $check_stmt = $conn->prepare("SELECT id, status FROM skip_receipt_requests WHERE invoice_no = ? AND status IN ('Pending', 'Approved')");
    $check_stmt->bind_param("s", $invoice_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $existing = $check_result->fetch_assoc();
        $existing_status = strtolower($existing['status']);
        $_SESSION['cancel_error'] = "A $existing_status skip/cancel request already exists for invoice number '$invoice_number'.";
        $check_stmt->close();
        header("Location: bookletinvlive.php" . ($view_branch ? "?view_branch=" . urlencode($view_branch) : ""));
        exit();
    }
    $check_stmt->close();

    // Generate unique request ID
    $year = date('Y');
    $request_id_prefix = 'SR-' . $year . '-';

    $last_id_query = $conn->query("SELECT request_id FROM skip_receipt_requests WHERE request_id LIKE '$request_id_prefix%' ORDER BY id DESC LIMIT 1");
    if ($last_id_query && $last_id_query->num_rows > 0) {
        $last_request = $last_id_query->fetch_assoc();
        $last_number = intval(substr($last_request['request_id'], strlen($request_id_prefix)));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    $request_id = $request_id_prefix . str_pad($new_number, 5, '0', STR_PAD_LEFT);

    // Insert pending skip receipt request
    $insert_stmt = $conn->prepare("INSERT INTO skip_receipt_requests (request_id, invoice_no, branch_code, branch_name, requested_by, requested_by_user, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $insert_stmt->bind_param("sssssss", $request_id, $invoice_number, $branch_code, $branch_name, $requested_by, $username, $cancel_reason);

    if ($insert_stmt->execute()) {
        $_SESSION['cancel_success'] = "Cancellation request for Invoice Number '$invoice_number' (Booklet #$booklet_no) has been submitted successfully! Approval is required at Sales Skip Approval.";
    } else {
        $_SESSION['cancel_error'] = "Failed to submit cancellation request. Error: " . $conn->error;
    }

    $insert_stmt->close();

    // Redirect back to the booklet page
    header("Location: bookletinvlive.php" . ($view_branch ? "?view_branch=" . urlencode($view_branch) : ""));
    exit();
} else {
    // If not POST request, redirect to main page
    header("Location: bookletinvlive.php");
    exit();
}
?>