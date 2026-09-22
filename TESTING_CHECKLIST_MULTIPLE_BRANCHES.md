# Testing Checklist - Multiple Branch Support for Sub-admin

## Test Scenario
**User Type**: Sub-admin  
**Assigned Branches**: "MOTOGAM LPA G 01 Y, Greentelcom SM Lucena"  
**Expected Behavior**: Should see data from BOTH branches, but NOT from other branches

---

## Pre-Test Requirements

### 1. Verify Account Setup
- [ ] Go to Account Registration
- [ ] Find the Sub-admin account
- [ ] Verify the Branch field shows: "MOTOGAM LPA G 01 Y, Greentelcom SM Lucena"
- [ ] System Level should be: "Sub-admin"

### 2. Force Session Refresh
- [ ] **CRITICAL**: Logout completely from the Sub-admin account
- [ ] Login again as the Sub-admin user
- [ ] This ensures the session has the updated branch information

---

## Test Suite

### Test 1: Branch Configuration Check
**URL**: `http://localhost/MOTOGAM/test_multiple_branches.php`

Expected Results:
- [ ] Shows 2 branches found
- [ ] Branch 1: "MOTOGAM LPA G 01 Y" with valid branch code
- [ ] Branch 2: "Greentelcom SM Lucena" with valid branch code
- [ ] Both branches show green checkmarks (✓)
- [ ] No "NOT FOUND" errors

**If this test fails**: The branch names in the account don't match the branch names in the branches table. Fix the spelling/spacing.

---

### Test 2: Purchase Order Access
**URL**: `http://localhost/MOTOGAM/purchaseorder.php`

Expected Results:
- [ ] Page shows "Purchase Order List"
- [ ] Purchase orders from "MOTOGAM LPA G 01 Y" are visible
- [ ] Purchase orders from "Greentelcom SM Lucena" are visible
- [ ] Purchase orders from OTHER branches are NOT visible
- [ ] No "No PO Displayed yet" message (if POs exist for these branches)

**How to verify**:
1. Check the branch column in the PO list
2. All visible POs should only be from your 2 assigned branches

---

### Test 3: Stock on Hand
**URL**: `http://localhost/MOTOGAM/sohandunit.php`

Expected Results:
- [ ] Branch dropdown shows BOTH assigned branches
- [ ] Stock data from "MOTOGAM LPA G 01 Y" is visible
- [ ] Stock data from "Greentelcom SM Lucena" is visible  
- [ ] Stock from other branches is NOT visible
- [ ] Can select each branch individually from dropdown

---

### Test 4: Sales Entry
**URL**: `http://localhost/MOTOGAM/salesentry.php`

Expected Results:
- [ ] Page header shows branch name (not "ALL BRANCHES")
- [ ] Can create sales for items from assigned branches
- [ ] Cannot see/access items from other branches

---

### Test 5: Void Sales
**URL**: `http://localhost/MOTOGAM/voidsales.php`

Expected Results:
- [ ] Shows sales from "MOTOGAM LPA G 01 Y"
- [ ] Shows sales from "Greentelcom SM Lucena"
- [ ] Does NOT show sales from other branches
- [ ] All sales in the list should be from the 2 assigned branches only

---

### Test 6: Refund Report
**URL**: `http://localhost/MOTOGAM/refundreport.php`

Expected Results:
- [ ] Shows refunds from "MOTOGAM LPA G 01 Y"
- [ ] Shows refunds from "Greentelcom SM Lucena"
- [ ] Does NOT show refunds from other branches
- [ ] Branch column only shows the 2 assigned branches

---

### Test 7: Stock Transfer Report
**URL**: `http://localhost/MOTOGAM/stocktransferreport.php`

Expected Results:
- [ ] Shows transfers involving assigned branches
- [ ] Does NOT show transfers between other branches only
- [ ] Branch filter dropdown shows only assigned branches (if not Super-Admin)

---

### Test 8: Void Sales Report
**URL**: `http://localhost/MOTOGAM/voidsalesreport.php`

Expected Results:
- [ ] Shows voided sales from assigned branches only
- [ ] Branch column shows "MOTOGAM LPA G 01 Y" or "Greentelcom SM Lucena"
- [ ] No voided sales from other branches

---

### Test 9: View Purchase Order Details
**Action**: Click on any PO from Test 2

Expected Results:
- [ ] Can view PO details if it belongs to assigned branches
- [ ] Cannot view PO details if it belongs to other branches
- [ ] No unauthorized access errors

---

## Common Issues & Solutions

### Issue 1: Still seeing "ALL BRANCHES"
**Solution**: 
- Logout and login again
- Clear browser cache
- Check that System Level is "Sub-admin" not "Super-Admin"

### Issue 2: Not seeing any data (empty tables)
**Possible Causes**:
1. **No data exists for those branches** - This is OK, not a bug
2. **Branch names don't match** - Check spelling in Account Registration vs Branches table
3. **Session not refreshed** - Logout and login again

### Issue 3: Seeing data from wrong branches
**Solution**:
- This indicates the fix didn't work properly
- Check the PHP error log for SQL errors
- Verify the branch codes are correct in the database

### Issue 4: "Branch NOT FOUND" in test script
**Solution**:
- The branch name in the account doesn't exist in branches table
- Fix the branch name or add the branch to the branches table
- Ensure exact match (spaces, capitalization, special characters)

---

## Super-Admin Comparison Test

To verify Sub-admin restrictions are working:

1. **Login as Super-Admin**
   - [ ] Should see "ALL BRANCHES" in headers
   - [ ] Should see data from ALL branches
   - [ ] Branch dropdowns should show ALL branches

2. **Login as Sub-admin** 
   - [ ] Should see specific branch names (not "ALL BRANCHES")
   - [ ] Should see data from assigned branches ONLY
   - [ ] Branch dropdowns should show assigned branches ONLY

---

## Final Verification

After completing all tests:
- [ ] Sub-admin can access data from ALL assigned branches
- [ ] Sub-admin CANNOT access data from non-assigned branches
- [ ] No "ALL BRANCHES" text appears for Sub-admin users
- [ ] All pages work correctly with multiple branch assignments
- [ ] No PHP errors in the error log

---

## Test Results Log

**Date Tested**: _______________  
**Tester Name**: _______________  
**Sub-admin Account**: _______________  
**Assigned Branches**: _______________

### Results Summary
- Total Tests: 9
- Passed: _____ / 9
- Failed: _____ / 9

### Failed Tests (if any):
_______________________________________
_______________________________________
_______________________________________

### Notes:
_______________________________________
_______________________________________
_______________________________________

---

## Report Issues

If you encounter any issues:
1. Note which test failed
2. Check PHP error logs: `xampp/apache/logs/error.log`
3. Take screenshots of unexpected behavior
4. Document the exact steps to reproduce the issue
