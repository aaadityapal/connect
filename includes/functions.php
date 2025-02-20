/**
 * Get pending leave request details
 * 
 * @param PDO $pdo Database connection
 * @return array Array of pending leave requests
 */
function getPendingLeaveDetails($pdo) {
    try {
        // Use the global database connection if $pdo is not passed
        if (!$pdo) {
            $pdo = getDBConnection();
            error_log("Using global database connection");
        }

        // Verify connection
        if (!$pdo) {
            error_log("No valid database connection available");
            return [];
        }

        // Debug: Check connection status
        error_log("Database connection status: " . ($pdo ? "Connected" : "Not Connected"));

        $query = "
            SELECT 
                lr.id,
                lr.user_id,
                lr.leave_type,
                lr.start_date,
                lr.end_date,
                lr.reason,
                lr.duration,
                lr.status,
                lr.created_at,
                lr.action_reason,
                lr.action_by,
                lr.action_at,
                lr.action_comments,
                u.username,
                u.full_name
            FROM leave_request lr
            INNER JOIN users u ON lr.user_id = u.id
            WHERE LOWER(lr.status) = 'pending'
            ORDER BY lr.created_at DESC
        ";

        // Debug: Log the query
        error_log("Executing query: " . $query);

        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Log results
        error_log("Found " . count($results) . " pending leaves");
        
        return $results;
    } catch (PDOException $e) {
        error_log("Database error in getPendingLeaveDetails: " . $e->getMessage());
        return [];
    } catch (Exception $e) {
        error_log("General error in getPendingLeaveDetails: " . $e->getMessage());
        return [];
    }
} 

/**
 * Get count of pending leaves
 *
 * @param PDO $pdo Database connection
 * @return int Count of pending leaves
 */
function getPendingLeavesCount($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_request WHERE status = 'pending'");
        $stmt->execute();
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error counting pending leaves: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get leave type label
 *
 * @param string $type Leave type
 * @return string Leave type label
 */
function getLeaveTypeLabel($type) {
    $types = [
        'casual' => 'Casual Leave',
        'sick' => 'Sick Leave',
        'earned' => 'Earned Leave',
        'compensatory' => 'Compensatory Leave',
        'maternity' => 'Maternity Leave',
        'paternity' => 'Paternity Leave',
        'bereavement' => 'Bereavement Leave',
        'unpaid' => 'Unpaid Leave',
        'other' => 'Other Leave'
    ];
    return $types[$type] ?? ucfirst($type);
}

function hasPermission($permission) {
    // For now, return true to allow access
    // You can implement proper permission checks later
    return true;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
} 