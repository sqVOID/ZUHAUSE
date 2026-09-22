# Purchase Order System - Data Flow Architecture

## Complete System Architecture

```
┌──────────────────────────────────────────────────────────────────────┐
│                         USER INTERFACE LAYER                          │
└──────────────────────────────────────────────────────────────────────┘

┌────────────────────────────┐         ┌────────────────────────────┐
│  createpurchaseorder.php   │         │  purchaseorder-details.php │
│                            │         │                            │
│  • Supplier Selection      │         │  • PO Header Display       │
│  • Terms & Payment Date    │         │  • Items Table             │
│  • Items Entry             │         │  • Allocations Table       │
│  • Real-time Calculations  │         │  • Action Buttons          │
│  • Validation              │         │  • Status Indicators       │
└────────────┬───────────────┘         └────────────┬───────────────┘
             │                                      │
             │ AJAX POST                            │ GET Request
             │ (JSON)                               │ (?id=XX)
             ▼                                      ▼

┌──────────────────────────────────────────────────────────────────────┐
│                      BACKEND PROCESSING LAYER                         │
└──────────────────────────────────────────────────────────────────────┘

┌────────────────────────────┐         ┌────────────────────────────┐
│ save_purchase_order_       │         │  purchaseorder-details.php │
│      simple.php            │         │     (PHP Section)          │
│                            │         │                            │
│  1. Validate Input         │         │  1. Validate PO ID         │
│  2. Generate PO Number     │         │  2. Fetch PO Data          │
│  3. Calculate Totals       │         │  3. Fetch Items            │
│  4. Insert PO Header       │         │  4. Fetch Allocations      │
│  5. Insert PO Items        │         │  5. Calculate Summaries    │
│  6. Return Success/Error   │         │  6. Render HTML            │
└────────────┬───────────────┘         └────────────┬───────────────┘
             │                                      │
             ▼                                      ▼

┌──────────────────────────────────────────────────────────────────────┐
│                          DATABASE LAYER                               │
└──────────────────────────────────────────────────────────────────────┘

       ┌─────────────────────────────────────────────────┐
       │         MySQL Database (ZUHAUSE)                │
       └─────────────────────────────────────────────────┘

┌──────────────────────┐  ┌──────────────────────┐  ┌──────────────────────┐
│  purchase_orders     │  │ purchase_order_items │  │purchase_order_       │
│                      │  │                      │  │   allocations        │
├──────────────────────┤  ├──────────────────────┤  ├──────────────────────┤
│ • id (PK)            │  │ • id (PK)            │  │ • id (PK)            │
│ • po_number          │◄─┤ • po_id (FK)         │◄─┤ • po_id (FK)         │
│ • supplier_company   │  │ • po_number          │  │ • po_number          │
│ • terms              │  │ • item_no            │  │ • branch_name        │
│ • payment_due_date   │  │ • family_code        │  │ • branch_code        │
│ • remarks            │  │ • quantity           │  │ • family_code        │
│ • po_date            │  │ • cost               │  │ • quantity           │
│ • total_items        │  │ • total              │  │ • received_qty       │
│ • total_qty          │  │ • allocated_quantity │  │ • cost               │
│ • total_cost         │  │ • received_qty       │  │ • status             │
│ • status             │  └──────────────────────┘  │ • created_at         │
│ • created_by         │                            │ • updated_at         │
│ • created_by_branch  │                            └──────────────────────┘
│ • created_at         │                                      
└──────────────────────┘                                      
```

## Detailed Data Flow

### 1. CREATE PURCHASE ORDER FLOW

```
USER ACTION                    FRONTEND                   BACKEND                    DATABASE
────────────                   ────────                   ───────                    ────────

1. Fill Form Fields
   ├─ Supplier              → Store in variables
   ├─ Terms                 → Calculate due date
   ├─ Payment Date          → Real-time validation
   └─ Remarks               → Format currency
                                     │
2. Add Items                         │
   ├─ Search Family Code    → AJAX to              → Query family_codes
   ├─ Enter Quantity          search_familycode.php   table
   ├─ Enter Cost            → Calculate totals
   └─ Validate              → Update summary
                                     │
3. Click Submit             → JavaScript validation
                            → Build JSON payload
                                     │
                            ─────────▼──────────────────────────────────────
                                                    
                                  submitPO() Function
                                  │
                                  ├─ Validate all fields
                                  ├─ Validate family codes (AJAX)
                                  ├─ Prepare FormData
                                  └─ POST to save_purchase_order_simple.php
                                                    │
                                  ─────────────────▼──────────
                                                    
                                  save_purchase_order_simple.php
                                  │
                                  ├─ Generate PO Number
                                  │  (PO-2026-001 format)
                                  │
                                  ├─ Validate inputs    → Query validation
                                  │  • Check supplier      rules
                                  │  • Check family codes
                                  │  • Check quantities
                                  │
                                  ├─ Calculate totals
                                  │  • Total items
                                  │  • Total quantity
                                  │  • Total cost
                                  │
                                  ├─ INSERT INTO      → purchase_orders
                                  │  purchase_orders     table
                                  │  (Returns po_id)
                                  │
                                  ├─ For each item:
                                  │  INSERT INTO      → purchase_order_items
                                  │  purchase_order_     table
                                  │  items
                                  │
                                  └─ Return JSON
                                     {success, po_number, redirect}
                                                    │
                            ◄───────────────────────┘
                            │
                            ├─ Show success alert
                            └─ Redirect to purchaseorder.php
```

### 2. VIEW PURCHASE ORDER DETAILS FLOW

```
USER ACTION                    FRONTEND                   BACKEND                    DATABASE
────────────                   ────────                   ───────                    ────────

1. Click on PO from list
   │
   └─ Navigate to          → URL: purchaseorder-
      Details Page            details.php?id=123
                                     │
                            ─────────▼──────────────────────────────────────
                                                    
                                  PHP Processing
                                  │
                                  ├─ Get PO ID from $_GET['id']
                                  │
                                  ├─ Validate PO ID
                                  │  (Redirect if invalid)
                                  │
                                  ├─ Query 1:         → SELECT * FROM
                                  │  Fetch PO Data      purchase_orders
                                  │                     WHERE id = ?
                                  │  Returns:
                                  │  • po_number
                                  │  • supplier_company
                                  │  • terms
                                  │  • payment_due_date
                                  │  • total_cost
                                  │  • status
                                  │  • created_by
                                  │
                                  ├─ Query 2:         → SELECT poi.*,
                                  │  Fetch Items        COALESCE(
                                  │                     allocated_quantity, 0)
                                  │                     FROM purchase_order_items
                                  │                     WHERE po_id = ?
                                  │  Returns:
                                  │  • family_code
                                  │  • quantity
                                  │  • cost
                                  │  • allocated_quantity
                                  │
                                  ├─ Query 3:         → SELECT branch_name,
                                  │  Fetch Branch       SUM(quantity),
                                  │  Allocations        SUM(cost * quantity)
                                  │                     FROM purchase_order_
                                  │                     allocations
                                  │                     WHERE po_id = ?
                                  │                     GROUP BY branch_name
                                  │  Returns:
                                  │  • branch_name
                                  │  • total_allocation_qty
                                  │  • total_cost_allocated
                                  │  • received_qty
                                  │  • status (calculated)
                                  │
                                  └─ Render HTML with data
                                                    │
                            ◄───────────────────────┘
                            │
2. View Displays:           │
   ├─ PO Information Table  │
   │  • PO Number          ◄┤─ From purchase_orders
   │  • Supplier           ◄┤
   │  • Date, Terms, Cost  ◄┤
   │  • Status             ◄┤
   │
   ├─ Items Table           │
   │  • Family Code        ◄┤─ From purchase_order_items
   │  • Cost per unit      ◄┤
   │  • Quantity           ◄┤
   │  • Allocated Qty      ◄┤
   │
   └─ Allocations Table     │
      • Branch Name        ◄┤─ From purchase_order_allocations
      • Cost Allocated     ◄┤   (if exists)
      • Quantity           ◄┤
      • Received Qty       ◄┤
      • Status             ◄┤
```

## Data Relationships

```
┌─────────────────────┐
│  purchase_orders    │
│  ─────────────────  │
│  id = 123           │
│  po_number =        │
│    "PO-2026-001"    │
└──────────┬──────────┘
           │
           │ ONE-TO-MANY
           │
           ├────────────────────────────────────────┐
           │                                        │
           ▼                                        ▼
┌──────────────────────┐              ┌──────────────────────────┐
│ purchase_order_items │              │ purchase_order_          │
│ ──────────────────── │              │    allocations           │
│ id = 1               │              │ ──────────────────────── │
│ po_id = 123          │              │ id = 1                   │
│ family_code = FC-001 │──────┐       │ po_id = 123              │
│ quantity = 10        │      │       │ branch_name = Main       │
│ cost = 500.00        │      │       │ family_code = FC-001     │
│ allocated_qty = 5    │      │       │ quantity = 5             │
└──────────────────────┘      │       │ received_qty = 0         │
                              │       └──────────────────────────┘
┌──────────────────────┐      │       ┌──────────────────────────┐
│ purchase_order_items │      │       │ purchase_order_          │
│ ──────────────────── │      │       │    allocations           │
│ id = 2               │      │       │ ──────────────────────── │
│ po_id = 123          │      │       │ id = 2                   │
│ family_code = FC-002 │      │       │ po_id = 123              │
│ quantity = 15        │      ├──────►│ branch_name = Branch 2   │
│ cost = 800.00        │      │       │ family_code = FC-001     │
│ allocated_qty = 10   │      │       │ quantity = 3             │
└──────────────────────┘      │       │ received_qty = 0         │
                              │       └──────────────────────────┘
┌──────────────────────┐      │       ┌──────────────────────────┐
│ purchase_order_items │      │       │ purchase_order_          │
│ ──────────────────── │      │       │    allocations           │
│ id = 3               │      │       │ ──────────────────────── │
│ po_id = 123          │      │       │ id = 3                   │
│ family_code = FC-003 │      │       │ po_id = 123              │
│ quantity = 20        │      └──────►│ branch_name = Branch 3   │
│ cost = 350.00        │              │ family_code = FC-001     │
│ allocated_qty = 0    │              │ quantity = 2             │
└──────────────────────┘              │ received_qty = 0         │
                                      └──────────────────────────┘

RELATIONSHIP SUMMARY:
• 1 PO → Many Items (purchase_order_items)
• 1 PO → Many Allocations (purchase_order_allocations)
• 1 Item → Many Allocations (distributed across branches)
• allocated_quantity = SUM of allocations for that item
```

## Key Data Transformations

### 1. PO Number Generation
```
INPUT:  User creates PO
LOGIC:  Get last PO number → Extract sequence → Increment
OUTPUT: PO-2026-001, PO-2026-002, etc.
```

### 2. Total Calculations
```
INPUT:  Items with quantity and cost
LOGIC:  
  total_items = COUNT(items)
  total_qty = SUM(quantity)
  total_cost = SUM(quantity × cost)
OUTPUT: Aggregated totals in purchase_orders table
```

### 3. Allocation Status
```
INPUT:  received_qty and quantity from allocations
LOGIC:  
  IF received_qty = 0 THEN 'Waiting'
  ELSE IF received_qty >= quantity THEN 'Complete'
  ELSE 'Incomplete'
OUTPUT: Dynamic status per branch
```

### 4. Terms Display
```
INPUT:  terms field (30, 60, 90, 'cod')
LOGIC:  
  IF terms = 'cod' THEN 'Cash on Delivery'
  ELSE terms + ' Days'
OUTPUT: User-friendly term description
```

## Session Data Usage

```
SESSION VARIABLES               USED IN                    PURPOSE
─────────────────               ───────                    ───────
$_SESSION['fullname']           save_purchase_order_      Track who created PO
$_SESSION['username']           simple.php                (fallback)
$_SESSION['user_branch']                                  Track origin branch
$_SESSION['system_level']                                 Permission level
```

## Security Measures

```
LAYER                  PROTECTION                          IMPLEMENTATION
─────                  ──────────                          ──────────────
Input Validation       • Required field checks             JavaScript + PHP
                       • Data type validation
                       • Range checks

SQL Injection          • Prepared statements               mysqli_real_escape_string
                       • Escaped strings                   Parameterized queries
                       • Validated inputs

XSS Prevention         • Output escaping                   htmlspecialchars()
                       • HTML entity encoding              on all user data

Session Security       • Session validation                session_check.php
                       • Timeout handling                  (included in all pages)
                       • Secure cookies

Business Logic         • Duplicate prevention              Family code uniqueness
                       • Family code validation            Database lookup
                       • Quantity > 0 checks               Frontend + Backend
```

## Performance Considerations

```
OPTIMIZATION                    IMPLEMENTATION
────────────                    ──────────────
Database Indexes                • PRIMARY KEY on id columns
                                • INDEX on po_number
                                • INDEX on family_code
                                • FOREIGN KEY constraints

Query Efficiency                • SELECT only needed columns
                                • JOIN optimization
                                • Proper WHERE clauses
                                • LIMIT usage

Frontend Performance            • AJAX for searches
                                • Real-time validation
                                • Debouncing on search
                                • Local calculations

Caching Strategy                • Session caching for user data
                                • Browser caching for static assets
                                • Query result caching (optional)
```

## Error Handling Flow

```
ERROR SOURCE              HANDLING                         USER FEEDBACK
────────────              ────────                         ─────────────
Invalid Input             Validation before submit         Alert messages
Missing Required          Form validation                  Highlighted fields
Database Error            Try-catch blocks                 Generic error message
Network Error             AJAX error handling              Retry option
Invalid PO ID             Redirect to listing              (Silent redirect)
Session Expired           session_check.php                Redirect to login
Permission Denied         Check system_level               Access denied page
```

This architecture ensures:
✅ Data integrity across all tables
✅ Real-time updates and calculations
✅ Secure data handling
✅ Scalable structure for future features
✅ Clear separation of concerns
✅ Maintainable codebase
