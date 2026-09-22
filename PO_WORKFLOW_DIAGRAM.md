# Purchase Order Workflow Diagram

## New Allocation-Required Workflow

```
┌─────────────────────────────────────────────────────────────────────┐
│                    PURCHASE ORDER LIFECYCLE                          │
└─────────────────────────────────────────────────────────────────────┘

STEP 1: CREATE PURCHASE ORDER
┌──────────────────────────────────┐
│   createpurchaseorder.php        │
│                                  │
│   User fills out:                │
│   - Supplier info                │
│   - Items (Family Code, Qty)     │
│   - Payment terms                │
│                                  │
│   ┌────────────────────┐         │
│   │  Save PO           │         │
│   │  Status: "Pending" │         │
│   └────────────────────┘         │
└──────────────────────────────────┘
           │
           ├─► Saved to: purchase_orders table
           │             (status = 'Pending')
           │
           ├─► Saved to: purchase_order_items table
           │             (all line items)
           │
           ▼

STEP 2: VIEW IN PURCHASE ORDER LIST
┌──────────────────────────────────┐
│   purchaseorder.php              │
│   (Main PO Management)           │
│                                  │
│   ✓ PO appears here              │
│   ✓ Status: Pending              │
│   ✓ Can view details             │
└──────────────────────────────────┘
           │
           │ User clicks "Details"
           ▼

STEP 3: ALLOCATE ITEMS TO BRANCHES
┌──────────────────────────────────┐
│   purchaseorder-details.php      │
│                                  │
│   User allocates items:          │
│   ┌────────────────────────────┐ │
│   │ Item: ABC-123              │ │
│   │ Branch: Main Branch        │ │
│   │ Quantity: 10               │ │
│   └────────────────────────────┘ │
│   ┌────────────────────────────┐ │
│   │ Item: ABC-123              │ │
│   │ Branch: Branch 2           │ │
│   │ Quantity: 5                │ │
│   └────────────────────────────┘ │
│                                  │
│   ┌────────────────────┐         │
│   │  Set Allocation    │         │
│   └────────────────────┘         │
└──────────────────────────────────┘
           │
           ├─► Saved to: purchase_order_allocations table
           │             (branch_name, family_code, quantity)
           │
           ▼

┌─────────────────────────────────────────────────────────────┐
│  ⚠️  CRITICAL CHECKPOINT: ALLOCATION REQUIRED               │
│                                                             │
│  Only POs with records in purchase_order_allocations       │
│  will appear in purchaseorderreceive.php                   │
└─────────────────────────────────────────────────────────────┘
           │
           │ ✓ Allocation exists
           ▼

STEP 4: RECEIVE ITEMS AT BRANCH
┌──────────────────────────────────┐
│   purchaseorderreceive.php       │
│                                  │
│   NOW the PO appears here!       │
│                                  │
│   ✓ Status: Pending              │
│   ✓ Can view and modify          │
│   ✓ Can mark as Received         │
│                                  │
│   User selects PO and enters:    │
│   - Serial numbers (if IMEI)     │
│   - Invoice number               │
│   - Mark as Received/Incomplete  │
│                                  │
│   ┌────────────────────┐         │
│   │  Update Status     │         │
│   │  Status: "Received"│         │
│   └────────────────────┘         │
└──────────────────────────────────┘
           │
           ├─► Updated: purchase_orders.status = 'Received'
           │
           ├─► Updated: purchase_order_items (serial numbers)
           │
           ├─► Inserted: stock_on_hand (inventory)
           │
           ▼

STEP 5: COMPLETE (Optional)
┌──────────────────────────────────┐
│   Status changes:                │
│   Received → Completed           │
│                                  │
│   All items fully received       │
│   and processed                  │
└──────────────────────────────────┘


═══════════════════════════════════════════════════════════════

KEY DATABASE TABLES INVOLVED:

1. purchase_orders
   - Stores PO header information
   - Fields: po_number, status, supplier_company, etc.

2. purchase_order_items
   - Stores PO line items
   - Fields: family_code, quantity, cost, serial_number, etc.

3. purchase_order_allocations ⭐ KEY TABLE
   - Stores which items go to which branches
   - Fields: po_id, branch_name, family_code, quantity, etc.
   - THIS determines if PO appears in purchaseorderreceive.php

4. stock_on_hand
   - Stores received inventory
   - Updated when PO status = 'Received'

═══════════════════════════════════════════════════════════════

SQL QUERY LOGIC IN purchaseorderreceive.php:

OLD QUERY (Before Changes):
```sql
SELECT * FROM purchase_orders
WHERE status = 'Pending'
```
Result: Shows ALL pending POs (even without allocations) ❌

NEW QUERY (After Changes):
```sql
SELECT * FROM purchase_orders po
WHERE status = 'Pending'
  AND EXISTS (
    SELECT 1 FROM purchase_order_allocations poa
    WHERE poa.po_id = po.id
  )
```
Result: Shows ONLY pending POs that have allocations ✓

═══════════════════════════════════════════════════════════════

EXAMPLE SCENARIO:

Day 1 - Create PO
┌─────────────────────┐
│ PO-2026-001         │
│ Status: Pending     │
│ Items:              │
│  - ABC-123 (20 pcs) │
│  - DEF-456 (15 pcs) │
└─────────────────────┘
                      
❌ Does NOT appear in purchaseorderreceive.php
   (No allocations yet)


Day 2 - Allocate Items
┌─────────────────────────────────┐
│ Allocation:                     │
│  Main Branch:   ABC-123 (10)    │
│  Branch 2:      ABC-123 (10)    │
│  Main Branch:   DEF-456 (15)    │
└─────────────────────────────────┘
                      
✓ NOW appears in purchaseorderreceive.php
  (Allocations exist in database)


Day 3 - Receive at Branch
┌─────────────────────────────────┐
│ Main Branch receives:           │
│  - ABC-123 (10 pcs) ✓           │
│  - DEF-456 (15 pcs) ✓           │
│                                 │
│ Status → Received               │
│ Added to stock_on_hand          │
└─────────────────────────────────┘

═══════════════════════════════════════════════════════════════

USER ROLES & ACCESS:

Super-Admin:
  ✓ Can create POs for any branch
  ✓ Can allocate to any branch
  ✓ Can see all POs in receive page
  ✓ Can modify and receive all POs

Sub-Admin (Branch-Specific):
  ✓ Can create POs for their branch
  ✓ Can allocate to their assigned branches
  ✓ Only sees POs for their branch
  ✓ Can receive POs for their branch only

Regular User:
  ✓ Can create POs (if allowed)
  ✓ May have limited allocation rights
  ✓ Can view/receive based on permissions

═══════════════════════════════════════════════════════════════
