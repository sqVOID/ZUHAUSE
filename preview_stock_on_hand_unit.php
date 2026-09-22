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

// Super-Admin sees all stock, others see only their branch(es)
if ($is_superadmin || ($session_branch !== '' && strtoupper($session_branch) !== 'SUPERADMIN')) {
    // Filter for IMEI items to count as units
    $conds = ["item_type = 'IMEI'"];
    
    // Apply branch filter only if not Super-Admin
    if (!$is_superadmin && $branch_in !== '') {
        $conds[] = "branch IN ($branch_in)";
    }
    
    if ($branch_f !== '')
        $conds[] = "branch = '" . addslashes($branch_f) . "'";
    if ($model_f !== '')
        $conds[] = "item_code LIKE '%" . addslashes($model_f) . "%'";
    if ($brand_f !== '')
        $conds[] = "brand = '" . addslashes($brand_f) . "'";
    if ($family_f !== '')
        $conds[] = "family_code = '" . addslashes($family_f) . "'";
    $where = implode(" AND ", $conds);

    $sql = "SELECT item_code, description, COUNT(*) AS total_qty, branch, family_code
            FROM stock_on_hand WHERE $where
            GROUP BY item_code, description, branch, family_code ORDER BY item_code, branch";
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

$pdf = new PDF('L', 'mm', 'A4'); // Landscape
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);

// Logo and Title
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 10, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(23);
$pdf->Cell(0, 10, 'STOCK ON HAND - UNIT', 0, 1, 'C');
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

$pdf->Cell(60, 8, 'MODEL CODE', 1, 0, 'C', true);
$pdf->Cell(80, 8, 'DESCRIPTION', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'QTY', 1, 0, 'C', true);
$pdf->Cell(50, 8, 'BRANCH', 1, 0, 'C', true);
$pdf->Cell(67, 8, 'FAMILY', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Courier', '', 6);

if (count($stock_data) === 0) {
    $pdf->Cell(277, 8, 'No Stock Available', 1, 1, 'C');
} else {
    $total_qty = 0;
    foreach ($stock_data as $row) {
        $qty = intval($row['total_qty']);
        $total_qty += $qty;
        
        // Check if we need a new page
        if ($pdf->GetY() > 180) {
            $pdf->AddPage();
            
            // Repeat header on new page
            $pdf->SetFont('Courier', 'B', 7);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(60, 8, 'MODEL CODE', 1, 0, 'C', true);
            $pdf->Cell(80, 8, 'DESCRIPTION', 1, 0, 'C', true);
            $pdf->Cell(20, 8, 'QTY', 1, 0, 'C', true);
            $pdf->Cell(50, 8, 'BRANCH', 1, 0, 'C', true);
            $pdf->Cell(67, 8, 'FAMILY', 1, 1, 'C', true);
            $pdf->SetFont('Courier', '', 6);
        }
        
        // Handle text wrapping for long model codes and descriptions
        $model_code = $row['item_code'];
        $description = $row['description'];
        $branch = $row['branch'];
        $family = $row['family_code'] ?? '—';
        
        // Wrap text for model code (approx 35 chars per line for 60mm width)
        $model_lines = explode("\n", wordwrap($model_code, 35, "\n", true));
        $desc_lines = explode("\n", wordwrap($description, 50, "\n", true));
        
        // Calculate row height based on max lines needed
        $row_height = max(6, count($model_lines) * 3, count($desc_lines) * 3);
        
        $x = 10; // Left margin
        $y = $pdf->GetY();
        
        // Draw all cell borders
        $pdf->Rect($x, $y, 60, $row_height);
        $pdf->Rect($x + 60, $y, 80, $row_height);
        $pdf->Rect($x + 140, $y, 20, $row_height);
        $pdf->Rect($x + 160, $y, 50, $row_height);
        $pdf->Rect($x + 210, $y, 67, $row_height);
        
        // Calculate vertical center for single-line cells
        $v_center = $y + ($row_height / 2) - 1.5;
        
        // Draw MODEL CODE with line breaks (left aligned in cell)
        $model_start_y = $y + (($row_height - (count($model_lines) * 3)) / 2);
        $model_y = $model_start_y;
        foreach ($model_lines as $line) {
            $pdf->SetXY($x, $model_y);
            $pdf->Cell(60, 3, $line, 0, 0, 'L');
            $model_y += 3;
        }
        
        // Draw DESCRIPTION with line breaks (left aligned in cell)
        $desc_start_y = $y + (($row_height - (count($desc_lines) * 3)) / 2);
        $desc_y = $desc_start_y;
        foreach ($desc_lines as $line) {
            $pdf->SetXY($x + 60, $desc_y);
            $pdf->Cell(80, 3, $line, 0, 0, 'L');
            $desc_y += 3;
        }
        
        // Draw QTY (centered)
        $pdf->SetXY($x + 140, $v_center);
        $pdf->Cell(20, 3, $qty, 0, 0, 'C');
        
        // Draw BRANCH (centered)
        $pdf->SetXY($x + 160, $v_center);
        $pdf->Cell(50, 3, $branch, 0, 0, 'C');
        
        // Draw FAMILY (left aligned)
        $pdf->SetXY($x + 210, $v_center);
        $pdf->Cell(67, 3, $family, 0, 0, 'L');
        
        // Move to next row
        $pdf->SetXY($x, $y + $row_height);
    }
    
    $pdf->Ln(3);
    
    // Summary
    $pdf->SetFont('Courier', 'B', 10);
    $pdf->SetTextColor(211, 47, 47);
    
    $pdf->Cell(50, 6, 'TOTAL QUANTITY:', 0, 0, 'L');
    $pdf->Cell(20, 6, $total_qty, 0, 1, 'L');
}

// Output PDF
$filename = 'Stock_On_Hand_Unit_' . date('Y-m-d') . '.pdf';
$pdf->Output('I', $filename);
?>
