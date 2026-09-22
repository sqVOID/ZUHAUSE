# Claimed Items Branch Column Addition

## Summary
Added a `branch` column to the `claimed_items` table to store the full branch name from the `unclaimed_freebies` table. This allows the Claim Item Modification page to display the complete branch name instead of just the branch code.

## Data Source Priority
The branch name is retrieved in the following order:
1. **Primary Source**: `unclaimed_freebies.branch` (most accurate - set when the freebie was originally created)
2. **Fallback**: `branches.branch_name` (looked up via `branch_code`)
3. **Last Resort**: Display `branch_code` if name is unavailable

## Changes Made

### 1. Database Migration Script
**File:** `add_branch_to_claimed_items.php`

This script:
- Adds a new `branch` column (VARCHAR 255) to the `claimed_items` table
- Populates existing records from `unclaimed_freebies.branch` (primary source)
- Falls back to `branches.branch_name` for any records not found in unclaimed_freebies
- Shows the updated table structure and sample data for verification

**To run:** Navigate to `http://localhost/MOTOGAM/add_branch_to_claimed_items.php`

### 2. Updated Save Process
**File:** `save_claimed_items.php`

Changes:
- Retrieves the full branch name from the `unclaimed_freebies` table (primary source)
- Falls back to `branches` table if needed
- Updates the table creation SQL to include the `branch` column
- Modified the INSERT statement to include both `branch_code` and `branch` fields
- Updated the bind_param call to include the branch name value

**Key code changes:**
```php
// Get branch name from the first unclaimed freebie (primary source)
$branch_name = '';
if (!empty($unclaimed_item_ids) && count($unclaimed_item_ids) > 0) {
    $first_unclaimed_id = $unclaimed_item_ids[0]['id'];
    $branch_query = $conn->prepare("SELECT branch FROM unclaimed_freebies WHERE id = ?");
    $branch_query->bind_param('i', $first_unclaimed_id);
    $branch_query->execute();
    $branch_result = $branch_query->get_result();
    if ($branch_result && $branch_result->num_rows > 0) {
        $branch_row = $branch_result->fetch_assoc();
        $branch_name = $branch_row['branch'];
    }
    $branch_query->close();
}

// Fallback: Get branch name from branches table
if (empty($branch_name) && !empty($branch_code)) {
    // ... fallback logic
}
```

### 3. Updated Display Logic
**File:** `modification-claimitem.php`

Changes:
- Modified the SQL query to use `COALESCE(uf.branch, ci.branch, b.branch_name, ci.branch_code)` to prioritize unclaimed_freebies branch name
- Updated the display logic to show ONLY the branch name (without code)

**Display format:**
- If branch name exists: `Main Branch`
- If only code exists: `001`

## Database Schema

### Before:
```sql
claimed_items (
    ...
    branch_code VARCHAR(10) NULL,
    ...
)
```

### After:
```sql
claimed_items (
    ...
    branch_code VARCHAR(10) NULL,
    branch VARCHAR(255) NULL,
    ...
)
```

## Benefits

1. **Full Branch Information**: Users can now see the complete branch name instead of just the code
2. **Better Readability**: Easier to identify branches at a glance (shows "Main Branch" not "Main Branch - 001")
3. **Data Consistency**: Branch names are pulled from `unclaimed_freebies` where they were originally set, preserving historical accuracy
4. **Backward Compatible**: The update script populates existing records automatically with proper fallback logic
5. **Authoritative Source**: Uses `unclaimed_freebies.branch` as the primary data source since that's where the item originated

## Testing Steps

1. Run the migration script: `add_branch_to_claimed_items.php`
2. Verify the table structure shows the new `branch` column
3. Check that existing records are populated with branch names
4. Create a new claimed item through the claim process
5. View the Modification page and verify branch names display correctly

## Files Modified

- ✅ `add_branch_to_claimed_items.php` - NEW migration script
- ✅ `save_claimed_items.php` - Updated to save branch name
- ✅ `modification-claimitem.php` - Updated to display branch name

## Notes

- The `branch` column is nullable to maintain backward compatibility
- Existing records will be automatically populated when the migration script runs
- The display logic has fallbacks to handle cases where branch name might be missing
- Both `branch_code` and `branch` are stored for flexibility and data integrity
