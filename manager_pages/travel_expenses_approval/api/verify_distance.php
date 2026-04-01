<?php
/**
 * VERIFY DISTANCE
 * manager_pages/travel_expenses_approval/api/verify_distance.php
 */
session_start();
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id']) || !isset($input['confirmed_distance'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    $expense_id = $input['id'];
    $confirmed_distance = $input['confirmed_distance'];
    $acting_level = $input['acting_level'] ?? '';
    $confirmed_by = $_SESSION['user_id'];

    // 1. Fetch current distances to check for a match
    $checkStmt = $pdo->prepare("SELECT confirmed_distance, hr_confirmed_distance, distance FROM travel_expenses WHERE id = ?");
    $checkStmt->execute([$expense_id]);
    $expense = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        echo json_encode(['success' => false, 'message' => 'Expense not found']);
        exit();
    }

    // 2. Determine who the "other" person is
    $other_distance = ($acting_level === 'HR (L2)') ? $expense['confirmed_distance'] : $expense['hr_confirmed_distance'];

    // 3. Match Logic: 
    // A. Comparison with the Original Claim:
    if (floatval($confirmed_distance) > floatval($expense['distance'])) {
         echo json_encode([
            'success' => false, 
            'message' => "❌ Invalid! The verified distance cannot be more than the user's original claimed distance. Please calculate accurately from the photos."
        ]);
        exit();
    }

    // B. Comparison with the other verifier (if someone has already done it):
    if ($other_distance !== null && $other_distance !== "") {
        $tolerance = 3.0; // KM tolerance
        $diff = abs(floatval($confirmed_distance) - floatval($other_distance));
        
        if ($diff > $tolerance) {
             echo json_encode([
                'success' => false, 
                'message' => "❌ Mismatch! Your reading ($confirmed_distance KM) does not match the other verifier's reading. Difference must be <= 3 KM."
            ]);
            exit();
        }
    }

    // 4. Update the DB based on role
    if ($acting_level === 'HR (L2)') {
        $query = "
            UPDATE travel_expenses 
            SET 
                hr_confirmed_distance = :distance,
                hr_id = :confirmed_by,
                hr_confirmed_at = NOW()
            WHERE id = :id
        ";
    } elseif ($acting_level === 'Senior Manager (L3)') {
        $query = "
            UPDATE travel_expenses 
            SET 
                senior_confirmed_distance = :distance,
                senior_id = :confirmed_by,
                senior_confirmed_at = NOW()
            WHERE id = :id
        ";
    } else {
        $query = "
            UPDATE travel_expenses 
            SET 
                confirmed_distance = :distance,
                distance_confirmed_by = :confirmed_by,
                distance_confirmed_at = NOW()
            WHERE id = :id
        ";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':distance' => $confirmed_distance,
        ':confirmed_by' => $confirmed_by,
        ':id' => $expense_id
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Distance verified successfully']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
