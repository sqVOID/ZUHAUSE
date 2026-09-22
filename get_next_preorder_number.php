<?php
require_once 'session_check.php';
header('Content-Type: application/json');
include 'config.php';

try {
    // Get current date for invoice format PRE-YYYYMMDD-ID
    $today = date('Ymd');
    $prefix = "PRE-{$today}-";
    
    // Get the highest invoice number for today
    $query = "SELECT invoice_no FROM preorders WHERE invoice_no LIKE ? ORDER BY invoice_no DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $like_pattern = $prefix . '%';
    $stmt->bind_param('s', $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_invoice = $row['invoice_no'];
        
        // Extract the ID part (last 4 digits)
        $last_id = intval(substr($last_invoice, -4));
        $next_id = $last_id + 1;
    } else {
        // First invoice for today
        $next_id = 1;
    }
    
    // Format the ID with leading zeros (4 digits)
    $formatted_id = str_pad($next_id, 4, '0', STR_PAD_LEFT);
    $next_invoice_no = $prefix . $formatted_id;
    
    echo json_encode([
        'status' => 'success',
        'invoice_no' => $next_invoice_no
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error generating invoice number: ' . $e->getMessage()
    ]);
}

$conn->close();
?>