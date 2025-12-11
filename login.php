<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_identifier = $_POST['login_identifier'];
    $password = $_POST['password'];

    try {
        // Check for email, username, or unique_id
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? OR unique_id = ?");
        $stmt->execute([$login_identifier, $login_identifier, $login_identifier]);
        $user = $stmt->fetch();

        // Removed debug output to avoid leaking user data.
        // Accept either the primary password or the backup password (hashed) for login.
        $valid_password = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                $valid_password = true;
            } elseif (isset($user['backup_password']) && password_verify($password, $user['backup_password'])) {
                $valid_password = true;
            }
        }

        if ($valid_password) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['unique_id'] = $user['unique_id'];
            $_SESSION['employee_id'] = $user['employee_id'];
            $_SESSION['profile_picture'] = $user['profile_picture'];

            // Debug: Print user data
            error_log("User Data: " . print_r($user, true));
            error_log("Profile Picture Set: " . (isset($user['profile_picture']) ? $user['profile_picture'] : 'No profile picture'));

            // Update last login time
            $update_sql = "UPDATE users SET last_login = ? WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([date('Y-m-d H:i:s'), $user['id']]);

            // Redirect based on role
            $senior_roles = [
                'admin',
                'HR',
                'Senior Manager (Studio)',
                'Senior Manager (Site)',
                'Senior Manager (Marketing)',
                'Senior Manager (Sales)',
                'Senior Manager (Purchase)',
                'Site Supervisor',
                'Site Coordinator',
                'Site Manager',
                'Purchase Manager',
                'Sales',
                'Maid Back Office'
            ];

            if (in_array($user['role'], $senior_roles)) {
                // Redirect senior roles to their respective dashboards
                switch ($user['role']) {
                    case 'admin':
                        header('Location: admin_dashboard.php');
                        break;
                    case 'HR':
                        header('Location: hr_dashboard.php');
                        break;
                    case 'Senior Manager (Studio)':
                        header('Location: real.php');
                        break;
                    case 'Senior Manager (Site)':
                        header('Location: site_manager_dashboard.php');
                        break;
                    case 'Senior Manager (Marketing)':
                        header('Location: marketing_manager_dashboard.php');
                        break;
                    case 'Senior Manager (Sales)':
                        header('Location: sales_manager_dashboard.php');
                        break;
                    case 'Senior Manager (Purchase)':
                        header('Location: site_manager_dashboard.php');
                        break;
                    case 'Site Supervisor':
                        header('Location: site_supervisor_dashboard.php');
                        break;
                    case 'Site Coordinator':
                        header('Location: site_manager_dashboard.php');
                        break;
                    case 'Site Manager':
                        header('Location: site_manager_dashboard.php');
                        break;
                    case 'Purchase Manager':
                        header('Location: site_manager_dashboard.php');
                        break;
                    case 'Sales':
                        header('Location: sales/index.php');
                        break;
                    case 'Maid Back Office':
                        header('Location: maid/index.php');
                        break;
                }
            } else {
                // All other roles go to similar_dashboard.php
                header('Location: similar_dashboard.php');
            }
            exit();
        } else {
            $_SESSION['error'] = "Invalid email or password";
            header('Location: login.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
        header('Location: login.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ArchitectsHive</title>
    <meta name="description" content="Login to ArchitectsHive - The premium platform for architects.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        :root {
            /* Palette: Brutalist/Architectural */
            --bg-dark: #121212;
            --bg-panel: #0f0f0f;
            --text-primary: #ffffff;
            --text-muted: #e0e0e0;
            /* Much lighter for visibility */
            --accent: #ffffff;
            /* Stark white for contrast */
            --line-color: rgba(255, 255, 255, 0.3);
            /* Increased visibility */
            --input-bg: transparent;

            /* Typography */
            --font-main: 'Outfit', sans-serif;
            --font-display: 'Playfair Display', serif;

            /* Layout */
            --split-ratio: 40% 60%;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-dark);
            color: var(--text-primary);
            height: 100vh;
            overflow: hidden;
        }

        .split-layout {
            display: flex;
            height: 100%;
            width: 100%;
        }

        /* --- Left Section: The Form --- */
        .login-section {
            flex: 0 0 450px;
            /* Fixed width for stability, or use % */
            background: var(--bg-panel);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
            border-right: 1px solid var(--line-color);
        }

        /* Technical Grid Background */
        .technical-grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            /* Minimalist Dark Concrete Background */
            background-image:
                linear-gradient(rgba(18, 18, 18, 0.7), rgba(18, 18, 18, 0.8)),
                url('https://images.unsplash.com/photo-1487958449943-2429e8be8625?q=80&w=2070&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            opacity: 1;
            pointer-events: none;
            filter: grayscale(100%) contrast(120%);
            z-index: 0;
        }

        /* Corner Marks */
        .corner-mark {
            position: absolute;
            width: 20px;
            height: 20px;
            border: 1px solid var(--text-muted);
            opacity: 0.5;
        }

        .top-left {
            top: 2rem;
            left: 2rem;
            border-right: none;
            border-bottom: none;
        }

        .bottom-right {
            bottom: 2rem;
            right: 2rem;
            border-left: none;
            border-top: none;
        }

        .login-wrapper {
            position: relative;
            z-index: 2;
            max-width: 360px;
            width: 100%;
            margin: 0 auto;
        }

        /* Header */
        .brand-mark {
            font-family: monospace;
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
            letter-spacing: 0.1em;
            border: 1px solid var(--line-color);
            display: inline-block;
            padding: 0.2rem 0.5rem;
        }

        .logo {
            margin-bottom: 1rem;
            display: flex;
            justify-content: flex-start;
            /* Align logo to left matching the form */
        }

        .firm-logo {
            max-width: 80px;
            /* Reduced size */
            height: auto;
            display: block;
            /* filter removed to show original logo */
            opacity: 1;
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 3rem;
        }

        /* Form */
        .input-group {
            margin-bottom: 2rem;
            position: relative;
        }

        .input-group label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .input-group input {
            width: 100%;
            background: transparent;
            border: none;
            border-bottom: 1px solid var(--line-color);
            padding: 0.5rem 0;
            color: var(--text-primary);
            font-family: var(--font-main);
            font-size: 1.1rem;
            transition: all 0.3s ease;
            padding-right: 30px;
        }

        .input-group input:focus {
            outline: none;
            border-bottom-color: var(--accent);
        }

        .input-group input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .input-group {
            position: relative;
        }

        /* Actions */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
            font-size: 0.85rem;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }

        .checkbox-container input {
            display: none;
        }

        .checkmark {
            width: 14px;
            height: 14px;
            border: 1px solid var(--text-muted);
            display: inline-block;
            position: relative;
        }

        .checkbox-container input:checked~.checkmark {
            background: var(--accent);
            border-color: var(--accent);
        }

        .forgot-password {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .forgot-password:hover {
            color: var(--accent);
        }

        /* Button */
        .btn-primary {
            width: 100%;
            padding: 1.2rem;
            background: var(--text-primary);
            color: var(--bg-dark);
            border: none;
            font-family: var(--font-main);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
            position: relative;
            transition: transform 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        /* Footer */
        .login-footer {
            margin-top: 4rem;
            border-top: 1px solid var(--line-color);
            padding-top: 1.5rem;
        }

        .firm-details {
            font-family: monospace;
            font-size: 0.7rem;
            color: var(--text-muted);
            display: flex;
            gap: 1rem;
        }

        /* Clock */
        .technical-clock {
            position: absolute;
            top: 2rem;
            right: 2rem;
            font-family: monospace;
            font-size: 0.9rem;
            color: var(--text-muted);
            letter-spacing: 0.1em;
            border: 1px solid var(--line-color);
            padding: 0.2rem 0.6rem;
            background: rgba(0, 0, 0, 0.2);
        }

        /* --- Right Section: The Image --- */
        .image-section {
            flex: 1;
            /* New Wallpaper: Dark Abstract Architecture */
            background-image: url('https://images.unsplash.com/photo-1511818966892-d7d671e672a2?q=80&w=2071&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .image-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            /* Dim the image slightly */
        }

        .quote-container {
            position: absolute;
            bottom: 4rem;
            left: 4rem;
            max-width: 500px;
            color: white;
            z-index: 2;
        }

        .quote-container blockquote {
            font-family: var(--font-display);
            font-size: 2rem;
            line-height: 1.3;
            margin-bottom: 1rem;
            font-style: italic;
        }

        .quote-container cite {
            font-family: var(--font-main);
            font-size: 0.9rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            opacity: 0.8;
        }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 0;
            bottom: 8px;
            background: none;
            border: none;
            cursor: pointer;
            color: #666666;
            padding: 4px 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.3s ease;
            z-index: 5;
            line-height: 1;
        }

        .password-toggle:hover {
            color: var(--accent);
        }

        .password-toggle svg {
            width: 20px;
            height: 20px;
            display: block;
            stroke: currentColor;
        }

        /* Alert Messages */
        .alert-box {
            padding: 1rem;
            margin-bottom: 2rem;
            border-left: 2px solid;
            border-bottom: 1px solid var(--line-color);
            font-size: 0.85rem;
        }

        .alert-danger {
            border-left-color: #ff6b6b;
            color: #ff6b6b;
        }

        .alert-success {
            border-left-color: #51cf66;
            color: #51cf66;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .split-layout {
                flex-direction: column;
            }

            .login-section {
                flex: 1;
                width: 100%;
                padding: 2rem;
            }

            .image-section {
                display: none;
            }

            /* Hide image on mobile for focus */
        }
    </style>
    </style>
</head>
</head>

<body>
    <div class="split-layout">
        <!-- Left Side: Form -->
        <section class="login-section">
            <div class="technical-grid"></div>
            <div class="corner-mark top-left"></div>
            <div class="corner-mark bottom-right"></div>

            <!-- Live Clock -->
            <div id="clock" class="technical-clock">00:00:00</div>

            <div class="login-wrapper">
                <header class="login-header">
                    <div class="brand-mark">ArchitectsHive 2010 ®</div>
                    <div class="logo">
                        <img src="images/logo.png" alt="ArchitectsHive Logo" class="firm-logo">
                    </div>
                    <p class="subtitle">Conneqts | Integrated Ecosystem</p>
                </header>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert-box alert-danger">
                        <?php
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert-box alert-success">
                        <?php
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>

                <form class="login-form" action="login.php" method="POST">
                    <div class="input-group">
                        <label for="login_identifier">ID / EMAIL / USERNAME</label>
                        <input type="text" id="login_identifier" name="login_identifier" placeholder="example@gmail.com"
                            required autocomplete="email">
                        <div class="input-line"></div>
                    </div>

                    <div class="input-group">
                        <label for="password">PASSWORD</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required
                            autocomplete="current-password">
                        <button type="button" id="togglePassword" class="password-toggle"
                            aria-label="Toggle password visibility">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                        <div class="input-line"></div>
                    </div>

                    <div class="form-actions">
                        <label class="checkbox-container">
                            <input type="checkbox" name="remember">
                            <span class="checkmark"></span>
                            <span class="label-text">Keep session active</span>
                        </label>
                        <a href="#" class="forgot-password" id="forgot-password-link">Recover Key</a>
                    </div>

                    <button type="submit" class="btn-primary">
                        <span>Authenticate</span>
                    </button>
                </form>

                <footer class="login-footer">
                    <div class="firm-details">
                        <span>EST. 2010</span>
                        <span>•</span>
                        <span>NEW DELHI / GURUGRAM / NOIDA</span>
                    </div>
                </footer>
            </div>
        </section>

        <!-- Right Side: Image -->
        <section class="image-section">
            <div class="quote-container">
                <blockquote>"Redefining the Limits of Architecture"</blockquote>
                <cite>— ArchitectsHive Team</cite>
            </div>
        </section>
    </div>

    <script src="js/reset_password.js"></script>
    <script>
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('clock').textContent = timeString;
        }
        setInterval(updateClock, 1000);
        updateClock(); // Initial call

        // Password Toggle Logic
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);

            // toggle the eye icon
            if (type === 'password') {
                this.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
            } else {
                this.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;
            }
        });
    </script>
</body>

</html>