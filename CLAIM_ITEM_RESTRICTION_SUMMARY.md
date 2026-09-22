# Claim Item Restriction - Implementation Summary

## Overview
Implemented validation to ensure that only selected unclaimed items can be added to the claim table, and the quantity cannot exceed the unclaimed quantity available.

## Date
January 30, 2025

## Problem Statement
Previously, users could add any item to the claim table regardless of what was selected from the unclaimed items list. This could lead to:
- Claiming items that were not part of the unclaimed freebies
- Exceeding the quantity available from the unclaimed items
- Inconsistency between what was selected and what was claimed

## Example Scenario
**Before:**
- Unclaimed item: MONARCH-FULL-FACE-HELMET (1 quantity)
- User could add ANY item to the claim table
- User could add unlimited quantity

**After:**
- Unclaimed item: MONARCH-FULL-FACE-HELMET (1 quantity) - SELECTED
- User can ONLY add MONARCH-FULL-FACE-HELMET to the claim table
- User can ONLY add up to 1 quantity total

## Changes Made to `claimitem.php`

### 1. Added Tracking Array for Selected Unclaimed Items
```javascript
let selectedUnclaimedItems = []; // Track selected unclaimed items with their allowed quantities
```

### 2. Enhanced `toggleItemSelection()` Function
- Now tracks which unclaimed items are selected (checked)
- Stores item data including: id, item_code, description, and quantity
- Adds/removes items from `selectedUnclaimedItems` array when checkboxes are toggled
- Provides visual feedback with border and background color changes

### 3. Added Validation in `addItemToTable()` Function
**Validation 1: Item Must Be Selected**
```javascript
const selectedItem = selectedUnclaimedItems.find(item => item.item_code === itemCode);
if (!selectedItem) {
    alert(`Cannot add "${itemCode}". This item is not selected from the unclaimed items list.`);
    return;
}
```

**Validation 2: Quantity Cannot Exceed Unclaimed Quantity**
```javascript
// Calculate total quantity already in table for this item code
let totalQtyInTable = 0;
claimedItemsTable.forEach(item => {
    if (item.itemCode === itemCode) {
        totalQtyInTable += item.quantity;
    }
});

const totalRequestedQty = totalQtyInTable + quantity;

if (totalRequestedQty > selectedItem.quantity) {
    alert(`Cannot add ${quantity} of "${itemCode}". Total quantity (${totalRequestedQty}) would exceed the unclaimed quantity (${selectedItem.quantity}).`);
    return;
}
```

### 4. Added Validation in `selectSearchItem()` Function
When users search for items and select from the modal:
```javascript
const unclaimedItem = selectedUnclaimedItems.find(item => item.item_code === itemCode);
if (!unclaimedItem) {
    alert(`Cannot select "${itemCode}". This item is not in your selected unclaimed items list.`);
    closeSearchModal();
    return;
}
```

### 5. Enhanced `searchUnclaimedFreebies()` Function
When searching for a new invoice:
- Clears `selectedUnclaimedItems` array
- Clears `claimedItemsTable` array
- Resets the items table display

### 6. Added `updateRemainingQuantities()` Function
Real-time display of:
- How much has been claimed
- How much remains available
- Color-coded display (green if remaining, red if fully claimed)

Example display in unclaimed items card:
```
Claimed: 1 | Remaining: 0
```

### 7. Enhanced `renderItemsTable()` Function
Now calls `updateRemainingQuantities()` after rendering to update the visual feedback

### 8. Added Visual Info Message
Blue info banner at the top of the claim section:
```
ℹ️ Important: You can only add items that are selected (checked) from the unclaimed items list above. 
The quantity cannot exceed the unclaimed quantity.
```

## User Experience Flow

### Step 1: Search Invoice
User enters invoice number and clicks "Search"

### Step 2: View Unclaimed Items
System displays all unclaimed items for that invoice

### Step 3: Select Unclaimed Items
User checks the checkboxes for items they want to claim
- Visual feedback: Card border turns green, background changes
- Items are added to `selectedUnclaimedItems` array

### Step 4: Add Items to Claim Table
User can search for items or manually enter item code

**Validation happens:**
- ✅ Item must be in `selectedUnclaimedItems` array
- ✅ Total quantity (already in table + new quantity) cannot exceed unclaimed quantity
- ✅ Stock availability is still checked

### Step 5: Real-time Feedback
As items are added to the table:
- Unclaimed items panel shows "Claimed: X | Remaining: Y"
- User can see exactly how much is left to claim

### Step 6: Error Messages
Clear, informative error messages:
- "Cannot add [ITEM]. This item is not selected from the unclaimed items list."
- "Cannot add 2 of [ITEM]. Total quantity (2) would exceed the unclaimed quantity (1)."

## Benefits

### 1. Data Integrity
- Ensures claimed items match unclaimed freebies
- Prevents over-claiming quantities

### 2. User Clarity
- Clear visual feedback on what can be claimed
- Real-time quantity tracking
- Helpful error messages

### 3. Error Prevention
- Multiple validation layers
- Validates both on search selection and on add
- Prevents accidental mistakes

### 4. Auditability
- Console logging for debugging
- Clear tracking of selected items
- Easy to trace what was claimed vs. what was available

## Technical Details

### Arrays Used
1. `currentUnclaimedItems` - All unclaimed items from API
2. `selectedUnclaimedItems` - Items user has checked (allowed to claim)
3. `claimedItemsTable` - Items actually added to the claim table
4. `currentSearchResults` - Search results from item search modal

### Validation Points
1. When selecting from search modal
2. When adding item to table (main validation)
3. Stock availability check (existing)
4. Serial/IMEI validation (existing)

### Console Logging
Added detailed logging for debugging:
- Selected unclaimed items array
- Quantity calculations
- Allowed unclaimed quantity

## Testing Scenarios

### Scenario 1: Normal Flow
1. Search invoice with 1 MONARCH-FULL-FACE-HELMET (qty: 1)
2. Check the checkbox for MONARCH-FULL-FACE-HELMET
3. Search/enter MONARCH-FULL-FACE-HELMET
4. Add with quantity 1
5. ✅ Success - Item added to table

### Scenario 2: Trying to Add Non-Selected Item
1. Search invoice with MONARCH-FULL-FACE-HELMET (qty: 1)
2. Do NOT check the checkbox
3. Try to add MONARCH-FULL-FACE-HELMET
4. ❌ Error - "This item is not selected from the unclaimed items list"

### Scenario 3: Exceeding Quantity
1. Search invoice with MONARCH-FULL-FACE-HELMET (qty: 1)
2. Check the checkbox for MONARCH-FULL-FACE-HELMET
3. Add with quantity 1 (Success)
4. Try to add with quantity 1 again
5. ❌ Error - "Total quantity (2) would exceed the unclaimed quantity (1)"

### Scenario 4: Multiple Items
1. Search invoice with 2 unclaimed items
2. Check both checkboxes
3. Add first item (Success)
4. Add second item (Success)
5. Try to add third item not in list
6. ❌ Error - Not in selected list

### Scenario 5: Partial Quantities
1. Search invoice with HELMET (qty: 3)
2. Check the checkbox
3. Add HELMET with quantity 1 (Success - Claimed: 1 | Remaining: 2)
4. Add HELMET with quantity 1 (Success - Claimed: 2 | Remaining: 1)
5. Add HELMET with quantity 1 (Success - Claimed: 3 | Remaining: 0)
6. Try to add HELMET with quantity 1 again
7. ❌ Error - Exceeds unclaimed quantity

## Files Modified
- `c:\xampp\htdocs\MOTOGAM\claimitem.php`

## Version
Claim Item Restriction - Version 3.0
(Previous: Stock validation added - Version 2.0)

## Notes
- All existing functionality remains intact
- Stock validation still works as before
- Serial/IMEI validation still works as before
- Only adds new validation layer for unclaimed item restrictions
- Backward compatible with existing data structures
