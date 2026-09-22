# Button Layout Update - Allocate Items Modal

## Change Summary

Reorganized button placement in the Allocate Items modal for better UX and clearer workflow.

## Previous Layout

```
Modal Footer:
  [Back]  [Set]
```

**Issue:** Unclear that "Set" was for adding to session, not finalizing.

---

## New Layout

```
Branch Allocation Section (inside modal body):
  (branch selection dropdown)
  (quantity input table)
  → [Set] ← Positioned at bottom-right of section

Modal Footer:
  [Back]  [Save Allocation]
```

**Benefit:** Clear separation of actions - "Set" for session actions, "Save Allocation" for finalizing.

---

## Button Details

### 🟢 Set Button
**Location:** Inside Branch Allocation section (bottom-right)  
**Color:** Green (`var(--color-green)`)  
**Function:** `setAllAllocations()`

**Purpose:** Add current branch allocation to session

**Actions:**
1. Validates branch and quantities
2. Saves to database
3. Adds to session tracking table
4. Updates Item Information totals
5. Resets form
6. Keeps modal open (shows alert if fully allocated, but doesn't auto-close)

---

### 🔵 Save Allocation Button
**Location:** Modal footer (right side)  
**Color:** Navy (`var(--color-navy)`)  
**Function:** `saveFinalAllocation()`

**Purpose:** Finalize allocation session and exit

**Actions:**
1. Closes modal
2. Reloads page to show allocation summary

**Use when:**
- Done allocating
- Want to save partial allocations
- Need to exit and view summary

---

### ⚫ Back Button
**Location:** Modal footer (left side)  
**Color:** Gray (`#666`)  
**Function:** `closeAllocateModal()`

**Purpose:** Cancel and close without reloading

**Actions:**
1. Closes modal
2. No page reload
3. Preserves "Set" allocations
4. Discards unsaved form input

---

## User Workflow Comparison

### Old Way:
```
1. Select branch, enter quantities
2. Click [Set] → Modal closes, page reloads
3. Click "Allocate Items" again
4. Repeat for each branch
```

### New Way:
```
1. Select Branch A, enter quantities
2. Click [Set] → Modal stays open, form resets
3. Select Branch B, enter quantities
4. Click [Set] → Modal stays open, form resets
5. Repeat as needed
6. Click [Save Allocation] → Modal closes, page reloads
```

---

## Implementation Details

### HTML Changes
- Moved Set button inside Branch Allocation `<div>` before closing tag
- Changed modal footer Set button to "Save Allocation"
- Updated onclick handler to `saveFinalAllocation()`

### CSS Changes
```css
.btn-save-final-allocation {
    background-color: var(--color-navy);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.btn-save-final-allocation:hover {
    background-color: var(--color-navy-dark);
}
```

### JavaScript Changes
```javascript
// New function for Save Allocation button
function saveFinalAllocation() {
    closeAllocateModal();
    window.location.reload();
}
```

---

## Benefits

✅ **Clearer Intent** - Button placement indicates function  
✅ **Better UX** - Actions grouped logically  
✅ **Reduced Confusion** - Separate buttons for session vs. finalization  
✅ **Visual Distinction** - Color coding (green for action, navy for finalization)  
✅ **Workflow Clarity** - "Set" multiple times, "Save" once  

---

## Testing Checklist

- [ ] Set button appears inside Branch Allocation section
- [ ] Set button adds allocation to session table
- [ ] Set button keeps modal open (always)
- [ ] Alert shows when fully allocated but modal stays open
- [ ] Save Allocation button in modal footer (navy color)
- [ ] Save Allocation closes modal and reloads page
- [ ] Back button closes modal without reload
- [ ] Button hover states work correctly
- [ ] Button text and colors match specification

---

## Files Modified

- `purchaseorder-details.php`
  - Moved Set button to Branch Allocation section
  - Added Save Allocation button to modal footer
  - Added CSS for `.btn-save-final-allocation`
  - Added `saveFinalAllocation()` JavaScript function

## Related Documentation

- `ALLOCATE_MODAL_STRUCTURE.md` - Visual layout and workflow
- `PO_MULTI_BRANCH_ALLOCATION_IMPLEMENTATION.md` - Full feature documentation
