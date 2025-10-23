<?php
// Set timezone to match other files
date_default_timezone_set('Asia/Kolkata');

session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug the POST data
        error_log("Travel Allowance Form Submitted: " . print_r($_POST, true));
        
        $sql = "INSERT INTO travel_allowances (
            user_id, 
            travel_date, 
            return_date,
            purpose,
            from_location,
            to_location,
            transport_type,
            estimated_cost,
            advance_amount,
            additional_details,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $_SESSION['user_id'],
            $_POST['travel_date'],
            $_POST['return_date'],
            $_POST['purpose'],
            $_POST['from_location'],
            $_POST['to_location'],
            $_POST['transport_type'],
            $_POST['estimated_cost'],
            $_POST['advance_amount'],
            $_POST['additional_details']
        ]);

        // Debug the insertion
        error_log("Travel Allowance Insert Result: " . ($result ? "Success" : "Failed"));
        error_log("Last Insert ID: " . $pdo->lastInsertId());

        if ($result) {
            $success_message = "Travel allowance request submitted successfully!";
        } else {
            $error_message = "Error submitting request.";
        }
    } catch (PDOException $e) {
        error_log("Travel Allowance Submit Error: " . $e->getMessage());
        $error_message = "Error submitting request: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Allowance Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .required::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="form-container">
            <h2 class="mb-4">Travel Allowance Request</h2>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Travel Date</label>
                        <input type="date" class="form-control" name="travel_date" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Return Date</label>
                        <input type="date" class="form-control" name="return_date" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required">Purpose of Travel</label>
                    <input type="text" class="form-control" name="purpose" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">From Location</label>
                        <input type="text" class="form-control" name="from_location" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">To Location</label>
                        <input type="text" class="form-control" name="to_location" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required">Mode of Transport</label>
                    <select class="form-select" name="transport_type" required>
                        <option value="">Select transport type</option>
                        <option value="flight">Flight</option>
                        <option value="train">Train</option>
                        <option value="bus">Bus</option>
                        <option value="cab">Cab</option>
                        <option value="personal_vehicle">Personal Vehicle</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Estimated Cost (₹)</label>
                        <input type="number" class="form-control" name="estimated_cost" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Advance Amount Required (₹)</label>
                        <input type="number" class="form-control" name="advance_amount">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Additional Details</label>
                    <textarea class="form-control" name="additional_details" rows="3" 
                              placeholder="Enter any additional details or special requirements"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Upload Supporting Documents</label>
                    <input type="file" class="form-control" name="documents" multiple>
                    <div class="form-text">Upload tickets, hotel bookings, or any other relevant documents</div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="button" class="btn btn-secondary me-md-2" onclick="history.back()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add client-side validation for dates
        document.querySelector('form').addEventListener('submit', function(e) {
            const travelDate = new Date(document.querySelector('[name="travel_date"]').value);
            const returnDate = new Date(document.querySelector('[name="return_date"]').value);
            
            if (returnDate < travelDate) {
                e.preventDefault();
                alert('Return date cannot be earlier than travel date');
            }
        });
    </script>
</body>
</html>
