<?php
require_once 'session_check.php';
include 'config.php';

// Create stock_transfers table
$sql_transfers = "CREATE TABLE IF NOT EXISTS `stock_transfers` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `st_number` VARCHAR(50) NOT NULL UNIQUE,
  `st_date` DATE NOT NULL,
  `branch_from` VARCHAR(10) DEFAULT NULL,
  `branch_to` VARCHAR(10) DEFAULT NULL,
  `store_name` VARCHAR(255) DEFAULT NULL,
  `prepared_by` VARCHAR(100) DEFAULT NULL,
  `approver` VARCHAR(100) DEFAULT NULL,
  `approval_date` DATETIME DEFAULT NULL,
  `status` VARCHAR(20) DEFAULT 'Pending',
  `remarks` TEXT DEFAULT NULL,
  `total_quantity` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql_transfers) === TRUE) {
    echo "Table 'stock_transfers' created successfully.<br>";
} else {
    echo "Error creating table 'stock_transfers': " . $conn->error . "<br>";
}

// Create stock_transfer_items table
$sql_items = "CREATE TABLE IF NOT EXISTS `stock_transfer_items` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `st_number` VARCHAR(50) NOT NULL,
  `item_code` VARCHAR(50) DEFAULT NULL,
  `item_description` TEXT DEFAULT NULL,
  `imei` VARCHAR(100) DEFAULT NULL,
  `quantity` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_st_number` (`st_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql_items) === TRUE) {
    echo "Table 'stock_transfer_items' created successfully.<br>";
} else {
    echo "Error creating table 'stock_transfer_items': " . $conn->error . "<br>";
}

// Insert sample data
$check_data = $conn->query("SELECT COUNT(*) as count FROM stock_transfers");
$row = $check_data->fetch_assoc();

if ($row['count'] == 0) {
    echo "<br>Inserting sample data...<br>";
    
    $sample_transfers = [
        ['ST-00001', '2024-01-15', '001', '002', 'Main Store', 'John Doe', 'Pending', 'Regular transfer', 5],
        ['ST-00002', '2024-01-20', '002', '003', 'Branch Store', 'Jane Smith', 'Approved', 'Urgent transfer', 3],
        ['ST-00003', '2024-01-25', '001', '003', 'Main Store', 'Bob Johnson', 'Disapproved', 'Insufficient stock', 2]
    ];
    
    foreach ($sample_transfers as $transfer) {
        $stmt = $conn->prepare("INSERT INTO stock_transfers (st_number, st_date, branch_from, branch_to, store_name, prepared_by, status, remarks, total_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssssssi', $transfer[0], $transfer[1], $transfer[2], $transfer[3], $transfer[4], $transfer[5], $transfer[6], $transfer[7], $transfer[8]);
        $stmt->execute();
    }
    
    $sample_items = [
        ['ST-00001', 'ITEM001', 'Samsung Galaxy S21', '123456789012345', 2],
        ['ST-00001', 'ITEM002', 'iPhone 13 Pro', '987654321098765', 3],
        ['ST-00002', 'ITEM003', 'Xiaomi Redmi Note 10', '456789123456789', 3],
        ['ST-00003', 'ITEM001', 'Samsung Galaxy S21', '321654987321654', 2]
    ];
    
    foreach ($sample_items as $item) {
        $stmt = $conn->prepare("INSERT INTO stock_transfer_items (st_number, item_code, item_description, imei, quantity) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssi', $item[0], $item[1], $item[2], $item[3], $item[4]);
        $stmt->execute();
    }
    
    echo "Sample data inserted successfully.<br>";
}

echo "<br><strong>Setup completed!</strong><br>";
echo "<a href='transferapproval.php'>Go to Transfer Approval</a>";

$conn->close();
?>
