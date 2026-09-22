# Receive Stock Transfer - Branch Filter Fix

## Problem
When logged in as **MOTOLPA - MOTOGAM LIPA**, the receivestocktransfer.php page was showing stock transfers from **MOTOTYQ - MOTOGAM TAYABAS** and other branches that were not related to the logged-in branch.

## Root Cause
Both `transferapproval.php` and `receivestocktransfer.php` were using the same endpoint (`get_transfer_approvals.php`), which had logic that showed transfers where:
- FROM = user's branch, OR
- TO = user's branch

This is correct for `transferapproval.php` (where you need to see both outgoing and incoming transfers), but **incorrect** for `receivestocktransfer.php` (where you should only see incoming transfers that you need to receive).

Additionally, the code was treating Sub-admins the same as Super-Admins, allowing them to bypass branch filtering entirely.

## Solution

### 1. Created New Endpoint: `get_receive_transfers.php`
- **Purpose**: Specifically for receivestocktransfer.php
- **Filter Logic**: Only shows transfers WHERE **TO = user's branch** (incoming transfers)
- **Access Control**:
  - **Super-Admin**: Sees ALL transfers (no filter)
  - **Sub-admin**: Sees only transfers TO their assigned branch(es)
  - **Regular Users**: Sees only transfers TO their assigned branch

### 2. Updated `receivestocktransfer.php`
- Changed API endpoint from `get_transfer_approvals.php` to `get_receive_transfers.php`
- Line 628: `fetch('get_receive_transfers.php', {`

### 3. Updated `get_transfer_approvals.php`
- **Purpose**: For transferapproval.php
- **Filter Logic**: Shows transfers WHERE **FROM = user's branch OR TO = user's branch**
- **Access Control**:
  - **Super-Admin**: Sees ALL transfers (no filter)
  - **Sub-admin**: Sees transfers FROM or TO their assigned branch(es)
  - **Regular Users**: Sees transfers FROM or TO their assigned branch

## Result

### Now when logged in as MOTOLPA - MOTOGAM LIPA:

**In receivestocktransfer.php (Receive Stock Transfer):**
- ✅ Shows ONLY transfers where TO = MOTOGAM LIPA
- ❌ Does NOT show transfers from MOTOTYQ or other branches (unless they're coming TO your branch)

**In transferapproval.php (Transfer Approval):**
- ✅ Shows transfers where FROM = MOTOLPA (outgoing)
- ✅ Shows transfers where TO = MOTOGAM LIPA (incoming)
- This is correct for approval purposes

## Files Modified
1. `get_transfer_approvals.php` - Updated filter logic for FROM or TO
2. `receivestocktransfer.php` - Changed endpoint to use get_receive_transfers.php
3. `get_receive_transfers.php` - NEW FILE - Filters only TO branch

## Testing
1. Log in as MOTOLPA - MOTOGAM LIPA
2. Go to receivestocktransfer.php
3. Select a status filter (Approved/Disapproved/All)
4. Verify only transfers TO MOTOGAM LIPA are shown
5. Verify transfers FROM other branches TO other branches are NOT shown

## Date
January 28, 2025
