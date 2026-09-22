# Serial Number Duplicate Transfer Fix

## Problem
Serial numbers that were already received in a stock transfer (status: 'Approved' or 'Received') could be used again in a new stock transfer. This caused duplicate serial number entries across different transfers.

### Example Scenario:
1. Transfer ST-20261713-001 is created with serial number '124'
2. Transfer is approved and received at the destination branch
3. Serial number '124' is moved to the new branch in `stock_on_hand` table
4. **BUG**: User can still create a new transfer with serial number '124' from the original branch
5. This creates an invalid state where the same serial number exists in multiple transfers

## Root Cause
The `search_imei.php` file (used when entering serial numbers in stocktransfer.php) only validated:
- If the serial number exists in the current branch's `stock_on_hand` table
- If the serial number is in a **pending** transfer (checked in `save_stock_transfer.php`)

**Missing validation**: No check for serial numbers already in **Approved** or **Received** transfers.

## Solution
Added validation in `search_imei.php` to check if a serial number is already part of an existing transfer with status 'Approved' or 'Received' **before** allowing it to be used in a new transfer.

### Changes Made

**File: `search_imei.php`**
- Added Step 0 validation before the existing stock_on_hand lookup
- Queries `stock_transfer_items` joined with `stock_transfers` to find any transfers with the serial number that have status 'Approved' or 'Received'
- Returns detailed error message showing:
  - Transfer number where it's already used
  - Current status of that transfer
  - Source and destination branches
- Prevents the serial number from being added to the modal list

### SQL Query Added:
```sql
SELECT st.st_number, st.status, st.branch_to, st.branch_from
FROM stock_transfer_items sti
JOIN stock_transfers st ON sti.st_number = st.st_number
WHERE sti.imei = '$imei'
  AND st.status IN ('Approved', 'Received')
LIMIT 1
```

### Error Message Format:
```
Serial number already used in transfer ST-20261713-001 (Status: Received, From: MOTOGAM - BOTOLAN, To: MOTOCAR LASA AREA)
```

## Testing
1. Create a transfer with serial number (e.g., '124')
2. Approve the transfer in Transfer Approval page
3. Receive the transfer in Receive Stock Transfer page
4. Try to create a new transfer using the same serial number '124'
5. **Expected**: Error message preventing duplicate usage
6. **Previous behavior**: Would allow creating a duplicate transfer

## Files Modified
- `search_imei.php` - Added duplicate serial number validation for Approved/Received transfers

## Impact
- **Positive**: Prevents duplicate serial numbers across multiple transfers
- **No Breaking Changes**: Only adds additional validation; existing functionality remains intact
- **User Experience**: Users get clear error messages explaining why a serial number cannot be used

## Related Files
- `stocktransfer.php` - Frontend that calls search_imei.php
- `save_stock_transfer.php` - Already checks for Pending transfers
- `update_transfer_status.php` - Handles receiving transfers and moving stock
- `receivestocktransfer.php` - Frontend for receiving transfers
- `stock_transfer_items` table - Stores serial numbers for each transfer
- `stock_transfers` table - Stores transfer status

## Status
✅ **FIXED** - Serial numbers can no longer be duplicated across Approved or Received transfers
