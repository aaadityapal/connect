<?php
require_once 'config/db_connect.php';
require_once 'includes/auth_check.php';
require_once 'includes/role_check.php';

// Check if user has required role
checkUserRole(['admin', 'manager', 'senior manager (site)', 'senior manager (studio)', 'hr']);

// Initialize variables
$error = null;
$success = null;
$attendance_records = [];

// Get filter values
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

try {
    // Base query
    $sql = "SELECT 
            a.id,
            a.user_id,
            a.date,
            a.punch_in,
            a.punch_out,
            a.approval_status,
            a.manager_id,
            a.approval_timestamp,
            a.manager_comments,
            u.username,
            u.role
            FROM attendance a 
            JOIN users u ON a.user_id = u.id 
            WHERE a.approval_status = 'pending'
            AND u.role IN ('Site Coordinator', 'Site Supervisor', 'Purchase Manager', 
                         'Social Media Marketing', 'Graphic Designer')";

    // Add date filters if provided
    if (!empty($date_from)) {
        $sql .= " AND a.date >= ?";
    }
    if (!empty($date_to)) {
        $sql .= " AND a.date <= ?";
    }
    // Add role filter if provided
    if (!empty($role_filter)) {
        $sql .= " AND u.role = ?";
    }

    $sql .= " ORDER BY a.date DESC, a.punch_in DESC";

    // Prepare and execute statement
    $stmt = $conn->prepare($sql);

    // Bind parameters if they exist
    if ($stmt) {
        $types = '';
        $params = array();

        if (!empty($date_from)) {
            $types .= 's';
            $params[] = $date_from;
        }
        if (!empty($date_to)) {
            $types .= 's';
            $params[] = $date_to;
        }
        if (!empty($role_filter)) {
            $types .= 's';
            $params[] = $role_filter;
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $attendance_records[] = $row;
        }

        $stmt->close();
    } else {
        throw new Exception("Error preparing statement");
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Function to get unique roles
function getUniqueRoles($conn) {
    $roles = array();
    try {
        $sql = "SELECT DISTINCT role FROM users WHERE role IN ('Site Coordinator', 'Site Supervisor', 
                'Purchase Manager', 'Social Media Marketing', 'Graphic Designer') ORDER BY role";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $roles[] = $row['role'];
            }
        }
    } catch (Exception $e) {
        // Handle error silently
    }
    return $roles;
}

$unique_roles = getUniqueRoles($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Attendance Records</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="css/pending_attendance.css" rel="stylesheet">
    
    <style>
        /* Action button styling */
        .action-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
            margin: 0 3px;
            text-decoration: none;  /* Remove underline */
        }
        
        /* Action buttons container */
        .action-buttons-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding-right: 15px;  /* Add padding to shift right */
            margin-left: 15px;    /* Add margin to shift right */
        }
        
        /* Table cell alignment for actions */
        .actions-cell {
            text-align: center;
            vertical-align: middle;
            min-width: 140px;     /* Increased min-width to accommodate shift */
            padding-right: 0;     /* Remove default padding */
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
        }
        
        .action-btn-view {
            background-color: #f8f9fa;
            color: #0d6efd;
            border: 1px solid #dee2e6;
        }
        
        .action-btn-view:hover {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        
        .action-btn-approve {
            background-color: #f8f9fa;
            color: #198754;
            border: 1px solid #dee2e6;
        }
        
        .action-btn-approve:hover {
            background-color: #198754;
            color: white;
            border-color: #198754;
        }
        
        .action-btn-reject {
            background-color: #f8f9fa;
            color: #dc3545;
            border: 1px solid #dee2e6;
        }
        
        .action-btn-reject:hover {
            background-color: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        /* Table styling enhancements */
        .table {
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        /* Icon styling */
        .icon-view {
            font-size: 14px;
        }

        .icon-approve {
            font-size: 14px;
        }

        .icon-reject {
            font-size: 14px;
        }
        
        /* Add styles for filter section */
        .filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 10px;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #495057;
            margin-bottom: 5px;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 6px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .btn-filter {
            padding: 6px 15px;
            font-size: 0.875rem;
        }
        
        /* Responsive styles for mobile devices */
        @media screen and (max-width: 414px) { /* iPhone XR width */
            .table {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table th, 
            .table td {
                white-space: nowrap;
                padding: 8px 12px;
                font-size: 13px;
            }

            /* Adjust action buttons for mobile */
            .action-buttons-container {
                padding-right: 8px;
                margin-left: 8px;
                gap: 4px;
            }

            .action-btn {
                width: 28px;
                height: 28px;
            }

            .icon-view,
            .icon-approve,
            .icon-reject {
                font-size: 12px;
            }

            .actions-cell {
                min-width: 110px;
            }

            /* Enhance table header readability */
            .table th {
                font-size: 0.75rem;
                padding: 10px 12px;
            }

            /* Adjust status badges */
            .badge {
                font-size: 11px;
                padding: 4px 8px;
            }

            /* Responsive styles for filters */
            .filter-section {
                padding: 10px;
                margin-bottom: 15px;
            }

            .filter-group {
                min-width: 100%;
            }

            .filter-buttons {
                width: 100%;
                justify-content: flex-end;
            }

            .btn-filter {
                flex: 1;
            }
        }

        /* Specific adjustments for iPhone SE */
        @media screen and (max-width: 375px) {
            .table th, 
            .table td {
                padding: 6px 10px;
                font-size: 12px;
            }

            .action-buttons-container {
                padding-right: 6px;
                margin-left: 6px;
                gap: 3px;
            }

            .action-btn {
                width: 26px;
                height: 26px;
            }

            .actions-cell {
                min-width: 100px;
            }
        }

        /* Ensure tooltips are visible on mobile */
        @media (hover: none) {
            [data-bs-toggle="tooltip"]:hover::before {
                display: none;
            }
        }
    </style>
</head>
<body>

<!-- Add viewport meta tag with content-width -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<!-- Main Content -->
<div class="container-fluid px-2 px-sm-3"> <!-- Adjust container padding for mobile -->
    <div class="row justify-content-center">
        <div class="col-12">
            <!-- Alerts - adjust padding for mobile -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show mt-2 mb-2 p-2 p-sm-3" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Main Card - adjust padding for mobile -->
            <div class="card shadow-sm mt-2 mb-2">
                <div class="card-header py-2 px-3">
                    <h5 class="mb-0">
                        <i class="fas fa-clock"></i>
                        Pending Attendance Records
                    </h5>
                </div>

                <div class="card-body p-2 p-sm-3">
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form id="filterForm" class="mb-0">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label for="dateFrom">
                                        <i class="fas fa-calendar-alt"></i> Date From
                                    </label>
                                    <input type="date" id="dateFrom" name="date_from" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars($date_from); ?>">
                                </div>
                                <div class="filter-group">
                                    <label for="dateTo">
                                        <i class="fas fa-calendar-alt"></i> Date To
                                    </label>
                                    <input type="date" id="dateTo" name="date_to" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($date_to); ?>">
                                </div>
                                <div class="filter-group">
                                    <label for="roleFilter">
                                        <i class="fas fa-user-tag"></i> Role
                                    </label>
                                    <select id="roleFilter" name="role" class="form-select form-select-sm">
                                        <option value="">All Roles</option>
                                        <?php foreach ($unique_roles as $role): ?>
                                            <option value="<?php echo htmlspecialchars($role); ?>" 
                                                    <?php echo $role_filter === $role ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($role); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="filter-buttons">
                                <button type="submit" class="btn btn-primary btn-filter" id="applyFilters">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-filter" id="resetFilters">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if (empty($attendance_records)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            No pending attendance records found.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Date</th>
                                        <th>Punch In</th>
                                        <th>Punch Out</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['id']); ?></td>
                                            <td>
                                                <i class="fas fa-user text-muted"></i>
                                                <?php echo htmlspecialchars($record['username']); ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-id-badge text-muted"></i>
                                                <?php echo htmlspecialchars($record['role']); ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-calendar-day text-muted"></i>
                                                <?php echo date('d M Y', strtotime($record['date'])); ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-sign-in-alt text-success"></i>
                                                <?php echo date('h:i A', strtotime($record['punch_in'])); ?>
                                            </td>
                                            <td>
                                                <?php if ($record['punch_out']): ?>
                                                    <i class="fas fa-sign-out-alt text-danger"></i>
                                                    <?php echo date('h:i A', strtotime($record['punch_out'])); ?>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-hourglass-half"></i>
                                                        Not Punched Out
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-clock"></i>
                                                    Pending
                                                </span>
                                            </td>
                                            <td class="actions-cell">
                                                <div class="action-buttons-container">
                                                    <a href="attendance_approval.php?id=<?php echo $record['id']; ?>" 
                                                       class="action-btn action-btn-view" 
                                                       data-bs-toggle="tooltip" title="View Details">
                                                        <i class="fas fa-eye icon-view"></i>
                                                    </a>
                                                    <a href="attendance_approval.php?id=<?php echo $record['id']; ?>&action=approve" 
                                                       class="action-btn action-btn-approve" 
                                                       data-bs-toggle="tooltip" title="Approve">
                                                        <i class="fas fa-check icon-approve"></i>
                                                    </a>
                                                    <a href="attendance_approval.php?id=<?php echo $record['id']; ?>&action=reject" 
                                                       class="action-btn action-btn-reject" 
                                                       data-bs-toggle="tooltip" title="Reject">
                                                        <i class="fas fa-times icon-reject"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Required Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<!-- Initialize DataTable -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'click hover'
        })
    });
    
    // Initialize DataTable
    var table = $('.table').DataTable({
        order: [[3, 'desc'], [4, 'desc']],
        pageLength: 25,
        responsive: true,
        language: {
            search: "<i class='fas fa-search'></i> _INPUT_",
            searchPlaceholder: "Search records...",
            lengthMenu: "<i class='fas fa-list'></i> Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ records",
            paginate: {
                first: "<i class='fas fa-angle-double-left'></i>",
                last: "<i class='fas fa-angle-double-right'></i>",
                next: "<i class='fas fa-angle-right'></i>",
                previous: "<i class='fas fa-angle-left'></i>"
            }
        },
        responsive: {
            details: {
                display: $.fn.dataTable.Responsive.display.modal({
                    header: function (row) {
                        return 'Details for ' + row.data()[1];
                    }
                }),
                renderer: $.fn.dataTable.Responsive.renderer.tableAll({
                    tableClass: 'table'
                })
            }
        }
    });

    // Handle form submission
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new URLSearchParams(new FormData(this)).toString();
        window.location.href = 'pending_attendance.php?' + formData;
    });

    // Reset filters
    $('#resetFilters').on('click', function() {
        window.location.href = 'pending_attendance.php';
    });

    // Show loading indicator
    function showLoading() {
        $('<div class="loading-overlay"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>').appendTo('body');
    }

    // Add loading overlay styles
    const loadingStyles = `
        <style>
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255, 255, 255, 0.8);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            }
        </style>
    `;
    $(loadingStyles).appendTo('head');

    // Add loading indicator to form submission
    $('#filterForm').on('submit', function() {
        showLoading();
    });
    $('#resetFilters').on('click', function() {
        showLoading();
    });
});
</script>

</body>
</html>

