<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
      <link rel="icon" type="image/svg+xml" href="Icon/ZUHAUSE-LOGO.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZUHAUSE - Loading</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 50%, #f5f7fa 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(240,242,245,0.4) 0%, transparent 70%);
            pointer-events: none;
        }

        body::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(240,242,245,0.3) 0%, transparent 70%);
            pointer-events: none;
        }

        .landing-container {
            text-align: center;
            color: #2c3e50;
            animation: fadeIn 0.8s ease-in;
            padding: 50px 40px;
            max-width: 100%;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06),
                        0 2px 8px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.8);
            position: relative;
            z-index: 1;
        }

        .logo-container {
            margin-bottom: 30px;
        }

        .logo {
            max-width: auto;
            width: 70%;
            height: auto;
            animation: pulse 2s ease-in-out infinite;
            filter: drop-shadow(0px 4px 12px rgba(0, 0, 0, 0.08));
        }

        .tagline {
            font-size: 1.2rem;
            margin-top: 10px;
            opacity: 0.85;
            letter-spacing: 1.5px;
            word-wrap: break-word;
            color: #6b7280;
            font-weight: 300;
        }

        .loader {
            margin: 40px auto;
            width: 60px;
            height: 60px;
            position: relative;
        }

        .loader-circle {
            width: 100%;
            height: 100%;
            border: 4px solid rgba(200, 200, 200, 0.25);
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            font-size: 1rem;
            margin-top: 20px;
            color: #6b7280;
            opacity: 0.85;
            animation: blink 1.5s ease-in-out infinite;
            font-weight: 300;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        @keyframes blink {
            0%, 100% {
                opacity: 0.8;
            }
            50% {
                opacity: 0.3;
            }
        }

        /* Responsive design */
        /* Extra small devices (phones, 320px to 480px) */
        @media (max-width: 480px) {
            .logo {
                max-width: 250px;
            }
            .tagline {
                font-size: 0.85rem;
                padding: 0 20px;
            }
            .loader {
                width: 50px;
                height: 50px;
                margin: 30px auto;
            }
            .loading-text {
                font-size: 0.9rem;
            }
            .landing-container {
                padding: 35px 25px;
            }
        }

        /* Small devices (tablets, 481px to 768px) */
        @media (min-width: 481px) and (max-width: 768px) {
            .logo {
                max-width: 320px;
            }
            .tagline {
                font-size: 1rem;
            }
            .loader {
                width: 55px;
                height: 55px;
            }
        }

        /* Medium devices (small laptops, 769px to 1024px) */
        @media (min-width: 769px) and (max-width: 1024px) {
            .logo {
                max-width: 360px;
            }
            .tagline {
                font-size: 1.1rem;
            }
        }

        /* Large devices (desktops, 1025px and up) */
        @media (min-width: 1025px) {
            .logo {
                max-width: 400px;
            }
            .tagline {
                font-size: 1.2rem;
            }
        }

        /* Landscape orientation adjustments */
        @media (max-height: 600px) and (orientation: landscape) {
            .logo {
                max-width: 280px;
            }
            .tagline {
                font-size: 0.9rem;
                margin-top: 8px;
            }
            .loader {
                width: 45px;
                height: 45px;
                margin: 20px auto;
            }
            .loading-text {
                font-size: 0.85rem;
                margin-top: 15px;
            }
            .logo-container {
                margin-bottom: 15px;
            }
        }

        /* Very small devices (older phones, less than 320px) */
        @media (max-width: 320px) {
            .logo {
                max-width: 200px;
            }
            .tagline {
                font-size: 0.75rem;
                padding: 0 15px;
            }
            .loader {
                width: 45px;
                height: 45px;
            }
            .loading-text {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <div class="logo-container">
            <img src="Icon/ZUHAUSE-LOGO.png" alt="ZUHAUSE Logo" class="logo">
            <div class="tagline">Inventory Management System</div>
        </div>
        
        <div class="loader">
            <div class="loader-circle"></div>
        </div>
        
        <!-- <div class="loading-text">Loading, please wait...</div> -->
    </div>

    <script>
        // Redirect to login page after 2.5 seconds
        setTimeout(function() {
            window.location.href = '/zuhause/login.php';
        }, 2500);
    </script>
</body>
</html>
