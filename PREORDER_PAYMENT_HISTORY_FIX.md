# Preorder Payment History - Multiple Report Entries Fix

## Problem Description

When a preorder has multiple payments on different dates, the report should show **separate entries for each payment**, not just one combined entry.

### Example Scenario:
- **Aug 25, 2026:** Customer pays ₱49,000.00 (partial payment for PRE-20260825-0004)
- **Aug 26, 2026:** Customer pays ₱990.00 (remaining balance for PRE-20260825-0004)

### Expected Result:
The report should show **TWO separate entries**:
1. PRE-20260825-0004 on Aug 25, 2026 - Payment: ₱49,000.00
2. PRE-20260825-0004 on Aug 26, 2026 - Payment: ₱990.00

### Previous Behavior:
The report only showed ONE entry (either on creation date or completion date)

## Solution: Payment History Table

Created a new table `preorder_payment_history` that stores **each individual payment transaction**. This allows:
- Multiple report entries for the same preorder
- Accurate tracking of when each payment was made
- Payment sequence tracking (1st payment, 2nd payment, etc.)
- Balance before/after each payment

## Database Schema

### New Table: `preorder_payment_history`

```sql
CREATE TABLE `preorder_payment_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `preorder_id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `payment_date` datetime NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_data` text DEFAULT NULL,
  `payment_sequence` int(11) NOT NULL DEFAULT 1,
  `status_after_payment` varchar(50) DEFAULT 'pending',
  `balance_before` decimal(12,2) DEFAULT 0.00,
  `balance_after` decimal(12,2) DEFAULT 0.00,
  `branch_code` varchar(10) NOT NULL,
  `encoder` varchar(150) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `preorder_id` (`preorder_id`),
  KEY `invoice_no` (`invoice_no`),
  KEY `payment_date` (`payment_date`)
);
```

## Files Changed

### 1. New Migration Script
**File:** `add_preorder_payment_history.php`
- Creates the payment history table
- Migrates existing payment data from preorders
- Splits multiple payments into separate history records
- Shows verification and sample data

### 2. Backend Payment Save
**File:** `save_preorder2_payment.php`
- Updated to insert each payment into payment history table
- Records payment_date as current timestamp
- Tracks payment sequence (1, 2, 3, etc.)
- Calculates balance before/after each payment
- Records status after each payment

### 3. Report Query
**File:** `fetch_preorder_report.php`
- **Complete rewrite** to query from payment history table
- Shows one report entry per payment transaction
- Filters by `payment_date` instead of `created_at`
- Uses payment amount from history (not calculated from JSON)
- Shows status after each specific payment

## How It Works

### Payment Flow:

**Step 1: First Payment (Aug 25)**
```
User creates preorder in preorder.php
- Payment: ₱49,000.00 (Cash)
- Status: pending/partial

Database Records Created:
1. preorders table: Basic preorder info
2. preorder_items table: Items ordered
3. preorder_payment_history table:
   - payment_date: 2026-08-25 10:00:00
   - amount: 49000.00
   - payment_sequence: 1
   - balance_before: 49990.00
   - balance_after: 990.00
   - status_after_payment: partial
```

**Step 2: Second Payment (Aug 26)**
```
User loads invoice in preorder2.php
- Pays remaining: ₱990.00 (Cash)
- Status: completed

Database Records Created:
1. preorders table: Updated payment_data, status, completed_at
2. preorder_payment_history table:
   - payment_date: 2026-08-26 14:30:00
   - amount: 990.00
   - payment_sequence: 2
   - balance_before: 990.00
   - balance_after: 0.00
   - status_after_payment: completed
```

### Report Display:

```sql
SELECT FROM preorder_payment_history 
WHERE payment_date BETWEEN '2026-08-25' AND '2026-08-26'
```

**Results:**
| Invoice | Date | Item | Payment | Status | Date Sold |
|---------|------|------|---------|--------|-----------|
| PRE-20260825-0004 | Aug 25 | IPHONE 15 | ₱49,000.00 | partial | Aug 25, 2026 |
| PRE-20260825-0004 | Aug 26 | IPHONE 15 | ₱990.00 | completed | Aug 26, 2026 |

## Installation Steps

### Step 1: Run Migration
Open browser and go to:
```
http://localhost/ZUHAUSE/add_preorder_payment_history.php
```

This will:
1. Create `preorder_payment_history` table
2. Migrate existing payment data
3. Show verification results

### Step 2: Verify Migration
Check the migration results:
- ✅ Table created successfully
- ✅ Existing payments migrated
- ✅ Sample data displayed

### Step 3: Test New Payment Flow
1. Go to `preorder2.php`
2. Search for an existing invoice with remaining balance
3. Enter remaining payment
4. Click SAVE
5. Check database to verify payment history entry created

### Step 4: Verify Report
1. Go to `preorderreport.php`
2. Set date range to include both payment dates
3. Verify **two separate entries** appear for the same preorder

## Benefits

✅ **Multiple Entries:** Same preorder appears multiple times on different dates
✅ **Accurate Dates:** Each payment shows on the date it was actually made
✅ **Payment Tracking:** Full history of all payments with sequence
✅ **Balance Tracking:** See balance before/after each payment
✅ **Sales Analysis:** Daily sales reports now show actual payment dates
✅ **Audit Trail:** Complete payment history for compliance

## Comparison

### Before (Single Entry):
```
Date Range: Aug 25 - Aug 26
Result: 
- PRE-20260825-0004 on Aug 26 (completed date only)
```

### After (Multiple Entries):
```
Date Range: Aug 25 - Aug 26
Result:
- PRE-20260825-0004 on Aug 25 (₱49,000.00 payment)
- PRE-20260825-0004 on Aug 26 (₱990.00 payment)
```

## Data Structure Example

### preorders table (Summary):
```json
{
  "id": 4,
  "invoice_no": "PRE-20260825-0004",
  "total_amount": 49990.00,
  "status": "completed",
  "created_at": "2026-08-25 10:00:00",
  "completed_at": "2026-08-26 14:30:00",
  "payment_data": {
    "payment_type": "multiple",
    "payments": [
      {"payment_type": "cash", "amount": "49000.00"},
      {"payment_type": "cash", "amount": "990.00"}
    ]
  }
}
```

### preorder_payment_history table (Detail):
```json
[
  {
    "id": 1,
    "preorder_id": 4,
    "invoice_no": "PRE-20260825-0004",
    "payment_date": "2026-08-25 10:00:00",
    "payment_type": "cash",
    "amount": 49000.00,
    "payment_sequence": 1,
    "balance_before": 49990.00,
    "balance_after": 990.00,
    "status_after_payment": "partial"
  },
  {
    "id": 2,
    "preorder_id": 4,
    "invoice_no": "PRE-20260825-0004",
    "payment_date": "2026-08-26 14:30:00",
    "payment_type": "cash",
    "amount": 990.00,
    "payment_sequence": 2,
    "balance_before": 990.00,
    "balance_after": 0.00,
    "status_after_payment": "completed"
  }
]
```

## Testing Checklist

- [x] Migration script runs successfully
- [x] Payment history table created
- [x] Existing payments migrated correctly
- [x] New payments create history entries
- [x] Report shows multiple entries for same preorder
- [x] Each entry shows on correct payment date
- [x] Payment amounts are accurate
- [x] Balance tracking works correctly
- [x] Status per payment is correct

## Troubleshooting

### Issue: Migration fails
**Check:**
- Database permissions
- Table doesn't already exist
- Existing preorders have valid payment_data

### Issue: Report shows no data
**Check:**
- Payment history table has data
- Date range includes payment dates
- Branch filter is correct

### Issue: Duplicate entries
**Check:**
- Migration was run only once
- Save function isn't creating duplicate history records

## Files Modified/Created

### Created:
1. `add_preorder_payment_history.php` - Migration script
2. `PREORDER_PAYMENT_HISTORY_FIX.md` - This documentation

### Modified:
1. `save_preorder2_payment.php` - Inserts into payment history
2. `fetch_preorder_report.php` - Queries payment history

## Notes

- Payment history is **append-only** (never deleted/updated)
- Each payment transaction creates exactly one history record
- The `preorders` table still maintains the summary/total
- Reports now show payment-level detail instead of preorder-level summary
- This provides full audit trail of all payment transactions

## Future Enhancements

Consider adding:
- Payment history viewer in UI
- Payment receipt per transaction
- Ability to void/refund individual payments
- Payment method analytics per transaction
- Daily payment summary reports
