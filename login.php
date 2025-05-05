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
                'Site Supervisor'
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
                    case 'Site Supervisor':
                        header('Location: site_supervision.php');
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
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        body {
            background: #f5f7fa;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .split-container {
            display: flex;
            width: 90%;
            max-width: 1200px;
            height: 600px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .image-section {
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        .image-section img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 20s ease;
        }

        .image-section:hover img {
            transform: scale(1.1);
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px;
            color: white;
        }

        .image-overlay h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .image-overlay p {
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .login-section {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
        }

        .card-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            text-align: center;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            border: none;
        }

        .card-header h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .form-control {
            height: 50px;
            padding: 10px 20px;
            font-size: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .input-icon {
            position: absolute;
            right: 15px;
            top: 45px;
            color: #6c757d;
        }

        .btn-login {
            background: #dc3545;
            border: none;
            height: 50px;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .signup-link {
            text-align: center;
            margin-top: 20px;
        }

        .signup-link a {
            color: #dc3545;
            text-decoration: none;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }

        /* Add animation classes */
        .animate-slide-right {
            animation: slideInRight 1s ease-out;
        }

        .animate-fade-in {
            animation: fadeIn 1s ease-out;
        }

        @media (max-width: 768px) {
            .split-container {
                flex-direction: column;
                height: auto;
            }

            .image-section {
                height: 300px;
            }
        }

        /* Add these styles to your CSS */
        .employee-profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dc3545;
            padding: 2px;
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

        /* Add loading animation for images */
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
        <div class="image-section animate-fade-in">
            <img src="https://images.unsplash.com/photo-1487958449943-2429e8be8625?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Modern Architecture">
            <div class="image-overlay">
                <h2 class="animate__animated animate__fadeInLeft">ArchitectsHive</h2>
                <p class="animate__animated animate__fadeInLeft animate__delay-1s">Transform your architectural vision into reality with our innovative platform.</p>
            </div>
        </div>
        
        <div class="login-section animate-slide-right">
            <div class="login-container">
                <h3 class="mb-4">Welcome Back</h3>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger animate__animated animate__shakeX">
                        <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
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
                               placeholder="Enter your email, username, or ID">
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

                    <button type="submit" class="btn btn-login btn-block">
                        Login <i class="fas fa-sign-in-alt ml-2"></i>
                    </button>
                </form>

                <div class="signup-link">
                    <a href="signup">Don't have an account? Sign up</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    // Add this to your existing JavaScript
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
