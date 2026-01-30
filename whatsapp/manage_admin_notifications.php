<?php
require_once __DIR__ . '/../config.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDBConnection();

    if (isset($_POST['add_admin'])) {
        $name = trim($_POST['admin_name']);
        $phone = trim($_POST['phone']);

        $stmt = $pdo->prepare("INSERT INTO admin_notifications (admin_name, phone) VALUES (?, ?)");
        $stmt->execute([$name, $phone]);
        $success = "Admin added successfully!";
    }

    if (isset($_POST['delete_admin'])) {
        $id = $_POST['admin_id'];
        $stmt = $pdo->prepare("DELETE FROM admin_notifications WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Admin deleted successfully!";
    }

    if (isset($_POST['toggle_status'])) {
        $id = $_POST['admin_id'];
        $stmt = $pdo->prepare("UPDATE admin_notifications SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Status updated successfully!";
    }
}

// Fetch all admins
$pdo = getDBConnection();
$admins = $pdo->query("SELECT * FROM admin_notifications ORDER BY admin_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admin Notifications</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .content {
            padding: 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .form-section h2 {
            margin-bottom: 20px;
            color: #333;
            font-size: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }

        input[type="text"],
        input[type="tel"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            font-size: 12px;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: #ffc107;
            color: #000;
            padding: 8px 16px;
            font-size: 12px;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>📱 Manage Admin Notifications</h1>
            <p>Configure who receives daily attendance summaries</p>
        </div>

        <div class="content">
            <?php if (isset($success)): ?>
                <div class="alert">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="form-section">
                <h2>➕ Add New Admin</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="admin_name">Admin Name</label>
                        <input type="text" id="admin_name" name="admin_name" required placeholder="e.g., John Doe">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number (with country code)</label>
                        <input type="tel" id="phone" name="phone" required placeholder="e.g., 919876543210">
                    </div>
                    <button type="submit" name="add_admin" class="btn btn-primary">Add Admin</button>
                </form>
            </div>

            <h2>📋 Admin List</h2>

            <?php if (empty($admins)): ?>
                <div class="empty-state">
                    <p>No admins configured yet. Add one above to get started!</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone Number</th>
                            <th>Status</th>
                            <th>Added On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($admin['admin_name']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($admin['phone']); ?>
                                </td>
                                <td>
                                    <span
                                        class="status-badge <?php echo $admin['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $admin['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($admin['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                            <button type="submit" name="toggle_status" class="btn btn-warning">
                                                <?php echo $admin['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;"
                                            onsubmit="return confirm('Are you sure you want to delete this admin?');">
                                            <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                            <button type="submit" name="delete_admin" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>