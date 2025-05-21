<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has the 'Site Supervisor' role
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Site Supervisor') {
    header("Location: unauthorized.php");
    exit();
}

// Helper function to check if a document is sample data
function is_sample_data($doc) {
    // Check if this is a sample document (has no ID or a sample ID)
    return !isset($doc['id']) || $doc['id'] === 'sample';
}

// Include database connection
include_once('includes/db_connect.php');

// Process form submission for profile update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Collect form data
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    
    // Validate only essential fields
    if (empty($fullName) || empty($email)) {
        $message = "Name and email are required fields.";
        $messageType = "danger";
    } else {
        // Update profile in database
        try {
            $stmt = $pdo->prepare("UPDATE users SET 
                username = ?, 
                email = ?, 
                phone = ?, 
                address = ?, 
                emergency_contact = ?, 
                bio = ?,
                education = ?,
                updated_at = NOW()
                WHERE id = ?");
            
            // Build education JSON data from form
            $education_data = [];
            if (isset($_POST['education']) && is_array($_POST['education'])) {
                // Reindex to ensure there are no gaps in array indexes
                foreach ($_POST['education'] as $edu) {
                    if (isset($edu['degree']) && !empty($edu['degree'])) {
                        $education_data[] = [
                            'degree' => trim($edu['degree']),
                            'institution' => trim($edu['institution'] ?? ''),
                            'year' => trim($edu['year'] ?? ''),
                            'score' => trim($edu['score'] ?? '')
                        ];
                    }
                }
            }
            
            $education_json = json_encode($education_data);
            
            $result = $stmt->execute([
                $fullName, 
                $email, 
                $phone, 
                $address, 
                $emergency_contact, 
                $bio,
                $education_json,
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                $message = "Profile updated successfully!";
                $messageType = "success";
            } else {
                $message = "Failed to update profile. Please try again.";
                $messageType = "danger";
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Check if file type is allowed
        if (in_array(strtolower($filetype), $allowed)) {
            // Create upload directory if it doesn't exist
            $upload_dir = 'uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate a unique filename
            $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $filetype;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Update profile picture path in database
                try {
                    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $result = $stmt->execute([$upload_path, $_SESSION['user_id']]);
                    
                    if ($result) {
                        $message = "Profile updated successfully with new profile picture!";
                        $messageType = "success";
                    }
                } catch (PDOException $e) {
                    $message = "Failed to update profile picture in database: " . $e->getMessage();
                    $messageType = "danger";
                }
            } else {
                $message = "Failed to upload profile picture. Please try again.";
                $messageType = "warning";
            }
        } else {
            $message = "Invalid file type. Allowed types: JPG, JPEG, PNG, GIF.";
            $messageType = "warning";
        }
    }
}

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $message = "User not found!";
        $messageType = "danger";
    }
} catch (PDOException $e) {
    $message = "Error fetching user data: " . $e->getMessage();
    $messageType = "danger";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Site Supervisor</title>
    
    <!-- Include CSS files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/supervisor/dashboard.css">
    
    <style>
        :root {
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --light-bg: #f8f9fa;
            --dark-text: #212529;
            --gray-text: #6c757d;
            --border-color: #dee2e6;
            --card-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
        }
        
        /* Profile specific styles */
        .profile-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--card-shadow);
        }
        
        .profile-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--light-bg);
            margin-bottom: 1.5rem;
        }
        
        @media (min-width: 768px) {
            .profile-header {
                flex-direction: row;
                align-items: flex-start;
                text-align: left;
            }
        }
        
        .profile-picture-container {
            position: relative;
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }
        
        .profile-picture-container:hover {
            transform: translateY(-5px);
        }
        
        @media (min-width: 768px) {
            .profile-picture-container {
                margin-right: 2.5rem;
                margin-bottom: 0;
            }
        }
        
        .profile-picture {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--light-bg);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            transition: var(--transition);
        }
        
        /* Adjust profile picture size on mobile */
        @media (max-width: 480px) {
            .profile-picture {
                width: 140px;
                height: 140px;
            }
        }
        
        .profile-picture:hover {
            border-color: var(--primary-color);
        }
        
        .profile-picture-edit {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: var(--primary-color);
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            border: 3px solid white;
        }
        
        @media (max-width: 480px) {
            .profile-picture-edit {
                width: 35px;
                height: 35px;
                bottom: 5px;
                right: 5px;
            }
        }
        
        .profile-picture-edit:hover {
            background: var(--secondary-color);
            transform: scale(1.1);
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--dark-text);
        }
        
        @media (max-width: 480px) {
            .profile-name {
                font-size: 1.5rem;
            }
        }
        
        .profile-role {
            color: var(--gray-text);
            font-size: 1.1rem;
            margin-bottom: 1.25rem;
            padding: 0.5rem 1rem;
            background-color: rgba(67, 97, 238, 0.1);
            border-radius: 50px;
            display: inline-block;
        }
        
        @media (max-width: 480px) {
            .profile-role {
                font-size: 0.9rem;
                padding: 0.4rem 0.8rem;
            }
        }
        
        .profile-stats {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
            gap: 0.75rem;
        }
        
        .profile-stat {
            background: var(--light-bg);
            border-radius: 8px;
            padding: 0.75rem 1.25rem;
            font-size: 0.95rem;
            font-weight: 500;
            transition: var(--transition);
            border-left: 3px solid var(--primary-color);
            width: 100%;
        }
        
        @media (min-width: 576px) {
            .profile-stat {
                width: auto;
            }
        }
        
        .profile-stat:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .profile-stat i {
            color: var(--primary-color);
            margin-right: 0.5rem;
        }
        
        .profile-actions {
            margin-top: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        
        .profile-actions button {
            padding: 0.6rem 1.25rem;
            font-weight: 500;
            border-radius: 6px;
            transition: var(--transition);
            width: 100%;
        }
        
        @media (min-width: 576px) {
            .profile-actions button {
                width: auto;
            }
        }
        
        .profile-actions button:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .nav-tabs {
            border-bottom: 2px solid var(--light-bg);
            margin-bottom: 1.5rem;
            gap: 0.5rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: var(--gray-text);
            font-weight: 500;
            padding: 0.75rem 1.25rem;
            border-radius: 6px 6px 0 0;
            transition: var(--transition);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .nav-tabs .nav-link:hover:not(.active) {
            background-color: var(--light-bg);
            color: var(--dark-text);
        }
        
        .form-section {
            margin-bottom: 2rem;
            animation: fadeIn 0.4s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--light-bg);
            color: var(--dark-text);
        }
        
        .form-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark-text);
        }
        
        .form-control {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .profile-header {
                text-align: center;
            }
            
            .profile-stats {
                justify-content: center;
            }
            
            .profile-container {
                padding: 1.5rem 1rem;
            }
            
            .col-md-6 {
                margin-bottom: 1rem;
            }
        }
        
        /* For very small screens */
        @media (max-width: 375px) {
            .profile-container {
                padding: 1rem 0.75rem;
            }
            
            .profile-picture {
                width: 120px;
                height: 120px;
            }
            
            .profile-name {
                font-size: 1.3rem;
            }
        }
        
        /* Success message style */
        .alert-message {
            border-radius: 8px;
            margin-bottom: 1.5rem;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .profile-completion {
            height: 8px;
            margin-top: 0.5rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            background-color: var(--light-bg);
        }
        
        .profile-completion .progress-bar {
            border-radius: 4px;
            background-color: var(--success-color);
        }
        
        .btn {
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            transition: var(--transition);
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-secondary {
            background-color: #e9ecef;
            border-color: #e9ecef;
            color: var(--dark-text);
        }
        
        .btn-secondary:hover {
            background-color: #dee2e6;
            border-color: #dee2e6;
        }
        
        .custom-control-input:checked ~ .custom-control-label::before {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .education-section {
            margin: 2rem 0;
        }
        
        .education-form {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: end;
        }
        
        /* Make education form responsive */
        @media (max-width: 768px) {
            .education-form {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
        }
        
        @media (max-width: 480px) {
            .education-form {
                grid-template-columns: 1fr;
            }
        }
        
        .education-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .education-table th,
        .education-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .education-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark-text);
        }
        
        /* Make tables responsive */
        @media (max-width: 768px) {
            .education-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .education-table th, 
            .education-table td {
                padding: 0.75rem;
            }
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
            white-space: nowrap;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb i {
            margin: 0 10px;
            color: var(--gray-text);
        }
        
        /* Profile tabs responsive styles */
        .profile-tabs {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            background: white;
            padding: 0.75rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
            scrollbar-width: thin;
        }
        
        .profile-tabs::-webkit-scrollbar {
            height: 4px;
        }
        
        .profile-tabs::-webkit-scrollbar-thumb {
            background-color: var(--border-color);
            border-radius: 4px;
        }
        
        .profile-tab {
            padding: 0.75rem 1.25rem;
            cursor: pointer;
            border-radius: 6px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            color: var(--gray-text);
            flex: 0 0 auto;
            white-space: nowrap;
        }
        
        .profile-tab.active {
            background: var(--primary-color);
            color: white;
        }
        
        .profile-tab:hover:not(.active) {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }
        
        .profile-tab i {
            font-size: 1rem;
        }
        
        /* Profile sections */
        .profile-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .profile-section.active {
            display: block;
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            justify-content: flex-end;
        }
        
        .action-btn {
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            transition: var(--transition);
        }
        
        .edit-btn {
            background-color: #e9ecef;
            color: var(--primary-color);
        }
        
        .edit-btn:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .delete-btn {
            background-color: #f8d7da;
            color: #dc3545;
        }
        
        .delete-btn:hover {
            background-color: #dc3545;
            color: white;
        }
        
        .document-type-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-bg);
            overflow-x: auto;
            scrollbar-width: thin;
            white-space: nowrap;
        }
        
        .document-type-tabs::-webkit-scrollbar {
            height: 4px;
        }
        
        .document-type-tabs::-webkit-scrollbar-thumb {
            background-color: var(--border-color);
            border-radius: 4px;
        }
        
        .doc-tab {
            padding: 0.5rem 1rem;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 500;
            color: var(--gray-text);
            position: relative;
            white-space: nowrap;
            flex: 0 0 auto;
        }
        
        .doc-tab.active {
            color: var(--primary-color);
        }
        
        .doc-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1rem;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px;
        }
        
        /* Responsive button sizing */
        @media (max-width: 576px) {
            .btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
            
            .button-group {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .button-group .btn {
                width: 100%;
            }
        }
        
        /* Toggle switch responsive styles */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        @media (max-width: 480px) {
            .switch {
                width: 50px;
                height: 26px;
            }
            
            .slider:before {
                height: 18px;
                width: 18px;
            }
            
            input:checked + .slider:before {
                transform: translateX(24px);
            }
            
            .preference-info h3 {
                font-size: 0.95rem;
            }
            
            .preference-info p {
                font-size: 0.8rem;
            }
        }
        
        /* Make forms more responsive */
        @media (max-width: 576px) {
            .form-control {
                padding: 0.6rem;
                font-size: 0.95rem;
            }
            
            .form-group label {
                font-size: 0.95rem;
            }
            
            .form-section-title {
                font-size: 1.1rem;
            }
            
            .section-title {
                font-size: 1.3rem;
            }
        }
        
        /* Document upload section responsive styles */
        @media (max-width: 768px) {
            #document-upload-form .row {
                margin-left: 0;
                margin-right: 0;
            }
            
            .custom-file-label {
                font-size: 0.9rem;
            }
        }
        
        /* No documents placeholder responsive */
        .no-documents {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--gray-text);
            background: rgba(0,0,0,0.02);
            border-radius: 8px;
            margin: 1rem 0;
        }
        
        @media (max-width: 576px) {
            .no-documents {
                padding: 1.5rem 1rem;
            }
            
            .no-documents i {
                font-size: 2.5rem !important;
            }
            
            .no-documents p {
                font-size: 0.9rem;
            }
        }
        
        /* Action buttons responsive */
        .action-btn {
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            transition: var(--transition);
        }
        
        @media (max-width: 480px) {
            .action-btn {
                padding: 0.3rem 0.5rem;
                font-size: 0.75rem;
            }
        }
        
        /* Add responsive container padding */
        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .row {
                margin-left: -5px;
                margin-right: -5px;
            }
            
            .col-12 {
                padding-left: 5px;
                padding-right: 5px;
            }
        }
        
        /* Fix hamburger menu for small screens */
        @media (max-width: 768px) {
            .hamburger-menu {
                top: 10px;
                left: 10px;
                z-index: 1001;
            }
            
            .main-content {
                padding-top: 60px; /* Add space for the hamburger menu */
            }
        }
        
        /* Responsive page title */
        @media (max-width: 576px) {
            h2 {
                font-size: 1.5rem;
            }
        }
        
        /* Fix preference items on small screens */
        @media (max-width: 480px) {
            .preference-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .preference-item .switch {
                margin-top: 10px;
                align-self: flex-start;
            }
        }
        
        /* Form validation styles */
        .is-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }
        
        .form-text.text-muted {
            font-size: 0.8rem;
        }
        
        .text-danger {
            color: #dc3545 !important;
        }
        
        /* Add a message for optional fields */
        .optional-message {
            display: block;
            margin-top: 1rem;
            margin-bottom: 1rem;
            font-style: italic;
            color: var(--gray-text);
        }
        
        @media (max-width: 576px) {
            .optional-message {
                font-size: 0.85rem;
            }
        }
        
        /* Document table styles */
        .document-table {
            width: 100%;
            margin-bottom: 1rem;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .document-table th {
            background-color: var(--light-bg);
            color: var(--dark-text);
            font-weight: 600;
            padding: 0.75rem;
            text-align: left;
            border-bottom: 2px solid var(--border-color);
        }
        
        .document-table td {
            padding: 0.75rem;
            vertical-align: middle;
            border-top: 1px solid var(--border-color);
        }
        
        .document-table tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .document-table .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.2rem;
        }
        
        .document-table .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .document-table .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        /* Make document table responsive */
        .table-responsive {
            display: block;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        @media (max-width: 768px) {
            .document-table th,
            .document-table td {
                padding: 0.5rem;
                font-size: 0.9rem;
            }
            
            .document-table .btn-sm {
                padding: 0.2rem 0.4rem;
                font-size: 0.8rem;
            }
        }
        
        /* Document table badge styles */
        .badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        
        .badge-success {
            color: #fff;
            background-color: #28a745;
        }
        
        .badge-warning {
            color: #212529;
            background-color: #ffc107;
        }
        
        .badge-danger {
            color: #fff;
            background-color: #dc3545;
        }
        
        .badge-secondary {
            color: #fff;
            background-color: #6c757d;
        }
    </style>
</head>

<body>
    <!-- Hamburger Menu for Mobile -->
    <div class="hamburger-menu" id="hamburgerMenu" onclick="toggleMobilePanel()">
        <i class="fas fa-bars"></i>
    </div>
    
    <!-- Include Left Panel -->
    <?php include 'includes/supervisor_panel.php'; ?>
    
    <!-- Main Content Area -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="breadcrumb">
                        <a href="site_supervisor_dashboard.php">Dashboard</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>My Profile</span>
                    </div>
                    <h2><i class="fas fa-user-circle mr-2"></i>My Profile</h2>
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php 
            // Check for password change messages
            if (isset($_SESSION['password_message'])) {
                $message = $_SESSION['password_message'];
                $messageType = $_SESSION['password_message_type'];
                unset($_SESSION['password_message']);
                unset($_SESSION['password_message_type']);
            }
            
            // Check for preferences change messages
            if (isset($_SESSION['preferences_message'])) {
                $message = $_SESSION['preferences_message'];
                $messageType = $_SESSION['preferences_message_type'];
                unset($_SESSION['preferences_message']);
                unset($_SESSION['preferences_message_type']);
            }
            
            if (!empty($message)): 
            ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show alert-message" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
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
                <div class="profile-tab" data-tab="education">
                    <i class="fas fa-graduation-cap"></i> Education
                </div>
                <div class="profile-tab" data-tab="documents">
                    <i class="fas fa-file-alt"></i> Documents
                </div>
            </div>
            
            <!-- Profile Container -->
            <div class="row">
                <div class="col-12">
                    <div class="profile-section active" id="personal">
                        <div class="profile-container">
                            <!-- Profile Header -->
                            <div class="profile-header">
                                <div class="profile-picture-container">
                                    <img src="<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'images/default-profile.png'; ?>" 
                                         alt="Profile Picture" class="profile-picture" id="profile-image-preview">
                                    <label for="profile-upload" class="profile-picture-edit" title="Change profile picture">
                                        <i class="fas fa-camera"></i>
                                    </label>
                                </div>
                                
                                <div>
                                    <h2 class="profile-name"><?php echo htmlspecialchars($user['username'] ?? $user['username']); ?></h2>
                                    <p class="profile-role"><i class="fas fa-hard-hat mr-1"></i> Site Supervisor</p>
                                    
                                    <div class="profile-stats">
                                        <div class="profile-stat">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?>
                                        </div>
                                        <div class="profile-stat">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?>
                                        </div>
                                        <div class="profile-stat">
                                            <i class="fas fa-calendar-alt"></i> Joined: <?php echo date('M Y', strtotime($user['created_at'] ?? 'now')); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="profile-actions">
                                        <button class="btn btn-primary" id="editProfileBtn">
                                            <i class="fas fa-edit"></i> Edit Profile
                                        </button>
                                        <button class="btn btn-secondary" id="changePasswordBtn">
                                            <i class="fas fa-key"></i> Change Password
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Profile Completion Bar -->
                            <div>
                                <small class="text-muted">Profile Completion</small>
                                <div class="progress profile-completion">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small class="text-muted">Complete your profile to improve your site profile visibility</small>
                            </div>
                            
                            <form action="site_supervisor_profile.php" method="POST" enctype="multipart/form-data" id="profile-form">
                                <!-- Hidden file input for profile picture -->
                                <input type="file" id="profile-upload" name="profile_picture" style="display: none;" accept="image/*">
                                
                                <div class="form-section">
                                    <h5 class="form-section-title">Basic Information</h5>
                                    <p class="optional-message">Fields marked with <span class="text-danger">*</span> are required. All other fields are optional.</p>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="full_name">Username <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                                       value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                                                <small class="form-text text-muted">Required field</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email">Email Address <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                                <small class="form-text text-muted">Required field</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="phone">Phone Number</label>
                                                <input type="text" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="emergency_contact">Emergency Contact</label>
                                                <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                                       value="<?php echo htmlspecialchars($user['emergency_contact'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-section">
                                    <h5 class="form-section-title">Address Information</h5>
                                    <div class="form-group">
                                        <label for="address">Full Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="form-section">
                                    <h5 class="form-section-title">Professional Information</h5>
                                    <div class="form-group">
                                        <label for="bio">Professional Bio</label>
                                        <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                        <small class="form-text text-muted">Write a short description about your professional background and experience.</small>
                                    </div>
                                </div>
                                
                                <div class="text-right button-group">
                                    <button type="reset" class="btn btn-secondary">Reset</button>
                                    <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                                </div>
                                
                                <!-- Include education data in the main form -->
                                <div id="main-education-container"></div>
                            </form>
                        </div>
                    </div>

                    <!-- Security Section -->
                    <div class="profile-section" id="security">
                        <div class="profile-container">
                            <h2 class="section-title">Security Settings</h2>
                            <form id="password-form" action="change_password.php" method="POST">
                                <div class="form-section">
                                    <div class="form-group">
                                        <label for="current_password">Current Password</label>
                                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="new_password">New Password</label>
                                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                                        <small class="form-text text-muted">Password must be at least 8 characters long and include a mix of letters, numbers, and special characters.</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm_password">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                    </div>
                                </div>
                                <div class="button-group text-right">
                                    <button type="reset" class="btn btn-secondary">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Notifications Section -->
                    <div class="profile-section" id="notifications">
                        <div class="profile-container">
                            <h2 class="section-title">Notification Preferences</h2>
                            <form action="update_preferences.php" method="POST">
                                <div class="preference-item">
                                    <div class="preference-info">
                                        <h3>Email Notifications</h3>
                                        <p>Receive updates via email</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" id="email_notifications" name="email_notifications" 
                                            <?php echo ($user['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                
                                <div class="preference-item">
                                    <div class="preference-info">
                                        <h3>SMS Notifications</h3>
                                        <p>Receive urgent alerts via SMS</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" id="sms_notifications" name="sms_notifications" 
                                            <?php echo ($user['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                
                                <div class="preference-item">
                                    <div class="preference-info">
                                        <h3>Daily Activity Report</h3>
                                        <p>Receive daily summary of site activities</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" id="daily_report" name="daily_report" 
                                            <?php echo ($user['daily_report'] ?? 1) ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                
                                <div class="form-section">
                                    <h5 class="form-section-title">Display Settings</h5>
                                    
                                    <div class="form-group">
                                        <label for="theme_preference">Theme Preference</label>
                                        <select class="form-control" id="theme_preference" name="theme_preference">
                                            <option value="light" <?php echo ($user['theme_preference'] ?? 'light') === 'light' ? 'selected' : ''; ?>>Light Mode</option>
                                            <option value="dark" <?php echo ($user['theme_preference'] ?? 'light') === 'dark' ? 'selected' : ''; ?>>Dark Mode</option>
                                            <option value="system" <?php echo ($user['theme_preference'] ?? 'light') === 'system' ? 'selected' : ''; ?>>Use System Setting</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="dashboard_layout">Dashboard Layout</label>
                                        <select class="form-control" id="dashboard_layout" name="dashboard_layout">
                                            <option value="standard" <?php echo ($user['dashboard_layout'] ?? 'standard') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                                            <option value="compact" <?php echo ($user['dashboard_layout'] ?? 'standard') === 'compact' ? 'selected' : ''; ?>>Compact</option>
                                            <option value="detailed" <?php echo ($user['dashboard_layout'] ?? 'standard') === 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="button-group text-right">
                                    <button type="reset" class="btn btn-secondary">Reset to Defaults</button>
                                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Education Section -->
                    <div class="profile-section" id="education">
                        <div class="profile-container">
                            <h2 class="section-title">Education & Qualifications</h2>
                            
                            <div class="education-section">
                                <div class="education-form">
                                    <div class="form-group">
                                        <label for="degree">Degree/Certificate</label>
                                        <input type="text" class="form-control" id="degree" name="temp_degree">
                                    </div>
                                    <div class="form-group">
                                        <label for="institution">Institution</label>
                                        <input type="text" class="form-control" id="institution" name="temp_institution">
                                    </div>
                                    <div class="form-group">
                                        <label for="year">Year</label>
                                        <input type="text" class="form-control" id="year" name="temp_year">
                                    </div>
                                    <div class="form-group">
                                        <label for="score">Grade/Score</label>
                                        <input type="text" class="form-control" id="score" name="temp_score">
                                    </div>
                                    <div class="form-group d-flex align-items-end">
                                        <button type="button" class="btn btn-primary add-education-btn">
                                            <i class="fas fa-plus"></i> Add
                                        </button>
                                    </div>
                                </div>
                                
                                <table class="education-table">
                                    <thead>
                                        <tr>
                                            <th>Degree/Certificate</th>
                                            <th>Institution</th>
                                            <th>Year</th>
                                            <th>Grade/Score</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="educationTableBody">
                                        <!-- Education entries will be dynamically loaded here -->
                                        <?php
                                        // Parse education data from JSON
                                        $education_data = [];
                                        if (!empty($user['education'])) {
                                            $education_data = json_decode($user['education'], true);
                                            if (!is_array($education_data)) {
                                                $education_data = [];
                                            }
                                        }
                                        
                                        // Display existing education entries
                                        if (!empty($education_data)) {
                                            foreach ($education_data as $index => $edu) {
                                                echo '<tr data-index="' . $index . '">';
                                                echo '<td>' . htmlspecialchars($edu['degree'] ?? '') . '</td>';
                                                echo '<td>' . htmlspecialchars($edu['institution'] ?? '') . '</td>';
                                                echo '<td>' . htmlspecialchars($edu['year'] ?? '') . '</td>';
                                                echo '<td>' . htmlspecialchars($edu['score'] ?? '') . '</td>';
                                                echo '<td>';
                                                echo '<button type="button" class="action-btn edit-btn" data-index="' . $index . '"><i class="fas fa-edit"></i></button>';
                                                echo '<button type="button" class="action-btn delete-btn" data-index="' . $index . '"><i class="fas fa-trash"></i></button>';
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo "<!-- No education data found -->";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                
                                <!-- Hidden container for education form data -->
                                <div id="education-data-container">
                                    <?php
                                    if (!empty($education_data)) {
                                        foreach ($education_data as $index => $edu) {
                                            echo '<input type="hidden" name="education[' . $index . '][degree]" value="' . htmlspecialchars($edu['degree'] ?? '') . '">';
                                            echo '<input type="hidden" name="education[' . $index . '][institution]" value="' . htmlspecialchars($edu['institution'] ?? '') . '">';
                                            echo '<input type="hidden" name="education[' . $index . '][year]" value="' . htmlspecialchars($edu['year'] ?? '') . '">';
                                            echo '<input type="hidden" name="education[' . $index . '][score]" value="' . htmlspecialchars($edu['score'] ?? '') . '">';
                                        }
                                    }
                                    ?>
                                </div>
                                
                                <div class="button-group text-right mt-4">
                                    <button type="button" id="save-education-btn" class="btn btn-primary">Continue to Save Profile</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Documents Section -->
                    <div class="profile-section" id="documents">
                        <div class="profile-container">
                            <h2 class="section-title">HR Documents</h2>
                            
                            <div class="document-type-tabs">
                                <button class="doc-tab active" data-doctype="policies">Policies & Requirements</button>
                                <button class="doc-tab" data-doctype="official">Official Documents</button>
                                <button class="doc-tab" data-doctype="personal">Personal Documents</button>
                            </div>
                            
                            <!-- Documents containers -->
                            <div class="documents-container" id="policyDocuments">
                                <?php
                                // For easier debugging, optionally show all documents regardless of status
                                $show_all_documents = true; // Set to false in production
                                
                                // Fetch policy documents from the database
                                try {
                                    // Check if the table exists first
                                    $check_table = $pdo->query("SHOW TABLES LIKE 'policy_documents'");
                                    if ($check_table->rowCount() == 0) {
                                        // If table doesn't exist, create a temporary solution with sample data
                                        echo '<div class="alert alert-warning">The policy_documents table does not exist. Showing sample data for demonstration.</div>';
                                        
                                        // Sample policy documents for demonstration
                                        $policy_documents = [
                                            [
                                                'policy_name' => 'Employee Handbook',
                                                'policy_type' => 'Company Policy',
                                                'file_size' => '2.5MB',
                                                'uploaded_by' => 1,
                                                'created_at' => date('Y-m-d H:i:s'),
                                                'stored_filename' => 'sample_policy.pdf'
                                            ],
                                            [
                                                'policy_name' => 'Health and Safety Guidelines',
                                                'policy_type' => 'Safety',
                                                'file_size' => '1.8MB',
                                                'uploaded_by' => 1,
                                                'created_at' => date('Y-m-d H:i:s', strtotime('-1 week')),
                                                'stored_filename' => 'sample_policy.pdf'
                                            ],
                                            [
                                                'policy_name' => 'Code of Conduct',
                                                'policy_type' => 'Ethics',
                                                'file_size' => '1.2MB',
                                                'uploaded_by' => 1,
                                                'created_at' => date('Y-m-d H:i:s', strtotime('-2 weeks')),
                                                'stored_filename' => 'sample_policy.pdf'
                                            ]
                                        ];
                                    } else {
                                        // Get column info for better error diagnosis
                                        echo "<!-- policy_documents columns: ";
                                        $columns = $pdo->query("DESCRIBE policy_documents")->fetchAll(PDO::FETCH_COLUMN);
                                        echo implode(', ', $columns);
                                        echo " -->";
                                        
                                        // First try without the status filter to see if there are any documents at all
                                        $count_stmt = $pdo->query("SELECT COUNT(*) FROM policy_documents");
                                        $total_docs = $count_stmt->fetchColumn();
                                        
                                        echo "<!-- Total documents in policy_documents: " . $total_docs . " -->";
                                        
                                        // Decide which query to use based on show_all_documents setting
                                        if ($show_all_documents) {
                                            $policy_stmt = $pdo->prepare("SELECT * FROM policy_documents ORDER BY created_at DESC");
                                            $policy_stmt->execute();
                                            $policy_documents = $policy_stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if ($total_docs > 0 && count($policy_documents) > 0) {
                                                echo '<div class="alert alert-info">Showing all policy documents, including those pending approval.</div>';
                                            }
                                        } else {
                                            // Only show approved documents
                                            $policy_stmt = $pdo->prepare("SELECT * FROM policy_documents WHERE status = 'approved' ORDER BY created_at DESC");
                                            $policy_stmt->execute();
                                            $policy_documents = $policy_stmt->fetchAll(PDO::FETCH_ASSOC);
                                        }
                                        
                                        echo "<!-- Documents found: " . count($policy_documents) . " -->";
                                    }
                                    
                                    if (!empty($policy_documents)) {
                                        echo '<div class="table-responsive">';
                                        echo '<table class="table table-striped document-table">';
                                        echo '<thead>';
                                        echo '<tr>';
                                        echo '<th>Policy Name</th>';
                                        echo '<th>Type</th>';
                                        echo '<th>File Size</th>';
                                        echo '<th>Uploaded By</th>';
                                        echo '<th>Date</th>';
                                        if ($show_all_documents && isset($columns) && in_array('status', $columns)) {
                                            echo '<th>Status</th>';
                                        }
                                        echo '<th>Actions</th>';
                                        echo '</tr>';
                                        echo '</thead>';
                                        echo '<tbody>';
                                        
                                        foreach ($policy_documents as $doc) {
                                            echo '<tr>';
                                            echo '<td>' . htmlspecialchars($doc['policy_name']) . '</td>';
                                            echo '<td>' . htmlspecialchars($doc['policy_type']) . '</td>';
                                            echo '<td>' . htmlspecialchars($doc['file_size']) . '</td>';
                                            
                                            // Get uploader name
                                            $uploader_name = "Admin";
                                            if (!empty($doc['uploaded_by'])) {
                                                $uploader_stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                                                $uploader_stmt->execute([$doc['uploaded_by']]);
                                                $uploader = $uploader_stmt->fetch(PDO::FETCH_ASSOC);
                                                if ($uploader) {
                                                    $uploader_name = $uploader['username'];
                                                }
                                            }
                                            
                                            echo '<td>' . htmlspecialchars($uploader_name) . '</td>';
                                            echo '<td>' . date('M d, Y', strtotime($doc['created_at'])) . '</td>';
                                            
                                            // Show status if showing all documents
                                            if ($show_all_documents && isset($columns) && in_array('status', $columns) && isset($doc['status'])) {
                                                $status_class = '';
                                                switch ($doc['status']) {
                                                    case 'approved':
                                                        $status_class = 'badge-success';
                                                        break;
                                                    case 'pending':
                                                        $status_class = 'badge-warning';
                                                        break;
                                                    case 'rejected':
                                                        $status_class = 'badge-danger';
                                                        break;
                                                    default:
                                                        $status_class = 'badge-secondary';
                                                }
                                                echo '<td><span class="badge ' . $status_class . '">' . htmlspecialchars($doc['status']) . '</span></td>';
                                            }
                                            
                                            echo '<td>';
                                            
                                            // Only enable download for approved documents or if showing all
                                            $can_download = $show_all_documents || (!isset($doc['status']) || $doc['status'] == 'approved');
                                            
                                            if ($can_download) {
                                                // Check if the file exists and create the directories if they don't
                                                $upload_dir = 'uploads/documents/policy/';
                                                if (!file_exists($upload_dir) && !is_sample_data($doc)) {
                                                    mkdir($upload_dir, 0777, true);
                                                }
                                                
                                                // Construct the file path
                                                if (isset($doc['stored_filename'])) {
                                                    // For real database entries
                                                    $file_path = $upload_dir . htmlspecialchars($doc['stored_filename']);
                                                    
                                                    // If the specific file doesn't exist, provide a download handler
                                                    if (!file_exists($file_path) || is_sample_data($doc)) {
                                                        $file_path = 'download_policy.php?id=' . (isset($doc['id']) ? $doc['id'] : 'sample');
                                                    }
                                                } else {
                                                    // Fallback for demo data
                                                    $file_path = 'sample_documents/policy_sample.pdf';
                                                }
                                                
                                                echo '<a href="' . $file_path . '" target="_blank" class="btn btn-sm btn-primary download-doc" data-doc-name="' . htmlspecialchars($doc['policy_name']) . '"><i class="fas fa-download"></i> Download</a>';
                                            } else {
                                                echo '<button class="btn btn-sm btn-secondary" disabled><i class="fas fa-lock"></i> Not Available</button>';
                                            }
                                            
                                            echo '</td>';
                                            echo '</tr>';
                                        }
                                        
                                        echo '</tbody>';
                                        echo '</table>';
                                        echo '</div>';
                                    } else {
                                        // Try querying without the status filter to see what statuses exist
                                        if (isset($check_table) && $check_table->rowCount() > 0) {
                                            $all_status_stmt = $pdo->query("SELECT DISTINCT status FROM policy_documents");
                                            $statuses = $all_status_stmt->fetchAll(PDO::FETCH_COLUMN);
                                            
                                            echo "<!-- Available statuses: " . implode(', ', $statuses) . " -->";
                                            
                                            // If there are documents with different statuses, show a message
                                            if (!empty($statuses)) {
                                                echo '<div class="alert alert-info">';
                                                echo 'There are policy documents in the system, but none are currently approved. Available statuses: ' . implode(', ', $statuses);
                                                echo '</div>';
                                            }
                                        }
                                        
                                        // No documents placeholder
                                        echo '<div class="no-documents">';
                                        echo '<i class="fas fa-folder-open"></i>';
                                        echo '<p>No policy documents available</p>';
                                        echo '</div>';
                                    }
                                } catch (PDOException $e) {
                                    echo '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
                                } catch (Exception $e) {
                                    echo '<div class="alert alert-warning">' . $e->getMessage() . '</div>';
                                }
                                ?>
                            </div>
                            
                            <div class="documents-container" id="officialDocuments" style="display: none;">
                                <!-- No documents placeholder -->
                                <div class="no-documents">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No official documents available</p>
                                </div>
                            </div>
                            
                            <div class="documents-container" id="personalDocuments" style="display: none;">
                                <!-- Document upload section -->
                                <div class="mb-4">
                                    <h5 class="form-section-title">Upload New Document</h5>
                                    <form id="document-upload-form" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="document_type">Document Type</label>
                                                    <select class="form-control" id="document_type" name="document_type" required>
                                                        <option value="">Select Document Type</option>
                                                        <option value="aadhar_card">Aadhar Card</option>
                                                        <option value="pan_card">PAN Card</option>
                                                        <option value="passport">Passport</option>
                                                        <option value="driving_license">Driving License</option>
                                                        <option value="voter_id">Voter ID</option>
                                                        <option value="education_certificate">Education Certificate</option>
                                                        <option value="experience_certificate">Experience Certificate</option>
                                                        <option value="others">Others</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="document_file">Upload File</label>
                                                    <div class="custom-file">
                                                        <input type="file" class="custom-file-input" id="document_file" name="document_file" required>
                                                        <label class="custom-file-label" for="document_file">Choose file</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <button type="submit" class="btn btn-primary">Upload Document</button>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- No documents placeholder -->
                                <div class="no-documents">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No personal documents uploaded yet</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include JS files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="js/supervisor/dashboard.js"></script>
    
    <script>
        $(document).ready(function() {
            // Tab switching functionality
            document.querySelectorAll('.profile-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active class from all tabs and sections
                    document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.profile-section').forEach(s => s.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding section
                    tab.classList.add('active');
                    document.getElementById(tab.dataset.tab).classList.add('active');
                    
                    // Scroll tab into view if not fully visible (for mobile)
                    const tabsContainer = document.querySelector('.profile-tabs');
                    const tabRect = tab.getBoundingClientRect();
                    const containerRect = tabsContainer.getBoundingClientRect();
                    
                    if (tabRect.right > containerRect.right) {
                        tabsContainer.scrollLeft += (tabRect.right - containerRect.right + 20);
                    } else if (tabRect.left < containerRect.left) {
                        tabsContainer.scrollLeft -= (containerRect.left - tabRect.left + 20);
                    }
                });
            });
            
            // Handle profile picture upload
            $('#profile-picture-edit, #profile-image-preview').click(function() {
                $('#profile-upload').click();
            });
            
            // Show image preview when file is selected
            $('#profile-upload').change(function() {
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    
                    reader.onload = function(e) {
                        $('#profile-image-preview').attr('src', e.target.result);
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
            
            // Switch to edit tab on Edit Profile button click
            $('#editProfileBtn').click(function() {
                $('.profile-tab[data-tab="personal"]').click();
                $('html, body').animate({
                    scrollTop: $("#personal").offset().top - 50
                }, 500);
            });
            
            // Switch to password tab on Change Password button click
            $('#changePasswordBtn').click(function() {
                $('.profile-tab[data-tab="security"]').click();
                $('html, body').animate({
                    scrollTop: $("#security").offset().top - 50
                }, 500);
            });
            
            // Password validation
            $('#password-form').submit(function(e) {
                var newPassword = $('#new_password').val();
                var confirmPassword = $('#confirm_password').val();
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New password and confirmation do not match!');
                    return false;
                }
                
                // Check password strength
                if (newPassword.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    return false;
                }
                
                var hasNumber = /\d/.test(newPassword);
                var hasLetter = /[a-zA-Z]/.test(newPassword);
                var hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(newPassword);
                
                if (!hasNumber || !hasLetter || !hasSpecial) {
                    e.preventDefault();
                    alert('Password must include at least one letter, one number, and one special character.');
                    return false;
                }
                
                return true;
            });
            
            // Calculate profile completion percentage
            function calculateProfileCompletion() {
                const fields = [
                    'full_name', 'email', 'phone', 'emergency_contact', 
                    'address', 'bio'
                ];
                
                let completed = 0;
                let hasProfilePic = $('#profile-image-preview').attr('src') !== 'images/default-profile.png';
                
                if (hasProfilePic) completed++;
                
                fields.forEach(field => {
                    if ($('#' + field).val().trim() !== '') {
                        completed++;
                    }
                });
                
                const percentage = Math.floor((completed / (fields.length + 1)) * 100);
                $('.profile-completion .progress-bar').css('width', percentage + '%').attr('aria-valuenow', percentage);
                
                return percentage;
            }
            
            // Document tabs functionality with improved responsiveness
            function initializeDocumentTabs() {
                const tabs = document.querySelectorAll('.doc-tab');
                const container = document.querySelector('.document-type-tabs');
                
                tabs.forEach(tab => {
                    tab.addEventListener('click', () => {
                        // Remove active class from all tabs
                        tabs.forEach(t => t.classList.remove('active'));
                        // Add active class to clicked tab
                        tab.classList.add('active');
                        
                        // Scroll tab into view if not fully visible
                        const tabRect = tab.getBoundingClientRect();
                        const containerRect = container.getBoundingClientRect();
                        
                        if (tabRect.right > containerRect.right) {
                            container.scrollLeft += (tabRect.right - containerRect.right + 20);
                        } else if (tabRect.left < containerRect.left) {
                            container.scrollLeft -= (containerRect.left - tabRect.left + 20);
                        }
                        
                        // Hide all containers
                        document.querySelectorAll('.documents-container').forEach(container => {
                            container.style.display = 'none';
                        });

                        // Show appropriate container
                        const docType = tab.dataset.doctype;
                        document.getElementById(`${docType}Documents`).style.display = 'block';
                        
                        // Add some animation for smooth transition
                        document.getElementById(`${docType}Documents`).style.opacity = '0';
                        setTimeout(() => {
                            document.getElementById(`${docType}Documents`).style.opacity = '1';
                        }, 50);
                    });
                });
            }
            
            // Initialize document tabs
            initializeDocumentTabs();
            
            // Run calculation on page load
            calculateProfileCompletion();
            
            // Update calculations on form changes
            $('.tab-pane input, .tab-pane textarea, .tab-pane select').on('change', function() {
                calculateProfileCompletion();
            });
            
            // Education form functionality
            let educationIndex = <?php echo count($education_data ?? []); ?>;  // Start with existing count
            
            $(".add-education-btn").click(function() {
                const degree = $("#degree").val().trim();
                const institution = $("#institution").val().trim();
                const year = $("#year").val().trim();
                const score = $("#score").val().trim();
                
                if (degree && institution && year) {
                    // Add to table for display
                    const $newRow = $(`
                        <tr data-index="${educationIndex}">
                            <td>${degree}</td>
                            <td>${institution}</td>
                            <td>${year}</td>
                            <td>${score || '-'}</td>
                            <td>
                                <button type="button" class="action-btn edit-btn" data-index="${educationIndex}"><i class="fas fa-edit"></i></button>
                                <button type="button" class="action-btn delete-btn" data-index="${educationIndex}"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    `);
                    
                    $("#educationTableBody").append($newRow);
                    
                    // Add hidden inputs for form submission
                    $("#education-data-container").append(`
                        <input type="hidden" name="education[${educationIndex}][degree]" value="${degree}">
                        <input type="hidden" name="education[${educationIndex}][institution]" value="${institution}">
                        <input type="hidden" name="education[${educationIndex}][year]" value="${year}">
                        <input type="hidden" name="education[${educationIndex}][score]" value="${score || ''}">
                    `);
                    
                    // Increment index for next entry
                    educationIndex++;
                    
                    // Clear the form
                    $("#degree, #institution, #year, #score").val('');
                } else {
                    alert("Please fill in the required fields: Degree, Institution, and Year");
                }
            });
            
            // Delete education entry
            $(document).on('click', '.delete-btn', function() {
                const index = $(this).data('index');
                
                // Remove row from table
                $(`tr[data-index="${index}"]`).remove();
                
                // Remove hidden inputs
                $(`input[name^="education[${index}]"]`).remove();
            });
            
            // Edit education entry
            $(document).on('click', '.edit-btn', function() {
                const index = $(this).data('index');
                const $row = $(`tr[data-index="${index}"]`);
                
                // Get values from hidden inputs
                const degree = $(`input[name="education[${index}][degree]"]`).val();
                const institution = $(`input[name="education[${index}][institution]"]`).val();
                const year = $(`input[name="education[${index}][year]"]`).val();
                const score = $(`input[name="education[${index}][score]"]`).val();
                
                // Populate form fields
                $("#degree").val(degree);
                $("#institution").val(institution);
                $("#year").val(year);
                $("#score").val(score);
                
                // Remove the entry since we'll add an updated one
                $row.remove();
                $(`input[name^="education[${index}]"]`).remove();
                
                // Scroll to form
                $('html, body').animate({
                    scrollTop: $(".education-form").offset().top - 100
                }, 500);
            });
            
            // Save education data to main profile form and switch to personal tab
            $("#save-education-btn").click(function() {
                // Copy education data to main form
                const educationInputs = $("#education-data-container").html();
                $("#main-education-container").html(educationInputs);
                
                // Switch to personal tab
                $(".profile-tab[data-tab='personal']").click();
                alert("Education data prepared. Click 'Save Changes' on the Personal Info tab to save your complete profile.");
            });
            
            // Make sure education data is included in main form submission
            $("#profile-form").on("submit", function() {
                const educationInputs = $("#education-data-container").html();
                $("#main-education-container").html(educationInputs);
                return true;
            });
            
            // Document browse button functionality
            $('.custom-file-input').on('change', function() {
                const fileName = $(this).val().split('\\').pop();
                const label = $(this).next('.custom-file-label');
                
                if (fileName) {
                    // Truncate long filenames on smaller screens
                    if (window.innerWidth < 576 && fileName.length > 20) {
                        label.html(fileName.substring(0, 17) + '...');
                    } else {
                        label.html(fileName);
                    }
                } else {
                    label.html('Choose file');
                }
            });
            
            // Handle window resize for responsive adjustments
            $(window).resize(function() {
                // Recalculate and adjust anything that needs to be responsive on resize
                
                // Adjust file input label text
                $('.custom-file-input').each(function() {
                    const fileName = $(this).val().split('\\').pop();
                    const label = $(this).next('.custom-file-label');
                    
                    if (fileName) {
                        if (window.innerWidth < 576 && fileName.length > 20) {
                            label.html(fileName.substring(0, 17) + '...');
                        } else {
                            label.html(fileName);
                        }
                    }
                });
                
                // Check if tables need horizontal scrolling
                if (window.innerWidth <= 768) {
                    $('.education-table').css('display', 'block');
                } else {
                    $('.education-table').css('display', 'table');
                }
            });
            
            // Trigger initial resize adjustments
            $(window).trigger('resize');
            
            // Add form validation
            $('#profile-form').submit(function(e) {
                let isValid = true;
                const fullName = $('#full_name').val().trim();
                const email = $('#email').val().trim();
                
                // Validate required fields
                if (fullName === '') {
                    isValid = false;
                    $('#full_name').addClass('is-invalid');
                } else {
                    $('#full_name').removeClass('is-invalid');
                }
                
                if (email === '') {
                    isValid = false;
                    $('#email').addClass('is-invalid');
                } else if (!isValidEmail(email)) {
                    isValid = false;
                    $('#email').addClass('is-invalid');
                } else {
                    $('#email').removeClass('is-invalid');
                }
                
                // If validation fails, prevent form submission
                if (!isValid) {
                    e.preventDefault();
                    // Scroll to the first invalid field
                    const firstInvalid = $('.is-invalid').first();
                    if (firstInvalid.length) {
                        $('html, body').animate({
                            scrollTop: firstInvalid.offset().top - 100
                        }, 500);
                    }
                }
                
                return isValid;
            });
            
            // Email validation helper function
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
            
            // Add validation visual feedback
            $('#full_name, #email').on('input', function() {
                if ($(this).val().trim() !== '') {
                    $(this).removeClass('is-invalid');
                }
            });
            
            // Security tab password validation
            $('#password-form').submit(function(e) {
                let isValid = true;
                const currentPassword = $('#current_password').val().trim();
                const newPassword = $('#new_password').val().trim();
                const confirmPassword = $('#confirm_password').val().trim();
                
                // Clear previous validation states
                $('#current_password, #new_password, #confirm_password').removeClass('is-invalid');
                
                // Validate all password fields are filled
                if (currentPassword === '') {
                    $('#current_password').addClass('is-invalid');
                    isValid = false;
                }
                
                if (newPassword === '') {
                    $('#new_password').addClass('is-invalid');
                    isValid = false;
                }
                
                if (confirmPassword === '') {
                    $('#confirm_password').addClass('is-invalid');
                    isValid = false;
                }
                
                // Check if password meets requirements
                if (newPassword !== '' && newPassword.length < 8) {
                    $('#new_password').addClass('is-invalid');
                    alert('Password must be at least 8 characters long.');
                    isValid = false;
                }
                
                // Check if passwords match
                if (newPassword !== confirmPassword) {
                    $('#confirm_password').addClass('is-invalid');
                    alert('New password and confirmation do not match!');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    // Scroll to the first invalid field
                    const firstInvalid = $('.is-invalid').first();
                    if (firstInvalid.length) {
                        $('html, body').animate({
                            scrollTop: firstInvalid.offset().top - 100
                        }, 500);
                    }
                }
                
                return isValid;
            });
            
            // Reset validation state on input
            $('#current_password, #new_password, #confirm_password').on('input', function() {
                $(this).removeClass('is-invalid');
            });
            
            // Auto-save notification preferences
            function setupAutoSave() {
                // Add styles for auto-save indicator
                $('<style>.save-indicator{position:fixed;bottom:20px;right:20px;background:#323232;color:white;padding:10px 20px;border-radius:4px;z-index:1000;}.save-indicator.saved{background:#28a745;}.save-indicator.error{background:#dc3545;}</style>').appendTo('head');
                
                // Get form reference
                const notificationForm = $('form[action="update_preferences.php"]');
                
                // Add change handler to all form controls
                notificationForm.find('input[type="checkbox"], select').on('change', function() {
                    const formData = notificationForm.serialize();
                    
                    // Show saving indicator
                    let saveIndicator = $('.save-indicator');
                    if (saveIndicator.length === 0) {
                        saveIndicator = $('<div class="save-indicator">Saving...</div>');
                        $('body').append(saveIndicator);
                    } else {
                        saveIndicator.removeClass('saved error').text('Saving...');
                    }
                    
                    // AJAX save preferences
                    $.ajax({
                        url: 'update_preferences.php',
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            saveIndicator.text('Saved!').addClass('saved');
                            setTimeout(function() {
                                saveIndicator.fadeOut(function() {
                                    $(this).remove();
                                });
                            }, 2000);
                        },
                        error: function() {
                            saveIndicator.text('Error saving!').addClass('error');
                            setTimeout(function() {
                                saveIndicator.fadeOut(function() {
                                    $(this).remove();
                                });
                            }, 2000);
                        }
                    });
                });
                
                // Prevent form submission - since we're auto-saving
                notificationForm.on('submit', function(e) {
                    e.preventDefault();
                    return false;
                });
            }
            
            // Initialize auto-save for preferences
            setupAutoSave();
            
            // Document download handling
            $(document).on('click', '.download-doc', function(e) {
                // Get document name
                const docName = $(this).data('doc-name');
                
                // Show a download toast notification
                showDownloadToast(docName);
                
                // Track download activity
                trackDocumentDownload(docName);
            });
            
            // Function to show a toast notification
            function showDownloadToast(docName) {
                // Create toast element if it doesn't exist
                if ($('#download-toast').length === 0) {
                    $('body').append(`
                        <div id="download-toast" style="
                            position: fixed;
                            bottom: 20px;
                            right: 20px;
                            background-color: #4CAF50;
                            color: white;
                            padding: 15px 25px;
                            border-radius: 8px;
                            z-index: 9999;
                            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                            display: none;
                            font-weight: 500;
                        ">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span id="toast-message"></span>
                        </div>
                    `);
                }
                
                // Set message and show toast
                $('#toast-message').text(`Downloading "${docName}" document...`);
                $('#download-toast').fadeIn(300);
                
                // Hide toast after 3 seconds
                setTimeout(function() {
                    $('#download-toast').fadeOut(300);
                }, 3000);
            }
            
            // Function to track document downloads (can be expanded later)
            function trackDocumentDownload(docName) {
                // This could send data to an analytics endpoint in the future
                console.log('Download tracked:', docName);
            }
        });
    </script>
</body>
</html> 