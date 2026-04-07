<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Authentication failure.']);
    exit();
}

require_once '../../../config/db_connect.php';

try {
    $actorId = (int)$_SESSION['user_id'];
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'attendance_action_permissions'");
    if (!$tableCheck->fetchColumn()) {
        throw new Exception("attendance_action_permissions table not found. Please run 2026_04_07_create_attendance_action_permissions_table.sql first.");
    }

    $stmtPerm = $pdo->prepare("SELECT can_edit_attendance FROM attendance_action_permissions WHERE user_id = ? LIMIT 1");
    $stmtPerm->execute([$actorId]);
    $canEdit = (int)($stmtPerm->fetchColumn() ?: 0) === 1;
    if (!$canEdit) {
        throw new Exception("You are not allowed to edit attendance.");
    }

    $userId = $_POST['user_id'] ?? '';
    $date = $_POST['attendance_date'] ?? '';
    
    // Parse times checking for empty inputs that equate to null constraints
    $punchIn = !empty($_POST['punch_in']) ? trim($_POST['punch_in']) : null;
    $punchOut = !empty($_POST['punch_out']) ? trim($_POST['punch_out']) : null;
    $workReport = !empty($_POST['work_report']) ? trim($_POST['work_report']) : null;

    if (empty($userId) || empty($date)) {
        throw new Exception("Missing crucial mapping identifiers. Form collision.");
    }
    
    if (empty($workReport)) {
        throw new Exception("A detailed Work Report is strictly required to manually edit attendance parameters.");
    }
    
    // Server-Side 20 Word Validation Protocol (Excluding Emojis and Special Characters)
    // We sanitize out everything excluding native alphabet and numerical strings
    $cleanReport = preg_replace('/[^\p{L}\p{N}\s]/u', '', $workReport);
    $wordArray = preg_split('/\s+/', $cleanReport, -1, PREG_SPLIT_NO_EMPTY);
    $wordCount = count($wordArray);
    
    if ($wordCount < 20) {
        throw new Exception("Work report constraint validation failed. Strictly 20 valid alphanumeric words required. You provided $wordCount.");
    }
    
    if (empty($punchIn) || empty($punchOut)) {
        throw new Exception("Both Punch In and Punch Out times are strictly required.");
    }

    // Standardize time strings for strict MySQL TIME column requirements
    if ($punchIn !== null && strlen($punchIn) === 5) {
        $punchIn .= ':00';
    }
    if ($punchOut !== null && strlen($punchOut) === 5) {
        $punchOut .= ':00';
    }

    // Handle File Uploads (Target Directory)
    $uploadDir = '../../../uploads/attendance/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $dateStr = date('Ymd', strtotime($date));
    
    $punchInPhoto = null;
    if (isset($_FILES['punch_in_photo']) && $_FILES['punch_in_photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['punch_in_photo']['name'], PATHINFO_EXTENSION));
        $filename = $userId . '_' . $dateStr . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['punch_in_photo']['tmp_name'], $uploadDir . $filename);
        $punchInPhoto = 'uploads/attendance/' . $filename; // store full web-relative path
    }

    $punchOutPhoto = null;
    if (isset($_FILES['punch_out_photo']) && $_FILES['punch_out_photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['punch_out_photo']['name'], PATHINFO_EXTENSION));
        $filename = $userId . '_' . $dateStr . '_' . (time() + 1) . '.' . $ext;
        move_uploaded_file($_FILES['punch_out_photo']['tmp_name'], $uploadDir . $filename);
        $punchOutPhoto = 'uploads/attendance/' . $filename; // store full web-relative path
    }

    $pdo->beginTransaction();

    // Verify presence logic explicitly mapped to target row
    $stmtCheck = $pdo->prepare("SELECT id, punch_in, punch_out, work_report, punch_in_photo, punch_out_photo FROM attendance WHERE user_id = ? AND date = ? LIMIT 1");
    $stmtCheck->execute([$userId, $date]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    // Fetch target user's username securely for description string
    $stmtTargetUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmtTargetUser->execute([$userId]);
    $targetUsername = $stmtTargetUser->fetchColumn() ?: "User #$userId";
    
    $adminId = $_SESSION['user_id'];
    
    // Fetch admin user's name
    $stmtAdminUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmtAdminUser->execute([$adminId]);
    $adminUsername = $stmtAdminUser->fetchColumn() ?: "Admin/Manager";
    
    $description = "";
    $updatesString = "";

    if ($existing) {
        $oldIn = $existing['punch_in'] ?: 'None';
        $oldOut = $existing['punch_out'] ?: 'None';
        $newIn = $punchIn ?: 'None';
        $newOut = $punchOut ?: 'None';
        
        $changes = [];
        if ($oldIn !== $newIn) $changes[] = "In: [$oldIn -> $newIn]";
        if ($oldOut !== $newOut) $changes[] = "Out: [$oldOut -> $newOut]";
        if ($existing['work_report'] !== $workReport) $changes[] = "Work Report Adjusted";
        if ($punchInPhoto !== null) $changes[] = "Punch-In Media Overwritten";
        if ($punchOutPhoto !== null) $changes[] = "Punch-Out Media Overwritten";

        $updatesString = empty($changes) ? "Saved with identical parameters" : implode(', ', $changes);
        $description = "$adminUsername modified attendance for $targetUsername on $date. Updates: $updatesString";

        // Execute Overwrite Block if log exists intrinsically
        $stmtUpdate = $pdo->prepare("
            UPDATE attendance 
            SET punch_in = ?, 
                punch_out = ?, 
                work_report = ?,
                punch_in_photo = COALESCE(?, punch_in_photo),
                punch_out_photo = COALESCE(?, punch_out_photo)
            WHERE id = ?
        ");
        $stmtUpdate->execute([$punchIn, $punchOut, $workReport, $punchInPhoto, $punchOutPhoto, $existing['id']]);
        
    } else {
        $newIn = $punchIn ?: 'None';
        $newOut = $punchOut ?: 'None';
        
        $changes = [];
        $changes[] = "In: [$newIn]";
        $changes[] = "Out: [$newOut]";
        if ($workReport !== null) $changes[] = "Work Report Added";
        if ($punchInPhoto !== null) $changes[] = "Punch-In Media Appended";
        if ($punchOutPhoto !== null) $changes[] = "Punch-Out Media Appended";
        
        $updatesString = implode(', ', $changes);
        $description = "$adminUsername created a manual attendance record for $targetUsername on $date. Updates: $updatesString";

        // Execute Insertion Block logic if establishing from raw
        if ($punchIn !== null || $punchOut !== null || $workReport !== null) {
            $stmtInsert = $pdo->prepare("
                INSERT INTO attendance (user_id, date, punch_in, punch_out, work_report, punch_in_photo, punch_out_photo, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'present')
            ");
            $stmtInsert->execute([$userId, $date, $punchIn, $punchOut, $workReport, $punchInPhoto, $punchOutPhoto]);
        } else {
            // Nothing to execute natively if form is completely blank
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'No action required. Blank mapping.']);
            exit();
        }
    }

    // Insert tracking log
    $stmtLog = $pdo->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata) VALUES (?, 'update_attendance', 'attendance', ?, ?, ?)");
    $logMeta = json_encode([
        'target_user' => $userId,
        'date' => $date,
        'updates_string' => $updatesString,
        'old_in' => $oldIn ?? null,
        'new_in' => $newIn ?? null,
        'old_out' => $oldOut ?? null,
        'new_out' => $newOut ?? null,
        'old_work_report' => $existing ? $existing['work_report'] : null,
        'new_work_report' => $workReport ?? null,
        'punch_in_photo_updated' => ($punchInPhoto !== null),
        'punch_out_photo_updated' => ($punchOutPhoto !== null),
        'admin_name' => $adminUsername,
        'target_name' => $targetUsername,
        'work_report_logged' => true
    ]);
    $stmtLog->execute([$adminId, $userId, $description, $logMeta]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Log updated securely.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
