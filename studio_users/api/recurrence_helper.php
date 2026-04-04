<?php
/**
 * api/recurrence_helper.php
 * Helper to expand recurring tasks within a given date range.
 *
 * Recurrence limits per frequency:
 *   Hourly  → repeats only on the SAME day as original due date, up to 6:00 PM
 *   Daily   → max 90 instances (per extension cycle)
 *   Weekly  → max 15 instances
 *   Monthly → max 12 instances
 *   Yearly  → max  5 instances
 *   Custom  → inherits limit based on unit
 *
 * Extension:
 *   Each time the user clicks "Extend Recurrence", recurrence_extra is incremented.
 *   effectiveMax = base_limit × (1 + recurrence_extra)
 *
 * Expiry warning:
 *   The very last emitted instance per task gets is_last_recurrence = true so the
 *   frontend can show the "Expiry" modal with Mark Done / Extend options.
 */

function expandRecurringTasks($tasks, $startDate, $endDate) {
    $expanded          = [];
    $existingInstances = []; // [parent_id => ['Y-m-d_H:i:s', ...]]
    $existingDates     = []; // [parent_id => ['Y-m-d', ...]]
    $startTs = strtotime($startDate);
    $endTs   = strtotime($endDate);

    $normalizeTime = function ($time) {
        if (!$time) return '00:00:00';
        $ts = strtotime((string)$time);
        if ($ts === false) return '00:00:00';
        return date('H:i:s', $ts);
    };

    // ── Pass 1: catalogue already-materialised (completed) instances ─────
    foreach ($tasks as $task) {
        if ($task['recurrence_parent_id']) {
            $key = $task['due_date'] . '_' . $normalizeTime($task['due_time'] ?? null);
            $existingInstances[$task['recurrence_parent_id']][] = $key;
            if (!empty($task['due_date'])) {
                $existingDates[$task['recurrence_parent_id']][] = $task['due_date'];
            }
        }
    }

    // ── Pass 2: expand master tasks ──────────────────────────────────────
    foreach ($tasks as $task) {
        // Include materialized child instances directly (these carry persisted
        // per-instance fields like progress/status/history).
        if (!empty($task['recurrence_parent_id'])) {
            $taskDueTs = $task['due_date'] ? strtotime($task['due_date']) : null;
            if ($taskDueTs && $taskDueTs >= $startTs && $taskDueTs <= $endTs) {
                $expanded[] = $task;
            }
            continue;
        }

        // Add the original task if it falls in the requested window.
        // For recurring masters, if a materialized child instance exists at
        // the same due date/time slot, skip the master so persisted child data
        // (progress/status/etc.) is used consistently.
        $taskDueTs = $task['due_date'] ? strtotime($task['due_date']) : null;
        $baseKey = ($task['due_date'] ?: '') . '_' . $normalizeTime($task['due_time'] ?? null);
        $hasMaterializedExact = !empty($task['is_recurring'])
            && isset($existingInstances[$task['id']])
            && in_array($baseKey, $existingInstances[$task['id']], true);

        // Legacy safety: for non-hourly recurrences, older materialized rows may
        // have NULL/00:00 due_time. If any child exists on the same date, prefer
        // that child over the master for that day.
        $freq = $task['recurrence_freq'] ?? null;
        $isHourlyMaster = ($freq === 'Hourly') || (is_string($freq) && stripos($freq, 'Every ') === 0 && stripos($freq, 'hour') !== false);
        $hasMaterializedSameDate = !$isHourlyMaster
            && !empty($task['is_recurring'])
            && !empty($task['due_date'])
            && isset($existingDates[$task['id']])
            && in_array($task['due_date'], $existingDates[$task['id']], true);

        $hasMaterializedBase = $hasMaterializedExact || $hasMaterializedSameDate;

        if ($taskDueTs && $taskDueTs >= $startTs && $taskDueTs <= $endTs && !$hasMaterializedBase) {
            $expanded[] = $task;
        }

        if (empty($task['is_recurring']) || $task['is_recurring'] == 0) continue;

        $freq = $task['recurrence_freq'] ?? null;
        if (!$freq) continue;

        // ── Base limits per frequency ────────────────────────────────────
        $baseLimit   = 90;
        $intervalStr = '';
        $isHourly    = false;
        $isSameDay   = false;

        if ($freq === 'Hourly') {
            $intervalStr = 'PT1H';
            $baseLimit   = 11;       // 9 AM → 6 PM max slots
            $isHourly    = true;
            $isSameDay   = true;

        } elseif ($freq === 'Daily') {
            $intervalStr = 'P1D';
            $baseLimit   = 90;

        } elseif ($freq === 'Weekly') {
            $intervalStr = 'P7D';
            $baseLimit   = 15;

        } elseif ($freq === 'Monthly') {
            $intervalStr = 'P1M';
            $baseLimit   = 12;

        } elseif ($freq === 'Yearly') {
            $intervalStr = 'P1Y';
            $baseLimit   = 5;

        } elseif (strpos($freq, 'Every') === 0) {
            $parts = explode(' ', $freq);
            if (count($parts) >= 3) {
                $num  = intval($parts[1]);
                $unit = strtolower($parts[2]);
                if (strpos($unit, 'minute') !== false) {
                    $intervalStr = "PT{$num}M"; $baseLimit = 90; $isHourly = true; $isSameDay = true;
                } elseif (strpos($unit, 'hour') !== false) {
                    $intervalStr = "PT{$num}H"; $baseLimit = 11; $isHourly = true; $isSameDay = true;
                } elseif (strpos($unit, 'day')   !== false) {
                    $intervalStr = "P{$num}D";   $baseLimit = 90;
                } elseif (strpos($unit, 'week')  !== false) {
                    $intervalStr = 'P' . ($num * 7) . 'D'; $baseLimit = 15;
                } elseif (strpos($unit, 'month') !== false) {
                    $intervalStr = "P{$num}M";   $baseLimit = 12;
                } elseif (strpos($unit, 'year')  !== false) {
                    $intervalStr = "P{$num}Y";   $baseLimit = 5;
                }
            }
        }

        if (!$intervalStr) continue;

        // ── Effective max: base × (1 + number of extensions) ────────────
        $extraExtensions = isset($task['recurrence_extra']) ? max(0, intval($task['recurrence_extra'])) : 0;
        $effectiveMax    = $baseLimit * (1 + $extraExtensions);

        $interval    = new DateInterval($intervalStr);
        $baseDateStr = $task['due_date'] . ' ' . ($task['due_time'] ?: '00:00:00');
        $baseDate    = new DateTime($baseDateStr);
        $currentDate = clone $baseDate;

        // For same-day tasks (hourly) — hard stop at 18:00 of the original day
        $sameDayEndTs = null;
        if ($isSameDay) {
            $sameDayEnd = clone $baseDate;
            $sameDayEnd->setTime(18, 0, 0);
            $sameDayEndTs = $sameDayEnd->getTimestamp();
        }

        // ── Generate all instances for this task into a local buffer ─────
        // Then mark the LAST emitted one as is_last_recurrence = true.
        $taskInstances = [];
        $count = 0;
        $currentDate->add($interval);

        while ($count < $effectiveMax) {
            $curTs = $currentDate->getTimestamp();

            // Same-day / hourly cap
            if ($isSameDay && $curTs > $sameDayEndTs) break;

            // Date-window cap
            if ($curTs > $endTs) break;

            $dateStr     = $currentDate->format('Y-m-d');
            $timeStr     = $currentDate->format('H:i:s');
            $combinedKey = $dateStr . '_' . $timeStr;

            // Skip out-of-day hours for hourly tasks
            if ($isHourly) {
                $hour     = (int)$currentDate->format('H');
                $min      = (int)$currentDate->format('i');
                $after6PM  = ($hour > 18) || ($hour === 18 && $min > 0);
                $before9AM = ($hour < 9);
                if ($before9AM || $after6PM) {
                    $currentDate->add($interval);
                    $count++;
                    continue;
                }
            }

            // Skip already-materialised (completed) instances
            if (
                isset($existingInstances[$task['id']]) &&
                in_array($combinedKey, $existingInstances[$task['id']])
            ) {
                $currentDate->add($interval);
                $count++;
                continue;
            }

            // Only emit if within the requested window
            if ($curTs >= $startTs) {
                $newInstance                      = $task;
                $newInstance['due_date']          = $dateStr;
                $newInstance['due_time']          = $timeStr;
                // --- FIX: Reset completion status for virtual instances ---
                $newInstance['status']             = 'Pending';
                $newInstance['completed_by']       = null;
                $newInstance['completed_at']       = null;
                $newInstance['completion_history'] = null;
                // -----------------------------------------------------------
                $newInstance['is_virtual']        = true;
                $newInstance['is_last_recurrence']= false; // will be updated below
                $newInstance['recurrence_base_limit'] = $baseLimit;
                $newInstance['id']                = $task['id'] . '_' . $currentDate->format('YmdHi');
                $taskInstances[] = $newInstance;
            }

            $currentDate->add($interval);
            $count++;
        }

        // ── Mark the very last emitted instance as expiring ─────────────
        // This fires when count reaches effectiveMax (no more extensions applied yet)
        if (!empty($taskInstances) && $count >= $effectiveMax) {
            $last = count($taskInstances) - 1;
            $taskInstances[$last]['is_last_recurrence'] = true;
        }

        $expanded = array_merge($expanded, $taskInstances);
    }

    return $expanded;
}
