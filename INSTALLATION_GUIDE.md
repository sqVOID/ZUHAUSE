# Preorder2 Payment & Report Fix - Installation Guide

## Quick Start

Follow these steps to install the complete preorder payment update functionality:

### Step 1: Run Database Migration
Open your browser and navigate to:
```
http://localhost/ZUHAUSE/add_completed_at_to_preorders.php
```

This will:
- ✅ Add `completed_at` column to `preorders` table
- ✅ Update existing completed preorders with completion dates
- ✅ Show verification of the changes

**Expected Output:**
- Green success messages
- Table structure showing new `completed_at` column (highlighted in yellow)

### Step 2: Verify Files Are In Place

Make sure these files exist in your ZUHAUSE folder:

**New Files:**
- ✅ `save_preorder2_payment.php` - Backend API for saving payments
- ✅ `add_completed_at_to_preorders.php` - Database migration script
- ✅ `test_preorder2_payment.php` - Testing utility

**Modified Files:**
- ✅ `preorder2.php` - Updated with save functionality
- ✅ `fetch_preorder_report.php` - Updated to use completed_at date

**Documentation:**
- ✅ `PREORDER2_SAVE_FUNCTIONALITY.md` - Save feature documentation
- ✅ `PREORDER2_REPORT_DATE_FIX.md` - Date fix documentation
- ✅ `INSTALLATION_GUIDE.md` - This file

### Step 3: Test the Functionality

#### Test 1: Database Migration
1. Access: `http://localhost/ZUHAUSE/add_completed_at_to_preorders.php`
2. Verify: Green success messages
3. Confirm: `completed_at` column appears in table structure

#### Test 2: Payment Save Functionality
1. Go to: `http://localhost/ZUHAUSE/preorder2.php`
2. Click "Search Invoice No" button
3. Enter an existing preorder invoice (e.g., PRE-20260825-0002)
4. System shows breakdown with remaining balance
5. Click "PAYMENT" button
6. Enter payment details (Cash: ₱90.00)
7. Click "Save" button in modal (validates amount matches remaining balance)
8. Close modal
9. Click main "SAVE" button
10. Verify: Success message shows remaining balance = ₱0.00 and status = COMPLETED

#### Test 3: Report Display
1. Go to: `http://localhost/ZUHAUSE/preorderreport.php`
2. Set date range to include **today's date** (payment completion date)
3. Select branch
4. Click "Search"
5. Verify: Preorder appears with **today's date** as "Date Sold"
6. Change date range to **yesterday** (original creation date)
7. Verify: Same preorder does NOT appear on yesterday's report

## What Was Fixed

### Problem:
When a preorder was created on Aug 25 and the remaining payment was completed on Aug 26, the report only showed it on Aug 25 (creation date), not Aug 26 (completion date).

### Solution:
1. Added `completed_at` column to track when preorder is fully paid
2. Updated save functionality to set `completed_at` when status becomes "completed"
3. Updated report to show completed preorders on their completion date instead of creation date

## Complete Feature List

### 1. Payment Save Functionality
- ✅ Load existing preorder by invoice number
- ✅ Display payment breakdown (amount paid + remaining balance)
- ✅ Enter remaining payment via modal
- ✅ Validate payment matches remaining balance
- ✅ Save merged payment data to database
- ✅ Auto-update preorder status (pending → partial → completed)
- ✅ Set completion date automatically
- ✅ Clear form after successful save

### 2. Report Date Fix
- ✅ Show completed preorders on completion date (not creation date)
- ✅ Show pending/partial preorders on creation date
- ✅ Backward compatible with old preorders
- ✅ Date filtering works correctly for both scenarios

### 3. Payment Data Management
- ✅ Merge multiple payments into JSON array
- ✅ Preserve payment history
- ✅ Support multiple payment methods (Cash, E-Wallet, Online Banking, etc.)
- ✅ Calculate total paid and remaining balance automatically

## Technical Details

### Database Changes
```sql
-- New column added
ALTER TABLE preorders 
ADD COLUMN completed_at DATETIME NULL DEFAULT NULL 
COMMENT 'Date/time when preorder was fully paid' 
AFTER updated_at;
```

### API Endpoints
- **POST** `save_preorder2_payment.php` - Save remaining payment
  - Input: `{ preorder_id, payment_data }`
  - Output: `{ status, remaining_balance, preorder_status }`

### Payment Status Flow
```
pending → partial → completed
   ↓         ↓          ↓
created_at | created_at | completed_at ✅
```

### Report Date Logic
```javascript
if (status === 'completed' && completed_at !== null) {
    display_date = completed_at;  // Use completion date
} else {
    display_date = created_at;    // Use creation date
}
```

## Troubleshooting

### Issue: Migration fails
**Solution:** Check database permissions, ensure config.php is correct

### Issue: Save button doesn't work
**Solution:** 
1. Check browser console for JavaScript errors
2. Verify `save_preorder2_payment.php` exists
3. Ensure payment amount matches remaining balance

### Issue: Report doesn't show preorder on completion date
**Solution:**
1. Verify `completed_at` column exists in database
2. Check if `completed_at` is set for the preorder (run query)
3. Verify date filter includes the completion date

### Issue: "Payment total does not match remaining balance"
**Solution:**
1. Check the breakdown in payment modal
2. Ensure you entered the exact remaining balance amount
3. Try entering amount with 2 decimal places (e.g., 90.00)

## Testing Script

Optional: Use the test script to verify backend functionality:
```
http://localhost/ZUHAUSE/test_preorder2_payment.php
```

Update `$test_preorder_id` in the file to test with a specific preorder.

## Support Files

### For Developers:
- `PREORDER2_SAVE_FUNCTIONALITY.md` - Complete save feature documentation
- `PREORDER2_REPORT_DATE_FIX.md` - Complete date fix documentation
- `test_preorder2_payment.php` - Backend testing utility

### For Users:
- This installation guide
- In-app error messages and validation

## Rollback Instructions

If you need to rollback the changes:

### 1. Remove completed_at column (optional):
```sql
ALTER TABLE preorders DROP COLUMN completed_at;
```

### 2. Restore old files:
- Replace `fetch_preorder_report.php` with backup
- Remove `save_preorder2_payment.php`

### 3. Update preorder2.php:
- Restore original `savePreOrder()` function

## Success Criteria

✅ Migration script runs without errors
✅ Payment save works correctly
✅ Payment amount validation works
✅ Completed preorders show on completion date
✅ Pending/partial preorders show on creation date
✅ No data loss from existing preorders
✅ Reports are accurate and timely

## Next Steps After Installation

1. **Train Users:** Show them how to use preorder2.php for payment updates
2. **Monitor Reports:** Verify preorders appear on correct dates
3. **Backup Database:** Keep a backup before going live
4. **Test Edge Cases:** Test with various payment methods and amounts

## Questions?

If you encounter any issues, check:
1. Browser console for JavaScript errors
2. PHP error logs for backend errors
3. Database logs for SQL errors
4. This guide's troubleshooting section

---

**Installation Complete!** 🎉

The preorder payment update and report date fix are now fully installed and ready to use.
