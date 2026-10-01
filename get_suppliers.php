<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT store_name, contact_number, address 
            FROM suppliers 
            WHERE status = 'Active' 
            ORDER BY store_name ASC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $suppliers = [];
        while ($row = $result->fetch_assoc()) {
            $suppliers[] = [
                'store_name' => $row['store_name'],
                'contact_number' => $row['contact_number'],
                'address' => $row['address']
            ];
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $suppliers
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to fetch suppliers'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
