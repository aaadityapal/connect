<?php
/**
 * Dashboard Cards Component
 * 
 * Provides functions to generate dashboard cards for site updates including:
 * - Today's Tasks
 * - Task Summary
 * - Upcoming Tasks
 * - Task Efficiency
 */

/**
 * Get today's tasks card content
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id The current user ID
 * @return array Card data with title, count, and items
 */
function getTodayTasksCard($pdo, $user_id) {
    $today = date('Y-m-d');
    
    // Get tasks for today
    $stmt = $pdo->prepare("SELECT su.id, s.site_name, su.update_date 
                          FROM site_updates su
                          JOIN sites s ON su.site_id = s.id
                          WHERE DATE(su.update_date) = ? AND (su.created_by = ? OR su.site_id IN 
                            (SELECT site_id FROM site_supervisors WHERE user_id = ?))
                          ORDER BY su.update_date DESC");
    $stmt->execute([$today, $user_id, $user_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'title' => "Today's Tasks",
        'icon' => 'fa-tasks',
        'count' => count($tasks),
        'items' => $tasks,
        'color' => 'primary',
        'link' => 'site_updates.php?date=' . $today
    ];
}

/**
 * Get task summary card content
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id The current user ID
 * @return array Card data with title, count, and summary data
 */
function getTaskSummaryCard($pdo, $user_id) {
    // Get task summary - total tasks, completed tasks, pending tasks
    $stmt = $pdo->prepare("SELECT 
                            COUNT(*) as total_tasks,
                            SUM(CASE WHEN EXISTS (
                                SELECT 1 FROM update_work_progress 
                                WHERE update_id = su.id AND completed = 100
                            ) THEN 1 ELSE 0 END) as completed_tasks
                          FROM site_updates su
                          WHERE su.created_by = ? OR su.site_id IN 
                            (SELECT site_id FROM site_supervisors WHERE user_id = ?)");
    $stmt->execute([$user_id, $user_id]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = intval($summary['total_tasks']);
    $completed = intval($summary['completed_tasks']);
    $pending = $total - $completed;
    $completion_rate = $total > 0 ? round(($completed / $total) * 100) : 0;
    
    return [
        'title' => 'Task Summary',
        'icon' => 'fa-chart-pie',
        'count' => $total,
        'summary' => [
            'completed' => $completed,
            'pending' => $pending,
            'completion_rate' => $completion_rate
        ],
        'color' => 'success',
        'link' => 'site_updates.php'
    ];
}

/**
 * Get upcoming tasks card content
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id The current user ID
 * @return array Card data with title, count, and items
 */
function getUpcomingTasksCard($pdo, $user_id) {
    $today = date('Y-m-d');
    $nextWeek = date('Y-m-d', strtotime('+7 days'));
    
    // Get upcoming tasks for the next 7 days
    $stmt = $pdo->prepare("SELECT su.id, s.site_name, su.update_date 
                          FROM site_updates su
                          JOIN sites s ON su.site_id = s.id
                          WHERE DATE(su.update_date) > ? AND DATE(su.update_date) <= ?
                          AND (su.created_by = ? OR su.site_id IN 
                            (SELECT site_id FROM site_supervisors WHERE user_id = ?))
                          ORDER BY su.update_date ASC");
    $stmt->execute([$today, $nextWeek, $user_id, $user_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'title' => 'Upcoming Tasks',
        'icon' => 'fa-calendar-alt',
        'count' => count($tasks),
        'items' => $tasks,
        'color' => 'info',
        'link' => 'site_updates.php?date_start=' . date('Y-m-d', strtotime('+1 day')) . '&date_end=' . $nextWeek
    ];
}

/**
 * Get task efficiency card content
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id The current user ID
 * @return array Card data with title, efficiency percentage, and trend
 */
function getTaskEfficiencyCard($pdo, $user_id) {
    // Calculate task efficiency based on completion rates and timeliness
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    
    $stmt = $pdo->prepare("SELECT 
                            COUNT(*) as total_updates,
                            SUM(CASE WHEN last_updated <= DATE_ADD(update_date, INTERVAL 1 DAY) THEN 1 ELSE 0 END) as timely_updates
                          FROM site_updates
                          WHERE update_date >= ? AND (created_by = ? OR site_id IN 
                            (SELECT site_id FROM site_supervisors WHERE user_id = ?))");
    $stmt->execute([$thirtyDaysAgo, $user_id, $user_id]);
    $efficiency = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = intval($efficiency['total_updates']);
    $timely = intval($efficiency['timely_updates']);
    $efficiency_rate = $total > 0 ? round(($timely / $total) * 100) : 0;
    
    // Calculate trend (simplified)
    $sixtyDaysAgo = date('Y-m-d', strtotime('-60 days'));
    $stmt = $pdo->prepare("SELECT 
                            COUNT(*) as total_updates,
                            SUM(CASE WHEN last_updated <= DATE_ADD(update_date, INTERVAL 1 DAY) THEN 1 ELSE 0 END) as timely_updates
                          FROM site_updates
                          WHERE update_date >= ? AND update_date < ? AND (created_by = ? OR site_id IN 
                            (SELECT site_id FROM site_supervisors WHERE user_id = ?))");
    $stmt->execute([$sixtyDaysAgo, $thirtyDaysAgo, $user_id, $user_id]);
    $prev_efficiency = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $prev_total = intval($prev_efficiency['total_updates']);
    $prev_timely = intval($prev_efficiency['timely_updates']);
    $prev_efficiency_rate = $prev_total > 0 ? round(($prev_timely / $prev_total) * 100) : 0;
    
    $trend = $efficiency_rate - $prev_efficiency_rate;
    
    return [
        'title' => 'Task Efficiency',
        'icon' => 'fa-tachometer-alt',
        'efficiency' => $efficiency_rate,
        'trend' => $trend,
        'color' => $efficiency_rate >= 70 ? 'success' : ($efficiency_rate >= 50 ? 'warning' : 'danger'),
        'link' => 'site_updates.php?efficiency=1'
    ];
}

/**
 * Render all dashboard cards
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id The current user ID
 * @return string HTML for all dashboard cards
 */
function renderDashboardCards($pdo, $user_id) {
    $todayTasks = getTodayTasksCard($pdo, $user_id);
    $taskSummary = getTaskSummaryCard($pdo, $user_id);
    $upcomingTasks = getUpcomingTasksCard($pdo, $user_id);
    $taskEfficiency = getTaskEfficiencyCard($pdo, $user_id);
    
    ob_start();
    ?>
    <div class="row dashboard-cards">
        <!-- Today's Tasks Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-<?= $todayTasks['color'] ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?= $todayTasks['color'] ?> text-uppercase mb-1">
                                <?= $todayTasks['title'] ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $todayTasks['count'] ?></div>
                            
                            <?php if (!empty($todayTasks['items'])): ?>
                                <div class="mt-3 small">
                                    <?php foreach(array_slice($todayTasks['items'], 0, 3) as $task): ?>
                                        <div class="mb-1">
                                            <i class="fas fa-dot-circle mr-1 text-<?= $todayTasks['color'] ?>"></i>
                                            <?= htmlspecialchars($task['site_name']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($todayTasks['items']) > 3): ?>
                                        <div class="text-muted">+ <?= count($todayTasks['items']) - 3 ?> more</div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <i class="fas <?= $todayTasks['icon'] ?> fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <a href="<?= $todayTasks['link'] ?>" class="card-footer text-<?= $todayTasks['color'] ?> clearfix small z-1">
                    <span class="float-left">View Details</span>
                    <span class="float-right">
                        <i class="fas fa-angle-right"></i>
                    </span>
                </a>
            </div>
        </div>

        <!-- Task Summary Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-<?= $taskSummary['color'] ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?= $taskSummary['color'] ?> text-uppercase mb-1">
                                <?= $taskSummary['title'] ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $taskSummary['count'] ?> Tasks</div>
                            
                            <div class="row mt-3">
                                <div class="col-6">
                                    <div class="small font-weight-bold">
                                        Completed: <?= $taskSummary['summary']['completed'] ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="small font-weight-bold">
                                        Pending: <?= $taskSummary['summary']['pending'] ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="progress progress-sm mt-2">
                                <div class="progress-bar bg-<?= $taskSummary['color'] ?>" role="progressbar" 
                                     style="width: <?= $taskSummary['summary']['completion_rate'] ?>%" 
                                     aria-valuenow="<?= $taskSummary['summary']['completion_rate'] ?>" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                            <div class="small mt-1 text-center">
                                <?= $taskSummary['summary']['completion_rate'] ?>% Complete
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas <?= $taskSummary['icon'] ?> fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <a href="<?= $taskSummary['link'] ?>" class="card-footer text-<?= $taskSummary['color'] ?> clearfix small z-1">
                    <span class="float-left">View Details</span>
                    <span class="float-right">
                        <i class="fas fa-angle-right"></i>
                    </span>
                </a>
            </div>
        </div>

        <!-- Upcoming Tasks Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-<?= $upcomingTasks['color'] ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?= $upcomingTasks['color'] ?> text-uppercase mb-1">
                                <?= $upcomingTasks['title'] ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $upcomingTasks['count'] ?></div>
                            
                            <?php if (!empty($upcomingTasks['items'])): ?>
                                <div class="mt-3 small">
                                    <?php foreach(array_slice($upcomingTasks['items'], 0, 3) as $task): ?>
                                        <div class="mb-1">
                                            <i class="far fa-calendar mr-1 text-<?= $upcomingTasks['color'] ?>"></i>
                                            <?= date('M d', strtotime($task['update_date'])) ?>: 
                                            <?= htmlspecialchars($task['site_name']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($upcomingTasks['items']) > 3): ?>
                                        <div class="text-muted">+ <?= count($upcomingTasks['items']) - 3 ?> more</div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <i class="fas <?= $upcomingTasks['icon'] ?> fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <a href="<?= $upcomingTasks['link'] ?>" class="card-footer text-<?= $upcomingTasks['color'] ?> clearfix small z-1">
                    <span class="float-left">View Details</span>
                    <span class="float-right">
                        <i class="fas fa-angle-right"></i>
                    </span>
                </a>
            </div>
        </div>

        <!-- Task Efficiency Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-<?= $taskEfficiency['color'] ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?= $taskEfficiency['color'] ?> text-uppercase mb-1">
                                <?= $taskEfficiency['title'] ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $taskEfficiency['efficiency'] ?>%</div>
                            
                            <div class="mt-3">
                                <div class="small">
                                    <?php if ($taskEfficiency['trend'] > 0): ?>
                                        <span class="text-success mr-2"><i class="fas fa-arrow-up"></i> <?= abs($taskEfficiency['trend']) ?>%</span>
                                        <span class="small">since last month</span>
                                    <?php elseif ($taskEfficiency['trend'] < 0): ?>
                                        <span class="text-danger mr-2"><i class="fas fa-arrow-down"></i> <?= abs($taskEfficiency['trend']) ?>%</span>
                                        <span class="small">since last month</span>
                                    <?php else: ?>
                                        <span class="text-muted mr-2"><i class="fas fa-equals"></i> No change</span>
                                        <span class="small">since last month</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="progress progress-sm mt-2">
                                    <div class="progress-bar bg-<?= $taskEfficiency['color'] ?>" role="progressbar" 
                                         style="width: <?= $taskEfficiency['efficiency'] ?>%" 
                                         aria-valuenow="<?= $taskEfficiency['efficiency'] ?>" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas <?= $taskEfficiency['icon'] ?> fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <a href="<?= $taskEfficiency['link'] ?>" class="card-footer text-<?= $taskEfficiency['color'] ?> clearfix small z-1">
                    <span class="float-left">View Details</span>
                    <span class="float-right">
                        <i class="fas fa-angle-right"></i>
                    </span>
                </a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?> 