# Purchase Order Multi-Branch Allocation Enhancement

## Overview
Enhance the Purchase Order allocation workflow to support multiple branch allocations within a single modal session, eliminating the need to close and reopen the modal for each branch allocation.

## Requirements

### R1: Keep Allocation Modal Open After Set
**Priority:** High  
**Type:** Functional

The Allocate Items modal must remain open after the user clicks the "Set" button, allowing immediate continuation of allocation work.

**Acceptance Criteria:**
- When user clicks "Set" button, allocation is saved to the database
- Modal does NOT close after successful save
- User can immediately select a different branch and allocate more items
- Modal only closes when user explicitly clicks "Back" or the close (X) button

---

### R2: Dynamic Quantity Updates After Each Allocation
**Priority:** High  
**Type:** Functional

The Item Information section must update dynamically after each allocation to reflect remaining available quantities.

**Acceptance Criteria:**
- After successful allocation, the "Already Allocated" column updates without page reload
- "Quantity Left" column recalculates and displays the new remaining quantity
- Over-allocation warnings (red text) appear immediately if quantities exceed available stock
- All calculations are accurate and consistent with database state

---

### R3: Branch Selection Reset After Set
**Priority:** High  
**Type:** Functional

After a successful allocation, the branch selection and quantity inputs must reset to allow easy entry of the next allocation.

**Acceptance Criteria:**
- Branch dropdown/selection field clears after successful allocation
- All quantity input fields reset to 0
- Item Information table shows updated totals from R2
- User can immediately start entering a new allocation without manual clearing

---

### R4: Visual Feedback During Save
**Priority:** Medium  
**Type:** User Experience

Provide clear feedback during the save operation to prevent duplicate submissions and inform users of progress.

**Acceptance Criteria:**
- "Set" button shows loading state (e.g., "Saving...") during save operation
- "Set" button is disabled during save to prevent double-clicks
- Success notification appears after successful allocation
- Error messages display clearly if save fails
- Button returns to normal state after completion (success or error)

---

### R5: Multi-Allocation Workflow Support
**Priority:** High  
**Type:** Functional

Support a seamless workflow where users can allocate items to multiple branches consecutively without modal interruption.

**Acceptance Criteria:**
- User can complete 3+ allocations to different branches in one modal session
- Each allocation is independent and persisted immediately
- No data loss between consecutive allocations
- Modal state remains stable throughout multiple allocations
- Page only reloads when user closes the modal (optional) or refreshes manually

---

### R6: Preserve Existing Allocation Logic
**Priority:** High  
**Type:** Technical

All existing allocation validation and business rules must continue to function correctly.

**Acceptance Criteria:**
- Quantity validation prevents over-allocation
- Cost calculations remain accurate
- Family code mapping works correctly
- Received quantities are respected
- All database constraints and triggers continue to function
- No regression in existing allocation features

---

### R7: Error Handling and Recovery
**Priority:** Medium  
**Type:** Functional

Gracefully handle errors without disrupting the allocation session.

**Acceptance Criteria:**
- Network errors display user-friendly messages
- Failed allocations do not close the modal
- User can retry failed allocations without losing entered data
- Validation errors are clear and actionable
- System recovers gracefully from database errors

---

## User Workflow

### Current State
1. User clicks "Allocate Items" button
2. Modal opens
3. User selects Branch A
4. User enters quantities for items
5. User clicks "Set"
6. **Modal closes**
7. User clicks "Allocate Items" again to continue
8. Repeat for Branch B, C, etc.

### Desired State
1. User clicks "Allocate Items" button
2. Modal opens
3. User selects Branch A
4. User enters quantities for items
5. User clicks "Set"
6. **Modal stays open**
7. Success message appears
8. Quantities update to show remaining available stock
9. Branch selection clears
10. Quantity inputs reset to 0
11. User selects Branch B
12. User enters quantities for remaining items
13. User clicks "Set" again
14. **Process repeats** until all allocations complete
15. User clicks "Back" to close modal and return to main view

---

## Technical Context

### Files Involved
- `purchaseorder-details.php` - Main UI and allocation modal
- `save_all_allocations.php` - Backend allocation save logic
- `purchase_order_allocations` - Database table for allocations
- `purchase_order_items` - Database table for PO items

### Current Implementation Key Points
1. `setAllAllocations()` function handles the save operation
2. After successful save, `window.location.reload()` is called
3. Modal close is triggered by the page reload
4. `updateAllQuantityLeft()` calculates real-time quantity left values
5. Branch selection uses a custom modal with checkbox behavior

### Technical Approach
1. Remove `window.location.reload()` from success callback
2. Replace with AJAX fetch to get updated allocation data
3. Update DOM elements dynamically with new quantities
4. Reset branch selection and input fields
5. Keep modal open and display success notification
6. Add proper error state handling without modal closure

---

## Non-Functional Requirements

### Performance
- Each allocation save should complete within 2 seconds under normal conditions
- UI updates should feel instantaneous (< 200ms)
- No memory leaks from keeping modal open for extended periods

### Usability
- Clear visual indication of successful allocations
- Intuitive workflow that doesn't require training
- Accessible error messages
- Responsive button states

### Compatibility
- Must work in current supported browsers
- Maintain compatibility with existing PHP/MySQL backend
- No breaking changes to database schema required

---

## Out of Scope

The following are explicitly NOT part of this enhancement:

- Bulk allocation across multiple branches in one action
- Allocation editing within the same modal session
- Undo/redo functionality for allocations
- Offline allocation support
- Mobile-specific optimizations
- Allocation templates or presets

---

## Success Metrics

- Users can complete multi-branch allocations without reopening modal
- Reduction in clicks required for multi-branch allocation scenarios
- No increase in allocation errors or data inconsistencies
- Positive user feedback on improved workflow efficiency

---

## Dependencies

- Existing `save_all_allocations.php` endpoint must support repeated calls
- Database transaction handling must support concurrent allocation operations
- `get_branch_allocations.php` or equivalent endpoint needed for fetching updated data

---

## Risks and Considerations

1. **Data Consistency:** Multiple allocations in quick succession could cause race conditions
   - Mitigation: Ensure proper database locking and transaction handling

2. **User Confusion:** Users might not realize modal stayed open
   - Mitigation: Clear success messages after each allocation

3. **Memory Usage:** Long allocation sessions could consume browser memory
   - Mitigation: Limit modal session time or add refresh mechanism

4. **Network Errors:** Failed saves could leave users uncertain of state
   - Mitigation: Clear error messages and ability to retry without data loss
