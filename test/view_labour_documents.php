<?php
// Include header
$pageTitle = "Labour Document Viewer";
include_once '../includes/header.php';

// Check if labour ID is provided
$labour_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no ID provided, show a form to enter ID
if (!$labour_id) {
?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-search me-2"></i> Find Labour Documents</h5>
                </div>
                <div class="card-body">
                    <form action="" method="GET">
                        <div class="mb-3">
                            <label for="labourId" class="form-label">Enter Labour ID</label>
                            <input type="number" class="form-control" id="labourId" name="id" 
                                   placeholder="Enter labour ID" required min="1">
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i> View Documents
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
} else {
    // Connect to database
    require_once '../config/db_connect.php';
    
    // Query to fetch labour details
    $query = "SELECT 
        labour_id, 
        full_name,
        position,
        position_custom,
        phone_number,
        join_date,
        labour_type,
        aadhar_card, 
        pan_card, 
        voter_id, 
        other_document 
    FROM hr_labours 
    WHERE labour_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$labour_id]);
    $labour = $stmt->fetch();
    
    if (!$labour) {
        // Labour not found
        ?>
        <div class="container mt-5">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Labour with ID <?= htmlspecialchars($labour_id) ?> not found.
            </div>
            <a href="view_labour_documents.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i> Back to Search
            </a>
        </div>
        <?php
    } else {
        // Display labour details and documents
        $position = !empty($labour['position_custom']) ? $labour['position_custom'] : $labour['position'];
        ?>
        <div class="container mt-5">
            <div class="row">
                <div class="col-md-12 mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2><i class="fas fa-id-card me-2"></i> Labour Documents</h2>
                        <a href="view_labour_documents.php" class="btn btn-outline-primary">
                            <i class="fas fa-search me-2"></i> Search Another
                        </a>
                    </div>
                    <hr>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i> Labour Details</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">ID:</th>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($labour['labour_id']) ?></span></td>
                                </tr>
                                <tr>
                                    <th>Name:</th>
                                    <td><?= htmlspecialchars($labour['full_name']) ?></td>
                                </tr>
                                <tr>
                                    <th>Position:</th>
                                    <td><?= htmlspecialchars($position) ?></td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td><?= htmlspecialchars($labour['phone_number']) ?></td>
                                </tr>
                                <tr>
                                    <th>Join Date:</th>
                                    <td><?= date('d M Y', strtotime($labour['join_date'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Type:</th>
                                    <td>
                                        <?php 
                                        $typeClass = '';
                                        switch($labour['labour_type']) {
                                            case 'permanent_labour':
                                                $typeClass = 'bg-success';
                                                $typeLabel = 'Permanent';
                                                break;
                                            case 'chowk_labour':
                                                $typeClass = 'bg-warning';
                                                $typeLabel = 'Chowk';
                                                break;
                                            case 'vendor_labour':
                                                $typeClass = 'bg-info';
                                                $typeLabel = 'Vendor';
                                                break;
                                            default:
                                                $typeClass = 'bg-secondary';
                                                $typeLabel = ucfirst($labour['labour_type']);
                                        }
                                        ?>
                                        <span class="badge <?= $typeClass ?>"><?= $typeLabel ?></span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i> Documents</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $hasDocuments = false;
                            $baseUrl = "/hr/uploads/labour_documents/";
                            $documents = [
                                'aadhar_card' => [
                                    'name' => 'Aadhar Card',
                                    'icon' => 'fa-id-card'
                                ],
                                'pan_card' => [
                                    'name' => 'PAN Card',
                                    'icon' => 'fa-id-badge'
                                ],
                                'voter_id' => [
                                    'name' => 'Voter ID / Driving License',
                                    'icon' => 'fa-address-card'
                                ],
                                'other_document' => [
                                    'name' => 'Other Document',
                                    'icon' => 'fa-file-alt'
                                ]
                            ];
                            
                            foreach ($documents as $key => $doc) {
                                if (!empty($labour[$key])) {
                                    $hasDocuments = true;
                                    $filename = $labour[$key];
                                    $filePath = "../uploads/labour_documents/" . $filename;
                                    $fileUrl = $baseUrl . $filename;
                                    
                                    // Determine file type
                                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                    $fileTypeClass = '';
                                    $fileTypeIcon = '';
                                    
                                    switch($extension) {
                                        case 'pdf':
                                            $fileTypeClass = 'bg-danger';
                                            $fileTypeIcon = 'fa-file-pdf';
                                            break;
                                        case 'jpg':
                                        case 'jpeg':
                                        case 'png':
                                            $fileTypeClass = 'bg-info';
                                            $fileTypeIcon = 'fa-file-image';
                                            break;
                                        default:
                                            $fileTypeClass = 'bg-secondary';
                                            $fileTypeIcon = 'fa-file';
                                    }
                                    
                                    // Check if file exists
                                    $fileExists = file_exists($filePath);
                                    ?>
                                    <div class="document-card mb-3">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col-md-1 text-center">
                                                        <span class="document-icon-wrapper <?= $fileTypeClass ?>">
                                                            <i class="fas <?= $fileTypeIcon ?>"></i>
                                                        </span>
                                                    </div>
                                                    <div class="col-md-7">
                                                        <h6 class="mb-1"><?= $doc['name'] ?></h6>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($filename) ?>
                                                            <?= $fileExists ? '' : '<span class="text-danger">(File missing)</span>' ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-md-4 text-end">
                                                        <?php if ($fileExists): ?>
                                                            <a href="<?= $fileUrl ?>" target="_blank" class="btn btn-sm btn-outline-primary me-2">
                                                                <i class="fas fa-eye me-1"></i> View
                                                            </a>
                                                            <a href="<?= $fileUrl ?>" download class="btn btn-sm btn-outline-secondary">
                                                                <i class="fas fa-download me-1"></i> Download
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">File Not Found</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            
                            if (!$hasDocuments) {
                                ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No documents have been uploaded for this labour.
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

// Include footer
include_once '../includes/footer.php';
?>

<style>
.document-icon-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: white;
    font-size: 1.2rem;
}

.document-card .card {
    transition: all 0.2s ease;
    border: 1px solid #e5e7eb;
}

.document-card .card:hover {
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    border-color: #d1d5db;
}
</style>
