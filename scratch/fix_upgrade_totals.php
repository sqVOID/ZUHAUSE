<?php
include 'config.php';

// Fix existing invoice 0122: total_amount should be cash paid (1400), not SRP of new item (2695)
// The payment_data.Amount = 1400 is the correct cash paid

$res = $conn->query("SELECT id, total_amount, payment_data FROM sales_entry WHERE invoice_no = '0122' AND original_invoice_no IS NOT NULL AND original_invoice_no != ''");
if ($res && $row = $res->fetch_assoc()) {
    $pd = json_decode($row['payment_data'], true);
    $amtRaw = $pd['Amount'] ?? $pd['amount'] ?? '';
    $cashPaid = floatval(str_replace(',', '', (string)$amtRaw));
    
    if ($cashPaid > 0 && abs($row['total_amount'] - $cashPaid) > 0.01) {
        echo "Fixing invoice 0122: total_amount {$row['total_amount']} -> {$cashPaid}" . PHP_EOL;
        $stmt = $conn->prepare("UPDATE sales_entry SET total_amount = ? WHERE id = ?");
        $stmt->bind_param("di", $cashPaid, $row['id']);
        if ($stmt->execute()) {
            echo "Fixed!" . PHP_EOL;
        } else {
            echo "Error: " . $stmt->error . PHP_EOL;
        }
        $stmt->close();
    } else {
        echo "Invoice 0122 already correct: total_amount = {$row['total_amount']}, cashPaid = {$cashPaid}" . PHP_EOL;
    }
} else {
    echo "Invoice 0122 not found as upgrade invoice." . PHP_EOL;
}

// Also fix any other upgrade invoices where total_amount != cash paid from payment_data
echo PHP_EOL . "Checking other upgrade invoices..." . PHP_EOL;
$res2 = $conn->query("SELECT id, invoice_no, total_amount, payment_data FROM sales_entry WHERE upgrade = 'UPGD' AND original_invoice_no IS NOT NULL AND original_invoice_no != ''");
while ($row = $res2->fetch_assoc()) {
    $pd = json_decode($row['payment_data'], true);
    $amtRaw = $pd['Amount'] ?? $pd['amount'] ?? '';
    $cashPaid = floatval(str_replace(',', '', (string)$amtRaw));
    
    if ($cashPaid > 0 && abs($row['total_amount'] - $cashPaid) > 0.01) {
        echo "Invoice {$row['invoice_no']}: total_amount {$row['total_amount']} -> {$cashPaid}" . PHP_EOL;
        $stmt = $conn->prepare("UPDATE sales_entry SET total_amount = ? WHERE id = ?");
        $stmt->bind_param("di", $cashPaid, $row['id']);
        $stmt->execute();
        $stmt->close();
        echo "  Fixed!" . PHP_EOL;
    } else {
        echo "Invoice {$row['invoice_no']}: already correct ({$row['total_amount']})" . PHP_EOL;
    }
}
