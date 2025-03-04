<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Debug: Print received data
        error_log("Received POST data: " . print_r($_POST, true));

        // Get form data with validation
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = trim($_POST['role']);
        $reporting_manager = isset($_POST['reporting_manager']) ? trim($_POST['reporting_manager']) : null;

        // Validate required fields
        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            throw new Exception("All fields are required");
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Generate unique ID based on role
        $prefix = '';
        switch($role) {
            case 'admin':
                $prefix = 'ADM';
                break;
            case 'HR':
                $prefix = 'HR';
                break;
            case 'Senior Manager (Studio)':
                $prefix = 'SMS';
                break;
            case 'Senior Manager (Site)':
                $prefix = 'SMT';
                break;
            case 'Senior Manager (Marketing)':
                $prefix = 'SMM';
                break;
            case 'Senior Manager (Sales)':
                $prefix = 'SML';
                break;
            case 'Design Team':
                $prefix = 'DT';
                break;
            case 'Working Team':
                $prefix = 'WT';
                break;
            case '3D Designing Team':
                $prefix = '3DT';
                break;
            case 'Studio Trainees':
                $prefix = 'STR';
                break;
            case 'Business Developer':
                $prefix = 'BD';
                break;
            case 'Social Media Manager':
                $prefix = 'SMD';
                break;
            case 'Site Manager':
                $prefix = 'STM';
                break;
            case 'Site Supervisor':
                $prefix = 'STS';
                break;
            case 'Site Trainee':
                $prefix = 'STT';
                break;
            case 'Relationship Manager':
                $prefix = 'RM';
                break;
            case 'Sales Manager':
                $prefix = 'SM';
                break;
            case 'Sales Consultant':
                $prefix = 'SC';
                break;
            case 'Field Sales Representative':
                $prefix = 'FSR';
                break;
            default:
                $prefix = 'EMP';
        }

        // Get next ID number
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(unique_id, :len) AS UNSIGNED)) as max_id FROM users WHERE unique_id LIKE :prefix");
        $stmt->execute([
            'len' => strlen($prefix) + 1,
            'prefix' => $prefix . '%'
        ]);
        $result = $stmt->fetch();
        $next_id = $result['max_id'] ? $result['max_id'] + 1 : 1;
        $unique_id = $prefix . str_pad($next_id, 3, '0', STR_PAD_LEFT);

        // Debug: Print values before insertion
        error_log("Inserting user with role: " . $role);
        error_log("Unique ID generated: " . $unique_id);

        // Debug: Print the exact role being inserted
        error_log("Attempting to insert user with following details:");
        error_log("Username: " . $username);
        error_log("Email: " . $email);
        error_log("Role: " . $role);
        error_log("Unique ID: " . $unique_id);
        error_log("Reporting Manager: " . $reporting_manager);

        // Prepare the SQL statement with explicit column names
        $sql = "INSERT INTO users (
                    username, 
                    email, 
                    password, 
                    role, 
                    unique_id, 
                    reporting_manager,
                    status,
                    created_at
                ) VALUES (
                    :username, 
                    :email, 
                    :password, 
                    :role, 
                    :unique_id, 
                    :reporting_manager,
                    'active',
                    :created_at
                )";
        
        $current_time = date('Y-m-d H:i:s');
        
        $params = [
            ':username' => $username,
            ':email' => $email,
            ':password' => $hashed_password,
            ':role' => $role,
            ':unique_id' => $unique_id,
            ':reporting_manager' => $reporting_manager,
            ':created_at' => $current_time
        ];

        // Debug: Print the SQL and parameters
        error_log("SQL Query: " . $sql);
        error_log("Parameters: " . print_r($params, true));

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);

        // Debug: Check the result
        if ($result) {
            error_log("Insert successful");
            
            // After successful insert, verify the data
            $verify_stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $verify_stmt->execute([$email]);
            $new_user = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Verification of new user: " . print_r($new_user, true));
            
            // Set session variables based on your table structure
            $_SESSION['user_id'] = $new_user['id'];
            $_SESSION['username'] = $new_user['username'];
            $_SESSION['role'] = $new_user['role'];
            $_SESSION['unique_id'] = $new_user['unique_id'];
            $_SESSION['employee_id'] = $new_user['employee_id'];

            // Set current timestamp for created_at and last_login
            $current_time = date('Y-m-d H:i:s');
            
            // Update the user record with additional details
            $update_sql = "UPDATE users SET 
                          created_at = :created_at,
                          last_login = :last_login,
                          status = 'active'
                          WHERE id = :id";
            
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                ':created_at' => $current_time,
                ':last_login' => $current_time,
                ':id' => $new_user['id']
            ]);

            // Redirect based on role
            $senior_roles = [
                'admin', 
                'HR', 
                'Senior Manager (Studio)', 
                'Senior Manager (Site)', 
                'Senior Manager (Marketing)', 
                'Senior Manager (Sales)'
            ];

            if (in_array($new_user['role'], $senior_roles)) {
                $_SESSION['success'] = "Registration successful! Your Employee ID is: " . $unique_id;
                header('Location: login.php');
            } else {
                // For all other roles, redirect to similar_dashboard.php
                header('Location: similar_dashboard.php');
            }
            exit();
        } else {
            error_log("Insert failed. PDO Error: " . print_r($stmt->errorInfo(), true));
            throw new Exception("Failed to create user account");
        }

    } catch(PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        error_log("Error Code: " . $e->getCode());
        throw $e;
    } catch(Exception $e) {
        error_log("General Error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }
}
?>

<!-- HTML Form -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | ArchitectsHive</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }

        .signup-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 25px;
            border-bottom: none;
        }

        .card-header h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .card-body {
            padding: 40px;
            background: white;
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
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        select.form-control {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 40px;
            background: url("data:image/svg+xml,<svg height='10px' width='10px' viewBox='0 0 16 16' fill='%23000000' xmlns='http://www.w3.org/2000/svg'><path d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/></svg>") no-repeat;
            background-position: calc(100% - 15px) center;
            background-color: white;
        }

        .btn-submit {
            height: 50px;
            font-weight: 600;
            font-size: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(0, 0, 0, 0.1);
        }

        .input-group-text {
            background: none;
            border: none;
            color: #667eea;
        }

        .form-text {
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        /* Custom styling for success/error messages */
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-body {
                padding: 20px;
            }
            
            .signup-container {
                margin: 0 15px;
            }
        }

        /* Animation for form elements */
        .form-group {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.5s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }
        .form-group:nth-child(5) { animation-delay: 0.5s; }
    </style>
</head>
<body>
    <div class="container signup-container">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus mr-2"></i>Create Account</h3>
            </div>
            <div class="card-body">
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <form action="signup.php" method="POST">
                    <div class="form-group">
                        <label for="username">Full Name</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                            </div>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                            </div>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="role">Select Role</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-user-tag"></i>
                                </span>
                            </div>
                            <select class="form-control" id="role" name="role" required>
                                <option value="">Choose role...</option>
                                <option value="admin">Admin</option>
                                <option value="HR">HR</option>
                                <option value="Senior Manager (Studio)">Senior Manager (Studio)</option>
                                <option value="Senior Manager (Site)">Senior Manager (Site)</option>
                                <option value="Senior Manager (Marketing)">Senior Manager (Marketing)</option>
                                <option value="Senior Manager (Sales)">Senior Manager (Sales)</option>
                                <option value="Design Team">Design Team</option>
                                <option value="Working Team">Working Team</option>
                                <option value="Draughtsman">Draughtsman</option>
                                <option value="3D Designing Team">3D Designing Team</option>
                                <option value="Studio Trainees">Studio Trainees</option>
                                <option value="Business Developer">Business Developer</option>
                                <option value="Social Media Manager">Social Media Manager</option>
                                <option value="Site Manager">Site Manager</option>
                                <option value="Site Supervisor">Site Supervisor</option>
                                <option value="Site Trainee">Site Trainee</option>
                                <option value="Relationship Manager">Relationship Manager</option>
                                <option value="Sales Manager">Sales Manager</option>
                                <option value="Sales Consultant">Sales Consultant</option>
                                <option value="Field Sales Representative">Field Sales Representative</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="reportingManagerDiv" style="display: none;">
                        <label for="reporting_manager">Reporting Manager</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-user-tie"></i>
                                </span>
                            </div>
                            <select class="form-control" id="reporting_manager" name="reporting_manager">
                                <option value="">Select Manager...</option>
                                <option value="Sr. Manager (Studio)">Sr. Manager (Studio)</option>
                                <option value="Sr. Manager (Business Developer)">Sr. Manager (Business Developer)</option>
                                <option value="Sr. Manager (Relationship Manager)">Sr. Manager (Relationship Manager)</option>
                                <option value="Sr. Manager (Operations)">Sr. Manager (Operations)</option>
                                <option value="Sr. Manager (HR)">Sr. Manager (HR)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                            </div>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle mr-1"></i>
                            Password must be at least 8 characters long and include numbers and special characters.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-submit btn-block">
                        <i class="fas fa-user-plus mr-2"></i>Create Account
                    </button>
                </form>

                <div class="text-center mt-4">
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-sign-in-alt mr-1"></i>
                        Already have an account? Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const role = document.getElementById('role').value;
            if (!role) {
                e.preventDefault();
                alert('Please select a role');
                return false;
            }
            
            // Log the form data before submission
            console.log('Submitting form with role:', role);
        });

        // Show/hide reporting manager based on role
        $('#role').change(function() {
            const role = $(this).val();
            console.log('Selected role:', role); // Debug log
            
            const seniorRoles = ['admin', 'HR', 'Senior Manager (Studio)', 'Senior Manager (Site)', 
                               'Senior Manager (Marketing)', 'Senior Manager (Sales)'];
            
            if (!seniorRoles.includes(role)) {
                $('#reportingManagerDiv').show();
                $('#reporting_manager').prop('required', true);
                
                // Auto-select reporting manager based on role
                let manager = '';
                if (['Design Team', 'Working Team', '3D Designing Team', 'Studio Trainees'].includes(role)) {
                    manager = 'Sr. Manager (Studio)';
                } else if (role === 'Business Developer') {
                    manager = 'Sr. Manager (Business Developer)';
                } else if (['Relationship Manager', 'Sales Manager', 'Sales Consultant', 'Field Sales Representative'].includes(role)) {
                    manager = 'Sr. Manager (Relationship Manager)';
                } else if (['Site Manager', 'Site Supervisor', 'Site Trainee'].includes(role)) {
                    manager = 'Sr. Manager (Operations)';
                } else if (role === 'Social Media Manager') {
                    manager = 'Sr. Manager (HR)';
                }
                
                $('#reporting_manager').val(manager);
            } else {
                $('#reportingManagerDiv').hide();
                $('#reporting_manager').prop('required', false);
            }
        });
    </script>
</body>
</html>
