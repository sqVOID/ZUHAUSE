# Allocate Items Modal - Enhanced Structure

## Modal Layout

```
┌─────────────────────────────────────────────────────────────┐
│  Allocate Items                                          ✕  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ Item Information                                      │ │
│  ├──────────────┬──────────┬──────────────┬─────────────┤ │
│  │ Family Code  │  Total   │   Already    │  Quantity   │ │
│  │              │ Quantity │  Allocated   │    Left     │ │
│  ├──────────────┼──────────┼──────────────┼─────────────┤ │
│  │ IPHONE-14    │    10    │      6       │      4      │ │
│  │ IPHONE-14 PRO│     5    │      2       │      3      │ │
│  └──────────────┴──────────┴──────────────┴─────────────┘ │
│                                                             │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ Current Session Allocations                           │ │
│  │ (Shows what you've allocated in this session)         │ │
│  ├──────────────┬──────────────┬─────────────────────────┤ │
│  │   Branch     │ Family Code  │       Quantity          │ │
│  ├──────────────┼──────────────┼─────────────────────────┤ │
│  │ Branch-A     │ IPHONE-14    │          3              │ │
│  │ Branch-A     │ IPHONE-14 PRO│          1              │ │
│  │ Branch-B     │ IPHONE-14    │          2              │ │
│  └──────────────┴──────────────┴─────────────────────────┘ │
│                                                             │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ Branch Allocation                                     │ │
│  │                                                       │ │
│  │ Select Branch: [Click to select branch] [Select]     │ │
│  │                                                       │ │
│  ├──────────────┬────────────────────────────────────────┤ │
│  │ Family Code  │         Quantity                       │ │
│  ├──────────────┼────────────────────────────────────────┤ │
│  │ IPHONE-14    │         [  1  ]                        │ │
│  │ IPHONE-14 PRO│         [  2  ]                        │ │
│  └──────────────┴────────────────────────────────────────┘ │
│  │                                          [Set] ←───────┤ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                              [Back]  [Save Allocation]      │
└─────────────────────────────────────────────────────────────┘
```

## Button Layout

**Inside Branch Allocation Section:**
- `[Set]` button (green) - Adds current allocation to session

**Modal Footer:**
- `[Back]` button (gray) - Cancel and close without saving current input
- `[Save Allocation]` button (navy) - Finalize session and reload page

## Table Descriptions

### 1. Item Information (Always Visible)
**Purpose:** Shows the overall status of each item in the PO

**Columns:**
- **Family Code** - The item identifier
- **Total Quantity** - Order quantity from the PO
- **Already Allocated** - Total allocated across all branches (updates after each Set)
- **Quantity Left** - Remaining available for allocation (color-coded)

**Color Coding:**
- 🔴 **Red** - Over-allocated (negative value)
- 🟠 **Orange** - Fully allocated (zero)
- ⚫ **Normal** - Available (positive value)

## Button Functions

### 1. Set Button (Green - Inside Branch Allocation)
**Purpose:** Add current branch allocation to the session

**Behavior:**
- Validates branch selection and quantities
- Saves allocation to database immediately
- Adds row to "Current Session Allocations" table
- Updates "Item Information" totals in real-time
- Resets branch selection and quantity inputs
- **Always keeps modal open** for next allocation
- **Shows alert** if all items become fully allocated (but doesn't auto-close)

**When to use:** After entering quantities for a branch, click Set to save that allocation and continue allocating to other branches.

### 2. Save Allocation Button (Navy - Modal Footer)
**Purpose:** Finalize session and return to main page

**Behavior:**
- Closes the modal
- Reloads page to show updated allocation summary
- No validation required
- Commits all "Set" allocations made during session

**When to use:** 
- When you're done allocating and want to finish
- To save partial allocations (some items unallocated)
- To exit and see the full allocation summary

### 3. Back Button (Gray - Modal Footer)
**Purpose:** Cancel and close modal

**Behavior:**
- Closes modal without reloading page
- Preserves any allocations already saved via "Set"
- Discards unsaved input in Branch Allocation form

**When to use:** When you want to exit without viewing the full allocation summary.

---

### 2. Current Session Allocations (Hidden → Visible after first allocation)
**Purpose:** Tracks all allocations made in the current modal session

**Columns:**
- **Branch** - Which branch received the allocation
- **Family Code** - Which item was allocated
- **Quantity** - How many units were allocated

**Behavior:**
- Initially hidden when modal opens
- Appears after first successful allocation
- Adds new row for each allocation in the session
- Shows cumulative log of session activity
- Clears when modal is closed and reopened

**Example Scenario:**
```
User allocates 3 IPHONE-14 to Branch A → Table shows:
  Branch A | IPHONE-14 | 3

User allocates 1 IPHONE-14 PRO to Branch A → Table shows:
  Branch A | IPHONE-14     | 3
  Branch A | IPHONE-14 PRO | 1

User allocates 2 IPHONE-14 to Branch B → Table shows:
  Branch A | IPHONE-14     | 3
  Branch A | IPHONE-14 PRO | 1
  Branch B | IPHONE-14     | 2
```

---

### 3. Branch Allocation (Always Visible)
**Purpose:** Input form to allocate items to a selected branch

**Components:**
- **Branch Selection** - Dropdown/modal to select target branch
- **Quantity Inputs** - Numeric fields for each item

**Behavior:**
- User selects branch
- User enters quantities for items to allocate
- Click "Set" to save
- Form resets after successful save
- Ready for next allocation

---

## User Workflow with New Button Structure

### Scenario: Allocate 10 IPHONE-14 units to 3 branches

1. **Open Modal**
   - Item Information shows: IPHONE-14 | 10 | 0 | 10
   - Current Session Allocations: *hidden*
   - Branch Allocation: *ready for input*
   - Buttons visible: `[Set]` inside section, `[Back] [Save Allocation]` in footer

2. **First Allocation to Branch A (4 units)**
   - Select Branch A, enter 4
   - **Click `[Set]` button** (inside Branch Allocation section)
   - Item Information updates: IPHONE-14 | 10 | 4 | 6 (green)
   - Current Session Allocations *appears*:
     ```
     Branch A | IPHONE-14 | 4
     ```
   - Branch Allocation form resets
   - **Modal stays open**

3. **Second Allocation to Branch B (3 units)**
   - Select Branch B, enter 3
   - **Click `[Set]` button**
   - Item Information updates: IPHONE-14 | 10 | 7 | 3 (green)
   - Current Session Allocations adds row:
     ```
     Branch A | IPHONE-14 | 4
     Branch B | IPHONE-14 | 3
     ```
   - Branch Allocation form resets
   - **Modal stays open**

4. **Third Allocation to Branch C (3 units)**
   - Select Branch C, enter 3
   - **Click `[Set]` button**
   - Item Information updates: IPHONE-14 | 10 | 10 | 0 (orange)
   - Current Session Allocations adds row:
     ```
     Branch A | IPHONE-14 | 4
     Branch B | IPHONE-14 | 3
     Branch C | IPHONE-14 | 3
     ```
   - **Alert: "All items have been fully allocated! Click 'Save Allocation' to finish."**
   - **Modal stays open**

5. **Finalize Allocation**
   - **Click `[Save Allocation]` button** (modal footer)
   - Modal closes
   - Page reloads showing complete allocation

### Alternative: Partial Allocation

1. **Allocate to Branch A (4 units)**
   - Select Branch A, enter 4
   - Click `[Set]`
   - Session table shows allocation

2. **Allocate to Branch B (3 units)**
   - Select Branch B, enter 3
   - Click `[Set]`
   - Session table shows both allocations
   - 3 units remain unallocated

3. **Finish Session**
   - **Click `[Save Allocation]` button** (modal footer)
   - Modal closes
   - Page reloads showing partial allocation
   - 3 units remain available for future allocation

---

## Benefits of Session Tracking

✅ **Transparency** - See exactly what you've allocated in this session  
✅ **Accountability** - Clear audit trail of your allocations  
✅ **Confidence** - Verify your work before closing the modal  
✅ **Context** - Remember which branches you've already allocated to  
✅ **Error Prevention** - Avoid duplicate allocations to same branch  

---

## Technical Implementation

### HTML Structure
```html
<div class="modal-table-section" id="currentSessionAllocationsSection" style="display: none;">
    <h4>Current Session Allocations</h4>
    <table id="currentSessionAllocationsTable">
        <thead>
            <tr>
                <th>Branch</th>
                <th>Family Code</th>
                <th>Quantity</th>
            </tr>
        </thead>
        <tbody id="currentSessionAllocationsBody">
            <!-- Populated dynamically via JavaScript -->
        </tbody>
    </table>
</div>
```

### JavaScript Functions
- `addToCurrentSessionAllocations(branchName, allocations)` - Adds allocation to table
- `clearCurrentSessionAllocations()` - Clears table on modal open

### Lifecycle
1. Modal opens → Table hidden, tbody empty
2. First allocation → Table visible, first row added
3. Subsequent allocations → Additional rows appended
4. Modal closes → Table state preserved (until next open)
5. Modal reopens → Table cleared and hidden (fresh session)
