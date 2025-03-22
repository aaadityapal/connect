<?php
session_start();
require_once 'config.php';

class DocumentRetrievalHandler {
    private $db;
    private $uploadDirectory = 'uploads/documents/';

    public function __construct($db) {
        $this->db = $db;
    }

    public function getOfficialDocuments($userId = null) {
        $sql = "SELECT 
                od.*,
                u1.username as assigned_to,
                u1.designation as assigned_designation,
                u2.username as uploaded_by_name
            FROM official_documents od
            LEFT JOIN users u1 ON od.assigned_user_id = u1.id
            LEFT JOIN users u2 ON od.uploaded_by = u2.id
            WHERE od.is_deleted = 0";

        if ($userId) {
            $sql .= " AND od.assigned_user_id = ?";
        }

        $sql .= " ORDER BY od.upload_date DESC";

        $stmt = $this->db->prepare($sql);

        if ($userId) {
            $stmt->bind_param('i', $userId);
        }

        if (!$stmt->execute()) {
            throw new Exception('Failed to fetch official documents');
        }

        $result = $stmt->get_result();
        $documents = [];

        while ($row = $result->fetch_assoc()) {
            $documents[] = $this->formatDocumentData($row, 'official');
        }

        return $documents;
    }

    public function getPersonalDocuments($userId = null) {
        $sql = "SELECT 
                pd.*,
                u1.username as assigned_to,
                u1.designation as assigned_designation,
                u2.username as uploaded_by_name,
                u3.username as verified_by_name
            FROM personal_documents pd
            LEFT JOIN users u1 ON pd.assigned_user_id = u1.id
            LEFT JOIN users u2 ON pd.uploaded_by = u2.id
            LEFT JOIN users u3 ON pd.verified_by = u3.id
            WHERE pd.is_deleted = 0";

        if ($userId) {
            $sql .= " AND pd.assigned_user_id = ?";
        }

        $sql .= " ORDER BY pd.upload_date DESC";

        $stmt = $this->db->prepare($sql);

        if ($userId) {
            $stmt->bind_param('i', $userId);
        }

        if (!$stmt->execute()) {
            throw new Exception('Failed to fetch personal documents');
        }

        $result = $stmt->get_result();
        $documents = [];

        while ($row = $result->fetch_assoc()) {
            $documents[] = $this->formatDocumentData($row, 'personal');
        }

        return $documents;
    }

    private function formatDocumentData($row, $type) {
        $fileSize = $this->formatFileSize($row['file_size']);
        $documentType = $this->formatDocumentType($row['document_type']);
        $uploadDate = date('M d, Y H:i', strtotime($row['upload_date']));

        $formatted = [
            'id' => $row['id'],
            'document_name' => $row['document_name'],
            'document_type' => $documentType,
            'file_type' => $row['file_type'],
            'formatted_size' => $fileSize,
            'upload_date' => $uploadDate,
            'assigned_to' => $row['assigned_to'],
            'assigned_designation' => $row['assigned_designation'],
            'assigned_user_id' => $row['assigned_user_id'],
            'uploaded_by' => $row['uploaded_by_name'],
            'status' => $row['status'] ?? 'pending',
            'icon_class' => $this->getFileIconClass($row['file_type'])
        ];

        if ($type === 'personal') {
            $formatted['verification_status'] = $row['verification_status'];
            $formatted['verified_by'] = $row['verified_by_name'];
            $formatted['document_number'] = $row['document_number'];
            if ($row['expiry_date']) {
                $formatted['expiry_date'] = date('M d, Y', strtotime($row['expiry_date']));
            }
        }

        return $formatted;
    }

    private function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    private function formatDocumentType($type) {
        return ucwords(str_replace('_', ' ', $type));
    }

    private function getFileIconClass($fileType) {
        switch ($fileType) {
            case 'application/pdf':
                return 'fa-file-pdf';
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return 'fa-file-word';
            case 'image/jpeg':
            case 'image/png':
                return 'fa-file-image';
            default:
                return 'fa-file';
        }
    }
}

// Handle the retrieval request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }

    try {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            throw new Exception('Database connection failed');
        }

        $retriever = new DocumentRetrievalHandler($db);
        $documentType = $_GET['type'] ?? 'official'; // 'official' or 'personal'
        $userId = $_GET['user_id'] ?? null;

        if ($documentType === 'official') {
            $documents = $retriever->getOfficialDocuments($userId);
        } else {
            $documents = $retriever->getPersonalDocuments($userId);
        }

        echo json_encode([
            'success' => true,
            'documents' => $documents
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
} 