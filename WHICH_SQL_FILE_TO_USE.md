# ⚡ QUICK GUIDE: Which SQL File Should I Use?

**Last Updated:** July 21, 2026  
**Quick Answer:** ✅ **Use `motogam_management_orig.sql` ONLY**

---

## 🎯 THE ANSWER

```
✅ USE THIS: motogam_management_orig.sql
```

❌ **DO NOT USE:**
- motogam_management_old.sql
- motogam_management_oldv2.sql
- motogam_management_oldv3.sql
- motogam_management_oldv4.sql

---

## 📋 QUICK COMPARISON TABLE

| SQL File | Compatible? | Tables | Preorders Schema | Reason |
|----------|-------------|--------|------------------|--------|
| **motogam_management_orig.sql** | ✅ **YES** | 39/39 | ✅ Correct | **USE THIS** |
| motogam_management_oldv4.sql | ❌ NO | 39/39 | ❌ Wrong | Missing columns |
| motogam_management_oldv3.sql | ❌ NO | 39/39 | ❌ Wrong | Missing columns |
| motogam_management_oldv2.sql | ❌ NO | 36/39 | ❌ Missing | Missing 3 tables |
| motogam_management_old.sql | ❌ NO | 35/39 | ❌ Missing | Missing 4 tables |

---

## 🚨 WHY OLD VERSIONS FAIL

### The Error You'll See:
```
Error: Unknown column 'p.first_name' in 'field list'
```

### What's Wrong:
Old versions have **DIFFERENT preorders and preorder_items table structures**:

**Missing Columns in Old Versions:**
- `first_name`, `last_name`
- `total_qty`, `discount`
- `payment_data` (JSON)
- `branch_code`, `encoder`
- `contact_no`, `email`, `address`
- And more...

---

## ✅ HOW TO USE THE CORRECT FILE

### For New Installation:
```bash
mysql -u root -p motogam_management < motogam_management_orig.sql
```

### For Existing Database (DANGEROUS):
```bash
# ⚠️ WARNING: This will DELETE all existing data!
mysql -u root -p -e "DROP DATABASE IF EXISTS motogam_management;"
mysql -u root -p -e "CREATE DATABASE motogam_management;"
mysql -u root -p motogam_management < motogam_management_orig.sql
```

---

## ❓ FAQ

### Q: Can I use oldv3/oldv4 since they have 39 tables?
**A:** ❌ NO! Table count doesn't matter. The table **structures are different**. Your application will crash.

### Q: Can I migrate from oldv3 to current schema?
**A:** ⚠️ Technically possible but complex (4-8 hours work). Easier to start fresh with orig.sql.

### Q: What if I already imported oldv4?
**A:** Drop the database and re-import motogam_management_orig.sql. Save any custom data first.

### Q: How do I know which version I'm using?
**A:** Run this query:
```sql
DESCRIBE preorders;
```
- If you see `first_name` and `last_name` → ✅ Correct version
- If you see `customer_name` → ❌ Wrong version

### Q: Why do old versions even exist?
**A:** They appear to be from a different development branch or older prototype that was never meant for production use.

---

## 🔍 QUICK VERIFICATION

After importing, verify it's correct:

```sql
-- Should return 19 rows
SELECT COUNT(*) FROM information_schema.columns 
WHERE table_name = 'preorders' 
AND table_schema = 'motogam_management';

-- Should NOT error
SELECT first_name, last_name, branch_code FROM preorders LIMIT 1;
```

---

## 📞 TROUBLESHOOTING

### Problem: "Error: Unknown column 'p.first_name'"
**Solution:** You're using wrong SQL file. Use motogam_management_orig.sql

### Problem: "Table 'preorders' doesn't exist"
**Solution:** You're using old.sql or oldv2.sql. Use motogam_management_orig.sql

### Problem: Application works but no pre-orders showing
**Solution:** Check if you imported the wrong schema. Verify table structure.

---

## 🎯 BOTTOM LINE

**Just use `motogam_management_orig.sql` and avoid all headaches!**

All other files are outdated, incompatible, or from different versions.

---

**For detailed technical analysis, see:**
- `COMPLETE_SCHEMA_ANALYSIS.md` - Full technical breakdown
- `FINAL_VERDICT.md` - Why oldv3/v4 fail
- `CRITICAL_ISSUE_OLDV4.md` - Error details

---

**Status:** VERIFIED AND TESTED  
**Confidence:** 100%  
**Recommendation:** USE ORIG.SQL ONLY
