// includes/inventory_media_handler.php
<?php
require_once '../config/db_connect.php';

class InventoryMediaHandler {
    private $pdo;
    private $uploadDir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        // Define upload directory relative to the project root
        $this->uploadDir = dirname(dirname(__FILE__)) . '/uploads/inventory/';
        
        // Create directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }
    
    public function saveMedia($inventoryId, $file, $description = '') {
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
            
            // Full path for the file
            $uploadFilePath = $this->uploadDir . $uniqueFileName;
            
            // Move the file
            if (!move_uploaded_file($fileTmpPath, $uploadFilePath)) {
                throw new Exception('Failed to upload file.');
            }
            
            // Save to database
            $stmt = $this->pdo->prepare("
                INSERT INTO inventory_media 
                (inventory_id, media_type, file_path, description) 
                VALUES (?, ?, ?, ?)
            ");
            
            $filePath = 'uploads/inventory/' . $uniqueFileName;
            $stmt->execute([$inventoryId, $mediaType, $filePath, $description]);
            
            return [
                'success' => true,
                'file_path' => $filePath,
                'media_type' => $mediaType,
                'id' => $this->pdo->lastInsertId()
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
        } else {
            return null;
        }
    }
    
    public function deleteMedia($mediaId) {
        try {
            // Get the file path first
            $stmt = $this->pdo->prepare("SELECT file_path FROM inventory_media WHERE id = ?");
            $stmt->execute([$mediaId]);
            $media = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($media) {
                // Delete the file
                $filePath = dirname(dirname(__FILE__)) . '/' . $media['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Delete from database
                $stmt = $this->pdo->prepare("DELETE FROM inventory_media WHERE id = ?");
                $stmt->execute([$mediaId]);
                
                return [
                    'success' => true
                ];
            } else {
                throw new Exception('Media not found');
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}