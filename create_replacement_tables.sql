-- Create replacements table
CREATE TABLE IF NOT EXISTS `replacements` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `replacement_no` VARCHAR(50) UNIQUE NOT NULL,
    `invoice_no` VARCHAR(50) NOT NULL,
    `new_invoice_no` VARCHAR(50) DEFAULT NULL,
    `reason` VARCHAR(100) NOT NULL,
    `remarks` TEXT,
    `less_amount` DECIMAL(10,2) DEFAULT 0,
    `total_amount` DECIMAL(10,2) DEFAULT 0,
    `created_by` VARCHAR(100),
    `branch` VARCHAR(100),
    `branch_code` VARCHAR(10),
    `status` VARCHAR(20) DEFAULT 'Pending',
    `created_at` DATETIME,
    `approved_by` VARCHAR(100),
    `approved_at` DATETIME,
    `disapproved_by` VARCHAR(100),
    `disapproved_at` DATETIME,
    `disapproval_reason` TEXT,
    INDEX `idx_replacement_no` (`replacement_no`),
    INDEX `idx_invoice_no` (`invoice_no`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_branch` (`branch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add missing columns if they don't exist
ALTER TABLE `replacements` 
ADD COLUMN IF NOT EXISTS `new_invoice_no` VARCHAR(50) DEFAULT NULL AFTER `invoice_no`,
ADD COLUMN IF NOT EXISTS `branch_code` VARCHAR(10) AFTER `branch`,
ADD COLUMN IF NOT EXISTS `approved_by` VARCHAR(100) AFTER `created_at`,
ADD COLUMN IF NOT EXISTS `approved_at` DATETIME AFTER `approved_by`,
ADD COLUMN IF NOT EXISTS `disapproved_by` VARCHAR(100) AFTER `approved_at`,
ADD COLUMN IF NOT EXISTS `disapproved_at` DATETIME AFTER `disapproved_by`,
ADD COLUMN IF NOT EXISTS `disapproval_reason` TEXT AFTER `disapproved_at`;

-- Create replacement_old_items table
CREATE TABLE IF NOT EXISTS `replacement_old_items` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `replacement_id` INT(11) NOT NULL,
    `item_description` VARCHAR(255),
    `imei` VARCHAR(50),
    `price` DECIMAL(10,2),
    INDEX `idx_replacement_id` (`replacement_id`),
    FOREIGN KEY (`replacement_id`) REFERENCES `replacements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create replacement_new_items table
CREATE TABLE IF NOT EXISTS `replacement_new_items` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `replacement_id` INT(11) NOT NULL,
    `item_code` VARCHAR(50),
    `item_description` VARCHAR(255),
    `imei` VARCHAR(50),
    `quantity` INT(11) DEFAULT 1,
    `price` DECIMAL(10,2),
    INDEX `idx_replacement_id` (`replacement_id`),
    FOREIGN KEY (`replacement_id`) REFERENCES `replacements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create replacement_approval_log table
CREATE TABLE IF NOT EXISTS `replacement_approval_log` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `replacement_id` INT(11) NOT NULL,
    `replacement_no` VARCHAR(50) NOT NULL,
    `original_invoice_no` VARCHAR(50),
    `new_invoice_no` VARCHAR(50),
    `branch` VARCHAR(255),
    `branch_code` VARCHAR(10),
    `reason` TEXT,
    `remarks` TEXT,
    `total_amount` DECIMAL(10,2) DEFAULT 0,
    `created_by` VARCHAR(100),
    `created_at` DATETIME,
    `approver` VARCHAR(100),
    `approval_date` DATETIME,
    `disapprover` VARCHAR(100),
    `disapproval_date` DATETIME,
    `disapproval_reason` TEXT,
    `status` VARCHAR(20) DEFAULT 'Pending',
    INDEX `idx_replacement_id` (`replacement_id`),
    INDEX `idx_replacement_no` (`replacement_no`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_branch` (`branch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
