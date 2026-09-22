<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'session_check.php';
require_once 'config.php';

ob_clean();
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON request']);
    exit;
}

$date_from = isset($data['date_from']) ? trim($data['date_from']) : '';
$date_to = isset($data['date_to']) ? trim($data['date_to']) : '';
$branch_name = isset($data['branch']) ? trim($data['branch']) : '';

if ($date_from === '' || $date_to === '' || $branch_name === '') {
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'From date, to date, and branch are required']);
    exit;
}

function normalizePaymentMethodLabel($method, $pd)
{
    $method = trim((string)$method);
    if ($method === '') {
        return '';
    }

    if (strpos($method, '+') !== false || strpos($method, '|') !== false || strpos($method, ',') !== false || strpos($method, '/') !== false || strpos($method, '&') !== false) {
        // It's already a combined string — split it and process each part
        $parts = preg_split('/\s*(?:\/|,|\+|\&|\|)\s*/', $method);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            if (strcasecmp($part, 'E-Wallet') === 0 && !empty($pd['E-Wallet-Text'])) {
                $part = trim((string)$pd['E-Wallet-Text']);
            } elseif (strcasecmp($part, 'Online Banking') === 0 && !empty($pd['Bank-Text'])) {
                $part = trim((string)$pd['Bank-Text']);
            } elseif (strcasecmp($part, 'payment_partners') === 0 && !empty($pd['payment_partner'])) {
                $part = trim((string)$pd['payment_partner']);
            }
            return $part; // Return first recognized part
        }
    }
    if (strcasecmp($method, 'E-Wallet') === 0 && !empty($pd['E-Wallet-Text'])) {
        return trim((string)$pd['E-Wallet-Text']);
    }
    if (strcasecmp($method, 'Online Banking') === 0 && !empty($pd['Bank-Text'])) {
        return trim((string)$pd['Bank-Text']);
    }
    if (strcasecmp($method, 'payment_partners') === 0 && !empty($pd['payment_partner'])) {
        return trim((string)$pd['payment_partner']);
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

    $raw = trim((string)$rawValue);
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
 * collectData() can produce values like "gcash | 25,900" when a checkbox and
 * a text input share the same key (same parent hc-form-group label).
 * This function splits by pipe/comma, strips commas from numbers, and returns
 * the first parseable float, or '' if none found.
 */
function extractNumericFromMixed($raw)
{
    if ($raw === '' || $raw === null) return '';
    $raw = (string)$raw;
    // Split by pipe (primary separator used by collectData for same-key merging)
    $parts = explode('|', $raw);
    foreach ($parts as $part) {
        $part = trim(str_replace(',', '', $part));
        if (is_numeric($part) && (float)$part > 0) {
            return (float)$part;
        }
    }
    // Fallback: try space-separated tokens
    $tokens = preg_split('/\s+/', $raw);
    foreach ($tokens as $token) {
        $token = trim(str_replace(',', '', $token));
        if (is_numeric($token) && (float)$token > 0) {
            return (float)$token;
        }
    }
    return '';
}

try {
    $branch_code = '';
    $stmt_branch = $conn->prepare("SELECT branch_code FROM branches WHERE branch_name = ? LIMIT 1");
    $stmt_branch->bind_param("s", $branch_name);
    $stmt_branch->execute();
    $result_branch = $stmt_branch->get_result();
    if ($result_branch && $result_branch->num_rows > 0) {
        $branch_row = $result_branch->fetch_assoc();
        $branch_code = $branch_row['branch_code'];
    }
    $stmt_branch->close();

    if ($branch_code === '') {
        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid branch selected']);
        exit;
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
            
            // Collect all methods used for this invoice/payment record.
            // NOTE: $pd['Payment Method'] can contain raw DP checkbox values like 'gcash' or 'maya'
            // (because the "Payment Method:" label in the STO DP section is used as the key by collectData).
            // We must skip those values so they don't appear as top-level payment methods.
            $dpRawValues = ['cash', 'gcash', 'maya'];
            $paymentMethods = [];
            $usedFallbackPM = true;
            
            // If the invoice was upgraded and we have a unit_payment_map for the original split payment,
            // we should only keep the payment methods of the units that were NOT upgraded.
            if (($row['upgrade'] === 'UPGD' || $row['old_unit_amount'] > 0) && !empty($pd['unit_payment_map'])) {
                $hasActiveOriginals = false;
                $invNo = $row['invoice_no'];
                $itemSt = $conn->prepare("
                    SELECT sei.item_code, sei.item_description, sei.imei,
                    (SELECT COUNT(*) FROM upgrade_old_items uoi
                     JOIN upgrades u ON u.id = uoi.upgrade_id
                     WHERE u.original_invoice_no = ?
                       AND (
                           (IFNULL(TRIM(uoi.imei), '') = '' AND IFNULL(TRIM(sei.imei), '') = '')
                           OR UPPER(TRIM(uoi.imei)) = UPPER(TRIM(sei.imei))
                       )
                    ) > 0 AS is_upgraded
                    FROM sales_entry_items sei 
                    WHERE sei.sales_entry_id = (SELECT id FROM sales_entry WHERE invoice_no = ? LIMIT 1)
                ");
                $itemSt->bind_param("ss", $invNo, $invNo);
                $itemSt->execute();
                $itemsRes = $itemSt->get_result();
                
                while($it = $itemsRes->fetch_assoc()) {
                    if (!$it['is_upgraded']) {
                        $hasActiveOriginals = true;
                        $imei = strtoupper(trim($it['imei'] ?? ''));
                        $desc = strtoupper(trim($it['item_description'] ?: $it['item_code']));
                        $matchedMethod = '';
                        // Find the method for this non-upgraded unit
                        foreach($pd['unit_payment_map'] as $key => $method) {
                            $keyUpper = strtoupper($key);
                            if ($imei && strpos($keyUpper, $imei) !== false) { $matchedMethod = $method; break; }
                            if ($desc && strpos($keyUpper, $desc) === 0) { $matchedMethod = $method; break; }
                        }
                        if ($matchedMethod) {
                            appendUniqueMethods($paymentMethods, $matchedMethod, $pd);
                        }
                    }
                }
                $itemSt->close();
                
                if ($hasActiveOriginals) {
                    $usedFallbackPM = false; // We successfully built the precise remaining methods
                } else if ($itemsRes->num_rows > 0) {
                    // All units were upgraded. The original sale methods should be completely dropped.
                    $usedFallbackPM = false;
                }
            }

            // Fallback: If no upgrade or no unit_payment_map, just append everything from the original sale
            if ($usedFallbackPM) {
                appendUniqueMethods($paymentMethods, $pd['payment_type'] ?? '', $pd);
                appendUniqueMethods($paymentMethods, $pd['payment_method'] ?? '', $pd);
                $pmVal = trim((string)($pd['Payment Method'] ?? ''));
                if ($pmVal !== '' && !in_array(strtolower($pmVal), $dpRawValues)) {
                    appendUniqueMethods($paymentMethods, $pmVal, $pd);
                }
            }

            // Always append the upgrade's new payment methods
            appendUniqueMethods($paymentMethods, $upgradePd['payment_type'] ?? '', $upgradePd);
            appendUniqueMethods($paymentMethods, $upgradePd['payment_method'] ?? '', $upgradePd);
            $upmVal = trim((string)($upgradePd['Payment Method'] ?? ''));
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
                $candidateRef = trim((string)$candidateRef);
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
                
                if (isset($pd['Loan Balance'])) $loanBalance = $pd['Loan Balance'];
                elseif (isset($pd['Loan Amount'])) $loanBalance = $pd['Loan Amount'];
                elseif (isset($pd['Amount Financed'])) $loanBalance = $pd['Amount Financed'];
                
                // --- DP Method ---
                // collectData() uses input.id as key, so the actual saved keys are:
                //   cash_down_payment_amount, gcash_down_payment_amount, maya_down_payment_amount,
                //   gcash_down_payment_reference, maya_down_payment_reference, down_payment_method
                $dpMethods = [];
                if (!empty($pd['down_payment_method'])) {
                    // Could be "cash", "gcash", "maya" or comma-separated combinations
                    $rawDpMethods = preg_split('/\s*[,\/]\s*/', (string)$pd['down_payment_method']);
                    foreach ($rawDpMethods as $m) {
                        $m = trim($m);
                        if (strcasecmp($m, 'cash') === 0) $dpMethods[] = 'Cash';
                        elseif (strcasecmp($m, 'gcash') === 0) $dpMethods[] = 'G-Cash';
                        elseif (strcasecmp($m, 'maya') === 0) $dpMethods[] = 'Maya';
                        elseif ($m !== '') $dpMethods[] = $m;
                    }
                }
                // Legacy / old keys fallback
                if (empty($dpMethods)) {
                    if (!empty($pd['Downpayment_Payment_Method'])) $dpMethods[] = $pd['Downpayment_Payment_Method'];
                    elseif (!empty($pd['Downpayment Method'])) $dpMethods[] = $pd['Downpayment Method'];
                    elseif (!empty($pd['Down payment method'])) $dpMethods[] = $pd['Down payment method'];
                }
                $dpMethod = implode(' & ', $dpMethods);

                // --- DP Ref ---
                $dpRefParts = [];
                if (!empty($pd['gcash_down_payment_reference'])) $dpRefParts[] = $pd['gcash_down_payment_reference'];
                if (!empty($pd['maya_down_payment_reference'])) $dpRefParts[] = $pd['maya_down_payment_reference'];
                // Legacy keys
                if (empty($dpRefParts)) {
                    if (!empty($pd['Downpayment_Ref'])) $dpRefParts[] = $pd['Downpayment_Ref'];
                    elseif (!empty($pd['Downpayment Reference'])) $dpRefParts[] = $pd['Downpayment Reference'];
                }
                $dpRef = implode(' | ', $dpRefParts);

                // --- DP Amount ---
                // Sum all individual DP amounts that were saved by their input IDs
                $dpTotal = 0.0;
                if (!empty($pd['cash_down_payment_amount'])) {
                    $v = extractNumericFromMixed((string)$pd['cash_down_payment_amount']);
                    if ($v !== '') $dpTotal += (float)$v;
                }
                if (!empty($pd['gcash_down_payment_amount'])) {
                    $v = extractNumericFromMixed((string)$pd['gcash_down_payment_amount']);
                    if ($v !== '') $dpTotal += (float)$v;
                }
                if (!empty($pd['maya_down_payment_amount'])) {
                    $v = extractNumericFromMixed((string)$pd['maya_down_payment_amount']);
                    if ($v !== '') $dpTotal += (float)$v;
                }
                // Legacy keys fallback
                if ($dpTotal == 0) {
                    $dpRaw = '';
                    if (!empty($pd['Downpayment_Amount'])) $dpRaw = $pd['Downpayment_Amount'];
                    elseif (!empty($pd['Down Payment'])) $dpRaw = $pd['Down Payment'];
                    elseif (!empty($pd['Down payment'])) $dpRaw = $pd['Down payment'];
                    elseif (!empty($pd['Downpayment'])) $dpRaw = $pd['Downpayment'];
                    elseif (!empty($pd['Downpayment Amount'])) $dpRaw = $pd['Downpayment Amount'];
                    elseif (!empty($pd['Enter Amount'])) $dpRaw = $pd['Enter Amount'];
                    $legacyVal = extractNumericFromMixed($dpRaw);
                    if ($legacyVal !== '') $dpTotal = (float)$legacyVal;
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

            // NOTE: DP method is intentionally NOT added to paymentMethods — it is a sub-method of the
            // payment partner (e.g. STO ninio), not a separate top-level payment method.

            // Fallback when no structured payment method exists
            if (count($paymentMethods) === 0) {
                appendUniqueMethods($paymentMethods, 'Cash', $pd);
            }
            // Format with '&' separator (Oxford comma for 3+)
            $methodValues = array_values($paymentMethods);
            $count = count($methodValues);
            if ($count === 0) {
                $paymentMethod = '';
            } elseif ($count === 1) {
                $paymentMethod = $methodValues[0];
            } elseif ($count === 2) {
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
                if (isset($pd['Amount'])) $candidateAmount = $pd['Amount'];
                elseif (isset($pd['Enter Amount'])) $candidateAmount = $pd['Enter Amount'];
                elseif (isset($pd['Cash Amount'])) $candidateAmount = $pd['Cash Amount'];

                // Handle pipe-separated amounts from split payments e.g. "156000 | 0"
                $candidateAmount = is_string($candidateAmount) ? str_replace(',', '', $candidateAmount) : (string)$candidateAmount;
                $pipeParts = explode('|', $candidateAmount);
                foreach ($pipeParts as $pipePart) {
                    $pipePart = trim($pipePart);
                    if ($pipePart !== '' && is_numeric($pipePart)) {
                        $amountParts[] = (float)$pipePart;
                    }
                }
            }
            if (count($amountParts) > 0) {
                $modalAmount = array_sum($amountParts);
            }

            if ($loanType !== '') {
                $loanType = ucwords(str_replace('_', ' ', $loanType));
            }

            // Determine the correct total amount to display.
            // Use se.total_amount as the base.
            // For new upgrade invoices (original_invoice_no is set), deduct trade-in discount (less_amount)
            $displayTotalAmount = round(floatval($row['total_amount']));
            if (!empty($row['original_invoice_no'])) {
                $discount = floatval($row['discount'] ?? 0);
                $displayTotalAmount = round(floatval($row['total_amount']) - $discount);
            }

            if ($loanType !== '' && !empty($pd['Total'])) {
                // This is a loan transaction - use Total from payment_data
                $totalFromPaymentData = str_replace(',', '', $pd['Total']);
                if (is_numeric($totalFromPaymentData) && (float)$totalFromPaymentData > 0) {
                    $displayTotalAmount = round((float)$totalFromPaymentData);
                }
            } elseif (!empty($pd['totalLoanAmount'])) {
                // Alternative: check for totalLoanAmount field
                $totalLoanAmt = str_replace(',', '', $pd['totalLoanAmount']);
                if (is_numeric($totalLoanAmt) && (float)$totalLoanAmt > 0) {
                    $displayTotalAmount = round((float)$totalLoanAmt);
                }
            }

            if ($modalAmount === '' || $modalAmount == 0 || !empty($row['original_invoice_no'])) {
                $modalAmount = $displayTotalAmount;
            }

            // Get SRP (invoice_items_total is the sum of item prices which is the SRP)
            $srpAmount = round($row['invoice_items_total']);

            $rows[] = [
                'invoice_no' => $row['invoice_no'],
                'customer_name' => $customerName,
                'srp_amount' => $srpAmount,
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

    // ── Include preorder payments in daily sales pay-type report ──────────────
    function computeDSPOPaidAmount($pd_json) {
        $amount = 0.0;
        $pd = json_decode($pd_json, true);
        if (!is_array($pd)) return $amount;
        if (isset($pd['payment_type']) && $pd['payment_type'] === 'multiple') {
            foreach ((array)($pd['payments'] ?? []) as $p) {
                $amount += floatval(str_replace(',', '', $p['amount'] ?? 0));
            }
        } else {
            $amount = floatval(str_replace(',', '', $pd['amount'] ?? 0));
        }
        return $amount;
    }

    $po_stmt2 = $conn->prepare("
        SELECT
            p.id,
            p.invoice_no,
            CONCAT(p.first_name, ' ', p.last_name) AS customer_name,
            p.total_amount,
            p.payment_data,
            p.status,
            COALESCE((
                SELECT SUM(COALESCE(pi.quantity, 0) * COALESCE(pi.price, 0))
                FROM preorder_items pi WHERE pi.preorder_id = p.id
            ), 0) AS invoice_items_total
        FROM preorders p
        WHERE DATE(p.created_at) BETWEEN ? AND ?
          AND p.branch_code = ?
          AND (
              p.status NOT IN ('claimed')
              OR (
                  -- Include claimed preorders that were originally partial
                  -- (downpayment should appear on the day they were created)
                  p.status = 'claimed'
                  AND EXISTS (
                      SELECT 1 FROM preorder_payment_history pph
                      WHERE pph.preorder_id = p.id
                      AND pph.payment_sequence > 1
                  )
              )
          )
        ORDER BY p.created_at DESC, p.id DESC
    ");
    $po_stmt2->bind_param("sss", $date_from, $date_to, $branch_code);
    $po_stmt2->execute();
    $po_res2 = $po_stmt2->get_result();

    if ($po_res2) {
        while ($po = $po_res2->fetch_assoc()) {
            $pd = [];
            if (!empty($po['payment_data'])) {
                $pd = json_decode($po['payment_data'], true) ?: [];
            }

            // For claimed preorders that were originally partial: show only the initial downpayment
            if ($po['status'] === 'claimed') {
                $fp_stmt = $conn->prepare("
                    SELECT amount FROM preorder_payment_history
                    WHERE preorder_id = ? ORDER BY payment_sequence ASC LIMIT 1
                ");
                $fp_stmt->bind_param("i", $po['id']);
                $fp_stmt->execute();
                $fp_row = $fp_stmt->get_result()->fetch_assoc();
                $fp_stmt->close();
                $paid = $fp_row ? floatval($fp_row['amount']) : computeDSPOPaidAmount($po['payment_data']);
            } else {
                $paid = computeDSPOPaidAmount($po['payment_data']);
            }

            // Derive payment method label
            $pm = 'Cash';
            if (!empty($pd['payment_type'])) {
                // Use normalizePaymentMethodLabel to handle payment_partners, e-wallet, etc.
                $pm = normalizePaymentMethodLabel($pd['payment_type'], $pd);
            } elseif (!empty($pd['payments'][0]['payment_type'])) {
                // Handle multiple payments
                $firstPayment = $pd['payments'][0];
                $pm = normalizePaymentMethodLabel($firstPayment['payment_type'], $firstPayment);
            }

            // Extract loan details if payment_type is payment_partners
            $loanType = '';
            $loanTerm = '';
            $loanNumber = '';
            $loanBalance = '';
            $dpMethod = '';
            $dpRef = '';
            $dpAmount = '';

            if (!empty($pd['payment_type']) && $pd['payment_type'] === 'payment_partners') {
                // Extract loan details
                if (isset($pd['loan_type'])) {
                    // Format loan type: "standard_loan" -> "Standard Loan"
                    $loanType = ucwords(str_replace('_', ' ', $pd['loan_type']));
                }
                
                // Extract loan terms (remove "months" text if present)
                if (isset($pd['loan_terms'])) {
                    $loanTerm = trim(str_ireplace('month', '', str_ireplace('months', '', $pd['loan_terms'])));
                }
                
                $loanNumber = isset($pd['loan_number']) ? $pd['loan_number'] : '';
                $loanBalance = isset($pd['loan_balance']) ? str_replace(',', '', $pd['loan_balance']) : '';
                
                // Extract down payment details
                if (isset($pd['down_payment_methods']) && is_array($pd['down_payment_methods'])) {
                    // Capitalize down payment methods
                    $dpMethodsFormatted = array_map('ucfirst', $pd['down_payment_methods']);
                    $dpMethod = implode(', ', $dpMethodsFormatted);
                    
                    // Get down payment amounts and references
                    $totalDpAmount = 0;
                    $dpRefs = [];
                    
                    if (in_array('cash', $pd['down_payment_methods']) && isset($pd['cash_dp_amount'])) {
                        $cashAmount = str_replace(',', '', $pd['cash_dp_amount']);
                        $totalDpAmount += (float)$cashAmount;
                    }
                    if (in_array('gcash', $pd['down_payment_methods'])) {
                        if (isset($pd['gcash_dp_amount'])) {
                            $gcashAmount = str_replace(',', '', $pd['gcash_dp_amount']);
                            $totalDpAmount += (float)$gcashAmount;
                        }
                        if (isset($pd['gcash_reference'])) {
                            $dpRefs[] = 'GCash: ' . $pd['gcash_reference'];
                        }
                    }
                    if (in_array('maya', $pd['down_payment_methods'])) {
                        if (isset($pd['maya_dp_amount'])) {
                            $mayaAmount = str_replace(',', '', $pd['maya_dp_amount']);
                            $totalDpAmount += (float)$mayaAmount;
                        }
                        if (isset($pd['maya_reference'])) {
                            $dpRefs[] = 'Maya: ' . $pd['maya_reference'];
                        }
                    }
                    
                    // Send as numeric value for frontend to format
                    $dpAmount = $totalDpAmount > 0 ? $totalDpAmount : '';
                    $dpRef = implode(', ', $dpRefs);
                }
            }

            $rows[] = [
                'invoice_no'          => $po['invoice_no'],
                'customer_name'       => trim($po['customer_name']),
                'srp_amount'          => $po['invoice_items_total'],
                'payment_method'      => $pm,
                'reference_no'        => '',
                'loan_type'           => $loanType,
                'loan_term'           => $loanTerm,
                'loan_number'         => $loanNumber,
                'loan_balance'        => $loanBalance,
                'dp_method'           => $dpMethod,
                'dp_ref'              => $dpRef,
                'dp_amount'           => $dpAmount,
                'payment_amount'      => number_format($paid, 2),
                'total_amount'        => $paid, // Actual payment made (downpayment for partial, full for others)
                'invoice_items_total' => $po['invoice_items_total'],
                'old_unit_amount'     => 0,
                'refund_amount'       => 0,
                'upgrade'             => '',
                'display_status'      => 'completed',
            ];
        }
    }
    $po_stmt2->close();

    if (ob_get_length()) ob_clean();
    echo json_encode([
        'status' => 'success',
        'rows'   => $rows
    ]);
} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Error fetching report: ' . $e->getMessage()
    ]);
}

$conn->close();
if (ob_get_length()) ob_end_flush();
?>
