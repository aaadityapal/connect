<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}

// Fetch all roles
$roles_query = $pdo->query("
    SELECT 
        r.*,
        COUNT(DISTINCT ur.user_id) as assigned_users,
        u.username as created_by
    FROM roles r
    LEFT JOIN user_roles ur ON r.id = ur.role_id AND ur.status = 'active'
    LEFT JOIN users u ON r.created_by = u.id
    GROUP BY r.id
    ORDER BY r.role_name
");
$roles = $roles_query->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management - HR Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Add your existing dashboard CSS here */
        
        /* Role Management specific styles */
        .roles-container {
            padding: 20px;
        }

        .roles-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .add-role-btn {
            background: #4834d4;
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .add-role-btn:hover {
            background: #3c2bb3;
            transform: translateY(-2px);
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .role-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
        }

        .role-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .role-title {
            font-size: 1.2rem;
            color: #2d3748;
            font-weight: 600;
        }

        .role-actions {
            display: flex;
            gap: 8px;
        }

        .role-action-btn {
            padding: 6px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .edit-btn {
            color: #4834d4;
            background: #edf2f7;
        }

        .delete-btn {
            color: #e53e3e;
            background: #fff5f5;
        }

        .role-info {
            color: #4a5568;
            font-size: 0.9rem;
        }

        .role-stats {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            color: #718096;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #4a5568;
        }

        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #4a5568;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .modal-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .save-btn {
            background: #4834d4;
            color: white;
        }

        .cancel-btn {
            background: #e2e8f0;
            color: #4a5568;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include your dashboard sidebar here -->
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="roles-container">
                <div class="roles-header">
                    <h1><i class="fas fa-user-tag"></i> Role Management</h1>
                    <button class="add-role-btn" onclick="openAddRoleModal()">
                        <i class="fas fa-plus"></i> Add New Role
                    </button>
                </div>

                <div class="roles-grid">
                    <?php foreach ($roles as $role): ?>
                        <div class="role-card">
                            <div class="role-header">
                                <h3 class="role-title">
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </h3>
                                <div class="role-actions">
                                    <button class="role-action-btn edit-btn" 
                                            onclick="openEditRoleModal(<?php echo $role['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="role-action-btn delete-btn" 
                                            onclick="deleteRole(<?php echo $role['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="role-info">
                                <?php echo htmlspecialchars($role['description']); ?>
                            </div>
                            <div class="role-stats">
                                <div class="stat-item">
                                    <i class="fas fa-users"></i>
                                    <?php echo $role['assigned_users']; ?> Users
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y', strtotime($role['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Role Modal -->
    <div id="addRoleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Role</h2>
                <button class="close-modal" onclick="closeModal('addRoleModal')">&times;</button>
            </div>
            <form class="modal-form" action="process_role.php" method="POST">
                <div class="form-group">
                    <label for="role_name">Role Name</label>
                    <input type="text" id="role_name" name="role_name" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn cancel-btn" onclick="closeModal('addRoleModal')">Cancel</button>
                    <button type="submit" class="modal-btn save-btn">Save Role</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddRoleModal() {
            document.getElementById('addRoleModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function openEditRoleModal(roleId) {
            // Implement edit role functionality
        }

        function deleteRole(roleId) {
            if (confirm('Are you sure you want to delete this role?')) {
                window.location.href = `process_role.php?action=delete&id=${roleId}`;
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html> 