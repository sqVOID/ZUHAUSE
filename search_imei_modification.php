<?php
require_once 'session_check.php';

// Suppress all error output to ensure valid JSON response
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';

header('Content-Type: application/json');

if (isset($_GET['imei'])) {
    $imei = $conn->real_escape_string(trim($_GET['imei']));

    // Query stock_on_hand table by imei (serial number)
    $sql = "SELECT soh.imei, soh.item_code, soh.description, soh.branch
            FROM stock_on_hand soh
            WHERE soh.imei = '$imei'
            LIMIT 1";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $item_code = $row['item_code'];
        $description = $row['description'];
        $item_branch = $row['branch'] ?? '';

        $srp = 0;
        $item_id = 0;
        $prices = [];
        $others_bank_enabled = false;

        $item_code_escaped = $conn->real_escape_string($item_code);

        if ($item_branch !== '') {
            $branch_escaped = $conn->real_escape_string($item_branch);

            // Get base SRP from items table
            $sql_price = "SELECT srp, id FROM items
                          WHERE item_code = '$item_code_escaped'
                            AND status = 'Active'
                          LIMIT 1";
            $res_price = @$conn->query($sql_price);
            if ($res_price && $res_price->num_rows > 0) {
                $item_row = $res_price->fetch_assoc();
                $srp = $item_row['srp'];
                $item_id = $item_row['id'];

                // Fetch all active prices for branch
                $sql_all_prices = "SELECT price_type, price FROM item_prices 
                                   WHERE item_id = '$item_id' 
                                     AND branch = '$branch_escaped' 
                                     AND is_active = 1";
                $res_all_prices = @$conn->query($sql_all_prices);
                if ($res_all_prices && $res_all_prices->num_rows > 0) {
                    $srp_price = 0;
                    $bdo_price = 0;
                    while ($p_row = $res_all_prices->fetch_assoc()) {
                        if ($p_row['price_type'] === 'Others Bank') {
                            $others_bank_enabled = true;
                        }
                        $prices[$p_row['price_type']] = $p_row['price'];
                        if ($p_row['price_type'] === '__SRP__' && is_numeric($p_row['price']) && $p_row['price'] > 0) {
                            $srp_price = $p_row['price'];
                        }
                        if ($p_row['price_type'] === 'BDO Straight' && is_numeric($p_row['price']) && $p_row['price'] > 0) {
                            $bdo_price = $p_row['price'];
                        }
                    }
                    if ($srp_price > 0) {
                        $srp = $srp_price;
                    } elseif ($bdo_price > 0) {
                        $srp = $bdo_price;
                    }
                }
            }
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'item_code' => $item_code,
                'description' => $description,
                'price' => $srp !== null ? $srp : 0,
                'branch' => $item_branch,
                'prices' => $prices,
                'others_bank_enabled' => $others_bank_enabled
            ]
        ]);

    } else {
        // IMEI not in stock_on_hand — check receive_dd as fallback
        $sql_rd = "SELECT rd.item_description, i.item_code, i.srp
                   FROM receive_dd rd
                   LEFT JOIN items i ON rd.item_description = i.description
                   WHERE rd.imei = '$imei'
                   LIMIT 1";
        $result_rd = @$conn->query($sql_rd);

        if ($result_rd && $result_rd->num_rows > 0) {
            $row_rd = $result_rd->fetch_assoc();
            $item_code = $row_rd['item_code'] ?? '';
            $srp = $row_rd['srp'] ?? 0;

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'item_code' => $item_code,
                    'description' => $row_rd['item_description'],
                    'price' => $srp !== null ? $srp : 0,
                    'prices' => [],
                    'others_bank_enabled' => false
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