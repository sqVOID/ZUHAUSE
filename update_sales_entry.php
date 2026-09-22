<?php
require_once 'session_check.php';
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_clean();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data received']);
    exit;
}

// Validate required fields
if (empty($data['sales_entry_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sales entry ID is required']);
    exit;
}

if (empty($data['assisted_by'])) {
    echo json_encode(['status' => 'error', 'message' => 'Assisted By is required']);
    exit;
}

if (empty($data['items']) || count($data['items']) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please add at least one item']);
    exit;
}

if (empty($data['reason_to_modify']) || trim($data['reason_to_modify']) === '') {
    echo json_encode(['status' => 'error', 'message' => 'Reason to Modify is required']);
    exit;
}

$sales_entry_id = intval($data['sales_entry_id']);

// Get the original sales entry to restore stock later
$original_query = $conn->prepare("SELECT * FROM sales_entry WHERE id = ?");
$original_query->bind_param("i", $sales_entry_id);
$original_query->execute();
$original_result = $original_query->get_result();

if ($original_result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Sales entry not found']);
    exit;
}

$original_entry = $original_result->fetch_assoc();
$branch_code = $original_entry['branch_code'];

// Get original items to restore stock
$original_items_query = $conn->prepare("SELECT * FROM sales_entry_items WHERE sales_entry_id = ?");
$original_items_query->bind_param("i", $sales_entry_id);
$original_items_query->execute();
$original_items_result = $original_items_query->get_result();

$original_items = [];
while ($item = $original_items_result->fetch_assoc()) {
    $original_items[] = $item;
}

// Get branch name for stock operations
$stock_branch_name = '';
$branch_name_query = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = ? OR branch_name = ? LIMIT 1");
$branch_name_query->bind_param("ss", $branch_code, $branch_code);
$branch_name_query->execute();
$branch_name_result = $branch_name_query->get_result();
if ($branch_name_result && $branch_name_result->num_rows > 0) {
    $branch_data = $branch_name_result->fetch_assoc();
    $stock_branch_name = trim($branch_data['branch_name']);
}
if (empty($stock_branch_name) && isset($_SESSION['user_branch'])) {
    $stock_branch_name = trim($_SESSION['user_branch']);
}

// Prepare payment data
$payment_data_raw = isset($data['payment_data']) ? $data['payment_data'] : null;
if (is_string($payment_data_raw)) {
    $decoded_payment = json_decode($payment_data_raw, true);
    $payment_data_raw = is_array($decoded_payment) ? $decoded_payment : null;
}

if (is_array($payment_data_raw)) {
    $normalize_payment_keys = function (&$pd) use (&$normalize_payment_keys) {
        if (!is_array($pd)) {
            return;
        }
        if (isset($pd['payments']) && is_array($pd['payments'])) {
            foreach ($pd['payments'] as &$payment_block) {
                $normalize_payment_keys($payment_block);
            }
            unset($payment_block);
            return;
        }
        if (!empty($pd['loanTypeDropdown']) && empty($pd['Loan Type'])) {
            $pd['Loan Type'] = $pd['loanTypeDropdown'];
        }
        if (isset($pd['loanTypeDropdown'])) {
            unset($pd['loanTypeDropdown']);
        }
        if (!empty($pd['creditCardAmount']) && empty($pd['Amount'])) {
            $pd['Amount'] = $pd['creditCardAmount'];
        }
        if (!empty($pd['debitCardAmount']) && empty($pd['Amount'])) {
            $pd['Amount'] = $pd['debitCardAmount'];
        }
        if (isset($pd['creditCardAmount'])) {
            unset($pd['creditCardAmount']);
        }
        if (isset($pd['debitCardAmount'])) {
            unset($pd['debitCardAmount']);
        }
        if (!empty($pd['cash_down_payment_amount']) && empty($pd['cash_dp_amount'])) {
            $pd['cash_dp_amount'] = $pd['cash_down_payment_amount'];
        }
        if (!empty($pd['gcash_down_payment_amount']) && empty($pd['gcash_dp_amount'])) {
            $pd['gcash_dp_amount'] = $pd['gcash_down_payment_amount'];
        }
        if (!empty($pd['maya_down_payment_amount']) && empty($pd['maya_dp_amount'])) {
            $pd['maya_dp_amount'] = $pd['maya_down_payment_amount'];
        }
        if (empty($pd['Total']) && !empty($pd['Amount'])) {
            $pd['Total'] = $pd['Amount'];
        }
        if (empty($pd['amount']) && !empty($pd['Amount'])) {
            $pd['amount'] = $pd['Amount'];
        }
    };
    $normalize_payment_keys($payment_data_raw);
}

$payment_data_json = $payment_data_raw ? json_encode($payment_data_raw) : null;

// Extract cash payments
$cash_payments = 0;
if ($payment_data_raw && is_array($payment_data_raw) && isset($payment_data_raw['payment_type'])) {
    if (strtolower($payment_data_raw['payment_type']) === 'cash') {
        foreach (['Amount', 'amount', 'Total', 'total'] as $k) {
            if (!empty($payment_data_raw[$k])) {
                $cash_payments = floatval(str_replace(',', '', $payment_data_raw[$k]));
                break;
            }
        }
    }
}
if (isset($data['cash_payments']) && $data['cash_payments'] > 0) {
    $cash_payments = floatval($data['cash_payments']);
}

// Extract card/bank type
$card_bank_type = null;
if ($payment_data_raw && is_array($payment_data_raw) && isset($payment_data_raw['payment_type'])) {
    $ptype = $payment_data_raw['payment_type'];
    $bank_keys = ['Bank', 'bank', 'E-Wallet', 'e-wallet'];
    $bank_val = null;
    foreach ($bank_keys as $bk) {
        if (!empty($payment_data_raw[$bk])) {
            $bank_val = $payment_data_raw[$bk];
            break;
        }
    }
    $card_bank_type = $bank_val ? "$ptype – $bank_val" : $ptype;
}
if (isset($data['card_bank_type'])) {
    $card_bank_type = $data['card_bank_type'];
}

$conn->begin_transaction();

try {
    // Step 1: Restore original stock (add back to stock_on_hand)
    $today = date('Y-m-d');
    foreach ($original_items as $orig_item) {
        $orig_imei = isset($orig_item['imei']) ? trim($orig_item['imei']) : '';
        $orig_item_code = isset($orig_item['item_code']) ? trim($orig_item['item_code']) : '';
        $orig_desc = isset($orig_item['item_description']) ? trim($orig_item['item_description']) : '';
        $orig_quantity = intval($orig_item['quantity']);
        $orig_dr_number = !empty($orig_item['dr_number']) ? trim($orig_item['dr_number']) : 'MOD-RESTORE';

        // Fetch full metadata from items table (group_name, department, brand, family_code, has_serial, etc.)
        $group_name = null;
        $department = null;
        $brand = null;
        $family_code = null;
        $has_serial = 0;

        if (!empty($orig_item_code)) {
            $mq = $conn->prepare("SELECT group_name, department, brand, family_code, has_serial, description FROM items WHERE item_code = ? LIMIT 1");
            $mq->bind_param("s", $orig_item_code);
            $mq->execute();
            $meta_res = $mq->get_result();
            if ($meta_res && $meta_res->num_rows > 0) {
                $meta = $meta_res->fetch_assoc();
                $group_name = $meta['group_name'] ?? null;
                $department = $meta['department'] ?? null;
                $brand = $meta['brand'] ?? null;
                $family_code = $meta['family_code'] ?? null;
                $has_serial = !empty($meta['has_serial']) ? intval($meta['has_serial']) : 0;
                if (empty($orig_desc) && !empty($meta['description'])) {
                    $orig_desc = $meta['description'];
                }
            }
            $mq->close();
        }

        if (!empty($orig_imei)) {
            // Restore serialized item
            $check_imei = $conn->prepare("SELECT id FROM stock_on_hand WHERE TRIM(imei) = TRIM(?) LIMIT 1");
            $check_imei->bind_param("s", $orig_imei);
            $check_imei->execute();
            $imei_exists = ($check_imei->get_result()->num_rows > 0);
            $check_imei->close();

            if (!$imei_exists) {
                $restore_stmt = $conn->prepare("
                    INSERT INTO stock_on_hand (
                        item_code, description, group_name, department, brand, family_code,
                        imei, quantity, branch, dr_date, dr_number, system_entry_date, item_type, status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, 1, ?, ?, ?, ?, 'IMEI', 'Active'
                    )
                ");
                $restore_stmt->bind_param(
                    "sssssssssss",
                    $orig_item_code,
                    $orig_desc,
                    $group_name,
                    $department,
                    $brand,
                    $family_code,
                    $orig_imei,
                    $stock_branch_name,
                    $today,
                    $orig_dr_number,
                    $today
                );
                $restore_stmt->execute();
                $restore_stmt->close();
            }
        } else {
            // Restore non-serialized item (increase quantity)
            $check_stock = $conn->prepare("
                SELECT id, quantity FROM stock_on_hand 
                WHERE TRIM(item_code) = TRIM(?) 
                  AND branch = ? 
                  AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active')
                ORDER BY (dr_number = ?) DESC, id ASC
                LIMIT 1
            ");
            $check_stock->bind_param("sss", $orig_item_code, $stock_branch_name, $orig_dr_number);
            $check_stock->execute();
            $check_result = $check_stock->get_result();

            if ($check_result && $check_result->num_rows > 0) {
                $stock_row = $check_result->fetch_assoc();
                $stock_id = $stock_row['id'];
                $update_stock = $conn->prepare("
                    UPDATE stock_on_hand 
                    SET quantity = quantity + ?
                    WHERE id = ?
                ");
                $update_stock->bind_param("ii", $orig_quantity, $stock_id);
                $update_stock->execute();
                $update_stock->close();
            } else {
                $item_type = $has_serial ? 'IMEI' : 'Unit';
                $insert_stock = $conn->prepare("
                    INSERT INTO stock_on_hand (
                        item_code, description, group_name, department, brand, family_code,
                        imei, quantity, branch, dr_date, dr_number, system_entry_date, item_type, status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        NULL, ?, ?, ?, ?, ?, ?, 'Active'
                    )
                ");
                $insert_stock->bind_param(
                    "ssssssisssss",
                    $orig_item_code,
                    $orig_desc,
                    $group_name,
                    $department,
                    $brand,
                    $family_code,
                    $orig_quantity,
                    $stock_branch_name,
                    $today,
                    $orig_dr_number,
                    $today,
                    $item_type
                );
                $insert_stock->execute();
                $insert_stock->close();
            }
            $check_stock->close();
        }
    }

    // Step 2: Delete old items from sales_entry_items
    $delete_items = $conn->prepare("DELETE FROM sales_entry_items WHERE sales_entry_id = ?");
    $delete_items->bind_param("i", $sales_entry_id);
    $delete_items->execute();
    $delete_items->close();

    // Step 3: Update main sales entry (including invoice_no and promo)
    $update_stmt = $conn->prepare("
        UPDATE sales_entry SET
            invoice_no = ?,
            original_invoice_no = ?,
            first_name = ?,
            last_name = ?,
            address = ?,
            contact_no = ?,
            email = ?,
            assisted_by = ?,
            remarks = ?,
            reason_to_modify = ?,
            promo_id = ?,
            promo_usage_number = ?,
            total_qty = ?,
            discount = ?,
            voucher_amount = ?,
            total_amount = ?,
            points = ?,
            commission = ?,
            payment_data = ?,
            cash_payments = ?,
            card_bank_type = ?
        WHERE id = ?
    ");

    $invoice_no = isset($data['invoice_no']) ? $data['invoice_no'] : '';
    $original_invoice_no = isset($data['original_invoice_no']) ? $data['original_invoice_no'] : '';
    // Prevent overwriting invoice numbers with empty values
    if (empty($invoice_no)) {
        $invoice_no = isset($original_entry['invoice_no']) ? $original_entry['invoice_no'] : '';
    }
    if (empty($original_invoice_no)) {
        $original_invoice_no = isset($original_entry['original_invoice_no']) ? $original_entry['original_invoice_no'] : '';
    }
    $first_name = isset($data['first_name']) ? strtoupper($data['first_name']) : '';
    $last_name = isset($data['last_name']) ? strtoupper($data['last_name']) : '';
    $address = isset($data['address']) ? $data['address'] : '';
    $contact_no = isset($data['contact_no']) ? $data['contact_no'] : '';
    $email = isset($data['email']) ? $data['email'] : '';
    $assisted_by = $data['assisted_by'];
    $remarks = isset($data['remarks']) ? strtoupper($data['remarks']) : '';
    $reason_to_modify = isset($data['reason_to_modify']) ? strtoupper($data['reason_to_modify']) : '';
    $promo_id = isset($data['applied_promo']) && !empty($data['applied_promo']) ? intval($data['applied_promo']) : null;
    $promo_usage_number = isset($data['promo_usage_number']) && trim($data['promo_usage_number']) !== '' ? trim($data['promo_usage_number']) : null;
    $total_qty = $data['total_qty'] ?? 0;
    $discount = $data['discount'] ?? 0;
    $voucher_amount = $data['voucher_amount'] ?? 0;
    $total_amount = $data['total_amount'] ?? 0;
    $points = $data['points'] ?? 0;
    $commission = $data['commission'] ?? 0;

    $update_stmt->bind_param(
        "ssssssssssisidddddsdsi",
        $invoice_no,
        $original_invoice_no,
        $first_name,
        $last_name,
        $address,
        $contact_no,
        $email,
        $assisted_by,
        $remarks,
        $reason_to_modify,
        $promo_id,
        $promo_usage_number,
        $total_qty,
        $discount,
        $voucher_amount,
        $total_amount,
        $points,
        $commission,
        $payment_data_json,
        $cash_payments,
        $card_bank_type,
        $sales_entry_id
    );

    $update_stmt->execute();
    $update_stmt->close();

    // Step 3a: If invoice numbers were overridden, update matching preorders invoice columns.
    // This is needed so `preorderreport.php` shows the edited Invoice No's.
    $old_original_invoice_no = isset($original_entry['original_invoice_no']) ? trim($original_entry['original_invoice_no']) : '';
    $old_invoice_no = isset($original_entry['invoice_no']) ? trim($original_entry['invoice_no']) : '';
    $new_original_invoice_no = !empty($original_invoice_no) ? trim($original_invoice_no) : '';
    $new_invoice_no = !empty($invoice_no) ? trim($invoice_no) : '';

    if (!empty($old_original_invoice_no) && !empty($new_original_invoice_no) && $old_original_invoice_no !== $new_original_invoice_no) {
        $stmt_po_inv = $conn->prepare("UPDATE preorders SET invoice_no = ? WHERE invoice_no = ?");
        $stmt_po_inv->bind_param("ss", $new_original_invoice_no, $old_original_invoice_no);
        $stmt_po_inv->execute();
        $stmt_po_inv->close();
    }

    if (!empty($old_invoice_no) && !empty($new_invoice_no) && $old_invoice_no !== $new_invoice_no) {
        // Claim invoice is stored in claimed_invoice_no (and sometimes invoice_no depending on legacy data)
        $stmt_po_claimed = $conn->prepare("UPDATE preorders SET claimed_invoice_no = ? WHERE claimed_invoice_no = ?");
        $stmt_po_claimed->bind_param("ss", $new_invoice_no, $old_invoice_no);
        $stmt_po_claimed->execute();
        $stmt_po_claimed->close();

        $stmt_po_invoice = $conn->prepare("UPDATE preorders SET invoice_no = ? WHERE invoice_no = ?");
        $stmt_po_invoice->bind_param("ss", $new_invoice_no, $old_invoice_no);
        $stmt_po_invoice->execute();
        $stmt_po_invoice->close();
    }

    // Stronger invoice update (by preorder row) to handle cases where legacy data stored claim invoice
    // in different columns. This ensures `claimed_invoice_no` is set for the report.
    if (!empty($old_original_invoice_no) && !empty($old_invoice_no) && (!empty($new_original_invoice_no) || !empty($new_invoice_no))) {
        $stmt_preorder_id = $conn->prepare("
            SELECT id
            FROM preorders
            WHERE invoice_no IN (?, ?)
               OR claimed_invoice_no IN (?, ?)
            LIMIT 1
        ");
        $stmt_preorder_id->bind_param(
            "ssss",
            $old_original_invoice_no,
            $old_invoice_no,
            $old_original_invoice_no,
            $old_invoice_no
        );
        $stmt_preorder_id->execute();
        $res_preorder_id = $stmt_preorder_id->get_result();
        if ($res_preorder_id && $res_preorder_id->num_rows > 0) {
            $preorder_row = $res_preorder_id->fetch_assoc();
            $preorder_id = $preorder_row['id'];

            if (!empty($new_original_invoice_no) || !empty($new_invoice_no)) {
                $stmt_po_set = $conn->prepare("
                    UPDATE preorders
                    SET invoice_no = ?,
                        claimed_invoice_no = ?
                    WHERE id = ?
                ");
                $stmt_po_set->bind_param("ssi", $new_original_invoice_no, $new_invoice_no, $preorder_id);
                $stmt_po_set->execute();
                $stmt_po_set->close();
            }
        }
        $stmt_preorder_id->close();
    }

    // Helper function to extract amount from a single payment block
    if (!function_exists('getPaymentBlockAmount')) {
        function getPaymentBlockAmount($payment)
        {
            if (!is_array($payment))
                return 0;
            foreach (['Amount', 'amount', 'Total', 'total'] as $k) {
                if (isset($payment[$k]) && $payment[$k] !== '') {
                    return floatval(str_replace(',', '', $payment[$k]));
                }
            }
            $dp_sum = 0;
            if (isset($payment['payment_type']) && $payment['payment_type'] === 'payment_partners') {
                if (isset($payment['cash_dp_amount'])) {
                    $dp_sum += floatval(str_replace(',', '', $payment['cash_dp_amount']));
                }
                if (isset($payment['gcash_dp_amount'])) {
                    $dp_sum += floatval(str_replace(',', '', $payment['gcash_dp_amount']));
                }
                if (isset($payment['maya_dp_amount'])) {
                    $dp_sum += floatval(str_replace(',', '', $payment['maya_dp_amount']));
                }
            }
            return $dp_sum;
        }
    }

    // Step 3b: If this is a preorder claim, update corresponding preorder records in preorders and preorder_items
    if (!empty($payment_data_json)) {
        $payment_data_decoded = json_decode($payment_data_json, true);
        if (is_array($payment_data_decoded)) {
            if (isset($payment_data_decoded['payment_type']) && $payment_data_decoded['payment_type'] === 'multiple') {
                // Multiple payments should be stored together (not overwritten per block),
                // otherwise report will only show the last payment block.
                $total_paid = 0;
                if (isset($payment_data_decoded['payments']) && is_array($payment_data_decoded['payments'])) {
                    foreach ($payment_data_decoded['payments'] as $payment) {
                        $total_paid += getPaymentBlockAmount($payment);
                    }
                }

                // Update the preorder row once using the overridden invoice numbers.
                $check_preorder = $conn->prepare("SELECT id FROM preorders WHERE invoice_no = ? OR claimed_invoice_no = ? LIMIT 1");
                $check_preorder->bind_param("ss", $new_original_invoice_no, $new_invoice_no);
                $check_preorder->execute();
                $preorder_res = $check_preorder->get_result();

                if ($preorder_res && $preorder_res->num_rows > 0) {
                    $preorder_row = $preorder_res->fetch_assoc();
                    $preorder_id = (int)$preorder_row['id'];

                    // Update preorder customer details and full payment_data (multiple structure)
                    $update_preorder = $conn->prepare("
                        UPDATE preorders SET
                            first_name = ?,
                            last_name = ?,
                            address = ?,
                            contact_no = ?,
                            email = ?,
                            assisted_by = ?,
                            payment_data = ?
                        WHERE id = ?
                    ");
                    $update_preorder->bind_param(
                        "sssssssi",
                        $first_name,
                        $last_name,
                        $address,
                        $contact_no,
                        $email,
                        $assisted_by,
                        $payment_data_json,
                        $preorder_id
                    );
                    $update_preorder->execute();
                    $update_preorder->close();

                    // Update preorder_items paid totals
                    $update_preorder_item = $conn->prepare("
                        UPDATE preorder_items SET
                            amount_paid = ?,
                            total_payment = ?
                        WHERE preorder_id = ?
                    ");
                    $update_preorder_item->bind_param("ddi", $total_paid, $total_paid, $preorder_id);
                    $update_preorder_item->execute();
                    $update_preorder_item->close();
                }
                $check_preorder->close();
            } else {
                // Single payment: check if a preorder exists with the current invoice_no
                $check_preorder = $conn->prepare("SELECT id FROM preorders WHERE invoice_no = ? OR claimed_invoice_no = ?");
                $check_preorder->bind_param("ss", $invoice_no, $invoice_no);
                $check_preorder->execute();
                $preorder_res = $check_preorder->get_result();

                if ($preorder_res && $preorder_res->num_rows > 0) {
                    $preorder_row = $preorder_res->fetch_assoc();
                    $preorder_id = $preorder_row['id'];
                    $payment_amt_val = getPaymentBlockAmount($payment_data_decoded);

                    $update_preorder = $conn->prepare("
                        UPDATE preorders SET
                            first_name = ?,
                            last_name = ?,
                            address = ?,
                            contact_no = ?,
                            email = ?,
                            assisted_by = ?,
                            payment_data = ?
                        WHERE id = ?
                    ");
                    $update_preorder->bind_param(
                        "sssssssi",
                        $first_name,
                        $last_name,
                        $address,
                        $contact_no,
                        $email,
                        $assisted_by,
                        $payment_data_json,
                        $preorder_id
                    );
                    $update_preorder->execute();
                    $update_preorder->close();

                    // Update preorder_items amount_paid and total_payment
                    $update_preorder_item = $conn->prepare("
                        UPDATE preorder_items SET
                            amount_paid = ?,
                            total_payment = ?
                        WHERE preorder_id = ?
                    ");
                    $update_preorder_item->bind_param("ddi", $payment_amt_val, $payment_amt_val, $preorder_id);
                    $update_preorder_item->execute();
                    $update_preorder_item->close();
                }
                $check_preorder->close();
            }
        }
    }

    // Step 4: Insert new items and deduct from stock
    $stmt_items = $conn->prepare("
        INSERT INTO sales_entry_items (
            sales_entry_id,
            item_description,
            imei,
            quantity,
            price,
            item_code,
            dr_number,
            is_promo_item
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($data['items'] as $item) {
        $item_description = strtoupper($item['item_description']);
        $imei = isset($item['imei']) ? trim(strtoupper($item['imei'])) : '';
        $quantity = $item['quantity'];
        $price = $item['price'];
        $item_code = isset($item['item_code']) ? trim(strtoupper($item['item_code'])) : '';
        $is_promo_item = isset($item['is_promo_item']) ? intval($item['is_promo_item']) : 0;

        // Capture DR number before stock deduction
        $original_dr_number = '';
        if (!empty($imei)) {
            $dr_lookup = $conn->prepare("SELECT dr_number, item_code FROM stock_on_hand WHERE TRIM(imei) = TRIM(?) LIMIT 1");
            $dr_lookup->bind_param("s", $imei);
            $dr_lookup->execute();
            $dr_row = $dr_lookup->get_result()->fetch_assoc();
            $dr_lookup->close();
            if ($dr_row) {
                if (!empty($dr_row['dr_number'])) {
                    $original_dr_number = $dr_row['dr_number'];
                }
                if (empty($item_code) && !empty($dr_row['item_code'])) {
                    $item_code = $dr_row['item_code'];
                }
            }
        } else {
            if (!empty($stock_branch_name) && strtolower($stock_branch_name) !== 'all branches') {
                $dr_lookup = $conn->prepare("
                    SELECT dr_number
                    FROM stock_on_hand
                    WHERE TRIM(item_code) = TRIM(?)
                      AND branch = ?
                      AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active')
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
                      AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active')
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

        $stmt_items->bind_param(
            "issidssi",
            $sales_entry_id,
            $item_description,
            $imei,
            $quantity,
            $price,
            $item_code,
            $original_dr_number,
            $is_promo_item
        );
        $stmt_items->execute();

        // Deduct from stock
        if (!empty($imei)) {
            // Remove serialized item
            if (!empty($stock_branch_name) && strtolower($stock_branch_name) !== 'all branches') {
                $delete_stock = $conn->prepare("
                    DELETE FROM stock_on_hand
                    WHERE TRIM(imei) = TRIM(?)
                      AND branch = ?
                      AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active')
                    LIMIT 1
                ");
                $delete_stock->bind_param("ss", $imei, $stock_branch_name);
            } else {
                $delete_stock = $conn->prepare("
                    DELETE FROM stock_on_hand
                    WHERE TRIM(imei) = TRIM(?)
                      AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active')
                    LIMIT 1
                ");
                $delete_stock->bind_param("s", $imei);
            }
            $delete_stock->execute();
            if ($delete_stock->affected_rows <= 0) {
                // Try fallback without branch restriction if not affected
                $del_fallback = $conn->prepare("
                    DELETE FROM stock_on_hand
                    WHERE TRIM(imei) = TRIM(?)
                      AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active')
                    LIMIT 1
                ");
                $del_fallback->bind_param("s", $imei);
                $del_fallback->execute();
                if ($del_fallback->affected_rows <= 0) {
                    $del_fallback->close();
                    // Fallback unconditionally by IMEI
                    $del_fallback2 = $conn->prepare("
                        DELETE FROM stock_on_hand
                        WHERE TRIM(imei) = TRIM(?)
                        LIMIT 1
                    ");
                    $del_fallback2->bind_param("s", $imei);
                    $del_fallback2->execute();
                    if ($del_fallback2->affected_rows <= 0) {
                        $del_fallback2->close();
                        $delete_stock->close();
                        throw new Exception("Insufficient stock for serialized item: {$item_code} ({$imei})");
                    }
                    $del_fallback2->close();
                } else {
                    $del_fallback->close();
                }
            }
            $delete_stock->close();
        } else {
            // Reduce quantity for non-serialized item
            if (!empty($stock_branch_name) && strtolower($stock_branch_name) !== 'all branches') {
                $update_stock = $conn->prepare("
                    UPDATE stock_on_hand
                    SET quantity = quantity - ?
                    WHERE TRIM(item_code) = TRIM(?)
                      AND branch = ?
                      AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active')
                      AND quantity >= ?
                    LIMIT 1
                ");
                $update_stock->bind_param("issi", $quantity, $item_code, $stock_branch_name, $quantity);
            } else {
                $update_stock = $conn->prepare("
                    UPDATE stock_on_hand
                    SET quantity = quantity - ?
                    WHERE TRIM(item_code) = TRIM(?)
                      AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active')
                      AND quantity >= ?
                    LIMIT 1
                ");
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

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Sales entry updated successfully!',
        'invoice_no' => $data['invoice_no']
    ]);

} catch (Throwable $e) {
    $conn->rollback();

    if (ob_get_length())
        ob_clean();

    echo json_encode([
        'status' => 'error',
        'message' => 'Error updating sales entry: ' . $e->getMessage()
    ]);
}

$conn->close();
if (ob_get_length())
    ob_end_flush();
?>