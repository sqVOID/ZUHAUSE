<?php
include 'config.php';

// 1. Remove extra case items from preorder_items
$conn->query("DELETE FROM preorder_items WHERE id IN (48, 50) AND (item_code LIKE '%CASE%' OR item_description LIKE '%CASE%')");
echo "Deleted extra preorder_items. Affected rows: " . $conn->affected_rows . "\n";

// 2. Fix preorder_payment_history for sequence 2
$conn->query("UPDATE preorder_payment_history SET amount = 4000.00, balance_after = 0.00 WHERE id IN (53, 55)");
echo "Updated preorder_payment_history. Affected rows: " . $conn->affected_rows . "\n";

// 3. Fix sales_entry total_qty and total_amount for the claimed sales entries
$conn->query("UPDATE sales_entry SET total_qty = 2, total_amount = 40090.00 WHERE id IN (172, 173)");
echo "Updated sales_entry. Affected rows: " . $conn->affected_rows . "\n";

// 4. Verify preorder report output for PRE-20260826-0007
echo "\n--- VERIFICATION: PREORDER REPORT FOR PRE-20260826-0007 ---\n";
$q = "SELECT 
        p.invoice_no as preorder_no,
        CONCAT(p.first_name, ' ', p.last_name) as customer_name,
        COALESCE(NULLIF(pi.item_description, ''), pi.family_code) as item_description,
        pi.imei,
        pi.quantity,
        pi.price as unit_price,
        (pi.quantity * pi.price) as total_amount,
        ph.amount as payment_amount,
        p.status,
        ph.payment_date as date_created,
        p.claimed_at
      FROM preorder_payment_history ph
      INNER JOIN preorders p ON ph.preorder_id = p.id
      LEFT JOIN preorder_items pi ON p.id = pi.preorder_id
      WHERE p.invoice_no = 'PRE-20260826-0007'
      ORDER BY ph.payment_date ASC";
$res = $conn->query($q);
while ($r = $res->fetch_assoc()) {
    echo sprintf(
        "No: %s | Customer: %s | Item: %s | Qty: %d | Total: %.2f | Payment: %.2f | Date: %s | Claimed: %s\n",
        $r['preorder_no'],
        $r['customer_name'],
        $r['item_description'],
        $r['quantity'],
        $r['total_amount'],
        $r['payment_amount'],
        $r['date_created'],
        $r['claimed_at']
    );
}
