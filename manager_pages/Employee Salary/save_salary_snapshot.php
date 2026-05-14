<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
    exit;
}

$month = isset($input['month']) ? (int)$input['month'] : 0;
$year = isset($input['year']) ? (int)$input['year'] : 0;
$rows = isset($input['rows']) && is_array($input['rows']) ? $input['rows'] : [];

if ($month < 1 || $month > 12 || $year < 2000 || $year > (int)date('Y') + 5) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid month or year']);
    exit;
}

if (count($rows) === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No rows provided']);
    exit;
}

$tableName = 'employee_salary_snapshot_records_20260513';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$tableName` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `month` TINYINT NOT NULL,
        `year` SMALLINT NOT NULL,
        `user_id` INT NOT NULL,
        `employee_id` VARCHAR(50) NOT NULL,
        `employee_name` VARCHAR(255) NOT NULL,
        `role` VARCHAR(255) NOT NULL,
        `gross_salary` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `base_salary` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `tds_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0,
        `payable_salary` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `working_days` DECIMAL(6,2) NOT NULL DEFAULT 0,
        `present_days` DECIMAL(6,2) NOT NULL DEFAULT 0,
        `late_days` DECIMAL(6,2) NOT NULL DEFAULT 0,
        `one_hour_late` DECIMAL(6,2) NOT NULL DEFAULT 0,
        `leave_taken` DECIMAL(6,2) NOT NULL DEFAULT 0,
        `leave_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `late_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `one_hour_late_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `fourth_saturday_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `penalty_days` DECIMAL(6,2) NOT NULL DEFAULT 0,
        `salary_calculated_days` DECIMAL(6,2) NOT NULL DEFAULT 0,
        `net_payable_salary` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `net_payable_salary_tds` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `payable_salary_after_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `overtime_hours` DECIMAL(8,2) NOT NULL DEFAULT 0,
        `overtime_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `ot_tds` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `payable_ot_after_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `total_tds_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `total_payable_salary` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `created_by` INT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_month_year` (`month`, `year`),
        UNIQUE KEY `uniq_user_month_year` (`user_id`, `month`, `year`),
        INDEX `idx_user_month_year` (`user_id`, `month`, `year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO `$tableName` (
        month, year, user_id, employee_id, employee_name, role,
        gross_salary, base_salary, tds_percentage, payable_salary,
        working_days, present_days, late_days, one_hour_late, leave_taken,
        leave_deduction, late_deduction, one_hour_late_deduction,
        fourth_saturday_deduction, penalty_days, salary_calculated_days,
        net_payable_salary, net_payable_salary_tds,
        payable_salary_after_deduction, overtime_hours, overtime_amount,
        ot_tds, payable_ot_after_deduction, total_tds_amount,
        total_payable_salary, created_by
    ) VALUES (
        :month, :year, :user_id, :employee_id, :employee_name, :role,
        :gross_salary, :base_salary, :tds_percentage, :payable_salary,
        :working_days, :present_days, :late_days, :one_hour_late, :leave_taken,
        :leave_deduction, :late_deduction, :one_hour_late_deduction,
        :fourth_saturday_deduction, :penalty_days, :salary_calculated_days,
        :net_payable_salary, :net_payable_salary_tds,
        :payable_salary_after_deduction, :overtime_hours, :overtime_amount,
        :ot_tds, :payable_ot_after_deduction, :total_tds_amount,
        :total_payable_salary, :created_by
    ) ON DUPLICATE KEY UPDATE
        employee_id = VALUES(employee_id),
        employee_name = VALUES(employee_name),
        role = VALUES(role),
        gross_salary = VALUES(gross_salary),
        base_salary = VALUES(base_salary),
        tds_percentage = VALUES(tds_percentage),
        payable_salary = VALUES(payable_salary),
        working_days = VALUES(working_days),
        present_days = VALUES(present_days),
        late_days = VALUES(late_days),
        one_hour_late = VALUES(one_hour_late),
        leave_taken = VALUES(leave_taken),
        leave_deduction = VALUES(leave_deduction),
        late_deduction = VALUES(late_deduction),
        one_hour_late_deduction = VALUES(one_hour_late_deduction),
        fourth_saturday_deduction = VALUES(fourth_saturday_deduction),
        penalty_days = VALUES(penalty_days),
        salary_calculated_days = VALUES(salary_calculated_days),
        net_payable_salary = VALUES(net_payable_salary),
        net_payable_salary_tds = VALUES(net_payable_salary_tds),
        payable_salary_after_deduction = VALUES(payable_salary_after_deduction),
        overtime_hours = VALUES(overtime_hours),
        overtime_amount = VALUES(overtime_amount),
        ot_tds = VALUES(ot_tds),
        payable_ot_after_deduction = VALUES(payable_ot_after_deduction),
        total_tds_amount = VALUES(total_tds_amount),
        total_payable_salary = VALUES(total_payable_salary),
        created_by = VALUES(created_by),
        created_at = CURRENT_TIMESTAMP
    ");

    $savedCount = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $stmt->execute([
            ':month' => $month,
            ':year' => $year,
            ':user_id' => (int)($row['user_id'] ?? 0),
            ':employee_id' => (string)($row['employee_id'] ?? ''),
            ':employee_name' => (string)($row['employee_name'] ?? ''),
            ':role' => (string)($row['role'] ?? ''),
            ':gross_salary' => (float)($row['gross_salary'] ?? 0),
            ':base_salary' => (float)($row['base_salary'] ?? 0),
            ':tds_percentage' => (float)($row['tds_percentage'] ?? 0),
            ':payable_salary' => (float)($row['payable_salary'] ?? 0),
            ':working_days' => (float)($row['working_days'] ?? 0),
            ':present_days' => (float)($row['present_days'] ?? 0),
            ':late_days' => (float)($row['late_days'] ?? 0),
            ':one_hour_late' => (float)($row['one_hour_late'] ?? 0),
            ':leave_taken' => (float)($row['leave_taken'] ?? 0),
            ':leave_deduction' => (float)($row['leave_deduction'] ?? 0),
            ':late_deduction' => (float)($row['late_deduction'] ?? 0),
            ':one_hour_late_deduction' => (float)($row['one_hour_late_deduction'] ?? 0),
            ':fourth_saturday_deduction' => (float)($row['fourth_saturday_deduction'] ?? 0),
            ':penalty_days' => (float)($row['penalty_days'] ?? 0),
            ':salary_calculated_days' => (float)($row['salary_calculated_days'] ?? 0),
            ':net_payable_salary' => (float)($row['net_payable_salary'] ?? 0),
            ':net_payable_salary_tds' => (float)($row['net_payable_salary_tds'] ?? 0),
            ':payable_salary_after_deduction' => (float)($row['payable_salary_after_deduction'] ?? 0),
            ':overtime_hours' => (float)($row['overtime_hours'] ?? 0),
            ':overtime_amount' => (float)($row['overtime_amount'] ?? 0),
            ':ot_tds' => (float)($row['ot_tds'] ?? 0),
            ':payable_ot_after_deduction' => (float)($row['payable_ot_after_deduction'] ?? 0),
            ':total_tds_amount' => (float)($row['total_tds_amount'] ?? 0),
            ':total_payable_salary' => (float)($row['total_payable_salary'] ?? 0),
            ':created_by' => (int)$_SESSION['user_id']
        ]);

        $savedCount++;
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "Saved {$savedCount} salary rows successfully.",
        'table' => $tableName
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Error saving salary snapshot: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save salary snapshot.'
    ]);
}
?>
