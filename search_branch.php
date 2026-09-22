<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

try {
    if ($term === '') {
        // Load all active branches
        $sql = "SELECT branch_name, branch_code FROM branches WHERE status = 'Active' ORDER BY branch_name";
        $stmt = $conn->prepare($sql);
    } else {
        // Search branches by name
        $sql = "SELECT branch_name, branch_code FROM branches WHERE status = 'Active' AND branch_name LIKE ? ORDER BY branch_name";
        $stmt = $conn->prepare($sql);
        $searchTerm = '%' . $term . '%';
        $stmt->bind_param('s', $searchTerm);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
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
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error fetching branches: ' . $e->getMessage()
    ]);
}

$conn->close();
?>