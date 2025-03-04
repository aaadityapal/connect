<?php
session_start();
require_once 'config.php';

// Add this near the top of the file after session_start()
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'company';

// Check authentication and HR role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Company Settings
        if (isset($_POST['company_settings'])) {
            $sql = "UPDATE company_settings SET 
                company_name = :company_name,
                company_address = :company_address,
                company_email = :company_email,
                company_phone = :company_phone,
                company_website = :company_website,
                tax_id = :tax_id,
                fiscal_year_start = :fiscal_year_start,
                timezone = :timezone,
                date_format = :date_format,
                currency = :currency
                WHERE id = 1";  // Assuming single company record

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'company_name' => $_POST['company_name'],
                'company_address' => $_POST['company_address'],
                'company_email' => $_POST['company_email'],
                'company_phone' => $_POST['company_phone'],
                'company_website' => $_POST['company_website'],
                'tax_id' => $_POST['tax_id'],
                'fiscal_year_start' => $_POST['fiscal_year_start'],
                'timezone' => $_POST['timezone'],
                'date_format' => $_POST['date_format'],
                'currency' => $_POST['currency']
            ]);
        }

        // Leave Settings
        if (isset($_POST['leave_settings'])) {
            $sql = "UPDATE leave_settings SET 
                annual_leave_days = :annual_leave_days,
                sick_leave_days = :sick_leave_days,
                casual_leave_days = :casual_leave_days,
                maternity_leave_days = :maternity_leave_days,
                paternity_leave_days = :paternity_leave_days,
                carry_forward_limit = :carry_forward_limit,
                leave_approval_chain = :leave_approval_chain
                WHERE id = 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'annual_leave_days' => $_POST['annual_leave_days'],
                'sick_leave_days' => $_POST['sick_leave_days'],
                'casual_leave_days' => $_POST['casual_leave_days'],
                'maternity_leave_days' => $_POST['maternity_leave_days'],
                'paternity_leave_days' => $_POST['paternity_leave_days'],
                'carry_forward_limit' => $_POST['carry_forward_limit'],
                'leave_approval_chain' => json_encode($_POST['leave_approval_chain'])
            ]);
        }

        // Attendance Settings
        if (isset($_POST['attendance_settings'])) {
            $sql = "UPDATE attendance_settings SET 
                work_hours_per_day = :work_hours,
                grace_time_minutes = :grace_time,
                half_day_hours = :half_day_hours,
                overtime_threshold = :overtime_threshold,
                weekend_days = :weekend_days,
                ip_restriction = :ip_restriction,
                allowed_ips = :allowed_ips
                WHERE id = 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'work_hours' => $_POST['work_hours'],
                'grace_time' => $_POST['grace_time'],
                'half_day_hours' => $_POST['half_day_hours'],
                'overtime_threshold' => $_POST['overtime_threshold'],
                'weekend_days' => json_encode($_POST['weekend_days']),
                'ip_restriction' => $_POST['ip_restriction'] ? 1 : 0,
                'allowed_ips' => json_encode($_POST['allowed_ips'])
            ]);
        }

        // Payroll Settings
        if (isset($_POST['payroll_settings'])) {
            $sql = "UPDATE payroll_settings SET 
                salary_calculation_type = :salary_type,
                payment_date = :payment_date,
                tax_calculation_method = :tax_method,
                pf_contribution_rate = :pf_rate,
                insurance_deduction = :insurance,
                bonus_calculation = :bonus_calc
                WHERE id = 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'salary_type' => $_POST['salary_type'],
                'payment_date' => $_POST['payment_date'],
                'tax_method' => $_POST['tax_method'],
                'pf_rate' => $_POST['pf_rate'],
                'insurance' => $_POST['insurance'],
                'bonus_calc' => json_encode($_POST['bonus_calc'])
            ]);
        }

        $success_message = "Settings updated successfully!";
    } catch (Exception $e) {
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

// Fetch current settings
$company_settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$leave_settings = $pdo->query("SELECT * FROM leave_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$attendance_settings = $pdo->query("SELECT * FROM attendance_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$payroll_settings = $pdo->query("SELECT * FROM payroll_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

// Add this after your existing queries
$users_query = "SELECT id, username, department FROM users WHERE status = 'active' ORDER BY username";
$users = $pdo->query($users_query)->fetchAll(PDO::FETCH_ASSOC);

// Update the acknowledgments query to match your table structure
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$filter_condition = $filter_user > 0 ? "AND da.user_id = :user_id" : "";

$acknowledgments_query = "
    SELECT 
        da.id,
        da.document_id,
        da.user_id,
        da.acknowledged_at,
        da.created_at as request_date,
        hd.original_name,
        hd.type as document_type,
        u.username,
        u.designation,
        u.department,
        CASE 
            WHEN da.acknowledged_at IS NOT NULL THEN 'acknowledged'
            ELSE 'pending'
        END as status,
        COALESCE(u.profile_picture, 'default.jpg') as profile_picture
    FROM document_acknowledgments da
    JOIN hr_documents hd ON da.document_id = hd.id
    JOIN users u ON da.user_id = u.id
    WHERE 1=1 $filter_condition
    ORDER BY da.acknowledged_at DESC";

// Add error reporting for debugging
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($filter_user > 0) {
        $stmt = $pdo->prepare($acknowledgments_query);
        $stmt->execute(['user_id' => $filter_user]);
        $acknowledgments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $acknowledgments = $pdo->query($acknowledgments_query)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Debug output
    echo "<!-- Query executed successfully -->\n";
    echo "<!-- Records found: " . count($acknowledgments) . " -->\n";
    
} catch (PDOException $e) {
    echo "<!-- Database Error: " . $e->getMessage() . " -->\n";
    $error_message = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    echo "<!-- General Error: " . $e->getMessage() . " -->\n";
    $error_message = "An error occurred: " . $e->getMessage();
}

function formatDocumentType($type) {
    return ucwords(str_replace('_', ' ', $type));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary-color: #4F46E5;
            --secondary-color: #7C3AED;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --dark-color: #111827;
            --light-color: #F3F4F6;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #F9FAFB;
            color: var(--dark-color);
            margin: 0;
            padding: 20px;
        }

        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .settings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .settings-title {
            font-size: 24px;
            font-weight: 600;
        }

        .settings-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 10px;
        }

        .tab-button {
            padding: 10px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 16px;
            color: var(--dark-color);
            opacity: 0.7;
            transition: all 0.3s;
        }

        .tab-button.active {
            opacity: 1;
            border-bottom: 2px solid var(--primary-color);
        }

        .settings-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            font-size: 14px;
        }

        .btn-save {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .btn-save:hover {
            background-color: var(--secondary-color);
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .alert-error {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .documents-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn-manage-docs {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-manage-docs:hover {
            background-color: var(--secondary-color);
            transform: translateY(-1px);
        }

        .documents-info {
            background-color: #F3F4F6;
            border-radius: 8px;
            padding: 20px;
        }

        .documents-info p {
            margin-bottom: 10px;
            font-weight: 500;
        }

        .documents-info ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .documents-info li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .documents-info li:before {
            content: '\f15c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: var(--primary-color);
        }

        .acknowledgments-table-wrapper {
            overflow-x: auto;
            margin-top: 20px;
        }

        .acknowledgments-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .acknowledgments-table th,
        .acknowledgments-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-color);
        }

        .acknowledgments-table th {
            background-color: #F8FAFC;
            font-weight: 600;
            color: var(--dark-color);
        }

        .acknowledgments-table tr:hover {
            background-color: #F8FAFC;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.acknowledged {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-badge.pending {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .no-records {
            text-align: center;
            color: var(--gray-600);
            padding: 20px !important;
        }

        .acknowledgments-table td {
            vertical-align: middle;
        }

        .employee-cell {
            min-width: 200px;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .employee-name {
            font-weight: 500;
            color: var(--dark-color);
        }

        .employee-designation {
            font-size: 12px;
            color: var(--gray-600);
        }

        .document-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .document-name {
            font-weight: 500;
            color: var(--dark-color);
        }

        .document-type {
            font-size: 12px;
            color: var(--gray-600);
            text-transform: capitalize;
        }

        .date-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .time-text {
            font-size: 12px;
            color: var(--gray-600);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-badge.acknowledged {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-badge.pending {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .acknowledgments-table th {
            white-space: nowrap;
            padding: 16px;
            font-size: 14px;
            background-color: #F8FAFC;
            border-bottom: 2px solid var(--gray-200);
        }

        .acknowledgments-table td {
            padding: 16px;
            vertical-align: middle;
        }

        .acknowledgments-table tr:hover {
            background-color: #F8FAFC;
        }

        @media (max-width: 768px) {
            .acknowledgments-table-wrapper {
                margin: 0 -20px;
            }
            
            .employee-designation,
            .document-type,
            .time-text {
                display: none;
            }
            
            .employee-avatar {
                width: 32px;
                height: 32px;
            }
            
            .status-badge {
                padding: 4px 8px;
            }
        }

        .filter-controls {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-form {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .filter-form .form-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-form label {
            font-weight: 500;
            color: var(--gray-600);
            white-space: nowrap;
        }

        .filter-form select {
            min-width: 250px;
            padding: 8px 12px;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            background-color: white;
        }

        .acknowledgments-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-card i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray-600);
        }

        @media (max-width: 768px) {
            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-form {
                flex-direction: column;
            }

            .filter-form .form-group {
                width: 100%;
            }

            .filter-form select {
                width: 100%;
            }

            .acknowledgments-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <div class="settings-header">
            <h1 class="settings-title">HR Settings</h1>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="settings-tabs">
            <button class="tab-button active" onclick="showTab('company')">Company</button>
            <button class="tab-button" onclick="showTab('leave')">Leave</button>
            <button class="tab-button" onclick="showTab('attendance')">Attendance</button>
            <button class="tab-button" onclick="showTab('payroll')">Payroll</button>
            <button class="tab-button" onclick="showTab('documents')">Documents</button>
            <button class="tab-button" onclick="showTab('acknowledge')">Acknowledge Documents</button>
            <!-- Add other tab buttons as needed -->
        </div>

        <!-- Company Settings -->
        <div id="company-settings" class="settings-section">
            <form method="POST">
                <input type="hidden" name="company_settings" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="company_name">Company Name</label>
                        <input type="text" id="company_name" name="company_name" class="form-control" 
                               value="<?php echo htmlspecialchars($company_settings['company_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="company_email">Company Email</label>
                        <input type="email" id="company_email" name="company_email" class="form-control"
                               value="<?php echo htmlspecialchars($company_settings['company_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="company_phone">Company Phone</label>
                        <input type="text" id="company_phone" name="company_phone" class="form-control"
                               value="<?php echo htmlspecialchars($company_settings['company_phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="company_website">Company Website</label>
                        <input type="url" id="company_website" name="company_website" class="form-control"
                               value="<?php echo htmlspecialchars($company_settings['company_website'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="tax_id">Tax ID</label>
                        <input type="text" id="tax_id" name="tax_id" class="form-control"
                               value="<?php echo htmlspecialchars($company_settings['tax_id'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="fiscal_year_start">Fiscal Year Start</label>
                        <input type="date" id="fiscal_year_start" name="fiscal_year_start" class="form-control"
                               value="<?php echo htmlspecialchars($company_settings['fiscal_year_start'] ?? ''); ?>">
                    </div>
                </div>
                <button type="submit" class="btn-save">Save Company Settings</button>
            </form>
        </div>

        <!-- Leave Settings -->
        <div id="leave-settings" class="settings-section" style="display: none;">
            <form method="POST">
                <input type="hidden" name="leave_settings" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="annual_leave_days">Annual Leave Days</label>
                        <input type="number" id="annual_leave_days" name="annual_leave_days" class="form-control"
                               value="<?php echo htmlspecialchars($leave_settings['annual_leave_days'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="sick_leave_days">Sick Leave Days</label>
                        <input type="number" id="sick_leave_days" name="sick_leave_days" class="form-control"
                               value="<?php echo htmlspecialchars($leave_settings['sick_leave_days'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="casual_leave_days">Casual Leave Days</label>
                        <input type="number" id="casual_leave_days" name="casual_leave_days" class="form-control"
                               value="<?php echo htmlspecialchars($leave_settings['casual_leave_days'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="carry_forward_limit">Carry Forward Limit</label>
                        <input type="number" id="carry_forward_limit" name="carry_forward_limit" class="form-control"
                               value="<?php echo htmlspecialchars($leave_settings['carry_forward_limit'] ?? ''); ?>">
                    </div>
                </div>
                <button type="submit" class="btn-save">Save Leave Settings</button>
            </form>
        </div>

        <!-- Attendance Settings -->
        <div id="attendance-settings" class="settings-section" style="display: none;">
            <form method="POST">
                <input type="hidden" name="attendance_settings" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="work_hours">Work Hours Per Day</label>
                        <input type="number" id="work_hours" name="work_hours" class="form-control" step="0.5"
                               value="<?php echo htmlspecialchars($attendance_settings['work_hours_per_day'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="grace_time">Grace Time (Minutes)</label>
                        <input type="number" id="grace_time" name="grace_time" class="form-control"
                               value="<?php echo htmlspecialchars($attendance_settings['grace_time_minutes'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="half_day_hours">Half Day Hours</label>
                        <input type="number" id="half_day_hours" name="half_day_hours" class="form-control" step="0.5"
                               value="<?php echo htmlspecialchars($attendance_settings['half_day_hours'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="overtime_threshold">Overtime Threshold (Hours)</label>
                        <input type="number" id="overtime_threshold" name="overtime_threshold" class="form-control" step="0.5"
                               value="<?php echo htmlspecialchars($attendance_settings['overtime_threshold'] ?? ''); ?>">
                    </div>
                </div>
                <button type="submit" class="btn-save">Save Attendance Settings</button>
            </form>
        </div>

        <!-- Payroll Settings -->
        <div id="payroll-settings" class="settings-section" style="display: none;">
            <form method="POST">
                <input type="hidden" name="payroll_settings" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="salary_type">Salary Calculation Type</label>
                        <select id="salary_type" name="salary_type" class="form-control">
                            <option value="monthly" <?php echo ($payroll_settings['salary_calculation_type'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="hourly" <?php echo ($payroll_settings['salary_calculation_type'] ?? '') === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_date">Payment Date</label>
                        <input type="number" id="payment_date" name="payment_date" class="form-control" min="1" max="31"
                               value="<?php echo htmlspecialchars($payroll_settings['payment_date'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="pf_rate">PF Contribution Rate (%)</label>
                        <input type="number" id="pf_rate" name="pf_rate" class="form-control" step="0.01"
                               value="<?php echo htmlspecialchars($payroll_settings['pf_contribution_rate'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="tax_method">Tax Calculation Method</label>
                        <select id="tax_method" name="tax_method" class="form-control">
                            <option value="progressive" <?php echo ($payroll_settings['tax_calculation_method'] ?? '') === 'progressive' ? 'selected' : ''; ?>>Progressive</option>
                            <option value="flat" <?php echo ($payroll_settings['tax_calculation_method'] ?? '') === 'flat' ? 'selected' : ''; ?>>Flat Rate</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-save">Save Payroll Settings</button>
            </form>
        </div>

        <!-- Documents Settings -->
        <div id="documents-settings" class="settings-section" style="display: none;">
            <div class="documents-header">
                <h2>HR Documents Management</h2>
                <a href="hr_documents_manager.php" class="btn-manage-docs">
                    <i class="fas fa-file-alt"></i>
                    Manage HR Documents
                </a>
            </div>
            <div class="documents-info">
                <p>Manage all HR-related documents including:</p>
                <ul>
                    <li>HR Policies</li>
                    <li>Employee Handbooks</li>
                    <li>Company Guidelines</li>
                    <li>Training Materials</li>
                    <li>Forms and Templates</li>
                </ul>
            </div>
        </div>

        <!-- Acknowledge Documents Settings -->
        <div id="acknowledge-settings" class="settings-section" style="display: none;">
            <div class="documents-header">
                <h2>Document Acknowledgments</h2>
                <div class="filter-controls">
                    <form id="filterForm" class="filter-form" method="GET">
                        <div class="form-group">
                            <label for="user_filter">Filter by Employee:</label>
                            <select id="user_filter" name="user_id" class="form-control" onchange="submitFilter(this)">
                                <option value="0">All Employees</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                            <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username']); ?> 
                                        (<?php echo htmlspecialchars($user['department']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="tab" value="acknowledge">
                    </form>
                </div>
            </div>
            
            <div class="acknowledgments-stats">
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo count(array_filter($acknowledgments, function($a) { return $a['status'] === 'acknowledged'; })); ?></span>
                        <span class="stat-label">Acknowledged</span>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo count(array_filter($acknowledgments, function($a) { return $a['status'] === 'pending'; })); ?></span>
                        <span class="stat-label">Pending</span>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-file-alt"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo count($acknowledgments); ?></span>
                        <span class="stat-label">Total Documents</span>
                    </div>
                </div>
            </div>

            <div class="acknowledgments-table-wrapper">
                <table class="acknowledgments-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Document Details</th>
                            <th>Department</th>
                            <th>Acknowledgment Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($acknowledgments)): ?>
                        <tr>
                            <td colspan="5" class="no-records">No acknowledgments found</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($acknowledgments as $ack): ?>
                            <tr>
                                <td class="employee-cell">
                                    <div class="employee-info">
                                        <?php
                                        $profilePicPath = 'uploads/profile_pictures/' . $ack['profile_picture'];
                                        $defaultPicPath = 'assets/images/default-avatar.png';
                                        $displayPicPath = file_exists($profilePicPath) ? $profilePicPath : $defaultPicPath;
                                        
                                        // Debug profile picture paths
                                        echo "<!-- 
                                            Profile Picture Debug:
                                            Attempted path: {$profilePicPath}
                                            File exists: " . (file_exists($profilePicPath) ? 'Yes' : 'No') . "
                                            Using path: {$displayPicPath}
                                        -->\n";
                                        ?>
                                        <img src="<?php echo htmlspecialchars($displayPicPath); ?>" 
                                             alt="Profile" 
                                             class="employee-avatar"
                                             onerror="this.src='<?php echo $defaultPicPath; ?>'">
                                        <div>
                                            <div class="employee-name"><?php echo htmlspecialchars($ack['username']); ?></div>
                                            <div class="employee-designation"><?php echo htmlspecialchars($ack['designation']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="document-info">
                                        <div class="document-name"><?php echo htmlspecialchars($ack['original_name']); ?></div>
                                        <div class="document-type"><?php echo htmlspecialchars(formatDocumentType($ack['document_type'])); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($ack['department']); ?></td>
                                <td>
                                    <div class="date-info">
                                        <div><?php echo date('M d, Y', strtotime($ack['acknowledged_at'])); ?></div>
                                        <div class="time-text"><?php echo date('h:i A', strtotime($ack['acknowledged_at'])); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($ack['status']); ?>">
                                        <?php 
                                        // Make sure to display the actual status from database
                                        $status = $ack['status'] ? htmlspecialchars($ack['status']) : 'pending';
                                        echo $status;
                                        ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Define the showTab function
        function showTab(tabName) {
            // Hide all sections
            const sections = document.querySelectorAll('.settings-section');
            sections.forEach(section => {
                section.style.display = 'none';
            });

            // Show the selected section
            const selectedSection = document.getElementById(tabName + '-settings');
            if (selectedSection) {
                selectedSection.style.display = 'block';
            }

            // Update active state of tab buttons
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(button => {
                button.classList.remove('active');
            });

            // Add active class to selected tab
            const activeButton = document.querySelector(`button[onclick="showTab('${tabName}')"]`);
            if (activeButton) {
                activeButton.classList.add('active');
            }
        }

        // Function to handle filter submission
        function submitFilter(selectElement) {
            const form = document.getElementById('filterForm');
            // Always set tab to 'acknowledge' when filtering
            const tabInput = form.querySelector('input[name="tab"]');
            if (tabInput) {
                tabInput.value = 'acknowledge';
            }
            form.submit();
        }

        // Initialize the page with the correct tab
        document.addEventListener('DOMContentLoaded', function() {
            const currentTab = '<?php echo $current_tab; ?>';
            showTab(currentTab);
            
            // Log debug information
            console.log('Database connection status:', '<?php echo isset($pdo) ? "Connected" : "Not connected"; ?>');
            console.log('Query results:', <?php echo json_encode($acknowledgments ?? null); ?>);
            
            <?php if (isset($error_message)): ?>
            console.error('Error:', <?php echo json_encode($error_message); ?>);
            <?php endif; ?>
        });
    </script>
</body>
</html> 