# Claim Item - Quick Start Guide

## Quick Test Steps

### 1. Navigate to Claim Item Page
```
URL: http://localhost/MOTOGAM/claimitem.php
```

### 2. Search for Invoice
1. Enter invoice number in "Invoice No" field
2. Click "Search" button
3. ✅ Verify unclaimed items appear on left
4. ✅ Verify customer details appear on right
5. ✅ Verify blue info message appears

### 3. Select Items to Claim
1. Check the checkbox(es) for items you want to claim
2. ✅ Verify card border turns green
3. ✅ Verify card background changes to light green

### 4. Add Items to Claim Table
1. Enter or search for item code
2. Enter quantity (must not exceed unclaimed quantity)
3. Click "Add" button
4. ✅ Verify item appears in table below
5. ✅ Verify unclaimed card shows "Claimed: X | Remaining: Y"

### 5. Add Remarks (Optional)
1. Enter any remarks in the remarks textarea
2. Example: "Customer picked up items"

### 6. Save Claim
1. Click "Save" button
2. ✅ Verify button shows "Saving..."
3. ✅ Verify success message appears
4. ✅ Verify form is reset automatically

---

## Example Walkthrough

### Scenario: Claim 1 MONARCH-FULL-FACE-HELMET

```
Step 1: Search Invoice
  Input: INV-12345
  Click: Search
  
Step 2: Select Item
  Check: ☑ MONARCH-FULL-FACE-HELMET (Quantity: 1)
  
Step 3: Add to Table
  Item Code: MONARCH-FULL-FACE-HELMET (or search for it)
  Quantity: 1
  Click: Add
  
Step 4: Verify Table
  Table shows:
  ┌─────────────────────────┬──────┬──────────┬────────┐
  │ Item Description        │ IMEI │ Quantity │ Action │
  ├─────────────────────────┼──────┼──────────┼────────┤
  │ Monarch Full Face Helmet│      │    1     │  [X]   │
  └─────────────────────────┴──────┴──────────┴────────┘
  
  Unclaimed card shows:
  Claimed: 1 | Remaining: 0
  
Step 5: Add Remarks
  Input: "Customer picked up helmet"
  
Step 6: Save
  Click: Save
  Result: ✅ "Successfully claimed 1 item(s) for invoice INV-12345"
  Form: Automatically resets
```

---

## Quick Validation Tests

### Test 1: Can't Add Non-Selected Item ❌
```
1. Search invoice
2. Do NOT check any checkboxes
3. Try to add item
4. Expected: ❌ Error alert
```

### Test 2: Can't Exceed Quantity ❌
```
1. Search invoice with HELMET (qty: 1)
2. Check HELMET checkbox
3. Add HELMET qty 1 → ✅ Success
4. Try to add HELMET qty 1 again
5. Expected: ❌ Error alert "Total quantity (2) would exceed..."
```

### Test 3: Can't Save Without Items ❌
```
1. Search invoice
2. Check items
3. Do NOT add to table
4. Click Save
5. Expected: ❌ Error alert "Please add at least one item to the claim table"
```

### Test 4: Normal Flow ✅
```
1. Search invoice
2. Check items
3. Add items to table
4. Enter remarks
5. Click Save
6. Expected: ✅ Success message + form reset
```

---

## Common Issues & Solutions

### Issue: "Please search for an invoice first"
**Cause**: Trying to save without searching invoice  
**Solution**: Search for an invoice first

### Issue: "This item is not selected from the unclaimed items list"
**Cause**: Trying to add item without checking its checkbox  
**Solution**: Check the checkbox for the item first

### Issue: "Total quantity would exceed the unclaimed quantity"
**Cause**: Trying to add more than available  
**Solution**: Check remaining quantity in unclaimed card

### Issue: Table is empty after save
**Cause**: This is normal - form resets after successful save  
**Solution**: Search for a new invoice to start a new claim

---

## Database Verification

### Check Claimed Items
```sql
SELECT * FROM claimed_items ORDER BY claimed_at DESC LIMIT 10;
```

### Check Unclaimed Status Updates
```sql
SELECT * FROM unclaimed_freebies WHERE status = 'claimed' ORDER BY id DESC LIMIT 10;
```

### Check Specific Invoice Claims
```sql
SELECT 
    ci.invoice_no,
    ci.customer_name,
    ci.item_code,
    ci.item_description,
    ci.quantity,
    ci.claimed_by,
    ci.claimed_at,
    ci.remarks
FROM claimed_items ci
WHERE ci.invoice_no = 'INV-12345'
ORDER BY ci.claimed_at DESC;
```

### Check Claim Statistics
```sql
-- Total claims per day
SELECT 
    DATE(claimed_at) as claim_date,
    COUNT(*) as total_claims,
    SUM(quantity) as total_items
FROM claimed_items
GROUP BY DATE(claimed_at)
ORDER BY claim_date DESC;

-- Claims by user
SELECT 
    claimed_by,
    COUNT(*) as total_claims,
    SUM(quantity) as total_items
FROM claimed_items
GROUP BY claimed_by
ORDER BY total_claims DESC;

-- Claims by branch
SELECT 
    branch_code,
    COUNT(*) as total_claims,
    SUM(quantity) as total_items
FROM claimed_items
GROUP BY branch_code
ORDER BY total_claims DESC;
```

---

## Browser Console (F12)

### Expected Console Logs

When selecting items:
```
Selected unclaimed items: [{id: 5, item_code: "HELMET", ...}]
```

When adding items:
```
Checking stock availability: check_stock_availability.php?item_code=HELMET&imei=&qty=1...
Quantity in table: 0
Current quantity: 1
Total requested: 1
Allowed unclaimed quantity: 1
```

When saving:
```
Saving claimed items: {invoice_no: "INV-001", customer_name: "John Doe", ...}
Form reset complete
```

---

## Success Indicators

### Visual Feedback
- ✅ Green border on selected unclaimed items
- ✅ Items appear in claim table
- ✅ "Claimed: X | Remaining: Y" display updates
- ✅ Button shows "Saving..." during save
- ✅ Success alert message
- ✅ Form resets automatically

### Database Changes
- ✅ New records in `claimed_items` table
- ✅ `unclaimed_freebies.status` changed to 'claimed'
- ✅ Correct quantities recorded
- ✅ Customer details saved
- ✅ Claimed_by and claimed_at recorded

---

## Workflow Summary

```
Search → Select → Add → Save → Reset
  ↓       ↓       ↓      ↓       ↓
 INV    Check   Table  DB +   Clean
      Checkbox  Item   Alert   Form
```

---

## API Testing (Optional)

### Using Browser Console
```javascript
// Test save API directly
fetch('save_claimed_items.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        invoice_no: 'INV-TEST-001',
        customer_name: 'Test Customer',
        customer_address: '123 Test St',
        customer_contact: '09123456789',
        customer_email: 'test@test.com',
        remarks: 'Test claim',
        claimed_items: [{
            itemCode: 'TEST-ITEM',
            description: 'Test Item Description',
            imei: '',
            quantity: 1
        }],
        unclaimed_item_ids: [{
            id: 1,
            item_code: 'TEST-ITEM'
        }]
    })
})
.then(r => r.json())
.then(d => console.log('Response:', d))
.catch(e => console.error('Error:', e));
```

### Using Postman
```
POST http://localhost/MOTOGAM/save_claimed_items.php
Content-Type: application/json

{
  "invoice_no": "INV-TEST-001",
  "customer_name": "Test Customer",
  ...
}
```

---

## Next Steps After Testing

1. ✅ Verify all validation messages work
2. ✅ Verify database records are correct
3. ✅ Verify form reset works properly
4. ✅ Test with multiple items
5. ✅ Test with serialized items (IMEI)
6. ✅ Test partial claims
7. ✅ Test Select All functionality
8. ✅ Check console for errors
9. ✅ Get user acceptance
10. ✅ Deploy to production

---

## Support

If you encounter any issues:
1. Check browser console (F12) for JavaScript errors
2. Check PHP error logs for backend errors
3. Verify database connection in config.php
4. Ensure session is active
5. Check unclaimed_freebies table has data

---

## Summary

The claim item feature is now **FULLY FUNCTIONAL**! 

Users can:
- ✅ Search for invoices with unclaimed items
- ✅ Select which items to claim
- ✅ Add items to claim table (with validation)
- ✅ Save claims to database
- ✅ Track who claimed what and when
- ✅ Prevent duplicate claims (status update)

**Happy claiming! 🎉**
