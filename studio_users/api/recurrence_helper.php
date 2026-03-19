<?php
/**
 * api/recurrence_helper.php
 * Helper to expand recurring tasks within a given date range.
 */

function expandRecurringTasks($tasks, $startDate, $endDate) {
    $expanded = [];
    $existingInstances = []; // Map of task_id => [array of materialized dates]
    $startTs = strtotime($startDate);
    $endTs = strtotime($endDate);

    foreach ($tasks as $task) {
        // Track materialized instances to avoid duplicates
        if ($task['recurrence_parent_id']) {
            $key = $task['due_date'] . '_' . ($task['due_time'] ?: '00:00:00');
            $existingInstances[$task['recurrence_parent_id']][] = $key;
        }
    }

    foreach ($tasks as $task) {
        // Skip child instances themselves for expansion to avoid recursive expansion
        if ($task['recurrence_parent_id']) continue;

        // Always add the original task if it falls in range
        $taskDueTs = $task['due_date'] ? strtotime($task['due_date']) : null;
        if ($taskDueTs && $taskDueTs >= $startTs && $taskDueTs <= $endTs) {
            $isHourly = (!empty($task['recurrence_freq']) && ($task['recurrence_freq'] === 'Hourly' || strpos(strtolower($task['recurrence_freq']), 'hour') !== false));
            $baseHour = $taskDueTs ? (int)date('H', $taskDueTs) : null;
            $baseMin = $taskDueTs ? (int)date('i', $taskDueTs) : null;
            
            $isAfter8PM = ($baseHour > 20) || ($baseHour === 20 && $baseMin > 0);
            $isBefore9AM = ($baseHour !== null && $baseHour < 9);

            if (!($isHourly && ($isBefore9AM || $isAfter8PM))) {
                $expanded[] = $task;
            }
        }

        // If not recurring, skip further processing
        if (empty($task['is_recurring']) || $task['is_recurring'] == 0) continue;

        $freq = $task['recurrence_freq'] ?? null;
        if (!$freq) continue;

        $baseDateStr = $task['due_date'] . ' ' . ($task['due_time'] ?: '00:00:00');
        $baseDate = new DateTime($baseDateStr);
        $currentDate = clone $baseDate;
        
        // Define increment interval
        $intervalStr = '';
        if ($freq === 'Daily') $intervalStr = 'P1D';
        else if ($freq === 'Weekly') $intervalStr = 'P7D';
        else if ($freq === 'Monthly') $intervalStr = 'P1M';
        else if ($freq === 'Yearly') $intervalStr = 'P1Y';
        else if ($freq === 'Hourly') $intervalStr = 'PT1H';
        else if (strpos($freq, 'Every') === 0) {
            // Parse "Every X Days/Weeks/Months"
            $parts = explode(' ', $freq);
            if (count($parts) >= 3) {
                $num = intval($parts[1]);
                $unit = strtolower($parts[2]);
                if (strpos($unit, 'minute') !== false) $intervalStr = "PT{$num}M";
                else if (strpos($unit, 'hour') !== false) $intervalStr = "PT{$num}H";
                else if (strpos($unit, 'day') !== false) $intervalStr = "P{$num}D";
                else if (strpos($unit, 'week') !== false) $intervalStr = "P" . ($num * 7) . "D";
                else if (strpos($unit, 'month') !== false) $intervalStr = "P{$num}M";
                else if (strpos($unit, 'year') !== false) $intervalStr = "P{$num}Y";
            }
        }

        if (!$intervalStr) continue;

        $interval = new DateInterval($intervalStr);
        $safetyMax = 1000; // Limit instances to prevent infinite loops (increased for hourly tasks)
        $count = 0;

        // Start from the next occurrence
        $currentDate->add($interval);

        while ($currentDate->getTimestamp() <= $endTs && $count < $safetyMax) {
            $dateStr = $currentDate->format('Y-m-d');
            $timeStr = $currentDate->format('H:i:s');
            $combinedKey = $dateStr . '_' . $timeStr;
            
            // Limit hourly tasks between 9:00 AM and 8:00 PM
            $hour = (int)$currentDate->format('H');
            $min = (int)$currentDate->format('i');
            $isAfter8PM = ($hour > 20) || ($hour === 20 && $min > 0);
            $isBefore9AM = ($hour < 9);
            $isHourly = ($freq === 'Hourly' || strpos(strtolower($freq ?? ''), 'hour') !== false);

            if ($isHourly && ($isBefore9AM || $isAfter8PM)) {
                $currentDate->add($interval);
                $count++;
                continue;
            }

            // Skip if a real materialized instance already exists for this date+time
            if (isset($existingInstances[$task['id']]) && in_array($combinedKey, $existingInstances[$task['id']])) {
                $currentDate->add($interval);
                $count++;
                continue;
            }

            if ($currentDate->getTimestamp() >= $startTs) {
                $newInstance = $task;
                $newInstance['due_date'] = $currentDate->format('Y-m-d');
                $newInstance['due_time'] = $currentDate->format('H:i:s');
                $newInstance['is_virtual'] = true; // Mark as virtual instance
                $newInstance['id'] = $task['id'] . '_' . $currentDate->format('YmdHi'); // More unique for hourly
                $expanded[] = $newInstance;
            }
            $currentDate->add($interval);
            $count++;
        }
    }
    return $expanded;
}
