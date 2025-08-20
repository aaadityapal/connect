<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // This will be implemented later for backend functionality
    // For now, just the UI is being created
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | ArchitectsHive</title>
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

        .reset-container {
            width: 90%;
            max-width: 500px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-dark);
            position: relative;
            transform: translateY(20px);
            opacity: 0;
            animation: fadeUp 0.8s ease-out forwards;
            padding: 40px;
        }

        @keyframes fadeUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reset-header {
            margin-bottom: 35px;
            position: relative;
            text-align: center;
        }

        .reset-header h3 {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 10px;
            opacity: 0;
            transform: translateY(10px);
            animation: fadeUp 0.6s ease-out 0.3s forwards;
        }

        .reset-header p {
            color: #777;
            margin: 0;
            opacity: 0;
            transform: translateY(10px);
            animation: fadeUp 0.6s ease-out 0.5s forwards;
        }

        .reset-icon {
            font-size: 3rem;
            color: var(--accent-color);
            margin-bottom: 20px;
            opacity: 0;
            animation: fadeIn 0.6s ease-out 0.2s forwards, pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
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

        .form-group:nth-child(1) { animation: fadeUp 0.5s ease-out 0.7s forwards; }
        .form-group:nth-child(2) { animation: fadeUp 0.5s ease-out 0.9s forwards; }
        .form-group:nth-child(3) { animation: fadeUp 0.5s ease-out 1.1s forwards; }

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

        .btn-reset {
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
            animation: fadeUp 0.5s ease-out 1.3s forwards;
        }

        .btn-reset:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.5s;
        }

        .btn-reset:hover {
            background: #c0392b;
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(231, 76, 60, 0.3);
        }

        .btn-reset:hover:before {
            left: 100%;
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.95rem;
            opacity: 0;
            animation: fadeIn 0.6s ease-out 1.5s forwards;
        }

        .login-link a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
            position: relative;
        }

        .login-link a:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--accent-color);
            transition: width 0.3s ease;
        }

        .login-link a:hover:after {
            width: 100%;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
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

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        /* Password strength indicator */
        .password-strength {
            height: 5px;
            margin-top: 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
            background: #ddd;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .strength-weak { width: 25%; background: #ff4d4d; }
        .strength-medium { width: 50%; background: #ffaa00; }
        .strength-strong { width: 75%; background: #73e600; }
        .strength-very-strong { width: 100%; background: #00cc44; }

        .password-match-indicator {
            margin-top: 5px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            opacity: 0;
        }

        .match-success {
            color: #00cc44;
            opacity: 1;
        }

        .match-error {
            color: #ff4d4d;
            opacity: 1;
        }

        /* Responsive styling */
        @media (max-width: 576px) {
            .reset-container {
                width: 95%;
                padding: 30px 20px;
            }

            .reset-header h3 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        
        <div class="reset-header">
            <div class="reset-icon">
                <i class="fas fa-key"></i>
            </div>
            <h3>Reset Your Password</h3>
            <p>Enter your email to receive a password reset link</p>
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

        <!-- Step 1: Email Verification Form -->
        <div id="step1Form">
            <form id="emailForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email" 
                           required 
                           placeholder="Enter your email address">
                    <i class="fas fa-envelope input-icon"></i>
                </div>

                <button type="button" id="sendResetLink" class="btn btn-reset btn-block text-white">
                    Send Reset Link <i class="fas fa-paper-plane ml-2"></i>
                </button>
            </form>
        </div>

        <!-- Step 2: Reset Password Form (Initially Hidden) -->
        <div id="step2Form" style="display: none;">
            <form id="resetForm">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" 
                           class="form-control" 
                           id="new_password" 
                           name="new_password" 
                           required 
                           placeholder="Enter your new password">
                    <i class="fas fa-lock input-icon"></i>
                    <div class="password-strength">
                        <div class="password-strength-bar"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" 
                           class="form-control" 
                           id="confirm_password" 
                           name="confirm_password" 
                           required 
                           placeholder="Confirm your new password">
                    <i class="fas fa-lock input-icon"></i>
                    <div class="password-match-indicator">Passwords match</div>
                </div>

                <button type="button" id="resetPassword" class="btn btn-reset btn-block text-white">
                    Reset Password <i class="fas fa-check ml-2"></i>
                </button>
            </form>
        </div>

        <div class="login-link">
            <a href="login.php">Back to Login</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
        const resetButtons = document.querySelectorAll('.btn-reset');
        resetButtons.forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.classList.add('animate__animated', 'animate__pulse');
                setTimeout(() => {
                    this.classList.remove('animate__animated', 'animate__pulse');
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
        });

        // Password strength checker
        const newPassword = document.getElementById('new_password');
        const passwordStrengthBar = document.querySelector('.password-strength-bar');
        
        if (newPassword) {
            newPassword.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength += 1;
                
                // Character variety checks
                if (/[A-Z]/.test(password)) strength += 1;
                if (/[0-9]/.test(password)) strength += 1;
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                
                // Update strength bar
                passwordStrengthBar.className = 'password-strength-bar';
                if (strength === 1) passwordStrengthBar.classList.add('strength-weak');
                else if (strength === 2) passwordStrengthBar.classList.add('strength-medium');
                else if (strength === 3) passwordStrengthBar.classList.add('strength-strong');
                else if (strength >= 4) passwordStrengthBar.classList.add('strength-very-strong');
            });
        }
        
        // Password match checker
        const confirmPassword = document.getElementById('confirm_password');
        const matchIndicator = document.querySelector('.password-match-indicator');
        
        if (confirmPassword && newPassword) {
            confirmPassword.addEventListener('input', function() {
                if (this.value === newPassword.value) {
                    matchIndicator.textContent = 'Passwords match';
                    matchIndicator.classList.add('match-success');
                    matchIndicator.classList.remove('match-error');
                } else {
                    matchIndicator.textContent = 'Passwords do not match';
                    matchIndicator.classList.add('match-error');
                    matchIndicator.classList.remove('match-success');
                }
            });
        }

        // Simulating the password reset flow (for UI demonstration)
        const sendResetLinkBtn = document.getElementById('sendResetLink');
        const step1Form = document.getElementById('step1Form');
        const step2Form = document.getElementById('step2Form');
        const resetPasswordBtn = document.getElementById('resetPassword');
        
        if (sendResetLinkBtn) {
            sendResetLinkBtn.addEventListener('click', function() {
                const email = document.getElementById('email').value;
                if (email) {
                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success animate__animated animate__fadeIn';
                    successAlert.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Reset link sent! Check your email.';
                    
                    // Insert before the form
                    step1Form.parentNode.insertBefore(successAlert, step1Form);
                    
                    // Hide step 1 and show step 2 after delay
                    setTimeout(() => {
                        step1Form.style.display = 'none';
                        step2Form.style.display = 'block';
                        
                        // Animate step 2 form groups
                        const step2Groups = step2Form.querySelectorAll('.form-group');
                        step2Groups.forEach((group, index) => {
                            group.style.animation = `fadeUp 0.5s ease-out ${0.3 + (index * 0.2)}s forwards`;
                        });
                        
                        // Animate button
                        resetPasswordBtn.style.animation = `fadeUp 0.5s ease-out ${0.3 + (step2Groups.length * 0.2)}s forwards`;
                    }, 2000);
                }
            });
        }
        
        if (resetPasswordBtn) {
            resetPasswordBtn.addEventListener('click', function() {
                const newPass = document.getElementById('new_password').value;
                const confirmPass = document.getElementById('confirm_password').value;
                
                if (newPass && confirmPass && newPass === confirmPass) {
                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success animate__animated animate__fadeIn';
                    successAlert.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Password reset successful! Redirecting to login...';
                    
                    // Insert before the form
                    step2Form.parentNode.insertBefore(successAlert, step2Form);
                    
                    // Hide step 2 form
                    step2Form.style.display = 'none';
                    
                    // Redirect after delay (in a real app)
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 3000);
                }
            });
        }
    });
    </script>
</body>
</html>
