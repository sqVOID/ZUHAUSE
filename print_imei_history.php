<?php
require_once 'session_check.php';
// Simplified version without session_check to avoid conflicts
require_once 'config.php';
require_once 'fpdf.php';

$imei = isset($_GET['imei']) ? trim($_GET['imei']) : '';

if (empty($imei)) {
    die('IMEI is required');
}

// Query to get IMEI history from multiple sources
$records = [];

// Combined query matching fetch_imei_history.php structure
$sql_history = "
    SELECT 
        DATE_FORMAT(transaction_date, '%Y-%m-%d') as date,
        status,
        invoice_ref_no,
        item_code,
        imei,
        details
    FROM (
        -- Sales Entry (including voided sales)
        SELECT 
            se.created_at as transaction_date,
            CASE WHEN se.status = 'voided' THEN 'VOID' ELSE 'Sold' END as status,
            se.invoice_no as invoice_ref_no,
            sei.item_code,
            sei.imei,
            CASE 
                WHEN se.status = 'voided' THEN CONCAT('VOIDED SALE - ', COALESCE(se.invoice_no, 'N/A'), ' - ', COALESCE(b.branch_name, 'N/A'))
                ELSE CONCAT('SOLD TO ', COALESCE(se.invoice_no, 'N/A'), ' - ', COALESCE(b.branch_name, 'N/A'))
            END as details
        FROM sales_entry se
        JOIN sales_entry_items sei ON sei.sales_entry_id = se.id
        LEFT JOIN items i ON i.item_code = sei.item_code
        LEFT JOIN branches b ON b.branch_code = se.branch_code
        WHERE sei.imei = ?
        
        UNION ALL
        
        -- Stock on Hand (Received from PO - Current Stock)
        SELECT 
            soh.created_at as transaction_date,
            'RR' as status,
            COALESCE(po.invoice_number, soh.dr_number) as invoice_ref_no,
            i.item_code,
            soh.imei,
            CONCAT('RECEIVED FROM SUPPLIER - ', 
                   COALESCE(po.supplier_company, 'N/A'), 
                   ', ', 
                   COALESCE(po.invoice_number, soh.dr_number), 
                   ' - ', 
                   COALESCE(soh.branch, 'N/A')) as details
        FROM stock_on_hand soh
        LEFT JOIN items i ON i.family_code = soh.family_code
        LEFT JOIN purchase_orders po ON po.po_number = soh.dr_number
        WHERE soh.imei = ?
          AND soh.dr_number IS NOT NULL
          AND soh.dr_number != ''
        
        UNION ALL
        
        -- Historical RR from Sold Items (Preserves RR history after sale)
        SELECT 
            COALESCE(po.received_at, po.po_date, se.created_at) as transaction_date,
            'RR' as status,
            COALESCE(po.invoice_number, sei.dr_number) as invoice_ref_no,
            sei.item_code,
            sei.imei,
            CONCAT('RECEIVED FROM SUPPLIER - ', 
                   COALESCE(po.supplier_company, 'N/A'), 
                   ', ', 
                   COALESCE(po.invoice_number, sei.dr_number), 
                   ' - ', 
                   COALESCE(b.branch_name, 'N/A')) as details
        FROM sales_entry_items sei
        JOIN sales_entry se ON se.id = sei.sales_entry_id
        LEFT JOIN branches b ON b.branch_code = se.branch_code
        LEFT JOIN purchase_orders po ON po.po_number = sei.dr_number
        WHERE sei.imei = ?
          AND sei.dr_number IS NOT NULL
          AND sei.dr_number != ''
        
        UNION ALL
        
        -- Stock Transfer (Out)
        SELECT 
            st.created_at as transaction_date,
            'ST' as status,
            st.st_number as invoice_ref_no,
            sti.item_code,
            sti.imei,
            CONCAT('STOCK TRANSFER TO ', 
                   COALESCE(st.branch_to, 'N/A')) as details
        FROM stock_transfer_items sti
        JOIN stock_transfers st ON st.st_number = sti.st_number
        LEFT JOIN branches bf ON bf.branch_code = st.branch_from
        WHERE sti.imei = ?
        
        UNION ALL
        
        -- Received Stock Transfer (In)
        SELECT 
            COALESCE(st.approval_date, st.st_date) as transaction_date,
            'RST' as status,
            st.st_number as invoice_ref_no,
            sti.item_code,
            sti.imei,
            CONCAT('RECEIVED STOCK TRANSFER FROM ', 
                   COALESCE(bf.branch_name, st.branch_from, 'N/A')) as details
        FROM stock_transfer_items sti
        JOIN stock_transfers st ON st.st_number = sti.st_number
        LEFT JOIN branches bf ON bf.branch_code = st.branch_from
        WHERE sti.imei = ?
          AND st.status = 'Received'
          
        UNION ALL
        
        -- Refund transactions
        SELECT 
            r.refund_date as transaction_date,
            'Refunded' as status,
            r.invoice_no as invoice_ref_no,
            ri.item_code,
            ri.imei,
            CONCAT('REFUNDED - ', 
                   COALESCE(r.invoice_no, 'N/A'), 
                   ' - ', 
                   COALESCE(b.branch_name, 'N/A')) as details
        FROM refunds r
        JOIN refund_items ri ON ri.refund_id = r.id
        LEFT JOIN branches b ON b.branch_code = r.branch_code
        WHERE ri.imei = ?
        
        UNION ALL
        
        -- Upgrade unit (Old Unit)
        SELECT 
            u.created_at as transaction_date,
            'Upgraded Out' as status,
            u.upgrade_no as invoice_ref_no,
            '' as item_code,
            uoi.imei as imei,
            CONCAT('UPGRADE OUT - Old Unit Returned (', COALESCE(u.upgrade_no, 'N/A'), ')') as details
        FROM upgrade_old_items uoi
        JOIN upgrades u ON u.id = uoi.upgrade_id
        WHERE uoi.imei = ?
        
        UNION ALL
        
        -- Upgrade unit (New Unit)
        SELECT 
            u.created_at as transaction_date,
            'Upgraded In' as status,
            u.upgrade_no as invoice_ref_no,
            uni.item_code as item_code,
            uni.imei as imei,
            CONCAT('UPGRADE IN - New Unit Issued (', COALESCE(u.upgrade_no, 'N/A'), ')') as details
        FROM upgrade_new_items uni
        JOIN upgrades u ON u.id = uni.upgrade_id
        WHERE uni.imei = ?
    ) as combined_history
    ORDER BY transaction_date DESC
";

$stmt = $conn->prepare($sql_history);
if ($stmt) {
    // Now we have 8 IMEI parameters (added one more for historical RR)
    $stmt->bind_param('ssssssss', $imei, $imei, $imei, $imei, $imei, $imei, $imei, $imei);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    $stmt->close();
}

// Create PDF using FPDF (matching your existing style)
class IMEIHistoryPDF extends FPDF
{
    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Courier', 'B', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, 'PAGE ' . $this->PageNo(), 0, 0, 'C');
    }
    
    // Multi-cell row with borders
    function Row($widths, $data, $start_x)
    {
        // Calculate the height needed for all cells in this row
        $nb = 0;
        for($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($widths[$i], $data[$i]));
        }
        $h = 5 * $nb; // Line height of 5mm
        
        // Check if we need a page break
        $this->CheckPageBreak($h);
        
        // Draw the cells
        for($i = 0; $i < count($data); $i++) {
            $w = $widths[$i];
            $x = $this->GetX();
            $y = $this->GetY();
            
            // Draw border
            $this->Rect($x, $y, $w, $h);
            
            // Print text with MultiCell
            $this->MultiCell($w, 5, $data[$i], 0, $i == 5 ? 'L' : 'C');
            
            // Position to the right of the cell for the next cell
            $this->SetXY($x + $w, $y);
        }
        // Go to the next line
        $this->Ln($h);
        $this->SetX($start_x);
    }
    
    function NbLines($w, $txt)
    {
        // Calculate the number of lines a MultiCell will use
        $cw = &$this->CurrentFont['cw'];
        if($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if($nb > 0 && $s[$nb - 1] == "\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while($i < $nb)
        {
            $c = $s[$i];
            if($c == "\n")
            {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if($c == ' ')
                $sep = $i;
            $l += isset($cw[$c]) ? $cw[$c] : 500;
            if($l > $wmax)
            {
                if($sep == -1)
                {
                    if($i == $j)
                        $i++;
                }
                else
                    $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            }
            else
                $i++;
        }
        return $nl;
    }
    
    function CheckPageBreak($h)
    {
        // If the height h would cause an overflow, add a new page immediately
        if($this->GetY() + $h > $this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation);
    }
}

$pdf = new IMEIHistoryPDF('L', 'mm', 'A4');
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
$pdf->Cell(0, 8, 'IMEI HISTORY', 0, 1, 'C');
$pdf->Ln(3);

// Table Header (removed IMEI and TIME & DATE info)
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Courier', 'B', 7);

$widths = [25, 18, 30, 50, 35, 107];
$headers = ['DATE', 'STATUS', 'INV/REF NO.', 'ITEM CODE', 'IMEI', 'DETAILS'];

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
        $status_display = $row['status'] ? strtoupper($row['status']) : 'N/A';
        $invoice_ref_display = $row['invoice_ref_no'] ? strtoupper($row['invoice_ref_no']) : 'N/A';
        $item_code_display = $row['item_code'] ? strtoupper($row['item_code']) : 'N/A';
        $imei_display = $row['imei'] ? strtoupper($row['imei']) : 'N/A';
        $details_display = $row['details'] ? strtoupper($row['details']) : 'N/A';
        
        $pdf->SetX($start_x);
        $data = [
            $date_display,
            $status_display,
            $invoice_ref_display,
            $item_code_display,
            $imei_display,
            $details_display
        ];
        $pdf->Row($widths, $data, $start_x);
    }
}

$pdf->Output('I', 'IMEI_History_' . $imei . '.pdf');

$conn->close();
?>
