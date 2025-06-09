<?php
// Include database connection
require_once 'config/db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Get request parameters
$category = isset($_GET['category']) ? $_GET['category'] : '';
$site = isset($_GET['site']) ? $_GET['site'] : 'all';

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'details' => []
];

try {
    // Handle different category requests
    switch ($category) {
        case 'sites':
            $response = getSitesDetails($pdo, $site);
            break;
        case 'managers':
            $response = getPersonnelDetails($pdo, $site, 'manager');
            break;
        case 'engineers':
            $response = getPersonnelDetails($pdo, $site, 'engineer');
            break;
        case 'supervisors':
            $response = getPersonnelDetails($pdo, $site, 'supervisor');
            break;
        case 'labor':
            $response = getLaborDetails($pdo, $site);
            break;
        default:
            $response['message'] = 'Invalid category requested';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error occurred';
    error_log("Construction details error: " . $e->getMessage());
}

// Return JSON response
echo json_encode($response);
exit;

/**
 * Get details for all construction sites or a specific site
 */
function getSitesDetails($pdo, $site) {
    $response = [
        'success' => true,
        'message' => '',
        'details' => []
    ];
    
    try {
        // Base query to get site information from sv_calendar_events
        $query = "SELECT 
                    title as name,
                    COUNT(DISTINCT event_id) as events_count,
                    MIN(event_date) as start_date
                FROM sv_calendar_events
                WHERE is_custom_title = 1
                AND event_date >= CURDATE()";
        
        // Add site filter if not "all"
        if ($site !== 'all') {
            $query .= " AND title = :site";
        }
        
        $query .= " GROUP BY title";
        
        $stmt = $pdo->prepare($query);
        
        // Bind parameters if needed
        if ($site !== 'all') {
            $stmt->bindParam(':site', $site, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no sites found
        if (empty($sites)) {
            $response['message'] = 'No active construction sites found';
            return $response;
        }
        
        // Process each site with mock data for now
        foreach ($sites as $site_data) {
            // For now, we'll use mock data for personnel counts and other details
            // In a real implementation, you would fetch this from appropriate tables
            $site_personnel = [
                'managers' => rand(1, 5),
                'engineers' => rand(2, 8),
                'supervisors' => rand(3, 10)
            ];
            
            $response['details'][] = [
                'name' => $site_data['name'],
                'location' => 'Site Location ' . $site_data['name'], // Mock data
                'start_date' => date('Y-m-d', strtotime($site_data['start_date'])),
                'status' => 'active',
                'completion' => rand(10, 95), // Mock data
                'managers' => $site_personnel['managers'],
                'engineers' => $site_personnel['engineers'],
                'supervisors' => $site_personnel['supervisors']
            ];
        }
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Error fetching site details';
        error_log("Site details error: " . $e->getMessage());
    }
    
    return $response;
}

/**
 * Get personnel details (managers, engineers, supervisors)
 */
function getPersonnelDetails($pdo, $site, $role) {
    $response = [
        'success' => true,
        'message' => '',
        'details' => []
    ];
    
    try {
        // In a real implementation, you would fetch this from users/employees table
        // For now, we'll generate mock data
        
        // Get site names from sv_calendar_events
        $sites_query = "SELECT DISTINCT title as name 
                        FROM sv_calendar_events 
                        WHERE is_custom_title = 1 
                        AND event_date >= CURDATE()";
        
        if ($site !== 'all') {
            $sites_query .= " AND title = :site";
        }
        
        $stmt = $pdo->prepare($sites_query);
        
        if ($site !== 'all') {
            $stmt->bindParam(':site', $site, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no sites found
        if (empty($sites)) {
            $response['message'] = 'No active construction sites found';
            return $response;
        }
        
        // Generate mock personnel for each site
        $names = [
            'John Smith', 'Jane Doe', 'Robert Johnson', 'Emily Wilson', 
            'Michael Brown', 'Sarah Davis', 'David Miller', 'Lisa Garcia',
            'James Wilson', 'Patricia Moore', 'Richard Taylor', 'Jennifer Anderson',
            'Charles Thomas', 'Margaret Jackson', 'Joseph White', 'Nancy Harris'
        ];
        
        foreach ($sites as $site_data) {
            // Generate 1-5 personnel per site
            $personnel_count = rand(1, 5);
            
            for ($i = 0; $i < $personnel_count; $i++) {
                $name = $names[array_rand($names)];
                $employee_id = 'EMP' . rand(1000, 9999);
                
                $response['details'][] = [
                    'name' => $name,
                    'employee_id' => $employee_id,
                    'site' => $site_data['name'],
                    'contact' => '+1-' . rand(100, 999) . '-' . rand(100, 999) . '-' . rand(1000, 9999),
                    'status' => (rand(0, 10) > 2) ? 'present' : 'absent', // 80% chance of being present
                    'experience' => rand(1, 15)
                ];
            }
        }
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Error fetching personnel details';
        error_log("Personnel details error: " . $e->getMessage());
    }
    
    return $response;
}

/**
 * Get labor details
 */
function getLaborDetails($pdo, $site) {
    $response = [
        'success' => true,
        'message' => '',
        'details' => []
    ];
    
    try {
        // Get site names from sv_calendar_events
        $sites_query = "SELECT DISTINCT title as name 
                        FROM sv_calendar_events 
                        WHERE is_custom_title = 1 
                        AND event_date >= CURDATE()";
        
        if ($site !== 'all') {
            $sites_query .= " AND title = :site";
        }
        
        $stmt = $pdo->prepare($sites_query);
        
        if ($site !== 'all') {
            $stmt->bindParam(':site', $site, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no sites found
        if (empty($sites)) {
            $response['message'] = 'No active construction sites found';
            return $response;
        }
        
        // Labor types
        $labor_types = ['General Labor', 'Skilled Labor', 'Electrician', 'Plumber', 'Carpenter', 'Mason'];
        
        // Generate mock labor data for each site
        foreach ($sites as $site_data) {
            // Generate 10-30 workers per site
            $worker_count = rand(10, 30);
            
            for ($i = 0; $i < $worker_count; $i++) {
                $worker_id = 'W' . rand(10000, 99999);
                $type = $labor_types[array_rand($labor_types)];
                
                $response['details'][] = [
                    'id' => $worker_id,
                    'name' => 'Worker ' . substr($worker_id, 1), // Use worker ID as part of name
                    'site' => $site_data['name'],
                    'type' => $type,
                    'status' => (rand(0, 10) > 2) ? 'present' : 'absent', // 80% chance of being present
                    'hours' => (rand(0, 10) > 2) ? rand(4, 9) : null // Hours for present workers
                ];
            }
        }
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Error fetching labor details';
        error_log("Labor details error: " . $e->getMessage());
    }
    
    return $response;
}
?> 