# Sales Modification (modification-motogam.php) - Date Filter Changes

## Summary
Updated the sales entry filter logic so that **Super-Admin** can see **ALL sales** without date restrictions, while other users (Sub-admin and regular users) can only see sales from the **past 2 days**.

---

## Changes Made

### BEFORE (Line ~2174-2184):
```php
$is_admin = ($system_level === 'Super-Admin' || $system_level === 'Sub-admin');

// Only show records from the last 1 day (including today)
$date_filter = "DATE(se.created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";

if ($is_admin) {
    $where_clause = "WHERE $date_filter";
} else {
    $where_clause = "WHERE se.branch_code = '$branch_code' AND $date_filter";
}
```

**Problem:**
- Both Super-Admin and Sub-admin saw only **1 day** of sales
- No way to view older sales

---

### AFTER (Line ~2174-2194):
```php
$is_super_admin = (strcasecmp($system_level, 'Super-Admin') === 0);
$is_sub_admin = (strcasecmp($system_level, 'Sub-admin') === 0);

// Super-Admin sees ALL sales (no date filter)
// Sub-admin and others see only last 2 days
if ($is_super_admin) {
    // No date filter for Super-Admin
    $where_clause = "WHERE 1=1";
} else {
    // Date filter for Sub-admin and others: last 2 days
    $date_filter = "DATE(se.created_at) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)";
    if ($is_sub_admin) {
        $where_clause = "WHERE $date_filter";
    } else {
        $where_clause = "WHERE se.branch_code = '$branch_code' AND $date_filter";
    }
}
```

**Improvements:**
- ✅ Super-Admin sees **ALL sales** (no date restriction)
- ✅ Sub-admin sees **last 2 days** of sales
- ✅ Regular users see **last 2 days** of sales from their branch only
- ✅ Uses case-insensitive comparison (`strcasecmp`)

---

## User Access Levels

| User Role | Access | Date Filter | Branch Filter |
|-----------|--------|-------------|---------------|
| **Super-Admin** | ✅ ALL sales | ❌ None (all dates) | ❌ None (all branches) |
| **Sub-admin** | ⏱️ Limited | ✅ Last 2 days | ❌ None (all branches) |
| **Regular Users** | 🏢 Branch only | ✅ Last 2 days | ✅ Own branch only |

---

## SQL Query Logic

### Super-Admin Query:
```sql
SELECT se.id, se.created_at, se.invoice_no, se.first_name, se.last_name, 
       se.branch_code, b.branch_name, se.status, se.reason_to_modify 
FROM sales_entry se
LEFT JOIN branches b ON se.branch_code = b.branch_code
WHERE 1=1  -- No filters, all sales
ORDER BY se.created_at DESC, se.id DESC 
LIMIT 500
```

### Sub-Admin Query:
```sql
WHERE DATE(se.created_at) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
-- Shows last 2 days, all branches
```

### Regular User Query:
```sql
WHERE se.branch_code = '000' 
  AND DATE(se.created_at) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
-- Shows last 2 days, own branch only
```

---

## Benefits

1. **Super-Admin Flexibility**: Can access and modify any sales entry, regardless of age
2. **Security**: Sub-admin and regular users still restricted to recent sales
3. **Audit Capability**: Super-Admin can investigate old transactions
4. **Compliance**: Case-insensitive user role checking (more robust)

---

## Testing Checklist

### Test 1: Super-Admin Access
- [x] Login as Super-Admin
- [x] Go to modification-motogam.php
- [x] **Expected**: See ALL sales entries (including old ones)
- [ ] **Verified**: Can modify sales from any date

### Test 2: Sub-Admin Access  
- [x] Login as Sub-admin
- [x] Go to modification-motogam.php
- [x] **Expected**: See only sales from last 2 days
- [ ] **Verified**: Cannot see older sales

### Test 3: Regular User Access
- [x] Login as regular user
- [x] Go to modification-motogam.php
- [x] **Expected**: See only sales from last 2 days AND own branch
- [ ] **Verified**: Cannot see other branches

---

## Notes

- **LIMIT 500**: Query still limits to 500 results for performance
- **Order**: Sales are ordered by `created_at DESC` (newest first)
- **Status**: Both Active and VOID sales are shown
- **Case Insensitive**: Uses `strcasecmp()` to avoid case-sensitivity issues

---

**Modified:** August 27, 2026  
**File:** `modification-motogam.php` (Line 2174-2194)  
**Status:** ✅ Ready for Testing
