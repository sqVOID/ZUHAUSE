# Claim Item Testing Checklist

## Pre-Testing Setup
- [ ] Access claimitem.php page
- [ ] Have test invoice with unclaimed items ready
- [ ] Open browser console (F12) to see debug logs

---

## Test Case 1: Normal Flow - Single Item, Full Quantity
**Objective**: Verify user can claim selected item with full quantity

### Steps:
1. [ ] Enter invoice number in "Invoice No" field
2. [ ] Click "Search" button
3. [ ] Verify unclaimed items appear in left panel
4. [ ] Verify blue info message appears: "You can only add items that are selected..."
5. [ ] Check checkbox for "MONARCH-FULL-FACE-HELMET" (quantity: 1)
6. [ ] Verify card border turns green and background changes
7. [ ] Enter or search for "MONARCH-FULL-FACE-HELMET" in Item Code field
8. [ ] Set Quantity to 1
9. [ ] Click "Add" button
10. [ ] Verify item appears in table below
11. [ ] Verify unclaimed item card shows: "Claimed: 1 | Remaining: 0"

**Expected Result**: ✅ Item successfully added to table

---

## Test Case 2: Attempt to Add Non-Selected Item
**Objective**: Verify validation prevents adding items not selected from unclaimed list

### Steps:
1. [ ] Search invoice with multiple unclaimed items
2. [ ] Check checkbox for ONLY "ITEM-A"
3. [ ] Leave "ITEM-B" unchecked
4. [ ] Try to add "ITEM-B" to the table
5. [ ] Verify error alert appears

**Expected Result**: ❌ Alert message:
```
Cannot add "ITEM-B". This item is not selected from the unclaimed items list. 
Please select it first from the unclaimed items panel.
```

---

## Test Case 3: Exceed Quantity Limit
**Objective**: Verify validation prevents exceeding unclaimed quantity

### Steps:
1. [ ] Search invoice with unclaimed item: HELMET (quantity: 1)
2. [ ] Check checkbox for HELMET
3. [ ] Add HELMET with quantity 1
4. [ ] Verify item appears in table (Claimed: 1 | Remaining: 0)
5. [ ] Try to add HELMET with quantity 1 again
6. [ ] Verify error alert appears

**Expected Result**: ❌ Alert message:
```
Cannot add 1 of "HELMET". Total quantity (2) would exceed the unclaimed quantity (1).

Already in table: 1
Unclaimed quantity available: 1
```

---

## Test Case 4: Multiple Partial Quantities
**Objective**: Verify user can add item in multiple batches up to limit

### Steps:
1. [ ] Search invoice with unclaimed item: GLOVES (quantity: 3)
2. [ ] Check checkbox for GLOVES
3. [ ] Add GLOVES with quantity 1
4. [ ] Verify shows: "Claimed: 1 | Remaining: 2"
5. [ ] Add GLOVES with quantity 1 again
6. [ ] Verify shows: "Claimed: 2 | Remaining: 1"
7. [ ] Add GLOVES with quantity 1 again
8. [ ] Verify shows: "Claimed: 3 | Remaining: 0"
9. [ ] Try to add GLOVES with quantity 1 again
10. [ ] Verify error alert appears

**Expected Result**: 
- ✅ First 3 additions succeed
- ❌ 4th addition shows error

---

## Test Case 5: Multiple Different Items
**Objective**: Verify user can add multiple different selected items

### Steps:
1. [ ] Search invoice with 3 unclaimed items
2. [ ] Check checkboxes for ALL 3 items
3. [ ] Add ITEM-A with its full quantity
4. [ ] Add ITEM-B with its full quantity
5. [ ] Add ITEM-C with its full quantity
6. [ ] Verify all 3 items appear in table
7. [ ] Try to add non-selected ITEM-D
8. [ ] Verify error alert appears

**Expected Result**: 
- ✅ Selected items added successfully
- ❌ Non-selected item rejected

---

## Test Case 6: Select All Function
**Objective**: Verify "Select All" button works correctly

### Steps:
1. [ ] Search invoice with multiple unclaimed items
2. [ ] Click "Select All" button
3. [ ] Verify ALL checkboxes are checked
4. [ ] Verify all cards have green border
5. [ ] Verify button text changes to "Deselect All"
6. [ ] Try adding any of the items
7. [ ] Verify they can all be added
8. [ ] Click "Deselect All" button
9. [ ] Verify all checkboxes are unchecked
10. [ ] Try adding items
11. [ ] Verify error message appears

**Expected Result**: 
- ✅ Select All enables all items
- ✅ Deselect All disables all items

---

## Test Case 7: Search Modal Validation
**Objective**: Verify search modal also validates selected items

### Steps:
1. [ ] Search invoice with HELMET (quantity: 1)
2. [ ] Check checkbox for HELMET
3. [ ] Click "Search" button in Quantity row
4. [ ] Search for "HELMET" in modal
5. [ ] Click "Select" on HELMET
6. [ ] Verify item code and description are populated
7. [ ] Now search for different item (e.g., "GLOVES") that is NOT selected
8. [ ] Click "Select" on GLOVES
9. [ ] Verify error alert appears
10. [ ] Verify modal closes

**Expected Result**: 
- ✅ Selected items can be chosen from modal
- ❌ Non-selected items show error

---

## Test Case 8: Delete Item and Re-add
**Objective**: Verify deleting item frees up quantity

### Steps:
1. [ ] Search invoice with HELMET (quantity: 2)
2. [ ] Check checkbox for HELMET
3. [ ] Add HELMET with quantity 2
4. [ ] Verify shows: "Claimed: 2 | Remaining: 0"
5. [ ] Click "X" button to delete item from table
6. [ ] Confirm deletion
7. [ ] Verify shows: "Remaining: 2" (or no claimed display)
8. [ ] Add HELMET with quantity 1
9. [ ] Verify item can be added again
10. [ ] Verify shows: "Claimed: 1 | Remaining: 1"

**Expected Result**: ✅ Deleting item restores available quantity

---

## Test Case 9: New Invoice Search Clears State
**Objective**: Verify searching new invoice clears previous selections

### Steps:
1. [ ] Search first invoice (Invoice A)
2. [ ] Check some items and add them to table
3. [ ] Verify table has items
4. [ ] Search different invoice (Invoice B)
5. [ ] Verify unclaimed items panel shows new invoice's items
6. [ ] Verify table is now EMPTY
7. [ ] Verify previous selections are cleared

**Expected Result**: ✅ New search completely resets the form

---

## Test Case 10: Uncheck Item After Adding to Table
**Objective**: Verify system prevents unchecking items that are in table

### Steps:
1. [ ] Search invoice with HELMET (quantity: 1)
2. [ ] Check checkbox for HELMET
3. [ ] Add HELMET to table with quantity 1
4. [ ] Try to uncheck the HELMET checkbox

**Current Behavior**: User CAN uncheck (this may need future validation)
**Suggested**: Should show warning "Cannot uncheck item that has been added to table"

---

## Test Case 11: Stock Availability Still Works
**Objective**: Verify existing stock validation still functions

### Steps:
1. [ ] Search invoice with item that has NO stock in inventory
2. [ ] Check checkbox for that item
3. [ ] Try to add that item
4. [ ] Verify stock availability error still appears

**Expected Result**: ❌ "Insufficient stock no available"

---

## Test Case 12: Serialized Items (IMEI)
**Objective**: Verify serialized items still work with new validation

### Steps:
1. [ ] Search invoice with serialized item (requires IMEI)
2. [ ] Check checkbox for that item
3. [ ] Add item without entering IMEI
4. [ ] Verify error: "Please enter IMEI for this serialized item"
5. [ ] Enter IMEI
6. [ ] Add item
7. [ ] Verify item is added successfully

**Expected Result**: 
- ✅ IMEI validation still works
- ✅ Unclaimed item validation works

---

## Test Case 13: Console Logging
**Objective**: Verify debug logging works for troubleshooting

### Steps:
1. [ ] Open browser console (F12)
2. [ ] Search invoice
3. [ ] Check an item checkbox
4. [ ] Verify console shows: "Selected unclaimed items: [...]"
5. [ ] Add item to table
6. [ ] Verify console shows quantity calculations
7. [ ] Verify console shows "Allowed unclaimed quantity: X"

**Expected Result**: ✅ Clear debug logs in console

---

## Browser Compatibility Testing
- [ ] Chrome/Edge (Chromium)
- [ ] Firefox
- [ ] Safari (if applicable)
- [ ] Check zoom levels: 77% (default), 100%, 125%

---

## Edge Cases

### Edge Case 1: Zero Quantity Item
- [ ] Unclaimed item with quantity: 0
- [ ] Try to check and add
- Expected: Should handle gracefully (may need validation)

### Edge Case 2: Very Large Quantities
- [ ] Unclaimed item with quantity: 100
- [ ] Try to add with quantity: 999
- Expected: ❌ Should reject (exceeds unclaimed)

### Edge Case 3: Decimal Quantities
- [ ] Try to enter quantity: 1.5
- Expected: Should round or reject

### Edge Case 4: Negative Quantities
- [ ] Try to enter quantity: -1
- Expected: ❌ Should reject (existing validation: quantity <= 0)

---

## Performance Testing
- [ ] Search invoice with 1 unclaimed item
- [ ] Search invoice with 10 unclaimed items
- [ ] Search invoice with 50+ unclaimed items
- [ ] Verify page doesn't lag when checking/unchecking
- [ ] Verify table renders quickly

---

## User Experience Verification
- [ ] Info message is visible and clear
- [ ] Error messages are understandable
- [ ] Visual feedback (green border) is obvious
- [ ] "Claimed/Remaining" display is helpful
- [ ] Select All button is easy to find
- [ ] Console logs don't spam normal users

---

## Regression Testing
- [ ] Customer details still display correctly
- [ ] Invoice search still works
- [ ] Item search modal still works
- [ ] IMEI validation still works
- [ ] Stock availability check still works
- [ ] Add button still works
- [ ] Delete button still works
- [ ] Save button works (if implemented)
- [ ] Remarks field works

---

## Post-Testing
- [ ] Document any bugs found
- [ ] Verify all validations work as expected
- [ ] Get user acceptance sign-off
- [ ] Deploy to production

---

## Known Limitations (If Any)
- User can uncheck items after adding to table (may need future validation)
- Console logs visible to end users (consider removing in production)
- Real-time remaining quantity only updates when table changes (not continuously)

---

## Sign-Off
| Role | Name | Date | Signature |
|------|------|------|-----------|
| Developer | | | |
| Tester | | | |
| Product Owner | | | |
| User | | | |

---

## Notes Section
Use this space to record any additional observations during testing:

```
[Your notes here]
```
