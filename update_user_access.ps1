$files = Get-ChildItem -Path "c:\xampp\htdocs\MOTOGAM" -Filter "*.php"
$pattern = 'strcasecmp($user_system_level, ''User'') !== 0'

foreach ($file in $files) {
    if (Test-Path $file.FullName) {
        $content = Get-Content $file.FullName -Raw
        
        if ($content.Contains($pattern)) {
            $content = $content.Replace($pattern, 'true')
            Set-Content $file.FullName -Value $content -NoNewline
            Write-Host "Updated: $($file.Name)"
        }
    }
}
Write-Host "Done"
