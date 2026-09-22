<?php
require_once 'session_check.php';
include 'config.php';
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get parameters
$item_code = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';
$imei = isset($_GET['imei']) ? trim($_GET['imei']) : '';
$requested_qty = isset($_GET['qty']) ? intval($_GET['qty']) : 1;
$force_branch_filter = isset($_GET['force_branch']) ? filter_var($_GET['force_branch'], FILTER_VALIDATE_BOOLEAN) : false;

// Get user's branch
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$system_level_normalized = strtolower(str_replace([' ', '_'], '-', $system_level));
$has_full_access = in_array($system_level_normalized, ['super-admin', 'superadmin', 'sub-admin', 'subadmin'], true);

// If force_branch_filter is true, apply branch filter regardless of access level
$apply_branch_filter = $force_branch_filter || (!$has_full_access && !empty($user_branch));

// If no item code provided, allow the add (for manual entries)
if (empty($item_code)) {
    echo json_encode(['status' => 'success', 'available' => true, 'message' => 'No item code provided, skipping stock check']);
    exit;
}

try {
    // Escape inputs
    $item_code_esc = $conn->real_escape_string($item_code);
    $imei_esc = $conn->real_escape_string($imei);
    $user_branch_esc = $conn->real_escape_string($user_branch);
    
    // Check if item has serial number (IMEI)
    if (!empty($imei)) {
        // For serialized items, check if specific IMEI exists in stock (check both imei and imei2 columns)
        // Use case-insensitive and trim for better matching
        $sql = "SELECT id, item_code, description, imei, imei2, branch, status, quantity 
                FROM stock_on_hand 
                WHERE item_code = '$item_code_esc' 
                AND (UPPER(TRIM(imei)) = UPPER(TRIM('$imei_esc')) OR UPPER(TRIM(imei2)) = UPPER(TRIM('$imei_esc')))";
        
        // Add branch filter if needed
        if ($apply_branch_filter && !empty($user_branch)) {
            $sql .= " AND branch = '$user_branch_esc'";
        }
        
        $sql .= " LIMIT 1";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Check if status is Available, Active, or Good Stock (case-insensitive)
            $status = strtolower(trim($row['status']));
            if (in_array($status, ['available', 'active', 'good stock'])) {
                echo json_encode([
                    'status' => 'success',
                    'available' => true,
                    'message' => 'Item with IMEI is available in stock',
                    'data' => $row
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'available' => false,
                    'message' => 'This item with IMEI ' . $imei . ' is not available (Status: ' . $row['status'] . ')'
                ]);
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'available' => false,
                'message' => 'Insufficient stock no available'
            ]);
        }
    } else {
        // For non-serialized items, check total available quantity
        $sql = "SELECT item_code, description, branch, status, quantity, SUM(quantity) as total_qty 
                FROM stock_on_hand 
                WHERE TRIM(item_code) = TRIM('$item_code_esc')
                AND quantity > 0
                AND (LOWER(TRIM(status)) IN ('available', 'active', 'good stock'))";
        
        // Add branch filter if needed
        if ($apply_branch_filter && !empty($user_branch)) {
            $sql .= " AND branch = '$user_branch_esc'";
        }
        
        $sql .= " GROUP BY item_code";
        
        $result = $conn->query($sql);
        
        if ($result) {
            $row = $result->fetch_assoc();
            $available_qty = intval($row['total_qty']);
            
            // Get detailed breakdown for debugging
            $detail_sql = "SELECT id, item_code, description, branch, status, quantity 
                          FROM stock_on_hand 
                          WHERE TRIM(item_code) = TRIM('$item_code_esc')
                          AND quantity > 0
                          AND (LOWER(TRIM(status)) IN ('available', 'active', 'good stock'))";
            
            if ($apply_branch_filter && !empty($user_branch)) {
                $detail_sql .= " AND branch = '$user_branch_esc'";
            }
            
            $detail_result = $conn->query($detail_sql);
            $stock_details = [];
            if ($detail_result) {
                while ($detail_row = $detail_result->fetch_assoc()) {
                    $stock_details[] = $detail_row;
                }
            }
            
            if ($available_qty >= $requested_qty) {
                echo json_encode([
                    'status' => 'success',
                    'available' => true,
                    'available_qty' => $available_qty,
                    'requested_qty' => $requested_qty,
                    'message' => 'Sufficient stock available',
                    'stock_details' => $stock_details,
                    'user_branch' => $user_branch,
                    'has_full_access' => $has_full_access,
                    'apply_branch_filter' => $apply_branch_filter
                ]);
            } else {
                // Check if there's no stock at all
                if ($available_qty == 0) {
                    $error_message = 'Insufficient stock no available';
                } else {
                    $error_message = 'Insufficient stock available: ' . $available_qty . "\nRequested: " . $requested_qty;
                }
                
                echo json_encode([
                    'status' => 'error',
                    'available' => false,
                    'available_qty' => $available_qty,
                    'requested_qty' => $requested_qty,
                    'message' => $error_message,
                    'stock_details' => $stock_details,
                    'user_branch' => $user_branch,
                    'has_full_access' => $has_full_access,
                    'apply_branch_filter' => $apply_branch_filter
                ]);
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'available' => false,
                'message' => 'Error executing query: ' . $conn->error,
                'sql' => $sql
            ]);
        }
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'available' => false,
        'message' => 'Exception: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
