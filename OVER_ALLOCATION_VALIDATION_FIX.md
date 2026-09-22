# Over-Allocation Validation Fix

## Problem
The system was allowing users to add allocations to the session table even when "Quantity Left" showed negative values (e.g., -1). This happened because the validation in `setAllAllocations()` was not accounting for pending allocations already in the session table.

## Root Cause
In the original `setAllAllocations()` function:
```javascript
const qtyLeft = totalQty - allocatedQty - totalNewAllocation;
```

This calculation only checked:
- Total Quantity
- Already Allocated (from database)
- New Allocation being attempted

**It was missing**: Pending allocations already staged in the session table!

## Solution
Modified the `setAllAllocations()` function to:

1. **Calculate pending allocations first** from the session table
2. **Include pending quantity** in the validation formula
3. **Show detailed error messages** indicating which items are over-allocated and by how much

### Updated Validation Logic

```javascript
function setAllAllocations() {
    // ... branch selection validation ...
    
    // STEP 1: Calculate pending allocations from session table
    const container = document.getElementById('currentSessionAllocationsContainer');
    const sessionRows = container.querySelectorAll('.session-allocation-table tbody tr');
    const pendingByItem = {};

    sessionRows.forEach(row => {
        const familyCode = row.getAttribute('data-family-code');
        const qty = parseInt(row.getAttribute('data-quantity')) || 0;
        
        if (!pendingByItem[familyCode]) {
            pendingByItem[familyCode] = 0;
        }
        pendingByItem[familyCode] += qty;
    });

    // STEP 2: Validate each item
    rows.forEach(row => {
        const familyCode = row.getAttribute('data-family-code');
        const totalQty = parseInt(row.getAttribute('data-total-qty')) || 0;
        const allocatedQty = parseInt(row.getAttribute('data-allocated-qty')) || 0;
        const qty = parseInt(input.value) || 0;
        
        if (qty > 0) {
            const totalNewAllocation = qty * branches.length;
            const pendingQty = pendingByItem[familyCode] || 0;  // ← NEW
            
            // Calculate available quantity INCLUDING pending allocations
            const qtyLeft = totalQty - allocatedQty - pendingQty - totalNewAllocation;
            
            if (qtyLeft < 0) {
                hasOverAllocation = true;
                const shortage = Math.abs(qtyLeft);
                overAllocationMessages.push(`${familyCode}: Over by ${shortage} unit(s)`);
            }
            
            allocations.push({
                family_code: familyCode,
                quantity: qty,
                cost: cost
            });
        }
    });

    // STEP 3: Block allocation if over-allocated
    if (hasOverAllocation) {
        const errorMsg = `Cannot allocate! Insufficient quantity:\n\n${overAllocationMessages.join('\n')}\n\nYou selected ${branches.length} branch(es). Please reduce the quantities or remove some pending allocations.`;
        alert(errorMsg);
        return;  // ← STOPS THE ALLOCATION
    }
    
    // ... proceed with allocation ...
}
```

## New Formula
```
Available Quantity = Total Qty - Already Allocated (DB) - Pending (Session) - (Input × Branches)
```

If `Available Quantity < 0`, the allocation is **blocked**.

## Changes Made

### File: `purchaseorder-details.php` (lines ~2270-2360)

**Added:**
1. Calculation of pending allocations from session table before validation
2. Inclusion of `pendingQty` in the quantity left calculation
3. Detailed error messages showing which items are over-allocated
4. Helpful message suggesting to reduce quantities or remove pending allocations

**Validation Flow:**
1. ✅ Check if branch selected
2. ✅ Read all pending allocations from session table
3. ✅ For each item with quantity > 0:
   - Calculate: Total - Allocated - Pending - (New × Branches)
   - If negative, add to error list
4. ✅ If any over-allocations found, show error and BLOCK
5. ✅ If all valid, proceed with adding to session table

## User Experience Improvements

### Before (Buggy):
- User could keep clicking "Set" even when Quantity Left = -1
- Invalid allocations were added to session table
- User might not notice until final save or review

### After (Fixed):
- User clicks "Set" with insufficient quantity
- System shows clear error message:
  ```
  Cannot allocate! Insufficient quantity:
  
  IPHONE 15 128GB: Over by 1 unit(s)
  
  You selected 1 branch(es). Please reduce the quantities 
  or remove some pending allocations.
  ```
- Allocation is **blocked** - nothing added to session table
- "Quantity Left" remains accurate

## Benefits
1. **Prevents data integrity issues** - no over-allocation possible
2. **Clear feedback** - users know exactly what's wrong and by how much
3. **Actionable guidance** - suggests reducing quantities or removing pending items
4. **Consistent with UI indicators** - respects red negative values in Quantity Left column

## Testing Scenarios

✅ **Scenario 1: Simple over-allocation**
- Total: 2, Allocated: 0, Try to allocate: 3
- Expected: Blocked with "Over by 1 unit(s)"

✅ **Scenario 2: Pending + new allocation**
- Total: 2, Allocated: 0, Pending: 1, Try to allocate: 2
- Expected: Blocked with "Over by 1 unit(s)"

✅ **Scenario 3: Multiple branches**
- Total: 2, Select 2 branches, Try to allocate: 2 each (4 total)
- Expected: Blocked with "Over by 2 unit(s)"

✅ **Scenario 4: Valid allocation at limit**
- Total: 2, Allocated: 0, Pending: 0, Allocate: 2
- Expected: Success, Quantity Left = 0

✅ **Scenario 5: After removing pending allocation**
- Total: 2, Pending: 2, User removes 1 pending, Try to allocate: 1
- Expected: Success, Quantity Left = 0
