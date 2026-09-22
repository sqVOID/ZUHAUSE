# Unclaimed Freebies Date Display Update

## Summary
Added date display functionality to the Unclaimed Freebies Breakdown section in the report.php. The system now shows:
- **Unclaimed Date** for items with status "UNCLAIMED" (from created_at field)
- **Claimed Date** for items with status "CLAIMED" (from claimed_at field)

## Changes Made

### 1. Database Migration
**File:** `add_claimed_at_to_unclaimed_freebies.php` (NEW)
- Added a new column `claimed_at` (TIMESTAMP) to the `unclaimed_freebies` table
- This column stores the date and time when a freebie item is claimed
- Run this file once to apply the database schema change

### 2. Backend API Update
**File:** `get_unclaimed_freebies_report.php`
- Updated SQL query to include `claimed_at` field
- Modified result array to return `claimed_at` timestamp for each record
- Frontend will now receive both `created_at` and `claimed_at` dates

### 3. Claiming Process Update
**File:** `save_claimed_items.php`
- Modified the UPDATE statement to set `claimed_at = NOW()` when status is changed to 'claimed'
- Ensures the claimed timestamp is recorded when items are marked as claimed

### 4. Frontend Display Update
**File:** `report.php`
- Updated the `displayUnclaimedFreebies()` function
- Added conditional logic to display appropriate date based on status:
  - If status is "unclaimed": Shows "UNCLAIMED DATE: [created_at]"
  - If status is "claimed": Shows "CLAIMED DATE: [claimed_at]"
- Uses existing `formatDateTime()` helper function for consistent date formatting

## How to Deploy

1. **Run the migration** to add the `claimed_at` column:
   - Navigate to: `http://your-domain/MOTOGAM/add_claimed_at_to_unclaimed_freebies.php`
   - Verify the column was added successfully

2. **Verify the changes**:
   - Open the report.php page
   - Filter by date range and branch
   - Check the "UNCLAIMED FREEBIES BREAKDOWNS" section
   - Unclaimed items should show "UNCLAIMED DATE"
   - Claimed items should show "CLAIMED DATE"

## Date Format
Dates are displayed in the format: `YYYY-MM-DD HH:MM` (e.g., "2026-07-31 14:30")

## Notes
- Existing "claimed" records will have NULL for `claimed_at` until they are re-claimed or manually updated
- The `created_at` field represents when the freebie was originally added (unclaimed date)
- The `claimed_at` field is only set when the status changes to "claimed"
