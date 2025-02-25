<?php
session_start();
require_once 'config.php';

// Verify user is HR
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'HR' && !isset($_SESSION['temp_admin_access']))) {
    header('Location: login.php');
    exit();
}

$employee_id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$employee_id) {
    header('Location: hr_dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize input
        $username = trim($_POST['username']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $department = $_POST['department'] === 'other' ? trim($_POST['other_department']) : trim($_POST['department']);
        $designation = $_POST['designation'] === 'other' ? trim($_POST['other_designation']) : trim($_POST['designation']);
        $role = trim($_POST['role']);
        $reporting_manager = trim($_POST['reporting_manager']);
        $status = trim($_POST['status']);
        $shift_id = trim($_POST['shift_id']);

        // Handle file uploads
        $upload_dir = 'uploads/' . $employee_id . '/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Initialize SQL parts
        $sql_parts = [
            "username = ?",
            "email = ?",
            "department = ?",
            "designation = ?",
            "role = ?",
            "reporting_manager = ?",
            "status = ?",
            "shift_id = ?",
            "bank_name = ?",
            "bank_account = ?",
            "ifsc_code = ?"
        ];
        
        $params = [
            $_POST['username'],
            $_POST['email'],
            $_POST['department'],
            $_POST['designation'],
            $_POST['role'],
            $_POST['reporting_manager'],
            $_POST['status'],
            $_POST['shift_id'],
            $_POST['bank_name'] ?? null,
            $_POST['bank_account'] ?? null,
            $_POST['ifsc_code'] ?? null
        ];

        // Handle document uploads
        $document_fields = [
            'offer_letter',
            'increment_letter',
            'resume',
            'aadhar_card',
            'pan_card',
            'matriculation',
            'intermediate',
            'graduation',
            'post_graduation'
        ];

        foreach ($document_fields as $field) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$field];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                // Validate file extension
                $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception("Invalid file type for {$field}. Allowed types: PDF, DOC, DOCX, JPG, PNG");
                }

                // Generate unique filename
                $new_filename = $field . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $new_filename;

                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    // Add to SQL query
                    $sql_parts[] = "$field = ?";
                    $params[] = $file_path;
                    
                    // Debug output
                    error_log("File uploaded successfully: " . $file_path);
                } else {
                    throw new Exception("Error uploading {$field}");
                }
            }
        }

        // Construct final SQL query
        $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = ?";
        $params[] = $employee_id;

        // Debug output
        error_log("SQL Query: " . $sql);
        error_log("Params: " . print_r($params, true));

        // Execute the update query
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);

        if ($result) {
            $_SESSION['success_message'] = "Employee details updated successfully!";
            error_log("Database update successful");
        } else {
            throw new Exception("Error updating database");
        }

        header("Location: edit_employee.php?id=" . $employee_id);
        exit();

    } catch (Exception $e) {
        error_log("Error in file upload/update: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: edit_employee.php?id=" . $employee_id);
        exit();
    }
}

// Fetch current employee data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch();

    if (!$employee) {
        throw new Exception("Employee not found");
    }

    // Determine current shift type
    function determineShiftType($start, $end) {
        if ($start === '09:00:00' && $end === '18:00:00') return 'morning';
        if ($start === '14:00:00' && $end === '23:00:00') return 'evening';
        if ($start === '21:00:00' && $end === '06:00:00') return 'night';
        return 'custom';
    }

    $current_shift_type = determineShiftType($employee['shift_start'], $employee['shift_end']);
    $weekly_off_array = !empty($employee['weekly_off']) ? array_map('trim', explode(',', $employee['weekly_off'])) : ['Sunday'];

} catch (Exception $e) {
    error_log("Error fetching employee data: " . $e->getMessage());
    $_SESSION['error_message'] = "Error fetching employee data: " . $e->getMessage();
    header('Location: hr_dashboard.php');
    exit();
}

// Fetch departments from database
$dept_query = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
$departments = $dept_query->fetchAll(PDO::FETCH_COLUMN);

// Fetch designations from database
$desig_query = $pdo->query("SELECT DISTINCT designation FROM users WHERE designation IS NOT NULL ORDER BY designation");
$designations = $desig_query->fetchAll(PDO::FETCH_COLUMN);

// Fetch roles from database
$role_query = $pdo->query("SELECT DISTINCT role FROM users WHERE role IS NOT NULL ORDER BY role");
$roles = $role_query->fetchAll(PDO::FETCH_COLUMN);

// Fetch reporting managers (active employees who can be managers)
$manager_query = $pdo->query("
    SELECT id, username, designation 
    FROM users 
    WHERE status = 'active' 
    AND (role = 'admin' OR role = 'HR' OR designation LIKE '%Manager%' OR designation LIKE '%Lead%')
    ORDER BY username
");
$managers = $manager_query->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - <?php echo htmlspecialchars($employee['username']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    
    <!-- Include your CSS here -->
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            color: #1a1a1a;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        /* Form Container Styles */
        .edit-form {
            background: transparent;
        }

        .edit-form h2 {
            font-size: 1.8rem;
            color: #2d3748;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .edit-form h2 i {
            color: #4834d4;
        }

        /* Form Sections */
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }

        .form-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .form-section h3 {
            font-size: 1.2rem;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section h3 i {
            color: #4834d4;
        }

        /* Form Grid Layout */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 0.5rem;
        }

        /* Form Inputs */
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #2d3748;
            transition: all 0.3s ease;
            background: #fff;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #4834d4;
            box-shadow: 0 0 0 3px rgba(72, 52, 212, 0.1);
            outline: none;
        }

        /* Select Styles */
        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%234834d4' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
            padding-right: 2.5rem;
        }

        /* Checkbox Group */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 0.8rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1.5px solid #e2e8f0;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.6rem 1rem;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            color: #4a5568;
        }

        .checkbox-label:hover {
            background: #edf2f7;
        }

        .checkbox-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            border: 1.5px solid #cbd5e0;
            border-radius: 4px;
            cursor: pointer;
        }

        /* Submit Button */
        .submit-btn {
            background: #4834d4;
            color: white;
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 1.5rem;
        }

        .submit-btn:hover {
            background: #3c2bb3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(72, 52, 212, 0.2);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }

        .alert-danger {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        /* Required Field Indicator */
        .required::after {
            content: '*';
            color: #e53e3e;
            margin-left: 4px;
        }

        /* Custom Time Input Styling */
        input[type="time"] {
            padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #2d3748;
        }

        /* Other Input Fields */
        #other_department,
        #other_designation {
            margin-top: 0.8rem;
            display: none;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
                margin: 1rem auto;
            }

            .form-section {
                padding: 1.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .checkbox-group {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }

            .submit-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-section {
            animation: fadeIn 0.3s ease-out;
        }

        /* Status Badge Styles */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-active {
            background: #c6f6d5;
            color: #2f855a;
        }

        .status-inactive {
            background: #fed7d7;
            color: #c53030;
        }

        /* Section Dividers */
        .section-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 2rem 0;
        }

        /* Tooltip Styles */
        [data-tooltip] {
            position: relative;
            cursor: help;
        }

        [data-tooltip]:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 0.5rem 1rem;
            background: #2d3748;
            color: white;
            border-radius: 4px;
            font-size: 0.85rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
        }

        [data-tooltip]:hover:before {
            opacity: 1;
            visibility: visible;
        }

        .upload-title {
            font-size: 1.1rem;
            color: #2d3748;
            margin: 1.5rem 0 1rem;
        }

        .upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .file-preview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: #f8fafc;
            border-radius: 6px;
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-info i {
            color: #4834d4;
            font-size: 1.1rem;
        }

        .file-info span {
            font-size: 0.9rem;
            color: #4a5568;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #4834d4;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .download-btn:hover {
            background: #3c2bb3;
            transform: translateY(-1px);
        }

        .download-btn i {
            font-size: 0.9rem;
        }

        .upload-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .upload-item label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            color: #2d3748;
        }

        .upload-item input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 1px dashed #cbd5e0;
            border-radius: 4px;
            background: #f8fafc;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="edit-form">
            <h2><i class="fas fa-user-edit"></i> Edit Employee Details</h2>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Personal Information -->
                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="required">Username</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($employee['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="required">Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Work Information -->
                <div class="form-section">
                    <h3><i class="fas fa-briefcase"></i> Work Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="required">Department</label>
                            <select name="department" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" 
                                            <?php echo $employee['department'] === $dept ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="other">Other</option>
                            </select>
                            <!-- Other Department Input (initially hidden) -->
                            <input type="text" id="other_department" name="other_department" 
                                   style="display: none; margin-top: 10px;" 
                                   placeholder="Enter Department Name">
                        </div>

                        <div class="form-group">
                            <label class="required">Designation</label>
                            <select name="designation" id="designation_select" required>
                                <option value="">Select Designation</option>
                                <?php foreach ($designations as $desig): ?>
                                    <option value="<?php echo htmlspecialchars($desig); ?>" 
                                            <?php echo $employee['designation'] === $desig ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($desig); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="other">Other</option>
                            </select>
                            <!-- Other Designation Input (initially hidden) -->
                            <input type="text" id="other_designation" name="other_designation" 
                                   style="display: none; margin-top: 10px;" 
                                   placeholder="Enter Designation">
                        </div>

                        <div class="form-group">
                            <label class="required">Role</label>
                            <select name="role" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role); ?>" 
                                            <?php echo $employee['role'] === $role ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Reporting Manager</label>
                            <select name="reporting_manager">
                                <option value="">Select Reporting Manager</option>
                                <?php foreach ($managers as $manager): ?>
                                    <?php if ($manager['id'] != $employee_id): // Prevent self-reporting ?>
                                        <option value="<?php echo htmlspecialchars($manager['username']); ?>" 
                                                <?php echo $employee['reporting_manager'] === $manager['username'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($manager['username'] . ' (' . $manager['designation'] . ')'); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Schedule Information -->
                <div class="form-section">
                    <h3><i class="fas fa-clock"></i> Work Schedule</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="required">Shift Type</label>
                            <select name="shift_type" id="shift_type" onchange="handleShiftChange()" required>
                                <option value="">Select Shift</option>
                                <option value="morning" <?php echo $current_shift_type === 'morning' ? 'selected' : ''; ?>>Morning Shift (9 AM - 6 PM)</option>
                                <option value="evening" <?php echo $current_shift_type === 'evening' ? 'selected' : ''; ?>>Evening Shift (2 PM - 11 PM)</option>
                                <option value="night" <?php echo $current_shift_type === 'night' ? 'selected' : ''; ?>>Night Shift (9 PM - 6 AM)</option>
                                <option value="custom" <?php echo $current_shift_type === 'custom' ? 'selected' : ''; ?>>Custom Shift</option>
                            </select>
                        </div>

                        <div id="custom_shift_fields" style="display: <?php echo $current_shift_type === 'custom' ? 'block' : 'none'; ?>">
                            <div class="form-group">
                                <label>Shift Start Time</label>
                                <input type="time" name="shift_start" id="shift_start" 
                                       value="<?php echo substr($employee['shift_start'], 0, 5); ?>">
                            </div>
                            <div class="form-group">
                                <label>Shift End Time</label>
                                <input type="time" name="shift_end" id="shift_end" 
                                       value="<?php echo substr($employee['shift_end'], 0, 5); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="required">Weekly Off Days</label>
                            <div class="checkbox-group">
                                <?php
                                $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                foreach ($days as $day):
                                ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" 
                                           name="weekly_off[]" 
                                           value="<?php echo $day; ?>"
                                           <?php echo in_array($day, $weekly_off_array) ? 'checked' : ''; ?>>
                                    <?php echo $day; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="form-section">
                    <h3><i class="fas fa-toggle-on"></i> Status</h3>
                    <div class="form-group">
                        <label class="required">Status</label>
                        <select name="status" required>
                            <option value="active" <?php echo $employee['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $employee['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="shift">Shift Assignment</label>
                    <select name="shift_id" id="shift" class="form-control" required>
                        <option value="">Select Shift</option>
                        <?php
                        $shiftStmt = $pdo->query("SELECT id, shift_name, start_time, end_time, weekly_offs FROM shifts ORDER BY shift_name");
                        while ($shift = $shiftStmt->fetch()) {
                            $timing = date('h:i A', strtotime($shift['start_time'])) . ' - ' . 
                                     date('h:i A', strtotime($shift['end_time']));
                            $weekly_offs = str_replace(',', ', ', $shift['weekly_offs']);
                            $selected = ($shift['id'] == $employee['shift_id']) ? 'selected' : '';
                            echo "<option value='" . $shift['id'] . "' $selected>" . 
                                 htmlspecialchars($shift['shift_name']) . " (" . $timing . ") - Off: " . $weekly_offs . 
                                 "</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Documents and Bank Details -->
                <div class="form-section">
                    <h3><i class="fas fa-file-alt"></i> Documents & Bank Details</h3>
                    
                    <!-- Bank Details -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="required">Bank Name</label>
                            <input type="text" name="bank_name" value="<?php echo htmlspecialchars($employee['bank_name'] ?? ''); ?>" 
                                   required>
                        </div>
                        <div class="form-group">
                            <label class="required">Bank Account Number</label>
                            <input type="text" name="bank_account" value="<?php echo htmlspecialchars($employee['bank_account'] ?? ''); ?>" 
                                   pattern="[0-9]{9,18}" title="Please enter a valid bank account number">
                        </div>
                        <div class="form-group">
                            <label class="required">IFSC Code</label>
                            <input type="text" name="ifsc_code" value="<?php echo htmlspecialchars($employee['ifsc_code'] ?? ''); ?>" 
                                   pattern="^[A-Z]{4}0[A-Z0-9]{6}$" title="Please enter a valid IFSC code">
                        </div>
                    </div>

                    <!-- Document Uploads -->
                    <div class="document-uploads">
                        <h4 class="upload-title">Required Documents</h4>
                        
                        <div class="upload-grid">
                            <!-- Offer Letter -->
                            <div class="upload-item">
                                <label>Offer Letter</label>
                                <?php if (!empty($employee['offer_letter'])): ?>
                                    <div class="file-preview">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf"></i>
                                            <span><?php echo basename($employee['offer_letter']); ?></span>
                                        </div>
                                        <a href="download.php?file=<?php echo urlencode($employee['offer_letter']); ?>&type=offer_letter" 
                                           class="download-btn" target="_blank">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="offer_letter" accept=".pdf,.doc,.docx">
                            </div>

                            <!-- Increment Letter -->
                            <div class="upload-item">
                                <label>Increment Letter</label>
                                <?php if (!empty($employee['increment_letter'])): ?>
                                    <div class="file-preview">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf"></i>
                                            <span><?php echo basename($employee['increment_letter']); ?></span>
                                        </div>
                                        <a href="download.php?file=<?php echo urlencode($employee['increment_letter']); ?>&type=increment_letter" 
                                           class="download-btn" target="_blank">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="increment_letter" accept=".pdf,.doc,.docx">
                            </div>

                            <!-- Resume -->
                            <div class="upload-item">
                                <label class="required">Resume</label>
                                <?php if (!empty($employee['resume'])): ?>
                                    <div class="file-preview">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf"></i>
                                            <span><?php echo basename($employee['resume']); ?></span>
                                        </div>
                                        <a href="download.php?file=<?php echo urlencode($employee['resume']); ?>&type=resume" 
                                           class="download-btn" target="_blank">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="resume" accept=".pdf,.doc,.docx">
                            </div>

                            <!-- Aadhar Card -->
                            <div class="upload-item">
                                <label class="required">Aadhar Card</label>
                                <?php if (!empty($employee['aadhar_card'])): ?>
                                    <div class="file-preview">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf"></i>
                                            <span><?php echo basename($employee['aadhar_card']); ?></span>
                                        </div>
                                        <a href="download.php?file=<?php echo urlencode($employee['aadhar_card']); ?>&type=aadhar_card" 
                                           class="download-btn" target="_blank">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="aadhar_card" accept=".pdf,.jpg,.jpeg,.png">
                            </div>

                            <!-- PAN Card -->
                            <div class="upload-item">
                                <label class="required">PAN Card</label>
                                <?php if (!empty($employee['pan_card'])): ?>
                                    <div class="file-preview">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf"></i>
                                            <span><?php echo basename($employee['pan_card']); ?></span>
                                        </div>
                                        <a href="download.php?file=<?php echo urlencode($employee['pan_card']); ?>&type=pan_card" 
                                           class="download-btn" target="_blank">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="pan_card" accept=".pdf,.jpg,.jpeg,.png">
                            </div>

                            <!-- Matriculation -->
                            <div class="upload-item">
                                <label class="required">Matriculation Certificate</label>
                                <?php if (!empty($employee['matriculation'])): ?>
                                    <div class="file-preview">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf"></i>
                                            <span><?php echo basename($employee['matriculation']); ?></span>
                                        </div>
                                        <a href="download.php?file=<?php echo urlencode($employee['matriculation']); ?>&type=matriculation" 
                                           class="download-btn" target="_blank">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="matriculation" accept=".pdf,.jpg,.jpeg,.png">
                            </div>

                            <!-- Intermediate -->
                            <div class="upload-item">
                                <label class="required">Intermediate Certificate</label>
                                <?php if (!empty($employee['intermediate'])): ?>
                                    <div class="file-preview">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf"></i>
                                            <span><?php echo basename($employee['intermediate']); ?></span>
                                        </div>
                                        <a href="download.php?file=<?php echo urlencode($employee['intermediate']); ?>&type=intermediate" 
                                           class="download-btn" target="_blank">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="intermediate" accept=".pdf,.jpg,.jpeg,.png">
                            </div>

                            <!-- Graduation -->
                            <div class="upload-item">
                                <label>Graduation Certificate</label>
                                <?php if (!empty($employee['graduation'])): ?>
                                    <div class="file-preview">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf"></i>
                                            <span><?php echo basename($employee['graduation']); ?></span>
                                        </div>
                                        <a href="download.php?file=<?php echo urlencode($employee['graduation']); ?>&type=graduation" 
                                           class="download-btn" target="_blank">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="graduation" accept=".pdf,.jpg,.jpeg,.png">
                            </div>

                            <!-- Post Graduation -->
                            <div class="upload-item">
                                <label>Post Graduation Certificate</label>
                                <?php if (!empty($employee['post_graduation'])): ?>
                                    <div class="file-preview">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf"></i>
                                            <span><?php echo basename($employee['post_graduation']); ?></span>
                                        </div>
                                        <a href="download.php?file=<?php echo urlencode($employee['post_graduation']); ?>&type=post_graduation" 
                                           class="download-btn" target="_blank">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="post_graduation" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-save"></i> Update Employee
                </button>
            </form>
        </div>
    </div>

    <script>
        // Your JavaScript from the previous response

        document.addEventListener('DOMContentLoaded', function() {
            // Handle Department "Other" option
            const departmentSelect = document.querySelector('select[name="department"]');
            const otherDepartment = document.getElementById('other_department');
            
            departmentSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    otherDepartment.style.display = 'block';
                    otherDepartment.required = true;
                } else {
                    otherDepartment.style.display = 'none';
                    otherDepartment.required = false;
                }
            });

            // Handle Designation "Other" option
            const designationSelect = document.getElementById('designation_select');
            const otherDesignation = document.getElementById('other_designation');
            
            designationSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    otherDesignation.style.display = 'block';
                    otherDesignation.required = true;
                } else {
                    otherDesignation.style.display = 'none';
                    otherDesignation.required = false;
                }
            });
        });
    </script>
</body>
</html>
