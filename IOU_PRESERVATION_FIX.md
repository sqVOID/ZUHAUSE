# IOU Preservation on Stock Transfer Fix

## Issue
When receiving stock transfers between branches, the system was resetting both IOU days and Aging days. This was incorrect because:
- **IOU days** should be preserved when transferring between branches (tracks total days in system)
- **Aging days** should reset to reflect the new branch's receipt date

## Example Scenario
- Item has 7 days IOU at Branch A
- Transfer to Branch B
- **Before Fix**: Both IOU and Aging reset to 0 at Branch B
- **After Fix**: IOU remains 7 days, only Aging resets to 0

## Database Fields
- `dr_date` - Delivery receipt date (used to calculate **Aging**)
  - Formula: `DATEDIFF(CURDATE(), dr_date)` = days since receipt
  
- `system_entry_date` - System entry date (used to calculate **IOU**)
  - Formula: `DATEDIFF(CURDATE(), system_entry_date)` = days in system

## Changes Made

### File: `update_transfer_status.php`

#### 1. IMEI/Serialized Items Section
**Changed:**
- Now fetches `system_entry_date` from original stock row
- Preserves `system_entry_date` when updating transferred item
- Sets `dr_date = NOW()` to reset Aging for new branch

```php
// Preserve the original system_entry_date to maintain IOU days
$originalSystemEntryDate = !empty($fullRow['system_entry_date']) ? $fullRow['system_entry_date'] : date('Y-m-d H:i:s');

// Update: Reset Aging (dr_date = NOW()) but preserve IOU (keep original system_entry_date)
$sqlUpdate = "UPDATE stock_on_hand
               SET branch = ?,
                   dr_number = ?,
                   dr_date = NOW(),
                   system_entry_date = ?,
                   status = 'Active',
                   quantity = 1
               WHERE id = ?";
```

#### 2. Accessories/Non-Serialized Items Section
**Changed:**
- Added `system_entry_date` to SELECT query
- Captures oldest `system_entry_date` from source stock
- Preserves this date when creating new stock row at destination

```php
// Track the oldest system_entry_date to preserve IOU
$oldestSystemEntryDate = $row['system_entry_date'] ?? null;

// Preserve original system_entry_date to maintain IOU days
$preservedSystemEntryDate = $oldestSystemEntryDate ?? date('Y-m-d H:i:s');

// Create new stock row for the transferred qty.
// Reset Aging (dr_date = NOW()) but preserve IOU (original system_entry_date)
$sqlInsert = "INSERT INTO stock_on_hand
               (item_code, description, item_type, dr_number, branch,
                dr_date, system_entry_date, status, quantity, family_code)
               VALUES (?, ?, 'Accessories', ?, ?, NOW(), ?, 'Active', ?, ?)";
```

## Result
- **IOU Days**: Preserved across branches ✓
- **Aging Days**: Reset for new branch location ✓
- Stock transfers now correctly maintain IOU tracking while resetting Aging

## Testing Recommendations
1. Create item with known IOU days (e.g., 7 days)
2. Transfer to another branch
3. Verify at receiving branch:
   - IOU still shows 7 days
   - Aging shows 0 days (reset)
4. Test both IMEI items and Accessories

## Date: July 10, 2026
