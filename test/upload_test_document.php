<?php
// Include header
$pageTitle = "Upload Test Document";
include_once '../includes/header.php';

// Check if form is submitted
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Connect to database
    require_once '../config/db_connect.php';
    
    // Get labour ID
    $labour_id = isset($_POST['labour_id']) ? intval($_POST['labour_id']) : 0;
    
    // Check if labour exists
    $checkQuery = "SELECT labour_id, full_name FROM hr_labours WHERE labour_id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$labour_id]);
    $labour = $checkStmt->fetch();
    
    if (!$labour) {
        $message = "Labour with ID $labour_id not found.";
        $messageType = "danger";
    } else {
        // Handle file upload
        $documentType = $_POST['document_type'];
        $uploadDir = '../uploads/labour_documents/';
        
        // Check if directory exists, if not create it
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Check if file was uploaded
        if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
            $file = $_FILES['document'];
            $fileName = $file['name'];
            $fileTmpName = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileError = $file['error'];
            $fileType = $file['type'];
            
            // Validate file
            $maxSize = 5 * 1024 * 1024; // 5MB
            $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $targetFilePath = $uploadDir . $newFileName;
            
            if ($fileSize > $maxSize) {
                $message = "File size exceeds the limit of 5MB.";
                $messageType = "danger";
            } elseif (!in_array($fileType, $allowedTypes)) {
                $message = "Invalid file type. Only JPG, PNG, and PDF are allowed.";
                $messageType = "danger";
            } elseif ($fileError !== UPLOAD_ERR_OK) {
                $message = "Error uploading file. Error code: $fileError";
                $messageType = "danger";
            } else {
                // Upload file
                if (move_uploaded_file($fileTmpName, $targetFilePath)) {
                    // Update database
                    $updateQuery = "UPDATE hr_labours SET $documentType = ? WHERE labour_id = ?";
                    $updateStmt = $pdo->prepare($updateQuery);
                    
                    if ($updateStmt->execute([$newFileName, $labour_id])) {
                        $message = "Document uploaded successfully for " . htmlspecialchars($labour['full_name']);
                        $messageType = "success";
                    } else {
                        $message = "Database update failed.";
                        $messageType = "danger";
                    }
                } else {
                    $message = "Failed to upload file.";
                    $messageType = "danger";
                }
            }
        } else {
            $message = "No file selected or upload error occurred.";
            $messageType = "danger";
        }
    }
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-upload me-2"></i> Upload Test Document</h2>
                <a href="view_labour_documents.php" class="btn btn-outline-primary">
                    <i class="fas fa-search me-2"></i> View Documents
                </a>
            </div>
            <hr>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-file-upload me-2"></i> Upload Document</h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="labour_id" class="form-label">Labour ID</label>
                            <input type="number" class="form-control" id="labour_id" name="labour_id" 
                                   placeholder="Enter labour ID" required min="1">
                        </div>
                        
                        <div class="mb-3">
                            <label for="document_type" class="form-label">Document Type</label>
                            <select class="form-select" id="document_type" name="document_type" required>
                                <option value="">Select Document Type</option>
                                <option value="aadhar_card">Aadhar Card</option>
                                <option value="pan_card">PAN Card</option>
                                <option value="voter_id">Voter ID / Driving License</option>
                                <option value="other_document">Other Document</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="document" class="form-label">Document File</label>
                            <input type="file" class="form-control" id="document" name="document" 
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-text">Supported formats: PDF, JPG, PNG (Max 5MB)</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i> Upload Document
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Instructions</h5>
                </div>
                <div class="card-body">
                    <ol>
                        <li>Enter the Labour ID for which you want to upload a document</li>
                        <li>Select the document type from the dropdown menu</li>
                        <li>Choose a file to upload (PDF, JPG, or PNG, max 5MB)</li>
                        <li>Click the "Upload Document" button</li>
                        <li>After uploading, you can view the document using the "View Documents" link</li>
                    </ol>
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb me-2"></i>
                        This is a test tool for uploading documents to existing labour records. The document will be stored in the database and can be viewed using the document viewer.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
