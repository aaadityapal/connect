<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/db_connect.php';

function normalize_role($role, $custom_role) {
    $role = trim((string)$role);
    if ($role === '__custom__') {
        return trim((string)$custom_role);
    }
    return $role;
}

function should_block_admin($current_role, $new_role) {
    $current = strtolower(trim((string)$current_role));
    return ($current === 'hr' || $current === 'human resources') && strtolower($new_role) === 'admin';
}

function role_prefix_map() {
    return [
        'admin' => 'ADM',
        'HR' => 'HR',
        'Senior Manager (Studio)' => 'SMS',
        'Senior Manager (Site)' => 'SMT',
        'Senior Manager (Marketing)' => 'SMM',
        'Senior Manager (Sales)' => 'SML',
        'Senior Manager (Purchase)' => 'SMP',
        'Design Team' => 'DT',
        'Working Team' => 'WT',
        'Interior Designer' => 'ID',
        'Senior Interior Designer' => 'SID',
        'Junior Interior Designer' => 'JID',
        'Lead Interior Designer' => 'LID',
        'Associate Interior Designer' => 'AID',
        'Interior Design Coordinator' => 'IDC',
        'Interior Design Assistant' => 'IDA',
        'FF&E Designer' => 'FFE',
        'Interior Stylist' => 'IS',
        'Interior Design Intern' => 'IDI',
        '3D Designing Team' => '3DT',
        'Studio Trainees' => 'STR',
        'Business Developer' => 'BD',
        'Social Media Manager' => 'SMD',
        'Social Media Marketing' => 'SMMKT',
        'Site Manager' => 'STM',
        'Site Coordinator' => 'STC',
        'Site Supervisor' => 'STS',
        'Site Trainee' => 'STT',
        'Relationship Manager' => 'RM',
        'Sales Manager' => 'SM',
        'Sales Consultant' => 'SC',
        'Field Sales Representative' => 'FSR',
        'Purchase Manager' => 'PM',
        'Purchase Executive' => 'PE',
        'Graphic Designer' => 'GD',
        'Sales' => 'SL',
        'Purchase' => 'PR',
        'Maid Back Office' => 'MBO'
    ];
}

function build_role_prefix($role) {
    $map = role_prefix_map();
    if (isset($map[$role])) {
        return $map[$role];
    }
    $words = preg_split('/\s+/', preg_replace('/[^a-zA-Z0-9\s]/', ' ', $role));
    $letters = '';
    foreach ($words as $word) {
        $word = trim($word);
        if ($word === '') {
            continue;
        }
        $letters .= strtoupper($word[0]);
        if (strlen($letters) >= 3) {
            break;
        }
    }
    if (strlen($letters) < 2) {
        return 'EMP';
    }
    return $letters;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = normalize_role($_POST['role'] ?? '', $_POST['role_custom'] ?? '');
        $reporting_manager = trim($_POST['reporting_manager'] ?? '');
        $joining_date = trim($_POST['joining_date'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($username === '' || $email === '' || $password === '' || $role === '') {
            throw new Exception('All required fields must be filled.');
        }

        if (should_block_admin($_SESSION['role'] ?? '', $role)) {
            throw new Exception('You do not have permission to create admin users.');
        }

        $dupStmt = $pdo->prepare('SELECT username, email, phone FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1');
        $dupStmt->execute([$username, $email, $phone]);
        $dup = $dupStmt->fetch(PDO::FETCH_ASSOC);
        if ($dup) {
            if (strcasecmp($dup['username'], $username) === 0) {
                throw new Exception('Username already exists.');
            }
            if (strcasecmp($dup['email'], $email) === 0) {
                throw new Exception('Email already exists.');
            }
            if ($phone !== '' && $dup['phone'] === $phone) {
                throw new Exception('Phone number already exists.');
            }
            throw new Exception('Duplicate user data found.');
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $prefix = build_role_prefix($role);

        $stmt = $pdo->prepare("SELECT unique_id FROM users WHERE unique_id LIKE ? ORDER BY unique_id DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $last_id = $result['unique_id'];
            $number = intval(substr($last_id, strlen($prefix)));
            $next_id = $number + 1;
        } else {
            $next_id = 1;
        }

        $unique_id = $prefix . str_pad($next_id, 3, '0', STR_PAD_LEFT);
        $checkUniqueStmt = $pdo->prepare('SELECT 1 FROM users WHERE unique_id = ? LIMIT 1');
        while (true) {
            $checkUniqueStmt->execute([$unique_id]);
            if (!$checkUniqueStmt->fetchColumn()) {
                break;
            }
            $next_id++;
            $unique_id = $prefix . str_pad($next_id, 3, '0', STR_PAD_LEFT);
        }

        $backup_plain = '@rchitectshive@750';
        $backup_hashed = password_hash($backup_plain, PASSWORD_DEFAULT);

        $hasMustChange = false;
        try {
            $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetch(PDO::FETCH_ASSOC);
            if (!$col) {
                $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
            }
            $col2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_changed_at'")->fetch(PDO::FETCH_ASSOC);
            if (!$col2) {
                $pdo->exec("ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL");
            }

            $hasMustChange = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $hasMustChange = false;
        }

        $sql = $hasMustChange ? "INSERT INTO users (
                    username,
                    email,
                    password,
                    role,
                    unique_id,
                    reporting_manager,
                    designation,
                    position,
                    department,
                    backup_password,
                    must_change_password,
                    status,
                    created_at,
                    joining_date,
                    phone
                ) VALUES (
                    :username,
                    :email,
                    :password,
                    :role,
                    :unique_id,
                    :reporting_manager,
                    :designation,
                    :position,
                    :department,
                    :backup_password,
                    1,
                    'active',
                    :created_at,
                    :joining_date,
                    :phone
                )" : "INSERT INTO users (
                    username,
                    email,
                    password,
                    role,
                    unique_id,
                    reporting_manager,
                    designation,
                    position,
                    department,
                    backup_password,
                    status,
                    created_at,
                    joining_date,
                    phone
                ) VALUES (
                    :username,
                    :email,
                    :password,
                    :role,
                    :unique_id,
                    :reporting_manager,
                    :designation,
                    :position,
                    :department,
                    :backup_password,
                    'active',
                    :created_at,
                    :joining_date,
                    :phone
                )";

        $current_time = date('Y-m-d H:i:s');

        $params = [
            ':username' => $username,
            ':email' => $email,
            ':password' => $hashed_password,
            ':role' => $role,
            ':unique_id' => $unique_id,
            ':reporting_manager' => $reporting_manager !== '' ? $reporting_manager : null,
            ':designation' => $role,
            ':position' => 'Employee',
            ':department' => $department !== '' ? $department : 'General',
            ':backup_password' => $backup_hashed,
            ':created_at' => $current_time,
            ':joining_date' => $joining_date !== '' ? $joining_date : null,
            ':phone' => $phone !== '' ? $phone : null
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $new_id = $pdo->lastInsertId();

        // ─── Auto-Allot Leave Bank ────────────────────────────────────────────
        // Mirrors the same logic in process_add_user.php and process_signup.php.
        // Seeds one leave_bank row per active leave type for the current year.
        try {
            $year = date('Y');
            $ltStmt = $pdo->query("SELECT id, max_days FROM leave_types WHERE status = 'active'");
            $bankStmt = $pdo->prepare(
                "INSERT IGNORE INTO leave_bank (user_id, leave_type_id, total_balance, remaining_balance, year)
                 VALUES (?, ?, ?, ?, ?)"
            );
            foreach ($ltStmt->fetchAll(PDO::FETCH_ASSOC) as $lt) {
                $max = (float)$lt['max_days'];
                $bankStmt->execute([$new_id, $lt['id'], $max, $max, $year]);
            }
        } catch (Exception $e) {
            // Non-fatal — log but don't block the employee creation
            error_log('[add_employee_handler] leave_bank seed failed: ' . $e->getMessage());
        }
        // ─────────────────────────────────────────────────────────────────────

        $_SESSION['add_employee_success'] = [
            'unique_id' => $unique_id,
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'reporting_manager' => $reporting_manager,
            'joining_date' => $joining_date,
            'department' => $department,
            'phone' => $phone,
            'temp_password' => $password
        ];

        $_SESSION['success'] = 'Employee added successfully.';
        header('Location: ../employees_profile/index.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['add_employee_error'] = $e->getMessage();
        header('Location: ../employees_profile/index.php');
        exit();
    }
}

header('Location: ../employees_profile/index.php');
exit();
