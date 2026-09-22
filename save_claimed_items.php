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
    
    // Get the user's branch name for stock deduction
    $user_branch = $_SESSION['user_branch'] ?? '';
    
    // Trim and normalize the branch name
    $user_branch = trim($user_branch);
    
    $stockDeductedCount = 0;
    $stockErrors = [];
    $stockDebug = [];
    
    foreach ($claimed_items as $item) {
        $item_code = trim($item['itemCode']);
        $quantity = intval($item['quantity']);
        
        $stockDebug[] = "Processing item: $item_code, qty: $quantity, branch: '$user_branch'";
        
        // Check if sufficient stock exists in stock_on_hand (with TRIM for matching)
        $checkStockSQL = "SELECT quantity, branch FROM stock_on_hand WHERE TRIM(item_code) = TRIM(?) AND TRIM(branch) = TRIM(?)";
        $checkStmt = $conn->prepare($checkStockSQL);
        
        if (!$checkStmt) {
            $stockErrors[] = "Failed to prepare stock check for item $item_code: " . $conn->error;
            continue;
        }
        
        $checkStmt->bind_param('ss', $item_code, $user_branch);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            $stockErrors[] = "Item $item_code not found in stock for branch '$user_branch'";
            $stockDebug[] = "No stock record found for item $item_code in branch '$user_branch'";
            $checkStmt->close();
            continue;
        }
        
        $stockRow = $checkResult->fetch_assoc();
        $currentStock = intval($stockRow['quantity']);
        $stockBranch = $stockRow['branch'];
        $stockDebug[] = "Found stock: item=$item_code, current=$currentStock, branch='$stockBranch'";
        $checkStmt->close();
        
        // Check if sufficient quantity is available
        if ($currentStock < $quantity) {
            $stockErrors[] = "Insufficient stock for item $item_code in branch '$stockBranch' (available: $currentStock, requested: $quantity)";
            $stockDebug[] = "Insufficient stock: available=$currentStock, requested=$quantity";
            continue;
        }
        
        // Deduct from stock_on_hand (with TRIM for matching)
        $stockDeductionSQL = "UPDATE stock_on_hand 
                              SET quantity = quantity - ? 
                              WHERE TRIM(item_code) = TRIM(?) AND TRIM(branch) = TRIM(?) AND quantity >= ?";
        $stockStmt = $conn->prepare($stockDeductionSQL);
        
        if (!$stockStmt) {
            $stockErrors[] = "Failed to prepare stock deduction for item $item_code: " . $conn->error;
            continue;
        }
        
        $stockStmt->bind_param('issi', $quantity, $item_code, $user_branch, $quantity);
        
        if (!$stockStmt->execute()) {
            $stockErrors[] = "Failed to deduct stock for item $item_code: " . $stockStmt->error;
            $stockStmt->close();
            continue;
        }
        
        if ($stockStmt->affected_rows > 0) {
            $stockDeductedCount++;
            $stockDebug[] = "Successfully deducted $quantity from item $item_code";
        } else {
            $stockErrors[] = "No stock deducted for item $item_code in branch '$user_branch' (check: current=$currentStock, requested=$quantity)";
            $stockDebug[] = "UPDATE affected 0 rows for item $item_code";
        }
        
        $stockStmt->close();
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
