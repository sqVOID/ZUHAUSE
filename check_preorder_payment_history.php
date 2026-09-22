<?php
/**
 * Diagnostic Script: Check Preorder Payment History
 * 
 * This script checks if a specific preorder has payment history entries
 * and helps diagnose why it might not be showing in the report.
 */

require_once 'config.php';

// Change this to the invoice number you want to check
$invoice_to_check = 'PRE-20260825-0002'; // CHANGE THIS

echo "<!DOCTYPE html><html><head><title>Check Payment History</title>";
echo "<style>body{font-family:Arial;padding:20px;max-width:1000px;margin:0 auto;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} .info{color:blue;} .warning{color:orange;font-weight:bold;} pre{background:#f5f5f5;padding:10px;border:1px solid #ccc;overflow-x:auto;} table{border-collapse:collapse;width:100%;margin:10px 0;} th,td{border:1px solid #ccc;padding:8px;text-align:left;} th{background:#f0f0f0;}</style>";
echo "</head><body>";
echo "<h1>Check Preorder Payment History</h1>";
echo "<p class='info'>Checking invoice: <strong>$invoice_to_check</strong></p>";

try {
    // Step 1: Check if preorder exists
    echo "<h2>Step 1: Check if preorder exists</h2>";
    $stmt = $conn->prepare("SELECT * FROM preorders WHERE invoice_no = ?");
    $stmt->bind_param("s", $invoice_to_check);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "<p class='error'>❌ Preorder $invoice_to_check not found in preorders table!</p>";
        echo "<h3>Try a different invoice number:</h3>";
        $recent = $conn->query("SELECT invoice_no, status, created_at FROM preorders ORDER BY created_at DESC LIMIT 10");
        if ($recent && $recent->num_rows > 0) {
            echo "<table><tr><th>Invoice No</th><th>Status</th><th>Created At</th></tr>";
            while ($row = $recent->fetch_assoc()) {
                echo "<tr><td>{$row['invoice_no']}</td><td>{$row['status']}</td><td>{$row['created_at']}</td></tr>";
            }
            echo "</table>";
        }
        exit;
    }
    
    $preorder = $result->fetch_assoc();
    $stmt->close();
    
    echo "<p class='success'>✓ Preorder found!</p>";
    echo "<table>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>{$preorder['id']}</td></tr>";
    echo "<tr><td>Invoice No</td><td>{$preorder['invoice_no']}</td></tr>";
    echo "<tr><td>Total Amount</td><td>₱" . number_format($preorder['total_amount'], 2) . "</td></tr>";
    echo "<tr><td>Status</td><td><strong>{$preorder['status']}</strong></td></tr>";
    echo "<tr><td>Created At</td><td>{$preorder['created_at']}</td></tr>";
    echo "<tr><td>Updated At</td><td>{$preorder['updated_at']}</td></tr>";
    echo "<tr><td>Completed At</td><td>" . ($preorder['completed_at'] ?? 'NULL') . "</td></tr>";
    echo "<tr><td>Branch Code</td><td>{$preorder['branch_code']}</td></tr>";
    echo "</table>";
    
    // Step 2: Check payment_data
    echo "<h2>Step 2: Check payment_data</h2>";
    if (empty($preorder['payment_data'])) {
        echo "<p class='error'>❌ No payment_data in preorder! This preorder has no payment information.</p>";
    } else {
        echo "<p class='success'>✓ Payment data exists</p>";
        $payment_data = json_decode($preorder['payment_data'], true);
        echo "<pre>" . json_encode($payment_data, JSON_PRETTY_PRINT) . "</pre>";
        
        // Parse payments
        $payments_array = [];
        if (isset($payment_data['payment_type']) && $payment_data['payment_type'] === 'multiple') {
            $payments_array = $payment_data['payments'];
            echo "<p class='info'>Multiple payments detected: " . count($payments_array) . " payment(s)</p>";
        } else {
            $payments_array = [$payment_data];
            echo "<p class='info'>Single payment detected</p>";
        }
        
        echo "<h3>Payment Breakdown:</h3>";
        echo "<table>";
        echo "<tr><th>#</th><th>Type</th><th>Amount</th></tr>";
        $total_paid = 0;
        foreach ($payments_array as $index => $payment) {
            $amount = isset($payment['amount']) ? floatval(str_replace(',', '', $payment['amount'])) : 0;
            $total_paid += $amount;
            echo "<tr>";
            echo "<td>" . ($index + 1) . "</td>";
            echo "<td>" . ($payment['payment_type'] ?? 'unknown') . "</td>";
            echo "<td>₱" . number_format($amount, 2) . "</td>";
            echo "</tr>";
        }
        echo "<tr style='background:#ffffcc;font-weight:bold;'>";
        echo "<td colspan='2'>Total Paid</td>";
        echo "<td>₱" . number_format($total_paid, 2) . "</td>";
        echo "</tr>";
        echo "<tr style='background:#ffeeee;font-weight:bold;'>";
        echo "<td colspan='2'>Remaining Balance</td>";
        echo "<td>₱" . number_format($preorder['total_amount'] - $total_paid, 2) . "</td>";
        echo "</tr>";
        echo "</table>";
    }
    
    // Step 3: Check payment history table
    echo "<h2>Step 3: Check payment history records</h2>";
    $history_stmt = $conn->prepare("
        SELECT * FROM preorder_payment_history 
        WHERE preorder_id = ? 
        ORDER BY payment_sequence
    ");
    $history_stmt->bind_param("i", $preorder['id']);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
    
    if ($history_result->num_rows === 0) {
        echo "<p class='warning'>⚠ NO payment history records found!</p>";
        echo "<p class='info'>This means the payment was made BEFORE the payment history table was created, or the migration didn't capture it.</p>";
        
        echo "<h3>Solution: Re-run Migration</h3>";
        echo "<p>Click the button below to migrate this specific preorder:</p>";
        echo "<form method='post' style='display:inline;'>";
        echo "<input type='hidden' name='migrate_invoice' value='$invoice_to_check'>";
        echo "<button type='submit' style='padding:10px 20px;background:#4CAF50;color:white;border:none;border-radius:4px;cursor:pointer;font-size:14px;'>Migrate This Preorder</button>";
        echo "</form>";
        
        // Handle migration
        if (isset($_POST['migrate_invoice']) && $_POST['migrate_invoice'] === $invoice_to_check) {
            echo "<div style='background:#e8f5e9;padding:15px;margin:15px 0;border-left:4px solid #4CAF50;'>";
            echo "<h4>Migration Result:</h4>";
            
            $payment_data = json_decode($preorder['payment_data'], true);
            if (!$payment_data) {
                echo "<p class='error'>❌ Invalid payment data, cannot migrate</p>";
            } else {
                $payments_array = [];
                if (isset($payment_data['payment_type']) && $payment_data['payment_type'] === 'multiple') {
                    $payments_array = $payment_data['payments'];
                } else {
                    $payments_array = [$payment_data];
                }
                
                $sequence = 1;
                $running_balance = floatval($preorder['total_amount']);
                $migrated = 0;
                
                foreach ($payments_array as $payment) {
                    $payment_type = $payment['payment_type'] ?? 'unknown';
                    $amount = isset($payment['amount']) ? floatval(str_replace(',', '', $payment['amount'])) : 0;
                    
                    if ($amount <= 0) {
                        echo "<p class='warning'>⊗ Skipped payment #{$sequence} - zero amount</p>";
                        $sequence++;
                        continue;
                    }
                    
                    $payment_method = ucfirst(str_replace('_', ' ', $payment_type));
                    if ($payment_type === 'ewallet') {
                        $payment_method = $payment['ewallet_type'] ?? 'E-Wallet';
                    } elseif ($payment_type === 'online_banking') {
                        $payment_method = $payment['bank_name'] ?? 'Online Banking';
                    }
                    
                    $balance_before = $running_balance;
                    $balance_after = $running_balance - $amount;
                    $running_balance = $balance_after;
                    $status_after = ($balance_after <= 0.01) ? 'completed' : 'partial';
                    
                    // Use completed_at for last payment, else use created_at
                    $payment_date = ($sequence === count($payments_array) && !empty($preorder['completed_at'])) 
                                    ? $preorder['completed_at'] 
                                    : $preorder['created_at'];
                    
                    $insert_stmt = $conn->prepare("
                        INSERT INTO preorder_payment_history 
                        (preorder_id, invoice_no, payment_date, payment_type, payment_method, 
                         amount, payment_data, payment_sequence, status_after_payment, 
                         balance_before, balance_after, branch_code, encoder)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $payment_json = json_encode($payment);
                    $insert_stmt->bind_param(
                        "issssdsisddss",
                        $preorder['id'],
                        $preorder['invoice_no'],
                        $payment_date,
                        $payment_type,
                        $payment_method,
                        $amount,
                        $payment_json,
                        $sequence,
                        $status_after,
                        $balance_before,
                        $balance_after,
                        $preorder['branch_code'],
                        $preorder['encoder']
                    );
                    
                    if ($insert_stmt->execute()) {
                        echo "<p class='success'>✓ Migrated payment #{$sequence}: ₱" . number_format($amount, 2) . " on " . date('M d, Y H:i', strtotime($payment_date)) . "</p>";
                        $migrated++;
                    } else {
                        echo "<p class='error'>❌ Failed to migrate payment #{$sequence}: " . $insert_stmt->error . "</p>";
                    }
                    $insert_stmt->close();
                    
                    $sequence++;
                }
                
                if ($migrated > 0) {
                    echo "<p class='success'><strong>✓ Successfully migrated $migrated payment(s)!</strong></p>";
                    echo "<p><a href='check_preorder_payment_history.php'>Refresh this page</a> to see the results.</p>";
                }
            }
            echo "</div>";
        }
        
    } else {
        echo "<p class='success'>✓ Found {$history_result->num_rows} payment history record(s)</p>";
        echo "<table>";
        echo "<tr><th>Seq</th><th>Payment Date</th><th>Type</th><th>Method</th><th>Amount</th><th>Balance Before</th><th>Balance After</th><th>Status</th></tr>";
        while ($history = $history_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$history['payment_sequence']}</td>";
            echo "<td>" . date('M d, Y H:i', strtotime($history['payment_date'])) . "</td>";
            echo "<td>{$history['payment_type']}</td>";
            echo "<td>{$history['payment_method']}</td>";
            echo "<td>₱" . number_format($history['amount'], 2) . "</td>";
            echo "<td>₱" . number_format($history['balance_before'], 2) . "</td>";
            echo "<td>₱" . number_format($history['balance_after'], 2) . "</td>";
            echo "<td>{$history['status_after_payment']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p class='success'>✓ Payment history looks good! This preorder should appear in the report.</p>";
    }
    $history_stmt->close();
    
    // Step 4: Test report query
    if ($history_result->num_rows > 0) {
        echo "<h2>Step 4: Test Report Query</h2>";
        $test_query = "
            SELECT 
                ph.payment_date,
                ph.amount,
                ph.payment_sequence,
                p.invoice_no,
                p.branch_code
            FROM preorder_payment_history ph
            INNER JOIN preorders p ON ph.preorder_id = p.id
            WHERE p.invoice_no = ?
            ORDER BY ph.payment_sequence
        ";
        $test_stmt = $conn->prepare($test_query);
        $test_stmt->bind_param("s", $invoice_to_check);
        $test_stmt->execute();
        $test_result = $test_stmt->get_result();
        
        echo "<p class='info'>This preorder will appear on the following dates in the report:</p>";
        echo "<ul>";
        while ($test_row = $test_result->fetch_assoc()) {
            echo "<li><strong>" . date('M d, Y', strtotime($test_row['payment_date'])) . "</strong> - Payment #{$test_row['payment_sequence']}: ₱" . number_format($test_row['amount'], 2) . "</li>";
        }
        echo "</ul>";
        $test_stmt->close();
    }
    
    echo "<hr>";
    echo "<p><a href='preorderreport.php'>Go to Preorder Report</a> | <a href='preorder2.php'>Go to Preorder2</a></p>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>❌ Error</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
}

echo "</body></html>";

$conn->close();
?>
