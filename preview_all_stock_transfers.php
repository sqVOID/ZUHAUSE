<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

// Get date parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : $date_from;
$branch_from_filter = isset($_GET['branch_from']) ? $_GET['branch_from'] : '';
$branch_to_filter = isset($_GET['branch_to']) ? $_GET['branch_to'] : '';

// Get user info
$branch_code = '000';
if (isset($_SESSION['user_branch'])) {
    $user_branch = $_SESSION['user_branch'];
    $branch_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name = '$user_branch'");
    if ($branch_query && $branch_query->num_rows > 0) {
        $branch_data = $branch_query->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
}

// Determine if the user is an admin who can see all branches
$system_level = isset($_SESSION['system_level']) ? $_SESSION['system_level'] : '';
$is_admin = ($system_level === 'Super-Admin' || $system_level === 'Sub-admin');

// Build query to fetch stock transfer data
$where_conditions = ["1=1"];

// Add date range filter
if ($date_from === $date_to) {
    $where_conditions[] = "st.st_date = '$date_from'";
} else {
    $where_conditions[] = "st.st_date >= '$date_from' AND st.st_date <= '$date_to'";
}

// Add branch filter if specified
if (!$is_admin) {
    $user_branch_escaped = $conn->real_escape_string($user_branch);
    $branch_code_escaped = $conn->real_escape_string($branch_code);
    $where_conditions[] = "(st.branch_from = '$branch_code_escaped' OR st.branch_to = '$user_branch_escaped' OR st.branch_to = '$branch_code_escaped' OR bf.branch_name = '$user_branch_escaped')";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Fetch data
$report_query = "SELECT st.st_number, st.st_date, st.prepared_by, st.remarks, st.status,
                        st.branch_from, st.branch_to,
                        bf.branch_name as from_branch_name,
                        bt.branch_code as to_branch_code,
                        sti.item_code, sti.item_description, sti.quantity, sti.imei,
                        COALESCE(
                            (SELECT price FROM item_prices 
                             WHERE item_id = i.id 
                             AND branch = st.branch_to 
                             AND price_type = 'SRP' 
                             LIMIT 1),
                            i.srp,
                            0
                        ) as amount
                 FROM stock_transfers st
                 LEFT JOIN branches bf ON bf.branch_code = st.branch_from
                 LEFT JOIN branches bt ON bt.branch_name = st.branch_to
                 LEFT JOIN stock_transfer_items sti ON st.st_number = sti.st_number
                 LEFT JOIN items i ON sti.item_code = i.item_code
                 $where_clause
                 ORDER BY st.st_date DESC, st.st_number DESC, sti.id ASC";

$result = $conn->query($report_query);
$stock_transfers = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Apply branch filters in PHP 
        $from_code = $row['branch_from'];
        $to_code = $row['to_branch_code'];
        
        $match = true;
        
        if ($is_admin) {
            if ($branch_from_filter && $from_code !== $branch_from_filter) {
                $match = false;
            }
            if ($branch_to_filter && $to_code !== $branch_to_filter) {
                $match = false;
            }
        }
        
        if ($match) {
            $stock_transfers[] = $row;
        }
    }
}

// Create PDF
class PDF extends FPDF
{
    function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        $this->SetTextColor(0, 0, 0); // Black color
        $this->SetFont('Courier', 'B', 10);
        $this->Cell(0, 10, 'PAGE ' . $this->PageNo() . ' OF {nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape since we have many columns
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);

// Logo and Title on same line
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 15, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(25);
$pdf->Cell(0, 10, 'STOCK TRANSFER REPORT', 0, 1, 'C');
$pdf->Ln(5);

// Report information
$pdf->SetFont('Courier', 'B', 10);
if ($date_from === $date_to) {
    $pdf->Cell(135, 6, 'REPORT DATE: ' . date('F d, Y', strtotime($date_from)), 0, 1, 'L');
} else {
    $pdf->Cell(135, 6, 'DATE RANGE: ' . date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to)), 0, 1, 'L');
}

// Ensure proper branch name displays in the header
if (!$is_admin && isset($user_branch)) {
    $pdf->Cell(135, 6, 'BRANCH: ' . $user_branch . ' - ' . $branch_code, 0, 1, 'L');
} else {
    $from_name = $branch_from_filter;
    $to_name = $branch_to_filter;
    
    if ($branch_from_filter) {
        $q = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '$branch_from_filter'");
        if ($q && $q->num_rows > 0) $from_name = $q->fetch_assoc()['branch_name'] . ' - ' . $branch_from_filter;
    }
    if ($branch_to_filter) {
        $q = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '$branch_to_filter'");
        if ($q && $q->num_rows > 0) $to_name = $q->fetch_assoc()['branch_name'] . ' - ' . $branch_to_filter;
    }
    
    $pdf->Cell(135, 6, 'FROM BRANCH: ' . ($from_name ?: 'ALL BRANCHES'), 0, 1, 'L');
    $pdf->Cell(135, 6, 'TO BRANCH: ' . ($to_name ?: 'ALL BRANCHES'), 0, 1, 'L');
}

$pdf->Ln(5);

// Calculate dynamic column widths based on available space
$available_width = 267; // A4 Landscape width minus margins (297 - 30)

$col_widths = [
    'date' => 20,
    'st_no' => 28,
    'branch_from' => 30,
    'branch_to' => 30,
    'item_desc' => 52,
    'imei' => 40,
    'qty' => 12,
    'srp' => 25,
    'total' => 30
];

// Table Header
$pdf->SetFont('Courier', 'B', 8);
$pdf->SetFillColor(240, 240, 240);

$pdf->Cell($col_widths['date'], 8, 'ST DATE', 1, 0, 'C', true);
$pdf->Cell($col_widths['st_no'], 8, 'ST NO.', 1, 0, 'C', true);
$pdf->Cell($col_widths['branch_from'], 8, 'BRANCH FROM', 1, 0, 'C', true);
$pdf->Cell($col_widths['branch_to'], 8, 'BRANCH TO', 1, 0, 'C', true);
$pdf->Cell($col_widths['item_desc'], 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
$pdf->Cell($col_widths['imei'], 8, 'IMEI', 1, 0, 'C', true);
$pdf->Cell($col_widths['qty'], 8, 'QTY', 1, 0, 'C', true);
$pdf->Cell($col_widths['srp'], 8, 'SRP', 1, 0, 'C', true);
$pdf->Cell($col_widths['total'], 8, 'TOTAL AMOUNT', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Courier', '', 7);

$grand_total_qty = 0;
$grand_total_amount = 0;

if (count($stock_transfers) === 0) {
    if ($date_from === $date_to) {
        $pdf->Cell($available_width, 8, 'No stock transfer records found for ' . date('F d, Y', strtotime($date_from)), 1, 1, 'C');
    } else {
        $pdf->Cell($available_width, 8, 'No stock transfer records found for ' . date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to)), 1, 1, 'C');
    }
} else {
    foreach ($stock_transfers as $row) {
        $s_date = !empty($row['st_date']) ? date('m/d/Y', strtotime($row['st_date'])) : '';
        $s_no = $row['st_number'];
        
        $branch_from_display = ($row['branch_from'] . ' - ' . ($row['from_branch_name'] ?? ''));
        $branch_to_display = (($row['to_branch_code'] ?? '') . ' - ' . $row['branch_to']);
        
        $item_desc = $row['item_description'] ?: '-';
        $imei = $row['imei'] ?: '-';
        $qty = intval($row['quantity'] ?: 0);
        $srp = floatval($row['amount'] ?: 0);
        $total_amt = $srp * $qty;
        
        $grand_total_qty += $qty;
        $grand_total_amount += $total_amt;

        // Handle text wrapping
        $chars_per_mm = 0.55; 
        
        $from_chars = floor($col_widths['branch_from'] * $chars_per_mm);
        $to_chars = floor($col_widths['branch_to'] * $chars_per_mm);
        $desc_chars = floor($col_widths['item_desc'] * $chars_per_mm);
        
        $from_lines = explode("\n", wordwrap($branch_from_display, $from_chars, "\n", true));
        $to_lines = explode("\n", wordwrap($branch_to_display, $to_chars, "\n", true));
        $desc_lines = explode("\n", wordwrap($item_desc, $desc_chars, "\n", true));
        
        $row_height = max(8, count($from_lines) * 4, count($to_lines) * 4, count($desc_lines) * 4);
        
        $x = 15; 
        $y = $pdf->GetY();
        
        // Check if we need a new page
        if ($y + $row_height > 185) { // Landscape A4 max height is ~210, save 25 for footer
            $pdf->AddPage();
            
            $pdf->SetFont('Courier', 'B', 8);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell($col_widths['date'], 8, 'ST DATE', 1, 0, 'C', true);
            $pdf->Cell($col_widths['st_no'], 8, 'ST NO.', 1, 0, 'C', true);
            $pdf->Cell($col_widths['branch_from'], 8, 'BRANCH FROM', 1, 0, 'C', true);
            $pdf->Cell($col_widths['branch_to'], 8, 'BRANCH TO', 1, 0, 'C', true);
            $pdf->Cell($col_widths['item_desc'], 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
            $pdf->Cell($col_widths['imei'], 8, 'IMEI', 1, 0, 'C', true);
            $pdf->Cell($col_widths['qty'], 8, 'QTY', 1, 0, 'C', true);
            $pdf->Cell($col_widths['srp'], 8, 'SRP', 1, 0, 'C', true);
            $pdf->Cell($col_widths['total'], 8, 'TOTAL AMOUNT', 1, 1, 'C', true);
            
            $pdf->SetFont('Courier', '', 7);
            $y = $pdf->GetY();
        }
        
        // Draw borders
        $current_x = $x;
        $pdf->Rect($current_x, $y, $col_widths['date'], $row_height);
        $current_x += $col_widths['date'];
        
        $pdf->Rect($current_x, $y, $col_widths['st_no'], $row_height);
        $current_x += $col_widths['st_no'];
        
        $pdf->Rect($current_x, $y, $col_widths['branch_from'], $row_height);
        $current_x += $col_widths['branch_from'];
        
        $pdf->Rect($current_x, $y, $col_widths['branch_to'], $row_height);
        $current_x += $col_widths['branch_to'];
        
        $pdf->Rect($current_x, $y, $col_widths['item_desc'], $row_height);
        $current_x += $col_widths['item_desc'];
        
        $pdf->Rect($current_x, $y, $col_widths['imei'], $row_height);
        $current_x += $col_widths['imei'];
        
        $pdf->Rect($current_x, $y, $col_widths['qty'], $row_height);
        $current_x += $col_widths['qty'];

        $pdf->Rect($current_x, $y, $col_widths['srp'], $row_height);
        $current_x += $col_widths['srp'];
        
        $pdf->Rect($current_x, $y, $col_widths['total'], $row_height);
        
        $v_center = $y + ($row_height / 2) - 2;
        $current_x = $x;
        
        // ST DATE
        $pdf->SetXY($current_x, $v_center);
        $pdf->Cell($col_widths['date'], 4, $s_date, 0, 0, 'C');
        $current_x += $col_widths['date'];
        
        // ST NO
        $pdf->SetXY($current_x, $v_center);
        $pdf->Cell($col_widths['st_no'], 4, $s_no, 0, 0, 'C');
        $current_x += $col_widths['st_no'];
        
        // BRANCH FROM (Multiline)
        $start_y = $y + (($row_height - (count($from_lines) * 4)) / 2);
        $curr_y = $start_y;
        foreach ($from_lines as $line) {
            $pdf->SetXY($current_x, $curr_y);
            $pdf->Cell($col_widths['branch_from'], 4, $line, 0, 0, 'C');
            $curr_y += 4;
        }
        $current_x += $col_widths['branch_from'];
        
        // BRANCH TO (Multiline)
        $start_y = $y + (($row_height - (count($to_lines) * 4)) / 2);
        $curr_y = $start_y;
        foreach ($to_lines as $line) {
            $pdf->SetXY($current_x, $curr_y);
            $pdf->Cell($col_widths['branch_to'], 4, $line, 0, 0, 'C');
            $curr_y += 4;
        }
        $current_x += $col_widths['branch_to'];
        
        // ITEM DESC
        $start_y = $y + (($row_height - (count($desc_lines) * 4)) / 2);
        $curr_y = $start_y;
        foreach ($desc_lines as $line) {
            $pdf->SetXY($current_x, $curr_y);
            $pdf->Cell($col_widths['item_desc'], 4, $line, 0, 0, 'C');
            $curr_y += 4;
        }
        $current_x += $col_widths['item_desc'];
        
        // IMEI
        $pdf->SetXY($current_x, $v_center);
        if ($imei !== '-') {
            $imei_length = strlen($imei);
            if ($imei_length != 15) {
                $pdf->SetTextColor(255, 0, 0);
            }
        }
        $pdf->Cell($col_widths['imei'], 4, $imei, 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0); // reset
        $current_x += $col_widths['imei'];
        
        // QTY
        $pdf->SetXY($current_x, $v_center);
        $pdf->Cell($col_widths['qty'], 4, $qty, 0, 0, 'C');
        $current_x += $col_widths['qty'];

        // SRP
        $pdf->SetXY($current_x, $v_center);
        $pdf->Cell($col_widths['srp'], 4, number_format($srp, 2), 0, 0, 'C');
        $current_x += $col_widths['srp'];

        // TOTAL
        $pdf->SetXY($current_x, $v_center);
        $pdf->Cell($col_widths['total'], 4, number_format($total_amt, 2), 0, 0, 'C');
        
        $pdf->SetY($y + $row_height);
    }
}

$pdf->Ln(5);
$pdf->SetFont('Courier', 'B', 9);
$pdf->SetTextColor(255, 0, 0); // Red color
$pdf->Cell(267, 6, 'TOTAL QUANTITY: ' . $grand_total_qty, 0, 1, 'L');
$pdf->Cell(267, 6, 'GRAND TOTAL: ' . number_format($grand_total_amount, 2), 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);

// Output PDF
if ($date_from === $date_to) {
    $filename = 'All_Stock_Transfers_Report_' . date('Y-m-d', strtotime($date_from)) . '.pdf';
} else {
    $filename = 'All_Stock_Transfers_Report_' . date('Y-m-d', strtotime($date_from)) . '_to_' . date('Y-m-d', strtotime($date_to)) . '.pdf';
}
$pdf->Output('I', $filename);
?>
