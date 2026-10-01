<?php
require '../config.php';
$r = $conn->query('DESCRIBE stock_on_hand');
while($row = $r->fetch_assoc()) {
    echo $row['Field'].' | '.$row['Type'].' | Null:'.$row['Null'].' | Default:'.$row['Default'].PHP_EOL;
}
?>
