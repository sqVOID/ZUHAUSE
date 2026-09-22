# Void Sale - Unclaimed & Claimed Freebies Fix

## Problem
When voiding a sale invoice, the system was:
1. ❌ NOT removing unclaimed freebies breakdowns from the database
2. ❌ NOT restoring claimed freebies back to stock

This resulted in orphaned freebie records that remained visible even after the sale was voided.

## Solution Implemented

### Changes Made to `void_sale.php`

#### 1. **Delete Unclaimed Freebies**
- When a sale is voided, all `unclaimed_freebies` records with matching `invoice_number` are deleted
- This removes any freebie items that were never claimed

#### 2. **Restore Claimed Freebies to Stock**
- Queries the `claimed_items` table for all items claimed against the voided invoice
- For each claimed item:
  - Retrieves item metadata (group, department, brand, family_code, has_serial)
  - Checks if a stock record exists in `stock_on_hand` for that item and branch
  - If stock exists: increments the quantity
  - If stock doesn't exist: creates a new stock record with `dr_number = 'VOID-CLAIMED-RESTORE'`
- Deletes all `claimed_items` records for the voided invoice

#### 3. **Error Handling Improvements**
- Added proper error logging to `void_sale_errors.log`
- Added database connection verification
- Added explicit error checks with meaningful exceptions
- All operations wrapped in a transaction (rollback on any failure)

#### 4. **Bug Fixes**
- Fixed column name: `invoice_number` → `invoice_no` (sales_entry table uses `invoice_no`)
- Optimized query to fetch invoice number in the initial sales_entry lookup

## Flow Diagram

```
VOID SALE
    ↓
1. Mark sale as 'voided' in sales_entry
    ↓
2. Find all claimed_items for this invoice_no
    ↓
3. For each claimed item:
    - Get item metadata from items table
    - Check if stock exists in stock_on_hand
    - Restore quantity to stock (increment or insert)
    ↓
4. Delete all claimed_items records
    ↓
5. Delete all unclaimed_freebies records
    ↓
6. Restore sold items to stock (original logic)
    ↓
7. COMMIT TRANSACTION
```

## Database Tables Affected

### Read Operations
- `sales_entry` - Get invoice details and verify sale exists
- `branches` - Get branch name from branch code
- `sales_entry_items` - Get items sold in the invoice
- `items` - Get item metadata for stock restoration
- `claimed_items` - Get claimed freebies for restoration
- `stock_on_hand` - Check existing stock levels

### Write Operations
- `sales_entry` - Update status to 'voided'
- `stock_on_hand` - Restore sold items and claimed freebies
- `claimed_items` - DELETE all records for voided invoice
- `unclaimed_freebies` - DELETE all records for voided invoice

## Testing Checklist

- [x] Void a sale with unclaimed freebies → freebies disappear from reports
- [x] Void a sale with claimed freebies → items restored to stock_on_hand
- [x] Void a sale with both claimed and unclaimed → both handled correctly
- [x] Error handling → transaction rollback on failure
- [x] Verify stock quantities are correct after voiding

## Success Message
When voiding completes successfully:
```
"Sale voided successfully. Stock and claimed freebies have been restored."
```

## Error Logging
All errors are logged to: `void_sale_errors.log`

## Date Implemented
August 4, 2026
