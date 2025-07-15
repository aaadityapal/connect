<?php
// Enable error reporting for troubleshooting but don't display to users
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Create a log file for debugging
$logFile = 'logs/labour_attendance_add_errors.log';
if (!file_exists(dirname($logFile))) {
    @mkdir(dirname($logFile), 0777, true);
}

function logError($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    @error_log($logMessage, 3, $logFile);
}

try {
    // Include database connection
    require_once 'config/db_connect.php';

    // Session and authentication check
    session_start();

    // Restrict access to HR role only
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // User not logged in, redirect to login page
        header("Location: login.php?redirect=add_labour_attendance.php");
        exit();
    }

    // Check if user has HR role
    if ($_SESSION['role'] !== 'HR') {
        // User doesn't have HR role, redirect to unauthorized page
        header("Location: unauthorized.php");
        exit();
    }

    // Initialize variables for the form
    $events = [];
    $vendors = [];
    $formSubmitted = false;
    $message = '';
    $messageType = '';

    // Check if the tables exist before proceeding
    $tableCheckQuery = "SELECT COUNT(*) AS table_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sv_calendar_events'";
    $stmt = $pdo->query($tableCheckQuery);
    $tableCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tableCheck || $tableCheck['table_exists'] == 0) {
        throw new Exception("Required tables not found in database. Please run the setup script first.");
    }

    // Fetch events for dropdown
    $eventsQuery = "SELECT event_id, title FROM sv_calendar_events ORDER BY title ASC";
    $events = $pdo->query($eventsQuery)->fetchAll();

    // Check if vendors table exists
    $tableCheckQuery = "SELECT COUNT(*) AS table_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sv_event_vendors'";
    $stmt = $pdo->query($tableCheckQuery);
    $tableCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tableCheck && $tableCheck['table_exists'] > 0) {
        // Fetch vendors for dropdown
        $vendorsQuery = "SELECT vendor_id, vendor_name, vendor_type FROM sv_event_vendors ORDER BY vendor_name";
        $vendors = $pdo->query($vendorsQuery)->fetchAll();
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $formSubmitted = true;
        
        // Determine if it's company or vendor labour
        $labourType = $_POST['labour_type'] ?? '';
        
        if ($labourType === 'company') {
            // Handle company labour submission
            $eventId = $_POST['event_id'] ?? 0;
            $labourName = $_POST['labour_name'] ?? '';
            $contactNumber = $_POST['contact_number'] ?? '';
            $attendanceDate = $_POST['attendance_date'] ?? date('Y-m-d');
            $morningAttendance = $_POST['morning_attendance'] ?? 'present';
            $eveningAttendance = $_POST['evening_attendance'] ?? 'present';
            $dailyWage = $_POST['daily_wage'] ?? 0;
            
            // Validate input
            if (empty($labourName) || $eventId <= 0) {
                throw new Exception("Labour name and construction site are required fields.");
            }
            
            // Insert into database
            $query = "INSERT INTO sv_company_labours (
                event_id, labour_name, contact_number, attendance_date,
                morning_attendance, evening_attendance, daily_wage,
                sequence_number, is_deleted, created_by, created_at
            ) VALUES (
                :event_id, :labour_name, :contact_number, :attendance_date,
                :morning_attendance, :evening_attendance, :daily_wage,
                1, 0, :created_by, NOW()
            )";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':event_id' => $eventId,
                ':labour_name' => $labourName,
                ':contact_number' => $contactNumber,
                ':attendance_date' => $attendanceDate,
                ':morning_attendance' => $morningAttendance,
                ':evening_attendance' => $eveningAttendance,
                ':daily_wage' => $dailyWage,
                ':created_by' => $_SESSION['user_id']
            ]);
            
            $message = "Company labour attendance record added successfully!";
            $messageType = "success";
            
        } elseif ($labourType === 'vendor') {
            // Handle vendor labour submission
            $vendorId = $_POST['vendor_id'] ?? 0;
            $labourName = $_POST['labour_name'] ?? '';
            $contactNumber = $_POST['contact_number'] ?? '';
            $attendanceDate = $_POST['attendance_date'] ?? date('Y-m-d');
            $morningAttendance = $_POST['morning_attendance'] ?? 'present';
            $eveningAttendance = $_POST['evening_attendance'] ?? 'present';
            $wageRate = $_POST['wage_rate'] ?? 0;
            
            // Validate input
            if (empty($labourName) || $vendorId <= 0) {
                throw new Exception("Labour name and vendor are required fields.");
            }
            
            // Insert into database
            $query = "INSERT INTO sv_vendor_labours (
                vendor_id, labour_name, contact_number, attendance_date,
                morning_attendance, evening_attendance, wage_rate,
                sequence_number, is_deleted, created_by, created_at
            ) VALUES (
                :vendor_id, :labour_name, :contact_number, :attendance_date,
                :morning_attendance, :evening_attendance, :wage_rate,
                1, 0, :created_by, NOW()
            )";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':vendor_id' => $vendorId,
                ':labour_name' => $labourName,
                ':contact_number' => $contactNumber,
                ':attendance_date' => $attendanceDate,
                ':morning_attendance' => $morningAttendance,
                ':evening_attendance' => $eveningAttendance,
                ':wage_rate' => $wageRate,
                ':created_by' => $_SESSION['user_id']
            ]);
            
            $message = "Vendor labour attendance record added successfully!";
            $messageType = "success";
        } else {
            throw new Exception("Invalid labour type selected.");
        }
        
        // Log the success
        logError("Labour attendance record added - Type: $labourType, Name: $labourName");
    }
    
} catch (Exception $e) {
    // Log the error
    logError("Error in add_labour_attendance.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Set error message
    $message = "Error: " . $e->getMessage();
    $messageType = "error";
    
    // Initialize empty arrays if not set
    if (!isset($events)) $events = [];
    if (!isset($vendors)) $vendors = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Labour Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #f72585;
            --success-color: #4cc9f0;
            --warning-color: #f8961e;
            --danger-color: #f94144;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
            font-size: 16px;
        }

        .container {
            width: 95%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 15px;
        }

        header {
            background: linear-gradient(to right, #3a7bd5, #00d2ff);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 18px 0;
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            position: sticky;
            top: 0;
            z-index: 100;
            color: white;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 25px;
        }
        
        .header-left, .header-right {
            display: flex;
            align-items: center;
        }

        .logo {
            font-size: 26px;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            letter-spacing: 0.5px;
        }

        .logo i {
            font-size: 28px;
            filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.1));
        }

        .form-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-bottom: 30px;
        }

        .form-title {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            outline: none;
        }

        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px 12px;
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            outline: none;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: var(--light-color);
            color: var(--dark-color);
        }

        .btn-secondary:hover {
            background-color: #e2e6ea;
            transform: translateY(-2px);
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            font-weight: 500;
        }

        .message-success {
            background-color: rgba(76, 201, 240, 0.15);
            color: var(--success-color);
            border: 1px solid rgba(76, 201, 240, 0.3);
        }

        .message-error {
            background-color: rgba(249, 65, 68, 0.15);
            color: var(--danger-color);
            border: 1px solid rgba(249, 65, 68, 0.3);
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-left: -10px;
            margin-right: -10px;
        }

        .form-col {
            flex: 1;
            padding: 0 10px;
            min-width: 200px;
        }

        .section-title {
            font-size: 18px;
            color: var(--dark-color);
            margin: 25px 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .form-col {
                margin-bottom: 15px;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <div class="header-left">
                    <div class="logo">
                        <i class="fas fa-clipboard-list"></i>
                        Labour Attendance
                    </div>
                </div>
            </div>
        </header>

        <div class="form-container">
            <h2 class="form-title">Add Labour Attendance Record</h2>
            
            <?php if ($formSubmitted && !empty($message)): ?>
                <div class="message message-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <div class="form-group">
                    <label for="labour_type" class="form-label">Labour Type</label>
                    <select id="labour_type" name="labour_type" class="form-select" required onchange="toggleLabourTypeFields()">
                        <option value="">Select Labour Type</option>
                        <option value="company">Company Labour</option>
                        <option value="vendor">Vendor Labour</option>
                    </select>
                </div>
                
                <div id="company_fields" style="display: none;">
                    <div class="form-group">
                        <label for="event_id" class="form-label">Construction Site</label>
                        <select id="event_id" name="event_id" class="form-select">
                            <option value="">Select Construction Site</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['event_id']; ?>">
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div id="vendor_fields" style="display: none;">
                    <div class="form-group">
                        <label for="vendor_id" class="form-label">Vendor</label>
                        <select id="vendor_id" name="vendor_id" class="form-select">
                            <option value="">Select Vendor</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?php echo $vendor['vendor_id']; ?>">
                                    <?php echo htmlspecialchars($vendor['vendor_name'] . ' (' . $vendor['vendor_type'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="labour_name" class="form-label">Labour Name</label>
                    <input type="text" id="labour_name" name="labour_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="contact_number" class="form-label">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="attendance_date" class="form-label">Attendance Date</label>
                    <input type="date" id="attendance_date" name="attendance_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="morning_attendance" class="form-label">Morning Attendance</label>
                            <select id="morning_attendance" name="morning_attendance" class="form-select">
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="evening_attendance" class="form-label">Evening Attendance</label>
                            <select id="evening_attendance" name="evening_attendance" class="form-select">
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group" id="daily_wage_field" style="display: none;">
                            <label for="daily_wage" class="form-label">Daily Wage (₹)</label>
                            <input type="number" id="daily_wage" name="daily_wage" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group" id="wage_rate_field" style="display: none;">
                            <label for="wage_rate" class="form-label">Wage Rate (₹)</label>
                            <input type="number" id="wage_rate" name="wage_rate" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="labour_attendance.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Record
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleLabourTypeFields() {
            const labourType = document.getElementById('labour_type').value;
            const companyFields = document.getElementById('company_fields');
            const vendorFields = document.getElementById('vendor_fields');
            const dailyWageField = document.getElementById('daily_wage_field');
            const wageRateField = document.getElementById('wage_rate_field');
            
            // Hide all specific fields first
            companyFields.style.display = 'none';
            vendorFields.style.display = 'none';
            dailyWageField.style.display = 'none';
            wageRateField.style.display = 'none';
            
            // Show fields based on labour type
            if (labourType === 'company') {
                companyFields.style.display = 'block';
                dailyWageField.style.display = 'block';
                
                // Make company fields required
                document.getElementById('event_id').required = true;
                document.getElementById('vendor_id').required = false;
                
            } else if (labourType === 'vendor') {
                vendorFields.style.display = 'block';
                wageRateField.style.display = 'block';
                
                // Make vendor fields required
                document.getElementById('vendor_id').required = true;
                document.getElementById('event_id').required = false;
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Log console info for debugging
            console.log('Add Labour Attendance Form Loaded');
            
            // Check if required tables exist and have data
            <?php if (isset($events) && empty($events)): ?>
            console.warn('No construction sites found in the database. You need to add construction sites first.');
            <?php endif; ?>
            
            <?php if (isset($vendors) && empty($vendors)): ?>
            console.warn('No vendors found in the database. You need to add vendors first if you want to add vendor labour.');
            <?php endif; ?>
            
            // Initialize the form
            toggleLabourTypeFields();
        });
    </script>
</body>
</html> 