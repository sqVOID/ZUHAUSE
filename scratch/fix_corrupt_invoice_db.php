<?php
include 'config.php';

// Fix sales_entry and preorders records with corrupt invoice_no
$conn->query("UPDATE sales_entry SET invoice_no = '260827-ZUHINFA-00006-PRE' WHERE invoice_no = '260827-ZUHINFA-260828-PRE'");
echo "Updated sales_entry: " . $conn->affected_rows . "\n";

$conn->query("UPDATE preorders SET claimed_invoice_no = '260827-ZUHINFA-00006-PRE' WHERE claimed_invoice_no = '260827-ZUHINFA-260828-PRE'");
echo "Updated preorders: " . $conn->affected_rows . "\n";

// Verify sales_entry invoices
$res = $conn->query("SELECT id, invoice_no, branch_code FROM sales_entry ORDER BY id DESC LIMIT 5");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
