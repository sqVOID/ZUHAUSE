<?php
// Start output buffering FIRST to catch any unwanted output
ob_start();

// Suppress all errors and warnings
error_reporting(0);
ini_set('display_errors', 0);

// Include required files
require_once 'session_check.php';
include 'config.php';

// Clear any buffered content and set JSON header
ob_clean();
header('Content-Type: application/json');

if (isset($_GET['invoice_no'])) {
    $invoice_no = trim($_GET['invoice_no']);
    $invoice_no_escaped = $conn->real_escape_string($invoice_no);
    
    // Get user's branch access
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    
    // Build branch filter condition
    $branch_condition = '';
    
    // Only Super-Admin can see all branches
    if ($system_level !== 'Super-Admin') {
        // Sub-admin and regular users are restricted to their assigned branches
        if (!empty($user_branch)) {
            // Handle multiple branches (comma-separated)
            $branch_names = array_map('trim', explode(',', $user_branch));
            $branch_names_escaped = array_map(function($name) use ($conn) {
                return "'" . $conn->real_escape_string($name) . "'";
            }, $branch_names);
            $branch_list = implode(',', $branch_names_escaped);
            
            $branch_condition = " AND uf.branch IN ($branch_list)";
        } else {
            // No branch assigned - should not see any results
            $branch_condition = " AND 0 = 1";
        }
    }
    
    // Search for freebies by invoice number
    // Use TRIM and exact match for better accuracy
    $sql = "SELECT 
                uf.id,
                uf.sales_entry_id,
                uf.invoice_number,
                uf.item_code,
                uf.item_description,
                uf.quantity,
                uf.note,
                uf.branch,
                uf.created_by,
                uf.created_at,
                uf.claimed_at,
                uf.status,
                se.first_name,
                se.last_name,
                se.address,
                se.contact_no,
                se.email,
                se.encoder
            FROM unclaimed_freebies uf
            LEFT JOIN sales_entry se ON uf.sales_entry_id = se.id
            WHERE TRIM(uf.invoice_number) = '$invoice_no_escaped' 
            $branch_condition
            ORDER BY uf.id ASC";

    $result = $conn->query($sql);

    if ($result) {
        if ($result->num_rows > 0) {
            $unclaimed_items = [];
            $customer_details = null;
            
            while ($row = $result->fetch_assoc()) {
                // Build unclaimed items array
                $unclaimed_items[] = [
                    'id' => $row['id'],
                    'sales_entry_id' => $row['sales_entry_id'],
                    'invoice_number' => $row['invoice_number'],
                    'item_code' => $row['item_code'],
                    'item_description' => $row['item_description'],
                    'quantity' => $row['quantity'],
                    'note' => $row['note'],
                    'branch' => $row['branch'],
                    'created_by' => $row['created_by'],
                    'created_at' => $row['created_at'],
                    'claimed_at' => $row['claimed_at'],
                    'status' => $row['status']
                ];
                
                // Get customer details (same for all items in the invoice)
                if ($customer_details === null) {
                    $customer_details = [
                        'first_name' => $row['first_name'] ?? '',
                        'last_name' => $row['last_name'] ?? '',
                        'address' => $row['address'] ?? '',
                        'contact_no' => $row['contact_no'] ?? '',
                        'email' => $row['email'] ?? '',
                        'encoder' => $row['encoder'] ?? ''
                    ];
                }
            }
            
            // Check if ALL items for this invoice are already claimed
            $allClaimed = count($unclaimed_items) > 0 && array_reduce($unclaimed_items, function($carry, $item) {
                return $carry && strtolower($item['status']) === 'claimed';
            }, true);

            if ($allClaimed) {
                echo json_encode([
                    'status' => 'all_claimed',
                    'message' => 'All freebies for this invoice have already been claimed.',
                    'unclaimed_items' => $unclaimed_items,
                    'customer_details' => $customer_details
                ]);
            } else {
                echo json_encode([
                    'status' => 'success',
                    'unclaimed_items' => $unclaimed_items,
                    'customer_details' => $customer_details
                ]);
            }
        } else {
            echo json_encode([
                'status' => 'not_found',
                'message' => 'No freebies found for this invoice number'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database query failed: ' . $conn->error
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No invoice number provided'
    ]);
}

$conn->close();
ob_end_flush();
?>
