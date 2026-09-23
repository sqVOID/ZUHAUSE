<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once 'session_check.php';
include 'config.php';

ob_clean();
header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}

// Fetch sale header including discount and payment_data
$stmt = $conn->prepare(
    "SELECT se.id, se.invoice_no, se.created_at, se.first_name, se.last_name,
            se.branch_code, se.discount, se.payment_data, b.branch_name
     FROM sales_entry se
     LEFT JOIN branches b ON se.branch_code = b.branch_code
     WHERE se.id = ? LIMIT 1"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Record not found']);
    exit;
}

$sale = $result->fetch_assoc();
$stmt->close();

// Parse payment_data to check for loan information
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

$actual_total_amount = 0;
if ($isLoanPayment) {
    if (!empty($pd['Total'])) {
        $totalFromPaymentData = str_replace(',', '', (string) $pd['Total']);
        if (is_numeric($totalFromPaymentData) && (float) $totalFromPaymentData > 0) {
            $actual_total_amount = (float) $totalFromPaymentData;
        }
    }
    if ($actual_total_amount <= 0 && !empty($pd['totalLoanAmount'])) {
        $totalLoanAmt = str_replace(',', '', (string) $pd['totalLoanAmount']);
        if (is_numeric($totalLoanAmt) && (float) $totalLoanAmt > 0) {
            $actual_total_amount = (float) $totalLoanAmt;
        }
    }
    if ($actual_total_amount <= 0) {
        $lbVal = $pd['Loan Balance'] ?? $pd['loan_balance'] ?? 0;
        $actual_total_amount += (float) str_replace(',', '', (string) $lbVal);
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
                        $actual_total_amount += (float) $dpPart;
                        break 2;
                    }
                }
            }
        }
    }
}

// Fetch items for this sale
$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS voucher_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS token_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");

$istmt = $conn->prepare(
    "SELECT sei.item_code, sei.item_description, sei.quantity, sei.price, sei.imei,
            COALESCE(
                NULLIF(sei.voucher_amount, 0),
                CASE WHEN i.has_voucher = 1 THEN COALESCE(i.voucher_amount, 0) ELSE 0 END,
                0
            ) AS voucher_amount,
            COALESCE(
                NULLIF(sei.token_amount, 0),
                CASE WHEN i.has_token = 1 THEN COALESCE(i.token_amount, 0) ELSE 0 END,
                0
            ) AS token_amount
     FROM sales_entry_items sei
     LEFT JOIN items i ON i.item_code = sei.item_code
     WHERE sei.sales_entry_id = ?
     ORDER BY sei.id"
);
$istmt->bind_param("i", $id);
$istmt->execute();
$ires = $istmt->get_result();

$items = [];
$invoice_subtotal = 0;
$invoice_discount = floatval($sale['discount'] ?? 0);

// First pass: calculate invoice subtotal
while ($item = $ires->fetch_assoc()) {
    $item_subtotal = $item['quantity'] * $item['price'];
    $invoice_subtotal += $item_subtotal;
    $items[] = $item;
}
$istmt->close();

// Second pass: calculate proper amounts (SRP and actual totals with loan support)
$items_with_discount = [];
$uMap = (isset($pd['unit_payment_map']) && is_array($pd['unit_payment_map'])) ? $pd['unit_payment_map'] : [];

foreach ($items as $item) {
    $item_subtotal = $item['quantity'] * $item['price'];
    $srp_amount = $item_subtotal; // SRP is price × quantity without discount
    
    // Calculate the displayed total amount
    $item_total = 0;
    
    // Try unit_payment_map resolution first
    $perItemResolved = null;
    if (!empty($uMap)) {
        $mapMethods = [];
        foreach ($uMap as $m) {
            $cleanM = strtolower(trim((string)$m));
            if ($cleanM !== '') $mapMethods[$cleanM] = true;
        }

        if (count($mapMethods) > 1) {
            $desc = strtoupper(trim((string)$item['item_description']));
            $imei = strtoupper(trim((string)($item['imei'] ?? '')));
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

            $matchedKey = strtolower(trim((string)$matchedMethod));
            $countWithSameMethod = 0;
            foreach ($uMap as $m) {
                if (strtolower(trim((string)$m)) === $matchedKey) $countWithSameMethod++;
            }

            if ($matchedMethod && $countWithSameMethod === 1) {
                if ($isLoanMethod($matchedMethod)) {
                    $lbRaw = $pd['Loan Balance'] ?? $pd['loan_balance'] ?? $pd['totalLoanAmount'] ?? $pd['total_loan_amount'] ?? 0;
                    $loanTotal = floatval(str_replace(',', '', (string)$lbRaw));
                    $dpKeys = ['cash_down_payment_amount', 'cash_dp_amount', 'gcash_down_payment_amount', 'gcash_dp_amount', 'maya_down_payment_amount', 'maya_dp_amount'];
                    foreach ($dpKeys as $dk) {
                        if (isset($pd[$dk])) {
                            $dpParts = explode('|', str_replace(',', '', (string)$pd[$dk]));
                            $dpVal = floatval(trim($dpParts[0]));
                            if ($dpVal > 0) $loanTotal += $dpVal;
                        }
                    }
                    if ($loanTotal > 0) $perItemResolved = $loanTotal * intval($item['quantity'] ?? 1);
                } elseif ($matchedKey === 'cash') {
                    $cashAmtRaw = (string)($pd['Amount'] ?? $pd['cash_amount'] ?? '');
                    $cashAmt = floatval(str_replace(',', '', trim($cashAmtRaw)));
                    if ($cashAmt > 0) $perItemResolved = $cashAmt * intval($item['quantity'] ?? 1);
                }
            }
        }
    }

    if ($perItemResolved !== null) {
        $item_total = round($perItemResolved);
    } elseif ($actual_total_amount > 0 && $invoice_subtotal > 0 && $isLoanPayment) {
        // Loan transaction: use proportional calculation based on actual loan total
        $proportion = $item_subtotal / $invoice_subtotal;
        $item_total = round($actual_total_amount * $proportion);
    } else {
        // Standard: deduct this item's own voucher/token (+ proportional discount)
        $item_voucher = floatval($item['voucher_amount'] ?? 0);
        $item_token = floatval($item['token_amount'] ?? 0);
        $item_discount = 0;
        if ($invoice_subtotal > 0 && $invoice_discount > 0) {
            $item_discount = round($invoice_discount * ($item_subtotal / $invoice_subtotal));
        }
        $item_total = round($item_subtotal - $item_voucher - $item_token - $item_discount);
    }
    
    $items_with_discount[] = [
        'item_code'        => $item['item_code'],
        'item_description' => $item['item_description'],
        'quantity'         => $item['quantity'],
        'price'            => $item['price'],
        'srp'              => $srp_amount,
        'voucher_amount'   => floatval($item['voucher_amount'] ?? 0),
        'token_amount'     => floatval($item['token_amount'] ?? 0),
        'total'            => $item_total,
    ];
}

$branch_display = ($sale['branch_name'] ? $sale['branch_name'] : 'Unknown') . ' - ' . ($sale['branch_code'] ? $sale['branch_code'] : 'UNK');

echo json_encode([
    'status'  => 'success',
    'sale'    => [
        'id'           => $sale['id'],
        'invoice_no'   => $sale['invoice_no'],
        'date_sold'    => date('m/d/Y', strtotime($sale['created_at'])),
        'customer'     => trim($sale['first_name'] . ' ' . $sale['last_name']),
        'branch'       => $branch_display,
    ],
    'items'   => $items_with_discount,
]);
?>
