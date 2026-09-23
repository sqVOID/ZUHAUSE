<?php
require_once 'session_check.php';
// Suppress output before JSON response
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';
session_start();

ob_clean();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$debug_log = [];
$debug_log[] = "Payload received: " . json_encode($data);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data received', 'debug' => $debug_log]);
    exit;
}

$preorder_no = isset($data['preorder_no']) ? trim($data['preorder_no']) : '';
$claim_items = isset($data['claim_items']) ? $data['claim_items'] : [];
$remarks = isset($data['remarks']) ? trim($data['remarks']) : '';

if (empty($preorder_no)) {
    echo json_encode(['success' => false, 'message' => 'Pre-order number is required', 'debug' => $debug_log]);
    exit;
}
if (empty($claim_items)) {
    echo json_encode(['success' => false, 'message' => 'Please add at least one item to claim', 'debug' => $debug_log]);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Fetch preorder record details
    $debug_log[] = "1. Looking up preorder with invoice_no: {$preorder_no}";
    $stmt = $conn->prepare("SELECT * FROM preorders WHERE invoice_no = ?");
    $stmt->bind_param("s", $preorder_no);
    $stmt->execute();
    $preorder_res = $stmt->get_result();

    if (!$preorder_res || $preorder_res->num_rows === 0) {
        $stmt->close();
        throw new Exception("Pre-order {$preorder_no} not found");
    }

    $preorder = $preorder_res->fetch_assoc();
    $stmt->close();
    $debug_log[] = "Pre-order found. ID: {$preorder['id']}, Status: {$preorder['status']}";

    if ($preorder['status'] !== 'fully paid' && $preorder['status'] !== 'partial') {
        throw new Exception("Pre-order {$preorder_no} must be fully paid or partial before claiming. Current status: " . $preorder['status']);
    }

    // Calculate total amount paid in preorder
    $preorder_total_paid = 0.00;
    $preorder_pd = !empty($preorder['payment_data']) ? json_decode($preorder['payment_data'], true) : null;
    if (is_array($preorder_pd)) {
        if (isset($preorder_pd['payment_type']) && $preorder_pd['payment_type'] === 'multiple') {
            if (isset($preorder_pd['payments']) && is_array($preorder_pd['payments'])) {
                foreach ($preorder_pd['payments'] as $payment) {
                    if (isset($payment['amount'])) {
                        $preorder_total_paid += floatval(str_replace(',', '', $payment['amount']));
                    } elseif (isset($payment['payment_type']) && $payment['payment_type'] === 'payment_partners') {
                        // For payment_partners, add loan_balance + all down payments
                        if (isset($payment['loan_balance'])) {
                            $preorder_total_paid += floatval(str_replace(',', '', $payment['loan_balance']));
                        }
                        if (isset($payment['total_loan_amount']) && !isset($payment['loan_balance'])) {
                            $preorder_total_paid += floatval(str_replace(',', '', $payment['total_loan_amount']));
                        }
                        if (isset($payment['cash_dp_amount'])) {
                            $preorder_total_paid += floatval(str_replace(',', '', $payment['cash_dp_amount']));
                        }
                        if (isset($payment['gcash_dp_amount'])) {
                            $preorder_total_paid += floatval(str_replace(',', '', $payment['gcash_dp_amount']));
                        }
                        if (isset($payment['maya_dp_amount'])) {
                            $preorder_total_paid += floatval(str_replace(',', '', $payment['maya_dp_amount']));
                        }
                    }
                }
            }
        } else {
            if (isset($preorder_pd['amount'])) {
                $preorder_total_paid = floatval(str_replace(',', '', $preorder_pd['amount']));
            } elseif (isset($preorder_pd['payment_type']) && $preorder_pd['payment_type'] === 'payment_partners') {
                // For payment_partners, add loan_balance + all down payments
                if (isset($preorder_pd['loan_balance'])) {
                    $preorder_total_paid += floatval(str_replace(',', '', $preorder_pd['loan_balance']));
                }
                if (isset($preorder_pd['total_loan_amount']) && !isset($preorder_pd['loan_balance'])) {
                    $preorder_total_paid += floatval(str_replace(',', '', $preorder_pd['total_loan_amount']));
                }
                if (isset($preorder_pd['cash_dp_amount'])) {
                    $preorder_total_paid += floatval(str_replace(',', '', $preorder_pd['cash_dp_amount']));
                }
                if (isset($preorder_pd['gcash_dp_amount'])) {
                    $preorder_total_paid += floatval(str_replace(',', '', $preorder_pd['gcash_dp_amount']));
                }
                if (isset($preorder_pd['maya_dp_amount'])) {
                    $preorder_total_paid += floatval(str_replace(',', '', $preorder_pd['maya_dp_amount']));
                }
            }
        }
    }

    $preorder_total_amount = floatval($preorder['total_amount']);
    $remaining_balance = $preorder_total_amount - $preorder_total_paid;
    $is_fully_paid = ($remaining_balance <= 0.01);

    // 2. Merge preorder payment_data with new claim payment_data
    $branch_code = $preorder['branch_code'];
    $new_payment_data = isset($data['payment_data']) ? $data['payment_data'] : null;

    if ($preorder_pd && $new_payment_data && !$is_fully_paid) {
        // Build a list of individual payment entries from both sources
        $merged_payments = [];
        if (isset($preorder_pd['payment_type']) && $preorder_pd['payment_type'] === 'multiple') {
            $merged_payments = array_merge($merged_payments, (array) ($preorder_pd['payments'] ?? []));
        } else {
            $merged_payments[] = $preorder_pd;
        }
        if (isset($new_payment_data['payment_type']) && $new_payment_data['payment_type'] === 'multiple') {
            $merged_payments = array_merge($merged_payments, (array) ($new_payment_data['payments'] ?? []));
        } else {
            $merged_payments[] = $new_payment_data;
        }
        $merged_payment_json = json_encode(['payment_type' => 'multiple', 'payments' => $merged_payments]);
    } elseif ($new_payment_data && !$is_fully_paid) {
        $merged_payment_json = json_encode($new_payment_data);
    } else {
        $merged_payment_json = $preorder['payment_data'];
    }
    $debug_log[] = "2. Merged payment_data: {$merged_payment_json}";

    // 3. Generate Sales Entry Invoice Number (use old preorder invoice number if fully paid)
    // 3. Generate Sales Entry Invoice Number (use old preorder invoice number if fully paid)
    $passed_invoice_no = isset($data['invoice_no']) ? trim($data['invoice_no']) : '';

    if ($is_fully_paid) {
        $sales_invoice_no = $preorder['invoice_no'];
        $debug_log[] = "3. Preorder was fully paid. Reusing preorder invoice no: {$sales_invoice_no}";
    } else {
        include_once 'get_next_invoice_number.php';
        $booklet = getBookletConfig($conn, $branch_code, 'salesentry');

        if ($booklet) {
            // ── VALIDATE BOOKLET RANGE ──────────────────────────────────────────────
            $current_number_numeric = null;
            if ($booklet['booklet_format'] === 'numeric') {
                $parts = explode('-', $booklet['current_number']);
                $current_number_numeric = intval(end($parts));
            } elseif (is_numeric(ltrim($booklet['current_number'], '0') ?: '0')) {
                $current_number_numeric = intval($booklet['current_number']);
            }

            if ($current_number_numeric !== null) {
                $ending_number_numeric = null;
                if ($booklet['booklet_format'] === 'numeric') {
                    $ending_parts = explode('-', $booklet['ending_number']);
                    $ending_number_numeric = intval(end($ending_parts));
                } elseif (is_numeric(ltrim($booklet['ending_number'], '0') ?: '0')) {
                    $ending_number_numeric = intval($booklet['ending_number']);
                }

                if ($ending_number_numeric !== null && $current_number_numeric > $ending_number_numeric) {
                    throw new Exception('Booklet number range exhausted! Current number (' . $booklet['current_number'] . ') has exceeded the ending number (' . $booklet['ending_number'] . '). Please register a new booklet range or contact administrator.');
                }
            }

            $sales_invoice_no = generateInvoiceNumber($booklet);
            if ($booklet['booklet_format'] === 'numeric') {
                $next_number = incrementInvoiceNumber($booklet['current_number'], 'numeric');
                updateInvoiceNumber($conn, $booklet['id'], $next_number);
            } elseif ($booklet['booklet_format'] === 'custom' && is_numeric(ltrim($booklet['current_number'], '0') ?: '0')) {
                $current_num = intval($booklet['current_number']);
                $padding = strlen($booklet['current_number']);
                $next_number = str_pad($current_num + 1, $padding, '0', STR_PAD_LEFT);
                updateInvoiceNumber($conn, $booklet['id'], $next_number);
            }
        } elseif (!empty($passed_invoice_no)) {
            // Check if passed invoice number from UI is available
            $chk_stmt = $conn->prepare("SELECT id FROM sales_entry WHERE invoice_no = ? LIMIT 1");
            $chk_stmt->bind_param("s", $passed_invoice_no);
            $chk_stmt->execute();
            $exists = $chk_stmt->get_result()->num_rows > 0;
            $chk_stmt->close();
            if (!$exists) {
                $sales_invoice_no = $passed_invoice_no;
            }
        }

        // Fallback if not yet set or collided: YYMMDD-BRANCHCODE-NNNNN-PRE
        if (empty($sales_invoice_no)) {
            $year = date('y');
            $month = date('m');
            $day = date('d');
            $escaped_bc = $conn->real_escape_string($branch_code);

            // Look for existing sales_entry invoices to find the true max sequence
            $invoice_query = $conn->query("
                SELECT invoice_no FROM sales_entry
                WHERE branch_code = '$escaped_bc'
                ORDER BY id DESC
            ");
            $max_seq = 0;
            if ($invoice_query) {
                while ($inv_row = $invoice_query->fetch_assoc()) {
                    $inv = $inv_row['invoice_no'];
                    $inv_clean = preg_replace('/-PRE$/i', '', $inv);
                    if (preg_match('/-(\d+)$/', $inv_clean, $m)) {
                        $s_val = intval($m[1]);
                        if ($s_val > $max_seq && $s_val < 100000) {
                            $max_seq = $s_val;
                        }
                    }
                }
            }
            $sequence = $max_seq + 1;

            // Loop until we find an invoice number that is strictly unique
            do {
                $sales_invoice_no = sprintf("%s%s%s-%s-%05d-PRE", $year, $month, $day, $branch_code, $sequence);
                $chk_stmt = $conn->prepare("SELECT id FROM sales_entry WHERE invoice_no = ? LIMIT 1");
                $chk_stmt->bind_param("s", $sales_invoice_no);
                $chk_stmt->execute();
                $exists = $chk_stmt->get_result()->num_rows > 0;
                $chk_stmt->close();
                if ($exists) {
                    $sequence++;
                }
            } while ($exists);
        }
        $debug_log[] = "3. Generated sales invoice number for remaining balance: {$sales_invoice_no}";
    }

    // Get encoder name
    $encoder = '';
    if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
        $encoder = trim($_SESSION['user_name']);
    } else {
        $encoder = $preorder['encoder'] ?: 'System';
    }

    // Calculate total quantity and full SRP amount from all claim items
    // (Must be done BEFORE the UPDATE preorders statement below)
    $sale_total_qty = 0;
    $sale_srp_amount = 0.00;  // full SRP of items claimed
    foreach ($claim_items as $ci) {
        $ci_q = intval($ci['quantity'] ?? 1);
        $ci_p = floatval(str_replace(',', '', $ci['price'] ?? 0));
        $sale_total_qty += $ci_q;
        $sale_srp_amount += ($ci_q * $ci_p);
    }
    if ($sale_total_qty <= 0) {
        $sale_total_qty = $preorder['total_qty'];
    }
    if ($sale_srp_amount <= 0) {
        $sale_srp_amount = floatval($preorder['total_amount']);
    }

    // For the sales_entry total_amount:
    //   - Fully paid preorders: reuse the old invoice, record full SRP
    //   - Partial preorders being claimed: record only the NEW payment made at claim time
    if ($is_fully_paid) {
        $sale_total_amount = $sale_srp_amount;
    } else {
        // Compute the new payment amount from payment_data sent by the client
        $claim_payment_amount = 0.00;
        if ($new_payment_data) {
            if (isset($new_payment_data['payment_type']) && $new_payment_data['payment_type'] === 'multiple') {
                foreach ((array) ($new_payment_data['payments'] ?? []) as $cp) {
                    if (isset($cp['amount'])) {
                        $claim_payment_amount += floatval(str_replace(',', '', $cp['amount']));
                    } elseif (isset($cp['payment_type']) && $cp['payment_type'] === 'payment_partners') {
                        $claim_payment_amount += floatval(str_replace(',', '', $cp['loan_balance'] ?? 0));
                        $claim_payment_amount += floatval(str_replace(',', '', $cp['cash_dp_amount'] ?? 0));
                        $claim_payment_amount += floatval(str_replace(',', '', $cp['gcash_dp_amount'] ?? 0));
                        $claim_payment_amount += floatval(str_replace(',', '', $cp['maya_dp_amount'] ?? 0));
                    }
                }
            } elseif (isset($new_payment_data['amount'])) {
                $claim_payment_amount = floatval(str_replace(',', '', $new_payment_data['amount']));
            } elseif (isset($new_payment_data['payment_type']) && $new_payment_data['payment_type'] === 'payment_partners') {
                $claim_payment_amount += floatval(str_replace(',', '', $new_payment_data['loan_balance'] ?? 0));
                $claim_payment_amount += floatval(str_replace(',', '', $new_payment_data['cash_dp_amount'] ?? 0));
                $claim_payment_amount += floatval(str_replace(',', '', $new_payment_data['gcash_dp_amount'] ?? 0));
                $claim_payment_amount += floatval(str_replace(',', '', $new_payment_data['maya_dp_amount'] ?? 0));
            }
        }
        // Fallback: use computed remaining_balance if client payment not parsed
        $sale_total_amount = ($claim_payment_amount > 0) ? $claim_payment_amount : max(0, $remaining_balance);
    }

    // 2b. Update preorder: status = 'claimed', claimed_invoice_no, claimed_at, total_amount, and total_qty
    $debug_log[] = "2b. Updating preorder status to 'claimed', claimed_invoice_no='{$sales_invoice_no}', claimed_at=NOW(), total_amount={$sale_total_amount}, total_qty={$sale_total_qty} for ID: {$preorder['id']}";
    $update_stmt = $conn->prepare("UPDATE preorders SET status = 'claimed', claimed_invoice_no = ?, claimed_at = NOW(), total_amount = ?, total_qty = ? WHERE id = ?");
    $update_stmt->bind_param("sddi", $sales_invoice_no, $sale_total_amount, $sale_total_qty, $preorder['id']);
    $update_ok = $update_stmt->execute();
    $debug_log[] = "Preorders update execution: " . ($update_ok ? "SUCCESS" : "FAILED") . ", affected_rows: " . $update_stmt->affected_rows;
    $update_stmt->close();

    // 4. Create final sales entry in sales_entry table (copying customer & payment info from pre-order)
    $debug_log[] = "4. Creating sales entry in sales_entry table...";
    $stmt_sale = $conn->prepare("
        INSERT INTO sales_entry (
            invoice_no,
            first_name,
            last_name,
            address,
            contact_no,
            email,
            assisted_by,
            remarks,
            total_qty,
            discount,
            total_amount,
            payment_data,
            branch_code,
            encoder,
            status,
            original_invoice_no,
            page_type,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, 'claimpreorder', NOW())
    ");

    $sale_remarks = trim($preorder['remarks'] . " (CLAIMED PRE-ORDER " . $preorder_no . ") " . $remarks);

    $stmt_sale->bind_param(
        "ssssssssdddssss",
        $sales_invoice_no,
        $preorder['first_name'],
        $preorder['last_name'],
        $preorder['address'],
        $preorder['contact_no'],
        $preorder['email'],
        $preorder['assisted_by'],
        $sale_remarks,
        $sale_total_qty,
        $preorder['discount'],
        $sale_total_amount,
        $merged_payment_json,
        $branch_code,
        $encoder,
        $preorder_no
    );
    $sale_ok = $stmt_sale->execute();
    $sales_entry_id = $conn->insert_id;
    $debug_log[] = "Sales entry insert: " . ($sale_ok ? "SUCCESS" : "FAILED") . ", Inserted ID: {$sales_entry_id}, Qty: {$sale_total_qty}, Amount: {$sale_total_amount}";
    $stmt_sale->close();

    // 5. Insert items into sales_entry_items & update stock_on_hand & update preorder_items with claim details
    $stmt_items = $conn->prepare("
        INSERT INTO sales_entry_items (
            sales_entry_id,
            item_description,
            imei,
            quantity,
            price,
            item_code,
            dr_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    // Get branch name for stock branch matching
    $stock_branch_name = '';
    if (isset($_SESSION['user_branch'])) {
        $stock_branch_name = trim($_SESSION['user_branch']);
    }

    // Get payment info from request
    $payment_data = isset($data['payment_data']) ? $data['payment_data'] : null;
    $payment_status = isset($data['payment_status']) ? $data['payment_status'] : 'pending';
    $payment_method = 'N/A';
    if ($payment_data && isset($payment_data['payment_type'])) {
        $payment_method = $payment_data['payment_type'];
    }

    $used_preorder_item_ids = [];

    foreach ($claim_items as $item) {
        $full_item_description = isset($item['description']) ? trim($item['description']) : '';
        $imei = isset($item['imei']) ? trim(strtoupper($item['imei'])) : '';
        $quantity = intval($item['quantity']);
        $item_code = isset($item['itemCode']) ? trim(strtoupper($item['itemCode'])) : (isset($item['item_code']) ? trim(strtoupper($item['item_code'])) : '');
        $item_price = isset($item['price']) ? floatval($item['price']) : 0.00;

        $debug_log[] = "--- Loop Iteration for item code {$item_code} ---";
        $debug_log[] = "Passed item: " . json_encode($item);

        // Look up the real family_code from the items table using item_code
        $family_code_from_claim = '';
        if (!empty($item_code)) {
            $fc_stmt = $conn->prepare("SELECT family_code FROM items WHERE UPPER(TRIM(item_code)) = ? AND status = 'Active' LIMIT 1");
            $fc_stmt->bind_param("s", $item_code);
            $fc_stmt->execute();
            $fc_res = $fc_stmt->get_result();
            if ($fc_res && $fc_res->num_rows > 0) {
                $family_code_from_claim = strtoupper(trim($fc_res->fetch_assoc()['family_code']));
            }
            $fc_stmt->close();
        }
        // Fallback: also try looking up via IMEI if item_code didn't resolve
        if (empty($family_code_from_claim) && !empty($imei)) {
            $fc_imei_stmt = $conn->prepare("SELECT i.family_code FROM stock_on_hand s LEFT JOIN items i ON UPPER(TRIM(s.item_code)) = UPPER(TRIM(i.item_code)) WHERE TRIM(s.imei) = TRIM(?) LIMIT 1");
            $fc_imei_stmt->bind_param("s", $imei);
            $fc_imei_stmt->execute();
            $fc_imei_res = $fc_imei_stmt->get_result();
            if ($fc_imei_res && $fc_imei_res->num_rows > 0) {
                $family_code_from_claim = strtoupper(trim($fc_imei_res->fetch_assoc()['family_code']));
            }
            $fc_imei_stmt->close();
        }

        // Determine item price: use passed price from claim payload, or fallback to preorder_items if omitted
        $price = $item_price;
        if (!isset($item['price']) || $item['price'] === null) {
            $price_stmt = $conn->prepare("SELECT price FROM preorder_items WHERE preorder_id = ? AND (family_code = ? OR item_code = ?) LIMIT 1");
            $price_stmt->bind_param("iss", $preorder['id'], $family_code_from_claim, $item_code);
            $price_stmt->execute();
            $price_res = $price_stmt->get_result();
            if ($price_res && $price_res->num_rows > 0) {
                $price = floatval($price_res->fetch_assoc()['price']);
            }
            $price_stmt->close();
        }

        $debug_log[] = "RESOLVED family_code_from_claim: " . $family_code_from_claim;
        $debug_log[] = "full_item_description: " . $full_item_description;
        $debug_log[] = "imei: " . $imei;
        $debug_log[] = "quantity: " . $quantity;
        $debug_log[] = "item_code: " . $item_code;
        // Capture original dr_number BEFORE touching stock
        $original_dr_number = '';
        $valid_status_sql = "(LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active' OR LOWER(TRIM(status)) = 'good stock' OR LOWER(TRIM(status)) = 'in stock' OR status IS NULL OR status = '')";

        if (!empty($imei)) {
            $dr_lookup = $conn->prepare("SELECT dr_number FROM stock_on_hand WHERE (TRIM(imei) = TRIM(?) OR TRIM(imei2) = TRIM(?)) AND (TRIM(item_code) = TRIM(?) OR item_code IS NULL OR item_code = '') LIMIT 1");
            $dr_lookup->bind_param("sss", $imei, $imei, $item_code);
            $dr_lookup->execute();
            $dr_row = $dr_lookup->get_result()->fetch_assoc();
            $dr_lookup->close();
            if ($dr_row && !empty($dr_row['dr_number'])) {
                $original_dr_number = $dr_row['dr_number'];
            }
        } else {
            if (!empty($stock_branch_name) && strtolower($stock_branch_name) !== 'all branches') {
                $dr_lookup = $conn->prepare("
                    SELECT dr_number
                    FROM stock_on_hand
                    WHERE TRIM(item_code) = TRIM(?)
                      AND branch = ?
                      AND $valid_status_sql
                      AND quantity >= ?
                    ORDER BY id ASC
                    LIMIT 1
                ");
                $dr_lookup->bind_param("ssi", $item_code, $stock_branch_name, $quantity);
            } else {
                $dr_lookup = $conn->prepare("
                    SELECT dr_number
                    FROM stock_on_hand
                    WHERE TRIM(item_code) = TRIM(?)
                      AND $valid_status_sql
                      AND quantity >= ?
                    ORDER BY id ASC
                    LIMIT 1
                ");
                $dr_lookup->bind_param("si", $item_code, $quantity);
            }
            $dr_lookup->execute();
            $dr_row = $dr_lookup->get_result()->fetch_assoc();
            $dr_lookup->close();
            if ($dr_row && !empty($dr_row['dr_number'])) {
                $original_dr_number = $dr_row['dr_number'];
            }
        }

        // Insert into sales_entry_items using full item description
        $sales_entry_item_desc = !empty($full_item_description) ? $full_item_description : (!empty($item_code) ? $item_code : $family_code_from_claim);
        $stmt_items->bind_param(
            "issidss",
            $sales_entry_id,
            $sales_entry_item_desc,
            $imei,
            $quantity,
            $price,
            $item_code,
            $original_dr_number
        );
        $stmt_items->execute();

        // Calculate amount paid and total payment
        if ($preorder_total_amount > 0 && $price > 0) {
            $item_share = ($price * $quantity) / $preorder_total_amount;
            $item_balance = $item_share * $remaining_balance;
            $item_downpayment = ($price * $quantity) - $item_balance;
            $original_item_total = $price * $quantity;
            $original_amount_paid = $item_downpayment;
        } else {
            $original_item_total = 0.00;
            $original_amount_paid = 0.00;
        }

        // Find an unclaimed preorder_items row to update
        $matched_pi_id = null;
        $not_in_clause = "";
        if (!empty($used_preorder_item_ids)) {
            $not_in_clause = " AND id NOT IN (" . implode(',', array_map('intval', $used_preorder_item_ids)) . ")";
        }

        if (!empty($family_code_from_claim)) {
            $match_stmt = $conn->prepare("SELECT id FROM preorder_items WHERE preorder_id = ? AND family_code = ? AND (claimed_at IS NULL OR status != 'claimed')" . $not_in_clause . " LIMIT 1");
            $match_stmt->bind_param("is", $preorder['id'], $family_code_from_claim);
            $match_stmt->execute();
            $match_res = $match_stmt->get_result();
            if ($match_res && $match_res->num_rows > 0) {
                $matched_pi_id = intval($match_res->fetch_assoc()['id']);
            }
            $match_stmt->close();
        }

        if (!$matched_pi_id) {
            $match_stmt = $conn->prepare("SELECT id FROM preorder_items WHERE preorder_id = ? AND (claimed_at IS NULL OR status != 'claimed')" . $not_in_clause . " LIMIT 1");
            $match_stmt->bind_param("i", $preorder['id']);
            $match_stmt->execute();
            $match_res = $match_stmt->get_result();
            if ($match_res && $match_res->num_rows > 0) {
                $matched_pi_id = intval($match_res->fetch_assoc()['id']);
            }
            $match_stmt->close();
        }

        if ($matched_pi_id) {
            $used_preorder_item_ids[] = $matched_pi_id;
            $update_pi = $conn->prepare("
                UPDATE preorder_items 
                SET item_description = ?,
                    item_code = ?,
                    imei = ?,
                    quantity = ?,
                    price = ?,
                    total_payment = ?,
                    amount_paid = ?,
                    claimed_at = NOW(),
                    status = 'claimed'
                WHERE id = ?
            ");
            $update_pi->bind_param(
                "sssddddi",
                $full_item_description,
                $item_code,
                $imei,
                $quantity,
                $price,
                $original_item_total,
                $original_amount_paid,
                $matched_pi_id
            );
            $exec_ok = $update_pi->execute();
            $update_pi->close();
            $debug_log[] = "Updated existing preorder_items ID: {$matched_pi_id} with desc: {$full_item_description}, qty: {$quantity}, price: {$price}, status: " . ($exec_ok ? "SUCCESS" : "FAILED");
        } else {
            // Extra item not originally in preorder — insert as extra claimed item
            $insert_pi = $conn->prepare("
                INSERT INTO preorder_items (
                    preorder_id,
                    family_code,
                    item_description,
                    item_code,
                    imei,
                    quantity,
                    price,
                    total_payment,
                    amount_paid,
                    claimed_at,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'claimed', NOW())
            ");
            $insert_pi->bind_param(
                "issssdddd",
                $preorder['id'],
                $family_code_from_claim,
                $full_item_description,
                $item_code,
                $imei,
                $quantity,
                $price,
                $original_item_total,
                $original_amount_paid
            );
            $exec_ok = $insert_pi->execute();
            $new_pi_id = $conn->insert_id;
            $used_preorder_item_ids[] = $new_pi_id;
            $insert_pi->close();
            $debug_log[] = "Inserted extra preorder_items ID: {$new_pi_id} with desc: {$full_item_description}, price: {$price}, status: " . ($exec_ok ? "SUCCESS" : "FAILED");
        }

        // Reduce stock on hand
        if (!empty($imei)) {
            // Serialized item: remove exact serial from stock_on_hand
            $user_branches = !empty($stock_branch_name) ? array_map('trim', explode(',', $stock_branch_name)) : [];
            $user_branches = array_filter($user_branches, function ($b) {
                return strtolower($b) !== 'all branches' && !empty($b);
            });

            $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
            $is_admin = in_array(strtolower(str_replace([' ', '_'], '-', $system_level)), ['super-admin', 'superadmin', 'sub-admin', 'subadmin'], true);

            if (!$is_admin && !empty($user_branches)) {
                $placeholders = implode(',', array_fill(0, count($user_branches), '?'));
                $sql = "DELETE FROM stock_on_hand
                        WHERE (TRIM(imei) = TRIM(?) OR TRIM(imei2) = TRIM(?))
                          AND (TRIM(item_code) = TRIM(?) OR item_code IS NULL OR item_code = '')
                          AND branch IN ($placeholders)
                          AND $valid_status_sql
                        LIMIT 1";
                $delete_stock = $conn->prepare($sql);
                $types = "sss" . str_repeat("s", count($user_branches));
                $params = array_merge([$imei, $imei, $item_code], array_values($user_branches));
                $delete_stock->bind_param($types, ...$params);
            } else {
                $sql = "DELETE FROM stock_on_hand
                        WHERE (TRIM(imei) = TRIM(?) OR TRIM(imei2) = TRIM(?))
                          AND (TRIM(item_code) = TRIM(?) OR item_code IS NULL OR item_code = '')
                          AND $valid_status_sql
                        LIMIT 1";
                $delete_stock = $conn->prepare($sql);
                $delete_stock->bind_param("sss", $imei, $imei, $item_code);
            }
            $delete_stock->execute();
            if ($delete_stock->affected_rows <= 0) {
                // Fallback attempt: delete just by IMEI in case item_code slightly differs
                $fallback = $conn->prepare("DELETE FROM stock_on_hand WHERE (TRIM(imei) = TRIM(?) OR TRIM(imei2) = TRIM(?)) AND $valid_status_sql LIMIT 1");
                $fallback->bind_param("ss", $imei, $imei);
                $fallback->execute();
                if ($fallback->affected_rows <= 0) {
                    $fallback->close();
                    $delete_stock->close();
                    throw new Exception("Insufficient stock for serialized item: {$item_code} ({$imei})");
                }
                $fallback->close();
            }
            $delete_stock->close();
        } else {
            // Non-serialized item
            $user_branches = !empty($stock_branch_name) ? array_map('trim', explode(',', $stock_branch_name)) : [];
            $user_branches = array_filter($user_branches, function ($b) {
                return strtolower($b) !== 'all branches' && !empty($b);
            });

            $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
            $is_admin = in_array(strtolower(str_replace([' ', '_'], '-', $system_level)), ['super-admin', 'superadmin', 'sub-admin', 'subadmin'], true);

            if (!$is_admin && !empty($user_branches)) {
                $placeholders = implode(',', array_fill(0, count($user_branches), '?'));
                $sql = "UPDATE stock_on_hand
                        SET quantity = quantity - ?
                        WHERE TRIM(item_code) = TRIM(?)
                          AND branch IN ($placeholders)
                          AND $valid_status_sql
                          AND quantity >= ?
                        LIMIT 1";
                $update_stock = $conn->prepare($sql);
                $types = "is" . str_repeat("s", count($user_branches)) . "i";
                $params = array_merge([$quantity, $item_code], array_values($user_branches), [$quantity]);
                $update_stock->bind_param($types, ...$params);
            } else {
                $sql = "UPDATE stock_on_hand
                        SET quantity = quantity - ?
                        WHERE TRIM(item_code) = TRIM(?)
                          AND $valid_status_sql
                          AND quantity >= ?
                        LIMIT 1";
                $update_stock = $conn->prepare($sql);
                $update_stock->bind_param("isi", $quantity, $item_code, $quantity);
            }
            $update_stock->execute();
            if ($update_stock->affected_rows <= 0) {
                $update_stock->close();
                throw new Exception("Insufficient stock for item: {$item_code}");
            }
            $update_stock->close();
        }
    }
    $stmt_items->close();

    // ── Insert Unclaimed Freebies (if any) ─────────────────────────────────
    if (!empty($data['unclaimed_freebies'])) {
        $debug_log[] = "Inserting unclaimed freebies...";

        // Get user branch for unclaimed freebies
        $user_branch = '';
        if (isset($_SESSION['user_branch'])) {
            $user_branch = trim($_SESSION['user_branch']);
        }

        // Get assisted_by from preorder
        $assisted_by = $preorder['assisted_by'] ?? '';

        $stmt_unclaimed = $conn->prepare("
            INSERT INTO unclaimed_freebies (
                sales_entry_id,
                invoice_number,
                item_code,
                item_description,
                quantity,
                note,
                branch,
                created_by,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unclaimed')
        ");

        foreach ($data['unclaimed_freebies'] as $unclaimed_freebie) {
            $uf_item_code = $unclaimed_freebie['item_code'] ?? '';
            $uf_description = strtoupper($unclaimed_freebie['description'] ?? '');
            $uf_quantity = intval($unclaimed_freebie['quantity'] ?? 0);
            $uf_note = $unclaimed_freebie['note'] ?? '';

            $stmt_unclaimed->bind_param(
                "isssisss",
                $sales_entry_id,
                $sales_invoice_no,
                $uf_item_code,
                $uf_description,
                $uf_quantity,
                $uf_note,
                $user_branch,
                $assisted_by
            );
            $exec_ok = $stmt_unclaimed->execute();
            $debug_log[] = "Inserted unclaimed freebie: {$uf_description} (Qty: {$uf_quantity}) - " . ($exec_ok ? "SUCCESS" : "FAILED");
        }
        $stmt_unclaimed->close();
    }

    // Insert payment history if new payment was made during claim
    if ($new_payment_data && is_array($new_payment_data) && !$is_fully_paid) {
        $debug_log[] = "Inserting payment history for claim payment...";

        $payments_array = [];
        // Handle multiple payments
        if (isset($new_payment_data['payment_type']) && $new_payment_data['payment_type'] === 'multiple') {
            $payments_array = $new_payment_data['payments'];
        } else {
            $payments_array = [$new_payment_data];
        }

        // Get the last payment sequence number for this preorder
        $seq_query = $conn->prepare("SELECT MAX(payment_sequence) as max_seq FROM preorder_payment_history WHERE preorder_id = ?");
        $seq_query->bind_param("i", $preorder['id']);
        $seq_query->execute();
        $seq_result = $seq_query->get_result();
        $last_seq = 0;
        if ($seq_result && $seq_result->num_rows > 0) {
            $seq_row = $seq_result->fetch_assoc();
            $last_seq = intval($seq_row['max_seq']);
        }
        $seq_query->close();

        $sequence = $last_seq + 1;
        $running_balance = $remaining_balance; // Start with remaining balance before this payment

        foreach ($payments_array as $payment) {
            $payment_type = $payment['payment_type'] ?? 'unknown';
            $amount = 0;

            // Calculate amount based on payment type
            if (isset($payment['amount'])) {
                $amount = floatval(str_replace(',', '', $payment['amount']));
            } elseif ($payment_type === 'payment_partners') {
                $loan_balance = isset($payment['loan_balance']) ? floatval(str_replace(',', '', $payment['loan_balance'])) : 0;
                $cash_dp = isset($payment['cash_dp_amount']) ? floatval(str_replace(',', '', $payment['cash_dp_amount'])) : 0;
                $gcash_dp = isset($payment['gcash_dp_amount']) ? floatval(str_replace(',', '', $payment['gcash_dp_amount'])) : 0;
                $maya_dp = isset($payment['maya_dp_amount']) ? floatval(str_replace(',', '', $payment['maya_dp_amount'])) : 0;
                $amount = $loan_balance + $cash_dp + $gcash_dp + $maya_dp;
            }

            if ($amount > 0) {
                // Get payment method details
                $payment_method = 'Unknown';
                if ($payment_type === 'ewallet') {
                    $payment_method = $payment['ewallet_type'] ?? 'E-Wallet';
                } elseif ($payment_type === 'online_banking') {
                    $payment_method = $payment['bank_name'] ?? 'Online Banking';
                } elseif ($payment_type === 'payment_partners') {
                    $pp = $payment['payment_partner'] ?? '';
                    $partner_map = [
                        'partner1' => 'Skyro',
                        'partner2' => 'Home Credit',
                        'partner5' => 'Salmon',
                        'partner6' => 'Samsung Finances',
                        'partner7' => 'Payjoy',
                        'partner8' => 'Billease',
                        'partner9' => 'Paymongo',
                        'partner10' => 'Skyro'
                    ];
                    if (isset($partner_map[strtolower($pp)])) {
                        $payment_method = $partner_map[strtolower($pp)];
                    } else {
                        $payment_method = !empty($pp) ? $pp : 'Payment Partner';
                    }
                    $payment['payment_partner'] = $payment_method;
                } elseif ($payment_type === 'credit_card') {
                    $payment_method = 'Credit Card';
                } elseif ($payment_type === 'debit_card') {
                    $payment_method = 'Debit Card';
                } else {
                    $payment_method = ucfirst(str_replace('_', ' ', $payment_type));
                }

                $balance_before = $running_balance;
                $balance_after = max(0, $running_balance - $amount);
                $running_balance = $balance_after;

                // Status logic: if balance_after > 0, it's still partial; if <=0, it's fully paid
                $status_after = ($balance_after <= 0.01) ? 'fully paid' : 'partial';

                // Payment date is NOW
                $payment_date = date('Y-m-d H:i:s');

                // Insert into payment history
                $history_stmt = $conn->prepare("
                    INSERT INTO preorder_payment_history 
                    (preorder_id, invoice_no, payment_date, payment_type, payment_method, 
                     amount, payment_data, payment_sequence, status_after_payment, 
                     balance_before, balance_after, branch_code, encoder, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $payment_json = json_encode($payment);

                $history_stmt->bind_param(
                    "issssdsisddss",
                    $preorder['id'],
                    $sales_invoice_no,
                    $payment_date,
                    $payment_type,
                    $payment_method,
                    $amount,
                    $payment_json,
                    $sequence,
                    $status_after,
                    $balance_before,
                    $balance_after,
                    $branch_code,
                    $encoder
                );

                $history_stmt->execute();
                $debug_log[] = "Payment history inserted: sequence={$sequence}, amount={$amount}, balance_after={$balance_after}, status={$status_after}";
                $history_stmt->close();

                $sequence++;
            }
        }
    }

    $conn->commit();
    $debug_log[] = "Transaction committed successfully. Sales Invoice: {$sales_invoice_no}";
    file_put_contents(__DIR__ . '/debug_claim.log', "[" . date('Y-m-d H:i:s') . "] SUCCESS: " . implode(" | ", $debug_log) . PHP_EOL, FILE_APPEND);
    echo json_encode(['success' => true, 'sales_invoice_no' => $sales_invoice_no, 'debug' => $debug_log]);

} catch (Exception $e) {
    $conn->rollback();
    $debug_log[] = "Transaction rolled back due to error: " . $e->getMessage();
    file_put_contents(__DIR__ . '/debug_claim.log', "[" . date('Y-m-d H:i:s') . "] ERROR: " . implode(" | ", $debug_log) . PHP_EOL, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debug' => $debug_log]);
}

$conn->close();
?>