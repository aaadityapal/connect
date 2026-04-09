<?php
require_once '../../config/db_connect.php';

echo "Starting Leave Bank Generation...\n";

try {
    // 1. Get all active users
    $stmt = $pdo->query("SELECT id, username, joining_date, role FROM users WHERE status != 'deleted'");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Define Leave Type IDs (matching the database)
    $typeIds = [
        'Sick'         => 2,
        'Casual'       => 3,
        'Maternity'    => 5,
        'Paternity'    => 6,
        'Short'        => 11,
        'Compensate'   => 12,
        'BackOffice'   => 15
    ];

    $today = new DateTime('2026-03-21'); // Current context date
    $currentYear = 2026;
    
    // Determine the start of the current cycle (April 1st of the relevant year)
    $cycleStart = new DateTime('2025-04-01');
    if ($today->format('n') < 4) {
        $cycleStart = new DateTime(($currentYear - 1) . '-04-01');
    }
    
    // Calculate months passed in the current cycle
    $interval = $cycleStart->diff($today);
    $monthsInCycle = ($interval->y * 12) + $interval->m + 1; // +1 to include current month

    foreach ($users as $user) {
        echo "Processing User: {$user['username']} (ID: {$user['id']})\n";
        
        $jDate = $user['joining_date'] ? new DateTime($user['joining_date']) : new DateTime('2024-01-01');
        
        // --- 1. Casual Leave (ID 3) --- 
        // Rule: +1 every month from April to April. Reset every April.
        $casual = $monthsInCycle;
        // If joined in the middle of the cycle, adjust
        if ($jDate > $cycleStart) {
            $jInt = $jDate->diff($today);
            $casual = ($jInt->y * 12) + $jInt->m + 1;
        }

        // --- 2. Short Leave (ID 11) --- 
        // Rule: +2 every month and lapse every month. So current balance is always 2 for the month.
        $short = 2.00;

        // --- 3. Sick Leave (ID 2) --- 
        // Rule: +0.5 every month, rolling 24-month window
        // After 24 months: +0.5 (new) -0.5 (24 months ago expires)
        $joiningDate = $user['joining_date'] ? new DateTime($user['joining_date']) : new DateTime('2024-01-01');
        $monthsSinceJoining = ($joiningDate->diff($today)->y * 12) + $joiningDate->diff($today)->m + 1;
        
        if ($monthsSinceJoining <= 24) {
            // First 24 months: simple accrual
            $sick = $monthsSinceJoining * 0.5;
        } else {
            // After 24 months: rolling window (max 12, minus any used)
            // Total accrued in last 24 months = 24 * 0.5 = 12
            $sick = 12.0;
        }

        // --- 4. Maternity/Paternity (IDs 5, 6) ---
        $mat = 28.00;
        $pat = 7.00;

        // --- 5. Back Office Leave (ID 15) ---
        // Rule: +3 every month April to April. Lapses every April.
        $backOffice = 0;
        if (strpos(strtolower($user['role']), 'back office') !== false) {
             $backOffice = $monthsInCycle * 3;
             if ($jDate > $cycleStart) {
                 $jInt = $jDate->diff($today);
                 $backOffice = (($jInt->y * 12) + $jInt->m + 1) * 3;
             }
             if ($backOffice > 36) $backOffice = 36; // Capped as per user "is of 36 day"
        }

        // --- 6. Compensate Leave (ID 12) ---
        // Rule: +1 if worked on week off. Fetch from attendance.
        // First get user's week off from user_shifts
        $shiftStmt = $pdo->prepare("SELECT weekly_offs FROM user_shifts WHERE user_id = ? ORDER BY effective_from DESC LIMIT 1");
        $shiftStmt->execute([$user['id']]);
        $shiftRow = $shiftStmt->fetch(PDO::FETCH_ASSOC);
        $offDays = $shiftRow ? explode(',', $shiftRow['weekly_offs']) : ['Saturday', 'Sunday'];
        $offDays = array_map(function($d) { return trim(strtolower($d)); }, $offDays);
        
        $comp = 0;
        if (!empty($offDays)) {
            // Count attendance on those days (only for the current cycle)
            $attQuery = "SELECT date FROM attendance WHERE user_id = ? AND date >= ?";
            $attStmt = $pdo->prepare($attQuery);
            $attStmt->execute([$user['id'], $cycleStart->format('Y-m-d')]);
            $dates = $attStmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($dates as $d) {
                $dayName = strtolower(date('l', strtotime($d)));
                if (in_array($dayName, $offDays)) {
                    $comp++;
                }
            }
        }

        // Save balances to database
        $balances = [
            $typeIds['Sick']       => $sick,
            $typeIds['Casual']     => $casual,
            $typeIds['Maternity']  => $mat,
            $typeIds['Paternity']  => $pat,
            $typeIds['Short']      => $short,
            $typeIds['Compensate'] => $comp,
            $typeIds['BackOffice'] => $backOffice
        ];

        foreach ($balances as $typeId => $val) {
            $insertSql = "INSERT INTO leave_bank (user_id, leave_type_id, total_balance, remaining_balance, year) 
                          VALUES (?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE 
                          remaining_balance = remaining_balance + (VALUES(total_balance) - total_balance),
                          total_balance = VALUES(total_balance),
                          year = VALUES(year)";
            $stmtInsert = $pdo->prepare($insertSql);
            // I'll assume remaining = total for this "create" step, unless user says otherwise.
            $stmtInsert->execute([$user['id'], $typeId, $val, $val, $currentYear]);
        }
    }

    echo "Finished updating Leave Bank for all users.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
