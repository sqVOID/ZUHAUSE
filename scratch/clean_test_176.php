<?php
include 'config.php';
$conn->query("DELETE FROM sales_entry WHERE id = 176");
echo "Deleted test row 176: " . $conn->affected_rows . "\n";
