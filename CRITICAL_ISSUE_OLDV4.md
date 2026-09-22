# 🚨 CRITICAL ISSUE FOUND - OldV4 Pre-Orders Table Mismatch

**Date:** July 21, 2026  
**Severity:** ❌ **CRITICAL - DATABASE INCOMPATIBLE**  
**Impact:** Application will fail when accessing sales reports with pre-orders

---

## THE PROBLEM

The `motogam_management_oldv4.sql` file has a **DIFFERENT** `preorders` table structure compared to the original database. This causes the error:

```
Error: Error fetching sales data: Unknown column 'p.first_name' in 'field list'
```

---

## DETAILED COMPARISON

### ❌ OldV4 `preorders` Table (28 columns - WRONG STRUCTURE)

```sql
CREATE TABLE `preorders` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `branch` varchar(100) NOT NULL,                    -- ❌ Should be branch_code
  `customer_name` varchar(255) NOT NULL,             -- ❌ Should be first_name + last_name
  `customer_contact` varchar(20) DEFAULT NULL,       -- ❌ Should be contact_no
  `customer_address` text DEFAULT NULL,              -- ❌ Should be address
  `down_payment` decimal(10,2) DEFAULT 0.00,         -- ❌ Not in original
  `remaining_balance` decimal(10,2) DEFAULT 0.00,    -- ❌ Not in original
  `total_amount` decimal(10,2) DEFAULT 0.00,         -- ✅ Exists but different
  `status` enum('pending','completed','cancelled'),  -- ❌ Different values
  `payment_method` varchar(50) DEFAULT NULL,         -- ❌ Not in original
  `payment_mode` varchar(50) DEFAULT NULL,           -- ❌ Not in original
  `dealer_code` varchar(50) DEFAULT NULL,            -- ❌ Not in original
  `prepared_by` varchar(100) DEFAULT NULL,           -- ❌ Not in original
  `preorder_date` date DEFAULT NULL,                 -- ❌ Not in original
  `target_release_date` date DEFAULT NULL,           -- ❌ Not in original
  `actual_release_date` date DEFAULT NULL,           -- ❌ Not in original
  `remarks` text DEFAULT NULL,                       -- ✅ Exists
  `created_at` timestamp NOT NULL,                   -- ✅ Exists
  `updated_at` timestamp NOT NULL,                   -- ✅ Exists
  `claimed_date` datetime DEFAULT NULL,              -- ❌ Not in original
  `claimed_by` varchar(255) DEFAULT NULL,            -- ❌ Not in original
  `booklet_no` varchar(50) DEFAULT NULL,             -- ❌ Not in original
  `freebies` text DEFAULT NULL,                      -- ❌ Not in original
  `promoter` varchar(255) DEFAULT NULL,              -- ❌ Not in original
  `terminal_id` varchar(255) DEFAULT NULL,           -- ❌ Not in original
  `promo_items` text DEFAULT NULL,                   -- ❌ Not in original
  `assisted_by` varchar(255) DEFAULT NULL            -- ✅ Exists
) ENGINE=InnoDB;
```

---

### ✅ Original `preorders` Table (19 columns - CORRECT STRUCTURE)

```sql
CREATE TABLE `preorders` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,               -- ❌ MISSING in OldV4
  `last_name` varchar(100) NOT NULL,                -- ❌ MISSING in OldV4
  `address` varchar(255) NOT NULL,                  -- ❌ MISSING in OldV4
  `contact_no` varchar(50) NOT NULL,                -- ❌ MISSING in OldV4
  `email` varchar(100) DEFAULT '',                  -- ❌ MISSING in OldV4
  `assisted_by` varchar(100) NOT NULL,              -- ✅ Exists
  `remarks` text DEFAULT NULL,                      -- ✅ Exists
  `total_qty` int(11) NOT NULL DEFAULT 0,           -- ❌ MISSING in OldV4
  `discount` decimal(12,2) DEFAULT 0.00,            -- ❌ MISSING in OldV4
  `total_amount` decimal(12,2) NOT NULL,            -- ✅ Exists
  `payment_data` text DEFAULT NULL,                 -- ❌ MISSING in OldV4 (JSON)
  `branch_code` varchar(10) NOT NULL,               -- ❌ MISSING in OldV4
  `encoder` varchar(150) DEFAULT NULL,              -- ❌ MISSING in OldV4
  `status` varchar(50) DEFAULT 'pending',           -- Different type
  `claimed_invoice_no` varchar(50) DEFAULT NULL,    -- ❌ MISSING in OldV4
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB;
```

---

## MISSING COLUMNS IN OLDV4

| Column | Data Type | Purpose |
|--------|-----------|---------|
| `first_name` | varchar(100) | ❌ Customer first name |
| `last_name` | varchar(100) | ❌ Customer last name |
| `address` | varchar(255) | ❌ Customer address |
| `contact_no` | varchar(50) | ❌ Customer contact |
| `email` | varchar(100) | ❌ Customer email |
| `total_qty` | int(11) | ❌ Total quantity |
| `discount` | decimal(12,2) | ❌ Discount amount |
| `payment_data` | text | ❌ Payment information (JSON) |
| `branch_code` | varchar(10) | ❌ Branch code (uses `branch` instead) |
| `encoder` | varchar(150) | ❌ Who encoded the pre-order |
| `claimed_invoice_no` | varchar(50) | ❌ Invoice when claimed |

---

## EXTRA COLUMNS IN OLDV4 (NOT IN ORIGINAL)

| Column | Data Type | Not Used By App |
|--------|-----------|-----------------|
| `customer_name` | varchar(255) | ❌ Should use first_name + last_name |
| `customer_contact` | varchar(20) | ❌ Should use contact_no |
| `customer_address` | text | ❌ Should use address |
| `down_payment` | decimal(10,2) | ❌ Not in original |
| `remaining_balance` | decimal(10,2) | ❌ Not in original |
| `payment_method` | varchar(50) | ❌ Should use payment_data JSON |
| `payment_mode` | varchar(50) | ❌ Should use payment_data JSON |
| `dealer_code` | varchar(50) | ❌ Not used |
| `prepared_by` | varchar(100) | ❌ Should use encoder |
| `preorder_date` | date | ❌ Should use created_at |
| `target_release_date` | date | ❌ Not used |
| `actual_release_date` | date | ❌ Not used |
| `claimed_date` | datetime | ❌ Not in original |
| `claimed_by` | varchar(255) | ❌ Not in original |
| `booklet_no` | varchar(50) | ❌ Not used |
| `freebies` | text | ❌ Not used |
| `promoter` | varchar(255) | ❌ Not used |
| `terminal_id` | varchar(255) | ❌ Not used |
| `promo_items` | text | ❌ Not used |

---

## WHY THIS BREAKS THE APPLICATION

The `fetch_sales_report.php` file queries:

```php
SELECT
    p.id, p.invoice_no, p.first_name, p.last_name,
    p.assisted_by, p.remarks, p.total_qty, p.total_amount,
    p.discount, p.payment_data, p.encoder, p.branch_code,
    p.created_at, p.status
FROM preorders p
```

**OldV4 is missing these columns:**
- ❌ `first_name` → Error
- ❌ `last_name` → Error  
- ❌ `total_qty` → Error
- ❌ `discount` → Error
- ❌ `payment_data` → Error
- ❌ `encoder` → Error
- ❌ `branch_code` → Error

---

## ROOT CAUSE

It appears that **oldv4 was created from a different version** of the database or was based on an **older schema design** that was later changed. The structure suggests it was designed for a different pre-order workflow.

---

## SOLUTION

### ⚠️ DO NOT USE motogam_management_oldv4.sql

**Use instead:**
- ✅ `motogam_management_orig.sql` - The correct, current schema
- ✅ `motogam_management_oldv3.sql` - IF it matches original (needs verification)

### If You Must Fix OldV4:

You would need to:

1. **Drop and recreate the preorders table:**
```sql
DROP TABLE IF EXISTS `preorders`;

CREATE TABLE `preorders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `contact_no` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT '',
  `assisted_by` varchar(100) NOT NULL,
  `remarks` text DEFAULT NULL,
  `total_qty` int(11) NOT NULL DEFAULT 0,
  `discount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_data` text DEFAULT NULL,
  `branch_code` varchar(10) NOT NULL,
  `encoder` varchar(150) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `claimed_invoice_no` varchar(50) DEFAULT NULL COMMENT 'Invoice number when claimed',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

2. **Verify preorder_items table matches as well**

---

## RECOMMENDATION

❌ **DO NOT USE `motogam_management_oldv4.sql`** for production

✅ **USE `motogam_management_orig.sql`** - This is the correct schema

The oldv4 file appears to be from a **different/incompatible version** of the application.

---

## VERIFICATION NEEDED

We need to check if `oldv3` has the same problem:

```sql
DESCRIBE preorders;
```

If oldv3 also has the wrong structure, then only the `original` file is correct.

---

**Status:** ❌ **OLDV4 IS INCOMPATIBLE**  
**Action:** Use `motogam_management_orig.sql` instead  
**Priority:** CRITICAL - Do not deploy oldv4
