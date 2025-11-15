<?php
session_start();
require_once 'config.php';

// Check if HR is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Generate unique employee ID (e.g., EMP001, EMP002)
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(unique_id, 4) AS UNSIGNED)) as max_id FROM users WHERE unique_id LIKE 'EMP%'");
        $result = $stmt->fetch();
        $next_id = $result['max_id'] ? $result['max_id'] + 1 : 1;
        $unique_id = 'EMP' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

        // Hash password
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Insert new employee
        $stmt = $pdo->prepare("INSERT INTO users (unique_id, username, email, password, department, designation, role, status) VALUES (?, ?, ?, ?, ?, ?, 'employee', 'Active')");
        
        $stmt->execute([
            $unique_id,
            $_POST['username'],
            $_POST['email'],
            $hashed_password,
            $_POST['department'],
            $_POST['designation']
        ]);

        $_SESSION['success'] = "Employee added successfully! Their Employee ID is: " . $unique_id;
        header('Location: employees.php');
        exit();

    } catch(PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
        }
        .form-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .form-header h2 {
            color: #2c3e50;
            font-size: 24px;
            font-weight: 600;
        }
        .form-group label {
            font-weight: 500;
            color: #2c3e50;
        }
        .btn-submit {
            background: #dc3545;
            border: none;
            padding: 12px 30px;
            font-weight: 500;
        }
        .btn-submit:hover {
            background: #c82333;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2c3e50;
            text-decoration: none;
        }
        .back-link:hover {
            color: #dc3545;
            text-decoration: none;
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="hr_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="form-container">
            <div class="form-header">
                <h2>Add New Employee</h2>
            </div>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <form action="add_employee.php" method="POST">
                <div class="form-group">
                    <label for="username">Full Name</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="department">Department</label>
                    <select class="form-control" id="department" name="department" required>
                        <option value="">Select Department</option>
                        <option value="IT">IT</option>
                        <option value="HR">HR</option>
                        <option value="Finance">Finance</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Operations">Operations</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="designation">Designation</label>
                    <input type="text" class="form-control" id="designation" name="designation" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <small class="form-text text-muted">
                        Password must be at least 8 characters long and include numbers and special characters.
                    </small>
                </div>

                <button type="submit" class="btn btn-submit btn-block">
                    <i class="fas fa-plus-circle"></i> Add Employee
                </button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        // Add password validation if needed
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const isValid = password.length >= 8 && /\d/.test(password) && /[!@#$%^&*]/.test(password);
            this.setCustomValidity(isValid ? '' : 'Password must be at least 8 characters long and include numbers and special characters');
        });
    </script>
</body>
</html>
