<?php
// Include database connection
require_once 'config/db_connect.php';

// Updated query to join with project_categories and users tables
$query = "SELECT p.id, p.title, p.description, p.project_type, 
                 pc.name as category_name, 
                 p.start_date, p.end_date, 
                 u1.username as created_by_username,
                 u1.profile_picture as created_by_picture,
                 u1.designation as created_by_designation,
                 u2.username as assigned_to_username,
                 u2.profile_picture as assigned_to_picture,
                 u2.designation as assigned_to_designation,
                 p.status, p.created_at, p.updated_at 
          FROM projects p
          LEFT JOIN project_categories pc ON p.category_id = pc.id 
          LEFT JOIN users u1 ON p.created_by = u1.id
          LEFT JOIN users u2 ON p.assigned_to = u2.id
          WHERE p.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            line-height: 1.5;
        }

        .dashboard-header {
            background: #ffffff;
            padding: 1.5rem 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .dashboard-title {
            font-size: 1.5rem;
            color: #111827;
            font-weight: 600;
        }

        .content-wrapper {
            padding: 0 2rem;
        }

        .card {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #4b5563;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        tr:hover {
            background-color: #f9fafb;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: capitalize;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-not-started {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-in-progress {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .edit-button {
            padding: 0.5rem 1rem;
            background-color: #4f46e5;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .edit-button:hover {
            background-color: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.1);
        }

        .edit-button i {
            margin-right: 0.5rem;
        }

        .project-title {
            color: #4f46e5;
            font-weight: 500;
        }

        .project-description {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .date-cell {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .user-section {
            display: flex;
            gap: 1.5rem;
            padding: 0.5rem 0;
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            border-radius: 8px;
            background-color: #f8fafc;
            transition: all 0.2s;
        }

        .user-card:hover {
            background-color: #f1f5f9;
            transform: translateY(-1px);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background-color: #4f46e5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
            font-weight: 500;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 500;
            color: #1f2937;
        }

        .user-role {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .user-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .user-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            animation: fadeIn 0.3s ease-out;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <h1 class="dashboard-title">Project Management Dashboard</h1>
    </div>

    <div class="content-wrapper">
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Project Details</th>
                        <th>Category</th>
                        <th>Timeline</th>
                        <th>Team Members</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($projects) > 0): ?>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['id']); ?></td>
                                <td>
                                    <div class="project-title"><?php echo htmlspecialchars($project['title']); ?></div>
                                    <div class="project-description"><?php echo htmlspecialchars($project['description']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($project['category_name']); ?></td>
                                <td>
                                    <div class="date-cell">
                                        <i class="far fa-calendar-alt"></i> Start: <?php echo htmlspecialchars($project['start_date']); ?><br>
                                        <i class="far fa-calendar-check"></i> End: <?php echo htmlspecialchars($project['end_date']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-section">
                                        <div class="user-group">
                                            <div class="user-label">Created By</div>
                                            <div class="user-card">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($project['created_by_username'], 0, 1)); ?>
                                                </div>
                                                <div class="user-info">
                                                    <div class="user-name"><?php echo htmlspecialchars($project['created_by_username']); ?></div>
                                                    <div class="user-role"><?php echo htmlspecialchars($project['created_by_designation'] ?? 'Team Member'); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="user-group">
                                            <div class="user-label">Assigned To</div>
                                            <div class="user-card">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($project['assigned_to_username'], 0, 1)); ?>
                                                </div>
                                                <div class="user-info">
                                                    <div class="user-name"><?php echo htmlspecialchars($project['assigned_to_username']); ?></div>
                                                    <div class="user-role"><?php echo htmlspecialchars($project['assigned_to_designation'] ?? 'Team Member'); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $project['status'])); ?>">
                                        <i class="fas fa-circle"></i>
                                        <?php echo htmlspecialchars($project['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit_project.php?id=<?php echo $project['id']; ?>" class="edit-button">
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem;">No projects found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 