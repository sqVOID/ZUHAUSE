<?php
require_once 'session_check.php';
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

try {
    include 'config.php';
    // Don't call session_start() here - config.php already does it
    
    ob_clean();
    header('Content-Type: application/json');
    
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Log the incoming request for debugging
    error_log("RE-ENTRY Request received: " . print_r($data, true));
    
    // Validate input
    if (!isset($data['sales_entry_id']) || !isset($data['invoice_no'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required fields'
        ]);
        exit;
    }
    
    // Validate reason to modify
    if (!isset($data['reason_to_modify']) || empty(trim($data['reason_to_modify']))) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Reason to Modify is required for RE-ENTRY'
        ]);
        exit;
    }
    
    $sales_entry_id = $conn->real_escape_string($data['sales_entry_id']);
    $invoice_no = $conn->real_escape_string($data['invoice_no']);
    $reason_to_modify = $conn->real_escape_string(trim($data['reason_to_modify']));
    
    // Get the current user for logging
    $reentry_by = isset($_SESSION['user_first_name']) && isset($_SESSION['user_last_name']) 
        ? $_SESSION['user_first_name'] . ' ' . $_SESSION['user_last_name'] 
        : 'System';
    
    // First, verify the sale exists and is voided
    $check_query = "SELECT status FROM sales_entry WHERE id = '$sales_entry_id' AND invoice_no = '$invoice_no'";
    $check_result = $conn->query($check_query);
    
    if (!$check_result || $check_result->num_rows === 0) {
        error_log("RE-ENTRY Error: Sales entry not found - ID: $sales_entry_id, Invoice: $invoice_no");
        echo json_encode([
            'status' => 'error',
            'message' => 'Sales entry not found'
        ]);
        exit;
    }
    
    $row = $check_result->fetch_assoc();
    if ($row['status'] !== 'voided') {
        error_log("RE-ENTRY Error: Sale is not voided - Current status: " . $row['status']);
        echo json_encode([
            'status' => 'error',
            'message' => 'This sale is not voided. Only voided sales can be re-entered.'
        ]);
        exit;
    }
    
    // Get branch information for stock deduction
    $branch_query = "SELECT branch_code FROM sales_entry WHERE id = '$sales_entry_id'";
    $branch_result = $conn->query($branch_query);
    $branch_row = $branch_result->fetch_assoc();
    $branch_code = $branch_row['branch_code'];
    
    // Get branch name from branches table for stock_on_hand matching
    $stock_branch_name = '';
    if (!empty($branch_code)) {
        $branch_lookup = $conn->query("SELECT branch_name FROM branches WHERE branch_code = '$branch_code' LIMIT 1");
        if ($branch_lookup && $branch_lookup->num_rows > 0) {
            $stock_branch_name = $branch_lookup->fetch_assoc()['branch_name'];
        }
    }
    
    // Start transaction for re-entry
    $conn->begin_transaction();
    
    try {
        // RE-ENTRY NOTE: We do NOT deduct stock again!
        // The stock was already deducted when the sale was first completed.
        // When it was voided, the stock should have been returned.
        // RE-ENTRY just changes status from 'voided' back to 'completed'
        // and re-deducts the stock that was returned during voiding.
        
        // Get all items from this sale to RE-deduct from stock (since they were returned when voided)
        $items_query = "SELECT item_code, imei, quantity FROM sales_entry_items WHERE sales_entry_id = '$sales_entry_id'";
        $items_result = $conn->query($items_query);
        
        if ($items_result && $items_result->num_rows > 0) {
            error_log("RE-ENTRY: Processing " . $items_result->num_rows . " items for stock RE-deduction");
            
            while ($item = $items_result->fetch_assoc()) {
                $item_code = trim(strtoupper($item['item_code']));
                $imei = isset($item['imei']) ? trim(strtoupper($item['imei'])) : '';
                $quantity = intval($item['quantity']);
                
                // RE-Deduct stock (since it was returned when voided)
                if (!empty($imei)) {
                    // Item has serial number - remove exact serial from stock
                    error_log("RE-ENTRY: RE-deducting serial item - Code: $item_code, IMEI: $imei");
                    
                    // Check if the serial exists in stock_on_hand (returned during void)
                    if (!empty($stock_branch_name) && strtolower($stock_branch_name) !== 'all branches') {
                        $check_serial = $conn->prepare("
                            SELECT id FROM stock_on_hand
                            WHERE TRIM(imei) = TRIM(?)
                              AND TRIM(item_code) = TRIM(?)
                              AND branch = ?
                            LIMIT 1
                        ");
                        $check_serial->bind_param("sss", $imei, $item_code, $stock_branch_name);
                    } else {
                        $check_serial = $conn->prepare("
                            SELECT id FROM stock_on_hand
                            WHERE TRIM(imei) = TRIM(?)
                              AND TRIM(item_code) = TRIM(?)
                            LIMIT 1
                        ");
                        $check_serial->bind_param("ss", $imei, $item_code);
                    }
                    $check_serial->execute();
                    $serial_exists = $check_serial->get_result();
                    $check_serial->close();
                    
                    if ($serial_exists && $serial_exists->num_rows > 0) {
                        // Serial exists - remove it from stock
                        if (!empty($stock_branch_name) && strtolower($stock_branch_name) !== 'all branches') {
                            $delete_stock = $conn->prepare("
                                DELETE FROM stock_on_hand
                                WHERE TRIM(imei) = TRIM(?)
                                  AND TRIM(item_code) = TRIM(?)
                                  AND branch = ?
                                LIMIT 1
                            ");
                            $delete_stock->bind_param("sss", $imei, $item_code, $stock_branch_name);
                        } else {
                            $delete_stock = $conn->prepare("
                                DELETE FROM stock_on_hand
                                WHERE TRIM(imei) = TRIM(?)
                                  AND TRIM(item_code) = TRIM(?)
                                LIMIT 1
                            ");
                            $delete_stock->bind_param("ss", $imei, $item_code);
                        }
                        $delete_stock->execute();
                        $delete_stock->close();
                        error_log("RE-ENTRY: Serial item removed from stock - $imei");
                    } else {
                        // Serial doesn't exist in stock - this is OK, it means it wasn't returned during void
                        error_log("RE-ENTRY: Serial $imei not in stock (already out of stock, no re-deduction needed)");
                    }
                    
                } else {
                    // Item without serial number - reduce quantity from available stock
                    error_log("RE-ENTRY: RE-deducting non-serial item - Code: $item_code, Qty: $quantity");
                    
                    // Check current quantity first
                    if (!empty($stock_branch_name) && strtolower($stock_branch_name) !== 'all branches') {
                        $check_qty = $conn->prepare("
                            SELECT quantity FROM stock_on_hand
                            WHERE TRIM(item_code) = TRIM(?)
                              AND branch = ?
                            LIMIT 1
                        ");
                        $check_qty->bind_param("ss", $item_code, $stock_branch_name);
                    } else {
                        $check_qty = $conn->prepare("
                            SELECT quantity FROM stock_on_hand
                            WHERE TRIM(item_code) = TRIM(?)
                            LIMIT 1
                        ");
                        $check_qty->bind_param("s", $item_code);
                    }
                    $check_qty->execute();
                    $qty_result = $check_qty->get_result();
                    $check_qty->close();
                    
                    if ($qty_result && $qty_result->num_rows > 0) {
                        $current_qty = $qty_result->fetch_assoc()['quantity'];
                        
                        if ($current_qty >= $quantity) {
                            // Sufficient quantity - deduct it
                            if (!empty($stock_branch_name) && strtolower($stock_branch_name) !== 'all branches') {
                                $update_stock = $conn->prepare("
                                    UPDATE stock_on_hand
                                    SET quantity = quantity - ?
                                    WHERE TRIM(item_code) = TRIM(?)
                                      AND branch = ?
                                      AND quantity >= ?
                                    LIMIT 1
                                ");
                                $update_stock->bind_param("issi", $quantity, $item_code, $stock_branch_name, $quantity);
                            } else {
                                $update_stock = $conn->prepare("
                                    UPDATE stock_on_hand
                                    SET quantity = quantity - ?
                                    WHERE TRIM(item_code) = TRIM(?)
                                      AND quantity >= ?
                                    LIMIT 1
                                ");
                                $update_stock->bind_param("isi", $quantity, $item_code, $quantity);
                            }
                            $update_stock->execute();
                            $update_stock->close();
                            error_log("RE-ENTRY: Non-serial item quantity deducted - $item_code");
                        } else {
                            // Insufficient quantity - this is OK, it means stock wasn't returned during void
                            error_log("RE-ENTRY: Insufficient stock for $item_code (already out of stock, no re-deduction needed)");
                        }
                    } else {
                        error_log("RE-ENTRY: Item $item_code not found in stock (already out of stock, no re-deduction needed)");
                    }
                }
            }
        } else {
            error_log("RE-ENTRY Warning: No items found for sales_entry_id $sales_entry_id");
        }
        
        // Update the status from 'voided' to 'completed' and save the reason
        $update_query = "UPDATE sales_entry 
                         SET status = 'completed', 
                             reason_to_modify = '$reason_to_modify',
                             updated_at = NOW() 
                         WHERE id = '$sales_entry_id' 
                         AND invoice_no = '$invoice_no'";
        
        if (!$conn->query($update_query)) {
            throw new Exception("Failed to update sales entry status: " . $conn->error);
        }
        
        error_log("RE-ENTRY Success: Invoice $invoice_no status changed to completed with reason: $reason_to_modify");
        
        // Log the re-entry action (check if table exists first)
        $table_check = $conn->query("SHOW TABLES LIKE 'sales_audit_log'");
        if ($table_check && $table_check->num_rows > 0) {
            $log_query = "INSERT INTO sales_audit_log (sales_entry_id, invoice_no, action, performed_by, created_at) 
                          VALUES ('$sales_entry_id', '$invoice_no', 'RE-ENTRY: $reason_to_modify', '$reentry_by', NOW())";
            $conn->query($log_query);
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Sale successfully re-entered, stock deducted, and status changed to completed',
            'invoice_no' => $invoice_no
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("RE-ENTRY Transaction Error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    $conn->close();

} catch (Exception $e) {
    error_log("RE-ENTRY Exception: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
