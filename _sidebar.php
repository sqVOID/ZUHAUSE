<?php
// -- Shared sidebar for sohandunit / sohandimei / sohandaccessories --
$sidebar_hidden = [];
if (isset($_SESSION['sidebar_access']) && $_SESSION['sidebar_access'] !== '') {
    $sidebar_hidden = explode(',', $_SESSION['sidebar_access']);
    // Trim whitespace from each item
    $sidebar_hidden = array_map('trim', $sidebar_hidden);
}
$user_system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$user_position = isset($_SESSION['user_position']) ? trim($_SESSION['user_position']) : '';
$is_area_manager = (stripos($user_position, 'Area Manager') !== false);

// Define page groups for sidebar state
$user_registration_pages = ['accountregistration.php', 'useractivation.php', 'position.php', 'sidebarperacc.php'];
$location_registration_pages = ['promotereg.php', 'areareg.php', 'branchregistration.php', 'dealerregistration.php', 'bookletnoreg.php', 'skipbookletno.php'];
$item_registration_pages = ['supplierreg.php', 'brandreg.php', 'familycodereg.php', 'departmentreg.php', 'groupreg.php', 'itemreg.php'];
$terminal_pages = ['createterminal.php', 'createterminalid.php'];
$history_pages = ['historyimei.php', 'historyitemmodel.php'];
$sohand_pages = ['sohand.php', 'sohandunit.php', 'sohandserial.php', 'sohandaccessories.php'];
$purchase_order_pages = ['purchaseorder.php', 'createpurchaseorder.php', 'viewpurchaseorder.php', 'purchaseorder-invperbranch.php'];
$receive_purchase_order_pages = ['purchaseorderreceive.php'];
$preorder_pages = ['preorder.php', 'preorder2.php', 'claimpreorder.php'];
$report_pages = ['report.php', 'salesreport.php', 'dailysalespaytype.php', 'voidsalesreport.php', 'upgradeunitreport.php', 'rddeliveryreport.php', 'stocktransferreport.php', 'refundreport.php', 'preorderreport.php', 'reporttrade-in.php'];
$purchase_management_pages = ['purchaseorder.php', 'createpurchaseorder.php', 'viewpurchaseorder.php', 'purchaseorderreceive.php'];
$sales_management_pages = ['salesentry.php', 'modification-motogam.php', 'voidsales.php', 'salesskipapproval.php', 'upgradeunit.php', 'refund.php', 'salestrade-in.php', 'salesentry-status.php'];
$stock_transfer_pages = ['stocktransfer.php', 'transferapproval.php', 'receivestocktransfer.php'];
$approval_pages = ['transferapproval.php', 'salesskipapproval.php', 'approval-itemstatus.php'];

// Get current page name for active menu highlighting
// Check if there's a custom sidebar page override (used by viewpurchaseorder.php)
$current_page = isset($_GET['_sidebar_page']) ? $_GET['_sidebar_page'] : basename($_SERVER['PHP_SELF']);

// Special handling for viewpurchaseorder.php - add it to the appropriate array based on override
if (basename($_SERVER['PHP_SELF']) === 'viewpurchaseorder.php') {
    if ($current_page === 'purchaseorderreceive.php') {
        $receive_purchase_order_pages[] = 'viewpurchaseorder.php';
    } else {
        // Already in purchase_order_pages, no need to add
    }
}
?>
<div class="sidebar">
    <?php
    $sales_mgmt_labels = ['Sales Entry', 'Modification Sales', 'Stock Transfer', 'Upgrade Unit', 'Refund', 'Claim Item', 'Trade-In', 'Item Status'];
    $sales_mgmt_visible = false;
    foreach ($sales_mgmt_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $sales_mgmt_visible = true;
            break;
        }
    }
    // Override for Super-Admin check on Modification Sales
    if (!in_array('Sales Entry', $sidebar_hidden)) {
        $sales_mgmt_visible = true;
    }
    ?>

    <?php if ($sales_mgmt_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Process</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">
                <?php if (!in_array('Sales Entry', $sidebar_hidden)): ?>
                    <a href="salesentry.php"
                        class="menu-item<?php echo ($current_page === 'salesentry.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Sales Entry</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Stock Transfer', $sidebar_hidden)): ?>
                    <a href="stocktransfer.php"
                        class="menu-item<?php echo ($current_page === 'stocktransfer.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Stock Transfer</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Upgrade Unit', $sidebar_hidden)): ?>
                    <a href="upgradeunit.php"
                        class="menu-item<?php echo ($current_page === 'upgradeunit.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Upgrade Unit</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Refund', $sidebar_hidden)): ?>
                    <a href="refund.php" class="menu-item<?php echo ($current_page === 'refund.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Refund</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Claim Item', $sidebar_hidden)): ?>
                    <a href="claimitem.php" class="menu-item<?php echo ($current_page === 'claimitem.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Claim Item</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Trade-In', $sidebar_hidden)): ?>
                    <a href="salestrade-in.php"
                        class="menu-item<?php echo ($current_page === 'salestrade-in.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Trade-In</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Item Status', $sidebar_hidden)): ?>
                    <a href="salesentry-status.php"
                        class="menu-item<?php echo ($current_page === 'salesentry-status.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Item Status</a>
                    <?php
                endif; ?>
                <?php if (false && !in_array('Cancel Invoice Approval', $sidebar_hidden)): ?>
                    <a href="salesskipapproval.php"
                        class="menu-item<?php echo ($current_page === 'salesskipapproval.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Cancel Invoice Approval</a>
                    <?php
                endif; ?>
            </div>
        </div> 
        <?php
    endif; ?>

    <?php
    $preorder_labels = ['Pre Order', 'Pre Order 2', 'Claim Pre Order'];
    $preorder_visible = false;
    foreach ($preorder_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $preorder_visible = true;
            break;
        }
    }
    ?>

    <?php if ($preorder_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Pre Orders</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">
                <?php if (!in_array('Pre Order', $sidebar_hidden)): ?>
                    <a href="preorder.php"
                        class="menu-item<?php echo in_array($current_page, $preorder_pages) && $current_page === 'preorder.php' ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Pre Order</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Pre Order 2', $sidebar_hidden)): ?>
                    <a href="preorder2.php"
                        class="menu-item<?php echo in_array($current_page, $preorder_pages) && $current_page === 'preorder2.php' ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Pre Order 2</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Claim Pre Order', $sidebar_hidden)): ?>
                    <a href="claimpreorder.php"
                        class="menu-item<?php echo in_array($current_page, $preorder_pages) && $current_page === 'claimpreorder.php' ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Claim Pre Order</a>
                    <?php
                endif; ?>
            </div>
        </div>
        <?php
    endif; ?>

    <?php
    $purchase_order_labels = ['Purchase Order', 'PO Invoice per Branch'];
    $purchase_order_visible = false;
    foreach ($purchase_order_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $purchase_order_visible = true;
            break;
        }
    }
    ?>

    <?php if ($purchase_order_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Purchase Order</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">
                <?php if (!in_array('Purchase Order', $sidebar_hidden)): ?>
                    <a href="purchaseorder.php"
                        class="menu-item<?php echo ($current_page === 'purchaseorder.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Purchase Order</a>
                    <?php
                endif; ?>
                <?php if (!in_array('PO Invoice per Branch', $sidebar_hidden)): ?>
                    <a href="purchaseorder-invperbranch.php"
                        class="menu-item<?php echo ($current_page === 'purchaseorder-invperbranch.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• PO Invoice Per Branch</a>
                    <?php
                endif; ?>
            </div>
        </div>
        <?php
    endif; ?>

    <?php
    $report_labels = ['Daily Sales Report', 'Monthly Sales Report', 'Payment Details Report', 'Void Sales Report', 'Upgrade Unit Report', 'Receive Direct Delivery', 'Stock Transfer Report', 'Refund Report', 'Stock on Hand', 'Pre Order Report', 'Trade-In Report', 'Item Status Report'];
    $report_visible = false;
    foreach ($report_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $report_visible = true;
            break;
        }
    }
    ?>
    <?php if ($report_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Branch Reports</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">

                <?php if (!in_array('Stock on Hand', $sidebar_hidden)): ?>
                    <a href="sohandunit.php"
                        class="menu-item<?php echo in_array($current_page, $sohand_pages) ? ' active' : ''; ?>"
                        style="text-decoration:none;">
                        • Stock on Hand
                    </a>
                    <?php
                endif; ?>

                <?php if (!in_array('Daily Sales Report', $sidebar_hidden)): ?>
                    <a href="report.php" class="menu-item<?php echo ($current_page === 'report.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">
                        • Daily Sales Report
                    </a>
                    <?php
                endif; ?>


                <?php if (!in_array('Monthly Sales Report', $sidebar_hidden)): ?>
                    <a href="salesreport.php"
                        class="menu-item<?php echo ($current_page === 'salesreport.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">
                        • Monthly Sales Report
                    </a>
                    <?php
                endif; ?>

                <?php if (!in_array('Payment Details Report', $sidebar_hidden)): ?>
                    <a href="dailysalespaytype.php"
                        class="menu-item<?php echo ($current_page === 'dailysalespaytype.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">
                        • Payment Details Report
                    </a>
                    <?php
                endif; ?>

                <?php if (!in_array('Pre Order Report', $sidebar_hidden)): ?>
                    <a href="preorderreport.php"
                        class="menu-item<?php echo ($current_page === 'preorderreport.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">
                        • Pre Order Report
                    </a>
                    <?php
                endif; ?>

                <?php if (!in_array('Trade-In Report', $sidebar_hidden)): ?>
                    <a href="reporttrade-in.php"
                        class="menu-item<?php echo ($current_page === 'reporttrade-in.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">
                        • Trade-In Report
                    </a>
                    <?php
                endif; ?>


                <?php if (!in_array('Void Sales Report', $sidebar_hidden)): ?>
                    <a href="voidsalesreport.php"
                        class="menu-item<?php echo ($current_page === 'voidsalesreport.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">
                        • Void Sales Report
                    </a>
                    <?php
                endif; ?>

                <?php if (!in_array('Upgrade Unit Report', $sidebar_hidden)): ?>
                    <a href="upgradeunitreport.php"
                        class="menu-item<?php echo ($current_page === 'upgradeunitreport.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">
                        • Upgrade Unit Report
                    </a>
                    <?php
                endif; ?>

                <?php if (!in_array('Receive Direct Delivery', $sidebar_hidden)): ?>
                    <a href="rddeliveryreport.php"
                        class="menu-item<?php echo ($current_page === 'rddeliveryreport.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">
                        • RD Delivery Report
                    </a>
                    <?php
                endif; ?>

                <?php if (!in_array('Stock Transfer Report', $sidebar_hidden)): ?>
                    <a href="stocktransferreport.php"
                        class="menu-item<?php echo ($current_page === 'stocktransferreport.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">
                        • Stock Transfer Report
                    </a>
                    <?php
                endif; ?>

                <?php if (!in_array('Refund Report', $sidebar_hidden)): ?>
                    <a href="refundreport.php"
                        class="menu-item<?php echo ($current_page === 'refundreport.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">
                        • Refund Report
                    </a>
                    <?php
                endif; ?>

                <?php if (!in_array('Item Status Report', $sidebar_hidden)): ?>
                    <a href="report-itemstatus.php"
                        class="menu-item<?php echo ($current_page === 'report-itemstatus.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">
                        • Item Status Report
                    </a>
                    <?php
                endif; ?>
            </div>
        </div>
        <?php
    endif; ?>

    <?php
    $booklet_labels = ['Booklet Inventory', 'Cancel Inventory', 'Cancel Booklet'];
    $booklet_visible = false;
    foreach ($booklet_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $booklet_visible = true;
            break;
        }
    }
    ?>

    <?php if ($booklet_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Booklet</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">
                <?php if (!in_array('Booklet Inventory', $sidebar_hidden)): ?>
                    <a href="bookletinv.php"
                        class="menu-item<?php echo ($current_page === 'bookletinv.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Booklet Inventory</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Cancel Inventory', $sidebar_hidden)): ?>
                    <a href="bookletinvlive.php"
                        class="menu-item<?php echo ($current_page === 'bookletinvlive.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Cancel Inventory</a>
                    <?php
                endif; ?>
            </div>
        </div>
        <?php
    endif; ?>


    <?php
    $user_reg_labels = ['Account Registration', 'User Activation', 'Position Registration', 'Sidebar Per Account'];
    $user_reg_visible = false;
    foreach ($user_reg_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $user_reg_visible = true;
            break;
        }
    }

    $location_reg_labels = ['Promoter Registration', 'Area Registration', 'Branch Registration', 'Dealer Registration'];
    $location_reg_visible = false;
    foreach ($location_reg_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $location_reg_visible = true;
            break;
        }
    }
    // Force visible for Area Manager if they have access to Promoter Registration
    if ($is_area_manager && !in_array('Promoter Registration', $sidebar_hidden))
        $location_reg_visible = true;

    $item_reg_labels = ['Supplier Registration', 'Brand Registration', 'Family Code Registration', 'Department Registration', 'Group Registration', 'Item Registration', 'Bank Registration', 'Booklet Number Registration', 'Promoreg Registration'];
    $item_reg_visible = false;
    foreach ($item_reg_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $item_reg_visible = true;
            break;
        }
    }

    $term_labels = ['Terminal Issuer Registration', 'Terminal ID Registration'];
    $term_visible = false;
    foreach ($term_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $term_visible = true;
            break;
        }
    }

    $history_labels = ['IMEI History', 'Item History'];
    $history_visible = false; // GLOBALLY HIDDEN
    /* foreach ($history_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $history_visible = true;
            break;
        }
    } */
    ?>

    <?php
    // Check if Registration parent section should be visible
    $registration_visible = $user_reg_visible || $location_reg_visible;
    ?>

    <?php if ($registration_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Registration</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">

                <?php if ($user_reg_visible): ?>
                    <div class="menu-section nested-section collapsed" style="margin-left: 0;">
                        <div class="menu-section-title" onclick="toggleNestedSection(this)" style="padding-left: 20px;">
                            <span>User Registration</span>
                            <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                                <path d="M7 10l5 5 5-5z" />
                            </svg>
                        </div>
                        <div class="submenu">
                            <?php if (!in_array('Account Registration', $sidebar_hidden) && (strcasecmp($user_system_level, 'Super-Admin') === 0 || strcasecmp($user_system_level, 'Sub-admin') === 0)): ?>
                                <a href="accountregistration.php"
                                    class="menu-item<?php echo ($current_page === 'accountregistration.php') ? ' active' : ''; ?>"
                                    style="text-decoration:none; padding-left: 40px;">• Account Registration</a>
                                <?php
                            endif; ?>
                            <?php if (!in_array('User Activation', $sidebar_hidden) && true): ?>
                                <a href="useractivation.php"
                                    class="menu-item<?php echo ($current_page === 'useractivation.php') ? ' active' : ''; ?>"
                                    style="text-decoration:none; padding-left: 40px;">• User Activation</a>
                                <?php
                            endif; ?>
                            <?php if (!in_array('Position Registration', $sidebar_hidden) && true): ?>
                                <a href="position.php"
                                    class="menu-item<?php echo ($current_page === 'position.php') ? ' active' : ''; ?>"
                                    style="text-decoration:none; padding-left: 40px;">• Position Registration</a>
                                <?php
                            endif; ?>
                            <?php if (!in_array('Sidebar Per Account', $sidebar_hidden) && true): ?>
                                <a href="sidebarperacc.php"
                                    class="menu-item<?php echo ($current_page === 'sidebarperacc.php') ? ' active' : ''; ?>"
                                    style="text-decoration:none; padding-left: 40px;">• Sidebar Per Account</a>
                                <?php
                            endif; ?>
                        </div>
                    </div>
                    <?php
                endif; ?>

                <?php if ($location_reg_visible): ?>
                    <div class="menu-section nested-section collapsed" style="margin-left: 0;">
                        <div class="menu-section-title" onclick="toggleNestedSection(this)" style="padding-left: 20px;">
                            <span>Store Registration</span>
                            <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                                <path d="M7 10l5 5 5-5z" />
                            </svg>
                        </div>
                        <div class="submenu">
                            <?php if (!in_array('Promoter Registration', $sidebar_hidden) || $is_area_manager): ?>
                                <a href="promotereg.php"
                                    class="menu-item<?php echo ($current_page === 'promotereg.php') ? ' active' : ''; ?>"
                                    style="text-decoration:none; padding-left: 40px;">• Promoter Registration</a>
                                <?php
                            endif; ?>
                            <?php if (!in_array('Area Registration', $sidebar_hidden)): ?>
                                <a href="areareg.php"
                                    class="menu-item<?php echo ($current_page === 'areareg.php') ? ' active' : ''; ?>"
                                    style="text-decoration:none; padding-left: 40px;">• Area Registration</a>
                                <?php
                            endif; ?>
                            <?php if (!in_array('Branch Registration', $sidebar_hidden) && true): ?>
                                <a href="branchregistration.php"
                                    class="menu-item<?php echo ($current_page === 'branchregistration.php') ? ' active' : ''; ?>"
                                    style="text-decoration:none; padding-left: 40px;">• Branch Registration</a>
                                <?php
                            endif; ?>
                            <?php if (!in_array('Dealer Registration', $sidebar_hidden) && true): ?>
                                <a href="dealerregistration.php"
                                    class="menu-item<?php echo ($current_page === 'dealerregistration.php') ? ' active' : ''; ?>"
                                    style="text-decoration:none; padding-left: 40px;">• Dealer Registration</a>
                                <?php
                            endif; ?>
                            <?php if (false && !in_array('Booklet Number Registration', $sidebar_hidden) && true): ?>
                                <a href="bookletnoreg.php"
                                    class="menu-item<?php echo ($current_page === 'bookletnoreg.php') ? ' active' : ''; ?>"
                                    style="text-decoration:none; padding-left: 40px;">• Booklet Registration</a>
                                <?php
                            endif; ?>
                        </div>
                    </div>
                    <?php
                endif; ?>

            </div>
        </div>
        <?php
    endif; ?>

    <?php if ($item_reg_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Item Registration</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">
                <?php if (!in_array('Supplier Registration', $sidebar_hidden) && true): ?>
                    <a href="supplierreg.php"
                        class="menu-item<?php echo ($current_page === 'supplierreg.php') ? ' active' : ''; ?>"
                        style="text-decoration: none;">• Supplier Registration</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Brand Registration', $sidebar_hidden) && true): ?>
                    <a href="brandreg.php" class="menu-item<?php echo ($current_page === 'brandreg.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Brand Registration</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Family Code Registration', $sidebar_hidden) && true): ?>
                    <a href="familycodereg.php"
                        class="menu-item<?php echo ($current_page === 'familycodereg.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Family Code Registration</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Department Registration', $sidebar_hidden) && true): ?>
                    <a href="departmentreg.php"
                        class="menu-item<?php echo ($current_page === 'departmentreg.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Department Registration</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Group Registration', $sidebar_hidden) && true): ?>
                    <a href="groupreg.php" class="menu-item<?php echo ($current_page === 'groupreg.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Group Registration</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Bank Registration', $sidebar_hidden) && true): ?>
                    <a href="bankreg.php" class="menu-item<?php echo ($current_page === 'bankreg.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Bank Registration</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Booklet Number Registration', $sidebar_hidden) && true): ?>
                    <a href="bookletnoreg.php"
                        class="menu-item<?php echo ($current_page === 'bookletnoreg.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Booklet No Registration</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Promoreg Registration', $sidebar_hidden) && true): ?>
                    <a href="promoreg.php" class="menu-item<?php echo ($current_page === 'promoreg.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Promoreg Registration</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Item Registration', $sidebar_hidden) && true): ?>
                    <a href="itemreg.php" class="menu-item<?php echo ($current_page === 'itemreg.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Item Registration</a>
                    <?php
                endif; ?>
            </div>
        </div>
        <?php
    endif; ?>

    <?php if ($term_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Terminal Registration</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">
                <?php if (!in_array('Terminal Issuer Registration', $sidebar_hidden) && true): ?>
                    <a href="createterminal.php"
                        class="menu-item<?php echo ($current_page === 'createterminal.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Terminal Issuer Registration</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Terminal ID Registration', $sidebar_hidden) && true): ?>
                    <a href="createterminalid.php"
                        class="menu-item<?php echo ($current_page === 'createterminalid.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Terminal ID Registration</a>
                    <?php
                endif; ?>
            </div>
        </div>





        <?php
    endif; ?>

    <?php if ($history_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>History</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">
                <?php if (!in_array('IMEI History', $sidebar_hidden) && true): ?>
                    <a href="javascript:void(0);"
                        onclick="var w=480,h=180,left=(screen.width-w)/2,top=(screen.height-h)/2;window.open('historyimei.php', '_blank', 'width='+w+',height='+h+',left='+left+',top='+top+',scrollbars=no,resizable=yes')"
                        class="menu-item<?php echo ($current_page === 'historyimei.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• IMEI</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Item History', $sidebar_hidden) && true): ?>
                    <a href="javascript:void(0);"
                        onclick="var w=800,h=180,left=(screen.width-w)/2,top=(screen.height-h)/2;window.open('historyitemmodel.php', '_blank', 'width='+w+',height='+h+',left='+left+',top='+top+',scrollbars=yes,resizable=yes')"
                        class="menu-item<?php echo ($current_page === 'historyitemmodel.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• ITEM</a>
                    <?php
                endif; ?>
            </div>
        </div>
        <?php
    endif; ?>

    <?php
    $preorder_labels = ['Pre-order', 'Claim Pre-order'];
    $preorder_visible = false; // GLOBALLY HIDDEN
    /* foreach ($preorder_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $preorder_visible = true;
            break;
        }
    } */
    ?>

    <?php if ($preorder_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Pre-order</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">
                <?php if (!in_array('Pre-order', $sidebar_hidden)): ?>
                    <a href="preorder.php" class="menu-item<?php echo ($current_page === 'preorder.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Pre-order</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Claim Pre-order', $sidebar_hidden)): ?>
                    <a href="claimpreorder.php"
                        class="menu-item<?php echo ($current_page === 'claimpreorder.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Claim Pre-order</a>
                    <?php
                endif; ?>
            </div>
        </div>
        <?php
    endif; ?>

    <?php
    $stock_transfer_labels = ['Transfer Approval', 'Cancel Invoice Approval', 'Item Status Approval'];
    $stock_transfer_visible = false;
    foreach ($stock_transfer_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $stock_transfer_visible = true;
            break;
        }
    }
    ?>

    <?php if ($stock_transfer_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Approval Process</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">
                <?php if (!in_array('Transfer Approval', $sidebar_hidden)): ?>
                    <a href="transferapproval.php"
                        class="menu-item<?php echo ($current_page === 'transferapproval.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Transfer Approval</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Cancel Invoice Approval', $sidebar_hidden)): ?>
                    <a href="salesskipapproval.php"
                        class="menu-item<?php echo ($current_page === 'salesskipapproval.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Cancel Invoice Approval</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Item Status Approval', $sidebar_hidden)): ?>
                    <a href="approval-itemstatus.php"
                        class="menu-item<?php echo ($current_page === 'approval-itemstatus.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Item Status Approval</a>
                    <?php
                endif; ?>
            </div>
        </div>
        <?php
    endif; ?>

    <?php
    $receive_process_labels = ['Receive Purchase Order', 'Receive Stock Transfer'];
    $receive_process_visible = false;
    foreach ($receive_process_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $receive_process_visible = true;
            break;
        }
    }
    ?>

    <?php if ($receive_process_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Receive Process</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">
                <?php if (!in_array('Receive Purchase Order', $sidebar_hidden)): ?>
                    <a href="purchaseorderreceive.php"
                        class="menu-item<?php echo in_array($current_page, $receive_purchase_order_pages) ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Receive Purchase Order</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Receive Stock Transfer', $sidebar_hidden)): ?>
                    <a href="receivestocktransfer.php"
                        class="menu-item<?php echo ($current_page === 'receivestocktransfer.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Receive Stock Transfer</a>
                    <?php
                endif; ?>
            </div>
        </div>
        <?php
    endif; ?>

    <?php
    $void_process_labels = ['Void Sales'];
    $void_process_visible = false;
    foreach ($void_process_labels as $label) {
        if (!in_array($label, $sidebar_hidden)) {
            $void_process_visible = true;
            break;
        }
    }
    ?>

    <?php if ($void_process_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Void Process</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">
                <?php if (!in_array('Void Sales', $sidebar_hidden)): ?>
                    <a href="voidsales.php" class="menu-item<?php echo ($current_page === 'voidsales.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Void Sales</a>
                    <?php
                endif; ?>
            </div>
        </div>
        <?php
    endif; ?>

    <?php
    // Sub admin menu section - ONLY for Sub-admin users
    $subadmin_labels = ['Late Entry', 'Modification Sales', 'Modification Upgrade', 'Modification Refund', 'Modification Claim Pre-Orders', 'Modification Pre-orders'];
    $subadmin_visible = false;
    // Only show for Sub-admin users
    if (strcasecmp($user_system_level, 'Sub-admin') === 0) {
        foreach ($subadmin_labels as $label) {
            if (!in_array($label, $sidebar_hidden)) {
                $subadmin_visible = true;
                break;
            }
        }
    }
    ?>

    <?php if ($subadmin_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Admin Level</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">
                <?php if (!in_array('Late Entry', $sidebar_hidden)): ?>
                    <a href="salesentrylate.php"
                        class="menu-item<?php echo ($current_page === 'salesentrylate.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Late Entry</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Modification Sales', $sidebar_hidden)): ?>
                    <a href="modification-motogam.php"
                        class="menu-item<?php echo ($current_page === 'modification-motogam.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Modification Sales</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Modification Upgrade', $sidebar_hidden)): ?>
                    <a href="modification-upgrade.php"
                        class="menu-item<?php echo ($current_page === 'modification-upgrade.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Modification Upgrade</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Modification Refund', $sidebar_hidden)): ?>
                    <a href="modification-refund.php"
                        class="menu-item<?php echo ($current_page === 'modification-refund.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Modification Refund</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Modification Claim Pre-Orders', $sidebar_hidden)): ?>
                    <a href="modification-claim-preorder.php"
                        class="menu-item<?php echo ($current_page === 'modification-claim-preorder.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Modification Claim Pre-Orders</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Modification Pre-orders', $sidebar_hidden)): ?>
                    <a href="modification-preorders.php"
                        class="menu-item<?php echo ($current_page === 'modification-preorders.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Modification Pre-orders</a>
                    <?php
                endif; ?>
            </div>
        </div>
        <?php
    endif; ?>

    <?php
    // Super Admin menu section - ONLY for Super-Admin users
    $superadmin_labels = ['Modification Sales', 'Modification Claim Item', 'Modification Upgrade', 'Modification Refund', 'Modification Claim Pre-Orders', 'Modification Pre-orders', 'Revert Void Sales'];
    $superadmin_visible = false;
    // Only show for Super-Admin users
    if (strcasecmp($user_system_level, 'Super-Admin') === 0) {
        foreach ($superadmin_labels as $label) {
            if (!in_array($label, $sidebar_hidden)) {
                $superadmin_visible = true;
                break;
            }
        }
    }
    ?>

    <?php if ($superadmin_visible): ?>
        <div class="menu-section collapsed">
            <div class="menu-section-title" onclick="toggleSection(this)">
                <span>Super Admin Level</span>
                <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </div>
            <div class="submenu">
                <?php if (!in_array('Modification Sales', $sidebar_hidden)): ?>
                    <a href="modification-motogam.php"
                        class="menu-item<?php echo ($current_page === 'modification-motogam.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Modification Sales</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Modification Claim Item', $sidebar_hidden)): ?>
                    <a href="modification-claimitem.php"
                        class="menu-item<?php echo ($current_page === 'modification-claimitem.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Modification Claim Item</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Modification Upgrade', $sidebar_hidden)): ?>
                    <a href="modification-upgrade.php"
                        class="menu-item<?php echo ($current_page === 'modification-upgrade.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Modification Upgrade</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Modification Refund', $sidebar_hidden)): ?>
                    <a href="modification-refund.php"
                        class="menu-item<?php echo ($current_page === 'modification-refund.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Modification Refund</a>
                    <?php   
                endif; ?>
                <?php if (!in_array('Modification Claim Pre-Orders', $sidebar_hidden)): ?>
                    <a href="modification-claim-preorder.php"
                        class="menu-item<?php echo ($current_page === 'modification-claim-preorder.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Modification Claim Pre-Orders</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Modification Pre-orders', $sidebar_hidden)): ?>
                    <a href="modification-preorders.php"
                        class="menu-item<?php echo ($current_page === 'modification-preorders.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Modification Pre-orders</a>
                    <?php
                endif; ?>
                <?php if (!in_array('Revert Void Sales', $sidebar_hidden)): ?>
                    <a href="revert-voidsales.php"
                        class="menu-item<?php echo ($current_page === 'revert-voidsales.php') ? ' active' : ''; ?>"
                        style="text-decoration:none;">• Revert Void Sales</a>
                    <?php
                endif; ?>
            </div>
        </div>
        <?php
    endif; ?>


    <a href="logout.php" class="menu-item<?php echo ($current_page === 'logout.php') ? ' active' : ''; ?>"
        style="text-decoration:none;">
        <svg viewBox="0 0 24 24">
            <path
                d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z" />
        </svg>
        Logout
    </a>
</div>

<style>
    /* Make sidebar responsive with scrollbar */
    .sidebar {
        position: fixed;
        left: 0;
        top: 60px;
        width: 250px;
        height: calc(130vh - 60px);
        background-color: white;
        box-shadow: 2px 0 4px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 20px 0;
        z-index: 1000;
    }

    /* Scrollbar styling for better appearance */
    .sidebar::-webkit-scrollbar {
        width: 8px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            z-index: 1500;
        }

        .sidebar.active {
            transform: translateX(0);
        }
    }

    @media (max-width: 480px) {
        .sidebar {
            width: 80%;
            max-width: 250px;
        }
    }
</style>

<script>
    // Block Alt+Left Click on all sidebar links to prevent code download
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.addEventListener('click', function (e) {
                // Check if Alt key is pressed during click
                if (e.altKey) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }, true);

            // Also block on mousedown for extra safety
            sidebar.addEventListener('mousedown', function (e) {
                if (e.altKey) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }, true);
        }

        // Restore sidebar state from localStorage
        restoreSidebarState();
    });

    // Toggle section - accordion behavior (only one section open at a time)
    // This will be overridden by individual pages, but they'll call saveSidebarState()
    function toggleSection(element) {
        const section = element.parentElement;
        const isCurrentlyCollapsed = section.classList.contains('collapsed');

        // Close all other sections (but not nested sections)
        const allSections = document.querySelectorAll('.sidebar > .menu-section');
        allSections.forEach(function (s) {
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

        saveSidebarState();
    }

    // Toggle nested section - accordion behavior within parent section
    function toggleNestedSection(element) {
        const section = element.parentElement;
        const isCurrentlyCollapsed = section.classList.contains('collapsed');
        const parentSection = section.closest('.menu-section:not(.nested-section)');

        // Close all other nested sections within the same parent
        if (parentSection) {
            const siblingNestedSections = parentSection.querySelectorAll('.nested-section');
            siblingNestedSections.forEach(function (s) {
                if (s !== section) {
                    s.classList.add('collapsed');
                }
            });
        }

        // Toggle the clicked nested section
        if (isCurrentlyCollapsed) {
            section.classList.remove('collapsed');
        } else {
            section.classList.add('collapsed');
        }

        saveSidebarState();
    }
    // Save sidebar state to localStorage
    function saveSidebarState() {
        const sections = document.querySelectorAll('.menu-section');
        const state = {};

        sections.forEach(function (section, index) {
            const title = section.querySelector('.menu-section-title span');
            if (title) {
                const sectionName = title.textContent.trim();
                state[sectionName] = !section.classList.contains('collapsed');
            }
        });

        localStorage.setItem('sidebarState', JSON.stringify(state));
    }

    // Restore sidebar state from localStorage
    function restoreSidebarState() {
        const savedState = localStorage.getItem('sidebarState');
        if (!savedState) return;

        try {
            const state = JSON.parse(savedState);
            const sections = document.querySelectorAll('.menu-section');

            sections.forEach(function (section) {
                const title = section.querySelector('.menu-section-title span');
                if (title) {
                    const sectionName = title.textContent.trim();
                    if (state[sectionName] === true) {
                        // Remove collapsed class to open the section
                        section.classList.remove('collapsed');
                    } else if (state[sectionName] === false) {
                        // Add collapsed class to close the section
                        section.classList.add('collapsed');
                    }
                }
            });
        } catch (e) {
            console.error('Error restoring sidebar state:', e);
        }
    }
</script>