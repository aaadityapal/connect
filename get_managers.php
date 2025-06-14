<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log errors to file
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

try {
    // Include database connection
    include 'config/db_connect.php';

    // Set header to return JSON
    header('Content-Type: application/json');

    // Get filter parameters
    $role = isset($_GET['role']) ? $_GET['role'] : 'all';
    $month = isset($_GET['month']) ? intval($_GET['month']) : null;
    $year = isset($_GET['year']) ? intval($_GET['year']) : null;

    // Build date filter condition
    $dateFilter = "";
    if ($month !== null && $year !== null) {
        $dateFilter = " AND MONTH(project_date) = $month AND YEAR(project_date) = $year";
    }

    // Log parameters
    error_log("Role: $role, Month: $month, Year: $year, DateFilter: $dateFilter");
    error_log("DB Connection: " . (isset($conn) ? "Connected" : "Not Connected"));

    // Prepare the SQL query based on role filter
    if ($role == 'all') {
        $sql = "SELECT 
                    u.id, 
                    u.username, 
                    u.role, 
                    u.email, 
                    u.phone, 
                    u.profile_picture, 
                    u.status,
                    (SELECT COALESCE(SUM(amount), 0) FROM project_payouts 
                        WHERE project_type = 'architecture'$dateFilter) as total_architecture,
                    (SELECT COALESCE(SUM(amount), 0) FROM project_payouts 
                        WHERE project_type = 'interior'$dateFilter) as total_interior,
                    (SELECT COALESCE(SUM(amount), 0) FROM project_payouts 
                        WHERE project_type = 'construction'$dateFilter) as total_construction,
                    (SELECT COALESCE(SUM(remaining_amount), 0) FROM project_payouts 
                        WHERE manager_id = u.id$dateFilter) as remaining_amount,
                    (SELECT COALESCE(SUM(amount), 0) FROM manager_payments 
                        WHERE manager_id = u.id) as amount_paid
                FROM users u
                WHERE (u.role = 'Senior Manager (Studio)' OR u.role = 'Senior Manager (Site)')
                AND u.deleted_at IS NULL
                ORDER BY u.role, u.username";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } else {
        $sql = "SELECT 
                    u.id, 
                    u.username, 
                    u.role, 
                    u.email, 
                    u.phone, 
                    u.profile_picture, 
                    u.status,
                    (SELECT COALESCE(SUM(amount), 0) FROM project_payouts 
                        WHERE project_type = 'architecture'$dateFilter) as total_architecture,
                    (SELECT COALESCE(SUM(amount), 0) FROM project_payouts 
                        WHERE project_type = 'interior'$dateFilter) as total_interior,
                    (SELECT COALESCE(SUM(amount), 0) FROM project_payouts 
                        WHERE project_type = 'construction'$dateFilter) as total_construction,
                    (SELECT COALESCE(SUM(remaining_amount), 0) FROM project_payouts 
                        WHERE manager_id = u.id$dateFilter) as remaining_amount,
                    (SELECT COALESCE(SUM(amount), 0) FROM manager_payments 
                        WHERE manager_id = u.id) as amount_paid
                FROM users u
                WHERE u.role = ? 
                AND u.deleted_at IS NULL
                ORDER BY u.username";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $role);
        $stmt->execute();
    }

    // Execute the query
    $result = $stmt->get_result();
    
    // Fetch all managers
    $managers = array();
    while ($row = $result->fetch_assoc()) {
        // Format profile picture
        $profilePic = !empty($row['profile_picture']) ? $row['profile_picture'] : 'default.png';
        
        // Calculate payout percentages based on role
        $architectureCommission = 0.05; // 5% for all managers
        $interiorCommission = 0.05; // 5% for all managers
        $constructionCommission = 0.03; // 3% for all managers
        
        // Set fixed remuneration based on role
        $fixedRemuneration = $row['role'] === 'Senior Manager (Studio)' ? 28000 : 30000;
        
        // Calculate payout amounts
        $architecturePayout = $row['total_architecture'] * $architectureCommission;
        $interiorPayout = $row['total_interior'] * $interiorCommission;
        $constructionPayout = $row['role'] === 'Senior Manager (Site)' ? $row['total_construction'] * $constructionCommission : 0;
        
        // Calculate total commission and total payable
        $totalCommission = $architecturePayout + $interiorPayout + $constructionPayout;
        $totalPayable = $totalCommission + $fixedRemuneration;
        
        // Format payout amounts
        $formattedArchitecturePayout = number_format($architecturePayout, 2);
        $formattedInteriorPayout = number_format($interiorPayout, 2);
        $formattedConstructionPayout = number_format($constructionPayout, 2);
        $formattedFixedRemuneration = number_format($fixedRemuneration, 2);
        $formattedTotalCommission = number_format($totalCommission, 2);
        $formattedTotalPayable = number_format($totalPayable, 2);
        $formattedRemainingAmount = number_format($row['remaining_amount'], 2);
        $formattedAmountPaid = number_format($row['amount_paid'], 2);
        
        // Add to managers array
        $managers[] = array(
            'id' => $row['id'],
            'name' => $row['username'],
            'role' => $row['role'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'profile_picture' => $profilePic,
            'status' => $row['status'],
            'architecture_payout' => $formattedArchitecturePayout,
            'interior_payout' => $formattedInteriorPayout,
            'construction_payout' => $formattedConstructionPayout,
            'fixed_remuneration' => $formattedFixedRemuneration,
            'total_commission' => $formattedTotalCommission,
            'total_payable' => $formattedTotalPayable,
            'remaining_amount' => $formattedRemainingAmount,
            'amount_paid' => $formattedAmountPaid,
            'filtered_month' => $month,
            'filtered_year' => $year
        );
    }

    // Return as JSON
    echo json_encode($managers);
    
    // Close the statement and connection
    $stmt->close();

} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    echo json_encode([
        'error' => true,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    exit;
}
?> 