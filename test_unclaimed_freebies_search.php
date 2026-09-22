<?php
require_once 'session_check.php';
include 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Unclaimed Freebies Search</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            margin-bottom: 30px;
        }
        
        .test-section {
            margin-bottom: 40px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .test-section h2 {
            color: #555;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-form input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .search-form button {
            padding: 10px 30px;
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .search-form button:hover {
            background-color: #1b5e20;
        }
        
        .result-box {
            background: white;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 100px;
        }
        
        .result-box pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 12px;
            color: #333;
        }
        
        .status-success {
            color: #2e7d32;
            font-weight: bold;
        }
        
        .status-error {
            color: #d32f2f;
            font-weight: bold;
        }
        
        .db-check {
            margin-bottom: 30px;
            padding: 15px;
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            border-radius: 4px;
        }
        
        .db-check h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .db-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .db-table th {
            background: #e0e0e0;
            padding: 10px;
            text-align: left;
            font-size: 13px;
            border: 1px solid #ccc;
        }
        
        .db-table td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 12px;
        }
        
        .btn-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #689f38;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .btn-link:hover {
            background-color: #558b2f;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Unclaimed Freebies Search</h1>
        
        <!-- Database Check -->
        <div class="db-check">
            <h3>📊 Database Status Check</h3>
            <?php
            // Check if table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'unclaimed_freebies'");
            if ($table_check && $table_check->num_rows > 0) {
                echo '<p class="status-success">✓ Table "unclaimed_freebies" exists</p>';
                
                // Count records
                $count_result = $conn->query("SELECT COUNT(*) as total FROM unclaimed_freebies");
                $total = $count_result->fetch_assoc()['total'];
                echo '<p>Total records: <strong>' . $total . '</strong></p>';
                
                // Count unclaimed
                $unclaimed_result = $conn->query("SELECT COUNT(*) as total FROM unclaimed_freebies WHERE status = 'unclaimed'");
                $unclaimed = $unclaimed_result->fetch_assoc()['total'];
                echo '<p>Unclaimed items: <strong>' . $unclaimed . '</strong></p>';
                
                // Show sample data
                if ($total > 0) {
                    echo '<h4 style="margin-top: 15px;">Sample Data:</h4>';
                    $sample = $conn->query("SELECT * FROM unclaimed_freebies ORDER BY id DESC LIMIT 5");
                    if ($sample && $sample->num_rows > 0) {
                        echo '<div style="overflow-x: auto;">';
                        echo '<table class="db-table">';
                        echo '<thead><tr>';
                        echo '<th>ID</th><th>Invoice No</th><th>Item Code</th><th>Description</th><th>Qty</th><th>Status</th><th>Branch</th>';
                        echo '</tr></thead><tbody>';
                        while ($row = $sample->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . $row['id'] . '</td>';
                            echo '<td>' . $row['invoice_number'] . '</td>';
                            echo '<td>' . $row['item_code'] . '</td>';
                            echo '<td>' . $row['item_description'] . '</td>';
                            echo '<td>' . $row['quantity'] . '</td>';
                            echo '<td>' . $row['status'] . '</td>';
                            echo '<td>' . $row['branch'] . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                        echo '</div>';
                    }
                }
            } else {
                echo '<p class="status-error">✗ Table "unclaimed_freebies" does NOT exist!</p>';
                echo '<p>Please run: <a href="setup_unclaimed_freebies_table.php" target="_blank">setup_unclaimed_freebies_table.php</a></p>';
            }
            ?>
        </div>
        
        <!-- Test Search -->
        <div class="test-section">
            <h2>🔍 Test Search Functionality</h2>
            <p style="margin-bottom: 15px; color: #666;">Enter an invoice number to search for unclaimed freebies:</p>
            
            <div class="search-form">
                <input type="text" id="testInvoiceNo" placeholder="Enter invoice number (e.g., 240115-001-00001)">
                <button onclick="testSearch()">Search</button>
            </div>
            
            <div class="result-box" id="searchResult">
                <p style="color: #999;">Results will appear here...</p>
            </div>
        </div>
        
        <!-- Direct API Test -->
        <div class="test-section">
            <h2>⚙️ Direct API Response</h2>
            <p style="margin-bottom: 15px; color: #666;">Raw JSON response from the API:</p>
            
            <div class="result-box" id="apiResult">
                <p style="color: #999;">API response will appear here...</p>
            </div>
        </div>
        
        <a href="claimitem.php" class="btn-link">→ Go to Claim Item Page</a>
    </div>
    
    <script>
        function testSearch() {
            const invoiceNo = document.getElementById('testInvoiceNo').value.trim();
            
            if (!invoiceNo) {
                alert('Please enter an invoice number');
                return;
            }
            
            // Clear results
            document.getElementById('searchResult').innerHTML = '<p style="color: #666;">Searching...</p>';
            document.getElementById('apiResult').innerHTML = '<p style="color: #666;">Loading...</p>';
            
            // Call API
            fetch('search_unclaimed_freebies.php?invoice_no=' + encodeURIComponent(invoiceNo))
                .then(response => response.json())
                .then(data => {
                    // Show formatted result
                    displayFormattedResult(data);
                    
                    // Show raw JSON
                    document.getElementById('apiResult').innerHTML = 
                        '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                })
                .catch(error => {
                    document.getElementById('searchResult').innerHTML = 
                        '<p class="status-error">Error: ' + error.message + '</p>';
                    document.getElementById('apiResult').innerHTML = 
                        '<p class="status-error">Error: ' + error.message + '</p>';
                });
        }
        
        function displayFormattedResult(data) {
            const resultDiv = document.getElementById('searchResult');
            
            if (data.status === 'success') {
                let html = '<p class="status-success">✓ Success!</p>';
                html += '<h4 style="margin-top: 15px;">Unclaimed Items (' + data.unclaimed_items.length + '):</h4>';
                html += '<ul style="margin-top: 10px; padding-left: 20px;">';
                
                data.unclaimed_items.forEach(item => {
                    html += '<li style="margin-bottom: 10px;">';
                    html += '<strong>' + item.item_description + '</strong><br>';
                    html += '<small>Code: ' + item.item_code + ' | Qty: ' + item.quantity + ' | Branch: ' + item.branch + '</small>';
                    if (item.note) {
                        html += '<br><small style="color: #888;">Note: ' + item.note + '</small>';
                    }
                    html += '</li>';
                });
                
                html += '</ul>';
                
                if (data.customer_details) {
                    html += '<h4 style="margin-top: 15px;">Customer Details:</h4>';
                    html += '<p style="margin-top: 10px;">';
                    html += '<strong>Name:</strong> ' + data.customer_details.first_name + ' ' + data.customer_details.last_name + '<br>';
                    html += '<strong>Contact:</strong> ' + data.customer_details.contact_no + '<br>';
                    html += '<strong>Email:</strong> ' + data.customer_details.email + '<br>';
                    html += '<strong>Encoder:</strong> ' + data.customer_details.encoder;
                    html += '</p>';
                }
                
                resultDiv.innerHTML = html;
            } else if (data.status === 'not_found') {
                resultDiv.innerHTML = '<p class="status-error">✗ Not Found</p><p>' + data.message + '</p>';
            } else {
                resultDiv.innerHTML = '<p class="status-error">✗ Error</p><p>' + data.message + '</p>';
            }
        }
        
        // Allow Enter key
        document.getElementById('testInvoiceNo').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                testSearch();
            }
        });
    </script>
</body>
</html>
