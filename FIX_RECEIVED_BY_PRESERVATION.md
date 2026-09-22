# Fix: Preserve "Received by" Workflow History for Super Admin Modifications

## Issue
When a Super Admin modifies a Purchase Order through `modificationrpo.php` and uses the `confirmAction()` function to save changes with status "Received", the system was overwriting the original `received_by`, `received_at`, and `received_by_branch` fields with the Super Admin's information.

This caused the Workflow History to incorrectly show the Super Admin as the person who received the PO, instead of preserving the original receiver's information.

## Root Cause
In `update_po_status.php`, when processing a "Received" status change, the code was unconditionally updating the workflow history fields:

```php
$sql = "UPDATE purchase_orders
        SET status      = 'Received',
            received_by = '{$action_by_esc}',
            received_at = '{$action_at}',
            received_by_branch = '{$po_branch_code_esc}'";
```

This meant every time the status was set to "Received" (whether for the first time or during a modification), it would overwrite the existing receiver information.

## Solution
Modified `update_po_status.php` to check if `received_by` is already set before updating it:

### Key Changes:
1. **Fetch existing `received_by` value** when querying the PO:
   ```php
   $po_branch_query = $conn->query("SELECT created_by_branch, received_by FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
   $existing_received_by = null;
   if ($po_branch_query && $po_branch_query->num_rows > 0) {
       $po_branch_row = $po_branch_query->fetch_assoc();
       $existing_received_by = $po_branch_row['received_by'];
       // ... other code
   }
   ```

2. **Conditionally update workflow history fields**:
   ```php
   // Only set received_by fields if they haven't been set before
   if (empty($existing_received_by)) {
       $sql .= ",
               received_by = '{$action_by_esc}',
               received_at = '{$action_at}',
               received_by_branch = '{$po_branch_code_esc}'";
   }
   ```

## Result
✅ **First-time receiving**: The `received_by`, `received_at`, and `received_by_branch` fields are populated with the user who first receives the PO.

✅ **Super Admin modifications**: When a Super Admin modifies a PO that was already received, the original receiver's information is preserved in the Workflow History.

✅ **Audit trail intact**: The system maintains a complete and accurate workflow history showing who originally received the PO, not who modified it later.

## Files Modified
- `update_po_status.php` - Added logic to preserve existing `received_by` workflow history fields

## Testing Recommendations
1. Create a new PO and receive it as a regular user → Verify `received_by` shows the regular user
2. Have Super Admin modify the same PO → Verify `received_by` still shows the original regular user
3. Check Workflow History section displays the correct original receiver
4. Verify modifications are still saved correctly (serial numbers, item models, quantities, etc.)

## Date
January 2025
