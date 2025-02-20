<?php

class ProjectLogger {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    /**
     * Log a project activity
     */
    public function logActivity($data) {
        try {
            $query = "INSERT INTO project_activity_logs (
                project_id, 
                stage_id, 
                substage_id, 
                action_type, 
                old_value, 
                new_value, 
                performed_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param(
                "iiisssi",
                $data['project_id'],
                $data['stage_id'],
                $data['substage_id'],
                $data['action_type'],
                $data['old_value'],
                $data['new_value'],
                $data['performed_by']
            );
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error logging project activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get activity log for a project
     */
    public function getProjectLogs($project_id, $limit = 50, $offset = 0) {
        try {
            $query = "SELECT 
                        pal.*,
                        u.username as performed_by_name,
                        p.title as project_name,
                        ps.stage_number,
                        pss.title as substage_name
                    FROM project_activity_logs pal
                    LEFT JOIN users u ON u.id = pal.performed_by
                    LEFT JOIN projects p ON p.id = pal.project_id
                    LEFT JOIN project_stages ps ON ps.id = pal.stage_id
                    LEFT JOIN project_substages pss ON pss.id = pal.substage_id
                    WHERE pal.project_id = ?
                    ORDER BY pal.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iii", $project_id, $limit, $offset);
            $stmt->execute();
            
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching project logs: " . $e->getMessage());
            return [];
        }
    }
}

// Usage example:
function logProjectActivity($conn, $project_id, $action_type, $old_value = null, $new_value = null, $stage_id = null, $substage_id = null) {
    $logger = new ProjectLogger($conn);
    
    return $logger->logActivity([
        'project_id' => $project_id,
        'stage_id' => $stage_id,
        'substage_id' => $substage_id,
        'action_type' => $action_type,
        'old_value' => $old_value,
        'new_value' => $new_value,
        'performed_by' => $_SESSION['user_id']
    ]);
} 