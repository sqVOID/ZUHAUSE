<?php
require_once 'session_check.php';
include 'config.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
      <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Receive Stock Transfer</title>
    <style>
        :root {
            /* Brand Colors - Navy & Gold Theme */
            --color-navy: #0d3347;
            --color-navy-dark: #081f2d;
            --color-navy-light: #164460;
            --color-gold: #b08a52;
            --color-gold-light: #c9a46e;
            --color-gold-pale: #f5ede0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color:#f0f0f0ff;
            zoom: 77%;
        }

        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background-color: white;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            padding: 0 20px;

            z-index: 1000;
            gap: 30px;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 250px;
            right: 0;
            height: 1px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
            pointer-events: none;
        }

        .logo {
            height: 50px;
            margin-left: -20px;
        }

        .menu-btn {
            width: 24px;
            height: 22px;
            cursor: pointer;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .menu-btn span {
            display: block;
            width: 18px;
            height: 2px;
            background-color: #333;
            position: absolute;
            transition: all 0.3s ease;
        }

        .menu-btn span:nth-child(1) {
            top: 0;
        }

        .menu-btn span:nth-child(2) {
            top: 50%;
            transform: translateY(-50%);
        }

        .menu-btn span:nth-child(3) {
            bottom: 0;
        }

        .menu-btn.active span:nth-child(1) {
            top: 50%;
            transform: translateY(-50%) rotate(45deg);
        }

        .menu-btn.active span:nth-child(2) {
            opacity: 0;
        }

        .menu-btn.active span:nth-child(3) {
            bottom: 50%;
            transform: translateY(50%) rotate(-45deg);
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 60px;
            width: 250px;
            height: calc(149.3vh - 60px);
            background-color: white;
            box-shadow: 2px 0 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            overflow-y: auto;
            padding: 20px 0;
        }

        .sidebar.hidden {
            transform: translateX(-100%);
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #666;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.2s;
            font-size: 14px;
        }

        .menu-item:hover {
            background-color: #f5f5f5;
        }

        .menu-item.active {
            background-color: var(--color-gold-pale);
            color: var(--color-navy);
            font-weight: bold;
        }

        .menu-item svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .menu-section {
            margin-bottom: 5px;
        }

        .menu-section-title {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            color: #666;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .menu-section-title svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .menu-section-title .arrow {
            transition: transform 0.3s ease;
        }

        .menu-section.collapsed .arrow {
            transform: rotate(-90deg);
        }

        .submenu {
            padding-left: 20px;
            max-height: 500px;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .menu-section.collapsed .submenu {
            max-height: 0;
        }

        .submenu .menu-item {
            padding: 10px 20px;
            font-size: 13px;
        }

        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .content-header {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 30px;
        }

        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .filter-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 180px;
            max-width: 200px;
        }

        .filter-group label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: white;
        }

        .btn-search {
            background-color: var(--color-navy);
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            height: 40px;
            transition: all 0.3s ease;
        }

        .btn-search:hover {
            background-color: var(--color-navy-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(13, 51, 71, 0.3);
        }

        .table-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;

        }

        thead {
            background: var(--color-gold-pale);
        }

        th {
            text-align: center;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #000000;
            border-top: 1px solid #ccc;
            border-bottom: 1px solid #ccc;
        }

        th:first-child {
            border-left: 1px solid #ccc;
        }

        th:last-child {
            border-right: 1px solid #ccc;
        }

        td {
            padding: 12px;
            font-size: 13px;
            color: #333;
            border-bottom: 1px solid #ccc;
            text-align: center !important;
        }

        td:first-child {
            border-left: 1px solid #ccc;
        }

        td:last-child {
            border-right: 1px solid #ccc;
        }

        tbody tr:hover {
            background: #fdf8f3;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.pending {
            background: #fff3e0;
            color: #e65100;
        }

        .status-badge.approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.disapproved {
            background: #ffebee;
            color: #c62828;
        }

        .status-badge.received {
            background: #e3f2fd;
            color: #1565c0;
        }

        .btn-preview {
            padding: 6px 14px;
            background-color: #1976D2;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-preview:hover {
            background-color: #1565C0;
        }

        .btn-approve {
            padding: 6px 14px;
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-approve:hover {
            background-color: #1b5e20;
        }

        .btn-disapprove {
            padding: 6px 14px;
            background-color: #c62828;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-disapprove:hover {
            background-color: #b71c1c;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1500;
            }

            .sidebar.hidden {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .header {
                padding: 0 15px;
                gap: 15px;
            }

            .logo {
                height: 40px;
            }

            .table-container {
                padding: 20px;
                overflow-x: auto;
            }

            table {
                min-width: 600px;
            }

            .content-header h2 {
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {
            .header {
                height: 50px;
                padding: 0 10px;
                gap: 10px;
            }

            .logo {
                height: 35px;
            }

            .sidebar {
                top: 50px;
                height: calc(100vh - 50px);
                width: 220px;
            }

            .main-content {
                margin-top: 50px;
                padding: 15px;
            }

            .table-container {
                padding: 15px;
            }

            th,
            td {
                padding: 8px;
                font-size: 12px;
            }

            .content-header {
                margin-bottom: 20px;
            }

            .content-header h2 {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <!-- <img src="Icon/motogam_logo.jpg" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Receive Stock Transfer</h2>
        </div>

        <div class="filter-container">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" id="date_from">
                </div>
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" id="date_to">
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select id="status_filter">
                        <option value="">Select Status</option>
                        <option value="all">All Status</option>
                        <option value="Approved">Approved</option>
                        <option value="Disapproved">Disapproved</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-search" onclick="searchTransfers()">SEARCH</button>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ST NO</th>
                        <th>DATE</th>
                        <th>BRANCH FROM</th>
                        <th>BRANCH TO</th>
                        <th>PREPARED BY</th>
                        <th>APPROVED BY</th>
                        <th style="display: none;">RECEIVED BY</th>
                        <th>STATUS</th>
                        <th>REMARKS</th>
                        <th>VIEW</th>
                        <th>DISAPPROVED</th>
                        <th>RECEIVE</th>
                    </tr>
                </thead>
                <tbody id="receiveTableBody">
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 30px 20px;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                <div style="color: #333; font-size: 15px; font-weight: 600;">SELECT A STATUS FILTER TO DISPLAY THE DATA</div>
                                <div style="color: #666; font-size: 13px;">Please select a status filter from the dropdown above to view transfers.</div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.menu-btn')?.classList.toggle('active');
            document.querySelector('.sidebar')?.classList.toggle('hidden');
            document.querySelector('.main-content')?.classList.toggle('expanded');
        }

        function toggleSection(element) {
            const section = element.parentElement;
            const isCurrentlyCollapsed = section.classList.contains('collapsed');
            
            // Close all other sections (accordion behavior)
            const allSections = document.querySelectorAll('.menu-section');
            allSections.forEach(function(s) {
                if (s !== section) {
                    s.classList.add('collapsed');
                }
            });
            
            // Toggle the clicked section
            if (isCurrentlyCollapsed) {
                section.classList.remove('collapsed');
            } else {
                section.classList.add('collapsed');
            }
            
            // Save the sidebar state to persist across navigation
            if (typeof saveSidebarState === 'function') {
                saveSidebarState();
            }
        }

        function searchTransfers() { loadTransfers(); }

        function loadTransfers() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const status = document.getElementById('status_filter').value;

            // Check if status filter is selected
            if (!status || status === '') {
                // Show message to select a filter
                const tbody = document.getElementById('receiveTableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 30px 20px;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                <div style="color: #333; font-size: 15px; font-weight: 600;">SELECT A STATUS FILTER TO DISPLAY THE DATA</div>
                                <div style="color: #666; font-size: 13px;">Please select a status filter from the dropdown above to view transfers.</div>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            // We load transfers based on the selected status filter
            fetch('get_receive_transfers.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}&status=${encodeURIComponent(status)}`
            })
                .then(response => response.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (Array.isArray(data)) {
                            renderTable(data);
                        } else if (data && data.error) {
                            document.getElementById('receiveTableBody').innerHTML =
                                `<tr><td colspan="12" style="text-align:center; padding:20px; color:#c62828;">Error: ${data.error}</td></tr>`;
                        } else {
                            renderTable([]);
                        }
                    } catch (e) {
                        document.getElementById('receiveTableBody').innerHTML =
                            '<tr><td colspan="12" style="text-align:center; padding:20px; color:#c62828;">Error: Invalid response from server.</td></tr>';
                    }
                })
                .catch(() => {
                    document.getElementById('receiveTableBody').innerHTML =
                        '<tr><td colspan="12" style="text-align:center; padding:20px; color:#c62828;">Error loading transfers</td></tr>';
                });
        }

        function renderTable(transfers) {
            const tbody = document.getElementById('receiveTableBody');

            if (!transfers || transfers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" style="text-align:center; padding:20px;">No transfers found</td></tr>';
                return;
            }

            tbody.innerHTML = '';
            transfers.forEach(transfer => {
                const statusClass = (transfer.status || '').toLowerCase();
                const statusBadge = `<span class="status-badge ${statusClass}">${transfer.status}</span>`;

                const canReceive = transfer.status === 'Approved';
                const receiveBtn = canReceive
                    ? `<button class="btn-approve" onclick="receiveTransfer('${transfer.st_number}')">Receive</button>`
                    : '-';

                const canDisapprove = transfer.status === 'Approved';
                const disapproveBtn = canDisapprove
                    ? `<button class="btn-disapprove" onclick="disapproveTransfer('${transfer.st_number}')">Disapprove</button>`
                    : '-';

                const receivedBy = transfer.received_by || '-';
                const approver = transfer.approver || '-';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${transfer.st_number}</td>
                    <td>${transfer.date}</td>
                    <td>${transfer.branch_from}</td>
                    <td>${transfer.branch_to}</td>
                    <td>${transfer.prepared_by}</td>
                    <td>${approver}</td>
                    <td style="display: none;">${receivedBy}</td>
                    <td>${statusBadge}</td>
                    <td>${transfer.remarks || '-'}</td>
                    <td><button class="btn-preview" onclick="printTransfer('${transfer.st_number}')">Preview</button></td>
                    <td>${disapproveBtn}</td>
                    <td>${receiveBtn}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function receiveTransfer(stNumber) {
            if (!confirm('Receive this stock transfer and move items into stock on hand?')) return;

            fetch('update_transfer_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `st_number=${encodeURIComponent(stNumber)}&status=${encodeURIComponent('Received')}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Transfer received successfully!');
                        loadTransfers();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to receive transfer'));
                    }
                })
                .catch(() => alert('Error receiving transfer'));
        }

        function disapproveTransfer(stNumber) {
            if (!confirm('Disapprove this stock transfer? This action cannot be undone.')) return;

            fetch('update_transfer_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `st_number=${encodeURIComponent(stNumber)}&status=${encodeURIComponent('Disapproved')}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Transfer disapproved successfully!');
                        loadTransfers();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to disapprove transfer'));
                    }
                })
                .catch(() => alert('Error disapproving transfer'));
        }

        function printTransfer(stNumber) {
            // Open PDF preview in new window (consistent with other preview functions)
            window.open('preview_stock_transfer.php?st_number=' + encodeURIComponent(stNumber), '_blank', 'width=900,height=700');
        }

        // Auto-load on page open (filters are empty by default).
        document.addEventListener('DOMContentLoaded', () => {
            // Don't automatically load - require status filter selection first
        });
    </script>
</body>

</html>