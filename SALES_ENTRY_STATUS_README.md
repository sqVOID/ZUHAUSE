# Sales Entry Status Feature

## Overview
This feature allows users to update the status of items in stock from "Good Stock" to "Defective" or vice versa.

## How It Works

### 1. Frontend (salesentry-status.php)
- User selects **Stock Type**: Good Stock or Defective
- User selects **Branch** (filtered by user access)
- User enters **Date** (auto-generated)
- User enters **Remarks** (required)
- User adds items by:
  - Entering item code/description manually and adding
  - Searching for items via Search modal
  - Scanning serial numbers via ADD UNIT modal (for serialized items)
- Click **Save** to update the status

### 2. Backend (save_sales_entry_status.php)
When Save is clicked:
- Validates all required fields
- Processes each item in the list
- Updates the `stock_on_hand` table:
  - For **serialized items** (with IMEI): Updates by `item_code`, `imei`, and `branch`
  - For **non-serialized items** (accessories): Updates by `item_code` and `branch`
- Sets the status to:
  - **"Good Stock"** if Stock Type = "Good Stock"
  - **"Defective"** if Stock Type = "Defective"
- Logs the operation to `sales_entry_status_log` table
- Returns success message with count of updated items

### 3. Database Changes
The migration script (`add_sales_entry_status_support.php`) creates:

#### Modified `stock_on_hand` table:
- `status` VARCHAR(50) - Stores "Good Stock" or "Defective"
- `modified_by` VARCHAR(100) - Username who modified the status
- `modified_at` DATETIME - Timestamp of modification

#### New `sales_entry_status_log` table:
- `id` - Primary key
- `entry_date` - Date of entry
- `branch_code` - Branch code
- `branch_name` - Branch name
- `stock_type` - Stock type selected (Good Stock/Defective)
- `remarks` - User remarks
- `items_count` - Total items submitted
- `updated_count` - Successfully updated items
- `created_by` - User who performed the operation
- `created_at` - Timestamp

## Installation Steps

1. **Run the migration script** to create necessary tables and columns:
   ```
   http://localhost/ZUHAUSE/add_sales_entry_status_support.php
   ```

2. **Access the Sales Entry Status page**:
   ```
   http://localhost/ZUHAUSE/salesentry-status.php
   ```

3. **View updated statuses** in Stock on Hand pages:
   - `sohandserial.php` - Shows status for serialized items
   - `sohandaccessories.php` - Shows status for accessories

## Usage Example

### Scenario 1: Mark items as Defective
1. Go to Sales Entry Status page
2. Select Stock Type: **Defective**
3. Select Branch: **Infanta**
4. Enter Remarks: "Damaged during shipping"
5. Scan serial numbers or add items manually
6. Click Save
7. ✓ Status updated to "Defective" in `stock_on_hand`

### Scenario 2: Mark defective items back to Good Stock
1. Go to Sales Entry Status page
2. Select Stock Type: **Good Stock**
3. Select Branch: **Infanta**
4. Enter Remarks: "Items repaired and tested"
5. Add the same items
6. Click Save
7. ✓ Status updated to "Good Stock" in `stock_on_hand`

## Features

✅ Branch filtering based on user access
✅ Support for both serialized (IMEI) and non-serialized items
✅ Search functionality for items
✅ Serial number scanning
✅ Stock availability checking
✅ Transaction logging
✅ Audit trail (modified_by, modified_at)
✅ Detailed success/error messages
✅ Status visible in Stock on Hand reports

## Security

- Session-based authentication required
- Branch access control enforced
- User tracking for all modifications
- SQL injection prevention via prepared statements
- Transaction support for data integrity

## Files Created/Modified

### New Files:
- `salesentry-status.php` - Frontend form
- `save_sales_entry_status.php` - Backend processing
- `add_sales_entry_status_support.php` - Database migration
- `SALES_ENTRY_STATUS_README.md` - Documentation

### Modified Files:
- `sohandserial.php` - Added Status column
- `sohandaccessories.php` - Added Status column and modified SQL queries

## Notes

- Default status for all existing items is "Good Stock"
- Items not found in stock will be listed in the response
- The log table keeps history of all status change operations
- Users can only access branches assigned to their account (except Super-Admin)
