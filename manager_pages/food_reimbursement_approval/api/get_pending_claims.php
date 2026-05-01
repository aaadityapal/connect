<?php
/**
 * manager_pages/food_reimbursement_approval/api/get_pending_claims.php
 *
 * Fetches food reimbursement claims that require the current user's approval.
 * Evaluates the manager mapping:
 * - If current user is Level-1 Manager, shows claims where manager_status is Pending.
 * - If current user is Level-2 HR, shows claims where hr_status is Pending.
 * - Can also fetch based on Payment Permissions for the "Mark Paid" tab.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../../../config/db_connect.php';

$userId = (int)$_SESSION['user_id'];
$tab    = $_GET['tab'] ?? 'manager'; // 'manager', 'hr', or 'payment'

try {
    $claims = [];

    // Get current user's role and permissions
    $userStmt = $pdo->prepare("SELECT role FROM users WHERE id = :uid");
    $userStmt->execute([':uid' => $userId]);
    $userRole = $userStmt->fetchColumn() ?: '';

    $permStmt = $pdo->prepare("SELECT can_mark_paid FROM food_reimbursement_payment_permissions WHERE user_id = :uid");
    $permStmt->execute([':uid' => $userId]);
    $hasPaymentPerm = $permStmt->fetchColumn() ? true : false;

    $isAdminOrHr = in_array(strtolower($userRole), ['admin', 'hr']);

    // Base query elements for fetching claims
    // We fetch claims that are submitted and where they need action from the CURRENT USER.
    $sql = "
        SELECT 
            a.id,
            a.user_id AS employee_id,
            a.date,
            DATE_FORMAT(a.date, '%d %b %Y') AS date_fmt,
            a.punch_in,
            a.punch_out,
            TIME_FORMAT(a.punch_in, '%H:%i') AS punch_in_fmt,
            TIME_FORMAT(a.punch_out, '%H:%i') AS punch_out_fmt,
            a.work_report,
            frc.late_minutes,
            frc.manager_status,
            frc.hr_status,
            frc.payment_status,
            frc.claim_status,
            u.username AS employee_name,
            u.email AS employee_email,
            frc.category,
            frc.vendor_name,
            frc.description,
            frc.resubmit_count,
            frm.manager_id,
            frm.hr_id,
            COALESCE(frp.price_per_meal, 100.00) AS price_per_meal
        FROM attendance a
        JOIN food_reimbursement_claims frc ON a.id = frc.attendance_id
        JOIN users u ON a.user_id = u.id
        LEFT JOIN food_reimbursement_mapping frm ON a.user_id = frm.employee_id
        LEFT JOIN food_reimbursement_price frp ON a.user_id = frp.user_id
        WHERE frc.claim_status = 'submitted'
    ";

    // Filtering logic based on role
    // User can see the claim if:
    // 1. They are Admin/HR (see all claims)
    // 2. They are the mapped manager (see their employees' claims)
    // 3. They have payment permission (see claims that are fully approved and unpaid)
    
    $whereConditions = [];
    if ($isAdminOrHr) {
        // Admin/HR can see everything that is submitted
        $whereConditions[] = "1=1"; 
    } else {
        // Normal user logic
        $userConditions = [];
        // Mapped manager can see it
        $userConditions[] = "frm.manager_id = :uid";
        
        // If they have payment permission, they can see fully approved unpaid claims
        if ($hasPaymentPerm) {
            $userConditions[] = "(frc.manager_status = 'approved' AND frc.hr_status = 'approved' AND frc.payment_status = 'unpaid')";
        }
        
        $whereConditions[] = "(" . implode(" OR ", $userConditions) . ")";
    }
    
    if (!empty($whereConditions)) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " ORDER BY a.date DESC";

    $stmt = $pdo->prepare($sql);
    $params = [];
    if (!$isAdminOrHr) {
        $params[':uid'] = $userId;
    }
    
    $stmt->execute($params);
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($claims as &$claim) {
        $claim['can_approve_manager'] = ($claim['manager_id'] == $userId && $claim['manager_status'] === 'pending');
        $claim['can_approve_hr'] = ($isAdminOrHr && $claim['hr_status'] === 'pending' && $claim['manager_status'] === 'approved');
        $claim['can_mark_paid'] = ($hasPaymentPerm && $claim['manager_status'] === 'approved' && $claim['hr_status'] === 'approved' && $claim['payment_status'] === 'unpaid');
    }

    echo json_encode([
        'success' => true,
        'data'    => $claims,
        'role'    => $userRole
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
