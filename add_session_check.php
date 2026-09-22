<?php
/**
 * Script to Add Session Check to All PHP Files
 * 
 * This script will scan all PHP files and add session_check.php
 * to files that don't have it yet
 */

// Files that should NOT have session check
$exclude_files = [
    'config.php',               // Database config
    'session_check.php',        // The check itself
    'login.php',                // Login page (needs to be accessible)
    'logout.php',               // Logout page
    'index.php',                // Landing/redirect page
    'fpdf.php',                 // Third-party library
    'add_session_check.php',    // This script itself
    '_header_user.php',         // Partial include
    '_sidebar.php',             // Partial include
    '_sohand_sidebar.php',      // Partial include
];

// File patterns that should NOT have session check
$exclude_patterns = [
    '/^add_.*\.php$/',           // Database migration scripts (add_*.php)
    '/^setup_.*\.php$/',         // Setup scripts (setup_*.php)
    '/^test_.*\.php$/',          // Test files (test_*.php)
    '/^debug_.*\.php$/',         // Debug files (debug_*.php)
    '/^fix_.*\.php$/',           // Fix scripts (fix_*.php)
    '/^cleanup_.*\.php$/',       // Cleanup scripts (cleanup_*.php)
    '/^clear_.*\.php$/',         // Clear scripts (clear_*.php)
    '/tutorial\//',              // Tutorial folder
    '/scratch\//',               // Scratch folder
    '/^hidden.*\.php$/',         // Hidden scripts
];

// Directories to exclude
$exclude_dirs = ['vendor', 'node_modules', 'tutorial', 'scratch'];

// Get all PHP files recursively
function getPhpFiles($dir, $exclude_dirs) {
    $files = [];
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($path)) {
            if (!in_array($item, $exclude_dirs)) {
                $files = array_merge($files, getPhpFiles($path, $exclude_dirs));
            }
        } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'php') {
            $files[] = $path;
        }
    }
    
    return $files;
}

// Check if file should be excluded
function shouldExclude($filename, $exclude_files, $exclude_patterns) {
    $basename = basename($filename);
    
    // Check exact matches
    if (in_array($basename, $exclude_files)) {
        return true;
    }
    
    // Check patterns
    foreach ($exclude_patterns as $pattern) {
        if (preg_match($pattern, $filename)) {
            return true;
        }
    }
    
    return false;
}

// Check if file already has session check
function hasSessionCheck($filepath) {
    $content = file_get_contents($filepath);
    
    // Look for various forms of session check
    $patterns = [
        "/require_once\s+['\"]session_check\.php['\"]/",
        "/require\s+['\"]session_check\.php['\"]/",
        "/include_once\s+['\"]session_check\.php['\"]/",
        "/include\s+['\"]session_check\.php['\"]/",
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return true;
        }
    }
    
    return false;
}

// Add session check to a file
function addSessionCheck($filepath) {
    $content = file_get_contents($filepath);
    
    // Find the opening PHP tag
    if (preg_match('/^<\?php\s*/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
        $pos = $matches[0][1] + strlen($matches[0][0]);
        
        // Insert session check right after opening PHP tag
        $session_check = "require_once 'session_check.php';\n";
        $new_content = substr($content, 0, $pos) . $session_check . substr($content, $pos);
        
        return $new_content;
    }
    
    return false;
}

// Main execution
echo "=== PHP Session Check Installer ===\n\n";

$root_dir = __DIR__;
$all_files = getPhpFiles($root_dir, $exclude_dirs);

$files_to_update = [];
$files_already_protected = [];
$files_excluded = [];

foreach ($all_files as $file) {
    if (shouldExclude($file, $exclude_files, $exclude_patterns)) {
        $files_excluded[] = $file;
        continue;
    }
    
    if (hasSessionCheck($file)) {
        $files_already_protected[] = $file;
    } else {
        $files_to_update[] = $file;
    }
}

// Display summary
echo "📊 SUMMARY:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Total PHP files found: " . count($all_files) . "\n";
echo "Already protected: " . count($files_already_protected) . "\n";
echo "Need session check: " . count($files_to_update) . "\n";
echo "Excluded (utilities/scripts): " . count($files_excluded) . "\n\n";

if (count($files_to_update) > 0) {
    echo "🔒 FILES THAT NEED SESSION CHECK:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    foreach ($files_to_update as $file) {
        echo "  • " . basename($file) . "\n";
    }
    echo "\n";
    
    echo "Would you like to add session checks to these files? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($response) === 'yes' || strtolower($response) === 'y') {
        $success_count = 0;
        $fail_count = 0;
        
        echo "\n✨ APPLYING CHANGES:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        foreach ($files_to_update as $file) {
            $new_content = addSessionCheck($file);
            if ($new_content !== false) {
                if (file_put_contents($file, $new_content)) {
                    echo "  ✓ " . basename($file) . "\n";
                    $success_count++;
                } else {
                    echo "  ✗ Failed to write: " . basename($file) . "\n";
                    $fail_count++;
                }
            } else {
                echo "  ✗ Could not parse: " . basename($file) . "\n";
                $fail_count++;
            }
        }
        
        echo "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "✅ Successfully updated: $success_count files\n";
        if ($fail_count > 0) {
            echo "❌ Failed to update: $fail_count files\n";
        }
        echo "\n🎉 Session protection has been applied!\n";
        echo "All users must now login before accessing pages.\n";
    } else {
        echo "\n❌ Operation cancelled. No files were modified.\n";
    }
} else {
    echo "✅ All files are already protected!\n";
    echo "No changes needed.\n";
}

echo "\n";
?>
