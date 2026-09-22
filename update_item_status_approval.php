<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
$status = isset($_POST['status']) ? $conn->real_escape_string($_POST['status']) : '';
$username = isset($_SESSION['username']) ? $conn->real_escape_string($_SESSION['username']) : '';
$user_full_name = isset($_SESSION['user_name']) ? $conn->real_escape_string($_SESSION['user_name']) : $username;

if (empty($entry_id) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate status
if (!in_array($status, ['Approved', 'Disapproved'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

$conn->begin_transaction();
try {
    // Lock the entry row
    $stmt = $conn->prepare("SELECT id, status FROM sales_entry_status_log WHERE id = ? FOR UPDATE");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param('i', $entry_id);
    $stmt->execute();
    $entry = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$entry) {
        throw new Exception("Entry not found");
    }

    $currentStatus = $entry['status'] ?? 'Pending';

    // If already in the requested status, don't re-apply changes
    if ($currentStatus === $status) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Entry already $status"]);
        exit;
    }

    // Get entry details
    $getEntry = $conn->prepare("SELECT entry_date, branch_code, branch_name, stock_type, remarks FROM sales_entry_status_log WHERE id = ?");
    $getEntry->bind_param('i', $entry_id);
    $getEntry->execute();
    $entryData = $getEntry->get_result()->fetch_assoc();
    $getEntry->close();
    
    if (!$entryData) {
        throw new Exception("Entry details not found");
    }
    
    $branch_name = $entryData['branch_name'];
    $stock_type = $entryData['stock_type'];

    // Update entry status
    if ($status === 'Disapproved') {
        // For disapproval, set disapproved_by user
        $sql = "UPDATE sales_entry_status_log
                SET status = ?,
                    approver = ?,
                    approval_date = NOW(),
                    disapproved_by = ?
                WHERE id = ?";
        $stmtStatus = $conn->prepare($sql);
        $stmtStatus->bind_param('sssi', $status, $username, $username, $entry_id);
        $stmtStatus->execute();
        $stmtStatus->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Entry disapproved successfully"]);
        exit;
    } elseif ($status === 'Approved') {
        // Get all items for this request
        $getItems = $conn->prepare("SELECT item_code, item_description, imei, quantity FROM sales_entry_status_items WHERE log_id = ?");
        $getItems->bind_param('i', $entry_id);
        $getItems->execute();
        $itemsResult = $getItems->get_result();
        
        $updated_count = 0;
        $errors = [];
        
        // Process each item and update stock status
        while ($item = $itemsResult->fetch_assoc()) {
            $item_code = trim($item['item_code']);
            $imei = isset($item['imei']) ? trim($item['imei']) : '';
            $quantity = intval($item['quantity']);
            
            if (empty($item_code)) {
                continue;
            }
            
            if (!empty($imei)) {
                // Get the previous status before updating
                $getPrevStatus = $conn->prepare("SELECT status FROM stock_on_hand WHERE item_code = ? AND imei = ? AND branch = ? LIMIT 1");
                $getPrevStatus->bind_param('sss', $item_code, $imei, $branch_name);
                $getPrevStatus->execute();
                $prevResult = $getPrevStatus->get_result();
                $previous_status = 'N/A';
                if ($prevRow = $prevResult->fetch_assoc()) {
                    $previous_status = $prevRow['status'];
                }
                $getPrevStatus->close();
                
                // For serialized items - update by item_code and IMEI
                $update_sql = "UPDATE stock_on_hand 
                              SET status = ?,
                                  modified_by = ?,
                                  modified_at = NOW()
                              WHERE item_code = ? 
                              AND imei = ? 
                              AND branch = ?";
                
                $stmt = $conn->prepare($update_sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param('sssss', $stock_type, $username, $item_code, $imei, $branch_name);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $updated_count++;
                        
                        // Record history
                        $historyStmt = $conn->prepare("INSERT INTO stock_status_history 
                            (item_code, imei, quantity, branch, previous_status, new_status, changed_by, approval_log_id, remarks) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $historyStmt->bind_param('ssissssis', $item_code, $imei, $quantity, $branch_name, 
                            $previous_status, $stock_type, $username, $entry_id, $entryData['remarks']);
                        $historyStmt->execute();
                        $historyStmt->close();
                    }
                } else {
                    $errors[] = "Failed to update $item_code (Serial: $imei)";
                }
                
                $stmt->close();
            } else {
                // For non-serialized items
                $check_sql = "SELECT id, quantity, status FROM stock_on_hand 
                             WHERE item_code = ? 
                             AND branch = ?
                             AND (imei IS NULL OR imei = '')
                             ORDER BY 
                                CASE 
                                    WHEN status != ? THEN 0 
                                    ELSE 1 
                                END,
                                id ASC";
                
                $check_stmt = $conn->prepare($check_sql);
                if (!$check_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $check_stmt->bind_param('sss', $item_code, $branch_name, $stock_type);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                $all_records = [];
                while ($row = $check_result->fetch_assoc()) {
                    $all_records[] = $row;
                }
                $check_stmt->close();
                
                if (count($all_records) == 0) {
                    $errors[] = "No stock found for $item_code";
                    continue;
                }
                
                $remaining_qty_needed = $quantity;
                
                foreach ($all_records as $record) {
                    if ($remaining_qty_needed <= 0) {
                        break;
                    }
                    
                    $record_id = $record['id'];
                    $record_qty = intval($record['quantity']);
                    $record_status = $record['status'];
                    
                    if ($record_status === $stock_type) {
                        continue;
                    }
                    
                    if ($record_qty <= $remaining_qty_needed) {
                        $update_sql = "UPDATE stock_on_hand 
                                      SET status = ?,
                                          modified_by = ?,
                                          modified_at = NOW()
                                      WHERE id = ?";
                        
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param('ssi', $stock_type, $username, $record_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                        
                        $remaining_qty_needed -= $record_qty;
                    } else {
                        $qty_to_take = $remaining_qty_needed;
                        $qty_to_keep = $record_qty - $qty_to_take;
                        
                        $reduce_sql = "UPDATE stock_on_hand 
                                      SET quantity = ?,
                                          modified_by = ?,
                                          modified_at = NOW()
                                      WHERE id = ?";
                        
                        $reduce_stmt = $conn->prepare($reduce_sql);
                        $reduce_stmt->bind_param('isi', $qty_to_keep, $username, $record_id);
                        $reduce_stmt->execute();
                        $reduce_stmt->close();
                        
                        $get_orig_sql = "SELECT * FROM stock_on_hand WHERE id = ?";
                        $get_stmt = $conn->prepare($get_orig_sql);
                        $get_stmt->bind_param('i', $record_id);
                        $get_stmt->execute();
                        $original_record = $get_stmt->get_result()->fetch_assoc();
                        $get_stmt->close();
                        
                        $columns_to_copy = [];
                        $values_to_copy = [];
                        $types_string = '';
                        
                        $possible_columns = [
                            'item_code', 'description', 'branch', 'item_type', 'dr_date', 
                            'dr_number', 'imei', 'brand', 'family_code', 
                            'group_name', 'department', 'system_entry_date'
                        ];
                        
                        foreach ($possible_columns as $col) {
                            if (array_key_exists($col, $original_record)) {
                                $columns_to_copy[] = $col;
                                $values_to_copy[] = $original_record[$col];
                                $types_string .= 's';
                            }
                        }
                        
                        $columns_to_copy[] = 'quantity';
                        $values_to_copy[] = $qty_to_take;
                        $types_string .= 'i';
                        
                        $columns_to_copy[] = 'status';
                        $values_to_copy[] = $stock_type;
                        $types_string .= 's';
                        
                        $columns_to_copy[] = 'modified_by';
                        $values_to_copy[] = $username;
                        $types_string .= 's';
                        
                        $columns_to_copy[] = 'modified_at';
                        $values_to_copy[] = date('Y-m-d H:i:s');
                        $types_string .= 's';
                        
                        $columns_list = implode(', ', $columns_to_copy);
                        $placeholders = implode(', ', array_fill(0, count($columns_to_copy), '?'));
                        
                        $insert_sql = "INSERT INTO stock_on_hand ($columns_list) VALUES ($placeholders)";
                        $insert_stmt = $conn->prepare($insert_sql);
                        $insert_stmt->bind_param($types_string, ...$values_to_copy);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                        
                        $remaining_qty_needed -= $qty_to_take;
                    }
                }
                
                $updated_count++;
            }
        }
        $getItems->close();
        
        // Update the log entry with approval and updated count
        $sql = "UPDATE sales_entry_status_log
                SET status = ?,
                    approver = ?,
                    approval_date = NOW(),
                    updated_count = ?
                WHERE id = ?";
        $stmtStatus = $conn->prepare($sql);
        $stmtStatus->bind_param('ssii', $status, $username, $updated_count, $entry_id);
        $stmtStatus->execute();
        $stmtStatus->close();
        
        $conn->commit();
        
        $message = "Entry approved successfully! Updated $updated_count item(s) to status '$stock_type'.";
        if (!empty($errors)) {
            $message .= " Some errors occurred: " . implode('; ', $errors);
        }
        
        echo json_encode(['success' => true, 'message' => $message, 'updated_count' => $updated_count]);
        exit;
    } else {
        throw new Exception("Unsupported status: {$status}");
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()]);
}

$conn->close();
?>
