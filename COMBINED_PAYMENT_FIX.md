# Combined Payment Display Fix for report.php

## Summary
The current implementation in report.php only handles "E-Wallet + Cash" combined payments. It needs to be enhanced to handle ALL possible payment method combinations:

- Payment Partner (STO niño de cebu, Home Credit) + Cash
- Payment Partner + E-Wallet  
- Credit Card + Cash
- Debit Card + Cash
- Online Banking + Cash
- QR PH + Cash
- Starpay QR + Cash
- Any combination of payment methods

## Solution Approach

The fix requires replacing the `hasMultiplePayments` section in report.php (around line 2076-2157) with a comprehensive payment parser that:

1. Splits the `payment_type` by '+' to get individual payment methods
2. For each payment method type, displays its specific fields:
   - **Payment Partners**: Loan Type, Loan Terms, Customer Name, Loan Number, Loan Balance, Down Payments
   - **Credit/Debit Card**: Terminal Issuer, Terminal ID, Bank, Terms, MID, Card No, Approval Code, Batch, Amount
   - **QR PH/Starpay**: Bank, Customer Name, Reference No, Amount
   - **E-Wallet**: Customer Name, Reference No, Amount
   - **Online Banking**: Reference No, Amount
   - **Cash**: Amount only

3. Calculates total payment from all methods

## Key Features Needed

- **Smart Amount Detection**: Each payment method's amount should be found by:
  - Looking for method-specific amount fields first (e.g., "credit_card_amount")
  - If first payment method, try using "Amount" field
  - For Cash, calculate as (Total - other payments) if not found explicitly
  
- **Field Extraction**: Use paymentData keys to extract all relevant information for each payment type

- **Proper Formatting**: Display each payment method as a section with proper indentation

## Implementation Status

✅ Cash only - Working
✅ Payment Partners (with down payments) - Working  
✅ Credit Card (single) - Working
✅ Debit Card (single) - Working
✅ QR PH (single) - Working
✅ Starpay QR (single) - Working
✅ E-Wallet (single) - Working
✅ Online Banking (single) - Working
✅ E-Wallet + Cash - Working (partially)
❌ All other combined payments - Not implemented

The file is too large to edit in one go. The solution is to replace lines 2076-2157 in report.php with a comprehensive payment type parser.
