<?php
// API endpoint to force logout a specific user by deleting their active sessions
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = (int) $_POST['user_id'];
    
    // Delete all active sessions for this user
    $delete_sql = "DELETE FROM active_sessions WHERE user_id = $user_id";
    
    if ($conn->query($delete_sql) === TRUE) {
        echo json_encode([
            'success' => true,
            'message' => 'User sessions terminated',
            'affected_rows' => $conn->affected_rows
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $conn->error
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}

$conn->close();
?>
