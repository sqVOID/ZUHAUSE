# Unclaimed Freebies - Quick Start Guide

## Setup (One-Time Only)

### Step 1: Create Database Table
1. Open your browser and navigate to: `http://localhost/MOTOGAM/setup_unclaimed_freebies_table.php`
2. You should see: "Table 'unclaimed_freebies' created successfully or already exists."
3. Done! The table is now ready.

---

## How to Use Unclaimed Freebies

### Step 1: Access Sales Entry
1. Log in to MOTOGAM
2. Navigate to Sales Entry page

### Step 2: Add Regular Sale Items
1. Search and add your regular sales items as usual
2. Fill in customer information
3. Process payment

### Step 3: Add Unclaimed Freebies
1. Scroll down to the **Unclaimed Freebies** section
2. Click the **"Add Unclaimed Freebies"** button
3. A new row will appear with:
   - Item Name field with Search button
   - Quantity field (default: 0)
   - Note field (optional)
   - Delete (X) button

### Step 4: Search for Items
1. Click the **"Search"** button next to the Item Name field
2. A modal will open titled: "Search Unclaimed Freebies (Non-Serialized Items Only)"
3. Type the item name or code (e.g., "charger", "case", "earphones")
4. Press **Enter** or click **Search**
5. Results will show only items that:
   - Are non-serialized (accessories)
   - Have ZERO stock at your branch
   - Are available for your branch

### Step 5: Select Item
1. Click on an item from the search results
2. The item will populate the row
3. Enter the **Quantity** (how many the customer will receive later)
4. Optionally add a **Note** (e.g., "Red color", "Waiting for delivery")

### Step 6: Add More Items
1. Click **"Add Unclaimed Freebies"** again to add another row
2. Repeat the search and selection process
3. You can add multiple different items

### Step 7: Remove Items
1. To remove an unclaimed freebie, click the **X** button on that row
2. The row will be deleted

### Step 8: Save
1. Complete all required fields in the sales entry
2. Click **"SAVE"** button
3. Your sales entry will be saved along with all unclaimed freebies

---

## What Happens After Saving?

1. The main sale is recorded in `sales_entry` table
2. Each unclaimed freebie is saved to `unclaimed_freebies` table with:
   - Invoice number
   - Item code and description
   - Quantity
   - Your notes
   - Your branch
   - Your name (as created_by)
   - Status: "unclaimed"

3. When the items arrive in stock, they can be marked as "claimed" (future feature)

---

## Important Rules

### ✅ ALLOWED
- Non-serialized items (accessories like chargers, cases, earphones)
- Items with ZERO stock at your branch
- Multiple unclaimed freebies per sale
- Optional notes for each item

### ❌ NOT ALLOWED
- Serialized items (phones, tablets with IMEI)
- Items that are currently in stock
- Items from other branches (unless shared)

---

## Example Scenario

**Customer buys a phone today but wants a free case:**

1. Process the phone sale normally
2. Search for "phone case" in Unclaimed Freebies
3. If the case has 0 stock, it will appear in search
4. Select it, set quantity to 1
5. Add note: "Blue color preferred"
6. Save the sales entry

**Result**: The sale is recorded, and the system tracks that the customer should receive 1 phone case when it arrives in stock.

---

## Troubleshooting

### "No items found when searching"
**Reason**: All items either:
- Have stock > 0 at your branch, OR
- Are serialized items (not allowed)

**Solution**: Check your inventory. Only items with exactly 0 stock will appear.

### "Can't see the Unclaimed Freebies section"
**Reason**: You might need to scroll down on the Sales Entry page

**Solution**: Scroll down below the main items table and payment section

### "Search button doesn't open modal"
**Reason**: JavaScript might not be loaded

**Solution**: Refresh the page (F5)

### "Save fails with error"
**Reason**: Database table might not exist

**Solution**: Run `setup_unclaimed_freebies_table.php` again

---

## For Administrators

### View Unclaimed Freebies Data
```sql
-- See all unclaimed freebies
SELECT * FROM unclaimed_freebies WHERE status = 'unclaimed';

-- See unclaimed freebies for a specific invoice
SELECT * FROM unclaimed_freebies WHERE invoice_number = 'YOUR_INVOICE_NO';

-- See unclaimed freebies by branch
SELECT * FROM unclaimed_freebies WHERE branch = 'YOUR_BRANCH' AND status = 'unclaimed';

-- See unclaimed freebies by item
SELECT * FROM unclaimed_freebies WHERE item_code = 'ITEM_CODE' AND status = 'unclaimed';
```

### Table Structure
```sql
DESCRIBE unclaimed_freebies;
```

### Check if Table Exists
```sql
SHOW TABLES LIKE 'unclaimed_freebies';
```

---

## Mobile Usage

The Unclaimed Freebies feature is fully responsive:

- **On Tablet**: Buttons and inputs are larger and touch-friendly
- **On Phone**: Layout stacks vertically for easier viewing
- **Scrolling**: Tables scroll horizontally if needed

Works on all devices with touch support!

---

## Need Help?

1. Check the full documentation: `UNCLAIMED_FREEBIES_IMPLEMENTATION.md`
2. Contact your system administrator
3. Check the browser console for error messages (F12 → Console tab)

---

## Summary

✅ Setup the database table (one time only)  
✅ Add regular sale items  
✅ Click "Add Unclaimed Freebies"  
✅ Search for items (only 0-stock accessories appear)  
✅ Enter quantity and optional notes  
✅ Save the sales entry  

**That's it!** The system now tracks what customers should receive when stock arrives.
