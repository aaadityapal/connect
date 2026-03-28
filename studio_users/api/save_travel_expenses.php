<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    if (!isset($_POST['expenses']) || !is_array($_POST['expenses'])) {
        echo json_encode(['success' => false, 'message' => 'No expense data provided']);
        exit();
    }

    $expenses = $_POST['expenses'];
    $pdo->beginTransaction();

    $uploadDir = __DIR__ . '/../../uploads/travel_documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $insertedCount = 0;

    foreach ($expenses as $index => $data) {
        $travel_date = $data['date'] ?? null;
        $purpose    = $data['purpose'] ?? '';
        $from       = $data['from'] ?? '';
        $to         = $data['to'] ?? '';
        $mode       = $data['mode'] ?? '';
        $distance   = $data['distance'] ?? 0;
        $amount     = $data['amount'] ?? 0;
        $notes      = $data['notes'] ?? '';

        if (!$travel_date || !$from || !$to || !$mode) continue;

        // ───── Server-side Date Validation (Max 15 days in past) ─────
        $tDate = new DateTime($travel_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0); // Normalize to start of day
        $interval = $today->diff($tDate);
        $daysDiff = $interval->days;

        if ($daysDiff > 15 && $tDate < $today) {
            echo json_encode(['success' => false, 'message' => "Expense on $travel_date is older than 15 days and cannot be submitted."]);
            $pdo->rollBack();
            exit();
        }

        // Handle File Uploads for this specific index
        $billPath = null;
        $meterStartPath = null;
        $meterEndPath = null;

        // PHP $_FILES structure for nested arrays: $_FILES['expenses']['name'][$index]['bill']
        if (isset($_FILES['expenses']['tmp_name'][$index])) {
            $fileData = $_FILES['expenses'];
            
            // 1. Bill Photo
            if (!empty($fileData['tmp_name'][$index]['bill'])) {
                $billPath = uploadTravelFile($fileData, $index, 'bill', $user_id, $uploadDir);
            }
            // 2. Meter Start
            if (!empty($fileData['tmp_name'][$index]['meter_start'])) {
                $meterStartPath = uploadTravelFile($fileData, $index, 'meter_start', $user_id, $uploadDir);
            }
            // 3. Meter End
            if (!empty($fileData['tmp_name'][$index]['meter_end'])) {
                $meterEndPath = uploadTravelFile($fileData, $index, 'meter_end', $user_id, $uploadDir);
            }
        }

        $query = "INSERT INTO travel_expenses (
                    user_id, travel_date, purpose, from_location, to_location, 
                    mode_of_transport, distance, amount, notes, status,
                    bill_file_path, meter_start_photo_path, meter_end_photo_path,
                    created_at
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $user_id, $travel_date, $purpose, $from, $to,
            $mode, $distance, $amount, $notes,
            $billPath, $meterStartPath, $meterEndPath
        ]);

        $newId = $pdo->lastInsertId();
        
        // Log Activity
        $logDescription = "Added new travel expense #$newId for $purpose. Trip: $from to $to on $travel_date via $mode ($distance km). Total: ₹$amount.";
        $metadata = json_encode([
            'id' => $newId,
            'date' => $travel_date,
            'purpose' => $purpose,
            'from' => $from,
            'to' => $to,
            'mode' => $mode,
            'distance' => $distance,
            'amount' => $amount
        ]);
        
        $logQuery = "INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at) 
                     VALUES (?, 'travel_added', 'travel', ?, ?, ?, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->execute([$user_id, $newId, $logDescription, $metadata]);
        
        $insertedCount++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "$insertedCount expenses saved successfully"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Helper to handle the weird PHP $_FILES structure for nested arrays
 */
function uploadTravelFile($fileData, $index, $field, $user_id, $uploadDir) {
    $tmpName = $fileData['tmp_name'][$index][$field];
    $origin  = $fileData['name'][$index][$field];
    
    if (!$tmpName) return null;

    $ext = pathinfo($origin, PATHINFO_EXTENSION);
    $newName = 'travel_' . $user_id . '_' . time() . '_' . $index . '_' . $field . '.' . $ext;
    $dest = $uploadDir . $newName;

    if (move_uploaded_file($tmpName, $dest)) {
        return 'uploads/travel_documents/' . $newName;
    }
    return null;
}
?>
