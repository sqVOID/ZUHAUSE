<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

if (!isset($_GET['st_number']) || empty($_GET['st_number'])) {
    die("Invalid Stock Transfer Number.");
}

$st_number = $_GET['st_number'];

// Fetch stock transfer header
$stmt = $conn->prepare("SELECT * FROM stock_transfers WHERE st_number = ?");
$stmt->bind_param("s", $st_number);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Stock Transfer record not found.");
}

$header = $res->fetch_assoc();
$stmt->close();

// Get full name for PREPARED BY
$prepared_by_fullname = $header['prepared_by'] ?? 'N/A';
if (!empty($header['prepared_by'])) {
    $stmt_prepared = $conn->prepare("SELECT first_name, last_name, system_level FROM accounts WHERE username = ?");
    if ($stmt_prepared) {
        $stmt_prepared->bind_param("s", $header['prepared_by']);
        $stmt_prepared->execute();
        $res_prepared = $stmt_prepared->get_result();
        if ($res_prepared && $res_prepared->num_rows > 0) {
            $prepared_data = $res_prepared->fetch_assoc();
            // Hide name if Super-Admin
            if ($prepared_data['system_level'] === 'Super-Admin') {
                $prepared_by_fullname = '';
            } elseif (!empty($prepared_data['first_name']) || !empty($prepared_data['last_name'])) {
                $prepared_by_fullname = trim($prepared_data['first_name'] . ' ' . $prepared_data['last_name']);
            }
        }
        $stmt_prepared->close();
    }
}

// Get full name for RECEIVED BY
$received_by_fullname = 'N/A';
if (isset($header['received_by']) && !empty($header['received_by'])) {
    $received_by_fullname = $header['received_by'];
    $stmt_received = $conn->prepare("SELECT first_name, last_name, system_level FROM accounts WHERE username = ?");
    if ($stmt_received) {
        $stmt_received->bind_param("s", $header['received_by']);
        $stmt_received->execute();
        $res_received = $stmt_received->get_result();
        if ($res_received && $res_received->num_rows > 0) {
            $received_data = $res_received->fetch_assoc();
            // Hide name if Super-Admin
            if ($received_data['system_level'] === 'Super-Admin') {
                $received_by_fullname = '';
            } elseif (!empty($received_data['first_name']) || !empty($received_data['last_name'])) {
                $received_by_fullname = trim($received_data['first_name'] . ' ' . $received_data['last_name']);
            }
        }
        $stmt_received->close();
    }
}

// Get full name for APPROVER
$approver_fullname = 'N/A';
if (isset($header['approver']) && !empty($header['approver'])) {
    $approver_fullname = $header['approver'];
    $stmt_approver = $conn->prepare("SELECT first_name, last_name, system_level FROM accounts WHERE username = ?");
    if ($stmt_approver) {
        $stmt_approver->bind_param("s", $header['approver']);
        $stmt_approver->execute();
        $res_approver = $stmt_approver->get_result();
        if ($res_approver && $res_approver->num_rows > 0) {
            $approver_data = $res_approver->fetch_assoc();
            // Hide name if Super-Admin
            if ($approver_data['system_level'] === 'Super-Admin') {
                $approver_fullname = '';
            } elseif (!empty($approver_data['first_name']) || !empty($approver_data['last_name'])) {
                $approver_fullname = trim($approver_data['first_name'] . ' ' . $approver_data['last_name']);
            }
        }
        $stmt_approver->close();
    }
}

// Fetch stock transfer items with pricing
$items = [];
$stmt_items = $conn->prepare("
    SELECT sti.*, 
           COALESCE(
               (SELECT price FROM item_prices 
                WHERE item_id = i.id 
                AND branch = ? 
                AND price_type = 'SRP' 
                LIMIT 1),
               i.srp,
               0
           ) as amount
    FROM stock_transfer_items sti
    LEFT JOIN items i ON sti.item_code = i.item_code
    WHERE sti.st_number = ?
    ORDER BY sti.id ASC
");
$stmt_items->bind_param("ss", $header['branch_to'], $st_number);
$stmt_items->execute();
$res_items = $stmt_items->get_result();
while ($row = $res_items->fetch_assoc()) {
    $items[] = $row;
}
$stmt_items->close();

// Calculate totals
$total_quantity = 0;
$total_amount = 0;
foreach ($items as $item) {
    $qty = intval($item['quantity']);
    $amount = floatval($item['amount']);
    $total_quantity += $qty;
    $total_amount += ($amount * $qty);
}

// Get branch names
$branch_from_name = $header['branch_from'];
$branch_to_name = $header['branch_to'];

// Try to get full branch names if they are codes
$branch_from_query = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = ?");
$branch_from_query->bind_param("s", $header['branch_from']);
$branch_from_query->execute();
$branch_from_result = $branch_from_query->get_result();
if ($branch_from_result && $branch_from_result->num_rows > 0) {
    $branch_from_data = $branch_from_result->fetch_assoc();
    $branch_from_name = $branch_from_data['branch_name'];
}
$branch_from_query->close();

$branch_to_query = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = ?");
$branch_to_query->bind_param("s", $header['branch_to']);
$branch_to_query->execute();
$branch_to_result = $branch_to_query->get_result();
if ($branch_to_result && $branch_to_result->num_rows > 0) {
    $branch_to_data = $branch_to_result->fetch_assoc();
    $branch_to_name = $branch_to_data['branch_name'];
}
$branch_to_query->close();

$created_date_txt = (!empty($header['created_at'])) ? date('F d, Y H:i:s', strtotime($header['created_at'])) : '';

// Create PDF with custom footer (exactly like other PDFs)
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

// Logo and Title on same line (exactly like print_transfer_approval_pdf.php)
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 10, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(20);
$pdf->Cell(0, 10, 'STOCK TRANSFER', 0, 1, 'C');
$pdf->Ln(5);  

// Meta information (exactly like print_transfer_approval_pdf.php format)
$pdf->SetFont('Courier', 'B', 9);
$pdf->SetTextColor(0, 0, 0);

// Left column and right column
$pdf->Cell(95, 6, 'ST NUMBER: ' . $header['st_number'], 0, 0, 'L');
$pdf->Cell(95, 6, 'DATE: ' . date('F d, Y', strtotime($header['st_date'])), 0, 1, 'R');

$pdf->Cell(95, 6, 'BRANCH FROM: ' . $branch_from_name, 0, 0, 'L');
$pdf->Cell(95, 6, 'BRANCH TO: ' . $branch_to_name, 0, 1, 'R');

$pdf->Cell(95, 6, 'PREPARED BY: ' . $prepared_by_fullname, 0, 0, 'L');
$pdf->Cell(95, 6, 'STATUS: ' . strtoupper($header['status']), 0, 1, 'R');

// Show APPROVER and APPROVAL DATE if available
if (!empty($header['approver'])) {
    $pdf->Cell(95, 6, 'APPROVER: ' . $approver_fullname, 0, 0, 'L');
    $pdf->Cell(95, 6, 'APPROVAL DATE: ' . (!empty($header['approval_date']) ? date('F d, Y H:i', strtotime($header['approval_date'])) : '-'), 0, 1, 'R');
}

// Show RECEIVED BY if available
if (!empty($header['received_by'])) {
    $pdf->Cell(95, 6, 'RECEIVED BY: ' . $received_by_fullname, 0, 0, 'L');
    // Show RECEIVED DATE if available
    if (!empty($header['received_date'])) {
        $pdf->Cell(95, 6, 'RECEIVED DATE: ' . date('F d, Y H:i', strtotime($header['received_date'])), 0, 1, 'R');
    } else {
        $pdf->Ln();
    }
}

if (!empty($header['remarks'])) {
    $pdf->Cell(95, 6, 'REMARKS: ' . $header['remarks'], 0, 1, 'L');
}

$pdf->Ln(5);

// Items Table Header (exactly like print_transfer_approval_pdf.php)
$pdf->SetFont('Courier', 'B', 7);
$pdf->SetFillColor(240, 240, 240); // Same gray background

$pdf->Cell(8, 8, 'NO', 1, 0, 'C', true);
$pdf->Cell(42, 8, 'ITEM CODE', 1, 0, 'C', true);
$pdf->Cell(52, 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'IMEI', 1, 0, 'C', true);
$pdf->Cell(13, 8, 'QTY', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'AMOUNT', 1, 1, 'C', true);

// Items Table Body (exactly like print_transfer_approval_pdf.php)
$pdf->SetFont('Courier', '', 8);

if (count($items) === 0) {
    $pdf->Cell(180, 8, 'NO ITEMS', 1, 1, 'C');
} else {
    $item_no = 1;
    foreach ($items as $item) {
        // Handle IMEI - split by various separators
        $imei = '';
        if (!empty($item['imei'])) {
            $imei = $item['imei'];
        }
        
        // Normalize separators to comma
        $imei_normalized = str_replace(["\r\n", "\n", "\r", "<br>", "<br/>", "<br />", "&"], ",", $imei);
        $imei_array = array_filter(array_map('trim', explode(',', $imei_normalized)));
        
        // Calculate row height based on number of IMEIs
        $row_height = max(6, count($imei_array) * 4);
        
        $x = 15; // Left margin
        $y = $pdf->GetY();
        
        // Check page break
        if ($y + $row_height > 270) {
            $pdf->AddPage();
            
            // Re-draw table header
            $pdf->SetFont('Courier', 'B', 7);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(8, 8, 'NO', 1, 0, 'C', true);
            $pdf->Cell(42, 8, 'ITEM CODE', 1, 0, 'C', true);
            $pdf->Cell(52, 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
            $pdf->Cell(40, 8, 'IMEI', 1, 0, 'C', true);
            $pdf->Cell(13, 8, 'QTY', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'AMOUNT', 1, 1, 'C', true);
            
            $pdf->SetFont('Courier', '', 8);
            $y = $pdf->GetY();
        }
        
        // Draw all cell borders
        $pdf->Rect($x, $y, 8, $row_height);
        $pdf->Rect($x + 8, $y, 42, $row_height);
        $pdf->Rect($x + 50, $y, 52, $row_height);
        $pdf->Rect($x + 102, $y, 40, $row_height);
        $pdf->Rect($x + 142, $y, 13, $row_height);
        $pdf->Rect($x + 155, $y, 25, $row_height);
        
        // Calculate vertical center for single-line cells
        $v_center = $y + ($row_height / 2) - 2;
        
        // Draw NO (centered)
        $pdf->SetXY($x, $v_center);
        $pdf->Cell(8, 4, $item_no, 0, 0, 'C');
        
        // Draw ITEM CODE (left aligned) - display full text without cutting
        $item_code = $item['item_code'];
        $item_code_width = $pdf->GetStringWidth($item_code);
        
        // Adjust font size if text is too wide for the cell (42mm width)
        $pdf->SetFont('Courier', '', 8);
        if ($item_code_width > 40) {
            // Calculate required font size to fit
            $scale_factor = 40 / $item_code_width;
            $new_font_size = 8 * $scale_factor;
            $pdf->SetFont('Courier', '', max(5, $new_font_size)); // Minimum 5pt font
        }
        
        $pdf->SetXY($x + 8, $v_center);
        $pdf->Cell(42, 4, $item_code, 0, 0, 'L');
        
        // Reset font for other cells
        $pdf->SetFont('Courier', '', 8);
        
        // Draw ITEM DESCRIPTION (left aligned) - display without exceeding border
        $item_description = $item['item_description'];
        $desc_width = $pdf->GetStringWidth($item_description);
        
        // Adjust font size if text is too wide for the cell (52mm width)
        $pdf->SetFont('Courier', '', 8);
        if ($desc_width > 50) {
            // Calculate required font size to fit
            $scale_factor = 50 / $desc_width;
            $new_font_size = 8 * $scale_factor;
            $pdf->SetFont('Courier', '', max(5, $new_font_size)); // Minimum 5pt font
        }
        
        $pdf->SetXY($x + 50, $v_center);
        $pdf->Cell(52, 4, $item_description, 0, 0, 'L');
        
        // Reset font back to 8pt
        $pdf->SetFont('Courier', '', 8);
        
        // Draw IMEI with line breaks (centered in cell)
        if (count($imei_array) > 0) {
            $imei_start_y = $y + (($row_height - (count($imei_array) * 4)) / 2);
            $imei_y = $imei_start_y;
            foreach ($imei_array as $serial) {
                $pdf->SetXY($x + 102, $imei_y);
                // Check if serial length is exactly 15 characters
                $serial_length = strlen($serial);
                if ($serial_length != 15) {
                    // Set text color to red if not exactly 15 characters
                    $pdf->SetTextColor(255, 0, 0);
                } else {
                    // Set text color to black if exactly 15 characters
                    $pdf->SetTextColor(0, 0, 0);
                }
                $pdf->Cell(40, 4, $serial, 0, 0, 'C');
                $imei_y += 4;
            }
            // Reset text color to black for subsequent cells
            $pdf->SetTextColor(0, 0, 0);
        } else {
            // No IMEI
            $pdf->SetXY($x + 102, $v_center);
            $pdf->Cell(40, 4, '-', 0, 0, 'C');
        }
        
        // Draw QTY (centered)
        $pdf->SetXY($x + 142, $v_center);
        $pdf->Cell(13, 4, $item['quantity'], 0, 0, 'C');
        
        // Draw AMOUNT (right aligned)
        $pdf->SetXY($x + 155, $v_center);
        $pdf->Cell(25, 4, number_format($item['amount'], 2), 0, 0, 'R');
        
        // Move to next row
        $pdf->SetXY($x, $y + $row_height);
        
        $item_no++;
    }
}

$pdf->Ln(5);

// Summary section (exactly like print_transfer_approval_pdf.php)
$pdf->SetFont('Courier', 'B', 10);
$pdf->SetTextColor(211, 47, 47); // Red color

$pdf->Cell(50, 6, 'TOTAL QUANTITY:', 0, 0, 'L');
$pdf->Cell(30, 6, $total_quantity, 0, 1, 'L');

$pdf->Cell(50, 6, 'TOTAL AMOUNT:', 0, 0, 'L');
$pdf->Cell(30, 6, number_format($total_amount, 2), 0, 1, 'L');

// Output PDF
$pdf->Output('I', 'ST_' . $st_number . '.pdf');
?>