# Multi-Branch Selection Feature

## Overview

Enhanced the branch selection modal to support selecting **multiple branches** at once. When multiple branches are selected, the system allocates the same quantities to all selected branches in one action.

---

## Feature Description

### Before (Single Branch Selection)
- User could only select one branch at a time
- To allocate to 3 branches with same quantities:
  - Select Branch A → Enter quantities → Click Set
  - Select Branch B → Enter quantities (same as before) → Click Set
  - Select Branch C → Enter quantities (same as before) → Click Set
- **Total: 9 actions** (3 selections + 3 quantity entries + 3 Set clicks)

### After (Multi-Branch Selection)
- User can select multiple branches at once
- To allocate to 3 branches with same quantities:
  - Select Branch A, B, and C (checkboxes)
  - Click "Select" button
  - Enter quantities once
  - Click Set → Allocates to all 3 branches
- **Total: 4 actions** (1 multi-selection + 1 quantity entry + 1 Set click + 1 Select click)

**Improvement: 56% reduction in actions!**

---

## How It Works

### 1. Branch Selection Modal

#### Visual Changes:
- Checkboxes now support **multiple selections**
- Added **"Select" button** next to "Cancel" button in footer
- Display shows count when multiple branches selected

#### Behavior:
- Check one or more branch checkboxes
- Click "Select" button to confirm
- Display field shows:
  - Single branch: "Branch Name"
  - Multiple branches: "3 branches selected"

### 2. Allocation Process

When you click **Set** with multiple branches selected:

1. **Validation**
   - Checks if quantities × number of branches exceeds available stock
   - Shows clear error if over-allocation detected

2. **Sequential Processing**
   - Saves allocation to Branch A
   - Saves allocation to Branch B
   - Saves allocation to Branch C
   - (continues for all selected branches)

3. **Session Tracking**
   - Adds each branch allocation to session table
   - Shows separate rows for each branch

4. **Dynamic Updates**
   - "Already Allocated" reflects total across all branches
   - "Quantity Left" updates accounting for all allocations

5. **Completion**
   - Shows success message: "Successfully allocated to 3 branch(es)!"
   - Form resets for next allocation

---

## Example Scenarios

### Scenario 1: Same Quantities to Multiple Branches

**Task:** Allocate 1 IPHONE 15 128GB to Infanta and Head Office

**Steps:**
1. Click "Click to select branch"
2. Check ☑ "ZUHAUSE INFANTA"
3. Check ☑ "ZUHAUSE HEAD OFFICE"
4. Click "Select" button
5. Display shows: "2 branches selected"
6. Enter quantity: 1 for IPHONE 15 128GB
7. Click "Set"

**Result:**
- Infanta: +1 IPHONE 15 128GB
- Head Office: +1 IPHONE 15 128GB
- Total allocated: 2 units
- Session table shows:
  ```
  ZUHAUSE INFANTA     | IPHONE 15 128GB | 1
  ZUHAUSE HEAD OFFICE | IPHONE 15 128GB | 1
  ```

### Scenario 2: Multiple Items to Multiple Branches

**Task:** Allocate inventory to 5 branches

**Steps:**
1. Select 5 branches via checkboxes
2. Click "Select"
3. Display shows: "5 branches selected"
4. Enter quantities:
   - IPHONE 15 128GB: 2
   - IPHONE 16 128GB: 1
5. Click "Set"

**Result:**
- All 5 branches get:
  - 2 units of IPHONE 15 128GB
  - 1 unit of IPHONE 16 128GB
- Total allocated:
  - 10 units of IPHONE 15 128GB (2 × 5)
  - 5 units of IPHONE 16 128GB (1 × 5)

### Scenario 3: Over-Allocation Prevention

**Available:** 5 units of IPHONE 15 128GB  
**Action:** Select 3 branches, enter quantity 2

**Calculation:**
- Requested: 2 units × 3 branches = 6 units
- Available: 5 units
- **Over-allocation: 1 unit**

**System Response:**
- Blocks the allocation
- Shows error: "Over-allocation detected! You selected 3 branch(es). The quantities entered will be allocated to each branch, which exceeds available quantity."
- Modal stays open for correction

---

## Technical Implementation

### Modified Functions

#### 1. `handleBranchCheckboxChange(checkbox)`
**Before:** Unchecked all other checkboxes (radio behavior)  
**After:** Allows multiple checkboxes, updates display

#### 2. `updateSelectedBranchesDisplay()`
**New function** - Updates display field based on selection count

#### 3. `applyBranchSelection()`
**Before:** Applied single selection  
**After:** Applies multiple selections with validation

#### 4. `setAllAllocations()`
**Major changes:**
- Parses comma-separated branch names
- Validates total allocation (qty × branches)
- Processes branches sequentially
- Tracks completion for all branches
- Updates session table for each branch
- Shows consolidated success message

### Data Flow

```
User selects branches
    ↓
Clicks "Select"
    ↓
Display updates: "3 branches selected"
    ↓
Hidden field stores: "Branch A,Branch B,Branch C"
    ↓
User enters quantities
    ↓
Clicks "Set"
    ↓
System validates: qty × 3 ≤ available
    ↓
Saves to Branch A → Session table +1
    ↓
Saves to Branch B → Session table +1
    ↓
Saves to Branch C → Session table +1
    ↓
Updates totals (1 fetch for all)
    ↓
Shows success: "Successfully allocated to 3 branch(es)!"
```

---

## User Interface Changes

### Branch Selection Modal Footer

**Before:**
```
[Cancel]
```

**After:**
```
[Cancel] [Select]
```

### Branch Display Field

**Single Selection:**
```
ZUHAUSE INFANTA
```

**Multiple Selection:**
```
3 branches selected
```

### Validation Messages

**Over-Allocation:**
```
Over-allocation detected! You selected 3 branch(es). 
The quantities entered will be allocated to each branch, 
which exceeds available quantity.
```

**Success:**
```
Successfully allocated to 3 branch(es)!
```

---

## Benefits

| Benefit | Impact |
|---------|--------|
| **Time Savings** | 56% fewer actions for identical allocations |
| **Error Reduction** | Enter quantities once instead of multiple times |
| **Consistency** | Same quantities guaranteed across all branches |
| **Transparency** | Session table shows each branch allocation |
| **Flexibility** | Still supports single-branch allocation |
| **Validation** | Prevents over-allocation across all branches |

---

## Edge Cases Handled

### ✅ No Branch Selected
- Error: "Please select at least one branch!"

### ✅ Over-Allocation
- Calculates total: qty × number of branches
- Blocks if exceeds available
- Shows detailed error message

### ✅ Network Error During Sequential Save
- Tracks which branches succeeded/failed
- Shows error list for failed branches
- Successfully saved branches remain in database

### ✅ Mixed Success/Failure
- Completes all branches even if some fail
- Shows summary of errors
- Session table only shows successful allocations

---

## Testing Checklist

### Functional Tests
- [ ] Select single branch → Works as before
- [ ] Select 2 branches → Both receive allocations
- [ ] Select 5+ branches → All receive allocations
- [ ] Display shows correct count (e.g., "5 branches selected")
- [ ] Session table shows separate rows for each branch
- [ ] Validation checks qty × branches vs. available
- [ ] Over-allocation blocked with clear message
- [ ] Success message shows correct branch count
- [ ] "Already Allocated" reflects total across all branches
- [ ] Form resets after successful multi-branch allocation

### Edge Case Tests
- [ ] Select no branches → Error shown
- [ ] Select all available branches → All processed
- [ ] Network error during save → Graceful handling
- [ ] Partial failure (some branches fail) → Error list shown
- [ ] Very large quantities × many branches → Validation works
- [ ] Cancel button closes modal without applying
- [ ] Modal reopens with cleared selection

### Data Integrity Tests
- [ ] Database shows separate records for each branch
- [ ] Session table matches database
- [ ] Totals calculate correctly
- [ ] No duplicate allocations created
- [ ] Remaining quantity accurate after multi-branch

---

## Backward Compatibility

✅ **Fully compatible with single-branch workflow**
- Selecting one branch works exactly as before
- Display shows branch name (not "1 branches selected")
- No breaking changes to existing functionality

---

## Files Modified

1. **purchaseorder-details.php**
   - Added "Select" button to branch selection modal footer
   - Modified `handleBranchCheckboxChange()` for multi-select
   - Added `updateSelectedBranchesDisplay()` function
   - Modified `applyBranchSelection()` for multiple branches
   - Rewrote `setAllAllocations()` for sequential processing
   - Added validation for multi-branch over-allocation

---

## Future Enhancements (Out of Scope)

- Branch group presets ("All Luzon branches")
- Different quantities per branch in one action
- Bulk edit of multiple branch allocations
- Branch allocation templates
- Copy allocation from one branch to others

---

## Success Metrics

✅ **Efficiency:** 56% reduction in actions for identical allocations  
✅ **Accuracy:** Enter quantities once, ensure consistency  
✅ **Usability:** Intuitive checkbox selection  
✅ **Safety:** Validation prevents over-allocation  
✅ **Transparency:** Complete audit trail in session table  

---

**Status: ✅ Implemented and Ready for Testing**

**Last Updated:** 2026-09-21  
**Version:** 1.0  
**Feature Type:** Enhancement
