<?php
/**
 * Shift Functions
 * Functions to fetch user shift information from the database
 */

/**
 * Get user's current shift end time
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array Shift information or default values
 */
function getUserShiftEndTime($pdo, $user_id) {
    try {
        $current_date = date('Y-m-d');
        
        $query = "SELECT s.shift_name, s.end_time 
                  FROM user_shifts us
                  JOIN shifts s ON us.shift_id = s.id
                  WHERE us.user_id = ? 
                  AND us.effective_from <= ?
                  AND (us.effective_to IS NULL OR us.effective_to >= ?)
                  ORDER BY us.effective_from DESC 
                  LIMIT 1";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $user_id,
            $current_date,
            $current_date
        ]);
        
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($shift) {
            return [
                'shift_name' => $shift['shift_name'],
                'end_time' => $shift['end_time']
            ];
        } else {
            // Return default shift if no specific shift is assigned
            return [
                'shift_name' => 'Default Shift',
                'end_time' => '18:00:00'
            ];
        }
    } catch (Exception $e) {
        error_log("Error fetching user shift: " . $e->getMessage());
        // Return default shift on error
        return [
            'shift_name' => 'Default Shift',
            'end_time' => '18:00:00'
        ];
    }
}

/**
 * Get all user shifts for a specific date
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param string $date Date in Y-m-d format
 * @return array Shift information
 */
function getUserShiftForDate($pdo, $user_id, $date) {
    try {
        $query = "SELECT s.shift_name, s.start_time, s.end_time, us.weekly_offs
                  FROM user_shifts us
                  JOIN shifts s ON us.shift_id = s.id
                  WHERE us.user_id = ? 
                  AND us.effective_from <= ?
                  AND (us.effective_to IS NULL OR us.effective_to >= ?)
                  ORDER BY us.effective_from DESC 
                  LIMIT 1";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $user_id,
            $date,
            $date
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'shift_name' => 'Default Shift',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'weekly_offs' => 'Saturday,Sunday'
        ];
    } catch (Exception $e) {
        error_log("Error fetching user shift for date: " . $e->getMessage());
        return [
            'shift_name' => 'Default Shift',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'weekly_offs' => 'Saturday,Sunday'
        ];
    }
}

/**
 * Get user's work report for a specific date
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param string $date Date in Y-m-d format
 * @return string Work report or empty string
 */
function getUserWorkReport($pdo, $user_id, $date) {
    try {
        $query = "SELECT work_report 
                  FROM attendance 
                  WHERE user_id = ? 
                  AND date = ? 
                  LIMIT 1";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $user_id,
            $date
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['work_report'] : '';
    } catch (Exception $e) {
        error_log("Error fetching user work report: " . $e->getMessage());
        return '';
    }
}
?>