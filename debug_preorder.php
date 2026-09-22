<?php
require_once 'session_check.php';
include 'config.php';

// Check a specific pre-order
$invoice_no = 'PRE-20260702-0001'; // Change this to your invoice number

$stmt = $conn->prepare("SELECT * FROM preorders WHERE invoice_no = ?");
$stmt->bind_param("s", $invoice_no);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $preorder = $result->fetch_assoc();
    
    echo "<h2>Pre-Order Debug Information</h2>";
    echo "<h3>Invoice No: " . $preorder['invoice_no'] . "</h3>";
    echo "<p><strong>Total Amount:</strong> " . $preorder['total_amount'] . "</p>";
    echo "<p><strong>Status:</strong> " . $preorder['status'] . "</p>";
    echo "<p><strong>Payment Data (Raw):</strong></p>";
    echo "<pre>" . htmlspecialchars($preorder['payment_data']) . "</pre>";
    
    if (!empty($preorder['payment_data'])) {
        $payment_data = json_decode($preorder['payment_data'], true);
        echo "<p><strong>Payment Data (Parsed):</strong></p>";
        echo "<pre>" . print_r($payment_data, true) . "</pre>";
    } else {
        echo "<p style='color: red;'><strong>Payment Data is EMPTY or NULL!</strong></p>";
    }
    
    echo "<hr>";
    echo "<h3>All Columns:</h3>";
    echo "<pre>" . print_r($preorder, true) . "</pre>";
    
} else {
    echo "Pre-order not found!";
}

$stmt->close();
?>
