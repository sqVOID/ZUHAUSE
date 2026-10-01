<?php
require '../config.php';
$r = $conn->query('DESCRIBE sales_entry');
while($row = $r->fetch_assoc()) {
    echo $row['Field'].' | '.$row['Type'].PHP_EOL;
}
?>
