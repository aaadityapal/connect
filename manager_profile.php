<?php
session_start();
// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Senior Manager (Studio)' && $_SESSION['role'] != 'Senior Manager (Sales)')) {
    // Redirect to login page if not authorized
    header('Location: login.php');
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Initialize variables
$success_message = '';
$error_message = '';

// Fetch current user data
$user_id = $_SESSION['user_id'];

// First, get column names from users table
try {
    $columns_query = "SHOW COLUMNS FROM users";
    $columns_result = $conn->query($columns_query);
    $existing_columns = [];
    
    while ($column = $columns_result->fetch_assoc()) {
        $existing_columns[] = $column['Field'];
    }
    
    // Now fetch user data
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        $error_message = "User not found!";
    }
    
    // Store column info for later use
    $_SESSION['existing_columns'] = $existing_columns;
    
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    $error_message = "An error occurred while fetching data.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Get form data
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $state = isset($_POST['state']) ? trim($_POST['state']) : '';
    $country = isset($_POST['country']) ? trim($_POST['country']) : '';
    $postal_code = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : '';
    $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
    $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
    $marital_status = isset($_POST['marital_status']) ? trim($_POST['marital_status']) : '';
    $nationality = isset($_POST['nationality']) ? trim($_POST['nationality']) : '';
    $languages = isset($_POST['languages']) ? trim($_POST['languages']) : '';
    $blood_group = isset($_POST['blood_group']) ? trim($_POST['blood_group']) : '';
    $emergency_contact_name = isset($_POST['emergency_contact_name']) ? trim($_POST['emergency_contact_name']) : '';
    $emergency_contact_phone = isset($_POST['emergency_contact_phone']) ? trim($_POST['emergency_contact_phone']) : '';
    
    // Validate input
    $validation_errors = [];
    if (empty($full_name)) {
        $validation_errors[] = "Full name is required";
    }
    if (empty($email)) {
        $validation_errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors[] = "Invalid email format";
    }
    if (empty($phone)) {
        $validation_errors[] = "Phone number is required";
    }
    
    // Handle profile picture upload
    $profile_picture = $user['profile_picture']; // Default to existing picture
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['size'] > 0) {
        $upload_dir = 'uploads/profile_pictures/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['profile_picture']['name']);
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Validate file
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file_type, $allowed_types)) {
            $validation_errors[] = "Only JPG, JPEG, PNG & GIF files are allowed";
        } elseif ($_FILES['profile_picture']['size'] > $max_size) {
            $validation_errors[] = "File is too large. Maximum size is 5MB";
        } elseif (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
            // File uploaded successfully
            $profile_picture = $target_file;
        } else {
            $validation_errors[] = "Failed to upload profile picture";
        }
    }

    // Update user data if there are no validation errors
    if (empty($validation_errors)) {
        try {
            // Use existing columns from session if available, otherwise fetch them
            $existing_columns = [];
            if (isset($_SESSION['existing_columns'])) {
                $existing_columns = $_SESSION['existing_columns'];
            } else {
                // Check if additional fields exist in the database
                $check_columns_query = "SHOW COLUMNS FROM users";
                $columns_result = $conn->query($check_columns_query);
                
                while ($column = $columns_result->fetch_assoc()) {
                    $existing_columns[] = $column['Field'];
                }
                
                // Store for future use
                $_SESSION['existing_columns'] = $existing_columns;
            }
            
            // Build the UPDATE query based on existing columns
            $update_query = "UPDATE users SET ";
            
            // Parameters array
            $params = [];
            $types = "";
            
            // Check for full_name or username field
            if (in_array('full_name', $existing_columns)) {
                $update_query .= "full_name = ?, ";
                $params[] = $full_name;
                $types .= "s";
            } else if (in_array('username', $existing_columns)) {
                $update_query .= "username = ?, ";
                $params[] = $full_name; // Use the full_name value for username
                $types .= "s";
            }
            
            // Email is required
            $update_query .= "email = ?, ";
            $params[] = $email;
            $types .= "s";
            
            // Check for phone or phone_number field
            if (in_array('phone', $existing_columns)) {
                $update_query .= "phone = ?, ";
                $params[] = $phone;
                $types .= "s";
            } else if (in_array('phone_number', $existing_columns)) {
                $update_query .= "phone_number = ?, ";
                $params[] = $phone; // Use the phone value for phone_number
                $types .= "s";
            }
            
            // Profile picture is required
            $update_query .= "profile_picture = ?";
            $params[] = $profile_picture;
            $types .= "s";
            
            // Check and add additional fields if they exist
            if (in_array('address', $existing_columns)) {
                $update_query .= ", address = ?";
                $params[] = $address;
                $types .= "s";
            }
            
            if (in_array('bio', $existing_columns)) {
                $update_query .= ", bio = ?";
                $params[] = $bio;
                $types .= "s";
            }
            
            if (in_array('city', $existing_columns)) {
                $update_query .= ", city = ?";
                $params[] = $city;
                $types .= "s";
            }
            
            if (in_array('state', $existing_columns)) {
                $update_query .= ", state = ?";
                $params[] = $state;
                $types .= "s";
            }
            
            if (in_array('country', $existing_columns)) {
                $update_query .= ", country = ?";
                $params[] = $country;
                $types .= "s";
            }
            
            if (in_array('postal_code', $existing_columns)) {
                $update_query .= ", postal_code = ?";
                $params[] = $postal_code;
                $types .= "s";
            }
            
            if (in_array('dob', $existing_columns)) {
                $update_query .= ", dob = ?";
                $params[] = $dob;
                $types .= "s";
            }
            
            if (in_array('gender', $existing_columns)) {
                $update_query .= ", gender = ?";
                $params[] = $gender;
                $types .= "s";
            }
            
            if (in_array('marital_status', $existing_columns)) {
                $update_query .= ", marital_status = ?";
                $params[] = $marital_status;
                $types .= "s";
            }
            
            if (in_array('nationality', $existing_columns)) {
                $update_query .= ", nationality = ?";
                $params[] = $nationality;
                $types .= "s";
            }
            
            if (in_array('languages', $existing_columns)) {
                $update_query .= ", languages = ?";
                $params[] = $languages;
                $types .= "s";
            }
            
            if (in_array('blood_group', $existing_columns)) {
                $update_query .= ", blood_group = ?";
                $params[] = $blood_group;
                $types .= "s";
            }
            
            if (in_array('emergency_contact_name', $existing_columns)) {
                $update_query .= ", emergency_contact_name = ?";
                $params[] = $emergency_contact_name;
                $types .= "s";
            }
            
            if (in_array('emergency_contact_phone', $existing_columns)) {
                $update_query .= ", emergency_contact_phone = ?";
                $params[] = $emergency_contact_phone;
                $types .= "s";
            }
            
            // Complete the query
            $update_query .= " WHERE id = ?";
            $params[] = $user_id;
            $types .= "i";
            
            // Prepare and execute
            $update_stmt = $conn->prepare($update_query);
            
            // Dynamically bind parameters
            $bind_params = array();
            $bind_params[] = & $types;
            for ($i = 0; $i < count($params); $i++) {
                $bind_params[] = & $params[$i];
            }
            call_user_func_array(array($update_stmt, 'bind_param'), $bind_params);
            
            if ($update_stmt->execute()) {
                // Update session data
                $_SESSION['profile_picture'] = $profile_picture;
                
                $success_message = "Profile updated successfully!";
                
                // Refresh user data
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $error_message = "Failed to update profile: " . $conn->error;
            }
        } catch (Exception $e) {
            error_log("Error updating profile: " . $e->getMessage());
            $error_message = "An error occurred while updating profile.";
        }
    } else {
        $error_message = implode("<br>", $validation_errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-styles.css">
    <link rel="stylesheet" href="assets/css/notification-system.css">
    <style>
        /* Additional styles for profile page */
        .profile-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }
        
        .profile-title {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            color: #333;
        }
        
        .profile-tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            margin-bottom: 25px;
        }
        
        .profile-tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 500;
            color: #555;
            position: relative;
            transition: color 0.3s;
        }
        
        .profile-tab.active {
            color: #4361ee;
        }
        
        .profile-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: #4361ee;
        }
        
        .profile-form {
            max-width: 100%;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 20px;
            gap: 20px;
        }
        
        .form-group {
            flex: 1;
            min-width: 250px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: #4361ee;
            outline: none;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .profile-picture-container {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .current-picture {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #f0f2f5;
            margin-right: 25px;
        }
        
        .picture-upload {
            flex: 1;
        }
        
        .picture-upload .form-control {
            padding: 10px;
        }
        
        .btn-update {
            background-color: #4361ee;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-update:hover {
            background-color: #3851d3;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        
        .alert-success {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #842029;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 25px 0 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar" id="sidebar">
            <div class="toggle-btn" id="toggle-btn">
                <i class="fas fa-chevron-left"></i>
            </div>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">MAIN</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="real.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="sidebar-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-calendar-check"></i>
                        <span class="sidebar-text">Leaves</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-users"></i>
                        <span class="sidebar-text">Employees</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-box"></i>
                        <span class="sidebar-text">Projects</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">ANALYTICS</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="#">
                        <i class="fas fa-chart-line"></i>
                        <span class="sidebar-text"> Employee Reports</span>
                    </a>
                </li>
                <li>
                    <a href="work_report.php">
                        <i class="fas fa-file-invoice"></i>
                        <span class="sidebar-text"> Work Reports</span>
                    </a>
                </li>
                <li>
                    <a href="attendance_report.php">
                        <i class="fas fa-clock"></i>
                        <span class="sidebar-text"> Attendance Reports</span>
                    </a>
                </li>
                
            </ul>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">SETTINGS</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li class="active">
                    <a href="manager_profile.php">
                        <i class="fas fa-user"></i>
                        <span class="sidebar-text">Profile</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-bell"></i>
                        <span class="sidebar-text">Notifications</span>
                    </a>
                </li>
                <li>
                    <a href="manager_settings.php">
                        <i class="fas fa-cog"></i>
                        <span class="sidebar-text">Settings</span>
                    </a>
                </li>
                <li>
                    <a href="reset_password.php">
                        <i class="fas fa-lock"></i>
                        <span class="sidebar-text">Reset Password</span>
                    </a>
                </li>
            </ul>

            <!-- Add logout at the end of sidebar -->
            <div class="sidebar-footer">
                <ul class="sidebar-menu">
                    <li>
                        <a href="logout.php" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            <span class="sidebar-text">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="main-content">
            <!-- Page heading with breadcrumb -->
            <div class="page-header">
                <h2>My Profile</h2>
            </div>

            <!-- Display success/error messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Profile content -->
            <div class="profile-container">
                <div class="profile-header">
                    <h3 class="profile-title">Profile Information</h3>
                </div>
                
                <div class="profile-tabs">
                    <div class="profile-tab active" data-tab="personal-info">Personal Information</div>
                    <div class="profile-tab" data-tab="change-password">Change Password</div>
                </div>
                
                <!-- Personal Information Tab -->
                <div class="tab-content active" id="personal-info">
                    <form class="profile-form" action="manager_profile.php" method="POST" enctype="multipart/form-data">
                        <div class="profile-picture-container">
                            <img src="<?php echo isset($user['profile_picture']) && !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'assets/default-avatar.png'; ?>" 
                                alt="Profile Picture" class="current-picture" id="profile-preview">
                            
                            <div class="picture-upload">
                                <label for="profile_picture">Profile Picture</label>
                                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this)">
                                <small>Allowed formats: JPG, JPEG, PNG, GIF. Max size: 5MB</small>
                            </div>
                        </div>
                        
                        <?php
                        // Suppress errors for missing fields
                        error_reporting(E_ERROR | E_PARSE);
                        ?>
                        
                        <h4 class="section-title">Basic Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : (isset($user['username']) ? htmlspecialchars($user['username']) : ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : (isset($user['phone_number']) ? htmlspecialchars($user['phone_number']) : ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="role">Role</label>
                                <input type="text" class="form-control" id="role" value="<?php echo isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : ''; ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="dob">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob" value="<?php echo isset($user['dob']) ? htmlspecialchars($user['dob']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select class="form-control" id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo (isset($user['gender']) && $user['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (isset($user['gender']) && $user['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo (isset($user['gender']) && $user['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="marital_status">Marital Status</label>
                                <select class="form-control" id="marital_status" name="marital_status">
                                    <option value="">Select Marital Status</option>
                                    <option value="Single" <?php echo (isset($user['marital_status']) && $user['marital_status'] == 'Single') ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?php echo (isset($user['marital_status']) && $user['marital_status'] == 'Married') ? 'selected' : ''; ?>>Married</option>
                                    <option value="Divorced" <?php echo (isset($user['marital_status']) && $user['marital_status'] == 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="Widowed" <?php echo (isset($user['marital_status']) && $user['marital_status'] == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="blood_group">Blood Group</label>
                                <select class="form-control" id="blood_group" name="blood_group">
                                    <option value="">Select Blood Group</option>
                                    <option value="A+" <?php echo (isset($user['blood_group']) && $user['blood_group'] == 'A+') ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo (isset($user['blood_group']) && $user['blood_group'] == 'A-') ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo (isset($user['blood_group']) && $user['blood_group'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo (isset($user['blood_group']) && $user['blood_group'] == 'B-') ? 'selected' : ''; ?>>B-</option>
                                    <option value="AB+" <?php echo (isset($user['blood_group']) && $user['blood_group'] == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo (isset($user['blood_group']) && $user['blood_group'] == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                    <option value="O+" <?php echo (isset($user['blood_group']) && $user['blood_group'] == 'O+') ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo (isset($user['blood_group']) && $user['blood_group'] == 'O-') ? 'selected' : ''; ?>>O-</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nationality">Nationality</label>
                                <input type="text" class="form-control" id="nationality" name="nationality" value="<?php echo isset($user['nationality']) ? htmlspecialchars($user['nationality']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="languages">Languages</label>
                                <input type="text" class="form-control" id="languages" name="languages" value="<?php echo isset($user['languages']) ? htmlspecialchars($user['languages']) : ''; ?>" placeholder="English, Hindi, etc.">
                            </div>
                        </div>
                        
                        <h4 class="section-title">Contact Information</h4>
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?php echo isset($user['address']) ? htmlspecialchars($user['address']) : ''; ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" class="form-control" id="city" name="city" value="<?php echo isset($user['city']) ? htmlspecialchars($user['city']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="state">State</label>
                                <input type="text" class="form-control" id="state" name="state" value="<?php echo isset($user['state']) ? htmlspecialchars($user['state']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="country">Country</label>
                                <input type="text" class="form-control" id="country" name="country" value="<?php echo isset($user['country']) ? htmlspecialchars($user['country']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="postal_code">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo isset($user['postal_code']) ? htmlspecialchars($user['postal_code']) : ''; ?>">
                            </div>
                        </div>
                        
                        <h4 class="section-title">Emergency Contact</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="emergency_contact_name">Emergency Contact Name</label>
                                <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo isset($user['emergency_contact_name']) ? htmlspecialchars($user['emergency_contact_name']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="emergency_contact_phone">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo isset($user['emergency_contact_phone']) ? htmlspecialchars($user['emergency_contact_phone']) : ''; ?>">
                            </div>
                        </div>
                        
                        <h4 class="section-title">About</h4>
                        <div class="form-group">
                            <label for="bio">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo isset($user['bio']) ? htmlspecialchars($user['bio']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="update_profile" class="btn-update">Update Profile</button>
                        </div>
                    </form>
                </div>
                
                <!-- Change Password Tab -->
                <div class="tab-content" id="change-password">
                    <form class="profile-form" action="change_password.php" method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="change_password" class="btn-update">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar
            const toggleBtn = document.getElementById('toggle-btn');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });

            // Tab switching
            const tabs = document.querySelectorAll('.profile-tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to current tab and content
                    tab.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Close alert messages after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 5000);
            });
        });
        
        // Preview profile image before upload
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html> 