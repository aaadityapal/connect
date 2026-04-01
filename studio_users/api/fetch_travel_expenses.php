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
    // Fetch travel expenses for the logged-in user
    $query = "SELECT te.*, tea.file_path as tea_file_path, tea.file_name as tea_file_name, tea.file_type as tea_file_type
              FROM travel_expenses te
              LEFT JOIN travel_expense_attachments tea ON te.id = tea.expense_id
              WHERE te.user_id = ?
              ORDER BY te.travel_date DESC, te.created_at DESC";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $expenses = [];
    foreach ($rows as $row) {
        $id = $row['id'];
        if (!isset($expenses[$id])) {
            $expenses[$id] = [
                'id' => 'EXP-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
                'date' => $row['travel_date'],
                'purpose' => $row['purpose'],
                'from' => $row['from_location'],
                'to' => $row['to_location'],
                'mode' => $row['mode_of_transport'],
                'distance' => $row['distance'] . ' km',
                'amount' => $row['amount'],
                'status' => $row['status'],
                'manager_status' => $row['manager_status'],
                'accountant_status' => $row['accountant_status'],
                'hr_status' => $row['hr_status'],
                'manager_reason' => $row['manager_reason'],
                'accountant_reason' => $row['accountant_reason'],
                'hr_reason' => $row['hr_reason'],
                'resubmission_count' => $row['resubmission_count'],
                'max_resubmissions'  => $row['max_resubmissions'] ?? 3,
                'payment_status' => $row['payment_status'] ?? 'Pending',
                'attachments' => []
            ];
            
            // Add primary file paths if they exist
            if (!empty($row['bill_file_path'])) {
                $expenses[$id]['attachments'][] = [
                    'path' => $row['bill_file_path'],
                    'name' => basename($row['bill_file_path']),
                    'type' => 'bill'
                ];
            }
            if (!empty($row['meter_start_photo_path'])) {
                $expenses[$id]['attachments'][] = [
                    'path' => $row['meter_start_photo_path'],
                    'name' => basename($row['meter_start_photo_path']),
                    'type' => 'meter_start'
                ];
            }
            if (!empty($row['meter_end_photo_path'])) {
                $expenses[$id]['attachments'][] = [
                    'path' => $row['meter_end_photo_path'],
                    'name' => basename($row['meter_end_photo_path']),
                    'type' => 'meter_end'
                ];
            }
        }
        
        // Add additional attachments from travel_expense_attachments
        if (!empty($row['tea_file_path'])) {
            $found = false;
            foreach ($expenses[$id]['attachments'] as $att) {
                if ($att['path'] === $row['tea_file_path']) { $found = true; break; }
            }
            if (!$found) {
                $expenses[$id]['attachments'][] = [
                    'path' => $row['tea_file_path'],
                    'name' => $row['tea_file_name'],
                    'type' => $row['tea_file_type']
                ];
            }
        }
    }

    echo json_encode(['success' => true, 'data' => array_values($expenses)]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
