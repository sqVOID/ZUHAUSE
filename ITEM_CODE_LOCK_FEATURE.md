# Item Code Lock Feature

## Overview
The Item Code field in `itemreg.php` is automatically locked (made read-only) when an item code is being used in other parts of the system. This prevents accidental changes that could break data integrity across related records.

## How It Works

### 1. **Detection Logic (Lines 183-218)**
When editing an item, the system checks if the Item Code exists in the following tables:
- `stock_on_hand` - Stock/Inventory records
- `sales_entry_items` - Sales transactions
- `po_items` - Purchase Order items
- `preorder_items` - Preorder transactions
- `allocations` - Allocation records

### 2. **Checking Process**
```php
// For each table:
1. Check if the table exists in the database
2. If exists, query for records with matching item code (case-insensitive)
3. If any record is found, set $item_code_in_use = true
4. Store result in $editData['item_code_locked']
```

### 3. **UI Implementation (Lines 2484-2487)**
When the item code is locked, the input field becomes:
- **Read-only**: Cannot be edited
- **Visually different**: Grey background (#f5f5f5)
- **Clear cursor**: Shows "not-allowed" cursor on hover
- **Informative tooltip**: Explains why it's locked

```php
<?php if ($editMode && isset($editData['item_code_locked']) && $editData['item_code_locked']): ?>
readonly 
style="background-color: #f5f5f5; cursor: not-allowed; color: #666; border: 1px solid #e0e0e0;"
title="This item code cannot be changed because it is being used in other records..."
<?php endif; ?>
```

## Visual Behavior

### Unlocked (Item Code not in use):
- White background
- Normal text cursor
- Fully editable

### Locked (Item Code in use):
- Grey background
- Not-allowed cursor (🚫)
- Read-only field
- Tooltip on hover explaining the lock

## Benefits

1. **Data Integrity**: Prevents breaking references in Purchase Orders, Sales, Stock, etc.
2. **User Guidance**: Clear visual feedback about why the field is locked
3. **Safe Operations**: Other fields can still be edited (description, prices, etc.)
4. **Automatic**: No manual configuration needed

## Technical Notes

- The check is performed ONLY in edit mode
- Uses case-insensitive comparison (UPPER/TRIM)
- Gracefully handles missing tables (silently skips)
- Error-tolerant with try-catch blocks
- Prevents data inconsistencies across the system

## Example Scenarios

**Scenario 1: New Item**
- Item Code field is editable
- No restrictions apply

**Scenario 2: Existing Item (Not Used)**
- Item exists in `items` table only
- Item Code field is editable
- Can be changed freely

**Scenario 3: Existing Item (In Use)**
- Item has been used in a Purchase Order
- Item Code field is locked
- User sees tooltip: "This item code cannot be changed because it is being used in other records..."
- User can still edit description, prices, commissions, etc.

## Status
✅ **FULLY IMPLEMENTED** - Feature is working as designed in itemreg.php
