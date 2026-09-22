# Fix: Inventory Table Issue

## Problem
The search for unclaimed freebies was failing with error:
```
Exception: Table 'motogam_management.inventory' doesn't exist
```

## Root Cause
The `search_freebies.php` was trying to query a table called `inventory`, but your system uses `stock_on_hand` instead.

## Solution
Changed all references from `inventory` table to `stock_on_hand` table.

### Query Changes

**Before** (WRONG - table doesn't exist):
```sql
SELECT i.id, i.item_code, i.description, i.branch, i.has_serial,
       COALESCE(inv.quantity, 0) as stock_quantity
FROM items i 
LEFT JOIN inventory inv ON i.item_code = inv.item_code 
    AND inv.branch_code = 'BRANCH_CODE'
WHERE ...
```

**After** (FIXED - using correct table):
```sql
SELECT i.id, i.item_code, i.description, i.branch, i.has_serial,
       COALESCE(SUM(soh.quantity), 0) as stock_quantity
FROM items i 
LEFT JOIN stock_on_hand soh ON i.item_code = soh.item_code 
    AND soh.branch = 'BRANCH_NAME'
    AND (LOWER(TRIM(soh.status)) = 'available' OR LOWER(TRIM(soh.status)) = 'active')
WHERE ...
GROUP BY i.id, i.item_code, i.description, i.branch, i.has_serial
```

### Key Differences

1. **Table Name**: `inventory` → `stock_on_hand`
2. **Table Alias**: `inv` → `soh`
3. **Branch Column**: `branch_code` → `branch` (uses branch name, not code)
4. **Status Filter**: Added `WHERE (status = 'available' OR status = 'active')`
5. **Aggregation**: Added `SUM(soh.quantity)` and `GROUP BY` since stock_on_hand can have multiple rows per item
6. **Branch Matching**: Uses `user_branch` (name) directly instead of `branch_code`

## Files Modified

1. **search_freebies.php** - Fixed SQL query to use stock_on_hand
2. **debug_search_freebies.php** - Updated debug script to check correct table

## Testing

**Test the fix**:
```
1. Navigate to: http://localhost/MOTOGAM/test_search_freebies.php
2. Enter a search term (e.g., "charger", "case")
3. Click "Test Search"
```

**Expected Result**:
- ✓ SUCCESS status
- List of non-serialized items with 0 stock
- Or "No items found" if all items have stock

**Debug if needed**:
```
Navigate to: http://localhost/MOTOGAM/debug_search_freebies.php?term=test
```

This will show:
- ✓ stock_on_hand table exists
- Table structure
- SQL query being executed
- Results found

## Stock Validation Logic

The query now correctly:
1. Joins `items` with `stock_on_hand` on `item_code`
2. Filters by user's branch name
3. Only counts stock with status 'available' or 'active'
4. Sums up quantities (handles multiple rows per item)
5. Only shows items where total stock = 0

## Status

✅ **FIXED** - The inventory table error is now resolved. The search should work properly with the `stock_on_hand` table.
