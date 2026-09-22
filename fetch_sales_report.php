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
                WHERE (u.original_invoice_no = se.invoice_no OR u.new_invoice_no = se.invoice_no)
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
                     ) > 0 AS is_upgrade_item
                FROM sales_entry_items sei 
                WHERE sei.sales_entry_id = ?
                ORDER BY sei.id
            ");
            $items_query->bind_param("ssi", $sale['invoice_no'], $sale['invoice_no'], $sale['id']);
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

    // ── Include preorder downpayments in report ───────────────────────────────
    // Helper: compute total paid from payment_data JSON
    function computePreorderPaidAmount($pd_json)
    {
        $amount = 0.0;
        $pd = json_decode($pd_json, true);
        if (!is_array($pd))
            return $amount;

        // Handle multiple payments
        if (isset($pd['payment_type']) && $pd['payment_type'] === 'multiple') {
            foreach ((array) ($pd['payments'] ?? []) as $p) {
                if (isset($p['amount'])) {
                    // Direct amount (cash, ewallet, credit_card, etc.)
                    $amount += floatval(str_replace(',', '', $p['amount']));
                } elseif (isset($p['payment_type']) && $p['payment_type'] === 'payment_partners') {
                    // Payment partners - calculate loan_balance + down payments
                    $loan_balance = isset($p['loan_balance']) ? floatval(str_replace(',', '', $p['loan_balance'])) : 0;
                    $down_payment = 0;

                    if (isset($p['cash_dp_amount'])) {
                        $down_payment += floatval(str_replace(',', '', $p['cash_dp_amount']));
                    }
                    if (isset($p['gcash_dp_amount'])) {
                        $down_payment += floatval(str_replace(',', '', $p['gcash_dp_amount']));
                    }
                    if (isset($p['maya_dp_amount'])) {
                        $down_payment += floatval(str_replace(',', '', $p['maya_dp_amount']));
                    }

                    $amount += $loan_balance + $down_payment;
                }
            }
        } else {
            // Single payment
            if (isset($pd['amount'])) {
                // Direct amount
                $amount = floatval(str_replace(',', '', $pd['amount']));
            } elseif (isset($pd['payment_type']) && $pd['payment_type'] === 'payment_partners') {
                // Payment partners - calculate loan_balance + down payments
                $loan_balance = isset($pd['loan_balance']) ? floatval(str_replace(',', '', $pd['loan_balance'])) : 0;
                $down_payment = 0;

                if (isset($pd['cash_dp_amount'])) {
                    $down_payment += floatval(str_replace(',', '', $pd['cash_dp_amount']));
                }
                if (isset($pd['gcash_dp_amount'])) {
                    $down_payment += floatval(str_replace(',', '', $pd['gcash_dp_amount']));
                }
                if (isset($pd['maya_dp_amount'])) {
                    $down_payment += floatval(str_replace(',', '', $pd['maya_dp_amount']));
                }

                $amount = $loan_balance + $down_payment;
            }
        }

        return $amount;
    }

    // Fetch preorders created on this date range (downpayment row)
    // EXCLUDE 'claimed' preorders where the pre-order was FULLY PAID (invoice reused as sales_entry)
    // INCLUDE 'claimed' preorders that were originally PARTIAL (they had a separate downpayment row)
    $po_query = $conn->prepare("
        SELECT
            p.id, p.invoice_no, p.first_name, p.last_name,
            p.assisted_by, p.remarks, p.total_qty, p.total_amount,
            p.discount, p.payment_data, p.encoder, p.branch_code,
            p.created_at, p.status
        FROM preorders p
        WHERE DATE(p.created_at) BETWEEN ? AND ?
          AND p.branch_code = ?
          AND (
              p.status NOT IN ('claimed')
              OR (
                  -- Include claimed preorders that were originally partial
                  -- (they had a real downpayment that should appear on the creation date)
                  p.status = 'claimed'
                  AND EXISTS (
                      SELECT 1 FROM preorder_payment_history pph
                      WHERE pph.preorder_id = p.id
                      AND pph.payment_sequence > 1
                  )
              )
          )
        ORDER BY p.created_at ASC
    ");
    $po_query->bind_param("sss", $date_from, $date_to, $branch_code);
    $po_query->execute();
    $po_result = $po_query->get_result();

    if ($po_result) {
        while ($po = $po_result->fetch_assoc()) {
            // For claimed preorders that were partial: only show the initial downpayment amount
            // For all others: show total paid amount
            if ($po['status'] === 'claimed') {
                // Get only the FIRST payment from payment history (the initial downpayment)
                $first_pay_q = $conn->prepare("
                    SELECT amount FROM preorder_payment_history
                    WHERE preorder_id = ? ORDER BY payment_sequence ASC LIMIT 1
                ");
                $first_pay_q->bind_param("i", $po['id']);
                $first_pay_q->execute();
                $first_pay_res = $first_pay_q->get_result()->fetch_assoc();
                $first_pay_q->close();
                $paid = $first_pay_res ? floatval($first_pay_res['amount']) : computePreorderPaidAmount($po['payment_data']);
            } else {
                $paid = computePreorderPaidAmount($po['payment_data']);
            }

            // Build item list from preorder_items
            $poi_q = $conn->prepare("SELECT family_code AS item_description, item_code, imei, quantity, price FROM preorder_items WHERE preorder_id = ?");
            $poi_q->bind_param("i", $po['id']);
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
                'id' => 'PO-' . $po['id'],
                'invoice_no' => $po['invoice_no'],
                'first_name' => $po['first_name'],
                'last_name' => $po['last_name'],
                'assisted_by' => $po['assisted_by'],
                'remarks' => '(PRE-ORDER) ' . $po['remarks'],
                'total_qty' => $po['total_qty'],
                'total_amount' => $paid,          // payment actually made
                'actual_total_amount' => $paid,          // same as total_amount for preorders
                'discount' => 0,
                'commission' => 0,
                'payment_data' => $po['payment_data'],
                'encoder' => $po['encoder'],
                'branch_code' => $po['branch_code'],
                'created_at' => $po['created_at'],
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