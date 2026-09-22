# Promo Usage Limit Feature

## Overview
Added a usage limit feature to the promo system that allows administrators to set a maximum number of times a promo can be used. When the limit is reached, the promo automatically deactivates.

## Changes Made

### 1. Database Changes (promoreg.php)
- **Added two new columns to `promos` table:**
  - `usage_limit` (INT, nullable) - Maximum number of times the promo can be used
  - `usage_count` (INT, default 0) - Current count of how many times the promo has been used

### 2. Promo Registration Form (promoreg.php)
- Added "Usage Limit" input field with optional usage
- Shows current usage count when editing existing promos
- Displays usage information in the promo list table:
  - Shows "X / Y" format (e.g., "5 / 20")
  - Shows "X / Unlimited" if no limit is set
  - Color-coded: Red when limit reached, Orange at 80%, Green otherwise
  - Shows "LIMIT REACHED" label when at 100%
- Updated theme colors to match salesentry.php (navy & gold)

### 3. Promo Sales Entry (promosentry.php)
- Updated promo dropdown query to exclude promos that have reached their limit
- Shows usage information in promo dropdown (e.g., "Summer Promo (5/20 used)")
- Sends promo_id to backend when saving sales entry
- Updated theme colors to match salesentry.php

### 4. Sales Entry Backend (save_sales_entry.php)
- Added `promo_id` column to `sales_entry` table
- Increments `usage_count` when a promo is used
- Automatically deactivates promo when usage limit is reached
- Includes proper validation and error handling

## How It Works

### Setting Up a Promo with Usage Limit
1. Go to Promo Registration page
2. Create or edit a promo
3. Enter a number in "Usage Limit" field (e.g., 20)
4. Leave blank for unlimited usage
5. Save the promo

### When a Promo is Used
1. User selects promo in Promo Sales Entry
2. System checks if promo has available uses
3. When sale is saved:
   - `usage_count` increments by 1
   - If count reaches limit, promo status changes to "Deactivated"
   - Promo becomes unavailable for future sales

### Monitoring Usage
- View promo list in Promo Registration
- "Usage" column shows current usage vs limit
- Color indicators:
  - 🟢 Green: Under 80% capacity
  - 🟠 Orange: 80-99% capacity
  - 🔴 Red: 100% (limit reached)

## Benefits
1. **Automated Control** - No manual intervention needed to stop promos
2. **Real-time Tracking** - See usage statistics at a glance
3. **Flexibility** - Can set different limits per promo or leave unlimited
4. **Fair Distribution** - Prevents overuse of limited-time offers
5. **Better Planning** - Track how quickly promos are being consumed

## Example Use Cases
- "First 20 customers get free helmet"
- "Limited to 50 units for this promotion"
- "Flash sale - maximum 100 redemptions"
- "VIP exclusive - only 10 available"

## Technical Notes
- Usage count increments only on successful sales entry save
- Deactivation is automatic and immediate when limit is reached
- Existing promos without limits continue to work normally (unlimited)
- System prevents selection of promos that have reached their limit
- Database changes are backward-compatible with existing data
