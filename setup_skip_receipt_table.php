<?php
require_once 'session_check.php';
include 'config.php';

// Create skip_receipt_requests table
$sql = "CREATE TABLE IF NOT EXISTS skip_receipt_requests (
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

if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'skip_receipt_requests' created successfully or already exists.<br>";
} else {
    echo "✗ Error creating table 'skip_receipt_requests': " . $conn->error . "<br>";
}

$conn->close();
echo "<br>Setup completed!";
?>
