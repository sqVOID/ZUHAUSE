# Page Type Feature - Booklet Number Registration

## Overview
The booklet number system now supports **multiple invoice number formats per branch** based on the page type. Each branch can have different booklet configurations for Sales Entry, Pre-Order, Stock Transfer, Purchase Order, and more.

---

## What Changed

### New Field: Page Type
Each booklet number configuration now includes a **Page Type** field that specifies which page or module will use that particular invoice format.

### Supported Page Types

1. **Sales Entry** (`salesentry`) - For regular sales transactions
2. **Pre-Order** (`preorder`) - For pre-order transactions
3. **Stock Transfer** (`stocktransfer`) - For stock transfer operations
4. **Purchase Order** (`purchaseorder`) - For purchase orders
5. **Refund** (`refund`) - For refund transactions
6. **Upgrade Unit** (`upgradeunit`) - For unit upgrade transactions

---

## Benefits

✅ **Multiple formats per branch** - Different numbering for different transaction types  
✅ **Better organization** - Separate sequences for sales, transfers, orders, etc.  
✅ **Flexibility** - Each module can have its own format requirements  
✅ **Clarity** - Invoice numbers clearly indicate transaction type  
✅ **Scalability** - Easy to add new page types as system grows  

---

## Database Changes

### Updated Schema

```sql
CREATE TABLE `booklet_numbers` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `branch_code` varchar(50) NOT NULL,
  `page_type` varchar(50) NOT NULL,  -- NEW FIELD
  `booklet_format` varchar(50) NOT NULL,
  `current_number` varchar(100) NOT NULL,
  `prefix` varchar(50) DEFAULT NULL,
  `suffix` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp ON UPDATE CURRENT_TIMESTAMP,
  KEY `branch_code` (`branch_code`),
  KEY `page_type` (`page_type`),      -- NEW INDEX
  KEY `status` (`status`)
);
```

### Migration for Existing Installations

If you already have the `booklet_numbers` table, run the migration script:

```
http://localhost/MOTOGAM/add_page_type_column.php
```

This will:
- Add the `page_type` column
- Set existing records to 'salesentry' by default
- Check for duplicate entries
- Show warnings if manual review needed

---

## Usage Examples

### Example 1: Sales Entry
**Configuration:**
- Branch: San Diego (SD)
- Page Type: **Sales Entry**
- Format: Numeric
- Current Number: 0003128-092-149

**Result:** Invoice numbers for sales will be `0003128-092-149`, `0003128-092-150`, etc.

### Example 2: Pre-Order (Same Branch)
**Configuration:**
- Branch: San Diego (SD)
- Page Type: **Pre-Order**
- Format: Date + Suffix
- Current Number: 07-06-2026
- Prefix: PO-
- Suffix: SD

**Result:** Pre-order numbers will be `PO-07-06-2026-SD`

### Example 3: Stock Transfer (Same Branch)
**Configuration:**
- Branch: San Diego (SD)
- Page Type: **Stock Transfer**
- Format: Custom
- Prefix: ST-
- Current Number: 2026-00001

**Result:** Stock transfer numbers will be `ST-2026-00001`, `ST-2026-00002`, etc.

---

## Setup Instructions

### For New Installations

1. Run `setup_booklet_numbers.php` - Creates table with page_type column
2. Go to Booklet Number Registration
3. Add booklet configuration for each page type you need
4. Select the appropriate page type when adding

### For Existing Installations

1. Run `add_page_type_column.php` - Adds page_type to existing table
2. Review existing booklet numbers
3. Edit each to assign correct page type
4. Add additional booklet configs for other page types

---

## Configuration Guide

### Step 1: Access Booklet Registration
Navigate to: **Sidebar → Store Registration → Booklet Number Registration**

### Step 2: Add Booklet for Each Page Type
Click **"Add Booklet Number"**

### Step 3: Fill Form
- **Branch**: Select your branch
- **Page Type**: Select the page/module this booklet is for ⭐ NEW
- **Booklet Format**: Choose format type
- **Current Number**: Enter starting number
- **Prefix/Suffix**: Optional
- **Description**: Notes about this booklet

### Step 4: Preview and Save
The preview will show: `Branch: [Name] | Page: [Type] | Invoice Number: [Format]`

---

## Integration

### Backend (PHP)

```php
include_once 'get_next_invoice_number.php';

// Get booklet for specific page type
$booklet = getBookletConfig($conn, $branch_code, 'salesentry');

if ($booklet) {
    $invoice_number = generateInvoiceNumber($booklet);
    
    // Auto-increment for numeric formats
    if ($booklet['booklet_format'] === 'numeric') {
        $next = incrementInvoiceNumber($booklet['current_number'], 'numeric');
        updateInvoiceNumber($conn, $booklet['id'], $next);
    }
}
```

### Frontend (JavaScript)

```javascript
fetch('get_next_invoice_number.php', {
    method: 'POST',
    body: 'action=get_invoice_number&branch_code=SD&page_type=preorder'
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        document.getElementById('invoice_field').value = data.invoice_number;
    }
});
```

---

## Multiple Booklets Per Branch

### Example Configuration

**San Diego Branch (SD):**

| Page Type | Format | Current Number | Full Format |
|-----------|--------|----------------|-------------|
| Sales Entry | Numeric | 0003128-092-149 | `0003128-092-149` |
| Pre-Order | Date+Suffix | 07-06-2026 | `PO-07-06-2026-SD` |
| Stock Transfer | Custom | ST-2026-00001 | `ST-2026-00001` |
| Purchase Order | Custom | PO2026-00123 | `PO2026-00123` |
| Refund | Numeric | RF-00001 | `RF-00001` |

Each has its own independent sequence and format!

---

## Table Display

The booklet registration table now shows:

| Branch Name | Branch Code | **Page Type** | Format Type | Prefix | Current Number | Suffix | Full Format | Status |
|-------------|-------------|---------------|-------------|---------|----------------|---------|-------------|---------|
| San Diego | SD | **Sales Entry** | Numeric | - | 0003128-092-149 | - | 0003128-092-149 | Active |
| San Diego | SD | **Pre-Order** | Date Suffix | PO- | 07-06-2026 | SD | PO-07-06-2026-SD | Active |

---

## API Changes

### getBookletConfig()
```php
// Old (deprecated)
getBookletConfig($conn, $branch_code);

// New (with page_type)
getBookletConfig($conn, $branch_code, 'salesentry');
getBookletConfig($conn, $branch_code, 'preorder');
getBookletConfig($conn, $branch_code, 'stocktransfer');
```

### AJAX Endpoint
```javascript
// Request
fetch('get_next_invoice_number.php', {
    method: 'POST',
    body: 'action=get_invoice_number&branch_code=SD&page_type=preorder'
});

// Response
{
    "success": true,
    "invoice_number": "PO-07-06-2026-SD",
    "booklet_id": 2,
    "format": "date_suffix",
    "page_type": "preorder"  // NEW FIELD
}
```

---

## Files Modified

1. **create_booklet_numbers_table.sql** - Added page_type column
2. **setup_booklet_numbers.php** - Updated table creation
3. **bookletnoreg.php** - Added page_type field in form and table
4. **get_next_invoice_number.php** - Updated getBookletConfig() to accept page_type
5. **salesentry.php** - Passes page_type='salesentry' when fetching invoice
6. **save_sales_entry.php** - Uses page_type when generating invoice

### New Files

1. **add_page_type_column.php** - Migration script for existing installations

---

## Troubleshooting

### Issue: Migration script shows duplicates
**Cause:** Multiple active booklets for same branch without page_type distinction  
**Solution:** Edit booklets to assign different page types or deactivate extras

### Issue: Can't add booklet - duplicate error
**Cause:** Trying to add another booklet for same branch+page_type  
**Solution:** Only one active booklet per branch per page_type. Edit existing or deactivate it first.

### Issue: Wrong invoice format being used
**Cause:** Incorrect page_type passed to getBookletConfig()  
**Solution:** Verify the page_type parameter matches your booklet configuration

### Issue: Existing booklets not showing page type
**Cause:** Migration not run  
**Solution:** Run `add_page_type_column.php`

---

## Best Practices

1. **One active booklet per branch per page type** - Prevents conflicts
2. **Descriptive prefixes** - Use prefixes to clearly identify transaction type (PO-, ST-, RF-)
3. **Consistent formats** - Use similar formats across branches for easier management
4. **Regular audits** - Check that each module has its booklet configured
5. **Test before production** - Complete test transactions for each page type
6. **Document formats** - Keep notes on why specific formats were chosen

---

## Migration Checklist

For existing installations upgrading to page_type feature:

- [ ] Backup database
- [ ] Run `add_page_type_column.php`
- [ ] Review migration output for warnings
- [ ] Edit existing booklets to assign correct page_type
- [ ] Add new booklet configs for additional page types
- [ ] Test invoice generation for each page type
- [ ] Update any custom integrations to pass page_type
- [ ] Verify fallback behavior works correctly

---

## Future Enhancements

Potential additions:
- Auto-detect page_type from calling script
- Booklet templates for common configurations
- Bulk import/export of booklet configs
- History tracking for booklet number changes
- Notifications when booklet needs manual update
- Integration with more modules (warranty, service, etc.)

---

## Summary

The page_type feature allows each branch to maintain **separate invoice number sequences and formats** for different transaction types. This provides better organization, flexibility, and clarity in your invoice numbering system.

**Key Takeaway:** One branch can now have multiple booklet configurations - one for each type of transaction it handles!

---

**Last Updated:** 2026-07-06  
**Version:** 2.0  
**Feature:** Page Type Support  
**Status:** ✅ Complete and Ready
