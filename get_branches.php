<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT branch_name, branch_code 
            FROM branches 
            WHERE status = 'Active' 
            ORDER BY branch_name ASC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $branches = [];
        while ($row = $result->fetch_assoc()) {
            $branches[] = [
                'branch_name' => $row['branch_name'],
                'branch_code' => $row['branch_code']
            ];
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $branches
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to fetch branches'
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
