<?php
// Include database connection
require_once 'config.php';

// Set headers to handle AJAX request
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'expense_id' => null
];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get form data
        $projectId = null;
        $projectName = $_POST['project_name'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $paymentModeId = (int)($_POST['payment_mode'] ?? 0);
        $paymentTypeId = (int)($_POST['payment_type'] ?? 0);
        $expenseDatetime = $_POST['expense_datetime'] ?? date('Y-m-d H:i:s');
        $paymentAccessBy = (int)($_POST['payment_access'] ?? 0);
        $remarks = $_POST['remarks'] ?? '';
        $createdBy = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Default to admin if no session
        
        // Handle custom project
        if (isset($_POST['is_custom_project']) && $_POST['is_custom_project'] == 'true') {
            // Check if project already exists
            $stmt = $conn->prepare("SELECT project_id FROM se_projects WHERE project_name = ?");
            $stmt->bind_param("s", $projectName);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Project exists, get its ID
                $row = $result->fetch_assoc();
                $projectId = $row['project_id'];
            } else {
                // Create new project
                $location = $_POST['project_location'] ?? '';
                $stmt = $conn->prepare("INSERT INTO se_projects (project_name, project_location) VALUES (?, ?)");
                $stmt->bind_param("ss", $projectName, $location);
                $stmt->execute();
                $projectId = $conn->insert_id;
            }
        } else {
            // Get project ID from selected option
            $projectId = (int)($_POST['project_id'] ?? 0);
        }
        
        // Handle receipt file upload if provided
        $receiptFilePath = null;
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
            $uploadDir = 'uploads/site_expenses/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['receipt']['name']);
            $targetFile = $uploadDir . $fileName;
            
            // Move uploaded file to target directory
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $targetFile)) {
                $receiptFilePath = $targetFile;
            } else {
                throw new Exception("Failed to upload receipt file.");
            }
        }
        
        // Insert main expense record
        $stmt = $conn->prepare("INSERT INTO se_expenses (
            project_id, amount, payment_mode_id, payment_type_id, 
            expense_datetime, payment_access_by, remarks, 
            receipt_file_path, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param(
            "idiiisssi", 
            $projectId, $amount, $paymentModeId, $paymentTypeId,
            $expenseDatetime, $paymentAccessBy, $remarks, 
            $receiptFilePath, $createdBy
        );
        
        $stmt->execute();
        $expenseId = $conn->insert_id;
        
        // Handle payment type specific details
        if ($paymentTypeId == 1 || $_POST['payment_type'] == 'vendor') { // Vendor Payment
            // Process vendor details
            $vendorCount = (int)($_POST['vendor_count'] ?? 1);
            
            for ($i = 1; $i <= $vendorCount; $i++) {
                $vendorName = $_POST["vendor_name_$i"] ?? '';
                $vendorMobile = $_POST["vendor_mobile_$i"] ?? '';
                $vendorAccount = $_POST["vendor_account_$i"] ?? '';
                $vendorIfsc = $_POST["vendor_ifsc_$i"] ?? '';
                $vendorUpi = $_POST["vendor_upi_$i"] ?? '';
                
                // Skip if vendor name is empty
                if (empty($vendorName)) {
                    continue;
                }
                
                // Check if vendor exists
                $stmt = $conn->prepare("SELECT vendor_id FROM se_vendors WHERE vendor_name = ? AND mobile_number = ?");
                $stmt->bind_param("ss", $vendorName, $vendorMobile);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $vendorId = null;
                if ($result->num_rows > 0) {
                    // Vendor exists
                    $row = $result->fetch_assoc();
                    $vendorId = $row['vendor_id'];
                } else {
                    // Create new vendor
                    $stmt = $conn->prepare("INSERT INTO se_vendors (
                        vendor_name, mobile_number, account_number, ifsc_code, upi_number
                    ) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $vendorName, $vendorMobile, $vendorAccount, $vendorIfsc, $vendorUpi);
                    $stmt->execute();
                    $vendorId = $conn->insert_id;
                }
                
                // Create vendor payment record
                $vendorAmount = $amount / $vendorCount; // Split amount equally if multiple vendors
                $paymentDetails = "Vendor payment for expense #$expenseId";
                
                $stmt = $conn->prepare("INSERT INTO se_vendor_payments (
                    expense_id, vendor_id, amount, payment_details
                ) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iids", $expenseId, $vendorId, $vendorAmount, $paymentDetails);
                $stmt->execute();
            }
        } elseif ($paymentTypeId == 7 || $_POST['payment_type'] == 'equipment_rental') { // Equipment Rental
            // Process equipment rental details
            $equipmentName = $_POST['equipment_name'] ?? '';
            $rentPerDay = floatval($_POST['rent_per_day'] ?? 0);
            $rentalDays = (int)($_POST['rental_days'] ?? 0);
            $rentalTotal = floatval($_POST['rental_total'] ?? 0);
            $advanceAmount = floatval($_POST['advance_amount'] ?? 0);
            $balanceAmount = floatval($_POST['balance_amount'] ?? 0);
            
            $stmt = $conn->prepare("INSERT INTO se_equipment_rentals (
                expense_id, equipment_name, rent_per_day, rental_days, 
                rental_total, advance_amount, balance_amount
            ) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param(
                "isdiddd", 
                $expenseId, $equipmentName, $rentPerDay, $rentalDays,
                $rentalTotal, $advanceAmount, $balanceAmount
            );
            
            $stmt->execute();
        }
        
        // Log the activity
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        $stmt = $conn->prepare("INSERT INTO se_activity_log (
            user_id, activity_type, entity_type, entity_id, 
            description, ip_address, user_agent
        ) VALUES (?, 'create', 'expense', ?, ?, ?, ?)");
        
        $description = "Expense created for " . $projectName . " with amount " . $amount;
        
        $stmt->bind_param(
            "iisss", 
            $createdBy, $expenseId, $description, $ipAddress, $userAgent
        );
        
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Set success response
        $response['success'] = true;
        $response['message'] = 'Expense saved successfully!';
        $response['expense_id'] = $expenseId;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $response['message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

// Return JSON response
echo json_encode($response); 