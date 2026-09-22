<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

// Get date parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : $date_from;
$selected_branch = isset($_GET['branch']) ? $_GET['branch'] : '';

// For backward compatibility with single date parameter
if (isset($_GET['date']) && !isset($_GET['date_from'])) {
    $date_from = $_GET['date'];
    $date_to = $_GET['date'];
}

// Get branch information
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

// Build query to fetch RD delivery data
$where_conditions = ["UPPER(r.status) = 'RECEIVED'"];

// Add date range filter
if ($date_from === $date_to) {
    $where_conditions[] = "DATE(r.received_at) = '$date_from'";
} else {
    $where_conditions[] = "DATE(r.received_at) >= '$date_from' AND DATE(r.received_at) <= '$date_to'";
}

// Add branch filter if specified
if ($selected_branch) {
    $where_conditions[] = "r.created_by_branch = '$selected_branch'";
} else if (!$is_admin) {
    $where_conditions[] = "r.created_by_branch = '$branch_code'";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Fetch RD delivery data
$report_query = "SELECT r.id, r.received_at as `date`, r.po_number, r.invoice_number, r.supplier_company as supplier, 
                        r.created_by_branch as branch_code, r.po_date as invoice_date, r.remarks as notes, b.branch_name 
                 FROM purchase_orders r 
                 LEFT JOIN branches b ON r.created_by_branch = b.branch_code 
                 $where_clause
                 ORDER BY r.received_at DESC, r.id DESC";

$result = $conn->query($report_query);
$rd_deliveries = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Fetch items for each delivery
        $items = [];
        $stmt_items = $conn->prepare("
            SELECT 
                poi.quantity as received_quantity, 
                poi.cost as actual_cost, 
                poi.serial_number as serials, 
                poi.item_model as item_code, 
                poi.item_description 
            FROM purchase_order_items poi 
            WHERE poi.po_id = ?
            ORDER BY poi.item_no ASC
        ");
        $stmt_items->bind_param("i", $row['id']);
        $stmt_items->execute();
        $res_items = $stmt_items->get_result();
        while ($item_row = $res_items->fetch_assoc()) {
            $items[] = $item_row;
        }
        $stmt_items->close();
        
        $row['items'] = $items;
        $rd_deliveries[] = $row;
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

$pdf = new PDF('P', 'mm', 'A4');
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
$pdf->Cell(0, 6, 'RECEIVE DIRECT DELIVERY', 0, 1, 'C');
$pdf->Cell(0, 6, 'REPORT', 0, 1, 'C');
$pdf->Ln(3);

// Report information - Date Range and Branch on same line
$pdf->SetFont('Courier', 'B', 10);

// First line: Date Range and Branch
if ($date_from === $date_to) {
    $pdf->Cell(95, 6, 'REPORT DATE: ' . date('F d, Y', strtotime($date_from)), 0, 0, 'L');
} else {
    $pdf->Cell(95, 6, 'DATE: ' . date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to)), 0, 0, 'L');
}

if (!$is_admin && isset($user_branch)) {
    $pdf->Cell(95, 6, 'BRANCH: ' . $user_branch . ' - ' . $branch_code, 0, 1, 'R');
} else if ($selected_branch) {
    // Get branch name for selected branch
    $branch_name_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '$selected_branch'");
    $selected_branch_name = 'Unknown Branch';
    if ($branch_name_query && $branch_name_query->num_rows > 0) {
        $branch_name_data = $branch_name_query->fetch_assoc();
        $selected_branch_name = $branch_name_data['branch_name'];
    }
    $pdf->Cell(95, 6, 'BRANCH: ' . $selected_branch_name . ' - ' . $selected_branch, 0, 1, 'R');
} else {
    $pdf->Cell(95, 6, 'BRANCH: ALL BRANCHES', 0, 1, 'R');
}

if (count($rd_deliveries) === 0) {
    $pdf->SetFont('Courier', '', 12);
    
    // Build message based on filters
    $message = 'No RD delivery records found';
    
    // Add date range to message
    //if ($date_from === $date_to) {
    //    $message .= ' for ' . date('F d, Y', strtotime($date_from));
    //} else {
    //    $message .= ' from ' . date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to));
    // }
    
    // Add branch to message if filtered
    if ($selected_branch) {
        $branch_name_query = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '$selected_branch'");
        if ($branch_name_query && $branch_name_query->num_rows > 0) {
            $branch_name_data = $branch_name_query->fetch_assoc();
            $message .= ' for branch ' . $branch_name_data['branch_name'] . ' - ' . $selected_branch;
        }
    }
    
    $pdf->Cell(0, 10, $message, 0, 1, 'C');
} else {
    $delivery_count = 0;
    $grand_total_qty = 0;
    $grand_total_amount = 0;
    
    foreach ($rd_deliveries as $delivery) {
        $delivery_count++;
        
        // Add page break if needed (except for first delivery)
        if ($delivery_count > 1) {
            $pdf->AddPage();
        }
        
        $branch_text = ($delivery['branch_name'] ? $delivery['branch_name'] : 'Unknown') . ' - ' . ($delivery['branch_code'] ? $delivery['branch_code'] : 'UNK');
        $invoice_date_txt = (!empty($delivery['invoice_date']) && $delivery['invoice_date'] != '1970-01-01') ? date('F d, Y', strtotime($delivery['invoice_date'])) : '';
        $received_date_txt = (!empty($delivery['date'])) ? date('F d, Y H:i:s', strtotime($delivery['date'])) : '';

        // Delivery Header

// Meta information
$pdf->SetFont('Courier', 'B', 9);

$pdf->Cell(95, 6, 'PO NUMBER: ' . $delivery['po_number'], 0, 0, 'L');
$pdf->Cell(95, 6, 'BRANCH RECEIVED: ' . $branch_text, 0, 1, 'R');

$pdf->Cell(95, 6, 'INVOICE NO: ' . ($delivery['invoice_number'] ?? '-'), 0, 0, 'L');
$pdf->Cell(95, 6, 'SUPPLIER: ' . $delivery['supplier'], 0, 1, 'R');

$pdf->Cell(95, 6, 'PO DATE: ' . $invoice_date_txt, 0, 0, 'L');
$pdf->Cell(95, 6, 'RECEIVED DATE: ' . $received_date_txt, 0, 1, 'R');

$pdf->Cell(95, 6, 'NOTE: ' . (isset($delivery['notes']) ? $delivery['notes'] : ''), 0, 1, 'L');

        $pdf->Ln(5);

        // Table Header
        $pdf->SetFont('Courier', 'B', 8);
        $pdf->SetFillColor(240, 240, 240);

        $pdf->Cell(12, 8, 'QTY', 1, 0, 'C', true);
        $pdf->Cell(42, 8, 'MODEL CODE', 1, 0, 'C', true);
        $pdf->Cell(56, 8, 'ITEM DESCRIPTION', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'IMEI', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'AMOUNT', 1, 0, 'C', true);

        // Multi-line header for TOTAL AMOUNT
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell(20, 4, "TOTAL\nAMOUNT", 1, 'C', true);
        $pdf->SetXY($x + 20, $y + 8);

        // Table Body
        $pdf->SetFont('Courier', '', 7);
        
        $delivery_total_qty = 0;
        $delivery_total_amount = 0;

        if (count($delivery['items']) === 0) {
            $pdf->Cell(190, 8, 'No Items', 1, 1, 'C');
        } else {
            foreach ($delivery['items'] as $item) {
                $rcv_qty = floatval($item['received_quantity']);
                $amt = floatval($item['actual_cost']);
                $tot_amt = $amt * $rcv_qty;
                
                $delivery_total_qty += $rcv_qty;
                $delivery_total_amount += $tot_amt;
                
                // Handle IMEI - normalize separators to comma and split
                $imei = $item['serials'] ? $item['serials'] : '';
                $imei_normalized = str_replace(["\r\n", "\n", "\r", "<br>", "<br/>", "<br />", "&"], ",", $imei);
                $imei_array = array_filter(array_map('trim', explode(',', $imei_normalized)));
                
                // Handle wrapping for model code and item description
                $model_lines = explode("\n", wordwrap($item['item_code'], 25, "\n", true));
                $desc_lines = explode("\n", wordwrap($item['item_description'], 35, "\n", true));
                
                // Calculate row height based on max lines needed
                $row_height = max(8, count($imei_array) * 4, count($model_lines) * 4, count($desc_lines) * 4);
                
                $x = 15; // Left margin
                $y = $pdf->GetY();
                
                // Draw all cell borders
                $pdf->Rect($x, $y, 12, $row_height);
                $pdf->Rect($x + 12, $y, 42, $row_height);
                $pdf->Rect($x + 54, $y, 56, $row_height);
                $pdf->Rect($x + 110, $y, 40, $row_height);
                $pdf->Rect($x + 150, $y, 20, $row_height);
                $pdf->Rect($x + 170, $y, 20, $row_height);
                
                // Calculate vertical center for single-line cells
                $v_center = $y + ($row_height / 2) - 2;
                
                // Draw QTY (centered)
                $pdf->SetXY($x, $v_center);
                $pdf->Cell(12, 4, $rcv_qty, 0, 0, 'C');
                
                // Draw MODEL CODE with line breaks (centered in cell)
                $mod_start_y = $y + (($row_height - (count($model_lines) * 4)) / 2);
                $mod_y = $mod_start_y;
                foreach ($model_lines as $line) {
                    $pdf->SetXY($x + 12, $mod_y);
                    $pdf->Cell(42, 4, $line, 0, 0, 'C');
                    $mod_y += 4;
                }
                
                // Draw ITEM DESCRIPTION with line breaks (centered in cell)
                $desc_start_y = $y + (($row_height - (count($desc_lines) * 4)) / 2);
                $desc_y = $desc_start_y;
                foreach ($desc_lines as $line) {
                    $pdf->SetXY($x + 54, $desc_y);
                    $pdf->Cell(56, 4, $line, 0, 0, 'C');
                    $desc_y += 4;
                }
                
                // Draw IMEI with line breaks (centered in cell)
                $imei_start_y = $y + (($row_height - (count($imei_array) * 4)) / 2);
                $imei_y = $imei_start_y;
                foreach ($imei_array as $serial) {
                    $pdf->SetXY($x + 110, $imei_y);
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
                
                // Draw AMOUNT (centered)
                $pdf->SetXY($x + 150, $v_center);
                $pdf->Cell(20, 4, number_format($amt, 2), 0, 0, 'C');
                
                // Draw TOTAL AMOUNT (centered)
                $pdf->SetXY($x + 170, $v_center);
                $pdf->Cell(20, 4, number_format($tot_amt, 2), 0, 0, 'C');
                
                // Move to next row
                $pdf->SetXY($x, $y + $row_height);
            }
        }

        $pdf->Ln(5);

        // Delivery Summary
        $pdf->SetFont('Courier', 'B', 10);
        $pdf->SetTextColor(211, 47, 47);

        $pdf->Cell(50, 6, 'DELIVERY TOTAL QTY:', 0, 0, 'L');
        $pdf->Cell(20, 6, $delivery_total_qty, 0, 1, 'L');

        $pdf->Cell(50, 6, 'DELIVERY TOTAL AMOUNT:', 0, 0, 'L');
        $pdf->Cell(20, 6, number_format($delivery_total_amount, 2), 0, 1, 'L');

        $pdf->SetTextColor(0, 0, 0); // Reset to black
        
        $grand_total_qty += $delivery_total_qty;
        $grand_total_amount += $delivery_total_amount;
        
        $pdf->Ln(10);
    }
    
    // Grand Total Summary (on last page)
    $pdf->SetFont('Courier', 'B', 12);
    $pdf->SetTextColor(0, 100, 0); // Green color for grand totals
}

// Output PDF
if ($date_from === $date_to) {
    $filename = 'RD_Deliveries_Report_' . date('Y-m-d', strtotime($date_from)) . '.pdf';
} else {
    $filename = 'RD_Deliveries_Report_' . date('Y-m-d', strtotime($date_from)) . '_to_' . date('Y-m-d', strtotime($date_to)) . '.pdf';
}
$pdf->Output('I', $filename);
?>