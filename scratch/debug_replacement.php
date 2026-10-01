<?php
require '../config.php';

$invoice_no = '0194';
$old_imei   = 'OIWJHET[WIJ';

echo "=== Check sales_entry ===\n";
$r = $conn->query("SELECT id, invoice_no, branch_code, encoder FROM sales_entry WHERE invoice_no = '$invoice_no' LIMIT 5");
while($row = $r->fetch_assoc()) print_r($row);

echo "\n=== Check sales_entry_items for this invoice ===\n";
$r2 = $conn->query("SELECT sei.* FROM sales_entry_items sei LEFT JOIN sales_entry se ON se.invoice_no='$invoice_no' WHERE sei.sales_entry_id = se.id");
while($row = $r2->fetch_assoc()) print_r($row);

echo "\n=== Check stock_on_hand for IMEI ===\n";
$r3 = $conn->query("SELECT * FROM stock_on_hand WHERE UPPER(TRIM(imei)) = UPPER('$old_imei')");
while($row = $r3->fetch_assoc()) print_r($row);

echo "\n=== Check item_code passed (from old_items) ===\n";
// Simulate what PHP gets from JS
$r4 = $conn->query("SELECT sei.item_code, sei.item_description, sei.imei FROM sales_entry_items sei JOIN sales_entry se ON se.id = sei.sales_entry_id WHERE se.invoice_no='$invoice_no' AND UPPER(TRIM(sei.imei)) = UPPER('$old_imei') LIMIT 1");
while($row = $r4->fetch_assoc()) print_r($row);
?>
