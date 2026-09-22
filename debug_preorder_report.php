<?php
/**
 * Debug Script: Check why preorder isn't showing in report
 */

require_once 'session_check.php';
require_once 'config.php';

// Configuration
$invoice_to_check = 'PRE-20260825-0002'; // Change this
$date_from = '2026-08-25'; // Change to your filter dates
$date_to = '2026-08-26';
$branch_name = 'ZUHAUS2 INFANTA'; // Change to your branch

echo "<!DOCTYPE html><html><head><title>Debug Preorder Report</title>";
echo "<style>body{font-family:Arial;padding:20px;max-width:1200px;margin:0 auto;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} .info{color:blue;} .warning{color:orange;font-weight:bold;} pre{background:#f5f5f5;padding:10px;border:1px solid #ccc;overflow-x:auto;white-space:pre-wrap;} table{border-collapse:collapse;width:100%;margin:10px 0;} th,td{border:1px solid #ccc;padding:8px;text-align:left;font-size:12px;} th{background:#f0f0f0;}</style>";
echo "</head><body>";
echo "<h1>Debug Preorder Report</h1>";

echo "<div style='background:#e3f2fd;padding:15px;margin:15px 0;border-left:4px solid #2196F3;'>";
echo "<h3>Debug Configuration:</h3>";
echo "<p><strong>Invoice:</strong> $invoice_to_check</p>";
echo "<p><strong>Date Range:</strong> $date_from to $date_to</p>";
echo "<p><strong>Branch:</strong> $branch_name</p>";
echo "<form method='get'>";
echo "<label>Invoice: <input type='text' name='invoice' value='$invoice_to_check' style='padding:5px;width:200px;'></label> ";
echo "<label>From: <input type='date' name='from' value='$date_from' style='padding:5px;'></label> ";
echo "<label>To: <input type='date' name='to' value='$date_to' style='padding:5px;'></label> ";
echo "<label>Branch: <input type='text' name='branch' value='$branch_name' style='padding:5px;width:200px;'></label> ";
echo "<button type='submit' style='padding:6px 15px;background:#2196F3;color:white;border:none;border-radius:3px;cursor:pointer;'>Update</button>";
echo "</form>";
echo "</div>";

// Get parameters from GET if provided
if (isset($_GET['invoice'])) $invoice_to_check = $_GET['invoice'];
if (isset($_GET['from'])) $date_from = $_GET['from'];
if (isset($_GET['to'])) $date_to = $_GET['to'];
if (isset($_GET['branch'])) $branch_name = $_GET['branch'];

try {
    // Step 1: Check payment history records
    echo "<h2>Step 1: Check Payment History Records</h2>";
    $history_query = "
        SELECT 
            ph.*,
            DATE(ph.payment_date) as payment_date_only,
            p.invoice_no,
            p.branch_code,
            b.branch_name
        FROM preorder_payment_history ph
        INNER JOIN preorders p ON ph.preorder_id = p.id
        LEFT JOIN branches b ON p.branch_code = b.branch_code
        WHERE p.invoice_no = ?
        ORDER BY ph.payment_sequence
    ";
    
    $stmt = $conn->prepare($history_query);
    $stmt->bind_param("s", $invoice_to_check);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "<p class='error'>❌ No payment history records found for $invoice_to_check!</p>";
        echo "<p class='warning'>Please run the migration first using check_preorder_payment_history.php</p>";
        exit;
    }
    
    echo "<p class='success'>✓ Found {$result->num_rows} payment history record(s)</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Seq</th><th>Payment Date</th><th>Date Only</th><th>Amount</th><th>Branch Code</th><th>Branch Name</th><th>Status</th></tr>";
    
    $payment_dates = [];
    while ($row = $result->fetch_assoc()) {
        $payment_dates[] = $row['payment_date_only'];
        $highlight = '';
        if ($row['payment_date_only'] >= $date_from && $row['payment_date_only'] <= $date_to) {
            $highlight = 'background:#c8e6c9;';
        } else {
            $highlight = 'background:#ffcdd2;';
        }
        
        echo "<tr style='$highlight'>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['payment_sequence']}</td>";
        echo "<td>{$row['payment_date']}</td>";
        echo "<td><strong>{$row['payment_date_only']}</strong></td>";
        echo "<td>₱" . number_format($row['amount'], 2) . "</td>";
        echo "<td>{$row['branch_code']}</td>";
        echo "<td>{$row['branch_name']}</td>";
        echo "<td>{$row['status_after_payment']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p class='info'>🟢 Green = Within date range | 🔴 Red = Outside date range</p>";
    
    // Step 2: Check branch code
    echo "<h2>Step 2: Check Branch Code Match</h2>";
    $branch_query = $conn->prepare("SELECT branch_code FROM branches WHERE branch_name = ?");
    $branch_query->bind_param('s', $branch_name);
    $branch_query->execute();
    $branch_result = $branch_query->get_result();
    
    if ($branch_result && $branch_result->num_rows > 0) {
        $branch_data = $branch_result->fetch_assoc();
        $expected_branch_code = $branch_data['branch_code'];
        echo "<p class='success'>✓ Branch '$branch_name' has code: <strong>$expected_branch_code</strong></p>";
    } else {
        echo "<p class='error'>❌ Branch '$branch_name' not found in branches table!</p>";
        $expected_branch_code = null;
    }
    $branch_query->close();
    
    // Step 3: Test the actual report query
    echo "<h2>Step 3: Test Report Query (Exact Same as fetch_preorder_report.php)</h2>";
    
    $query = "SELECT 
                p.id as preorder_id,
                p.invoice_no as preorder_no,
                p.claimed_invoice_no,
                CONCAT(p.first_name, ' ', p.last_name) as customer_name,
                p.contact_no as customer_phone,
                p.branch_code,
                ph.payment_date as date_created,
                p.status,
                ph.payment_type,
                ph.payment_method,
                ph.amount as payment_amount,
                ph.payment_sequence,
                ph.balance_before,
                ph.balance_after,
                ph.status_after_payment,
                pi.item_description,
                pi.imei,
                pi.quantity,
                pi.price as unit_price,
                (pi.quantity * pi.price) as total_amount
              FROM preorder_payment_history ph
              INNER JOIN preorders p ON ph.preorder_id = p.id
              LEFT JOIN preorder_items pi ON p.id = pi.preorder_id
              WHERE DATE(ph.payment_date) BETWEEN ? AND ?";
    
    if ($expected_branch_code) {
        $query .= " AND p.branch_code = ?";
        $params = [$date_from, $date_to, $expected_branch_code];
        $types = 'sss';
    } else {
        $params = [$date_from, $date_to];
        $types = 'ss';
    }
    
    $query .= " ORDER BY ph.payment_date DESC, p.id DESC";
    
    echo "<div style='background:#fff9c4;padding:10px;margin:10px 0;'>";
    echo "<strong>SQL Query:</strong>";
    echo "<pre>" . $query . "</pre>";
    echo "<strong>Parameters:</strong> " . implode(", ", $params);
    echo "</div>";
    
    $stmt2 = $conn->prepare($query);
    $stmt2->bind_param($types, ...$params);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    echo "<p class='info'>Query returned: <strong>{$result2->num_rows} row(s)</strong></p>";
    
    if ($result2->num_rows === 0) {
        echo "<p class='error'>❌ Query returned NO results!</p>";
        echo "<h3>Possible Issues:</h3>";
        echo "<ul>";
        echo "<li>Payment dates are outside the date range ($date_from to $date_to)</li>";
        echo "<li>Branch code doesn't match (Expected: $expected_branch_code)</li>";
        echo "<li>Payment history records don't exist</li>";
        echo "</ul>";
        
        // Debug: Check what dates are in payment history
        echo "<h4>All Payment Dates for This Invoice:</h4>";
        $debug_query = "
            SELECT 
                DATE(ph.payment_date) as payment_date,
                p.branch_code,
                ph.amount
            FROM preorder_payment_history ph
            INNER JOIN preorders p ON ph.preorder_id = p.id
            WHERE p.invoice_no = ?
        ";
        $debug_stmt = $conn->prepare($debug_query);
        $debug_stmt->bind_param("s", $invoice_to_check);
        $debug_stmt->execute();
        $debug_result = $debug_stmt->get_result();
        
        echo "<ul>";
        while ($debug_row = $debug_result->fetch_assoc()) {
            $in_range = ($debug_row['payment_date'] >= $date_from && $debug_row['payment_date'] <= $date_to) ? '✅ IN RANGE' : '❌ OUT OF RANGE';
            $branch_match = ($debug_row['branch_code'] === $expected_branch_code) ? '✅ MATCH' : '❌ MISMATCH';
            echo "<li>Date: <strong>{$debug_row['payment_date']}</strong> $in_range | Branch: {$debug_row['branch_code']} $branch_match | Amount: ₱" . number_format($debug_row['amount'], 2) . "</li>";
        }
        echo "</ul>";
        
    } else {
        echo "<p class='success'>✓ Query returned results! This invoice SHOULD appear in the report.</p>";
        echo "<table>";
        echo "<tr><th>Invoice</th><th>Customer</th><th>Item</th><th>Payment</th><th>Status</th><th>Date</th><th>Branch</th></tr>";
        while ($row = $result2->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['preorder_no']}</td>";
            echo "<td>{$row['customer_name']}</td>";
            echo "<td>{$row['item_description']}</td>";
            echo "<td>₱" . number_format($row['payment_amount'], 2) . "</td>";
            echo "<td>{$row['status_after_payment']}</td>";
            echo "<td>" . date('M d, Y', strtotime($row['date_created'])) . "</td>";
            echo "<td>{$row['branch_code']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Step 4: Check preorder_items
    echo "<h2>Step 4: Check Preorder Items</h2>";
    $items_query = "
        SELECT * FROM preorder_items pi
        INNER JOIN preorders p ON pi.preorder_id = p.id
        WHERE p.invoice_no = ?
    ";
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->bind_param("s", $invoice_to_check);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    if ($items_result->num_rows === 0) {
        echo "<p class='warning'>⚠ No preorder_items found! This might cause the report to show blank item descriptions.</p>";
    } else {
        echo "<p class='success'>✓ Found {$items_result->num_rows} item(s)</p>";
        echo "<table>";
        echo "<tr><th>Family Code</th><th>Item Description</th><th>Quantity</th><th>Price</th></tr>";
        while ($item = $items_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$item['family_code']}</td>";
            echo "<td>{$item['item_description']}</td>";
            echo "<td>{$item['quantity']}</td>";
            echo "<td>₱" . number_format($item['price'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h2>✅ Debug Complete</h2>";
    echo "<div style='background:#e8f5e9;padding:15px;margin:15px 0;border-left:4px solid #4CAF50;'>";
    echo "<h3>Summary:</h3>";
    echo "<ul>";
    echo "<li>Payment history records: <strong>" . count($payment_dates) . "</strong></li>";
    echo "<li>Payment dates: <strong>" . implode(", ", $payment_dates) . "</strong></li>";
    echo "<li>Date filter: <strong>$date_from to $date_to</strong></li>";
    echo "<li>Branch filter: <strong>$branch_name ($expected_branch_code)</strong></li>";
    echo "<li>Query results: <strong>{$result2->num_rows} row(s)</strong></li>";
    echo "</ul>";
    
    if ($result2->num_rows > 0) {
        echo "<p class='success'><strong>✓ Everything looks good! The invoice should appear in the report.</strong></p>";
        echo "<p>If it's still not showing in preorderreport.php, check:</p>";
        echo "<ul>";
        echo "<li>Browser cache - try hard refresh (Ctrl+F5)</li>";
        echo "<li>JavaScript console for errors</li>";
        echo "<li>Verify you're using the exact same date range and branch</li>";
        echo "</ul>";
    } else {
        echo "<p class='error'><strong>❌ The invoice won't appear because the query returned no results.</strong></p>";
        echo "<p>Fix the issues listed above and try again.</p>";
    }
    echo "</div>";
    
    echo "<hr>";
    echo "<p>";
    echo "<a href='preorderreport.php' style='display:inline-block;padding:10px 20px;background:#2196F3;color:white;text-decoration:none;border-radius:4px;margin-right:10px;'>Go to Report</a>";
    echo "<a href='check_preorder_payment_history.php' style='display:inline-block;padding:10px 20px;background:#FF9800;color:white;text-decoration:none;border-radius:4px;'>Check Payment History</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>❌ Error</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";

$conn->close();
?>
