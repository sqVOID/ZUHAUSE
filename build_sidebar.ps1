$content = Get-Content "c:\xampp\htdocs\MOTOGAM\_sohand_sidebar.php" -Raw

# 1. Clean up any existing active classes or php echos
$content = [regex]::Replace($content, 'class="menu-item[^"]*"', 'class="menu-item"')

# 2. Add dynamic active class PHP to all links conditionally
$content = [regex]::Replace($content, '<a href="([^"]+)" class="menu-item"', {
    param($m)
    $file = $m.Groups[1].Value
    return '<a href="' + $file + '" class="menu-item<?php echo ($current_page === ''' + $file + ''') ? '' active'' : ''''; ?>"'
})

Set-Content "c:\xampp\htdocs\MOTOGAM\_sidebar.php" -Value $content -NoNewline
Write-Host "Created _sidebar.php"
