$files = Get-ChildItem -Path "c:\xampp\htdocs\MOTOGAM" -Filter "*.php"
$pattern = '(?s)<div class="sidebar">\s*<\?php[\s\S]*?<a href="logout\.php"[^>]*>[\s\S]*?</a>\s*</div>'

foreach ($file in $files) {
    if ($file.Name -match '^_') { continue }
    
    $content = Get-Content $file.FullName -Raw
    $original = $content
    
    # 1. Replace the giant hardcoded sidebar block
    if ($content -match $pattern) {
        $content = [regex]::Replace($content, $pattern, "<?php include '_sidebar.php'; ?>")
    }
    
    # 2. Replace any references to _sohand_sidebar.php with _sidebar.php
    if ($content -match '_sohand_sidebar\.php') {
        $content = $content -replace '_sohand_sidebar\.php', '_sidebar.php'
    }
    
    if ($content -ne $original) {
        Set-Content $file.FullName -Value $content -NoNewline
        Write-Host "Updated $($file.Name)"
    }
}
Write-Host "Done"
