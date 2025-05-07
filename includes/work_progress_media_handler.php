<?php
require_once '../config/db_connect.php';

class WorkProgressMediaHandler {
    private $pdo;
    private $uploadDir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
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
            
            // Create work_progress_media table if it doesn't exist
            $this->ensureMediaTableExists();
            
            // Check for existing records with the same work_progress_id (for debugging)
            $checkStmt = $this->pdo->prepare("SELECT COUNT(*) FROM work_progress_media WHERE work_progress_id = ?");
            $checkStmt->execute([$workProgressId]);
            $existingCount = $checkStmt->fetchColumn();
            error_log("Found $existingCount existing media records for work_progress_id: $workProgressId");
            
            // Save to database
            try {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO work_progress_media 
                    (work_progress_id, media_type, file_path, description, created_at) 
                    VALUES (?, ?, ?, ?, current_timestamp())"
                );
                
                error_log("Executing database insert with values: " . 
                    "work_progress_id: $workProgressId, " . 
                    "media_type: $mediaType, " . 
                    "file_path: $relativeFilePath");
                
                $stmt->execute([
                    $workProgressId,
                    $mediaType,
                    $relativeFilePath,
                    $description
                ]);
                
                $rowCount = $stmt->rowCount();
                error_log("Statement row count after execute: $rowCount");
                
                if ($rowCount === 0) {
                    error_log("Warning: INSERT statement didn't report any affected rows");
                    error_log("PDO error info: " . json_encode($stmt->errorInfo()));
                }
                
                $mediaId = $this->pdo->lastInsertId();
                error_log("Media saved successfully with ID: $mediaId");
                
                // Verify the record exists
                $verifyStmt = $this->pdo->prepare("SELECT * FROM work_progress_media WHERE id = ?");
                $verifyStmt->execute([$mediaId]);
                $record = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($record) {
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
            } catch (PDOException $e) {
                error_log("PDO Exception in database insert: " . $e->getMessage());
                error_log("SQL State: " . $e->getCode());
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
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE 'work_progress_media'");
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
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
                
                $this->pdo->exec($sql);
                error_log("Created work_progress_media table");
            }
        } catch (Exception $e) {
            error_log("Error checking/creating work_progress_media table: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getMediaByWorkProgressId($workProgressId) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM work_progress_media 
            WHERE work_progress_id = ? 
            ORDER BY created_at DESC"
        );
        $stmt->execute([$workProgressId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function deleteMedia($mediaId) {
        try {
            // Get media details first
            $stmt = $this->pdo->prepare("SELECT file_path FROM work_progress_media WHERE id = ?");
            $stmt->execute([$mediaId]);
            $media = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($media) {
                // Get server file path from the relative path
                $filePath = dirname(dirname(__FILE__)) . '/' . $media['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Delete from database
                $stmt = $this->pdo->prepare("DELETE FROM work_progress_media WHERE id = ?");
                $stmt->execute([$mediaId]);
                
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Media not found'];
            
        } catch (Exception $e) {
            error_log("Error in deleteMedia: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}