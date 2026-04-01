<?php
/**
 * GET TRAVEL MAPPING DATA
 * studio_users/api/get_travel_mapping_data.php
 */
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // 1. Fetch all active users (Employees to be mapped)
    $stmt = $pdo->query("SELECT id, username as name, email, position, role FROM users WHERE deleted_at IS NULL AND status = 'Active' ORDER BY username ASC");
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch potential Approvers (can filter by role/designation, but returning all active for flexible mapping)
    $potential_approvers = $all_users;

    // 3. Fetch existing mappings
    $mapping_query = "SELECT employee_id, manager_id, hr_id, senior_manager_id FROM travel_expense_mapping";
    $stmt = $pdo->query($mapping_query);
    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group mappings by employee_id for easy front-end lookup
    $mapped_data = [];
    foreach ($mappings as $m) {
        $mapped_data[$m['employee_id']] = [
            'manager_id' => $m['manager_id'],
            'hr_id' => $m['hr_id'],
            'senior_manager_id' => $m['senior_manager_id']
        ];
    }

    echo json_encode([
        'success' => true,
        'users' => $all_users,
        'approvers' => $potential_approvers,
        'mappings' => $mapped_data
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>
