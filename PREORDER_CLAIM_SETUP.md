# Pre-order Claim Setup Guide

## Problem
The error "Unknown column 'amount_paid' in 'field list'" occurs because the `preorder_items` table is missing columns needed to track claimed items.

## Solution: Extend preorder_items table

Instead of creating a new table, we're adding columns to the existing `preorder_items` table to track claim details.

### New Columns Added:
1. **item_code** - Actual item code when claimed
2. **imei** - Serial/IMEI number of claimed item
3. **total_payment** - Total amount for this item
4. **amount_paid** - Amount already paid in pre-order
5. **payment_method** - Payment method used
6. **status** - pending/paid/partial
7. **dr_number** - DR number from stock
8. **claimed_at** - Timestamp when item was claimed

## Setup Instructions

### Step 1: Run the Migration
Visit this URL in your browser:
```
http://localhost/MOTOGAM/add_preorder_items_columns.php
```

This will:
- Add all missing columns to `preorder_items` table
- Create indexes for better performance
- Show you the updated table structure

### Step 2: Verify
The script will show:
- ✓ Success count (columns added)
- ⊘ Skipped count (columns that already exist)
- ✗ Error count (any issues)

### Step 3: Test
After successful migration:
1. Go to Pre-order page and create a test pre-order
2. Go to Claim Pre-order page
3. Search for the pre-order
4. Add items with IMEI/serial numbers
5. Complete payment
6. Save the claim

## How It Works

### Before (Pre-order):
```
preorder_items
├── preorder_id
├── item_description (family code like "HONDA CLICK 160")
├── quantity
└── price
```

### After (Claimed):
```
preorder_items
├── preorder_id
├── item_description (still "HONDA CLICK 160")
├── quantity
├── price
├── item_code (actual: "HC160-WHT-2024")
├── imei (specific: "123456789012345")
├── total_payment (₱16,900.00)
├── amount_paid (₱900.00)
├── payment_method ("cash")
├── status ("paid")
├── dr_number ("DR-2024-001")
└── claimed_at (2024-12-01 10:30:00)
```

## Benefits

1. **Single table** - All pre-order data in one place
2. **Complete history** - Shows original order and claim details
3. **Payment tracking** - Tracks partial and full payments
4. **Stock tracking** - Links to DR numbers
5. **Status tracking** - pending → claimed → paid

## Files Modified

1. `add_preorder_items_columns.php` - Migration script (NEW)
2. `save_claim_preorder.php` - Updated to use extended preorder_items
3. `preorderreport.php` - Added "Claimed" status filter
4. `claimpreorder.php` - Enhanced with balance tracking

## Rollback (if needed)

If you need to remove the added columns:
```sql
ALTER TABLE preorder_items 
DROP COLUMN item_code,
DROP COLUMN imei,
DROP COLUMN total_payment,
DROP COLUMN amount_paid,
DROP COLUMN payment_method,
DROP COLUMN status,
DROP COLUMN dr_number,
DROP COLUMN claimed_at;
```

## Support

If you encounter any issues:
1. Check the migration script output for errors
2. Verify your MySQL user has ALTER TABLE permissions
3. Backup your database before running migrations
