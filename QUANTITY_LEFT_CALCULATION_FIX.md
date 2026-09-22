# Quantity Left Calculation Fix

## Issue

When users clicked "Set" to stage allocations, the "Quantity Left" column in Item Information table was not updating to reflect pending (staged) allocations. It only showed database-committed allocations.

**Example Problem:**
- Total Quantity: 2
- Already Allocated (in DB): 0
- Pending in Session: 2 (1 to INFANTA + 1 to HEAD OFFICE)
- **Displayed Quantity Left: 2** ❌ (should be 0)

---

## Root Cause

The `updateAllQuantityLeft()` function was only calculating:
```javascript
qtyLeft = totalQty - allocatedQty - inputQty
```

It was **NOT** including pending allocations from the Current Session Allocations table.

---

## Solution

Modified `updateAllQuantityLeft()` to:
1. Read pending allocations from session table
2. Sum pending quantities by family code
3. Include pending in calculation:
   ```javascript
   qtyLeft = totalQty - allocatedQty - pendingQty - inputQty
   ```

### New Calculation Formula

```
Quantity Left = Total Quantity 
              - Already Allocated (database)
              - Pending (session table)
              - Current Input (form field)
```

---

## Code Changes

### Before
```javascript
function updateAllQuantityLeft() {
    const allocationRows = document.querySelectorAll('#branchAllocationBody tr');
    
    allocationRows.forEach(allocationRow => {
        const familyCode = allocationRow.getAttribute('data-family-code');
        const totalQty = parseInt(allocationRow.getAttribute('data-total-qty')) || 0;
        const allocatedQty = parseInt(allocationRow.getAttribute('data-allocated-qty')) || 0;
        const input = allocationRow.querySelector('.allocation-qty-input');
        const inputQty = parseInt(input.value) || 0;
        
        // MISSING: pending allocations
        const qtyLeft = totalQty - allocatedQty - inputQty;
        
        // Update display...
    });
}
```

### After
```javascript
function updateAllQuantityLeft() {
    // 1. Get pending allocations from session table
    const sessionRows = document.querySelectorAll('#currentSessionAllocationsBody tr');
    const pendingByItem = {};

    sessionRows.forEach(row => {
        const cells = row.cells;
        if (cells.length >= 3) {
            const familyCode = cells[1].textContent.trim();
            const qty = parseInt(cells[2].textContent.trim()) || 0;
            
            if (!pendingByItem[familyCode]) {
                pendingByItem[familyCode] = 0;
            }
            pendingByItem[familyCode] += qty;
        }
    });

    // 2. Now update each row INCLUDING pending
    const allocationRows = document.querySelectorAll('#branchAllocationBody tr');
    
    allocationRows.forEach(allocationRow => {
        const familyCode = allocationRow.getAttribute('data-family-code');
        const totalQty = parseInt(allocationRow.getAttribute('data-total-qty')) || 0;
        const allocatedQty = parseInt(allocationRow.getAttribute('data-allocated-qty')) || 0;
        const input = allocationRow.querySelector('.allocation-qty-input');
        const inputQty = parseInt(input.value) || 0;
        const pendingQty = pendingByItem[familyCode] || 0;
        
        // FIXED: includes pending
        const qtyLeft = totalQty - allocatedQty - pendingQty - inputQty;
        
        // Update display with color coding...
    });
}
```

---

## Behavior After Fix

### Example 1: Simple Allocation

**Initial State:**
- IPHONE 15 128GB: Total = 2, Allocated = 0, Quantity Left = 2

**After selecting 2 branches and entering 1 each:**
- Session table shows:
  ```
  INFANTA     | IPHONE 15 128GB | 1
  HEAD OFFICE | IPHONE 15 128GB | 1
  ```
- **Quantity Left: 0** ✅ (2 - 0 - 2 = 0)
- Color: Orange (fully allocated)

### Example 2: Partial Allocation

**Initial State:**
- IPHONE 15 128GB: Total = 5, Allocated = 2, Quantity Left = 3

**After staging 2 more:**
- Session table shows:
  ```
  LOPEZ | IPHONE 15 128GB | 2
  ```
- **Quantity Left: 1** ✅ (5 - 2 - 2 = 1)
- Color: Normal (still available)

### Example 3: Over-Allocation

**Initial State:**
- IPHONE 15 128GB: Total = 2, Allocated = 0, Quantity Left = 2

**After staging 3 branches with 1 each:**
- Session table shows:
  ```
  INFANTA     | IPHONE 15 128GB | 1
  HEAD OFFICE | IPHONE 15 128GB | 1
  LOPEZ       | IPHONE 15 128GB | 1
  ```
- **Quantity Left: -1** ✅ (2 - 0 - 3 = -1)
- Color: Red (over-allocated)

---

## Benefits

✅ **Real-time Accuracy:** Quantity Left always reflects pending allocations  
✅ **Prevents Over-allocation:** Users see immediately when they've allocated too much  
✅ **Visual Feedback:** Color coding helps identify fully/over-allocated items  
✅ **Consistency:** Staging and committed allocations calculated the same way

---

## Related Functions

### `updateAllQuantityLeft()`
**Purpose:** Calculate and display remaining quantities  
**Called by:**
- When quantity input changes (user typing)
- After clicking "Set" (staging allocation)
- When modal opens (initial state)

### `addToCurrentSessionAllocations()`
**Purpose:** Add row to session table  
**Does NOT:** Update quantity left (that's done by `updateAllQuantityLeft()`)

### Removed Function
❌ `updatePendingAllocations()` - No longer needed, logic merged into `updateAllQuantityLeft()`

---

## Color Coding

| Quantity Left | Color | Meaning |
|---------------|-------|---------|
| Negative (< 0) | 🔴 Red | Over-allocated |
| Zero (= 0) | 🟠 Orange | Fully allocated |
| Positive (> 0) | ⚫ Normal | Available |

---

## Testing Checklist

- [x] Select 2 branches, enter 1 each → Quantity Left = 0 ✅
- [x] Pending allocations counted correctly
- [x] Multiple items tracked independently
- [x] Over-allocation shows red
- [x] Full allocation shows orange
- [x] Partial allocation shows normal
- [x] Form input also considered in calculation
- [x] Session table cleared → Quantity Left resets

---

## Files Modified

**purchaseorder-details.php:**
- Modified `updateAllQuantityLeft()` function
- Removed `updatePendingAllocations()` function (duplicate logic)
- Updated `setAllAllocations()` to call `updateAllQuantityLeft()`

---

**Status: ✅ Fixed**

**Issue:** Quantity Left not reflecting pending allocations  
**Solution:** Include session table data in calculation  
**Result:** Accurate real-time quantity tracking
