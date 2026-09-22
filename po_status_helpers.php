<?php
/**
 * Shared helpers for purchase order status and quantity totals.
 */

function get_po_quantity_totals(mysqli $conn, int $po_id): array
{
    $order_qty = 0;
    $items_result = $conn->query("SELECT COALESCE(SUM(quantity), 0) as total FROM purchase_order_items WHERE po_id = {$po_id} AND COALESCE(is_receive_added, 0) = 0");
    if ($items_result && ($row = $items_result->fetch_assoc())) {
        $order_qty = (int) ($row['total'] ?? 0);
    }

    $db_status = '';
    $header_result = $conn->query("SELECT total_qty, status FROM purchase_orders WHERE id = {$po_id} LIMIT 1");
    if ($header_result && ($header = $header_result->fetch_assoc())) {
        $db_status = $header['status'] ?? '';
        $header_qty = (int) ($header['total_qty'] ?? 0);
        if ($header_qty > $order_qty) {
            $order_qty = $header_qty;
        }
    }

    // ---------------------------------------------------------------
    // Mirror viewpurchaseorder.php: fetch individual allocation rows
    // (GROUP BY poa.id) to avoid inflated totals from duplicate rows.
    // Use serial number count for serialized items; received_qty for
    // non-serialized items.
    // ---------------------------------------------------------------
    $collate = 'utf8mb4_unicode_ci';
    $allocated = 0;
    $received = 0;

    $alloc_result = $conn->query("
        SELECT
            poa.id,
            poa.quantity,
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
            poa.received_at
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
        WHERE poa.po_id = {$po_id}
        GROUP BY poa.id, poi.id
    ");

    if ($alloc_result) {
        while ($alloc = $alloc_result->fetch_assoc()) {
            $allocated += (int) $alloc['quantity'];

            // Count received same way viewpurchaseorder.php does
            // CRITICAL: For unserialized items, only count received_qty if formally received
            if ((int) $alloc['has_serial'] === 1) {
                // Serialized: count serial numbers
                $sn = trim($alloc['serial_number'] ?? '');
                if (!empty($sn)) {
                    $sn_arr = (strpos($sn, "\n") !== false)
                        ? explode("\n", $sn)
                        : explode(",", $sn);
                    $received += count(array_filter(array_map('trim', $sn_arr)));
                }
            } else {
                // Unserialized: count received_qty if > 0 or formally received
                if ((int) $alloc['poa_received_qty'] > 0 || !empty($alloc['received_by']) || !empty($alloc['received_at'])) {
                    $received += (int) $alloc['poa_received_qty'];
                }
            }
        }
    }

    return [
        'total_order_qty' => $order_qty,
        'total_allocated' => $allocated,
        'total_received' => $received,
        'db_status' => $db_status,
    ];
}

function calculate_po_workflow_status(array $totals, ?string $db_status = null): string
{
    $db_status = $db_status ?? ($totals['db_status'] ?? '');

    if (strcasecmp($db_status, 'Canceled') === 0 || strcasecmp($db_status, 'Cancelled') === 0) {
        return $db_status;
    }
    if (strcasecmp($db_status, 'Closed') === 0) {
        return 'Closed';
    }
    if (strcasecmp($db_status, 'Incomplete') === 0) {
        return 'Incomplete';
    }
    if (strcasecmp($db_status, 'Received') === 0) {
        return 'Received';
    }
    if (strcasecmp($db_status, 'Completed') === 0) {
        return 'Completed';
    }

    $order_qty = (int) ($totals['total_order_qty'] ?? 0);
    $allocated = (int) ($totals['total_allocated'] ?? 0);
    $received = (int) ($totals['total_received'] ?? 0);

    // No allocations yet → show as "Open" (Pending internally)
    if ($allocated === 0) {
        return 'Pending';
    }
    // Some items allocated but not all → Incomplete
    if ($order_qty > $allocated) {
        return 'Incomplete';
    }
    if ($received === 0) {
        return 'Pending';
    }
    if ($received >= $allocated) {
        return 'Received';
    }

    return 'Incomplete';
}
