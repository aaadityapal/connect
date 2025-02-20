<?php
session_start();
require_once 'config/db_connect.php';

// Get user details from database
$user_id = $_SESSION['user_id'] ?? 0;
$user_query = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_name = $user['username'] ?? 'Guest';

// Get current hour in IST
$hour = date('H', strtotime('+5 hours 30 minutes')); // Convert to IST

// Determine greeting based on time
$greeting = '';
if ($hour >= 5 && $hour < 12) {
    $greeting = 'Good Morning';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = 'Good Afternoon';
} elseif ($hour >= 17 && $hour < 21) {
    $greeting = 'Good Evening';
} else {
    $greeting = 'Good Night';
}

// Add these PHP variables at the top with other PHP code
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Calculate previous and next month/year
$prev_month = $month - 1;
$next_month = $month + 1;
$prev_year = $year;
$next_year = $year;

if ($prev_month == 0) {
    $prev_month = 12;
    $prev_year--;
}
if ($next_month == 13) {
    $next_month = 1;
    $next_year++;
}

// Add this PHP code near the top with other database queries
$users_query = "SELECT id, username FROM users ORDER BY username";
$users_result = $conn->query($users_query);
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Add this PHP code after getting the users array
// ... existing code ...
$projects_query = "SELECT 
    p.id,
    p.contract_number,
    p.project_type,
    p.project_name,
    p.client_name,
    p.created_at,
    u1.username as assigned_by,
    ps.name as stage_name,
    ps.status as stage_status,
    u2.username as stage_assigned_to,
    ptm.role as team_role,
    u3.username as team_member
FROM projects p
LEFT JOIN users u1 ON p.got_project_from = u1.id
LEFT JOIN project_stages ps ON ps.project_id = p.id
LEFT JOIN users u2 ON ps.assigned_to = u2.id
LEFT JOIN project_team_members ptm ON ptm.project_id = p.id
LEFT JOIN users u3 ON ptm.user_id = u3.id
WHERE MONTH(p.created_at) = ? AND YEAR(p.created_at) = ?
AND (ps.id IS NULL OR ps.id = (
    SELECT ps2.id 
    FROM project_stages ps2 
    WHERE ps2.project_id = p.id 
    ORDER BY ps2.created_at DESC 
    LIMIT 1
))
ORDER BY p.created_at";

$stmt = $conn->prepare($projects_query);
$stmt->bind_param("ii", $month, $year);
$stmt->execute();
$result = $stmt->get_result();

$projects = [];
while ($row = $result->fetch_assoc()) {
    $day = date('j', strtotime($row['created_at']));
    if (!isset($projects[$day])) {
        $projects[$day] = [];
    }
    $projects[$day][] = $row;
}
// ... existing code ...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .greeting-section {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin: 2rem auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            max-width: 100%;
            width: 100%;
        }
        .greeting-text {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .date-text {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .calendar-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem auto;
            box-shadow: none;
            border: 1px solid #e5e5e5;
            max-width: 1600px;
            width: 100%;
            overflow-x: auto;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .calendar-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(200px, 1fr));
            gap: 1px;
            padding: 0;
            border: 1px solid #e5e5e5;
        }
        .calendar-day {
            text-align: center;
            padding: 10px;
            font-weight: normal;
            color: #666;
            font-size: 0.9rem;
            background: #f8f8f8;
            border-bottom: 1px solid #e5e5e5;
        }
        .calendar-date {
            text-align: left;
            padding: 0;
            border-radius: 0;
            cursor: pointer;
            transition: none;
            min-height: 200px;
            min-width: 200px;
            border-right: 1px solid #e5e5e5;
            border-bottom: 1px solid #e5e5e5;
            overflow-y: auto;
        }
        .date-box {
            border: none;
            width: auto;
            height: auto;
            margin: 0;
            display: block;
        }
        .date-number {
            padding: 8px;
            font-size: 0.9rem;
            font-weight: normal;
            color: #333;
        }
        .current-date {
            background-color: transparent;
        }
        .current-date .date-number {
            color: #0066ff;
            font-weight: bold;
        }
        .calendar-header select {
            min-width: 150px;
            font-size: 0.9rem;
            padding: 0.375rem 2rem 0.375rem 0.75rem;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            background-color: white;
        }
        .date-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid #e5e5e5;
        }
        .add-task-btn {
            opacity: 0;
            border: none;
            background: none;
            color: #0066ff;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0 5px;
            transition: opacity 0.2s ease;
        }
        .date-box:hover .add-task-btn {
            opacity: 1;
        }
        .add-task-btn:hover {
            transform: scale(1.2);
        }
        .stage-item {
            background-color: #f8f9fa;
            margin-bottom: 1rem;
            padding: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
        }

        .sub-stage-item {
            background-color: #ffffff;
            margin-top: 0.5rem;
            padding: 1rem;
            border-left: 3px solid #0d6efd;
            border-radius: 0.25rem;
        }

        .sub-stages-container {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }

        .btn-remove {
            float: right;
        }

        select[name$="[status]"] {
            transition: all 0.3s ease;
        }

        select[name$="[status]"] option {
            background-color: #ffffff;
            color: #000000;
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .project-card {
            margin: 5px 5px 5px 15px;
            padding: 8px;
            background-color: #ffffff;
            border-left: 3px solid #0066ff;
            border-radius: 4px;
            font-size: 0.8rem;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            width: 90%;
        }

        .project-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .project-card h6 {
            margin: 0 0 5px 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .project-details {
            margin-right: 0;
            margin-bottom: 5px;
        }

        .project-details p {
            margin: 0 0 2px 0;
            color: #666;
            font-size: 0.75rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .project-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.7rem;
            color: #888;
            border-top: 1px solid #eee;
            padding-top: 5px;
            margin-top: 5px;
        }

        .project-meta i {
            margin-right: 3px;
        }

        .container {
            max-width: 1800px;
            padding: 0 20px;
        }

        @media (max-width: 1600px) {
            .calendar-section {
                margin: 1rem;
                padding: 1rem;
            }
            
            .calendar-grid {
                grid-template-columns: repeat(7, minmax(180px, 1fr));
            }
        }

        @media (max-width: 1200px) {
            .calendar-section {
                margin: 0.5rem;
                padding: 0.5rem;
            }
        }

        .project-type-section {
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            border: 1px solid #e5e5e5;
            max-width: 1600px;
        }

        .section-title {
            color: #333;
            margin-bottom: 2rem;
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: #0066ff;
            border-radius: 2px;
        }

        .project-type-column {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            height: 100%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            border: 1px solid #e9ecef;
        }

        .column-title {
            color: #0066ff;
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #0066ff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .column-title i {
            font-size: 1.1rem;
        }

        .project-type-card {
            background: white;
            border-radius: 8px;
            padding: 1.2rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #e5e5e5;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }

        .project-type-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
        }

        .project-header h6 {
            margin: 0;
            color: #0066ff;
            font-weight: 600;
            font-size: 1rem;
        }

        .date-badge {
            background: #e9ecef;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #666;
            font-weight: 500;
        }

        .project-info {
            font-size: 0.9rem;
        }

        .project-info p {
            margin: 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .project-info p i {
            color: #6c757d;
            width: 16px;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge.pending { 
            background: rgba(255, 193, 7, 0.2); 
            color: #997404;
        }

        .status-badge.in_progress { 
            background: rgba(0, 102, 255, 0.1); 
            color: #0066ff;
        }

        .status-badge.completed { 
            background: rgba(40, 167, 69, 0.1); 
            color: #28a745;
        }

        .status-badge.delayed { 
            background: rgba(220, 53, 69, 0.1); 
            color: #dc3545;
        }

        .project-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            font-size: 0.85rem;
            color: #666;
        }

        .project-footer span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .project-footer i {
            color: #6c757d;
        }

        /* Custom scrollbar for project lists */
        .project-list {
            max-height: 600px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .project-list::-webkit-scrollbar {
            width: 6px;
        }

        .project-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .project-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .project-list::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .project-type-section {
                padding: 1rem;
            }
            
            .project-type-column {
                margin-bottom: 1.5rem;
            }
        }

        .show-more-projects {
            margin: 5px 5px 5px 15px;
            text-align: left;
        }

        .show-more-projects .btn-link {
            padding: 0;
            font-size: 0.8rem;
            color: #0066ff;
            text-decoration: none;
        }

        .show-more-projects .btn-link:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Extended status badge styles */
        .status-badge.not_started { 
            background: rgba(108, 117, 125, 0.1); 
            color: #6c757d;
        }

        .status-badge.in_progress { 
            background: rgba(0, 102, 255, 0.1); 
            color: #0066ff;
        }

        .status-badge.completed { 
            background: rgba(40, 167, 69, 0.1); 
            color: #28a745;
        }

        .status-badge.delayed { 
            background: rgba(253, 126, 20, 0.1); 
            color: #fd7e14;
        }

        .status-badge.critical { 
            background: rgba(220, 53, 69, 0.1); 
            color: #dc3545;
        }

        .status-badge.on_hold { 
            background: rgba(108, 117, 125, 0.1); 
            color: #6c757d;
        }

        .status-badge.cancelled { 
            background: rgba(114, 28, 36, 0.1); 
            color: #721c24;
        }

        .status-badge.review_needed { 
            background: rgba(111, 66, 193, 0.1); 
            color: #6f42c1;
        }

        .status-badge.revision_needed { 
            background: rgba(255, 193, 7, 0.1); 
            color: #ffc107;
        }

        .status-badge.approved { 
            background: rgba(32, 201, 151, 0.1); 
            color: #20c997;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="greeting-section">
            <div class="greeting-text">
                <?php echo $greeting; ?>, <?php echo htmlspecialchars($user_name); ?>!
            </div>
            <div class="date-text">
                <?php echo date('l, j F Y'); ?>
            </div>
        </div>
        
        <div class="calendar-section">
            <div class="calendar-header">
                <div class="calendar-title">
                    <?php echo date('F Y', strtotime("$year-$month-01")); ?>
                </div>
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <select class="form-select" id="userFilter">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <a href="#" onclick="changeMonth(<?php echo $prev_month; ?>, <?php echo $prev_year; ?>); return false;" 
                           class="btn btn-link">&lt;</a>
                        <a href="#" onclick="goToToday(); return false;" class="btn btn-link">Today</a>
                        <a href="#" onclick="changeMonth(<?php echo $next_month; ?>, <?php echo $next_year; ?>); return false;" 
                           class="btn btn-link">&gt;</a>
                    </div>
                </div>
            </div>
            <div class="calendar-grid">
                <?php
                $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                foreach ($days as $day) {
                    echo "<div class='calendar-day'>$day</div>";
                }

                $currentDay = date('j');
                $currentMonth = date('m');
                $currentYear = date('Y');
                
                $totalDays = date('t', strtotime("$year-$month-01"));
                $firstDay = date('w', strtotime("$year-$month-01"));

                // Add empty cells for days before the first day of the month
                for ($i = 0; $i < $firstDay; $i++) {
                    echo "<div class='calendar-date'></div>";
                }

                // Add calendar dates
                for ($day = 1; $day <= $totalDays; $day++) {
                    $isCurrentDate = ($day == $currentDay && $month == $currentMonth && $year == $currentYear);
                    $class = $isCurrentDate ? 'calendar-date current-date' : 'calendar-date';
                    echo "<div class='$class'>
                            <div class='date-box'>
                                <div class='date-header'>
                                    <div class='date-number'>$day</div>
                                    <button class='add-task-btn'>+</button>
                                </div>";
                    
                    // Add project cards if they exist for this day
                    if (isset($projects[$day])) {
                        $projectCount = count($projects[$day]);
                        $maxVisible = 2; // Maximum number of projects to show initially
                        
                        foreach ($projects[$day] as $index => $project) {
                            // Set border color based on project type
                            $borderColor = '';
                            switch($project['project_type']) {
                                case 'Architecture':
                                    $borderColor = '#0066ff'; // Blue
                                    break;
                                case 'Interior':
                                    $borderColor = '#ffc107'; // Yellow
                                    break;
                                case 'Construction':
                                    $borderColor = '#6c757d'; // Grey
                                    break;
                                default:
                                    $borderColor = '#0066ff'; // Default blue
                            }
                            
                            $displayStyle = $index >= $maxVisible ? 'display: none;' : '';
                            echo "<div class='project-card' data-date='$day' style='border-left-color: {$borderColor}; {$displayStyle}'>
                                    <h6>{$project['project_name']}</h6>
                                    <div class='project-details'>
                                        <p><i class='fas fa-tag'></i> {$project['project_type']}</p>
                                        <p><i class='fas fa-user'></i> {$project['client_name']}</p>
                                    </div>
                                    <div class='project-meta'>
                                        <span><i class='fas fa-user-circle'></i> {$project['stage_assigned_to']}</span>
                                        <span><i class='fas fa-user'></i> {$project['assigned_by']}</span>
                                    </div>
                                  </div>";
                        }
                        
                        // Add "Show More" button if there are more than maxVisible projects
                        if ($projectCount > $maxVisible) {
                            $remainingCount = $projectCount - $maxVisible;
                            echo "<div class='show-more-projects' data-date='$day'>
                                    <button class='btn btn-link btn-sm' onclick='toggleProjects(this, $day)'>
                                        +$remainingCount more
                                    </button>
                                  </div>";
                        }
                    }
                    
                    echo "</div></div>";
                }
                ?>
            </div>
        </div>

        <!-- Project Type Section -->
        <div class="project-type-section">
            <h3 class="section-title mb-4">Projects by Type</h3>
            <div class="row">
                <!-- Architecture Column -->
                <div class="col-md-4">
                    <div class="project-type-column">
                        <h4 class="column-title">
                            <i class="fas fa-building"></i> Architecture
                        </h4>
                        <div class="project-list">
                            <?php
                            $arch_query = "SELECT 
                                p.*,
                                u1.username as assigned_by,
                                ps.name as stage_name,
                                ps.status as stage_status,
                                u2.username as stage_assigned_to
                            FROM projects p
                            LEFT JOIN users u1 ON p.got_project_from = u1.id
                            LEFT JOIN project_stages ps ON ps.project_id = p.id
                            LEFT JOIN users u2 ON ps.assigned_to = u2.id
                            WHERE p.project_type = 'Architecture'
                            AND (ps.id IS NULL OR ps.id = (
                                SELECT ps2.id 
                                FROM project_stages ps2 
                                WHERE ps2.project_id = p.id 
                                ORDER BY ps2.created_at DESC 
                                LIMIT 1
                            ))
                            ORDER BY p.created_at DESC";
                            
                            $arch_result = $conn->query($arch_query);
                            while ($project = $arch_result->fetch_assoc()) {
                                echo "<div class='project-type-card' onclick='openProjectTracker({$project['id']})'>
                                        <div class='project-header'>
                                            <h6>{$project['project_name']}</h6>
                                            <span class='date-badge'>".date('d M Y', strtotime($project['created_at']))."</span>
                                        </div>
                                        <div class='project-info'>
                                            <p><strong>Contract:</strong> {$project['contract_number']}</p>
                                            <p><strong>Client:</strong> {$project['client_name']}</p>
                                            <p><strong>Stage:</strong> {$project['stage_name']}</p>
                                            <p><strong>Status:</strong> <span class='status-badge {$project['stage_status']}'>{$project['stage_status']}</span></p>
                                        </div>
                                        <div class='project-footer'>
                                            <span class='assigned-by'><i class='fas fa-user'></i> {$project['assigned_by']}</span>
                                            <span class='stage-owner'><i class='fas fa-user-circle'></i> {$project['stage_assigned_to']}</span>
                                        </div>
                                    </div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Interior Column -->
                <div class="col-md-4">
                    <div class="project-type-column">
                        <h4 class="column-title">
                            <i class="fas fa-couch"></i> Interior
                        </h4>
                        <div class="project-list">
                            <?php
                            $int_query = str_replace("'Architecture'", "'Interior'", $arch_query);
                            $int_result = $conn->query($int_query);
                            while ($project = $int_result->fetch_assoc()) {
                                // Same card structure as Architecture
                                echo "<div class='project-type-card' onclick='openProjectTracker({$project['id']})'>
                                        <div class='project-header'>
                                            <h6>{$project['project_name']}</h6>
                                            <span class='date-badge'>".date('d M Y', strtotime($project['created_at']))."</span>
                                        </div>
                                        <div class='project-info'>
                                            <p><strong>Contract:</strong> {$project['contract_number']}</p>
                                            <p><strong>Client:</strong> {$project['client_name']}</p>
                                            <p><strong>Stage:</strong> {$project['stage_name']}</p>
                                            <p><strong>Status:</strong> <span class='status-badge {$project['stage_status']}'>{$project['stage_status']}</span></p>
                                        </div>
                                        <div class='project-footer'>
                                            <span class='assigned-by'><i class='fas fa-user'></i> {$project['assigned_by']}</span>
                                            <span class='stage-owner'><i class='fas fa-user-circle'></i> {$project['stage_assigned_to']}</span>
                                        </div>
                                    </div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Construction Column -->
                <div class="col-md-4">
                    <div class="project-type-column">
                        <h4 class="column-title">
                            <i class="fas fa-hard-hat"></i> Construction
                        </h4>
                        <div class="project-list">
                            <?php
                            $const_query = str_replace("'Architecture'", "'Construction'", $arch_query);
                            $const_result = $conn->query($const_query);
                            while ($project = $const_result->fetch_assoc()) {
                                // Same card structure as Architecture
                                echo "<div class='project-type-card' onclick='openProjectTracker({$project['id']})'>
                                        <div class='project-header'>
                                            <h6>{$project['project_name']}</h6>
                                            <span class='date-badge'>".date('d M Y', strtotime($project['created_at']))."</span>
                                        </div>
                                        <div class='project-info'>
                                            <p><strong>Contract:</strong> {$project['contract_number']}</p>
                                            <p><strong>Client:</strong> {$project['client_name']}</p>
                                            <p><strong>Stage:</strong> {$project['stage_name']}</p>
                                            <p><strong>Status:</strong> <span class='status-badge {$project['stage_status']}'>{$project['stage_status']}</span></p>
                                        </div>
                                        <div class='project-footer'>
                                            <span class='assigned-by'><i class='fas fa-user'></i> {$project['assigned_by']}</span>
                                            <span class='stage-owner'><i class='fas fa-user-circle'></i> {$project['stage_assigned_to']}</span>
                                        </div>
                                    </div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add your tasks dashboard content here -->
        
    </div>

    <!-- Add Modal Form (add this before closing body tag) -->
    <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTaskModalLabel">Add New Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addTaskForm">
                        <input type="hidden" id="taskDate" name="date">
                        
                        <!-- Project Details Section -->
                        <h6 class="mb-3">Project Details</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contractNumber" class="form-label">Contract Number</label>
                                <input type="text" class="form-control" id="contractNumber" name="contract_number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="projectType" class="form-label">Project Type</label>
                                <select class="form-control" id="projectType" name="project_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Architecture">Architecture</option>
                                    <option value="Interior">Interior</option>
                                    <option value="Construction">Construction</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="projectName" class="form-label">Project Name</label>
                                <input type="text" class="form-control" id="projectName" name="project_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="clientName" class="form-label">Client Name</label>
                                <input type="text" class="form-control" id="clientName" name="client_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="clientGuardianName" class="form-label">Client Father/Husband Name</label>
                                <input type="text" class="form-control" id="clientGuardianName" name="client_guardian_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="clientEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="clientEmail" name="client_email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="clientMobile" class="form-label">Mobile Number</label>
                                <input type="tel" class="form-control" id="clientMobile" name="client_mobile" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="gotProjectFrom" class="form-label">Got Project From</label>
                                <select class="form-control" id="gotProjectFrom" name="got_project_from" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Project Team</label>
                                <div class="team-members-container">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="teamTable">
                                            <thead>
                                                <tr>
                                                    <th>Team Member</th>
                                                    <th>Role</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Team members will be added here dynamically -->
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="addTeamMember()">
                                        <i class="fas fa-plus"></i> Add Team Member
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Stages Section -->
                        <h6 class="mb-3 mt-4">Project Stages</h6>
                        <div id="stagesContainer">
                            <!-- Stage template will be cloned here -->
                        </div>
                        <button type="button" class="btn btn-secondary mt-2" onclick="addStage()">Add Stage</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="submitProject()">Save Project</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stage Template (hidden) -->
    <template id="stageTemplate">
        <div class="stage-item border rounded p-3 mb-3">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Stage Name</label>
                    <input type="text" class="form-control" name="stages[{index}][name]" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Assigned To</label>
                    <select class="form-control" name="stages[{index}][assigned_to]" required>
                        <option value="">Select User</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Due Date</label>
                    <input type="date" class="form-control" name="stages[{index}][due_date]" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="stages[{index}][status]" required>
                        <option value="">Select Status</option>
                        <option value="not_started" data-color="#000000">Not Started</option>
                        <option value="in_progress" data-color="#007bff">In Progress</option>
                        <option value="completed" data-color="#28a745">Completed</option>
                        <option value="delayed" data-color="#fd7e14">Delayed</option>
                        <option value="critical" data-color="#dc3545">Critical</option>
                        <option value="on_hold" data-color="#6c757d">On Hold</option>
                        <option value="cancelled" data-color="#721c24">Cancelled</option>
                        <option value="review_needed" data-color="#6f42c1">Review Needed</option>
                        <option value="revision_needed" data-color="#ffc107">Revision Needed</option>
                        <option value="approved" data-color="#20c997">Approved</option>
                    </select>
                </div>
            </div>

            <!-- Sub-stages Container -->
            <div class="sub-stages-container mt-3">
                <h6>Sub-stages</h6>
                <div class="sub-stages-list">
                    <!-- Sub-stages will be added here -->
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addSubStage(this)">
                    Add Sub-stage
                </button>
            </div>
            
            <button type="button" class="btn btn-danger btn-sm mt-2" onclick="removeStage(this)">Remove Stage</button>
        </div>
    </template>

    <!-- Sub-stage Template (hidden) -->
    <template id="subStageTemplate">
        <div class="sub-stage-item border-start ps-3 mt-2">
            <div class="row">
                <div class="col-md-6 mb-2">
                    <label class="form-label">Sub-stage Name</label>
                    <input type="text" class="form-control" name="stages[{stageIndex}][sub_stages][{subIndex}][name]" required>
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">Assigned To</label>
                    <select class="form-control" name="stages[{stageIndex}][sub_stages][{subIndex}][assigned_to]" required>
                        <option value="">Select User</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">Due Date</label>
                    <input type="date" class="form-control" name="stages[{stageIndex}][sub_stages][{subIndex}][due_date]" required>
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="stages[{stageIndex}][sub_stages][{subIndex}][status]" required>
                        <option value="">Select Status</option>
                        <option value="not_started" data-color="#000000">Not Started</option>
                        <option value="in_progress" data-color="#007bff">In Progress</option>
                        <option value="completed" data-color="#28a745">Completed</option>
                        <option value="delayed" data-color="#fd7e14">Delayed</option>
                        <option value="critical" data-color="#dc3545">Critical</option>
                        <option value="on_hold" data-color="#6c757d">On Hold</option>
                        <option value="cancelled" data-color="#721c24">Cancelled</option>
                        <option value="review_needed" data-color="#6f42c1">Review Needed</option>
                        <option value="revision_needed" data-color="#ffc107">Revision Needed</option>
                        <option value="approved" data-color="#20c997">Approved</option>
                    </select>
                </div>
            </div>
            <button type="button" class="btn btn-danger btn-sm mt-2" onclick="removeSubStage(this)">Remove Sub-stage</button>
        </div>
    </template>

    <!-- Add this template for team member rows -->
    <template id="teamMemberTemplate">
        <tr class="team-member-row">
            <td>
                <select class="form-control select2-member" name="team_members[{index}][user_id]" required>
                    <option value="">Select Member</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['id']); ?>">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <select class="form-control" name="team_members[{index}][role]" required>
                    <option value="">Select Role</option>
                    <option value="Project Manager">Project Manager</option>
                    <option value="Team Lead">Team Lead</option>
                    <option value="Architect">Architect</option>
                    <option value="Interior Designer">Interior Designer</option>
                    <option value="Civil Engineer">Civil Engineer</option>
                    <option value="Site Supervisor">Site Supervisor</option>
                    <option value="Designer">Designer</option>
                    <option value="Team Member">Team Member</option>
                </select>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeTeamMember(this)">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        </tr>
    </template>

    <!-- Add this before the scripts -->
    <script>
        // Make PHP variables available to JavaScript
        const year = <?php echo $year; ?>;
        const month = <?php echo $month; ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function changeMonth(month, year) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('month', month);
        currentUrl.searchParams.set('year', year);
        
        // Use history.pushState to update URL without page reload
        history.pushState({}, '', currentUrl);
        
        // Fetch and update the calendar content
        fetch(currentUrl)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newCalendar = doc.querySelector('.calendar-section');
                document.querySelector('.calendar-section').innerHTML = newCalendar.innerHTML;
            });
    }
    function goToToday() {
        const today = new Date();
        const month = today.getMonth() + 1; // JavaScript months are 0-based
        const year = today.getFullYear();
        
        changeMonth(month, year);
    }

    // Add these new functions
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM Content Loaded');
        const addButtons = document.querySelectorAll('.add-task-btn');
        console.log('Found buttons:', addButtons.length);
        
        addButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                console.log('Button clicked');
                e.preventDefault();
                e.stopPropagation(); // Add this to prevent event bubbling
                
                const dateBox = this.closest('.date-box');
                const day = dateBox.querySelector('.date-number').textContent;
                const date = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
                console.log('Selected date:', date);
                
                document.getElementById('taskDate').value = date;
                
                const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
                modal.show();
            });
        });
    });

    function submitTask() {
        const form = document.getElementById('addTaskForm');
        const formData = new FormData(form);

        fetch('api/add_task.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal and refresh calendar
                bootstrap.Modal.getInstance(document.getElementById('addTaskModal')).hide();
                // Optionally refresh the calendar or add the task to the view
                location.reload(); // For now, just reload the page
            } else {
                alert('Error adding task: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error adding task');
        });
    }

    let stageCounter = 0;

    function addStage() {
        const container = document.getElementById('stagesContainer');
        const template = document.getElementById('stageTemplate');
        const clone = document.importNode(template.content, true); // Use importNode instead of cloneNode
        
        // Replace {index} placeholder with actual index
        const elements = clone.querySelectorAll('[name*="{index}"]');
        elements.forEach(element => {
            element.name = element.name.replace(/{index}/g, stageCounter);
        });
        
        container.appendChild(clone);
        stageCounter++;
    }

    function removeStage(button) {
        button.closest('.stage-item').remove();
    }

    function addSubStage(button) {
        const stageItem = button.closest('.stage-item');
        const subStagesContainer = stageItem.querySelector('.sub-stages-list');
        const template = document.getElementById('subStageTemplate');
        const clone = document.importNode(template.content, true);
        
        // Get stage index from the stage's first input name attribute
        const stageInputName = stageItem.querySelector('input[name^="stages"]').name;
        const stageIndex = stageInputName.match(/stages\[(\d+)\]/)[1];
        const subIndex = subStagesContainer.children.length;
        
        // Replace placeholders with actual indices
        const elements = clone.querySelectorAll('[name*="{stageIndex}"], [name*="{subIndex}"]');
        elements.forEach(element => {
            element.name = element.name
                .replace(/{stageIndex}/g, stageIndex)
                .replace(/{subIndex}/g, subIndex);
        });
        
        subStagesContainer.appendChild(clone);
    }

    function removeSubStage(button) {
        button.closest('.sub-stage-item').remove();
    }

    // Initialize the form when the modal is shown
    document.getElementById('addTaskModal').addEventListener('show.bs.modal', function () {
        // Clear existing stages
        document.getElementById('stagesContainer').innerHTML = '';
        stageCounter = 0;
        // Add initial stage
        addStage();
    });

    // Make sure all buttons use event delegation since they're dynamically added
    document.addEventListener('click', function(e) {
        if (e.target.matches('.add-task-btn')) {
            e.preventDefault();
            e.stopPropagation();
            
            const dateBox = e.target.closest('.date-box');
            const day = dateBox.querySelector('.date-number').textContent;
            const date = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
            
            document.getElementById('taskDate').value = date;
            const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
            modal.show();
        }
    });

    // Update the submitProject function
    function submitProject() {
        const form = document.getElementById('addTaskForm');
        const formData = new FormData(form);

        fetch('api/add_project.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('addTaskModal')).hide();
                location.reload();
            } else {
                alert('Error adding project: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error adding project');
        });
    }

    $(document).ready(function() {
        $('.select2-multiple').select2({
            placeholder: "Select team members",
            allowClear: true,
            width: '100%'
        });
    });
    
    // Reset Select2 when modal is closed
    $('#addTaskModal').on('hidden.bs.modal', function () {
        $('.select2-multiple').val(null).trigger('change');
    });

    let teamMemberCounter = 0;

    function addTeamMember() {
        console.log('Adding team member');
        const tbody = document.querySelector('#teamTable tbody');
        const template = document.getElementById('teamMemberTemplate');
        const clone = document.importNode(template.content, true);
        
        // Replace index placeholder
        const elements = clone.querySelectorAll('[name*="{index}"]');
        elements.forEach(element => {
            element.name = element.name.replace(/{index}/g, teamMemberCounter);
            if (element.classList.contains('select2-member')) {
                element.id = 'team_member_' + teamMemberCounter;
            }
        });
        
        tbody.appendChild(clone);
        
        // Initialize Select2 for the new row
        initializeSelect2ForRow(teamMemberCounter);
        
        teamMemberCounter++;
        console.log('Team member added, counter:', teamMemberCounter);
    }

    function initializeSelect2ForRow(index) {
        const newSelect = document.querySelector(`#team_member_${index}`);
        if (newSelect) {
            $(newSelect).select2({
                placeholder: "Select Member",
                width: '100%',
                dropdownParent: $('#addTaskModal') // This ensures dropdown shows over modal
            });
        }
    }

    function removeTeamMember(button) {
        const row = button.closest('tr');
        const select = row.querySelector('select.select2-member');
        if (select) {
            try {
                $(select).select2('destroy');
            } catch (e) {
                console.log('Select2 instance already destroyed');
            }
        }
        row.remove();
    }

    // Initialize modal events
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('addTaskModal');
        
        // When modal is about to be shown
        modal.addEventListener('show.bs.modal', function () {
            console.log('Modal showing');
            const tbody = document.querySelector('#teamTable tbody');
            tbody.innerHTML = ''; // Clear existing rows
            teamMemberCounter = 0;
            
            // Add first team member row after a short delay
            setTimeout(() => {
                addTeamMember();
            }, 100);
        });

        // When modal is hidden
        modal.addEventListener('hidden.bs.modal', function () {
            console.log('Modal hidden');
            const tbody = document.querySelector('#teamTable tbody');
            const selects = tbody.querySelectorAll('select.select2-member');
            selects.forEach(select => {
                try {
                    $(select).select2('destroy');
                } catch (e) {
                    console.log('Select2 instance already destroyed');
                }
            });
            tbody.innerHTML = '';
            teamMemberCounter = 0;
        });
    });

    // Debug logging for button click
    document.addEventListener('click', function(e) {
        if (e.target.matches('.btn-secondary') && e.target.textContent.includes('Add Team Member')) {
            console.log('Add Team Member button clicked');
        }
    });

    // Add this to your existing JavaScript
    function styleStatusDropdowns() {
        document.querySelectorAll('select[name$="[status]"]').forEach(select => {
            select.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const color = selectedOption.dataset.color;
                this.style.backgroundColor = color;
                this.style.color = ['#000000', '#ffc107'].includes(color) ? '#000000' : '#ffffff';
            });
        });
    }

    // Call this function after adding new stages or sub-stages
    document.addEventListener('DOMContentLoaded', styleStatusDropdowns);

    // Add this to your existing JavaScript
    function toggleProjects(button, date) {
        const dateBox = button.closest('.date-box');
        const projects = dateBox.querySelectorAll(`.project-card[data-date="${date}"]`);
        const showMoreBtn = button.parentElement;
        
        projects.forEach((project, index) => {
            if (index >= 2) { // Skip first two projects
                project.style.display = project.style.display === 'none' ? 'block' : 'none';
            }
        });
        
        // Toggle button text
        if (button.textContent.includes('more')) {
            button.innerHTML = 'Show less';
        } else {
            button.innerHTML = `+${projects.length - 2} more`;
        }
    }
    </script>

    <!-- Project Task Tracker Modal -->
    <div class="modal fade" id="projectTrackerModal" tabindex="-1" aria-labelledby="projectTrackerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="projectTrackerModalLabel">Project Task Tracker</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="project-details-section mb-4">
                        <h6>Project Details</h6>
                        <div class="row" id="projectDetails">
                            <!-- Project details will be populated here -->
                        </div>
                    </div>
                    <div class="stages-section">
                        <h6>Project Stages</h6>
                        <div id="projectStages">
                            <!-- Stages and substages will be populated here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openProjectTracker(projectId) {
        // Show loading state
        const modal = new bootstrap.Modal(document.getElementById('projectTrackerModal'));
        modal.show();
        
        // Fetch project details
        fetch(`api/get_project_details.php?project_id=${projectId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayProjectDetails(data.project);
                    displayProjectStages(data.stages);
                } else {
                    alert('Error loading project details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading project details');
            });
    }

    function displayProjectDetails(project) {
        const detailsContainer = document.getElementById('projectDetails');
        detailsContainer.innerHTML = `
            <div class="col-md-6">
                <p><strong>Project Name:</strong> ${project.project_name}</p>
                <p><strong>Contract Number:</strong> ${project.contract_number}</p>
                <p><strong>Project Type:</strong> ${project.project_type}</p>
                <p><strong>Client Name:</strong> ${project.client_name}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Created Date:</strong> ${project.created_at}</p>
                <p><strong>Assigned By:</strong> ${project.assigned_by}</p>
                <p><strong>Current Stage:</strong> ${project.current_stage}</p>
                <p><strong>Status:</strong> <span class="status-badge ${project.status}">${project.status}</span></p>
            </div>
        `;
    }

    function displayProjectStages(stages) {
        const stagesContainer = document.getElementById('projectStages');
        stagesContainer.innerHTML = '';
        
        stages.forEach(stage => {
            const stageElement = document.createElement('div');
            stageElement.className = 'stage-card mb-3';
            stageElement.innerHTML = `
                <div class="stage-header">
                    <h6>${stage.name}</h6>
                    <span class="status-badge ${stage.status}">${stage.status}</span>
                </div>
                <div class="stage-info">
                    <p><strong>Assigned To:</strong> ${stage.assigned_to}</p>
                    <p><strong>Due Date:</strong> ${stage.due_date}</p>
                </div>
                ${renderSubStages(stage.sub_stages)}
            `;
            stagesContainer.appendChild(stageElement);
        });
    }

    function renderSubStages(subStages) {
        if (!subStages || subStages.length === 0) return '';
        
        let subStagesHtml = '<div class="sub-stages mt-2">';
        subStages.forEach(subStage => {
            subStagesHtml += `
                <div class="sub-stage-card">
                    <div class="sub-stage-header">
                        <h6>${subStage.name}</h6>
                        <span class="status-badge ${subStage.status}">${subStage.status}</span>
                    </div>
                    <div class="sub-stage-info">
                        <p><strong>Assigned To:</strong> ${subStage.assigned_to}</p>
                        <p><strong>Due Date:</strong> ${subStage.due_date}</p>
                    </div>
                </div>
            `;
        });
        subStagesHtml += '</div>';
        return subStagesHtml;
    }
    </script>

    <style>
    /* Add these styles to your existing CSS */
    .project-type-card {
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .project-type-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .stage-card {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 1rem;
    }

    .stage-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .sub-stage-card {
        background: #f8f9fa;
        border-left: 3px solid #0066ff;
        margin: 0.5rem 0;
        padding: 0.75rem;
        border-radius: 4px;
    }

    .sub-stage-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }

    .status-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .status-badge.pending { background: #ffc107; color: #000; }
    .status-badge.in_progress { background: #0066ff; color: #fff; }
    .status-badge.completed { background: #28a745; color: #fff; }
    .status-badge.delayed { background: #dc3545; color: #fff; }
    .status-badge.critical { background: #dc3545; color: #fff; }
    .status-badge.on_hold { background: #6c757d; color: #fff; }
    .status-badge.cancelled { background: #721c24; color: #fff; }
    .status-badge.review_needed { background: #6f42c1; color: #fff; }
    .status-badge.revision_needed { background: #ffc107; color: #000; }
    .status-badge.approved { background: #20c997; color: #fff; }
    </style>
</body>
</html>
