<?php
// Start the session
session_start();

// Include database configuration
include('config/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Circulars</title>
    
    <!-- CSS -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4154f1;
            --secondary-color: #717ff5;
            --text-color: #012970;
            --light-bg: #f6f9ff;
        }

        /* Layout */
        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: white;
            box-shadow: 0 0 20px rgba(1, 41, 112, 0.1);
            padding: 20px 0;
        }

        .sidebar-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--light-bg);
        }

        .sidebar-header h3 {
            color: var(--text-color);
            font-size: 18px;
            margin: 0;
        }

        .nav-pills .nav-link {
            color: var(--text-color);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 0;
            transition: all 0.3s;
        }

        .nav-pills .nav-link:hover,
        .nav-pills .nav-link.active {
            background: var(--light-bg);
            color: var(--primary-color);
        }

        .nav-pills .nav-link i {
            margin-right: 10px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            background: var(--light-bg);
        }

        /* Cards */
        .card {
            background: white;
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(1, 41, 112, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: none;
            border-bottom: 1px solid var(--light-bg);
            padding: 15px 20px;
        }

        .card-title {
            color: var(--text-color);
            font-size: 18px;
            font-weight: 500;
            margin: 0;
        }

        /* Table */
        .table th {
            background: var(--light-bg);
            color: var(--text-color);
            font-weight: 500;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Admin Panel</h3>
        </div>
        
        <div class="nav flex-column nav-pills">
            <a class="nav-link" href="dashboard.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#communicationMenu">
                <i class="bi bi-megaphone"></i> Communication
                <i class="bi bi-chevron-down float-end"></i>
            </a>
            <div class="collapse show" id="communicationMenu">
                <a class="nav-link" href="?type=events">
                    <i class="bi bi-calendar-event"></i> Events
                </a>
                <a class="nav-link" href="?type=announcements">
                    <i class="bi bi-broadcast"></i> Announcements
                </a>
                <a class="nav-link active" href="?type=circulars">
                    <i class="bi bi-file-text"></i> Circulars
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php
        $type = isset($_GET['type']) ? $_GET['type'] : 'circulars';
        $title = ucfirst($type);
        ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title">All <?php echo $title; ?></h5>
                <button class="btn btn-primary" onclick="addNew('<?php echo $type; ?>')">
                    <i class="bi bi-plus"></i> Add New <?php echo rtrim($title, 's'); ?>
                </button>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Valid Until</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch records based on type
                        $query = "SELECT * FROM $type ORDER BY created_at DESC";
                        $result = mysqli_query($conn, $query);

                        while($row = mysqli_fetch_assoc($result)) {
                            $status_class = match($row['status']) {
                                'Active' => 'success',
                                'Expired' => 'warning',
                                'Archived' => 'secondary',
                                default => 'primary'
                            };
                        ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo date('d M Y', strtotime($row['valid_until'])); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $status_class; ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['created_by']); ?></td>
                            <td><?php echo date('d M Y H:i', strtotime($row['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="view(<?php echo $row['id']; ?>, '<?php echo $type; ?>')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-primary" onclick="edit(<?php echo $row['id']; ?>, '<?php echo $type; ?>')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $row['id']; ?>, '<?php echo $type; ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<?php include('modals/communication_modal.php'); ?>

<!-- JavaScript -->
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
function addNew(type) {
    $('#communicationModal').modal('show');
    $('#communicationType').val(type);
    $('#modalTitle').text('Add New ' + type.charAt(0).toUpperCase() + type.slice(1, -1));
}

function view(id, type) {
    $.ajax({
        url: 'process_communication.php',
        type: 'GET',
        data: { id: id, type: type, action: 'view' },
        success: function(response) {
            // Handle view response
        }
    });
}

function edit(id, type) {
    $.ajax({
        url: 'process_communication.php',
        type: 'GET',
        data: { id: id, type: type, action: 'edit' },
        success: function(response) {
            // Handle edit response
        }
    });
}

function deleteItem(id, type) {
    if(confirm('Are you sure you want to delete this ' + type.slice(0, -1) + '?')) {
        $.ajax({
            url: 'process_communication.php',
            type: 'POST',
            data: { id: id, type: type, action: 'delete' },
            success: function(response) {
                // Handle delete response
                location.reload();
            }
        });
    }
}
</script>

</body>
</html>