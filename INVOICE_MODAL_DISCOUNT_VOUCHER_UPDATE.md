# Invoice Modal - Discount & Voucher Display Update

## Summary
Updated the `report.php` invoiceModal to display **Discount** and **Voucher** information in the Payment Information section.

## Changes Made

### File Updated: `report.php`

Added discount and voucher display to **all payment method sections** in the invoiceModal:

1. **Combined Payments** (e.g., GCash & Cash, E-Wallet + Cash)
2. **Filtered Payment View** (when viewing specific payment method)
3. **Cash Only Transactions**
4. **Payment Partner Transactions** (Bank of Makati, Home Credit, etc.)
5. **Credit Card Payments**
6. **Fallback for Other Payment Methods**

## Display Format

### Discount Display
- **Label**: "Discount:"
- **Value**: Displayed in red color (hex: #d32f2f)
- **Format**: - ₱X,XXX.XX (negative amount with Philippine peso sign)
- **Only shown if**: discount > 0

### Voucher Display
- **Label**: "Voucher ({voucher_number}):"
- **Value**: Displayed in red color (hex: #d32f2f)
- **Format**: - ₱X,XXX.XX (negative amount with Philippine peso sign)
- **Only shown if**: voucher_amount > 0
- **Shows voucher number**: Displays the voucher reference number in parentheses

## Example Display

```
Payment Information
─────────────────────────────────
Payment Method:     Cash
Amount:             ₱10,000.00
Discount:           - ₱500.00          ← NEW
Voucher (INV-001):  - ₱1,000.00        ← NEW
─────────────────────────────────
Total Payment:      ₱8,500.00
```

## Data Sources

The discount and voucher data is retrieved from the `sales_entry` table:
- **discount**: `data.sale.discount`
- **voucher_amount**: `data.sale.voucher_amount`
- **voucher_number**: `data.sale.voucher_number`

## Visual Styling

Both discount and voucher are displayed with:
- **Font weight**: 600 (semi-bold)
- **Color**: #d32f2f (red) to indicate deductions
- **Position**: Between the payment details and the Total Payment line
- **Format**: Negative amounts (- ₱) to clearly show they reduce the total

## Testing

To test the implementation:

1. **Create a sales entry with discount**:
   - Go to Sales Entry
   - Add items
   - Enter a discount amount
   - Save the entry

2. **Create a sales entry with voucher**:
   - Go to Sales Entry
   - Add items that have vouchers enabled
   - The voucher should automatically calculate
   - Save the entry

3. **View in Report**:
   - Go to report.php
   - Click on any invoice to open the invoice modal
   - Check the Payment Information section
   - Discount and voucher should appear if they exist

## Notes

- Discount and voucher only appear when their amounts are greater than 0
- Both fields are optional and won't show if not applicable
- The voucher displays its reference number for tracking purposes
- Red color (#d32f2f) is used to clearly indicate these are deductions from the payment
- Works across all payment method types (Cash, Credit Card, Payment Partners, etc.)
