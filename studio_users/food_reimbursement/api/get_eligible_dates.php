<?php
/**
 * food_reimbursement/api/get_eligible_dates.php
 *
 * Returns attendance rows for the logged-in user where punch_out >= 21:00:00
 * (i.e. they left after 9:00 PM).
 * Optionally filtered by ?month=YYYY-MM
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../config/db_connect.php';

try {
    $userId = (int) $_SESSION['user_id'];

    // Optional month filter: ?month=2026-04
    $month = $_GET['month'] ?? '';

    $params = [$userId];
    $monthClause = '';

    if (!empty($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
        $monthClause = "AND DATE_FORMAT(a.date, '%Y-%m') = ?";
        $params[]    = $month;
    }

    $sql = "
        SELECT
            a.id,
            a.date,
            a.punch_in,
            a.punch_out,
            a.status,
            a.work_report,
            TIMESTAMPDIFF(MINUTE, CONCAT(a.date, ' 21:00:00'), a.punch_out) AS late_minutes,
            frc.claim_status,
            frc.manager_status,
            frc.hr_status,
            frc.payment_status,
            frc.amount,
            frc.category,
            frc.meal_type,
            frc.vendor_name,
            frc.description,
            frc.notes,
            frc.manager_note,
            frc.hr_note,
            frc.resubmit_count,
            mgr.username AS manager_name,
            COALESCE(frp.price_per_meal, 100.00) AS price_per_meal
        FROM attendance a
        LEFT JOIN food_reimbursement_claims frc ON a.id = frc.attendance_id
        LEFT JOIN food_reimbursement_mapping frm ON a.user_id = frm.employee_id
        LEFT JOIN users mgr ON frm.manager_id = mgr.id
        LEFT JOIN food_reimbursement_price frp ON a.user_id = frp.user_id
        WHERE a.user_id = ?
          AND a.punch_out IS NOT NULL
          AND TIME(a.punch_out) >= '21:00:00'
          {$monthClause}
        ORDER BY a.date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format times for display
    foreach ($rows as &$row) {
        $row['punch_in_fmt']  = $row['punch_in']  ? date('h:i A', strtotime($row['punch_in']))  : '—';
        $row['punch_out_fmt'] = $row['punch_out'] ? date('h:i A', strtotime($row['punch_out'])) : '—';
        $row['date_fmt']      = date('d M Y', strtotime($row['date']));
        $row['late_minutes']  = max(0, (int) $row['late_minutes']);
    }
    unset($row);

    echo json_encode([
        'success' => true,
        'data'    => $rows,
        'total'   => count($rows),
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch data: ' . $e->getMessage(),
    ]);
}
