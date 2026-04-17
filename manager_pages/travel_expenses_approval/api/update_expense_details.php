<?php
/**
 * UPDATE TRAVEL EXPENSE DETAILS
 * manager_pages/travel_expenses_approval/api/update_expense_details.php
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

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || empty($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing expense ID']);
    exit();
}

$expense_id = (int)$input['id'];

try {
    $permStmt = $pdo->prepare(
        "SELECT te.id, te.user_id, te.confirmed_distance, te.hr_confirmed_distance,
                te.mode_of_transport, te.distance,
                m.manager_id, m.hr_id, m.senior_manager_id
         FROM travel_expenses te
         LEFT JOIN travel_expense_mapping m ON te.user_id = m.employee_id
         WHERE te.id = ?"
    );
    $permStmt->execute([$expense_id]);
    $expense = $permStmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        echo json_encode(['success' => false, 'message' => 'Expense not found']);
        exit();
    }

    $is_mapped_approver = (
        (string)($expense['manager_id'] ?? '') === (string)$current_user_id ||
        (string)($expense['hr_id'] ?? '') === (string)$current_user_id ||
        (string)($expense['senior_manager_id'] ?? '') === (string)$current_user_id
    );

    if (!$is_admin && !$is_mapped_approver) {
        echo json_encode(['success' => false, 'message' => 'You are not allowed to edit this expense']);
        exit();
    }

    // Apply same verification gate as approval actions.
    if (!$is_admin) {
        $has_verified_distance = false;

        if ((string)($expense['senior_manager_id'] ?? '') === (string)$current_user_id) {
            // Senior Manager is exempt from distance lock in current workflow.
            $has_verified_distance = true;
        } elseif ((string)($expense['manager_id'] ?? '') === (string)$current_user_id) {
            $has_verified_distance = ($expense['confirmed_distance'] !== null && $expense['confirmed_distance'] !== '');
        } elseif ((string)($expense['hr_id'] ?? '') === (string)$current_user_id) {
            $has_verified_distance = ($expense['hr_confirmed_distance'] !== null && $expense['hr_confirmed_distance'] !== '');
        }

        if (!$has_verified_distance) {
            echo json_encode(['success' => false, 'message' => 'Please complete distance verification before editing this expense']);
            exit();
        }
    }

    // Fields allowed from the edit modal.
    $allowed_fields = [
        'purpose' => 'purpose',
        'from_location' => 'from_location',
        'to_location' => 'to_location',
        'mode_of_transport' => 'mode_of_transport',
        'travel_date' => 'travel_date',
        'distance' => 'distance'
    ];

    $updates = [];
    $params = [];

    foreach ($allowed_fields as $payload_key => $column_name) {
        if (!array_key_exists($payload_key, $input)) {
            continue;
        }

        $value = $input[$payload_key];

        if (in_array($payload_key, ['distance', 'amount'], true)) {
            if (!is_numeric($value) || (float)$value < 0) {
                echo json_encode(['success' => false, 'message' => ucfirst($payload_key) . ' must be a valid non-negative number']);
                exit();
            }
            $value = (float)$value;
        }

        if ($payload_key === 'travel_date') {
            $date = date_create($value);
            if (!$date) {
                echo json_encode(['success' => false, 'message' => 'Invalid travel date']);
                exit();
            }
            $value = date_format($date, 'Y-m-d');
        }

        if (in_array($payload_key, ['purpose', 'from_location', 'to_location', 'mode_of_transport'], true)) {
            $value = trim((string)$value);
            if ($value === '') {
                echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $payload_key)) . ' cannot be empty']);
                exit();
            }
        }

        $placeholder = ':' . $payload_key;
        $updates[] = "$column_name = $placeholder";
        $params[$placeholder] = $value;
    }

    $effective_mode = strtolower(trim((string)($input['mode_of_transport'] ?? $expense['mode_of_transport'] ?? '')));
    $effective_distance = array_key_exists('distance', $input)
        ? (float)$input['distance']
        : (float)($expense['distance'] ?? 0);

    $is_locked_mode = in_array($effective_mode, ['car', 'bike'], true);

    if ($is_locked_mode) {
        $rateStmt = $pdo->prepare("SELECT rate_per_km FROM travel_transport_rates WHERE LOWER(transport_mode) = ? LIMIT 1");
        $rateStmt->execute([$effective_mode]);
        $rateRow = $rateStmt->fetch(PDO::FETCH_ASSOC);

        if (!$rateRow) {
            echo json_encode(['success' => false, 'message' => 'Transport rate not configured for selected mode']);
            exit();
        }

        $rate = (float)$rateRow['rate_per_km'];
        $auto_amount = round($effective_distance * $rate, 2);
        $updates[] = "amount = :amount";
        $params[':amount'] = $auto_amount;
    } elseif (array_key_exists('amount', $input)) {
        $amount = $input['amount'];
        if (!is_numeric($amount) || (float)$amount < 0) {
            echo json_encode(['success' => false, 'message' => 'Amount must be a valid non-negative number']);
            exit();
        }

        $updates[] = "amount = :amount";
        $params[':amount'] = (float)$amount;
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No editable fields provided']);
        exit();
    }

    $params[':id'] = $expense_id;
    $sql = "UPDATE travel_expenses SET " . implode(', ', $updates) . " WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Travel expense details updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
