# Unclaimed Freebies Flow Diagram

## Complete System Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                     SALES ENTRY PROCESS                              │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                │ User adds unclaimed freebies
                                ▼
                    ┌──────────────────────┐
                    │   salesentry.php     │
                    │  (Frontend Form)     │
                    └──────────────────────┘
                                │
                                │ POST: items + unclaimed_freebies
                                ▼
                    ┌──────────────────────┐
                    │ save_sales_entry.php │
                    │   (Backend API)      │
                    └──────────────────────┘
                                │
                                │ INSERT
                                ▼
                    ┌──────────────────────┐
                    │  Database Tables:    │
                    │  - sales_entry       │
                    │  - sales_entry_items │
                    │  - unclaimed_freebies│ ← Status: 'unclaimed'
                    └──────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                    CLAIM ITEM PROCESS                                │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                │ User searches by invoice number
                                ▼
                    ┌──────────────────────┐
                    │    claimitem.php     │
                    │   (Frontend Page)    │
                    └──────────────────────┘
                                │
                                │ GET: invoice_no
                                ▼
                    ┌────────────────────────────┐
                    │ search_unclaimed_freebies  │
                    │        .php                │
                    │     (Backend API)          │
                    └────────────────────────────┘
                                │
                                │ SELECT
                                ▼
                    ┌──────────────────────┐
                    │  Database Query:     │
                    │  unclaimed_freebies  │
                    │  JOIN sales_entry    │
                    │  WHERE status=       │
                    │   'unclaimed'        │
                    └──────────────────────┘
                                │
                                │ JSON Response
                                ▼
                    ┌──────────────────────┐
                    │  Display Results:    │
                    │  - Unclaimed Items   │
                    │  - Customer Details  │
                    └──────────────────────┘
```

## Data Flow Details

### 1. Sales Entry → Unclaimed Freebies Storage

```javascript
// Frontend (salesentry.php)
const unclaimedFreebies = [
    {
        item_code: 'CASE001',
        description: 'Free Phone Case',
        quantity: 1,
        note: 'Promotional item'
    }
];

// Sent to backend
POST /save_sales_entry.php
{
    invoice_no: '240115-001-00001',
    items: [...],
    unclaimed_freebies: unclaimedFreebies
}
```

```php
// Backend (save_sales_entry.php)
foreach ($data['unclaimed_freebies'] as $freebie) {
    INSERT INTO unclaimed_freebies (
        sales_entry_id,
        invoice_number,
        item_code,
        item_description,
        quantity,
        note,
        branch,
        created_by,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unclaimed');
}
```

### 2. Search → Display Unclaimed Items

```javascript
// Frontend (claimitem.php)
function searchUnclaimedFreebies() {
    const invoiceNo = '240115-001-00001';
    
    fetch('search_unclaimed_freebies.php?invoice_no=' + invoiceNo)
        .then(response => response.json())
        .then(data => {
            displayUnclaimedItems(data.unclaimed_items);
            displayCustomerDetails(data.customer_details);
        });
}
```

```php
// Backend (search_unclaimed_freebies.php)
SELECT 
    uf.*,
    se.first_name,
    se.last_name,
    se.contact_no,
    se.email
FROM unclaimed_freebies uf
LEFT JOIN sales_entry se ON uf.sales_entry_id = se.id
WHERE uf.invoice_number = '$invoice_no' 
  AND uf.status = 'unclaimed';
```

## Database Schema

```sql
┌─────────────────────────────────────────────────────────────┐
│                     sales_entry                             │
├─────────────────────────────────────────────────────────────┤
│ id (PK)                                                     │
│ invoice_no                                                  │
│ first_name, last_name                                       │
│ contact_no, email, address                                  │
│ total_amount, payment_data                                  │
│ branch_code, encoder                                        │
│ created_at                                                  │
└─────────────────────────────────────────────────────────────┘
                        │
                        │ sales_entry_id (FK)
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│                 unclaimed_freebies                          │
├─────────────────────────────────────────────────────────────┤
│ id (PK)                                                     │
│ sales_entry_id (FK) → sales_entry.id                        │
│ invoice_number                                              │
│ item_code                                                   │
│ item_description                                            │
│ quantity                                                    │
│ note                                                        │
│ branch                                                      │
│ created_by                                                  │
│ created_at                                                  │
│ status (ENUM: 'unclaimed', 'claimed')                       │
│                                                             │
│ Indexes:                                                    │
│  - idx_sales_entry (sales_entry_id)                         │
│  - idx_invoice (invoice_number)                             │
│  - idx_status (status)                                      │
└─────────────────────────────────────────────────────────────┘
```

## UI Components

### claimitem.php Layout

```
┌────────────────────────────────────────────────────────────────┐
│                         Header                                 │
│  [☰] MOTOGAM Logo                                    [User]    │
└────────────────────────────────────────────────────────────────┘
│                                                                │
│  Claim Item                                                    │
│                                                                │
│  ┌──────────────────────┐  ┌──────────────────────┐          │
│  │ Search Box           │  │ Customer Details     │          │
│  │ Invoice No: [____]   │  │                      │          │
│  │           [Search]   │  │ Name: John Doe       │          │
│  │                      │  │ Contact: 0912345     │          │
│  │ ┌──────────────────┐ │  │ Email: john@ex.com   │          │
│  │ │ Unclaimed Items  │ │  │ Encoder: Jane S.     │          │
│  │ ├──────────────────┤ │  │                      │          │
│  │ │ Free Phone Case  │ │  └──────────────────────┘          │
│  │ │ Code: CASE001    │ │                                    │
│  │ │ Qty: 1           │ │                                    │
│  │ │ Branch: MOTOGAM  │ │                                    │
│  │ └──────────────────┘ │                                    │
│  └──────────────────────┘                                    │
│                                                                │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ IMEI: [_________]        Accessories: [___________]    │  │
│  │ Description: [____]      Quantity: [__] [Add]         │  │
│  │                                                          │  │
│  │ ┌────────────────────────────────────────────────────┐ │  │
│  │ │ Item Table (to be implemented)                     │ │  │
│  │ └────────────────────────────────────────────────────┘ │  │
│  │                                                          │  │
│  │ Remarks: [__________________]                           │  │
│  │                                    [Save]               │  │
│  └────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────┘
```

## Status Flow

```
┌─────────────┐                    ┌─────────────┐
│  UNCLAIMED  │ ──── Search ────→  │  DISPLAYED  │
│   (Status)  │                    │  in UI      │
└─────────────┘                    └─────────────┘
                                          │
                                          │ (Future)
                                          │ User claims item
                                          ▼
                                   ┌─────────────┐
                                   │   CLAIMED   │
                                   │   (Status)  │
                                   └─────────────┘
```

## API Endpoints

| Endpoint | Method | Purpose | Input | Output |
|----------|--------|---------|-------|--------|
| `search_unclaimed_freebies.php` | GET | Search unclaimed freebies | `invoice_no` | JSON with items & customer |
| `save_sales_entry.php` | POST | Save sales entry with freebies | Full sales data | Success/failure |

## Testing Flow

```
1. Create Sales Entry
   └─→ salesentry.php
       └─→ Add unclaimed freebies
           └─→ Save (generates invoice number)

2. Test Database
   └─→ test_unclaimed_freebies_search.php
       └─→ Check if data saved correctly

3. Search for Items
   └─→ claimitem.php
       └─→ Enter invoice number
           └─→ Click Search
               └─→ View results

4. Verify Display
   └─→ Unclaimed Items panel shows freebies
   └─→ Customer Details panel shows customer info
```

## Key Features

### ✅ Implemented
- Search by invoice number
- Display unclaimed items with details
- Display customer information
- Real-time search with loading indicator
- Enter key support for search
- Error handling for no results
- Responsive card-based layout

### ⏳ To Be Implemented
- Actual claiming process (mark as claimed)
- IMEI/Serial number assignment
- Stock management integration
- Print receipt for claimed items
- History tracking
- Notifications

## Error Handling

```javascript
// Frontend Error Handling
- Empty invoice number → Alert user
- No results found → Display "not found" message
- Network error → Display error message
- Invalid response → Handle gracefully

// Backend Error Handling
- Missing parameters → Return error JSON
- Database error → Return error with message
- No records → Return not_found status
- SQL errors → Logged and returned
```

---

**Version:** 1.0  
**Last Updated:** January 2024  
**Status:** Search & Display Complete ✅
