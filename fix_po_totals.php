<?php
/**
 * Migration script to fix incorrect total_qty and total_items in purchase_orders
 * 
 * This script recalculates the totals by excluding is_receive_added = 1 items,
 * which are emergency items added during receiving and should not count toward
 * the original purchase order totals.
 */

require_once 'session_check.php';
include 'config.php';

echo "<h2>Fixing Purchase Order Totals</h2>";

// Get all purchase orders
$po_query = $conn->query("SELECT id, po_number, total_items, total_qty FROM purchase_orders ORDER BY id DESC");

if (!$po_query) {
    die("Error fetching purchase orders: " . $conn->error);
}

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr>
        <th>PO Number</th>
        <th>Old Total Items</th>
        <th>New Total Items</th>
        <th>Old Total Qty</th>
        <th>New Total Qty</th>
        <th>Status</th>
      </tr>";

$fixed_count = 0;
$unchanged_count = 0;

while ($po = $po_query->fetch_assoc()) {
    $po_id = (int)$po['id'];
    $po_number = $po['po_number'];
    $old_total_items = (int)$po['total_items'];
    $old_total_qty = (int)$po['total_qty'];
    
    // Recalculate totals excluding is_receive_added items
    $recalc_query = $conn->query("
        SELECT COUNT(DISTINCT id) as total_items,
               COALESCE(SUM(quantity), 0) as total_qty
        FROM purchase_order_items
        WHERE po_id = {$po_id}
        AND COALESCE(is_receive_added, 0) = 0
    ");
    
    if ($recalc_query && $recalc_query->num_rows > 0) {
        $recalc_data = $recalc_query->fetch_assoc();
        $new_total_items = (int)$recalc_data['total_items'];
        $new_total_qty = (int)$recalc_data['total_qty'];
        
        // Only update if values changed
        if ($old_total_items !== $new_total_items || $old_total_qty !== $new_total_qty) {
            $update_sql = "UPDATE purchase_orders 
                          SET total_items = {$new_total_items},
                              total_qty = {$new_total_qty}
                          WHERE id = {$po_id}";
            
            if ($conn->query($update_sql)) {
                echo "<tr>
                        <td>{$po_number}</td>
                        <td>{$old_total_items}</td>
                        <td style='font-weight: bold; color: green;'>{$new_total_items}</td>
                        <td>{$old_total_qty}</td>
                        <td style='font-weight: bold; color: green;'>{$new_total_qty}</td>
                        <td style='color: green;'>✓ Fixed</td>
                      </tr>";
                $fixed_count++;
            } else {
                echo "<tr>
                        <td>{$po_number}</td>
                        <td>{$old_total_items}</td>
                        <td>{$new_total_items}</td>
                        <td>{$old_total_qty}</td>
                        <td>{$new_total_qty}</td>
                        <td style='color: red;'>✗ Error: {$conn->error}</td>
                      </tr>";
            }
        } else {
            $unchanged_count++;
        }
    }
}

echo "</table>";
echo "<br>";
echo "<p><strong>Summary:</strong></p>";
echo "<ul>";
echo "<li>Fixed: {$fixed_count} purchase orders</li>";
echo "<li>Unchanged: {$unchanged_count} purchase orders</li>";
echo "</ul>";
echo "<p style='color: green; font-weight: bold;'>Migration completed!</p>";
echo "<br><a href='purchaseorder.php'>← Back to Purchase Orders</a>";
?>
