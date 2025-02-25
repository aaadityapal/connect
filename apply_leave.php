<?php
session_start();
require_once 'config/db_connect.php';
require_once 'functions.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get leave balances
$stmt = $pdo->prepare("
    SELECT 
        leave_type,
        total_leaves,
        used_leaves
    FROM leave_balances 
    WHERE user_id = ? AND year = ?
");
$stmt->execute([$_SESSION['user_id'], date('Y')]);
$leaveBalances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $leaveType = $_POST['leave_type'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $reason = $_POST['reason'];
        
        // Validate dates and leave balance
        // ... add your validation logic here ...
        
        // Insert leave request
        $stmt = $pdo->prepare("
            INSERT INTO leave_requests 
            (user_id, leave_type, start_date, end_date, reason, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $leaveType, $startDate, $endDate, $reason]);
        
        $_SESSION['success_message'] = 'Leave request submitted successfully!';
        header('Location: apply_leave.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error submitting leave request: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Leave | <?php echo htmlspecialchars($user['username']); ?></title>
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <!-- Include your CSS and other dependencies here -->
</head>
<body>
    <!-- Include your header/navigation here -->
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Apply for Leave</h5>
                    </div>
                    <div class="card-body">
                        <!-- Success/Error Messages -->
                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success">
                                <?php 
                                echo $_SESSION['success_message'];
                                unset($_SESSION['success_message']);
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                echo $_SESSION['error_message'];
                                unset($_SESSION['error_message']);
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Leave Application Form -->
                        <form method="POST" action="apply_leave.php" class="needs-validation" novalidate>
                            <input type="hidden" name="debug" value="1">
                            
                            <div class="form-group mb-3">
                                <label for="leave_type" class="form-label">Leave Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="leave_type" id="leave_type" required>
                                    <option value="">Select Leave Type</option>
                                    <option value="Annual Leave">Annual Leave</option>
                                    <option value="Short Leave">Short Leave</option>
                                    <option value="Compensation Leave">Compensation Leave</option>
                                    <option value="Unpaid Leave">Unpaid Leave</option>
                                    <option value="Maternity Leave">Maternity Leave</option>
                                    <option value="Paternity Leave">Paternity Leave</option>
                                    <option value="Medical Leave">Medical Leave</option>
                                    <option value="Other">Other</option>
                                </select>
                                <div class="invalid-feedback">
                                    Please select a leave type.
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" name="start_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" name="end_date" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <textarea name="reason" class="form-control" rows="3" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Submit Leave Request</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Leave Balance Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Leave Balance</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($leaveBalances as $balance): ?>
                        <div class="leave-balance-item">
                            <div class="d-flex justify-content-between mb-2">
                                <span><?php echo ucfirst($balance['leave_type']); ?> Leave</span>
                                <span class="badge bg-primary">
                                    <?php echo $balance['total_leaves'] - $balance['used_leaves']; ?> remaining
                                </span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo ($balance['used_leaves'] / $balance['total_leaves'] * 100); ?>%">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Recent Leave Requests -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Requests</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT * FROM leave_requests 
                            WHERE user_id = ? 
                            ORDER BY created_at DESC 
                            LIMIT 5
                        ");
                        $stmt->execute([$_SESSION['user_id']]);
                        $recentRequests = $stmt->fetchAll();
                        
                        foreach ($recentRequests as $request):
                        ?>
                        <div class="leave-request-item">
                            <div class="d-flex justify-content-between">
                                <span><?php echo ucfirst($request['leave_type']); ?></span>
                                <span class="badge bg-<?php 
                                    echo $request['status'] === 'approved' ? 'success' : 
                                         ($request['status'] === 'pending' ? 'warning' : 'danger');
                                ?>"><?php echo ucfirst($request['status']); ?></span>
                            </div>
                            <small class="text-muted">
                                <?php echo date('M d', strtotime($request['start_date'])); ?> - 
                                <?php echo date('M d', strtotime($request['end_date'])); ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include your footer and scripts here -->
    <script>
    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Log form data
            const formData = new FormData(form);
            console.log('Form data before submission:');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            form.classList.add('was-validated');
        });
    });
    </script>
</body>
</html>