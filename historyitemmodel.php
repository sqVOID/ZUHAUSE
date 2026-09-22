<?php
require_once 'session_check.php';
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/motogam_logo.jpg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item Model History</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0ff;
            margin: 0;
            padding: 0;
        }

        .container {
            background: white;
            width: 100%;
            height: 100vh;
            padding: 0;
            overflow: hidden;
        }

        .header {
            background: #1a1a1a;
            color: white;
            padding: 10px 12px;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .content {
            padding: 16px 20px;
            background: white;
        }

        .input-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .input-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .input-label {
            font-size: 13px;
            font-weight: 600;
            color: #333;
            min-width: 70px;
        }

        .input-field {
            flex: 1;
            height: 30px;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 0 10px;
            font-size: 13px;
            color: #333;
            background: #fff;
            outline: none;
            transition: border-color 0.2s;
        }

        .input-field:focus {
            border-color: #0e7725;
        }

        .btn-print {
            height: 30px;
            padding: 0 18px;
            background: #000000ff;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-print:hover {
            background: #616161ff;
        }

        .btn-print:active {
            transform: scale(0.98);
        }

        /* Results area */
        .results-wrapper {
            margin-top: 32px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            display: none;
        }

        .results-wrapper.visible {
            display: block;
        }

        .results-header {
            background: #f8f8f8;
            border-bottom: 1px solid #ddd;
            padding: 14px 20px;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 2px;
            color: #1a1a1a;
            text-transform: uppercase;
        }

        .results-content {
            padding: 24px;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .history-table th {
            border: 1px solid #000000;
            letter-spacing: 1.5px;
            padding: 10px 12px;
            text-align: center;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            background: #f4f4f4;
            white-space: nowrap;
            color: #000000;
        }

        .history-table td {
            border: 1px solid #000000;
            letter-spacing: 1px;
            padding: 10px 12px;
            text-align: center;
            font-size: 13px;
            white-space: nowrap;
            vertical-align: middle;
            color: #000000;
        }

        .history-table td.td-no-data {
            text-align: center;
            color: #666;
            font-size: 14px;
            padding: 20px;
            font-style: italic;
        }

        .history-table td.td-text-left {
            text-align: left;
        }

        /* Print styles */
        @media print {
            @page {
                margin: 0.5in;
                size: A4;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body {
                background: white !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            body * {
                visibility: hidden !important;
            }

            .print-zone,
            .print-zone * {
                visibility: visible !important;
            }

            .print-zone {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                padding: 20px !important;
                margin: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .header {
                background: #1a1a1a !important;
                color: white !important;
                padding: 20px !important;
                text-align: center !important;
                font-size: 24px !important;
                font-weight: 700 !important;
                letter-spacing: 3px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .content {
                padding: 20px 0 !important;
            }

            .input-row {
                margin-bottom: 20px !important;
            }

            .input-label {
                font-size: 16px !important;
                font-weight: 600 !important;
            }

            .input-field {
                border: 2px solid #000 !important;
                padding: 8px 12px !important;
                font-size: 14px !important;
            }

            .history-table {
                border: 2px solid #000000 !important;
            }

            .history-table th {
                border: 2px solid #000000 !important;
                background: #f0f0f0 !important;
                color: #000000 !important;
                font-weight: bold !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .history-table td {
                border: 2px solid #000000 !important;
                color: #000000 !important;
                font-weight: bold !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="header">
            ITEM MODEL HISTORY
        </div>

        <div class="content print-zone" id="printZone">
            <div class="input-row">
                <div class="input-group">
                    <label class="input-label">Date From:</label>
                    <input type="date" id="dateFromInput" class="input-field">
                </div>
                <div class="input-group">
                    <label class="input-label">Date To:</label>
                    <input type="date" id="dateToInput" class="input-field">
                </div>
            </div>
            
            <div class="input-row">
                <div class="input-group">
                    <label class="input-label">Branch:</label>
                    <select id="branchInput" class="input-field">
                        <option value="">All Branches</option>
                        <?php
                        $branch_query = "SELECT branch_code, branch_name FROM branches ORDER BY branch_name";
                        $branch_result = $conn->query($branch_query);
                        if ($branch_result && $branch_result->num_rows > 0) {
                            while ($branch = $branch_result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($branch['branch_code']) . '">' . 
                                     htmlspecialchars($branch['branch_name']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="input-group">
                    <label class="input-label">Model Code:</label>
                    <input type="text" id="modelCodeInput" class="input-field" placeholder="Enter item model">
                    <button class="btn-print no-print" onclick="printReport()">Print</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function printReport() {
            const dateFrom = document.getElementById('dateFromInput').value;
            const dateTo = document.getElementById('dateToInput').value;
            const branch = document.getElementById('branchInput').value;
            const modelCode = document.getElementById('modelCodeInput').value.trim();
            
            if (!dateFrom || !dateTo) {
                alert('Please select date range');
                return;
            }

            if (!modelCode) {
                alert('Please enter a model code first');
                return;
            }

            // Open PDF in new window
            const params = new URLSearchParams({
                date_from: dateFrom,
                date_to: dateTo,
                branch: branch,
                model_code: modelCode
            });

            window.open('print_item_model_history.php?' + params.toString(), '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
        }

        // Allow Enter key to trigger print
        document.getElementById('modelCodeInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                printReport();
            }
        });
    </script>

</body>

</html>
