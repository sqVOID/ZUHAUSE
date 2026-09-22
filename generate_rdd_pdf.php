<?php
require_once 'session_check.php';
require_once 'config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Record ID.");
}

$id = intval($_GET['id']);

// Fetch header
$stmt = $conn->prepare("SELECT r.*, b.branch_name FROM rddeliveries r LEFT JOIN branches b ON r.branch_code = b.branch_code WHERE r.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Record not found.");
}

$header = $res->fetch_assoc();
$stmt->close();

// Fetch items
$items = [];
$po_number = isset($header['po_number']) ? $header['po_number'] : '';
$stmt_items = $conn->prepare("
    SELECT ri.*, 
        COALESCE(
            (SELECT poi.cost FROM purchase_order_items poi WHERE poi.po_number = ? AND poi.item_model = ri.item_code LIMIT 1),
            (SELECT srp FROM items WHERE items.item_code = ri.item_code LIMIT 1),
            0
        ) as actual_cost 
    FROM rddelivery_items ri 
    WHERE ri.rddelivery_id = ?
");
$stmt_items->bind_param("si", $po_number, $id);
$stmt_items->execute();
$res_items = $stmt_items->get_result();
while ($row = $res_items->fetch_assoc()) {
    if (isset($row['serials']) && !empty(trim($row['serials']))) {
        $sn = trim($row['serials']);
        $sn_normalized = str_replace(["\r\n", "\n", "\r", "<br>", "<br/>", "<br />", "&"], ",", $sn);
        $sn_arr = array_filter(array_map('trim', explode(',', $sn_normalized)));
        if (count($sn_arr) > 0) {
            $row['received_quantity'] = count($sn_arr);
        }
    }
    $items[] = $row;
}
$stmt_items->close();

$branch_text = ($header['branch_name'] ? $header['branch_name'] : 'Unknown') . ' - ' . ($header['branch_code'] ? $header['branch_code'] : 'UNK');
$invoice_date_txt = (!empty($header['invoice_date']) && $header['invoice_date'] != '1970-01-01') ? date('F d, Y', strtotime($header['invoice_date'])) : '';
$encoded_date_txt = (!empty($header['created_at'])) ? date('F d, Y H:i:s', strtotime($header['created_at'])) : '';

$total_qty = 0;
$overall_total_amt = 0;

// Calculate totals
foreach ($items as $row) {
    $rcv_qty = floatval($row['received_quantity']);
    $total_qty += $rcv_qty;
    $amt = floatval($row['actual_cost']);
    $tot_amt = $amt * $rcv_qty;
    $overall_total_amt += $tot_amt;
}

// Generate HTML for PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Courier New', monospace; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .logo { height: 60px; }
        .title { font-size: 20px; font-weight: bold; letter-spacing: 2px; margin: 10px 0; }
        .meta { margin: 20px 0; font-size: 12px; }
        .meta-row { display: flex; justify-content: space-between; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 11px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: center; }
        th { background: #f0f0f0; font-weight: bold; }
        .left { text-align: left; }
        .right { text-align: right; }
        .summary { margin: 20px 0; font-size: 13px; font-weight: bold; }
        .summary div { display: flex; justify-content: space-between; max-width: 400px; margin: 5px 0; }
        .red { color: #d32f2f; }
        .footer { text-align: right; margin-top: 30px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">RECEIVE DIRECT DELIVERY</div>
    </div>
    
    <div class="meta">
        <div class="meta-row">
            <span><strong>INVOICE NO:</strong> <?php echo htmlspecialchars($header['invoice_number']); ?></span>
            <span><strong>BRANCH RECEIVED:</strong> <?php echo htmlspecialchars($branch_text); ?></span>
        </div>
        <div class="meta-row">
            <span><strong>P.O NO:</strong> <?php echo htmlspecialchars($header['po_number']); ?></span>
            <span><strong>SUPPLIER:</strong> <?php echo htmlspecialchars($header['supplier']); ?></span>
        </div>
        <div class="meta-row">
            <span><strong>INVOICE DATE:</strong> <?php echo $invoice_date_txt; ?></span>
            <span><strong>RECEIVED BY:</strong> <?php echo htmlspecialchars($header['received_by']); ?></span>
        </div>
        <div class="meta-row">
            <span><strong>INVOICE ENCODED:</strong> <?php echo $encoded_date_txt; ?></span>
            <span><strong>NOTE:</strong> <?php echo isset($header['notes']) ? htmlspecialchars($header['notes']) : ''; ?></span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>QUANTITY</th>
                <th>MODEL CODE</th>
                <th>ITEM DESCRIPTION</th>
                <th>IMEI</th>
                <th>AMOUNT</th>
                <th>TOTAL AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            <?php
if (count($items) === 0) {
    echo '<tr><td colspan="6">No Items</td></tr>';
}
else {
    foreach ($items as $row) {
        $rcv_qty = floatval($row['received_quantity']);
        $amt = floatval($row['actual_cost']);
        $tot_amt = $amt * $rcv_qty;
        $imei = $row['serials'] ? str_replace(', ', ', ', $row['serials']) : '';

        echo "<tr>";
        echo "<td>" . htmlspecialchars($rcv_qty) . "</td>";
        echo "<td class='left'>" . htmlspecialchars($row['item_code']) . "</td>";
        echo "<td class='left'>" . htmlspecialchars($row['item_description']) . "</td>";
        echo "<td style='font-size:9px;'>" . htmlspecialchars($imei) . "</td>";
        echo "<td class='right'>" . number_format($amt, 2) . "</td>";
        echo "<td class='right'>" . number_format($tot_amt, 2) . "</td>";
        echo "</tr>";
    }
}
?>
        </tbody>
    </table>

    <div class="summary">
        <div class="red">
            <span>TOTAL QUANTITY:</span>
            <span><?php echo $total_qty; ?></span>
        </div>
        <div class="red">
            <span>TOTAL AMOUNT:</span>
            <span><?php echo number_format($overall_total_amt, 2); ?></span>
        </div>
    </div>

    <div class="footer">PAGE 1 OF 1</div>
</body>
</html>
<?php
$html = ob_get_clean();

// Try to use DomPDF if available via composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';

    if (class_exists('Dompdf\Dompdf')) {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream("RDD_" . $header['invoice_number'] . ".pdf", array("Attachment" => false));
        exit;
    }
}

// Fallback: Output HTML and let browser handle PDF conversion
echo $html;
?>
<script>
window.onload = function() {
    window.print();
};
</script>
