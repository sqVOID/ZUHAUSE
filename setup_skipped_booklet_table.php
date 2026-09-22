<?php
require_once 'session_check.php';
/**
 * Setup script for skipped_booklet_numbers table
 * Run this once to create the table in your database
 */

require_once 'config.php';

echo "Setting up skipped_booklet_numbers table...\n<br>";

$sql = "CREATE TABLE IF NOT EXISTS `skipped_booklet_numbers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booklet_id` int(11) NOT NULL COMMENT 'References booklet_numbers.id',
  `branch_code` varchar(50) NOT NULL,
  `skipped_number` varchar(100) NOT NULL COMMENT 'The booklet number that was skipped',
  `reason` text NOT NULL COMMENT 'Reason for skipping the number',
  `skipped_by` varchar(100) NOT NULL COMMENT 'Username who skipped the number',
  `skipped_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booklet_id` (`booklet_id`),
  KEY `branch_code` (`branch_code`),
  KEY `skipped_at` (`skipped_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'skipped_booklet_numbers' created successfully!\n<br>";
    
    // Check if foreign key exists
    $check_fk = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                 WHERE TABLE_NAME = 'skipped_booklet_numbers' 
                 AND CONSTRAINT_NAME LIKE '%booklet_id%'
                 AND TABLE_SCHEMA = DATABASE()";
    
    $result = $conn->query($check_fk);
    
    if ($result && $result->num_rows == 0) {
        // Add foreign key constraint
        $fk_sql = "ALTER TABLE `skipped_booklet_numbers` 
                   ADD CONSTRAINT `fk_skipped_booklet_id` 
                   FOREIGN KEY (`booklet_id`) 
                   REFERENCES `booklet_numbers`(`id`) 
                   ON DELETE CASCADE";
        
        if ($conn->query($fk_sql) === TRUE) {
            echo "✓ Foreign key constraint added successfully!\n<br>";
        } else {
            echo "⚠ Warning: Could not add foreign key constraint: " . $conn->error . "\n<br>";
            echo "This is optional and the table will still work.\n<br>";
        }
    } else {
        echo "✓ Foreign key constraint already exists.\n<br>";
    }
    
    echo "\n<br><strong>Setup complete! You can now use the Skip Booklet Number feature.</strong>\n<br>";
    echo "<a href='skipbookletno.php'>Go to Skip Booklet Number page</a>";
    
} else {
    echo "✗ Error creating table: " . $conn->error . "\n<br>";
}

$conn->close();
?>
