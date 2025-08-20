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

        if ($user) {
            echo "Found user with role: " . $user['role'] . "<br>";
            var_dump($user);  // This will show all user data
        }

        if ($user && password_verify($password, $user['password'])) {
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
                'Purchase Manager'
            ];

            if (in_array($user['role'], $senior_roles)) {
                // Redirect senior roles to their respective dashboards
                switch($user['role']) {
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
    } catch(PDOException $e) {
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
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #e74c3c;
            --light-gray: #f5f7fa;
            --dark-gray: #2c3e50;
            --text-light: #ecf0f1;
            --text-dark: #2c3e50;
            --shadow-light: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-dark: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        body {
            background: var(--light-gray);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Raleway', sans-serif;
            background-image: linear-gradient(135deg, rgba(44, 62, 80, 0.05) 25%, transparent 25%, 
                              transparent 50%, rgba(44, 62, 80, 0.05) 50%, rgba(44, 62, 80, 0.05) 75%, 
                              transparent 75%, transparent);
            background-size: 40px 40px;
            animation: backgroundMove 50s linear infinite;
        }

        @keyframes backgroundMove {
            from {background-position: 0 0;}
            to {background-position: 1000px 1000px;}
        }

        .split-container {
            display: flex;
            width: 90%;
            max-width: 1200px;
            height: 650px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-dark);
            position: relative;
            transform: translateY(20px);
            opacity: 0;
            animation: fadeUp 0.8s ease-out forwards;
        }

        @keyframes fadeUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .blueprint-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                linear-gradient(to right, rgba(255,255,255,0.9) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255,255,255,0.9) 1px, transparent 1px);
            background-size: 20px 20px;
            pointer-events: none;
            opacity: 0.15;
        }

        .image-section {
            flex: 1.2;
            position: relative;
            overflow: hidden;
        }

        .image-section img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scale(1.05);
            transition: transform 30s ease;
        }

        .split-container:hover .image-section img {
            transform: scale(1.15);
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(44, 62, 80, 0.7) 0%, rgba(44, 62, 80, 0.5) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px;
            color: white;
        }

        .image-overlay h2 {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: 700;
            opacity: 0;
            transform: translateX(-30px);
            animation: slideInFade 0.8s ease-out 0.3s forwards;
            position: relative;
        }

        .image-overlay h2:after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 0;
            height: 3px;
            background: var(--accent-color);
            animation: lineGrow 1.2s ease-out 1.2s forwards;
        }

        @keyframes lineGrow {
            to { width: 80px; }
        }

        .image-overlay p {
            font-size: 1.1rem;
            line-height: 1.7;
            font-weight: 300;
            max-width: 80%;
            opacity: 0;
            transform: translateX(-20px);
            animation: slideInFade 0.8s ease-out 0.6s forwards;
        }

        @keyframes slideInFade {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .architectural-elements {
            position: absolute;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 15px;
        }

        .element {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: translateY(20px);
            opacity: 0;
            animation: elementFadeIn 0.5s ease-out forwards;
        }

        .element:nth-child(1) { animation-delay: 1.3s; }
        .element:nth-child(2) { animation-delay: 1.5s; }
        .element:nth-child(3) { animation-delay: 1.7s; }

        @keyframes elementFadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .element i {
            color: white;
            font-size: 24px;
        }

        .login-section {
            flex: 0.8;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: white;
            position: relative;
            overflow: hidden;
        }

        .corner-accent {
            position: absolute;
            width: 300px;
            height: 300px;
            background: var(--light-gray);
            top: -150px;
            right: -150px;
            border-radius: 50%;
            z-index: 0;
            opacity: 0.5;
        }

        .corner-accent:before {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(231, 76, 60, 0.1);
            top: 120px;
            right: 120px;
            border-radius: 50%;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .login-header {
            margin-bottom: 35px;
            position: relative;
        }

        .login-header h3 {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 10px;
            opacity: 0;
            transform: translateY(10px);
            animation: fadeUp 0.6s ease-out 0.8s forwards;
        }

        .login-header p {
            color: #777;
            margin: 0;
            opacity: 0;
            transform: translateY(10px);
            animation: fadeUp 0.6s ease-out 1s forwards;
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 25px;
            border: none;
            box-shadow: var(--shadow-light);
            opacity: 0;
            animation: fadeIn 0.6s ease-out 0.4s forwards;
        }

        /* Enhanced form styling */
        .form-group {
            margin-bottom: 30px;
            position: relative;
            opacity: 0;
            transform: translateY(10px);
            background: #f0f4f8;
            border-radius: 16px;
            padding: 25px 20px 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .form-group:nth-child(1) { animation: fadeUp 0.5s ease-out 1.1s forwards; }
        .form-group:nth-child(2) { animation: fadeUp 0.5s ease-out 1.3s forwards; }

        .form-group:hover {
            background: #f8fafc;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
        }

        .form-group.active {
            border-left: 4px solid var(--accent-color);
            background: white;
        }

        .form-group label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 12px;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            display: block;
            transition: all 0.3s;
            position: absolute;
            top: 12px;
            left: 50px;
            opacity: 0.7;
        }

        .form-control {
            height: 45px;
            padding: 10px 10px 10px 40px;
            font-size: 16px;
            border: none;
            background-color: transparent;
            color: var(--text-dark);
            font-weight: 500;
            width: 100%;
            outline: none;
        }

        .form-control:focus {
            border: none;
            box-shadow: none;
            background-color: transparent;
        }

        .form-control:focus + .input-icon {
            color: var(--accent-color);
            transform: scale(1.1);
        }

        .input-icon {
            position: absolute;
            left: 20px;
            top: 35px;
            color: #a0aec0;
            transition: all 0.3s ease;
        }

        .btn-login {
            background: var(--accent-color);
            border: none;
            height: 55px;
            font-weight: 600;
            font-size: 16px;
            letter-spacing: 0.5px;
            border-radius: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.2);
            position: relative;
            overflow: hidden;
            margin-top: 10px;
            opacity: 0;
            transform: translateY(10px);
            animation: fadeUp 0.5s ease-out 1.5s forwards;
        }

        .btn-login:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.5s;
        }

        .btn-login:hover {
            background: #c0392b;
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(231, 76, 60, 0.3);
        }

        .btn-login:hover:before {
            left: 100%;
        }

        .signup-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.95rem;
            opacity: 0;
            animation: fadeIn 0.6s ease-out 1.7s forwards;
        }

        .signup-link a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
            position: relative;
        }

        .signup-link a:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--accent-color);
            transition: width 0.3s ease;
        }

        .signup-link a:hover:after {
            width: 100%;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        @keyframes shimmer {
            0% { background-position: -468px 0; }
            100% { background-position: 468px 0; }
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes wave {
            0% { transform: rotate(0deg); }
            20% { transform: rotate(15deg); }
            40% { transform: rotate(-10deg); }
            60% { transform: rotate(5deg); }
            80% { transform: rotate(-5deg); }
            100% { transform: rotate(0deg); }
        }

        .shape {
            position: absolute;
            background: rgba(231, 76, 60, 0.05);
            border-radius: 50%;
            z-index: 0;
        }

        .shape-1 {
            width: 150px;
            height: 150px;
            bottom: -75px;
            left: -75px;
            animation: float 6s ease-in-out infinite;
        }

        .shape-2 {
            width: 100px;
            height: 100px;
            top: 50px;
            right: -30px;
            animation: float 8s ease-in-out infinite 1s;
        }

        .shape-3 {
            width: 70px;
            height: 70px;
            top: 70%;
            left: 20%;
            background: rgba(44, 62, 80, 0.05);
            animation: float 7s ease-in-out infinite 0.5s;
        }

        /* Responsive styling */
        @media (max-width: 992px) {
            .split-container {
                width: 95%;
                height: auto;
                flex-direction: column;
            }

            .image-section {
                height: 300px;
            }

            .login-section {
                padding: 40px 20px;
            }

            .image-overlay h2 {
                font-size: 2.5rem;
            }

            .architectural-elements {
                bottom: 10px;
                right: 10px;
            }

            .element {
                width: 50px;
                height: 50px;
            }
        }

        @media (max-width: 576px) {
            .image-section {
                height: 250px;
            }

            .image-overlay h2 {
                font-size: 2rem;
            }

            .image-overlay p {
                font-size: 1rem;
                max-width: 100%;
            }

            .login-header h3 {
                font-size: 1.8rem;
            }

            .architectural-elements {
                display: none;
            }
        }

        /* Profile image styles */
        .employee-profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-color);
            padding: 2px;
            transition: all 0.3s ease;
        }

        .employee-profile-img:hover {
            transform: scale(1.1);
        }

        .profile img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .employee-name {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Loading animation for images */
        .profile-loading {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
    </style>
</head>
<body>
    <div class="split-container">
        <div class="image-section">
            <img src="images/login.jpg" alt="Modern Architecture">
            <div class="image-overlay">
                <h2>ArchitectsHive</h2>
                <p>Where vision meets precision — our platform empowers architects to collaborate, create, and transform spaces with unmatched efficiency.</p>
                
                <div class="architectural-elements">
                    <div class="element">
                        <i class="fas fa-drafting-compass"></i>
                    </div>
                    <div class="element">
                        <i class="fas fa-ruler-combined"></i>
                    </div>
                    <div class="element">
                        <i class="fas fa-pencil-ruler"></i>
                    </div>
                </div>
            </div>
            <div class="blueprint-pattern"></div>
        </div>
        
        <div class="login-section">
            <div class="corner-accent"></div>
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            
            <div class="login-container">
                <div class="login-header">
                    <h3>Welcome Back</h3>
                    <p>Please sign in to access your workspace</p>
                </div>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger animate__animated animate__shakeX">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="form-group">
                        <label for="login_identifier">Email / Username / Employee ID</label>
                        <input type="text" 
                               class="form-control" 
                               id="login_identifier" 
                               name="login_identifier" 
                               required 
                               placeholder="Enter your credentials">
                        <i class="fas fa-user input-icon"></i>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               required 
                               placeholder="Enter your password">
                        <i class="fas fa-lock input-icon"></i>
                    </div>

                    <button type="submit" class="btn btn-login btn-block text-white">
                        Sign In <i class="fas fa-sign-in-alt ml-2"></i>
                    </button>
                </form>

                <div class="signup-link">
                    <a href="signup">Don't have an account? Sign up</a>
                    <div class="mt-2">
                        <a href="#" id="forgot-password-link">Forgot your password?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="js/reset_password.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle image loading
        const profileImages = document.querySelectorAll('.employee-profile-img, .profile img');
        profileImages.forEach(img => {
            img.addEventListener('load', function() {
                this.classList.remove('profile-loading');
            });
            img.addEventListener('error', function() {
                this.src = 'assets/default-profile.png';
                this.classList.remove('profile-loading');
            });
            img.classList.add('profile-loading');
        });

        // Enhanced form interactions
        const formGroups = document.querySelectorAll('.form-group');
        const inputs = document.querySelectorAll('.form-control');

        inputs.forEach(input => {
            // Add active class on focus
            input.addEventListener('focus', function() {
                this.closest('.form-group').classList.add('active');
                
                // Subtle animation
                const group = this.closest('.form-group');
                group.style.transform = 'translateX(5px)';
                setTimeout(() => {
                    group.style.transform = 'translateX(0)';
                }, 300);
            });

            // Remove active class on blur if empty
            input.addEventListener('blur', function() {
                if (this.value === '') {
                    this.closest('.form-group').classList.remove('active');
                }
            });

            // Check if input has value on load
            if (input.value !== '') {
                input.closest('.form-group').classList.add('active');
            }
        });

        // Button animation
        const loginButton = document.querySelector('.btn-login');
        loginButton.addEventListener('mouseenter', function() {
            this.classList.add('animate__animated', 'animate__pulse');
            setTimeout(() => {
                this.classList.remove('animate__animated', 'animate__pulse');
            }, 1000);
        });

        // Add element animations
        const elements = document.querySelectorAll('.element i');
        elements.forEach(element => {
            element.addEventListener('mouseenter', function() {
                this.style.animation = 'wave 1s ease';
                setTimeout(() => {
                    this.style.animation = '';
                }, 1000);
            });
        });

        // Add parallax effect to background
        document.addEventListener('mousemove', function(e) {
            const moveX = (e.clientX - window.innerWidth / 2) * 0.01;
            const moveY = (e.clientY - window.innerHeight / 2) * 0.01;
            
            const shapes = document.querySelectorAll('.shape');
            shapes.forEach((shape, index) => {
                const factor = (index + 1) * 0.8;
                shape.style.transform = `translate(${moveX * factor}px, ${moveY * factor}px)`;
            });
            
            const imageSection = document.querySelector('.image-section img');
            imageSection.style.transform = `scale(1.05) translate(${moveX * -0.5}px, ${moveY * -0.5}px)`;
        });

        // Add typing animation for login header
        const welcomeText = document.querySelector('.login-header h3');
        const text = welcomeText.textContent;
        welcomeText.textContent = '';
        
        let charIndex = 0;
        function typeWriter() {
            if (charIndex < text.length) {
                welcomeText.textContent += text.charAt(charIndex);
                charIndex++;
                setTimeout(typeWriter, 100);
            }
        }
        
        // Start typing animation after a delay
        setTimeout(typeWriter, 1000);
    });

    // Update profile image in real-time after upload
    function updateProfileImages(newImageUrl) {
        const profileImages = document.querySelectorAll(`img[src*="${newImageUrl.split('_')[1]}"]`);
        profileImages.forEach(img => {
            img.src = newImageUrl + '?v=' + new Date().getTime();
        });
    }
    </script>
</body>
</html>
