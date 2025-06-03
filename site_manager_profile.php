<?php
// Start session for authentication
session_start();

// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Redirect to login page if not logged in
    $_SESSION['error'] = "You must log in to access the profile";
    header('Location: login.php');
    exit();
}

// Check if user has the correct role
$allowed_roles = ['Site Manager', 'Senior Manager (Site)', 'Purchase Manager', 'Site Coordinator'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    // Redirect to appropriate page based on role
    $_SESSION['error'] = "You don't have permission to access this page";
    header('Location: login.php');
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Get the site manager's details from session
$userId = $_SESSION['user_id'];
$siteManagerName = isset($_SESSION['username']) ? $_SESSION['username'] : "Site Manager";

// Fetch user data from database
$userData = [];
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
    } else {
        // User not found in database
        $_SESSION['error'] = "User data not found";
        header('Location: login.php');
        exit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    // Log error
    error_log("Database error: " . $e->getMessage());
    
    // Use placeholder data if database query fails
    $userData = [
        'id' => $userId,
        'username' => $siteManagerName,
        'email' => 'manager@example.com',
        'phone' => '+91 98765 43210',
        'role' => $_SESSION['role'],
        'department' => 'Project Management',
        'joining_date' => '2020-05-15',
        'employee_id' => 'SM' . str_pad($userId, 4, '0', STR_PAD_LEFT),
        'address' => 'Mumbai, Maharashtra',
        'profile_picture' => '',
        'designation' => $_SESSION['role'],
        'dob' => '1985-01-15',
        'gender' => 'Male',
        'marital_status' => 'Married',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'country' => 'India',
        'postal_code' => '400001',
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_contact_phone' => '+91 98765 43210',
        'blood_group' => 'O+',
        'nationality' => 'Indian',
        'languages' => 'English, Hindi',
        'bio' => 'Experienced site manager with expertise in construction project management.'
    ];
}

// Parse skills from JSON if available
$skills = [];
if (!empty($userData['skills'])) {
    try {
        $skills = json_decode($userData['skills'], true);
        if (!is_array($skills)) {
            $skills = [];
        }
    } catch (Exception $e) {
        $skills = [];
    }
}

// Parse education from JSON if available
$education = [];
if (!empty($userData['education_background'])) {
    try {
        $education = json_decode($userData['education_background'], true);
        if (!is_array($education)) {
            $education = [
                ['degree' => 'B.Tech Civil Engineering', 'institution' => 'IIT Mumbai', 'year' => '2015'],
                ['degree' => 'MBA Project Management', 'institution' => 'XLRI Jamshedpur', 'year' => '2018']
            ];
        }
    } catch (Exception $e) {
        $education = [
            ['degree' => 'B.Tech Civil Engineering', 'institution' => 'IIT Mumbai', 'year' => '2015'],
            ['degree' => 'MBA Project Management', 'institution' => 'XLRI Jamshedpur', 'year' => '2018']
        ];
    }
}

// Parse work experience from JSON if available
$work_experience = [];
if (!empty($userData['work_experiences'])) {
    try {
        $work_experience = json_decode($userData['work_experiences'], true);
        if (!is_array($work_experience)) {
            $work_experience = [];
        }
    } catch (Exception $e) {
        $work_experience = [];
    }
}

// Default certifications
$certifications = [
    ['name' => 'PMP Certification', 'issuer' => 'PMI', 'year' => '2019'],
    ['name' => 'LEED Green Associate', 'issuer' => 'USGBC', 'year' => '2020']
];

// Parse social media from JSON if available
$socialMedia = [];
if (!empty($userData['social_media'])) {
    try {
        $socialMedia = json_decode($userData['social_media'], true);
        if (!is_array($socialMedia)) {
            $socialMedia = [];
        }
    } catch (Exception $e) {
        $socialMedia = [];
    }
}

// Handle profile update
$updateMessage = '';
$updateStatus = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Validate and sanitize input
        $fullName = filter_input(INPUT_POST, 'fullName', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        
        try {
            // Update user data in database
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssssi", $fullName, $email, $phone, $address, $userId);
            $result = $stmt->execute();
            
            if ($result) {
                // Update successful
                $updateStatus = 'success';
                $updateMessage = 'Profile updated successfully!';
                
                // Update session data
                $_SESSION['username'] = $fullName;
                
                // Update local user data
                $userData['username'] = $fullName;
                $userData['email'] = $email;
                $userData['phone'] = $phone;
                $userData['address'] = $address;
            } else {
                // Update failed
                $updateStatus = 'danger';
                $updateMessage = 'Failed to update profile: ' . $stmt->error;
            }
            
            $stmt->close();
        } catch (Exception $e) {
            // Log error
            error_log("Database error during update: " . $e->getMessage());
            
            // Show error message
            $updateStatus = 'danger';
            $updateMessage = 'An error occurred while updating your profile';
        }
    }
    elseif (isset($_POST['update_social_media'])) {
        // Process social media update
        $socialMediaData = isset($_POST['social_media']) ? $_POST['social_media'] : [];
        $bio = filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_STRING);
        
        // Sanitize URLs
        foreach ($socialMediaData as $platform => $url) {
            if (!empty($url)) {
                $socialMediaData[$platform] = filter_var($url, FILTER_SANITIZE_URL);
            } else {
                unset($socialMediaData[$platform]); // Remove empty entries
            }
        }
        
        // Convert to JSON
        $socialMediaJson = json_encode($socialMediaData);
        
        try {
            // Update social media in database
            $stmt = $conn->prepare("UPDATE users SET social_media = ?, bio = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $socialMediaJson, $bio, $userId);
            $result = $stmt->execute();
            
            if ($result) {
                // Update successful
                $updateStatus = 'success';
                $updateMessage = 'Social media profiles updated successfully!';
                
                // Update local data
                $socialMedia = $socialMediaData;
                $userData['bio'] = $bio;
                $userData['social_media'] = $socialMediaJson;
            } else {
                // Update failed
                $updateStatus = 'danger';
                $updateMessage = 'Failed to update social media: ' . $stmt->error;
            }
            
            $stmt->close();
        } catch (Exception $e) {
            // Log error
            error_log("Database error during social media update: " . $e->getMessage());
            
            // Show error message
            $updateStatus = 'danger';
            $updateMessage = 'An error occurred while updating your social media profiles';
        }
    }
    elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        // Handle profile picture upload
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $filesize = $_FILES['profile_picture']['size'];
        $filetype = $_FILES['profile_picture']['type'];
        $tmp_name = $_FILES['profile_picture']['tmp_name'];
        
        // Get file extension
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Validate file extension
        if (!in_array($file_ext, $allowed)) {
            $updateStatus = 'danger';
            $updateMessage = 'Invalid file format. Allowed formats: JPG, JPEG, PNG, GIF';
        } 
        // Validate file size (max 5MB)
        elseif ($filesize > 5 * 1024 * 1024) {
            $updateStatus = 'danger';
            $updateMessage = 'File size exceeds the limit of 5MB';
        } 
        else {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/profile_pictures/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $new_filename = 'profile_' . $userId . '_' . time() . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($tmp_name, $destination)) {
                // Update database with new profile picture path
                try {
                    $profile_picture_path = $destination;
                    
                    $stmt = $conn->prepare("UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("si", $profile_picture_path, $userId);
                    $result = $stmt->execute();
                    
                    if ($result) {
                        // Update successful
                        $updateStatus = 'success';
                        $updateMessage = 'Profile picture updated successfully!';
                        
                        // Update local data
                        $userData['profile_picture'] = $profile_picture_path;
                        
                        // Update session data
                        $_SESSION['profile_picture'] = $profile_picture_path;
                    } else {
                        // Update failed
                        $updateStatus = 'danger';
                        $updateMessage = 'Failed to update profile picture in database: ' . $stmt->error;
                    }
                    
                    $stmt->close();
                } catch (Exception $e) {
                    // Log error
                    error_log("Database error during profile picture update: " . $e->getMessage());
                    
                    // Show error message
                    $updateStatus = 'danger';
                    $updateMessage = 'An error occurred while updating your profile picture';
                }
            } else {
                $updateStatus = 'danger';
                $updateMessage = 'Failed to upload profile picture';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Site Manager</title>
    
    <!-- Include CSS files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/manager/dashboard.css">
    
    <style>
        /* Profile Page Specific Styles */
        .profile-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 30px;
            border: 5px solid #f0f0f0;
            position: relative;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-upload {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background-color: #0d6efd;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .profile-info {
            flex: 1;
            min-width: 250px;
        }
        
        .profile-name {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .profile-designation {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        
        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #495057;
            font-size: 0.9rem;
        }
        
        .profile-meta-item i {
            color: #0d6efd;
        }
        
        .profile-actions {
            margin-top: 20px;
        }
        
        .profile-tabs {
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .profile-tab {
            padding: 10px 20px;
            cursor: pointer;
            display: inline-block;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .profile-tab.active {
            border-bottom-color: #0d6efd;
            color: #0d6efd;
        }
        
        .profile-section {
            margin-bottom: 30px;
        }
        
        .profile-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .profile-section-content {
            padding: 10px 0;
        }
        
        .form-row {
            margin-bottom: 20px;
        }
        
        .skill-badge {
            background-color: #e9ecef;
            color: #495057;
            padding: 5px 10px;
            border-radius: 15px;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
            font-size: 0.85rem;
        }
        
        .education-item, .certification-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .education-item:last-child, .certification-item:last-child {
            border-bottom: none;
        }
        
        .education-degree, .certification-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .education-details, .certification-details {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .password-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        /* Alert styles */
        .alert {
            margin-bottom: 20px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 20px;
            }
            
            .profile-meta {
                justify-content: center;
            }
            
            .profile-actions {
                display: flex;
                justify-content: center;
            }
        }
        
        /* Main container styles */
        .main-container {
            display: flex;
            height: 100vh;
            overflow: hidden;
            position: relative;
        }

        /* Left panel styles */
        #leftPanel {
            width: 250px;
            background-color: #1e2a78;
            color: white;
            transition: all 0.3s;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            height: 100vh;
            overflow-y: auto; /* Enable vertical scrolling */
            overflow-x: hidden;
            position: fixed;
            left: 0;
            top: 0;
        }

        /* Add scrollbar styling for the left panel */
        #leftPanel::-webkit-scrollbar {
            width: 6px;
            background-color: rgba(255, 255, 255, 0.1);
        }

        #leftPanel::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        #leftPanel::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        /* Left panel collapsed state */
        #leftPanel.collapsed {
            width: 70px;
            overflow-y: auto; /* Keep scrolling enabled in collapsed state */
        }

        /* Left panel mobile state */
        #leftPanel.mobile-open {
            width: 250px;
            transform: translateX(0);
            overflow-y: auto; /* Ensure scrolling works in mobile view */
        }

        /* Left panel needs-scrolling state */
        #leftPanel.needs-scrolling {
            overflow-y: auto !important; /* Force scrolling when content is tall */
        }

        /* Main content styles */
        .main-content {
            flex: 1;
            padding: 30px 30px 30px 30px;
            overflow-y: auto;
            height: 100vh;
            box-sizing: border-box;
            margin-left: 250px; /* Match the width of the left panel */
            position: relative;
            transition: margin-left 0.3s;
        }

        /* Main content expanded state */
        .main-content.expanded {
            margin-left: 70px; /* Match the width of the collapsed left panel */
        }

        /* Mobile styles */
        @media (max-width: 768px) {
            .hamburger-menu {
                display: flex;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            #leftPanel {
                width: 0;
                overflow: hidden;
                transform: translateX(-100%);
                transition: transform 0.3s, width 0.3s;
            }
            
            #leftPanel.mobile-open {
                width: 250px;
                transform: translateX(0);
                overflow-y: auto; /* Ensure scrolling works in mobile view */
            }
        }
    </style>
</head>
<body>
    <!-- Overlay for mobile menu -->
    <div class="overlay" id="overlay"></div>
    
    <!-- Hamburger menu for mobile -->
    <div class="hamburger-menu" id="hamburgerMenu">
        <i class="fas fa-bars"></i>
    </div>

    <div class="main-container">
        <!-- Include left panel -->
        <?php include_once('includes/manager_panel.php'); ?>
        
        <!-- Main Content Area -->
        <div class="main-content" id="mainContent">
            <h1 class="mb-4">My Profile</h1>
            
            <?php if(!empty($updateMessage)): ?>
                <div class="alert alert-<?php echo $updateStatus; ?> alert-dismissible fade show" role="alert">
                    <?php echo $updateMessage; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php if(isset($userData['profile_picture']) && !empty($userData['profile_picture'])): ?>
                            <img src="<?php echo $userData['profile_picture']; ?>" alt="<?php echo $userData['username']; ?>" id="profileImagePreview">
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($userData['username']); ?>&background=0D8ABC&color=fff" alt="<?php echo $userData['username']; ?>" id="profileImagePreview">
                        <?php endif; ?>
                        <div class="avatar-upload" title="Change profile picture" id="profilePictureUpload">
                            <i class="fas fa-camera"></i>
                        </div>
                        <form id="profilePictureForm" method="post" action="" enctype="multipart/form-data" style="display: none;">
                            <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*">
                        </form>
                    </div>
                    <div class="profile-info">
                        <h2 class="profile-name"><?php echo $userData['username']; ?></h2>
                        <div class="profile-designation"><?php echo $userData['designation']; ?> - <?php echo $userData['department']; ?></div>
                        <div class="profile-meta">
                            <div class="profile-meta-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo isset($userData['email']) ? $userData['email'] : 'N/A'; ?></span>
                            </div>
                            <div class="profile-meta-item">
                                <i class="fas fa-phone"></i>
                                <span><?php echo $userData['phone']; ?></span>
                            </div>
                            <div class="profile-meta-item">
                                <i class="fas fa-id-card"></i>
                                <span>Employee ID: <?php echo isset($userData['unique_id']) ? $userData['unique_id'] : 'N/A'; ?></span>
                            </div>
                        </div>
                        <div class="profile-actions">
                            <button class="btn btn-primary" id="editProfileBtn">
                                <i class="fas fa-edit"></i> Edit Profile
                            </button>
                            <button class="btn btn-outline-secondary ml-2">
                                <i class="fas fa-download"></i> Download CV
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="profile-tabs">
                    <div class="profile-tab active" data-tab="personal">Personal Information</div>
                    <div class="profile-tab" data-tab="professional">Professional Details</div>
                    <div class="profile-tab" data-tab="settings">Account Settings</div>
                </div>
                
                <!-- Personal Information Tab -->
                <div class="profile-tab-content" id="personal-tab">
                    <form id="personalInfoForm" method="post" action="">
                        <div class="profile-section">
                            <h3 class="profile-section-title">Personal Details</h3>
                            <div class="profile-section-content">
                                <div class="row form-row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="fullName">Full Name</label>
                                            <input type="text" class="form-control" id="fullName" name="fullName" value="<?php echo $userData['username']; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $userData['email']; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="phone">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $userData['phone']; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="address">Address</label>
                                            <input type="text" class="form-control" id="address" name="address" value="<?php echo $userData['address']; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="dob">Date of Birth</label>
                                            <input type="date" class="form-control" id="dob" name="dob" value="<?php echo isset($userData['dob']) ? $userData['dob'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="gender">Gender</label>
                                            <input type="text" class="form-control" id="gender" name="gender" value="<?php echo isset($userData['gender']) ? $userData['gender'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="maritalStatus">Marital Status</label>
                                            <input type="text" class="form-control" id="maritalStatus" name="maritalStatus" value="<?php echo isset($userData['marital_status']) ? $userData['marital_status'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="bloodGroup">Blood Group</label>
                                            <input type="text" class="form-control" id="bloodGroup" name="bloodGroup" value="<?php echo isset($userData['blood_group']) ? $userData['blood_group'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="nationality">Nationality</label>
                                            <input type="text" class="form-control" id="nationality" name="nationality" value="<?php echo isset($userData['nationality']) ? $userData['nationality'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="languages">Languages</label>
                                            <input type="text" class="form-control" id="languages" name="languages" value="<?php echo isset($userData['languages']) ? $userData['languages'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-section">
                            <h3 class="profile-section-title">Employment Information</h3>
                            <div class="profile-section-content">
                                <div class="row form-row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="employeeId">Employee ID</label>
                                            <input type="text" class="form-control" id="employeeId" name="employeeId" value="<?php echo isset($userData['unique_id']) ? $userData['unique_id'] : 'N/A'; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="joiningDate">Date of Joining</label>
                                            <input type="date" class="form-control" id="joiningDate" name="joiningDate" value="<?php echo $userData['joining_date']; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="designation">Designation</label>
                                            <input type="text" class="form-control" id="designation" name="designation" value="<?php echo $userData['designation']; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="department">Department</label>
                                            <input type="text" class="form-control" id="department" name="department" value="<?php echo $userData['department']; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="reportingManager">Reporting Manager</label>
                                            <input type="text" class="form-control" id="reportingManager" name="reportingManager" value="<?php echo isset($userData['reporting_manager']) ? $userData['reporting_manager'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="shift">Shift</label>
                                            <input type="text" class="form-control" id="shift" name="shift" value="<?php echo isset($userData['shift_id']) ? 'Shift #' . $userData['shift_id'] : 'Default'; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-section">
                            <h3 class="profile-section-title">Contact Information</h3>
                            <div class="profile-section-content">
                                <div class="row form-row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="fullAddress">Full Address</label>
                                            <textarea class="form-control" id="fullAddress" name="fullAddress" rows="2" readonly><?php echo isset($userData['address']) ? $userData['address'] : ''; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="city">City</label>
                                            <input type="text" class="form-control" id="city" name="city" value="<?php echo isset($userData['city']) ? $userData['city'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="state">State</label>
                                            <input type="text" class="form-control" id="state" name="state" value="<?php echo isset($userData['state']) ? $userData['state'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="country">Country</label>
                                            <input type="text" class="form-control" id="country" name="country" value="<?php echo isset($userData['country']) ? $userData['country'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="postalCode">Postal Code</label>
                                            <input type="text" class="form-control" id="postalCode" name="postalCode" value="<?php echo isset($userData['postal_code']) ? $userData['postal_code'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-section">
                            <h3 class="profile-section-title">Emergency Contact</h3>
                            <div class="profile-section-content">
                                <div class="row form-row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="emergencyContactName">Contact Name</label>
                                            <input type="text" class="form-control" id="emergencyContactName" name="emergencyContactName" value="<?php echo isset($userData['emergency_contact_name']) ? $userData['emergency_contact_name'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="emergencyContactPhone">Contact Phone</label>
                                            <input type="text" class="form-control" id="emergencyContactPhone" name="emergencyContactPhone" value="<?php echo isset($userData['emergency_contact_phone']) ? $userData['emergency_contact_phone'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-right mt-4 d-none" id="personalInfoButtons">
                            <button type="button" class="btn btn-secondary" id="cancelPersonalEdit">Cancel</button>
                            <button type="submit" class="btn btn-primary" name="update_profile">Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <!-- Professional Details Tab -->
                <div class="profile-tab-content" id="professional-tab" style="display: none;">
                    <div class="profile-section">
                        <h3 class="profile-section-title">Skills</h3>
                        <div class="profile-section-content">
                            <?php if (!empty($skills)): ?>
                                <?php foreach($skills as $skill): ?>
                                    <span class="skill-badge"><?php echo $skill; ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No skills listed</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="profile-section">
                        <h3 class="profile-section-title">Work Experience</h3>
                        <div class="profile-section-content">
                            <?php if (!empty($work_experience)): ?>
                                <?php foreach($work_experience as $experience): ?>
                                    <div class="education-item">
                                        <div class="education-degree"><?php echo $experience['position']; ?> at <?php echo $experience['company']; ?></div>
                                        <div class="education-details">
                                            <?php echo $experience['from_date']; ?> - <?php echo isset($experience['to_date']) ? $experience['to_date'] : 'Present'; ?>
                                        </div>
                                        <?php if (!empty($experience['description'])): ?>
                                            <div class="mt-2 text-muted"><?php echo $experience['description']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No work experience listed</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="profile-section">
                        <h3 class="profile-section-title">Education</h3>
                        <div class="profile-section-content">
                            <?php if (!empty($education)): ?>
                                <?php foreach($education as $educationItem): ?>
                                    <div class="education-item">
                                        <div class="education-degree"><?php echo $educationItem['degree']; ?></div>
                                        <div class="education-details">
                                            <?php echo $educationItem['institution']; ?> - <?php echo $educationItem['year']; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No education details listed</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="profile-section">
                        <h3 class="profile-section-title">Certifications</h3>
                        <div class="profile-section-content">
                            <?php if (!empty($certifications)): ?>
                                <?php foreach($certifications as $certification): ?>
                                    <div class="certification-item">
                                        <div class="certification-name"><?php echo $certification['name']; ?></div>
                                        <div class="certification-details">
                                            <?php echo $certification['issuer']; ?> - <?php echo $certification['year']; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No certifications listed</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Account Settings Tab -->
                <div class="profile-tab-content" id="settings-tab" style="display: none;">
                    <div class="profile-section">
                        <h3 class="profile-section-title">Account Settings</h3>
                        <div class="profile-section-content">
                            <div class="form-group">
                                <label class="d-block">Email Notifications</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="emailNotifications" checked>
                                    <label class="custom-control-label" for="emailNotifications">Receive email notifications</label>
                                </div>
                            </div>
                            
                            <div class="form-group mt-3">
                                <label class="d-block">SMS Notifications</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="smsNotifications" checked>
                                    <label class="custom-control-label" for="smsNotifications">Receive SMS notifications</label>
                                </div>
                            </div>
                            
                            <div class="form-group mt-3">
                                <label class="d-block">Two-Factor Authentication</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="twoFactorAuth">
                                    <label class="custom-control-label" for="twoFactorAuth">Enable two-factor authentication</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="password-section">
                        <h3 class="profile-section-title">Change Password</h3>
                        <div id="passwordChangeAlert" class="alert" style="display: none;"></div>
                        <form id="changePasswordForm">
                            <div class="form-group">
                                <label for="currentPassword">Current Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="currentPassword" name="currentPassword">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="currentPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="newPassword">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="newPassword" name="newPassword">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="newPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="password-strength-meter mt-2">
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar" id="passwordStrengthBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="form-text mt-1" id="passwordStrengthText">Password strength</small>
                                </div>
                                <small class="form-text text-muted">Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.</small>
                            </div>
                            <div class="form-group">
                                <label for="confirmPassword">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" id="updatePasswordBtn">Update Password</button>
                            <button type="button" class="btn btn-secondary ml-2" id="resetPasswordForm">Reset</button>
                            <button type="button" class="btn btn-info ml-2" id="testConnectionBtn" style="display: none;">Test Connection</button>
                        </form>
                    </div>
                </div>

                <div class="profile-section">
                    <h3 class="profile-section-title">Social Media</h3>
                    <div class="profile-section-content">
                        <form id="socialMediaForm" method="post" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="linkedin">LinkedIn</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fab fa-linkedin" style="color: #0077B5;"></i></span>
                                            </div>
                                            <input type="url" class="form-control" id="linkedin" name="social_media[linkedin]" 
                                                value="<?php echo isset($socialMedia['linkedin']) ? $socialMedia['linkedin'] : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="twitter">Twitter</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fab fa-twitter" style="color: #1DA1F2;"></i></span>
                                            </div>
                                            <input type="url" class="form-control" id="twitter" name="social_media[twitter]" 
                                                value="<?php echo isset($socialMedia['twitter']) ? $socialMedia['twitter'] : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="facebook">Facebook</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fab fa-facebook" style="color: #4267B2;"></i></span>
                                            </div>
                                            <input type="url" class="form-control" id="facebook" name="social_media[facebook]" 
                                                value="<?php echo isset($socialMedia['facebook']) ? $socialMedia['facebook'] : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="instagram">Instagram</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fab fa-instagram" style="color: #E1306C;"></i></span>
                                            </div>
                                            <input type="url" class="form-control" id="instagram" name="social_media[instagram]" 
                                                value="<?php echo isset($socialMedia['instagram']) ? $socialMedia['instagram'] : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="github">GitHub</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fab fa-github" style="color: #333;"></i></span>
                                            </div>
                                            <input type="url" class="form-control" id="github" name="social_media[github]" 
                                                value="<?php echo isset($socialMedia['github']) ? $socialMedia['github'] : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="youtube">YouTube</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fab fa-youtube" style="color: #FF0000;"></i></span>
                                            </div>
                                            <input type="url" class="form-control" id="youtube" name="social_media[youtube]" 
                                                value="<?php echo isset($socialMedia['youtube']) ? $socialMedia['youtube'] : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right mt-4" id="socialMediaButtons">
                                <button type="submit" class="btn btn-primary" name="update_social_media">Save Social Media</button>
                            </div>
                        </form>
                        
                        <?php if ($userData['bio']): ?>
                            <div class="mt-4">
                                <h5>Bio</h5>
                                <div class="form-group">
                                    <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo $userData['bio']; ?></textarea>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Tab switching
            $('.profile-tab').click(function() {
                const tabId = $(this).data('tab');
                
                // Update active tab
                $('.profile-tab').removeClass('active');
                $(this).addClass('active');
                
                // Show selected tab content
                $('.profile-tab-content').hide();
                $(`#${tabId}-tab`).show();
            });
            
            // Edit profile button
            $('#editProfileBtn').click(function() {
                // Make only personal details form fields editable
                $('#personalInfoForm input').not('#employeeId, #joiningDate, #designation, #department, #reportingManager, #shift').removeAttr('readonly');
                $('#personalInfoButtons').removeClass('d-none');
            });
            
            // Cancel edit button
            $('#cancelPersonalEdit').click(function() {
                // Make form fields readonly again
                $('#personalInfoForm input').attr('readonly', true);
                $('#personalInfoButtons').addClass('d-none');
            });
            
            // Toggle Panel Function
            function togglePanel() {
                const leftPanel = document.getElementById('leftPanel');
                const mainContent = document.getElementById('mainContent');
                const toggleIcon = document.getElementById('toggleIcon');
                
                leftPanel.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                
                if (leftPanel.classList.contains('collapsed')) {
                    toggleIcon.classList.remove('fa-chevron-left');
                    toggleIcon.classList.add('fa-chevron-right');
                    mainContent.style.marginLeft = '70px'; // Changed from 0 to 70px to match the collapsed panel width
                } else {
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                    mainContent.style.marginLeft = '250px';
                }
                
                // Check if scrolling is needed after toggling
                setTimeout(checkPanelScrolling, 100);
            }
            
            // Attach toggle function to button
            $('#leftPanelToggleBtn').click(togglePanel);
            
            // Function to check if left panel needs scrolling
            function checkPanelScrolling() {
                const leftPanel = document.getElementById('leftPanel');
                if (!leftPanel) return;
                
                // Simply check if the content height is greater than the viewport height
                if (leftPanel.scrollHeight > window.innerHeight) {
                    leftPanel.classList.add('needs-scrolling');
                } else {
                    leftPanel.classList.remove('needs-scrolling');
                }
            }
            
            // Mobile menu functions
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const leftPanel = document.getElementById('leftPanel');
            const overlay = document.getElementById('overlay');
            
            // Check if panel needs scrolling on page load
            checkPanelScrolling();
            
            // Hamburger menu click handler
            hamburgerMenu.addEventListener('click', function() {
                leftPanel.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
                // Check scrolling after toggling mobile view
                setTimeout(checkPanelScrolling, 100);
            });
            
            // Overlay click handler (close menu when clicking outside)
            overlay.addEventListener('click', function() {
                leftPanel.classList.remove('mobile-open');
                overlay.classList.remove('active');
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    leftPanel.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                }
                // Check if panel needs scrolling on window resize
                checkPanelScrolling();
            });
            
            // Profile picture upload
            $('#profilePictureUpload').click(function() {
                $('#profilePictureInput').click();
            });
            
            // Handle file selection
            $('#profilePictureInput').change(function() {
                if (this.files && this.files[0]) {
                    // Show loading indicator
                    $('#profileImagePreview').css('opacity', '0.5');
                    
                    // Create a preview
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#profileImagePreview').attr('src', e.target.result);
                        $('#profileImagePreview').css('opacity', '1');
                    }
                    reader.readAsDataURL(this.files[0]);
                    
                    // Submit the form
                    $('#profilePictureForm').submit();
                }
            });
            
            // Password change form
            $('#changePasswordForm').submit(function(e) {
                e.preventDefault();
                
                const currentPassword = $('#currentPassword').val();
                const newPassword = $('#newPassword').val();
                const confirmPassword = $('#confirmPassword').val();
                
                // Basic validation
                if (!currentPassword || !newPassword || !confirmPassword) {
                    showPasswordAlert('danger', 'Please fill in all password fields');
                    return;
                }
                
                if (newPassword !== confirmPassword) {
                    showPasswordAlert('danger', 'New password and confirmation do not match');
                    return;
                }
                
                // Password strength validation
                const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
                if (!passwordRegex.test(newPassword)) {
                    showPasswordAlert('danger', 'Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character');
                    return;
                }
                
                // Disable submit button and show loading state
                const updateBtn = $('#updatePasswordBtn');
                const originalBtnText = updateBtn.html();
                updateBtn.html('<i class="fas fa-spinner fa-spin"></i> Updating...');
                updateBtn.prop('disabled', true);
                
                // Send AJAX request to update password
                $.ajax({
                    url: 'process_password_change.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        currentPassword: currentPassword,
                        newPassword: newPassword,
                        confirmPassword: confirmPassword
                    },
                    beforeSend: function() {
                        // Clear any previous alerts
                        $('#passwordChangeAlert').hide();
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            showPasswordAlert('success', response.message);
                            $('#changePasswordForm')[0].reset();
                            
                            // Reset password strength meter
                            updatePasswordStrengthMeter(0);
                        } else {
                            showPasswordAlert('danger', response.message || 'Unknown error occurred');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        console.log('Response:', xhr.responseText);
                        
                        // Try to parse the response
                        try {
                            const errorResponse = JSON.parse(xhr.responseText);
                            showPasswordAlert('danger', errorResponse.message || 'Server error occurred');
                        } catch (e) {
                            // If we can't parse the response, show a generic error
                            showPasswordAlert('danger', 'An error occurred while updating your password. Please try again later.');
                            
                            // Add a link to refresh the page
                            $('#passwordChangeAlert').append('<br><a href="' + window.location.href + '">Refresh the page</a> and try again.');
                        }
                    },
                    complete: function() {
                        // Restore button state
                        updateBtn.html(originalBtnText);
                        updateBtn.prop('disabled', false);
                    }
                });
            });
            
            // Reset password form
            $('#resetPasswordForm').click(function() {
                $('#changePasswordForm')[0].reset();
                $('#passwordChangeAlert').hide();
            });
            
            // Function to show password alert
            function showPasswordAlert(type, message) {
                const alertElement = $('#passwordChangeAlert');
                alertElement.removeClass('alert-success alert-danger alert-warning');
                alertElement.addClass('alert-' + type);
                alertElement.html(message);
                alertElement.show();
                
                // Scroll to alert
                $('html, body').animate({
                    scrollTop: alertElement.offset().top - 100
                }, 200);
            }
            
            // Password strength meter
            $('#newPassword').on('input', function() {
                const password = $(this).val();
                const strength = calculatePasswordStrength(password);
                updatePasswordStrengthMeter(strength);
            });
            
            // Function to calculate password strength
            function calculatePasswordStrength(password) {
                // Initialize score
                let score = 0;
                
                // If password is empty, return 0
                if (password.length === 0) {
                    return 0;
                }
                
                // Length check
                if (password.length >= 8) {
                    score += 25;
                } else {
                    score += Math.floor((password.length / 8) * 25);
                }
                
                // Complexity checks
                const hasLowerCase = /[a-z]/.test(password);
                const hasUpperCase = /[A-Z]/.test(password);
                const hasNumbers = /\d/.test(password);
                const hasSpecialChars = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
                
                // Add score for each complexity criteria
                if (hasLowerCase) score += 15;
                if (hasUpperCase) score += 15;
                if (hasNumbers) score += 15;
                if (hasSpecialChars) score += 15;
                
                // Add bonus for combination of criteria
                const varietyCount = [hasLowerCase, hasUpperCase, hasNumbers, hasSpecialChars].filter(Boolean).length;
                score += (varietyCount - 1) * 5;
                
                // Cap score at 100
                return Math.min(100, score);
            }
            
            // Function to update password strength meter
            function updatePasswordStrengthMeter(strength) {
                const strengthBar = $('#passwordStrengthBar');
                const strengthText = $('#passwordStrengthText');
                
                // Update progress bar width
                strengthBar.css('width', strength + '%');
                strengthBar.attr('aria-valuenow', strength);
                
                // Update progress bar color and text
                if (strength < 25) {
                    strengthBar.removeClass('bg-warning bg-info bg-success').addClass('bg-danger');
                    strengthText.text('Very Weak').removeClass('text-warning text-info text-success').addClass('text-danger');
                } else if (strength < 50) {
                    strengthBar.removeClass('bg-danger bg-info bg-success').addClass('bg-warning');
                    strengthText.text('Weak').removeClass('text-danger text-info text-success').addClass('text-warning');
                } else if (strength < 75) {
                    strengthBar.removeClass('bg-danger bg-warning bg-success').addClass('bg-info');
                    strengthText.text('Good').removeClass('text-danger text-warning text-success').addClass('text-info');
                } else {
                    strengthBar.removeClass('bg-danger bg-warning bg-info').addClass('bg-success');
                    strengthText.text('Strong').removeClass('text-danger text-warning text-info').addClass('text-success');
                }
            }
            
            // Toggle password visibility
            $('.toggle-password').click(function() {
                const targetId = $(this).data('target');
                const passwordInput = $('#' + targetId);
                const icon = $(this).find('i');
                
                // Toggle password visibility
                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Test connection button (hidden by default)
            $('#testConnectionBtn').click(function() {
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Testing...');
                
                $.ajax({
                    url: 'process_password_change.php?test=true',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        showPasswordAlert('info', 'Connection test successful: ' + response.message);
                        console.log('Test response:', response);
                    },
                    error: function(xhr, status, error) {
                        showPasswordAlert('danger', 'Connection test failed: ' + error);
                        console.error('Test error:', xhr.responseText);
                    },
                    complete: function() {
                        $('#testConnectionBtn').prop('disabled', false).html('Test Connection');
                    }
                });
            });
            
            // Show test button if URL has debug parameter
            if (window.location.href.indexOf('debug=true') > -1) {
                $('#testConnectionBtn').show();
            }
        });
    </script>
</body>
</html>