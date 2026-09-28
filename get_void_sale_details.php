<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['invoice_no']) || empty($_GET['invoice_no'])) {
        echo json_encode(['success' => false, 'error' => 'Invoice number is required']);
        exit;
    }

    $invoice_no = $conn->real_escape_string($_GET['invoice_no']);

    // Fetch void sale header
    $header_query = "SELECT se.*, b.branch_name 
                     FROM sales_entry se
                     LEFT JOIN branches b ON se.branch_code = b.branch_code
                     WHERE se.invoice_no = ? AND se.status = 'voided'
                     LIMIT 1";
    
    $stmt = $conn->prepare($header_query);
    $stmt->bind_param("s", $invoice_no);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Voided sales record not found']);
        exit;
    }

    $header_row = $result->fetch_assoc();
    $stmt->close();

    // Format header data
    $date_sold = !empty($header_row['created_at']) && $header_row['created_at'] != '1970-01-01 00:00:00' 
        ? date('F d, Y', strtotime($header_row['created_at'])) 
        : 'N/A';
    
    $date_voided = !empty($header_row['voided_at']) 
        ? date('F d, Y', strtotime($header_row['voided_at'])) 
        : 'N/A';
    
    $customer_name = trim(($header_row['first_name'] ?? '') . ' ' . ($header_row['last_name'] ?? ''));
    if (empty($customer_name)) {
        $customer_name = 'N/A';
    }

    $branch_display = $header_row['branch_name'] 
        ? $header_row['branch_name'] . ' - ' . $header_row['branch_code'] 
        : $header_row['branch_code'];

    // Fetch void sale items
    $items_query = "SELECT sei.*, i.description as item_desc, i.brand
                    FROM sales_entry_items sei
                    LEFT JOIN items i ON sei.item_code = i.item_code
                    WHERE sei.sales_entry_id = ?
                    ORDER BY sei.id ASC";
    
    $stmt_items = $conn->prepare($items_query);
    $stmt_items->bind_param("i", $header_row['id']);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();

    $items = [];
    $grand_total = 0;
    
    while ($item_row = $items_result->fetch_assoc()) {
        $item_description = !empty($item_row['item_description']) 
            ? $item_row['item_description'] 
            : (!empty($item_row['item_desc']) ? $item_row['item_desc'] : 'Unknown Item');
        
        $quantity = intval($item_row['quantity']);
        $price = floatval($item_row['price']);
        $total = $quantity * $price;
        $grand_total += $total;

        $items[] = [
            'item_code' => $item_row['item_code'] ?? '',
            'item_description' => $item_description,
            'imei' => $item_row['imei'] ?? '',
            'brand' => $item_row['brand'] ?? '',
            'quantity' => $quantity,
            'price' => $price,
            'total' => $total
        ];
    }
    $stmt_items->close();

    // Prepare header data
    $header = [
        'invoice_no' => $header_row['invoice_no'],
        'date_sold' => $date_sold,
        'date_voided' => $date_voided,
        'customer_name' => $customer_name,
        'branch' => $branch_display,
        'void_reason' => $header_row['void_reason'] ?? 'No reason provided',
        'voided_by' => $header_row['voided_by'] ?? 'Unknown',
        'grand_total' => $grand_total
    ];

    echo json_encode([
        'success' => true,
        'header' => $header,
        'items' => $items
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching void sale details.'
    ]);
}

$conn->close();
?>
