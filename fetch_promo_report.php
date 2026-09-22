<?php
require_once 'session_check.php';
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Get filter parameters
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $area = isset($_GET['area']) ? trim($_GET['area']) : '';
    $branch = isset($_GET['branch']) ? trim($_GET['branch']) : '';
    $promo_id = isset($_GET['promo_id']) ? trim($_GET['promo_id']) : '';

    // Validate required parameters
    if (empty($date_from) || empty($date_to)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Date range is required'
        ]);
        exit;
    }

    // Get user access information
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

    // Build the query
    $query = "
        SELECT 
            se.id,
            se.invoice_no,
            DATE_FORMAT(se.created_at, '%Y-%m-%d') as created_at,
            b.branch_name,
            CONCAT(se.first_name, ' ', se.last_name) as customer_name,
            p.promo_name,
            p.motor_model,
            p.free_item,
            p.discount_type,
            p.discount_value,
            se.total_amount,
            se.assisted_by,
            (SELECT sei.item_code
             FROM sales_entry_items sei
             WHERE sei.sales_entry_id = se.id
             ORDER BY sei.id ASC
             LIMIT 1
            ) as first_item
        FROM sales_entry se
        LEFT JOIN branches b ON se.branch_code = b.branch_code
        LEFT JOIN promos p ON se.promo_id = p.id
        WHERE se.promo_id IS NOT NULL
        AND se.promo_id > 0
        AND DATE(se.created_at) BETWEEN ? AND ?
    ";

    $params = [$date_from, $date_to];
    $param_types = 'ss';

    // Apply promo filter
    if (!empty($promo_id)) {
        $query .= " AND se.promo_id = ?";
        $params[] = $promo_id;
        $param_types .= 'i';
    }

    // Apply area filter
    if (!empty($area)) {
        $query .= " AND b.area = ?";
        $params[] = $area;
        $param_types .= 's';
    }

    // Apply branch filter
    if (!empty($branch)) {
        $query .= " AND b.branch_name = ?";
        $params[] = $branch;
        $param_types .= 's';
    }

    // Apply user access restrictions
    if ($system_level !== 'Super-Admin' && strtoupper($user_branch) !== 'SUPERADMIN') {
        if (!empty($user_branch)) {
            // User has specific branch access
            $user_branches = array_map('trim', explode(',', $user_branch));
            $user_branches = array_filter($user_branches, function ($b) {
                return !empty($b);
            });

            if (!empty($user_branches)) {
                $placeholders = implode(',', array_fill(0, count($user_branches), '?'));
                $query .= " AND b.branch_name IN ($placeholders)";
                foreach ($user_branches as $ub) {
                    $params[] = $ub;
                    $param_types .= 's';
                }
            }
        }
    }

    $query .= " ORDER BY se.created_at DESC, se.invoice_no ASC";

    // Prepare and execute query
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters dynamically
    if (!empty($params)) {
        $bind_params = array_merge([$param_types], $params);
        $tmp = [];
        foreach ($bind_params as $key => $value) {
            $tmp[$key] = &$bind_params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $tmp);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'invoice_no' => $row['invoice_no'],
            'created_at' => $row['created_at'],
            'branch_name' => $row['branch_name'],
            'customer_name' => $row['customer_name'],
            'promo_name' => $row['promo_name'],
            'first_item' => $row['first_item'],
            'motor_model' => $row['motor_model'],
            'free_item' => $row['free_item'],
            'discount_type' => $row['discount_type'],
            'discount_value' => number_format((float)$row['discount_value'], 2, '.', ''),
            'total_amount' => number_format((float)$row['total_amount'], 2, '.', ''),
            'assisted_by' => $row['assisted_by']
        ];
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'count' => count($data)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
