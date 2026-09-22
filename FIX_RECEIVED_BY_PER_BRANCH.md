# Fix: Received By Per Branch

## Problem
The "Received By" column in the PO Invoice modal was showing the same person for all branches instead of showing who actually received items for each specific branch.

**Example:**
- MOTOCOMP LIPA was received by "lipa lipa" ✅
- MOTOCOMP CANDELARIA was received by "Cande Laria" but showing "lipa lipa" ❌

## Root Cause
The `purchase_order_allocations` table didn't have columns to track WHO received items and WHEN they were received for each branch allocation.

## Solution

### Step 1: Run Database Migration ⚠️ REQUIRED
Run this file in your browser to add the required columns:
```
http://localhost/ZUHAUSE/add_received_by_to_allocations.php
```

This will add:
- `received_by` column - stores the username of who received the items for this branch
- `received_at` column - stores the timestamp of when items were received

### Step 2: Files Updated ✅ COMPLETED

1. **add_received_by_to_allocations.php** (NEW)
   - Migration script to add columns to purchase_order_allocations table

2. **update_po_status.php** (UPDATED)
   - When items are received, now saves `received_by` (username) and `received_at` (timestamp) to the allocation
   - Updated in 2 places: for "Received" status and "Incomplete" status

3. **get_po_branch_details.php** (UPDATED)
   - Changed to read `received_by` from allocations table instead of purchase_orders table
   - Now joins with accounts table using `poa.received_by` (allocation level) instead of `po.received_by` (PO level)
   - Shows correct "Received By" name for each branch

## How It Works Now

### Before:
```
purchase_orders table had:
  - received_by: "lipa" (global for all branches)
  
Modal showed:
  - MOTOCOMP LIPA: Received By = lipa lipa
  - MOTOCOMP CANDELARIA: Received By = lipa lipa (WRONG!)
```

### After:
```
purchase_order_allocations table now has:
  - For LIPA branch: received_by = "lipa", received_at = "2025-01-10 14:30:00"
  - For CANDELARIA branch: received_by = "cande", received_at = "2025-01-10 15:45:00"
  
Modal shows:
  - MOTOCOMP LIPA: Received By = lipa lipa ✅
  - MOTOCOMP CANDELARIA: Received By = Cande Laria ✅
```

## Testing Steps

1. **Run the migration** (Step 1 above)

2. **Test with new PO:**
   - Create a new PO with allocations to multiple branches
   - Receive items at LIPA branch (login as lipa user)
   - Receive items at CANDELARIA branch (login as cande user)
   - View the PO modal - should show correct "Received By" for each branch

3. **Existing POs:**
   - Existing POs won't have `received_by` data until items are received again
   - When you receive more items or update status, the system will save the username

## Database Schema Changes

```sql
ALTER TABLE purchase_order_allocations 
ADD COLUMN received_by VARCHAR(150) DEFAULT NULL;

ALTER TABLE purchase_order_allocations 
ADD COLUMN received_at DATETIME DEFAULT NULL;
```

## Notes
- The `received_by` column stores the **username** (e.g., "lipa", "cande")
- The system automatically looks up the full name from the `accounts` table for display
- Each branch allocation can now track independently who received the items and when
- This provides proper audit trail for branch-level receiving

## Rollback (if needed)
If you need to rollback:
```sql
ALTER TABLE purchase_order_allocations DROP COLUMN received_by;
ALTER TABLE purchase_order_allocations DROP COLUMN received_at;
```

Then revert the changes to:
- update_po_status.php
- get_po_branch_details.php
