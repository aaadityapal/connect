<?php
/**
 * api/fetch_dashboard_tasks.php
 * 
 * Returns the HTML for the Task List rows to be injected into the table.
 * Restored to original layout and design with explicit column widths.
 */
session_start();
require_once '../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Unauthorized";
    exit();
}

$user_id = intval($_SESSION['user_id']);
$fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('monday this week'));
$toDate   = $_GET['to']   ?? date('Y-m-d', strtotime('sunday this week'));

// --- Helper Functions from index.php ---
function getPersonColorLocal($name) {
    if (!$name) return '#94a3b8';
    $palette = [
        '#ef4444', '#f97316', '#f59e0b', '#84cc16', '#22c55e', '#10b981', '#14b8a6', '#06b6d4', 
        '#0ea5e9', '#3b82f6', '#6366f1', '#8b5cf6', '#d946ef', '#ec4899', '#f43f5e',
        '#dc2626', '#ea580c', '#d97706', '#65a30d', '#16a34a', '#059669', '#0d9488', '#0891b2',
        '#0284c7', '#2563eb', '#4f46e5', '#7c3aed', '#c026d3', '#db2777', '#e11d48'
    ];
    $sum = 0;
    $name = trim($name);
    for ($i = 0; $i < strlen($name); $i++) {
        $sum += ord($name[$i]);
    }
    return $palette[$sum % count($palette)];
}

try {
    // Fetch user's tasks for the dynamic board
    $tlQuery = "SELECT sat.*, u.username as creator_name 
                FROM studio_assigned_tasks sat
                LEFT JOIN users u ON sat.created_by = u.id
                WHERE sat.deleted_at IS NULL 
                AND FIND_IN_SET(:uid, REPLACE(sat.assigned_to, ', ', ',')) 
                AND sat.due_date BETWEEN :from AND :to
                ORDER BY sat.created_at DESC LIMIT 50";
    
    $tlStmt = $pdo->prepare($tlQuery);
    $tlStmt->execute(['uid' => $user_id, 'from' => $fromDate, 'to' => $toDate]);
    $rows = $tlStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo '<tr><td colspan="7" style="text-align:center; padding: 2rem; color: #94a3b8;">No tasks found for you.</td></tr>';
        exit();
    }

    foreach ($rows as $row) {
        $priority = $row['priority'] ?: 'Low';
        $pColor = $priority === 'High' ? '#ef4444' : ($priority === 'Medium' ? '#f59e0b' : '#10b981');
        $pBg = $priority === 'High' ? 'rgba(239, 68, 68, 0.08)' : ($priority === 'Medium' ? 'rgba(245, 158, 11, 0.08)' : 'rgba(16, 185, 129, 0.08)');
        
        $extHistory = json_decode($row['extension_history'] ?? '[]', true);
        if (!empty($extHistory)) {
            $firstExt = $extHistory[0];
            $initDate = $firstExt['previous_due_date'] ?: $row['due_date'];
            $initTime = $firstExt['previous_due_time'] ?: $row['due_time'];
        } else {
            $initDate = $row['due_date'];
            $initTime = $row['due_time'];
        }

        $targetDate = $initDate ? date('M j', strtotime($initDate)) : 'No Date';
        $targetTime = $initTime ? date('h:i A', strtotime($initTime)) : '11:59 PM';
        
        $compHist = json_decode($row['completion_history'] ?? '{}', true);
        $myCompAt = $compHist[$user_id] ?? null;
        $compDate = $myCompAt ? date('M j', strtotime($myCompAt)) : '--';
        $compTime = $myCompAt ? date('h:i A', strtotime($myCompAt)) : '--';
        
        $myCompAtIso = null;
        if ($myCompAt) {
            try {
                $myCompAtIso = (new DateTime($myCompAt, new DateTimeZone('Asia/Kolkata')))->format('c');
            } catch (Exception $e) { $myCompAtIso = null; }
        }
        
        $assignees = array_filter(array_map('trim', explode(',', $row['assigned_names'] ?? '')));
        $aCount = count($assignees);
        $assignedIdsList = array_filter(array_map('trim', explode(',', $row['assigned_to'] ?? '')));
        $completedIdsList = array_filter(array_map('trim', explode(',', $row['completed_by'] ?? '')));
        
        $assigneeStatuses = [];
        for ($idx = 0; $idx < count($assignedIdsList); $idx++) {
            $cId = $assignedIdsList[$idx];
            $assigneeStatuses[] = [
                'name' => $assignees[$idx] ?? "User $cId",
                'status' => in_array($cId, $completedIdsList) ? 'Completed' : 'Pending',
                'extended' => false,
                'extension_count' => 0
            ];
        }

        $myStatus = in_array((string)$user_id, $completedIdsList) ? 'Completed' : ($row['status'] === 'Cancelled' ? 'Cancelled' : ($row['status'] === 'Completed' ? 'Completed' : 'Pending'));
        $tmObject = [
            'id' => (int)$row['id'],
            'projectStage' => trim($row['project_name'] . ($row['stage_number'] ? " - Stage " . $row['stage_number'] : "")),
            'title' => $row['task_description'],
            'desc' => $row['task_description'],
            'status' => $myStatus,
            'global_status' => $row['status'],
            'priority' => $priority,
            'dotColor' => $pColor,
            'person' => !empty($assignees) ? $assignees[0] : 'Unassigned',
            'assignees' => $assignees,
            'assignedBy' => $row['creator_name'] ?: 'System Manager',
            'dateFrom' => date('M j, Y - g:i A', strtotime($row['created_at'])),
            'dateTo' => ($row['due_date'] ? date('M j, Y', strtotime($row['due_date'])) . ' - ' . ($row['due_time'] ? date('g:i A', strtotime($row['due_time'])) : '11:59 PM') : 'No Deadline'),
            'extension_count' => (int)$row['extension_count'],
            'extension_history' => $extHistory,
            'due_date' => $row['due_date'],
            'due_time_24' => $row['due_time'] ? date('H:i', strtotime($row['due_time'])) : null,
            'my_completed_at' => $myCompAtIso,
            'assignee_statuses' => $assigneeStatuses
        ];
        $tmJson = htmlspecialchars(json_encode($tmObject), ENT_QUOTES, 'UTF-8');
        ?>
        <tr class="task-list-row el-519" 
            data-task-json='<?php echo $tmJson; ?>'
            style="box-shadow: 0 2px 10px rgba(0,0,0,0.04); background: linear-gradient(to right, <?php echo $pBg; ?> 0%, #ffffff 40%); border-radius: 8px; cursor: pointer;">
            
            <!-- Width: 34% -->
            <td style="width: 34%; white-space: normal; word-wrap: break-word; padding: 12px 16px; font-weight: 500; color: #475569; border-left: 4px solid <?php echo $pColor; ?>; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                <div style="display: flex; flex-direction: column;">
                    <span style="font-weight: 700; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($row['task_description']); ?></span>
                    <span style="font-size: 0.75rem; color: #64748b;"><?php echo htmlspecialchars($row['project_name']); ?><?php if($row['stage_number']) echo " • Stage " . htmlspecialchars($row['stage_number']); ?></span>
                </div>
            </td>

            <!-- Width: 14% -->
            <td style="width: 14%; text-align: center; vertical-align: middle;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <div style="display: flex; align-items: center;">
                        <?php for($i=0; $i<min(2, $aCount); $i++): 
                             $uCol = ltrim(getPersonColorLocal($assignees[$i]), '#');
                        ?>
                             <div style="width: 24px; height: 24px; border-radius: 50%; overflow: hidden; border: 2px solid #fff; margin-right: -8px;">
                                 <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($assignees[$i]); ?>&background=<?php echo $uCol; ?>&color=fff" style="width: 100%; height: 100%; object-fit: cover;">
                             </div>
                        <?php endfor; ?>
                        <?php if($aCount > 2): ?>
                             <div style="width: 24px; height: 24px; border-radius: 50%; background: #f1f5f9; color: #64748b; font-size: 10px; display: flex; align-items: center; justify-content: center; border: 2px solid #fff;">+<?php echo $aCount-2; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </td>

            <!-- Width: 20% -->
            <td style="width: 20%; padding: 0; vertical-align: middle;">
                <div style="display:flex; height: 32px; align-items:center; border: 1px solid #f1f5f9; border-radius: 6px; overflow: hidden; margin: 0 8px;">
                    <span style="flex: 1; text-align: center; padding: 4px 2px; font-size: 0.75rem; background: #fff; color: #475569; font-weight: 500;">
                        <?php echo $targetDate; ?>
                    </span>
                    <span style="flex: 1; text-align: center; padding: 4px 2px; border-left: 1px solid #f1f5f9; font-size: 0.75rem; background: #f8fafc; color: #64748b;">
                        <?php echo $targetTime; ?>
                    </span>
                </div>
            </td>

            <!-- Width: 20% -->
            <td style="width: 20%; padding: 0; vertical-align: middle;">
                <div style="display:flex; height: 32px; align-items:center; border: 1px solid #f1f5f9; border-radius: 6px; overflow: hidden; margin: 0 8px; color: <?php echo $myCompAt ? '#10b981' : '#94a3b8'; ?>;">
                    <span style="flex: 1; text-align: center; padding: 4px 2px; font-size: 0.75rem; background: #fff; font-weight: 500;">
                        <?php echo $compDate; ?>
                    </span>
                    <span style="flex: 1; text-align: center; padding: 4px 2px; border-left: 1px solid #f1f5f9; font-size: 0.75rem; background: #f8fafc;">
                        <?php echo $compTime; ?>
                    </span>
                </div>
            </td>

            <!-- Width: 6% -->
            <td style="width: 6%; text-align:center; vertical-align: middle;">
                <span style="font-weight: 600; color: #64748b; font-size: 0.85rem;">
                    <?php echo (int)$row['extension_count']; ?>
                </span>
            </td>

            <!-- Width: 6% -->
            <td style="width: 6%; text-align:center; vertical-align: middle; padding-right: 8px; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: <?php echo $myStatus === 'Completed' ? '#dcfce7' : '#fef9c3'; ?>; color: <?php echo $myStatus === 'Completed' ? '#16a34a' : '#854d0e'; ?>; border: 1px solid <?php echo $myStatus === 'Completed' ? '#bbf7d0' : '#fef08a'; ?>;">
                    <i class="fa-solid <?php echo $myStatus === 'Completed' ? 'fa-check' : 'fa-clock'; ?>" style="font-size: 0.7rem;"></i>
                </div>
            </td>
        </tr>
    <?php }
} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
