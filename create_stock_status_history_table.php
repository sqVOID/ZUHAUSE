<?php
require_once 'session_check.php';
include 'config.php';

// Create stock status history table
$create_history_table = "CREATE TABLE IF NOT EXISTS stock_status_history (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(50),
    imei VARCHAR(100),
    quantity INT(11) DEFAULT 0,
    branch VARCHAR(255),
    previous_status VARCHAR(50),
    new_status VARCHAR(50),
    changed_by VARCHAR(100),
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approval_log_id INT(11),
    remarks TEXT,
    INDEX idx_imei (imei),
    INDEX idx_item_code (item_code),
    INDEX idx_changed_at (changed_at),
    INDEX idx_branch (branch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($create_history_table)) {
    echo "✓ Stock status history table created successfully!<br>";
} else {
    echo "✗ Error creating table: " . $conn->error . "<br>";
}

$conn->close();
?>
