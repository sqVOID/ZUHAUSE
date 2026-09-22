<?php
require_once 'session_check.php';

// Authorization Check
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';

// Restrict 'User' from accessing this page
if (false) {
    header("Location: report.php");
    exit();
}
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "motogam_management";


$conn = new mysqli($servername, $username, $password, $dbname);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$create_table = "CREATE TABLE IF NOT EXISTS departments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(255) NOT NULL UNIQUE,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$conn->query($create_table);

// Handle form submission
$message = "";
$messageType = "";
$editMode = false;
$editData = null;

// Check if editing
if (isset($_GET['edit'])) {
    $editMode = true;
    $id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT * FROM departments WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $editData = $result->fetch_assoc();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_department'])) {
    $department_name = strtoupper($conn->real_escape_string($_POST['department_name']));

    // Check if updating or inserting
    if (isset($_POST['department_id']) && !empty($_POST['department_id'])) {
        // Update existing department
        $id = $conn->real_escape_string($_POST['department_id']);
        $sql = "UPDATE departments SET department_name='$department_name' WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            header("Location: DepartmentReg.php?updated=1");
            exit();
        }
        else {
            $message = "Error: " . $conn->error;
            $messageType = "error";
        }
    }
    else {
        // Insert new department
        $sql = "INSERT INTO departments (department_name, status) VALUES ('$department_name', 'Active')";

        if ($conn->query($sql) === TRUE) {
            header("Location: DepartmentReg.php?success=1");
            exit();
        }
        else {
            $message = "Error: " . $conn->error;
            $messageType = "error";
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Department registered successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Check for update message from redirect
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = "Department updated successfully!";
    $messageType = "success";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

// Handle status update
if (isset($_GET['status_update'])) {
    $id = $conn->real_escape_string($_GET['id']);
    $status = $conn->real_escape_string($_GET['status']);

    // Checkbox checked = Deactivate
    // Checkbox unchecked = Activate
    $new_status = ($status == 'true') ? 'Deactivated' : 'Active';

    $sql = "UPDATE departments SET status = '$new_status' WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        header("Location: DepartmentReg.php");
        exit();
    }
    else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Handle deletion (optional)
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);
    $sql = "DELETE FROM departments WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "Department deleted successfully!";
        $messageType = "success";
        header("Location: DepartmentReg.php");
        exit();
    }
    else {
        $message = "Error: " . $conn->error;
        $messageType = "error";
    }
}

// Fetch all departments
$departments_result = $conn->query("SELECT * FROM departments ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/imslogo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Registration</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #fffcf4ff;
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
            box-shadow: 0 2px 4px rgba(0,0,0,0.5);
            pointer-events: none;
        }

        .logo {
            margin-left: -20px;
            height: 50px;
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
            box-shadow: 2px 0 4px rgba(0,0,0,0.1);
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
            background-color: #e3fdeeff;
            color: #0e7725ff;
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

        .btn-add-department {
            padding: 10px 24px;
            border: none;
            background: #2e7d32;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-add-department:hover {
            background: #1b5e20;
        }

        .form-container.hidden {
            display: none;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 20px;
            max-width: 500px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: white;
        }

        .form-group input::placeholder {
            color: #999;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2196F3;
        }

        .form-actions {
            display: flex;
            justify-content: flex-start;
            gap: 10px;
            margin-top: 30px;
        }

        .btn-cancel {
            padding: 10px 24px;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-cancel:hover {
            background: #f5f5f5;
        }

        .btn-save {
            padding: 10px 24px;
            border: none;
            background: #2e7d32;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-save:hover {
            background: #1b5e20;
        }

        .table-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .table-container h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0 0 20px 0;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            margin: 0;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 8px 35px 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #2196F3;
        }

        .search-box svg {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            fill: #999;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #E1FFDE;
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
            background: #fafafa;
        }

        tbody td {
            text-align: center;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.inactive {
            background: #ffebee;
            color: #c62828;
        }

        .btn-edit {
            padding: 6px 16px;
            border: none;
            background: #1976D2;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
        }

        .btn-edit:hover {
            background: #1565C0;
        }

        .btn-delete {
            padding: 6px 16px;
            border: none;
            background: #c62828;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }

        .btn-delete:hover {
            background: #b71c1c;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
        }

        .alert.success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #2e7d32;
        }

        .alert.error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #c62828;
        }

        @media (max-width: 1024px) {
            .form-row {
                max-width: 100%;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.hidden {
                transform: translateX(-100%);
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
            .form-container {
                padding: 20px;
            }
            .table-container {
                padding: 20px;
                overflow-x: auto;
            }
            .search-box {
                width: 200px;
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
            .form-container {
                padding: 15px;
            }
            .table-container {
                padding: 15px;
            }
            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .search-box {
                width: 100%;
            }
            .content-header {
                margin-bottom: 20px;
            }
            .content-header h2 {
                font-size: 16px;
            }
            .form-actions {
                flex-direction: column;
            }
            .btn-cancel,
            .btn-save {
                width: 100%;
            }
            th, td {
                padding: 8px;
                font-size: 12px;
            }
        }

        .btn-toggle-status {
            padding: 6px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-left: 5px;
            transition: background 0.3s;
        }

        .btn-toggle-status.deactivate {
            background: #c62828;
            color: white;
        }

        .btn-toggle-status.deactivate:hover {
            background: #b71c1c;
        }

        .btn-toggle-status.activate {
            background: #2e7d32;
            color: white;
        }

        .btn-toggle-status.activate:hover {
            background: #1b5e20;
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
        <img src="Icon/imslogo2.svg" alt="IMS Logo" class="logo">
        <?php include '_header_user.php'; ?>
    </div>

    <?php include '_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h2>Department Registration</h2>
            <button class="btn-add-department" onclick="toggleForm()">Add Department</button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php
endif; ?>

        <div id="formContainer" class="form-container <?php echo $editMode ? '' : 'hidden'; ?>">
            <form method="POST" action="">
                <?php if ($editMode): ?>
                    <input type="hidden" name="department_id" value="<?php echo $editData['id']; ?>">
                <?php
endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Department Name</label>
                        <input type="text" name="department_name" required value="<?php echo $editMode ? $editData['department_name'] : ''; ?>" placeholder="Enter department name" oninput="this.value = this.value.toUpperCase()">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="toggleForm()">Cancel</button>
                    <button type="submit" name="register_department" class="btn-save">
                        <?php echo $editMode ? 'Update' : 'Register'; ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3>Department List</h3>
                <div class="search-box">
                    <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>                    <input type="text" id="searchInput" placeholder="Search..." onkeyup="searchTable()">
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Department Name</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($departments_result && $departments_result->num_rows > 0): ?>
                        <?php while ($row = $departments_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($row['status']) == 'active' ? 'active' : 'inactive'; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="DepartmentReg.php?edit=<?php echo $row['id']; ?>" class="btn-edit">Edit</a>
                                    <!-- Toggle Status Button -->
                                    <button class="btn-toggle-status <?php echo strtolower($row['status']) == 'active' ? 'deactivate' : 'activate'; ?>" 
                                            onclick="toggleStatus(<?php echo $row['id']; ?>, '<?php echo $row['status']; ?>')">
                                        <?php echo($row['status'] == 'Active') ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                    <a href="DepartmentReg.php?delete=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this department?')">Delete</a>
                                </td>
                            </tr>
                        <?php
    endwhile; ?>
                    <?php
else: ?>
                        <tr>
                            <td colspan="3">No departments found</td>
                        </tr>
                    <?php
endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('hidden');
            document.querySelector('.main-content').classList.toggle('expanded');
            document.querySelector('.menu-btn').classList.toggle('active');
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

        function toggleForm() {
            const formContainer = document.getElementById('formContainer');
            const btnAdd = document.querySelector('.btn-add-department');
            
            if (formContainer.classList.contains('hidden')) {
                formContainer.classList.remove('hidden');
                btnAdd.style.display = 'none';
                
                // If not in edit mode, reset form
                <?php if (!$editMode): ?>
                document.querySelector('form').reset();
                <?php
endif; ?>
            } else {
                formContainer.classList.add('hidden');
                btnAdd.style.display = 'block';
                // If in edit mode and cancelling, simple redirect to clear edit mode
                <?php if ($editMode): ?>
                window.location.href = 'DepartmentReg.php';
                <?php
endif; ?>
            }
        }

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.querySelector('table');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td')[0];
                if (td) {
                    const txtValue = td.textContent || td.innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }

        function toggleStatus(id, currentStatus) {
            // Determine the new status based on current status
            const newStatus = (currentStatus === 'Active') ? 'Deactivated' : 'Active';
            const statusParam = (currentStatus === 'Active') ? 'true' : 'false';
            
            window.location.href = `DepartmentReg.php?status_update=1&id=${id}&status=${statusParam}`;
        }
    </script>
</body>
</html>


