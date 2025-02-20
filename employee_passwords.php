<?php
require_once 'config/db_connect.php';
session_start();

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Fetch users grouped by role
$query = "SELECT id, username, email, password, role FROM users ORDER BY role";
$result = mysqli_query($conn, $query);

// Group users by role
$users_by_role = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users_by_role[$row['role']][] = $row;
}

// Handle password reset
if (isset($_POST['reset_password'])) {
    $user_id = $_POST['user_id'];
    $new_password = bin2hex(random_bytes(8));
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $update_query = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "Password has been reset. New password: " . $new_password;
    } else {
        $_SESSION['error'] = "Failed to reset password.";
    }
    
    // Redirect to refresh the page
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Display messages
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success">' . $_SESSION['message'] . '</div>';
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Passwords Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1200px;
        }
        .page-header {
            background: linear-gradient(135deg, #0062cc, #0096ff);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .role-section {
            background: white;
            margin-bottom: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .role-title {
            background: linear-gradient(to right, #f8f9fa, white);
            padding: 1rem 1.5rem;
            margin: 0;
            border-left: 4px solid #007bff;
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            background-color: #343a40;
            color: white;
            border: none;
        }
        .table td {
            vertical-align: middle;
        }
        .badge {
            padding: 0.5em 1em;
            font-size: 0.85em;
        }
        .badge-admin {
            background-color: #dc3545;
        }
        .badge-manager {
            background-color: #28a745;
        }
        .badge-employee {
            background-color: #17a2b8;
        }
        .badge-user {
            background-color: #6c757d;
        }
        .btn-reset {
            background-color: #ffc107;
            border: none;
            color: #000;
            transition: all 0.3s;
        }
        .btn-reset:hover {
            background-color: #ffca2c;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .password-field {
            position: relative;
        }
        .password-input {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            font-family: monospace;
        }
        .alert {
            border-radius: 10px;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="page-header">
            <h2 class="mb-0"><i class="fas fa-key mr-2"></i>Employee Passwords Management</h2>
            <p class="mb-0 mt-2 text-light">Manage user passwords securely</p>
        </div>

        <?php foreach ($users_by_role as $role => $users): ?>
        <div class="role-section">
            <h3 class="role-title">
                <i class="fas <?php 
                    echo match($role) {
                        'admin' => 'fa-user-shield',
                        'manager' => 'fa-user-tie',
                        'employee' => 'fa-user',
                        default => 'fa-user-circle'
                    };
                ?> mr-2"></i>
                <?php echo ucfirst(htmlspecialchars($role)); ?> Users
            </h3>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><i class="fas fa-id-badge mr-2"></i>ID</th>
                            <th><i class="fas fa-user mr-2"></i>Username</th>
                            <th><i class="fas fa-envelope mr-2"></i>Email</th>
                            <th><i class="fas fa-tags mr-2"></i>Role</th>
                            <th><i class="fas fa-key mr-2"></i>Password</th>
                            <th><i class="fas fa-cogs mr-2"></i>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo htmlspecialchars($user['role']); ?>">
                                    <?php echo htmlspecialchars($user['role']); ?>
                                </span>
                            </td>
                            <td class="password-field">
                                <input type="password" 
                                       class="form-control password-input" 
                                       value="<?php echo htmlspecialchars($user['password']); ?>" 
                                       readonly>
                            </td>
                            <td>
                                <form method="POST" class="m-0" 
                                      onsubmit="return confirm('Are you sure you want to reset the password for <?php echo htmlspecialchars($user['username']); ?>?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="reset_password" class="btn btn-reset btn-sm">
                                        <i class="fas fa-sync-alt mr-1"></i> Reset Password
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
