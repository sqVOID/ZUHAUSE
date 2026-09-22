<?php
// Fix stock_on_hand records with wrong dr_number (VOID-RESTORE, UPGD-*, or ST-*)
require_once 'session_check.php';
header('Content-Type: text/html; charset=utf-8');

$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS dr_number VARCHAR(100) DEFAULT '' AFTER item_code");

echo "<h2>Stock On Hand Repair</h2><pre>";

// Target: VOID-RESTORE, UPGD-*, and ST-* dr_numbers with IMEI
$bad = $conn->query("
    SELECT id, imei, item_code, dr_number 
    FROM stock_on_hand 
    WHERE (
        dr_number = 'VOID-RESTORE' 
        OR dr_number LIKE 'UPGD-%' 
        OR dr_number LIKE 'ST-%'
    )
    AND imei IS NOT NULL AND imei != ''
");

$fixed = 0;
$notfound = 0;
if ($bad && $bad->num_rows > 0) {
    while ($row = $bad->fetch_assoc()) {
        $id = (int)$row['id'];
        $imei = trim($row['imei']);
        $item_code = trim($row['item_code']);
        $old_dr = $row['dr_number'];
        $new_dr = null;

        // Method 1: Borrow from sibling in same stock (same item_code, valid PO-)
        $sib = $conn->prepare("SELECT dr_number FROM stock_on_hand WHERE UPPER(TRIM(item_code)) = UPPER(?) AND dr_number LIKE 'PO-%' AND imei != ? ORDER BY id ASC LIMIT 1");
        $sib->bind_param("ss", $item_code, $imei);
        $sib->execute();
        $sr = $sib->get_result()->fetch_assoc();
        $sib->close();
        if ($sr) {
            $new_dr = $sr['dr_number'];
        }

        // Method 2: Check sales_entry_items for a saved dr_number for this IMEI
        if (!$new_dr) {
            $sq = $conn->prepare("SELECT dr_number FROM sales_entry_items WHERE UPPER(TRIM(imei)) = UPPER(?) AND dr_number IS NOT NULL AND dr_number LIKE 'PO-%' ORDER BY id DESC LIMIT 1");
            $sq->bind_param("s", $imei);
            $sq->execute();
            $sr2 = $sq->get_result()->fetch_assoc();
            $sq->close();
            if ($sr2 && !empty($sr2['dr_number'])) {
                $new_dr = $sr2['dr_number'];
            }
        }

        // Method 3: Exact model match in received purchase orders
        if (!$new_dr) {
            $pq = $conn->prepare("SELECT po.po_number FROM purchase_orders po JOIN purchase_order_items poi ON poi.po_id = po.id WHERE UPPER(TRIM(poi.item_model)) = UPPER(?) AND po.status IN ('Received','Completed','Incomplete') ORDER BY po.id DESC LIMIT 1");
            $pq->bind_param("s", $item_code);
            $pq->execute();
            $pr = $pq->get_result()->fetch_assoc();
            $pq->close();
            if ($pr) {
                $new_dr = $pr['po_number'];
            }
        }

        // Method 4: LIKE match on item_model
        if (!$new_dr) {
            $parts = explode('-', $item_code);
            $search = '%' . implode('%', array_slice($parts, 0, 3)) . '%';
            $pq = $conn->prepare("SELECT po.po_number FROM purchase_orders po JOIN purchase_order_items poi ON poi.po_id = po.id WHERE UPPER(poi.item_model) LIKE UPPER(?) AND po.status IN ('Received','Completed','Incomplete') ORDER BY po.id DESC LIMIT 1");
            $pq->bind_param("s", $search);
            $pq->execute();
            $pr = $pq->get_result()->fetch_assoc();
            $pq->close();
            if ($pr) {
                $new_dr = $pr['po_number'];
            }
        }

        if ($new_dr) {
            $upd = $conn->prepare("UPDATE stock_on_hand SET dr_number = ? WHERE id = ?");
            $upd->bind_param("si", $new_dr, $id);
            $upd->execute();
            $upd->close();
            echo "✅ Fixed  ID=$id  IMEI=$imei  [$old_dr] → [$new_dr]\n";
            $fixed++;
        }
        else {
            echo "❌ No fix: ID=$id  IMEI=$imei  item=$item_code  current=[$old_dr]\n";
            $notfound++;
        }
    }
}
else {
    echo "✅ No bad records found. All clean!\n";
}

echo "\nDone. Fixed: $fixed | Needs manual update: $notfound\n";
echo "</pre>";
$conn->close();
echo "<p><strong>Done!</strong> <a href='sohandserial.php'>Check Stock On Hand →</a></p>";
?>
