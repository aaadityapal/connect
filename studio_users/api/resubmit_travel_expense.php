<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$id      = str_replace('EXP-', '', $_POST['id'] ?? '');

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit();
}

try {
    $checkQ = "SELECT id, status, resubmission_count, max_resubmissions FROM travel_expenses WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($checkQ);
    $stmt->execute([$id, $user_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        throw new Exception("Expense not found or unauthorized");
    }
    if ($existing['status'] !== 'rejected') {
        throw new Exception("Only rejected expenses can be resubmitted.");
    }
    
    $maxResub = $existing['max_resubmissions'] ?? 3;
    $currCount = $existing['resubmission_count'] ?? 0;
    
    if ($currCount >= $maxResub) {
        throw new Exception("Maximum resubmission limit ($maxResub) reached.");
    }

    $amount   = $_POST['amount'] ?? 0;
    $date     = $_POST['date'] ?? null;
    $purpose  = $_POST['purpose'] ?? '';
    $from     = $_POST['from'] ?? '';
    $to       = $_POST['to'] ?? '';
    $mode     = $_POST['mode'] ?? '';
    $distance = $_POST['distance'] ?? 0;

    $uploadDir = __DIR__ . '/../../uploads/travel_documents/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $billPath = null;
    $meterStartPath = null;
    $meterEndPath = null;

    if (isset($_FILES['bill']))         $billPath       = uploadTravelFile($_FILES['bill'], 'bill', $user_id, $uploadDir);
    if (isset($_FILES['meter_start']))  $meterStartPath = uploadTravelFile($_FILES['meter_start'], 'meter_start', $user_id, $uploadDir);
    if (isset($_FILES['meter_end']))    $meterEndPath   = uploadTravelFile($_FILES['meter_end'], 'meter_end', $user_id, $uploadDir);

    $query = "UPDATE travel_expenses SET 
                travel_date = ?, 
                purpose = ?, 
                from_location = ?, 
                to_location = ?, 
                mode_of_transport = ?, 
                distance = ?, 
                amount = ?,
                status = 'pending',
                manager_status = 'pending',
                hr_status = 'pending',
                accountant_status = 'pending',
                manager_reason = NULL,
                hr_reason = NULL,
                accountant_reason = NULL,
                resubmission_count = resubmission_count + 1,
                is_resubmitted = 1,
                resubmission_date = NOW()";
                
    $params = [$date, $purpose, $from, $to, $mode, $distance, $amount];

    if ($billPath) { $query .= ", bill_file_path = ?"; $params[] = $billPath; }
    if ($meterStartPath) { $query .= ", meter_start_photo_path = ?"; $params[] = $meterStartPath; }
    if ($meterEndPath) { $query .= ", meter_end_photo_path = ?"; $params[] = $meterEndPath; }

    $query .= " WHERE id = ? AND user_id = ?";
    $params[] = $id;
    $params[] = $user_id;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    // --- Log Activity for Notification ---
    $performerName = $_SESSION['username'] ?? 'User';
    $logQuery = "INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at) VALUES (?, 'travel_updated', 'travel_expense', ?, ?, ?, NOW())";
    
    $newCount = $currCount + 1;
    $desc = "You successfully resubmitted travel expense #{$id} for '{$purpose}'. Resubmission {$newCount} of {$maxResub}. Amount: ₹{$amount}.";
    $meta = json_encode([
        'purpose' => $purpose,
        'amount' => $amount,
        'mode' => $mode,
        'resubmission_count' => $newCount,
        'max_resubmissions' => $maxResub,
        'acted_by_name' => $performerName
    ]);
    
    $logStmt = $pdo->prepare($logQuery);
    $logStmt->execute([$user_id, $id, $desc, $meta]);

    echo json_encode(['success' => true, 'message' => 'Expense resubmitted successfully.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function uploadTravelFile($file, $type, $user_id, $dir) {
    if (!$file || !isset($file['tmp_name']) || empty($file['tmp_name'])) return null;
    $origin = $file['name'];
    $ext = pathinfo($origin, PATHINFO_EXTENSION);
    $newName = 'travel_' . $user_id . '_' . time() . '_resub_' . $type . '.' . $ext;
    $dest = $dir . $newName;
    if (move_uploaded_file($file['tmp_name'], $dest)) return 'uploads/travel_documents/' . $newName;
    return null;
}
