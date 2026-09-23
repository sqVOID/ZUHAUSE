<?php
require_once 'session_check.php';
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data received');
    }
    
    // Validate required fields
    if (empty($data['invoice_no'])) {
        throw new Exception('Invoice number is required');
    }
    
    if (empty($data['claimed_items']) || !is_array($data['claimed_items'])) {
        throw new Exception('At least one item must be claimed');
    }
    
    if (empty($data['unclaimed_item_ids']) || !is_array($data['unclaimed_item_ids'])) {
        throw new Exception('Unclaimed item IDs are required');
    }
    
    $invoice_no = trim($data['invoice_no']);
    $customer_name = isset($data['customer_name']) ? trim($data['customer_name']) : '';
    $customer_address = isset($data['customer_address']) ? trim($data['customer_address']) : '';
    $customer_contact = isset($data['customer_contact']) ? trim($data['customer_contact']) : '';
    $customer_email = isset($data['customer_email']) ? trim($data['customer_email']) : '';
    $remarks = isset($data['remarks']) ? trim($data['remarks']) : '';
    $claimed_items = $data['claimed_items'];
    $unclaimed_item_ids = $data['unclaimed_item_ids'];
    
    $claimed_by = $_SESSION['username'] ?? 'system';
    $branch_code = $_SESSION['user_branch'] ?? '';
    
    // Get branch name from the first unclaimed freebie (they should all have the same branch)
    $branch_name = '';
    if (!empty($unclaimed_item_ids) && count($unclaimed_item_ids) > 0) {
        $first_unclaimed_id = $unclaimed_item_ids[0]['id'];
        $branch_query = $conn->prepare("SELECT branch FROM unclaimed_freebies WHERE id = ?");
        $branch_query->bind_param('i', $first_unclaimed_id);
        $branch_query->execute();
        $branch_result = $branch_query->get_result();
        if ($branch_result && $branch_result->num_rows > 0) {
            $branch_row = $branch_result->fetch_assoc();
            $branch_name = $branch_row['branch'];
        }
        $branch_query->close();
    }
    
    // Fallback: Get branch name from branches table if not found in unclaimed_freebies
    if (empty($branch_name) && !empty($branch_code)) {
        $branch_query2 = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = ?");
        $branch_query2->bind_param('s', $branch_code);
        $branch_query2->execute();
        $branch_result2 = $branch_query2->get_result();
        if ($branch_result2 && $branch_result2->num_rows > 0) {
            $branch_row2 = $branch_result2->fetch_assoc();
            $branch_name = $branch_row2['branch_name'];
        }
        $branch_query2->close();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Create claimed_items table if it doesn't exist
    $createTableSQL = "CREATE TABLE IF NOT EXISTS claimed_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(50) NOT NULL,
        unclaimed_freebie_id INT NOT NULL,
        
        customer_name VARCHAR(255) NULL,
        customer_address TEXT NULL,
        customer_contact VARCHAR(100) NULL,
        customer_email VARCHAR(255) NULL,
        
        item_code VARCHAR(100) NOT NULL,
        item_description VARCHAR(255) NOT NULL,
        imei VARCHAR(100) NULL,
        quantity INT NOT NULL DEFAULT 1,
        
        remarks TEXT NULL,
        
        claimed_by VARCHAR(100) NULL,
        claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        branch_code VARCHAR(10) NULL,
        branch VARCHAR(255) NULL,
        
        INDEX idx_invoice_no (invoice_no),
        INDEX idx_unclaimed_freebie (unclaimed_freebie_id),
        INDEX idx_item_code (item_code),
        INDEX idx_imei (imei),
        INDEX idx_claimed_at (claimed_at),
        INDEX idx_claimed_by (claimed_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($createTableSQL);
    
    // Insert claimed items
    $insertSQL = "INSERT INTO claimed_items 
                  (invoice_no, unclaimed_freebie_id, customer_name, customer_address, customer_contact, 
                   customer_email, item_code, item_description, imei, quantity, remarks, 
                   claimed_by, claimed_at, branch_code, branch) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
    
    $stmt = $conn->prepare($insertSQL);
    if (!$stmt) {
        throw new Exception('Failed to prepare insert statement: ' . $conn->error);
    }
    
    $insertedCount = 0;
    
    foreach ($claimed_items as $item) {
        // Validate item data
        if (empty($item['itemCode']) || empty($item['description']) || empty($item['quantity'])) {
            throw new Exception('Invalid item data: missing required fields');
        }
        
        // Find the matching unclaimed freebie ID
        $item_code = $item['itemCode'];
        $unclaimed_freebie_id = null;
        
        foreach ($unclaimed_item_ids as $id_mapping) {
            if ($id_mapping['item_code'] === $item_code) {
                $unclaimed_freebie_id = $id_mapping['id'];
                break;
            }
        }
        
        if (!$unclaimed_freebie_id) {
            throw new Exception("Unclaimed freebie ID not found for item: $item_code");
        }
        
        $imei = isset($item['imei']) && !empty($item['imei']) ? $item['imei'] : null;
        $quantity = intval($item['quantity']);
        
        $stmt->bind_param(
            'sisisssssissss',
            $invoice_no,
            $unclaimed_freebie_id,
            $customer_name,
            $customer_address,
            $customer_contact,
            $customer_email,
            $item_code,
            $item['description'],
            $imei,
            $quantity,
            $remarks,
            $claimed_by,
            $branch_code,
            $branch_name
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert claimed item: ' . $stmt->error);
        }
        
        $insertedCount++;
    }
    
    $stmt->close();
    
    // Get the user's branch name and code for stock deduction
    $user_branch = trim($_SESSION['user_branch'] ?? '');
    
    $stockDeductedCount = 0;
    $stockErrors = [];
    $stockDebug = [];
    
    foreach ($claimed_items as $item) {
        $item_code = trim($item['itemCode']);
        $quantity = intval($item['quantity']);
        $imei = isset($item['imei']) ? trim($item['imei']) : '';
        
        $stockDebug[] = "Processing item: $item_code, qty: $quantity, imei: '$imei', branch: '$user_branch', branch_name: '$branch_name'";
        
        // 1. SERIALIZED ITEM DEDUCTION (If IMEI is present)
        if (!empty($imei)) {
            $checkImeiSQL = "SELECT id, quantity, branch, status 
                             FROM stock_on_hand 
                             WHERE (UPPER(TRIM(imei)) = UPPER(TRIM(?)) OR (imei2 IS NOT NULL AND UPPER(TRIM(imei2)) = UPPER(TRIM(?))))
                               AND (LOWER(TRIM(status)) IN ('good', 'good stock', 'available', 'active') OR status IS NULL OR status = '')
                             LIMIT 1";
            $imeiStmt = $conn->prepare($checkImeiSQL);
            if (!$imeiStmt) {
                $stockErrors[] = "Failed to prepare IMEI check for item $item_code ($imei): " . $conn->error;
                continue;
            }
            $imeiStmt->bind_param('ss', $imei, $imei);
            $imeiStmt->execute();
            $imeiResult = $imeiStmt->get_result();
            
            if ($imeiResult && $imeiResult->num_rows > 0) {
                $stockRow = $imeiResult->fetch_assoc();
                $stockId = $stockRow['id'];
                $stockQty = intval($stockRow['quantity']);
                $imeiStmt->close();
                
                if ($stockQty <= 1) {
                    $delStmt = $conn->prepare("DELETE FROM stock_on_hand WHERE id = ?");
                    $delStmt->bind_param('i', $stockId);
                    $delStmt->execute();
                    if ($delStmt->affected_rows > 0) {
                        $stockDeductedCount++;
                        $stockDebug[] = "Deleted serialized stock row id=$stockId for IMEI $imei";
                    } else {
                        $stockErrors[] = "Failed to delete serialized stock record for IMEI $imei";
                    }
                    $delStmt->close();
                } else {
                    $decStmt = $conn->prepare("UPDATE stock_on_hand SET quantity = quantity - 1 WHERE id = ? AND quantity >= 1");
                    $decStmt->bind_param('i', $stockId);
                    $decStmt->execute();
                    if ($decStmt->affected_rows > 0) {
                        $stockDeductedCount++;
                        $stockDebug[] = "Decremented serialized stock quantity for row id=$stockId for IMEI $imei";
                    } else {
                        $stockErrors[] = "Failed to decrement serialized stock record for IMEI $imei";
                    }
                    $decStmt->close();
                }
                continue;
            } else {
                $imeiStmt->close();
                $stockErrors[] = "Serialized item $item_code with IMEI '$imei' not found in available stock";
                $stockDebug[] = "No active/good stock found for IMEI '$imei'";
                continue;
            }
        }
        
        // 2. NON-SERIALIZED ITEM DEDUCTION
        // Query available matching stock records (ordered by ID for FIFO deduction)
        $findStockSQL = "SELECT id, quantity, branch, status 
                         FROM stock_on_hand 
                         WHERE UPPER(TRIM(item_code)) = UPPER(TRIM(?))
                           AND (
                               TRIM(branch) = TRIM(?) 
                               OR TRIM(branch) = TRIM(?) 
                               OR ? = '' 
                               OR LOWER(?) = 'all branches'
                           )
                           AND (LOWER(TRIM(status)) IN ('good', 'good stock', 'available', 'active') OR status IS NULL OR status = '')
                           AND quantity > 0
                         ORDER BY id ASC";
        
        $findStmt = $conn->prepare($findStockSQL);
        if (!$findStmt) {
            $stockErrors[] = "Failed to prepare stock query for item $item_code: " . $conn->error;
            continue;
        }
        
        $findStmt->bind_param('sssss', $item_code, $user_branch, $branch_name, $user_branch, $user_branch);
        $findStmt->execute();
        $findResult = $findStmt->get_result();
        
        $matchingRows = [];
        $totalAvailable = 0;
        if ($findResult) {
            while ($row = $findResult->fetch_assoc()) {
                $matchingRows[] = $row;
                $totalAvailable += intval($row['quantity']);
            }
        }
        $findStmt->close();
        
        // Fallback: If no stock matched with branch filter, check without branch constraint if branch not strictly required
        if (empty($matchingRows)) {
            $findFallbackSQL = "SELECT id, quantity, branch, status 
                                FROM stock_on_hand 
                                WHERE UPPER(TRIM(item_code)) = UPPER(TRIM(?))
                                  AND (LOWER(TRIM(status)) IN ('good', 'good stock', 'available', 'active') OR status IS NULL OR status = '')
                                  AND quantity > 0
                                ORDER BY id ASC";
            $fbStmt = $conn->prepare($findFallbackSQL);
            if ($fbStmt) {
                $fbStmt->bind_param('s', $item_code);
                $fbStmt->execute();
                $fbResult = $fbStmt->get_result();
                if ($fbResult) {
                    while ($row = $fbResult->fetch_assoc()) {
                        $matchingRows[] = $row;
                        $totalAvailable += intval($row['quantity']);
                    }
                }
                $fbStmt->close();
            }
        }
        
        if (empty($matchingRows)) {
            $stockErrors[] = "Item $item_code not found in good/available stock";
            $stockDebug[] = "No stock record found for item $item_code in branch '$user_branch' or '$branch_name'";
            continue;
        }
        
        if ($totalAvailable < $quantity) {
            $stockErrors[] = "Insufficient stock for item $item_code (available: $totalAvailable, requested: $quantity)";
            $stockDebug[] = "Insufficient stock: available=$totalAvailable, requested=$quantity";
            continue;
        }
        
        // Deduct quantity across matching rows
        $remainingToDeduct = $quantity;
        $itemDeducted = 0;
        
        foreach ($matchingRows as $stockRow) {
            if ($remainingToDeduct <= 0) {
                break;
            }
            
            $rowId = $stockRow['id'];
            $rowQty = intval($stockRow['quantity']);
            $deductThisRow = min($remainingToDeduct, $rowQty);
            
            $deductStmt = $conn->prepare("UPDATE stock_on_hand SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
            if ($deductStmt) {
                $deductStmt->bind_param('iii', $deductThisRow, $rowId, $deductThisRow);
                if ($deductStmt->execute() && $deductStmt->affected_rows > 0) {
                    $remainingToDeduct -= $deductThisRow;
                    $itemDeducted += $deductThisRow;
                    $stockDebug[] = "Deducted $deductThisRow from stock_on_hand id=$rowId (item=$item_code)";
                }
                $deductStmt->close();
            }
        }
        
        if ($itemDeducted >= $quantity) {
            $stockDeductedCount++;
            $stockDebug[] = "Successfully deducted total $quantity for item $item_code";
        } else {
            $stockErrors[] = "Partial stock deduction for item $item_code ($itemDeducted of $quantity deducted)";
        }
    }
    
    // Update unclaimed_freebies record status to 'claimed' and set claimed_at timestamp
    if (!empty($unclaimed_item_ids)) {
        $updateClaimedSQL = "UPDATE unclaimed_freebies 
                             SET status = 'claimed', claimed_at = NOW()
                             WHERE id = ?";
        
        $updateClaimedStmt = $conn->prepare($updateClaimedSQL);
        
        if (!$updateClaimedStmt) {
            throw new Exception('Failed to prepare update claimed statement: ' . $conn->error);
        }
        
        $updatedClaimedCount = 0;
        
        foreach ($unclaimed_item_ids as $id_mapping) {
            $unclaimed_id = $id_mapping['id'];
            
            $updateClaimedStmt->bind_param('i', $unclaimed_id);
            
            if ($updateClaimedStmt->execute()) {
                $updatedClaimedCount++;
            } else {
                throw new Exception('Failed to update claimed record: ' . $updateClaimedStmt->error);
            }
        }
        
        $updateClaimedStmt->close();
        $updatedCount = $updatedClaimedCount;
    }
    
    // Commit transaction
    $conn->commit();
    
    // Build response message
    $message = "Successfully claimed $insertedCount item(s) for invoice $invoice_no";
    if ($stockDeductedCount > 0) {
        $message .= ". Stock deducted for $stockDeductedCount item(s).";
    }
    if (!empty($stockErrors)) {
        $message .= " Stock warnings: " . implode('; ', $stockErrors);
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $message,
        'inserted_count' => $insertedCount,
        'updated_count' => $updatedCount ?? 0,
        'stock_deducted_count' => $stockDeductedCount,
        'stock_errors' => $stockErrors,
        'stock_debug' => $stockDebug,
        'invoice_no' => $invoice_no,
        'user_branch' => $user_branch
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
