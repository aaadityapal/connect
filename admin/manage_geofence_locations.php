<?php
/**
 * Geofence Locations Management
 * 
 * This page allows administrators to manage geofence locations for attendance tracking.
 */

// Include necessary files
require_once '../config/db_connect.php';
require_once '../includes/auth_check.php';
require_once '../includes/activity_logger.php';



// Process form submissions
$message = '';
$message_type = '';

// Delete location
if (isset($_POST['delete_location']) && isset($_POST['location_id'])) {
    $location_id = intval($_POST['location_id']);
    
    // Check if location is in use
    $check_query = "SELECT COUNT(*) as count FROM user_geofence_locations WHERE geofence_location_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $location_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $message = "Cannot delete location because it is assigned to users.";
        $message_type = "error";
    } else {
        $delete_query = "DELETE FROM geofence_locations WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $location_id);
        
        if ($stmt->execute()) {
            $message = "Location deleted successfully.";
            $message_type = "success";
            
            // Log activity
            logActivity($_SESSION['user_id'], 'Deleted geofence location ID: ' . $location_id);
        } else {
            $message = "Error deleting location: " . $conn->error;
            $message_type = "error";
        }
    }
}

// Add or update location
if (isset($_POST['save_location'])) {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);
    $radius = intval($_POST['radius']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    if (empty($name) || empty($address) || $latitude == 0 || $longitude == 0 || $radius <= 0) {
        $message = "Please fill in all fields with valid values.";
        $message_type = "error";
    } else {
        // Check if we're updating or adding
        if (isset($_POST['location_id']) && $_POST['location_id'] > 0) {
            // Update existing location
            $location_id = intval($_POST['location_id']);
            $query = "UPDATE geofence_locations SET 
                     name = ?, 
                     address = ?, 
                     latitude = ?, 
                     longitude = ?, 
                     radius = ?, 
                     is_active = ? 
                     WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssddiis", $name, $address, $latitude, $longitude, $radius, $is_active, $location_id);
            
            if ($stmt->execute()) {
                $message = "Location updated successfully.";
                $message_type = "success";
                
                // Log activity
                logActivity($_SESSION['user_id'], 'Updated geofence location ID: ' . $location_id);
            } else {
                $message = "Error updating location: " . $conn->error;
                $message_type = "error";
            }
        } else {
            // Add new location
            $query = "INSERT INTO geofence_locations (name, address, latitude, longitude, radius, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssddii", $name, $address, $latitude, $longitude, $radius, $is_active);
            
            if ($stmt->execute()) {
                $location_id = $conn->insert_id;
                $message = "Location added successfully.";
                $message_type = "success";
                
                // Log activity
                logActivity($_SESSION['user_id'], 'Added new geofence location ID: ' . $location_id);
            } else {
                $message = "Error adding location: " . $conn->error;
                $message_type = "error";
            }
        }
    }
}

// Get location for editing
$edit_location = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $location_id = intval($_GET['edit']);
    $query = "SELECT * FROM geofence_locations WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $location_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $edit_location = $result->fetch_assoc();
    }
}

// Get all locations
$query = "SELECT * FROM geofence_locations ORDER BY name";
$result = $conn->query($query);
$locations = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
}

// Page title
$page_title = "Manage Geofence Locations";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - HR System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    <style>
        .location-form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .location-form h3 {
            margin-top: 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-check {
            margin-top: 15px;
        }
        
        .btn-container {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        .locations-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .locations-table th, .locations-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .locations-table th {
            background-color: #f2f2f2;
        }
        
        .locations-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-secondary {
            background-color: #2196F3;
            color: white;
        }
        
        .btn-danger {
            background-color: #f44336;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        
        .alert-error {
            background-color: #f2dede;
            color: #a94442;
        }
        
        .map-container {
            height: 300px;
            margin-bottom: 15px;
        }
        
        #map {
            height: 100%;
            border-radius: 4px;
            border: 1px solid #ddd;
            z-index: 1; /* Ensure map controls work properly */
        }
        
        .coordinates-display {
            margin-top: 10px;
            font-family: monospace;
            font-size: 14px;
            color: #666;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-active {
            background-color: #4CAF50;
        }
        
        .status-inactive {
            background-color: #f44336;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1><?php echo $page_title; ?></h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="location-form">
            <h3><?php echo $edit_location ? 'Edit Location' : 'Add New Location'; ?></h3>
            
            <form method="post" action="">
                <?php if ($edit_location): ?>
                    <input type="hidden" name="location_id" value="<?php echo $edit_location['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Location Name:</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo $edit_location ? htmlspecialchars($edit_location['name']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea id="address" name="address" class="form-control" rows="3" required><?php echo $edit_location ? htmlspecialchars($edit_location['address']) : ''; ?></textarea>
                </div>
                
                <div class="map-container">
                    <label>Select Location on Map:</label>
                    <div id="map"></div>
                    <div class="coordinates-display">
                        Selected coordinates: <span id="selected-coords"><?php echo $edit_location ? $edit_location['latitude'] . ', ' . $edit_location['longitude'] : 'None selected'; ?></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="latitude">Latitude:</label>
                    <input type="text" id="latitude" name="latitude" class="form-control" value="<?php echo $edit_location ? $edit_location['latitude'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="longitude">Longitude:</label>
                    <input type="text" id="longitude" name="longitude" class="form-control" value="<?php echo $edit_location ? $edit_location['longitude'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="radius">Radius (meters):</label>
                    <input type="number" id="radius" name="radius" class="form-control" min="1" value="<?php echo $edit_location ? $edit_location['radius'] : '50'; ?>" required>
                </div>
                
                <div class="form-check">
                    <input type="checkbox" id="is_active" name="is_active" <?php echo (!$edit_location || $edit_location['is_active']) ? 'checked' : ''; ?>>
                    <label for="is_active">Active</label>
                </div>
                
                <div class="btn-container">
                    <button type="submit" name="save_location" class="btn btn-primary">
                        <?php echo $edit_location ? 'Update Location' : 'Add Location'; ?>
                    </button>
                    
                    <?php if ($edit_location): ?>
                        <a href="manage_geofence_locations.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <h2>Existing Locations</h2>
        
        <?php if (empty($locations)): ?>
            <p>No locations found. Add your first location above.</p>
        <?php else: ?>
            <table class="locations-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Coordinates</th>
                        <th>Radius</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $location): ?>
                        <tr>
                            <td><?php echo $location['id']; ?></td>
                            <td><?php echo htmlspecialchars($location['name']); ?></td>
                            <td><?php echo htmlspecialchars($location['address']); ?></td>
                            <td><?php echo $location['latitude'] . ', ' . $location['longitude']; ?></td>
                            <td><?php echo $location['radius']; ?> m</td>
                            <td>
                                <span class="status-indicator status-<?php echo $location['is_active'] ? 'active' : 'inactive'; ?>"></span>
                                <?php echo $location['is_active'] ? 'Active' : 'Inactive'; ?>
                            </td>
                            <td class="action-buttons">
                                <a href="manage_geofence_locations.php?edit=<?php echo $location['id']; ?>" class="btn btn-secondary">Edit</a>
                                
                                <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this location?');" style="display: inline;">
                                    <input type="hidden" name="location_id" value="<?php echo $location['id']; ?>">
                                    <button type="submit" name="delete_location" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
    <script>
        let map;
        let marker;
        let circle;
        
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
        });
        
        function initMap() {
            // Default location (can be customized)
            const defaultLat = <?php echo $edit_location ? $edit_location['latitude'] : '28.636941'; ?>;
            const defaultLng = <?php echo $edit_location ? $edit_location['longitude'] : '77.302690'; ?>;
            
            // Initialize map
            map = L.map('map').setView([defaultLat, defaultLng], 15);
            
            // Add OpenStreetMap tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Add initial marker if editing
            if (defaultLat && defaultLng) {
                placeMarker([defaultLat, defaultLng]);
            }
            
            // Click on map to set marker
            map.on('click', function(e) {
                placeMarker(e.latlng);
            });
            
            // Update radius when radius input changes
            document.getElementById('radius').addEventListener('input', function() {
                updateCircleRadius();
            });
        }
        
        function placeMarker(location) {
            // Remove existing marker and circle if any
            if (marker) {
                map.removeLayer(marker);
            }
            
            if (circle) {
                map.removeLayer(circle);
            }
            
            // Create new marker
            marker = L.marker(location, {
                draggable: true
            }).addTo(map);
            
            // Add circle to represent radius
            const radius = parseInt(document.getElementById('radius').value) || 50;
            circle = L.circle(location, {
                radius: radius,
                fillColor: '#4285F4',
                fillOpacity: 0.3,
                color: '#4285F4',
                weight: 1
            }).addTo(map);
            
            // Update form fields
            updateMarkerPosition();
            
            // Add event listener for marker drag
            marker.on('dragend', function() {
                updateMarkerPosition();
                updateCirclePosition();
            });
        }
        
        function updateMarkerPosition() {
            const position = marker.getLatLng();
            document.getElementById('latitude').value = position.lat.toFixed(7);
            document.getElementById('longitude').value = position.lng.toFixed(7);
            document.getElementById('selected-coords').textContent = position.lat.toFixed(7) + ', ' + position.lng.toFixed(7);
        }
        
        function updateCirclePosition() {
            if (marker && circle) {
                circle.setLatLng(marker.getLatLng());
            }
        }
        
        function updateCircleRadius() {
            if (circle) {
                const newRadius = parseInt(document.getElementById('radius').value) || 50;
                circle.setRadius(newRadius);
            }
        }
    </script>
</body>
</html> 