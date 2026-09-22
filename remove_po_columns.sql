-- Migration Script: Remove unused columns from purchase_orders table
-- Date: 2026-08-06
-- Description: Remove supplier_name, contact_number, address, and branch columns from purchase_orders

-- Check if columns exist and drop them
-- Note: MySQL will ignore the DROP COLUMN if it doesn't exist (with IF EXISTS in MySQL 5.7.6+)

-- Remove supplier_name column
ALTER TABLE purchase_orders 
DROP COLUMN IF EXISTS supplier_name;

-- Remove contact_number column
ALTER TABLE purchase_orders 
DROP COLUMN IF EXISTS contact_number;

-- Remove address column
ALTER TABLE purchase_orders 
DROP COLUMN IF EXISTS address;

-- Remove branch column (if it exists as a direct column)
ALTER TABLE purchase_orders 
DROP COLUMN IF EXISTS branch;

-- Verification: Show remaining columns
DESCRIBE purchase_orders;

-- Expected remaining columns:
-- id, po_number, supplier_company, terms, payment_due_date, remarks, po_date, 
-- total_items, total_qty, total_cost, status, created_by, created_by_branch, 
-- created_at, updated_at, etc.
