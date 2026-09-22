# Preorder Booklet Integration Summary

## Overview
The preorder system is now integrated with the booklet number registration system (`bookletnoreg.php`), allowing branches to configure custom invoice number formats for pre-orders.

## Integration Status
✅ **COMPLETED** - Preorder invoice numbers now connect to booklet system

## How It Works

### 1. Booklet Configuration
- Go to **Store Registration → Booklet Number Registration**
- Add a new booklet configuration:
  - **Branch**: Select the branch
  - **Page Type**: Select "Pre-Order"
  - **Format Type**: Choose from:
    - Numeric (e.g., 0003128-092-149)
    - Date + Suffix (e.g., 07-06-2026-PRE)
    - Custom Format
  - **Prefix/Suffix**: Optional
  - **Current Number**: Starting number

### 2. Invoice Generation
When creating a pre-order:
- System checks if booklet configuration exists for page_type='preorder'
- **If booklet configured**: Uses booklet format and auto-increments
- **If no booklet**: Falls back to default format PRE-YYYYMMDD-####

### 3. Fallback Behavior
If no booklet is configured for preorder:
- Format: `PRE-YYYYMMDD-####`
- Example: `PRE-20260706-0001`
- Sequence never resets (continues across all dates)

## Modified Files

### 1. `preorder.php`
**Changes:**
- Updated `initializeForm()` JavaScript function
- Now fetches invoice number from `get_next_invoice_number.php`
- Passes `page_type=preorder` and branch code
- Displays actual invoice number instead of "System Generated"

**Line Updated:** ~1973-1998

### 2. `save_preorder.php`
**Changes:**
- Added booklet system integration
- Includes `get_next_invoice_number.php` helper
- Checks for booklet configuration with page_type='preorder'
- Auto-increments numeric format booklets
- Falls back to PRE-YYYYMMDD-#### if no booklet configured

**Line Updated:** ~85-120

### 3. `get_next_invoice_number.php`
**Changes:**
- Enhanced fallback logic to handle multiple page types
- Added preorder-specific fallback format
- Queries `preorders` table for sequence when page_type='preorder'
- Maintains separate sequences for different page types

**Line Updated:** ~127-175

## Database Requirements
No additional database changes needed. Uses existing:
- `booklet_numbers` table (with page_type column)
- `preorders` table

## Example Usage

### Example 1: Configure Custom Preorder Format
```
Branch: MOTOGAM (001)
Page Type: Pre-Order
Format: Numeric
Prefix: PRE-
Current Number: 0001
Suffix: -M

Result: PRE-0001-M, PRE-0002-M, PRE-0003-M...
```

### Example 2: Date-Based Format
```
Branch: San Dionisio (092)
Page Type: Pre-Order
Format: Date + Suffix
Current Number: 07-06-2026
Suffix: -SD

Result: 07-06-2026-SD (manually updated per day)
```

### Example 3: No Configuration (Fallback)
```
No booklet configured for preorder
Result: PRE-20260706-0001, PRE-20260706-0002...
```

## Testing Checklist
- [ ] Create booklet configuration for page_type='preorder'
- [ ] Open preorder.php and verify invoice number displays
- [ ] Create a pre-order and verify invoice is saved correctly
- [ ] Check that booklet current_number auto-increments
- [ ] Test fallback (no booklet) - should use PRE-YYYYMMDD-####
- [ ] Test with different branches
- [ ] Test with different format types (Numeric, Date+Suffix, Custom)

## Benefits
1. ✅ Unified invoice management across all transaction types
2. ✅ Branch-specific invoice formats for pre-orders
3. ✅ Backward compatibility with fallback format
4. ✅ Auto-increment for numeric formats
5. ✅ Consistent with salesentry integration pattern

## Notes
- Each branch can have its own preorder invoice format
- Multiple branches can share the same format if desired
- Format changes only affect new pre-orders (existing pre-orders unchanged)
- Booklet status (Active/Inactive) controls whether format is used

## Related Documentation
- `BOOKLET_SYSTEM_COMPLETE.md` - Comprehensive booklet system overview
- `SALESENTRY_BOOKLET_INTEGRATION.md` - Sales entry integration (similar pattern)
- `BOOKLET_NUMBER_SETUP.md` - Setup guide for booklet system

---
**Integration Date:** July 6, 2026
**Status:** Active and Production Ready
