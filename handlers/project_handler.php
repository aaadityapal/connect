<?php
require_once '../includes/config.php';

class ProjectHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function createProject($data) {
        try {
            $this->pdo->beginTransaction();
            
            // Insert project
            $sql = "INSERT INTO projects (
                title, description, project_type, category_id, 
                start_date, end_date, created_by, assigned_to, 
                status, created_at
            ) VALUES (
                :title, :description, :project_type, :category_id,
                :start_date, :end_date, :created_by, :assigned_to,
                'active', NOW()
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':title' => $data['projectTitle'],
                ':description' => $data['projectDescription'],
                ':project_type' => $data['projectType'],
                ':category_id' => $data['projectCategory'],
                ':start_date' => $data['startDate'],
                ':end_date' => $data['dueDate'],
                ':created_by' => $_SESSION['user_id'], // Assuming you have user session
                ':assigned_to' => $data['assignTo']
            ]);
            
            $projectId = $this->pdo->lastInsertId();
            
            // Insert stages
            if (!empty($data['stages'])) {
                foreach ($data['stages'] as $stageNum => $stage) {
                    $stageId = $this->createStage($projectId, $stageNum, $stage);
                    
                    // Insert substages if any
                    if (!empty($stage['substages'])) {
                        foreach ($stage['substages'] as $substageNum => $substage) {
                            $this->createSubstage($stageId, $substageNum, $substage);
                        }
                    }
                }
            }
            
            // Log project creation in activity log
            $this->logActivity($projectId, null, null, 'create', 'Project created');
            
            // Add to project history
            $this->addToHistory($projectId, 'create', null, 'Project created');
            
            $this->pdo->commit();
            return ['success' => true, 'project_id' => $projectId];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function createStage($projectId, $stageNum, $stageData) {
        $sql = "INSERT INTO project_stages (
            project_id, stage_number, assigned_to,
            start_date, end_date, status, created_at
        ) VALUES (
            :project_id, :stage_number, :assigned_to,
            :start_date, :end_date, 'pending', NOW()
        )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':project_id' => $projectId,
            ':stage_number' => $stageNum + 1,
            ':assigned_to' => $stageData['assignTo'],
            ':start_date' => $stageData['startDate'],
            ':end_date' => $stageData['dueDate']
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    private function createSubstage($stageId, $substageNum, $substageData) {
        $sql = "INSERT INTO project_substages (
            stage_id, substage_number, title,
            assigned_to, start_date, end_date,
            status, created_at, substage_identifier
        ) VALUES (
            :stage_id, :substage_number, :title,
            :assigned_to, :start_date, :end_date,
            'pending', NOW(), :identifier
        )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':stage_id' => $stageId,
            ':substage_number' => $substageNum + 1,
            ':title' => $substageData['title'],
            ':assigned_to' => $substageData['assignTo'],
            ':start_date' => $substageData['startDate'],
            ':end_date' => $substageData['dueDate'],
            ':identifier' => uniqid('sub_')
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    private function logActivity($projectId, $stageId, $substageId, $type, $description) {
        $sql = "INSERT INTO project_activity_log (
            project_id, stage_id, substage_id,
            activity_type, description, performed_by,
            performed_at
        ) VALUES (
            :project_id, :stage_id, :substage_id,
            :type, :description, :performed_by,
            NOW()
        )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':project_id' => $projectId,
            ':stage_id' => $stageId,
            ':substage_id' => $substageId,
            ':type' => $type,
            ':description' => $description,
            ':performed_by' => $_SESSION['user_id']
        ]);
    }
    
    private function addToHistory($projectId, $actionType, $oldValue, $newValue) {
        $sql = "INSERT INTO project_history (
            project_id, action_type, old_value,
            new_value, changed_by, changed_at
        ) VALUES (
            :project_id, :action_type, :old_value,
            :new_value, :changed_by, NOW()
        )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':project_id' => $projectId,
            ':action_type' => $actionType,
            ':old_value' => $oldValue,
            ':new_value' => $newValue,
            ':changed_by' => $_SESSION['user_id']
        ]);
    }
} 