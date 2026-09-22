<?php
require_once 'config.php';

// Fix preorder_items row 39 (the main phone)
$stmt1 = $conn->prepare("
    UPDATE preorder_items 
    SET item_description = 'IPHONE 16 128GB WHITE',
        item_code = 'IPHONE-16-128GB-WHITE',
        imei = 'NBCNCYESA2',
        quantity = 1,
        price = 49990.00,
        total_payment = 49990.00,
        amount_paid = 49990.00,
        status = 'claimed',
        dr_number = 'PO-2026-002',
        claimed_at = '2026-08-26 13:52:17'
    WHERE id = 39 AND preorder_id = 43
");
$stmt1->execute();
echo "Updated preorder_items id 39: " . $stmt1->affected_rows . " rows affected\n";

// Check if freebie row already exists in preorder_items for preorder_id 43
$chk = $conn->query("SELECT id FROM preorder_items WHERE preorder_id = 43 AND item_code = 'IPHONE-16-128GB-CASE'");
if ($chk && $chk->num_rows == 0) {
    // Insert freebie row in preorder_items
    $stmt2 = $conn->prepare("
        INSERT INTO preorder_items (
            preorder_id,
            family_code,
            item_description,
            item_code,
            imei,
            quantity,
            price,
            total_payment,
            amount_paid,
            payment_method,
            status,
            dr_number,
            claimed_at
        ) VALUES (43, 'IPHONE 16 128GB CASE', 'IPHONE 16 128GB CASE', 'IPHONE-16-128GB-CASE', '', 1, 0.00, 0.00, 0.00, 'completed', 'claimed', 'PO-2026-003', '2026-08-26 13:52:17')
    ");
    $stmt2->execute();
    echo "Inserted freebie into preorder_items: " . $stmt2->affected_rows . " rows affected\n";
} else {
    echo "Freebie item already exists in preorder_items\n";
}

// Fix sales_entry_items
$conn->query("UPDATE sales_entry_items SET item_description = 'IPHONE 16 128GB WHITE', price = 49990.00 WHERE id = 241");
$conn->query("UPDATE sales_entry_items SET item_description = 'IPHONE 16 128GB CASE', price = 0.00 WHERE id = 242");
echo "Updated sales_entry_items 241 & 242\n";

echo "DONE!\n";
