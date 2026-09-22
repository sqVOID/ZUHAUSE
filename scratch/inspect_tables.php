<?php
include 'config.php';
echo "=== PREORDER_ITEMS COLUMNS ===\n";
$res = $conn->query("SHOW COLUMNS FROM preorder_items");
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}

echo "\n=== PREORDER_PAYMENT_HISTORY COLUMNS ===\n";
$res = $conn->query("SHOW COLUMNS FROM preorder_payment_history");
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}
