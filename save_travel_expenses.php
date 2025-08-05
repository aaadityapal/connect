<?php
// Start session to get user data
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response = array(
        'success' => false,
        'message' => 'User not logged in'
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Include database connection
include_once('config/db_connect.php');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response = array(
        'success' => false,
        'message' => 'Invalid request method'
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Create uploads directories if they don't exist
$bills_dir = 'uploads/bills';
$meter_photos_dir = 'uploads/meter_photos';

if (!file_exists($bills_dir)) {
    mkdir($bills_dir, 0755, true);
}

if (!file_exists($meter_photos_dir)) {
    mkdir($meter_photos_dir, 0755, true);
}

// Debug info
error_log('POST data: ' . print_r($_POST, true));
error_log('FILES data: ' . print_r($_FILES, true));

// Get expenses data from POST
$expenses_json = isset($_POST['expenses']) ? $_POST['expenses'] : null;

if (!$expenses_json) {
    // Legacy method - get from raw input
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    // Check if expenses data exists
    if (!isset($data['expenses']) || empty($data['expenses'])) {
        $response = array(
            'success' => false,
            'message' => 'No expense data provided'
        );
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    $expenses = $data['expenses'];
} else {
    // New method with FormData
    $expenses = json_decode($expenses_json, true);
    
    // Check if expenses data exists
    if (empty($expenses)) {
        $response = array(
            'success' => false,
            'message' => 'No expense data provided'
        );
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Start a transaction
$conn->begin_transaction();

try {
    // Create an array to track uploaded files
    $uploaded_files = array();
    
    // First, process all file uploads
    foreach ($_FILES as $file_key => $file_info) {
        // Skip if there's no file or it's not a valid upload
        if (!isset($file_info['name']) || $file_info['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        // Get file details
        $file_name = $file_info['name'];
        $file_tmp = $file_info['tmp_name'];
        $file_size = $file_info['size'];
        $file_type = $file_info['type'];
        
        error_log("Processing file: " . $file_key . " - " . $file_name . " (Size: " . $file_size . " bytes, Type: " . $file_type . ")");
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("Invalid file type for file: " . $file_type);
        }
        
        // Validate file size (max 5MB)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($file_size > $max_size) {
            throw new Exception("File size too large. Maximum allowed size is 5MB");
        }
        
        // Determine if this is a meter photo or a bill file
        $is_meter_photo = (strpos($file_key, 'meter_start_photo_') === 0 || strpos($file_key, 'meter_end_photo_') === 0);
        $upload_dir = $is_meter_photo ? $meter_photos_dir : $bills_dir;
        $prefix = $is_meter_photo ? 'meter_' : 'bill_';
        
        // Generate a unique filename to prevent overwriting
        $unique_prefix = uniqid($prefix . $user_id . '_');
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $unique_filename = $unique_prefix . '.' . $file_extension;
        $upload_path = $upload_dir . '/' . $unique_filename;
        
        error_log("Attempting to upload file to: " . $upload_path);
        
        // Move the uploaded file to the destination
        if (move_uploaded_file($file_tmp, $upload_path)) {
            // Store the upload path indexed by the original file key
            $uploaded_files[$file_key] = $upload_path;
            error_log("File uploaded successfully to: " . $upload_path);
        } else {
            error_log("Failed to upload file. move_uploaded_file() returned false.");
            throw new Exception("Failed to upload file: " . $file_name);
        }
    }
    
    // Check if we have at least one file for expenses that require bills
    $needs_bill_files = false;
    $expense_types_needing_bills = [];
    
    foreach ($expenses as $expense) {
        if (in_array($expense['mode'], ['Taxi', 'Bus', 'Train', 'Other'])) {
            $needs_bill_files = true;
            $expense_types_needing_bills[] = $expense['mode'];
            break;
        }
    }
    
    if ($needs_bill_files && empty($uploaded_files)) {
        $expense_types = array_unique($expense_types_needing_bills);
        throw new Exception("No bill files were uploaded for " . implode(', ', $expense_types) . " expenses");
    }

    // Prepare the SQL statement with file paths
    $stmt = $conn->prepare("
        INSERT INTO travel_expenses (
            user_id, purpose, mode_of_transport, from_location, 
            to_location, travel_date, distance, amount, status, notes, 
            bill_file_path, meter_start_photo_path, meter_end_photo_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $total_amount = 0;
    $total_entries = 0;
    
    // Insert each expense entry
    foreach ($expenses as $index => $expense) {
        // Initialize file paths
        $expense_bill_path = null;
        $meter_start_photo_path = null;
        $meter_end_photo_path = null;
        
        // Handle bill file for Taxi, Bus, Train and Other
        if (in_array($expense['mode'], ['Taxi', 'Bus', 'Train', 'Other'])) {
            // Look for a file that matches this expense's index
            if (isset($expense['billFileIndex']) && isset($uploaded_files['bill_file_' . $expense['billFileIndex']])) {
                $expense_bill_path = $uploaded_files['bill_file_' . $expense['billFileIndex']];
            } 
            // If not found by index, try using bill_file_0
            else if (isset($uploaded_files['bill_file_0'])) {
                $expense_bill_path = $uploaded_files['bill_file_0'];
            }
            // Or just use any uploaded bill file
            else if (isset($uploaded_files['bill_file'])) {
                $expense_bill_path = $uploaded_files['bill_file'];
            }
            // If we still don't have a file, check if there's any bill file
            else {
                // Look for any bill file
                foreach ($uploaded_files as $key => $path) {
                    if (strpos($key, 'bill_file_') === 0) {
                        $expense_bill_path = $path;
                        break;
                    }
                }
            }
            
            // If we still don't have a bill path for this expense type, that's an error
            if ($expense_bill_path === null) {
                throw new Exception("Missing bill file for " . $expense['mode'] . " expense #" . ($index + 1));
            }
        }
        
        // Handle meter photos for Bike and Car
        if ($expense['mode'] === 'Bike' || $expense['mode'] === 'Car') {
            // Look for meter start photo
            if (isset($expense['meterStartPhotoIndex']) && isset($uploaded_files['meter_start_photo_' . $expense['meterStartPhotoIndex']])) {
                $meter_start_photo_path = $uploaded_files['meter_start_photo_' . $expense['meterStartPhotoIndex']];
            } else {
                // Look for any meter start photo
                foreach ($uploaded_files as $key => $path) {
                    if (strpos($key, 'meter_start_photo_') === 0) {
                        $meter_start_photo_path = $path;
                        break;
                    }
                }
            }
            
            // Look for meter end photo
            if (isset($expense['meterEndPhotoIndex']) && isset($uploaded_files['meter_end_photo_' . $expense['meterEndPhotoIndex']])) {
                $meter_end_photo_path = $uploaded_files['meter_end_photo_' . $expense['meterEndPhotoIndex']];
            } else {
                // Look for any meter end photo
                foreach ($uploaded_files as $key => $path) {
                    if (strpos($key, 'meter_end_photo_') === 0) {
                        $meter_end_photo_path = $path;
                        break;
                    }
                }
            }
            
            // Check if we have both meter photos
            if ($meter_start_photo_path === null || $meter_end_photo_path === null) {
                throw new Exception("Missing meter photos for " . $expense['mode'] . " expense #" . ($index + 1));
            }
        }
        
        // Validate date format (YYYY-MM-DD)
        $travel_date = $expense['date'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $travel_date)) {
            throw new Exception("Invalid date format. Date must be in YYYY-MM-DD format.");
        }

        // Ensure the date is valid
        $date_parts = explode('-', $travel_date);
        if (!checkdate((int)$date_parts[1], (int)$date_parts[2], (int)$date_parts[0])) {
            throw new Exception("Invalid date: " . $travel_date);
        }

        // Get status or default to 'pending'
        $status = isset($expense['status']) ? $expense['status'] : 'pending';

        // Bind the parameters - note we're using 's' for date as it's a string in YYYY-MM-DD format
        $stmt->bind_param(
            "isssssddsssss",
            $user_id,
            $expense['purpose'],
            $expense['mode'],
            $expense['from'],
            $expense['to'],
            $travel_date,
            $expense['distance'],
            $expense['amount'],
            $status,
            $expense['notes'],
            $expense_bill_path,
            $meter_start_photo_path,
            $meter_end_photo_path
        );

        // Execute the statement
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $total_amount += $expense['amount'];
        $total_entries++;
    }

    // Check if approval is needed based on total amount
    $approval_needed = false;
    $approval_threshold = 5000; // Default threshold
    
    // Get the approval threshold from settings
    $threshold_query = $conn->query("SELECT setting_value FROM travel_expense_settings WHERE setting_key = 'approval_threshold'");
    if ($threshold_query && $threshold_row = $threshold_query->fetch_assoc()) {
        $approval_threshold = floatval($threshold_row['setting_value']);
    }
    
    // If total amount exceeds threshold, create approval request
    if ($total_amount > $approval_threshold) {
        $approval_needed = true;
        
        // Get supervisor/manager ID (assumes there's a way to determine the approver)
        // For demonstration, we'll use a simple query to find a user with 'Manager' role
        $approver_query = $conn->query("SELECT id FROM users WHERE role = 'Manager' LIMIT 1");
        if ($approver_query && $approver_row = $approver_query->fetch_assoc()) {
            $approver_id = $approver_row['id'];
            
            // Get the IDs of the expenses we just inserted
            $expense_ids_query = $conn->query("
                SELECT id FROM travel_expenses 
                WHERE user_id = $user_id 
                ORDER BY created_at DESC 
                LIMIT $total_entries
            ");
            
            if ($expense_ids_query) {
                while ($expense_row = $expense_ids_query->fetch_assoc()) {
                    // Create approval request for each expense
                    $approval_stmt = $conn->prepare("
                        INSERT INTO travel_expense_approvals (expense_id, approver_id) 
                        VALUES (?, ?)
                    ");
                    
                    if ($approval_stmt) {
                        $expense_id = $expense_row['id'];
                        $approval_stmt->bind_param("ii", $expense_id, $approver_id);
                        $approval_stmt->execute();
                        $approval_stmt->close();
                    }
                }
            }
        }
    }
    
    // Commit the transaction
    $conn->commit();
    
    // Success response
    $response = array(
        'success' => true,
        'message' => 'Expenses saved successfully',
        'total_entries' => $total_entries,
        'total_amount' => $total_amount,
        'approval_needed' => $approval_needed,
        'uploaded_files' => array_keys($uploaded_files)
    );
    
} catch (Exception $e) {
    // Rollback the transaction
    $conn->rollback();
    
    // Error response
    $response = array(
        'success' => false,
        'message' => $e->getMessage()
    );
    
    // Log the error
    error_log("Error saving travel expenses: " . $e->getMessage());
}

// Close the statement if it exists
if (isset($stmt) && $stmt) {
    $stmt->close();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>