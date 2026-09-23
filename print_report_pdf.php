<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

// Get parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : (isset($_GET['date']) ? $_GET['date'] : '');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : $date_from;
$branch = isset($_GET['branch']) ? $_GET['branch'] : '';

if (empty($date_from) || empty($date_to) || empty($branch)) {
    die("Missing required parameters: date_from/date_to and branch");
}

// Get branch code from branch name
$branch_code = '';
$branch_query = $conn->prepare("SELECT branch_code FROM branches WHERE branch_name = ?");
$branch_query->bind_param("s", $branch);
$branch_query->execute();
$branch_result = $branch_query->get_result();
if ($branch_result && $branch_result->num_rows > 0) {
    $branch_data = $branch_result->fetch_assoc();
    $branch_code = $branch_data['branch_code'];
}
$branch_query->close();

if (empty($branch_code)) {
    die("Branch not found: " . $branch);
}

// Helper: compute total paid from payment_data JSON (preorders only)
function computePreorderPaidAmount($pd_json)
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

// Fetch sales data (including refunded invoices) for the selected date range
$stmt = $conn->prepare("
    SELECT 
        se.id,
        se.invoice_no,
        se.original_invoice_no,
        se.branch_code,
        se.total_qty,
        se.total_amount,
        se.discount,
        se.commission,
        se.status,
        se.assisted_by,
        se.encoder,
        se.payment_data,
        se.created_at,
        se.upgrade,
        COALESCE((
            SELECT SUM(uoi.price)
            FROM upgrades u
            JOIN upgrade_old_items uoi ON uoi.upgrade_id = u.id
            WHERE u.new_invoice_no = se.invoice_no
        ), 0) AS old_unit_amount,
        se.status as display_status,
        r.total_amount as refund_amount,
        se.page_type,
        se.promo_id,
        se.original_invoice_no
    FROM sales_entry se
    LEFT JOIN refunds r ON r.invoice_no = se.invoice_no
    WHERE DATE(se.created_at) BETWEEN ? AND ? AND se.branch_code = ?
      AND (se.page_type != 'claimpreorder' OR se.page_type IS NULL)
    ORDER BY se.created_at ASC
");

$stmt->bind_param("sss", $date_from, $date_to, $branch_code);
$stmt->execute();
$result = $stmt->get_result();

$sales = [];
$lastInvoice = '';

while ($row = $result->fetch_assoc()) {
    $sales[] = $row;

    // Match fetch_sales_report.php: last invoice is MAX invoice_no from sales_entry only
    if (empty($lastInvoice) || $row['invoice_no'] > $lastInvoice) {
        $lastInvoice = $row['invoice_no'];
    }

    // Fetch items for this sale using sales_entry_id
    $stmt_items = $conn->prepare("
        SELECT 
            sei.item_description,
            sei.item_code,
            sei.imei,
            sei.quantity,
            sei.price,
            sei.is_promo_item,
            (SELECT COUNT(*) FROM refund_items ri 
             JOIN refunds r ON r.id = ri.refund_id 
             WHERE r.invoice_no = ? 
               AND ri.item_code = sei.item_code 
               AND (ri.imei = sei.imei OR (IFNULL(ri.imei,'') = IFNULL(sei.imei,'')))
            ) > 0 AS is_refunded,
            (SELECT COUNT(*) FROM upgrade_new_items uni
             JOIN upgrades u ON u.id = uni.upgrade_id
             WHERE u.new_invoice_no = ?
               AND TRIM(uni.item_code) = TRIM(sei.item_code)
               AND (
                   (IFNULL(TRIM(uni.imei), '') = '' AND IFNULL(TRIM(sei.imei), '') = '')
                   OR UPPER(TRIM(uni.imei)) = UPPER(TRIM(sei.imei))
               )
            ) > 0 AS is_upgrade_item,
            (SELECT COUNT(*) FROM upgrade_old_items uoi
             JOIN upgrades u ON u.id = uoi.upgrade_id
             WHERE u.original_invoice_no = ?
               AND (
                   (IFNULL(TRIM(uoi.imei), '') != '' AND UPPER(TRIM(uoi.imei)) = UPPER(TRIM(sei.imei)))
                   OR (IFNULL(TRIM(uoi.imei), '') = '' AND UPPER(TRIM(uoi.item_description)) = UPPER(TRIM(sei.item_description)))
               )
            ) > 0 AS is_old_upgrade_item
        FROM sales_entry_items sei 
        WHERE sei.sales_entry_id = ?
        ORDER BY sei.id
    ");

    $stmt_items->bind_param("sssi", $row['invoice_no'], $row['invoice_no'], $row['invoice_no'], $row['id']);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();

    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    $stmt_items->close();

    // Get payment_data to check for loan information and calculate proper total
    $pd = [];
    if (!empty($row['payment_data'])) {
        $pd = json_decode($row['payment_data'], true);
        if (!is_array($pd)) {
            $pd = [];
        }
    }

    // Determine if this is a loan / payment-partner transaction and get the actual total
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

    $actual_total_amount = floatval($row['total_amount']);
    if ($isLoanPayment) {
        $totalFromPaymentData = 0;
        if (!empty($pd['Total'])) {
            $totalRaw = str_replace(',', '', (string) $pd['Total']);
            if (is_numeric($totalRaw) && (float) $totalRaw > 0) {
                $totalFromPaymentData = (float) $totalRaw;
            }
        }
        if ($totalFromPaymentData <= 0 && !empty($pd['totalLoanAmount'])) {
            $totalLoanAmt = str_replace(',', '', (string) $pd['totalLoanAmount']);
            if (is_numeric($totalLoanAmt) && (float) $totalLoanAmt > 0) {
                $totalFromPaymentData = (float) $totalLoanAmt;
            }
        }
        if ($totalFromPaymentData <= 0) {
            $lbVal = $pd['Loan Balance'] ?? $pd['loan_balance'] ?? 0;
            $totalFromPaymentData += (float) str_replace(',', '', (string) $lbVal);
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
                            $totalFromPaymentData += (float) $dpPart;
                            break 2;
                        }
                    }
                }
            }
        }
        if ($totalFromPaymentData > 0) {
            $actual_total_amount = $totalFromPaymentData;
        }
    }

    $sales[count($sales) - 1]['actual_total_amount'] = $actual_total_amount;
    $sales[count($sales) - 1]['items'] = $items;
}

$stmt->close();

// Include preorder payment transactions exactly like fetch_sales_report.php
$po_query = $conn->prepare("
    SELECT
        ph.id              AS ph_id,
        ph.invoice_no      AS payment_invoice_no,
        ph.payment_date,
        ph.amount          AS payment_amount,
        ph.payment_type    AS ph_payment_type,
        ph.payment_method  AS ph_payment_method,
        ph.payment_data    AS ph_payment_data,
        ph.payment_sequence,
        ph.encoder         AS ph_encoder,
        p.id               AS preorder_id,
        p.invoice_no       AS preorder_no,
        p.claimed_at,
        p.claimed_invoice_no,
        p.first_name,
        p.last_name,
        p.assisted_by,
        p.remarks,
        p.total_qty,
        p.total_amount     AS preorder_total_srp,
        p.discount,
        p.payment_data     AS full_payment_data,
        p.encoder          AS preorder_encoder,
        p.branch_code,
        p.status           AS preorder_status,
        p.created_at       AS preorder_created_at
    FROM preorder_payment_history ph
    INNER JOIN preorders p ON ph.preorder_id = p.id
    WHERE DATE(ph.payment_date) BETWEEN ? AND ?
      AND p.branch_code = ?
    ORDER BY ph.payment_date ASC, ph.payment_sequence ASC
");

$po_query->bind_param("sss", $date_from, $date_to, $branch_code);
$po_query->execute();
$po_result = $po_query->get_result();

$preorder_groups = [];

if ($po_result) {
    while ($po = $po_result->fetch_assoc()) {
        $paid = floatval($po['payment_amount']);
        $invoice_display = !empty($po['payment_invoice_no']) ? $po['payment_invoice_no'] : $po['preorder_no'];

        // Build item list from preorder_items
        $poi_q = $conn->prepare("SELECT family_code AS item_description, item_code, imei, quantity, price FROM preorder_items WHERE preorder_id = ?");
        $poi_q->bind_param("i", $po['preorder_id']);
        $poi_q->execute();
        $poi_res = $poi_q->get_result();

        $po_items = [];
        while ($poi = $poi_res->fetch_assoc()) {
            $po_items[] = [
                'item_description' => $poi['item_description'],
                'item_code' => $poi['item_code'],
                'imei' => $poi['imei'],
                'quantity' => $poi['quantity'],
                'price' => $poi['price'], // SRP
                'is_refunded' => 0,
                'is_upgrade_item' => 0,
            ];
        }
        $poi_q->close();

        $sales[] = [
            'id' => 'PO-' . $po['preorder_id'] . '-' . $po['ph_id'],
            'invoice_no' => $invoice_display,
            'first_name' => $po['first_name'],
            'last_name' => $po['last_name'],
            'assisted_by' => $po['assisted_by'],
            'remarks' => '(PRE-ORDER) ' . $po['remarks'],
            'total_qty' => $po['total_qty'],
            'total_amount' => $paid, // payment actually made for this invoice
            'actual_total_amount' => $paid, // same as total_amount for preorders
            'discount' => 0,
            'commission' => 0,
            'payment_data' => !empty($po['ph_payment_data']) ? $po['ph_payment_data'] : $po['full_payment_data'],
            'encoder' => !empty($po['ph_encoder']) ? $po['ph_encoder'] : $po['preorder_encoder'],
            'branch_code' => $po['branch_code'],
            'created_at' => $po['payment_date'],
            'status' => 'completed',
            'upgrade' => null,
            'old_unit_amount' => 0,
            'upgrade_payment_data' => null,
            'void_reason' => null,
            'voided_at' => null,
            'voided_by' => null,
            'refund_amount' => 0,
            'display_status' => 'completed',
            'items' => $po_items,
        ];

        // Track for breakdown grouping
        $po_id = $po['preorder_id'];
        if (!isset($preorder_groups[$po_id])) {
            $first_desc = !empty($po_items[0]['item_description']) ? $po_items[0]['item_description'] : (!empty($po_items[0]['item_code']) ? $po_items[0]['item_code'] : 'PRE-ORDER ITEM');
            $first_qty = !empty($po_items[0]['quantity']) ? intval($po_items[0]['quantity']) : 1;
            $preorder_groups[$po_id] = [
                'preorder_id' => $po_id,
                'original_preorder_no' => $po['preorder_no'],
                'claimed_invoice_no' => $po['claimed_invoice_no'] ?? null,
                'claimed_at' => $po['claimed_at'] ?? null,
                'preorder_status' => $po['preorder_status'],
                'item_description' => $first_desc,
                'quantity' => $first_qty,
                'branch' => $branch,
                'payments' => []
            ];
        }
        $preorder_groups[$po_id]['payments'][] = [
            'invoice_no' => $invoice_display,
            'date' => $po['payment_date'],
            'payment_sequence' => $po['payment_sequence']
        ];
    }
}

$po_query->close();

$unclaimed_breakdowns = [];

// 1. Process Preorder groups for breakdown
foreach ($preorder_groups as $po_id => $group) {
    $is_claimed = (strtolower(trim($group['preorder_status'])) === 'claimed');

    // UNCLAIMED PRE-ORDER for each payment invoice
    foreach ($group['payments'] as $p) {
        $unclaimed_breakdowns[] = [
            'type' => 'preorder',
            'invoice_number' => $p['invoice_no'],
            'item_code' => $group['item_description'],
            'quantity' => $group['quantity'],
            'branch' => $group['branch'],
            'status' => 'unclaimed',
            'created_at' => $p['date'],
            'claimed_at' => null
        ];
    }

    // CLAIMED PRE-ORDER combined entry if claimed
    if ($is_claimed) {
        $allInvoices = array_map(function($p) { return $p['invoice_no']; }, $group['payments']);
        if (!empty($group['claimed_invoice_no']) && !in_array($group['claimed_invoice_no'], $allInvoices)) {
            $allInvoices[] = $group['claimed_invoice_no'];
        }
        $combinedInvoiceNo = implode(' & ', $allInvoices);

        $unclaimed_breakdowns[] = [
            'type' => 'preorder',
            'invoice_number' => $combinedInvoiceNo,
            'item_code' => $group['item_description'],
            'quantity' => $group['quantity'],
            'branch' => $group['branch'],
            'status' => 'claimed',
            'created_at' => $group['payments'][0]['date'] ?? null,
            'claimed_at' => !empty($group['claimed_at']) ? $group['claimed_at'] : ($group['payments'][0]['date'] ?? null)
        ];
    }
}

// Fetch Unclaimed Freebies data for the selected date range
$freebies_stmt = $conn->prepare("
    SELECT 
        uf.id,
        uf.invoice_number,
        uf.item_code,
        uf.item_description,
        uf.quantity,
        uf.status,
        uf.branch,
        uf.created_by,
        uf.created_at,
        uf.claimed_at
    FROM unclaimed_freebies uf
    WHERE (
        DATE(uf.created_at) BETWEEN ? AND ?
        OR
        (uf.status = 'claimed' AND DATE(COALESCE(NULLIF(uf.claimed_at, '0000-00-00 00:00:00'), uf.created_at)) BETWEEN ? AND ?)
    )
    AND uf.status != 'void'
    AND uf.branch = ?
    ORDER BY uf.created_at DESC, uf.invoice_number ASC
");

$freebies_stmt->bind_param("sssss", $date_from, $date_to, $date_from, $date_to, $branch);
$freebies_stmt->execute();
$freebies_result = $freebies_stmt->get_result();

if ($freebies_result) {
    while ($freebie = $freebies_result->fetch_assoc()) {
        $created_date = !empty($freebie['created_at']) ? date('Y-m-d', strtotime($freebie['created_at'])) : '';
        $claimed_raw = (!empty($freebie['claimed_at']) && $freebie['claimed_at'] != '0000-00-00 00:00:00') ? $freebie['claimed_at'] : $freebie['created_at'];
        $claimed_date = !empty($claimed_raw) ? date('Y-m-d', strtotime($claimed_raw)) : '';

        // Add UNCLAIMED entry if created_at is within date range
        if (empty($date_from) || empty($date_to) || ($created_date >= $date_from && $created_date <= $date_to)) {
            $unclaimed_breakdowns[] = [
                'type' => 'freebie',
                'id' => $freebie['id'] . '_unclaimed',
                'invoice_number' => $freebie['invoice_number'],
                'item_code' => !empty($freebie['item_code']) ? $freebie['item_code'] : $freebie['item_description'],
                'item_description' => $freebie['item_description'],
                'quantity' => intval($freebie['quantity']),
                'status' => 'unclaimed',
                'branch' => $freebie['branch'],
                'created_by' => $freebie['created_by'],
                'created_at' => $freebie['created_at'],
                'claimed_at' => null
            ];
        }

        // Add CLAIMED entry if status is claimed and claimed date is within date range
        if (strtolower($freebie['status']) === 'claimed') {
            if (empty($date_from) || empty($date_to) || (empty($claimed_date) || ($claimed_date >= $date_from && $claimed_date <= $date_to))) {
                $unclaimed_breakdowns[] = [
                    'type' => 'freebie',
                    'id' => $freebie['id'] . '_claimed',
                    'invoice_number' => $freebie['invoice_number'],
                    'item_code' => !empty($freebie['item_code']) ? $freebie['item_code'] : $freebie['item_description'],
                    'item_description' => $freebie['item_description'],
                    'quantity' => intval($freebie['quantity']),
                    'status' => 'claimed',
                    'branch' => $freebie['branch'],
                    'created_by' => $freebie['created_by'],
                    'created_at' => $freebie['created_at'],
                    'claimed_at' => $claimed_raw
                ];
            }
        }
    }
}
$freebies_stmt->close();

// Helper function to abbreviate names
function abbreviateName($fullName)
{
    if (!$fullName || trim($fullName) === '')
        return '';
    $words = preg_split('/\s+/', trim($fullName));
    $abbreviated = '';
    foreach ($words as $word) {
        if (strlen($word) > 0) {
            $abbreviated .= strtoupper(substr($word, 0, 1)) . '.';
        }
    }
    if (strlen($abbreviated) > 0) {
        $abbreviated .= '.';
    }
    return $abbreviated;
}

function fitTextInCell($pdf, $text, $cellWidth, $baseFontSize = 5, $minFontSize = 3)
{
    // FPDF uses ISO-8859-1 encoding; convert UTF-8 from DB so special chars (ñ→Ñ) render correctly
    $text = utf8_decode(mb_strtoupper(trim((string) $text), 'UTF-8'));
    $fontSize = $baseFontSize;
    $availableWidth = $cellWidth - 2; // increased horizontal padding to prevent overflow

    $pdf->SetFont('Courier', '', $fontSize);
    while ($fontSize > $minFontSize && $pdf->GetStringWidth($text) > $availableWidth) {
        $fontSize -= 0.2;
        $pdf->SetFont('Courier', '', $fontSize);
    }

    return [$text, $fontSize];
}

// Helper to get per-item transaction amount from payment_data and unit_payment_map
function getPerItemAmountPdf($item, $pd, $uMap)
{
    if (!is_array($pd) || empty($uMap) || !is_array($uMap)) return null;

    $mapMethods = [];
    foreach ($uMap as $m) {
        $cleanM = strtolower(trim((string)$m));
        if ($cleanM !== '') $mapMethods[$cleanM] = true;
    }
    if (count($mapMethods) <= 1) return null;

    $imei = strtoupper(trim((string)($item['imei'] ?? '')));
    $desc = strtoupper(trim((string)(($item['item_description'] ?? '') ?: ($item['item_code'] ?? ''))));
    $matchedMethod = null;

    if ($imei) {
        foreach ($uMap as $key => $method) {
            if (strpos(strtoupper((string)$key), $imei) !== false) {
                $matchedMethod = (string)$method;
                break;
            }
        }
    }
    if ($matchedMethod === null && $desc) {
        foreach ($uMap as $key => $method) {
            if (strpos(strtoupper((string)$key), $desc) === 0) {
                $matchedMethod = (string)$method;
                break;
            }
        }
    }

    $isLoanMethod = function ($m) {
        $ml = strtolower((string)$m);
        return strpos($ml, 'home credit') !== false || strpos($ml, 'salmon') !== false ||
               strpos($ml, 'sumisho') !== false || strpos($ml, 'payjoy') !== false ||
               strpos($ml, 'billease') !== false || strpos($ml, 'paymongo') !== false ||
               strpos($ml, 'skyro') !== false || strpos($ml, 'samsung') !== false ||
               strpos($ml, 'cebu') !== false || strpos($ml, 'partner') !== false ||
               strpos($ml, 'makati') !== false;
    };

    if ($matchedMethod === null) {
        $pt = strtolower((string)($pd['payment_type'] ?? ''));
        $hasCashInType = strpos($pt, 'cash') !== false;
        $hasLoanInType = $isLoanMethod($pt) || isset($pd['Loan Balance']) || isset($pd['totalLoanAmount']);
        $allMapAreLoan = true;
        foreach ($uMap as $m) {
            if (!$isLoanMethod($m)) { $allMapAreLoan = false; break; }
        }
        if ($hasCashInType && $hasLoanInType && $allMapAreLoan) {
            $cashAmtRaw = (string)($pd['Amount'] ?? $pd['cash_amount'] ?? '');
            $cashAmt = floatval(str_replace(',', '', trim($cashAmtRaw)));
            if ($cashAmt > 0) return $cashAmt * intval($item['quantity'] ?? 1);
        }
        return null;
    }

    $matchedKey = strtolower(trim($matchedMethod));
    $countWithSameMethod = 0;
    foreach ($uMap as $m) {
        if (strtolower(trim((string)$m)) === $matchedKey) $countWithSameMethod++;
    }
    if ($countWithSameMethod > 1) {
        return null;
    }

    if ($isLoanMethod($matchedMethod)) {
        $lbRaw = $pd['Loan Balance'] ?? $pd['loan_balance'] ?? $pd['totalLoanAmount'] ?? $pd['total_loan_amount'] ?? 0;
        $loanTotal = floatval(str_replace(',', '', (string)$lbRaw));
        $dpKeys = ['cash_down_payment_amount', 'cash_dp_amount', 'gcash_down_payment_amount',
                   'gcash_dp_amount', 'maya_down_payment_amount', 'maya_dp_amount'];
        foreach ($dpKeys as $dk) {
            if (isset($pd[$dk])) {
                $dpParts = explode('|', str_replace(',', '', (string)$pd[$dk]));
                $dpVal = floatval(trim($dpParts[0]));
                if ($dpVal > 0) $loanTotal += $dpVal;
            }
        }
        if ($loanTotal > 0) return $loanTotal * intval($item['quantity'] ?? 1);
        return null;
    } elseif ($matchedKey === 'cash') {
        $cashAmtRaw = (string)($pd['Amount'] ?? $pd['cash_amount'] ?? '');
        $cashAmt = floatval(str_replace(',', '', trim($cashAmtRaw)));
        if ($cashAmt > 0) return $cashAmt * intval($item['quantity'] ?? 1);
        return null;
    }

    return null;
}

// Create PDF with custom footer
class PDF extends FPDF
{
    function Footer()
    {
        $this->SetY(-15);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Courier', 'B', 10);
        $this->Cell(0, 10, 'PAGE 1 OF 1', 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);

// Logo and Title
$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.PNG';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 15, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(25);
$pdf->Cell(0, 10, 'DAILY SALES', 0, 1, 'C');
$pdf->Ln(5);

// Meta information
$pdf->SetFont('Courier', 'B', 9);
$pdf->SetTextColor(0, 0, 0);

$pdf->Cell(0, 6, 'BRANCH: ' . $branch, 0, 1, 'L');
$dateDisplayFrom = date('F d, Y', strtotime($date_from));
$dateDisplayTo = date('F d, Y', strtotime($date_to));
$dateDisplay = ($dateDisplayFrom === $dateDisplayTo) ? $dateDisplayFrom : ($dateDisplayFrom . ' - ' . $dateDisplayTo);
$pdf->Cell(0, 6, 'DATE OF SALES: ' . $dateDisplay, 0, 1, 'L');

// LAST INVOICE NO on left, TIME & DATE on right - aligned to table width (180mm)
$y_pos = $pdf->GetY();
$pdf->SetXY(15, $y_pos);
$pdf->Cell(90, 6, 'LAST INVOICE NO: ' . $lastInvoice, 0, 0, 'L');
$pdf->Cell(90, 6, 'TIME & DATE: ' . date('h:i:s A - F d, Y'), 0, 1, 'R');

$pdf->Ln(5);

// Table Header
$pdf->SetFont('Courier', 'B', 6);
$pdf->SetFillColor(240, 240, 240);

$y = $pdf->GetY();

// Column widths (total stays 180mm)
$wInvoice = 19;
$wQty = 7;
$wItemCode = 42;
$wSrp = 13;
$wOldUnit = 12;
$wItemAmt = 15;
$wTotalAmt = 16;
$wComm = 9;
$wUpg = 9;
$wStat = 8;
$wSp = 7;
$wEn = 7;
$wPayment = 16;

// Draw all header cells with borders
$pdf->Cell($wInvoice, 8, 'INVOICE NO', 1, 0, 'C', true);
$pdf->Cell($wQty, 8, 'QTY', 1, 0, 'C', true);
$pdf->Cell($wItemCode, 8, 'ITEM CODE', 1, 0, 'C', true);
$pdf->Cell($wSrp, 8, 'SRP', 1, 0, 'C', true);
$pdf->Cell($wOldUnit, 8, 'OLD UNIT', 1, 0, 'C', true);

// ITEM AMT with line break
$x1 = $pdf->GetX();
$pdf->Rect($x1, $y, $wItemAmt, 8, 'FD');
$pdf->SetXY($x1, $y + 1);
$pdf->Cell($wItemAmt, 3, 'ITEM', 0, 2, 'C');
$pdf->SetX($x1);
$pdf->Cell($wItemAmt, 3, 'AMOUNT', 0, 0, 'C');

// TOTAL AMT with line break
$x2 = $x1 + $wItemAmt;
$pdf->Rect($x2, $y, $wTotalAmt, 8, 'FD');
$pdf->SetXY($x2, $y + 1);
$pdf->Cell($wTotalAmt, 3, 'TOTAL', 0, 2, 'C');
$pdf->SetX($x2);
$pdf->Cell($wTotalAmt, 3, 'AMOUNT', 0, 0, 'C');

$pdf->SetXY($x2 + $wTotalAmt, $y);
$pdf->Cell($wComm, 8, 'COMM', 1, 0, 'C', true);
$pdf->Cell($wUpg, 8, 'UPG', 1, 0, 'C', true);
$pdf->Cell($wStat, 8, 'STAT', 1, 0, 'C', true);
$pdf->Cell($wSp, 8, 'SP', 1, 0, 'C', true);
$pdf->Cell($wEn, 8, 'EN', 1, 0, 'C', true);

// Draw PAYMENT METHOD cell border with fill
$x = $pdf->GetX();
$pdf->Rect($x, $y, $wPayment, 8, 'FD');

// Add text inside
$pdf->SetXY($x, $y + 1);
$pdf->SetFont('Courier', 'B', 6);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($wPayment, 3, 'PAYMENT', 0, 2, 'C');
$pdf->SetX($x);
$pdf->Cell($wPayment, 3, 'METHOD', 0, 0, 'C');

// Move to next line
$pdf->SetXY(15, $y + 8);
$pdf->SetFont('Courier', '', 5);

if (count($sales) === 0) {
    $pdf->Cell(180, 8, 'NO SALES', 1, 1, 'C');
} else {
    foreach ($sales as $sale) {
        $isVoided = ($sale['status'] === 'voided');
        $isRefunded = ($sale['display_status'] === 'refunded');
        $isTradeIn = (isset($sale['page_type']) && $sale['page_type'] === 'salestrade-in' && ($sale['upgrade'] ?? '') !== 'UPGD');

        // Parse payment data
        $paymentMethod = '';
        if ($sale['payment_data']) {
            $paymentData = json_decode($sale['payment_data'], true);
            $paymentMethod = isset($paymentData['payment_type']) ? $paymentData['payment_type'] : '';

            if (isset($paymentData['E-Wallet-Text']) && strpos($paymentMethod, 'E-Wallet') !== false) {
                $paymentMethod = str_replace('E-Wallet', $paymentData['E-Wallet-Text'], $paymentMethod);
            }
            if (isset($paymentData['Bank-Text']) && strpos($paymentMethod, 'Online Banking') !== false) {
                $paymentMethod = str_replace('Online Banking', $paymentData['Bank-Text'], $paymentMethod);
            }
            // Format: use '&' separator instead of '+'
            $pmParts = array_filter(array_map('trim', explode(' + ', $paymentMethod)));
            $pmCount = count($pmParts);
            if ($pmCount <= 1) {
                $paymentMethod = implode('', $pmParts);
            } elseif ($pmCount === 2) {
                $pmParts = array_values($pmParts);
                $paymentMethod = $pmParts[0] . ' & ' . $pmParts[1];
            } else {
                $pmParts = array_values($pmParts);
                $paymentMethod = implode(', ', array_slice($pmParts, 0, -1)) . ', & ' . end($pmParts);
            }
        }

        $assistedByAbbr = abbreviateName($sale['assisted_by']);
        $encoderAbbr = abbreviateName($sale['encoder']);
        $hasPromoItemInSale = false;
        if (!empty($sale['items'])) {
            foreach ($sale['items'] as $it) {
                if (!empty($it['is_promo_item']) && (int)$it['is_promo_item'] === 1) {
                    $hasPromoItemInSale = true;
                    break;
                }
            }
        }
        $isPromoEntry = (isset($sale['page_type']) && $sale['page_type'] === 'promosentry') || $hasPromoItemInSale || (!empty($sale['promo_id']) && (int)$sale['promo_id'] > 0);
        $stat = $isVoided ? 'VD' : ($isRefunded ? 'RF' : ($isTradeIn ? 'TRD' : ($isPromoEntry ? 'PROMO' : '')));

        if ($isVoided || $isRefunded) {
            $pdf->SetTextColor(211, 47, 47);
        } else {
            $pdf->SetTextColor(0, 0, 0);
        }

        // Process items
        if (isset($sale['items']) && count($sale['items']) > 0) {
            $itemsCount = count($sale['items']);
            $saleAmount = floatval($sale['actual_total_amount'] ?? $sale['total_amount'] ?? 0);
            $oldUnitAmount = floatval($sale['old_unit_amount'] ?? 0);

            // Same detection as report.php JS
            $isPreorder =
                (isset($sale['id']) && strpos((string) $sale['id'], 'PO-') === 0) ||
                (isset($sale['remarks']) && is_string($sale['remarks']) && strpos($sale['remarks'], 'PRE-ORDER') !== false);

            $totalSrp = 0.0;
            if ($isPreorder && $itemsCount > 1) {
                foreach ($sale['items'] as $it) {
                    $totalSrp += floatval($it['price'] ?? 0) * intval($it['quantity'] ?? 1);
                }
            }

            foreach ($sale['items'] as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                $itemSubtotal = $qty * $price;

                $itemRefunded = !empty($item['is_refunded']);
                $itemVoided = $isVoided;
                $itemIsPromo = (!empty($item['is_promo_item']) && (int)$item['is_promo_item'] === 1)
                    || (($sale['page_type'] ?? '') === 'promosentry' && !$hasPromoItemInSale)
                    || ($isPromoEntry && !$hasPromoItemInSale && !empty($sale['promo_id']) && (int)$sale['promo_id'] > 0);
                $stat = $itemVoided ? 'VD' : ($itemRefunded ? 'RF' : ($isTradeIn ? 'TRD' : ($itemIsPromo ? 'PROMO' : '')));

                // Only NEW upgrade invoices (with original_invoice_no) use cash paid display, not the original invoice
                $isNewUpgradeSale = (($sale['upgrade'] ?? '') === 'UPGD' && !empty($sale['original_invoice_no']));
                $itemIsUpgraded = $isNewUpgradeSale || !empty($item['is_upgrade_item']);
                $itemIsOldUpgraded = !empty($item['is_old_upgrade_item']);

                // report.php colours the entire sale row red when invoice-level is voided/refunded
                if ($isVoided || $isRefunded || $itemRefunded) {
                    $pdf->SetTextColor(211, 47, 47);
                } else {
                    $pdf->SetTextColor(0, 0, 0);
                }

                // Column display values (match report.php)
                $oldUnitDisplay = '0.00';
                $itemAmtDisplay = '';
                $totalAmtDisplay = '';
                $upgradeCellText = '';

                if ($itemIsUpgraded || $itemIsOldUpgraded || ($itemsCount === 1 && ($sale['upgrade'] ?? '') === 'UPGD')) {
                    $upgradeCellText = 'UPGD';
                }

                if ($isNewUpgradeSale) {
                    $oldUnitDisplay = number_format($oldUnitAmount, 2, '.', ',');
                    // Extract cash paid from payment_data.Amount (most reliable)
                    $cashPaid = 0;
                    $amtRaw = $paymentData['Amount'] ?? $paymentData['amount'] ?? '';
                    $parsedAmt = floatval(str_replace(',', '', (string)$amtRaw));
                    if ($parsedAmt > 0) {
                        $cashPaid = $parsedAmt;
                    } else {
                        $cashPaid = max(0, $saleAmount - floatval($sale['discount'] ?? 0));
                    }
                    $itemAmtDisplay = number_format($cashPaid, 2, '.', ',');
                    $totalAmtDisplay = number_format($cashPaid, 2, '.', ',');
                } else {
                    $oldUnitDisplay = '0.00';
                    if ($isPreorder) {
                        if ($itemsCount === 1) {
                            $itemAmtDisplay = number_format($saleAmount, 2, '.', ',');
                            $totalAmtDisplay = number_format($saleAmount, 2, '.', ',');
                        } else {
                            $proportion = ($totalSrp > 0) ? ($itemSubtotal / $totalSrp) : 0;
                            $itemPaid = round($saleAmount * $proportion);
                            $itemAmt = ($qty > 0) ? round($itemPaid / $qty) : 0;
                            $itemAmtDisplay = number_format($itemAmt, 2, '.', ',');
                            $totalAmtDisplay = number_format($itemPaid, 2, '.', ',');
                        }
                    } else {
                        if ($itemsCount === 1) {
                            // Single item: use actual_total_amount (loan total) for both ITEM AMOUNT and TOTAL AMOUNT
                            $itemAmtDisplay = number_format($saleAmount, 2, '.', ',');
                            $totalAmtDisplay = number_format($saleAmount, 2, '.', ',');
                        } else {
                            // For multiple items: first try per-item amount from payment_data,
                            // then fall back to proportional SRP distribution
                            $unitMapForCalc = (isset($paymentData['unit_payment_map']) && is_array($paymentData['unit_payment_map']))
                                ? $paymentData['unit_payment_map'] : [];
                            $perItemAmtPdf = getPerItemAmountPdf($item, $paymentData, $unitMapForCalc);
                            if ($perItemAmtPdf !== null) {
                                $itemTotal = round($perItemAmtPdf);
                                $itemAmt = ($qty > 0) ? round($perItemAmtPdf / $qty) : 0;
                                $itemAmtDisplay = number_format($itemAmt, 2, '.', ',');
                                $totalAmtDisplay = number_format($itemTotal, 2, '.', ',');
                            } else {
                                $totalSrpForMulti = 0.0;
                                foreach ($sale['items'] as $it) {
                                    $totalSrpForMulti += floatval($it['price'] ?? 0) * intval($it['quantity'] ?? 1);
                                }
                                $proportion = ($totalSrpForMulti > 0) ? ($itemSubtotal / $totalSrpForMulti) : 0;
                                $itemTotal = round($saleAmount * $proportion);
                                $itemAmt = ($qty > 0) ? round($itemTotal / $qty) : 0;
                                $itemAmtDisplay = number_format($itemAmt, 2, '.', ',');
                                $totalAmtDisplay = number_format($itemTotal, 2, '.', ',');
                            }
                        }
                    }
                }

                // Apply fitTextInCell to INVOICE NO to prevent exceeding border
                list($invoiceText, $invoiceFontSize) = fitTextInCell($pdf, $sale['invoice_no'], $wInvoice, 5, 3);
                $pdf->SetFont('Courier', '', $invoiceFontSize);
                $pdf->Cell($wInvoice, 6, $invoiceText, 1, 0, 'C');
                $pdf->SetFont('Courier', '', 5);

                $pdf->Cell($wQty, 6, $qty, 1, 0, 'C');

                $itemCodeTextRaw = !empty($item['item_code']) ? $item['item_code'] : ($item['item_description'] ?? '');
                list($itemCodeText, $itemCodeFontSize) = fitTextInCell($pdf, $itemCodeTextRaw, $wItemCode, 5, 3);
                $pdf->SetFont('Courier', '', $itemCodeFontSize);
                $pdf->Cell($wItemCode, 6, $itemCodeText, 1, 0, 'L');
                $pdf->SetFont('Courier', '', 5);

                $pdf->Cell($wSrp, 6, number_format($price, 2, '.', ','), 1, 0, 'C');

                // Apply fitTextInCell to prevent numeric values from exceeding borders
                list($oldUnitText, $oldUnitFontSize) = fitTextInCell($pdf, $oldUnitDisplay, $wOldUnit, 5, 3);
                $pdf->SetFont('Courier', '', $oldUnitFontSize);
                $pdf->Cell($wOldUnit, 6, $oldUnitText, 1, 0, 'C');
                $pdf->SetFont('Courier', '', 5);

                list($itemAmtText, $itemAmtFontSize) = fitTextInCell($pdf, $itemAmtDisplay, $wItemAmt, 5, 3);
                $pdf->SetFont('Courier', '', $itemAmtFontSize);
                $pdf->Cell($wItemAmt, 6, $itemAmtText, 1, 0, 'C');
                $pdf->SetFont('Courier', '', 5);

                list($totalAmtText, $totalAmtFontSize) = fitTextInCell($pdf, $totalAmtDisplay, $wTotalAmt, 5, 3);
                $pdf->SetFont('Courier', '', $totalAmtFontSize);
                $pdf->Cell($wTotalAmt, 6, $totalAmtText, 1, 0, 'C');
                $pdf->SetFont('Courier', '', 5);

                $commissionFormatted = number_format(floatval($sale['commission'] ?? 0), 2, '.', ',');
                list($commText, $commFontSize) = fitTextInCell($pdf, $commissionFormatted, $wComm, 5, 3);
                $pdf->SetFont('Courier', '', $commFontSize);
                $pdf->Cell($wComm, 6, $commText, 1, 0, 'C');
                $pdf->SetFont('Courier', '', 5);
                $pdf->Cell($wUpg, 6, $upgradeCellText, 1, 0, 'C');
                $pdf->Cell($wStat, 6, $stat, 1, 0, 'C');
                $pdf->Cell($wSp, 6, $assistedByAbbr, 1, 0, 'C');
                $pdf->Cell($wEn, 6, $encoderAbbr, 1, 0, 'C');

                // Resolve per-item payment method using unit_payment_map (same logic as report.php)
                $itemPaymentMethod = $paymentMethod; // default: whole-sale method
                $unitMap = (isset($paymentData['unit_payment_map']) && is_array($paymentData['unit_payment_map']))
                    ? $paymentData['unit_payment_map'] : [];

                if (!empty($unitMap)) {
                    $imei = strtoupper(trim((string) ($item['imei'] ?? '')));
                    $desc = strtoupper(trim((string) (($item['item_description'] ?? '') ?: ($item['item_code'] ?? ''))));
                    $matchedMethod = null;

                    // Pass 1: exact full key match "DESCRIPTION (IMEI)"
                    if ($imei && $desc) {
                        $fullKey = $desc . ' (' . $imei . ')';
                        foreach ($unitMap as $key => $method) {
                            if (strtoupper((string) $key) === $fullKey) {
                                $matchedMethod = (string) $method;
                                break;
                            }
                        }
                    }

                    // Pass 2: IMEI match across all keys
                    if ($matchedMethod === null && $imei) {
                        foreach ($unitMap as $key => $method) {
                            $keyUpper = strtoupper((string) $key);
                            if (strpos($keyUpper, $imei) !== false) {
                                $matchedMethod = (string) $method;
                                break;
                            }
                        }
                    }

                    // Pass 3: description prefix match (least specific)
                    if ($matchedMethod === null && $desc) {
                        foreach ($unitMap as $key => $method) {
                            $keyUpper = strtoupper((string) $key);
                            if (strpos($keyUpper, $desc) === 0) {
                                $matchedMethod = (string) $method;
                                break;
                            }
                        }
                    }

                    if ($matchedMethod !== null) {
                        $itemPaymentMethod = $matchedMethod;
                    }
                }

                list($paymentText, $paymentFontSize) = fitTextInCell($pdf, $itemPaymentMethod, $wPayment, 5, 3);
                $pdf->SetFont('Courier', '', $paymentFontSize);
                $pdf->Cell($wPayment, 6, $paymentText, 1, 1, 'C');
            }
        } else {
            $isUpgradeSale = ($sale['upgrade'] === 'UPGD');
            $saleAmtForNoItems = floatval($sale['actual_total_amount'] ?? $sale['total_amount'] ?? 0);
            $oldUnitValue = $isUpgradeSale ? number_format(floatval($sale['old_unit_amount'] ?? 0), 2, '.', ',') : '0.00';

            // Apply fitTextInCell to INVOICE NO to prevent exceeding border
            list($invoiceText, $invoiceFontSize) = fitTextInCell($pdf, $sale['invoice_no'], $wInvoice, 5, 3);
            $pdf->SetFont('Courier', '', $invoiceFontSize);
            $pdf->Cell($wInvoice, 6, $invoiceText, 1, 0, 'C');
            $pdf->SetFont('Courier', '', 5);

            $pdf->Cell($wQty, 6, $sale['total_qty'], 1, 0, 'C');
            $pdf->Cell($wItemCode, 6, 'No Items', 1, 0, 'C');
            $pdf->Cell($wSrp, 6, '0.00', 1, 0, 'C');

            // Apply fitTextInCell to prevent numeric values from exceeding borders
            list($oldUnitText, $oldUnitFontSize) = fitTextInCell($pdf, $oldUnitValue, $wOldUnit, 5, 3);
            $pdf->SetFont('Courier', '', $oldUnitFontSize);
            $pdf->Cell($wOldUnit, 6, $oldUnitText, 1, 0, 'C');
            $pdf->SetFont('Courier', '', 5);

            $pdf->Cell($wItemAmt, 6, '0.00', 1, 0, 'C');

            $totalAmt = $isUpgradeSale ? ($saleAmtForNoItems + floatval($sale['old_unit_amount'] ?? 0)) : $saleAmtForNoItems;
            $totalAmtFormatted = number_format($totalAmt, 2, '.', ',');
            list($totalAmtText, $totalAmtFontSize) = fitTextInCell($pdf, $totalAmtFormatted, $wTotalAmt, 5, 3);
            $pdf->SetFont('Courier', '', $totalAmtFontSize);
            $pdf->Cell($wTotalAmt, 6, $totalAmtText, 1, 0, 'C');
            $pdf->SetFont('Courier', '', 5);

            $commissionFormatted = number_format(floatval($sale['commission'] ?? 0), 2, '.', ',');
            list($commText, $commFontSize) = fitTextInCell($pdf, $commissionFormatted, $wComm, 5, 3);
            $pdf->SetFont('Courier', '', $commFontSize);
            $pdf->Cell($wComm, 6, $commText, 1, 0, 'C');
            $pdf->SetFont('Courier', '', 5);
            $pdf->Cell($wUpg, 6, $isUpgradeSale ? 'UPGD' : '', 1, 0, 'C');
            $pdf->Cell($wStat, 6, $stat, 1, 0, 'C');
            $pdf->Cell($wSp, 6, $assistedByAbbr, 1, 0, 'C');
            $pdf->Cell($wEn, 6, $encoderAbbr, 1, 0, 'C');

            list($paymentText, $paymentFontSize) = fitTextInCell($pdf, $paymentMethod, $wPayment, 5, 3);
            $pdf->SetFont('Courier', '', $paymentFontSize);
            $pdf->Cell($wPayment, 6, $paymentText, 1, 1, 'C');
        }
    }
}

$pdf->SetTextColor(0, 0, 0);

// Calculate breakdown
$groupedByEncoder = [];
$totalUnits = 0;
$grandTotalAmount = 0;
$totalNonCashPayment = 0;
$nonCashBreakdown = [];
$totalCommissions = 0;
$totalUpgrade = 0;
$totalOldUnit = 0;
$totalCash = 0;
$totalVoid = 0;
$totalRefund = 0;

foreach ($sales as $sale) {
    $encoderName = $sale['encoder'] ? $sale['encoder'] : 'Unknown';
    $isVoided = ($sale['status'] === 'voided');
    $isRefunded = ($sale['display_status'] === 'refunded');
    $rawSaleAmount = floatval($sale['actual_total_amount'] ?? $sale['total_amount'] ?? 0);
    $oldUnitAmount = floatval($sale['old_unit_amount'] ?? 0);
    $discountAmount = floatval($sale['discount'] ?? 0);

    // Only NEW upgrade invoices (with original_invoice_no) use cash paid display
    $isNewUpgradeSale = (($sale['upgrade'] ?? '') === 'UPGD' && !empty($sale['original_invoice_no']));

    $saleAmount = $rawSaleAmount;
    if ($isNewUpgradeSale) {
        // Extract cash paid from payment_data.Amount (most reliable)
        $cashPaid = 0;
        if (!empty($sale['payment_data'])) {
            $pdArr = json_decode($sale['payment_data'], true);
            $amtRaw = $pdArr['Amount'] ?? $pdArr['amount'] ?? '';
            $parsedAmt = floatval(str_replace(',', '', (string)$amtRaw));
            if ($parsedAmt > 0) $cashPaid = $parsedAmt;
        }
        $saleAmount = $cashPaid > 0 ? $cashPaid : max(0, $rawSaleAmount - $discountAmount);
    }

    $saleQty = intval($sale['total_qty']);
    $saleDisplayedTotal = 0;

    // Match saleDisplayedTotal logic in report.php (JavaScript)
    if (isset($sale['items']) && count($sale['items']) > 0) {
        $shownUpgradeTotals = false;
        foreach ($sale['items'] as $idx => $item) {
            if (!$shownUpgradeTotals) {
                $saleDisplayedTotal += $saleAmount;
                $shownUpgradeTotals = true;
            }
        }
    } else {
        $saleDisplayedTotal = $saleAmount;
    }

    if (!isset($groupedByEncoder[$encoderName])) {
        $groupedByEncoder[$encoderName] = [
            'quantity' => 0,
            'totalAmount' => 0
        ];
    }

    if (!$isVoided) {
        $groupedByEncoder[$encoderName]['quantity'] += $saleQty;
        $groupedByEncoder[$encoderName]['totalAmount'] += $saleDisplayedTotal;

        $totalUnits += $saleQty;
        $grandTotalAmount += $saleDisplayedTotal;
    }

    // Parse payment data for cash/non-cash calculation (align with report.php JavaScript rules)
    if ($sale['payment_data']) {
        $paymentData = json_decode($sale['payment_data'], true);
        $rawPaymentType = $paymentData['payment_type'] ?? '';
        $paymentTypeLower = strtolower((string) $rawPaymentType);

        $partnerMap = [
            'partner1' => 'Skyro',
            'partner2' => 'Home Credit',
            'partner3' => 'Sumisho',
            'partner4' => 'AEON Credit',
            'partner5' => 'Salmon',
            'partner6' => 'Samsung Finances',
            'partner7' => 'Payjoy',
            'partner8' => 'Billease',
            'partner9' => 'Paymongo',
            'partner10' => 'Skyro',
            'partner11' => 'Fundline',
            'partner12' => 'Flexi Finance'
        ];
        $normalizePartnerName = function ($p) use ($partnerMap) {
            if (!$p) return '';
            $pl = strtolower(trim((string) $p));
            return $partnerMap[$pl] ?? $p;
        };

        if (!$rawPaymentType || $paymentTypeLower === 'cash') {
            // Pure cash
            $totalCash += $saleAmount;
        } elseif ($paymentTypeLower === 'multiple' && !empty($paymentData['payments']) && is_array($paymentData['payments'])) {
            // Multiple payment methods array (e.g. preorder/claimed preorders)
            foreach ($paymentData['payments'] as $p) {
                $pType = strtolower((string) ($p['payment_type'] ?? ''));
                $pAmt = floatval(str_replace(',', '', (string) ($p['amount'] ?? 0)));
                if ($pAmt <= 0) continue;
                if ($pType === 'cash') {
                    $totalCash += $pAmt;
                } else {
                    $pLabel = $p['payment_method'] ?? $pType;
                    if ($pType === 'payment_partners' || !empty($p['payment_partner'])) {
                        $pLabel = $normalizePartnerName($p['payment_partner'] ?? $pLabel);
                    } elseif ($pType === 'ewallet') {
                        $pLabel = $p['ewallet_type'] ?? 'E-Wallet';
                    } elseif ($pType === 'online_banking') {
                        $pLabel = $p['bank_name'] ?? 'Online Banking';
                    }
                    $labelUpper = strtoupper((string) $pLabel);
                    if (!isset($nonCashBreakdown[$labelUpper])) {
                        $nonCashBreakdown[$labelUpper] = 0;
                    }
                    $nonCashBreakdown[$labelUpper] += $pAmt;
                    $totalNonCashPayment += $pAmt;
                }
            }
        } else {
            $specificType = (string) $rawPaymentType;
            if (!empty($paymentData['E-Wallet-Text']) && strpos($specificType, 'E-Wallet') !== false) {
                $specificType = str_replace('E-Wallet', $paymentData['E-Wallet-Text'], $specificType);
            }
            if (!empty($paymentData['Bank-Text']) && strpos($specificType, 'Online Banking') !== false) {
                $specificType = str_replace('Online Banking', $paymentData['Bank-Text'], $specificType);
            }
            if (!empty($paymentData['payment_partner']) || stripos($specificType, 'payment_partners') !== false || stripos($specificType, 'partner') === 0) {
                $mapped = $normalizePartnerName($paymentData['payment_partner'] ?? $specificType);
                $specificType = preg_replace('/payment_partners/i', $mapped, $specificType);
                $specificType = preg_replace('/partner\d+/i', $mapped, $specificType);
                if (!$specificType || $specificType === (string) $rawPaymentType) $specificType = $mapped;
            }

            $methodParts = array_values(array_filter(array_map('trim', explode(' + ', (string) $rawPaymentType)), function ($s) {
                return $s !== '';
            }));

            $hasCash = false;
            foreach ($methodParts as $m) {
                if (strtolower($m) === 'cash') {
                    $hasCash = true;
                    break;
                }
            }

            $resolveMethodLabel = function ($m) use ($paymentData, $normalizePartnerName) {
                if (!$m) return '';
                if ($m === 'E-Wallet' && !empty($paymentData['E-Wallet-Text']))
                    return $paymentData['E-Wallet-Text'];
                if ($m === 'Online Banking' && !empty($paymentData['Bank-Text']))
                    return $paymentData['Bank-Text'];
                if (stripos($m, 'payment_partners') !== false || stripos($m, 'partner') === 0) {
                    return $normalizePartnerName($paymentData['payment_partner'] ?? $m);
                }
                return $normalizePartnerName($m);
            };

            // Try unit_payment_map first (most accurate per-unit amounts)
            $unitMap = (isset($paymentData['unit_payment_map']) && is_array($paymentData['unit_payment_map']))
                ? $paymentData['unit_payment_map'] : null;

            if ($unitMap && !empty($sale['items']) && count($sale['items']) > 0) {
                $methodAmounts = [];

                // Use actual_total_amount (loan total) instead of item prices
                $saleActualTotal = floatval($sale['actual_total_amount'] ?? $sale['total_amount']);
                $totalSrp = 0.0;
                foreach ($sale['items'] as $it) {
                    $totalSrp += (float) ($it['price'] ?? 0) * (int) ($it['quantity'] ?? 1);
                }

                foreach ($sale['items'] as $item) {
                    $imei = strtoupper(trim((string) ($item['imei'] ?? '')));
                    $desc = strtoupper(trim((string) (($item['item_description'] ?? '') ?: ($item['item_code'] ?? ''))));
                    $matchedMethod = null; // reset for each item

                    // Pass 1: Try IMEI match against all unitMap keys first
                    if ($imei) {
                        foreach ($unitMap as $key => $method) {
                            $keyUpper = strtoupper((string) $key);
                            if (strpos($keyUpper, $imei) !== false) {
                                $matchedMethod = (string) $method;
                                break;
                            }
                        }
                    }

                    // Pass 2: If no IMEI match found, try description match
                    if ($matchedMethod === null && $desc) {
                        foreach ($unitMap as $key => $method) {
                            $keyUpper = strtoupper((string) $key);
                            if (strpos($keyUpper, $desc) === 0) {
                                $matchedMethod = (string) $method;
                                break;
                            }
                        }
                    }

                    // Determine if all mapped methods are loan-type
                    $isLoanMethodFn = function($m) {
                        $ml = strtolower((string)($m ?? ''));
                        return str_contains($ml, 'home credit') || str_contains($ml, 'salmon') ||
                               str_contains($ml, 'sumisho') || str_contains($ml, 'payjoy') ||
                               str_contains($ml, 'billease') || str_contains($ml, 'paymongo') ||
                               str_contains($ml, 'skyro') || str_contains($ml, 'samsung') ||
                               str_contains($ml, 'cebu') || str_contains($ml, 'partner') ||
                               str_contains($ml, 'makati');
                    };
                    $allMapMethodsAreLoan = !empty($unitMap) && array_reduce(
                        array_values($unitMap),
                        fn($carry, $m) => $carry && $isLoanMethodFn($m),
                        true
                    );

                    if ($matchedMethod === null) {
                        // Item not in map — assume Cash portion for mixed loan+cash
                        $pt = strtolower((string)($paymentData['payment_type'] ?? ''));
                        $hasCashPt = str_contains($pt, 'cash');
                        $hasLoanPt = $isLoanMethodFn($pt) || isset($paymentData['Loan Balance']) || isset($paymentData['totalLoanAmount']);
                        if ($hasCashPt && $hasLoanPt && $allMapMethodsAreLoan) {
                            $cashAmtRaw = (string)($paymentData['Amount'] ?? $paymentData['cash_amount'] ?? '');
                            $cashAmt = floatval(str_replace(',', '', trim($cashAmtRaw)));
                            if ($cashAmt > 0) {
                                $matchedMethod = 'Cash';
                                $itemAmt = round($cashAmt * (int)($item['quantity'] ?? 1));
                                if (!isset($methodAmounts[$matchedMethod])) $methodAmounts[$matchedMethod] = 0;
                                $methodAmounts[$matchedMethod] += $itemAmt;
                                continue;
                            }
                        }
                        $fallback = $methodParts[0] ?? '';
                        $matchedMethod = (string) $resolveMethodLabel($fallback);
                    }

                    // Determine per-item amount: loan items use Loan Balance + DP, cash items use Amount
                    if ($isLoanMethodFn($matchedMethod)) {
                        $lbRaw = $paymentData['Loan Balance'] ?? $paymentData['loan_balance'] ?? $paymentData['totalLoanAmount'] ?? $paymentData['total_loan_amount'] ?? 0;
                        $loanTotal = floatval(str_replace(',', '', (string)$lbRaw));
                        $dpKeys = ['cash_down_payment_amount', 'cash_dp_amount', 'gcash_down_payment_amount',
                                   'gcash_dp_amount', 'maya_down_payment_amount', 'maya_dp_amount'];
                        foreach ($dpKeys as $dk) {
                            if (isset($paymentData[$dk])) {
                                $dpParts = explode('|', str_replace(',', '', (string)$paymentData[$dk]));
                                $dpVal = floatval(trim($dpParts[0]));
                                if ($dpVal > 0) { $loanTotal += $dpVal; break; }
                            }
                        }
                        $itemAmt = ($loanTotal > 0) ? round($loanTotal * (int)($item['quantity'] ?? 1)) : 0;
                    } elseif (strtolower($matchedMethod) === 'cash') {
                        $cashAmtRaw = (string)($paymentData['Amount'] ?? $paymentData['cash_amount'] ?? '');
                        $cashAmt = floatval(str_replace(',', '', trim($cashAmtRaw)));
                        $itemAmt = ($cashAmt > 0) ? round($cashAmt * (int)($item['quantity'] ?? 1)) : 0;
                    } else {
                        // Fallback: proportional SRP distribution
                        $itemSrp = (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
                        $proportion = ($totalSrp > 0) ? ($itemSrp / $totalSrp) : 0;
                        $itemAmt = round($saleActualTotal * $proportion);
                    }

                    if (!isset($methodAmounts[$matchedMethod]))
                        $methodAmounts[$matchedMethod] = 0;
                    $methodAmounts[$matchedMethod] += $itemAmt;
                }

                foreach ($methodAmounts as $method => $amt) {
                    if (strtolower((string) $method) === 'cash') {
                        $totalCash += $amt;
                    } else {
                        $totalNonCashPayment += $amt;
                        $labelUpper = strtoupper((string) $method);
                        if (!isset($nonCashBreakdown[$labelUpper]))
                            $nonCashBreakdown[$labelUpper] = 0;
                        $nonCashBreakdown[$labelUpper] += $amt;
                    }
                }
            } elseif ($hasCash && count($methodParts) > 1) {
                // Split payment containing cash: allocate from pipe-separated Amount/Total
                $amountRaw = $paymentData['Amount'] ?? ($paymentData['Total'] ?? '');
                $amountParts = array_map(function ($s) {
                    return floatval(str_replace(',', '', trim($s)));
                }, explode('|', (string) $amountRaw));

                $cashPortion = 0;
                foreach ($methodParts as $idx => $m) {
                    $amt = $amountParts[$idx] ?? 0;
                    if (strtolower($m) === 'cash') {
                        $cashPortion += $amt;
                    } else {
                        $label = strtoupper((string) $resolveMethodLabel($m));
                        if (!isset($nonCashBreakdown[$label]))
                            $nonCashBreakdown[$label] = 0;
                        $nonCashBreakdown[$label] += $amt;
                        $totalNonCashPayment += $amt;
                    }
                }

                // Fallback if amounts couldn't be parsed
                if ($cashPortion === 0 && empty($nonCashBreakdown)) {
                    $totalNonCashPayment += $saleAmount;
                    $fallbackMethod = $methodParts[0] ?? '';
                    $fallbackLabel = strtoupper((string) $resolveMethodLabel($fallbackMethod));
                    if (!isset($nonCashBreakdown[$fallbackLabel]))
                        $nonCashBreakdown[$fallbackLabel] = 0;
                    $nonCashBreakdown[$fallbackLabel] += $saleAmount;
                }

                $totalCash += $cashPortion;
            } elseif (count($methodParts) > 1) {
                // Pure non-cash split (no cash): allocate from pipe-separated Amount/Total
                $amountRaw = $paymentData['Amount'] ?? ($paymentData['Total'] ?? '');
                $amountParts = array_map(function ($s) {
                    return floatval(str_replace(',', '', trim($s)));
                }, explode('|', (string) $amountRaw));

                $totalParsed = 0;
                foreach ($amountParts as $ap) {
                    $totalParsed += $ap;
                }

                if ($totalParsed > 0) {
                    foreach ($methodParts as $idx => $m) {
                        $amt = $amountParts[$idx] ?? 0;
                        if ($amt > 0) {
                            $label = strtoupper((string) $resolveMethodLabel($m));
                            if (!isset($nonCashBreakdown[$label]))
                                $nonCashBreakdown[$label] = 0;
                            $nonCashBreakdown[$label] += $amt;
                            $totalNonCashPayment += $amt;
                        }
                    }
                } else {
                    // Fallback: split evenly
                    $share = (count($methodParts) > 0) ? ($saleAmount / count($methodParts)) : $saleAmount;
                    foreach ($methodParts as $m) {
                        $label = strtoupper((string) $resolveMethodLabel($m));
                        if (!isset($nonCashBreakdown[$label]))
                            $nonCashBreakdown[$label] = 0;
                        $nonCashBreakdown[$label] += $share;
                        $totalNonCashPayment += $share;
                    }
                }
            } else {
                // Single non-cash method
                $totalNonCashPayment += $saleAmount;
                $specificTypeUpper = strtoupper((string) $resolveMethodLabel($specificType));
                if (!isset($nonCashBreakdown[$specificTypeUpper])) {
                    $nonCashBreakdown[$specificTypeUpper] = 0;
                }
                $nonCashBreakdown[$specificTypeUpper] += $saleAmount;
            }
        }
    } else {
        // If no payment data, assume cash
        $totalCash += $saleAmount;
    }

    // Add commissions
    if (isset($sale['commission'])) {
        $totalCommissions += floatval($sale['commission']);
    }

    // Accumulate upgrade amount (balance paid) for UPGD sales
    if ($isNewUpgradeSale) {
        $totalUpgrade += $saleAmount;
        $totalOldUnit += $oldUnitAmount;
    }

    // Add void and refund amounts
    if ($isVoided) {
        $totalVoid += $saleDisplayedTotal;
    } else {
        $totalRefund += floatval($sale['refund_amount'] ?? 0);
    }
}

// Keep NET SALES aligned with GRAND TOTAL AMOUNT basis shown in this report.
// Void/refund remain visible as separate lines.
$netSales = $grandTotalAmount;

$pdf->Ln(5);
$startY = $pdf->GetY();

// Calculate commission breakdown
$commissionBreakdown = [];
foreach ($sales as $sale) {
    if (isset($sale['commission'])) {
        $encoderName = $sale['encoder'] ? $sale['encoder'] : 'Unknown';
        $normalizedName = preg_replace('/\s+/', ' ', trim(strtoupper($encoderName)));
        if (!isset($commissionBreakdown[$normalizedName])) {
            $commissionBreakdown[$normalizedName] = 0;
        }
        $commissionBreakdown[$normalizedName] += floatval($sale['commission']);
    }
}

// Draw Commission Breakdown Box on the right side
$boxX = 140;
$boxY = $startY;
$boxW = 55;
$pdf->SetXY($boxX, $boxY);
$pdf->SetFont('Courier', 'B', 8);
$pdf->SetTextColor(0, 0, 0);

// Box Title
$pdf->Cell($boxW, 5, 'COMMISSION BREAKDOWNS', 1, 2, 'C');

// Box Content
$contentY = $pdf->GetY();
$boxH = count($commissionBreakdown) * 4 + 4; // Add some padding
$pdf->Rect($boxX, $contentY, $boxW, $boxH);

// Add items inside the box
$pdf->SetXY($boxX + 2, $contentY + 2);
foreach ($commissionBreakdown as $name => $amount) {
    if (strlen($name) > 16) {
        $name = substr($name, 0, 14) . '..';
    }
    $pdf->Cell(32, 4, $name, 0, 0, 'L');
    $pdf->Cell(19, 4, number_format($amount, 2), 0, 1, 'R');
    $pdf->SetX($boxX + 2);
}

// Draw Unclaimed Breakdowns Box below Commission box (only if there are records)
if (count($unclaimed_breakdowns) > 0) {
    // Position below the commission breakdown box
    $breakdownsBoxX = $boxX; // Same X position as commission box
    $breakdownsBoxY = $contentY + $boxH + 5; // Below commission box with 5mm gap
    $breakdownsBoxW = $boxW; // Same width as commission box (55mm)
    $pdf->SetXY($breakdownsBoxX, $breakdownsBoxY);
    $pdf->SetFont('Courier', 'B', 7);
    $pdf->SetTextColor(0, 0, 0);

    // Box Title
    $pdf->Cell($breakdownsBoxW, 5, 'UNCLAIMED BREAKDOWNS', 1, 2, 'C');

    // Calculate box height - limit to max 10 records for PDF space (each record takes 5 lines + divider)
    $displayRecords = array_slice($unclaimed_breakdowns, 0, 10);
    $breakdownsContentY = $pdf->GetY();
    $recordHeight = 16; // Each record takes 5 lines x 3mm + divider
    $breakdownsBoxH = count($displayRecords) * $recordHeight + 4;
    $pdf->Rect($breakdownsBoxX, $breakdownsContentY, $breakdownsBoxW, $breakdownsBoxH);

    // Add records inside the box (multi-line format for complete display)
    $pdf->SetXY($breakdownsBoxX + 1, $breakdownsContentY + 2);
    $pdf->SetFont('Courier', '', 5);

    $recordCount = count($displayRecords);
    $currentIndex = 0;

    foreach ($displayRecords as $record) {
        $currentIndex++;
        $isPreorder = (($record['type'] ?? '') === 'preorder');
        $statusColor = ($record['status'] === 'unclaimed') ? [211, 47, 47] : [46, 125, 50];
        
        if ($record['status'] === 'unclaimed') {
            $statusText = $isPreorder ? 'UNCLAIMED PRE-ORDER' : 'UNCLAIMED FREEBIES';
        } else {
            $statusText = $isPreorder ? 'CLAIMED PRE-ORDER' : 'CLAIMED FREEBIES';
        }

        // Complete invoice number
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($breakdownsBoxW - 2, 3, 'INV: ' . $record['invoice_number'], 0, 1, 'L');
        $pdf->SetX($breakdownsBoxX + 1);

        // Complete item code
        $pdf->Cell($breakdownsBoxW - 2, 3, 'ITEM: ' . $record['item_code'], 0, 1, 'L');
        $pdf->SetX($breakdownsBoxX + 1);

        // Quantity and Branch
        $pdf->Cell(25, 3, 'QTY: ' . $record['quantity'], 0, 0, 'L');
        $pdf->Cell(26, 3, 'BR: ' . $record['branch'], 0, 1, 'L');
        $pdf->SetX($breakdownsBoxX + 1);

        // Status with color
        $pdf->SetTextColor($statusColor[0], $statusColor[1], $statusColor[2]);
        $pdf->Cell($breakdownsBoxW - 2, 3, 'STATUS: ' . $statusText, 0, 1, 'L');
        $pdf->SetX($breakdownsBoxX + 1);

        // Date (Unclaimed Date or Claimed Date)
        $pdf->SetTextColor(0, 0, 0);
        if ($record['status'] === 'unclaimed') {
            $dateVal = (!empty($record['created_at']) && $record['created_at'] != '0000-00-00 00:00:00')
                ? date('m/d/Y h:i A', strtotime($record['created_at']))
                : 'N/A';
            $pdf->Cell($breakdownsBoxW - 2, 3, 'UNCLAIMED DATE: ' . $dateVal, 0, 1, 'L');
        } else {
            $dateRaw = (!empty($record['claimed_at']) && $record['claimed_at'] != '0000-00-00 00:00:00')
                ? $record['claimed_at']
                : $record['created_at'];
            $dateVal = (!empty($dateRaw) && $dateRaw != '0000-00-00 00:00:00')
                ? date('m/d/Y h:i A', strtotime($dateRaw))
                : 'N/A';
            $pdf->Cell($breakdownsBoxW - 2, 3, 'CLAIMED DATE: ' . $dateVal, 0, 1, 'L');
        }
        $pdf->SetX($breakdownsBoxX + 1);

        // Divider line between items (except after the last item)
        if ($currentIndex < $recordCount) {
            $lineY = $pdf->GetY() + 0.5;
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->Line($breakdownsBoxX + 1, $lineY, $breakdownsBoxX + $breakdownsBoxW - 1, $lineY);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetXY($breakdownsBoxX + 1, $lineY + 1);
        } else {
            $pdf->Ln(0.5);
            $pdf->SetX($breakdownsBoxX + 1);
        }
    }

    // Show count if more records exist
    if (count($unclaimed_breakdowns) > 10) {
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Courier', 'I', 5);
        $pdf->Cell($breakdownsBoxW - 2, 3, '... +' . (count($unclaimed_breakdowns) - 10) . ' more', 0, 1, 'C');
    }
}

// Restore Y and X position for the left side rendering
$pdf->SetXY(15, $startY);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Courier', 'B', 8);

foreach ($groupedByEncoder as $encoderName => $data) {
    // Normalize spacing - replace multiple spaces with single space
    $normalizedName = preg_replace('/\s+/', ' ', trim(strtoupper($encoderName)));
    // Combine name, quantity, and MOTORCYCLE in one compact string on the left
    $leftText = $normalizedName . ' ' . $data['quantity'] . ' MOTORCYCLE';
    $pdf->Cell(40, 5, $leftText, 0, 0, 'L');
    // Amount aligned to match the red summary values (same position as "794,700.00" below)
    $pdf->Cell(30, 5, number_format($data['totalAmount'], 2), 0, 1, 'R');
}

$pdf->Ln(5);

// Move Y cursor below the max height of the side-by-side elements if needed
$currentY = $pdf->GetY();
$boxBottomY = $contentY + $boxH;
if ($boxBottomY > $currentY) {
    // No need to adjust Y here for left panel since Summary fits neatly
// But be careful not to overlap if left side is very short
}

// Summary
$pdf->SetFont('Courier', 'B', 8);

// UNIT QUANTITY - RED
$pdf->SetTextColor(211, 47, 47);
$pdf->Cell(40, 5, 'UNIT QUANTITY:', 0, 0, 'L');
$pdf->Cell(30, 5, $totalUnits, 0, 1, 'R');

// GRAND TOTAL AMOUNT - RED
$pdf->Cell(40, 5, 'GRAND TOTAL AMOUNT:', 0, 0, 'L');
$pdf->Cell(30, 5, number_format($grandTotalAmount, 2), 0, 1, 'R');

$pdf->Ln(4);

// NON-CASH PAYMENTS - RED
$pdf->Cell(40, 5, 'NON-CASH PAYMENTS:', 0, 1, 'L');
foreach ($nonCashBreakdown as $method => $amount) {
    $pdf->Cell(4, 5, '', 0, 0, 'L');
    $pdf->Cell(36, 5, $method, 0, 0, 'L');
    $pdf->Cell(30, 5, number_format($amount, 2), 0, 1, 'R');
}

// CASH PAYMENT - RED
$pdf->Cell(40, 5, 'CASH PAYMENT:', 0, 0, 'L');
$pdf->Cell(30, 5, number_format($totalCash, 2), 0, 1, 'R');
$pdf->Cell(40, 5, 'COMMISSIONS:', 0, 0, 'L');
$pdf->Cell(30, 5, number_format($totalCommissions, 2), 0, 1, 'R');

// UPGRADE - RED
$pdf->Cell(40, 5, 'UPGRADE:', 0, 0, 'L');
$pdf->Cell(30, 5, number_format($totalUpgrade, 2), 0, 1, 'R');

// VOID - RED
$pdf->Cell(40, 5, 'VOID:', 0, 0, 'L');
$pdf->Cell(30, 5, number_format($totalVoid, 2), 0, 1, 'R');

// REFUND - RED
$pdf->Cell(40, 5, 'REFUND:', 0, 0, 'L');
$pdf->Cell(30, 5, number_format($totalRefund, 2), 0, 1, 'R');

// NET SALES - RED
$pdf->Cell(40, 5, 'NET SALES:', 0, 0, 'L');
$pdf->Cell(30, 5, number_format($netSales, 2), 0, 1, 'R');

// Ensure the cursor returns below whichever is taller
$finalY = max($pdf->GetY(), $boxBottomY);
$pdf->SetY($finalY);

$pdf->Ln(10);

// Cashier's Signature
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Courier', 'B', 9);
$pdf->Line(15, $pdf->GetY(), 65, $pdf->GetY()); // Signature line
$pdf->Ln(2);
$pdf->Cell(50, 6, "CASHIER'S SIGNATURE", 0, 1, 'L');

// Output PDF
$pdf->Output('I', 'Daily_Sales_' . $date_from . '_' . $branch . '.pdf');
?>