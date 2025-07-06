<?php
session_start();
require_once 'config.php';

// Check authentication and HR role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}

// Check if employee ID is provided
if (!isset($_GET['id'])) {
    header('Location: employee.php');
    exit();
}

$employeeId = $_GET['id'];

// Fetch employee details
$query = "
    SELECT * FROM users 
    WHERE id = :id 
    AND deleted_at IS NULL
";
$stmt = $pdo->prepare($query);
$stmt->execute(['id' => $employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// If employee not found, redirect back
if (!$employee) {
    header('Location: employee.php');
    exit();
}

// Add this after the existing employee fetch query
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create uploads directory if it doesn't exist
        $uploadDir = 'uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Get existing documents
        $existingDocs = json_decode($employee['documents'] ?? '{}', true) ?: [];
        
        // Handle file uploads
        if (!empty($_FILES['documents']['name'])) {
            foreach ($_FILES['documents']['name'] as $docType => $filename) {
                if (!empty($filename)) {
                    $tmpName = $_FILES['documents']['tmp_name'][$docType];
                    $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    // Validate file extension
                    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                    if (!in_array($fileExt, $allowedExts)) {
                        throw new Exception("Invalid file type for $docType");
                    }
                    
                    // Generate unique filename
                    $newFilename = uniqid($employeeId . '_' . $docType . '_') . '.' . $fileExt;
                    $destination = $uploadDir . $newFilename;
                    
                    // Delete old file if exists
                    if (isset($existingDocs[$docType]['filename'])) {
                        $oldFile = $uploadDir . $existingDocs[$docType]['filename'];
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    
                    // Move uploaded file
                    if (move_uploaded_file($tmpName, $destination)) {
                        $existingDocs[$docType] = [
                            'filename' => $newFilename,
                            'original_name' => $filename,
                            'uploaded_at' => date('Y-m-d H:i:s')
                        ];
                    }
                }
            }
        }

        // Handle document deletions
        if (isset($_POST['delete_documents']) && is_array($_POST['delete_documents'])) {
            foreach ($_POST['delete_documents'] as $docType) {
                if (isset($existingDocs[$docType])) {
                    // Delete file
                    $fileToDelete = $uploadDir . $existingDocs[$docType]['filename'];
                    if (file_exists($fileToDelete)) {
                        unlink($fileToDelete);
                    }
                    // Remove from array
                    unset($existingDocs[$docType]);
                }
            }
        }

        // Update database with new document information
        $documentsJson = json_encode($existingDocs);
        
        // Add documents to your existing UPDATE query
        $sql = "UPDATE users SET 
            username = :username,
            email = :email,
            phone = :phone,
            dob = :dob,
            gender = :gender,
            role = :role,
            position = :position,
            designation = :designation,
            joining_date = :joining_date,
            reporting_manager = :reporting_manager,
            address = :address,
            city = :city,
            state = :state,
            country = :country,
            postal_code = :postal_code,
            emergency_contact_name = :emergency_contact_name,
            emergency_contact_phone = :emergency_contact_phone,
            bank_details = :bank_details,
            documents = :documents,
            base_salary = :base_salary,
            allowances = :allowances,
            deductions = :deductions
            WHERE id = :id";
            
        $stmt = $pdo->prepare($sql);
        $params = [
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'dob' => $_POST['dob'],
            'gender' => $_POST['gender'],
            'role' => $_POST['role'],
            'position' => $_POST['position'],
            'designation' => $_POST['designation'],
            'joining_date' => $_POST['joining_date'],
            'reporting_manager' => $_POST['reporting_manager'],
            'address' => $_POST['address'],
            'city' => $_POST['city'],
            'state' => $_POST['state'],
            'country' => $_POST['country'],
            'postal_code' => $_POST['postal_code'],
            'emergency_contact_name' => $_POST['emergency_contact_name'],
            'emergency_contact_phone' => $_POST['emergency_contact_phone'],
            'bank_details' => json_encode([
                'account_holder' => $_POST['bank_details']['account_holder'] ?? '',
                'bank_name' => $_POST['bank_details']['bank_name'] ?? '',
                'account_number' => $_POST['bank_details']['account_number'] ?? '',
                'ifsc_code' => $_POST['bank_details']['ifsc_code'] ?? '',
                'branch_name' => $_POST['bank_details']['branch_name'] ?? '',
                'account_type' => $_POST['bank_details']['account_type'] ?? ''
            ]),
            'documents' => $documentsJson,
            'base_salary' => $_POST['base_salary'],
            'allowances' => json_encode($_POST['allowances']),
            'deductions' => json_encode($_POST['deductions']),
            'id' => $employeeId
        ];
        $stmt->execute($params);

        $success_message = "Employee details and documents updated successfully!";
    } catch (Exception $e) {
        $error_message = "Error updating employee details: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Details - <?php echo htmlspecialchars($employee['username']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4F46E5;
            --primary-dark: #4338CA;
            --text-dark: #1F2937;
            --text-light: #6B7280;
            --bg-light: #F3F4F6;
            --bg-white: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            --border-radius: 16px;
            --spacing-sm: 12px;
            --spacing-md: 18px;
            --spacing-lg: 24px;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
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
            color: var(--text-dark);
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
            color: var(--primary-color);
            background-color: #F3F4FF;
        }

        .nav-link.active {
            background-color: #F3F4FF;
            font-weight: 500;
        }

        .nav-link:hover i,
        .nav-link.active i {
            color: var(--primary-color);
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

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            width: calc(100% - var(--sidebar-width));
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        .container {
            width: 100%;
            padding: var(--spacing-lg);
            margin: 0;
            max-width: none;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--bg-white);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            color: var(--text-dark);
            text-decoration: none;
            transition: all 0.3s ease;
            margin-bottom: var(--spacing-lg);
        }

        .back-button:hover {
            background: var(--bg-light);
            transform: translateX(-5px);
        }

        .profile-header {
            width: 100%;
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: var(--spacing-lg);
        }

        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 20px;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            padding: 3px;
            background: linear-gradient(45deg, var(--primary-color), #818CF8);
        }

        .profile-info h1 {
            font-size: 2em;
            color: var(--text-dark);
            margin-bottom: var(--spacing-sm);
        }

        .profile-info p {
            color: var(--text-light);
            margin-bottom: var(--spacing-sm);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: var(--spacing-lg);
            width: 100%;
        }

        .detail-section {
            width: 100%;
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow);
        }

        .section-title {
            font-size: 1.2em;
            color: var(--primary-color);
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-sm);
            border-bottom: 2px solid rgba(79, 70, 229, 0.1);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .info-group {
            margin-bottom: var(--spacing-md);
        }

        .info-label {
            font-size: 0.9em;
            color: var(--text-light);
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 1em;
            color: var(--text-dark);
            padding: 8px 12px;
            background: #F9FAFB;
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-active {
            color: #059669;
            background: rgba(5, 150, 105, 0.1);
        }

        .status-inactive {
            color: #DC2626;
            background: rgba(220, 38, 38, 0.1);
        }

        @media (max-width: 1200px) {
            .detail-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar.show {
                transform: translateX(0);
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                padding: var(--spacing-md);
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .detail-section {
                padding: var(--spacing-md);
            }
        }

        .info-value input,
        .info-value select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1em;
        }

        .edit-controls {
            margin-top: 24px;
            text-align: right;
        }

        .btn-save {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .success-message {
            background: #dcfce7;
            color: #166534;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
        }

        .document-list {
            margin-top: 10px;
            margin-bottom: 20px;
        }

        .document-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.25rem;
        }

        .document-main {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .document-header {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .document-icon {
            width: 24px;
            height: 24px;
            min-width: 24px;
        }

        .document-icon i {
            font-size: 1.25rem;
            color: #dc2626;
        }

        .document-info {
            flex: 1;
        }

        .document-name {
            font-weight: 500;
            color: #1a202c;
            font-size: 0.95rem;
        }

        .document-type {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .document-meta {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-item i {
            font-size: 1rem;
            color: #64748b;
        }

        .action-links {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: auto;
        }

        .action-link {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: #4F46E5;
            text-decoration: none;
            font-size: 0.85rem;
        }

        .action-link.download {
            color: #10B981;
        }

        .action-link i {
            font-size: 1rem;
        }

        .no-documents {
            text-align: center;
            padding: 30px;
            color: #64748b;
        }

        .no-documents i {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .no-documents p {
            margin: 0;
            font-size: 0.95em;
        }

        .document-upload-form {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
            border: 1px solid #e2e8f0;
        }

        .form-grid {
            display: grid;
            gap: 15px;
        }

        .btn-secondary {
            background: #94a3b8;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            margin-left: 10px;
        }

        .btn-secondary:hover {
            background: #64748b;
        }

        /* Add to your existing style section */
        .salary-components {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 8px;
        }

        .component-item {
            background: #f8fafc;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .component-item label {
            display: block;
            font-size: 0.8em;
            color: #64748b;
            margin-bottom: 4px;
        }

        .component-item input {
            width: 100%;
            padding: 4px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .total-salary {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed #e2e8f0;
        }

        .salary-summary {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9em;
            color: #64748b;
        }

        .summary-item.total {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-weight: 600;
            color: #1e293b;
        }

        .amount {
            font-family: monospace;
        }

        .hr-documents-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .section-header {
            border-bottom: 1px solid #edf2f7;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #1a202c;
            font-size: 1.1rem;
            font-weight: 500;
            margin: 0;
        }

        .section-title i {
            color: #4F46E5;
            font-size: 1.2rem;
        }

        .documents-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .document-item {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s ease;
        }

        .document-item:hover {
            background: #f1f5f9;
        }

        .document-icon {
            width: 40px;
            height: 40px;
            min-width: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .document-icon i.fa-file-pdf {
            color: #dc2626;
            font-size: 1.5rem;
        }

        .document-info {
            flex: 1;
            min-width: 0; /* Prevents text overflow */
        }

        .document-name {
            font-weight: 500;
            color: #1a202c;
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .document-type {
            color: #64748b;
            font-size: 0.85rem;
        }

        .document-meta {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-left: auto;
            padding-left: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.85rem;
        }

        .document-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-left: 1rem;
        }

        .btn-action {
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            color: #64748b;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-action.view {
            color: #4F46E5;
        }

        .btn-action.download {
            background: #4F46E5;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
        }

        .btn-action:hover {
            opacity: 0.9;
        }

        .no-documents {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-left: 8px;
        }

        .badge i {
            font-size: 14px;
        }

        .badge-success {
            background-color: #DEF7EC;
            color: #03543F;
        }

        .badge-warning {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .btn-acknowledge {
            background: #4F46E5;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            margin-left: 8px;
            transition: background-color 0.2s;
        }

        .btn-acknowledge:hover {
            background: #4338CA;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-warning {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .badge-success {
            background-color: #DEF7EC;
            color: #03543F;
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
            <a href="employee.php" class="nav-link active">
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
                <i class="bi bi-calendar-x-fill"></i>
                Leave Request
            </a>
            <a href="manage_leave_balance.php" class="nav-link">
                <i class="bi bi-briefcase-fill"></i>
                Recruitment
            </a>
            <a href="hr_travel_expenses.php" class="nav-link">
                <i class="bi bi-car-front-fill"></i>
                Travel Expenses
            </a>
            <a href="generate_agreement.php" class="nav-link">
                <i class="bi bi-chevron-contract"></i>
                Contracts
            </a>
            <a href="hr_password_reset.php" class="nav-link">
                <i class="bi bi-key-fill"></i>
                Password Reset
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
    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="message success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="message error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <a href="employee.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Employee List
        </a>

        <form method="POST" enctype="multipart/form-data">
            <!-- Profile Header Section -->
            <div class="profile-header">
                <img src="<?php echo $employee['profile_picture'] ?? 'default-avatar.png'; ?>" 
                     alt="Profile" class="profile-image">
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($employee['username']); ?></h1>
                    <p><?php echo htmlspecialchars($employee['designation']); ?></p>
                    <span class="status-badge status-<?php echo $employee['status'] === 'active' ? 'active' : 'inactive'; ?>">
                        <?php echo ucfirst($employee['status']); ?>
                    </span>
                </div>
            </div>

            <div class="detail-grid">
                <!-- Basic Information Section -->
                <div class="detail-section">
                    <div class="section-title">
                        <i class="fas fa-user"></i>
                        Basic Information
                    </div>
                    <div class="info-group">
                        <div class="info-label">Employee ID</div>
                        <div class="info-value"><?php echo htmlspecialchars($employee['unique_id']); ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Username</div>
                        <div class="info-value">
                            <input type="text" name="username" value="<?php echo htmlspecialchars($employee['username']); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Email</div>
                        <div class="info-value">
                            <input type="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Phone</div>
                        <div class="info-value">
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($employee['phone']); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value">
                            <input type="date" name="dob" value="<?php echo $employee['dob'] ? date('Y-m-d', strtotime($employee['dob'])) : ''; ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Gender</div>
                        <div class="info-value">
                            <select name="gender">
                                <option value="Male" <?php echo $employee['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $employee['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo $employee['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Work Information Section -->
                <div class="detail-section">
                    <div class="section-title">
                        <i class="fas fa-briefcase"></i>
                        Work Information
                    </div>
                    <div class="info-group">
                        <div class="info-label">Role</div>
                        <div class="info-value">
                            <select name="role" class="form-control">
                                <option value="">Select Role...</option>
                                <option value="admin" <?php echo $employee['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="HR" <?php echo $employee['role'] === 'HR' ? 'selected' : ''; ?>>HR</option>
                                <option value="Senior Manager (Studio)" <?php echo $employee['role'] === 'Senior Manager (Studio)' ? 'selected' : ''; ?>>Senior Manager (Studio)</option>
                                <option value="Senior Manager (Site)" <?php echo $employee['role'] === 'Senior Manager (Site)' ? 'selected' : ''; ?>>Senior Manager (Site)</option>
                                <option value="Senior Manager (Marketing)" <?php echo $employee['role'] === 'Senior Manager (Marketing)' ? 'selected' : ''; ?>>Senior Manager (Marketing)</option>
                                <option value="Senior Manager (Sales)" <?php echo $employee['role'] === 'Senior Manager (Sales)' ? 'selected' : ''; ?>>Senior Manager (Sales)</option>
                                <option value="Senior Manager (Purchase)" <?php echo $employee['role'] === 'Senior Manager (Purchase)' ? 'selected' : ''; ?>>Senior Manager (Purchase)</option>
                                <option value="Design Team" <?php echo $employee['role'] === 'Design Team' ? 'selected' : ''; ?>>Design Team</option>
                                <option value="Working Team" <?php echo $employee['role'] === 'Working Team' ? 'selected' : ''; ?>>Working Team</option>
                                <option value="Draughtsman" <?php echo $employee['role'] === 'Draughtsman' ? 'selected' : ''; ?>>Draughtsman</option>
                                <option value="3D Designing Team" <?php echo $employee['role'] === '3D Designing Team' ? 'selected' : ''; ?>>3D Designing Team</option>
                                <option value="Studio Trainees" <?php echo $employee['role'] === 'Studio Trainees' ? 'selected' : ''; ?>>Studio Trainees</option>
                                <option value="Business Developer" <?php echo $employee['role'] === 'Business Developer' ? 'selected' : ''; ?>>Business Developer</option>
                                <option value="Social Media Manager" <?php echo $employee['role'] === 'Social Media Manager' ? 'selected' : ''; ?>>Social Media Manager</option>
                                <option value="Site Manager" <?php echo $employee['role'] === 'Site Manager' ? 'selected' : ''; ?>>Site Manager</option>
                                <option value="Site Coordinator" <?php echo $employee['role'] === 'Site Coordinator' ? 'selected' : ''; ?>>Site Coordinator</option>
                                <option value="Site Supervisor" <?php echo $employee['role'] === 'Site Supervisor' ? 'selected' : ''; ?>>Site Supervisor</option>
                                <option value="Site Trainee" <?php echo $employee['role'] === 'Site Trainee' ? 'selected' : ''; ?>>Site Trainee</option>
                                <option value="Relationship Manager" <?php echo $employee['role'] === 'Relationship Manager' ? 'selected' : ''; ?>>Relationship Manager</option>
                                <option value="Sales Manager" <?php echo $employee['role'] === 'Sales Manager' ? 'selected' : ''; ?>>Sales Manager</option>
                                <option value="Sales Consultant" <?php echo $employee['role'] === 'Sales Consultant' ? 'selected' : ''; ?>>Sales Consultant</option>
                                <option value="Field Sales Representative" <?php echo $employee['role'] === 'Field Sales Representative' ? 'selected' : ''; ?>>Field Sales Representative</option>
                                <option value="Purchase Manager" <?php echo $employee['role'] === 'Purchase Manager' ? 'selected' : ''; ?>>Purchase Manager</option>
                                <option value="Purchase Executive" <?php echo $employee['role'] === 'Purchase Executive' ? 'selected' : ''; ?>>Purchase Executive</option>
                                <option value="Sales" <?php echo $employee['role'] === 'Sales' ? 'selected' : ''; ?>>Sales</option>
                                <option value="Purchase" <?php echo $employee['role'] === 'Purchase' ? 'selected' : ''; ?>>Purchase</option>
                                <option value="Social Media Marketing" <?php echo $employee['role'] === 'Social Media Marketing' ? 'selected' : ''; ?>>Social Media Marketing</option>
                                <option value="Graphic Designer" <?php echo $employee['role'] === 'Graphic Designer' ? 'selected' : ''; ?>>Graphic Designer</option>
                            </select>
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Position</div>
                        <div class="info-value">
                            <select name="position" class="form-control">
                                <option value="">Select Position...</option>
                                <option value="Executive" <?php echo $employee['position'] === 'Executive' ? 'selected' : ''; ?>>Executive</option>
                                <option value="Manager" <?php echo $employee['position'] === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                                <option value="Senior Manager" <?php echo $employee['position'] === 'Senior Manager' ? 'selected' : ''; ?>>Senior Manager</option>
                                <option value="Team Lead" <?php echo $employee['position'] === 'Team Lead' ? 'selected' : ''; ?>>Team Lead</option>
                                <option value="Supervisor" <?php echo $employee['position'] === 'Supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                <option value="Trainee" <?php echo $employee['position'] === 'Trainee' ? 'selected' : ''; ?>>Trainee</option>
                                <option value="Consultant" <?php echo $employee['position'] === 'Consultant' ? 'selected' : ''; ?>>Consultant</option>
                                <option value="Representative" <?php echo $employee['position'] === 'Representative' ? 'selected' : ''; ?>>Representative</option>
                                <option value="Developer" <?php echo $employee['position'] === 'Developer' ? 'selected' : ''; ?>>Developer</option>
                                <option value="Designer" <?php echo $employee['position'] === 'Designer' ? 'selected' : ''; ?>>Designer</option>
                                <option value="Administrator" <?php echo $employee['position'] === 'Administrator' ? 'selected' : ''; ?>>Administrator</option>
                                <option value="Graphic Designer" <?php echo $employee['position'] === 'Graphic Designer' ? 'selected' : ''; ?>>Graphic Designer</option>
                                <option value="Social Media Marketing" <?php echo $employee['position'] === 'Social Media Marketing' ? 'selected' : ''; ?>>Social Media Marketing</option>
                                <option value="Other" <?php echo $employee['position'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Designation</div>
                        <div class="info-value">
                            <select name="designation" class="form-control">
                                <option value="">Select Designation...</option>
                                <option value="Admin" <?php echo $employee['designation'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="HR Manager" <?php echo $employee['designation'] === 'HR Manager' ? 'selected' : ''; ?>>HR Manager</option>
                                <option value="Studio Manager" <?php echo $employee['designation'] === 'Studio Manager' ? 'selected' : ''; ?>>Studio Manager</option>
                                <option value="Site Manager" <?php echo $employee['designation'] === 'Site Manager' ? 'selected' : ''; ?>>Site Manager</option>
                                <option value="Marketing Manager" <?php echo $employee['designation'] === 'Marketing Manager' ? 'selected' : ''; ?>>Marketing Manager</option>
                                <option value="Sales Manager" <?php echo $employee['designation'] === 'Sales Manager' ? 'selected' : ''; ?>>Sales Manager</option>
                                <option value="Design Team Member" <?php echo $employee['designation'] === 'Design Team Member' ? 'selected' : ''; ?>>Design Team Member</option>
                                <option value="Working Team Member" <?php echo $employee['designation'] === 'Working Team Member' ? 'selected' : ''; ?>>Working Team Member</option>
                                <option value="Draughtsman" <?php echo $employee['designation'] === 'Draughtsman' ? 'selected' : ''; ?>>Draughtsman</option>
                                <option value="3D Designer" <?php echo $employee['designation'] === '3D Designer' ? 'selected' : ''; ?>>3D Designer</option>
                                <option value="Studio Trainee" <?php echo $employee['designation'] === 'Studio Trainee' ? 'selected' : ''; ?>>Studio Trainee</option>
                                <option value="Business Developer" <?php echo $employee['designation'] === 'Business Developer' ? 'selected' : ''; ?>>Business Developer</option>
                                <option value="Social Media Manager" <?php echo $employee['designation'] === 'Social Media Manager' ? 'selected' : ''; ?>>Social Media Manager</option>
                                <option value="Site Manager" <?php echo $employee['designation'] === 'Site Manager' ? 'selected' : ''; ?>>Site Manager</option>
                                <option value="Site Coordinator" <?php echo $employee['designation'] === 'Site Coordinator' ? 'selected' : ''; ?>>Site Coordinator</option>
                                <option value="Site Supervisor" <?php echo $employee['designation'] === 'Site Supervisor' ? 'selected' : ''; ?>>Site Supervisor</option>
                                <option value="Site Trainee" <?php echo $employee['designation'] === 'Site Trainee' ? 'selected' : ''; ?>>Site Trainee</option>
                                <option value="Relationship Manager" <?php echo $employee['designation'] === 'Relationship Manager' ? 'selected' : ''; ?>>Relationship Manager</option>
                                <option value="Sales Consultant" <?php echo $employee['designation'] === 'Sales Consultant' ? 'selected' : ''; ?>>Sales Consultant</option>
                                <option value="Field Sales Representative" <?php echo $employee['designation'] === 'Field Sales Representative' ? 'selected' : ''; ?>>Field Sales Representative</option>
                                <option value="Purchase Manager" <?php echo $employee['designation'] === 'Purchase Manager' ? 'selected' : ''; ?>>Purchase Manager</option>
                                <option value="Purchase Executive" <?php echo $employee['designation'] === 'Purchase Executive' ? 'selected' : ''; ?>>Purchase Executive</option>
                                <option value="Sales Executive" <?php echo $employee['designation'] === 'Sales Executive' ? 'selected' : ''; ?>>Sales Executive</option>
                                <option value="Graphic Designer" <?php echo $employee['designation'] === 'Graphic Designer' ? 'selected' : ''; ?>>Graphic Designer</option>
                                <option value="Social Media Marketing" <?php echo $employee['designation'] === 'Social Media Marketing' ? 'selected' : ''; ?>>Social Media Marketing</option>
                                <option value="Other" <?php echo $employee['designation'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Joining Date</div>
                        <div class="info-value">
                            <input type="date" name="joining_date" value="<?php echo $employee['joining_date'] ? date('Y-m-d', strtotime($employee['joining_date'])) : ''; ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Reporting Manager</div>
                        <div class="info-value">
                            <input type="text" name="reporting_manager" value="<?php echo htmlspecialchars($employee['reporting_manager'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="detail-section">
                    <div class="section-title">
                        <i class="fas fa-address-card"></i>
                        Contact Information
                    </div>
                    <div class="info-group">
                        <div class="info-label">Address</div>
                        <div class="info-value">
                            <input type="text" name="address" value="<?php echo htmlspecialchars($employee['address'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">City</div>
                        <div class="info-value">
                            <input type="text" name="city" value="<?php echo htmlspecialchars($employee['city'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">State</div>
                        <div class="info-value">
                            <input type="text" name="state" value="<?php echo htmlspecialchars($employee['state'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Country</div>
                        <div class="info-value">
                            <input type="text" name="country" value="<?php echo htmlspecialchars($employee['country'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Postal Code</div>
                        <div class="info-value">
                            <input type="text" name="postal_code" value="<?php echo htmlspecialchars($employee['postal_code'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact Section -->
                <div class="detail-section">
                    <div class="section-title">
                        <i class="fas fa-phone-alt"></i>
                        Emergency Contact
                    </div>
                    <div class="info-group">
                        <div class="info-label">Emergency Contact Name</div>
                        <div class="info-value">
                            <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($employee['emergency_contact_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Emergency Contact Phone</div>
                        <div class="info-value">
                            <input type="tel" name="emergency_contact_phone" value="<?php echo htmlspecialchars($employee['emergency_contact_phone'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Bank Account Details Section -->
                <div class="detail-section">
                    <div class="section-title">
                        <i class="fas fa-university"></i>
                        Bank Account Details
                    </div>
                    <div class="info-group">
                        <div class="info-label">Account Holder Name</div>
                        <div class="info-value">
                            <input type="text" 
                                   name="bank_details[account_holder]" 
                                   value="<?php echo htmlspecialchars(json_decode($employee['bank_details'] ?? '{}', true)['account_holder'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Bank Name</div>
                        <div class="info-value">
                            <input type="text" 
                                   name="bank_details[bank_name]" 
                                   value="<?php echo htmlspecialchars(json_decode($employee['bank_details'] ?? '{}', true)['bank_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Account Number</div>
                        <div class="info-value">
                            <input type="text" 
                                   name="bank_details[account_number]" 
                                   value="<?php echo htmlspecialchars(json_decode($employee['bank_details'] ?? '{}', true)['account_number'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">IFSC Code</div>
                        <div class="info-value">
                            <input type="text" 
                                   name="bank_details[ifsc_code]" 
                                   value="<?php echo htmlspecialchars(json_decode($employee['bank_details'] ?? '{}', true)['ifsc_code'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Branch Name</div>
                        <div class="info-value">
                            <input type="text" 
                                   name="bank_details[branch_name]" 
                                   value="<?php echo htmlspecialchars(json_decode($employee['bank_details'] ?? '{}', true)['branch_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Account Type</div>
                        <div class="info-value">
                            <select name="bank_details[account_type]">
                                <?php 
                                $accountType = json_decode($employee['bank_details'] ?? '{}', true)['account_type'] ?? '';
                                ?>
                                <option value="savings" <?php echo $accountType === 'savings' ? 'selected' : ''; ?>>Savings</option>
                                <option value="current" <?php echo $accountType === 'current' ? 'selected' : ''; ?>>Current</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="edit-controls">
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </div>
        </form>
        </div>
    </div>

    <!-- Add SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Add JavaScript for Sidebar functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');
        
        // Handle sidebar toggle click
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            sidebarToggle.classList.toggle('collapsed');
            
            // Store the state in localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
        
        // Check if sidebar was collapsed previously
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
            sidebarToggle.classList.add('collapsed');
        }
        
        // Add mobile detection for sidebar
        function checkMobile() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                sidebarToggle.classList.add('collapsed');
            } else if (!sidebarCollapsed) {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
                sidebarToggle.classList.remove('collapsed');
            }
        }
        
        // Initial check
        checkMobile();
        
        // Listen for window resize
        window.addEventListener('resize', checkMobile);
        
        // Role-Position-Designation Relationship
        const roleSelect = document.querySelector('select[name="role"]');
        const positionSelect = document.querySelector('select[name="position"]');
        const designationSelect = document.querySelector('select[name="designation"]');
        
        if (roleSelect && positionSelect && designationSelect) {
            // Map roles to appropriate positions and designations
            const roleMapping = {
                'admin': {
                    position: 'Administrator',
                    designation: 'Admin'
                },
                'HR': {
                    position: 'Manager',
                    designation: 'HR Manager'
                },
                'Senior Manager (Studio)': {
                    position: 'Senior Manager',
                    designation: 'Studio Manager'
                },
                'Senior Manager (Site)': {
                    position: 'Senior Manager',
                    designation: 'Site Manager'
                },
                'Senior Manager (Marketing)': {
                    position: 'Senior Manager',
                    designation: 'Marketing Manager'
                },
                'Senior Manager (Sales)': {
                    position: 'Senior Manager',
                    designation: 'Sales Manager'
                },
                'Design Team': {
                    position: 'Designer',
                    designation: 'Design Team Member'
                },
                'Working Team': {
                    position: 'Executive',
                    designation: 'Working Team Member'
                },
                'Draughtsman': {
                    position: 'Designer',
                    designation: 'Draughtsman'
                },
                '3D Designing Team': {
                    position: 'Designer',
                    designation: '3D Designer'
                },
                'Studio Trainees': {
                    position: 'Trainee',
                    designation: 'Studio Trainee'
                },
                'Business Developer': {
                    position: 'Developer',
                    designation: 'Business Developer'
                },
                'Social Media Manager': {
                    position: 'Manager',
                    designation: 'Social Media Manager'
                },
                'Site Manager': {
                    position: 'Manager',
                    designation: 'Site Manager'
                },
                'Site Coordinator': {
                    position: 'Coordinator',
                    designation: 'Site Coordinator'
                },
                'Site Supervisor': {
                    position: 'Supervisor',
                    designation: 'Site Supervisor'
                },
                'Site Trainee': {
                    position: 'Trainee',
                    designation: 'Site Trainee'
                },
                'Relationship Manager': {
                    position: 'Manager',
                    designation: 'Relationship Manager'
                },
                'Sales Manager': {
                    position: 'Manager',
                    designation: 'Sales Manager'
                },
                'Sales Consultant': {
                    position: 'Consultant',
                    designation: 'Sales Consultant'
                },
                'Field Sales Representative': {
                    position: 'Representative',
                    designation: 'Field Sales Representative'
                },
                'Purchase Manager': {
                    position: 'Manager',
                    designation: 'Purchase Manager'
                },
                'Purchase Executive': {
                    position: 'Executive',
                    designation: 'Purchase Executive'
                },
                'Sales': {
                    position: 'Executive',
                    designation: 'Sales Executive'
                },
                'Purchase': {
                    position: 'Executive',
                    designation: 'Purchase Executive'
                },
                'Senior Manager (Purchase)': {
                    position: 'Senior Manager',
                    designation: 'Purchase Manager'
                }
            };
            
            // Function to update position and designation based on role
            function updatePositionAndDesignation() {
                const selectedRole = roleSelect.value;
                if (selectedRole && roleMapping[selectedRole]) {
                    positionSelect.value = roleMapping[selectedRole].position;
                    designationSelect.value = roleMapping[selectedRole].designation;
                }
            }
            
            // Add event listener to role select
            roleSelect.addEventListener('change', updatePositionAndDesignation);
            
            // Initial update if role is already selected
            if (roleSelect.value) {
                updatePositionAndDesignation();
            }
        }
    });
    </script>
</body>
</html> 