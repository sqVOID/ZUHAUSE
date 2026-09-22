<?php
require_once 'session_check.php';
// Suppress all output before JSON response
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';
session_start();

// Clean any output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

// Get invoice number from request
$invoice_no = isset($_GET['invoice_no']) ? trim($_GET['invoice_no']) : '';

if (empty($invoice_no)) {
    // Clean any output buffer before sending JSON
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invoice number is required']);
    exit;
}

try {
    // Fetch sales entry data
    $sales_query = $conn->prepare("
        SELECT 
            se.id,
            se.invoice_no,
            se.first_name,
            se.last_name,
            se.address,
            se.contact_no,
            se.email,
            se.assisted_by,
            se.remarks,
            se.total_qty,
            se.discount,
            se.total_amount,
            se.points,
            se.commission,
            se.status,
            se.created_at
        FROM sales_entry se
        WHERE se.invoice_no = ?
        LIMIT 1
    ");

    $sales_query->bind_param("s", $invoice_no);
    $sales_query->execute();
    $result = $sales_query->get_result();

    if ($result->num_rows === 0) {
        // Clean any output buffer before sending JSON
        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
        exit;
    }

    $sales_data = $result->fetch_assoc();
    
    // Check if invoice is voided
    if (isset($sales_data['status']) && strtolower($sales_data['status']) === 'voided') {
        // Clean any output buffer before sending JSON
        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Cannot refund a voided invoice. Invoice ' . $invoice_no . ' has been voided.']);
        exit;
    }
    
    // Check if invoice has already been refunded
    $refund_check = $conn->prepare("SELECT id FROM refunds WHERE invoice_no = ? LIMIT 1");
    $refund_check->bind_param("s", $invoice_no);
    $refund_check->execute();
    $refund_result = $refund_check->get_result();
    
    if ($refund_result->num_rows > 0) {
        // Clean any output buffer before sending JSON
        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'This invoice has already been refunded. Invoice: ' . $invoice_no]);
        $refund_check->close();
        exit;
    }
    $refund_check->close();
    
    $sales_entry_id = $sales_data['id'];

    // Fetch sales items
    $items_query = $conn->prepare("
        SELECT 
            item_description,
            imei,
            quantity,
            price,
            item_code
        FROM sales_entry_items
        WHERE sales_entry_id = ?
        ORDER BY id
    ");

    $items_query->bind_param("i", $sales_entry_id);
    $items_query->execute();
    $items_result = $items_query->get_result();

    $items = [];
    $invoice_subtotal = 0;
    
    // First pass: calculate invoice subtotal and collect items
    while ($item = $items_result->fetch_assoc()) {
        $item_subtotal = $item['quantity'] * $item['price'];
        $invoice_subtotal += $item_subtotal;
        $items[] = $item;
    }
    
    // Second pass: apply proportional discount to each item
    $invoice_discount = floatval($sales_data['discount']);
    $items_with_discount = [];
    
    foreach ($items as $item) {
        $item_subtotal = $item['quantity'] * $item['price'];
        
        // Calculate proportional discount for this item
        if ($invoice_subtotal > 0 && $invoice_discount > 0) {
            $proportion = $item_subtotal / $invoice_subtotal;
            $item_discount = round($invoice_discount * $proportion);
            $discounted_item_total = $item_subtotal - $item_discount;
            // Calculate discounted price per unit
            $discounted_price = $item['quantity'] > 0 ? $discounted_item_total / $item['quantity'] : $item['price'];
        } else {
            $discounted_price = $item['price'];
        }
        
        $items_with_discount[] = [
            'item_description' => $item['item_description'],
            'imei' => $item['imei'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],  // Original price
            'discounted_price' => $discounted_price,  // Price after proportional discount
            'item_code' => $item['item_code']
        ];
    }

    // Prepare response
    $response = [
        'status' => 'success',
        'data' => [
            'invoice_no' => $sales_data['invoice_no'],
            'customer' => [
                'first_name' => $sales_data['first_name'] ?? '',
                'last_name' => $sales_data['last_name'] ?? '',
                'address' => $sales_data['address'] ?? '',
                'contact_no' => $sales_data['contact_no'] ?? '',
                'email' => $sales_data['email'] ?? ''
            ],
            'assisted_by' => $sales_data['assisted_by'] ?? '',
            'remarks' => $sales_data['remarks'] ?? '',
            'total_qty' => $sales_data['total_qty'] ?? 0,
            'discount' => $sales_data['discount'] ?? 0,
            'total_amount' => $sales_data['total_amount'] ?? 0,
            'points' => $sales_data['points'] ?? 0,
            'commission' => $sales_data['commission'] ?? 0,
            'created_at' => $sales_data['created_at'] ?? '',
            'items' => $items_with_discount
        ]
    ];

    // Clean any output buffer before sending JSON
    if (ob_get_length()) ob_clean();
    echo json_encode($response);

    $sales_query->close();
    $items_query->close();

} catch (Exception $e) {
    // Clean any output buffer before sending JSON
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();

// Ensure clean output
if (ob_get_length()) ob_end_flush();
?>
