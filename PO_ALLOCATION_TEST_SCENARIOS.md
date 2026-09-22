# Purchase Order Allocation - Testing Scenarios

## Test Scenario 1: Create PO Without Allocation

### Steps:
1. Navigate to `createpurchaseorder.php`
2. Fill in supplier details:
   - Supplier Company: "Test Supplier Co."
   - Terms: 30 Days
   - Payment Due Date: (select future date)
3. Add items:
   - Family Code: ABC-123, Quantity: 10, Cost: 1000
   - Family Code: DEF-456, Quantity: 5, Cost: 500
4. Click "Create Purchase Order"

### Expected Results:
✓ PO is created successfully (e.g., PO-2026-015)
✓ PO appears in `purchaseorder.php` with status "Pending"
❌ PO does NOT appear in `purchaseorderreceive.php` (even when filter = "Pending")
✓ Message shown: "Note: Only purchase orders with branch allocations are shown here..."

### Database Verification:
```sql
-- PO exists in purchase_orders
SELECT * FROM purchase_orders WHERE po_number = 'PO-2026-015';
-- Status should be 'Pending'

-- No allocations exist yet
SELECT * FROM purchase_order_allocations WHERE po_id = (
    SELECT id FROM purchase_orders WHERE po_number = 'PO-2026-015'
);
-- Should return 0 rows
```

---

## Test Scenario 2: Allocate Items to One Branch

### Steps:
1. From `purchaseorder.php`, click "Details" on PO-2026-015
2. In `purchaseorder-details.php`, click "ALLOCATE" for ABC-123
3. Select Branch: "Main Branch"
4. Enter Quantity: 10
5. Click "Set"
6. Repeat for DEF-456: allocate 5 to "Main Branch"

### Expected Results:
✓ Allocation is saved successfully
✓ Branch Allocations table now shows "Main Branch" with total allocated items
✓ PO NOW appears in `purchaseorderreceive.php` when filter = "Pending"
✓ PO is visible only to users who have access to "Main Branch"

### Database Verification:
```sql
-- Allocations exist
SELECT * FROM purchase_order_allocations 
WHERE po_id = (SELECT id FROM purchase_orders WHERE po_number = 'PO-2026-015');
-- Should return 2 rows (one for ABC-123, one for DEF-456)

-- Verify allocation details
SELECT branch_name, family_code, quantity 
FROM purchase_order_allocations 
WHERE po_id = (SELECT id FROM purchase_orders WHERE po_number = 'PO-2026-015');
-- Expected:
-- Main Branch | ABC-123 | 10
-- Main Branch | DEF-456 | 5
```

---

## Test Scenario 3: Allocate Items to Multiple Branches

### Steps:
1. Create new PO-2026-016 with:
   - Family Code: GHI-789, Quantity: 20, Cost: 2000
2. In `purchaseorder-details.php`, click "ALLOCATE" for GHI-789
3. Select Branch: "Main Branch", Quantity: 10, click "Set"
4. Click "ALLOCATE" again for GHI-789
5. Select Branch: "Branch 2", Quantity: 10, click "Set"

### Expected Results:
✓ Both allocations are saved
✓ PO appears in `purchaseorderreceive.php`
✓ Main Branch users can see the PO
✓ Branch 2 users can see the PO
✓ Other branch users cannot see the PO (unless Super-Admin)

### Database Verification:
```sql
SELECT branch_name, family_code, quantity 
FROM purchase_order_allocations 
WHERE po_id = (SELECT id FROM purchase_orders WHERE po_number = 'PO-2026-016');
-- Expected:
-- Main Branch | GHI-789 | 10
-- Branch 2    | GHI-789 | 10
```

---

## Test Scenario 4: Remove Allocation

### Steps:
1. Open PO-2026-015 in `purchaseorder-details.php`
2. In Branch Allocations section, click "REMOVE" for "Main Branch"
3. Confirm deletion
4. Navigate to `purchaseorderreceive.php`

### Expected Results:
✓ Allocation is removed successfully
❌ PO-2026-015 no longer appears in `purchaseorderreceive.php`
✓ PO still exists in `purchaseorder.php` with status "Pending"
✓ Message shown: "Note: Only purchase orders with branch allocations are shown here..."

### Database Verification:
```sql
-- No allocations remain
SELECT COUNT(*) FROM purchase_order_allocations 
WHERE po_id = (SELECT id FROM purchase_orders WHERE po_number = 'PO-2026-015');
-- Should return 0
```

---

## Test Scenario 5: Receive PO After Allocation

### Steps:
1. Ensure PO-2026-016 has allocations
2. Navigate to `purchaseorderreceive.php`
3. Select Status Filter: "Pending"
4. Verify PO-2026-016 appears
5. Click "VIEW" on PO-2026-016
6. Enter serial numbers (if applicable)
7. Enter invoice number
8. Click "Receive PO"

### Expected Results:
✓ PO status changes to "Received"
✓ Items are added to `stock_on_hand` table
✓ PO still appears in `purchaseorderreceive.php` (now with "Received" status filter)
✓ Serial numbers are saved correctly
✓ Inventory is updated

### Database Verification:
```sql
-- Status updated
SELECT status FROM purchase_orders WHERE po_number = 'PO-2026-016';
-- Should return 'Received'

-- Stock added
SELECT * FROM stock_on_hand WHERE dr_number = 'PO-2026-016';
-- Should return rows for all received items

-- Serial numbers saved (if applicable)
SELECT serial_number FROM purchase_order_items 
WHERE po_number = 'PO-2026-016' AND serial_number IS NOT NULL;
```

---

## Test Scenario 6: Partial Allocation

### Steps:
1. Create PO-2026-017 with:
   - Item 1: JKL-111, Quantity: 30
   - Item 2: MNO-222, Quantity: 20
2. Allocate only Item 1 (JKL-111) to "Main Branch": 30 units
3. Do NOT allocate Item 2 (MNO-222)
4. Check `purchaseorderreceive.php`

### Expected Results:
✓ PO-2026-017 DOES appear (because at least one allocation exists)
✓ User can still receive the PO
✓ Only allocated items should be received at the designated branch

### Database Verification:
```sql
-- Partial allocation
SELECT family_code, quantity FROM purchase_order_allocations 
WHERE po_id = (SELECT id FROM purchase_orders WHERE po_number = 'PO-2026-017');
-- Should return only 1 row:
-- JKL-111 | 30
```

---

## Test Scenario 7: Status Filter Testing

### Setup:
- PO-A: Status = Pending, HAS allocations
- PO-B: Status = Pending, NO allocations
- PO-C: Status = Incomplete, HAS allocations
- PO-D: Status = Received, HAS allocations
- PO-E: Status = Completed, HAS allocations

### Test Each Filter:

#### Filter: "Pending"
Expected: Shows only PO-A (Pending + Allocated)
Not shown: PO-B (no allocation)

#### Filter: "Incomplete"
Expected: Shows only PO-C (Incomplete + Allocated)

#### Filter: "Received"
Expected: Shows only PO-D (Received + Allocated)

#### Filter: "All Status"
Expected: Shows PO-A, PO-C, PO-D, PO-E (all with allocations)
Not shown: PO-B (no allocation)

#### Filter: "Pending & Incomplete" (auto-selected)
Expected: Shows PO-A and PO-C (both have allocations)

---

## Test Scenario 8: Branch Access Control

### Setup Users:
- User A: Super-Admin (access to ALL branches)
- User B: Sub-Admin (access to "Main Branch" only)
- User C: Sub-Admin (access to "Branch 2" only)

### Test Data:
- PO-X: Allocated to "Main Branch"
- PO-Y: Allocated to "Branch 2"
- PO-Z: Allocated to both "Main Branch" and "Branch 2"

### Expected Results:

| User      | Can See PO-X | Can See PO-Y | Can See PO-Z |
|-----------|--------------|--------------|--------------|
| User A    | ✓ Yes        | ✓ Yes        | ✓ Yes        |
| User B    | ✓ Yes        | ❌ No         | ✓ Yes        |
| User C    | ❌ No         | ✓ Yes        | ✓ Yes        |

---

## Test Scenario 9: Search Functionality

### Steps:
1. Create multiple POs with allocations
2. In `purchaseorderreceive.php`, use search box
3. Search for: PO number (e.g., "PO-2026-015")
4. Search for: Supplier name (e.g., "Test Supplier")

### Expected Results:
✓ Search results only show POs that:
  - Match the search term, AND
  - Have allocations in the system
✓ POs without allocations are excluded even if they match the search term

---

## Test Scenario 10: Date Filter

### Steps:
1. Create PO-2026-020 today with allocations
2. Create PO-2026-021 yesterday with allocations  
3. Create PO-2026-022 today without allocations
4. In `purchaseorderreceive.php`:
   - Select date: Today
   - Select status: "All Status"

### Expected Results:
✓ Shows PO-2026-020 (today + allocated)
❌ Does NOT show PO-2026-021 (different date)
❌ Does NOT show PO-2026-022 (no allocation)

---

## Performance Test Scenario

### Setup:
- Create 100 POs total
- 50 with allocations
- 50 without allocations

### Test:
1. Navigate to `purchaseorderreceive.php`
2. Select status: "All Status"
3. Measure page load time

### Expected Results:
✓ Page loads efficiently (< 2 seconds)
✓ Shows exactly 50 POs (only those with allocations)
✓ No SQL errors or timeouts
✓ Pagination works correctly (if implemented)

### Database Query Performance:
```sql
-- Check query execution time
EXPLAIN SELECT * FROM purchase_orders po
WHERE EXISTS (
    SELECT 1 FROM purchase_order_allocations poa 
    WHERE poa.po_id = po.id
);

-- Recommended index for performance:
CREATE INDEX idx_po_allocations_po_id 
ON purchase_order_allocations(po_id);
```

---

## Regression Testing

### Areas to Verify:
1. ✓ PO creation still works normally
2. ✓ PO listing page shows all POs (including non-allocated)
3. ✓ Allocation functionality unchanged
4. ✓ Receiving functionality unchanged
5. ✓ Stock updates work correctly
6. ✓ Serial number handling works
7. ✓ Invoice number saving works
8. ✓ Status transitions work (Pending → Received → Completed)
9. ✓ Edit/Modify PO still works
10. ✓ Branch permissions still enforced
11. ✓ Super-Admin can see all allocated POs
12. ✓ Reports and exports still work

---

## Bug Testing Checklist

### Test for Common Issues:

- [ ] SQL injection in search box
- [ ] XSS in PO details display
- [ ] CSRF in allocation forms
- [ ] Race condition with simultaneous allocations
- [ ] NULL handling in allocation queries
- [ ] Division by zero in quantity calculations
- [ ] Orphaned allocations (PO deleted but allocations remain)
- [ ] Duplicate allocations for same branch/item
- [ ] Over-allocation (allocating more than available)
- [ ] Session timeout during allocation
- [ ] Browser back button behavior
- [ ] Concurrent user access

---

## Rollback Testing

### Steps:
1. Note current PO data state
2. Apply the rollback instructions from PO_ALLOCATION_WORKFLOW_CHANGES.md
3. Refresh `purchaseorderreceive.php`
4. Verify all POs appear (including non-allocated)

### Expected Results After Rollback:
✓ All pending POs appear (old behavior restored)
✓ No SQL errors
✓ System works as it did before changes

---

## Sign-Off Checklist

Before deploying to production:

- [ ] All test scenarios passed
- [ ] Performance is acceptable
- [ ] No regression issues found
- [ ] User documentation updated
- [ ] Training materials prepared
- [ ] Backup created before deployment
- [ ] Rollback plan tested and ready
- [ ] Stakeholders notified of changes
- [ ] Support team briefed on new behavior

---

## Support Documentation

### Common User Questions:

**Q: Why don't I see my PO in the receive page?**
A: The PO must have items allocated to branches first. Go to Purchase Order Details and allocate items.

**Q: I allocated items but still don't see the PO?**
A: Check if you have permission to access the branch where items were allocated. Contact your administrator.

**Q: Can I receive a PO without allocating all items?**
A: Yes, as long as at least one item is allocated. You can receive the allocated items.

**Q: What happens if I remove all allocations?**
A: The PO will no longer appear in the receive page until items are allocated again.

**Q: Can I change allocations after receiving?**
A: Contact your Super-Admin. This may require special handling depending on what has been received.

---

**Last Updated:** August 7, 2026
**Tested By:** [Your Name]
**Approved By:** [Manager Name]
