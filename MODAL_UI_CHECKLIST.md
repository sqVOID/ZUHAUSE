# Preorder Modal vs Invoice Modal - UI Comparison Checklist

## ✅ CSS Styling - MATCHES

### Modal Container
- ✅ `.modal` - display: none, fixed position, z-index: 10000, rgba(0,0,0,0.6) background
- ✅ `.modal-content` - 3% margin, 90% width, max-width 1000px, border-radius 8px
- ✅ Animation - slideDown 0.3s ease-out
- ✅ Box shadow - 0 4px 20px rgba(0,0,0,0.3)

### Modal Header
- ✅ Background - #000000ff (black)
- ✅ Padding - 20px 25px
- ✅ Border radius - 8px 8px 0 0
- ✅ H2 font-size - 20px, font-weight 600
- ✅ Close button - 32px, white color, hover #ffcccc

### Modal Body
- ✅ Padding - 0
- ✅ No max-height restriction (scrolls naturally)

### Inner Content Div
- ✅ max-height: 100vh
- ✅ overflow-y: auto
- ✅ padding: 20px

## ✅ Content Structure - MATCHES

### Header Section
- ✅ Title format - "Preorder Details: [NUMBER]" vs "Invoice Details: [NUMBER]"
- ✅ Border bottom - 2px solid #acacacff
- ✅ Margins - 0 0 20px 0
- ✅ Font size - 20px

### Two-Column Grid (Customer/Order Info)
- ✅ Grid layout - 1fr 1fr
- ✅ Gap - 20px
- ✅ Margin bottom - 25px
- ✅ Card styling - 2px solid #acacacff, border-radius 8px, padding 15px, background #f9f9f9
- ✅ H3 styling - 16px, color #1E455D, margin 0 0 12px 0
- ✅ Table font-size - 14px
- ✅ Cell padding - 5px 10px 5px 0

### Items Table
- ✅ Card styling - Same as above
- ✅ Margin bottom - 20px
- ✅ Table border-collapse - collapse
- ✅ Header background - #f5f5f5 (light gray)
- ✅ Header border - 1px solid #acacacff
- ✅ Header padding - 10px
- ✅ Cell padding - 8px
- ✅ Font sizes - 14px
- ✅ Overall amount row - background #F5EDE8, bold, border-top 2px solid #1E455D

### Payment Section
- ✅ Same card styling
- ✅ Same table styling
- ✅ Same colors and spacing

## ✅ Colors - MATCHES

### Status Colors
- ✅ Pending - #ff9800 (orange)
- ✅ Fully Paid - #4caf50 (green)
- ✅ Claimed - #2196f3 (blue)
- ✅ Cancelled - #f44336 (red)
- ✅ Partial - #ff6b00 (dark orange)

### UI Colors
- ✅ Border - #acacacff
- ✅ Background - #f9f9f9
- ✅ Headers - #1E455D (navy)
- ✅ Text - #1a1a1a, #333, #000000

## ✅ Print Styles - MATCHES

- ✅ Modal hidden - display: none !important
- ✅ Links styled - color #000000, no underline, bold

## ✅ JavaScript Functions - MATCHES

- ✅ `viewPreorderDetails()` - Opens modal, fetches data
- ✅ `showPreorderModal()` - Displays content
- ✅ `closePreorderModal()` - Closes modal
- ✅ Click outside to close - window.onclick handler

## ✅ Hyperlinks in Table - MATCHES

- ✅ Color - #0066cc (blue)
- ✅ Text decoration - underline
- ✅ Cursor - pointer
- ✅ onclick handler - returns false to prevent navigation
- ✅ Print style - removes link styling

---

## Summary

✅ **ALL UI ELEMENTS MATCH** between preorderModal and invoiceModal!

The only intentional differences are:
1. Content-specific data (preorder vs invoice fields)
2. Field names (Order Information vs Sale Information)
3. Backend API endpoint (get_preorder_details.php vs get_invoice_details.php)

Everything else is identical in styling, structure, and behavior.
