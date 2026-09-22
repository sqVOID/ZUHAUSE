# Sales Entry - Booklet Number Integration

## Overview
The sales entry system is now integrated with the booklet number registration system. Invoice numbers will be automatically generated based on the booklet configuration for each branch.

## Changes Made

### 1. **salesentry.php** - Frontend Integration
**Modified:** JavaScript function `initializeForm()`

**Before:**
```javascript
fetch('get_next_invoice_number.php')
    .then(response => response.json())
    .then(result => {
        if (result.status === 'success') {
            document.getElementById('invoice_no').value = result.invoice_no;
        }
    });
```

**After:**
```javascript
fetch('get_next_invoice_number.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=get_invoice_number&branch_code=<?php echo $branch_code; ?>'
})
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            document.getElementById('invoice_no').value = result.invoice_number;
        } else {
            document.getElementById('invoice_no').value = 'System Generated';
        }
    });
```

**What Changed:**
- Now sends branch_code to get the correct booklet configuration
- Uses POST method to pass branch information
- Handles both success and failure cases gracefully

### 2. **save_sales_entry.php** - Backend Integration
**Modified:** Invoice number generation section

**Before:**
```php
// Generate Invoice Number
$year  = date('y');
$month = date('m');
$day   = date('d');
// ... old sequential logic
$invoice_no = sprintf("%s%s%s-%s-%05d", $year, $month, $day, $branch_code, $sequence);
```

**After:**
```php
// Generate Invoice Number using booklet configuration
include_once 'get_next_invoice_number.php';

$booklet = getBookletConfig($conn, $branch_code);

if ($booklet) {
    // Use booklet number configuration
    $invoice_no = generateInvoiceNumber($booklet);
    
    // Auto-increment for numeric formats
    if ($booklet['booklet_format'] === 'numeric') {
        $next_number = incrementInvoiceNumber($booklet['current_number'], 'numeric');
        updateInvoiceNumber($conn, $booklet['id'], $next_number);
    }
} else {
    // Fallback to old system if no booklet configured
    // ... original logic remains as fallback
}
```

**What Changed:**
- Checks for booklet configuration first
- Uses booklet format if available
- Auto-increments numeric formats
- Falls back to old system if no booklet configured
- Ensures backward compatibility

## How It Works

### Flow Diagram
```
User Opens Sales Entry Page
         ↓
JavaScript calls get_next_invoice_number.php with branch_code
         ↓
PHP checks booklet_numbers table for active booklet
         ↓
    ┌─────────────────────┐
    │ Booklet Found?      │
    └─────────────────────┘
         ↓           ↓
        YES         NO
         ↓           ↓
  Generate from   Use fallback
  booklet format  (YYMMDD-CODE-XXXXX)
         ↓           ↓
    Display in Invoice No field
         ↓
User completes sale
         ↓
save_sales_entry.php generates final invoice number
         ↓
    ┌─────────────────────┐
    │ Booklet Found?      │
    └─────────────────────┘
         ↓           ↓
        YES         NO
         ↓           ↓
  Use booklet     Use fallback
  & increment     sequential
         ↓           ↓
    Save to database
```

## Configuration Examples

### Example 1: Numeric Format
**Booklet Configuration:**
- Branch: San Diego (SD)
- Format: Numeric
- Current Number: 0003128-092-149

**Generated Invoice:** `0003128-092-149`
**Next Invoice:** `0003128-092-150` (auto-incremented)

### Example 2: Date + Suffix
**Booklet Configuration:**
- Branch: Batangas (BTG)
- Format: Date + Suffix
- Current Number: 07-06-2026
- Suffix: SD10

**Generated Invoice:** `07-06-2026-SD10`
**Next Invoice:** `07-06-2026-SD10` (manual update required)

### Example 3: With Prefix
**Booklet Configuration:**
- Branch: Manila (MNL)
- Format: Custom
- Prefix: INV-
- Current Number: 2026-001234

**Generated Invoice:** `INV-2026-001234`
**Next Invoice:** Manual update required

### Example 4: No Booklet (Fallback)
**If no booklet configured:**
**Generated Invoice:** `260706-SD-00001`
**Next Invoice:** `260706-SD-00002`

## Testing Steps

### Step 1: Configure Booklet Number
1. Go to: Store Registration → Booklet Number Registration
2. Add a booklet number for your branch
3. Set it to Active status

### Step 2: Test Sales Entry
1. Open Sales Entry page
2. Check that Invoice No field shows the booklet format
3. Complete a sale
4. Verify invoice number is saved correctly

### Step 3: Verify Auto-Increment
For numeric formats:
1. Complete another sale
2. Check that invoice number incremented
3. Verify in booklet registration that current number updated

## Backward Compatibility

✅ **Old System Still Works:**
- If no booklet is configured, system uses old format
- Existing invoice numbers remain unchanged
- No data migration required

✅ **Seamless Transition:**
- Can add booklet configuration anytime
- Immediate effect after configuration
- No system restart needed

## Troubleshooting

### Issue: Invoice shows "System Generated"
**Cause:** No active booklet configured for the branch
**Solution:** 
1. Go to Booklet Number Registration
2. Add booklet for the branch
3. Set status to Active
4. Refresh Sales Entry page

### Issue: Invoice number doesn't increment
**Cause:** Booklet format is not set to "numeric"
**Solution:**
- Numeric formats auto-increment
- Date and Custom formats require manual updates
- Update current number in booklet registration after each batch

### Issue: Wrong invoice format
**Cause:** Incorrect booklet configuration
**Solution:**
1. Edit booklet in Booklet Number Registration
2. Verify prefix, current number, and suffix
3. Check preview before saving

### Issue: Multiple branches getting same number
**Cause:** Branches sharing same booklet configuration
**Solution:**
- Each branch should have its own booklet entry
- Ensure branch_code is correct in configuration

## API Reference

### Frontend: Get Invoice Number
```javascript
fetch('get_next_invoice_number.php', {
    method: 'POST',
    body: 'action=get_invoice_number&branch_code=SD'
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log(data.invoice_number); // "0003128-092-149"
        console.log(data.booklet_id);     // 1
        console.log(data.format);         // "numeric"
    }
});
```

### Backend: Generate Invoice
```php
include_once 'get_next_invoice_number.php';

$booklet = getBookletConfig($conn, $branch_code);

if ($booklet) {
    $invoice_no = generateInvoiceNumber($booklet);
    
    // For numeric auto-increment
    if ($booklet['booklet_format'] === 'numeric') {
        $next = incrementInvoiceNumber($booklet['current_number'], 'numeric');
        updateInvoiceNumber($conn, $booklet['id'], $next);
    }
}
```

## Best Practices

1. **Configure booklet before going live** - Test formats first
2. **Use Active/Inactive wisely** - Only one active booklet per branch
3. **Numeric for auto-increment** - Use numeric format for automatic sequencing
4. **Manual for date-based** - Update date-based formats daily/manually
5. **Keep records** - Deactivate instead of delete for audit trail
6. **Test thoroughly** - Complete test transactions before production

## Migration Guide

### From Old System to Booklet System

**Step 1:** Identify your current invoice format
```sql
SELECT invoice_no, branch_code 
FROM sales_entry 
WHERE branch_code = 'YOUR_BRANCH_CODE'
ORDER BY id DESC LIMIT 1;
```

**Step 2:** Create booklet configuration matching current format
- Extract the format pattern
- Set current number to last invoice number
- Add prefix/suffix as needed

**Step 3:** Activate booklet
- Set status to Active
- Test with one transaction
- Verify number continues sequence

**Step 4:** Monitor
- Check first few invoices
- Ensure no duplicates
- Verify auto-increment works

## Support

For issues or questions:
1. Check BOOKLET_NUMBER_SETUP.md for booklet configuration
2. Review this guide for integration details
3. Test with the integration demo page
4. Contact system administrator if needed

---
**Last Updated:** 2026-07-06
**Version:** 1.0
**System:** MOTOGAM Sales Entry Integration
