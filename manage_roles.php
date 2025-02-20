<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}

// Fetch user details
$user_id = isset($_GET['id']) ? $_GET['id'] : null;
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch all available roles
$roles_stmt = $pdo->query("SELECT * FROM roles ORDER BY role_name");
$available_roles = $roles_stmt->fetchAll();

// Fetch user's current roles
$user_roles_stmt = $pdo->prepare("
    SELECT ur.*, r.role_name, r.description,
           u.username as assigned_by_name
    FROM user_roles ur
    JOIN roles r ON ur.role_id = r.id
    LEFT JOIN users u ON ur.assigned_by = u.id
    WHERE ur.user_id = ?
    ORDER BY ur.assigned_date DESC
");
$user_roles_stmt->execute([$user_id]);
$user_roles = $user_roles_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage User Roles - <?php echo htmlspecialchars($user['username']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            color: #1a1a1a;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        /* Header Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 1.8rem;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header h1 i {
            color: #4834d4;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.6rem 1.2rem;
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
        }

        /* Card Styles */
        .card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }

        .card h2 {
            font-size: 1.4rem;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card h2 i {
            color: #4834d4;
        }

        /* Form Styles */
        .role-form {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 0.5rem;
        }

        .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #2d3748;
            background: #fff;
            cursor: pointer;
        }

        /* Roles Grid */
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .role-card {
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .role-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .role-header h3 {
            font-size: 1.1rem;
            color: #2d3748;
        }

        .role-info {
            margin-bottom: 1.5rem;
        }

        .role-info p {
            font-size: 0.9rem;
            color: #4a5568;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Status Badge */
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-badge.active {
            background: #c6f6d5;
            color: #2f855a;
        }

        .status-badge.inactive {
            background: #fed7d7;
            color: #c53030;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #4834d4;
            color: white;
        }

        .btn-primary:hover {
            background: #3c2bb3;
        }

        .btn-danger {
            background: #e53e3e;
            color: white;
        }

        .btn-danger:hover {
            background: #c53030;
        }

        .btn-success {
            background: #38a169;
            color: white;
        }

        .btn-success:hover {
            background: #2f855a;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .role-form {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .roles-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-user-tag"></i>
                Manage Roles: <?php echo htmlspecialchars($user['username']); ?>
            </h1>
            <a href="view_employee.php?id=<?php echo $user_id; ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Employee
            </a>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                <?php 
                    echo $_SESSION['message']; 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Assign New Role Section -->
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Assign New Role</h2>
            <form action="process_role_assignment.php" method="POST" class="role-form">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <div class="form-group">
                    <label for="role">Select Role</label>
                    <select name="role_id" required>
                        <option value="">Choose a role...</option>
                        <?php foreach ($available_roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>">
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Assign Role
                </button>
            </form>
        </div>

        <!-- Current Roles Section -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Current Roles</h2>
            <div class="roles-grid">
                <?php foreach ($user_roles as $role): ?>
                    <div class="role-card <?php echo $role['status'] === 'active' ? 'active' : 'inactive'; ?>">
                        <div class="role-header">
                            <h3><?php echo htmlspecialchars($role['role_name']); ?></h3>
                            <span class="status-badge <?php echo $role['status']; ?>">
                                <?php echo ucfirst($role['status']); ?>
                            </span>
                        </div>
                        <div class="role-info">
                            <p><i class="fas fa-clock"></i> Assigned: 
                                <?php echo date('M d, Y', strtotime($role['assigned_date'])); ?>
                            </p>
                            <p><i class="fas fa-user"></i> By: 
                                <?php echo htmlspecialchars($role['assigned_by_name']); ?>
                            </p>
                        </div>
                        <div class="role-actions">
                            <?php if ($role['status'] === 'active'): ?>
                                <button onclick="updateRoleStatus(<?php echo $role['id']; ?>, 'inactive')" 
                                        class="btn btn-danger">
                                    <i class="fas fa-times"></i> Deactivate
                                </button>
                            <?php else: ?>
                                <button onclick="updateRoleStatus(<?php echo $role['id']; ?>, 'active')" 
                                        class="btn btn-success">
                                    <i class="fas fa-check"></i> Activate
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        function updateRoleStatus(roleId, status) {
            if (confirm('Are you sure you want to ' + status + ' this role?')) {
                window.location.href = `process_role_update.php?role_id=${roleId}&status=${status}`;
            }
        }
    </script>
</body>
</html> 