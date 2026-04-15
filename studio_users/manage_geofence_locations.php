<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../config/db_connect.php';

// Only admins can access by default, or maybe checked via sidebar permissions if needed.
// But we'll follow general pattern for now.
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$username = $user['username'] ?? 'User';

$message = '';
$message_type = '';

if (isset($_POST['delete_location']) && isset($_POST['location_id'])) {
    $location_id = intval($_POST['location_id']);
    
    // Check if location is in use
    $check_query = "SELECT COUNT(*) as count FROM user_geofence_locations WHERE geofence_location_id = ?";
    $stmt = $pdo->prepare($check_query);
    $stmt->execute([$location_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['count'] > 0) {
        $message = "Cannot delete location because it is assigned to users.";
        $message_type = "error";
    } else {
        $delete_query = "DELETE FROM geofence_locations WHERE id = ?";
        $stmt = $pdo->prepare($delete_query);
        
        if ($stmt->execute([$location_id])) {
            $message = "Location deleted successfully.";
            $message_type = "success";
        } else {
            $message = "Error deleting location.";
            $message_type = "error";
        }
    }
}

if (isset($_POST['save_location'])) {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);
    $radius = intval($_POST['radius']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name) || empty($address) || $latitude == 0 || $longitude == 0 || $radius <= 0) {
        $message = "Please fill in all fields with valid values.";
        $message_type = "error";
    } else {
        if (isset($_POST['location_id']) && $_POST['location_id'] > 0) {
            $location_id = intval($_POST['location_id']);
            $query = "UPDATE geofence_locations SET 
                     name = ?, address = ?, latitude = ?, longitude = ?, radius = ?, is_active = ? 
                     WHERE id = ?";
            $stmt = $pdo->prepare($query);
            if ($stmt->execute([$name, $address, $latitude, $longitude, $radius, $is_active, $location_id])) {
                $message = "Location updated successfully.";
                $message_type = "success";
            } else {
                $message = "Error updating location.";
                $message_type = "error";
            }
        } else {
            $query = "INSERT INTO geofence_locations (name, address, latitude, longitude, radius, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            if ($stmt->execute([$name, $address, $latitude, $longitude, $radius, $is_active])) {
                $message = "Location added successfully.";
                $message_type = "success";
            } else {
                $message = "Error adding location.";
                $message_type = "error";
            }
        }
    }
}

$edit_location = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $location_id = intval($_GET['edit']);
    $query = "SELECT * FROM geofence_locations WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$location_id]);
    $edit_location = $stmt->fetch(PDO::FETCH_ASSOC);
}

$query = "SELECT * FROM geofence_locations ORDER BY name";
$stmt = $pdo->prepare($query);
$stmt->execute();
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Geofence Locations</title>
    <!-- Sidebar Requirements -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>window.SIDEBAR_BASE_PATH = '';</script>
    <script src="components/sidebar-loader.js" defer></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Modular CSS (Assuming they exist like in travel settings, otherwise fallback) -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="header.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <style>
        .page-container {
            padding: 2rem;
            max-width: 1200px;
        }
        .header-banner {
            background:#1e293b; padding: 30px; border-radius: 16px; margin-bottom: 24px; color: #fff;
        }
        .form-card, .table-card {
            background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 24px;
            border: 1px solid #f0f2f5;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block; margin-bottom: 6px; font-weight: 600; color: #475569; font-size: 0.9rem;
        }
        .form-control {
            width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Outfit', sans-serif;
            outline: none; transition: border-color 0.2s; box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .btn {
            padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-family: 'Outfit', sans-serif; font-size: 0.9rem; transition: all 0.2s;
        }
        .btn-primary { background: #6366f1; color: #fff; }
        .btn-primary:hover { background: #4f46e5; }
        .btn-secondary { background: #f1f5f9; color: #475569; text-decoration: none; display: inline-block; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .map-container { height: 350px; margin-bottom: 15px; border-radius: 10px; overflow: hidden; border: 1px solid #cbd5e1; z-index: 1;}
        #map { height: 100%; width: 100%; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 0.9rem; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        th { background: #f8fafc; color: #475569; font-weight: 600; }
        .status-badge { padding: 4px 8px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status-active { background: #dcfce7; color: #16a34a; }
        .status-inactive { background: #fee2e2; color: #dc2626; }
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .slider { background-color: #6366f1; }
        input:checked + .slider:before { transform: translateX(20px); }
    </style>
</head>
<body class="el-1">
    <div class="dashboard-container">
        <div id="sidebar-mount"></div>
        <main class="main-content">
            <header class="dh-nav-header">
                <div class="dh-nav-left" style="display:flex;align-items:center;gap:0.75rem;">
                    <button class="mobile-hamburger-btn" id="mobileMenuBtn">
                        <i data-lucide="menu" style="width:18px;height:18px;"></i>
                    </button>
                    <div class="dh-user-info">
                        <div class="dh-icon-orange"><i data-lucide="map-pin"></i></div>
                        <div class="dh-greeting">
                            <span class="dh-greeting-text">Settings</span>
                            <span class="dh-greeting-name">Geofence Locations</span>
                        </div>
                    </div>
                </div>
            </header>
            
            <div class="page-container">
                <header class="header-banner">
                    <h1 style="font-size: 24px; margin-bottom: 5px;">Geofence Locations</h1>
                    <p style="color: #94a3b8; font-size: 14px;">Define geofence zones for attendance tracking.</p>
                </header>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-card" id="locationForm">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                        <h3 style="margin:0; color:#1e293b; font-weight: 700;">
                            <?php echo $edit_location ? 'Edit Geofence Location' : 'Add New Geofence Location'; ?>
                        </h3>
                        <?php if ($edit_location): ?>
                            <a href="manage_geofence_locations.php" class="btn btn-primary" style="font-size: 0.8rem; padding: 8px 16px; display:inline-flex; align-items:center; gap:6px;">
                                <i class="fa-solid fa-plus"></i> Add New Location
                            </a>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="">
                        <?php if ($edit_location): ?>
                            <input type="hidden" name="location_id" value="<?php echo $edit_location['id']; ?>">
                        <?php endif; ?>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label>Location Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo $edit_location ? htmlspecialchars($edit_location['name']) : ''; ?>" required placeholder="e.g. Main Office">
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" name="address" class="form-control" value="<?php echo $edit_location ? htmlspecialchars($edit_location['address']) : ''; ?>" required placeholder="e.g. 123 Main St, City">
                            </div>
                        </div>

                        <div class="map-container">
                            <div id="map"></div>
                        </div>
                        <div style="font-family: monospace; font-size: 13px; color: #64748b; margin-bottom: 15px;">
                            Selected coordinates: <span id="selected-coords"><?php echo $edit_location ? $edit_location['latitude'] . ', ' . $edit_location['longitude'] : 'None selected'; ?></span>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label>Latitude</label>
                                <input type="text" id="latitude" name="latitude" class="form-control" value="<?php echo $edit_location ? $edit_location['latitude'] : ''; ?>" required readonly>
                            </div>
                            <div class="form-group">
                                <label>Longitude</label>
                                <input type="text" id="longitude" name="longitude" class="form-control" value="<?php echo $edit_location ? $edit_location['longitude'] : ''; ?>" required readonly>
                            </div>
                            <div class="form-group">
                                <label>Radius (meters)</label>
                                <input type="number" id="radius" name="radius" class="form-control" min="1" value="<?php echo $edit_location ? $edit_location['radius'] : '50'; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                            <label style="margin: 0;">Active Status:</label>
                            <label class="switch">
                                <input type="checkbox" name="is_active" <?php echo (!$edit_location || $edit_location['is_active']) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div style="margin-top: 25px; display: flex; gap: 10px;">
                            <button type="submit" name="save_location" class="btn btn-primary">
                                <i class="fa-solid fa-save"></i> <?php echo $edit_location ? 'Update Location' : 'Save Location'; ?>
                            </button>
                            <?php if ($edit_location): ?>
                                <a href="manage_geofence_locations.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="table-card">
                    <h3 style="margin-top:0; color:#1e293b; margin-bottom: 20px; font-weight: 700;">Existing Geofences</h3>
                    <?php if (empty($locations)): ?>
                        <div style="text-align: center; padding: 40px; color: #94a3b8;">
                            <i class="fa-solid fa-map-location-dot" style="font-size: 40px; opacity: 0.5; margin-bottom: 15px;"></i>
                            <p>No geofence locations found. Establish your first zone above.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Location Name</th>
                                        <th>Address</th>
                                        <th>Coords (Lat, Lng)</th>
                                        <th>Range</th>
                                        <th>Status</th>
                                        <th style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($locations as $loc): ?>
                                        <tr>
                                            <td style="font-weight:600; color:#1e293b;"><?php echo htmlspecialchars($loc['name']); ?></td>
                                            <td style="color:#64748b;"><?php echo htmlspecialchars($loc['address']); ?></td>
                                            <td style="font-family:monospace; font-size:12px;"><?php echo $loc['latitude'] . ', ' . $loc['longitude']; ?></td>
                                            <td><?php echo $loc['radius']; ?> m</td>
                                            <td>
                                                <span class="status-badge <?php echo $loc['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo $loc['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td style="text-align:right;">
                                                <div style="display:flex; justify-content:flex-end; gap:8px;">
                                                    <a href="manage_geofence_locations.php?edit=<?php echo $loc['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </a>
                                                    <form method="post" action="" onsubmit="return confirm('Delete this geofence?');" style="display:inline;">
                                                        <input type="hidden" name="location_id" value="<?php echo $loc['id']; ?>">
                                                        <button type="submit" name="delete_location" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.8rem;">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    <script>
        let map, marker, circle;
        
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
            
            // Wait a tick for sidebar loaders if any
            setTimeout(initMap, 100);
        });
        
        function initMap() {
            const defaultLat = <?php echo $edit_location ? $edit_location['latitude'] : '28.636941'; ?>;
            const defaultLng = <?php echo $edit_location ? $edit_location['longitude'] : '77.302690'; ?>;
            
            map = L.map('map').setView([defaultLat, defaultLng], 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 19
            }).addTo(map);
            
            if (defaultLat && defaultLng) {
                placeMarker([defaultLat, defaultLng]);
            }
            
            // Add Geocoder Search
            const geocoder = L.Control.geocoder({
                defaultMarkGeocode: false,
                placeholder: "Search for a location...",
            })
            .on('markgeocode', function(e) {
                const latlng = e.geocode.center;
                map.setView(latlng, 15);
                placeMarker(latlng);
                
                const addressField = document.querySelector('input[name="address"]');
                if (addressField && !addressField.value.trim()) {
                    addressField.value = e.geocode.name;
                }
            })
            .addTo(map);
            
            map.on('click', function(e) {
                placeMarker(e.latlng);
            });
            
            document.getElementById('radius').addEventListener('input', updateCircleRadius);
        }
        
        function placeMarker(location) {
            if (marker) map.removeLayer(marker);
            if (circle) map.removeLayer(circle);
            
            marker = L.marker(location, { draggable: true }).addTo(map);
            
            const radius = parseInt(document.getElementById('radius').value) || 50;
            circle = L.circle(location, {
                radius: radius,
                fillColor: '#6366f1',
                fillOpacity: 0.3,
                color: '#6366f1',
                weight: 2
            }).addTo(map);
            
            updateMarkerPosition();
            
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
