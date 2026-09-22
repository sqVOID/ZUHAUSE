# Purchase Order System - Testing Checklist

## Pre-Testing Setup

### Database Setup
- [ ] Run migration: `php add_allocated_quantity_column.php`
- [ ] Run migration: `php create_purchase_order_allocations_table.php`
- [ ] Verify `purchase_orders` table exists
- [ ] Verify `purchase_order_items` table exists
- [ ] Verify `purchase_order_allocations` table exists
- [ ] Check that `allocated_quantity` column exists in `purchase_order_items`
- [ ] Check that `received_qty` column exists in `purchase_order_items`

### Test Data Prerequisites
- [ ] Have at least 3 active suppliers in the database
- [ ] Have at least 10 family codes in `family_codes` table
- [ ] Have at least 3 active branches in `branches` table
- [ ] Ensure user is logged in with valid session

---

## Test Case 1: Create Purchase Order - Happy Path

### Test Steps
1. [ ] Navigate to `createpurchaseorder.php`
2. [ ] Click on Supplier Company dropdown
3. [ ] Search for a supplier by typing at least 2 characters
4. [ ] Select a supplier from the dropdown
5. [ ] Select Terms: "30 Days"
6. [ ] Select Payment Due Date (should auto-calculate)
7. [ ] Enter Remarks: "Test PO - Quality Check"
8. [ ] Click "+ Add Item" button
9. [ ] Enter Family Code: Type valid code and press Enter
10. [ ] Select from search modal
11. [ ] Enter Quantity: 10
12. [ ] Enter Cost: 500.00
13. [ ] Verify line total shows ₱5,000.00
14. [ ] Add 2 more items following steps 8-13
15. [ ] Verify summary shows:
    - [ ] Items: 3
    - [ ] Total Quantity: Sum of all quantities
    - [ ] Total: Correct total cost
16. [ ] Click "Create Purchase Order"
17. [ ] Wait for success alert
18. [ ] Note the PO number shown (e.g., PO-2026-001)
19. [ ] Verify redirect to `purchaseorder.php`

### Expected Results
- [x] PO number generated in format PO-YYYY-NNN
- [x] Success message displayed
- [x] Redirected to PO listing page
- [x] New PO appears in the listing

### Database Verification
```sql
-- Check PO was created
SELECT * FROM purchase_orders WHERE po_number = 'PO-2026-XXX';

-- Check items were created
SELECT * FROM purchase_order_items WHERE po_number = 'PO-2026-XXX';

-- Verify totals match
SELECT 
    po.total_items,
    po.total_qty,
    po.total_cost,
    COUNT(poi.id) as actual_items,
    SUM(poi.quantity) as actual_qty,
    SUM(poi.total) as actual_cost
FROM purchase_orders po
LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
WHERE po.po_number = 'PO-2026-XXX'
GROUP BY po.id;
```

---

## Test Case 2: View Purchase Order Details

### Test Steps
1. [ ] Navigate to `purchaseorder.php`
2. [ ] Find the PO created in Test Case 1
3. [ ] Click on the PO row
4. [ ] Verify page URL: `purchaseorder-details.php?id=X`

### Expected Results - PO Information Table
- [ ] PO Number displays correctly
- [ ] Supplier Company displays correctly
- [ ] PO Date displays in DD/MM/YYYY format
- [ ] Terms display correctly:
  - [ ] "30 Days" for numeric terms
  - [ ] "Cash on Delivery" for COD
- [ ] Total Cost displays with ₱ symbol and 2 decimals
- [ ] Status badge displays with correct styling
- [ ] VIEW button is disabled (no items received yet)

### Expected Results - Items Table
- [ ] All items from PO are displayed
- [ ] Family Code column shows correct codes
- [ ] Cost column shows ₱ symbol and correct amounts
- [ ] Quantity column shows correct values
- [ ] Allocated Quantity shows "0/X" format
- [ ] ALLOCATE button is visible and clickable

### Expected Results - Branch Allocations Table
- [ ] Shows message: "No branch allocations yet..."
- [ ] Message guides user to use ALLOCATE button

### Database Verification
```sql
-- Verify data matches display
SELECT 
    po.*,
    COUNT(poi.id) as item_count
FROM purchase_orders po
LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
WHERE po.id = X
GROUP BY po.id;
```

---

## Test Case 3: Create PO with COD Terms

### Test Steps
1. [ ] Navigate to `createpurchaseorder.php`
2. [ ] Fill in Supplier Company
3. [ ] Select Terms: "Cash on Delivery"
4. [ ] Select Payment Due Date
5. [ ] Add 1 item
6. [ ] Click "Create Purchase Order"
7. [ ] Navigate to details page

### Expected Results
- [ ] PO creates successfully
- [ ] Terms display as "Cash on Delivery" (not "cod Days")
- [ ] All other fields display correctly

---

## Test Case 4: Empty States

### Test Steps
1. [ ] Create a PO with no items (should fail validation)
2. [ ] Navigate to a newly created PO details page
3. [ ] Verify empty states display correctly

### Expected Results
- [ ] Cannot submit PO without items
- [ ] "No branch allocations yet" message displays
- [ ] Message is clear and actionable

---

## Test Case 5: Validation Tests

### Test 5a: Required Fields
1. [ ] Leave Supplier Company empty → Click submit
2. [ ] Expected: Alert "Please enter the Supplier Company Name"
3. [ ] Leave Payment Due Date empty → Click submit
4. [ ] Expected: Alert about required field

### Test 5b: Invalid Family Code
1. [ ] Add item with non-existent family code
2. [ ] Enter quantity and cost
3. [ ] Click submit
4. [ ] Expected: Alert "Family Code 'XXX' does not exist in the system"

### Test 5c: Duplicate Family Codes
1. [ ] Add item with family code FC-001
2. [ ] Add another item with same family code FC-001
3. [ ] Expected: Alert "This family code 'FC-001' is already added"

### Test 5d: Invalid Quantities
1. [ ] Add item with quantity = 0
2. [ ] Click submit
3. [ ] Expected: Alert "Quantity must be greater than 0"
4. [ ] Add item with quantity = -5
5. [ ] Expected: Same alert or validation prevents negative

### Test 5e: Invalid Cost
1. [ ] Add item with cost = -100
2. [ ] Click submit
3. [ ] Expected: Alert "Cost cannot be negative"

---

## Test Case 6: Currency Formatting

### Test Steps
1. [ ] Create PO with item cost: 1234567.89
2. [ ] Verify display shows: 1,234,567.89 (with commas)
3. [ ] Create PO with item cost: 500
4. [ ] Verify display shows: 500.00 (with decimals)

### Expected Results
- [ ] All costs display with commas as thousand separators
- [ ] All costs display with exactly 2 decimal places
- [ ] ₱ symbol displays correctly

---

## Test Case 7: Search Functionality

### Test 7a: Supplier Search
1. [ ] Click on Supplier Company field
2. [ ] Type 2 characters
3. [ ] Verify dropdown shows matching results
4. [ ] Type more characters
5. [ ] Verify results filter further
6. [ ] Select a supplier
7. [ ] Verify field populates correctly

### Test 7b: Family Code Search
1. [ ] Add item row
2. [ ] Type family code and press Enter
3. [ ] Verify modal opens with search results
4. [ ] Verify results are relevant to search term
5. [ ] Click "Select" on a result
6. [ ] Verify modal closes
7. [ ] Verify family code field populates
8. [ ] Verify focus moves to quantity field

---

## Test Case 8: Real-time Calculations

### Test Steps
1. [ ] Add item with quantity: 10, cost: 500
2. [ ] Verify line total updates to ₱5,000.00
3. [ ] Change quantity to 15
4. [ ] Verify line total updates to ₱7,500.00
5. [ ] Change cost to 600
6. [ ] Verify line total updates to ₱9,000.00
7. [ ] Add second item
8. [ ] Verify summary updates:
   - [ ] Items count increases
   - [ ] Total Quantity increases
   - [ ] Total cost updates

### Expected Results
- [ ] All calculations happen instantly (no delay)
- [ ] All values are accurate
- [ ] Summary updates with each change

---

## Test Case 9: Remove Item

### Test Steps
1. [ ] Add 3 items to PO
2. [ ] Click "Remove" on second item
3. [ ] Verify row is removed
4. [ ] Verify summary updates correctly
5. [ ] Remove all items
6. [ ] Verify empty state message appears
7. [ ] Verify table is hidden

### Expected Results
- [ ] Items remove correctly
- [ ] Totals recalculate
- [ ] Empty state displays when all items removed

---

## Test Case 10: Back Button Navigation

### Test Steps
1. [ ] From PO details page, click Back button
2. [ ] Verify returns to `purchaseorder.php`
3. [ ] Navigate to details from different page
4. [ ] Use URL: `?from=reports`
5. [ ] Click Back button
6. [ ] Verify returns to `reports.php`

### Expected Results
- [ ] Back button works correctly
- [ ] Returns to correct page based on `from` parameter

---

## Test Case 11: Mobile Responsiveness

### Test Steps
1. [ ] Resize browser to mobile width (< 768px)
2. [ ] Verify sidebar is hidden
3. [ ] Click hamburger menu
4. [ ] Verify sidebar slides in
5. [ ] Verify tables are scrollable
6. [ ] Verify all buttons are accessible

### Expected Results
- [ ] Layout adjusts for mobile
- [ ] All functionality remains accessible
- [ ] No horizontal scrolling (except tables)

---

## Test Case 12: Multiple PO Creation

### Test Steps
1. [ ] Create PO #1 with 2 items
2. [ ] Note PO number (e.g., PO-2026-001)
3. [ ] Create PO #2 with 3 items
4. [ ] Note PO number (e.g., PO-2026-002)
5. [ ] Verify numbers increment correctly
6. [ ] View details of PO #1
7. [ ] Verify only PO #1 items display
8. [ ] View details of PO #2
9. [ ] Verify only PO #2 items display

### Expected Results
- [ ] Each PO gets unique sequential number
- [ ] POs don't interfere with each other
- [ ] Data isolation is correct

---

## Test Case 13: Edge Cases

### Test 13a: Very Large Numbers
1. [ ] Create PO with quantity: 999999
2. [ ] Create PO with cost: 999999.99
3. [ ] Verify calculations work correctly
4. [ ] Verify display formatting is correct

### Test 13b: Decimal Precision
1. [ ] Enter cost: 123.456 (3 decimals)
2. [ ] Verify system rounds to 123.46 (2 decimals)
3. [ ] Verify total calculation uses rounded value

### Test 13c: Special Characters in Remarks
1. [ ] Enter remarks with special characters: @#$%
2. [ ] Submit PO
3. [ ] View details
4. [ ] Verify remarks display correctly (no corruption)

---

## Test Case 14: Concurrent User Testing

### Test Steps
1. [ ] User A creates PO
2. [ ] User B creates PO at same time
3. [ ] Verify both POs get unique numbers
4. [ ] Verify no number collision
5. [ ] Verify both POs save correctly

---

## Test Case 15: Session Handling

### Test Steps
1. [ ] Start creating PO
2. [ ] Let session expire (wait or manually clear)
3. [ ] Try to submit PO
4. [ ] Verify redirect to login
5. [ ] Login again
6. [ ] Verify can access pages correctly

---

## Test Case 16: XSS Prevention

### Test Steps
1. [ ] Try entering `<script>alert('XSS')</script>` in remarks
2. [ ] Submit PO
3. [ ] View details
4. [ ] Verify script doesn't execute
5. [ ] Verify text displays as plain text

### Expected Results
- [ ] No script execution
- [ ] Special characters are escaped
- [ ] Display shows escaped version or plain text

---

## Test Case 17: SQL Injection Prevention

### Test Steps
1. [ ] Try entering `'; DROP TABLE purchase_orders; --` in remarks
2. [ ] Submit PO
3. [ ] Verify PO saves correctly
4. [ ] Verify tables still exist
5. [ ] Verify no SQL error

### Expected Results
- [ ] Input treated as string data
- [ ] No SQL commands execute
- [ ] Database remains intact

---

## Performance Testing

### Load Testing
- [ ] Create 100 POs
- [ ] Verify page load time < 2 seconds
- [ ] Verify search remains fast
- [ ] Verify no memory issues

### Database Performance
```sql
-- Check query performance
EXPLAIN SELECT * FROM purchase_orders 
WHERE po_number = 'PO-2026-001';

-- Should use index on po_number

EXPLAIN SELECT * FROM purchase_order_items 
WHERE po_id = 123;

-- Should use index on po_id
```

---

## Browser Compatibility

Test on:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Edge (latest)
- [ ] Safari (if available)
- [ ] Mobile Chrome
- [ ] Mobile Safari

---

## Regression Testing

After any code changes, re-run:
- [ ] Test Case 1 (Create PO - Happy Path)
- [ ] Test Case 2 (View PO Details)
- [ ] Test Case 5 (All Validation Tests)
- [ ] Test Case 8 (Real-time Calculations)

---

## Bug Report Template

When a test fails, document:

```
BUG REPORT #XXX

TITLE: [Brief description]

SEVERITY: [Critical/High/Medium/Low]

TEST CASE: [Which test case failed]

STEPS TO REPRODUCE:
1.
2.
3.

EXPECTED RESULT:
[What should happen]

ACTUAL RESULT:
[What actually happened]

SCREENSHOTS:
[Attach if applicable]

BROWSER/ENVIRONMENT:
- Browser: 
- OS:
- PHP Version:
- MySQL Version:

ERROR MESSAGES:
[Console errors, PHP errors, etc.]

DATABASE STATE:
[Query results if relevant]
```

---

## Sign-off Checklist

Before marking testing as complete:

- [ ] All critical test cases pass
- [ ] No blocking bugs remain
- [ ] Database migrations run successfully
- [ ] Documentation is accurate
- [ ] Code follows security best practices
- [ ] Performance is acceptable
- [ ] Mobile experience is functional
- [ ] Multiple browsers tested
- [ ] Edge cases handled
- [ ] Error messages are user-friendly

---

## Test Summary Report Template

```
TESTING SUMMARY REPORT
Date: ___________
Tester: ___________

TOTAL TEST CASES: XX
PASSED: XX
FAILED: XX
BLOCKED: XX
SKIPPED: XX

PASS RATE: XX%

CRITICAL ISSUES: XX
HIGH PRIORITY ISSUES: XX
MEDIUM PRIORITY ISSUES: XX
LOW PRIORITY ISSUES: XX

NOTES:
[Any additional observations]

RECOMMENDATION:
[ ] Ready for production
[ ] Needs fixes before production
[ ] Requires additional testing

SIGNED: ___________
```

---

## Post-Testing Tasks

After testing is complete:

- [ ] Document all found bugs
- [ ] Prioritize bug fixes
- [ ] Update user documentation if needed
- [ ] Create user training materials
- [ ] Plan deployment strategy
- [ ] Set up monitoring/logging
- [ ] Create backup strategy
- [ ] Document known limitations
