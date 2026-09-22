$files = @(
    "c:/xampp/htdocs/IMS/voidsales.php",
    "c:/xampp/htdocs/IMS/useractivation.php",
    "c:/xampp/htdocs/IMS/upgradeunit.php",
    "c:/xampp/htdocs/IMS/stocktransfer.php",
    "c:/xampp/htdocs/IMS/salesentry.php",
    "c:/xampp/htdocs/IMS/replacementunit.php",
    "c:/xampp/htdocs/IMS/refund.php",
    "c:/xampp/htdocs/IMS/rddelivery.php",
    "c:/xampp/htdocs/IMS/purchaseorder.php",
    "c:/xampp/htdocs/IMS/promotereg.php",
    "c:/xampp/htdocs/IMS/itemreg.php",
    "c:/xampp/htdocs/IMS/groupreg.php",
    "c:/xampp/htdocs/IMS/familycodereg.php",
    "c:/xampp/htdocs/IMS/dsentry.php",
    "c:/xampp/htdocs/IMS/departmentreg.php",
    "c:/xampp/htdocs/IMS/dealerregistration.php",
    "c:/xampp/htdocs/IMS/claimitem.php",
    "c:/xampp/htdocs/IMS/brandReg.php",
    "c:/xampp/htdocs/IMS/branchregistration.php"
)

$oldPattern = @"
                <?php if (strcasecmp(`$user_system_level, 'Sub-admin') !== 0): ?>
                <a href="accountregistration.php" class="menu-item
"@

$newPattern = @"
                <!-- Show Account Registration for both Super-Admin and Sub-admin -->
                <a href="accountregistration.php" class="menu-item
"@

foreach ($file in $files) {
    if (Test-Path $file) {
        $content = Get-Content $file -Raw
        
        # Replace the pattern
        $content = $content -replace [regex]::Escape($oldPattern), $newPattern
        
        # Remove the <?php endif; ?> line after Account Registration
        $content = $content -replace "(?m)^\s*<a href=`"accountregistration\.php`".*?>\s*\r?\n\s*<\?php endif; \?>\s*\r?\n", "<a href=`"accountregistration.php`" class=`"menu-item`" style=`"text-decoration: none;`">Account Registration</a>`r`n"
        
        Set-Content $file -Value $content -NoNewline
        Write-Host "Updated: $file"
    } else {
        Write-Host "File not found: $file" -ForegroundColor Yellow
    }
}

Write-Host "`nAll files updated successfully!" -ForegroundColor Green
