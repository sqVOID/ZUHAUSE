<?php
require_once 'session_check.php';
require_once 'config.php';

echo "<h2>Adding 'branch' column to claimed_items table...</h2>";

// Step 1: Add branch column if it doesn't exist
$add_column_sql = "ALTER TABLE claimed_items 
                   ADD COLUMN IF NOT EXISTS branch VARCHAR(255) NULL 
                   AFTER branch_code";

try {
    if ($conn->query($add_column_sql) === TRUE) {
        echo "<p style='color: green;'>✓ Column 'branch' added successfully to claimed_items table!</p>";
    } else {
        echo "<p style='color: orange;'>Note: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error adding column: " . $e->getMessage() . "</p>";
}

// Step 2: Populate existing records with branch names from unclaimed_freebies (primary source) or branches table (fallback)
echo "<h3>Populating existing records with branch names...</h3>";

// First try to get branch from unclaimed_freebies (most accurate source)
$update_from_unclaimed_sql = "UPDATE claimed_items ci
                               INNER JOIN unclaimed_freebies uf ON ci.unclaimed_freebie_id = uf.id
                               SET ci.branch = uf.branch
                               WHERE (ci.branch IS NULL OR ci.branch = '') 
                               AND uf.branch IS NOT NULL AND uf.branch != ''";

try {
    if ($conn->query($update_from_unclaimed_sql) === TRUE) {
        $affected1 = $conn->affected_rows;
        echo "<p style='color: green;'>✓ Updated $affected1 records with branch names from unclaimed_freebies!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error updating from unclaimed_freebies: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>";
}

// Fallback: Use branches table for any remaining records
$update_from_branches_sql = "UPDATE claimed_items ci
                              LEFT JOIN branches b ON TRIM(ci.branch_code) COLLATE utf8mb4_general_ci = TRIM(b.branch_code) COLLATE utf8mb4_general_ci
                              SET ci.branch = b.branch_name
                              WHERE (ci.branch IS NULL OR ci.branch = '') 
                              AND ci.branch_code IS NOT NULL 
                              AND b.branch_name IS NOT NULL";

try {
    if ($conn->query($update_from_branches_sql) === TRUE) {
        $affected2 = $conn->affected_rows;
        echo "<p style='color: green;'>✓ Updated $affected2 additional records with branch names from branches table!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error updating from branches table: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>";
}

// Step 3: Show current table structure
echo "<h3>Current Table Structure:</h3>";
$result = $conn->query("DESCRIBE claimed_items");
if ($result) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Step 4: Show sample data
echo "<h3>Sample Records (First 5):</h3>";
$sample_sql = "SELECT id, invoice_no, branch_code, branch, item_description, claimed_at 
               FROM claimed_items 
               ORDER BY claimed_at DESC 
               LIMIT 5";
$sample_result = $conn->query($sample_sql);

if ($sample_result && $sample_result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Invoice</th><th>Branch Code</th><th>Branch Name</th><th>Item</th><th>Claimed At</th></tr>";
    while ($row = $sample_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['invoice_no']) . "</td>";
        echo "<td>" . htmlspecialchars($row['branch_code'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['branch'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['item_description']) . "</td>";
        echo "<td>" . htmlspecialchars($row['claimed_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No records found in claimed_items table.</p>";
}

echo "<p><strong>Setup complete!</strong> The 'branch' column is now available in claimed_items table.</p>";
echo "<p><a href='modification-claimitem.php'>Go to Claim Item Modification</a></p>";

$conn->close();
?>
