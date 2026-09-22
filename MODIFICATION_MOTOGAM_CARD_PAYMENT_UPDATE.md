# Modification-Motogam.php Card Payment Update

## Summary
Updated the Card Payment modal in `modification-motogam.php` to match the functionality of `salesentry.php` and `salesentrylate.php`, ensuring Terminal Issuer, Terminal ID, Bank, and Terms dropdowns work properly.

## Changes Made

### 1. Database Query Addition (Line ~54-58)
**Added**: Query to fetch banks from `others_bank` table
```php
// Fetch other banks (Active only) for Card Payment
$others_bank_result = $conn->query("SELECT bank_name FROM others_bank WHERE status='Active' ORDER BY bank_name");
```

### 2. Credit Card Section (Lines ~2718-2759)
**Already Present**: Terminal Issuer and Terminal ID fields with proper structure
- Terminal Issuer dropdown populated from `terminal_issuers` table
- Terminal ID dropdown with `onchange="filterTerminalIds('cc')"` event
- Bank dropdown (id: `creditCardBankDropdown`)
- Terms dropdown (id: `creditCardTermsDropdown`)
- Additional fields: MID, Card No, Approval Code, Batch, Amount

### 3. Debit Card Section (Lines ~2784-2825)
**Already Present**: Terminal Issuer and Terminal ID fields with proper structure
- Terminal Issuer dropdown populated from `terminal_issuers` table
- Terminal ID dropdown with `onchange="filterTerminalIds('dc')"` event
- Bank dropdown (id: `debitCardBankDropdown`)
- Terms dropdown (id: `debitCardTermsDropdown`)
- Additional fields: MID, Card No, Approval Code, Batch, Amount

### 4. JavaScript Functions Added (Lines ~5550-5630)

#### a. Enhanced `filterTerminalIds` Function
**Added**: Event listener to Terminal ID dropdown
- When Terminal ID is selected, it triggers `filterBanksByTerminalId()` to populate Bank dropdown

#### b. New `filterBanksByTerminalId` Function
**Purpose**: Populate Bank dropdown with banks from `others_bank` table
- Clears existing Bank and Terms options
- Populates Bank dropdown with banks from `others_bank` table
- Sets up Bank change listener via `setupBankChangeListener()`

#### c. New `setupBankChangeListener` Function
**Purpose**: Handle Bank dropdown changes and populate Terms dropdown
- When Bank is selected, populates Terms dropdown with standard installment terms:
  - 3 Months
  - 6 Months
  - 12 Months
  - 24 Months

## Functionality Flow

1. **User selects Terminal Issuer** (e.g., "BDO", "BPI")
   - `filterTerminalIds('cc')` or `filterTerminalIds('dc')` is called
   - Terminal ID dropdown is populated with IDs matching the selected issuer
   - Bank and Terms dropdowns are cleared

2. **User selects Terminal ID**
   - `filterBanksByTerminalId('cc')` or `filterBanksByTerminalId('dc')` is called
   - Bank dropdown is populated with banks from `others_bank` table
   - Terms dropdown is cleared

3. **User selects Bank**
   - Terms dropdown is populated with standard installment terms
   - Amount field is cleared and enabled for manual entry

4. **User selects Terms and enters Amount**
   - Payment data is ready for submission

## Connected Tables

1. **terminal_issuers** - Stores terminal issuer names (banks that issue terminals)
2. **terminal_ids** - Stores terminal IDs with their associated issuers and branches
3. **others_bank** - Stores bank names for card payment options

## Files Referenced

- `modification-motogam.php` - Main file updated
- `salesentry.php` - Reference implementation
- `salesentrylate.php` - Reference implementation
- `createterminalid.php` - Management of terminal IDs
- `createterminal.php` - Management of terminal issuers
- `itemreg.php` - Item registration with pricing

## Testing Checklist

- [ ] Terminal Issuer dropdown displays active issuers from `terminal_issuers` table
- [ ] Selecting Terminal Issuer populates Terminal ID dropdown with matching IDs
- [ ] Selecting Terminal ID populates Bank dropdown with banks from `others_bank` table
- [ ] Selecting Bank populates Terms dropdown with standard installment terms
- [ ] All dropdowns clear properly when parent selection changes
- [ ] Payment data is correctly saved when form is submitted
- [ ] Both Credit Card and Debit Card sections work identically
- [ ] Modification form maintains existing payment data when loading a sale

## Notes

- The implementation in `modification-motogam.php` is simpler than `salesentry.php` because it works with existing sales data rather than dynamic item pricing
- Bank dropdown is populated from `others_bank` table only (not from item prices)
- Terms dropdown uses standard fixed terms rather than item-specific pricing tiers
- This is appropriate for modification scenarios where the original pricing structure may have changed
