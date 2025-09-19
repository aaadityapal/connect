<?php
// Production Error Diagnostic Tool for Payment Entry Saving
session_start();

// Enable error reporting for diagnostics
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'environment' => 'production',
    'checks' => []
];

try {
    // 1. Check Session Authentication
    $diagnostics['checks']['session'] = [
        'user_id_set' => isset($_SESSION['user_id']),
        'user_id_value' => $_SESSION['user_id'] ?? 'not_set',
        'session_status' => session_status(),
        'session_data' => $_SESSION ?? []
    ];

    // 2. Check Database Connection
    try {
        require_once '../config/db_connect.php';
        $diagnostics['checks']['database'] = [
            'connection_status' => 'success',
            'pdo_available' => isset($pdo),
            'test_query' => false
        ];
        
        // Test database with simple query
        if (isset($pdo)) {
            $test = $pdo->query("SELECT 1");
            $diagnostics['checks']['database']['test_query'] = $test ? true : false;
        }
    } catch (Exception $e) {
        $diagnostics['checks']['database'] = [
            'connection_status' => 'error',
            'error_message' => $e->getMessage()
        ];
    }

    // 3. Check Directory Permissions
    $upload_base = "../uploads/payment_documents/";
    $diagnostics['checks']['directories'] = [
        'base_exists' => file_exists($upload_base),
        'base_writable' => file_exists($upload_base) ? is_writable($upload_base) : false,
        'can_create_base' => false,
        'test_directory_creation' => false
    ];

    // Test directory creation
    if (!file_exists($upload_base)) {
        $diagnostics['checks']['directories']['can_create_base'] = mkdir($upload_base, 0777, true);
    } else {
        $diagnostics['checks']['directories']['can_create_base'] = true;
    }

    // Test creating a subdirectory
    $test_dir = $upload_base . "test_payment_999/recipient_999/";
    if (mkdir($test_dir, 0777, true)) {
        $diagnostics['checks']['directories']['test_directory_creation'] = true;
        // Clean up test directory
        rmdir($test_dir);
        rmdir(dirname($test_dir));
    }

    // 4. Check PHP Configuration
    $diagnostics['checks']['php_config'] = [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'file_uploads' => ini_get('file_uploads') ? 'enabled' : 'disabled',
        'max_file_uploads' => ini_get('max_file_uploads'),
        'upload_tmp_dir' => ini_get('upload_tmp_dir'),
        'temp_dir_writable' => is_writable(sys_get_temp_dir())
    ];

    // 5. Check Required Tables
    if (isset($pdo)) {
        $required_tables = [
            'hr_payment_entries',
            'hr_payment_recipients', 
            'hr_payment_documents',
            'hr_payment_splits',
            'users',
            'projects'
        ];
        
        $diagnostics['checks']['database_tables'] = [];
        foreach ($required_tables as $table) {
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                $diagnostics['checks']['database_tables'][$table] = $stmt->rowCount() > 0;
            } catch (Exception $e) {
                $diagnostics['checks']['database_tables'][$table] = false;
            }
        }
    }

    // 6. Test Form Data Processing
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $diagnostics['checks']['form_data'] = [
            'post_data_received' => !empty($_POST),
            'post_data_count' => count($_POST),
            'files_data_received' => !empty($_FILES),
            'files_data_count' => count($_FILES),
            'required_fields_present' => []
        ];

        // Check required main fields
        $required_main_fields = ['projectType', 'projectName', 'paymentDate', 'paymentAmount', 'paymentDoneVia', 'paymentMode'];
        foreach ($required_main_fields as $field) {
            $diagnostics['checks']['form_data']['required_fields_present'][$field] = isset($_POST[$field]) && !empty($_POST[$field]);
        }

        // Check recipients data
        $diagnostics['checks']['form_data']['recipients'] = [
            'recipients_data_present' => isset($_POST['recipients']),
            'recipients_count' => isset($_POST['recipientCount']) ? $_POST['recipientCount'] : 0
        ];
    }

    // 7. Server Environment
    $diagnostics['checks']['server_environment'] = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
        'current_working_directory' => getcwd(),
        'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown'
    ];

    // 8. Test File Upload Simulation
    if (!empty($_FILES)) {
        $diagnostics['checks']['file_upload'] = [];
        foreach ($_FILES as $field_name => $files) {
            $diagnostics['checks']['file_upload'][$field_name] = [
                'upload_error_code' => $files['error'] ?? 'no_error_code',
                'tmp_name_exists' => isset($files['tmp_name']) && file_exists($files['tmp_name']),
                'file_size' => $files['size'] ?? 0
            ];
        }
    }

    $diagnostics['status'] = 'completed';
    $diagnostics['overall_health'] = 'checking...';

    // Determine overall health
    $critical_issues = 0;
    if (!$diagnostics['checks']['session']['user_id_set']) $critical_issues++;
    if ($diagnostics['checks']['database']['connection_status'] !== 'success') $critical_issues++;
    if (!$diagnostics['checks']['directories']['base_writable']) $critical_issues++;

    if ($critical_issues === 0) {
        $diagnostics['overall_health'] = 'good';
        $diagnostics['message'] = 'No critical issues detected. Payment entry system should work.';
    } else {
        $diagnostics['overall_health'] = 'issues_detected';
        $diagnostics['message'] = "Found $critical_issues critical issue(s) that could prevent payment entry saving.";
    }

} catch (Exception $e) {
    $diagnostics['status'] = 'error';
    $diagnostics['error'] = $e->getMessage();
    $diagnostics['overall_health'] = 'error';
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
?>