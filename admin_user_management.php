<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if user has Admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: unauthorized.php");
    exit;
}

require_once __DIR__ . '/config/db_connect.php';

// Fetch all users
$query = "SELECT id, username, email, employee_id, position, designation, department, role, status, last_login FROM users WHERE deleted_at IS NULL ORDER BY username ASC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            padding: 20px 30px;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2em;
            font-weight: 600;
            color: #2a4365;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 0.95em;
            color: #718096;
        }

        .controls {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.95em;
            transition: all 0.2s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #2a4365;
            box-shadow: 0 0 0 3px rgba(42, 67, 101, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        .filter-btn {
            padding: 12px 20px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #2a4365;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95em;
            font-weight: 600;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover {
            border-color: #2a4365;
            background-color: #f8f9fa;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 700;
            color: #2a4365;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: background-color 0.2s ease;
        }

        tbody tr:hover {
            background-color: #f9fafb;
        }

        td {
            padding: 15px;
            color: #4a5568;
            font-size: 0.9em;
        }

        .user-name {
            font-weight: 600;
            color: #2a4365;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-active {
            background-color: #c6f6d5;
            color: #22543d;
        }

        .status-inactive {
            background-color: #fed7d7;
            color: #742a2a;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            background-color: #ebf8ff;
            color: #2c5aa0;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-edit {
            padding: 8px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85em;
            font-weight: 600;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        .empty-state i {
            font-size: 3em;
            color: #cbd5e0;
            margin-bottom: 15px;
            display: block;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5em;
            font-weight: 700;
            color: #2a4365;
            margin: 0;
        }

        .modal-close-btn {
            background: none;
            border: none;
            font-size: 1.5em;
            color: #718096;
            cursor: pointer;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .modal-close-btn:hover {
            background-color: #f0f4f8;
            color: #2a4365;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2a4365;
            font-size: 0.9em;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.95em;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 0.95em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background-color: #e2e8f0;
            color: #2a4365;
        }

        .btn-secondary:hover {
            background-color: #cbd5e0;
        }

        .info-box {
            background-color: #f0f4f8;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #4a5568;
        }

        .required {
            color: #e53e3e;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .pagination-btn {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #2a4365;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85em;
            transition: all 0.2s ease;
        }

        .pagination-btn:hover:not(:disabled) {
            background-color: #2a4365;
            color: white;
            border-color: #2a4365;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-info {
            color: #718096;
            font-size: 0.85em;
        }

        .loading-spinner {
            text-align: center;
            padding: 40px;
            color: #2a4365;
        }

        .loading-spinner i {
            font-size: 2.5em;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .header h1 {
                font-size: 1.5em;
            }

            .controls {
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .table-wrapper {
                font-size: 0.85em;
            }

            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Include the side panel -->
        <?php include 'includes/admin_panel.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-users-cog"></i> User Management</h1>
                <p>Manage user roles, positions, and designations</p>
            </div>

            <!-- Search and Filter -->
            <div class="controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by username, email, or employee ID...">
                </div>
                <button class="filter-btn" id="filterBtn">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>

            <!-- Users Table -->
            <div class="table-container">
                <div class="table-wrapper">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Employee ID</th>
                                <th>Position</th>
                                <th>Designation</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <!-- Table rows will be inserted here -->
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="empty-state" style="display: none;">
                    <i class="fas fa-users"></i>
                    <p>No users found</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editUserModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">Edit User</h2>
                <button type="button" class="modal-close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="editUserForm">
                <div class="modal-body">
                    <div class="info-box">
                        <strong>User ID:</strong> <span id="userIdInfo"></span><br>
                        <strong>Username:</strong> <span id="usernameInfo"></span><br>
                        <strong>Email:</strong> <span id="emailInfo"></span>
                    </div>

                    <div class="form-group">
                        <label for="position">Position <span class="required">*</span></label>
                        <input type="text" id="position" name="position" placeholder="e.g., Manager, Developer" required>
                    </div>

                    <div class="form-group">
                        <label for="designation">Designation <span class="required">*</span></label>
                        <input type="text" id="designation" name="designation" placeholder="e.g., Senior Manager, Junior Developer" required>
                    </div>

                    <div class="form-group">
                        <label for="department">Department <span class="required">*</span></label>
                        <input type="text" id="department" name="department" placeholder="e.g., Engineering, Sales" required>
                    </div>

                    <div class="form-group">
                        <label for="role">Role <span class="required">*</span></label>
                        <select id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="Admin">Admin</option>
                            <option value="Manager">Manager</option>
                            <option value="Purchase Manager">Purchase Manager</option>
                            <option value="Employee">Employee</option>
                            <option value="HR">HR</option>
                            <option value="Finance">Finance</option>
                            <option value="Supervisor">Supervisor</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" required>
                            <option value="">Select Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentUserId = null;
        let usersData = <?php echo json_encode($users); ?>;
        let filteredData = [...usersData];

        // Initialize table on page load
        document.addEventListener('DOMContentLoaded', function() {
            renderTable();
            setupEventListeners();
        });

        function renderTable() {
            const tbody = document.getElementById('usersTableBody');
            const emptyState = document.getElementById('emptyState');

            if (filteredData.length === 0) {
                tbody.innerHTML = '';
                emptyState.style.display = 'block';
                return;
            }

            emptyState.style.display = 'none';
            
            tbody.innerHTML = filteredData.map(user => `
                <tr>
                    <td class="user-name">${escapeHtml(user.username)}</td>
                    <td>${escapeHtml(user.email)}</td>
                    <td>${user.employee_id ? escapeHtml(user.employee_id) : '-'}</td>
                    <td>${user.position ? escapeHtml(user.position) : '-'}</td>
                    <td>${user.designation ? escapeHtml(user.designation) : '-'}</td>
                    <td>${user.department ? escapeHtml(user.department) : '-'}</td>
                    <td><span class="role-badge">${escapeHtml(user.role)}</span></td>
                    <td><span class="status-badge status-${user.status}">${escapeHtml(user.status)}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-edit" onclick="editUser(${user.id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function setupEventListeners() {
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                filteredData = usersData.filter(user => 
                    user.username.toLowerCase().includes(searchTerm) ||
                    user.email.toLowerCase().includes(searchTerm) ||
                    (user.employee_id && user.employee_id.toLowerCase().includes(searchTerm))
                );
                renderTable();
            });

            // Form submission
            document.getElementById('editUserForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveUserChanges();
            });
        }

        function editUser(userId) {
            const user = usersData.find(u => u.id === userId);
            
            if (!user) {
                alert('User not found');
                return;
            }

            currentUserId = userId;

            // Populate modal with user data
            document.getElementById('userIdInfo').textContent = user.id;
            document.getElementById('usernameInfo').textContent = user.username;
            document.getElementById('emailInfo').textContent = user.email;
            document.getElementById('position').value = user.position || '';
            document.getElementById('designation').value = user.designation || '';
            document.getElementById('department').value = user.department || '';
            document.getElementById('role').value = user.role || '';
            document.getElementById('status').value = user.status || '';

            // Show modal
            document.getElementById('editUserModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('editUserModal').classList.remove('active');
            document.getElementById('editUserForm').reset();
            currentUserId = null;
        }

        function saveUserChanges() {
            if (!currentUserId) {
                alert('User ID not set');
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const formData = new FormData();
            formData.append('userId', currentUserId);
            formData.append('position', document.getElementById('position').value);
            formData.append('designation', document.getElementById('designation').value);
            formData.append('department', document.getElementById('department').value);
            formData.append('role', document.getElementById('role').value);
            formData.append('status', document.getElementById('status').value);

            fetch('handlers/update_user_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update local data
                    const userIndex = usersData.findIndex(u => u.id === currentUserId);
                    if (userIndex > -1) {
                        usersData[userIndex].position = document.getElementById('position').value;
                        usersData[userIndex].designation = document.getElementById('designation').value;
                        usersData[userIndex].department = document.getElementById('department').value;
                        usersData[userIndex].role = document.getElementById('role').value;
                        usersData[userIndex].status = document.getElementById('status').value;
                    }
                    
                    // Update filtered data if needed
                    const filteredIndex = filteredData.findIndex(u => u.id === currentUserId);
                    if (filteredIndex > -1) {
                        filteredData[filteredIndex] = usersData[userIndex];
                    }

                    renderTable();
                    closeModal();
                    alert('User updated successfully!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to update user'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving user changes: ' + error.message);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal when clicking overlay
        document.getElementById('editUserModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
