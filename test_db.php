<?php
require_once 'session_check.php';
include 'config.php';
$res = $conn->query('SHOW TABLES'); 
while($r = $res->fetch_array()) echo $r[0] . "\n";
