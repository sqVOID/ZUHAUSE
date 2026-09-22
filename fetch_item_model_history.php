<?php
require_once 'session_check.php';
require_once 'config.php';

header('Content-Type: application/json');

$date_from = isset($_POST['date_from']) ? trim($_POST['date_from']) : '';
$date_to = isset($_POST['date_to']) ? trim($_POST['date_to']) : '';
$branch = isset($_POST['branch']) ? trim($_POST['branch']) : '';
$model_code = isset($_POST['model_code']) ? trim($_POST['model_code']) : '';

if (empty($date_from) || empty($date_to)) {
    echo json_encode(['success' => false, 'message' => 'Date range is required']);
    exit;
}

if (empty($model_code)) {
    echo json_encode(['success' => false, 'message' => 'Model code is required']);
    exit;
}

$records = [];

// Build branch filter
$branch_filter = '';
$branch_param = '';
if (!empty($branch)) {
    $branch_filter = " AND branch_code = ?";
    $branch_param = $branch;
}

// Combined query to get item model history from multiple sources
$sql_history = "
    SELECT 
        DATE_FORMAT(transaction_date, '%Y-%m-%d') as date,
        invoice_no,
        quantity,
        branch_name as branch,
        item_code as model_code,
        item_description,
        imei,
        transaction_type,
        status,
        accountability
    FROM (
        -- Sales Entry (including voided sales)
        SELECT 
            se.created_at as transaction_date,
            se.invoice_no as invoice_no,
            CASE WHEN se.status = 'voided' THEN 1 ELSE -1 END as quantity,
            b.branch_name,
            sei.item_code,
            i.description as item_description,
            sei.imei,
            'SALES' as transaction_type,
            CASE WHEN se.status = 'voided' THEN 'VOID' ELSE 'SOLD' END as status,
            se.branch_code,
            COALESCE(se.encoder, 'N/A') as accountability
        FROM sales_entry se
        JOIN sales_entry_items sei ON sei.sales_entry_id = se.id
        LEFT JOIN items i ON i.item_code = sei.item_code
        LEFT JOIN branches b ON b.branch_code = se.branch_code
        WHERE sei.item_code = ?
          AND DATE(se.created_at) BETWEEN ? AND ?
        
        UNION ALL
        
        -- Stock on Hand (Received from PO)
        SELECT 
            soh.created_at as transaction_date,
            COALESCE(po.invoice_number, soh.dr_number) as invoice_no,
            1 as quantity,
            soh.branch as branch_name,
            i.item_code,
            i.description as item_description,
            soh.imei,
            'RECEIVE' as transaction_type,
            'RR' as status,
            soh.branch as branch_code,
            COALESCE(po.received_by, 'N/A') as accountability
        FROM stock_on_hand soh
        LEFT JOIN items i ON i.family_code = soh.family_code
        LEFT JOIN purchase_orders po ON po.po_number = soh.dr_number
        WHERE i.item_code = ?
          AND DATE(soh.created_at) BETWEEN ? AND ?
          AND soh.dr_number IS NOT NULL
          AND soh.dr_number != ''
        
        UNION ALL
        
        -- Stock Transfer (Out)
        SELECT 
            st.created_at as transaction_date,
            st.st_number as invoice_no,
            -1 as quantity,
            bf.branch_name,
            sti.item_code,
            i.description as item_description,
            sti.imei,
            'STOCK TRANSFER' as transaction_type,
            'ST' as status,
            st.branch_from as branch_code,
            COALESCE(st.prepared_by, 'N/A') as accountability
        FROM stock_transfer_items sti
        JOIN stock_transfers st ON st.st_number = sti.st_number
        LEFT JOIN branches bf ON bf.branch_code = st.branch_from
        LEFT JOIN items i ON i.item_code = sti.item_code
        WHERE sti.item_code = ?
          AND DATE(st.created_at) BETWEEN ? AND ?
        
        UNION ALL
        
        -- Received Stock Transfer (In)
        SELECT 
            COALESCE(st.approval_date, st.st_date) as transaction_date,
            st.st_number as invoice_no,
            1 as quantity,
            bt.branch_name,
            sti.item_code,
            i.description as item_description,
            sti.imei,
            'STOCK TRANSFER' as transaction_type,
            'STR' as status,
            st.branch_to as branch_code,
            COALESCE(st.approver, st.prepared_by, 'N/A') as accountability
        FROM stock_transfer_items sti
        JOIN stock_transfers st ON st.st_number = sti.st_number
        LEFT JOIN branches bt ON bt.branch_code = st.branch_to
        LEFT JOIN items i ON i.item_code = sti.item_code
        WHERE sti.item_code = ?
          AND DATE(COALESCE(st.approval_date, st.st_date)) BETWEEN ? AND ?
          AND st.status = 'Received'
          
        UNION ALL
        
        -- Refund transactions
        SELECT 
            r.refund_date as transaction_date,
            r.invoice_no as invoice_no,
            1 as quantity,
            b.branch_name,
            ri.item_code,
            i.description as item_description,
            ri.imei,
            'REFUND' as transaction_type,
            'REFUNDED' as status,
            r.branch_code,
            COALESCE(r.encoder, 'N/A') as accountability
        FROM refunds r
        JOIN refund_items ri ON ri.refund_id = r.id
        LEFT JOIN branches b ON b.branch_code = r.branch_code
        LEFT JOIN items i ON i.item_code = ri.item_code
        WHERE ri.item_code = ?
          AND DATE(r.refund_date) BETWEEN ? AND ?
        
        UNION ALL
        
        -- Upgrade unit (New Unit)
        SELECT 
            u.created_at as transaction_date,
            u.upgrade_no as invoice_no,
            -1 as quantity,
            u.branch as branch_name,
            uni.item_code,
            uni.item_description,
            uni.imei,
            'UPGRADE' as transaction_type,
            'NEW UNIT' as status,
            u.branch as branch_code,
            COALESCE(u.created_by, 'N/A') as accountability
        FROM upgrade_new_items uni
        JOIN upgrades u ON u.id = uni.upgrade_id
        WHERE uni.item_code = ?
          AND DATE(u.created_at) BETWEEN ? AND ?
    ) as combined_history
    WHERE 1=1 " . $branch_filter . "
    ORDER BY transaction_date DESC, transaction_type, status
";

$stmt = $conn->prepare($sql_history);
if ($stmt) {
    if (!empty($branch_param)) {
        // With branch filter - 6 instances of model_code, 12 instances of dates, 1 branch = 19 params
        $stmt->bind_param('sssssssssssssssssss', 
            $model_code, $date_from, $date_to,    // Sales
            $model_code, $date_from, $date_to,    // Stock on Hand
            $model_code, $date_from, $date_to,    // ST Out
            $model_code, $date_from, $date_to,    // ST In
            $model_code, $date_from, $date_to,    // Refund
            $model_code, $date_from, $date_to,    // Upgrade New
            $branch_param                          // Branch filter
        );
    } else {
        // Without branch filter - 6 instances of model_code, 12 instances of dates = 18 params
        $stmt->bind_param('ssssssssssssssssss', 
            $model_code, $date_from, $date_to,    // Sales
            $model_code, $date_from, $date_to,    // Stock on Hand
            $model_code, $date_from, $date_to,    // ST Out
            $model_code, $date_from, $date_to,    // ST In
            $model_code, $date_from, $date_to,    // Refund
            $model_code, $date_from, $date_to     // Upgrade New
        );
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $records[] = [
            'date' => $row['date'],
            'invoice_no' => $row['invoice_no'] ? $row['invoice_no'] : 'N/A',
            'quantity' => $row['quantity'],
            'branch' => $row['branch'] ? $row['branch'] : 'N/A',
            'model_code' => $row['model_code'] ? strtoupper($row['model_code']) : 'N/A',
            'item_description' => $row['item_description'] ? $row['item_description'] : 'N/A',
            'imei' => $row['imei'] ? $row['imei'] : 'N/A',
            'transaction_type' => $row['transaction_type'],
            'status' => $row['status'],
            'accountability' => $row['accountability'] ? $row['accountability'] : 'N/A'
        ];
    }
    $stmt->close();
}

if (count($records) > 0) {
    echo json_encode(['success' => true, 'records' => $records]);
} else {
    echo json_encode(['success' => false, 'message' => 'No records found for this model code in the selected date range']);
}

$conn->close();
?>
