# Sequential Numbering Fix for Replacement and Upgrade Units

## Problem
The Replacement No. and Upgrade No. were being generated randomly, causing issues with tracking and reporting. The numbering system needed to be:
1. **Sequential** - Numbers should increment by 1 for each transaction
2. **Daily Reset** - Numbers should reset to 1 at the start of each new day
3. **Branch-specific** - Include branch code for better tracking

## Solution Implemented

### 1. Created `get_next_replacement_number.php`
This new file generates sequential replacement numbers using the same logic as upgrade numbers.

**Format:** `REP-YYMMDD-BBBB-NNNN`
- `REP` = Prefix for Replacement
- `YYMMDD` = Date (Year-Month-Day)
- `BBBB` = Branch Code (e.g., "000" for main branch)
- `NNNN` = Sequential number (0001, 0002, 0003, etc.)

**Logic:**
- Queries the database for the last replacement number created today
- Extracts the sequence number and increments it by 1
- If no replacements exist for today, starts at 1
- Automatically resets to 1 each day

### 2. Updated `replacementunit.php`
**Changed FROM:**
- Random number generation: `REP-YYYYMMDD-####` (random 4 digits)

**Changed TO:**
- Server-side sequential generation via `get_next_replacement_number.php`
- Format: `REP-YYMMDD-BBBB-NNNN` (sequential 4 digits)

**Benefits:**
- No duplicate numbers
- Sequential tracking
- Daily reset
- Branch identification

### 3. Verified `upgradeunit.php`
The upgrade unit already uses the correct sequential numbering system via `get_next_upgrade_number.php`.

**Format:** `UPGD-YYMMDD-BBBB-NNNN`
- `UPGD` = Prefix for Upgrade
- `YYMMDD` = Date (Year-Month-Day)
- `BBBB` = Branch Code
- `NNNN` = Sequential number (0001, 0002, 0003, etc.)

## How It Works

### Daily Reset Mechanism
Both systems check the database for records created on the current date:
```sql
SELECT replacement_no 
FROM replacements 
WHERE DATE(created_at) = '$current_date' 
ORDER BY id DESC 
LIMIT 1
```

If a record exists for today:
- Extract the last 4 digits (sequence number)
- Increment by 1

If no record exists for today:
- Start at sequence 1

### Example Sequence

#### Day 1 (October 2, 2026):
- Branch Code: 001
- First replacement: `REP-261002-001-0001`
- Second replacement: `REP-261002-001-0002`
- Third replacement: `REP-261002-001-0003`

#### Day 2 (October 3, 2026):
- Branch Code: 001
- First replacement: `REP-261003-001-0001` ← Resets to 0001
- Second replacement: `REP-261003-001-0002`

## Files Modified

1. **Created:** `get_next_replacement_number.php`
   - New file for sequential replacement number generation

2. **Modified:** `replacementunit.php`
   - Replaced random number generation with server-side sequential generation
   - Added error handling for number generation failures

3. **Verified:** `upgradeunit.php`
   - Already using correct sequential numbering (no changes needed)

4. **Verified:** `get_next_upgrade_number.php`
   - Already implemented correctly (no changes needed)

## Testing Recommendations

1. **Test Sequential Generation:**
   - Create multiple replacements on the same day
   - Verify numbers increment: 0001, 0002, 0003, etc.

2. **Test Daily Reset:**
   - Create a replacement today
   - Create a replacement tomorrow (or change system date)
   - Verify tomorrow's number starts at 0001

3. **Test Branch Codes:**
   - Create replacements from different branches
   - Verify each branch code appears in the format

4. **Test Concurrent Requests:**
   - Have multiple users create replacements simultaneously
   - Verify no duplicate numbers are generated

## Database Requirements

The system uses existing tables:
- `replacements` table with `created_at` column
- `upgrades` table with `created_at` column
- `branches` table with `branch_code` column

No database schema changes are required.

## Benefits

✅ **Sequential Tracking:** Easy to track order of transactions
✅ **Daily Organization:** Clear separation of transactions by date
✅ **Branch Identification:** Know which branch created each transaction
✅ **No Duplicates:** Database-driven sequence prevents duplicates
✅ **Automatic Reset:** No manual intervention needed
✅ **Consistent Format:** Both replacement and upgrade use same format

## Date: October 2, 2026
