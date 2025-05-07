<?php
require_once '../config/db_connect.php';

class WorkProgressMediaHandler {
    private $conn;
    private $uploadDir;
    
    public function __construct($conn) {
        $this->conn = $conn;
        // Define upload directory relative to the project root
        $this->uploadDir = dirname(dirname(__FILE__)) . '/uploads/work_progress/';
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0777, true)) {
                error_log("Failed to create upload directory: {$this->uploadDir}");
                throw new Exception("Could not create upload directory. Please check permissions.");
            }
            // Ensure directory is writable
            chmod($this->uploadDir, 0777);
        }
        
        // Verify directory is writable
        if (!is_writable($this->uploadDir)) {
            error_log("Upload directory not writable: {$this->uploadDir}");
            throw new Exception("Upload directory is not writable. Please check permissions.");
        }
        
        // Ensure the table exists
        $this->ensureMediaTableExists();
    }
    
    public function saveMedia($workProgressId, $file, $description = '') {
        try {
            error_log("Starting saveMedia for workProgressId: $workProgressId");
            
            // Check if required parameters are valid
            if (!isset($file['name']) || !isset($file['tmp_name'])) {
                throw new Exception("Invalid file data provided");
            }
            
            // Validate workProgressId
            if (!is_numeric($workProgressId) || $workProgressId <= 0) {
                throw new Exception("Invalid work progress ID: $workProgressId");
            }
            
            // Get file details
            $fileName = $file['name'];
            $fileTmpPath = $file['tmp_name'];
            $fileType = $file['type'];
            $fileSize = $file['size'];
            
            error_log("Processing file: $fileName, type: $fileType, size: $fileSize bytes");
            
            // Check if temporary file exists
            if (!file_exists($fileTmpPath)) {
                throw new Exception("Temporary file does not exist: $fileTmpPath");
            }
            
            // Check file size (up to 1GB)
            if ($fileSize > 1024 * 1024 * 1024) {
                throw new Exception("File size exceeds limit. Maximum allowed is 1GB.");
            }
            
            // Determine media type
            $mediaType = $this->getMediaType($fileType);
            if (!$mediaType) {
                throw new Exception("Invalid file type: $fileType. Only images and videos are allowed.");
            }
            
            // Generate unique filename
            $uniqueFileName = time() . '_' . uniqid() . '_' . $this->sanitizeFileName($fileName);
            
            // Full path for the file (server path)
            $uploadFilePath = $this->uploadDir . $uniqueFileName;
            
            // Relative path for database storage and URL access
            $relativeFilePath = 'uploads/work_progress/' . $uniqueFileName;
            
            error_log("Moving uploaded file to: $uploadFilePath");
            
            // Make sure upload directory exists and is writable
            if (!is_dir($this->uploadDir)) {
                if (!mkdir($this->uploadDir, 0777, true)) {
                    throw new Exception("Unable to create upload directory");
                }
            }
            
            if (!is_writable($this->uploadDir)) {
                throw new Exception("Upload directory is not writable");
            }
            
            // Move file to upload directory
            if (!move_uploaded_file($fileTmpPath, $uploadFilePath)) {
                $moveError = error_get_last();
                error_log("Failed to move uploaded file. PHP Error: " . json_encode($moveError));
                throw new Exception("Failed to move uploaded file. Please check permissions and PHP settings.");
            }
            
            // Verify the file was actually moved
            if (!file_exists($uploadFilePath)) {
                throw new Exception("File upload appears to have failed, the destination file does not exist.");
            }
            
            // Check for existing records with the same work_progress_id (for debugging)
            $checkQuery = $this->conn->query("SELECT COUNT(*) as count FROM work_progress_media WHERE work_progress_id = $workProgressId");
            $existingCount = $checkQuery->fetch_assoc()['count'];
            error_log("Found $existingCount existing media records for work_progress_id: $workProgressId");
            
            // Save to database
            try {
                // Escape values
                $workProgressId = intval($workProgressId);
                $mediaType = $this->conn->real_escape_string($mediaType);
                $relativeFilePath = $this->conn->real_escape_string($relativeFilePath);
                $description = $this->conn->real_escape_string($description);
                
                error_log("Executing database insert with values: " . 
                    "work_progress_id: $workProgressId, " . 
                    "media_type: $mediaType, " . 
                    "file_path: $relativeFilePath");
                
                $sql = "INSERT INTO work_progress_media 
                       (work_progress_id, media_type, file_path, description, created_at) 
                       VALUES 
                       ($workProgressId, '$mediaType', '$relativeFilePath', '$description', NOW())";
                
                if (!$this->conn->query($sql)) {
                    throw new Exception("Database error: " . $this->conn->error);
                }
                
                $mediaId = $this->conn->insert_id;
                error_log("Media saved successfully with ID: $mediaId");
                
                // Verify the record exists
                $verifyQuery = $this->conn->query("SELECT * FROM work_progress_media WHERE id = $mediaId");
                if ($verifyQuery && $verifyQuery->num_rows > 0) {
                    error_log("Verified record exists in database with ID: $mediaId");
                } else {
                    error_log("Warning: Could not verify record in database with ID: $mediaId");
                }
                
                return [
                    'success' => true,
                    'media_id' => $mediaId,
                    'file_path' => $relativeFilePath,
                    'media_type' => $mediaType
                ];
            } catch (Exception $e) {
                error_log("Exception in database insert: " . $e->getMessage());
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Error in saveMedia: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function getMediaType($fileType) {
        if (strpos($fileType, 'image/') === 0) {
            return 'image';
        } elseif (strpos($fileType, 'video/') === 0) {
            return 'video';
        }
        return false;
    }
    
    // Sanitize filename to prevent directory traversal attacks and ensure valid filenames
    private function sanitizeFileName($fileName) {
        // Remove special characters that are illegal in filenames
        $fileName = preg_replace('/[^\w\.-]+/', '_', $fileName);
        // Ensure the filename isn't too long
        if (strlen($fileName) > 255) {
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $fileName = substr($fileName, 0, 245) . '.' . $extension;
        }
        return $fileName;
    }
    
    // Ensure the work_progress_media table exists
    private function ensureMediaTableExists() {
        try {
            // Check if table exists
            $result = $this->conn->query("SHOW TABLES LIKE 'work_progress_media'");
            $tableExists = $result && $result->num_rows > 0;
            
            if (!$tableExists) {
                // Table doesn't exist, create it
                $sql = "CREATE TABLE work_progress_media (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    work_progress_id INT(11) NOT NULL,
                    media_type ENUM('image', 'video') NOT NULL,
                    file_path VARCHAR(255) NOT NULL,
                    description TEXT,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY idx_work_progress_id (work_progress_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                
                if (!$this->conn->query($sql)) {
                    throw new Exception("Failed to create work_progress_media table: " . $this->conn->error);
                }
                
                error_log("Created work_progress_media table");
            }
        } catch (Exception $e) {
            error_log("Error checking/creating work_progress_media table: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getMediaByWorkProgressId($workProgressId) {
        $workProgressId = intval($workProgressId);
        $result = $this->conn->query(
            "SELECT * FROM work_progress_media 
            WHERE work_progress_id = $workProgressId 
            ORDER BY created_at DESC"
        );
        
        $media = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $media[] = $row;
            }
        }
        
        return $media;
    }
    
    public function deleteMedia($mediaId) {
        try {
            $mediaId = intval($mediaId);
            
            // Get media details first
            $query = $this->conn->query("SELECT file_path FROM work_progress_media WHERE id = $mediaId");
            if ($query && $query->num_rows > 0) {
                $media = $query->fetch_assoc();
                
                // Get server file path from the relative path
                $filePath = dirname(dirname(__FILE__)) . '/' . $media['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Delete from database
                if (!$this->conn->query("DELETE FROM work_progress_media WHERE id = $mediaId")) {
                    throw new Exception("Failed to delete record: " . $this->conn->error);
                }
                
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Media not found'];
            
        } catch (Exception $e) {
            error_log("Error in deleteMedia: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
} 