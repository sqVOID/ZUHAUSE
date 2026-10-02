<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

try {
    $date_from = isset($_POST['date_from']) ? trim($_POST['date_from']) : '';
    $date_to = isset($_POST['date_to']) ? trim($_POST['date_to']) : '';
    $branch_filter = isset($_POST['branch']) ? trim($_POST['branch']) : 'ALL';

    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
    $is_super_admin = (strcasecmp($system_level, 'Super-Admin') === 0);
    $is_sub_admin = (strcasecmp($system_level, 'Sub-admin') === 0);
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

    // Resolve branch name(s) to branch code(s) — rts.branch_from stores CODE
    $resolveCodes = function (array $names) use ($conn) {
        $codes = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') continue;
            $esc = $conn->real_escape_string($name);
            $q = $conn->query("SELECT branch_code FROM branches 
                WHERE branch_name = '$esc' OR branch_code = '$esc' LIMIT 1");
            if ($q && $q->num_rows > 0) {
                $codes[] = $q->fetch_assoc()['branch_code'];
            } else {
                $codes[] = $name;
            }
        }
        return array_values(array_unique($codes));
    };

    $table_check = $conn->query("SHOW TABLES LIKE 'return_to_supplier'");
    if (!$table_check || $table_check->num_rows === 0) {
        echo json_encode([]);
        exit;
    }

    $where = ["COALESCE(ral.status, 'Pending') = 'Approved'"];

    if (!empty($date_from)) {
        $where[] = "DATE(rts.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
    }
    if (!empty($date_to)) {
        $where[] = "DATE(rts.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
    }

    // Branch access + optional dropdown filter
    if (!$is_super_admin) {
        $names = array_filter(array_map('trim', explode(',', $user_branch)));
        $codes = $resolveCodes($names);
        if (!empty($codes)) {
            $quoted = array_map(function ($c) use ($conn) {
                return "'" . $conn->real_escape_string($c) . "'";
            }, $codes);
            $where[] = "rts.branch_from IN (" . implode(', ', $quoted) . ")";
        } else {
            $where[] = "1=0";
        }
    }

    if (($is_super_admin || $is_sub_admin) && $branch_filter !== '' && strcasecmp($branch_filter, 'ALL') !== 0) {
        $codes = $resolveCodes([$branch_filter]);
        if (!empty($codes)) {
            $where[] = "rts.branch_from = '" . $conn->real_escape_string($codes[0]) . "'";
        }
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where);

    // Complete report: one row per RTS item (with IMEI)
    $sql = "SELECT 
                rts.rts_number,
                rts.rts_date,
                rts.reference_number,
                rts.branch_from,
                rts.branch_name,
                rts.delivery_to,
                rts.created_at,
                rts.created_by,
                rtsi.item_code,
                rtsi.item_description,
                rtsi.imei,
                rtsi.quantity,
                rtsi.cost
            FROM return_to_supplier rts
            INNER JOIN return_to_supplier_items rtsi ON rts.id = rtsi.rts_id
            LEFT JOIN rts_approval_log ral ON rts.id = ral.rts_id
            $where_clause
            ORDER BY rts.created_at DESC, rtsi.id ASC";

    $result = $conn->query($sql);
    if ($result === false) {
        throw new Exception($conn->error);
    }

    $records = [];
    while ($row = $result->fetch_assoc()) {
        $date = !empty($row['created_at'])
            ? date('m/d/Y', strtotime($row['created_at']))
            : ($row['rts_date'] ?? '');

        $branch_label = trim($row['branch_name'] ?? '');
        if ($branch_label === '') {
            $branch_label = $row['branch_from'] ?? '';
        }

        $records[] = [
            'date' => $date,
            'rts_number' => $row['rts_number'] ?? '',
            'reference_number' => $row['reference_number'] ?? '',
            'branch_from' => $branch_label,
            'delivery_to' => $row['delivery_to'] ?? '',
            'item_description' => $row['item_description'] ?? ($row['item_code'] ?? ''),
            'imei' => $row['imei'] ?? '',
            'quantity' => intval($row['quantity'] ?? 0),
            'cost' => number_format(floatval($row['cost'] ?? 0), 2),
            'created_by' => $row['created_by'] ?? ''
        ];
    }

    echo json_encode($records);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>
