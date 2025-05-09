<?php
/**
 * Photo Viewer
 * This page allows users to view material and bill photos with their metadata
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
// Using an absolute path reference to avoid duplicate includes
require_once __DIR__ . '/includes/config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get parameters
$material_id = isset($_GET['material_id']) ? intval($_GET['material_id']) : 0;
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Page title
$page_title = "Photo Viewer";

// Get material photos based on parameters
$photos = [];
$vendor_name = '';
$material_info = '';
$site_name = '';

try {
    if ($material_id > 0) {
        // Get photos for a specific material
        $stmt = $conn->prepare("
            SELECT 
                p.photo_id,
                p.photo_type,
                p.photo_filename,
                p.photo_path,
                p.latitude,
                p.longitude,
                p.location_accuracy,
                p.location_address,
                p.location_timestamp,
                m.material_remark,
                m.material_amount,
                v.vendor_name,
                v.vendor_type,
                s.site_name,
                e.event_date
            FROM 
                hr_supervisor_material_photo_records p
            JOIN 
                hr_supervisor_material_transaction_records m ON p.material_id = m.material_id
            JOIN 
                hr_supervisor_vendor_registry v ON m.vendor_id = v.vendor_id
            JOIN 
                hr_supervisor_calendar_site_events e ON v.event_id = e.event_id
            JOIN 
                hr_supervisor_construction_sites s ON e.site_id = s.site_id
            WHERE 
                p.material_id = ?
            ORDER BY 
                p.photo_type, p.location_timestamp DESC
        ");
        
        $stmt->bind_param("i", $material_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $photos[] = $row;
            
            // Set additional info
            if (empty($vendor_name)) {
                $vendor_name = $row['vendor_name'];
                $material_info = $row['material_remark'];
                $site_name = $row['site_name'];
            }
        }
        
        $page_title = "Material Photos - " . $vendor_name;
    } elseif ($vendor_id > 0) {
        // Get photos for all materials from a specific vendor
        $stmt = $conn->prepare("
            SELECT 
                p.photo_id,
                p.photo_type,
                p.photo_filename,
                p.photo_path,
                p.latitude,
                p.longitude,
                p.location_accuracy,
                p.location_address,
                p.location_timestamp,
                m.material_id,
                m.material_remark,
                m.material_amount,
                v.vendor_name,
                v.vendor_type,
                s.site_name,
                e.event_date
            FROM 
                hr_supervisor_material_photo_records p
            JOIN 
                hr_supervisor_material_transaction_records m ON p.material_id = m.material_id
            JOIN 
                hr_supervisor_vendor_registry v ON m.vendor_id = v.vendor_id
            JOIN 
                hr_supervisor_calendar_site_events e ON v.event_id = e.event_id
            JOIN 
                hr_supervisor_construction_sites s ON e.site_id = s.site_id
            WHERE 
                v.vendor_id = ?
            ORDER BY 
                p.material_id, p.photo_type, p.location_timestamp DESC
        ");
        
        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $photos[] = $row;
            
            // Set additional info
            if (empty($vendor_name)) {
                $vendor_name = $row['vendor_name'];
                $site_name = $row['site_name'];
            }
        }
        
        $page_title = "Vendor Photos - " . $vendor_name;
    } elseif ($event_id > 0) {
        // Get photos for all vendors in an event
        $stmt = $conn->prepare("
            SELECT 
                p.photo_id,
                p.photo_type,
                p.photo_filename,
                p.photo_path,
                p.latitude,
                p.longitude,
                p.location_accuracy,
                p.location_address,
                p.location_timestamp,
                m.material_id,
                m.material_remark,
                m.material_amount,
                v.vendor_id,
                v.vendor_name,
                v.vendor_type,
                s.site_name,
                e.event_date
            FROM 
                hr_supervisor_material_photo_records p
            JOIN 
                hr_supervisor_material_transaction_records m ON p.material_id = m.material_id
            JOIN 
                hr_supervisor_vendor_registry v ON m.vendor_id = v.vendor_id
            JOIN 
                hr_supervisor_calendar_site_events e ON v.event_id = e.event_id
            JOIN 
                hr_supervisor_construction_sites s ON e.site_id = s.site_id
            WHERE 
                e.event_id = ?
            ORDER BY 
                v.vendor_name, m.material_id, p.photo_type, p.location_timestamp DESC
        ");
        
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $photos[] = $row;
            
            // Set additional info
            if (empty($site_name)) {
                $site_name = $row['site_name'];
            }
        }
        
        $page_title = "Site Photos - " . $site_name;
    } else {
        // No valid parameters provided - show recent photos
        $stmt = $conn->prepare("
            SELECT 
                p.photo_id,
                p.photo_type,
                p.photo_filename,
                p.photo_path,
                p.latitude,
                p.longitude,
                p.location_accuracy,
                p.location_address,
                p.location_timestamp,
                m.material_id,
                m.material_remark,
                m.material_amount,
                v.vendor_id,
                v.vendor_name,
                v.vendor_type,
                s.site_name,
                e.event_id,
                e.event_date
            FROM 
                hr_supervisor_material_photo_records p
            JOIN 
                hr_supervisor_material_transaction_records m ON p.material_id = m.material_id
            JOIN 
                hr_supervisor_vendor_registry v ON m.vendor_id = v.vendor_id
            JOIN 
                hr_supervisor_calendar_site_events e ON v.event_id = e.event_id
            JOIN 
                hr_supervisor_construction_sites s ON e.site_id = s.site_id
            ORDER BY 
                p.location_timestamp DESC
            LIMIT 20
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $photos[] = $row;
        }
        
        $page_title = "Recent Photos";
    }
} catch (Exception $e) {
    $error_message = "Error fetching photos: " . $e->getMessage();
}

// Group photos by type
$material_photos = [];
$bill_photos = [];

foreach ($photos as $photo) {
    if ($photo['photo_type'] === 'material') {
        $material_photos[] = $photo;
    } else {
        $bill_photos[] = $photo;
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="photo-viewer-header">
        <h1 class="page-title">
            <i class="fas fa-images"></i> <?php echo htmlspecialchars($page_title); ?>
        </h1>
        
        <?php if (!empty($vendor_name) || !empty($site_name)): ?>
        <div class="photo-meta-info">
            <?php if (!empty($site_name)): ?>
            <div class="meta-item">
                <i class="fas fa-map-marker-alt"></i> Site: <?php echo htmlspecialchars($site_name); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($vendor_name)): ?>
            <div class="meta-item">
                <i class="fas fa-user"></i> Vendor: <?php echo htmlspecialchars($vendor_name); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($material_info)): ?>
            <div class="meta-item">
                <i class="fas fa-clipboard"></i> Material Info: <?php echo htmlspecialchars($material_info); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="photo-nav-links">
            <a href="javascript:history.back()" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger mt-3">
        <?php echo $error_message; ?>
    </div>
    <?php endif; ?>
    
    <?php if (empty($photos)): ?>
    <div class="alert alert-info mt-3">
        <i class="fas fa-info-circle"></i> No photos found.
    </div>
    <?php else: ?>
    
    <!-- Photo Tabs -->
    <ul class="nav nav-tabs mt-4" id="photoTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="material-tab" data-bs-toggle="tab" data-bs-target="#material-photos" 
                type="button" role="tab" aria-controls="material-photos" aria-selected="true">
                <i class="fas fa-boxes"></i> Material Photos (<?php echo count($material_photos); ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="bill-tab" data-bs-toggle="tab" data-bs-target="#bill-photos" 
                type="button" role="tab" aria-controls="bill-photos" aria-selected="false">
                <i class="fas fa-file-invoice"></i> Bill Photos (<?php echo count($bill_photos); ?>)
            </button>
        </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content" id="photoTabsContent">
        <!-- Material Photos Tab -->
        <div class="tab-pane fade show active" id="material-photos" role="tabpanel" aria-labelledby="material-tab">
            <?php if (empty($material_photos)): ?>
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle"></i> No material photos found.
            </div>
            <?php else: ?>
            <div class="photo-grid mt-4">
                <?php foreach ($material_photos as $photo): ?>
                <div class="photo-card">
                    <div class="photo-card-inner">
                        <div class="photo-card-front">
                            <img src="get_photo.php?id=<?php echo $photo['photo_id']; ?>&type=material" 
                                 alt="<?php echo htmlspecialchars($photo['photo_filename']); ?>"
                                 class="photo-image">
                            <div class="photo-card-overlay">
                                <div class="photo-card-title">
                                    <i class="fas fa-image"></i> 
                                    <?php echo htmlspecialchars(basename($photo['photo_filename'])); ?>
                                </div>
                                <button class="photo-info-btn" title="Show photo details">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </div>
                        </div>
                        <div class="photo-card-back">
                            <div class="photo-details">
                                <h5 class="photo-detail-title">
                                    <i class="fas fa-image"></i> Photo Details
                                </h5>
                                <div class="photo-detail-content">
                                    <?php if (!empty($photo['vendor_name']) && empty($vendor_name)): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Vendor:</span>
                                        <span class="detail-value">
                                            <a href="view_photos.php?vendor_id=<?php echo $photo['vendor_id']; ?>">
                                                <?php echo htmlspecialchars($photo['vendor_name']); ?>
                                            </a>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($photo['material_remark']) && empty($material_info)): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Remark:</span>
                                        <span class="detail-value">
                                            <?php echo htmlspecialchars($photo['material_remark']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($photo['location_address'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Location:</span>
                                        <span class="detail-value">
                                            <?php echo htmlspecialchars($photo['location_address']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($photo['latitude']) && !empty($photo['longitude'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Coordinates:</span>
                                        <span class="detail-value">
                                            <a href="https://maps.google.com/?q=<?php echo $photo['latitude'] . ',' . $photo['longitude']; ?>" 
                                                target="_blank">
                                                View on Map <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($photo['location_timestamp'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Date:</span>
                                        <span class="detail-value">
                                            <?php echo date('M j, Y g:i A', strtotime($photo['location_timestamp'])); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Filename:</span>
                                        <span class="detail-value file-name">
                                            <?php echo htmlspecialchars(basename($photo['photo_filename'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <button class="photo-back-btn" title="Back to photo">
                                    <i class="fas fa-arrow-left"></i> Back
                                </button>
                                <a href="get_photo.php?id=<?php echo $photo['photo_id']; ?>&type=material" 
                                    download="<?php echo htmlspecialchars(basename($photo['photo_filename'])); ?>"
                                    class="photo-download-btn" title="Download photo">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Bill Photos Tab -->
        <div class="tab-pane fade" id="bill-photos" role="tabpanel" aria-labelledby="bill-tab">
            <?php if (empty($bill_photos)): ?>
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle"></i> No bill photos found.
            </div>
            <?php else: ?>
            <div class="photo-grid mt-4">
                <?php foreach ($bill_photos as $photo): ?>
                <div class="photo-card">
                    <div class="photo-card-inner">
                        <div class="photo-card-front">
                            <img src="get_photo.php?id=<?php echo $photo['photo_id']; ?>&type=bill" 
                                 alt="<?php echo htmlspecialchars($photo['photo_filename']); ?>"
                                 class="photo-image">
                            <div class="photo-card-overlay">
                                <div class="photo-card-title">
                                    <i class="fas fa-file-invoice"></i>
                                    <?php echo htmlspecialchars(basename($photo['photo_filename'])); ?>
                                </div>
                                <button class="photo-info-btn" title="Show photo details">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </div>
                        </div>
                        <div class="photo-card-back">
                            <div class="photo-details">
                                <h5 class="photo-detail-title">
                                    <i class="fas fa-file-invoice"></i> Bill Details
                                </h5>
                                <div class="photo-detail-content">
                                    <?php if (!empty($photo['vendor_name']) && empty($vendor_name)): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Vendor:</span>
                                        <span class="detail-value">
                                            <a href="view_photos.php?vendor_id=<?php echo $photo['vendor_id']; ?>">
                                                <?php echo htmlspecialchars($photo['vendor_name']); ?>
                                            </a>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($photo['material_amount'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Amount:</span>
                                        <span class="detail-value">
                                            ₹<?php echo number_format($photo['material_amount'], 2); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($photo['location_address'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Location:</span>
                                        <span class="detail-value">
                                            <?php echo htmlspecialchars($photo['location_address']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($photo['latitude']) && !empty($photo['longitude'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Coordinates:</span>
                                        <span class="detail-value">
                                            <a href="https://maps.google.com/?q=<?php echo $photo['latitude'] . ',' . $photo['longitude']; ?>" 
                                                target="_blank">
                                                View on Map <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($photo['location_timestamp'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Date:</span>
                                        <span class="detail-value">
                                            <?php echo date('M j, Y g:i A', strtotime($photo['location_timestamp'])); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Filename:</span>
                                        <span class="detail-value file-name">
                                            <?php echo htmlspecialchars(basename($photo['photo_filename'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <button class="photo-back-btn" title="Back to photo">
                                    <i class="fas fa-arrow-left"></i> Back
                                </button>
                                <a href="get_photo.php?id=<?php echo $photo['photo_id']; ?>&type=bill" 
                                    download="<?php echo htmlspecialchars(basename($photo['photo_filename'])); ?>"
                                    class="photo-download-btn" title="Download photo">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- CSS for photo viewer -->
<style>
    .photo-viewer-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .page-title {
        font-size: 1.8rem;
        color: #333;
        margin-bottom: 15px;
    }
    
    .page-title i {
        color: #3498db;
        margin-right: 10px;
    }
    
    .photo-meta-info {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .meta-item {
        display: flex;
        align-items: center;
        font-size: 0.95rem;
        color: #555;
        background: #f8f9fa;
        padding: 8px 12px;
        border-radius: 6px;
    }
    
    .meta-item i {
        color: #3498db;
        margin-right: 8px;
    }
    
    .photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .photo-card {
        background-color: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        height: 250px;
        perspective: 1000px;
    }
    
    .photo-card-inner {
        position: relative;
        width: 100%;
        height: 100%;
        text-align: center;
        transition: transform 0.6s;
        transform-style: preserve-3d;
    }
    
    .photo-card.flipped .photo-card-inner {
        transform: rotateY(180deg);
    }
    
    .photo-card-front, .photo-card-back {
        position: absolute;
        width: 100%;
        height: 100%;
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
    }
    
    .photo-card-front {
        background-color: #f8f9fa;
    }
    
    .photo-card-back {
        background-color: #fff;
        transform: rotateY(180deg);
        padding: 15px;
        text-align: left;
    }
    
    .photo-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .photo-card-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background-color: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .photo-card-title {
        font-size: 0.85rem;
        text-align: left;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 80%;
    }
    
    .photo-info-btn {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        padding: 5px;
        font-size: 1.1rem;
        opacity: 0.8;
        transition: opacity 0.2s;
    }
    
    .photo-info-btn:hover {
        opacity: 1;
    }
    
    .photo-details {
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .photo-detail-title {
        font-size: 1.1rem;
        margin-bottom: 15px;
        color: #333;
        display: flex;
        align-items: center;
    }
    
    .photo-detail-title i {
        margin-right: 8px;
        color: #3498db;
    }
    
    .photo-detail-content {
        flex: 1;
        overflow-y: auto;
    }
    
    .detail-item {
        margin-bottom: 10px;
    }
    
    .detail-label {
        font-weight: 600;
        color: #555;
        display: block;
        margin-bottom: 3px;
        font-size: 0.8rem;
    }
    
    .detail-value {
        color: #333;
        font-size: 0.9rem;
    }
    
    .detail-value a {
        color: #3498db;
        text-decoration: none;
    }
    
    .detail-value a:hover {
        text-decoration: underline;
    }
    
    .file-name {
        word-break: break-all;
    }
    
    .photo-back-btn, .photo-download-btn {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 0.85rem;
        margin-top: 15px;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        text-decoration: none;
        color: #333;
    }
    
    .photo-back-btn {
        margin-right: 10px;
    }
    
    .photo-back-btn:hover, .photo-download-btn:hover {
        background-color: #e9ecef;
    }
    
    .photo-back-btn i, .photo-download-btn i {
        margin-right: 5px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 576px) {
        .photo-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
        
        .photo-card {
            height: 180px;
        }
    }
</style>

<!-- JavaScript for photo viewer interaction -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Setup flip animation for photo cards
    const infoButtons = document.querySelectorAll('.photo-info-btn');
    const backButtons = document.querySelectorAll('.photo-back-btn');
    
    infoButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const card = this.closest('.photo-card');
            card.classList.add('flipped');
        });
    });
    
    backButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const card = this.closest('.photo-card');
            card.classList.remove('flipped');
        });
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 