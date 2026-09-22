<?php
require_once 'session_check.php';
include 'config.php';

$po_id = isset($_GET['po_id']) ? (int)$_GET['po_id'] : 0;

echo "<h2>Debug: Purchase Order Serial Types</h2>";
echo "<p>PO ID: {$po_id}</p>";

if ($po_id > 0) {
    $query = $conn->query("SELECT * FROM purchase_order_serial_types WHERE po_id = {$po_id} ORDER BY id");
    
    if ($query && $query->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>PO ID</th><th>Family Code</th><th>Item No</th><th>Serial Number</th><th>Item Type</th><th>Serial Length</th></tr>";
        
        while ($row = $query->fetch_assoc()) {
            $serial_length = strlen($row['serial_number']);
            $serial_display = htmlspecialchars($row['serial_number']);
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['po_id']}</td>";
            echo "<td>{$row['family_code']}</td>";
            echo "<td>{$row['item_no']}</td>";
            echo "<td><strong>{$serial_display}</strong></td>";
            echo "<td><strong>{$row['item_type']}</strong></td>";
            echo "<td>{$serial_length}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        echo "<br><h3>Total records: " . $query->num_rows . "</h3>";
    } else {
        echo "<p style='color: red;'>No serial types found for this PO!</p>";
    }
} else {
    echo "<p style='color: red;'>Invalid PO ID</p>";
}

// Also check stock_on_hand
echo "<br><br><h2>Stock on Hand for this PO</h2>";
$po_query = $conn->query("SELECT po_number FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
if ($po_query && $po_query->num_rows > 0) {
    $po_number = $po_query->fetch_assoc()['po_number'];
    echo "<p>PO Number: {$po_number}</p>";
    
    $stock_query = $conn->query("SELECT imei, status, item_code, description FROM stock_on_hand WHERE dr_number = '{$po_number}' ORDER BY id");
    
    if ($stock_query && $stock_query->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Serial (IMEI)</th><th>Status</th><th>Item Code</th><th>Description</th><th>Serial Length</th></tr>";
        
        while ($row = $stock_query->fetch_assoc()) {
            $serial_length = strlen($row['imei']);
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($row['imei']) . "</strong></td>";
            echo "<td><strong>" . htmlspecialchars($row['status']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
            echo "<td>{$serial_length}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No stock found for this PO</p>";
    }
}

$conn->close();
?>
