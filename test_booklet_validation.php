<?php
require_once 'session_check.php';
/**
 * Test script to verify booklet number validation
 * This simulates different booklet scenarios to ensure validation works correctly
 */

include 'config.php';

echo "<h2>Booklet Number Validation Test</h2>";
echo "<hr>";

// Test 1: Check if booklet validation logic works for numeric format
echo "<h3>Test 1: Numeric Format Validation</h3>";

// Simulate a booklet configuration
$test_booklet = [
    'id' => 1,
    'branch_code' => '043',
    'booklet_no' => '001',
    'beginning_number' => '0001',
    'ending_number' => '0015',
    'current_number' => '0016', // This exceeds the ending number!
    'booklet_format' => 'numeric',
    'suffix' => '-43',
    'status' => 'Active'
];

// Extract numeric parts
$parts = explode('-', $test_booklet['current_number']);
$current_number_numeric = intval(end($parts));

$ending_parts = explode('-', $test_booklet['ending_number']);
$ending_number_numeric = intval(end($ending_parts));

echo "Current Number: " . $test_booklet['current_number'] . " (numeric: $current_number_numeric)<br>";
echo "Ending Number: " . $test_booklet['ending_number'] . " (numeric: $ending_number_numeric)<br>";

if ($current_number_numeric > $ending_number_numeric) {
    echo "<strong style='color: red;'>❌ VALIDATION FAILED: Current number exceeds ending number!</strong><br>";
    echo "Message: Booklet number range exhausted! Current number (" . $test_booklet['current_number'] . ") has exceeded the ending number (" . $test_booklet['ending_number'] . ").<br>";
} else {
    echo "<strong style='color: green;'>✓ VALIDATION PASSED: Invoice number is within range</strong><br>";
}

echo "<hr>";

// Test 2: Valid booklet number
echo "<h3>Test 2: Valid Booklet Number (Within Range)</h3>";

$test_booklet_valid = [
    'id' => 2,
    'branch_code' => '043',
    'booklet_no' => '001',
    'beginning_number' => '0001',
    'ending_number' => '0015',
    'current_number' => '0010', // This is within range
    'booklet_format' => 'numeric',
    'suffix' => '-43',
    'status' => 'Active'
];

$parts = explode('-', $test_booklet_valid['current_number']);
$current_number_numeric = intval(end($parts));

$ending_parts = explode('-', $test_booklet_valid['ending_number']);
$ending_number_numeric = intval(end($ending_parts));

echo "Current Number: " . $test_booklet_valid['current_number'] . " (numeric: $current_number_numeric)<br>";
echo "Ending Number: " . $test_booklet_valid['ending_number'] . " (numeric: $ending_number_numeric)<br>";

if ($current_number_numeric > $ending_number_numeric) {
    echo "<strong style='color: red;'>❌ VALIDATION FAILED: Current number exceeds ending number!</strong><br>";
} else {
    echo "<strong style='color: green;'>✓ VALIDATION PASSED: Invoice number is within range</strong><br>";
}

echo "<hr>";

// Test 3: Check actual database booklets
echo "<h3>Test 3: Check Actual Booklets in Database</h3>";

$booklets_query = $conn->query("
    SELECT id, branch_code, booklet_no, beginning_number, ending_number, 
           current_number, booklet_format, suffix, status 
    FROM booklet_numbers 
    WHERE status = 'Active' 
    ORDER BY branch_code, booklet_no
");

if ($booklets_query && $booklets_query->num_rows > 0) {
    echo "<table border='1' cellpadding='10' cellspacing='0'>";
    echo "<tr>
            <th>Branch Code</th>
            <th>Booklet No</th>
            <th>Beginning</th>
            <th>Current</th>
            <th>Ending</th>
            <th>Format</th>
            <th>Status</th>
            <th>Validation</th>
          </tr>";
    
    while ($booklet = $booklets_query->fetch_assoc()) {
        // Extract numeric values based on format
        $current_numeric = null;
        $ending_numeric = null;
        
        if ($booklet['booklet_format'] === 'numeric') {
            $parts = explode('-', $booklet['current_number']);
            $current_numeric = intval(end($parts));
            
            $ending_parts = explode('-', $booklet['ending_number']);
            $ending_numeric = intval(end($ending_parts));
        } elseif (is_numeric(ltrim($booklet['current_number'], '0') ?: '0')) {
            $current_numeric = intval($booklet['current_number']);
            $ending_numeric = intval($booklet['ending_number']);
        }
        
        $validation_status = "N/A";
        $status_color = "black";
        
        if ($current_numeric !== null && $ending_numeric !== null) {
            if ($current_numeric > $ending_numeric) {
                $validation_status = "❌ EXHAUSTED";
                $status_color = "red";
            } else {
                $validation_status = "✓ Valid";
                $status_color = "green";
            }
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($booklet['branch_code']) . "</td>";
        echo "<td>" . htmlspecialchars($booklet['booklet_no']) . "</td>";
        echo "<td>" . htmlspecialchars($booklet['beginning_number']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($booklet['current_number']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($booklet['ending_number']) . "</td>";
        echo "<td>" . htmlspecialchars($booklet['booklet_format']) . "</td>";
        echo "<td>" . htmlspecialchars($booklet['status']) . "</td>";
        echo "<td style='color: $status_color; font-weight: bold;'>$validation_status</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No active booklets found in database.</p>";
}

echo "<hr>";
echo "<p><a href='bookletnoreg.php'>← Back to Booklet Registration</a></p>";

$conn->close();
?>
