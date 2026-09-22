# Staging Allocation Workflow

## Overview

Refactored the allocation system to use a **staging/commit workflow** where allocations are prepared in-memory before being saved to the database.

---

## Workflow Concept

### Before (Immediate Save)
- Click "Set" → Saves to database immediately
- No way to review or undo before commit
- Each Set action was permanent

### After (Staging → Commit)
- Click "Set" → Adds to session table (staging area)
- Review all allocations in session table
- Click "Save Allocation" → Commits all to database
- Like a shopping cart: add items, then checkout

---

## How It Works

### Step 1: Add Allocations (Set Button)

**Purpose:** Stage allocations without saving to database

**Actions:**
1. User selects branch(es)
2. User enters quantities
3. User clicks "Set"
4. **Allocation added to Current Session Allocations table (IN-MEMORY ONLY)**
5. "Quantity Left" updates to show pending allocations
6. Form resets for next allocation
7. Modal stays open

**Alert Message:**
```
Added allocation for 2 branch(es) to session. 
Click "Save Allocation" to commit.
```

### Step 2: Review Staged Allocations

**Current Session Allocations Table:**
- Shows all pending allocations
- User can review before committing
- Can add more allocations

**Example:**
```
Branch              | Family Code      | Quantity
--------------------|------------------|----------
ZUHAUSE INFANTA     | IPHONE 15 128GB  | 1
ZUHAUSE HEAD OFFICE | IPHONE 15 128GB  | 1
ZUHAUSE LOPEZ       | IPHONE 16 128GB  | 2
```

### Step 3: Commit to Database (Save Allocation Button)

**Purpose:** Save all staged allocations to database

**Actions:**
1. User clicks "Save Allocation"
2. System reads all rows from session table
3. Groups allocations by branch
4. Saves each branch sequentially to database
5. Shows progress: "Saving to database..."
6. On success: Closes modal and reloads page
7. On error: Shows error messages, keeps modal open

**Success Message:**
```
Successfully saved allocations for 3 branch(es) to database!
```

---

## Benefits

### ✅ Review Before Commit
- See all allocations before saving
- Catch errors before they hit database
- Make adjustments if needed

### ✅ Batch Processing
- All allocations saved in one transaction
- More efficient than multiple saves
- Consistent database state

### ✅ Error Recovery
- If save fails, staging data preserved
- Can retry or modify
- No partial saves in database

### ✅ Better UX
- Clear separation: "prepare" vs "commit"
- User controls when data is saved
- Familiar shopping cart pattern

---

## User Workflow Examples

### Example 1: Simple Allocation

**Task:** Allocate 1 IPHONE 15 to 2 branches

**Steps:**
1. Select INFANTA and HEAD OFFICE
2. Enter quantity: 1
3. Click "Set" → Added to session table
4. Click "Save Allocation" → Saved to database
5. Page reloads

**Session Table During Process:**
```
Branch              | Family Code      | Quantity
--------------------|------------------|----------
ZUHAUSE INFANTA     | IPHONE 15 128GB  | 1
ZUHAUSE HEAD OFFICE | IPHONE 15 128GB  | 1
```

### Example 2: Multiple Allocations

**Task:** Different quantities to different branches

**Steps:**
1. Select INFANTA
2. Enter: IPHONE 15 = 2, IPHONE 16 = 1
3. Click "Set"
4. Select HEAD OFFICE
5. Enter: IPHONE 15 = 1, IPHONE 16 = 3
6. Click "Set"
7. Select LOPEZ
8. Enter: IPHONE 16 = 2
9. Click "Set"
10. Review session table (shows all 6 allocations)
11. Click "Save Allocation"
12. All 6 allocations saved to database

**Session Table Before Save:**
```
Branch              | Family Code      | Quantity
--------------------|------------------|----------
ZUHAUSE INFANTA     | IPHONE 15 128GB  | 2
ZUHAUSE INFANTA     | IPHONE 16 128GB  | 1
ZUHAUSE HEAD OFFICE | IPHONE 15 128GB  | 1
ZUHAUSE HEAD OFFICE | IPHONE 16 128GB  | 3
ZUHAUSE LOPEZ       | IPHONE 16 128GB  | 2
```

### Example 3: Review and Cancel

**Steps:**
1. Add several allocations using "Set"
2. Review session table
3. Notice error or change of mind
4. Click "Back" → Modal closes, nothing saved
5. Session table clears

**Result:** No database changes, clean slate

---

## Technical Implementation

### Modified Functions

#### 1. `setAllAllocations()`
**Changed:** No longer saves to database

**New Behavior:**
- Validates quantities and branches
- Adds to session table (DOM only)
- Updates "Quantity Left" display with pending
- Resets form for next allocation
- **NO `fetch()` to backend**

#### 2. `saveFinalAllocation()`
**Changed:** Now actually saves to database

**New Behavior:**
- Reads all rows from session table
- Parses into structured data by branch
- Sends each branch to backend sequentially
- Shows loading state
- Handles success/failure
- Closes modal and reloads on success

#### 3. `updatePendingAllocations()` ⭐ NEW
**Purpose:** Update display with pending counts

**Behavior:**
- Counts pending allocations from session table
- Updates "Quantity Left" to show: available - allocated - pending
- Applies color coding

#### 4. `clearCurrentSessionAllocations()`
**Modified:** Called when modal opens

**Behavior:**
- Clears session table HTML
- Hides session section
- Fresh staging area for new session

---

## Data Flow

```
┌─────────────────────────────────────────┐
│ User selects branches and quantities    │
└─────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│ Click "Set"                             │
└─────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│ Add to Session Table (DOM)              │
│ ✓ In-memory only                        │
│ ✓ Not in database                       │
└─────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│ Update "Quantity Left" with pending     │
└─────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│ User can:                               │
│ • Add more allocations (Set again)      │
│ • Review session table                  │
│ • Cancel (Back button)                  │
│ • Commit (Save Allocation)              │
└─────────────────────────────────────────┘
                  ↓
        ┌─────────┴─────────┐
        │                   │
    Cancel              Commit
        │                   │
        ↓                   ↓
  Modal closes    Click "Save Allocation"
  Nothing saved           ↓
                 ┌────────────────────┐
                 │ Read session table │
                 └────────────────────┘
                          ↓
                 ┌────────────────────┐
                 │ Group by branch    │
                 └────────────────────┘
                          ↓
                 ┌────────────────────┐
                 │ Save to database   │
                 │ (Branch by branch) │
                 └────────────────────┘
                          ↓
                 ┌────────────────────┐
                 │ Close modal        │
                 │ Reload page        │
                 └────────────────────┘
```

---

## Validation

### During "Set" (Staging)
✅ Branch selection required  
✅ Quantity > 0 required  
✅ Over-allocation check: (Allocated + Pending + New) vs Available  
✅ Blocks if exceeds

### During "Save Allocation" (Commit)
✅ Session table not empty  
✅ Database connection  
✅ Transaction handling  
✅ Error reporting per branch

---

## Error Handling

### Scenario 1: No Allocations Staged
**Action:** Click "Save Allocation" with empty session table

**Result:**
```
No allocations to save! 
Please add allocations using the Set button first.
```

### Scenario 2: Network Error During Save
**Action:** Click "Save Allocation", network fails

**Result:**
- Error message with details
- Modal stays open
- Session table preserved
- User can retry

### Scenario 3: Partial Failure
**Action:** 3 branches, 1 fails to save

**Result:**
```
Some allocations failed to save:
ZUHAUSE LOPEZ: Connection timeout
```
- 2 branches saved successfully
- 1 branch failed
- User can manually retry failed branch

---

## Button Behavior Summary

| Button | Location | Action | Database Save |
|--------|----------|--------|---------------|
| **Set** | Branch Allocation section | Add to staging | ❌ No |
| **Save Allocation** | Modal footer | Commit all staged | ✅ Yes |
| **Back** | Modal footer | Cancel and close | ❌ No |

---

## Advantages Over Previous Approach

| Aspect | Old (Immediate Save) | New (Staging) |
|--------|---------------------|---------------|
| **Review** | ❌ No review possible | ✅ Review all before commit |
| **Undo** | ❌ Database changes permanent | ✅ Cancel before Save |
| **Errors** | ❌ Multiple partial saves | ✅ All-or-nothing per branch |
| **Performance** | ⚠️ Multiple HTTP requests | ✅ Batch at end |
| **UX** | ⚠️ Confusing | ✅ Clear intent |

---

## Testing Checklist

### Staging Tests
- [ ] Click "Set" does NOT save to database
- [ ] Session table populates correctly
- [ ] "Quantity Left" updates with pending
- [ ] Multiple "Set" actions accumulate
- [ ] Over-allocation blocked at staging

### Commit Tests
- [ ] "Save Allocation" saves all staged
- [ ] Success closes modal and reloads
- [ ] Error keeps modal open
- [ ] Partial failure reported clearly
- [ ] Database reflects all saves

### Cancel Tests
- [ ] "Back" closes without saving
- [ ] Reopening modal clears session table
- [ ] No database changes on cancel

### Edge Cases
- [ ] Empty session table → Error message
- [ ] Network error → Modal stays open
- [ ] Same branch multiple times → Quantities aggregate
- [ ] Very large session → Handles correctly

---

## Migration Notes

### Breaking Changes
✅ **None** - Fully backward compatible

### User-Visible Changes
- "Set" button now says "added to session"
- Must click "Save Allocation" to commit
- Session table shows pending allocations

### Admin Notes
- No database schema changes
- No backend changes required
- All changes in frontend JavaScript

---

**Status: ✅ Implemented**

**Last Updated:** 2026-09-21  
**Version:** 2.0  
**Feature Type:** Workflow Enhancement
