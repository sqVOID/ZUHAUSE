<?php
/**
 * Test Script for Preorder2 Payment Save Functionality
 * 
 * This script helps verify that save_preorder2_payment.php works correctly
 * by simulating payment updates for existing preorders.
 * 
 * Usage: Run this script in browser after creating a test preorder
 */

require_once 'session_check.php';
include 'config.php';

// Set test preorder ID (change this to a real preorder ID from your database)
$test_preorder_id = 1; // CHANGE THIS TO TEST

echo "<!DOCTYPE html><html><head><title>Test Preorder2 Payment</title>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border:1px solid #ccc;}</style>";
echo "</head><body>";
echo "<h1>Preorder2 Payment Save Test</h1>";

// Step 1: Get existing preorder data
echo "<h2>Step 1: Fetching Test Preorder (ID: $test_preorder_id)</h2>";
$stmt = $conn->prepare("SELECT * FROM preorders WHERE id = ?");
$stmt->bind_param("i", $test_preorder_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p class='error'>❌ Preorder ID $test_preorder_id not found. Please update \$test_preorder_id with a valid ID.</p>";
    echo "<h3>Available Preorders:</h3>";
    $all_preorders = $conn->query("SELECT id, invoice_no, total_amount, status FROM preorders ORDER BY id DESC LIMIT 10");
    if ($all_preorders && $all_preorders->num_rows > 0) {
        echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Invoice No</th><th>Total Amount</th><th>Status</th></tr>";
        while ($po = $all_preorders->fetch_assoc()) {
            echo "<tr><td>{$po['id']}</td><td>{$po['invoice_no']}</td><td>₱" . number_format($po['total_amount'], 2) . "</td><td>{$po['status']}</td></tr>";
        }
        echo "</table>";
    }
    exit;
}

$preorder = $result->fetch_assoc();
$stmt->close();

echo "<p class='success'>✓ Found preorder: {$preorder['invoice_no']}</p>";
echo "<pre>" . print_r($preorder, true) . "</pre>";

// Step 2: Calculate existing payments
echo "<h2>Step 2: Calculate Existing Payments</h2>";
$total_amount = floatval($preorder['total_amount']);
$existing_payment_data = json_decode($preorder['payment_data'], true);
$total_paid = 0;

if ($existing_payment_data) {
    echo "<p class='info'>Existing Payment Data:</p>";
    echo "<pre>" . json_encode($existing_payment_data, JSON_PRETTY_PRINT) . "</pre>";
    
    // Calculate total paid from existing payments
    if (isset($existing_payment_data['payment_type']) && $existing_payment_data['payment_type'] === 'multiple') {
        foreach ($existing_payment_data['payments'] as $payment) {
            if (isset($payment['amount'])) {
                $amount = floatval(str_replace(',', '', $payment['amount']));
                $total_paid += $amount;
            }
        }
    } else if (isset($existing_payment_data['amount'])) {
        $total_paid = floatval(str_replace(',', '', $existing_payment_data['amount']));
    }
}

$remaining_balance = $total_amount - $total_paid;

echo "<p><strong>Total Amount:</strong> ₱" . number_format($total_amount, 2) . "</p>";
echo "<p><strong>Already Paid:</strong> ₱" . number_format($total_paid, 2) . "</p>";
echo "<p><strong>Remaining Balance:</strong> ₱" . number_format($remaining_balance, 2) . "</p>";

// Step 3: Simulate new payment
if ($remaining_balance > 0) {
    echo "<h2>Step 3: Simulating New Payment</h2>";
    
    // Create test payment data (paying the remaining balance with cash)
    $new_payment = [
        'payment_type' => 'cash',
        'amount' => number_format($remaining_balance, 2, '.', '')
    ];
    
    echo "<p class='info'>New Payment Data:</p>";
    echo "<pre>" . json_encode($new_payment, JSON_PRETTY_PRINT) . "</pre>";
    
    // Prepare data for API call
    $post_data = [
        'preorder_id' => $test_preorder_id,
        'payment_data' => $new_payment
    ];
    
    echo "<p class='info'>Sending to save_preorder2_payment.php:</p>";
    echo "<pre>" . json_encode($post_data, JSON_PRETTY_PRINT) . "</pre>";
    
    // Make the API call using cURL
    $ch = curl_init('http://localhost/ZUHAUSE/save_preorder2_payment.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<h2>Step 4: Response from API</h2>";
    echo "<p><strong>HTTP Code:</strong> $http_code</p>";
    
    $response_data = json_decode($response, true);
    if ($response_data) {
        if ($response_data['status'] === 'success') {
            echo "<p class='success'>✓ Payment saved successfully!</p>";
        } else {
            echo "<p class='error'>❌ Error: {$response_data['message']}</p>";
        }
        echo "<pre>" . json_encode($response_data, JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<p class='error'>❌ Invalid response</p>";
        echo "<pre>$response</pre>";
    }
    
    // Step 5: Verify database update
    echo "<h2>Step 5: Verify Database Update</h2>";
    $verify_stmt = $conn->prepare("SELECT payment_data, status FROM preorders WHERE id = ?");
    $verify_stmt->bind_param("i", $test_preorder_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $updated_preorder = $verify_result->fetch_assoc();
    $verify_stmt->close();
    
    echo "<p class='info'>Updated Preorder Data:</p>";
    echo "<pre>" . print_r($updated_preorder, true) . "</pre>";
    
    $updated_payment_data = json_decode($updated_preorder['payment_data'], true);
    echo "<p class='info'>Parsed Payment Data:</p>";
    echo "<pre>" . json_encode($updated_payment_data, JSON_PRETTY_PRINT) . "</pre>";
    
} else {
    echo "<h2>Step 3: Already Fully Paid</h2>";
    echo "<p class='info'>This preorder is already fully paid. No additional payment needed.</p>";
}

echo "<hr>";
echo "<h3>Test Complete!</h3>";
echo "<p><a href='preorder2.php'>Go to Preorder2 Page</a></p>";

echo "</body></html>";

$conn->close();
?>
