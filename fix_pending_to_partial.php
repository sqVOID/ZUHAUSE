<?php
/**
 * Fix PENDING status to PARTIAL
 * 
 * Changes payment history records from 'pending' to 'partial'
 * when there's actually a payment made (amount > 0) but balance remaining.
 */

require_once 'session_check.php';
require_once 'config.php';

echo "<!DOCTYPE html><html><head><title>Fix Pending to Partial</title>";
echo "<style>body{font-family:Arial;padding:20px;max-width:900px;margin:0 auto;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} .info{color:blue;} table{border-collapse:collapse;width:100%;margin:10px 0;} th,td{border:1px solid #ccc;padding:8px;text-align:left;font-size:12px;} th{background:#f0f0f0;}</style>";
echo "</head><body>";
echo "<h1>Fix PENDING Status to PARTIAL</h1>";

try {
    // Find records with 'pending' status but have actual payment (amount > 0) and balance remaining
    $query = "
        SELECT 
            id, 
            invoice_no, 
            payment_sequence,
            amount,
            balance_before,
            balance_after,
            status_after_payment
        FROM preorder_payment_history
        WHERE status_after_payment = 'pending'
        AND amount > 0
        AND balance_after > 0.01
        ORDER BY invoice_no, payment_sequence
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    if ($result->num_rows === 0) {
        echo "<p class='success'>✓ No records need fixing! All statuses are correct.</p>";
    } else {
        echo "<p class='info'>Found {$result->num_rows} record(s) with incorrect 'pending' status</p>";
        
        echo "<h2>Records to Fix:</h2>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Invoice</th><th>Seq</th><th>Amount</th><th>Balance Before</th><th>Balance After</th><th>Current Status</th><th>Should Be</th></tr>";
        
        $records_to_fix = [];
        while ($row = $result->fetch_assoc()) {
            $records_to_fix[] = $row;
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['invoice_no']}</td>";
            echo "<td>{$row['payment_sequence']}</td>";
            echo "<td>₱" . number_format($row['amount'], 2) . "</td>";
            echo "<td>₱" . number_format($row['balance_before'], 2) . "</td>";
            echo "<td>₱" . number_format($row['balance_after'], 2) . "</td>";
            echo "<td style='color:#ff9800;font-weight:bold;'>PENDING</td>";
            echo "<td style='color:#ff6b00;font-weight:bold;'>PARTIAL</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<div style='background:#fff3cd;padding:15px;margin:15px 0;border-left:4px solid #ff9800;'>";
        echo "<p><strong>Explanation:</strong></p>";
        echo "<p>These records are marked as 'PENDING' but should be 'PARTIAL' because:</p>";
        echo "<ul>";
        echo "<li>✅ Payment was made (Amount > ₱0)</li>";
        echo "<li>✅ Balance remaining (Balance After > ₱0)</li>";
        echo "<li>❌ Status says 'PENDING' (incorrect)</li>";
        echo "</ul>";
        echo "<p><strong>PENDING</strong> should only be used when NO payment has been made yet.</p>";
        echo "<p><strong>PARTIAL</strong> means some payment made, but not fully paid.</p>";
        echo "</div>";
        
        if (isset($_POST['action']) && $_POST['action'] === 'fix_status') {
            echo "<div style='background:#e8f5e9;padding:15px;margin:15px 0;border-left:4px solid #4CAF50;'>";
            echo "<h3>Fixing Records...</h3>";
            
            $fixed_count = 0;
            foreach ($records_to_fix as $record) {
                $update_stmt = $conn->prepare("
                    UPDATE preorder_payment_history 
                    SET status_after_payment = 'partial'
                    WHERE id = ?
                ");
                $update_stmt->bind_param("i", $record['id']);
                
                if ($update_stmt->execute()) {
                    echo "<p class='success'>✓ Fixed {$record['invoice_no']} - Seq {$record['payment_sequence']}: PENDING → PARTIAL</p>";
                    $fixed_count++;
                } else {
                    echo "<p class='error'>✗ Failed to fix {$record['invoice_no']}: " . $update_stmt->error . "</p>";
                }
                $update_stmt->close();
            }
            
            echo "<p class='success'><strong>✓ Fixed $fixed_count record(s)!</strong></p>";
            echo "<p><a href='preorderreport.php'>View Report</a> to see the updated statuses.</p>";
            echo "</div>";
        } else {
            echo "<form method='post' style='margin:20px 0;'>";
            echo "<input type='hidden' name='action' value='fix_status'>";
            echo "<button type='submit' style='padding:12px 24px;background:#4CAF50;color:white;border:none;border-radius:4px;cursor:pointer;font-size:14px;font-weight:bold;'>✓ Fix All Records (Change PENDING to PARTIAL)</button>";
            echo "</form>";
        }
    }
    
    echo "<hr>";
    echo "<p><a href='preorderreport.php' style='display:inline-block;padding:10px 20px;background:#2196F3;color:white;text-decoration:none;border-radius:4px;'>View Report</a></p>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>❌ Error</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
}

echo "</body></html>";
$conn->close();
?>
