<?php
require_once 'session_check.php';
// Test script to debug invoice generation
include 'config.php';
session_start();

// Simulate the same request that preorder.php makes
$_POST['action'] = 'get_invoice_number';
$_POST['branch_code'] = '43'; // Your branch code
$_POST['page_type'] = 'preorder';

echo "<h2>Testing Invoice Number Generation</h2>";
echo "<h3>Input Parameters:</h3>";
echo "Branch Code: " . $_POST['branch_code'] . "<br>";
echo "Page Type: " . $_POST['page_type'] . "<br>";

// Check existing preorders for this branch
$query = "SELECT id, invoice_no, branch_code FROM preorders WHERE branch_code = '43' ORDER BY id DESC LIMIT 5";
$result = $conn->query($query);

echo "<h3>Existing Preorders for Branch 43:</h3>";
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Invoice No</th><th>Branch Code</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['invoice_no'] . "</td>";
        echo "<td>" . $row['branch_code'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No preorders found for branch 43<br>";
}

echo "<h3>Generated Invoice Number:</h3>";

// Now call the get_next_invoice_number.php logic
include_once 'get_next_invoice_number.php';
?>
