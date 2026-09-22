# Area Checkbox Feature - Branch Selection Modal

## Overview
Added checkboxes in the Area column of the Branch Selection Modal, allowing users to select all branches within a specific area with one click. This matches the functionality from `accountregistration.php`.

## Changes Made

### 1. Table Structure Update
**File**: `purchaseorder-details.php` (lines ~1319-1375)

Added a checkbox column before the Area column:

**Before:**
```
┌────────────┬──────────────────┐
│ Area       │ Branch Name      │
├────────────┼──────────────────┤
│ QUEZON     │ ☐ BRANCH 1       │
│            │ ☐ BRANCH 2       │
└────────────┴──────────────────┘
```

**After:**
```
┌────┬────────────┬──────────────────┐
│    │ Area       │ Branch Name      │
├────┼────────────┼──────────────────┤
│ ☐  │ QUEZON     │ ☐ BRANCH 1       │
│    │            │ ☐ BRANCH 2       │
└────┴────────────┴──────────────────┘
```

### Updated PHP Table Generation:

```php
<thead>
    <tr>
        <th style="width: 60px; text-align: center; ..."></th>  <!-- NEW COLUMN -->
        <th style="text-align: left; ...">Area</th>
        <th style="text-align: left; ...">Branch Name</th>
    </tr>
</thead>
<tbody>
    <?php
    foreach ($branches_by_area as $area => $area_branches) {
        $area_safe = htmlspecialchars($area);
        $branch_count = count($area_branches);
        
        foreach ($area_branches as $index => $branch) {
            echo '<tr class="branch-selection-row" ...>';
            
            // First row of each area: show area checkbox
            if ($index === 0) {
                // CHECKBOX COLUMN
                echo '<td style="text-align: center; ..." rowspan="' . $branch_count . '">';
                echo '<input type="checkbox" class="area-select-all" ';
                echo 'data-area="' . $area_safe . '" ';
                echo 'onchange="toggleAreaSelectAll(this)">';
                echo '</td>';
                
                // AREA COLUMN
                echo '<td ... rowspan="' . $branch_count . '">';
                echo $area_display;
                echo '</td>';
            }
            
            // BRANCH COLUMN with area-specific CSS class
            echo '<td>';
            echo '<input type="checkbox" class="branch-checkbox ' . $area_safe . '-checkbox" ';
            echo 'data-area="' . $area_safe . '" ...>';
            echo '</td>';
            echo '</tr>';
        }
    }
    ?>
</tbody>
```

### 2. New Function: toggleAreaSelectAll()
**File**: `purchaseorder-details.php` (lines ~2198-2211)

Handles area checkbox clicks - selects/deselects all branches within that area:

```javascript
function toggleAreaSelectAll(areaCheckbox) {
    const area = areaCheckbox.getAttribute('data-area');
    const areaCheckboxes = document.querySelectorAll('.' + area + '-checkbox');
    
    // Check/uncheck all branches in this area (only visible ones)
    areaCheckboxes.forEach(cb => {
        const row = cb.closest('tr');
        if (!row || row.style.display !== 'none') {
            cb.checked = areaCheckbox.checked;
        }
    });
    
    updateSelectedBranchesDisplay();
    updateBranchSelectionSelectAllState();
}
```

### 3. New Function: updateAreaCheckboxStates()
**File**: `purchaseorder-details.php` (lines ~2213-2245)

Updates area checkboxes based on individual branch selections (shows checked, unchecked, or indeterminate):

```javascript
function updateAreaCheckboxStates() {
    const areas = document.querySelectorAll('.area-select-all');
    
    areas.forEach(areaCheckbox => {
        const area = areaCheckbox.getAttribute('data-area');
        const areaCheckboxes = document.querySelectorAll('.' + area + '-checkbox');
        
        // Only consider visible checkboxes (respects search filter)
        const visibleAreaCheckboxes = Array.from(areaCheckboxes).filter(cb => {
            const row = cb.closest('tr');
            return !row || row.style.display !== 'none';
        });
        
        if (visibleAreaCheckboxes.length === 0) {
            areaCheckbox.checked = false;
            areaCheckbox.indeterminate = false;
            return;
        }
        
        const checkedCount = visibleAreaCheckboxes.filter(cb => cb.checked).length;
        
        if (checkedCount === 0) {
            areaCheckbox.checked = false;
            areaCheckbox.indeterminate = false;
        } else if (checkedCount === visibleAreaCheckboxes.length) {
            areaCheckbox.checked = true;
            areaCheckbox.indeterminate = false;
        } else {
            areaCheckbox.checked = false;
            areaCheckbox.indeterminate = true;  // Shows dash/minus
        }
    });
}
```

### 4. Updated Functions

**toggleBranchSelectionSelectAll()** - Added call to `updateAreaCheckboxStates()`:
```javascript
function toggleBranchSelectionSelectAll() {
    const selectAll = document.getElementById('selectAllBranchesModal');
    const checkboxes = document.querySelectorAll('.branch-checkbox');
    
    checkboxes.forEach(cb => {
        cb.checked = selectAll.checked;
    });
    
    updateSelectedBranchesDisplay();
    updateAreaCheckboxStates();  // ← NEW
}
```

**handleBranchCheckboxChange()** - Added call to `updateAreaCheckboxStates()`:
```javascript
function handleBranchCheckboxChange(checkbox) {
    updateSelectedBranchesDisplay();
    updateBranchSelectionSelectAllState();
    updateAreaCheckboxStates();  // ← NEW
}
```

**openBranchSelectionModal()** - Added initialization of area checkboxes:
```javascript
function openBranchSelectionModal() {
    document.getElementById('branchSelectionModal').style.display = 'flex';
    document.getElementById('branchSearchInput').value = '';
    
    // Uncheck all checkboxes
    const allCheckboxes = document.querySelectorAll('.branch-checkbox');
    allCheckboxes.forEach(cb => cb.checked = false);
    
    // Uncheck all area checkboxes  ← NEW
    const areaCheckboxes = document.querySelectorAll('.area-select-all');
    areaCheckboxes.forEach(cb => cb.checked = false);
    
    updateBranchSelectionSelectAllState();
    filterBranchSelection();
}
```

## Functionality

### Three Levels of Selection

1. **Global Select All** (top of modal)
   - Selects/deselects ALL branches across ALL areas

2. **Area Checkboxes** (left column)
   - Selects/deselects all branches within that specific area
   - Shows 3 states:
     - ☐ None selected in area
     - ☑ All selected in area
     - ☐- Some selected in area (indeterminate)

3. **Individual Branch Checkboxes** (right column)
   - Select individual branches

### User Interaction Examples

**Example 1: Select all branches in QUEZON area**
1. User clicks QUEZON area checkbox
2. All QUEZON branches become checked
3. Display updates: "6 branches selected" (if 6 QUEZON branches)
4. Global Select All updates to indeterminate (some, not all)

**Example 2: Manually select some QUEZON branches**
1. User checks 2 out of 6 QUEZON branches
2. QUEZON area checkbox automatically becomes indeterminate (☐-)
3. Display shows "2 branches selected"

**Example 3: Select all areas**
1. User clicks Global Select All
2. All area checkboxes become checked
3. All branch checkboxes become checked
4. Display shows "25 branches selected" (total count)

**Example 4: Search and select**
1. User searches "LUCBAN"
2. Only matching branches shown
3. User clicks visible area checkbox
4. Only visible LUCBAN branches are checked
5. Hidden branches remain unchecked

## Visual Layout

```
┌───────────────────────────────────────────────┐
│  Select Branch                              × │
├───────────────────────────────────────────────┤
│  [Search branches...]                         │
│                                               │
│  ☑ Select All                                 │
│  ─────────────────────────────────────        │
│                                               │
│  ┌─────┬────────────┬────────────────────┐   │
│  │     │ Area       │ Branch Name        │   │
│  ├─────┼────────────┼────────────────────┤   │
│  │ ☑   │ CENTRAL    │ ☑ HEAD OFFICE      │   │
│  │     │            │ ☑ INFANTA          │   │
│  ├─────┼────────────┼────────────────────┤   │
│  │ ☐-  │ QUEZON     │ ☑ LUCBAN           │   │
│  │     │            │ ☐ LOPEZ            │   │
│  │     │            │ ☐ MAUBAN           │   │
│  ├─────┼────────────┼────────────────────┤   │
│  │ ☐   │ NORTH      │ ☐ VALENZUELA       │   │
│  └─────┴────────────┴────────────────────┘   │
│                                               │
├───────────────────────────────────────────────┤
│                    [Cancel]  [Select]         │
└───────────────────────────────────────────────┘
```

## Benefits

1. **Faster area-based selection**: Select all branches in an area with one click
2. **Visual feedback**: Indeterminate state shows partial area selection
3. **Hierarchical control**: Global → Area → Individual branch
4. **Search-aware**: Area checkboxes respect filtered results
5. **Consistent UX**: Matches accountregistration.php pattern

## CSS Classes Used

- `.area-select-all` - Area checkbox elements
- `.branch-checkbox` - Individual branch checkbox elements
- `.{area}-checkbox` - Branch checkboxes grouped by area (e.g., `.quezon-checkbox`)
- `.branch-selection-row` - Table rows for filtering

## Data Attributes

- `data-area="{area}"` - Identifies which area a checkbox belongs to
- Used for:
  - Linking area checkboxes to their branches
  - Filtering functionality
  - CSS class generation

## Integration with Existing Features

✅ **Multi-branch allocation**: Compatible with quantity calculation
✅ **Search filter**: Area checkboxes respect hidden rows
✅ **Global Select All**: Syncs with area-level selections
✅ **Session allocations**: Works with staging workflow
✅ **Quantity Left calculation**: Updates based on total selected branches
