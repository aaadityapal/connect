<?php
/**
 * View Material and Bill Photos
 * This script displays photos associated with materials
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include necessary files
require_once __DIR__ . '/../includes/calendar_data_handler.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Material & Bill Photos Viewer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .search-form {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="number"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 200px;
        }
        button {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #2980b9;
        }
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .photo-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .photo-card:hover {
            transform: translateY(-5px);
        }
        .photo-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .photo-info {
            padding: 15px;
        }
        .photo-info h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .photo-meta {
            font-size: 0.9em;
            color: #666;
        }
        .location-info {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        .photo-type {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .type-material {
            background: #e1f5fe;
            color: #0288d1;
        }
        .type-bill {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        .map-link {
            display: inline-block;
            margin-top: 10px;
            color: #3498db;
            text-decoration: none;
        }
        .map-link:hover {
            text-decoration: underline;
        }
        .no-photos {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .timestamp {
            font-size: 0.85em;
            color: #888;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Material & Bill Photos Viewer</h1>

        <div class="search-form">
            <form method="GET">
                <div class="form-group">
                    <label for="material_id">Material ID:</label>
                    <input type="number" name="material_id" id="material_id" 
                           value="<?php echo isset($_GET['material_id']) ? htmlspecialchars($_GET['material_id']) : ''; ?>" 
                           required>
                </div>
                <button type="submit">View Photos</button>
            </form>
        </div>

        <?php
        if (isset($_GET['material_id'])) {
            $materialId = intval($_GET['material_id']);
            
            try {
                // Check if material exists
                $stmt = $conn->prepare("SELECT mtr.*, vr.vendor_name 
                                      FROM hr_supervisor_material_transaction_records mtr
                                      JOIN hr_supervisor_vendor_registry vr ON mtr.vendor_id = vr.vendor_id
                                      WHERE mtr.material_id = ?");
                $stmt->bind_param("i", $materialId);
                $stmt->execute();
                $materialResult = $stmt->get_result();
                
                if ($materialResult->num_rows === 0) {
                    echo '<div class="no-photos">';
                    echo '<h2>Material not found</h2>';
                    echo '<p>No material found with ID: ' . htmlspecialchars($materialId) . '</p>';
                    echo '</div>';
                } else {
                    $materialData = $materialResult->fetch_assoc();
                    
                    // Get photos
                    $stmt = $conn->prepare("SELECT * FROM hr_supervisor_material_photo_records 
                                          WHERE material_id = ? 
                                          ORDER BY created_at DESC");
                    $stmt->bind_param("i", $materialId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        echo '<h2>Photos for Material ID: ' . htmlspecialchars($materialId) . '</h2>';
                        echo '<p>Vendor: ' . htmlspecialchars($materialData['vendor_name']) . '</p>';
                        echo '<p>Material Remark: ' . htmlspecialchars($materialData['material_remark']) . '</p>';
                        
                        echo '<div class="photo-grid">';
                        while ($photo = $result->fetch_assoc()) {
                            echo '<div class="photo-card">';
                            
                            // Display photo
                            $photoUrl = '/' . $photo['photo_path'];
                            echo '<img src="' . htmlspecialchars($photoUrl) . '" alt="' . htmlspecialchars($photo['filename']) . '">';
                            
                            echo '<div class="photo-info">';
                            echo '<span class="photo-type type-' . htmlspecialchars($photo['type']) . '">' . 
                                 ucfirst(htmlspecialchars($photo['type'])) . '</span>';
                            echo '<h3>' . htmlspecialchars($photo['filename']) . '</h3>';
                            
                            echo '<div class="photo-meta">';
                            if ($photo['photo_size']) {
                                echo '<div>Size: ' . number_format($photo['photo_size'] / 1024, 2) . ' KB</div>';
                            }
                            
                            if ($photo['latitude'] && $photo['longitude']) {
                                echo '<div class="location-info">';
                                echo '<div>Location: ' . 
                                     number_format($photo['latitude'], 6) . ', ' . 
                                     number_format($photo['longitude'], 6) . '</div>';
                                if ($photo['location_accuracy']) {
                                    echo '<div>Accuracy: ' . number_format($photo['location_accuracy'], 1) . ' meters</div>';
                                }
                                if ($photo['location_address']) {
                                    echo '<div>Address: ' . htmlspecialchars($photo['location_address']) . '</div>';
                                }
                                echo '<a href="https://www.google.com/maps?q=' . $photo['latitude'] . ',' . $photo['longitude'] . '" 
                                      class="map-link" target="_blank">View on Map</a>';
                                echo '</div>';
                            }
                            
                            echo '<div class="timestamp">';
                            echo 'Uploaded: ' . date('Y-m-d H:i:s', strtotime($photo['uploaded_at']));
                            echo '</div>';
                            
                            echo '</div>'; // .photo-meta
                            echo '</div>'; // .photo-info
                            echo '</div>'; // .photo-card
                        }
                        echo '</div>'; // .photo-grid
                    } else {
                        echo '<div class="no-photos">';
                        echo '<h2>No Photos Found</h2>';
                        echo '<p>No photos have been uploaded for this material yet.</p>';
                        echo '</div>';
                    }
                }
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<h2>Error</h2>';
                echo '<p>Failed to retrieve photos: ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
        }
        ?>
    </div>

    <script>
        // Add image error handling
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('img');
            images.forEach(img => {
                img.onerror = function() {
                    this.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24"%3E%3Cpath fill="%23ccc" d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/%3E%3C/svg%3E';
                    this.style.padding = '50px';
                    this.style.background = '#f8f9fa';
                };
            });
        });
    </script>
</body>
</html>