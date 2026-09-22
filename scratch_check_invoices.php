<?php
require 'config.php';
$res = $conn->query("SELECT * FROM preorder_payment_history WHERE preorder_id = 93");
while($r = $res->fetch_assoc()) {
    print_r($r);
}
