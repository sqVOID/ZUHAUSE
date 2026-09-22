<?php
require_once 'session_check.php';
include 'config.php';
$conn->query("
CREATE TABLE IF NOT EXISTS refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(50),
    original_sales_id INT,
    refund_date DATE,
    customer_name VARCHAR(100),
    approved_by VARCHAR(100),
    remarks TEXT,
    total_qty INT,
    total_amount DECIMAL(10,2),
    branch_code VARCHAR(10),
    encoder VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
");
$conn->query("
CREATE TABLE IF NOT EXISTS refund_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    refund_id INT,
    item_code VARCHAR(50),
    imei VARCHAR(100),
    quantity INT,
    price DECIMAL(10,2),
    item_description VARCHAR(255)
);
");
echo "Tables created/checked successfully: " . $conn->error;
