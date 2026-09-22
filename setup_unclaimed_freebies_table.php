<?php
require_once 'session_check.php';
include 'config.php';

// Create unclaimed_freebies table
$sql = "CREATE TABLE IF NOT EXISTS unclaimed_freebies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sales_entry_id INT NOT NULL,
    invoice_number VARCHAR(50),
    item_code VARCHAR(100) NOT NULL,
    item_description TEXT,
    quantity INT DEFAULT 0,
    note TEXT,
    branch VARCHAR(255),
    created_by VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    claimed_at TIMESTAMP NULL DEFAULT NULL,
    voided_at TIMESTAMP NULL DEFAULT NULL,
    voided_by VARCHAR(255) NULL,
    void_reason TEXT NULL,
    status VARCHAR(50) DEFAULT 'unclaimed',
    INDEX idx_sales_entry (sales_entry_id),
    INDEX idx_invoice (invoice_number),
    INDEX idx_item_code (item_code),
    INDEX idx_branch (branch),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Table 'unclaimed_freebies' created successfully or already exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

$conn->close();
?>