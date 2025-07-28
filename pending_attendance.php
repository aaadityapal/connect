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

// Set default date range to current month if not provided
$current_month_start = date('Y-m-01'); // First day of current month
$current_month_end = date('Y-m-t');    // Last day of current month

// Get filter values
$date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : $current_month_start;
$date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : $current_month_end;
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';

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
            WHERE u.role IN ('Site Coordinator', 'Site Supervisor', 'Purchase Manager', 
                         'Social Media Marketing', 'Graphic Designer')";
    
    // Add status filter
    if (!empty($status_filter)) {
        $sql .= " AND a.approval_status = ?";
    }

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
        
        // Add status filter parameter
        if (!empty($status_filter)) {
            $types .= 's';
            $params[] = $status_filter;
        }

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
<?php include 'includes/manager_panel.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records</title>
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Leaflet CSS and JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="css/pending_attendance.css" rel="stylesheet">
    
    <style>
        /* Global styles */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f8f9fa;
            color: #212529;
            line-height: 1.5;
        }
        
        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem 1rem;
        }
        
        /* Card styling */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1rem 1.25rem;
        }
        
        .card-header h5 {
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
            background-color: #fff;
        }
        
        /* Action button styling - more minimal */
        .action-btn {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s ease;
            box-shadow: none;
            margin: 0 3px;
            text-decoration: none;
            border: 1px solid transparent;
        }
        
        /* Action buttons container */
        .action-buttons-container {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 6px;
            width: 100%;
        }
        
        /* Table cell alignment for actions */
        .actions-cell {
            text-align: right;
            vertical-align: middle;
            min-width: 120px;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .action-btn-view {
            background-color: #f0f7ff;
            color: #0d6efd;
        }
        
        .action-btn-view:hover {
            background-color: #0d6efd;
            color: white;
        }
        
        .action-btn-approve {
            background-color: #f0fff5;
            color: #198754;
        }
        
        .action-btn-approve:hover {
            background-color: #198754;
            color: white;
        }
        
        .action-btn-reject {
            background-color: #fff5f5;
            color: #dc3545;
        }
        
        .action-btn-reject:hover {
            background-color: #dc3545;
            color: white;
        }
        
        /* Table styling enhancements */
        .table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #6c757d;
            padding: 0.75rem 1rem;
            border-top: none;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            border-top: none;
            border-bottom: 1px solid #f2f2f2;
            color: #444;
            font-size: 0.875rem;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Icon styling */
        .icon-view, .icon-approve, .icon-reject {
            font-size: 13px;
        }
        
        /* Badge styling */
        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
            border-radius: 4px;
            font-size: 0.75rem;
            text-transform: none;
            letter-spacing: 0.3px;
        }
        
        /* Add styles for filter section */
        .filter-section {
            background: #fff;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #f0f0f0;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            font-size: 0.8125rem;
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.375rem;
            display: block;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: border-color 0.15s ease-in-out;
            background-color: #f9fafb;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            border-color: #90cdf4;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
        }

        .filter-buttons {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .btn-filter {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: #3182ce;
            border-color: #3182ce;
        }
        
        .btn-primary:hover {
            background-color: #2b6cb0;
            border-color: #2b6cb0;
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
        
        .attendance-details-card {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .punch-photo-placeholder {
            height: 150px;
            background-color: #f8f9fa;
            border: 1px dashed #dee2e6;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        
        .punch-photo-placeholder i {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .punch-photo {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .location-status .badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.6rem;
        }
        
        /* Add styles for the map containers */
        .location-map {
            height: 200px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            margin-bottom: 15px;
        }
        
        /* Style for geofence circle */
        .geofence-circle {
            stroke: #28a745;
            stroke-opacity: 0.8;
            stroke-width: 2;
            fill: #28a745;
            fill-opacity: 0.1;
        }
        
        /* Style for user marker */
        .user-location-marker {
            color: #dc3545;
        }
        
        /* Blur effect styles */
        .modal-backdrop.blur {
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        body.modal-open-with-blur .container-fluid:not(.modal) {
            filter: blur(5px);
            transition: filter 0.3s ease;
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
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Main Card - adjust padding for mobile -->
            <div class="card shadow-sm mt-2 mb-2">
                <div class="card-header py-2 px-3">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>
                        <?php 
                        if ($status_filter === 'pending') {
                            echo 'Pending Attendance Records';
                        } elseif ($status_filter === 'approved') {
                            echo 'Approved Attendance Records';
                        } elseif ($status_filter === 'rejected') {
                            echo 'Rejected Attendance Records';
                        } else {
                            echo 'All Attendance Records';
                        }
                        ?>
                    </h5>
                </div>

                <div class="card-body p-2 p-sm-3">
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form id="filterForm" class="mb-0">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label for="dateFrom">
                                        <i class="fas fa-calendar-alt me-1"></i> Date From
                                    </label>
                                    <input type="date" id="dateFrom" name="date_from" class="form-control" 
                                           value="<?php echo htmlspecialchars($date_from); ?>">
                                </div>
                                <div class="filter-group">
                                    <label for="dateTo">
                                        <i class="fas fa-calendar-alt me-1"></i> Date To
                                    </label>
                                    <input type="date" id="dateTo" name="date_to" class="form-control"
                                           value="<?php echo htmlspecialchars($date_to); ?>">
                                </div>
                                <div class="filter-group">
                                    <label for="roleFilter">
                                        <i class="fas fa-user-tag me-1"></i> Role
                                    </label>
                                    <select id="roleFilter" name="role" class="form-select">
                                        <option value="">All Roles</option>
                                        <?php foreach ($unique_roles as $role): ?>
                                            <option value="<?php echo htmlspecialchars($role); ?>" 
                                                    <?php echo $role_filter === $role ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($role); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="statusFilter">
                                        <i class="fas fa-filter me-1"></i> Status
                                    </label>
                                    <select id="statusFilter" name="status" class="form-select">
                                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                            </div>
                            <div class="filter-buttons">
                                <button type="submit" class="btn btn-primary btn-filter" id="applyFilters">
                                    <i class="fas fa-filter me-1"></i> Apply Filters
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-filter" id="resetFilters">
                                    <i class="fas fa-undo me-1"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if (empty($attendance_records)): ?>
                        <div class="alert alert-info d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <div>No attendance records found.</div>
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
                                        <th class="actions-cell">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['id']); ?></td>
                                            <td>
                                                <i class="fas fa-user text-muted me-1"></i>
                                                <?php echo htmlspecialchars($record['username']); ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-id-badge text-muted me-1"></i>
                                                <?php echo htmlspecialchars($record['role']); ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-calendar-day text-muted me-1"></i>
                                                <?php echo date('d M Y', strtotime($record['date'])); ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-sign-in-alt text-success me-1"></i>
                                                <?php echo date('h:i A', strtotime($record['punch_in'])); ?>
                                            </td>
                                            <td>
                                                <?php if ($record['punch_out']): ?>
                                                    <i class="fas fa-sign-out-alt text-danger me-1"></i>
                                                    <?php echo date('h:i A', strtotime($record['punch_out'])); ?>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-hourglass-half me-1"></i>
                                                        Not Punched Out
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php 
                                                    if ($record['approval_status'] === 'pending') {
                                                        echo 'bg-warning text-dark';
                                                    } elseif ($record['approval_status'] === 'approved') {
                                                        echo 'bg-success';
                                                    } else {
                                                        echo 'bg-danger';
                                                    }
                                                ?>">
                                                    <i class="fas <?php 
                                                        if ($record['approval_status'] === 'pending') {
                                                            echo 'fa-clock';
                                                        } elseif ($record['approval_status'] === 'approved') {
                                                            echo 'fa-check-circle';
                                                        } else {
                                                            echo 'fa-times-circle';
                                                        }
                                                    ?> me-1"></i>
                                                    <?php echo ucfirst($record['approval_status']); ?>
                                                </span>
                                            </td>
                                            <td class="actions-cell">
                                                <div class="action-buttons-container">
                                                    <a href="#" 
                                                   class="action-btn action-btn-view" 
                                                   data-bs-toggle="tooltip" title="View Details"
                                                   data-attendance-id="<?php echo $record['id']; ?>">
                                                    <i class="fas fa-eye icon-view"></i>
                                                </a>
                                                    <?php if ($record['approval_status'] === 'pending'): ?>
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
                                                    <?php endif; ?>
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
<script>
    // Function to initialize Leaflet maps
    function initMaps() {
        // This function will be called when the page loads
        console.log('Maps initialized');
    }
    
    // Function to initialize a specific map (legacy function, kept for compatibility)
    function initMap(elementId, coordinatesStr, title) {
        console.log('Legacy map initialization called');
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<!-- Google Maps API -->
<!-- No Google Maps API needed as we're using Leaflet -->

<!-- Initialize DataTable -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover',
            boundary: 'window'
        })
    });
    
    // Initialize DataTable with improved styling
    var table = $('.table').DataTable({
        order: [[3, 'desc'], [4, 'desc']],
        pageLength: 30,
        responsive: true,
        dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex align-items-center"l><"d-flex"f>>t<"d-flex justify-content-between align-items-center mt-3"<"text-muted"i><"d-flex"p>>',
        language: {
            search: "<i class='fas fa-search'></i>",
            searchPlaceholder: "Search records...",
            lengthMenu: "<span class='me-2'>Show</span> _MENU_",
            info: "Showing _START_ to _END_ of _TOTAL_ records",
            paginate: {
                first: "<i class='fas fa-angle-double-left'></i>",
                last: "<i class='fas fa-angle-double-right'></i>",
                next: "<i class='fas fa-angle-right'></i>",
                previous: "<i class='fas fa-angle-left'></i>"
            },
            emptyTable: "<div class='text-center p-3'><i class='fas fa-inbox fa-2x mb-2 text-muted'></i><p>No records found</p></div>"
        },
        responsive: {
            details: {
                display: $.fn.dataTable.Responsive.display.modal({
                    header: function (row) {
                        return '<h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Details for ' + row.data()[1] + '</h5>';
                    },
                    renderer: function (api, rowIdx, columns) {
                        var data = $.map(columns, function (col, i) {
                            return col.hidden ?
                                '<tr data-dt-row="'+col.rowIndex+'" data-dt-column="'+col.columnIndex+'">'+
                                    '<td class="fw-medium">'+col.title+':'+'</td> '+
                                    '<td>'+col.data+'</td>'+
                                '</tr>' :
                                '';
                        }).join('');
                        
                        return data ?
                            $('<table class="table table-sm"/>').append(data) :
                            false;
                    }
                })
            }
        },
        drawCallback: function() {
            $('.dataTables_paginate .paginate_button').addClass('btn btn-sm');
            $('.dataTables_paginate .paginate_button.current').addClass('btn-primary');
            $('.dataTables_paginate .paginate_button:not(.current)').addClass('btn-outline-secondary');
        }
    });

    // Handle form submission
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var queryString = new URLSearchParams(formData).toString();
        showLoading();
        window.location.href = 'pending_attendance.php?' + queryString;
    });

    // Reset filters
    $('#resetFilters').on('click', function(e) {
        e.preventDefault();
        // Reset form fields to defaults
        $('#dateFrom').val('<?php echo $current_month_start; ?>');
        $('#dateTo').val('<?php echo $current_month_end; ?>');
        $('#roleFilter').val('');
        $('#statusFilter').val('pending');
        
        // Submit the form with default values
        var formData = new FormData($('#filterForm')[0]);
        formData.set('date_from', '<?php echo $current_month_start; ?>');
        formData.set('date_to', '<?php echo $current_month_end; ?>');
        formData.delete('role');
        var queryString = new URLSearchParams(formData).toString();
        
        showLoading();
        window.location.href = 'pending_attendance.php?' + queryString;
    });

    // Show loading indicator
    function showLoading() {
        $('<div class="loading-overlay"><div class="spinner-grow text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>').appendTo('body');
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
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(2px);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
                transition: all 0.3s ease;
            }
            .dataTables_wrapper .dataTables_length select {
                background-color: #f9fafb;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
            .dataTables_wrapper .dataTables_filter input {
                background-color: #f9fafb;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                padding: 0.375rem 0.75rem;
                font-size: 0.875rem;
                margin-left: 0.5rem;
            }
            .dataTables_wrapper .dataTables_paginate .paginate_button {
                margin: 0 2px;
                border: none !important;
                background: none !important;
                box-shadow: none !important;
            }
            .dataTables_wrapper .dataTables_info {
                font-size: 0.875rem;
                color: #6c757d;
            }
        </style>
    `;
    $(loadingStyles).appendTo('head');
});
</script>

<!-- Attendance Details Modal -->
<div class="modal fade" id="attendanceDetailsModal" tabindex="-1" aria-labelledby="attendanceDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attendanceDetailsModalLabel">
                    <i class="fas fa-clipboard-list me-2"></i>Attendance Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading attendance details...</p>
                </div>
                
                <div id="modalContent" class="d-none">
                    <!-- Employee Information -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-user me-2"></i>Employee Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <p class="mb-1 text-muted small">Employee Name</p>
                                    <p class="mb-0 fw-medium" id="modal-employee-name">-</p>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <p class="mb-1 text-muted small">Unique ID</p>
                                    <p class="mb-0 fw-medium" id="modal-employee-id">-</p>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <p class="mb-1 text-muted small">Role</p>
                                    <p class="mb-0" id="modal-department">-</p>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <p class="mb-1 text-muted small">Designation</p>
                                    <p class="mb-0" id="modal-designation">-</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1 text-muted small">Reporting Manager</p>
                                    <p class="mb-0" id="modal-reporting-manager">-</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Details -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Attendance Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <p class="mb-1 text-muted small">Date</p>
                                    <p class="mb-0 fw-medium" id="modal-date">-</p>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <p class="mb-1 text-muted small">Status</p>
                                    <p class="mb-0" id="modal-status">-</p>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <p class="mb-1 text-muted small">Punch In Time</p>
                                    <p class="mb-0" id="modal-punch-in-time">-</p>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <p class="mb-1 text-muted small">Punch Out Time</p>
                                    <p class="mb-0" id="modal-punch-out-time">-</p>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <p class="mb-1 text-muted small">Working Hours</p>
                                    <p class="mb-0" id="modal-working-hours">-</p>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <p class="mb-1 text-muted small">Overtime Hours</p>
                                    <p class="mb-0" id="modal-overtime-hours">-</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Punch In Details -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>Punch In Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div id="punch-in-photo-container">
                                        <p class="mb-1 text-muted small">Punch In Photo</p>
                                        <div class="punch-photo-placeholder" id="modal-punch-in-photo">
                                            <i class="fas fa-camera"></i>
                                            <span>No photo available</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <p class="mb-1 text-muted small">Location Status</p>
                                    <div id="modal-punch-in-location-status" class="location-status">
                                        <span class="badge bg-success">Within Geofence</span>
                                    </div>
                                    
                                    <p class="mb-1 text-muted small mt-3">Distance from Geofence</p>
                                    <p class="mb-0" id="modal-punch-in-distance">-</p>
                                    
                                    <p class="mb-1 text-muted small mt-3">Address</p>
                                    <p class="mb-0" id="modal-punch-in-address">-</p>
                                    
                                    <!-- Add coordinates display -->
                                    <p class="mb-1 text-muted small mt-3">Coordinates</p>
                                    <p class="mb-0" id="modal-punch-in-coordinates">-</p>
                                </div>
                            </div>
                            
                            <!-- Add map container for punch in location -->
                            <div class="mt-3">
                                <p class="mb-1 text-muted small">Location Map</p>
                                <div id="punch-in-map" class="location-map"></div>
                            </div>
                            
                            <div id="punch-in-outside-reason-container" class="mt-2 d-none">
                                <p class="mb-1 text-muted small">Reason for being outside geofence</p>
                                <div class="p-2 bg-light rounded">
                                    <p class="mb-0" id="modal-punch-in-outside-reason">-</p>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <p class="mb-1 text-muted small">Device Information</p>
                                <p class="mb-0 small" id="modal-device-info">-</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Punch Out Details -->
                    <div class="card mb-3" id="punch-out-details-card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-sign-out-alt me-2"></i>Punch Out Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div id="punch-out-photo-container">
                                        <p class="mb-1 text-muted small">Punch Out Photo</p>
                                        <div class="punch-photo-placeholder" id="modal-punch-out-photo">
                                            <i class="fas fa-camera"></i>
                                            <span>No photo available</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <p class="mb-1 text-muted small">Location Status</p>
                                    <div id="modal-punch-out-location-status" class="location-status">
                                        <span class="badge bg-success">Within Geofence</span>
                                    </div>
                                    
                                    <p class="mb-1 text-muted small mt-3">Distance from Geofence</p>
                                    <p class="mb-0" id="modal-punch-out-distance">-</p>
                                    
                                    <p class="mb-1 text-muted small mt-3">Address</p>
                                    <p class="mb-0" id="modal-punch-out-address">-</p>
                                    
                                    <!-- Add coordinates display -->
                                    <p class="mb-1 text-muted small mt-3">Coordinates</p>
                                    <p class="mb-0" id="modal-punch-out-coordinates">-</p>
                                </div>
                            </div>
                            
                            <!-- Add map container for punch out location -->
                            <div class="mt-3">
                                <p class="mb-1 text-muted small">Location Map</p>
                                <div id="punch-out-map" class="location-map"></div>
                            </div>
                            
                            <div id="punch-out-outside-reason-container" class="mt-2 d-none">
                                <p class="mb-1 text-muted small">Reason for being outside geofence</p>
                                <div class="p-2 bg-light rounded">
                                    <p class="mb-0" id="modal-punch-out-outside-reason">-</p>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <p class="mb-1 text-muted small">Work Report</p>
                                <div class="p-2 bg-light rounded">
                                    <p class="mb-0" id="modal-work-report">-</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="acceptAttendanceBtn" class="btn btn-success">
                    <i class="fas fa-check me-1"></i> Accept
                </button>
                <button type="button" id="rejectAttendanceBtn" class="btn btn-danger">
                    <i class="fas fa-times me-1"></i> Reject
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Action Modal -->
<div class="modal fade" id="attendanceActionModal" tabindex="-1" aria-labelledby="attendanceActionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 px-4 py-3" id="actionModalHeader">
                <h5 class="modal-title fw-bold" id="attendanceActionModalLabel">
                    <i class="fas fa-clipboard-check me-2"></i>
                    <span id="actionModalTitle">Confirm Action</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert d-flex align-items-center mb-4" id="actionAlertBox">
                    <i class="fas fa-info-circle me-3 fs-5"></i>
                    <div id="actionAlertText">Please review your decision before confirming.</div>
                </div>
                
                <form id="attendanceActionForm">
                    <input type="hidden" id="actionAttendanceId" name="attendance_id" value="">
                    <input type="hidden" id="actionType" name="action" value="">
                    
                    <div class="mb-4">
                        <label for="actionComments" class="form-label fw-medium">Comments</label>
                        <textarea class="form-control border-2" 
                                id="actionComments" 
                                name="comments" 
                                rows="4" 
                                placeholder="Enter your comments or reason for this action..."
                                style="border-radius: 8px; resize: none;"></textarea>
                        <div class="invalid-feedback" id="commentsError">
                            Please provide a reason for rejecting this attendance record.
                        </div>
                        <div class="form-text text-muted mt-2" id="commentsHelp">
                            <i class="fas fa-lightbulb me-1"></i> 
                            <span id="commentHelpText">Add any relevant information about your decision.</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0 d-flex justify-content-between">
                <button type="button" class="btn btn-light px-4 py-2" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn px-4 py-2" id="confirmActionBtn">
                    <i class="fas me-2" id="confirmBtnIcon"></i>
                    <span id="confirmBtnText">Confirm</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add custom styles for the modal -->
<style>
    /* Enhanced Modal Styles */
    .modal-content {
        border-radius: 12px;
        overflow: hidden;
    }
    
    #actionModalHeader.approve-header {
        background-color: rgba(25, 135, 84, 0.1);
        color: #198754;
    }
    
    #actionModalHeader.reject-header {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
    
    #actionAlertBox.alert-approve {
        background-color: rgba(25, 135, 84, 0.08);
        color: #198754;
        border: 1px solid rgba(25, 135, 84, 0.2);
        border-radius: 8px;
    }
    
    #actionAlertBox.alert-reject {
        background-color: rgba(220, 53, 69, 0.08);
        color: #dc3545;
        border: 1px solid rgba(220, 53, 69, 0.2);
        border-radius: 8px;
    }
    
    #confirmActionBtn.btn-confirm-approve {
        background-color: #198754;
        color: white;
        box-shadow: 0 2px 6px rgba(25, 135, 84, 0.3);
        transition: all 0.2s ease;
    }
    
    #confirmActionBtn.btn-confirm-approve:hover {
        background-color: #157347;
        box-shadow: 0 4px 8px rgba(25, 135, 84, 0.4);
        transform: translateY(-1px);
    }
    
    #confirmActionBtn.btn-confirm-reject {
        background-color: #dc3545;
        color: white;
        box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3);
        transition: all 0.2s ease;
    }
    
    #confirmActionBtn.btn-confirm-reject:hover {
        background-color: #bb2d3b;
        box-shadow: 0 4px 8px rgba(220, 53, 69, 0.4);
        transform: translateY(-1px);
    }
    
    .form-control:focus {
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        border-color: #86b7fe;
    }
    
    /* Blur effect styles - enhanced */
    .modal-backdrop.blur {
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        background-color: rgba(0, 0, 0, 0.4);
    }
    
    body.modal-open-with-blur .container-fluid:not(.modal) {
        filter: blur(6px);
        transition: filter 0.3s ease;
    }
    
    /* Animation for modal */
    .modal.fade .modal-dialog {
        transition: transform 0.3s ease-out;
    }
    
    .modal.fade.show .modal-dialog {
        transform: none;
    }
</style>

<script>
    // Handle view button click to open modal with attendance details
    $(document).on('click', '.action-btn-view', function(e) {
        e.preventDefault();
        
        // Get attendance ID from data attribute
        const attendanceId = $(this).data('attendance-id');
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('attendanceDetailsModal'));
        modal.show();
        
        // Show loading and hide content
        $('#modalLoading').removeClass('d-none');
        $('#modalContent').addClass('d-none');
        
        // Initialize map variables
        let punchInMap = null;
        let punchOutMap = null;
        let punchInMarker = null;
        let punchOutMarker = null;
        let punchInGeofenceCircle = null;
        let punchOutGeofenceCircle = null;
        
        // Fetch attendance details
        fetch(`fetch_attendance_modal_data.php?id=${attendanceId}`)
            .then(response => response.json())
            .then(data => {
                // Hide loading and show content
                $('#modalLoading').addClass('d-none');
                $('#modalContent').removeClass('d-none');
                
                if (data.success && data.data) {
                    const attendance = data.data;
                    
                    // Populate employee information
                    $('#modal-employee-name').text(attendance.username || '-');
                    $('#modal-employee-id').text(attendance.unique_id || '-');
                    $('#modal-department').text(attendance.role || '-');
                    $('#modal-designation').text(attendance.designation || '-');
                    $('#modal-reporting-manager').text(attendance.reporting_manager || '-');
                    
                    // Populate attendance details
                    $('#modal-date').text(attendance.formatted_date || '-');
                    $('#modal-status').html(`<span class="badge bg-warning text-dark">Pending</span>`);
                    $('#modal-punch-in-time').text(attendance.formatted_punch_in || '-');
                    $('#modal-punch-out-time').text(attendance.formatted_punch_out || '-');
                    $('#modal-working-hours').text(attendance.working_hours || '-');
                    $('#modal-overtime-hours').text(attendance.overtime_hours || '-');
                    
                    // Populate punch in details
                    if (attendance.punch_in_photo) {
                        $('#modal-punch-in-photo').html(`<img src="${attendance.punch_in_photo}" class="punch-photo" alt="Punch In Photo">`);
                    }
                    
                    // Set punch in location status
                    const punchInLocationStatus = attendance.punch_in_geofence_status;
                    if (punchInLocationStatus === 'Within Geofence') {
                        $('#modal-punch-in-location-status').html(`<span class="badge bg-success">Within Geofence</span>`);
                        $('#punch-in-outside-reason-container').addClass('d-none');
                    } else {
                        $('#modal-punch-in-location-status').html(`<span class="badge bg-danger">Outside Geofence</span>`);
                        $('#punch-in-outside-reason-container').removeClass('d-none');
                        $('#modal-punch-in-outside-reason').text(attendance.punch_in_outside_reason || 'No reason provided');
                    }
                    
                    $('#modal-punch-in-distance').text(attendance.distance_from_geofence ? `${attendance.distance_from_geofence} meters` : '-');
                    $('#modal-punch-in-address').text(attendance.address || '-');
                    $('#modal-device-info').text(attendance.device_info || '-');
                    
                    // Add coordinates display for punch in
                    if (attendance.latitude && attendance.longitude) {
                        $('#modal-punch-in-coordinates').text(`${attendance.latitude}, ${attendance.longitude}`);
                        
                        // Initialize punch in map
                        if (!punchInMap) {
                            punchInMap = L.map('punch-in-map').setView([attendance.latitude, attendance.longitude], 15);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '&copy; OpenStreetMap contributors'
                            }).addTo(punchInMap);
                            
                            // Add user location marker
                            punchInMarker = L.marker([attendance.latitude, attendance.longitude], {
                                title: 'Punch In Location'
                            }).addTo(punchInMap);
                            
                            // Add geofence circle if geofence data is available
                            if (attendance.geofence_latitude && attendance.geofence_longitude && attendance.geofence_radius) {
                                punchInGeofenceCircle = L.circle([attendance.geofence_latitude, attendance.geofence_longitude], {
                                    radius: attendance.geofence_radius,
                                    className: 'geofence-circle'
                                }).addTo(punchInMap);
                                
                                // Adjust map view to show both marker and geofence
                                const bounds = L.latLngBounds(
                                    [attendance.latitude, attendance.longitude],
                                    [attendance.geofence_latitude, attendance.geofence_longitude]
                                );
                                punchInMap.fitBounds(bounds.pad(0.3));
                            }
                        }
                    }
                    
                    // Populate punch out details if available
                    if (attendance.punch_out) {
                        $('#punch-out-details-card').removeClass('d-none');
                        
                        if (attendance.punch_out_photo) {
                            $('#modal-punch-out-photo').html(`<img src="${attendance.punch_out_photo}" class="punch-photo" alt="Punch Out Photo">`);
                        }
                        
                        // Set punch out location status
                        const punchOutLocationStatus = attendance.punch_out_geofence_status;
                        if (punchOutLocationStatus === 'Within Geofence') {
                            $('#modal-punch-out-location-status').html(`<span class="badge bg-success">Within Geofence</span>`);
                            $('#punch-out-outside-reason-container').addClass('d-none');
                        } else {
                            $('#modal-punch-out-location-status').html(`<span class="badge bg-danger">Outside Geofence</span>`);
                            $('#punch-out-outside-reason-container').removeClass('d-none');
                            $('#modal-punch-out-outside-reason').text(attendance.punch_out_outside_reason || 'No reason provided');
                        }
                        
                        $('#modal-punch-out-distance').text(attendance.distance_from_geofence ? `${attendance.distance_from_geofence} meters` : '-');
                        $('#modal-punch-out-address').text(attendance.punch_out_address || '-');
                        $('#modal-work-report').text(attendance.work_report || 'No work report submitted');
                        
                        // Add coordinates display for punch out
                        if (attendance.punch_out_latitude && attendance.punch_out_longitude) {
                            $('#modal-punch-out-coordinates').text(`${attendance.punch_out_latitude}, ${attendance.punch_out_longitude}`);
                            
                            // Initialize punch out map
                            if (!punchOutMap) {
                                punchOutMap = L.map('punch-out-map').setView([attendance.punch_out_latitude, attendance.punch_out_longitude], 15);
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    attribution: '&copy; OpenStreetMap contributors'
                                }).addTo(punchOutMap);
                                
                                // Add user location marker
                                punchOutMarker = L.marker([attendance.punch_out_latitude, attendance.punch_out_longitude], {
                                    title: 'Punch Out Location'
                                }).addTo(punchOutMap);
                                
                                // Add geofence circle if geofence data is available
                                if (attendance.geofence_latitude && attendance.geofence_longitude && attendance.geofence_radius) {
                                    punchOutGeofenceCircle = L.circle([attendance.geofence_latitude, attendance.geofence_longitude], {
                                        radius: attendance.geofence_radius,
                                        className: 'geofence-circle'
                                    }).addTo(punchOutMap);
                                    
                                    // Adjust map view to show both marker and geofence
                                    const bounds = L.latLngBounds(
                                        [attendance.punch_out_latitude, attendance.punch_out_longitude],
                                        [attendance.geofence_latitude, attendance.geofence_longitude]
                                    );
                                    punchOutMap.fitBounds(bounds.pad(0.3));
                                }
                            }
                        }
                    } else {
                        $('#punch-out-details-card').addClass('d-none');
                    }
                    
                    // Set up Accept and Reject buttons with the attendance ID
                    $('#acceptAttendanceBtn').data('attendance-id', attendanceId);
                    $('#rejectAttendanceBtn').data('attendance-id', attendanceId);
                } else {
                    // Show error message
                    $('#modalContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            ${data.message || 'Failed to load attendance details'}
                        </div>
                    `);
                }
            })
            .catch(error => {
                // Hide loading and show error
                $('#modalLoading').addClass('d-none');
                $('#modalContent').removeClass('d-none').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error loading attendance details. Please try again.
                    </div>
                `);
                console.error('Error fetching attendance details:', error);
            });
    });
    
    // Handle modal hidden event to properly clean up maps
    $('#attendanceDetailsModal').on('hidden.bs.modal', function () {
        // Clean up maps when modal is closed
        if (window.punchInMap) {
            window.punchInMap.remove();
            window.punchInMap = null;
        }
        if (window.punchOutMap) {
            window.punchOutMap.remove();
            window.punchOutMap = null;
        }
    });
    
    // Handle Accept button click
    $(document).on('click', '#acceptAttendanceBtn', function() {
        const attendanceId = $(this).data('attendance-id');
        
        // Set values in the action modal
        $('#actionAttendanceId').val(attendanceId);
        $('#actionType').val('approve');
        $('#actionModalTitle').text('Confirm Approval');
        $('#actionComments').attr('placeholder', 'Enter any comments about this approval (optional)...');
        $('#commentHelpText').text('Optional: Add any relevant information about your approval.');
        
        // Update styling for approval
        $('#actionModalHeader').removeClass('reject-header').addClass('approve-header');
        $('#actionAlertBox').removeClass('alert-reject').addClass('alert-approve');
        $('#actionAlertText').html('<strong>You are about to approve this attendance record.</strong> The employee will be notified of your decision.');
        
        // Update confirm button
        $('#confirmActionBtn').removeClass('btn-confirm-reject').addClass('btn-confirm-approve');
        $('#confirmBtnIcon').removeClass('fa-times-circle').addClass('fa-check-circle');
        $('#confirmBtnText').text('Approve');
        
        // Reset validation state
        $('#actionComments').removeClass('is-invalid');
        
        // Add blur effect to background
        $('body').addClass('modal-open-with-blur');
        $('.modal-backdrop').addClass('blur');
        
        // Show the action modal
        const actionModal = new bootstrap.Modal(document.getElementById('attendanceActionModal'));
        actionModal.show();
    });
    
    // Handle Reject button click
    $(document).on('click', '#rejectAttendanceBtn', function() {
        const attendanceId = $(this).data('attendance-id');
        
        // Set values in the action modal
        $('#actionAttendanceId').val(attendanceId);
        $('#actionType').val('reject');
        $('#actionModalTitle').text('Confirm Rejection');
        $('#actionComments').attr('placeholder', 'Please provide a reason for rejecting this attendance record...');
        $('#commentHelpText').text('Required: Explain why this attendance record is being rejected.');
        
        // Update styling for rejection
        $('#actionModalHeader').removeClass('approve-header').addClass('reject-header');
        $('#actionAlertBox').removeClass('alert-approve').addClass('alert-reject');
        $('#actionAlertText').html('<strong>You are about to reject this attendance record.</strong> The employee will be notified of your decision.');
        
        // Update confirm button
        $('#confirmActionBtn').removeClass('btn-confirm-approve').addClass('btn-confirm-reject');
        $('#confirmBtnIcon').removeClass('fa-check-circle').addClass('fa-times-circle');
        $('#confirmBtnText').text('Reject');
        
        // Reset validation state
        $('#actionComments').removeClass('is-invalid');
        
        // Add blur effect to background
        $('body').addClass('modal-open-with-blur');
        $('.modal-backdrop').addClass('blur');
        
        // Show the action modal
        const actionModal = new bootstrap.Modal(document.getElementById('attendanceActionModal'));
        actionModal.show();
    });
    
    // Remove blur effect when modal is closed
    $('#attendanceActionModal').on('hidden.bs.modal', function () {
        $('body').removeClass('modal-open-with-blur');
        $('.modal-backdrop').removeClass('blur');
    });
    
    // Add handler for the confirm action button
    $(document).on('click', '#confirmActionBtn', function() {
        const attendanceId = $('#actionAttendanceId').val();
        const action = $('#actionType').val();
        const comments = $('#actionComments').val();
        
        // Validate comments for rejection (required for rejection)
        if (action === 'reject' && comments.trim() === '') {
            $('#actionComments').addClass('is-invalid');
            $('#commentsError').show();
            return;
        }
        
        // Visual feedback - disable button and show loading state
        const originalBtnHtml = $(this).html();
        $(this).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...');
        $(this).prop('disabled', true);
        
        // Send request to server
        fetch('approve_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `attendance_id=${attendanceId}&action=${action}&comments=${encodeURIComponent(comments)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close both modals
                bootstrap.Modal.getInstance(document.getElementById('attendanceActionModal')).hide();
                bootstrap.Modal.getInstance(document.getElementById('attendanceDetailsModal')).hide();
                
                // Show success message with toast notification
                const toastHTML = `
                    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
                        <div class="toast align-items-center text-white bg-${action === 'approve' ? 'success' : 'danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <i class="fas fa-${action === 'approve' ? 'check-circle' : 'times-circle'} me-2"></i>
                                    Attendance record ${action === 'approve' ? 'approved' : 'rejected'} successfully!
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                        </div>
                    </div>
                `;
                
                $('body').append(toastHTML);
                const toastElement = document.querySelector('.toast');
                const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
                toast.show();
                
                // Reload the page after toast is shown
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                // Reset button state
                $(this).html(originalBtnHtml);
                $(this).prop('disabled', false);
                
                // Show error message
                alert('Error: ' + (data.message || `Failed to ${action} attendance record`));
            }
        })
        .catch(error => {
            // Reset button state
            $(this).html(originalBtnHtml);
            $(this).prop('disabled', false);
            
            console.error(`Error ${action}ing attendance:`, error);
            alert(`An error occurred while ${action}ing the attendance record.`);
        });
    });
</script>

</body>
</html>