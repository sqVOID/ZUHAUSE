<?php
require_once 'session_check.php';
include 'config.php';

// Authorization Check
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

// Fetch positions - Sub-admin cannot see Superadmin positions or positions used by Super-Admin/Sub-admin accounts
if (strcasecmp($system_level, 'Sub-admin') === 0) {
    // Sub-admin: exclude Superadmin positions and positions used by Super-Admin or Sub-admin accounts
    $positions = $conn->query("SELECT p.id, p.position_name FROM positions p 
                               WHERE p.position_name NOT LIKE '%superadmin%' 
                               AND p.position_name NOT LIKE '%super admin%'
                               AND p.position_name NOT IN (
                                   SELECT DISTINCT position FROM accounts 
                                   WHERE system_level IN ('Super-Admin', 'Sub-admin')
                               )
                               ORDER BY p.position_name ASC");
} else {
    // Super-Admin: see all positions
    $positions = $conn->query("SELECT id, position_name FROM positions ORDER BY position_name ASC");
}

$position_list = [];
if ($positions && $positions->num_rows > 0) {
    while ($row = $positions->fetch_assoc()) {
        $position_list[] = [
            'id' => $row['id'],
            'position_name' => $row['position_name']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($position_list);
?>
