# Voucher Save Functionality Fix

## Problem
When clicking the SAVE button in `salesentry.php` or `salesentrylate.php`, the voucher data was not being saved to the database because the `sales_entry` table was missing the necessary voucher columns.

## Solution

### 1. Database Migration File Created
**File**: `add_voucher_to_sales_entry.php`

This migration adds two columns to the `sales_entry` table:
- `voucher_number` (VARCHAR 100) - Stores the voucher reference number
- `voucher_amount` (DECIMAL 10,2) - Stores the voucher monetary value

**How to run**: Access this file in your browser once to add the columns:
```
http://localhost/ZUHAUSE/add_voucher_to_sales_entry.php
```

### 2. Backend Update
**File**: `save_sales_entry.php`

Updated the INSERT statement to include voucher columns:
- Added `voucher_number` and `voucher_amount` to the INSERT query
- Added voucher data extraction from the POST data:
  ```php
  $voucher_number = isset($data['voucher_number']) && trim($data['voucher_number']) !== '' ? trim($data['voucher_number']) : null;
  $voucher_amount = isset($data['voucher_amount']) ? floatval($data['voucher_amount']) : 0.00;
  ```
- Updated the `bind_param` to include the voucher fields (changed from `"ssssssssdddddssdsssss"` to `"ssssssssdddddsssddsssss"`)

### 3. Frontend Updates

#### salesentry.php
Updated the data object sent to the server to include:
```javascript
voucher_amount: parseFormattedNumber(document.getElementById('voucherField').value),
voucher_number: document.getElementById('invoice_no').value.trim(),
```

#### salesentrylate.php
Updated the data object sent to the server to include:
```javascript
voucher_amount: parseFormattedNumber(document.getElementById('voucherField').value),
voucher_number: document.getElementById('invoice_no').value.trim(),
```

## How It Works

1. When items with vouchers are added to the sales entry, the voucher amount is automatically calculated and displayed in the "Voucher" field
2. When the SAVE button is clicked, the voucher data is now included in the request
3. The backend saves the voucher information to the `sales_entry` table along with all other sales data
4. The voucher amount is used in the total calculation (Total Amount = Grand Total - Discount - Voucher)

## Testing Steps

1. **Run the migration first**:
   - Access `http://localhost/ZUHAUSE/add_voucher_to_sales_entry.php`
   - Verify you see success messages for both columns

2. **Test Sales Entry**:
   - Go to Sales Entry page
   - Add an item that has voucher configured (has_voucher = 1 in items table)
   - Verify the Voucher field shows the correct amount
   - Click SAVE
   - Verify the entry is saved successfully

3. **Test Late Entry**:
   - Go to Late Entry page
   - Add an item that has voucher configured
   - Verify the Voucher field shows the correct amount
   - Click SAVE
   - Verify the entry is saved successfully

4. **Verify Database**:
   - Check the `sales_entry` table
   - Confirm the `voucher_number` and `voucher_amount` columns contain the correct data

## Related Files
- `add_voucher_columns.php` - Existing file that adds voucher columns to the `items` table
- Items need to have `has_voucher = 1` and `voucher_amount > 0` to trigger voucher calculation

## Notes
- The voucher number uses the invoice number as a reference
- Voucher amounts are automatically calculated based on items that have vouchers enabled
- The voucher field in the UI is read-only and automatically updated when items are added/removed
