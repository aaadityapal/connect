<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Validate and sanitize input
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';
    $alt_number = isset($_POST['alt_number']) ? trim($_POST['alt_number']) : '';
    $join_date = isset($_POST['join_date']) ? $_POST['join_date'] : NULL;
    $labour_type = isset($_POST['labour_type']) ? trim($_POST['labour_type']) : '';
    $daily_salary = isset($_POST['daily_salary']) ? floatval($_POST['daily_salary']) : NULL;
    $street_address = isset($_POST['street_address']) ? trim($_POST['street_address']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $state = isset($_POST['state']) ? trim($_POST['state']) : '';
    $zip_code = isset($_POST['zip_code']) ? trim($_POST['zip_code']) : '';

    // Validate required fields
    if (empty($full_name)) {
        throw new Exception('Full name is required');
    }
    if (empty($contact_number)) {
        throw new Exception('Contact number is required');
    }
    if (!preg_match('/^[0-9]{10}$/', $contact_number)) {
        throw new Exception('Contact number must be exactly 10 digits');
    }
    if (!empty($alt_number) && !preg_match('/^[0-9]{10}$/', $alt_number)) {
        throw new Exception('Alternative number must be exactly 10 digits if provided');
    }
    if (empty($labour_type)) {
        throw new Exception('Labour type is required');
    }
    if (!empty($zip_code) && !preg_match('/^[0-9]{6}$/', $zip_code)) {
        throw new Exception('Zip code must be exactly 6 digits');
    }

    // Generate unique labour code
    $labour_code = 'LB-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

    // Check for duplicate labour record
    $check_query = "SELECT id, labour_unique_code, full_name FROM labour_records 
                    WHERE full_name = ? AND contact_number = ? AND labour_type = ?";
    $check_stmt = $conn->prepare($check_query);
    if (!$check_stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $check_stmt->bind_param('sss', $full_name, $contact_number, $labour_type);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        $check_stmt->close();
        
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'is_duplicate' => true,
            'message' => 'This labour is already added',
            'existing_code' => $existing['labour_unique_code'],
            'existing_name' => $existing['full_name']
        ]);
        exit;
    }
    $check_stmt->close();

    // Handle file uploads
    $file_paths = [
        'aadhar_card' => '',
        'pan_card' => '',
        'voter_id' => '',
        'other_document' => ''
    ];

    $upload_dir = __DIR__ . '/../uploads/labour_documents/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    foreach (array_keys($file_paths) as $file_key) {
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$file_key];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('Invalid file type for ' . $file_key . '. Only images and PDF are allowed.');
            }

            // Validate file size (max 5MB)
            $max_size = 5 * 1024 * 1024;
            if ($file['size'] > $max_size) {
                throw new Exception('File size for ' . $file_key . ' exceeds 5MB limit');
            }

            // Generate unique filename
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $file_name = $labour_code . '_' . $file_key . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception('Failed to upload ' . $file_key);
            }

            $file_paths[$file_key] = 'uploads/labour_documents/' . $file_name;
        }
    }

    // Prepare and execute insert query using MySQLi
    $query = "INSERT INTO labour_records (
        labour_unique_code, full_name, contact_number, alt_number, join_date, 
        labour_type, daily_salary, street_address, city, state, zip_code,
        aadhar_card, pan_card, voter_id, other_document, created_by, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $status = 'active';
    $created_by = $_SESSION['user_id'];

    $stmt->bind_param(
        'ssssssdssssssssss',
        $labour_code,
        $full_name,
        $contact_number,
        $alt_number,
        $join_date,
        $labour_type,
        $daily_salary,
        $street_address,
        $city,
        $state,
        $zip_code,
        $file_paths['aadhar_card'],
        $file_paths['pan_card'],
        $file_paths['voter_id'],
        $file_paths['other_document'],
        $created_by,
        $status
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $labour_id = $stmt->insert_id;
    $stmt->close();

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Labour record added successfully',
        'labour_id' => $labour_id,
        'labour_code' => $labour_code,
        'labour_name' => $full_name
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

