# Branch Selection Modal - Select All Feature

## Overview
Added a "Select All" checkbox to the Branch Selection Modal in `purchaseorder-details.php`, matching the functionality from `accountregistration.php`.

## Changes Made

### 1. HTML Structure Update
**File**: `purchaseorder-details.php` (lines ~1311-1319)

Added a "Select All" checkbox area before the branch list:

```html
<div style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
    <label style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 10px;">
        <input type="checkbox" id="selectAllBranchesModal" style="width: 18px; height: 18px;"
            onchange="toggleBranchSelectionSelectAll()">
        Select All
    </label>
</div>
```

### 2. New Function: toggleBranchSelectionSelectAll()
**File**: `purchaseorder-details.php` (lines ~2186-2195)

Handles the Select All checkbox click - checks/unchecks all branch checkboxes:

```javascript
function toggleBranchSelectionSelectAll() {
    const selectAll = document.getElementById('selectAllBranchesModal');
    const checkboxes = document.querySelectorAll('.branch-checkbox');
    
    checkboxes.forEach(cb => {
        cb.checked = selectAll.checked;
    });
    
    updateSelectedBranchesDisplay();
}
```

### 3. New Function: updateBranchSelectionSelectAllState()
**File**: `purchaseorder-details.php` (lines ~2197-2220)

Updates the Select All checkbox state based on individual branch checkboxes:

```javascript
function updateBranchSelectionSelectAllState() {
    const checkboxes = document.querySelectorAll('.branch-checkbox');
    const selectAll = document.getElementById('selectAllBranchesModal');
    
    if (checkboxes.length === 0) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
        return;
    }
    
    const checkedCount = document.querySelectorAll('.branch-checkbox:checked').length;
    
    if (checkedCount === 0) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
    } else if (checkedCount === checkboxes.length) {
        selectAll.checked = true;
        selectAll.indeterminate = false;
    } else {
        selectAll.checked = false;
        selectAll.indeterminate = true;  // Shows dash/minus state
    }
}
```

### 4. Updated: handleBranchCheckboxChange()
**File**: `purchaseorder-details.php` (lines ~2222-2228)

Added call to update Select All state when individual checkboxes change:

```javascript
function handleBranchCheckboxChange(checkbox) {
    // Allow multiple checkboxes to be selected
    // Update the display to show count of selected branches
    updateSelectedBranchesDisplay();
    // Update select all checkbox state  ← NEW
    updateBranchSelectionSelectAllState();
}
```

### 5. Updated: openBranchSelectionModal()
**File**: `purchaseorder-details.php` (lines ~2146-2159)

Added call to initialize Select All state when modal opens:

```javascript
function openBranchSelectionModal() {
    document.getElementById('branchSelectionModal').style.display = 'flex';
    document.getElementById('branchSearchInput').value = '';
    
    // Uncheck all checkboxes
    const allCheckboxes = document.querySelectorAll('.branch-checkbox');
    allCheckboxes.forEach(cb => cb.checked = false);
    
    // Update select all state  ← NEW
    updateBranchSelectionSelectAllState();
    
    filterBranchSelection();
}
```

## Functionality

### Select All States

1. **Unchecked** (☐):
   - No branches are selected
   - Checkbox appears empty

2. **Checked** (☑):
   - All branches are selected
   - Checkbox appears checked

3. **Indeterminate** (☐-):
   - Some (but not all) branches are selected
   - Checkbox shows a dash/minus symbol

### User Interactions

**Scenario 1: User clicks Select All checkbox**
- Action: All branch checkboxes become checked/unchecked
- Display updates to show "X branches selected"

**Scenario 2: User manually checks individual branches**
- Action: Select All checkbox automatically updates:
  - None selected → Select All is unchecked
  - Some selected → Select All is indeterminate
  - All selected → Select All is checked

**Scenario 3: User opens modal**
- Action: All checkboxes start unchecked
- Select All state initializes to unchecked

### Integration with Existing Features

✅ **Multi-branch allocation**: Works with existing multi-branch selection logic
✅ **Search filter**: Select All respects filtered results
✅ **Quantity calculation**: Updates "Quantity Left" based on selected branch count
✅ **Session allocations**: Compatible with staging workflow

## Visual Layout

```
┌─────────────────────────────────────────┐
│  Select Branch                        × │
├─────────────────────────────────────────┤
│  [Search box]                           │
│                                         │
│  ☑ Select All                           │ ← NEW FEATURE
│  ─────────────────────────────          │
│                                         │
│  ┌───────────────────────────────────┐ │
│  │ Area        │ Branch Name         │ │
│  ├───────────────────────────────────┤ │
│  │ Central     │ ☐ HEAD OFFICE       │ │
│  │             │ ☐ INFANTA           │ │
│  ├───────────────────────────────────┤ │
│  │ North       │ ☐ VALENZUELA        │ │
│  └───────────────────────────────────┘ │
│                                         │
├─────────────────────────────────────────┤
│               [Cancel]  [Select]        │
└─────────────────────────────────────────┘
```

## Benefits

1. **Faster selection**: Users can select all branches with one click
2. **Clear feedback**: Indeterminate state shows partial selection
3. **Consistent UX**: Matches pattern from accountregistration.php
4. **Reduces clicks**: No need to individually check each branch
5. **Better usability**: Especially helpful when allocating to many branches

## Similar Implementation In

- `accountregistration.php` - Account branch assignment
- `promotereg.php` - Promo registration
- `promoreg.php` - Promo registration

All use the same pattern for consistency across the application.
