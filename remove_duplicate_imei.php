<?php
require_once 'config.php';

echo "<h2>Removing Duplicate Serial Numbers from stock_on_hand</h2>";

// Find all duplicate IMEIs (where imei is not null or empty)
$find_duplicates_sql = "
    SELECT imei, COUNT(*) as count, GROUP_CONCAT(id ORDER BY id DESC) as ids
    FROM stock_on_hand 
    WHERE imei IS NOT NULL AND imei != '' AND imei != '-'
    GROUP BY imei 
    HAVING count > 1
    ORDER BY count DESC
";

$duplicates_result = $conn->query($find_duplicates_sql);

if (!$duplicates_result || $duplicates_result->num_rows === 0) {
    echo "<p style='color: green;'>✓ No duplicate serial numbers found in stock_on_hand table.</p>";
    $conn->close();
    exit;
}

echo "<p>Found " . $duplicates_result->num_rows . " serial numbers with duplicates.</p>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; margin: 20px 0;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>Serial Number</th>";
echo "<th>Duplicate Count</th>";
echo "<th>IDs Found</th>";
echo "<th>Action</th>";
echo "</tr>";

$total_deleted = 0;
$errors = [];

while ($row = $duplicates_result->fetch_assoc()) {
    $imei = $row['imei'];
    $count = $row['count'];
    $ids_string = $row['ids'];
    $ids_array = explode(',', $ids_string);
    
    // Keep the FIRST id (most recent based on ORDER BY id DESC), delete the rest
    $keep_id = $ids_array[0];
    $delete_ids = array_slice($ids_array, 1);
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($imei) . "</td>";
    echo "<td>" . $count . "</td>";
    echo "<td>" . htmlspecialchars($ids_string) . "</td>";
    
    if (count($delete_ids) > 0) {
        // Get details of the record we're keeping
        $keep_sql = "SELECT item_code, status, branch, dr_date FROM stock_on_hand WHERE id = ?";
        $keep_stmt = $conn->prepare($keep_sql);
        $keep_stmt->bind_param('i', $keep_id);
        $keep_stmt->execute();
        $keep_result = $keep_stmt->get_result();
        $keep_data = $keep_result->fetch_assoc();
        $keep_stmt->close();
        
        // Delete the duplicate records
        $delete_ids_str = implode(',', array_map('intval', $delete_ids));
        $delete_sql = "DELETE FROM stock_on_hand WHERE id IN ($delete_ids_str)";
        
        if ($conn->query($delete_sql)) {
            $deleted_count = $conn->affected_rows;
            $total_deleted += $deleted_count;
            echo "<td style='color: green;'>✓ Kept ID $keep_id (" . 
                 htmlspecialchars($keep_data['item_code']) . " - " . 
                 htmlspecialchars($keep_data['status']) . " - " . 
                 htmlspecialchars($keep_data['branch']) . "), Deleted $deleted_count duplicate(s)</td>";
        } else {
            $error_msg = "Failed to delete duplicates for IMEI $imei: " . $conn->error;
            $errors[] = $error_msg;
            echo "<td style='color: red;'>✗ " . htmlspecialchars($error_msg) . "</td>";
        }
    } else {
        echo "<td style='color: orange;'>No action needed</td>";
    }
    
    echo "</tr>";
}

echo "</table>";

echo "<h3>Summary</h3>";
echo "<p><strong>Total duplicate records deleted:</strong> $total_deleted</p>";

if (count($errors) > 0) {
    echo "<h3 style='color: red;'>Errors:</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: green;'>✓ All duplicates removed successfully!</p>";
}

// Show remaining duplicate check
$verify_sql = "
    SELECT COUNT(*) as remaining_dupes
    FROM (
        SELECT imei, COUNT(*) as count
        FROM stock_on_hand 
        WHERE imei IS NOT NULL AND imei != '' AND imei != '-'
        GROUP BY imei 
        HAVING count > 1
    ) as dupes
";

$verify_result = $conn->query($verify_sql);
if ($verify_result) {
    $verify_row = $verify_result->fetch_assoc();
    if ($verify_row['remaining_dupes'] > 0) {
        echo "<p style='color: orange;'>⚠ Warning: " . $verify_row['remaining_dupes'] . " serial numbers still have duplicates. You may need to run this script again.</p>";
    } else {
        echo "<p style='color: green;'>✓ Verification: No remaining duplicates found!</p>";
    }
}

$conn->close();
?>
