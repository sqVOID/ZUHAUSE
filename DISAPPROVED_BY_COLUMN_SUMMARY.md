# Disapproved By Column Implementation

## Summary
Added a "DISAPPROVED BY" column to the Stock Transfer Report to track which user disapproved a transfer. This applies to disapprovals made from both `transferapproval.php` and `receivestocktransfer.php`.

## Changes Made

### 1. Database Schema Update
**File: `add_disapproved_by_column.php`** (NEW)
- Created migration script to add `disapproved_by` column to `stock_transfers` table
- Column is added after `received_by` column
- Type: `VARCHAR(100) DEFAULT NULL`
- Run this file once to update the database schema

### 2. Backend Logic Update
**File: `update_transfer_status.php`**
- Modified the disapproval logic to capture the username of the person who disapproved
- When status is set to 'Disapproved', the `disapproved_by` field is populated with the username from session
- Works for disapprovals from both pages:
  - `transferapproval.php` (initial approval stage)
  - `receivestocktransfer.php` (receiving stage)

**Changes:**
```php
// For disapproval, save the disapproved_by user
if ($status === 'Disapproved') {
    $sql = "UPDATE stock_transfers
            SET status = ?,
                approver = ?,
                approval_date = NOW(),
                disapproved_by = ?
            WHERE st_number = ?";
    $stmtStatus->bind_param('ssss', $status, $approver, $approver, $st_number);
}
```

### 3. Report Display Update
**File: `stocktransferreport.php`**

**Table Header:**
- Added "DISAPPROVED BY" column header between "RECEIVED BY" and "ACTION"
- Adjusted column widths to accommodate the new column

**SQL Query:**
- Added `st.disapproved_by` to the SELECT statement
- Fetches the disapproved_by value from the database

**Table Body:**
- Displays the username of who disapproved the transfer
- Shows "-" if the transfer was not disapproved or if no user is recorded

**Updated Column Layout:**
| ST DATE | ST NO. | BRANCH FROM | BRANCH TO | STATUS | PREPARED BY | RECEIVED BY | DISAPPROVED BY | ACTION |
|---------|--------|-------------|-----------|--------|-------------|-------------|----------------|--------|
| 10%     | 12%    | 18%         | 18%       | 9%     | 12%         | 12%         | 12%            | 9%     |

## How It Works

### Disapproval from Transfer Approval Page (`transferapproval.php`)
1. User clicks "Disapprove" button on a Pending transfer
2. `update_transfer_status.php` is called with status='Disapproved'
3. The current logged-in username is saved to `disapproved_by` column
4. Transfer status changes to 'Disapproved'

### Disapproval from Receive Stock Transfer Page (`receivestocktransfer.php`)
1. User clicks "Disapprove" button on an Approved transfer
2. Same `update_transfer_status.php` endpoint is called with status='Disapproved'
3. The current logged-in username is saved to `disapproved_by` column
4. Transfer status changes to 'Disapproved'

### Viewing in Report
1. Open `stocktransferreport.php`
2. The "DISAPPROVED BY" column shows:
   - Username of the person who clicked disapprove (if disapproved)
   - "-" if not disapproved or if the column was null (old records)

## Database Migration

**Run once to add the column:**
```
http://your-domain/add_disapproved_by_column.php
```

**Expected Output:**
```
Column 'disapproved_by' added successfully to stock_transfers table.
```

Or if already exists:
```
Column 'disapproved_by' already exists in stock_transfers table.
```

## Files Modified
1. ✅ `add_disapproved_by_column.php` - NEW migration script
2. ✅ `update_transfer_status.php` - Updated disapproval logic
3. ✅ `stocktransferreport.php` - Added DISAPPROVED BY column to report

## Files Involved (No Changes Needed)
- `transferapproval.php` - Uses existing disapprove button
- `receivestocktransfer.php` - Uses existing disapprove button
- Both pages call `update_transfer_status.php` which now saves the disapproved_by user

## Testing Steps
1. ✅ Run `add_disapproved_by_column.php` to add the database column
2. ✅ Go to Transfer Approval page
3. ✅ Click "Disapprove" on a pending transfer
4. ✅ Go to Stock Transfer Report
5. ✅ Verify the "DISAPPROVED BY" column shows your username
6. ✅ Go to Receive Stock Transfer page
7. ✅ Click "Disapprove" on an approved transfer
8. ✅ Go to Stock Transfer Report
9. ✅ Verify the "DISAPPROVED BY" column shows your username

## Expected Results
- ✅ New column appears in the report between "RECEIVED BY" and "ACTION"
- ✅ Shows username for disapproved transfers
- ✅ Shows "-" for non-disapproved transfers
- ✅ Works from both approval pages (transferapproval.php and receivestocktransfer.php)
- ✅ Old records show "-" until they are disapproved

## Status
✅ **IMPLEMENTED** - DISAPPROVED BY column tracking and display complete
