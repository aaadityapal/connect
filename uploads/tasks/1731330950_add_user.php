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
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }

        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
        }

        .card-body {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-control {
            height: 50px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 10px 20px;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-submit {
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(0, 0, 0, 0.1);
        }

        .input-group-text {
            background: none;
            border: none;
            color: #667eea;
        }

        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            color: #667eea;
            text-decoration: none;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            color: #764ba2;
            text-decoration: none;
        }

        .designation-group {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        #designation {
            height: 50px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }

        #designation:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        #designation optgroup {
            font-weight: 600;
            color: #2c3e50;
        }

        #designation option {
            font-weight: normal;
            padding: 8px;
        }

        .designation-badge {
            display: inline-block;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 500;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 15px;
            color: #fff;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        #reportingManagerDiv select {
            height: 50px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 10px 20px;
            background-color: white;
        }

        #reportingManagerDiv select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        #reportingManagerDiv option {
            padding: 10px;
        }

        .input-group-text {
            border-radius: 10px 0 0 10px;
            border: 2px solid #e9ecef;
            border-right: none;
        }

        #department {
            height: 50px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }

        #department:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .department-badge {
            display: inline-block;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 500;
            border-radius: 15px;
            background: #e3f2fd;
            color: #1565c0;
        }

        #role {
            height: auto !important;
            min-height: 100px;
        }

        #role option {
            padding: 8px 12px;
            margin: 2px 0;
            border-radius: 4px;
        }

        #role option:checked {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white;
        }

        #role:focus option:checked {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white;
        }

        .form-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .form-section h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2d3436;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2d3436;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: #4834d4;
            outline: none;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: background-color 0.3s;
        }

        .checkbox-label:hover {
            background-color: #f8f9fa;
        }

        .checkbox-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #4834d4;
        }
    </style>
</head>
<body>
    <a href="hr_dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <div class="container form-container">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus mr-2"></i>Add New User</h3>
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
