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

// Ensure dr_number column exists in sales_entry_items (idempotent)
$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS dr_number VARCHAR(100) DEFAULT '' AFTER item_code");

// Ensure promo_id column exists in sales_entry (idempotent)
$conn->query("ALTER TABLE sales_entry ADD COLUMN IF NOT EXISTS promo_id INT(11) DEFAULT NULL AFTER remarks");
$conn->query("ALTER TABLE sales_entry ADD INDEX IF NOT EXISTS idx_promo_id (promo_id)");

// Ensure is_promo_item column exists in sales_entry_items (idempotent)
$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS is_promo_item TINYINT(1) NOT NULL DEFAULT 0 AFTER dr_number");


// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Check if JSON decode was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data received']);
    exit;
}

// Validate required field
if (empty($data['assisted_by'])) {
    echo json_encode(['status' => 'error', 'message' => 'Assisted By is required!']);
    exit;
}

// Validate that there are items
if (empty($data['items']) || count($data['items']) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please add at least one item to the sales entry!']);
    exit;
}

// Get branch code from session
$branch_code = '000'; // Default
$user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
$stock_branch_name = $user_branch; // Canonical branch name used for stock_on_hand matching
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

// Generate Invoice Number using booklet configuration
// BUT: If invoice_no is provided (from salesentrylate.php), use that instead
include_once 'get_next_invoice_number.php';

$booklet_id_for_validation = null; // Store booklet ID for cancelled invoice validation

if (isset($data['invoice_no']) && !empty(trim($data['invoice_no']))) {
    // Use the provided invoice number (for late entries with custom invoice numbers)
    $invoice_no = trim($data['invoice_no']);

    // ── VALIDATE CANCELLED & SKIPPED INVOICE NUMBER FOR LATE ENTRIES ──────────
    // Check both skip_receipt_requests and cancelled_invoices
    $booklet = getBookletConfig($conn, $branch_code, 'salesentry');
    $booklet_id_for_validation = $booklet ? $booklet['id'] : 0;

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
    // Auto-generate invoice number (for regular sales entries)
    $booklet = getBookletConfig($conn, $branch_code, 'salesentry');

    if ($booklet) {
        // ── VALIDATE BOOKLET RANGE ──────────────────────────────────────────────
        // Extract the numeric part from current_number for comparison
        $current_number_numeric = null;

        if ($booklet['booklet_format'] === 'numeric') {
            // For numeric format like "0003128-092-149", extract last segment
            $parts = explode('-', $booklet['current_number']);
            $current_number_numeric = intval(end($parts));
        } elseif (is_numeric(ltrim($booklet['current_number'], '0') ?: '0')) {
            // For pure numeric strings like "0000001"
            $current_number_numeric = intval($booklet['current_number']);
        }

        // If we can extract numeric value, validate against booklet range
        if ($current_number_numeric !== null) {
            // Extract ending number for comparison
            $ending_number_numeric = null;
            if ($booklet['booklet_format'] === 'numeric') {
                $ending_parts = explode('-', $booklet['ending_number']);
                $ending_number_numeric = intval(end($ending_parts));
            } elseif (is_numeric(ltrim($booklet['ending_number'], '0') ?: '0')) {
                $ending_number_numeric = intval($booklet['ending_number']);
            }

            // Check if current number exceeds ending number
            if ($ending_number_numeric !== null && $current_number_numeric > $ending_number_numeric) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Booklet number range exhausted! Current number (' . $booklet['current_number'] . ') has exceeded the ending number (' . $booklet['ending_number'] . '). Please register a new booklet range or activate other booklet or contact administrator.'
                ]);
                exit;
            }
        }

        // Use booklet number configuration
        $invoice_no = generateInvoiceNumber($booklet);
        $booklet_id_for_validation = $booklet['id'];

        // Auto-increment for numeric formats
        // Also increment 'custom' format if current_number is purely numeric (e.g. 0000001)
        if ($booklet['booklet_format'] === 'numeric') {
            $next_number = incrementInvoiceNumber($booklet['current_number'], 'numeric');
            // Update booklet for next invoice
            updateInvoiceNumber($conn, $booklet['id'], $next_number);
        } elseif ($booklet['booklet_format'] === 'custom' && is_numeric(ltrim($booklet['current_number'], '0') ?: '0')) {
            // current_number is a zero-padded numeric string (e.g. "0000001") — safe to auto-increment
            $current_num = intval($booklet['current_number']);
            $padding = strlen($booklet['current_number']);
            $next_number = str_pad($current_num + 1, $padding, '0', STR_PAD_LEFT);
            updateInvoiceNumber($conn, $booklet['id'], $next_number);
        }
    } else {
        // Fallback to old system if no booklet configured
        $year = date('y');
        $month = date('m');
        $day = date('d');

        // Get the all-time last invoice for this branch (never resets across days)
        // Only look at invoices matching the standard format to exclude custom late entry invoices
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
            // Extract last 5 digits (supports both old 4-digit and new 5-digit formats)
            $sequence = intval(preg_replace('/.*-(\d+)$/', '$1', $last_invoice)) + 1;
        } else {
            $sequence = 1;
        }

        // Format: YYMMDD-BRANCHCODE-NNNNN (5-digit, never resets)
        $invoice_no = sprintf("%s%s%s-%s-%05d", $year, $month, $day, $branch_code, $sequence);
    }
}

// ── Resolve payment extras ────────────────────────────────────────────────────
$payment_data_raw = isset($data['payment_data']) ? $data['payment_data'] : null;
$payment_data_json = $payment_data_raw ? json_encode($payment_data_raw) : null;

// cash_payments: extract from payment_data if payment_type is Cash
$cash_payments = 0;
if ($payment_data_raw && isset($payment_data_raw['payment_type'])) {
    if (strtolower($payment_data_raw['payment_type']) === 'cash') {
        // Try common keys: Amount, amount
        foreach (['Amount', 'amount', 'Total', 'total'] as $k) {
            if (!empty($payment_data_raw[$k])) {
                $cash_payments = floatval(str_replace(',', '', $payment_data_raw[$k]));
                break;
            }
        }
    }
}
// Allow explicit override from frontend
if (isset($data['cash_payments']) && $data['cash_payments'] > 0) {
    $cash_payments = floatval($data['cash_payments']);
}

// card_bank_type: derive from payment_data (Bank field / payment type)
$card_bank_type = null;
if ($payment_data_raw && isset($payment_data_raw['payment_type'])) {
    $ptype = $payment_data_raw['payment_type'];
    // For card/bank-based payment types, include the bank name
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

// encoder: logged-in user name from session
$encoder = '';
if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
    $encoder = trim($_SESSION['user_name']);
} elseif (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
    $encoder = trim($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
} elseif (isset($_SESSION['username'])) {
    $encoder = trim($_SESSION['username']);
} else {
    // Fallback: use assisted_by as encoder if no session data
    $encoder = isset($data['assisted_by']) ? $data['assisted_by'] : 'System';
}

// Allow override from data if provided
if (isset($data['encoder']) && !empty($data['encoder'])) {
    $encoder = $data['encoder'];
}

// commission_encoder: encoder's commission (separate from sales-person commission)
$commission_encoder = isset($data['commission_encoder']) ? floatval($data['commission_encoder']) : 0;

// status
$status = isset($data['status']) ? $data['status'] : 'completed';
$allowed_statuses = ['pending', 'completed', 'voided', 'cancelled'];
if (!in_array($status, $allowed_statuses)) {
    $status = 'completed';
}

// created_at: use the provided date for late entries, or NOW() for current entries
$created_at = 'NOW()';
$late_created_at = null; // Track when late entry was actually created
if (isset($data['date']) && !empty($data['date'])) {
    // Date is provided from salesentrylate.php in YYYY-MM-DD format
    // Convert it to a datetime string with current time
    $date_value = $data['date'];
    // Validate date format
    $date_parts = explode('-', $date_value);
    if (count($date_parts) == 3 && checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
        // Valid date - use it with current time for created_at
        $created_at = "'" . $conn->real_escape_string($date_value) . " " . date('H:i:s') . "'";
        // Store the actual timestamp when this late entry was created
        $late_created_at = date('Y-m-d H:i:s');
    }
}

// ── Start transaction ─────────────────────────────────────────────────────────
$conn->begin_transaction();

try {
    // Insert main sales entry with all necessary columns
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
            promo_id,
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
            commission_encoder,
            status,
            branch_code,
            encoder,
            page_type,
            created_at,
            late_created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, {$created_at}, ?)
    ");

    $first_name = isset($data['first_name']) && trim($data['first_name']) !== '' ? strtoupper($data['first_name']) : '0';
    $last_name = isset($data['last_name']) && trim($data['last_name']) !== '' ? strtoupper($data['last_name']) : '';
    $address = isset($data['address']) && trim($data['address']) !== '' ? $data['address'] : '0';
    $contact_no = isset($data['contact_no']) && trim($data['contact_no']) !== '' ? $data['contact_no'] : '0';
    $email = isset($data['email']) && trim($data['email']) !== '' ? $data['email'] : '0';
    $assisted_by = $data['assisted_by'];
    $remarks = isset($data['remarks']) ? strtoupper($data['remarks']) : '';
    $promo_id = !empty($data['promo_id']) ? intval($data['promo_id']) : null;
    $total_qty = $data['total_qty'] ?? 0;
    $discount = $data['discount'] ?? 0;
    $total_amount = $data['total_amount'] ?? 0;
    $points = $data['points'] ?? 0;
    $commission = $data['commission'] ?? 0;

    // Voucher data
    $voucher_amount = isset($data['voucher_amount']) ? floatval($data['voucher_amount']) : 0.00;

    // Token data
    $token = isset($data['token']) ? floatval($data['token']) : 0.00;

    // Use the encoder we determined above
    // $encoder is already set above

    $page_type = isset($data['page_type']) ? trim($data['page_type']) : 'salesentry';

    $stmt->bind_param(
        "ssssssssiiddddsdsddssssss",
        $invoice_no,
        $first_name,
        $last_name,
        $address,
        $contact_no,
        $email,
        $assisted_by,
        $remarks,
        $promo_id,
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
        $commission_encoder,
        $status,
        $branch_code,
        $encoder,
        $page_type,
        $late_created_at
    );

    $stmt->execute();
    $sales_entry_id = $conn->insert_id;
    $stmt->close();

    // ── Promo Usage Tracking ────────────────────────────────────────────────
    if ($promo_id !== null && $promo_id > 0) {
        // Calculate promo usage count increment from items
        $free_count = 0;
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if (isset($item['price']) && (float) $item['price'] == 0) {
                    $free_count += isset($item['quantity']) ? (int) $item['quantity'] : 1;
                }
            }
        }
        $inc_usage = ($free_count > 0) ? $free_count : 1;

        // Capture CURRENT usage count BEFORE incrementing so we can record
        // which slot(s) this sale consumed (e.g. sale used slot 7 of 10)
        $promo_before = $conn->query("SELECT usage_count, usage_limit FROM promos WHERE id = $promo_id");
        $promo_usage_number = null;
        if ($promo_before && $promo_before->num_rows > 0) {
            $promo_before_data = $promo_before->fetch_assoc();
            $before_count = (int) $promo_before_data['usage_count'];
            // This sale occupies slots (before_count+1) through (before_count+inc_usage)
            // Store the ending slot number so the display shows e.g. "7/10"
            $promo_usage_number = $before_count + $inc_usage;
        }

        // Increment promo usage count
        $conn->query("UPDATE promos SET usage_count = usage_count + $inc_usage WHERE id = $promo_id");

        // Save the usage slot number onto the sales_entry row
        if ($promo_usage_number !== null && $sales_entry_id) {
            $conn->query("UPDATE sales_entry SET promo_usage_number = $promo_usage_number WHERE id = $sales_entry_id");
        }

        // Check if limit reached and auto-deactivate
        $promo_check = $conn->query("SELECT usage_count, usage_limit FROM promos WHERE id = $promo_id");
        if ($promo_check && $promo_check->num_rows > 0) {
            $promo_data = $promo_check->fetch_assoc();
            $usage_count = (int) $promo_data['usage_count'];
            $usage_limit = !empty($promo_data['usage_limit']) ? (int) $promo_data['usage_limit'] : null;

            // If limit is set and reached, deactivate the promo
            if ($usage_limit !== null && $usage_count >= $usage_limit) {
                $conn->query("UPDATE promos SET status = 'Deactivated' WHERE id = $promo_id");
            }
        }
    }


    // ── Insert sales items ──────────────────────────────────────────────────
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
        $item_description = strtoupper($item['description']);
        $imei = isset($item['imei']) ? trim(strtoupper($item['imei'])) : '';
        $quantity = $item['quantity'];
        $price = $item['price'];
        $item_code = isset($item['item_code']) ? trim(strtoupper($item['item_code'])) : '';

        // ── Capture original dr_number BEFORE touching stock ────────────────
        $original_dr_number = '';
        if (!empty($imei)) {
            $dr_lookup = $conn->prepare("SELECT dr_number FROM stock_on_hand WHERE TRIM(imei) = TRIM(?) AND TRIM(item_code) = TRIM(?) LIMIT 1");
            $dr_lookup->bind_param("ss", $imei, $item_code);
            $dr_lookup->execute();
            $dr_row = $dr_lookup->get_result()->fetch_assoc();
            $dr_lookup->close();
            if ($dr_row && !empty($dr_row['dr_number'])) {
                $original_dr_number = $dr_row['dr_number'];
            }
        } else {
            // For non-IMEI items, capture DR from the stock row being consumed.
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

        $is_promo_item = isset($item['is_promo_item']) ? intval($item['is_promo_item']) : 0;

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

        // ── Reduce stock on hand (strict: must affect stock or fail save) ──
        if (!empty($imei)) {
            // Item has serial number - remove exact serial from stock
            if (!empty($stock_branch_name) && strtolower($stock_branch_name) !== 'all branches') {
                $delete_stock = $conn->prepare("
                    DELETE FROM stock_on_hand
                    WHERE (TRIM(imei) = TRIM(?) OR TRIM(imei2) = TRIM(?))
                      AND TRIM(item_code) = TRIM(?)
                      AND branch = ?
                      AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active' OR LOWER(TRIM(status)) = 'good stock')
                    LIMIT 1
                ");
                $delete_stock->bind_param("ssss", $imei, $imei, $item_code, $stock_branch_name);
            } else {
                $delete_stock = $conn->prepare("
                    DELETE FROM stock_on_hand
                    WHERE (TRIM(imei) = TRIM(?) OR TRIM(imei2) = TRIM(?))
                      AND TRIM(item_code) = TRIM(?)
                      AND (LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'active' OR LOWER(TRIM(status)) = 'good stock')
                    LIMIT 1
                ");
                $delete_stock->bind_param("sss", $imei, $imei, $item_code);
            }
            $delete_stock->execute();
            if ($delete_stock->affected_rows <= 0) {
                $delete_stock->close();
                throw new Exception("Insufficient stock for serialized item: {$item_code} ({$imei})");
            }
            $delete_stock->close();
        } else {
            // Item without serial number - reduce quantity from available stock
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

    // ── Insert freebies ─────────────────────────────────────────────────────
    // ── Insert Freebies (if any) ───────────────────────────────────────────
    if (!empty($data['freebies'])) {
        $stmt_freebies = $conn->prepare("
            INSERT INTO sales_entry_freebies (
                sales_entry_id,
                freebie_description,
                quantity
            ) VALUES (?, ?, ?)
        ");

        foreach ($data['freebies'] as $freebie) {
            $freebie_description = strtoupper($freebie['description']);
            $freebie_quantity = $freebie['quantity'];

            $stmt_freebies->bind_param(
                "isi",
                $sales_entry_id,
                $freebie_description,
                $freebie_quantity
            );
            $stmt_freebies->execute();
        }
        $stmt_freebies->close();
    }

    // ── Insert Unclaimed Freebies (if any) ─────────────────────────────────
    if (!empty($data['unclaimed_freebies'])) {
        // Determine the created_at timestamp for unclaimed freebies
        // If date is provided (from salesentrylate.php), use it; otherwise use current timestamp
        $uf_created_at = null;
        if (isset($data['date']) && !empty($data['date'])) {
            // Use the provided date from late entry with current time
            $date_value = $data['date'];
            $date_parts = explode('-', $date_value);
            if (count($date_parts) == 3 && checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
                $uf_created_at = $date_value . " " . date('H:i:s');
            }
        }

        if ($uf_created_at) {
            // Insert with specific created_at (for late entries)
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
                    created_at,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unclaimed')
            ");

            foreach ($data['unclaimed_freebies'] as $unclaimed_freebie) {
                $uf_item_code = $unclaimed_freebie['item_code'] ?? '';
                $uf_description = strtoupper($unclaimed_freebie['description'] ?? '');
                $uf_quantity = intval($unclaimed_freebie['quantity'] ?? 0);
                $uf_note = $unclaimed_freebie['note'] ?? '';
                $uf_created_by = $data['assisted_by'] ?? '';

                $stmt_unclaimed->bind_param(
                    "issssisss",
                    $sales_entry_id,
                    $invoice_no,
                    $uf_item_code,
                    $uf_description,
                    $uf_quantity,
                    $uf_note,
                    $user_branch,
                    $uf_created_by,
                    $uf_created_at
                );
                $stmt_unclaimed->execute();
            }
        } else {
            // Insert without created_at (uses DEFAULT CURRENT_TIMESTAMP for regular entries)
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
        }
        $stmt_unclaimed->close();
    }

    // ── Commit ──────────────────────────────────────────────────────────────
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Sales entry saved successfully!',
        'invoice_no' => $invoice_no,
        'sales_entry_id' => $sales_entry_id
    ]);

} catch (Throwable $e) {
    $conn->rollback();

    // Clean any output buffer before sending JSON
    if (ob_get_length())
        ob_clean();

    echo json_encode([
        'status' => 'error',
        'message' => 'Error saving sales entry: ' . $e->getMessage()
    ]);
}

$conn->close();

// Ensure clean output
if (ob_get_length())
    ob_end_flush();
?>