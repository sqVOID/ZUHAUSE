$files = Get-ChildItem -Path "c:\xampp\htdocs\MOTOGAM" -Filter "*.php"
$pattern = "if (strcasecmp(`$system_level, 'User') === 0) {"

foreach ($file in $files) {
    if (Test-Path $file.FullName) {
        $content = Get-Content $file.FullName -Raw
        
        if ($content.Contains($pattern)) {
            $content = $content.Replace($pattern, 'if (false) {')
            Set-Content $file.FullName -Value $content -NoNewline
            Write-Host "Updated: $($file.Name)"
        }
    }
}
Write-Host "Done"
