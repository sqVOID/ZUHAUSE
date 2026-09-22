# 🚀 Purchase Order System - Quick Start Guide

## ✅ What's Working Now

- **Create Purchase Orders** (`createpurchaseorder.php`)
- **View Purchase Order Details** (`purchaseorder-details.php`)
- **Database Integration** (Real data, no more sample data!)
- **Collation Error** (FIXED!)

## 📋 Setup Checklist

### 1. Database Migrations (Optional but Recommended)

Run these commands in your terminal from the ZUHAUSE directory:

```bash
# Add tracking columns to purchase_order_items
php add_allocated_quantity_column.php

# Create branch allocations table
php create_purchase_order_allocations_table.php

# Fix collation issues (prevents future errors)
php fix_collation_issues.php
```

### 2. Test the System

**Create a Test Purchase Order:**
1. Go to `createpurchaseorder.php`
2. Select a supplier
3. Choose payment terms (30/60/90 Days or COD)
4. Add 2-3 items with family codes
5. Click "Create Purchase Order"

**View the Purchase Order:**
1. Go to `purchaseorder.php` (listing page)
2. Click on the PO you just created
3. Verify all data displays correctly

## 📊 What You'll See

### Create PO Page
- Supplier selection (searchable dropdown)
- Terms and payment date
- Items table (add multiple items)
- Real-time summary (items, quantity, total cost)

### PO Details Page
- **Table 1:** PO header info (number, supplier, date, terms, cost, status)
- **Table 2:** All items in the PO
- **Table 3:** Branch allocations (empty initially, ready for future use)

## 🔧 Common Tasks

### Create a New PO
```
Navigate to: createpurchaseorder.php
Fill form → Add items → Submit
Result: Auto-generated PO number (e.g., PO-2026-001)
```

### View PO Details
```
Navigate to: purchaseorder.php
Click on any PO row
Result: Detailed view of that specific PO
```

### Search Family Codes
```
In item row: Type family code → Press Enter
Modal opens with search results
Select item → Auto-fills family code
```

## 📁 Important Files

### User Pages
- `createpurchaseorder.php` - Create new POs
- `purchaseorder-details.php` - View PO details
- `purchaseorder.php` - List all POs

### Backend
- `save_purchase_order_simple.php` - Saves PO to database
- `search_familycode.php` - Family code search API
- `search_supplier.php` - Supplier search API

### Database Migrations
- `add_allocated_quantity_column.php`
- `create_purchase_order_allocations_table.php`
- `fix_collation_issues.php`

### Documentation
- `PO_DATA_CONNECTION_SUMMARY.md` - Technical overview
- `PO_SYSTEM_QUICK_GUIDE.md` - User guide
- `PO_DATA_FLOW_DIAGRAM.md` - Architecture diagrams
- `PO_TESTING_CHECKLIST.md` - Testing procedures
- `COLLATION_FIX_README.md` - Collation error details
- `COLLATION_ERROR_FIXED.md` - Fix confirmation

## 🗄️ Database Tables

### purchase_orders
Main PO header information
- PO number, supplier, dates, totals, status

### purchase_order_items
Individual line items
- Family code, quantity, cost per unit

### purchase_order_allocations (optional)
Branch-wise item allocation tracking
- Which branch gets which items

## ⚠️ Troubleshooting

### Error: "No items found"
**Solution:** Check that items were saved during PO creation

### Error: "Collation error"
**Solution:** Already fixed! If it happens again, run `fix_collation_issues.php`

### Error: "Family code doesn't exist"
**Solution:** Ensure the family code is in the `family_codes` table

### Page redirects to listing
**Solution:** Invalid PO ID in URL. Use a valid PO ID.

## 🎯 Next Steps

### Ready to Use
✅ Create purchase orders
✅ View purchase order details
✅ Search for suppliers and items
✅ Track PO status

### Coming Soon (UI Ready, Backend Needed)
⏳ Allocate items to branches
⏳ Receive items at branches
⏳ Track receiving status
⏳ Generate reports

## 💡 Tips

1. **Always validate family codes** - System checks if they exist
2. **Press Enter to search** - Quick way to find family codes
3. **Check the summary** - Review before submitting
4. **Run migrations first** - Ensures all columns exist
5. **Use the documentation** - Detailed guides are available

## 🆘 Need Help?

1. Check the error message carefully
2. Look in browser console for JavaScript errors
3. Check database connection in `config.php`
4. Review documentation files
5. Verify migrations have been run

## 📞 Support Files

- Technical details: `PO_DATA_CONNECTION_SUMMARY.md`
- User guide: `PO_SYSTEM_QUICK_GUIDE.md`
- Testing: `PO_TESTING_CHECKLIST.md`
- Architecture: `PO_DATA_FLOW_DIAGRAM.md`

---

## ✨ You're All Set!

The Purchase Order system is ready to use. Start by creating a test PO to familiarize yourself with the workflow.

**Happy ordering! 📦**
