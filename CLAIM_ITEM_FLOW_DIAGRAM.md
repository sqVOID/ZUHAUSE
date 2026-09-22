# Claim Item Flow Diagram

## Visual Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                     1. SEARCH INVOICE                           │
│  User enters invoice number → Click "Search"                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                  2. VIEW UNCLAIMED ITEMS                        │
│  ┌───────────────────────────────────────────────────────┐     │
│  │ ☐ MONARCH-FULL-FACE-HELMET                            │     │
│  │   Item Code: MONARCH-FULL-FACE-HELMET                 │     │
│  │   Quantity: 1                                          │     │
│  └───────────────────────────────────────────────────────┘     │
│  ┌───────────────────────────────────────────────────────┐     │
│  │ ☐ RIDING-GLOVES-XL                                    │     │
│  │   Item Code: RIDING-GLOVES-XL                         │     │
│  │   Quantity: 2                                          │     │
│  └───────────────────────────────────────────────────────┘     │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│              3. USER SELECTS (CHECKS) ITEMS                     │
│  ┌───────────────────────────────────────────────────────┐     │
│  │ ☑ MONARCH-FULL-FACE-HELMET       ← SELECTED          │     │
│  │   Item Code: MONARCH-FULL-FACE-HELMET                 │     │
│  │   Quantity: 1                                          │     │
│  └───────────────────────────────────────────────────────┘     │
│                                                                  │
│  selectedUnclaimedItems = [                                     │
│    { item_code: "MONARCH-FULL-FACE-HELMET", quantity: 1 }      │
│  ]                                                               │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│              4. USER TRIES TO ADD ITEM TO TABLE                 │
│  Item Code: MONARCH-FULL-FACE-HELMET                            │
│  Quantity: 1                                                     │
│  Click "Add" →                                                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    VALIDATION LAYER 1                           │
│  ❓ Is "MONARCH-FULL-FACE-HELMET" in selectedUnclaimedItems?   │
│     YES ✅ → Continue to next validation                        │
│     NO  ❌ → Show error: "Not selected from unclaimed items"    │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    VALIDATION LAYER 2                           │
│  ❓ Does quantity exceed unclaimed quantity?                    │
│     Current requested: 1                                         │
│     Already in table: 0                                          │
│     Total requested: 1                                           │
│     Unclaimed quantity: 1                                        │
│                                                                  │
│     Total (1) <= Unclaimed (1) ✅ → Continue                    │
│     Total > Unclaimed ❌ → Show error: "Exceeds quantity"       │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    VALIDATION LAYER 3                           │
│  ❓ Stock availability check (existing)                         │
│     YES ✅ → Add to table                                       │
│     NO  ❌ → Show error: "Insufficient stock"                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    5. ITEM ADDED TO TABLE                       │
│  ┌──────────────────────────────────────────────────────┐      │
│  │ Item Description │ IMEI │ Quantity │ Action          │      │
│  ├──────────────────────────────────────────────────────┤      │
│  │ MONARCH-FULL...  │      │    1     │   [X]           │      │
│  └──────────────────────────────────────────────────────┘      │
│                                                                  │
│  claimedItemsTable = [                                          │
│    { itemCode: "MONARCH-FULL-FACE-HELMET", quantity: 1 }       │
│  ]                                                               │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│              6. REAL-TIME FEEDBACK UPDATED                      │
│  ┌───────────────────────────────────────────────────────┐     │
│  │ ☑ MONARCH-FULL-FACE-HELMET                            │     │
│  │   Item Code: MONARCH-FULL-FACE-HELMET                 │     │
│  │   Quantity: 1                                          │     │
│  │   Claimed: 1 | Remaining: 0  ← NEW DISPLAY           │     │
│  └───────────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────────┘
```

## Error Scenarios

### Scenario A: Item Not Selected
```
User Action:
  ☐ MONARCH-FULL-FACE-HELMET (NOT CHECKED)
  Try to add MONARCH-FULL-FACE-HELMET

Result:
  ❌ Alert: "Cannot add 'MONARCH-FULL-FACE-HELMET'. 
             This item is not selected from the unclaimed items list. 
             Please select it first from the unclaimed items panel."
```

### Scenario B: Quantity Exceeded
```
User Action:
  ☑ HELMET (Unclaimed: 1)
  Add HELMET quantity 1 → ✅ Success (in table now)
  Try to add HELMET quantity 1 again

Result:
  ❌ Alert: "Cannot add 1 of 'HELMET'. 
             Total quantity (2) would exceed the unclaimed quantity (1).
             
             Already in table: 1
             Unclaimed quantity available: 1"
```

### Scenario C: Correct Flow
```
User Action:
  ☑ HELMET (Unclaimed: 3)
  Add HELMET quantity 1 → ✅ Success (Claimed: 1 | Remaining: 2)
  Add HELMET quantity 1 → ✅ Success (Claimed: 2 | Remaining: 1)
  Add HELMET quantity 1 → ✅ Success (Claimed: 3 | Remaining: 0)
  Try to add HELMET quantity 1 again → ❌ Error (exceeds limit)
```

## Data Structure Relationships

```
┌─────────────────────────────────────────────────────────────────┐
│              currentUnclaimedItems (from API)                   │
│  All unclaimed items for the invoice                            │
│  [                                                               │
│    { id: 1, item_code: "HELMET", quantity: 1 },                │
│    { id: 2, item_code: "GLOVES", quantity: 2 }                 │
│  ]                                                               │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ User checks checkboxes
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│            selectedUnclaimedItems (user selection)              │
│  Only items user has checked - ALLOWED TO CLAIM                 │
│  [                                                               │
│    { id: 1, item_code: "HELMET", quantity: 1 }  ← Only HELMET │
│  ]                                                               │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ User adds items (with validation)
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│             claimedItemsTable (items in table)                  │
│  Items actually added to the claim table                        │
│  [                                                               │
│    { itemCode: "HELMET", quantity: 1, description: "..." }     │
│  ]                                                               │
└─────────────────────────────────────────────────────────────────┘

Validation Rule:
  claimedItemsTable[item_code].sum(quantity) 
    <= 
  selectedUnclaimedItems[item_code].quantity
```

## Key Validation Points

```
┌──────────────────────────────────────────────────────────────────┐
│  Validation Point 1: selectSearchItem()                          │
│  Triggered: When user selects item from search modal             │
│  Checks: Is item in selectedUnclaimedItems?                      │
│  Action: Allow/Block item code population                        │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│  Validation Point 2: addItemToTable() - Item Check               │
│  Triggered: When user clicks "Add" button                        │
│  Checks: Is item in selectedUnclaimedItems?                      │
│  Action: Allow/Block adding to table                             │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│  Validation Point 3: addItemToTable() - Quantity Check           │
│  Triggered: When user clicks "Add" button                        │
│  Checks: Does total quantity exceed unclaimed quantity?          │
│  Action: Allow/Block adding to table                             │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│  Validation Point 4: Stock Availability (existing)               │
│  Triggered: After passing above validations                      │
│  Checks: Is there sufficient stock?                              │
│  Action: Allow/Block adding to table                             │
└──────────────────────────────────────────────────────────────────┘
```

## State Management

```
Event: Search New Invoice
  ↓
  Clear selectedUnclaimedItems = []
  Clear claimedItemsTable = []
  Render empty table

Event: Check Unclaimed Item Checkbox
  ↓
  Add to selectedUnclaimedItems[]
  Update visual feedback (green border)

Event: Uncheck Unclaimed Item Checkbox
  ↓
  Remove from selectedUnclaimedItems[]
  Update visual feedback (normal border)

Event: Add Item to Table
  ↓
  Validate → Add to claimedItemsTable[]
  Render table
  Update remaining quantities

Event: Delete Item from Table
  ↓
  Remove from claimedItemsTable[]
  Render table
  Update remaining quantities (show more available)
```

## Summary

This implementation creates a **gated system** where:

1. **Gate 1**: User must SELECT items from unclaimed list (checkbox)
2. **Gate 2**: User can only ADD items that passed Gate 1
3. **Gate 3**: Quantity cannot exceed what was available in unclaimed list
4. **Gate 4**: Stock must be available (existing check)

**Result**: Complete control and validation of claimed items matching unclaimed freebies exactly!
