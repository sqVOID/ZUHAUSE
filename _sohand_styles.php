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

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #f0f0f0ff; zoom: 77%; }

        .header {
            position: fixed; top: 0; left: 0; right: 0; height: 60px;
            background-color: white; display: flex; justify-content: flex-start;
            align-items: center; padding: 0 20px; z-index: 1000; gap: 30px;
        }
        .header::after {
            content: ''; position: absolute; bottom: 0; left: 250px; right: 0;
            height: 1px; box-shadow: 0 2px 4px rgba(0,0,0,0.5); pointer-events: none;
        }
        .logo { margin-left: -20px; height: 50px; }

        .menu-btn { width: 24px; height: 22px; cursor: pointer; position: relative;
            display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .menu-btn span { display: block; width: 18px; height: 2px; background: #333;
            position: absolute; transition: all 0.3s ease; }
        .menu-btn span:nth-child(1) { top: 0; }
        .menu-btn span:nth-child(2) { top: 50%; transform: translateY(-50%); }
        .menu-btn span:nth-child(3) { bottom: 0; }
        .menu-btn.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); }
        .menu-btn.active span:nth-child(2) { opacity: 0; }
        .menu-btn.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }

        .sidebar {
            position: fixed; left: 0; top: 60px; width: 250px;
            height: calc(149.3vh - 60px); background: white;
            box-shadow: 2px 0 4px rgba(0,0,0,0.1); transition: transform 0.3s ease;
            overflow-y: auto; padding: 20px 0;
        }
        .sidebar.hidden { transform: translateX(-100%); }

        .menu-item {
            padding: 12px 20px; display: flex; align-items: center; gap: 12px;
            color: #666; text-decoration: none; cursor: pointer;
            transition: background-color 0.2s; font-size: 14px;
        }
        .menu-item:hover { background-color: #f5f5f5; }
        .menu-item.active { background-color: var(--color-gold-pale); color: var(--color-navy); font-weight: bold; }
        .menu-item svg { width: 20px; height: 20px; fill: currentColor; }

        .menu-section { margin-bottom: 5px; }
        .menu-section-title {
            padding: 12px 20px; display: flex; align-items: center;
            justify-content: space-between; gap: 12px; color: #666;
            cursor: pointer; font-size: 14px; font-weight: 500;
        }
        .menu-section-title svg { width: 20px; height: 20px; fill: currentColor; }
        .menu-section-title .arrow { transition: transform 0.3s ease; }
        .menu-section.collapsed .arrow { transform: rotate(-90deg); }
        .submenu { padding-left: 20px; max-height: 500px; overflow: hidden; transition: max-height 0.3s ease; }
        .menu-section.collapsed .submenu { max-height: 0; }
        .submenu .menu-item { padding: 10px 20px; font-size: 13px; }

        .main-content { margin-left: 250px; margin-top: 60px; padding: 20px; transition: margin-left 0.3s ease; }
        .main-content.expanded { margin-left: 0; }

        .content-header { display: flex; flex-direction: column; width: 100%; gap: 15px; margin-bottom: 20px; }
        .content-header h2 { font-size: 20px; font-weight: 600; color: #000; margin: 0; }

        .action-buttons { display: flex; gap: 10px; }
        .btn-print {
            display: flex; align-items: center; gap: 8px; padding: 8px 16px;
            border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer;
            background-color: var(--color-navy); color: white; border: 1px solid var(--color-navy);
            transition: background-color 0.2s ease;
        }
        .btn-print:hover { background-color: var(--color-navy-dark); }
        .btn-print svg { width: 16px; height: 16px; fill: none; stroke: currentColor;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

        .button-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-summary {
            padding: 10px 20px; background-color: var(--color-gold); color: white;
            border: none; border-radius: 4px; font-size: 14px; font-weight: 500;
            cursor: pointer; text-decoration: none; display: inline-block;
            transition: background-color 0.2s ease;
        }
        .btn-summary:hover { background-color: var(--color-gold-light); }
        .btn-summary.active { background-color: var(--color-navy); }

        /* Filter Bar */
        .filter-bar {
            background: white; border: 1px solid #ddd; border-radius: 4px;
            padding: 10px; display: flex; align-items: center;
            justify-content: space-between; margin-bottom: 20px;
            flex-wrap: wrap; gap: 10px;
        }
        .search-group { display: flex; align-items: center; }
        .search-input-wrapper { position: relative; display: flex; align-items: center; }
        .search-input-wrapper input {
            padding: 8px 10px 8px 35px; border: 1px solid #ddd;
            border-right: none; border-radius: 4px 0 0 4px;
            font-size: 14px; width: 250px; outline: none;
        }
        .search-input-wrapper svg { position: absolute; left: 10px; width: 16px; height: 16px; fill: #999; }
        .btn-search {
            background: var(--color-navy); color: white; border: none;
            padding: 9px 20px; border-radius: 0 4px 4px 0;
            cursor: pointer; font-size: 14px; font-weight: 600;
            transition: background-color 0.2s ease;
        }
        .btn-search:hover { background-color: var(--color-navy-dark); }
        .filters-right { display: flex; gap: 10px; align-items: center; }
        .filter-select {
            padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;
            background: white; font-size: 14px; color: #666;
            cursor: pointer; min-width: 130px; outline: none;
        }
        .filter-date {
            padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;
            background: white; font-size: 14px; color: #666; cursor: pointer; outline: none;
        }

        .table-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table-container h3 { font-size: 16px; font-weight: 600; color: #333; margin: 0 0 20px 0; }

        table { width: 100%; border-collapse: collapse; }
        thead { background: var(--color-gold-pale); }
        th {
            text-align: center; padding: 12px; font-size: 13px; font-weight: 600;
            color: #000; border-top: 1px solid #ccc; border-bottom: 1px solid #ccc;
        }
        th:first-child { border-left: 1px solid #ccc; }
        th:last-child  { border-right: 1px solid #ccc; }
        td {
            padding: 12px; font-size: 13px; color: #333;
            border-bottom: 1px solid #ccc; text-align: center !important;
        }
        td:first-child { border-left: 1px solid #ccc; text-align: left !important; }
        td:last-child  { border-right: 1px solid #ccc; }
        tbody tr:hover { background: #fdf8f3; }

        .no-data { text-align: center; padding: 20px; color: #666; font-weight: 500; font-size: 14px; }

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

            .header::after { left: 0; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .search-input-wrapper input { width: 100%; }
            .filters-right { flex-direction: column; }
        }

            /* Print Styles */
        @media print {
            body { zoom: 100%; background: white; }
            
            /* Hide UI elements */
            .header, .sidebar, .menu-btn, .button-group, 
            form, .btn-print, .filter-bar, .action-buttons,
            .content-header > div:first-child { display: none !important; }
            
            /* Show print header */
            .print-header { display: block !important; }
            
            /* Reset main content */
            .main-content { margin: 0; padding: 20px; }
            
            /* Print header */
            .table-container h3 {
                text-align: center;
                font-size: 20px;
                font-weight: bold;
                text-transform: uppercase;
                margin-bottom: 30px;
                color: #000;
            }
            
            /* Table styling for print */
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
            
            th, td { 
                padding: 8px;
                font-size: 11px;
                border: 1px solid #000 !important;
            }
            
            thead { background: var(--color-gold-pale) !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            
            /* Remove hover effects */
            tbody tr:hover { background: transparent !important; }
            
            /* Ensure table fits on page */
            .table-container { 
                box-shadow: none;
                padding: 0;
                background: white;
            }
        }
    </style>
