<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Basic validation
        if (empty($_POST['username']) || empty($_POST['email'])) {
            throw new Exception("Username and email are required fields.");
        }

        // Prepare update data
        $updateData = [
            'username' => $_POST['username'],
            'position' => $_POST['position'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'designation' => $_POST['designation'],
            'address' => $_POST['address'],
            'emergency_contact_name' => $_POST['emergency_contact_name'],
            'emergency_contact_phone' => $_POST['emergency_contact_phone'],
            'city' => $_POST['city'],
            'state' => $_POST['state'],
            'country' => $_POST['country'],
            'postal_code' => $_POST['postal_code'],
            'dob' => $_POST['dob'],
            'bio' => $_POST['bio'],
            'gender' => $_POST['gender'],
            'marital_status' => $_POST['marital_status'],
            'nationality' => $_POST['nationality'],
            'languages' => $_POST['languages'],
            'social_media' => json_encode($_POST['social_media']),
            'skills' => $_POST['skills'],
            'interests' => $_POST['interests'],
            'blood_group' => $_POST['blood_group'],
            'education' => json_encode($_POST['education']),
            'work_experience' => json_encode($_POST['work_experience']),
            'bank_details' => json_encode($_POST['bank_details']),
            'modified_at' => date('Y-m-d H:i:s'),
            'id' => $user_id
        ];

        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_picture']['name'];
            $filetype = pathinfo($filename, PATHINFO_EXTENSION);

            if (!in_array(strtolower($filetype), $allowed)) {
                throw new Exception("Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.");
            }

            $tempname = $_FILES['profile_picture']['tmp_name'];
            $folder = "uploads/profile_pictures/";
            
            // Create directory if it doesn't exist
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            // Delete old profile picture if exists
            if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                unlink($user['profile_picture']);
            }

            $new_filename = uniqid('profile_') . '.' . $filetype;
            $destination = $folder . $new_filename;
            
            if (move_uploaded_file($tempname, $destination)) {
                $updateData['profile_picture'] = $destination;
            } else {
                throw new Exception("Failed to upload profile picture.");
            }
        }

        // Build SQL query
        $sql = "UPDATE users SET 
                username = :username,
                position = :position,
                email = :email,
                phone = :phone,
                designation = :designation,
                address = :address,
                emergency_contact_name = :emergency_contact_name,
                emergency_contact_phone = :emergency_contact_phone,
                city = :city,
                state = :state,
                country = :country,
                postal_code = :postal_code,
                dob = :dob,
                bio = :bio,
                gender = :gender,
                marital_status = :marital_status,
                nationality = :nationality,
                languages = :languages,
                social_media = :social_media,
                skills = :skills,
                interests = :interests,
                blood_group = :blood_group,
                education = :education,
                work_experience = :work_experience,
                bank_details = :bank_details,
                modified_at = :modified_at";

        if (isset($updateData['profile_picture'])) {
            $sql .= ", profile_picture = :profile_picture";
        }

        $sql .= " WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateData);

        $_SESSION['success_message'] = "Profile updated successfully!";
        header("Location: hr_profile.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Enhancement Styles */
        :root {
            --primary-color: #4361ee;
            --primary-light: #eef2ff;
            --secondary-color: #3f37c9;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --background-color: #f3f4f6;
            --border-color: #e5e7eb;
            --text-color: #1f2937;
            --text-muted: #6b7280;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --sidebar-width: 280px;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            margin: 0;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease;
            z-index: 1000;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 10px);
        }

        .sidebar nav a {
            text-decoration: none;
        }

        .nav-link {
            color: var(--text-color);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link:hover, 
        .nav-link.active {
            color: #4361ee;
            background-color: #F3F4FF;
        }

        .nav-link.active {
            background-color: #F3F4FF;
            font-weight: 500;
        }

        .nav-link:hover i,
        .nav-link.active i {
            color: #4361ee;
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        /* Logout Link */
        .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            color: black!important;
            background-color: #D22B2B;
        }

        .logout-link:hover {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            padding: 2rem;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            background-color: var(--background-color);
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        .main-content .container {
            max-width: none;
            width: 100%;
            padding: 0;
            margin: 0;
        }

        .main-content .row {
            margin: 0;
            width: 100%;
        }

        .main-content .col-lg-10 {
            max-width: 100%;
            flex: 0 0 100%;
            padding: 0;
        }

        /* Toggle Button */
        .toggle-sidebar {
            position: fixed;
            left: calc(var(--sidebar-width) - 16px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 1001;
            background: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .toggle-sidebar:hover {
            background: var(--primary-color);
            color: white;
        }

        .toggle-sidebar.collapsed {
            left: 1rem;
        }

        .toggle-sidebar .bi {
            transition: transform 0.3s ease;
        }

        .toggle-sidebar.collapsed .bi {
            transform: rotate(180deg);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }

        /* Modern Profile Styling */
        .profile-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .profile-section:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .profile-section h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--primary-light);
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .profile-section h3::before {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 80px;
            height: 2px;
            background-color: var(--primary-color);
        }

        /* Profile Picture Improvements */
        .profile-picture-container {
            position: relative;
            margin: 0 auto;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .profile-picture-container:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .profile-picture {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-picture-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 4px;
            font-size: 12px;
            text-align: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .profile-picture-container:hover .profile-picture-overlay {
            opacity: 1;
        }

        /* Form Styling */
        .form-control, .form-select {
            padding: 0.75rem 1rem;
            height: auto;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 0.95rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: var(--primary-color);
            font-size: 1rem;
        }

        .form-text {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 0.35rem;
        }

        /* Button Styling */
        .btn {
            padding: 0.75rem 1.75rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background-color: #f9fafb;
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background-color: #f3f4f6;
        }

        /* Alert Styling */
        .alert {
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: none;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
            border-left: 4px solid var(--danger-color);
        }

        /* Section Icons */
        .section-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background-color: var(--primary-light);
            color: var(--primary-color);
            border-radius: 8px;
            margin-right: 0.75rem;
        }

        /* Layout and Spacing */
        .col-form-label {
            font-weight: 500;
        }

        .form-section-divider {
            height: 1px;
            background: linear-gradient(to right, var(--border-color), transparent);
            margin: 1.5rem 0;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .profile-section {
                padding: 1.5rem;
            }
            
            .profile-picture-container {
                width: 120px;
                height: 120px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <i class="bi bi-hexagon-fill"></i>
            HR Portal
        </div>
        
        <nav>
            <a href="hr_dashboard.php" class="nav-link">
                <i class="bi bi-grid-1x2-fill"></i>
                Dashboard
            </a>
            <a href="employee.php" class="nav-link">
                <i class="bi bi-people-fill"></i>
                Employees
            </a>
            <a href="hr_attendance_report.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Attendance
            </a>
            <a href="shifts.php" class="nav-link">
                <i class="bi bi-clock-history"></i>
                Shifts
            </a>
            <a href="salary_overview.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Leave Request
            </a>
            <a href="manage_leave_balance.php" class="nav-link">
                <i class="bi bi-briefcase-fill"></i>
                Recruitment
            </a>
            <a href="hr_documents_manager.php" class="nav-link">
                <i class="bi bi-file-earmark-text-fill"></i>
                Documents
            </a>
            <a href="generate_agreement.php" class="nav-link">
                <i class="bi bi-chevron-contract"></i>
                Contracts
            </a>
            <a href="hr_settings.php" class="nav-link">
                <i class="bi bi-gear-fill"></i>
                Settings
            </a>
            <!-- Logout Button -->
            <a href="logout.php" class="nav-link logout-link">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </nav>
    </div>

    <!-- Toggle Sidebar Button -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success">
                            <?php 
                            echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger">
                            <?php 
                            echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <!-- Personal Information -->
                        <div class="profile-section">
                            <h3>
                                <div class="section-icon">
                                    <i class="bi bi-person"></i>
                                </div>
                                Personal Information
                            </h3>
                            <div class="row">
                                <div class="col-md-3 text-center mb-4">
                                    <div class="profile-picture-container">
                                        <img src="<?php echo !empty($user['profile_picture']) && file_exists($user['profile_picture']) 
                                                ? $user['profile_picture'] 
                                                : 'assets/default-profile.png'; ?>" 
                                            class="profile-picture mb-3" alt="Profile Picture">
                                        <div class="profile-picture-overlay">
                                            <i class="bi bi-camera"></i> Change Photo
                                        </div>
                                    </div>
                                    <input type="file" class="form-control mt-3" name="profile_picture" accept="image/*">
                                    <small class="form-text">Recommended: Square image, 300x300 pixels</small>
                                </div>
                                <div class="col-md-9">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="bi bi-person-badge"></i> Username</label>
                                            <input type="text" class="form-control" name="username" 
                                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="bi bi-envelope"></i> Email</label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="bi bi-telephone"></i> Phone</label>
                                            <input type="tel" class="form-control" name="phone" 
                                                   value="<?php echo htmlspecialchars($user['phone']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="bi bi-calendar-date"></i> Date of Birth</label>
                                            <input type="date" class="form-control" name="dob" 
                                                   value="<?php echo $user['dob']; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Professional Information -->
                        <div class="profile-section">
                            <h3>
                                <div class="section-icon">
                                    <i class="bi bi-briefcase"></i>
                                </div>
                                Professional Information
                            </h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="bi bi-award"></i> Position</label>
                                    <input type="text" class="form-control" name="position" 
                                           value="<?php echo htmlspecialchars($user['position']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="bi bi-building"></i> Designation</label>
                                    <input type="text" class="form-control" name="designation" 
                                           value="<?php echo htmlspecialchars($user['designation']); ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label"><i class="bi bi-tools"></i> Skills</label>
                                    <input type="text" class="form-control" name="skills" 
                                           value="<?php echo htmlspecialchars($user['skills']); ?>" 
                                           placeholder="Separate skills with commas">
                                    <small class="form-text">Ex: Project Management, Team Leadership, Microsoft Excel</small>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="profile-section">
                            <h3>
                                <div class="section-icon">
                                    <i class="bi bi-geo-alt"></i>
                                </div>
                                Contact Information
                            </h3>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label"><i class="bi bi-house-door"></i> Address</label>
                                    <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><i class="bi bi-buildings"></i> City</label>
                                    <input type="text" class="form-control" name="city" 
                                           value="<?php echo htmlspecialchars($user['city']); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><i class="bi bi-geo"></i> State</label>
                                    <input type="text" class="form-control" name="state" 
                                           value="<?php echo htmlspecialchars($user['state']); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><i class="bi bi-globe"></i> Country</label>
                                    <input type="text" class="form-control" name="country" 
                                           value="<?php echo htmlspecialchars($user['country']); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><i class="bi bi-mailbox"></i> Postal Code</label>
                                    <input type="text" class="form-control" name="postal_code" 
                                           value="<?php echo htmlspecialchars($user['postal_code']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Contact -->
                        <div class="profile-section">
                            <h3>
                                <div class="section-icon">
                                    <i class="bi bi-shield-plus"></i>
                                </div>
                                Emergency Contact
                            </h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="bi bi-person-vcard"></i> Contact Name</label>
                                    <input type="text" class="form-control" name="emergency_contact_name" 
                                           value="<?php echo htmlspecialchars($user['emergency_contact_name']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="bi bi-telephone-forward"></i> Contact Phone</label>
                                    <input type="tel" class="form-control" name="emergency_contact_phone" 
                                           value="<?php echo htmlspecialchars($user['emergency_contact_phone']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="profile-section">
                            <h3>
                                <div class="section-icon">
                                    <i class="bi bi-info-circle"></i>
                                </div>
                                Additional Information
                            </h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="bi bi-gender-ambiguous"></i> Gender</label>
                                    <select class="form-select" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?php echo $user['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo $user['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo $user['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="bi bi-heart"></i> Marital Status</label>
                                    <select class="form-select" name="marital_status">
                                        <option value="">Select Status</option>
                                        <option value="single" <?php echo $user['marital_status'] === 'single' ? 'selected' : ''; ?>>Single</option>
                                        <option value="married" <?php echo $user['marital_status'] === 'married' ? 'selected' : ''; ?>>Married</option>
                                        <option value="divorced" <?php echo $user['marital_status'] === 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                                        <option value="widowed" <?php echo $user['marital_status'] === 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="bi bi-flag"></i> Nationality</label>
                                    <input type="text" class="form-control" name="nationality" 
                                           value="<?php echo htmlspecialchars($user['nationality']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="bi bi-droplet"></i> Blood Group</label>
                                    <select class="form-select" name="blood_group">
                                        <option value="">Select Blood Group</option>
                                        <?php
                                        $blood_groups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
                                        foreach ($blood_groups as $bg) {
                                            echo "<option value=\"$bg\"" . 
                                                 ($user['blood_group'] === $bg ? ' selected' : '') . 
                                                 ">$bg</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label"><i class="bi bi-translate"></i> Languages Known</label>
                                    <input type="text" class="form-control" name="languages" 
                                           value="<?php echo htmlspecialchars($user['languages']); ?>" 
                                           placeholder="Separate languages with commas">
                                    <small class="form-text">Ex: English, Spanish, Hindi</small>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4 mb-5">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="bi bi-save me-2"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelector('input[name="profile_picture"]').addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.querySelector('.profile-picture').src = e.target.result;
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Sidebar functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');
        
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            sidebarToggle.classList.toggle('collapsed');
            
            // Change icon direction
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.classList.remove('bi-chevron-left');
                icon.classList.add('bi-chevron-right');
            } else {
                icon.classList.remove('bi-chevron-right');
                icon.classList.add('bi-chevron-left');
            }
        });
        
        // Handle responsive behavior
        function checkWidth() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                sidebarToggle.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
                sidebarToggle.classList.remove('collapsed');
            }
        }
        
        // Check on load
        checkWidth();
        
        // Check on resize
        window.addEventListener('resize', checkWidth);
        
        // Handle click outside on mobile
        document.addEventListener('click', function(e) {
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile && !sidebar.contains(e.target) && !sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                sidebarToggle.classList.add('collapsed');
            }
        });
    });
    </script>
</body>
</html> 