# Approval Date Preservation Fix

## Problem
When receiving a stock transfer via the "Receive" button in `receivestocktransfer.php`, the system was incorrectly updating the `approval_date` timestamp, overwriting the original approval date/time.

### Example:
- **Original approval_date**: 2026-07-28 13:22:23 (when approved)
- **After receiving**: 2026-07-28 13:24:54 (incorrectly updated)

This caused the approval date to be changed to the receiving date, losing the original approval timestamp.

---

## Root Cause

In `update_transfer_status.php`, the code had a flaw in how it handled different status updates:

### Issue 1 (Lines 85-91 - OLD CODE):
```php
} else {
    // For other statuses, don't set disapproved_by
    $sql = "UPDATE stock_transfers
            SET status = ?,
                approver = ?,
                approval_date = NOW()  // ❌ This was updating approval_date for ALL statuses
            WHERE st_number = ?";
```

When status = 'Received', this code was still updating `approver` and `approval_date`, overwriting the original approval timestamp.

### Issue 2 (Lines 50-62 - OLD CODE):
```php
if ($currentStatus === $status) {
    $sql = "UPDATE stock_transfers
            SET approver = ?, approval_date = NOW()  // ❌ Unnecessarily updating
            WHERE st_number = ?";
```

Even when the status was already set, it was still updating the approval_date.

---

## Solution

Updated `update_transfer_status.php` to handle each status change appropriately:

### Fix 1: Separate Logic for Each Status

**NEW CODE (Lines 57-108)**:
```php
if ($status === 'Disapproved') {
    // Update: status, approver, approval_date, disapproved_by
    $sql = "UPDATE stock_transfers
            SET status = ?,
                approver = ?,
                approval_date = NOW(),
                disapproved_by = ?
            WHERE st_number = ?";
    
} elseif ($status === 'Approved') {
    // Update: status, approver, approval_date ONLY
    $sql = "UPDATE stock_transfers
            SET status = ?,
                approver = ?,
                approval_date = NOW()
            WHERE st_number = ?";
    
} elseif ($status === 'Received') {
    // Update: status ONLY - DO NOT touch approver or approval_date ✅
    $sql = "UPDATE stock_transfers
            SET status = ?
            WHERE st_number = ?";
    
} else {
    throw new Exception("Unsupported status: {$status}");
}
```

### Fix 2: Remove Unnecessary Update

**NEW CODE (Lines 50-54)**:
```php
// If already in the requested status, don't re-apply changes.
if ($currentStatus === $status) {
    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Transfer already $status"]);
    exit;
}
```

No more updating approval_date when status is already the same.

---

## Update Flow

### When APPROVING a transfer:
1. ✅ Updates `status = 'Approved'`
2. ✅ Updates `approver = [username]`
3. ✅ Updates `approval_date = NOW()`
4. ✅ Exits (no stock movement)

### When RECEIVING a transfer:
1. ✅ Updates `status = 'Received'` ONLY
2. ✅ **Does NOT touch `approver`** (preserves original approver)
3. ✅ **Does NOT touch `approval_date`** (preserves original timestamp)
4. ✅ Updates `received_by = [username]`
5. ✅ Updates `received_date = NOW()`
6. ✅ Moves stock to destination branch

---

## Result

### Before Fix:
```
Approve:  approval_date = 2026-07-28 13:22:23
Receive:  approval_date = 2026-07-28 13:24:54  ❌ CHANGED!
```

### After Fix:
```
Approve:  approval_date = 2026-07-28 13:22:23
Receive:  approval_date = 2026-07-28 13:22:23  ✅ PRESERVED!
          received_date = 2026-07-28 13:24:54  ✅ NEW!
```

---

## Database Fields

### stock_transfers table:
- `status` - Current status (Pending/Approved/Disapproved/Received)
- `approver` - Username who approved (set during Approval)
- `approval_date` - Timestamp when approved (set during Approval)
- `received_by` - Username who received (set during Receive)
- `received_date` - Timestamp when received (set during Receive)
- `disapproved_by` - Username who disapproved (set during Disapproval)

---

## Testing Checklist

- [x] Approve a transfer → approval_date is set
- [x] Receive the same transfer → approval_date remains unchanged
- [x] Verify received_date is set correctly
- [x] Verify received_by is set correctly
- [x] Check that stock movement still works correctly

---

## Files Modified
1. ✅ `update_transfer_status.php`

## Date
January 28, 2025
