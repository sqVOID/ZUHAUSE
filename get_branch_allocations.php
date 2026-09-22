<?php
/**
 * Get Branch Allocations
 * Fetches all allocations for a specific branch in a purchase order
 */

require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    // Get parameters
    $po_id = isset($_GET['po_id']) ? (int)$_GET['po_id'] : 0;
    $branch_name = isset($_GET['branch_name']) ? trim($_GET['branch_name']) : '';
    
    // Validate inputs
    if ($po_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid PO ID.']);
        exit;
    }
    
    if (empty($branch_name)) {
        echo json_encode(['success' => false, 'message' => 'Branch name is required.']);
        exit;
    }
    
    $collate = 'utf8mb4_unicode_ci';

    // Fetch allocations for this branch (one row per allocation)
    $allocations_query = $conn->query("
        SELECT 
            poa.id,
            poa.family_code,
            poa.item_model,
            poa.quantity,
            poa.cost,
            COALESCE(poa.received_qty, 0) as received_qty,
            poa.serial_number,
            poa.invoice_number,
            poa.receiving_remarks,
            COALESCE(MAX(CASE 
                WHEN poa.item_model IS NOT NULL 
                    AND poa.item_model != '' 
                    AND poa.item_model != '-' 
                    AND poa.item_model COLLATE {$collate} = i.item_code COLLATE {$collate}
                THEN i.has_serial 
                ELSE 0 
            END), 0) as has_serial,
            (
                SELECT MAX(poi.quantity)
                FROM purchase_order_items poi
                WHERE poi.po_id = poa.po_id
                AND poi.family_code COLLATE {$collate} = poa.family_code COLLATE {$collate}
            ) as total_quantity
        FROM purchase_order_allocations poa
        LEFT JOIN items i ON poa.family_code COLLATE {$collate} = i.family_code COLLATE {$collate}
        WHERE poa.po_id = {$po_id}
        AND poa.branch_name = '" . $conn->real_escape_string($branch_name) . "'
        GROUP BY poa.id
        ORDER BY poa.family_code ASC
    ");
    
    $allocations = [];
    if ($allocations_query && $allocations_query->num_rows > 0) {
        while ($allocation = $allocations_query->fetch_assoc()) {
            $alloc_qty = (int)$allocation['quantity'];
            $recv_qty = 0;

            if ((int)$allocation['has_serial'] === 1) {
                $sn = trim($allocation['serial_number'] ?? '');
                if (!empty($sn)) {
                    $sn_arr = (strpos($sn, "\n") !== false)
                        ? explode("\n", $sn)
                        : explode(",", $sn);
                    $recv_qty = count(array_filter(array_map('trim', $sn_arr)));
                }
            } else {
                $recv_qty = (int)$allocation['received_qty'];
            }

            if ($recv_qty > $alloc_qty) {
                $recv_qty = $alloc_qty;
            }

            $allocations[] = [
                'id' => $allocation['id'],
                'family_code' => $allocation['family_code'],
                'quantity' => $alloc_qty,
                'cost' => (float)$allocation['cost'],
                'received_qty' => $recv_qty,
                'invoice_number' => $allocation['invoice_number'] ?: '',
                'receiving_remarks' => $allocation['receiving_remarks'] ?: '',
                'total_quantity' => (int)$allocation['total_quantity']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'allocations' => $allocations,
        'branch_name' => $branch_name
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
