<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $userId = $_SESSION['user_id'];
        $leaveType = $_POST['leave_type'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $reason = $_POST['reason'];
        $status = 'Pending';
        
        // Calculate number of days
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = $start->diff($end);
        $days = $interval->days + 1;

        // Begin transaction
        $pdo->beginTransaction();

        // Insert leave request
        $stmt = $pdo->prepare("INSERT INTO leaves (user_id, leave_type, start_date, end_date, reason, status, days) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $leaveType, $startDate, $endDate, $reason, $status, $days]);
        
        // Get manager details
        $stmt = $pdo->prepare("
            SELECT u.reporting_manager, m.email as manager_email, m.username as manager_name 
            FROM users u 
            LEFT JOIN users m ON m.username = u.reporting_manager 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $managerInfo = $stmt->fetch();

        // Commit transaction
        $pdo->commit();
        
        // Set success message
        $_SESSION['leave_success'] = true;
        $_SESSION['manager_name'] = $managerInfo['manager_name'] ?? 'your manager';
        
        // Redirect back to dashboard
        header('Location: multi_role_dashboard.php');
        exit();

    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $_SESSION['leave_error'] = "Error submitting leave application. Please try again.";
        error_log("Leave Application Error: " . $e->getMessage());
        header('Location: multi_role_dashboard.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Leaves</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Add Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <!-- Add Custom CSS -->
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            border-radius: 15px 15px 0 0 !important;
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%) !important;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 12px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
            border-color: #4e73df;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            border: none;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.4);
        }
        .alert {
            border-radius: 10px;
            animation: slideIn 0.5s ease;
        }
        @keyframes slideIn {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        .form-label {
            font-weight: 600;
            color: #4a5568;
        }
        .select2-container .select2-selection--single {
            height: 45px;
            padding: 8px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 45px;
        }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <?php if (isset($_SESSION['notification'])): ?>
            <div class="alert alert-<?php echo $_SESSION['notification']['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['notification']['message'];
                unset($_SESSION['notification']); // Clear the notification after displaying
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card shadow">
            <div class="card-header">
                <h3 class="mb-0 text-white">
                    <i class="fas fa-calendar-alt me-2"></i>Apply for Leave
                </h3>
            </div>
            <div class="card-body p-4">
                <form method="POST" id="leaveForm">
                    <div class="mb-3">
                        <label for="leave_type" class="form-label">
                            <i class="fas fa-list-alt me-2"></i>Leave Type
                        </label>
                        <select class="form-select select2" id="leave_type" name="leave_type" required>
                            <option value="">Select Leave Type</option>
                            <option value="Annual">Annual Leave</option>
                            <option value="Short">Short Leave</option>
                            <option value="Compensation">Compensation Leave</option>
                            <option value="Unpaid">Unpaid Leave</option>
                            <option value="Maternity">Maternity Leave</option>
                            <option value="Paternity">Paternity Leave</option>
                            <option value="Medical">Medical Leave</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">
                                    <i class="fas fa-calendar-plus me-2"></i>Start Date
                                </label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">
                                    <i class="fas fa-calendar-minus me-2"></i>End Date
                                </label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="reason" class="form-label">
                            <i class="fas fa-comment-alt me-2"></i>Reason
                        </label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="attachment" class="form-label">
                            <i class="fas fa-paperclip me-2"></i>Attachment (if any)
                        </label>
                        <input type="file" class="form-control" id="attachment" name="attachment">
                    </div>

                    <div class="text-end">
                        <button type="button" class="btn btn-secondary me-2" onclick="window.history.back()">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap-5'
            });
            
            // Existing date validation
            $('#end_date').change(function() {
                var startDate = new Date($('#start_date').val());
                var endDate = new Date($('#end_date').val());
                
                if (endDate < startDate) {
                    alert('End date cannot be earlier than start date');
                    $('#end_date').val('');
                }
            });

            // Add animation to form fields
            $('.form-control, .form-select').focus(function() {
                $(this).parent().addClass('scale-up');
            }).blur(function() {
                $(this).parent().removeClass('scale-up');
            });

            // Smooth scroll to form on page load
            $('html, body').animate({
                scrollTop: $('.card').offset().top - 50
            }, 1000);
        });
    </script>
</body>
</html> 