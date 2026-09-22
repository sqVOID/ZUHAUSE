# Late Created At Implementation

## Overview
Added `late_created_at` column to track the actual timestamp when a late entry was created, separate from the sale date.

## Purpose
- **created_at**: Stores the actual sale date (can be in the past or future for late entries)
- **late_created_at**: Stores when the user actually created the entry in the system (always NOW())

## Example Scenario
1. User creates a late entry on **July 24, 2026 at 10:30 AM**
2. User selects sale date as **July 22, 2026**
3. Database will store:
   - `created_at`: **2026-07-22 10:30:00** (the sale date with current time)
   - `late_created_at`: **2026-07-24 10:30:00** (when entry was actually created)

## Database Schema

```sql
ALTER TABLE sales_entry 
ADD COLUMN late_created_at DATETIME NULL DEFAULT NULL 
AFTER created_at;
```

### Column Details
- **Type**: DATETIME
- **Nullable**: YES (NULL for regular sales entries)
- **Default**: NULL
- **Position**: After `created_at` column

## Usage

### For Regular Sales Entries (salesentry.php)
- `created_at`: Current timestamp (NOW())
- `late_created_at`: NULL (not a late entry)

### For Late Sales Entries (salesentrylate.php)
- `created_at`: User-selected date with current time
- `late_created_at`: Current timestamp (actual creation time)

## Installation Steps

1. Run the migration file:
   ```
   http://your-domain/MOTOGAM/add_late_created_at_column.php
   ```

2. The column will be added automatically
3. Future late entries will populate this field
4. Existing entries will have NULL (which is correct)

## Benefits

1. **Audit Trail**: Know exactly when each late entry was created
2. **Reporting**: Can filter entries by actual creation date vs. sale date
3. **Compliance**: Better tracking for backdated or future-dated entries
4. **Data Integrity**: Maintain both historical sale date and system entry date

## Files Modified

1. `add_late_created_at_column.php` - Migration file (NEW)
2. `save_sales_entry.php` - Updated to save late_created_at timestamp
3. `salesentrylate.php` - Already sends date field (no changes needed)

## Testing

After running the migration:

1. Create a regular sale entry:
   - Check: `late_created_at` should be NULL
   
2. Create a late entry with past date:
   - Check: `created_at` = selected date
   - Check: `late_created_at` = current timestamp
   
3. Create a late entry with future date:
   - Check: `created_at` = selected date
   - Check: `late_created_at` = current timestamp

## SQL Queries for Verification

```sql
-- View all late entries with both timestamps
SELECT 
    invoice_no,
    created_at AS sale_date,
    late_created_at AS entry_created_date,
    DATEDIFF(late_created_at, created_at) AS days_difference
FROM sales_entry
WHERE late_created_at IS NOT NULL
ORDER BY late_created_at DESC;

-- Count late entries by how many days late they were entered
SELECT 
    CASE 
        WHEN DATEDIFF(late_created_at, created_at) = 0 THEN 'Same day'
        WHEN DATEDIFF(late_created_at, created_at) > 0 THEN 'Past date entry'
        ELSE 'Future date entry'
    END AS entry_type,
    COUNT(*) AS count
FROM sales_entry
WHERE late_created_at IS NOT NULL
GROUP BY entry_type;
```

## Notes

- The `late_created_at` field is only populated for entries from `salesentrylate.php`
- Regular entries from `salesentry.php` will have `late_created_at` = NULL
- This is intentional and helps distinguish between regular and late entries
- The field can be used for reporting and auditing purposes
