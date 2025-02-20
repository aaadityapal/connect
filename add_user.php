<?php
session_start();
require_once 'config.php';

// Check if user is HR
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}

// Fetch all managers
function getManagers($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, username, role FROM users 
                            WHERE role LIKE '%Senior Manager%' 
                            OR role IN ('HR', 'admin')
                            ORDER BY role, username");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching managers: " . $e->getMessage());
        return [];
    }
}

// Fetch all designations
function getDesignations($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM designations ORDER BY department, name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching designations: " . $e->getMessage());
        return [];
    }
}

// Add this function to get departments
function getDepartments($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM departments ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching departments: " . $e->getMessage());
        return [];
    }
}

// Add this function to generate unique ID
function generateUniqueId($pdo, $role) {
    try {
        // Get prefix based on role
        $prefix = '';
        switch ($role) {
            case 'Senior Manager (Studio)':
                $prefix = 'SMS';
                break;
            case 'Senior Manager (Site)':
                $prefix = 'SMT';
                break;
            case 'Senior Manager (Marketing)':
                $prefix = 'SMM';
                break;
            case 'Senior Manager (Sales)':
                $prefix = 'SML';
                break;
            case 'Design Team':
                $prefix = 'DT';
                break;
            case 'Working Team':
                $prefix = 'WT';
                break;
            case '3D Designing Team':
                $prefix = '3DT';
                break;
            case 'Studio Trainees':
                $prefix = 'STR';
                break;
            case 'Business Developer':
                $prefix = 'BD';
                break;
            case 'Social Media Manager':
                $prefix = 'SMM';
                break;
            case 'Site Manager':
                $prefix = 'STM';
                break;
            case 'Site Supervisor':
                $prefix = 'STS';
                break;
            case 'Site Trainee':
                $prefix = 'STT';
                break;
            case 'Relationship Manager':
                $prefix = 'RM';
                break;
            case 'Sales Manager':
                $prefix = 'SM';
                break;
            case 'Sales Consultant':
                $prefix = 'SC';
                break;
            case 'Field Sales Representative':
                $prefix = 'FSR';
                break;
            case 'HR':
                $prefix = 'HR';
                break;
            default:
                $prefix = 'EMP';
        }

        // Get the last ID for this prefix
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(unique_id, LENGTH(:prefix) + 1) AS UNSIGNED)) as last_num 
                              FROM users WHERE unique_id LIKE :prefix_like");
        $stmt->execute([
            'prefix' => $prefix,
            'prefix_like' => $prefix . '%'
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $nextNum = 1;
        if ($result['last_num']) {
            $nextNum = $result['last_num'] + 1;
        }

        // Generate new ID (prefix + 3 digit number)
        return $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

    } catch(PDOException $e) {
        error_log("Error generating unique ID: " . $e->getMessage());
        throw $e;
    }
}

$managers = getManagers($pdo);
$designations = getDesignations($pdo);
$departments = getDepartments($pdo);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Generate unique ID based on the first selected role
        $selectedRoles = $_POST['role'];
        $primaryRole = is_array($selectedRoles) ? $selectedRoles[0] : $selectedRoles;
        $unique_id = generateUniqueId($pdo, $primaryRole);

        // Convert roles array to string
        $role = is_array($_POST['role']) ? implode(', ', $_POST['role']) : $_POST['role'];

        // Existing user data insertion
        $stmt = $pdo->prepare("
            INSERT INTO users (
                username, email, password, role, department, 
                designation, reporting_manager, unique_id, 
                shift_type, weekly_off_days, status, created_at, shift_id
            ) VALUES (
                :username, :email, :password, :role, :department, 
                :designation, :reporting_manager, :unique_id, 
                :shift_type, :weekly_off_days, 'active', NOW(), :shift_id
            )
        ");

        // Get and validate weekly off days
        $weekly_off_days = isset($_POST['weekly_off_days']) ? implode(',', $_POST['weekly_off_days']) : '';
        
        $stmt->execute([
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            'role' => $role, // Use the converted role string
            'department' => $_POST['department'],
            'designation' => $_POST['designation'],
            'reporting_manager' => $_POST['reporting_manager'],
            'unique_id' => $unique_id, // Use the generated unique ID
            'shift_type' => $_POST['shift_type'],
            'weekly_off_days' => $weekly_off_days,
            'shift_id' => $_POST['shift_id']
        ]);

        // Get the newly inserted user's ID
        $user_id = $pdo->lastInsertId();

        // Insert shift timing details into a separate table
        $shift_stmt = $pdo->prepare("
            INSERT INTO shift_timings (
                user_id, shift_type, start_time, end_time, 
                break_start, break_end, created_at
            ) VALUES (
                :user_id, :shift_type, :start_time, :end_time, 
                :break_start, :break_end, NOW()
            )
        ");

        // Get shift timings based on shift type
        $shift_timings = getShiftTimings($_POST['shift_type']);

        $shift_stmt->execute([
            'user_id' => $user_id,
            'shift_type' => $_POST['shift_type'],
            'start_time' => $shift_timings['start_time'],
            'end_time' => $shift_timings['end_time'],
            'break_start' => $shift_timings['break_start'],
            'break_end' => $shift_timings['break_end']
        ]);

        $pdo->commit();
        $_SESSION['success'] = "User added successfully! Employee ID: " . $unique_id;
        header('Location: employees.php');
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error adding user: " . $e->getMessage();
    }
}

// Helper function to get shift timings
function getShiftTimings($shift_type) {
    switch ($shift_type) {
        case 'morning':
            return [
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'break_start' => '13:00:00',
                'break_end' => '14:00:00'
            ];
        case 'evening':
            return [
                'start_time' => '14:00:00',
                'end_time' => '23:00:00',
                'break_start' => '18:00:00',
                'break_end' => '19:00:00'
            ];
        case 'night':
            return [
                'start_time' => '21:00:00',
                'end_time' => '06:00:00',
                'break_start' => '01:00:00',
                'break_end' => '02:00:00'
            ];
        default:
            return [
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'break_start' => '13:00:00',
                'break_end' => '14:00:00'
            ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User | HR Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --secondary-color: #3b82f6;
            --success-color: #10b981;
            --background-color: #f1f5f9;
            --card-color: #ffffff;
            --text-color: #1e293b;
            --border-color: #e2e8f0;
            --border-radius: 16px;
            --box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        body {
            background: var(--background-color);
            min-height: 100vh;
            padding: 40px 0;
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .form-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .card {
            background: var(--card-color);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: all 0.4s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 32px;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 40%, transparent 50%);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .card-header h3 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .card-body {
            padding: 40px;
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
        }

        .form-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 35px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .form-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.08);
        }

        .form-section h3 {
            color: var(--primary-color);
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-color);
            position: relative;
        }

        .form-section h3::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background: var(--secondary-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }

        .form-group {
            margin-bottom: 28px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 15px;
        }

        .form-control, select.form-control {
            height: 52px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            padding: 8px 20px;
            font-size: 15px;
            transition: all 0.3s ease;
            width: 100%;
            background-color: white;
            color: var(--text-color);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group-text {
            position: absolute;
            left: 15px;
            color: var(--primary-color);
            z-index: 2;
        }

        .input-group .form-control {
            padding-left: 45px;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            padding: 20px;
            background: #f8fafc;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
            border: 1px solid var(--border-color);
        }

        .checkbox-label:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .checkbox-label input[type="checkbox"] {
            margin-right: 12px;
            width: 20px;
            height: 20px;
            accent-color: var(--primary-color);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            height: 56px;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 16px;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            padding: 0 40px;
            width: 100%;
            margin-top: 30px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: translateX(-100%);
            transition: 0.5s;
        }

        .btn-submit:hover::before {
            transform: translateX(100%);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }

        .back-btn {
            position: fixed;
            top: 25px;
            left: 25px;
            background: white;
            color: var(--primary-color);
            padding: 12px 24px;
            border-radius: 30px;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            z-index: 1000;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            transform: translateX(-5px);
            background: var(--primary-color);
            color: white;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.2);
        }

        /* Multiple select styling */
        select[multiple] {
            min-height: 140px;
            padding: 8px;
        }

        select[multiple] option {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 4px;
            transition: all 0.2s ease;
        }

        select[multiple] option:checked {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            color: white;
        }

        /* Alert styling */
        .alert {
            border-radius: var(--border-radius);
            padding: 20px 25px;
            margin-bottom: 30px;
            border: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 12px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 6px;
            border: 3px solid #f1f5f9;
        }

        /* Loading animation for submit button */
        .btn-submit.loading {
            position: relative;
            color: transparent;
        }

        .btn-submit.loading::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 3px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-container {
                padding: 15px;
            }

            .card-body {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .back-btn {
                position: static;
                display: inline-flex;
                margin-bottom: 20px;
            }

            .checkbox-group {
                grid-template-columns: 1fr;
            }
        }

        /* Animation Keyframes */
        @keyframes slideInUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Animated Elements */
        .slide-in {
            animation: slideInUp 0.6s ease-out forwards;
            opacity: 0;
        }

        .animate-gradient {
            background: linear-gradient(270deg, var(--primary-color), var(--secondary-color), #4f46e5);
            background-size: 200% 200%;
            animation: gradientMove 6s ease infinite;
        }

        /* Header Icon */
        .header-icon {
            font-size: 2.5rem;
            color: white;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }

        /* Floating Input Style */
        .floating-input {
            position: relative;
            margin-bottom: 2rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .floating-input label {
            position: absolute;
            left: 3rem;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            padding: 0 0.5rem;
            color: #64748b;
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .floating-input .form-control:focus ~ label,
        .floating-input .form-control:not(:placeholder-shown) ~ label {
            top: 0;
            font-size: 0.85rem;
            color: var(--primary-color);
        }

        .floating-input .form-control:focus ~ .input-icon {
            color: var(--primary-color);
            transform: translateY(-50%) scale(1.1);
        }

        /* Enhanced Button Styles */
        .btn-submit {
            overflow: hidden;
            position: relative;
            transition: all 0.3s ease;
        }

        .btn-submit i {
            margin-right: 8px;
            transition: all 0.3s ease;
        }

        .btn-submit:hover i {
            transform: translateX(3px);
        }

        .btn-submit::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: -100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }

        .btn-submit:hover::after {
            left: 100%;
        }

        /* Form Section Enhancement */
        .form-section {
            position: relative;
            overflow: hidden;
        }

        .form-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-color);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .form-section:hover::before {
            transform: scaleY(1);
        }

        /* Checkbox Animation */
        .checkbox-label input[type="checkbox"] {
            position: relative;
            cursor: pointer;
        }

        .checkbox-label input[type="checkbox"]:checked::before {
            animation: pulse 0.5s ease;
        }

        /* Select Enhancement */
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,...");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        /* Loading State */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--border-color);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body>
    <a href="hr_dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <div class="container form-container">
        <div class="card">
            <div class="card-header animate-gradient">
                <div class="header-icon">
                    <i class="ri-user-add-line"></i>
                </div>
                <h3>Add New User</h3>
            </div>
            <div class="card-body">
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <form action="add_user.php" method="POST">
                    <div class="form-group">
                        <label for="username">Full Name</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                            </div>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                            </div>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="role">Select Role(s)</label>
                        <select class="form-control" id="role" name="role[]" multiple required>
                            <option value="Senior Manager (Studio)">Senior Manager (Studio)</option>
                            <option value="Senior Manager (Site)">Senior Manager (Site)</option>
                            <option value="Senior Manager (Marketing)">Senior Manager (Marketing)</option>
                            <option value="Senior Manager (Sales)">Senior Manager (Sales)</option>
                            <option value="Design Team">Design Team</option>
                            <option value="Working Team">Working Team</option>
                            <option value="3D Designing Team">3D Designing Team</option>
                            <option value="Studio Trainees">Studio Trainees</option>
                            <option value="Business Developer">Business Developer</option>
                            <option value="Social Media Manager">Social Media Manager</option>
                            <option value="Site Manager">Site Manager</option>
                            <option value="Site Supervisor">Site Supervisor</option>
                            <option value="Site Trainee">Site Trainee</option>
                            <option value="Relationship Manager">Relationship Manager</option>
                            <option value="Sales Manager">Sales Manager</option>
                            <option value="Sales Consultant">Sales Consultant</option>
                            <option value="Field Sales Representative">Field Sales Representative</option>
                        </select>
                        <small class="form-text text-muted">
                            Hold Ctrl (Windows) or Command (Mac) to select multiple roles
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="department">Department</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-building"></i>
                                </span>
                            </div>
                            <select class="form-control" id="department" name="department" required>
                                <option value="">Select Department...</option>
                                <option value="Studio">Studio</option>
                                <option value="Site">Site</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Sales">Sales</option>
                                <option value="HR">HR</option>
                                <option value="Administration">Administration</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="designation">Designation</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-id-badge"></i>
                                </span>
                            </div>
                            <select class="form-control" id="designation" name="designation" required>
                                <option value="">Select Designation...</option>
                                <?php
                                $currentDepartment = '';
                                foreach($designations as $designation) {
                                    if ($currentDepartment !== $designation['department']) {
                                        if ($currentDepartment !== '') {
                                            echo '</optgroup>';
                                        }
                                        $currentDepartment = $designation['department'];
                                        echo '<optgroup label="' . htmlspecialchars($currentDepartment) . '">';
                                        
                                        // Add corresponding manager based on department
                                        switch($currentDepartment) {
                                            case 'Studio':
                                                echo '<option value="Manager (Studio)">Manager (Studio)</option>';
                                                break;
                                            case 'Site':
                                                echo '<option value="Manager (Site)">Manager (Site)</option>';
                                                break;
                                            case 'Marketing':
                                                echo '<option value="Manager (Marketing)">Manager (Marketing)</option>';
                                                break;
                                            case 'Sales':
                                                echo '<option value="Manager (Sales)">Manager (Sales)</option>';
                                                break;
                                        }
                                    }
                                    echo '<option value="' . htmlspecialchars($designation['name']) . '">' 
                                         . htmlspecialchars($designation['name']) . '</option>';
                                }
                                if ($currentDepartment !== '') {
                                    echo '</optgroup>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="reportingManagerDiv">
                        <label for="reporting_manager">Reporting Manager</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-user-tie"></i>
                                </span>
                            </div>
                            <select class="form-control" id="reporting_manager" name="reporting_manager">
                                <option value="">Select Reporting Manager...</option>
                                <?php foreach($managers as $manager): ?>
                                    <option value="<?php echo htmlspecialchars($manager['username']); ?>" 
                                            data-role="<?php echo htmlspecialchars($manager['role']); ?>">
                                        <?php echo htmlspecialchars($manager['username']) . ' (' . htmlspecialchars($manager['role']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                            </div>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <small class="form-text text-muted">
                            Password must be at least 8 characters long
                        </small>
                    </div>

                    <div class="form-section">
                        <h3><i class="ri-time-line"></i> Work Schedule</h3>
                        <div class="form-grid">
                            <!-- Shift Selection -->
                            <div class="form-group">
                                <label for="shift_type">Shift Type <span class="required">*</span></label>
                                <select name="shift_type" id="shift_type" class="form-control" required onchange="handleShiftChange()">
                                    <option value="">Select Shift</option>
                                    <option value="morning">Morning Shift (9 AM - 6 PM)</option>
                                    <option value="evening">Evening Shift (2 PM - 11 PM)</option>
                                    <option value="night">Night Shift (9 PM - 6 AM)</option>
                                    <option value="custom">Custom Shift</option>
                                </select>
                            </div>

                            <!-- Custom Shift Times -->
                            <div id="custom_shift_fields" style="display: none;">
                                <div class="form-group">
                                    <label for="shift_start">Shift Start Time <span class="required">*</span></label>
                                    <input type="time" name="shift_start" id="shift_start" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="shift_end">Shift End Time <span class="required">*</span></label>
                                    <input type="time" name="shift_end" id="shift_end" class="form-control">
                                </div>
                            </div>

                            <!-- Weekly Off Selection -->
                            <div class="form-group">
                                <label>Weekly Off Days <span class="required">*</span></label>
                                <div class="checkbox-group">
                                    <?php
                                    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                    foreach ($days as $day):
                                    ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" 
                                               name="weekly_off[]" 
                                               value="<?php echo $day; ?>"
                                               class="weekly-off-checkbox"> 
                                        <?php echo $day; ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="shift">Shift Assignment</label>
                        <select name="shift_id" id="shift" class="form-control" required>
                            <option value="">Select Shift</option>
                            <?php
                            // Fetch all active shifts
                            $shiftStmt = $pdo->query("SELECT id, shift_name, start_time, end_time, weekly_offs FROM shifts ORDER BY shift_name");
                            while ($shift = $shiftStmt->fetch()) {
                                $timing = date('h:i A', strtotime($shift['start_time'])) . ' - ' . 
                                         date('h:i A', strtotime($shift['end_time']));
                                $weekly_offs = str_replace(',', ', ', $shift['weekly_offs']);
                                echo "<option value='" . $shift['id'] . "'>" . 
                                     htmlspecialchars($shift['shift_name']) . " (" . $timing . ") - Off: " . $weekly_offs . 
                                     "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-submit btn-block">
                        <i class="fas fa-user-plus mr-2"></i>Add User
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#role').change(function() {
                const roles = $(this).val() || [];
                const reportingManagerDiv = $('#reportingManagerDiv');
                const reportingManagerSelect = $('#reporting_manager');
                
                // Show/hide reporting manager based on roles
                if (roles.length > 0) {
                    // Define roles that don't need reporting managers
                    const seniorRoles = ['admin', 'HR', 'Senior Manager (Studio)', 'Senior Manager (Site)', 
                                       'Senior Manager (Marketing)', 'Senior Manager (Sales)'];
                    
                    // Check if any selected role is a senior role
                    const hasSeniorRole = roles.some(role => seniorRoles.includes(role));
                    
                    if (!hasSeniorRole) {
                        reportingManagerDiv.show();
                        reportingManagerSelect.prop('required', true);
                        
                        // Get the primary role (first selected)
                        const primaryRole = roles[0];
                        
                        // Filter and select appropriate manager based on primary role
                        let appropriateManager = '';
                        
                        if (['Design Team', 'Working Team', '3D Designing Team', 'Studio Trainees'].includes(primaryRole)) {
                            appropriateManager = 'Senior Manager (Studio)';
                        } else if (['Site Manager', 'Site Supervisor', 'Site Trainee'].includes(primaryRole)) {
                            appropriateManager = 'Senior Manager (Site)';
                        } else if (primaryRole === 'Social Media Manager' || primaryRole === 'Business Developer') {
                            appropriateManager = 'Senior Manager (Marketing)';
                        } else if (['Relationship Manager', 'Sales Manager', 'Sales Consultant', 'Field Sales Representative'].includes(primaryRole)) {
                            appropriateManager = 'Senior Manager (Sales)';
                        }
                        
                        // Select the appropriate manager
                        if (appropriateManager) {
                            reportingManagerSelect.find('option').each(function() {
                                if ($(this).data('role') === appropriateManager) {
                                    reportingManagerSelect.val($(this).val());
                                    return false;
                                }
                            });
                        }
                    } else {
                        reportingManagerDiv.hide();
                        reportingManagerSelect.prop('required', false);
                    }
                } else {
                    reportingManagerDiv.hide();
                    reportingManagerSelect.prop('required', false);
                }

                // Update department based on primary role
                const primaryRole = roles[0];
                if (primaryRole) {
                    let department = '';
                    
                    if (['Senior Manager (Studio)', 'Studio Trainees', '3D Designing Team', 'Working Team'].includes(primaryRole)) {
                        department = 'Studio';
                    } else if (['Senior Manager (Site)', 'Site Manager', 'Site Supervisor', 'Site Trainee'].includes(primaryRole)) {
                        department = 'Site';
                    } else if (['Senior Manager (Marketing)', 'Social Media Manager', 'Business Developer', 'Design Team'].includes(primaryRole)) {
                        department = 'Marketing';
                    } else if (['Senior Manager (Sales)', 'Sales Manager', 'Sales Consultant', 'Field Sales Representative', 'Relationship Manager'].includes(primaryRole)) {
                        department = 'Sales';
                    } else if (primaryRole === 'HR') {
                        department = 'HR';
                    } else if (primaryRole === 'admin') {
                        department = 'Administration';
                    }
                    
                    $('#department').val(department);
                }
            });

            // Trigger change event on page load if role is pre-selected
            if ($('#role').val()) {
                $('#role').trigger('change');
            }

            // Add validation for designation
            $('form').submit(function(e) {
                if (!$('#designation').val()) {
                    e.preventDefault();
                    alert('Please select a designation');
                    return false;
                }
            });

            // Auto-select designation based on role
            $('#role').change(function() {
                const role = $(this).val();
                const designationSelect = $('#designation');
                
                // Reset designation
                designationSelect.val('');
                
                // Auto-select based on role
                if (role) {
                    const roleDesignationMap = {
                        'Senior Manager (Studio)': 'Senior Architect',
                        'Design Team': 'Junior Architect',
                        '3D Designing Team': '3D Visualizer',
                        'Site Manager': 'Site Engineer',
                        'Site Supervisor': 'Site Supervisor',
                        'Social Media Manager': 'Digital Marketing Specialist',
                        'Sales Manager': 'Sales Manager',
                        'Sales Consultant': 'Sales Executive'
                    };
                    
                    if (roleDesignationMap[role]) {
                        designationSelect.val(roleDesignationMap[role]);
                    }
                }
            });

            // Auto-select department based on role
            $('#role').change(function() {
                const role = $(this).val();
                let department = '';
                
                // Map roles to departments
                if (['Senior Manager (Studio)', 'Studio Trainees', '3D Designing Team', 'Working Team'].includes(role)) {
                    department = 'Studio';
                } else if (['Senior Manager (Site)', 'Site Manager', 'Site Supervisor', 'Site Trainee'].includes(role)) {
                    department = 'Site';
                } else if (['Senior Manager (Marketing)', 'Social Media Manager', 'Business Developer', 'Design Team'].includes(role)) {
                    department = 'Marketing';
                } else if (['Senior Manager (Sales)', 'Sales Manager', 'Sales Consultant', 'Field Sales Representative', 'Relationship Manager'].includes(role)) {
                    department = 'Sales';
                } else if (role === 'HR') {
                    department = 'HR';
                } else if (role === 'admin') {
                    department = 'Administration';
                }
                
                // Set the department
                $('#department').val(department);
            });

            // Update designation options based on selected department
            $('#department').change(function() {
                const department = $(this).val();
                const designationSelect = $('#designation');
                
                // You might want to filter designations based on department
                // This requires additional backend work to get designations by department
            });

            function handleShiftChange() {
                const shiftType = document.getElementById('shift_type').value;
                const customFields = document.getElementById('custom_shift_fields');
                const shiftStart = document.getElementById('shift_start');
                const shiftEnd = document.getElementById('shift_end');

                if (shiftType === 'custom') {
                    customFields.style.display = 'block';
                    shiftStart.required = true;
                    shiftEnd.required = true;
                } else {
                    customFields.style.display = 'none';
                    shiftStart.required = false;
                    shiftEnd.required = false;

                    // Set hidden input values based on shift type
                    switch(shiftType) {
                        case 'morning':
                            shiftStart.value = '09:00';
                            shiftEnd.value = '18:00';
                            break;
                        case 'evening':
                            shiftStart.value = '14:00';
                            shiftEnd.value = '23:00';
                            break;
                        case 'night':
                            shiftStart.value = '21:00';
                            shiftEnd.value = '06:00';
                            break;
                        default:
                            shiftStart.value = '';
                            shiftEnd.value = '';
                    }
                }
            }

            // Form validation
            document.querySelector('form').addEventListener('submit', function(e) {
                const weeklyOffCheckboxes = document.querySelectorAll('input[name="weekly_off[]"]:checked');
                const shiftType = document.getElementById('shift_type').value;

                if (weeklyOffCheckboxes.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one weekly off day');
                    return;
                }

                if (!shiftType) {
                    e.preventDefault();
                    alert('Please select a shift type');
                    return;
                }

                // Log form data before submission
                console.log('Form Data:', {
                    shiftType: shiftType,
                    shiftStart: document.getElementById('shift_start').value,
                    shiftEnd: document.getElementById('shift_end').value,
                    weeklyOff: Array.from(weeklyOffCheckboxes).map(cb => cb.value)
                });
            });
        });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const weeklyOffCheckboxes = document.querySelectorAll('input[name="weekly_off[]"]');
        
        // Remove required attribute from all checkboxes except the first one
        weeklyOffCheckboxes.forEach((checkbox, index) => {
            if (index > 0) checkbox.removeAttribute('required');
        });
        
        form.addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('input[name="weekly_off[]"]:checked');
            
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one weekly off day');
                return false;
            }
            
            // Debug: Log selected values
            const selectedDays = Array.from(checkedBoxes).map(cb => cb.value);
            console.log('Selected weekly off days:', selectedDays);
        });
    });
    </script>
</body>
</html>
