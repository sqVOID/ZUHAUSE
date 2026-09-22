# Skip Booklet Number Feature

## Overview
The Skip Booklet Number feature allows you to skip/void a specific booklet/invoice number and automatically advance to the next number. This is useful when:
- An invoice is damaged or misprinted
- A transaction needs to be voided
- A booklet number needs to be reserved or skipped for any reason

## Example Usage
If your current booklet number is `0001-043`, and you skip it:
- `0001-043` will be marked as skipped/voided
- The next active number becomes `0002-043`
- All skipped numbers are logged with reason and user information

## Installation Steps

### 1. Run Setup Script
First, you need to create the database table for tracking skipped numbers:

```
http://localhost/MOTOGAM/setup_skipped_booklet_table.php
```

This will create the `skipped_booklet_numbers` table in your database.

### 2. Access the Skip Booklet Page
After setup, you can access the feature at:

```
http://localhost/MOTOGAM/skipbookletno.php
```

## How to Use

1. **Select Booklet**: Choose the booklet/branch/page type you want to skip from the dropdown
2. **Review Numbers**: 
   - Current Number: The number that will be skipped
   - Next Number: The number that will become active
3. **Enter Reason**: Provide a clear reason for skipping (required for audit trail)
4. **Confirm**: Click "Skip Booklet Number" and confirm the action

## Features

### Skip Booklet Form
- Dropdown list of all active booklets across branches
- Real-time preview of current and next numbers
- Required reason field for audit compliance
- Confirmation dialog to prevent accidental skips

### Skip History Table
- Shows last 50 skipped booklet numbers
- Displays branch, page type, skipped number, reason, user, and timestamp
- Helps maintain audit trail and accountability

## Supported Formats

The feature automatically handles different booklet formats:

1. **Numeric Format**: `0001-043` → `0002-043`
2. **Date Suffix Format**: `07-06-2026-SD10` → `07-06-2026-SD11`
3. **Custom Format**: Automatically increments the last numeric part

## Database Schema

```sql
skipped_booklet_numbers table:
- id: Primary key
- booklet_id: References booklet_numbers.id
- branch_code: Branch code
- skipped_number: The number that was skipped
- reason: Reason for skipping
- skipped_by: Username who performed the skip
- skipped_at: Timestamp when skipped
```

## Security & Audit

- All skip actions are logged permanently
- User information is captured automatically
- Reasons are required and stored
- Actions cannot be reversed (by design, for audit compliance)
- Only authorized users with access to the page can skip numbers

## Integration with Existing System

The feature integrates with:
- `bookletnoreg.php`: Uses existing booklet_numbers table
- Branch management system
- User session management
- Existing sidebar and header components

## Notes

⚠️ **Important**: 
- Skipped numbers cannot be "unskipped" - this is intentional for audit trail integrity
- The current_number in booklet_numbers table is automatically updated
- Make sure to provide clear reasons for all skips
- Review the skip history regularly for accountability

## Troubleshooting

If you encounter issues:

1. **Table doesn't exist**: Run `setup_skipped_booklet_table.php`
2. **Foreign key error**: The table will still work, foreign key is optional
3. **No booklets showing**: Make sure you have active booklets in `booklet_numbers` table
4. **Permission denied**: Check your user session and permissions

## Support

For issues or questions, refer to the database administrator or system maintainer.
