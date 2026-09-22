# 🚨 COMPLETE SCHEMA ANALYSIS - ALL SQL FILES

**Date:** July 21, 2026  
**Status:** CRITICAL INCOMPATIBILITY CONFIRMED  
**Analysis:** COMPLETE - All versions verified

---

## EXECUTIVE SUMMARY

After thorough analysis, **ONLY `motogam_management_orig.sql` is compatible** with your application.

**All "old" versions (old, oldv2, oldv3, oldv4) have CRITICAL SCHEMA MISMATCHES** that will cause application failures.

---

## 🔴 CRITICAL FINDING: MULTIPLE TABLE MISMATCHES

### Two Major Tables Have Wrong Schema in Old Versions:

1. ❌ **`preorders` table** - Completely different column structure
2. ❌ **`preorder_items` table** - Completely different column structure

These are not just "missing columns" - they are **FUNDAMENTALLY DIFFERENT SCHEMAS** from different development branches or older prototypes.

---

## DETAILED COMPARISON: `preorders` TABLE

### ✅ Original Schema (19 columns - CORRECT)

```sql
CREATE TABLE `preorders` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,              -- ✅ REQUIRED
  `last_name` varchar(100) NOT NULL,               -- ✅ REQUIRED
  `address` varchar(255) NOT NULL,                 -- ✅ REQUIRED
  `contact_no` varchar(50) NOT NULL,               -- ✅ REQUIRED
  `email` varchar(100) DEFAULT '',                 -- ✅ REQUIRED
  `assisted_by` varchar(100) NOT NULL,
  `remarks` text DEFAULT NULL,
  `total_qty` int(11) NOT NULL DEFAULT 0,          -- ✅ REQUIRED
  `discount` decimal(12,2) DEFAULT 0.00,           -- ✅ REQUIRED
  `total_amount` decimal(12,2) NOT NULL,
  `payment_data` text DEFAULT NULL,                -- ✅ REQUIRED (JSON)
  `branch_code` varchar(10) NOT NULL,              -- ✅ REQUIRED
  `encoder` varchar(150) DEFAULT NULL,             -- ✅ REQUIRED
  `status` varchar(50) DEFAULT 'pending',
  `claimed_invoice_no` varchar(50) DEFAULT NULL,   -- ✅ REQUIRED
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB;
```

### ❌ OldV3/V4 Schema (28 columns - WRONG)

```sql
CREATE TABLE `preorders` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `branch` varchar(100) NOT NULL,                  -- ❌ Wrong name (should be branch_code)
  `customer_name` varchar(255) NOT NULL,           -- ❌ Wrong (should be first_name + last_name)
  `customer_contact` varchar(20) DEFAULT NULL,     -- ❌ Wrong name (should be contact_no)
  `customer_address` text DEFAULT NULL,            -- ❌ Wrong name (should be address)
  `down_payment` decimal(10,2) DEFAULT 0.00,       -- ❌ Not in original
  `remaining_balance` decimal(10,2) DEFAULT 0.00,  -- ❌ Not in original
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','completed','cancelled'),
  `payment_method` varchar(50) DEFAULT NULL,       -- ❌ Not in original
  `payment_mode` varchar(50) DEFAULT NULL,         -- ❌ Not in original
  `dealer_code` varchar(50) DEFAULT NULL,          -- ❌ Not in original
  `prepared_by` varchar(100) DEFAULT NULL,         -- ❌ Not in original
  `preorder_date` date DEFAULT NULL,               -- ❌ Not in original
  `target_release_date` date DEFAULT NULL,         -- ❌ Not in original
  `actual_release_date` date DEFAULT NULL,         -- ❌ Not in original
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NOT NULL,
  `claimed_date` datetime DEFAULT NULL,            -- ❌ Not in original
  `claimed_by` varchar(255) DEFAULT NULL,          -- ❌ Not in original
  `booklet_no` varchar(50) DEFAULT NULL,           -- ❌ Not in original
  `freebies` text DEFAULT NULL,                    -- ❌ Not in original
  `promoter` varchar(255) DEFAULT NULL,            -- ❌ Not in original
  `terminal_id` varchar(255) DEFAULT NULL,         -- ❌ Not in original
  `promo_items` text DEFAULT NULL,                 -- ❌ Not in original
  `assisted_by` varchar(255) DEFAULT NULL
) ENGINE=InnoDB;
```

**Missing in OldV3/V4:** first_name, last_name, address, contact_no, email, total_qty, discount, payment_data, branch_code, encoder, claimed_invoice_no

---

## DETAILED COMPARISON: `preorder_items` TABLE

### ✅ Original Schema (15 columns - CORRECT)

```sql
CREATE TABLE `preorder_items` (
  `id` int(11) NOT NULL,
  `preorder_id` int(11) NOT NULL,
  `family_code` varchar(100) DEFAULT NULL,         -- ✅ REQUIRED
  `item_description` varchar(255) DEFAULT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `imei` varchar(100) DEFAULT NULL,                -- ✅ REQUIRED
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,     -- ✅ REQUIRED
  `total_payment` decimal(10,2) DEFAULT NULL,      -- ✅ REQUIRED
  `amount_paid` decimal(10,2) DEFAULT 0.00,        -- ✅ REQUIRED
  `payment_method` varchar(50) DEFAULT NULL,       -- ✅ REQUIRED
  `status` varchar(20) DEFAULT 'pending',          -- ✅ REQUIRED
  `dr_number` varchar(100) DEFAULT NULL,           -- ✅ REQUIRED
  `claimed_at` timestamp NULL DEFAULT NULL,        -- ✅ REQUIRED
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB;
```

### ❌ OldV3/V4 Schema (17 columns - WRONG)

```sql
CREATE TABLE `preorder_items` (
  `id` int(11) NOT NULL,
  `preorder_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `item_description` text DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,               -- ❌ Not in original
  `model` varchar(100) DEFAULT NULL,               -- ❌ Not in original
  `color` varchar(100) DEFAULT NULL,               -- ❌ Not in original
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) DEFAULT 0.00,         -- ❌ Wrong name (should be price)
  `discount` decimal(10,2) DEFAULT 0.00,           -- ❌ Not in original
  `total_price` decimal(10,2) DEFAULT 0.00,        -- ❌ Wrong name (should be total_payment)
  `imei_number` varchar(100) DEFAULT NULL,         -- ❌ Wrong name (should be imei)
  `engine_number` varchar(100) DEFAULT NULL,       -- ❌ Not in original
  `chassis_number` varchar(100) DEFAULT NULL,      -- ❌ Not in original
  `conduction_sticker` varchar(100) DEFAULT NULL,  -- ❌ Not in original
  `remarks` text DEFAULT NULL,                     -- ❌ Not in original
  `family_code` varchar(100) DEFAULT NULL
) ENGINE=InnoDB;
```

**Missing in OldV3/V4:** price, total_payment, amount_paid, payment_method, status, dr_number, claimed_at

**Column Name Differences:**
- `unit_price` vs `price`
- `total_price` vs `total_payment`
- `imei_number` vs `imei`

---

## FILE-BY-FILE STATUS

### ❌ motogam_management_old.sql
**Status:** SEVERELY OUTDATED  
**Issues:**
- Missing 4 tables: booklet_invoice_usage, booklet_numbers, preorders, preorder_items
- Missing 2 columns in accounts table
- **Verdict:** ❌ **COMPLETELY INCOMPATIBLE**

---

### ❌ motogam_management_oldv2.sql
**Status:** NEARLY COMPLETE BUT MISSING CRITICAL TABLES  
**Issues:**
- Missing 3 tables: preorders, preorder_items, skip_receipt_requests
- All other 36 tables present
- **Verdict:** ❌ **INCOMPATIBLE - Missing preorder functionality**

---

### ❌ motogam_management_oldv3.sql
**Status:** ALL 39 TABLES PRESENT BUT WRONG SCHEMA  
**Issues:**
- ❌ Wrong `preorders` table structure (28 columns vs 19)
- ❌ Wrong `preorder_items` table structure (17 columns vs 15)
- Different column names and data types
- **Verdict:** ❌ **INCOMPATIBLE - Will cause runtime errors**

---

### ❌ motogam_management_oldv4.sql
**Status:** IDENTICAL TO OLDV3 - WRONG SCHEMA  
**Issues:**
- ❌ Same wrong `preorders` structure as oldv3
- ❌ Same wrong `preorder_items` structure as oldv3
- **Verdict:** ❌ **INCOMPATIBLE - Will cause runtime errors**

---

### ✅ motogam_management_orig.sql
**Status:** ✅ **CORRECT AND CURRENT**  
**Features:**
- All 39 tables present
- Correct preorders schema (19 columns)
- Correct preorder_items schema (15 columns)
- Matches application queries exactly
- **Verdict:** ✅ **USE THIS ONE ONLY**

---

## APPLICATION ERRORS YOU'LL SEE WITH OLD VERSIONS

### Using OldV3 or OldV4:

**Error 1 - Preorders Query:**
```
Error: Unknown column 'p.first_name' in 'field list'
Error: Unknown column 'p.last_name' in 'field list'
Error: Unknown column 'p.total_qty' in 'field list'
Error: Unknown column 'p.discount' in 'field list'
Error: Unknown column 'p.payment_data' in 'field list'
Error: Unknown column 'p.branch_code' in 'field list'
Error: Unknown column 'p.encoder' in 'field list'
```

**Error 2 - Preorder Items Query:**
```
Error: Unknown column 'pi.price' in 'field list'
Error: Unknown column 'pi.total_payment' in 'field list'
Error: Unknown column 'pi.amount_paid' in 'field list'
Error: Unknown column 'pi.status' in 'field list'
Error: Unknown column 'pi.dr_number' in 'field list'
Error: Unknown column 'pi.claimed_at' in 'field list'
```

### Using OldV2:
```
Error: Table 'preorders' doesn't exist
Error: Table 'preorder_items' doesn't exist
Error: Table 'skip_receipt_requests' doesn't exist
```

### Using Old:
```
Error: Table 'preorders' doesn't exist
Error: Table 'booklet_numbers' doesn't exist
Error: Table 'booklet_invoice_usage' doesn't exist
```

---

## ROOT CAUSE ANALYSIS

The old versions appear to be from:

1. **Different Development Branch**
   - Parallel development that diverged from main
   - Different design decisions made
   - Never merged back to main

2. **Older Prototype/Beta Version**
   - Early design with different field structure
   - Application was redesigned but old SQL kept
   - Database schema evolved but old exports remained

3. **Different Application Version**
   - Possibly from different client/deployment
   - Customized version with different requirements
   - Not meant for your current application

---

## FIELD NAMING PATTERNS SUGGEST DESIGN CHANGES

### Old Version Philosophy:
- Single `customer_name` field
- Separate payment fields (`down_payment`, `remaining_balance`)
- Generic `branch` field
- More motorcycle-specific fields (engine_number, chassis_number)

### Current Version Philosophy:
- Normalized `first_name` + `last_name`
- Flexible JSON `payment_data`
- Standardized `branch_code` (FK to branches table)
- Generic item tracking with `imei` only

**This suggests a complete redesign/refactoring happened**

---

## MIGRATION FEASIBILITY

### Can You Migrate from Old to Current?

**From Old/OldV2:** ❌ **NOT RECOMMENDED**
- Missing too many tables
- Would require complete database rebuild
- Easier to start fresh

**From OldV3/V4:** ⚠️ **POSSIBLE BUT COMPLEX**

Would require:

1. **Data Extraction:**
   ```sql
   -- Extract existing preorders data
   SELECT customer_name, customer_contact, customer_address, 
          branch, total_amount, status, created_at
   FROM preorders;
   ```

2. **Field Mapping Logic:**
   - Split `customer_name` → `first_name` + `last_name`
   - Rename `customer_contact` → `contact_no`
   - Rename `customer_address` → `address`
   - Map `branch` → `branch_code` (lookup from branches table)
   - Default `email` to empty string
   - Set `total_qty` to 0 or calculate from items
   - Set `discount` to 0.00
   - Convert `payment_method` + `payment_mode` → JSON `payment_data`
   - Set `encoder` to prepared_by or NULL
   - Map `claimed_invoice_no` from claimed_date logic

3. **Drop and Recreate:**
   ```sql
   DROP TABLE IF EXISTS preorder_items;
   DROP TABLE IF EXISTS preorders;
   -- Recreate with correct structure
   -- Import converted data
   ```

**Estimated Effort:** 4-8 hours  
**Risk Level:** HIGH (data loss if mapping fails)  
**Recommendation:** Only if you have critical data in oldv3/v4

---

## RECOMMENDED ACTIONS

### ✅ IMMEDIATE ACTIONS:

1. **Use Only Original File**
   ```bash
   # For all new deployments
   mysql -u root -p motogam_management < motogam_management_orig.sql
   ```

2. **Archive Old Files**
   ```bash
   mkdir archive_incompatible
   move motogam_management_old*.sql archive_incompatible/
   ```

3. **Document Decision**
   - Add note in your deployment docs
   - Update database setup instructions
   - Warn team members about incompatible files

4. **Standardize Exports**
   ```bash
   # Always export from production database
   mysqldump -u root -p motogam_management > motogam_management_YYYYMMDD.sql
   # Use date-stamped filenames
   ```

### ⚠️ IF YOU HAVE DATA IN OLDV3/V4:

**Option 1: Start Fresh (Recommended)**
1. Export any critical custom data manually
2. Drop database
3. Import motogam_management_orig.sql
4. Re-enter necessary data through application

**Option 2: Migrate Data (Complex)**
1. Create data extraction scripts
2. Write field mapping logic (split names, convert formats)
3. Validate all mappings
4. Test on copy of database first
5. Execute migration with backups

**Recommendation:** Option 1 unless you have significant production data

---

## VERIFICATION CHECKLIST

Before using ANY SQL file, verify:

✅ Check table count: `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'motogam_management';`  
   Expected: 39 tables

✅ Check preorders columns:
```sql
DESCRIBE preorders;
```
Expected: 19 columns including first_name, last_name, payment_data, branch_code

✅ Check preorder_items columns:
```sql
DESCRIBE preorder_items;
```
Expected: 15 columns including price, total_payment, amount_paid, status, dr_number

✅ Test application queries:
```sql
SELECT p.first_name, p.last_name, p.total_qty, p.discount
FROM preorders p
LIMIT 1;
```
Should NOT error

✅ Run application:
- Open sales report page
- Create test pre-order
- Claim pre-order
- All should work without errors

---

## LESSONS LEARNED

### For Future Database Management:

1. ✅ **Version Control SQL Files**
   - Keep SQL files in git with proper naming
   - Use semantic versioning
   - Tag releases

2. ✅ **Schema Migration Scripts**
   - Never hand-edit production schemas
   - Use migration tools (Flyway, Liquibase)
   - Track schema changes in code

3. ✅ **Automated Schema Comparison**
   - Write tests to compare schema vs expected
   - CI/CD checks for schema compatibility
   - Fail builds on schema mismatch

4. ✅ **Documentation**
   - Document schema changes in CHANGELOG
   - Keep ERD diagrams updated
   - Note breaking changes

5. ✅ **Testing**
   - Test database imports in staging
   - Verify application works after import
   - Check all major features

---

## FINAL RECOMMENDATIONS SUMMARY

| Action | Status | Priority |
|--------|--------|----------|
| Use motogam_management_orig.sql | ✅ Required | CRITICAL |
| Archive old/oldv2/oldv3/oldv4 files | ✅ Recommended | HIGH |
| Test application after any DB import | ✅ Required | CRITICAL |
| Document this finding in project wiki | ✅ Recommended | MEDIUM |
| Implement schema versioning | ✅ Recommended | MEDIUM |
| Add schema validation tests | ✅ Recommended | LOW |

---

## CONCLUSION

**CRITICAL FINDING:**

All "old" SQL files (old, oldv2, oldv3, oldv4) are **INCOMPATIBLE** with your current application.

**The ONLY compatible file is: `motogam_management_orig.sql`**

The schema differences are not minor variations - they represent **fundamentally different database designs** that will cause **application failures** at runtime.

**DO NOT USE** any old version files for production deployments.

---

**Document Status:** COMPLETE ANALYSIS  
**Verification Method:** Field-by-field comparison + application code review  
**Confidence Level:** 100%  
**Action Required:** IMMEDIATE - Update all deployment procedures

**Last Updated:** July 21, 2026  
**Analyst:** Kiro Database Schema Analysis Tool
