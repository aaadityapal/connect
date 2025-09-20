<?php
// Get Employees API - Fetch real employee data from users table
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Start session and check authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Include database connection
require_once '../../config/db_connect.php';

// Function to get day number from day name
function getDayNumber($dayName) {
    $days = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 7
    ];
    return $days[strtolower(trim($dayName))] ?? null;
}

// Function to calculate working days for a specific month and employee
function calculateWorkingDays($pdo, $userId, $year, $month) {
    try {
        // Get the first and last day of the month
        $startDate = date('Y-m-01', strtotime("$year-$month-01"));
        $endDate = date('Y-m-t', strtotime("$year-$month-01"));
        
        // Get employee's weekly offs from user_shifts table
        $weeklyOffQuery = "
            SELECT us.weekly_offs 
            FROM user_shifts us 
            WHERE us.user_id = ? 
            AND us.effective_from <= ? 
            AND (us.effective_to IS NULL OR us.effective_to >= ?)
            ORDER BY us.effective_from DESC 
            LIMIT 1
        ";
        
        $stmt = $pdo->prepare($weeklyOffQuery);
        $stmt->execute([$userId, $startDate, $startDate]);
        $weeklyOffResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $weeklyOffs = $weeklyOffResult ? $weeklyOffResult['weekly_offs'] : 'Saturday,Sunday';
        $weeklyOffArray = !empty($weeklyOffs) ? explode(',', $weeklyOffs) : ['Saturday', 'Sunday'];
        
        // Convert weekly offs to day numbers
        $weeklyOffNumbers = [];
        foreach ($weeklyOffArray as $day) {
            $dayNumber = getDayNumber($day);
            if ($dayNumber) {
                $weeklyOffNumbers[] = $dayNumber;
            }
        }
        
        // Get holidays for the month
        $holidayQuery = "SELECT date FROM holidays WHERE date BETWEEN ? AND ?";
        $stmt = $pdo->prepare($holidayQuery);
        $stmt->execute([$startDate, $endDate]);
        $holidays = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Calculate working days
        $currentDate = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);
        $workingDays = 0;
        
        while ($currentDate <= $endDateObj) {
            $dayOfWeek = $currentDate->format('N'); // 1 (Monday) to 7 (Sunday)
            $currentDateStr = $currentDate->format('Y-m-d');
            
            // Check if current day is not a weekly off and not a holiday
            if (!in_array($dayOfWeek, $weeklyOffNumbers) && !in_array($currentDateStr, $holidays)) {
                $workingDays++;
            }
            
            $currentDate->modify('+1 day');
        }
        
        return $workingDays;
    } catch (Exception $e) {
        error_log("Error calculating working days for user $userId: " . $e->getMessage());
        // Return default working days if calculation fails
        return 22;
    }
}

try {
    // Get parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $role = isset($_GET['role']) ? trim($_GET['role']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 30;
    $offset = ($page - 1) * $limit;
    
    // Get selected month (default to current month)
    $selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
    $year = date('Y', strtotime($selectedMonth));
    $month = date('m', strtotime($selectedMonth));

    // Base query to get employees from users table - only active employees
    $whereConditions = ["u.deleted_at IS NULL", "u.status = 'active'"];
    $params = [];

    // Add search filter
    if (!empty($search)) {
        $whereConditions[] = "(u.username LIKE ? OR u.unique_id LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Add role filter if needed (assuming role is stored in users table)
    if (!empty($role)) {
        $whereConditions[] = "u.role = ?";
        $params[] = $role;
    }

    // Add salary processing status filter if needed (this would be for salary processing status, not employee status)
    // Note: Since we're only showing active employees, 'status' filter here refers to salary processing status
    if (!empty($status)) {
        // This could be used for salary processing status in the future
        // For now, we'll ignore it since we're focusing on active employees only
        // $whereConditions[] = "salary_status = ?";
        // $params[] = $status;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Count total records
    $countQuery = "SELECT COUNT(*) as total FROM users u WHERE $whereClause";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get employees with pagination
    $query = "
        SELECT 
            u.id,
            u.username,
            u.unique_id,
            u.role,
            u.status,
            u.created_at,
            u.updated_at
        FROM users u 
        WHERE $whereClause
        ORDER BY u.username ASC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add working days information to each employee
    foreach ($employees as &$employee) {
        $employee['working_days'] = calculateWorkingDays($pdo, $employee['id'], $year, $month);
    }

    // Calculate pagination info
    $totalPages = ceil($totalRecords / $limit);
    $startRecord = $offset + 1;
    $endRecord = min($offset + $limit, $totalRecords);

    // Prepare response
    $response = [
        'success' => true,
        'employees' => $employees,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'start_record' => $startRecord,
            'end_record' => $endRecord,
            'per_page' => $limit
        ],
        'statistics' => [
            'total_employees' => $totalRecords,
            'active_employees' => $totalRecords // All employees returned are active
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Database Error in get_employees.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General Error in get_employees.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching employees',
        'error' => $e->getMessage()
    ]);
}
?>