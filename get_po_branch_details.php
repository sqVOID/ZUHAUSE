<?php
/**
 * Get PO Branch Details
 * Fetches branch allocation details for the View PO modal
 */

require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    // Get parameter
    $po_id = isset($_GET['po_id']) ? (int) $_GET['po_id'] : 0;

    // Validate input
    if ($po_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
        exit;
    }

    // Check if PO is Closed
    $po_status_query = $conn->query("SELECT status FROM purchase_orders WHERE id = {$po_id}");
    $is_po_closed = false;
    if ($po_status_query && $po_status_query->num_rows > 0) {
        $po_status_row = $po_status_query->fetch_assoc();
        $is_po_closed = (strcasecmp($po_status_row['status'], 'Closed') === 0);
    }

    $check_column = $conn->query("SHOW COLUMNS FROM purchase_order_allocations LIKE 'received_by'");
    $has_received_by_column = ($check_column && $check_column->num_rows > 0);

    $conn->query("ALTER TABLE purchase_order_allocations ADD COLUMN IF NOT EXISTS receiving_remarks TEXT");

    $collate = 'utf8mb4_unicode_ci';

    if ($has_received_by_column) {
        $sql = "
            SELECT 
                poa.id AS poa_id,
                poa.branch_name,
                poa.invoice_number,
                poa.received_at AS dr_date,
                poa.quantity AS allocated_qty,
                COALESCE(poa.received_qty, 0) AS poa_received_qty,
                COALESCE(poa.serial_number, poi.serial_number) AS serial_number,
                COALESCE(MAX(CASE 
                    WHEN poi.item_model IS NOT NULL 
                        AND poi.item_model != '' 
                        AND poi.item_model != '-' 
                        AND poi.item_model COLLATE {$collate} = i.item_code COLLATE {$collate}
                    THEN i.has_serial 
                    ELSE 0 
                END), 0) AS has_serial,
                poa.received_by,
                poa.receiving_remarks,
                CONCAT(acc.first_name, ' ', acc.last_name) AS received_by_fullname
            FROM purchase_order_allocations poa
            LEFT JOIN purchase_order_items poi
                   ON poa.po_id = poi.po_id
                  AND poa.family_code COLLATE {$collate} = poi.family_code COLLATE {$collate}
                  AND COALESCE(poi.is_receive_added, 0) = 0
                  AND (
                      poa.item_model IS NULL OR poa.item_model = '' OR poa.item_model = '-'
                      OR poi.item_model IS NULL OR poi.item_model = '' OR poi.item_model = '-'
                      OR poa.item_model COLLATE {$collate} = poi.item_model COLLATE {$collate}
                  )
            LEFT JOIN items i ON poa.family_code COLLATE {$collate} = i.family_code COLLATE {$collate}
                AND i.status = 'Active'
            LEFT JOIN accounts acc ON poa.received_by COLLATE {$collate} = acc.username COLLATE {$collate}
            WHERE poa.po_id = {$po_id}
            GROUP BY poa.id, poi.id
            ORDER BY poa.branch_name ASC
        ";
    } else {
        $sql = "
            SELECT 
                poa.id AS poa_id,
                poa.branch_name,
                poa.invoice_number,
                po.received_at AS dr_date,
                poa.quantity AS allocated_qty,
                COALESCE(poa.received_qty, 0) AS poa_received_qty,
                COALESCE(poa.serial_number, poi.serial_number) AS serial_number,
                COALESCE(MAX(CASE 
                    WHEN poi.item_model IS NOT NULL 
                        AND poi.item_model != '' 
                        AND poi.item_model != '-' 
                        AND poi.item_model COLLATE {$collate} = i.item_code COLLATE {$collate}
                    THEN i.has_serial 
                    ELSE 0 
                END), 0) AS has_serial,
                po.received_by,
                poa.receiving_remarks,
                CONCAT(acc.first_name, ' ', acc.last_name) AS received_by_fullname
            FROM purchase_order_allocations poa
            LEFT JOIN purchase_orders po ON poa.po_id = po.id
            LEFT JOIN purchase_order_items poi
                   ON poa.po_id = poi.po_id
                  AND poa.family_code COLLATE {$collate} = poi.family_code COLLATE {$collate}
                  AND COALESCE(poi.is_receive_added, 0) = 0
                  AND (
                      poa.item_model IS NULL OR poa.item_model = '' OR poa.item_model = '-'
                      OR poi.item_model IS NULL OR poi.item_model = '' OR poi.item_model = '-'
                      OR poa.item_model COLLATE {$collate} = poi.item_model COLLATE {$collate}
                  )
            LEFT JOIN items i ON poa.family_code COLLATE {$collate} = i.family_code COLLATE {$collate}
                AND i.status = 'Active'
            LEFT JOIN accounts acc ON po.received_by COLLATE {$collate} = acc.username COLLATE {$collate}
            WHERE poa.po_id = {$po_id}
            GROUP BY poa.id, poi.id
            ORDER BY poa.branch_name ASC
        ";
    }

    $branches_query = $conn->query($sql);
    if (!$branches_query) {
        echo json_encode([
            'success' => false,
            'message' => 'Query error: ' . $conn->error,
            'sql' => $sql
        ]);
        exit;
    }

    $branch_groups = [];
    while ($row = $branches_query->fetch_assoc()) {
        $key = $row['branch_name'] . '|' . ($row['invoice_number'] ?? '');
        if (!isset($branch_groups[$key])) {
            $branch_groups[$key] = [
                'branch_name' => $row['branch_name'],
                'invoice_number' => $row['invoice_number'] ?: '-',
                'dr_date' => $row['dr_date'] ? date('d/m/Y', strtotime($row['dr_date'])) : '-',
                'dr_received' => 0,
                'total_allocated' => 0,
                'received_by' => $row['received_by_fullname'] ?: ($row['received_by'] ?: '-'),
                'receiving_remarks' => $row['receiving_remarks'] ?: '-',
                'is_formally_received' => false
            ];
        }

        $alloc_qty = (int) $row['allocated_qty'];
        $branch_groups[$key]['total_allocated'] += $alloc_qty;

        // Check if formally received
        if (!empty($row['received_by'])) {
            $branch_groups[$key]['is_formally_received'] = true;
        }

        // Count received quantity
        if ((int) $row['has_serial'] === 1) {
            // Serialized: count serial numbers
            $sn = trim($row['serial_number'] ?? '');
            if (!empty($sn)) {
                $sn_arr = (strpos($sn, "\n") !== false)
                    ? explode("\n", $sn)
                    : explode(",", $sn);
                $branch_groups[$key]['dr_received'] += count(array_filter(array_map('trim', $sn_arr)));
            }
        } else {
            // Unserialized: count received_qty if > 0 or formally received
            if ((int) $row['poa_received_qty'] > 0 || !empty($row['received_by'])) {
                $branch_groups[$key]['dr_received'] += (int) $row['poa_received_qty'];
            }
        }
    }

    $branches = [];
    foreach ($branch_groups as $bg) {
        $alloc_qty = $bg['total_allocated'];
        $recv_qty = $bg['dr_received'];

        $status = 'Waiting';
        if ($alloc_qty > 0 && $recv_qty >= $alloc_qty) {
            $status = 'Completed';
        } elseif ($recv_qty > 0) {
            $status = 'Incomplete';
        }

        if ($is_po_closed) {
            $status = 'Closed';
        }

        $branches[] = [
            'branch_name' => $bg['branch_name'],
            'invoice_number' => $bg['invoice_number'],
            'dr_date' => $bg['dr_date'],
            'dr_received' => $recv_qty,
            'received_by' => $bg['received_by'],
            'status' => $status,
            'receiving_remarks' => $bg['receiving_remarks']
        ];
    }

    echo json_encode([
        'success' => true,
        'branches' => $branches
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
