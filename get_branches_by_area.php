<?php
require_once 'session_check.php';
require_once 'config.php';

header('Content-Type: application/json');

try {
    $area = isset($_GET['area']) ? trim($_GET['area']) : '';

    if (empty($area)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Area parameter is required'
        ]);
        exit;
    }

    // Get user access information
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

    $query = "SELECT branch_name FROM branches WHERE area = ? AND status = 'Active'";
    $params = [$area];
    $param_types = 's';

    // Apply user access restrictions
    if ($system_level !== 'Super-Admin' && strtoupper($user_branch) !== 'SUPERADMIN') {
        if (!empty($user_branch)) {
            $user_branches = array_map('trim', explode(',', $user_branch));
            $user_branches = array_filter($user_branches, function ($b) {
                return !empty($b);
            });

            if (!empty($user_branches)) {
                $placeholders = implode(',', array_fill(0, count($user_branches), '?'));
                $query .= " AND branch_name IN ($placeholders)";
                foreach ($user_branches as $ub) {
                    $params[] = $ub;
                    $param_types .= 's';
                }
            }
        }
    }

    $query .= " ORDER BY branch_name ASC";

    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters dynamically
    $bind_params = array_merge([$param_types], $params);
    $tmp = [];
    foreach ($bind_params as $key => $value) {
        $tmp[$key] = &$bind_params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $tmp);

    $stmt->execute();
    $result = $stmt->get_result();

    $branches = [];
    while ($row = $result->fetch_assoc()) {
        $branches[] = $row['branch_name'];
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        'status' => 'success',
        'branches' => $branches
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
