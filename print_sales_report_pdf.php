<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$branch = isset($_GET['branch']) ? trim($_GET['branch']) : '';

if ($date_from === '' || $date_to === '' || $branch === '') {
    die("Missing required parameters: date_from, date_to, and branch");
}

$branch_code = '';
$stmt_branch = $conn->prepare("SELECT branch_code FROM branches WHERE branch_name = ? LIMIT 1");
$stmt_branch->bind_param("s", $branch);
$stmt_branch->execute();
$res_branch = $stmt_branch->get_result();
if ($res_branch && $res_branch->num_rows > 0) {
    $branch_row = $res_branch->fetch_assoc();
    $branch_code = $branch_row['branch_code'];
}
$stmt_branch->close();

if ($branch_code === '') {
    die("Branch not found: " . htmlspecialchars($branch));
}

$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS voucher_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS token_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");

$sql = "
    SELECT
        se.invoice_no AS invoice_no,
        se.assisted_by AS representative,
        COALESCE(sei.quantity, 0) AS quantity,
        COALESCE(sei.price, 0) AS item_price,
        COALESCE(se.discount, 0) AS invoice_discount,
        COALESCE(se.voucher_amount, 0) AS invoice_voucher,
        COALESCE(se.token, 0) AS invoice_token,
        se.total_amount AS invoice_total,
        (COALESCE(sei.quantity, 0) * COALESCE(sei.price, 0)) AS item_subtotal,
        COALESCE(
            NULLIF(sei.voucher_amount, 0),
            CASE WHEN i.has_voucher = 1 THEN COALESCE(i.voucher_amount, 0) ELSE 0 END,
            0
        ) AS item_voucher,
        COALESCE(
            NULLIF(sei.token_amount, 0),
            CASE WHEN i.has_token = 1 THEN COALESCE(i.token_amount, 0) ELSE 0 END,
            0
        ) AS item_token,
        COALESCE(se.commission, 0.00) AS commission,
        COALESCE(i.family_code, '') AS family_code,
        COALESCE(sei.imei, '') AS imei,
        COALESCE(i.brand, '') AS brand,
        COALESCE(i.group_name, '') AS group_name,
        COALESCE(
            po.supplier_company,
            (
                SELECT po2.supplier_company
                FROM purchase_order_items poi2
                JOIN purchase_orders po2 ON po2.po_number = poi2.po_number
                WHERE TRIM(poi2.item_model) = TRIM(sei.item_code)
                ORDER BY poi2.id DESC
                LIMIT 1
            ),
            ''
        ) AS supplier,
        COALESCE(b.branch_name, ?) AS branch_name,
        se.created_at AS date_sold,
        se.upgrade,
        se.discount AS discount,
        se.total_amount AS upgrade_amount,
        (SELECT COUNT(*) FROM upgrade_new_items uni
         JOIN upgrades u ON u.id = uni.upgrade_id
         WHERE u.new_invoice_no = se.invoice_no
           AND TRIM(uni.item_code) = TRIM(sei.item_code)
           AND (
               (IFNULL(TRIM(uni.imei), '') = '' AND IFNULL(TRIM(sei.imei), '') = '')
               OR UPPER(TRIM(uni.imei)) = UPPER(TRIM(sei.imei))
           )
        ) > 0 AS is_upgrade_item,
        COALESCE((
            SELECT SUM(uoi.price)
            FROM upgrades u
            JOIN upgrade_old_items uoi ON uoi.upgrade_id = u.id
            WHERE (u.original_invoice_no = se.invoice_no OR u.new_invoice_no = se.invoice_no)
        ), 0) AS old_unit_amount,
        (SELECT SUM(COALESCE(sei2.quantity, 0) * COALESCE(sei2.price, 0))
         FROM sales_entry_items sei2
         WHERE sei2.sales_entry_id = se.id
        ) AS invoice_subtotal
    FROM sales_entry se
    LEFT JOIN sales_entry_items sei ON sei.sales_entry_id = se.id
    LEFT JOIN items i ON i.item_code = sei.item_code
    LEFT JOIN branches b ON b.branch_code = se.branch_code
    LEFT JOIN purchase_orders po ON po.po_number = sei.dr_number
    WHERE DATE(se.created_at) BETWEEN ? AND ?
      AND se.branch_code = ?
      AND (sei.quantity IS NULL OR sei.quantity > 0)
      AND se.status != 'voided'
    ORDER BY se.created_at DESC, se.id DESC, sei.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $branch, $date_from, $date_to, $branch_code);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    // Get payment_data to check for loan information
    $se_id = null;
    $payment_data_json = '';
    $se_stmt = $conn->prepare("SELECT id, payment_data FROM sales_entry WHERE invoice_no = ? LIMIT 1");
    $se_stmt->bind_param("s", $row['invoice_no']);
    $se_stmt->execute();
    $se_result = $se_stmt->get_result();
    if ($se_result && $se_data = $se_result->fetch_assoc()) {
        $se_id = $se_data['id'];
        $payment_data_json = $se_data['payment_data'];
    }
    $se_stmt->close();
    
    $pd = [];
    if (!empty($payment_data_json)) {
        $pd = json_decode($payment_data_json, true);
        if (!is_array($pd)) {
            $pd = [];
        }
    }
    
    // Calculate total_sales: apply voucher/token to the specific item (not pro-rated)
    $item_subtotal = floatval($row['item_subtotal']);
    $invoice_subtotal = floatval($row['invoice_subtotal']);
    $invoice_discount = floatval($row['invoice_discount']);
    $invoice_voucher = floatval($row['invoice_voucher'] ?? 0);
    $invoice_token = floatval($row['invoice_token'] ?? 0);
    $item_voucher = floatval($row['item_voucher'] ?? 0);
    $item_token = floatval($row['item_token'] ?? 0);
    
    // SRP is the item_subtotal (price × quantity without discount)
    $srp_amount = $item_subtotal;
    
    // Determine the correct total_sales to display
    // For loan-based payments, use the Total from payment_data (includes loan amount)
    // Otherwise, use the calculated value with discount
    $total_sales = 0;
    $invoice_total_from_pd = 0;
    
    // Check if this is a loan / payment-partner transaction
    $loanType = '';
    $lowerPM = strtolower($pd['payment_type'] ?? '');
    $isLoanPayment = (
        strpos($lowerPM, 'cebu') !== false ||
        strpos($lowerPM, 'partner') !== false ||
        strpos($lowerPM, 'home credit') !== false ||
        strpos($lowerPM, 'salmon') !== false ||
        strpos($lowerPM, 'sumisho') !== false ||
        strpos($lowerPM, 'payjoy') !== false ||
        strpos($lowerPM, 'billease') !== false ||
        strpos($lowerPM, 'paymongo') !== false ||
        strpos($lowerPM, 'skyro') !== false ||
        strpos($lowerPM, 'samsung') !== false ||
        isset($pd['Loan Term']) || isset($pd['Loan Terms']) ||
        isset($pd['Loan Type']) || isset($pd['loan_type']) || isset($pd['loanTypeDropdown']) ||
        isset($pd['Loan Balance']) || isset($pd['loan_balance']) ||
        isset($pd['payment_partner'])
    );
    if ($isLoanPayment) {
        $loanType = $pd['Loan Type'] ?? $pd['loan_type'] ?? $pd['loanTypeDropdown'] ?? 'loan';
    }

    if ($isLoanPayment) {
        if (!empty($pd['Total'])) {
            $totalFromPaymentData = str_replace(',', '', (string) $pd['Total']);
            if (is_numeric($totalFromPaymentData) && (float) $totalFromPaymentData > 0) {
                $invoice_total_from_pd = (float) $totalFromPaymentData;
            }
        }
        if ($invoice_total_from_pd <= 0 && !empty($pd['totalLoanAmount'])) {
            $totalLoanAmt = str_replace(',', '', (string) $pd['totalLoanAmount']);
            if (is_numeric($totalLoanAmt) && (float) $totalLoanAmt > 0) {
                $invoice_total_from_pd = (float) $totalLoanAmt;
            }
        }
        if ($invoice_total_from_pd <= 0) {
            $lbVal = $pd['Loan Balance'] ?? $pd['loan_balance'] ?? 0;
            $invoice_total_from_pd += (float) str_replace(',', '', (string) $lbVal);
            $dpPairs = [
                ['cash_down_payment_amount', 'cash_dp_amount'],
                ['gcash_down_payment_amount', 'gcash_dp_amount'],
                ['maya_down_payment_amount', 'maya_dp_amount'],
            ];
            foreach ($dpPairs as $dpKeys) {
                foreach ($dpKeys as $dpKey) {
                    if (!isset($pd[$dpKey])) {
                        continue;
                    }
                    $dpRaw = str_replace(',', '', (string) $pd[$dpKey]);
                    $dpParts = explode('|', $dpRaw);
                    foreach ($dpParts as $dpPart) {
                        $dpPart = trim($dpPart);
                        if (is_numeric($dpPart)) {
                            $invoice_total_from_pd += (float) $dpPart;
                            break 2;
                        }
                    }
                }
            }
        }
    }
    
    // Calculate total_sales
    if ($invoice_total_from_pd > 0 && $invoice_subtotal > 0) {
        // Use loan total proportionally for this item
        $proportion = $item_subtotal / $invoice_subtotal;
        $total_sales = round($invoice_total_from_pd * $proportion);
    } else {
        // Non-loan: deduct this item's own voucher/token (+ proportional invoice discount)
        $item_discount = 0;
        if ($invoice_subtotal > 0 && $invoice_discount > 0) {
            $item_discount = round($invoice_discount * ($item_subtotal / $invoice_subtotal));
        }
        $item_net = $item_subtotal - $item_voucher - $item_token - $item_discount;
        $expected_total = $invoice_subtotal - $invoice_voucher - $invoice_token - $invoice_discount;
        $invoice_total = floatval($row['invoice_total']);

        if ($expected_total > 0 && $invoice_total > 0 && abs($invoice_total - $expected_total) > 0.02) {
            $total_sales = round($invoice_total * ($item_net / $expected_total));
        } else {
            $total_sales = round($item_net);
        }
    }
    
            // For upgrade items, calculate the actual cash paid for the upgrade
            if (!empty($row['is_upgrade_item'])) {
                $upgPaid = 0;
                if (!empty($pd['Amount'])) {
                    $upgPaid = floatval(str_replace(',', '', (string)$pd['Amount']));
                } elseif (!empty($pd['Enter Amount'])) {
                    $upgPaid = floatval(str_replace(',', '', (string)$pd['Enter Amount']));
                } elseif (!empty($pd['Cash Amount'])) {
                    $upgPaid = floatval(str_replace(',', '', (string)$pd['Cash Amount']));
                }
                if ($upgPaid <= 0) {
                    $rawUpgAmt = floatval($row['upgrade_amount']);
                    $disc = floatval($row['discount']);
                    if ($rawUpgAmt > $disc && $rawUpgAmt == $srp_amount) {
                        $upgPaid = $rawUpgAmt - $disc;
                    } else {
                        $upgPaid = $rawUpgAmt;
                    }
                }
                $row['upgrade_amount'] = $upgPaid;
            }

            // Add the calculated values to the row
            $row['total_sales'] = $total_sales;
            $row['srp_amount'] = $srp_amount;

            $rows[] = $row;
}
$stmt->close();

// ── Include preorder downpayments (same behaviour as fetch_sales_report_range.php) ──
function computePOPaidAmount($pd_json)
{
    $amount = 0.0;
    $pd = json_decode($pd_json, true);
    if (!is_array($pd))
        return $amount;
    if (isset($pd['payment_type']) && $pd['payment_type'] === 'multiple') {
        foreach ((array) ($pd['payments'] ?? []) as $p) {
            $amount += floatval(str_replace(',', '', $p['amount'] ?? 0));
        }
    } else {
        $amount = floatval(str_replace(',', '', $pd['amount'] ?? 0));
    }
    return $amount;
}

$po_sql = "
    SELECT
        p.invoice_no,
        p.assisted_by,
        p.payment_data,
        p.encoder,
        p.branch_code,
        p.created_at,
        p.total_qty,
        p.total_amount,
        p.discount,
        p.id AS preorder_id,
        pi.family_code,
        pi.item_code,
        pi.imei,
        pi.quantity,
        pi.price,
        COALESCE(b.branch_name, p.branch_code) AS branch_name,
        (SELECT SUM(COALESCE(pi2.quantity, 0) * COALESCE(pi2.price, 0)) FROM preorder_items pi2 WHERE pi2.preorder_id = p.id) AS preorder_total_srp
    FROM preorders p
    LEFT JOIN preorder_items pi ON pi.preorder_id = p.id
    LEFT JOIN branches b ON b.branch_code = p.branch_code
    WHERE DATE(p.created_at) BETWEEN ? AND ?
      AND p.branch_code = ?
      AND p.status NOT IN ('claimed')
      AND (pi.quantity IS NULL OR pi.quantity > 0)
    ORDER BY p.created_at DESC, p.id DESC
";

$po_stmt = $conn->prepare($po_sql);
$po_stmt->bind_param("sss", $date_from, $date_to, $branch_code);
$po_stmt->execute();
$po_result = $po_stmt->get_result();
if ($po_result) {
    while ($po = $po_result->fetch_assoc()) {
        $paid = computePOPaidAmount($po['payment_data']);
        $item_price = floatval($po['price']);
        $item_qty = intval($po['quantity']);
        $item_subtotal = $item_price * $item_qty;
        $preorder_total_srp = floatval($po['preorder_total_srp']);

        if ($preorder_total_srp > 0) {
            $proportion = $item_subtotal / $preorder_total_srp;
            $item_paid = round($paid * $proportion);
        } else {
            $item_paid = $paid;
        }

        $rows[] = [
            'invoice_no' => $po['invoice_no'],
            'representative' => $po['assisted_by'],
            'quantity' => $item_qty,
            'item_price' => $item_price,
            'invoice_discount' => 0,
            'invoice_total' => $paid,
            'item_subtotal' => $item_subtotal,
            'commission' => 0,
            'family_code' => $po['family_code'],
            'imei' => $po['imei'],
            'brand' => '',
            'group_name' => '',
            'supplier' => '',
            'branch_name' => $po['branch_name'],
            'date_sold' => $po['created_at'],
            'upgrade' => null,
            'upgrade_amount' => 0,
            'is_upgrade_item' => 0,
            'old_unit_amount' => 0,
            'invoice_subtotal' => $item_subtotal,
            'total_sales' => $item_paid,
            'srp_amount' => $item_subtotal,
        ];
    }
}
$po_stmt->close();

class SalesReportPDF extends FPDF
{
    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Courier', 'B', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, 'PAGE ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new SalesReportPDF('L', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 6, 10, 20, 20);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(20);
$pdf->Cell(0, 8, 'MONTHLY SALES REPORT', 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(0, 6, 'BRANCH: ' . strtoupper($branch), 0, 1, 'L');
$pdf->Cell(0, 6, 'DATE RANGE: ' . date('F d, Y', strtotime($date_from)) . ' to ' . date('F d, Y', strtotime($date_to)), 0, 1, 'L');
$pdf->Cell(0, 6, 'TIME & DATE: ' . date('h:i:s A - F d, Y'), 0, 1, 'R');
$pdf->Ln(2);

$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Courier', 'B', 6.5);

// Total 277mm width for landscape A4 (with 10mm left/right margins)
$widths = [24, 7, 14, 14, 24, 14, 14, 14, 26, 18, 16, 16, 16, 18, 18, 18];
$headers = [
    'REPRESENTATIVE',
    'QTY',
    'COMM',
    'FAMILY',
    'IMEI',
    'SRP',
    'BRAND',
    'GROUP',
    'SUPPLIER',
    'BRANCH',
    'DATE SOLD',
    'DATE UPGD',
    'OLD UNIT',
    'UPGD AMT',
    'TOTAL UPGD',
    'TOTAL SALES'
];

for ($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('Courier', '', 6);
if (count($rows) === 0) {
    $pdf->Cell(array_sum($widths), 8, 'NO DATA', 1, 1, 'C');
} else {
    foreach ($rows as $row) {
        $isUpgd = !empty($row['is_upgrade_item']);
        $oldUnitAmountRaw = (float)($row['old_unit_amount'] ?? 0);
        $upgradeUnitAmountRaw = (float)($row['upgrade_amount'] ?? 0);

        $cashPaidUpgd = $isUpgd ? $upgradeUnitAmountRaw : 0;

        $displayTotalSales = $isUpgd ? 0 : (float)$row['total_sales'];
        $displayDateUpg = $isUpgd ? date('m/d/Y', strtotime($row['date_sold'])) : '';
        $displayOldUnit = $isUpgd ? number_format($oldUnitAmountRaw, 2) : '0.00';
        $displayUpgdAmt = $isUpgd ? number_format($cashPaidUpgd, 2) : '0.00';
        $displayTotalUpgd = $isUpgd ? number_format($cashPaidUpgd, 2) : '0.00';

        $pdf->Cell($widths[0], 7, strtoupper(substr((string)$row['representative'], 0, 18)), 1, 0, 'L');
        $pdf->Cell($widths[1], 7, $row['quantity'], 1, 0, 'C');
        $pdf->Cell($widths[2], 7, number_format((float)$row['commission'], 2), 1, 0, 'R');
        $pdf->Cell($widths[3], 7, strtoupper(substr($row['family_code'], 0, 10)), 1, 0, 'C');
        
        $imei_val = strtoupper(substr($row['imei'], 0, 18));
        if (strlen(trim($row['imei'])) != 15) {
            $pdf->SetTextColor(255, 0, 0);
        }
        $pdf->Cell($widths[4], 7, $imei_val, 1, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
        
        // SRP column
        $pdf->Cell($widths[5], 7, number_format((float)$row['srp_amount'], 2), 1, 0, 'R');
        
        $pdf->Cell($widths[6], 7, strtoupper(substr($row['brand'], 0, 10)), 1, 0, 'C');
        $pdf->Cell($widths[7], 7, strtoupper(substr($row['group_name'], 0, 10)), 1, 0, 'C');
        $pdf->Cell($widths[8], 7, strtoupper(substr($row['supplier'], 0, 20)), 1, 0, 'L');
        $pdf->Cell($widths[9], 7, strtoupper(substr($row['branch_name'], 0, 14)), 1, 0, 'C');
        $pdf->Cell($widths[10], 7, date('m/d/Y', strtotime($row['date_sold'])), 1, 0, 'C');
        $pdf->Cell($widths[11], 7, $displayDateUpg, 1, 0, 'C');
        $pdf->Cell($widths[12], 7, $displayOldUnit, 1, 0, 'R');
        $pdf->Cell($widths[13], 7, $displayUpgdAmt, 1, 0, 'R');
        $pdf->Cell($widths[14], 7, $displayTotalUpgd, 1, 0, 'R');
        $pdf->Cell($widths[15], 7, number_format($displayTotalSales, 2), 1, 1, 'R');
    }
}

$pdf->Output('I', 'Sales_Report_' . $date_from . '_to_' . $date_to . '_' . $branch . '.pdf');
?>
