# Purchase Order System - Quick User Guide

## Setup Instructions

### 1. Run Database Migrations (One-time setup)

Before using the PO details page for the first time, run these migration files:

```bash
# Open your command prompt in the ZUHAUSE directory and run:
php add_allocated_quantity_column.php
php create_purchase_order_allocations_table.php
```

**What these do:**
- Adds columns to track allocated and received quantities
- Creates a new table to track branch allocations

## How to Use the System

### Creating a Purchase Order

1. **Navigate to Create PO Page**
   - Go to `createpurchaseorder.php`
   - Or click "Create Purchase Order" from the PO listing page

2. **Fill in PO Information**
   - Supplier Company Name (searchable dropdown)
   - Terms (30/60/90 Days or Cash on Delivery)
   - Payment Due Date (auto-calculates based on terms)
   - Remarks (optional)

3. **Add Items**
   - Click "+ Add Item" button
   - Enter Family Code (or press Enter to search)
   - Enter Quantity
   - Enter Cost per unit
   - The system will calculate line totals automatically

4. **Review Summary**
   - Check the summary card on the right
   - Shows total items, quantities, and cost

5. **Submit**
   - Click "Create Purchase Order"
   - System generates PO number automatically (format: PO-YYYY-NNN)
   - Redirects to PO listing page

### Viewing PO Details

1. **Access Details Page**
   - From PO listing, click on any PO row
   - Or navigate to `purchaseorder-details.php?id={po_id}`

2. **View PO Information**
   - **Table 1: PO Header** - Shows PO number, supplier, date, terms, total cost, status
   - **Table 2: Items** - Shows all items ordered with quantities and costs
   - **Table 3: Branch Allocations** - Shows which branches are allocated items (if any)

3. **Available Actions**
   - **VIEW button** - View receiving details (enabled when items are received)
   - **ALLOCATE button** - Allocate items to branches
   - **EDIT/REMOVE** - Manage branch allocations

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│  CREATE PURCHASE ORDER                                       │
│  (createpurchaseorder.php)                                   │
└────────────┬────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────┐
│  SAVE TO DATABASE                                            │
│  (save_purchase_order_simple.php)                            │
│                                                              │
│  • Generates PO Number                                       │
│  • Saves to purchase_orders table                            │
│  • Saves items to purchase_order_items table                 │
└────────────┬────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────┐
│  VIEW PO DETAILS                                             │
│  (purchaseorder-details.php?id=XX)                           │
│                                                              │
│  • Fetches PO data from purchase_orders                      │
│  • Fetches items from purchase_order_items                   │
│  • Fetches allocations from purchase_order_allocations       │
│  • Displays in organized tables                              │
└─────────────────────────────────────────────────────────────┘
```

## Key Features

### ✅ Completed Features

1. **PO Creation**
   - Auto-generated PO numbers
   - Supplier lookup with search
   - Multiple items per PO
   - Family code validation
   - Real-time cost calculations

2. **PO Details Display**
   - Real database data (no more sample data)
   - Organized 3-table layout
   - Status tracking
   - Empty state handling

3. **Data Integrity**
   - Family code validation
   - Duplicate item prevention
   - Required field validation
   - Proper database relationships

### 🔄 Ready for Implementation

1. **Branch Allocation**
   - UI is ready (modal exists)
   - Backend needs to be implemented
   - Will update `allocated_quantity` column

2. **Receiving Process**
   - Database columns ready (`received_qty`)
   - Status calculation logic in place
   - UI needs to be built

3. **Approval Workflow**
   - Can be added to track approvals
   - Status transitions

## Database Schema

### Main Tables

```sql
purchase_orders
├── id (PK)
├── po_number (UNIQUE)
├── supplier_company
├── terms
├── payment_due_date
├── remarks
├── total_cost
├── status
└── created_by

purchase_order_items
├── id (PK)
├── po_id (FK → purchase_orders)
├── family_code
├── quantity
├── cost
├── allocated_quantity ← NEW
└── received_qty ← NEW

purchase_order_allocations ← NEW TABLE
├── id (PK)
├── po_id (FK → purchase_orders)
├── branch_name
├── family_code
├── quantity
├── received_qty
└── status
```

## Troubleshooting

### Issue: "No items found for this purchase order"
**Solution:** Check that items were saved correctly during PO creation. Verify `purchase_order_items` table has records for this PO ID.

### Issue: PO Details page redirects to listing
**Solution:** The PO ID in the URL might be invalid. Check that the PO exists in the database.

### Issue: Allocated quantity shows "0/10" for all items
**Solution:** This is normal for new POs. Allocations need to be created using the ALLOCATE button (feature pending implementation).

### Issue: Terms shows "Days" for COD
**Solution:** Make sure the terms field in database is lowercase 'cod', not 'COD'.

### Issue: Migration files don't run
**Solution:** 
- Ensure you're in the correct directory
- Check PHP is in your system PATH
- Verify database connection in config.php

## URL Parameters

### purchaseorder-details.php

| Parameter | Required | Description | Example |
|-----------|----------|-------------|---------|
| `id` | Yes | Purchase Order ID | `?id=123` |
| `from` | No | Return page reference | `?id=123&from=purchaseorder` |

**Examples:**
```
purchaseorder-details.php?id=1
purchaseorder-details.php?id=5&from=purchaseorder
```

## Status Values

| Status | Description | Color |
|--------|-------------|-------|
| Pending | PO created, not yet processed | Yellow |
| Waiting | PO approved, waiting for allocation/receiving | Blue |
| Incomplete | Some items received, not complete | Yellow |
| Complete | All items received | Green |
| Allocated | Items allocated to branches | Green |

## Tips & Best Practices

1. **Always validate family codes** - The system checks if family codes exist in the database
2. **Use search feature** - Press Enter in family code field to search
3. **Review before submitting** - Check the summary card before creating PO
4. **Run migrations first** - Ensure database schema is up-to-date
5. **Check empty states** - Empty states guide users on next actions

## Support

If you encounter issues:
1. Check the browser console for JavaScript errors
2. Check database connection in config.php
3. Verify all required tables exist
4. Run migration files if needed
5. Check file permissions

## Future Enhancements

Planned features:
- [ ] Complete allocation functionality
- [ ] Receiving workflow
- [ ] Approval workflow
- [ ] Email notifications
- [ ] PDF export
- [ ] Advanced reporting
- [ ] Supplier management
- [ ] Inventory integration
