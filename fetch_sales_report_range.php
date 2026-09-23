<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'session_check.php';
require_once 'config.php';

ob_clean();
header('Content-Type: application/json');

// Ensure per-item voucher/token columns exist (idempotent)
$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS voucher_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS token_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON request']);
    exit;
}

$date_from = isset($data['date_from']) ? trim($data['date_from']) : '';
$date_to = isset($data['date_to']) ? trim($data['date_to']) : '';
$branch_name = isset($data['branch']) ? trim($data['branch']) : '';
$area = isset($data['area']) ? trim($data['area']) : '';

if ($date_from === '' || $date_to === '') {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['status' => 'error', 'message' => 'From date and to date are required']);
    exit;
}

// Allow empty branch and area for "All Areas" and "All Branches" selection
try {
    $branch_codes = [];

    if ($branch_name !== '') {
        // Specific branch selected
        $stmt_branch = $conn->prepare("SELECT branch_code FROM branches WHERE branch_name = ? LIMIT 1");
        $stmt_branch->bind_param("s", $branch_name);
        $stmt_branch->execute();
        $result_branch = $stmt_branch->get_result();
        if ($result_branch && $result_branch->num_rows > 0) {
            $branch_row = $result_branch->fetch_assoc();
            $branch_codes[] = $branch_row['branch_code'];
        }
        $stmt_branch->close();
    } elseif ($area !== '') {
        // Specific area selected
        $stmt_branch = $conn->prepare("SELECT branch_code FROM branches WHERE area = ?");
        $stmt_branch->bind_param("s", $area);
        $stmt_branch->execute();
        $result_branch = $stmt_branch->get_result();
        if ($result_branch && $result_branch->num_rows > 0) {
            while ($branch_row = $result_branch->fetch_assoc()) {
                $branch_codes[] = $branch_row['branch_code'];
            }
        }
        $stmt_branch->close();
    } else {
        // Both empty - means "All Areas" and "All Branches"
        $stmt_branch = $conn->prepare("SELECT branch_code FROM branches WHERE status = 'Active'");
        $stmt_branch->execute();
        $result_branch = $stmt_branch->get_result();
        if ($result_branch && $result_branch->num_rows > 0) {
            while ($branch_row = $result_branch->fetch_assoc()) {
                $branch_codes[] = $branch_row['branch_code'];
            }
        }
        $stmt_branch->close();
    }

    if (empty($branch_codes)) {
        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode(['status' => 'error', 'message' => 'No active branches found']);
        exit;
    }

    $in_placeholders = implode(',', array_fill(0, count($branch_codes), '?'));

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
            COALESCE(b.branch_name, se.branch_code) AS branch_name,
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
            se.status as display_status,
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
          AND se.branch_code IN ($in_placeholders)
          AND (sei.quantity IS NULL OR sei.quantity > 0)
          AND se.status != 'voided'
        ORDER BY se.created_at DESC, se.id DESC, sei.id DESC
    ";

    $stmt = $conn->prepare($sql);
    $types = "ss" . str_repeat("s", count($branch_codes));
    $params = array_merge([$date_from, $date_to], $branch_codes);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    if ($result) {
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

            // Check if this is a loan transaction (support both old and new formats)
            $loanType = '';
            $lowerPM = strtolower($pd['payment_type'] ?? '');
            if (
                strpos($lowerPM, 'cebu') !== false || strpos($lowerPM, 'partner') !== false ||
                isset($pd['Loan Term']) || isset($pd['Loan Type']) ||
                isset($pd['loan_type']) || isset($pd['payment_partner'])
            ) {
                $loanType = isset($pd['Loan Type']) ? $pd['Loan Type'] :
                    (isset($pd['loan_type']) ? $pd['loan_type'] : 'loan');
            }

            if ($loanType !== '') {
                // Try multiple fields for total loan amount
                if (!empty($pd['Total'])) {
                    $totalFromPaymentData = str_replace(',', '', $pd['Total']);
                    if (is_numeric($totalFromPaymentData) && (float) $totalFromPaymentData > 0) {
                        $invoice_total_from_pd = (float) $totalFromPaymentData;
                    }
                } elseif (!empty($pd['totalLoanAmount'])) {
                    $totalLoanAmt = str_replace(',', '', $pd['totalLoanAmount']);
                    if (is_numeric($totalLoanAmt) && (float) $totalLoanAmt > 0) {
                        $invoice_total_from_pd = (float) $totalLoanAmt;
                    }
                } elseif (!empty($pd['total_loan_amount'])) {
                    // New format from preorders
                    $totalLoanAmt = str_replace(',', '', $pd['total_loan_amount']);
                    if (is_numeric($totalLoanAmt) && (float) $totalLoanAmt > 0) {
                        $invoice_total_from_pd = (float) $totalLoanAmt;
                    }
                } elseif (!empty($pd['loan_balance'])) {
                    // Fallback: calculate from loan_balance + down payments
                    $loanBalance = str_replace(',', '', $pd['loan_balance']);
                    $downPayment = 0;
                    if (isset($pd['cash_dp_amount'])) {
                        $downPayment += (float) str_replace(',', '', $pd['cash_dp_amount']);
                    }
                    if (isset($pd['gcash_dp_amount'])) {
                        $downPayment += (float) str_replace(',', '', $pd['gcash_dp_amount']);
                    }
                    if (isset($pd['maya_dp_amount'])) {
                        $downPayment += (float) str_replace(',', '', $pd['maya_dp_amount']);
                    }
                    $invoice_total_from_pd = (float) $loanBalance + $downPayment;
                }
            }

            // Calculate total_sales
            if ($invoice_total_from_pd > 0 && $invoice_subtotal > 0) {
                // Loan transaction: distribute loan total proportionally across items
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
                $invoice_total = floatval($row['invoice_total']); // se.total_amount

                // Card/QR surcharge: scale nets so they still sum to se.total_amount
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
    }
    $stmt->close();

    // ── Include preorder downpayments in salesreport range ───────────────────────────────
    function computePOPaidAmount($pd_json)
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

    if (!empty($branch_codes)) {
        $in_ph2 = implode(',', array_fill(0, count($branch_codes), '?'));
        $po_sql = "
            SELECT
                p.id AS preorder_id,
                p.invoice_no,
                p.status,
                p.assisted_by,
                p.payment_data,
                p.encoder,
                p.branch_code,
                p.created_at,
                p.total_qty,
                p.total_amount,
                p.discount,
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
              AND p.branch_code IN ($in_ph2)
              AND (
                  p.status NOT IN ('claimed')
                  OR (
                      -- Include claimed preorders that were originally partial
                      p.status = 'claimed'
                      AND EXISTS (
                          SELECT 1 FROM preorder_payment_history pph
                          WHERE pph.preorder_id = p.id
                          AND pph.payment_sequence > 1
                      )
                  )
              )
              AND (pi.quantity IS NULL OR pi.quantity > 0)
            ORDER BY p.created_at DESC, p.id DESC
        ";
        $po_types = 'ss' . str_repeat('s', count($branch_codes));
        $po_params = array_merge([$date_from, $date_to], $branch_codes);
        $po_stmt = $conn->prepare($po_sql);
        $po_stmt->bind_param($po_types, ...$po_params);
        $po_stmt->execute();
        $po_result = $po_stmt->get_result();

        if ($po_result) {
            while ($po = $po_result->fetch_assoc()) {
                // For claimed partial preorders: use only the initial downpayment amount
                if ($po['status'] === 'claimed') {
                    $fp_stmt = $conn->prepare("
                        SELECT amount FROM preorder_payment_history
                        WHERE preorder_id = ? ORDER BY payment_sequence ASC LIMIT 1
                    ");
                    $fp_stmt->bind_param("i", $po['preorder_id']);
                    $fp_stmt->execute();
                    $fp_row = $fp_stmt->get_result()->fetch_assoc();
                    $fp_stmt->close();
                    $paid = $fp_row ? floatval($fp_row['amount']) : computePOPaidAmount($po['payment_data']);
                } else {
                    $paid = computePOPaidAmount($po['payment_data']);
                }

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
                    'item_price' => $item_price,      // SRP
                    'invoice_discount' => 0,
                    'invoice_total' => $paid,            // actual payment
                    'item_subtotal' => $item_subtotal,   // SRP × qty
                    'srp_amount' => $item_subtotal,   // SRP for this item
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
                    'display_status' => 'completed',
                    'invoice_subtotal' => $item_subtotal,
                    'total_sales' => $item_paid,       // actual payment made for this item
                ];
            }
        }
        $po_stmt->close();
    }

    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode([
        'status' => 'success',
        'rows' => $rows,
        'count' => count($rows)
    ]);
} catch (Exception $e) {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Error fetching sales report: ' . $e->getMessage()
    ]);
}

$conn->close();
if (ob_get_length()) {
    ob_end_flush();
}
?>