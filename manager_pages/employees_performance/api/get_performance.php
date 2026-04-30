<?php
/**
 * api/get_performance.php
 * Fetches real performance data from the database.
 */
header('Content-Type: application/json');
require_once '../../../config/db_connect.php';

try {
    // Determine the period date range
    $periodStr = isset($_GET['period']) ? $_GET['period'] : 'This Month';
    $periodStart = date('Y-m-01');
    $periodEnd = date('Y-m-d');
    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $periodStart = $_GET['start_date'];
        $periodEnd = $_GET['end_date'];
    } else {
        switch ($periodStr) {
        case 'This Week':
            // Start of week (Monday) to today
            $periodStart = date('Y-m-d', strtotime('monday this week'));
            $periodEnd = date('Y-m-d');
            break;
        case 'Last Week':
            $periodStart = date('Y-m-d', strtotime('monday last week'));
            $periodEnd = date('Y-m-d', strtotime('sunday last week'));
            break;
        case 'This Month':
            $periodStart = date('Y-m-01');
            $periodEnd = date('Y-m-d');
            break;
        case 'Last Month':
            $periodStart = date('Y-m-d', strtotime('first day of last month'));
            $periodEnd = date('Y-m-d', strtotime('last day of last month'));
            break;
        case 'Q1 2026':
            $periodStart = '2026-01-01';
            $periodEnd = '2026-03-31';
            break;
        case 'Q4 2025':
            $periodStart = '2025-10-01';
            $periodEnd = '2025-12-31';
            break;
    }
    }

    // 1. Fetch Users
    $stmtUsers = $pdo->query("SELECT id, username, role, department, profile_image FROM users WHERE status = 'Active'");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Tasks mapped to users and projects (filtered by period)
    // We consider tasks created or completed within the period
    $stmtTasks = $pdo->prepare("
        SELECT id, project_id, project_name, task_description, assigned_to, status, due_date, created_at, completed_at, extension_count, completion_reject_count, extension_history 
        FROM studio_assigned_tasks
        WHERE (DATE(created_at) BETWEEN ? AND ?) 
           OR (DATE(completed_at) BETWEEN ? AND ?)
    ");
    $stmtTasks->execute([$periodStart, $periodEnd, $periodStart, $periodEnd]);
    $allTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Data for Attendance Calculation
    // Period for calculation is based on the selected period filter above.

    // Fetch office holidays
    $stmtHols = $pdo->query("SELECT holiday_date FROM office_holidays WHERE holiday_date BETWEEN '$periodStart' AND '$periodEnd'");
    $holidays = $stmtHols->fetchAll(PDO::FETCH_COLUMN);

    // Fetch approved leaves
    $stmtLeaves = $pdo->query("SELECT user_id, start_date, end_date FROM leave_request WHERE status = 'approved' AND start_date <= '$periodEnd' AND end_date >= '$periodStart'");
    $allLeaves = $stmtLeaves->fetchAll(PDO::FETCH_ASSOC);

    // Fetch user shifts for weekly offs
    // Sometimes effective_to is null
    $stmtShifts = $pdo->query("SELECT user_id, weekly_offs, effective_from, effective_to FROM user_shifts");
    $allUserShifts = $stmtShifts->fetchAll(PDO::FETCH_ASSOC);

    // Fetch present days from attendance
    $stmtAtt = $pdo->query("
        SELECT user_id, COUNT(*) as present_days
        FROM attendance
        WHERE status = 'present' AND date BETWEEN '$periodStart' AND '$periodEnd'
        GROUP BY user_id
    ");
    $attData = [];
    foreach ($stmtAtt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $attData[$row['user_id']] = (int)$row['present_days'];
    }

    $employees = [];
    $allProjectsSet = [];

    // Helper to extract user IDs from assigned_to (e.g., "1,2,3" or "21")
    function extractUserIds($assignedStr) {
        if (!$assignedStr) return [];
        return array_map('trim', explode(',', $assignedStr));
    }

    foreach ($users as $u) {
        $uid = $u['id'];

        // Filter tasks for this user
        $userTasks = array_filter($allTasks, function($t) use ($uid) {
            $ids = extractUserIds($t['assigned_to']);
            return in_array((string)$uid, $ids);
        });

        // Group tasks by project
        $projectStats = [];
        $totalTasks = 0;
        $totalCompleted = 0;
        $totalIncomplete = 0;
        $totalOnTime = 0;
        $totalExtensions = 0;
        $totalRejects = 0;

        foreach ($userTasks as $t) {
            if ($t['status'] === 'Cancelled') continue; // Don't penalize or count cancelled tasks

            $pName = $t['project_name'] ? $t['project_name'] : 'Internal / Unknown';
            $allProjectsSet[$pName] = true;

            if (!isset($projectStats[$pName])) {
                $projectStats[$pName] = [
                    'name' => $pName,
                    'total' => 0,
                    'completed' => 0,
                    'incomplete' => 0,
                    'on_time' => 0,
                    'extensions' => 0,
                    'rejects' => 0,
                    'tasks_list' => []
                ];
            }

            $projectStats[$pName]['total']++;
            $totalTasks++;

            if ($t['status'] === 'Completed') {
                $projectStats[$pName]['completed']++;
                $totalCompleted++;

                // Check on-time (completed_at <= due_date)
                if (!empty($t['completed_at']) && !empty($t['due_date'])) {
                    $dueTime = strtotime($t['due_date'] . ' 23:59:59');
                    $compTime = strtotime($t['completed_at']);
                    if ($compTime <= $dueTime) {
                        $projectStats[$pName]['on_time']++;
                        $totalOnTime++;
                    }
                }
            } else if ($t['status'] === 'Incomplete') {
                $projectStats[$pName]['incomplete']++;
                $totalIncomplete++;
            }

            $ext = (int)$t['extension_count'];
            $projectStats[$pName]['extensions'] += $ext;
            $totalExtensions += $ext;

            $rej = (int)$t['completion_reject_count'];
            $projectStats[$pName]['rejects'] += $rej;
            $totalRejects += $rej;

            // Calculate duration for completed tasks
            $durationStr = 'N/A';
            if ($t['status'] === 'Completed' && !empty($t['completed_at']) && !empty($t['created_at'])) {
                $start = new DateTime($t['created_at']);
                $end = new DateTime($t['completed_at']);
                $diff = $start->diff($end);
                $durationStr = $diff->days > 0 ? $diff->days . ' days' : ($diff->h > 0 ? $diff->h . ' hours' : 'Under 1 hr');
            }

            $projectStats[$pName]['tasks_list'][] = [
                'desc' => $t['task_description'] ? $t['task_description'] : 'Task #'.$t['id'],
                'status' => $t['status'],
                'due_date' => $t['due_date'],
                'created_at' => $t['created_at'],
                'completed_at' => $t['completed_at'],
                'duration' => $durationStr,
                'extensions' => (int)$t['extension_count'],
                'extension_history' => $t['extension_history']
            ];
        }

        // Include all active users even if they don't have tasks or attendance yet
        // Defaulting them to base metrics below.

        // Calculate Overall Metrics
        // Calculate Expected Working Days
        $userLeaves = array_filter($allLeaves, function($l) use ($uid) { return $l['user_id'] == $uid; });
        $userShifts = array_filter($allUserShifts, function($s) use ($uid) { return $s['user_id'] == $uid; });
        
        $expectedWorkingDays = 0;
        $currentDate = strtotime($periodStart);
        $endDate = strtotime($periodEnd);
        
        while ($currentDate <= $endDate) {
            $dateStr = date('Y-m-d', $currentDate);
            $dayName = date('l', $currentDate);
            
            // Is it a holiday?
            if (in_array($dateStr, $holidays)) {
                $currentDate = strtotime('+1 day', $currentDate);
                continue;
            }
            
            // Is it an approved leave?
            $onLeave = false;
            foreach ($userLeaves as $l) {
                if ($dateStr >= $l['start_date'] && $dateStr <= $l['end_date']) {
                    $onLeave = true;
                    break;
                }
            }
            if ($onLeave) {
                $currentDate = strtotime('+1 day', $currentDate);
                continue;
            }
            
            // Is it a weekly off?
            $isWeekOff = false;
            foreach ($userShifts as $s) {
                // Check if shift is effective for this date
                $effFrom = !empty($s['effective_from']) ? $s['effective_from'] : '1970-01-01';
                $effTo = !empty($s['effective_to']) ? $s['effective_to'] : '2099-12-31';
                
                if ($dateStr >= $effFrom && $dateStr <= $effTo) {
                    $offs = array_map('trim', explode(',', $s['weekly_offs']));
                    if (in_array($dayName, $offs)) {
                        $isWeekOff = true;
                    }
                    break; // use the first matching shift definition
                }
            }
            if ($isWeekOff) {
                $currentDate = strtotime('+1 day', $currentDate);
                continue;
            }
            
            // If we get here, it's a working day
            $expectedWorkingDays++;
            $currentDate = strtotime('+1 day', $currentDate);
        }

        // Attendance Percentage Calculation
        $attendancePct = 100; // default if no working days evaluated
        if ($expectedWorkingDays > 0) {
            $present = isset($attData[$uid]) ? $attData[$uid] : 0;
            // Cap at 100% in case they worked on a holiday/off day
            $attendancePct = min(100, round(($present / $expectedWorkingDays) * 100));
        }

        // Tasks (Completion Rate)
        $tasksPct = $totalTasks > 0 ? round(($totalCompleted / $totalTasks) * 100) : 0;
        
        // Overall Score (Weighted Average: 50% Attendance, 50% Tasks)
        $score = round(($attendancePct * 0.50) + ($tasksPct * 0.50));

        // Format Project Data
        $formattedProjects = [];
        foreach ($projectStats as $pName => $pData) {
            $pTasksPct = $pData['total'] > 0 ? round(($pData['completed'] / $pData['total']) * 100) : 0;
            
            // Project Score (Weighted Average: 50% Attendance, 50% Tasks)
            $pScore = round(($attendancePct * 0.50) + ($pTasksPct * 0.50));

            $formattedProjects[] = [
                'name' => $pName,
                'tasks' => $pTasksPct,
                'score' => $pScore,
                'tasks_list' => $pData['tasks_list']
            ];
        }

        // Avatar color generator based on user ID
        $colors = ['#7c3aed', '#0ea5e9', '#10b981', '#ec4899', '#f97316', '#a855f7', '#06b6d4', '#f59e0b', '#64748b'];
        $avatar_color = $colors[$uid % count($colors)];

        $employees[] = [
            'id' => (int)$uid,
            'name' => $u['username'],
            'role' => $u['role'] ? $u['role'] : 'Employee',
            'dept' => $u['department'] ? $u['department'] : 'General',
            'avatar_color' => $avatar_color,
            'attendance' => $attendancePct,
            'tasks' => $tasksPct,
            'score' => $score,
            'projects' => $formattedProjects
        ];
    }

    // Fetch master list of projects for the dropdown regardless of period
    $stmtAllProjs = $pdo->query("SELECT DISTINCT project_name FROM studio_assigned_tasks WHERE project_name IS NOT NULL AND project_name != ''");
    $masterProjects = $stmtAllProjs->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'employees' => array_values($employees),
        'projects' => $masterProjects
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
