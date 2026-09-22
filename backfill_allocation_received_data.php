<?php
/**
 * Backfill Script: Update purchase_order_allocations with received_qty and invoice_number
 * This script updates existing allocations for already-received POs
 */

require_once 'session_check.php';
include 'config.php';

// Check if user is Super Admin
$sys_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
if (strcasecmp($sys_level, 'Super-Admin') !== 0) {
    die("<h2>Access Denied</h2><p>Only Super Admin can run this script.</p><a href='main.php'>Go to Dashboard</a>");
}

echo "<h2>Backfill Allocation Received Data</h2>";
echo "<p>This script will update purchase_order_allocations with received quantities and invoice numbers for already-received POs.</p>";

// Start transaction
$conn->begin_transaction();

try {
    // Get all received/incomplete POs
    $po_query = $conn->query("
        SELECT id, po_number, status, invoice_number 
        FROM purchase_orders 
        WHERE status IN ('Received', 'Incomplete')
        ORDER BY id DESC
    ");
    
    if (!$po_query) {
        throw new Exception("Failed to fetch POs: " . $conn->error);
    }
    
    echo "<h3>Processing " . $po_query->num_rows . " POs...</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>
            <th>PO Number</th>
            <th>Status</th>
            <th>Invoice Number</th>
            <th>Items Updated</th>
            <th>Result</th>
          </tr>";
    
    $total_updated = 0;
    
    while ($po = $po_query->fetch_assoc()) {
        $po_id = $po['id'];
        $po_number = $po['po_number'];
        $status = $po['status'];
        $invoice_number = $po['invoice_number'] ?: '';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($po_number) . "</td>";
        echo "<td>" . htmlspecialchars($status) . "</td>";
        echo "<td>" . htmlspecialchars($invoice_number ?: 'None') . "</td>";
        
        // Check if allocations exist for this PO
        $alloc_check = $conn->query("SELECT COUNT(*) as count FROM purchase_order_allocations WHERE po_id = {$po_id}");
        if (!$alloc_check || $alloc_check->fetch_assoc()['count'] == 0) {
            echo "<td colspan='2' style='color: orange;'>No allocations found</td>";
            echo "</tr>";
            continue;
        }
        
        // Get all items from this PO with their quantities
        $items_query = $conn->query("
            SELECT family_code, quantity, serial_number 
            FROM purchase_order_items 
            WHERE po_id = {$po_id}
        ");
        
        if (!$items_query) {
            echo "<td colspan='2' style='color: red;'>Error: " . $conn->error . "</td>";
            echo "</tr>";
            continue;
        }
        
        $items_updated = 0;
        
        while ($item = $items_query->fetch_assoc()) {
            $family_code = $item['family_code'];
            $family_code_esc = $conn->real_escape_string($family_code);
            
            // Determine received quantity
            $received_qty = 0;
            
            if ($status === 'Received') {
                // For fully received POs, received_qty = total quantity
                $received_qty = (int)$item['quantity'];
            } elseif ($status === 'Incomplete') {
                // For incomplete POs, count serial numbers
                $serial_numbers = $item['serial_number'];
                if (!empty($serial_numbers)) {
                    $serials = explode("\n", trim($serial_numbers));
                    $received_qty = count(array_filter($serials));
                }
            }
            
            // Update allocations for this family_code
            $update_sql = "UPDATE purchase_order_allocations 
                          SET received_qty = {$received_qty}";
            
            // Add invoice_number if available
            if (!empty($invoice_number)) {
                $invoice_number_esc = $conn->real_escape_string($invoice_number);
                $update_sql .= ", invoice_number = '{$invoice_number_esc}'";
            }
            
            $update_sql .= " WHERE po_id = {$po_id} 
                            AND family_code = '{$family_code_esc}'";
            
            if ($conn->query($update_sql)) {
                $affected = $conn->affected_rows;
                if ($affected > 0) {
                    $items_updated += $affected;
                }
            } else {
                echo "<td colspan='2' style='color: red;'>Update failed for {$family_code}: " . $conn->error . "</td>";
                echo "</tr>";
                continue 2; // Skip to next PO
            }
        }
        
        echo "<td>" . $items_updated . "</td>";
        echo "<td style='color: green;'>✓ Updated</td>";
        echo "</tr>";
        
        $total_updated += $items_updated;
    }
    
    echo "</table>";
    
    // Commit transaction
    $conn->commit();
    
    echo "<h3 style='color: green;'>✓ Success!</h3>";
    echo "<p><strong>Total allocation records updated: {$total_updated}</strong></p>";
    echo "<p><a href='purchaseorder-details.php?id=" . (isset($_GET['po_id']) ? $_GET['po_id'] : '') . "'>Go back to Purchase Order Details</a></p>";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "<h3 style='color: red;'>✗ Error!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>
