<?php
/**
 * Test/Demo page showing how to integrate booklet numbers into your sales/invoice system
 */
require_once 'session_check.php';
include 'config.php';
include 'get_next_invoice_number.php';

// Fetch branches for the demo
$branches_result = $conn->query("SELECT * FROM branches WHERE status = 'Active' ORDER BY branch_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booklet Number Integration Test</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2e7d32;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #666;
        }
        select, input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        button {
            padding: 10px 24px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        button:hover {
            background: #1b5e20;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            background: #e8f5e9;
            border-left: 4px solid #2e7d32;
            border-radius: 4px;
            display: none;
        }
        .result.show {
            display: block;
        }
        .invoice-number {
            font-size: 24px;
            font-weight: bold;
            color: #2e7d32;
            margin: 10px 0;
            font-family: monospace;
        }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #1976d2;
        }
        .code-example {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            overflow-x: auto;
        }
        pre {
            margin: 0;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Booklet Number Integration Test</h1>
        
        <div class="info">
            <strong>ℹ️ Information:</strong> This page demonstrates how to integrate booklet numbers into your sales or invoice system.
        </div>

        <div class="form-group">
            <label for="branch_select">Select Branch:</label>
            <select id="branch_select">
                <option value="">-- Select Branch --</option>
                <?php
                if ($branches_result && $branches_result->num_rows > 0) {
                    while ($branch = $branches_result->fetch_assoc()) {
                        echo "<option value='" . htmlspecialchars($branch['branch_code']) . "'>";
                        echo htmlspecialchars($branch['branch_name']) . " (" . htmlspecialchars($branch['branch_code']) . ")";
                        echo "</option>";
                    }
                }
                ?>
            </select>
        </div>

        <button onclick="getInvoiceNumber()">Get Next Invoice Number</button>

        <div class="result" id="result">
            <p><strong>Invoice Number:</strong></p>
            <div class="invoice-number" id="invoice_number"></div>
            <p id="result_message"></p>
        </div>

        <div class="code-example">
            <h3>PHP Integration Example:</h3>
            <pre><?php echo htmlspecialchars('
// Include the helper file
include \'get_next_invoice_number.php\';

// Get booklet config for a branch
$branch_code = \'SD\'; // Your branch code
$booklet = getBookletConfig($conn, $branch_code);

if ($booklet) {
    // Generate the full invoice number
    $invoice_number = generateInvoiceNumber($booklet);
    echo "Invoice Number: " . $invoice_number;
    
    // Use in your sales entry
    $sql = "INSERT INTO sales (invoice_number, branch_code, ...) 
            VALUES (?, ?, ...)";
    
    // After successful sale, optionally increment
    $next_number = incrementInvoiceNumber(
        $booklet[\'current_number\'], 
        $booklet[\'booklet_format\']
    );
    updateInvoiceNumber($conn, $booklet[\'id\'], $next_number);
}
'); ?></pre>
        </div>

        <div class="code-example">
            <h3>JavaScript/AJAX Example:</h3>
            <pre><?php echo htmlspecialchars('
// Get invoice number via AJAX
$.ajax({
    url: \'get_next_invoice_number.php\',
    method: \'POST\',
    data: {
        action: \'get_invoice_number\',
        branch_code: \'SD\'
    },
    success: function(response) {
        const data = JSON.parse(response);
        if (data.success) {
            console.log(\'Invoice Number:\', data.invoice_number);
            // Use the invoice number in your form
        }
    }
});
'); ?></pre>
        </div>
    </div>

    <script>
        function getInvoiceNumber() {
            const branchCode = document.getElementById('branch_select').value;
            
            if (!branchCode) {
                alert('Please select a branch');
                return;
            }

            // Make AJAX request
            fetch('get_next_invoice_number.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_invoice_number&branch_code=' + encodeURIComponent(branchCode)
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('result');
                const invoiceNumberDiv = document.getElementById('invoice_number');
                const messageDiv = document.getElementById('result_message');
                
                if (data.success) {
                    invoiceNumberDiv.textContent = data.invoice_number;
                    messageDiv.innerHTML = `
                        <small>
                            Format: <strong>${data.format}</strong><br>
                            Booklet ID: ${data.booklet_id}
                        </small>
                    `;
                    resultDiv.classList.add('show');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to fetch invoice number');
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
