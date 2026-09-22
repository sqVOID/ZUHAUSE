# Voucher Not Showing - Fix Instructions

## Problem
The voucher (₱95.00) shows in the sales entry form but doesn't appear in the report invoice modal.

## Root Cause
The `sales_entry` table is missing the `voucher_number` and `voucher_amount` columns, so the voucher data cannot be saved to the database.

## Solution - Follow These Steps

### ⚠️ IMPORTANT: Step 1 - Run the Database Migration
**You MUST do this first before the voucher will work!**

1. Open your web browser
2. Go to: `http://localhost/ZUHAUSE/add_voucher_to_sales_entry.php`
3. You should see success messages confirming the columns were added
4. This adds the following columns to the `sales_entry` table:
   - `voucher_number` (VARCHAR 100)
   - `voucher_amount` (DECIMAL 10,2)

### Step 2 - Test the Fix

1. Go to Sales Entry page
2. Add an item that has a voucher (has_voucher = 1 in items table)
3. The Voucher field should show the amount (e.g., ₱95.00)
4. Click SAVE
5. Go to Report page
6. Click on the invoice to open the modal
7. The voucher should now appear in the Payment Information section!

## Files Updated

### 1. `add_voucher_to_sales_entry.php` (NEW)
- Migration file to add voucher columns to sales_entry table

### 2. `save_sales_entry.php`
- Updated INSERT query to save voucher_number and voucher_amount

### 3. `salesentry.php`
- Updated JavaScript to send voucher data to backend

### 4. `salesentrylate.php`
- Updated JavaScript to send voucher data to backend

### 5. `get_invoice_details.php`
- Updated SELECT query to retrieve voucher_number and voucher_amount
- **This was the missing piece!** The backend wasn't fetching the voucher data.

### 6. `report.php`
- Updated invoiceModal to display discount and voucher in all payment method sections

## Verification

After running the migration, check that:

1. ✅ Discount shows in invoice modal (already working)
2. ✅ Voucher shows in invoice modal (should work after migration)
3. ✅ Both are displayed in red color
4. ✅ Voucher shows the voucher number in parentheses
5. ✅ Format: - ₱XX.XX (negative amount)

## Example Display in Invoice Modal

```
Payment Information
─────────────────────────────────
Payment Method:     Cash
Amount:             ₱2,500.00
Discount:           - ₱100.00
Voucher (260818-ZUHINFA-00012): - ₱95.00
─────────────────────────────────
Total Payment:      ₱2,305.00
```

## Troubleshooting

### Issue: Migration file shows errors
**Solution**: Check that:
- XAMPP MySQL is running
- Database connection in config.php is correct
- You have permission to ALTER tables

### Issue: Voucher still not showing after migration
**Solution**: 
1. Check browser console for JavaScript errors
2. Check that the item has `has_voucher = 1` in the items table
3. Verify the voucher was actually saved by checking the database:
   ```sql
   SELECT invoice_no, discount, voucher_amount, voucher_number 
   FROM sales_entry 
   ORDER BY id DESC 
   LIMIT 10;
   ```

### Issue: Old invoices don't show voucher
**Solution**: This is expected! Only new sales entries created AFTER:
1. Running the migration
2. Updating the files
...will have voucher data saved and displayed.

## Summary

The fix requires **3 things**:
1. ✅ Database columns added (run migration)
2. ✅ Backend saves voucher data (save_sales_entry.php)
3. ✅ Backend retrieves voucher data (get_invoice_details.php) - **NOW FIXED!**
4. ✅ Frontend displays voucher data (report.php)

All files have been updated. Just **run the migration** and test!
