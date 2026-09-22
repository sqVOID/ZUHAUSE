<?php
// Direct database query test - bypasses session checks
include 'config.php';

// Get the invoice number from the database screenshot
$test_invoice = '260727-MOTOLPA-00194';

echo "<h2>Debug: Unclaimed Freebies Search</h2>";
echo "<p><strong>Testing Invoice Number:</strong> $test_invoice</p>";

// Test 1: Check if table exists
echo "<h3>Test 1: Check if unclaimed_freebies table exists</h3>";
$table_check = $conn->query("SHOW TABLES LIKE 'unclaimed_freebies'");
if ($table_check && $table_check->num_rows > 0) {
    echo "<p style='color: green;'>✓ Table exists</p>";
} else {
    echo "<p style='color: red;'>✗ Table does NOT exist!</p>";
    exit;
}

// Test 2: Count total records
echo "<h3>Test 2: Total records in table</h3>";
$count = $conn->query("SELECT COUNT(*) as total FROM unclaimed_freebies");
$total = $count->fetch_assoc()['total'];
echo "<p>Total records: <strong>$total</strong></p>";

// Test 3: Count unclaimed records
echo "<h3>Test 3: Unclaimed records</h3>";
$unclaimed_count = $conn->query("SELECT COUNT(*) as total FROM unclaimed_freebies WHERE status = 'unclaimed'");
$unclaimed_total = $unclaimed_count->fetch_assoc()['total'];
echo "<p>Unclaimed records: <strong>$unclaimed_total</strong></p>";

// Test 4: Show all invoice numbers
echo "<h3>Test 4: All invoice numbers in table</h3>";
$invoices = $conn->query("SELECT DISTINCT invoice_number, status FROM unclaimed_freebies ORDER BY id DESC LIMIT 10");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Invoice Number</th><th>Status</th><th>Length</th><th>Has Spaces</th></tr>";
while ($row = $invoices->fetch_assoc()) {
    $inv = $row['invoice_number'];
    $has_spaces = (strlen($inv) != strlen(trim($inv))) ? 'YES' : 'NO';
    echo "<tr>";
    echo "<td>" . htmlspecialchars($inv) . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . strlen($inv) . "</td>";
    echo "<td>" . $has_spaces . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test 5: Direct search with exact match
echo "<h3>Test 5: Search with EXACT match (no TRIM)</h3>";
$exact_search = $conn->query("SELECT * FROM unclaimed_freebies WHERE invoice_number = '$test_invoice' AND status = 'unclaimed'");
echo "<p>Results: <strong>" . $exact_search->num_rows . "</strong></p>";
if ($exact_search->num_rows > 0) {
    echo "<p style='color: green;'>✓ Found with exact match</p>";
    while ($row = $exact_search->fetch_assoc()) {
        echo "<pre>" . print_r($row, true) . "</pre>";
    }
} else {
    echo "<p style='color: orange;'>⚠ NOT found with exact match</p>";
}

// Test 6: Search with TRIM
echo "<h3>Test 6: Search with TRIM</h3>";
$trim_search = $conn->query("SELECT * FROM unclaimed_freebies WHERE TRIM(invoice_number) = '$test_invoice' AND status = 'unclaimed'");
echo "<p>Results: <strong>" . $trim_search->num_rows . "</strong></p>";
if ($trim_search->num_rows > 0) {
    echo "<p style='color: green;'>✓ Found with TRIM</p>";
    while ($row = $trim_search->fetch_assoc()) {
        echo "<pre>" . print_r($row, true) . "</pre>";
    }
} else {
    echo "<p style='color: orange;'>⚠ NOT found with TRIM</p>";
}

// Test 7: Search with LIKE
echo "<h3>Test 7: Search with LIKE pattern</h3>";
$like_search = $conn->query("SELECT * FROM unclaimed_freebies WHERE invoice_number LIKE '%$test_invoice%' AND status = 'unclaimed'");
echo "<p>Results: <strong>" . $like_search->num_rows . "</strong></p>";
if ($like_search->num_rows > 0) {
    echo "<p style='color: green;'>✓ Found with LIKE</p>";
    while ($row = $like_search->fetch_assoc()) {
        echo "<pre>" . print_r($row, true) . "</pre>";
    }
} else {
    echo "<p style='color: red;'>✗ NOT found with LIKE</p>";
}

// Test 8: Search with status check
echo "<h3>Test 8: Check status values</h3>";
$status_check = $conn->query("SELECT invoice_number, status FROM unclaimed_freebies WHERE invoice_number LIKE '%$test_invoice%'");
echo "<p>Results (any status): <strong>" . $status_check->num_rows . "</strong></p>";
if ($status_check->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Invoice Number</th><th>Status</th></tr>";
    while ($row = $status_check->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['invoice_number']) . "</td>";
        echo "<td><strong>" . $row['status'] . "</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>
