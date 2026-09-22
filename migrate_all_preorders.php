<?php
/**
 * Migrate ALL Preorders to Payment History
 * 
 * This script migrates all existing preorders with payment data
 * to the payment history table in one go.
 */

require_once 'session_check.php';
require_once 'config.php';

echo "<!DOCTYPE html><html><head><title>Migrate All Preorders</title>";
echo "<style>body{font-family:Arial;padding:20px;max-width:1200px;margin:0 auto;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} .info{color:blue;} .warning{color:orange;font-weight:bold;} pre{background:#f5f5f5;padding:10px;border:1px solid #ccc;overflow-x:auto;} table{border-collapse:collapse;width:100%;margin:10px 0;font-size:12px;} th,td{border:1px solid #ccc;padding:6px;text-align:left;} th{background:#f0f0f0;position:sticky;top:0;}</style>";
echo "</head><body>";
echo "<h1>Migrate All Preorders to Payment History</h1>";

try {
    // Check if migration has been run
    $check_count = $conn->query("SELECT COUNT(*) as count FROM preorder_payment_history");
    $existing_count = 0;
    if ($check_count) {
        $row = $check_count->fetch_assoc();
        $existing_count = intval($row['count']);
    }
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'clear') {
            // Clear payment history
            echo "<h2>Clearing Payment History...</h2>";
            if ($conn->query("TRUNCATE TABLE preorder_payment_history")) {
                echo "<p class='success'>✓ Payment history table cleared!</p>";
                $existing_count = 0;
            } else {
                echo "<p class='error'>❌ Failed to clear: " . $conn->error . "</p>";
            }
        } elseif ($_POST['action'] === 'migrate') {
            // Run migration
            echo "<h2>Starting Migration...</h2>";
            
            $migrate_query = "
            SELECT 
                id, 
                invoice_no, 
                payment_data, 
                branch_code, 
                encoder, 
                created_at,
                updated_at,
                completed_at,
                total_amount,
                status
            FROM preorders 
            WHERE payment_data IS NOT NULL 
            AND payment_data != ''
            AND payment_data != 'null'
            ORDER BY created_at
            ";
            
            $result = $conn->query($migrate_query);
            
            if (!$result) {
                throw new Exception("Query failed: " . $conn->error);
            }
            
            echo "<p class='info'>Found {$result->num_rows} preorder(s) with payment data</p>";
            
            echo "<div style='max-height:400px;overflow-y:auto;border:1px solid #ccc;padding:10px;background:#f9f9f9;'>";
            echo "<table>";
            echo "<tr><th>Invoice</th><th>Seq</th><th>Payment Date</th><th>Type</th><th>Amount</th><th>Status</th><th>Result</th></tr>";
            
            $total_migrated = 0;
            $total_skipped = 0;
            $total_errors = 0;
            
            while ($preorder = $result->fetch_assoc()) {
                $payment_data = json_decode($preorder['payment_data'], true);
                
                if (!$payment_data || !is_array($payment_data)) {
                    echo "<tr><td>{$preorder['invoice_no']}</td><td colspan='6' class='error'>Invalid JSON</td></tr>";
                    $total_skipped++;
                    continue;
                }
                
                $payments_array = [];
                if (isset($payment_data['payment_type']) && $payment_data['payment_type'] === 'multiple') {
                    $payments_array = $payment_data['payments'];
                } else {
                    $payments_array = [$payment_data];
                }
                
                $sequence = 1;
                $running_balance = floatval($preorder['total_amount']);
                $total_payments = count($payments_array);
                
                foreach ($payments_array as $payment) {
                    $payment_type = $payment['payment_type'] ?? 'unknown';
                    $amount = 0;
                    
                    // Calculate amount
                    if (isset($payment['amount'])) {
                        $amount = floatval(str_replace(',', '', $payment['amount']));
                    } elseif ($payment_type === 'payment_partners') {
                        $loan_balance = isset($payment['loan_balance']) ? floatval(str_replace(',', '', $payment['loan_balance'])) : 0;
                        $cash_dp = isset($payment['cash_dp_amount']) ? floatval(str_replace(',', '', $payment['cash_dp_amount'])) : 0;
                        $gcash_dp = isset($payment['gcash_dp_amount']) ? floatval(str_replace(',', '', $payment['gcash_dp_amount'])) : 0;
                        $maya_dp = isset($payment['maya_dp_amount']) ? floatval(str_replace(',', '', $payment['maya_dp_amount'])) : 0;
                        $amount = $loan_balance + $cash_dp + $gcash_dp + $maya_dp;
                    }
                    
                    if ($amount <= 0) {
                        echo "<tr><td>{$preorder['invoice_no']}</td><td>$sequence</td><td colspan='5' class='warning'>Zero amount</td></tr>";
                        $total_skipped++;
                        $sequence++;
                        continue;
                    }
                    
                    // Get payment method
                    $payment_method = 'Unknown';
                    if ($payment_type === 'ewallet') {
                        $payment_method = $payment['ewallet_type'] ?? 'E-Wallet';
                    } elseif ($payment_type === 'online_banking') {
                        $payment_method = $payment['bank_name'] ?? 'Online Banking';
                    } elseif ($payment_type === 'payment_partners') {
                        $payment_method = $payment['payment_partner'] ?? 'Payment Partner';
                    } else {
                        $payment_method = ucfirst(str_replace('_', ' ', $payment_type));
                    }
                    
                    $balance_before = $running_balance;
                    $balance_after = $running_balance - $amount;
                    $running_balance = $balance_after;
                    $status_after = ($balance_after <= 0.01) ? 'completed' : 'partial';
                    
                    // Determine payment date
                    $payment_date = $preorder['created_at'];
                    if ($sequence === $total_payments && !empty($preorder['completed_at']) && $preorder['completed_at'] != '0000-00-00 00:00:00') {
                        $payment_date = $preorder['completed_at'];
                    } elseif ($sequence > 1 && !empty($preorder['updated_at']) && $preorder['updated_at'] != '0000-00-00 00:00:00') {
                        $payment_date = $preorder['updated_at'];
                    }
                    
                    // Insert into payment history
                    $insert_stmt = $conn->prepare("
                        INSERT INTO preorder_payment_history 
                        (preorder_id, invoice_no, payment_date, payment_type, payment_method, 
                         amount, payment_data, payment_sequence, status_after_payment, 
                         balance_before, balance_after, branch_code, encoder, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
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
                        echo "<tr style='background:#c8e6c9;'>";
                        echo "<td>{$preorder['invoice_no']}</td>";
                        echo "<td>$sequence</td>";
                        echo "<td>" . date('Y-m-d H:i', strtotime($payment_date)) . "</td>";
                        echo "<td>$payment_method</td>";
                        echo "<td>₱" . number_format($amount, 2) . "</td>";
                        echo "<td>$status_after</td>";
                        echo "<td class='success'>✓ Success</td>";
                        echo "</tr>";
                        $total_migrated++;
                    } else {
                        echo "<tr style='background:#ffcdd2;'>";
                        echo "<td>{$preorder['invoice_no']}</td>";
                        echo "<td>$sequence</td>";
                        echo "<td colspan='4'>" . $insert_stmt->error . "</td>";
                        echo "<td class='error'>✗ Failed</td>";
                        echo "</tr>";
                        $total_errors++;
                    }
                    $insert_stmt->close();
                    
                    $sequence++;
                }
            }
            
            echo "</table>";
            echo "</div>";
            
            echo "<div style='background:#e8f5e9;padding:15px;margin:15px 0;border-left:4px solid #4CAF50;'>";
            echo "<h3>Migration Complete!</h3>";
            echo "<p><strong>✓ Migrated:</strong> $total_migrated payment transaction(s)</p>";
            if ($total_skipped > 0) {
                echo "<p><strong>⊗ Skipped:</strong> $total_skipped record(s)</p>";
            }
            if ($total_errors > 0) {
                echo "<p><strong>✗ Errors:</strong> $total_errors record(s)</p>";
            }
            echo "</div>";
        }
    }
    
    // Show current status
    $check_count = $conn->query("SELECT COUNT(*) as count FROM preorder_payment_history");
    if ($check_count) {
        $row = $check_count->fetch_assoc();
        $existing_count = intval($row['count']);
    }
    
    echo "<div style='background:#e3f2fd;padding:15px;margin:15px 0;border-left:4px solid #2196F3;'>";
    echo "<h3>Current Status:</h3>";
    echo "<p><strong>Payment History Records:</strong> $existing_count</p>";
    
    $preorder_count = $conn->query("SELECT COUNT(*) as count FROM preorders WHERE payment_data IS NOT NULL AND payment_data != '' AND payment_data != 'null'");
    $total_preorders = 0;
    if ($preorder_count) {
        $row = $preorder_count->fetch_assoc();
        $total_preorders = intval($row['count']);
    }
    echo "<p><strong>Preorders with Payment Data:</strong> $total_preorders</p>";
    
    if ($existing_count === 0) {
        echo "<p class='warning'>⚠ No payment history records exist. Please run migration.</p>";
    } elseif ($existing_count < $total_preorders) {
        echo "<p class='warning'>⚠ Some preorders may not be migrated yet.</p>";
    } else {
        echo "<p class='success'>✓ All preorders appear to be migrated!</p>";
    }
    echo "</div>";
    
    // Action buttons
    echo "<div style='margin:20px 0;'>";
    echo "<form method='post' style='display:inline-block;margin-right:10px;'>";
    echo "<input type='hidden' name='action' value='migrate'>";
    if ($existing_count > 0) {
        echo "<button type='submit' onclick='return confirm(\"This will add new records. Existing records will NOT be deleted. Continue?\")' style='padding:12px 24px;background:#FF9800;color:white;border:none;border-radius:4px;cursor:pointer;font-size:14px;font-weight:bold;'>Re-Run Migration (Add New)</button>";
    } else {
        echo "<button type='submit' style='padding:12px 24px;background:#4CAF50;color:white;border:none;border-radius:4px;cursor:pointer;font-size:14px;font-weight:bold;'>▶ Start Migration</button>";
    }
    echo "</form>";
    
    if ($existing_count > 0) {
        echo "<form method='post' style='display:inline-block;margin-right:10px;'>";
        echo "<input type='hidden' name='action' value='clear'>";
        echo "<button type='submit' onclick='return confirm(\"This will DELETE ALL payment history records! Are you sure?\")' style='padding:12px 24px;background:#f44336;color:white;border:none;border-radius:4px;cursor:pointer;font-size:14px;font-weight:bold;'>🗑 Clear All & Start Fresh</button>";
        echo "</form>";
    }
    echo "</div>";
    
    // Sample data
    if ($existing_count > 0) {
        echo "<h3>Sample Payment History (Last 20)</h3>";
        $sample = $conn->query("
            SELECT 
                ph.invoice_no,
                ph.payment_date,
                ph.payment_type,
                ph.payment_method,
                ph.amount,
                ph.payment_sequence,
                ph.status_after_payment
            FROM preorder_payment_history ph
            ORDER BY ph.created_at DESC
            LIMIT 20
        ");
        
        if ($sample && $sample->num_rows > 0) {
            echo "<div style='max-height:300px;overflow-y:auto;'>";
            echo "<table>";
            echo "<tr><th>Invoice</th><th>Payment Date</th><th>Seq</th><th>Type</th><th>Method</th><th>Amount</th><th>Status</th></tr>";
            while ($row = $sample->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['invoice_no']}</td>";
                echo "<td>" . date('M d, Y H:i', strtotime($row['payment_date'])) . "</td>";
                echo "<td>{$row['payment_sequence']}</td>";
                echo "<td>{$row['payment_type']}</td>";
                echo "<td>{$row['payment_method']}</td>";
                echo "<td>₱" . number_format($row['amount'], 2) . "</td>";
                echo "<td>{$row['status_after_payment']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";
        }
    }
    
    echo "<hr>";
    echo "<p>";
    echo "<a href='preorderreport.php' style='display:inline-block;padding:10px 20px;background:#2196F3;color:white;text-decoration:none;border-radius:4px;margin-right:10px;'>View Report</a>";
    echo "<a href='debug_preorder_report.php' style='display:inline-block;padding:10px 20px;background:#FF9800;color:white;text-decoration:none;border-radius:4px;'>Debug Report</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>❌ Error</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";

$conn->close();
?>
