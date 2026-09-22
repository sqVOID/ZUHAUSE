<?php
require_once 'session_check.php';
include 'config.php';

// Fix PO-2026-006 specifically
$po_number = 'PO-2026-006';

// Get the PO ID
$po_query = $conn->query("SELECT id FROM purchase_orders WHERE po_number = '{$po_number}' LIMIT 1");
if ($po_query && $po_query->num_rows > 0) {
    $po_data = $po_query->fetch_assoc();
    $po_id = $po_data['id'];
    
    // Recalculate total_qty and total_items from purchase_order_items
    $recalc_query = $conn->query("
        SELECT COUNT(DISTINCT id) as total_items,
               SUM(quantity) as total_qty
        FROM purchase_order_items
        WHERE po_id = {$po_id}
    ");
    
    if ($recalc_query && $recalc_query->num_rows > 0) {
        $recalc_data = $recalc_query->fetch_assoc();
        $new_total_items = (int)$recalc_data['total_items'];
        $new_total_qty = (int)$recalc_data['total_qty'];
        
        echo "PO Number: {$po_number}<br>";
        echo "Current total_items in purchase_order_items: {$new_total_items}<br>";
        echo "Current total_qty in purchase_order_items: {$new_total_qty}<br><br>";
        
        // Update the purchase_orders table
        $update_sql = "UPDATE purchase_orders 
                      SET total_items = {$new_total_items},
                          total_qty = {$new_total_qty}
                      WHERE id = {$po_id}";
        
        if ($conn->query($update_sql)) {
            echo "<span style='color: green;'>✓ Successfully updated {$po_number} to total_qty = {$new_total_qty}</span><br>";
        } else {
            echo "<span style='color: red;'>✗ Failed to update: " . $conn->error . "</span><br>";
        }
    }
} else {
    echo "<span style='color: red;'>PO-2026-006 not found!</span><br>";
}

$conn->close();
?>
