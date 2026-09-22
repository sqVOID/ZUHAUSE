<?php

require_once 'session_check.php';

require_once 'config.php';


header('Content-Type: application/json');



$response = ['exists' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_code'])) {
    $item_code = strtoupper(trim($_POST['item_code']));
    $item_id = isset($_POST['item_id']) && !empty($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    
    if (!empty($item_code)) {
        $item_code_escaped = $conn->real_escape_string($item_code);
        
        if ($item_id > 0) {
            // Check for duplicate excluding the current item (for updates)
            $query = "SELECT id FROM items WHERE item_code = '$item_code_escaped' AND id != $item_id LIMIT 1";
        } else {
            // Check for duplicate (for new items)
            $query = "SELECT id FROM items WHERE item_code = '$item_code_escaped' LIMIT 1";
        }
        
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $response['exists'] = true;
        }
    }
}

echo json_encode($response);
?>
