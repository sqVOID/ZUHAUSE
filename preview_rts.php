<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

if (!isset($_GET['rts_number']) || empty($_GET['rts_number'])) {
    die("Invalid RTS Number.");
}

$rts_number = $conn->real_escape_string($_GET['rts_number']);

// Fetch RTS header
$stmt = $conn->prepare("SELECT rts.*, b.branch_name, ral.status, ral.approver, ral.approval_date, ral.disapprover, ral.disapproval_date
                        FROM return_to_supplier rts
                        LEFT JOIN branches b ON rts.branch_from = b.branch_code
                        LEFT JOIN rts_approval_log ral ON rts.id = ral.rts_id
                        WHERE rts.rts_number = ?
                        LIMIT 1");
$stmt->bind_param("s", $rts_number);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Return to Supplier record not found.");
}

$header = $res->fetch_assoc();
$stmt->close();

// Fetch RTS items
$items = [];
$stmt_items = $conn->prepare("
    SELECT rtsi.*
    FROM return_to_supplier_items rtsi
    WHERE rtsi.rts_id = ?
    ORDER BY rtsi.id ASC
");
$stmt_items->bind_param("i", $header['id']);
$stmt_items->execute();
$res_items = $stmt_items->get_result();
while ($row = $res_items->fetch_assoc()) {
    $items[] = $row;
}
$stmt_items->close();

// Calculate totals
$total_quantity = 0;
$total_cost = 0;
foreach ($items as $item) {
    $qty = intval($item['quantity']);
    $cost = floatval($item['cost']);
    $total_quantity += $qty;
    $total_cost += ($cost * $qty);
}

// Format dates
$rts_date = !empty($header['rts_date']) ? $header['rts_date'] : 'N/A';
$created_date = !empty($header['created_at']) ? date('F d, Y', strtotime($header['created_at'])) : 'N/A';

// Get branch display
$branch_display = $header['branch_name'] ? $header['branch_name'] . ' (' . $header['branch_from'] . ')' : $header['branch_from'];

// Get status information
$status = isset($header['status']) ? $header['status'] : 'Pending';
$status_by = '';
$status_date = '';

if ($status === 'Approved' && !empty($header['approver'])) {
    $status_by = $header['approver'];
    $status_date = !empty($header['approval_date']) ? date('F d, Y', strtotime($header['approval_date'])) : '';
} elseif ($status === 'Disapproved' && !empty($header['disapprover'])) {
    $status_by = $header['disapprover'];
    $status_date = !empty($header['disapproval_date']) ? date('F d, Y', strtotime($header['disapproval_date'])) : '';
}

// Create PDF with custom footer
class PDF extends FPDF
{
    function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        $this->SetTextColor(0, 0, 0); // Black color
        $this->SetFont('Courier', 'B', 10);
        $this->Cell(0, 10, 'PAGE 1 OF 1', 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);

// Logo and Title on same line
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 10, 20, 20);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(22);
$pdf->Cell(0, 10, 'RETURN TO SUPPLIER', 0, 1, 'C');
$pdf->Ln(5);

// Meta information
$pdf->SetFont('Courier', 'B', 9);

$pdf->Cell(95, 6, 'RTS NUMBER: ' . $header['rts_number'], 0, 0, 'L');
$pdf->Cell(95, 6, 'DATE: ' . $rts_date, 0, 1, 'R');

$pdf->Cell(95, 6, 'REFERENCE NO: ' . ($header['reference_number'] ?: 'N/A'), 0, 0, 'L');
$pdf->Cell(95, 6, 'BRANCH: ' . $branch_display, 0, 1, 'R');

$pdf->Cell(95, 6, 'DELIVERY TO: ' . ($header['delivery_to'] ?: 'N/A'), 0, 0, 'L');
$pdf->Cell(95, 6, 'PREPARED BY: ' . ($header['created_by'] ?: 'Unknown'), 0, 1, 'R');

// Remarks
$remarks = !empty($header['remarks']) ? $header['remarks'] : 'No remarks';
$pdf->Cell(190, 6, 'REMARKS: ' . $remarks, 0, 1, 'L');

$pdf->Ln(5);

// Items Table Header
$pdf->SetFont('Courier', 'B', 8);
$pdf->SetFillColor(240, 240, 240);

$pdf->Cell(15, 8, 'QTY', 1, 0, 'C', true);$pdf->Cell(60, 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'IMEI', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'COST', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'REASON', 1, 1, 'C', true);

// Items Table Body
$pdf->SetFont('Courier', '', 7);

if (count($items) === 0) {
    $pdf->Cell(190, 8, 'No Items', 1, 1, 'C');
} else {
    $current_reason = null;
    $reason_start_y = 0;
    $reason_row_count = 0;
    
    foreach ($items as $index => $item) {
        $item_reason = $item['reason'] ?: '-';
        
        // Check if this is a new reason group
        if ($current_reason !== $item_reason) {
            // Draw the previous reason cell if exists
            if ($current_reason !== null && $reason_row_count > 0) {
                $reason_height = $reason_row_count * 8;
                $pdf->Rect(165, $reason_start_y, 40, $reason_height);
                $pdf->SetXY(165, $reason_start_y + ($reason_height / 2) - 2);
                $pdf->Cell(40, 4, $current_reason, 0, 0, 'C');
            }
            
            // Start new reason group
            $current_reason = $item_reason;
            $reason_start_y = $pdf->GetY();
            $reason_row_count = 0;
        }
        
        // Get item description
        $description = !empty($item['item_description']) ? $item['item_description'] : '-';
        
        $chars_per_mm = 0.55;
        $desc_chars = floor(60 * $chars_per_mm);
        $desc_lines = explode("\n", wordwrap($description, $desc_chars, "\n", true));
        $row_height = max(8, count($desc_lines) * 4);
        
        $x = 15;
        $y = $pdf->GetY();
        
        // Check page break
        if ($y + $row_height > 270) {
            // Draw pending reason before page break
            if ($reason_row_count > 0) {
                $reason_height = $reason_row_count * 8;
                $pdf->Rect(165, $reason_start_y, 40, $reason_height);
                $pdf->SetXY(165, $reason_start_y + ($reason_height / 2) - 2);
                $pdf->Cell(40, 4, $current_reason, 0, 0, 'C');
            }
            
            $pdf->AddPage();
            
            $pdf->SetFont('Courier', 'B', 8);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(15, 8, 'QTY', 1, 0, 'C', true);
            $pdf->Cell(60, 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
            $pdf->Cell(40, 8, 'IMEI', 1, 0, 'C', true);
            $pdf->Cell(35, 8, 'COST', 1, 0, 'C', true);
            $pdf->Cell(40, 8, 'REASON', 1, 1, 'C', true);
            
            $pdf->SetFont('Courier', '', 7);
            $y = $pdf->GetY();
            
            // Reset reason tracking for new page
            $reason_start_y = $y;
            $reason_row_count = 0;
        }
        
        // Draw cell borders (except reason which will be drawn later)
        $pdf->Rect($x, $y, 15, $row_height);
        $pdf->Rect($x + 15, $y, 60, $row_height);
        $pdf->Rect($x + 75, $y, 40, $row_height);
        $pdf->Rect($x + 115, $y, 35, $row_height);
        
        $v_center = $y + ($row_height / 2) - 2;
        
        // QTY
        $pdf->SetXY($x, $v_center);
        $pdf->Cell(15, 4, $item['quantity'] ?: '0', 0, 0, 'C');
        
        // ITEM DESCRIPTION (Multiline)
        $start_y = $y + (($row_height - (count($desc_lines) * 4)) / 2);
        $curr_y = $start_y;
        foreach ($desc_lines as $line) {
            $pdf->SetXY($x + 15, $curr_y);
            $pdf->Cell(60, 4, $line, 0, 0, 'C');
            $curr_y += 4;
        }
        
        // IMEI
        $pdf->SetXY($x + 75, $v_center);
        $imei_value = $item['imei'] ?: '-';
        $pdf->Cell(40, 4, $imei_value, 0, 0, 'C');
        
        // COST
        $pdf->SetXY($x + 115, $v_center);
        $item_cost = floatval($item['cost']);
        $item_total = $item_cost * intval($item['quantity']);
        $pdf->Cell(35, 4, number_format($item_total, 2), 0, 0, 'C');
        
        $pdf->SetY($y + $row_height);
        $reason_row_count++;
    }
    
    // Draw the last reason cell
    if ($reason_row_count > 0) {
        $reason_height = $reason_row_count * 8;
        $pdf->Rect(165, $reason_start_y, 40, $reason_height);
        $pdf->SetXY(165, $reason_start_y + ($reason_height / 2) - 2);
        $pdf->Cell(40, 4, $current_reason, 0, 0, 'C');
    }
}

$pdf->Ln(5);

// Summary section
$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(190, 6, 'TOTAL QUANTITY: ' . $total_quantity, 0, 1, 'L');
$pdf->Cell(190, 6, 'TOTAL COST: ' . number_format($total_cost, 2), 0, 1, 'L');

$pdf->Ln(5);

// Output PDF
$pdf->Output('I', 'RTS_' . $header['rts_number'] . '.pdf');
?>
