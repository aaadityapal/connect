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
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
                            <div>No pending attendance records found.</div>
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
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Pending
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
            trigger: 'hover',
            boundary: 'window'
        })
    });
    
    // Initialize DataTable with improved styling
    var table = $('.table').DataTable({
        order: [[3, 'desc'], [4, 'desc']],
        pageLength: 25,
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
        var formData = new URLSearchParams(new FormData(this)).toString();
        showLoading();
        window.location.href = 'pending_attendance.php?' + formData;
    });

    // Reset filters
    $('#resetFilters').on('click', function() {
        showLoading();
        window.location.href = 'pending_attendance.php';
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
                                </div>
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
                                </div>
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
                <a href="#" id="viewFullDetailsBtn" class="btn btn-primary">
                    <i class="fas fa-external-link-alt me-1"></i> View Full Details
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add custom styles for the modal -->
<style>
    .punch-photo-placeholder {
        width: 100%;
        height: 150px;
        background-color: #f8f9fa;
        border: 1px dashed #dee2e6;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        color: #adb5bd;
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
                    } else {
                        $('#punch-out-details-card').addClass('d-none');
                    }
                    
                    // Set the full details link
                    $('#viewFullDetailsBtn').attr('href', `attendance_approval.php?id=${attendanceId}`);
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

</script>
</body>
</html>