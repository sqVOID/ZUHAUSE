<?php require_once 'session_check.php'; ?>
<?php require_once 'config.php'; ?>
<?php

// ── Branch Access Control ─────────────────────────────────────────────
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$session_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';

// Only Super-Admin has access to ALL branches
// Sub-admin should be restricted to their assigned branches
$has_full_access = in_array(strtolower($system_level), ['super-admin', 'superadmin']);

// Always initialize filter vars so the form renders cleanly
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_f = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_f = isset($_GET['date']) ? trim($_GET['date']) : '';
$branch_f = isset($_GET['branch']) ? trim($_GET['branch']) : '';
$model_f = isset($_GET['model']) ? trim($_GET['model']) : '';
$brand_f = isset($_GET['brand']) ? trim($_GET['brand']) : '';
$family_f = isset($_GET['family']) ? trim($_GET['family']) : '';

// Build branch IN(...) clause for filtering dropdowns and queries
$branch_in = '';
if (!$has_full_access && $session_branch !== '' && strtoupper($session_branch) !== 'SUPERADMIN') {
    $raw_branches = array_map('trim', explode(',', $session_branch));
    $escaped_branches = array_map(fn($b) => "'" . addslashes($b) . "'", $raw_branches);
    $branch_in = implode(',', $escaped_branches);
}

$res = null;
$show_initial_message = true; // Flag to show "Select Branch first" message

if ($has_full_access) {
    // Super-Admin: Query ALL branches
    // Only run query if at least one filter is applied
    if ($branch_f !== '' || $model_f !== '' || $brand_f !== '' || $family_f !== '' || $search !== '') {
        $show_initial_message = false;
        $conds = ["s.item_type = 'IMEI'"];
    if ($search !== '')
        $conds[] = "(s.item_code LIKE '%" . addslashes($search) . "%' OR s.description LIKE '%" . addslashes($search) . "%')";
    if ($status_f !== '')
        $conds[] = "s.status = '" . addslashes($status_f) . "'";
    if ($date_f !== '')
        $conds[] = "s.dr_date = '" . addslashes($date_f) . "'";
    if ($branch_f !== '' && $branch_f !== 'ALL_BRANCHES')
        $conds[] = "s.branch = '" . addslashes($branch_f) . "'";
    if ($model_f !== '')
        $conds[] = "s.item_code LIKE '%" . addslashes($model_f) . "%'";
    if ($brand_f !== '')
        $conds[] = "(COALESCE(i.brand, s.brand) = '" . addslashes($brand_f) . "')";
    if ($family_f !== '')
        $conds[] = "s.family_code = '" . addslashes($family_f) . "'";
    $where = implode(" AND ", $conds);

    $sql = "SELECT s.item_code, s.description, COUNT(*) AS total_qty, s.branch, s.family_code, 
                   COALESCE(i.brand, s.brand, '—') AS brand
            FROM stock_on_hand s
            LEFT JOIN items i ON s.item_code = i.item_code
            WHERE $where
            GROUP BY s.item_code, s.description, s.branch, s.family_code, i.brand, s.brand 
            ORDER BY s.item_code, s.branch";
    $res = $conn->query($sql);
    }
} elseif ($session_branch !== '' && strtoupper($session_branch) !== 'SUPERADMIN') {

    // Filter for IMEI items to count as units (each IMEI = 1 unit)
    // Only run query if at least one filter is applied
    if ($branch_f !== '' || $model_f !== '' || $brand_f !== '' || $family_f !== '' || $search !== '') {
        $show_initial_message = false;
        $conds = ["s.item_type = 'IMEI'", "s.branch IN ($branch_in)"];
    if ($search !== '')
        $conds[] = "(s.item_code LIKE '%" . addslashes($search) . "%' OR s.description LIKE '%" . addslashes($search) . "%')";
    if ($status_f !== '')
        $conds[] = "s.status = '" . addslashes($status_f) . "'";
    if ($date_f !== '')
        $conds[] = "s.dr_date = '" . addslashes($date_f) . "'";
    if ($branch_f !== '' && $branch_f !== 'ALL_BRANCHES')
        $conds[] = "s.branch = '" . addslashes($branch_f) . "'";
    if ($model_f !== '')
        $conds[] = "s.item_code LIKE '%" . addslashes($model_f) . "%'";
    if ($brand_f !== '')
        $conds[] = "(COALESCE(i.brand, s.brand) = '" . addslashes($brand_f) . "')";
    if ($family_f !== '')
        $conds[] = "s.family_code = '" . addslashes($family_f) . "'";
    $where = implode(" AND ", $conds);

    $sql = "SELECT s.item_code, s.description, COUNT(*) AS total_qty, s.branch, s.family_code, 
                   COALESCE(i.brand, s.brand, '—') AS brand
            FROM stock_on_hand s
            LEFT JOIN items i ON s.item_code = i.item_code
            WHERE $where
            GROUP BY s.item_code, s.description, s.branch, s.family_code, i.brand, s.brand 
            ORDER BY s.item_code, s.branch";
        $res = $conn->query($sql);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
        <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <title>Stock On Hand - Unit</title>
    <?php include '_sohand_styles.php'; ?>
    <!-- xlsx-js-style: SheetJS with cell styling support -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
</head>

<body>
    <div class="header">
        <div class="menu-btn active" onclick="toggleSidebar()"><span></span><span></span><span></span></div>
        <!-- <img src="Icon/motogam_logo.jpg" alt="IMS Logo" class="logo"> -->
        <?php include '_header_user.php'; ?>
    </div>

    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                <h2>Stock On Hand</h2>
            </div>
            <div class="button-group">
                <a href="sohandunit.php" class="btn-summary active">Stock Summary Unit</a>
                <a href="sohandserial.php" class="btn-summary">Stock Summary per Serial</a>
                <a href="sohandaccessories.php" class="btn-summary">Stock Summary Accessories</a>
            </div>
        </div>

        <form method="GET" action="sohandunit.php"
            style="background: white; padding: 30px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Select Branch:</label>
                    <select name="branch" class="searchable-select"
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="">Select Branch</option>
                        <option value="ALL_BRANCHES" <?php echo $branch_f === 'ALL_BRANCHES' ? 'selected' : ''; ?>>Select All Branches</option>
                        <?php
                        // Super-Admin: Show ALL branches
                        if ($has_full_access) {
                            $all_branches_res = $conn->query("SELECT branch_name FROM branches ORDER BY branch_name");
                            if ($all_branches_res) {
                                while ($b = $all_branches_res->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo htmlspecialchars($b['branch_name']); ?>" <?php echo $branch_f === $b['branch_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['branch_name']); ?>
                                    </option>
                                    <?php
                                endwhile;
                            }
                        }
                        // Regular users (including Sub-admin): Show only their assigned branches
                        elseif ($session_branch !== '' && strtoupper($session_branch) !== 'SUPERADMIN') {
                            $raw_branches = array_map('trim', explode(',', $session_branch));
                            foreach ($raw_branches as $branch):
                                ?>
                                <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo $branch_f === $branch ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branch); ?>
                                </option>
                                <?php
                            endforeach;
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Model:</label>
                    <select name="model" class="searchable-select"
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="">Models</option>
                        <?php
                        $model_res = $conn->query("SELECT DISTINCT item_code FROM stock_on_hand WHERE item_type = 'IMEI' ORDER BY item_code");
                        while ($m = $model_res->fetch_assoc()):
                            ?>
                            <option value="<?php echo htmlspecialchars($m['item_code']); ?>" <?php echo $model_f === $m['item_code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['item_code']); ?>
                            </option>
                            <?php
                        endwhile; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Brand:</label>
                    <select name="brand" class="searchable-select"
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="">Brands</option>
                        <?php
                        $brand_res = $conn->query("SELECT brand_name FROM brands WHERE status = 'Active' ORDER BY brand_name");
                        if ($brand_res) {
                            while ($br = $brand_res->fetch_assoc()):
                                ?>
                                <option value="<?php echo htmlspecialchars($br['brand_name']); ?>" <?php echo $brand_f === $br['brand_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($br['brand_name']); ?>
                                </option>
                                <?php
                            endwhile;
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Family:</label>
                    <select name="family" class="searchable-select"
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="">Families</option>
                        <?php
                        $family_res = $conn->query("SELECT family_code FROM family_codes WHERE status = 'Active' ORDER BY family_code");
                        if ($family_res) {
                            while ($fam = $family_res->fetch_assoc()):
                                ?>
                                <option value="<?php echo htmlspecialchars($fam['family_code']); ?>" <?php echo $family_f === $fam['family_code'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($fam['family_code']); ?>
                                </option>
                                <?php
                            endwhile;
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div style="display: flex; justify-content: start; gap: 15px; align-items: center;">
                <button type="submit"
                    style="padding: 10px 30px; background: white; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">
                    Search
                </button>
                <!-- <input type="date" name="date" value="<?php echo htmlspecialchars($date_f); ?>" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"> -->
                <button class="btn-print" onclick="printReport()">
                    <svg viewBox="0 0 24 24">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2-2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Print
                </button>
                <button class="btn-export" onclick="exportToExcel()">
                    &#128196; Export
                </button>
            </div>
        </form>

        <div class="table-container">
            <div class="print-header" style="display: none;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 12px;">
                    <div>
                        <div><strong>BRANCH:</strong> <?php echo htmlspecialchars($branch_f ?: 'All'); ?></div>
                        <div><strong>BRAND:</strong> <?php echo htmlspecialchars($brand_f ?: 'All'); ?></div>
                    </div>
                    <div style="text-align: right;">
                        <div><strong>DATE:</strong> <?php echo date('m/d/Y'); ?></div>
                        <div><strong>TIME:</strong> <?php echo date('h:i A'); ?></div>
                    </div>
                </div>
            </div>
            <h3>Stock Summary Unit</h3>
            <?php if ($show_initial_message): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Model Code</th>
                            <th>Description</th>
                            <th>Qty</th>
                            <th>Branch</th>
                            <th>Family</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5">
                                <div class="no-data">Please select Branch or apply filters to view stock</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            <?php elseif ($res && $res->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Model Code</th>
                            <th>Description</th>
                            <th>Qty</th>
                            <th>Branch</th>
                            <th>Family</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res && $res->num_rows > 0): ?>
                            <?php while ($row = $res->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['item_code']); ?></td>
                                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td><?php echo intval($row['total_qty']); ?></td>
                                    <td><?php echo htmlspecialchars($row['branch']); ?></td>
                                    <td><?php echo htmlspecialchars($row['family_code'] ?? '—'); ?></td>
                                </tr>
                                <?php
                            endwhile; ?>
                            <?php
                        else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="no-data">No Stock Available</div>
                                </td>
                            </tr>
                            <?php
                        endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Model Code</th>
                            <th>Description</th>
                            <th>Qty</th>
                            <th>Branch</th>
                            <th>Family</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5">
                                <div class="no-data">No Stock Available</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .select-wrapper {
            position: relative;
        }

        .select-search-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            z-index: 1000;
            display: none;
        }

        .select-dropdown.active {
            display: block;
        }

        .select-option {
            padding: 10px;
            cursor: pointer;
            font-size: 14px;
        }

        .select-option:hover {
            background: #f5f5f5;
        }

        .select-option.selected {
            background: #e8e8e8;
            font-weight: 600;
        }

        .select-option.hidden {
            display: none;
        }

        /* Export button styling */
        .btn-export {
            height: 38px;
            padding: 0 22px;
            background: #1b7f3d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-export:hover {
            background: #145c22;
        }
    </style>

    <script>
        function toggleSidebar() {
            document.querySelector('.menu-btn').classList.toggle('active');
            document.querySelector('.sidebar').classList.toggle('hidden');
            document.querySelector('.main-content').classList.toggle('expanded');
        }
        function toggleSection(el) { 
            const section = el.parentElement;
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
            
            if (typeof saveSidebarState === 'function') { saveSidebarState(); }
        }

        function printReport() {
            // Get current filter values
            const branch = '<?php echo addslashes($branch_f); ?>';
            const model = '<?php echo addslashes($model_f); ?>';
            const brand = '<?php echo addslashes($brand_f); ?>';
            const family = '<?php echo addslashes($family_f); ?>';

            // Build URL with parameters
            let url = 'preview_stock_on_hand_unit.php?';
            const params = [];
            if (branch) params.push('branch=' + encodeURIComponent(branch));
            if (model) params.push('model=' + encodeURIComponent(model));
            if (brand) params.push('brand=' + encodeURIComponent(brand));
            if (family) params.push('family=' + encodeURIComponent(family));

            url += params.join('&');

            // Open PDF in new window (popup)
            window.open(url, '_blank', 'width=1000,height=800,toolbar=no,menubar=no,scrollbars=yes,resizable=yes');
        }

        // Make selects searchable
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.searchable-select').forEach(function (select) {
                const wrapper = document.createElement('div');
                wrapper.className = 'select-wrapper';
                select.parentNode.insertBefore(wrapper, select);

                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'select-search-input';
                input.placeholder = select.options[0].text;

                const dropdown = document.createElement('div');
                dropdown.className = 'select-dropdown';

                // Get selected value
                const selectedValue = select.value;
                if (selectedValue) {
                    const selectedOption = Array.from(select.options).find(opt => opt.value === selectedValue);
                    if (selectedOption) input.value = selectedOption.text;
                }

                // Build dropdown options
                Array.from(select.options).forEach(function (option, index) {
                    if (index === 0) return; // Skip placeholder
                    const div = document.createElement('div');
                    div.className = 'select-option';
                    div.textContent = option.text;
                    div.dataset.value = option.value;
                    if (option.value === selectedValue) div.classList.add('selected');
                    dropdown.appendChild(div);
                });

                wrapper.appendChild(input);
                wrapper.appendChild(dropdown);
                select.style.display = 'none';
                wrapper.appendChild(select);

                // Show dropdown on focus
                input.addEventListener('focus', function () {
                    dropdown.classList.add('active');
                });

                // Filter options on input
                input.addEventListener('input', function () {
                    const searchTerm = this.value.toLowerCase();
                    // Clear the select value when user manually types/clears — only a dropdown click sets it
                    select.value = '';
                    dropdown.querySelectorAll('.select-option').forEach(function (option) {
                        option.classList.remove('selected');
                        const text = option.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            option.classList.remove('hidden');
                        } else {
                            option.classList.add('hidden');
                        }
                    });
                    // Keep dropdown open while typing
                    dropdown.classList.add('active');
                });

                // Select option on click
                dropdown.addEventListener('click', function (e) {
                    if (e.target.classList.contains('select-option')) {
                        input.value = e.target.textContent;
                        select.value = e.target.dataset.value;
                        dropdown.querySelectorAll('.select-option').forEach(opt => opt.classList.remove('selected'));
                        e.target.classList.add('selected');
                        dropdown.classList.remove('active');
                    }
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function (e) {
                    if (!wrapper.contains(e.target)) {
                        dropdown.classList.remove('active');
                    }
                });
            });
        });

        function exportToExcel() {
            const branch = '<?php echo addslashes($branch_f); ?>';
            const model = '<?php echo addslashes($model_f); ?>';
            const brand = '<?php echo addslashes($brand_f); ?>';
            const family = '<?php echo addslashes($family_f); ?>';
            const status = '<?php echo addslashes($status_f); ?>';

            const table = document.querySelector('.table-container table');
            if (!table) {
                alert('Table not found.');
                return;
            }
            
            const tbody = table.querySelector('tbody');
            if (!tbody) {
                alert('Table body not found.');
                return;
            }
            
            const rows = tbody.querySelectorAll('tr');

            if (rows.length === 0 || rows[0].querySelector('td[colspan]')) {
                alert('No data to export. Please search first.');
                return;
            }

            const branchLabel = branch || 'All Branches';
            const modelLabel = model || 'All Models';
            const brandLabel = brand || 'All Brands';
            const statusLabel = status || 'All Status';

            // Colour palette
            const C = {
                titleBg: '000000',
                titleFg: 'FFFFFF',
                headerBg: '0E4C2F',
                headerFg: 'FFFFFF',
                metaBg: 'EAF0FB',
                metaKey: '000000',
                metaVal: '333333',
                oddRow: 'FFFFFF',
                evenRow: 'F0F5FF',
                border: 'B0BEC5',
                hdrBorder: '000000',
                accentLine: '000000'
            };

            const headers = ['Model Code', 'Description', 'Qty', 'Branch', 'Family'];
            const COL = headers.length;

            // Collect data rows
            const dataRows = [];
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length === 0 || cells[0].hasAttribute('colspan')) return;
                const r = Array.from(cells).map(cell => cell.textContent.trim());
                // Convert quantity to number
                r[2] = isNaN(r[2]) || r[2] === '' ? r[2] : Number(r[2]);
                dataRows.push(r);
            });

            const allRows = [
                ['STOCK ON HAND - UNIT'],
                ['Branch:', branchLabel],
                ['Model:', modelLabel],
                ['Brand:', brandLabel],
                ['Status:', statusLabel],
                [],
                headers,
                ...dataRows
            ];
            const ws = XLSX.utils.aoa_to_sheet(allRows);
            const EXPORT_SCALE = 0.9;

            ws['!merges'] = [
                { s: { r: 0, c: 0 }, e: { r: 0, c: COL - 1 } },
                { s: { r: 1, c: 1 }, e: { r: 1, c: 3 } },
                { s: { r: 2, c: 1 }, e: { r: 2, c: 3 } },
                { s: { r: 3, c: 1 }, e: { r: 3, c: 3 } },
                { s: { r: 4, c: 1 }, e: { r: 4, c: 3 } }
            ];

            ws['!rows'] = [
                { hpt: Math.round(36 * EXPORT_SCALE) },
                { hpt: Math.round(18 * EXPORT_SCALE) },
                { hpt: Math.round(18 * EXPORT_SCALE) },
                { hpt: Math.round(18 * EXPORT_SCALE) },
                { hpt: Math.round(18 * EXPORT_SCALE) },
                { hpt: Math.round(8 * EXPORT_SCALE) },
                { hpt: Math.round(22 * EXPORT_SCALE) }
            ];

            ws['!cols'] = [
                { wch: Math.round(50 * EXPORT_SCALE) }, // Model Code - 50
                { wch: Math.round(50 * EXPORT_SCALE) }, // Description - increased to 50
                { wch: Math.round(10 * EXPORT_SCALE) }, // Qty
                { wch: Math.round(20 * EXPORT_SCALE) }, // Branch
                { wch: Math.round(35 * EXPORT_SCALE) }  // Family - increased to 25
            ];

            function cs(addr, style) {
                if (!ws[addr]) ws[addr] = { v: '', t: 's' };
                ws[addr].s = style;
            }

            const thick = col => ({ style: 'medium', color: { rgb: col } });
            const thin = col => ({ style: 'thin', color: { rgb: col } });

            // Title
            cs('A1', {
                font: { bold: true, sz: Math.round(18 * EXPORT_SCALE), color: { rgb: C.titleFg }, name: 'Calibri' },
                fill: { patternType: 'solid', fgColor: { rgb: C.titleBg } },
                alignment: { horizontal: 'center', vertical: 'center' },
                border: { bottom: thick(C.titleBg), left: thick(C.titleBg), right: thick(C.titleBg), top: thick(C.titleBg) }
            });

            // Metadata rows
            [1, 2, 3, 4].forEach(ri => {
                const keyAddr = XLSX.utils.encode_cell({ r: ri, c: 0 });
                const valAddr = XLSX.utils.encode_cell({ r: ri, c: 1 });
                cs(keyAddr, {
                    font: { bold: true, sz: Math.round(11 * EXPORT_SCALE), color: { rgb: C.metaKey }, name: 'Calibri' },
                    fill: { patternType: 'solid', fgColor: { rgb: C.metaBg } },
                    alignment: { horizontal: 'left', vertical: 'center' }
                });
                cs(valAddr, {
                    font: { sz: Math.round(11 * EXPORT_SCALE), color: { rgb: C.metaVal }, name: 'Calibri' },
                    fill: { patternType: 'solid', fgColor: { rgb: C.metaBg } },
                    alignment: { horizontal: 'left', vertical: 'center' }
                });
                for (let ci = 2; ci < COL; ci++) {
                    cs(XLSX.utils.encode_cell({ r: ri, c: ci }), {
                        fill: { patternType: 'solid', fgColor: { rgb: C.metaBg } }
                    });
                }
            });

            // Header
            headers.forEach((h, ci) => {
                const addr = XLSX.utils.encode_cell({ r: 6, c: ci });
                cs(addr, {
                    font: { bold: true, sz: Math.round(11 * EXPORT_SCALE), color: { rgb: C.headerFg }, name: 'Calibri' },
                    fill: { patternType: 'solid', fgColor: { rgb: C.headerBg } },
                    alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
                    border: { top: thick(C.accentLine), bottom: thick(C.accentLine), left: thin(C.hdrBorder), right: thin(C.hdrBorder) }
                });
            });

            // Data rows
            dataRows.forEach((drow, ri) => {
                const isEven = ri % 2 === 1;
                const rowBg = isEven ? C.evenRow : C.oddRow;
                headers.forEach((__, ci) => {
                    const addr = XLSX.utils.encode_cell({ r: 7 + ri, c: ci });
                    if (!ws[addr]) ws[addr] = { v: '', t: 's' };
                    const isQty = ci === 2;
                    // Left align: Model Code (0), Description (1), Family (4)
                    const isLeftAlign = ci === 0 || ci === 1 || ci === 4;
                    ws[addr].s = {
                        font: { sz: Math.round(10 * EXPORT_SCALE), name: 'Calibri', color: { rgb: '1A1A1A' } },
                        fill: { patternType: 'solid', fgColor: { rgb: rowBg } },
                        alignment: { horizontal: isQty ? 'right' : (isLeftAlign ? 'left' : 'center'), vertical: 'center' },
                        border: { top: thin(C.border), bottom: thin(C.border), left: thin(C.border), right: thin(C.border) }
                    };
                    if (isQty && typeof ws[addr].v === 'number') {
                        ws[addr].z = '#,##0';
                    }
                });
            });

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Stock Unit');
            const fileName = 'Stock_On_Hand_Unit_' + (branch || 'All').replace(/\s+/g, '_') + '_' + new Date().toISOString().slice(0, 10) + '.xlsx';
            XLSX.writeFile(wb, fileName);
        }
    </script>
</body>

</html>