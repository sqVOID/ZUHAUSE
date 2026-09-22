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
    <title>IMEI History</title>
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

        .input-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0;
        }

        .input-label {
            font-size: 13px;
            font-weight: 600;
            color: #333;
            min-width: 45px;
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

            .input-group {
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
            IMEI HISTORY
        </div>

        <div class="content print-zone" id="printZone">
            <div class="input-group">
                <label class="input-label">IMEI:</label>
                <input type="text" id="imeiInput" class="input-field" placeholder="Enter IMEI number">
                <button class="btn-print no-print" onclick="printReport()">Print</button>
            </div>


        </div>
    </div>

    <script>
        function printReport() {
            const imei = document.getElementById('imeiInput').value.trim();
            
            if (!imei) {
                alert('Please enter an IMEI number first');
                return;
            }

            // Open PDF in new window
            window.open('print_imei_history.php?imei=' + encodeURIComponent(imei), '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
        }
    </script>

</body>

</html>
