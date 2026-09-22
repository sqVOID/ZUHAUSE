<?php
require_once 'session_check.php';
// Simple script to check preorder data
include 'config.php';

echo "<h2>Preorder Data Check</h2>";

// Get all preorders
$query = "SELECT id, invoice_no, branch_code, first_name, last_name, created_at FROM preorders ORDER BY id DESC LIMIT 10";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<h3>Last 10 Preorders:</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background: #e0e0e0;'><th>ID</th><th>Invoice No</th><th>Branch Code</th><th>Customer</th><th>Created At</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['invoice_no']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['branch_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No preorders found in database</p>";
}

// Check branch 43 specifically
echo "<h3>Preorders for Branch 43:</h3>";
$query43 = "SELECT id, invoice_no, branch_code FROM preorders WHERE branch_code = '43' ORDER BY id DESC LIMIT 5";
$result43 = $conn->query($query43);

if ($result43 && $result43->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background: #e0e0e0;'><th>ID</th><th>Invoice No</th><th>Branch Code</th></tr>";
    while ($row = $result43->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['invoice_no']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['branch_code']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No preorders found for branch 43</p>";
}

// Test the invoice generation logic
echo "<h3>Testing Invoice Generation Logic:</h3>";
$branch_code = '43';
$last_invoice = '0000001-43-PRE';

echo "Last Invoice: <strong>$last_invoice</strong><br>";

// Test regex pattern
if (preg_match('/^(\d+)/', $last_invoice, $matches)) {
    echo "✓ Matched numeric prefix pattern<br>";
    echo "Extracted number: " . $matches[1] . "<br>";
    $last_id = intval($matches[1]);
    echo "Last ID (integer): $last_id<br>";
    $next_id = $last_id + 1;
    echo "Next ID: $next_id<br>";
    $padding = strlen($matches[1]);
    echo "Padding length: $padding<br>";
    
    if (preg_match('/^(\d+)-(.+)$/', $last_invoice, $format_parts)) {
        echo "✓ Matched suffix pattern<br>";
        $suffix_part = $format_parts[2];
        echo "Suffix part: " . $suffix_part . "<br>";
        $formatted_id = str_pad($next_id, $padding, '0', STR_PAD_LEFT);
        echo "Formatted next ID: $formatted_id<br>";
        $next_invoice = $formatted_id . '-' . $suffix_part;
        echo "<h4 style='color: green;'>Next Invoice Number: <strong>$next_invoice</strong></h4>";
    }
}

$conn->close();
?>
