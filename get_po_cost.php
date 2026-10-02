<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

// Get parameters
$item_code = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';
$imei = isset($_GET['imei']) ? trim($_GET['imei']) : '';

if (empty($item_code)) {
    echo json_encode(['status' => 'error', 'message' => 'Item code is required', 'cost' => 0]);
    exit;
}

try {
    $item_code_esc = $conn->real_escape_string($item_code);
    $cost = 0;
    
    // If IMEI is provided (serialized item), search in po_allocations with IMEI
    if (!empty($imei)) {
        $imei_esc = $conn->real_escape_string($imei);
        
        // Search in purchase_order_allocations for the cost by item_model and serial_number or imei_2
        $sql = "SELECT cost 
                FROM purchase_order_allocations 
                WHERE item_model = '$item_code_esc' 
                AND (FIND_IN_SET('$imei_esc', REPLACE(serial_number, '\n', ',')) > 0 
                     OR FIND_IN_SET('$imei_esc', REPLACE(imei_2, '\n', ',')) > 0)
                ORDER BY id DESC 
                LIMIT 1";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $cost = floatval($row['cost']);
        } else {
            // If not found in allocations, try purchase_order_items
            $sql = "SELECT cost 
                    FROM purchase_order_items 
                    WHERE item_code = '$item_code_esc' 
                    AND (FIND_IN_SET('$imei_esc', REPLACE(serial_number, '\n', ',')) > 0 
                         OR FIND_IN_SET('$imei_esc', REPLACE(imei_2, '\n', ',')) > 0)
                    ORDER BY id DESC 
                    LIMIT 1";
            
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $cost = floatval($row['cost']);
            }
        }
    } else {
        // Non-serialized item - get the latest cost from purchase_order_allocations or purchase_order_items
        $sql = "SELECT cost 
                FROM purchase_order_allocations 
                WHERE item_model = '$item_code_esc' 
                ORDER BY id DESC 
                LIMIT 1";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $cost = floatval($row['cost']);
        } else {
            // Try purchase_order_items
            $sql = "SELECT cost 
                    FROM purchase_order_items 
                    WHERE item_code = '$item_code_esc' 
                    ORDER BY id DESC 
                    LIMIT 1";
            
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $cost = floatval($row['cost']);
            }
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'cost' => $cost,
        'item_code' => $item_code,
        'imei' => $imei
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Exception: ' . $e->getMessage(),
        'cost' => 0
    ]);
}

$conn->close();
?>
