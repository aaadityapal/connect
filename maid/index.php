<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Back Office</title>
    <link rel="stylesheet" href="style.css">
    <!-- Preconnect for fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>

<body>

    <div class="app-container">
        <header class="header"
            style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
            <div style="text-align: left;">
                <h1 style="font-size: 1.5rem; font-weight: 700; border: none; margin: 0; padding: 0;">Back Office</h1>
                <p style="margin-top: 4px; color: var(--text-muted); font-size: 0.9rem;">
                    Welcome <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>
                </p>
            </div>
            <a href="../logout.php" class="logout-btn" style="
                color: #9CA3AF; 
                padding: 8px; 
                border-radius: 50%; 
                transition: all 0.2s; 
                display: flex; 
                align-items: center; 
                justify-content: center;
                background: transparent;
            " onmouseover="this.style.color='#ef4444'; this.style.background='rgba(239, 68, 68, 0.1)'"
                onmouseout="this.style.color='#9CA3AF'; this.style.background='transparent'">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                    <line x1="12" y1="2" x2="12" y2="12"></line>
                </svg>
            </a>
        </header>

        <div class="clock-display">
            <div class="time">--:--</div>
            <div class="date">Loading date...</div>
        </div>

        <div class="action-area">
            <button id="punchInBtn" class="btn btn-punch-in">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                    <polyline points="10 17 15 12 10 7"></polyline>
                    <line x1="15" y1="12" x2="3" y2="12"></line>
                </svg>
                Punch In
            </button>

            <button id="punchOutBtn" class="btn btn-punch-out" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Punch Out
            </button>
        </div>

        <div id="statusText" class="status-message">
            Loading status...
        </div>

        <div style="margin-top: 3rem; margin-bottom: 2rem; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.8;">
            <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted); margin-bottom: 0.75rem;">
                <rect x="3" y="11" width="18" height="10" rx="2"></rect>
                <circle cx="12" cy="5" r="2"></circle>
                <path d="M12 7v4"></path>
                <line x1="8" y1="16" x2="8" y2="16"></line>
                <line x1="16" y1="16" x2="16" y2="16"></line>
            </svg>
            <p style="font-size: 1rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px; font-weight: 500;">
                Made with <span style="color: #ef4444; font-size: 1.2rem;">&hearts;</span> by Aditya
            </p>
        </div>

        <nav class="bottom-nav">
            <a href="index.php" class="nav-item active">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Punch</span>
            </a>
            <a href="attendance.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <span>Attendance</span>
            </a>
            <a href="leaves.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>Leaves</span>
            </a>
            <a href="profile.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Profile</span>
            </a>
        </nav>
    </div>

    <script src="https://unpkg.com/feather-icons"></script>
    <script src="punch-modal.js"></script>
    <script src="script.js"></script>
</body>

</html>