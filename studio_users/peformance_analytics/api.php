<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

try {
    $data = [];
    $now = new DateTime();
    $today = $now->format('Y-m-d');
    $startOfMonth = $now->format('Y-m-01');
    $next7Days = (clone $now)->modify('+7 days')->format('Y-m-d');

    // 1. Summary
    // Overall Efficiency (On-time completion rate)
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_completed,
        SUM(CASE WHEN completed_date <= due_date THEN 1 ELSE 0 END) as on_time
        FROM pa_tasks WHERE status = 'Completed'");
    $eff = $stmt->fetch(PDO::FETCH_ASSOC);
    $efficiency = ($eff['total_completed'] > 0) ? round(($eff['on_time'] / $eff['total_completed']) * 100) : 0;

    // Active Tasks
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pa_tasks WHERE status = 'In Progress'");
    $activeTasks = $stmt->fetchColumn();

    // This Month (Completed)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pa_tasks WHERE status = 'Completed' AND completed_date >= ?");
    $stmt->execute([$startOfMonth]);
    $thisMonth = $stmt->fetchColumn();

    // Upcoming Deadlines (Next 7 days, Not completed)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pa_tasks WHERE status != 'Completed' AND due_date BETWEEN ? AND ?");
    $stmt->execute([$today, $next7Days]);
    $upcoming = $stmt->fetchColumn();

    $data['summary'] = [
        'efficiency' => $efficiency,
        'active_tasks' => $activeTasks,
        'this_month' => $thisMonth,
        'upcoming' => $upcoming
    ];

    // 2. Pie Chart (Task Statuses)
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM pa_tasks GROUP BY status");
    $counts = ['Completed' => 0, 'In Progress' => 0, 'Pending' => 0, 'Not Started' => 0];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $counts[$row['status']] = (int)$row['count'];
    }
    $data['pie'] = [
        $counts['Completed'],
        $counts['In Progress'],
        $counts['Pending'],
        $counts['Not Started']
    ];

    // 3. Trend Data
    // For simplicity, we just calculate last 12 months array for efficiency & completion
    // Monthly stats
    $months12 = [];
    for($i=11; $i>=0; $i--) {
        $m = (clone $now)->modify("-$i months");
        $months12[] = $m->format('n-Y'); // month-year
    }

    $trend12 = ['labels' => [], 'efficiency' => [], 'completion' => []];
    $trend6 = ['labels' => [], 'efficiency' => [], 'completion' => []];

    foreach($months12 as $idx => $my) {
        list($m, $y) = explode('-', $my);
        
        $mName = date('M Y', mktime(0, 0, 0, $m, 1, $y));
        $trend12['labels'][] = $mName;

        // Total Tasks this month 
        // tasks created in or before this month? Let's keep it simple: Tasks completed this month vs Tasks due this month
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total_due,
            SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as total_completed,
            SUM(CASE WHEN status='Completed' AND completed_date <= due_date THEN 1 ELSE 0 END) as on_time
            FROM pa_tasks 
            WHERE MONTH(due_date) = ? AND YEAR(due_date) = ?");
        $stmt->execute([$m, $y]);
        $mStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $cr = ($mStats['total_due'] > 0) ? round(($mStats['total_completed'] / $mStats['total_due']) * 100) : 0;
        $effRate = ($mStats['total_completed'] > 0) ? round(($mStats['on_time'] / $mStats['total_completed']) * 100) : 0;

        $trend12['completion'][] = $cr;
        $trend12['efficiency'][] = $effRate;

        if ($idx >= 6) {
            $trend6['labels'][] = $mName;
            $trend6['completion'][] = $cr;
            $trend6['efficiency'][] = $effRate;
        }
    }

    $data['trend'] = ['data6M' => $trend6, 'data12M' => $trend12];

    // 4. Completions & Projects
    $stmt = $pdo->query("SELECT * FROM pa_tasks ORDER BY completed_date DESC, due_date ASC");
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data['tasks'] = $tasks;

    echo json_encode($data);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
