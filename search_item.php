<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if (isset($_GET['term'])) {
    $term = $conn->real_escape_string($_GET['term']);
    $searchImei = isset($_GET['search_imei']) && $_GET['search_imei'] === 'true';

    // Get user's branch from session
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

    // Build WHERE clause - items table doesn't have imei column
    $whereClause = "(i.description LIKE '%$term%' OR i.item_code LIKE '%$term%')";

    $sql = "SELECT i.id, i.item_code, i.description, i.srp, i.commission, i.has_commission, i.points, i.has_points, i.branch, i.has_voucher, i.voucher_amount, i.has_token, i.token_amount 
            FROM items i 
            WHERE $whereClause 
            AND i.status = 'Active' 
            LIMIT 20";

    $result = $conn->query($sql);

    $items_data = [];
    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Check if user's branch is in the item's allowed branches
                $item_branches = !empty($row['branch']) ? array_map('trim', explode(',', $row['branch'])) : [];
                $price_to_use = 0;
                $is_branch_allowed = false;

                // If item has no branch restrictions, allow all branches
                if (empty($item_branches)) {
                    $is_branch_allowed = true;
                    $price_to_use = $row['srp'] ?? 0;
                }
                else {
                    // Check if user's branch is in the allowed list
                    if (!empty($user_branch) && in_array($user_branch, $item_branches)) {
                        $is_branch_allowed = true;
                        $price_to_use = $row['srp'] ?? 0;
                    }
                    else {
                        // Branch not allowed - set price to 0
                        $is_branch_allowed = false;
                        $price_to_use = 0;
                    }
                }


                // Fetch active prices for the user's branch only
                $itemId = $row['id'];
                $prices = [];
                
                $others_bank_enabled = false; // Flag to check if Others Bank is enabled for this branch
                
                $branch_price_filter = !empty($user_branch) ? "AND branch = '" . $conn->real_escape_string($user_branch) . "'" : "";
                $priceRes = $conn->query("SELECT price_type, price FROM item_prices WHERE item_id = '$itemId' AND is_active = 1 $branch_price_filter");
                if ($priceRes) {
                    $srp_price = 0;
                    $bdo_price = 0;
                    while ($p = $priceRes->fetch_assoc()) {
                        // Check if "Others Bank" is enabled for this branch
                        if ($p['price_type'] === 'Others Bank' && $is_branch_allowed) {
                            // If Others Bank record exists with is_active=1 for this branch, enable it
                            $others_bank_enabled = true;
                        }
                        
                        // Override global SRP if an active __SRP__ row exists and is numeric
                        if ($p['price_type'] === '__SRP__' && is_numeric($p['price']) && $p['price'] > 0 && $is_branch_allowed) {
                            $srp_price = $p['price'];
                        }
                        if ($p['price_type'] === 'BDO Straight' && is_numeric($p['price']) && $p['price'] > 0 && $is_branch_allowed) {
                            $bdo_price = $p['price'];
                        }
                        // Also set installment prices to 0 if branch not allowed
                        $prices[$p['price_type']] = $is_branch_allowed ? $p['price'] : 0;
                    }
                    if ($srp_price > 0) {
                        $price_to_use = $srp_price;
                    }
                    elseif ($bdo_price > 0) {
                        $price_to_use = $bdo_price;
                    }
                }

                $items_data[] = [
                    'item_code' => $row['item_code'],
                    'description' => $row['description'],
                    'imei' => '',
                    'price' => $price_to_use,
                    'commission' => $is_branch_allowed ? ($row['commission'] ?? 0) : 0,
                    'has_commission' => $row['has_commission'] ?? 0,
                    'points' => $is_branch_allowed ? ($row['points'] ?? 0) : 0,
                    'has_points' => $row['has_points'] ?? 0,
                    'has_voucher' => $row['has_voucher'] ?? 0,
                    'voucher_amount' => $is_branch_allowed ? ($row['voucher_amount'] ?? 0) : 0,
                    'has_token' => $row['has_token'] ?? 0,
                    'token_amount' => $is_branch_allowed ? ($row['token_amount'] ?? 0) : 0,
                    'prices' => $prices,
                    'branch_allowed' => $is_branch_allowed,
                    'others_bank_enabled' => $others_bank_enabled // Flag for Others Bank availability
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $items_data]);
        }
        else {
            echo json_encode(['status' => 'success', 'data' => []]);
        }
    }
    else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}
else {
    echo json_encode(['status' => 'error', 'message' => 'No search term provided']);
}
?>

