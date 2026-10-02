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

$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS voucher_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
$conn->query("ALTER TABLE sales_entry_items ADD COLUMN IF NOT EXISTS token_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");

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
            se.original_invoice_no,
            se.page_type,
            se.promo_id,
            se.promo_usage_number,
            COALESCE((
                SELECT SUM(uoi.price)
                FROM upgrades u
                JOIN upgrade_old_items uoi ON uoi.upgrade_id = u.id
                WHERE (u.new_invoice_no = se.invoice_no OR u.original_invoice_no = se.invoice_no)
            ), 0) AS old_unit_amount,
            (
                SELECT u.payment_data
                FROM upgrades u
                WHERE (u.new_invoice_no = se.invoice_no OR u.original_invoice_no = se.invoice_no)
                ORDER BY u.id DESC
                LIMIT 1
            ) AS upgrade_payment_data,
            CASE
                WHEN se.page_type = 'upgradeunit' THEN (
                    SELECT ual.approver
                    FROM upgrade_approval_log ual
                    WHERE (ual.new_invoice_no = se.invoice_no OR (ual.original_invoice_no = se.invoice_no AND ual.original_invoice_no != ''))
                    ORDER BY ual.id DESC
                    LIMIT 1
                )
                WHEN se.page_type = 'replacementunit' THEN (
                    SELECT ral.approver
                    FROM replacement_approval_log ral
                    WHERE (ral.new_invoice_no = se.invoice_no OR (ral.original_invoice_no = se.invoice_no AND ral.original_invoice_no != ''))
                    ORDER BY ral.id DESC
                    LIMIT 1
                )
                ELSE NULL
            END AS approver,
            CASE
                WHEN se.page_type = 'upgradeunit' THEN (
                    SELECT ual.approval_date
                    FROM upgrade_approval_log ual
                    WHERE (ual.new_invoice_no = se.invoice_no OR (ual.original_invoice_no = se.invoice_no AND ual.original_invoice_no != ''))
                    ORDER BY ual.id DESC
                    LIMIT 1
                )
                WHEN se.page_type = 'replacementunit' THEN (
                    SELECT ral.approval_date
                    FROM replacement_approval_log ral
                    WHERE (ral.new_invoice_no = se.invoice_no OR (ral.original_invoice_no = se.invoice_no AND ral.original_invoice_no != ''))
                    ORDER BY ral.id DESC
                    LIMIT 1
                )
                ELSE NULL
            END AS approval_date,
            CASE
                WHEN se.page_type = 'upgradeunit' THEN (
                    SELECT ual.disapprover
                    FROM upgrade_approval_log ual
                    WHERE (ual.new_invoice_no = se.invoice_no OR (ual.original_invoice_no = se.invoice_no AND ual.original_invoice_no != ''))
                    ORDER BY ual.id DESC
                    LIMIT 1
                )
                WHEN se.page_type = 'replacementunit' THEN (
                    SELECT ral.disapprover
                    FROM replacement_approval_log ral
                    WHERE (ral.new_invoice_no = se.invoice_no OR (ral.original_invoice_no = se.invoice_no AND ral.original_invoice_no != ''))
                    ORDER BY ral.id DESC
                    LIMIT 1
                )
                ELSE NULL
            END AS disapprover,
            CASE
                WHEN se.page_type = 'upgradeunit' THEN (
                    SELECT ual.disapproval_date
                    FROM upgrade_approval_log ual
                    WHERE (ual.new_invoice_no = se.invoice_no OR (ual.original_invoice_no = se.invoice_no AND ual.original_invoice_no != ''))
                    ORDER BY ual.id DESC
                    LIMIT 1
                )
                WHEN se.page_type = 'replacementunit' THEN (
                    SELECT ral.disapproval_date
                    FROM replacement_approval_log ral
                    WHERE (ral.new_invoice_no = se.invoice_no OR (ral.original_invoice_no = se.invoice_no AND ral.original_invoice_no != ''))
                    ORDER BY ral.id DESC
                    LIMIT 1
                )
                ELSE NULL
            END AS disapproval_date,
            CASE
                WHEN se.page_type = 'upgradeunit' THEN (
                    SELECT COALESCE(ual.status, 'Pending')
                    FROM upgrade_approval_log ual
                    WHERE (ual.new_invoice_no = se.invoice_no OR (ual.original_invoice_no = se.invoice_no AND ual.original_invoice_no != ''))
                    ORDER BY ual.id DESC
                    LIMIT 1
                )
                WHEN se.page_type = 'replacementunit' THEN (
                    SELECT COALESCE(ral.status, 'Pending')
                    FROM replacement_approval_log ral
                    WHERE (ral.new_invoice_no = se.invoice_no OR (ral.original_invoice_no = se.invoice_no AND ral.original_invoice_no != ''))
                    ORDER BY ral.id DESC
                    LIMIT 1
                )
                ELSE NULL
            END AS approval_status,
            CASE
                WHEN se.page_type = 'upgradeunit' THEN (
                    SELECT ual.disapproval_reason
                    FROM upgrade_approval_log ual
                    WHERE (ual.new_invoice_no = se.invoice_no OR (ual.original_invoice_no = se.invoice_no AND ual.original_invoice_no != ''))
                      AND ual.status = 'Disapproved'
                    ORDER BY ual.id DESC
                    LIMIT 1
                )
                WHEN se.page_type = 'replacementunit' THEN (
                    SELECT ral.disapproval_reason
                    FROM replacement_approval_log ral
                    WHERE (ral.new_invoice_no = se.invoice_no OR (ral.original_invoice_no = se.invoice_no AND ral.original_invoice_no != ''))
                      AND ral.status = 'Disapproved'
                    ORDER BY ral.id DESC
                    LIMIT 1
                )
                ELSE NULL
            END AS disapproval_reason
        FROM sales_entry se 
        WHERE se.invoice_no = ?
          AND (se.page_type != 'claimpreorder' OR se.page_type IS NULL)
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
                COALESCE(ph.invoice_no, p.invoice_no) AS invoice_no,
                p.first_name,
                p.last_name,
                p.address,
                p.contact_no,
                p.email,
                p.assisted_by,
                p.remarks,
                p.total_qty,
                COALESCE(ph.amount, p.total_amount) AS total_amount,
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
                COALESCE(ph.payment_data, p.payment_data) AS payment_data,
                COALESCE(ph.encoder, p.encoder) AS encoder,
                p.branch_code,
                COALESCE(ph.payment_date, p.created_at) AS created_at,
                p.status,
                NULL AS upgrade,
                'preorder' AS page_type,
                NULL AS promo_id,
                0 AS old_unit_amount,
                NULL AS upgrade_payment_data
            FROM preorders p 
            LEFT JOIN preorder_payment_history ph ON ph.preorder_id = p.id AND ph.invoice_no = ?
            WHERE p.invoice_no = ? OR ph.invoice_no = ?
            ORDER BY (ph.invoice_no = ?) DESC
            LIMIT 1
        ");
        $po_query->bind_param("ssss", $invoice_no, $invoice_no, $invoice_no, $invoice_no);
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

        // Calculate actual paid amount for preorder (or use the transaction amount)
        $paid = floatval($sale['total_amount']);
        if ($paid <= 0) {
            $paid = $compute_po_paid($sale['payment_data']);
        }
        $sale['actual_total_amount'] = $paid;
        $sale['total_amount'] = $paid;

        // Fetch ALL preorder payment history records for this preorder
        $preorder_payments = [];
        $pph_query = $conn->prepare("
            SELECT 
                invoice_no,
                payment_date,
                payment_method,
                amount,
                status_after_payment,
                payment_sequence
            FROM preorder_payment_history
            WHERE preorder_id = ?
            ORDER BY payment_sequence ASC
        ");
        $pph_query->bind_param("i", $sale['id']);
        $pph_query->execute();
        $pph_result = $pph_query->get_result();
        if ($pph_result && $pph_result->num_rows > 0) {
            while ($pph_row = $pph_result->fetch_assoc()) {
                $preorder_payments[] = $pph_row;
            }
        }
        $pph_query->close();
        $sale['preorder_payments'] = $preorder_payments;

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

    // Check if old_imei column exists and add it if not
    $check_old_imei = $conn->query("SHOW COLUMNS FROM sales_entry_items LIKE 'old_imei'");
    $has_old_imei = ($check_old_imei && $check_old_imei->num_rows > 0);
    
    if (!$has_old_imei) {
        // Add the column if it doesn't exist
        $conn->query("ALTER TABLE sales_entry_items ADD COLUMN old_imei VARCHAR(50) NULL AFTER imei");
        $has_old_imei = true;
    }

    // Get items for this sale
    $old_imei_select = $has_old_imei ? "sei.old_imei," : "NULL AS old_imei,";
    
    $items_query = $conn->prepare("
        SELECT 
            sei.item_description,
            sei.item_code,
            sei.imei,
            $old_imei_select
            sei.quantity,
            COALESCE(NULLIF(sei.price, 0), i.srp, 0) AS price,
            COALESCE(i.srp, NULLIF(sei.price, 0), 0) AS srp,
            sei.is_promo_item,
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

    // Fetch unclaimed freebies linked to this invoice
    $freebies = [];
    $freebies_query = $conn->prepare("
        SELECT 
            uf.item_code,
            uf.item_description,
            uf.quantity,
            uf.status,
            uf.note,
            uf.created_at,
            uf.claimed_at
        FROM unclaimed_freebies uf
        WHERE uf.invoice_number = ?
           OR uf.sales_entry_id = ?
        ORDER BY uf.id
    ");
    $freebies_query->bind_param("si", $invoice_no, $sale['id']);
    $freebies_query->execute();
    $freebies_result = $freebies_query->get_result();
    if ($freebies_result && $freebies_result->num_rows > 0) {
        while ($fb = $freebies_result->fetch_assoc()) {
            $freebies[] = $fb;
        }
    }
    $freebies_query->close();

    // Fetch replacement records if any exist for this invoice (only Approved)
    $replacements = [];
    $rep_query = $conn->prepare("
        SELECT 
            r.id, 
            r.replacement_no, 
            r.invoice_no, 
            r.new_invoice_no, 
            r.reason, 
            r.remarks, 
            r.created_at, 
            COALESCE(ral.status, r.status, 'Approved') AS status, 
            r.created_by,
            COALESCE(NULLIF(TRIM(r.approved_by), ''), ral.approver) AS approver,
            COALESCE(NULLIF(TRIM(r.approved_at), ''), ral.approval_date) AS approval_date,
            COALESCE(NULLIF(TRIM(r.disapproved_by), ''), ral.disapprover) AS disapprover,
            COALESCE(NULLIF(TRIM(r.disapproved_at), ''), ral.disapproval_date) AS disapproval_date,
            COALESCE(NULLIF(TRIM(r.disapproval_reason), ''), ral.disapproval_reason) AS disapproval_reason
        FROM replacements r
        LEFT JOIN (
            SELECT ral1.*
            FROM replacement_approval_log ral1
            INNER JOIN (
                SELECT MAX(id) AS max_id
                FROM replacement_approval_log
                GROUP BY replacement_id
            ) ral2 ON ral1.id = ral2.max_id
        ) ral ON (ral.replacement_id = r.id OR (r.replacement_no IS NOT NULL AND r.replacement_no != '' AND ral.replacement_no = r.replacement_no))
        WHERE (r.invoice_no = ? OR (r.new_invoice_no IS NOT NULL AND r.new_invoice_no != '' AND r.new_invoice_no = ?))
          AND (r.status = 'Approved' OR (r.status IS NULL AND ral.status = 'Approved'))
        GROUP BY r.id
        ORDER BY r.id ASC
    ");
    if ($rep_query) {
        $rep_query->bind_param("ss", $invoice_no, $invoice_no);
        $rep_query->execute();
        $rep_res = $rep_query->get_result();
        if ($rep_res && $rep_res->num_rows > 0) {
            while ($r_row = $rep_res->fetch_assoc()) {
                $r_id = $r_row['id'];
                
                // Old items
                $old_q = $conn->prepare("SELECT item_description, imei, price FROM replacement_old_items WHERE replacement_id = ? ORDER BY id ASC");
                $old_q->bind_param("i", $r_id);
                $old_q->execute();
                $old_res = $old_q->get_result();
                $old_items = [];
                if ($old_res && $old_res->num_rows > 0) {
                    while ($oi = $old_res->fetch_assoc()) {
                        $old_items[] = $oi;
                    }
                }
                $old_q->close();
                $r_row['old_items'] = $old_items;

                // New items
                $new_q = $conn->prepare("SELECT item_description, imei, quantity, price, item_code FROM replacement_new_items WHERE replacement_id = ? ORDER BY id ASC");
                $new_q->bind_param("i", $r_id);
                $new_q->execute();
                $new_res = $new_q->get_result();
                $new_items = [];
                if ($new_res && $new_res->num_rows > 0) {
                    while ($ni = $new_res->fetch_assoc()) {
                        $new_items[] = $ni;
                    }
                }
                $new_q->close();
                $r_row['new_items'] = $new_items;

                $replacements[] = $r_row;
            }
        }
        $rep_query->close();
    }
    $sale['replacements'] = $replacements;

    // Fetch upgrade records if this is an upgrade invoice
    $upgrades_data = [];
    $is_upgrade_invoice = ($sale['upgrade'] === 'UPGD' && !empty($sale['original_invoice_no'])) || $sale['page_type'] === 'upgradeunit';
    if ($is_upgrade_invoice) {
        $upg_query = $conn->prepare("
            SELECT 
                u.id,
                u.upgrade_no,
                u.original_invoice_no,
                u.new_invoice_no,
                u.reason,
                u.remarks,
                u.less_amount,
                u.total_amount AS upgrade_total,
                u.created_by,
                u.branch,
                u.created_at,
                COALESCE(ual.status, 'Pending') AS approval_status,
                ual.approver,
                ual.approval_date,
                ual.disapprover,
                ual.disapproval_date,
                ual.disapproval_reason
            FROM upgrades u
            LEFT JOIN (
                SELECT ual1.*
                FROM upgrade_approval_log ual1
                INNER JOIN (
                    SELECT MAX(id) AS max_id
                    FROM upgrade_approval_log
                    GROUP BY upgrade_id
                ) ual2 ON ual1.id = ual2.max_id
            ) ual ON (ual.upgrade_id = u.id OR (u.upgrade_no IS NOT NULL AND u.upgrade_no != '' AND ual.upgrade_no = u.upgrade_no))
            WHERE ((u.original_invoice_no IS NOT NULL AND u.original_invoice_no != '' AND u.original_invoice_no = ?) OR (u.new_invoice_no IS NOT NULL AND u.new_invoice_no != '' AND u.new_invoice_no = ?))
            ORDER BY u.id ASC
        ");
        if ($upg_query) {
            $upg_query->bind_param("ss", $invoice_no, $invoice_no);
            $upg_query->execute();
            $upg_res = $upg_query->get_result();
            if ($upg_res && $upg_res->num_rows > 0) {
                while ($u_row = $upg_res->fetch_assoc()) {
                    $u_id = $u_row['id'];

                    // Fetch old items for this upgrade
                    $uoi_q = $conn->prepare("SELECT item_description, imei, price FROM upgrade_old_items WHERE upgrade_id = ? ORDER BY id ASC");
                    $uoi_q->bind_param("i", $u_id);
                    $uoi_q->execute();
                    $uoi_res = $uoi_q->get_result();
                    $u_old_items = [];
                    if ($uoi_res && $uoi_res->num_rows > 0) {
                        while ($uoi = $uoi_res->fetch_assoc()) {
                            $u_old_items[] = $uoi;
                        }
                    }
                    $uoi_q->close();
                    $u_row['old_items'] = $u_old_items;

                    $upgrades_data[] = $u_row;
                }
            }
            $upg_query->close();
        }
    }
    $sale['upgrades_data'] = $upgrades_data;

    // If this invoice is the TARGET of an upgrade (e.g. 0186 upgraded via 0187),
    // resolve the upgrade invoice number(s) so the modal can show
    // "Status: Upgrade (Invoice No: 0187)" under Sale Information.
    $upgraded_to_invoice_nos = [];
    $is_target_of_upgrade = ($sale['upgrade'] === 'UPGD' && empty($sale['original_invoice_no']) && $sale['page_type'] !== 'upgradeunit');
    if ($is_target_of_upgrade || $sale['upgrade'] === 'UPGD') {
        // Prefer upgrades.new_invoice_no linked to this original invoice
        $ug_to_q = $conn->prepare("
            SELECT DISTINCT new_invoice_no
            FROM upgrades
            WHERE original_invoice_no = ?
              AND original_invoice_no != ''
              AND new_invoice_no IS NOT NULL
              AND new_invoice_no != ''
              AND new_invoice_no != ?
            ORDER BY id ASC
        ");
        if ($ug_to_q) {
            $ug_to_q->bind_param("ss", $invoice_no, $invoice_no);
            $ug_to_q->execute();
            $ug_to_res = $ug_to_q->get_result();
            if ($ug_to_res) {
                while ($row = $ug_to_res->fetch_assoc()) {
                    $inv = trim($row['new_invoice_no'] ?? '');
                    if ($inv !== '' && !in_array($inv, $upgraded_to_invoice_nos, true)) {
                        $upgraded_to_invoice_nos[] = $inv;
                    }
                }
            }
            $ug_to_q->close();
        }

        // Fallback: sales_entry rows that reference this invoice as original
        if (empty($upgraded_to_invoice_nos)) {
            $se_to_q = $conn->prepare("
                SELECT invoice_no
                FROM sales_entry
                WHERE original_invoice_no = ?
                  AND original_invoice_no != ''
                  AND invoice_no != ?
                  AND (upgrade = 'UPGD' OR page_type = 'upgradeunit')
                ORDER BY id ASC
            ");
            if ($se_to_q) {
                $se_to_q->bind_param("ss", $invoice_no, $invoice_no);
                $se_to_q->execute();
                $se_to_res = $se_to_q->get_result();
                if ($se_to_res) {
                    while ($row = $se_to_res->fetch_assoc()) {
                        $inv = trim($row['invoice_no'] ?? '');
                        if ($inv !== '' && !in_array($inv, $upgraded_to_invoice_nos, true)) {
                            $upgraded_to_invoice_nos[] = $inv;
                        }
                    }
                }
                $se_to_q->close();
            }
        }
    }
    $sale['upgraded_to_invoice_nos'] = $upgraded_to_invoice_nos;
    $sale['upgraded_to_invoice_no'] = !empty($upgraded_to_invoice_nos) ? $upgraded_to_invoice_nos[0] : null;

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
        'promo_items' => $promo_items,
        'freebies' => $freebies
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