<?php
require_once 'session_check.php';
require 'config.php';
$q = $conn->query("SELECT payment_data FROM sales_entry WHERE invoice_no = '260505-MGL-00012'");
$r = $q->fetch_assoc();
echo "SALE DATA:\n" . print_r($r, true);

$q2 = $conn->query("SELECT payment_data FROM upgrades WHERE original_invoice_no = '260505-MGL-00012' ORDER BY id DESC LIMIT 1");
$r2 = $q2->fetch_assoc();
echo "\nUPGRADE DATA:\n" . print_r($r2, true);
?>
