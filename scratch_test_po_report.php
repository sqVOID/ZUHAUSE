<?php
require 'config.php';

$date_from = '2026-09-22';
$date_to = '2026-09-22';
$branch_code = 'ZUHINFA';

$po_query = $conn->prepare("
    SELECT
        ph.id AS ph_id,
        ph.invoice_no AS payment_invoice_no,
        ph.payment_date,
        ph.amount AS payment_amount,
        ph.payment_type AS ph_payment_type,
        ph.payment_method AS ph_payment_method,
        ph.payment_data AS ph_payment_data,
        ph.payment_sequence,
        ph.encoder AS ph_encoder,
        p.id AS preorder_id,
        p.invoice_no AS preorder_no,
        p.first_name,
        p.last_name,
        p.assisted_by,
        p.remarks,
        p.total_qty,
        p.total_amount AS preorder_total_srp,
        p.discount,
        p.encoder AS preorder_encoder,
        p.branch_code,
        p.status AS preorder_status,
        p.created_at AS preorder_created_at
    FROM preorder_payment_history ph
    INNER JOIN preorders p ON ph.preorder_id = p.id
    WHERE DATE(ph.payment_date) BETWEEN ? AND ?
      AND p.branch_code = ?
    ORDER BY ph.payment_date ASC, ph.payment_sequence ASC
");
$po_query->bind_param('sss', $date_from, $date_to, $branch_code);
$po_query->execute();
$res = $po_query->get_result();
while ($row = $res->fetch_assoc()) {
    echo "Invoice: " . $row['payment_invoice_no'] . " | Amount: " . $row['payment_amount'] . " | Method: " . $row['ph_payment_method'] . " | Date: " . $row['payment_date'] . "\n";
}
