<?php
// Enable error logging to a file for debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/search_freebies_error.log');

// Suppress display but log errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Basic session check without requiring session_check.php (to avoid output issues)
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Include config
include 'config.php';

// Clean any output that was generated and set JSON header
ob_end_clean();
ob_start();
header('Content-Type: application/json');

try {
    if (isset($_GET['term'])) {
        $term = $conn->real_escape_string($_GET['term']);

        // Get user's branch from session
        $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

        if (empty($user_branch)) {
            echo json_encode(['status' => 'error', 'message' => 'User branch not found', 'data' => []]);
            ob_end_flush();
            exit();
        }

        // Get branch code
        $branch_code = '000';
        $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '" . $conn->real_escape_string($user_branch) . "'");
        if ($branch_query && $branch_query->num_rows > 0) {
            $branch_data = $branch_query->fetch_assoc();
            $branch_code = $branch_data['branch_code'];
        }

        // Build WHERE clause for searching by item code only (NOT description)
        $whereClause = "i.item_code LIKE '%$term%'";

        // Query items that are non-serialized and check their stock in stock_on_hand
        $sql = "SELECT i.id, i.item_code, i.description, i.branch, i.has_serial,
                COALESCE(SUM(soh.quantity), 0) as stock_quantity
                FROM items i 
                LEFT JOIN stock_on_hand soh ON i.item_code = soh.item_code 
                    AND soh.branch = '" . $conn->real_escape_string($user_branch) . "'
                    AND (LOWER(TRIM(soh.status)) = 'available' OR LOWER(TRIM(soh.status)) = 'active')
                WHERE $whereClause 
                AND i.status = 'Active'
                AND (i.has_serial = 0 OR i.has_serial IS NULL)
                GROUP BY i.id, i.item_code, i.description, i.branch, i.has_serial
                LIMIT 20";

        $result = $conn->query($sql);

        if ($result === false) {
            // SQL error occurred
            error_log("SQL Error: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Database query failed: ' . $conn->error, 'data' => []]);
            ob_end_flush();
            exit();
        }

        $items_data = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Check if user's branch is in the item's allowed branches
                $item_branches = !empty($row['branch']) ? array_map('trim', explode(',', $row['branch'])) : [];
                $is_branch_allowed = false;

                // If item has no branch restrictions, allow all branches
                if (empty($item_branches)) {
                    $is_branch_allowed = true;
                }
                else {
                    // Check if user's branch is in the allowed list
                    if (!empty($user_branch) && in_array($user_branch, $item_branches)) {
                        $is_branch_allowed = true;
                    }
                }

                // Only include items that have NO stock (quantity = 0) in current branch
                if ($is_branch_allowed && $row['stock_quantity'] == 0) {
                    $items_data[] = [
                        'item_code' => $row['item_code'],
                        'description' => $row['description'],
                        'stock_quantity' => $row['stock_quantity'],
                        'branch_allowed' => $is_branch_allowed
                    ];
                }
            }
        }
        
        echo json_encode(['status' => 'success', 'data' => $items_data]);
    }
    else {
        echo json_encode(['status' => 'error', 'message' => 'No search term provided', 'data' => []]);
    }
} catch (Exception $e) {
    error_log("Exception in search_freebies.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage(), 'data' => []]);
} catch (Error $e) {
    error_log("Error in search_freebies.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode(['status' => 'error', 'message' => 'Fatal error: ' . $e->getMessage(), 'data' => []]);
}

ob_end_flush();
exit();
?>
