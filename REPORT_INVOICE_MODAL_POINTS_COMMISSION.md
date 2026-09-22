# Report Invoice Modal - Points & Commission Display

## Summary
Added Points and Commission fields to the Sale Information section in the invoice modal of report.php.

## Changes Made

### File: report.php
**Location**: Invoice Modal → Sale Information section

**Before:**
```html
Sale Information
─────────────────────
Assisted By:    John Doe
Date Sold:      8/18/2026, 10:30:00 AM
Branch:         092
```

**After:**
```html
Sale Information
─────────────────────
Assisted By:    John Doe
Date Sold:      8/18/2026, 10:30:00 AM
Branch:         092
Points:         500            ← NEW
Commission:     ₱250.00        ← NEW
```

## Implementation

Added two new rows to the Sale Information table:

```javascript
<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Points:</td>
    <td style="padding:5px 0;">${parseFloat(data.sale.points || 0).toLocaleString('en-US', { minimumFractionDigits: 0 })}</td>
</tr>
<tr><td style="padding:5px 10px 5px 0; font-weight:600;">Commission:</td>
    <td style="padding:5px 0;">₱${parseFloat(data.sale.commission || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
</tr>
```

## Formatting

### Points:
- **Format**: Number with thousand separators, no decimals
- **Example**: 1,500 (not 1,500.00)
- **Default**: 0 if no points

### Commission:
- **Format**: Philippine peso sign (₱) + number with 2 decimals
- **Example**: ₱250.00
- **Default**: ₱0.00 if no commission

## Data Source

Both fields retrieve data from `data.sale` object:
- **Points**: `data.sale.points`
- **Commission**: `data.sale.commission`

This data comes from `get_invoice_details.php` which fetches from the `sales_entry` table.

## Invoice Modal Layout

```
┌─────────────────────────────────────────────────────────┐
│                    Invoice Details                       │
├─────────────────────┬───────────────────────────────────┤
│ Customer Info       │ Sale Information                  │
│ ─────────────────   │ ─────────────────                 │
│ Name: John Doe      │ Assisted By: Jane Smith           │
│ Address: 123 St     │ Date Sold: 8/18/2026             │
│ Contact: 09xx       │ Branch: 092                       │
│ Email: john@...     │ Points: 500            ← NEW      │
│                     │ Commission: ₱250.00    ← NEW      │
├─────────────────────────────────────────────────────────┤
│                        Items                             │
│  [Item Code] [Description] [IMEI] [Qty] [Price] [Total]│
├─────────────────────────────────────────────────────────┤
│                   Payment Information                    │
│  Payment Method: Cash                                    │
│  Amount: ₱2,500.00                                      │
│  Discount: -₱100.00                                     │
│  Voucher: -₱95.00                                       │
│  Total Payment: ₱2,305.00                               │
└─────────────────────────────────────────────────────────┘
```

## Testing

1. **Create a sales entry** with:
   - Items: ₱2,500.00
   - Points: 500
   - Commission: ₱250.00

2. **Go to Report page**:
   - Click on the invoice
   - Invoice modal opens

3. **Verify Sale Information shows**:
   - ✅ Assisted By
   - ✅ Date Sold
   - ✅ Branch
   - ✅ **Points: 500**
   - ✅ **Commission: ₱250.00**

## Files Updated

| File | Section | Change |
|------|---------|--------|
| `report.php` | Invoice Modal → Sale Information | Added Points and Commission rows |

## Complete Invoice Modal Information

The invoice modal now displays all important information:

### Customer Information:
- Name
- Address
- Contact
- Email

### Sale Information:
- Assisted By
- Date Sold
- Branch
- **Points** ← NEW
- **Commission** ← NEW

### Items:
- Item details with prices

### Payment Information:
- Payment method details
- Discount
- Voucher
- Total payment

Perfect! The invoice modal now shows complete sale information! 🎉
