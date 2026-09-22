<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

// Get filter parameters
$branch_f = isset($_GET['branch']) ? trim($_GET['branch']) : '';
$model_f = isset($_GET['model']) ? trim($_GET['model']) : '';
$brand_f = isset($_GET['brand']) ? trim($_GET['brand']) : '';
$family_f = isset($_GET['family']) ? trim($_GET['family']) : '';

// Branch Access Control
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$session_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

$is_superadmin = in_array(strtolower($system_level), ['super-admin', 'superadmin', 'sub-admin', 'subadmin']);

// Build branch IN(...) clause
$branch_in = '';
if (!$is_superadmin && $session_branch !== '' && strtoupper($session_branch) !== 'SUPERADMIN') {
    $raw_branches = array_map('trim', explode(',', $session_branch));
    $escaped_branches = array_map(fn($b) => "'" . addslashes($b) . "'", $raw_branches);
    $branch_in = implode(',', $escaped_branches);
}

$stock_data = [];

// Only query if at least one filter is applied (same logic as sohandserial.php)
$should_query = ($branch_f !== '' || $model_f !== '' || $brand_f !== '' || $family_f !== '');

// Super-Admin sees all stock, others see only their branch(es)
if ($should_query && ($is_superadmin || ($session_branch !== '' && strtoupper($session_branch) !== 'SUPERADMIN'))) {
    $conds = ["s.item_type = 'IMEI'"];
    
    // Apply branch filter only if not Super-Admin
    if (!$is_superadmin && $branch_in !== '') {
        $conds[] = "s.branch IN ($branch_in)";
    }
    
    if ($branch_f !== '' && $branch_f !== 'ALL_BRANCHES')
        $conds[] = "s.branch = '" . addslashes($branch_f) . "'";
    if ($model_f !== '')
        $conds[] = "s.item_code LIKE '%" . addslashes($model_f) . "%'";
    if ($brand_f !== '')
        $conds[] = "(COALESCE(i.brand, s.brand) = '" . addslashes($brand_f) . "')";
    if ($family_f !== '')
        $conds[] = "s.family_code = '" . addslashes($family_f) . "'";
    $where = implode(" AND ", $conds);

    $sql = "SELECT s.item_code, s.description, s.dr_date, s.imei, s.imei2, s.dr_number, s.branch, s.family_code,
                   COALESCE(i.serial_primary, 1) AS serial_primary,
                   DATEDIFF(CURDATE(), s.dr_date) AS aging,
                   DATEDIFF(CURDATE(), s.system_entry_date) AS iou,
                   s.status
            FROM stock_on_hand s
            LEFT JOIN items i ON s.item_code = i.item_code
            WHERE $where
            ORDER BY s.item_code, s.dr_date";
    $res = $conn->query($sql);
    
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $stock_data[] = $row;
        }
    }
}

// Create PDF
class PDF extends FPDF
{
    function Footer()
    {
        $this->SetY(-15);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Courier', 'B', 10);
        $this->Cell(0, 10, 'PAGE ' . $this->PageNo() . ' OF {nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape for more columns
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);

// Logo and Title
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 5, 10, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(23);
$pdf->Cell(0, 10, 'STOCK ON HAND - PER SERIAL', 0, 1, 'C');
$pdf->Ln(3);

// Report information
$pdf->SetFont('Courier', 'B', 9);

$y_pos = $pdf->GetY();
$pdf->SetXY(10, $y_pos);
$pdf->Cell(135, 6, 'BRANCH: ' . ($branch_f ?: 'All'), 0, 0, 'L');
$pdf->Cell(135, 6, 'DATE: ' . date('F d, Y'), 0, 1, 'R');

$y_pos = $pdf->GetY();
$pdf->SetXY(10, $y_pos);
$pdf->Cell(135, 6, 'BRAND: ' . ($brand_f ?: 'All'), 0, 0, 'L');
$pdf->Cell(135, 6, 'TIME: ' . date('h:i A'), 0, 1, 'R');

if ($model_f) {
    $pdf->Cell(135, 6, 'MODEL: ' . $model_f, 0, 1, 'L');
}
if ($family_f) {
    $pdf->Cell(135, 6, 'FAMILY: ' . $family_f, 0, 1, 'L');
}

$pdf->Ln(3);

// Table Header
$pdf->SetFont('Courier', 'B', 7);
$pdf->SetFillColor(240, 240, 240);

$pdf->Cell(45, 8, 'MODEL CODE', 1, 0, 'C', true);
$pdf->Cell(24, 8, 'DR DATE', 1, 0, 'C', true);
$pdf->Cell(45, 8, 'IMEI / SERIAL', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'DR RECEIVED', 1, 0, 'C', true);
$pdf->Cell(55, 8, 'BRANCH', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'FAMILY', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'AGING', 1, 0, 'C', true);
$pdf->Cell(18, 8, 'IOU', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Courier', '', 6);

if (count($stock_data) === 0) {
    $pdf->Cell(277, 8, 'No Stock Available', 1, 1, 'C');
} else {
    foreach ($stock_data as $row) {
        // Check if we need a new page
        if ($pdf->GetY() > 180) {
            $pdf->AddPage();
            
            // Repeat header on new page
            $pdf->SetFont('Courier', 'B', 7);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(45, 8, 'MODEL CODE', 1, 0, 'C', true);
            $pdf->Cell(24, 8, 'DR DATE', 1, 0, 'C', true);
            $pdf->Cell(45, 8, 'SERIAL', 1, 0, 'C', true);
            $pdf->Cell(35, 8, 'DR RECEIVED', 1, 0, 'C', true);
            $pdf->Cell(55, 8, 'BRANCH', 1, 0, 'C', true);
            $pdf->Cell(35, 8, 'FAMILY', 1, 0, 'C', true);
            $pdf->Cell(20, 8, 'AGING', 1, 0, 'C', true);
            $pdf->Cell(18, 8, 'IOU', 1, 1, 'C', true);
            $pdf->SetFont('Courier', '', 6);
        }
        
        // Handle text wrapping for long model codes
        $model_code = $row['item_code'];
        $dr_date = $row['dr_date'] ? date('m/d/Y', strtotime($row['dr_date'])) : '—';
        $serialPrimary = isset($row['serial_primary']) ? intval($row['serial_primary']) : 1;
        if ($serialPrimary == 2 && !empty($row['imei2'])) {
            $serial = $row['imei2'];
        } else {
            $serial = !empty($row['imei']) ? $row['imei'] : (!empty($row['imei2']) ? $row['imei2'] : '—');
        }
        $dr_number = $row['dr_number'] ?? '—';
        $branch = $row['branch'];
        $family = $row['family_code'] ?? '—';
        $aging = intval($row['aging']) . ' days';
        $iou = intval($row['iou']) . ' days';
        
        // Wrap text for model code (approx 26 chars per line for 45mm width)
        $model_lines = explode("\n", wordwrap($model_code, 26, "\n", true));
        
        // Calculate row height based on lines needed
        $row_height = max(6, count($model_lines) * 3);
        
        $x = 10; // Left margin
        $y = $pdf->GetY();
        
        // Draw all cell borders
        $pdf->Rect($x, $y, 45, $row_height);
        $pdf->Rect($x + 45, $y, 24, $row_height);
        $pdf->Rect($x + 69, $y, 45, $row_height);
        $pdf->Rect($x + 114, $y, 35, $row_height);
        $pdf->Rect($x + 149, $y, 55, $row_height);
        $pdf->Rect($x + 204, $y, 35, $row_height);
        $pdf->Rect($x + 239, $y, 20, $row_height);
        $pdf->Rect($x + 259, $y, 18, $row_height);
        
        // Calculate vertical center for single-line cells
        $v_center = $y + ($row_height / 2) - 1.5;
        
        // Draw MODEL CODE with line breaks (left aligned in cell)
        $model_start_y = $y + (($row_height - (count($model_lines) * 3)) / 2);
        $model_y = $model_start_y;
        foreach ($model_lines as $line) {
            $pdf->SetXY($x, $model_y);
            $pdf->Cell(45, 3, $line, 0, 0, 'L');
            $model_y += 3;
        }
        
        // Draw other cells (centered)
        $pdf->SetXY($x + 45, $v_center);
        $pdf->Cell(24, 3, $dr_date, 0, 0, 'C');
        
        $pdf->SetXY($x + 69, $v_center);
        // Check if serial length is exactly 15 characters (skip check for '—')
        if ($serial !== '—') {
            $serial_length = strlen($serial);
            if ($serial_length != 15) {
                // Set text color to red if not exactly 15 characters
                $pdf->SetTextColor(255, 0, 0);
            } else {
                // Set text color to black if exactly 15 characters
                $pdf->SetTextColor(0, 0, 0);
            }
        }
        $pdf->Cell(45, 3, $serial, 0, 0, 'C');
        // Reset text color to black
        $pdf->SetTextColor(0, 0, 0);
        
        $pdf->SetXY($x + 114, $v_center);
        $pdf->Cell(35, 3, $dr_number, 0, 0, 'C');
        
        $pdf->SetXY($x + 149, $v_center);
        $pdf->Cell(55, 3, $branch, 0, 0, 'C');
        
        $pdf->SetXY($x + 204, $v_center);
        $pdf->Cell(35, 3, $family, 0, 0, 'L');
        
        $pdf->SetXY($x + 239, $v_center);
        $pdf->Cell(20, 3, $aging, 0, 0, 'C');
        
        $pdf->SetXY($x + 259, $v_center);
        $pdf->Cell(18, 3, $iou, 0, 0, 'C');
        
        // Move to next row
        $pdf->SetXY($x, $y + $row_height);
    }
    
    $pdf->Ln(3);
    
    // Summary
    $pdf->SetFont('Courier', 'B', 10);
    $pdf->SetTextColor(211, 47, 47);
    
    $pdf->Cell(50, 6, 'TOTAL RECORDS:', 0, 0, 'L');
    $pdf->Cell(20, 6, count($stock_data), 0, 1, 'L');
}

// Output PDF
$filename = 'Stock_On_Hand_Serial_' . date('Y-m-d') . '.pdf';
$pdf->Output('I', $filename);
?>
