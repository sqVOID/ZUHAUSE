<?php
/**
 * Migration Script: Add preorder_payment_history table
 * 
 * This table will store each individual payment transaction for preorders,
 * allowing the report to show multiple entries for the same preorder
 * (one for each payment on different dates).
 */

require_once 'config.php';

echo "<!DOCTYPE html><html><head><title>Add Payment History Table</title>";
echo "<style>body{font-family:Arial;padding:20px;max-width:900px;margin:0 auto;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border:1px solid #ccc;overflow-x:auto;} table{border-collapse:collapse;width:100%;margin:10px 0;} th,td{border:1px solid #ccc;padding:8px;text-align:left;}</style>";
echo "</head><body>";
echo "<h1>Add Preorder Payment History Table</h1>";

try {
    // Check if table already exists
    echo "<h2>Step 1: Check if table exists</h2>";
    $check_query = "SHOW TABLES LIKE 'preorder_payment_history'";
    $result = $conn->query($check_query);
    
    if ($result && $result->num_rows > 0) {
        echo "<p class='info'>✓ Table 'preorder_payment_history' already exists.</p>";
        $table_exists = true;
    } else {
        echo "<p class='info'>Table 'preorder_payment_history' does not exist. Will create it now.</p>";
        $table_exists = false;
    }
    
    if (!$table_exists) {
        // Create the table
        echo "<h2>Step 2: Create preorder_payment_history table</h2>";
        $create_query = "
        CREATE TABLE `preorder_payment_history` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `preorder_id` int(11) NOT NULL COMMENT 'References preorders.id',
          `invoice_no` varchar(50) NOT NULL COMMENT 'Preorder invoice number',
          `payment_date` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Date payment was made',
          `payment_type` varchar(50) NOT NULL COMMENT 'cash, ewallet, online_banking, etc.',
          `payment_method` varchar(100) DEFAULT NULL COMMENT 'Specific method like GCash, Maya, etc.',
          `amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Payment amount',
          `payment_data` text DEFAULT NULL COMMENT 'Full payment details as JSON',
          `payment_sequence` int(11) NOT NULL DEFAULT 1 COMMENT '1=first payment, 2=second, etc.',
          `status_after_payment` varchar(50) DEFAULT 'pending' COMMENT 'Preorder status after this payment',
          `balance_before` decimal(12,2) DEFAULT 0.00 COMMENT 'Balance before this payment',
          `balance_after` decimal(12,2) DEFAULT 0.00 COMMENT 'Balance after this payment',
          `branch_code` varchar(10) NOT NULL,
          `encoder` varchar(150) DEFAULT NULL,
          `created_at` datetime DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `preorder_id` (`preorder_id`),
          KEY `invoice_no` (`invoice_no`),
          KEY `payment_date` (`payment_date`),
          KEY `branch_code` (`branch_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";
        
        if ($conn->query($create_query)) {
            echo "<p class='success'>✓ Successfully created 'preorder_payment_history' table!</p>";
        } else {
            throw new Exception("Failed to create table: " . $conn->error);
        }
        
        // Migrate existing payment data from preorders
        echo "<h2>Step 3: Migrate existing payment data</h2>";
        
        // First, check if any payments have already been migrated to avoid duplicates
        $check_existing = $conn->query("SELECT COUNT(*) as count FROM preorder_payment_history");
        $existing_count = 0;
        if ($check_existing) {
            $count_row = $check_existing->fetch_assoc();
            $existing_count = intval($count_row['count']);
        }
        
        if ($existing_count > 0) {
            echo "<p class='info'>✓ Payment history already has $existing_count record(s). Skipping migration to avoid duplicates.</p>";
            echo "<p class='info'>If you need to re-migrate, please truncate the preorder_payment_history table first.</p>";
        } else {
            $migrate_query = "
            SELECT 
                id, 
                invoice_no, 
                payment_data, 
                branch_code, 
                encoder, 
                created_at,
                total_amount,
                status,
                completed_at,
                updated_at
            FROM preorders 
            WHERE payment_data IS NOT NULL 
            AND payment_data != ''
            AND payment_data != 'null'
            ORDER BY id
            ";
            
            $migrate_result = $conn->query($migrate_query);
            $migrated_count = 0;
            $skipped_count = 0;
            
            if ($migrate_result && $migrate_result->num_rows > 0) {
                echo "<p class='info'>Found " . $migrate_result->num_rows . " preorders with payment data. Processing...</p>";
                echo "<ul style='max-height:300px;overflow-y:auto;background:#f9f9f9;padding:10px;border:1px solid #ccc;'>";
                
                while ($preorder = $migrate_result->fetch_assoc()) {
                    $payment_data = json_decode($preorder['payment_data'], true);
                    
                    if (!$payment_data || !is_array($payment_data)) {
                        echo "<li style='color:#999;'>⊗ {$preorder['invoice_no']} - Invalid JSON</li>";
                        $skipped_count++;
                        continue;
                    }
                    
                    $payments_array = [];
                    
                    // Handle multiple payments
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
                        
                        // Calculate amount based on payment type
                        if (isset($payment['amount'])) {
                            $amount = floatval(str_replace(',', '', $payment['amount']));
                        } elseif ($payment_type === 'payment_partners') {
                            // Handle payment partners (Home Credit, etc.)
                            $loan_balance = isset($payment['loan_balance']) ? floatval(str_replace(',', '', $payment['loan_balance'])) : 0;
                            $cash_dp = isset($payment['cash_dp_amount']) ? floatval(str_replace(',', '', $payment['cash_dp_amount'])) : 0;
                            $gcash_dp = isset($payment['gcash_dp_amount']) ? floatval(str_replace(',', '', $payment['gcash_dp_amount'])) : 0;
                            $maya_dp = isset($payment['maya_dp_amount']) ? floatval(str_replace(',', '', $payment['maya_dp_amount'])) : 0;
                            $amount = $loan_balance + $cash_dp + $gcash_dp + $maya_dp;
                        }
                        
                        if ($amount <= 0) {
                            echo "<li style='color:#999;'>⊗ {$preorder['invoice_no']} - Payment #{$sequence} has no amount</li>";
                            $skipped_count++;
                            $sequence++;
                            continue;
                        }
                        
                        // Get payment method details
                        $payment_method = null;
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
                        // If this is the last payment and completed_at exists, use that
                        // Otherwise use created_at
                        $payment_date = $preorder['created_at'];
                        if ($sequence === $total_payments && !empty($preorder['completed_at']) && $preorder['completed_at'] != '0000-00-00 00:00:00') {
                            $payment_date = $preorder['completed_at'];
                        } elseif ($sequence > 1 && !empty($preorder['updated_at']) && $preorder['updated_at'] != '0000-00-00 00:00:00') {
                            // For subsequent payments, use updated_at if available
                            $payment_date = $preorder['updated_at'];
                        }
                        
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
                            $migrated_count++;
                            echo "<li style='color:green;'>✓ {$preorder['invoice_no']} - Payment #{$sequence}: ₱" . number_format($amount, 2) . " on " . date('M d, Y', strtotime($payment_date)) . "</li>";
                        } else {
                            echo "<li style='color:red;'>✗ {$preorder['invoice_no']} - Payment #{$sequence} failed: " . $insert_stmt->error . "</li>";
                        }
                        $insert_stmt->close();
                        
                        $sequence++;
                    }
                }
                
                echo "</ul>";
                echo "<p class='success'>✓ Migrated $migrated_count payment transaction(s) from existing preorders.</p>";
                if ($skipped_count > 0) {
                    echo "<p class='info'>⚠ Skipped $skipped_count payment(s) with invalid or zero amount.</p>";
                }
            } else {
                echo "<p class='info'>No preorders with payment data found.</p>";
            }
        }
    }
    
    // Show current table structure
    echo "<h2>Step " . ($table_exists ? "2" : "4") . ": Verify table structure</h2>";
    $structure_query = "SHOW COLUMNS FROM preorder_payment_history";
    $structure_result = $conn->query($structure_query);
    
    if ($structure_result) {
        echo "<table>";
        echo "<tr style='background:#f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Default</th><th>Extra</th></tr>";
        while ($col = $structure_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td><strong>{$col['Field']}</strong></td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>{$col['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Show sample data
    echo "<h2>Step " . ($table_exists ? "3" : "5") . ": Sample Payment History Data</h2>";
    $sample_query = "
        SELECT 
            invoice_no, 
            payment_date, 
            payment_type, 
            payment_method,
            amount, 
            payment_sequence,
            balance_before,
            balance_after,
            status_after_payment
        FROM preorder_payment_history 
        ORDER BY payment_date DESC 
        LIMIT 10
    ";
    $sample_result = $conn->query($sample_query);
    
    if ($sample_result && $sample_result->num_rows > 0) {
        echo "<table>";
        echo "<tr style='background:#f0f0f0;'><th>Invoice</th><th>Date</th><th>Type</th><th>Method</th><th>Amount</th><th>Seq</th><th>Balance Before</th><th>Balance After</th><th>Status</th></tr>";
        while ($row = $sample_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['invoice_no']}</td>";
            echo "<td>{$row['payment_date']}</td>";
            echo "<td>{$row['payment_type']}</td>";
            echo "<td>{$row['payment_method']}</td>";
            echo "<td>₱" . number_format($row['amount'], 2) . "</td>";
            echo "<td>{$row['payment_sequence']}</td>";
            echo "<td>₱" . number_format($row['balance_before'], 2) . "</td>";
            echo "<td>₱" . number_format($row['balance_after'], 2) . "</td>";
            echo "<td>{$row['status_after_payment']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>No payment history data yet.</p>";
    }
    
    echo "<h2>✅ Migration Complete!</h2>";
    echo "<p class='success'>The 'preorder_payment_history' table has been successfully created.</p>";
    
    echo "<hr>";
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Update save_preorder2_payment.php to insert into payment history table</li>";
    echo "<li>Update fetch_preorder_report.php to read from payment history table</li>";
    echo "<li>Test the payment flow to ensure each payment creates a history entry</li>";
    echo "<li>Verify the report shows multiple entries for the same preorder on different dates</li>";
    echo "</ol>";
    
    echo "<p><a href='preorder2.php'>Go to Preorder2</a> | <a href='preorderreport.php'>Go to Preorder Report</a></p>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>❌ Error</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
}

echo "</body></html>";

$conn->close();
?>
