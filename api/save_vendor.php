<?php
// Start session to get current user
session_start();

// Database connection
require_once '../config/db_connect.php';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User not authenticated'
        ]);
        exit;
    }
    
    $created_by = $_SESSION['user_id'];
    $updated_by = $_SESSION['user_id'];
    // Collect form data
    $fullName = mysqli_real_escape_string($conn, $_POST['fullName']);
    $phoneNumber = mysqli_real_escape_string($conn, $_POST['phoneNumber']);
    $alternativeNumber = isset($_POST['alternativeNumber']) ? mysqli_real_escape_string($conn, $_POST['alternativeNumber']) : '';
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $vendorType = mysqli_real_escape_string($conn, $_POST['vendorType']);
    
    // Banking details
    $bankName = isset($_POST['bankName']) ? mysqli_real_escape_string($conn, $_POST['bankName']) : '';
    $accountNumber = isset($_POST['accountNumber']) ? mysqli_real_escape_string($conn, $_POST['accountNumber']) : '';
    $routingNumber = isset($_POST['routingNumber']) ? mysqli_real_escape_string($conn, $_POST['routingNumber']) : '';
    $accountType = isset($_POST['accountType']) ? mysqli_real_escape_string($conn, $_POST['accountType']) : '';
    
    // Address details
    $streetAddress = isset($_POST['streetAddress']) ? mysqli_real_escape_string($conn, $_POST['streetAddress']) : '';
    $city = isset($_POST['city']) ? mysqli_real_escape_string($conn, $_POST['city']) : '';
    $state = isset($_POST['state']) ? mysqli_real_escape_string($conn, $_POST['state']) : '';
    $zipCode = isset($_POST['zipCode']) ? mysqli_real_escape_string($conn, $_POST['zipCode']) : '';
    $country = isset($_POST['country']) ? mysqli_real_escape_string($conn, $_POST['country']) : '';
    
    // Additional notes
    $additionalNotes = isset($_POST['additionalNotes']) ? mysqli_real_escape_string($conn, $_POST['additionalNotes']) : '';
    
    // Validate required fields
    if (empty($fullName) || empty($phoneNumber) || empty($email) || empty($vendorType)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Required fields are missing'
        ]);
        exit;
    }
    
    // Insert data into the vendors table
    $sql = "INSERT INTO hr_vendors (
                full_name, 
                phone_number, 
                alternative_number, 
                email, 
                vendor_type, 
                bank_name, 
                account_number, 
                routing_number, 
                account_type, 
                street_address, 
                city, 
                state, 
                zip_code, 
                country, 
                additional_notes,
                created_by,
                updated_by
            ) VALUES (
                '$fullName', 
                '$phoneNumber', 
                '$alternativeNumber', 
                '$email', 
                '$vendorType', 
                '$bankName', 
                '$accountNumber', 
                '$routingNumber', 
                '$accountType', 
                '$streetAddress', 
                '$city', 
                '$state', 
                '$zipCode', 
                '$country', 
                '$additionalNotes',
                '$created_by',
                '$updated_by'
            )";
    
    if (mysqli_query($conn, $sql)) {
        $vendor_id = mysqli_insert_id($conn);
        echo json_encode([
            'status' => 'success',
            'message' => 'Vendor added successfully',
            'vendor_id' => $vendor_id
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . mysqli_error($conn)
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}

// Close connection
mysqli_close($conn);
?>
