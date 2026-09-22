# Allocation Set Button - Testing Checklist

## Pre-Test Setup

- [ ] Ensure migrations have been run:
  - `php create_purchase_order_allocations_table.php`
  - `php add_allocated_quantity_column.php`
  - `php fix_collation_issues.php` (if needed)

- [ ] Have a test PO with at least 3 items
- [ ] Have at least 3 active branches
- [ ] User is logged in with valid session

---

## Test 1: Basic Allocation ✅

### Steps:
1. Navigate to PO details page
2. Click ALLOCATE on an item with 0 allocations
3. Select a branch
4. Enter quantity (e.g., 10)
5. Click SET

### Expected Results:
- [ ] Loading state shows ("Saving...")
- [ ] Success alert appears
- [ ] Page reloads automatically
- [ ] Branch appears in "Branch Allocations" table
- [ ] Item's "Allocated Quantity" updates (e.g., 10/50)
- [ ] Database has new record in `purchase_order_allocations`

### SQL Verification:
```sql
SELECT * FROM purchase_order_allocations 
WHERE po_id = [your_po_id] AND family_code = 'FC-XXX';
```

---

## Test 2: Multiple Branches ✅

### Steps:
1. Allocate 10 units to Branch A
2. Allocate 15 units to Branch B
3. Allocate 20 units to Branch C

### Expected Results:
- [ ] All three branches appear in allocations table
- [ ] Item shows 45/X allocated (X = total quantity)
- [ ] Each branch has correct quantity
- [ ] Database has 3 separate records

---

## Test 3: Cumulative Allocation (Same Branch) ✅

### Steps:
1. Allocate 10 units to Branch A (first time)
2. Go to same item again
3. Allocate 5 more units to Branch A (second time)

### Expected Results:
- [ ] Success message: "Added 5 units (new total: 15)"
- [ ] Branch A shows 15 units total (not two separate rows)
- [ ] Database has 1 record with quantity = 15
- [ ] `updated_at` timestamp changed

### SQL Verification:
```sql
SELECT branch_name, quantity, updated_at 
FROM purchase_order_allocations 
WHERE po_id = [your_po_id] AND family_code = 'FC-XXX' AND branch_name = 'Branch A';
```

---

## Test 4: Over-Allocation Prevention ❌

### Scenario: Item has 50 units, 40 already allocated

### Steps:
1. Try to allocate 20 units to a new branch (would exceed 50)

### Expected Results:
- [ ] Frontend shows quantity left in RED (-10)
- [ ] Alert: "Allocated quantity exceeds available quantity!"
- [ ] Request NOT sent to server
- [ ] No database changes
- [ ] Button re-enabled for correction

---

## Test 5: Quantity Left Calculation ✅

### Scenario: Item with 100 units, 60 allocated

### Steps:
1. Click ALLOCATE
2. Observe modal

### Expected Results:
- [ ] Total Quantity: 100
- [ ] Already Allocated: 60
- [ ] Quantity Left: 40
- [ ] When typing 30: Quantity Left → 10
- [ ] When typing 40: Quantity Left → 0
- [ ] When typing 50: Quantity Left → -10 (RED)

---

## Test 6: Validation - No Branch ❌

### Steps:
1. Click ALLOCATE
2. Enter quantity but DON'T select branch
3. Click SET

### Expected Results:
- [ ] Alert: "Please select a branch!"
- [ ] No network request
- [ ] Modal stays open

---

## Test 7: Validation - Zero Quantity ❌

### Steps:
1. Click ALLOCATE
2. Select branch
3. Leave quantity as 0
4. Click SET

### Expected Results:
- [ ] Alert: "Please enter a quantity greater than 0!"
- [ ] No network request
- [ ] Modal stays open

---

## Test 8: Backend Validation - Invalid PO ❌

### Steps:
1. Manually call: `save_allocation.php` with `po_id=99999`

### Expected Results:
- [ ] Error response: "Purchase order not found."
- [ ] No database changes

---

## Test 9: Backend Validation - Invalid Family Code ❌

### Steps:
1. Manually call: `save_allocation.php` with non-existent family_code

### Expected Results:
- [ ] Error response: "Item not found in purchase order."
- [ ] No database changes

---

## Test 10: Full Allocation Flow ✅

### Scenario: Complete allocation of 100-unit item

### Steps:
1. Item: FC-001, Total: 100 units
2. Allocate 40 to Branch A
3. Allocate 30 to Branch B
4. Allocate 30 to Branch C
5. Try to allocate more

### Expected Results:
- [ ] After step 2: Shows 40/100
- [ ] After step 3: Shows 70/100
- [ ] After step 4: Shows 100/100 (fully allocated)
- [ ] Step 5: Shows 0 quantity left, prevents over-allocation

---

## Test 11: Data Persistence ✅

### Steps:
1. Allocate 25 units to Branch A
2. Close browser
3. Open browser and navigate back to PO details

### Expected Results:
- [ ] Branch A still shows in allocations table
- [ ] Quantity still shows 25
- [ ] Item still shows X/Y allocated correctly

---

## Test 12: Page Reload After Save ✅

### Steps:
1. Note current item allocated quantity (e.g., 20/50)
2. Allocate 10 more units
3. Observe page reload

### Expected Results:
- [ ] Page automatically reloads
- [ ] Item now shows 30/50
- [ ] New branch allocation visible
- [ ] URL stays the same
- [ ] No manual refresh needed

---

## Test 13: Cost Calculation ✅

### Scenario: Item costs ₱500.00 per unit

### Steps:
1. Allocate 10 units to Branch A

### Expected Results in allocations table:
- [ ] Branch A shows: ₱5,000.00 total cost
- [ ] Database `cost` field: 500.00
- [ ] Calculation: 10 × ₱500.00 = ₱5,000.00

### SQL Verification:
```sql
SELECT branch_name, quantity, cost, (quantity * cost) as total_cost
FROM purchase_order_allocations
WHERE po_id = [your_po_id];
```

---

## Test 14: Branch Code Lookup ✅

### Steps:
1. Allocate to a branch
2. Check database

### Expected Results:
- [ ] `branch_code` field is populated
- [ ] Matches code from `branches` table

### SQL Verification:
```sql
SELECT 
    poa.branch_name, 
    poa.branch_code, 
    b.branch_code as actual_code
FROM purchase_order_allocations poa
LEFT JOIN branches b ON b.branch_name = poa.branch_name
WHERE poa.po_id = [your_po_id];
```

---

## Test 15: Status Field ✅

### Steps:
1. Create new allocation
2. Check database

### Expected Results:
- [ ] `status` field = 'Waiting'
- [ ] `received_qty` field = 0
- [ ] Ready for receiving workflow

---

## Test 16: Multiple Items ✅

### Steps:
1. Allocate 10 units of FC-001 to Branch A
2. Allocate 15 units of FC-002 to Branch A
3. Check allocations table

### Expected Results:
- [ ] Branch A appears once in summary (not twice)
- [ ] Shows total cost and quantity across both items
- [ ] Database has 2 separate records (one per item)

---

## Test 17: Network Error Handling ❌

### Steps:
1. Disable network connection or block save_allocation.php
2. Try to allocate
3. Observe behavior

### Expected Results:
- [ ] Error alert: "Network error: ..."
- [ ] Button re-enabled
- [ ] User can retry
- [ ] No data corruption

---

## Test 18: Concurrent Allocations 🔀

### Steps:
1. User A: Start allocating 20 units (don't click SET yet)
2. User B: Allocate 15 units and click SET
3. User A: Now click SET

### Expected Results:
- [ ] User B's allocation saves first
- [ ] User A's allocation validated against new total
- [ ] If exceeds: Error message with updated available
- [ ] No over-allocation occurs

---

## Test 19: Browser Back Button ↩️

### Steps:
1. Allocate successfully
2. Page reloads
3. Click browser Back button
4. Try to allocate same item again

### Expected Results:
- [ ] Modal shows updated "Already Allocated" value
- [ ] Cannot over-allocate
- [ ] System reflects current database state

---

## Test 20: SQL Injection Prevention 🛡️

### Steps (Manual Testing):
1. Try injecting SQL in branch name field
2. Example: `Branch'; DROP TABLE purchase_order_allocations; --`

### Expected Results:
- [ ] Input treated as literal string
- [ ] No SQL commands execute
- [ ] No database damage
- [ ] Proper escaping applied

---

## Database Integrity Checks

### After All Tests, Verify:

```sql
-- Check allocated_quantity matches allocations sum
SELECT 
    poi.family_code,
    poi.quantity as total_qty,
    poi.allocated_quantity as recorded_allocated,
    COALESCE(SUM(poa.quantity), 0) as actual_allocated,
    CASE 
        WHEN poi.allocated_quantity = COALESCE(SUM(poa.quantity), 0) 
        THEN 'MATCH ✓' 
        ELSE 'MISMATCH ✗' 
    END as status
FROM purchase_order_items poi
LEFT JOIN purchase_order_allocations poa 
    ON poi.po_id = poa.po_id 
    AND poi.family_code = poa.family_code
WHERE poi.po_id = [your_po_id]
GROUP BY poi.id, poi.family_code;
```

**Expected:** All rows show 'MATCH ✓'

```sql
-- Check no over-allocations
SELECT 
    poi.family_code,
    poi.quantity as total_qty,
    COALESCE(SUM(poa.quantity), 0) as allocated,
    poi.quantity - COALESCE(SUM(poa.quantity), 0) as remaining,
    CASE 
        WHEN COALESCE(SUM(poa.quantity), 0) <= poi.quantity 
        THEN 'OK ✓' 
        ELSE 'OVER-ALLOCATED ✗' 
    END as status
FROM purchase_order_items poi
LEFT JOIN purchase_order_allocations poa 
    ON poi.po_id = poa.po_id 
    AND poi.family_code = poa.family_code
WHERE poi.po_id = [your_po_id]
GROUP BY poi.id, poi.family_code;
```

**Expected:** All rows show 'OK ✓'

---

## Performance Test

### Test Large Quantities:
- [ ] Allocate 10,000 units - works without timeout
- [ ] Allocate to 20 different branches - all save correctly
- [ ] Page load after many allocations - still fast

---

## Browser Compatibility

Test on:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Edge (latest)
- [ ] Safari (if available)
- [ ] Mobile browsers

---

## Summary Checklist

### Core Functionality
- [ ] Can create new allocation
- [ ] Can update existing allocation (same branch)
- [ ] Prevents over-allocation
- [ ] Validates all inputs
- [ ] Shows success/error messages
- [ ] Reloads page after save
- [ ] Updates database correctly

### Data Integrity
- [ ] allocated_quantity matches sum
- [ ] No over-allocations possible
- [ ] Cost calculated correctly
- [ ] Branch codes populated
- [ ] Timestamps accurate

### User Experience
- [ ] Loading state visible
- [ ] Error messages clear
- [ ] Success confirmation shown
- [ ] Modal closes appropriately
- [ ] Data persists after reload

### Security
- [ ] Session required
- [ ] SQL injection prevented
- [ ] Input validation works
- [ ] Business rules enforced

---

## Test Results Template

```
Date: __________
Tester: __________
PO ID Used: __________

PASSED: ___ / 20
FAILED: ___ / 20
SKIPPED: ___ / 20

Critical Issues:
[ List any blocking issues ]

Notes:
[ Any observations ]

Ready for Production: YES / NO
```

---

## Quick Smoke Test (5 minutes)

For rapid verification:

1. [ ] Create allocation → Success
2. [ ] Add to same branch → Cumulative works
3. [ ] Try to over-allocate → Blocked
4. [ ] Check database → Data correct
5. [ ] Reload page → Data persists

If all 5 pass: ✅ **Basic functionality working!**
