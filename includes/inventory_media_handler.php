// includes/inventory_media_handler.php
<?php
// Fix the path to work from both direct inclusion and when included from another file
$config_path = file_exists(__DIR__ . '/../config/db_connect.php') ? 
    __DIR__ . '/../config/db_connect.php' : 'config/db_connect.php';
require_once $config_path;

class InventoryMediaHandler {
    private $pdo;
    private $uploadDir;
    private $billsUploadDir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        // Define upload directory relative to the project root
        $this->uploadDir = dirname(dirname(__FILE__)) . '/uploads/inventory/';
        
        // Define bills upload directory
        $this->billsUploadDir = dirname(dirname(__FILE__)) . '/uploads/inventory_bills/';
        
        // Create directories if they don't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
        
        if (!is_dir($this->billsUploadDir)) {
            mkdir($this->billsUploadDir, 0777, true);
        }
    }
    
    public function saveMedia($inventoryId, $file, $description = '', $isBill = false) {
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
            $uniqueFileName = ($isBill ? 'file_' : '') . time() . '_' . uniqid() . '_' . $fileName;
            
            // Choose the appropriate upload directory based on whether this is a bill
            $uploadDirectory = $isBill ? $this->billsUploadDir : $this->uploadDir;
            
            // Full path for the file
            $uploadFilePath = $uploadDirectory . $uniqueFileName;
            
            // Move the file
            if (!move_uploaded_file($fileTmpPath, $uploadFilePath)) {
                throw new Exception('Failed to upload file.');
            }
            
            // Determine the relative file path for database storage
            $filePath = $isBill ? 'uploads/inventory_bills/' . $uniqueFileName : 'uploads/inventory/' . $uniqueFileName;
            
            // If this is a bill, also update the bill_picture field in event_inventory_items
            if ($isBill) {
                try {
                    $updateBillStmt = $this->pdo->prepare("
                        UPDATE event_inventory_items 
                        SET bill_picture = ? 
                        WHERE id = ?
                    ");
                    $updateBillStmt->execute([$filePath, $inventoryId]);
                } catch (Exception $e) {
                    // Log this error but continue
                    error_log('Failed to update bill_picture in event_inventory_items: ' . $e->getMessage());
                }
            }
            
            // Save to inventory_media table
            $stmt = $this->pdo->prepare("
                INSERT INTO inventory_media 
                (inventory_id, media_type, file_path, description) 
                VALUES (?, ?, ?, ?)
            ");
            
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
    
    // Function specifically for saving bill images
    public function saveBillImage($inventoryId, $file, $description = '') {
        return $this->saveMedia($inventoryId, $file, $description, true);
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