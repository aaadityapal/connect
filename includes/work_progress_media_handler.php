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
            mkdir($this->uploadDir, 0777, true);
        }
    }
    
    public function saveMedia($workProgressId, $file, $description = '') {
        try {
            // Get file details
            $fileName = $file['name'];
            $fileTmpPath = $file['tmp_name'];
            $fileType = $file['type'];
            
            // Determine media type
            $mediaType = $this->getMediaType($fileType);
            if (!$mediaType) {
                throw new Exception('Invalid file type. Only images and videos are allowed.');
            }
            
            // Generate unique filename
            $uniqueFileName = time() . '_' . uniqid() . '_' . $fileName;
            
            // Full path for the file (server path)
            $uploadFilePath = $this->uploadDir . $uniqueFileName;
            
            // Relative path for database storage
            $relativeFilePath = 'uploads/work_progress/' . $uniqueFileName;
            
            // Move file to upload directory
            if (!move_uploaded_file($fileTmpPath, $uploadFilePath)) {
                throw new Exception('Failed to move uploaded file.');
            }
            
            // Save to database
            $stmt = $this->pdo->prepare(
                "INSERT INTO work_progress_media 
                (work_progress_id, media_type, file_path, description, created_at) 
                VALUES (?, ?, ?, ?, current_timestamp())"
            );
            
            $stmt->execute([
                $workProgressId,
                $mediaType,
                $relativeFilePath,
                $description
            ]);
            
            return [
                'success' => true,
                'media_id' => $this->pdo->lastInsertId(),
                'file_path' => $relativeFilePath,
                'media_type' => $mediaType
            ];
            
        } catch (Exception $e) {
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
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}