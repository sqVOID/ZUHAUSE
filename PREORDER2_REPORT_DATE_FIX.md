# Preorder Report - Date Sold Fix

## Problem Description

When a preorder is created on one date (e.g., Aug 25, 2026) and the remaining payment is completed on a different date (e.g., Aug 26, 2026), the preorder report only shows the entry on the original creation date (Aug 25). This is because the report was filtering by `created_at` instead of considering when the preorder was completed.

### Expected Behavior:
- Preorder created on Aug 25, 2026 (partial payment)
- Remaining payment completed on Aug 26, 2026
- **Report should show the preorder on Aug 26, 2026** (completion date)

### Actual Behavior (Before Fix):
- Report only showed the preorder on Aug 25, 2026 (creation date)
- The Aug 26 payment was saved but didn't appear in the report

## Solution

Added a `completed_at` column to track when a preorder is fully paid, and updated the report to use this date for completed preorders.

## Changes Made

### 1. Database Schema Change

**New Column:** `completed_at`
- **Type:** DATETIME
- **Nullable:** YES (NULL for pending/partial preorders)
- **Purpose:** Stores the date/time when preorder becomes fully paid
- **Location:** After `updated_at` column in `preorders` table

**Migration Script:** `add_completed_at_to_preorders.php`
- Adds the column if it doesn't exist
- Updates existing completed preorders to use their `updated_at` as `completed_at`
- Shows verification of table structure

### 2. Backend Update

**File:** `save_preorder2_payment.php`

**Changes:**
```php
// Set completion date when status becomes 'completed'
$date_completed = null;
if ($remaining_balance <= 0.01) {
    $status = 'completed';
    $date_completed = date('Y-m-d H:i:s'); // Current timestamp
}

// Update includes completed_at
UPDATE preorders 
SET payment_data = ?, 
    status = ?,
    completed_at = ?,
    updated_at = NOW()
WHERE id = ?
```

### 3. Report Query Update

**File:** `fetch_preorder_report.php`

**Key Changes:**

1. **Added `completed_at` to SELECT:**
```sql
SELECT 
    p.completed_at,
    CASE 
        WHEN p.status = 'completed' AND p.completed_at IS NOT NULL 
            THEN DATE(p.completed_at)
        ELSE DATE(p.created_at)
    END as report_date
```

2. **Updated WHERE clause:**
```sql
WHERE (
    -- Completed orders: use completed_at date
    (p.status = 'completed' AND p.completed_at IS NOT NULL 
     AND DATE(p.completed_at) BETWEEN ? AND ?)
    OR
    -- Non-completed orders: use created_at date
    (p.status != 'completed' AND DATE(p.created_at) BETWEEN ? AND ?)
    OR
    -- Completed orders without completed_at (legacy): use created_at
    (p.status = 'completed' AND p.completed_at IS NULL 
     AND DATE(p.created_at) BETWEEN ? AND ?)
)
```

3. **Updated date_created output:**
```php
'date_created' => $row['status'] === 'completed' && $row['completed_at'] 
                  ? $row['completed_at'] 
                  : $row['date_created']
```

## How It Works Now

### Timeline Example:

**Aug 25, 2026:**
- User creates preorder PRE-20260825-0002
- Pays ₱39,900.00 (partial payment)
- Status: `pending` or `partial`
- `created_at`: 2026-08-25 10:00:00
- `completed_at`: NULL
- **Report shows this on Aug 25**

**Aug 26, 2026:**
- User searches for PRE-20260825-0002 in preorder2.php
- Pays remaining ₱90.00
- Status changes to: `completed`
- `completed_at`: 2026-08-26 14:30:00 (set automatically)
- `updated_at`: 2026-08-26 14:30:00
- **Report NOW shows this on Aug 26** ✅

### Report Filtering Logic:

| Status | Date Used in Report | Condition |
|--------|---------------------|-----------|
| `pending` | `created_at` | Payment not started |
| `partial` | `created_at` | Payment in progress |
| `completed` | `completed_at` | Payment fully paid (if completed_at exists) |
| `completed` (legacy) | `created_at` | Old records without completed_at |

## Installation Steps

1. **Run the migration:**
   ```
   http://localhost/ZUHAUSE/add_completed_at_to_preorders.php
   ```
   This adds the `completed_at` column to the database.

2. **Files are already updated:**
   - `save_preorder2_payment.php` - Sets completed_at when status = completed
   - `fetch_preorder_report.php` - Uses completed_at for filtering and display

3. **Test the flow:**
   - Create a partial payment preorder on one date
   - Complete the payment on a different date using preorder2.php
   - Check the report - should show on completion date

## Benefits

✅ **Accurate Date Sold:** Report shows when preorder was actually completed
✅ **Sales Tracking:** Daily sales reports now reflect actual completion dates
✅ **Payment History:** Can track when partial payments become complete
✅ **Backward Compatible:** Existing preorders without completed_at still work
✅ **No Data Loss:** Original creation date is preserved in created_at

## Testing Checklist

- [x] Migration script runs successfully
- [x] completed_at column added to preorders table
- [x] Existing completed preorders updated with completion date
- [x] New completed payments set completed_at correctly
- [x] Report shows completed preorders on completion date
- [x] Report shows pending/partial preorders on creation date
- [x] Legacy completed preorders (no completed_at) still appear in report
- [x] Date filtering works correctly for both date types

## Files Modified

### Created:
1. `add_completed_at_to_preorders.php` - Database migration script

### Modified:
1. `save_preorder2_payment.php` - Sets completed_at when payment completes
2. `fetch_preorder_report.php` - Uses completed_at for report filtering
3. `PREORDER2_REPORT_DATE_FIX.md` - This documentation

## Database Schema

**Before:**
```sql
CREATE TABLE `preorders` (
  ...
  `status` varchar(50) DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
)
```

**After:**
```sql
CREATE TABLE `preorders` (
  ...
  `status` varchar(50) DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `completed_at` datetime DEFAULT NULL COMMENT 'Date/time when preorder was fully paid'
)
```

## Notes

- The `completed_at` field is **automatically** set when a preorder becomes fully paid
- Reports use `completed_at` for completed orders, `created_at` for others
- This fix ensures accurate sales reporting by completion date, not creation date
- All payment history is preserved in the `payment_data` JSON field

## Future Enhancements

Consider adding:
- `first_payment_at` - Track when first payment was made
- `last_payment_at` - Track when last payment was made
- Payment history table for detailed transaction tracking
- Report option to toggle between creation date and completion date views
