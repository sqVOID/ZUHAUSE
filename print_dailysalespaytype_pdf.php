<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf.php';

$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$branch = isset($_GET['branch']) ? trim($_GET['branch']) : '';

if ($date_from === '' || $date_to === '' || $branch === '') {
    die("Missing required parameters.");
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

function normalizePaymentMethodLabel($method, $pd)
{
    $method = trim((string) $method);
    if ($method === '') {
        return '';
    }

    if (strpos($method, '+') !== false || strpos($method, '|') !== false || strpos($method, ',') !== false || strpos($method, '/') !== false || strpos($method, '&') !== false) {
        // It's already a combined string — split it and process each part
        $parts = preg_split('/\s*(?:\/|,|\+|\&|\|)\s*/', $method);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '')
                continue;
            if (strcasecmp($part, 'E-Wallet') === 0 && !empty($pd['E-Wallet-Text'])) {
                $part = trim((string) $pd['E-Wallet-Text']);
            } elseif (strcasecmp($part, 'Online Banking') === 0 && !empty($pd['Bank-Text'])) {
                $part = trim((string) $pd['Bank-Text']);
            }
            return $part; // Return first recognized part
        }
    }
    if (strcasecmp($method, 'E-Wallet') === 0 && !empty($pd['E-Wallet-Text'])) {
        return trim((string) $pd['E-Wallet-Text']);
    }
    if (strcasecmp($method, 'Online Banking') === 0 && !empty($pd['Bank-Text'])) {
        return trim((string) $pd['Bank-Text']);
    }

    return $method;
}

function appendUniqueMethods(&$methods, $rawValue, $pd)
{
    if (is_array($rawValue)) {
        foreach ($rawValue as $value) {
            appendUniqueMethods($methods, $value, $pd);
        }
        return;
    }

    $raw = trim((string) $rawValue);
    if ($raw === '') {
        return;
    }

    // Support values like "Cash / GCash", "Cash,Card", "Cash + Maya"
    $parts = preg_split('/\s*(?:\/|,|\+|&|\|)\s*/', $raw);
    if (!$parts || count($parts) === 0) {
        $parts = [$raw];
    }

    foreach ($parts as $part) {
        $normalized = normalizePaymentMethodLabel($part, $pd);
        if ($normalized === '') {
            continue;
        }
        $key = strtoupper($normalized);
        if (!isset($methods[$key])) {
            $methods[$key] = $normalized;
        }
    }
}

/**
 * Extracts the first valid numeric value from a potentially mixed string.
 */
function extractNumericFromMixed($raw)
{
    if ($raw === '' || $raw === null)
        return '';
    $raw = (string) $raw;
    // Split by pipe (primary separator used by collectData for same-key merging)
    $parts = explode('|', $raw);
    foreach ($parts as $part) {
        $part = trim(str_replace(',', '', $part));
        if (is_numeric($part) && (float) $part > 0) {
            return (float) $part;
        }
    }
    // Fallback: try space-separated tokens
    $tokens = preg_split('/\s+/', $raw);
    foreach ($tokens as $token) {
        $token = trim(str_replace(',', '', $token));
        if (is_numeric($token) && (float) $token > 0) {
            return (float) $token;
        }
    }
    return '';
}

$sql = "
    SELECT
        se.invoice_no,
        se.original_invoice_no,
        se.discount,
        TRIM(CONCAT(COALESCE(se.first_name, ''), ' ', COALESCE(se.last_name, ''))) AS customer_name,
        se.total_amount,
        se.payment_data,
        se.upgrade,
        (
            SELECT u.payment_data
            FROM upgrades u
            WHERE (u.original_invoice_no = se.invoice_no OR u.new_invoice_no = se.invoice_no)
            ORDER BY u.id DESC
            LIMIT 1
        ) AS upgrade_payment_data,
        COALESCE((
            SELECT SUM(COALESCE(sei.quantity, 0) * COALESCE(sei.price, 0))
            FROM sales_entry_items sei
            WHERE sei.sales_entry_id = se.id
        ), 0) AS invoice_items_total,
        COALESCE(r.total_amount, 0) AS refund_amount,
        COALESCE((
            SELECT SUM(uoi.price)
            FROM upgrades u
            JOIN upgrade_old_items uoi ON uoi.upgrade_id = u.id
            WHERE u.new_invoice_no = se.invoice_no
        ), 0) AS old_unit_amount,
        CASE 
            WHEN r.invoice_no IS NOT NULL THEN 'refunded'
            ELSE se.status
        END as display_status
    FROM sales_entry se
    LEFT JOIN refunds r ON r.invoice_no = se.invoice_no
    WHERE DATE(se.created_at) BETWEEN ? AND ?
      AND se.branch_code = ?
      AND se.status != 'voided'
    ORDER BY se.created_at DESC, se.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $date_from, $date_to, $branch_code);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pd = [];
        $upgradePd = [];
        if (!empty($row['payment_data'])) {
            $pd = json_decode($row['payment_data'], true);
        }
        if (!empty($row['upgrade_payment_data'])) {
            $upgradePd = json_decode($row['upgrade_payment_data'], true);
            if (!is_array($upgradePd)) {
                $upgradePd = [];
            }
        }

        // Collect all methods used for this invoice/payment record
        // NOTE: $pd['Payment Method'] can contain raw DP checkbox values like 'gcash' or 'maya'
        // We must skip those values so they don't appear as top-level payment methods.
        $dpRawValues = ['cash', 'gcash', 'maya'];
        $paymentMethods = [];

        appendUniqueMethods($paymentMethods, $pd['payment_type'] ?? '', $pd);
        appendUniqueMethods($paymentMethods, $pd['payment_method'] ?? '', $pd);

        // Filter out DP raw values from Payment Method field
        $pmVal = trim((string) ($pd['Payment Method'] ?? ''));
        if ($pmVal !== '' && !in_array(strtolower($pmVal), $dpRawValues)) {
            appendUniqueMethods($paymentMethods, $pmVal, $pd);
        }

        appendUniqueMethods($paymentMethods, $upgradePd['payment_type'] ?? '', $upgradePd);
        appendUniqueMethods($paymentMethods, $upgradePd['payment_method'] ?? '', $upgradePd);

        $upmVal = trim((string) ($upgradePd['Payment Method'] ?? ''));
        if ($upmVal !== '' && !in_array(strtolower($upmVal), $dpRawValues)) {
            appendUniqueMethods($paymentMethods, $upmVal, $upgradePd);
        }

        $referenceNo = '';
        $referenceParts = [];
        foreach ([$pd, $upgradePd] as $candidatePd) {
            if (!is_array($candidatePd) || count($candidatePd) === 0) {
                continue;
            }
            $candidateRef = '';
            if (isset($candidatePd['Reference Number'])) {
                $candidateRef = $candidatePd['Reference Number'];
            } elseif (isset($candidatePd['Transaction No / Reference No'])) {
                $candidateRef = $candidatePd['Transaction No / Reference No'];
            } elseif (isset($candidatePd['Reference No'])) {
                $candidateRef = $candidatePd['Reference No'];
            } elseif (isset($candidatePd['Reference No.'])) {
                $candidateRef = $candidatePd['Reference No.'];
            } elseif (isset($candidatePd['Approval Code'])) {
                $candidateRef = $candidatePd['Approval Code'];
            } elseif (isset($candidatePd['Trace No.'])) {
                $candidateRef = $candidatePd['Trace No.'];
            }
            $candidateRef = trim((string) $candidateRef);
            if ($candidateRef !== '' && !in_array($candidateRef, $referenceParts, true)) {
                $referenceParts[] = $candidateRef;
            }
        }
        $referenceNo = implode(' + ', $referenceParts);

        $loanType = '';
        $loanTerm = '';
        $loanNumber = '';
        $loanBalance = '';
        $dpMethod = '';
        $dpRef = '';
        $dpAmount = '';

        // Extract logic for payment partners / STO ninio de cebu
        $methodValues = array_values($paymentMethods);
        $pmCount = count($methodValues);
        if ($pmCount === 0) {
            $paymentMethod = '';
        } elseif ($pmCount === 1) {
            $paymentMethod = $methodValues[0];
        } elseif ($pmCount === 2) {
            $paymentMethod = $methodValues[0] . ' & ' . $methodValues[1];
        } else {
            $paymentMethod = implode(', ', array_slice($methodValues, 0, -1)) . ', & ' . end($methodValues);
        }
        $lowerPM = strtolower($pd['payment_type'] ?? '');
        if (strpos($lowerPM, 'cebu') !== false || strpos($lowerPM, 'partner') !== false || strpos($lowerPM, 'home credit') !== false || isset($pd['Loan Term']) || isset($pd['Loan Type']) || isset($pd['loan_type']) || isset($pd['loanTypeDropdown'])) {
            $loanType = $pd['Loan Type'] ?? $pd['loan_type'] ?? $pd['loanTypeDropdown'] ?? '';
            $loanTerm = '';
            if (isset($pd['Loan Terms'])) {
                $loanTerm = trim(str_ireplace('month', '', str_ireplace('months', '', $pd['Loan Terms'])));
            } elseif (isset($pd['Loan Term'])) {
                $loanTerm = trim(str_ireplace('month', '', str_ireplace('months', '', $pd['Loan Term'])));
            }
            $loanNumber = isset($pd['Loan Number']) ? $pd['Loan Number'] : (isset($pd['Account Number']) ? $pd['Account Number'] : '');

            if (isset($pd['Loan Balance']))
                $loanBalance = $pd['Loan Balance'];
            elseif (isset($pd['Loan Amount']))
                $loanBalance = $pd['Loan Amount'];
            elseif (isset($pd['Amount Financed']))
                $loanBalance = $pd['Amount Financed'];

            // --- DP Method ---
            $dpMethods = [];
            if (!empty($pd['down_payment_method'])) {
                // Could be "cash", "gcash", "maya" or comma-separated combinations
                $rawDpMethods = preg_split('/\s*[,\/]\s*/', (string) $pd['down_payment_method']);
                foreach ($rawDpMethods as $m) {
                    $m = trim($m);
                    if (strcasecmp($m, 'cash') === 0)
                        $dpMethods[] = 'Cash';
                    elseif (strcasecmp($m, 'gcash') === 0)
                        $dpMethods[] = 'G-Cash';
                    elseif (strcasecmp($m, 'maya') === 0)
                        $dpMethods[] = 'Maya';
                    elseif ($m !== '')
                        $dpMethods[] = $m;
                }
            }
            // Legacy / old keys fallback
            if (empty($dpMethods)) {
                if (!empty($pd['Downpayment_Payment_Method']))
                    $dpMethods[] = $pd['Downpayment_Payment_Method'];
                elseif (!empty($pd['Downpayment Method']))
                    $dpMethods[] = $pd['Downpayment Method'];
                elseif (!empty($pd['Down payment method']))
                    $dpMethods[] = $pd['Down payment method'];
            }
            $dpMethod = implode(' & ', $dpMethods);

            // --- DP Ref ---
            $dpRefParts = [];
            if (!empty($pd['gcash_down_payment_reference']))
                $dpRefParts[] = $pd['gcash_down_payment_reference'];
            if (!empty($pd['maya_down_payment_reference']))
                $dpRefParts[] = $pd['maya_down_payment_reference'];
            // Legacy keys
            if (empty($dpRefParts)) {
                if (!empty($pd['Downpayment_Ref']))
                    $dpRefParts[] = $pd['Downpayment_Ref'];
                elseif (!empty($pd['Downpayment Reference']))
                    $dpRefParts[] = $pd['Downpayment Reference'];
            }
            $dpRef = implode(' | ', $dpRefParts);

            // --- DP Amount ---
            // Sum all individual DP amounts that were saved by their input IDs
            $dpTotal = 0.0;
            if (!empty($pd['cash_down_payment_amount'])) {
                $v = extractNumericFromMixed((string) $pd['cash_down_payment_amount']);
                if ($v !== '')
                    $dpTotal += (float) $v;
            }
            if (!empty($pd['gcash_down_payment_amount'])) {
                $v = extractNumericFromMixed((string) $pd['gcash_down_payment_amount']);
                if ($v !== '')
                    $dpTotal += (float) $v;
            }
            if (!empty($pd['maya_down_payment_amount'])) {
                $v = extractNumericFromMixed((string) $pd['maya_down_payment_amount']);
                if ($v !== '')
                    $dpTotal += (float) $v;
            }
            // Legacy keys fallback
            if ($dpTotal == 0) {
                $dpRaw = '';
                if (!empty($pd['Downpayment_Amount']))
                    $dpRaw = $pd['Downpayment_Amount'];
                elseif (!empty($pd['Down Payment']))
                    $dpRaw = $pd['Down Payment'];
                elseif (!empty($pd['Down payment']))
                    $dpRaw = $pd['Down payment'];
                elseif (!empty($pd['Downpayment']))
                    $dpRaw = $pd['Downpayment'];
                elseif (!empty($pd['Downpayment Amount']))
                    $dpRaw = $pd['Downpayment Amount'];
                elseif (!empty($pd['Enter Amount']))
                    $dpRaw = $pd['Enter Amount'];
                $legacyVal = extractNumericFromMixed($dpRaw);
                if ($legacyVal !== '')
                    $dpTotal = (float) $legacyVal;
            }
            $dpAmount = $dpTotal > 0 ? $dpTotal : '';

            // Auto-detect DP Method if still empty but we have an amount
            if ($dpAmount !== '' && $dpMethod === '') {
                if ($dpRef === '') {
                    $dpMethod = 'Cash';
                } elseif (!empty($pd['gcash_down_payment_reference'])) {
                    $dpMethod = 'G-Cash';
                } elseif (!empty($pd['maya_down_payment_reference'])) {
                    $dpMethod = 'Maya';
                }
            }
        }

        // DO NOT include downpayment method in payment method list
        // DP methods are separate and shown in the DP METHOD column

        // Fallback when no structured payment method exists
        if (count($paymentMethods) === 0) {
            appendUniqueMethods($paymentMethods, 'Cash', $pd);
        }
        $methodValues = array_values($paymentMethods);
        $pmCount = count($methodValues);
        if ($pmCount === 0) {
            $paymentMethod = '';
        } elseif ($pmCount === 1) {
            $paymentMethod = $methodValues[0];
        } elseif ($pmCount === 2) {
            $paymentMethod = $methodValues[0] . ' & ' . $methodValues[1];
        } else {
            $paymentMethod = implode(', ', array_slice($methodValues, 0, -1)) . ', & ' . end($methodValues);
        }

        $customerName = trim($row['customer_name']);
        if ($customerName === '' && isset($pd["Customer's Name"])) {
            $customerName = $pd["Customer's Name"];
        }

        $modalAmount = '';
        $amountParts = [];
        if (is_array($pd) && count($pd) > 0) {
            $candidateAmount = '';
            if (isset($pd['Amount']))
                $candidateAmount = $pd['Amount'];
            elseif (isset($pd['Enter Amount']))
                $candidateAmount = $pd['Enter Amount'];
            elseif (isset($pd['Cash Amount']))
                $candidateAmount = $pd['Cash Amount'];

            // Handle pipe-separated amounts from split payments e.g. "156000 | 0"
            $candidateAmount = is_string($candidateAmount) ? str_replace(',', '', $candidateAmount) : (string) $candidateAmount;
            $pipeParts = explode('|', $candidateAmount);
            foreach ($pipeParts as $pipePart) {
                $pipePart = trim($pipePart);
                if ($pipePart !== '' && is_numeric($pipePart)) {
                    $amountParts[] = (float) $pipePart;
                }
            }
        }
        if (count($amountParts) > 0) {
            $modalAmount = array_sum($amountParts);
        }

        if ($loanType !== '') {
            $loanType = ucwords(str_replace('_', ' ', $loanType));
        }

        // Calculate actual total amount (loan total for loan transactions)
        $isUpgradeInvoice = !empty($row['original_invoice_no']);
        $displayTotalAmount = round((float) $row['total_amount']);

        if ($isUpgradeInvoice) {
            // For upgrade invoices:
            // payment_amount (Amount column) is the cash/payment paid (e.g. 1,400)
            // total_amount (Total Amount column) is the full transaction value (SRP: e.g. 2,695)
            $displayTotalAmount = round((float) $row['invoice_items_total'] > 0 ? (float) $row['invoice_items_total'] : ((float) $row['total_amount'] + (float) $row['old_unit_amount']));
            if ($modalAmount === '' || $modalAmount == 0) {
                $modalAmount = round((float) $row['total_amount']);
            }
        } else {
            if ($loanType !== '' && !empty($pd['Total'])) {
                // This is a loan transaction - use Total from payment_data
                $totalFromPaymentData = str_replace(',', '', $pd['Total']);
                if (is_numeric($totalFromPaymentData) && (float) $totalFromPaymentData > 0) {
                    $displayTotalAmount = round((float) $totalFromPaymentData);
                }
            } elseif (!empty($pd['totalLoanAmount'])) {
                // Alternative: check for totalLoanAmount field
                $totalLoanAmt = str_replace(',', '', $pd['totalLoanAmount']);
                if (is_numeric($totalLoanAmt) && (float) $totalLoanAmt > 0) {
                    $displayTotalAmount = round((float) $totalLoanAmt);
                }
            }

            if ($modalAmount === '' || $modalAmount == 0) {
                $modalAmount = $displayTotalAmount;
            }
        }

        $rows[] = [
            'invoice_no' => $row['invoice_no'],
            'customer_name' => $customerName,
            'payment_method' => $paymentMethod,
            'reference_no' => $referenceNo,
            'loan_type' => $loanType,
            'loan_term' => $loanTerm,
            'loan_number' => $loanNumber,
            'loan_balance' => $loanBalance,
            'dp_method' => $dpMethod,
            'dp_ref' => $dpRef,
            'dp_amount' => $dpAmount,
            'payment_amount' => $modalAmount,
            'total_amount' => $displayTotalAmount,
            'invoice_items_total' => $row['invoice_items_total'],
            'old_unit_amount' => $row['old_unit_amount'],
            'refund_amount' => $row['refund_amount'],
            'upgrade' => $row['upgrade'] ?? '',
            'display_status' => $row['display_status'] ?? ''
        ];
    }
}
$stmt->close();

class PaymentTypeReportPDF extends FPDF
{
    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Courier', 'B', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, 'PAGE ' . $this->PageNo(), 0, 0, 'C');
    }
}

// 13 columns -> Layout L for Landscape!
$pdf = new PaymentTypeReportPDF('L', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

$logo_path = __DIR__ . '/Icon/ZUHAUSE-LOGO.PNG';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 5, 5, 25, 25);
}

$pdf->SetFont('Courier', 'B', 16);
$pdf->SetY(20);
$pdf->Cell(0, 8, 'DAILY SALES PAYMENT DETAILS REPORT', 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(0, 6, 'BRANCH: ' . strtoupper($branch), 0, 1, 'L');
$pdf->Cell(0, 6, 'DATE RANGE: ' . date('F d, Y', strtotime($date_from)) . ' to ' . date('F d, Y', strtotime($date_to)), 0, 1, 'L');
$pdf->Cell(0, 6, 'TIME & DATE: ' . date('h:i:s A - F d, Y'), 0, 1, 'R');
$pdf->Ln(2);

$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Courier', 'B', 5.5);

$widths = [18, 25, 16, 18, 22, 12, 18, 20, 16, 18, 18, 18, 18, 18, 18]; // Total 273mm for 15 columns
$headers = ['INVOICE NO', 'CUSTOMER NAME', 'SRP', 'PAY METHOD', 'LOAN TYPE', 'TERMS', 'LOAN NO', 'LOAN BAL', 'DP METHOD', 'DP REF', 'DP AMOUNT', 'OLD UNIT AMT', 'AMOUNT', 'REFUND AMT', 'TOTAL AMT'];

for ($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
}
$pdf->Ln();

$grandTotalAmount = 0;

$pdf->SetFont('Courier', '', 5);
if (count($rows) === 0) {
    $pdf->Cell(array_sum($widths), 8, 'NO DATA', 1, 1, 'C');
} else {
    foreach ($rows as $r) {
        // Check if this is a refunded sale
        $isRefunded = ($r['display_status'] === 'refunded');
        $isUpgrade = ($r['upgrade'] === 'UPGD');

        // Set text color for refunded items
        if ($isRefunded) {
            $pdf->SetTextColor(211, 47, 47); // Red color for refunded items
        } else {
            $pdf->SetTextColor(0, 0, 0); // Black color for normal items
        }

        $refundIndicator = $isRefunded ? ' RF' : '';

        $pdf->Cell($widths[0], 7, substr(strtoupper($r['invoice_no'] . $refundIndicator), 0, 13), 1, 0, 'C');
        $pdf->Cell($widths[1], 7, substr(strtoupper($r['customer_name']), 0, 20), 1, 0, 'L');

        // SRP column (invoice_items_total)
        $srp = number_format((float) $r['invoice_items_total'], 2);
        $pdf->Cell($widths[2], 7, $srp, 1, 0, 'R');

        $pdf->Cell($widths[3], 7, substr(strtoupper($r['payment_method']), 0, 13), 1, 0, 'C');
        $pdf->Cell($widths[4], 7, substr(strtoupper($r['loan_type']), 0, 16), 1, 0, 'C');
        $pdf->Cell($widths[5], 7, substr(strtoupper($r['loan_term']), 0, 8), 1, 0, 'C');
        $pdf->Cell($widths[6], 7, substr(strtoupper($r['loan_number']), 0, 13), 1, 0, 'C');

        $lb = $r['loan_balance'] !== '' ? number_format((float) str_replace(',', '', $r['loan_balance']), 2) : '';
        $pdf->Cell($widths[7], 7, $lb, 1, 0, 'R');

        $pdf->Cell($widths[8], 7, substr(strtoupper($r['dp_method']), 0, 11), 1, 0, 'C');
        $pdf->Cell($widths[9], 7, substr(strtoupper($r['dp_ref']), 0, 13), 1, 0, 'C');

        $dpa = $r['dp_amount'] !== '' ? number_format((float) str_replace(',', '', $r['dp_amount']), 2) : '';
        $pdf->Cell($widths[10], 7, $dpa, 1, 0, 'R');

        // Old Unit Amount (for upgrades)
        $oldUnitAmount = $isUpgrade ? number_format((float) $r['old_unit_amount'], 2) : '';
        $pdf->Cell($widths[11], 7, $oldUnitAmount, 1, 0, 'R');

        $pa = $r['payment_amount'] !== '' ? number_format((float) str_replace(',', '', $r['payment_amount']), 2) : '';
        $pdf->Cell($widths[12], 7, $pa, 1, 0, 'R');

        // Refund Amount
        $refundAmount = number_format((float) $r['refund_amount'], 2);
        $pdf->Cell($widths[13], 7, $refundAmount, 1, 0, 'R');

        // Calculate row total using the actual displayTotalAmount (includes loan totals)
        $rowTotalAmountRaw = (float) $r['total_amount'];
        $refundAmountRaw = (float) $r['refund_amount'];

        $ta = number_format($rowTotalAmountRaw, 2);
        $pdf->Cell($widths[14], 7, $ta, 1, 1, 'R');

        // Add to grand total (deduct refunds like in dailysalespaytype.php)
        $grandTotalAmount += $rowTotalAmountRaw - $refundAmountRaw;

        // Reset text color
        $pdf->SetTextColor(0, 0, 0);
    }
}

$pdf->Ln(5);
$pdf->SetTextColor(211, 47, 47);
$pdf->SetFont('Courier', 'B', 8);
$pdf->Cell(40, 5, 'TOTAL AMOUNT:', 0, 0, 'L');
$pdf->Cell(30, 5, number_format($grandTotalAmount, 2), 0, 1, 'R');

$pdf->Output('I', 'Daily_Sales_Payment_Details_' . $date_from . '_to_' . $date_to . '_' . $branch . '.pdf');
?>