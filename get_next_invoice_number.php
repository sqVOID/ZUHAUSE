<?php
require_once 'session_check.php';
/**
 * Helper script to get the next invoice number for a branch
 * Usage: Include this file or call via AJAX
 */

// Uncomment if calling directly (not via include)
// include 'config.php';

/**
 * Get the active booklet number configuration for a branch and page type
 * @param mysqli $conn Database connection
 * @param string $branch_code Branch code
 * @param string $page_type Page type (salesentry, preorder, stocktransfer, etc.)
 * @return array|null Booklet configuration or null if not found
 */
function getBookletConfig($conn, $branch_code, $page_type = 'salesentry') {
    // Only preorder, salesentry, and salestrade-in are connected to booklet numbers
    // salestrade-in uses the same booklet as salesentry
    if ($page_type !== 'salesentry' && $page_type !== 'preorder' && $page_type !== 'salestrade-in') {
        return null;
    }
    
    // Both salesentry and salestrade-in share the same active booklet configuration for the branch
    // Get the booklet with the lowest booklet_no that is still active
    $sql = "SELECT * FROM booklet_numbers 
            WHERE branch_code = ? AND status = 'Active' 
            ORDER BY booklet_no ASC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $branch_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $booklet = $result->fetch_assoc();
        
        // Check if current number is cancelled and skip to next available
        $booklet = skipCancelledInvoiceNumbers($conn, $booklet);
        
        return $booklet;
    }
    
    return null;
}

/**
 * Skip cancelled invoice numbers and return the next available number
 * @param mysqli $conn Database connection
 * @param array $booklet Booklet configuration
 * @return array Updated booklet with next available invoice number
 */
function skipCancelledInvoiceNumbers($conn, $booklet) {
    if (!$booklet) {
        return $booklet;
    }
    
    $booklet_id = $booklet['id'];
    $current_number = $booklet['current_number'];
    $ending_number = $booklet['ending_number'];
    $format = $booklet['booklet_format'];
    
    // Extract numeric part for comparison
    $current_parts = explode('-', $current_number);
    $current_num = intval(end($current_parts));
    
    $ending_parts = explode('-', $ending_number);
    $ending_num = intval(end($ending_parts));
    
    // Check if cancelled_invoices table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'cancelled_invoices'");
    if ($table_check->num_rows === 0) {
        return $booklet;
    }
    
    // Keep checking and incrementing until we find a non-cancelled number
    $max_iterations = 1000; // Prevent infinite loop
    $iterations = 0;
    
    while ($iterations < $max_iterations) {
        // Generate full invoice number for checking
        $check_invoice_number = '';
        if (!empty($booklet['prefix'])) {
            $check_invoice_number .= $booklet['prefix'];
        }
        $check_invoice_number .= $current_number;
        if (!empty($booklet['suffix'])) {
            $check_invoice_number .= $booklet['suffix'];
        }
        
        // Check if this invoice number is cancelled
        $check_stmt = $conn->prepare("SELECT id FROM cancelled_invoices WHERE booklet_id = ? AND invoice_number = ?");
        $check_stmt->bind_param("is", $booklet_id, $check_invoice_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            // This number is not cancelled, use it
            break;
        }
        
        // This number is cancelled, increment to next
        $current_num++;
        
        // Check if we've exceeded the ending number
        if ($current_num > $ending_num) {
            // Reached the end of the booklet, return current state
            break;
        }
        
        // Update current_number with proper padding
        if ($format === 'numeric') {
            $parts = explode('-', $current_number);
            $last_index = count($parts) - 1;
            $parts[$last_index] = str_pad($current_num, strlen($parts[$last_index]), '0', STR_PAD_LEFT);
            $current_number = implode('-', $parts);
        } else {
            // For simple numeric format
            $padding = strlen($current_number);
            $current_number = str_pad($current_num, $padding, '0', STR_PAD_LEFT);
        }
        
        $iterations++;
    }
    
    // Update the booklet's current_number if it changed
    if ($booklet['current_number'] !== $current_number) {
        $update_stmt = $conn->prepare("UPDATE booklet_numbers SET current_number = ? WHERE id = ?");
        $update_stmt->bind_param("si", $current_number, $booklet_id);
        $update_stmt->execute();
        
        $booklet['current_number'] = $current_number;
    }
    
    return $booklet;
}

/**
 * Generate the full invoice number based on booklet configuration
 * @param array $booklet Booklet configuration
 * @return string Full invoice number
 */
function generateInvoiceNumber($booklet) {
    if (!$booklet) {
        return null;
    }
    
    $invoice_number = '';
    
    // Add prefix if exists
    if (!empty($booklet['prefix'])) {
        $invoice_number .= $booklet['prefix'];
    }
    
    // Add current number
    $invoice_number .= $booklet['current_number'];
    
    // Add suffix if exists
    if (!empty($booklet['suffix'])) {
        $invoice_number .= $booklet['suffix'];
    }
    
    return $invoice_number;
}

/**
 * Increment the invoice number based on format type
 * @param string $current_number Current invoice number
 * @param string $format Format type (numeric, date_suffix, custom)
 * @return string Next invoice number
 */
function incrementInvoiceNumber($current_number, $format) {
    switch ($format) {
        case 'numeric':
            // Handle formats like: 0003128-092-149
            // Split by dashes and increment the last segment
            $parts = explode('-', $current_number);
            $last_index = count($parts) - 1;
            $parts[$last_index] = str_pad((int)$parts[$last_index] + 1, strlen($parts[$last_index]), '0', STR_PAD_LEFT);
            return implode('-', $parts);
            
        case 'date_suffix':
            // For date formats, typically you'd update the date
            // For now, keep the date and increment if there's a numeric suffix
            return $current_number; // User updates this manually
            
        case 'custom':
            // For custom formats, return as-is
            // User should update manually
            return $current_number;
            
        default:
            return $current_number;
    }
}

/**
 * Check if a booklet has reached or passed its ending number
 * @param string $current_number Current invoice number
 * @param string $ending_number Ending number from booklet
 * @return bool True if booklet is complete
 */
function isBookletComplete($current_number, $ending_number) {
    // Extract the last numeric segment from both numbers
    // Format examples: 0003128-092-149, 001-050, 0050
    
    // Get the last segment after the last dash, or the whole string if no dash
    $current_parts = explode('-', $current_number);
    $ending_parts = explode('-', $ending_number);
    
    $current_last = intval(end($current_parts));
    $ending_last = intval(end($ending_parts));
    
    return $current_last > $ending_last;
}

/**
 * Update the current invoice number in the database
 * Also checks if booklet is complete and sets complete_date if needed
 * @param mysqli $conn Database connection
 * @param int $booklet_id Booklet ID
 * @param string $new_number New invoice number
 * @return bool Success status
 */
function updateInvoiceNumber($conn, $booklet_id, $new_number) {
    // First, get the booklet's ending number and current complete_date
    $check_sql = "SELECT ending_number, complete_date FROM booklet_numbers WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $booklet_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $booklet = $result->fetch_assoc();
        $ending_number = $booklet['ending_number'];
        $complete_date = $booklet['complete_date'];
        
        // Check if booklet is complete and hasn't been marked complete yet
        if (empty($complete_date) && isBookletComplete($new_number, $ending_number)) {
            // Mark booklet as complete
            $sql = "UPDATE booklet_numbers SET current_number = ?, complete_date = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_number, $booklet_id);
            return $stmt->execute();
        }
    }
    
    // Normal update without marking as complete
    $sql = "UPDATE booklet_numbers SET current_number = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_number, $booklet_id);
    return $stmt->execute();
}

// Example usage when called via AJAX
if (isset($_POST['action']) && $_POST['action'] == 'get_invoice_number') {
    include 'config.php';
    
    $branch_code = $_POST['branch_code'] ?? '';
    $page_type = $_POST['page_type'] ?? 'salesentry';
    
    if (empty($branch_code)) {
        echo json_encode(['success' => false, 'message' => 'Branch code is required']);
        exit;
    }
    
    $booklet = getBookletConfig($conn, $branch_code, $page_type);
    
    if ($booklet) {
        $invoice_number = generateInvoiceNumber($booklet);
        
        // Add -PRE suffix for preorder page type
        if ($page_type === 'preorder') {
            $invoice_number .= '-PRE';
        }
        
        echo json_encode([
            'success' => true,
            'invoice_number' => $invoice_number,
            'booklet_id' => $booklet['id'],
            'format' => $booklet['booklet_format'],
            'page_type' => $booklet['page_type']
        ]);
    } else {
        // Generate fallback invoice number based on page type
        if ($page_type === 'preorder') {
            // Get the highest invoice number from preorders for this branch
            $escaped_branch_code = $conn->real_escape_string($branch_code);
            
            $preorder_query = $conn->query("
                SELECT invoice_no 
                FROM preorders 
                WHERE branch_code = '$escaped_branch_code'
                ORDER BY id DESC 
                LIMIT 1
            ");
            
            if ($preorder_query && $preorder_query->num_rows > 0) {
                $last_invoice = $preorder_query->fetch_assoc()['invoice_no'];
                
                // Try to extract the numeric sequence from various formats
                // Format examples: 0000001-43-PRE, PRE-20260706-0001, 0001-43-PRE-SD
                if (preg_match('/^(\d+)/', $last_invoice, $matches)) {
                    // Format: NNNNNNN-XX-PRE (numeric prefix)
                    $last_id = intval($matches[1]);
                    $next_id = $last_id + 1;
                    $padding = strlen($matches[1]); // Maintain same padding
                    
                    // Extract format pattern from last invoice
                    if (preg_match('/^(\d+)-(.+)$/', $last_invoice, $format_parts)) {
                        // Format: NNNNNNN-43-PRE or NNNNNNN-43-PRE-SD
                        $suffix_part = $format_parts[2]; // e.g., "43-PRE" or "43-PRE-SD"
                        $formatted_id = str_pad($next_id, $padding, '0', STR_PAD_LEFT);
                        $fallback_invoice = $formatted_id . '-' . $suffix_part;
                    } else {
                        // Simple numeric format
                        $formatted_id = str_pad($next_id, $padding, '0', STR_PAD_LEFT);
                        $fallback_invoice = $formatted_id;
                    }
                } else if (preg_match('/-(\d+)$/', $last_invoice, $matches)) {
                    // Format: PRE-YYYYMMDD-#### (numeric suffix)
                    $last_id = intval($matches[1]);
                    $next_id = $last_id + 1;
                    $padding = strlen($matches[1]);
                    
                    // Extract prefix part
                    $prefix = preg_replace('/-\d+$/', '', $last_invoice);
                    $formatted_id = str_pad($next_id, $padding, '0', STR_PAD_LEFT);
                    $fallback_invoice = $prefix . '-' . $formatted_id;
                } else {
                    // Unknown format, use default: PRE-YYYYMMDD-####
                    $today = date('Ymd');
                    $fallback_invoice = "PRE-{$today}-0001";
                }
            } else {
                // No previous preorders for this branch, use default format
                $today = date('Ymd');
                $fallback_invoice = "PRE-{$today}-0001";
            }
        } elseif ($page_type === 'stocktransfer') {
            // Stock transfer-specific fallback: ST-YYYYMMDD-###
            $today = date('Ymd');
            $prefix = "ST-{$today}-";
            
            $st_query = $conn->query("
                SELECT st_number 
                FROM stock_transfers 
                WHERE st_number LIKE 'ST-%'
                ORDER BY st_number DESC 
                LIMIT 1
            ");
            
            if ($st_query && $st_query->num_rows > 0) {
                $last_st = $st_query->fetch_assoc()['st_number'];
                // Extract the last 3 digits from ST-YYYYMMDD-### format
                $last_id = intval(substr($last_st, -3));
                $next_id = $last_id + 1;
            } else {
                $next_id = 1;
            }
            
            $formatted_id = str_pad($next_id, 3, '0', STR_PAD_LEFT);
            $fallback_invoice = $prefix . $formatted_id;
        } else {
            // Sales entry and other page types fallback: YYMMDD-BRANCHCODE-NNNNN
            $year  = date('y');
            $month = date('m');
            $day   = date('d');
            
            $escaped_branch_code = $conn->real_escape_string($branch_code);
            // Only look at invoices matching the standard format pattern: YYMMDD-BRANCHCODE-NNNNN
            // This excludes custom invoices from late entries like "test222", "12222", etc.
            $invoice_query = $conn->query("
                SELECT invoice_no 
                FROM sales_entry 
                WHERE branch_code = '$escaped_branch_code'
                AND invoice_no REGEXP '^[0-9]{6}-[A-Z0-9]+-[0-9]+$'
                ORDER BY id DESC 
                LIMIT 1
            ");
            
            if ($invoice_query && $invoice_query->num_rows > 0) {
                $last_invoice = $invoice_query->fetch_assoc()['invoice_no'];
                // Extract last 5 digits (supports both old 4-digit and new 5-digit formats)
                $sequence = intval(preg_replace('/.*-(\d+)$/', '$1', $last_invoice)) + 1;
            } else {
                $sequence = 1;
            }
            
            // Format: YYMMDD-BRANCHCODE-NNNNN (5-digit, never resets)
            $fallback_invoice = sprintf("%s%s%s-%s-%05d", $year, $month, $day, $branch_code, $sequence);
        }
        
        echo json_encode([
            'success' => true,
            'invoice_number' => $fallback_invoice,
            'booklet_id' => null,
            'format' => 'fallback',
            'page_type' => $page_type,
            'message' => 'No booklet configured for ' . $page_type . ', using default format'
        ]);
    }
    
    exit;
}

// Example usage when called via AJAX to update
if (isset($_POST['action']) && $_POST['action'] == 'update_invoice_number') {
    include 'config.php';
    
    $booklet_id = $_POST['booklet_id'] ?? 0;
    $format = $_POST['format'] ?? '';
    $current_number = $_POST['current_number'] ?? '';
    
    if (empty($booklet_id) || empty($current_number)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    $next_number = incrementInvoiceNumber($current_number, $format);
    
    if (updateInvoiceNumber($conn, $booklet_id, $next_number)) {
        echo json_encode([
            'success' => true,
            'next_number' => $next_number,
            'message' => 'Invoice number updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update invoice number'
        ]);
    }
    
    exit;
}


// If accessed directly via URL/AJAX and no action was matched
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action or missing parameters'
    ]);
    exit;
}
?>