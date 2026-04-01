<?php
/**
 * FETCH TRAVEL APPROVALS (HIERARCHICAL)
 * manager_pages/travel_expenses_approval/api/fetch_approvals.php
 */
session_start();
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';
$is_admin = (strtolower($user_role) === 'admin' || strtolower($user_role) === 'administrator');

// Fetch explicit payment permission
$pay_auth_stmt = $pdo->prepare("SELECT 1 FROM travel_payment_auth WHERE user_id = ?");
$pay_auth_stmt->execute([$current_user_id]);
$can_pay_auth = $pay_auth_stmt->fetchColumn() ? true : false;
if ($is_admin) $can_pay_auth = true;

try {
    /**
     * LOGIC:
     * 1. Check Approval Window for current user.
     * 2. If Admin: Show ALL pending travel expenses.
     * 3. If Manager/HR/SrMgr: Show expenses waiting specifically for them.
     */
    $sched_stmt = $pdo->prepare("SELECT active_days, start_time, end_time FROM travel_approver_schedules WHERE user_id = ?");
    $sched_stmt->execute([$current_user_id]);
    $sched = $sched_stmt->fetch(PDO::FETCH_ASSOC);

    $is_window_open = false;
    $window_message = "Approval window not configured.";

    if ($is_admin) {
        $is_window_open = true;
        $window_message = "Admin override.";
    } else {
        $active_days = $sched ? explode(',', $sched['active_days']) : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $start_time = $sched ? $sched['start_time'] : '09:00:00';
        $end_time = $sched ? $sched['end_time'] : '18:00:00';
        
        // Ensure accurate Indian Standard Time evaluation
        date_default_timezone_set('Asia/Kolkata');
        $current_day = date('l');
        $current_time = date('H:i:s');
        
        if (in_array($current_day, $active_days)) {
            if ($current_time >= $start_time && $current_time <= $end_time) {
                $is_window_open = true;
                $window_message = "Window open.";
            } else {
                $window_message = "Approvals allowed " . date('h:i A', strtotime($start_time)) . " to " . date('h:i A', strtotime($end_time)) . ".";
            }
        } else {
            $window_message = "Approvals are not allowed on $current_day.";
        }
    }
    
    $query = "
        SELECT 
            te.*, 
            u.username as employee_name,
            u.employee_id as employee_code,
            u.role as employee_role,
            m.manager_id,
            m.hr_id,
            m.senior_manager_id,
            tea.file_path as extra_file_path,
            tea.file_name as extra_file_name,
            tea.file_type as extra_file_type,
            COALESCE(trc.require_meters, 0) as require_meters,
            COALESCE(tmmc.meter_mode, 0) as meter_mode,
            att.punch_in_photo,
            att.punch_out_photo
        FROM travel_expenses te
        JOIN users u ON te.user_id = u.id
        LEFT JOIN travel_expense_mapping m ON te.user_id = m.employee_id
        LEFT JOIN travel_expense_attachments tea ON te.id = tea.expense_id
        LEFT JOIN travel_role_config trc ON u.role = trc.role_name
        LEFT JOIN travel_meter_mode_config tmmc ON te.user_id = tmmc.user_id
        LEFT JOIN attendance att ON te.user_id = att.user_id AND te.travel_date = att.date
        WHERE 
            -- Admin sees everything
            (:is_admin = 1)
            
            OR
            
            -- Case 1: Current user is the assigned Manager
            (m.manager_id = :curr_id1)
            
            OR
            
            -- Case 2: Current user is the assigned HR
            (m.hr_id = :curr_id2)
            
            OR
            
            -- Case 3: Current user is the assigned Senior Manager
            (m.senior_manager_id = :curr_id3)
        ORDER BY te.travel_date DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':is_admin' => $is_admin ? 1 : 0,
        ':curr_id1' => $current_user_id,
        ':curr_id2' => $current_user_id,
        ':curr_id3' => $current_user_id
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $expenses = [];
    foreach ($rows as $row) {
        $id = $row['id'];
        
        if (!isset($expenses[$id])) {
            // Determine if they are responsible for this record (even if waiting)
            $is_responsible = (
                ($row['manager_id'] == $current_user_id && strtolower($row['manager_status']) === 'pending') ||
                ($row['hr_id'] == $current_user_id && strtolower($row['hr_status']) === 'pending') ||
                ($row['senior_manager_id'] == $current_user_id && strtolower($row['accountant_status']) === 'pending')
            );
            
            if ($is_admin) $acting_level = 'Administrator (Oversight)';
            elseif ($row['manager_id'] == $current_user_id) $acting_level = 'Manager (L1)';
            elseif ($row['hr_id'] == $current_user_id) $acting_level = 'HR (L2)';
            elseif ($row['senior_manager_id'] == $current_user_id) $acting_level = 'Senior Manager (L3)';

            if ($is_admin && $row['status'] == 'pending') {
                $can_act = true; 
            } elseif ($row['manager_id'] == $current_user_id && $row['manager_status'] == 'pending' && $row['status'] == 'pending') {
                $can_act = true;
            } elseif ($row['hr_id'] == $current_user_id && $row['hr_status'] == 'pending' && $row['status'] == 'pending') {
                $can_act = true;
            } elseif ($row['senior_manager_id'] == $current_user_id && $row['accountant_status'] == 'pending' && $row['status'] == 'pending') {
                // Dependency: L3 must wait for L1 and L2
                if (strtolower($row['manager_status']) === 'approved' && strtolower($row['hr_status']) === 'approved') {
                    $can_act = true;
                } else {
                    $can_act = false;
                    $window_message = "⏳ Waiting for Manager (L1) & HR (L2) to approve first.";
                }
            }

            $expenses[$id] = [
                'id' => $row['id'],
                'display_id' => 'EXP-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
                'employee_name' => $row['employee_name'],
                'employee_code' => $row['employee_code'],
                'employee_role' => $row['employee_role'],
                'purpose' => $row['purpose'],
                'from' => $row['from_location'],
                'to' => $row['to_location'],
                'mode' => $row['mode_of_transport'],
                'date' => $row['travel_date'],
                'distance' => $row['distance'],
                'amount' => $row['amount'],
                'status' => $row['status'],
                'manager_status' => $row['manager_status'],
                'manager_reason' => $row['manager_reason'],
                'hr_status' => $row['hr_status'],
                'hr_reason' => $row['hr_reason'],
                'accountant_status' => $row['accountant_status'],
                'accountant_reason' => $row['accountant_reason'],
                'acting_level' => $acting_level,
                'needs_action' => $is_responsible || $is_admin,
                'can_act' => ($can_act && $is_window_open),
                'is_window_open' => $is_window_open,
                'window_message' => $window_message,
                'require_meters' => (int)$row['require_meters'],
                'meter_mode' => (int)$row['meter_mode'],
                'punch_in_photo' => $row['punch_in_photo'],
                'punch_out_photo' => $row['punch_out_photo'],
                'meter_start_photo_path' => $row['meter_start_photo_path'],
                'meter_end_photo_path' => $row['meter_end_photo_path'],
                'confirmed_distance' => $row['confirmed_distance'],
                'hr_confirmed_distance' => $row['hr_confirmed_distance'],
                'senior_confirmed_distance' => $row['senior_confirmed_distance'],
                'resubmission_count' => $row['resubmission_count'] ?? 0,
                'max_resubmissions' => $row['max_resubmissions'] ?? 3,
                'payment_status' => $row['payment_status'] ?? 'Pending',
                'can_pay' => $can_pay_auth,
                'attachments' => []
            ];

            // Add standard attachments if they exist
            if (!empty($row['bill_file_path'])) {
                $expenses[$id]['attachments'][] = ['path' => $row['bill_file_path'], 'type' => 'bill'];
            }
        }
        
        // Add extra attachments if they exist
        if (!empty($row['extra_file_path'])) {
            $expenses[$id]['attachments'][] = [
                'path' => $row['extra_file_path'],
                'type' => $row['extra_file_type']
            ];
        }
    }

    $approvals = array_values($expenses);

    // Post-process statuses for Global Rejection
    foreach ($approvals as &$row) {
        // If the claim is rejected anywhere, it's rejected everywhere for the UI
        if ($row['status'] === 'rejected') {
            if ($row['manager_status'] === 'pending') $row['manager_status'] = 'rejected';
            if ($row['hr_status'] === 'pending') $row['hr_status'] = 'rejected';
            if ($row['accountant_status'] === 'pending') $row['accountant_status'] = 'rejected';
        }
    }

    echo json_encode(['success' => true, 'data' => $approvals]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
