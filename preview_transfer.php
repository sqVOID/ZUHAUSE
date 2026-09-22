<?php
require_once 'session_check.php';
include 'config.php';

$st_number = isset($_GET['st_number']) ? $_GET['st_number'] : '';

if (empty($st_number)) {
    die('Invalid transfer number');
}

// Get transfer header
$sql = "SELECT 
    st.*,
    b1.branch_name as branch_from_name,
    b2.branch_name as branch_to_name
FROM stock_transfers st
LEFT JOIN branches b1 ON st.branch_from = b1.branch_code
LEFT JOIN branches b2 ON st.branch_to = b2.branch_code
WHERE st.st_number = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $st_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Transfer not found');
}

$transfer = $result->fetch_assoc();

// Get transfer items
$sql_items = "SELECT * FROM stock_transfer_items WHERE st_number = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param('s', $st_number);
$stmt_items->execute();
$items_result = $stmt_items->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Preview - <?php echo htmlspecialchars($st_number); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .preview-container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { font-size: 24px; margin-bottom: 20px; color: #333; text-align: center; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; }
        .info-item { display: flex; flex-direction: column; }
        .info-item label { font-size: 12px; color: #666; margin-bottom: 5px; font-weight: 600; }
        .info-item span { font-size: 14px; color: #333; padding: 8px; background: #f9f9f9; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #e1ffde; padding: 12px; font-size: 13px; font-weight: 600; color: #000; border: 1px solid #ccc; text-align: center; }
        td { padding: 10px; font-size: 13px; color: #333; border: 1px solid #ccc; text-align: center; }
        .btn-close { background: #424242; color: white; border: none; padding: 10px 30px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn-close:hover { background: #212121; }
        .btn-print { background: #1976D2; color: white; border: none; padding: 10px 30px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; margin-left: 10px; }
        .btn-print:hover { background: #1565C0; }
        .actions { text-align: center; margin-top: 20px; }
        .status-badge { padding: 6px 16px; border-radius: 12px; font-size: 13px; font-weight: 600; display: inline-block; }
        .status-badge.pending { background: #fff3e0; color: #e65100; }
        .status-badge.approved { background: #e8f5e9; color: #2e7d32; }
        .status-badge.disapproved { background: #ffebee; color: #c62828; }
        @media print {
            .actions { display: none; }
            body { background: white; }
            .preview-container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <h1>Stock Transfer Preview</h1>
        
        <div class="info-grid">
            <div class="info-item">
                <label>ST Number:</label>
                <span><?php echo htmlspecialchars($transfer['st_number']); ?></span>
            </div>
            <div class="info-item">
                <label>Date:</label>
                <span><?php echo date('m/d/Y', strtotime($transfer['st_date'])); ?></span>
            </div>
            <div class="info-item">
                <label>Branch From:</label>
                <span><?php echo htmlspecialchars($transfer['branch_from_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-item">
                <label>Branch To:</label>
                <span><?php echo htmlspecialchars($transfer['branch_to_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-item">
                <label>Store Name:</label>
                <span><?php echo htmlspecialchars($transfer['store_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-item">
                <label>Prepared By:</label>
                <span><?php echo htmlspecialchars($transfer['prepared_by'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-item">
                <label>Approver:</label>
                <span><?php echo htmlspecialchars($transfer['approver'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-item">
                <label>Status:</label>
                <span>
                    <?php
                    $status = $transfer['status'] ?? 'Pending';
                    $statusClass = strtolower($status);
                    echo "<span class='status-badge $statusClass'>" . htmlspecialchars($status) . "</span>";
                    ?>
                </span>
            </div>
            <div class="info-item" style="grid-column: 1 / -1;">
                <label>Remarks:</label>
                <span><?php echo htmlspecialchars($transfer['remarks'] ?? 'N/A'); ?></span>
            </div>
        </div>

        <h3 style="margin-bottom: 15px; font-size: 16px;">Transfer Items</h3>
        <table>
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>IMEI</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $total_qty = 0;
                if ($items_result->num_rows > 0) {
                    while ($item = $items_result->fetch_assoc()) {
                        $total_qty += $item['quantity'];
                        echo "<tr>";
                        echo "<td style='text-align:left;'>" . htmlspecialchars($item['item_description']) . "</td>";
                        echo "<td>" . htmlspecialchars($item['imei'] ?? '') . "</td>";
                        echo "<td>" . htmlspecialchars($item['quantity']) . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='3' style='text-align:center;'>No items found</td></tr>";
                }
                ?>
                <tr style="font-weight: 600; background: #f5f5f5;">
                    <td colspan="2" style="text-align:right;">Total Quantity:</td>
                    <td><?php echo $total_qty; ?></td>
                </tr>
            </tbody>
        </table>

        <div class="actions">
            <button class="btn-close" onclick="window.close()">Close</button>
            <button class="btn-print" onclick="window.print()">Print</button>
        </div>
    </div>
</body>
</html>
