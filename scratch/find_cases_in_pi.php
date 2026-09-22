<?php
include 'config.php';
$r = $conn->query("SELECT pi.id, pi.preorder_id, p.invoice_no, pi.item_description, pi.item_code, pi.price FROM preorder_items pi JOIN preorders p ON pi.preorder_id = p.id WHERE pi.item_description LIKE '%CASE%' OR pi.item_code LIKE '%CASE%'");
while ($row = $r->fetch_assoc()) {
    print_r($row);
}
