# AGING and IOU Preservation Fix

## Issue
When saving modifications to purchase orders, the AGING and IOU were being reset to 0 days because new serial numbers were being inserted with the current date instead of the original dates.

## Solution Implemented
Updated `save_po_modifications.php` to preserve the original `dr_date` and `system_entry_date` when modifying stock items.

## How It Works

### Before the Fix ❌
```php
$current_date = date('Y-m-d H:i:s');

$insert_stock_sql = "INSERT INTO stock_on_hand 
                    (..., dr_date, system_entry_date, ...)
                    VALUES 
                    (..., '{$current_date}', '{$current_date}', ...)";
```
**Result**: AGING and IOU reset to 0 days

### After the Fix ✅
```php
// Retrieve original dates from existing stock
$original_dates_query = $conn->query("
    SELECT dr_date, system_entry_date 
    FROM stock_on_hand 
    WHERE family_code = '{$family_code_esc}' 
    AND dr_number = '{$po_number}' 
    LIMIT 1
");

// Use original dates when inserting new serials
$insert_stock_sql = "INSERT INTO stock_on_hand 
                    (..., dr_date, system_entry_date, ...)
                    VALUES 
                    (..., '{$original_dr_date}', '{$original_system_entry_date}', ...)";
```
**Result**: AGING and IOU preserved correctly

## What Gets Preserved

| Field | Description | Formula | Preserved? |
|-------|-------------|---------|------------|
| `dr_date` | Document reference date | Used for AGING calculation | ✅ YES |
| `system_entry_date` | System entry timestamp | Used for IOU calculation | ✅ YES |
| **AGING** | Days since document date | `DATEDIFF(CURDATE(), dr_date)` | ✅ YES |
| **IOU** | Days since system entry | `DATEDIFF(CURDATE(), system_entry_date)` | ✅ YES |

## Modification Scenarios

### Scenario 1: Change Serial Number
```
Stock Entry:
- IMEI: 123456789
- dr_date: 2026-06-01
- system_entry_date: 2026-06-01
- AGING: 30 days
- IOU: 30 days

User Action: Change serial from 123456789 to 987654321

Result:
OLD serial 123456789: DELETED from stock
NEW serial 987654321: INSERTED with dr_date=2026-06-01, system_entry_date=2026-06-01
AGING: Still 30 days ✅
IOU: Still 30 days ✅
```

### Scenario 2: Keep Same Serial, Update Model
```
Stock Entry:
- IMEI: 123456789
- item_code: PHONE-A
- dr_date: 2026-06-15
- AGING: 16 days

User Action: Change model from PHONE-A to PHONE-B

Result:
- IMEI: 123456789 (same)
- item_code: PHONE-B (updated)
- dr_date: 2026-06-15 (preserved)
- system_entry_date: (preserved)
AGING: Still 16 days ✅
IOU: Preserved ✅
```

### Scenario 3: Add Additional Serial
```
Existing Stock:
- IMEI: 111111111
- dr_date: 2026-06-10
- AGING: 21 days

User Action: Add IMEI 222222222 to the same item

Result:
IMEI 111111111: Unchanged (AGING: 21 days)
IMEI 222222222: INSERTED with same dr_date (2026-06-10) and system_entry_date
Both serials now have AGING: 21 days ✅
```

## Code Changes Summary

### File Modified
`c:\xampp\htdocs\MOTOGAM\save_po_modifications.php`

### Key Changes
1. **Query original dates** before modifying serials
2. **Pass original dates** when inserting new stock entries
3. **Skip date fields** when updating existing stock entries
4. **Added comments** explaining the preservation logic

### Lines Added
```php
// Get original dr_date and system_entry_date to preserve AGING and IOU
$original_dates_query = $conn->query("
    SELECT dr_date, system_entry_date 
    FROM stock_on_hand 
    WHERE family_code = '{$family_code_esc}' 
    AND dr_number = '{$po_number}' 
    LIMIT 1
");

// Default to current date if no existing stock found
$original_dr_date = date('Y-m-d H:i:s');
$original_system_entry_date = date('Y-m-d H:i:s');

if ($original_dates_query && $original_dates_query->num_rows > 0) {
    $dates_data = $original_dates_query->fetch_assoc();
    $original_dr_date = $dates_data['dr_date'];
    $original_system_entry_date = $dates_data['system_entry_date'];
}
```

## Testing Checklist

- [ ] Modify an item with AGING > 0 days
- [ ] Change its serial number
- [ ] Verify AGING value remains the same after save
- [ ] Verify IOU value remains the same after save
- [ ] Check stock_on_hand table directly for `dr_date` and `system_entry_date`
- [ ] Test with multiple serials on same item
- [ ] Test updating item model without changing serial
- [ ] Test adding new serials to existing item

## Database Impact

**Tables Affected:**
- `stock_on_hand` (READ and WRITE)
- `purchase_order_items` (WRITE)

**Performance:**
- Adds one additional SELECT query per modified item to fetch original dates
- Minimal performance impact (single row lookup with indexed fields)

## Compatibility

- ✅ Backward compatible with existing stock entries
- ✅ Works with both serialized and non-serialized items
- ✅ Maintains transaction safety
- ✅ Compatible with existing stock reports and queries

## Date: July 1, 2026
