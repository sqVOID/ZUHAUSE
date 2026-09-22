<?php
// Migration script to add item_model and item_description columns to purchase_order_allocations table
require_once 'session_check.php';
include 'config.php';

// Only Super Admin can run migrations
if (!isset($_SESSION['system_level']) || $_SESSION['system_level'] !== 'Super-Admin') {
    die("Access denied. Only Super Admin can run this script.");
}

echo "<h2>Adding item_model and item_description to purchase_order_allocations table</h2>";

// Add item_model column
$sql1 = "ALTER TABLE purchase_order_allocations 
         ADD COLUMN IF NOT EXISTS item_model VARCHAR(255) DEFAULT NULL";

if ($conn->query($sql1)) {
    echo "<p style='color: green;'>✓ item_model column added successfully (or already exists)</p>";
} else {
    echo "<p style='color: red;'>✗ Error adding item_model column: " . $conn->error . "</p>";
}

// Add item_description column
$sql2 = "ALTER TABLE purchase_order_allocations 
         ADD COLUMN IF NOT EXISTS item_description TEXT DEFAULT NULL";

if ($conn->query($sql2)) {
    echo "<p style='color: green;'>✓ item_description column added successfully (or already exists)</p>";
} else {
    echo "<p style='color: red;'>✗ Error adding item_description column: " . $conn->error . "</p>";
}

// Backfill existing data from purchase_order_items to purchase_order_allocations
echo "<h3>Backfilling existing item models...</h3>";

$backfill_sql = "
    UPDATE purchase_order_allocations poa
    INNER JOIN purchase_order_items poi 
        ON poa.po_id = poi.po_id 
        AND poa.family_code COLLATE utf8mb4_general_ci = poi.family_code COLLATE utf8mb4_general_ci
    SET poa.item_model = poi.item_model,
        poa.item_description = poi.item_description
    WHERE (poa.item_model IS NULL OR poa.item_model = '')
";

if ($conn->query($backfill_sql)) {
    $affected = $conn->affected_rows;
    echo "<p style='color: green;'>✓ Backfilled {$affected} allocation records with item models from purchase_order_items</p>";
} else {
    echo "<p style='color: red;'>✗ Error backfilling: " . $conn->error . "</p>";
}

echo "<h3>Migration Complete!</h3>";
echo "<p><a href='purchaseorderreceive.php'>Go to Purchase Order Receive</a></p>";

$conn->close();
?>
