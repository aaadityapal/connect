<?php
/**
 * Test endpoint for get_payment_entries.php
 * Returns sample data if no real data exists
 * Used for testing/debugging the Recently Added Records section
 */

session_start();

// Mock the session if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'Purchase Manager';
}

require_once __DIR__ . '/config/db_connect.php';

// Set response header
header('Content-Type: application/json');

try {
    // Get pagination parameters
    $limit = intval($_GET['limit'] ?? 10);
    $offset = intval($_GET['offset'] ?? 0);
    $vendorCategory = $_GET['vendorCategory'] ?? '';

    error_log('TEST Endpoint - vendorCategory: ' . $vendorCategory);

    // Check if real data exists
    $check_stmt = $pdo->query('SELECT COUNT(*) as count FROM tbl_payment_entry_master_records');
    $real_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($real_count === 0) {
        // Return sample data for testing
        error_log('No real data found, returning sample data');
        
        $sample_data = [
            [
                'payment_entry_id' => 1,
                'project_type' => 'Architecture',
                'project_name' => 'Office Building',
                'paid_to' => [
                    ['type' => 'vendor', 'name' => 'ABC Vendor', 'category' => 'Material Supplier'],
                    ['type' => 'labour', 'name' => 'John Doe', 'category' => 'Labour Skilled']
                ],
                'grand_total' => 50000.00,
                'payment_date' => date('Y-m-d'),
                'payment_mode' => 'bank_transfer',
                'status' => 'approved',
                'files_attached' => 2
            ],
            [
                'payment_entry_id' => 2,
                'project_type' => 'Interior',
                'project_name' => 'Home Renovation',
                'paid_to' => [
                    ['type' => 'vendor', 'name' => 'Material Bricks Co', 'category' => 'Material Bricks'],
                    ['type' => 'labour', 'name' => 'Labour Team', 'category' => 'Labour']
                ],
                'grand_total' => 75000.00,
                'payment_date' => date('Y-m-d', strtotime('-1 day')),
                'payment_mode' => 'cash',
                'status' => 'pending',
                'files_attached' => 1
            ]
        ];

        // Apply vendor category filter if provided
        if (!empty($vendorCategory)) {
            $sample_data = array_filter($sample_data, function($entry) use ($vendorCategory) {
                foreach ($entry['paid_to'] as $recipient) {
                    if (stripos($recipient['category'], $vendorCategory) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }

        $filtered_data = array_slice($sample_data, $offset, $limit);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Sample payment entries returned (no real data in database)',
            'data' => array_values($filtered_data),
            'pagination' => [
                'total' => count($sample_data),
                'totalPages' => ceil(count($sample_data) / $limit),
                'currentPage' => ($offset / $limit) + 1,
                'limit' => $limit,
                'offset' => $offset
            ],
            'debug' => [
                'real_data_count' => $real_count,
                'using_sample_data' => true,
                'vendorCategory' => $vendorCategory
            ]
        ]);
    } else {
        // Use real endpoint
        include 'get_payment_entries.php';
    }

} catch (Exception $e) {
    error_log('Test Endpoint Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>
