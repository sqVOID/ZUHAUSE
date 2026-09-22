<?php
require 'config.php';

$date_from = '2026-09-21';
$date_to = '2026-09-21';
$search_branch = 'ZUHAUSE INFANTA';

// Resolve branch code
$branch_code = '';
$branch_query = $conn->prepare("SELECT branch_code FROM branches WHERE branch_name = ?");
$branch_query->bind_param("s", $search_branch);
$branch_query->execute();
$branch_res = $branch_query->get_result();
if ($branch_res && $branch_res->num_rows > 0) {
    $branch_data = $branch_res->fetch_assoc();
    $branch_code = $branch_data['branch_code'];
}
$branch_query->close();

echo "Branch code: $branch_code\n";

// 1. Sales query (excluding claimpreorder)
$sales_query = $conn->prepare("
    SELECT 
        se.id,
        se.invoice_no,
        se.first_name,
        se.last_name,
        se.assisted_by,
        se.remarks,
        se.total_qty,
        se.total_amount,
        se.discount,
        se.commission,
        se.payment_data,
        se.encoder,
        se.branch_code,
        se.created_at,
        se.status,
        se.upgrade,
        se.original_invoice_no,
        se.page_type
    FROM sales_entry se 
    WHERE DATE(se.created_at) BETWEEN ? AND ?
      AND se.branch_code = ?
      AND (se.page_type != 'claimpreorder' OR se.page_type IS NULL)
    ORDER BY se.created_at ASC
");
$sales_query->bind_param("sss", $date_from, $date_to, $branch_code);
$sales_query->execute();
$sales_result = $sales_query->get_result();
$sales_data = [];
while ($sale = $sales_result->fetch_assoc()) {
    $sales_data[] = $sale;
}
echo "Sales count: " . count($sales_data) . "\n";
print_r($sales_data);

// 2. Preorder payments query from preorder_payment_history
$payment_query = "
    SELECT
        ph.id              AS ph_id,
        ph.invoice_no      AS payment_invoice_no,
        ph.payment_date,
        ph.amount          AS payment_amount,
        ph.payment_type    AS ph_payment_type,
        ph.payment_method  AS ph_payment_method,
        ph.payment_data    AS ph_payment_data,
        ph.payment_sequence,
        ph.encoder         AS ph_encoder,
        p.id               AS preorder_id,
        p.invoice_no       AS preorder_no,
        p.first_name,
        p.last_name,
        p.assisted_by,
        p.remarks,
        p.total_qty,
        p.total_amount     AS preorder_total_srp,
        p.discount,
        p.encoder          AS preorder_encoder,
        p.branch_code,
        p.status           AS preorder_status
    FROM preorder_payment_history ph
    INNER JOIN preorders p ON ph.preorder_id = p.id
    WHERE DATE(ph.payment_date) BETWEEN ? AND ?
      AND p.branch_code = ?
    ORDER BY ph.payment_date ASC, ph.payment_sequence ASC
";
$po_stmt = $conn->prepare($payment_query);
$po_stmt->bind_param("sss", $date_from, $date_to, $branch_code);
$po_stmt->execute();
$po_result = $po_stmt->get_result();

$po_rows = [];
while ($po = $po_result->fetch_assoc()) {
    $po_rows[] = $po;
}
echo "Preorder payment count: " . count($po_rows) . "\n";
foreach ($po_rows as $pr) {
    echo "Payment ID: {$pr['ph_id']} | Invoice: {$pr['payment_invoice_no']} | Seq: {$pr['payment_sequence']} | Amount: {$pr['payment_amount']} | Method: {$pr['ph_payment_method']}\n";
}
