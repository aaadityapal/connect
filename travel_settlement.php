<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$requestId = $_GET['id'];

// Verify request belongs to user and is approved
$sql = "SELECT * FROM travel_allowances WHERE id = ? AND user_id = ? AND status = 'approved_by_hr'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$requestId, $userId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header('Location: my_travel_requests.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Update travel allowance status
        $sql = "UPDATE travel_allowances SET 
                actual_cost = ?, 
                settlement_details = ?,
                status = 'completed',
                settlement_date = NOW() 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['actual_cost'],
            $_POST['settlement_details'],
            $requestId
        ]);
        
        // Handle file uploads
        if (isset($_FILES['receipts'])) {
            $uploadDir = 'uploads/receipts/';
            foreach ($_FILES['receipts']['tmp_name'] as $key => $tmp_name) {
                $fileName = uniqid() . '_' . $_FILES['receipts']['name'][$key];
                move_uploaded_file($tmp_name, $uploadDir . $fileName);
                
                $sql = "INSERT INTO travel_receipts (request_id, file_path) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$requestId, $fileName]);
            }
        }
        
        $pdo->commit();
        $success_message = "Settlement submitted successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error submitting settlement: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Expense Settlement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h2>Travel Expense Settlement</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Actual Total Cost (₹)</label>
                        <input type="number" step="0.01" class="form-control" name="actual_cost" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Settlement Details</label>
                        <textarea class="form-control" name="settlement_details" rows="4" required
                                placeholder="Provide breakdown of expenses"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Upload Receipts</label>
                        <input type="file" class="form-control" name="receipts[]" multiple required>
                        <div class="form-text">Upload all relevant receipts and bills</div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-secondary me-md-2" onclick="history.back()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Settlement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 