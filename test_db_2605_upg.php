<?php
require_once 'session_check.php';
require 'config.php';
$q = $conn->query("SELECT * FROM upgrade_old_items WHERE upgrade_id = (SELECT id FROM upgrades WHERE original_invoice_no='260505-MGL-00012' ORDER BY id DESC LIMIT 1)");
while($r=$q->fetch_assoc()) echo json_encode($r) . "\n";
?>
