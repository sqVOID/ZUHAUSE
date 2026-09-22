<?php
require_once 'session_check.php';

// Suppress output before JSON response
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';

// Clean any output buffer and set JSON header
if (ob_get_length())
    ob_clean();
header('Content-Type: application/json');

try {
    // Ensure status column can accept TRDIN and trade-in columns exist in sales_entry table (idempotent)
    $conn->query("ALTER TABLE sales_entry MODIFY COLUMN status VARCHAR(50) DEFAULT 'completed'");
    $conn->query("ALTER TABLE sales_entry ADD COLUMN IF NOT EXISTS tradein_value DECIMAL(10,2) DEFAULT 0.00");
    $conn->query("ALTER TABLE sales_entry ADD COLUMN IF NOT EXISTS tradein_imei VARCHAR(100) DEFAULT ''");
    $conn->query("ALTER TABLE sales_entry ADD COLUMN IF NOT EXISTS tradein_item_code VARCHAR(100) DEFAULT ''");
    $conn->query("ALTER TABLE sales_entry ADD COLUMN IF NOT EXISTS tradein_brand VARCHAR(100) DEFAULT ''");
    $conn->query("ALTER TABLE sales_entry ADD COLUMN IF NOT EXISTS titu_control VARCHAR(100) DEFAULT ''");
    $conn->query("ALTER TABLE sales_entry ADD COLUMN IF NOT EXISTS titu_token DECIMAL(10,2) DEFAULT 0.00");
    $conn->query("ALTER TABLE sales_entry ADD COLUMN IF NOT EXISTS cross_sell DECIMAL(10,2) DEFAULT 0.00");
    $conn->query("ALTER TABLE sales_entry ADD COLUMN IF NOT EXISTS trade_in_voucher DECIMAL(10,2) DEFAULT 0.00");
    $conn->query("ALTER TABLE sales_entry ADD COLUMN IF NOT EXISTS titu_voucher_total DECIMAL(10,2) DEFAULT 0.00");
    $conn->query("ALTER TABLE sales_entry ADD COLUMN IF NOT EXISTS token DECIMAL(10,2) DEFAULT 0.00");
    $conn->query("ALTER TABLE sales_entry ADD COLUMN IF NOT EXISTS page_type VARCHAR(50) DEFAULT NULL");

    // Ensure dr_number column exists in sales_entry_items (idempotent)
    $conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS dr_number VARCHAR(100) DEFAULT '' AFTER item_code");

    // Get POST data
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    // Check if JSON decode was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data received']);
        exit;
    }

    // Validate required field
    if (empty($data['assisted_by'])) {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Assisted By is required!']);
        exit;
    }

    // Validate that there are items
    if (empty($data['items']) || count($data['items']) == 0) {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Please add at least one item to the sales trade-in entry!']);
        exit;
    }

    // Get branch code from session
    $branch_code = '000'; // Default
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $stock_branch_name = $user_branch;
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
    if (isset($_SESSION['user_branch'])) {
        $branch_name_exact = $user_branch;
        $branch_name_base = trim(preg_replace('/\s*-\s*[A-Za-z0-9]+$/', '', $user_branch));
        if ($branch_name_base === '') {
            $branch_name_base = $branch_name_exact;
        }

        $branch_lookup = $conn->prepare("
            SELECT branch_name, branch_code
            FROM branches
            WHERE branch_name = ? OR branch_name = ?
            ORDER BY (branch_name = ?) DESC
            LIMIT 1
        ");
        if ($branch_lookup) {
            $branch_lookup->bind_param("sss", $branch_name_exact, $branch_name_base, $branch_name_exact);
            $branch_lookup->execute();
            $branch_result = $branch_lookup->get_result();
            if ($branch_result && $branch_result->num_rows > 0) {
                $branch_data = $branch_result->fetch_assoc();
                $branch_code = $branch_data['branch_code'];
                $stock_branch_name = trim($branch_data['branch_name']);
            }
            $branch_lookup->close();
        }
    }

    // Generate Invoice Number using booklet configuration
    include_once 'get_next_invoice_number.php';

    $booklet_id_for_validation = null; // Store booklet ID for cancelled invoice validation

    if (isset($data['invoice_no']) && !empty(trim($data['invoice_no']))) {
        $invoice_no = trim($data['invoice_no']);

        // ── VALIDATE CANCELLED & SKIPPED INVOICE NUMBER FOR LATE ENTRIES ──────────
        // Check both skip_receipt_requests and cancelled_invoices
        $booklet = getBookletConfig($conn, $branch_code, 'salestrade-in');
        if (!$booklet) {
            $booklet = getBookletConfig($conn, $branch_code, 'salesentry');
        }

        $check_invoice = $invoice_no;
        $check_invoice_with_prefix_suffix = '';
        if ($booklet) {
            if (!empty($booklet['prefix'])) {
                $check_invoice_with_prefix_suffix .= $booklet['prefix'];
            }
            $check_invoice_with_prefix_suffix .= $invoice_no;
            if (!empty($booklet['suffix'])) {
                $check_invoice_with_prefix_suffix .= $booklet['suffix'];
            }
        }
        if (empty($check_invoice_with_prefix_suffix)) {
            $check_invoice_with_prefix_suffix = $check_invoice;
        }

        // 1. Check skip_receipt_requests table for pending or approved requests
        $skip_table_check = $conn->query("SHOW TABLES LIKE 'skip_receipt_requests'");
        if ($skip_table_check && $skip_table_check->num_rows > 0) {
            $invoice_no_unpadded = ltrim($invoice_no, '0');
            if (empty($invoice_no_unpadded)) {
                $invoice_no_unpadded = '0';
            }
            $skip_check = $conn->prepare("
                SELECT id, request_id, invoice_no, status, reason, requested_by, created_at 
                FROM skip_receipt_requests 
                WHERE (invoice_no = ? OR invoice_no = ? OR invoice_no = ?) 
                AND status IN ('Pending', 'Approved')
            ");
            $skip_check->bind_param("sss", $check_invoice, $check_invoice_with_prefix_suffix, $invoice_no_unpadded);
            $skip_check->execute();
            $skip_res = $skip_check->get_result();

            if ($skip_res && $skip_res->num_rows > 0) {
                $skip_info = $skip_res->fetch_assoc();
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invoice number "' . $invoice_no . '" has been skipped/cancelled and cannot be used! Please use a different invoice number.'
                ]);
                exit;
            }
            $skip_check->close();
        }

        // 2. Check cancelled_invoices table
        $table_check = $conn->query("SHOW TABLES LIKE 'cancelled_invoices'");
        if ($table_check && $table_check->num_rows > 0) {
            $cancel_check = $conn->prepare("
                SELECT id, cancel_reason, cancelled_by, cancelled_at 
                FROM cancelled_invoices 
                WHERE (invoice_number = ? OR invoice_number = ?)
            ");
            $cancel_check->bind_param("ss", $check_invoice, $check_invoice_with_prefix_suffix);
            $cancel_check->execute();
            $cancel_result = $cancel_check->get_result();

            if ($cancel_result && $cancel_result->num_rows > 0) {
                $cancelled_info = $cancel_result->fetch_assoc();
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invoice number "' . $invoice_no . '" has been cancelled and cannot be used! Please use a different invoice number.'
                ]);
                exit;
            }
            $cancel_check->close();
        }
    } else {
        // Try getting booklet for salestrade-in or fallback to salesentry
        $booklet = getBookletConfig($conn, $branch_code, 'salestrade-in');
        if (!$booklet) {
            $booklet = getBookletConfig($conn, $branch_code, 'salesentry');
        }

        if ($booklet) {
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
                    if (ob_get_length())
                        ob_clean();
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Booklet number range exhausted! Current number (' . $booklet['current_number'] . ') has exceeded ending number (' . $booklet['ending_number'] . ').'
                    ]);
                    exit;
                }
            }

            $invoice_no = generateInvoiceNumber($booklet);

            if ($booklet['booklet_format'] === 'numeric') {
                $next_number = incrementInvoiceNumber($booklet['current_number'], 'numeric');
                updateInvoiceNumber($conn, $booklet['id'], $next_number);
            } elseif ($booklet['booklet_format'] === 'custom' && is_numeric(ltrim($booklet['current_number'], '0') ?: '0')) {
                $current_num = intval($booklet['current_number']);
                $padding = strlen($booklet['current_number']);
                $next_number = str_pad($current_num + 1, $padding, '0', STR_PAD_LEFT);
                updateInvoiceNumber($conn, $booklet['id'], $next_number);
            }
        } else {
            // Fallback invoice generation
            $year = date('y');
            $month = date('m');
            $day = date('d');
            $escaped_branch_code = $conn->real_escape_string($branch_code);
            $invoice_query = $conn->query("
                SELECT invoice_no 
                FROM sales_entry 
                WHERE branch_code = '$escaped_branch_code'
                AND invoice_no REGEXP '^[0-9]{6}-[A-Z0-9]+-[0-9]+$'
                ORDER BY id DESC 
                LIMIT 1
            ");

            if ($invoice_query && $invoice_query->num_rows > 0) {
                $last_invoice = $invoice_query->fetch_assoc()['invoice_no'];
                $sequence = intval(preg_replace('/.*-(\d+)$/', '$1', $last_invoice)) + 1;
            } else {
                $sequence = 1;
            }
            $invoice_no = sprintf("%s%s%s-%s-%05d", $year, $month, $day, $branch_code, $sequence);
        }
    }

    // Payment data extraction
    $payment_data_raw = isset($data['payment_data']) ? $data['payment_data'] : null;
    $payment_data_json = $payment_data_raw ? json_encode($payment_data_raw) : null;

    $cash_payments = 0;
    if ($payment_data_raw && isset($payment_data_raw['payment_type'])) {
        if (strtolower($payment_data_raw['payment_type']) === 'cash') {
            foreach (['Amount', 'amount', 'Total', 'total'] as $k) {
                if (!empty($payment_data_raw[$k])) {
                    $cash_payments = floatval(str_replace(',', '', $payment_data_raw[$k]));
                    break;
                }
            }
        }
    }

    $card_bank_type = null;
    if ($payment_data_raw && isset($payment_data_raw['payment_type'])) {
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

    $encoder = '';
    if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
        $encoder = trim($_SESSION['user_name']);
    } elseif (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
        $encoder = trim($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
    } elseif (isset($_SESSION['username'])) {
        $encoder = trim($_SESSION['username']);
    } else {
        $encoder = isset($data['assisted_by']) ? $data['assisted_by'] : 'System';
    }

    $status = 'completed';

    // Start transaction
    $conn->begin_transaction();

    // Extract Trade-In fields
    $tradein_value = floatval($data['tradein_value'] ?? 0);
    $tradein_imei = strtoupper(trim($data['tradein_imei'] ?? ''));
    $tradein_item_code = strtoupper(trim($data['tradein_item_code'] ?? ''));
    $tradein_brand = strtoupper(trim($data['tradein_brand'] ?? ''));

    // Extract TITU fields
    $titu_control = trim($data['titu_control'] ?? '');
    $titu_token = trim($data['titu_token'] ?? '');
    $cross_sell = floatval($data['cross_sell'] ?? 0);
    $trade_in_voucher = floatval($data['trade_in_voucher'] ?? 0);
    $titu_voucher_total = floatval($data['titu_voucher_total'] ?? 0);

    $stmt = $conn->prepare("
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
            points,
            commission,
            payment_data,
            cash_payments,
            card_bank_type,
            voucher_amount,
            token,
            status,
            branch_code,
            encoder,
            created_at,
            tradein_value,
            tradein_imei,
            tradein_item_code,
            tradein_brand,
            titu_control,
            titu_token,
            cross_sell,
            trade_in_voucher,
            titu_voucher_total,
            page_type
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'salestrade-in')
    ");

    if (!$stmt) {
        throw new Exception("Sales entry prepare failed: " . $conn->error);
    }

    $first_name = isset($data['first_name']) && trim($data['first_name']) !== '' ? strtoupper($data['first_name']) : '0';
    $last_name = isset($data['last_name']) && trim($data['last_name']) !== '' ? strtoupper($data['last_name']) : '';
    $address = isset($data['address']) && trim($data['address']) !== '' ? $data['address'] : '0';
    $contact_no = isset($data['contact_no']) && trim($data['contact_no']) !== '' ? $data['contact_no'] : '0';
    $email = isset($data['email']) && trim($data['email']) !== '' ? $data['email'] : '0';
    $assisted_by = $data['assisted_by'];
    $remarks = isset($data['remarks']) ? strtoupper($data['remarks']) : '';
    $total_qty = intval($data['total_qty'] ?? 0);
    $discount = floatval($data['discount'] ?? 0);
    $total_amount = floatval($data['total_amount'] ?? 0);
    $points = floatval($data['points'] ?? 0);
    $commission = floatval($data['commission'] ?? 0);
    $voucher_amount = floatval($data['voucher_amount'] ?? 0);
    $token = floatval($data['token'] ?? 0);

    // 30 parameters exact mapping:
    // ssssssssiddddsdsddsssdsssssddd
    $stmt->bind_param(
        "ssssssssiddddsdsddsssdsssssddd",
        $invoice_no,
        $first_name,
        $last_name,
        $address,
        $contact_no,
        $email,
        $assisted_by,
        $remarks,
        $total_qty,
        $discount,
        $total_amount,
        $points,
        $commission,
        $payment_data_json,
        $cash_payments,
        $card_bank_type,
        $voucher_amount,
        $token,
        $status,
        $branch_code,
        $encoder,
        $tradein_value,
        $tradein_imei,
        $tradein_item_code,
        $tradein_brand,
        $titu_control,
        $titu_token,
        $cross_sell,
        $trade_in_voucher,
        $titu_voucher_total
    );

    if (!$stmt->execute()) {
        throw new Exception("Sales entry execute failed: " . $stmt->error);
    }
    $sales_entry_id = $conn->insert_id;
    $stmt->close();

    // Insert sales items & reduce stock
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

    if (!$stmt_items) {
        throw new Exception("Sales items prepare failed: " . $conn->error);
    }

    foreach ($data['items'] as $item) {
        $item_description = strtoupper($item['description']);
        $imei = isset($item['imei']) ? trim(strtoupper($item['imei'])) : '';
        $quantity = intval($item['quantity'] ?? 1);
        $price = floatval($item['price'] ?? 0);
        $item_code = isset($item['item_code']) ? trim(strtoupper($item['item_code'])) : '';

        // If serialized item and item_code is missing, attempt to fetch item_code from stock_on_hand by imei
        if (!empty($imei) && empty($item_code)) {
            $code_lookup = $conn->prepare("SELECT item_code FROM stock_on_hand WHERE TRIM(imei) = TRIM(?) LIMIT 1");
            if ($code_lookup) {
                $code_lookup->bind_param("s", $imei);
                $code_lookup->execute();
                $code_res = $code_lookup->get_result()->fetch_assoc();
                $code_lookup->close();
                if ($code_res && !empty($code_res['item_code'])) {
                    $item_code = trim(strtoupper($code_res['item_code']));
                }
            }
        }

        // Capture original dr_number BEFORE touching stock
        $original_dr_number = '';
        if (!empty($imei)) {
            $dr_lookup = $conn->prepare("SELECT dr_number FROM stock_on_hand WHERE TRIM(imei) = TRIM(?) LIMIT 1");
            if ($dr_lookup) {
                $dr_lookup->bind_param("s", $imei);
                $dr_lookup->execute();
                $dr_row = $dr_lookup->get_result()->fetch_assoc();
                $dr_lookup->close();
                if ($dr_row && !empty($dr_row['dr_number'])) {
                    $original_dr_number = $dr_row['dr_number'];
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
                if ($dr_lookup) {
                    $dr_lookup->bind_param("ssi", $item_code, $stock_branch_name, $quantity);
                }
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
                if ($dr_lookup) {
                    $dr_lookup->bind_param("si", $item_code, $quantity);
                }
            }
            if ($dr_lookup) {
                $dr_lookup->execute();
                $dr_row = $dr_lookup->get_result()->fetch_assoc();
                $dr_lookup->close();
                if ($dr_row && !empty($dr_row['dr_number'])) {
                    $original_dr_number = $dr_row['dr_number'];
                }
            }
        }

        $stmt_items->bind_param(
            "issidss",
            $sales_entry_id,
            $item_description,
            $imei,
            $quantity,
            $price,
            $item_code,
            $original_dr_number
        );
        $stmt_items->execute();

        // Reduce stock on hand
        if (!empty($imei)) {
            $deleted = false;

            // Attempt 1: match imei, item_code, and branch
            if (!empty($item_code) && !empty($stock_branch_name) && strtolower($stock_branch_name) !== 'all branches') {
                $delete_stock = $conn->prepare("
                    DELETE FROM stock_on_hand
                    WHERE TRIM(imei) = TRIM(?)
                      AND TRIM(item_code) = TRIM(?)
                      AND branch = ?
                    LIMIT 1
                ");
                if ($delete_stock) {
                    $delete_stock->bind_param("sss", $imei, $item_code, $stock_branch_name);
                    $delete_stock->execute();
                    if ($delete_stock->affected_rows > 0)
                        $deleted = true;
                    $delete_stock->close();
                }
            }

            // Attempt 2: match imei and item_code
            if (!$deleted && !empty($item_code)) {
                $delete_stock = $conn->prepare("
                    DELETE FROM stock_on_hand
                    WHERE TRIM(imei) = TRIM(?)
                      AND TRIM(item_code) = TRIM(?)
                    LIMIT 1
                ");
                if ($delete_stock) {
                    $delete_stock->bind_param("ss", $imei, $item_code);
                    $delete_stock->execute();
                    if ($delete_stock->affected_rows > 0)
                        $deleted = true;
                    $delete_stock->close();
                }
            }

            // Attempt 3: match imei alone
            if (!$deleted) {
                $delete_stock = $conn->prepare("
                    DELETE FROM stock_on_hand
                    WHERE TRIM(imei) = TRIM(?)
                    LIMIT 1
                ");
                if ($delete_stock) {
                    $delete_stock->bind_param("s", $imei);
                    $delete_stock->execute();
                    if ($delete_stock->affected_rows > 0)
                        $deleted = true;
                    $delete_stock->close();
                }
            }

            if (!$deleted) {
                $disp_code = !empty($item_code) ? $item_code : $item_description;
                throw new Exception("Insufficient stock for serialized item: {$disp_code} ({$imei})");
            }
        } else {
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

    // Insert Unclaimed Freebies (if any)
    if (!empty($data['unclaimed_freebies'])) {
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

        if ($stmt_unclaimed) {
            foreach ($data['unclaimed_freebies'] as $unclaimed_freebie) {
                $uf_item_code = $unclaimed_freebie['item_code'] ?? '';
                $uf_description = strtoupper($unclaimed_freebie['description'] ?? '');
                $uf_quantity = intval($unclaimed_freebie['quantity'] ?? 0);
                $uf_note = $unclaimed_freebie['note'] ?? '';
                $uf_created_by = $data['assisted_by'] ?? '';

                $stmt_unclaimed->bind_param(
                    "isssisss",
                    $sales_entry_id,
                    $invoice_no,
                    $uf_item_code,
                    $uf_description,
                    $uf_quantity,
                    $uf_note,
                    $user_branch,
                    $uf_created_by
                );
                $stmt_unclaimed->execute();
            }
            $stmt_unclaimed->close();
        }
    }

    // Commit
    $conn->commit();

    if (ob_get_length())
        ob_clean();
    echo json_encode([
        'status' => 'success',
        'message' => 'Sales Trade-In entry saved successfully!',
        'invoice_no' => $invoice_no,
        'sales_entry_id' => $sales_entry_id
    ]);

} catch (Throwable $e) {
    if (isset($conn) && $conn->connect_errno === 0) {
        @$conn->rollback();
    }

    if (ob_get_length())
        ob_clean();

    echo json_encode([
        'status' => 'error',
        'message' => 'Error saving sales trade-in entry: ' . $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}

if (ob_get_length())
    ob_end_flush();
?>