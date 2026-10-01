-- Add missing columns to replacements table

-- Add new_invoice_no column
ALTER TABLE `replacements` 
ADD COLUMN `new_invoice_no` VARCHAR(50) DEFAULT NULL AFTER `invoice_no`;

-- Add branch_code column
ALTER TABLE `replacements` 
ADD COLUMN `branch_code` VARCHAR(10) AFTER `branch`;

-- Add disapproval_reason column
ALTER TABLE `replacements` 
ADD COLUMN `disapproval_reason` TEXT AFTER `disapproved_at`;

-- Verify all columns exist
SHOW COLUMNS FROM `replacements`;
