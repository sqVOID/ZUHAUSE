# Skip Receipt Revert Feature Implementation

## Overview
Added the ability to revert approved skip receipt requests, which restores the invoice number counter back to its previous value.

## Problem Solved
When a skip receipt request is approved (e.g., invoice 0151 is skipped), the system increments to 0152. The revert feature allows administrators to undo this action and restore the counter back to 0151 if the skip was done in error.

## Changes Made

### 1. Database Schema
**File**: `add_revert_columns_to_skip_receipt.php`
- Added `reverted_by` VARCHAR(255) - stores username of person who reverted
- Added `reverted_date` DATETIME - timestamp of revert action
- Added `revert_reason` TEXT - explanation for why the revert was done
- Added `is_reverted` BOOLEAN - flag to mark if request has been reverted

### 2. Backend Processing
**File**: `process_skip_receipt.php`

#### Fixed Invoice Number Padding Issue
- **Problem**: When approving skip requests, invoice "0151" would become "152" (lost leading zero)
- **Solution**: Implemented `str_pad()` to preserve zero-padding format
  - Reads current invoice number (e.g., "0151")
  - Calculates padding length (4 digits)
  - Increments and pads: `str_pad(152, 4, '0', STR_PAD_LEFT)` → "0152"

#### Added Revert Action
- **Action**: `revert`
- **Required Parameter**: `revert_reason`
- **Logic**:
  1. Validates the request is in 'Approved' status
  2. Gets current booklet number from database
  3. Decrements the number while preserving padding
     - Example: "0152" → "0151"
     - Example: "00002" → "00001"
  4. Updates the booklet_numbers table with decremented value
  5. Marks the request record as reverted with timestamp and reason
- **Safety Check**: Prevents reverting if invoice number is already at minimum (1 or 0001)

### 3. Frontend Interface
**File**: `salesskipapproval.php`

#### Table Updates
- Added "Reverted" column to display revert status
- Shows "Yes" (in red) with the reverter's name if reverted
- Shows "No" (in gray) if not reverted

#### Action Buttons
- **Pending requests**: Show "Approve" and "Reject" buttons
- **Approved requests (not reverted)**: Show "Revert" button (orange)
- **Reverted or Rejected requests**: Show "No actions available"

#### Styling
- Added `.btn-revert` CSS class with orange theme (#ff9800)
- Hover effect changes to darker orange (#f57c00)

#### JavaScript Functions
- Added `revertRequest(requestId)` function
  1. Prompts user for revert reason
  2. Shows confirmation dialog
  3. Sends AJAX request to `process_skip_receipt.php`
  4. Displays success/error message
  5. Reloads page to show updated data

## Usage Flow

### Scenario 1: Skip and Revert
1. Current invoice number: **0150**
2. User requests to skip invoice **0151**
3. Admin approves → Invoice counter moves to **0152**
4. Admin realizes mistake and clicks "Revert"
5. Enters reason: "Approved by mistake"
6. Invoice counter returns to **0151**

### Scenario 2: Works with Different Padding
- **4-digit padding**: 0001 → 0002 → (revert) → 0001
- **5-digit padding**: 00001 → 00002 → (revert) → 00001
- **6-digit padding**: 000001 → 000002 → (revert) → 000001

## Key Features

### Audit Trail
- Records who reverted the request
- Records when it was reverted
- Requires and stores a reason for reverting
- Original approval information is preserved

### Data Integrity
- Preserves invoice number format (padding with leading zeros)
- Prevents reverting below minimum number
- Only allows reverting approved requests
- Cannot revert a request that's already been reverted

### User Experience
- Clear visual indicators (color-coded buttons)
- Confirmation prompts prevent accidental reverts
- Informative success/error messages
- Real-time page updates after actions

## Testing Checklist

- ✅ Run migration script: `c:\xampp\php\php.exe add_revert_columns_to_skip_receipt.php`
- ✅ Test approve with 4-digit invoice (0151 → 0152)
- ✅ Test revert with 4-digit invoice (0152 → 0151)
- ✅ Test with 5-digit invoices (00001 → 00002 → 00001)
- ✅ Verify revert reason is required
- ✅ Verify cannot revert pending or rejected requests
- ✅ Verify revert button disappears after reverting
- ✅ Check audit trail in database (reverted_by, reverted_date, revert_reason)

## Database Migration
To enable this feature, run:
```bash
c:\xampp\php\php.exe c:\xampp\htdocs\MOTOGAM\add_revert_columns_to_skip_receipt.php
```

## Files Modified
1. `add_revert_columns_to_skip_receipt.php` - NEW (migration script)
2. `process_skip_receipt.php` - MODIFIED (added revert action + fixed padding)
3. `salesskipapproval.php` - MODIFIED (added UI elements)

## Security Considerations
- User authentication required (session check)
- SQL injection prevention (prepared statements)
- XSS protection (htmlspecialchars on output)
- Action validation (only approved requests can be reverted)
- Reason required for all destructive actions
