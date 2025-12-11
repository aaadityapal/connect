<?php
session_start();
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data from database
try {
    $stmt = $pdo->prepare("SELECT username, role, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // User not found, logout
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

    // Get user data
    $username = htmlspecialchars($user['username']);
    $role = htmlspecialchars($user['role']);
    $profile_picture = $user['profile_picture'];

    // Generate initials from username
    $nameParts = explode(' ', $username);
    if (count($nameParts) >= 2) {
        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
    } else {
        $initials = strtoupper(substr($username, 0, 2));
    }

    // Set profile picture path
    if (!empty($profile_picture) && $profile_picture !== 'default.png') {
        $profile_pic_path = '../uploads/profile_pictures/' . $profile_picture;
        // Check if file exists
        if (!file_exists($profile_pic_path)) {
            $profile_pic_path = null;
        }
    } else {
        $profile_pic_path = null;
    }

    // Fetch HR/Policy documents
    try {
        $stmt = $pdo->prepare("SELECT id, policy_name, policy_type, stored_filename, original_filename, file_size, file_type, created_at 
                               FROM policy_documents 
                               ORDER BY created_at DESC");
        $stmt->execute();
        $hr_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Policy documents fetch error: " . $e->getMessage());
        $hr_documents = [];
    }
} catch (PDOException $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    $username = "User";
    $role = "Unknown";
    $initials = "U";
    $profile_pic_path = null;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>

<body>

    <div class="app-container">

        <div class="scroll-content">
            <div class="profile-header">
                <div class="avatar-container">
                    <?php if ($profile_pic_path): ?>
                        <div class="avatar avatar-image" id="profileAvatar">
                            <img src="<?php echo $profile_pic_path; ?>" alt="<?php echo $username; ?>" id="avatarImage">
                            <div class="avatar-overlay">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path
                                        d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z">
                                    </path>
                                    <circle cx="12" cy="13" r="4"></circle>
                                </svg>
                                <span>Change</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="avatar" id="profileAvatar">
                            <?php echo $initials; ?>
                            <div class="avatar-overlay">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path
                                        d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z">
                                    </path>
                                    <circle cx="12" cy="13" r="4"></circle>
                                </svg>
                                <span>Upload</span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="profilePictureInput" accept="image/*" style="display: none;">
                </div>
                <h3><?php echo $username; ?></h3>
                <p style="color: var(--text-muted); font-size: 0.875rem;"><?php echo $role; ?></p>
            </div>

            <div class="profile-menu">
                <a href="../logout.php" class="menu-item logout" id="logoutBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Logout</span>
                </a>
            </div>

            <!-- HR Documents Section -->
            <div class="hr-documents-section">
                <h4 class="section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    HR Documents
                </h4>

                <div class="documents-grid" id="documentsGrid">
                    <?php if (!empty($hr_documents)): ?>
                        <?php foreach ($hr_documents as $doc): ?>
                            <?php
                            // Format file size
                            $file_size_kb = round($doc['file_size'] / 1024, 1);
                            $file_size_display = $file_size_kb < 1024
                                ? $file_size_kb . ' KB'
                                : round($file_size_kb / 1024, 1) . ' MB';

                            // Get document type display name
                            $type_display = ucwords(str_replace('_', ' ', $doc['policy_type']));

                            // Use policy_name if available, otherwise use original_filename
                            $display_name = !empty($doc['policy_name']) ? $doc['policy_name'] : $doc['original_filename'];
                            ?>
                            <div class="document-card">
                                <div class="document-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                    </svg>
                                </div>
                                <div class="document-info">
                                    <h5><?php echo htmlspecialchars($display_name); ?></h5>
                                    <p><?php echo htmlspecialchars($type_display) . ' • ' . $file_size_display; ?></p>
                                </div>
                                <button class="download-btn" data-doc-id="<?php echo $doc['id']; ?>"
                                    data-filename="<?php echo htmlspecialchars($doc['stored_filename']); ?>"
                                    data-original-name="<?php echo htmlspecialchars($doc['original_filename']); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="7 10 12 15 17 10"></polyline>
                                        <line x1="12" y1="15" x2="12" y2="3"></line>
                                    </svg>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem 1rem; color: var(--text-muted);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                                style="margin: 0 auto 1rem; opacity: 0.3;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                            </svg>
                            <p style="font-size: 0.875rem;">No documents available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <nav class="bottom-nav">
            <a href="index.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Punch</span>
            </a>
            <a href="attendance.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <span>Attendance</span>
            </a>
            <a href="leaves.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>Leaves</span>
            </a>
            <a href="profile.php" class="nav-item active">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Profile</span>
            </a>
        </nav>
    </div>

    <script src="modal-notification.js"></script>
    <script src="profile.js"></script>
</body>

</html>