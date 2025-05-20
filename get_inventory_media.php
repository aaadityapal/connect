<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">You must be logged in to view media files.</div>';
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Check if inventory_id is provided
if (!isset($_GET['inventory_id']) || empty($_GET['inventory_id'])) {
    echo '<div class="alert alert-warning">Invalid inventory item.</div>';
    exit();
}

$inventory_id = intval($_GET['inventory_id']);

// Fetch media files for this inventory item
$media_query = "SELECT * FROM sv_inventory_media WHERE inventory_id = ? ORDER BY sequence_number, created_at";
$media_stmt = $pdo->prepare($media_query);
$media_stmt->execute([$inventory_id]);
$media_files = $media_stmt->fetchAll(PDO::FETCH_ASSOC);

// Quick debug check for file paths - can be removed after fixing
if (!empty($media_files) && isset($_GET['debug']) && $_GET['debug'] == 1) {
    echo '<div class="alert alert-info">Debugging File Paths:</div>';
    echo '<ul>';
    foreach ($media_files as $file) {
        $original_path = $file['file_path'];
        $full_server_path = $_SERVER['DOCUMENT_ROOT'] . $original_path;
        $fixed_path = 'uploads/' . ltrim($original_path, '/');
        $full_fixed_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $fixed_path;
        
        echo '<li>';
        echo 'File: ' . htmlspecialchars($file['file_name']) . '<br>';
        echo 'Original DB Path: ' . htmlspecialchars($original_path) . '<br>';
        echo 'Original Server Path: ' . htmlspecialchars($full_server_path) . ' - Exists: ' . (file_exists($full_server_path) ? 'Yes' : 'No') . '<br>';
        echo 'Fixed Path: ' . htmlspecialchars($fixed_path) . '<br>';
        echo 'Fixed Server Path: ' . htmlspecialchars($full_fixed_path) . ' - Exists: ' . (file_exists($full_fixed_path) ? 'Yes' : 'No') . '<br>';
        echo '</li>';
    }
    echo '</ul>';
}

// Fetch item details for reference
$item_query = "SELECT i.*, e.title as event_title 
              FROM sv_inventory_items i 
              JOIN sv_calendar_events e ON i.event_id = e.event_id 
              WHERE i.inventory_id = ?";
$item_stmt = $pdo->prepare($item_query);
$item_stmt->execute([$inventory_id]);
$item = $item_stmt->fetch(PDO::FETCH_ASSOC);

// Check if any media was found
if (empty($media_files)) {
    echo '<div class="col-12 text-center py-4">
            <div class="empty-media">
                <i class="fas fa-photo-video empty-icon"></i>
                <p>No media files have been attached to this inventory item.</p>
            </div>
          </div>';
    exit();
}

// Group media by type
$photos = [];
$bills = [];
$other = [];

foreach ($media_files as $file) {
    $file_extension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
    
    // Check file type
    if ($file['media_type'] === 'bill' || $file_extension === 'pdf') {
        $bills[] = $file;
    } else if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $photos[] = $file;
    } else {
        $other[] = $file;
    }
}

// Display item title
echo '<div class="col-12 mb-3">
        <h5 class="media-item-title">' . htmlspecialchars($item['material_type']) . ' - ' . ucfirst($item['inventory_type']) . '</h5>
        <p class="text-muted">' . htmlspecialchars($item['event_title']) . '</p>
      </div>';

// Display media files
echo '<div class="col-12 mb-4">
        <ul class="nav nav-tabs" id="mediaTypeTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="all-tab" data-toggle="tab" href="#all" role="tab">
                    All (' . count($media_files) . ')
                </a>
            </li>';
if (count($photos) > 0) {
    echo '<li class="nav-item">
            <a class="nav-link" id="photos-tab" data-toggle="tab" href="#photos" role="tab">
                Photos (' . count($photos) . ')
            </a>
          </li>';
}
if (count($bills) > 0) {
    echo '<li class="nav-item">
            <a class="nav-link" id="bills-tab" data-toggle="tab" href="#bills" role="tab">
                Bills (' . count($bills) . ')
            </a>
          </li>';
}
echo '</ul>
      </div>';

// Tab content
echo '<div class="col-12">
        <div class="tab-content" id="mediaTypeContent">';

// All media tab
echo '<div class="tab-pane fade show active" id="all" role="tabpanel">
        <div class="row">';
displayMediaFiles($media_files);
echo '</div>
     </div>';

// Photos tab
if (count($photos) > 0) {
    echo '<div class="tab-pane fade" id="photos" role="tabpanel">
            <div class="row">';
    displayMediaFiles($photos);
    echo '</div>
         </div>';
}

// Bills tab
if (count($bills) > 0) {
    echo '<div class="tab-pane fade" id="bills" role="tabpanel">
            <div class="row">';
    displayMediaFiles($bills);
    echo '</div>
         </div>';
}

echo '</div>
      </div>';

// Function to display media files
function displayMediaFiles($files) {
    foreach ($files as $file) {
        $file_extension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
        $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
        $is_pdf = $file_extension === 'pdf';
        $file_icon = $is_pdf ? 'file-pdf' : 'file';
        $media_type_class = $file['media_type'] === 'bill' ? 'media-type-bill' : 'media-type-photo';
        
        // Fix the file path to ensure it's a valid URL
        $file_path = $file['file_path'];
        // Check if the path doesn't start with http:// or https:// and doesn't have the base URL
        if (!preg_match('/^https?:\/\//', $file_path) && !file_exists($_SERVER['DOCUMENT_ROOT'] . $file_path)) {
            // Try to fix the path - first remove any leading slash
            $file_path = ltrim($file_path, '/');
            // Prepend the correct base directory path
            $file_path = 'uploads/' . $file_path;
        }
        
        echo '<div class="col-md-4 col-sm-6 mb-4 media-gallery-item">
                <div class="card h-100">
                    <div class="card-img-container">';
        
        if ($is_image) {
            echo '<a href="' . htmlspecialchars($file_path) . '" data-lightbox="media-gallery" data-title="' . htmlspecialchars($file['file_name']) . '">
                    <img src="' . htmlspecialchars($file_path) . '" class="card-img-top" alt="' . htmlspecialchars($file['file_name']) . '">
                  </a>';
        } else {
            echo '<a href="' . htmlspecialchars($file_path) . '" target="_blank">
                    <i class="fas fa-' . $file_icon . ' file-icon"></i>
                  </a>';
        }
        
        echo '<span class="media-type ' . $media_type_class . '">' . ucfirst($file['media_type']) . '</span>
             </div>
             
             <div class="card-body">
                <h6 class="card-title">' . htmlspecialchars($file['file_name']) . '</h6>
             </div>
             
             <div class="card-footer bg-white border-top-0">
                <div class="d-flex justify-content-between">
                    <small class="text-muted">' . formatFileSize($file['file_size']) . '</small>
                    <div class="btn-group">
                        <a href="' . htmlspecialchars($file_path) . '" class="btn btn-sm btn-outline-primary" ' . ($is_image ? 'data-lightbox="media-gallery-actions"' : 'target="_blank"') . '>
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="' . htmlspecialchars($file_path) . '" class="btn btn-sm btn-outline-secondary" download="' . htmlspecialchars($file['file_name']) . '">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                </div>
             </div>
            </div>
          </div>';
    }
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?> 