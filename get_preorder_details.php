<?php
require_once 'session_check.php';
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['preorder_no']) || empty($_GET['preorder_no'])) {
    echo json_encode(['status' => 'error', 'message' => 'Preorder number is required']);
    exit;
}

$preorder_no = trim($_GET['preorder_no']);

try {
    // First try to find preorder by invoice_no directly
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.invoice_no AS preorder_no,
            CONCAT(p.first_name, ' ', p.last_name) AS customer_name,
            p.contact_no AS contact_number,
            b.branch_name,
            p.status,
            p.created_at AS date_created,
            p.claimed_at,
            p.completed_at,
            p.total_amount,
            p.encoder AS created_by
        FROM preorders p
        LEFT JOIN branches b ON p.branch_code = b.branch_code
        WHERE p.invoice_no = ?
    ");

    $stmt->bind_param("s", $preorder_no);
    $stmt->execute();
    $result = $stmt->get_result();

    // If not found by preorder invoice_no, try to find via payment history invoice_no
    if ($result->num_rows === 0) {
        $stmt->close();

        $stmt = $conn->prepare("
            SELECT 
                p.id,
                p.invoice_no AS preorder_no,
                CONCAT(p.first_name, ' ', p.last_name) AS customer_name,
                p.contact_no AS contact_number,
                b.branch_name,
                p.status,
                p.created_at AS date_created,
                p.claimed_at,
                p.completed_at,
                p.total_amount,
                p.encoder AS created_by
            FROM preorders p
            LEFT JOIN branches b ON p.branch_code = b.branch_code
            INNER JOIN preorder_payment_history ph ON p.id = ph.preorder_id
            WHERE ph.invoice_no = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $preorder_no);
        $stmt->execute();
        $result = $stmt->get_result();
    }

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Preorder not found']);
        exit;
    }

    $preorder = $result->fetch_assoc();
    $preorder_id = $preorder['id'];
    $stmt->close();

    // Fetch preorder items
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(NULLIF(item_description, ''), family_code) AS item_description,
            imei,
            quantity,
            price AS unit_price,
            (quantity * price) AS total_amount
        FROM preorder_items
        WHERE preorder_id = ?
        ORDER BY id
    ");

    $stmt->bind_param("i", $preorder_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    // Fetch payment history
    $stmt = $conn->prepare("
        SELECT 
            invoice_no,
            payment_date,
            amount AS amount_paid,
            encoder AS received_by,
            payment_method,
            payment_sequence,
            status_after_payment,
            status_after_payment AS status
        FROM preorder_payment_history
        WHERE preorder_id = ?
        ORDER BY payment_sequence ASC
    ");

    $stmt->bind_param("i", $preorder_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt->close();

    // Return all data
    echo json_encode([
        'status' => 'success',
        'success' => true,
        'preorder' => $preorder,
        'items' => $items,
        'payments' => $payments
    ]);

} catch (Exception $e) {
    error_log("Error fetching preorder details: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>