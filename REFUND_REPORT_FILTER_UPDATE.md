# Refund Report Filter Button Update

## Summary
Updated `refundreport.php` to include a filter button and dynamic data loading, matching the pattern used in `voidsalesreport.php` and `stocktransferreport.php`.

## Files Modified/Created

### 1. **refundreport.php** (Modified)
   - Updated search bar to include a green "FILTER" button
   - Changed "PREVIEW ALL" button styling to match other reports
   - Updated tbody to show "SELECT A FILTER TO DISPLAY THE DATA" message initially
   - Modified JavaScript to support both live filtering and button-click data loading
   - Added validation for branch selection (admin users only)
   - Updated responsive CSS for mobile devices

### 2. **fetch_refund_data.php** (Created)
   - New backend file to handle AJAX data requests
   - Supports date range filtering
   - Supports branch filtering for different user levels:
     - Super-Admin: Can view all branches
     - Sub-admin: Can view assigned branches only
     - Regular users: Can view their branch only
   - Returns JSON response with refund data

## Key Features Added

### Filter Button Functionality
- **Green FILTER button** with icon (matches other reports)
- Validates branch selection before loading data (admin users only)
- Fetches data from server via AJAX
- Shows "SELECT A FILTER TO DISPLAY THE DATA" initially

### Responsive Design
- Filter button becomes icon-only on mobile (510px breakpoint)
- All buttons adapt to screen size
- Maintains functionality across all devices

### User Level Support
- **Super-Admin**: Must select a branch (or "All Branches") to filter
- **Sub-admin**: Must select from assigned branches
- **Regular Users**: Automatically loads their branch data without branch selector

## How It Works

1. **Initial Load**: Shows "SELECT A FILTER TO DISPLAY THE DATA" message
2. **Click FILTER Button**: 
   - Validates branch selection (if applicable)
   - Calls `loadRefundData()` function
   - Fetches data from `fetch_refund_data.php` via AJAX
   - Populates table with results
3. **Live Filtering**: 
   - Search input filters loaded data in real-time
   - Date range filters work on loaded data
   - Branch dropdown filters work on loaded data

## JavaScript Functions

### `filterTable(isButtonClick)`
- `isButtonClick = false`: Live client-side filtering
- `isButtonClick = true`: Validates and triggers server data load

### `loadRefundData()`
- Builds query parameters from filters
- Fetches data via AJAX from `fetch_refund_data.php`
- Updates table with results
- Handles pagination

### `previewAllRefunds()`
- Opens print preview with current filters applied

## CSS Classes Added

```css
.btn-filter {
    padding: 9px 20px;
    background-color: #4caf50;  /* Green */
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    margin-left: 10px;
}

.btn-preview-all {
    padding: 8px 20px;
    background-color: #333333;  /* Dark gray */
    color: white;
    border: none;
    border-radius: 15px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    margin-left: 15px;
}
```

## Testing Checklist

- [ ] Super-Admin can select and filter by branch
- [ ] Super-Admin can filter "All Branches"
- [ ] Sub-admin can only see assigned branches
- [ ] Regular users can click FILTER without branch selector
- [ ] Date range filtering works correctly
- [ ] Search box filters loaded data
- [ ] Pagination works with filtered results
- [ ] "PREVIEW ALL" button opens print preview
- [ ] Responsive design works on mobile devices
- [ ] "SELECT A FILTER TO DISPLAY THE DATA" shows initially

## Date: July 17, 2026
