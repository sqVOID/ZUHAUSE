<?php
require_once 'session_check.php';
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Get parameters
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-d');
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');
    $branch = isset($_GET['branch']) ? trim($_GET['branch']) : '';

    // Check user role for branch filtering
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

    // Build query - filter by created_at for ALL items (both unclaimed and claimed status)
    // This shows items that became unclaimed within the date range, showing current status
    $sql = "SELECT 
                uf.id,
                uf.invoice_number,
                uf.item_code,
                uf.item_description,
                uf.quantity,
                uf.status,
                uf.branch,
                uf.created_by,
                uf.created_at,
                uf.claimed_at,
                uf.note,
                se.encoder
            FROM unclaimed_freebies uf
            LEFT JOIN sales_entry se ON uf.sales_entry_id = se.id
            WHERE (DATE(uf.created_at) BETWEEN ? AND ? OR DATE(COALESCE(NULLIF(uf.claimed_at, '0000-00-00 00:00:00'), uf.created_at)) BETWEEN ? AND ?)
            AND uf.status != 'void'";

    $params = [$date_from, $date_to, $date_from, $date_to];
    $types = 'ssss';

    // Apply branch filter
    if ($system_level === 'User') {
        // Users can only see their own branch
        $sql .= " AND uf.branch = ?";
        $params[] = $user_branch;
        $types .= 's';
    } else if ($system_level === 'Sub-admin' && !empty($branch)) {
        // Sub-admins can filter by branch
        $sql .= " AND uf.branch = ?";
        $params[] = $branch;
        $types .= 's';
    } else if ($system_level === 'Sub-admin') {
        // Sub-admin without specific branch sees their branch only
        $sql .= " AND uf.branch = ?";
        $params[] = $user_branch;
        $types .= 's';
    } else if (!empty($branch) && $branch !== 'all') {
        // Super admin with specific branch filter
        $sql .= " AND uf.branch = ?";
        $params[] = $branch;
        $types .= 's';
    }

    $sql .= " ORDER BY uf.created_at DESC, uf.invoice_number ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $freebies = [];

    while ($row = $result->fetch_assoc()) {
        $created_date = !empty($row['created_at']) ? date('Y-m-d', strtotime($row['created_at'])) : '';
        $claimed_raw = (!empty($row['claimed_at']) && $row['claimed_at'] != '0000-00-00 00:00:00') ? $row['claimed_at'] : $row['created_at'];
        $claimed_date = !empty($claimed_raw) ? date('Y-m-d', strtotime($claimed_raw)) : '';

        // Add the UNCLAIMED entry if created_at is within date range
        if (empty($date_from) || empty($date_to) || ($created_date >= $date_from && $created_date <= $date_to)) {
            $freebies[] = [
                'id' => $row['id'] . '_unclaimed',
                'invoice_number' => $row['invoice_number'],
                'item_code' => $row['item_code'],
                'item_description' => $row['item_description'],
                'quantity' => intval($row['quantity']),
                'status' => 'unclaimed',
                'branch' => $row['branch'],
                'created_by' => $row['created_by'],
                'created_at' => $row['created_at'],
                'claimed_at' => null,
                'note' => $row['note'],
                'encoder' => $row['encoder']
            ];
        }

        // Add the CLAIMED entry if status is claimed and claimed date is within date range
        if (strtolower($row['status']) === 'claimed') {
            if (empty($date_from) || empty($date_to) || (empty($claimed_date) || ($claimed_date >= $date_from && $claimed_date <= $date_to))) {
                $freebies[] = [
                    'id' => $row['id'] . '_claimed',
                    'invoice_number' => $row['invoice_number'],
                    'item_code' => $row['item_code'],
                    'item_description' => $row['item_description'],
                    'quantity' => intval($row['quantity']),
                    'status' => 'claimed',
                    'branch' => $row['branch'],
                    'created_by' => $row['created_by'],
                    'created_at' => $row['created_at'],
                    'claimed_at' => $claimed_raw,
                    'note' => $row['note'],
                    'encoder' => $row['encoder']
                ];
            }
        }
    }

    $stmt->close();

    // Calculate summary statistics
    $total_unclaimed = 0;
    $total_claimed = 0;
    $total_qty_unclaimed = 0;
    $total_qty_claimed = 0;
    $by_status = [
        'unclaimed' => 0,
        'claimed' => 0
    ];
    $by_branch = [];
    $by_item = [];

    foreach ($freebies as $freebie) {
        $status = $freebie['status'];
        $branch = $freebie['branch'];
        $item_code = $freebie['item_code'];
        $qty = $freebie['quantity'];

        // Count by status
        if ($status === 'unclaimed') {
            $total_unclaimed++;
            $total_qty_unclaimed += $qty;
        } else {
            $total_claimed++;
            $total_qty_claimed += $qty;
        }
        $by_status[$status] = ($by_status[$status] ?? 0) + 1;

        // Count by branch
        if (!isset($by_branch[$branch])) {
            $by_branch[$branch] = [
                'unclaimed' => 0,
                'claimed' => 0,
                'total' => 0
            ];
        }
        if ($status === 'unclaimed') {
            $by_branch[$branch]['unclaimed']++;
        } else {
            $by_branch[$branch]['claimed']++;
        }
        $by_branch[$branch]['total']++;

        // Count by item
        if (!isset($by_item[$item_code])) {
            $by_item[$item_code] = [
                'description' => $freebie['item_description'],
                'unclaimed' => 0,
                'claimed' => 0,
                'qty_unclaimed' => 0,
                'qty_claimed' => 0,
                'total' => 0
            ];
        }
        if ($status === 'unclaimed') {
            $by_item[$item_code]['unclaimed']++;
            $by_item[$item_code]['qty_unclaimed'] += $qty;
        } else {
            $by_item[$item_code]['claimed']++;
            $by_item[$item_code]['qty_claimed'] += $qty;
        }
        $by_item[$item_code]['total']++;
    }

    // Return response
    echo json_encode([
        'success' => true,
        'data' => $freebies,
        'summary' => [
            'total_records' => count($freebies),
            'total_unclaimed' => $total_unclaimed,
            'total_claimed' => $total_claimed,
            'total_qty_unclaimed' => $total_qty_unclaimed,
            'total_qty_claimed' => $total_qty_claimed,
            'by_status' => $by_status,
            'by_branch' => $by_branch,
            'by_item' => $by_item
        ],
        'date_from' => $date_from,
        'date_to' => $date_to,
        'branch_filter' => $branch
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>