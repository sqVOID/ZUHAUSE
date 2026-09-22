# Claim Item - Branch Access Control Fix

## Problem
When searching for unclaimed items in `claimitem.php`, the system was showing results from **all branches** regardless of the user's assigned branch access. This allowed users to see and potentially claim items from branches they shouldn't have access to.

## Solution Applied
Implemented branch access control in the unclaimed items search functionality to ensure that:
- **Super-Admin** users can search and see unclaimed items from ALL branches
- **Sub-admin** and **Regular users** can ONLY search and see unclaimed items from their assigned branch(es)

## Files Modified

### 1. search_unclaimed_freebies.php
**Changes Made:**
- Added user branch access check using `$_SESSION['user_branch']` and `$_SESSION['system_level']`
- Implemented branch filtering in the SQL query:
  - Super-Admin: No branch restriction (can see all)
  - Sub-admin/Regular users: Filtered by assigned branch(es)
  - Supports multiple branches (comma-separated) for Sub-admin users
- Added SQL condition: `AND uf.branch IN ($branch_list)` to filter results

**Code Logic:**
```php
// Only Super-Admin can see all branches
if ($system_level !== 'Super-Admin') {
    // Sub-admin and regular users are restricted to their assigned branches
    if (!empty($user_branch)) {
        // Handle multiple branches (comma-separated)
        $branch_names = array_map('trim', explode(',', $user_branch));
        $branch_names_escaped = array_map(function($name) use ($conn) {
            return "'" . $conn->real_escape_string($name) . "'";
        }, $branch_names);
        $branch_list = implode(',', $branch_names_escaped);
        
        $branch_condition = " AND uf.branch IN ($branch_list)";
    } else {
        // No branch assigned - should not see any results
        $branch_condition = " AND 0 = 1";
    }
}
```

## Access Control Summary

| User Type | Branch Access Behavior | Example |
|-----------|------------------------|---------|
| **Super-Admin** | Can search and view unclaimed items from **ALL branches** | Searches invoice #12345 → sees items from all branches |
| **Sub-admin** | Can search and view unclaimed items only from **their assigned branch(es)** | Assigned to "Greentelcom SM Lucena" → only sees items from that branch |
| **Regular User** | Can search and view unclaimed items only from **their assigned branch** | Assigned to "MOTOGAM LPA G 01 Y" → only sees items from that branch |

## Multiple Branch Support
The system now properly handles users with **multiple branches** assigned:
- Branch field format: `"MOTOGAM LPA G 01 Y, Greentelcom SM Lucena"` (comma-separated)
- System splits the string and checks if the unclaimed item's branch matches ANY of the assigned branches
- Users will see unclaimed items from ALL their assigned branches

## How It Works

### Before Fix:
1. User searches for invoice number
2. System retrieves ALL unclaimed items with that invoice number (regardless of branch)
3. User sees items from branches they don't have access to ❌

### After Fix:
1. User searches for invoice number
2. System checks user's system level and assigned branch(es)
3. System filters results to ONLY show unclaimed items from allowed branches
4. User sees ONLY items from their authorized branch(es) ✅

## Testing Recommendations

### Test Case 1: Super-Admin
1. Login as Super-Admin user
2. Go to Claim Item page
3. Search for an invoice with unclaimed items from different branches
4. **Expected**: Should see all unclaimed items regardless of branch

### Test Case 2: Sub-admin with Single Branch
1. Login as Sub-admin assigned to "Greentelcom SM Lucena"
2. Go to Claim Item page
3. Search for an invoice with unclaimed items from "Greentelcom SM Lucena"
4. **Expected**: Should see the items
5. Search for an invoice with unclaimed items from a different branch
6. **Expected**: Should see "No unclaimed freebies found for this invoice number"

### Test Case 3: Sub-admin with Multiple Branches
1. Login as Sub-admin assigned to "MOTOGAM LPA G 01 Y, Greentelcom SM Lucena"
2. Go to Claim Item page
3. Search for invoices from both assigned branches
4. **Expected**: Should see items from both branches
5. Search for an invoice from a non-assigned branch
6. **Expected**: Should see "No unclaimed freebies found for this invoice number"

### Test Case 4: Regular User
1. Login as regular user assigned to specific branch
2. Go to Claim Item page
3. Search for an invoice from their assigned branch
4. **Expected**: Should see the items
5. Search for an invoice from a different branch
6. **Expected**: Should see "No unclaimed freebies found for this invoice number"

## Security Benefits
- ✅ Prevents unauthorized access to unclaimed items from other branches
- ✅ Enforces branch-level data isolation
- ✅ Maintains consistent access control across the system
- ✅ Supports multiple branch assignments for Sub-admin users
- ✅ Aligns with existing branch access control implementation

## Important Notes
1. Users must be logged in with a valid session (enforced by `session_check.php`)
2. Branch assignment is stored in the user's account and loaded into `$_SESSION['user_branch']` during login
3. If a user's branch assignment is updated, they must **logout and login again** for the changes to take effect
4. The `unclaimed_freebies` table must have a `branch` column that stores the branch name

## Date Applied
January 30, 2025

## Status
✅ **COMPLETED** - Branch access control successfully implemented for Claim Item search
