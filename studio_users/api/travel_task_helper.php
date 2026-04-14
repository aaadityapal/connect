<?php
/**
 * TRAVEL TASK WINDOW HELPER
 * studio_users/api/travel_task_helper.php
 *
 * Finds the next open approval window across a set of approvers
 * based on their travel_approver_day_schedules.
 *
 * Logic:
 *  - For each approver, scan forward up to 14 days to find their next active day.
 *  - If checking today, skip if the day's window has already closed.
 *  - Return the EARLIEST due_date + due_time across all approvers,
 *    so the task is due when the FIRST available approver can action it.
 *
 * @param PDO   $pdo         Active DB connection
 * @param array $approverIds Array of user IDs to check
 * @return array ['due_date' => 'YYYY-MM-DD', 'due_time' => 'HH:MM:SS']
 */
function getNextApprovalWindow(PDO $pdo, array $approverIds): array
{
    date_default_timezone_set('Asia/Kolkata');
    $now         = new DateTime();
    $currentTime = $now->format('H:i:s');

    $earliestDate = null;
    $earliestTime = '18:00:00';

    foreach ($approverIds as $aid) {
        // Fetch all active-day rows for this approver
        $stmt = $pdo->prepare("
            SELECT day_name, start_time, end_time
            FROM travel_approver_day_schedules
            WHERE approver_id = ? AND is_active = 1
        ");
        $stmt->execute([(int)$aid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build lookup keyed by day name
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[$r['day_name']] = $r;
        }

        // Fallback: Mon-Fri 09:00-18:00 if no schedule exists
        if (empty($byDay)) {
            foreach (['Monday','Tuesday','Wednesday','Thursday','Friday'] as $d) {
                $byDay[$d] = ['start_time' => '09:00:00', 'end_time' => '18:00:00'];
            }
        }

        // Scan up to 14 days forward to find the next open window
        $found = null;
        for ($i = 0; $i <= 14; $i++) {
            $checkDt  = (clone $now)->modify("+{$i} days");
            $dayName  = $checkDt->format('l');   // "Monday", "Saturday", etc.

            if (!isset($byDay[$dayName])) continue; // day not active

            $endTime = $byDay[$dayName]['end_time'];

            // If checking today make sure window hasn't already closed
            if ($i === 0 && $currentTime > $endTime) continue;

            $found = [
                'date'     => $checkDt->format('Y-m-d'),
                'end_time' => $endTime
            ];
            break;
        }

        // Ultimate fallback: tomorrow 18:00
        if (!$found) {
            $found = [
                'date'     => (clone $now)->modify('+1 day')->format('Y-m-d'),
                'end_time' => '18:00:00'
            ];
        }

        // Keep track of the EARLIEST date across all approvers
        if ($earliestDate === null || $found['date'] < $earliestDate) {
            $earliestDate = $found['date'];
            $earliestTime = $found['end_time'];
        }
    }

    return [
        'due_date' => $earliestDate ?? date('Y-m-d'),
        'due_time' => $earliestTime
    ];
}
?>
