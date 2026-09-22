# Allocation Set Button - Complete Implementation Guide

## ✅ Implementation Complete!

The **Set** button in the Allocate Modal is now fully functional and connected to the database.

## How It Works

### User Flow

```
1. User clicks ALLOCATE on item
   ↓
2. Modal opens with item details
   ↓
3. User selects branch
   ↓
4. User enters quantity
   ↓
5. User clicks SET button
   ↓
6. JavaScript validates input
   ↓
7. AJAX POST to save_allocation.php
   ↓
8. Backend validates and saves to database
   ↓
9. Page reloads with updated data
   ↓
10. Branch allocation appears in table
```

### Technical Flow

```
┌─────────────────────────────────────────────────────────────┐
│  FRONTEND (purchaseorder-details.php)                        │
└─────────────────────────────────────────────────────────────┘
                          │
                          │ User clicks SET
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  JavaScript Validation                                       │
│  • Branch selected? ✓                                        │
│  • Quantity > 0? ✓                                          │
│  • Not over-allocated? ✓                                     │
└─────────────────────────────────────────────────────────────┘
                          │
                          │ AJAX POST Request
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  BACKEND (save_allocation.php)                               │
│                                                              │
│  Step 1: Validate Inputs                                    │
│  • PO ID valid?                                             │
│  • Family code exists?                                       │
│  • Branch name valid?                                        │
│  • Quantity > 0?                                            │
│                                                              │
│  Step 2: Get Item Details                                   │
│  • Fetch cost from purchase_order_items                     │
│  • Get total quantity ordered                               │
│                                                              │
│  Step 3: Check Current Allocations                          │
│  • Sum existing allocations for this item                   │
│  • Calculate available quantity                             │
│                                                              │
│  Step 4: Validate Allocation                                │
│  • New allocation + existing ≤ total? ✓                    │
│                                                              │
│  Step 5: Save to Database                                   │
│  • If exists: UPDATE allocation (add quantity)              │
│  • If new: INSERT new allocation record                     │
│                                                              │
│  Step 6: Update Item                                        │
│  • Update allocated_quantity in purchase_order_items        │
│  • Recalculate from all allocations                         │
└─────────────────────────────────────────────────────────────┘
                          │
                          │ JSON Response
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  FRONTEND Response Handler                                   │
│  • Show success/error message                               │
│  • Reload page to show updated data                         │
└─────────────────────────────────────────────────────────────┘
```

## Database Operations

### Tables Involved

#### 1. purchase_order_allocations (Main)
```sql
INSERT INTO purchase_order_allocations 
(po_id, po_number, branch_name, branch_code, family_code, 
 quantity, received_qty, cost, status)
VALUES (?, ?, ?, ?, ?, ?, 0, ?, 'Waiting')
```

**Or if allocation exists:**
```sql
UPDATE purchase_order_allocations 
SET quantity = quantity + ?, 
    cost = ?, 
    updated_at = NOW()
WHERE po_id = ? 
  AND family_code = ? 
  AND branch_name = ?
```

#### 2. purchase_order_items (Update)
```sql
UPDATE purchase_order_items 
SET allocated_quantity = (
    SELECT COALESCE(SUM(quantity), 0)
    FROM purchase_order_allocations
    WHERE po_id = ? AND family_code = ?
)
WHERE po_id = ? AND family_code = ?
```

### Data Flow Example

**Scenario:** Allocate 15 units of FC-001 to Main Branch

**Before:**
```
purchase_order_items:
- family_code: FC-001
- quantity: 50
- allocated_quantity: 20

purchase_order_allocations:
- Branch A: 10 units
- Branch B: 10 units
Total allocated: 20
```

**User Action:**
```
Branch: Main Branch
Family Code: FC-001
Quantity: 15
```

**After:**
```
purchase_order_items:
- family_code: FC-001
- quantity: 50
- allocated_quantity: 35 (20 + 15)

purchase_order_allocations:
- Branch A: 10 units
- Branch B: 10 units
- Main Branch: 15 units (NEW!)
Total allocated: 35
```

## Validation Rules

### Frontend Validation (JavaScript)

1. **Branch Selection**
   ```javascript
   if (!branch) {
       alert('Please select a branch!');
       return;
   }
   ```

2. **Quantity Validation**
   ```javascript
   if (qty <= 0) {
       alert('Please enter a quantity greater than 0!');
       return;
   }
   ```

3. **Over-Allocation Check**
   ```javascript
   const quantityLeft = parseInt(document.getElementById('itemQuantityLeft').textContent);
   if (quantityLeft < 0) {
       alert('Allocated quantity exceeds available quantity!');
       return;
   }
   ```

### Backend Validation (PHP)

1. **Input Validation**
   - PO ID must be positive integer
   - Family code must not be empty
   - Branch name must not be empty
   - Quantity must be greater than 0

2. **Database Existence Checks**
   - PO must exist in purchase_orders
   - Item must exist in purchase_order_items
   - Branch must exist in branches

3. **Business Logic Validation**
   ```php
   if (($total_allocated + $quantity) > $total_quantity) {
       $available = $total_quantity - $total_allocated;
       return "Only {$available} units available.";
   }
   ```

## Success & Error Handling

### Success Response

```json
{
    "success": true,
    "message": "Allocation created successfully! 15 units allocated to Main Branch",
    "redirect": "purchaseorder-details.php?id=123"
}
```

**Frontend Action:**
- Shows success alert
- Reloads page
- Updated data appears in tables

### Error Responses

#### Validation Error
```json
{
    "success": false,
    "message": "Quantity must be greater than 0."
}
```

#### Over-Allocation Error
```json
{
    "success": false,
    "message": "Allocation exceeds available quantity. Only 15 units available."
}
```

#### Database Error
```json
{
    "success": false,
    "message": "Database error: [error details]"
}
```

**Frontend Action:**
- Shows error alert
- Re-enables Set button
- User can correct and retry

## Features Implemented

### ✅ Core Functionality

1. **Save New Allocation**
   - Creates record in purchase_order_allocations
   - Updates allocated_quantity in purchase_order_items
   - Associates with correct branch and PO

2. **Update Existing Allocation**
   - If branch already has allocation for this item
   - Adds quantity to existing (cumulative)
   - Updates timestamp

3. **Real-Time Validation**
   - Prevents over-allocation
   - Checks available quantity
   - Validates all inputs

4. **Database Integrity**
   - Atomic transactions
   - Proper foreign keys
   - Cascading updates

5. **User Feedback**
   - Loading state ("Saving...")
   - Success messages
   - Error messages
   - Page refresh with updated data

### ✅ Additional Features

1. **Cost Calculation**
   - Automatically fetches item cost
   - Calculates total cost for allocation
   - Stores cost per unit

2. **Branch Code Lookup**
   - Automatically retrieves branch code
   - Stores for reference

3. **Status Management**
   - Sets initial status to "Waiting"
   - Ready for receiving workflow

4. **Timestamp Tracking**
   - created_at on insert
   - updated_at on update

## Testing Scenarios

### Test Case 1: Fresh Allocation

**Setup:**
- Item FC-001: 50 units, 0 allocated
- No existing allocations

**Action:**
- Select Branch A
- Enter 20 units
- Click SET

**Expected Result:**
- ✅ Success message
- ✅ Page reloads
- ✅ Branch A appears in allocations table
- ✅ Item shows 20/50 allocated
- ✅ Quantity Left: 30

### Test Case 2: Multiple Allocations

**Setup:**
- Item FC-001: 50 units, 20 allocated to Branch A

**Action 1:**
- Select Branch B
- Enter 15 units
- Click SET

**Expected Result:**
- ✅ Branch B added
- ✅ Item shows 35/50 allocated

**Action 2:**
- Select Branch C
- Enter 15 units (total would be 50)
- Click SET

**Expected Result:**
- ✅ Branch C added
- ✅ Item shows 50/50 allocated (fully allocated)

### Test Case 3: Over-Allocation Prevention

**Setup:**
- Item FC-001: 50 units, 40 allocated

**Action:**
- Select Branch D
- Enter 20 units (would exceed 50)
- Click SET

**Expected Result:**
- ❌ Error: "Only 10 units available"
- ❌ Allocation NOT saved
- ✅ Button re-enabled for correction

### Test Case 4: Cumulative Allocation

**Setup:**
- Item FC-001: 50 units, Branch A has 10 units

**Action:**
- Select Branch A (already has allocation)
- Enter 5 more units
- Click SET

**Expected Result:**
- ✅ Branch A updated to 15 units (10 + 5)
- ✅ Message: "Added 5 units (new total: 15)"

### Test Case 5: Validation Errors

**Test 5a: No Branch Selected**
```
Action: Enter quantity but don't select branch
Result: Alert "Please select a branch!"
```

**Test 5b: Zero Quantity**
```
Action: Select branch, enter 0
Result: Alert "Please enter a quantity greater than 0!"
```

**Test 5c: Negative Quantity**
```
Action: Select branch, enter -5
Result: Input field prevents negative (min="0")
```

## Code Structure

### JavaScript Function

```javascript
function setAllocation() {
    // 1. Get form values
    // 2. Validate inputs
    // 3. Get PO ID from URL
    // 4. Show loading state
    // 5. Prepare FormData
    // 6. Send AJAX POST
    // 7. Handle response
    // 8. Show message
    // 9. Reload page or re-enable button
}
```

### PHP Endpoint

```php
save_allocation.php:
    // 1. Validate request method
    // 2. Get and validate POST data
    // 3. Verify PO exists
    // 4. Get item details and cost
    // 5. Check current allocations
    // 6. Validate new allocation won't exceed total
    // 7. Get branch code
    // 8. Check if allocation exists
    // 9a. UPDATE existing OR
    // 9b. INSERT new
    // 10. Update item's allocated_quantity
    // 11. Return JSON response
```

## API Documentation

### Endpoint: save_allocation.php

**Method:** POST

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| po_id | int | Yes | Purchase Order ID |
| family_code | string | Yes | Product family code |
| branch_name | string | Yes | Branch name |
| quantity | int | Yes | Quantity to allocate (> 0) |

**Response Format:**

Success:
```json
{
    "success": true,
    "message": "Success message",
    "redirect": "purchaseorder-details.php?id=123"
}
```

Error:
```json
{
    "success": false,
    "message": "Error description"
}
```

**HTTP Status:** Always 200 (check JSON for actual status)

## Security Considerations

### ✅ Implemented

1. **Session Check**
   - Requires valid session
   - User must be logged in

2. **SQL Injection Prevention**
   - Uses `real_escape_string()` for all inputs
   - Parameterized where applicable

3. **Input Validation**
   - Type checking (int, string)
   - Range validation (quantity > 0)
   - Required field checks

4. **Business Logic Enforcement**
   - Cannot over-allocate
   - Cannot allocate to non-existent PO
   - Cannot allocate invalid items

5. **XSS Prevention**
   - JSON responses (no HTML)
   - Escaped output on page reload

## Files Involved

1. **purchaseorder-details.php**
   - Contains the Allocate Modal
   - JavaScript `setAllocation()` function
   - Handles user interaction

2. **save_allocation.php** (NEW!)
   - Backend endpoint
   - Database operations
   - Validation logic

3. **purchase_order_allocations** (table)
   - Stores allocation records
   - Created by migration

4. **purchase_order_items** (table)
   - Updated allocated_quantity field
   - Tracks total allocated per item

## Troubleshooting

### Problem: "Invalid PO ID" error

**Cause:** PO ID not in URL or invalid  
**Solution:** Check URL has `?id=X` parameter

### Problem: "Item not found in purchase order"

**Cause:** Family code mismatch  
**Solution:** Verify family code exists in purchase_order_items

### Problem: "Only X units available" error

**Cause:** Trying to allocate more than available  
**Solution:** Check current allocations, reduce quantity

### Problem: Allocation saves but doesn't appear

**Cause:** Page didn't reload or cache issue  
**Solution:** Hard refresh (Ctrl+F5)

### Problem: "Network error"

**Cause:** save_allocation.php not accessible  
**Solution:** Check file exists, check console for errors

## Next Steps (Optional Enhancements)

1. **Edit Allocations** - Modify existing allocations
2. **Remove Allocations** - Delete allocations
3. **View Details** - See allocation breakdown
4. **Bulk Allocation** - Allocate multiple items at once
5. **Allocation History** - Track changes over time
6. **Email Notifications** - Notify branches of allocations
7. **Export** - Generate allocation reports

---

## Summary

✅ **Set Button Works!**  
✅ **Saves to Database**  
✅ **Updates Tables**  
✅ **Validates Input**  
✅ **Prevents Errors**  
✅ **User Friendly**  

The allocation system is now fully functional!
