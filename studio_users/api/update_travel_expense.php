<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$id = str_replace('EXP-', '', $_POST['id'] ?? '');

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit();
}

try {
    // 1. Check if allowed to update (not approved)
    $checkQ = "SELECT id, status, manager_status, accountant_status, hr_status FROM travel_expenses WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($checkQ);
    $stmt->execute([$id, $user_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        throw new Exception("Expense not found or unauthorized");
    }
    // Block update if ANY approval has happened
    if ($existing['status'] === 'approved' || $existing['manager_status'] === 'approved' || $existing['accountant_status'] === 'approved' || $existing['hr_status'] === 'approved') {
        throw new Exception("This expense is already approved and cannot be updated.");
    }

    $amount = $_POST['amount'] ?? 0;
    $date = $_POST['date'] ?? null;
    $purpose = $_POST['purpose'] ?? '';
    $from = $_POST['from'] ?? '';
    $to = $_POST['to'] ?? '';
    $mode = $_POST['mode'] ?? '';
    $distance = $_POST['distance'] ?? 0;

    // ───── Server-side Date Validation (Max 15 days in past) ─────
    if ($date) {
        $tDate = new DateTime($date);
        $today = new DateTime();
        $today->setTime(0, 0, 0); // Normalize to start of day

        $interval = $today->diff($tDate);
        $daysDiff = $interval->days;

        if ($daysDiff > 15 && $tDate < $today) {
            throw new Exception("The selected date ($date) is more than 15 days in the past and cannot be saved.");
        }
    }

    $uploadDir = __DIR__ . '/../../uploads/travel_documents/';
    if (!is_dir($uploadDir))
        mkdir($uploadDir, 0777, true);

    $billPath = null;
    $meterStartPath = null;
    $meterEndPath = null;

    if (isset($_FILES['bill']))
        $billPath = uploadTravelFile($_FILES['bill'], 'bill', $user_id, $uploadDir);
    if (isset($_FILES['meter_start']))
        $meterStartPath = uploadTravelFile($_FILES['meter_start'], 'meter_start', $user_id, $uploadDir);
    if (isset($_FILES['meter_end']))
        $meterEndPath = uploadTravelFile($_FILES['meter_end'], 'meter_end', $user_id, $uploadDir);

    $query = "UPDATE travel_expenses SET 
                travel_date = ?, 
                purpose = ?, 
                from_location = ?, 
                to_location = ?, 
                mode_of_transport = ?, 
                distance = ?, 
                amount = ?";
    $params = [$date, $purpose, $from, $to, $mode, $distance, $amount];

    if ($billPath) {
        $query .= ", bill_file_path = ?";
        $params[] = $billPath;
    }
    if ($meterStartPath) {
        $query .= ", meter_start_photo_path = ?";
        $params[] = $meterStartPath;
    }
    if ($meterEndPath) {
        $query .= ", meter_end_photo_path = ?";
        $params[] = $meterEndPath;
    }

    $query .= " WHERE id = ? AND user_id = ?";
    $params[] = $id;
    $params[] = $user_id;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    // LOG ACTIVITY
    $logDescription = "Updated travel expense EXP-" . str_pad($id, 4, '0', STR_PAD_LEFT) . " for $purpose. Trip: $from to $to on $date via $mode ($distance km). New Total: ₹$amount.";
    $metadata = json_encode([
        'id' => $id,
        'action' => 'update',
        'details' => [
            'date' => $date,
            'purpose' => $purpose,
            'from' => $from,
            'to' => $to,
            'mode' => $mode,
            'distance' => $distance,
            'amount' => $amount
        ]
    ]);

    $logQuery = "INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at) 
                 VALUES (?, 'travel_updated', 'travel', ?, ?, ?, NOW())";
    $logStmt = $pdo->prepare($logQuery);
    $logStmt->execute([$user_id, $id, $logDescription, $metadata]);

    echo json_encode(['success' => true, 'message' => 'Expense updated successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Helper to handle file uploads
 */
function uploadTravelFile($file, $type, $user_id, $dir)
{
    if (!$file || !isset($file['tmp_name']) || empty($file['tmp_name']))
        return null;

    $origin = $file['name'];
    $ext = pathinfo($origin, PATHINFO_EXTENSION);
    $newName = 'travel_' . $user_id . '_' . time() . '_upd_' . $type . '.' . $ext;
    $dest = $dir . $newName;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return 'uploads/travel_documents/' . $newName;
    }
    return null;
}
