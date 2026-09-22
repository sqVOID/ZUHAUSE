<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if (isset($_GET['term'])) {
    $term = $conn->real_escape_string($_GET['term']);
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    
    // Get family codes from pre-order if provided
    $family_codes = isset($_GET['family_codes']) ? $_GET['family_codes'] : '';
    
    // Build WHERE clause
    $whereClause = "(i.description LIKE '%$term%' OR i.item_code LIKE '%$term%')";
    
    // If family codes are provided, filter by them
    if (!empty($family_codes)) {
        $family_codes_array = array_map('trim', explode(',', $family_codes));
        $family_codes_escaped = array_map(function($code) use ($conn) {
            return "'" . $conn->real_escape_string($code) . "'";
        }, $family_codes_array);
        $family_codes_str = implode(',', $family_codes_escaped);
        $whereClause .= " AND i.family_code IN ($family_codes_str)";
    }

    $sql = "SELECT i.id, i.item_code, i.description, i.family_code, i.srp, i.commission, i.has_commission, i.points, i.has_points, i.branch 
            FROM items i 
            WHERE $whereClause 
            AND i.status = 'Active' 
            LIMIT 20";

    $result = $conn->query($sql);

    $items_data = [];
    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $item_branches = !empty($row['branch']) ? array_map('trim', explode(',', $row['branch'])) : [];
                $price_to_use = 0;
                $is_branch_allowed = false;

                if (empty($item_branches)) {
                    $is_branch_allowed = true;
                    $price_to_use = $row['srp'] ?? 0;
                }
                else {
                    if (!empty($user_branch) && in_array($user_branch, $item_branches)) {
                        $is_branch_allowed = true;
                        $price_to_use = $row['srp'] ?? 0;
                    }
                    else {
                        $is_branch_allowed = false;
                        $price_to_use = 0;
                    }
                }

                $itemId = $row['id'];
                $prices = [];
                $branch_price_filter = !empty($user_branch) ? "AND branch = '" . $conn->real_escape_string($user_branch) . "'" : "";
                $priceRes = $conn->query("SELECT price_type, price FROM item_prices WHERE item_id = '$itemId' AND is_active = 1 $branch_price_filter");
                if ($priceRes) {
                    $srp_price = 0;
                    $bdo_price = 0;
                    while ($p = $priceRes->fetch_assoc()) {
                        if ($p['price_type'] === '__SRP__' && is_numeric($p['price']) && $p['price'] > 0 && $is_branch_allowed) {
                            $srp_price = $p['price'];
                        }
                        if ($p['price_type'] === 'BDO Straight' && is_numeric($p['price']) && $p['price'] > 0 && $is_branch_allowed) {
                            $bdo_price = $p['price'];
                        }
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
                    'family_code' => $row['family_code'],
                    'price' => $price_to_use,
                    'srp' => $price_to_use,
                    'commission' => $is_branch_allowed ? ($row['commission'] ?? 0) : 0,
                    'has_commission' => $row['has_commission'] ?? 0,
                    'points' => $is_branch_allowed ? ($row['points'] ?? 0) : 0,
                    'has_points' => $row['has_points'] ?? 0,
                    'prices' => $prices,
                    'branch_allowed' => $is_branch_allowed
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
