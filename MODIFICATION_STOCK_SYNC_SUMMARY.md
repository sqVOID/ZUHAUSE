# Purchase Order Modification - Stock on Hand Synchronization

## Overview
Updated `save_po_modifications.php` to automatically synchronize changes with the `stock_on_hand` table when modifications are made to purchase orders.

## Implementation Date
July 1, 2026

## Changes Made

### 1. **Delete Items - Remove from Stock on Hand**
When an item is removed from a purchase order:
- **Serialized Items (IMEI)**: Each serial number is deleted from `stock_on_hand` where:
  - `imei` matches the serial number
  - `item_code` matches the item model
  - `dr_number` matches the PO number
  
- **Non-Serialized Items (Accessories)**: The stock entry is deleted where:
  - `family_code` matches the item's family code
  - `dr_number` matches the PO number

### 2. **Update Item Models - Update Stock Information**
When item model or description is changed:
- Updates `stock_on_hand` records where:
  - `family_code` matches the item
  - `dr_number` matches the PO number
- Updates both `item_code` (item_model) and `description` fields
- Works for both serialized and non-serialized items

### 3. **Update Serial Numbers - Smart Synchronization**
When serial numbers are modified for an item:

#### Three Operations:
1. **Delete Removed Serials**: Serials that were in the old list but not in the new list
   - Removes them from `stock_on_hand`
   
2. **Add New Serials**: Serials that are in the new list but not in the old list
   - Inserts them into `stock_on_hand` with:
     - `item_type` = 'IMEI'
     - `status` = 'Active'
     - `quantity` = 1
     - `dr_number` = PO number
     - `branch` = PO branch
   - Includes duplicate check to prevent conflicts
   
3. **Update Existing Serials**: Serials that exist in both old and new lists
   - Updates their `item_code`, `description`, and `family_code` in case these details changed

## Key Features

### Transaction Safety
- All operations wrapped in database transaction
- Automatic rollback on any error
- Ensures data consistency between `purchase_order_items` and `stock_on_hand`

### **AGING and IOU Preservation** ⚠️
- **CRITICAL**: When modifying items, the original `dr_date` and `system_entry_date` are preserved
- This ensures AGING and IOU calculations remain accurate and are NOT reset to 0 days
- When adding new serials, they inherit the original dates from the existing stock entry
- When updating existing serials, the dates are NOT modified
- Formula:
  - **AGING** = `DATEDIFF(CURDATE(), dr_date)` 
  - **IOU** = `DATEDIFF(CURDATE(), system_entry_date)`

### Duplicate Protection
- Checks for existing serial numbers before insertion
- Prevents duplicate IMEI entries in stock

### Branch Handling
- Resolves branch name from branch code
- Supports 'ALL' branches designation
- Uses PO's original branch assignment

### Data Integrity
- Validates PO existence before processing
- Requires "reason to modify" field
- Maintains audit trail through PO modification tracking

## Database Fields Used

### stock_on_hand table:
- `item_code` - Item model/SKU
- `description` - Item description
- `item_type` - 'IMEI' for serialized, 'Accessories' for non-serialized
- `imei` - Serial number (for serialized items)
- `dr_number` - Reference to PO number
- `branch` - Branch name
- `dr_date` - Document reference date
- `system_entry_date` - Entry timestamp
- `status` - 'Active' for available stock
- `quantity` - 1 for serialized, actual count for non-serialized
- `family_code` - Item family classification

## Error Handling
All operations include specific error messages:
- "Failed to delete serialized item from stock"
- "Failed to delete non-serialized item from stock"
- "Failed to update item model in stock"
- "Failed to delete old serial from stock"
- "Failed to add new serial to stock"
- "Failed to update serial in stock"

## Use Cases

### Example 1: Edit Serial Number
```
Before: IMEI 123456789 (dr_date: 2026-06-01, AGING: 30 days)
After:  IMEI 987654321

Result:
- Deletes stock entry for IMEI 123456789
- Inserts new stock entry for IMEI 987654321
- NEW: Uses SAME dr_date (2026-06-01) and system_entry_date
- AGING remains 30 days (NOT reset to 0)
```

### Example 2: Update Same Serial
```
Before: IMEI 123456789, Model: PHONE-A (AGING: 15 days, IOU: 10 days)
After:  IMEI 123456789, Model: PHONE-B

Result:
- Updates stock entry for IMEI 123456789
- Changes item_code to PHONE-B
- dr_date and system_entry_date are PRESERVED
- AGING and IOU remain unchanged (15 days and 10 days)
```

### Example 3: Remove Entire Item
```
Before: Item exists in PO with serial IMEI 123456789 (AGING: 45 days)
After:  Item deleted from PO

Result:
- Deletes stock entry for IMEI 123456789 completely
- Removes item from purchase_order_items
- AGING data is lost (item removed from stock)
```

## Testing Recommendations

1. **Test serialized item modification**
   - Add, edit, and delete serial numbers
   - Verify stock_on_hand reflects changes
   - **IMPORTANT**: Check AGING and IOU values remain unchanged after modification

2. **Test non-serialized item modification**
   - Change quantities and descriptions
   - Verify stock quantities update correctly

3. **Test item deletion**
   - Delete items with and without serials
   - Confirm stock entries are removed

4. **Test model changes**
   - Update item models
   - Verify stock item_code updates
   - **IMPORTANT**: Verify dr_date and system_entry_date are NOT changed

5. **Test AGING/IOU preservation specifically**
   - Find an item with AGING > 0 days
   - Modify its serial number
   - Verify the new serial has the SAME AGING value
   - Verify IOU is also preserved

6. **Test error scenarios**
   - Duplicate serials
   - Missing PO
   - Database connection issues

## Notes
- Only processes items that are already in stock (from received POs)
- Maintains original dr_number for traceability
- Preserves branch assignment from original PO
- Compatible with existing stock management workflows
