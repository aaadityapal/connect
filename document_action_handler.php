<?php
session_start();
require_once 'config.php';

class DocumentActionHandler {
    private $db;
    private $uploadDirectory = 'uploads/documents/';

    public function __construct($db) {
        $this->db = $db;
    }

    public function handleAction($action, $documentId, $documentType) {
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('Unauthorized access');
        }

        switch ($action) {
            case 'view':
                return $this->viewDocument($documentId, $documentType);
            case 'download':
                return $this->downloadDocument($documentId, $documentType);
            case 'delete':
                return $this->deleteDocument($documentId, $documentType);
            default:
                throw new Exception('Invalid action');
        }
    }

    private function getDocumentInfo($documentId, $documentType) {
        $table = ($documentType === 'official') ? 'official_documents' : 'personal_documents';
        
        $stmt = $this->db->prepare("
            SELECT stored_filename, original_filename, file_type 
            FROM $table 
            WHERE id = ? AND is_deleted = 0
        ");
        
        $stmt->bind_param('i', $documentId);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to fetch document information');
        }

        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception('Document not found');
        }

        return $result->fetch_assoc();
    }

    private function viewDocument($documentId, $documentType) {
        $docInfo = $this->getDocumentInfo($documentId, $documentType);
        $filePath = $this->uploadDirectory . $documentType . '/' . $docInfo['stored_filename'];

        if (!file_exists($filePath)) {
            throw new Exception('File not found');
        }

        // Set appropriate headers for viewing
        header('Content-Type: ' . $docInfo['file_type']);
        header('Content-Disposition: inline; filename="' . $docInfo['original_filename'] . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($filePath);
        exit;
    }

    private function downloadDocument($documentId, $documentType) {
        $docInfo = $this->getDocumentInfo($documentId, $documentType);
        $filePath = $this->uploadDirectory . $documentType . '/' . $docInfo['stored_filename'];

        if (!file_exists($filePath)) {
            throw new Exception('File not found');
        }

        // Set headers for download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $docInfo['original_filename'] . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($filePath);
        exit;
    }

    private function deleteDocument($documentId, $documentType) {
        $table = ($documentType === 'official') ? 'official_documents' : 'personal_documents';
        
        // Start transaction
        $this->db->begin_transaction();

        try {
            // Soft delete in database
            $stmt = $this->db->prepare("
                UPDATE $table 
                SET is_deleted = 1, 
                    last_modified = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            
            $stmt->bind_param('i', $documentId);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to delete document record');
            }

            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Document deleted successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}

// Handle incoming requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            throw new Exception('Database connection failed');
        }

        $handler = new DocumentActionHandler($db);

        $action = $_REQUEST['action'] ?? '';
        $documentId = $_REQUEST['id'] ?? '';
        $documentType = $_REQUEST['type'] ?? '';

        if (!$action || !$documentId || !$documentType) {
            throw new Exception('Missing required parameters');
        }

        $result = $handler->handleAction($action, $documentId, $documentType);

        // Only send JSON response for delete action
        if ($action === 'delete') {
            header('Content-Type: application/json');
            echo json_encode($result);
        }
        // View and download actions handle their own output

    } catch (Exception $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} 