<?php
// Start session
session_start();

// Include database connection
include_once('includes/db_connect.php');

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/bills';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = array(
        'success' => false,
        'message' => 'Unknown error'
    );
    
    try {
        // Get form data
        $purpose = isset($_POST['purpose']) ? $_POST['purpose'] : '';
        $mode = isset($_POST['mode']) ? $_POST['mode'] : '';
        $from = isset($_POST['from']) ? $_POST['from'] : '';
        $to = isset($_POST['to']) ? $_POST['to'] : '';
        $date = isset($_POST['date']) ? $_POST['date'] : '';
        $distance = isset($_POST['distance']) ? floatval($_POST['distance']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
        
        // Validate required fields
        if (empty($purpose) || empty($mode) || empty($from) || empty($to) || empty($date)) {
            throw new Exception("Required fields are missing");
        }
        
        // Process file upload if mode is Taxi
        $bill_file_path = null;
        if ($mode === 'Taxi') {
            if (!isset($_FILES['bill_file']) || $_FILES['bill_file']['error'] !== UPLOAD_ERR_OK) {
                $error_codes = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive specified in the HTML form',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
                ];
                
                $error_code = isset($_FILES['bill_file']) ? $_FILES['bill_file']['error'] : UPLOAD_ERR_NO_FILE;
                $error_message = isset($error_codes[$error_code]) ? $error_codes[$error_code] : 'Unknown upload error';
                
                throw new Exception("Bill file is required for Taxi expenses: " . $error_message);
            }
            
            $file = $_FILES['bill_file'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_type = $file['type'];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type. Please upload JPG, PNG, or PDF only.");
            }
            
            // Validate file size (max 5MB)
            $max_size = 5 * 1024 * 1024; // 5MB in bytes
            if ($file_size > $max_size) {
                throw new Exception("File size is too large. Maximum allowed size is 5MB.");
            }
            
            // Generate a unique filename
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'unknown';
            $unique_prefix = uniqid('bill_' . $user_id . '_');
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_filename = $unique_prefix . '.' . $file_extension;
            $upload_path = $upload_dir . '/' . $unique_filename;
            
            // Move the uploaded file
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                throw new Exception("Failed to upload file. Check server permissions.");
            }
            
            $bill_file_path = $upload_path;
        }
        
        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception("Invalid date format. Date must be in YYYY-MM-DD format.");
        }
        
        // Ensure the date is valid
        $date_parts = explode('-', $date);
        if (!checkdate((int)$date_parts[1], (int)$date_parts[2], (int)$date_parts[0])) {
            throw new Exception("Invalid date: " . $date);
        }
        
        // Get user ID from session
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Default to 1 for testing
        
        // Insert into database
        $stmt = $conn->prepare("
            INSERT INTO travel_expenses (
                user_id, purpose, mode_of_transport, from_location, 
                to_location, travel_date, distance, amount, notes, bill_file_path
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param(
            "isssssddss",
            $user_id,
            $purpose,
            $mode,
            $from,
            $to,
            $date,
            $distance,
            $amount,
            $notes,
            $bill_file_path
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Database execution failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Success response
        $response = array(
            'success' => true,
            'message' => 'Expense saved successfully',
            'file_path' => $bill_file_path
        );
        
    } catch (Exception $e) {
        $response = array(
            'success' => false,
            'message' => $e->getMessage()
        );
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Travel Expense</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 800px;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .bill-upload {
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #17a2b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add Travel Expense</h1>
        <p>Use this simplified form to add a travel expense with bill upload.</p>
        
        <form id="expenseForm" method="post" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="purpose">Purpose of Visit</label>
                    <input type="text" class="form-control" id="purpose" name="purpose" required>
                </div>
                <div class="col-md-6 form-group">
                    <label for="mode">Mode of Transport</label>
                    <select class="form-control" id="mode" name="mode" required>
                        <option value="">Select mode</option>
                        <option value="Car">Car</option>
                        <option value="Bike">Bike</option>
                        <option value="Public Transport">Public Transport</option>
                        <option value="Taxi">Taxi</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="from">From</label>
                    <input type="text" class="form-control" id="from" name="from" required>
                </div>
                <div class="col-md-6 form-group">
                    <label for="to">To</label>
                    <input type="text" class="form-control" id="to" name="to" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 form-group">
                    <label for="date">Date</label>
                    <input type="date" class="form-control" id="date" name="date" required>
                </div>
                <div class="col-md-4 form-group">
                    <label for="distance">Distance (km)</label>
                    <input type="number" min="0" step="0.1" class="form-control" id="distance" name="distance" required>
                </div>
                <div class="col-md-4 form-group">
                    <label for="amount">Amount</label>
                    <input type="number" min="0" step="0.01" class="form-control" id="amount" name="amount" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes">Notes (Optional)</label>
                <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
            </div>
            
            <div id="billUploadContainer" class="bill-upload" style="display: none;">
                <label for="bill_file">Upload Taxi Bill (Required)<span class="text-danger">*</span></label>
                <div class="custom-file">
                    <input type="file" class="custom-file-input" id="bill_file" name="bill_file" accept=".jpg,.jpeg,.png,.pdf">
                    <label class="custom-file-label" for="bill_file">Choose file...</label>
                </div>
                <small class="form-text text-muted">Please upload taxi bill receipt (JPG, PNG, or PDF only)</small>
            </div>
            
            <button type="submit" class="btn btn-primary mt-3">Save Expense</button>
        </form>
        
        <div id="result" style="display: none;" class="result">
            <p id="resultMessage"></p>
        </div>
    </div>
    
    <script>
        // Show/hide bill upload based on mode selection
        document.getElementById('mode').addEventListener('change', function() {
            const billUploadContainer = document.getElementById('billUploadContainer');
            const billFileInput = document.getElementById('bill_file');
            
            if (this.value === 'Taxi') {
                billUploadContainer.style.display = 'block';
                billFileInput.required = true;
            } else {
                billUploadContainer.style.display = 'none';
                billFileInput.required = false;
            }
        });
        
        // Update file input label
        document.getElementById('bill_file').addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'Choose file...';
            const fileLabel = document.querySelector('.custom-file-label');
            
            if (fileLabel) {
                fileLabel.textContent = fileName;
            }
        });
        
        // Form submission
        document.getElementById('expenseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const mode = document.getElementById('mode').value;
            const billFileInput = document.getElementById('bill_file');
            
            // Validate file for Taxi
            if (mode === 'Taxi' && (!billFileInput.files || billFileInput.files.length === 0)) {
                alert('Please upload a bill file for Taxi expenses');
                return;
            }
            
            const formData = new FormData(this);
            
            // Show loading
            const resultElement = document.getElementById('result');
            const messageElement = document.getElementById('resultMessage');
            
            resultElement.className = 'result';
            resultElement.style.display = 'block';
            messageElement.textContent = 'Saving expense...';
            
            fetch('simple_bill_upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultElement.className = 'result success';
                    messageElement.textContent = data.message;
                    // Reset form on success
                    document.getElementById('expenseForm').reset();
                    document.getElementById('billUploadContainer').style.display = 'none';
                } else {
                    resultElement.className = 'result error';
                    messageElement.textContent = data.message;
                }
            })
            .catch(error => {
                resultElement.className = 'result error';
                messageElement.textContent = 'Error: ' + error.message;
            });
        });
    </script>
</body>
</html> 