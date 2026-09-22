<?php
include 'config.php';
$po_id = 161;
$r = $conn->query("
    SELECT poi.family_code, MAX(poi.quantity) as quantity, COALESCE(alloc.total_allocated, 0) as allocated_quantity
    FROM purchase_order_items poi
    LEFT JOIN (
        SELECT po_id, family_code, SUM(quantity) as total_allocated
        FROM purchase_order_allocations WHERE po_id = $po_id
        GROUP BY po_id, family_code
    ) alloc ON alloc.po_id = poi.po_id AND alloc.family_code = poi.family_code
    WHERE poi.po_id = $po_id
    GROUP BY poi.family_code, alloc.total_allocated
");
while ($row = $r->fetch_assoc()) {
    $q = (int)$row['quantity'];
    $a = (int)$row['allocated_quantity'];
    if ($a > $q) $q = $a;
    echo $row['family_code'] . " display_qty=$q stored={$row['quantity']} alloc=$a\n";
}
