<?php
// -- Shared sidebar for sohandunit / sohandimei / sohandaccessories --
$sidebar_hidden = [];
if (isset($_SESSION['sidebar_access'])) {
    $sidebar_hidden = explode(',', $_SESSION['sidebar_access']);
}
$user_system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
$user_position = isset($_SESSION['user_position']) ? trim($_SESSION['user_position']) : '';
$is_area_manager = (stripos($user_position, 'Area Manager') !== false);

// Get current page name for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <?php if (!in_array('Report', $sidebar_hidden)): ?>
    <a href="report.php" class="menu-item" style="text-decoration:none;">
        <svg viewBox="0 0 24 24"><path d="M9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4zm2.5 2.1h-15V5h15v14.1zm0-16.1h-15c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h15c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
        Report
    </a>
    <?php
endif; ?>

    <?php if (true || $is_area_manager): ?>
    <div class="menu-section collapsed">
        <div class="menu-section-title" onclick="toggleSection(this)">
            <span>Registration</span>
            <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M7 10l5 5 5-5z"/></svg>
        </div>
        <div class="submenu">
            <?php if (!in_array('Account Registration', $sidebar_hidden) && true): ?>
            <a href="accountregistration.php" class="menu-item" style="text-decoration:none;">Account Registration</a>
            <?php
    endif; ?>
            <?php if (!in_array('User Activation', $sidebar_hidden) && true): ?>
            <a href="useractivation.php" class="menu-item" style="text-decoration:none;">User Activation</a>
            <?php
    endif; ?>
            <?php if (true): ?>
            <a href="position.php" class="menu-item" style="text-decoration:none;">Position Registration</a>
            <?php
    endif; ?>
            <?php if (!in_array('Promoter Registration', $sidebar_hidden) || $is_area_manager): ?>
            <a href="promotereg.php" class="menu-item" style="text-decoration:none;">Promoter Registration</a>
            <?php
    endif; ?>
            <?php if (!in_array('Branch Registration', $sidebar_hidden) && true): ?>
            <a href="branchregistration.php" class="menu-item" style="text-decoration:none;">Branch Registration</a>
            <?php
    endif; ?>
             <?php if (!in_array('Supplier Registration', $sidebar_hidden) && true): ?>
                <a href="supplierreg.php" class="menu-item" style="text-decoration: none;">Supplier Registration</a>
                <?php
    endif; ?>
            <?php if (!in_array('Brand Registration', $sidebar_hidden) && true): ?>
            <a href="brandreg.php" class="menu-item" style="text-decoration:none;">Brand Registration</a>
            <?php
    endif; ?>
            <?php if (!in_array('Family Code Registration', $sidebar_hidden) && true): ?>
            <a href="familycodereg.php" class="menu-item" style="text-decoration:none;">Family Code Registration</a>
            <?php
    endif; ?>
            <?php if (!in_array('Department Registration', $sidebar_hidden) && true): ?>
            <a href="departmentreg.php" class="menu-item" style="text-decoration:none;">Department Registration</a>
            <?php
    endif; ?>
            <?php if (!in_array('Group Registration', $sidebar_hidden) && true): ?>
            <a href="groupreg.php" class="menu-item" style="text-decoration:none;">Group Registration</a>
            <?php
    endif; ?>
            <?php if (!in_array('Item Registration', $sidebar_hidden) && true): ?>
            <a href="itemreg.php" class="menu-item" style="text-decoration:none;">Item Registration</a>
            <?php
    endif; ?>
        </div>
    </div>

    <div class="menu-section collapsed">
        <div class="menu-section-title" onclick="toggleSection(this)">
            <span>Terminal Registration</span>
            <svg class="arrow" viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M7 10l5 5 5-5z"/></svg>
        </div>
        <div class="submenu">
            <?php if (!in_array('Terminal Issuer Registration', $sidebar_hidden) && true): ?>
            <a href="createterminal.php" class="menu-item" style="text-decoration:none;">Terminal Issuer Registration</a>
            <?php
    endif; ?>
            <?php if (!in_array('Terminal ID Registration', $sidebar_hidden) && true): ?>
            <a href="createterminalid.php" class="menu-item" style="text-decoration:none;">Terminal ID Registration</a>
            <?php
    endif; ?>
        </div>
    </div>


    


    <?php
endif; ?>

    <?php if (!in_array('Pre Order', $sidebar_hidden)): ?>
    <a href="preorder.php" class="menu-item" style="text-decoration:none;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M17 12C14.24 12 12 14.24 12 17C12 19.76 14.24 22 17 22C19.76 22 22 19.76 22 17C22 14.24 19.76 12 17 12ZM18.65 19.35L16.5 17.2V14H17.5V16.79L19.35 18.64L18.65 19.35ZM18 3H14.82C14.4 1.84 13.3 1 12 1C10.7 1 9.6 1.84 9.18 3H6C4.9 3 4 3.9 4 5V20C4 21.1 4.9 22 6 22H12.11C11.5176 21.4264 11.0362 20.7484 10.69 20H6V5H8V8H16V5H18V10.08C18.71 10.18 19.38 10.39 20 10.68V5C20 3.9 19.1 3 18 3ZM12 5C11.45 5 11 4.55 11 4C11 3.45 11.45 3 12 3C12.55 3 13 3.45 13 4C13 4.55 12.55 5 12 5Z" fill="#757575"/></svg>
        Pre Order
    </a>
    <?php
endif; ?>
    
   <?php if (!in_array('Purchase Order', $sidebar_hidden)): ?>
         <a href="purchaseorder.php" class="menu-item" style="text-decoration: none;">
            <svg width="800px" height="800px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="5" y="4" width="14" height="17" rx="2" stroke="#ffffffff"/>
            <path d="M9 9H15" stroke="#ffffffff" stroke-linecap="round"/>
            <path d="M9 13H15" stroke="#ffffffff" stroke-linecap="round"/>
            <path d="M9 17H13" stroke="#ffffffff" stroke-linecap="round"/>
            </svg>Purchase Order
        </a>   
        <?php
endif; ?>


    <?php if (!in_array('Sales Entry', $sidebar_hidden)): ?>
    <a href="salesentry.php" class="menu-item" style="text-decoration:none;">
        <svg viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
        Sales Entryss
    </a>
    <?php
endif; ?>

    <?php if (!in_array('Void Sales', $sidebar_hidden)): ?>
    <a href="voidsales.php" class="menu-item" style="text-decoration:none;">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        Void Sales
    </a>
    <?php
endif; ?>

               <?php if (!in_array('Void Sales', $sidebar_hidden)): ?>
            <a href="voidsalesreport.php" class="menu-item" style="text-decoration:none;">
                <svg viewBox="0 0 24 24">
                    <path
                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                </svg>
                Void Sales Report
            </a>
        <?php
endif; ?>


    <?php if (!in_array('Stock Transfer', $sidebar_hidden)): ?>
    <a href="stocktransfer.php" class="menu-item" style="text-decoration:none;">
        <svg viewBox="0 0 24 24"><path d="M10 9h4V6h3l-5-5-5 5h3v3zm-1 1H6V7l-5 5 5 5v-3h3v-4zm14 2l-5-5v3h-3v4h3v3l5-5zm-9 3h-4v3H7l5 5 5-5h-3v-3z"/></svg>
        Stock Transfer
    </a>
    <?php
endif; ?>

   <a href="transferapproval.php" class="menu-item" style="text-decoration: none;">
            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            Transfer Approval
    </a>

      <a href="receivestocktransfer.php" class="menu-item" style="text-decoration: none;">
            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zm-7-2l-4-4h3V8h2v5h3l-4 4z"/></svg>
            Receive Stock Transfer
    </a>

    <?php if (!in_array('Upgrade Unit', $sidebar_hidden)): ?>
    <a href="upgradeunit.php" class="menu-item" style="text-decoration:none;">
        <svg viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
        Upgrade Unit
    </a>
    <?php
endif; ?>

  <?php if (!in_array('Upgrade Unit Report', $sidebar_hidden)): ?>
    <a href="upgradeunitreport.php" class="menu-item<?php echo($current_page === 'upgradeunitreport.php') ? ' active' : ''; ?>" style="text-decoration:none;">
        <svg viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
        Upgrade Unit Report
    </a>
    <?php
endif; ?>



    <?php if (!in_array('Refund', $sidebar_hidden)): ?>
    <a href="refund.php" class="menu-item" style="text-decoration:none;">
        <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
        Refund
    </a>
    <?php
endif; ?>

    <?php if (!in_array('Receive Direct Delivery', $sidebar_hidden)): ?>
            <a href="rddeliveryreport.php" class="menu-item" style="text-decoration:none;">
                <svg viewBox="0 0 24 24">
                    <path
                        d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z" />
                </svg>
                RD Delivery Report
            </a>
        <?php
endif; ?>


    <?php if (!in_array('Claim Item', $sidebar_hidden)): ?>
    <a href="claimitem.php" class="menu-item" style="text-decoration:none;">
        <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
        Claim Item
    </a>
    <?php
endif; ?>

    <?php if (!in_array('Stock on Hand', $sidebar_hidden)): ?>
    <a href="sohandunit.php" class="menu-item<?php echo($current_page === 'sohandunit.php') ? ' active' : ''; ?>" style="text-decoration:none;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path fill-rule="evenodd" clip-rule="evenodd" d="M14.179 2.94805L18.749 5.58805C19.4114 5.97048 19.9615 6.52043 20.3442 7.18268C20.7268 7.84494 20.9285 8.5962 20.929 9.36105V14.6391C20.9287 15.4041 20.7271 16.1555 20.3444 16.818C19.9617 17.4804 19.4115 18.0305 18.749 18.4131L14.179 21.0521C13.5164 21.4344 12.7649 21.6356 12 21.6356C11.2351 21.6356 10.4836 21.4344 9.821 21.0521L5.251 18.4121C4.58862 18.0296 4.03849 17.4797 3.65585 16.8174C3.2732 16.1552 3.0715 15.4039 3.071 14.6391V9.36105C3.071 7.80405 3.902 6.36605 5.251 5.58705L9.821 2.94805C10.4836 2.56576 11.2351 2.3645 12 2.3645C12.7649 2.3645 13.5164 2.56576 14.179 2.94805ZM13.179 4.68005C12.8205 4.47329 12.4139 4.36445 12 4.36445C11.5861 4.36445 11.1795 4.47329 10.821 4.68005L6.251 7.32005C5.89279 7.52686 5.59523 7.82418 5.38813 8.18222C5.18103 8.54026 5.07167 8.94644 5.071 9.36005V14.6401C5.071 15.4811 5.521 16.2601 6.251 16.6801L10.821 19.3201C11.551 19.7401 12.449 19.7401 13.179 19.3201L17.749 16.6801C18.1072 16.4732 18.4048 16.1759 18.6119 15.8179C18.819 15.4599 18.9283 15.0537 18.929 14.6401V9.36005C18.929 8.51905 18.479 7.74005 17.749 7.32005L13.179 4.68005Z" fill="#757575"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M10.499 11.796L4.696 8.89396L5.59 7.10596L11.393 10.007C11.775 10.198 12.225 10.198 12.607 10.007L18.41 7.10596L19.305 8.89396L13.502 11.796C13.0357 12.0289 12.5217 12.1501 12.0005 12.1501C11.4793 12.1501 10.9653 12.0289 10.499 11.796Z" fill="#757575"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M13 11.428V20.571H11V11.428H13Z" fill="#757575"/>
</svg>
Stock on Hand
    </a>
    <?php
endif; ?>

    <a href="logout.php" class="menu-item" style="text-decoration:none;">
        <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
        Logout
    </a>
</div>

