<?php
include __DIR__ . '/../config.php';
$r = $conn->query("UPDATE preorder_items SET item_description='', item_code='', imei='', status='pending', claimed_at=NULL WHERE preorder_id=17 AND family_code='CLICK 160'");
echo "Reset: affected=" . $conn->affected_rows . PHP_EOL;

// Also reset preorders back to pending for preorder_id=17
$r2 = $conn->query("UPDATE preorders SET status='pending' WHERE id=17");
echo "Preorder reset: affected=" . $conn->affected_rows . PHP_EOL;
?>
