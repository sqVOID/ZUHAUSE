# Preorder Invoice Number - Before & After Comparison

## 📊 Feature Comparison

| Feature | BEFORE (Original) | AFTER (Booklet Integration) |
|---------|-------------------|----------------------------|
| **Invoice Format** | Fixed: PRE-YYYYMMDD-#### | Configurable per branch |
| **Format Types** | 1 (Date-based only) | 3 (Numeric, Date+Suffix, Custom) |
| **Configuration** | Hardcoded in PHP | Admin UI (bookletnoreg.php) |
| **Branch-Specific** | ❌ No | ✅ Yes |
| **Auto-Increment** | Manual date change | ✅ Auto (for numeric) |
| **Prefix/Suffix** | Fixed "PRE-" | ✅ Customizable |
| **Fallback Support** | N/A | ✅ Yes (uses old format) |
| **Display on Load** | Placeholder text | ✅ Actual invoice number |

---

## 🔄 Code Changes

### 1. preorder.php - Invoice Fetching

#### BEFORE:
```javascript
// Fetch next invoice number from server
fetch('get_next_preorder_number.php')
    .then(response => response.json())
    .then(result => {
        if (result.status === 'success') {
            document.getElementById('invoice_no').value = result.invoice_no;
        } else {
            document.getElementById('invoice_no').value = 'System Generated';
        }
    })
```

#### AFTER:
```javascript
// Fetch next invoice number from booklet system
const branchCode = '<?php echo $branch_code; ?>';

fetch('get_next_invoice_number.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=get_invoice_number&branch_code=' + branchCode + '&page_type=preorder'
})
    .then(response => response.json())
    .then(result => {
        if (result.success && result.invoice_number) {
            document.getElementById('invoice_no').value = result.invoice_number;
        }
    })
```

**Key Changes:**
- ✅ Uses centralized `get_next_invoice_number.php`
- ✅ Passes `page_type=preorder` parameter
- ✅ Includes branch code from PHP session

---

### 2. save_preorder.php - Invoice Generation

#### BEFORE:
```php
// Generate Pre-order Invoice Number (using original system)
$today = date('Ymd');
$prefix = "PRE-{$today}-";

$invoice_query = $conn->prepare("SELECT invoice_no FROM preorders WHERE invoice_no LIKE 'PRE-%' ORDER BY invoice_no DESC LIMIT 1");
$invoice_query->execute();
$invoice_result = $invoice_query->get_result();

if ($invoice_result && $invoice_result->num_rows > 0) {
    $row = $invoice_result->fetch_assoc();
    $last_invoice = $row['invoice_no'];
    $last_id = intval(substr($last_invoice, -4));
    $next_id = $last_id + 1;
} else {
    $next_id = 1;
}
$invoice_query->close();

$formatted_id = str_pad($next_id, 4, '0', STR_PAD_LEFT);
$invoice_no = $prefix . $formatted_id;
```

#### AFTER:
```php
// Generate Pre-order Invoice Number using booklet configuration
include_once 'get_next_invoice_number.php';

$booklet = getBookletConfig($conn, $branch_code, 'preorder');

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
    [... original logic here ...]
}
```

**Key Changes:**
- ✅ Checks for booklet configuration first
- ✅ Auto-increments numeric formats
- ✅ Falls back to original format if no booklet
- ✅ Maintains backward compatibility

---

### 3. get_next_invoice_number.php - Fallback Logic

#### BEFORE (didn't handle preorder):
```php
// Only handled salesentry fallback
$fallback_invoice = sprintf("%s%s%s-%s-%05d", $year, $month, $day, $branch_code, $sequence);
```

#### AFTER:
```php
if ($page_type === 'preorder') {
    // Preorder-specific fallback: PRE-YYYYMMDD-####
    $today = date('Ymd');
    $prefix = "PRE-{$today}-";
    
    $preorder_query = $conn->query("
        SELECT invoice_no FROM preorders 
        WHERE invoice_no LIKE 'PRE-%'
        ORDER BY invoice_no DESC LIMIT 1
    ");
    
    // Generate PRE-YYYYMMDD-#### format
    $fallback_invoice = $prefix . $formatted_id;
} else {
    // Sales entry fallback: YYMMDD-BRANCHCODE-NNNNN
    $fallback_invoice = sprintf("%s%s%s-%s-%05d", $year, $month, $day, $branch_code, $sequence);
}
```

**Key Changes:**
- ✅ Separate fallback logic per page_type
- ✅ Queries correct table (preorders vs sales_entry)
- ✅ Maintains original format when no booklet

---

## 💡 Usage Examples

### Scenario 1: Branch with Booklet Configuration
```
Configuration:
- Branch: MOTOGAM (001)
- Page Type: Pre-Order
- Format: Numeric
- Prefix: PRE-
- Current Number: 0001
- Suffix: -MOTO

Result: PRE-0001-MOTO → PRE-0002-MOTO → PRE-0003-MOTO
```

### Scenario 2: Branch without Booklet (Fallback)
```
Configuration: None

Result: PRE-20260706-0001 → PRE-20260706-0002 → PRE-20260706-0003
(Same as original system)
```

### Scenario 3: Different Branches, Different Formats
```
Branch A (MOTOGAM):
- Config: Numeric with prefix "MG-PRE-"
- Result: MG-PRE-0001, MG-PRE-0002

Branch B (San Dionisio):
- Config: Date+Suffix with suffix "-SD"
- Result: 07-06-2026-SD (manual date update)

Branch C (No config):
- Fallback: PRE-20260706-0001
```

---

## ✅ Benefits of Integration

1. **Flexibility** - Each branch can use their preferred format
2. **Consistency** - Same system as sales entry
3. **Auto-Increment** - No manual tracking for numeric formats
4. **Backward Compatible** - Works with or without configuration
5. **Centralized Management** - One admin page for all invoice formats
6. **Easy Updates** - Change format without code changes
7. **Branch-Specific** - Support for multiple branches with different needs

---

## 🔍 Testing Scenarios

| Test Case | Expected Behavior | Status |
|-----------|-------------------|--------|
| Load preorder.php with booklet | Shows configured invoice format | ✅ |
| Load preorder.php without booklet | Shows PRE-YYYYMMDD-#### fallback | ✅ |
| Save preorder with booklet | Uses booklet format | ✅ |
| Save preorder without booklet | Uses fallback format | ✅ |
| Numeric format auto-increment | Current number increases by 1 | ✅ |
| Date+Suffix format | Uses manual date entry | ✅ |
| Multiple branches | Each uses own config | ✅ |
| Switch from fallback to booklet | New preorders use booklet | ✅ |

---

## 📝 Migration Notes

- **Existing Preorders**: Not affected, keep original invoice numbers
- **New Preorders**: Use booklet system if configured
- **Database**: No migration needed (page_type column already exists)
- **Code**: Fully backward compatible

---

**Summary**: The preorder system now has the same powerful invoice management capabilities as the sales entry system, while maintaining complete backward compatibility with the original format.
