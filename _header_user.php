<?php
// _header_user.php — drop this inside any .header div
$_hu_name = isset($_SESSION['user_name']) ? trim($_SESSION['user_name']) : 'User';
$_hu_position = isset($_SESSION['user_position']) ? trim($_SESSION['user_position']) : '';

// Build initials: up to 2 capital letters from each word
$_hu_words = preg_split('/\s+/', $_hu_name);
$_hu_initials = '';
foreach ($_hu_words as $_w) {
    if ($_w !== '')
        $_hu_initials .= strtoupper($_w[0]);
    if (strlen($_hu_initials) >= 2)
        break;
}
if ($_hu_initials === '')
    $_hu_initials = 'U';

// Query for completed booklets that need to be returned
$_hu_completed_booklets = [];
$_hu_completed_count = 0;
$_hu_show_booklet_notification = true; // Notification enabled

// Query for approved stock transfers that need to be received
$_hu_approved_transfers = [];
$_hu_approved_count = 0;
$_hu_show_transfer_notification = true; // Notification enabled

// GLOBALLY HIDDEN - Notifications disabled
/* Check if user has access to booklet pages
if (isset($_SESSION['sidebar_access']) && $_SESSION['sidebar_access'] !== '') {
    $sidebar_hidden = array_map('trim', explode(',', $_SESSION['sidebar_access']));
    // Show notification if user has access to either booklet page
    $_hu_show_booklet_notification = !in_array('Booklet Inventory', $sidebar_hidden) || !in_array('Booklet Number Registration', $sidebar_hidden);
    // Show notification if user has access to receive stock transfer page
    $_hu_show_transfer_notification = !in_array('Receive Stock Transfer', $sidebar_hidden);
} else {
    // If no restrictions, user has access to all pages
    $_hu_show_booklet_notification = true;
    $_hu_show_transfer_notification = true;
}
*/

if ($_hu_show_booklet_notification && isset($conn)) {
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    
    // Build WHERE clause based on user access level
    $where_clause = "WHERE bn.complete_date IS NOT NULL AND bn.return_date IS NULL";
    
    // For non-Super-Admin users, restrict to their branches
    if (strcasecmp($system_level, 'Super-Admin') !== 0 && !empty($user_branch)) {
        $branch_names = array_map('trim', explode(',', $user_branch));
        $branch_names_quoted = array_map(function($name) use ($conn) {
            return "'" . $conn->real_escape_string($name) . "'";
        }, $branch_names);
        $branch_names_in = implode(',', $branch_names_quoted);
        
        // Get branch codes for these branch names
        $branch_code_query = $conn->query("SELECT branch_code FROM branches WHERE branch_name IN ($branch_names_in)");
        if ($branch_code_query && $branch_code_query->num_rows > 0) {
            $user_branch_codes = [];
            while ($row = $branch_code_query->fetch_assoc()) {
                $user_branch_codes[] = $row['branch_code'];
            }
            if (!empty($user_branch_codes)) {
                $codes_quoted = array_map(function($code) use ($conn) {
                    return "'" . $conn->real_escape_string($code) . "'";
                }, $user_branch_codes);
                $codes_in = implode(',', $codes_quoted);
                $where_clause .= " AND bn.branch_code IN ($codes_in)";
            } else {
                $where_clause .= " AND 1=0"; // No valid branches
            }
        }
    }
    
    // Fetch completed booklets
    $_hu_booklets_query = "SELECT bn.id, bn.booklet_no, bn.complete_date, b.branch_name, b.branch_code 
                           FROM booklet_numbers bn 
                           LEFT JOIN branches b ON bn.branch_code = b.branch_code 
                           $where_clause
                           ORDER BY bn.complete_date DESC
                           LIMIT 10";
    $_hu_result = $conn->query($_hu_booklets_query);
    
    if ($_hu_result && $_hu_result->num_rows > 0) {
        while ($_hu_row = $_hu_result->fetch_assoc()) {
            $_hu_completed_booklets[] = $_hu_row;
        }
        $_hu_completed_count = count($_hu_completed_booklets);
    }
}

// Fetch approved stock transfers that need to be received
if ($_hu_show_transfer_notification && isset($conn)) {
    $system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
    $user_branch = isset($_SESSION['user_branch']) ? trim($_SESSION['user_branch']) : '';
    
    // Check if user is Super-Admin or Sub-admin (has access to all branches)
    $is_admin = (strcasecmp($system_level, 'Super-Admin') === 0 || strcasecmp($system_level, 'Sub-admin') === 0);
    
    // Build query for approved transfers
    $_hu_transfers_query = "SELECT st.st_number, st.st_date, 
                                   CONCAT(st.branch_from, ' - ', COALESCE(bf.branch_name, '')) as branch_from,
                                   CONCAT(COALESCE(bt.branch_code, ''), ' - ', st.branch_to) as branch_to,
                                   st.prepared_by
                            FROM stock_transfers st
                            LEFT JOIN branches bf ON bf.branch_code = st.branch_from
                            LEFT JOIN branches bt ON bt.branch_name = st.branch_to
                            WHERE st.status = 'Approved'";
    
    // Filter by user's branch unless they are admin
    if (!$is_admin && !empty($user_branch)) {
        $user_branch_escaped = $conn->real_escape_string($user_branch);
        $_hu_transfers_query .= " AND st.branch_to = '$user_branch_escaped'";
    }
    
    $_hu_transfers_query .= " ORDER BY st.st_date DESC LIMIT 10";
    
    $_hu_result = $conn->query($_hu_transfers_query);
    
    if ($_hu_result && $_hu_result->num_rows > 0) {
        while ($_hu_row = $_hu_result->fetch_assoc()) {
            $_hu_approved_transfers[] = $_hu_row;
        }
        $_hu_approved_count = count($_hu_approved_transfers);
    }
}
?>
<style>
    .avatar-wavy-bg {
        position: relative;
        background: linear-gradient(135deg, #0d3347 0%, #164460 50%, #081f2d 100%);
        overflow: hidden;
    }
    
    .avatar-wavy-bg::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: 
            radial-gradient(circle at 30% 40%, rgba(22, 68, 96, 0.5) 0%, transparent 60%),
            radial-gradient(circle at 70% 60%, rgba(8, 31, 45, 0.6) 0%, transparent 60%);
    }
    
    .avatar-wavy-bg::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            repeating-linear-gradient(
                45deg,
                transparent,
                transparent 2px,
                rgba(255, 255, 255, 0.08) 2px,
                rgba(255, 255, 255, 0.08) 4px
            );
        animation: wave-slide 12s linear infinite;
    }
    
    @keyframes wave-slide {
        0% { transform: translate(0, 0) rotate(0deg); }
        100% { transform: translate(4px, 4px) rotate(360deg); }
    }
</style>
<div style="
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
">
    <?php if ($_hu_show_booklet_notification || $_hu_show_transfer_notification): ?>
    <!-- Notification Bell -->
    <div style="position: relative;">
        <div id="notificationBell" style="
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        " onmouseover="this.style.backgroundColor='#e0e0e0'" onmouseout="this.style.backgroundColor='#f5f5f5'" onclick="toggleNotificationDropdown()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 22C13.1 22 14 21.1 14 20H10C10 21.1 10.9 22 12 22ZM18 16V11C18 7.93 16.37 5.36 13.5 4.68V4C13.5 3.17 12.83 2.5 12 2.5C11.17 2.5 10.5 3.17 10.5 4V4.68C7.64 5.36 6 7.92 6 11V16L4 18V19H20V18L18 16Z" fill="#666"/>
            </svg>
            <?php 
            $_hu_total_count = $_hu_completed_count + $_hu_approved_count;
            if ($_hu_total_count > 0): 
            ?>
            <div style="
                position: absolute;
                top: -2px;
                right: -2px;
                background-color: #dc3545;
                color: white;
                border-radius: 50%;
                width: 18px;
                height: 18px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 10px;
                font-weight: 700;
                border: 2px solid white;
            ">
                <?php echo $_hu_total_count > 9 ? '9+' : $_hu_total_count; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Notification Dropdown -->
        <div id="notificationDropdown" style="
            display: none;
            position: absolute;
            top: 45px;
            right: 0;
            width: 350px;
            max-height: 400px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 2000;
            overflow: hidden;
        ">
            <div style="
                padding: 15px;
                border-bottom: 1px solid #e0e0e0;
                background-color: #fafafa;
                font-weight: 600;
                font-size: 14px;
                color: #333;
            ">
                Notification
            </div>
            <div style="
                max-height: 350px;
                overflow-y: auto;
            ">
                <?php if ($_hu_completed_count > 0): ?>
                    <!-- Booklet Section -->
                    <div style="padding: 10px 15px; background-color: #f9f9f9; font-weight: 600; font-size: 12px; color: #666;">
                        COMPLETED BOOKLETS
                    </div>
                    <?php foreach ($_hu_completed_booklets as $_hu_booklet): ?>
                    <a href="bookletinv.php?status=Completed" style="
                        display: block;
                        padding: 12px 15px;
                        border-bottom: 1px solid #f0f0f0;
                        text-decoration: none;
                        color: inherit;
                        transition: background-color 0.2s;
                    " onmouseover="this.style.backgroundColor='#f9f9f9'" onmouseout="this.style.backgroundColor='white'">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 4px;">
                            <div style="font-weight: 600; font-size: 13px; color: #333;">
                                Booklet #<?php echo htmlspecialchars($_hu_booklet['booklet_no']); ?>
                            </div>
                            <div style="
                                background-color: #dc3545;
                                color: white;
                                padding: 2px 8px;
                                border-radius: 10px;
                                font-size: 10px;
                                font-weight: 600;
                            ">
                                COMPLETED
                            </div>
                        </div>
                        <div style="font-size: 12px; color: #666; margin-bottom: 2px;">
                            <?php echo htmlspecialchars($_hu_booklet['branch_name'] ?? 'Unknown Branch'); ?>
                        </div>
                        <div style="font-size: 11px; color: #999;">
                            Completed: <?php echo date('M d, Y h:i A', strtotime($_hu_booklet['complete_date'])); ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if ($_hu_approved_count > 0): ?>
                    <!-- Stock Transfer Section -->
                    <div style="padding: 10px 15px; background-color: #f9f9f9; font-weight: 600; font-size: 12px; color: #666;">
                        APPROVED STOCK TRANSFERS
                    </div>
                    <?php foreach ($_hu_approved_transfers as $_hu_transfer): ?>
                    <a href="receivestocktransfer.php" style="
                        display: block;
                        padding: 12px 15px;
                        border-bottom: 1px solid #f0f0f0;
                        text-decoration: none;
                        color: inherit;
                        transition: background-color 0.2s;
                    " onmouseover="this.style.backgroundColor='#f9f9f9'" onmouseout="this.style.backgroundColor='white'">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 4px;">
                            <div style="font-weight: 600; font-size: 13px; color: #333;">
                                ST #<?php echo htmlspecialchars($_hu_transfer['st_number']); ?>
                            </div>
                            <div style="
                                background-color: #2e7d32;
                                color: white;
                                padding: 2px 8px;
                                border-radius: 10px;
                                font-size: 10px;
                                font-weight: 600;
                            ">
                                APPROVED
                            </div>
                        </div>
                        <div style="font-size: 12px; color: #666; margin-bottom: 2px;">
                            From: <?php echo htmlspecialchars($_hu_transfer['branch_from']); ?>
                        </div>
                        <div style="font-size: 12px; color: #666; margin-bottom: 2px;">
                            To: <?php echo htmlspecialchars($_hu_transfer['branch_to']); ?>
                        </div>
                        <div style="font-size: 11px; color: #999;">
                            Date: <?php echo date('M d, Y', strtotime($_hu_transfer['st_date'])); ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if ($_hu_completed_count === 0 && $_hu_approved_count === 0): ?>
                    <div style="
                        padding: 40px 20px;
                        text-align: center;
                        color: #999;
                        font-size: 13px;
                    ">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="#ccc" style="margin-bottom: 10px;">
                            <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z"/>
                        </svg>
                        <div>No pending notifications</div>
                        <div style="font-size: 11px; margin-top: 5px;">All items are up to date</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Name + Position -->
    <div style="text-align: right; line-height: 1.3;">
        <div style="font-size: 13px; font-weight: 600; color: #222; white-space: nowrap;">
            <?php echo htmlspecialchars($_hu_name); ?>
        </div>
        <?php if ($_hu_position !== ''): ?>
        <div style="font-size: 11px; color: #777; white-space: nowrap;">
            <?php echo htmlspecialchars($_hu_position); ?>
        </div>
        <?php
endif; ?>
    </div>
    <!-- Circle avatar with initials -->
    <div class="avatar-wavy-bg" style="
        width: 36px;
        height: 36px;
        border-radius: 50%;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.5px;
        flex-shrink: 0;
        user-select: none;
        position: relative;
    ">
        <span style="position: relative; z-index: 3;">
            <?php echo htmlspecialchars($_hu_initials); ?>
        </span>
    </div>
</div>

<?php if ($_hu_show_booklet_notification || $_hu_show_transfer_notification): ?>
<script>
function toggleNotificationDropdown() {
    var dropdown = document.getElementById('notificationDropdown');
    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
        dropdown.style.display = 'block';
    } else {
        dropdown.style.display = 'none';
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    var bell = document.getElementById('notificationBell');
    var dropdown = document.getElementById('notificationDropdown');
    
    if (bell && dropdown) {
        var isClickInsideBell = bell.contains(event.target);
        var isClickInsideDropdown = dropdown.contains(event.target);
        
        if (!isClickInsideBell && !isClickInsideDropdown) {
            dropdown.style.display = 'none';
        }
    }
});
</script>
<?php endif; ?>
