<?php
// Database connection
require_once 'api/config/db_connect.php';

class ProjectMetrics {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getActiveProjectsMetrics() {
        try {
            $metrics = [
                'total_active' => 0,
                'pending' => 0,
                'due' => 0,
                'overdue' => 0
            ];

            // Get metrics based on actual table structure
            $query = "SELECT 
                        COUNT(*) as total_active,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE 
                            WHEN end_date = CURRENT_DATE 
                            AND status NOT IN ('completed', 'cancelled') 
                            AND deleted_at IS NULL 
                            THEN 1 ELSE 0 END) as due,
                        SUM(CASE 
                            WHEN end_date < CURRENT_DATE 
                            AND status NOT IN ('completed', 'cancelled') 
                            AND deleted_at IS NULL 
                            THEN 1 ELSE 0 END) as overdue
                    FROM projects 
                    WHERE deleted_at IS NULL 
                    AND status NOT IN ('completed', 'cancelled')";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $metrics = [
                    'total_active' => (int)$result['total_active'],
                    'pending' => (int)$result['pending'],
                    'due' => (int)$result['due'],
                    'overdue' => (int)$result['overdue']
                ];
            }

            // Get project details for each category
            $projectDetails = $this->getProjectDetails();

            return [
                'status' => 'success',
                'data' => [
                    'metrics' => $metrics,
                    'projects' => $projectDetails
                ]
            ];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    private function getProjectDetails() {
        try {
            $query = "SELECT 
                        p.id,
                        p.title,
                        p.project_type,
                        p.status,
                        p.start_date,
                        p.end_date,
                        DATEDIFF(p.end_date, CURRENT_DATE) as days_remaining
                    FROM projects p
                    WHERE p.deleted_at IS NULL 
                    AND p.status NOT IN ('completed', 'cancelled')
                    ORDER BY p.end_date ASC
                    LIMIT 10";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}

// Handle the API request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $database = new Database();
    $db = $database->getConnection();
    $projectMetrics = new ProjectMetrics($db);
    
    $result = $projectMetrics->getActiveProjectsMetrics();
    
    header('Content-Type: application/json');
    echo json_encode($result);
}
?> 