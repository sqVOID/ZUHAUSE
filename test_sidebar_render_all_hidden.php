<?php
require_once 'session_check.php';
session_start();
$_SESSION['sidebar_access'] = 'Account Registration,User Activation,Position Registration,Promoter Registration,Branch Registration,Dealer Registration,Supplier Registration,Brand Registration,Family Code Registration,Department Registration,Group Registration,Item Registration,Terminal Issuer Registration,Terminal ID Registration';
$current_page = 'report.php';
include '_sidebar.php';
?>
