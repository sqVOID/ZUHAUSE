<?php
require_once 'session_check.php';
require_once 'config.php';

echo "<h2>Adding columns to preorder_items table...</h2>";

$alterations = [
    "ALTER TABLE preorder_items ADD COLUMN family_code VARCHAR(100) NULL COMMENT 'Family code like HONDA CLICK 160' AFTER preorder_id",
    "ALTER TABLE preorder_items ADD COLUMN item_code VARCHAR(100) NULL COMMENT 'Actual item code/model' AFTER item_description",
    "ALTER TABLE preorder_items ADD COLUMN imei VARCHAR(100) NULL COMMENT 'Serial/IMEI number' AFTER item_code",
    "ALTER TABLE preorder_items ADD COLUMN total_payment DECIMAL(10,2) NULL AFTER price",
    "ALTER TABLE preorder_items ADD COLUMN amount_paid DECIMAL(10,2) DEFAULT 0.00 AFTER total_payment",
    "ALTER TABLE preorder_items ADD COLUMN payment_method VARCHAR(50) NULL AFTER amount_paid",
    "ALTER TABLE preorder_items ADD COLUMN status VARCHAR(20) DEFAULT 'pending' AFTER payment_method",
    "ALTER TABLE preorder_items ADD COLUMN dr_number VARCHAR(100) NULL AFTER status",
    "ALTER TABLE preorder_items ADD COLUMN claimed_at TIMESTAMP NULL AFTER dr_number",
    "ALTER TABLE preorder_items ADD INDEX idx_family_code (family_code)",
    "ALTER TABLE preorder_items ADD INDEX idx_item_code (item_code)",
    "ALTER TABLE preorder_items ADD INDEX idx_imei (imei)",
    "ALTER TABLE preorder_items ADD INDEX idx_status (status)"
];

$success_count = 0;
$skip_count = 0;
$error_count = 0;

foreach ($alterations as $sql) {
    try {
        if ($conn->query($sql) === TRUE) {
            echo "<p style='color: green;'>✓ " . htmlspecialchars($sql) . "</p>";
            $success_count++;
        } else {
            // Check if column already exists
            if (strpos($conn->error, 'Duplicate column') !== false) {
                echo "<p style='color: orange;'>⊘ Column already exists: " . htmlspecialchars($sql) . "</p>";
                $skip_count++;
            } else {
                echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($conn->error) . "<br>SQL: " . htmlspecialchars($sql) . "</p>";
                $error_count++;
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Exception: " . htmlspecialchars($e->getMessage()) . "<br>SQL: " . htmlspecialchars($sql) . "</p>";
        $error_count++;
    }
}

echo "<hr>";
echo "<h3>Summary:</h3>";
echo "<p><strong style='color: green;'>Success:</strong> $success_count</p>";
echo "<p><strong style='color: orange;'>Skipped (already exists):</strong> $skip_count</p>";
echo "<p><strong style='color: red;'>Errors:</strong> $error_count</p>";

// Show updated table structure
echo "<hr>";
echo "<h3>Updated Table Structure:</h3>";
$result = $conn->query("DESCRIBE preorder_items");
if ($result) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $isNew = in_array($row['Field'], ['family_code', 'item_code', 'imei', 'total_payment', 'amount_paid', 'payment_method', 'status', 'dr_number', 'claimed_at']);
        $rowStyle = $isNew ? "background-color: #d4edda;" : "";
        echo "<tr style='$rowStyle'>";
        echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><em>New columns are highlighted in green</em></p>";
}

echo "<hr>";
if ($error_count === 0) {
    echo "<p style='color: green; font-size: 16px;'><strong>✓ Migration completed successfully!</strong></p>";
    
    // Now we need to migrate existing data from item_description to family_code
    echo "<h3>Migrating existing data...</h3>";
    $migrate_sql = "UPDATE preorder_items SET family_code = item_description WHERE family_code IS NULL";
    if ($conn->query($migrate_sql)) {
        echo "<p style='color: green;'>✓ Existing item_description data copied to family_code</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Could not migrate data: " . htmlspecialchars($conn->error) . "</p>";
    }
    
    echo "<div style='background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;'>";
    echo "<h4 style='margin-top: 0;'>⚠️ Important: Database Structure</h4>";
    echo "<p><strong>Now the structure should be:</strong></p>";
    echo "<ul>";
    echo "<li><strong>family_code</strong> - Family code like 'HONDA CLICK 160' (from pre-order)</li>";
    echo "<li><strong>item_description</strong> - Full description (updated when claimed)</li>";
    echo "<li><strong>item_code</strong> - Actual model/item code (added when claimed)</li>";
    echo "<li><strong>imei</strong> - Serial/IMEI number (added when claimed)</li>";
    echo "</ul>";
    echo "<p><em>Note: You may need to update save_preorder.php to save to family_code instead of item_description</em></p>";
    echo "</div>";
    
    echo "<p>You can now use the claim preorder feature with full tracking.</p>";
    echo "<p><a href='claimpreorder.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Go to Claim Pre-order</a></p>";
} else {
    echo "<p style='color: red; font-size: 16px;'><strong>⚠ Migration completed with errors. Please check the errors above.</strong></p>";
}

$conn->close();
?>
