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

    // Determine the user's branch and system level from session
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
    $is_super_admin = (strcasecmp($system_level, 'Super-Admin') === 0);

    // Step 1: Look up the IMEI in stock_on_hand
    // For Super Admin, don't filter by branch
    $branch_filter = "";
    if (!$is_super_admin && $user_branch !== '' && $user_branch !== 'Select All Branches') {
        $branch_escaped_soh = $conn->real_escape_string($user_branch);
        $branch_filter = "AND branch = '$branch_escaped_soh'";
    }

    $sql_soh = "SELECT item_code, description, branch
                FROM stock_on_hand
                WHERE imei = '$imei'
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

        // Step 2: Get item details including family_code
        $srp = null;
        $family_code = null;
        
        // Check if item exists and get family_code (branch filter only if item has branch restrictions)
        $sql_item = "SELECT srp, id, family_code, branch FROM items
                      WHERE item_code = '$item_code_escaped'
                        AND status = 'Active'
                      LIMIT 1";
        $res_item = @$conn->query($sql_item);
        
        if ($res_item && $res_item->num_rows > 0) {
            $item_row = $res_item->fetch_assoc();
            $item_id = $item_row['id'];
            $family_code = $item_row['family_code'];
            $item_branch = $item_row['branch'];
            
            // Check if branch restriction applies
            $branch_restricted = !empty($item_branch);
            $branch_allowed = false;
            
            if ($branch_restricted) {
                // Item has branch restrictions - check if user's branch is in the list
                $item_branches = array_map('trim', explode(',', $item_branch));
                if (!$is_super_admin && !empty($user_branch) && in_array($user_branch, $item_branches)) {
                    $branch_allowed = true;
                    $srp = $item_row['srp'];
                }
            } else {
                // No branch restrictions - but Super Admin should see 0 price
                if ($is_super_admin) {
                    // Super Admin sees price = 0 for items without branch restrictions
                    $srp = 0;
                } else if (!empty($user_branch)) {
                    $branch_allowed = true;
                    $srp = $item_row['srp'];
                } else {
                    $srp = 0;
                }
            }
            
            if ($branch_allowed && $srp > 0 && !$is_super_admin && !empty($user_branch)) {
                // Check for price overrides
                $branch_escaped = $conn->real_escape_string($user_branch);
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
                    }
                    elseif ($bdo_price > 0) {
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
                'family_code' => $family_code,
                'price' => $srp !== null ? $srp : 0
            ]
        ]);

    }
    else {
        // IMEI not in stock_on_hand — check receive_dd as fallback
        $sql_rd = "SELECT rd.item_description, i.item_code, i.family_code, i.srp
                   FROM receive_dd rd
                   LEFT JOIN items i ON rd.item_description = i.description
                   WHERE rd.imei = '$imei'
                   LIMIT 1";
        $result_rd = @$conn->query($sql_rd);

        if ($result_rd && $result_rd->num_rows > 0) {
            $row_rd = $result_rd->fetch_assoc();
            $item_code = $row_rd['item_code'] ?? '';
            $family_code = $row_rd['family_code'] ?? '';

            $srp = null;
            if ($item_code !== '') {
                $item_code_escaped = $conn->real_escape_string($item_code);

                $sql_price = "SELECT srp, id, family_code, branch FROM items
                              WHERE item_code = '$item_code_escaped'
                                AND status = 'Active'
                              LIMIT 1";
                $res_price = @$conn->query($sql_price);
                if ($res_price && $res_price->num_rows > 0) {
                    $item_row = $res_price->fetch_assoc();
                    $item_id = $item_row['id'];
                    $family_code = $item_row['family_code'];
                    $item_branch = $item_row['branch'];
                    
                    // Check if branch restriction applies
                    $branch_restricted = !empty($item_branch);
                    $branch_allowed = false;
                    
                    if ($branch_restricted) {
                        // Item has branch restrictions - check if user's branch is in the list
                        $item_branches = array_map('trim', explode(',', $item_branch));
                        if (!$is_super_admin && !empty($user_branch) && in_array($user_branch, $item_branches)) {
                            $branch_allowed = true;
                            $srp = $item_row['srp'];
                        }
                    } else {
                        // No branch restrictions - but Super Admin should see 0 price
                        if ($is_super_admin) {
                            // Super Admin sees price = 0 for items without branch restrictions
                            $srp = 0;
                        } else if (!empty($user_branch)) {
                            $branch_allowed = true;
                            $srp = $item_row['srp'];
                        } else {
                            $srp = 0;
                        }
                    }
                    
                    if ($branch_allowed && $srp > 0 && !$is_super_admin && !empty($user_branch)) {
                        // Check for price overrides
                        $branch_escaped = $conn->real_escape_string($user_branch);
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
                            }
                            elseif ($bdo_price > 0) {
                                $srp = $bdo_price;
                            }
                        }
                    }
                }
            }
            else {
                $srp = $row_rd['srp'];
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'item_code' => $item_code,
                    'description' => $row_rd['item_description'],
                    'family_code' => $family_code,
                    'price' => $srp !== null ? $srp : 0
                ]
            ]);
        }
        else {
            echo json_encode([
                'status' => 'error',
                'message' => 'IMEI not found'
            ]);
        }
    }
}
else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No IMEI provided'
    ]);
}
?>
