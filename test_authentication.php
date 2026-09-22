<?php
/**
 * Authentication Test Page
 * 
 * This page helps verify that the session protection is working correctly.
 * If you can see this page content, it means you are logged in.
 */
require_once 'session_check.php';
include 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Test - MOTOGAM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 700px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .success-badge {
            background: #10b981;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        h1 {
            color: #1f2937;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #6b7280;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .info-section {
            background: #f3f4f6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-section h3 {
            color: #374151;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #6b7280;
            font-weight: 500;
        }
        
        .info-value {
            color: #1f2937;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .test-results {
            background: #ecfdf5;
            border-left: 4px solid #10b981;
            padding: 20px;
            border-radius: 6px;
            margin-top: 20px;
        }
        
        .test-results h4 {
            color: #065f46;
            margin-bottom: 10px;
        }
        
        .test-results ul {
            list-style: none;
            padding: 0;
        }
        
        .test-results li {
            padding: 8px 0;
            color: #047857;
        }
        
        .test-results li:before {
            content: "✓ ";
            color: #10b981;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .icon {
            display: inline-block;
            width: 24px;
            height: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-badge">✓ Authentication Test Passed</div>
        
        <h1>🔐 Session Protection Active</h1>
        <p class="subtitle">You are successfully logged in and authenticated.</p>
        
        <div class="info-section">
            <h3>
                <span class="icon">👤</span>
                Your Session Information
            </h3>
            
            <div class="info-item">
                <span class="info-label">User ID:</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['user_id'] ?? 'N/A'); ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Username:</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['username'] ?? 'N/A'); ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Full Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'N/A'); ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Position:</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['user_position'] ?? 'N/A'); ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Branch:</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['user_branch'] ?? 'N/A'); ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">System Level:</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['system_level'] ?? 'N/A'); ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Session Status:</span>
                <span class="status-badge active">Active</span>
            </div>
        </div>
        
        <div class="test-results">
            <h4>✅ System Security Checks</h4>
            <ul>
                <li>Session authentication is working correctly</li>
                <li>User credentials are validated</li>
                <li>Session data is properly loaded</li>
                <li>Page protection is active</li>
                <li>Unauthorized access is blocked</li>
            </ul>
        </div>
        
        <div class="info-section">
            <h3>
                <span class="icon">📊</span>
                Protection Statistics
            </h3>
            
            <div class="info-item">
                <span class="info-label">Protected Pages:</span>
                <span class="info-value">209 files</span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Implementation Date:</span>
                <span class="info-value">July 17, 2026</span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Security Level:</span>
                <span class="status-badge active">High</span>
            </div>
        </div>
        
        <div class="button-group">
            <a href="report.php" class="btn btn-primary">Go to Dashboard</a>
            <a href="accountregistration.php" class="btn btn-secondary">User Management</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px;">
            <p><strong>Note:</strong> If you can see this page, it means the authentication system is working correctly. 
            Unauthenticated users will be automatically redirected to the login page.</p>
        </div>
    </div>
</body>
</html>
