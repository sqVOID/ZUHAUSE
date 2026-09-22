<?php
require_once 'session_check.php';
include 'config.php';

// Find and remove duplicate serial numbers in stock_on_hand
$sql = "SELECT imei, COUNT(*) as count, MIN(id) as keep_id, GROUP_CONCAT(id) as all_ids
        FROM stock_on_hand 
        WHERE imei IS NOT NULL AND imei != '' 
        GROUP BY imei 
        HAVING count > 1";

$result = $conn->query($sql);

echo "<h2>Duplicate Serial Numbers Found:</h2>";
echo "<pre>";

$deleted_count = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $imei = $row['imei'];
        $keep_id = $row['keep_id'];
        $all_ids = $row['all_ids'];
        
        echo "Serial: {$imei}\n";
        echo "  Total duplicates: {$row['count']}\n";
        echo "  Keeping ID: {$keep_id}\n";
        echo "  All IDs: {$all_ids}\n";
        
        // Delete all duplicates except the first one (keep_id)
        $delete_sql = "DELETE FROM stock_on_hand WHERE imei = '{$conn->real_escape_string($imei)}' AND id != {$keep_id}";
        
        if ($conn->query($delete_sql)) {
            $deleted = $conn->affected_rows;
            $deleted_count += $deleted;
            echo "  ✓ Deleted {$deleted} duplicate(s)\n";
        } else {
            echo "  ✗ Error deleting: {$conn->error}\n";
        }
        
        echo "\n";
    }
    
    echo "\nTotal duplicates deleted: {$deleted_count}\n";
} else {
    echo "No duplicates found!\n";
}

echo "</pre>";

echo "<br><a href='stockonhand.php'>Go to Stock on Hand</a>";
?>
