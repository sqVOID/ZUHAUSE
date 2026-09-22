<?php
include __DIR__ . '/../config.php';

$res = $conn->query("SELECT invoice_no, payment_data FROM sales_entry WHERE invoice_no LIKE '%0175%' OR invoice_no LIKE '%0176%' ORDER BY id DESC LIMIT 5");
while ($row = $res->fetch_assoc()) {
    echo "Invoice: " . $row['invoice_no'] . "\n";
    echo "Payment Data: " . $row['payment_data'] . "\n\n";
}
?>
