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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-lg);
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-lg);
        }

        .detail-section {
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

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .detail-grid {
                grid-template-columns: 1fr;
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
                        <div class="info-label">Position</div>
                        <div class="info-value">
                            <input type="text" name="position" value="<?php echo htmlspecialchars($employee['position'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Designation</div>
                        <div class="info-value">
                            <input type="text" name="designation" value="<?php echo htmlspecialchars($employee['designation'] ?? ''); ?>">
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

                <!-- Documents Section with Upload Form -->
                <div class="detail-section">
                    <div class="section-title">
                        <i class="fas fa-file-alt"></i>
                        Documents
                        <button type="button" class="btn btn-sm btn-add-doc" onclick="toggleDocumentForm()">
                            <i class="fas fa-plus"></i> Add Document
                        </button>
                    </div>

                    <!-- Document Upload Form (Hidden by default) -->
                    <div id="documentUploadForm" class="document-upload-form" style="display: none;">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="document_type">Document Type</label>
                                <select id="document_type" name="document[type]" class="form-control">
                                    <option value="">Select Document Type</option>
                                    <option value="aadhar">Aadhar Card</option>
                                    <option value="pan">PAN Card</option>
                                    <option value="passport">Passport</option>
                                    <option value="driving_license">Driving License</option>
                                    <option value="voter_id">Voter ID</option>
                                    <option value="resume">Resume</option>
                                    <option value="certificates">Certificates</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="document_file">Upload Document</label>
                                <input type="file" id="document_file" name="document[file]" class="form-control" 
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <small class="text-muted">
                                    Supported formats: PDF, DOC, DOCX, JPG, PNG (Max size: 5MB)
                                </small>
                            </div>
                            <div class="form-group">
                                <button type="button" class="btn btn-primary" onclick="uploadDocument(<?php echo $employee['id']; ?>)">
                                    Upload Document
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="toggleDocumentForm()">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Documents List -->
                    <div id="documentsList" class="document-list">
                        <!-- Documents will be loaded here -->
                    </div>
                </div>

                <!-- Modify the HR Documents section to remove the upload button -->
                <div class="hr-documents-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-file-alt"></i>
                            HR Documents
                        </h2>
                    </div>
                    <div id="hrDocumentsList" class="documents-list">
                        <!-- Documents will be loaded here -->
                    </div>
                </div>

                <!-- Salary Information Section -->
                <div class="detail-section">
                    <div class="section-title">
                        <i class="fas fa-money-bill-wave"></i>
                        Salary Information
                    </div>
                    <div class="info-group">
                        <div class="info-label">Base Salary</div>
                        <div class="info-value">
                            <input type="number" name="base_salary" value="<?php echo htmlspecialchars($employee['base_salary'] ?? '0'); ?>">
                        </div>
                    </div>
                    
                    <!-- Allowances -->
                    <div class="info-group">
                        <div class="info-label">Allowances</div>
                        <?php 
                        $allowances = json_decode($employee['allowances'] ?? '{}', true) ?: [
                            'hra' => 0,
                            'da' => 0,
                            'ta' => 0,
                            'medical' => 0
                        ];
                        ?>
                        <div class="salary-components">
                            <div class="component-item">
                                <label>HRA</label>
                                <input type="number" name="allowances[hra]" value="<?php echo htmlspecialchars($allowances['hra']); ?>">
                            </div>
                            <div class="component-item">
                                <label>DA</label>
                                <input type="number" name="allowances[da]" value="<?php echo htmlspecialchars($allowances['da']); ?>">
                            </div>
                            <div class="component-item">
                                <label>Travel</label>
                                <input type="number" name="allowances[ta]" value="<?php echo htmlspecialchars($allowances['ta']); ?>">
                            </div>
                            <div class="component-item">
                                <label>Medical</label>
                                <input type="number" name="allowances[medical]" value="<?php echo htmlspecialchars($allowances['medical']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Deductions -->
                    <div class="info-group">
                        <div class="info-label">Deductions</div>
                        <?php 
                        $deductions = json_decode($employee['deductions'] ?? '{}', true) ?: [
                            'pf' => 0,
                            'tax' => 0,
                            'insurance' => 0
                        ];
                        ?>
                        <div class="salary-components">
                            <div class="component-item">
                                <label>PF</label>
                                <input type="number" name="deductions[pf]" value="<?php echo htmlspecialchars($deductions['pf']); ?>">
                            </div>
                            <div class="component-item">
                                <label>Tax</label>
                                <input type="number" name="deductions[tax]" value="<?php echo htmlspecialchars($deductions['tax']); ?>">
                            </div>
                            <div class="component-item">
                                <label>Insurance</label>
                                <input type="number" name="deductions[insurance]" value="<?php echo htmlspecialchars($deductions['insurance']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Total Salary Calculation -->
                    <div class="info-group total-salary">
                        <div class="info-label">Total Salary</div>
                        <div class="salary-summary">
                            <div class="summary-item">
                                <span>Gross Salary:</span>
                                <span class="amount" id="grossSalary">0</span>
                            </div>
                            <div class="summary-item">
                                <span>Total Deductions:</span>
                                <span class="amount" id="totalDeductions">0</span>
                            </div>
                            <div class="summary-item total">
                                <span>Net Salary:</span>
                                <span class="amount" id="netSalary">0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="edit-controls">
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Add this JavaScript -->
    <script>
    function toggleDocumentForm() {
        const form = document.getElementById('documentUploadForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }

    function uploadDocument(employeeId) {
        const docType = document.getElementById('document_type');
        const docFile = document.getElementById('document_file');
        
        if (!docType.value || !docFile.files[0]) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Please select both document type and file'
            });
            return;
        }

        const formData = new FormData();
        formData.append('type', docType.value);
        formData.append('file', docFile.files[0]);
        formData.append('employee_id', employeeId);
        formData.append('action', 'add');

        // Show loading indicator
        Swal.fire({
            title: 'Uploading...',
            text: 'Please wait while we upload your document',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Debug log
        console.log('Uploading document for employee:', employeeId);
        console.log('Document type:', docType.value);
        console.log('File name:', docFile.files[0].name);

        fetch('update_employee_documents.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Upload response:', data); // Debug log
            Swal.close();
            if (data.success) {
                // Refresh documents list
                loadDocuments(employeeId);
                // Reset form
                docType.value = '';
                docFile.value = '';
                toggleDocumentForm();

                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message || 'Document uploaded successfully'
                });
            } else {
                throw new Error(data.message || 'Failed to upload document');
            }
        })
        .catch(error => {
            console.error('Upload error:', error); // Debug log
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to upload document'
            });
        });
    }

    function loadDocuments(employeeId) {
        fetch(`get_employee_documents.php?employee_id=${employeeId}`)
            .then(response => response.json())
            .then(data => {
                const documentsList = document.getElementById('documentsList');
                if (data.length === 0) {
                    documentsList.innerHTML = `
                        <div class="no-documents">
                            <i class="fas fa-folder-open"></i>
                            <p>No documents uploaded yet</p>
                        </div>`;
                    return;
                }

                documentsList.innerHTML = data.map((doc, index) => `
                    <div class="document-item">
                        <i class="fas ${getFileIcon(doc.filename)}"></i>
                        <div class="document-info">
                            <div class="document-name">${doc.filename}</div>
                            <div class="document-type">${doc.type}</div>
                            <div class="document-date">Uploaded: ${doc.upload_date}</div>
                        </div>
                        <div class="document-actions">
                            <button class="btn btn-sm btn-view" onclick="viewDocument('${doc.file_path}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-download" onclick="downloadDocument('${doc.file_path}')">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="btn btn-sm btn-delete" onclick="deleteDocument(${employeeId}, ${index})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            })
            .catch(error => {
                console.error('Error loading documents:', error);
            });
    }

    function getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        switch(ext) {
            case 'pdf': return 'fa-file-pdf';
            case 'doc':
            case 'docx': return 'fa-file-word';
            case 'jpg':
            case 'jpeg':
            case 'png': return 'fa-file-image';
            default: return 'fa-file';
        }
    }

    function viewDocument(docId) {
        if (!docId) return;
        window.open(`view_document.php?id=${docId}`, '_blank');
    }

    function downloadDocument(docId) {
        if (!docId) return;
        window.location.href = `download_document.php?id=${docId}`;
    }

    function deleteDocument(employeeId, index) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This action cannot be undone",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('employee_id', employeeId);
                formData.append('index', index);
                formData.append('action', 'delete');

                fetch('update_employee_documents.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadDocuments(employeeId);
                        Swal.fire('Deleted!', 'Document has been deleted.', 'success');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Failed to delete document', 'error');
                });
            }
        });
    }

    // Load documents when page loads
    document.addEventListener('DOMContentLoaded', () => {
        loadDocuments(<?php echo $employee['id']; ?>);
        loadHRDocuments();
        calculateSalary();
    });

    function calculateSalary() {
        const baseSalary = parseFloat(document.querySelector('input[name="base_salary"]').value) || 0;
        
        // Calculate total allowances
        let totalAllowances = 0;
        document.querySelectorAll('input[name^="allowances"]').forEach(input => {
            totalAllowances += parseFloat(input.value) || 0;
        });
        
        // Calculate total deductions
        let totalDeductions = 0;
        document.querySelectorAll('input[name^="deductions"]').forEach(input => {
            totalDeductions += parseFloat(input.value) || 0;
        });
        
        const grossSalary = baseSalary + totalAllowances;
        const netSalary = grossSalary - totalDeductions;
        
        // Update summary
        document.getElementById('grossSalary').textContent = grossSalary.toFixed(2);
        document.getElementById('totalDeductions').textContent = totalDeductions.toFixed(2);
        document.getElementById('netSalary').textContent = netSalary.toFixed(2);
    }

    // Add event listeners to all salary inputs
    document.querySelectorAll('input[name="base_salary"], input[name^="allowances"], input[name^="deductions"]')
        .forEach(input => {
            input.addEventListener('input', calculateSalary);
        });

    // Calculate initial values
    document.addEventListener('DOMContentLoaded', calculateSalary);

    function loadHRDocuments() {
        fetch('get_hr_documents.php')
            .then(response => response.json())
            .then(data => {
                const documentsList = document.getElementById('hrDocumentsList');
                
                if (!data.success || !data.documents || data.documents.length === 0) {
                    documentsList.innerHTML = `
                        <div class="no-documents">
                            <i class="ri-folder-open-line"></i>
                            <p>No HR documents available</p>
                        </div>`;
                    return;
                }

                documentsList.innerHTML = data.documents.map(doc => `
                    <div class="document-item">
                        <div class="document-main">
                            <div class="document-header">
                                <div class="document-icon">
                                    <i class="ri-file-pdf-line"></i>
                                </div>
                                <div class="document-info">
                                    <div class="document-name">${doc.original_name || doc.filename}</div>
                                    <div class="document-type">${formatDocumentType(doc.type)}</div>
                                </div>
                            </div>
                            <div class="document-meta">
                                <div class="meta-item">
                                    <i class="ri-calendar-line"></i>
                                    ${doc.upload_date}
                                </div>
                                <div class="action-links">
                                    <a href="#" class="action-link" onclick="viewDocument(${doc.id}); return false;">
                                        <i class="ri-eye-line"></i>
                                        View
                                    </a>
                                    <a href="#" class="action-link download" onclick="downloadDocument(${doc.id}); return false;">
                                        <i class="ri-download-line"></i>
                                        Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
            })
            .catch(error => {
                console.error('Error loading HR documents:', error);
            });
    }

    function formatDocumentType(type) {
        return type.split('_')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }

    // Update the view and download functions to use the correct PHP endpoints
    function viewDocument(docId) {
        if (!docId) return;
        window.open(`view_document.php?id=${docId}`, '_blank');
    }

    function downloadDocument(docId) {
        if (!docId) return;
        window.location.href = `download_document.php?id=${docId}`;
    }
    </script>
</body>
</html> 