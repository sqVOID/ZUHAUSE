<?php
$dir = 'c:/xampp/htdocs/MOTOGAM';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

foreach ($files as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    if (strpos($path, 'node_modules') !== false || strpos($path, '.git') !== false || strpos($path, 'scratch') !== false) continue;
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if ($ext === 'php' || $ext === 'js' || $ext === 'html') {
        $content = file_get_contents($path);
        if (stripos($content, 'Invoice Details') !== false) {
            echo "Found 'Invoice Details' in: " . $path . "\n";
        }
    }
}
?>
