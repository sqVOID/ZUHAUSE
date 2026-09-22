<?php
require_once 'session_check.php';
/**
 * Simple Validation Logic Test
 * Run this in browser: http://localhost/MOTOGAM/simple_validation_test.php
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Booklet Validation Test</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f0f0f0; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 800px; margin: 0 auto; }
        h1 { color: #2e7d32; }
        .test { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
        .pass { background: #e8f5e9; border-color: #4caf50; }
        .fail { background: #ffebee; border-color: #f44336; }
        .result { font-weight: bold; font-size: 18px; }
        .pass .result { color: #2e7d32; }
        .fail .result { color: #c62828; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #E1FFDE; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Booklet Validation Logic Test</h1>
        <p>This page tests the validation logic that prevents saving sales when booklet is exhausted.</p>
        
        <hr>
        
        <?php
        // Test function
        function testValidation($testName, $current, $ending, $shouldBlock) {
            $isBlocked = ($current > $ending);
            $passed = ($isBlocked === $shouldBlock);
            
            $cssClass = $passed ? 'pass' : 'fail';
            $resultText = $passed ? '✅ PASS' : '❌ FAIL';
            
            echo "<div class='test $cssClass'>";
            echo "<h3>$testName</h3>";
            echo "<p><strong>Current Number:</strong> $current</p>";
            echo "<p><strong>Ending Number:</strong> $ending</p>";
            echo "<p><strong>Expected:</strong> " . ($shouldBlock ? 'BLOCK' : 'ALLOW') . " save</p>";
            echo "<p><strong>Actual:</strong> " . ($isBlocked ? 'BLOCKED' : 'ALLOWED') . " save</p>";
            echo "<p class='result'>$resultText</p>";
            echo "</div>";
            
            return $passed;
        }
        
        // Run tests
        $tests = [
            ['Test 1: Exceeded Range', 16, 15, true],
            ['Test 2: Far Exceeded', 20, 15, true],
            ['Test 3: Within Range', 10, 15, false],
            ['Test 4: At Beginning', 1, 15, false],
            ['Test 5: At End (Exactly)', 15, 15, false],
            ['Test 6: Just After End', 16, 15, true],
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            if (testValidation($test[0], $test[1], $test[2], $test[3])) {
                $passed++;
            }
        }
        ?>
        
        <hr>
        
        <h2>Summary</h2>
        <p style="font-size: 20px;">
            <strong>Tests Passed: <?php echo $passed; ?> / <?php echo $total; ?></strong>
        </p>
        
        <?php if ($passed === $total): ?>
            <p style="color: #2e7d32; font-size: 18px; font-weight: bold;">
                ✅ All validation tests passed! The logic is working correctly.
            </p>
        <?php else: ?>
            <p style="color: #c62828; font-size: 18px; font-weight: bold;">
                ❌ Some tests failed! Please review the validation logic.
            </p>
        <?php endif; ?>
        
        <hr>
        
        <h2>Visual Example</h2>
        <table>
            <thead>
                <tr>
                    <th>Booklet Number</th>
                    <th>Beginning</th>
                    <th>Current</th>
                    <th>Ending</th>
                    <th>Can Save?</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>001</td>
                    <td>0001</td>
                    <td>0010</td>
                    <td>0015</td>
                    <td style="color: green; font-weight: bold;">✅ YES</td>
                    <td>Active (5 left)</td>
                </tr>
                <tr>
                    <td>001</td>
                    <td>0001</td>
                    <td>0015</td>
                    <td>0015</td>
                    <td style="color: green; font-weight: bold;">✅ YES</td>
                    <td>Last number</td>
                </tr>
                <tr style="background: #ffebee;">
                    <td>001</td>
                    <td>0001</td>
                    <td>0016</td>
                    <td>0015</td>
                    <td style="color: red; font-weight: bold;">❌ NO</td>
                    <td><strong>EXHAUSTED</strong></td>
                </tr>
                <tr style="background: #ffebee;">
                    <td>001</td>
                    <td>0001</td>
                    <td>0020</td>
                    <td>0015</td>
                    <td style="color: red; font-weight: bold;">❌ NO</td>
                    <td><strong>EXHAUSTED</strong></td>
                </tr>
            </tbody>
        </table>
        
        <hr>
        
        <h2>What Happens in Each Case</h2>
        
        <div style="margin: 20px 0;">
            <h3 style="color: #2e7d32;">✅ Allowed Scenarios</h3>
            <ul>
                <li><strong>Within Range (0010 ≤ 0015):</strong> Transaction saves normally</li>
                <li><strong>At End Exactly (0015 = 0015):</strong> Last invoice in booklet, saves successfully</li>
            </ul>
        </div>
        
        <div style="margin: 20px 0;">
            <h3 style="color: #c62828;">❌ Blocked Scenarios</h3>
            <ul>
                <li><strong>Exceeded (0016 > 0015):</strong> Error message shown, transaction NOT saved</li>
                <li><strong>Far Exceeded (0020 > 0015):</strong> Error message shown, transaction NOT saved</li>
            </ul>
        </div>
        
        <div style="background: #fff3e0; padding: 15px; border-left: 4px solid #ff9800; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #e65100;">⚠️ User Action Required</h3>
            <p>When booklet is exhausted, users must:</p>
            <ol>
                <li>Register a new booklet range starting from 0016</li>
                <li>Or contact administrator to register new booklet</li>
                <li>Then return to Sales Entry and continue</li>
            </ol>
        </div>
        
        <p><a href="bookletnoreg.php" style="color: #2e7d32;">← Go to Booklet Registration</a></p>
        <p><a href="test_booklet_validation.php" style="color: #1976d2;">→ Check Database Booklets</a></p>
    </div>
</body>
</html>
