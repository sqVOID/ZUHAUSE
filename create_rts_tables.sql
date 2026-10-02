-- ====================================================
-- Return to Supplier (RTS) Database Tables
-- ====================================================
-- This script creates all necessary tables for the RTS system
-- Run this in phpMyAdmin or MySQL client if tables don't auto-create
-- ====================================================

-- Main Return to Supplier Table
CREATE TABLE IF NOT EXISTS `return_to_supplier` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `rts_number` VARCHAR(50) NOT NULL,
    `rts_date` VARCHAR(20),
    `reference_number` VARCHAR(100),
    `branch_from` VARCHAR(10),
    `branch_name` VARCHAR(255),
    `delivery_to` VARCHAR(255),
    `remarks` TEXT,
    `created_by` VARCHAR(100),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_rts_number` (`rts_number`),
    INDEX `idx_branch` (`branch_from`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Return to Supplier Items Table
CREATE TABLE IF NOT EXISTS `return_to_supplier_items` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `rts_id` INT(11) NOT NULL,
    `rts_number` VARCHAR(50),
    `item_code` VARCHAR(100),
    `item_description` TEXT,
    `imei` VARCHAR(100),
    `quantity` INT(11) DEFAULT 0,
    `cost` DECIMAL(15,2) DEFAULT 0.00,
    `reason` TEXT,
    INDEX `idx_rts_id` (`rts_id`),
    INDEX `idx_rts_number` (`rts_number`),
    INDEX `idx_item_code` (`item_code`),
    INDEX `idx_imei` (`imei`),
    FOREIGN KEY (`rts_id`) REFERENCES `return_to_supplier`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RTS Approval Log Table
CREATE TABLE IF NOT EXISTS `rts_approval_log` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `rts_id` INT(11) NOT NULL,
    `rts_number` VARCHAR(50) NOT NULL,
    `reference_number` VARCHAR(100),
    `branch_from` VARCHAR(255),
    `branch_code` VARCHAR(10),
    `delivery_to` VARCHAR(255),
    `reason` TEXT,
    `remarks` TEXT,
    `total_items` INT(11) DEFAULT 0,
    `created_by` VARCHAR(100),
    `created_at` DATETIME,
    `approver` VARCHAR(100),
    `approval_date` DATETIME,
    `disapprover` VARCHAR(100),
    `disapproval_date` DATETIME,
    `status` VARCHAR(20) DEFAULT 'Pending',
    INDEX `idx_rts_id` (`rts_id`),
    INDEX `idx_rts_number` (`rts_number`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_branch` (`branch_from`),
    FOREIGN KEY (`rts_id`) REFERENCES `return_to_supplier`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================
-- Table Descriptions
-- ====================================================
-- return_to_supplier: Main RTS records with header information
-- return_to_supplier_items: Individual items being returned
-- rts_approval_log: Tracks approval/disapproval workflow
-- ====================================================
