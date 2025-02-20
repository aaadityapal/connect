<?php
session_start();
require_once 'config.php';

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
    </div>

    <script>
        function showTab(tabName) {
            // Hide all sections
            document.querySelectorAll('.settings-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected section and activate tab
            document.getElementById(tabName + '-settings').style.display = 'block';
            document.querySelector(`button[onclick="showTab('${tabName}')"]`).classList.add('active');
        }

        // Show success message using SweetAlert2
        <?php if (isset($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?php echo $success_message; ?>',
            timer: 2000,
            showConfirmButton: false
        });
        <?php endif; ?>

        // Show error message using SweetAlert2
        <?php if (isset($error_message)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo $error_message; ?>'
        });
        <?php endif; ?>
    </script>
</body>
</html> 