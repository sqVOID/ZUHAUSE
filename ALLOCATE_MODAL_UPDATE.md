# Allocate Modal - Accurate Data Display Update

## Changes Made ✅

Updated the **Allocate Item Modal** in `purchaseorder-details.php` to display accurate, real-time data from the database instead of hardcoded sample values.

## What Was Fixed

### Before (Hardcoded Values)
```javascript
function allocateItem(familyCode) {
    // Always showed 10 and 10
    document.getElementById('itemQuantity').textContent = '10';
    document.getElementById('itemQuantityLeft').textContent = '10';
}
```

### After (Dynamic Values)
```javascript
function allocateItem(familyCode, quantity, allocatedQuantity) {
    // Uses actual values from database
    document.getElementById('itemQuantity').textContent = quantity;
    document.getElementById('itemAllocated').textContent = allocatedQuantity;
    document.getElementById('itemQuantityLeft').textContent = quantity - allocatedQuantity;
}
```

## Modal Display Updates

### New Table Structure

**Previous (3 columns):**
| Family Code | Quantity | Quantity Left |

**Updated (4 columns):**
| Family Code | Total Quantity | Already Allocated | Quantity Left |

### Example Display

**Scenario:** FC-001 has 50 units ordered, 20 already allocated to other branches

| Field | Value | Description |
|-------|-------|-------------|
| Family Code | FC-001 | The product code |
| Total Quantity | 50 | Original order quantity |
| Already Allocated | 20 | Units already assigned to branches |
| Quantity Left | 30 | Available for new allocation (50 - 20) |

## Real-Time Calculation

When you enter a quantity in the allocation field:

```
Example:
- Total Quantity: 50
- Already Allocated: 20
- User enters: 15 in branch allocation

Quantity Left = 50 - 20 - 15 = 15
```

If user enters too much (e.g., 35):
```
Quantity Left = 50 - 20 - 35 = -5 (shows in RED)
System prevents saving with validation alert
```

## Visual Indicators

### Normal State
- **Quantity Left ≥ 0:** Black text, normal weight
- User can proceed with allocation

### Over-Allocated State  
- **Quantity Left < 0:** RED text, bold weight
- Visual warning that quantity exceeds available
- System blocks submission with error message

## How It Works

### 1. Opening the Modal

When you click **ALLOCATE** on any item:

```php
<button onclick="allocateItem('<?php echo $item['family_code']; ?>', 
                              <?php echo $item['quantity']; ?>, 
                              <?php echo $item['allocated_quantity']; ?>)">
    ALLOCATE
</button>
```

**Parameters passed:**
- `family_code` - e.g., "FC-001"
- `quantity` - e.g., 50 (total ordered)
- `allocated_quantity` - e.g., 20 (already allocated)

### 2. Modal Populates

```javascript
allocateItem('FC-001', 50, 20) 
↓
Modal shows:
- Family Code: FC-001
- Total Quantity: 50
- Already Allocated: 20
- Quantity Left: 30 (50 - 20)
```

### 3. User Interaction

**User selects branch and enters quantity:**
```
Branch: Main Branch
Quantity: 15

Quantity Left updates in real-time: 30 - 15 = 15 ✓
```

**User enters too much:**
```
Branch: Main Branch  
Quantity: 35

Quantity Left: 30 - 35 = -5 ❌ (RED)
Alert: "Allocated quantity exceeds available quantity!"
```

## Validation Rules

### Before Submission

1. ✅ **Branch Selected:** Must choose a branch
   ```
   if (!branch) {
       alert('Please select a branch!');
   }
   ```

2. ✅ **Quantity > 0:** Must enter positive quantity
   ```
   if (qty <= 0) {
       alert('Please enter a quantity greater than 0!');
   }
   ```

3. ✅ **Not Over-Allocated:** Cannot exceed available quantity
   ```
   if (quantityLeft < 0) {
       alert('Allocated quantity exceeds available quantity!');
   }
   ```

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│  PURCHASE ORDER DETAILS PAGE                                 │
│  (purchaseorder-details.php)                                 │
└─────────────────────────────────────────────────────────────┘
                          │
                          │ User clicks ALLOCATE
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  DATABASE QUERY                                              │
│  • Fetch item quantity from purchase_order_items            │
│  • Calculate allocated_quantity from existing allocations   │
└─────────────────────────────────────────────────────────────┘
                          │
                          │ Data passed to button
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  ALLOCATE BUTTON                                             │
│  onclick="allocateItem('FC-001', 50, 20)"                    │
└─────────────────────────────────────────────────────────────┘
                          │
                          │ JavaScript function called
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  MODAL DISPLAY                                               │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ Item Information                                      │  │
│  ├───────────┬────────┬───────────┬────────────────────┤  │
│  │ FC-001    │  50    │    20     │       30           │  │
│  └───────────┴────────┴───────────┴────────────────────┘  │
│                                                             │
│  Branch: [Select Branch ▼]                                 │
│  Quantity: [0        ]                                      │
└─────────────────────────────────────────────────────────────┘
                          │
                          │ User enters quantity
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  REAL-TIME CALCULATION                                       │
│  Quantity Left = Total - Already Allocated - Branch Input   │
│                = 50 - 20 - 15 = 15                          │
└─────────────────────────────────────────────────────────────┘
                          │
                          │ User clicks SET
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  VALIDATION & SAVE                                           │
│  • Check all validation rules                               │
│  • If valid: Save to purchase_order_allocations            │
│  • Update allocated_quantity in purchase_order_items        │
│  • Refresh page to show updated data                        │
└─────────────────────────────────────────────────────────────┘
```

## Testing Scenarios

### Scenario 1: Fresh Item (No Allocations)
```
Given: Item FC-001, Quantity: 100, Allocated: 0
When:  User clicks ALLOCATE
Then:  Modal shows:
       - Total Quantity: 100
       - Already Allocated: 0
       - Quantity Left: 100
```

### Scenario 2: Partially Allocated Item
```
Given: Item FC-002, Quantity: 50, Allocated: 30
When:  User clicks ALLOCATE
Then:  Modal shows:
       - Total Quantity: 50
       - Already Allocated: 30
       - Quantity Left: 20
```

### Scenario 3: Fully Allocated Item
```
Given: Item FC-003, Quantity: 25, Allocated: 25
When:  User clicks ALLOCATE
Then:  Modal shows:
       - Total Quantity: 25
       - Already Allocated: 25
       - Quantity Left: 0 (can't allocate more)
```

### Scenario 4: Real-Time Update
```
Given: Item with Quantity Left: 20
When:  User types "15" in quantity field
Then:  Quantity Left updates to: 5 (20 - 15)
When:  User changes to "25"
Then:  Quantity Left: -5 (RED color)
       Alert on save: "Allocated quantity exceeds available quantity!"
```

## Code Changes Summary

### Files Modified
- ✅ `purchaseorder-details.php`

### Functions Updated

1. **allocateItem(familyCode, quantity, allocatedQuantity)**
   - Added `quantity` parameter
   - Added `allocatedQuantity` parameter
   - Calculates and displays accurate values
   - Stores data attributes for calculations

2. **updateQuantityLeft()**
   - Now reads `data-allocated-qty` attribute
   - Calculates: Total - Already Allocated - Current Input
   - Shows accurate remaining quantity

3. **Modal HTML**
   - Added "Already Allocated" column
   - Added `data-` attributes for storing values
   - Added "Total Quantity" label for clarity

## Benefits

✅ **Accurate Data:** Shows real database values  
✅ **Real-Time Feedback:** Updates as user types  
✅ **Visual Warnings:** Red color for over-allocation  
✅ **Prevents Errors:** Validation before save  
✅ **Better UX:** Clear information display  
✅ **No Over-Allocation:** System enforces limits  

## Usage Example

```
User Story:
"As a warehouse manager, I need to allocate 15 units of FC-001 
to Branch A, knowing I have 30 units available."

Steps:
1. View PO details page
2. Find item FC-001 (shows 30/50 allocated)
3. Click ALLOCATE
4. Modal shows:
   - Total: 50
   - Already Allocated: 20
   - Quantity Left: 30
5. Select Branch A
6. Enter 15 units
7. Quantity Left shows 15 (30 - 15)
8. Click SET
9. Allocation saved
10. Table now shows 35/50 allocated
```

## Next Steps (Future Enhancements)

The modal UI is ready. To complete the allocation system:

1. **Backend Implementation** (not yet implemented)
   - Create `save_allocation.php` endpoint
   - Insert into `purchase_order_allocations` table
   - Update `allocated_quantity` in `purchase_order_items`

2. **Load Existing Allocations**
   - Show what's already allocated to each branch
   - Allow editing existing allocations

3. **Allocation History**
   - Track who allocated when
   - Audit trail for changes

## Testing Checklist

- [x] Modal opens with correct family code
- [x] Total Quantity shows database value
- [x] Already Allocated shows database value
- [x] Quantity Left calculates correctly
- [x] Real-time update works when typing
- [x] Over-allocation shows in red
- [x] Validation prevents invalid entries
- [ ] Save functionality (needs backend implementation)

---

**Status:** ✅ UI Complete and Accurate  
**Next:** Backend save functionality
