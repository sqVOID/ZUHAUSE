# Claim Item Save Functionality - Implementation Summary

## Overview
Implemented complete save functionality for claimed items, including database storage, validation, and status updates for unclaimed freebies.

## Date
January 30, 2025

## Features Implemented

### 1. Backend API: `save_claimed_items.php`

#### Database Table: `claimed_items`
Automatically created if it doesn't exist. Structure:

```sql
CREATE TABLE claimed_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(50) NOT NULL,
    unclaimed_freebie_id INT NOT NULL,
    
    customer_name VARCHAR(255) NOT NULL,
    customer_address TEXT NULL,
    customer_contact VARCHAR(100) NULL,
    customer_email VARCHAR(255) NULL,
    
    item_code VARCHAR(100) NOT NULL,
    item_description VARCHAR(255) NOT NULL,
    imei VARCHAR(100) NULL,
    quantity INT NOT NULL DEFAULT 1,
    
    remarks TEXT NULL,
    
    claimed_by VARCHAR(100) NULL,
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    branch_code VARCHAR(10) NULL,
    
    INDEX idx_invoice_no (invoice_no),
    INDEX idx_unclaimed_freebie (unclaimed_freebie_id),
    INDEX idx_item_code (item_code),
    INDEX idx_imei (imei),
    INDEX idx_claimed_at (claimed_at),
    INDEX idx_claimed_by (claimed_by)
)
```

#### API Endpoint Details

**URL**: `save_claimed_items.php`  
**Method**: `POST`  
**Content-Type**: `application/json`

**Request Body**:
```json
{
    "invoice_no": "INV-12345",
    "customer_name": "John Doe",
    "customer_address": "123 Main St",
    "customer_contact": "09123456789",
    "customer_email": "john@example.com",
    "remarks": "Customer picked up items",
    "claimed_items": [
        {
            "itemCode": "MONARCH-FULL-FACE-HELMET",
            "description": "Monarch Full Face Helmet",
            "imei": "",
            "quantity": 1
        }
    ],
    "unclaimed_item_ids": [
        {
            "id": 5,
            "item_code": "MONARCH-FULL-FACE-HELMET"
        }
    ]
}
```

**Success Response**:
```json
{
    "success": true,
    "message": "Successfully claimed 1 item(s) for invoice INV-12345",
    "inserted_count": 1,
    "updated_count": 1,
    "invoice_no": "INV-12345"
}
```

**Error Response**:
```json
{
    "success": false,
    "message": "Invoice number is required"
}
```

#### Backend Validation

1. **Required Fields Validation**:
   - Invoice number
   - Customer name
   - At least one claimed item
   - Unclaimed item IDs

2. **Data Integrity**:
   - JSON parsing validation
   - Item data structure validation
   - Unclaimed freebie ID matching

3. **Transaction Safety**:
   - Uses database transactions
   - Rollback on any error
   - Ensures data consistency

#### Backend Process Flow

```
1. Receive POST data
   ↓
2. Validate JSON format
   ↓
3. Validate required fields
   ↓
4. Start database transaction
   ↓
5. Create claimed_items table (if not exists)
   ↓
6. Insert each claimed item with:
   - Customer details
   - Item details (code, description, IMEI, quantity)
   - Remarks
   - Metadata (claimed_by, claimed_at, branch_code)
   ↓
7. Update unclaimed_freebies status to 'claimed'
   ↓
8. Commit transaction
   ↓
9. Return success response
   
   [If any error occurs]
   ↓
   Rollback transaction
   ↓
   Return error response
```

### 2. Frontend: `claimitem.php`

#### New Function: `saveClaimedItems()`

**Validation Layers**:

1. **Invoice Search Check**:
```javascript
if (!currentCustomerDetails) {
    alert('Please search for an invoice first');
    return;
}
```

2. **Selected Items Check**:
```javascript
if (selectedUnclaimedItems.length === 0) {
    alert('Please select at least one item from the unclaimed items list');
    return;
}
```

3. **Claimed Items Check**:
```javascript
if (claimedItemsTable.length === 0) {
    alert('Please add at least one item to the claim table');
    return;
}
```

4. **Partial Claim Warning** (Optional):
```javascript
// Warns if not all selected items are fully claimed
if (hasUnclaimedQuantity) {
    const confirmSave = confirm('Warning: Not all selected items have been fully claimed...');
    if (!confirmSave) return;
}
```

#### Data Preparation

The function prepares the following data:
- Invoice number from search input
- Customer details from API response
- All items in the claim table
- Mapping of unclaimed freebie IDs to item codes
- Remarks from textarea

#### User Feedback

**Loading State**:
- Button text changes to "Saving..."
- Button is disabled during save
- Prevents double-submission

**Success**:
- Shows success message with count
- Resets entire form
- Clears all state

**Error**:
- Shows descriptive error message
- Preserves form state for retry
- Logs error to console

#### New Function: `resetClaimForm()`

Completely resets the form after successful save:
- Clears invoice input
- Clears unclaimed items panel
- Clears customer details panel
- Clears item input fields
- Clears claimed items table
- Clears remarks textarea
- Resets all state variables
- Hides info messages

## Complete User Flow

```
┌─────────────────────────────────────────────────────────────────┐
│  1. User searches invoice                                        │
│     → Unclaimed items displayed                                  │
│     → Customer details displayed                                 │
└────────────────────────┬────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  2. User selects (checks) unclaimed items                        │
│     → Items added to selectedUnclaimedItems[]                    │
│     → Visual feedback (green border)                             │
└────────────────────────┬────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  3. User adds items to claim table                               │
│     ✅ Validation: Must be in selectedUnclaimedItems            │
│     ✅ Validation: Quantity cannot exceed unclaimed quantity     │
│     ✅ Validation: Stock availability                            │
│     → Items added to claimedItemsTable[]                         │
└────────────────────────┬────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  4. User enters remarks (optional)                               │
└────────────────────────┬────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  5. User clicks "Save" button                                    │
│     ✅ Validation: Invoice searched                              │
│     ✅ Validation: Items selected from unclaimed list            │
│     ✅ Validation: Items added to table                          │
│     ⚠️  Warning: Not all quantities claimed (optional)          │
└────────────────────────┬────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  6. Frontend sends data to backend                               │
│     POST /save_claimed_items.php                                 │
│     Body: {invoice_no, customer, items, unclaimed_ids, remarks}  │
└────────────────────────┬────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  7. Backend processes request                                    │
│     → Validates data                                             │
│     → Starts transaction                                         │
│     → Creates table if needed                                    │
│     → Inserts claimed items                                      │
│     → Updates unclaimed_freebies status to 'claimed'             │
│     → Commits transaction                                        │
└────────────────────────┬────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  8. Success response returned                                    │
│     → Shows success message                                      │
│     → Resets entire form                                         │
│     → User can start new claim                                   │
└─────────────────────────────────────────────────────────────────┘
```

## Database Changes

### Inserts to `claimed_items` table
For each item in the claim:
- Links to original unclaimed_freebie record via `unclaimed_freebie_id`
- Stores complete customer information
- Stores item details with IMEI if applicable
- Records who claimed it and when
- Stores branch code for tracking

### Updates to `unclaimed_freebies` table
For each claimed item:
- Changes `status` from 'unclaimed' to 'claimed'
- Prevents the same freebie from being claimed multiple times

## Error Handling

### Frontend Errors
1. **No invoice searched**: "Please search for an invoice first"
2. **No items selected**: "Please select at least one item from the unclaimed items list"
3. **No items in table**: "Please add at least one item to the claim table"
4. **Partial claim**: "Warning: Not all selected items have been fully claimed..." (with details)
5. **Network error**: "Error saving claimed items: [error message]"

### Backend Errors
1. **Invalid JSON**: "Invalid JSON data received"
2. **Missing invoice**: "Invoice number is required"
3. **Missing customer**: "Customer name is required"
4. **No items**: "At least one item must be claimed"
5. **No unclaimed IDs**: "Unclaimed item IDs are required"
6. **Invalid item data**: "Invalid item data: missing required fields"
7. **ID not found**: "Unclaimed freebie ID not found for item: [item_code]"
8. **Database errors**: Specific SQL error messages
9. **Transaction errors**: Automatic rollback with error message

## Security Features

1. **Session Check**: Requires active user session
2. **SQL Injection Prevention**: Uses prepared statements with parameter binding
3. **Transaction Safety**: All-or-nothing approach
4. **Input Validation**: Validates all required fields
5. **User Tracking**: Records who claimed items and when
6. **Branch Tracking**: Records branch code for accountability

## Testing Scenarios

### Success Case
```
1. Search invoice: INV-001
2. Select HELMET (qty: 1)
3. Add HELMET qty 1 to table
4. Enter remarks: "Customer picked up"
5. Click Save
6. ✅ Success: "Successfully claimed 1 item(s) for invoice INV-001"
7. Form resets automatically
```

### Error Case: No Items Selected
```
1. Search invoice: INV-001
2. Do NOT check any checkboxes
3. Click Save
4. ❌ Error: "Please select at least one item from the unclaimed items list"
```

### Error Case: Items Selected But Not Added
```
1. Search invoice: INV-001
2. Check HELMET checkbox
3. Do NOT add to table
4. Click Save
5. ❌ Error: "Please add at least one item to the claim table"
```

### Partial Claim Warning
```
1. Search invoice: INV-001
2. Select HELMET (qty: 3) and GLOVES (qty: 2)
3. Add only HELMET qty 2 to table (leaving 1 unclaimed)
4. Click Save
5. ⚠️ Warning: "Not all selected items have been fully claimed:
   - HELMET: 1 remaining
   - GLOVES: 2 remaining
   
   Do you still want to save?"
6. User can choose:
   - Cancel → Go back and add more
   - OK → Save partial claim
```

## Console Logging

Debug information logged:
- Selected unclaimed items array
- Save data payload before sending
- Backend response
- Any errors during save process

Example console output:
```
Selected unclaimed items: [{id: 5, item_code: "HELMET", description: "...", quantity: 1}]
Saving claimed items: {invoice_no: "INV-001", customer_name: "John Doe", ...}
```

## API Response Examples

### Success
```json
{
    "success": true,
    "message": "Successfully claimed 2 item(s) for invoice INV-12345",
    "inserted_count": 2,
    "updated_count": 2,
    "invoice_no": "INV-12345"
}
```

### Error - Missing Invoice
```json
{
    "success": false,
    "message": "Invoice number is required"
}
```

### Error - Database Error
```json
{
    "success": false,
    "message": "Failed to insert claimed item: Duplicate entry..."
}
```

## Files Created/Modified

### Created:
1. `c:\xampp\htdocs\MOTOGAM\save_claimed_items.php` - Backend API

### Modified:
1. `c:\xampp\htdocs\MOTOGAM\claimitem.php` - Added save functionality
   - Added ID to remarks textarea
   - Added onclick handler to Save button
   - Added `saveClaimedItems()` function
   - Added `resetClaimForm()` function

## Database Tables

### New Table: `claimed_items`
Stores all claimed items with full details and traceability

### Updated Table: `unclaimed_freebies`
Status updated from 'unclaimed' to 'claimed' when items are claimed

## Benefits

1. **Complete Audit Trail**: Every claim is recorded with who, when, and what
2. **Data Integrity**: Transaction ensures all-or-nothing saves
3. **User Experience**: Clear validation messages and automatic form reset
4. **Traceability**: Links claimed items back to original unclaimed freebies
5. **Reporting**: Can track claimed items by invoice, date, branch, user, etc.
6. **Prevention**: Claimed freebies cannot be claimed again (status update)

## Future Enhancements (Optional)

1. **Print Claim Receipt**: Generate PDF receipt after successful claim
2. **Claim History**: View all claims for a customer or invoice
3. **Unclaim Function**: Ability to reverse a claim (admin only)
4. **Email Notification**: Send email to customer when items are claimed
5. **SMS Notification**: Send SMS reminder to customer
6. **Barcode Scanning**: Quick IMEI entry via barcode scanner
7. **Stock Deduction**: Automatically deduct from inventory when claimed
8. **Multiple Invoice Claim**: Claim items from multiple invoices at once
9. **Claim Analytics**: Dashboard showing claim statistics

## Version
Claim Item Save Functionality - Version 1.0
(Part of Claim Item Restriction - Version 3.0)
