<?php
require_once 'session_check.php';
// Setup booklet configuration for branch 43 preorder
include 'config.php';

echo "<h2>Setting up Booklet Configuration for Branch 43 - Preorder</h2>";

// First, check if booklet already exists
$check_query = "SELECT * FROM booklet_numbers WHERE branch_code = '43' AND page_type = 'preorder'";
$check_result = $conn->query($check_query);

if ($check_result && $check_result->num_rows > 0) {
    echo "<p style='color: orange;'>⚠ Booklet configuration already exists for branch 43 - preorder</p>";
    $existing = $check_result->fetch_assoc();
    echo "<pre>";
    print_r($existing);
    echo "</pre>";
    
    echo "<h3>Update Existing Booklet?</h3>";
    echo "<p>Current number in booklet: " . htmlspecialchars($existing['current_number']) . "</p>";
} else {
    echo "<p>No existing booklet configuration found. Creating new one...</p>";
    
    // Insert new booklet configuration
    // Based on your format: 0000001-43-PRE
    // The current number should be: 0000001-43
    // The suffix is: -PRE
    
    $insert_query = "INSERT INTO booklet_numbers 
        (branch_code, page_type, booklet_format, current_number, prefix, suffix, status, description) 
        VALUES 
        ('43', 'preorder', 'numeric', '0000001-43', '', '-PRE', 'Active', 'Branch 43 Preorder Numbers')";
    
    if ($conn->query($insert_query)) {
        echo "<p style='color: green;'>✓ Successfully created booklet configuration for branch 43 - preorder</p>";
        
        // Verify the insert
        $verify = $conn->query("SELECT * FROM booklet_numbers WHERE branch_code = '43' AND page_type = 'preorder'");
        if ($verify && $verify->num_rows > 0) {
            $row = $verify->fetch_assoc();
            echo "<h3>Created Booklet Configuration:</h3>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            foreach ($row as $key => $value) {
                echo "<tr><td><strong>" . htmlspecialchars($key) . "</strong></td><td>" . htmlspecialchars($value) . "</td></tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color: red;'>✗ Error creating booklet: " . $conn->error . "</p>";
    }
}

echo "<br><hr><br>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If booklet was created successfully, go back to preorder.php and refresh (Ctrl+F5)</li>";
echo "<li>The invoice number should now show: <strong>0000002-43-PRE</strong></li>";
echo "</ol>";

$conn->close();
?>
