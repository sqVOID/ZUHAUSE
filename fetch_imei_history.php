<?php
require_once 'session_check.php';
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imei = isset($_POST['imei']) ? trim($_POST['imei']) : '';
    
    if (empty($imei)) {
        echo json_encode([
            'success' => false,
            'message' => 'IMEI number is required'
        ]);
        exit;
    }

    try {
        // Prepare the SQL query to search for IMEI history
        // Query from actual database tables
        
        $stmt = $conn->prepare("
            SELECT 
                DATE_FORMAT(transaction_date, '%Y-%m-%d') as date,
                branch_name as branch,
                imei,
                item_code,
                item_description,
                transaction_type,
                status
            FROM (
                -- Sales transactions (including voided sales)
                SELECT 
                    se.created_at as transaction_date,
                    b.branch_name,
                    sei.imei,
                    sei.item_code,
                    sei.item_description,
                    CASE WHEN se.status = 'voided' THEN 'Void Sale' ELSE 'Sale' END as transaction_type,
                    CASE WHEN se.status = 'voided' THEN 'VOID' ELSE 'Sold' END as status
                FROM sales_entry se
                JOIN sales_entry_items sei ON sei.sales_entry_id = se.id
                LEFT JOIN branches b ON b.branch_code = se.branch_code
                WHERE sei.imei = ?
                
                UNION ALL
                
                -- Stock transfer transactions (Out)
                SELECT 
                    st.st_date as transaction_date,
                    bf.branch_name as branch_name,
                    sti.imei,
                    sti.item_code,
                    i.item_description,
                    'Stock Transfer (Out)' as transaction_type,
                    'ST' as status
                FROM stock_transfer_items sti
                JOIN stock_transfers st ON st.st_number = sti.st_number
                LEFT JOIN branches bf ON bf.branch_code = st.branch_from
                LEFT JOIN items i ON i.item_code = sti.item_code
                WHERE sti.imei = ?
                
                UNION ALL
                
                -- Receive stock transfer (In)
                SELECT 
                    COALESCE(st.approval_date, st.st_date) as transaction_date,
                    bt.branch_name as branch_name,
                    sti.imei,
                    sti.item_code,
                    i.item_description,
                    'Stock Transfer (In)' as transaction_type,
                    'RST' as status
                FROM stock_transfer_items sti
                JOIN stock_transfers st ON st.st_number = sti.st_number
                LEFT JOIN branches bt ON bt.branch_code = st.branch_to
                LEFT JOIN items i ON i.item_code = sti.item_code
                WHERE sti.imei = ? AND st.status = 'Received'
                
                UNION ALL
                
                -- Stock on Hand (Received from PO - Current Stock)
                SELECT 
                    soh.created_at as transaction_date,
                    soh.branch as branch_name,
                    soh.imei,
                    i.item_code,
                    i.item_description,
                    'Purchase Order' as transaction_type,
                    'RR' as status
                FROM stock_on_hand soh
                LEFT JOIN items i ON i.family_code = soh.family_code
                WHERE soh.imei = ?
                  AND soh.dr_number IS NOT NULL
                  AND soh.dr_number != ''
                
                UNION ALL
                
                -- Historical RR from Sold Items (Preserves RR history after sale)
                SELECT 
                    COALESCE(po.received_at, po.po_date, se.created_at) as transaction_date,
                    b.branch_name,
                    sei.imei,
                    sei.item_code,
                    sei.item_description,
                    'Purchase Order' as transaction_type,
                    'RR' as status
                FROM sales_entry_items sei
                JOIN sales_entry se ON se.id = sei.sales_entry_id
                LEFT JOIN branches b ON b.branch_code = se.branch_code
                LEFT JOIN purchase_orders po ON po.po_number = sei.dr_number
                WHERE sei.imei = ?
                  AND sei.dr_number IS NOT NULL
                  AND sei.dr_number != ''
                
                UNION ALL
                
                -- Refund transactions
                SELECT 
                    r.refund_date as transaction_date,
                    b.branch_name,
                    ri.imei,
                    ri.item_code,
                    ri.item_description,
                    'Refund' as transaction_type,
                    'Refunded' as status
                FROM refunds r
                JOIN refund_items ri ON ri.refund_id = r.id
                LEFT JOIN branches b ON b.branch_code = r.branch_code
                WHERE ri.imei = ?
                
                UNION ALL
                
                -- Upgrade unit (Old Unit)
                SELECT 
                    u.created_at as transaction_date,
                    b.branch_name,
                    uoi.imei as imei,
                    '' as item_code,
                    uoi.item_description as item_description,
                    'Upgrade (Old Unit)' as transaction_type,
                    'Upgraded Out' as status
                FROM upgrade_old_items uoi
                JOIN upgrades u ON u.id = uoi.upgrade_id
                LEFT JOIN branches b ON b.branch_name = u.branch
                WHERE uoi.imei = ?
                
                UNION ALL
                
                -- Upgrade unit (New Unit)
                SELECT 
                    u.created_at as transaction_date,
                    b.branch_name,
                    uni.imei as imei,
                    uni.item_code as item_code,
                    uni.item_description as item_description,
                    'Upgrade (New Unit)' as transaction_type,
                    'Upgraded In' as status
                FROM upgrade_new_items uni
                JOIN upgrades u ON u.id = uni.upgrade_id
                LEFT JOIN branches b ON b.branch_name = u.branch
                WHERE uni.imei = ?
            ) as combined_history
            ORDER BY transaction_date DESC, branch
        ");
        
        // Bind parameters (8 times for each UNION query - added historical RR)
        $stmt->bind_param('ssssssss', $imei, $imei, $imei, $imei, $imei, $imei, $imei, $imei);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        
        if (count($records) > 0) {
            echo json_encode([
                'success' => true,
                'records' => $records,
                'message' => 'Records found'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'records' => [],
                'message' => 'No history found for IMEI: ' . htmlspecialchars($imei)
            ]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        error_log('IMEI History Error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while fetching IMEI history',
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}

$conn->close();
?>
