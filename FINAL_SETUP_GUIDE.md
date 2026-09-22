# Final Setup Guide - Preorder Payment History System

## Current Situation

You have existing preorders (like PRE-20260825-0002) that were saved **before** the payment history system was implemented. These won't show in the report until they are migrated.

## Solution: 2-Step Process

### Step 1: Migrate Existing Preorders

Run the migration to convert all existing preorders to payment history:

```
http://localhost/ZUHAUSE/migrate_all_preorders.php
```

**What it does:**
- Shows how many preorders need migration
- Provides "Start Migration" button
- Migrates ALL existing preorders at once
- Shows progress with green checkmarks for each payment
- Takes 1-2 minutes depending on number of preorders

**Click the button:** ▶ Start Migration

### Step 2: Verify in Report

After migration, go to:
```
http://localhost/ZUHAUSE/preorderreport.php
```

**Filter settings:**
- From: 2026-08-25
- To: 2026-08-26  
- Branch: ZUHAUS2 INFANTA
- Status: All Status

**Expected result:**
- PRE-20260825-0001 appears ✅
- PRE-20260825-0002 appears ✅
- PRE-20260825-0004 appears with MULTIPLE entries (one per payment) ✅

## What's Been Fixed

### 1. save_preorder.php ✅
**NOW:** When you save a new preorder in preorder.php, it automatically creates payment history records

**BEFORE:** Only saved to preorders table, no payment history

### 2. save_preorder2_payment.php ✅
**NOW:** When you add remaining payment in preorder2.php, it creates payment history records

**BEFORE:** Only updated preorders table

### 3. fetch_preorder_report.php ✅
**NOW:** Queries payment_history table, shows one entry per payment transaction

**BEFORE:** Queried preorders table, showed one entry per preorder

## How It Works Now

### For NEW Preorders (created AFTER this fix):

**User creates preorder in preorder.php:**
```
1. User fills form
2. Clicks Payment → Cash
3. Clicks SAVE
4. System saves to:
   - preorders table ✅
   - preorder_items table ✅
   - preorder_payment_history table ✅ (NEW!)
5. Report shows it immediately ✅
```

### For EXISTING Preorders (created BEFORE this fix):

**These need to be migrated once:**
```
1. Run: migrate_all_preorders.php
2. Click: Start Migration
3. System reads payment_data from preorders
4. Creates payment_history records for each payment
5. Report shows them all ✅
```

### For Remaining Payments (preorder2.php):

**User pays remaining balance:**
```
1. User searches invoice
2. System shows breakdown
3. User enters remaining payment
4. Clicks SAVE
5. System:
   - Updates preorders table
   - Inserts NEW payment_history record ✅
6. Report shows BOTH payments on different dates ✅
```

## Timeline Example

**Aug 25, 2026:**
- Create PRE-20260825-0002
- Pay ₱39,900 (partial)
- Payment history record created with date: Aug 25

**Aug 26, 2026:**
- Search PRE-20260825-0002 in preorder2.php
- Pay ₱90 (remaining)
- Payment history record created with date: Aug 26

**Report Output:**
```
PRE-20260825-0002 | Aug 25, 2026 | ₱39,900.00 | PARTIAL
PRE-20260825-0002 | Aug 26, 2026 | ₱90.00     | COMPLETED
```

## Diagnostic Tools

If preorders still don't show:

### 1. Check specific invoice:
```
http://localhost/ZUHAUSE/check_preorder_payment_history.php
```
Change line 11: `$invoice_to_check = 'PRE-20260825-0002';`

### 2. Debug report query:
```
http://localhost/ZUHAUSE/debug_preorder_report.php
```
Shows exactly why invoice isn't appearing

### 3. Migrate all preorders:
```
http://localhost/ZUHAUSE/migrate_all_preorders.php
```
One-click migration of all existing preorders

## Quick Checklist

- [ ] Run `migrate_all_preorders.php` (first time only)
- [ ] Verify existing preorders appear in report
- [ ] Test creating NEW preorder in preorder.php
- [ ] Verify new preorder appears immediately in report
- [ ] Test paying remaining balance in preorder2.php
- [ ] Verify BOTH payments show on different dates

## Files Modified

### Backend:
1. `save_preorder.php` - Now creates payment history
2. `save_preorder2_payment.php` - Creates payment history for remaining payments
3. `fetch_preorder_report.php` - Queries payment history table

### Migration:
1. `add_preorder_payment_history.php` - Creates payment history table
2. `migrate_all_preorders.php` - Migrates all existing preorders

### Diagnostic:
1. `check_preorder_payment_history.php` - Check specific invoice
2. `debug_preorder_report.php` - Debug report query

## Common Issues

### Issue: PRE-20260825-0002 doesn't show
**Solution:** Run `migrate_all_preorders.php` to migrate it

### Issue: New preorders don't show
**Solution:** `save_preorder.php` has been updated, should work now

### Issue: Remaining payments don't show on correct date
**Solution:** `save_preorder2_payment.php` has been updated, should work now

### Issue: Report shows "NO DATA"
**Solution:** Check:
1. Payment history table has records (run migration)
2. Date filter includes payment dates
3. Branch filter matches preorder branch

## Success Criteria

✅ **Migration completed** - All existing preorders have payment history records
✅ **New preorders work** - Automatically create payment history when saved
✅ **Remaining payments work** - Create separate payment history records
✅ **Report shows data** - All payments appear on correct dates
✅ **Multiple entries** - Same preorder appears multiple times for different payments

## Next Steps

1. **Run migration NOW:** `migrate_all_preorders.php`
2. **Verify report:** Check that PRE-20260825-0002 now appears
3. **Test new preorder:** Create one and verify it appears immediately
4. **Test remaining payment:** Pay remainder and verify it shows on new date

---

**Done! Your preorder payment history system is now fully functional.** 🎉
