# Purchase Order Branch Display Fix

## Issue Description

When viewing a Purchase Order in `viewpurchaseorder.php`, the "Branch" field in the status bar was showing the **branch that CREATED** the PO instead of the **branches where items were ALLOCATED**.

### Example Problem:
- PO created by: MOTOGAM LIPA - MOTOLPA  
- Items allocated to: MOTOGAM CANDELARIA
- Branch field displayed: **MOTOGAM LIPA - MOTOLPA** ❌ (incorrect)
- Branch field should display: **MOTOGAM CANDELARIA** ✓ (correct)

## Root Cause

The status bar was using the `created_by_branch` field from the `purchase_orders` table, which stores which branch created the PO, not where items were allocated.

## Solution Implemented

### File Modified: `viewpurchaseorder.php`

**Location:** Status Bar Section (around line 1355)

**Changed From:**
```php
<div class="status-group">
    <span class="po-number-label">Branch</span>
    <span class="po-number-value">
        <?php
        if (!empty($created_branch_name)) {
            echo htmlspecialchars($created_branch_name);
            if (!empty($created_by_branch) && $created_by_branch !== 'ALL') {
                echo ' - ' . htmlspecialchars($created_by_branch);
            }
        } else {
            echo 'Unknown Branch';
        }
        ?>
    </span>
</div>
```

**Changed To:**
```php
<div class="status-group">
    <span class="po-number-label">Branch</span>
    <span class="po-number-value">
        <?php
        // Get allocated branches instead of created_by_branch
        $allocated_branches_query = $conn->query("
            SELECT DISTINCT branch_name 
            FROM purchase_order_allocations 
            WHERE po_id = {$po_id}
            ORDER BY branch_name ASC
        ");
        
        $allocated_branches = [];
        if ($allocated_branches_query && $allocated_branches_query->num_rows > 0) {
            while ($branch_row = $allocated_branches_query->fetch_assoc()) {
                $allocated_branches[] = $branch_row['branch_name'];
            }
        }
        
        if (!empty($allocated_branches)) {
            echo htmlspecialchars(implode(', ', $allocated_branches));
        } else {
            echo 'No Allocations Yet';
        }
        ?>
    </span>
</div>
```

## What This Fix Does

1. **Queries the allocation table** to find all branches that have allocations for this PO
2. **Shows multiple branches** if items are allocated to more than one branch (comma-separated)
3. **Shows "No Allocations Yet"** if no allocations exist (instead of showing the creator branch)
4. **Sorts alphabetically** for consistent display

## Examples

### Single Branch Allocation
- Allocated to: MOTOGAM CANDELARIA
- Display: **MOTOGAM CANDELARIA**

### Multiple Branch Allocations
- Allocated to: MOTOGAM CANDELARIA, MOTOGAM LIPA - MOTOLPA
- Display: **MOTOGAM CANDELARIA, MOTOGAM LIPA - MOTOLPA**

### No Allocations
- Allocated to: (none)
- Display: **No Allocations Yet**

## Benefits

✅ **Accurate Information:** Shows where items will be/are being received  
✅ **Supports Multiple Branches:** Displays all allocated branches  
✅ **Clear When Unallocated:** Shows "No Allocations Yet" message  
✅ **Consistent with Workflow:** Aligns with the allocation-before-receiving requirement

## Related Changes

This fix works in conjunction with the previously implemented changes:
- **`purchaseorderreceive.php`**: Only shows POs with allocations
- **`purchaseorder-details.php`**: Where allocations are made

Together, these changes ensure a complete and accurate allocation workflow.

## Testing

To verify the fix works correctly:

1. ✓ View a PO that was allocated to MOTOGAM CANDELARIA
2. ✓ Branch field should show "MOTOGAM CANDELARIA" (not the creating branch)
3. ✓ View a PO allocated to multiple branches
4. ✓ Branch field should show all allocated branches separated by commas
5. ✓ View a PO with no allocations
6. ✓ Branch field should show "No Allocations Yet"

## Database Query

The fix uses this SQL query:
```sql
SELECT DISTINCT branch_name 
FROM purchase_order_allocations 
WHERE po_id = [PO_ID]
ORDER BY branch_name ASC
```

This efficiently retrieves all unique branch names for the PO's allocations.

## Performance Note

- Query is very efficient (single table, indexed foreign key)
- Executes only once per page load
- DISTINCT ensures no duplicate branch names
- ORDER BY provides consistent alphabetical sorting

---

**Date Fixed:** August 7, 2026  
**Fixed By:** [Your Name]  
**Tested:** ✓ Verified with actual allocation data
