<?php
// Include database connection
require_once '../../config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone to India Standard Time
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Get attendance ID from URL
$attendanceId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if attendance record exists and belongs to the user
$stmt = $pdo->prepare("SELECT a.*, u.username 
                      FROM attendance a 
                      JOIN users u ON a.user_id = u.id 
                      WHERE a.id = ? AND a.user_id = ?");
$stmt->execute([$attendanceId, $_SESSION['user_id']]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attendance) {
    $_SESSION['error'] = "Invalid attendance record.";
    header('Location: ../../site_supervision.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $workReport = $_POST['work_report'] ?? '';
    $overtime = isset($_POST['overtime']) ? 1 : 0;
    $remarks = $_POST['remarks'] ?? '';
    
    try {
        // Update the attendance record with work report
        $updateStmt = $pdo->prepare("UPDATE attendance 
                                     SET work_report = ?, 
                                         overtime = ?, 
                                         remarks = ?,
                                         modified_at = NOW(),
                                         modified_by = ?
                                     WHERE id = ? AND user_id = ?");
        
        $updateStmt->execute([
            $workReport,
            $overtime,
            $remarks,
            $_SESSION['user_id'],
            $attendanceId,
            $_SESSION['user_id']
        ]);
        
        // Calculate overtime hours if overtime is checked
        if ($overtime) {
            // Assume standard working hours is 8 hours
            $standardHours = 8.0;
            $actualHours = $attendance['working_hours'];
            
            if ($actualHours > $standardHours) {
                $overtimeHours = $actualHours - $standardHours;
                
                $overtimeStmt = $pdo->prepare("UPDATE attendance 
                                              SET overtime_hours = ? 
                                              WHERE id = ?");
                $overtimeStmt->execute([$overtimeHours, $attendanceId]);
            }
        }
        
        $_SESSION['success'] = "Work report submitted successfully.";
        header('Location: ../../site_supervision.php');
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error saving work report: " . $e->getMessage();
    }
}

// Format times for display with IST
$punchInTime = date('h:i A', strtotime($attendance['punch_in'])) . ' IST';
$punchOutTime = date('h:i A', strtotime($attendance['punch_out'])) . ' IST';
$workingHours = $attendance['working_hours'];

// Page title
$pageTitle = "Submit Work Report";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Work Report Form for ArchitectsHive">
    <meta name="theme-color" content="#34495e">
    <title><?= $pageTitle ?> | ArchitectsHive</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #34495e;
            --secondary-color: #e74c3c;
            --light-color: #f5f5f5;
            --dark-color: #2c3e50;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: #c0392b;
            border-color: #c0392b;
        }
        
        .form-check-input:checked {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Submit Work Report</h3>
                <a href="../../site_supervision.php" class="btn btn-sm btn-light">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= $_SESSION['error'] ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="mb-4">
                <h5 class="card-title">Attendance Summary</h5>
                <div class="card bg-light p-3">
                    <div class="summary-item">
                        <span>User:</span>
                        <strong><?= htmlspecialchars($attendance['username']) ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Date:</span>
                        <strong><?= htmlspecialchars($attendance['date']) ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Punch In:</span>
                        <strong class="text-success"><?= $punchInTime ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Punch Out:</span>
                        <strong class="text-danger"><?= $punchOutTime ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Working Hours:</span>
                        <strong><?= $workingHours ?> hours</strong>
                    </div>
                </div>
            </div>
            
            <form method="post" action="">
                <div class="mb-3">
                    <label for="work_report" class="form-label">Work Report <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="work_report" name="work_report" rows="6" required placeholder="Please provide details about your work activities today..."><?= htmlspecialchars($attendance['work_report'] ?? '') ?></textarea>
                    <div class="form-text">Describe tasks completed, challenges faced, and progress made.</div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="overtime" name="overtime" value="1" <?= ($attendance['overtime'] ?? 0) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="overtime">Claim overtime for additional hours worked beyond standard hours</label>
                </div>
                
                <div class="mb-3">
                    <label for="remarks" class="form-label">Additional Remarks</label>
                    <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Any additional notes or remarks..."><?= htmlspecialchars($attendance['remarks'] ?? '') ?></textarea>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Submit Work Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 