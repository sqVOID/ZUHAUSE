<?php
session_start();


if (isset($_SESSION['user_id'])) {
    header("Location: report.php");
    exit();
}

// Database connection - use config.php
require_once 'config.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = "";

// Check for error messages from session_check.php
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'account_deactivated') {
        $error_message = "Your session has been terminated. Your account has been deactivated.";
    } elseif ($_GET['error'] === 'account_not_found') {
        $error_message = "Your account could not be found. Please contact the administrator.";
    } elseif ($_GET['error'] === 'session_terminated') {
        $error_message = "Please login to your account.";
    }
}

// Handle login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $pass = trim($_POST['password']);
    
    $username_escaped = $conn->real_escape_string($username);
    $pass_escaped = $conn->real_escape_string($pass);

    // Check in accounts table
    $sql = "SELECT * FROM accounts WHERE username = '$username_escaped' AND password = '$pass_escaped'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Check if account is activated
        if ($user['status'] == 'Activated') {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = isset($user['email']) ? $user['email'] : '';
            $_SESSION['user_position'] = $user['position'];
            $_SESSION['user_branch'] = $user['branch'];
            $_SESSION['system_level'] = $user['system_level'];

            // Sidebar access: Use sidebar_source to determine which access to load
            $sidebar_source = isset($user['sidebar_source']) ? $user['sidebar_source'] : 'position';
            
            // Store sidebar source in session for debugging/display purposes
            $_SESSION['sidebar_source'] = $sidebar_source;
            
            // DEBUG: Log the values
            error_log("DEBUG LOGIN - User: " . $user['username']);
            error_log("DEBUG LOGIN - Sidebar Source: " . $sidebar_source);
            error_log("DEBUG LOGIN - System Level: " . $user['system_level']);
            error_log("DEBUG LOGIN - User sidebar_access field: " . ($user['sidebar_access'] ?? 'NULL'));
            
            if ($sidebar_source === 'account') {
                // Use per-account sidebar settings from sidebarperacc.php
                $_SESSION['sidebar_access'] = isset($user['sidebar_access']) ? $user['sidebar_access'] : '';
                error_log("DEBUG LOGIN - Using ACCOUNT source, sidebar_access: " . $_SESSION['sidebar_access']);
            } else {
                // Use position-based sidebar settings from position.php
                $user_pos = $conn->real_escape_string($user['position']);
                $p_sql = "SELECT sidebar_access FROM positions WHERE position_name = '$user_pos'";
                $p_result = $conn->query($p_sql);
                if ($p_result && $p_result->num_rows > 0) {
                    $p_row = $p_result->fetch_assoc();
                    $_SESSION['sidebar_access'] = $p_row['sidebar_access'];
                    error_log("DEBUG LOGIN - Using POSITION source, sidebar_access: " . $_SESSION['sidebar_access']);
                } else {
                    $_SESSION['sidebar_access'] = '';
                    error_log("DEBUG LOGIN - Using POSITION source, but no position found, using empty (full access)");
                }
            }
            
            error_log("DEBUG LOGIN - Final sidebar_access: " . $_SESSION['sidebar_access']);

            // Register active session for instant logout capability
            $current_session_id = session_id();
            $user_id = (int)$user['id'];
            $current_session_id_escaped = $conn->real_escape_string($current_session_id);
            
            // Remove old sessions for this user
            $delete_result = $conn->query("DELETE FROM active_sessions WHERE user_id = $user_id");
            
            // Insert new session (with error handling for duplicates)
            $insert_sql = "INSERT INTO active_sessions (user_id, session_id) VALUES ($user_id, '$current_session_id_escaped') 
                          ON DUPLICATE KEY UPDATE session_id = '$current_session_id_escaped'";
            $insert_result = $conn->query($insert_sql);
            
            if (!$insert_result) {
                error_log("Session insert error: " . $conn->error);
            }

            // Redirect to dashboard or home page
            header("Location: main.php");
            exit();
        }
        else if ($user['status'] == 'Pending') {
            $error_message = "Your account is pending activation. Please contact the administrator.";
        }
        else if ($user['status'] == 'Deactivated') {
            $error_message = "Your account has been deactivated. Please contact the administrator.";
        }
        else {
            $error_message = "Your account status is: " . $user['status'] . ". Please contact the administrator.";
        }
    }
    else {
        $error_message = "Invalid username or password.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/imslogo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luna Group - Login Page</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Brand Colors */
            --color-navy: #0d3347;
            --color-navy-dark: #081f2d;
            --color-navy-light: #164460;
            --color-gold: #b08a52;
            --color-gold-light: #c9a46e;
            --color-gold-pale: #f5ede0;
            
            /* Background Colors */
            --bg-form-panel: #faf8f5;
            --bg-input: #f0ebe3;
            --bg-input-focus: #ffffff;
            
            /* Text Colors */
            --text-heading: #0d3347;
            --text-gold: #b08a52;
            --text-body: #9a9086;
            --text-muted: #7a7068;
            --text-footer: #c0b8ae;
            --text-white: #ffffff;
            
            /* Border Colors */
            --border-input: #d6cfc5;
            --border-divider: #e0d8ce;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .login-container {
            display: flex;
            min-height: 100vh;
        }

        /* LEFT PANEL - Hero Section */
        .left-panel {
            flex: 1;
            position: relative;
            background-color: var(--color-navy-dark);
            display: flex;
            align-items: flex-end;
            justify-content: flex-start;
            padding: 3rem;
            overflow: hidden;
        }

        /* Image Grid Background */
        .left-panel::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: 
                url('zuhause/612413585_855148214167000_7559703811353813301_n.jpg'),
                url('zuhause/612715889_855148187500336_199028372415888300_n.jpg'),
                url('zuhause/612815017_855146504167171_3633856675230303244_n.jpg'),
                url('zuhause/612842462_855148237500331_4722815120933457420_n.jpg'),
                url('zuhause/612874607_855148100833678_3612227835510404849_n.jpg'),
                url('zuhause/612912182_855146520833836_8913344491493668129_n.jpg'),
                url('zuhause/612966270_855146540833834_6024105830328147843_n.jpg'),
                url('zuhause/613044096_855146467500508_7844468152074335254_n.jpg'),
                url('zuhause/613069097_855148127500342_2437632218516205860_n.jpg'),
                url('zuhause/613134699_855148174167004_1540071753621638330_n.jpg'),
                url('zuhause/613569215_855146480833840_6979155031752060316_n.jpg'),
                url('zuhause/614388066_855146567500498_7175191320898149689_n.jpg'),
                url('zuhause/614431924_855148154167006_2514952981555761993_n.jpg'),
                url('zuhause/615398792_858001790548309_426767090424707646_n.jpg'),
                url('zuhause/615555053_858001767214978_5135513880588571244_n.jpg'),
                url('zuhause/615560248_858001730548315_5917665513337274640_n.jpg');
            background-size: 
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4),
                calc(100% / 4) calc(100% / 4);
            background-position: 
                0% 0%, calc(100% / 3) 0%, calc(200% / 3) 0%, 100% 0%,
                0% calc(100% / 3), calc(100% / 3) calc(100% / 3), calc(200% / 3) calc(100% / 3), 100% calc(100% / 3),
                0% calc(200% / 3), calc(100% / 3) calc(200% / 3), calc(200% / 3) calc(200% / 3), 100% calc(200% / 3),
                0% 100%, calc(100% / 3) 100%, calc(200% / 3) 100%, 100% 100%;
            background-repeat: no-repeat;
            opacity: 0.30;
            z-index: 0;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(160deg, rgba(8,31,45,0.70), rgba(13,51,71,0.60), rgba(8,31,45,0.75));
            z-index: 1;
        }

        .left-content {
            position: relative;
            z-index: 1;
            max-width: 520px;
            color: var(--text-white);
        }

        .left-label {
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--color-gold-light);
            margin-bottom: 1.5rem;
        }

        .left-headline {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(2rem, 4vw, 2.5rem);
            font-weight: 400;
            line-height: 1.2;
            margin-bottom: 1.5rem;
        }

        .left-headline em {
            font-style: italic;
            color: var(--color-gold-light);
        }

        .left-body {
            font-size: 1rem;
            font-weight: 300;
            line-height: 1.6;
            color: rgba(255,255,255,0.60);
            margin-bottom: 3rem;
        }

        .left-stats {
            display: flex;
            gap: 3rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(176,138,82,0.4);
        }

        .stat-item {
            flex: 1;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--color-gold-light);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            font-weight: 300;
            color: rgba(255,255,255,0.50);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* RIGHT PANEL - Form Section */
        .right-panel {
            flex: 1;
            background: var(--bg-form-panel);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            position: relative;
        }

        .logo-container {
            position: absolute;
            top: 2rem;
            right: 2rem;
        }

        .logo-container img {
            width: 120px;
            height: auto;
        }

        .form-container {
            width: 100%;
            max-width: 440px;
        }

        .form-label {
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--text-gold);
            margin-bottom: 1rem;
            display: block;
        }

        .form-title {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(1.75rem, 3vw, 2.25rem);
            font-weight: 400;
            color: var(--text-heading);
            margin-bottom: 0.75rem;
        }

        .form-subtitle {
            font-size: 0.875rem;
            font-weight: 400;
            color: var(--text-body);
            margin-bottom: 2.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .input-label {
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-heading);
            margin-bottom: 0.5rem;
            display: block;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.875rem;
            font-weight: 400;
            color: var(--text-heading);
            background: var(--bg-input);
            border: 1.5px solid transparent;
            border-radius: 0.75rem;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-input::placeholder {
            color: #999;
        }

        .form-input:focus {
            background: var(--bg-input-focus);
            border-color: var(--color-gold);
        }

        /* Hide browser's default password reveal button */
        .form-input::-ms-reveal,
        .form-input::-ms-clear {
            display: none;
        }

        .form-input::-webkit-credentials-auto-fill-button,
        .form-input::-webkit-contacts-auto-fill-button {
            visibility: hidden;
            display: none !important;
            pointer-events: none;
            height: 0;
            width: 0;
            margin: 0;
        }

        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.25rem;
            color: var(--text-muted);
            font-size: 1.25rem;
        }

        .form-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-input {
            width: 18px;
            height: 18px;
            border: 1.5px solid var(--border-input);
            border-radius: 0.25rem;
            cursor: pointer;
            accent-color: var(--color-navy);
        }

        .checkbox-label {
            font-size: 0.75rem;
            font-weight: 400;
            color: var(--text-muted);
        }

        .forgot-link {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--color-gold);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .forgot-link:hover {
            color: var(--color-gold-light);
        }

        .submit-btn {
            width: 100%;
            padding: 0.875rem 1.5rem;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-white);
            background: var(--color-navy);
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .submit-btn:hover {
            background: var(--color-navy-dark);
        }

        .submit-btn:active {
            transform: scale(0.98);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 2rem 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-divider);
        }

        .divider-text {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .sso-btn {
            width: 100%;
            padding: 0.875rem 1.5rem;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-heading);
            background: var(--text-white);
            border: 1.5px solid var(--border-input);
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .sso-btn:hover {
            border-color: var(--color-navy);
        }

        .footer-text {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .footer-link {
            color: var(--color-gold);
            text-decoration: none;
            font-weight: 500;
        }

        .footer-link:hover {
            text-decoration: underline;
        }

        .error-message {
            background: #fff3f3;
            color: #c62828;
            border: 1px solid #ffcdd2;
            padding: 0.875rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            text-align: center;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .login-container {
                flex-direction: column;
            }

            .left-panel {
                min-height: 40vh;
                padding: 3rem 2rem;
            }

            .left-stats {
                gap: 2rem;
            }

            .right-panel {
                padding: 2rem 1.5rem;
            }

            .logo-container {
                top: 1.5rem;
                right: 1.5rem;
            }
        }

        @media (max-width: 640px) {
            .left-panel {
                padding: 2rem 1.5rem;
            }

            .left-stats {
                flex-direction: column;
                gap: 1.5rem;
            }

            .logo-container img {
                width: 60px;
            }

            .form-container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- LEFT PANEL - Hero -->
        <div class="left-panel">
            <div class="left-content">
                <div class="left-label">ZUHAUSE NVENTORY MANAGEMENT</div>
                <h1 class="left-headline">Every appliance, <em>perfectly tracked.</em></h1>
                <p class="left-body">Manage your full appliance catalog, track stock levels, and streamline procurement — all in one place.</p>
            </div>
        </div>

        <!-- RIGHT PANEL - Form -->
        <div class="right-panel">
            <div class="logo-container">
                <img src="Icon/ZUHAUSE-LOGO.png" alt="ZUHAUSE Logo">
            </div>

            <div class="form-container">
                <h2 class="form-title">Welcome back</h2>
                <p class="form-subtitle">Sign in to access your account</p>

                <?php if (!empty($error_message)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <form id="loginForm" method="POST" action="">
                    <div class="form-group">
                        <label class="input-label" for="username">Username</label>
                        <input 
                            type="text" 
                            name="username" 
                            id="username" 
                            class="form-input" 
                            placeholder="Enter Username" 
                            required
                            autocomplete="username"
                        >
                    </div>

                    <div class="form-group">
                        <label class="input-label" for="password">Password</label>
                        <div class="password-wrapper">
                            <input 
                                type="password" 
                                name="password" 
                                id="password" 
                                class="form-input" 
                                placeholder="Enter Password" 
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="toggle-password" id="togglePassword" title="Show password">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="login" class="submit-btn">Login</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
        });

        // Randomize background grid images on load
        const images = [
            'zuhause/612413585_855148214167000_7559703811353813301_n.jpg',
            'zuhause/612715889_855148187500336_199028372415888300_n.jpg',
            'zuhause/612815017_855146504167171_3633856675230303244_n.jpg',
            'zuhause/612842462_855148237500331_4722815120933457420_n.jpg',
            'zuhause/612874607_855148100833678_3612227835510404849_n.jpg',
            'zuhause/612912182_855146520833836_8913344491493668129_n.jpg',
            'zuhause/612966270_855146540833834_6024105830328147843_n.jpg',
            'zuhause/613044096_855146467500508_7844468152074335254_n.jpg',
            'zuhause/613069097_855148127500342_2437632218516205860_n.jpg',
            'zuhause/613134699_855148174167004_1540071753621638330_n.jpg',
            'zuhause/613569215_855146480833840_6979155031752060316_n.jpg',
            'zuhause/614388066_855146567500498_7175191320898149689_n.jpg',
            'zuhause/614431924_855148154167006_2514952981555761993_n.jpg',
            'zuhause/615398792_858001790548309_426767090424707646_n.jpg',
            'zuhause/615555053_858001767214978_5135513880588571244_n.jpg',
            'zuhause/615560248_858001730548315_5917665513337274640_n.jpg'
        ];

        // Shuffle array function
        function shuffle(array) {
            const newArray = [...array];
            for (let i = newArray.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [newArray[i], newArray[j]] = [newArray[j], newArray[i]];
            }
            return newArray;
        }

        // Apply random grid on page load
        window.addEventListener('DOMContentLoaded', function() {
            const leftPanel = document.querySelector('.left-panel');
            const shuffledImages = shuffle(images);
            
            // Create background-image string with all 16 images
            const bgImages = shuffledImages.map(img => `url('${img}')`).join(',\n                ');
            
            // Create a style element to override the CSS
            const style = document.createElement('style');
            style.textContent = `
                .left-panel::after {
                    background-image: ${bgImages} !important;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>