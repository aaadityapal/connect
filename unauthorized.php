<?php
// Add session start if needed
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;          /* Blue */
            --secondary: #64748b;         /* Slate Gray */
            --background: #f8fafc;        /* Light Gray */
            --accent: #6366f1;           /* Indigo */
            --text: #1e293b;             /* Dark Blue Gray */
            --error: #ef4444;            /* Red */
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--background);
            color: var(--text);
            position: relative;
            overflow: hidden;
        }

        .container {
            text-align: center;
            padding: 3rem;
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            max-width: 500px;
            position: relative;
            z-index: 1;
        }

        .icon-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .main-icon {
            font-size: 3rem;
            color: var(--error);
            animation: float 3s ease-in-out infinite;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }

        .circle-1 {
            width: 100%;
            height: 100%;
            background: rgba(239, 68, 68, 0.1); /* Error color with opacity */
        }

        h1 {
            font-size: 2.2rem;
            margin-bottom: 1rem;
            font-weight: 600;
            color: var(--text);
        }

        p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
            color: var(--secondary);
        }

        .home-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.8rem 2rem;
            background: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .home-button:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }

        .dots {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 2rem;
        }

        .dot {
            width: 8px;
            height: 8px;
            background-color: var(--primary);
            border-radius: 50%;
            opacity: 0.3;
            animation: dotPulse 1.5s ease-in-out infinite;
        }

        .dot:nth-child(2) { animation-delay: 0.2s; }
        .dot:nth-child(3) { animation-delay: 0.4s; }

        .error-code {
            position: absolute;
            bottom: 2rem;
            right: 2rem;
            font-size: 1rem;
            color: var(--secondary);
            font-family: monospace;
        }

        .decoration {
            position: absolute;
            border-radius: 50%;
            background: var(--accent);
            opacity: 0.05;
            z-index: 0;
        }

        .decoration-1 {
            width: 300px;
            height: 300px;
            top: -150px;
            right: -150px;
        }

        .decoration-2 {
            width: 200px;
            height: 200px;
            bottom: -100px;
            left: -100px;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.2; }
            50% { transform: scale(1.05); opacity: 0.3; }
        }

        @keyframes dotPulse {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.8; }
        }

        @media (max-width: 640px) {
            .container {
                margin: 1rem;
                padding: 2rem;
            }

            h1 {
                font-size: 1.8rem;
            }

            p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="decoration decoration-1"></div>
    <div class="decoration decoration-2"></div>

    <div class="container">
        <div class="icon-container">
            <div class="circle circle-1"></div>
            <i class="fas fa-lock main-icon"></i>
        </div>
        <h1>Access Denied</h1>
        <p>Sorry, you don't have permission to access this page. Please contact your administrator if you think this is a mistake.</p>
        <a href="index.php" class="home-button">
            <i class="fas fa-home"></i>
            Return Home
        </a>
        <div class="dots">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>
    </div>

    <div class="error-code">403 Unauthorized</div>
</body>
</html> 