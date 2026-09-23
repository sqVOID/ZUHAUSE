<?php
require_once 'session_check.php';
// Suppress all output before JSON response
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';
session_start();

// Clean any output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS voucher_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS token_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if JSON decode was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    // Clean any output buffer before sending JSON
    if (ob_get_length())
        ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data received: ' . json_last_error_msg()]);
    exit;
}

// Validate required fields
$date_from = !empty($data['date_from']) ? $data['date_from'] : (!empty($data['date']) ? $data['date'] : '');
$date_to = !empty($data['date_to']) ? $data['date_to'] : $date_from;
$search_branch = !empty($data['branch']) ? $data['branch'] : '';

if (empty($date_from) || empty($search_branch)) {
    // Clean any output buffer before sending JSON
    if (ob_get_length())
        ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Date and branch are required']);
    exit;
}

try {
    // First, let's check if there are any sales entries at all
    $count_query = $conn->query("SELECT COUNT(*) as total FROM sales_entry");
    $count_result = $count_query->fetch_assoc();

    // Get branch code from branch name
    $branch_code = '';
    if (!empty($search_branch)) {
        $branch_query = $conn->prepare("SELECT branch_code FROM branches WHERE branch_name = ?");
        $branch_query->bind_param("s", $search_branch);
        $branch_query->execute();
        $branch_result = $branch_query->get_result();
        if ($branch_result && $branch_result->num_rows > 0) {
            $branch_data = $branch_result->fetch_assoc();
            $branch_code = $branch_data['branch_code'];
        }
        $branch_query->close();
    }

    // Fetch sales entries for the specified date range and branch (including refunded invoices)
    $sales_query = $conn->prepare("
        SELECT 
            se.id,
            se.invoice_no,
            se.first_name,
            se.last_name,
            se.assisted_by,
            se.remarks,
            se.total_qty,
            se.total_amount,
            se.discount,
            se.voucher_amount,
            se.token,
            se.commission,
            se.payment_data,
            se.encoder,
            se.branch_code,
            se.created_at,
            se.status,
            se.upgrade,
            se.original_invoice_no,
            COALESCE((
                SELECT SUM(uoi.price)
                FROM upgrades u
                JOIN upgrade_old_items uoi ON uoi.upgrade_id = u.id
                WHERE u.new_invoice_no = se.invoice_no
            ), 0) AS old_unit_amount,
            (
                SELECT u.payment_data
                FROM upgrades u
                WHERE (u.original_invoice_no = se.invoice_no OR u.new_invoice_no = se.invoice_no)
                ORDER BY u.id DESC
                LIMIT 1
            ) AS upgrade_payment_data,
            se.void_reason,
            se.voided_at,
            se.voided_by,
            r.total_amount as refund_amount,
            se.status as display_status,
            se.page_type,
            se.promo_id
        FROM sales_entry se 
        LEFT JOIN refunds r ON r.invoice_no = se.invoice_no
        WHERE DATE(se.created_at) BETWEEN ? AND ?
          AND se.branch_code = ?
          AND (se.page_type != 'claimpreorder' OR se.page_type IS NULL)
        ORDER BY se.created_at ASC
    ");

    $sales_query->bind_param("sss", $date_from, $date_to, $branch_code);
    $sales_query->execute();
    $sales_result = $sales_query->get_result();

    $sales_data = [];
    $last_invoice = '';

    if ($sales_result && $sales_result->num_rows > 0) {
        while ($sale = $sales_result->fetch_assoc()) {

            // Get items for this sale
            $items_query = $conn->prepare("
                SELECT 
                    sei.item_description,
                    sei.item_code,
                    sei.imei,
                    sei.quantity,
                    sei.price,
                    sei.is_promo_item,
                    COALESCE(
                        NULLIF(sei.voucher_amount, 0),
                        CASE WHEN i.has_voucher = 1 THEN COALESCE(i.voucher_amount, 0) ELSE 0 END,
                        0
                    ) AS voucher_amount,
                    COALESCE(
                        NULLIF(sei.token_amount, 0),
                        CASE WHEN i.has_token = 1 THEN COALESCE(i.token_amount, 0) ELSE 0 END,
                        0
                    ) AS token_amount,
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
                LEFT JOIN items i ON i.item_code = sei.item_code
                WHERE sei.sales_entry_id = ?
                ORDER BY sei.id
            ");
            $items_query->bind_param("sssi", $sale['invoice_no'], $sale['invoice_no'], $sale['invoice_no'], $sale['id']);
            $items_query->execute();
            $items_result = $items_query->get_result();

            $items = [];
            if ($items_result && $items_result->num_rows > 0) {
                while ($item = $items_result->fetch_assoc()) {
                    $items[] = $item;
                }
            }
            $items_query->close();

            // Get payment_data to check for loan information and calculate proper total
            $pd = [];
            if (!empty($sale['payment_data'])) {
                $pd = json_decode($sale['payment_data'], true);
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

            // Get the actual total amount (loan total if it's a loan, otherwise use sale total_amount)
            $actual_total_amount = floatval($sale['total_amount']);
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

            $sale['actual_total_amount'] = $actual_total_amount;
            $sale['items'] = $items;
            $sales_data[] = $sale;

            // Track last invoice
            if (empty($last_invoice) || $sale['invoice_no'] > $last_invoice) {
                $last_invoice = $sale['invoice_no'];
            }
        }
    }
    $sales_query->close();

    // ── Include preorder payment transactions in report ────────────────────────
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
            p.created_at       AS preorder_created_at,
            p.claimed_at,
            p.claimed_invoice_no
        FROM preorder_payment_history ph
        INNER JOIN preorders p ON ph.preorder_id = p.id
        WHERE DATE(ph.payment_date) BETWEEN ? AND ?
          AND p.branch_code = ?
        ORDER BY ph.payment_date ASC, ph.payment_sequence ASC
    ");
    $po_query->bind_param("sss", $date_from, $date_to, $branch_code);
    $po_query->execute();
    $po_result = $po_query->get_result();

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
                    'price' => $poi['price'],  // SRP
                    'is_refunded' => 0,
                    'is_upgrade_item' => 0,
                ];
            }
            $poi_q->close();

            $sales_data[] = [
                'id' => 'PO-' . $po['preorder_id'] . '-' . $po['ph_id'],
                'is_preorder' => true,
                'preorder_id' => $po['preorder_id'],
                'original_preorder_no' => $po['preorder_no'],
                'preorder_status' => $po['preorder_status'],
                'claimed_at' => $po['claimed_at'],
                'claimed_invoice_no' => $po['claimed_invoice_no'],
                'invoice_no' => $invoice_display,
                'first_name' => $po['first_name'],
                'last_name' => $po['last_name'],
                'assisted_by' => $po['assisted_by'],
                'remarks' => '(PRE-ORDER) ' . $po['remarks'],
                'total_qty' => $po['total_qty'],
                'total_amount' => $paid,          // payment actually made for this invoice
                'actual_total_amount' => $paid,   // same as total_amount for preorders
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

            // Track last invoice
            if (empty($last_invoice) || $invoice_display > $last_invoice) {
                $last_invoice = $invoice_display;
            }
        }
    }
    $po_query->close();

    // Clean any output buffer before sending JSON
    if (ob_get_length())
        ob_clean();

    echo json_encode([
        'status' => 'success',
        'sales' => $sales_data,
        'lastInvoice' => $last_invoice,
        'count' => count($sales_data),
        'debug' => [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'search_branch' => $search_branch,
            'total_in_db' => $count_result['total']
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in fetch_sales_report.php: " . $e->getMessage());

    // Clean any output buffer before sending JSON
    if (ob_get_length())
        ob_clean();

    echo json_encode([
        'status' => 'error',
        'message' => 'Error fetching sales data: ' . $e->getMessage()
    ]);
}

$conn->close();

// Ensure clean output
if (ob_get_length())
    ob_end_flush();
?>