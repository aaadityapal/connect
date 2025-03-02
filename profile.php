<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch current user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user role for conditional display
$user_role = $_SESSION['role'] ?? 'employee';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        /* Add these new styles */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .left-panel {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            background: #f5f7fa;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .breadcrumb a {
            color: #0084ff;
            text-decoration: none;
        }

        .breadcrumb i {
            margin: 0 10px;
            color: #7f8c8d;
        }

        /* Profile Tabs */
        .profile-tabs {
            display: flex;
            margin-bottom: 20px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .profile-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .profile-tab.active {
            background: #0084ff;
            color: white;
        }

        .profile-tab:hover:not(.active) {
            background: #f0f2f5;
        }

        /* Profile sections */
        .profile-section {
            display: none;
        }

        .profile-section.active {
            display: block;
        }

        /* Activity Log styles */
        .activity-log {
            margin-top: 20px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e1f5fe;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .activity-icon i {
            color: #0084ff;
        }

        .activity-details {
            flex: 1;
        }

        .activity-time {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        /* Notification preferences */
        .notification-preferences {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .preference-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .preference-item:last-child {
            border-bottom: none;
        }

        /* Toggle switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #0084ff;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* HR Documents styles */
        .hr-documents {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .documents-container {
            margin-top: 20px;
        }

        .document-item {
            display: flex;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .document-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .document-icon {
            width: 50px;
            height: 50px;
            background: #e1f5fe;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
        }

        .document-icon i {
            font-size: 24px;
            color: #0084ff;
        }

        .document-details {
            flex: 1;
        }

        .document-details h3 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }

        .document-details p {
            margin: 0 0 10px 0;
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .document-actions {
            display: flex;
            gap: 10px;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Left Panel -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="similar_dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>My Profile</span>
            </div>

            <!-- Profile Tabs -->
            <div class="profile-tabs">
                <div class="profile-tab active" data-tab="personal">
                    <i class="fas fa-user"></i> Personal Info
                </div>
                <div class="profile-tab" data-tab="security">
                    <i class="fas fa-lock"></i> Security
                </div>
                <div class="profile-tab" data-tab="notifications">
                    <i class="fas fa-bell"></i> Notifications
                </div>
                <div class="profile-tab" data-tab="activity">
                    <i class="fas fa-history"></i> Activity Log
                </div>
                <div class="profile-tab" data-tab="hr-documents">
                    <i class="fas fa-file-alt"></i> HR Documents
                </div>
            </div>

            <!-- Profile Card (Personal Info) -->
            <div class="profile-section active" id="personal">
                <div class="profile-card">
                    <!-- Your existing profile form here -->
                    <?php include 'includes/profile_form.php'; ?>
                </div>
            </div>

            <!-- Security Section -->
            <div class="profile-section" id="security">
                <div class="profile-card">
                    <h2 class="section-title">Security Settings</h2>
                    <form id="securityForm" onsubmit="return updateSecurity(event)">
                        <div class="form-section">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notifications Section -->
            <div class="profile-section" id="notifications">
                <div class="notification-preferences">
                    <h2 class="section-title">Notification Preferences</h2>
                    <div class="preference-item">
                        <div class="preference-info">
                            <h3>Email Notifications</h3>
                            <p>Receive updates via email</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked id="emailNotifications">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="preference-item">
                        <div class="preference-info">
                            <h3>Push Notifications</h3>
                            <p>Receive notifications in browser</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="pushNotifications">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <!-- Add more notification preferences as needed -->
                </div>
            </div>

            <!-- Activity Log Section -->
            <div class="profile-section" id="activity">
                <div class="activity-log">
                    <h2 class="section-title">Recent Activity</h2>
                    <!-- Activity items will be loaded dynamically -->
                    <div id="activityContainer">
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-text">Logged in successfully</div>
                                <div class="activity-time">Today at 9:30 AM</div>
                            </div>
                        </div>
                        <!-- More activity items -->
                    </div>
                </div>
            </div>

            <!-- HR Documents Section -->
            <div class="profile-section" id="hr-documents">
                <div class="hr-documents">
                    <h2 class="section-title">HR Documents</h2>
                    <div id="documents-container" class="documents-container">
                        <!-- Documents will be loaded dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Tab switching functionality
        document.querySelectorAll('.profile-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and sections
                document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.profile-section').forEach(s => s.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding section
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
            });
        });

        // Handle avatar upload
        function handleAvatarUpload(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                    uploadAvatar(file);
                };
                
                reader.readAsDataURL(file);
            }
        }

        // Upload avatar to server
        function uploadAvatar(file) {
            const formData = new FormData();
            formData.append('avatar', file);
            
            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Profile picture updated successfully'
                    });
                } else {
                    throw new Error(data.message || 'Failed to update profile picture');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
            });
        }

        // Handle form submission
        function handleSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            
            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Profile updated successfully'
                    });
                } else {
                    throw new Error(data.message || 'Failed to update profile');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
            });
            
            return false;
        }

        // Handle security form submission
        function updateSecurity(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            
            if (formData.get('new_password') !== formData.get('confirm_password')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'New passwords do not match'
                });
                return false;
            }
            
            fetch('update_security.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Security settings updated successfully'
                    });
                    event.target.reset();
                } else {
                    throw new Error(data.message || 'Failed to update security settings');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
            });
            
            return false;
        }

        // Handle notification preferences
        document.querySelectorAll('.switch input').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const type = this.id;
                const enabled = this.checked;
                
                fetch('update_notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: type,
                        enabled: enabled
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Failed to update notification preferences');
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message
                    });
                    // Revert toggle if update failed
                    this.checked = !enabled;
                });
            });
        });

        // Load activity log
        function loadActivityLog() {
            fetch('get_activity_log.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('activityContainer');
                        container.innerHTML = data.activities.map(activity => `
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="${getActivityIcon(activity.type)}"></i>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-text">${activity.description}</div>
                                    <div class="activity-time">${formatActivityTime(activity.timestamp)}</div>
                                </div>
                            </div>
                        `).join('');
                    }
                })
                .catch(error => console.error('Error loading activity log:', error));
        }

        function getActivityIcon(type) {
            const icons = {
                login: 'fas fa-sign-in-alt',
                profile_update: 'fas fa-user-edit',
                password_change: 'fas fa-key',
                // Add more activity types and icons as needed
            };
            return icons[type] || 'fas fa-info-circle';
        }

        function formatActivityTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleString();
        }

        // Load activity log when the activity tab is shown
        document.querySelector('[data-tab="activity"]').addEventListener('click', loadActivityLog);

        // Add this function to load HR documents
        function loadHRDocuments() {
            fetch('get_hr_documents.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('documents-container');
                        container.innerHTML = data.documents.map(doc => `
                            <div class="document-item">
                                <div class="document-icon">
                                    <i class="fas ${doc.icon_class}"></i>
                                </div>
                                <div class="document-details">
                                    <h3>${doc.original_name}</h3>
                                    <p>Last updated: ${doc.last_modified}</p>
                                    <p><small>
                                        Size: ${doc.formatted_size}
                                        ${doc.uploaded_by_name ? `• Uploaded by: ${doc.uploaded_by_name}` : ''}
                                    </small></p>
                                    <div class="document-actions">
                                        <button class="btn btn-primary btn-sm" onclick="viewDocument(${doc.id})">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-secondary btn-sm" onclick="downloadDocument(${doc.id})">
                                            <i class="fas fa-download"></i> Download
                                        </button>
                                        ${doc.acknowledgment_status === 'acknowledged' ? `
                                            <button class="btn btn-success btn-sm" disabled>
                                                <i class="fas fa-check"></i> Acknowledged
                                            </button>
                                        ` : `
                                            <button class="btn btn-info btn-sm" onclick="acknowledgeDocument(${doc.id})">
                                                <i class="fas fa-clipboard-check"></i> Read & Accept
                                            </button>
                                        `}
                                    </div>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        throw new Error(data.message || 'Failed to load documents');
                    }
                })
                .catch(error => {
                    console.error('Error loading documents:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message
                    });
                });
        }

        // Update document handling functions to handle numeric IDs
        function viewDocument(docId) {
            if (!docId) return;
            window.open(`view_document.php?id=${docId}`, '_blank');
        }

        function downloadDocument(docId) {
            if (!docId) return;
            window.location.href = `download_document.php?id=${docId}`;
        }

        // Add this new function for document acknowledgment
        function acknowledgeDocument(docId) {
            if (!docId) return;

            Swal.fire({
                title: 'Confirm Acknowledgment',
                text: 'By clicking "Confirm", you acknowledge that you have read and accepted this document.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Confirm',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('acknowledge_document.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            document_id: docId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: 'Document has been acknowledged.'
                            });
                            loadHRDocuments(); // Reload the documents list
                        } else {
                            throw new Error(data.message || 'Failed to acknowledge document');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message
                        });
                    });
                }
            });
        }

        // Load documents when the HR documents tab is shown
        document.querySelector('[data-tab="hr-documents"]').addEventListener('click', loadHRDocuments);

        // Also load documents if it's the active tab on page load
        if (window.location.hash === '#hr-documents') {
            document.querySelector('[data-tab="hr-documents"]').click();
        } else {
            // Optionally preload the documents anyway
            loadHRDocuments();
        }
    </script>
</body>
</html>
