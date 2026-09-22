# Preorder2.php - Save Payment Functionality

## Overview
Added functionality to save remaining balance payments for existing preorders loaded via invoice search in `preorder2.php`.

## What Was Added

### 1. Backend Script: `save_preorder2_payment.php`
**Purpose**: Handles saving the remaining payment for an existing preorder.

**Key Features**:
- Validates preorder ID and payment data
- Retrieves existing preorder and payment data from database
- Merges existing payments with new payment(s)
- Calculates total paid amount and remaining balance
- Updates preorder status automatically:
  - `pending` - No payment made
  - `partial` - Partially paid (still has remaining balance)
  - `completed` - Fully paid (remaining balance ≤ ₱0.01)
- Returns updated balance and status

**Database Updates**:
- Updates `preorders.payment_data` with merged payment information
- Updates `preorders.status` based on payment completion
- Updates `preorders.updated_at` timestamp

### 2. Frontend Updates: `preorder2.php`

#### Updated `savePaymentData()` Function
Added validation to check if payment amount matches remaining balance:
```javascript
// Calculate total payment amount
let totalPaymentAmount = 0;
payments.forEach(payment => {
    const amount = parseFloat(String(payment.amount || 0).replace(/,/g, ''));
    totalPaymentAmount += amount;
});

// Validate against remaining balance
if (loadedPreorderId !== null && loadedRemainingBalance !== null) {
    if (Math.abs(totalPaymentAmount - loadedRemainingBalance) > 0.01) {
        alert('Payment total does not match remaining balance');
        return;
    }
}
```

#### Updated `savePreOrder()` Function
Now detects if an invoice is loaded and routes to payment update:
```javascript
function savePreOrder() {
    if (loadedPreorderId !== null && loadedRemainingBalance !== null) {
        // This is a payment update for existing preorder
        savePreOrderPayment();
    } else {
        // Redirect to preorder.php for new preorders
        alert('Please use preorder.php for creating new preorders.');
    }
}
```

#### New `savePreOrderPayment()` Function
Handles the complete flow of saving remaining payment:
- Validates invoice is loaded
- Validates payment data exists
- Validates payment amount matches remaining balance
- Sends data to backend via AJAX
- Shows success message with updated balance and status
- Clears form after successful save

## User Flow

1. **Search Invoice**: User searches for existing preorder invoice using "Search Invoice No" button
2. **View Breakdown**: System displays:
   - Unit(s) to pay
   - Amount already paid (from previous transactions)
   - Remaining balance
3. **Enter Payment**: User clicks "PAYMENT" button and enters payment details
   - Payment amount is validated against remaining balance
   - If amounts don't match, user is prompted to adjust
4. **Save Payment**: User clicks "SAVE" button
   - System validates all data
   - Saves payment to database
   - Merges with existing payment data
   - Updates preorder status automatically
   - Shows confirmation with new balance

## Payment Data Structure

### Single Payment
```json
{
    "payment_type": "cash",
    "amount": "90.00"
}
```

### Multiple Payments (Merged)
```json
{
    "payment_type": "multiple",
    "payments": [
        {
            "payment_type": "cash",
            "amount": "39900.00"
        },
        {
            "payment_type": "cash",
            "amount": "90.00"
        }
    ]
}
```

## Status Logic

| Total Paid | Remaining Balance | Status |
|-----------|------------------|---------|
| ₱0.00 | = Total Amount | `pending` |
| > ₱0.00 | > ₱0.01 | `partial` |
| = Total Amount | ≤ ₱0.01 | `completed` |

## Error Handling

- **No Invoice Loaded**: Prompts user to search for invoice first
- **No Payment Data**: Prompts user to enter payment details
- **Payment Mismatch**: Alerts if payment amount doesn't match remaining balance
- **Database Errors**: Shows friendly error message with details
- **Network Errors**: Catches and displays connection issues

## Testing Checklist

- [x] Load existing preorder with remaining balance
- [x] View breakdown showing amount already paid
- [x] Enter payment matching remaining balance
- [x] Validate payment amount must match remaining balance
- [x] Save payment successfully
- [x] Verify payment data merged correctly in database
- [x] Verify preorder status updated correctly
- [x] Clear form after successful save

## Files Modified/Created

### Created:
- `save_preorder2_payment.php` - Backend payment save handler

### Modified:
- `preorder2.php`:
  - `savePaymentData()` - Added remaining balance validation
  - `savePreOrder()` - Routes to payment update or new preorder
  - `savePreOrderPayment()` - New function for payment updates

## Notes

- `preorder2.php` is designed for updating existing preorder payments only
- New preorders should be created using `preorder.php`
- Payment history is preserved by merging all payments into single JSON array
- Status is automatically calculated based on payment amounts
- All monetary calculations use 2 decimal precision with 0.01 tolerance

## Future Enhancements

Consider adding:
- Payment history viewer showing all transactions
- Partial payment support (allow payments less than remaining balance)
- Payment method breakdown in reports
- Email receipt after payment update
- Print receipt for payment transactions
