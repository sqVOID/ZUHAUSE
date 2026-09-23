<?php
require_once 'session_check.php';
require_once 'config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $date_from = isset($input['date_from']) ? $input['date_from'] : '';
    $date_to = isset($input['date_to']) ? $input['date_to'] : '';
    $branch = isset($input['branch']) ? $input['branch'] : '';
    $status = isset($input['status']) ? $input['status'] : '';

    // Validate required fields
    if (empty($date_from) || empty($date_to) || empty($branch)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required fields'
        ]);
        exit;
    }

    // ------------------------------------------------------------------
    // Step 1: Resolve branch_code from branch name
    // ------------------------------------------------------------------
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

    $branch_code = null;
    $branch_query = $conn->prepare("SELECT branch_code FROM branches WHERE branch_name = ?");
    $branch_query->bind_param('s', $branch);
    $branch_query->execute();
    $branch_result = $branch_query->get_result();
    if ($branch_result && $branch_result->num_rows > 0) {
        $branch_data = $branch_result->fetch_assoc();
        $branch_code = $branch_data['branch_code'];
    }
    $branch_query->close();

    // ------------------------------------------------------------------
    // Step 2: Fetch one row per individual payment from preorder_payment_history
    //         so that partial payment and claim payment each appear separately
    //         with their own invoice number.
    // ------------------------------------------------------------------
    $payment_query = "
        SELECT
            ph.id              AS ph_id,
            ph.invoice_no      AS payment_invoice_no,
            ph.payment_date,
            ph.amount          AS payment_amount,
            ph.payment_sequence,
            ph.status_after_payment,
            p.id               AS preorder_id,
            p.invoice_no       AS preorder_no,
            p.claimed_invoice_no,
            p.claimed_at,
            p.created_at       AS preorder_created_at,
            p.status,
            p.branch_code,
            CONCAT(p.first_name, ' ', p.last_name) AS customer_name,
            p.contact_no       AS customer_phone
        FROM preorder_payment_history ph
        INNER JOIN preorders p ON ph.preorder_id = p.id
        WHERE DATE(ph.payment_date) BETWEEN ? AND ?
    ";

    $payment_params = [$date_from, $date_to];
    $payment_types = 'ss';

    if ($branch_code !== null) {
        $payment_query .= " AND p.branch_code = ?";
        $payment_params[] = $branch_code;
        $payment_types .= 's';
    }

    if (!empty($status)) {
        $payment_query .= " AND p.status = ?";
        $payment_params[] = $status;
        $payment_types .= 's';
    }

    $payment_query .= " ORDER BY ph.payment_date ASC, ph.payment_sequence ASC";

    $pay_stmt = $conn->prepare($payment_query);
    if (!$pay_stmt) {
        throw new Exception('Failed to prepare payment statement: ' . $conn->error);
    }
    $pay_stmt->bind_param($payment_types, ...$payment_params);
    $pay_stmt->execute();
    $pay_result = $pay_stmt->get_result();

    $rows = [];

    // Cache items per preorder to avoid re-querying
    $items_cache = [];

    while ($pay_row = $pay_result->fetch_assoc()) {
        $preorder_id = intval($pay_row['preorder_id']);
        $payment_amount = floatval($pay_row['payment_amount']);
        $payment_date = $pay_row['payment_date'];
        $claimed_at = $pay_row['claimed_at'];

        // Use the payment's own invoice_no as the displayed "Pre Order No"
        // (e.g. 0001-PRE for partial, 0002-PRE for claim payment)
        $display_invoice = !empty($pay_row['payment_invoice_no'])
            ? $pay_row['payment_invoice_no']
            : $pay_row['preorder_no'];

        // ------------------------------------------------------------------
        // Step 3: Fetch items for this preorder (cached)
        // ------------------------------------------------------------------
        if (!isset($items_cache[$preorder_id])) {
            $item_stmt = $conn->prepare("
                SELECT
                    id             AS item_id,
                    COALESCE(NULLIF(item_description, ''), family_code) AS item_description,
                    imei,
                    quantity,
                    price          AS unit_price,
                    (quantity * price) AS total_amount,
                    amount_paid,
                    created_at     AS item_created_at,
                    status         AS item_status
                FROM preorder_items
                WHERE preorder_id = ?
                ORDER BY id ASC
            ");
            $item_stmt->bind_param('i', $preorder_id);
            $item_stmt->execute();
            $item_result = $item_stmt->get_result();

            $items = [];
            $grand_total = 0.00;
            while ($item_row = $item_result->fetch_assoc()) {
                $items[] = $item_row;
                $grand_total += floatval($item_row['total_amount']);
            }
            $item_stmt->close();

            $items_cache[$preorder_id] = [
                'items' => $items,
                'grand_total' => $grand_total,
            ];
        }

        $cached = $items_cache[$preorder_id];
        $items = $cached['items'];
        $grand_item_total = $cached['grand_total'];

        if (empty($items)) {
            // No items yet — emit one placeholder row for this payment
            $rows[] = [
                'preorder_id' => $preorder_id,
                'original_preorder_no' => $pay_row['preorder_no'],
                'preorder_no' => $display_invoice,
                'claimed_invoice_no' => $pay_row['claimed_invoice_no'],
                'customer_name' => $pay_row['customer_name'],
                'customer_phone' => $pay_row['customer_phone'],
                'item_description' => '',
                'imei' => '',
                'quantity' => 0,
                'unit_price' => 0.00,
                'total_amount' => 0.00,
                'payment_amount' => $payment_amount,
                'status' => $pay_row['status'],
                'branch_name' => $branch,
                'date_created' => $payment_date,
                'claimed_at' => $claimed_at,
            ];
            continue;
        }

        // ------------------------------------------------------------------
        // Step 4: Emit one row per item, using this payment's amount/invoice
        // ------------------------------------------------------------------
        foreach ($items as $item_row) {
            $item_total = floatval($item_row['total_amount']);
            $unit_price = floatval($item_row['unit_price']);
            $quantity = intval($item_row['quantity']);

            // Proportional share of this payment for this item
            if ($grand_item_total > 0) {
                $item_payment = round(($item_total / $grand_item_total) * $payment_amount, 2);
            } else {
                $item_payment = 0.00;
            }

            $rows[] = [
                'preorder_id' => $preorder_id,
                'original_preorder_no' => $pay_row['preorder_no'],
                'preorder_no' => $display_invoice,
                'claimed_invoice_no' => $pay_row['claimed_invoice_no'],
                'customer_name' => $pay_row['customer_name'],
                'customer_phone' => $pay_row['customer_phone'],
                'item_description' => $item_row['item_description'],
                'imei' => $item_row['imei'],
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total_amount' => $item_total,
                'payment_amount' => $item_payment,
                'status' => $pay_row['status'],
                'branch_name' => $branch,
                'date_created' => $payment_date,
                'claimed_at' => $claimed_at,
            ];
        }
    }

    $pay_stmt->close();

    echo json_encode([
        'status' => 'success',
        'rows' => $rows,
        'count' => count($rows)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>