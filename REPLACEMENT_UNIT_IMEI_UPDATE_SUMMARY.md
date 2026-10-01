# Replacement Unit IMEI Update - Implementation Summary

## Overview
When a replacement unit is approved, the system now updates the original invoice to reflect the new IMEI and preserves the old IMEI for reference.

## Changes Made

### 1. Backend: `update_replacement_approval.php`
**What it does:**
- When a replacement is approved, it updates the `sales_entry_items` table
- Replaces the old IMEI with the new IMEI for the invoice items
- Stores the original IMEI in a new `old_imei` column
- Automatically creates the `old_imei` column if it doesn't exist

**Key Logic:**
```php
// Create IMEI replacement map
foreach ($old_items as $idx => $old_item) {
    $old_imei = trim($old_item['imei'] ?? '');
    if (!empty($old_imei) && isset($new_items[$idx])) {
        $new_imei = trim($new_items[$idx]['imei'] ?? '');
        if (!empty($new_imei)) {
            $imei_replacement_map[$old_imei] = [
                'new_imei' => $new_imei,
                'old_imei' => $old_imei
            ];
        }
    }
}

// Update sales_entry_items
foreach ($imei_replacement_map as $old_imei => $replacement_info) {
    $new_imei = $replacement_info['new_imei'];
    $old_imei_value = $replacement_info['old_imei'];
    
    // Add old_imei column if it doesn't exist
    $check_column = $conn->query("SHOW COLUMNS FROM sales_entry_items LIKE 'old_imei'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE sales_entry_items ADD COLUMN old_imei VARCHAR(50) AFTER imei");
    }
    
    // Update the IMEI in sales_entry_items
    UPDATE sales_entry_items 
    SET imei = ?, old_imei = ?
    WHERE sales_entry_id IN (SELECT id FROM sales_entry WHERE invoice_no = ?)
      AND UPPER(TRIM(imei)) = UPPER(TRIM(?))
}
```

### 2. Backend: `get_invoice_details.php`
**What changed:**
- Added `sei.old_imei` to the SELECT query when fetching invoice items
- This ensures the old IMEI is included in the response data

**Query modification:**
```sql
SELECT 
    sei.item_description,
    sei.item_code,
    sei.imei,
    sei.old_imei,  -- ADDED THIS LINE
    sei.quantity,
    ...
FROM sales_entry_items sei 
```

### 3. Frontend: `report.php`
**What changed:**
- Modified the invoice modal display to show both new IMEI and old IMEI
- Old IMEI is displayed below the new IMEI in a smaller, gray font

**Display Logic:**
```javascript
// Prepare IMEI display - show new IMEI and old IMEI if available
let imeiDisplay = item.imei || '';
if (item.old_imei && item.old_imei.trim() !== '') {
    imeiDisplay += `<br><span style="font-size:11px; color:#666;">Old Unit IMEI: ${item.old_imei}</span>`;
}

itemsHTML += `
    <tr>
        <td>${item.item_code || ''}</td>
        <td>${item.item_description || ''}</td>
        <td>${imeiDisplay}</td>  <!-- Shows both IMEIs -->
        ...
    </tr>
`;
```

## Database Changes
- **Table:** `sales_entry_items`
- **New Column:** `old_imei VARCHAR(50)`
- **Position:** After the `imei` column
- **Purpose:** Store the original IMEI when a replacement occurs

## How It Works (Complete Flow)

1. **User creates a replacement in `replacementunit.php`:**
   - Selects old units from invoice
   - Adds new replacement units
   - Saves replacement (status: Pending)

2. **Admin approves replacement in `approval-replacementunit.php`:**
   - Views pending replacements
   - Clicks "Approve" button
   - System calls `update_replacement_approval.php`

3. **Backend processes approval:**
   - Updates replacement status to "Approved"
   - Creates IMEI mapping (old → new)
   - Updates `sales_entry_items`:
     - Sets `imei` = new IMEI
     - Sets `old_imei` = old IMEI
   - Updates stock status (old unit → Good Stock, new unit → Sold)

4. **User views invoice in `report.php`:**
   - Clicks invoice to view details
   - System fetches data via `get_invoice_details.php`
   - Modal displays:
     - **IMEI:** [new IMEI]
     - **Old Unit IMEI:** [old IMEI] (in smaller gray text)

## Visual Example

**Before Replacement:**
```
IMEI: 123456789
```

**After Replacement Approved:**
```
IMEI: 987654321
Old Unit IMEI: 123456789
```

## Testing Checklist
- ✅ Replacement approval updates invoice IMEI
- ✅ Old IMEI is preserved in `old_imei` column
- ✅ Invoice modal displays both IMEIs
- ✅ Multiple replacements can be tracked independently
- ✅ Stock status is updated correctly
- ✅ Works for both single and multiple item replacements

## Notes
- The `old_imei` column is created automatically on first approval if it doesn't exist
- IMEI comparison is case-insensitive and trims whitespace
- Each IMEI replacement is tracked individually (not by description)
- Accessories (items without IMEI) are handled separately
- Only affects items in the original invoice that match the old IMEI

## Files Modified
1. `update_replacement_approval.php` - Main approval logic
2. `get_invoice_details.php` - Added old_imei to query
3. `report.php` - Updated invoice modal display
