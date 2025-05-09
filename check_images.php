<?php
/**
 * Image Checker Utility
 * This file provides a utility to check images stored in the database table
 * and verify their existence on the filesystem
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once 'includes/calendar_data_handler.php';

// Pagination settings
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filtering options
$photo_type = isset($_GET['photo_type']) ? $_GET['photo_type'] : '';
$material_id = isset($_GET['material_id']) ? intval($_GET['material_id']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query conditions
$conditions = [];
$params = [];
$types = '';

if (!empty($photo_type)) {
    $conditions[] = "photo_type = ?";
    $params[] = $photo_type;
    $types .= 's';
}

if ($material_id > 0) {
    $conditions[] = "material_id = ?";
    $params[] = $material_id;
    $types .= 'i';
}

if (!empty($date_from)) {
    $conditions[] = "created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
    $types .= 's';
}

if (!empty($date_to)) {
    $conditions[] = "created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
    $types .= 's';
}

// Construct the WHERE clause
$where_clause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : '';

// Count total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM hr_supervisor_material_photo_records $where_clause";
$total_records = 0;

if (!empty($params)) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_records = $row['total'];
    $stmt->close();
} else {
    $result = $conn->query($count_sql);
    $row = $result->fetch_assoc();
    $total_records = $row['total'];
}

$total_pages = ceil($total_records / $limit);

// Fetch records with pagination
$sql = "SELECT photo_id, material_id, photo_type, photo_filename, photo_path, created_at 
        FROM hr_supervisor_material_photo_records $where_clause 
        ORDER BY photo_id DESC LIMIT ?, ?";

$images = [];
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $params[] = $offset;
    $params[] = $limit;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $offset, $limit);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Check if file exists
    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $row['photo_path'];
    $file_exists = file_exists($filePath);
    
    // If not found at the exact path, check common upload folders
    if (!$file_exists) {
        $filename = basename($row['photo_path']);
        $common_paths = [
            $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/' . date('Y/m/d/') . $filename,
            $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/' . date('Y/m/') . $filename,
            $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/2025/08/' . $filename
        ];
        
        foreach ($common_paths as $path) {
            if (file_exists($path)) {
                $file_exists = true;
                $filePath = $path;
                break;
            }
        }
    }
    
    $row['file_exists'] = $file_exists;
    $row['file_path'] = $filePath;
    $row['file_size'] = $file_exists ? filesize($filePath) : 0;
    $images[] = $row;
}

$stmt->close();

// Delete image record if requested
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $photo_id = intval($_GET['delete']);
    $delete_sql = "DELETE FROM hr_supervisor_material_photo_records WHERE photo_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param('i', $photo_id);
    $delete_success = $stmt->execute();
    $stmt->close();
    
    // Redirect to remove the delete parameter from URL
    header("Location: " . $_SERVER['PHP_SELF'] . "?page=$page" . 
           (!empty($photo_type) ? "&photo_type=$photo_type" : "") . 
           ($material_id > 0 ? "&material_id=$material_id" : "") .
           (!empty($date_from) ? "&date_from=$date_from" : "") .
           (!empty($date_to) ? "&date_to=$date_to" : ""));
    exit;
}

// Display setup
$types_options = [
    'material' => 'Material Photos',
    'bill' => 'Bill Photos',
    'site' => 'Site Photos',
    'worker' => 'Worker Photos'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Checker Utility</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --primary: #3498db;
            --success: #2ecc71;
            --danger: #e74c3c;
            --warning: #f39c12;
            --light: #f8f9fa;
            --dark: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .filters {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        input, select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .table-container {
            overflow-x: auto;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            text-decoration: none;
            color: var(--primary);
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .pagination a:hover {
            background-color: #f5f5f5;
        }
        
        .pagination .active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .thumbnail {
            max-width: 100px;
            max-height: 60px;
            object-fit: cover;
            border-radius: 3px;
            cursor: pointer;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            overflow: auto;
        }
        
        .modal-content {
            margin: 5% auto;
            max-width: 800px;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }
        
        .modal-header {
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
        }
        
        .modal-body {
            padding: 20px;
            text-align: center;
        }
        
        .modal-img {
            max-width: 100%;
            max-height: 500px;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #333;
        }
        
        .info-row {
            display: flex;
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        
        .info-label {
            flex: 0 0 150px;
            font-weight: 600;
        }
        
        .info-value {
            flex: 1;
        }
        
        .stats {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stat-card {
            flex: 1;
            min-width: 200px;
            padding: 15px;
            border-radius: 5px;
            background-color: #f8f9fa;
            border-left: 4px solid var(--primary);
        }
        
        .stat-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }

        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
            }
            
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>Image Checker Utility</h1>
            <a href="site_supervisor_dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-title">Total Images</div>
                <div class="stat-value"><?php echo $total_records; ?></div>
            </div>
            <?php
            // Get stats for different image types
            $type_stats = [];
            $stats_sql = "SELECT photo_type, COUNT(*) as count FROM hr_supervisor_material_photo_records GROUP BY photo_type";
            $stats_result = $conn->query($stats_sql);
            while ($stat = $stats_result->fetch_assoc()) {
                $type_stats[$stat['photo_type']] = $stat['count'];
            }
            
            foreach ($types_options as $type_key => $type_name):
                $count = $type_stats[$type_key] ?? 0;
            ?>
            <div class="stat-card">
                <div class="stat-title"><?php echo $type_name; ?></div>
                <div class="stat-value"><?php echo $count; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="filters">
            <form action="" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="photo_type">Photo Type</label>
                    <select name="photo_type" id="photo_type">
                        <option value="">All Types</option>
                        <?php foreach ($types_options as $type_key => $type_name): ?>
                            <option value="<?php echo $type_key; ?>" <?php echo $photo_type === $type_key ? 'selected' : ''; ?>>
                                <?php echo $type_name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="material_id">Material ID</label>
                    <input type="number" id="material_id" name="material_id" value="<?php echo $material_id; ?>" placeholder="Enter Material ID">
                </div>
                
                <div class="form-group">
                    <label for="date_from">Date From</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">Date To</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-primary" style="margin-left: 10px;">
                        <i class="fas fa-sync"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Preview</th>
                        <th>ID</th>
                        <th>Material ID</th>
                        <th>Type</th>
                        <th>Filename</th>
                        <th>Created</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($images)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">No images found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($images as $image): ?>
                            <tr>
                                <td>
                                    <?php if ($image['file_exists']): ?>
                                        <img src="<?php echo str_replace($_SERVER['DOCUMENT_ROOT'], '', $image['file_path']); ?>" 
                                             alt="Thumbnail" class="thumbnail" onclick="openImageModal(this.src, <?php echo htmlspecialchars(json_encode($image), ENT_QUOTES, 'UTF-8'); ?>)">
                                    <?php else: ?>
                                        <i class="fas fa-image" style="font-size: 32px; color: #ccc;"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $image['photo_id']; ?></td>
                                <td><?php echo $image['material_id']; ?></td>
                                <td><?php echo $image['photo_type']; ?></td>
                                <td><?php echo $image['photo_filename']; ?></td>
                                <td><?php echo $image['created_at'] ?? 'N/A'; ?></td>
                                <td>
                                    <?php if ($image['file_exists']): ?>
                                        <span class="badge badge-success">File Exists (<?php echo formatFileSize($image['file_size']); ?>)</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">File Missing</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo $_SERVER['PHP_SELF'] . '?delete=' . $image['photo_id'] . '&page=' . $page . 
                                        (!empty($photo_type) ? "&photo_type=$photo_type" : "") . 
                                        ($material_id > 0 ? "&material_id=$material_id" : "") .
                                        (!empty($date_from) ? "&date_from=$date_from" : "") .
                                        (!empty($date_to) ? "&date_to=$date_to" : ""); ?>" 
                                       onclick="return confirm('Are you sure you want to delete this image record?')" 
                                       class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF'] . '?page=1' . 
                        (!empty($photo_type) ? "&photo_type=$photo_type" : "") . 
                        ($material_id > 0 ? "&material_id=$material_id" : "") .
                        (!empty($date_from) ? "&date_from=$date_from" : "") .
                        (!empty($date_to) ? "&date_to=$date_to" : ""); ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="<?php echo $_SERVER['PHP_SELF'] . '?page=' . ($page - 1) . 
                        (!empty($photo_type) ? "&photo_type=$photo_type" : "") . 
                        ($material_id > 0 ? "&material_id=$material_id" : "") .
                        (!empty($date_from) ? "&date_from=$date_from" : "") .
                        (!empty($date_to) ? "&date_to=$date_to" : ""); ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php
                // Calculate range of page numbers to display
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo $_SERVER['PHP_SELF'] . '?page=' . $i . 
                            (!empty($photo_type) ? "&photo_type=$photo_type" : "") . 
                            ($material_id > 0 ? "&material_id=$material_id" : "") .
                            (!empty($date_from) ? "&date_from=$date_from" : "") .
                            (!empty($date_to) ? "&date_to=$date_to" : ""); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF'] . '?page=' . ($page + 1) . 
                        (!empty($photo_type) ? "&photo_type=$photo_type" : "") . 
                        ($material_id > 0 ? "&material_id=$material_id" : "") .
                        (!empty($date_from) ? "&date_from=$date_from" : "") .
                        (!empty($date_to) ? "&date_to=$date_to" : ""); ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="<?php echo $_SERVER['PHP_SELF'] . '?page=' . $total_pages . 
                        (!empty($photo_type) ? "&photo_type=$photo_type" : "") . 
                        ($material_id > 0 ? "&material_id=$material_id" : "") .
                        (!empty($date_from) ? "&date_from=$date_from" : "") .
                        (!empty($date_to) ? "&date_to=$date_to" : ""); ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Image Details</h2>
                <span class="close" onclick="closeImageModal()">&times;</span>
            </div>
            <div class="modal-body">
                <img id="modalImage" src="" alt="Full size image" class="modal-img">
                <div id="imageDetails" style="margin-top: 20px; text-align: left;">
                    <!-- Image details will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Image modal functionality
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        const modalTitle = document.getElementById('modalTitle');
        const imageDetails = document.getElementById('imageDetails');
        
        function openImageModal(imgSrc, imageData) {
            modal.style.display = "block";
            modalImg.src = imgSrc;
            modalTitle.textContent = 'Image ID: ' + imageData.photo_id;
            
            // Create details HTML
            let detailsHtml = '';
            detailsHtml += createInfoRow('Material ID', imageData.material_id);
            detailsHtml += createInfoRow('Photo Type', imageData.photo_type);
            detailsHtml += createInfoRow('Filename', imageData.photo_filename);
            detailsHtml += createInfoRow('Database Path', imageData.photo_path);
            detailsHtml += createInfoRow('Actual File Path', imageData.file_path);
            detailsHtml += createInfoRow('File Size', formatFileSize(imageData.file_size));
            detailsHtml += createInfoRow('Created Date', imageData.created_at || 'N/A');
            
            imageDetails.innerHTML = detailsHtml;
        }
        
        function closeImageModal() {
            modal.style.display = "none";
        }
        
        function createInfoRow(label, value) {
            return `
                <div class="info-row">
                    <div class="info-label">${label}:</div>
                    <div class="info-value">${value}</div>
                </div>
            `;
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target == modal) {
                closeImageModal();
            }
        }
    </script>
</body>
</html>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?> 