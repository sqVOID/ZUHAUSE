<?php
/**
 * Check for Duplicate Payment History Records
 */

require_once 'session_check.php';
require_once 'config.php';

$invoice_to_check = 'PRE-20260825-0001'; // Change this

echo "<!DOCTYPE html><html><head><title>Check Duplicates</title>";
echo "<style>body{font-family:Arial;padding:20px;max-width:1200px;margin:0 auto;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} .info{color:blue;} .warning{color:orange;font-weight:bold;} table{border-collapse:collapse;width:100%;margin:10px 0;} th,td{border:1px solid #ccc;padding:8px;text-align:left;font-size:12px;} th{background:#f0f0f0;} .duplicate{background:#ffcdd2;}</style>";
echo "</head><body>";
echo "<h1>Check Duplicate Payment History</h1>";
echo "<p class='info'>Checking: <strong>$invoice_to_check</strong></p>";

try {
    // Get all payment history for this invoice
    $query = "
        SELECT 
            ph.*,
            p.total_amount,
            DATE(ph.payment_date) as date_only,
            TIME(ph.payment_date) as time_only
        FROM preorder_payment_history ph
        INNER JOIN preorders p ON ph.preorder_id = p.id
        WHERE ph.invoice_no = ?
        ORDER BY ph.payment_date, ph.payment_sequence
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $invoice_to_check);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "<p class='error'>❌ No payment history found for $invoice_to_check</p>";
        exit;
    }
    
    echo "<p class='info'>Found {$result->num_rows} payment history record(s)</p>";
    
    // Display all records
    echo "<h2>All Payment History Records:</h2>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Seq</th><th>Date</th><th>Time</th><th>Type</th><th>Method</th><th>Amount</th><th>Balance Before</th><th>Balance After</th><th>Status</th><th>Created At</th></tr>";
    
    $records = [];
    $duplicates_found = false;
    
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
        
        // Check for potential duplicates (same sequence and similar amount)
        $is_duplicate = false;
        foreach ($records as $existing) {
            if ($existing['id'] != $row['id'] && 
                $existing['payment_sequence'] == $row['payment_sequence'] &&
                abs($existing['amount'] - $row['amount']) < 0.01) {
                $is_duplicate = true;
                $duplicates_found = true;
                break;
            }
        }
        
        $row_class = $is_duplicate ? 'duplicate' : '';
        
        echo "<tr class='$row_class'>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['payment_sequence']}</td>";
        echo "<td>{$row['date_only']}</td>";
        echo "<td>{$row['time_only']}</td>";
        echo "<td>{$row['payment_type']}</td>";
        echo "<td>{$row['payment_method']}</td>";
        echo "<td>₱" . number_format($row['amount'], 2) . "</td>";
        echo "<td>₱" . number_format($row['balance_before'], 2) . "</td>";
        echo "<td>₱" . number_format($row['balance_after'], 2) . "</td>";
        echo "<td>{$row['status_after_payment']}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if ($duplicates_found) {
        echo "<p class='error'>⚠ Duplicate records found (highlighted in red)!</p>";
    }
    
    // Analyze the payment flow
    echo "<h2>Payment Flow Analysis:</h2>";
    echo "<table>";
    echo "<tr><th>Step</th><th>Date</th><th>Amount Paid</th><th>Balance After</th><th>Status</th><th>Issue?</th></tr>";
    
    $total_amount = $records[0]['total_amount'];
    $expected_balance = $total_amount;
    $step = 1;
    
    foreach ($records as $record) {
        $amount = floatval($record['amount']);
        $expected_balance -= $amount;
        $actual_balance_after = floatval($record['balance_after']);
        
        $issue = '';
        if (abs($expected_balance - $actual_balance_after) > 0.01) {
            $issue = '❌ Balance mismatch!';
        }
        
        echo "<tr>";
        echo "<td>$step</td>";
        echo "<td>{$record['date_only']}</td>";
        echo "<td>₱" . number_format($amount, 2) . "</td>";
        echo "<td>₱" . number_format($actual_balance_after, 2) . " (Expected: ₱" . number_format($expected_balance, 2) . ")</td>";
        echo "<td>{$record['status_after_payment']}</td>";
        echo "<td class='error'>$issue</td>";
        echo "</tr>";
        
        $step++;
    }
    echo "</table>";
    
    // Provide solution
    echo "<h2>Solution:</h2>";
    
    if ($result->num_rows > 2) {
        echo "<div style='background:#fff3cd;padding:15px;border-left:4px solid #ff9800;margin:15px 0;'>";
        echo "<p class='warning'><strong>Issue:</strong> There are {$result->num_rows} payment records when there should only be 2.</p>";
        echo "<p><strong>Likely Cause:</strong></p>";
        echo "<ul>";
        echo "<li>Migration was run multiple times</li>";
        echo "<li>Save button was clicked multiple times</li>";
        echo "<li>Old records weren't cleaned before migration</li>";
        echo "</ul>";
        
        echo "<p><strong>To Fix:</strong></p>";
        echo "<form method='post'>";
        echo "<input type='hidden' name='action' value='clean_duplicates'>";
        echo "<input type='hidden' name='invoice' value='$invoice_to_check'>";
        echo "<button type='submit' onclick='return confirm(\"This will delete duplicate payment history records. Continue?\")' style='padding:10px 20px;background:#f44336;color:white;border:none;border-radius:4px;cursor:pointer;font-weight:bold;'>🗑 Remove Duplicates</button>";
        echo "</form>";
        echo "</div>";
        
        // Handle cleanup
        if (isset($_POST['action']) && $_POST['action'] === 'clean_duplicates') {
            echo "<div style='background:#e8f5e9;padding:15px;margin:15px 0;border-left:4px solid #4CAF50;'>";
            echo "<h3>Cleaning Duplicates...</h3>";
            
            // Keep only the first payment record for each sequence
            $sequences_to_keep = [];
            foreach ($records as $record) {
                $seq = $record['payment_sequence'];
                if (!isset($sequences_to_keep[$seq])) {
                    $sequences_to_keep[$seq] = $record['id'];
                }
            }
            
            $deleted_count = 0;
            foreach ($records as $record) {
                $seq = $record['payment_sequence'];
                if ($sequences_to_keep[$seq] != $record['id']) {
                    // Delete this duplicate
                    $delete_stmt = $conn->prepare("DELETE FROM preorder_payment_history WHERE id = ?");
                    $delete_stmt->bind_param("i", $record['id']);
                    if ($delete_stmt->execute()) {
                        echo "<p class='success'>✓ Deleted duplicate record ID {$record['id']} (Seq: $seq, Amount: ₱" . number_format($record['amount'], 2) . ")</p>";
                        $deleted_count++;
                    }
                    $delete_stmt->close();
                }
            }
            
            echo "<p class='success'><strong>✓ Deleted $deleted_count duplicate record(s)!</strong></p>";
            echo "<p><a href='check_duplicate_payments.php'>Refresh to verify</a></p>";
            echo "</div>";
        }
    } else {
        echo "<p class='success'>✓ Payment history looks correct! Only {$result->num_rows} record(s) as expected.</p>";
    }
    
    echo "<hr>";
    echo "<p>";
    echo "<a href='preorderreport.php' style='display:inline-block;padding:10px 20px;background:#2196F3;color:white;text-decoration:none;border-radius:4px;margin-right:10px;'>View Report</a>";
    echo "<a href='debug_preorder_report.php' style='display:inline-block;padding:10px 20px;background:#FF9800;color:white;text-decoration:none;border-radius:4px;'>Debug Report</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>❌ Error</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
}

echo "</body></html>";
$conn->close();
?>
