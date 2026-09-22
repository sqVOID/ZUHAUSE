<?php
require_once 'session_check.php';
require_once 'config.php';

echo "<h2>Setting up claimed_preorder_items table...</h2>";

$sql = "
CREATE TABLE IF NOT EXISTS claimed_preorder_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    preorder_id INT NOT NULL,
    preorder_invoice_no VARCHAR(50) NOT NULL,
    sales_entry_id INT NULL,
    sales_invoice_no VARCHAR(50) NULL,
    
    preorder_item_id INT NULL,
    family_code VARCHAR(100) NOT NULL COMMENT 'From pre-order',
    
    item_code VARCHAR(100) NOT NULL COMMENT 'Actual item code',
    item_description VARCHAR(255) NOT NULL COMMENT 'Full item description',
    imei VARCHAR(100) NULL COMMENT 'Serial/IMEI number',
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    
    amount_paid DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Amount already paid in pre-order',
    balance_due DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Remaining balance at claim time',
    payment_method VARCHAR(50) NULL COMMENT 'Payment method used at claim',
    payment_status VARCHAR(20) DEFAULT 'pending' COMMENT 'paid, partial, pending',
    
    dr_number VARCHAR(100) NULL COMMENT 'DR number from stock',
    
    claimed_by VARCHAR(100) NULL,
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    branch_code VARCHAR(10) NULL,
    
    INDEX idx_preorder_id (preorder_id),
    INDEX idx_preorder_invoice (preorder_invoice_no),
    INDEX idx_sales_entry (sales_entry_id),
    INDEX idx_item_code (item_code),
    INDEX idx_imei (imei),
    INDEX idx_claimed_at (claimed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

try {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✓ Table 'claimed_preorder_items' created successfully!</p>";
        
        // Check if table exists and show structure
        $result = $conn->query("DESCRIBE claimed_preorder_items");
        if ($result) {
            echo "<h3>Table Structure:</h3>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        echo "<p><strong>Setup complete!</strong> You can now use the claim preorder feature.</p>";
        echo "<p><a href='claimpreorder.php'>Go to Claim Pre-order</a></p>";
        
    } else {
        echo "<p style='color: red;'>✗ Error creating table: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>";
}

$conn->close();
?>
