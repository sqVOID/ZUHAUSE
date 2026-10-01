<?php
require '../config.php';
// Fix the already-inserted record that has wrong item_type='Unit'
// It should be 'IMEI' so the SOH page can find it
$result = $conn->query("UPDATE stock_on_hand SET item_type = 'IMEI' WHERE imei != '' AND imei IS NOT NULL AND item_type = 'Unit' AND status = 'On Process' AND created_at >= '2026-10-01'");
echo "Updated rows: " . $conn->affected_rows . "\n";

// Confirm OIWJHET[WIJ is now correct
$r = $conn->query("SELECT id, item_code, imei, item_type, status, branch FROM stock_on_hand WHERE UPPER(TRIM(imei)) = UPPER('OIWJHET[WIJ')");
while($row = $r->fetch_assoc()) print_r($row);
?>
