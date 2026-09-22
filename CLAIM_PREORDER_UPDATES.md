# Claim Pre-Order Updates

## Overview
Updated the claim pre-order functionality to properly handle completed pre-orders that are ready to be claimed by customers.

## Changes Made

### 1. Database Migration
**File:** `add_claimed_at_to_preorders.php`
- Added `claimed_at` DATETIME column to `preorders` table
- This tracks the exact timestamp when a pre-order was claimed by the customer

**Migration Status:** ✅ Successfully executed

### 2. Search Logic Update
**File:** `search_preorder.php`

**Changes:**
- Modified status check to only allow searching for **COMPLETED** pre-orders (line 24-28)
- **Before:** Only searched pre-orders with 'pending' status
- **After:** Only searches pre-orders with 'completed' status
- Updated error message to be more descriptive

**Payment Status Logic:**
- Added query to `preorder_payment_history` table to get accurate payment information
- Calculates `totalPaid` from actual payment history records
- Falls back to `payment_data` JSON for backward compatibility
- Properly marks status as 'paid' when totalPaid >= totalAmount

### 3. Frontend Updates
**File:** `claimpreorder.php`

**Changes:**
- **Payment Button Hidden:** Added `display: none;` to `.btn-payment` class (line 436)
  - Reason: Completed pre-orders are already fully paid
  - Only the "Save" button is visible for claiming items

- **Save Function Updated:** Modified `saveClaimPreOrder()` function (lines 2791-2820)
  - **Removed:** Payment validation checks that blocked saving
  - **Before:** Required `window.paymentData` and checked if status was 'paid'
  - **After:** Uses existing payment data from the completed pre-order
  - Automatically sets payment status to 'paid' for completed pre-orders

### 4. Backend Save Logic
**File:** `save_claim_preorder.php`

**Changes:**
- Updated status check from 'pending' to 'completed' (line 55)
  - **Before:** `if ($preorder['status'] !== 'pending')`
  - **After:** `if ($preorder['status'] !== 'completed')`
  - Ensures only completed pre-orders can be claimed

- Added `claimed_at = NOW()` to UPDATE query (line 229)
  - Records the exact timestamp when the claim is saved
  - Query: `UPDATE preorders SET status = 'claimed', claimed_invoice_no = ?, claimed_at = NOW() WHERE id = ?`

### 5. Report Display
**File:** `preorderreport.php`

**Status:** ✅ Already supports 'claimed' status display
- Has CSS class `.status-claimed` for blue color
- Filter dropdown includes "Claimed" option
- Status properly displays as "CLAIMED" in uppercase

**File:** `fetch_preorder_report.php`

**Status:** ✅ No changes needed
- Already queries and displays the status from preorders table
- Status filter works correctly with 'claimed' value

## Workflow Summary

### Old Flow (Before Changes):
1. Search for pre-order → Only found 'pending' pre-orders
2. Required payment before saving
3. Status: pending → (no completed state) → claimed

### New Flow (After Changes):
1. Complete pre-order payment → Status = 'completed', `completed_at` set
2. Search for completed pre-order in claim page → Only finds 'completed' pre-orders
3. Add items and click Save → No payment needed (already paid)
4. Save updates → Status = 'claimed', `claimed_at` = NOW()
5. Report shows → Status displays as "CLAIMED" in blue

## Database Schema Updates

### preorders table:
```sql
ALTER TABLE preorders 
ADD COLUMN claimed_at DATETIME NULL DEFAULT NULL COMMENT 'Timestamp when pre-order was claimed' 
AFTER claimed_invoice_no;
```

## Testing Checklist

- [x] Database migration executed successfully
- [x] Search only returns completed pre-orders
- [x] Payment button is hidden on claim page
- [x] Save button works without payment validation
- [x] Status updates to 'claimed' after saving
- [x] claimed_at timestamp is recorded
- [x] Report displays 'CLAIMED' status correctly
- [x] Payment status shows 'PAID' for completed pre-orders

## Files Modified

1. `add_claimed_at_to_preorders.php` (NEW)
2. `search_preorder.php`
3. `claimpreorder.php`
4. `save_claim_preorder.php`
5. `CLAIM_PREORDER_UPDATES.md` (NEW - this file)

## Notes

- The payment button is hidden via CSS, making it easy to re-enable if needed
- All completed pre-orders are assumed to be fully paid (as per business logic)
- The `claimed_at` field provides audit trail for when items were picked up
- Report supports filtering by 'claimed' status
