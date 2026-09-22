<?php
/**
 * Migration script to fix is_receive_added flag for items added during modification
 * 
 * This script identifies items in purchase_order_items that were added during
 * receiving/modification but weren't properly marked with is_receive_added = 1.
 * 
 * These items should not be counted in the total_order_qty calculation.
 */

require_once 'session_check.php';
include 'config.php';

// Ensure the columns exist
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS is_receive_added TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS receiving_branch VARCHAR(255) DEFAULT NULL");

echo "<h2>Fixing is_receive_added flag for orphaned items</h2>";

// Find items that don't have allocations but are in purchase_order_items
// These are items that were added during modification and should be marked as receive-added
$sql = "
    SELECT poi.id, poi.po_id, poi.po_number, poi.family_code, poi.item_no, poi.item_model
    FROM purchase_order_items poi
    WHERE COALESCE(poi.is_receive_added, 0) = 0
    AND NOT EXISTS (
        SELECT 1 
        FROM purchase_order_allocations poa 
        WHERE poa.po_id = poi.po_id 
        AND poa.family_code COLLATE utf8mb4_general_ci = poi.family_code COLLATE utf8mb4_general_ci
    )
";

$result = $conn->query($sql);
$items_to_fix = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $items_to_fix[] = $row;
    }
}

echo "<p>Found " . count($items_to_fix) . " items to fix.</p>";

if (count($items_to_fix) > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>
            <th>ID</th>
            <th>PO Number</th>
            <th>Family Code</th>
            <th>Item No</th>
            <th>Item Model</th>
            <th>Action</th>
          </tr>";
    
    foreach ($items_to_fix as $item) {
        $item_id = (int)$item['id'];
        $po_id = (int)$item['po_id'];
        
        // Get the branch this PO was allocated to (if any)
        $branch_query = $conn->query("
            SELECT DISTINCT branch_name 
            FROM purchase_order_allocations 
            WHERE po_id = {$po_id} 
            LIMIT 1
        ");
        
        $receiving_branch = '';
        if ($branch_query && $branch_query->num_rows > 0) {
            $receiving_branch = $branch_query->fetch_assoc()['branch_name'];
        }
        
        // Update the item
        $receiving_branch_esc = $conn->real_escape_string($receiving_branch);
        $update_sql = "
            UPDATE purchase_order_items 
            SET is_receive_added = 1,
                receiving_branch = '{$receiving_branch_esc}'
            WHERE id = {$item_id}
        ";
        
        if ($conn->query($update_sql)) {
            echo "<tr>
                    <td>{$item['id']}</td>
                    <td>{$item['po_number']}</td>
                    <td>{$item['family_code']}</td>
                    <td>{$item['item_no']}</td>
                    <td>{$item['item_model']}</td>
                    <td style='color: green;'>✓ Fixed (Branch: {$receiving_branch})</td>
                  </tr>";
        } else {
            echo "<tr>
                    <td>{$item['id']}</td>
                    <td>{$item['po_number']}</td>
                    <td>{$item['family_code']}</td>
                    <td>{$item['item_no']}</td>
                    <td>{$item['item_model']}</td>
                    <td style='color: red;'>✗ Error: {$conn->error}</td>
                  </tr>";
        }
    }
    
    echo "</table>";
    echo "<p style='color: green; font-weight: bold;'>Migration completed!</p>";
} else {
    echo "<p>No items need fixing. All items are properly marked.</p>";
}

echo "<br><a href='purchaseorder.php'>← Back to Purchase Orders</a>";
?>
