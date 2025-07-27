<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone_config.php';

// Include database connection
try {
    require_once 'config/db_connect.php';
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$error_message = '';
$result = null;
$users_result = null;
$total_pages = 1;

try {
    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Filters
    $user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $date_filter = isset($_GET['date']) ? $_GET['date'] : null;
    $action_filter = isset($_GET['action']) ? $_GET['action'] : null;

    // Build the query
    $where_conditions = [];
    $params = [];
    $param_types = '';

    if ($user_filter) {
        $where_conditions[] = "s.user_id = ?";
        $params[] = $user_filter;
        $param_types .= 'i';
    }

    if ($date_filter) {
        $where_conditions[] = "DATE(s.timestamp) = ?";
        $params[] = $date_filter;
        $param_types .= 's';
    }

    if ($action_filter) {
        $where_conditions[] = "s.action = ?";
        $params[] = $action_filter;
        $param_types .= 's';
    }

    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    // First check if the table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'site_in_out_logs'");
    if ($table_check->num_rows === 0) {
        throw new Exception("The site_in_out_logs table does not exist. Please run the database migration script.");
    }

    // Get total records for pagination
    $count_sql = "SELECT COUNT(*) as total FROM site_in_out_logs s $where_clause";
    $count_stmt = $conn->prepare($count_sql);
    if (!empty($params)) {
        $count_stmt->bind_param($param_types, ...$params);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $limit);

    // Get records
    $sql = "SELECT s.*, u.username as user_name, g.address
            FROM site_in_out_logs s 
            LEFT JOIN users u ON s.user_id = u.id 
            LEFT JOIN geofence_locations g ON s.geofence_location_id = g.id 
            $where_clause 
            ORDER BY s.timestamp DESC 
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $params[] = $limit;
        $params[] = $offset;
        $param_types .= 'ii';
        $stmt->bind_param($param_types, ...$params);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Get all users for filter dropdown
    $users_sql = "SELECT id, username FROM users ORDER BY username";
    $users_result = $conn->query($users_sql);
    
    if (!$users_result) {
        throw new Exception("Failed to fetch users: " . $conn->error);
    }

} catch (Exception $e) {
    $error_message = "An error occurred: " . $e->getMessage();
    error_log($error_message);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site In/Out Logs</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .action-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            white-space: nowrap;
        }
        .site-in {
            background-color: #d4edda;
            color: #155724;
        }
        .site-out {
            background-color: #f8d7da;
            color: #721c24;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            border: 1px solid #f5c6cb;
        }
        .map-icon {
            color: #007bff;
            cursor: pointer;
            margin-left: 5px;
            font-size: 1.2em;
        }
        .map-icon:hover {
            color: #0056b3;
        }
        .address-text {
            display: inline-block;
            max-width: 300px;
        }
        #mapModal .modal-dialog {
            max-width: 800px;
            margin: 10px;
        }
        #map {
            height: 400px;
            width: 100%;
            border-radius: 4px;
            background: #f8f9fa;
        }
        .map-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        
        /* New responsive styles */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 10px;
            }
            
            h2 {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }
            
            .table {
                font-size: 0.9rem;
            }
            
            .table td, .table th {
                padding: 0.5rem;
            }
            
            .address-text {
                max-width: 200px;
            }
            
            #map {
                height: 300px;
            }
            
            .btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.9rem;
            }
            
            .form-control {
                font-size: 0.9rem;
            }
            
            .pagination {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .pagination .page-link {
                padding: 0.4rem 0.75rem;
                margin: 2px;
            }
        }
        
        /* iPhone XR/XS specific styles */
        @media only screen 
        and (min-device-width: 375px) 
        and (max-device-width: 812px) 
        and (-webkit-min-device-pixel-ratio: 2) {
            .table-responsive {
                margin: -5px;  /* Compensate for increased cell padding */
            }
            
            .table td, .table th {
                padding: 8px 5px;
            }
            
            .action-badge {
                padding: 3px 8px;
                font-size: 0.8em;
            }
            
            .address-text {
                max-width: 150px;
                font-size: 0.9em;
            }
            
            .map-icon {
                padding: 5px;  /* Larger touch target */
            }
            
            small.text-muted {
                font-size: 0.75em;
                display: block;
                margin-top: 2px;
            }
            
            #mapModal .modal-dialog {
                margin: 0;
                max-width: 100%;
                height: 100%;
            }
            
            #mapModal .modal-content {
                border-radius: 0;
                min-height: 100%;
            }
            
            #map {
                height: calc(100vh - 150px);
            }
        }
        
        /* Responsive table improvements */
        @media (max-width: 576px) {
            .table-responsive {
                border: 0;
            }
            
            .table {
                border: 0;
            }
            
            .table thead {
                display: none;
            }
            
            .table tr {
                margin-bottom: 1rem;
                display: block;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fff;
            }
            
            .table td {
                display: block;
                text-align: right;
                padding: 0.5rem;
                position: relative;
                border-bottom: 1px solid #eee;
            }
            
            .table td:last-child {
                border-bottom: 0;
            }
            
            .table td::before {
                content: attr(data-label);
                float: left;
                font-weight: bold;
                text-transform: uppercase;
                font-size: 0.85em;
            }
            
            .table td:first-child {
                background: #f8f9fa;
                border-radius: 4px 4px 0 0;
            }
            
            .d-flex.align-items-center {
                justify-content: flex-end;
            }
            
            .filter-buttons {
                display: flex;
                gap: 5px;
                margin-top: 10px;
            }
            
            .filter-buttons .btn {
                flex: 1;
            }
        }

        /* Add styles for main content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 60px;
        }

        /* Adjust responsive styles */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 60px 15px 15px 15px; /* Added top padding for hamburger menu */
            }
            
            .container-fluid {
                padding: 0;
            }
            
            .mobile-toggle + .main-content {
                margin-left: 0;
            }
        }

        /* Adjust modal for side panel */
        @media (min-width: 769px) {
            #mapModal .modal-dialog {
                margin-left: 125px; /* Half of sidebar width */
            }
        }
    </style>
</head>
<body>
    <!-- Include Manager Panel -->
    <?php include 'includes/manager_panel.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2>Site In/Out Logs</h2>
            
            <?php if ($error_message): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <form class="mb-4 bg-light p-3 rounded">
                <div class="row">
                    <div class="col-12 col-md-3 mb-3">
                        <div class="form-group mb-md-0">
                            <label for="user_id">User</label>
                            <select name="user_id" id="user_id" class="form-control">
                                <option value="">All Users</option>
                                <?php if ($users_result): while ($user = $users_result->fetch_assoc()): ?>
                                    <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['username']) ?>
                                    </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-3 mb-3">
                        <div class="form-group mb-md-0">
                            <label for="date">Date</label>
                            <input type="date" name="date" id="date" class="form-control" value="<?= $date_filter ?>">
                        </div>
                    </div>
                    <div class="col-12 col-md-3 mb-3">
                        <div class="form-group mb-md-0">
                            <label for="action">Action</label>
                            <select name="action" id="action" class="form-control">
                                <option value="">All Actions</option>
                                <option value="site_in" <?= $action_filter === 'site_in' ? 'selected' : '' ?>>Site In</option>
                                <option value="site_out" <?= $action_filter === 'site_out' ? 'selected' : '' ?>>Site Out</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="form-group mb-0">
                            <label class="d-none d-md-block">&nbsp;</label>
                            <div class="filter-buttons">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="site_in_out_logs.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Logs Table -->
            <?php if ($result && $result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="thead-dark">
                        <tr>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Action Location</th>
                            <th>Geofence</th>
                            <th>Distance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="Date & Time"><?= formatTimestamp($row['timestamp']) ?></td>
                                <td data-label="User"><?= htmlspecialchars($row['user_name']) ?></td>
                                <td data-label="Action">
                                    <span class="action-badge <?= $row['action'] === 'site_in' ? 'site-in' : 'site-out' ?>">
                                        <?= ucwords(str_replace('_', ' ', $row['action'])) ?>
                                    </span>
                                </td>
                                <td data-label="Location">
                                    <div class="d-flex align-items-center">
                                        <span class="address-text text-truncate location-address" 
                                              data-lat="<?= $row['latitude'] ?>"
                                              data-lng="<?= $row['longitude'] ?>">
                                            <i class="fas fa-spinner fa-spin"></i> Loading address...
                                        </span>
                                        <i class="fas fa-map-marker-alt map-icon ml-2" 
                                           data-toggle="modal" 
                                           data-target="#mapModal"
                                           data-lat="<?= $row['latitude'] ?>"
                                           data-lng="<?= $row['longitude'] ?>">
                                        </i>
                                    </div>
                                    <small class="text-muted">
                                        Lat: <?= $row['latitude'] ?>, Long: <?= $row['longitude'] ?>
                                    </small>
                                </td>
                                <td data-label="Geofence"><?= htmlspecialchars($row['address'] ?: 'N/A') ?></td>
                                <td data-label="Distance">
                                    <?php if ($row['distance_from_geofence']): ?>
                                        <?= number_format($row['distance_from_geofence'] * 1000, 2) ?> m
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-info">No records found.</div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?><?= $user_filter ? "&user_id=$user_filter" : '' ?><?= $date_filter ? "&date=$date_filter" : '' ?><?= $action_filter ? "&action=$action_filter" : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>

            <!-- Add Map Modal -->
            <div class="modal fade" id="mapModal" tabindex="-1" role="dialog" aria-labelledby="mapModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="mapModalLabel">Location Map</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p id="mapAddress" class="mb-3"></p>
                            <div id="map">
                                <div class="map-loading">
                                    <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                                    <br>Loading map...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Add OpenStreetMap Leaflet CSS and JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

    <script>
    $(document).ready(function() {
        let map = null;
        let tileLayer = null;
        let currentMarker = null;
        let addressCache = new Map();
        let observer;
        let geocodingQueue = [];
        let isProcessingQueue = false;
        const GEOCODING_DELAY = 1000;

        // Pre-initialize map components
        function initializeMapComponents() {
            if (!map) {
                map = L.map('map', {
                    minZoom: 4,
                    maxZoom: 18,
                    zoomControl: true,
                    fadeAnimation: false, // Disable animations for better performance
                    markerZoomAnimation: false
                });

                // Use Mapbox tiles for better performance (you'll need to sign up for a free API key)
                // If you don't want to use Mapbox, keep the OpenStreetMap tiles but add retina support
                tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    crossOrigin: true,
                    updateWhenIdle: true,
                    keepBuffer: 2,
                    maxNativeZoom: 18,
                    maxZoom: 18
                });

                map.addLayer(tileLayer);
            }
        }

        // Initialize Intersection Observer for address loading
        function initObserver() {
            observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        loadAddress(element);
                        observer.unobserve(element);
                    }
                });
            }, {
                root: null,
                rootMargin: '50px',
                threshold: 0.1
            });

            document.querySelectorAll('.location-address').forEach(element => {
                observer.observe(element);
            });
        }

        // Process geocoding queue
        async function processQueue() {
            if (isProcessingQueue || geocodingQueue.length === 0) return;

            isProcessingQueue = true;
            const element = geocodingQueue.shift();
            
            try {
                await loadAddress(element);
            } catch (error) {
                console.error('Geocoding error:', error);
            }

            setTimeout(() => {
                isProcessingQueue = false;
                processQueue();
            }, GEOCODING_DELAY);
        }

        // Load address for an element
        async function loadAddress(element) {
            const lat = element.dataset.lat;
            const lng = element.dataset.lng;
            const cacheKey = `${lat},${lng}`;

            if (addressCache.has(cacheKey)) {
                element.textContent = addressCache.get(cacheKey);
                element.title = addressCache.get(cacheKey);
                return;
            }

            try {
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,
                    {
                        headers: {
                            'Accept': 'application/json',
                            'User-Agent': 'HR Attendance System'
                        }
                    }
                );

                if (!response.ok) throw new Error('Network response was not ok');

                const data = await response.json();
                const address = data.display_name || 'Address not available';
                
                addressCache.set(cacheKey, address);
                element.textContent = address;
                element.title = address;
                
                const mapIcon = element.nextElementSibling;
                if (mapIcon) {
                    mapIcon.dataset.address = address;
                }
            } catch (error) {
                console.error('Error fetching address:', error);
                element.textContent = 'Address not available';
            }
        }

        // Pre-initialize map when page loads
        initializeMapComponents();

        // Handle modal events
        $('#mapModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const lat = parseFloat(button.data('lat'));
            const lng = parseFloat(button.data('lng'));
            const address = button.data('address') || 'Location';
            
            $('#mapAddress').text(address);

            // Remove previous marker if exists
            if (currentMarker) {
                map.removeLayer(currentMarker);
            }

            // Set view and add marker
            requestAnimationFrame(() => {
                map.invalidateSize();
                map.setView([lat, lng], 16, { animate: false });
                
                currentMarker = L.marker([lat, lng], {
                    title: address,
                    alt: address
                }).addTo(map);

                currentMarker.bindPopup(address).openPopup();
            });
        });

        $('#mapModal').on('hidden.bs.modal', function () {
            if (currentMarker) {
                map.removeLayer(currentMarker);
                currentMarker = null;
            }
        });

        // Initialize the observer
        initObserver();

        // Add listener for sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const leftPanel = document.getElementById('leftPanel');
            const mainContent = document.querySelector('.main-content');
            
            // Update map size when sidebar is toggled
            const updateMapSize = () => {
                if (map) {
                    setTimeout(() => {
                        map.invalidateSize();
                    }, 300); // Wait for transition to complete
                }
            };

            // Listen for sidebar toggle
            if (leftPanel) {
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                            updateMapSize();
                        }
                    });
                });

                observer.observe(leftPanel, {
                    attributes: true
                });
            }

            // Handle window resize
            window.addEventListener('resize', updateMapSize);
        });
    });
    </script>
</body>
</html> 