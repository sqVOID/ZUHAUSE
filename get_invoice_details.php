<?php
// Suppress all output before JSON response
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'session_check.php';
include 'config.php';

// Clean any output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

// Get invoice number from query parameter
$invoice_no = isset($_GET['invoice_no']) ? trim($_GET['invoice_no']) : '';

if (empty($invoice_no)) {
    echo json_encode(['status' => 'error', 'message' => 'Invoice number is required']);
    exit;
}

try {
    // Fetch sales entry with all customer and payment information
    $sales_query = $conn->prepare("
        SELECT 
            se.id,
            se.invoice_no,
            se.first_name,
            se.last_name,
            se.address,
            se.contact_no,
            se.email,
            se.assisted_by,
            se.remarks,
            se.total_qty,
            se.total_amount,
            se.discount,
            se.voucher_amount,
            se.token,
            se.titu_control,
            se.titu_token,
            se.cross_sell,
            se.trade_in_voucher,
            se.titu_voucher_total,
            se.tradein_value,
            se.tradein_imei,
            se.tradein_item_code,
            se.tradein_brand,
            se.points,
            se.commission,
            se.payment_data,
            se.encoder,
            se.branch_code,
            se.created_at,
            se.status,
            se.upgrade,
            se.page_type,
            se.promo_id,
            se.promo_usage_number,
            COALESCE((
                SELECT SUM(uoi.price)
                FROM upgrades u
                JOIN upgrade_old_items uoi ON uoi.upgrade_id = u.id
                WHERE u.original_invoice_no = se.invoice_no
            ), 0) AS old_unit_amount,
            (
                SELECT u.payment_data
                FROM upgrades u
                WHERE u.original_invoice_no = se.invoice_no
                ORDER BY u.id DESC
                LIMIT 1
            ) AS upgrade_payment_data
        FROM sales_entry se 
        WHERE se.invoice_no = ?
        LIMIT 1
    ");

    $sales_query->bind_param("s", $invoice_no);
    $sales_query->execute();
    $sales_result = $sales_query->get_result();

    if (!$sales_result || $sales_result->num_rows === 0) {
        $sales_query->close();

        // Helper: compute total paid from payment_data JSON
        $compute_po_paid = function ($pd_json) {
            $amount = 0.0;
            $pd = json_decode($pd_json, true);
            if (!is_array($pd))
                return $amount;

            if (isset($pd['payment_type']) && $pd['payment_type'] === 'multiple') {
                foreach ((array) ($pd['payments'] ?? []) as $p) {
                    if (isset($p['amount'])) {
                        $amount += floatval(str_replace(',', '', $p['amount']));
                    } elseif (isset($p['payment_type']) && $p['payment_type'] === 'payment_partners') {
                        $loan_balance = isset($p['loan_balance']) ? floatval(str_replace(',', '', $p['loan_balance'])) : 0;
                        $down_payment = 0;
                        if (isset($p['cash_dp_amount']))
                            $down_payment += floatval(str_replace(',', '', $p['cash_dp_amount']));
                        if (isset($p['gcash_dp_amount']))
                            $down_payment += floatval(str_replace(',', '', $p['gcash_dp_amount']));
                        if (isset($p['maya_dp_amount']))
                            $down_payment += floatval(str_replace(',', '', $p['maya_dp_amount']));
                        $amount += $loan_balance + $down_payment;
                    }
                }
            } else {
                if (isset($pd['amount'])) {
                    $amount = floatval(str_replace(',', '', $pd['amount']));
                } elseif (isset($pd['payment_type']) && $pd['payment_type'] === 'payment_partners') {
                    $loan_balance = isset($pd['loan_balance']) ? floatval(str_replace(',', '', $pd['loan_balance'])) : 0;
                    $down_payment = 0;
                    if (isset($pd['cash_dp_amount']))
                        $down_payment += floatval(str_replace(',', '', $pd['cash_dp_amount']));
                    if (isset($pd['gcash_dp_amount']))
                        $down_payment += floatval(str_replace(',', '', $pd['gcash_dp_amount']));
                    if (isset($pd['maya_dp_amount']))
                        $down_payment += floatval(str_replace(',', '', $pd['maya_dp_amount']));
                    $amount += $loan_balance + $down_payment;
                }
            }
            return $amount;
        };

        // Query preorders table
        $po_query = $conn->prepare("
            SELECT 
                p.id,
                p.invoice_no,
                p.first_name,
                p.last_name,
                p.address,
                p.contact_no,
                p.email,
                p.assisted_by,
                p.remarks,
                p.total_qty,
                p.total_amount,
                p.discount,
                0 AS voucher_amount,
                0 AS token,
                NULL AS titu_control,
                NULL AS titu_token,
                0 AS cross_sell,
                0 AS trade_in_voucher,
                0 AS titu_voucher_total,
                0 AS tradein_value,
                NULL AS tradein_imei,
                NULL AS tradein_item_code,
                NULL AS tradein_brand,
                0 AS points,
                0 AS commission,
                p.payment_data,
                p.encoder,
                p.branch_code,
                p.created_at,
                p.status,
                NULL AS upgrade,
                'preorder' AS page_type,
                NULL AS promo_id,
                0 AS old_unit_amount,
                NULL AS upgrade_payment_data
            FROM preorders p 
            WHERE p.invoice_no = ?
            LIMIT 1
        ");
        $po_query->bind_param("s", $invoice_no);
        $po_query->execute();
        $po_result = $po_query->get_result();

        if (!$po_result || $po_result->num_rows === 0) {
            $po_query->close();
            echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
            exit;
        }

        $sale = $po_result->fetch_assoc();
        $po_query->close();

        // Fetch preorder items
        $poi_query = $conn->prepare("
            SELECT 
                COALESCE(NULLIF(TRIM(pi.item_description), ''), pi.family_code, '') AS item_description,
                COALESCE(NULLIF(TRIM(pi.item_code), ''), pi.family_code, '') AS item_code,
                COALESCE(pi.imei, '') AS imei,
                pi.quantity,
                pi.price
            FROM preorder_items pi 
            WHERE pi.preorder_id = ?
            ORDER BY pi.id
        ");
        $poi_query->bind_param("i", $sale['id']);
        $poi_query->execute();
        $poi_result = $poi_query->get_result();

        $items = [];
        if ($poi_result && $poi_result->num_rows > 0) {
            while ($item = $poi_result->fetch_assoc()) {
                $items[] = $item;
            }
        }
        $poi_query->close();

        // Calculate actual paid amount for preorder
        $paid = $compute_po_paid($sale['payment_data']);
        $sale['actual_total_amount'] = $paid > 0 ? $paid : $sale['total_amount'];
        $sale['total_amount'] = $paid > 0 ? $paid : $sale['total_amount'];

        // Check if assisted_by is a promoter and fetch their brand
        $assisted_by_brand = '';
        if (!empty($sale['assisted_by'])) {
            $brand_query = $conn->prepare("SELECT brand FROM promoters WHERE name = ? LIMIT 1");
            $brand_query->bind_param("s", $sale['assisted_by']);
            $brand_query->execute();
            $brand_result = $brand_query->get_result();

            if ($brand_result && $brand_result->num_rows > 0) {
                $brand_row = $brand_result->fetch_assoc();
                $assisted_by_brand = $brand_row['brand'] ?? '';
            }
            $brand_query->close();
        }

        $sale['assisted_by_brand'] = $assisted_by_brand;

        // Clean any output buffer before sending JSON
        if (ob_get_length())
            ob_clean();

        echo json_encode([
            'status' => 'success',
            'sale' => $sale,
            'items' => $items,
            'promo_details' => null,
            'promo_items' => []
        ]);
        exit;
    }

    $sale = $sales_result->fetch_assoc();
    $sales_query->close();

    // Get items for this sale
    $items_query = $conn->prepare("
        SELECT 
            sei.item_description,
            sei.item_code,
            sei.imei,
            sei.quantity,
            sei.price,
            sei.is_promo_item
        FROM sales_entry_items sei 
        WHERE sei.sales_entry_id = ?
        ORDER BY sei.id
    ");

    $items_query->bind_param("i", $sale['id']);
    $items_query->execute();
    $items_result = $items_query->get_result();

    $items = [];
    if ($items_result && $items_result->num_rows > 0) {
        while ($item = $items_result->fetch_assoc()) {
            $items[] = $item;
        }
    }
    $items_query->close();

    // Fetch promo details if promo_id exists
    $promo_details = null;
    $promo_items = [];
    if (!empty($sale['promo_id'])) {
        $promo_query = $conn->prepare("
            SELECT 
                p.id,
                p.promo_name,
                p.start_date,
                p.end_date,
                p.discount_type,
                p.discount_value,
                p.motor_model,
                p.brand,
                p.free_item,
                p.usage_limit
            FROM promos p
            WHERE p.id = ?
            LIMIT 1
        ");

        $promo_query->bind_param("i", $sale['promo_id']);
        $promo_query->execute();
        $promo_result = $promo_query->get_result();

        if ($promo_result && $promo_result->num_rows > 0) {
            $promo_details = $promo_result->fetch_assoc();
        }
        $promo_query->close();

        // Fetch promo items
        $promo_items_query = $conn->prepare("
            SELECT 
                pi.id,
                pi.motor_model,
                pi.discount_type,
                pi.discount_value,
                pi.promo_item
            FROM promo_items pi
            WHERE pi.promo_id = ?
            ORDER BY pi.id
        ");

        $promo_items_query->bind_param("i", $sale['promo_id']);
        $promo_items_query->execute();
        $promo_items_result = $promo_items_query->get_result();

        if ($promo_items_result && $promo_items_result->num_rows > 0) {
            while ($promo_item = $promo_items_result->fetch_assoc()) {
                $promo_items[] = $promo_item;
            }
        }
        $promo_items_query->close();
    }

    // Calculate actual_total_amount (same logic as fetch_sales_report.php)
    $payment_data = [];
    if (!empty($sale['payment_data'])) {
        $payment_data = json_decode($sale['payment_data'], true);
        if (!is_array($payment_data)) {
            $payment_data = [];
        }
    }

    $actual_total_amount = floatval($sale['total_amount']);
    $is_partner = isset($payment_data['Loan Balance']) || isset($payment_data['loan_balance']) ||
        isset($payment_data['cash_down_payment_amount']) || isset($payment_data['cash_dp_amount']) ||
        isset($payment_data['gcash_down_payment_amount']) || isset($payment_data['gcash_dp_amount']) ||
        isset($payment_data['maya_down_payment_amount']) || isset($payment_data['maya_dp_amount']) ||
        (isset($payment_data['payment_type']) && ($payment_data['payment_type'] === 'payment_partners' || strpos(strtolower($payment_data['payment_type']), 'makati') !== false || strpos(strtolower($payment_data['payment_type']), 'home credit') !== false)) ||
        isset($payment_data['payment_partner']);

    if ($is_partner) {
        $totalFromPaymentData = 0;
        if (!empty($payment_data['Total'])) {
            $totalRaw = str_replace(',', '', (string) $payment_data['Total']);
            if (is_numeric($totalRaw) && (float) $totalRaw > 0) {
                $totalFromPaymentData = (float) $totalRaw;
            }
        }
        if ($totalFromPaymentData <= 0 && !empty($payment_data['totalLoanAmount'])) {
            $totalLoanAmt = str_replace(',', '', (string) $payment_data['totalLoanAmount']);
            if (is_numeric($totalLoanAmt) && (float) $totalLoanAmt > 0) {
                $totalFromPaymentData = (float) $totalLoanAmt;
            }
        }
        if ($totalFromPaymentData <= 0) {
            $lb_val = $payment_data['Loan Balance'] ?? $payment_data['loan_balance'] ?? 0;
            $totalFromPaymentData += (float) str_replace(',', '', (string) $lb_val);
            $dp_keys = [
                'cash_down_payment_amount',
                'cash_dp_amount',
                'gcash_down_payment_amount',
                'gcash_dp_amount',
                'maya_down_payment_amount',
                'maya_dp_amount'
            ];
            foreach ($dp_keys as $dp_key) {
                if (isset($payment_data[$dp_key])) {
                    $val = str_replace(',', '', (string) $payment_data[$dp_key]);
                    $parts = explode('|', $val);
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if (is_numeric($part)) {
                            $totalFromPaymentData += (float) $part;
                            break;
                        }
                    }
                }
            }
        }
        if ($totalFromPaymentData > 0) {
            $actual_total_amount = $totalFromPaymentData;
        }
    } elseif (isset($payment_data['payment_type']) && $payment_data['payment_type'] === 'multiple' && !empty($payment_data['payments'])) {
        $multipleActualTotal = 0;
        foreach ((array) $payment_data['payments'] as $p) {
            if (isset($p['payment_type']) && ($p['payment_type'] === 'payment_partners' || strpos(strtolower($p['payment_type'] ?? ''), 'makati') !== false || isset($p['payment_partner']))) {
                $lb = str_replace(',', '', (string) ($p['loan_balance'] ?? $p['Loan Balance'] ?? $p['total_loan_amount'] ?? 0));
                if (is_numeric($lb)) {
                    $multipleActualTotal += (float) $lb;
                }
                foreach (['cash_dp_amount', 'cash_down_payment_amount', 'gcash_dp_amount', 'gcash_down_payment_amount', 'maya_dp_amount', 'maya_down_payment_amount'] as $dp_k) {
                    if (isset($p[$dp_k])) {
                        $val = str_replace(',', '', (string) $p[$dp_k]);
                        if (is_numeric($val)) {
                            $multipleActualTotal += (float) $val;
                        }
                    }
                }
            } else {
                $amt = str_replace(',', '', (string) ($p['amount'] ?? $p['cash_amount'] ?? 0));
                if (is_numeric($amt)) {
                    $multipleActualTotal += (float) $amt;
                }
            }
        }
        if ($multipleActualTotal > 0) {
            $actual_total_amount = $multipleActualTotal;
        }
    }

    $sale['actual_total_amount'] = $actual_total_amount;

    // Check if assisted_by is a promoter and fetch their brand
    $assisted_by_brand = '';
    if (!empty($sale['assisted_by'])) {
        // Extract first name from "FirstName LastName" format
        $name_parts = explode(' ', trim($sale['assisted_by']), 2);
        $first_name = $name_parts[0];

        $brand_query = $conn->prepare("SELECT brand FROM promoters WHERE name = ? LIMIT 1");
        $brand_query->bind_param("s", $sale['assisted_by']);
        $brand_query->execute();
        $brand_result = $brand_query->get_result();

        if ($brand_result && $brand_result->num_rows > 0) {
            $brand_row = $brand_result->fetch_assoc();
            $assisted_by_brand = $brand_row['brand'] ?? '';
        }
        $brand_query->close();
    }

    $sale['assisted_by_brand'] = $assisted_by_brand;

    // Clean any output buffer before sending JSON
    if (ob_get_length())
        ob_clean();

    echo json_encode([
        'status' => 'success',
        'sale' => $sale,
        'items' => $items,
        'promo_details' => $promo_details,
        'promo_items' => $promo_items
    ]);

} catch (Exception $e) {
    error_log("Error in get_invoice_details.php: " . $e->getMessage());

    // Clean any output buffer before sending JSON
    if (ob_get_length())
        ob_clean();

    echo json_encode([
        'status' => 'error',
        'message' => 'Error fetching invoice details: ' . $e->getMessage()
    ]);
}

$conn->close();

// Ensure clean output
if (ob_get_length())
    ob_end_flush();
?>