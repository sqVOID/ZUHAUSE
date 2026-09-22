<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if (isset($_GET['term']) || isset($_GET['family_code'])) {
    $term = isset($_GET['term']) ? $conn->real_escape_string($_GET['term']) : '';
    $family_code_filter = isset($_GET['family_code']) ? $conn->real_escape_string($_GET['family_code']) : '';

    // Get user's branch and system level from session
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

    // Build WHERE clause for purchase order item search
    $whereClause = "1=1"; // Start with always-true condition
    
    // Add search term filter if provided
    if (!empty($term)) {
        $whereClause .= " AND (i.description LIKE '%$term%' OR i.item_code LIKE '%$term%')";
    }
    
    // Add family code filter if provided
    if (!empty($family_code_filter)) {
        $whereClause .= " AND i.family_code = '$family_code_filter'";
    }

    $sql = "SELECT i.id, i.item_code, i.description, i.family_code, i.department, i.srp, i.branch, i.has_serial, COALESCE(i.has_serial_2, 0) as has_serial_2, COALESCE(i.has_serial_number, 0) as has_serial_number 
            FROM items i 
            WHERE $whereClause 
            AND i.status = 'Active' 
            LIMIT 20";

    $result = $conn->query($sql);

    $items_data = [];
    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Check if user's branch is in the item's allowed branches
                $item_branches = !empty($row['branch']) ? array_map('trim', explode(',', $row['branch'])) : [];
                $is_branch_allowed = false;

                // Super-Admin has access to all items
                if (strcasecmp($system_level, 'Super-Admin') === 0) {
                    $is_branch_allowed = true;
                }
                // If item has no branch restrictions, allow all branches
                elseif (empty($item_branches)) {
                    $is_branch_allowed = true;
                }
                else {
                    // Check if user's branch is in the allowed list
                    if (!empty($user_branch) && in_array($user_branch, $item_branches)) {
                        $is_branch_allowed = true;
                    }
                    else {
                        // Branch not allowed
                        $is_branch_allowed = false;
                    }
                }

                // Return all items (show them in the search results)
                $items_data[] = [
                    'item_code' => $row['item_code'],
                    'description' => $row['description'],
                    'family_code' => $row['family_code'],
                    'department' => $row['department'] ?? '',
                    'has_serial' => (int) $row['has_serial'], // 1 for serialized, 0 for non-serialized
                    'has_serial_2' => (int) $row['has_serial_2'], // 1 for IMEI 2
                    'has_serial_number' => (int) $row['has_serial_number'], // 1 for Serial Number (TABLET)
                    'branch_allowed' => $is_branch_allowed
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $items_data]);
        }
        else {
            echo json_encode(['status' => 'success', 'data' => []]);
        }
    }
    else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}
else {
    echo json_encode(['status' => 'error', 'message' => 'No search term or family code provided']);
}
?>
