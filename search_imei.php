<?php
require_once 'session_check.php';
// Suppress all errors to prevent breaking JSON response
error_reporting(0);
ini_set('display_errors', 0);

session_start();

include 'config.php';

header('Content-Type: application/json');

if (isset($_GET['imei'])) {
    $imei = $conn->real_escape_string(trim($_GET['imei']));

    // Determine the user's branch from session
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

    // Step 0: Check if IMEI is already in an approved or received transfer
    // IMPORTANT: Only block if the transfer is NOT received by the current user's branch
    // If status is 'Received' and branch_to matches user's branch, allow it (item is now in their inventory)

    // First check if imei2 column exists in stock_transfer_items
    $check_column = @$conn->query("SHOW COLUMNS FROM stock_transfer_items LIKE 'imei2'");
    $has_imei2_column = ($check_column && $check_column->num_rows > 0);

    if ($has_imei2_column) {
        $check_transfer = "SELECT st.st_number, st.status, st.branch_to, st.branch_from
                           FROM stock_transfer_items sti
                           JOIN stock_transfers st ON sti.st_number = st.st_number
                           WHERE (sti.imei = '$imei' OR sti.imei2 = '$imei')
                             AND st.status IN ('Approved', 'Received')
                           LIMIT 1";
    } else {
        $check_transfer = "SELECT st.st_number, st.status, st.branch_to, st.branch_from
                           FROM stock_transfer_items sti
                           JOIN stock_transfers st ON sti.st_number = st.st_number
                           WHERE sti.imei = '$imei'
                             AND st.status IN ('Approved', 'Received')
                           LIMIT 1";
    }
    $result_transfer = @$conn->query($check_transfer);

    if ($result_transfer && $result_transfer->num_rows > 0) {
        $transfer_row = $result_transfer->fetch_assoc();

        // If the transfer is 'Received' and the destination is the user's branch, allow it
        // The item is now in their inventory and can be sold
        // Use case-insensitive comparison and handle potential branch name variations
        $branch_to_normalized = strtoupper(trim($transfer_row['branch_to']));
        $user_branch_normalized = strtoupper(trim($user_branch));

        $is_received_by_user_branch = (
            $transfer_row['status'] === 'Received' &&
            ($branch_to_normalized === $user_branch_normalized ||
                strpos($branch_to_normalized, $user_branch_normalized) !== false ||
                strpos($user_branch_normalized, $branch_to_normalized) !== false)
        );

        // Only block if it's NOT received by the user's branch
        if (!$is_received_by_user_branch) {
            $error_msg = "Serial number already used in transfer " . $transfer_row['st_number'] .
                " (Status: " . $transfer_row['status'] .
                ", From: " . $transfer_row['branch_from'] .
                " To: " . $transfer_row['branch_to'] . ")";
            echo json_encode([
                'status' => 'error',
                'message' => $error_msg
            ]);
            exit;
        }
        // If received by user's branch, continue to allow the sale
    }

    // Step 1: Look up the IMEI in stock_on_hand — restricted to the user's own branch
    $branch_escaped_soh = $conn->real_escape_string($user_branch);
    $branch_filter = ($user_branch !== '') ? "AND branch = '$branch_escaped_soh'" : "";

    $sql_soh = "SELECT item_code, description, branch, status
                FROM stock_on_hand
                WHERE (imei = '$imei' OR imei2 = '$imei')
                  AND item_type = 'IMEI'
                  $branch_filter
                LIMIT 1";

    $result_soh = @$conn->query($sql_soh);

    if ($result_soh && $result_soh->num_rows > 0) {
        $row_soh = $result_soh->fetch_assoc();
        $item_code = $row_soh['item_code'];
        $description = $row_soh['description'];

        $item_code_escaped = $conn->real_escape_string($item_code);
        $branch_escaped = $conn->real_escape_string($user_branch);

        // Step 2: Check if item has the user's branch assigned in itemreg (items.branch)
        $srp = null;
        if ($user_branch !== '') {
            // Use FIND_IN_SET against the comma-separated branch column
            // items.branch stores values like "Branch A, Branch B, Branch C"
            // We replace ", " with "," so FIND_IN_SET works correctly
            $sql_price = "SELECT srp, id FROM items
                          WHERE item_code = '$item_code_escaped'
                            AND status = 'Active'
                            AND FIND_IN_SET('$branch_escaped', REPLACE(branch, ', ', ',')) > 0
                          LIMIT 1";
            $res_price = @$conn->query($sql_price);
            if ($res_price && $res_price->num_rows > 0) {
                $item_row = $res_price->fetch_assoc();
                $srp = $item_row['srp'];
                $item_id = $item_row['id'];

                $sql_override = "SELECT price_type, price FROM item_prices WHERE item_id = '$item_id' AND branch = '$branch_escaped' AND is_active = 1 AND price_type IN ('__SRP__', 'BDO Straight')";
                $res_override = @$conn->query($sql_override);
                if ($res_override && $res_override->num_rows > 0) {
                    $srp_price = 0;
                    $bdo_price = 0;
                    while ($override_row = $res_override->fetch_assoc()) {
                        if ($override_row['price_type'] === '__SRP__' && is_numeric($override_row['price']) && $override_row['price'] > 0) {
                            $srp_price = $override_row['price'];
                        }
                        if ($override_row['price_type'] === 'BDO Straight' && is_numeric($override_row['price']) && $override_row['price'] > 0) {
                            $bdo_price = $override_row['price'];
                        }
                    }
                    if ($srp_price > 0) {
                        $srp = $srp_price;
                    } elseif ($bdo_price > 0) {
                        $srp = $bdo_price;
                    }
                }
            }
            // If branch is NOT in the assigned list → price stays null (will become 0)
        } else {
            // No branch in session — also return 0 (can't determine correct price)
            $srp = null;
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'item_code' => $item_code,
                'description' => $description,
                'price' => $srp !== null ? $srp : 0, // 0 if branch not assigned
                'current_stock_type' => $row_soh['status'] ?? 'Good Stock' // Include current stock type
            ]
        ]);

    } else {
        // IMEI not in stock_on_hand — check receive_dd as fallback

        // Check if imei2 column exists in receive_dd
        $check_rd_column = @$conn->query("SHOW COLUMNS FROM receive_dd LIKE 'imei2'");
        $has_rd_imei2_column = ($check_rd_column && $check_rd_column->num_rows > 0);

        if ($has_rd_imei2_column) {
            $sql_rd = "SELECT rd.item_description, i.item_code
                       FROM receive_dd rd
                       LEFT JOIN items i ON rd.item_description = i.description
                       WHERE (rd.imei = '$imei' OR rd.imei2 = '$imei')
                       LIMIT 1";
        } else {
            $sql_rd = "SELECT rd.item_description, i.item_code
                       FROM receive_dd rd
                       LEFT JOIN items i ON rd.item_description = i.description
                       WHERE rd.imei = '$imei'
                       LIMIT 1";
        }
        $result_rd = @$conn->query($sql_rd);

        if ($result_rd && $result_rd->num_rows > 0) {
            $row_rd = $result_rd->fetch_assoc();
            $item_code = $row_rd['item_code'] ?? '';

            $srp = null;
            if ($item_code !== '' && $user_branch !== '') {
                $item_code_escaped = $conn->real_escape_string($item_code);
                $branch_escaped = $conn->real_escape_string($user_branch);

                $sql_price = "SELECT srp, id FROM items
                              WHERE item_code = '$item_code_escaped'
                                AND status = 'Active'
                                AND FIND_IN_SET('$branch_escaped', REPLACE(branch, ', ', ',')) > 0
                              LIMIT 1";
                $res_price = @$conn->query($sql_price);
                if ($res_price && $res_price->num_rows > 0) {
                    $item_row = $res_price->fetch_assoc();
                    $srp = $item_row['srp'];
                    $item_id = $item_row['id'];

                    $sql_override = "SELECT price_type, price FROM item_prices WHERE item_id = '$item_id' AND branch = '$branch_escaped' AND is_active = 1 AND price_type IN ('__SRP__', 'BDO Straight')";
                    $res_override = @$conn->query($sql_override);
                    if ($res_override && $res_override->num_rows > 0) {
                        $srp_price = 0;
                        $bdo_price = 0;
                        while ($override_row = $res_override->fetch_assoc()) {
                            if ($override_row['price_type'] === '__SRP__' && is_numeric($override_row['price']) && $override_row['price'] > 0) {
                                $srp_price = $override_row['price'];
                            }
                            if ($override_row['price_type'] === 'BDO Straight' && is_numeric($override_row['price']) && $override_row['price'] > 0) {
                                $bdo_price = $override_row['price'];
                            }
                        }
                        if ($srp_price > 0) {
                            $srp = $srp_price;
                        } elseif ($bdo_price > 0) {
                            $srp = $bdo_price;
                        }
                    }
                }
                // Branch not assigned → price = 0
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'item_code' => $item_code,
                    'description' => $row_rd['item_description'],
                    'price' => $srp !== null ? $srp : 0,
                    'current_stock_type' => 'Good Stock' // Default for receive_dd items
                ]
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'IMEI not found'
            ]);
        }
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No IMEI provided'
    ]);
}
?>
