<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Restricted</title>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;700&family=Space+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --blueprint-dark: #0a1922;
            --blueprint-light: #12232e;
            --blueprint-line: #1e90ff;
            --blueprint-accent: #ff6b6b;
            --blueprint-text: #e6f1ff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Archivo', sans-serif;
        }

        body {
            background-color: var(--blueprint-dark);
            color: var(--blueprint-text);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
            background-image: 
                linear-gradient(rgba(30, 144, 255, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(30, 144, 255, 0.05) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        .container {
            text-align: center;
            z-index: 10;
            padding: 3rem;
            max-width: 800px;
            background: rgba(10, 25, 34, 0.85);
            border-radius: 4px;
            box-shadow: 0 0 0 1px var(--blueprint-line),
                        0 0 30px rgba(10, 25, 34, 0.8);
            backdrop-filter: blur(5px);
            border: 1px solid var(--blueprint-line);
            transform: scale(0.95);
            animation: scaleIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        @keyframes scaleIn {
            to { transform: scale(1); opacity: 1; }
        }

        h1 {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: var(--blueprint-accent);
            text-shadow: 0 0 10px rgba(255, 107, 107, 0.3);
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s forwards 0.3s;
            letter-spacing: -0.5px;
            position: relative;
            display: inline-block;
        }

        h1:after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--blueprint-line);
            transform: scaleX(0);
            transform-origin: left;
            animation: lineIn 1s forwards 0.8s;
        }

        @keyframes lineIn {
            to { transform: scaleX(1); }
        }

        p {
            font-size: 1.2rem;
            margin-bottom: 2.5rem;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s forwards 0.5s;
            line-height: 1.6;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .structure {
            position: relative;
            width: 180px;
            height: 180px;
            margin: 0 auto 2.5rem;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s forwards 0.7s;
        }

        .building {
            position: absolute;
            width: 100%;
            height: 100%;
            background: rgba(30, 144, 255, 0.1);
            border: 2px solid var(--blueprint-line);
            clip-path: polygon(25% 0%, 75% 0%, 100% 100%, 0% 100%);
        }

        .building:before {
            content: '';
            position: absolute;
            top: 10%;
            left: 10%;
            right: 10%;
            bottom: 10%;
            background: rgba(30, 144, 255, 0.05);
            border: 1px dashed var(--blueprint-line);
        }

        .window {
            position: absolute;
            width: 20px;
            height: 30px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid var(--blueprint-line);
        }

        .window:nth-child(1) { top: 30%; left: 25%; }
        .window:nth-child(2) { top: 30%; right: 25%; }
        .window:nth-child(3) { top: 50%; left: 25%; }
        .window:nth-child(4) { top: 50%; right: 25%; }
        .window:nth-child(5) { top: 30%; left: 50%; transform: translateX(-50%); }

        .btn {
            display: inline-block;
            padding: 0.9rem 2.5rem;
            background: transparent;
            color: var(--blueprint-text);
            text-decoration: none;
            border-radius: 0;
            font-weight: 600;
            margin-top: 1rem;
            transition: all 0.4s;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s forwards 0.9s;
            border: 2px solid var(--blueprint-line);
            position: relative;
            overflow: hidden;
            font-family: 'Space Mono', monospace;
            letter-spacing: 1px;
        }

        .btn:hover {
            background: rgba(30, 144, 255, 0.1);
            color: var(--blueprint-accent);
        }

        .btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(30, 144, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .btn:hover:before {
            left: 100%;
        }

        @keyframes fadeInUp {
            to { opacity: 1; transform: translateY(0); }
        }

        .blueprint-grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            opacity: 0.3;
        }

        .dimension-line {
            position: absolute;
            background: var(--blueprint-line);
            opacity: 0;
            animation: fadeIn 1s forwards 1.2s;
        }

        .dimension-line.horizontal {
            height: 1px;
            width: 100px;
        }

        .dimension-line.vertical {
            width: 1px;
            height: 100px;
        }

        .dimension-line:nth-child(1) {
            top: 20%;
            left: 10%;
        }
        .dimension-line:nth-child(2) {
            top: 10%;
            left: 20%;
            transform: rotate(90deg);
        }
        .dimension-line:nth-child(3) {
            bottom: 20%;
            right: 10%;
        }
        .dimension-line:nth-child(4) {
            bottom: 10%;
            right: 20%;
            transform: rotate(90deg);
        }

        .error-code {
            position: absolute;
            bottom: 2rem;
            right: 2rem;
            font-family: 'Space Mono', monospace;
            color: var(--blueprint-line);
            opacity: 0;
            animation: fadeIn 1s forwards 1.4s;
            font-size: 0.9rem;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }

        .construction-icon {
            position: absolute;
            font-size: 1.5rem;
            opacity: 0;
            animation: fadeIn 1s forwards;
            color: var(--blueprint-accent);
        }

        .construction-icon:nth-child(1) {
            top: 15%;
            left: 15%;
            animation-delay: 1s;
        }
        .construction-icon:nth-child(2) {
            top: 25%;
            right: 15%;
            animation-delay: 1.2s;
        }
        .construction-icon:nth-child(3) {
            bottom: 20%;
            left: 20%;
            animation-delay: 1.4s;
        }
        .construction-icon:nth-child(4) {
            bottom: 15%;
            right: 25%;
            animation-delay: 1.6s;
        }
    </style>
</head>
<body>
    <div class="blueprint-grid"></div>
    
    <div class="dimension-line horizontal"></div>
    <div class="dimension-line vertical"></div>
    <div class="dimension-line horizontal"></div>
    <div class="dimension-line vertical"></div>

    <div class="construction-icon">🚧</div>
    <div class="construction-icon">⛏️</div>
    <div class="construction-icon">🏗️</div>
    <div class="construction-icon">🔨</div>

    <div class="container">
        <div class="structure">
            <div class="building"></div>
            <div class="window"></div>
            <div class="window"></div>
            <div class="window"></div>
            <div class="window"></div>
            <div class="window"></div>
        </div>
        
        <h1>CONSTRUCTION ZONE</h1>
        <p>This area is currently under development and access is restricted to authorized personnel only. Please check back later or contact the project administrator for access permissions.</p>
        <a href="javascript:history.back();" class="btn">RETURN TO SITE MAP</a>
        
        <div class="error-code">SECURITY CLEARANCE REQUIRED • ERROR 403</div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate windows
            const windows = document.querySelectorAll('.window');
            windows.forEach((window, index) => {
                setTimeout(() => {
                    window.style.animation = 'windowFlicker ' + (Math.random() * 3 + 2) + 's infinite';
                    
                    // Create style for window flicker
                    const style = document.createElement('style');
                    style.innerHTML = `
                        @keyframes windowFlicker {
                            0%, 19%, 21%, 23%, 25%, 54%, 56%, 100% { opacity: 0.8; }
                            20%, 22%, 24%, 55% { opacity: 0.2; }
                        }
                    `;
                    document.head.appendChild(style);
                }, index * 300 + 1000);
            });

            // Add blueprint grid animation
            const grid = document.querySelector('.blueprint-grid');
            setTimeout(() => {
                grid.style.backgroundImage = `
                    linear-gradient(rgba(30, 144, 255, 0.1) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(30, 144, 255, 0.1) 1px, transparent 1px)`;
            }, 500);

            // Add pulsing effect to building outline
            const building = document.querySelector('.building');
            setInterval(() => {
                building.style.boxShadow = `0 0 0 ${Math.random() * 5}px rgba(30, 144, 255, 0.2)`;
            }, 3000);
        });
    </script>
</body>
</html>