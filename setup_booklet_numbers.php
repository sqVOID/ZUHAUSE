<?php
require_once 'session_check.php';
/**
 * Setup script for booklet_numbers table
 * Run this file once to create the database table
 */

include 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Booklet Numbers Table Setup</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #2e7d32; }
        .success { color: #2e7d32; background: #e8f5e9; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #c62828; background: #ffebee; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #1976d2; background: #e3f2fd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>
    <h1>Booklet Numbers Table Setup</h1>";

// Create booklet_numbers table
$sql = "CREATE TABLE IF NOT EXISTS `booklet_numbers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_code` varchar(50) NOT NULL,
  `page_type` varchar(50) NOT NULL COMMENT 'salesentry, preorder, stocktransfer, etc.',
  `booklet_format` varchar(50) NOT NULL COMMENT 'numeric, date_suffix, or custom',
  `current_number` varchar(100) NOT NULL COMMENT 'Current invoice number',
  `prefix` varchar(50) DEFAULT NULL COMMENT 'Optional prefix for invoice number',
  `suffix` varchar(50) DEFAULT NULL COMMENT 'Optional suffix for invoice number',
  `description` text DEFAULT NULL COMMENT 'Optional description or notes',
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `branch_code` (`branch_code`),
  KEY `page_type` (`page_type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "<div class='success'>✓ Table 'booklet_numbers' created successfully or already exists!</div>";
    
    // Check if table has data
    $check = $conn->query("SELECT COUNT(*) as count FROM booklet_numbers");
    $row = $check->fetch_assoc();
    
    if ($row['count'] == 0) {
        echo "<div class='info'>ℹ Table is empty. You can now add booklet numbers through the registration page.</div>";
    } else {
        echo "<div class='info'>ℹ Table has " . $row['count'] . " record(s).</div>";
    }
    
} else {
    echo "<div class='error'>✗ Error creating table: " . $conn->error . "</div>";
}

echo "<h2>Table Structure:</h2>";
echo "<pre>";
echo "Fields:\n";
echo "- id (Primary Key)\n";
echo "- branch_code (Links to branches table)\n";
echo "- page_type (salesentry, preorder, stocktransfer, etc.)\n";
echo "- booklet_format (numeric/date_suffix/custom)\n";
echo "- current_number (Current invoice number)\n";
echo "- prefix (Optional)\n";
echo "- suffix (Optional)\n";
echo "- description (Optional notes)\n";
echo "- status (Active/Inactive)\n";
echo "- created_at\n";
echo "- updated_at\n";
echo "</pre>";

echo "<h2>Next Steps:</h2>";
echo "<ul>";
echo "<li>Go to <a href='bookletnoreg.php'>Booklet Number Registration</a> to add invoice number formats</li>";
echo "<li>Each branch can have multiple booklet number formats</li>";
echo "<li>Formats supported: Numeric (0003128-092-149), Date+Suffix (07-06-2026-SD10), or Custom</li>";
echo "</ul>";

echo "</div>
</body>
</html>";

$conn->close();
?>
