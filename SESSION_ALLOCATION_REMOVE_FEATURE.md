# Session Allocation Remove Feature

## Overview
Added the ability to remove individual allocations from the Current Session Allocations table before committing them to the database.

## Changes Made

### 1. Table Structure Update
**File**: `purchaseorder-details.php` (lines ~1184-1194)

Added a new "Action" column to the Current Session Allocations table:

```html
<thead>
    <tr>
        <th style="text-align: left;">Branch</th>
        <th style="text-align: left;">Family Code</th>
        <th>Quantity</th>
        <th>Action</th>  <!-- NEW COLUMN -->
    </tr>
</thead>
```

### 2. Modified addToCurrentSessionAllocations Function
**File**: `purchaseorder-details.php` (lines ~1851-1877)

Updated the function to:
- Add data attributes to each row for tracking
- Include a "Remove" button in the Action column

```javascript
function addToCurrentSessionAllocations(branchName, allocations) {
    const tbody = document.getElementById('currentSessionAllocationsBody');
    const section = document.getElementById('currentSessionAllocationsSection');
    
    if (section.style.display === 'none') {
        section.style.display = 'block';
    }
    
    allocations.forEach(alloc => {
        const row = document.createElement('tr');
        // Add data attributes for tracking
        row.setAttribute('data-branch', branchName);
        row.setAttribute('data-family-code', alloc.family_code);
        row.setAttribute('data-quantity', alloc.quantity);
        
        row.innerHTML = `
            <td style="text-align: left; font-weight: 600;">${branchName}</td>
            <td style="text-align: left;">${alloc.family_code}</td>
            <td>${alloc.quantity}</td>
            <td>
                <button type="button" class="btn-remove-session-allocation" 
                        onclick="removeSessionAllocation(this)">
                    Remove
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}
```

### 3. New Function: removeSessionAllocation
**File**: `purchaseorder-details.php` (lines ~1885-1902)

Created a new function to handle removal of individual session allocations:

```javascript
function removeSessionAllocation(button) {
    const row = button.closest('tr');
    const tbody = document.getElementById('currentSessionAllocationsBody');
    const section = document.getElementById('currentSessionAllocationsSection');
    
    // Remove the row
    row.remove();
    
    // Hide section if no more rows
    if (tbody.querySelectorAll('tr').length === 0) {
        section.style.display = 'none';
    }
    
    // Recalculate quantity left after removal
    updateAllQuantityLeft();
}
```

### 4. CSS Styling
**File**: `purchaseorder-details.php` (lines ~816-828)

Added styling for the remove button:

```css
.btn-remove-session-allocation {
    background-color: #dc3545;  /* Red background */
    color: white;
    border: none;
    padding: 5px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
}

.btn-remove-session-allocation:hover {
    background-color: #c82333;  /* Darker red on hover */
}
```

## Functionality

### User Flow
1. User clicks "Set" button to add allocations to the session table
2. Allocations appear in the Current Session Allocations table with a "Remove" button
3. User can click "Remove" on any row to delete that specific allocation
4. When a row is removed:
   - The row is immediately deleted from the table
   - "Quantity Left" values are recalculated automatically
   - If all rows are removed, the section hides itself
5. User can continue adding more allocations or click "Save Allocation" to commit

### Key Features
- **Individual Removal**: Each allocation can be removed independently
- **Real-time Updates**: Quantity calculations update immediately after removal
- **Auto-hide**: Section disappears when empty
- **Visual Feedback**: Red button with hover effect for clear action indication

## Benefits
- Users can correct mistakes before committing to database
- Provides flexibility in the allocation workflow
- No need to cancel entire session to fix one allocation
- Maintains accurate quantity tracking throughout the process

## Testing Checklist
- [ ] Remove button appears for each allocation in session table
- [ ] Clicking Remove deletes the correct row
- [ ] Quantity Left updates correctly after removal
- [ ] Section hides when all allocations are removed
- [ ] Can add new allocations after removing some
- [ ] Save Allocation works correctly after removals
- [ ] Button styling matches design (red with hover effect)
