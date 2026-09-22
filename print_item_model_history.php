<?php
require_once 'session_check.php';
// Simplified version without session_check to avoid conflicts
require_once 'config.php';
require_once 'fpdf.php';

$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$branch = isset($_GET['branch']) ? trim($_GET['branch']) : '';
$model_code = isset($_GET['model_code']) ? trim($_GET['model_code']) : '';

if (empty($date_from) || empty($date_to)) {
    die('Date range is required');
}

if (empty($model_code)) {
    die('Model code is required');
}

// Build branch filter
$branch_filter = '';
$branch_param = '';
$branch_name = 'All Branches';

if (!empty($branch)) {
    $branch_filter = " AND branch_code = ?";
    $branch_param = $branch;
    
    // Get branch name
    $branch_query = "SELECT branch_name FROM branches WHERE branch_code = ?";
    $stmt_branch = $conn->prepare($branch_query);
    if ($stmt_branch) {
        $stmt_branch->bind_param('s', $branch);
        $stmt_branch->execute();
        $result_branch = $stmt_branch->get_result();
        if ($row_branch = $result_branch->fetch_assoc()) {
            $branch_name = $row_branch['branch_name'];
        }
        $stmt_branch->close();
    }
}

$records = [];

// Combined query to get item model history
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
        
        -- Stock on Hand (Received from PO - Current Stock)
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
        
        -- Historical RR from Sold Items (Preserves RR history after sale)
        SELECT 
            COALESCE(po.received_at, po.po_date, se.created_at) as transaction_date,
            COALESCE(po.invoice_number, sei.dr_number) as invoice_no,
            1 as quantity,
            b.branch_name,
            sei.item_code,
            i.description as item_description,
            sei.imei,
            'RECEIVE' as transaction_type,
            'RR' as status,
            se.branch_code,
            COALESCE(po.received_by, 'N/A') as accountability
        FROM sales_entry_items sei
        JOIN sales_entry se ON se.id = sei.sales_entry_id
        LEFT JOIN items i ON i.item_code = sei.item_code
        LEFT JOIN branches b ON b.branch_code = se.branch_code
        LEFT JOIN purchase_orders po ON po.po_number = sei.dr_number
        WHERE sei.item_code = ?
          AND DATE(se.created_at) BETWEEN ? AND ?
          AND sei.dr_number IS NOT NULL
          AND sei.dr_number != ''
        
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
        // With branch filter - 7 queries × 3 params = 21, plus 1 branch = 22 total
        $stmt->bind_param('ssssssssssssssssssssss', 
            $model_code, $date_from, $date_to,    // Sales
            $model_code, $date_from, $date_to,    // Stock on Hand (Current)
            $model_code, $date_from, $date_to,    // Historical RR from Sales
            $model_code, $date_from, $date_to,    // ST Out
            $model_code, $date_from, $date_to,    // ST In
            $model_code, $date_from, $date_to,    // Refund
            $model_code, $date_from, $date_to,    // Upgrade New
            $branch_param                          // Branch filter
        );
    } else {
        // Without branch filter - 7 queries × 3 params = 21 total
        $stmt->bind_param('sssssssssssssssssssss', 
            $model_code, $date_from, $date_to,    // Sales
            $model_code, $date_from, $date_to,    // Stock on Hand (Current)
            $model_code, $date_from, $date_to,    // Historical RR from Sales
            $model_code, $date_from, $date_to,    // ST Out
            $model_code, $date_from, $date_to,    // ST In
            $model_code, $date_from, $date_to,    // Refund
            $model_code, $date_from, $date_to     // Upgrade New
        );
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    $stmt->close();
}

// Create PDF using FPDF
class ItemModelHistoryPDF extends FPDF
{
    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Courier', 'B', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, 'PAGE ' . $this->PageNo(), 0, 0, 'C');
    }
    
    // Helper function to truncate text to fit cell width
    function TruncateToFit($text, $width) {
        $text = (string)$text;
        // Get string width in mm
        $textWidth = $this->GetStringWidth($text);
        
        // If text fits, return as-is
        if ($textWidth <= ($width - 2)) { // -2mm for cell padding
            return $text;
        }
        
        // Binary search to find max characters that fit
        $left = 0;
        $right = strlen($text);
        $result = '';
        
        while ($left <= $right) {
            $mid = intval(($left + $right) / 2);
            $substring = substr($text, 0, $mid);
            $subWidth = $this->GetStringWidth($substring . '...');
            
            if ($subWidth <= ($width - 2)) {
                $result = $substring;
                $left = $mid + 1;
            } else {
                $right = $mid - 1;
            }
        }
        
        return $result . '...';
    }
}

$pdf = new ItemModelHistoryPDF('L', 'mm', 'A4');
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();

// Logo
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 10, 20, 20);
}

// Title
$pdf->SetFont('Courier', 'B', 14);
$pdf->SetY(22);
$pdf->Cell(0, 8, 'ITEM MODEL HISTORY', 0, 1, 'C');
$pdf->Ln(3);

// Table Header
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Courier', 'B', 7);

$widths = [20, 32, 10, 35, 40, 35, 16, 22, 27];
$headers = ['DATE', 'INVOICE NO.', 'QTY', 'MODEL', 'ITEM DESC', 'IMEI', 'STATUS', 'ACCOUNTABILITY', 'BRANCH'];

// Calculate starting X position to center the table
$table_width = array_sum($widths);
$page_width = 297; // A4 landscape width in mm
$start_x = ($page_width - $table_width) / 2;

$pdf->SetX($start_x);
for ($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
}
$pdf->Ln();

// Table Data
$pdf->SetFont('Courier', '', 6.5);
if (count($records) === 0) {
    $pdf->SetX($start_x);
    $pdf->Cell(array_sum($widths), 8, 'NO RECORDS FOUND', 1, 1, 'C');
} else {
    foreach ($records as $row) {
        $date_display = $row['date'] ? date('m/d/Y', strtotime($row['date'])) : 'N/A';
        $invoice_display = $row['invoice_no'] ? strtoupper($row['invoice_no']) : 'N/A';
        $qty_display = $row['quantity'];
        $model_code_display = $row['model_code'] ? strtoupper($row['model_code']) : 'N/A';
        $item_desc_display = $row['item_description'] ? strtoupper($row['item_description']) : 'N/A';
        $imei_display = $row['imei'] ? strtoupper($row['imei']) : 'N/A';
        $status_display = $row['status'] ? strtoupper($row['status']) : 'N/A';
        $accountability_display = $row['accountability'] ? strtoupper($row['accountability']) : 'N/A';
        $branch_display = $row['branch'] ? strtoupper($row['branch']) : 'N/A';
        
        // Truncate text to fit each cell width
        $date_display = $pdf->TruncateToFit($date_display, $widths[0]);
        $invoice_display = $pdf->TruncateToFit($invoice_display, $widths[1]);
        $qty_display = $pdf->TruncateToFit($qty_display, $widths[2]);
        $model_code_display = $pdf->TruncateToFit($model_code_display, $widths[3]);
        $item_desc_display = $pdf->TruncateToFit($item_desc_display, $widths[4]);
        $imei_display = $pdf->TruncateToFit($imei_display, $widths[5]);
        $status_display = $pdf->TruncateToFit($status_display, $widths[6]);
        $accountability_display = $pdf->TruncateToFit($accountability_display, $widths[7]);
        $branch_display = $pdf->TruncateToFit($branch_display, $widths[8]);
        
        $pdf->SetX($start_x);
        $pdf->Cell($widths[0], 6, $date_display, 1, 0, 'L');
        $pdf->Cell($widths[1], 6, $invoice_display, 1, 0, 'L');
        $pdf->Cell($widths[2], 6, $qty_display, 1, 0, 'L');
        $pdf->Cell($widths[3], 6, $model_code_display, 1, 0, 'L');
        $pdf->Cell($widths[4], 6, $item_desc_display, 1, 0, 'L');
        $pdf->Cell($widths[5], 6, $imei_display, 1, 0, 'L');
        $pdf->Cell($widths[6], 6, $status_display, 1, 0, 'L');
        $pdf->Cell($widths[7], 6, $accountability_display, 1, 0, 'L');
        $pdf->Cell($widths[8], 6, $branch_display, 1, 1, 'L');
    }
}

$pdf->Output('I', 'Item_Model_History_' . $model_code . '_' . date('Ymd') . '.pdf');

$conn->close();
?>
