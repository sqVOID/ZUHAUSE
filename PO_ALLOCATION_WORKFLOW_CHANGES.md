# Purchase Order Allocation Workflow Changes

## Overview
This document describes the changes made to the Purchase Order system to ensure that POs only appear in the "Purchase Order Receive" page AFTER items have been allocated to branches.

## Problem Statement
Previously, when a purchase order was created, it would immediately appear in `purchaseorderreceive.php` with a "Pending" status, even though no items had been allocated to any branches yet. This caused confusion because users would see POs ready for receiving before the allocation step was completed.

## Solution Implemented

### Workflow Changes

#### Before (Old Workflow)
1. Create PO in `createpurchaseorder.php` → Status = "Pending"
2. PO **immediately appears** in `purchaseorderreceive.php` 
3. User must allocate items to branches in `purchaseorder-details.php`
4. User can receive items at the allocated branch

#### After (New Workflow)
1. Create PO in `createpurchaseorder.php` → Status = "Pending"
2. PO does **NOT appear** in `purchaseorderreceive.php` yet
3. User **must allocate** items to branches in `purchaseorder-details.php`
4. **Only after allocation**, PO appears in `purchaseorderreceive.php` with "Pending" status
5. User can now receive items at the allocated branch

### Technical Changes

#### File Modified: `purchaseorderreceive.php`

**Location:** Lines 117-143 (SQL Query Section)

**Change Made:**
Added a condition to only show Purchase Orders that have branch allocations in the `purchase_order_allocations` table.

```php
// IMPORTANT: Only show POs that have branch allocations (allocation requirement)
// A PO must be allocated to branches before it can be received
$where_conditions[] = "EXISTS (
    SELECT 1 FROM purchase_order_allocations poa 
    WHERE poa.po_id = po.id
)";
```

**What This Does:**
- Uses an `EXISTS` subquery to check if the PO has ANY allocations in the `purchase_order_allocations` table
- If no allocations exist, the PO will not appear in the receive list
- This ensures users complete the allocation step before attempting to receive items

#### Additional Change: User-Friendly Message

**Location:** Lines 975-987 (No Results Message)

**Change Made:**
Updated the "no data" message to inform users about the allocation requirement:

```php
echo ".<br><small style='color: #999; font-size: 12px; margin-top: 8px; display: block;'>
Note: Only purchase orders with branch allocations are shown here. 
Please allocate items to branches first in Purchase Order Details.
</small>";
```

## User Impact

### For Regular Users
- **Before:** Could see unallocated POs and might attempt to receive them (causing confusion)
- **After:** Only see POs that are ready to be received (already allocated to branches)

### For Admins
- **Before:** Had to train users to check allocation status before receiving
- **After:** System automatically enforces the allocation requirement

## Database Dependencies

This change relies on the following database structure:

### Required Tables
1. **purchase_orders** - Main PO table
2. **purchase_order_allocations** - Stores which items are allocated to which branches

### Required Columns in purchase_order_allocations
- `po_id` - Foreign key to purchase_orders
- `branch_name` or `branch_code` - Which branch the allocation is for
- `family_code` - Which item is allocated
- `quantity` - How many units are allocated

## Testing Checklist

To verify the changes work correctly:

- [x] Create a new PO in `createpurchaseorder.php`
- [x] Verify it does NOT appear in `purchaseorderreceive.php` yet
- [x] Go to `purchaseorder-details.php` and allocate items to at least one branch
- [x] Verify the PO NOW appears in `purchaseorderreceive.php`
- [x] Verify the "no data" message shows the allocation note when no allocated POs exist
- [x] Test with different status filters (Pending, Incomplete, Received, All)

## Edge Cases Handled

1. **PO with partial allocation:** Will appear in receive list (allocation exists)
2. **PO with allocation later removed:** Will disappear from receive list (no allocation)
3. **Multiple branches allocated:** Will appear in receive list for all allocated branches
4. **Search and filter:** Allocation check works with all existing search/filter functionality

## Related Files

### Files Modified
- `purchaseorderreceive.php` - Added allocation requirement check

### Files Referenced (No Changes)
- `createpurchaseorder.php` - Creates POs (unchanged)
- `purchaseorder-details.php` - Handles allocation (unchanged)
- `save_purchase_order_simple.php` - Saves new POs (unchanged)
- `update_po_status.php` - Updates PO status (unchanged)

## Benefits

1. **Enforced Workflow:** System now enforces the allocation step before receiving
2. **Less Confusion:** Users only see POs that are ready for the next step
3. **Data Integrity:** Prevents receiving items before allocation is complete
4. **Better UX:** Clear message when no allocated POs are available
5. **Maintains Flexibility:** Still allows all status filters to work as before

## Rollback Instructions

If you need to rollback this change:

1. Open `purchaseorderreceive.php`
2. Find line ~131 and remove these lines:
```php
// IMPORTANT: Only show POs that have branch allocations (allocation requirement)
// A PO must be allocated to branches before it can be received
$where_conditions[] = "EXISTS (
    SELECT 1 FROM purchase_order_allocations poa 
    WHERE poa.po_id = po.id
)";
```

3. Find line ~983 and remove the allocation note from the no-data message

## Support Notes

- **Query Performance:** The EXISTS subquery is efficient as it stops after finding the first allocation
- **Index Recommendation:** Consider adding an index on `purchase_order_allocations(po_id)` for better performance
- **Backward Compatibility:** Existing POs without allocations will not appear until allocated

## Date Implemented
**August 7, 2026**

## Developer Notes
- The allocation check is applied BEFORE any status filtering
- This ensures the rule applies to ALL status types (Pending, Incomplete, Received, etc.)
- The check works with branch filtering for Sub-admin users
- Super-Admin can see all allocated POs across all branches
